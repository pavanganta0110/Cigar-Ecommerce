# Security Policy

Report suspected vulnerabilities privately to the project owner. Do not open a public issue containing credentials, customer data, payment details, identity-verification data, or an exploit.

## Baseline

- Production requires HTTPS, unique secrets, least-privilege accounts, tested backups, and staging verification.
- Full card numbers, CVV values, government-ID values, and identity-document images must never be stored by this application.
- Provider credentials belong in protected environment configuration, never Git or WordPress-visible logs.
- Production integrations remain disabled until the applicable tobacco merchant, tax, age, and carrier approvals are documented.
