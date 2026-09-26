@extends('layouts.app')

@section('title', 'Dashboard quản lý - Sky Laundry')
@section('page-title', 'Dashboard quản lý')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/libs/apexcharts/apexcharts.css') }}">
<style>
    /* Dashboard-specific stat card enhancements */
    .stat-card .stat-value {
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        color: #111827 !important;
        line-height: 1.2;
    }
    .stat-card .stat-label {
        font-size: 0.875rem !important;
        color: #4b5563 !important;
        font-weight: 500;
    }
    .stat-card .stat-icon {
        width: 50px !important;
        height: 50px !important;
        font-size: 1.25rem !important;
    }
    .stat-card .stat-change {
        font-size: 0.75rem !important;
    }
    
    /* Table enhancements */
    .table-custom thead th {
        background-color: #f9fafb !important;
        font-weight: 600;
    }
    .table-custom tbody td {
        padding: 14px 16px !important;
    }
    
    /* Review items spacing */
    .review-item {
        border-bottom: 1px solid #f3f4f6;
    }
    .review-item:last-child {
        border-bottom: none;
    }
    .review-stars .fa-star {
        color: #f59e0b !important;
    }
</style>
@endpush

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
        'pending'    => 'bg-warning-subtle text-warning-emphasis border border-warning rounded-pill',
        'received'   => 'bg-primary-subtle text-primary-emphasis border border-primary rounded-pill',
        'sorting'    => 'bg-purple-subtle text-purple-emphasis border border-purple rounded-pill',
        'processing' => 'bg-primary-subtle text-primary-emphasis border border-primary-subtle fw-semibold rounded-pill',
        'washed'     => 'bg-teal-subtle text-teal-emphasis border border-teal rounded-pill',
        'delivering' => 'bg-indigo-subtle text-indigo-emphasis border border-indigo rounded-pill',
        'completed'  => 'bg-success-subtle text-success-emphasis border border-success rounded-pill',
        'cancelled'  => 'bg-danger-subtle text-danger-emphasis border border-danger rounded-pill',
    ];
@endphp

<!-- ===== KPI Cards Row (2 rows x 3 cols = 6 cards) ===== -->
<div class="row g-4 mb-4">
    <!-- 1. Doanh thu hôm nay -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card stat-card stat-card--revenue-today h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ number_format($todayRevenue) }} VNĐ</div>
                        <div class="stat-label">Doanh thu hôm nay</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-currency-dollar"></i></div>
                </div>
                <div class="mt-3">
                    @if($todayRevenueChange >= 0)
                        <small class="stat-change text-success"><i class="bi bi-arrow-up"></i> {{ abs($todayRevenueChange) }}% so với hôm qua</small>
                    @else
                        <small class="stat-change text-danger"><i class="bi bi-arrow-down"></i> {{ abs($todayRevenueChange) }}% so với hôm qua</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Doanh thu tuần này -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card stat-card stat-card--revenue-week h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ number_format($weekRevenue) }} VNĐ</div>
                        <div class="stat-label">Doanh thu tuần này</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-calendar-week"></i></div>
                </div>
                <div class="mt-3">
                    @if($weekRevenueChange >= 0)
                        <small class="stat-change text-success"><i class="bi bi-arrow-up"></i> {{ abs($weekRevenueChange) }}% so với tuần trước</small>
                    @else
                        <small class="stat-change text-danger"><i class="bi bi-arrow-down"></i> {{ abs($weekRevenueChange) }}% so với tuần trước</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Doanh thu tháng này -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card stat-card stat-card--revenue-month h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ number_format($monthRevenue) }} VNĐ</div>
                        <div class="stat-label">Doanh thu tháng này</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
                <div class="mt-3">
                    @if($monthRevenueChange >= 0)
                        <small class="stat-change text-success"><i class="bi bi-arrow-up"></i> {{ abs($monthRevenueChange) }}% so với tháng trước</small>
                    @else
                        <small class="stat-change text-danger"><i class="bi bi-arrow-down"></i> {{ abs($monthRevenueChange) }}% so với tháng trước</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Tổng đơn hàng -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card stat-card stat-card--orders h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ $totalOrders }}</div>
                        <div class="stat-label">Tổng đơn hàng</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-receipt"></i></div>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        <span class="badge bg-success-subtle text-success-emphasis border border-success rounded-pill">{{ $statusCounts['completed'] }} Hoàn thành</span>
                        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle fw-semibold rounded-pill">{{ $statusCounts['processing'] }} Đang xử lý</span>
                        <span class="badge bg-danger-subtle text-danger-emphasis border border-danger rounded-pill">{{ $statusCounts['cancelled'] }} Đã hủy</span>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Tổng khách hàng -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card stat-card stat-card--customers h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ number_format($totalCustomers) }}</div>
                        <div class="stat-label">Tổng khách hàng</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                </div>
                <div class="mt-3">
                    <small class="stat-change text-muted"><i class="bi bi-person-plus"></i> {{ number_format($newCustomersMonth) }} khách mới tháng {{ now()->format('m/Y') }}</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Điểm đánh giá trung bình -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card stat-card stat-card--rating h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ number_format($averageRating, 1) }}<small class="fs-6 text-muted">/5.0</small></div>
                        <div class="stat-label">Điểm đánh giá trung bình</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-star-fill"></i></div>
                </div>
                <div class="mt-3">
                    <small class="stat-change text-muted"><i class="bi bi-star"></i> {{ number_format($totalReviews) }} lượt đánh giá</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Charts Row ===== -->
