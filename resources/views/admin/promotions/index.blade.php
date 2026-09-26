@extends('layouts.app')

@section('title', 'Chương trình khuyến mãi - Sky Laundry')
@section('page-title', 'Chương trình khuyến mãi')

@section('content')
<div class="order-toolbar d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('promotions.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i>Thêm chương trình
    </a>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm theo ID, mã khuyến mãi, tên chương trình..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-4">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            @foreach($statuses ?? \App\Enums\RecordStatus::options() as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>

<!-- Promotions Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        @php
                            $currentSortBy = request('sort_by');
                            $currentSortOrder = request('sort_order', 'desc');
                            $nextOrderId = ($currentSortBy === 'id' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderName = ($currentSortBy === 'name' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderCode = ($currentSortBy === 'code' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderDiscount = ($currentSortBy === 'discount' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderExpires = ($currentSortBy === 'expires_at' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                        @endphp
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => $nextOrderId]) }}" class="text-dark text-decoration-none">
                                ID
                                @if($currentSortBy === 'id') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => $nextOrderName]) }}" class="text-dark text-decoration-none">
                                Tên chương trình
                                @if($currentSortBy === 'name') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'code', 'sort_order' => $nextOrderCode]) }}" class="text-dark text-decoration-none">
                                Mã KM
                                @if($currentSortBy === 'code') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'discount', 'sort_order' => $nextOrderDiscount]) }}" class="text-dark text-decoration-none">
                                Giá trị giảm
                                @if($currentSortBy === 'discount') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'expires_at', 'sort_order' => $nextOrderExpires]) }}" class="text-dark text-decoration-none">
                                Hạn sử dụng
                                @if($currentSortBy === 'expires_at') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promotions as $promotion)
                    @php
                        $isExpired = $promotion->expires_at && $promotion->expires_at->lt(today());
                    @endphp
                    <tr>
                        <td><strong>{{ $promotion->id }}</strong></td>
                        <td><strong>{{ $promotion->name ?: '-' }}</strong></td>
                        <td>{{ $promotion->code ?: '-' }}</td>
                        <td>{{ $promotion->discount ?: '-' }}</td>
                        <td>{{ $promotion->expires_at?->format('d/m/Y') ?: 'Không hạn' }}</td>
                        <td>
                            @if($isExpired)
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-2 rounded-pill"><i class="far fa-hourglass me-1"></i>{{ $promotion->status_label ?? 'Đã hết hạn' }}</span>
                            @elseif($promotion->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>{{ $promotion->status_label ?? 'Đang hoạt động' }}</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-times-circle me-1"></i>{{ $promotion->status_label ?? 'Tạm ngưng' }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('promotions.edit', $promotion) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('promotions.destroy', $promotion) }}" method="POST" class="d-inline" id="deletePromotionForm_{{ $promotion->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                     </tr>
                     @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Chưa có dữ liệu nào</td>
                    </tr>
                    @endempty
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($promotions->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $promotions->firstItem() }} - {{ $promotions->lastItem() }} của {{ $promotions->total() }} khuyến mãi</div>
        <ul class="pagination mb-0">
            @if ($promotions->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $promotions->appends(request()->query())->url($promotions->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($promotions->getUrlRange(max(1, $promotions->currentPage() - 2), min($promotions->lastPage(), $promotions->currentPage() + 2)) as $page => $url)
                @if ($page == $promotions->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $promotions->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($promotions->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $promotions->appends(request()->query())->url($promotions->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deletePromotionForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Xóa?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa khuyến mãi?',
                    text: 'Hành động này không thể hoàn tác.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Xóa',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush