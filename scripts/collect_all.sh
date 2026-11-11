#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

HOSTS_FILE="${HOSTS_FILE:-$ROOT_DIR/config/hosts.conf}"
OUTPUT_DIR="${OUTPUT_DIR:-$ROOT_DIR/data}"
COLLECTOR="${COLLECTOR:-$ROOT_DIR/scripts/collect_metrics.sh}"

mkdir -p "$OUTPUT_DIR"

if [[ ! -f "$HOSTS_FILE" ]]; then
    echo "Hosts file not found: $HOSTS_FILE" >&2
    exit 1
fi

while IFS='|' read -r label target remote_script; do

    # Skip empty lines and comments
    [[ -z "${label//[[:space:]]/}" || "$label" == \#* ]] && continue

    remote_script="${remote_script:-/opt/linux-infrastructure-monitor/scripts/collect_metrics.sh}"

    tmp="$OUTPUT_DIR/${label}.json.tmp"
    dest="$OUTPUT_DIR/${label}.json"

    echo "Collecting metrics from $label..."

    if [[ "$target" == "LOCAL" ]]; then

        if bash "$COLLECTOR" > "$tmp"; then
            mv "$tmp" "$dest"
            echo "SUCCESS: $label data collected."
        else
            rm -f "$tmp"
            echo "ERROR: Local collection failed for $label." >&2
        fi

    else

        if ssh -n \
            -o BatchMode=yes \
            -o ConnectTimeout=5 \
            "$target" \
            "bash '$remote_script'" > "$tmp"; then

            mv "$tmp" "$dest"
            echo "SUCCESS: $label data collected."

        else
            rm -f "$tmp"

            printf '{"hostname":"%s","status":"UNREACHABLE","status_reason":"SSH collection failed","collected_at":"%s"}\n' \
                "$label" "$(date -Is)" > "$dest"

            echo "ERROR: Collection failed for $label." >&2
        fi

    fi

done < "$HOSTS_FILE"

echo "-----------------------------------"
echo "InfraWatch collection completed."
echo "Output directory: $OUTPUT_DIR"
