# TinNhan Supabase Realtime verification

Broadcast signals invalidate the open order/support conversation; authorized Laravel HTTP reads supply content. Public topics are opaque, expire absolutely and carry only empty payloads. Mobile RPC, Supabase Auth and TinNhan RLS/grants are unchanged.

- RED: four new endpoint tests failed before implementation (route/configuration missing).
- Strict PHP: 421 tests / 2,213 assertions passed.
- Frontend: 20 tests passed; Vite production build passed. Subscription tests cover catch-up, invalidation coalescing, renewal, obsolete callbacks, retry/pause, denied configuration and server-relative lifetime.
- PostgreSQL: 69 existing contract assertions, three races / 21 assertions, new registry service contracts and SQL trigger assertions passed. Transport doubles include generic failure and query_canceled (57014); both preserve business writes.
- Browser runner: 34 Node tests passed across message, financial/UI and avatar suites. New test uses real Laravel session/CSRF, a real private registry and TinNhan trigger in disposable PostgreSQL, substituting only the external SDK transport. The chat updates within five seconds of the signal, preserves draft/document and renders message markup as text.
- Mutation: inverting the scope comparison in a disposable container made the exact-conversation SQL assertion fail; real source remained unchanged.
- Review corrected browser clock-skew renewal loops and uncaught Broadcast cancellations. The CDP harness ignores expired interception IDs; independent runtime/network errors still fail verification.
- Live Supabase additive migration `add_web_message_realtime_invalidation` applied. Catalog verified registry RLS, denied anon/authenticated registry reads and trigger execution, enabled TinNhan trigger, retained TinNhan RLS/publication. Vercel production already has project URL and anon-key variable names; values were not decrypted.

## Limit

A direct live WebSocket probe could not connect from the managed environment, whose enforced network allowlist excludes the Supabase project. No production business messages or customer fixtures were inserted. Production mobile-to-web WebSocket delivery is recorded Blocked in TESTCASES, not inferred from the simulated transport. Polling remains a fallback.
