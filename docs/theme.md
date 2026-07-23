# Compadres theme

The `compadres` theme owns presentation only. Catalog, compliance, integrations, reports, and durable commerce metadata belong in the Compadres Commerce plugin.

## Editable content

Public informational pages are regular WordPress pages created idempotently by `scripts/bootstrap.sh`. Editors can replace their placeholder bodies in the block editor. Policy placeholders are deliberately marked **Legal review required** and must not be treated as approved production terms.

Storefront settings use native WordPress features:

- **Appearance → Customize → Site Identity:** logo and favicon/site icon.
- **Appearance → Customize → Compadres Storefront:** hero image and copy, primary CTA, footer description, support email, social URLs, accent, age notice, and shipping notice.

All custom settings have WordPress sanitization callbacks. Social and support links are omitted until configured.

## WooCommerce rendering

The theme declares WooCommerce support and uses WooCommerce hooks, shortcodes, APIs, and `woocommerce_content()` rather than copying core business behavior.

There are currently **no copied WooCommerce core template overrides**, so there is no upstream template-version inventory to maintain. `woocommerce.php` is the theme's supported wrapper, and `taxonomy-compadres_brand.php` is a WordPress taxonomy template owned by this project. If a file is later copied from `woocommerce/templates`, record its upstream `@version` here and verify it on every WooCommerce update.

## Empty states and fixtures

Homepage commercial sections query published WooCommerce data. Empty catalogs render clear preparation messages. Fictional fixtures are loaded only through the development WP-CLI commands documented with the plugin; production never loads them automatically.

## Accessibility

The header supports keyboard activation, Escape dismissal with focus restoration, outside-click dismissal, resize cleanup, and safe body-scroll locking. The menu remains server-rendered without JavaScript. Playwright and Axe cover desktop and mobile storefront paths.
