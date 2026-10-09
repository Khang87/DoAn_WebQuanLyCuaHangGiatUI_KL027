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

<div class="row g-4 align-items-start">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Bảng giá áp dụng cho loại đồ giặt này</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table-custom mb-0">
                        <thead>
                            <tr>
                                <th scope="col">STT</th>
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
                    <a href="{{ route('loaidogiat.edit', $category->LoaiDoGiatID) }}" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-pencil-alt me-1"></i> Chỉnh sửa
                    </a>
                @endcan

                @can('garment_categories.delete')
                    <x-admin.detail.confirm-form
                        :action="route('loaidogiat.destroy', $category->LoaiDoGiatID)"
                        title="Xóa loại đồ giặt?"
                        text="Nếu đang được sử dụng, loại đồ giặt sẽ chuyển sang trạng thái tạm ngưng."
                        label="Xóa loại đồ giặt"
                        icon="bi-trash"
                        variant="btn-outline-danger"
                        size="py-2"
                        block
                    />
                @endcan

                <a href="{{ route('loaidogiat.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
                </a>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">{{ $category->TenLoaiDoGiat }}</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Mã loại đồ giặt</dt>
                    <dd class="col-sm-8">{{ $category->LoaiDoGiatID }}</dd>

                    <dt class="col-sm-4">Trạng thái</dt>
                    <dd class="col-sm-8">
                        <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" />
                    </dd>

                    <dt class="col-sm-4">Mô tả</dt>
                    <dd class="col-sm-8">{{ $category->MoTa ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
