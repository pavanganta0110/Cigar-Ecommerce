#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ ! -f .env ]]; then
  printf '%s
' 'Missing .env. Copy .env.example to .env and replace local passwords.' >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

if [[ "${APP_ENV:-production}" == "production" ]]; then
  printf '%s
' 'Bootstrap is intentionally blocked in production.' >&2
  exit 1
fi

wp_cli() {
  docker compose run --rm wpcli "$@"
}

docker compose up -d db mailpit wordpress

until wp_cli core is-installed >/dev/null 2>&1; do
  if wp_cli core install     --url="${WP_URL}"     --title="${WP_TITLE}"     --admin_user="${WP_ADMIN_USER}"     --admin_password="${WP_ADMIN_PASSWORD}"     --admin_email="${WP_ADMIN_EMAIL}"     --skip-email; then
    break
  fi
  sleep 3
done

wp_cli option update blog_public 0
wp_cli option update users_can_register 1
wp_cli option update default_role customer
wp_cli rewrite structure '/%postname%/' --hard
wp_cli plugin install woocommerce --version="${WOOCOMMERCE_VERSION}" --activate
wp_cli theme activate compadres
wp_cli plugin activate compadres-commerce
wp_cli wc tool run install_pages --user="${WP_ADMIN_USER}" || true
wp_cli option update woocommerce_enable_guest_checkout yes
wp_cli option update woocommerce_enable_signup_and_login_from_checkout yes
wp_cli option update woocommerce_enable_myaccount_registration yes
wp_cli option update woocommerce_currency USD
wp_cli rewrite flush --hard

printf 'WordPress: %s
' "${WP_URL}"
printf 'Mailpit: http://localhost:%s
' "${MAILPIT_UI_PORT:-8025}"
