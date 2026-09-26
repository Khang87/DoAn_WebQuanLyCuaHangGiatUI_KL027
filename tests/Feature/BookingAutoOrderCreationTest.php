<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lịch hẹn chuyển sang "Đã xác nhận" phải tự động sinh đơn hàng, và đơn đó
 * phải mang mã tham chiếu về lịch. Việc sinh đơn nằm ở BookingService nên
 * mọi đường cập nhật trạng thái (form sửa, service, nút xác nhận) đều dùng
 * chung một logic và không tạo trùng đơn.
 */
class BookingAutoOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->bookingService = app(BookingService::class);
    }

    public function test_booking_is_given_a_reference_code_on_creation(): void
    {
        $booking = Booking::factory()->create();

        $this->assertNotEmpty($booking->code);
        $this->assertMatchesRegularExpression('/^DL\d{4}$/', $booking->code);
    }

    public function test_reference_codes_are_unique_across_bookings(): void
    {
        $codes = Booking::factory()->count(3)->create()->pluck('code');

        $this->assertCount(3, $codes->unique());
    }

    public function test_updating_booking_to_confirmed_creates_an_order_automatically(): void
    {
        $customer = Customer::factory()->create();
        $staff = User::factory()->staff()->create();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'method' => 'giao_do',
            'status' => 'pending',
        ]);

        $this->assertSame(0, Order::where('booking_id', $booking->id)->count());

        $response = $this->actingAs($this->admin)->put(route('bookings.update', $booking->id), [
            'customer_id' => $customer->id,
            'staff_id' => $staff->id,
            'method' => 'giao_do',
            'scheduled_date' => $booking->scheduled_date->format('Y-m-d'),
            'scheduled_time' => $booking->scheduled_time->format('H:i'),
            'status' => 'confirmed',
            'notes' => 'Giữ xe trước 8h',
        ]);

        $response->assertRedirect(route('bookings.index'));
        $response->assertSessionHas('success');

        $order = Order::where('booking_id', $booking->id)->firstOrFail();

        $this->assertSame('pending', $order->status);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame($staff->id, $order->employee_id);
        $this->assertStringContainsString($booking->code, $order->notes);

        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'method' => 'giao_do',
        ]);
    }

    public function test_keeping_status_confirmed_does_not_create_a_duplicate_order(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);

        $this->bookingService->update($booking, ['status' => 'confirmed']);
        $order = Order::where('booking_id', $booking->id)->firstOrFail();

        $this->bookingService->update($booking->fresh(), ['notes' => 'Cập nhật ghi chú']);
        $this->bookingService->update($booking->fresh(), ['status' => 'confirmed']);

        $this->assertSame(1, Order::where('booking_id', $booking->id)->count());
        $this->assertSame($order->id, Order::where('booking_id', $booking->id)->firstOrFail()->id);
    }

    public function test_pending_booking_does_not_get_an_order(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);

        $this->bookingService->update($booking, ['status' => 'pending']);

        $this->assertSame(0, Order::where('booking_id', $booking->id)->count());
    }

    public function test_cancelled_booking_does_not_get_an_order(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);

        $this->bookingService->update($booking, ['status' => 'cancelled']);

        $this->assertSame(0, Order::where('booking_id', $booking->id)->count());
    }

    public function test_later_status_still_creates_the_missing_order(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);

        $this->bookingService->update($booking, ['status' => 'arrived']);

        $this->assertSame(1, Order::where('booking_id', $booking->id)->count());
    }

    public function test_confirm_route_reuses_the_order_created_earlier(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);

        $this->bookingService->update($booking, ['status' => 'confirmed']);
        $order = Order::where('booking_id', $booking->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('bookings.confirm', $booking->id))
            ->assertRedirect(route('orders.show', $order->id));

        $this->assertSame(1, Order::where('booking_id', $booking->id)->count());
    }

    public function test_order_exposes_the_booking_reference_code(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);
        $this->bookingService->update($booking, ['status' => 'confirmed']);

        $order = Order::where('booking_id', $booking->id)->firstOrFail();

        $this->assertTrue($order->comesFromBooking());
        $this->assertSame($booking->code, $order->booking_code);
    }

    public function test_order_created_without_booking_has_no_reference_code(): void
    {
        $order = Order::create([
            'code' => 'DH-NO-BOOKING',
            'customer_id' => Customer::factory()->create()->id,
            'total_amount' => 0,
            'status' => 'pending',
        ]);

        $this->assertFalse($order->comesFromBooking());
        $this->assertNull($order->booking_code);
    }

    public function test_booking_views_render_the_reference_code_and_order(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);
        $this->bookingService->update($booking, ['status' => 'confirmed']);
        $order = Order::where('booking_id', $booking->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('bookings.index'))
            ->assertStatus(200)
            ->assertSee($booking->code)
            ->assertSee($order->code);

        $this->actingAs($this->admin)
            ->get(route('bookings.show', $booking))
            ->assertStatus(200)
            ->assertSee($booking->code)
            ->assertSee($order->code);

        $this->actingAs($this->admin)
            ->get(route('orders.index'))
            ->assertStatus(200)
            ->assertSee($booking->code);

        $this->actingAs($this->admin)
            ->get(route('orders.show', $order))
            ->assertStatus(200)
            ->assertSee($booking->code);
    }
}
