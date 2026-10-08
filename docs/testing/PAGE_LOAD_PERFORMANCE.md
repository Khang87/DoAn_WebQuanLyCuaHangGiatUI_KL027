# Page-load performance verification

Date: 2026-10-08, Asia/Ho_Chi_Minh. Application baseline main `9f9997b`. Owner approved `SPEC-page-load-performance.md`, then reported that every production page loads slowly. The resulting read-path changes preserve the existing page data, financial writes and request-scoped permission cache.

## Retained changes

| Change | Before | After | Evidence |
|---|---:|---:|---|
| Staff operational KPI aggregate queries | 3 | 1 | Mixed status and empty-state PHPUnit tests; original failed both query guards |
| Full staff dashboard HTTP fixture queries | 15 | 13 | Five actual browser/HTTP/PostgreSQL samples per version |
| Populated orders list service queries, including display reads | 12 | 5 | Fixture contains every previously eager-loaded relation; values, filters, sort, pagination and paid lock retained |
| Full orders index HTTP fixture queries | 18 | 10 | Five actual HTTP samples; removed unused all-customer/points reads and list-irrelevant relations |
| Shared admin/login script loading | Parser-blocking | Deferred, in existing order | Actual UI works before held SweetAlert download completes; flash/dropdown/confirmation checks pass |

Orders index still loads customer and detail/service data required by its rows. Detail/find eager loads are unchanged. No database index/schema, dependency, persistent cache, region, session driver, permission or money formula changes.

## Comparable asset experiment

Both versions used the same final profile fixture, grouped dashboard controller, original orders-list read path, cache-disabled Chromium 151, PHP 8.4.26 and PostgreSQL 17. Only the two layouts' Bootstrap/SweetAlert2 `defer` attributes differ. SweetAlert2 uses identical verified official bytes and a controlled **500 ms** response delay; external decorative font/icon CSS remains explicitly excluded by the existing E2E policy. Five samples per page. These are local synthetic results, not production CDN/network or field Web Vitals.

The metric is elapsed time from document response start to a successful actual sidebar-button click, measured by the same CDP polling loop. It describes early navigation interaction, not INP or total page loading.

| Page | Sync median/range (ms) | Deferred median/range (ms) |
|---|---:|---:|
| /orders | 685.9 / 566.9–773.9 | 169.5 / 140.9–241.1 |
| /orders/create | 676.7 / 640.1–720.4 | 188.6 / 135.7–245.0 |
| /staff/dashboard | 591.9 / 583.7–661.2 | 146.3 / 137.8–339.8 |

Ranges do not overlap for this controlled interaction experiment. Ordinary first-paint/full-load timings vary and do not establish a general first-paint or full-load gain. Resource body bytes remain 609,787 for orders/dashboard and 618,956 for create; no bundle-size reduction is claimed. Browser-cold samples use isolated local compiled views/session configuration; they are not serverless cold-start measurements.

| Attempt | Verdict | Reason |
|---|---|---|
| Group staff KPI queries | Kept | Eliminates two reads, preserves every bucket and empty integer zero |
| Reduce list-only eager loads and unused customer lookup | Kept | Eliminates eight actual HTTP reads; display and lock/filter/pagination checks pass |
| Defer shared scripts | Kept for early interaction | Controlled successful-click timing improves beyond observed variation; held-download login control test fails without defer and passes with it |
| Attribute the change to faster first paint/full load | Rejected claim | Paint/load data is noisy; improvement is early interaction |
| Self-host SweetAlert2 or add font preconnect/caching | Not implemented | No independent measured benefit; retain official-byte test policy and current deployment assets |
| Change function region from metadata alone | Not implemented | Deployment metadata says iad1 but actual request logs report hkg1 |

## Reproduce and regression gates

```bash
# Native PHP, Docker and Chrome/Chromium prerequisites match the existing runner.
CHROMIUM_BIN=google-chrome bash scripts/test-web-e2e.sh performance

# Optional explicitly simulated per-query latency, bounded to 0–50 ms:
WEB_E2E_QUERY_DELAY_MS=30 CHROMIUM_BIN=google-chrome bash scripts/test-web-e2e.sh performance

php vendor/bin/phpunit --filter 'StaffDashboardPerformanceTest|OrderListPerformanceTest' --fail-on-warning --fail-on-risky
```

The existing runner accepts `performance`, creates its owned loopback PostgreSQL fixture and private runtime, and seeds only the permissions needed for profiling/UI checks. `X-E2E-Queries` exists only in the guarded test HTTP bootstrap under `WEB_E2E_PROFILE=1`; production routes/providers never enable this instrumentation. Default `all` clears profile/delay flags and retains the original staff login redirect and permissions. Logs contain counts/timing, not SQL, bindings, passwords or real business rows.

The profile enforces <=13 staff dashboard queries and <=10 orders-index queries on the exact fixture. It also verifies real Bootstrap dropdown behavior, cancelling an actual Booking confirmation sends no POST and preserves DB state, actual logout/login, password toggle while SweetAlert bytes are held, and the real failed-login flash popup. No business HTTP response or ACL is mocked. CI runs this profile after the eight existing business E2E scenarios. Millisecond timing is printed for review rather than used as a fragile CI threshold.

Mutation evidence: an isolated controller copy replaces the ready bucket's Washed status with Cancelled; the mixed fixture fails (5 instead of 9). Restoring synchronous script tags in a separate source copy fails at the held-download login-control deadline. Source originals are untouched; original source runners pass after these experiments. Neither setup failures nor independent environment failures are counted as mutation evidence.

## Production evidence and limits

Read-only Vercel runtime logs for the baseline production deployment show roughly 8 seconds between PHP Accepted/Closing for `/customers`, 15 seconds for `/admin/dashboard` and 16 seconds for `/orders`; timestamps have one-second granularity, and these are samples rather than a p95 benchmark. The process was already serving requests, so frontend assets and an initial cold start alone do not explain all observed server-side time. The actual log region is hkg1; deployment API metadata reports iad1. Supabase project metadata is Singapore (ap-southeast-1), but the production DB endpoint and Redis/session path could not be verified: the environment API suppresses sensitive values.

Direct production HTTP measurement was blocked by the workspace proxy; the Vercel fetch connector also failed. No production credentials, data, session/cache settings or region were changed. Query reduction should reduce required backend work, but this verification does **not** establish that all 8–16 seconds are resolved. Production DB/connect/cache/session latency, per-page TTFB and field LCP/INP/CLS still require measurements in an environment that can access the deployment. Existing Vercel request logs provide ongoing coarse server-duration evidence; no new RUM/monitoring service was installed.
