@extends('layouts.app')

@section('title', 'Quyền hạn - Sky Laundry')
@section('page-title', 'Danh mục quyền hạn')

@section('content')
<div class="page-toolbar d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Danh mục quyền do ứng dụng cấu hình. Gán các quyền có sẵn cho từng nhóm quyền tại trang Nhóm quyền.</p>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">STT</th>
                    <th>Nhóm chức năng</th>
                    <th>Mã quyền</th>
                    <th>Tên quyền</th>
                    <th>Mô tả</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                @forelse($permissions as $permission)
                    @php($module = \Illuminate\Support\Str::headline(strtolower(explode('_', $permission->MaQuyen)[0])))
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $module }}</td>
                        <td><code>{{ $permission->MaQuyen }}</code></td>
                        <td>{{ $permission->TenQuyen }}</td>
                        <td>{{ $permission->MoTa ?: '—' }}</td>
                        <td>
                            <x-admin.status-badge
                                :status="$permission->TrangThai"
                                :enum="\App\Enums\RecordStatus::class"
                                size="px-2 py-1 text-xs"
                                :label="$permission->TrangThai === 'Hoạt động' ? 'Đang hoạt động' : $permission->TrangThai"
                            />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có quyền hạn.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
