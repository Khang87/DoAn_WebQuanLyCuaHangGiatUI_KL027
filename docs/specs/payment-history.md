# Separate payment history

Payment records currently live in the Thanh toán module (`ThanhToanController`, `PaymentService`, `ThanhToan`). Invoice change history is a separate concept (`LichSuThayDoiHoaDon`). Add a sidebar destination named Lịch sử thanh toán for existing order-payment records, without creating another ledger or changing payment writes.

GET /payment-history lists all statuses, newest first, with continuous row numbers, order/customer/transaction search, method/status/date filters and bounded pagination. GET /payment-history/{payment} presents read-only transaction details. Both require the existing payments.view permission and existing staff/customer middleware. History views expose no create/edit/delete controls. Original payment management and invoice audit history remain available.

Reuse PaymentService for data, add date bounds and stable sort tie-breaking there, and use existing Bootstrap/detail components. Empty lists, invalid filter ranges, missing records, lost permissions and optional relationships must be handled. Dates follow the current payment timestamp convention (application local time); end-date filtering includes the whole day using an exclusive next-day boundary.

This is a view of stored payment records, not a new immutable event log. Financial finality protections and existing deletion rules remain authoritative. No DB migration or production data mutation is needed.

Verify permissions and read-only details, all statuses including refunds, combined filters, date boundaries, deterministic pagination/row numbering and original payment management routes. Browser checks use an isolated fixture dedicated to payment history so unrelated delivery setup does not block them.
