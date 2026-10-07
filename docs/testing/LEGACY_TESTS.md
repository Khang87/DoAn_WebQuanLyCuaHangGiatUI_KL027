# Xử lý 192 skipped tests

Danh mục được lấy từ `phpunit --list-tests-xml` trước thay đổi: 192 case, 23 file. Mã lịch sử còn trong Git tại commit `754d115f01e5b50ec21defea53fa2e72bd89db81`; không cần giữ các test bị vô hiệu hóa trong bộ test chạy hiện hành.

Phân loại: **retired-outdated-schema: 141**, **restored: 6**, **retired-rule: 18**, **retired-placeholder: 25**, **ported: 2**.

`legacy-test-inventory.json` ghi từng case, lý do và các test hiện hành liên quan. Liên kết thay thế theo nhóm nghiệp vụ, **không có nghĩa cả 192 case đã được chuyển thành 192 test chạy hoặc có coverage tương đương**. Các hành vi cũ đã nghỉ dùng không tính vào mẫu số kiểm thử hiện hành.

| File lịch sử | Case | Test hiện hành liên quan |
|---|---:|---|
| AccountControllerTest | 5 | UserAccountAuditTest, InternalOtpTest |
| ActionButtonIconStyleTest | 6 | ActionButtonIconStyleTest |
| AuthTest | 7 | AuthFlowTest, AuthorizationBoundaryTest |
| BookingAutoOrderCreationTest | 11 | BookingOrderConversionTest, RewardPointTest |
| CustomerControllerTest | 5 | CustomerUpdateTest, CustomerDeletionTest, CustomerSortTest |
| DashboardControllerTest | 11 | DashboardRevenueChartTest, ReportsServiceTest |
| DeleteBrowserFlowTest | 6 | CustomerDeletionTest, ServiceCatalogDeletionTest, UserAccountAuditTest |
| DeliveryControllerTest | 4 | DeliverySchedulingTest |
| DetailViewConsistencyTest | 20 | ReviewDisplayTest, AdminCommunicationTest |
| DynamicRbacTest | 19 | DynamicRolePermissionTest, AuthorizationBoundaryTest |
| GarmentControllerTest | 5 | LoaiDoGiatTest, GarmentCategoryManagementTest |
| InvoiceControllerTest | 4 | InvoiceHistoryObserverTest, PaymentOrderRedirectTest |
| ModuleConsistencyTest | 7 | BookingOrderConversionTest, ReviewDisplayTest, ServiceCatalogDeletionTest |
| OrderControllerTest | 16 | BookingOrderConversionTest, RewardPointTest, LuuDonHangRequestTest |
| OrderNullCustomerTest | 4 | LuuDonHangRequestTest |
| OrderPaymentStatusTest | 7 | RewardPointTest, PaymentOrderRedirectTest |
| PaidRecordsAreLockedTest | 20 | RewardPointTest, BookingOrderConversionTest |
| ReportsRouteSmokeTest | 2 | ReportsServiceTest |
| ServiceCategoryControllerTest | 7 | ServiceCatalogDeletionTest |
| ServiceControllerTest | 4 | ServiceCatalogDeletionTest |
| SoftDeleteRelationIntegrityTest | 9 | CustomerDeletionTest, ServiceCatalogDeletionTest, UserAccountAuditTest |
| StatusConsistencyTest | 9 | OrderStatusSchemaTest, ReviewStatusTest |
| UserAvatarSyncTest | 4 | ProfileUpdateTest, UserAvatarUrlTest |

Chạy bộ hiện hành: `php artisan test --compact`. Test tạo schema chỉ trong SQLite in-memory, không chạy migration Supabase. Kiểm thử PostgreSQL RPC độc lập: xem `docs/supabase/BUSINESS_RULES.md`. Không dùng database Live có dữ liệu khách hàng để chạy PHPUnit.
