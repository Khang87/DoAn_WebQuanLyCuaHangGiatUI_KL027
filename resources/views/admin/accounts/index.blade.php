@extends('layouts.app')
@section('title', 'Tài Khoản - Giặt Ủi Pro')
@section('page-title', 'Tài Khoản')
@section('content')
<div class="order-toolbar"><p class="text-muted mb-0">Quản lý tài khoản nhân viên và quyền truy cập.</p><a href="{{ route('accounts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Thêm tài khoản</a></div>
<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table-custom mb-0"><thead><tr><th>Nhân viên</th><th>Email</th><th>Vai trò</th><th>Trạng thái</th><th>Đăng nhập gần nhất</th><th>Thao tác</th></tr></thead><tbody><tr><td><strong>Admin</strong></td><td>admin@giatui.com</td><td><span class="badge bg-primary">Quản trị viên</span></td><td><span class="badge-status badge-completed">Đang hoạt động</span></td><td>Hôm nay, 10:15</td><td><a href="{{ route('accounts.edit', 1) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a></td></tr><tr><td><strong>Nhân viên cửa hàng</strong></td><td>staff@giatui.com</td><td><span class="badge bg-secondary">Nhân viên</span></td><td><span class="badge-status badge-completed">Đang hoạt động</span></td><td>Hôm qua, 16:40</td><td><a href="{{ route('accounts.edit', 2) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a></td></tr></tbody></table></div></div></div>
@endsection
