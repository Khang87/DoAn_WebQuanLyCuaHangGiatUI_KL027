@extends('layouts.app')
@section('title', 'Quản lý đánh giá - Sky Laundry')
@section('page-title', 'Quản lý đánh giá')
@section('content')
@php
    $averageValue = (float) ($averageRating ?? 0);
    $roundedAverage = round($averageValue);
    $totalCount = (int) ($totalReviews ?? (method_exists($reviews, 'total') ? $reviews->total() : count($reviews)));
@endphp
<div class="page-toolbar">
    <p class="text-muted page-toolbar__desc">Xem và phản hồi các đánh giá của khách hàng về chất lượng dịch vụ.</p>
</div>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="small text-muted mb-1 fw-medium">Điểm đánh giá trung bình</div>
        <div class="d-flex flex-wrap align-items-baseline gap-3">
            <span class="display-5 fw-bold text-warning">{{ number_format($averageValue, 1) }}</span>
            <span class="fs-5 text-warning text-nowrap" aria-label="{{ $averageValue }} trên 5 sao">
                @for($star = 1; $star <= 5; $star++)
                    @if ($star <= $roundedAverage)
                        <i class="fas fa-star text-warning"></i>
                    @else
                        <i class="far fa-star text-black-50 opacity-25"></i>
                    @endif
                @endfor
            </span>
            <div class="text-secondary small text-nowrap">Tổng <strong>{{ number_format($totalCount) }}</strong> đánh giá</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold text-dark mb-3">Bộ lọc đánh giá</h6>
        <form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center">
            @if(request('sort_by'))
                <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
            @endif
            @if(request('sort_order'))
                <input type="hidden" name="sort_order" value="{{ request('sort_order') }}">
            @endif

            <div class="col-12 col-md-auto flex-grow-1">
                <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden filter-search">
                    <span class="input-group-text bg-white border-end-0 ps-3">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm theo nội dung, tên khách hàng..." value="{{ request('search') }}">
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-auto">
                <select name="rating" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
                    <option value="">Tất cả số sao</option>
                    @for($star = 5; $star >= 1; $star--)
                        <option value="{{ $star }}" @selected((string) request('rating') === (string) $star)>{{ $star }} sao</option>
                    @endfor
                </select>
            </div>

            <div class="col-12 col-sm-6 col-md-auto">
                <x-admin.status-select
                    name="status"
                    id="filter-status"
                    :options="\App\Enums\ReviewStatus::options()"
                    placeholder="Tất cả trạng thái"
                    class="form-select form-select-sm filter-select shadow-sm rounded-3"
                    submit
                />
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-custom mb-0">
                <thead>
                    <tr>
                        <th scope="col">STT</th>
                        <th>Ngày đánh giá</th>
                        <th>Mã đơn hàng</th>
                        <th>Khách hàng</th>
                        <th>Đánh giá</th>
                        <th>Nội dung</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reviews as $review)
                    <tr>
                        <td>{{ $reviews->firstItem() + $loop->index }}</td>
                        <td>{{ $review->NgayDanhGia?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td><span class="fw-semibold text-dark">{{ $review->donHang?->MaDonHang ?: '—' }}</span></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <x-admin.customer-avatar :customer="$review->khachHang" />
                                <span class="fw-semibold">{{ $review->khachHang?->HoTen ?: '—' }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="text-warning">
                                @for($star = 1; $star <= 5; $star++)
                                    @if ($star <= (int) $review->SoSao)
                                        <i class="fas fa-star text-warning"></i>
                                    @else
                                        <i class="far fa-star text-black-50 opacity-25"></i>
                                    @endif
                                @endfor
                            </div>
                            <small class="text-muted">{{ $review->SoSao }}/5</small>
                        </td>
                        <td style="max-width: 320px;">
                            <div class="text-truncate">{{ $review->BinhLuan ?: 'Không có nội dung' }}</div>
                            @if($review->shop_response)
                                <small class="text-success">Đã phản hồi</small>
                            @endif
                        </td>
                        <td>
                            <x-admin.status-badge :status="$review->status" :enum="\App\Enums\ReviewStatus::class" />
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                @can('reviews.view')
                                    <a href="{{ route('reviews.show', $review) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @endcan
                                @can('reviews.respond')
                                    <a href="{{ route('reviews.show', ['review' => $review, 'respond' => 1]) }}#phan-hoi" class="btn btn-order-action edit" title="Phản hồi"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('reviews.toggle')
                                    <x-admin.detail.confirm-form
                                        :action="route('reviews.toggle', $review)"
                                        method="PATCH"
                                        title="{{ $review->status === 'hidden' ? 'Hiện' : 'Ẩn' }} đánh giá này?"
                                        text="Trạng thái hiển thị của đánh giá sẽ thay đổi."
                                        label="{{ $review->status === 'hidden' ? 'Hiện đánh giá' : 'Ẩn đánh giá' }}"
                                        :icon="$review->status === 'hidden' ? 'bi-eye' : 'bi-eye-slash'"
                                        variant="btn-order-action"
                                        size=""
                                        :iconOnly="true"
                                        color="#64748b"
                                        :iconName="'question'"
                                    />
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Chưa có đánh giá nào</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-admin.pagination :paginator="$reviews" label="đánh giá" />
@endsection