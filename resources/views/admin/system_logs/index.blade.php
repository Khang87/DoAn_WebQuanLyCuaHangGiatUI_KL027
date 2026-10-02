@extends('layouts.app')

@section('title', 'Nhật ký hệ thống - Sky Laundry')
@section('page-title', 'Nhật ký hệ thống')

@section('content')
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-funnel me-2"></i>Bộ lọc nhật ký</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.system-logs.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="table" class="form-label">Bảng tác động</label>
                <select id="table" name="table" class="form-select">
                    <option value="">Tất cả bảng</option>
                    @foreach($tables as $table)
                        <option value="{{ $table }}" @selected(($filters['table'] ?? '') === $table)>{{ $table }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="action" class="form-label">Hành động</label>
                <input id="action" name="action" value="{{ $filters['action'] ?? '' }}" class="form-control" maxlength="255" placeholder="Tìm hành động">
            </div>
            <div class="col-md-2">
                <label for="account_id" class="form-label">Mã tài khoản</label>
                <input id="account_id" name="account_id" type="number" min="1" value="{{ $filters['account_id'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label for="from" class="form-label">Từ ngày</label>
                <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label for="to" class="form-label">Đến ngày</label>
                <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Lọc</button>
                <a href="{{ route('admin.system-logs.index') }}" class="btn btn-outline-secondary">Xóa lọc</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold">Nhật ký hoạt động</h5>
        <span class="text-muted small">{{ $logs->total() }} bản ghi</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Thời gian</th>
                        <th>Tài khoản</th>
                        <th>Hành động</th>
                        <th>Bảng</th>
                        <th>Bản ghi</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->ThoiGian?->format('d-m-Y H:i:s') ?: '—' }}</td>
                            <td>
                                {{ $log->taiKhoan?->TenDangNhap ?: '—' }}
                                @if($log->TaiKhoanID)
                                    <span class="text-muted small d-block">#{{ $log->TaiKhoanID }}</span>
                                @endif
                            </td>
                            <td>{{ $log->HanhDong }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $log->BangDuLieu }}</span></td>
                            <td>
                                @if($log->BangDuLieu === 'DonHang' && $log->BanGhiID)
                                    <a href="{{ route('orders.show', $log->BanGhiID) }}">#{{ $log->BanGhiID }}</a>
                                @elseif($log->BangDuLieu === 'Booking' && $log->BanGhiID)
                                    <a href="{{ route('bookings.show', $log->BanGhiID) }}">#{{ $log->BanGhiID }}</a>
                                @else
                                    {{ $log->BanGhiID ? '#'.$log->BanGhiID : '—' }}
                                @endif
                            </td>
                            <td>
                                @if($log->DuLieuCu || $log->DuLieuMoi || $log->LyDo)
                                    <details>
                                        <summary class="text-primary">Xem</summary>
                                        @if($log->LyDo)
                                            <p class="small mt-2 mb-1">{{ $log->LyDo }}</p>
                                        @endif
                                        @if($log->DuLieuCu)
                                            <div class="small text-muted mt-2">Trước</div>
                                            <pre class="small text-wrap mb-1">{{ json_encode($log->DuLieuCu, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                        @endif
                                        @if($log->DuLieuMoi)
                                            <div class="small text-muted mt-2">Sau</div>
                                            <pre class="small text-wrap mb-1">{{ json_encode($log->DuLieuMoi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                        @endif
                                    </details>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">Chưa có nhật ký phù hợp với bộ lọc.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
        <div class="card-footer bg-white">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
