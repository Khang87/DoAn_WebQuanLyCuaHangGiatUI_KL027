# Implementation plan: order contracts

Scope map approved; user authorized autonomous implementation and push/merge. Specs saved before code.

1. order-pricing: use canonical effective-price query, preserve triples in options, replace unit badges with selects, share row behavior, lock displayed prices. Verify PHP options and executable UI regressions; commit focused fix.
2. active-assignment: shared constrained employee rule and service assertion, create/changed assignment checks, historical dropdown retention. Verify request and service rejections without financial side effects.
3. delivery-contract: transactional order locks, immutable parent/leg, noncancelled duplicate checks, lifecycle rules, schedule pair contract and friendly errors; align forms/order list. Verify direct service and HTTP cases, PostgreSQL race if local DB available.
4. booking-snapshot: fresh lock + narrowly permitted dirty inspection overlay, preserving reward markers and existing idempotency. Verify address/method changes and stale status/customer snapshots.
5. Integrated verification: full suite, frontend DOM/browser checks, formatting, Blade compilation, Vite build; review diff and mutation-check key guards. Record limitations.
6. Publish: commit focused changes, push branch, create PR, check exact-head statuses, merge only after required checks pass, fetch and verify main.

Risks: terminal delivery edits and duplicate guards intentionally tighten invalid paths; UI must not imply unsupported transitions. Historical price preview must follow save semantics. SQLite cannot establish row locking; local PostgreSQL check is separate. No production fixture writes.


# Implementation plan: review verification

## Approval and baseline

The owner approved `CAPABILITY-review-verification.md` and all four module specs, then instructed continuation. This section extends the completed order-contract initiative rather than replacing its history. Base application revision is `0f030d1`; previous order-contract changes are on main via PR #6 (`07e589e`), and documentation via PR #7 (`fdd869e`).

## Architecture decisions

- Reuse PostgreSQL 17 fixtures and the existing RPC business assertions. Convert psql includes to file-relative includes so execution does not depend on `/source`. Add a local fixture identity marker.
- Keep SQLite PHPUnit isolated and unchanged. The new PostgreSQL runner bootstraps the real Laravel application without `.env` or cached production configuration and accepts only explicit local test settings.
- Use committed fixture data for concurrency scenarios. Spawn separate PHP workers/connections, hold a coordinator row lock, and observe both workers blocked before releasing it. This proves overlap without timing-only sleep assumptions.
- Compare Web and RPC outcomes against common expected contract values, including intended actor differences. Do not claim service tests are browser E2E.
- No production schema, API, price policy, legacy retirement, or delivery workflow changes. A reproduced regression gets a targeted diagnosis and spec update before a production fix.
- Workflow uses isolated disposable credentials/resources and read-only GitHub permissions. Actual workflow execution and required-check repository settings are verified separately from YAML syntax.

## Ordered task index

Acceptance criteria and executable checks live in the appended `tasks/todo.md` checklist.

| Task | Module | Deliverable | Dependency | Scope |
|---|---|---|---|---|
| RV1 | postgres-test-runner | Guarded PHP bootstrap and executable unsafe-target checks | — | 2 files |
| RV2 | postgres-test-runner | Docker runner, portable SQL fixtures, suite entry point | RV1 | 3–4 files |
| RV3 | web-rpc-contract-tests | Shared catalog/input cases and real service/RPC comparisons | RV2 | 2–3 files |
| RV4 | postgres-concurrency-tests | Workers, controlled blocking harness and three persisted-state scenarios | RV2 | 3–4 files |
| RV5 | verification-ci | GitHub Actions workflow running repository-native gates | RV3, RV4 | 1–2 files |
| RV6 | verification-ci | README, test coverage and execution instructions | RV5 | 3–4 files |
| RV7 | all modules | Full verification, one guard mutation, diff review and evidence log | RV6 | 1–2 documentation files |

Build order: RV1 → RV2 → RV3 → RV4 → RV5 → RV6 → RV7.

## Checkpoints

1. After RV2: invalid targets fail before access; valid disposable fixture runs existing RPC assertions; SQLite configuration remains unchanged.
2. After RV4: all Web/RPC and concurrency cases pass on actual PostgreSQL; worker timeouts and cleanup are verified.
3. After RV7: existing suite, Chromium check and view compilation pass; native build/audits are recorded honestly; a guard mutation is caught; application source is restored and working tree reviewed.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| Loading production credentials or destructive test setup | Ignore `.env` and cached config, require explicit loopback parameters, fixed disposable database and fixture identity marker before business writes |
| Fixture differs from application/Live shape | Reuse current RPC fixture, minimally add only missing local fixture fields; document that it is not a complete production schema clone |
| False concurrency confidence from sequential scheduling | Independent workers, distinct PostgreSQL sessions, coordinator locks and observed blocking before release |
| Hung worker or leaked container | Bounded deadlines, checked subprocess exit codes, per-run owned resource names and finally/trap cleanup |
| PHP host lacks pdo_pgsql | Verify runtime and prepare isolated PHP 8.4 runtime; document native prerequisites; do not skip PostgreSQL tests |
| Font-download or dependency-audit failure | Preserve native checks and report exact failures; investigate root cause rather than bypass the failing gate |
| Existing repository-wide formatting debt | Format new/touched PHP files; keep unrelated reformatting outside this change |
| Workflow passes but merge remains unprotected | Document required job names and distinguish actual checks from repository branch-protection settings |

## Open questions

No unresolved business requirements. Technical failures discovered while implementing are evidence to diagnose. The owner approved the plan and checklist and instructed implementation; local validation is recorded in tasks/verification.md.
