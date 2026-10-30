@extends('layouts.app')

@section('title', 'Chi tiết đặt lịch - Sky Laundry')
@section('page-title', 'Chi tiết đặt lịch')

@section('content')
<x-admin.detail.page-header
    title="Chi tiết lịch hẹn"
    :subtitle="$booking->method_label . ' ngày ' . ($booking->NgayHen?->format('d/m/Y') ?: '—')"
    backUrl="{{ route('bookings.index') }}"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$booking->TrangThai" :enum="\App\Enums\BookingStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

@php $order = $booking->donHangs->first() @endphp
@php
    $storeAddress = '140 Lê Trọng Tấn, Tây Thạnh, Hồ Chí Minh, Việt Nam';
    $receiveAtStore = $booking->HinhThucNhanDo === 'Tại cửa hàng';
    $returnAtStore = $booking->HinhThucTraDo === 'Tại cửa hàng';
    $intakeDocument = $booking->giaoNhanDuKiens->firstWhere('LoaiGiaoNhan', 'NHAN_DO');
    $legacyRequestPrefix = 'Khách chọn dịch vụ chưa rõ số lượng: ';
    $bookingNoteLines = preg_split('/\r\n|\r|\n/', (string) ($booking->GhiChu ?? ''));
    $legacyRequestedServices = collect($bookingNoteLines)
        ->filter(fn ($line) => str_starts_with($line, $legacyRequestPrefix))
        ->map(fn ($line) => rtrim(substr($line, strlen($legacyRequestPrefix)), '.'))
        ->values();
    $visibleBookingNotes = collect($bookingNoteLines)
        ->reject(fn ($line) => str_starts_with($line, $legacyRequestPrefix))
        ->implode("\n");
@endphp

