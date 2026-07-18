# Compadres Cigars E-Commerce Implementation Plan

> **For Hermes:** Execute this plan phase-by-phase with TDD, spec review, and code-quality review. Never mark external integrations complete without sandbox evidence.

**Goal:** Build a production-ready, single-installation WordPress/WooCommerce consumer store for multiple Compadres cigar brands, with one catalog/cart/checkout/admin and extensible compliance, tax, shipping, payment, reporting, and future ERP interfaces.

**Architecture:** Use Docker Compose for reproducible local development, the official WordPress image, MariaDB, WP-CLI, Mailpit, a custom `compadres` WooCommerce theme, and a custom `compadres-commerce` plugin. Store brands as a WooCommerce product taxonomy, business data as registered metadata or purpose-built versioned tables, and historical legal/financial values as immutable order/item snapshots. External services sit behind interfaces with fail-closed production behavior and explicit sandbox/configuration health states.

**Tech stack:** WordPress, WooCommerce, PHP 8.3, MariaDB, WP-CLI, Composer, PHPUnit, WordPress Coding Standards/PHPCS, PHPStan, JavaScript, CSS, Playwright, Docker Compose, WooCommerce REST API.

---

## Audit baseline

- Remote: `https://github.com/pavanganta0110/Cigar-Ecommerce.git`
- Starting state: empty repository, no commits or remote branches.
- Working branch: `feature/compadres-cigar-ecommerce`.
- Host has Docker and Docker Compose; PHP, Composer, WP-CLI, and MySQL are intentionally supplied by containers.
- No existing database, product data, theme, plugins, tests, deployment config, production environment, backup, or secrets.
- No migration or compatibility burden exists.
- External approvals/credentials are missing for payment, Avalara, AgeChecker, approved carrier service, SMTP, analytics, hosting, staging, production, and legal restriction rules.

## Implementation principles

1. One WordPress/WooCommerce installation, one catalog, one cart, one checkout, and one order ledger.
2. Cigar-only product scope; no accessories until explicitly approved.
3. Server-side revalidation immediately before order/payment.
4. Fail closed for unavailable age, restriction, shipping, tax, or payment dependencies.
5. Never store PAN, CVV, identity-document images, or government-ID values.
6. Immutable order snapshots power historical reports.
7. Administrators configure restriction rules; no unverified nationwide hardcoded legal list.
8. Every provider integration has an interface, health check, idempotency behavior, safe logging, mocks, and setup documentation.
9. Production features remain disabled until the corresponding merchant/provider approval is recorded.
10. No production deployment or transaction without explicit authorization.

---

## Phase 1 — Foundation and reproducible environments

### Task 1.1: Create repository guardrails

**Files:**
- Create: `.gitignore`
- Create: `.gitattributes`
- Create: `.editorconfig`
- Create: `.env.example`
- Create: `SECURITY.md`
- Create: `CONTRIBUTING.md`

**Steps:**
1. Add exclusions for environment files, WordPress core, uploads, caches, DB dumps, certificates, logs, vendor, and node_modules.
2. Document branch, review, secret, and production-change rules.
3. Run a tracked-file secret scan and verify only variable names exist in `.env.example`.
4. Commit as `chore: establish repository security guardrails`.

### Task 1.2: Create Docker development stack

**Files:**
- Create: `compose.yaml`
- Create: `docker/php/Dockerfile`
- Create: `docker/php/php.ini`
- Create: `docker/nginx/default.conf` if nginx is selected after smoke testing
- Create: `scripts/bootstrap.sh`
- Create: `scripts/healthcheck.sh`
- Create: `scripts/backup.sh`
- Create: `scripts/restore.sh`

**Steps:**
1. Pin WordPress/PHP and MariaDB image versions.
2. Add services for WordPress, DB, WP-CLI, Mailpit, and test runner.
3. Mount only custom themes/plugins/config; keep WordPress core out of Git.
4. Add health checks and named volumes.
5. Bootstrap WordPress and WooCommerce idempotently.
6. Verify home page, REST API, WooCommerce activation, email capture, and DB connectivity.
7. Test backup and restore against non-production sample data.
8. Commit as `build: add reproducible WordPress development stack`.

### Task 1.3: Add quality toolchain and CI

**Files:**
- Create: `composer.json`
- Create: `phpcs.xml.dist`
- Create: `phpstan.neon.dist`
- Create: `phpunit.xml.dist`
- Create: `package.json`
- Create: `playwright.config.ts`
- Create: `.github/workflows/ci.yml`

