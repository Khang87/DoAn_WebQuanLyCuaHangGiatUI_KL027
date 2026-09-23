@extends('layouts.app')
@section('title', 'Hóa Đơn - Giặt Ủi Pro')
@section('page-title', 'Hóa Đơn')

@section('content')
<div class="order-toolbar">
    <p class="text-muted mb-0">Quản lý hóa đơn và biên nhận của khách hàng.</p>
    <a href="{{ route('invoices.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i>Tạo hóa đơn
    </a>
</div>

<form action="{{ route('invoices.index') }}" method="GET" class="d-flex gap-2 mb-3">
    <div class="input-group" style="width: 200px;">
        <input type="text" name="search" class="form-control" placeholder="Tìm kiếm..." value="{{ request('search') }}">
    </div>
    <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
        <option value="">Tất cả trạng thái</option>
        @foreach($statuses as $value => $label)
            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @if(request('status'))
        <input type="hidden" name="status" value="{{ request('status') }}">
    @endif
    <button type="submit" class="btn btn-outline-secondary">Lọc</button>
    <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">Xóa</a>
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
                        <td><strong>#{{ $invoice->code }}</strong></td>
                        <td>{{ $invoice->order?->customer?->name ?: '-' }}</td>
                        <td>
                            @if($invoice->order)
                                <a href="{{ route('orders.show', $invoice->order) }}">#{{ $invoice->order->code }}</a>
                            @else
                                -
                            @endif
                        </td>
                        <td class="fw-semibold">{{ number_format($invoice->total) }} VNĐ</td>
                        <td>{{ $invoice->created_at?->format('d/m/Y') }}</td>
                        <td>
                            @if($invoice->status === 'paid')
                                <span class="badge-status badge-completed">Đã thanh toán</span>
                            @elseif($invoice->status === 'partial')
                                <span class="badge-status badge-warning">Một phần</span>
                            @else
                                <span class="badge-status badge-pending">Chưa thanh toán</span>
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
