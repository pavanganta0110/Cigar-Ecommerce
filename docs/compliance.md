# Compliance controls

## 21+ site-entry gate

The site-entry gate asks visitors to confirm that they are at least 21 years old before browsing. It is an entry control and user notice only. It does **not** identify a visitor, inspect an identity document, query an age-verification provider, or replace the independent server-side verification required during checkout. Production wording requires legal and business approval before launch.

### Administration and defaults

Authorized users with `compadres_manage_restrictions` can open **WooCommerce → Compliance** and configure:

| Setting | Default | Behavior |
| --- | --- | --- |
| Enabled | Yes | Disabling prevents the modal and its assets from rendering. |
| Title | `Are you 21 or older?` | Accessible dialog name. |
| Explanation | States that visitors must be 21 and that the gate is not identity verification | Accessible dialog description. |
| Confirmation label | `I am 21 or older` | Sends a nonce-protected server request. |
| Exit label | `Exit website` | Labels the exit link. |
| Exit URL | `https://www.google.com/` | Destination for visitors who do not confirm. |
| Cookie lifetime | 720 hours | Clamped to 1–8,760 hours. |
| SameSite | `Lax` | Supports `Lax`, `Strict`, or `None`. |

To disable the gate, clear **Enable the 21+ site-entry confirmation** and save. Disabling the gate is an operational configuration choice; it does not disable checkout age-verification requirements.

### Confirmation and cookie security

Confirmation is posted to WordPress `admin-ajax.php` with a WordPress nonce. The browser does not create or choose a verification status. The server creates `compadres_age_confirmed` containing only an expiration timestamp and an HMAC signature generated with the WordPress authentication salt.

On later requests, the server checks the signature and expiration before treating the visitor as confirmed. Invalid, altered, malformed, or expired values are rejected and the gate is shown again.

Cookie attributes are:

- `HttpOnly`, so ordinary browser scripts cannot read the token.
- `Path=/`, so confirmation applies across the storefront.
- `Secure` when WordPress detects HTTPS.
- The configured `SameSite` value. `None` is downgraded to `Lax` when HTTPS is unavailable because modern browsers require `Secure` with `SameSite=None`.
- An explicit expiration based on the configured lifetime.

Production must use HTTPS. The cookie contains no name, birth date, identity-document data, provider response, or checkout verification result. Retention is limited to the configured lifetime, and expiration is enforced both by the browser and server. Privacy documentation should disclose this operational cookie and its purpose.

### Accessible interaction

On an unconfirmed first visit:

1. A modal dialog with `role="dialog"`, `aria-modal="true"`, a labeled heading, and a described explanation is rendered.
2. Initial focus moves to the confirmation button.
3. Tab and Shift+Tab remain within the confirmation and exit controls.
4. Escape does not bypass or dismiss the required gate. It returns focus to confirmation and announces that the visitor must confirm or exit.
5. Confirmation removes the modal after the server accepts the request and transfers focus to the main page content.
6. The exit control follows the configured exit URL and does not set a confirmation cookie.

The mobile layout stacks actions at narrow widths. Reduced-motion preferences disable gate animation, transition, and smooth-scrolling behavior. A live status region announces Escape instructions, save progress, and recoverable request errors.

### Testing

`tests/e2e/age-gate.spec.ts` clears the confirmation cookie before each test. It covers first visit, dialog semantics, initial focus, keyboard containment, Escape behavior, confirmation, focus transfer, signed-cookie persistence, repeat visits, exit configuration, Axe checks, desktop, and mobile layouts.

The shared Playwright fixture in `tests/e2e/compadres-test.ts` confirms through the real server endpoint before existing storefront scenarios. It does not inject a fake browser status. Tests that need a first-visit state use the base Playwright fixture and clear cookies explicitly.

`AgeGateTest.php` covers settings sanitization, disabling, token alteration, and token expiration. `PluginBootTest.php` proves repeated plugin boot calls do not register hooks or services twice.

When testing manually, remove the `compadres_age_confirmed` cookie or use a private browsing context to see the first-visit flow again. Administrators can test the disabled state by disabling the setting and confirming that neither modal markup nor age-gate assets render.

## Checkout age verification

Checkout age verification is independent from the 21+ site-entry gate. The signed entry cookie records only that a visitor acknowledged the entry notice; checkout never reads it as identity or age evidence. Forged form fields claiming `passed` are likewise ignored. Only the authoritative normalized result held in the WooCommerce session may authorize checkout.

