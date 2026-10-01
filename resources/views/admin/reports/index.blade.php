@extends('layouts.app')

@section('title', 'Báo cáo & Thống kê kinh doanh - Sky Laundry')
@section('page-title', 'Báo cáo & Thống kê kinh doanh')

@push('styles')
<style>
    .kpi-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.1) !important;
    }
    .chart-container {
        position: relative;
        height: 320px;
        width: 100%;
        min-height: 320px;
    }
    .chart-container > div:first-child {
        width: 100%;
        height: 100%;
    }
    .report-chart-empty {
        height: 100%;
        display: grid;
        place-items: center;
        color: #64748b;
        font-size: 0.875rem;
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-label {
        font-size: 0.8125rem;
        font-weight: 500;
    }
    .badge-breakdown {
        font-size: 0.6875rem;
        padding: 0.25rem 0.5rem;
        white-space: nowrap;
    }
    /* Reports table overrides - must use !important to beat global laundry.css */
    .reports-table thead th {
        font-size: 0.6875rem !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.04em !important;
        color: #000000 !important;
        background-color: #f8fafc !important;
        border-bottom: 2px solid #e2e8f0 !important;
        padding: 0.75rem 1rem !important;
    }
    .reports-table tbody td {
        padding: 0.625rem 1rem !important;
        vertical-align: middle !important;
        font-size: 0.875rem !important;
        font-weight: 600 !important;
        color: #1e293b !important;
        border-color: #f1f5f9 !important;
    }
    .reports-table tbody td.fw-semibold,
    .reports-table tbody td.fw-bold,
    .reports-table tbody td.text-dark,
    .reports-table tbody td.text-primary,
    .reports-table tbody td.text-success,
    .reports-table tbody td.text-warning,
    .reports-table tbody td.text-danger,
    .reports-table tbody td.text-info {
        color: inherit !important;
        font-weight: inherit !important;
    }
    .reports-table tbody tr:hover {
        background-color: #f8fafc !important;
    }
    .reports-table .badge {
        font-weight: 600 !important;
    }
    .reports-table .bg-primary-subtle { background-color: #dbeafe !important; }
    .reports-table .bg-success-subtle { background-color: #dcfce7 !important; }
    .reports-table .bg-warning-subtle { background-color: #fef3c7 !important; }
    .reports-table .bg-danger-subtle { background-color: #fee2e2 !important; }
    .reports-table .bg-info-subtle { background-color: #e0f2fe !important; }
    .reports-table .bg-teal-subtle { background-color: #ccfbf1 !important; }
    .reports-table .bg-indigo-subtle { background-color: #e0e7ff !important; }
    .reports-table .bg-pink-subtle { background-color: #fce7f3 !important; }
    .reports-table .text-primary { color: #2563eb !important; }
    .reports-table .text-success { color: #16a34a !important; }
    .reports-table .text-warning { color: #d97706 !important; }
    .reports-table .text-danger { color: #dc2626 !important; }
    .reports-table .text-info { color: #0891b2 !important; }
    .reports-table .text-teal { color: #0d9488 !important; }
    .reports-table .text-indigo { color: #4f46e5 !important; }
    .reports-table .text-pink { color: #db2777 !important; }
    .reports-table .border-primary { border-color: #3b82f6 !important; }
    .reports-table .border-success { border-color: #22c55e !important; }
    .reports-table .border-warning { border-color: #f59e0b !important; }
    .reports-table .border-danger { border-color: #ef4444 !important; }
    .reports-table .border-info { border-color: #06b6d4 !important; }
    .reports-table a:not(.badge):not(.btn) {
        color: inherit !important;
        font-weight: inherit !important;
        text-decoration: none !important;
    }
    .card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    }
    .card-header {
        background: #fff;
        border-bottom: 1px solid #eef2f7;
        border-radius: 12px 12px 0 0 !important;
        padding: 1rem 1.25rem;
        font-weight: 700;
        font-size: 0.9375rem;
        color: #0f172a;
    }
    .card-body {
        padding: 1.25rem;
    }
    .form-select-sm, .form-control-sm {
        font-size: 0.8125rem;
        padding: 0.375rem 0.75rem;
        border-radius: 8px;
        border-color: #d1d5db;
    }
    .form-select-sm:focus, .form-control-sm:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }
    /* Pagination fix for Bootstrap 5 */
    .pagination .page-link {
        color: #3b82f6;
        background-color: #fff;
        border: 1px solid #dee2e6;
        padding: 0.375rem 0.75rem;
        font-size: 0.8125rem;
    }
    .pagination .page-link:hover {
        color: #2563eb;
        background-color: #eff6ff;
        border-color: #3b82f6;
    }
    .pagination .page-item.active .page-link {
        background-color: #3b82f6;
        border-color: #3b82f6;
        color: #fff;
    }
    .pagination .page-item.disabled .page-link {
        color: #9ca3af;
        background-color: #f9fafb;
        border-color: #e5e7eb;
    }
    .pagination .page-item:first-child .page-link {
        border-radius: 6px 0 0 6px;
    }
    .pagination .page-item:last-child .page-link {
        border-radius: 0 6px 6px 0;
    }
    /* Filter bar responsive */
    @media (max-width: 767.98px) {
        .filter-form {
            flex-direction: column;
            align-items: stretch !important;
        }
        .filter-form > div {
            width: 100% !important;
            min-width: 0 !important;
        }
        .filter-form .btn {
            width: 100%;
            justify-content: center;
        }
    }
    /* Ensure table-responsive works properly */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table-responsive > .table {
        width: 100%;
        margin-bottom: 0;
    }
</style>
@endpush

@section('content')
<!-- Page Header + Global Filter Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h4 fw-bold text-dark mb-0">Báo cáo & Thống kê kinh doanh</h1>
        <p class="text-muted mb-0 small">Tổng quan hiệu suất kinh doanh cửa hàng giặt ủi</p>
    </div>
    <form action="{{ route('reports.index') }}" method="GET" class="d-flex flex-wrap gap-2 align-items-end filter-form" style="max-width: 900px; width: 100%;">
        <div class="flex-grow-1" style="min-width: 180px;">
            <label class="form-label mb-1 small fw-medium">Khoảng thời gian</label>
            <select name="range" class="form-select form-select-sm" id="rangeSelect" onchange="toggleCustomDate(this)">
                <option value="all_time" {{ $filters['range'] === 'all_time' ? 'selected' : '' }}>Toàn thời gian</option>
                <option value="today" {{ $filters['range'] === 'today' ? 'selected' : '' }}>Hôm nay</option>
                <option value="7_days" {{ $filters['range'] === '7_days' ? 'selected' : '' }}>7 ngày qua</option>
                <option value="this_month" {{ $filters['range'] === 'this_month' ? 'selected' : '' }}>Tháng này</option>
                <option value="last_month" {{ $filters['range'] === 'last_month' ? 'selected' : '' }}>Tháng trước</option>
                <option value="custom" {{ $filters['range'] === 'custom' ? 'selected' : '' }}>Tuỳ chỉnh</option>
            </select>
        </div>
        <div id="customFromGroup" class="{{ $filters['range'] === 'custom' ? '' : 'd-none' }}" style="min-width: 140px;">
            <label class="form-label mb-1 small fw-medium">Từ ngày</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div id="customToGroup" class="{{ $filters['range'] === 'custom' ? '' : 'd-none' }}" style="min-width: 140px;">
            <label class="form-label mb-1 small fw-medium">Đến ngày</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-filter me-1"></i>Lọc dữ liệu
        </button>
        <button type="submit" formaction="{{ route('reports.export') }}" formmethod="GET" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Xuất Excel
        </button>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-clockwise me-1"></i>Đặt lại
        </a>
    </form>
</div>

<!-- KPI Metric Cards -->
<div class="row g-3 mb-4">
    <!-- Card 1: Tổng doanh thu -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center p-3">
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="ms-3">
                    <div class="stat-value text-dark">{{ number_format($kpi['total_revenue'], 0, ',', '.') }} VNĐ</div>
                    <div class="stat-label text-muted">Tổng doanh thu</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Tổng đơn hàng -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-start p-3">
                <div class="stat-icon bg-success-subtle text-success flex-shrink-0">
                    <i class="bi bi-receipt"></i>
                </div>
                <div class="ms-3 flex-grow-1 min-w-0">
                    <div class="stat-value text-dark">{{ number_format($kpi['total_orders'], 0, ',', '.') }}</div>
                    <div class="stat-label d-flex flex-wrap gap-1 align-items-center mt-1">
                        <span class="text-muted">Tổng đơn hàng</span>
                        <span class="badge bg-success-subtle text-success-emphasis badge-breakdown">{{ $kpi['completed_orders'] }} HT</span>
                        <span class="badge bg-warning-subtle text-warning-emphasis badge-breakdown">{{ $kpi['processing_orders'] }} ĐXL</span>
                        <span class="badge bg-danger-subtle text-danger-emphasis badge-breakdown">{{ $kpi['cancelled_orders'] }} Hủy</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Đơn đặt lịch mới -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center p-3">
                <div class="stat-icon bg-info-subtle text-info">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="ms-3">
                    <div class="stat-value text-dark">{{ number_format($kpi['new_bookings'], 0, ',', '.') }}</div>
                    <div class="stat-label text-muted">Đơn đặt lịch mới</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Giá trị đơn TB (AOV) -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card h-100 shadow-sm border-0">
            <div class="card-body d-flex align-items-center p-3">
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="ms-3">
                    <div class="stat-value text-dark">{{ number_format($kpi['avg_order_value'], 0, ',', '.') }} VNĐ</div>
                    <div class="stat-label text-muted">Giá trị đơn TB (AOV)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row g-3 mb-4">
    <!-- Revenue Chart - col-lg-7 -->
    <div class="col-12 col-lg-7">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Xu hướng doanh thu</span>
                <select id="chartType" class="form-select form-select-sm" style="width: auto; min-width: 100px;">
                    <option value="bar">Cột</option>
                    <option value="line">Đường</option>
                </select>
            </div>
            <div class="card-body p-3">
                <div class="chart-container" style="position: relative; height: 320px; width: 100%;">
                    @if(array_sum($chartData['revenue'] ?? []) > 0 || array_sum($chartData['orders'] ?? []) > 0)
                    <div id="revenueChart"></div>
                    @else
                    <div class="report-chart-empty">Chưa có doanh thu trong khoảng thời gian này</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Service Composition Chart - col-lg-5 -->
    <div class="col-12 col-lg-5">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header">
                <span>Cơ cấu dịch vụ</span>
            </div>
            <div class="card-body p-3">
                <div class="chart-container" style="position: relative; height: 320px; width: 100%;">
                    @if(array_sum($compositionData['revenue'] ?? []) > 0)
                    <div id="compositionChart"></div>
                    @else
                    <div class="report-chart-empty">Chưa có dữ liệu dịch vụ</div>
                    @endif
                </div>
                @if(!empty($compositionData['labels']))
                <div class="mt-3 pt-3 border-top w-100">
                    @foreach($compositionData['labels'] as $index => $label)
                    <div class="d-flex align-items-center mb-2">
                        <span class="me-2 rounded-circle" style="width: 10px; height: 10px; background-color: {{ $chartColors[$index] ?? '#ccc' }};"></span>
                        <span class="small fw-semibold flex-grow-1">{{ $label }}</span>
                        <span class="ms-2 small text-muted">{{ number_format($compositionData['percentages'][$index] ?? 0, 1, ',', '.') }}%</span>
                        <span class="ms-auto small text-muted">{{ number_format($compositionData['revenue'][$index] ?? 0, 0, ',', '.') }} VNĐ</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Data Tables Section -->
<div class="row g-3">
    <!-- Top 5 Services -->
    <div class="col-12 col-xl-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header">
                <span>Top 5 dịch vụ bán chạy</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 reports-table">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-bold text-dark">Dịch vụ</th>
                                <th class="fw-bold text-dark text-center" style="width: 80px;">SL bán</th>
                                <th class="fw-bold text-dark text-end" style="width: 150px;">Doanh thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topServices as $service)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $service->name }}</span>
                                    <small class="text-muted d-block">({{ $service->unit ?: 'ĐVT' }})</small>
                                </td>
                                <td class="text-center">{{ number_format($service->total_qty, 2, ',', '.') }}</td>
                                <td class="text-end fw-semibold text-primary">{{ number_format($service->total_revenue, 0, ',', '.') }} VNĐ</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Chưa có dữ liệu</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue by Payment Method -->
    <div class="col-12 col-xl-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header">
                <span>Doanh thu theo phương thức thanh toán</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 reports-table">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-bold text-dark">Phương thức</th>
                                <th class="fw-bold text-dark text-center" style="width: 80px;">GD</th>
                                <th class="fw-bold text-dark text-end" style="width: 150px;">Tổng tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($paymentMethods as $method)
                            <tr>
                                <td>
                                    <span class="badge {{ $paymentMethodColors[$method->method] ?? 'bg-light text-dark border' }} px-3 py-2 rounded-pill">
                                        {{ $paymentMethodLabels[$method->method] ?? $method->method }}
                                    </span>
                                </td>
                                <td class="text-center">{{ $method->transaction_count }}</td>
                                <td class="text-end fw-semibold text-primary">{{ number_format($method->total_amount, 0, ',', '.') }} VNĐ</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Chưa có dữ liệu</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders Table -->
<div class="row g-3 mt-3">
    <div class="col-12">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="card-title fw-bold m-0 text-dark">Đơn hàng gần đây</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-nowrap mb-0">
                        <thead class="bg-light-subtle text-secondary fs-7 text-uppercase fw-semibold">
                            <tr>
                                <th scope="col" style="width: 110px;" class="ps-3">Mã đơn</th>
                                <th scope="col" style="width: 160px;">Khách hàng</th>
                                <th scope="col">Dịch vụ</th>
                                <th scope="col" style="width: 130px;" class="text-center">Trạng thái</th>
                                <th scope="col" style="width: 140px;" class="text-end">Tổng tiền</th>
                                <th scope="col" style="width: 140px;" class="text-center">Ngày tạo</th>
                                <th scope="col" style="width: 80px;" class="text-center pe-3">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="fs-7">
                            @forelse($recentOrders as $order)
                            @php($createdAt = $order->created_at?->copy()->setTimezone('Asia/Ho_Chi_Minh'))
                            <tr>
                                <td class="ps-3 fw-bold text-primary">{{ $order->code }}</td>
                                <td class="fw-medium text-dark">{{ $order->customer?->name ?: '-' }}</td>
                                <td class="text-secondary text-truncate" style="max-width: 250px;">{{ $order->items->pluck('service.name')->filter()->join(', ') ?: ($order->service?->name ?: '-') }}</td>
                                <td class="text-center">
                                    <x-admin.status-badge :status="$order->status" :enum="\App\Enums\OrderStatus::class" />
                                </td>
                                <td class="text-end fw-bold text-dark">{{ number_format($order->total_amount, 0, ',', '.') }} VNĐ</td>
                                <td class="text-center">
                                    <div class="text-dark fw-medium">{{ $createdAt?->format('d/m/Y') ?? '-' }}</div>
                                    <small class="text-muted fs-8">{{ $createdAt?->format('H:i') ?? '' }}</small>
                                </td>
                                <td class="text-center pe-3">
                                    <a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-light btn-active-light-primary btn-icon rounded-circle" title="Xem chi tiết">
                                        <i class="bi bi-eye text-secondary"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Chưa có đơn hàng nào</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($recentOrders->hasPages())
                <div class="card-footer bg-white border-top border-light p-3">
                    {{ $recentOrders->appends(request()->query())->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
<script>
    (function () {
        const formatVnd = value => `${new Intl.NumberFormat('vi-VN').format(value || 0)} VNĐ`;
        const revenueLabels = @json($chartData['labels'] ?? []);
        const revenueValues = @json($chartData['revenue'] ?? []);
        const orderValues = @json($chartData['orders'] ?? []);
        const compositionLabels = @json($compositionData['labels'] ?? []);
        const compositionValues = @json($compositionData['revenue'] ?? []);
        const colors = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1'];

        window.toggleCustomDate = function (select) {
            document.getElementById('customFromGroup')?.classList.toggle('d-none', select.value !== 'custom');
            document.getElementById('customToGroup')?.classList.toggle('d-none', select.value !== 'custom');
        };

        if (typeof window.ApexCharts === 'undefined') {
            console.error('ApexCharts did not load; report charts cannot be rendered.');
            return;
        }

        let revenueChart = null;
        let compositionChart = null;

        function renderRevenueChart() {
            const element = document.getElementById('revenueChart');
            if (!element) {
                return;
            }

            revenueChart?.destroy();
            const displayType = document.getElementById('chartType')?.value === 'line' ? 'line' : 'column';
            revenueChart = new ApexCharts(element, {
                series: [
                    { name: 'Doanh thu', type: displayType, data: revenueValues.map(value => Number(value) || 0) },
                    { name: 'Số đơn', type: 'line', data: orderValues.map(value => Number(value) || 0) },
                ],
                chart: {
                    type: 'line',
                    height: 320,
                    fontFamily: 'Plus Jakarta Sans, sans-serif',
                    toolbar: { show: false },
                    animations: { enabled: false },
                    zoom: { enabled: false },
                },
                colors: ['#3b82f6', '#22c55e'],
                stroke: { curve: 'smooth', width: displayType === 'column' ? [0, 3] : [3, 3] },
                fill: { opacity: [0.85, 1] },
                plotOptions: { bar: { columnWidth: '48%', borderRadius: 3 } },
                dataLabels: { enabled: false },
                markers: { size: [0, 3], hover: { size: 5 } },
                xaxis: {
                    categories: revenueLabels,
                    tickAmount: 12,
                    labels: { rotate: 0, hideOverlappingLabels: true },
                },
                yaxis: [
                    {
                        seriesName: 'Doanh thu',
                        min: 0,
                        title: { text: 'Doanh thu' },
                        labels: { formatter: value => new Intl.NumberFormat('vi-VN', { notation: 'compact' }).format(value) + ' đ' },
                    },
                    {
                        seriesName: 'Số đơn',
                        opposite: true,
                        min: 0,
                        forceNiceScale: true,
                        title: { text: 'Số đơn' },
                        labels: { formatter: value => Math.round(value) + ' đơn' },
                    },
                ],
                grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                legend: { position: 'top', horizontalAlign: 'left' },
                tooltip: {
                    shared: true,
                    y: {
                        formatter: (value, { seriesIndex }) => seriesIndex === 0 ? formatVnd(value) : `${value} đơn`,
                    },
                },
                noData: { text: 'Chưa có dữ liệu trong khoảng thời gian này' },
            });
            revenueChart.render();
        }

        function renderCompositionChart() {
            const element = document.getElementById('compositionChart');
            if (!element) {
                return;
            }

            compositionChart?.destroy();
            compositionChart = new ApexCharts(element, {
                series: compositionValues.map(value => Number(value) || 0),
                labels: compositionLabels,
                chart: {
                    type: 'donut',
                    height: 320,
                    fontFamily: 'Plus Jakarta Sans, sans-serif',
                    toolbar: { show: false },
                    animations: { enabled: false },
                },
                colors,
                stroke: { colors: ['#fff'], width: 2 },
                dataLabels: {
                    enabled: true,
                    formatter: (percentage, { seriesIndex }) => `${percentage.toFixed(1)}%`,
                },
                legend: { show: false },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '62%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Doanh thu dịch vụ',
                                    formatter: context => formatVnd(context.globals.seriesTotals.reduce((total, value) => total + value, 0)),
                                },
                            },
                        },
                    },
                },
                tooltip: {
                    y: {
                        formatter: value => formatVnd(value),
                    },
                },
                noData: { text: 'Chưa có dữ liệu dịch vụ trong khoảng thời gian này' },
            });
            compositionChart.render();
        }

        document.getElementById('chartType')?.addEventListener('change', renderRevenueChart);
        renderRevenueChart();
        renderCompositionChart();
    })();
</script>
@endpush