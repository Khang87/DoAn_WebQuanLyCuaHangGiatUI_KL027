@php
    $mobileCustomer = $booking->khachHang;
    $mobileAccount = $mobileCustomer?->taiKhoan;
@endphp
<x-admin.detail.panel title="Thông tin đặt lịch trên mobile" icon="bi-phone" :iconClass="'bg-info-subtle text-info'" data-booking-mobile-summary>
    <div class="d-flex align-items-center gap-3 mb-3">
        @if($mobileCustomer)<img src="{{ $mobileCustomer->avatar_url }}" alt="Ảnh khách hàng" class="rounded-circle" width="40" height="40" style="object-fit:cover">@endif
        <div><strong>{{ $mobileCustomer?->HoTen ?: $mobileAccount?->TenDangNhap ?: 'Chưa có tên khách hàng' }}</strong>
            <div class="small text-muted">{{ $mobileCustomer?->SoDienThoai ?: $mobileAccount?->SoDienThoai ?: 'Chưa có số điện thoại' }} · {{ $mobileCustomer?->Email ?: $mobileAccount?->Email ?: 'Chưa có email' }}</div>
        </div>
    </div>
    <dl class="row mb-0 small">
        <dt class="col-sm-6">Địa chỉ hồ sơ khách hàng</dt><dd class="col-sm-6">{{ $mobileCustomer?->DiaChi ?: 'Chưa bổ sung' }}</dd>
        <dt class="col-sm-6">Phương thức thanh toán đã chọn</dt><dd class="col-sm-6">{{ $booking->PhuongThucThanhToan ?: 'Chưa chọn' }}</dd>
        <dt class="col-sm-6">Phí nhận đồ đã lưu</dt><dd class="col-sm-6">{{ number_format((float) $booking->PickupDeliveryFee, 0, ',', '.') }} VNĐ</dd>
        <dt class="col-sm-6">Khoảng cách nhận đồ</dt><dd class="col-sm-6">{{ number_format((int) $booking->PickupDistanceMeters, 0, ',', '.') }} m</dd>
        <dt class="col-sm-6">Phí giao đồ đã lưu</dt><dd class="col-sm-6">{{ number_format((float) $booking->DeliveryFee, 0, ',', '.') }} VNĐ</dd>
        <dt class="col-sm-6">Khoảng cách giao đồ</dt><dd class="col-sm-6">{{ number_format((int) $booking->DeliveryDistanceMeters, 0, ',', '.') }} m</dd>
        <dt class="col-sm-6">Điểm khách đã chọn</dt><dd class="col-sm-6">{{ number_format((int) $booking->DiemSuDung, 0, ',', '.') }} điểm ({{ number_format((float) $booking->TienGiamDoDiem, 0, ',', '.') }} VNĐ)</dd>
    </dl>
    <p class="text-muted small mt-3 mb-0">Phí giao nhận lấy từ lịch đặt trên mobile; nhận hoặc trả đồ tại cửa hàng không tính phí chặng đó. Phương thức đã chọn chưa xác nhận khách đã thanh toán.</p>
</x-admin.detail.panel>
