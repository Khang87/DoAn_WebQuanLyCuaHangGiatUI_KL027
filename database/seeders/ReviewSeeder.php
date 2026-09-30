<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\DanhGia;
use App\Models\DonHang;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Sinh đánh giá khách hàng trong `DanhGia` cho các đơn đã hoàn thành.
 *
 * `SoSao` nằm trong khoảng 1-5, `TrangThai` nhận 'Hiển thị' hoặc 'Ẩn'.
 */
class ReviewSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, list<string>>
     */
    private const COMMENTS = [
        5 => [
            'Đồ giặt rất sạch, thơm lâu, giao đúng hẹn! Sẽ tiếp tục ủng hộ cửa hàng.',
            'Dịch vụ tuyệt vời! Nhân viên chuyên nghiệp, nhiệt tình. Đồ giặt sạch như mới.',
            'Rất hài lòng! Đồ đã sạch sẽ, giao đúng hẹn. Giá cả lại hợp lý.',
            'Giặt rất sạch, đóng gói cẩn thận. Tuyệt vời! Sẽ giới thiệu cho bạn bè.',
            'Đơn hàng đúng mẫu, giặt nhanh chóng. Cảm ơn nhân viên nhiệt tình!',
        ],
        4 => [
            'Dịch vụ tốt, đồ giặt sạch sẽ. Tuy nhiên giao hơi trễ 15 phút.',
            'Chất lượng tốt nhưng hơi lâu thời gian chờ. Đồ đã sạch, hy vọng cải thiện thời gian.',
            'Dịch vụ tốt, đồ giặt sạch. Chỉ có chờ hơi lâu thời gian giao hàng.',
        ],
        3 => [
            'Chất lượng tạm ổn, quần áo phẳng phiu nhưng mùi nước hoa hơi nồng.',
            'Dịch vụ bình thường, đồ giặt được rửa sạch nhưng chưa thơm thoảng thoải.',
        ],
        2 => [
            'Xử lý vết bẩn chưa sạch hẳn, cần chờ lâu hơn. Không hài lòng với kết quả.',
            'Đồ giặt còn bẩn, chưa đạt yêu cầu. Phải đưa ra sửa lại.',
        ],
        1 => [
            'Dịch vụ kém, đồ giặt chưa sạch sẽ, thất vọng. Cần cải thiện ngay.',
            'Giao hàng chậm trễ, không liên hệ được. Chưa hài lòng với dịch vụ.',
        ],
    ];

    public function run(): void
    {
        $orders = DonHang::with('danhGia')
            ->whereIn('TrangThai', [OrderStatus::Delivered->value, OrderStatus::Paid->value])
            ->whereDoesntHave('danhGia')
            ->inRandomOrder()
            ->limit(8)
            ->get();

        if ($orders->isEmpty()) {
            $this->command?->info('Không có đơn hoàn thành nào để seed đánh giá.');

            return;
        }

        $ratings = [5, 5, 5, 5, 4, 4, 4, 3, 3, 2, 1];

        foreach ($orders as $index => $order) {
            $rating = $ratings[$index % count($ratings)];
            $comments = self::COMMENTS[$rating];

            DanhGia::create([
                'DonHangID' => $order->DonHangID,
                'KhachHangID' => $order->KhachHangID,
                'SoSao' => $rating,
                'BinhLuan' => $comments[array_rand($comments)],
                'NgayDanhGia' => ($order->NgayCapNhat ?? $order->NgayTao ?? now())->copy()->addHours(random_int(6, 72)),
                'TrangThai' => $rating >= 3 ? 'Hiển thị' : 'Ẩn',
            ]);
        }

        $this->command?->info('Đã tạo '.$orders->count().' đánh giá.');
    }
}
