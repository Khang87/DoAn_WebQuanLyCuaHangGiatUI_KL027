# Implementation plan: order contracts

Scope map approved; user authorized autonomous implementation and push/merge. Specs saved before code.

1. order-pricing: use canonical effective-price query, preserve triples in options, replace unit badges with selects, share row behavior, lock displayed prices. Verify PHP options and executable UI regressions; commit focused fix.
2. active-assignment: shared constrained employee rule and service assertion, create/changed assignment checks, historical dropdown retention. Verify request and service rejections without financial side effects.
3. delivery-contract: transactional order locks, immutable parent/leg, noncancelled duplicate checks, lifecycle rules, schedule pair contract and friendly errors; align forms/order list. Verify direct service and HTTP cases, PostgreSQL race if local DB available.
4. booking-snapshot: fresh lock + narrowly permitted dirty inspection overlay, preserving reward markers and existing idempotency. Verify address/method changes and stale status/customer snapshots.
5. Integrated verification: full suite, frontend DOM/browser checks, formatting, Blade compilation, Vite build; review diff and mutation-check key guards. Record limitations.
6. Publish: commit focused changes, push branch, create PR, check exact-head statuses, merge only after required checks pass, fetch and verify main.

Risks: terminal delivery edits and duplicate guards intentionally tighten invalid paths; UI must not imply unsupported transitions. Historical price preview must follow save semantics. SQLite cannot establish row locking; local PostgreSQL check is separate. No production fixture writes.


# Implementation plan: review verification

## Approval and baseline

The owner approved `CAPABILITY-review-verification.md` and all four module specs, then instructed continuation. This section extends the completed order-contract initiative rather than replacing its history. Base application revision is `0f030d1`; previous order-contract changes are on main via PR #6 (`07e589e`), and documentation via PR #7 (`fdd869e`).

## Architecture decisions

- Reuse PostgreSQL 17 fixtures and the existing RPC business assertions. Convert psql includes to file-relative includes so execution does not depend on `/source`. Add a local fixture identity marker.
- Keep SQLite PHPUnit isolated and unchanged. The new PostgreSQL runner bootstraps the real Laravel application without `.env` or cached production configuration and accepts only explicit local test settings.
- Use committed fixture data for concurrency scenarios. Spawn separate PHP workers/connections, hold a coordinator row lock, and observe both workers blocked before releasing it. This proves overlap without timing-only sleep assumptions.
- Compare Web and RPC outcomes against common expected contract values, including intended actor differences. Do not claim service tests are browser E2E.
- No production schema, API, price policy, legacy retirement, or delivery workflow changes. A reproduced regression gets a targeted diagnosis and spec update before a production fix.
- Workflow uses isolated disposable credentials/resources and read-only GitHub permissions. Actual workflow execution and required-check repository settings are verified separately from YAML syntax.

## Ordered task index

Acceptance criteria and executable checks live in the appended `tasks/todo.md` checklist.

| Task | Module | Deliverable | Dependency | Scope |
|---|---|---|---|---|
| RV1 | postgres-test-runner | Guarded PHP bootstrap and executable unsafe-target checks | — | 2 files |
| RV2 | postgres-test-runner | Docker runner, portable SQL fixtures, suite entry point | RV1 | 3–4 files |
| RV3 | web-rpc-contract-tests | Shared catalog/input cases and real service/RPC comparisons | RV2 | 2–3 files |
| RV4 | postgres-concurrency-tests | Workers, controlled blocking harness and three persisted-state scenarios | RV2 | 3–4 files |
| RV5 | verification-ci | GitHub Actions workflow running repository-native gates | RV3, RV4 | 1–2 files |
| RV6 | verification-ci | README, test coverage and execution instructions | RV5 | 3–4 files |
| RV7 | all modules | Full verification, one guard mutation, diff review and evidence log | RV6 | 1–2 documentation files |

Build order: RV1 → RV2 → RV3 → RV4 → RV5 → RV6 → RV7.

## Checkpoints

1. After RV2: invalid targets fail before access; valid disposable fixture runs existing RPC assertions; SQLite configuration remains unchanged.
2. After RV4: all Web/RPC and concurrency cases pass on actual PostgreSQL; worker timeouts and cleanup are verified.
3. After RV7: existing suite, Chromium check and view compilation pass; native build/audits are recorded honestly; a guard mutation is caught; application source is restored and working tree reviewed.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| Loading production credentials or destructive test setup | Ignore `.env` and cached config, require explicit loopback parameters, fixed disposable database and fixture identity marker before business writes |
| Fixture differs from application/Live shape | Reuse current RPC fixture, minimally add only missing local fixture fields; document that it is not a complete production schema clone |
| False concurrency confidence from sequential scheduling | Independent workers, distinct PostgreSQL sessions, coordinator locks and observed blocking before release |
| Hung worker or leaked container | Bounded deadlines, checked subprocess exit codes, per-run owned resource names and finally/trap cleanup |
| PHP host lacks pdo_pgsql | Verify runtime and prepare isolated PHP 8.4 runtime; document native prerequisites; do not skip PostgreSQL tests |
| Font-download or dependency-audit failure | Preserve native checks and report exact failures; investigate root cause rather than bypass the failing gate |
| Existing repository-wide formatting debt | Format new/touched PHP files; keep unrelated reformatting outside this change |
| Workflow passes but merge remains unprotected | Document required job names and distinguish actual checks from repository branch-protection settings |

## Open questions

No unresolved business requirements. Technical failures discovered while implementing are evidence to diagnose. The owner approved the plan and checklist and instructed implementation; local validation is recorded in tasks/verification.md.


# Implementation plan: web-postgres-e2e

## Approval and scope

Owner approved `SPEC-web-postgres-e2e.md` in conversation on 08/10/2026 after the VER2 review. The owner subsequently approved this plan/checklist and instructed implementation; work follows spec-driven-development and planning-and-task-breakdown. App baseline: main `648612c`; working branch and draft PR #9 retain the completed review and approved spec. Preserve earlier completed plans.

One capability: real browser → existing Laravel HTTP/auth/CSRF/permission → disposable PostgreSQL persisted-state verification. Eight scenarios in the approved spec; no business redesign or production writes.

## Findings from read-only planning

- Booking inspection is `BookingController::inspection → edit(..., true) → admin.bookings.edit`, with no editable DonGia input. Do not implement a readonly price fix on the wrong legacy view.
- Current PostgreSQL bootstrap creates/removes fresh runtime per process and uses array sessions. That is correct for service tests but cannot carry login between HTTP requests. E2E needs one owner-created runtime, stable random APP_KEY and file session storage across requests.
- Login uses Email/MatKhau, active account, role/permission queries and redirect to an authorized page. Existing pgSeed creates accounts without passwords or permissions. Add only isolated E2E seed/schema fields required by actual requests; keep pgActor out of browser authentication.
- Laravel runningUnitTests recognizes environment testing; CSRF middleware may use that to bypass checks. Set isolated app environment e2e, never load repository .env/config cache, assert HTTP runtime is not runningUnitTests, and prove a missing-token POST is rejected (419) before accepting login as verified.
- Bootstrap CSS/JS and app controls are local. Layout and login load SweetAlert2 from jsDelivr; Google Fonts and Font Awesome CSS are external. Their failures cannot be indiscriminately swallowed or replaced by fake Swal handlers.
- Existing CI splits PHP/PostgreSQL and Node/frontend into separate jobs. E2E needs PHP, Node, Chrome and Docker together; use a dedicated bounded job with pinned existing setup actions and lockfile installs, preserving both current jobs.

## Architecture decisions

1. Factor only the loopback/database/marker validation and explicit test connection settings needed by both test bootstraps into a test-layer helper. Existing service runner semantics/SQLite phpunit.xml stay unchanged. HTTP entry point and router live only in tests/E2E; no production test endpoint or auth bypass.
2. Runner owns disposable PostgreSQL, private runtime directory, stable key, generated account password, loopback HTTP listener and browser profile. Validate runtime path/target before seeding or requests. Cleanup is scoped to owned IDs/paths and runs on success, failure and signal; child processes and SQL have deadlines.
3. Serve actual production routes/templates/local assets through Laravel HTTP kernel and PHP local server. Test bootstrap sets file session/cache isolation, array mail and sync queue. Browser navigates login, enters seed credentials and uses real forms/cookies/CSRF; no middleware disabling.
4. Use Node built-ins/CDP already established by tests/Frontend. Keep browser transport helper focused: bounded command correlation, navigation/network events, input/click/form submission and teardown. DB helper is CLI-only for seed/read assertions; no credentials in page/output.
5. Before implementation choose an exact official SweetAlert2 version compatible with the current @11 URL, retrieve official bytes and verify SHA-256. Store only version/hash metadata in tests, cache bytes per run outside repository, and fulfill only the exact existing SweetAlert2 URL from those unchanged bytes in CDP. This is asset transport, not a mocked application response or added npm dependency. Do not inject fake business JS. Known decorative font/icon CSS may be deliberately blocked with recorded URLs; this excludes external typography/icon rendering from claims. Other failed requests/console exceptions and missing business assets fail tests. If the official asset cannot be obtained/verified, fail setup; do not silently replace it.
6. Seed minimal named scenarios after safety checks. Read state via independent guarded PHP/PostgreSQL helper after browser responses. Browser assertions check real form selection/value/error feedback; persisted assertions check money, tuples, status, counts, point balance, estimates and audit.
7. E2E-05 uses a replay of the real authenticated request captured from the browser; it proves HTTP idempotency, not simultaneous HTTP race. E2E-08 may submit forbidden local requests through the authenticated browser when UI hides controls, using valid CSRF and structurally valid payloads; 500/419/403 due to inadequate test credentials is not accepted as financial-guard evidence. ORDER_DELETE is owner-only: staff delete is separately tested as an ACL denial, not financial-guard coverage; owner override remains unchanged.
8. Paid-order fixture preparation may create/settle local data through seed-only service calls. Operations under test go through browser/HTTP; fixture setup is not reported as browser lifecycle coverage. No live JWT/RLS/schema parity claim.

## Dependency graph and ordered tasks

E2E1 (safe HTTP runtime) → E2E2 (seed/orchestration) → E2E3 (browser login + first conversion) → E2E4 (delivery/replay) → E2E5 (pricing/paid guards) → E2E6 (mutation + CI) → E2E7 (docs/full verification/publication).

| Task | Vertical outcome | Likely files | Dependencies |
|---|---|---|---|
| E2E1 | HTTP bootstrap carries sessions while rejecting unsafe targets and preserving CSRF | tests/Postgres/bootstrap.php; new tests/Postgres/connection.php; tests/E2E/bootstrap.php; tests/E2E/router.php | — |
| E2E2 | Disposable fixture/server can be started, seeded and always cleaned | scripts/test-web-e2e.sh; tests/E2E/seed.php; tests/E2E/fixture.sql | E2E1 |
| E2E3 | Real login and store/store inspection persist an Order | tests/E2E/browser.mjs; tests/E2E/web-postgres.test.mjs; tests/E2E/state.php; tests/E2E/assets.json | E2E2 |
| E2E4 | Remaining home/store combinations and authenticated replay pass | tests/E2E/web-postgres.test.mjs; tests/E2E/state.php; tests/E2E/seed.php if needed | E2E3 |
| E2E5 | Real pricing controls and paid-order rejection preserve financial state | tests/E2E/web-postgres.test.mjs; tests/E2E/state.php; tests/E2E/seed.php if needed | E2E4 |
| E2E6 | Covered guard mutation fails and dedicated CI runs actual E2E | .github/workflows/verification.yml; tests/E2E/web-postgres.test.mjs if gap; tasks/verification.md | E2E5 |
| E2E7 | Commands/coverage published with original regressions intact | docs/testing/WEB_POSTGRES_E2E.md; README.md; TESTCASES.md; tasks/verification.md | E2E6 |

