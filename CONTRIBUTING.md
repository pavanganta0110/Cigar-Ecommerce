# Contributing

1. Work from a feature branch; never commit directly to production branches.
2. Write a failing test before production behavior changes.
3. Run PHP lint, static analysis, unit tests, JavaScript/CSS lint, and relevant browser tests.
4. Never commit secrets, customer data, database dumps, uploaded media, provider payloads, or private certificates.
5. Use WordPress nonces, capability checks, sanitization, escaping, prepared queries, and safe logging.
6. Keep external providers behind interfaces and fail closed for checkout-critical compliance services.
7. Do not force-push or merge without explicit authorization.
