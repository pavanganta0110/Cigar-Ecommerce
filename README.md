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
- WordPress admin: <http://localhost:8080/wp-admin/>
- Mailpit: <http://localhost:8025>

The bootstrap script installs WordPress, installs WooCommerce 10.9.4, activates the Compadres theme and Compadres Commerce plugin, creates WooCommerce pages, and configures local account/checkout defaults. It refuses to run when `APP_ENV=production`.

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