<div class="row g-4 mb-4">
    <!-- Doanh thu theo bộ lọc -->
    <div class="col-12 col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0" id="revenue-chart-title">Doanh thu 7 ngày gần nhất</h5>
                <select class="form-select form-select-sm" id="revenueFilter" style="width: auto;">
                    <option value="today">Hôm nay</option>
                    <option value="7_days" selected>7 ngày gần nhất</option>
                    <option value="this_month">Tháng này</option>
                    <option value="this_year">Năm nay</option>
                </select>
            </div>
            <div class="card-body">
                <div id="revenue-chart" style="height: 300px;"></div>
            </div>
        </div>
    </div>

    <!-- Trạng thái đơn hàng (Donut) -->
    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Tỷ lệ đơn hàng theo trạng thái</h5>
            </div>
            <div class="card-body">
                <div id="status-chart" style="height: 300px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Top Services ===== -->
<div class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Top dịch vụ được đặt nhiều nhất</h5>
                <a href="{{ route('services.index') }}" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
            </div>
            <div class="card-body">
                <div id="top-service-chart" style="height: 320px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ===== Data Tables Row ===== -->
<div class="row g-4">
    <!-- Đơn hàng mới nhất -->
    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Đơn hàng mới nhất</h5>
                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Nhân viên</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                            <tr>
                                <td><strong>{{ $order->code }}</strong></td>
                                <td>{{ $order->customer?->name ?: '-' }}</td>
                                <td>{{ $order->employee?->name ?: '-' }}</td>
                                <td><strong>{{ number_format($order->total_amount) }} VNĐ</strong></td>
                                <td>
                                    @php
                                        $label = $orderStatusLabels[$order->status] ?? 'Chờ tiếp nhận';
                                        $badge = $statusBadgeMap[$order->status] ?? 'bg-secondary-subtle text-secondary border-secondary';
                                    @endphp
                                    <span class="badge {{ $badge }} px-3 py-2 rounded-pill">{{ $label }}</span>
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

    <!-- Đánh giá mới nhất chưa phản hồi -->
    <div class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Đánh giá chưa phản hồi</h5>
                <a href="{{ route('reviews.index') }}" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
            </div>
            <div class="card-body">
                @forelse($latestReviews as $review)
                <div class="review-item d-flex align-items-start mb-3 pb-3">
                    <div class="flex-grow-1">
                        <div class="review-stars d-flex align-items-center gap-1 mb-1">
                            @for ($i = 1; $i <= 5; $i++)
                                <i class="fa-{{ $i <= $review->rating ? 'solid' : 'regular' }} fa-star text-warning"></i>
                            @endfor
                        </div>
                        <div class="fw-semibold">{{ $review->customer?->name ?: 'Khách hàng ẩn danh' }}</div>
                        <small class="text-muted">{{ Str::limit($review->content, 100) }}</small>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">{{ $review->created_at?->format('d/m H:i') }}</small>
                    </div>
                </div>
                @empty
                <p class="text-center text-muted">Chưa có đánh giá nào</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
