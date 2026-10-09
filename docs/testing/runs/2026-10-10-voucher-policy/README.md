# Web voucher policy — item 14, 10/10/2026 (GMT+7)

Manual web creation has no voucher selector/code input or voucher discount preview. Both FormRequest and OrderService reject submitted nonempty voucher fields before financial writes. Editing cannot add/replace a voucher; the saved voucher remains read-only and is revalidated when saving. Existing Booking conversion and historical valid vouchers remain supported; points redemption is unchanged.

## Verification

- RED: three new policy tests failed on baseline: FormRequest, direct service create and update accepted submitted vouchers.
- Strict PHP: 417 tests / 2,193 assertions pass, no skipped tests. Existing promotion/points/fee and expiry reservation tests pass. Historical voucher update checks retained ID, discount 9,900 and capped 100 redeemed points.
- Frontend: 11 tests pass with Chromium process access; native Vite production build passes.
- Mutation in a Docker copy: change create KhuyenMaiID guard from prohibited to nullable; the injection test fails because the attempted creation is accepted. Real source remains unchanged.
- Full browser runner passes: 33 Node tests across chat, nine Booking/business subtests, 20 UI/HTTP variants and avatar (including parent tests). New HTTP create/edit voucher injection checks return 422 with unchanged persisted state; inactive saved voucher preview/save shows 15,000 payable and zero discount.
- Review found invalid saved vouchers being included in edit preview. Fixed using the canonical rejectionReasonForCustomer and warning, retaining read-only code. The browser regression observes zero discount and 15,000 payable for an inactive saved voucher. Blade render structure restored and DTO encoded through a variable.

## Limits

Disposable PostgreSQL and real Laravel sessions/CSRF are used. No production business data, voucher reservations, schema, Supabase Auth, mobile RPC or Realtime configuration are modified. The edit preview remains an estimate; saving revalidates voucher conditions and computes financial state on the server.
