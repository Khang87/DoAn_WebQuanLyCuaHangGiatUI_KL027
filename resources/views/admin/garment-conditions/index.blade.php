@extends('layouts.app')

@section('title', 'Điều kiện đồ giặt - Sky Laundry')
@section('page-title', 'Điều kiện đồ giặt')

@section('content')
<!-- Page Actions: nút "Thêm" luôn là phần tử đầu tiên ở góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('garment-conditions.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm điều kiện
    </a>
    <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
    <p class="text-muted page-toolbar__desc">Quy định tình trạng ban đầu của đồ khi nhận vào cửa hàng (mới, cũ, thấm bẩn…).</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3"><i class="fas fa-search text-muted"></i></span>
            <input type="text" name="garment_id" class="form-control form-control-sm border-start-0 ps-2" placeholder="Lọc theo mã loại đồ giặt..." value="{{ request('garment_id') }}">
        </div>
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
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>ID</th><th>Loại đồ</th><th>Điều kiện</th><th>Mô tả</th><th>Trạng thái</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                    @forelse($conditions as $condition)
                    <tr>
                        <td>{{ $condition->id }}</td>
                        <td>{{ $condition->garment?->name }}</td>
                        <td><strong>{{ $condition->condition_type }}</strong></td>
                        <td>{{ \Illuminate\Support\Str::limit($condition->description, 50) ?: '-' }}</td>
                        <td>
                            <x-admin.status-badge :status="$condition->status" :enum="\App\Enums\RecordStatus::class" />
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('garment-conditions.edit', $condition) }}" class="btn btn-sm btn-outline-warning">Sửa</a>
                                <form action="{{ route('garment-conditions.destroy', $condition) }}" method="POST" class="d-inline" id="deleteConditionForm_{{ $condition->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có điều kiện</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($conditions->hasPages())
<div class="mt-3">
    {{ $conditions->appends(request()->query())->links('pagination::bootstrap-5') }}
</div>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deleteConditionForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Xóa?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa điều kiện?',
                    text: 'Hành động này không thể hoàn tác.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Xóa',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush
