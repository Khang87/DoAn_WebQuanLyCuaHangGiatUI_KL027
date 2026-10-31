@extends('layouts.app')
@section('title', 'Quản lý giao nhận - Sky Laundry')
@section('page-title', 'Quản lý giao nhận')
@section('content')
<div class="page-toolbar">
    @can('deliveries.create')
        <a href="{{ route('deliveries.create') }}" class="btn btn-create">
            <i class="bi bi-plus-lg"></i>Thêm lịch giao nhận
        </a>
    @endcan
    <p class="text-muted page-toolbar__desc">Theo dõi lịch nhận và giao đồ cho khách hàng.</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden filter-search">
            <span class="input-group-text bg-white border-end-0 ps-3"><i class="fas fa-search text-muted"></i></span>
            <input type="search" name="search" class="form-control form-control-sm border-start-0 ps-2"
                   placeholder="Tìm mã phiếu, đơn hàng, đặt lịch, khách hàng, nhân viên hoặc địa chỉ..."
                   value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="method" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">Tất cả công việc</option>
            <option value="nhan_do" @selected(request('method') === 'nhan_do')>Nhận đồ</option>
            <option value="giao_do" @selected(request('method') === 'giao_do')>Giao đồ</option>
        </select>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="array_merge(\App\Enums\DeliveryStatus::options(), ['received' => 'Đã nhận đồ'])"
            placeholder="Tất cả trạng thái"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort_order" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="desc" @selected(request('sort_order', 'desc') !== 'asc')>Mới nhất trước</option>
            <option value="asc" @selected(request('sort_order') === 'asc')>Cũ nhất trước</option>
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-custom mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Mã phiếu</th>
                        <th scope="col">Công việc</th>
                        <th scope="col">Khách hàng</th>
                        <th scope="col">Đặt lịch / Đơn hàng</th>
                        <th scope="col">Nhân viên phụ trách</th>
                        <th scope="col">Thời gian dự kiến</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col" class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $delivery)
                        @php
                            $isBookingDocument = $delivery->source_type === 'booking';
                            $isIntake = $delivery->task_type === 'NHAN_DO';
                            $isMutableBookingIntake = $isBookingDocument && (int) $delivery->intake_mutable === 1;
                            $isReturnIntake = $isBookingDocument && $delivery->task_type === 'GIAO_DO';
                            $deliveryStatus = \App\Enums\DeliveryStatus::parseForLeg($delivery->raw_status, $delivery->task_type);
                            $canManageOperationalReturn = ! $isBookingDocument
                                && $delivery->task_type === 'GIAO_DO'
                                && ! in_array($deliveryStatus, [\App\Enums\DeliveryStatus::Completed, \App\Enums\DeliveryStatus::Cancelled], true)
                                && in_array($delivery->order_status, [\App\Enums\OrderStatus::Washed->value, \App\Enums\OrderStatus::Delivering->value], true)
                                && $delivery->booking_status !== \App\Enums\BookingStatus::Cancelled->value;
                            $canEditOperationalDelivery = ! $isBookingDocument
                                && ($delivery->task_type !== 'GIAO_DO'
                                    || (
                                        ! in_array($deliveryStatus, [\App\Enums\DeliveryStatus::Completed, \App\Enums\DeliveryStatus::Cancelled], true)
                                        && $delivery->order_status !== \App\Enums\OrderStatus::Cancelled->value
                                        && $delivery->booking_status !== \App\Enums\BookingStatus::Cancelled->value
                                    ));
                            $expectedAt = $delivery->expected_at
                                ? \Illuminate\Support\Carbon::parse($delivery->expected_at)
                                : null;
                            $detailUrl = $isBookingDocument
                                ? route('deliveries.booking-documents.show', $delivery->source_id)
                                : route('deliveries.show', $delivery->source_id);
                        @endphp
                        <tr data-delivery-key="{{ $delivery->source_type }}:{{ $delivery->source_id }}"
                            data-detail-url="{{ $detailUrl }}">
                            <td>
                                <span class="text-dark fw-semibold">{{ $delivery->source_code }}</span>
                                <span class="d-block small text-muted">{{ $isBookingDocument ? 'Phiếu giao nhận từ đặt lịch' : 'Phiếu giao nhận từ đơn hàng' }}</span>
                            </td>
                            <td>
                                @if($isIntake)
                                    <i class="bi bi-box-arrow-in-down me-1" aria-hidden="true"></i>Nhận đồ
                                @else
                                    <i class="bi bi-truck me-1" aria-hidden="true"></i>Giao đồ
                                @endif
                            </td>
                            <td>{{ $delivery->customer_name ?: '—' }}</td>
                            <td>
                                @if($delivery->booking_code)
                                    @can('bookings.view')
                                        <a href="{{ route('bookings.show', $delivery->booking_id) }}">{{ $delivery->booking_code }}</a>
                                    @else
                                        {{ $delivery->booking_code }}
                                    @endcan
                                @endif
                                @if($delivery->order_code)
                                    @if($delivery->booking_code)<br>@endif
                                    @can('orders.view')
                                        <a href="{{ route('orders.show', $delivery->order_id) }}">{{ $delivery->order_code }}</a>
                                    @else
                                        {{ $delivery->order_code }}
                                    @endcan
                                @endif
                                @if(! $delivery->booking_code && ! $delivery->order_code)
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $delivery->employee_name ?: '—' }}</td>
                            <td>
                                @if($expectedAt)
                                    {{ $expectedAt->format('d/m/Y') }}
                                    <br><small class="text-muted">{{ $expectedAt->format('H:i') }}</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if($delivery->status_key === 'received')
                                    <x-admin.status-badge
                                        :status="\App\Enums\DeliveryStatus::Completed"
                                        :enum="\App\Enums\DeliveryStatus::class"
                                        label="Đã nhận đồ"
                                    />
                                @else
                                    <x-admin.status-badge
                                        :status="$deliveryStatus"
                                        :enum="\App\Enums\DeliveryStatus::class"
                                    />
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-2 justify-content-center">
                                    @can('deliveries.view')
                                        @if($isBookingDocument)
                                            <a href="{{ route('deliveries.booking-documents.show', $delivery->source_id) }}"
                                               class="btn btn-order-action view" title="Xem chi tiết"
                                               aria-label="Xem chi tiết {{ $delivery->source_code }}">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        @else
                                            <a href="{{ route('deliveries.show', $delivery->source_id) }}"
                                               class="btn btn-order-action view" title="Xem chi tiết"
                                               aria-label="Xem chi tiết {{ $delivery->source_code }}">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        @endif
                                    @endcan

                                    @if($isMutableBookingIntake)
                                        @can('deliveries.edit')
                                            <a href="{{ route('deliveries.booking-documents.edit', $delivery->source_id) }}"
                                               class="btn btn-order-action edit" title="Sửa phiếu nhận đồ">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                    @endif
                                    @if($isBookingDocument && $isIntake && in_array($delivery->booking_id, $inspectableBookingIds))
                                        @can('bookings.view')
                                            <a href="{{ route('bookings.inspection', $delivery->booking_id) }}"
                                               class="btn btn-order-action"
                                               title="Kiểm tra và tạo đơn"
                                               aria-label="Kiểm tra và tạo đơn cho {{ $delivery->booking_code }}">
                                                <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                                            </a>
                                        @endcan
                                    @endif
                                    @if($isReturnIntake && $delivery->employee_name !== null && $delivery->order_id !== null && in_array($deliveryStatus, [\App\Enums\DeliveryStatus::Pending, \App\Enums\DeliveryStatus::Picking, \App\Enums\DeliveryStatus::Delivering], true))
                                        @php
                                            $progressAction = \App\Support\DeliveryWorkflow::progressAction($delivery->task_type, $delivery->fulfillment, $deliveryStatus);
                                            $order = $delivery->order_id ? \App\Models\DonHang::find($delivery->order_id) : null;
                                            $paymentNotDone = $progressAction && $deliveryStatus === \App\Enums\DeliveryStatus::Delivering && ! $order?->isLocked();
                                        @endphp
                                        @if($progressAction)
                                            @can('deliveries.edit')
                                                @if($paymentNotDone)
                                                    @can('payments.create')
                                                        <a class="btn btn-order-action payment" href="{{ route('payments.create', ['order_id' => $delivery->order_id]) }}" title="Thanh toán" aria-label="Thanh toán đơn {{ $delivery->order_code }}">
                                                            <i class="bi bi-credit-card" aria-hidden="true"></i>
                                                        </a>
                                                    @endcan
                                                @else
                                                    <x-admin.detail.confirm-form
                                                        :action="route('deliveries.booking-documents.advance-progress', $delivery->source_id)"
                                                        method="POST"
                                                        :title="$progressAction['label'].'?'"
                                                        text="Trạng thái phiếu được cập nhật và ghi vào lịch sử."
                                                        :label="$progressAction['label']"
                                                        :icon="$progressAction['icon']"
                                                        variant="btn-order-action"
                                                        size=""
                                                        iconOnly
                                                        :color="$progressAction['color']"
                                                        iconName="question"
                                                    />
                                                @endif
                                            @endcan
                                        @endif
                                    @endif
                                    @if(! $isBookingDocument)
                                        @php
                                            $progressAction = \App\Support\DeliveryWorkflow::progressAction($delivery->task_type, $delivery->fulfillment, $deliveryStatus);
                                            
                                            $order = $delivery->order_id ? \App\Models\DonHang::find($delivery->order_id) : null;
                                            $paymentNotDone = $progressAction && $delivery->task_type === 'GIAO_DO'
                                                && in_array($deliveryStatus, [\App\Enums\DeliveryStatus::Pending, \App\Enums\DeliveryStatus::Delivering], true)
                                                && ($deliveryStatus === \App\Enums\DeliveryStatus::Delivering || $delivery->fulfillment === 'Tại cửa hàng')
                                                && ! $order?->isLocked();
                                        @endphp
                                        @if($progressAction)
                                            @can('deliveries.edit')
                                                @if($paymentNotDone)
                                                    @can('payments.create')
                                                        <a class="btn btn-order-action payment" href="{{ route('payments.create', ['order_id' => $delivery->order_id]) }}" title="Thanh toán" aria-label="Thanh toán đơn {{ $delivery->order_code }}">
                                                            <i class="bi bi-credit-card" aria-hidden="true"></i>
                                                        </a>
                                                    @endcan
                                                @else
                                                    <x-admin.detail.confirm-form
                                                        :action="route('deliveries.advance-progress', $delivery->source_id)"
                                                        method="POST"
                                                        :title="$progressAction['label'].'?'"
                                                        text="Trạng thái phiếu được cập nhật và ghi vào lịch sử."
                                                        :label="$progressAction['label']"
                                                        :icon="$progressAction['icon']"
                                                        variant="btn-order-action"
                                                        size=""
                                                        iconOnly
                                                        :color="$progressAction['color']"
                                                        iconName="question"
                                                    />
                                                @endif
                                            @endcan
                                        @endif
                                        @if($canEditOperationalDelivery)
                                            @can('deliveries.edit')
                                                <a href="{{ route('deliveries.edit', $delivery->source_id) }}"
                                                   class="btn btn-order-action edit" title="Sửa">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endcan
                                        @endif
                                        @if($canManageOperationalReturn)
                                            @can('deliveries.delete')
                                                <x-admin.detail.confirm-form
                                                    :action="route('deliveries.cancel-return', $delivery->source_id)"
                                                    method="POST"
                                                    title="Hủy lịch giao?"
                                                    text="Phiếu được giữ lại làm lịch sử; đơn hàng và trạng thái thanh toán không thay đổi."
                                                    label="Hủy lịch giao"
                                                    icon="bi bi-x-circle"
                                                    variant="btn-order-action delete"
                                                    size=""
                                                    iconOnly
                                                />
                                            @endcan
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Chưa có dữ liệu giao nhận</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-admin.pagination :paginator="$deliveries" label="phiếu giao nhận" />
@endsection
