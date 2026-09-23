@extends('layouts.app')

@section('title', 'Quản Lý Tài Khoản - Giặt Ủi Pro')
@section('page-title', 'Quản Lý Tài Khoản & Phân Quyền')

@section('content')
<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('accounts.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>Thêm Tài Khoản Mới
        </a>
    </div>
    <form action="{{ route('accounts.index') }}" method="GET" class="d-flex gap-2">
        <div class="input-group" style="width: 260px;">
            <input type="text" name="search" class="form-control" placeholder="Tìm tên, email..." value="{{ request('search') }}">
            <button class="btn btn-outline-secondary" type="submit">
                <i class="bi bi-search"></i>
            </button>
        </div>
        <select name="role" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả vai trò</option>
            <option value="admin" @selected(request('role') === 'admin')>Quản trị viên</option>
            <option value="staff" @selected(request('role') === 'staff')>Nhân viên</option>
            <option value="customer" @selected(request('role') === 'customer')>Khách hàng</option>
        </select>
    </form>
</div>

<!-- Accounts Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Người Dùng</th>
                        <th>Email</th>
                        <th>Vai Trò Phân Quyền</th>
                        <th>Trạng Thái</th>
                        <th>Ngày Tạo</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="{{ asset('assets/images/avatar.png') }}" alt="Avatar" class="rounded-circle me-2" style="width: 36px; height: 36px; object-fit: cover;" onerror="this.src='https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=256&auto=format&fit=crop'">
                                <strong class="fw-semibold">{{ $account->name }}</strong>
                            </div>
                        </td>
                        <td>{{ $account->email }}</td>
                        <td>
                            @if($account->role === 'admin')
                                <span class="badge bg-danger"><i class="bi bi-shield-lock-fill me-1"></i>Quản trị viên</span>
                            @elseif($account->role === 'staff')
                                <span class="badge bg-info text-dark"><i class="bi bi-person-badge-fill me-1"></i>Nhân viên</span>
                            @else
                                <span class="badge bg-success"><i class="bi bi-person-fill me-1"></i>Khách hàng</span>
                            @endif
                        </td>
                        <td><span class="badge-status badge-completed">Đang hoạt động</span></td>
                        <td>{{ $account->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('accounts.show', $account->id) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('accounts.edit', $account->id) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                @if((int) $account->id !== (int) auth()->id())
                                <form action="{{ route('accounts.destroy', $account->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa tài khoản này?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Chưa có tài khoản nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
