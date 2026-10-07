# Spec: active-assignment

## Objective
Allow only active employees for new or changed assignments while preserving historical attribution.

## Contract and success criteria
- Employee status value is exactly Hoạt động. Shared assignment validation returns a field-specific ValidationException.
- Create order, convert Booking and changed order/delivery assignment validate active status in service as well as HTTP requests.
- Updating unrelated data may retain the persisted employee even if inactive; active dropdowns include the current historical employee only on editing.
- Booking inspection staff is always a new assignment and must be active. Employee status is not account authorization.

## Testing strategy
Inactive/missing/new active assignments through HTTP and direct services; preserve unchanged inactive historical assignments; no side effects on rejection.

## Stack and commands
Laravel 13.34.0 (composer.lock), PHP 8.4 runtime, Blade and existing Bootstrap UI, PHPUnit 12.5.37, PostgreSQL production / isolated SQLite tests.
- Test: `php vendor/bin/phpunit` (phpunit.xml forces SQLite :memory:).
- Format: `php vendor/bin/pint --dirty`; verify: `php vendor/bin/pint --dirty --test`.
- Views: `php artisan view:cache`; frontend: `npm run build`.
- UI regressions: `node --test tests/Frontend/*.test.mjs`.

## Structure and style
Existing app/Services, app/Http/Requests/Admin, app/Http/Controllers/Admin, resources/views/admin, public/assets/js, tests/Feature and tests/Frontend. Follow existing PHP namespaces, constructor promotion, guard clauses and ValidationException field errors. Example: `throw ValidationException::withMessages(['employee_id' => 'Nhân viên phải đang hoạt động.']);`. Use DOM Option/textContent for external labels.

## Boundaries
Always validate external inputs and callable mutation boundaries, preserve transaction atomicity, run regressions before committing. Ask before schema/dependency/CI changes if ever necessary (none planned). Never mutate production test data, commit secrets, edit vendor or remove legacy Pending support. Web remains authoritative; no new endpoint, payment override or financial policy. Pure behavior-preserving cleanup is separate from fixes.

## Sources
Official Laravel 13 sources retrieved before implementation:
- https://github.com/laravel/docs/blob/13.x/validation.md#rule-exists (constrained exists rules), nullable and required-with.
- https://github.com/laravel/docs/blob/13.x/queries.md#pessimistic-locking (`lockForUpdate` within transaction).
Project contracts: docs/supabase/BUSINESS_RULES.md and SCHEMA_AUDIT.md. Framework docs establish API behavior; business rules come from this project.

## Open questions
No blocking questions. No redelivery after completed slips is introduced; that requires a future explicit business workflow.
