<?php

namespace Database\Seeders;

use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $notifications = [
            ['title' => 'Đơn hàng #DH001 đã hoàn thành', 'message' => 'Đơn hàng giặt ủi đã được xử lý xong.', 'read_at' => Carbon::now()->subHours(2)],
            ['title' => 'Khách hàng mới đăng ký', 'message' => 'Khách hàng mới vừa đăng ký tài khoản.', 'read_at' => Carbon::now()->subHours(5)],
            ['title' => 'Thanh toán đã được nhận', 'message' => 'Thanh toán đơn hàng #DH002 đã được xác nhận.', 'read_at' => Carbon::now()->subDay()],
            ['title' => 'Đặt lịch mới', 'message' => 'Khách hàng vừa đặt lịch giặt ủi.', 'read_at' => null],
            ['title' => 'Khuyến mãi sắp hết hạn', 'message' => 'Chương trình Giảm 20% sẽ hết hạn trong 5 ngày.', 'read_at' => null],
        ];

        foreach ($notifications as $notif) {
            Notification::create($notif);
        }
    }
}
