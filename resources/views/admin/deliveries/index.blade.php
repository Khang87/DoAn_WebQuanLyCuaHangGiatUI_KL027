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
                            @if($delivery->status === 'confirmed')
                                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đã xác nhận</span>
                            @elseif($delivery->status === 'cancelled')
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>Đã hủy</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>Chờ xác nhận</span>
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
                     <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-search fa-2x mb-2 text-secondary d-block"></i>Không tìm thấy dữ liệu phù hợp</td></tr>
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
