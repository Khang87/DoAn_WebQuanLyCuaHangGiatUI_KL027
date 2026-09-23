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
        $orders = Order::whereIn('status', ['completed', 'processing'])->get();

        foreach ($orders as $index => $order) {
            Invoice::create([
                'order_id' => $order->id,
                'code' => 'HD' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'total' => $order->total_amount,
                'status' => $order->status === 'completed' ? 'paid' : 'unpaid',
                'notes' => 'Hóa đơn cho đơn hàng #' . $order->code,
                'created_at' => $order->created_at,
                'updated_at' => $order->created_at,
            ]);
        }
    }
}
