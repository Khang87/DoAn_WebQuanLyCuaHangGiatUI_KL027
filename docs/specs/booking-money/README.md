# Booking money and store address fields

Approved scope: show the Booking promotion and full estimated payment on web, preserve the inspected-order calculator, and disable receive/return addresses for store fulfillment.

## Contract

- Booking detail shows the stored service-line estimate, linked voucher/code, effective promotion discount, requested reward-point discount, both delivery fees and estimated payable total. No-detail Bookings show an explicit unavailable estimate instead of a guessed zero total.
- Stored Booking estimate uses ChiTietBooking.ThanhTien and the Booking's requested points. Inspection continues to reprice actual items with the current three-key price and revalidate the voucher. Do not confuse estimated and confirmed order totals.
- Reuse OrderService.calculateAmounts: discount promotion first, points second, delivery fees last. Reading a summary must not mutate Booking, order, points or voucher quota.
- Show a clear warning when the voucher is invalid; never silently display a discount that the calculator rejects.
- Store fulfillment disables/grays the relevant address independently for each direction; home fulfillment enables/requires it. Keep an unsent draft address when toggling, but normalize the stored address to NULL for store fulfillment, even for crafted requests.
- No changes to schema, Supabase Auth/Realtime or manual-order voucher policy in this focused slice. Those are separate proposed tasks.

## Files and checks

Laravel models/services/controllers under app; Blade detail component and booking/delivery views; existing delivery-schedule.js. Tests: PHPUnit financial regression and normalization; Chromium/session/PostgreSQL browser scenarios. Commands: php vendor/bin/phpunit --fail-on-warning --fail-on-risky; node --test tests/Frontend/*.test.mjs; npm run build; bash scripts/test-web-e2e.sh. Use PHP 8.4 and the isolated Postgres fixture; never write business fixtures to production.

## Build order

1. Failing financial-summary and address regression tests.
2. Read-only summary service and detail presentation.
3. Address UI and server normalization.
4. Regression/CI, README and TESTCASES updates, independent review, push/merge.
