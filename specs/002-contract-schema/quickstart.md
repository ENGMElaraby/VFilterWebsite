# Quickstart: validate 002

## Tests only
```bash
php artisan test tests/Feature/Contract tests/Feature/Settings tests/Feature/Console
./vendor/bin/phpstan && ./vendor/bin/pint --test
```

## Against SQL Server (empty VFILTER)
```bash
php artisan migrate                 # 001 + 002 tables
php artisan vfilter:install-defaults
php artisan vfilter:install-defaults  # second run: "0 rows added"
php artisan test --group=sqlsrv     # column types: nvarchar / datetime2 / bit / bigint
```
Check: `SELECT area, version FROM filter_config_version` → 6 rows; `filter_modules` → Gateway,
Download, Agent1 (disabled); `filter_cooldowns` → 17 rows at 0.

## Local SQLite preview
Same commands with `DB_FILTER_DRIVER=sqlite` in `.env`.
