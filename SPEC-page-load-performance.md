# Spec: Faster administrative page loads

Status: Approved by owner on 2026-10-08; implementation authorized.
Date: 2026-10-08, Asia/Ho_Chi_Minh. Baseline: main `9f9997b`.

## Objective and assumptions

Improve loading of the existing Laravel/Blade administration website while preserving authentication, authorization, inspection-first and legacy behavior, notification delivery and financial calculations. Prioritize the staff dashboard and shared login/admin assets. Retain the existing visual design and framework. This is a focused page-load improvement; production schema changes and architecture migration are outside scope.

Keep an optimization only when comparable measurements show reduced work or latency and correctness checks remain green. No claimed percentage speedup on production without production measurements.

## Baseline and identified work

Measured five navigations per route on an isolated checkout copy, real Chromium 151, PHP 8.4.26 and disposable PostgreSQL 17. Browser cache disabled; original production controllers/templates used. Test-only router counts queries without logging SQL/bindings. Actual login/session/permissions enabled; fixture grants dashboard permission so login redirects to the staff dashboard. First profiling attempt expected the old orders redirect and failed; the corrected run completed all measurements.

| Route | Queries/request | Median TTFB (ms) | Median DOMContentLoaded (ms) | Median load (ms) | Resource body bytes |
|---|---:|---:|---:|---:|---:|
| /orders | 18 | 77.6 | 179.7 | 198.2 | 609787 |
| /orders/create | 16 | 66.9 | 187.8 | 192.8 | 618956 |
| /staff/dashboard | 15 | 65.4 | 173.4 | 181.0 | 609787 |

Limitations: local small fixture, uncompressed local HTTP, no production DB network latency/cold starts. Existing E2E asset policy provides verified official SweetAlert2 bytes and excludes decorative font/icon external CSS. These values are navigation timing, not field Core Web Vitals or CDN measurements. Resource bytes are body bytes reported by Resource Timing, not compressed production transfer sizes. Full raw evidence remains workspace-only at work/perf-baseline-run.log and work/performance-baseline.json. Profiling copy contains no .env and cleanup removed owned services.

Source-supported candidates:

- Staff DashboardController executes three separate order-status count queries. Replace them with one grouped/conditional aggregate while preserving every status bucket, empty data, staff scheduling filters and all rendered values. Target 15 → 13 total queries on the same fixture. Do not change the page's visibility rules.
- Shared admin/login layouts load Bootstrap and SweetAlert2 synchronously. Investigate deferred loading and serving the already verified official SweetAlert2 version locally. Preserve dependency execution order, flash notifications, confirmation dialogs, dropdowns and login controls. Measure first paint and parser/interaction readiness with the same controlled asset latency before accepting the change; do not assume a gain from attributes alone.
- Admin dashboard repeats its order-status aggregation for two widgets. Measure the full admin page before considering reuse of its existing result. No optimization there is accepted on the staff baseline alone.

Existing permission caching is request-scoped and supports immediate revocation on the next request. Preserve it. The orders page query count is not proof of an N+1 issue. Subsequent source review, prompted by the owner reporting every production page is slow, identifies redundant eager loads in OrderService::getAll and unused all-customer/points reads in DonHangController::index. The only application consumer is the orders index; its template uses customer, order details/service and persisted order columns, and settled controls use the status enum. This additional read optimization is within the page-load goal: retain these fields and filters/pagination, preserve find/detail loading and all financial writes, and enforce <=5 service queries and <=10 actual HTTP queries on the fixed fixture. Production runtime logs show 8–16 seconds inside PHP on sampled authenticated pages, so the project must not present frontend deferral alone as a complete production fix.

## Stack and project structure

Laravel 13 / PHP 8.4, Blade, Bootstrap, Vite 8 and PostgreSQL 17 verification fixtures. No new npm/Composer dependency is planned.

