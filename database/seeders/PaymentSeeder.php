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
        $methods = ['cash', 'bank_transfer', 'momo', 'credit_card', 'e_wallet'];
        $statuses = ['paid', 'paid', 'paid', 'paid', 'pending', 'failed', 'refunded'];

        foreach ($orders as $order) {
            $paymentStatus = $statuses[array_rand($statuses)];
            $paidAt = null;
            $transactionCode = null;

            if ($paymentStatus === 'paid') {
                $paidAt = $order->created_at ? $order->created_at->copy()->addHours(rand(1, 48)) : now();
                $transactionCode = 'TXN' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
            }

            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'method' => $methods[array_rand($methods)],
                'paid_at' => $paidAt,
                'status' => $paymentStatus,
                'transaction_code' => $transactionCode,
                'created_at' => $order->created_at,
                'updated_at' => $order->created_at,
            ]);
        }

        $otherOrders = Order::whereNotIn('status', ['completed'])->get();
        foreach ($otherOrders->take(5) as $order) {
            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total_amount / 2,
                'method' => $methods[array_rand($methods)],
                'paid_at' => null,
                'status' => $statuses[array_rand($statuses)],
                'transaction_code' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $pendingOrders = Order::where('status', 'processing')->take(3)->get();
        foreach ($pendingOrders as $order) {
            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'method' => $methods[array_rand($methods)],
                'paid_at' => null,
                'status' => 'pending',
                'transaction_code' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
