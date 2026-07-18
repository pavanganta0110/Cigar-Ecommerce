#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"
set -a
# shellcheck disable=SC1091
source .env
set +a

mkdir -p backups
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
file="backups/compadres-${stamp}.sql.gz"
docker compose exec -T db mariadb-dump   --user="${DB_USER}"   --password="${DB_PASSWORD}"   --single-transaction --routines --triggers "${DB_NAME}" | gzip > "$file"
printf 'Created %s
' "$file"
