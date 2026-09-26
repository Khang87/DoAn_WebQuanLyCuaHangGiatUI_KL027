<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $orders = Order::whereIn('status', ['completed', 'delivering', 'washed'])
            ->whereDoesntHave('review')
            ->inRandomOrder()
            ->get();

        if ($orders->isEmpty()) {
            $orders = Order::inRandomOrder()->limit(5)->get();
        }

        $reviewTemplates = [
            5 => [
                'Đồ giặt rất sạch, thơm lâu, giao đúng hẹn! Sẽ tiếp tục ủng hộ cửa hàng.',
                'Dịch vụ tuyệt vời! Nhân viên chuyên nghiệp, nhiệt tình. Đồ giặt sạch như mới.',
                'Rất hài lòng! Đồ đã sạch sẽ, giao đúng hẹn. Giá cả lại hợp lý.',
                'Giặt rất sạch, đóng gói cẩn thận. Tuyệt vời! Sẽ giới thiệu cho bạn bè.',
                'Đơn hàng đúng mẫu, giặt nhanh chóng. Cảm ơn nhân viên nhiệt tình!',
            ],
            4 => [
                'Dịch vụ tốt, đồ giặt sạch sẽ. Tuy nhiên giao hơi trễ 15 phút nhưng nhân viên có báo trước.',
                'Chất lượng tốt nhưng hơi mất thời gian chờ. Đồ đã sạch, hy vọng cải thiện thời gian.',
                'Dịch vụ tốt, đồ giặt sạch. Chỉ có chút chậm về thời gian giao hàng.',
                'Đồ giặt sạch nhưng giao hàng chậm hơn chút. Nhìn chung vẫn khá hài lòng.',
            ],
            3 => [
                'Chất lượng tạm ổn, quần áo phẳng phiu nhưng mùi nước hoa hơi nồng.',
                'Dịch vụ bình thường, đồ giặt được rửa sạch nhưng chưa thơm thoang thoả.',
                'Giặt được sạch nhưng cảm giác chung chưa thật sự ấn tượng.',
            ],
            2 => [
                'Xử lý vết bẩn chưa sạch hẳn, cần chú ý hơn. Không hài lòng với kết quả.',
                'Đồ giặt còn bẩn, chưa đạt yêu cầu. Phải đưa ra sửa lại.',
            ],
            1 => [
                'Dịch vụ kém, đồ giặt chưa sạch sẽ, thất vọng. Cần cải thiện ngay.',
                'Giao hàng chậm trễ, không liên hệ được. Chưa hài lòng với dịch vụ.',
            ],
        ];

        $ratingPool = [5, 5, 5, 5, 5, 5, 5, 4, 4, 4, 4, 3, 3, 2, 1];

        $shopResponses = [
            'Cảm ơn bạn đã tin tưởng dịch vụ của giặt ủi! Chúng tôi sẽ phấn đấu hoàn thiện hơn.',
            'Cảm ơn bạn đã đánh giá! Chúng tôi xin lỗi vì sự bất tiện và sẽ cải thiện ngay.',
            'Cửa hàng chân thành xin lỗi vì đã không hoàn hảo. Đã ghi nhận phản hồi và sẽ khắt khe hơn.',
            'Rất cảm ơn phản hồi của bạn! Chúng tôi đang cập nhật quy trình để tốt hơn.',
            'Cảm ơn bạn đã ủng hộ! Chúng tôi sẽ cố gắng hơn trong lần tới.',
        ];

        $imageArrays = [
            null,
            ['images/cloth1.jpg', 'images/cloth2.jpg'],
            ['images/cloth3.jpg'],
        ];

        $visibleCount = 0;

        foreach ($orders as $index => $order) {
            $rating = $ratingPool[$index % count($ratingPool)];
            $contents = $reviewTemplates[$rating];

            $dayOffset = $orders->count() - $index;
            $createdAt = Carbon::now()->subDays($dayOffset)->subHours(rand(0, 12));

            $hasShopResponse = rand(0, 3) === 0;
            $status = $visibleCount < (int) floor($orders->count() * 0.8)
                ? 'visible'
                : 'hidden';
            if ($status === 'visible') {
                $visibleCount++;
            }

            Review::create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'rating' => $rating,
                'content' => $contents[array_rand($contents)],
                'images' => $imageArrays[array_rand($imageArrays)],
                'shop_response' => $hasShopResponse ? $shopResponses[array_rand($shopResponses)] : null,
                'status' => $status,
                'reviewed_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
