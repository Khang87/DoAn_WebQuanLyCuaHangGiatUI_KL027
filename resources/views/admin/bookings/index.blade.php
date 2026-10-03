@extends('layouts.app')

@section('title', 'Đặt lịch - Sky Laundry')
@section('page-title', 'Đặt lịch')

@section('content')
<div class="page-toolbar">
    <p class="text-muted page-toolbar__desc">Tiếp nhận lịch hẹn. Khi chuyển lịch sang <strong>Đã xác nhận</strong>, hệ thống tự động tạo đơn hàng có mã tham chiếu về lịch.</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3"><i class="fas fa-search text-muted"></i></span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm theo mã lịch, tên khách hàng, SĐT..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="\App\Enums\BookingStatus::options()"
            placeholder="Tất cả trạng thái"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="method" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">Tất cả hình thức</option>
            @foreach(\App\Enums\BookingMethod::options() as $value => $label)
                <option value="{{ $value }}" @selected(request('method') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead><tr><th>STT</th><th>Mã lịch hẹn</th><th>Khách hàng</th><th>Nhân viên</th><th>Hình thức</th><th>Ngày hẹn</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                <tbody>
                @forelse($bookings as $booking)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $booking->MaBooking }}</strong></td>
                        <td><div class="fw-semibold">{{ $booking->khachHang?->HoTen ?: '-' }}</div><small class="text-muted">{{ $booking->khachHang?->SoDienThoai ?: 'Chưa có SĐT' }}</small></td>
                        <td>{{ $booking->nhanVien?->HoTen ?: 'Chưa phân công' }}</td>
                        <td>
                            <i class="bi {{ $booking->method_icon }} me-1"></i>{{ $booking->HinhThucNhanDo }}
                        </td>
                        <td>{{ $booking->NgayHen?->format('d/m/Y') ?: '-' }} {{ $booking->GioHen?->format('H:i') ?: '' }}</td>
                        <td><x-admin.status-badge :status="$booking->TrangThai" :enum="\App\Enums\BookingStatus::class" /></td>
                        <td><div class="d-flex gap-2">
                            @can('bookings.view')
                                <a href="{{ route('bookings.show', $booking) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                            @endcan
                            @can('bookings.edit')
                                <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                            @endcan
                            @can('bookings.confirm')
                                @if($booking->statusEnum() === \App\Enums\BookingStatus::Pending)
                                    @if($booking->chiTietBookings->isNotEmpty())
                                        <button
                                            type="button"
                                            class="btn btn-order-action"
                                            title="Xác nhận lịch và tự động tạo đơn hàng"
                                            data-bs-toggle="modal"
                                            data-bs-target="#confirmBookingModal"
                                            data-confirm-booking-action="{{ route('bookings.confirm', $booking) }}"
                                            data-booking-id="{{ $booking->BookingID }}"
                                            data-booking-employee="{{ $booking->NhanVienID }}"
                                        >
                                            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                            <span class="visually-hidden">Xác nhận Booking</span>
                                        </button>
                                    @elseif(auth()->user()?->can('bookings.edit'))
                                        <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-order-action text-warning" aria-label="Cần thêm dịch vụ trước khi xác nhận" title="Chưa thể xác nhận: hãy thêm ít nhất một dòng dịch vụ trước.">
                                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                        </a>
                                    @else
                                            <span class="btn btn-order-action text-warning" aria-label="Cần thêm dịch vụ trước khi xác nhận" title="Chưa thể xác nhận: cần nhân viên có quyền sửa Booking thêm ít nhất một dòng dịch vụ.">
                                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                            </span>
                                    @endif
                                @endif
                            @endcan
                            @can('bookings.edit')
                                @if(! $booking->donHangs->first() && $booking->statusEnum() === \App\Enums\BookingStatus::Confirmed)
                                    <span class="btn btn-order-action text-warning" aria-label="Thiếu đơn hàng" title="Booking đã xác nhận nhưng chưa có đơn hàng. Vui lòng kiểm tra nhật ký hệ thống."><i class="bi bi-exclamation-triangle"></i></span>
                                @elseif($booking->donHangs->first())
                                    <a href="{{ route('orders.show', $booking->donHangs->first()) }}" class="btn btn-order-action view" title="Xem đơn hàng {{ $booking->donHangs->first()->MaDonHang }}"><i class="bi bi-box-arrow-up-right"></i></a>
                                @endif
                            @endcan
                            @can('bookings.delete')
                                <form action="{{ route('bookings.destroy', $booking) }}" method="POST" class="d-inline" id="deleteBookingForm_{{ $booking->BookingID }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Chưa có dữ liệu đặt lịch.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($bookings->hasPages())
<div class="mt-4 d-flex justify-content-between align-items-center">
    <small class="text-muted">Hiển thị {{ $bookings->firstItem() }} - {{ $bookings->lastItem() }} của {{ $bookings->total() }} lịch hẹn</small>
    {{ $bookings->links('pagination::bootstrap-5') }}
</div>
@endif

<div class="modal fade" id="confirmBookingModal" tabindex="-1" aria-labelledby="confirmBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form
                id="confirmBookingForm"
                action="{{ old('booking_id') ? route('bookings.confirm', old('booking_id')) : '#' }}"
                method="POST"
                data-confirm-booking-form
            >
                @csrf
                <input type="hidden" name="booking_id" value="{{ old('booking_id') }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmBookingModalLabel">Xác nhận và tạo đơn hàng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="confirm-booking-employee">
                        Nhân viên phụ trách <span class="text-danger">*</span>
                    </label>
                    <select
                        class="form-select @error('NhanVienID') is-invalid @enderror"
                        id="confirm-booking-employee"
                        name="NhanVienID"
                        required
                    >
                        <option value="">Chọn nhân viên phụ trách</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->NhanVienID }}" @selected(old('NhanVienID') == $employee->NhanVienID)>{{ $employee->HoTen }}</option>
                        @endforeach
                    </select>
                    @error('NhanVienID')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Xác nhận và tạo đơn
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const confirmBookingModal = document.getElementById('confirmBookingModal');
        const confirmBookingForm = document.getElementById('confirmBookingForm');
        const responsibleEmployee = document.getElementById('confirm-booking-employee');
        const bookingId = confirmBookingForm.querySelector('[name="booking_id"]');

        document.querySelectorAll('[data-confirm-booking-action]').forEach(function(button) {
            button.addEventListener('click', function() {
                confirmBookingForm.action = button.dataset.confirmBookingAction;
                bookingId.value = button.dataset.bookingId;
                responsibleEmployee.value = button.dataset.bookingEmployee || '';
            });
        });

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

        @if($errors->has('NhanVienID'))
            bootstrap.Modal.getOrCreateInstance(confirmBookingModal).show();
        @endif

        document.querySelectorAll('[id^="deleteBookingForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa lịch hẹn này?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa lịch hẹn?',
                    text: 'Hành động này không thể hoàn tác.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Xóa',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush