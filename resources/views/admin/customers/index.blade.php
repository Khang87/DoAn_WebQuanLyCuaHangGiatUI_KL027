@extends('layouts.app')

@section('title', 'Quản Lý Khách Hàng - Giặt Ủi Pro')
@section('page-title', 'Quản Lý Khách Hàng')

@section('content')
<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>Thêm Khách Hàng
        </a>
    </div>
    <div class="d-flex gap-2">
        <div class="input-group" style="width: 300px;">
            <input type="text" class="form-control" placeholder="Tìm kiếm khách hàng...">
            <button class="btn btn-outline-secondary" type="button">
                <i class="bi bi-search"></i>
            </button>
        </div>
        <select class="form-select" style="width: auto;">
            <option value="">Tất cả loại</option>
            <option value="vip">VIP</option>
            <option value="regular">Thường</option>
            <option value="new">Mới</option>
        </select>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Mã khách hàng</th>
                        <th>Khách Hàng</th>
                        <th>Email</th>
                        <th>Số Điện Thoại</th>
                        <th>Địa Chỉ</th>
                        <th>Điểm Tích Lũy</th>
                        <th>Loại</th>
                        <th>Ngày Đăng Ký</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $sampleCustomers = [
                            (object)['code' => 'KH001', 'name' => 'Nguyễn Văn A', 'email' => 'nguyenvana@email.com', 'phone' => '0901234567', 'address' => '123 Đường ABC, Quận 1, TP.HCM', 'points' => 1250, 'type' => 'VIP', 'created_at' => now()->subDays(5)],
                            (object)['code' => 'KH002', 'name' => 'Trần Thị B', 'email' => 'tranthib@email.com', 'phone' => '0912345678', 'address' => '456 Đường XYZ, Quận 3, TP.HCM', 'points' => 850, 'type' => 'Thường', 'created_at' => now()->subDays(8)],
                            (object)['code' => 'KH003', 'name' => 'Phạm Thị C', 'email' => 'phamthic@email.com', 'phone' => '0923456789', 'address' => '789 Đường DEF, Quận 5, TP.HCM', 'points' => 0, 'type' => 'Mới', 'created_at' => now()->subDays(12)],
                        ];
                        $displayCustomers = $customers->count() > 0 ? $customers : collect($sampleCustomers);
                    @endphp
                    @forelse($displayCustomers as $customer)
                    <tr>
                        <td><strong>{{ $customer->code }}</strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="{{ asset('assets/images/user.jfif') }}" alt="Ảnh khách hàng" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.onerror=null; this.src='{{ asset('assets/images/user.jfif') }}';">
                                <div class="fw-semibold">{{ $customer->name }}</div>
                            </div>
                        </td>
                        <td>{{ $customer->email ?: '-' }}</td>
                        <td>{{ $customer->phone ?: '-' }}</td>
                        <td>{{ $customer->address ?: '-' }}</td>
                        <td><strong>{{ number_format($customer->points) }}</strong> điểm</td>
                        <td><span class="customer-type-badge {{ $customer->type === 'VIP' ? 'vip' : ($customer->type === 'Mới' ? 'new' : 'regular') }}">{{ $customer->type }}</span></td>
                        <td>{{ $customer->created_at?->format('d/m/Y') }}</td>
                        <td><div class="d-flex gap-2">
                            <a href="{{ route('customers.show', $customer) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">@csrf @method('DELETE')<button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button></form>
                        </div></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Chưa có khách hàng nào</td>
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