<script>
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Chart configuration for revenue
    var revenueChartOptions = {
        series: [{
            name: 'Doanh thu',
            data: @json($last7DaysRevenue->pluck('revenue'))
        }],
        chart: {
            type: 'area',
            height: 300,
            fontFamily: 'Plus Jakarta Sans, sans-serif',
            toolbar: { show: false }
        },
        colors: ['#3B82F6'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.05,
                stops: [0, 90, 100]
            }
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        xaxis: {
            categories: @json($last7DaysRevenue->pluck('date')),
            labels: { style: { colors: '#64748B', fontSize: '12px' } }
        },
        yaxis: {
            labels: {
                style: { colors: '#64748B', fontSize: '12px' },
                formatter: function (value) {
                    return numberWithCommas(value) + ' VNĐ';
                }
            }
        },
        tooltip: {
            theme: 'light',
            y: {
                formatter: function (value) {
                    return numberWithCommas(value) + ' VNĐ';
                }
            }
        }
    };

    var revenueChart = new ApexCharts(document.querySelector("#revenue-chart"), revenueChartOptions);
    revenueChart.render();

    var statusOptions = {
        series: @json($statusDistribution->values()->all()),
        chart: {
            type: 'donut',
            height: 300,
            fontFamily: 'Plus Jakarta Sans, sans-serif'
        },
        colors: ['#F59E0B', '#3B82F6', '#22C55E', '#EF4444', '#8B5CF6', '#10B981'],
        labels: @json(collect($statusDistribution->keys()->all())->map(fn($k) => $orderStatusLabels[$k] ?? $k)->all()),
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Tổng',
                            fontSize: '18px',
                            fontWeight: 700,
                            color: '#111827'
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        legend: {
            position: 'bottom',
            fontSize: '13px',
            fontWeight: 500,
            markers: { width: 10, height: 10 }
        }
    };

    var topServiceOptions = {
        series: [{
            name: 'Số đơn',
            data: @json($topServices->pluck('orders_count')->values())
        }],
        chart: {
            type: 'bar',
            height: 340,
            fontFamily: 'Plus Jakarta Sans, sans-serif',
            toolbar: { show: false }
        },
        colors: ['#1D4ED8', '#2563EB', '#3B82F6', '#60A5FA', '#93C5FD'],
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 6,
                barHeight: '60%',
                distributed: true,
                dataLabels: {
                    position: 'end'
                }
            }
        },
        dataLabels: {
            enabled: false
        },
        xaxis: {
            categories: @json($topServices->pluck('name')),
            labels: { style: { colors: '#64748B', fontSize: '12px' } },
            max: function(max) {
                return Math.max(5, max + Math.max(2, Math.ceil(max * 0.4)));
            }
        },
        yaxis: {
            labels: {
                style: { colors: '#334155', fontSize: '13px', fontWeight: 600 },
                maxWidth: 320
            }
        },
        legend: { show: false },
        tooltip: {
            theme: 'light',
            y: {
                formatter: function (val) { return val + ' đơn'; }
            }
        }
    };

    var statusChart = new ApexCharts(document.querySelector("#status-chart"), statusOptions);
    statusChart.render();

    var topServiceChart = new ApexCharts(document.querySelector("#top-service-chart"), topServiceOptions);
    topServiceChart.render();

    // AJAX handler for revenue filter
    document.getElementById('revenueFilter').addEventListener('change', function () {
        var filter = this.value;
        var titleEl = document.getElementById('revenue-chart-title');

        fetch('{{ route("admin.dashboard.revenue-chart") }}?filter=' + filter)
            .then(response => response.json())
            .then(data => {
                // Update chart title
                if (titleEl && data.filter_label) {
                    titleEl.textContent = data.filter_label;
                }
                // Update chart data
                revenueChart.updateOptions({
                    series: [{
                        name: 'Doanh thu',
                        data: data.data
                    }],
                    xaxis: {
                        categories: data.labels
                    }
                });
            })
            .catch(error => {
                console.error('Error fetching revenue data:', error);
            });
    });
</script>
@endpush

