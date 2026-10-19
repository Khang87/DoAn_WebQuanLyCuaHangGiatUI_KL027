@extends('layouts.app')

@section('title', 'Quản lý khách hàng - Sky Laundry')
@section('page-title', 'Quản lý khách hàng')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    @can('customers.create')
        <a href="{{ route('customers.create') }}" class="btn btn-create">
            <i class="bi bi-plus-lg"></i>Thêm khách hàng
        </a>
    @endcan
    <p class="text-muted page-toolbar__desc">Danh sách khách hàng đã gửi đồ giặt, kèm thông tin liên hệ và lịch sử sử dụng.</p>
</div>
<form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden filter-search">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm kiếm khách hàng..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()" style="height: 40px;">
            <option value="latest" @selected(request('sort', 'latest') === 'latest')>Mới nhất</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>Cũ nhất</option>
            <option value="name_asc" @selected(request('sort') === 'name_asc')>Tên A → Z</option>
            <option value="name_desc" @selected(request('sort') === 'name_desc')>Tên Z → A</option>
            <option value="points_desc" @selected(request('sort') === 'points_desc')>Điểm tích lũy cao → thấp</option>
            <option value="points_asc" @selected(request('sort') === 'points_asc')>Điểm tích lũy thấp → cao</option>
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
                        <th class="fw-bold text-dark">Khách hàng</th>
                        <th class="fw-bold text-dark">Email</th>
                        <th class="fw-bold text-dark">Số điện thoại</th>
                        <th class="fw-bold text-dark">Địa chỉ</th>
                        <th class="fw-bold text-dark">Điểm tích lũy</th>
                        <th class="fw-bold text-dark">Ngày đăng ký</th>
                        @if(auth()->user()?->canAny(['customers.view', 'customers.edit', 'customers.delete']))
                        <th class="fw-bold text-dark">Thao tác</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr>
                        <td class="text-dark text-center fw-semibold">{{ $customers->firstItem() + $loop->index }}</td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $customer->HoTen }}</div>
                            <small class="text-muted">{{ $customer->SoDienThoai ?: 'Chưa có số điện thoại' }}</small>
                        </td>
                        <td class="text-dark">{{ $customer->Email ?: '-' }}</td>
                        <td class="text-dark">{{ $customer->SoDienThoai ?: '-' }}</td>
                        <td class="text-dark">{{ $customer->DiaChi ?: '-' }}</td>
                        <td class="text-dark">{{ number_format((int) ($customer->diemTichLuy?->DiemHienTai ?? 0)) }} điểm</td>
                        <td class="text-dark">{{ $customer->NgayTao?->format('d/m/Y') ?: '-' }}</td>
                        @if(auth()->user()?->canAny(['customers.view', 'customers.edit', 'customers.delete']))
                        <td>
                            <div class="d-flex gap-2">
                                @can('customers.view')
                                <a href="{{ route('customers.show', $customer->KhachHangID) }}" class="btn btn-outline-secondary btn-sm" title="Xem"><i class="bi bi-eye"></i></a>
                                @endcan
                                @can('customers.edit')
                                <a href="{{ route('customers.edit', $customer->KhachHangID) }}" class="btn btn-outline-secondary btn-sm" title="Sửa"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('customers.delete')
                                <form action="{{ route('customers.destroy', $customer->KhachHangID) }}" method="POST" class="d-inline" id="deleteCustomerForm_{{ $customer->KhachHangID }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                                @endcan
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ auth()->user()?->canAny(['customers.view', 'customers.edit', 'customers.delete']) ? 8 : 7 }}" class="text-center text-muted py-4">Chưa có khách hàng nào</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-admin.pagination :paginator="$customers" label="khách hàng" />
@endsection

@push('scripts')
@if(session()->has('customer_blocking_orders'))
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const orders = @json(session('customer_blocking_orders'));
        const accounts = @json(session('customer_linked_accounts', []));
        const content = document.createElement('div');
        const summary = document.createElement('p');
        const orderList = document.createElement('ul');
        const accountList = document.createElement('ul');

        summary.className = 'text-start';
        summary.textContent = @json(session('error'));
        orderList.className = 'text-start mb-2';
        accountList.className = 'text-start mb-0';
        orderList.style.maxHeight = '10rem';
        accountList.style.maxHeight = '10rem';
        orderList.style.overflowY = 'auto';
        accountList.style.overflowY = 'auto';

        orders.forEach(function(order) {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = order.url;
            link.textContent = `${order.code} (${order.status})`;
            item.append(link, document.createTextNode(` — ${order.payment_status}`));
            orderList.append(item);
        });

        accounts.forEach(function(account) {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = account.url;
            link.textContent = account.email || account.username || `Tài khoản #${account.id}`;
            item.append(link, document.createTextNode(` — ${account.provider}`));
            accountList.append(item);
        });

        content.append(summary);
        if (orders.length > 0) {
            const orderHeading = document.createElement('p');
            orderHeading.className = 'text-start fw-semibold mb-1';
            orderHeading.textContent = 'Đơn chưa thanh toán hoặc chưa hoàn tất:';
            content.append(orderHeading, orderList);
        }
        if (accounts.length > 0) {
            const accountHeading = document.createElement('p');
            accountHeading.className = 'text-start fw-semibold mb-1';
            accountHeading.textContent = 'Tài khoản đang liên kết:';
            content.append(accountHeading, accountList);
        }

        Swal.fire({
            icon: 'error',
            title: 'Không thể xóa khách hàng',
            html: content,
            confirmButtonText: 'Đóng'
        });
    });
</script>
@endif
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
