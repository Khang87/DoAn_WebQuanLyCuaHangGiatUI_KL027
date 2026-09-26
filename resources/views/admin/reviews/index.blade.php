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
        <div class="row align-items-center g-4">
            {{-- Cột trái: điểm đánh giá trung bình --}}
            <div class="col-12 col-md-4 col-lg-3 text-center text-md-start border-md-end pe-md-4">
                <div class="small text-muted mb-1 fw-medium">Điểm đánh giá trung bình</div>
                <div class="display-5 fw-bold text-warning mb-1">{{ number_format($averageValue, 1) }}</div>
                <div class="fs-5 text-warning mb-2">
                    @for($star = 1; $star <= 5; $star++)
                        <i class="fas fa-star{{ $star <= $roundedAverage ? '' : '-regular' }}"></i>
                    @endfor
                </div>
                <div class="text-secondary small">Tổng <strong>{{ number_format($totalCount) }}</strong> đánh giá</div>
            </div>

            {{-- Cột phải: bộ lọc nằm ngang, cùng format với các module khác --}}
            <div class="col-12 col-md-8 col-lg-9 ps-md-4">
                <h6 class="fw-bold text-dark mb-3">Bộ lọc đánh giá</h6>
                <form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center">
                    @if(request('sort_by'))
                        <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
                    @endif
                    @if(request('sort_order'))
                        <input type="hidden" name="sort_order" value="{{ request('sort_order') }}">
                    @endif

                    <div class="col-12 col-md-auto flex-grow-1">
                        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-end-0 ps-3">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm theo nội dung, tên khách hàng..." value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-md-auto">
                        <select name="rating" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
                            <option value="">-- Tất cả số sao --</option>
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
                            placeholder="-- Tất cả trạng thái --"
                            class="form-select form-select-sm filter-select shadow-sm rounded-3"
                            submit
                        />
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-custom mb-0">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã đánh giá</th>
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
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $review->code ?? 'DG' . str_pad($review->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td><span class="fw-semibold text-dark">{{ $review->order?->code ?: '—' }}</span></td>
                        <td>{{ $review->customer?->name ?: $review->order?->customer?->name ?: '—' }}</td>
                        <td>
                            <div class="text-warning">
                                @for($star = 1; $star <= 5; $star++)
                                    <i class="fas fa-star{{ $star <= $review->rating ? '' : '-regular' }}"></i>
                                @endfor
                            </div>
                            <small class="text-muted">{{ $review->rating }}/5</small>
                        </td>
                        <td style="max-width: 320px;">
                            <div class="text-truncate">{{ $review->content ?: $review->comment ?: 'Không có nội dung' }}</div>
                            @if($review->shop_response)
                                <small class="text-success">Đã phản hồi</small>
                            @endif
                        </td>
                        <td>
                            <x-admin.status-badge :status="$review->status" :enum="\App\Enums\ReviewStatus::class" />
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('reviews.show', $review) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('reviews.show', $review) }}#phan-hoi" class="btn btn-order-action edit" title="Phản hồi"><i class="bi bi-reply"></i></a>
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

@if(method_exists($reviews, 'links'))
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $reviews->firstItem() }} - {{ $reviews->lastItem() }} của {{ $reviews->total() }} đánh giá</div>
        <ul class="pagination mb-0">
            @if ($reviews->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $reviews->appends(request()->query())->url($reviews->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($reviews->getUrlRange(max(1, $reviews->currentPage() - 2), min($reviews->lastPage(), $reviews->currentPage() + 2)) as $page => $url)
                @if ($page == $reviews->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $reviews->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($reviews->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $reviews->appends(request()->query())->url($reviews->currentPage() + 1) }}">{{ $page }}</a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection