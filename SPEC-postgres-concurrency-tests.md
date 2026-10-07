# Spec: postgres-concurrency-tests

## Objective
Prove lock behavior using independently bootstrapped PHP processes and PostgreSQL sessions.

## Contract and success criteria
Seed committed fixtures. Controller session holds a specific row lock; workers signal readiness and execute production services. Observe both worker sessions blocked in pg_stat_activity/pg_blocking_pids before releasing the lock, making overlap evidence explicit instead of relying on sleep. Bound startup/blocking/completion waits and terminate workers on failure.
- Same Booking: both requests return one Order ID; one point deduction, expected detail/delivery counts, one Booking confirmation audit.
- Same Order/changing delivery: exactly one successful noncancelled leg and one validation rejection; cancelled leg replacement remains covered separately.
- Different Bookings/same customer balance: both reach the balance lock; one redemption succeeds, one rolls back. Balance stays nonnegative; no orphan Order/detail/delivery/audit for the rejected Booking.

## Commands and testing strategy
`bash scripts/test-postgres.sh` runs these scenarios after sequential checks; `php tests/Postgres/run.php concurrency` focuses these checks. Persisted counts and balances are primary expectations. No application lock or financial-rule changes unless the test reproduces a concrete regression.

## Stack, structure and style
Laravel 13 / PHP 8.4, PostgreSQL 17, existing Composer/npm lockfiles. Keep test helpers in tests/Postgres and scripts, workflow in .github/workflows, instructions in docs/testing. Use strict comparisons, named expectations and explicit failure exceptions, e.g. `if ($actual !== $expected) { throw new RuntimeException($label); }`. No new package dependency.

## Boundaries
Always fail on setup/test errors, use bounded timeouts, preserve SQLite/legacy tests and verify persisted business state. Never load production database credentials, run fixtures on Live, edit vendor, remove financial guards or add price override/redelivery. The owner approved test/CI changes and instructed implementation; deployment or repository settings changes must be reported separately.

## Open questions
No business-policy questions. Native build/network and dependency audit findings are verification results, not grounds to weaken checks.
