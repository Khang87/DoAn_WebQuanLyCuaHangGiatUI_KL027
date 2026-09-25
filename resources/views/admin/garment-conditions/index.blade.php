@extends('layouts.app')

@section('title', 'Điều kiện đồ giặt - Sky Laundry')
@section('page-title', 'Điều kiện đồ giặt')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
    <a href="{{ route('garment-conditions.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Thêm điều kiện
    </a>
</div>

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
                            @if($condition->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Khóa</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('garment-conditions.edit', $condition) }}" class="btn btn-sm btn-outline-warning">Sửa</a>
                                <form action="{{ route('garment-conditions.destroy', $condition) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa?')">
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