Acceptance criteria and checkpoints are in tasks/todo.md. Files are estimates, each task kept within five touched files; split further if bootstrap/fixture needs more work. Work remains sequential in this shared test runtime.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| Session vanishes between HTTP requests | Stable per-run key/runtime, file sessions, verify login survives navigation |
| Tests succeed with CSRF/permission accidentally disabled | Environment e2e, negative 419/auth/permission probes, no withoutMiddleware/fake actor |
| Fixture missing fields/relations queried by real pages | Diagnose SQL failure; add local fixture fields only, no production schema changes |
| CDN is unavailable or introduces nondeterminism | Exact official asset/hash and constrained transport; fail core asset retrieval; record decorative exclusions |
| Browser redirected to wrong/default page or returns 500 | Check redirect chain, page exceptions and HTTP statuses; seed least sufficient real permissions |
| HTTP method override/CSRF rejection mistaken for business guard | Use valid known payload/token and permitted actor, verify expected financial rejection and unchanged DB |
| Passwords/session/DB values in logs | Runtime-only generated credentials and masked CI logs; don't dump cookie/token or full environment |
| Cleanup breaks other workloads | Own IDs/paths only; bounded termination and failure cleanup checks |
| Browser UI appears correct but DB wrong | Independent persisted-state assertions after each action |
| Scope grows into Live/Flutter/legacy retirement | Return to approved spec; retain current contracts and tests |

## Verification checkpoints and publishing

- After E2E2: unsafe targets refuse setup; PostgreSQL and HTTP ready; sessions/CSRF work; failure cleanup verified; existing PostgreSQL runner still passes.
- After E2E4: login, all four inspection combinations and HTTP replay pass in Chromium with persisted-state checks.
- After E2E7: all eight cases, mutation detection, PHP/Chromium/PostgreSQL regressions, Blade, native build and audits pass or concrete failures are diagnosed; no weakened checks.
- Push updates to existing draft PR #9 under standing user authorization. Merge after approved plan/task execution and exact-head CI success, then sync/verify main. Approval of this plan also approves the ordered checklist; no additional business-policy decision is proposed.

# Implementation plan: page-load performance

Owner approved SPEC-page-load-performance.md and instructed implementation on 2026-10-08. Execute its two reviewed slices in order, with a verification checkpoint after each. Existing completed plans remain intact.

1. Establish a reproducible guarded HTTP/PostgreSQL profiling mode, query-count response header confined to the test bootstrap, five cold-browser samples and optional controlled asset/DB latency. Preserve existing E2E default seed/redirect and unsafe-target guards. Files: test bootstrap, seed, browser helper, performance test and existing runner. Acceptance: actual HTTP login succeeds, fixed target/runtime guards apply, baseline dashboard has 15 queries and the new <=13 budget fails before optimization. No SQL/bindings logged.
2. Goup staff operational status counts into one Eloquent aggregate in the existing controller. Test all status buckets and empty state; ensure scheduling and rendered values match. Files: staff controller and focused feature test. Acceptance: same counts, one KPI aggregate, full PG fixture dashboard <=13 queries. Compare original/changed checkout with the same profiling mode; keep eliminated query work without claiming noisy local timing as production speedup.
3. Measure deferred Bootstrap/SweetAlert2 loading on actual pages with controlled 500 ms asset delay and cold browser cache. If first paint improves consistently, retain only the measured change. Check inline consumers, flash message and actual confirmation/dropdown/password controls. Files: two layouts plus real-browser test/benchmark. Serving official local SweetAlert2 is optional and is not retained without separate attributable evidence. No new dependencies or production cache/schema changes.
4. Checkpoint: strict full PHP, Blade, frontend, PostgreSQL and browser E2E; mutation on isolated copy proves the KPI guard. Review and record kept/reverted attempts, update performance docs/README/test cases, then publish PR and merge only after exact-head CI passes. Files split into small documentation and verification commits. Existing push/merge authorization persists.

Risks: deferred dependencies can race immediate consumers (verify actual DOMContentLoaded/event handlers); query aggregation can count settled/cancelled records into operational buckets (test explicit mixed/empty fixtures); synthetic metrics do not establish real production Web Vitals (label conditions and limitations). Dependencies: measurement -> dashboard slice -> asset experiment -> full verification -> publication. No open product decision remains within the approved scope.
