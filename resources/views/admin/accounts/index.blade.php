@extends('layouts.app')

@section('title', 'Quản lý tài khoản - Sky Laundry')
@section('page-title', 'Quản lý tài khoản & Phân quyền')

@section('content')
<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('accounts.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>Thêm tài khoản mới
        </a>
    </div>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm tên, email, SĐT..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="role" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả vai trò --</option>
            <option value="manager" @selected(request('role') === 'manager' || request('role') === 'admin')>Quản lý</option>
            <option value="staff" @selected(request('role') === 'staff')>Nhân viên</option>
            <option value="customer" @selected(request('role') === 'customer')>Khách hàng</option>
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 200px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            @foreach($statuses ?? \App\Enums\RecordStatus::options() as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>

<!-- Accounts Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã</th>
                        @php
                            $currentSortBy = request('sort_by');
                            $currentSortOrder = request('sort_order', 'desc');
                            $nextOrderName = ($currentSortBy === 'name' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderEmail = ($currentSortBy === 'email' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderCreated = ($currentSortBy === 'created_at' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                        @endphp
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => $nextOrderName]) }}" class="text-dark text-decoration-none">
                                Người dùng
                                @if($currentSortBy === 'name') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'email', 'sort_order' => $nextOrderEmail]) }}" class="text-dark text-decoration-none">
                                Email / SĐT
                                @if($currentSortBy === 'email') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Vai trò phân quyền</th>
                        <th>Trạng thái</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => $nextOrderCreated]) }}" class="text-dark text-decoration-none">
                                Ngày tạo
                                @if($currentSortBy === 'created_at') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $account->code ?? 'TK' . str_pad($account->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                @php
                                $randomImage = 'assets/images/user_' . (($account->id % 8) + 1) . '.jpg';
                                $avatarUrl = (!empty($account->avatar) && file_exists(public_path($account->avatar))) 
                                           ? asset($account->avatar) 
                                           : asset($randomImage);
                                @endphp
                                <img src="{{ $avatarUrl }}" alt="Avatar" class="rounded-circle me-2 avatar-cover" style="width: 36px; height: 36px;">
                                <strong class="fw-semibold">{{ $account->name }}</strong>
                            </div>
                        </td>
                        <td>
                            {{ $account->email }}
                            @if($account->phone)
                                <br><small class="text-muted">{{ $account->phone }}</small>
                            @endif
                        </td>
                        <td>
                            @if($account->isManager())
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-shield-alt me-1"></i>Quản lý</span>
                            @elseif($account->role === 'staff')
                                <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-user-tie me-1"></i>Nhân viên</span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-user me-1"></i>Khách hàng</span>
                            @endif
                        </td>
                        <td>
                            @if($account->deleted_at)
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-2 rounded-pill"><i class="fas fa-pause-circle me-1"></i>{{ $account->status_label ?? 'Tạm ngưng' }}</span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>{{ $account->status_label ?? 'Đang hoạt động' }}</span>
                            @endif
                        </td>
                        <td>{{ $account->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('accounts.show', $account->id) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @can('update', $account)
                                <a href="{{ route('accounts.edit', $account->id) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Chưa có tài khoản nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($accounts->hasPages())
<div class="mt-3">
    {{ $accounts->appends(request()->query())->links('pagination::bootstrap-5') }}
</div>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
    });
</script>
@endpush