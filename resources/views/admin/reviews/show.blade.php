@extends('layouts.app')

@section('title', 'Chi tiết đánh giá - Sky Laundry')
@section('page-title', 'Chi tiết đánh giá')

@section('content')
@php
    $reviewStatus = $review->status ?? 'visible';
    $isHidden = $reviewStatus === 'hidden';
@endphp

<x-admin.detail.page-header
    title="Đánh giá #{{ $review->id }}"
    :back="route('reviews.index')"
    :subtitle="$review->customer?->name ?: $review->order?->customer?->name"
>
    <x-slot:badge>
        <span class="badge bg-amber-subtle text-amber-emphasis border border-amber px-3 py-2 rounded-pill">
            @for($star = 1; $star <= 5; $star++)
                <i class="fas fa-star{{ $star <= $review->rating ? '' : '-regular' }}"></i>
            @endfor
            <span class="ms-1">{{ $review->rating }}/5</span>
        </span>
        <x-admin.status-badge :status="$reviewStatus" :enum="\App\Enums\ReviewStatus::class" />
    </x-slot:badge>

    <x-slot:actions>
        @if(auth()->user()?->isManager())
            <x-admin.detail.confirm-form
                :action="route('reviews.toggle', $review)"
                method="PATCH"
                title="{{ $isHidden ? 'Hiện' : 'Ẩn' }} đánh giá này?"
                text="Trạng thái hiển thị của đánh giá sẽ thay đổi."
                label="{{ $isHidden ? 'Hiện đánh giá' : 'Ẩn đánh giá' }}"
                :icon="$isHidden ? 'bi-eye' : 'bi-eye-slash'"
                variant="btn-outline-secondary"
                color="#64748b"
                :iconName="'question'"
            />
        @endif
    </x-slot:actions>
</x-admin.detail.page-header>

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Nội dung đánh giá" icon="bi-chat-quote" :iconClass="'bg-warning-subtle text-warning-emphasis'">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="fs-4 text-warning">
                    @for($star = 1; $star <= 5; $star++)
                        <i class="fas fa-star{{ $star <= $review->rating ? '' : '-regular' }}"></i>
                    @endfor
                </div>
                <span class="detail-field__label">Chấm điểm</span>
            </div>

            <div class="detail-text mb-4">{{ $review->content ?: $review->comment ?: 'Không có nội dung' }}</div>

            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Đơn hàng">
                    @if($review->order_id)
                        <a href="{{ route('orders.show', $review->order_id) }}" class="text-decoration-none">
                            {{ $review->order?->code ?: 'Xem đơn hàng' }}
                        </a>
                    @else
                        <span class="detail-empty-value">—</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Khách hàng" :value="$review->customer?->name ?: $review->order?->customer?->name" />
                <x-admin.detail.info-item label="Ngày đánh giá" :value="$review->created_at?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$reviewStatus" :enum="\App\Enums\ReviewStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            @if($review->images)
                <div class="mt-4">
                    <div class="detail-field__label mb-2">Hình ảnh khách hàng</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach((array) $review->images as $image)
                            <img src="{{ \Illuminate\Support\Str::startsWith($image, 'http') ? $image : asset('storage/' . $image) }}"
                                 class="rounded border" style="width: 96px; height: 96px; object-fit: cover;" alt="Ảnh đánh giá">
                        @endforeach
                    </div>
                </div>
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Phản hồi từ cửa hàng" icon="bi-reply" :iconClass="'bg-success-subtle text-success'" id="phan-hoi">
            @if($review->shop_response)
                <div class="detail-text mb-2">{{ $review->shop_response }}</div>
                <div class="detail-field__label">Đã phản hồi lúc {{ $review->reviewed_at?->format('d/m/Y H:i') }}</div>
            @else
                <x-admin.detail.empty message="Chưa có phản hồi cho đánh giá này" icon="bi-reply" />
            @endif

            <form action="{{ route('reviews.respond', $review) }}" method="POST" class="mt-3">
                @csrf
                @method('PATCH')
                <label class="form-label" for="shop_response">Nội dung phản hồi <span class="text-danger ms-1">*</span></label>
                <textarea class="form-control" id="shop_response" name="shop_response" rows="5"
                          placeholder="Cảm ơn khách hàng đã đánh giá..." required>{{ old('shop_response', $review->shop_response) }}</textarea>
                <button type="submit" class="btn btn-primary btn-sm mt-3">
                    <i class="bi bi-send me-1"></i>Gửi phản hồi
                </button>
            </form>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