<div class="row g-4 align-items-start">
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin lịch hẹn" icon="bi-calendar-check" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã đặt lịch" :value="$booking->MaBooking ?: '—'" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$booking->TrangThai" :enum="\App\Enums\BookingStatus::class" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="HÌNH THỨC NHÂN VIÊN NHẬN ĐỒ" :value="$booking->method_label" />
                <x-admin.detail.info-item label="ĐỊA CHỈ NHÂN VIÊN NHẬN ĐỒ" :value="$receiveAtStore ? $storeAddress : ($booking->DiaChiNhan ?: '—')" />
                <x-admin.detail.info-item label="HÌNH THỨC NHÂN VIÊN GIAO ĐỒ" :value="$booking->return_method_label" />
                <x-admin.detail.info-item label="ĐỊA CHỈ NHÂN VIÊN GIAO ĐỒ" :value="$returnAtStore ? $storeAddress : ($booking->DiaChiTra ?: '—')" />
                <x-admin.detail.info-item label="Nhân viên xác nhận Booking" :value="$booking->nhanVienXacNhan?->HoTen ?? '—'" />
                <x-admin.detail.info-item label="Nhân viên nhận và kiểm kê" :value="$intakeDocument?->nhanVien?->HoTen ?? 'Chưa phân công'" />
                <x-admin.detail.info-item label="Thời gian xác nhận" :value="$booking->ThoiGianXacNhan?->format('d/m/Y H:i') ?? '—'" />
                <x-admin.detail.info-item label="Ngày hẹn" :value="$booking->NgayHen?->format('d/m/Y')" />
                <x-admin.detail.info-item label="Giờ hẹn" :value="$booking->GioHen?->format('H:i')" />
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Dịch vụ khách đã chọn ({{ $booking->chiTietBookings->count() + $booking->requestedServices->count() + ($booking->requestedServices->isEmpty() ? $legacyRequestedServices->count() : 0) }} mục)</div>
                @foreach($booking->chiTietBookings as $item)
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between gap-3">
                            <strong>{{ $item->dichVu?->TenDichVu ?? 'Dịch vụ không còn tồn tại' }} · {{ $item->loaiDoGiat?->TenLoaiDoGiat ?? 'Loại đồ không còn tồn tại' }}</strong>
                            <strong>{{ number_format((float) $item->ThanhTien, 0, ',', '.') }} VNĐ</strong>
                        </div>
                        <div class="text-muted small mt-1">
                            {{ format_quantity_weight($item->SoLuong, $item->KhoiLuong) ?: '—' }}
                            · Đơn giá {{ number_format((float) $item->DonGia, 0, ',', '.') }} VNĐ
                        </div>
                        @if($item->GhiChu)
                            <div class="text-muted small mt-1">{{ $item->GhiChu }}</div>
                        @endif
                    </div>
                @endforeach
                @foreach($booking->requestedServices as $requestedService)
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between gap-3">
                            <strong>{{ $requestedService->dichVu?->TenDichVu ?? 'Dịch vụ không còn tồn tại' }} · {{ $requestedService->loaiDoGiat?->TenLoaiDoGiat ?? 'Loại đồ không còn tồn tại' }}</strong>
                            <strong class="text-muted">Chờ kiểm tra</strong>
                        </div>
                        <div class="text-muted small mt-1">
                            Đơn vị dự kiến {{ $requestedService->donViTinh?->KyHieu ?: $requestedService->donViTinh?->TenDonViTinh ?: 'chưa rõ' }}
                            · Chờ kiểm tra số lượng/khối lượng, chưa báo giá
                        </div>
                    </div>
                @endforeach
                @if($booking->requestedServices->isEmpty())
                    @foreach($legacyRequestedServices as $legacyRequest)
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between gap-3">
                                <strong>{{ $legacyRequest }}</strong>
                                <strong class="text-muted">Chờ kiểm tra</strong>
                            </div>
                            <div class="text-muted small mt-1">Yêu cầu cũ lưu trong ghi chú · Chờ kiểm tra số lượng/khối lượng, chưa báo giá</div>
                        </div>
                    @endforeach
                @endif
                @if($booking->chiTietBookings->isEmpty() && $booking->requestedServices->isEmpty() && $legacyRequestedServices->isEmpty())
                    <div class="text-muted">Chưa có dịch vụ khách chọn.</div>
                @endif
            </div>

            @if(trim($visibleBookingNotes) !== '')
                <div class="mt-4">
                    <div class="detail-field__label mb-2">Ghi chú</div>
                    <div class="detail-text">{{ $visibleBookingNotes }}</div>
                </div>
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Ước tính từ lịch đặt" icon="bi-calculator" :iconClass="'bg-info-subtle text-info'">
            @if($booking->KhuyenMaiID && $booking->khuyenMai)
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        <i class="fas fa-ticket me-1"></i>{{ $booking->khuyenMai->MaKhuyenMai }}
                    </span>
                    <span class="text-muted small">Đã áp dụng · {{ $booking->khuyenMai->discountValueLabel() }}</span>
                </div>
            @else
                <div class="text-muted mb-3">Không dùng mã giảm giá.</div>
            @endif
            @if($bookingAmounts !== null)
                @php
                    $bookingRows = [
                        'TongTien' => 'Tạm tính các dịch vụ đã rõ số lượng',
                        'PhiGiaoHang' => 'Tổng phí giao nhận',
                        'TienGiamKhuyenMai' => 'Giảm từ mã khuyến mãi',
                        'TienGiamDoDiem' => 'Giảm từ điểm',
                        'TongDuocGiam' => 'Tổng tiền được giảm',
                        'ThanhTien' => 'Tổng tiền thanh toán (ước tính)',
                    ];
                    $bookingAmounts['TongDuocGiam'] = (float) $bookingAmounts['TienGiamKhuyenMai'] + (float) $bookingAmounts['TienGiamDoDiem'];
                @endphp
                <dl class="mb-0" data-booking-money-summary>
                    @foreach($bookingRows as $field => $label)
                        <div class="d-flex justify-content-between gap-3 py-2 {{ $field === 'ThanhTien' ? 'border-top fw-bold' : '' }}">
                            <dt class="fw-normal">{{ $label }}</dt>
                            <dd class="mb-0 text-end" data-booking-amount="{{ $field }}"><x-admin.detail.money :value="$bookingAmounts[$field]" :negative="in_array($field, ['TienGiamKhuyenMai', 'TienGiamDoDiem', 'TongDuocGiam'])" /></dd>
                        </div>
                    @endforeach
                </dl>
                @if($bookingAmounts['promotion_warning'])<div class="alert alert-warning mt-3 mb-0" role="status">{{ $bookingAmounts['promotion_warning'] }}</div>@endif
                <p class="text-muted small mt-3 mb-0">Ước tính theo dịch vụ đã đặt và số điểm đã chọn. Tiền chốt được tính lại khi kiểm kê đồ thực tế; xem đơn hàng liên kết để biết số tiền đã xác nhận.</p>
            @else
                <p class="text-muted mb-0">Chưa có dịch vụ dự kiến để tính tổng tiền. Tổng thanh toán sẽ được tính khi kiểm kê đồ thực tế.</p>
            @endif
        </x-admin.detail.panel>
        @include('admin.bookings.mobile-summary')
    </div>

    <div class="col-lg-4">
        <x-admin.detail.panel title="Khách hàng" icon="bi-person" :iconClass="'bg-secondary-subtle text-secondary'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Họ và tên" :value="$booking->khachHang?->HoTen" />
                <x-admin.detail.info-item label="Số điện thoại" :value="$booking->khachHang?->SoDienThoai" />
                <x-admin.detail.info-item label="Ngày tạo" :value="$booking->NgayTao?->format('d/m/Y H:i')" />
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>

        @if($booking->giaoNhanDuKiens->isNotEmpty())
            <x-admin.detail.panel title="Phiếu nhận và kế hoạch trả từ Booking" icon="bi-truck" :iconClass="'bg-info-subtle text-info'">
                @foreach($booking->giaoNhanDuKiens as $leg)
                    <div class="border rounded p-3 mb-2">
                        <strong>{{ $leg->LoaiGiaoNhan === 'NHAN_DO' ? 'Phiếu nhận đồ' : 'Kế hoạch trả đồ' }}</strong>
                        <span class="ms-2">{{ $leg->HinhThuc }}</span>
                        <span class="ms-2 text-muted">{{ $leg->TrangThai }}</span>
                        <div class="text-muted small">{{ $leg->DiaChi ?: 'Tại cửa hàng' }}</div>
                        @if($leg->LoaiGiaoNhan === 'NHAN_DO')
                            <div class="small text-muted mt-2">
                                {{ $leg->HinhThuc === 'Tại nhà' ? 'Nhân viên đến nhận đồ tại địa chỉ trên.' : 'Khách mang đồ đến cửa hàng để nhân viên tiếp nhận.' }}
                            </div>
                            @if($leg->TrangThai === \App\Enums\DeliveryStatus::Cancelled->dbValue())
                                <div class="small text-muted mt-2">Phiếu đã hủy; không thể tiếp tục kiểm kê.</div>
                            @elseif(! $leg->NhanVienID)
                                <div class="small text-muted mt-2">Chưa phân công nhân viên nhận và kiểm kê.</div>
                            @endif
                        @else
                            <div class="small text-muted mt-2">Thông tin kế hoạch từ Booking; đây chưa phải phiếu giao vận hành.</div>
                        @endif
                        @if($leg->DonHangID && $leg->BookingKiemKeID === $booking->kiemKe?->BookingKiemKeID)
                            <div class="small mt-2">
                                Đã đối soát từ phiếu nhận · kiểm kê phiên bản {{ $booking->kiemKe->PhienBan }} ·
                                @can('orders.view')
                                    <a href="{{ route('orders.show', $order) }}">Đơn {{ $order->MaDonHang }}</a>
                                @else
                                    Đơn {{ $order->MaDonHang }}
                                @endcan
                            </div>
                            @if($leg->LoaiGiaoNhan === 'GIAO_DO')
                                <ul class="small mb-0 mt-2 ps-3" data-booking-return-items>
                                    @foreach($order->chiTietDonHangs as $item)
                                        <li>{{ $item->item_name }} · {{ $item->KhoiLuong ?: $item->SoLuong }} {{ $item->unit }} · {{ $item->TinhTrangTruocKhiGiat }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </div>
                @endforeach
            </x-admin.detail.panel>
        @endif
    </div>
</div>

@if($order)
    <div class="row g-4 align-items-start">
        <div class="col-12">
        <x-admin.detail.panel title="Đơn hàng liên kết" icon="bi-receipt" :iconClass="'bg-success-subtle text-success'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Mã đơn hàng">
                    <a href="{{ route('orders.show', $order) }}" class="text-decoration-none">
                        {{ $order->MaDonHang }}
                    </a>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái đơn">
                    <x-admin.status-badge :status="$order->TrangThai" :enum="\App\Enums\OrderStatus::class" :pill="false" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Tổng thanh toán">
                    <x-admin.detail.money :value="$order->ThanhTien" class="fw-bold" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
        </div>
    </div>
@endif
@endsection
