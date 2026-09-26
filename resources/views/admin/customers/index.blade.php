@extends('layouts.app')

@section('title', 'Quản lý khách hàng - Sky Laundry')
@section('page-title', 'Quản lý khách hàng')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('customers.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm khách hàng
    </a>
    <p class="text-muted page-toolbar__desc">Danh sách khách hàng đã gửi đồ giặt, kèm thông tin liên hệ và lịch sử sử dụng.</p>
</div>
<form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm kiếm khách hàng..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="latest" @selected(request('sort', 'latest') === 'latest')>Mới nhất</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>Cũ nhất</option>
            <option value="name_asc" @selected(request('sort') === 'name_asc')>Tên A → Z</option>
            <option value="name_desc" @selected(request('sort') === 'name_desc')>Tên Z → A</option>
            <option value="points_desc" @selected(request('sort') === 'points_desc')>Điểm tích lũy cao → thấp</option>
        </select>
    </div>
</form>

<!-- Customers Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th class="fw-bold text-dark">STT</th>
                        <th class="fw-bold text-dark">Mã khách hàng</th>
                        <th class="fw-bold text-dark">Khách hàng</th>
                        <th class="fw-bold text-dark">Email</th>
                        <th class="fw-bold text-dark">Số điện thoại</th>
                        <th class="fw-bold text-dark">Địa chỉ</th>
                        <th class="fw-bold text-dark">Điểm tích lũy</th>
                        <th class="fw-bold text-dark">Ngày đăng ký</th>
                        <th class="fw-bold text-dark">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr>
                        <td class="text-dark">{{ $loop->iteration }}</td>
                        <td class="text-dark">{{ $customer->code }}</td>
                            <td>
                            <div class="d-flex align-items-center">
                                <img src="{{ $customer->avatar_url }}" alt="Avatar" class="rounded-circle me-2 avatar-cover" style="width: 40px; height: 40px;">
                                <div>
                                    <div class="fw-semibold text-dark">{{ $customer->name }}</div>
                                    <small class="text-muted">{{ $customer->phone ?: 'Chưa có số điện thoại' }}</small>
                                </div>
                            </div>
                        </td>
                        <td class="text-dark">{{ $customer->email ?: '-' }}</td>
                        <td class="text-dark">{{ $customer->phone ?: '-' }}</td>
                        <td class="text-dark">{{ $customer->address ?: '-' }}</td>
                        <td class="text-dark">{{ number_format($customer->points) }} điểm</td>
                        <td class="text-dark">{{ $customer->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary btn-sm" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-secondary btn-sm" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline" id="deleteCustomerForm_{{ $customer->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Chưa có khách hàng nào</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@if($customers->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $customers->firstItem() }} - {{ $customers->lastItem() }} của {{ $customers->total() }} khách hàng</div>
        <ul class="pagination mb-0">
            @if ($customers->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $customers->appends(request()->query())->url($customers->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($customers->getUrlRange(max(1, $customers->currentPage() - 2), min($customers->lastPage(), $customers->currentPage() + 2)) as $page => $url)
                @if ($page == $customers->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $customers->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($customers->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $customers->appends(request()->query())->url($customers->currentPage() + 1) }}">{{ $page }}</a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deleteCustomerForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa khách hàng này?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa khách hàng?',
                    text: 'Hành động này không thể hoàn tác.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Xóa',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush