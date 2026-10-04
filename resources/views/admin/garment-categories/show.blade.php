@extends('layouts.app')

@section('title', 'Chi tiết danh mục loại đồ giặt - Sky Laundry')
@section('page-title', 'Chi tiết danh mục loại đồ giặt')

@section('content')
<x-admin.detail.page-header
    :title="'Danh mục '.$category->TenDanhMuc"
    :subtitle="$category->MoTa"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    <div class="col-lg-8">
        <x-admin.detail.panel title="Loại đồ trong danh mục" icon="bi-list-check" :iconClass="'bg-secondary-subtle text-secondary'" flush>
            <x-slot:header>
                <span class="text-muted small">{{ $garments->total() }} loại đồ</span>
            </x-slot:header>

            @if($garments->isEmpty())
                <x-admin.detail.empty message="Danh mục này chưa có loại đồ giặt nào" icon="bi-tags" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover detail-table">
                        <thead>
                            <tr>
                                <th>Mã loại đồ</th>
                                <th>Tên loại đồ</th>
                                <th>Mô tả</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($garments as $garment)
                                <tr>
                                    <td>{{ 'LD'.str_pad((string) $garment->LoaiDoGiatID, 4, '0', STR_PAD_LEFT) }}</td>
                                    <td class="fw-semibold">{{ $garment->TenLoaiDoGiat }}</td>
                                    <td>{{ $garment->MoTa ?: '—' }}</td>
                                    <td><x-admin.status-badge :status="$garment->TrangThai" :enum="\App\Enums\RecordStatus::class" size="px-2 py-1" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($garments->hasPages())
                    <div class="p-3">{{ $garments->links() }}</div>
                @endif
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Thông tin danh mục" icon="bi-folder" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Tên danh mục" :value="$category->TenDanhMuc" />
                <x-admin.detail.info-item label="Danh mục ID" :value="$category->DanhMucID" />
                <x-admin.detail.info-item label="Mã danh mục" :value="'DM'.str_pad((string) $category->DanhMucID, 4, '0', STR_PAD_LEFT)" />
                <x-admin.detail.info-item label="Số loại đồ" :value="$category->loaiDoGiats()->count()" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
            <div class="mt-4">
                <div class="detail-field__label mb-2">Mô tả</div>
                <div class="detail-text">{{ $category->MoTa ?: 'Chưa có mô tả.' }}</div>
            </div>
        </x-admin.detail.panel>
    </div>

    <div class="col-lg-4 d-flex flex-column">
        <div class="card shadow-sm border-0 order-first">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @can('garment_categories.edit')
                    <a href="{{ route('garment-categories.edit', $category) }}" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-pencil-alt me-1"></i>Chỉnh sửa
                    </a>
                @endcan
                @can('garment_categories.create')
                    <a href="{{ route('loaidogiat.create') }}" class="btn btn-outline-primary w-100 py-2">
                        <i class="fas fa-plus me-1"></i>Thêm loại đồ giặt
                    </a>
                @endcan
                @can('garment_categories.delete')
                    <x-admin.detail.confirm-form
                        :action="route('garment-categories.destroy', $category)"
                        title="Xóa danh mục loại đồ giặt?"
                        text="Danh mục còn loại đồ giặt liên quan sẽ được chuyển sang trạng thái tạm ngưng."
                        label="Xóa danh mục"
                        icon="bi-trash"
                        variant="btn-outline-danger"
                        size="py-2"
                        block
                    />
                @endcan
                <a href="{{ route('garment-categories.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
