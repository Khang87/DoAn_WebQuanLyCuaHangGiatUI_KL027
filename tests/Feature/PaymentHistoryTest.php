<?php

namespace Tests\Feature;

use App\Models\ThanhToan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PaymentHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->integer('KhachHangID')->nullable();
            $table->integer('NhanVienID')->nullable();
            $table->string('TenDangNhap')->nullable();
        });
        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
            $table->string('SoDienThoai')->nullable();
        });
        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->integer('KhachHangID')->nullable();
            $table->string('MaDonHang');
            $table->string('TrangThai')->default('Đã tiếp nhận');
            $table->dateTime('NgayTao')->nullable();
            $table->decimal('ThanhTien', 18, 2)->default(15000);
        });
        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->integer('DonHangID');
            $table->string('MaHoaDon');
            $table->string('TrangThai')->default('Chưa thanh toán');
            $table->decimal('ThanhTien', 18, 2)->default(15000);
        });
        Schema::create('ThanhToan', function (Blueprint $table): void {
            $table->increments('ThanhToanID');
            $table->integer('DonHangID')->nullable();
            $table->decimal('SoTien', 18, 2);
            $table->string('PhuongThuc');
            $table->string('TrangThai');
            $table->dateTime('ThoiGian')->nullable();
            $table->string('MaGiaoDich');
            $table->text('GhiChu')->nullable();
        });
        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('HoTen');
        });
        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->integer('TaiKhoanID')->nullable();
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->integer('BanGhiID');
            $table->text('DuLieuMoi')->nullable();
            $table->dateTime('ThoiGian')->nullable();
        });
        Schema::create('ThongBao', function (Blueprint $table): void {
            $table->increments('ThongBaoID');
            $table->integer('TaiKhoanID');
            $table->string('LoaiThongBao')->nullable();
            $table->dateTime('ThoiGianGui')->nullable();
            $table->boolean('DaDoc')->default(false);
        });
        DB::table('KhachHang')->insert(['KhachHangID' => 1, 'HoTen' => 'Nguyễn An', 'SoDienThoai' => '0901234567']);
        DB::table('DonHang')->insert(['DonHangID' => 1, 'KhachHangID' => 1, 'MaDonHang' => 'DH100']);
        DB::table('HoaDon')->insert(['HoaDonID' => 1, 'DonHangID' => 1, 'MaHoaDon' => 'HD100']);
    }

    private function viewer(bool $allowed = true, bool $customer = false): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('isCustomer')->andReturn($customer);
        $user->shouldReceive('isOwner')->andReturn(false);
        $user->shouldReceive('getKey')->andReturn(1);
        $user->shouldReceive('getAuthIdentifier')->andReturn(1);
        $user->shouldReceive('getNameAttribute')->andReturn('Staff');
        $user->shouldReceive('getRoleAttribute')->andReturn('staff');
        $user->shouldReceive('canPermission')->andReturnUsing(fn ($permission) => $allowed && $permission === 'payments.view');

        return $user;
    }

    private function payment(string $status = 'Thành công', string $date = '2026-10-10 23:59:59', string $method = 'Tiền mặt'): int
    {
        return DB::table('ThanhToan')->insertGetId([
            'DonHangID' => 1, 'SoTien' => 15000, 'PhuongThuc' => $method, 'TrangThai' => $status,
            'ThoiGian' => $date, 'MaGiaoDich' => 'TX-'.$status, 'GhiChu' => '<script>window.bad=true</script>',
        ], 'ThanhToanID');
    }

    public function test_history_totals_cover_all_pages_and_exclude_failed_and_pending_payments(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->payment();
        }
        $this->payment('Đã hoàn tiền');
        $this->payment('Thất bại');
        $this->payment('Chờ thanh toán');
        $before = DB::table('ThanhToan')->get()->toJson();
        $this->actingAs($this->viewer())->get('/payment-history?page=2')->assertOk()
            ->assertViewHas('totals', fn ($totals) => (float) $totals->collected === 180000.0 && (float) $totals->refunded === 15000.0);
        $this->get('/payment-history?status='.urlencode('Thất bại'))->assertOk()
            ->assertViewHas('totals', fn ($totals) => (float) $totals->collected === 0.0 && (float) $totals->refunded === 0.0);
        $this->assertSame($before, DB::table('ThanhToan')->get()->toJson());
        $this->assertSame(0, DB::table('NhatKyHeThong')->count());
        $this->assertSame('Đã tiếp nhận', DB::table('DonHang')->value('TrangThai'));
        $this->assertSame('Chưa thanh toán', DB::table('HoaDon')->value('TrangThai'));
    }

    public function test_history_filters_actual_collector_and_does_not_infer_missing_attribution(): void
    {
        DB::table('NhanVien')->insert(['NhanVienID' => 1, 'HoTen' => 'Người thu tiền']);
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 1, 'NhanVienID' => 1, 'TenDangNhap' => 'collector']);
        $id = $this->payment();
        $this->payment();
        DB::table('NhatKyHeThong')->insert([
            'TaiKhoanID' => 1, 'HanhDong' => ThanhToan::COLLECTION_AUDIT_ACTION,
            'BangDuLieu' => 'ThanhToan', 'BanGhiID' => $id,
        ]);
        $this->actingAs($this->viewer())->get('/payment-history?collector_id=1')->assertOk()
            ->assertViewHas('payments', fn ($rows) => $rows->total() === 1 && $rows->first()->getKey() === $id)
            ->assertSee('Người thu tiền');
        $this->get('/payment-history')->assertSee('Chưa ghi nhận');
        $this->get('/payment-history/'.$id)->assertSee('Người thu tiền');
    }

    public function test_payment_entry_lists_unpaid_orders_instead_of_transaction_records(): void
    {
        foreach ([2 => 'Đã hủy', 3 => 'Đã thanh toán', 4 => 'Đã tiếp nhận', 5 => 'Đang giặt'] as $id => $status) {
            DB::table('DonHang')->insert(['DonHangID' => $id, 'MaDonHang' => 'DH'.$id, 'TrangThai' => $status]);
        }
        DB::table('HoaDon')->insert(['DonHangID' => 4, 'MaHoaDon' => 'HD4', 'TrangThai' => 'Đã hủy']);
        DB::table('ThanhToan')->insert(['DonHangID' => 5, 'SoTien' => 15000, 'PhuongThuc' => 'Tiền mặt', 'TrangThai' => 'Thành công', 'MaGiaoDich' => 'COLLECTED']);
        DB::table('DonHang')->insert(['DonHangID' => 6, 'MaDonHang' => 'FREE', 'ThanhTien' => 0]);
        DB::table('DonHang')->insert(['DonHangID' => 7, 'MaDonHang' => 'FREE-INVOICE', 'ThanhTien' => 15000]);
        DB::table('HoaDon')->insert(['DonHangID' => 7, 'MaHoaDon' => 'FREE', 'ThanhTien' => 0]);
        $this->actingAs($this->viewer())->get('/payments')->assertOk()
            ->assertViewHas('orders', fn ($rows) => $rows->total() === 1 && $rows->first()->getKey() === 1)
            ->assertSee('Đơn hàng cần thanh toán')->assertDontSee('COLLECTED')
            ->assertDontSee('href="'.route('payments.create', ['order_id' => 1]).'"', false);
    }

    public function test_invalid_html_date_range_displays_feedback_and_preserves_dates(): void
    {
        $this->actingAs($this->viewer())->from('/payment-history')->followingRedirects()
            ->get('/payment-history?date_from=2026-10-11&date_to=2026-10-10')->assertOk()
            ->assertSee('Đến ngày phải bằng hoặc sau Từ ngày.')
            ->assertSee('value="2026-10-11"', false)->assertSee('value="2026-10-10"', false);
    }

    public function test_history_lists_all_statuses_and_has_its_own_active_sidebar_item(): void
    {
        foreach (['Thành công', 'Chờ thanh toán', 'Thất bại', 'Đã hoàn tiền'] as $status) {
            $this->payment($status);
        }
        $this->actingAs($this->viewer())->get('/payment-history')->assertOk()
            ->assertViewHas('payments', fn ($rows) => $rows->total() === 4)
            ->assertSee('Lịch sử thanh toán')->assertSee('DH100')->assertSee('Nguyễn An')
            ->assertDontSee('Thêm thanh toán')->assertDontSee('deletePaymentForm')
            ->assertSee('href="'.route('payment-history.index').'" class="sidebar-menu-link active"', false);
    }

    public function test_history_filters_combine_and_include_the_whole_end_day(): void
    {
        $id = $this->payment('Đã hoàn tiền', '2026-10-10 23:59:59', 'Chuyển khoản');
        $this->payment('Thành công');
        $this->payment('Đã hoàn tiền', '2026-10-11 00:00:00', 'Chuyển khoản');
        $this->actingAs($this->viewer())->get('/payment-history?'.http_build_query([
            'search' => 'HD100', 'method' => 'bank_transfer', 'status' => 'Đã hoàn tiền',
            'date_from' => '2026-10-10', 'date_to' => '2026-10-10',
        ]))->assertOk()->assertViewHas('payments', fn ($rows) => $rows->total() === 1 && $rows->first()->getKey() === $id);
        $this->get('/payment-history?date_to=2026-10-10')->assertOk()->assertViewHas('payments', fn ($rows) => $rows->total() === 2);
        $this->get('/payment-history?date_from=2026-10-11')->assertOk()->assertViewHas('payments', fn ($rows) => $rows->total() === 1);
    }

    public function test_history_pagination_is_stable_and_row_numbers_continue(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->payment();
        }
        $this->actingAs($this->viewer())->get('/payment-history?status=Thành%20công&page=2')->assertOk()
            ->assertViewHas('payments', fn ($rows) => $rows->firstItem() === 11 && $rows->pluck('ThanhToanID')->all() === [2, 1])
            ->assertSee('<td>11</td>', false)->assertSee('<td>12</td>', false);
    }

    public function test_history_details_are_read_only_and_escape_notes(): void
    {
        $id = $this->payment();
        $this->actingAs($this->viewer())->get('/payment-history/'.$id)->assertOk()
            ->assertSee('&lt;script&gt;window.bad=true&lt;/script&gt;', false)
            ->assertDontSee('<script>window.bad=true</script>', false)
            ->assertDontSee(route('payments.edit', $id), false)
            ->assertDontSee('deletePaymentForm')->assertSee('Quay lại lịch sử');
        $this->get('/payment-history/999')->assertNotFound();
        $this->post('/payment-history/'.$id)->assertStatus(405);
    }

    public function test_history_rejects_guest_missing_permission_and_customer_role(): void
    {
        $id = $this->payment();
        foreach (['/payment-history', '/payment-history/'.$id] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->actingAs($this->viewer(false))->get('/payment-history')->assertForbidden();
        $this->get('/payment-history/'.$id)->assertForbidden();
        $this->actingAs($this->viewer(true, true))->get('/payment-history')->assertForbidden();
    }

    public function test_invalid_filters_are_rejected_and_empty_results_are_explained(): void
    {
        $this->actingAs($this->viewer())->getJson('/payment-history?date_from=2026-10-11&date_to=2026-10-10')
            ->assertUnprocessable()->assertJsonValidationErrors('date_to');
        $this->getJson('/payment-history?sort=unsafe&status=unknown&date_to=invalid')->assertUnprocessable();
        $this->get('/payment-history')->assertOk()->assertSee('Không có giao dịch phù hợp.');
    }
}
