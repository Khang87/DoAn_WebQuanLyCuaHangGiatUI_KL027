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
use App\Support\PermissionCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminCommunicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::purge('sqlite');

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->string('TenDangNhap');
            $table->string('MatKhau')->nullable();
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->string('AvatarURL')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });

        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('HoTen');
        });

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
            $table->string('SoDienThoai')->nullable();
            $table->string('AvatarUrl')->nullable();
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

        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->unsignedInteger('DonHangID');
            $table->string('TrangThai');
        });

        Schema::create('TinNhan', function (Blueprint $table): void {
            $table->increments('TinNhanID');
            $table->unsignedInteger('NguoiGuiID');
            $table->unsignedInteger('NguoiNhanID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->string('NoiDung', 1000);
            $table->text('NoiDungMaHoa')->nullable();
            $table->text('NoiDungNonce')->nullable();
            $table->integer('MaHoaPhienBan')->nullable();
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

    public function test_lazy_inbox_ssr_uses_the_json_dto_only_for_the_active_tab_and_keeps_legacy_pages(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        $this->actingAs($this->seedLazyInbox());
        foreach (['orders', 'support'] as $tab) {
            foreach ([1 => 20, 2 => 3, 3 => 0] as $page => $size) {
                $expected = $this->getJson('/admin/messages/inbox?'.http_build_query([
                    'tab' => $tab, 'q' => 'Lazy client', 'page' => $page,
                ]))->assertOk()->json();
                DB::enableQueryLog();
                DB::flushQueryLog();
                try {
                    $response = $this->get('/admin/messages?'.http_build_query([
                        'tab' => $tab, 'q' => 'Lazy client',
                        'page' => $tab === 'orders' ? $page : 99,
                        'support_page' => $tab === 'support' ? $page : 99,
                    ]))->assertOk()->assertViewIs('admin.messages.index')
                        ->assertViewHas('activeTab', $tab)->assertViewHas('selectedOrder', null)
                        ->assertViewHas('selectedCustomer', null)->assertViewHas('inboxPage', $expected);
                    $queries = array_column(DB::getQueryLog(), 'query');
                } finally {
                    DB::disableQueryLog();
                }
                $activeTable = $tab === 'orders' ? 'DonHang' : 'TaiKhoan';
                $inactiveTable = $tab === 'orders' ? 'TaiKhoan' : 'DonHang';
                $this->assertNotEmpty(array_filter($queries, fn ($sql): bool => str_contains($sql, 'select count(*) as "aggregate" from "'.$activeTable.'"')), 'The active inbox must be paginated.');
                $this->assertEmpty(array_filter($queries, fn ($sql): bool => str_contains($sql, 'select count(*) as "aggregate" from "'.$inactiveTable.'"')), 'The inactive inbox must not be paginated.');

                $xpath = $this->inboxXPath($response->getContent());
                $inbox = $xpath->query('//*[@data-message-inbox]')->item(0);
                $this->assertNotNull($inbox);
                $this->assertSame($tab, $inbox->getAttribute('data-inbox-tab'));
                $this->assertSame((string) $page, $inbox->getAttribute('data-inbox-page'));
                $this->assertSame($page === 1 ? '2' : '', $inbox->getAttribute('data-next-page'));
                parse_str(parse_url($inbox->getAttribute('data-message-inbox'), PHP_URL_QUERY), $inboxQuery);
                $this->assertSame(['tab' => $tab, 'q' => 'Lazy client'], $inboxQuery);
                $this->assertSame(1, $xpath->query('.//*[@data-inbox-rows]', $inbox)->length);
                $more = $xpath->query('.//button[@data-inbox-more]', $inbox)->item(0);
                $this->assertNotNull($more);
                $this->assertSame($page !== 1, $more->hasAttribute('hidden'));
                $this->assertSame(0, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " pagination ")]', $inbox)->length);
                $rows = $xpath->query('.//*[@data-inbox-rows]/a[@data-inbox-id]', $inbox);
                $this->assertSame($size, $rows->length);
                foreach ($rows as $index => $row) {
                    $conversation = $expected['conversations'][$index];
                    $this->assertSame((string) $conversation['id'], $row->getAttribute('data-inbox-id'));
                    parse_str(parse_url($row->getAttribute('href'), PHP_URL_QUERY), $linkQuery);
                    $this->assertSame([
                        'tab' => $tab, $tab === 'orders' ? 'order_id' : 'customer_id' => (string) $conversation['id'], 'q' => 'Lazy client',
                    ], $linkQuery);
                    $this->assertStringNotContainsString('page=', $row->getAttribute('href'));
                    $this->assertStringContainsString($conversation['customer'], $row->textContent);
                    $this->assertStringContainsString($conversation['number'], $row->textContent);
                    $this->assertSame($conversation['avatar_url'], $xpath->query('.//img', $row)->item(0)->getAttribute('src'));
                }
            }
        }
        foreach (['page', 'support_page'] as $parameter) {
            $this->getJson('/admin/messages?tab=support&'.$parameter.'=0')->assertUnprocessable()->assertJsonValidationErrors($parameter);
        }
        Http::assertNothingSent();
    }

    public function test_lazy_inbox_ssr_escapes_row_text_and_avatar_props_and_displays_the_initial_fallback(): void
    {
        $preview = '<script>alert("preview")</script> & hello';
        Http::fake(fn () => Http::response(['messages' => [
            ['TinNhanID' => 1, 'NoiDung' => $preview], ['TinNhanID' => 2, 'NoiDung' => $preview],
        ]]));
        Http::preventStrayRequests();
        config(['services.supabase.project_url' => 'https://chat.example.test', 'services.supabase.service_role_key' => 'fixture-key']);
        $staff = $this->seedLazyInbox(2);
        $name = '<script>name</script> & "quoted"';
        $avatar = 'https://avatars.example.test/photo.png?x=" onload="alert(1)&y=<svg>';
        $number = '<b>number</b> & "quoted"';
        DB::table('KhachHang')->where('KhachHangID', 101)->update(['HoTen' => $name, 'AvatarUrl' => $avatar, 'SoDienThoai' => $number]);
        DB::table('KhachHang')->where('KhachHangID', 102)->update(['HoTen' => ' ánh & <b>Bình</b>']);
        DB::table('TaiKhoan')->where('TaiKhoanID', 502)->update(['AvatarURL' => null]);
        DB::table('DonHang')->where('DonHangID', 1001)->update(['MaDonHang' => $number]);
        DB::table('TinNhan')->insert([
            ['TinNhanID' => 1, 'NguoiGuiID' => 501, 'NguoiNhanID' => $staff->getKey(), 'DonHangID' => 1001, 'NoiDung' => $preview],
            ['TinNhanID' => 2, 'NguoiGuiID' => 501, 'NguoiNhanID' => $staff->getKey(), 'DonHangID' => null, 'NoiDung' => $preview],
        ]);
        $this->actingAs($staff);
        foreach (['orders' => 1001, 'support' => 501] as $tab => $id) {
            $response = $this->get('/admin/messages?tab='.$tab)->assertOk()
                ->assertSee(e($name), false)->assertSee(e($number), false)->assertSee(e($preview), false)
                ->assertSee('src="'.e($avatar).'"', false)->assertDontSee($name, false)->assertDontSee($preview, false);
            $xpath = $this->inboxXPath($response->getContent());
            $row = $xpath->query('//a[@data-inbox-id="'.$id.'"]')->item(0);
            $this->assertNotNull($row);
            $this->assertSame(0, $xpath->query('.//script | .//b | .//svg', $row)->length);
            $image = $xpath->query('.//img', $row)->item(0);
            $this->assertNotNull($image);
            $this->assertSame($avatar, $image->getAttribute('src'));
            $this->assertFalse($image->hasAttribute('onload'));
            $avatarElement = $xpath->query('.//*[@role="img" and starts-with(@aria-label, "Ảnh đại diện")]', $row)->item(0);
            $this->assertSame('Ảnh đại diện của '.$name, $avatarElement->getAttribute('aria-label'));
            $this->assertTrue($xpath->query('./span[@aria-hidden="true"]', $avatarElement)->item(0)->hasAttribute('hidden'));

            $fallback = $xpath->query('//a[@data-inbox-id="'.($id + 1).'"]')->item(0);
            $this->assertSame(0, $xpath->query('.//img', $fallback)->length);
            $initial = $xpath->query('.//*[@role="img"]/span[@aria-hidden="true"]', $fallback)->item(0);
            $this->assertSame('Á', trim($initial->textContent));
            $this->assertFalse($initial->hasAttribute('hidden'));
        }
        $this->assertDatabaseHas('TinNhan', ['TinNhanID' => 1, 'TrangThai' => 'Đã gửi']);
        $this->assertDatabaseHas('TinNhan', ['TinNhanID' => 2, 'TrangThai' => 'Đã gửi']);
    }

    public function test_lazy_inbox_ssr_keeps_selected_threads_outside_the_page_without_writing_to_the_database(): void
    {
        Http::fake(fn () => Http::response(['messages' => [
            ['TinNhanID' => 1, 'NoiDung' => 'Selected order message'],
            ['TinNhanID' => 2, 'NoiDung' => 'Selected support message'],
            ['TinNhanID' => 3, 'NoiDung' => 'Other order message'],
            ['TinNhanID' => 4, 'NoiDung' => 'Other support message'],
        ]]));
        Http::preventStrayRequests();
        config(['services.supabase.project_url' => 'https://chat.example.test', 'services.supabase.service_role_key' => 'fixture-key']);
        $staff = $this->seedLazyInbox();
        foreach ([1 => [501, 1001, 'Selected order message'], 2 => [501, null, 'Selected support message'],
            3 => [502, 1002, 'Other order message'], 4 => [502, null, 'Other support message']] as $id => [$sender, $order, $content]) {
            DB::table('TinNhan')->insert([
                'TinNhanID' => $id, 'NguoiGuiID' => $sender, 'NguoiNhanID' => $staff->getKey(),
                'DonHangID' => $order, 'NoiDung' => $content, 'ThoiGianGui' => '2026-10-09 12:00:00',
            ]);
        }
        $snapshot = fn (): array => collect([
            'TaiKhoan', 'KhachHang', 'NhanVien', 'VaiTro', 'Quyen', 'TaiKhoan_VaiTro', 'VaiTro_Quyen',
            'DonHang', 'TinNhan', 'ThongBao', 'NhatKyHeThong', 'HoaDon',
        ])->mapWithKeys(fn ($table): array => [$table => DB::table($table)->orderBy('rowid')->get()->toJson()])->all();
        $before = $snapshot();
        $this->actingAs($staff);
        foreach (['orders' => ['order_id', 1001, 1], 'support' => ['customer_id', 501, 2]] as $tab => [$target, $id, $messageId]) {
            // A nonmatching search keeps the selected conversation outside the rendered sidebar.
            $response = $this->get('/admin/messages?'.http_build_query([
                'tab' => $tab === 'orders' ? 'support' : 'orders', $target => $id, 'q' => 'Lazy client 23',
            ]))->assertOk()->assertViewHas('activeTab', $tab)->assertViewHas('messageDecryptionUnavailable', false)
                ->assertSee('data-message-id="'.$messageId.'"', false)
                ->assertDontSee('data-inbox-id="'.$id.'"', false)->assertSee('data-selected-id="'.$id.'"', false)
                ->assertSee('name="'.$target.'" value="'.$id.'"', false)->assertSee('data-message-form', false);
            $this->assertSame($id, $response->viewData($tab === 'orders' ? 'selectedOrder' : 'selectedCustomer')->getKey());
            $this->assertNull($response->viewData($tab === 'orders' ? 'selectedCustomer' : 'selectedOrder'));
            $this->assertSame([$messageId], $response->viewData('messages')->pluck('TinNhanID')->all());
            $this->assertSame([$tab === 'orders' ? 1023 : 523], array_column($response->viewData('inboxPage')['conversations'], 'id'));
            $response->assertSee('data-message-updates="'.route($tab === 'orders' ? 'admin.messages.updates' : 'admin.messages.support-updates', $id).'"', false);
            $this->assertSame($before, $snapshot());
        }
        Http::assertSent(fn ($request): bool => $request['action'] === 'present_web_messages');
    }

    public function test_lazy_inbox_ssr_shows_an_order_without_an_account_but_no_send_form(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        $staff = $this->seedLazyInbox(1);
        DB::table('TaiKhoan')->where('TaiKhoanID', 501)->delete();
        $response = $this->actingAs($staff)->get('/admin/messages?order_id=1001')->assertOk()
            ->assertSee('data-inbox-id="1001"', false)->assertSee('Khách hàng chưa có tài khoản nhận tin nhắn.')
            ->assertDontSee('data-message-form', false)->assertViewHas('messageDecryptionUnavailable', false);
        $this->assertNull($response->viewData('inboxPage')['conversations'][0]['avatar_url']);
        $this->assertSame(1001, $response->viewData('selectedOrder')->getKey());
        $this->assertTrue($response->viewData('messages')->isEmpty());
        Http::assertNothingSent();
    }

    public function test_store_message_uses_linked_customer_account_and_keeps_chat_out_of_audit_log(): void
    {
        $key = random_bytes(32);
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
        $this->assertSame('', $message->NoiDung);
        $this->assertSame(1, $message->MaHoaPhienBan);
        $this->assertNotSame('Xin chào khách hàng!', $message->NoiDungMaHoa);
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
        $this->assertSame('Xin chào khách hàng!', app(MessageService::class)
            ->getMessages($order)
            ->last()
            ->NoiDung);
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
                'BookingID' => 98765,
            ]);

        $response->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', 'Thông báo đã được tạo thành công.');

        $notification = ThongBao::query()->where('TieuDe', 'Thông báo kiểm thử')->firstOrFail();
        $this->assertSame($account->TaiKhoanID, $notification->TaiKhoanID);
        $this->assertSame($expectedSentAt->format('Y-m-d H:i'), $notification->ThoiGianGui->format('Y-m-d H:i'));
        $this->assertNull($notification->BookingID);
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

    public function test_update_notification_ignores_user_supplied_booking_id(): void
    {
        $account = $this->createAccount('notification-booking-link');
        $notification = ThongBao::query()->create([
            'TaiKhoanID' => $account->TaiKhoanID,
            'TieuDe' => 'Thông báo cần cập nhật',
            'NoiDung' => 'Nội dung.',
        ]);

        $this->actingAs($account)
            ->withoutMiddleware()
            ->put(route('notifications.update', $notification->ThongBaoID), [
                'TaiKhoanID' => $account->TaiKhoanID,
                'TieuDe' => 'Thông báo đã cập nhật',
                'NoiDung' => 'Nội dung đã cập nhật.',
                'BookingID' => 98765,
            ])
            ->assertRedirect(route('notifications.index'));

        $this->assertNull($notification->fresh()->BookingID);
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
        Http::fake();
        Http::preventStrayRequests();
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

    public function test_chat_uses_compact_send_button_contrasting_metadata_and_header_message_badge(): void
    {
        $staff = $this->createAccount('chat-staff');
        $this->assignRole($staff, 'Owner');
        $customer = $this->createAccount('chat-customer', 1);
        KhachHang::query()->create(['KhachHangID' => 1, 'HoTen' => 'Khách thử nghiệm']);
        $message = TinNhan::query()->create([
            'NguoiGuiID' => $staff->getKey(),
            'NguoiNhanID' => $customer->getKey(),
            'NoiDung' => 'Cửa hàng xin chào',
            'TrangThai' => 'Đã gửi',
        ]);
        config([
            'services.supabase.project_url' => 'https://example.supabase.co',
            'services.supabase.service_role_key' => 'test-service-role-key',
        ]);
        Http::fake([
            'https://example.supabase.co/functions/v1/chat-encryption' => Http::response([
                'messages' => [['TinNhanID' => $message->getKey(), 'NoiDung' => 'Cửa hàng xin chào']],
            ]),
        ]);

        $this->actingAs($staff)->get(route('admin.messages.index', ['customer_id' => $customer->getKey()]))
            ->assertOk()
            ->assertSee('Cửa hàng xin chào')
            ->assertSee('class="small text-white"', false)
            ->assertSee('class="small text-end text-white"', false)
            ->assertDontSee('text-white-50', false)
            ->assertSee('btn-primary btn-sm align-self-end flex-shrink-0 text-nowrap px-3 py-2', false)
            ->assertSee('data-message-unread="'.route('admin.messages.unread-count').'"', false)
            ->assertSee('data-message-unread-count', false);

        $this->get(route('admin.system-logs.index'))->assertOk()
            ->assertSee('data-message-unread', false);
        $this->assertSame('Đã gửi', $message->fresh()->TrangThai);
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

    public function test_custom_internal_role_has_communication_defaults_without_permission_rows(): void
    {
        $user = $this->createAccount('future-internal');
        DB::table('NhanVien')->insert(['NhanVienID' => 1, 'HoTen' => 'Bộ phận mới']);
        $user->update(['NhanVienID' => 1]);
        $this->assignRole($user, 'Bộ phận mới bất kỳ');
        DB::table('VaiTro_Quyen')->delete();
        $this->actingAs($user);

        foreach (['messages.view', 'messages.create', 'notifications.view'] as $code) {
            $this->assertTrue($user->canPermission($code), $code);
            $this->assertTrue($user->can($code), $code);
            $this->assertContains($code, $user->permissionCodes());
        }
        $this->assertFalse($user->can('notifications.create'));
        $this->assertFalse($user->can('orders.create'));
        $this->get(route('admin.messages.index'))->assertOk()->assertSee('data-message-inbox', false)
            ->assertSee('data-notification-updates', false);
        $this->getJson(route('admin.messages.inbox'))->assertOk();
        $this->getJson(route('notifications.updates'))->assertOk();

        $encryptionKey = random_bytes(32);
        config([
            'services.supabase.project_url' => 'https://example.supabase.co',
            'services.supabase.service_role_key' => 'test-service-role-key',
        ]);
        Http::fake([
            'https://example.supabase.co/rest/v1/rpc/get_chat_encryption_key' => Http::response(
                json_encode(base64_encode($encryptionKey), JSON_THROW_ON_ERROR),
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);
        $customer = $this->createAccount('recipient-new-role', 1);
        $this->post(route('admin.messages.store'), ['customer_id' => $customer->getKey(), 'content' => 'Xin chào'])
            ->assertRedirect();
        $this->assertDatabaseHas('TinNhan', [
            'NguoiGuiID' => $user->getKey(),
            'NoiDung' => '',
            'MaHoaPhienBan' => 1,
        ]);
        $this->assertNotSame('Xin chào', DB::table('TinNhan')->where('NguoiGuiID', $user->getKey())->value('NoiDungMaHoa'));
        $this->get(route('admin.messages.index', ['customer_id' => $customer->getKey()]))
            ->assertOk()->assertSee('Xin chào')->assertSee('data-message-form', false);
    }

    public function test_customer_aliases_cannot_use_admin_communications_even_with_grants(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        foreach (['Khách hàng', 'Customer', 'Client', 'khach-hang'] as $index => $name) {
            $user = $this->createAccount('excluded-'.$index, $index + 1);
            $this->assignRole($user, $name);
            $this->actingAs($user);
            foreach (['messages.view', 'messages.create', 'notifications.view'] as $code) {
                $this->assertFalse($user->canPermission($code), $name.': '.$code);
                $this->assertFalse($user->can($code));
            }
            $this->get(route('admin.messages.index'))->assertForbidden();
            $this->getJson(route('admin.messages.inbox'))->assertForbidden();
            $this->postJson(route('admin.messages.realtime'), ['order_id' => 1])->assertForbidden();
            $this->get(route('notifications.index'))->assertForbidden();
            $this->getJson(route('notifications.updates'))->assertForbidden();
            $this->post(route('admin.messages.store'), ['customer_id' => 1, 'content' => 'Không được gửi'])->assertForbidden();
            $html = view('admin.messages.index')->render();
            $this->assertStringNotContainsString('data-message-inbox', $html);
            $this->assertStringNotContainsString('data-message-form', $html);
            $this->assertStringNotContainsString('data-message-unread', $html);
            $this->assertStringNotContainsString('notification-bell-toggle', $html);
            $this->assertStringNotContainsString('data-notification-updates', $html);
        }
        $this->assertDatabaseCount('TinNhan', 0);
    }

    public function test_customer_membership_wins_over_owner_for_admin_communications(): void
    {
        $user = $this->createAccount('mixed-role');
        $this->assignRole($user, 'Owner');
        $this->assignRole($user, 'Khách hàng');
        $this->actingAs($user);
        $this->assertFalse($user->can('messages.create'));
        $this->assertFalse($user->can('notifications.view'));
        $this->getJson(route('notifications.updates'))->assertForbidden();
    }

    public function test_missing_or_disabled_internal_roles_do_not_get_defaults(): void
    {
        $user = $this->createAccount('no-role');
        $this->actingAs($user);
        $this->assertFalse($user->canPermission('messages.view'));
        $this->assignRole($user, 'Nội bộ đã khóa');
        DB::table('VaiTro')->update(['TrangThai' => 'Khóa']);
        PermissionCache::forgetAll();
        $this->assertFalse($user->canPermission('notifications.view'));
        $user->update(['TrangThai' => 'Khóa']);
        $this->assertFalse($user->canPermission('messages.create'));
    }

    private function seedLazyInbox(int $count = 23): User
    {
        $staff = $this->createAccount('inbox-staff');
        $this->assignRole($staff, 'Owner');
        for ($i = 1; $i <= $count; $i++) {
            DB::table('KhachHang')->insert([
                'KhachHangID' => 100 + $i, 'HoTen' => sprintf('Lazy client %02d', $i),
                'SoDienThoai' => sprintf('09070000%02d', $i),
            ]);
            DB::table('TaiKhoan')->insert([
                'TaiKhoanID' => 500 + $i, 'KhachHangID' => 100 + $i,
                'TenDangNhap' => 'inbox-client-'.$i, 'AvatarURL' => 'https://avatars.example.test/'.$i.'.png',
            ]);
            DB::table('DonHang')->insert([
                'DonHangID' => 1000 + $i, 'KhachHangID' => 100 + $i, 'MaDonHang' => 'LAZY-ORDER-'.$i,
                'TrangThai' => 'Chờ tiếp nhận', 'NgayTao' => '2026-10-09 12:00:00',
            ]);
        }

        return $staff;
    }

    private function inboxXPath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($document);
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
