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
                <x-admin.detail.info-item label="Hình thức">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        <i class="bi {{ $booking->method_icon }} me-1"></i>{{ $booking->method_label }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Nhân viên phụ trách" :value="$booking->nhanVien?->HoTen ?? 'Chưa phân công'" />
                <x-admin.detail.info-item label="Nhân viên xác nhận" :value="$booking->nhanVienXacNhan?->HoTen ?? '—'" />
                <x-admin.detail.info-item label="Thời gian xác nhận" :value="$booking->ThoiGianXacNhan?->format('d/m/Y H:i') ?? '—'" />
                <x-admin.detail.info-item label="Ngày hẹn" :value="$booking->NgayHen?->format('d/m/Y')" />
                <x-admin.detail.info-item label="Giờ hẹn" :value="$booking->GioHen?->format('H:i')" />
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
                    <span>Đơn hàng được tạo tự động khi lịch chuyển sang trạng thái Đã xác nhận.</span>
                </div>
            @else
                <x-admin.detail.empty message="Chưa có đơn — sẽ tự động tạo khi lịch được xác nhận" icon="bi-hourglass-split" />
            @endif
        </x-admin.detail.panel>

        <div class="card shadow-sm border-0 order-first">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @can('bookings.edit')
                    <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-pencil-alt me-1"></i> Chỉnh sửa
                    </a>
                @endcan

                @php $order = $booking->donHangs->first() @endphp
                @if($order)
                    <a href="{{ route('orders.show', $order) }}" class="btn btn-outline-primary w-100 py-2">
                        <i class="bi bi-receipt me-1"></i> Xem đơn {{ $order->MaDonHang }}
                    </a>
                @elseif($booking->statusEnum() === \App\Enums\BookingStatus::Pending)
                    @if($booking->chiTietBookings->isNotEmpty() && auth()->user()?->can('bookings.confirm'))
                        <form action="{{ route('bookings.confirm', $booking) }}" method="POST" data-confirm-booking-form>
                            @csrf
                            <label class="form-label" for="booking-responsible-employee">
                                Nhân viên phụ trách <span class="text-danger">*</span>
                            </label>
                            <select
                                class="form-select @error('NhanVienID') is-invalid @enderror mb-2"
                                id="booking-responsible-employee"
                                name="NhanVienID"
                                required
                            >
                                <option value="">Chọn nhân viên phụ trách</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->NhanVienID }}" @selected(old('NhanVienID', $booking->NhanVienID) == $employee->NhanVienID)>{{ $employee->HoTen }}</option>
                                @endforeach
                            </select>
                            @error('NhanVienID')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                            <input type="hidden" name="booking_id" value="{{ $booking->BookingID }}">
                            <label class="form-label" for="booking-points-used">Dùng điểm tích lũy</label>
                            <input
                                class="form-control mb-2"
                                id="booking-points-used"
                                type="number"
                                name="DiemSuDung"
                                min="0"
                                max="{{ $booking->khachHang?->points ?? 0 }}"
                                value="{{ old('DiemSuDung', 0) }}"
                            >
                            <div class="form-text mb-2">
                                Khách đang có {{ number_format($booking->khachHang?->points ?? 0) }} điểm (tương đương {{ number_format(($booking->khachHang?->points ?? 0) * \App\Services\OrderService::POINT_VALUE) }} VNĐ)
                            </div>
                            <button type="submit" class="btn btn-outline-primary py-2 w-100">
                                <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i> Xác nhận và tạo đơn
                            </button>
                        </form>
                    @elseif($booking->chiTietBookings->isEmpty() && auth()->user()?->can('bookings.edit'))
                        <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-outline-primary py-2 w-100">
                            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i> Thêm dịch vụ trước khi xác nhận
                        </a>
                    @elseif($booking->chiTietBookings->isEmpty())
                        <div class="alert alert-warning mb-0" role="alert">
                            Chưa thể xác nhận: cần thêm ít nhất một dòng dịch vụ trước khi tạo đơn hàng.
                        </div>
                    @else
                        <span class="btn btn-outline-primary py-2 disabled w-100">
                            <i class="bi bi-hourglass-split me-1"></i> Chờ xác nhận
                        </span>
                    @endif
                @elseif($booking->statusEnum() === \App\Enums\BookingStatus::Confirmed)
                    <div class="alert alert-warning mb-0" role="alert">
                        Booking đã xác nhận nhưng chưa có đơn hàng. Vui lòng kiểm tra nhật ký hệ thống.
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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-confirm-booking-form]').forEach(function(form) {
            form.addEventListener('submit', function() {
                const button = form.querySelector('button[type="submit"]');
                if (!button) {
                    return;
                }

                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span class="visually-hidden">Đang xác nhận</span>';
            });
        });
    });
</script>
@endpush
