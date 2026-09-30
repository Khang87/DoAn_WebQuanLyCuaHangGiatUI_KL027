# Sky Laundry

Sky Laundry is a laundry-shop management system for customer, staff, manager, and shop-owner workflows. It provides a Laravel web application and a versioned JSON API, backed by the existing Supabase PostgreSQL schema.

## Technology stack

- PHP 8.3+
- Laravel 13.x (`laravel/framework` `^13.17`)
- PostgreSQL on Supabase
- Blade, Bootstrap-based UI, Tailwind CSS 4, and Vite
- PHPUnit 12

The versions above reflect the dependency manifests in this repository; they are not Laravel 10 / PHP 8.1 requirements.

## Core features

- Role- and permission-based access checks at route middleware, controller/Gate, and Blade presentation layers.
- Multiple roles per account through `TaiKhoan_VaiTro`, with grants through `VaiTro_Quyen` and `Quyen`.
- Inactive accounts (`TaiKhoan.TrangThai`) do not receive owner bypass or permission access.
- Order, booking, invoice, payment, delivery, customer, service, pricing, promotion, and reporting workflows.
- Order line totals support weight-based pricing with a configurable minimum weight, and piece-based pricing for units such as item, pair, set, or blanket.
- PostgreSQL identifiers retain their schema-defined PascalCase names.
- Destructive Artisan commands are guarded to protect the provisioned database schema.

## Development progress

### Completed

- Implemented the dynamic RBAC path over `TaiKhoan_VaiTro`, `VaiTro_Quyen`, and `Quyen`, using the existing PascalCase schema.
- Added request-scoped permission lookup and cache invalidation after role-permission changes.
- Added active-account checks to the owner bypass, role middleware, and permission middleware.
- Split resource authorization by action and added per-permission checks to protected API endpoints.
- Added regression coverage for customer admin/API denial and action-level permission boundaries.
- Added order-form state switching and line-total calculation for weight and piece-based units, with server-side calculation in `TinhTienGiatUiService`.
- Kept unsupported garment-condition functionality explicit rather than writing to a table absent from the current schema.

### Fixed issues

- Corrected route parameter usage and PascalCase database mappings in the areas previously refactored.
- Corrected the garment-category detail relationship to use schema-backed `LoaiDoGiat` and pricing data.
- Removed confirmed unused compatibility wrappers, duplicate reporting code, unreachable views, and default example tests.
- Closed authorization bypasses caused by OR-combined resource permissions and missing API permission checks.

### Regression-test status

After the cleanup, the full suite reports 216 tests, 24 passed, 192 skipped, and 239 assertions. The six focused authorization regression tests pass. Many skipped cases are legacy tests built for an older English-named schema; skipped tests are not counted as passing coverage.

### Roadmap

- Continue auditing `DonHang`, `ChiTietDonHang`, and `HoaDon` mappings against the latest `schema.sql` snapshot and runtime queries.
- Exercise order-entry calculations across create/edit and server validation paths, including null, zero, and minimum-weight cases.
- Add a customer-facing order portal only with ownership enforcement that scopes every order by the authenticated account's `KhachHangID`.
- Reduce the skipped legacy test backlog and add regression tests against the supported schema without connecting tests to Supabase Live.

## Architecture and database safety

- **Read-only DDL:** Treat `schema.sql` as the reference snapshot. Do not edit it, run migrations, or issue DDL against Supabase.
- **No live test writes:** Never point automated tests, seeders, or local setup commands at the live Supabase database.
- `phpunit.xml` configures tests to use SQLite `:memory:`. Some existing feature tests use `RefreshDatabase`; this is isolated to that in-memory test connection and must not be redirected to the live database.
- Do not commit `.env` credentials or expose database connection strings.
- Do not run seeders against Supabase Live.

## Project structure

```text
.
├── app/
│   ├── Enums/
│   ├── Exceptions/
│   ├── Exports/
│   ├── Http/
│   │   ├── Controllers/       # Admin, API, Auth, and Staff
│   │   ├── Middleware/        # Role and permission guards
│   │   ├── Requests/          # Validated request inputs
│   │   └── Resources/         # API response resources
│   ├── Models/                # Schema-backed Eloquent models
│   ├── Observers/
│   ├── Policies/
│   ├── Providers/
│   ├── Services/              # Business logic and calculations
│   └── Support/               # Permission cache, mappings, and helpers
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/            # Existing files; do not run against Supabase
│   └── seeders/
├── resources/
│   └── views/                  # Admin, staff, auth, layouts, components
├── routes/
│   ├── api.php
│   └── web.php
├── schema.sql                  # Read-only schema snapshot
├── tests/
│   ├── Feature/
│   └── Unit/
├── composer.json
├── package.json
└── phpunit.xml
```

## Getting started

Requirements: PHP 8.3+, Composer, Node.js/npm, and access to an already-provisioned PostgreSQL database that matches `schema.sql`.

1. Install PHP and frontend dependencies:

   ```sh
   composer install
   npm install
   ```

2. Create a local environment file and application key:

   ```sh
   cp .env.example .env
   php artisan key:generate
   ```

   On Windows PowerShell, use `Copy-Item .env.example .env` instead of `cp`.

3. Configure the local `.env` with the provisioned database connection values. Keep credentials private. Do not run migration, schema, or seeding commands against Supabase.

4. Build frontend assets and start the local server:

   ```sh
   npm run build
   php artisan serve
   ```

   For frontend development with hot reload, run `npm run dev` in a separate terminal.

## Verification commands

Run tests using the SQLite in-memory settings in `phpunit.xml`:

```sh
php artisan test
```

Inspect registered routes without connecting to or changing the database:

```sh
php artisan route:list
```