### Provider boundary and current AgeChecker limitations

Checkout orchestration depends on the replaceable `AgeVerificationProvider` interface. Results are normalized to `passed`, `failed`, `pending`, `manual_review`, `expired`, or `unavailable`. Only an unexpired `passed` result permits checkout. Every other state fails closed before order creation and payment authorization. A valid unexpired pass is reused to avoid unnecessary duplicate provider transactions.

`AgeCheckerProvider` currently supplies the normalized adapter, hosted-continuation URL construction, and authoritative status-refresh boundary. Network transport is injected through `compadres_agechecker_transport`; this repository intentionally contains no invented AgeChecker endpoint, request schema, webhook signature, or production credential handling. An account, credential, or successful connection is not evidence of production approval.

When additional evidence is required, the customer follows the configured HTTPS hosted-provider link. The link contains only the encoded provider reference and encoded return URL. It opens with `noopener noreferrer`. Returning to Compadres does not assert success: the server refreshes the provider reference and stores the newly normalized authoritative status. Refresh is customer initiated and does not poll automatically or create another verification attempt.

Compadres does not receive, proxy, inspect, cache, log, or retain identity documents. There is no custom ID upload, selfie capture, government-ID field, OCR, facial recognition, document-review dashboard, or verification-session database table.

### Conditional, transient date of birth

DOB is rendered only when the selected provider configuration declares it required. The field is labeled, exposes its required state to assistive technology, and uses `autocomplete="bday"`. Server validation accepts only a real `YYYY-MM-DD` date strictly before the current date. Compadres performs no custom local age-matching decision; the validated value is passed transiently to the provider abstraction.

DOB is removed from normalized results and is never written to the WooCommerce session, customer or user metadata, protected order metadata, audit details, application logs, browser local storage, analytics, or WordPress administration. Providers that do not require DOB render no DOB field and create no empty DOB metadata.

### Storage

The WooCommerce session stores only:

- Provider name.
- Provider reference.
- Canonical status.
- Non-sensitive reason code.
- Verification timestamp.
- Expiration timestamp.

The protected order snapshot stores the same fields and, when applicable, only the manual decision, reviewer user ID, and manual-decision timestamp. It does not copy the checkout identity payload, billing address, DOB, entry cookie, authorization data, provider request, provider response, government-ID value, or document data.

The launch checkout uses WooCommerce's server-rendered `[woocommerce_checkout]` surface so validation runs through the documented classic checkout hooks before order creation and gateway processing. `scripts/bootstrap.sh` configures that checkout surface. Checkout Blocks are not represented as supported by this increment.

### Manual decisions

Existing orders carrying a trusted server-side `manual_review` snapshot expose reference-only approval and rejection controls to users with `compadres_review_age_verification`. Normal checkout does not create such an order because every non-pass state is blocked before order creation; this control is reserved for an approved operational/provider integration that creates the snapshot without browser assertions. The controls do not display or review identity documents. Requests require a decision-specific nonce, order ID, reviewer identity, and current `manual_review` state. The optional non-sensitive reason is sanitized. Approval writes a 24-hour `passed` decision when no later provider expiry exists; rejection writes `failed`. Both write the reviewer ID and decision timestamp. An atomic per-order decision lock prevents concurrent decisions, a second decision is rejected with HTTP 409, unauthorized requests return HTTP 403, and redirect notices use a separate nonce.

### Audit events and privacy

The age-verification workflow emits these essential events through the existing recursively redacting audit service:

- `age_verification.started`
- `age_verification.status_updated`
- `age_verification.passed`
- `age_verification.failed`
- `age_verification.manual_review_required`
- `age_verification.manual_approved`
- `age_verification.manual_rejected`
- `age_verification.expired`
- `age_verification.provider_unavailable`
- `age_verification.settings_updated`

Status events include only normalized provider, reference, status, non-sensitive reason, and timestamps. Audit values exclude DOB, customer addresses, full customer identity, cookies, authorization values, credentials, and raw provider request or response payloads.

### Configuration and production boundary

Authorized compliance administrators configure whether verification is enabled, provider selection, conditional DOB capability, hosted URL template, and the separate production-approval flag. The administration status is `Not configured` until a provider is selected; a selected AgeChecker adapter remains `configured_not_approved` and does not claim connectivity or production approval. Development mock status is distinct from both states.

