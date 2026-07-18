# Deployment Environments

## Staging

Use an isolated database, uploads bucket, domain, secrets, and sandbox provider accounts. Set `APP_ENV=staging`, disable indexing, block production card/tax/carrier transactions, verify webhooks, run the complete test plan, and take a pre-release backup.

## Production

Provision managed WordPress/PHP 8.3+, MariaDB/MySQL, HTTPS, object cache, CDN with checkout exclusions, encrypted backups, monitoring, SMTP, and provider secrets through the hosting secret store. Do not copy local `.env` values. Deploy custom theme/plugin artifacts only after staging acceptance.

## Rollback

Preserve the previous theme/plugin artifact and a pre-release database/files backup. Put checkout in maintenance mode, restore code, run plugin schema compatibility checks, restore data only when the migration is not backward compatible, purge safe caches, and execute the smoke test. Never restore over production without explicit authorization and a second verified backup.
