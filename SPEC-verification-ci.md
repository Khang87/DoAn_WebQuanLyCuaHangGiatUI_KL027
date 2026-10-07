# Spec: verification-ci

## Objective
Automate the repository's real checks on pull requests targeting main and pushes to main.

## Contract and success criteria
Workflow jobs run with read-only repository permissions, bounded timeouts and cancellation of obsolete runs. PHP 8.4 with needed extensions, Node 22 and Chromium execute the existing suite; PostgreSQL 17 runner executes SQL, Web and concurrency checks. Run PHP syntax/changed-file formatting, Blade compile, `npm run build`, and high/critical native dependency audits. No blanket skip/continue-on-error. No production secrets; runner creates disposable credentials. Pin trusted actions to immutable commits. Record that required status checks need repository branch-protection configuration; workflow creation alone does not enforce merging.

## Commands and testing strategy
`composer install --no-interaction --prefer-dist`, `php artisan test --compact`, `php artisan view:cache`, `node --test tests/Frontend/*.test.mjs`, `npm ci --ignore-scripts`, `npm run build`, `composer audit`, `npm audit --audit-level=high`, `bash scripts/test-postgres.sh`. Syntax-validate YAML and exercise job commands locally where available. Report failed external font fetches and vulnerabilities honestly. PHP has no TypeScript gate. README and docs/testing explain prerequisites, actual coverage and limits.

## Stack, structure and style
Laravel 13 / PHP 8.4, PostgreSQL 17, existing Composer/npm lockfiles. Keep test helpers in tests/Postgres and scripts, workflow in .github/workflows, instructions in docs/testing. Use strict comparisons, named expectations and explicit failure exceptions, e.g. `if ($actual !== $expected) { throw new RuntimeException($label); }`. No new package dependency.

## Boundaries
Always fail on setup/test errors, use bounded timeouts, preserve SQLite/legacy tests and verify persisted business state. Never load production database credentials, run fixtures on Live, edit vendor, remove financial guards or add price override/redelivery. The owner approved test/CI changes and instructed implementation; deployment or repository settings changes must be reported separately.

## Open questions
No business-policy questions. Native build/network and dependency audit findings are verification results, not grounds to weaken checks.

## Verification findings and targeted remediation
The npm audit gate reproduced GHSA-pqg4-j6r4-53mv in concurrently 10.0.5's exact shell-quote 1.9.0 dependency. Pin only that transitive dependency to patched 1.11.0 through a scoped npm override, review its changelog and generated lockfile, and retain a regression that fails before the patch. No unrelated package upgrade. CI supplies APP_URL=http://localhost:8000, creates ignored storage directories, and invokes PHPUnit with fail-on-warning/fail-on-risky to match the clean checkout environment without using a production .env.
