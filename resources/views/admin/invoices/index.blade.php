@extends('layouts.app')
@section('title', 'Hóa Đơn - Giặt Ủi Pro')
@section('page-title', 'Hóa Đơn')
@section('content')
<div class="order-toolbar"><p class="text-muted mb-0">Quản lý hóa đơn và biên nhận của khách hàng.</p><a href="{{ route('invoices.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Tạo hóa đơn</a></div>
<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table-custom mb-0"><thead><tr><th>Số hóa đơn</th><th>Khách hàng</th><th>Mã đơn</th><th>Tổng tiền</th><th>Ngày lập</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<tr><td><strong>HD001</strong></td><td>Nguyễn Văn A</td><td>#DH001</td><td class="fw-semibold">250,000 VNĐ</td><td>20/09/2026</td><td><span class="badge-status badge-completed">Đã thanh toán</span></td><td><a href="{{ route('invoices.show', 1) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a></td></tr>
<tr><td><strong>HD002</strong></td><td>Trần Thị B</td><td>#DH002</td><td class="fw-semibold">180,000 VNĐ</td><td>19/09/2026</td><td><span class="badge-status badge-pending">Chưa thanh toán</span></td><td><a href="{{ route('invoices.show', 2) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a></td></tr>
</tbody></table></div></div></div>
@endsection
