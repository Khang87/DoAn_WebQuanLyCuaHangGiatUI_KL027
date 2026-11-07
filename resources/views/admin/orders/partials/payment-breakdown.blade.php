@php
    $formatSummaryDistance = static fn (int $meters): string => rtrim(
        rtrim(number_format($meters / 1000, 1, '.', ','), '0'),
        '.',
    );
    $summaryOrder = $order ?? null;
    $summaryInvoice = $invoice ?? null;
    $summaryBookingAmounts = $bookingAmounts ?? null;
    $summaryBooking = ($booking ?? null) ?? $summaryOrder?->booking;
    $matchMobileOrder = ($matchMobile ?? false) && $summaryOrder !== null;
    $matchMobileBooking = ($matchMobile ?? false) && $summaryOrder === null && $summaryBooking !== null;
    $matchMobileSummary = $matchMobileOrder || $matchMobileBooking;
    $summaryMoneyUnit = $matchMobileSummary ? 'đ' : 'VNĐ';
    $summaryShowAdditionalPaymentDetails = ! $matchMobileSummary;
    $summaryDeliveryLegs = $summaryOrder?->relationLoaded('giaoNhans')
        ? $summaryOrder->giaoNhans
        : collect();
    $summaryPickupLeg = $summaryDeliveryLegs->firstWhere('LoaiGiaoNhan', 'NHAN_DO');
    $summaryDeliveryLeg = $summaryDeliveryLegs->firstWhere('LoaiGiaoNhan', 'GIAO_DO');
    $summaryLatestPayment = $summaryOrder?->thanhToans
        ?->sortByDesc('ThoiGian')
        ->first();
    $summaryPaymentMethod = $summaryBooking?->PhuongThucThanhToan
        ?: $summaryOrder?->recorded_payment_method
        ?: $summaryLatestPayment?->getMethodLabel()
        ?: 'Chưa chọn';
    $summarySubtotal = $summaryInvoice?->TongTien
        ?? $summaryOrder?->subtotal
        ?? $summaryBookingAmounts['TongTien']
        ?? 0;
    $summaryHasLaundryDetails = $summaryOrder?->chiTietDonHangs?->isNotEmpty()
        ?? $summaryBooking?->chiTietBookings?->isNotEmpty()
        ?? false;
    $summaryNeedsBookingQuote = $matchMobileBooking
        && ! $summaryHasLaundryDetails
        && $summaryOrder === null;
    $summaryAmountPending = $matchMobileBooking
        && ($summaryBookingAmounts === null || ! $summaryHasLaundryDetails);
    $summaryPromotionCode = $summaryOrder?->khuyenMai?->MaKhuyenMai
        ?? $summaryBooking?->khuyenMai?->MaKhuyenMai;
    $summaryPointsUsed = (int) ($summaryOrder?->DiemSuDung ?? $summaryBookingAmounts['DiemSuDung'] ?? 0);
    $summaryPromotionDiscount = (float) ($summaryOrder?->discount_by_promotion
        ?? $summaryBookingAmounts['TienGiamKhuyenMai']
        ?? 0);
    $summaryPointsDiscount = (float) ($summaryOrder?->discount_by_points
        ?? $summaryBookingAmounts['TienGiamDoDiem']
        ?? 0);
    $summaryTotalDiscount = $summaryOrder
        ? $summaryPromotionDiscount + $summaryPointsDiscount
        : (float) ($summaryInvoice?->GiamGia ?? 0);
    $summaryDeliveryFee = (float) ($summaryInvoice?->PhiGiaoHang
        ?? $summaryOrder?->PhiGiaoHang
        ?? (($summaryBooking?->PickupDeliveryFee ?? 0) + ($summaryBooking?->DeliveryFee ?? 0)));
    $summaryPickupFee = (float) ($summaryBooking?->PickupDeliveryFee ?? $summaryPickupLeg?->PhiGiaoNhan ?? 0);
    $summaryReturnFee = (float) ($summaryBooking?->DeliveryFee ?? $summaryDeliveryLeg?->PhiGiaoNhan ?? 0);
    $summaryPickupMethod = $summaryBooking?->HinhThucNhanDo
        ?: $summaryPickupLeg?->HinhThuc;
    $summaryReturnMethod = $summaryDeliveryLeg?->HinhThuc
        ?: $summaryBooking?->HinhThucGiaoDo
        ?: $summaryBooking?->HinhThucTraDo;
    $summaryPickupDistance = (int) ($summaryBooking?->PickupDistanceMeters ?? 0);
    $summaryReturnDistance = (int) ($summaryBooking?->DeliveryDistanceMeters ?? 0);
    $hasSavedDeliveryFeeBreakdown = $summaryBooking !== null
        && abs($summaryPickupFee + $summaryReturnFee - $summaryDeliveryFee) < 0.01;
    $summaryTotal = $summaryInvoice?->ThanhTien
        ?? $summaryOrder?->total_amount
        ?? $summaryBookingAmounts['ThanhTien']
        ?? 0;
