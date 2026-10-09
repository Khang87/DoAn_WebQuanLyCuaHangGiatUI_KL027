<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationUpdatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->travelTo(now()->setDate(2026, 10, 9)->setTime(12, 0));
        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->string('TenDangNhap');
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('VaiTro', function (Blueprint $table): void {
            $table->increments('VaiTroID');
            $table->string('TenVaiTro');
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('Quyen', function (Blueprint $table): void {
            $table->increments('QuyenID');
            $table->string('MaQuyen');
            $table->string('TrangThai')->default('Hoạt động');
        });
        foreach (['TaiKhoan_VaiTro' => 'TaiKhoanID', 'VaiTro_Quyen' => 'QuyenID'] as $name => $key) {
            Schema::create($name, function (Blueprint $table) use ($key): void {
                $table->integer('VaiTroID');
                $table->integer($key);
            });
        }
        Schema::create('ThongBao', function (Blueprint $table): void {
            $table->increments('ThongBaoID');
            $table->integer('TaiKhoanID');
            $table->integer('DonHangID')->nullable();
            $table->string('LoaiThongBao')->nullable();
            $table->string('TieuDe');
            $table->text('NoiDung');
            $table->dateTime('ThoiGianGui');
            $table->boolean('DaDoc')->default(false);
        });
        DB::table('TaiKhoan')->insert([
            ['TaiKhoanID' => 1, 'TenDangNhap' => 'Staff'],
            ['TaiKhoanID' => 2, 'TenDangNhap' => 'Other user'],
        ]);
        DB::table('VaiTro')->insert(['VaiTroID' => 1, 'TenVaiTro' => 'Nhân viên']);
        DB::table('Quyen')->insert(['QuyenID' => 1, 'MaQuyen' => 'NOTIFICATION_VIEW']);
        DB::table('TaiKhoan_VaiTro')->insert(['TaiKhoanID' => 1, 'VaiTroID' => 1]);
        DB::table('VaiTro_Quyen')->insert(['VaiTroID' => 1, 'QuyenID' => 1]);
    }

    private function notification(array $overrides = []): int
    {
        return DB::table('ThongBao')->insertGetId(array_merge([
            'TaiKhoanID' => 1, 'TieuDe' => 'Đơn hàng mới', 'NoiDung' => 'Private notification body',
            'ThoiGianGui' => now()->toDateTimeString(), 'DaDoc' => false,
        ], $overrides), 'ThongBaoID');
    }

    private function staff(): User
    {
        return User::findOrFail(1);
    }

    public function test_updates_are_private_recipient_scoped_and_contain_only_display_fields(): void
    {
        $id = $this->notification(['TieuDe' => '<img src=x onerror=alert(1)>']);
        $this->notification(['TaiKhoanID' => 2]);
        $response = $this->actingAs($this->staff())->getJson('/notifications/updates?user_id=2')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.id', $id)
            ->assertJsonPath('notifications.0.title', '<img src=x onerror=alert(1)>')
            ->assertJsonPath('notifications.0.is_read', false)
            ->assertJsonPath('notifications.0.url', route('notifications.show', $id));
        $this->assertEqualsCanonicalizing(['unread_count', 'notifications'], array_keys($response->json()));
        $this->assertEqualsCanonicalizing(['id', 'title', 'is_read', 'url', 'time'], array_keys($response->json('notifications.0')));
        $this->assertIsString($response->json('notifications.0.time'));
    }

    public function test_dropdown_returns_latest_five_but_count_includes_all_unread_notifications(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->notification(['ThoiGianGui' => now()->subMinutes(8 - $i)->toDateTimeString()]);
        }
        $read = $this->notification(['DaDoc' => true]);
        $this->actingAs($this->staff())->getJson('/notifications/updates')
            ->assertOk()->assertJsonPath('unread_count', 7)->assertJsonCount(5, 'notifications')
            ->assertJsonPath('notifications.0.id', $read)->assertJsonPath('notifications.0.is_read', true)
            ->assertJsonPath('notifications.4.id', 4);
    }

    public function test_expired_otp_is_excluded_from_both_dropdown_and_unread_count(): void
    {
        $this->notification(['LoaiThongBao' => 'internal_password_otp', 'ThoiGianGui' => now()->subMinutes(11)->toDateTimeString()]);
        $this->notification(['LoaiThongBao' => 'internal_password_otp', 'ThoiGianGui' => now()->subMinutes(10)->toDateTimeString()]);
        $active = $this->notification(['LoaiThongBao' => 'internal_password_otp', 'ThoiGianGui' => now()->subMinutes(9)->toDateTimeString()]);
        $this->notification(['ThoiGianGui' => now()->subDay()->toDateTimeString()]);
        $this->actingAs($this->staff())->getJson('/notifications/updates')
            ->assertOk()->assertJsonPath('unread_count', 2)->assertJsonCount(2, 'notifications')
            ->assertJsonPath('notifications.0.id', $active);
    }

    public function test_refresh_observes_new_notifications_without_marking_any_as_read(): void
    {
        $this->actingAs($this->staff());
        $this->getJson('/notifications/updates')->assertOk()->assertJsonPath('unread_count', 0);
        $id = $this->notification();
        $this->getJson('/notifications/updates')->assertOk()->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.id', $id);
        $this->getJson('/notifications/updates')->assertOk()->assertJsonCount(1, 'notifications');
        $this->assertEquals(0, DB::table('ThongBao')->where('ThongBaoID', $id)->value('DaDoc'));
    }

    public function test_guests_and_accounts_without_view_permission_cannot_read_updates(): void
    {
        $this->getJson('/notifications/updates')->assertUnauthorized();
        DB::table('VaiTro_Quyen')->delete();
        $this->actingAs($this->staff())->getJson('/notifications/updates')->assertForbidden();
    }
}
