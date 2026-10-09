# Booking promotion summary and store address fields — 10/10/2026

Scope: Booking detail reads mobile-linked voucher and estimated payment; store receive/return addresses are disabled and normalized. No production schema/Auth writes, no manual-order voucher policy change, no Realtime implementation in this slice.

## Findings and behavior

Booking already carried KhuyenMaiID and the inspection/conversion calculator applied it. The detail page displayed only service-line prices and lacked voucher/discount/fee/total information. Add a read-only saved-estimate summary using the same calculator and existing Booking data. The displayed stored estimate remains distinct from repriced actual items at inspection. Blank service lines do not invent a zero payment; invalid promotions display a warning.

Address fields previously remained editable, and crafted inspection requests could preserve stale store addresses. Disable each store address, re-enable/require each home address, restore state after native reset and normalize both stored addresses to NULL for store methods. Draft address text survives a temporary toggle but is omitted from the submitted store fields.

## Verification

- Before implementation: three missing saved-estimate method errors and one failure retaining a store pickup address. After implementation: focused regression passed.
- Strict PHPUnit: **416 tests / 2,158 assertions**, no failure/skip. Includes HTTP detail with/without items, financial snapshots, expired voucher, points selection and store normalization.
- Example: stored items 10,000, voucher 9,900, 40 selected points, delivery fees 3,000 => payable **3,060**. Changing the inspection price to 20,000 yields 13,100 with the same voucher/fees and no points; reading either estimate leaves balances/quota/order rows unchanged.
- Native Vite production build passed.
- PostgreSQL business rules, **69 Web/RPC assertions** and **3 races / 21 assertions** passed. CI exposed a pre-existing midnight-only harness bug: config changed to Vietnam time after Laravel had initialized PHP to UTC, while the SQL session used Vietnam time. Reproduced locally (expected price total 7,500, observed 299,997); synchronized PHP and connection timezone in the guarded test bootstraps and added three clock/boundary assertions. Production runtime/RPC configuration was not changed.
- Chromium / actual Laravel sessions / disposable PostgreSQL: **31 Node tests** including parents passed. Saved Booking voucher E2E-VOUCHER displayed; 10,000 goods minus 10,000 voucher plus 30,000 fees => 30,000. Address disabled/required/FormData behavior, independent directions and home/store/reset covered. Existing conversion/financial/legacy/avatar flows passed.
- Mutation in an isolated Docker copy: removing linked promotion resolution makes the new voucher regression fail (0 versus 9,900 discount); source remains intact.
- Independent review flagged reset synchronization; fixed and covered by browser regression. No remaining Required/Critical findings.

TESTCASES now 356 cases, 355 Passed / 1 Blocked. Existing production-upload case 237 remains blocked by execution network policy. Fixture outcomes are not manual production confirmation.
