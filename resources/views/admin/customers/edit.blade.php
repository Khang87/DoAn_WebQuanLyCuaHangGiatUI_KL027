@extends('layouts.app')

@section('title', 'Chỉnh Sửa Khách Hàng - Giặt Ủi Pro')
@section('page-title', 'Chỉnh Sửa Khách Hàng')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Nguyễn Văn A</h5>
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('customers.update', 1) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Họ tên</label>
                    <input type="text" class="form-control" name="name" value="Nguyễn Văn A" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="nguyenvana@email.com" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" class="form-control" name="phone" value="0901234567" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại khách hàng</label>
                    <select class="form-select" name="type">
                        <option value="new">Mới</option>
                        <option value="regular">Thường</option>
                        <option value="vip" selected>VIP</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Địa chỉ</label>
                    <textarea class="form-control" name="address" rows="3">123 Đường ABC, Quận 1, TP.HCM</textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
