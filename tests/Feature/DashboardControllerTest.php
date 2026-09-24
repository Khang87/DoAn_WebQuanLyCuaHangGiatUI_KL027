<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_visit_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_unauthenticated_users_are_redirected_from_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_see_full_dashboard_with_financial_widgets(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('isAdmin', true);
        $response->assertSee('Doanh Thu');
        $response->assertSee('Thống Kê Doanh Thu');
        $response->assertSee('Đơn Hàng Gần Đây');
        $response->assertSee('Đơn Hàng Mới Cần Xử Lý');
        $response->assertSee('apexcharts');
    }

    public function test_staff_see_only_shared_dashboard_without_financial_widgets(): void
    {
        $user = User::factory()->staff()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('isAdmin', false);
        $response->assertSee('Lịch Giao Nhận Trong Ngày');
        $response->assertSee('Hóa Đơn Chờ Thanh Toán');
        $response->assertSee('Khách Hàng Mới Hôm Nay');
        $response->assertSee('Lối Tắt Nhanh');
        $response->assertSee('Tạo khách hàng mới');
        $response->assertSee('Lập hóa đơn');
        $response->assertDontSee('Doanh Thu');
        $response->assertDontSee('Thống Kê Doanh Thu');
        $response->assertDontSee('apexcharts');
    }
}
