#!/usr/bin/env bash
set -euo pipefail

CRON_EXPR="${GEN_CRON:-*/5 * * * *}"
CRON_FILE="/etc/supercronic.cron"

echo "[telemetry] schedule: ${CRON_EXPR}"
echo "${CRON_EXPR} python3 /opt/telemetry/main.py" > "${CRON_FILE}"

exec /usr/local/bin/supercronic "${CRON_FILE}"

