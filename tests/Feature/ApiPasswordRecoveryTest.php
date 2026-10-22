<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiPasswordRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.supabase.project_url' => 'https://test-project.supabase.co',
            'services.supabase.anon_key' => 'test-anon-key',
        ]);
        DB::purge('sqlite');

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->string('TenDangNhap');
            $table->string('Email');
            $table->string('MatKhau');
            $table->string('TrangThai');
        });
    }

    public function test_unknown_email_returns_client_error_without_calling_supabase(): void
    {
        Http::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.test',
        ])->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Email không tồn tại trong hệ thống, vui lòng nhập lại.');

        Http::assertNothingSent();
    }

    public function test_inactive_account_returns_error_without_calling_supabase(): void
    {
        User::query()->create([
            'TenDangNhap' => 'locked@example.test',
            'Email' => 'locked@example.test',
            'MatKhau' => 'unused',
            'TrangThai' => 'Khóa',
        ]);
        Http::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'locked@example.test',
        ])->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Tài khoản đã bị vô hiệu hóa.');

        Http::assertNothingSent();
    }

    public function test_active_account_is_validated_against_app_database_before_supabase_recovery(): void
    {
        User::query()->create([
            'TenDangNhap' => 'active@example.test',
            'Email' => 'active@example.test',
            'MatKhau' => 'unused',
            'TrangThai' => 'Hoạt động',
        ]);
        Http::fake([
            'https://test-project.supabase.co/auth/v1/recover' => Http::response([], 200),
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'ACTIVE@example.test',
        ])->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://test-project.supabase.co/auth/v1/recover'
            && $request->hasHeader('apikey', 'test-anon-key')
            && $request->hasHeader('Authorization', 'Bearer test-anon-key')
            && $request['email'] === 'active@example.test');
    }

    public function test_invalid_email_is_rejected_before_database_lookup_or_supabase_request(): void
    {
        Http::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'not-an-email',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Http::assertNothingSent();
    }

    public function test_supabase_error_is_returned_as_gateway_error_without_provider_details(): void
    {
        User::query()->create([
            'TenDangNhap' => 'active@example.test',
            'Email' => 'active@example.test',
            'MatKhau' => 'unused',
            'TrangThai' => 'Hoạt động',
        ]);
        Http::fake([
            'https://test-project.supabase.co/auth/v1/recover' => Http::response([
                'msg' => 'internal provider detail',
            ], 500),
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'active@example.test',
        ])->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Không thể gửi mã khôi phục lúc này. Vui lòng thử lại sau.')
            ->assertDontSee('internal provider detail');
    }
}
