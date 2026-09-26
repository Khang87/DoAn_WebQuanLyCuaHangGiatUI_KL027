<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Invoice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $orders = Order::whereIn('status', ['completed', 'processing', 'ready_for_pickup'])->get();

        foreach ($orders as $index => $order) {
            $subtotal = (float) ($order->subtotal ?: $order->total_amount);
            $discountAmount = round((float) $order->discount_by_promotion + (float) $order->discount_by_points, 2);
            $deliveryFee = $order->delivery ? 15000 : 0;
            $grandTotal = max(0, $subtotal - $discountAmount + $deliveryFee);

            Invoice::create([
                'order_id' => $order->id,
                'code' => 'HD' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'invoice_date' => $order->created_at ? $order->created_at->toDateString() : now()->toDateString(),
                'total' => $grandTotal,
                'total_amount' => $subtotal,
                'discount_amount' => $discountAmount,
                'delivery_fee' => $deliveryFee,
                'grand_total' => $grandTotal,
                'status' => in_array($order->status, ['completed', 'ready_for_pickup']) ? 'paid' : 'unpaid',
                'notes' => 'Hóa đơn cho đơn hàng #' . $order->code,
                'created_at' => $order->created_at,
                'updated_at' => $order->created_at,
            ]);
        }
    }
}
