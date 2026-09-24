@extends('layouts.app')

@section('title', 'Quản Lý Giao Nhận - Sky Laundry')
@section('page-title', 'Quản Lý Giao Nhận')

@section('content')
<div class="order-toolbar mb-4">
    <p class="text-muted mb-0">Theo dõi lịch lấy và giao đồ cho khách hàng.</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm theo ID, mã giao nhận, mã đơn hàng, tên khách, SĐT..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="method" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả hình thức --</option>
            <option value="pickup" @selected(request('method') === 'pickup')>Lấy tại tiệm</option>
            <option value="dropoff" @selected(request('method') === 'dropoff')>Giao tận nơi</option>
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 200px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="pending" @selected(request('status') === 'pending')>Chờ xác nhận</option>
            <option value="confirmed" @selected(request('status') === 'confirmed')>Đã xác nhận</option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>Đã hủy</option>
        </select>
    </div>
</form>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Mã giao nhận</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Khách hàng</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Hình thức</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Địa chỉ</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Thời gian lấy</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Trạng thái</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $delivery)
                    <tr>
                        <td class="text-start align-middle py-3 fw-bold text-dark">GH{{ $delivery->id }}</td>
                        <td class="text-start align-middle py-3">
                            <div class="fw-bold">{{ $delivery->customer?->name ?: '-' }}</div>
                            <small class="text-muted">{{ $delivery->customer?->phone ?: 'Chưa có SĐT' }}</small>
                        </td>
                        <td class="text-start align-middle py-3 text-muted">
                            @if($delivery->method === 'pickup')
                                <i class="bi bi-truck me-1"></i>Lấy tại tiệm
                            @else
                                <i class="bi bi-shop me-1"></i>Giao tận nơi
                            @endif
                        </td>
                        <td class="text-start align-middle py-3 text-muted">{{ $delivery->address ?: '-' }}</td>
                        <td class="text-start align-middle py-3 text-muted">
                            {{ $delivery->pickup_date?->format('d/m/Y') ?: '-' }}<br>
                            <small>{{ $delivery->pickup_time?->format('H:i') ?: '-' }}</small>
                        </td>
                        <td class="text-start align-middle py-3">
                            @if($delivery->status === 'confirmed')
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i>Đã xác nhận</span>
                            @elseif($delivery->status === 'cancelled')
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2"><i class="fas fa-times-circle me-1"></i>Đã hủy</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-2"><i class="fas fa-clock me-1"></i>Chờ xác nhận</span>
                            @endif
                        </td>
                        <td class="text-start align-middle py-3">
                            <div class="d-flex align-items-center gap-1">
                                <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-sm btn-outline-info" title="Xem chi tiết"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-sm btn-outline-warning" title="Chỉnh sửa"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('deliveries.destroy', $delivery) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fas fa-search fa-2x mb-2 text-secondary d-block"></i>
                            Không tìm thấy dữ liệu phù hợp
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($deliveries->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            Hiển thị {{ $deliveries->firstItem() }} - {{ $deliveries->lastItem() }} của {{ $deliveries->total() }} giao nhận
        </div>
        {{ $deliveries->appends(request()->query())->links('pagination::bootstrap-5') }}
    </div>
</nav>
@endif
@endsection
