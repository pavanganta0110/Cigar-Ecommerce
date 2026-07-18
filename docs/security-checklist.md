# Security Checklist

- [ ] HTTPS enforced at the production edge and WordPress URLs use HTTPS.
- [ ] Unique WordPress salts and database/provider credentials are injected outside Git.
- [ ] File editing is disabled; production debug display is off.
- [ ] Administrative MFA and rate limiting use a currently maintained, reviewed implementation.
- [ ] Least-privilege Compadres roles are assigned.
- [ ] Checkout/account/admin responses bypass shared caches.
- [ ] Provider webhooks verify signatures, timestamp windows, and event idempotency.
- [ ] Logs redact credentials, tokens, PAN/CVV, IDs, identity documents, and unnecessary PII.
- [ ] Backups are encrypted, access-controlled, monitored, and restore-tested.
- [ ] Dependency, secret, PHPCS, PHPStan, PHPUnit, and browser checks pass.
- [ ] Payment, tax, age, and carrier production approvals are recorded separately from credentials.
