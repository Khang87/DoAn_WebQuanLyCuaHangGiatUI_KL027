@extends('layouts.app')

@section('title', 'Dashboard - Quản Lý Cửa Hàng Giặt Ủi')
@section('page-title', 'Dashboard')

@push('styles')
@if(auth()->user()->isAdmin())
<link rel="stylesheet" href="{{ asset('assets/libs/apexcharts/apexcharts.css') }}">
@endif
@endpush

@section('content')
<!-- Staff Shared Cards Row (Visible to Staff & Admin) -->
<div class="row g-4 mb-4">
    <!-- Card 1: Lịch Giao Nhận Hôm Nay -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ $todayDeliveries->count() }}</div>
                        <div class="stat-label">Lịch Giao Nhận Hôm Nay</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-truck"></i>
                    </div>
                </div>
                <div class="mt-3">
                        <small class="text-muted"><i class="bi bi-calendar3"></i> Đơn cần giao/nhận hôm nay</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Hóa Đơn Chờ Thanh Toán -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ $pendingInvoices->count() }}</div>
                        <div class="stat-label">Hóa Đơn Chờ Thanh Toán</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                </div>
                <div class="mt-3">
                        <small class="text-warning"><i class="bi bi-exclamation-circle"></i> Chưa thu tiền</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Khách Hàng Mới Hôm Nay -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ $newCustomersToday }}</div>
                        <div class="stat-label">Khách Hàng Mới Hôm Nay</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
                <div class="mt-3">
                        <small class="text-muted"><i class="bi bi-calendar3"></i> Trong ngày hôm nay</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <div class="stat-label">Lối Tắt Nhanh</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('orders.create') }}" class="btn btn-primary w-100 mb-2"><i class="fas fa-plus-circle me-1"></i> Tạo đơn hàng mới</a>
                    @endif
                    <a href="{{ route('customers.create') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-plus"></i> Tạo khách hàng mới</a>
                    <a href="{{ route('invoices.create') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-receipt"></i> Lập hóa đơn</a>
                </div>
            </div>
        </div>
    </div>
</div>

@if(auth()->user()->isAdmin())
<!-- Stats Cards Row (Admin Only) -->
<div class="row g-4 mb-4">
    <!-- Tổng Đơn Hàng -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ $totalOrders }}</div>
                        <div class="stat-label">Tổng Đơn Hàng</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                </div>
                <div class="mt-3">
                        <small class="text-muted"><i class="bi bi-database"></i> Dữ liệu thực tế</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Doanh Thu -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ number_format($revenue / 1000000, 1) }}M</div>
                        <div class="stat-label">Doanh Thu (VNĐ)</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                </div>
                <div class="mt-3">
                        <small class="text-muted"><i class="bi bi-database"></i> Từ đơn hàng hiện có</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Khách Hàng Mới -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ $newCustomers }}</div>
                        <div class="stat-label">Khách Hàng Mới</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
                <div class="mt-3">
                        <small class="text-muted"><i class="bi bi-calendar3"></i> Trong 30 ngày qua</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Đơn Chờ Xử Lý -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ $pendingOrders }}</div>
                        <div class="stat-label">Đơn Chờ Xử Lý</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="mt-3">
                        <small class="text-warning"><i class="bi bi-exclamation-circle"></i> Đơn đang chờ xử lý</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Doanh Thu Chart -->
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Thống Kê Doanh Thu</h5>
                <select class="form-select form-select-sm" style="width: auto;">
                    <option>7 ngày qua</option>
                    <option selected>30 ngày qua</option>
                    <option>3 tháng qua</option>
                </select>
            </div>
            <div class="card-body">
                <div id="revenue-chart" style="height: 300px;"></div>
            </div>
        </div>
    </div>

    <!-- Trạng Thái Đơn Hàng -->
    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Trạng Thái Đơn Hàng</h5>
            </div>
            <div class="card-body">
                <div id="status-chart" style="height: 300px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Orders & Recent Customers Row -->
