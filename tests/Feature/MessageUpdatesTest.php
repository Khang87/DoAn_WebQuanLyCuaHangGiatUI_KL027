<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MessageRealtimeService;
use App\Support\PermissionCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessageUpdatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', config('database.default'));
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
        Schema::create('DonHang', fn (Blueprint $table) => $table->increments('DonHangID'));
        Schema::create('TinNhan', function (Blueprint $table): void {
            $table->increments('TinNhanID');
            $table->integer('DonHangID');
            $table->integer('NguoiGuiID');
            $table->text('NoiDung');
            $table->dateTime('ThoiGianGui')->nullable();
        });
        DB::table('TaiKhoan')->insert([
            ['TaiKhoanID' => 1, 'TenDangNhap' => 'Store'],
            ['TaiKhoanID' => 2, 'TenDangNhap' => 'Mobile'],
        ]);
        DB::table('VaiTro')->insert(['VaiTroID' => 1, 'TenVaiTro' => 'Nhân viên']);
        DB::table('Quyen')->insert(['QuyenID' => 1, 'MaQuyen' => 'MESSAGES_VIEW']);
        DB::table('TaiKhoan_VaiTro')->insert(['TaiKhoanID' => 1, 'VaiTroID' => 1]);
        DB::table('VaiTro_Quyen')->insert(['VaiTroID' => 1, 'QuyenID' => 1]);
        DB::table('DonHang')->insert([['DonHangID' => 1], ['DonHangID' => 2]]);
    }

    private function staff(): User
    {
        return User::findOrFail(1);
    }

    private function message(int $order = 1, string $content = 'Hello', int $sender = 2): int
    {
        return DB::table('TinNhan')->insertGetId([
            'DonHangID' => $order, 'NguoiGuiID' => $sender,
            'NoiDung' => $content, 'ThoiGianGui' => '2026-10-09 12:00:00',
        ], 'TinNhanID');
    }

    public function test_updates_return_only_selected_conversation_and_minimal_display_data(): void
    {
        $this->message(2, 'Other conversation');
        $id = $this->message(1, '<img src=x onerror=alert(1)>');
        $this->actingAs($this->staff())->getJson('/admin/messages/1/updates')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['order_id' => 1, 'messages' => [[
                'id' => $id, 'content' => '<img src=x onerror=alert(1)>',
                'sender_name' => 'Mobile', 'is_mine' => false, 'sent_at' => '2026-10-09T19:00:00+07:00',
            ]]]);
    }

    public function test_later_mobile_message_appears_on_next_read_without_old_duplicates(): void
    {
        $this->actingAs($this->staff());
        $this->getJson('/admin/messages/1/updates')->assertJsonCount(0, 'messages');
        $id = $this->message();
        $this->getJson('/admin/messages/1/updates')->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.id', $id);
        $this->getJson('/admin/messages/1/updates')->assertJsonCount(1, 'messages');
    }

    public function test_history_is_bounded_and_stable_for_equal_timestamps(): void
    {
        for ($i = 1; $i <= 105; $i++) {
            $this->message(content: (string) $i, sender: 1);
        }
        $this->actingAs($this->staff())->getJson('/admin/messages/1/updates')
            ->assertOk()->assertJsonCount(100, 'messages')
            ->assertJsonPath('messages.0.id', 6)->assertJsonPath('messages.99.id', 105)
            ->assertJsonPath('messages.0.is_mine', true);
    }

    public function test_guest_and_missing_permission_cannot_read_updates(): void
    {
        $this->getJson('/admin/messages/1/updates')->assertUnauthorized();
        DB::table('VaiTro_Quyen')->delete();
        $this->actingAs($this->staff())->getJson('/admin/messages/1/updates')->assertForbidden();
    }

    public function test_revocation_and_inactive_account_block_subsequent_reads(): void
    {
        $this->actingAs($this->staff())->getJson('/admin/messages/1/updates')->assertOk();
        DB::table('VaiTro_Quyen')->delete();
        PermissionCache::forgetAll();
        $this->getJson('/admin/messages/1/updates')->assertForbidden();
        $this->actingAs(new User(['TaiKhoanID' => 1, 'TrangThai' => 'Khóa']))
            ->getJson('/admin/messages/1/updates')->assertForbidden();
    }

    public function test_unknown_order_returns_not_found(): void
    {
        $this->actingAs($this->staff())->getJson('/admin/messages/999/updates')->assertNotFound();
    }

    public function test_realtime_configuration_requires_session_and_permission(): void
    {
        $this->postJson('/admin/messages/realtime', ['order_id' => 1])->assertUnauthorized();
        DB::table('VaiTro_Quyen')->delete();
        $this->actingAs($this->staff())->postJson('/admin/messages/realtime', ['order_id' => 1])->assertForbidden();
    }

    public function test_realtime_configuration_validates_conversation_before_registration(): void
    {
        $this->actingAs($this->staff());
        $this->postJson('/admin/messages/realtime', [])->assertUnprocessable();
        $this->postJson('/admin/messages/realtime', ['order_id' => 1, 'customer_id' => 2])->assertUnprocessable();
        $this->postJson('/admin/messages/realtime', ['order_id' => 999])->assertNotFound();
    }

    public function test_realtime_configuration_returns_only_public_connection_and_opaque_topic(): void
    {
        $this->mock(MessageRealtimeService::class, function ($mock): void {
            $mock->shouldReceive('configuration')->once()->with('order:1')->andReturn([
                'url' => 'https://example.supabase.co', 'key' => 'sb_publishable_test',
                'topic' => 'web-message:opaque', 'expires_at' => 2000000000,
            ]);
        });
        $this->actingAs($this->staff())->postJson('/admin/messages/realtime', ['order_id' => 1])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['url' => 'https://example.supabase.co', 'key' => 'sb_publishable_test',
                'topic' => 'web-message:opaque', 'expires_at' => 2000000000]);
    }

    public function test_missing_realtime_configuration_does_not_break_message_reads(): void
    {
        config(['services.supabase.project_url' => null]);
        $this->actingAs($this->staff())->postJson('/admin/messages/realtime', ['order_id' => 1])->assertStatus(503);
        $this->getJson('/admin/messages/1/updates')->assertOk();
    }
}
