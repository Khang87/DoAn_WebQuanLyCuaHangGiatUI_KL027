@extends('layouts.app')

@section('title', 'Doanh Thu - Sky Laundry')
@section('page-title', 'Doanh Thu')

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Tóm tắc doanh thu</h5>
        <a href="{{ route('reports.index') }}" class="text-decoration-none text-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
    <div class="card-body">
        <div class="row g-3 text-center">
            <div class="col-4">
                <div class="fw-bold text-muted">Tổng doanh thu</div>
                <div class="stat-value">{{ number_format($summary['total_revenue']) }} VNĐ</div>
            </div>
            <div class="col-4">
                <div class="fw-bold text-muted">Đơn hàng hoàn thành</div>
                <div class="stat-value">{{ number_format($summary['completed_orders']) }}</div>
            </div>
            <div class="col-4">
                <div class="fw-bold text-muted">Doanh thu hôm nay</div>
                <div class="stat-value">{{ number_format($summary['today_revenue']) }} VNĐ</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Doanh thu theo tháng (VNĐ)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Tháng</th>
                        <th class="text-end">Doanh Thu</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($revenueData as $item)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($item['period'])->format('m/Y') }}</td>
                        <td class="text-end fw-semibold text-primary">{{ number_format($item['total']) }} VNĐ</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
