@extends('layouts.app')
@section('title', 'Thanh toán - Sky Laundry')
@section('page-title', 'Thanh toán')
@section('content')
<div class="page-toolbar">
    <p class="text-muted page-toolbar__desc mb-0">Chọn đơn hàng cần thu tiền để ghi nhận thanh toán.</p>
    <a href="{{ route('payment-history.index') }}" class="btn btn-outline-secondary"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Lịch sử thanh toán</a>
</div>
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<form action="{{ route('payments.index') }}" method="GET" class="card mb-4">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div class="flex-grow-1">
            <label for="collection-search" class="form-label">Tìm đơn hàng hoặc khách hàng</label>
            <input id="collection-search" name="search" class="form-control" maxlength="200" value="{{ old('search', request('search')) }}" placeholder="Mã đơn hàng, tên khách hàng...">
        </div>
        <button class="btn btn-primary" type="submit">Tìm kiếm</button>
        <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Xóa bộ lọc</a>
    </div>
</form>
<div class="card">
    <div class="card-body p-0 table-responsive">
        <table class="table-custom mb-0" aria-label="Đơn hàng cần thanh toán">
            <thead><tr><th scope="col">STT</th><th scope="col">Đơn hàng</th><th scope="col">Khách hàng</th><th scope="col">Trạng thái xử lý</th><th scope="col">Số tiền cần thu</th><th scope="col">Thao tác</th></tr></thead>
            <tbody>
            @forelse($orders as $order)
                <tr data-collection-order="{{ $order->getKey() }}">
                    <td>{{ $orders->firstItem() + $loop->index }}</td>
                    <td class="fw-semibold">{{ $order->MaDonHang }}</td>
                    <td><div class="d-flex align-items-center gap-2">
                        <img src="{{ $order->khachHang?->avatar_url ?? asset('assets/images/user_1.jpg') }}" alt="Ảnh khách hàng" class="rounded-circle avatar-cover" width="32" height="32" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('assets/images/user_1.jpg') }}';">
                        <span>{{ $order->khachHang?->HoTen ?: '—' }}</span>
                    </div></td>
                    <td><x-admin.status-badge :status="$order->TrangThai" :enum="\App\Enums\OrderStatus::class" /></td>
                    <td>{{ number_format((float) ($order->hoaDons->first()?->ThanhTien ?? $order->ThanhTien)) }} VNĐ</td>
                    <td>@can('payments.create')<a class="btn btn-primary btn-sm" href="{{ route('payments.create', ['order_id' => $order->getKey()]) }}">Thu tiền</a>@else<span class="text-muted">Chỉ xem</span>@endcan</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Không có đơn hàng cần thu tiền phù hợp.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())<div class="card-footer">{{ $orders->links() }}</div>@endif
</div>
@endsection