**Steps:**
1. Configure WPCS, WooCommerce-compatible stubs, PHPStan, PHPUnit, ESLint, Stylelint, and Playwright.
2. Add deterministic scripts: `lint`, `analyse`, `test:unit`, `test:e2e`, `test`, and `build`.
3. Run each check locally and in CI.
4. Commit as `ci: add static analysis and automated test pipeline`.

### Task 1.4: Security baseline

**Files:**
- Create: `config/wordpress-hardening.php`
- Create: `docs/security-checklist.md`
- Modify: `compose.yaml`

**Steps:**
1. Disable file editing, enforce secure cookies in HTTPS environments, restrict debug display, configure salts by environment, and add safe headers.
2. Define admin MFA/login-rate-limit plugin selection criteria; do not silently install abandoned plugins.
3. Add data/log redaction requirements.
4. Verify checkout/account pages are excluded from caches.
5. Commit as `security: establish WordPress hardening baseline`.

---

## Phase 2 — Theme, catalog, and multi-brand storefront

### Task 2.1: Create custom theme foundation

**Files:**
- Create: `wp-content/themes/compadres/style.css`
- Create: `wp-content/themes/compadres/functions.php`
- Create: `wp-content/themes/compadres/theme.json`
- Create: `wp-content/themes/compadres/header.php`
- Create: `wp-content/themes/compadres/footer.php`
- Create: `wp-content/themes/compadres/front-page.php`
- Create: `wp-content/themes/compadres/assets/css/*`
- Create: `wp-content/themes/compadres/assets/js/*`

**Steps:**
1. Create premium, adult-oriented design tokens and accessible components.
2. Add keyboard navigation, skip links, focus states, responsive header/cart, footer policies, and reduced-motion support.
3. Add an accessible 21+ site-entry dialog whose cookie is explicitly documented as not checkout verification.
4. Add unit/e2e accessibility tests for the dialog and navigation.
5. Commit as `feat(theme): add Compadres premium storefront foundation`.

### Task 2.2: Create plugin foundation and data contracts

**Files:**
- Create: `wp-content/plugins/compadres-commerce/compadres-commerce.php`
- Create: `wp-content/plugins/compadres-commerce/src/Plugin.php`
- Create: `wp-content/plugins/compadres-commerce/src/Contracts/*`
- Create: `wp-content/plugins/compadres-commerce/src/Infrastructure/*`
- Create: `wp-content/plugins/compadres-commerce/tests/*`

**Steps:**
1. Add namespaced autoloading, activation checks, uninstall behavior, schema versioning, capability checks, and service container.
2. Add interfaces for payment, age, tax, shipping, email, ERP exports, audit, clock, and idempotency.
3. Add redacted structured logger.
4. Test activation, version checks, capability failures, and log redaction.
5. Commit as `feat(plugin): establish Compadres commerce domain foundation`.

### Task 2.3: Implement structured brand taxonomy

**Files:**
- Create: `src/Catalog/BrandTaxonomy.php`
- Create: `src/Catalog/BrandMeta.php`
- Create: `src/Admin/BrandFields.php`
- Create: theme `taxonomy-product_brand.php`
- Create: tests for taxonomy/meta/permissions/escaping

**Steps:**
1. Register `product_brand` for WooCommerce products and REST exposure.
2. Register logo, colors, story, hero, SEO, OG, and ERP metadata with sanitization and authorization.
3. Build brand archive templates from structured data, not hardcoded brands.
4. Add brand shop filter and breadcrumbs/structured data.
5. Verify multiple brands share one cart and checkout.
6. Commit as `feat(catalog): add structured multi-brand storefront`.

### Task 2.4: Implement cigar product attributes and metadata

**Files:**
- Create: `src/Catalog/ProductFields.php`
- Create: `src/Admin/ProductFields.php`
- Create: `src/Rest/ProductSchema.php`
- Create: `docs/product-import-template.csv`
- Create: `docs/product-import-guide.md`

**Steps:**
1. Register validated metadata for UPC, origin, wrapper, binder, filler, strength, flavor, vitola, dimensions, pack/box size, tax codes, excise code, ERP ID, and wholesale ID.
2. Support WooCommerce variations and per-variation inventory.
3. Render accessible specifications and schema data on product pages.
4. Add CSV import/export mapping and validation tests.
5. Commit as `feat(catalog): add cigar product data model`.

### Task 2.5: Complete storefront templates

**Files:**
- Create/modify theme WooCommerce templates only where hooks cannot satisfy requirements.
- Add shop, product, cart, account, search, filter, sorting, new-release, best-seller, sampler, about, contact, and policy templates.

