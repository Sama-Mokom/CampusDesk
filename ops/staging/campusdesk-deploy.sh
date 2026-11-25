#!/bin/bash
#
# Reviewed copy of the CampusDesk staging host deployment script.
# Installed on the staging host as /usr/local/sbin/campusdesk-deploy
# (root:root, mode 0750). The repository copy is the source of truth; install
# changes through a separately reviewed host update.

set -Eeuo pipefail
umask 077
export AWS_PAGER=""

readonly AWS_REGION="eu-north-1"
readonly BACKEND_REPOSITORY="campusdesk_backend"
readonly FRONTEND_REPOSITORY="campusdesk_frontend"

readonly COMPOSE_FILE="/opt/campusdesk/compose.staging.yaml"
readonly ENV_FILE="/opt/campusdesk/.env.staging"
readonly RELEASE_RECORD="/opt/campusdesk/release.json"
readonly LAST_DEPLOYED_PARAMETER="/campusdesk/staging/last-deployed-sha"
readonly STATE_DIR="/var/lib/campusdesk-deploy"
readonly LOCK_FILE="/run/lock/campusdesk-deploy.lock"

release_sha=""
backend_image=""
frontend_image=""
runtime_build_sha=""
runtime_build_time=""
frontend_build_sha=""
frontend_meta_sha=""

previous_release_sha="unknown"
action=""
migration_step_completed="false"

maintenance_started=false
migration_started=false
migration_succeeded=false
env_updated=false
candidate_env=""
work_dir=""
logged_in=false

log() {
    printf '%s %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$*"
}

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

