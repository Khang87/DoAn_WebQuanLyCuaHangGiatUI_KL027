<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\DonHangController;
use App\Http\Controllers\Api\V1\StaffMobileController;
use App\Http\Requests\Admin\LuuChiTietDonHangRequest;
use App\Models\Booking;
use App\Models\BookingGiaoNhan;
use App\Models\BookingKiemKe;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\LoaiDoGiat;
use App\Services\BookingService;
use App\Services\CatalogAvailability;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Support\OrderWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BookingInspectionFixture;
use Tests\TestCase;

class CatalogAvailabilityTest extends TestCase
{
    use BookingInspectionFixture;

    public function createApplication()
    {
        $app = parent::createApplication();

        // Must run before Tests\TestCase's RBAC schema check/seeding.
        $this->assertSame('sqlite', $app['config']->get('database.default'));
        $this->assertSame(':memory:', $app['config']->get('database.connections.sqlite.database'));
        $this->assertEmpty($app['config']->get('database.connections.sqlite.url'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Http::preventStrayRequests();
        Http::fake();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        Schema::table('ThanhToan', fn (Blueprint $table) => $table->string('TrangThai')->nullable());
        Schema::create('Quyen', function (Blueprint $table): void {
            $table->increments('QuyenID');
            $table->string('MaQuyen');
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('VaiTro_Quyen', function (Blueprint $table): void {
            $table->integer('VaiTroID');
            $table->integer('QuyenID');
        });
        Schema::create('BookingGiaoNhan', function (Blueprint $table): void {
            $table->increments('BookingGiaoNhanID');
            $table->integer('BookingID');
            foreach (['DonHangID', 'BookingKiemKeID', 'NhanVienID'] as $column) {
                $table->integer($column)->nullable();
            }
            $table->string('LoaiGiaoNhan');
            $table->string('HinhThuc');
            $table->string('DiaChi')->nullable();
            $table->string('TrangThai')->default(DeliveryStatus::Pending->dbValue());
            $table->decimal('PhiGiaoNhan')->default(0);
            foreach (['ThoiGianDuKien', 'ThoiGianThucTe', 'NgayTao'] as $column) {
                $table->dateTime($column)->nullable();
            }
        });
        Schema::create('BookingKiemKe', function (Blueprint $table): void {
            $table->increments('BookingKiemKeID');
            $table->integer('BookingID');
            $table->json('DuLieuThucTe');
            $table->boolean('CoChenhLech')->default(false);
            $table->integer('PhienBan')->default(1);
            $table->dateTime('CapNhatLuc')->nullable();
        });
        Schema::create('StaffBookingDraft', function (Blueprint $table): void {
            $table->integer('BookingID')->primary();
            $table->json('Payload');
            $table->boolean('CoChenhLech')->default(false);
            $table->dateTime('CapNhatLuc')->nullable();
        });

        DB::table('KhachHang')->insert(['KhachHangID' => 9, 'HoTen' => 'Test customer']);
        foreach ([1 => 'Hoạt động', 2 => 'Tạm ngưng'] as $id => $status) {
            DB::table('LoaiDichVu')->insert(['LoaiDichVuID' => $id, 'TenLoaiDichVu' => 'Services '.$id, 'TrangThai' => $status]);
            DB::table('DanhMucLoaiDoGiat')->insert(['DanhMucID' => $id, 'TenDanhMuc' => 'Garments '.$id, 'TrangThai' => $status]);
        }
        foreach ([1, 2, 3] as $id) {
            DB::table('DichVu')->insert([
                'DichVuID' => $id, 'TenDichVu' => 'Service '.$id,
                'LoaiDichVuID' => $id === 3 ? 2 : 1, 'TrangThai' => $id === 2 ? 'Tạm ngưng' : 'Hoạt động',
            ]);
            DB::table('LoaiDoGiat')->insert([
                'LoaiDoGiatID' => $id, 'TenLoaiDoGiat' => 'Garment '.$id,
                'DanhMucID' => $id === 3 ? 2 : 1, 'TrangThai' => $id === 2 ? 'Tạm ngưng' : 'Hoạt động',
            ]);
        }
        foreach ([1, 2] as $id) {
            DB::table('DonViTinh')->insert(['DonViTinhID' => $id, 'TenDonViTinh' => 'Cái', 'KyHieu' => 'Cái', 'TrangThai' => 'Hoạt động']);
            foreach ([1, 2, 3] as $service) {
                foreach ([1, 2, 3] as $garment) {
                    DB::table('BangGia')->insert([
                        'DichVuID' => $service, 'LoaiDoGiatID' => $garment, 'DonViTinhID' => $id,
                        'DonGia' => 15000, 'TrangThai' => 'Hoạt động',
                    ]);
                }
            }
        }
        $this->mock(NotificationService::class, function ($mock): void {
            $mock->shouldReceive('notifyManagement')->andReturn(0);
        });
        $this->actingAsBookingEmployee();
        Gate::before(fn () => true);
    }

    private function item(int $service = 1, int $garment = 1, int $unit = 1): array
    {
        return ['DichVuID' => $service, 'LoaiDoGiatID' => $garment, 'DonViTinhID' => $unit,
            'SoLuong' => 2, 'TinhTrangTruocKhiGiat' => 'Bình thường'];
    }

    private function bookingData(array $items): array
    {
        return ['customer_id' => 9, 'method' => 'Tại cửa hàng', 'return_method' => 'Tại cửa hàng',
            'scheduled_date' => '2026-10-25', 'scheduled_time' => '14:30',
            'status' => BookingStatus::Pending->value, 'items' => $items];
    }

    private function newBooking(): Booking
    {
        return app(BookingService::class)->create($this->bookingData([$this->item()]));
    }

    private function newOrder(): DonHang
    {
        return app(OrderService::class)->create([
            'KhachHangID' => 9, 'TrangThai' => OrderStatus::Received->value, 'items' => [$this->item()],
        ]);
    }

    private function pendingOrder(): DonHang
    {
        $order = $this->newOrder();
        DB::table('DonHang')->where('DonHangID', $order->getKey())->update(['TrangThai' => OrderStatus::Pending->value]);

        return $order->fresh();
    }

    private function prepareInspection(Booking $booking): void
    {
        app(BookingService::class)->approve($booking);
        BookingGiaoNhan::query()->where('BookingID', $booking->getKey())->where('LoaiGiaoNhan', 'NHAN_DO')
            ->update(['NhanVienID' => 1]);
        app(BookingService::class)->markReceived($booking);
        $booking->refresh();
    }

    private function assertRejected(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Expected catalog availability validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public static function suspendedReferences(): array
    {
        return [
            'suspended service' => [2, 1, 'DichVuID'],
            'suspended service parent' => [3, 1, 'DichVuID'],
            'suspended garment' => [1, 2, 'LoaiDoGiatID'],
            'suspended garment parent' => [1, 3, 'LoaiDoGiatID'],
        ];
    }

    #[DataProvider('suspendedReferences')]
    public function test_new_order_and_booking_reject_suspended_references_despite_active_prices(int $service, int $garment, string $field): void
    {
        $item = $this->item($service, $garment);
        $item['ChiTietBookingID'] = 999;
        $item['ChiTietDonHangID'] = 999;
        $item['DonGia'] = 1;
        $this->assertNotNull(app(PricingService::class)->getLatestPricing($service, $garment, 1));
        $this->assertRejected(fn () => app(BookingService::class)->create($this->bookingData([$item])), 'items.0.'.$field);
        $this->assertRejected(fn () => app(OrderService::class)->create([
            'KhachHangID' => 9, 'TrangThai' => OrderStatus::Received->value, 'items' => [$item],
        ]), 'items.0.'.$field);
        $this->assertDatabaseCount('Booking', 0);
        $this->assertDatabaseCount('DonHang', 0);
        Http::assertNothingSent();
    }

    public function test_active_references_are_accepted_and_scopes_are_opt_in_for_catalog_and_history(): void
    {
        $booking = $this->newBooking();
        $order = $this->newOrder();
        $this->assertSame(30000.0, (float) $order->TongTien);
        $this->assertSame([1], DichVu::availableForBooking()->pluck('DichVuID')->all());
        $this->assertSame([1], LoaiDoGiat::availableForBooking()->pluck('LoaiDoGiatID')->all());
        $this->assertSame(3, DichVu::count());
        $this->assertSame(3, LoaiDoGiat::count());
        DB::table('LoaiDichVu')->where('LoaiDichVuID', 1)->update(['TrangThai' => 'Tạm ngưng']);
        DB::table('DanhMucLoaiDoGiat')->where('DanhMucID', 1)->update(['TrangThai' => 'Tạm ngưng']);
        $this->assertSame('Service 1', $booking->fresh()->chiTietBookings->first()->dichVu->TenDichVu);
        $line = $order->fresh()->chiTietDonHangs->first();
        $this->assertSame('Tạm ngưng', $line->dichVu->loaiDichVu->TrangThai);
        $this->assertSame('Garment 1', $line->loaiDoGiat->TenLoaiDoGiat);
        $this->assertSame('Tạm ngưng', $line->loaiDoGiat->danhMuc->TrangThai);
        $this->assertSame(3, DichVu::count());
        $this->assertSame(3, LoaiDoGiat::count());
    }

    public function test_options_filter_both_parents_but_historic_price_lookup_is_unrestricted(): void
    {
        $booking = $this->newBooking();
        $booking->chiTietBookings()->update(['DichVuID' => 2, 'LoaiDoGiatID' => 2]);
        $pricing = app(PricingService::class);
        $this->assertCount(2, $pricing->orderOptions());
        $this->assertCount(3, $pricing->orderOptionsForRecord($booking));
        $keys = $pricing->orderOptionsForRecord($booking)->map(fn ($p) => "$p->DichVuID:$p->LoaiDoGiatID:$p->DonViTinhID");
        $this->assertTrue($keys->contains('2:2:1'));
        $this->assertFalse($keys->contains('2:1:1'));
        $this->assertFalse($keys->contains('2:2:2'));
        $this->assertSame(15000.0, (float) $pricing->getLatestPricing(2, 2, 1)->DonGia);
    }

    public function test_booking_retains_existing_suspended_tuple_but_rejects_new_duplicates_and_new_units(): void
    {
        $booking = $this->newBooking();
        DB::table('DichVu')->where('DichVuID', 1)->update(['TrangThai' => 'Tạm ngưng']);
        app(BookingService::class)->update($booking, ['items' => [$this->item()]]);
        $this->assertSame(30000.0, app(OrderService::class)->estimateBooking($booking, [$this->item()])['TongTien']);
        $this->assertRejected(fn () => app(BookingService::class)->update($booking, ['items' => [$this->item(), $this->item()]]), 'items.1.DichVuID');
        $this->assertRejected(fn () => app(OrderService::class)->estimateBooking($booking, [$this->item(1, 1, 2)]), 'items.0.DichVuID');
        $this->assertRejected(fn () => app(BookingService::class)->update($booking, ['items' => [$this->item(3)]]), 'items.0.DichVuID');
        $this->assertDatabaseCount('ChiTietBooking', 1);
    }

    public function test_forged_loaded_relations_and_another_bookings_history_never_grant_exemptions(): void
    {
        $booking = $this->newBooking();
        $other = $this->newBooking();
        $other->chiTietBookings()->update(['DichVuID' => 2]);
        $booking->setRelation('chiTietBookings', $other->chiTietBookings()->get());
        $forged = $this->item(2) + ['ChiTietBookingID' => $other->chiTietBookings()->first()->getKey()];
        $this->assertRejected(fn () => app(OrderService::class)->estimateBooking($booking, [$forged]), 'items.0.DichVuID');
        $this->assertRejected(fn () => app(BookingService::class)->create($this->bookingData([$forged])), 'items.0.DichVuID');
        $unsaved = new Booking(['BookingID' => $other->getKey()]);
        $this->assertRejected(fn () => CatalogAvailability::assertItemsAvailable([$this->item(2)], $unsaved), 'items.0.DichVuID');
    }

    public function test_order_update_and_receiving_preserve_existing_suspended_lines_and_price_snapshot(): void
    {
        $order = $this->pendingOrder();
        DB::table('LoaiDichVu')->where('LoaiDichVuID', 1)->update(['TrangThai' => 'Tạm ngưng']);
        app(OrderService::class)->update($order, ['items' => [$this->item()]]);
        $this->assertRejected(fn () => app(OrderService::class)->update($order, ['items' => [$this->item(), $this->item(3)]]), 'items.1.DichVuID');
        $line = $order->chiTietDonHangs()->first();
        $line->update(['DonGia' => 777]);
        DB::table('BangGia')->update(['TrangThai' => 'Tạm ngưng']);
        $received = app(OrderService::class)->completeReceivingInspection($order, [
            $this->item() + ['ChiTietDonHangID' => $line->getKey()],
        ]);
        $this->assertSame(OrderStatus::Received->value, $received->TrangThai);
        $this->assertSame(777.0, (float) $received->chiTietDonHangs->first()->DonGia);
        $this->assertSame(1554.0, (float) $received->TongTien);
    }

    public function test_receiving_rejects_new_suspended_lines_even_with_forged_snapshot_prices(): void
    {
        $order = $this->pendingOrder();
        $line = $order->chiTietDonHangs()->first();
        $this->assertRejected(fn () => app(OrderService::class)->completeReceivingInspection($order, [
            $this->item() + ['ChiTietDonHangID' => $line->getKey()],
            $this->item(2) + ['DonGia' => 1],
        ]), 'items.1.DichVuID');
        $this->assertRejected(fn () => app(OrderService::class)->completeReceivingInspection($order, [
            $this->item(2) + ['ChiTietDonHangID' => $line->getKey(), 'DonGia' => 1],
        ]), 'items.0.DichVuID');
        $this->assertSame(OrderStatus::Pending->value, $order->fresh()->TrangThai);
        $this->assertDatabaseCount('ChiTietDonHang', 1);
    }

    public function test_draft_and_conversion_allow_existing_suspended_parents_but_reject_added_lines(): void
    {
        $booking = $this->newBooking();
        $this->prepareInspection($booking);
        DB::table('LoaiDichVu')->where('LoaiDichVuID', 1)->update(['TrangThai' => 'Tạm ngưng']);
        DB::table('DanhMucLoaiDoGiat')->where('DanhMucID', 1)->update(['TrangThai' => 'Tạm ngưng']);
        $data = $this->inspectionPayload($booking, 1);
        $this->assertRejected(fn () => app(BookingService::class)->saveInspectionDraft($booking, array_replace($data, [
            'items' => [$this->item(), $this->item(3)],
        ])), 'items.1.DichVuID');
        $this->assertDatabaseCount('StaffBookingDraft', 0);
        app(BookingService::class)->saveInspectionDraft($booking, $data);
        $order = app(BookingService::class)->submitInspection($booking, $data);
        $this->assertSame(30000.0, (float) $order->TongTien);
        $this->assertSame('Service 1', $order->chiTietDonHangs->first()->dichVu->TenDichVu);
        $this->assertDatabaseCount('StaffBookingDraft', 0);
        $this->assertDatabaseCount('DonHang', 1);
        Http::assertNothingSent();
    }

    public function test_new_inspection_and_matching_inspection_snapshot_cannot_self_authorize_suspended_items(): void
    {
        $booking = $this->newBooking();
        $this->prepareInspection($booking);
        $items = [$this->item(2)];
        $data = array_replace($this->inspectionPayload($booking, 1), ['items' => $items]);
        $this->assertRejected(fn () => app(BookingService::class)->submitInspection($booking, $data), 'items.0.DichVuID');
        $this->assertDatabaseCount('BookingKiemKe', 0);
        BookingKiemKe::create(['BookingID' => $booking->getKey(), 'DuLieuThucTe' => ['items' => $items]]);
        $this->assertRejected(fn () => app(OrderService::class)->createFromBooking($booking, $items, 1), 'items.0.DichVuID');
        $this->assertDatabaseCount('DonHang', 0);
    }

    public function test_persisted_unquantified_booking_requests_can_be_inspected_after_suspension(): void
    {
        $booking = $this->newBooking();
        DB::table('BookingYeuCauDichVu')->insert([
            'BookingID' => $booking->getKey(), 'DichVuID' => 2, 'LoaiDoGiatID' => 3, 'DonViTinhID' => 1,
        ]);
        $this->assertSame(30000.0, app(OrderService::class)->estimateBooking($booking, [$this->item(2, 3)])['TongTien']);
        $this->assertRejected(fn () => app(OrderService::class)->estimateBooking($booking, [$this->item(2, 1)]), 'items.0.DichVuID');
    }

    private function validateDetail(array $item, int $orderId, ?int $lineId = null): array
    {
        $request = LuuChiTietDonHangRequest::create('/order-items/'.($lineId ?? ''), $lineId ? 'PUT' : 'POST', [
            ...$item, 'DonHangID' => $orderId, 'DonGia' => 15000, 'KhoiLuong' => 1,
        ]);
        if ($lineId) {
            $route = new Route(['PUT'], 'order-items/{order_item}', fn () => null);
            $route->bind($request);
            $request->setRouteResolver(fn () => $route);
        }
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request->validated();
    }

    public function test_direct_detail_creation_and_updates_cannot_borrow_other_lines_or_orders(): void
    {
        $order = $this->pendingOrder();
        $other = $this->pendingOrder();
        $line = $order->chiTietDonHangs()->first();
        $this->assertSame(1, (int) $this->validateDetail($this->item(), $order->getKey())['DichVuID']);
        DB::table('DichVu')->where('DichVuID', 1)->update(['TrangThai' => 'Tạm ngưng']);
        $this->assertRejected(fn () => $this->validateDetail($this->item() + ['ChiTietDonHangID' => $line->getKey()], $order->getKey()), 'DichVuID');
        $this->assertSame(1, (int) $this->validateDetail($this->item(), $order->getKey(), $line->getKey())['DichVuID']);
        $this->assertRejected(fn () => $this->validateDetail($this->item(), $other->getKey(), $line->getKey()), 'DichVuID');
        $this->assertRejected(fn () => $this->validateDetail($this->item(2), $order->getKey(), $line->getKey()), 'DichVuID');
    }

    public function test_order_forms_only_offer_available_or_that_orders_stored_selections(): void
    {
        $order = $this->pendingOrder();
        $order->chiTietDonHangs()->update(['DichVuID' => 3, 'LoaiDoGiatID' => 3]);
        $request = Request::create('/orders/create', 'GET', ['DichVuID' => 2, 'LoaiDoGiatID' => 2]);
        $create = app(DonHangController::class)->create($request)->getData();
        $this->assertSame([1], $create['services']->pluck('DichVuID')->all());
        $this->assertSame([1], $create['garments']->pluck('LoaiDoGiatID')->all());
        $edit = app(DonHangController::class)->edit($request, $order->getKey())->getData();
        $this->assertSame([1, 3], $edit['services']->pluck('DichVuID')->all());
        $this->assertSame([1, 3], $edit['garments']->pluck('LoaiDoGiatID')->all());
        $this->assertSame([1, 2], $edit['serviceCategories']->pluck('LoaiDichVuID')->all());

        // Màn chi tiết mặc định chỉ xem thông tin, nên không dựng danh mục nhập.
        $show = app(DonHangController::class)->show($order->getKey())->getData();
        $this->assertEmpty($show['services']);
        $this->assertEmpty($show['garments']);

        // Tùy chọn kiểm tra thực tế chỉ hiện khi mở đúng ý định tiếp nhận
        // từ cột Thao tác của danh sách.
        $this->app->instance('request', Request::create('/orders/'.$order->getKey(), 'GET', [
            'action' => OrderWorkflow::INTENT_RECEIVING,
        ]));
        $receiving = app(DonHangController::class)->show($order->getKey())->getData();
        $this->assertSame([1, 3], $receiving['services']->pluck('DichVuID')->all());
        $this->assertSame([1, 3], $receiving['garments']->pluck('LoaiDoGiatID')->all());
        $this->assertSame([1, 2], $receiving['garmentCategories']->pluck('DanhMucID')->all());
        $this->assertCount(3, $receiving['inspectionPricings']);
    }

    public function test_booking_and_staff_catalogs_keep_only_own_suspended_selections(): void
    {
        $booking = $this->newBooking();
        $booking->chiTietBookings()->update(['DichVuID' => 3, 'LoaiDoGiatID' => 3]);
        $this->prepareInspection($booking);
        $data = app(BookingController::class)->inspection($booking->getKey())->getData();
        $this->assertSame([1, 3], $data['services']->pluck('DichVuID')->all());
        $this->assertSame([1, 3], $data['garments']->pluck('LoaiDoGiatID')->all());
        $this->assertCount(3, $data['pricingUnitOptions']);
        DB::table('Booking')->where('BookingID', $booking->getKey())->update(['HinhThucNhanDo' => 'Tại nhà', 'DiaChiNhan' => 'Test address']);
        $mobile = app(StaffMobileController::class)->booking($booking->getKey())->getData(true)['data'];
        $this->assertSame([1, 3], array_column($mobile['catalog']['services'], 'id'));
        $this->assertSame([1, 3], array_column($mobile['catalog']['garments'], 'id'));
        $this->assertCount(3, $mobile['catalog']['prices']);
        $this->assertSame('Service 3', $mobile['items'][0]['service_name']);
    }
}
