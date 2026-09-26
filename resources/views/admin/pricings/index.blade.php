@extends('layouts.app')
@section('title', 'Bảng giá - Sky Laundry')
@section('page-title', 'Bảng giá')
@section('content')
<div class="order-toolbar">
    <p class="text-muted mb-0">Giá dịch vụ theo loại đồ và đơn vị tính.</p>
    <a href="{{ route('pricings.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Thêm bảng giá</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã</th>
                        <th>Dịch vụ</th>
                        <th>Loại đồ giặt</th>
                        <th>Đơn vị</th>
                        <th>Đơn giá</th>
                        <th>Ngày áp dụng</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pricings as $pricing)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $pricing->code ?? 'PG' . str_pad($pricing->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td><strong>{{ $pricing->service?->name ?: $pricing->name ?: '—' }}</strong></td>
                        <td>{{ $pricing->garment?->name ?: $pricing->garment_name ?: '—' }}</td>
                        <td>{{ $pricing->unit ?: 'kg' }}</td>
                        <td class="fw-semibold text-primary">{{ number_format($pricing->price) }} VNĐ</td>
                        <td>{{ $pricing->effective_date?->format('d/m/Y') ?: $pricing->created_at?->format('d/m/Y') ?: '—' }}</td>
                        <td>
                            @if($pricing->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>{{ $pricing->status_label ?? 'Đang hoạt động' }}</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-2 rounded-pill">{{ $pricing->status_label ?? 'Tạm ngưng' }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('pricings.show', $pricing) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('pricings.edit', $pricing) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('pricings.destroy', $pricing) }}" method="POST" class="d-inline" id="deletePricingForm_{{ $pricing->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Chưa có bảng giá nào</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deletePricingForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa bảng giá?',
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