while (( $# > 0 )); do
    case "$1" in
        --release-sha)
            (( $# >= 2 )) || die "--release-sha requires a value"
            [[ -z "$release_sha" ]] || die "--release-sha was provided more than once"
            release_sha="$2"
            shift 2
            ;;
        --backend-image)
            (( $# >= 2 )) || die "--backend-image requires a value"
            [[ -z "$backend_image" ]] || die "--backend-image was provided more than once"
            backend_image="$2"
            shift 2
            ;;
        --frontend-image)
            (( $# >= 2 )) || die "--frontend-image requires a value"
            [[ -z "$frontend_image" ]] || die "--frontend-image was provided more than once"
            frontend_image="$2"
            shift 2
            ;;
        *)
            die "Unknown argument: $1"
            ;;
    esac
done

[[ "$EUID" -eq 0 ]] || die "This script must run as root"
[[ "$release_sha" =~ ^[0-9a-f]{40}$ ]] || die "Invalid release SHA"

for command_name in aws docker flock curl awk mktemp stat; do
    command -v "$command_name" >/dev/null ||
        die "Required command is missing: $command_name"
done

[[ -f "$COMPOSE_FILE" ]] || die "Compose file is missing"
[[ -f "$ENV_FILE" ]] || die "Staging environment file is missing"
[[ "$(stat -c '%a' "$ENV_FILE")" == "600" ]] ||
    die "Staging environment file must have mode 600"

docker compose version >/dev/null

account_id="$(
    aws sts get-caller-identity \
        --query Account \
        --output text
)"

[[ "$account_id" =~ ^[0-9]{12}$ ]] ||
    die "Unable to determine the AWS account"

registry="${account_id}.dkr.ecr.${AWS_REGION}.amazonaws.com"

backend_pattern="^${account_id}\\.dkr\\.ecr\\.${AWS_REGION}\\.amazonaws\\.com/${BACKEND_REPOSITORY}@sha256:[0-9a-f]{64}$"
frontend_pattern="^${account_id}\\.dkr\\.ecr\\.${AWS_REGION}\\.amazonaws\\.com/${FRONTEND_REPOSITORY}@sha256:[0-9a-f]{64}$"

[[ "$backend_image" =~ $backend_pattern ]] ||
    die "Backend image is outside the permitted repository"

[[ "$frontend_image" =~ $frontend_pattern ]] ||
    die "Frontend image is outside the permitted repository"

backend_digest="${backend_image##*@}"
frontend_digest="${frontend_image##*@}"

install -d -o root -g root -m 0700 "$STATE_DIR"

exec 9>"$LOCK_FILE"
flock -n 9 || die "Another CampusDesk deployment is already running"

work_dir="$(mktemp -d /run/campusdesk-deploy.XXXXXX)"
export DOCKER_CONFIG="${work_dir}/docker"
install -d -o root -g root -m 0700 "$DOCKER_CONFIG"

compose=(
    docker compose
    --env-file "$ENV_FILE"
    --file "$COMPOSE_FILE"
)

read_env_value() {
    local key="$1"
    local file="$2"
    local count

    count="$(
        awk -F= -v key="$key" '
            $1 == key { count++ }
            END { print count + 0 }
        ' "$file"
    )"

    [[ "$count" -eq 1 ]] ||
        die "Expected exactly one ${key} entry in ${file}"

    awk -F= -v key="$key" '
        $1 == key {
            sub(/^[^=]*=/, "")
            print
            exit
        }
    ' "$file"
}

render_env_file() {
    local source_file="$1"
    local target_file="$2"
    local selected_backend="$3"
    local selected_frontend="$4"

    awk \
        -v backend="$selected_backend" \
        -v frontend="$selected_frontend" '
        /^BACKEND_IMAGE=/ {
            print "BACKEND_IMAGE=" backend
            backend_count++
            next
        }

        /^FRONTEND_IMAGE=/ {
            print "FRONTEND_IMAGE=" frontend
            frontend_count++
            next
        }

        { print }

        END {
            if (backend_count != 1 || frontend_count != 1) {
                exit 42
            }
        }
    ' "$source_file" >"$target_file" || return 1

    chmod --reference="$source_file" "$target_file"
    chown --reference="$source_file" "$target_file"
}

validate_compose() {
    local selected_env="$1"

    docker compose \
        --env-file "$selected_env" \
        --file "$COMPOSE_FILE" \
        config --quiet
}

resolve_ecr_digest() {
    local repository="$1"

    aws ecr batch-get-image \
        --region "$AWS_REGION" \
        --repository-name "$repository" \
        --image-ids "imageTag=${release_sha}" \
        --query 'images[0].imageId.imageDigest' \
        --output text
}

write_state() {
    local destination="$1"
    local recorded_sha="$2"
    local recorded_backend="$3"
    local recorded_frontend="$4"
    local temporary_state

    temporary_state="$(mktemp "${STATE_DIR}/state.XXXXXX")"

    {
        printf 'RECORDED_AT=%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
        printf 'RELEASE_SHA=%s\n' "$recorded_sha"
        printf 'BACKEND_IMAGE=%s\n' "$recorded_backend"
        printf 'FRONTEND_IMAGE=%s\n' "$recorded_frontend"
    } >"$temporary_state"

    chmod 0600 "$temporary_state"
    chown root:root "$temporary_state"
    mv -f "$temporary_state" "$destination"
}

read_previous_release_sha() {
    local state_file="${STATE_DIR}/current-release.env"
    local value=""

    if [[ ! -f "$state_file" ]]; then
        printf 'unknown'
        return 0
    fi

    value="$(
        awk -F= '
            $1 == "RELEASE_SHA" {
                sub(/^[^=]*=/, "")
                print
                exit
            }
        ' "$state_file"
    )"

    if [[ "$value" =~ ^[0-9a-f]{40}$ ]]; then
        printf '%s' "$value"
    else
        printf 'unknown'
    fi
}

write_release_record() {
    local temporary_record

    temporary_record="$(mktemp "${RELEASE_RECORD}.XXXXXX")"

    {
        printf '{\n'
        printf '  "release_sha": "%s",\n' "$release_sha"
        printf '  "backend_digest": "%s",\n' "$backend_digest"
        printf '  "frontend_digest": "%s",\n' "$frontend_digest"
        printf '  "build_sha": "%s",\n' "$runtime_build_sha"
        printf '  "build_time": "%s",\n' "$runtime_build_time"
        printf '  "frontend_build_sha": "%s",\n' "$frontend_build_sha"
        printf '  "frontend_meta_sha": "%s",\n' "$frontend_meta_sha"
        printf '  "previous_release_sha": "%s",\n' "$previous_release_sha"
        printf '  "action": "%s",\n' "$action"
        printf '  "migration_step_completed": %s,\n' "$migration_step_completed"
        printf '  "recorded_at": "%s"\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
        printf '}\n'
    } >"$temporary_record"

    chmod 0644 "$temporary_record"
    chown root:root "$temporary_record"
    mv -f "$temporary_record" "$RELEASE_RECORD"
}

verify_image_revision() {
    local image="$1"
    local revision

    revision="$(
        docker image inspect "$image" \
            --format '{{index .Config.Labels "org.opencontainers.image.revision"}}'
    )"

    [[ "$revision" == "$release_sha" ]] ||
        die "Image ${image} declares revision '${revision}', expected ${release_sha}"
}

