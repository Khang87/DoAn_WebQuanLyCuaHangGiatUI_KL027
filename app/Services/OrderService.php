<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\SettledOrderException;
use App\Models\BangGia;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\DonViTinh;
use App\Models\KhachHang;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private TinhTienGiatUiService $tinhTienGiatUiService,
    ) {}

    /**
     * Quy ước 1 điểm tích lũy thành tiền.
     */
    public const POINT_VALUE = 1000;

    /**
     * Lý do voucher cuối cùng bị loại trong lần gọi create()/update() gần nhất.
     */
    private ?string $promotionRejection = null;

    /**
     * Lý do voucher bị loại ở lần xử lý gần nhất (null nghĩa là không bị loại).
     */
    public function promotionRejection(): ?string
    {
        return $this->promotionRejection;
    }

    /**
     * Chuẩn hóa bộ số tiền của đơn hàng.
     *
     *   Tạm tính            = Σ (số lượng hoặc kg × Đơn giá lịch sử theo BangGia)
     *   Tiền giảm khuyến mãi = KhuyenMai::calculateDiscount(Tạm tính)
     *   Tiền giảm do điểm    = số điểm dùng × POINT_VALUE
     *   Tổng thanh toán      = Tạm tính - giảm khuyến mãi - giảm điểm (không nhỏ hơn 0)
     *
     * Mỗi khoản giảm được chặn tối đa bằng số tiền còn lại để tổng không âm.
     *
     * @return array{TongTien: float, TienGiamKhuyenMai: float, DiemSuDung: int, TienGiamDoDiem: float, ThanhTien: float}
     */
    public function calculateAmounts(float $subtotal, ?KhuyenMai $promotion, int $pointsUsed, int $customerPoints): array
    {
        $subtotal = max(0, $subtotal);

        $discountByPromotion = $promotion
            ? min($promotion->calculateDiscount($subtotal), $subtotal)
            : 0.0;

        // Không cho dùng vượt số điểm khách đang có.
        $pointsUsed = max(0, min($pointsUsed, $customerPoints));

        $remaining = max(0, $subtotal - $discountByPromotion);
        $discountByPoints = min($pointsUsed * self::POINT_VALUE, $remaining);

        // Số điểm thực sự quy ước được thành tiền (tránh ghi điểm "lãng phí").
        $effectivePoints = (int) floor($discountByPoints / self::POINT_VALUE);

        return [
            'TongTien' => round($subtotal, 2),
            'TienGiamKhuyenMai' => round($discountByPromotion, 2),
            'DiemSuDung' => $effectivePoints,
            'TienGiamDoDiem' => round($discountByPoints, 2),
            'ThanhTien' => round(max(0, $subtotal - $discountByPromotion - $discountByPoints), 2),
        ];
    }

    /**
     * Tạo các dòng mặt hàng từ dữ liệu form, chốt Đơn giá lịch sử và tính tạm tính.
     *
     * Đơn giá lấy từ BangGia theo bộ ba DichVuID + LoaiDoGiatID + DonViTinhID:
     *   - Đơn vị "kg"  → Tạm tính = max(Khối lượng, mức tối thiểu) × Đơn giá
     *   - Đơn vị khác  → Tạm tính = Số lượng × Đơn giá
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function buildItems(?array $rawItems): array
    {
        $rows = [];
        $calculationItems = [];
        $items = $rawItems ?? [];
        $pricingTuples = [];
        $unitIds = [];

        foreach ($items as $item) {
            $serviceId = $item['DichVuID'] ?? $item['service_id'] ?? null;
            $garmentId = $item['LoaiDoGiatID'] ?? $item['garment_id'] ?? null;
            $unitId = $item['DonViTinhID'] ?? $item['unit_id'] ?? null;

            if ($serviceId && $garmentId && $unitId) {
                $key = (int) $serviceId.':'.(int) $garmentId.':'.(int) $unitId;
                $pricingTuples[$key] = [
                    'DichVuID' => (int) $serviceId,
                    'LoaiDoGiatID' => (int) $garmentId,
                    'DonViTinhID' => (int) $unitId,
                ];
            }

            if ($unitId !== null && $unitId !== '') {
                $unitIds[] = (int) $unitId;
            }
        }

        $pricingByTuple = [];
        if ($pricingTuples !== []) {
            $pricingRows = BangGia::query()
                ->select(['BangGiaID', 'DichVuID', 'LoaiDoGiatID', 'DonViTinhID', 'DonGia'])
                ->where('TrangThai', 'Hoạt động')
                ->where(function ($dateQuery): void {
                    $dateQuery->whereNull('NgayApDung')->orWhereDate('NgayApDung', '<=', today());
                })
                ->where(function ($dateQuery): void {
                    $dateQuery->whereNull('NgayKetThuc')->orWhereDate('NgayKetThuc', '>=', today());
                })
                ->where(function ($tupleQuery) use ($pricingTuples): void {
                    foreach ($pricingTuples as $tuple) {
                        $tupleQuery->orWhere(function ($query) use ($tuple): void {
                            $query->where('DichVuID', $tuple['DichVuID'])
                                ->where('LoaiDoGiatID', $tuple['LoaiDoGiatID'])
                                ->where('DonViTinhID', $tuple['DonViTinhID']);
                        });
                    }
                })
                ->orderByRaw('CASE WHEN "NgayApDung" IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('NgayApDung')
                ->orderByDesc('BangGiaID')
                ->get();

            foreach ($pricingRows as $pricingRow) {
                $key = $pricingRow->DichVuID.':'.$pricingRow->LoaiDoGiatID.':'.$pricingRow->DonViTinhID;
                $pricingByTuple[$key] ??= $pricingRow;
            }
        }

        $units = $unitIds === []
            ? collect()
            : DonViTinh::query()
                ->select(['DonViTinhID', 'KyHieu', 'TenDonViTinh'])
                ->whereIn('DonViTinhID', array_values(array_unique($unitIds)))
                ->get()
                ->keyBy('DonViTinhID');

        foreach ($items as $itemIndex => $item) {
            $serviceId = $item['DichVuID'] ?? $item['service_id'] ?? null;
            $garmentId = $item['LoaiDoGiatID'] ?? $item['garment_id'] ?? null;
            $unitId = $item['DonViTinhID'] ?? $item['unit_id'] ?? null;
            if (! $serviceId || ! $garmentId || ! $unitId) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.DichVuID" => 'Hãy chọn đầy đủ dịch vụ, loại đồ giặt và đơn vị tính cho từng dòng.',
                ]);
            }

            $tupleKey = (int) $serviceId.':'.(int) $garmentId.':'.(int) $unitId;
            $pricing = $pricingByTuple[$tupleKey] ?? null;
            if (! $pricing) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.DonViTinhID" => 'Không có bảng giá đang hiệu lực cho tổ hợp dịch vụ, loại đồ và đơn vị tính đã chọn.',
                ]);
            }

            $price = (float) $pricing->DonGia;
            $quantityInput = $item['SoLuong'] ?? $item['quantity'] ?? null;
            $weightInput = $item['KhoiLuong'] ?? $item['weight'] ?? null;
            $quantity = $quantityInput !== null && $quantityInput !== '' ? (float) $quantityInput : null;
            $weight = $weightInput !== null && $weightInput !== '' ? (float) $weightInput : null;
            $unitRecord = $units->get((int) $unitId);

            if (! $unitRecord) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.DonViTinhID" => 'Đơn vị tính không tồn tại.',
                ]);
            }

            $unit = $unitRecord->KyHieu ?? $unitRecord->TenDonViTinh;
            $isWeightUnit = $unitRecord->isWeightUnit();
            if (
                ($isWeightUnit && ($weight === null || $weight <= 0 || $quantity !== null))
                || (! $isWeightUnit && ($quantity === null || $quantity <= 0 || $weight !== null))
            ) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.SoLuong" => $isWeightUnit
                        ? 'Chỉ nhập khối lượng lớn hơn 0 cho đơn vị tính theo kg.'
                        : 'Chỉ nhập số lượng lớn hơn 0 cho đơn vị tính theo món.',
                ]);
            }

            $minimumWeight = (float) config('giatui.khoi_luong_toi_thieu', 3.0);
            $billableWeight = $isWeightUnit ? max($weight ?? 0, $minimumWeight) : ($weight ?? 0);
            $calculationItem = [
                'SoLuong' => $quantity ?? 0,
                'KhoiLuong' => $weight ?? 0,
                'DonGia' => $price,
                'TenDonViTinh' => $unit,
                'MucToiThieu' => $minimumWeight,
            ];
            $lineSubtotal = $this->tinhTienGiatUiService->tinhThanhTienChiTiet($calculationItem);
            $calculationItems[] = $calculationItem;

            $rows[] = [
                'DichVuID' => $serviceId ?: null,
                'LoaiDoGiatID' => $garmentId ?: null,
                'DonViTinhID' => (int) $unitId,
                'DonGia' => $price,
                'SoLuong' => $isWeightUnit ? null : $quantity,
                'KhoiLuong' => $isWeightUnit ? $billableWeight : null,
                'ThanhTien' => $lineSubtotal,
                'GhiChu' => $item['GhiChu'] ?? $item['notes'] ?? null,
            ];

        }

        return [$rows, $this->tinhTienGiatUiService->tinhTongTienHoaDon($calculationItems)];
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = DonHang::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($orderQuery) use ($search) {
                $orderQuery->where('MaDonHang', 'like', "%{$search}%")
                    ->orWhereHas('khachHang', fn ($customerQuery) => $customerQuery
                        ->where('HoTen', 'like', "%{$search}%")
                        ->orWhere('SoDienThoai', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['customer_id'])) {
            $query->where('KhachHangID', $filters['customer_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('NgayTao', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('NgayTao', '<=', $filters['date_to']);
        }

        $sortMap = [
            'latest' => ['NgayTao', 'desc'],
            'oldest' => ['NgayTao', 'asc'],
            'code_asc' => ['MaDonHang', 'asc'],
            'code_desc' => ['MaDonHang', 'desc'],
            'total_desc' => ['ThanhTien', 'desc'],
            'total_asc' => ['ThanhTien', 'asc'],
        ];
        $sort = $filters['sort'] ?? 'latest';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['NgayTao', 'desc'];

        return $query->with([
            'khachHang',
            'nhanVien',
            'chiTietDonHangs.dichVu',
            'chiTietDonHangs.loaiDoGiat',
            'chiTietDonHangs.donViTinh',
            'khuyenMai',
            'hoaDons',
            'thanhToans',
            'booking',
        ])
            ->orderBy($sortBy, $sortOrder)
            ->paginate(10)
            ->withQueryString();
    }

    public function find(int $id): ?DonHang
    {
        return DonHang::with([
            'khachHang',
            'nhanVien',
            'chiTietDonHangs.dichVu',
            'chiTietDonHangs.loaiDoGiat',
            'chiTietDonHangs.donViTinh',
            'khuyenMai',
            'thanhToans',
            'hoaDons',
            'giaoNhans',
            'booking',
        ])->find($id);
    }

    /**
     * Xác định voucher từ form: ưu tiên KhuyenMaiID, nếu rỗng thì tra theo mã.
     */
    private function resolvePromotion(array $data): ?KhuyenMai
    {
        if (! empty($data['KhuyenMaiID'])) {
            return KhuyenMai::find($data['KhuyenMaiID']);
        }

        if (! empty($data['promotion_code'])) {
            return KhuyenMai::findByCode($data['promotion_code']);
        }

        return null;
    }

    /**
     * Loại voucher nếu điều kiện nghiệp vụ không cho phép khách dùng.
     *
     * Chính sách: Đơn vẫn được lưu nhưng KHÔNG áp dụng giảm giá, đồng thời ghi
     * nhận lý do để controller hiển thị cảnh báo cho người dùng.
     */
    private function applyPromotionConditions(?KhuyenMai $promotion, ?KhachHang $customer, float $subtotal, ?int $excludeOrderId = null): ?KhuyenMai
    {
        $this->promotionRejection = null;

        if (! $promotion) {
            return null;
        }

        $reason = $promotion->rejectionReasonForCustomer($customer, $subtotal, $excludeOrderId);

        if ($reason !== null) {
            $this->promotionRejection = $reason;

            return null;
        }

        return $promotion;
    }

    public function create(array $data): DonHang
    {
        $this->promotionRejection = null;

        return DB::transaction(function () use ($data) {
            if (empty($data['MaDonHang'])) {
                $data['MaDonHang'] = $this->generateOrderCode();
            }

            $data['NgayTao'] = $data['NgayTao'] ?? now();

            $customer = KhachHang::find($data['KhachHangID'] ?? null);

            [$items, $subtotal] = $this->buildItems($data['items'] ?? []);

            $promotion = $this->applyPromotionConditions(
                $this->resolvePromotion($data),
                $customer,
                $subtotal
            );

            $customerPoints = $customer?->points() ?? 0;

            $amounts = $this->calculateAmounts(
                $subtotal,
                $promotion,
                (int) ($data['DiemSuDung'] ?? 0),
                $customerPoints
            );

            // Trừ điểm tích lũy của khách hàng ngay trong cùng transaction.
            if ($customer && $amounts['DiemSuDung'] > 0) {
                $customer->deductPoints($amounts['DiemSuDung']);
            }

            $order = DonHang::create(array_merge($this->onlyOrderColumns($data), [
                'KhuyenMaiID' => $promotion?->KhuyenMaiID,
                'NgayCapNhat' => now(),
            ], $amounts));

            foreach ($items as $item) {
                if (empty($item['DichVuID']) || empty($item['LoaiDoGiatID']) || empty($item['DonViTinhID'])) {
                    continue;
                }

                ChiTietDonHang::create(array_merge(['DonHangID' => $order->DonHangID], $item));
            }

            return $order->fresh(['chiTietDonHangs', 'khachHang', 'khuyenMai']);
        });
    }

    public function update(DonHang $order, array $data, bool $overrideSettled = false): DonHang
    {
        if ($order->isLocked() && ! $overrideSettled) {
            throw SettledOrderException::forOrder($order->MaDonHang);
        }

        $this->promotionRejection = null;

        return DB::transaction(function () use ($order, $data) {
            $customer = ! empty($data['KhachHangID'])
                ? KhachHang::find($data['KhachHangID'])
                : $order->khachHang;

            [$items, $subtotal] = $this->buildItems($data['items'] ?? []);

            // Form gửi KhuyenMaiID (có thể rỗng) và/hoặc promotion_code.
            $touchesPromotion = array_key_exists('KhuyenMaiID', $data) || array_key_exists('promotion_code', $data);
            $promotion = $touchesPromotion
                ? $this->resolvePromotion($data)
                : $order->khuyenMai;

            // Chính sách điều kiện voucher: bỏ voucher nếu khách không thoả
            // điều kiện, đơn vẫn lưu bình thường.
            $promotion = $this->applyPromotionConditions($promotion, $customer, $subtotal, $order->DonHangID);

            // Hoàn lại số điểm đã dùng ở lần lưu trước để tính lại từ đầu.
            $previousPoints = (int) $order->DiemSuDung;
            if ($customer && $previousPoints > 0) {
                $customer->addPoints($previousPoints);
            }

            $customerPoints = $customer?->points() ?? 0;

            $amounts = $this->calculateAmounts(
                $subtotal,
                $promotion,
                (int) ($data['DiemSuDung'] ?? 0),
                $customerPoints
            );

            if ($customer && $amounts['DiemSuDung'] > 0) {
                $customer->deductPoints($amounts['DiemSuDung']);
            }

            $order->fill(array_merge($this->onlyOrderColumns($data), [
                'KhuyenMaiID' => $promotion?->KhuyenMaiID,
                'NgayCapNhat' => now(),
            ], $amounts));
            $order->save();

            $order->chiTietDonHangs()->delete();
            foreach ($items as $item) {
                if (empty($item['DichVuID']) || empty($item['LoaiDoGiatID']) || empty($item['DonViTinhID'])) {
                    continue;
                }

                ChiTietDonHang::create(array_merge(['DonHangID' => $order->DonHangID], $item));
            }

            return $order->fresh(['chiTietDonHangs', 'khachHang', 'khuyenMai']);
        });
    }

    public function delete(DonHang $order, bool $overrideSettled = false): bool
    {
        if ($order->isLocked() && ! $overrideSettled) {
            throw SettledOrderException::forOrder($order->MaDonHang);
        }

        return DB::transaction(function () use ($order) {
            // Hoàn lại điểm tích lũy đã trừ cho khách.
            if ($order->KhachHangID && (int) $order->DiemSuDung > 0) {
                KhachHang::find($order->KhachHangID)?->addPoints((int) $order->DiemSuDung);
            }

            return (bool) $order->delete();
        });
    }

    /**
     * Đổi trạng thái đơn. Đơn đã quyết toán thì không được đi lại trạng thái
     * vì sẽ làm sai lịch sử tiền đã thu.
     */
    public function updateStatus(DonHang $order, string $status, bool $overrideSettled = false): DonHang
    {
        if ($order->isLocked() && ! $overrideSettled) {
            throw SettledOrderException::forOrder($order->MaDonHang);
        }

        $order->update([
            'TrangThai' => $status,
            'NgayCapNhat' => now(),
        ]);

        return $order->fresh();
    }

    public function findById(int $id): ?DonHang
    {
        return $this->find($id);
    }

    public function getStatusFlow(): array
    {
        return OrderStatus::options();
    }

    /**
     * Mã đơn hàng kế tiếp: DH + số thứ tự đệm 3 chữ số.
     */
    private function generateOrderCode(): string
    {
        $next = (int) (DonHang::max('DonHangID') ?? 0) + 1;

        return 'DH'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Chỉ giữ lại các cột thuộc bảng DonHang từ dữ liệu form.
     *
     * Loại bỏ `items` (quan hệ 1-N được tạo riêng) và các khoá form phụ trợ.
     */
    private function onlyOrderColumns(array $data): array
    {
        $allowed = [
            'MaDonHang', 'BookingID', 'KhachHangID', 'NhanVienID', 'TrangThai',
            'PhiGiaoHang', 'GhiChu', 'NgayTao', 'NgayCapNhat',
        ];

        return array_intersect_key($data, array_flip($allowed));
    }
}
