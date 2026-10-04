@extends('layouts.app')

@section('title', 'Quyền hạn - Sky Laundry')
@section('page-title', 'Danh mục quyền hạn')

@section('content')
<div class="page-toolbar d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Danh mục quyền do ứng dụng cấu hình. Gán các quyền có sẵn cho từng nhóm quyền tại trang Nhóm quyền.</p>
    <div class="d-flex gap-2">
        <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary">Nhóm quyền</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nhóm chức năng</th>
                    <th>Mã quyền</th>
                    <th>Tên quyền</th>
                    <th>Mô tả</th>
                    <th>Nhóm được cấp</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                @forelse($permissions as $permission)
                    @php($module = \Illuminate\Support\Str::headline(strtolower(explode('_', $permission->MaQuyen)[0])))
                    <tr>
                        <td>{{ $module }}</td>
                        <td><code>{{ $permission->MaQuyen }}</code></td>
                        <td>{{ $permission->TenQuyen }}</td>
                        <td>{{ $permission->MoTa ?: '—' }}</td>
                        <td>{{ $permission->vai_tros_count }}</td>
                        <td><span class="badge {{ $permission->TrangThai === 'Hoạt động' ? 'bg-success-subtle text-success border' : 'bg-secondary-subtle text-secondary border' }}">{{ $permission->TrangThai }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có quyền hạn.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
