@extends('layouts.app')

@section('title', 'Nhật ký hệ thống - Sky Laundry')
@section('page-title', 'Nhật ký hệ thống')

@push('styles')
    <style>
        summary.system-log-detail-toggle {
            display: inline-flex;
            list-style: none;
            cursor: pointer;
        }

        summary.system-log-detail-toggle::-webkit-details-marker {
            display: none;
        }

        .system-log-account {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .system-log-filter-reset {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: 0.75rem;
            padding: 0;
            border: 0;
            background: none;
            color: #6c757d;
            font-size: 0.875rem;
            text-decoration: none;
        }

        .system-log-filter-reset:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
    </style>
@endpush

@section('content')
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-funnel me-2"></i>Bộ lọc nhật ký</h5>
    </div>
    <div class="card-body">
        <form id="system-log-filter-form" method="GET" action="{{ route('admin.system-logs.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="table" class="form-label">Bảng dữ liệu</label>
                <select id="table" name="table" class="form-select">
                    <option value="">Tất cả bảng</option>
                    @foreach($tables as $table)
                        <option value="{{ $table }}" @selected(($filters['table'] ?? '') === $table)>{{ \App\Models\NhatKyHeThong::labelForBang($table) }}</option>
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
                            {{ $account->dropdownLabel() }}
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
            @if(collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty())
                <div class="col-12">
                    <a href="{{ route('admin.system-logs.index') }}" class="system-log-filter-reset">
                        <i class="bi bi-x-circle" aria-hidden="true"></i>Xóa lọc
                    </a>
                </div>
            @endif
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
                        <th scope="col">STT</th>
                        <th style="width: 170px;">Thời gian</th>
                        <th style="width: 170px;">Tài khoản</th>
                        <th style="width: 210px;">Bảng dữ liệu</th>
                        <th>Hành động</th>
                        <th style="width: 110px;">Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $logs->firstItem() + $loop->index }}</td>
                            <td class="text-nowrap">{{ $log->ThoiGian?->format('d/m/Y H:i:s') ?: '—' }}</td>
                            <td>
                                @if($log->TaiKhoanID === null)
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Hệ thống</span>
                                @else
                                    <div class="system-log-account">
                                        <x-admin.customer-avatar :size="28"
                                            :url="$log->taiKhoan?->getAttributes()['AvatarURL'] ?? $log->taiKhoan?->khachHang?->getAttributes()['AvatarUrl'] ?? null"
                                            :name="$log->taiKhoan?->tenHienThi" />
                                        <span class="text-truncate">{{ $log->taiKhoan?->tenHienThi ?: 'Tài khoản #'.$log->TaiKhoanID }}</span>
                                    </div>
                                @endif
                            </td>
                            <td><span class="badge {{ \App\Models\NhatKyHeThong::toneForBang((string) $log->BangDuLieu) }}">{{ $log->BangDuLieuLabel }}</span></td>
                            <td>
                                {{ $log->HanhDong }}
                                @if($log->BanGhiID)
                                    <span class="text-muted ms-1">· {{ $targetCodes[$log->NhatKyID] ?? '#'.$log->BanGhiID }}</span>
                                @endif
                            </td>
                            <td>
                                @if($log->DuLieuCu || $log->DuLieuMoi || $log->LyDo)
                                    <details>
                                        <summary class="system-log-detail-toggle btn-order-action view" title="Xem" aria-label="Xem chi tiết">
                                            <i class="bi bi-eye"></i>
                                        </summary>
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
</div>

<x-admin.pagination :paginator="$logs" label="nhật ký" />

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('system-log-filter-form');
            if (!form) return;
            form.querySelectorAll('select, input[type="date"]').forEach(control => {
                control.addEventListener('change', () => form.submit());
            });
        })();
    </script>
@endpush
@endsection
