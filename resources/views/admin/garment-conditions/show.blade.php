@extends('layouts.app')

@section('title', 'Chi tiết hiện trạng đồ - Sky Laundry')
@section('page-title', 'Chi tiết hiện trạng đồ')

@section('content')
<x-admin.detail.page-header
    title="Hiện trạng #{{ $condition->id }}"
    :back="route('garment-conditions.index')"
    :subtitle="$condition->garment?->name"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$condition->status ?: 'active'" :enum="\App\Enums\RecordStatus::class" />
    </x-slot:badge>

    <x-slot:actions>
        <a href="{{ route('garment-conditions.edit', $condition) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>

        <x-admin.detail.confirm-form
            :action="route('garment-conditions.destroy', $condition)"
            title="Xóa bản ghi hiện trạng?"
            text="Hành động này không thể hoàn tác."
            label="Xóa"
            icon="bi-trash"
            variant="btn-outline-danger"
        />
    </x-slot:actions>
</x-admin.detail.page-header>

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin hiện trạng" icon="bi-clipboard-check" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Loại hiện trạng" :value="$condition->condition_type" />
                <x-admin.detail.info-item label="Ngày ghi nhận" :value="$condition->created_at?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$condition->status ?: 'active'" :enum="\App\Enums\RecordStatus::class" :pill="false" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Cập nhật lần cuối" :value="$condition->updated_at?->format('d/m/Y H:i')" />
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Mô tả hiện trạng</div>
                <div class="detail-text">{{ $condition->description ?: 'Chưa có mô tả.' }}</div>
            </div>

            @if($condition->photo)
                <div class="mt-4">
                    <div class="detail-field__label mb-2">Ảnh hiện trạng</div>
                    <img src="{{ \Illuminate\Support\Str::startsWith($condition->photo, 'http') ? $condition->photo : asset('storage/' . $condition->photo) }}"
                         class="rounded border" style="max-width: 320px;" alt="Ảnh hiện trạng">
                </div>
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Loại đồ giặt" icon="bi-tag" :iconClass="'bg-secondary-subtle text-secondary'">
            @if($condition->garment)
                <x-admin.detail.info-grid :columns="1">
                    <x-admin.detail.info-item label="Tên loại đồ" :value="$condition->garment->name" />
                    <x-admin.detail.info-item label="Danh mục" :value="$condition->garment->category" />
                    <x-admin.detail.info-item label="Giá dịch vụ">
                        <x-admin.detail.money :value="$condition->garment->price" />
                    </x-admin.detail.info-item>
                </x-admin.detail.info-grid>
                <div class="mt-3">
                    <a href="{{ route('garments.show', $condition->garment->id) }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem loại đồ giặt
                    </a>
                </div>
            @else
                <x-admin.detail.empty message="Bản ghi chưa gắn loại đồ giặt" icon="bi-tag" />
            @endif
        </x-admin.detail.panel>
    </div>
</div>
@endsection
