@extends('layouts.app')
@section('title', 'Bảng Giá - Giặt Ủi Pro')
@section('page-title', 'Bảng Giá')

@section('content')
<div class="order-toolbar">
    <p class="text-muted mb-0">Giá dịch vụ theo kg, món, cái hoặc đôi.</p>
    <a href="{{ route('pricings.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i>Thêm bảng giá
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Dịch vụ</th>
                        <th>Đơn vị</th>
                        <th>Đơn giá</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pricings as $pricing)
                    <tr>
                        <td><strong>{{ $pricing->name }}</strong></td>
                        <td>{{ $pricing->unit ?: 'kg' }}</td>
                        <td class="fw-semibold text-primary">{{ number_format($pricing->price) }} VNĐ</td>
                        <td>
                            @if($pricing->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đang áp dụng</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Khóa</span>
                            @endif
                        </td>
                        <td>{{ $pricing->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('pricings.show', $pricing) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('pricings.edit', $pricing) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('pricings.destroy', $pricing) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có bảng giá nào</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
