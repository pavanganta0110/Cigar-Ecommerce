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

create_page() {
  local slug="$1"
  local title="$2"
  local content="$3"
  if [[ -z "$(wp_cli post list --post_type=page --name="$slug" --field=ID --format=ids)" ]]; then
    wp_cli post create \
      --post_type=page \
      --post_status=publish \
      --post_title="$title" \
      --post_name="$slug" \
      --post_content="$content"
  fi
}

create_page about 'About Compadres Cigars' '<p><strong>Development placeholder:</strong> Add the approved Compadres company story, team, and business information before production.</p>'
create_page contact 'Contact' '<p><strong>Development placeholder:</strong> Add approved support contact details before production. Customer service details must not be invented.</p>'
create_page shipping-policy 'Shipping Policy' '<p><strong>Legal review required:</strong> Shipping services, destinations, timing, adult-signature terms, and carrier obligations must be approved before production.</p>'
create_page age-policy 'Age Policy' '<p><strong>Legal review required:</strong> This store is intended only for adults age 21 and older. Final identity-verification language and procedures require legal and provider review.</p>'
create_page privacy-policy 'Privacy Policy' '<p><strong>Legal review required:</strong> Replace this placeholder with an approved privacy notice covering actual data practices and providers before production.</p>'
create_page returns-policy 'Returns and Refunds Policy' '<p><strong>Legal review required:</strong> Replace this placeholder with approved return, cancellation, and refund terms before production.</p>'
create_page terms 'Terms and Conditions' '<p><strong>Legal review required:</strong> Replace this placeholder with approved store terms before production.</p>'
create_page restrictions 'State and Local Restrictions' '<p><strong>Legal review required:</strong> Checkout uses configured server-side jurisdiction rules. No nationwide legal rule set is represented as complete.</p>'

privacy_id="$(wp_cli post list --post_type=page --name=privacy-policy --field=ID --format=ids)"
if [[ -n "$privacy_id" ]]; then
  wp_cli post update "$privacy_id" --post_status=publish --post_name=privacy-policy >/dev/null
fi

wp_cli rewrite flush --hard

printf 'WordPress: %s
' "${WP_URL}"
printf 'Mailpit: http://localhost:%s
' "${MAILPIT_UI_PORT:-8025}"
