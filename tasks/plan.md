# Implementation plan: order contracts

Scope map approved; user authorized autonomous implementation and push/merge. Specs saved before code.

1. order-pricing: use canonical effective-price query, preserve triples in options, replace unit badges with selects, share row behavior, lock displayed prices. Verify PHP options and executable UI regressions; commit focused fix.
2. active-assignment: shared constrained employee rule and service assertion, create/changed assignment checks, historical dropdown retention. Verify request and service rejections without financial side effects.
3. delivery-contract: transactional order locks, immutable parent/leg, noncancelled duplicate checks, lifecycle rules, schedule pair contract and friendly errors; align forms/order list. Verify direct service and HTTP cases, PostgreSQL race if local DB available.
4. booking-snapshot: fresh lock + narrowly permitted dirty inspection overlay, preserving reward markers and existing idempotency. Verify address/method changes and stale status/customer snapshots.
5. Integrated verification: full suite, frontend DOM/browser checks, formatting, Blade compilation, Vite build; review diff and mutation-check key guards. Record limitations.
6. Publish: commit focused changes, push branch, create PR, check exact-head statuses, merge only after required checks pass, fetch and verify main.

Risks: terminal delivery edits and duplicate guards intentionally tighten invalid paths; UI must not imply unsupported transitions. Historical price preview must follow save semantics. SQLite cannot establish row locking; local PostgreSQL check is separate. No production fixture writes.
