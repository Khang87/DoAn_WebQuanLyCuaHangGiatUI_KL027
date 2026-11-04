@php
    $summaryOrder = $order ?? null;
    $summaryInvoice = $invoice ?? null;
    $summaryBooking = $summaryOrder?->booking;
    $summaryPaidPayment = $summaryOrder?->thanhToans
        ?->sortByDesc('ThoiGian')
        ->first(fn ($payment) => $payment->TrangThai === \App\Enums\PaymentStatus::Paid->value);
    $summaryPaymentMethod = $summaryBooking?->PhuongThucThanhToan
        ?: $summaryPaidPayment?->getMethodLabel()
        ?: 'Chưa chọn';
    $summarySubtotal = $summaryOrder?->subtotal ?? $summaryInvoice?->TongTien ?? 0;
    $summaryPromotionDiscount = (float) ($summaryOrder?->discount_by_promotion ?? 0);
    $summaryPointsDiscount = (float) ($summaryOrder?->discount_by_points ?? 0);
    $summaryTotalDiscount = $summaryOrder
        ? $summaryPromotionDiscount + $summaryPointsDiscount
        : (float) ($summaryInvoice?->GiamGia ?? 0);
    $summaryDeliveryFee = (float) ($summaryOrder?->PhiGiaoHang ?? $summaryInvoice?->PhiGiaoHang ?? 0);
    $summaryPickupFee = (float) ($summaryBooking?->PickupDeliveryFee ?? 0);
    $summaryReturnFee = (float) ($summaryBooking?->DeliveryFee ?? 0);
    $hasSavedDeliveryFeeBreakdown = $summaryBooking !== null
        && abs($summaryPickupFee + $summaryReturnFee - $summaryDeliveryFee) < 0.01;
    $summaryTotal = $summaryOrder?->total_amount ?? $summaryInvoice?->ThanhTien ?? 0;
@endphp

<div class="d-flex justify-content-between align-items-center py-2">
    <span class="detail-summary__label">Phương thức thanh toán</span>
    <span class="detail-money fw-semibold">{{ $summaryPaymentMethod }}</span>
</div>
<div class="d-flex justify-content-between align-items-center py-2">
    <span class="detail-summary__label">{{ $summaryOrder ? 'Tổng tiền đơn hàng' : 'Tổng tiền đơn hàng theo bảng giá' }}</span>
    <x-admin.detail.money :value="$summarySubtotal" class="fw-semibold" />
</div>
@if($hasSavedDeliveryFeeBreakdown)
    @if($summaryBooking->HinhThucNhanDo === 'Tại nhà' || $summaryPickupFee > 0)
        <div class="d-flex justify-content-between align-items-center py-2">
            <span class="detail-summary__label">
                Phí nhận đồ
                @if((int) $summaryBooking->PickupDistanceMeters > 0)
                    · {{ number_format((int) $summaryBooking->PickupDistanceMeters / 1000, 1, '.', ',') }} km
                @endif
            </span>
            <x-admin.detail.money :value="$summaryPickupFee" class="fw-semibold" />
        </div>
    @endif
    @if($summaryBooking->HinhThucTraDo === 'Tại nhà' || $summaryReturnFee > 0)
        <div class="d-flex justify-content-between align-items-center py-2">
            <span class="detail-summary__label">
                Phí giao đồ
                @if((int) $summaryBooking->DeliveryDistanceMeters > 0)
                    · {{ number_format((int) $summaryBooking->DeliveryDistanceMeters / 1000, 1, '.', ',') }} km
                @endif
            </span>
            <x-admin.detail.money :value="$summaryReturnFee" class="fw-semibold" />
        </div>
    @endif
@elseif($summaryDeliveryFee > 0)
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">Phí giao nhận</span>
        <x-admin.detail.money :value="$summaryDeliveryFee" class="fw-semibold" />
    </div>
@endif
@if($summaryPromotionDiscount > 0)
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">Giảm từ mã khuyến mãi</span>
        <x-admin.detail.money :value="$summaryPromotionDiscount" :negative="true" class="fw-semibold" />
    </div>
@endif
@if($summaryPointsDiscount > 0)
    <div class="d-flex justify-content-between align-items-start py-2">
        <span class="detail-summary__label">
            Giảm từ điểm tích lũy
        </span>
        <x-admin.detail.money :value="$summaryPointsDiscount" :negative="true" class="fw-semibold" />
    </div>
@endif
@if(! $summaryOrder)
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">Tổng tiền được giảm</span>
        <x-admin.detail.money :value="$summaryTotalDiscount" :negative="true" class="fw-semibold" />
    </div>
@endif

<div class="detail-summary d-flex justify-content-between align-items-center">
    <span class="detail-summary__total">Tổng tiền thanh toán</span>
    <x-admin.detail.money :value="$summaryTotal" class="detail-summary__total text-primary" />
</div>
