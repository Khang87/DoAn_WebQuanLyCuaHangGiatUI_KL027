@extends('layouts.app')
@section('title', 'Thông báo - Sky Laundry')
@section('page-title', 'Thông báo')

@section('content')
<!-- Page Actions: nút "Them" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('notifications.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Them thông báo
    </a>
    <p class="text-muted page-toolbar__desc">Các cập nhật mới từ hoạt động cửa hàng.</p>
    <div class="page-toolbar__actions ms-auto">
        <form action="{{ route('notifications.mark-all-read') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm" title="Đánh dấu tất cả là đã đọc">
                <i class="bi bi-check2-all"></i> Đánh dấu tất cả đã đọc
            </button>
        </form>
    </div>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm thông báo..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="read" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
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
            {{-- Chưa đọc: in đậm + nền nhấn mạnh. Đã đọc: chữ thường, màu nhạt. --}}
            <a href="{{ route('notifications.show', $notification->id) }}"
               id="notification-{{ $notification->id }}"
               class="list-group-item notification-row px-0 d-flex gap-3 {{ $notification->read_at ? 'is-read' : 'is-unread' }} notification-link">
                <i class="bi bi-bell-fill fs-4 {{ $notification->read_at ? 'notification-icon-read' : 'notification-icon-unread' }}"></i>
                <div class="flex-grow-1">
                    <div class="notification-title">{{ $notification->title }}</div>
                    <small class="notification-body d-block">{{ Str::limit($notification->message, 80) }}</small>
                </div>
                <div class="text-end">
                    <small class="text-muted">{{ $notification->created_at?->format('d/m H:i') }}</small>
                    @if(! $notification->read_at)
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning ms-2">Moi</span>
                    @endif
                </div>
            </a>
            @empty
            <div class="p-4 text-center text-muted">Chưa có thông báo nào.</div>
            @endforelse
        </div>
    </div>
</div>

@if($notifications->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $notifications->firstItem() }} - {{ $notifications->lastItem() }} của {{ $notifications->total() }} thông báo</div>
        <ul class="pagination mb-0">
            @if ($notifications->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $notifications->appends(request()->query())->url($notifications->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($notifications->getUrlRange(max(1, $notifications->currentPage() - 2), min($notifications->lastPage(), $notifications->currentPage() + 2)) as $page => $url)
                @if ($page == $notifications->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $notifications->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($notifications->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $notifications->appends(request()->query())->url($notifications->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const targetId = urlParams.get('id');

    if (targetId) {
        const targetElement = document.getElementById('notification-' + targetId);
        if (targetElement) {
            // Cuộn mượt tới phần tử
            targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Thêm hiệu ứng Highlight
            targetElement.classList.add('table-warning', 'border', 'border-primary', 'notification-highlight');
            setTimeout(() => {
                targetElement.classList.remove('table-warning', 'border', 'border-primary', 'notification-highlight');
            }, 3000);
        }
    }
});
</script>
<style>
.notification-highlight {
    animation: notification-pulse 1.5s ease-out;
}
@keyframes notification-pulse {
    0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.5); }
    70% { box-shadow: 0 0 0 12px rgba(37, 99, 235, 0); }
    100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
}
</style>
@endsection