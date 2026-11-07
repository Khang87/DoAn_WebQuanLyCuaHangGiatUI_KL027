@extends('layouts.app')

@section('title', 'Nhóm quyền - Sky Laundry')
@section('page-title', 'Nhóm quyền')

@section('content')
<div class="page-toolbar">
    @can('roles.manage')
        <a href="{{ route('roles.create') }}" class="btn btn-create">
            <i class="bi bi-plus-lg"></i>Thêm nhóm quyền
        </a>
    @endcan
    <p class="text-muted page-toolbar__desc">Xem quyền hạn và thành viên được phân vào từng nhóm.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="rounded-3 bg-primary-subtle text-primary p-3"><i class="bi bi-shield-lock fs-5"></i></span>
                <div><div class="small text-muted">Nhóm quyền</div><div class="fs-4 fw-semibold">{{ $metrics['roles'] }}</div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="rounded-3 bg-success-subtle text-success p-3"><i class="bi bi-key fs-5"></i></span>
                <div><div class="small text-muted">Quyền có thể cấp</div><div class="fs-4 fw-semibold">{{ $metrics['permissions'] }}</div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="rounded-3 bg-info-subtle text-info p-3"><i class="bi bi-people fs-5"></i></span>
                <div><div class="small text-muted">Tài khoản đã được phân nhóm</div><div class="fs-4 fw-semibold">{{ $metrics['assignedAccounts'] }}</div></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    @forelse($roles as $role)
        @php
            $builtInRoles = ['Chủ cửa hàng', 'Quản lý', 'Nhân viên', 'Khách hàng'];
            $isBuiltIn = in_array($role->TenVaiTro, $builtInRoles, true);
            $roleIcon = match ($role->TenVaiTro) {
                'Chủ cửa hàng' => 'bi-shield-fill-check',
                'Quản lý' => 'bi-person-gear',
                'Nhân viên' => 'bi-person-workspace',
                'Khách hàng' => 'bi-person',
                default => 'bi-people',
            };
            $visiblePermissions = $role->quyens;
        @endphp
        <div class="col-md-6 col-xl-4">
            <article class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start gap-3">
                        <span class="rounded-3 bg-primary-subtle text-primary p-3">
                            <i class="bi {{ $roleIcon }} fs-5" aria-hidden="true"></i>
                        </span>
                        <div class="flex-grow-1 min-w-0">
                            <h3 class="h6 fw-bold mb-2 text-break">{{ $role->TenVaiTro }}</h3>
                            <div class="d-flex align-items-center flex-wrap gap-2 small">
                                <span class="badge {{ $isBuiltIn ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info-emphasis' }}">
                                    {{ $isBuiltIn ? 'Có sẵn' : 'Tùy chỉnh' }}
                                </span>
                                <x-admin.status-badge
                                    :status="$role->TrangThai"
                                    :enum="\App\Enums\RecordStatus::class"
                                    size="px-2 py-1 text-xs"
                                    :label="$role->TrangThai === 'Hoạt động' ? 'Đang hoạt động' : $role->TrangThai"
                                />
                            </div>
                        </div>
                        @can('roles.manage')
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Thao tác nhóm {{ $role->TenVaiTro }}">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('roles.edit', $role->getKey()) }}#role-info"><i class="bi bi-pencil me-2"></i>Chỉnh sửa nhóm</a></li>
                                    @if(! $role->isOwner())
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <x-admin.detail.confirm-form
                                                :action="route('roles.destroy', $role)"
                                                title="Xóa nhóm quyền?"
                                                text="Hành động này sẽ xóa toàn bộ cấu hình quyền hạn của nhóm. Tài khoản đang thuộc nhóm này sẽ mất quyền hạn tương ứng."
                                                label="Xóa group"
                                                variant="btn-link text-danger dropdown-item"
                                                icon="bi bi-trash me-2"
                                            />
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        @endcan
                    </div>

                    <p class="text-muted small mt-3 mb-3 flex-grow-1">
                        {{ $role->MoTa ?: 'Chưa có mô tả cho nhóm quyền này.' }}
                    </p>

                    <div class="d-flex flex-wrap gap-3 border-top border-bottom py-3 mb-3 small">
                        <span><i class="bi bi-key text-primary me-1"></i><strong>{{ $role->permission_count }}</strong> quyền</span>
                        <span><i class="bi bi-people text-primary me-1"></i><strong>{{ $role->member_count }}</strong> người dùng</span>
                    </div>

                    <div class="small text-muted fw-semibold mb-2">Quyền hạn</div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @forelse($visiblePermissions as $permission)
                            <span class="badge rounded-pill bg-light text-dark border"
                                  data-permission-role="{{ $role->getKey() }}"
                                  data-permission-id="{{ $permission->getKey() }}">
                                {{ $permission->TenQuyen }}
                            </span>
                        @empty
                            @if($role->isOwner())
                                <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border">Toàn quyền hệ thống</span>
                            @else
                                <span class="small text-muted">Chưa được cấp quyền</span>
                            @endif
                        @endforelse
                    </div>

                    @can('roles.manage')
                        <a href="{{ route('roles.show', $role->getKey()) }}" class="btn btn-outline-primary btn-sm mt-auto">
                            Xem chi tiết nhóm <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    @endcan
                </div>
            </article>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-5">Chưa có nhóm quyền nào.</div>
            </div>
        </div>
    @endforelse
</div>

<x-admin.pagination :paginator="$roles" label="nhóm quyền" />
@endsection

@push('styles')
<style>
    /* Style cho nút xóa trong dropdown */
    [data-confirm-form] {
        width: 100%;
        text-align: left;
        border: none;
        background: transparent;
        padding: 0.375rem 1rem;
        color: #dc2626;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn[data-confirm-form]:hover,
    .btn[data-confirm-form]:focus,
    [data-confirm-form]:hover,
    [data-confirm-form]:focus {
        background: #fee2e2;
        color: #991b1b;
        outline: none;
    }
</style>
@endpush
@endsection
