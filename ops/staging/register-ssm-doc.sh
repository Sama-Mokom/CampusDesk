#!/bin/bash
#
# Register or update the CampusDesk-DeployStaging SSM document.
#
# The tracked document is a template: the AWS account ID appears as
# __AWS_ACCOUNT_ID__ and is injected here from the caller's STS identity. The
# rendered document is written to a temporary file, used for the API call, and
# removed on exit. Never commit a rendered copy.
#
# Requires AWS credentials that may create and update SSM documents in the
# target account.

set -euo pipefail
umask 077

readonly DOCUMENT_NAME="CampusDesk-DeployStaging"
readonly PLACEHOLDER="__AWS_ACCOUNT_ID__"

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
readonly script_dir
readonly TEMPLATE="${script_dir}/CampusDesk-DeployStaging.template.json"

for command_name in aws sed grep mktemp; do
    command -v "$command_name" >/dev/null 2>&1 ||
        {
            printf 'Required command is missing: %s\n' "$command_name" >&2
            exit 1
        }
done

[[ -f "$TEMPLATE" ]] ||
    {
        printf 'Template is missing: %s\n' "$TEMPLATE" >&2
        exit 1
    }

ACCOUNT_ID="$(aws sts get-caller-identity --query Account --output text)"

[[ "$ACCOUNT_ID" =~ ^[0-9]{12}$ ]] ||
    {
        printf 'Unable to determine a valid 12-digit AWS account ID from STS.\n' >&2
        exit 1
    }

redact() {
    local value="${1//$ACCOUNT_ID/<ACCOUNT_ID>}"
    printf '%s\n' "$value" >&2
}

remind_version_pin() {
    printf 'If deploy-staging.yml pins an older version, update --document-version in the same reviewed change.\n'
}

rendered="$(mktemp)"
trap 'rm -f "$rendered"' EXIT

sed "s/${PLACEHOLDER}/${ACCOUNT_ID}/g" "$TEMPLATE" >"$rendered"

if grep -qF "$PLACEHOLDER" "$rendered"; then
    printf 'The rendered document still contains the account placeholder.\n' >&2
    exit 1
fi

set +e
describe_output="$(aws ssm describe-document --name "$DOCUMENT_NAME" 2>&1 >/dev/null)"
describe_status=$?
set -e

if (( describe_status == 0 )); then
    printf 'Updating %s.\n' "$DOCUMENT_NAME"

    set +e
    update_output="$(
        aws ssm update-document \
            --name "$DOCUMENT_NAME" \
            --document-version "\$LATEST" \
            --document-format JSON \
            --content "file://${rendered}" \
            --query 'DocumentDescription.DocumentVersion' \
            --output text 2>&1
    )"
    update_status=$?
    set -e

    if (( update_status != 0 )); then
        if grep -qiE 'identical|same content|no changes' <<<"$update_output"; then
            printf 'Document content is unchanged; nothing to do.\n'
            exit 0
        fi

        redact "$update_output"
        exit "$update_status"
    fi

    [[ "$update_output" =~ ^[0-9]+$ ]] ||
        {
            printf 'SSM did not return a valid document version.\n' >&2
            exit 1
        }

    aws ssm update-document-default-version \
        --name "$DOCUMENT_NAME" \
        --document-version "$update_output" >/dev/null

    printf 'Updated %s to version %s and set it as the default.\n' \
        "$DOCUMENT_NAME" "$update_output"
    remind_version_pin
elif grep -qiE 'DocumentNotFound|ResourceNotFound|does not exist' <<<"$describe_output"; then
    printf 'Creating %s.\n' "$DOCUMENT_NAME"

    set +e
    create_output="$(
        aws ssm create-document \
            --name "$DOCUMENT_NAME" \
            --document-type Command \
            --document-format JSON \
            --content "file://${rendered}" \
            --query 'DocumentDescription.DocumentVersion' \
            --output text 2>&1
    )"
    create_status=$?
    set -e

    if (( create_status != 0 )); then
        redact "$create_output"
        exit "$create_status"
    fi

    [[ "$create_output" =~ ^[0-9]+$ ]] ||
        {
            printf 'SSM did not return a valid document version.\n' >&2
            exit 1
        }

    printf 'Created %s version %s.\n' "$DOCUMENT_NAME" "$create_output"
    remind_version_pin
else
    redact "$describe_output"
    exit "$describe_status"
fi
