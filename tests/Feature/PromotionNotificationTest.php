<?php

namespace Tests\Feature;

use App\Services\PromotionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromotionNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
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
            $table->date('NgayKetThuc');
            $table->string('TrangThai');
        });

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('TrangThai');
        });

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->unsignedInteger('KhachHangID');
            $table->string('TrangThai');
        });

        Schema::create('ThongBao', function (Blueprint $table): void {
            $table->increments('ThongBaoID');
            $table->unsignedInteger('TaiKhoanID');
            $table->string('TieuDe');
            $table->string('NoiDung');
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER notify_customers_new_promotion
            AFTER INSERT ON KhuyenMai
            WHEN NEW.TrangThai = 'Hoạt động'
                AND NEW.NgayBatDau <= DATE('now')
                AND NEW.NgayKetThuc >= DATE('now')
            BEGIN
                INSERT INTO ThongBao (TaiKhoanID, TieuDe, NoiDung)
                SELECT account.TaiKhoanID, 'Có khuyến mãi mới', NEW.TenKhuyenMai
                FROM TaiKhoan AS account
                JOIN KhachHang AS customer ON customer.KhachHangID = account.KhachHangID
                WHERE account.TrangThai = 'Hoạt động'
                    AND customer.TrangThai = 'Hoạt động';
            END;
            SQL);
    }

    protected function tearDown(): void
    {
        foreach (['ThongBao', 'TaiKhoan', 'KhachHang', 'KhuyenMai'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_creating_active_promotion_does_not_fan_out_database_notifications(): void
    {
        foreach ([1, 2] as $customerId) {
            DB::table('KhachHang')->insert([
                'KhachHangID' => $customerId,
                'TrangThai' => 'Hoạt động',
            ]);
            DB::table('TaiKhoan')->insert([
                'KhachHangID' => $customerId,
                'TrangThai' => 'Hoạt động',
            ]);
        }

        $promotion = app(PromotionService::class)->create([
            'MaKhuyenMai' => 'KM-ONCE',
            'TenKhuyenMai' => 'Khuyến mãi kiểm thử',
            'LoaiKhuyenMai' => 'Tiền mặt',
            'GiaTriGiam' => 1000,
            'NgayBatDau' => today(),
            'NgayKetThuc' => today(),
            'TrangThai' => 'Hoạt động',
        ]);

        $this->assertSame('Hoạt động', $promotion->TrangThai);
        $this->assertSame(0, DB::table('ThongBao')->count());
    }
}
