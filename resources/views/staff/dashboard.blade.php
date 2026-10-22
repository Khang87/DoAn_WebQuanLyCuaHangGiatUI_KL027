@extends('layouts.app')

@section('title', 'Dashboard nhân viên - Sky Laundry')
@section('page-title', 'Dashboard nhân viên')

@section('content')
<!-- ===== Operational KPI Cards Row ===== -->
@if(auth()->user()?->canAny(['orders.view', 'deliveries.view']))
<div class="row g-4 mb-4">
    <!-- Đơn hàng chờ tiếp nhận -->
    @can('orders.view')
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
    @endcan
    @can('deliveries.view')
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
                        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary rounded-pill">{{ $pickupCount }} Nhận</span>
                        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary rounded-pill">{{ $deliveryCount }} Giao</span>
                    </small>
                </div>
            </div>
        </div>
    </div>
    @endcan
</div>
@endif

<!-- ===== Orders To Process ===== -->
@can('orders.view')
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Đơn hàng cần xử lý</h5>
                @can('orders.view')<a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-primary">Xem tất cả</a>@endcan
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0"
                           @can('orders.update_status') data-staff-order-status-url="{{ route('staff.dashboard.update-order-status', ['order' => '__ID__']) }}" @endcan>
                        <thead>
                            <tr>
                                <th scope="col">STT</th>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>SĐT</th>
                                <th>Trạng thái hiện Tại</th>
                                @can('orders.update_status')<th>Cập nhật nhanh</th>@endcan
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($processingOrders as $order)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $order->MaDonHang }}</strong></td>
                                <td>{{ $order->khachHang?->HoTen ?: '-' }}</td>
                                <td>{{ $order->khachHang?->SoDienThoai ?: '-' }}</td>
                                <td><x-admin.status-badge :status="$order->TrangThai" :enum="\App\Enums\OrderStatus::class" /></td>
                                @can('orders.update_status')
                                <td style="min-width: 200px;">
                                    <select class="form-select form-select-sm status-select"
                                            data-order-id="{{ $order->DonHangID }}"
                                            data-current-status="{{ $order->TrangThai }}">
                                        <option value="" selected disabled>Chuyển trạng thái...</option>
                                        @foreach($quickStatusFlow as $value => $label)
                                            @if($value !== $order->TrangThai
                                                && ! ($order->TrangThai === \App\Enums\OrderStatus::Washed->value
                                                    && ($value === \App\Enums\OrderStatus::Delivering->value
                                                        || ($order->requiresHomeDelivery()
                                                            && $value === \App\Enums\OrderStatus::Delivered->value))))
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endif
                                        @endforeach
                                        @if($order->TrangThai === \App\Enums\OrderStatus::Washed->value && ! $order->requiresHomeDelivery())
                                            <option value="{{ \App\Enums\OrderStatus::Delivered->value }}">
                                                {{ \App\Enums\OrderStatus::Delivered->label() }} (khách nhận tại cửa hàng)
                                            </option>
                                        @endif
                                    </select>
                                </td>
                                @endcan
                                <td>
                                    @if($order->TrangThai === \App\Enums\OrderStatus::Washed->value
                                        && $order->requiresHomeDelivery()
                                        && ($order->hasActiveReturnDelivery()
                                            ? auth()->user()?->canPermission('deliveries.edit')
                                            : auth()->user()?->canPermission('deliveries.create')))
                                        <a href="{{ route('orders.delivery-assignment', $order) }}" class="btn btn-order-action edit" title="Phân công giao hàng">
                                            <i class="bi bi-truck"></i>
                                        </a>
                                    @endif
                                    @can('orders.view')<a href="{{ route('orders.show', $order) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>@endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="{{ auth()->user()?->can('orders.update_status') ? 7 : 6 }}" class="text-center text-muted py-4">Không có đơn hàng cần xử lý</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endcan

<!-- ===== Pickup/Delivery Schedule & Upcoming Bookings ===== -->
@if(auth()->user()?->canAny(['deliveries.view', 'bookings.view']))
<div class="row g-4">
    <!-- Lịch nhận đồ / giao đồ hôm nay -->
    @can('deliveries.view')
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 rounded-3 h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Lịch nhận / giao đồ hôm nay</h5>
                @can('deliveries.view')<a href="{{ route('deliveries.index') }}" class="btn btn-sm btn-outline-primary">Xem tất cả</a>@endcan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th scope="col">STT</th>
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
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $delivery->donHang?->khachHang?->HoTen ?: '-' }}</td>
                                <td>{{ $delivery->donHang?->khachHang?->SoDienThoai ?: '-' }}</td>
                                <td>{{ $delivery->DiaChi ?: 'Chưa có địa chỉ' }}</td>
                                <td>
                                    @php
                                        $typeLabel = match($delivery->LoaiGiaoNhan) {
                                            'NHAN_DO' => 'Nhận đồ',
                                            'GIAO_DO' => 'Giao đồ',
                                            default => $delivery->LoaiGiaoNhan,
                                        };
                                        $typeBadge = 'bg-primary-subtle text-primary-emphasis border border-primary';
                                    @endphp
                                    <span class="badge {{ $typeBadge }} px-2 py-1 rounded-pill">{{ $typeLabel }}</span>
                                </td>
                                <td>{{ $delivery->ThoiGianDuKien?->format('H:i') ?: '-' }}</td>
                                <td>
                                    @php
                                        [$deliveryStatusLabel, $deliveryStatusClass] = match ($delivery->TrangThai) {
                                            'Chờ thực hiện' => ['Chờ thực hiện', 'bg-warning-subtle text-warning-emphasis border border-warning'],
                                            'Đang thực hiện' => ['Đang thực hiện', 'bg-primary-subtle text-primary-emphasis border border-primary'],
                                            'Hoàn thành' => ['Hoàn thành', 'bg-success-subtle text-success-emphasis border border-success'],
                                            'Đã hủy' => ['Đã hủy', 'bg-danger-subtle text-danger-emphasis border border-danger'],
                                            default => [$delivery->TrangThai, 'bg-secondary-subtle text-secondary-emphasis border border-secondary'],
                                        };
                                    @endphp
                                    <span class="badge {{ $deliveryStatusClass }} px-2 py-1 rounded-pill">{{ $deliveryStatusLabel }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Không có lịch hôm nay</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @endcan
<!-- Lịch hẹn sắp tới -->
    @can('bookings.view')
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Lịch hẹn sắp tới</h5>
                    @can('bookings.view')<a href="{{ route('bookings.index') }}" class="btn btn-sm btn-outline-primary">Xem tất cả</a>@endcan
                </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th scope="col">STT</th>
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
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $booking->khachHang?->HoTen ?: '-' }}</td>
                                <td>{{ $booking->khachHang?->SoDienThoai ?: '-' }}</td>
                                <td>
                                    {{ $booking->NgayHen?->format('d/m') }}
                                    <small class="text-muted">{{ $booking->GioHen?->format('H:i') }}</small>
                                </td>
                                <td>{{ $booking->method_label }}</td>
                                <td>
                                    <x-admin.status-badge :status="$booking->TrangThai" :enum="\App\Enums\BookingStatus::class" size="px-2 py-1" />
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Không có lịch hẹn sắp tới</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endcan
</div>
@endif
@endsection

@push('scripts')
@vite('resources/js/staff-dashboard.js')
@endpush
