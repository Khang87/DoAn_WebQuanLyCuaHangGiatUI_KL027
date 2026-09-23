@extends('layouts.app')

@section('title', 'Mã giảm giá - Giặt Ủi Pro')
@section('page-title', 'Mã giảm giá')

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
    <form action="{{ route('coupons.index') }}" method="GET" class="d-flex gap-2">
        <select name="promotion_id" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả chương trình</option>
            @foreach($promotions as $promo)
                <option value="{{ $promo->id }}" @selected(request('promotion_id') == $promo->id)>{{ $promo->name }}</option>
            @endforeach
        </select>
        <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            <option value="active" @selected(request('status') === 'active')>Hoạt động</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Tắt</option>
        </select>
    </form>
</div>

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
                            <span class="badge {{ $coupon->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                {{ $coupon->status === 'active' ? 'Hoạt động' : 'Tắt' }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('coupons.edit', $coupon) }}" class="btn btn-sm btn-outline-warning">Sửa</a>
                                <form action="{{ route('coupons.destroy', $coupon) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
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
