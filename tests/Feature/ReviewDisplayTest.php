<?php

namespace Tests\Feature;

use App\Models\DanhGia;
use App\Models\DonHang;
use App\Models\KhachHang;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ReviewDisplayTest extends TestCase
{
    public function test_review_list_displays_order_code_and_customer_name_without_technical_ids(): void
    {
        $customer = new KhachHang;
        $customer->setAttribute('KhachHangID', 7);
        $customer->setAttribute('HoTen', 'Khách hàng kiểm thử');
        $customer->setRelation('taiKhoan', null);

        $order = new DonHang;
        $order->setAttribute('DonHangID', 24);
        $order->setAttribute('MaDonHang', 'DH0024');

        $review = new DanhGia;
        $review->setAttribute('DanhGiaID', 3);
        $review->setAttribute('DonHangID', 24);
        $review->setAttribute('KhachHangID', 7);
        $review->setAttribute('SoSao', 5);
        $review->setAttribute('BinhLuan', 'Dịch vụ sạch sẽ và đúng hẹn.');
        $review->setAttribute('NgayDanhGia', '2026-10-06 14:50:00');
        $review->setAttribute('TrangThai', 'Hiển thị');
        $review->setRelation('donHang', $order);
        $review->setRelation('khachHang', $customer);

        $reviews = new LengthAwarePaginator(collect([$review]), 1, 10);
        $html = view('admin.reviews.index', [
            'reviews' => $reviews,
            'averageRating' => 5,
            'totalReviews' => 1,
        ])->render();

        $this->assertStringContainsString('DH00000024', $html);
        $this->assertStringNotContainsString('KH007', $html);
        $this->assertStringContainsString('Khách hàng kiểm thử', $html);
        $this->assertStringContainsString('Dịch vụ sạch sẽ và đúng hẹn.', $html);
        $this->assertStringContainsString('06/10/2026 14:50', $html);
        $this->assertStringContainsString('<th>Ngày đánh giá</th>', $html);
        $this->assertStringContainsString('<th scope="col">STT</th>', $html);
    }

    public function test_review_summary_aligns_rating_and_total_on_the_same_text_baseline(): void
    {
        foreach ([[4.0, 11], [0.0, 0]] as [$average, $total]) {
            $html = view('admin.reviews.index', [
                'reviews' => new LengthAwarePaginator([], 0, 10),
                'averageRating' => $average,
                'totalReviews' => $total,
            ])->render();

            $this->assertMatchesRegularExpression(
                '/<div class="d-flex flex-wrap align-items-baseline gap-3">\s*<span class="display-5 fw-bold text-warning">'.preg_quote(number_format($average, 1), '/').'<\/span>.*?Tổng <strong>'.$total.'<\/strong> đánh giá<\/div>\s*<\/div>/s',
                $html,
            );
        }
    }
}
