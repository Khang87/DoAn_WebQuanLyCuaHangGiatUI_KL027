@extends('layouts.app')

@section('title', 'Đặt lịch - Giặt Ủi Pro')
@section('page-title', 'Đặt lịch')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('bookings.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i>Tạo đặt lịch
    </a>
    <form action="{{ route('bookings.index') }}" method="GET" class="d-flex gap-2">
        <div class="input-group" style="width: 250px;">
            <input type="text" name="search" class="form-control" placeholder="Tìm khách hàng..." value="{{ request('search') }}">
        </div>
        <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            <option value="pending" @selected(request('status') === 'pending')>Chờ xác nhận</option>
            <option value="confirmed" @selected(request('status') === 'confirmed')>Đã xác nhận</option>
            <option value="completed" @selected(request('status') === 'completed')>Hoàn thành</option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>Đã hủy</option>
        </select>
        <select name="delivery_method" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả hình thức</option>
            <option value="pickup" @selected(request('delivery_method') === 'pickup')>Nhận tại tiệm</option>
            <option value="dropoff" @selected(request('delivery_method') === 'dropoff')>Giao tận nơi</option>
        </select>
        <a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary">Xóa</a>
    </form>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Mã</th><th>Khách hàng</th><th>Dịch vụ</th><th>Loại đồ</th><th>SL</th><th>Giao nhận</th><th>Ngày lấy</th><th>Trạng thái</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                    @forelse($bookings as $booking)
                    <tr>
                        <td><strong>#{{ $booking->id }}</strong></td>
                        <td>{{ $booking->customer?->name ?: '-' }}</td>
                        <td>{{ $booking->service?->name ?: '-' }}</td>
                        <td>{{ $booking->garment_type }}</td>
                        <td>{{ $booking->quantity }}</td>
                        <td>{{ $booking->delivery_method === 'pickup' ? 'Nhận tại tiệm' : 'Giao tận nơi' }}</td>
                        <td>{{ $booking->pickup_date?->format('d/m/Y') }}</td>
                        <td>
                            @if($booking->status === 'confirmed')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đã xác nhận</span>
                            @elseif($booking->status === 'cancelled')
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>Đã hủy</span>
                            @elseif($booking->status === 'completed')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoàn thành</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>{{ ucfirst($booking->status) }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('bookings.show', $booking) }}" class="btn btn-sm btn-outline-info">Xem</a>
                                <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-sm btn-outline-warning">Sửa</a>
                                <form action="{{ route('bookings.destroy', $booking) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Chưa có đặt lịch</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($bookings->hasPages())
<div class="mt-3">
    {{ $bookings->appends(request()->query())->links('pagination::bootstrap-5') }}
</div>
@endif
@endsection
