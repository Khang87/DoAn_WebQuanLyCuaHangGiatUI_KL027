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
            $t->string('AvatarURL')->nullable();
            $t->string('TrangThai')->default('Hoạt động');
        });
        foreach (['KhachHang' => 'KhachHangID', 'NhanVien' => 'NhanVienID'] as $name => $id) {
            Schema::create($name, function (Blueprint $t) use ($id, $name): void {
                $t->increments($id);
                $t->string('HoTen');
                if ($name === 'KhachHang') {
                    $t->string('SoDienThoai')->nullable();
                    $t->string('AvatarUrl')->nullable();
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
        Schema::create('ThongBao', function (Blueprint $t): void {
            $t->increments('ThongBaoID');
            $t->integer('TaiKhoanID');
            $t->integer('DonHangID')->nullable();
            $t->integer('TinNhanID')->nullable();
            $t->string('LoaiThongBao')->nullable();
            $t->string('TieuDe');
            $t->string('NoiDung');
            $t->dateTime('ThoiGianGui')->nullable();
            $t->boolean('DaDoc')->default(false);
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

    private function seedLazyInbox(int $count = 23): void
    {
        for ($i = 1; $i <= $count; $i++) {
            DB::table('KhachHang')->insert([
                'KhachHangID' => 100 + $i, 'HoTen' => sprintf('Lazy inbox %02d', $i),
                'SoDienThoai' => sprintf('09070000%02d', $i),
            ]);
            DB::table('TaiKhoan')->insert([
                'TaiKhoanID' => 500 + $i, 'KhachHangID' => 100 + $i,
                'TenDangNhap' => sprintf('lazy-account-%02d', $i),
                'AvatarURL' => 'https://avatars.example.test/'.$i.'.png',
            ]);
            DB::table('DonHang')->insert([
                'DonHangID' => 1000 + $i, 'KhachHangID' => 100 + $i,
                'MaDonHang' => sprintf('LAZY-ORDER-%02d', $i), 'NgayTao' => '2026-10-09 12:00:00',
            ]);
        }
    }

    public function test_lazy_inbox_paginates_both_tabs_with_a_common_page_and_defaults_to_orders(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        $this->seedLazyInbox();
        $this->actingAs(User::findOrFail(1));

        foreach (['orders' => [...range(1023, 1001), 1], 'support' => [...range(523, 501), 3, 2]] as $tab => $ids) {
            $first = $this->getJson('/admin/messages/inbox'.($tab === 'support' ? '?tab=support' : ''))
                ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                ->assertJsonPath('tab', $tab)->assertJsonPath('page', 1)->assertJsonPath('next_page', 2)
                ->assertJsonCount(20, 'conversations');
            $this->assertEqualsCanonicalizing(['tab', 'page', 'next_page', 'conversations'], array_keys($first->json()));
            $this->assertSame(array_slice($ids, 0, 20), array_column($first->json('conversations'), 'id'));
            $this->assertSame([
                'id' => $ids[0],
                'number' => $tab === 'orders' ? 'LAZY-ORDER-23' : '0907000023',
                'customer' => 'Lazy inbox 23',
                'avatar_url' => 'https://avatars.example.test/23.png',
                'preview' => null, 'unread' => false,
                'date' => $tab === 'orders' ? '09/10/2026' : null,
            ], $first->json('conversations.0'));

            // The JSON endpoint uses page for support too, not the legacy SSR support_page.
            $second = $this->getJson('/admin/messages/inbox?'.http_build_query([
                'tab' => $tab, 'page' => 2, 'support_page' => 99,
            ]))->assertOk()->assertJsonPath('tab', $tab)->assertJsonPath('page', 2)
                ->assertJsonPath('next_page', null)->assertJsonCount(count($ids) - 20, 'conversations');
            $this->assertSame(array_slice($ids, 20), array_column($second->json('conversations'), 'id'));
            $this->assertSame([], array_intersect(
                array_column($first->json('conversations'), 'id'), array_column($second->json('conversations'), 'id'),
            ));
            $this->getJson('/admin/messages/inbox?tab='.$tab.'&page=3')->assertOk()->assertExactJson([
                'tab' => $tab, 'page' => 3, 'next_page' => null, 'conversations' => [],
            ]);
        }
        Http::assertNothingSent();
    }

    public function test_lazy_inbox_search_stays_scoped_across_both_tabs_and_all_pages(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        $this->seedLazyInbox();
        $this->actingAs(User::findOrFail(1));

        foreach (['orders' => 'lazy-order-', 'support' => 'LAZY-ACCOUNT-'] as $tab => $identifier) {
            $ids = $tab === 'orders' ? range(1023, 1001) : range(523, 501);
            foreach (['  Lazy inbox  ', '09070000', $identifier] as $search) {
                foreach ([1 => 20, 2 => 3, 3 => 0] as $page => $size) {
                    $response = $this->getJson('/admin/messages/inbox?'.http_build_query([
                        'tab' => $tab, 'q' => $search, 'page' => $page,
                    ]))->assertOk()->assertJsonPath('tab', $tab)->assertJsonPath('page', $page)
                        ->assertJsonPath('next_page', $page === 1 ? 2 : null)->assertJsonCount($size, 'conversations');
                    $this->assertSame(array_slice($ids, ($page - 1) * 20, 20), array_column($response->json('conversations'), 'id'));
                }
            }
            $this->getJson('/admin/messages/inbox?tab='.$tab.'&q=not-a-fixture')->assertOk()->assertExactJson([
                'tab' => $tab, 'page' => 1, 'next_page' => null, 'conversations' => [],
            ]);
        }
        Http::assertNothingSent();
    }

    public function test_lazy_inbox_avatars_prefer_raw_profile_then_the_exact_conversation_account(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        DB::table('DonHang')->where('DonHangID', 1)->update(['KhachHangID' => 1, 'MaDonHang' => 'AVATAR-ORDER']);
        DB::table('KhachHang')->where('KhachHangID', 1)->update(['SoDienThoai' => '0901111222']);
        DB::table('TaiKhoan')->where('TaiKhoanID', 2)->update(['AvatarURL' => 'https://avatars.example.test/original.png']);
        DB::table('TaiKhoan')->insert([
            'TaiKhoanID' => 99, 'KhachHangID' => 1, 'TenDangNhap' => 'second-account-same-profile',
            'AvatarURL' => 'https://avatars.example.test/exact.png',
        ]);
        $this->actingAs(User::findOrFail(1));
        foreach (['https://avatars.example.test/profile.png', null, ''] as $profileAvatar) {
            DB::table('KhachHang')->where('KhachHangID', 1)->update(['AvatarUrl' => $profileAvatar]);
            $orders = $this->getJson('/admin/messages/inbox')->assertOk();
            $this->assertSame([
                'id' => 1, 'number' => 'AVATAR-ORDER', 'customer' => 'Nguyễn An',
                'avatar_url' => $profileAvatar ?: 'https://avatars.example.test/original.png',
                'preview' => null, 'unread' => false, 'date' => null,
            ], $orders->json('conversations.0'));
            $support = $this->getJson('/admin/messages/inbox?tab=support&q=0901111222')
                ->assertOk()->assertJsonCount(2, 'conversations');
            foreach ([99 => 'exact', 2 => 'original'] as $accountId => $avatar) {
                $row = collect($support->json('conversations'))->firstWhere('id', $accountId);
                $this->assertSame([
                    'id' => $accountId, 'number' => '0901111222', 'customer' => 'Nguyễn An',
                    'avatar_url' => $profileAvatar ?: 'https://avatars.example.test/'.$avatar.'.png',
                    'preview' => null, 'unread' => false, 'date' => null,
                ], $row);
            }
        }

        // A sibling account's avatar and the profile accessor's stock image are not fallbacks.
        DB::table('TaiKhoan')->where('TaiKhoanID', 99)->update(['AvatarURL' => null]);
        $this->getJson('/admin/messages/inbox?tab=support&q=second-account-same-profile')->assertOk()
            ->assertJsonCount(1, 'conversations')->assertJsonPath('conversations.0.id', 99)
            ->assertJsonPath('conversations.0.avatar_url', null);
        $this->getJson('/admin/messages/inbox?tab=support&q=auth-binh')->assertOk()
            ->assertJsonPath('conversations.0.id', 3)->assertJsonPath('conversations.0.number', 'Tư vấn khách hàng')
            ->assertJsonPath('conversations.0.avatar_url', null);
        Http::assertNothingSent();
    }

    public function test_lazy_inbox_keeps_orders_without_accounts_or_profiles_and_nullable_dto_fields(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        DB::table('KhachHang')->insert(['KhachHangID' => 30, 'HoTen' => 'Ánh chưa đăng ký']);
        DB::table('DonHang')->where('DonHangID', 1)->update(['KhachHangID' => 30, 'MaDonHang' => 'NO-ACCOUNT']);
        DB::table('DonHang')->insert(['DonHangID' => 2]);
        $this->actingAs(User::findOrFail(1))->getJson('/admin/messages/inbox')->assertOk()->assertExactJson([
            'tab' => 'orders', 'page' => 1, 'next_page' => null,
            'conversations' => [
                ['id' => 2, 'number' => '', 'customer' => 'Khách hàng', 'avatar_url' => null, 'preview' => null, 'unread' => false, 'date' => null],
                ['id' => 1, 'number' => 'NO-ACCOUNT', 'customer' => 'Ánh chưa đăng ký', 'avatar_url' => null, 'preview' => null, 'unread' => false, 'date' => null],
            ],
        ]);
        DB::table('KhachHang')->where('KhachHangID', 30)->update(['AvatarUrl' => 'https://avatars.example.test/profile-only.png']);
        $this->getJson('/admin/messages/inbox?q=NO-ACCOUNT')->assertOk()
            ->assertJsonPath('conversations.0.avatar_url', 'https://avatars.example.test/profile-only.png');
        Http::assertNothingSent();
    }

    public function test_lazy_inbox_rejects_invalid_tab_query_and_page_values(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        $this->actingAs(User::findOrFail(1));
        foreach (['orders', 'support'] as $tab) {
            foreach ([
                ['page' => 0], ['page' => -1], ['page' => '1.5'], ['page' => 'invalid'], ['page' => [1]],
                ['q' => str_repeat('a', 101)], ['q' => ['nested']],
                ['tab' => 'other'], ['tab' => ['orders']],
            ] as $invalid) {
                $this->getJson('/admin/messages/inbox?'.http_build_query(array_merge(['tab' => $tab], $invalid)))
                    ->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
            }
            $this->getJson('/admin/messages/inbox?'.http_build_query([
                'tab' => $tab, 'q' => str_repeat('a', 100), 'page' => 1,
            ]))->assertOk()->assertJsonPath('conversations', []);
        }
        Http::assertNothingSent();
    }

    public function test_lazy_inbox_requires_staff_permission_and_never_accepts_post_for_either_tab(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        foreach (['orders', 'support'] as $tab) {
            $this->getJson('/admin/messages/inbox?tab='.$tab)->assertUnauthorized();
        }
        $this->actingAs(User::findOrFail(2));
        foreach (['orders', 'support'] as $tab) {
            $this->getJson('/admin/messages/inbox?tab='.$tab)->assertForbidden();
        }
        $this->actingAs(User::findOrFail(1));
        foreach (['orders', 'support'] as $tab) {
            $this->getJson('/admin/messages/inbox?tab='.$tab)->assertOk();
            $this->postJson('/admin/messages/inbox?tab='.$tab)->assertStatus(405);
        }
        DB::table('TaiKhoan_VaiTro')->where('TaiKhoanID', 1)->delete();
        PermissionCache::forgetAll();
        foreach (['orders', 'support'] as $tab) {
            $this->getJson('/admin/messages/inbox?tab='.$tab)->assertForbidden();
        }
        $this->assertDatabaseCount('TinNhan', 0);
        Http::assertNothingSent();
    }

    public function test_lazy_inbox_gets_preserve_the_entire_fixture_database_and_unread_state(): void
    {
        Http::fake(fn () => Http::response(['messages' => [
            ['TinNhanID' => 1, 'NoiDung' => 'Order preview'],
            ['TinNhanID' => 2, 'NoiDung' => 'Support preview'],
        ]]));
        Http::preventStrayRequests();
        config(['services.supabase.project_url' => 'https://chat.example.test', 'services.supabase.service_role_key' => 'fixture-key']);
        $this->seedLazyInbox();
        $orderMessage = $this->message(2, 1, 1);
        $supportMessage = $this->message(2, 1);
        DB::table('TinNhan')->where('TinNhanID', $orderMessage)->update(['NoiDung' => 'Order preview']);
        DB::table('TinNhan')->where('TinNhanID', $supportMessage)->update(['NoiDung' => 'Support preview', 'TrangThai' => 'Đã nhận']);
        $snapshot = fn (): array => collect([
            'TaiKhoan', 'KhachHang', 'NhanVien', 'VaiTro', 'Quyen', 'TaiKhoan_VaiTro', 'VaiTro_Quyen', 'DonHang', 'TinNhan', 'ThongBao',
        ])->mapWithKeys(fn ($table): array => [$table => DB::table($table)->orderBy('rowid')->get()->toJson()])->all();
        $before = $snapshot();
        $this->actingAs(User::findOrFail(1));

        foreach (['orders', 'support'] as $tab) {
            foreach ([1, 2, 3] as $page) {
                $this->getJson('/admin/messages/inbox?tab='.$tab.'&page='.$page)->assertOk();
                $this->get('/admin/messages?'.http_build_query([
                    'tab' => $tab, $tab === 'support' ? 'support_page' : 'page' => $page,
                ]))->assertOk();
                $this->assertSame($before, $snapshot(), $tab.' page '.$page.' must be read-only');
            }
        }
        $this->getJson('/admin/messages/inbox')->assertOk()->assertJsonPath('conversations.0.id', 1)
            ->assertJsonPath('conversations.0.preview', 'Order preview')->assertJsonPath('conversations.0.unread', true)
            ->assertJsonPath('conversations.0.date', '09/10/2026');
        $this->getJson('/admin/messages/inbox?tab=support&q=auth-an')->assertOk()
            ->assertJsonPath('conversations.0.id', 2)->assertJsonPath('conversations.0.preview', 'Support preview')
            ->assertJsonPath('conversations.0.date', '09/10/2026');
        $this->assertSame($before, $snapshot());
        Http::assertSent(fn ($request): bool => $request['action'] === 'present_web_messages');
    }

    public function test_lazy_inbox_account_reads_are_bounded_for_json_and_ssr_and_eagerload_avatar_keys(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        $this->seedLazyInbox(20);
        $this->actingAs(User::findOrFail(1));
        foreach (['orders', 'support'] as $tab) {
            foreach (['/admin/messages/inbox', '/admin/messages'] as $endpoint) {
                $this->get($endpoint.'?tab='.$tab)->assertOk(); // Warm permission/schema caches before measuring.
                $accountReads = [];
                foreach (['Lazy inbox 01' => 1, 'Lazy inbox' => 20] as $search => $size) {
                    DB::enableQueryLog();
                    DB::flushQueryLog();
                    try {
                        $response = $this->get($endpoint.'?'.http_build_query(['tab' => $tab, 'q' => $search]))->assertOk();
                        $rows = $endpoint === '/admin/messages/inbox'
                            ? $response->json('conversations') : $response->viewData('inboxPage')['conversations'];
                        $this->assertCount($size, $rows);
                        foreach ($rows as $row) {
                            $id = $row['id'] - ($tab === 'orders' ? 1000 : 500);
                            $this->assertSame('https://avatars.example.test/'.$id.'.png', $row['avatar_url']);
                        }
                        $accountReads[$size] = count(array_filter(DB::getQueryLog(), fn ($query): bool => str_contains($query['query'], 'from "TaiKhoan"')));
                    } finally {
                        DB::disableQueryLog();
                    }
                }
                $this->assertGreaterThan(0, $accountReads[1]);
                $this->assertSame($accountReads[1], $accountReads[20], $tab.' '.$endpoint.' must not read one account per row');
            }
        }
        foreach (app(MessageService::class)->getOrders('Lazy inbox') as $order) {
            $this->assertTrue($order->relationLoaded('khachHang'));
            $this->assertTrue($order->khachHang->relationLoaded('taiKhoan'));
            $this->assertEqualsCanonicalizing(['TaiKhoanID', 'KhachHangID', 'AvatarURL'], array_keys($order->khachHang->taiKhoan->getAttributes()));
        }
        Http::assertNothingSent();
    }

    public function test_unread_count_includes_all_incoming_order_and_support_messages_without_marking_them_read(): void
    {
        Http::fake();
        $this->message(2, 1, 1);
        $this->message(2, 1, 1);
        $received = $this->message(3, 1);
        DB::table('TinNhan')->where('TinNhanID', $received)->update(['TrangThai' => 'Đã nhận']);
        $read = $this->message(2, 1);
        DB::table('TinNhan')->where('TinNhanID', $read)->update(['TrangThai' => 'Đã đọc']);
        $this->message(1, 2, 1);
        $this->message(1, 1);
        $this->message(2, 3);
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 4, 'TenDangNhap' => 'Staff2', 'NhanVienID' => 1]);
        DB::table('TaiKhoan_VaiTro')->insert(['TaiKhoanID' => 4, 'VaiTroID' => 1]);
        $this->message(2, 4);
        $before = DB::table('TinNhan')->orderBy('TinNhanID')->get()->toJson();
        $this->actingAs(User::findOrFail(1));

        foreach (['', '?q=missing&page=99'] as $query) {
            $this->getJson('/admin/messages/unread-count'.$query)
                ->assertOk()->assertExactJson(['unread_count' => 4])
                ->assertHeader('Cache-Control', 'no-store, private');
        }

        $this->assertSame($before, DB::table('TinNhan')->orderBy('TinNhanID')->get()->toJson());
        Http::assertNothingSent();
    }

    public function test_unread_count_returns_zero_and_excludes_inactive_recipients(): void
    {
        Http::fake();
        $this->actingAs(User::findOrFail(1));
        $this->getJson('/admin/messages/unread-count')->assertOk()->assertExactJson(['unread_count' => 0]);
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 4, 'TenDangNhap' => 'LockedStaff', 'NhanVienID' => 1, 'TrangThai' => 'Khóa']);
        DB::table('TaiKhoan_VaiTro')->insert(['TaiKhoanID' => 4, 'VaiTroID' => 1]);
        $this->message(2, 4);
        $this->getJson('/admin/messages/unread-count')->assertOk()->assertExactJson(['unread_count' => 0]);
        Http::assertNothingSent();
    }

    public function test_unread_count_requires_staff_permission_and_only_accepts_get(): void
    {
        Http::fake();
        Http::preventStrayRequests();
        $this->getJson('/admin/messages/unread-count')->assertUnauthorized();
        $this->actingAs(User::findOrFail(2))->getJson('/admin/messages/unread-count')->assertForbidden();
        $this->actingAs(User::findOrFail(1))->postJson('/admin/messages/unread-count')->assertStatus(405);
        DB::table('TaiKhoan_VaiTro')->where('TaiKhoanID', 1)->delete();
        PermissionCache::forgetAll();
        $this->getJson('/admin/messages/unread-count')->assertForbidden();
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
        Http::fake();
        Http::preventStrayRequests();
        DB::table('DonHang')->where('DonHangID', 1)->update(['NgayTao' => '2026-10-01 00:00:00', 'MaDonHang' => 'DH1']);
        DB::table('DonHang')->insert(['DonHangID' => 2, 'NgayTao' => '2026-10-08 00:00:00', 'MaDonHang' => 'DH2']);
        $this->actingAs(User::findOrFail(1));
        $this->getJson('/admin/messages/inbox')->assertOk()->assertJsonPath('conversations.0.id', 2);
        $incoming = $this->message(2, 1, 1);
        $other = $this->message(2, 1, 2);
        DB::table('TinNhan')->where('TinNhanID', $other)->update(['ThoiGianGui' => '2026-10-08 12:00:00']);
        $outgoing = $this->message(1, 2, 1);
        $support = $this->message(2, 1);
        $this->getJson('/admin/messages/inbox')->assertJsonPath('conversations.0.id', 1)->assertJsonPath('conversations.0.unread', true);
        $this->assertDatabaseHas('TinNhan', ['TinNhanID' => $incoming, 'TrangThai' => 'Đã gửi']);
        $receipt = ['order_id' => 1, 'message_ids' => [$incoming, $other, $outgoing, $support]];
        $this->postJson('/admin/messages/read', $receipt)->assertOk()->assertJsonPath('updated', 1);
        foreach ([$other, $outgoing, $support] as $id) {
            $this->assertDatabaseHas('TinNhan', ['TinNhanID' => $id, 'TrangThai' => 'Đã gửi']);
        }
        $this->postJson('/admin/messages/read', $receipt)->assertJsonPath('updated', 0);
        $this->getJson('/admin/messages/inbox')->assertJsonPath('conversations.0.unread', false)->assertJsonPath('conversations.1.unread', true);
        $this->message(2, 1, 1);
        $this->getJson('/admin/messages/inbox')->assertJsonPath('conversations.0.unread', true);
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
        Http::fake();
        Http::preventStrayRequests();
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
        Http::fake();
        Http::preventStrayRequests();
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

        $this->actingAs(User::findOrFail(1))->post('/admin/messages', ['customer_id' => 2, 'content' => '  Cửa hàng xin chào  '])
            ->assertRedirect()->assertSessionMissing('success');
        $message = DB::table('TinNhan')->where('NguoiGuiID', 1)->first();
        $this->assertSame(2, $message->NguoiNhanID);
        $this->assertNull($message->DonHangID);
        $this->assertSame('', $message->NoiDung);
        $this->assertNotSame('Cửa hàng xin chào', $message->NoiDungMaHoa);
        $this->assertSame(1, (int) $message->MaHoaPhienBan);
        $this->assertNotNull(base64_decode($message->NoiDungNonce, true));

        $this->getJson('/admin/messages/support/2/updates')
            ->assertOk()
            ->assertJsonPath('messages.0.content', 'Cửa hàng xin chào');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://example.supabase.co/rest/v1/rpc/get_chat_encryption_key'
            && $request->hasHeader('apikey', 'test-service-role-key')
            && $request->hasHeader('Authorization', 'Bearer test-service-role-key'));
    }

    public function test_support_message_is_not_saved_when_encryption_key_is_unavailable(): void
    {
        config([
            'services.supabase.project_url' => 'https://example.supabase.co',
            'services.supabase.service_role_key' => 'test-service-role-key',
        ]);
        Http::fake([
            'https://example.supabase.co/rest/v1/rpc/get_chat_encryption_key' => Http::response('null', 200, [
                'Content-Type' => 'application/json',
            ]),
        ]);

        $this->actingAs(User::findOrFail(1))
            ->postJson('/admin/messages', ['customer_id' => 2, 'content' => 'Không được lưu thuần'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Chưa thể mã hóa tin nhắn. Vui lòng thử lại sau.');

        $this->assertDatabaseCount('TinNhan', 0);
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
