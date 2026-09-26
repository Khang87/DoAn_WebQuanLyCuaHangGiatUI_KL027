@extends('layouts.app')

@section('title', 'Dashboard nhân viên - Sky Laundry')
@section('page-title', 'Dashboard nhân viên')

@section('content')
@php
    $orderStatusLabels = [
        'pending'    => 'Chờ tiếp nhận',
        'received'   => 'Đã nhận đồ',
        'sorting'    => 'Đang phân loại',
        'processing' => 'Đang giặt / Xử lý',
        'washed'     => 'Đã giặt xong',
        'delivering' => 'Đang giao đồ',
        'completed'  => 'Hoàn thành',
        'cancelled'  => 'Đã hủy',
    ];

    $statusBadgeMap = [
        'pending'    => 'bg-warning-subtle text-warning-emphasis border-warning',
        'received'   => 'bg-info-subtle text-info border-info',
        'sorting'    => 'bg-primary-subtle text-primary border-primary',
        'processing' => 'bg-info-subtle text-info border-info',
        'washed'     => 'bg-success-subtle text-success border-success',
        'delivering' => 'bg-primary-subtle text-primary border-primary',
        'completed'  => 'bg-success-subtle text-success border-success',
        'cancelled'  => 'bg-danger-subtle text-danger border-danger',
    ];
@endphp

<!-- ===== Operational KPI Cards Row ===== -->
<div class="row g-4 mb-4">
    <!-- Đơn hàng chờ tiếp nhận -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-dark">{{ number_format($waitingReceiveCount) }}</div>
                        <div class="stat-label text-dark">Chờ tiếp nhận</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-hourglass-top"></i></div>
                </div>
                <div class="mt-3">
                    <small class="text-warning-emphasis"><i class="bi bi-exclamation-circle"></i> Cần kiểm tra đồ và nhận hàng</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Đơn hàng đang giặt / xử lý -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-dark">{{ number_format($washingCount) }}</div>
                        <div class="stat-label text-dark">Đang giặt / xử lý</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-gear-wide"></i></div>
                </div>
                <div class="mt-3">
                    <small class="text-info"><i class="bi bi-gear-wide"></i> Đang trong quy trình giặt ủi</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Đơn hàng đã giặt xong (chờ giao) -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-dark">{{ number_format($readyCount) }}</div>
                        <div class="stat-label text-dark">Đã giặt xong</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
                </div>
                <div class="mt-3">
                    <small class="text-success"><i class="bi bi-box-seam"></i> Chờ giao hàng hoặc khách lấy</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Lịch nhận / giao đồ hôm nay -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-dark">{{ number_format($pickupCount + $deliveryCount) }}</div>
                        <div class="stat-label text-dark">Lịch nhận / giao hôm nay</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-truck"></i></div>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        <span class="badge bg-info-subtle text-info border border-info rounded-pill">{{ $pickupCount }} Nhận</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary rounded-pill">{{ $deliveryCount }} Giao</span>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Orders To Process ===== -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Đơn hàng cần xử lý</h5>
                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Mã Đơn</th>
                                <th>Khách hàng</th>
                                <th>SĐT</th>
                                <th>Trạng thái hiện Tại</th>
                                <th>Cập nhật nhanh</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($processingOrders as $order)
                            <tr>
                                <td><strong>{{ $order->code }}</strong></td>
                                <td>{{ $order->customer?->name ?: '-' }}</td>
                                <td>{{ $order->customer?->phone ?: '-' }}</td>
                                <td>
                                    @php
                                        $label = $orderStatusLabels[$order->status] ?? 'Chờ tiếp nhận';
                                        $badge = $statusBadgeMap[$order->status] ?? 'bg-secondary-subtle text-secondary border-secondary';
                                    @endphp
                                    <span class="badge {{ $badge }} px-3 py-2 rounded-pill">{{ $label }}</span>
                                </td>
                                <td style="min-width: 200px;">
                                    <select class="form-select form-select-sm status-select"
                                            data-order-id="{{ $order->id }}"
                                            data-current-status="{{ $order->status }}">
                                        <option value="" selected disabled>Chuyển trạng thái...</option>
                                        @foreach($statusFlow as $value => $label)
                                            @if($value !== 'cancelled' && $value !== 'completed' && $value !== $order->status)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <a href="{{ route('orders.show', $order) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Không có đơn hàng cần xử lý</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Pickup/Delivery Schedule & Upcoming Bookings ===== -->