@endphp

<div @if($matchMobileBooking && $summaryBookingAmounts !== null) data-booking-money-summary @endif>
<div class="d-flex justify-content-between align-items-center py-2">
    <span class="detail-summary__label">{{ $matchMobileBooking ? 'Hình thức' : 'Phương thức thanh toán' }}</span>
    <span class="detail-money fw-semibold">{{ $summaryPaymentMethod }}</span>
</div>
@if($matchMobileSummary)
    @if(($summaryHasLaundryDetails || $summaryInvoice !== null || $summaryBookingAmounts !== null) && $summarySubtotal !== null)
        <div class="d-flex justify-content-between align-items-center py-2">
            <span class="detail-summary__label">{{ $summaryOrder ? 'Tổng tiền đơn hàng' : ($bookingSubtotalLabel ?? 'Tổng tiền đơn hàng') }}</span>
            <span @if($matchMobileBooking) data-booking-amount="TongTien" @endif>
                <x-admin.detail.money :value="$summarySubtotal" :unit="$summaryMoneyUnit" class="fw-semibold" />
            </span>
        </div>
    @endif
@else
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">{{ $summaryOrder ? 'Tổng tiền đơn hàng' : 'Tổng tiền đơn hàng theo bảng giá' }}</span>
        <x-admin.detail.money :value="$summarySubtotal" :unit="$summaryMoneyUnit" class="fw-semibold" />
    </div>
@endif
@if($summaryShowAdditionalPaymentDetails && $summaryPromotionCode)
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">Mã khuyến mãi</span>
        <span class="detail-money fw-semibold">{{ $summaryPromotionCode }}</span>
    </div>
@endif
@if($matchMobileSummary)
    @if(! $summaryNeedsBookingQuote)
        @if($summaryPickupMethod !== null)
            <div class="d-flex justify-content-between align-items-center py-2">
                <span class="detail-summary__label">Phí nhận đồ: {{ $formatSummaryDistance($summaryPickupDistance) }} km</span>
                <x-admin.detail.money :value="$summaryPickupFee" :unit="$summaryMoneyUnit" class="fw-semibold" />
            </div>
        @endif
        @if($summaryReturnMethod !== null)
            <div class="d-flex justify-content-between align-items-center py-2">
                <span class="detail-summary__label">Phí giao đồ: {{ $formatSummaryDistance($summaryReturnDistance) }} km</span>
                <x-admin.detail.money :value="$summaryReturnFee" :unit="$summaryMoneyUnit" class="fw-semibold" />
            </div>
        @endif
        @if($summaryDeliveryFee > 0 && abs($summaryPickupFee + $summaryReturnFee) < 0.01)
            <div class="d-flex justify-content-between align-items-center py-2">
                <span class="detail-summary__label">Phí giao nhận</span>
                <x-admin.detail.money :value="$summaryDeliveryFee" :unit="$summaryMoneyUnit" class="fw-semibold" />
            </div>
        @endif
    @endif
    @if($summaryNeedsBookingQuote)
        <div class="d-flex justify-content-between align-items-center py-2">
            <span class="detail-summary__label">Tổng phí giao nhận</span>
            <x-admin.detail.money :value="$summaryDeliveryFee" :unit="$summaryMoneyUnit" class="fw-semibold" />
        </div>
    @endif
