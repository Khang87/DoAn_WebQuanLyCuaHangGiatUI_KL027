<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_services_index_has_no_lock_or_unlock_button(): void
    {
        Service::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->admin)->get(route('services.index'));

        $response->assertStatus(200);
        $response->assertDontSee('toggle-status');
        $response->assertDontSee('Khóa dịch vụ');
        $response->assertDontSee('Kích hoạt</button>');
    }

    public function test_services_module_has_no_toggle_status_route(): void
    {
        $this->assertNull(
            collect(\Illuminate\Support\Facades\Route::getRoutes())->first(
                fn ($route) => $route->getName() === 'services.toggle-status'
            )
        );
    }

    public function test_confirmed_booking_converts_into_an_order_and_delivery(): void
    {
        $customer = Customer::factory()->create();
        $staff = User::factory()->staff()->create();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'status' => 'confirmed',
            'method' => 'giao_do',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('bookings.confirm', $booking->id));

        $order = Order::where('customer_id', $customer->id)->firstOrFail();

        $response->assertRedirect(route('orders.show', $order));
        $this->assertFalse($response->isRedirection() && session('error'), session('error') ?? '');

        $this->assertSame('pending', $order->status);
        $this->assertSame($staff->id, $order->employee_id);
        $this->assertSame('confirmed', $booking->fresh()->status);

        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'method' => 'giao_do',
        ]);
    }

    public function test_pending_booking_cannot_be_converted_into_an_order(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);

        $this->actingAs($this->admin)
            ->post(route('bookings.confirm', $booking->id))
            ->assertRedirect(route('bookings.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, Order::count());
    }

    public function test_delivery_and_booking_use_vietnamese_method_labels(): void
    {
        $this->assertSame('Nhận đồ', (new Delivery(['method' => 'nhan_do']))->type_label);
        $this->assertSame('Giao đồ', (new Delivery(['method' => 'giao_do']))->type_label);
        $this->assertSame('Nhận đồ', (new Booking(['method' => 'nhan_do']))->method_label);
        $this->assertSame('Giao đồ', (new Booking(['method' => 'giao_do']))->method_label);
    }

    public function test_reviews_index_shows_average_rating_rounded_to_one_decimal(): void
    {
        $orders = Order::factory()->count(4)->create(['status' => 'completed']);

        // 5 + 4 + 4 + 5 = 18 / 4 = 4.5
        foreach ([5, 4, 4, 5] as $index => $rating) {
            \App\Models\Review::factory()->create([
                'order_id' => $orders[$index]->id,
                'customer_id' => $orders[$index]->customer_id,
                'rating' => $rating,
                'status' => 'visible',
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('reviews.index'));

        $response->assertStatus(200);
        $response->assertSee('4.5');
    }

    public function test_invoice_total_is_accepted_from_the_form(): void
    {
        $order = Order::factory()->create(['status' => 'processing']);

        $response = $this->actingAs($this->admin)->post(route('invoices.store'), [
            'order_id' => $order->id,
            'total' => 275000,
            'status' => 'unpaid',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoices', [
            'order_id' => $order->id,
            'total' => 275000,
        ]);
    }
}
