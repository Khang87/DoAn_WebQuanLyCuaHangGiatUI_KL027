@extends('layouts.app')

@php($isInvoiceEdit = request()->routeIs('invoices.edit', 'invoices.update'))
@section('title', ($isInvoiceEdit ? 'Không thể chỉnh sửa hóa đơn' : 'Không đủ quyền').' - Sky Laundry')
@section('page-title', $isInvoiceEdit ? 'Không thể chỉnh sửa hóa đơn' : 'Không đủ quyền')

@section('content')
<div class="card">
    <div class="card-body text-center py-5">
        <div class="display-4 fw-bold text-warning mb-3">
            <i class="bi bi-shield-lock"></i>
        </div>
        <h4 class="mb-2">{{ $isInvoiceEdit ? 'Không thể chỉnh sửa hóa đơn' : 'Chỉ Chủ cửa hàng được phép thực hiện' }}</h4>
        <p class="text-muted mb-4">{{ $message }}</p>

        <div class="alert alert-warning d-inline-block text-start mb-4">
            <div class="small">
                @if($isInvoiceEdit)
                    Hóa đơn đã thanh toán không thể chỉnh sửa, kể cả với tài khoản Chủ cửa hàng.
                @else
                    <strong>Quy tắc bảo mật:</strong> đơn hàng / hóa đơn đã quyết toán là
                    chốt số tiền. Chỉ tài khoản <strong>Chủ cửa hàng</strong> mới được
                    sửa, xóa hoặc hoàn tiền. Quản lý và Nhân viên không được cấp các
                    quyền này dù có tích trong ma trận phân quyền.
                @endif
            </div>
        </div>

        <div>
            <a href="{{ url()->previous() }}" class="btn btn-primary">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                <i class="bi bi-house me-1"></i>Trang chủ
            </a>
        </div>
    </div>
</div>
@endsection
