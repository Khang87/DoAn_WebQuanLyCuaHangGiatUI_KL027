@extends('layouts.app')

@section('title', 'Quản Lý Tài Khoản - Sky Laundry')
@section('page-title', 'Quản Lý Tài Khoản & Phân Quyền')

@section('content')
<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('accounts.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>Thêm Tài Khoản Mới
        </a>
    </div>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm tên, email, SĐT..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="role" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả vai trò --</option>
            <option value="admin" @selected(request('role') === 'admin')>Quản trị viên</option>
            <option value="staff" @selected(request('role') === 'staff')>Nhân viên</option>
            <option value="customer" @selected(request('role') === 'customer')>Khách hàng</option>
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 200px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="active" @selected(request('status') === 'active')>Đang hoạt động</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Đã khóa</option>
        </select>
    </div>
</form>

<!-- Accounts Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Người Dùng</th>
                        <th>Email / SĐT</th>
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
                        <td>
                            {{ $account->email }}
                            @if($account->phone)
                                <br><small class="text-muted">{{ $account->phone }}</small>
                            @endif
                        </td>
                        <td>
                            @if($account->role === 'admin')
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-shield-alt me-1"></i>Quản trị viên</span>
                            @elseif($account->role === 'staff')
                                <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-user-tie me-1"></i>Nhân viên</span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-user me-1"></i>Khách hàng</span>
                            @endif
                        </td>
                        <td>
                            @if($account->deleted_at)
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Khóa</span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
                            @endif
                        </td>
                        <td>{{ $account->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('accounts.show', $account->id) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @can('update', $account)
                                <a href="{{ route('accounts.edit', $account->id) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('update', $account)
                                <form action="{{ route('accounts.toggle-status', $account->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn {{ $account->deleted_at ? 'kích hoạt' : 'khóa' }} tài khoản này?')">
                                    @csrf
                                    <button type="submit" class="btn btn-order-action {{ $account->deleted_at ? 'view' : 'delete' }}" title="{{ $account->deleted_at ? 'Kích hoạt' : 'Khóa' }}">
                                        <i class="bi bi-{{ $account->deleted_at ? 'unlock' : 'lock' }}"></i>
                                    </button>
                                </form>
                                @endcan
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

@if($accounts->hasPages())
<div class="mt-3">
    {{ $accounts->appends(request()->query())->links('pagination::bootstrap-5') }}
</div>
@endif
@endsection
