@extends('layouts.app')

@php
    $editing = isset($role);
    $assignedPermissionIds = $editing
        ? $role->quyens->pluck('QuyenID')->map(fn ($id) => (int) $id)
        : collect();
@endphp

@section('title', $editing ? 'Chi tiết nhóm quyền - Sky Laundry' : 'Thêm nhóm quyền - Sky Laundry')
@section('page-title', $editing ? $role->TenVaiTro : 'Thêm nhóm quyền')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="h5 mb-1">{{ $editing ? 'Chi tiết và quản lý nhóm quyền' : 'Tạo nhóm quyền mới' }}</h2>
        @if($editing)
            <span class="badge {{ $role->isOwner() ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info-emphasis' }}">
                {{ in_array($role->TenVaiTro, ['Chủ cửa hàng', 'Quản lý', 'Nhân viên', 'Khách hàng'], true) ? 'Có sẵn' : 'Tùy chỉnh' }}
            </span>
            <span class="badge {{ $role->TrangThai === 'Hoạt động' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary' }}">
                {{ $role->TrangThai }}
            </span>
        @endif
    </div>
    <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Danh sách nhóm quyền
    </a>
</div>

<div class="card border-0 shadow-sm mb-4" id="role-info">
    <div class="card-header bg-white py-3">
        <h3 class="h6 fw-bold mb-0"><i class="bi bi-info-circle text-primary me-2"></i>Thông tin nhóm</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ $editing ? route('roles.update-role', $role->getKey()) : route('roles.store') }}">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <div class="mb-3">
                <label class="form-label" for="TenVaiTro">Tên nhóm quyền <span class="text-danger">*</span></label>
                <input class="form-control @error('TenVaiTro') is-invalid @enderror" id="TenVaiTro"
                       name="TenVaiTro" value="{{ old('TenVaiTro', $role->TenVaiTro ?? '') }}"
                       maxlength="100" required @disabled($editing && $role->isOwner())>
                @if($editing && $role->isOwner())
                    <input type="hidden" name="TenVaiTro" value="{{ $role->TenVaiTro }}">
                    <div class="form-text">Tên vai trò Chủ cửa hàng được bảo vệ.</div>
                @endif
                @error('TenVaiTro')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="MoTa">Mô tả</label>
                <textarea class="form-control @error('MoTa') is-invalid @enderror" id="MoTa"
                          name="MoTa" maxlength="255" rows="3">{{ old('MoTa', $role->MoTa ?? '') }}</textarea>
                @error('MoTa')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @if($editing)
                <div class="mb-3">
                    <label class="form-label" for="TrangThai">Trạng thái</label>
                    <select class="form-select" id="TrangThai" name="TrangThai" @disabled($role->isOwner())>
                        @foreach(['Hoạt động', 'Ngừng hoạt động'] as $status)
                            <option value="{{ $status }}" @selected(old('TrangThai', $role->TrangThai) === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    @if($role->isOwner())
                        <input type="hidden" name="TrangThai" value="Hoạt động">
                    @endif
                </div>
            @endif
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i>{{ $editing ? 'Lưu thông tin' : 'Tạo nhóm quyền' }}
            </button>
        </form>
    </div>
</div>

@if($editing)
    <section class="card border-0 shadow-sm mb-4" id="permission-panel">
        <div class="card-header bg-white d-flex justify-content-between align-items-center gap-3 py-3">
            <div>
                <h3 class="h6 fw-bold mb-1"><i class="bi bi-key text-primary me-2"></i>Quyền của nhóm</h3>
                <p class="small text-muted mb-0">Chọn các chức năng mà thành viên của nhóm được phép sử dụng.</p>
            </div>
            <span class="badge bg-primary-subtle text-primary">{{ $role->isOwner() ? $permissionGroups->flatten(1)->count() : $assignedPermissionIds->count() }} quyền</span>
        </div>
        <div class="card-body">
            @if($role->isOwner())
                <div class="alert alert-info mb-0">
                    <i class="bi bi-shield-check me-1"></i>Chủ cửa hàng luôn có toàn quyền hệ thống; danh sách quyền được khóa để bảo vệ tài khoản quản trị.
                </div>
            @else
                @if($role->TrangThai !== 'Hoạt động')
                    <div class="alert alert-warning">
                        Nhóm đã ngừng hoạt động. Hãy kích hoạt nhóm trước khi thay đổi quyền.
                    </div>
                @endif

                <form method="POST" action="{{ route('roles.permissions.update', $role->getKey()) }}">
                    @csrf
                    @method('PUT')

                    @error('permission_ids')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

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
                                                $permissionId = (int) $permission->getKey();
                                                $isOwnerOnly = in_array($permission->MaQuyen, $ownerOnly ?? [], true);
                                                $isDefault = $permission->MaQuyen === \App\Services\DefaultNotificationPermission::CODE;
                                                $isChecked = $isDefault || $assignedPermissionIds->contains($permissionId);
                                            @endphp
                                            <label class="d-flex align-items-start gap-2 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                                                <input class="form-check-input mt-1 flex-shrink-0" type="checkbox"
                                                       name="permission_ids[]" value="{{ $permissionId }}"
                                                       @checked($isChecked)
                                                       @disabled($isDefault || $isOwnerOnly || $role->TrangThai !== 'Hoạt động')>
                                                <span class="min-w-0">
                                                    <span class="d-block fw-medium">{{ $permission->TenQuyen }}</span>
                                                    <span class="small text-muted"><code>{{ $permission->MaQuyen }}</code></span>
                                                    @if($permission->MoTa)
                                                        <span class="d-block small text-muted">{{ $permission->MoTa }}</span>
                                                    @endif
                                                    @if($isDefault)
                                                        <span class="badge bg-primary-subtle text-primary mt-1">Mặc định cho mọi nhóm</span>
                                                    @endif
                                                    @if($isOwnerOnly)
                                                        <span class="badge bg-warning-subtle text-warning-emphasis mt-1">Chỉ Chủ cửa hàng</span>
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </section>
                            </div>
                        @empty
                            <div class="col-12 text-center text-muted py-4">Chưa có quyền hoạt động trong danh mục.</div>
                        @endforelse
                    </div>

                    <button type="submit" class="btn btn-primary mt-3" @disabled($role->TrangThai !== 'Hoạt động')>
                        <i class="bi bi-check-lg me-1"></i>Lưu quyền nhóm
                    </button>
                </form>
            @endif
        </div>
    </section>

    @php
        $members = $role->taiKhoans;
        $addMemberModalId = 'addRoleMemberModal';
    @endphp
    <section class="card border-0 shadow-sm" id="member-panel">
        <div class="card-header bg-white d-flex justify-content-between align-items-center gap-3 py-3">
            <div>
                <h3 class="h6 fw-bold mb-1"><i class="bi bi-people text-primary me-2"></i>Những người thuộc nhóm này</h3>
                <p class="small text-muted mb-0">{{ $members->count() }} tài khoản được phân vào nhóm.</p>
            </div>
            @if(! $role->isOwner() && $role->TrangThai === 'Hoạt động')
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#{{ $addMemberModalId }}">
                    <i class="bi bi-person-plus me-1"></i>Thêm người
                </button>
            @endif
        </div>
        <div class="card-body">
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
                            $memberInitials = mb_strtoupper(mb_substr($memberName, 0, 1));
                        @endphp
                        <div class="list-group-item px-0 d-flex align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="rounded-circle bg-primary-subtle text-primary fw-semibold d-flex align-items-center justify-content-center flex-shrink-0"
                                     style="width: 44px; height: 44px;" aria-hidden="true">
                                    {{ $memberInitials ?: '?' }}
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $memberName }}</div>
                                    <div class="small text-muted text-break">{{ $member->Email ?: $member->TenDangNhap }}</div>
                                    @if($member->Email && $member->TenDangNhap !== $member->Email)
                                        <div class="small text-muted">{{ $member->TenDangNhap }}</div>
                                    @endif
                                </div>
                            </div>
                            @if(! $role->isOwner())
                                <x-admin.detail.confirm-form
                                    :action="route('roles.members.destroy', [$role->getKey(), $member->getKey()])"
                                    method="DELETE"
                                    title="Gỡ {{ $memberName }} khỏi nhóm?"
                                    text="Cảnh báo: tài khoản này sẽ mất quyền truy cập được cấp thông qua nhóm {{ $role->TenVaiTro }}. Tài khoản không bị xóa."
                                    label="Gỡ thành viên"
                                    icon="bi-person-dash"
                                    variant="btn-outline-danger"
                                    color="#dc2626"
                                    iconName="warning"
                                >
                                    Gỡ
                                </x-admin.detail.confirm-form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if(! $role->isOwner() && $role->TrangThai === 'Hoạt động')
        <div class="modal fade" id="{{ $addMemberModalId }}" tabindex="-1" aria-labelledby="{{ $addMemberModalId }}Label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('roles.members.store', $role->getKey()) }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="{{ $addMemberModalId }}Label">Thêm người vào nhóm</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                        </div>
                        <div class="modal-body">
                            <label for="TaiKhoanID" class="form-label">Tài khoản đang hoạt động</label>
                            <select class="form-select @error('TaiKhoanID') is-invalid @enderror" id="TaiKhoanID" name="TaiKhoanID" required>
                                <option value="">Chọn tài khoản</option>
                                @foreach($availableAccounts as $account)
                                    @php
                                        $accountName = $account->nhanVien?->HoTen ?: $account->khachHang?->HoTen ?: $account->TenDangNhap;
                                    @endphp
                                    <option value="{{ $account->getKey() }}" @selected((string) old('TaiKhoanID') === (string) $account->getKey())>
                                        {{ $accountName }} · {{ $account->Email ?: $account->TenDangNhap }}
                                    </option>
                                @endforeach
                            </select>
                            @error('TaiKhoanID')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if($availableAccounts->isEmpty())
                                <div class="form-text">Không còn tài khoản đang hoạt động để thêm vào nhóm này.</div>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                            <button type="submit" class="btn btn-primary" @disabled($availableAccounts->isEmpty())>
                                <i class="bi bi-person-plus me-1"></i>Thêm vào nhóm
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endif
@endsection

@if($errors->has('TaiKhoanID'))
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('addRoleMemberModal')).show();
            });
        </script>
    @endpush
@endif
