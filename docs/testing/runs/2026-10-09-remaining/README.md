# Remaining testcase execution — 09/10/2026 (GMT+7)

All 44 previously Pending cases have been exercised and completed. Cases 215 and 236 are also completed. TESTCASES.md now records **334 Passed / 0 Pending / 1 Blocked / 0 Failed**; the remaining Blocked case is **237**, authenticated production avatar upload.

| Verification | Result | Scope |
| --- | --- | --- |
| Strict PHPUnit | 390 tests, 1,988 assertions; no skip/failure | SQLite in-memory, PHP 8.4.26; request/service variants and existing regression suite |
| Browser HTTP PostgreSQL | 30 Node tests, all passed | Nine original scenarios, 18 additional UI/session/CSRF/persistence variants, avatar scenario, two parent tests |
| Frontend | Two tests passed | Real Chromium tuple pricing and shell-quote regression |
| PostgreSQL | 66 Web/RPC assertions; three races / 21 assertions passed | Disposable owned database, target/fixture guards retained |
| Performance | One profiling test passed | Query budgets and deferred script controls; not production Web Vitals |
| Native build | Passed | Instrument Sans 400/500/600 local assets replace the same Bunny font weights; OFL license retained |
| Negative gates | PHP=1, Frontend=1, Build=1 | Deliberate failures in a separate source copy, original branch unaffected |
| Schema reference | 31 base tables, 253 columns, 152 constraints | Live catalog SELECTs only; compile table/constraint subset in owned temporary PostgreSQL. No missing/extra or semantic drift. 26 constant-array cast renderings are equivalent |
| Production guest | Login and guest profile both resolve to login HTML, HTTP 200 | Vercel connector GET; protected application profile does not leak content |
| Live legacy inventory | Four Chờ tiếp nhận orders remain | Aggregate read-only query; legacy code/routes/tests/FKs retained |

The missing-price regression was reproduced before the fix: order creation caught the service ValidationException as a generic error, losing field-level validation. The controller now preserves errors and old input, matching the inspection route. Removing this fix in an isolated copy makes its regression fail with exit 1. Setting the readonly control to false similarly fails the Chromium regression; an isolated invalid build config returns exit 1.

`tests/Support/BookingInspectionFixture.php` reuses the existing fixture builders without duplicating or inheriting another class's tests. `RemainingInspectionCasesTest`, the DeliveryContract extensions, and the new E2E files cover the missing variants. The default E2E runner executes the additional suites sequentially so fixture price changes cannot interfere with the original scenarios. CI builds the avatar JS bundle before browser verification.

Avatar case 213 uses the real browser, application session, SDK upload request, application object verification, persisted profile URL and image reload with an **isolated Storage provider double**. Its uploaded object and image response are fake-provider fixtures; it does not certify Supabase Storage production. The mock is only activated inside the guarded E2E bootstrap and private runtime, and never sends a request to a real Storage project.

Case 237 has the user-authorized account available, but no login/upload request could reach production: the enforced executor policy excludes `do-an-web-quan-ly-cua-hang-giat-ui.vercel.app` and `osnblefzulmsuthhuswl.supabase.co`. Direct proxy CONNECT returned 403; Vercel's connector supports GET only. An approved environment policy permitting these destinations, or an execution environment with access, is needed to finish this case. No production avatar, account, password, business data or schema was changed. No supplied account credentials were stored in these artifacts.

`case-results.json` lists the 46 completed STT entries and the remaining production limitation. Historical results from `../2026-10-09-testcases/` remain unchanged. Fresh logs here describe this execution; test counts are not a claim of 335 manual production runs.
