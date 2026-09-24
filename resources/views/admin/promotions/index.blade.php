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

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>ID</th><th>Tên</th><th>Mã</th><th>Giảm</th><th>HSD</th><th>Trạng thái</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                    @forelse($promotions as $promotion)
                    <tr>
                        <td>{{ $promotion->id }}</td>
                        <td><strong>{{ $promotion->name }}</strong></td>
                        <td>{{ $promotion->code }}</td>
                        <td>{{ $promotion->discount }}</td>
                        <td>{{ $promotion->expires_at?->format('d/m/Y') ?: 'Không hạn' }}</td>
                        <td>
                            @if($promotion->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đang chạy</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Hết hạn</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('promotions.edit', $promotion) }}" class="btn btn-sm btn-outline-warning">Sửa</a>
                                <form action="{{ route('promotions.destroy', $promotion) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-search fa-2x mb-2 text-secondary d-block"></i>Không tìm thấy dữ liệu phù hợp</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($promotions->hasPages())
<div class="mt-3">
    {{ $promotions->appends(request()->query())->links('pagination::bootstrap-5') }}
</div>
@endif
@endsection
