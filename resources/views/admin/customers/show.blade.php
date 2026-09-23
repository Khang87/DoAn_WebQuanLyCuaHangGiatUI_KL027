@extends('layouts.app')

@section('title', 'Chi Tiết Khách Hàng - Giặt Ủi Pro')
@section('page-title', 'Chi Tiết Khách Hàng')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">Nguyễn Văn A</h5>
                    <span class="badge bg-warning text-dark">VIP</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Email</div>
                        <div class="fw-semibold">nguyenvana@email.com</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Số điện thoại</div>
                        <div class="fw-semibold">0901234567</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Địa chỉ</div>
                        <div class="fw-semibold">123 Đường ABC, Quận 1, TP.HCM</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Điểm tích lũy</div>
                        <div class="fw-semibold text-primary">1,250 điểm</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Ngày đăng ký</div>
                        <div class="fw-semibold">01/01/2024</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Trạng thái</div>
                        <div class="fw-semibold">Hoạt động</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Thao tác</h6>
                <div class="d-grid gap-2">
                    <a href="{{ route('customers.edit', 1) }}" class="btn btn-warning">Chỉnh sửa</a>
                    <form action="{{ route('customers.destroy', 1) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa khách hàng?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">Xóa khách hàng</button>
                    </form>
                    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Quay lại danh sách</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
