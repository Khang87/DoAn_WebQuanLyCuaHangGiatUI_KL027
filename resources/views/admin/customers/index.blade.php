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
                    <tr>
                        <td><strong>KH001</strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=40&auto=format&fit=crop" 
                                     alt="Avatar" class="rounded-circle me-2" style="width: 40px; height: 40px;">
                                <div>
                                    <div class="fw-semibold">Nguyễn Văn A</div>
                                </div>
                            </div>
                        </td>
                        <td>nguyenvana@email.com</td>
                        <td>0901234567</td>
                        <td>123 Đường ABC, Quận 1, TP.HCM</td>
                        <td><strong>1,250</strong> điểm</td>
                        <td><span class="badge bg-warning text-dark">VIP</span></td>
                        <td>01/01/2024</td>
                        <td><div class="d-flex gap-2">
                            <a href="{{ route('customers.show', 1) }}" class="btn btn-order-action view" title="Xem">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('customers.edit', 1) }}" class="btn btn-order-action edit" title="Sửa">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('customers.destroy', 1) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-order-action delete" title="Xóa">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div></td>
                    </tr>
                    <tr>
                        <td><strong>KH002</strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=40&auto=format&fit=crop" 
                                     alt="Avatar" class="rounded-circle me-2" style="width: 40px; height: 40px;">
                                <div>
                                    <div class="fw-semibold">Trần Thị B</div>
                                </div>
                            </div>
                        </td>
                        <td>tranthib@email.com</td>
                        <td>0912345678</td>
                        <td>456 Đường XYZ, Quận 3, TP.HCM</td>
                        <td><strong>850</strong> điểm</td>
                        <td><span class="badge bg-primary">Thường</span></td>
                        <td>15/02/2024</td>
                        <td><div class="d-flex gap-2">
                            <a href="{{ route('customers.show', 2) }}" class="btn btn-order-action view" title="Xem">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('customers.edit', 2) }}" class="btn btn-order-action edit" title="Sửa">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('customers.destroy', 2) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-order-action delete" title="Xóa">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div></td>
                    </tr>
                    <tr>
                        <td><strong>KH003</strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=40&auto=format&fit=crop" 
                                     alt="Avatar" class="rounded-circle me-2" style="width: 40px; height: 40px;">
                                <div>
                                    <div class="fw-semibold">Phạm Thị C</div>
                                </div>
                            </div>
                        </td>
                        <td>phamthic@email.com</td>
                        <td>0923456789</td>
                        <td>789 Đường DEF, Quận 5, TP.HCM</td>
                        <td><strong>0</strong> điểm</td>
                        <td><span class="badge bg-success">Mới</span></td>
                        <td>20/09/2024</td>
                        <td><div class="d-flex gap-2">
                            <a href="{{ route('customers.show', 3) }}" class="btn btn-order-action view" title="Xem">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('customers.edit', 3) }}" class="btn btn-order-action edit" title="Sửa">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('customers.destroy', 3) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-order-action delete" title="Xóa">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div></td>
                    </tr>
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
