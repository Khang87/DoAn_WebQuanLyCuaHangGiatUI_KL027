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
            placeholder="-- Tất cả trạng thái --"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="method" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">-- Tất cả hình thức --</option>
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
                                @if($booking->statusEnum() === \App\Enums\BookingStatus::Pending)
                                    <span class="btn btn-order-action" disabled title="Đơn hàng sẽ tự động được tạo khi chuyển sang trạng thái Đã xác nhận"><i class="bi bi-hourglass-split"></i></span>
                                @elseif(! $booking->donHangs->first() && $booking->statusEnum() === \App\Enums\BookingStatus::Confirmed)
                                    @can('orders.view')
                                        <form action="{{ route('bookings.confirm', $booking) }}" method="POST" class="d-inline" id="confirmBookingForm_{{ $booking->BookingID }}">
                                            @csrf
                                            <button type="submit" class="btn btn-order-action view" title="Tạo đơn hàng"><i class="bi bi-cart-plus"></i></button>
                                        </form>
                                    @endcan
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
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="confirmBookingForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Chuyển lịch này thành đơn hàng?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Tạo đơn hàng từ lịch hẹn?',
                    text: 'Lịch hẹn này sẽ được chuyển thành đơn hàng.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Tạo đơn hàng',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });

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