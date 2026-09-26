@extends('layouts.app')

@section('title', 'Quản lý tài khoản - Sky Laundry')
@section('page-title', 'Quản lý tài khoản & Phân quyền')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('accounts.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm tài khoản
    </a>
    <p class="text-muted page-toolbar__desc">Quản lý tài khoản nhân viên và chủ cửa hàng, bao gồm trạng thái và quyền truy cập.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm tên, email, SĐT..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="role" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">-- Tất cả vai trò --</option>
            <option value="manager" @selected(request('role') === 'manager' || request('role') === 'admin')>Quản lý</option>
            <option value="staff" @selected(request('role') === 'staff')>Nhân viên</option>
            <option value="customer" @selected(request('role') === 'customer')>Khách hàng</option>
        </select>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="$statuses ?? \App\Enums\RecordStatus::options()"
            placeholder="-- Tất cả trạng thái --"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">-- Tất cả cách sắp xếp --</option>
            <option value="latest" @selected(request('sort') === 'latest')>Mới nhất</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>Cũ nhất</option>
            <option value="name_asc" @selected(request('sort') === 'name_asc')>Tên A-Z</option>
            <option value="name_desc" @selected(request('sort') === 'name_desc')>Tên Z-A</option>
            <option value="email_asc" @selected(request('sort') === 'email_asc')>Email A-Z</option>
            <option value="email_desc" @selected(request('sort') === 'email_desc')>Email Z-A</option>
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
                        <th class="fw-bold text-dark">STT</th>
                        <th class="fw-bold text-dark">Mã nhân viên</th>
                        <th class="fw-bold text-dark">Người dùng</th>
                        <th class="fw-bold text-dark">Email / SĐT</th>
                        <th class="fw-bold text-dark">Vai trò phân quyền</th>
                        <th class="fw-bold text-dark">Trạng thái</th>
                        <th class="fw-bold text-dark">Ngày tạo</th>
                        <th class="fw-bold text-dark">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $account->code ?? 'TK' . str_pad($account->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="{{ $account->avatar_url }}" alt="Avatar" class="rounded-circle me-2 avatar-cover" style="width: 36px; height: 36px;">
                                <strong class="fw-semibold text-dark">{{ $account->name }}</strong>
                            </div>
                        </td>
                        <td>
                            {{ $account->email }}
                            @if($account->phone)
                                <br><small class="text-muted">{{ $account->phone }}</small>
                            @endif
                        </td>
                        <td>
                            @if($account->isManager())
                                <span class="badge bg-danger-subtle text-danger-emphasis border border-danger px-3 py-2 rounded-pill"><i class="fas fa-shield-alt me-1"></i>Quản lý</span>
                            @elseif($account->role === 'staff')
                                <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill"><i class="fas fa-user-tie me-1"></i>Nhân viên</span>
                            @else
                                <span class="badge bg-success-subtle text-success-emphasis border border-success px-3 py-2 rounded-pill"><i class="fas fa-user me-1"></i>Khách hàng</span>
                            @endif
                        </td>
                        <td>
                            <x-admin.status-badge
                                :status="$account->deleted_at ? 'inactive' : 'active'"
                                :enum="\App\Enums\RecordStatus::class"
                            />
                        </td>
                        <td>{{ $account->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('accounts.show', $account->id) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @can('update', $account)
                                <a href="{{ route('accounts.edit', $account->id) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Chưa có tài khoản nào.</td>
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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
    });
</script>
@endpush