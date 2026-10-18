<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MessageService;
use App\Support\PermissionCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
            Schema::create($name, function (Blueprint $t) use ($id, $name): void {
                $t->increments($id);
                $t->string('HoTen');
                if ($name === 'KhachHang') {
                    $t->string('SoDienThoai')->nullable();
                }
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
        Schema::create('DonHang', function (Blueprint $t): void {
            $t->increments('DonHangID');
            $t->dateTime('NgayTao')->nullable();
            $t->string('MaDonHang')->nullable();
            $t->integer('KhachHangID')->nullable();
        });
        Schema::create('TinNhan', function (Blueprint $t): void {
            $t->increments('TinNhanID');
            $t->integer('DonHangID')->nullable();
            $t->integer('NguoiGuiID');
            $t->integer('NguoiNhanID');
            $t->text('NoiDung');
            $t->text('NoiDungMaHoa')->nullable();
            $t->text('NoiDungNonce')->nullable();
            $t->integer('MaHoaPhienBan')->nullable();
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

    public function test_encrypted_support_message_is_decrypted_for_the_staff_view(): void
    {
        $key = random_bytes(32);
        $nonce = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt(
            'Cửa hàng sẽ phản hồi sớm.',
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
        );
        $this->assertIsString($cipherText);
        DB::table('TinNhan')->insert([
            'NguoiGuiID' => 2,
            'NguoiNhanID' => 1,
            'NoiDung' => '',
            'NoiDungMaHoa' => base64_encode($cipherText.$tag),
            'NoiDungNonce' => base64_encode($nonce),
            'MaHoaPhienBan' => 1,
        ]);
        config([
            'services.supabase.project_url' => 'https://example.supabase.co',
            'services.supabase.service_role_key' => 'test-service-role-key',
        ]);
        Http::fake([
            'https://example.supabase.co/rest/v1/rpc/get_chat_encryption_key' => Http::response(
                json_encode(base64_encode($key), JSON_THROW_ON_ERROR),
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $this->actingAs(User::findOrFail(1))->getJson('/admin/messages/support/2/updates')
            ->assertOk()
            ->assertJsonPath('messages.0.content', 'Cửa hàng sẽ phản hồi sớm.');
    }

    public function test_order_inbox_promotes_old_order_and_read_receipt_is_scoped_and_persistent(): void
    {
        DB::table('DonHang')->where('DonHangID', 1)->update(['NgayTao' => '2026-10-01 00:00:00', 'MaDonHang' => 'DH1']);
        DB::table('DonHang')->insert(['DonHangID' => 2, 'NgayTao' => '2026-10-08 00:00:00', 'MaDonHang' => 'DH2']);
        $this->actingAs(User::findOrFail(1));
        $this->getJson('/admin/messages/inbox')->assertOk()->assertJsonPath('orders.0.id', 2);
        $incoming = $this->message(2, 1, 1);
        $other = $this->message(2, 1, 2);
        DB::table('TinNhan')->where('TinNhanID', $other)->update(['ThoiGianGui' => '2026-10-08 12:00:00']);
        $outgoing = $this->message(1, 2, 1);
        $support = $this->message(2, 1);
        $this->getJson('/admin/messages/inbox')->assertJsonPath('orders.0.id', 1)->assertJsonPath('orders.0.unread', true);
        $this->assertDatabaseHas('TinNhan', ['TinNhanID' => $incoming, 'TrangThai' => 'Đã gửi']);
        $receipt = ['order_id' => 1, 'message_ids' => [$incoming, $other, $outgoing, $support]];
        $this->postJson('/admin/messages/read', $receipt)->assertOk()->assertJsonPath('updated', 1);
        foreach ([$other, $outgoing, $support] as $id) {
            $this->assertDatabaseHas('TinNhan', ['TinNhanID' => $id, 'TrangThai' => 'Đã gửi']);
        }
        $this->postJson('/admin/messages/read', $receipt)->assertJsonPath('updated', 0);
        $this->getJson('/admin/messages/inbox')->assertJsonPath('orders.0.unread', false)->assertJsonPath('orders.1.unread', true);
        $this->message(2, 1, 1);
        $this->getJson('/admin/messages/inbox')->assertJsonPath('orders.0.unread', true);
    }

    public function test_inbox_search_and_previews_stay_with_their_conversations(): void
    {
        DB::table('KhachHang')->where('KhachHangID', 1)->update(['SoDienThoai' => '0901111222']);
        DB::table('DonHang')->where('DonHangID', 1)->update(['MaDonHang' => 'DH-AN', 'KhachHangID' => 1]);
        DB::table('DonHang')->insert(['DonHangID' => 2, 'MaDonHang' => 'DH-BINH', 'KhachHangID' => 2]);
        DB::table('TinNhan')->insert([
            ['NguoiGuiID' => 2, 'NguoiNhanID' => 1, 'DonHangID' => 1, 'NoiDung' => 'Tin đơn An', 'ThoiGianGui' => '2026-10-09 12:00:00'],
            ['NguoiGuiID' => 3, 'NguoiNhanID' => 1, 'DonHangID' => 2, 'NoiDung' => 'Tin đơn Bình', 'ThoiGianGui' => '2026-10-09 13:00:00'],
            ['NguoiGuiID' => 2, 'NguoiNhanID' => 1, 'DonHangID' => null, 'NoiDung' => 'Tư vấn An', 'ThoiGianGui' => '2026-10-09 14:00:00'],
        ]);

        $service = app(MessageService::class);
        $orders = $service->getOrders('0901111222');
        $this->assertSame(1, $orders->total());
        $this->assertSame('Tin đơn An', $orders->first()->last_message_preview);
        $this->assertSame(1, $service->getOrders('dh-an')->total());

        $customers = $service->supportCustomers('Nguyễn An');
        $this->assertSame(1, $customers->total());
        $this->assertSame('Tư vấn An', $customers->first()->last_message_preview);
        $this->assertSame(1, $service->supportCustomers('0901111222')->total());
    }

    public function test_opening_recent_window_clears_older_unread_history_without_touching_later_arrivals(): void
    {
        $old = $this->message(2, 1, 1);
        for ($i = 0; $i < 100; $i++) {
            $boundary = $this->message(1, 2, 1);
        }
        $later = $this->message(2, 1, 1);
        $this->actingAs(User::findOrFail(1))->postJson('/admin/messages/read', [
            'order_id' => 1, 'message_ids' => [$boundary],
        ])->assertOk()->assertJsonPath('updated', 1);
        $this->assertDatabaseHas('TinNhan', ['TinNhanID' => $old, 'TrangThai' => 'Đã đọc']);
        $this->assertDatabaseHas('TinNhan', ['TinNhanID' => $later, 'TrangThai' => 'Đã gửi']);
    }

    public function test_read_receipts_require_staff_permission_and_do_not_mark_new_undisplayed_messages(): void
    {
        $seen = $this->message(2, 1, 1);
        $new = $this->message(2, 1, 1);
        $body = ['order_id' => 1, 'message_ids' => [$seen]];
        $this->postJson('/admin/messages/read', $body)->assertUnauthorized();
        $this->actingAs(User::findOrFail(2))->postJson('/admin/messages/read', $body)->assertForbidden();
        $this->actingAs(User::findOrFail(1))->postJson('/admin/messages/read', $body)->assertOk();
        $this->assertDatabaseHas('TinNhan', ['TinNhanID' => $new, 'TrangThai' => 'Đã gửi']);
        $this->postJson('/admin/messages/read', ['order_id' => 999, 'message_ids' => [$new]])->assertNotFound();
        $this->postJson('/admin/messages/read', ['order_id' => 1, 'customer_id' => 2, 'message_ids' => [$new]])->assertUnprocessable();
        DB::table('TaiKhoan_VaiTro')->delete();
        PermissionCache::forgetAll();
        $this->postJson('/admin/messages/read', $body)->assertForbidden();
        $this->getJson('/admin/messages/inbox')->assertForbidden();
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
            ->assertRedirect()->assertSessionMissing('success');
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
        DB::table('TaiKhoan_VaiTro')->where('VaiTroID', 1)->delete();
        $this->actingAs(User::findOrFail(1))->getJson('/admin/messages/support/2/updates')->assertForbidden();
        $this->postJson('/admin/messages', ['customer_id' => 2, 'content' => 'Hello'])->assertForbidden();
    }
}
