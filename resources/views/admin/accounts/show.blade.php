@extends('layouts.app')

@section('title', 'Chi Tiết Tài Khoản - Giặt Ủi Pro')
@section('page-title', 'Chi Tiết Tài Khoản')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <img src="{{ asset('assets/images/avatar.png') }}" alt="Avatar" class="rounded-circle me-3" style="width: 52px; height: 52px; object-fit: cover;" onerror="this.src='https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=256&auto=format&fit=crop'">
                        <div>
                            <h5 class="mb-0 fw-bold">{{ $account->name }}</h5>
                            <small class="text-muted">{{ $account->email }}</small>
                        </div>
                    </div>
                    @if($account->role === 'admin')
                        <span class="badge bg-danger fs-6"><i class="bi bi-shield-lock-fill me-1"></i>Quản trị viên</span>
                    @elseif($account->role === 'staff')
                        <span class="badge bg-info text-dark fs-6"><i class="bi bi-person-badge-fill me-1"></i>Nhân viên</span>
                    @else
                        <span class="badge bg-success fs-6"><i class="bi bi-person-fill me-1"></i>Khách hàng</span>
                    @endif
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Họ tên</div>
                        <div class="fw-semibold">{{ $account->name }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Email đăng nhập</div>
                        <div class="fw-semibold text-primary">{{ $account->email }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Vai trò phân quyền</div>
                        <div class="fw-semibold">
                            {{ $account->role === 'admin' ? 'Quản trị viên (Admin)' : ($account->role === 'staff' ? 'Nhân viên (Staff)' : 'Khách hàng (Customer)') }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Ngày tạo tài khoản</div>
                        <div class="fw-semibold">{{ $account->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Thao tác</h6>
                <div class="d-grid gap-2">
                    <a href="{{ route('accounts.edit', $account->id) }}" class="btn btn-warning">Chỉnh sửa</a>
                    @if((int) $account->id !== (int) auth()->id())
                    <form action="{{ route('accounts.destroy', $account->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa tài khoản này?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">Xóa tài khoản</button>
                    </form>
                    @endif
                    <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">Quay lại danh sách</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
