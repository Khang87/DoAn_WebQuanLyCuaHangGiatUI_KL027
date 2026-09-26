<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_visit_dashboard(): void
    {
        $user = User::factory()->staff()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        // /dashboard redirects to role-specific dashboard, so follow the redirect
        $response->assertRedirect();
        $response = $this->actingAs($user)->get($response->headers->get('Location'));
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

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('isAdmin', true);
        $response->assertViewHasAll([
            'todayRevenue',
            'weekRevenue',
            'monthRevenue',
            'statusCounts',
            'totalOrders',
            'totalCustomers',
            'newCustomersMonth',
            'averageRating',
            'totalReviews',
            'last7DaysRevenue',
            'last12Months',
            'statusDistribution',
            'topServices',
            'recentOrders',
            'latestReviews',
        ]);
        $response->assertSee('Doanh thu hôm nay');
        $response->assertSee('Doanh thu tuần này');
        $response->assertSee('Doanh thu tháng này');
        $response->assertSee('Điểm đánh giá trung bình');
        $response->assertSee('Đơn hàng mới nhất');
        $response->assertSee('Top dịch vụ được đặt nhiều nhất');
        $response->assertSee('apexcharts');
    }

    public function test_manager_role_also_reaches_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'manager']);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertStatus(200);
    }

    public function test_staff_see_only_operational_dashboard_without_financial_widgets(): void
    {
        $user = User::factory()->staff()->create();

        $response = $this->actingAs($user)->get(route('staff.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHasAll([
            'statusFlow',
            'waitingReceiveCount',
            'washingCount',
            'readyCount',
            'pickupCount',
            'deliveryCount',
            'processingOrders',
            'todaySchedule',
            'upcomingBookings',
        ]);
        $response->assertSee('Lịch nhận / giao hôm nay');
        $response->assertSee('Đơn hàng cần xử lý');
        $response->assertSee('Lịch hẹn sắp tới');
        $response->assertDontSee('Doanh thu');
        $response->assertDontSee('apexcharts');
    }

    public function test_staff_is_redirected_to_staff_dashboard_when_opening_admin_dashboard(): void
    {
        $user = User::factory()->staff()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertRedirect(route('staff.dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_admin_gets_forbidden_on_staff_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('staff.dashboard'))
            ->assertStatus(403);
    }

    public function test_staff_is_redirected_away_from_manager_only_modules(): void
    {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->get(route('services.index'))
            ->assertRedirect(route('staff.dashboard'));
    }

    public function test_dashboards_render_correctly_with_real_records(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();

        Customer::factory()->count(6)->create();
        Service::factory()->count(4)->create();

        Order::factory()->create(['status' => 'completed', 'total_amount' => 100000]);

        foreach (['pending', 'received', 'sorting', 'processing', 'washed', 'delivering', 'cancelled'] as $status) {
            Order::factory()->create(['status' => $status, 'total_amount' => 250000]);
        }

        Order::take(3)->get()->each(fn ($order) => Review::factory()->create(['order_id' => $order->id]));

        Delivery::factory()->count(4)->create([
            'employee_id' => $staff->id,
            'pickup_date' => now(),
            'status' => 'pending',
        ]);

        Booking::factory()->count(3)->create(['scheduled_date' => now()->addDay()]);

        $adminResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('Top dịch vụ được đặt nhiều nhất');
        $adminResponse->assertSee('Doanh thu tuần này');

        $staffResponse = $this->actingAs($staff)->get(route('staff.dashboard'));
        $staffResponse->assertStatus(200);
        $staffResponse->assertSee('Lịch nhận / giao hôm nay');
        $staffResponse->assertDontSee('Doanh thu');
    }

    public function test_staff_can_quickly_update_order_status(): void
    {
        $staff = User::factory()->staff()->create();
        $order = Order::factory()->create(['status' => 'pending']);

        $this->actingAs($staff)
            ->patchJson(route('staff.dashboard.update-order-status', $order), ['status' => 'sorting'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('sorting', $order->fresh()->status);
    }

    public function test_staff_cannot_set_an_invalid_order_status(): void
    {
        $staff = User::factory()->staff()->create();
        $order = Order::factory()->create(['status' => 'pending']);

        $this->actingAs($staff)
            ->patchJson(route('staff.dashboard.update-order-status', $order), ['status' => 'completed'])
            ->assertStatus(400);

        $this->assertSame('pending', $order->fresh()->status);
    }
}
