@extends('layouts.app')

@section('title', 'Đặt lịch - Sky Laundry')
@section('page-title', 'Đặt lịch')

@section('content')
<div class="mb-3 text-start">
    <h4 class="fw-bold mb-1">Quản Lý Đặt Lịch</h4>
    <p class="text-muted small mb-0">Xem và quản lý toàn bộ danh sách lịch hẹn đặt giặt ủi của khách hàng.</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm theo mã đặt lịch, tên khách hàng, SĐT, địa chỉ..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="pending" @selected(request('status') === 'pending')>Chờ xác nhận</option>
            <option value="confirmed" @selected(request('status') === 'confirmed')>Đã xác nhận</option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>Đã hủy</option>
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="delivery_method" class="form-select shadow-sm rounded-3 py-2" style="min-width: 200px;" onchange="this.form.submit()">
            <option value="">-- Tất cả hình thức --</option>
            <option value="pickup" @selected(request('delivery_method') === 'pickup')>Nhận tại tiệm</option>
            <option value="dropoff" @selected(request('delivery_method') === 'dropoff')>Giao tận nơi</option>
        </select>
    </div>
</form>

<!-- Bookings Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        @php
                            $currentSortBy = request('sort_by');
                            $currentSortOrder = request('sort_order', 'desc');
                            $nextOrderId = ($currentSortBy === 'id' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                        @endphp
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => $nextOrderId]) }}" class="text-dark text-decoration-none">
                                Mã đặt lịch
                                @if($currentSortBy === 'id') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Khách hàng</th>
                        <th>Dịch vụ</th>
                        <th>Loại đồ</th>
                        <th>Số lượng</th>
                        <th>Giao nhận</th>
                        <th>Ngày hẹn</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookings as $booking)
                    <tr>
                        <td><strong>BK{{ $booking->id }}</strong></td>
                        <td>
                <div class="d-flex align-items-center">
                                 @php
                                     $avatarId = $booking->customer?->id ?? $booking->id;
                                     $avatarUrl = 'assets/images/user_' . (($avatarId % 8) + 1) . '.jpg';
                                 @endphp
                                 <img src="{{ asset($avatarUrl) }}" alt="Ảnh khách hàng" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                 <div>
                                    <div class="fw-semibold">{{ $booking->customer?->name ?: '-' }}</div>
                                    <small class="text-muted">{{ $booking->customer?->phone ?: 'Chưa có SĐT' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $booking->service?->name ?: '-' }}</td>
                        <td>{{ $booking->garment_type ?: '-' }}</td>
                        <td>{{ $booking->quantity ?: 0 }}</td>
                        <td>{{ $booking->delivery_method === 'pickup' ? 'Nhận tại tiệm' : 'Giao tận nơi' }}</td>
                        <td>{{ $booking->pickup_date?->format('d/m/Y') ?: '-' }}</td>
                        <td>
                            @if($booking->status === 'confirmed')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đã xác nhận</span>
                            @elseif($booking->status === 'cancelled')
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-times-circle me-1"></i>Đã hủy</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-clock me-1"></i>Chờ xác nhận</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('bookings.show', $booking) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('bookings.destroy', $booking) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                     </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Chưa có dữ liệu nào</td>
                    </tr>
                    @endempty
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($bookings->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            Hiển thị {{ $bookings->firstItem() }} - {{ $bookings->lastItem() }} của {{ $bookings->total() }} đặt lịch
        </div>
        <ul class="pagination mb-0">
            @if ($bookings->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $bookings->appends(request()->query())->url($bookings->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($bookings->getUrlRange(max(1, $bookings->currentPage() - 2), min($bookings->lastPage(), $bookings->currentPage() + 2)) as $page => $url)
                @if ($page == $bookings->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $bookings->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($bookings->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $bookings->appends(request()->query())->url($bookings->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection
