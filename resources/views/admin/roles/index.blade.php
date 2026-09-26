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

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 rbac-matrix">
                    <thead>
                        <tr>
                            <th class="rbac-matrix__module-col">
                                Quyền hạn
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
                        @foreach($groups as $group)
                            <tr class="rbac-matrix__group-row">
                                <th colspan="{{ $roles->count() + 1 }}" class="text-uppercase small fw-bold text-secondary">
                                    <i class="bi bi-folder2-open me-1"></i>{{ $group }}
                                </th>
                            </tr>

                            @foreach($matrix[$group] ?? [] as $code => $meta)
                                <tr>
                                    <td class="rbac-matrix__label">
                                        <div class="fw-semibold">{{ $meta['name'] }}</div>
                                        <div class="small text-muted"><code>{{ $code }}</code></div>
                                        @if($meta['owner_only'])
                                            <span class="badge bg-warning-subtle text-warning border border-warning">
                                                Chỉ Chủ cửa hàng
                                            </span>
                                        @endif
                                    </td>

                                    @foreach($roles as $role)
                                        @php
                                            $checked = in_array($role->slug, $meta['roles'], true);
                                            $locked = $meta['owner_only'] && ! $role->isOwner();
                                            $isOwnerRole = $role->isOwner();
                                        @endphp
                                        <td class="text-center">
                                            @if($locked)
                                                <input type="checkbox" class="form-check-input" disabled
                                                       title="Chỉ Chủ cửa hàng được cấp quyền này">
                                                <i class="bi bi-lock-fill small text-muted d-block mt-1"></i>
                                            @elseif($isOwnerRole)
                                                <input type="checkbox" class="form-check-input" checked disabled
                                                       title="Chủ cửa hàng luôn có toàn bộ quyền">
                                            @else
                                                <input type="checkbox" class="form-check-input js-rbac-check"
                                                       name="permissions[{{ $role->slug }}][]"
                                                       value="{{ $code }}"
                                                       @checked($checked)>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
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
