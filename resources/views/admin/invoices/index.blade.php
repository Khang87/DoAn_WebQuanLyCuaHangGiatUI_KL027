@extends('layouts.app')
@section('title', 'Hóa Đơn - Sky Laundry')
@section('page-title', 'Hóa Đơn')

@section('content')
<div class="order-toolbar d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Quản lý hóa đơn và biên nhận của khách hàng.</p>
    <div class="d-flex gap-2">
        <a href="{{ route('invoices.create') }}" class="btn btn-primary rounded-3">
            <i class="bi bi-plus-lg me-1"></i> Tạo hóa đơn
        </a>
    </div>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-4">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm kiếm..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            @foreach($statuses ?? \App\Enums\RecordStatus::options() as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-2">
        <input type="date" name="date_from" class="form-control shadow-sm rounded-3 py-2" value="{{ request('date_from') }}" placeholder="Từ ngày">
    </div>
    <div class="col-12 col-md-2">
        <input type="date" name="date_to" class="form-control shadow-sm rounded-3 py-2" value="{{ request('date_to') }}" placeholder="Đến ngày">
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Số hóa đơn</th>
                        <th>Khách hàng</th>
                        <th>Mã đơn</th>
                        <th>Tạm tính</th>
                        <th>Giảm giá</th>
                        <th>Phí giao hàng</th>
                        <th>Tổng thanh toán</th>
                        <th>Ngày lập</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr>
                        <td><strong>{{ $invoice->code }}</strong></td>
                        <td>{{ $invoice->order?->customer?->name ?: '-' }}</td>
                        <td>
                            @if($invoice->order)
                                <a href="{{ route('orders.show', $invoice->order) }}">{{ $invoice->order->code }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ number_format($invoice->total_amount) }} VNĐ</td>
                        <td class="text-danger">{{ number_format($invoice->discount_amount) }} VNĐ</td>
                        <td>{{ number_format($invoice->delivery_fee) }} VNĐ</td>
                        <td class="fw-semibold text-primary">{{ number_format($invoice->grand_total) }} VNĐ</td>
                        <td>{{ $invoice->invoice_date?->format('d/m/Y') ?? $invoice->created_at?->format('d/m/Y') }}</td>
                        <td class="align-middle">
                            <div class="d-flex align-items-center justify-content-start gap-2 flex-wrap">
                                <span class="badge {{ $invoice->getStatusBadgeClass() }} px-3 py-2 rounded-pill">
                                    <i class="fas fa-{{ $invoice->status === 'paid' ? 'check-circle' : ($invoice->status === 'partial' ? 'hourglass' : 'x-circle') }} me-1"></i>{{ $invoice->getStatusLabel() }}
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="d-inline" id="deleteInvoiceForm_{{ $invoice->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">Chưa có hóa đơn nào</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($invoices->hasPages())
<div class="mt-3">
    {{ $invoices->appends(request()->query())->links('pagination::bootstrap-5') }}
</div>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deleteInvoiceForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa?')) {
                        form.submit();
                    }
                    return;
                }
                
                Swal.fire({
                    title: 'Xóa hóa đơn?',
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
