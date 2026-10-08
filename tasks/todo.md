# Tasks

- [x] Approve scope map and record specs/contracts before code.
- [x] Canonical effective price options preserving triples; verify PHP regression.
- [x] Unit selects and readonly prices on create/edit; verify UI selection/amount/name regressions.
- [x] Active assignment checks at form/service boundaries; preserve history; verify rejected assignments.
- [x] Transactional delivery identity/lifecycle/duplicate guards; verify CRUD rollback and terminal cases.
- [x] Nullable paired return schedule and unchanged historical schedule; align forms; verify requests.
- [x] Lock Booking snapshot with allowed unsaved inspection edits; verify stale data and idempotency.
- [x] Full verification, formatting, build, Blade, diff review and relevant mutation check.
- [x] Push PR, verify required checks, merge and confirm origin/main. Completed via PR #6, merge `07e589e`; main now includes this work.


# Review verification tasks

- [x] Owner approved module map and all four specs.
- [x] Owner reviewed technical plan and task checklist and instructed implementation.

## RV1: Guarded PostgreSQL bootstrap
- [x] Implement explicit test-only connection setup without loading `.env`/cached config.
- [x] Reject remote hosts, invalid port/database and absent fixture identity before business mutations.
- [x] Verify executable refusal cases and PHP syntax; a refused target must fail nonzero.
- Files: `tests/Postgres/bootstrap.php`, `tests/Postgres/safety.php`. Dependency: none.

## RV2: Reproducible local runner
- [x] Make fixture includes portable and add a test-fixture identity marker.
- [x] Start uniquely named PostgreSQL 17 with disposable credentials and loopback binding; load SQL with ON_ERROR_STOP and asserts enabled.
- [x] Add suite entry point; bound startup waits and clean only the run's own container on success/failure.
- Verify: `bash scripts/test-postgres.sh`; setup error is nonzero; inspect cleanup and confirm unchanged `phpunit.xml`.
- Files: `scripts/test-postgres.sh`, `tests/Postgres/fixtures.sql`, `tests/Postgres/run.php`. Dependency: RV1.

## Checkpoint A
- [x] Safety and existing RPC checks pass on actual local PostgreSQL.

## RV3: Web/RPC contract comparisons
- [x] Shared table-driven price examples cover unit tuples, effective dates, ties and canonical amounts.
- [x] Verify inspection, preserved estimates, point intent/redemption, four delivery combinations and NULL pending return schedule.
- [x] Verify actor/ownership restrictions and shared reward/refund idempotency using actual services and RPCs.
- Verify: `bash scripts/test-postgres.sh`; assertions describe persisted outcomes, sequential cases roll back.
- Files: `tests/Postgres/contracts.php`, shared test support as needed. Dependency: RV2.

## RV4: Deterministic concurrency cases
- [x] Same Booking returns one Order with one redemption/confirmation audit and expected detail/delivery counts.
- [x] Same delivery leg yields one success and one rejection; two Bookings cannot overspend one customer's balance.
- [x] Observe both independent sessions blocked before release; verify losers roll back, deadlines and cleanup.
- Verify: `php tests/Postgres/run.php concurrency` against runner-created fixture, or the full runner.
- Files: `tests/Postgres/worker.php`, `tests/Postgres/concurrency.php`, process/test support as needed. Dependency: RV2.

## Checkpoint B
- [x] Web/RPC and three concurrency cases pass with observed lock overlap on PostgreSQL.

## RV5: CI gates
- [x] Workflow triggers for PRs to main and pushes to main, with read-only permissions and bounded jobs.
- [x] Run syntax/formatting, existing PHPUnit, views, Chromium, native build, audits and PostgreSQL runner.
- [x] Pin trusted action revisions; no production credentials or ignored failures.
- Verify: validate workflow syntax and execute constituent commands locally; inspect actual CI status if published.
- Files: `.github/workflows/verification.yml`, optional small validation helper. Dependencies: RV3, RV4.

