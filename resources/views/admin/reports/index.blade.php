@extends('layouts.app')

@section('title', 'Báo cáo & Thống kê - Giặt Ủi Pro')
@section('page-title', 'Báo cáo & Thống kê')

@section('content')
<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-value">{{ number_format($summary['total_revenue']) }}</div>
                <div class="stat-label">Tổng Doanh Thu</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-value">{{ $summary['total_orders'] }}</div>
                <div class="stat-label">Tổng Đơn Hàng</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-value">{{ $summary['today_revenue'] }}</div>
                <div class="stat-label">Doanh Thu Hôm Nay</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-value">{{ $summary['month_revenue'] }}</div>
                <div class="stat-label">Doanh Thu Tháng</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Đơn hàng theo trạng thái</h5></div>
            <div class="card-body">
                @foreach($orderStatusCounts as $status => $count)
                <div class="d-flex align-items-center mb-2">
                    <span class="badge bg-info text-dark me-3" style="width: 100px;">{{ $status }}</span>
                    <div class="flex-grow-1">
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar" role="progressbar" style="width: {{ $count > 0 ? min(100, ($count / max(1, max($orderStatusCounts))) * 100) : 0 }}%;">
                                {{ $count }}
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Top Khách Hàng</h5></div>
            <div class="card-body">
                @forelse($topCustomers as $item)
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $item->customer?->name }}</div>
                        <small class="text-muted">{{ $item->customer?->email }}</small>
                    </div>
                    <div class="text-end">
                        <strong>{{ number_format($item->total_spent) }} VNĐ</strong><br>
                        <small>{{ $item->order_count }} đơn</small>
                    </div>
                </div>
                @empty
                <p class="text-center text-muted">Chưa có dữ liệu</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Báo cáo chi tiết</h5>
            </div>
            <div class="card-body">
                <div class="d-flex gap-2">
                    <a href="{{ route('reports.revenue') }}" class="btn btn-outline-primary">
                        <i class="bi bi-currency-dollar me-1"></i>Doanh Thu
                    </a>
                    <a href="{{ route('reports.orders') }}" class="btn btn-outline-primary">
                        <i class="bi bi-receipt me-1"></i>Đơn Hàng
                    </a>
                    <a href="{{ route('reports.customers') }}" class="btn btn-outline-primary">
                        <i class="bi bi-people me-1"></i>Khách Hàng
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
