@php
    $formatSummaryDistance = static fn (int $meters): string => rtrim(
        rtrim(number_format($meters / 1000, 1, '.', ','), '0'),
        '.',
    );
    $summaryOrder = $order ?? null;
    $summaryInvoice = $invoice ?? null;
    $matchMobileOrder = ($matchMobile ?? false) && $summaryOrder !== null;
    $summaryShowAdditionalPaymentDetails = ! $matchMobileOrder || $summaryInvoice !== null;
    $summaryBooking = $summaryOrder?->booking;
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
    $summarySubtotal = $summaryOrder?->subtotal ?? $summaryInvoice?->TongTien ?? 0;
    $summaryHasLaundryDetails = $summaryOrder?->chiTietDonHangs?->isNotEmpty() ?? false;
    $summaryPromotionCode = $summaryOrder?->khuyenMai?->MaKhuyenMai;
    $summaryPointsUsed = (int) ($summaryOrder?->DiemSuDung ?? 0);
    $summaryPromotionDiscount = (float) ($summaryOrder?->discount_by_promotion ?? 0);
    $summaryPointsDiscount = (float) ($summaryOrder?->discount_by_points ?? 0);
    $summaryTotalDiscount = $summaryOrder
        ? $summaryPromotionDiscount + $summaryPointsDiscount
        : (float) ($summaryInvoice?->GiamGia ?? 0);
    $summaryDeliveryFee = (float) ($summaryOrder?->PhiGiaoHang ?? $summaryInvoice?->PhiGiaoHang ?? 0);
    $summaryPickupFee = (float) ($summaryBooking?->PickupDeliveryFee ?? $summaryPickupLeg?->PhiGiaoNhan ?? 0);
    $summaryReturnFee = (float) ($summaryBooking?->DeliveryFee ?? $summaryDeliveryLeg?->PhiGiaoNhan ?? 0);
    $summaryPickupMethod = $summaryBooking?->HinhThucNhanDo
        ?: $summaryPickupLeg?->HinhThuc;
    $summaryReturnMethod = $summaryDeliveryLeg?->HinhThuc
        ?: $summaryBooking?->HinhThucGiaoDo
        ?: $summaryBooking?->HinhThucTraDo;
    $summaryPickupDistance = $summaryBooking?->PickupDistanceMeters;
    $summaryReturnDistance = $summaryBooking?->DeliveryDistanceMeters;
    $hasSavedDeliveryFeeBreakdown = $summaryBooking !== null
        && abs($summaryPickupFee + $summaryReturnFee - $summaryDeliveryFee) < 0.01;
    $summaryTotal = $summaryOrder?->total_amount ?? $summaryInvoice?->ThanhTien ?? 0;
@endphp

<div class="d-flex justify-content-between align-items-center py-2">
    <span class="detail-summary__label">Phương thức thanh toán</span>
    <span class="detail-money fw-semibold">{{ $summaryPaymentMethod }}</span>
</div>
@if($matchMobileOrder)
    @if(($summaryHasLaundryDetails || $summaryInvoice !== null) && $summaryOrder->subtotal !== null)
        <div class="d-flex justify-content-between align-items-center py-2">
            <span class="detail-summary__label">Tổng tiền đơn hàng</span>
            <x-admin.detail.money :value="$summarySubtotal" class="fw-semibold" />
        </div>
    @elseif(! $summaryHasLaundryDetails)
        <p class="text-muted small py-2 mb-0">Cửa hàng sẽ báo giá sau khi kiểm nhận đồ.</p>
    @endif
@else
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">{{ $summaryOrder ? 'Tổng tiền đơn hàng' : 'Tổng tiền đơn hàng theo bảng giá' }}</span>
        <x-admin.detail.money :value="$summarySubtotal" class="fw-semibold" />
    </div>
@endif
@if($summaryShowAdditionalPaymentDetails && $summaryPromotionCode)
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">Mã khuyến mãi</span>
        <span class="detail-money fw-semibold">{{ $summaryPromotionCode }}</span>
    </div>
@endif
@if($matchMobileOrder)
    @if($summaryPickupMethod !== null)
        <div class="d-flex justify-content-between align-items-center py-2">
            <span class="detail-summary__label">
                Phí nhận đồ
                @if($summaryPickupDistance > 0)
                    · {{ $formatSummaryDistance($summaryPickupDistance) }} km
                @endif
            </span>
            <x-admin.detail.money :value="$summaryPickupFee" class="fw-semibold" />
        </div>
    @endif
    @if($summaryReturnMethod !== null)
        <div class="d-flex justify-content-between align-items-center py-2">
            <span class="detail-summary__label">
                Phí giao đồ
                @if($summaryReturnDistance > 0)
                    · {{ $formatSummaryDistance($summaryReturnDistance) }} km
                @endif
            </span>
            <x-admin.detail.money :value="$summaryReturnFee" class="fw-semibold" />
        </div>
    @endif
    @if($summaryDeliveryFee > 0 && abs($summaryPickupFee + $summaryReturnFee) < 0.01)
        <div class="d-flex justify-content-between align-items-center py-2">
            <span class="detail-summary__label">Phí giao nhận</span>
            <x-admin.detail.money :value="$summaryDeliveryFee" class="fw-semibold" />
        </div>
    @endif
@elseif($hasSavedDeliveryFeeBreakdown)
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
@if($summaryShowAdditionalPaymentDetails && $summaryPointsUsed > 0)
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">Điểm đã sử dụng</span>
        <span class="detail-money fw-semibold">{{ number_format($summaryPointsUsed, 0, ',', '.') }} điểm</span>
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
@if($summaryShowAdditionalPaymentDetails && $summaryTotalDiscount > 0)
    <div class="d-flex justify-content-between align-items-center py-2">
        <span class="detail-summary__label">Tổng tiền được giảm</span>
        <x-admin.detail.money :value="$summaryTotalDiscount" class="fw-semibold" />
    </div>
@endif

<div class="detail-summary d-flex justify-content-between align-items-center">
    <span class="detail-summary__total">Tổng tiền thanh toán</span>
    <x-admin.detail.money :value="$summaryTotal" class="detail-summary__total text-primary" />
</div>
@if($matchMobileOrder && $summaryOrder->KhuyenMaiID !== null && $summaryOrder->TienGiamKhuyenMai === null)
    <p class="text-muted small mt-3 mb-0">Mức giảm của mã khuyến mãi sẽ được xác nhận khi cửa hàng tiếp nhận yêu cầu.</p>
@endif
