@extends('layouts.app')

@section('title', 'Dashboard quản lý - Sky Laundry')
@section('page-title', 'Dashboard quản lý')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/libs/apexcharts/apexcharts.css') }}">
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

<!-- ===== KPI Cards Row ===== -->
<div class="row g-4 mb-4">
    <!-- Doanh thu hôm nay -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-dark">{{ number_format($todayRevenue) }} VNĐ</div>
                        <div class="stat-label text-dark">Doanh thu hôm nay</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-currency-dollar"></i></div>
                </div>
                <div class="mt-3">
                    @if($todayRevenueChange >= 0)
                        <small class="text-success"><i class="bi bi-arrow-up"></i> {{ abs($todayRevenueChange) }}% so với hôm qua</small>
                    @else
                        <small class="text-danger"><i class="bi bi-arrow-down"></i> {{ abs($todayRevenueChange) }}% so với hôm qua</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Doanh thu tuần này -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-dark">{{ number_format($weekRevenue) }} VNĐ</div>
                        <div class="stat-label text-dark">Doanh thu tuần này</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-calendar-week"></i></div>
                </div>
                <div class="mt-3">
                    @if($weekRevenueChange >= 0)
                        <small class="text-success"><i class="bi bi-arrow-up"></i> {{ abs($weekRevenueChange) }}% so với tuần trước</small>
                    @else
                        <small class="text-danger"><i class="bi bi-arrow-down"></i> {{ abs($weekRevenueChange) }}% so với tuần trước</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Doanh thu tháng này -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-dark">{{ number_format($monthRevenue) }} VNĐ</div>
                        <div class="stat-label text-dark">Doanh thu tháng này</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
                <div class="mt-3">
                    @if($monthRevenueChange >= 0)
                        <small class="text-success"><i class="bi bi-arrow-up"></i> {{ abs($monthRevenueChange) }}% so với tháng trước</small>
                    @else
                        <small class="text-danger"><i class="bi bi-arrow-down"></i> {{ abs($monthRevenueChange) }}% so với tháng trước</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Đánh giá trung bình -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-dark">{{ number_format($averageRating, 1) }}<small class="fs-6 text-muted">/5.0</small></div>
                        <div class="stat-label text-dark">Điểm đánh giá trung bình</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-star-fill"></i></div>
                </div>
                <div class="mt-3">
                    <small class="text-muted"><i class="bi bi-star"></i> {{ number_format($totalReviews) }} lượt đánh giá</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Đơn hàng & Khách hàng -->
<div class="row g-4 mb-4">
    <!-- Tổng số đơn hàng -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-dark">{{ $totalOrders }}</div>
                        <div class="stat-label text-dark">Tổng đơn hàng</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-receipt"></i></div>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        <span class="badge bg-success-subtle text-success border border-success rounded-pill">{{ $statusCounts['completed'] }} Hoàn thành</span>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning rounded-pill">{{ $statusCounts['processing'] }} Đang xử lý</span>
                        <span class="badge bg-danger-subtle text-danger border border-danger rounded-pill">{{ $statusCounts['cancelled'] }} Đã hủy</span>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tổng khách hàng -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value text-dark">{{ number_format($totalCustomers) }}</div>
                        <div class="stat-label text-dark">Tổng khách hàng</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-people"></i></div>
                </div>
                <div class="mt-3">
                    <small class="text-muted"><i class="bi bi-person-plus"></i> {{ number_format($newCustomersMonth) }} khách mới tháng {{ now()->format('m/Y') }}</small>
                </div>
            </div>
    </div>
</div>

<!-- ===== Charts Row ===== -->
<div class="row g-4 mb-4">
    <!-- Doanh thu theo 7 ngày gần nhất -->
    <div class="col-12 col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Doanh thu 7 ngày gần nhất</h5>
                <select class="form-select form-select-sm" id="revenue-period" style="width: auto;">
                    <option value="7day">7 ngày gần nhất</option>
                    <option value="12month">12 tháng</option>
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
                                <th>Mã Đơn</th>
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
                <div class="d-flex align-items-start mb-3 pb-3 border-bottom">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-1 mb-1">
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
    var revenue7day = {
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
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
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

    var revenue12month = {
        series: [{
            name: 'Doanh thu',
            data: @json($last12Months->pluck('revenue'))
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
                opacityTo: 0.1,
                stops: [0, 90, 100]
            }
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        xaxis: {
            categories: @json($last12Months->pluck('label')),
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
        dataLabels: { enabled: false },
        legend: {
            position: 'bottom',
            fontSize: '13px',
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
            height: 320,
            fontFamily: 'Plus Jakarta Sans, sans-serif',
            toolbar: { show: false }
        },
        colors: ['#22C55E'],
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 6,
                barHeight: '55%'
            }
        },
        dataLabels: { enabled: true },
        xaxis: {
            categories: @json($topServices->pluck('name')),
            labels: { style: { colors: '#64748B', fontSize: '12px' } }
        },
        yaxis: {
            labels: {
                style: { colors: '#64748B', fontSize: '12px' },
                maxWidth: 180
            }
        },
        legend: { show: false },
        tooltip: { theme: 'light' }
    };

    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    var revenueChart = new ApexCharts(document.querySelector("#revenue-chart"), revenue7day);
    revenueChart.render();

    var statusChart = new ApexCharts(document.querySelector("#status-chart"), statusOptions);
    statusChart.render();

    var topServiceChart = new ApexCharts(document.querySelector("#top-service-chart"), topServiceOptions);
    topServiceChart.render();

    document.getElementById('revenue-period').addEventListener('change', function () {
        var chart;
        if (this.value === '12month') {
            chart = new ApexCharts(document.querySelector("#revenue-chart"), revenue12month);
        } else {
            chart = new ApexCharts(document.querySelector("#revenue-chart"), revenue7day);
        }
        revenueChart.dispose();
        revenueChart = chart;
        revenueChart.render();
    });
</script>
@endpush

