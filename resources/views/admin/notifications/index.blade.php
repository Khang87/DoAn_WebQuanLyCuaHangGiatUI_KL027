@extends('layouts.app')
@section('title', 'Thông Báo - Sky Laundry')
@section('page-title', 'Thông Báo')

@section('content')
<div class="order-toolbar d-flex justify-content-between align-items-center mb-4">
    <p class="text-muted mb-0">Các cập nhật mới từ hoạt động cửa hàng.</p>
    <a href="{{ route('notifications.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i>Tạo thông báo
    </a>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm thông báo..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="read" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="unread" @selected(request('read') === 'unread')>Chưa đọc</option>
            <option value="read" @selected(request('read') === 'read')>Đã đọc</option>
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            @forelse($notifications as $notification)
            <div class="list-group-item px-0 d-flex gap-3 {{ $notification->read_at ? '' : 'bg-light fw-bold' }}">
                <i class="bi bi-bag-check text-success fs-4"></i>
                <div class="flex-grow-1">
                    <div>{{ $notification->title }}</div>
                    <small class="text-muted">{{ Str::limit($notification->message, 80) }}</small>
                </div>
                <div class="text-end">
                    <small class="text-muted">{{ $notification->created_at?->format('d/m H:i') }}</small>
                </div>
            </div>
            @empty
            <div class="p-4 text-center text-muted">Chưa có thông báo nào.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
