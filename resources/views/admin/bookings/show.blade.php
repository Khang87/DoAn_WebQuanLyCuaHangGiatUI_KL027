@extends('layouts.app')

@section('title', 'Chi tiết đặt lịch - Sky Laundry')
@section('page-title', 'Chi tiết đặt lịch')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
    <div class="d-flex gap-2">
        <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>Chỉnh sửa</a>
        @if($booking->status === 'confirmed')
            <form action="{{ route('bookings.confirm', $booking) }}" method="POST" id="confirmBookingForm">
                @csrf
                <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-cart-plus me-1"></i>Tạo đơn hàng</button>
            </form>
        @endif
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Lịch hẹn #BK{{ $booking->id }}</h5></div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6"><table class="table table-borderless mb-0"><tr><td class="text-muted">Khách hàng</td><td class="text-end fw-semibold">{{ $booking->customer?->name ?: '—' }}</td></tr><tr><td class="text-muted">Số điện thoại</td><td class="text-end">{{ $booking->customer?->phone ?: '—' }}</td></tr><tr><td class="text-muted">Nhân viên phụ trách</td><td class="text-end">{{ $booking->staff?->name ?: 'Chưa phân công' }}</td></tr></table></div>
            <div class="col-md-6"><table class="table table-borderless mb-0"><tr><td class="text-muted">Hình thức</td><td class="text-end fw-semibold">{{ $booking->method_label }}</td></tr><tr><td class="text-muted">Ngày hẹn</td><td class="text-end">{{ $booking->scheduled_date?->format('d/m/Y') ?: '—' }}</td></tr><tr><td class="text-muted">Giờ hẹn</td><td class="text-end">{{ $booking->scheduled_time?->format('H:i') ?: '—' }}</td></tr><tr><td class="text-muted">Trạng thái</td><td class="text-end"><span class="badge {{ $booking->status_badge_class }}">{{ $booking->status_label }}</span></td></tr></table></div>
        </div>
        @if($booking->notes)<div class="alert alert-light border mt-4 mb-0"><strong>Ghi chú:</strong> {{ $booking->notes }}</div>@endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('confirmBookingForm');
        if (!form) return;

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
</script>
@endpush
