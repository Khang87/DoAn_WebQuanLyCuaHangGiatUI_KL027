# Spec: order-pricing

## Objective
Keep every effective price tuple selectable and make previews agree with server-authoritative pricing.

## Contract and success criteria
- Provider: PricingService returns latest effective BangGia for each service/garment/unit; options additionally require usable active units. Consumers keep the full triple.
- Create/edit item controls expose eligible units, preserve valid selected units, auto-select only when exactly one option exists, and require an explicit selection when multiple choices exist.
- Price input is readonly; old or tampered prices never override the selected current tuple. Missing tuple clears price and invalidates the unit choice.
- Quantity/weight behavior and minimum kg charge follow existing rules; switching unit updates them.
- Existing resource routes and request field names stay unchanged.

## Testing strategy
PHP regression for options plus executable DOM/UI tests for multiple units, switching, restored inputs, no price override, missing tuple and row remove/add names. Existing exact-tuple order persistence test remains required.

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
