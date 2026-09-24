@extends('layouts.app')

@section('title', 'Khuyến Mãi - Giặt Ủi Pro')
@section('page-title', 'Khuyến Mãi')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('promotions.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Thêm khuyến mãi
        </a>
    </div>
    <form action="{{ route('promotions.index') }}" method="GET" class="d-flex gap-2">
        <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            <option value="active" @selected(request('status') === 'active')>Đang chạy</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Tắt</option>
        </select>
        <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">Xóa</a>
    </form>
</div>

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
                    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có khuyến mãi</td></tr>
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
