@extends('layouts.app')

@section('title', 'Chi tiết danh mục dịch vụ - Sky Laundry')
@section('page-title', 'Chi tiết danh mục dịch vụ')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1">{{ $category->TenLoaiDichVu }}</h2>
        <span class="text-muted">Mã danh mục: {{ $category->getKey() }}</span>
    </div>
    <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" />
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Thông tin danh mục</h5>
        <p class="mb-0">{{ $category->MoTa ?: 'Chưa có mô tả.' }}</p>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Dịch vụ thuộc danh mục</h5>
        <span class="text-muted small">{{ $services->total() }} dịch vụ</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Mã dịch vụ</th>
                    <th>Tên dịch vụ</th>
                    <th>Mô tả</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                @forelse($services as $service)
                    <tr>
                        <td>{{ $service->getKey() }}</td>
                        <td>{{ $service->TenDichVu }}</td>
                        <td>{{ $service->MoTa ?: '—' }}</td>
                        <td><x-admin.status-badge :status="$service->TrangThai" :enum="\App\Enums\RecordStatus::class" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">Danh mục chưa có dịch vụ.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($services->hasPages())
        <div class="card-footer">{{ $services->links() }}</div>
    @endif
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('laundry-categories.index') }}" class="btn btn-outline-secondary">Quay lại</a>
    <a href="{{ route('laundry-categories.edit', $category->getKey()) }}" class="btn btn-primary">Chỉnh sửa</a>
</div>
@endsection
