@extends('layouts.app')

@section('title', 'Top khách hàng - Giặt Ủi Pro')
@section('page-title', 'Top khách hàng')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Top khách hàng chi tiêu cao nhất</h5>
        <a href="{{ route('reports.index') }}" class="text-decoration-none text-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr><th>#</th><th>Họ tên</th><th>Email</th><th>SĐT</th><th>Dòng tiền</th><th>Số đơn</th><th>Tổng chi tiêu</th></tr>
                </thead>
                <tbody>
                    @forelse($topCustomers as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $item->customer?->name }}</strong>
                            @if($item->customer?->type === 'VIP')
                                <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1 rounded-pill ms-2"><i class="fas fa-crown"></i></span>
                            @elseif($item->customer?->type === 'Thường')
                                <span class="badge bg-success-subtle text-success border border-success px-2 py-1 rounded-pill ms-2"><i class="fas fa-user"></i></span>
                            @else
                                <span class="badge bg-info-subtle text-info border border-info px-2 py-1 rounded-pill ms-2"><i class="fas fa-user-plus"></i></span>
                            @endif
                        </td>
                        <td>{{ $item->customer?->email ?: '-' }}</td>
                        <td>{{ $item->customer?->phone ?: '-' }}</td>
                        <td><span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1 rounded-pill">{{ number_format($item->customer?->points ?? 0) }} <i class="fas fa-star"></i></span></td>
                        <td>{{ number_format($item->order_count) }}</td>
                        <td><strong class="text-primary">{{ number_format($item->total_spent) }} VNĐ</strong></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có dữ liệu khách hàng</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
