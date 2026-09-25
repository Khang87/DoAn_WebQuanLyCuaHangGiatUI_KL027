@extends('layouts.app')

@section('title', 'Quản lý Giao nhận - Sky Laundry')
@section('page-title', 'Quản lý Giao nhận')

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
            <option value="pickup" @selected(request('method') === 'pickup')>Khách nhận tại cửa hàng</option>
            <option value="dropoff" @selected(request('method') === 'dropoff')>Giao tận nơi</option>
            <option value="home_pickup" @selected(request('method') === 'home_pickup')>Đến lấy đồ tận nhà</option>
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 200px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="pending" @selected(request('status') === 'pending')>Chờ xác nhận</option>
            <option value="picking" @selected(request('status') === 'picking')>Đang lấy hàng</option>
            <option value="delivering" @selected(request('status') === 'delivering')>Đang giao hàng</option>
            <option value="completed" @selected(request('status') === 'completed')>Hoàn thành</option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>Đã hủy</option>
        </select>
    </div>
</form>

<!-- Deliveries Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-custom">
                <thead class="table-light">
                    <tr>
                        <th>MÃ GIAO NHẬN</th>
                        <th>KHÁCH HÀNG</th>
                        <th>HÌNH THỨC</th>
                        <th>ĐỊA CHỈ</th>
                        <th>THỜI GIAN LẤY</th>
                        <th>TRẠNG THÁI</th>
                        <th class="text-center">THAO TÁC</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $delivery)
                    <tr>
                        <td>
                        <span class="fw-bold text-dark">
                            {{ $delivery->code_format }}
                        </span>
                    </td>
                        <td>
                            @php
                                $avatarId = $delivery->customer?->id ?? $delivery->id;
                                $avatarUrl = 'assets/images/user_' . (($avatarId % 8) + 1) . '.jpg';
                            @endphp
                            <div class="d-flex align-items-center">
                                <img src="{{ asset($avatarUrl) }}" alt="Ảnh khách hàng" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                <div>
                                    <div class="fw-semibold">{{ $delivery->customer?->name ?: $delivery->customer_name ?: '-' }}</div>
                                    <small class="text-muted">{{ $delivery->customer?->phone ?: $delivery->phone ?: '-' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($delivery->method === 'pickup')
                                <i class="bi bi-shop me-1"></i>Khách nhận tại cửa hàng
                            @elseif($delivery->method === 'home_pickup')
                                <i class="bi bi-truck me-1"></i>Đến lấy đồ tận nhà
                            @else
                                <i class="bi bi-geo-alt me-1"></i>Giao tận nơi
                            @endif
                        </td>
                        <td>{{ $delivery->address ?: '-' }}</td>
                        <td>
                            {{ $delivery->pickup_date?->format('d/m/Y') ?: '-' }}<br>
                            <small class="text-muted">{{ $delivery->pickup_time?->format('H:i') ?: '-' }}</small>
                        </td>
                        <td>
                            @if($delivery->status === 'picking')
                                <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-cog me-1"></i>Đang lấy hàng</span>
                            @elseif($delivery->status === 'delivering')
                                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill"><i class="fas fa-motorcycle me-1"></i>Đang giao hàng</span>
                            @elseif($delivery->status === 'completed')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoàn thành</span>
                            @elseif($delivery->status === 'cancelled')
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-times-circle me-1"></i>Đã hủy</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-clock me-1"></i>Chờ xác nhận</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-2">
                                <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('deliveries.destroy', $delivery) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Chưa có dữ liệu nào</td>
                    </tr>
                    @endempty
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
        <ul class="pagination mb-0">
            @if ($deliveries->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $deliveries->appends(request()->query())->url($deliveries->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($deliveries->getUrlRange(max(1, $deliveries->currentPage() - 2), min($deliveries->lastPage(), $deliveries->currentPage() + 2)) as $page => $url)
                @if ($page == $deliveries->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $deliveries->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($deliveries->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $deliveries->appends(request()->query())->url($deliveries->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection

@push('styles')
<style>
    .table-custom th { background: #f8f9fa; font-weight: 600; }
    .btn-order-action { min-width: 36px; padding: 6px 10px; }
    .table tbody tr:hover { background-color: rgba(0,0,0,0.02); }
</style>
@endpush
