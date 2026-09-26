<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Đã thanh toán thì không được sửa/xoá".
 *
 * Bao phủ 4 mức khoá:
 *   1. Đơn đã thu đủ tiền (chưa completed, chưa có hoá đơn paid) -> phải khoá.
 *   2. Hoá đơn paid -> sửa/xoá bị từ chối.
 *   3. Khoản thu đã ghi nhận (status paid) -> sửa/xoá bị từ chối.
 *   4. Chi tiết đơn thuộc đơn đã khoá -> sửa/xoá bị từ chối.
 */
class PaidRecordsAreLockedTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        // Quản lý và Nhân viên KHÔNG được cấp orders.edit_completed /
        // invoices.edit_paid, nên các khoá dưới đây vẫn phải giữ nguyên cho họ.
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->customer = Customer::factory()->create();
    }

    private function order(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'code' => 'DH-LOCK-' . random_int(1000, 9999),
            'customer_id' => $this->customer->id,
            'subtotal' => 100000,
            'total_amount' => 100000,
            'status' => 'processing',
        ], $attrs));
    }

    // ---------- 1. Đơn đã thu đủ tiền ----------

    public function test_order_locked_when_payment_covers_total(): void
    {
        $order = $this->order();

        Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        $order->refresh()->load('payments', 'invoice');

        $this->assertTrue($order->hasSettledPayment());
        $this->assertTrue($order->isLocked());
        $this->assertFalse($order->canEdit());
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_order_not_locked_when_payment_is_partial(): void
    {
        $order = $this->order();

        Payment::create([
            'order_id' => $order->id,
            'amount' => 40000,
            'method' => 'cash',
            'status' => 'partial',
        ]);

        $order->refresh()->load('payments', 'invoice');

        $this->assertFalse($order->hasSettledPayment());
        $this->assertFalse($order->isLocked());
        $this->assertSame('partial', $order->payment_status);
    }

    public function test_cannot_edit_order_that_is_fully_paid(): void
    {
        $order = $this->order();
        Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        $this->actingAs($this->manager)
            ->get(route('orders.edit', $order->id))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->put(route('orders.update', $order->id), [
                'code' => $order->code,
                'customer_id' => $this->customer->id,
                'status' => 'cancelled',
                'total_amount' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total_amount' => 100000,
        ]);
    }

    public function test_cannot_delete_order_that_is_fully_paid(): void
    {
        $order = $this->order();
        Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        $this->actingAs($this->manager)
            ->delete(route('orders.destroy', $order->id))
            ->assertForbidden();

        $this->assertNotSoftDeleted('orders', ['id' => $order->id]);
    }

    // ---------- 2. Hoá đơn đã thanh toán ----------

    public function test_cannot_update_or_delete_paid_invoice(): void
    {
        $order = $this->order();
        $invoice = Invoice::create([
            'code' => 'HD-LOCK-1',
            'order_id' => $order->id,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'grand_total' => 100000,
            'total' => 100000,
            'status' => 'paid',
        ]);

        $this->actingAs($this->manager)
            ->put(route('invoices.update', $invoice->id), [
                'order_id' => $order->id,
                'grand_total' => 1,
                'status' => 'unpaid',
            ])
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->delete(route('invoices.destroy', $invoice->id))
            ->assertForbidden();

        $this->assertNotSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);
    }

    public function test_paid_invoice_edit_page_is_blocked(): void
    {
        $order = $this->order();
        $invoice = Invoice::create([
            'code' => 'HD-LOCK-2',
            'order_id' => $order->id,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'grand_total' => 100000,
            'total' => 100000,
            'status' => 'paid',
        ]);

        $this->actingAs($this->manager)
            ->get(route('invoices.edit', $invoice->id))
            ->assertForbidden();
    }

    // ---------- 3. Khoản thu đã ghi nhận ----------

    public function test_paid_payment_is_locked(): void
    {
        $order = $this->order();
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50000,
            'method' => 'cash',
            'status' => 'paid',
            'transaction_code' => 'TX-LOCK-1',
        ]);

        $payment->refresh()->load('order.invoice', 'order.payments', 'invoice');

        $this->assertTrue($payment->isSettled());
        $this->assertTrue($payment->isLocked());
        $this->assertFalse($payment->canEdit());
        $this->assertFalse($payment->canDelete());
    }

    public function test_pending_payment_is_not_locked(): void
    {
        $order = $this->order();
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50000,
            'method' => 'cash',
            'status' => 'pending',
        ]);

        $payment->refresh()->load('order.invoice', 'order.payments', 'invoice');

        $this->assertFalse($payment->isLocked());
        $this->assertTrue($payment->canEdit());
    }

    public function test_owner_can_edit_or_delete_paid_payment(): void
    {
        $order = $this->order();
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        // Owner (admin) CAN edit paid payment
        $this->actingAs($this->admin)
            ->get(route('payments.edit', $payment->id))
            ->assertStatus(200);

        // Owner CAN delete paid payment
        $this->actingAs($this->admin)
            ->delete(route('payments.destroy', $payment->id))
            ->assertRedirect();

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_manager_cannot_edit_or_delete_paid_payment(): void
    {
        $order = $this->order();
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        // Manager CANNOT edit paid payment
        $this->actingAs($this->manager)
            ->get(route('payments.edit', $payment->id))
            ->assertForbidden();

        // Manager CANNOT delete paid payment
        $this->actingAs($this->manager)
            ->delete(route('payments.destroy', $payment->id))
            ->assertForbidden();

        $this->assertNotSoftDeleted('payments', ['id' => $payment->id]);
    }

    // ---------- 4. Chi tiết đơn của đơn đã khoá ----------

    public function test_cannot_edit_order_item_of_paid_order(): void
    {
        $order = $this->order();
        Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'service_id' => \App\Models\Service::factory()->create()->id,
            'item_name' => 'Áo',
            'item_type' => 'garment',
            'price' => 100000,
            'quantity' => 1,
            'subtotal' => 100000,
        ]);

        $this->actingAs($this->admin)
            ->get(route('order-items.edit', $item->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->delete(route('order-items.destroy', $item->id))
            ->assertRedirect();

        $this->assertNotSoftDeleted('order_items', ['id' => $item->id]);
    }

    // ---------- Bản ghi đã xoá mềm không được tính là tiền ----------

    public function test_soft_deleted_payment_is_not_counted_as_collected_money(): void
    {
        $order = $this->order();

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        $order->refresh()->load('payments', 'invoice');
        $this->assertSame(100000.0, $order->paidAmount());

        $payment->delete();

        $order->refresh()->load('payments', 'invoice');

        $this->assertSame(0.0, $order->paidAmount(), 'Khoản thu đã xoá mềm không phải tiền còn trong quỹ.');
        $this->assertSame('pending', $order->payment_status);
        $this->assertFalse($order->hasSettledPayment());
        $this->assertFalse($order->isLocked());
    }

    public function test_soft_deleted_order_item_is_excluded_from_order_aggregates(): void
    {
        $order = $this->order();

        $item = OrderItem::create([
            'order_id' => $order->id,
            'service_id' => \App\Models\Service::factory()->create()->id,
            'item_name' => 'Áo',
            'item_type' => 'garment',
            'price' => 50000,
            'quantity' => 2,
            'subtotal' => 100000,
        ]);

        $order->refresh()->load('items');
        $this->assertCount(1, $order->items);
        $this->assertSame(2, (int) $order->items->sum('quantity'));

        $item->delete();

        $order->refresh()->load('items');
        $this->assertCount(0, $order->items, 'Chi tiết đã xoá mềm không được tính vào danh sách đơn.');
        $this->assertSame(0, (int) $order->items->sum('quantity'));

        $this->assertCount(1, $order->itemsWithTrashed()->get());
    }

    public function test_soft_deleted_order_is_excluded_from_customer_totals(): void
    {
        $order = $this->order(['total_amount' => 100000]);
        $customer = $order->customer;

        $service = new \App\Services\CustomerService();

        $this->assertSame(100000.0, $service->getTotalSpent($customer));
        $this->assertSame(1, $service->getOrderCount($customer));

        $order->delete();

        $customer->refresh();
        $this->assertSame(0.0, $service->getTotalSpent($customer), 'Đơn đã xoá mềm không được tính vào tổng chi tiêu.');
        $this->assertSame(0, $service->getOrderCount($customer));
    }

    // ---------- Khoá đơn không được nới trong Service ----------

    public function test_cannot_record_payment_against_a_settled_order(): void
    {
        $order = $this->order(['status' => 'completed']);

        $this->actingAs($this->admin)
            ->post(route('payments.store'), [
                'order_id' => $order->id,
                'amount' => 50000,
                'method' => 'cash',
                'status' => 'partial',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('payments', ['order_id' => $order->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'completed']);
    }

    public function test_partial_payment_cannot_unlock_a_completed_order(): void
    {
        $order = $this->order(['status' => 'completed', 'total_amount' => 100000]);

        Payment::create([
            'order_id' => $order->id,
            'amount' => 50000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        $order->refresh();

        // Đơn 'completed' phải khoá dù tổng tiền đã thu chưa đủ
        $this->assertTrue($order->isLocked());
        $this->assertSame(50000.0, $order->paidAmount());
    }

    public function test_cannot_create_invoice_for_a_settled_order(): void
    {
        $order = $this->order(['status' => 'completed']);

        $this->actingAs($this->admin)
            ->post(route('invoices.store'), [
                'order_id' => $order->id,
                'grand_total' => 100000,
                'status' => 'unpaid',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('invoices', ['order_id' => $order->id]);
    }

    // ---------- Cờ cấu hình khoá ----------

    public function test_payment_lock_rule_can_be_disabled_by_config(): void
    {
        $order = $this->order();
        Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        config(['app.lock_settled.payment' => false]);

        $order->refresh()->load('payments', 'invoice');

        // Tắt cờ chỉ tắt luật khoá, phép tính tiền và cột "Thanh toán" vẫn đúng
        $this->assertSame(100000.0, $order->paidAmount());
        $this->assertTrue($order->hasSettledPayment(), 'Phép tính tiền không phụ thuộc cờ cấu hình.');
        $this->assertSame('paid', $order->payment_status, 'Trạng thái thanh toán vẫn phản ánh tiền thực.');
        $this->assertFalse($order->isLocked(), 'Tắt cờ thì đơn không bị khoá theo tiền đã thu.');
    }

    // ---------- Giao diện ----------

    public function test_index_hides_edit_buttons_for_locked_records(): void
    {
        $order = $this->order();
        Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        $this->actingAs($this->manager)
            ->get(route('orders.index'))
            ->assertStatus(200)
            ->assertSee('Đã quyết toán')
            ->assertDontSee('deleteOrderForm_' . $order->id);
    }

    public function test_invoice_payment_and_item_index_hide_actions_when_locked(): void
    {
        $order = $this->order();

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 100000,
            'method' => 'cash',
            'status' => 'paid',
        ]);

        $invoice = Invoice::create([
            'code' => 'HD-LOCK-3',
            'order_id' => $order->id,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'grand_total' => 100000,
            'total' => 100000,
            'status' => 'paid',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'service_id' => \App\Models\Service::factory()->create()->id,
            'item_name' => 'Áo',
            'item_type' => 'garment',
            'price' => 100000,
            'quantity' => 1,
            'subtotal' => 100000,
        ]);

        // Hoá đơn đã thanh toán -> ẩn nút Sửa/Xoá
        $this->actingAs($this->manager)
            ->get(route('invoices.index'))
            ->assertStatus(200)
            ->assertSee('Đã thanh toán')
            ->assertDontSee('deleteInvoiceForm_' . $invoice->id);

        // Khoản thu đã ghi nhận -> ẩn nút Sửa/Xoá cho Manager
        $this->actingAs($this->manager)
            ->get(route('payments.index'))
            ->assertStatus(200)
            ->assertSee('Đã thanh toán')
            ->assertDontSee('deletePaymentForm_' . $payment->id);

        // Owner CÓ thấy nút Sửa/Xoá cho khoản thu đã thanh toán
        $this->actingAs($this->admin)
            ->get(route('payments.index'))
            ->assertStatus(200)
            ->assertSee('deletePaymentForm_' . $payment->id);

        // Đơn đã quyết toán -> ẩn nút Sửa/Xoá trên chi tiết đơn
        $this->actingAs($this->admin)
            ->get(route('order-items.index'))
            ->assertStatus(200)
            ->assertSee('Đã quyết toán')
            ->assertDontSee('deleteOrderItemForm_' . $item->id);
    }
}
