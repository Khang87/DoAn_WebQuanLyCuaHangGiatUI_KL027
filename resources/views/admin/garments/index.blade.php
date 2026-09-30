@extends('layouts.app')

@section('title', 'Quản lý dịch vụ - Sky Laundry')
@section('page-title', 'Quản lý dịch vụ')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    @can('garments.create')
        <a href="{{ route('garments.create') }}" class="btn btn-create">
            <i class="bi bi-plus-lg"></i>Thêm dịch vụ
        </a>
    @endcan
    <p class="text-muted page-toolbar__desc">Danh sách dịch vụ giặt ủi cửa hàng cung cấp và trạng thái hiện tại.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm kiếm dịch vụ, danh mục..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="category" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">-- Tất cả danh mục --</option>
            @foreach($categories as $id => $name)
                <option value="{{ $id }}" @selected(request('category') == $id)>{{ $name }}</option>
            @endforeach
        </select>
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
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">-- Tất cả cách sắp xếp --</option>
            <option value="latest" @selected(request('sort') === 'latest')>Mới nhất</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>Cũ nhất</option>
            <option value="name_asc" @selected(request('sort') === 'name_asc')>Tên A-Z</option>
            <option value="name_desc" @selected(request('sort') === 'name_desc')>Tên Z-A</option>
            <option value="time_asc" @selected(request('sort') === 'time_asc')>Thời gian tăng dần</option>
            <option value="time_desc" @selected(request('sort') === 'time_desc')>Thời gian giảm dần</option>
        </select>
    </div>
</form>


<!-- Services Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th class="fw-bold text-dark">STT</th>
                        <th class="fw-bold text-dark">Mã dịch vụ</th>
                        <th class="fw-bold text-dark">Tên dịch vụ</th>
                        <th class="fw-bold text-dark">Danh mục</th>
                        <th class="fw-bold text-dark">Thời gian ước tính (phút)</th>
                        <th class="fw-bold text-dark">Mô tả</th>
                        <th class="fw-bold text-dark">Trạng thái</th>
                        <th class="fw-bold text-dark">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $service)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $service->DichVuID }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-secondary-subtle text-secondary rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="bi bi-basket2"></i>
                                </div>
                                <strong class="fw-semibold">{{ $service->TenDichVu }}</strong>
                            </div>
                        </td>
                        <td>
                            @if($service->loaiDichVu)
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary">{{ $service->loaiDichVu->TenLoaiDichVu }}</span>
                            @else
                                <span class="text-muted">Chưa phân loại</span>
                            @endif
                        </td>
                        <td><strong class="fw-semibold text-dark">{{ $service->ThoiGianDuKien ?? '—' }} phút</strong></td>
                        <td><small class="text-muted">{{ $service->MoTa ?: 'Chưa có mô tả' }}</small></td>
                        <td>
                            <x-admin.status-badge :status="$service->statusLabel" :enum="\App\Enums\RecordStatus::class" />
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                @can('garments.view')
                                    <a href="{{ route('garments.show', $service->DichVuID) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @endcan
                                @can('garments.edit')
                                    <a href="{{ route('garments.edit', $service->DichVuID) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('garments.delete')
                                    <form action="{{ route('garments.destroy', $service->DichVuID) }}" method="POST" class="d-inline" id="deleteServiceForm_{{ $service->DichVuID }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Chưa có dịch vụ nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($services->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $services->firstItem() }} - {{ $services->lastItem() }} của {{ $services->total() }} dịch vụ</div>
        <ul class="pagination mb-0">
            @if ($services->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $services->appends(request()->query())->url($services->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($services->getUrlRange(max(1, $services->currentPage() - 2), min($services->lastPage(), $services->currentPage() + 2)) as $page => $url)
                @if ($page == $services->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $services->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($services->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $services->appends(request()->query())->url($services->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deleteServiceForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa dịch vụ này?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa dịch vụ?',
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