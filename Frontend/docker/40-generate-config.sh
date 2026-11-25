#!/bin/sh
set -eu

if [ -z "${REVERB_APP_KEY:-}" ]; then
    echo "REVERB_APP_KEY is required" >&2
    exit 1
fi

config="$(jq -cn \
    --arg key "$REVERB_APP_KEY" \
    --arg sha "${APP_BUILD_SHA:-unknown}" \
    '{reverbAppKey:$key, buildSha:$sha}')"
printf 'window.__APP_CONFIG__ = %s;\n' "$config" \
    > /usr/share/nginx/html/config.js
chmod 0644 /usr/share/nginx/html/config.js
