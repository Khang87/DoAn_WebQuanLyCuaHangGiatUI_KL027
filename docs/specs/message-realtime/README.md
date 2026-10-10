# TinNhan Web Realtime

User-authorized scope: replace the open web conversation's polling-only behavior with Supabase Realtime, keeping Laravel session authentication, permissions and message writes unchanged.

## Contract

- A protected, CSRF-checked POST issues an opaque topic for an existing order or active support customer after `messages.view` authorization.
- Database INSERT/UPDATE/DELETE broadcasts only `{}` / `changed` to registered topics for the affected conversation. The browser refetches the existing authorized Laravel endpoint; it never renders Broadcast content.
- Public Broadcast is a timing-only capability, not message authorization. Topics expire absolutely within about ten minutes and rotate. The response includes server-relative expires_in; the browser uses a monotonic timer, avoiding clock-skew renewal loops. A copied topic can observe timing until expiry; it grants no message access. No account/order IDs, content or names travel over Broadcast.
- Channel registry is private, indexed, RLS enabled without client policies/grants. Existing TinNhan grants, RLS and mobile RPC remain unchanged. The browser receives only project URL and a publishable/anon key, never a service-role key.
- Subscription/reconnection triggers a catch-up read. Invalidations are coalesced with a minimum one-second gap. Hidden/offline/disposed pages disconnect. Loss of Laravel authorization stops all updates.
- Polling remains a fallback at 15 seconds; while connected, a 60-second safety read handles missed signals and permission revocation. Realtime errors cannot roll back business message writes.

## Implementation and verification order

1. Protected configuration endpoint + registry service; request tests for session, permissions, scope validation and safe public configuration.
2. Additive SQL registry/trigger; disposable PostgreSQL assertions for exact scope, empty payload, expiry, mutation/delete and broadcast failure isolation.
3. Web subscription lifecycle; deterministic tests for signal refresh, throttle, renewal, reconnect and disposal; existing browser flows and build.
4. Apply reviewed additive SQL to Supabase, verify grants and a real WebSocket signal without inserting production messages. Update schema snapshot, README and test cases. Review, CI, push and merge.

No open product questions. Production activation additionally requires a configured Supabase URL and publishable/anon key and the additive SQL migration.
