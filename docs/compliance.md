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