- app/Http/Controllers/Staff/DashboardController.php: operational dashboard reads.
- app/Http/Controllers/Admin/DashboardController.php: financial/admin dashboard reads if separately measured.
- resources/views/layouts/app.blade.php and resources/views/auth/login.blade.php: shared page assets and script lifecycle.
- public/assets/: any retained, versioned official local vendor asset with provenance and hash recorded.
- tests/Feature/, tests/Frontend/, tests/E2E/: behavior and bounded performance regressions.
- docs/testing/: reproducible measurement command, before/after ledger and limitations.

## Commands

Native verification:

```bash
php vendor/bin/phpunit --fail-on-warning --fail-on-risky
php artisan view:cache
node --test tests/Frontend/*.test.mjs
bash scripts/test-postgres.sh
bash scripts/test-web-e2e.sh
npm run build
git diff --check
```

Current workspace isolated profiling command (not yet a permanent repository runner):

```bash
TASK_TEST_REPO=/workspace/work/perf-baseline \
WEB_E2E_ASSET_ARCHIVE=/workspace/work/e2e-assets/sweetalert2.tgz \
PHP_BIN=/workspace/work/verification-runtime/php-e2e \
bash /workspace/work/perf-baseline/scripts/test-web-e2e.sh
```

Before implementation, the approved plan must provide a reproducible permanent benchmark using the repository's guarded fixtures. Profiling never boots the live .env. Native CI must validate builds and tests if local runtime/network limitations block them.

## Code style

Use existing Eloquent query conventions, enums for persisted statuses, explicit PostgreSQL PascalCase quoting in raw aggregates and integer count defaults. Prefer reusing a single clearly named result over new cache layers or helper abstractions.

```php
$statusCounts = DonHang::query()
    ->selectRaw('"TrangThai", COUNT(*) AS total')
    ->groupBy('TrangThai')
    ->pluck('total', 'TrangThai');
```

Keep asset changes explicit in the current layouts. Do not defer a dependency before checking every inline consumer. Record official vendor source/version/integrity if self-hosting an existing asset.

## Testing and measurement strategy

Measure each candidate independently, using the same data, browser cache policy, PHP/storage configuration and request sample count. Record median and range, including first-load and subsequent navigation separately where useful. A deterministic query-count reduction is measurable eliminated DB work; a wall-clock gain inside observed noise is not evidence of a latency improvement. A labeled latency-injection experiment can model DB/CDN delays, but must not be presented as a production benchmark.

For dashboard aggregation, exercise zero rows and all current/legacy status buckets and enforce a single aggregate query for the three KPIs. Compare returned values before and after on identical data. For asset loading, verify real browser errors, flash/modal/confirmation behavior and loading with controlled delayed resources. Keep existing HTTP session/CSRF/permission and financial E2E checks enabled.

Record both retained and reverted attempts. Add a deterministic query-count/asset-order regression guard without fragile millisecond gates. Field LCP/INP/CLS measurements require access to an agreed production/test URL; this draft does not claim those values are known.

## Boundaries

- Always: measure before/after; retain business/security checks; verify nonzero failure exits; use disposable owned fixtures; run appropriate tests/build; document limitations and changes.
- Review separately before expanding scope: production schema/index changes, new dependencies, persistent caches, authentication/session/permission changes, new monitoring services or deployment configuration changes.
- Never: cache balances or permissions across requests; hide failing tests; remove legacy behavior/tests; alter financial formulas to reduce work; read/write live business data during profiling; commit secrets; claim synthetic fixture results as production measurements.

## Success criteria

1. Staff status KPIs keep identical values and reduce their aggregate queries from three to one; full request on the baseline fixture uses at most 13 queries.
2. Any asset change shows a repeatable improvement in its measured loading/parser/interaction metric beyond noise. If it does not, revert it and record the attempt.
3. Existing login, session, CSRF, ACL, notification/confirmation, unit/pricing and paid-write behaviors pass real browser and backend regressions.
4. Production build and existing CI jobs pass on the exact final head before publication/merge.
5. Documentation includes reproducible measurements, vendor provenance where applicable, and scope/production limitations.

## Open questions and approval

No product decision blocks this proposed narrow scope. Owner confirmed this scope and instructed implementation in the next turn. Optional later input: the production/test URL and the particular page users experience as slow. Existing push/merge authorization remains in force once the reviewed implementation passes its checks.
