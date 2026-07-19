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