<div class="row g-4">
    <!-- Đơn Hàng Gần Đây -->
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Đơn Hàng Gần Đây</h5>
                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-primary">Xem Tất Cả</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Mã Đơn</th>
                                <th>Khách Hàng</th>
                                <th>Dịch Vụ</th>
                                <th>Tổng Tiền</th>
                                <th>Trạng Thái</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                            <tr>
                                <td><strong>{{ $order->code }}</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="{{ asset('assets/images/user.jfif') }}" alt="Ảnh khách hàng" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                        <span>{{ $order->customer?->name ?: '-' }}</span>
                                    </div>
                                </td>
                                <td>{{ $order->service?->name ?: '-' }}</td>
                                <td><strong>{{ number_format($order->total_amount) }} VNĐ</strong></td>
                                <td>
                                    @if($order->status === 'completed')
                                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoàn thành</span>
                                    @elseif($order->status === 'cancelled')
                                        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>Đã hủy</span>
                                    @elseif($order->status === 'pending')
                                        <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>Chờ xử lý</span>
                                    @elseif($order->status === 'processing')
                                        <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-cog me-1"></i>Đang xử lý</span>
                                    @elseif($order->status === 'delivering')
                                        <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill"><i class="fas fa-truck me-1"></i>Đang giao</span>
                                    @elseif($order->status === 'washing')
                                        <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-washer me-1"></i>Đang giặt</span>
                                    @elseif($order->status === 'washed')
                                        <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-tshirt-pocket me-1"></i>Đã giặt xong</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-2 rounded-pill"><i class="fas fa-circle-notch me-1"></i>{{ $order->status }}</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('orders.show', $order) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a></td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Chưa có đơn hàng</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Khách Hàng Mới -->
    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Khách Hàng Mới</h5>
                <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-primary">Xem Tất Cả</a>
            </div>
            <div class="card-body">
                @forelse($recentCustomers as $customer)
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <img src="{{ asset('assets/images/user.jfif') }}" alt="Ảnh khách hàng" class="rounded-circle me-3" style="width: 48px; height: 48px; object-fit: cover;">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $customer->name }}</div>
                        <small class="text-muted">{{ $customer->email ?: 'Chưa có email' }}</small>
                    </div>
                    @if($customer->type === 'VIP')
                        <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1 rounded-pill"><i class="fas fa-crown"></i> VIP</span>
                    @elseif($customer->type === 'Thường')
                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1 rounded-pill"><i class="fas fa-user"></i> Thường</span>
                    @else
                        <span class="badge bg-info-subtle text-info border border-info px-2 py-1 rounded-pill"><i class="fas fa-user-plus"></i> Mới</span>
                    @endif
                </div>
                @empty
                <p class="text-center text-muted">Chưa có khách hàng</p>
                @endforelse
            </div>
            </div>
        </div>

        <!-- Khuyến Mãi Đang Áp Dụng -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Khuyến Mãi Đang Áp Dụng</h5>
            </div>
            <div class="card-body">
                @forelse($activePromotions as $promotion)
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <div class="bg-warning text-white rounded p-2 me-3"><i class="bi bi-percent"></i></div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $promotion->name }}</div>
                        <small class="text-muted">Mã: {{ $promotion->code }} · HSD: {{ $promotion->expires_at?->format('d/m/Y') ?: 'Không thời hạn' }}</small>
                    </div>
                </div>
                @empty
                <p class="text-center text-muted">Chưa có khuyến mãi đang áp dụng</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endif

