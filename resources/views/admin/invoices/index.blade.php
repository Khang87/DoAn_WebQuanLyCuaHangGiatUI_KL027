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
        <a href="{{ route('invoices.export', request()->query()) }}" class="btn btn-outline-success rounded-3 me-2">
            <i class="fas fa-file-excel me-1"></i> Xuất Excel
        </a>
    </div>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm kiếm..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-4">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
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
                        <th>Tổng tiền</th>
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
                        <td class="fw-semibold">{{ number_format($invoice->total) }} VNĐ</td>
                        <td>{{ $invoice->created_at?->format('d/m/Y') }}</td>
                        <td>
                                @if($invoice->status === 'paid')
                                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đã thanh toán</span>
                                @elseif($invoice->status === 'partial')
                                    <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>Một phần</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>Chưa thanh toán</span>
                                @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có hóa đơn nào</td></tr>
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
