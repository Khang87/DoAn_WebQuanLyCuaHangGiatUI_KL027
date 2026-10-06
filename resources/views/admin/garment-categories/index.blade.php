@extends('layouts.app')

@section('title', 'Danh mục loại đồ giặt - Sky Laundry')
@section('page-title', 'Danh mục loại đồ giặt')

@section('content')
<div class="page-toolbar">
    @can('garment_categories.create')
        <a href="{{ route('garment-categories.create') }}" class="btn btn-create">
            <i class="bi bi-plus-lg"></i>Thêm danh mục
        </a>
    @endcan
    <p class="text-muted page-toolbar__desc">Nhóm các loại đồ giặt để nhân viên dễ chọn khi lập bảng giá và tiếp nhận đơn.</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input
                type="text"
                name="search"
                class="form-control form-control-sm border-start-0 ps-2"
                placeholder="Tìm theo ID, tên danh mục..."
                value="{{ request('search') }}"
            >
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="$statuses"
            placeholder="Tất cả trạng thái"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">Tất cả cách sắp xếp</option>
            <option value="latest" @selected(request('sort', 'latest') === 'latest')>Mới nhất</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>Cũ nhất</option>
            <option value="name_asc" @selected(request('sort') === 'name_asc')>Tên A-Z</option>
            <option value="name_desc" @selected(request('sort') === 'name_desc')>Tên Z-A</option>
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th class="fw-bold text-dark">STT</th>
                        <th class="fw-bold text-dark">Mã danh mục</th>
                        <th class="fw-bold text-dark">Tên danh mục</th>
                        <th class="fw-bold text-dark">Mô tả</th>
                        <th class="fw-bold text-dark">Số loại đồ</th>
                        <th class="fw-bold text-dark">Ngày tạo</th>
                        <th class="fw-bold text-dark">Trạng thái</th>
                        <th class="fw-bold text-dark">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr data-id="{{ $category->DanhMucID }}">
                            <td>{{ $categories->firstItem() + $loop->index }}</td>
                            <td>{{ 'DM'.str_pad((string) $category->DanhMucID, 4, '0', STR_PAD_LEFT) }}</td>
                            <td><strong>{{ $category->TenDanhMuc }}</strong></td>
                            <td>{{ \Illuminate\Support\Str::limit($category->MoTa, 50) ?: '—' }}</td>
                            <td>{{ $category->loai_do_giats_count }}</td>
                            <td>{{ $category->NgayTao?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>
                                <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" />
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-2 justify-content-center">
                                    @can('garment_categories.view')
                                        <a href="{{ route('garment-categories.show', $category) }}" class="btn btn-order-action view" title="Xem" aria-label="Xem">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('garment_categories.edit')
                                        <a href="{{ route('garment-categories.edit', $category) }}" class="btn btn-order-action edit" title="Sửa" aria-label="Sửa">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('garment_categories.delete')
                                        <x-admin.detail.confirm-form
                                            :action="route('garment-categories.destroy', $category)"
                                            title="Xóa danh mục loại đồ giặt?"
                                            text="Danh mục đang có loại đồ giặt sẽ được chuyển sang trạng thái tạm ngưng."
                                            label="Xóa"
                                            icon="bi bi-trash"
                                            variant="btn-order-action delete"
                                            size=""
                                            :iconOnly="true"
                                        />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Chưa có danh mục loại đồ giặt nào</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($categories->hasPages())
    <nav class="mt-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                Hiển thị {{ $categories->firstItem() }} - {{ $categories->lastItem() }} của {{ $categories->total() }} danh mục
            </div>
            {{ $categories->links() }}
        </div>
    </nav>
@endif
@endsection