record_last_deployed() {
    aws ssm put-parameter \
        --region "$AWS_REGION" \
        --name "$LAST_DEPLOYED_PARAMETER" \
        --type String \
        --value "$release_sha" \
        --overwrite >/dev/null
}

smoke_test() {
    local frontend_status
    local api_status

    frontend_status="$(
        curl \
            --silent \
            --show-error \
            --output /dev/null \
            --write-out '%{http_code}' \
            http://127.0.0.1:8080/
    )"

    [[ "$frontend_status" == "200" ]] ||
        die "Frontend smoke test returned HTTP ${frontend_status}"

    api_status="$(
        curl \
            --silent \
            --show-error \
            --output /dev/null \
            --write-out '%{http_code}' \
            --header 'Accept: application/json' \
            http://127.0.0.1:8080/api/user
    )"

    [[ "$api_status" == "401" ]] ||
        die "API smoke test returned HTTP ${api_status}"
}

verify_frontend_config() {
    local expected_key
    local actual_key
    local headers

    expected_key="$(read_env_value REVERB_APP_KEY "$ENV_FILE")"

    [[ -n "$expected_key" ]] ||
        die "REVERB_APP_KEY is missing from the staging environment"

    actual_key="$(
        "${compose[@]}" exec -T frontend sh -c \
            'sed -e "s/^window.__APP_CONFIG__ = //" -e "s/;$//" /usr/share/nginx/html/config.js | jq -r .reverbAppKey' \
            2>/dev/null
    )" ||
        die "Could not read the frontend runtime configuration"

    [[ "$actual_key" == "$expected_key" ]] ||
        die "Frontend runtime Reverb key does not match the staging environment"

    headers="$(curl --silent --show-error --head http://127.0.0.1:8080/config.js)" ||
        die "Frontend runtime configuration is not served"

    grep -qi '^Cache-Control:.*no-store' <<< "$headers" ||
        die "Frontend runtime configuration is cacheable"

    log "Frontend runtime configuration matches the staging Reverb key."
}

verify_runtime_sha() {
    local version_json
    local frontend_config
    local index_html

    version_json="$(
        curl --silent --show-error --fail http://127.0.0.1:8080/api/version
    )" ||
        die "The runtime version endpoint is unavailable"

    runtime_build_sha="$(
        sed -n 's/.*"build_sha"[[:space:]]*:[[:space:]]*"\([0-9a-f]\{40\}\)".*/\1/p' <<< "$version_json"
    )"
    runtime_build_time="$(
        sed -n 's/.*"build_time"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' <<< "$version_json"
    )"

    [[ "$runtime_build_sha" == "$release_sha" ]] ||
        die "Runtime build SHA '${runtime_build_sha}' does not match release ${release_sha}"
    [[ -n "$runtime_build_time" ]] ||
        die "Runtime build time is missing from /api/version"

    frontend_config="$(
        "${compose[@]}" exec -T frontend cat /usr/share/nginx/html/config.js 2>/dev/null
    )" ||
        die "Could not read the frontend runtime configuration"

    frontend_build_sha="$(
        sed -e 's/^window.__APP_CONFIG__ = //' -e 's/;$//' <<< "$frontend_config" |
            "${compose[@]}" exec -T frontend jq -r '.buildSha // empty' 2>/dev/null
    )" ||
        die "Could not read the frontend build SHA"

    [[ "$frontend_build_sha" == "$release_sha" ]] ||
        die "Frontend build SHA '${frontend_build_sha}' does not match release ${release_sha}"

    index_html="$(
        curl --silent --show-error --fail http://127.0.0.1:8080/
    )" ||
        die "The frontend page is unavailable"

    frontend_meta_sha="$(
        sed -n 's/.*<meta name="app-build" content="\([0-9a-f]\{40\}\)".*/\1/p' <<< "$index_html"
    )"

    [[ "$frontend_meta_sha" == "$release_sha" ]] ||
        die "Frontend app-build meta '${frontend_meta_sha}' does not match release ${release_sha}"

    log "runtime_sha=${runtime_build_sha} runtime_build_time=${runtime_build_time} frontend_build_sha=${frontend_build_sha} frontend_meta_sha=${frontend_meta_sha}"
}

