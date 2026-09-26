@extends('layouts.app')

@section('title', 'Mã giảm giá - Sky Laundry')
@section('page-title', 'Mã giảm giá')

@section('content')
<!-- Page Actions: nút "Thêm" luôn là phần tử đầu tiên ở góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('coupons.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm coupon
    </a>
    <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Quay lại Khuyến mãi
    </a>
    <p class="text-muted page-toolbar__desc">Danh sách mã giảm giá đã phát ra, theo dõi số lượt dùng và thời hạn hiệu lực.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm kiếm mã..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="promotion_id" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">-- Tất cả chương trình --</option>
            @foreach($promotions as $promo)
                <option value="{{ $promo->id }}" @selected(request('promotion_id') == $promo->id)>{{ $promo->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="\App\Enums\RecordStatus::options()"
            placeholder="-- Tất cả trạng thái --"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>ID</th><th>Mã coupon</th><th>Chương trình</th><th>Loại giảm</th><th>Giá trị</th><th>HSD</th><th>SL dùng</th><th>Trạng thái</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                    @forelse($coupons as $coupon)
                    <tr>
                        <td>{{ $coupon->id }}</td>
                        <td><strong>{{ $coupon->code }}</strong></td>
                        <td>{{ $coupon->promotion?->name }}</td>
                        <td>{{ $coupon->discount_type === 'percent' ? '%' : '₫' }} {{ $coupon->discount_type === 'percent' ? $coupon->discount_value . '%' : number_format($coupon->discount_value) }}</td>
                        <td>{{ $coupon->expires_at?->format('d/m/Y') }}</td>
                        <td>{{ $coupon->used_count }}/{{ $coupon->max_uses ?? '∞' }}</td>
                        <td>
                            <x-admin.status-badge :status="$coupon->status" :enum="\App\Enums\RecordStatus::class" />
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('coupons.show', $coupon) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('coupons.edit', $coupon) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('coupons.destroy', $coupon) }}" method="POST" class="d-inline" id="deleteCouponForm_{{ $coupon->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Chưa có mã giảm giá</td></tr>
        @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($coupons->hasPages())
<div class="mt-3">
    {{ $coupons->appends(request()->query())->links('pagination::bootstrap-5') }}
</div>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deleteCouponForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Xóa?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa mã giảm giá?',
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
