@extends('layouts.app')

@section('title', 'Quản Lý Đơn Hàng - Giặt Ủi Pro')
@section('page-title', 'Quản Lý Đơn Hàng')

@section('content')
<!-- Page Actions -->
<div class="order-toolbar">
    <div>
        <a href="{{ route('orders.create') }}" class="btn btn-order-primary">
            <i class="bi bi-plus-lg me-2"></i>Tạo đơn hàng mới
        </a>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <div class="input-group search-box">
            <input type="text" class="form-control" placeholder="Tìm kiếm đơn hàng...">
            <button class="btn btn-order-secondary" type="button">
                <i class="bi bi-search"></i>
            </button>
        </div>
        <select class="form-select" style="width: auto; border-radius: 12px; border-color: rgba(148,163,184,0.35);">
            <option value="">Tất cả trạng thái</option>
            <option value="pending">Chờ xử lý</option>
            <option value="processing">Đang xử lý</option>
            <option value="completed">Hoàn thành</option>
            <option value="cancelled">Đã hủy</option>
        </select>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Mã Đơn</th>
                        <th>Khách Hàng</th>
                        <th>Dịch Vụ</th>
                        <th>Số Lượng</th>
                        <th>Ghi Chú Khách Hàng</th>
                        <th>Tổng Tiền</th>
                        <th>Trạng Thái</th>
                        <th>Ngày Tạo</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $sampleOrders = [
                            (object)['code' => 'DH001', 'customer_id' => 1, 'service_id' => 1, 'weight_kg' => '5kg', 'quantity_items' => 'đồ thường + 3 áo trắng', 'total_amount' => 250000, 'status' => 'processing', 'notes' => 'Giặt nhẹ, lấy trước 18h tối nay.', 'created_at' => now(), 'customer' => (object)['name' => 'Nguyễn Văn A', 'phone' => '0901234567'], 'service' => (object)['name' => 'Giặt thường']],
                            (object)['code' => 'DH002', 'customer_id' => 2, 'service_id' => 2, 'weight_kg' => '3kg', 'quantity_items' => 'đồ thường + 2 áo sơ mi', 'total_amount' => 180000, 'status' => 'completed', 'notes' => 'Không dùng nước xả có mùi.', 'created_at' => now()->subDay(), 'customer' => (object)['name' => 'Trần Thị B', 'phone' => '0912345678'], 'service' => (object)['name' => 'Giặt khô']],
                            (object)['code' => 'DH003', 'customer_id' => 3, 'service_id' => 3, 'weight_kg' => '8kg', 'quantity_items' => 'đồ nặng + 5 quần dài', 'total_amount' => 320000, 'status' => 'pending', 'notes' => 'Ủi phẳng, đóng gói riêng.', 'created_at' => now()->subDays(2), 'customer' => (object)['name' => 'Phạm Thị C', 'phone' => '0923456789'], 'service' => (object)['name' => 'Ủi đồ']],
                        ];
                        $displayOrders = $orders->count() > 0 ? $orders : collect($sampleOrders);
                    @endphp
                    @forelse($displayOrders as $order)
                    <tr>
                        <td><strong>#{{ $order->code }}</strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="{{ asset('assets/images/user.jfif') }}" alt="Ảnh khách hàng" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;" onerror="this.onerror=null; this.src='{{ asset('assets/images/user.jfif') }}';">
                                <div>
                                    <div class="fw-semibold">{{ $order->customer?->name ?: '-' }}</div>
                                    <small class="text-muted">{{ $order->customer?->phone ?: 'Chưa có số điện thoại' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $order->service?->name ?: '-' }}</td>
                        <td><span class="fw-semibold">{{ trim(($order->weight_kg ? $order->weight_kg . ' ' : '') . ($order->quantity_items ?: '-')) }}</span></td>
                        <td><small>{{ $order->notes ?: 'Không có ghi chú' }}</small></td>
                        <td><strong>{{ number_format($order->total_amount) }} VNĐ</strong></td>
                        <td><span class="badge-status badge-{{ $order->status === 'completed' ? 'completed' : ($order->status === 'pending' ? 'pending' : 'processing') }}">{{ $order->status }}</span></td>
                        <td>{{ $order->created_at?->format('d/m/Y') }}</td>
                        <td><div class="d-flex gap-2"><a href="{{ route('orders.show', $order) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a><a href="{{ route('orders.edit', $order) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a><form action="{{ route('orders.destroy', $order) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">@csrf @method('DELETE')<button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button></form></div></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Chưa có đơn hàng nào</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <li class="page-item disabled">
            <a class="page-link" href="#"><i class="bi bi-chevron-left"></i></a>
        </li>
        <li class="page-item active"><a class="page-link" href="#">1</a></li>
        <li class="page-item"><a class="page-link" href="#">2</a></li>
        <li class="page-item"><a class="page-link" href="#">3</a></li>
        <li class="page-item">
            <a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a>
        </li>
    </ul>
</nav>
@endsection
