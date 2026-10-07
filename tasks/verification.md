# Verification: order contracts

Baseline: 573a5102. Scope and autonomous implementation/push/merge authorized in conversation.

- PHP 8.4.26 / Laravel 13.34.0: **328 tests, 1,715 assertions, no failures/skips**, using phpunit.xml SQLite :memory: in a Docker container with network disabled.
- Chromium 151.0.7922.173 through CDP: real DOM regression passes for multi-unit selection, per-unit price, readonly/tampered price restoration, quantity/weight switching, minimum kg, and missing tuple. No npm dependency added. This is a focused controls test, not a production browser-to-database test.
- Mutation verification: reversing allowed-order-state guard fails the return execution test; dropping the selected unit from price lookup fails the browser regression. Both files restored before green verification.
- Pint on changed PHP files and Blade compilation passed. Container has no Git, so explicit file lists replace --dirty.
- Original npm run build cannot download fonts.bunny.net in this environment. Vite build with only the font download removed in a temporary configuration outside the repository passes (48 modules). Project vite.config.js unchanged. Remote deployment checks are inspected before merge.
- No database migration, application dependency or production fixture write. No live Supabase or production end-to-end verification. PostgreSQL race behavior is not proven by the SQLite tests; parent row locks are reviewed against Laravel 13 pessimistic-locking documentation.

## Review decisions

- Keep completeReceivingInspection for historical Pending records.
- Preserve full service/garment/unit tuple in UI and canonical effective-price options.
- Distinguish delivery planning from execution. Pending returns can remain unscheduled before washing completes.
- Block conflicting noncancelled legs, cancelled/paid parent mutations, terminal reopening and completed-slip deletion.
- Preserve unrelated fields and original timestamp seconds during delivery edits.
- Retain inactive staff only for unchanged historical assignments. New assignments must be active.
- Refresh locked Booking status/customer/reward markers; overlay only dirty inspection fields. Preserve unsaved edited receive/return addresses and caller reward-marker cleanup.

Behavior-preserving cleanup separately removes obsolete unit-label/refresh-price scaffolding and controller pass-through wrappers; targeted Booking/order tests and Blade compilation verify it.


# Verification: PostgreSQL and CI review priorities

Date: 07/10/2026. Application base: `0f030d1`. Work branch: `codex/postgres-verification-ci-20261007`. Specs, scope map and plan approved by owner. Application PHP, routes, financial policy and SQLite config unchanged.

- Existing PHPUnit suite on PHP 8.4.26 / Laravel 13.34.0: **328 tests, 1,715 assertions**, no warnings/risky failures with strict flags; `.env` excluded, APP_URL fixed to localhost:8000, ephemeral key and proper cache/data directories. Blade compile passed.
- PostgreSQL 17 runner: **4 unsafe-target refusals**, existing RPC SQL assertions, **66 Web/RPC assertions** (including wrong fixture marker), **3 real concurrent scenarios / 21 assertions**. Both worker sessions observed waiting on locks before release. Success and failed/mutated runs removed their owned containers.
- Guard mutation on an isolated source copy changed DeliveryService's noncancelled predicate to cancelled. Concurrent creation returned two successes; suite correctly failed with exit 1. Original application source was never modified; full original PostgreSQL runner passed afterwards.
- Frontend: original Chromium 151 controls test passed with required sandbox permissions; new shell-quote security test failed on 1.9.0 and passed on 1.11.0. Four line terminators rejected; ordinary quoted arguments round-trip.
- npm audit: **0 vulnerabilities** after the narrowly scoped override. Only package version changed in lock graph is shell-quote 1.9.0 → 1.11.0. npm also normalized lockfile name/platform libc metadata; no manual lockfile edit. Changelog 1.11.0 and official advisory reviewed.
- Pint passed for all 8 new PostgreSQL PHP files; shell syntax, workflow YAML/events/permissions/SHA pins and `git diff --check` passed. TESTCASES has 316 consecutive rows, including 26 additions and all 29 legacy rows retained.
- Native Vite build failed fetching fonts.bunny.net (EAI_AGAIN/network policy); project Vite config untouched. Composer audit failed with HTTP proxy 403 for packagist.org advisory API. These are recorded as blocked checks, not passes; no policy bypass or ignored gate.
- CI file contains strict PHP/PostgreSQL and frontend/build/audit jobs. GitHub workflow execution passed on publication; required-check branch protection is separate repository configuration and has not been changed. Local fixture tests do not establish production ACL/RLS, JWT integration, browser E2E, pricing/payment race coverage or Supabase Live correctness.

## Published CI evidence

- GitHub Actions [run 37644219054](https://github.com/Khang87/DoAn_WebQuanLyCuaHangGiatUI_KL027/actions/runs/37644219054), exact head `512ae1240722db28961bb0ef15173ab3e2cf70bc`: **PHP and PostgreSQL** and **Frontend and build** both succeeded. Every check step passed, including native production build, Chromium, PostgreSQL and both dependency audits. This resolves the local network limitations without weakening checks.
- PR: [#8](https://github.com/Khang87/DoAn_WebQuanLyCuaHangGiatUI_KL027/pull/8). The final documentation commit must also have successful checks before merge.