**Steps:**
1. Prefer WooCommerce hooks over copied templates.
2. Add responsive image handling, pagination, recently viewed, related products, and noindex rules.
3. Exclude accessories until approved products exist.
4. Add storefront and mobile Playwright coverage.
5. Commit as `feat(storefront): complete responsive shopping experience`.

---

## Phase 3 — Compliance and checkout orchestration

### Task 3.1: Implement restriction data model and admin UI

**Files:**
- Create: `src/Restrictions/RuleRepository.php`
- Create: `src/Restrictions/RuleEvaluator.php`
- Create: `src/Restrictions/AdminPage.php`
- Create: `src/Restrictions/BlockedAttemptRepository.php`
- Create: `db/migrations/*restriction*`
- Create: tests for every dimension and date boundary

**Data:** Versioned custom tables for rules, rule targets, and blocked attempts, including jurisdiction, source URL/date, effective/expiry dates, review date, and notes.

**Steps:**
1. Implement state/county/city/ZIP/product/category/brand/shipping/customer/effective-date matching.
2. Apply the same evaluator to checkout, Store API, REST-created orders, reorders, admin orders, and payment links.
3. Re-evaluate after address/cart/shipping changes and immediately before payment.
4. Log rule IDs without excess PII.
5. Add admin CRUD, import/export, capability checks, nonces, and audit events.
6. Commit as `feat(compliance): add configurable location restriction engine`.

### Task 3.2: Implement AgeChecker provider boundary

**Files:**
- Create: `src/AgeVerification/AgeVerificationService.php`
- Create: `src/AgeVerification/AgeCheckerClient.php`
- Create: `src/AgeVerification/ReviewWorkflow.php`
- Create: `src/AgeVerification/AdminPage.php`
- Create: provider contract/mocked HTTP tests

**Steps:**
1. Confirm current AgeChecker API documentation and sandbox contract before coding endpoint details.
2. Collect only required fields and never persist IDs or document images.
3. Persist provider, transaction ID, status, timestamp, review status/reviewer/time.
4. Fail closed on errors and block all checkout paths until pass or authorized review.
5. Add rate limits, safe errors, redacted logs, and manual-review capability.
6. Commit as `feat(compliance): integrate sandbox-ready age verification`.

### Task 3.3: Implement authoritative checkout pipeline

**Files:**
- Create: `src/Checkout/CheckoutOrchestrator.php`
- Create: `src/Checkout/CartFingerprint.php`
- Create: `src/Checkout/IdempotencyStore.php`
- Create: `src/Checkout/ValidationResult.php`
- Create: checkout hooks/blocks integration
- Create: pipeline and tamper tests

**Required order:** inventory/pricing → address validation → restriction → age → approved shipping → tax → final review → payment authorization/capture → order completion.

**Steps:**
1. Ignore client totals and regenerate from the server cart.
2. Fingerprint cart/address/shipping; invalidate downstream results on changes.
3. Lock checkout with idempotency key and order-level unique constraint.
4. Preserve cart on recoverable failures.
5. Verify payment cannot execute if any prerequisite is missing/stale.
6. Commit as `feat(checkout): add fail-closed checkout orchestration`.

### Task 3.4: Implement immutable order snapshots

**Files:**
- Create: `src/Orders/OrderSnapshot.php`
- Create: `src/Orders/OrderSnapshotRepository.php`
- Create: `src/Orders/OrderSnapshotSchema.php`
- Create: snapshot immutability tests

**Steps:**
1. Snapshot customer type, addresses, product/SKU/brand/variation, amounts, tax components/codes, payment, age, restrictions, shipping/adult signature, refunds, source, and timestamps.
2. Store searchable normalized fields plus a versioned canonical JSON payload.
3. Ensure product/brand/tax edits do not alter historical output.
4. Commit as `feat(orders): preserve immutable compliance and financial snapshots`.

---

## Phase 4 — External provider integrations

### Task 4.1: Avalara sales/excise tax adapter

**Files:**
- Create: `src/Tax/AvalaraClient.php`
- Create: `src/Tax/TaxService.php`
- Create: `src/Tax/TaxSnapshot.php`
- Create: `src/Tax/RefundAdjustmentService.php`
- Create: contract, sandbox, outage, commit/void/refund tests

**Steps:**
1. Map WooCommerce lines, discounts, shipping, sales-tax codes, and excise codes.
2. Support calculate, commit, void, refund, and partial adjustment.
3. Store jurisdiction components and provider IDs immutably.
4. Fail closed; never silently charge zero tax.
5. Keep production disabled until registrations/configuration are approved.
6. Commit as `feat(tax): add Avalara tax transaction lifecycle`.

