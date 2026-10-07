#!/usr/bin/env bash

set -Eeuo pipefail

readonly RECOVERY_BACKUP_ROOT="/var/backups/campusdesk/backup-sets"
readonly RECOVERY_LOCK_FILE="/run/lock/campusdesk-recovery.lock"

recovery_fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

recovery_require_root() {
  ((EUID == 0)) || recovery_fail "Run this script as root."
}

recovery_require_command() {
  command -v "$1" >/dev/null 2>&1 ||
    recovery_fail "Required command is unavailable: $1"
}

recovery_normalize_attachment_paths() {
  LC_ALL=C awk '
    {
      path = $0

      if (index(path, "attachments/") != 1) {
        exit 1
      }

      relative_path = substr(path, 13)

      if (relative_path == "" ||
          relative_path ~ /[[:cntrl:]\\]/ ||
          substr(relative_path, 1, 1) == "/" ||
          substr(relative_path, length(relative_path), 1) == "/") {
        exit 1
      }

      segment_count = split(relative_path, segments, "/")

      for (segment = 1; segment <= segment_count; segment++) {
        if (segments[segment] == "" ||
            segments[segment] == "." ||
            segments[segment] == "..") {
          exit 1
        }
      }

      print relative_path
    }
  ' | LC_ALL=C sort
}

recovery_validate_attachment_archive_members() {
  local archive_path="$1"

  python3 - "$archive_path" <<'PY' ||
import sys
import tarfile
import unicodedata


def reject(message):
    print(f"ERROR: {message}", file=sys.stderr)
    raise SystemExit(1)


archive_path = sys.argv[1]

try:
    with tarfile.open(archive_path, mode="r:gz") as archive:
        for member in archive.getmembers():
            name = member.name

            if not name:
                reject("The attachment archive contains an empty member path.")

            if name.startswith("/"):
                reject("The attachment archive contains an absolute path.")

            if "\\" in name:
                reject("The attachment archive contains a backslash path.")

            if any(unicodedata.category(character) == "Cc" for character in name):
                reject("The attachment archive contains a control character.")

            if name == ".":
                if not member.isdir():
                    reject("The attachment archive root is not a directory.")
                continue

            if not name.startswith("./"):
                reject("The attachment archive contains an unexpected top-level path.")

            relative_name = name[2:]

            if member.isdir():
                relative_name = relative_name.rstrip("/")

            if not relative_name:
                reject("The attachment archive contains an empty relative path.")

            components = relative_name.split("/")

            if any(component in ("", ".", "..") for component in components):
                reject("The attachment archive contains an unsafe path component.")

            if not (member.isfile() or member.isdir()):
                reject("The attachment archive contains a non-regular member.")
except (OSError, tarfile.TarError) as error:
    reject(f"The attachment archive cannot be inspected: {error}")
PY
  recovery_fail "The attachment archive failed member validation."
}

recovery_latest_backup_id() {
  local backup_id

  backup_id="$(
    find "$RECOVERY_BACKUP_ROOT" \
      -mindepth 1 \
      -maxdepth 1 \
      -type d \
      -name '????????T??????Z' \
      -printf '%f\n' |
      sort |
      tail -n 1
  )"

  [[ -n "$backup_id" ]] || recovery_fail "No recovery set is available."
  printf '%s\n' "$backup_id"
}

recovery_backup_directory() {
  local backup_id="$1"
  local backup_directory

  [[ "$backup_id" =~ ^[0-9]{8}T[0-9]{6}Z$ ]] ||
    recovery_fail "The backup ID is invalid."

  backup_directory="${RECOVERY_BACKUP_ROOT}/${backup_id}"

  [[ -d "$backup_directory" ]] ||
    recovery_fail "The requested recovery set does not exist."

  [[ ! -L "$backup_directory" ]] ||
    recovery_fail "The recovery set must not be a symbolic link."

  printf '%s\n' "$backup_directory"
}

