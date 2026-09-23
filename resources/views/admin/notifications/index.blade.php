@extends('layouts.app')
@section('title', 'Thông Báo - Giặt Ủi Pro')
@section('page-title', 'Thông Báo')

@section('content')
<div class="order-toolbar">
    <p class="text-muted mb-0">Các cập nhật mới từ hoạt động cửa hàng.</p>
    <a href="{{ route('notifications.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i>Tạo thông báo
    </a>
</div>

<form action="{{ route('notifications.index') }}" method="GET" class="d-flex gap-2 mb-3">
    <div class="input-group" style="width: 250px;">
        <input type="text" name="search" class="form-control" placeholder="Tìm thông báo..." value="{{ request('search') }}">
        <button class="btn btn-outline-secondary" type="submit">
            <i class="bi bi-search"></i>
        </button>
    </div>
    <select name="read" class="form-select" style="width: auto;" onchange="this.form.submit()">
        <option value="">Tất cả</option>
        <option value="unread" @selected(request('read') === 'unread')>Chưa đọc</option>
        <option value="read" @selected(request('read') === 'read')>Đã đọc</option>
    </select>
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
