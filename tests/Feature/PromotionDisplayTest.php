<?php

namespace Tests\Feature;

use App\Models\KhuyenMai;
use App\Services\PromotionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromotionDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('KhuyenMai', function (Blueprint $table): void {
            $table->increments('KhuyenMaiID');
            $table->string('MaKhuyenMai');
            $table->string('TenKhuyenMai');
            $table->string('LoaiKhuyenMai');
            $table->decimal('GiaTriGiam', 12, 2);
            $table->decimal('GiaTriDonToiThieu', 12, 2)->nullable();
            $table->decimal('MucGiamToiDa', 12, 2)->nullable();
            $table->integer('SoLuongSuDung')->nullable();
            $table->string('DieuKienApDung')->nullable();
            $table->date('NgayBatDau');
            $table->date('NgayKetThuc')->nullable();
            $table->string('TrangThai');
        });

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->unsignedInteger('BookingID')->nullable();
            $table->unsignedInteger('KhuyenMaiID')->nullable();
            $table->decimal('TienGiamKhuyenMai', 12, 2)->default(0);
            $table->string('TrangThai');
        });

        Schema::create('Booking', function (Blueprint $table): void {
            $table->increments('BookingID');
            $table->unsignedInteger('KhuyenMaiID')->nullable();
            $table->boolean('KhuyenMaiDaTru')->default(false);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('DonHang');
        Schema::dropIfExists('Booking');
        Schema::dropIfExists('KhuyenMai');

        parent::tearDown();
    }

    public function test_create_form_keeps_back_and_save_actions_without_a_cancel_action(): void
    {
        $this->withViewErrors([]);
        $html = view('admin.promotions.create')->render();

        $this->assertStringContainsString('href="'.route('promotions.index').'" class="btn btn-outline-secondary btn-sm"', $html);
        $this->assertStringContainsString('Quay lại', $html);
        $this->assertStringContainsString('type="submit" class="btn btn-primary"', $html);
        $this->assertStringContainsString('Thêm mới', $html);
        $this->assertStringNotContainsString('class="btn btn-outline-secondary">Hủy</a>', $html);
    }

    public function test_promotions_list_separates_expiration_date_from_linked_order_count(): void
    {
        $emptyPromotions = app(PromotionService::class)->getAll();

        DB::table('KhuyenMai')->insert([
            [
                'KhuyenMaiID' => 1,
                'MaKhuyenMai' => 'PROMO10',
                'TenKhuyenMai' => 'Giảm giá 10%',
                'LoaiKhuyenMai' => 'Phần trăm',
                'GiaTriGiam' => 10,
                'NgayBatDau' => '2026-10-01',
                'NgayKetThuc' => '2026-10-31',
                'TrangThai' => 'Hoạt động',
            ],
            [
                'KhuyenMaiID' => 2,
                'MaKhuyenMai' => 'NOEXPIRY',
                'TenKhuyenMai' => 'Không hạn',
                'LoaiKhuyenMai' => 'Tiền mặt',
                'GiaTriGiam' => 1000,
                'NgayBatDau' => '2026-10-01',
                'NgayKetThuc' => null,
                'TrangThai' => 'Hoạt động',
            ],
        ]);
        DB::table('DonHang')->insert([
            ['KhuyenMaiID' => 1, 'TrangThai' => 'Đã tiếp nhận'],
            ['KhuyenMaiID' => 1, 'TrangThai' => 'Đã hủy'],
        ]);

        $promotions = app(PromotionService::class)->getAll();
        $html = view('admin.promotions.index', [
            'promotions' => $promotions,
            'statuses' => ['Hoạt động' => 'Hoạt động'],
        ])->render();

        $this->assertSame(2, $promotions->getCollection()->firstWhere('KhuyenMaiID', 1)->usedCount);
        $this->assertStringContainsString('<th class="fw-bold text-dark">Hạn sử dụng</th>', $html);
        $this->assertStringContainsString('<th class="fw-bold text-dark">Số lượng đã sử dụng</th>', $html);
        $this->assertStringContainsString('31/10/2026', $html);
        $this->assertStringContainsString('Không hạn', $html);
        $this->assertStringContainsString('2 đơn', $html);
        $this->assertStringNotContainsString('2 lượt', $html);
        $this->assertStringNotContainsString('Hết hạn:', $html);

        $emptyHtml = view('admin.promotions.index', [
            'promotions' => $emptyPromotions,
            'statuses' => ['Hoạt động' => 'Hoạt động'],
        ])->render();
        $this->assertStringContainsString('colspan="8"', $emptyHtml);
    }

    public function test_status_filter_covers_every_state_the_status_column_can_show(): void
    {
        DB::table('KhuyenMai')->insert([
            [
                'KhuyenMaiID' => 1,
                'MaKhuyenMai' => 'RUNNING',
                'TenKhuyenMai' => 'Còn chạy',
                'LoaiKhuyenMai' => 'Phần trăm',
                'GiaTriGiam' => 10,
                'NgayBatDau' => today()->subDays(5)->toDateString(),
                'NgayKetThuc' => today()->addDays(10)->toDateString(),
                'TrangThai' => 'Hoạt động',
            ],
            [
                'KhuyenMaiID' => 2,
                'MaKhuyenMai' => 'PASSED',
                'TenKhuyenMai' => 'Qua hạn dùng',
                'LoaiKhuyenMai' => 'Phần trăm',
                'GiaTriGiam' => 10,
                'NgayBatDau' => today()->subDays(20)->toDateString(),
                'NgayKetThuc' => today()->subDay()->toDateString(),
                'TrangThai' => 'Hoạt động',
            ],
            [
                'KhuyenMaiID' => 3,
                'MaKhuyenMai' => 'MARKED',
                'TenKhuyenMai' => 'Đánh dấu hết hạn',
                'LoaiKhuyenMai' => 'Tiền mặt',
                'GiaTriGiam' => 500,
                'NgayBatDau' => today()->subDays(20)->toDateString(),
                'NgayKetThuc' => null,
                'TrangThai' => 'Hết hạn',
            ],
            [
                'KhuyenMaiID' => 4,
                'MaKhuyenMai' => 'PAUSED',
                'TenKhuyenMai' => 'Đang tạm ngưng',
                'LoaiKhuyenMai' => 'Tiền mặt',
                'GiaTriGiam' => 500,
                'NgayBatDau' => today()->subDays(5)->toDateString(),
                'NgayKetThuc' => null,
                'TrangThai' => 'Tạm ngưng',
            ],
        ]);

        $this->assertSame(['Hoạt động', 'Tạm ngưng', 'Hết hạn'], array_keys(KhuyenMai::statusOptions()));
        $this->assertSame(
            [3, 2],
            app(PromotionService::class)->getAll(['status' => 'Hết hạn'])->getCollection()->pluck('KhuyenMaiID')->values()->all()
        );
        $this->assertSame(
            [1],
            app(PromotionService::class)->getAll(['status' => 'Hoạt động'])->getCollection()->pluck('KhuyenMaiID')->values()->all()
        );

        $html = view('admin.promotions.index', [
            'promotions' => app(PromotionService::class)->getAll(),
            'statuses' => KhuyenMai::statusOptions(),
        ])->render();

        $this->assertStringContainsString('<option value="Hết hạn" ', $html);
        $this->assertStringContainsString('Còn chạy', $html);
        $this->assertStringContainsString('Đánh dấu hết hạn', $html);
        $this->assertStringNotContainsString('Đã hết hạn', $html);
        $this->assertSame(2, substr_count($html, 'fa-hourglass'));
    }

    public function test_suspended_unused_promotion_can_be_permanently_deleted(): void
    {
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 1,
            'MaKhuyenMai' => 'PAUSED10',
            'TenKhuyenMai' => 'Khuyến mãi tạm ngưng',
            'LoaiKhuyenMai' => 'Phần trăm',
            'GiaTriGiam' => 10,
            'NgayBatDau' => '2026-10-01',
            'NgayKetThuc' => '2026-10-31',
            'TrangThai' => 'Tạm ngưng',
        ]);
        $promotion = KhuyenMai::query()->findOrFail(1);

        $this->assertTrue(app(PromotionService::class)->delete($promotion));
        $this->assertDatabaseMissing('KhuyenMai', ['KhuyenMaiID' => 1]);
    }

    public function test_suspended_used_promotion_can_be_permanently_deleted_without_losing_order_discount(): void
    {
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 1,
            'MaKhuyenMai' => 'PAUSED10',
            'TenKhuyenMai' => 'Khuyến mãi tạm ngưng',
            'LoaiKhuyenMai' => 'Phần trăm',
            'GiaTriGiam' => 10,
            'SoLuongSuDung' => 5,
            'NgayBatDau' => today()->toDateString(),
            'NgayKetThuc' => today()->addDays(30)->toDateString(),
            'TrangThai' => 'Tạm ngưng',
        ]);
        DB::table('Booking')->insert(['BookingID' => 3, 'KhuyenMaiID' => 1, 'KhuyenMaiDaTru' => true]);
        DB::table('DonHang')->insert([
            'DonHangID' => 4,
            'BookingID' => 3,
            'KhuyenMaiID' => 1,
            'TienGiamKhuyenMai' => 15000,
            'TrangThai' => 'Đã tiếp nhận',
        ]);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER restore_booking_promotion_before_order_update
            AFTER UPDATE OF KhuyenMaiID ON DonHang
            FOR EACH ROW
            WHEN NEW.KhuyenMaiID IS NULL
                AND (SELECT KhuyenMaiID FROM Booking WHERE BookingID = NEW.BookingID) IS NOT NULL
            BEGIN
                UPDATE DonHang
                SET KhuyenMaiID = (SELECT KhuyenMaiID FROM Booking WHERE BookingID = NEW.BookingID)
                WHERE DonHangID = NEW.DonHangID;
            END;
        SQL);
        $promotion = KhuyenMai::query()->findOrFail(1);

        $this->assertTrue(app(PromotionService::class)->delete($promotion));
        $this->assertDatabaseMissing('KhuyenMai', ['KhuyenMaiID' => 1]);
        $this->assertDatabaseHas('DonHang', [
            'DonHangID' => 4,
            'KhuyenMaiID' => null,
            'TienGiamKhuyenMai' => 15000,
        ]);
        $this->assertDatabaseHas('Booking', [
            'BookingID' => 3,
            'KhuyenMaiID' => null,
            'KhuyenMaiDaTru' => false,
        ]);
    }

    public function test_expired_used_promotion_can_be_deleted_and_order_discount_is_preserved(): void
    {
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 1,
            'MaKhuyenMai' => 'USED10',
            'TenKhuyenMai' => 'Khuyến mãi hết hạn',
            'LoaiKhuyenMai' => 'Phần trăm',
            'GiaTriGiam' => 10,
            'SoLuongSuDung' => 5,
            'NgayBatDau' => today()->subDays(30)->toDateString(),
            'NgayKetThuc' => today()->subDay()->toDateString(),
            'TrangThai' => 'Hết hạn',
        ]);
        DB::table('Booking')->insert(['BookingID' => 3, 'KhuyenMaiID' => 1, 'KhuyenMaiDaTru' => true]);
        DB::table('DonHang')->insert([
            'DonHangID' => 4,
            'KhuyenMaiID' => 1,
            'TienGiamKhuyenMai' => 15000,
            'TrangThai' => 'Đã tiếp nhận',
        ]);
        $promotion = KhuyenMai::query()->findOrFail(1);

        $this->assertTrue(app(PromotionService::class)->delete($promotion));
        $this->assertDatabaseMissing('KhuyenMai', ['KhuyenMaiID' => 1]);
        $this->assertDatabaseHas('DonHang', [
            'DonHangID' => 4,
            'KhuyenMaiID' => null,
            'TienGiamKhuyenMai' => 15000,
        ]);
        $this->assertDatabaseHas('Booking', [
            'BookingID' => 3,
            'KhuyenMaiID' => null,
            'KhuyenMaiDaTru' => false,
        ]);
    }

    public function test_exhausted_active_promotion_is_suspended_instead_of_deleted(): void
    {
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 1,
            'MaKhuyenMai' => 'EMPTY10',
            'TenKhuyenMai' => 'Khuyến mãi hết lượt',
            'LoaiKhuyenMai' => 'Phần trăm',
            'GiaTriGiam' => 10,
            'SoLuongSuDung' => 0,
            'NgayBatDau' => today()->toDateString(),
            'NgayKetThuc' => today()->addDays(30)->toDateString(),
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('Booking')->insert(['BookingID' => 3, 'KhuyenMaiID' => 1, 'KhuyenMaiDaTru' => true]);
        $promotion = KhuyenMai::query()->findOrFail(1);

        $this->assertFalse(app(PromotionService::class)->delete($promotion));
        $this->assertDatabaseHas('KhuyenMai', ['KhuyenMaiID' => 1, 'TrangThai' => 'Tạm ngưng']);
        $this->assertDatabaseHas('Booking', [
            'BookingID' => 3,
            'KhuyenMaiID' => 1,
            'KhuyenMaiDaTru' => true,
        ]);
    }

    public function test_active_promotion_past_end_date_without_references_is_suspended_instead_of_deleted(): void
    {
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 1,
            'MaKhuyenMai' => 'ACTIVE10',
            'TenKhuyenMai' => 'Khuyến mãi đang chạy',
            'LoaiKhuyenMai' => 'Phần trăm',
            'GiaTriGiam' => 10,
            'NgayBatDau' => today()->subDays(30)->toDateString(),
            'NgayKetThuc' => today()->subDay()->toDateString(),
            'TrangThai' => 'Hoạt động',
        ]);
        $promotion = KhuyenMai::query()->findOrFail(1);

        $this->assertFalse(app(PromotionService::class)->delete($promotion));
        $this->assertDatabaseHas('KhuyenMai', ['KhuyenMaiID' => 1, 'TrangThai' => 'Tạm ngưng']);
    }

    public function test_used_active_promotion_with_remaining_uses_is_suspended_without_deleting_it(): void
    {
        DB::table('KhuyenMai')->insert([
            'KhuyenMaiID' => 1,
            'MaKhuyenMai' => 'ACTIVE10',
            'TenKhuyenMai' => 'Khuyến mãi đang chạy',
            'LoaiKhuyenMai' => 'Phần trăm',
            'GiaTriGiam' => 10,
            'SoLuongSuDung' => 5,
            'NgayBatDau' => '2026-10-01',
            'NgayKetThuc' => '2026-12-31',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('DonHang')->insert(['DonHangID' => 5, 'KhuyenMaiID' => 1, 'TrangThai' => 'Đã tiếp nhận']);
        $promotion = KhuyenMai::query()->findOrFail(1);

        $this->assertFalse(app(PromotionService::class)->delete($promotion));
        $this->assertDatabaseHas('KhuyenMai', ['KhuyenMaiID' => 1, 'TrangThai' => 'Tạm ngưng']);
    }
}
