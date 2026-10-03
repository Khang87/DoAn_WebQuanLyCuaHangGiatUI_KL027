<?php

namespace App\Services;

use App\Enums\BookingMethod;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Exceptions\SettledOrderException;
use App\Models\BangGia;
use App\Models\Booking;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\DonViTinh;
use App\Models\GiaoNhan;
use App\Models\KhachHang;
use App\Models\NhanVien;
use App\Models\NhatKyHeThong;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private TinhTienGiatUiService $tinhTienGiatUiService,
    ) {}

    /**
     * Giá trị giảm giá của một điểm tích lũy tính theo VNĐ.
     */
    public const POINT_VALUE = 10;

    /**
     * Số tiền thanh toán cần thiết để nhận một lô điểm tích lũy.
     */
    public const POINTS_PER_AMOUNT = 1000;

    /**
     * Số điểm được cộng cho mỗi lô giá trị đơn hàng đạt mức quy định.
     */
    public const POINTS_EARNED_PER_AMOUNT = 100;

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
        $remaining = max(0, $subtotal - $discountByPromotion);
        $redeemablePoints = (int) floor($remaining / self::POINT_VALUE);
        $effectivePoints = max(0, min($pointsUsed, $customerPoints, $redeemablePoints));
        $discountByPoints = $effectivePoints * self::POINT_VALUE;

        return [
            'TongTien' => round($subtotal, 2),
            'TienGiamKhuyenMai' => round($discountByPromotion, 2),
            'DiemSuDung' => $effectivePoints,
            'TienGiamDoDiem' => round($discountByPoints, 2),
            'ThanhTien' => round(max(0, $subtotal - $discountByPromotion - $discountByPoints), 2),
        ];
    }

    /**
     * Create an order and delivery record from a validated booking snapshot.
     *
     * @param  array<int, array<string, mixed>>  $snapshots
     */
    public function createFromBooking(Booking $booking, array $snapshots, int $employeeId, int $pointsUsed = 0): DonHang
    {
        return DB::transaction(function () use ($booking, $snapshots, $employeeId, $pointsUsed): DonHang {
            $existingOrder = DonHang::query()
                ->where('BookingID', $booking->BookingID)
                ->first();

            if ($existingOrder !== null) {
                return $existingOrder;
            }

            if (! NhanVien::query()->whereKey($employeeId)->exists()) {
                throw ValidationException::withMessages([
                    'NhanVienID' => $employeeId > 0
                        ? 'Nhân viên không tồn tại.'
                        : 'Vui lòng chọn nhân viên phụ trách.',
                ]);
            }

            $bookingCode = $booking->MaBooking ?: Booking::nextCode();
            $total = array_sum(array_column($snapshots, 'ThanhTien'));
            $customer = KhachHang::query()->find($booking->KhachHangID);
            $customerPoints = $customer?->points() ?? 0;
            $this->assertRequestedPointsAvailable($pointsUsed, $customerPoints);
            $amounts = $this->calculateAmounts($total, null, $pointsUsed, $customerPoints);

            if ($amounts['DiemSuDung'] > 0 && ! $customer->deductPoints($amounts['DiemSuDung'])) {
                throw ValidationException::withMessages([
                    'DiemSuDung' => 'Số dư điểm đã thay đổi. Vui lòng kiểm tra lại điểm tích lũy và thử lại.',
                ]);
            }

            $reference = 'Tự động tạo từ đặt lịch '.$bookingCode
                .' ('.$booking->method_label.' ngày '
                .($booking->NgayHen?->format('d/m/Y') ?? '—').')';
            $notes = mb_substr(
                $booking->GhiChu ? $reference.' | '.$booking->GhiChu : $reference,
                0,
                500,
            );

            $order = DonHang::create(array_merge([
                'MaDonHang' => 'TMP'.Str::ulid(),
                'KhachHangID' => $booking->KhachHangID,
                'NhanVienID' => $employeeId,
                'BookingID' => $booking->BookingID,
                'TrangThai' => OrderStatus::Pending->value,
                'GhiChu' => $notes,
            ], $amounts));
            $order->update([
                'MaDonHang' => 'DH'.str_pad((string) $order->DonHangID, 3, '0', STR_PAD_LEFT),
            ]);

            foreach ($snapshots as $snapshot) {
                ChiTietDonHang::create(array_merge(
                    ['DonHangID' => $order->DonHangID],
                    $snapshot,
                ));
            }

            GiaoNhan::create([
                'DonHangID' => $order->DonHangID,
                'NhanVienID' => $employeeId,
                'HinhThuc' => $booking->HinhThucNhanDo,
                'LoaiGiaoNhan' => $booking->methodEnum()->deliveryType(),
                'DiaChi' => $booking->methodEnum() === BookingMethod::GiaoDo
                    ? $booking->DiaChiNhan
                    : null,
                'ThoiGianDuKien' => $booking->NgayHen->format('Y-m-d').' '.$booking->GioHen->format('H:i:s'),
                'TrangThai' => DeliveryStatus::Pending->dbValue(),
                'GhiChu' => $booking->GhiChu,
            ]);

            return $order;
        });
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
            $weight = $weightInput !== null && $weightInput !== '' ? round((float) $weightInput, 2) : null;
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
                || (! $isWeightUnit && (
                    $quantity === null
                    || $quantity < 1
                    || floor($quantity) !== $quantity
                    || ($weight !== null && $weight !== 0.0)
                ))
            ) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.SoLuong" => $isWeightUnit
                        ? 'Chỉ nhập khối lượng lớn hơn 0 cho đơn vị KG; không thể lưu số lượng món cùng dòng theo schema hiện tại.'
                        : 'Đơn vị tính theo món cần số lượng nguyên dương; khối lượng phải bằng 0 hoặc để trống.',
                ]);
            }

            $minimumWeight = (float) config('giatui.khoi_luong_toi_thieu', 3.0);
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
                'KhoiLuong' => $isWeightUnit ? $weight : null,
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
            $pointsRequested = (int) ($data['DiemSuDung'] ?? 0);
            $this->assertRequestedPointsAvailable($pointsRequested, $customerPoints);

            $amounts = $this->calculateAmounts(
                $subtotal,
                $promotion,
                $pointsRequested,
                $customerPoints
            );

            // Trừ điểm tích lũy của khách hàng ngay trong cùng transaction.
            if ($amounts['DiemSuDung'] > 0 && (! $customer || ! $customer->deductPoints($amounts['DiemSuDung']))) {
                throw ValidationException::withMessages([
                    'DiemSuDung' => 'Không thể sử dụng số điểm đã chọn. Vui lòng kiểm tra số dư điểm và thử lại.',
                ]);
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

        return DB::transaction(function () use ($order, $data, $overrideSettled) {
            $lockedOrder = DonHang::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($lockedOrder->isLocked() && ! $overrideSettled) {
                throw SettledOrderException::forOrder($lockedOrder->MaDonHang);
            }

            [$items, $subtotal] = $this->buildItems($data['items'] ?? []);

            // Form gửi KhuyenMaiID (có thể rỗng) và/hoặc promotion_code.
            $touchesPromotion = array_key_exists('KhuyenMaiID', $data) || array_key_exists('promotion_code', $data);
            $promotion = $touchesPromotion
                ? $this->resolvePromotion($data)
                : $lockedOrder->khuyenMai;

            // Chính sách điều kiện voucher: bỏ voucher nếu khách không thoả
            // điều kiện, đơn vẫn lưu bình thường.
            $newCustomerId = (int) ($data['KhachHangID'] ?? $lockedOrder->KhachHangID);
            $customerChanged = $newCustomerId !== (int) $lockedOrder->KhachHangID;
            $oldCustomer = KhachHang::query()->find($lockedOrder->KhachHangID);
            $customer = $customerChanged
                ? KhachHang::query()->findOrFail($newCustomerId)
                : $oldCustomer;

            $promotion = $this->applyPromotionConditions($promotion, $customer, $subtotal, $lockedOrder->DonHangID);

            // Hoàn lại số điểm đã dùng ở lần lưu trước để tính lại từ đầu.
            $previousPoints = (int) $lockedOrder->DiemSuDung;
            if ($oldCustomer && $previousPoints > 0) {
                $oldCustomer->addPoints($previousPoints);
            }

            $customerPoints = $customer?->points() ?? 0;
            $pointsRequested = (int) ($data['DiemSuDung'] ?? 0);
            $this->assertRequestedPointsAvailable($pointsRequested, $customerPoints);

            $amounts = $this->calculateAmounts(
                $subtotal,
                $promotion,
                $pointsRequested,
                $customerPoints
            );

            if ($amounts['DiemSuDung'] > 0 && (! $customer || ! $customer->deductPoints($amounts['DiemSuDung']))) {
                throw ValidationException::withMessages([
                    'DiemSuDung' => 'Không thể sử dụng số điểm đã chọn. Vui lòng kiểm tra số dư điểm và thử lại.',
                ]);
            }

            $auditedFields = [
                'TongTien',
                'TienGiamDoDiem',
                'TienGiamKhuyenMai',
                'PhiGiaoHang',
                'ThanhTien',
            ];
            $previousValues = [];
            foreach ($auditedFields as $field) {
                $previousValues[$field] = $lockedOrder->getAttribute($field);
            }

            $lockedOrder->fill(array_merge($this->onlyOrderColumns($data), [
                'KhachHangID' => $newCustomerId,
                'KhuyenMaiID' => $promotion?->KhuyenMaiID,
                'NgayCapNhat' => now(),
            ], $amounts));
            $lockedOrder->save();

            $changedFields = array_intersect($auditedFields, array_keys($lockedOrder->getChanges()));
            if ($changedFields !== []) {
                NhatKyHeThong::query()->create([
                    'TaiKhoanID' => auth()->id(),
                    'HanhDong' => 'Thay đổi số tiền đơn hàng',
                    'BangDuLieu' => 'DonHang',
                    'BanGhiID' => $lockedOrder->getKey(),
                    'DuLieuCu' => array_intersect_key($previousValues, array_flip($changedFields)),
                    'DuLieuMoi' => array_intersect_key($lockedOrder->getAttributes(), array_flip($changedFields)),
                    'ThoiGian' => now(),
                    'IPAddress' => request()->ip(),
                    'UserAgent' => request()->userAgent(),
                ]);
            }

            $lockedOrder->chiTietDonHangs()->delete();
            foreach ($items as $item) {
                if (empty($item['DichVuID']) || empty($item['LoaiDoGiatID']) || empty($item['DonViTinhID'])) {
                    continue;
                }

                ChiTietDonHang::create(array_merge(['DonHangID' => $lockedOrder->DonHangID], $item));
            }

            return $lockedOrder->fresh(['chiTietDonHangs', 'khachHang', 'khuyenMai']);
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

        return DB::transaction(function () use ($order, $status, $overrideSettled): DonHang {
            $lockedOrder = DonHang::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if ($lockedOrder->isLocked() && ! $overrideSettled) {
                throw SettledOrderException::forOrder($lockedOrder->MaDonHang);
            }

            $lockedOrder->update([
                'TrangThai' => $status,
                'NgayCapNhat' => now(),
            ]);

            return $lockedOrder->fresh();
        });
    }

    private function assertRequestedPointsAvailable(int $pointsRequested, int $availablePoints): void
    {
        if ($pointsRequested < 0 || $pointsRequested > $availablePoints) {
            throw ValidationException::withMessages([
                'DiemSuDung' => 'Số điểm sử dụng vượt quá số dư điểm hiện có.',
            ]);
        }
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