### Task 4.2: Approved payment gateway adapter

**Files:**
- Create: `src/Payments/ApprovedGateway.php`
- Create: `src/Payments/AuthorizeNetGateway.php` only after approved MID/gateway confirmation
- Create: `src/Payments/WebhookController.php`
- Create: authorization/capture/void/refund/idempotency/webhook tests

**Steps:**
1. Record merchant approval status/config separately from credentials.
2. Use provider-hosted tokenization; never receive/store PAN or CVV.
3. Add auth, capture, void, full/partial refund, status sync, signature validation, replay prevention, and idempotency.
4. Keep gateway unavailable in production until tobacco approval is documented.
5. Commit as `feat(payments): add approved tokenized payment lifecycle`.

### Task 4.3: Approved carrier/adult-signature adapter

**Files:**
- Create: `src/Shipping/ApprovedCarrier.php`
- Create: `src/Shipping/UpsClient.php` after contractual approval
- Create: `src/Shipping/ShippingService.php`
- Create: rate/address/label/tracking/return tests

**Steps:**
1. Offer only approved cigar-capable services.
2. Require adult signature when rule/config requires it.
3. Store carrier/service/rate/signature/transaction/tracking snapshots.
4. Add admin override with capability, reason, and audit event.
5. Commit as `feat(shipping): add approved adult-signature shipping workflow`.

### Task 4.4: Transactional email and analytics

**Files:**
- Create: `src/Notifications/*`
- Create: theme email templates
- Create: `src/Analytics/EventPublisher.php`
- Create: tests for content redaction and event payloads

**Steps:**
1. Add required customer/admin transactional emails.
2. Send through verified SMTP/provider configuration.
3. Add GA4 events without PII, verification details, or payment data.
4. Add consent checks where required.
5. Commit as `feat(notifications): add privacy-safe commerce messaging and analytics`.

---

## Phase 5 — Administration, reporting, refunds, and accounts

### Task 5.1: Roles and audit log

**Files:**
- Create: `src/Admin/Roles.php`
- Create: `src/Audit/AuditRepository.php`
- Create: `src/Audit/AdminPage.php`
- Create: permission-matrix tests

**Steps:**
1. Add least-privilege roles and capabilities from the specification.
2. Audit sensitive reads/writes, overrides, refunds, review decisions, settings, and exports.
3. Add retention/export behavior and safe metadata redaction.
4. Commit as `feat(admin): add least-privilege roles and audit history`.

### Task 5.2: Connected operations dashboard

**Files:**
- Create: `src/Admin/Dashboard.php`
- Create: `src/Admin/IntegrationStatus.php`
- Create: dashboard query tests

**Steps:**
1. Compute metrics from real WooCommerce orders/snapshots—never placeholders.
2. Add integration health without exposing secrets.
3. Add pending verification, blocked orders, payment failures, tax, shipping, inventory, and refund views.
4. Commit as `feat(admin): add operational commerce dashboard`.

### Task 5.3: Snapshot-based reports and CSV exports

**Files:**
- Create: `src/Reports/ReportQuery.php`
- Create: `src/Reports/ReportController.php`
- Create: `src/Reports/CsvExporter.php`
- Create: report fixtures/tests

**Steps:**
1. Implement all required filters and reports against immutable snapshots/refund adjustments.
2. Add export metadata, time zone, currency, row counts, and totals.
3. Use background jobs for large exports.
4. Verify edits to products/brands/rules do not change historical reports.
5. Commit as `feat(reports): add jurisdiction and commerce reporting`.

### Task 5.4: Refund orchestration

**Files:**
- Create: `src/Refunds/RefundOrchestrator.php`
- Create: `src/Refunds/RefundAttemptRepository.php`
- Create: refund pipeline tests

**Steps:**
1. Validate captured balance and idempotency.
2. Coordinate payment refund, Avalara adjustment, excise adjustment, optional restock, snapshot update, email, and audit.
3. Preserve prior state on external failure and support controlled retry.
4. Commit as `feat(refunds): add idempotent payment and tax refund workflow`.

### Task 5.5: Customer accounts and privacy

**Files:**
- Create: account endpoint customizations and privacy exporter/eraser hooks
- Create: receipt/invoice template
- Create: account/reorder/password-reset/privacy tests

**Steps:**
1. Add profile, addresses, orders, tracking, receipt, reorder, preferences, and deletion request.
2. Ensure reorders rerun all current commerce/compliance checks.
3. Integrate WordPress privacy exporter/eraser while retaining legally required records.
4. Commit as `feat(accounts): complete customer self-service and privacy controls`.

---

