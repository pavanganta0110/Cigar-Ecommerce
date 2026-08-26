# Compadres Cigars E-Commerce

A WordPress and WooCommerce platform for a unified, multi-brand premium cigar storefront. This repository contains custom application code and reproducible local infrastructure; WordPress core, WooCommerce runtime files, uploads, and secrets are not committed.

> **Status:** Active development. Mock integrations are development-only. No payment, tax, age-verification, or carrier integration is production-approved merely because configuration is present.

## Requirements

- Docker Desktop / Docker Engine with Compose
- Git

## Local startup

```bash
git clone https://github.com/pavanganta0110/Cigar-Ecommerce.git
cd Cigar-Ecommerce
git switch feature/compadres-cigar-ecommerce
cp .env.example .env
# Replace every local password in .env.
docker compose up -d --build
./scripts/bootstrap.sh
./scripts/healthcheck.sh
```

Open:

- Store: <http://localhost:8080>
- Compadres Cigars Admin Portal: <http://localhost:8080/wp-admin/>
- Sales & Tax Dashboard: <http://localhost:8080/wp-admin/admin.php?page=compadres-sales-tax>
- Mailpit: <http://localhost:8025>

The bootstrap script installs WordPress, installs WooCommerce 10.9.4, activates the Compadres theme and Compadres Commerce plugin, creates WooCommerce pages, and configures local account/checkout defaults. It also loads the idempotent fictional catalog fixtures in local/development environments. It refuses to run when `APP_ENV=production`.

## Staff portal and reporting

The staff experience is branded as the **Compadres Cigars Admin Portal**. The login screen, browser title, toolbar, and footer do not expose WordPress branding. Reporting access is capability-based through `compadres_view_tax_reports`; hiding navigation is not the security boundary.

The private **Sales & Tax Dashboard** reports finalized processing/completed orders and recorded refunds without displaying customer names, emails, addresses, payment data, or age-verification payloads. It includes date/state/product filters, state tax collected, product/SKU quantities and revenue, refunds, current stock, and a nonce-protected formula-safe CSV export. Reported tax is tax collected under the configured rules and is not represented as a filed or legally reconciled liability.

## Manual sales-tax rules

Checkout uses the business-approved `Avg Combined Reference %` column from `Compadres_Cigars_50_State_Tobacco_Tax_Matrix_2026.xlsx - 50-State Tax Matrix.pdf`, effective for application use beginning **2026-08-19**. The rule set contains one explicit rate for each of the 50 states, including explicit zero rates. The source attachment remains outside source control.

These values are statewide average references, not exact city/county destination rates. This limitation is shown in the staff dashboard. Shipping is not taxed by this rule set because the source does not provide shipping-taxability instructions. Tobacco/cigar excise taxes are separate and are not calculated from these sales-tax rates. Each order stores the applied state, rate, amount, calculation basis, effective date, source column/document/hash, rule version, and average-reference designation so later rate changes do not rewrite historical orders.

## Common commands

```bash
docker compose up -d
docker compose down
docker compose run --rm wpcli plugin list
docker compose run --rm wpcli wc status --user=compadres_admin
./scripts/backup.sh
./scripts/restore.sh backups/<file>.sql.gz
./scripts/healthcheck.sh
./scripts/secret-scan.sh
```

## Quality checks

```bash
docker run --rm -v "$PWD:/app" -w /app composer:2.8 install
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli vendor/bin/phpcs
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli vendor/bin/phpstan analyse --memory-limit=1G
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli vendor/bin/phpunit
npm ci
npx playwright install chromium
npm run lint:js
npm run lint:css
npm run test:e2e
```

## Environments

- **Local:** Docker Compose, Mailpit, explicit mock providers.
- **Staging:** Isolated database/domain, sandbox provider credentials, no production transactions.
- **Production:** Separate infrastructure and secrets; approved tobacco merchant account, tax setup, age verification, carrier contract, SMTP, backups, and legal rules required before launch.

See `docs/` and the tracked implementation plan under `.hermes/plans/` for architecture, provider approvals, deployment, rollback, and operational guidance.
