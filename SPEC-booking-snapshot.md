# Spec: booking-snapshot

## Objective
Ground standalone conversion in a freshly locked Booking while preserving inspected edits.

## Contract and success criteria
- Under transaction load locked Booking. Return existing order idempotently before new-assignment effects. Reject nonconvertible booking for new conversion.
- Overlay only explicitly dirty inspection fields from caller (receive/return methods and addresses, appointment, notes) on locked row. Never overlay persisted status/customer/reward/promotion markers or other stale attributes.
- Use this locked snapshot for all derived data and side effects. Preserve caller reward-reservation marker cleanup relied on by BookingService subsequent save.
- Keep completeReceivingInspection for historical Pending. Do not introduce a new public conversion route.

## Testing strategy
Existing conversion suite plus standalone stale snapshot, invalid status, preserved unsaved inspected address/method and idempotency. Full point/promotion/delivery atomic rollback tests remain required.

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
