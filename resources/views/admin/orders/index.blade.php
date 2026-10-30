@extends('layouts.app')

@section('title', 'Quản lý Đơn hàng - Sky Laundry')
@section('page-title', 'Quản lý Đơn hàng')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    @can('orders.create')
        <a href="{{ route('orders.create') }}" class="btn btn-create">
            <i class="bi bi-plus-lg"></i>Thêm đơn hàng
        </a>
    @endcan
    <p class="text-muted page-toolbar__desc">Theo dõi toàn bộ đơn giặt của khách hàng, từ lúc tiếp nhận đến khi hoàn tất giao trả.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden filter-search">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm kiếm đơn hàng..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="$statusFlow ?? \App\Enums\OrderStatus::options()"
            placeholder="Tất cả trạng thái"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="latest" @selected(request('sort', 'latest') === 'latest')>Mới nhất</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>Cũ nhất</option>
            <option value="total_desc" @selected(request('sort') === 'total_desc')>Tổng tiền cao → thấp</option>
            <option value="total_asc" @selected(request('sort') === 'total_asc')>Tổng tiền thấp → cao</option>
            <option value="code_asc" @selected(request('sort') === 'code_asc')>Mã đơn A → Z</option>
            <option value="code_desc" @selected(request('sort') === 'code_desc')>Mã đơn Z → A</option>
        </select>
    </div>
</form>

