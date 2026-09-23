@extends('layouts.app')

@section('title', 'Top khách hàng - Giặt Ủi Pro')
@section('page-title', 'Top khách hàng')

@section('content')
<div class="card">
    <div class="card-header"><h5 class="mb-0">Top khách hàng chi tiêu cao nhất</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>#</th><th>Họ tên</th><th>Email</th><th>Số đơn</th><th>Tổng chi tiêu</th></tr>
                </thead>
                <tbody>
                    @foreach($topCustomers as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td><strong>{{ $item->customer?->name }}</strong></td>
                        <td>{{ $item->customer?->email }}</td>
                        <td>{{ $item->order_count }}</td>
                        <td><strong>{{ number_format($item->total_spent) }} VNĐ</strong></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
