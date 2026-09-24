@extends('layouts.app')

@section('title', 'Chi Tiết Mã Giảm Giá - Giặt Ủi Pro')
@section('page-title', 'Chi tiết mã giảm giá')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('coupons.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
    <div class="d-flex gap-2">
        <a href="{{ route('coupons.edit', $coupon) }}" class="btn btn-warning btn-sm">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>
        <form action="{{ route('coupons.destroy', $coupon) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa mã giảm giá này?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">
                <i class="bi bi-trash me-1"></i>Xóa
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <span class="text-muted">Mã coupon</span>
                <h4 class="mb-0 text-primary">{{ $coupon->code }}</h4>
            </div>
            @if($coupon->status === 'active')
                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
            @else
                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Tắt</span>
            @endif
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Chương trình</strong></td>
                        <td>{{ $coupon->promotion?->name }} ({{ $coupon->promotion?->code }})</td>
                    </tr>
                    <tr>
                        <td><strong>Loại giảm</strong></td>
                        <td>
                            @if($coupon->discount_type === 'percent')
                                <span class="badge bg-info-subtle text-info border border-info px-2 py-1 rounded-pill">Phần trăm</span>
                            @elseif($coupon->discount_type === 'fixed')
                                <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1 rounded-pill">Số tiền cố định</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1 rounded-pill">Miễn phí ship</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Giá trị</strong></td>
                        <td>
                            @if($coupon->discount_type === 'percent')
                                {{ $coupon->discount_value }}%
                            @elseif($coupon->discount_type === 'fixed')
                                {{ number_format($coupon->discount_value) }} VNĐ
                            @else
                                Miễn phí
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Số lần dùng</strong></td>
                        <td>{{ $coupon->used_count }}/{{ $coupon->max_uses ?: '∞' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Ngày tạo</strong></td>
                        <td>{{ $coupon->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Ngày hết hạn</strong></td>
                        <td>{{ $coupon->expires_at?->format('d/m/Y') ?: 'Không thời hạn' }}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <div class="progress mt-2" style="height: 20px;">
                    @php $percent = $coupon->max_uses ? min(100, ($coupon->used_count / $coupon->max_uses) * 100) : 0; @endphp
                    <div class="progress-bar {{ $percent >= 100 ? 'bg-danger' : 'bg-success' }}" role="progressbar" style="width: {{ $percent }}%"></div>
                </div>
                <small class="text-muted">Tỷ lệ sử dụng: {{ number_format($percent, 1) }}%</small>
            </div>
        </div>
    </div>
</div>

<a href="{{ route('coupons.index') }}" class="btn btn-outline-secondary mt-3">
    <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
</a>
@endsection
