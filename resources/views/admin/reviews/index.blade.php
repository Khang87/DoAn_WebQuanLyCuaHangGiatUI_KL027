@extends('layouts.app')
@section('title', 'Quản lý đánh giá - Sky Laundry')
@section('page-title', 'Quản lý đánh giá')
@section('content')
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="small text-muted">Điểm đánh giá trung bình</div>
                <div class="display-3 fw-bold text-warning">{{ number_format((float)($averageRating ?? 0), 1) }}</div>
                <div class="fs-4 text-warning mb-2">
                    @for($star = 1; $star <= 5; $star++)
                        <i class="fas fa-star{{ $star <= round((float)($averageRating ?? 0)) ? '' : '-regular' }}"></i>
                    @endfor
                </div>
                <div class="text-muted">Tổng {{ number_format($totalReviews ?? (method_exists($reviews, 'total') ? $reviews->total() : count($reviews))) }} đánh giá</div>
            </div>
        </div>
    </div>
</div>
<form action="{{ url()->current() }}" method="GET" class="card h-100">
    <div class="card-body">
        <div class="small text-muted mb-3">Bộ lọc đánh giá</div>
        <div class="mb-3">
            <label class="form-label" for="rating">Đánh giá</label>
            <select class="form-select" id="rating" name="rating" onchange="this.form.submit()">
                <option value="">Tất cả số sao</option>
                @for($star = 1; $star <= 5; $star++)
                    <option value="{{ $star }}" @selected((string)request('rating') === (string)$star)>{{ $star }} sao</option>
                @endfor
            </select>
        </div>
        <div>
            <label class="form-label" for="status">Trạng thái</label>
            <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                <option value="">Tất cả trạng thái</option>
                <option value="visible" @selected(request('status') === 'visible')>Hiển thị</option>
                <option value="hidden" @selected(request('status') === 'hidden')>Ẩn</option>
            </select>
        </div>
    </div>
</form>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-custom mb-0">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã</th>
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
                        <td><a href="{{ route('orders.show', $review->order_id) }}" class="fw-semibold text-decoration-none">{{ $review->order?->code ?: '—' }}</a></td>
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
                            @if(($review->status ?? 'visible') === 'hidden')
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-2 rounded-pill">Ẩn</span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">Hiển thị</span>
                            @endif
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