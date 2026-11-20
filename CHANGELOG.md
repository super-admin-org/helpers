# Changelog

## Unreleased

### Added

- The migration folder is declared on the extension (`$migrations`), so `php artisan admin:upgrade` (in super-admin versions that have it) lists pending migrations and runs them. `php artisan migrate` keeps running them as before.
