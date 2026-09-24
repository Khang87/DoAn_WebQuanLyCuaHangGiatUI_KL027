<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $orders = Order::where('status', 'completed')->get();
        $methods = ['cash', 'bank_transfer', 'e_wallet'];

        foreach ($orders as $order) {
            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'method' => $methods[array_rand($methods)],
                'status' => 'paid',
                'created_at' => $order->created_at,
                'updated_at' => $order->created_at,
            ]);
        }

        // Also create some pending payments for non-completed orders
        $otherOrders = Order::whereNotIn('status', ['completed'])->get();
        foreach ($otherOrders->take(5) as $order) {
            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total_amount / 2,
                'method' => $methods[array_rand($methods)],
                'status' => 'partial',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create pending payments for some new orders
        $pendingOrders = Order::where('status', 'processing')->take(3)->get();
        foreach ($pendingOrders as $order) {
            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'method' => $methods[array_rand($methods)],
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
