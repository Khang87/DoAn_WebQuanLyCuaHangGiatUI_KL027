<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReceiveMethod;
use App\Enums\ReturnMethod;
use App\Exceptions\SettledOrderException;
use App\Models\BangGia;
use App\Models\Booking;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\DonViTinh;
use App\Models\GiaoNhan;
use App\Models\KhachHang;
use App\Models\KhuyenMai;
use App\Models\LoaiDoGiat;
use App\Models\NhatKyHeThong;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private TinhTienGiatUiService $tinhTienGiatUiService,
        private PricingService $pricingService,
    ) {}

    /**
     * Giá trị giảm giá của một điểm tích lũy tính theo VNĐ.
     */
    public const POINT_VALUE = 1;

    /**
     * Số tiền thanh toán cần thiết để nhận một lô điểm tích lũy.
     */
    public const POINTS_PER_AMOUNT = 1000;

    /**
     * Số điểm được cộng cho mỗi lô giá trị đơn hàng đạt mức quy định.
     */
    public const POINTS_EARNED_PER_AMOUNT = 100;

    public function nextOrderCode(): string
    {
        $nextId = ((int) DonHang::query()->max('DonHangID')) + 1;

        return $this->formatOrderCode($nextId);
    }

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
     *   Tổng thanh toán      = max(0, Tạm tính - giảm khuyến mãi - giảm điểm) + phí giao hàng
     *
     * Mỗi khoản giảm được chặn tối đa bằng số tiền còn lại để tổng không âm.
     *
     * @return array{TongTien: float, TienGiamKhuyenMai: float, DiemSuDung: int, TienGiamDoDiem: float, PhiGiaoHang: float, ThanhTien: float}
     */
    public function calculateAmounts(float $subtotal, ?KhuyenMai $promotion, int $pointsUsed, int $customerPoints, float $deliveryFee = 0): array
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
            'PhiGiaoHang' => round(max(0, $deliveryFee), 2),
            'ThanhTien' => round(max(0, $subtotal - $discountByPromotion - $discountByPoints) + max(0, $deliveryFee), 2),
        ];
    }

    /** Read-only inspection preview; conversion still rechecks prices under a lock. */
    public function estimateBooking(Booking $booking, array $items, bool $usePoints): array
    {
        [, $subtotal] = $this->buildItems($items);

        return $this->calculateBookingEstimate($booking, $subtotal, $usePoints ? null : 0);
    }

    /** Stored mobile estimate; actual inspection reprices items separately. */
    public function estimateSavedBooking(Booking $booking): array
    {
        return $this->calculateBookingEstimate($booking, (float) $booking->chiTietBookings->sum('ThanhTien'), (int) $booking->DiemSuDung);
    }

    /** A null points request means use all available points in the inspection preview. */
    private function calculateBookingEstimate(Booking $booking, float $subtotal, ?int $requestedPoints): array
    {
        $customer = KhachHang::find($booking->KhachHangID);
        $availablePoints = ($customer?->points() ?? 0)
            + ($booking->DiemDaTru ? (int) $booking->DiemSuDung : 0);
        $promotion = $booking->khuyenMai;
        $warning = $promotion?->rejectionReasonForCustomer($customer, $subtotal);
        if ($booking->KhuyenMaiID && ! $promotion) {
            $warning = 'Không tìm thấy khuyến mãi của lịch đặt. Vui lòng kiểm tra trước khi xác nhận.';
        }
        if ($warning !== null) {
            $promotion = null;
        }

        return [
            ...$this->calculateAmounts($subtotal, $promotion, $requestedPoints ?? $availablePoints, $availablePoints,
                (float) $booking->PickupDeliveryFee + (float) $booking->DeliveryFee),
            'promotion_warning' => $warning,
        ];
    }

    /**
     * Create an order and delivery record from a validated booking snapshot.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function createFromBooking(Booking $booking, array $items, int $employeeId, int $pointsUsed = 0): DonHang
    {
        return DB::transaction(function () use ($booking, $items, $employeeId, $pointsUsed): DonHang {
            $callerBooking = $booking;
            $inspectionChanges = array_intersect_key($booking->getDirty(), array_flip([
                'HinhThucNhanDo', 'DiaChiNhan', 'HinhThucTraDo', 'DiaChiTra', 'NgayHen', 'GioHen', 'GhiChu',
            ]));
            $booking = Booking::query()->whereKey($booking->BookingID)->lockForUpdate()->firstOrFail();
            $existingOrder = DonHang::query()
                ->where('BookingID', $booking->BookingID)
                ->first();

            if ($existingOrder !== null) {
                return $existingOrder;
            }

            if (! $booking->isConvertibleToOrder()) {
                throw ValidationException::withMessages(['booking' => 'Chỉ Booking chờ xác nhận mới có thể được kiểm tra và tạo đơn.']);
            }
            // Preserve edits from inspection, never stale customer/status/reward markers.
            $booking->fill($inspectionChanges);

            EmployeeAssignment::assertAssignable($employeeId, 'NhanVienID');

            Validator::make($booking->only(['HinhThucNhanDo', 'DiaChiNhan', 'HinhThucTraDo', 'DiaChiTra']), [
                'HinhThucNhanDo' => ['required', 'in:'.implode(',', ReceiveMethod::values())],
                'DiaChiNhan' => ['nullable', 'string', 'max:255', 'required_if:HinhThucNhanDo,'.ReceiveMethod::Home->value],
                'HinhThucTraDo' => ['required', 'in:'.implode(',', ReturnMethod::values())],
                'DiaChiTra' => ['nullable', 'string', 'max:255', 'required_if:HinhThucTraDo,'.ReturnMethod::Home->value],
            ])->validate();
            Validator::make(['items' => $items], [
                'items' => ['required', 'array', 'min:1'],
                'items.*.DichVuID' => ['required', 'integer', 'exists:DichVu,DichVuID'],
                'items.*.LoaiDoGiatID' => ['required', 'integer', 'exists:LoaiDoGiat,LoaiDoGiatID'],
                'items.*.DonViTinhID' => ['required', 'integer', 'exists:DonViTinh,DonViTinhID'],
                'items.*.SoLuong' => ['nullable', 'numeric', 'min:1'],
                'items.*.KhoiLuong' => ['nullable', 'numeric', 'gt:0'],
                'items.*.TinhTrangTruocKhiGiat' => ['required', 'string', 'max:320'],
                'items.*.GhiChu' => ['nullable', 'string', 'max:160'],
            ])->validate();
            foreach ($items as $index => $item) {
                $items[$index]['TinhTrangTruocKhiGiat'] = trim($item['TinhTrangTruocKhiGiat']);
                if ($items[$index]['TinhTrangTruocKhiGiat'] === '') {
                    throw ValidationException::withMessages([
                        "items.{$index}.TinhTrangTruocKhiGiat" => 'Vui lòng nhập tình trạng trước khi giặt.',
                    ]);
                }
            }
            [$snapshots, $total] = $this->buildItems($items);

            $bookingCode = $booking->MaBooking ?: Booking::nextCode();
            $customer = KhachHang::query()->find($booking->KhachHangID);
            // Older App bookings may already have reserved points. Return that
            // reservation inside this transaction before redeeming the inspected order.
            if ($booking->getAttribute('DiemDaTru') && (int) $booking->getAttribute('DiemSuDung') > 0) {
                $customer?->addPoints((int) $booking->getAttribute('DiemSuDung'));
            }
            $customerPoints = $customer?->fresh()->points() ?? 0;
            $this->assertRequestedPointsAvailable($pointsUsed, $customerPoints);
            $promotion = $booking->getAttribute('KhuyenMaiID')
                ? $this->applyPromotionConditions(KhuyenMai::find($booking->getAttribute('KhuyenMaiID')), $customer, $total)
                : null;
            if ($promotion === null && $booking->getAttribute('KhuyenMaiID')) {
                if ($booking->getAttribute('KhuyenMaiDaTru')) {
                    KhuyenMai::whereKey($booking->getAttribute('KhuyenMaiID'))->whereNotNull('SoLuongSuDung')->increment('SoLuongSuDung');
                }
                $booking->forceFill(['KhuyenMaiID' => null, 'KhuyenMaiDaTru' => false])->saveQuietly();
            }
            $deliveryFee = (float) $booking->getAttribute('PickupDeliveryFee') + (float) $booking->getAttribute('DeliveryFee');
            $amounts = $this->calculateAmounts($total, $promotion, $pointsUsed, $customerPoints, $deliveryFee);
            $amounts['KhuyenMaiID'] = $promotion?->KhuyenMaiID;

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
                'TrangThai' => OrderStatus::Received->value,
                'GhiChu' => $notes,
            ], $amounts));
            $order->update([
                'MaDonHang' => $this->formatOrderCode((int) $order->DonHangID),
            ]);

            foreach ($snapshots as $snapshot) {
                ChiTietDonHang::create(array_merge(
                    ['DonHangID' => $order->DonHangID],
                    $snapshot,
                ));
            }

            foreach ([
                ['method' => $booking->receiveMethodEnum(), 'home' => ReceiveMethod::Home, 'type' => 'NHAN_DO', 'address' => $booking->DiaChiNhan],
                ['method' => $booking->returnMethodEnum(), 'home' => ReturnMethod::Home, 'type' => 'GIAO_DO', 'address' => $booking->DiaChiTra],
            ] as $leg) {
                if ($leg['method'] !== $leg['home']) {
                    continue;
                }
                GiaoNhan::create([
                    'DonHangID' => $order->DonHangID,
                    'NhanVienID' => $employeeId,
                    'HinhThuc' => $leg['method']->value,
                    'LoaiGiaoNhan' => $leg['type'],
                    'DiaChi' => $leg['address'],
                    // NgayHen/GioHen is the receiving appointment, never the return schedule.
                    'ThoiGianDuKien' => $leg['type'] === 'NHAN_DO'
                        ? $booking->NgayHen->format('Y-m-d').' '.$booking->GioHen->format('H:i:s')
                        : null,
                    'TrangThai' => DeliveryStatus::Pending->dbValue(),
                    'GhiChu' => $booking->GhiChu,
                ]);
            }

            if (array_key_exists('DiemDaTru', $booking->getAttributes())) {
                $booking->fill(['DiemDaTru' => false, 'DiemSuDung' => 0, 'TienGiamDoDiem' => 0]);
                $callerBooking->fill(['DiemDaTru' => false, 'DiemSuDung' => 0, 'TienGiamDoDiem' => 0]);
            }

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
    private function buildItems(?array $rawItems, array $priceOverridesByIndex = []): array
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

        $pricingByTuple = $this->pricingService->getLatestPricingForTuples($pricingTuples);

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
            $priceOverride = $priceOverridesByIndex[$itemIndex] ?? null;
            if (! $pricing && $priceOverride === null) {
                throw ValidationException::withMessages([
                    "items.{$itemIndex}.DonViTinhID" => 'Không có bảng giá đang hiệu lực cho tổ hợp dịch vụ, loại đồ và đơn vị tính đã chọn.',
                ]);
            }

            $price = $priceOverride !== null ? (float) $priceOverride : (float) $pricing->DonGia;
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
                'TinhTrangTruocKhiGiat' => $item['TinhTrangTruocKhiGiat'] ?? null,
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
            'chiTietDonHangs.dichVu',
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

        // Direct callers must obey the same inspection boundary as the Web form.
        Validator::make($data, [
            'NhanVienID' => ['required', 'integer', EmployeeAssignment::rule()],
            'BookingID' => ['prohibited'],
            'KhuyenMaiID' => ['prohibited'],
            'promotion_code' => ['prohibited'],
            'TrangThai' => ['required', 'in:'.OrderStatus::Received->value],
            'items' => ['required', 'array', 'min:1'],
            'items.*.TinhTrangTruocKhiGiat' => ['required', 'string', 'max:320'],
        ], [
            'BookingID.prohibited' => 'Hãy tạo đơn từ màn hình kiểm kê Booking.',
            'TrangThai.in' => 'Đơn mới chỉ được tạo ở trạng thái Đã tiếp nhận sau khi kiểm kê.',
        ])->validate();
        foreach ($data['items'] as &$item) {
            $item['TinhTrangTruocKhiGiat'] = trim($item['TinhTrangTruocKhiGiat']);
        }
        unset($item);

        return DB::transaction(function () use ($data) {
            EmployeeAssignment::assertAssignable((int) $data['NhanVienID'], 'NhanVienID');

            $data['MaDonHang'] = 'TMP'.Str::ulid();

            $data['NgayTao'] = $data['NgayTao'] ?? now();

            $customer = KhachHang::find($data['KhachHangID'] ?? null);

            [$items, $subtotal] = $this->buildItems($data['items'] ?? []);

            $customerPoints = $customer?->points() ?? 0;
            $pointsRequested = array_key_exists('use_points', $data)
                ? ((bool) $data['use_points'] ? $customerPoints : 0)
                : (int) ($data['DiemSuDung'] ?? 0);
            $this->assertRequestedPointsAvailable($pointsRequested, $customerPoints);

            $amounts = $this->calculateAmounts(
                $subtotal,
                null,
                $pointsRequested,
                $customerPoints,
                (float) ($data['PhiGiaoHang'] ?? 0),
            );

            // Trừ điểm tích lũy của khách hàng ngay trong cùng transaction.
            if ($amounts['DiemSuDung'] > 0 && (! $customer || ! $customer->deductPoints($amounts['DiemSuDung']))) {
                throw ValidationException::withMessages([
                    'DiemSuDung' => 'Không thể sử dụng số điểm đã chọn. Vui lòng kiểm tra số dư điểm và thử lại.',
                ]);
            }

            $order = DonHang::create(array_merge($this->onlyOrderColumns($data), [
                'KhuyenMaiID' => null,
                'NgayCapNhat' => now(),
            ], $amounts));

            $order->update([
                'MaDonHang' => $this->formatOrderCode((int) $order->getKey()),
            ]);

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

        Validator::make($data, [
            'KhuyenMaiID' => ['prohibited'],
            'promotion_code' => ['prohibited'],
        ])->validate();

        unset($data['MaDonHang']);

        $this->promotionRejection = null;

        return DB::transaction(function () use ($order, $data, $overrideSettled) {
            $lockedOrder = DonHang::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($lockedOrder->isLocked() && ! $overrideSettled) {
                throw SettledOrderException::forOrder($lockedOrder->MaDonHang);
            }

            if ($lockedOrder->TrangThai !== OrderStatus::Pending->value && ! $overrideSettled) {
                throw ValidationException::withMessages([
                    'order' => 'Chi tiết đơn hàng đã khóa sau khi hoàn tất tiếp nhận.',
                ]);
            }

            if (
                $lockedOrder->statusEnum() === OrderStatus::Pending
                && isset($data['TrangThai'])
                && $data['TrangThai'] !== OrderStatus::Pending->value
            ) {
                throw ValidationException::withMessages([
                    'TrangThai' => 'Cần hoàn tất kiểm tra và tiếp nhận thực tế trước khi chuyển trạng thái đơn hàng.',
                ]);
            }

            if (isset($data['TrangThai'])) {
                $this->assertStatusTransition($lockedOrder, $data['TrangThai'], $overrideSettled, $data['cancellation_reason'] ?? null);
            }

            if ($lockedOrder->statusEnum() === OrderStatus::Cancelled) {
                throw ValidationException::withMessages(['order' => 'Đơn đã hủy chỉ được xem, không thể sửa số tiền hoặc điểm.']);
            }

            if (array_key_exists('NhanVienID', $data)) {
                EmployeeAssignment::assertAssignable((int) $data['NhanVienID'], 'NhanVienID', $lockedOrder->NhanVienID);
            }

            [$items, $subtotal] = $this->buildItems($data['items'] ?? []);

            $promotion = $lockedOrder->khuyenMai;

            // Chính sách điều kiện voucher: bỏ voucher nếu khách không thoả
            // điều kiện, đơn vẫn lưu bình thường.
            $newCustomerId = (int) ($data['KhachHangID'] ?? $lockedOrder->KhachHangID);
            $customerChanged = $newCustomerId !== (int) $lockedOrder->KhachHangID;
            $oldCustomer = KhachHang::query()->find($lockedOrder->KhachHangID);
            $customer = $customerChanged
                ? KhachHang::query()->findOrFail($newCustomerId)
                : $oldCustomer;

            $promotion = $this->applyPromotionConditions($promotion, $customer, $subtotal, $lockedOrder->DonHangID);

            $previousPoints = (int) $lockedOrder->DiemSuDung;
            $sameCustomer = ! $customerChanged;
            $customerPoints = ($customer?->points() ?? 0)
                + ($sameCustomer ? $previousPoints : 0);
            $pointsRequested = array_key_exists('use_points', $data)
                ? ((bool) $data['use_points'] ? $customerPoints : 0)
                : (int) ($data['DiemSuDung'] ?? 0);

            $this->assertRequestedPointsAvailable($pointsRequested, $customerPoints);

            $amounts = $this->calculateAmounts(
                $subtotal,
                $promotion,
                $pointsRequested,
                $customerPoints,
                (float) ($data['PhiGiaoHang'] ?? $lockedOrder->PhiGiaoHang),
            );

            if ($sameCustomer) {
                $pointsDifference = $amounts['DiemSuDung'] - $previousPoints;

                if ($pointsDifference > 0 && (! $customer || ! $customer->deductPoints($pointsDifference))) {
                    throw ValidationException::withMessages([
                        'DiemSuDung' => 'Không thể sử dụng số điểm đã chọn. Vui lòng kiểm tra số dư điểm và thử lại.',
                    ]);
                }

                if ($pointsDifference < 0 && $customer) {
                    $customer->addPoints(abs($pointsDifference));
                }
            } else {
                if ($oldCustomer && $previousPoints > 0) {
                    $oldCustomer->addPoints($previousPoints);
                }

                if ($amounts['DiemSuDung'] > 0 && (! $customer || ! $customer->deductPoints($amounts['DiemSuDung']))) {
                    throw ValidationException::withMessages([
                        'DiemSuDung' => 'Không thể sử dụng số điểm đã chọn. Vui lòng kiểm tra số dư điểm và thử lại.',
                    ]);
                }
            }

            if ($pointsRequested > 0 && ! $customer) {
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
            $this->recordCancellationReason($lockedOrder, $data['cancellation_reason'] ?? null);

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

    /**
     * Lưu kết quả kiểm tra thực tế và hoàn tất tiếp nhận trong một transaction.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function completeReceivingInspection(DonHang $order, array $items): DonHang
    {
        return DB::transaction(function () use ($order, $items): DonHang {
            $lockedOrder = DonHang::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if ($lockedOrder->statusEnum() !== OrderStatus::Pending) {
                throw ValidationException::withMessages([
                    'order' => 'Chỉ đơn hàng đang chờ tiếp nhận mới có thể hoàn tất kiểm tra.',
                ]);
            }

            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => 'Đơn hàng cần có ít nhất một mặt hàng thực tế.',
                ]);
            }

            $items = array_values($items);
            $existingItems = $lockedOrder->chiTietDonHangs()
                ->lockForUpdate()
                ->get()
                ->keyBy('ChiTietDonHangID');
            $submittedItemIds = [];

            foreach ($items as $item) {
                $itemId = $item['ChiTietDonHangID'] ?? null;
                if ($itemId === null) {
                    continue;
                }

                $itemId = (int) $itemId;
                if (! $existingItems->has($itemId)) {
                    throw ValidationException::withMessages([
                        'items' => 'Có mặt hàng không thuộc đơn hàng này. Vui lòng tải lại và kiểm tra danh sách.',
                    ]);
                }

                if (isset($submittedItemIds[$itemId])) {
                    throw ValidationException::withMessages([
                        'items' => 'Không thể gửi trùng một mặt hàng trong danh sách kiểm tra.',
                    ]);
                }

                $submittedItemIds[$itemId] = true;
            }

            $priceOverridesByIndex = [];
            foreach ($items as $index => $item) {
                $itemId = (int) ($item['ChiTietDonHangID'] ?? 0);
                $existingItem = $existingItems->get($itemId);

                if (isset($item['DonGia']) && $item['DonGia'] !== '') {
                    $priceOverridesByIndex[$index] = (float) $item['DonGia'];
                } elseif (
                    $existingItem
                    && (int) $existingItem->DichVuID === (int) $item['DichVuID']
                    && (int) $existingItem->LoaiDoGiatID === (int) $item['LoaiDoGiatID']
                    && (int) $existingItem->DonViTinhID === (int) $item['DonViTinhID']
                ) {
                    $priceOverridesByIndex[$index] = (float) $existingItem->DonGia;
                }
            }

            $garmentNames = LoaiDoGiat::query()
                ->whereIn('LoaiDoGiatID', collect($items)->pluck('LoaiDoGiatID')->unique())
                ->pluck('TenLoaiDoGiat', 'LoaiDoGiatID');

            foreach ($items as $index => $item) {
                $condition = trim((string) ($item['TinhTrangTruocKhiGiat'] ?? $item['GhiChu'] ?? ''));
                $hasConditionField = isset($item['TinhTrangTruocKhiGiat']);
                $additionalNotes = $hasConditionField ? trim((string) ($item['GhiChu'] ?? '')) : '';
                $garmentName = $garmentNames->get((int) $item['LoaiDoGiatID']);
                $notes = sprintf('[Tình trạng: %s; Loại: %s]', $condition, $garmentName)
                    .($additionalNotes !== '' ? ' '.$additionalNotes : '');

                if (mb_strlen($notes) > 500) {
                    throw ValidationException::withMessages([
                        "items.{$index}.GhiChu" => 'Tình trạng, tên loại đồ và ghi chú cộng lại không được vượt quá 500 ký tự.',
                    ]);
                }

                $items[$index]['TinhTrangTruocKhiGiat'] = $condition;
                $items[$index]['GhiChu'] = $notes;
            }

            [$rows, $subtotal] = $this->buildItems($items, $priceOverridesByIndex);
            $retainedIds = [];

            foreach ($rows as $index => $row) {
                $itemId = $items[$index]['ChiTietDonHangID'] ?? null;
                if ($itemId !== null) {
                    $existingItems->get((int) $itemId)->update($row);
                    $retainedIds[] = (int) $itemId;

                    continue;
                }

                $createdItem = $lockedOrder->chiTietDonHangs()->create($row);
                $retainedIds[] = (int) $createdItem->getKey();
            }

            $lockedOrder->chiTietDonHangs()
                ->whereNotIn('ChiTietDonHangID', $retainedIds)
                ->delete();

            $customer = KhachHang::query()->find($lockedOrder->KhachHangID);
            $previousPoints = (int) $lockedOrder->DiemSuDung;

            if ($customer && $previousPoints > 0) {
                $customer->addPoints($previousPoints);
            }

            $promotion = $this->applyPromotionConditions(
                $lockedOrder->khuyenMai,
                $customer,
                $subtotal,
                (int) $lockedOrder->DonHangID,
            );
            $customerPoints = $customer?->points() ?? 0;
            $this->assertRequestedPointsAvailable($previousPoints, $customerPoints);
            $amounts = $this->calculateAmounts(
                $subtotal,
                $promotion,
                $previousPoints,
                $customerPoints,
                (float) $lockedOrder->PhiGiaoHang,
            );

            if ($amounts['DiemSuDung'] > 0 && (! $customer || ! $customer->deductPoints($amounts['DiemSuDung']))) {
                throw ValidationException::withMessages([
                    'DiemSuDung' => 'Không thể áp dụng lại số điểm đã chọn. Vui lòng kiểm tra số dư điểm.',
                ]);
            }

            $lockedOrder->update(array_merge($amounts, [
                'KhuyenMaiID' => $promotion?->KhuyenMaiID,
                'TrangThai' => OrderStatus::Received->value,
                'NgayCapNhat' => now(),
            ]));

            return $lockedOrder->fresh([
                'chiTietDonHangs.dichVu',
                'chiTietDonHangs.loaiDoGiat',
                'chiTietDonHangs.donViTinh',
                'khachHang',
                'khuyenMai',
                'nhanVien',
            ]);
        });
    }

    public function delete(DonHang $order, bool $overrideSettled = false): bool
    {
        if ($order->isLocked() && ! $overrideSettled) {
            throw SettledOrderException::forOrder($order->MaDonHang);
        }

        return DB::transaction(function () use ($order, $overrideSettled) {
            $order = DonHang::query()->lockForUpdate()->find($order->getKey());
            if ($order === null) {
                return false;
            }
            if ($order->isLocked() && ! $overrideSettled) {
                throw SettledOrderException::forOrder($order->MaDonHang);
            }
            // Hoàn lại điểm tích lũy đã trừ cho khách.
            if ($order->KhachHangID && (int) $order->DiemSuDung > 0 && $order->TrangThai !== OrderStatus::Cancelled->value) {
                KhachHang::find($order->KhachHangID)?->addPoints((int) $order->DiemSuDung);
            }

            return (bool) $order->delete();
        });
    }

    /**
     * Đổi trạng thái đơn. Đơn đã quyết toán thì không được đi lại trạng thái
     * vì sẽ làm sai lịch sử tiền đã thu.
     */
    public function updateStatus(DonHang $order, string $status, bool $overrideSettled = false, ?string $reason = null): DonHang
    {
        if ($order->isLocked() && ! $overrideSettled) {
            throw SettledOrderException::forOrder($order->MaDonHang);
        }

        return DB::transaction(function () use ($order, $status, $overrideSettled, $reason): DonHang {
            $lockedOrder = DonHang::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            if ($lockedOrder->isLocked() && ! $overrideSettled) {
                throw SettledOrderException::forOrder($lockedOrder->MaDonHang);
            }

            if (! in_array($status, OrderStatus::values(), true)) {
                throw ValidationException::withMessages([
                    'TrangThai' => 'Trạng thái đơn hàng không hợp lệ.',
                ]);
            }

            $this->assertStatusTransition($lockedOrder, $status, $overrideSettled, $reason);

            $lockedOrder->update([
                'TrangThai' => $status,
                'NgayCapNhat' => now(),
            ]);

            $this->recordCancellationReason($lockedOrder, $reason);

            return $lockedOrder->fresh();
        });
    }

    private function recordCancellationReason(DonHang $order, ?string $reason): void
    {
        if ($order->TrangThai !== OrderStatus::Cancelled->value || trim((string) $reason) === '') {
            return;
        }
        $action = 'Hủy đơn hàng';
        if (! NhatKyHeThong::where('BangDuLieu', 'DonHang')->where('BanGhiID', $order->getKey())->where('HanhDong', $action)->exists()) {
            NhatKyHeThong::create([
                'TaiKhoanID' => auth()->id(), 'HanhDong' => $action, 'BangDuLieu' => 'DonHang',
                'BanGhiID' => $order->getKey(), 'LyDo' => trim((string) $reason), 'ThoiGian' => now(),
            ]);
        }
    }

    private function assertStatusTransition(DonHang $lockedOrder, string $status, bool $overrideSettled, ?string $reason = null): void
    {
        $target = OrderStatus::tryFrom($status);
        if ($target === null) {
            throw ValidationException::withMessages(['TrangThai' => 'Trạng thái đơn hàng không hợp lệ.']);
        }
        $current = $lockedOrder->statusEnum();
        if (! $current->canTransitionTo($target) && ! ($overrideSettled && $current === OrderStatus::Paid && $target === OrderStatus::Delivered)) {
            throw ValidationException::withMessages([
                'TrangThai' => $current === OrderStatus::Pending
                    ? 'Cần hoàn tất kiểm tra và tiếp nhận thực tế trước khi chuyển trạng thái đơn hàng.'
                    : 'Không thể bỏ bước hoặc quay ngược trạng thái đơn hàng.',
            ]);
        }
        if ($target === OrderStatus::Cancelled && $current !== $target && trim((string) $reason) === '') {
            throw ValidationException::withMessages(['cancellation_reason' => 'Vui lòng nhập lý do hủy đơn hàng.']);
        }
        if ($target === OrderStatus::Paid) {
            $total = (float) ($lockedOrder->hoaDons()->first()?->ThanhTien ?? $lockedOrder->ThanhTien);
            $paid = (float) $lockedOrder->thanhToans()->where('TrangThai', PaymentStatus::Paid->value)->sum('SoTien');
            if (! in_array($current, [OrderStatus::Delivered, OrderStatus::Paid], true) || $paid < $total) {
                throw ValidationException::withMessages(['TrangThai' => 'Chỉ quyết toán đơn đã giao và đã thu đủ tiền.']);
            }
        }
        if ($current === OrderStatus::Paid && $target === OrderStatus::Delivered) {
            $total = (float) ($lockedOrder->hoaDons()->first()?->ThanhTien ?? $lockedOrder->ThanhTien);
            $paid = (float) $lockedOrder->thanhToans()->where('TrangThai', PaymentStatus::Paid->value)->sum('SoTien');
            if ($paid >= $total) {
                throw ValidationException::withMessages(['TrangThai' => 'Đơn đã thu đủ tiền không thể mở lại trạng thái chưa quyết toán.']);
            }
        }
        if ($target === OrderStatus::Cancelled && $lockedOrder->thanhToans()->where('TrangThai', PaymentStatus::Paid->value)->exists()) {
            throw ValidationException::withMessages(['TrangThai' => 'Đơn đã thu tiền cần xử lý hoàn tiền trước khi hủy.']);
        }

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

    private function formatOrderCode(int $orderId): string
    {
        return 'DH'.str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
    }
}
