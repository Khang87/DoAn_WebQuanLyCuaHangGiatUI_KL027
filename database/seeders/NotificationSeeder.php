<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $users = User::whereIn('role', ['manager', 'staff'])->get();
        $orders = Order::limit(5)->get();

        $notifications = [
            [
                'user_id' => $users->first()?->id,
                'type' => 'order',
                'message' => 'Đơn hàng #DH001 đã hoàn thành',
                'order_id' => $orders->first()?->id,
                'sent_at' => Carbon::now()->subHours(2),
                'read_at' => Carbon::now()->subHours(2),
            ],
            [
                'user_id' => $users->first()?->id,
                'type' => 'customer',
                'message' => 'Khách hàng mới đăng ký',
                'order_id' => null,
                'sent_at' => Carbon::now()->subHours(5),
                'read_at' => Carbon::now()->subHours(5),
            ],
            [
                'user_id' => $users->skip(1)->first()?->id ?? $users->first()?->id,
                'type' => 'payment',
                'message' => 'Thanh toán đơn hàng #' . ($orders->skip(1)->first()?->code ?? 'DH002') . ' đã được xác nhận.',
                'order_id' => $orders->skip(1)->first()?->id,
                'sent_at' => Carbon::now()->subDay(),
                'read_at' => null,
            ],
            [
                'user_id' => $users->first()?->id,
                'type' => 'booking',
                'message' => 'Khách hàng vừa đặt lịch giặt ủi.',
                'order_id' => null,
                'sent_at' => Carbon::now()->subHours(3),
                'read_at' => null,
            ],
            [
                'user_id' => $users->first()?->id,
                'type' => 'promotion',
                'message' => 'Chương trình Giảm 20% sẽ hết hạn trong 5 ngày.',
                'order_id' => null,
                'sent_at' => Carbon::now()->subHours(1),
                'read_at' => null,
            ],
        ];

        foreach ($notifications as $notif) {
            Notification::create($notif);
        }
    }
}