## Phase 6 — QA, launch preparation, and documentation

### Task 6.1: Formal automated and manual QA

**Files:**
- Create: `tests/e2e/*`
- Create: `tests/integration/*`
- Create: `docs/test-plan.md`
- Create: `docs/uat-report.md`

**Steps:**
1. Encode all specified storefront, checkout, admin, integration, and UAT scenarios.
2. Run sandbox/test modes only.
3. Capture exact passed/failed/skipped counts and explain credential-blocked tests.
4. Commit as `test: add full commerce and compliance QA suite`.

### Task 6.2: Security, accessibility, and performance review

**Files:**
- Create: `docs/security-review.md`
- Create: `docs/accessibility-review.md`
- Create: `docs/performance-review.md`

**Steps:**
1. Run PHPCS, PHPStan, dependency audit, secret scan, permissions tests, nonce/escape tests, and webhook replay tests.
2. Run axe/keyboard/screen-size checks and document exceptions.
3. Run Lighthouse/WebPageTest equivalent against staging; tune images/assets/cache exclusions/query plans.
4. Commit as `docs: record launch quality reviews`.

### Task 6.3: Operations and deployment documentation

**Files:**
- Create/complete: `README.md`
- Create all required guides under `docs/` for local/staging/production, providers, brands, products, restrictions, reports, refunds, roles, backups, troubleshooting, security, launch, Odoo, and B2B.
- Create: `docs/production-launch-checklist.md`
- Create: `docs/rollback-plan.md`

**Steps:**
1. Clearly label implemented, sandbox-tested, awaiting credentials, and awaiting approval items.
2. Add environment-variable names only.
3. Add backup/restore and rollback proof.
4. Commit as `docs: add operations and production launch handbook`.

### Task 6.4: Final verification and handoff

**Steps:**
1. Run full lint, static analysis, unit, integration, e2e, accessibility, build, secret scan, and Docker smoke suite.
2. Verify Git diff, no secrets, exact remote/branch, and no changes outside this repository.
3. Produce final feature/changed-file/commit/test/security/performance/accessibility/deployment/credential/approval/limitation report.
4. Do not merge or deploy production automatically.

---

## Expected database changes

Use WooCommerce HPOS for orders and add versioned plugin tables only where WordPress metadata is unsuitable:

- `wp_compadres_restriction_rules`
- `wp_compadres_restriction_targets`
- `wp_compadres_blocked_attempts`
- `wp_compadres_age_verifications`
- `wp_compadres_order_snapshots`
- `wp_compadres_refund_attempts`
- `wp_compadres_idempotency_keys`
- `wp_compadres_webhook_events`
- `wp_compadres_audit_log`
- `wp_compadres_export_jobs`

Every table requires schema versioning, indexes, retention rules, capability-protected access, multisite-aware prefixes, and uninstall behavior that preserves commercial records unless an explicit destructive option is enabled.

## Plugins expected

- **Required:** WooCommerce.
- **Custom:** Compadres Commerce plugin and Compadres theme.
- **Likely external/commercial after approval:** Authorize.Net gateway, Avalara, UPS, SMTP provider, SEO, consent manager, MFA/security, and object cache.
- Final vendor/plugin selection requires license, support, data-processing, tobacco-category, and current maintenance review. Avoid duplicating reliable commercial integrations with bespoke code unless required by the approved account contract.

## External approvals and unresolved inputs

- Approved Compadres brand list, logos, colors, stories, photography, products, SKUs, prices, inventory, tax/excise codes, and ERP IDs.
- Hosting/staging/prod, domain/DNS, WordPress admin, email DNS, GA4, and Search Console.
- PaymentCloud/merchant-account approval and final gateway choice (NMI vs Authorize.Net) explicitly covering online cigars.
- Avalara account, registrations, company code, tax/excise configuration, and sandbox credentials.
- AgeChecker account, approved workflow, retention rules, and sandbox credentials.
- UPS/other carrier written contractual approval, account, approved services, and adult-signature behavior.
- Legal-approved policies and jurisdiction restriction rules with official sources/review dates.
- Data-retention schedule and privacy counsel review.

## Verification commands

Exact commands will be finalized by the foundation task, but the release gate will expose deterministic wrappers similar to:

```bash
docker compose config
docker compose up -d --build
./scripts/bootstrap.sh
composer validate --strict
composer lint
composer analyse
composer test
npm ci
npm run lint
npm run test:e2e
./scripts/healthcheck.sh
./scripts/secret-scan.sh
```

A missing credential must produce an explicit skipped/blocked integration test and an unavailable integration status—not a fabricated pass.
