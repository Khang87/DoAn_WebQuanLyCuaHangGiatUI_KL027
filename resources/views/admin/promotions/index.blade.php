@extends('layouts.app')

@section('title', 'Chương Trình Khuyến Mãi - Sky Laundry')
@section('page-title', 'Chương Trình Khuyến Mãi')

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
            <option value="active" @selected(request('status') === 'active')>Đang chạy</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Tắt</option>
        </select>
    </div>
</form>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">ID</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Tên chương trình</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Mã KM</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Giá trị giảm</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">HSD</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Trạng thái</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promotions as $promotion)
                    <tr>
                        <td class="text-start align-middle py-3 fw-bold text-dark">{{ $promotion->id }}</td>
                        <td class="text-start align-middle py-3 fw-bold text-muted">{{ $promotion->name ?: '-' }}</td>
                        <td class="text-start align-middle py-3 text-muted">{{ $promotion->code ?: '-' }}</td>
                        <td class="text-start align-middle py-3 text-muted">{{ $promotion->discount ?: '-' }}</td>
                        <td class="text-start align-middle py-3 text-muted">{{ $promotion->expires_at?->format('d/m/Y') ?: 'Không hạn' }}</td>
                        <td class="text-start align-middle py-3">
                            @if($promotion->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i>Đang chạy</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2"><i class="fas fa-times-circle me-1"></i>Hết hạn</span>
                            @endif
                        </td>
                        <td class="text-start align-middle py-3">
                            <div class="d-flex align-items-center gap-1">
                                <a href="{{ route('promotions.edit', $promotion) }}" class="btn btn-sm btn-outline-warning" title="Chỉnh sửa"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('promotions.destroy', $promotion) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fas fa-search fa-2x mb-2 text-secondary d-block"></i>
                            Không tìm thấy dữ liệu phù hợp
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($promotions->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            Hiển thị {{ $promotions->firstItem() }} - {{ $promotions->lastItem() }} của {{ $promotions->total() }} khuyến mãi
        </div>
        {{ $promotions->appends(request()->query())->links('pagination::bootstrap-5') }}
    </div>
</nav>
@endif
@endsection
