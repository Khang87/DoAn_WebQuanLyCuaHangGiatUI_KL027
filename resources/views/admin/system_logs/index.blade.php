@extends('layouts.app')

@section('title', 'Nhật ký hệ thống - Sky Laundry')
@section('page-title', 'Nhật ký hệ thống')

@push('styles')
    <style>
        .system-log-filter-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .system-log-filter-button {
            box-sizing: border-box;
            display: inline-flex;
            height: 2.5rem;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border: 1px solid transparent;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.25rem;
            text-decoration: none;
            white-space: nowrap;
            transition: color 150ms ease, background-color 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
        }

        .system-log-filter-button svg {
            width: 1rem;
            height: 1rem;
            margin-right: 0.375rem;
            flex: none;
        }

        .system-log-filter-button:focus-visible {
            outline: none;
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px currentColor;
        }

        .system-log-filter-button--primary {
            border-color: #2563eb;
            background-color: #2563eb;
            color: #fff;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
        }

        .system-log-filter-button--primary:hover,
        .system-log-filter-button--primary:active {
            border-color: #1d4ed8;
            background-color: #1d4ed8;
            color: #fff;
        }

        .system-log-filter-button--primary:focus-visible {
            color: #2563eb;
        }

        .system-log-filter-button--secondary {
            border-color: #d1d5db;
            background-color: #fff;
            color: #374151;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.08);
        }

        .system-log-filter-button--secondary svg {
            color: #9ca3af;
        }

        .system-log-filter-button--secondary:hover,
        .system-log-filter-button--secondary:active {
            border-color: #d1d5db;
            background-color: #f9fafb;
            color: #111827;
        }

        .system-log-filter-button--secondary:focus-visible {
            color: #9ca3af;
        }
    </style>
@endpush

@section('content')
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-funnel me-2"></i>Bộ lọc nhật ký</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.system-logs.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="table" class="form-label">Bảng dữ liệu</label>
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
                <label for="TaiKhoanID" class="form-label">Tài khoản</label>
                <select id="TaiKhoanID" name="TaiKhoanID" class="form-select">
                    <option value="">Tất cả tài khoản</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->TaiKhoanID }}" @selected((string) ($filters['TaiKhoanID'] ?? '') === (string) $account->TaiKhoanID)>
                            {{ $account->TenDangNhap }} (#{{ $account->TaiKhoanID }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="from" class="form-label">Từ ngày</label>
                <input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label for="to" class="form-label">Đến ngày</label>
                <input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-12 system-log-filter-actions">
                <button type="submit" class="system-log-filter-button system-log-filter-button--primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-6-6m2-5a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/>
                    </svg>
                    Lọc
                </button>
                <a href="{{ route('admin.system-logs.index') }}" class="system-log-filter-button system-log-filter-button--secondary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 0 4.582 9M4.582 9H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2m15.357 2H15"/>
                    </svg>
                    Xóa lọc
                </a>
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
                        <th>Bảng dữ liệu</th>
                        <th>Bản ghi</th>
                        <th>Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="text-nowrap px-3 py-2">
                                @if($log->ThoiGian)
                                    <div class="fw-normal font-monospace">{{ $log->ThoiGian->format('d/m/Y') }}</div>
                                    <div class="small fw-normal font-monospace text-muted mt-1">{{ $log->ThoiGian->format('H:i:s') }}</div>
                                @else
                                    <span class="fw-normal text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($log->TaiKhoanID === null)
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Hệ thống</span>
                                @else
                                    {{ $log->taiKhoan?->TenDangNhap ?: 'Tài khoản #'.$log->TaiKhoanID }}
                                @endif
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
