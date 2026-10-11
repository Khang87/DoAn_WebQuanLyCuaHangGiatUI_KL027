# Booking mobile snapshot verification — 11/10/2026

Base: `main` at `1d55de6`. The mobile Booking and related customer/item rows in Supabase are read as persisted business data. Actual inspection still reprices clothes using the current three-key price table; web fee submissions are ignored.

| Check | Result | Scope |
| --- | --- | --- |
| PHP suite | Passed: 484 tests, 2,515 assertions | Booking, rewards, payment preference, delivery and existing module regressions; isolated SQLite |
| Frontend controls | Passed: 24 tests | Real Chromium controls and existing JS regressions |
| Production build | Passed | Vite build |
| Booking browser → HTTP → PostgreSQL | Passed | Real Laravel session/CSRF/permissions; mobile customer/contact/addresses/notes/payment preference/distances/fees, read-only preview, fee tampering ignored, conversion, invoice and legs |
| Store-leg fee boundary | Passed | Mobile pickup 11,111 retained on Booking but excluded when inspection changes pickup to store; return 22,222, order/invoice 32,222 including 10,000 service cost |
| Mutation guard | Passed | Temporarily restored unconditional pickup fee in disposable fixture: test failed with 33,333 vs 22,222; correct SQL restored |
| Schema restore/parity/security | Passed | `scripts/test-schema.sh`; updated fee function definition captured from live Supabase after approved replacement |
| Supabase fee trigger | Applied and verified | `align_booking_mobile_delivery_fee_snapshot`; owner and EXECUTE ACL unchanged; no order data backfill |
| Full PostgreSQL contracts | Failed: existing settlement-point boundary | `private.record_legacy_order_status_change` awards points on `Đã giao` without checking full collection first; assertion expected 0 award markers, observed 1 |
| Full browser suite | Failed at existing E2E-08 | Messages (2 tests) and Booking/legacy/price/voucher flows E2E-01–07 passed; delivery write refusal returned HTTP 422 while the older financial-guard test expects redirected HTTP 200. Later remaining/avatar suites were not reached |

## Existing PostgreSQL issue

The live definition was read separately and confirms the same problem as the disposable regression: the reward block in `private.record_legacy_order_status_change` runs before checking successful payment totals. This routine was not modified as part of the delivery-fee change. The request authorized updating `private.copy_booking_delivery_fee_to_order`; it does not justify silently changing a second production financial/reward function.

No passing or pending case was fabricated. The entire PHP suite passes after making older fixtures match current columns, active employee requirements, retained cancellation history and the existing full-payment rule. PostgreSQL contracts now explicitly reject a partial web collection instead of expecting it to succeed.

## Deployment

Apply the reviewed SQL before deploying the new Laravel conversion. The fee function has already been replaced on the connected Supabase project after explicit user approval. It does not alter existing order totals, rows, RLS policies or grants. New conversion persists inspected methods inside the same transaction before inserting the order, so both PostgreSQL and Laravel exclude store legs while preserving stored mobile fees.

The schema capture refreshed only the changed fee routine. The broader snapshot timestamp is retained; this is not a new complete Supabase schema export.

Supabase security advisors before/after reported identical existing findings. The trigger replacement introduced no new finding: [RLS without policies](https://supabase.com/docs/guides/database/database-linter?lint=0008_rls_enabled_no_policy) and existing function/credential advisories remain outside this change.
