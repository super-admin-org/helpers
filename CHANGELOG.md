# Changelog

## v1.18.3 - 2026-11-20

### Changed

- Works with super-admin 2.x: the package now requires `super-admin-org/super-admin` `^1.0|^2.0`. Earlier versions required `^1.0`, so Composer could not install them next to super-admin v2.0.0.
- Package homepage and documentation: https://super-admin.org/laravel/extension-helpers/

## v1.18.2 - 2026-11-20

### Added

- The migration folder is declared on the extension (`$migrations`), so `php artisan admin:upgrade` (in super-admin versions that have it) lists pending migrations and runs them. `php artisan migrate` keeps running them as before.
