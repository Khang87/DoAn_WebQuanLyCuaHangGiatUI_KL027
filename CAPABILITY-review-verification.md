# Capability map: review verification

## Objective and current evidence

Implement the review priorities selected by the project owner: PostgreSQL concurrency verification, Web/RPC business-contract verification, and CI. Base revision: `0f030d1`.

The application already has SQLite PHPUnit tests, a real Chromium pricing-controls test, and PostgreSQL RPC fixtures and sequential assertions. The missing evidence is Laravel services running against PostgreSQL, concurrent requests, and automated execution of these checks on pull requests and main.

## Modules

| Module id | Responsibility and acceptance criteria | Depends on |
|---|---|---|
| postgres-test-runner | Run existing SQL fixtures and RPC assertions in a disposable local PostgreSQL database; bootstrap Laravel against that same database without loading production configuration. Reject unsafe targets and fail on setup or assertion errors. Preserve the SQLite PHPUnit configuration. | — |
| web-rpc-contract-tests | Verify both implementations against documented pricing, inspection, points and lifecycle contracts using shared test inputs and expected outcomes. Explicitly test intended actor differences and RPC ownership restrictions. Keep Laravel and PostgreSQL result counts separate. | postgres-test-runner |
| postgres-concurrency-tests | Run independent Laravel processes with separate connections and a controlled overlap. Prove one Order/one redemption/one confirmation audit for a repeated Booking; one noncancelled delivery per leg; and no overspending when different Bookings redeem the same customer's points. Assert rejected operations roll back and verify final persisted state. | postgres-test-runner |
| verification-ci | Run applicable PHP formatting/syntax checks, the existing PHPUnit suite, Blade compilation, Chromium regression, Vite build, PostgreSQL tests and dependency audits on PRs to main and pushes to main. Use only isolated test resources. Document local execution, CI results and branch-protection prerequisites. | web-rpc-contract-tests, postgres-concurrency-tests |

Build order: postgres-test-runner → web-rpc-contract-tests → postgres-concurrency-tests → verification-ci.

## Boundaries

- Reuse `tests/Postgres/fixtures.sql`, `tests/Postgres/business_rules.sql`, and `docs/supabase/BUSINESS_RULES.md`; extend the local test fixture only when application tests need missing fixture fields or relations.
- Never run fixtures, destructive setup or test mutations against Supabase Live. No production credentials in the runner or CI. Never bypass production authentication to exercise a live RPC.
- Preserve `phpunit.xml` forcing SQLite, existing legacy Pending tests, existing route signatures and financial rules. No price-override feature or redelivery workflow is included.
- A deterministic regression reproduced by these new checks must be diagnosed before proposing a targeted fix; update the relevant specification before changing a business contract.
- Native build failures and security audit findings must be reported accurately. Do not replace the build with a modified configuration or suppress failed checks to claim success.
- Adding workflow files does not itself configure GitHub required checks. Repository settings and actual workflow runs must be distinguished from local validation.

## Verification and documentation

- Existing baseline commands: `php artisan test --compact`, `php artisan view:cache`, `node --test tests/Frontend/*.test.mjs`, `npm run build`.
- New PostgreSQL command and its runtime requirements will be specified after the module map is approved; it must include RPC checks, Web checks and process concurrency checks with bounded timeouts and cleanup.
- Exercise at least one mutation of a newly covered guard to demonstrate that the new test fails, then restore the application source and rerun the focused check.
- Record exactly which commit, runtime and database were tested. Do not equate a Vercel deployment status with PHP/PostgreSQL verification.
- Update README and testing documentation with commands, coverage and remaining limitations. Keep legacy cases in TESTCASES.md until removal prerequisites are met.

## Review status

Scope family authorized by the owner. Module boundaries and build order approved by the owner on 07/10/2026 under `.codex/Skills/skills/spec-driven-development/SKILL.md`, Phase 0. The owner instructed implementation of the approved scope.
