# Spec: delivery-contract

## Objective
Make delivery CRUD obey order lifecycle and support unscheduled pending returns.

## Contract and success criteria
- Keep POST/PUT/DELETE deliveries routes and request field names. HTTP business validation errors return existing session field errors or Laravel JSON 422; no SQL details.
- Every mutation locks the parent order in a transaction before checking lifecycle/duplicates; update/delete reload the delivery and reject stale parent changes. Order and leg cannot be changed on an existing slip.
- Cancelled or Paid orders reject all delivery mutations. Pending planning may occur before washing completes. NHAN_DO execution allowed only Pending/Received orders; GIAO_DO execution/completion allowed only Washed/Delivering/Delivered orders. No automatic order transition via delivery CRUD.
- Same order/type may have at most one non-cancelled slip, including completed history. Cancelled slips can be replaced; completed slips cannot be reopened/deleted. No speculative redelivery feature.
- States: pending -> in progress/completed/cancelled; in progress -> completed/cancelled; terminal states remain terminal. Unchanged terminal record can retain historical fields, but cannot mutate identity or reopen.
- NHAN_DO requires schedule. GIAO_DO pending/cancelled may omit schedule; execution/completion requires schedule. Date/time must be both present or both absent. Newly set/changed schedule is future; unchanged historical schedule may be retained on update. Clearing is allowed only for pending/cancelled returns.
- Status/notes-only updates preserve absent fields, including status and schedule. Changed fulfillment validates home address; no forced default on service partial updates.
- UI shows explanatory schedule hint and server errors; parent/type are readonly on edit. Eligible order list permits the other leg and cancelled replacements, excludes cancelled/paid orders.

## Testing strategy
Service CRUD, duplicate/replacement, lifecycle/type checks, terminal state guards, immutable identity, unscheduled request and form, partial update, historical schedule retention. PostgreSQL locking is separately verified when an isolated local DB is available; SQLite cannot prove races.

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