recovery_metadata_value() {
  local backup_directory="$1"
  local key="$2"
  local value

  value="$(
    awk -F= -v key="$key" '
      $1 == key {
        if (found) {
          exit 2
        }

        found = 1
        print substr($0, index($0, "=") + 1)
      }

      END {
        if (!found) {
          exit 1
        }
      }
    ' "$backup_directory/recovery-metadata.txt"
  )" || recovery_fail "Recovery metadata is missing or ambiguous: $key"

  printf '%s\n' "$value"
}

recovery_validate_release_image_reference() {
  local image="$1"
  local component="$2"
  local repository_component

  repository_component='[a-z0-9]+([._-]+[a-z0-9]+)*'

  [[ "$image" =~ ^${repository_component}(:[0-9]+)?(/${repository_component})*@sha256:[0-9a-f]{64}$ ]] ||
    recovery_fail "The $component image reference must be digest-qualified as repository@sha256:<64 lowercase hexadecimal characters>."
}

recovery_release_image() {
  local backup_directory="$1"
  local component="$2"
  local image

  image="$(
    awk -F= -v component="$component" '
      $1 == component {
        if (found) {
          exit 2
        }

        found = 1
        print substr($0, index($0, "=") + 1)
      }

      END {
        if (!found) {
          exit 1
        }
      }
    ' "$backup_directory/release-images.txt"
  )" || recovery_fail "Release image metadata is missing or ambiguous: $component"

  recovery_validate_release_image_reference "$image" "$component"

  printf '%s\n' "$image"
}

recovery_validate_backup_set() {
  local backup_directory="$1"
  local artifact
  local artifact_path
  local release_component

  [[ -d "$backup_directory" ]] ||
    recovery_fail "The recovery-set directory is unavailable."

  [[ ! -L "$backup_directory" ]] ||
    recovery_fail "The recovery-set directory must not be a symbolic link."

  [[ "$(stat --format '%U:%G' -- "$backup_directory")" == "root:root" ]] ||
    recovery_fail "The recovery-set directory must be owned by root:root."

  [[ "$(stat --format '%a' -- "$backup_directory")" == "700" ]] ||
    recovery_fail "The recovery-set directory must have mode 0700."

  for artifact in \
    database.sql.gz \
    database-counts.txt \
    attachments.tar.gz \
    attachment-files.sha256 \
    attachment-db-paths.txt \
    attachment-archive-paths.txt \
    release-images.txt \
    recovery-metadata.txt \
    SHA256SUMS
  do
    artifact_path="${backup_directory}/${artifact}"

    [[ -f "$artifact_path" ]] ||
      recovery_fail "A required recovery artifact is missing: $artifact"

    [[ ! -L "$artifact_path" ]] ||
      recovery_fail "Recovery artifacts must not be symbolic links: $artifact"

    [[ "$(stat --format '%U:%G' -- "$artifact_path")" == "root:root" ]] ||
      recovery_fail "Recovery artifacts must be owned by root:root: $artifact"

    [[ "$(stat --format '%a' -- "$artifact_path")" == "600" ]] ||
      recovery_fail "Recovery artifacts must have mode 0600: $artifact"
  done

  (
    cd "$backup_directory"
    sha256sum --check --status SHA256SUMS
  ) || recovery_fail "Recovery-set checksum validation failed."

  gzip --test "$backup_directory/database.sql.gz" ||
    recovery_fail "The database archive is invalid."

  gzip --test "$backup_directory/attachments.tar.gz" ||
    recovery_fail "The attachment archive is invalid."

  recovery_validate_attachment_archive_members \
    "$backup_directory/attachments.tar.gz"

  for release_component in backend frontend mysql; do
    recovery_release_image \
      "$backup_directory" \
      "$release_component" \
      >/dev/null
  done

  cmp --silent \
    "$backup_directory/attachment-db-paths.txt" \
    "$backup_directory/attachment-archive-paths.txt" ||
    recovery_fail "Captured database and archive attachment paths do not match."
}
