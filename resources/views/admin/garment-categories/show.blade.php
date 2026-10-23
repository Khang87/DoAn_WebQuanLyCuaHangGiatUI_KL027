@extends('layouts.app')

@section('title', 'Chi tiết danh mục loại đồ giặt - Sky Laundry')
@section('page-title', 'Chi tiết danh mục loại đồ giặt')

@section('content')
<x-admin.detail.page-header
    :title="'Danh mục '.$category->TenDanhMuc"
    :subtitle="$category->MoTa"
    backUrl="{{ route('garment-categories.index') }}"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    <div class="col-12">
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
                                <th scope="col">STT</th>
                                <th>Tên loại đồ</th>
                                <th>Mô tả</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($garments as $garment)
                                <tr>
                                    <td>{{ $garments->firstItem() + $loop->index }}</td>
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
                <x-admin.detail.info-item label="Số loại đồ" :value="$category->loaiDoGiats()->count()" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
            <div class="mt-4">
                <div class="detail-field__label mb-2">Mô tả</div>
                <div class="detail-text">{{ $category->MoTa ?: 'Chưa có mô tả.' }}</div>
            </div>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