The deterministic mock provider is permitted only in `local` and `development` environments. It is rejected in staging and production regardless of options. Production AgeChecker use requires an approved transport implementation, provider credentials supplied through the deployment secret manager, an HTTPS hosted template containing `{reference}` and `{return_url}`, the correct DOB capability, and explicit production approval. The hosted template host must also appear in the deployment-controlled `COMPADRES_AGECHECKER_ALLOWED_HOSTS` comma-separated allowlist; only `.example.test` hosts are accepted in local/development. Required verification remains fail closed when configuration, transport, or provider service is unavailable.

Current external blockers include AgeChecker commercial approval, approved API and hosted-return documentation, sandbox and production credentials, legal approval of customer wording and operating procedures, and final privacy and retention review. This implementation is not a statement of legal approval or production readiness.

### Local and browser testing

Bootstrap creates the supported classic checkout surface:

```bash
./scripts/bootstrap.sh
docker compose run --rm wpcli option get compadres_age_verification --format=json
```

Focused quality and browser checks:

```bash
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli vendor/bin/phpunit --testsuite unit
node_modules/.bin/playwright test tests/e2e/age-verification.spec.ts --project=desktop-chromium
node_modules/.bin/playwright test tests/e2e/age-verification.spec.ts --project=mobile-chromium
```

`tests/e2e/age-verification.spec.ts` temporarily selects the classic checkout, enables Cash on Delivery for a fictional virtual development product, and uses development-only mock statuses. It restores the checkout content, product state, provider settings, entry-gate settings, and payment settings afterward. Covered states include pass, failure, pending hosted continuation and refresh, manual review, expiration, provider unavailability, unconfigured provider, forged browser status, entry-cookie separation, conditional DOB, protected metadata, administration security, gateway handoff, desktop, mobile, and focused Axe checks. It never contacts a real AgeChecker account.

## Geographic checkout restrictions

Geographic restrictions are server-side deny rules for the classic WooCommerce checkout. The launch implementation supports U.S. country, state, city, exact postal code, an explicitly configured postal prefix, product, product category, and Compadres brand targets. It does not provide maps, county lookup, geographic APIs, legal-research automation, law ingestion, bulk imports, simulation dashboards, or additional override systems.

### Matching behavior

- Values within one target type are alternatives. For example, a state target containing `AA` and `BB` matches either value.
- Product, category, and brand target groups are also alternatives. A matching product **or** category **or** brand satisfies the cart side of a rule.
- Configured destination dimensions and the cart side are combined with AND. A rule containing state and product targets blocks only when both the state and one configured cart target match.
- Exact postal codes compare the complete normalized postal value. They never imply prefix behavior.
- Postal prefixes match only when entered in the separate explicit postal-prefix field. Prefixes are bounded to one through nine uppercase letters or digits.
- Higher numeric priority rules are evaluated first. The highest-priority matching rule supplies the customer-facing message; all matching rule IDs remain available for bounded audit context.
- Effective and expiration values are interpreted in the WordPress site timezone when saved and stored as UTC. A rule is active at its effective instant and inactive at its expiration instant.
- Launch enforcement is U.S.-only. A non-U.S. checkout fails closed rather than being treated as supported.

Production rules require current legal review by qualified counsel and operational approval. Rule names, notes, source references, and fixture content in this repository are not legal guidance and do not establish where cigar sales are lawful.

### Administration and audit

Users with `compadres_manage_compliance` manage rules from the top-level **Restrictions** administration page. Store administrators have this capability; a separately assigned compliance role may receive it without receiving unrelated administrative capabilities. The page lists rules and supports add, edit, activate, deactivate, and archive. Archive is used instead of destructive deletion so audit or future order references remain interpretable.

Every state-changing request requires the capability and a rule-specific WordPress nonce. Input is normalized and bounded, referenced products and taxonomy terms must exist, source links must be public HTTPS URLs without credentials, query parameters, or fragments, and all rendered values are escaped. Revision-qualified writes reject stale concurrent updates.

Only these administration events are audited:

- `restriction.rule_created`
- `restriction.rule_updated`
- `restriction.rule_activated`
- `restriction.rule_deactivated`
- `restriction.rule_archived`

Audit details contain only bounded rule-administration fields. Customer addresses, cart contents, internal notes, customer messages, and source URLs are not included. Checkout block events contain only the validation phase and bounded matching rule IDs.