wait_for_service() {
    local service="$1"
    local expected="$2"
    local timeout_seconds="$3"
    local start_time="$SECONDS"
    local container_id=""
    local state=""
    local health=""

    while (( SECONDS - start_time < timeout_seconds )); do
        container_id="$("${compose[@]}" ps -q "$service" 2>/dev/null || true)"

        if [[ -n "$container_id" ]]; then
            state="$(
                docker inspect \
                    --format '{{.State.Status}}' \
                    "$container_id"
            )"

            health="$(
                docker inspect \
                    --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' \
                    "$container_id"
            )"

            if [[ "$expected" == "healthy" && "$health" == "healthy" ]]; then
                return 0
            fi

            if [[ "$expected" == "running" && "$state" == "running" ]]; then
                return 0
            fi
        fi

        sleep 5
    done

    printf 'Service %s did not become %s\n' "$service" "$expected" >&2
    return 1
}

running_matches() {
    local service="$1"
    local expected="$2"
    local container_id=""
    local actual=""

    container_id="$("${compose[@]}" ps -q "$service" 2>/dev/null || true)"
    [[ -n "$container_id" ]] || return 1

    actual="$(
        docker inspect \
            --format '{{.Config.Image}}' \
            "$container_id" 2>/dev/null || true
    )"

    [[ "$actual" == "$expected" ]]
}

previous_backend=""
previous_frontend=""

restore_previous_images() {
    local restore_file

    restore_file="$(mktemp "${ENV_FILE}.restore.XXXXXX")"

    if ! render_env_file \
        "$ENV_FILE" \
        "$restore_file" \
        "$previous_backend" \
        "$previous_frontend"; then
        rm -f "$restore_file"
        return 1
    fi

    if ! validate_compose "$restore_file"; then
        rm -f "$restore_file"
        return 1
    fi

    mv -f "$restore_file" "$ENV_FILE"
    env_updated=false
}

cleanup() {
    local status="$?"

    trap - EXIT
    set +e

    if (( status != 0 )); then
        if [[ "$migration_started" == "true" ]]; then
            if [[ "$migration_succeeded" != "true" ]]; then
                log "Migration failed; restoring previous image references."

                if [[ "$env_updated" == "true" ]]; then
                    restore_previous_images ||
                        log "WARNING: previous image references could not be restored."
                fi

                log "Application services remain stopped for manual recovery."
            else
                log "Migration succeeded but a later deployment step failed."
                log "Automatic image rollback is intentionally disabled."
            fi
        elif [[ "$maintenance_started" == "true" ]]; then
            log "Failure occurred before migration; restoring the previous application."

            if [[ "$env_updated" == "true" ]]; then
                restore_previous_images ||
                    log "WARNING: previous image references could not be restored."
            fi

            "${compose[@]}" up -d backend worker frontend ||
                log "WARNING: previous application services could not be restarted."
        fi
    fi

    if [[ "$logged_in" == "true" ]]; then
        docker logout "$registry" >/dev/null 2>&1 || true
    fi

    if [[ -n "$candidate_env" && -e "$candidate_env" ]]; then
        rm -f "$candidate_env"
    fi

    if [[ -n "$work_dir" && -d "$work_dir" ]]; then
        rm -rf -- "$work_dir"
    fi

    exit "$status"
}

trap cleanup EXIT

previous_backend="$(read_env_value BACKEND_IMAGE "$ENV_FILE")"
previous_frontend="$(read_env_value FRONTEND_IMAGE "$ENV_FILE")"
previous_release_sha="$(read_previous_release_sha)"

validate_compose "$ENV_FILE"

log "release_sha=${release_sha} backend_digest=${backend_digest} frontend_digest=${frontend_digest} previous_release_sha=${previous_release_sha}"

