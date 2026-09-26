@extends('layouts.app')

@section('title', 'Quản lý loại đồ giặt - Sky Laundry')
@section('page-title', 'Quản lý loại đồ giặt')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('garments.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm loại đồ giặt
    </a>
    <p class="text-muted page-toolbar__desc">Các loại đồ giặt cửa hàng nhận xử lý và tình trạng hiện có của từng loại.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm kiếm loại đồ, hiện trạng..." value="{{ request('search') }}">
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
            <option value="price_asc" @selected(request('sort') === 'price_asc')>Giá tăng dần</option>
            <option value="price_desc" @selected(request('sort') === 'price_desc')>Giá giảm dần</option>
        </select>
    </div>
</form>


<!-- Garments Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th class="fw-bold text-dark">STT</th>
                        <th class="fw-bold text-dark">Mã loại đồ giặt</th>
                        <th class="fw-bold text-dark">Tên loại đồ</th>
                        <th class="fw-bold text-dark">Danh mục</th>
                        <th class="fw-bold text-dark">Giá dịch vụ</th>
                        <th class="fw-bold text-dark">Mô tả hiện trạng</th>
                        <th class="fw-bold text-dark">Trạng thái</th>
                        <th class="fw-bold text-dark">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($garments as $garment)
                    @php $garmentIcon = $garment->icon(); @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $garment->code ?? 'GD' . str_pad($garment->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-secondary-subtle text-secondary rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="{{ $garmentIcon }}"></i>
                                </div>
                                <strong class="fw-semibold">{{ $garment->name }}</strong>
                            </div>
                        </td>
                        <td>
                            @if($garment->category_name)
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary">{{ $garment->category_name }}</span>
                            @else
                                <span class="text-muted">Đồ giặt</span>
                            @endif
                        </td>
                        <td><strong class="fw-semibold text-dark">{{ number_format($garment->price) }} VNĐ</strong></td>
                        <td><small class="text-muted">{{ $garment->condition_note ?: 'Chưa ghi nhận hiện trạng' }}</small></td>
                        <td>
                            <x-admin.status-badge :status="$garment->status" :enum="\App\Enums\RecordStatus::class" />
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('garments.show', $garment->id) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('garments.edit', $garment->id) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('garments.destroy', $garment->id) }}" method="POST" class="d-inline" id="deleteGarmentForm_{{ $garment->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Chưa có thông tin loại đồ giặt nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($garments->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $garments->firstItem() }} - {{ $garments->lastItem() }} của {{ $garments->total() }} loại đồ</div>
        <ul class="pagination mb-0">
            @if ($garments->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $garments->appends(request()->query())->url($garments->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($garments->getUrlRange(max(1, $garments->currentPage() - 2), min($garments->lastPage(), $garments->currentPage() + 2)) as $page => $url)
                @if ($page == $garments->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $garments->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($garments->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $garments->appends(request()->query())->url($garments->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deleteGarmentForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa loại đồ giặt này?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa loại đồ giặt?',
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