## RV6: Documentation
- [x] Document prerequisites, local commands, CI check names, isolation guarantees and actual coverage limits.
- [x] Add new cases to TESTCASES.md with continuous STT while retaining current and legacy cases.
- [x] Explain Web/RPC actor contracts and branch-protection prerequisites.
- Verify: compare documentation to implemented commands and reconcile testcase numbering.
- Files: `README.md`, `TESTCASES.md`, `docs/testing/POSTGRES_VERIFICATION.md`, `docs/supabase/BUSINESS_RULES.md` if clarification needed. Dependency: RV5.

## RV7: Verification and review
- [x] Existing `php artisan test --compact`, frontend regression and view compilation pass.
- [x] PostgreSQL runner passes; one covered guard mutation fails as expected, then original source is restored and focused check passes.
- [x] Native build/audits and runtime versions are recorded accurately; review correctness/security/architecture, diff scope and resource cleanup.
- Verify: `npm run build`, `composer audit`, `npm audit --audit-level=high`, `git diff --check`, targeted mutation experiment, source restore verification.
- Files: `tasks/verification.md` and relevant test instructions. Dependency: RV6.

## Checkpoint C
- [x] Approved scope is complete; all checks pass or remaining failures are explicitly documented without weakened gates. GitHub Actions run 37644219054 passed both jobs on 512ae12, including native build and both audits.


# web-postgres-e2e checklist (approved)

Spec approved in conversation 08/10/2026; plan/checklist subsequently approved; execution evidence is in tasks/verification.md. Preserve completed RV/order-contract history.

## E2E1: Safe HTTP runtime
- [x] Share test target validation without changing existing PostgreSQL/SQLite behavior; unsafe targets refuse before application writes.
- [x] HTTP uses private stable runtime/key and file sessions; no production .env/config; environment e2e keeps CSRF/auth middleware.
- [x] Router serves only real app routes/local public assets; no test-auth endpoint.
- Verify: existing `bash scripts/test-postgres.sh` plus bootstrap safety probes and HTTP missing-CSRF rejection.
- Files: tests/Postgres/bootstrap.php, tests/Postgres/connection.php, tests/E2E/bootstrap.php, tests/E2E/router.php. Dependencies: none.

## E2E2: Disposable fixture and lifecycle
- [x] Seed active account with runtime password, actual role/permissions, catalog and named cases; never pgActor for browser login.
- [x] `bash scripts/test-web-e2e.sh` starts isolated DB/loopback HTTP and checks readiness with deadlines.
- [x] Success/failure/signal cleanup removes only owned process/container/runtime; no resource leaks.
- Verify: runner setup/safety probes, deliberate startup failure cleanup and database inspection after seed.
- Files: scripts/test-web-e2e.sh, tests/E2E/seed.php, tests/E2E/fixture.sql. Dependency: E2E1.

## Checkpoint E2E-A
- [x] Safe fixture/runtime works; existing PostgreSQL runner still passes; session/CSRF runtime behavior verified.

## E2E3: Browser login and first Order
- [x] Real Chromium login/session, logged-out/CSRF/permission probes, actual app requests; missing core assets and HTTP/JS errors fail.
- [x] Constrained real SweetAlert2 asset transport uses official version/hash; decorative exclusions recorded, no fake app response.
- [x] E2E-01 checks GET read-only and first store/store conversion with condition/server price/estimate/order persisted.
- Verify: `bash scripts/test-web-e2e.sh` focused E2E-01/login probes; capture response and independent DB assertions.
- Files: tests/E2E/browser.mjs, tests/E2E/web-postgres.test.mjs, tests/E2E/state.php, tests/E2E/assets.json. Dependency: E2E2.

## E2E4: Delivery combinations and replay
- [x] E2E-02/03/04 persist exact chặng, distinct addresses, schedule NULL only for pending return and fees.
- [x] E2E-05 authenticated HTTP replay returns one Order/redemption/detail set/chặng set/audit.
- [x] Browser validates actual successful redirects and persistence, not just service calls.
- Verify: `bash scripts/test-web-e2e.sh`; existing PostgreSQL three race tests remain required separately.
- Files: tests/E2E/web-postgres.test.mjs, tests/E2E/state.php, tests/E2E/seed.php as needed. Dependency: E2E3.

## Checkpoint E2E-B
- [x] Real login, four combinations and HTTP replay pass; no auth/CSRF bypass; resources cleaned.

## E2E5: Pricing and financial guards
- [x] E2E-06 selects both units on real pages and persists correct tuple/minimum kg; Inspection has no editable price; create/edit Order prices readonly.
- [x] E2E-07 voucher/points/service/delivery arithmetic and single redemption match expected persisted amounts.
- [x] E2E-08 valid-CSRF staff edits of paid Order/GiaoNhan reject via business guard; staff deletion is owner-only ACL denial tested separately; 500/419/insufficient permission do not count as financial-guard evidence; snapshot unchanged.
- Verify: `bash scripts/test-web-e2e.sh` with all eight scenarios and independent state assertions.
- Files: tests/E2E/web-postgres.test.mjs, tests/E2E/state.php, tests/E2E/seed.php as needed. Dependency: E2E4.

## E2E6: Mutation and CI
- [x] Guard mutation on isolated copy fails appropriate E2E case; original checkout/source restored and full suite green.
- [x] Dedicated bounded E2E job installs locked PHP/Node dependencies and uses pinned trusted actions/Chrome; existing two jobs unchanged.
- [x] Actual exact-head CI succeeds; no ignored failures or production credentials.
- Verify: mutation experiment and original rerun; inspect GitHub Actions jobs on final SHA.
- Files: .github/workflows/verification.yml, tests/E2E/web-postgres.test.mjs if missing case, tasks/verification.md. Dependency: E2E5.

## E2E7: Documentation and final verification
- [x] Document prerequisites, commands, isolation, asset treatment and fixture-vs-Live limits; record exact SHA/runtime/results.
- [x] Add eight TESTCASES rows with consecutive STT; retain every existing/legacy row; update README.
- [x] Original PHP/Chromium/PostgreSQL suites, Blade/build/audits pass; review diff and cleanup; push/merge according to standing authorization after exact-head checks.
- Verify: `php vendor/bin/phpunit --fail-on-warning --fail-on-risky`, `node --test tests/Frontend/*.test.mjs`, `bash scripts/test-postgres.sh`, `bash scripts/test-web-e2e.sh`, `php artisan view:cache`, `npm run build`, `composer audit --locked --no-interaction`, `npm audit --audit-level=high`, Pint/numbering/`git diff --check`.
- Files: docs/testing/WEB_POSTGRES_E2E.md, README.md, TESTCASES.md, tasks/verification.md. Dependency: E2E6.

## Checkpoint E2E-C
- [x] Eight scenarios and regression gates verified; coverage claims precise; draft PR ready for final review/merge.

E2E checkpoint evidence: all three jobs passed on 65a09a6 in run 37734324447; final documentation commit must also pass before authorized merge.

# Page-load performance — approved scope

- [x] Profiling: guarded test-only mode, real HTTP login, five cache-disabled samples; original dashboard 15 queries and <=13 budget RED.
- [x] Dashboard: all operational status buckets and empty fixture retain values; three KPI queries become one; exact fixture <=13 queries GREEN.
- [x] Dashboard checkpoint: comparable original/changed measurements and isolated mutation prove the budget and bucket assertions.
- [x] Assets: test delayed dependency first paint before/after, preserve script ordering and existing flash/confirmation/dropdown/login controls; revert unmeasured/noisy candidates.
- [x] Regression checkpoint: strict PHP, Blade, frontend, PG contracts/concurrency and actual browser E2E pass.
- [x] Document measurements, provenance if applicable, retained/reverted attempts and field limitations; review diff and testcase numbering.
- [ ] Push PR, wait for exact-head CI/build/audits and Vercel, merge and sync clean main.
- [x] Production-directed list slice: remove proven-unused orders index reads, preserve filters/pagination/row values and settled controls; service budget <=5, HTTP fixture <=10 queries.
- [x] Record production log evidence and limits; do not claim frontend deferral fixes 8–16 second server processing or infer actual regions solely from deployment metadata.
