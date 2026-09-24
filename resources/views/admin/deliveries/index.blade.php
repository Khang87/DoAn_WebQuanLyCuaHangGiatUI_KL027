@extends('layouts.app')

@section('title', 'Quản Lý Giao Nhận - Giặt Ủi Pro')
@section('page-title', 'Quản Lý Giao Nhận')

@section('content')
<div class="order-toolbar">
    <div>
        <p class="text-muted mb-0">Theo dõi lịch lấy và giao đồ cho khách hàng.</p>
    </div>
    <a href="{{ route('deliveries.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i>Tạo lịch giao nhận
    </a>
</div>

<form action="{{ route('deliveries.index') }}" method="GET" class="d-flex gap-2 mb-3">
    <div class="input-group" style="width: 200px;">
        <input type="text" name="search" class="form-control" placeholder="Tìm khách hàng..." value="{{ request('search') }}">
    </div>
    <select name="method" class="form-select" style="width: auto;" onchange="this.form.submit()">
        <option value="">Tất cả hình thức</option>
        <option value="pickup" @selected(request('method') === 'pickup')>Lấy tại tiệm</option>
        <option value="dropoff" @selected(request('method') === 'dropoff')>Giao tận nơi</option>
    </select>
    <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
        <option value="">Tất cả trạng thái</option>
        <option value="pending" @selected(request('status') === 'pending')>Chờ lấy đồ</option>
        <option value="confirmed" @selected(request('status') === 'confirmed')>Đã nhận đồ</option>
        <option value="completed" @selected(request('status') === 'completed')>Hoàn thành</option>
        <option value="cancelled" @selected(request('status') === 'cancelled')>Đã hủy</option>
    </select>
    <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary">Xóa</a>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Mã giao nhận</th>
                        <th>Khách hàng</th>
                        <th>Hình thức</th>
                        <th>Địa chỉ</th>
                        <th>Thời gian lấy</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $delivery)
                    <tr>
                        <td><strong>GH{{ $delivery->id }}</strong></td>
                        <td>
                            {{ $delivery->customer?->name ?: '-' }}
                            <br><small class="text-muted">{{ $delivery->customer?->phone ?: '' }}</small>
                        </td>
                        <td>
                            @if($delivery->method === 'pickup')
                                <span class="delivery-type pickup"><i class="bi bi-truck me-1"></i>Lấy tận nơi</span>
                            @else
                                <span class="delivery-type store"><i class="bi bi-shop me-1"></i>Mang đến cửa hàng</span>
                            @endif
                        </td>
                        <td>{{ $delivery->address ?: '-' }}</td>
                        <td>{{ $delivery->pickup_date?->format('d/m/Y') }}<br><small class="text-muted">{{ $delivery->pickup_time?->format('H:i') }}</small></td>
                        <td>
                            @if($delivery->status === 'completed')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đã giao</span>
                            @elseif($delivery->status === 'in_progress')
                                <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-cog me-1"></i>Đang giao</span>
                            @elseif($delivery->status === 'cancelled')
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>Đã hủy</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>Chờ lấy đồ</span>
                            @endif
                        </td>
                        <td>
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
                    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có lịch giao nhận nào</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($deliveries->hasPages())
<div class="mt-3">
    {{ $deliveries->appends(request()->query())->links('pagination::bootstrap-5') }}
</div>
@endif
@endsection