<!-- Orders Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th scope="col">STT</th>
                        <th class="fw-bold text-dark">Mã đơn hàng</th>
                        <th class="fw-bold text-dark">Khách hàng</th>
                        <th class="fw-bold text-dark">Thành tiền</th>
                        <th class="fw-bold text-dark text-center">Trạng thái</th>
                        <th class="fw-bold text-dark">Ngày tạo</th>
                        <th class="fw-bold text-dark">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr>
                        <td>{{ $orders->firstItem() + $loop->index }}</td>
                        <td class="text-dark">{{ $order->MaDonHang }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <x-admin.customer-avatar :customer="$order->khachHang" />
                                <div>
                                    <div class="text-dark">{{ $order->khachHang?->HoTen ?: '-' }}</div>
                                    <small class="text-muted">{{ $order->khachHang?->SoDienThoai ?: 'Chưa có số điện thoại' }}</small>
                                </div>
                            </div>
                        </td>
                        <td class="text-dark">{{ number_format((float) $order->ThanhTien) }} đ</td>
                        <td class="text-center">
                            <div class="order-status-badges d-inline-flex align-items-center flex-nowrap gap-1 text-nowrap">
                                <x-admin.order-status-badges :order="$order" :is-paid="$order->isPaid()" :is-receiving="false" compact />
                            </div>
                        </td>
                        <td class="text-dark">{{ $order->NgayTao?->format('d/m/Y H:i') ?: '-' }}</td>
                        <td class="text-nowrap">
                            @php
                                $canManageSettled = ! $order->isLocked() || auth()->user()?->isOwner();
                                $operationalStatus = $order->operationalStatusEnum();
                                $nextOrderAction = $canManageSettled
                                    ? \App\Support\OrderWorkflow::nextAction($order)
                                    : null;
                                $orderInvoice = $order->hoaDons->first();
                                $isBookingReturnFlow = $order->booking !== null
                                    && in_array($operationalStatus, [\App\Enums\OrderStatus::Washed, \App\Enums\OrderStatus::Delivering], true);
                                $returnDelivery = $isBookingReturnFlow
                                    ? \App\Support\OrderWorkflow::activeReturnDelivery($order)
                                    : null;
                            @endphp
                            <div class="d-flex align-items-center flex-nowrap gap-2">
                                @can('orders.view')
                                    <a href="{{ route('orders.show', $order) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @endcan
                                @if($canManageSettled && $operationalStatus === \App\Enums\OrderStatus::Pending)
                                    @can('orders.edit')
                                        <a href="{{ \App\Support\OrderWorkflow::receivingUrl($order) }}"
                                           class="btn btn-order-action"
                                           title="Kiểm tra và tiếp nhận"
                                           aria-label="Kiểm tra và tiếp nhận đơn {{ $order->MaDonHang }}">
                                            <i class="bi bi-box-arrow-in-down" aria-hidden="true"></i>
                                        </a>
                                    @endcan
                                @endif
                                @if($nextOrderAction && $nextOrderAction['kind'] === 'delivery')
                                    @if($order->hasActiveReturnDelivery()
                                        ? auth()->user()?->canPermission('deliveries.edit')
                                        : auth()->user()?->canPermission('deliveries.create'))
                                        <a href="{{ route('orders.delivery-assignment', [$order] + \App\Support\OrderWorkflow::listReturnParams()) }}"
                                           class="btn btn-order-action"
                                           title="{{ $nextOrderAction['label'] }}"
                                           aria-label="{{ $nextOrderAction['label'] }} đơn {{ $order->MaDonHang }}">
                                            <i class="bi {{ $nextOrderAction['icon'] }}" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                @elseif($nextOrderAction)
                                    @can('orders.update_status')
                                        <x-admin.detail.confirm-form
                                            :action="route('orders.update-status', $order)"
                                            method="PATCH"
                                            :title="$nextOrderAction['label'].'?'"
                                            :text="'Trạng thái đơn '. $order->MaDonHang .' sẽ chuyển sang '. $nextOrderAction['status']->label() .'.'"
                                            :label="$nextOrderAction['label']"
                                            :icon="$nextOrderAction['icon']"
                                            variant="btn-order-action"
                                            size=""
                                            iconOnly
                                            :hidden="['TrangThai' => $nextOrderAction['status']->value]"
                                            color="#2563eb"
                                            iconName="question"
                                        />
                                    @endcan
                                @endif
                                @if($isBookingReturnFlow)
                                    @if($returnDelivery)
                                        @can('deliveries.view')
                                            <a href="{{ route('deliveries.show', [$returnDelivery] + \App\Support\OrderWorkflow::listReturnParams()) }}"
                                               class="btn btn-order-action"
                                               title="Xem phiếu giao"
                                               aria-label="Xem phiếu giao của đơn {{ $order->MaDonHang }}">
                                                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                            </a>
                                        @endcan
                                    @elseif($operationalStatus === \App\Enums\OrderStatus::Washed)
                                        @can('deliveries.create')
                                            <a href="{{ route('orders.delivery-assignment', [$order] + \App\Support\OrderWorkflow::listReturnParams()) }}"
                                               class="btn btn-order-action"
                                               title="Tạo phiếu giao"
                                               aria-label="Tạo phiếu giao cho đơn {{ $order->MaDonHang }}">
                                                <i class="bi bi-truck" aria-hidden="true"></i>
                                            </a>
                                        @endcan
                                    @endif
                                @endif
                                @if($orderInvoice?->isPaid())
                                    @can('invoices.view')
                                        <a href="{{ route('invoices.show', [$orderInvoice->HoaDonID] + \App\Support\OrderWorkflow::listReturnParams()) }}"
                                           class="btn btn-order-action"
                                           title="Xem hóa đơn {{ $orderInvoice->MaHoaDon }}"
                                           aria-label="Xem hóa đơn {{ $orderInvoice->MaHoaDon }}">
                                            <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                                        </a>
                                    @endcan
                                @elseif(! $orderInvoice && in_array($order->TrangThai, ['Hoàn thành giặt', 'Đang giao', 'Đã giao'], true))
                                    @can('invoices.create')
                                        <a href="{{ route('invoices.create', ['order_id' => $order->getKey()]) }}"
                                           class="btn btn-order-action"
                                           title="Tạo hóa đơn"
                                           aria-label="Tạo hóa đơn cho đơn {{ $order->MaDonHang }}">
                                            <i class="bi bi-file-earmark-plus" aria-hidden="true"></i>
                                        </a>
                                    @endcan
                                @endif
                                @if(! $order->isLocked() && $order->TrangThai !== \App\Enums\OrderStatus::Cancelled->value)
                                    @can('payments.create')
                                        <a href="{{ route('payments.create', ['order_id' => $order->getKey()]) }}"
                                           class="btn btn-order-action payment"
                                           title="Thanh toán"
                                           aria-label="Thanh toán đơn {{ $order->MaDonHang }}"
                                           data-payment-link>
                                            <i class="bi bi-credit-card" aria-hidden="true"></i>
                                        </a>
                                    @endcan
                                @endif
                                @if($canManageSettled)
                                    @can('orders.edit')
                                        <a href="{{ route('orders.edit', $order) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                    @endcan
                                    @can('orders.delete')
                                        <x-admin.detail.confirm-form
                                            :action="route('orders.destroy', $order)"
                                            title="Xóa đơn hàng?"
                                            text="Đơn hàng đã quyết toán chỉ có thể xóa theo quyền chủ cửa hàng."
                                            label="Xóa đơn hàng"
                                            icon="bi-trash"
                                            variant="btn-order-action delete"
                                            size=""
                                            iconOnly
                                        />
                                    @endcan
                                @endif
                                @if($order->isLocked() && ! auth()->user()?->isOwner())
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary px-3 py-2 rounded-pill d-flex align-items-center" title="Đã quyết toán">
                                        <i class="bi bi-lock me-1"></i>Đã quyết toán
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">Chưa có đơn hàng nào</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-admin.pagination :paginator="$orders" label="đơn hàng" />
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-payment-link]').forEach(function(link) {
            link.addEventListener('click', function() {
                if (link.dataset.loading === 'true') {
                    return;
                }

                link.dataset.loading = 'true';
                link.setAttribute('aria-disabled', 'true');
                link.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span class="visually-hidden">Đang chuyển đến thanh toán</span>';
            });
        });

        document.querySelectorAll('[id^="deleteOrderForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa đơn hàng?',
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
