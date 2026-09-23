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
                        <th>Tổng Tiền</th>
                        <th>Trạng Thái</th>
                        <th>Ngày Tạo</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>#DH001</strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=32&auto=format&fit=crop" 
                                     alt="Avatar" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                                <div>
                                    <div class="fw-semibold">Nguyễn Văn A</div>
                                    <small class="text-muted">0901234567</small>
                                </div>
                            </div>
                        </td>
                        <td>Giặt ủi + Giặt khô</td>
                        <td><span class="fw-semibold">5kg đồ thường + 3 áo trắng</span></td>
                        <td><strong>250,000 VNĐ</strong></td>
                        <td><span class="badge-status badge-processing">Đang xử lý</span></td>
                        <td>20/09/2024</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('orders.show', 1) }}" class="btn btn-order-action view" title="Xem">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('orders.edit', 1) }}" class="btn btn-order-action edit" title="Sửa">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('orders.destroy', 1) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>#DH002</strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=32&auto=format&fit=crop" 
                                     alt="Avatar" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                                <div>
                                    <div class="fw-semibold">Trần Thị B</div>
                                    <small class="text-muted">0912345678</small>
                                </div>
                            </div>
                        </td>
                        <td>Giặt ủi thường</td>
                        <td><span class="fw-semibold">3kg đồ thường + 2 áo sơ mi</span></td>
                        <td><strong>180,000 VNĐ</strong></td>
                        <td><span class="badge-status badge-pending">Chờ xử lý</span></td>
                        <td>19/09/2024</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('orders.show', 2) }}" class="btn btn-order-action view" title="Xem">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('orders.edit', 2) }}" class="btn btn-order-action edit" title="Sửa">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('orders.destroy', 2) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>#DH003</strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=32&auto=format&fit=crop" 
                                     alt="Avatar" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                                <div>
                                    <div class="fw-semibold">Phạm Thị C</div>
                                    <small class="text-muted">0923456789</small>
                                </div>
                            </div>
                        </td>
                        <td>Giặt khô + Ủi</td>
                        <td><span class="fw-semibold">8kg đồ nặng + 5 quần dài</span></td>
                        <td><strong>320,000 VNĐ</strong></td>
                        <td><span class="badge-status badge-completed">Hoàn thành</span></td>
                        <td>18/09/2024</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('orders.show', 3) }}" class="btn btn-order-action view" title="Xem">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('orders.edit', 3) }}" class="btn btn-order-action edit" title="Sửa">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('orders.destroy', 3) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
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
