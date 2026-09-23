@extends('layouts.app')
@section('title', 'Thanh Toán - Giặt Ủi Pro')
@section('page-title', 'Thanh Toán')
@section('content')
<div class="order-toolbar"><p class="text-muted mb-0">Theo dõi các khoản thu của đơn hàng.</p><a href="{{ route('payments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Ghi nhận thanh toán</a></div>
<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table-custom mb-0"><thead><tr><th>Mã thanh toán</th><th>Mã đơn</th><th>Khách hàng</th><th>Số tiền</th><th>Phương thức</th><th>Trạng thái</th></tr></thead><tbody>
<tr><td><strong>TT001</strong></td><td>#DH001</td><td>Nguyễn Văn A</td><td class="fw-semibold">250,000 VNĐ</td><td>Tiền mặt</td><td><span class="badge-status badge-completed">Đã thanh toán</span></td></tr>
<tr><td><strong>TT002</strong></td><td>#DH002</td><td>Trần Thị B</td><td class="fw-semibold">180,000 VNĐ</td><td>Chuyển khoản</td><td><span class="badge-status badge-pending">Chờ thanh toán</span></td></tr>
</tbody></table></div></div></div>
@endsection
