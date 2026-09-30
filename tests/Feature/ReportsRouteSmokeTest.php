<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kiem tra GET /reports render duoc tren schema tieng Viet (chi doc, khong migrate).
 *
 * Tuyet doi KHONG dung RefreshDatabase: no se chay migration tao schema tieng Anh.
 */
class ReportsRouteSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Chỉ chạy được trên PostgreSQL thật (schema tiếng Việt đã tồn tại).
        // Với config mặc định sqlite :memory: thì bỏ qua để không làm hỏng suite.
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Cần PostgreSQL thật: đặt DB_CONNECTION=pgsql và DB_DATABASE=postgres.');
        }
    }

    public function test_reports_page_renders_for_owner(): void
    {
        $owner = User::whereHas('vaiTros', fn ($q) => $q->where('TenVaiTro', 'Chủ cửa hàng'))->first();

        $this->assertNotNull($owner, 'Khong tim thay tai khoan Chu cua hang');

        $response = $this->actingAs($owner)->get('/reports');

        $response->assertStatus(200);
        $response->assertSee('Báo cáo', false);
    }

    public function test_reports_page_renders_with_date_filters(): void
    {
        $owner = User::whereHas('vaiTros', fn ($q) => $q->where('TenVaiTro', 'Chủ cửa hàng'))->first();

        foreach (['today', '7_days', 'this_month', 'last_month'] as $range) {
            $this->actingAs($owner)->get('/reports?range=' . $range)->assertStatus(200);
        }

        $this->actingAs($owner)
            ->get('/reports?range=custom&date_from=2026-01-01&date_to=2026-12-31')
            ->assertStatus(200);
    }
}
