# Spec: postgres-test-runner

## Objective
Reuse local RPC fixtures to verify Laravel against PostgreSQL in a disposable database. Provide a reproducible entry point without altering phpunit.xml.

## Contract
`scripts/test-postgres.sh` starts a uniquely named PostgreSQL 17 container, with an ephemeral password, binds only loopback, creates laundry_rpc_test, loads fixture and SQL assertions with ON_ERROR_STOP and ASSERT enabled, then runs `php tests/Postgres/run.php`. Trap cleanup removes only the container created by this run. Existing fixture includes become relative via psql \ir. PHP bootstrap ignores .env and cached config, accepts only explicit PG_TEST_* connection parameters, verifies loopback/database name and a fixture marker before business mutations. Workers use the same bootstrap and independent connections. Invalid targets fail before connecting.

## Commands and success criteria
- `bash scripts/test-postgres.sh`: RPC, Web contract and concurrency checks all pass; no production access, no leftover container. Requires Docker, PHP 8.4 with pdo_pgsql and Composer vendor.
- `php tests/Postgres/run.php`: run against a pre-initialized local fixture with PG_TEST_PORT/PG_TEST_PASSWORD.
- Preserve `php artisan test --compact` SQLite behavior.

## Testing strategy
Verify refused remote target and incorrect database marker; test relative SQL includes from a checkout outside /source; make setup errors exit nonzero. SQL/RPC and PHP results remain separately labeled.

## Stack, structure and style
Laravel 13 / PHP 8.4, PostgreSQL 17, existing Composer/npm lockfiles. Keep test helpers in tests/Postgres and scripts, workflow in .github/workflows, instructions in docs/testing. Use strict comparisons, named expectations and explicit failure exceptions, e.g. `if ($actual !== $expected) { throw new RuntimeException($label); }`. No new package dependency.

## Boundaries
Always fail on setup/test errors, use bounded timeouts, preserve SQLite/legacy tests and verify persisted business state. Never load production database credentials, run fixtures on Live, edit vendor, remove financial guards or add price override/redelivery. The owner approved test/CI changes and instructed implementation; deployment or repository settings changes must be reported separately.

## Open questions
No business-policy questions. Native build/network and dependency audit findings are verification results, not grounds to weaken checks.
