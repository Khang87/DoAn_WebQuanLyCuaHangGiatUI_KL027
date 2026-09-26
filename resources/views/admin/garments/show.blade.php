@extends('layouts.app')

@section('title', 'Chi tiết loại Đồ giặt - Sky Laundry')
@section('page-title', 'Chi tiết loại đồ giặt')

@section('content')
@php
    $garmentIcon = $garment->icon();
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary-subtle text-primary rounded p-3 me-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="{{ $garmentIcon }} fs-4"></i>
                        </div>
                        <h5 class="mb-0">{{ $garment->name }}</h5>
                    </div>
                    @if($garment->status === 'active')
                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Khóa</span>
                    @endif
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Danh mục</div>
                        <div class="fw-semibold">{{ $garment->category ?: 'Chưa phân loại' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Giá dịch vụ</div>
                        <div class="fw-semibold text-primary">{{ number_format($garment->price) }} VNĐ</div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Mô tả hiện trạng trước khi giặt</div>
                        <div class="p-3 bg-light rounded mt-1">{{ $garment->condition_note ?: 'Chưa có thông tin ghi nhận hiện trạng.' }}</div>
                    </div>
                </div>

                @if($garment->deleted_at)
                <div class="mt-4">
                    <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-trash me-1"></i>Đã xóa</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Thao tác</h6>
                <div class="d-grid gap-2">
                    <a href="{{ route('garments.edit', $garment->id) }}" class="btn btn-warning"><i class="bi bi-pencil me-1"></i>Chỉnh sửa</a>
                    <form action="{{ route('garments.destroy', $garment->id) }}" method="POST" id="deleteGarmentShowForm">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100"><i class="bi bi-trash"></i> Xóa</button>
                    </form>
                    <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại danh sách</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('deleteGarmentShowForm');
        if (!form) return;

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
</script>
@endpush