### Checkout ordering and failure behavior

Restrictions are reevaluated after cart or address updates, during checkout validation, on `woocommerce_checkout_create_order` before the order is persisted, and during pay-for-order before the selected gateway's payment action. Browser fields claiming an allowed result are ignored. A blocked checkout cannot create an order or invoke payment processing.

Repository, schema, hydration, or evaluation failures fail closed with a generic retry message. Missing or malformed rule data is never treated as permission to ship. Operations should alert on associated application and audit errors.

### Fictional development fixtures and testing

The fixture rule is fictional, development-only, and not legal guidance. It is keyed by immutable fixture ownership so repeated load is idempotent and removal deletes only its own rule and targets:

```bash
docker compose run --rm wpcli compadres restriction-fixtures load
docker compose run --rm wpcli compadres restriction-fixtures remove
```

Fixture loading is disabled in production. Staging requires the explicit `COMPADRES_ENABLE_FIXTURES=1` opt-in and still uses fictional data only. Focused browser coverage is in `tests/e2e/restrictions.spec.ts` for desktop and mobile; it covers allowed checkout, fictional state and exact-postal restrictions, destination-plus-product matching, server reevaluation after address/cart changes, forged browser values, payment ordering, administration authorization/nonces, fixture ownership, and focused accessibility.

## Administrative audit logging

The audit log records security-relevant administrative and compliance decisions. It supports investigation and operational accountability, but does not by itself prove regulatory compliance.

### Storage and migrations

Records are stored in the append-only `{$wpdb->prefix}compadres_audit_log` custom table (normally `wp_compadres_audit_log`). Schema version `1` is recorded in `compadres_audit_schema_version`. Activation creates or upgrades the table with WordPress `dbDelta`; normal plugin boot runs the migration only when the stored version differs. Re-running the current migration is idempotent and preserves existing rows.

The table uses the WordPress database collation and has indexes for event type, user, entity type/ID, result, and creation time. Future migrations must increment `AuditSchema::VERSION`, preserve records, remain safe to retry, and include automated schema and live migration verification.

Deactivation and uninstall intentionally do not remove audit records. Operational history may be subject to retention, litigation-hold, tax, privacy, or security requirements and may only be destroyed through a separately approved procedure. Backups must include the custom table and restore it consistently with related orders and settings.

Local inspection commands:

```bash
docker compose run --rm wpcli option get compadres_audit_schema_version
docker compose run --rm wpcli db query "SHOW CREATE TABLE wp_compadres_audit_log"
docker compose run --rm wpcli db query "SHOW INDEX FROM wp_compadres_audit_log"
```

### Record contents and redaction

Records contain event type, actor user ID, entity type/ID, changed previous and new values, result, redacted failure reason, correlation ID, application environment, non-sensitive request context, and creation timestamp. IP addresses are not retained because they are not necessary for the implemented events.

Redaction is recursive. Passwords, credentials, authorization headers, cookies, API keys, tokens, payment-card data, CVV, government identifiers, identity-document images, raw provider payloads, and provider responses are replaced before storage. Credential-setting events must record only that a credential changed, never its old or new value. Full request payloads are not audit context.

Initial events include `compliance.age_gate.settings_updated`. Provider configuration, age verification, restriction rules, manual decisions, overrides, refunds, tax, payment, shipping, and role changes must use the same service as those modules are introduced.

If storage fails, the caller receives a failure result without an exception interrupting checkout or settings persistence. A redacted operational error is sent to the PHP error log for monitoring. Monitoring must alert on these errors because missing audit records must not fail silently.

### Administration, retention, and privacy

The top-level WordPress administration **Compadres Audit Log** page requires `compadres_view_audit_logs`. Store administrators and operations managers can view it; unauthorized roles cannot. Compliance settings are nested beneath that page and require `compadres_manage_compliance`. Filters support date range, event type, user, entity type/ID, and result, with server-side pagination. JSON details and entity identifiers are escaped before display.

`compadres_export_audit_logs` is reserved for store administrators. CSV export is not currently implemented. When added, it must require that capability and a nonce, preserve redaction, stream bounded batches, and audit the export. The page exposes no public REST route and has no state-changing action requiring a nonce today.

Audit records may contain customer or staff operational identifiers even after redaction. Access must be reviewed periodically, retention periods require legal and privacy approval, exports require equivalent controls, and production backups must be encrypted and access controlled.
