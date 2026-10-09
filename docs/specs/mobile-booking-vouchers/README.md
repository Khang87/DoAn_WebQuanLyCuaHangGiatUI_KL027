# Voucher eligibility for web Orders (item 14)

User-approved requirement: manual web Orders cannot select/apply a voucher. Booking-to-Order conversion preserves the Booking voucher subject to existing validation.

## Contract
- Remove voucher selection/code and discount preview from manual creation. Points remain available.
- Reject nonempty KhuyenMaiID/promotion_code in both FormRequest and direct OrderService creation, before writes; totals/quota/points remain unchanged on rejection.
- Prevent adding/replacing vouchers through editing an Order. Show the saved voucher read-only; revalidate it with the existing calculator on updates. Preserve valid historical vouchers, including legacy Orders without BookingID.
- Booking inspection/conversion keeps its stored voucher and existing expiry, eligibility, quota and points/fee behavior. No client BookingID can turn manual creation into conversion.
- No schema, mobile RPC, dependencies or Realtime changes. Existing historical records are not rewritten.

## Implementation and verification
1. PHPUnit RED: request and service injection on create/update with no side effects.
2. Remove selectable UI and promotion lookup queries; server policy before transactions; update preview uses only the saved voucher.
3. PHPUnit full suite, Chromium authenticated HTTP/PostgreSQL injection and normal creation, existing Booking voucher financial scenarios, frontend tests/build, mutation check and review.
4. Update README/TESTCASES and push/merge only with green CI.

Commands: php vendor/bin/phpunit --fail-on-warning --fail-on-risky; node --test tests/Frontend/*.test.mjs; npm run build; bash scripts/test-web-e2e.sh; bash scripts/test-postgres.sh.

Sources: app/Http/Requests/Admin/LuuDonHangRequest.php, app/Services/OrderService.php, app/Http/Controllers/Admin/DonHangController.php, resources/views/admin/orders/{create,edit}.blade.php and existing tests/Feature, tests/E2E.

Style: reuse Laravel Validator prohibited rules and ValidationException; no new policy abstraction needed for this small boundary. Always validate server inputs and verify financial state. Never modify production fixtures, credentials or historical financial data.
