@extends('layouts.app')

@section('title', 'Báo cáo & Thống kê kinh doanh - Sky Laundry')
@section('page-title', 'Báo cáo & Thống kê kinh doanh')

@section('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
    .chart-container canvas {
        display: block;
        width: 100% !important;
        height: 100% !important;
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
@endsection

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
                <option value="today" {{ $filters['range'] === 'today' ? 'selected' : '' }}>Hôm nay</option>
                <option value="7_days" {{ $filters['range'] === '7_days' ? 'selected' : '' }}>7 ngày qua</option>
                <option value="this_month" {{ $filters['range'] === 'this_month' ? 'selected' : '' }}>Tháng này</option>
                <option value="last_month" {{ $filters['range'] === 'last_month' ? 'selected' : '' }}>Tháng trước</option>
                <option value="custom" {{ $filters['range'] === 'custom' ? 'selected' : '' }}>Tuỳ chỉnh</option>
            </select>
        </div>
        <div id="customFromGroup" class="{{ $filters['range'] === 'custom' ? '' : 'd-none' }}" style="min-width: 140px;">
            <label class="form-label mb-1 small fw-medium">Từ ngày</label>
            <input type="date" name="date_from" class="form-control form-control-sm flatpickr-date" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div id="customToGroup" class="{{ $filters['range'] === 'custom' ? '' : 'd-none' }}" style="min-width: 140px;">
            <label class="form-label mb-1 small fw-medium">Đến ngày</label>
            <input type="date" name="date_to" class="form-control form-control-sm flatpickr-date" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-filter me-1"></i>Lọc dữ liệu
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportToExcel()">
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
                    <div class="stat-value text-dark">{{ number_format($kpi['total_revenue']) }} VNĐ</div>
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
                    <div class="stat-value text-dark">{{ number_format($kpi['total_orders']) }}</div>
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
                    <div class="stat-value text-dark">{{ number_format($kpi['new_bookings']) }}</div>
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
                    <div class="stat-value text-dark">{{ number_format($kpi['avg_order_value']) }} VNĐ</div>
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
                <span>Xu hướng Doanh thu</span>
                <select id="chartType" class="form-select form-select-sm" style="width: auto; min-width: 100px;" onchange="renderRevenueChart()">
                    <option value="bar">Cột</option>
                    <option value="line">Đường</option>
                </select>
            </div>
            <div class="card-body p-3">
                <div class="chart-container">
                    <canvas id="revenueChart" width="800" height="320"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Composition Chart - col-lg-5 -->
    <div class="col-12 col-lg-5">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header">
                <span>Cơ cấu Dịch vụ</span>
            </div>
            <div class="card-body p-3 d-flex flex-column">
                <div class="chart-container flex-grow-1 d-flex align-items-center justify-content-center">
                    <canvas id="compositionChart" width="400" height="320"></canvas>
                </div>
                <div class="mt-3 pt-3 border-top w-100">
                    @foreach($compositionData['labels'] as $index => $label)
                    <div class="d-flex align-items-center mb-2">
                        <span class="me-2 rounded-circle" style="width: 10px; height: 10px; background-color: {{ $chartColors[$index] ?? '#ccc' }};"></span>
                        <span class="small fw-semibold flex-grow-1">{{ $label }}</span>
                        <span class="ms-auto small text-muted">{{ number_format($compositionData['revenue'][$index] ?? 0) }} VNĐ</span>
                    </div>
                    @endforeach
                </div>
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
                <span>Top 5 Dịch vụ bán chạy</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 reports-table">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-bold text-dark" style="width: 40px;">#</th>
                                <th class="fw-bold text-dark">Dịch vụ</th>
                                <th class="fw-bold text-dark text-center" style="width: 80px;">SL bán</th>
                                <th class="fw-bold text-dark text-end" style="width: 150px;">Doanh thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topServices as $index => $service)
                            <tr>
                                <td class="fw-semibold text-primary">{{ $index + 1 }}</td>
                                <td>
                                    <span class="fw-semibold">{{ $service->name }}</span>
                                    <small class="text-muted d-block">({{ $service->unit ?: 'kg' }})</small>
                                </td>
                                <td class="text-center">{{ number_format($service->total_qty) }}</td>
                                <td class="text-end fw-semibold text-primary">{{ number_format($service->total_revenue) }} VNĐ</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Chưa có dữ liệu</td>
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
                <span>Doanh thu theo Phương thức TT</span>
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
                                        {{ $paymentMethodLabels[$method->method] ?? ucfirst(str_replace('_', ' ', $method->method)) }}
                                    </span>
                                </td>
                                <td class="text-center">{{ $method->transaction_count }}</td>
                                <td class="text-end fw-semibold text-primary">{{ number_format($method->total_amount) }} VNĐ</td>
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
        <div class="card shadow-sm border-0">
            <div class="card-header">
                <span>Đơn hàng gần đây</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 reports-table">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-bold text-dark" style="width: 100px;">Mã đơn</th>
                                <th class="fw-bold text-dark">Khách hàng</th>
                                <th class="fw-bold text-dark">Dịch vụ</th>
                                <th class="fw-bold text-dark" style="width: 130px;">Trạng thái</th>
                                <th class="fw-bold text-dark text-end" style="width: 140px;">Tổng tiền</th>
                                <th class="fw-bold text-dark" style="width: 140px;">Ngày tạo</th>
                                <th class="fw-bold text-dark" style="width: 50px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                            <tr>
                                <td class="fw-semibold">{{ $order->code }}</td>
                                <td>{{ $order->customer?->name ?: '-' }}</td>
                                <td>{{ $order->items->pluck('service.name')->filter()->join(', ') ?: ($order->service?->name ?: '-') }}</td>
                                <td>
                                    <x-admin.status-badge :status="$order->status" :enum="\App\Enums\OrderStatus::class" />
                                </td>
                                <td class="text-end fw-semibold">{{ number_format($order->total_amount) }} VNĐ</td>
                                <td>{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-center">
                                    <a href="{{ route('orders.show', $order) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
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

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    // Chart.js default config
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.color = '#64748B';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.padding = 16;

    // Chart colors
    const chartColors = [
        '#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6',
        '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1'
    ];

    // Revenue Chart Data
    const revenueLabels = @json($chartData['labels']);
    const revenueData = @json($chartData['revenue']);
    const orderData = @json($chartData['orders']);

    let revenueChart = null;

    function renderRevenueChart() {
        const type = document.getElementById('chartType').value;
        const canvas = document.getElementById('revenueChart');
        const ctx = canvas.getContext('2d');

        // Ensure canvas has proper dimensions
        const container = canvas.parentElement;
        canvas.width = container.clientWidth;
        canvas.height = 320;

        if (revenueChart) {
            revenueChart.destroy();
        }

        const isBar = type === 'bar';

        revenueChart = new Chart(ctx, {
            type: isBar ? 'bar' : 'line',
            data: {
                labels: revenueLabels,
                datasets: [
                    {
                        label: 'Doanh thu (VNĐ)',
                        data: revenueData,
                        borderColor: '#3b82f6',
                        backgroundColor: isBar ? 'rgba(59, 130, 246, 0.15)' : 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: isBar ? 0 : 0.3,
                        yAxisID: 'y',
                        borderDash: isBar ? [] : [2, 2],
                    },
                    {
                        label: 'Số đơn',
                        data: orderData,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.3,
                        yAxisID: 'y1',
                        type: 'line',
                        borderDash: [2, 2],
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 16,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleFont: { size: 12 },
                        bodyFont: { size: 11 },
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.yAxisID === 'y') {
                                    return context.dataset.label + ': ' + new Intl.NumberFormat('vi-VN').format(context.raw) + ' VNĐ';
                                }
                                return context.dataset.label + ': ' + context.raw + ' đơn';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        ticks: {
                            font: { size: 10 },
                            callback: function(value) {
                                return new Intl.NumberFormat('vi-VN', { notation: 'compact', compactDisplay: 'short' }).format(value) + 'đ';
                            }
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.15)',
                            borderDash: [2, 2],
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            font: { size: 10 },
                            stepSize: 1,
                            callback: function(value) {
                                return value + ' đơn';
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            font: { size: 10 },
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12
                        }
                    }
                }
            }
        });
    }

    // Composition Chart (Doughnut)
    const compositionLabels = @json($compositionData['labels']);
    const compositionRevenue = @json($compositionData['revenue']);
    const compositionCounts = @json($compositionData['counts']);

    function initCompositionChart() {
        const canvas = document.getElementById('compositionChart');
        const container = canvas.parentElement;
        canvas.width = Math.min(container.clientWidth, 400);
        canvas.height = 320;
        
        const ctx = canvas.getContext('2d');
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: compositionLabels,
                datasets: [{
                    data: compositionRevenue,
                    backgroundColor: chartColors,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleFont: { size: 12 },
                        bodyFont: { size: 11 },
                        padding: 10,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.raw / total) * 100).toFixed(1);
                                return context.label + ': ' + new Intl.NumberFormat('vi-VN').format(context.raw) + ' VNĐ (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Initialize flatpickr for date inputs
    flatpickr('.flatpickr-date', {
        dateFormat: 'Y-m-d',
        locale: 'vi',
        allowInput: true,
    });

    // Toggle custom date inputs
    function toggleCustomDate(select) {
        const fromGroup = document.getElementById('customFromGroup');
        const toGroup = document.getElementById('customToGroup');
        if (select.value === 'custom') {
            fromGroup.classList.remove('d-none');
            toGroup.classList.remove('d-none');
        } else {
            fromGroup.classList.add('d-none');
            toGroup.classList.add('d-none');
        }
    }

    // Render charts on load
    document.addEventListener('DOMContentLoaded', function() {
        // Small delay to ensure layout is settled
        setTimeout(() => {
            renderRevenueChart();
            initCompositionChart();
        }, 100);
    });

    // Re-render charts on window resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            if (revenueChart) renderRevenueChart();
            initCompositionChart();
        }, 150);
    });

    // Export to Excel (placeholder)
    function exportToExcel() {
        Swal.fire({
            icon: 'info',
            title: 'Tính năng đang phát triển',
            text: 'Xuất Excel sẽ được cập nhật trong phiên bản tới.',
            timer: 2000,
            showConfirmButton: false
        });
    }
</script>
@endsection