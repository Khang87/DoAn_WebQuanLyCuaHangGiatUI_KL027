@extends('layouts.app')

@section('title', 'Đặt lịch - Sky Laundry')
@section('page-title', 'Đặt lịch')

@section('content')
<div class="mb-3 text-start">
    <h4 class="fw-bold mb-1">Quản lý đặt lịch</h4>
    <p class="text-muted small mb-0">Tiếp nhận lịch hẹn và chuyển lịch đã xác nhận thành đơn hàng.</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3"><i class="fas fa-search text-muted"></i></span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm theo mã lịch, tên khách hàng, SĐT..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            @foreach($statuses ?? \App\Enums\RecordStatus::options() as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="method" class="form-select shadow-sm rounded-3 py-2" onchange="this.form.submit()">
            <option value="">-- Tất cả hình thức --</option>
            <option value="nhan_do" @selected(request('method') === 'nhan_do')>Nhận đồ</option>
            <option value="giao_do" @selected(request('method') === 'giao_do')>Giao đồ</option>
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead><tr><th>STT</th><th>Mã</th><th>Khách hàng</th><th>Nhân viên</th><th>Hình thức</th><th>Ngày hẹn</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                <tbody>
                @forelse($bookings as $booking)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $booking->code ?: 'BK' . str_pad($booking->id, 4, '0', STR_PAD_LEFT) }}</strong></td>
                        <td><div class="fw-semibold">{{ $booking->customer?->name ?: '-' }}</div><small class="text-muted">{{ $booking->customer?->phone ?: 'Chưa có SĐT' }}</small></td>
                        <td>{{ $booking->staff?->name ?: 'Chưa phân công' }}</td>
                        <td>{{ $booking->method_label }}</td>
                        <td>{{ $booking->scheduled_date?->format('d/m/Y') ?: '-' }} {{ $booking->scheduled_time?->format('H:i') ?: '' }}</td>
                        <td><span class="badge {{ $booking->status_badge_class }}">{{ $booking->status_label }}</span></td>
                        <td><div class="d-flex gap-2">
                            <a href="{{ route('bookings.show', $booking) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                            @if($booking->status === 'confirmed')
                                <form action="{{ route('bookings.confirm', $booking) }}" method="POST" class="d-inline" id="confirmBookingForm_{{ $booking->id }}">
                                    @csrf
                                    <button type="submit" class="btn btn-order-action view" title="Tạo đơn hàng"><i class="bi bi-cart-plus"></i></button>
                                </form>
                            @endif
                            <form action="{{ route('bookings.destroy', $booking) }}" method="POST" class="d-inline" id="deleteBookingForm_{{ $booking->id }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                            </form>
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
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $bookings->firstItem() }} - {{ $bookings->lastItem() }} của {{ $bookings->total() }} lịch hẹn</div>
        <ul class="pagination mb-0">
            @if ($bookings->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $bookings->appends(request()->query())->url($bookings->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($bookings->getUrlRange(max(1, $bookings->currentPage() - 2), min($bookings->lastPage(), $bookings->currentPage() + 2)) as $page => $url)
                @if ($page == $bookings->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $bookings->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($bookings->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $bookings->appends(request()->query())->url($bookings->currentPage() + 1) }}">{{ $page }}</a></li>
            @endif
        </ul>
    </div>
</nav>
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