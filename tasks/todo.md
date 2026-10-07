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
- [ ] Approved scope is complete; all checks pass or remaining failures are explicitly documented without weakened gates.
