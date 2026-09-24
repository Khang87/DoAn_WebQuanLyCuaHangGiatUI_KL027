@extends('layouts.app')
@section('title', 'Chi Tiết Khuyến Mãi - Giặt Ủi Pro')
@section('page-title', 'Chi tiết khuyến mãi')
@section('content')
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <span class="text-muted">Mã khuyến mãi</span>
                <h4 class="mb-0 text-primary">{{ $promotion->code }}</h4>
            </div>
            @if($promotion->status === 'active')
                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đang áp dụng</span>
            @else
                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Hết hạn</span>
            @endif
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="small text-muted">Tên khuyến mãi</div>
                <div class="fw-semibold">{{ $promotion->name }}</div>
            </div>
            <div class="col-md-6">
                <div class="small text-muted">Giảm giá</div>
                <div class="fw-semibold">{{ $promotion->discount }}</div>
            </div>
            <div class="col-md-6">
                <div class="small text-muted">Ngày tạo</div>
                <div class="fw-semibold">{{ $promotion->created_at?->format('d/m/Y H:i') }}</div>
            </div>
            <div class="col-md-6">
                <div class="small text-muted">Hết hạn</div>
                <div class="fw-semibold">{{ $promotion->expires_at?->format('d/m/Y') ?: 'Không thời hạn' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Mã giảm giá liên quan</h5>
        <a href="{{ route('coupons.create') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Thêm mã
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Mã Coupon</th><th>Loại</th><th>Giá trị</th><th>Số lần dùng</th><th>Hạn dùng</th><th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coupons as $coupon)
                    <tr>
                        <td><strong>{{ $coupon->code }}</strong></td>
                        <td>
                            @if($coupon->discount_type === 'percent')
                                <span class="badge bg-info-subtle text-info border border-info px-2 py-1 rounded-pill">Phần trăm</span>
                            @elseif($coupon->discount_type === 'fixed')
                                <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1 rounded-pill">Số tiền</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1 rounded-pill">Miễn phí ship</span>
                            @endif
                        </td>
                        <td>
                            @if($coupon->discount_type === 'percent')
                                {{ $coupon->discount_value }}%
                            @elseif($coupon->discount_type === 'fixed')
                                {{ number_format($coupon->discount_value) }} VNĐ
                            @else
                                Miễn phí
                            @endif
                        </td>
                        <td>{{ $coupon->used_count }}/{{ $coupon->max_uses ?: '∞' }}</td>
                        <td>{{ $coupon->expires_at?->format('d/m/Y') ?: 'Không hạn' }}</td>
                        <td>
                            @if($coupon->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success px-2 py-1 rounded-pill"><i class="fas fa-check-circle"></i></span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1 rounded-pill"><i class="fas fa-ban"></i></span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">Chưa có coupon nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($coupons->hasPages())
    <div class="mt-3 px-3">
        {{ $coupons->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

<div class="d-flex gap-2">
    <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
    </a>
    <a href="{{ route('promotions.edit', $promotion) }}" class="btn btn-primary">
        <i class="bi bi-pencil me-1"></i>Chỉnh sửa
    </a>
</div>
@endsection
