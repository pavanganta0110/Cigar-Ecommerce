#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

set -a
# shellcheck disable=SC1091
source .env
set +a

curl -fsS "${WP_URL}/wp-json/" >/dev/null
docker compose run --rm wpcli core is-installed
docker compose run --rm wpcli plugin is-active woocommerce
docker compose run --rm wpcli plugin is-active compadres-commerce
docker compose run --rm wpcli theme is-active compadres
curl -fsS "http://localhost:${MAILPIT_UI_PORT:-8025}/api/v1/info" >/dev/null
printf '%s
' 'Compadres local stack is healthy.'
