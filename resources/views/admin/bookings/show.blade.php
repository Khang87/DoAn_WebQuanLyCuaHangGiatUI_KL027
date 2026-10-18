@extends('layouts.app')

@section('title', 'Chi tiết đặt lịch - Sky Laundry')
@section('page-title', 'Chi tiết đặt lịch')

@section('content')
@php
    $bookingCode = $booking->MaBooking ?: 'BK' . str_pad($booking->BookingID, 4, '0', STR_PAD_LEFT);
@endphp

<x-admin.detail.page-header
    title="Lịch hẹn {{ $bookingCode }}"
    :subtitle="$booking->method_label . ' ngày ' . ($booking->NgayHen?->format('d/m/Y') ?: '—')"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$booking->TrangThai" :enum="\App\Enums\BookingStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    <div class="col-lg-7">
        <x-admin.detail.panel title="Thông tin lịch hẹn" icon="bi-calendar-check" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã tham chiếu">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        {{ $bookingCode }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$booking->TrangThai" :enum="\App\Enums\BookingStatus::class" :pill="false" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Hình thức nhận đồ">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        <i class="bi {{ $booking->method_icon }} me-1"></i>{{ $booking->method_label }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Nhân viên phụ trách" :value="$booking->nhanVien?->HoTen ?? 'Chưa phân công'" />
                <x-admin.detail.info-item label="Nhân viên xác nhận" :value="$booking->nhanVienXacNhan?->HoTen ?? '—'" />
                <x-admin.detail.info-item label="Thời gian xác nhận" :value="$booking->ThoiGianXacNhan?->format('d/m/Y H:i') ?? '—'" />
                <x-admin.detail.info-item label="Ngày hẹn" :value="$booking->NgayHen?->format('d/m/Y')" />
                <x-admin.detail.info-item label="Giờ hẹn" :value="$booking->GioHen?->format('H:i')" />
                <x-admin.detail.info-item label="Hình thức trả đồ" :value="$booking->return_method_label" />
                <x-admin.detail.info-item label="Địa chỉ trả đồ" :value="$booking->DiaChiTra ?: '—'" />
                <x-admin.detail.info-item label="Địa chỉ nhận đồ" :value="$booking->DiaChiNhan ?: '—'" />
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Dịch vụ dự kiến</div>
                @forelse($booking->chiTietBookings as $item)
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
                @empty
                    <div class="text-muted">Chưa có dịch vụ dự kiến.</div>
                @endforelse
            </div>

            @if($booking->GhiChu)
                <div class="mt-4">
                    <div class="detail-field__label mb-2">Ghi chú</div>
                    <div class="detail-text">{{ $booking->GhiChu }}</div>
                </div>
            @endif
        </x-admin.detail.panel>
    </div>

    <div class="col-lg-5 d-flex flex-column">
        <x-admin.detail.panel title="Ước tính từ lịch đặt" icon="bi-calculator" :iconClass="'bg-info-subtle text-info'">
            @if($booking->KhuyenMaiID)
                <div class="mb-3">Voucher: <strong>{{ $booking->khuyenMai?->MaKhuyenMai ?: 'Không tìm thấy mã' }}</strong>
                    @if($booking->khuyenMai)<span class="text-muted"> · {{ $booking->khuyenMai->TenKhuyenMai }}</span>@endif
                </div>
            @else
                <div class="text-muted mb-3">Không dùng voucher.</div>
            @endif
            @if($bookingAmounts !== null)
                <dl class="mb-0" data-booking-money-summary>
                    @foreach(['TongTien' => 'Tiền dịch vụ dự kiến', 'TienGiamKhuyenMai' => 'Giảm khuyến mãi', 'TienGiamDoDiem' => 'Giảm điểm tích lũy', 'PhiGiaoHang' => 'Phí nhận và giao đồ', 'ThanhTien' => 'Tổng thanh toán ước tính'] as $field => $label)
                        <div class="d-flex justify-content-between gap-3 py-2 {{ $field === 'ThanhTien' ? 'border-top fw-bold' : '' }}">
                            <dt class="fw-normal">{{ $label }}</dt>
                            <dd class="mb-0 text-end" data-booking-amount="{{ $field }}"><x-admin.detail.money :value="$bookingAmounts[$field]" :negative="in_array($field, ['TienGiamKhuyenMai', 'TienGiamDoDiem'])" /></dd>
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
        <x-admin.detail.panel title="Khách hàng" icon="bi-person" :iconClass="'bg-secondary-subtle text-secondary'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Họ và tên" :value="$booking->khachHang?->HoTen" />
                <x-admin.detail.info-item label="Số điện thoại" :value="$booking->khachHang?->SoDienThoai" />
                <x-admin.detail.info-item label="Ngày tạo" :value="$booking->NgayTao?->format('d/m/Y H:i')" />
            </x-admin.detail.info-grid>

            @if($booking->khachHang)
                <div class="mt-3">
                    <a href="{{ route('customers.show', $booking->khachHang->KhachHangID) }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Hồ sơ khách hàng
                    </a>
                </div>
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Đơn hàng liên kết" icon="bi-receipt" :iconClass="'bg-success-subtle text-success'">
            @php $order = $booking->donHangs->first() @endphp
            @if($order)
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
                <div class="detail-lock mt-3">
                    <i class="bi bi-info-circle"></i>
                    <span>Đơn hàng mới được tạo sau khi kiểm tra thực tế và có trạng thái Đã tiếp nhận. Đơn cũ đang Chờ tiếp nhận vẫn cần hoàn tất kiểm tra trên trang đơn hàng.</span>
                </div>
            @else
                <x-admin.detail.empty :message="config('giatui.booking_two_step') ? 'Booking chưa chuyển thành đơn hàng chính thức.' : 'Chưa có đơn — mở form kiểm tra thực tế để tiếp nhận Booking'" icon="bi-hourglass-split" />
            @endif
        </x-admin.detail.panel>

        @if(config('giatui.booking_two_step') && $booking->giaoNhanDuKiens->isNotEmpty())
            <x-admin.detail.panel title="Phiếu nhận và phiếu trả từ Booking" icon="bi-truck" :iconClass="'bg-info-subtle text-info'">
                @foreach($booking->giaoNhanDuKiens as $leg)
                    <div class="border rounded p-3 mb-2">
                        <strong>{{ $leg->LoaiGiaoNhan === 'NHAN_DO' ? 'Phiếu nhận' : 'Phiếu trả' }}</strong>
                        <span class="ms-2">{{ $leg->HinhThuc }}</span>
                        <span class="ms-2 text-muted">{{ $leg->TrangThai }}</span>
                        <div class="text-muted small">{{ $leg->DiaChi ?: 'Tại cửa hàng' }}</div>
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
                        @elseif($leg->LoaiGiaoNhan === 'GIAO_DO')
                            <div class="small text-muted mt-2">Chờ nhánh Nhận hoàn tất kiểm kê và tạo đơn.</div>
                        @endif
                    </div>
                @endforeach
            </x-admin.detail.panel>
        @endif

        @if(config('giatui.booking_two_step') && $booking->kiemKe)
            <x-admin.detail.panel title="Kiểm kê thực tế" icon="bi-clipboard-check" :iconClass="'bg-warning-subtle text-warning'">
                <p class="mb-1">Phiên bản {{ $booking->kiemKe->PhienBan }} · {{ count($booking->kiemKe->DuLieuThucTe['items'] ?? []) }} dòng đồ thực tế</p>
                <p class="mb-0">{{ $booking->kiemKe->CoChenhLech ? ($booking->kiemKe->isCustomerApproved() ? 'Khách đã xác nhận chênh lệch.' : 'Đang chờ khách xác nhận chênh lệch trên mobile.') : 'Không có chênh lệch.' }}</p>
            </x-admin.detail.panel>
        @endif

        <div class="card shadow-sm border-0 order-first">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @if(! config('giatui.booking_two_step') || $booking->statusEnum() === \App\Enums\BookingStatus::Pending)
                @can('bookings.edit')
                    <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-pencil-alt me-1"></i> Chỉnh sửa
                    </a>
                @endcan
                @endif

                @php $order = $booking->donHangs->first() @endphp
                @if($order)
                    @can('orders.view')
                    <a href="{{ route('orders.show', $order) }}" class="btn btn-outline-primary w-100 py-2">
                        <i class="bi bi-receipt me-1"></i> Xem đơn {{ $order->MaDonHang }}
                    </a>
                    @endcan
                @elseif(config('giatui.booking_two_step') && $booking->statusEnum() === \App\Enums\BookingStatus::Pending)
                    @can('bookings.confirm')
                        <form action="{{ route('bookings.approve', $booking) }}" method="POST">@csrf<button class="btn btn-primary w-100 py-2" type="submit">Xác nhận Booking</button></form>
                        <form action="{{ route('bookings.cancel', $booking) }}" method="POST">@csrf<button class="btn btn-outline-danger w-100 py-2" type="submit">Hủy Booking</button></form>
                    @endcan
                @elseif($booking->statusEnum() === \App\Enums\BookingStatus::Pending)
                    @can('bookings.confirm')
                        <a href="{{ route('bookings.inspection', $booking) }}" class="btn btn-outline-primary py-2 w-100">
                            <i class="bi bi-clipboard-check me-1"></i>Kiểm tra thực tế &amp; chuyển đổi
                        </a>
                    @endcan
                @elseif(config('giatui.booking_two_step') && $booking->statusEnum() === \App\Enums\BookingStatus::Confirmed)
                    @can('bookings.confirm')
                        @if($booking->giaoNhanDuKiens->firstWhere('LoaiGiaoNhan', 'NHAN_DO')?->TrangThai !== 'Đã nhận')
                            <form action="{{ route('bookings.receive', $booking) }}" method="POST">@csrf<button class="btn btn-primary w-100 py-2" type="submit">Ghi nhận đã nhận đồ</button></form>
                        @else
                        @if($booking->kiemKe && (! $booking->kiemKe->CoChenhLech || $booking->kiemKe->isCustomerApproved()))
                            <form action="{{ route('bookings.finalize-inspection', $booking) }}" method="POST">@csrf<button class="btn btn-success w-100 py-2" type="submit">Tạo đơn từ kiểm kê đã xác nhận</button></form>
                        @endif
                        <a href="{{ route('bookings.inspection', $booking) }}" class="btn btn-outline-primary py-2 w-100">
                            <i class="bi bi-clipboard-check me-1"></i>Kiểm kê và tạo đơn
                        </a>
                        @endif
                    @endcan
                @elseif($booking->statusEnum() === \App\Enums\BookingStatus::Confirmed)
                    <div class="alert alert-warning mb-0" role="alert">
                        Booking đã được xác nhận nhưng chưa có đơn hàng. Vui lòng kiểm tra nhật ký hệ thống.
                    </div>
                @endif

                @if(! $order)
                    @can('bookings.delete')
                        <x-admin.detail.confirm-form
                            :action="route('bookings.destroy', $booking)"
                            title="Xóa đặt lịch?"
                            text="Hành động này không thể hoàn tác."
                            label="Xóa đặt lịch"
                            icon="bi-trash"
                            variant="btn-outline-danger"
                            size="py-2"
                            block
                        />
                    @endcan
                @endif

                <a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
