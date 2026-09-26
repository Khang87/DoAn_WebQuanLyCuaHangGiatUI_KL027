@extends('layouts.app')

@section('title', 'Cài đặt - Sky Laundry')
@section('page-title', 'Cài đặt')

@section('content')
<div class="page-toolbar">
    <p class="text-muted page-toolbar__desc">Thiết lập thông tin cửa hàng, giờ làm việc và các tuỳ chọn vận hành.</p>
</div>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Cài đặt hệ thống</h5>
        <a href="{{ route('profile') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-person me-1"></i>Hồ sơ
        </a>
    </div>
    <div class="card-body">
        <div class="alert alert-info alert-dismissible fade show">
            <i class="bi bi-info-circle me-2"></i>Trang cài đặt hệ thống đang được cập nhật.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-secondary h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-gear-fill display-4 text-secondary mb-3"></i>
                        <h6 class="card-title">Cài đặt chung</h6>
                        <p class="card-text small text-muted mb-3">Tùy chỉnh tên cửa hàng, thông tin liên hệ, cài đặt hệ thống.</p>
                        <button class="btn btn-outline-secondary btn-sm" disabled>
                            Đang cập nhật
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-secondary h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-bell-fill display-4 text-secondary mb-3"></i>
                        <h6 class="card-title">Thông báo</h6>
                        <p class="card-text small text-muted mb-3">Quản lý cài đặt thông báo, email, SMS.</p>
                        <button class="btn btn-outline-secondary btn-sm" disabled>
                            Đang cập nhật
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