<!-- Recent Orders & Deliveries Tables (Staff & Admin) -->
<div class="row g-4">
    <!-- Left Column: Orders & Deliveries -->
    <div class="col-lg-8">
        @if(auth()->user()->isAdmin())
        <!-- Đơn Hàng Mới Cần Xử Lý (Admin Only - contains financial data) -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Đơn Hàng Mới Cần Xử Lý</h5>
                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-primary">Xem Tất Cả</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Mã Đơn</th>
                                <th>Khách Hàng</th>
                                <th>Dịch Vụ</th>
                                <th>Tổng Tiền</th>
                                <th>Trạng Thái</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($processingOrders as $order)
                            <tr>
                                <td><strong>{{ $order->code }}</strong></td>
                                <td>{{ $order->customer?->name ?: '-' }}</td>
                                <td>{{ $order->service?->name ?: '-' }}</td>
                                <td><strong>{{ number_format($order->total_amount) }} VNĐ</strong></td>
                                <td>
                                    @if($order->status === 'pending')
                                        <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>Chờ xử lý</span>
                                    @elseif($order->status === 'washing')
                                        <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-washer me-1"></i>Đang giặt</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-2 rounded-pill">{{ $order->status }}</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-light"><i class="bi bi-eye"></i> Xem</a></td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Chưa có đơn hàng cần xử lý</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- Lịch Giao Nhận Trong Ngày (Staff & Admin) -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Lịch Giao Nhận Trong Ngày</h5>
                <a href="{{ route('deliveries.index') }}" class="btn btn-sm btn-outline-primary">Xem Tất Cả</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Mã Lịch</th>
                                <th>Khách Hàng</th>
                                <th>Địa Chỉ Giao Hàng</th>
                                <th>Khung Giờ</th>
                                <th>Loại</th>
                                <th>Trạng Thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($todayDeliveries as $delivery)
                            <tr>
                                <td><strong>#{{ $delivery->id }}</strong></td>
                                <td>{{ $delivery->customer?->name ?: '-' }}</td>
                                <td>{{ $delivery->address ?: 'Chưa có địa chỉ' }}</td>
                                <td>{{ $delivery->pickup_time?->format('H:i') ?: '-' }}</td>
                                <td>
                                    <span class="badge bg-info-subtle text-info border border-info px-2 py-1 rounded-pill">
                                        {{ $delivery->type_label }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $delivery->status_badge_class }} px-2 py-1 rounded-pill">
                                        {{ $delivery->status_label }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Không có lịch giao hôm nay</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Pending Invoices -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-3 h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Hóa Đơn Chờ Thanh Toán</h5>
                <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-outline-primary">Xem Tất Cả</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($pendingInvoices as $invoice)
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <div>
                            <div class="fw-semibold">{{ $invoice->code }}</div>
                            <small class="text-muted">{{ $invoice->order?->customer?->name ?: 'Không có khách hàng' }}</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold text-danger">{{ number_format($invoice->total) }} VNĐ</div>
                            <div class="btn-group btn-group-sm mt-1" role="group">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-light" title="Xem hóa đơn"><i class="bi bi-eye"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-success collect-cash-btn" title="Thu tiền mặt" data-invoice-id="{{ $invoice->id }}" data-invoice-code="{{ $invoice->code }}">
                                    <i class="bi bi-cash-stack"></i>
                                </button>
                            </div>
                        </div>
                    </li>
                    @empty
                    <li class="list-group-item text-center text-muted py-4">
                        Không có hóa đơn chờ thanh toán
                    </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if(auth()->user()->isAdmin())
<script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>

<script>
    // Revenue Chart
    var revenueOptions = {
        series: [{
            name: 'Doanh thu',
            data: @json($monthlyRevenue->map(fn ($value) => round($value / 1000000, 2))->values())
        }],
        chart: {
            type: 'area',
            height: 300,
            fontFamily: 'Plus Jakarta Sans, sans-serif',
            toolbar: {
                show: false
            }
        },
        colors: ['#60A5FA'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.1,
                stops: [0, 90, 100]
            }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth',
            width: 2
        },
        xaxis: {
            categories: ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'],
            labels: {
                style: {
                    colors: '#64748B',
                    fontSize: '12px'
                }
            }
        },
        yaxis: {
            labels: {
                style: {
                    colors: '#64748B',
                    fontSize: '12px'
                },
                formatter: function(value) {
                    return value + 'M';
                }
            }
        },
        tooltip: {
            theme: 'light',
            y: {
                formatter: function(value) {
                    return value + ' triệu VNĐ';
                }
            }
        }
    };
    var revenueChart = new ApexCharts(document.querySelector("#revenue-chart"), revenueOptions);
    revenueChart.render();

    // Status Chart
    var statusOptions = {
        series: @json(array_values($statusCounts)),
        chart: {
            type: 'donut',
            height: 300,
            fontFamily: 'Plus Jakarta Sans, sans-serif'
        },
        colors: ['#F59E0B', '#3B82F6', '#22C55E', '#EF4444'],
        labels: ['Chờ xử lý', 'Đang xử lý', 'Hoàn thành', 'Đã hủy'],
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Tổng',
                            fontSize: '16px',
                            fontWeight: 600,
                            color: '#1E293B'
                        }
                    }
                }
            }
        },
        dataLabels: {
            enabled: false
        },
        legend: {
            position: 'bottom',
            fontSize: '13px',
            markers: {
                width: 10,
                height: 10
            }
        }
    };
    var statusChart = new ApexCharts(document.querySelector("#status-chart"), statusOptions);
    statusChart.render();
</script>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const collectBtns = document.querySelectorAll('.collect-cash-btn');
        collectBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const invoiceId = btn.getAttribute('data-invoice-id');
                const invoiceCode = btn.getAttribute('data-invoice-code');

                Swal.fire({
                    title: 'Xác nhận thu tiền?',
                    html: 'Thu tiền mặt cho hóa đơn <strong>' + invoiceCode + '</strong>?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Xác nhận',
                    cancelButtonText: 'Hủy'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        var baseUrl = '{{ route('dashboard.collect-cash-payment', '__ID__') }}';
                        var url = baseUrl.replace('__ID__', invoiceId);
                        fetch(url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                        })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Thành công!',
                                    text: data.message,
                                    icon: 'success',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                                const invoiceItem = btn.closest('li');
                                if (invoiceItem) {
                                    invoiceItem.remove();
                                }
                                const countEl = document.querySelector('.stat-value');
                                const statCard = document.querySelector('[class*="stat-value"]');
                                const card = btn.closest('.card');
                                if (card) {
                                    let count = card.querySelector('.stat-value');
                                    if (count) {
                                        count.textContent = parseInt(count.textContent) - 1;
                                    }
                                }
                            } else {
                                Swal.fire({
                                    title: 'Lỗi!',
                                    text: data.message,
                                    icon: 'error',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            }
                        })
                        .catch(function() {
                            Swal.fire({
                                title: 'Lỗi!',
                                text: 'Có lỗi xảy ra. Vui lòng thử lại.',
                                icon: 'error',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        });
                    }
                });
            });
        });
    });
</script>
@endpush
