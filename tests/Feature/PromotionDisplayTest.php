<?php

namespace Tests\Feature;

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
            $table->unsignedInteger('KhuyenMaiID')->nullable();
            $table->string('TrangThai');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('DonHang');
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
        $this->assertStringContainsString('Lưu khuyến mãi', $html);
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
        $this->assertStringContainsString('<th class="fw-bold text-dark">Số đơn liên kết</th>', $html);
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
}