<div class="row g-4">
    <!-- Lịch nhận đồ / giao đồ hôm nay -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 rounded-3 h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Lịch nhận / giao đồ hôm nay</h5>
                <a href="{{ route('deliveries.index') }}" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Khách hàng</th>
                                <th>SĐT</th>
                                <th>Địa chỉ</th>
                                <th>Loại</th>
                                <th>Giờ</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($todaySchedule as $delivery)
                            <tr>
                                <td>{{ $delivery->customer?->name ?: '-' }}</td>
                                <td>{{ $delivery->customer?->phone ?: '-' }}</td>
                                <td>{{ $delivery->address ?: 'Chưa có địa chỉ' }}</td>
                                <td>
                                    @php
                                        $typeLabel = match($delivery->method) {
                                            'nhan_do' => 'Nhận đồ',
                                            'giao_do' => 'Giao đồ',
                                            default => 'Giao đồ',
                                        };
                                        $typeBadge = $delivery->method === 'giao_do'
                                            ? 'bg-primary-subtle text-primary border-primary'
                                            : 'bg-info-subtle text-info border-info';
                                    @endphp
                                    <span class="badge {{ $typeBadge }} px-2 py-1 rounded-pill">{{ $typeLabel }}</span>
                                </td>
                                <td>{{ $delivery->pickup_time?->format('H:i') ?: '-' }}</td>
                                <td>
                                    <span class="badge {{ $delivery->status_badge_class }} px-2 py-1 rounded-pill">
                                        {{ $delivery->status_label }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Không có lịch hôm nay</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<!-- Lịch hẹn sắp tới -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Lịch hẹn sắp tới</h5>
                    <a href="{{ route('bookings.index') }}" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
                </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Khách hàng</th>
                                <th>SĐT</th>
                                <th>Ngày / Giờ</th>
                                <th>Loại</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($upcomingBookings as $booking)
                            <tr>
                                <td>{{ $booking->customer?->name ?: '-' }}</td>
                                <td>{{ $booking->customer?->phone ?: '-' }}</td>
                                <td>
                                    {{ $booking->scheduled_date?->format('d/m') }}
                                    <small class="text-muted">{{ $booking->scheduled_time?->format('H:i') }}</small>
                                </td>
                                <td>{{ $booking->method_label }}</td>
                                <td>
                                    <span class="badge {{ $booking->status_badge_class }} px-2 py-1 rounded-pill">
                                        {{ $booking->status_label }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Không có lịch hẹn sắp tới</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selects = document.querySelectorAll('.status-select');

        selects.forEach(function(select) {
            select.addEventListener('change', function() {
                const orderId = this.getAttribute('data-order-id');
                const newStatus = this.value;

                if (!newStatus) return;

                fetch('{{ route('staff.dashboard.update-order-status', ['order' => '__ID__']) }}'.replace('__ID__', orderId), {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ status: newStatus })
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Đã cập nhật!',
                            text: 'Đơn hàng đã chuyển sang: ' + data.status_label,
                            timer: 1500,
                            showConfirmButton: false
                        });

                        // Loại bỏ dòng khỏi danh sách sau khi cập nhật thành công
                        setTimeout(function() {
                            const row = select.closest('tr');
                            if (row) row.remove();
                        }, 1200);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        select.selectedIndex = 0;
                    }
                })
                .catch(function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi!',
                        text: 'Có lỗi xảy ra. Vui lòng thử lại.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    select.selectedIndex = 0;
                });
            });
        });
    });
</script>
@endpush
