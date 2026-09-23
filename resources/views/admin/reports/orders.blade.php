@extends('layouts.app')

@section('title', 'Thống kê đơn hàng - Giặt Ủi Pro')
@section('page-title', 'Thống kê đơn hàng')

@section('content')
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Đơn hàng theo trạng thái</h5></div>
    <div class="card-body">
        <table class="table table-hover">
            <thead><tr><th>Trạng thái</th><th>Số lượng</th><th>Tỷ lệ</th></tr></thead>
            <tbody>
                @foreach($orderStatusCounts as $status => $count)
                <tr>
                    <td>{{ $status }}</td>
                    <td>{{ $count }}</td>
                    <td>{{ $count > 0 ? round(($count / array_sum($orderStatusCounts)) * 100, 1) : 0 }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Đơn hàng theo dịch vụ</h5></div>
    <div class="card-body">
        <table class="table table-hover">
            <thead><tr><th>Dịch vụ</th><th>Số đơn</th></tr></thead>
            <tbody>
                @foreach($serviceCounts as $serviceId => $count)
                <tr>
                    <td>{{ $services->firstWhere('id', $serviceId)?->name ?? 'Không rõ' }}</td>
                    <td>{{ $count }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
