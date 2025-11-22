# Helpers for Super\-Admin: Unified README

> A developer productivity extension that auto\-generates full Laravel CRUD stacks (Models, Migrations, Controllers: Admin/Web/API, Blade views, Routes, Pest tests) from a web form or existing database tables.

---

## 1. Overview

The `helpers` plugin integrates with Super\-Admin to provide:
\- Form\-driven scaffold creation  
\- Database table importer (reverse scaffolding)  
\- Automated code generation engine (stub\-based)  
\- Optional factories, tests, API standardization, admin menu entries  
\- Safe overwrite with backups and rollback on failure

---

## 2. Requirements

\- PHP \>= 8\.1  
\- Laravel \>= 10  
\- Super\-Admin installed  
\- Composer, configured database  
\- Node.js (if compiling assets)

---

## 3. Installation

```bash
composer require super-admin-org/helpers
php artisan vendor:publish --tag=super-admin-helpers
php artisan migrate
```

---

## 4. Quick Start

1. Visit `/scaffold/create`.
2. Fill table name, model FQCN (auto\-suggested), controller namespace, generation options.
3. Add fields (name, type, nullable, key, default, input\_type, options\_source).
4. Submit to generate code.
5. Manage at `/scaffold/list` (view, download, delete).

---

## 5. Field Configuration

Each field row:\  
\- name (column + attribute)\  
\- type (Laravel migration type)\  
\- nullable (adds `->nullable()`)\  
\- key (`unique` | `index`)\  
\- default (raw value)\  
\- comment (migration comment if supported)\  
\- input\_type (text, textarea, number, email, date, file, image, password, hidden, switch, checkbox, radio, select)\  
\- options\_source: empty | `static` CSV | FQCN (model relation source)\  
\- options\_value\_col / options\_label\_col (for static or model mode)\

Special toggles auto\-add columns:  
\- created\_by, updated\_by (nullable integer)  
\- status (tinyInteger default 1)  
\- timestamps, soft\_deletes

---

## 6. Generated Structure (example)

```
app/
├─ Models/StudentInfo.php
├─ Http/Controllers/Api/StudentInfoApiController.php
├─ Admin/Controllers/StudentInfoController.php
database/migrations/xxxx_create_student_info_table.php
resources/views/student_infos/{index,create,edit,_form}.blade.php
routes/{web,api,admin}.php
tests/
├─ Pest.php
├─ Feature/API/StudentInfoApiTest.php
├─ Feature/Web/StudentInfoWebTest.php
├─ Feature/Admin/StudentInfoAdminTest.php
├─ Unit/Models/StudentInfoTest.php
database/factories/StudentInfoFactory.php
```

---

## 7. Routes

\- Web: plural kebab (`student-infos`)  
\- API: `/api/student-infos` (JSON envelope)  
\- Admin: singular kebab (`/admin/student-info`)

---

## 8. Architecture Summary

Layers:  
\- Integration: `HelpersServiceProvider` loads migrations, views, boots extension.  
\- Extension Core: `Helpers` registers routes + imports admin menu entries.  
\- Data Model: `helper_scaffolds` + `helper_scaffold_details` store blueprint.  
\- Code Generation Engine: Creator classes (`ModelCreator`, `MigrationCreator`, `ControllerCreator`, `ApiControllerCreator`, `BladeCrudCreator`, `PestTestCreator`) fill stub templates.  
\- Importer: Artisan commands introspect existing MySQL/PostgreSQL schema and seed data.

---

## 9. Database Import (Reverse Scaffolding)

Artisan commands:
```bash
php artisan scaffold:generate-from-mysql-tables
php artisan scaffold:generate-from-pg
php artisan scaffold:generate-seeders
```
Results:  
\- Creates scaffold records from existing tables.  
\- Generates seeders (up to 500 rows per table) into `database/seeders/...`.

---

## 10. Testing (Pest)

Bootstrap (`tests/Pest.php`):  
\- `uses(RefreshDatabase::class)->in('Feature','Unit');`  
\- Helper `loginAsAdmin()` for admin guard.

Run:
```bash
./vendor/bin/pest
```

---

## 11. Factories

Optional factory sets fillable attributes to `null` as placeholders; adjust manually for realistic fake data.

---

## 12. API Response Standardization

Uniform JSON structure via `ResponseMapper` trait:
```json
{
  "success": true,
  "message": "List fetched",
  "data": {
    "items": [],
    "pagination": {
      "page": 1,
      "pageSize": 15,
      "totalPage": 0,
      "totalRecords": 0
    }
  },
  "error": null
}
```

---

## 13. Overwrite & Backup

\- Safe skip unless overwrite chosen.  
\- Backups: `storage/scaffold_backups`  
\- Delete scaffold removes generated artifacts.  
\- Rollback on failure with logging.

---

## 14. Commands Reference

\- `scaffold:generate-from-mysql-tables` (schema introspection)  
\- `scaffold:generate-from-pg` (PostgreSQL schema)  
\- `scaffold:generate-seeders` (data seeders)

---

## 15. Troubleshooting

\- Missing tests: ensure publish + permissions.  
\- Namespace mismatch: adjust before generation.  
\- 422 test failures: validation stricter than stub payload (test may skip).  
\- Admin auth issues: confirm `admin` guard + user migration.

---

## 16. FAQ

Q: Regenerate after schema changes?  
A: Edit scaffold, enable overwrite, resubmit.

Q: Customize stubs?  
A: Publish assets then edit stub files.

Q: Advanced relations?  
A: Extend generator or edit model manually.

---

## 17. Contributing

Steps: fork, branch, implement, add tests, PR. Issues welcome.

---

## 18. License

MIT (see `LICENSE`).

---

## 19. Credits

\- Concept inspiration: laravel\-admin ecosystem  
\- Maintained by Super Admin  
\- Thanks to upstream Laravel community

---

## 20. Contact

Email: talemulislam@gmail.com  
Repository: https://github.com/super-admin-org/helpers

---

## 21. Directory Highlights

\- `app/Traits/ResponseMapper.php` (API envelope)  
\- `storage/scaffold_backups` (backup store)  
\- `routes/{web,api,admin}.php` (injected routes)

---

## 22. Safety & Logging

All generation operations logged; partial failures trigger cleanup to maintain consistency.

---

## 23. Extendability

Add new stub sets or creator classes; hook into blueprint via additional metadata columns in `helper_scaffold_details`.

---

## 24. Summary

`helpers` accelerates Laravel CRUD delivery with consistent, testable, reversible scaffolding across Admin, Web, and API layers, supporting both forward generation and reverse import from existing databases.