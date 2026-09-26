@extends('layouts.app')

@section('title', 'Chi tiết Danh mục loại đồ giặt - Sky Laundry')
@section('page-title', 'Chi tiết Danh mục loại đồ giặt')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('garment-categories.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
    <div class="d-flex gap-2">
        <a href="{{ route('garment-categories.edit', $category) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $category->name }}</h5>
        @if($category->icon)
            <i class="{{ $category->icon }} fa-2x"></i>
        @endif
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Slug</strong></td><td>{{ $category->slug }}</td></tr>
                    <tr><td><strong>Trạng thái</strong></td>
                        <td>
                            <x-admin.status-badge :status="$category->status" :enum="\App\Enums\RecordStatus::class" />
                        </td>
                    </tr>
                    <tr><td><strong>Thứ tự</strong></td><td>{{ $category->sort_order }}</td></tr>
                    <tr><td><strong>Ngày tạo</strong></td><td>{{ $category->created_at?->format('d/m/Y H:i') }}</td></tr>
                    <tr><td><strong>Cập nhật lần cuối</strong></td><td>{{ $category->updated_at?->format('d/m/Y H:i') }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                @if($category->description)
                <table class="table table-borderless">
                    <tr><td colspan="2"><strong>Mô tả</strong></td></tr>
                    <tr><td colspan="2">{{ $category->description }}</td></tr>
                </table>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Các loại đồ giặt trong danh mục này</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th class="fw-bold text-dark">STT</th>
                        <th class="fw-bold text-dark">Tên loại đồ</th>
                        <th class="fw-bold text-dark">Giá dịch vụ</th>
                        <th class="fw-bold text-dark">Trạng thái</th>
                        <th class="fw-bold text-dark">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($garments as $garment)
                    @php $garmentIcon = $garment->icon(); @endphp
                    <tr>
                        <td class="text-dark">{{ $loop->iteration }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary-subtle text-primary rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="{{ $garmentIcon }}"></i>
                                </div>
                                <span class="text-dark">{{ $garment->name }}</span>
                            </div>
                        </td>
                        <td class="text-dark"><strong>{{ number_format($garment->price) }} VNĐ</strong></td>
                        <td>
                            <x-admin.status-badge :status="$garment->status" :enum="\App\Enums\RecordStatus::class" />
                        </td>
                        <td>
                            <a href="{{ route('garments.show', $garment) }}" class="btn btn-outline-secondary btn-sm" title="Xem"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Chưa có loại đồ giặt nào trong danh mục này</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($garments->hasPages())
    <nav class="p-3">
        {{ $garments->appends(request()->query())->links('pagination::bootstrap-5') }}
    </nav>
    @endif
</div>
@endsection