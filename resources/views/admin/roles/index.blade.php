@extends('layouts.app')

@section('title', 'Phân quyền - Sky Laundry')
@section('page-title', 'Quản lý phân quyền')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-clockwise me-1"></i>Tải lại ma trận
    </a>
    <p class="text-muted page-toolbar__desc">
        Tích chọn ô vuông để cấp quyền cho từng vai trò. Các ô có nhãn
        <span class="badge bg-warning-subtle text-warning border border-warning">Chỉ Chủ cửa hàng</span>
        không thể cấp cho vai trò khác.
    </p>
</div>

<form action="{{ route('roles.update') }}" method="POST" id="rbacMatrixForm">
    @csrf
    @method('PUT')

    @foreach($roles as $role)
        <input type="hidden" name="role_slugs[]" value="{{ $role->slug }}">
    @endforeach

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 rbac-matrix">
                    <thead>
                        <tr>
                            <th class="rbac-matrix__module-col">
                                Quyền trên cơ sở dữ liệu
                            </th>
                            @foreach($roles as $role)
                            <th class="text-center rbac-matrix__role-col">
                                <div class="fw-semibold">{{ $role->name }}</div>
                                <div class="small text-muted fw-normal">{{ $role->description }}</div>
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($permissions as $permission)
                            @php
                                $ownerOnlyPermission = in_array($permission->MaQuyen, $ownerOnly, true);
                                $permissionActive = $permission->TrangThai === 'Hoạt động';
                            @endphp
                            <tr>
                                <td class="rbac-matrix__label">
                                    <div class="fw-semibold">{{ $permission->TenQuyen }}</div>
                                    <div class="small text-muted"><code>{{ $permission->MaQuyen }}</code></div>
                                    @if($permission->MoTa)
                                        <div class="small text-muted">{{ $permission->MoTa }}</div>
                                    @endif
                                    @if($ownerOnlyPermission)
                                        <span class="badge bg-warning-subtle text-warning border border-warning">
                                            Chỉ Chủ cửa hàng
                                        </span>
                                    @endif
                                    @unless($permissionActive)
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary">
                                            Ngừng hoạt động
                                        </span>
                                    @endunless
                                </td>

                                @foreach($roles as $role)
                                    @php
                                        $checked = $role->quyens->contains(
                                            fn ($grantedPermission) => (int) $grantedPermission->QuyenID === (int) $permission->QuyenID
                                        );
                                    @endphp
                                    <td class="text-center">
                                        @if($role->isOwner())
                                            <input type="checkbox" class="form-check-input" checked disabled
                                                   title="Chủ cửa hàng luôn có toàn bộ quyền">
                                        @elseif($ownerOnlyPermission)
                                            <input type="checkbox" class="form-check-input" disabled
                                                   title="Chỉ Chủ cửa hàng được cấp quyền này">
                                            <i class="bi bi-lock-fill small text-muted d-block mt-1"></i>
                                        @elseif(! $permissionActive)
                                            <input type="checkbox" class="form-check-input" disabled
                                                   @checked($checked)
                                                   title="Quyền đang ngừng hoạt động; trạng thái hiện tại được giữ nguyên">
                                        @else
                                            <input type="checkbox" class="form-check-input js-rbac-check"
                                                   name="quyen_ids[{{ $role->slug }}][]"
                                                   value="{{ $permission->QuyenID }}"
                                                   @checked($checked)>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="form-text mb-0">
                Chủ cửa hàng luôn có toàn bộ quyền, kể cả quyền bị khoá.
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="rbacReset">
                    <i class="bi bi-x-circle me-1"></i>Bỏ chọn tất cả
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Lưu thay đổi
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.getElementById('rbacReset')?.addEventListener('click', function () {
        document.querySelectorAll('#rbacMatrixForm .js-rbac-check').forEach(function (box) {
            box.checked = false;
        });
    });
</script>
@endpush
