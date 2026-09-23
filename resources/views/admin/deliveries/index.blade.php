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
                    <tr>
                        <td><strong>GH001</strong></td>
                        <td>Nguyễn Văn A<br><small class="text-muted">0901234567</small></td>
                        <td><span class="delivery-type pickup"><i class="bi bi-truck me-1"></i>Lấy tận nơi</span></td>
                        <td>123 Đường Điện Biên Phủ, Bình Thạnh</td>
                        <td>25/09/2026<br><small class="text-muted">09:00 - 10:00</small></td>
                        <td><span class="badge-status badge-pending">Chờ lấy đồ</span></td>
                        <td>
                            <a href="{{ route('deliveries.show', 1) }}" class="btn btn-order-action view" title="Xem">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>GH002</strong></td>
                        <td>Trần Thị B<br><small class="text-muted">0912345678</small></td>
                        <td><span class="delivery-type store"><i class="bi bi-shop me-1"></i>Mang đến cửa hàng</span></td>
                        <td>Cửa hàng Giặt Ủi Pro</td>
                        <td>24/09/2026<br><small class="text-muted">14:30</small></td>
                        <td><span class="badge-status badge-processing">Đã nhận đồ</span></td>
                        <td>
                            <a href="{{ route('deliveries.show', 2) }}" class="btn btn-order-action view" title="Xem">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
