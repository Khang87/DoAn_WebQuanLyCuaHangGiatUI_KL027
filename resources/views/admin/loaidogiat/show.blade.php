@extends('layouts.app')

@section('title', 'Chi tiết Loại đồ giặt - Sky Laundry')
@section('page-title', 'Chi tiết Loại đồ giặt')

@section('content')
<x-admin.detail.page-header
    :title="$category->TenLoaiDoGiat"
    :subtitle="'Mã loại đồ giặt: '.$category->LoaiDoGiatID"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $category->TenLoaiDoGiat }}</h5>
        @can('garment_categories.edit')
            <a href="{{ route('loaidogiat.edit', $category->LoaiDoGiatID) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>Chỉnh sửa
            </a>
        @endcan
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Mã loại đồ giặt</dt>
            <dd class="col-sm-9">{{ $category->LoaiDoGiatID }}</dd>

            <dt class="col-sm-3">Trạng thái</dt>
            <dd class="col-sm-9">
                <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" />
            </dd>

            <dt class="col-sm-3">Mô tả</dt>
            <dd class="col-sm-9">{{ $category->MoTa ?: '—' }}</dd>
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Bảng giá áp dụng cho loại đồ giặt này</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Dịch vụ</th>
                        <th>Đơn vị tính</th>
                        <th>Đơn giá</th>
                        <th>Ngày áp dụng</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pricings as $pricing)
                        <tr>
                            <td>{{ $pricings->firstItem() + $loop->index }}</td>
                            <td>{{ $pricing->dichVu?->TenDichVu ?? '—' }}</td>
                            <td>{{ $pricing->donViTinh?->TenDonViTinh ?? '—' }}</td>
                            <td>{{ number_format((float) $pricing->DonGia, 0, ',', '.') }} đ</td>
                            <td>{{ $pricing->NgayApDung?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $pricing->TrangThai }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Chưa có bảng giá cho loại đồ giặt này.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($pricings->hasPages())
        <div class="card-footer">
            {{ $pricings->links() }}
        </div>
    @endif
</div>

<a href="{{ route('loaidogiat.index') }}" class="btn btn-outline-secondary mt-3">
    <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
</a>
@endsection
