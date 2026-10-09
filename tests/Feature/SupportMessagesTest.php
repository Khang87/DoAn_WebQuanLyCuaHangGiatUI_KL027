<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupportMessagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('TaiKhoan', function (Blueprint $t): void {
            $t->increments('TaiKhoanID');
            $t->string('TenDangNhap');
            $t->integer('KhachHangID')->nullable();
            $t->integer('NhanVienID')->nullable();
            $t->string('TrangThai')->default('Hoạt động');
        });
        foreach (['KhachHang' => 'KhachHangID', 'NhanVien' => 'NhanVienID'] as $name => $id) {
            Schema::create($name, function (Blueprint $t) use ($id): void {
                $t->increments($id);
                $t->string('HoTen');
                $t->string('TrangThai')->default('Hoạt động');
            });
        }
        Schema::create('VaiTro', function (Blueprint $t): void {
            $t->increments('VaiTroID');
            $t->string('TenVaiTro');
            $t->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('Quyen', function (Blueprint $t): void {
            $t->increments('QuyenID');
            $t->string('MaQuyen');
            $t->string('TrangThai')->default('Hoạt động');
        });
        foreach (['TaiKhoan_VaiTro' => 'TaiKhoanID', 'VaiTro_Quyen' => 'QuyenID'] as $name => $key) {
            Schema::create($name, function (Blueprint $t) use ($key): void {
                $t->integer('VaiTroID');
                $t->integer($key);
            });
        }
        Schema::create('DonHang', fn (Blueprint $t) => $t->increments('DonHangID'));
        Schema::create('TinNhan', function (Blueprint $t): void {
            $t->increments('TinNhanID');
            $t->integer('DonHangID')->nullable();
            $t->integer('NguoiGuiID');
            $t->integer('NguoiNhanID');
            $t->text('NoiDung');
            $t->dateTime('ThoiGianGui')->nullable();
            $t->string('TrangThai')->default('Đã gửi');
        });
        DB::table('NhanVien')->insert(['NhanVienID' => 1, 'HoTen' => 'Nhân viên cửa hàng']);
        DB::table('KhachHang')->insert([
            ['KhachHangID' => 1, 'HoTen' => 'Nguyễn An'],
            ['KhachHangID' => 2, 'HoTen' => 'Nguyễn Bình'],
        ]);
        DB::table('TaiKhoan')->insert([
            ['TaiKhoanID' => 1, 'TenDangNhap' => 'Staff', 'KhachHangID' => null, 'NhanVienID' => 1],
            ['TaiKhoanID' => 2, 'TenDangNhap' => 'auth-an', 'KhachHangID' => 1, 'NhanVienID' => null],
            ['TaiKhoanID' => 3, 'TenDangNhap' => 'auth-binh', 'KhachHangID' => 2, 'NhanVienID' => null],
        ]);
        DB::table('VaiTro')->insert([
            ['VaiTroID' => 1, 'TenVaiTro' => 'Nhân viên'],
            ['VaiTroID' => 2, 'TenVaiTro' => 'Khách hàng'],
        ]);
        DB::table('Quyen')->insert([
            ['QuyenID' => 1, 'MaQuyen' => 'MESSAGES_VIEW'],
            ['QuyenID' => 2, 'MaQuyen' => 'MESSAGES_CREATE'],
        ]);
        DB::table('TaiKhoan_VaiTro')->insert([
            ['TaiKhoanID' => 1, 'VaiTroID' => 1],
            ['TaiKhoanID' => 2, 'VaiTroID' => 2],
        ]);
        DB::table('VaiTro_Quyen')->insert([
            ['VaiTroID' => 1, 'QuyenID' => 1], ['VaiTroID' => 1, 'QuyenID' => 2],
            ['VaiTroID' => 2, 'QuyenID' => 1], ['VaiTroID' => 2, 'QuyenID' => 2],
        ]);
        DB::table('DonHang')->insert(['DonHangID' => 1]);
    }

    private function message(int $sender, int $recipient, ?int $order = null): int
    {
        return DB::table('TinNhan')->insertGetId([
            'NguoiGuiID' => $sender, 'NguoiNhanID' => $recipient, 'DonHangID' => $order,
            'NoiDung' => 'Hỏi thông tin trước khi đặt', 'ThoiGianGui' => '2026-10-09 12:00:00',
        ], 'TinNhanID');
    }

    public function test_support_updates_exclude_other_customers_and_order_bound_messages(): void
    {
        $id = $this->message(2, 1);
        $this->message(3, 1);
        $this->message(3, 2);
        $this->message(2, 1, 1);
        $this->actingAs(User::findOrFail(1))->getJson('/admin/messages/support/2/updates')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('order_id', null)->assertJsonPath('customer_account_id', 2)
            ->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.id', $id)
            ->assertJsonPath('messages.0.sender_name', 'Nguyễn An')
            ->assertJsonPath('messages.0.is_mine', false);
    }

    public function test_staff_can_send_support_message_without_an_order(): void
    {
        $this->actingAs(User::findOrFail(1))->post('/admin/messages', ['customer_id' => 2, 'content' => '  Cửa hàng xin chào  '])
            ->assertRedirect();
        $this->assertDatabaseHas('TinNhan', [
            'NguoiGuiID' => 1, 'NguoiNhanID' => 2, 'DonHangID' => null, 'NoiDung' => 'Cửa hàng xin chào',
        ]);
    }

    public function test_support_history_is_bounded_and_keeps_latest_messages_in_stable_order(): void
    {
        for ($i = 1; $i <= 105; $i++) {
            $this->message(1, 2);
        }
        $this->actingAs(User::findOrFail(1))->getJson('/admin/messages/support/2/updates')
            ->assertOk()->assertJsonCount(100, 'messages')->assertJsonPath('messages.0.id', 6)
            ->assertJsonPath('messages.99.id', 105)->assertJsonPath('messages.0.is_mine', true)
            ->assertJsonPath('messages.0.sender_name', 'Cửa hàng');
    }

    public function test_staff_or_locked_account_is_not_a_support_customer(): void
    {
        $this->actingAs(User::findOrFail(1))->getJson('/admin/messages/support/1/updates')->assertNotFound();
        DB::table('TaiKhoan')->where('TaiKhoanID', 2)->update(['TrangThai' => 'Khóa']);
        $this->getJson('/admin/messages/support/2/updates')->assertNotFound();
        $this->postJson('/admin/messages', ['customer_id' => 2, 'content' => 'Hello'])->assertNotFound();
    }

    public function test_blank_content_and_ambiguous_target_are_rejected_without_sending(): void
    {
        $this->actingAs(User::findOrFail(1));
        $this->postJson('/admin/messages', ['customer_id' => 2, 'content' => '   '])->assertUnprocessable();
        $this->postJson('/admin/messages', ['customer_id' => 2, 'order_id' => 1, 'content' => 'Hello'])->assertUnprocessable();
        $this->assertDatabaseCount('TinNhan', 0);
    }

    public function test_support_updates_and_send_keep_authentication_role_and_permission_boundaries(): void
    {
        $this->getJson('/admin/messages/support/2/updates')->assertUnauthorized();
        $this->postJson('/admin/messages', ['customer_id' => 2, 'content' => 'Hello'])->assertUnauthorized();
        $this->actingAs(User::findOrFail(2))->getJson('/admin/messages/support/3/updates')->assertForbidden();
        $this->postJson('/admin/messages', ['customer_id' => 3, 'content' => 'Hello'])->assertForbidden();
        DB::table('VaiTro_Quyen')->where('VaiTroID', 1)->delete();
        $this->actingAs(User::findOrFail(1))->getJson('/admin/messages/support/2/updates')->assertForbidden();
        $this->postJson('/admin/messages', ['customer_id' => 2, 'content' => 'Hello'])->assertForbidden();
    }
}
