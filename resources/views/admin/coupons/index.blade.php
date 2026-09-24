@extends('layouts.app')

@section('title', 'Mã Giảm Giá - Sky Laundry')
@section('page-title', 'Mã Giảm Giá')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Quay lại Khuyến mãi
        </a>
        <a href="{{ route('coupons.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tạo coupon
        </a>
    </div>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm kiếm mã..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="promotion_id" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả chương trình --</option>
            @foreach($promotions as $promo)
                <option value="{{ $promo->id }}" @selected(request('promotion_id') == $promo->id)>{{ $promo->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 200px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="active" @selected(request('status') === 'active')>Hoạt động</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Tắt</option>
        </select>
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
                            @if($coupon->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Tắt</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('coupons.show', $coupon) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('coupons.edit', $coupon) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('coupons.destroy', $coupon) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa?')">
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
