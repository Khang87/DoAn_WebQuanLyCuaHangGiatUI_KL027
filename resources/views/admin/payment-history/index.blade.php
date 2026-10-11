@extends('layouts.app')
@section('title', 'Lịch sử thanh toán - Sky Laundry')
@section('page-title', 'Lịch sử thanh toán')

@section('content')
<div class="page-toolbar">
    <p class="text-muted page-toolbar__desc mb-0">Tra cứu và đối soát giao dịch đã ghi nhận. Các thao tác tại đây chỉ đọc.</p>
</div>
<div class="row g-3 mb-4" aria-label="Tổng tiền theo bộ lọc">
    @foreach(['Tổng đã thu' => $totals->collected, 'Đã hoàn tiền' => $totals->refunded, 'Thu ròng' => $totals->collected - $totals->refunded] as $label => $amount)
    <div class="col-12 col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted">{{ $label }}</div><div class="fs-4 fw-semibold">{{ number_format((float) $amount) }} VNĐ</div></div></div></div>
    @endforeach
</div>
<p class="small text-muted">Tổng trên toàn bộ kết quả lọc. Tổng đã thu gồm giao dịch thành công và đã hoàn tiền; giao dịch chờ/thất bại không tính vào tổng.</p>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('payment-history.index', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]) }}">Hôm nay</a>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('payment-history.index', ['date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->toDateString()]) }}">Tháng này</a>
</div>
@if($errors->any())
<div class="alert alert-danger" role="alert">
    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif
<form method="GET" action="{{ route('payment-history.index') }}" class="card mb-4">
    <div class="card-body"><div class="row g-3">
        <div class="col-12 col-md-6 col-xl-4">
            <label class="form-label" for="history-search">Tìm giao dịch</label>
            <input class="form-control" id="history-search" name="search" value="{{ old('search', request('search')) }}" maxlength="200" placeholder="Mã thanh toán, đơn hàng, khách hàng...">
        </div>
        <div class="col-6 col-xl-2">
            <label class="form-label" for="history-method">Phương thức</label>
            <select class="form-select" id="history-method" name="method">
                <option value="">Tất cả</option>
                <option value="cash" @selected(old('method', request('method')) === 'cash')>Tiền mặt</option>
                <option value="bank_transfer" @selected(old('method', request('method')) === 'bank_transfer')>Chuyển khoản / QR</option>
            </select>
        </div>
        <div class="col-6 col-xl-2">
            <label class="form-label" for="history-status">Trạng thái</label>
            <select class="form-select" id="history-status" name="status">
                <option value="">Tất cả</option>
                @foreach(\App\Enums\PaymentStatus::options() as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', request('status')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="history-from">Từ ngày</label>
            <input type="date" class="form-control" id="history-from" name="date_from" value="{{ old('date_from', request('date_from')) }}">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="history-to">Đến ngày</label>
            <input type="date" class="form-control" id="history-to" name="date_to" value="{{ old('date_to', request('date_to')) }}">
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <label class="form-label" for="history-collector">Người thực hiện</label>
            <select class="form-select" id="history-collector" name="collector_id">
                <option value="">Tất cả</option>
                @foreach($collectors as $collector)
                    <option value="{{ $collector->getKey() }}" @selected((string) old('collector_id', request('collector_id')) === (string) $collector->getKey())>{{ $collector->nhanVien?->HoTen ?? $collector->khachHang?->HoTen ?? $collector->TenDangNhap ?? 'Tài khoản '.$collector->getKey() }}</option>
                @endforeach
            </select>
        </div>
        @if(request('order_id'))<input type="hidden" name="order_id" value="{{ request('order_id') }}">@endif
        <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
            <label class="visually-hidden" for="history-sort">Sắp xếp</label>
            <select class="form-select w-auto" id="history-sort" name="sort">
                @foreach(['latest' => 'Mới nhất', 'oldest' => 'Cũ nhất', 'amount_desc' => 'Số tiền cao nhất', 'amount_asc' => 'Số tiền thấp nhất'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('sort', request('sort', 'latest')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Lọc</button>
            <a class="btn btn-outline-secondary" href="{{ route('payment-history.index') }}">Xóa bộ lọc</a>
        </div>
    </div></div>
</form>
<div class="card">
    <div class="card-body p-0 table-responsive">
        <table class="table-custom mb-0" aria-label="Danh sách lịch sử thanh toán">
            <thead><tr>
                <th scope="col">STT</th><th scope="col">Mã giao dịch</th><th scope="col">Đơn hàng</th>
                <th scope="col">Khách hàng</th><th scope="col">Số tiền</th><th scope="col">Phương thức</th>
                <th scope="col">Trạng thái</th><th scope="col">Người thực hiện</th><th scope="col">Thời gian</th><th scope="col">Chi tiết</th>
            </tr></thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr data-history-payment="{{ $payment->getKey() }}">
                        <td>{{ $payments->firstItem() + $loop->index }}</td>
                        <td class="fw-semibold">{{ $payment->MaGiaoDich ?: 'TT'.$payment->getKey() }}<div class="small text-muted">TT{{ $payment->getKey() }}</div></td>
                        <td>{{ $payment->donHang?->MaDonHang ?: '—' }}</td>
                        <td><div class="d-flex align-items-center gap-2">
                        <img src="{{ $payment->donHang?->khachHang?->avatar_url ?? asset('assets/images/user_1.jpg') }}" alt="Ảnh khách hàng" class="rounded-circle avatar-cover" width="32" height="32" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('assets/images/user_1.jpg') }}';">
                        <span>{{ $payment->donHang?->khachHang?->HoTen ?: '—' }}</span>
                    </div></td>
                        <td class="text-nowrap">{{ number_format($payment->SoTien) }} VNĐ</td>
                        <td>{{ $payment->getMethodLabel() }}</td>
                        <td><x-admin.status-badge :status="$payment->TrangThai" :enum="\App\Enums\PaymentStatus::class" /></td>
                        <td>{{ $payment->collectorName() }}</td>
                        <td class="text-nowrap">{{ $payment->ThoiGian?->format('d/m/Y H:i') ?: '—' }}</td>
                        <td><a class="btn btn-order-action view" href="{{ route('payment-history.show', $payment->getKey()) }}" aria-label="Xem giao dịch TT{{ $payment->getKey() }}"><i class="bi bi-eye" aria-hidden="true"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">Không có giao dịch phù hợp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $payments->links() }}</div>
@endsection
