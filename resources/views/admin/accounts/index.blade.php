@extends('layouts.app')

@section('title', 'Quản lý tài khoản - Sky Laundry')
@section('page-title', 'Quản lý tài khoản & Phân quyền')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    @can('accounts.create')
        <a href="{{ route('accounts.create') }}" class="btn btn-create">
            <i class="bi bi-plus-lg"></i>Thêm tài khoản
        </a>
    @endcan
    <p class="text-muted page-toolbar__desc">Quản lý tài khoản đăng nhập, bao gồm người dùng liên kết Google và quyền truy cập.</p>
</div>
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
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
        <select name="role_id" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">Tất cả vai trò</option>
            @foreach($roles as $role)
                <option value="{{ $role->VaiTroID }}" @selected((string) request('role_id') === (string) $role->VaiTroID)>{{ $role->TenVaiTro }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="$statuses ?? \App\Enums\RecordStatus::options()"
            placeholder="Tất cả trạng thái"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">Tất cả cách sắp xếp</option>
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
                        <th class="fw-bold text-dark">Tên / Email</th>
                        <th class="fw-bold text-dark">Phương thức đăng nhập</th>
                        <th class="fw-bold text-dark">Vai trò hiện tại</th>
                        <th class="fw-bold text-dark">Trạng thái</th>
                        <th class="fw-bold text-dark">Ngày tạo</th>
                        <th class="fw-bold text-dark">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                    <tr>
                        <td>{{ $accounts->firstItem() + $loop->index }}</td>
                        <td>
                            <strong class="fw-semibold text-dark">{{ $account->name }}</strong>
                            <br><small class="text-muted">{{ $account->Email }}</small>
                        </td>
                        <td>
                            @if($account->UserAuthId)
                                <span class="badge bg-primary-subtle text-primary-emphasis border border-primary">Google</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary">Mặc định</span>
                            @endif
                        </td>
                        <td>
                            @forelse($account->vaiTros as $role)
                                @php
                                    $roleBadgeClass = match ((int) $role->VaiTroID) {
                                        1 => 'bg-danger-subtle text-danger-emphasis border border-danger',
                                        2 => 'bg-warning-subtle text-warning-emphasis border border-warning',
                                        3 => 'bg-primary-subtle text-primary-emphasis border border-primary',
                                        default => 'bg-success-subtle text-success-emphasis border border-success',
                                    };
                                @endphp
                                <span class="badge {{ $roleBadgeClass }} me-1 mb-1">{{ $role->TenVaiTro }}</span>
                            @empty
                                <span class="text-muted">Chưa gán vai trò</span>
                            @endforelse
                        </td>
                        <td>
                            <span class="badge {{ $account->TrangThai === 'Hoạt động' ? 'bg-success-subtle text-success-emphasis border border-success' : 'bg-secondary-subtle text-secondary-emphasis border border-secondary' }}">
                                {{ $account->TrangThai }}
                            </span>
                        </td>
                        <td>{{ $account->NgayTao?->format('d/m/Y') }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                @can('accounts.view')
                                    <a href="{{ route('accounts.show', $account->getKey()) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @endcan
                                @can('accounts.edit')
                                    @can('update', $account)
                                    <a href="{{ route('accounts.edit', $account->getKey()) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                    @endcan
                                @endcan
                                @if($canManageRoles && auth()->id() !== $account->getKey())
                                    <button
                                        type="button"
                                        class="btn btn-order-action edit"
                                        title="Đổi vai trò"
                                        data-bs-toggle="modal"
                                        data-bs-target="#updateRoleModal"
                                        data-account-name="{{ $account->name }}"
                                        data-account-roles="{{ $account->vaiTros->pluck('VaiTroID')->implode(',') }}"
                                        data-update-url="{{ route('accounts.update-role', $account->getKey()) }}"
                                    ><i class="bi bi-shield-lock"></i></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Chưa có tài khoản nào.</td>
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

@if($canManageRoles)
<div class="modal fade" id="updateRoleModal" tabindex="-1" aria-labelledby="updateRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="updateRoleForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="updateRoleModalLabel">Đổi vai trò tài khoản</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p>Chọn vai trò mới cho <strong id="roleAccountName"></strong>:</p>
                    @foreach($roles as $role)
                        <div class="form-check mb-2">
                            <input class="form-check-input role-option" type="checkbox" name="vai_tro_ids[]" value="{{ $role->VaiTroID }}" id="role-{{ $role->VaiTroID }}">
                            <label class="form-check-label" for="role-{{ $role->VaiTroID }}">{{ $role->TenVaiTro }}</label>
                        </div>
                    @endforeach
                    <small class="text-muted">Đổi vai trò sẽ đăng xuất các phiên hiện tại của tài khoản để áp dụng quyền mới.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('updateRoleModal');

    if (!modal) {
        return;
    }

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const selectedRoles = (button.dataset.accountRoles || '')
            .split(',')
            .filter(Boolean);

        document.getElementById('roleAccountName').textContent = button.dataset.accountName;
        document.getElementById('updateRoleForm').action = button.dataset.updateUrl;

        modal.querySelectorAll('.role-option').forEach(function (checkbox) {
            checkbox.checked = selectedRoles.includes(checkbox.value);
        });
    });
});
</script>
@endpush