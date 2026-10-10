<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SystemLogController;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\NhatKyHeThong;
use App\Models\ThongBao;
use App\Models\TinNhan;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminCommunicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->string('TenDangNhap');
            $table->string('MatKhau')->nullable();
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
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

        Schema::create('TaiKhoan_VaiTro', function (Blueprint $table): void {
            $table->unsignedInteger('TaiKhoanID');
            $table->unsignedInteger('VaiTroID');
            $table->primary(['TaiKhoanID', 'VaiTroID']);
        });

        Schema::create('VaiTro_Quyen', function (Blueprint $table): void {
            $table->unsignedInteger('VaiTroID');
            $table->unsignedInteger('QuyenID');
            $table->primary(['VaiTroID', 'QuyenID']);
        });

        Schema::create('ThongBao', function (Blueprint $table): void {
            $table->increments('ThongBaoID');
            $table->unsignedInteger('TaiKhoanID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->unsignedInteger('TinNhanID')->nullable();
            $table->string('LoaiThongBao')->nullable();
            $table->string('TieuDe');
            $table->string('NoiDung');
            $table->dateTime('ThoiGianGui')->useCurrent();
            $table->boolean('DaDoc')->default(false);
        });

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang');
            $table->unsignedInteger('KhachHangID');
            $table->string('TrangThai');
            $table->decimal('TongTien', 18, 2)->default(0);
            $table->dateTime('NgayTao')->useCurrent();
        });

        Schema::create('TinNhan', function (Blueprint $table): void {
            $table->increments('TinNhanID');
            $table->unsignedInteger('NguoiGuiID');
            $table->unsignedInteger('NguoiNhanID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->string('NoiDung', 1000);
            $table->dateTime('ThoiGianGui')->useCurrent();
            $table->string('TrangThai')->default('Đã gửi');
        });

        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->unsignedInteger('TaiKhoanID')->nullable();
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedBigInteger('BanGhiID')->nullable();
            $table->json('DuLieuCu')->nullable();
            $table->json('DuLieuMoi')->nullable();
            $table->string('LyDo')->nullable();
            $table->dateTime('ThoiGian')->useCurrent();
            $table->string('IPAddress')->nullable();
            $table->text('UserAgent')->nullable();
        });
    }

    public function test_store_message_uses_linked_customer_account_and_keeps_chat_out_of_audit_log(): void
    {
        $staff = $this->createAccount('store-staff');
        $customerAccount = $this->createAccount('laundry-customer', 18);
        $customer = KhachHang::query()->create([
            'KhachHangID' => 18,
            'HoTen' => 'Khách hàng thử nghiệm',
        ]);
        $order = DonHang::query()->create([
            'MaDonHang' => 'DH001',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => 'Chờ tiếp nhận',
        ]);
        $auditCountBeforeMessage = NhatKyHeThong::query()->count();

        $message = app(MessageService::class)->sendFromStore($order, $staff, ' Xin chào khách hàng! ');

        $this->assertSame($staff->TaiKhoanID, $message->NguoiGuiID);
        $this->assertSame($customerAccount->TaiKhoanID, $message->NguoiNhanID);
        $this->assertSame($order->DonHangID, $message->DonHangID);
        $this->assertSame('Xin chào khách hàng!', $message->NoiDung);
        $this->assertSame('Đã gửi', $message->TrangThai);
        $this->assertNotNull($message->ThoiGianGui);
        $incoming = TinNhan::query()->create([
            'NguoiGuiID' => $customerAccount->TaiKhoanID,
            'NguoiNhanID' => $staff->TaiKhoanID,
            'DonHangID' => $order->DonHangID,
            'NoiDung' => 'Tôi sẽ ghé lấy đồ.',
            'ThoiGianGui' => now('UTC')->subMinute(),
            'TrangThai' => 'Đã gửi',
        ]);
        $this->assertSame(2, TinNhan::query()->count());
        $this->assertSame($auditCountBeforeMessage, NhatKyHeThong::query()->count());
        $this->assertSame([$incoming->TinNhanID, $message->TinNhanID], app(MessageService::class)
            ->getMessages($order)
            ->pluck('TinNhanID')
            ->all());
    }

    public function test_message_is_rejected_when_customer_has_no_linked_account(): void
    {
        $staff = $this->createAccount('store-staff');
        $customer = KhachHang::query()->create([
            'KhachHangID' => 18,
            'HoTen' => 'Khách hàng chưa đăng ký tài khoản',
        ]);
        $order = DonHang::query()->create([
            'MaDonHang' => 'DH001',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => 'Chờ tiếp nhận',
        ]);

        try {
            app(MessageService::class)->sendFromStore($order, $staff, 'Nội dung');
            $this->fail('Messages must not be stored without a customer account recipient.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('message', $exception->errors());
        }

        $this->assertSame(0, TinNhan::query()->count());
    }

    public function test_store_notification_saves_title_and_uses_server_send_time(): void
    {
        $account = $this->createAccount('notification-recipient');
        $expectedSentAt = now();

        $response = $this->actingAs($account)
            ->withoutMiddleware()
            ->post(route('notifications.store'), [
                'TaiKhoanID' => $account->TaiKhoanID,
                'LoaiThongBao' => 'system',
                'TieuDe' => 'Thông báo kiểm thử',
                'NoiDung' => 'Nội dung thông báo kiểm thử.',
                'ThoiGianGui' => '2000-01-01T00:00',
            ]);

        $response->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', 'Thông báo đã được tạo thành công.');

        $notification = ThongBao::query()->where('TieuDe', 'Thông báo kiểm thử')->firstOrFail();
        $this->assertSame($account->TaiKhoanID, $notification->TaiKhoanID);
        $this->assertSame($expectedSentAt->format('Y-m-d H:i'), $notification->ThoiGianGui->format('Y-m-d H:i'));
    }

    public function test_store_notification_can_send_to_customer_and_staff_groups(): void
    {
        $customer = $this->createAccount('notification-customer', 1);
        $staff = $this->createAccount('notification-staff');
        $unrelated = $this->createAccount('notification-unrelated');
        $this->assignRole($customer, 'Khách hàng');
        $staff->update(['NhanVienID' => 1]);
        $this->assignRole($staff, 'Nhân viên');
        $this->actingAs($staff)->withoutMiddleware();

        $this->post(route('notifications.store'), [
            'recipient' => 'all_customers',
            'TieuDe' => 'Thông báo khách hàng',
            'NoiDung' => 'Nội dung khách hàng.',
        ])->assertRedirect(route('notifications.index'));

        $this->assertSame(
            [$customer->TaiKhoanID],
            ThongBao::query()->where('TieuDe', 'Thông báo khách hàng')->pluck('TaiKhoanID')->all(),
        );

        $this->post(route('notifications.store'), [
            'recipient' => 'all_staff',
            'TieuDe' => 'Thông báo nhân viên',
            'NoiDung' => 'Nội dung nhân viên.',
        ])->assertRedirect(route('notifications.index'));

        $this->assertSame(
            [$staff->TaiKhoanID],
            ThongBao::query()->where('TieuDe', 'Thông báo nhân viên')->pluck('TaiKhoanID')->all(),
        );

        $this->post(route('notifications.store'), [
            'recipient' => 'all',
            'TieuDe' => 'Thông báo toàn hệ thống',
            'NoiDung' => 'Nội dung toàn hệ thống.',
        ])->assertRedirect(route('notifications.index'));

        $allRecipientIds = ThongBao::query()
            ->where('TieuDe', 'Thông báo toàn hệ thống')
            ->orderBy('TaiKhoanID')
            ->pluck('TaiKhoanID')
            ->all();
        $expectedIds = [$customer->TaiKhoanID, $staff->TaiKhoanID, $unrelated->TaiKhoanID];
        sort($expectedIds);
        $this->assertSame($expectedIds, $allRecipientIds);

        $this->assertDatabaseMissing('ThongBao', [
            'TaiKhoanID' => $unrelated->TaiKhoanID,
            'TieuDe' => 'Thông báo khách hàng',
        ]);
    }

    public function test_notification_forms_do_not_allow_manual_send_time(): void
    {
        $account = $this->createAccount('notification-form-user');
        $notification = ThongBao::query()->create([
            'TaiKhoanID' => $account->TaiKhoanID,
            'TieuDe' => 'Thông báo cần sửa',
            'NoiDung' => 'Nội dung.',
        ]);

        $this->actingAs($account)
            ->withoutMiddleware()
            ->withViewErrors([])
            ->get(route('notifications.create'))
            ->assertOk()
            ->assertSee('name="TieuDe"', false)
            ->assertSee('all_customers')
            ->assertSee('all_staff')
            ->assertDontSee('name="ThoiGianGui"', false);

        $this->get(route('notifications.edit', $notification->ThongBaoID))
            ->assertOk()
            ->assertSee('name="TieuDe"', false)
            ->assertDontSee('name="ThoiGianGui"', false);
    }

    public function test_mark_all_read_updates_every_notification_for_current_account_only(): void
    {
        $currentAccount = $this->createAccount('notification-current');
        $otherAccount = $this->createAccount('notification-other');

        $currentUnread = ThongBao::query()->create([
            'TaiKhoanID' => $currentAccount->TaiKhoanID,
            'TieuDe' => 'Chưa đọc của tôi',
            'NoiDung' => 'Thông báo chưa đọc.',
            'DaDoc' => false,
        ]);
        $alreadyRead = ThongBao::query()->create([
            'TaiKhoanID' => $currentAccount->TaiKhoanID,
            'TieuDe' => 'Đã đọc của tôi',
            'NoiDung' => 'Thông báo đã đọc.',
            'DaDoc' => true,
        ]);
        $otherUnread = ThongBao::query()->create([
            'TaiKhoanID' => $otherAccount->TaiKhoanID,
            'TieuDe' => 'Chưa đọc tài khoản khác',
            'NoiDung' => 'Không bị thay đổi.',
            'DaDoc' => false,
        ]);

        $this->actingAs($currentAccount)
            ->withoutMiddleware()
            ->post(route('notifications.mark-all-read'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Đã đánh dấu 1 thông báo là đã đọc.');

        $this->assertTrue($currentUnread->fresh()->DaDoc);
        $this->assertTrue($alreadyRead->fresh()->DaDoc);
        $this->assertFalse($otherUnread->fresh()->DaDoc);
    }

    public function test_system_log_screen_applies_table_action_account_and_date_filters(): void
    {
        $account = $this->createAccount('log-owner');
        $this->assignRole($account, 'Owner');
        $this->actingAs($account);
        NhatKyHeThong::query()->create([
            'TaiKhoanID' => $account->TaiKhoanID,
            'HanhDong' => 'Chuyển trạng thái đơn hàng',
            'BangDuLieu' => 'DonHang',
            'BanGhiID' => 7,
            'DuLieuCu' => ['TrangThai' => 'Chờ tiếp nhận'],
            'DuLieuMoi' => ['TrangThai' => 'Đã tiếp nhận'],
            'ThoiGian' => '2026-10-02 12:00:00',
        ]);
        NhatKyHeThong::query()->create([
            'TaiKhoanID' => $account->TaiKhoanID,
            'HanhDong' => 'Tạo Booking',
            'BangDuLieu' => 'Booking',
            'BanGhiID' => 8,
            'ThoiGian' => '2026-10-01 12:00:00',
        ]);

        $request = Request::create('/admin/system-logs', 'GET', [
            'table' => 'DonHang',
            'action' => 'trạng thái',
            'TaiKhoanID' => $account->TaiKhoanID,
            'from' => '2026-10-02',
            'to' => '2026-10-02',
        ]);
        $request->setUserResolver(fn () => $account);
        $view = app(SystemLogController::class)->index($request);
        $logs = $view->getData()['logs'];

        $this->assertSame('admin.system_logs.index', $view->name());
        $this->assertSame(1, $logs->total());
        $this->assertSame('DonHang', $logs->items()[0]->BangDuLieu);
        $this->assertSame('Chuyển trạng thái đơn hàng', $logs->items()[0]->HanhDong);
        $this->assertSame('log-owner', $view->getData()['accounts']->firstWhere('TaiKhoanID', $account->TaiKhoanID)->TenDangNhap);

        $html = $view->render();

        $this->assertStringContainsString('name="TaiKhoanID"', $html);
        $this->assertStringContainsString('log-owner (#'.$account->TaiKhoanID.')', $html);
        $this->assertStringContainsString('value="'.$account->TaiKhoanID.'" selected', $html);
        $this->assertStringContainsString('Bảng dữ liệu', $html);
    }

    public function test_owner_can_view_system_logs_and_sees_the_menu_link(): void
    {
        $owner = $this->createAccount('log-owner');
        $this->assignRole($owner, 'Owner');

        $this->actingAs($owner)
            ->get(route('admin.system-logs.index'))
            ->assertOk()
            ->assertSee('Nhật ký hệ thống');
    }

    public function test_manager_and_staff_cannot_open_system_logs_or_see_the_menu_link(): void
    {
        foreach (['Quản lý', 'Nhân viên'] as $index => $roleName) {
            $user = $this->createAccount('restricted-'.$index);
            $this->assignRole($user, $roleName);

            $response = $this->actingAs($user)->get(route('admin.system-logs.index'));

            $response->assertForbidden();

            $this->actingAs($user)
                ->get(route('admin.messages.index'))
                ->assertOk()
                ->assertDontSee('Nhật ký hệ thống');
        }
    }

    public function test_notification_bell_shows_unread_dot_and_item_opens_its_detail(): void
    {
        $staff = $this->createAccount('notification-staff');
        $this->assignRole($staff, 'Owner');
        $roleId = DB::table('TaiKhoan_VaiTro')
            ->where('TaiKhoanID', $staff->TaiKhoanID)
            ->value('VaiTroID');
        $permissionId = DB::table('Quyen')->insertGetId([
            'MaQuyen' => 'NOTIFICATIONS_VIEW',
            'TrangThai' => 'Hoạt động',
        ], 'QuyenID');
        DB::table('VaiTro_Quyen')->insert([
            'VaiTroID' => $roleId,
            'QuyenID' => $permissionId,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertDontSee('navbar-action-badge');

        $notification = ThongBao::query()->create([
            'TaiKhoanID' => $staff->TaiKhoanID,
            'LoaiThongBao' => 'system',
            'TieuDe' => 'Có yêu cầu đặt giặt mới',
            'NoiDung' => 'Khách vừa tạo một yêu cầu đặt giặt.',
            'ThoiGianGui' => now(),
            'DaDoc' => false,
        ]);

        $this->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('navbar-action-badge')
            ->assertSee(route('notifications.show', $notification->ThongBaoID), false);

        $this->get(route('notifications.show', $notification->ThongBaoID))
            ->assertOk()
            ->assertSee('Nội dung thông báo')
            ->assertSee($notification->TieuDe);

        $this->assertTrue($notification->fresh()->DaDoc);

        $this->get(route('admin.messages.index'))
            ->assertOk()
            ->assertDontSee('navbar-action-badge');
    }

    public function test_customer_message_notification_links_to_the_matching_chat_thread(): void
    {
        $staff = $this->createAccount('notification-staff');
        $this->assignRole($staff, 'Owner');
        $roleId = DB::table('TaiKhoan_VaiTro')
            ->where('TaiKhoanID', $staff->TaiKhoanID)
            ->value('VaiTroID');
        $notificationPermissionId = DB::table('Quyen')->insertGetId([
            'MaQuyen' => 'NOTIFICATIONS_VIEW',
            'TrangThai' => 'Hoạt động',
        ], 'QuyenID');
        DB::table('VaiTro_Quyen')->insert([
            'VaiTroID' => $roleId,
            'QuyenID' => $notificationPermissionId,
        ]);

        $customer = $this->createAccount('chat-customer', 18);
        $supportMessage = TinNhan::query()->create([
            'NguoiGuiID' => $customer->TaiKhoanID,
            'NguoiNhanID' => $staff->TaiKhoanID,
            'NoiDung' => 'Tôi muốn hỏi về dịch vụ.',
        ]);
        $supportNotification = ThongBao::query()->create([
            'TaiKhoanID' => $staff->TaiKhoanID,
            'TieuDe' => 'Tin nhắn hỗ trợ mới',
            'NoiDung' => 'Khách hàng đã gửi tin nhắn.',
        ]);
        DB::table('ThongBao')
            ->where('ThongBaoID', $supportNotification->ThongBaoID)
            ->update(['TinNhanID' => $supportMessage->TinNhanID]);

        $this->actingAs($staff)
            ->get(route('notifications.show', $supportNotification->ThongBaoID))
            ->assertOk()
            ->assertSee('Nhắn tin khách hàng')
            ->assertSee(route('admin.messages.index', ['customer_id' => $customer->TaiKhoanID]), false);

        $order = DonHang::query()->create([
            'MaDonHang' => 'DH002',
            'KhachHangID' => 18,
            'TrangThai' => 'Chờ tiếp nhận',
        ]);
        $orderMessage = TinNhan::query()->create([
            'NguoiGuiID' => $customer->TaiKhoanID,
            'NguoiNhanID' => $staff->TaiKhoanID,
            'DonHangID' => $order->DonHangID,
            'NoiDung' => 'Tôi sẽ mang đồ đến.',
        ]);
        $orderNotification = ThongBao::query()->create([
            'TaiKhoanID' => $staff->TaiKhoanID,
            'TieuDe' => 'Tin nhắn về đơn hàng',
            'NoiDung' => 'Khách hàng đã gửi tin nhắn về đơn hàng.',
        ]);
        DB::table('ThongBao')
            ->where('ThongBaoID', $orderNotification->ThongBaoID)
            ->update(['TinNhanID' => $orderMessage->TinNhanID]);

        $this->get(route('notifications.show', $orderNotification->ThongBaoID))
            ->assertOk()
            ->assertSee('Nhắn tin khách hàng')
            ->assertSee(route('admin.messages.index', ['order_id' => $order->DonHangID]), false);

        $staffMessage = TinNhan::query()->create([
            'NguoiGuiID' => $staff->TaiKhoanID,
            'NguoiNhanID' => $customer->TaiKhoanID,
            'NoiDung' => 'Cửa hàng đã phản hồi.',
        ]);
        $staffNotification = ThongBao::query()->create([
            'TaiKhoanID' => $staff->TaiKhoanID,
            'TieuDe' => 'Phản hồi cửa hàng',
            'NoiDung' => 'Nhân viên đã gửi tin nhắn.',
        ]);
        DB::table('ThongBao')
            ->where('ThongBaoID', $staffNotification->ThongBaoID)
            ->update(['TinNhanID' => $staffMessage->TinNhanID]);

        $this->get(route('notifications.show', $staffNotification->ThongBaoID))
            ->assertOk()
            ->assertDontSee('Nhắn tin khách hàng');
    }

    public function test_system_log_route_is_protected_by_dynamic_permission_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.system-logs.index');

        $this->assertContains('permission:system_logs.view', $route->getAction('middleware'));
        $this->assertNotContains('role:manager|admin', $route->getAction('middleware'));
        $this->assertNotContains('role:admin', $route->getAction('middleware'));
    }

    public function test_mark_all_read_route_is_available_to_notification_viewers(): void
    {
        $route = app('router')->getRoutes()->getByName('notifications.mark-all-read');

        $this->assertContains('permission:notifications.view', $route->getAction('middleware'));
    }

    private function createAccount(string $username, ?int $customerId = null): User
    {
        return User::query()->create([
            'TenDangNhap' => $username,
            'MatKhau' => 'test-password',
            'KhachHangID' => $customerId,
            'TrangThai' => 'Hoạt động',
        ]);
    }

    private function assignRole(User $user, string $roleName): void
    {
        $roleId = DB::table('VaiTro')->insertGetId([
            'TenVaiTro' => $roleName,
            'TrangThai' => 'Hoạt động',
        ], 'VaiTroID');

        DB::table('TaiKhoan_VaiTro')->insert([
            'TaiKhoanID' => $user->TaiKhoanID,
            'VaiTroID' => $roleId,
        ]);

        $messagePermissionId = DB::table('Quyen')->insertGetId([
            'MaQuyen' => 'MESSAGES_VIEW',
            'TrangThai' => 'Hoạt động',
        ], 'QuyenID');

        DB::table('VaiTro_Quyen')->insert([
            'VaiTroID' => $roleId,
            'QuyenID' => $messagePermissionId,
        ]);
    }
}
