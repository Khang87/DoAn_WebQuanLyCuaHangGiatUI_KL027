@extends('layouts.app')
@section('title', 'Hóa đơn - Sky Laundry')
@section('page-title', 'Hóa đơn')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('invoices.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm hóa đơn
    </a>
    <p class="text-muted page-toolbar__desc">Quản lý hóa đơn và biên nhận của khách hàng.</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm kiếm..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="$statuses ?? \App\Enums\InvoiceStatus::options()"
            placeholder="-- Tất cả trạng thái --"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <input type="date" name="date_from" class="form-control form-control-sm shadow-sm rounded-3 filter-date" value="{{ request('date_from') }}" placeholder="Từ ngày">
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <input type="date" name="date_to" class="form-control form-control-sm shadow-sm rounded-3 filter-date" value="{{ request('date_to') }}" placeholder="Đến ngày">
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Mã hóa đơn</th>
                        <th>Khách hàng</th>
                        <th>Mã đơn hàng</th>
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
                                {{ $invoice->order->code }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ number_format($invoice->total_amount) }} VNĐ</td>
                        <td class="text-danger">{{ number_format($invoice->discount_amount) }} VNĐ</td>
                        <td>{{ number_format($invoice->delivery_fee) }} VNĐ</td>
                        <td class="fw-semibold text-dark">{{ number_format($invoice->grand_total) }} VNĐ</td>
                        <td>{{ $invoice->invoice_date?->format('d/m/Y') ?? $invoice->created_at?->format('d/m/Y') }}</td>
                        <td class="align-middle">
                            <div class="d-flex align-items-center justify-content-start gap-2 flex-wrap">
                                <x-admin.status-badge :status="$invoice->status" :enum="\App\Enums\InvoiceStatus::class" />
                            </div>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @if(! $invoice->isPaid())
                                    <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="d-inline" id="deleteInvoiceForm_{{ $invoice->id }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                    </form>
                                @elseif(auth()->user()?->canPermission('invoices.edit_paid'))
                                    @can('invoices.edit')
                                        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-order-action edit" title="Sửa hóa đơn đã thanh toán (Chủ cửa hàng)"><i class="bi bi-pencil"></i></a>
                                    @endcan
                                    @can('invoices.delete')
                                        <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="d-inline" id="deleteInvoiceForm_{{ $invoice->id }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-order-action delete" title="Xóa hóa đơn đã thanh toán (Chủ cửa hàng)"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success px-3 py-2 rounded-pill" title="Đã thanh toán - Chủ cửa hàng được phép điều chỉnh">
                                        <i class="bi bi-shield-check me-1"></i>Đã thanh toán
                                    </span>
                                @else
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success px-3 py-2 rounded-pill" title="Đã thanh toán nên không thể sửa hoặc xóa">
                                        <i class="bi bi-lock me-1"></i>Đã thanh toán
                                    </span>
                                @endif
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
