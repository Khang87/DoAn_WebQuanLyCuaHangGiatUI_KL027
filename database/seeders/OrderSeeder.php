<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\BangGia;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\KhuyenMai;
use App\Models\NhanVien;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Nạp đơn hàng vào `DonHang` kèm chi tiết trong `ChiTietDonHang`.
 *
 * Giá và đơn vị tính lấy từ `BangGia` (PricingSeeder). Ràng buộc
 * `CK_CTDH_SoLuongKhoiLuong` bắt buộc mỗi dòng chi tiết có đúng
 * `SoLuong` (đơn vị cái/bộ/đôi) hoặc `KhoiLuong` (đơn vị kilogram).
 */
class OrderSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Trạng thái đơn hàng được dùng khi seed, theo đúng giá trị trong `DonHang`.
     *
     * @var list<string>
     */
    private const STATUSES = [
        OrderStatus::Pending->value,
        OrderStatus::Pending->value,
        OrderStatus::Received->value,
        OrderStatus::Washing->value,
        OrderStatus::Washed->value,
        OrderStatus::Delivering->value,
        OrderStatus::Delivered->value,
        OrderStatus::Paid->value,
        OrderStatus::Cancelled->value,
    ];

    private const ITEM_NOTES = [
        'Giặt riêng áo trắng, không tẩy mạnh.',
        'Váy cưới vải voan mỏng, cần giặt hấp nhẹ nhàng.',
        'Chăn bông có vết ố, xử lý kỹ thuốt.',
        'Ơi gấp nếp quần tây phẳng, giao trước 17h.',
        'Giày Sneaker vải lưới, không phơi nắng.',
        'Áo len nhỏ, giặt nước lạnh.',
        'Đồ trẻ em nhạy cảm da, dùng xà phòng không hương liệu.',
    ];

    public function run(): void
    {
        if (DonHang::query()->exists()) {
            $this->command?->info('Bảng DonHang đã có dữ liệu, bỏ qua seed đơn hàng.');

            return;
        }

        $customers = KhachHang::where('TrangThai', 'Hoạt động')->get();
        $prices = BangGia::with('donViTinh')->where('TrangThai', 'Hoạt động')->get();
        $staff = NhanVien::where('TrangThai', 'Hoạt động')->get();
        $promotions = KhuyenMai::where('TrangThai', 'Hoạt động')->get();

        if ($customers->isEmpty() || $prices->isEmpty()) {
            $this->command?->warn('Cần có khách hàng và bảng giá trước khi seed đơn hàng.');

            return;
        }

        $sequence = (int) (DonHang::max('DonHangID') ?? 0);
        $created = 0;

        foreach ($customers as $customer) {
            foreach (range(1, random_int(2, 4)) as $ignored) {
                $sequence++;
                $createdAt = now()->subDays(random_int(1, 30))->subHours(random_int(0, 23));
                $status = self::STATUSES[array_rand(self::STATUSES)];

                $items = $prices->random(random_int(1, min(3, $prices->count())));
                $subtotal = 0;

                $order = DonHang::create([
                    'MaDonHang' => 'DH'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                    'KhachHangID' => $customer->KhachHangID,
                    'NhanVienID' => $staff->isNotEmpty() ? $staff->random()->NhanVienID : null,
                    'TrangThai' => $status,
                    'TongTien' => 0,
                    'DiemSuDung' => 0,
                    'TienGiamDoDiem' => 0,
                    'TienGiamKhuyenMai' => 0,
                    'PhiGiaoHang' => 0,
                    'ThanhTien' => 0,
                    'GhiChu' => null,
                    'NgayTao' => $createdAt,
                    'NgayCapNhat' => $createdAt,
                ]);

                foreach ($items as $price) {
                    $isWeight = $price->donViTinh?->TenDonViTinh === 'Kilogram';
                    $amount = $isWeight ? random_int(2, 8) : random_int(1, 4);
                    $lineTotal = $amount * (float) $price->DonGia;

                    ChiTietDonHang::create([
                        'DonHangID' => $order->DonHangID,
                        'DichVuID' => $price->DichVuID,
                        'LoaiDoGiatID' => $price->LoaiDoGiatID,
                        'DonViTinhID' => $price->DonViTinhID,
                        $isWeight ? 'KhoiLuong' : 'SoLuong' => $amount,
                        'DonGia' => $price->DonGia,
                        'ThanhTien' => $lineTotal,
                        'GhiChu' => self::ITEM_NOTES[array_rand(self::ITEM_NOTES)],
                    ]);

                    $subtotal += $lineTotal;
                }

                $promotion = $promotions->first(
                    fn (KhuyenMai $promo) => $subtotal >= (float) ($promo->GiaTriDonToiThieu ?? 0)
                );

                $discount = $promotion
                    ? ($promotion->LoaiKhuyenMai === 'Phần trăm'
                        ? min($subtotal * ((float) $promotion->GiaTriGiam / 100), (float) ($promotion->MucGiamToiDa ?? INF))
                        : min((float) $promotion->GiaTriGiam, $subtotal))
                    : 0;

                $deliveryFee = $status === OrderStatus::Cancelled->value ? 0 : 20000;
                $total = max(0, $subtotal - $discount + $deliveryFee);

                $order->update([
                    'TongTien' => $subtotal,
                    'KhuyenMaiID' => $promotion?->KhuyenMaiID,
                    'TienGiamKhuyenMai' => $discount,
                    'PhiGiaoHang' => $deliveryFee,
                    'ThanhTien' => $total,
                ]);

                $created++;
            }
        }

        $this->command?->info("Đã tạo {$created} đơn hàng mẫu.");
    }
}
