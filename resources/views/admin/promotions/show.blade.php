@extends('layouts.app')

@section('title', 'Chi tiết khuyến mãi - Sky Laundry')
@section('page-title', 'Chi tiết khuyến mãi')

@section('content')
@php
    // Coupon dùng bộ giá trị riêng (percent/fixed/free_shipping),
    // Promotion dùng bộ giá trị trong Promotion::discountTypeOptions().
    $couponDiscountLabels = [
        'percent' => 'Phần trăm',
        'fixed' => 'Số tiền cố định',
        'free_shipping' => 'Miễn phí giao hàng',
    ];
@endphp

<x-admin.detail.page-header
    title="Khuyến mãi {{ $promotion->code }}"
    :back="route('promotions.index')"
    :subtitle="$promotion->name"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$promotion->status" :enum="\App\Enums\RecordStatus::class" />
        <span class="badge {{ $promotion->is_valid ? 'bg-success-subtle text-success-emphasis border border-success' : 'bg-secondary-subtle text-secondary-emphasis border border-secondary' }} px-3 py-2 rounded-pill">
            <i class="bi {{ $promotion->is_valid ? 'bi-check-circle' : 'bi-x-circle' }} me-1"></i>
            {{ $promotion->is_valid ? 'Đang áp dụng' : 'Không áp dụng' }}
        </span>
    </x-slot:badge>

    <x-slot:actions>
        <a href="{{ route('coupons.create') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Thêm mã giảm giá
        </a>
        <a href="{{ route('promotions.edit', $promotion) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>
    </x-slot:actions>
</x-admin.detail.page-header>

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin khuyến mãi" icon="bi-megaphone" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Tên khuyến mãi" :value="$promotion->name" />
                <x-admin.detail.info-item label="Mã khuyến mãi">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        {{ $promotion->code }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Loại giảm">
                    <span class="badge {{ $promotion->discountTypeBadgeClass() }} px-3 py-2 rounded-pill">
                        {{ $promotion->discountTypeLabel() }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Chiết khấu">
                    <span class="fw-semibold">{{ $promotion->discountSummary() }}</span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Đơn tối thiểu">
                    @if((float) $promotion->min_order_amount > 0)
                        <x-admin.detail.money :value="$promotion->min_order_amount" />
                    @else
                        <span class="detail-empty-value">Không yêu cầu</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Số lượt sử dụng">
                    @if($promotion->usage_limit)
                        <span class="fw-semibold">{{ $promotion->usageLabel() }}</span>
                    @else
                        <span class="detail-empty-value">Không giới hạn lượt</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Số mã phát ra">
                    @if($promotion->remainingCodes() === null)
                        <span class="detail-empty-value">Phát vô hạn</span>
                    @else
                        <span class="fw-semibold">{{ number_format($promotion->remainingCodes()) }} mã còn lại</span>
                        <small class="text-muted d-block">Tổng {{ number_format((int) $promotion->quantity) }} mã · đã dùng {{ number_format((int) $promotion->used_count) }}</small>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Bắt đầu" :value="$promotion->starts_at?->format('d/m/Y') ?: 'Tức thì'" />
                <x-admin.detail.info-item label="Hết hạn" :value="$promotion->expires_at?->format('d/m/Y') ?: 'Không thời hạn'" />
                <x-admin.detail.info-item label="Ngày tạo" :value="$promotion->created_at?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$promotion->status" :enum="\App\Enums\RecordStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            @if($promotion->hasConditions())
                <div class="mt-4">
                    <div class="detail-field__label mb-2">Điều kiện áp dụng</div>
                    <div class="detail-text">
                        @if($promotion->isFirstOrderOnly())
                            <span class="badge bg-amber-subtle text-amber-emphasis border border-amber px-3 py-2 rounded-pill">
                                <i class="bi bi-person-check me-1"></i>Chỉ áp dụng cho đơn hàng đầu tiên
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Mã giảm giá liên quan" icon="bi-ticket-perforated" :iconClass="'bg-secondary-subtle text-secondary'" flush>
            <x-slot:header>
                <a href="{{ route('coupons.create') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-lg me-1"></i>Thêm mã
                </a>
            </x-slot:header>

            @if($coupons->isEmpty())
                <x-admin.detail.empty message="Chưa có mã giảm giá nào thuộc chương trình" icon="bi-ticket-perforated" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover detail-table">
                        <thead>
                            <tr>
                                <th>Mã coupon</th>
                                <th>Loại</th>
                                <th class="text-end">Giá trị</th>
                                <th class="text-end">Số lần dùng</th>
                                <th class="text-end">Hạn dùng</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($coupons as $coupon)
                                <tr>
                                    <td>
                                        <span class="fw-semibold">
                                            {{ $coupon->code }}
                                        </span>
                                    </td>
                                    <td>{{ $couponDiscountLabels[$coupon->discount_type] ?? '—' }}</td>
                                    <td class="text-end">
                                        @if($coupon->discount_type === 'percent')
                                            {{ rtrim(rtrim((string) $coupon->discount_value, '0'), '.') }}%
                                        @elseif($coupon->discount_type === 'fixed')
                                            <x-admin.detail.money :value="$coupon->discount_value" />
                                        @else
                                            Miễn phí
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $coupon->used_count }}/{{ $coupon->max_uses ?: '∞' }}</td>
                                    <td class="text-end">{{ $coupon->expires_at?->format('d/m/Y') ?: 'Không hạn' }}</td>
                                    <td>
                                        <x-admin.status-badge :status="$coupon->status" :enum="\App\Enums\RecordStatus::class" size="px-2 py-1" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($coupons->hasPages())
                    <div class="p-3">{{ $coupons->links('pagination::bootstrap-5') }}</div>
                @endif
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Hạn mức sử dụng" icon="bi-bar-chart" :iconClass="'bg-success-subtle text-success'">
            @php
                $hasUsageLimit = (bool) $promotion->usage_limit;
                $usagePercent = $hasUsageLimit
                    ? min(100, ($promotion->used_count / $promotion->usage_limit) * 100)
                    : 0.0;
            @endphp
            <div class="d-flex justify-content-between align-items-baseline mb-2">
                <span class="detail-field__label">Đã dùng</span>
                <span class="detail-summary__total">
                    {{ $hasUsageLimit ? number_format($usagePercent, 1) . '%' : 'Không giới hạn' }}
                </span>
            </div>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar {{ $hasUsageLimit && $usagePercent >= 100 ? 'bg-danger' : 'bg-success' }}" role="progressbar"
                     style="width: {{ $hasUsageLimit ? $usagePercent : 0 }}%" aria-valuenow="{{ $hasUsageLimit ? (int) $usagePercent : 0 }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <x-admin.detail.info-grid :columns="1" class="mt-3">
                <x-admin.detail.info-item label="Đã sử dụng" :value="$promotion->used_count . ' lượt'" />
                <x-admin.detail.info-item label="Tối đa" :value="$promotion->usage_limit ?: 'Không giới hạn'" />
                <x-admin.detail.info-item label="Mã đã phát" :value="($promotion->remainingCodes() === null ? 'Không giới hạn' : $promotion->remainingCodes() . ' mã còn lại')" />
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
