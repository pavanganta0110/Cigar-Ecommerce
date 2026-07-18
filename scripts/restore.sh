#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
  printf 'Usage: %s backups/file.sql.gz
' "$0" >&2
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"
set -a
# shellcheck disable=SC1091
source .env
set +a

if [[ "${APP_ENV:-production}" == "production" ]]; then
  printf '%s
' 'Restore is blocked in production by this local script.' >&2
  exit 1
fi

file="$1"
[[ -f "$file" ]] || { printf 'Backup not found: %s
' "$file" >&2; exit 1; }
gzip -dc "$file" | docker compose exec -T db mariadb   --user="${DB_USER}" --password="${DB_PASSWORD}" "${DB_NAME}"
printf 'Restored %s
' "$file"