@elseif($hasSavedDeliveryFeeBreakdown)
    @if($summaryBooking->HinhThucNhanDo === 'Tại nhà' || $summaryPickupFee > 0)
        <div class="d-flex justify-content-between align-items-center py-2">
            <span class="detail-summary__label">
                Phí nhận đồ
                @if((int) $summaryBooking->PickupDistanceMeters > 0)
                    · {{ $formatSummaryDistance((int) $summaryBooking->PickupDistanceMeters) }} km
                @endif
            </span>
            <x-admin.detail.money :value="$summaryPickupFee" :unit="$summaryMoneyUnit" class="fw-semibold" />
        </div>
    @endif
    @if($summaryBooking->HinhThucTraDo === 'Tại nhà' || $summaryReturnFee > 0)
        <div class="d-flex justify-content-between align-items-center py-2">
            <span class="detail-summary__label">
                Phí giao đồ
                @if((int) $summaryBooking->DeliveryDistanceMeters > 0)
                    · {{ $formatSummaryDistance((int) $summaryBooking->DeliveryDistanceMeters) }} km
                @endif
            </span>
            <x-admin.detail.money :value="$summaryReturnFee" :unit="$summaryMoneyUnit" class="fw-semibold" />
        </div>
    @endif
@elseif($summaryDeliveryFee > 0)
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">Phí giao nhận</span>
        <x-admin.detail.money :value="$summaryDeliveryFee" :unit="$summaryMoneyUnit" class="fw-semibold" />
    </div>
@endif
@if($summaryNeedsBookingQuote)
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">Tổng tiền đơn hàng</span>
        <span class="detail-money fw-semibold">Cửa hàng sẽ báo giá</span>
    </div>
@endif
@if($summaryShowAdditionalPaymentDetails && $summaryPointsUsed > 0)
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">Điểm đã sử dụng</span>
        <span class="detail-money fw-semibold">{{ number_format($summaryPointsUsed, 0, ',', '.') }} điểm</span>
    </div>
@endif
@if($summaryPromotionDiscount > 0)
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">Giảm từ mã khuyến mãi</span>
        <span @if($matchMobileBooking) data-booking-amount="TienGiamKhuyenMai" @endif>
            <x-admin.detail.money :value="$summaryPromotionDiscount" :unit="$summaryMoneyUnit" :negative="true" class="fw-semibold" />
        </span>
    </div>
@endif
@if($summaryPointsDiscount > 0)
    <div class="d-flex justify-content-between align-items-start py-2">
        <span class="detail-summary__label">
            Giảm từ điểm tích lũy
        </span>
        <span @if($matchMobileBooking) data-booking-amount="TienGiamDoDiem" @endif>
            <x-admin.detail.money :value="$summaryPointsDiscount" :unit="$summaryMoneyUnit" :negative="true" class="fw-semibold" />
        </span>
    </div>
@endif
@if($summaryShowAdditionalPaymentDetails && $summaryTotalDiscount > 0)
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">Tổng tiền được giảm</span>
        <x-admin.detail.money :value="$summaryTotalDiscount" :unit="$summaryMoneyUnit" class="fw-semibold" />
    </div>
@endif

<div class="detail-summary d-flex justify-content-between align-items-center">
    <span class="detail-summary__total">Tổng tiền thanh toán</span>
    @if($summaryAmountPending)
        <span class="detail-summary__total text-primary">Cửa hàng sẽ báo giá</span>
    @else
        <span @if($matchMobileBooking) data-booking-amount="ThanhTien" @endif>
            <x-admin.detail.money :value="$summaryTotal" :unit="$summaryMoneyUnit" class="detail-summary__total text-primary" />
        </span>
    @endif
</div>
@if($matchMobileOrder && $summaryOrder->KhuyenMaiID !== null && $summaryOrder->TienGiamKhuyenMai === null)
    <p class="text-muted small mt-3 mb-0">Mức giảm của mã khuyến mãi sẽ được xác nhận khi cửa hàng tiếp nhận yêu cầu.</p>
@endif
@if($matchMobileSummary)
    <p class="text-muted small mt-3 mb-0">Giá cuối cùng có thể được điều chỉnh sau khi cửa hàng kiểm nhận đồ.</p>
@endif
</div>
