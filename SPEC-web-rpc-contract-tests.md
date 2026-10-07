# Spec: web-rpc-contract-tests

## Objective
Demonstrate the Web/RPC contract with actual Laravel services and PostgreSQL RPCs on the same fixture.

## Contract and success criteria
- Use shared catalog and table-driven kg/piece examples to compare RPC Booking estimates and Laravel inspected Order amounts; include effective-price ties, inactive/future/expired prices, both date boundaries and multiple units.
- RPC submits points intent without redemption; RPC cannot create an Order from uninspected estimates. Laravel inspection stores actual condition/measurement and canonical server price while keeping Booking estimates. Repeated RPC confirmation after Web inspection returns the same Order.
- Four receive/return combinations create only home delivery legs; pending return time remains NULL.
- Exercise voucher/points/delivery-fee arithmetic and staff/customer lifecycle permissions with actual services/RPC. Reject other customers' access, inactive assignments, paid/cancelled delivery mutations; verify award/refund markers do not duplicate across Web/RPC.
- Keep legacy snapshot-price contract unchanged. The existing SQLite legacy suite remains required.

## Commands and testing strategy
`bash scripts/test-postgres.sh` executes shared-input comparisons and the existing SQL assertions. Expectations are state-based and rollback the sequential Web test transaction. A mutation of one covered production guard must make the relevant check fail. No full browser E2E claim: existing Chromium control tests verify DOM only.

## Stack, structure and style
Laravel 13 / PHP 8.4, PostgreSQL 17, existing Composer/npm lockfiles. Keep test helpers in tests/Postgres and scripts, workflow in .github/workflows, instructions in docs/testing. Use strict comparisons, named expectations and explicit failure exceptions, e.g. `if ($actual !== $expected) { throw new RuntimeException($label); }`. No new package dependency.

## Boundaries
Always fail on setup/test errors, use bounded timeouts, preserve SQLite/legacy tests and verify persisted business state. Never load production database credentials, run fixtures on Live, edit vendor, remove financial guards or add price override/redelivery. The owner approved test/CI changes and instructed implementation; deployment or repository settings changes must be reported separately.

## Open questions
No business-policy questions. Native build/network and dependency audit findings are verification results, not grounds to weaken checks.