resolved_backend_digest="$(resolve_ecr_digest "$BACKEND_REPOSITORY")"
resolved_frontend_digest="$(resolve_ecr_digest "$FRONTEND_REPOSITORY")"

[[ "$resolved_backend_digest" == "$backend_digest" ]] ||
    die "Backend digest does not match the requested release SHA"

[[ "$resolved_frontend_digest" == "$frontend_digest" ]] ||
    die "Frontend digest does not match the requested release SHA"

recorded_release_matches=false
if [[ "$previous_backend" == "$backend_image" &&
      "$previous_frontend" == "$frontend_image" ]]; then
    recorded_release_matches=true
fi

running_release_matches=false
if running_matches backend "$backend_image" &&
   running_matches worker "$backend_image" &&
   running_matches reverb "$backend_image" &&
   running_matches scheduler "$backend_image" &&
   running_matches frontend "$frontend_image"; then
    running_release_matches=true
fi

if [[ "$recorded_release_matches" == "true" && "$running_release_matches" == "true" ]]; then
    log "Release ${release_sha} is already deployed; running health checks only."

    wait_for_service db healthy 60
    wait_for_service backend healthy 60
    wait_for_service worker running 60
    wait_for_service frontend running 60
    smoke_test
    verify_frontend_config
    verify_runtime_sha

    action="noop"

    write_state \
        "${STATE_DIR}/current-release.env" \
        "$release_sha" \
        "$backend_image" \
        "$frontend_image"

    write_release_record

    if ! record_last_deployed; then
        log "ERROR: application is deployed but ${LAST_DEPLOYED_PARAMETER} could not be updated."
        exit 1
    fi

    log "action=${action} migration_step_completed=${migration_step_completed}"
    log "Existing deployment is healthy."
    exit 0
fi

if [[ "$recorded_release_matches" == "true" ]]; then
    log "The recorded release matches but the running containers do not; proceeding with a recreate."
fi

action="recreate"

log "action=${action} migration_step_completed=${migration_step_completed}"
log "Authenticating to ECR."

aws ecr get-login-password --region "$AWS_REGION" |
    docker login \
        --username AWS \
        --password-stdin \
        "$registry" >/dev/null

logged_in=true

log "Pulling immutable backend and frontend images."

docker pull "$backend_image"
docker pull "$frontend_image"

docker image inspect "$backend_image" >/dev/null
docker image inspect "$frontend_image" >/dev/null

verify_image_revision "$backend_image"
verify_image_revision "$frontend_image"

docker logout "$registry" >/dev/null
logged_in=false

candidate_env="$(mktemp "${ENV_FILE}.candidate.XXXXXX")"

render_env_file \
    "$ENV_FILE" \
    "$candidate_env" \
    "$backend_image" \
    "$frontend_image" ||
    die "Unable to construct the candidate environment file"

validate_compose "$candidate_env"

write_state \
    "${STATE_DIR}/previous-images.env" \
    "unknown" \
    "$previous_backend" \
    "$previous_frontend"

log "Entering the staging maintenance window."

maintenance_started=true

"${compose[@]}" stop frontend
"${compose[@]}" stop --timeout 70 worker
"${compose[@]}" stop backend

mv -f "$candidate_env" "$ENV_FILE"
candidate_env=""
env_updated=true

migration_started=true

log "Running database migrations exactly once."

"${compose[@]}" run --rm backend php artisan migrate --force

migration_succeeded=true
migration_step_completed="true"

log "Starting the new application services."

"${compose[@]}" up -d --force-recreate backend worker frontend

wait_for_service db healthy 120
wait_for_service backend healthy 180
wait_for_service worker running 120
wait_for_service frontend running 120

smoke_test
verify_frontend_config
verify_runtime_sha

write_state \
    "${STATE_DIR}/current-release.env" \
    "$release_sha" \
    "$backend_image" \
    "$frontend_image"

write_release_record

if ! record_last_deployed; then
    log "ERROR: application is deployed but ${LAST_DEPLOYED_PARAMETER} could not be updated."
    exit 1
fi

log "action=${action} migration_step_completed=${migration_step_completed}"
log "CampusDesk staging deployment completed successfully."
