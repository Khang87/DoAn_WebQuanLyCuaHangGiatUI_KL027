@extends('layouts.app')

@php
    $assignedPermissionIds = $role->quyens->pluck('QuyenID')->map(fn ($id) => (int) $id);
    $members = $role->taiKhoans;
@endphp

@section('title', 'Chi tiết nhóm quyền - Sky Laundry')
@section('page-title', $role->TenVaiTro)

@section('content')
<x-admin.detail.page-header
    :title="$role->TenVaiTro"
    subtitle="Chi tiết nhóm quyền — chỉ xem. Muốn đổi thông tin, quyền hoặc thành viên thì dùng menu thao tác ở danh sách nhóm."
    backUrl="{{ route('roles.index') }}">
    <x-slot:badge>
        <span class="badge {{ $role->isOwner() ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info-emphasis' }}">
            {{ in_array($role->TenVaiTro, ['Chủ cửa hàng', 'Quản lý', 'Nhân viên', 'Khách hàng'], true) ? 'Có sẵn' : 'Tùy chỉnh' }}
        </span>
        <span class="badge {{ $role->TrangThai === 'Hoạt động' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary' }}">
            {{ $role->TrangThai }}
        </span>
    </x-slot:badge>
</x-admin.detail.page-header>

<x-admin.detail.panel title="Thông tin nhóm" icon="bi-info-circle">
    <x-admin.detail.info-grid :columns="2">
        <x-admin.detail.info-item label="Tên nhóm quyền" :value="$role->TenVaiTro" />
        <x-admin.detail.info-item label="Số thành viên" :value="$members->count().' tài khoản'" />
        <x-admin.detail.info-item label="Mô tả" :value="$role->MoTa" class="col-12" />
    </x-admin.detail.info-grid>
</x-admin.detail.panel>

<x-admin.detail.panel title="Quyền của nhóm" icon="bi-key">
    @if($role->isOwner())
        <div class="alert alert-info mb-3">
            <i class="bi bi-shield-check me-1"></i>Chủ cửa hàng luôn có toàn quyền hệ thống.
        </div>
    @endif
    <div class="row g-3">
        @forelse($permissionGroups as $module => $permissions)
            <div class="col-12 col-xl-6">
                <section class="border rounded-3 h-100" aria-label="{{ $module }}">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-light border-bottom rounded-top">
                        <h4 class="small fw-bold mb-0">{{ $module }}</h4>
                        <span class="badge bg-white text-secondary border">{{ $permissions->count() }}</span>
                    </div>
                    <div class="p-3">
                        @foreach($permissions as $permission)
                            @php
                                $state = \App\Support\RolePermissionDisplay::state($permission, $role, $assignedPermissionIds);
                            @endphp
                            <div class="d-flex align-items-start gap-2 py-2 {{ ! $loop->last ? 'border-bottom' : '' }} {{ $state['granted'] ? '' : 'opacity-50' }}">
                                <i class="bi {{ $state['granted'] ? 'bi-check-circle-fill text-success' : 'bi-dash-circle text-secondary' }} mt-1 flex-shrink-0" aria-hidden="true"></i>
                                <span class="min-w-0">
                                    <span class="d-block fw-medium">{{ $permission->TenQuyen }}</span>
                                    <span class="small text-muted"><code>{{ $permission->MaQuyen }}</code></span>
                                    @if($state['default'])
                                        <span class="badge bg-primary-subtle text-primary mt-1">Mặc định cho nhóm nội bộ</span>
                                    @endif
                                    @if($state['excluded'])
                                        <span class="badge bg-secondary-subtle text-secondary mt-1">{{ $state['mobileOnly'] ? 'Chỉ dành cho mobile; bị chặn trên quản trị' : 'Khách hàng không dùng trên quản trị' }}</span>
                                    @endif
                                    @if($state['ownerOnly'])
                                        <span class="badge bg-warning-subtle text-warning-emphasis mt-1">Chỉ Chủ cửa hàng</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        @empty
            <div class="col-12 text-center text-muted py-4">Chưa có quyền hoạt động trong danh mục.</div>
        @endforelse
    </div>
</x-admin.detail.panel>

<x-admin.detail.panel title="Những người thuộc nhóm này" icon="bi-people">
    @if($members->isEmpty())
        <div class="text-center text-muted py-4">
            <i class="bi bi-person-dash fs-3 d-block mb-2"></i>
            Chưa có tài khoản nào thuộc nhóm quyền này.
        </div>
    @else
        <div class="list-group list-group-flush">
            @foreach($members as $member)
                @php
                    $memberName = $member->nhanVien?->HoTen ?: $member->khachHang?->HoTen ?: $member->TenDangNhap;
                @endphp
                <div class="list-group-item px-0 d-flex align-items-center gap-3">
                    <x-admin.customer-avatar
                        :url="$member->getAttributes()['AvatarURL'] ?? $member->khachHang?->getAttributes()['AvatarUrl'] ?? null"
                        :name="$memberName"
                        :size="44" />
                    <div class="min-w-0">
                        <div class="fw-semibold text-truncate">{{ $memberName }}</div>
                        <div class="small text-muted text-break">{{ $member->Email ?: $member->TenDangNhap }}</div>
                        @if($member->Email && $member->TenDangNhap !== $member->Email)
                            <div class="small text-muted">{{ $member->TenDangNhap }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-admin.detail.panel>
@endsection
