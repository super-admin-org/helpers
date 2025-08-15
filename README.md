Helpers for Super-Admin
=========================
# Laravel Scaffolding System (Super-Admin Extension)

A **developer productivity extension** for [Super-Admin](https://github.com/super-admin-org/super-admin) that automates CRUD generation for Laravel applications.  
From a simple **web interface**, you can generate **Models, Controllers, Views, Migrations, Routes, and Pest tests** instantly.

---

## Overview

This extension eliminates repetitive boilerplate coding by providing a form-based interface to create Laravel CRUD modules.  
It supports generating:

- **Eloquent Models**
- **Migrations**
- **Controllers** (Admin, Web, API)
- **Blade Views**
- **Routes** (Admin, Web, API)
- **Pest Unit & Feature Tests**

All generated code follows a consistent and maintainable structure.

---

## Workflow

```
flowchart TD
  A[Open Scaffold Form] --> B[Fill Details: Model, Controller, Fields]
  B --> C[Submit to ScaffoldController@store]
  C --> D[Validate & Save to Database]
  D --> E[Generate Laravel Resources]
  E --> F[Insert Routes: Admin, Web, API]
  F --> G[Add Menu Entry (optional)]
  G --> H[Manage from Dashboard]
```

---

## Key Features

### 1. **Automatic Resource Generation**
- Models
- Controllers (Admin, Web, API)
- Migrations
- Blade Views
- Routes (Admin, Web, API)
- Pest Test Stubs (Unit + Feature)

### 2. **Intelligent File Handling**
- Backup before overwriting
- Remove generated files on scaffold deletion

### 3. **Admin Menu Integration**
- Optionally add an admin panel menu entry

### 4. **Standardized API Responses**
- Includes `ResponseMapper` trait for consistent JSON output

### 5. **Safe & Reliable**
- Rollbacks on failure
- Logs all important actions

---


---

## Features

- **Automatic Resource Generation**: Generate models, controllers (admin, web, API), migrations, Blade views, routes (admin, web, API), and test cases in Pest.
- **Intelligent File Handling**: Backup existing files before overwriting and remove unused scaffold files on deletion.
- **Admin Menu Integration**: Automatically adds a menu link to the admin panel.
- **API Response Standardization**: Includes `ResponseMapper` trait for consistent JSON formatting.
- **Error Handling**: Rolls back partial files on failure and logs all important actions.

---

## Usage

1. **Open the Scaffold Form** – Go to `/scaffold/create` in your browser.  
2. **Fill in Details** – Model name, table name, controller name, view path, and add multiple fields with type, name, and label.  
3. **Submit** – Backend validates data and generates resources instantly.  
4. **Manage from Dashboard** – View, download, or delete scaffolds from `/scaffold/list`.

---

##  Advantages

- ** Time Saver** – Generate full CRUD in seconds.  
- ** Consistency** – Standardized Laravel code structure.  
- ** Safety** – Backup before overwrite.  
- ** Extensible** – Add more generation rules easily.

---

## Why This Plugin?

- **Ship faster** – Go from schema to Admin + API + Blade in minutes.  
- **Stay consistent** – Shared conventions reduce “decision tax.”  
- **Safer refactors** – Included tests catch regressions early.  
- **Team-friendly** – Great onboarding and shared patterns.


## Installation

```bash
composer require super-admin-org/helpers
php artisan admin:import helpers
```

---

## Usage

1. **Open the Scaffold Form**  
   Navigate to `/scaffold/create`.

2. **Fill in Details**  
   - Model Name  
   - Table Name  
   - Controller Name  
   - View Path  
   - Field Definitions (type, name, label, etc.)

3. **Generate Resources**  
   On submission:
   - Files are generated
   - Routes are registered
   - Optional menu link is added

4. **Manage from Dashboard**  
   View, download, or delete scaffolds from `/scaffold/list`.

---

## Benefits

- ** Save Time** – Full CRUD in seconds  
- ** Consistent** – Unified Laravel code patterns  
- ** Safe** – Backups prevent accidental loss  
- ** Extensible** – Easily add new generation rules  

---

## Example Generated Structure

```
app/
├─ Admin/Controllers/StudentInfoController.php
├─ Http/Controllers/Api/StudentInfoApiController.php
├─ Http/Controllers/Web/StudentInfoController.php
├─ Models/StudentInfo.php
database/migrations/
├─ 2025_..._create_student_info_table.php
resources/views/student_infos/
├─ index.blade.php
├─ create.blade.php
├─ edit.blade.php
├─ _form.blade.php
routes/
├─ api.php
├─ web.php
tests/
├─ Pest.php
├─ Unit/Models/StudentInfoTest.php
├─ Feature/API/StudentInfoApiTest.php
├─ Feature/Web/StudentInfoWebTest.php
├─ Feature/Admin/StudentInfoAdminTest.php
```

---

## Pest Testing

Generated Pest test stubs include:

- **Unit Tests** – Model configuration, traits, and fillables  
- **Feature Tests** – CRUD for Admin, Web, and API endpoints

Run tests:

```bash
./vendor/bin/pest
```

---

## 🛠 Developer Notes

- **Backup Location:** `storage/scaffold_backups`  
- **Generated Routes:**  
  - Admin: `routes/admin.php`  
  - Web: `routes/web.php`  
  - API: `routes/api.php`  
- **Trait Location:** `app/Traits/ResponseMapper.php`  

---

## Contributing

We welcome contributions! You can help with:

- Writing & improving tests  
- Suggesting new generation features  
- Translating documentation  
- Sponsoring development  

---

## License

Licensed under the [MIT License](LICENSE).

---

## Credits

- **Original Concept:** [laravel-admin-ext/helpers](https://github.com/laravel-admin-extensions/helpers)  
- **Maintained by:** [Super Admin](mailto:talemulislam@gmail.com)  
- **Special Thanks:** z-song (Original Laravel-Admin Author)  
