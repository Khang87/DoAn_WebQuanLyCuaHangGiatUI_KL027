@extends('layouts.app')

@section('title', 'Chi Tiết Khách Hàng - Giặt Ủi Pro')
@section('page-title', 'Chi Tiết Khách Hàng')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">{{ $customer->name }}</h5>
                    <span class="badge bg-warning text-dark">{{ $customer->type }}</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Email</div>
                        <div class="fw-semibold">{{ $customer->email ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Số điện thoại</div>
                        <div class="fw-semibold">{{ $customer->phone ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Địa chỉ</div>
                        <div class="fw-semibold">{{ $customer->address ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Điểm tích lũy</div>
                        <div class="fw-semibold text-primary">{{ number_format($customer->points) }} điểm</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Ngày đăng ký</div>
                        <div class="fw-semibold">{{ $customer->created_at?->format('d/m/Y') }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Trạng thái</div>
                        <div class="fw-semibold">Hoạt động</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order & Service History -->
        <div class="card mt-4">
            <div class="card-header bg-transparent border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Lịch sử sử dụng dịch vụ & Đơn hàng</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Dịch vụ</th>
                                <th>Số lượng</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($customer->orders as $order)
                            <tr>
                                <td><strong>#{{ $order->code }}</strong></td>
                                <td>{{ $order->service?->name ?: '-' }}</td>
                                <td>{{ $order->quantity_items ?: '-' }}</td>
                                <td><strong class="text-primary">{{ number_format($order->total_amount) }} VNĐ</strong></td>
                                <td><span class="badge-status badge-{{ $order->status === 'completed' ? 'completed' : ($order->status === 'pending' ? 'pending' : 'processing') }}">{{ $order->status }}</span></td>
                                <td>{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Chưa có lịch sử đơn hàng nào cho khách hàng này.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Thao tác</h6>
                <div class="d-grid gap-2">
                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-warning">Chỉnh sửa</a>
                    <form action="{{ route('customers.destroy', $customer) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa khách hàng?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">Xóa khách hàng</button>
                    </form>
                    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Quay lại danh sách</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
