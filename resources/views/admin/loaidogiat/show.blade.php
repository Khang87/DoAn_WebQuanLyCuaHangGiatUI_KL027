@extends('layouts.app')

@section('title', 'Chi tiết Loại đồ giặt - Sky Laundry')
@section('page-title', 'Chi tiết Loại đồ giặt')

@section('content')
<x-admin.detail.page-header
    :title="$category->TenLoaiDoGiat"
    backUrl="{{ route('loaidogiat.index') }}"
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
                                    <td><x-admin.status-badge :status="$pricing->TrangThai" :enum="\App\Enums\RecordStatus::class" size="px-2 py-1" /></td>
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
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ $category->TenLoaiDoGiat }}</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
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
