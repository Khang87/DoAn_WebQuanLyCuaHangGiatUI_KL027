@extends('layouts.app')

@section('title', 'Doanh Thu - Giặt Ủi Pro')
@section('page-title', 'Doanh Thu')

@section('content')
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Tóm tắt</h5></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-4"><div class="fw-bold">Tổng:</div>{{ number_format($summary['total_revenue']) }} VNĐ</div>
            <div class="col-4"><div class="fw-bold">Đã hoàn thành:</div>{{ number_format($summary['completed_orders']) }} đơn</div>
            <div class="col-4"><div class="fw-bold">Hôm nay:</div>{{ number_format($summary['today_revenue']) }} VNĐ</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Doanh Thu theo tháng</h5></div>
    <div class="card-body">
        <table class="table table-hover">
            <thead><tr><th>Tháng</th><th>Doanh Thu (VNĐ)</th></tr></thead>
            <tbody>
                @foreach($monthlyRevenue as $month => $revenue)
                <tr>
                    <td>{{ \Carbon\Carbon::create(2026, $month + 1, 1)->translatedFormat('M Y') }}</td>
                    <td>{{ number_format($revenue * 1000000) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
