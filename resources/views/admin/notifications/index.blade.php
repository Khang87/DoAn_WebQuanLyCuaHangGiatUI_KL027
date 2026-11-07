@extends('layouts.app')
@section('title', 'Thông báo - Sky Laundry')
@section('page-title', 'Thông báo')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    @can('notifications.create')
        <a href="{{ route('notifications.create') }}" class="btn btn-create">
            <i class="bi bi-plus-lg"></i>Thêm thông báo
        </a>
    @endcan
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 w-100">
        <p class="text-muted mb-0">Các cập nhật mới từ hoạt động cửa hàng.</p>
        <div class="page-toolbar__actions">
            <form action="{{ route('notifications.mark-all-read') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm notification-mark-read-button" title="Đánh dấu tất cả thông báo của tài khoản hiện tại là đã đọc" style="width: 240px;">
                    <i class="bi bi-check2-all"></i> Đánh dấu tất cả đã đọc
                </button>
            </form>
        </div>
    </div>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <x-admin.search-field placeholder="Tìm thông báo..." label="Tìm kiếm thông báo" />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="read" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()" style="width: 240px;">
            <option value="">Tất cả trạng thái</option>
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
            <a href="{{ route('notifications.show', $notification->ThongBaoID) }}"
               id="notification-{{ $notification->ThongBaoID }}"
               class="list-group-item notification-row d-flex gap-3 {{ $notification->DaDoc ? 'is-read' : 'is-unread' }} notification-link">
                <i class="bi bi-bell-fill fs-4 {{ $notification->DaDoc ? 'notification-icon-read' : 'notification-icon-unread' }}"></i>
                <div class="flex-grow-1">
                    <div class="notification-title">{{ $notification->TieuDe }}</div>
                    <small class="notification-body d-block">{{ Str::limit($notification->NoiDung, 80) }}</small>
                </div>
                <div class="text-end">
                    <small class="text-muted">{{ $notification->ThoiGianGui?->format('d/m/Y H:i') }}</small>
                    @if(! $notification->DaDoc)
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning ms-2">Mới</span>
                    @endif
                </div>
            </a>
            @empty
            <div class="p-4 text-center text-muted">Chưa có thông báo nào.</div>
            @endforelse
        </div>
    </div>
</div>

<x-admin.pagination :paginator="$notifications" label="thông báo" />

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
