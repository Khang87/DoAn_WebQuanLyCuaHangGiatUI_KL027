@extends('layouts.app')

@section('title', 'Chi tiết đánh giá - Sky Laundry')
@section('page-title', 'Chi tiết đánh giá')

@section('content')
@php
    $reviewStatus = $review->status ?? 'visible';
    $isResponding = request()->boolean('respond');
@endphp

<x-admin.detail.page-header
    title="Chi tiết đánh giá"
    :subtitle="$review->customer?->name ?: $review->order?->customer?->name"
    backUrl="{{ route('reviews.index') }}"
>
    <x-slot:badge>
        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill d-inline-flex align-items-center gap-1">
            @for($star = 1; $star <= 5; $star++)
                @if ($star <= (int) $review->SoSao)
                    <i class="fas fa-star text-warning"></i>
                @else
                    <i class="far fa-star text-black-50 opacity-25"></i>
                @endif
            @endfor
            <span class="ms-1 fw-bold text-dark">{{ $review->SoSao }}/5</span>
        </span>
        <x-admin.status-badge :status="$reviewStatus" :enum="\App\Enums\ReviewStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    <div class="{{ $isResponding ? 'col-lg-7' : 'col-12' }}">
        <x-admin.detail.panel title="Nội dung đánh giá" icon="bi-chat-quote" :iconClass="'bg-warning-subtle text-warning-emphasis'">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="fs-4 text-warning">
                    @for($star = 1; $star <= 5; $star++)
                        @if ($star <= (int) $review->SoSao)
                            <i class="fas fa-star"></i>
                        @else
                            <i class="far fa-star"></i>
                        @endif
                    @endfor
                </div>
                <span class="detail-field__label">Chấm điểm</span>
            </div>

            <div class="detail-text mb-4">{{ $review->BinhLuan ?: 'Không có nội dung' }}</div>

            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Đơn hàng">
                    @if($review->DonHangID)
                        @can('orders.view')
                        <a href="{{ route('orders.show', $review->DonHangID) }}" class="text-decoration-none">
                            {{ $review->donHang?->MaDonHang ?: 'Xem đơn hàng' }}
                        </a>
                        @else
                        {{ $review->donHang?->MaDonHang ?: '—' }}
                        @endcan
                    @else
                        <span class="detail-empty-value">—</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Khách hàng">
                    {{ $review->khachHang?->HoTen ?: '—' }}
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Ngày đánh giá" :value="$review->NgayDanhGia?->format('d/m/Y H:i')" />
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

    @if($isResponding)
    <div class="col-lg-5 d-flex flex-column">
        <x-admin.detail.panel title="Phản hồi từ cửa hàng" icon="bi-reply" :iconClass="'bg-success-subtle text-success'" id="phan-hoi">
            @if($review->shop_response)
                <div class="detail-text mb-2">{{ $review->shop_response }}</div>
                <div class="detail-field__label">Cửa hàng đã gửi phản hồi</div>
            @else
                <x-admin.detail.empty message="Chưa có phản hồi cho đánh giá này" icon="bi-reply" />
            @endif

            @can('reviews.respond')
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
            @endcan
        </x-admin.detail.panel>
    </div>
    @endif
</div>
@endsection
