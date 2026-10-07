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
