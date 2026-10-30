@extends('layouts.app')

@section('title', 'Đặt lịch - Sky Laundry')
@section('page-title', 'Đặt lịch')

@section('content')
<div class="page-toolbar">
    <p class="text-muted page-toolbar__desc">Theo dõi Booking từ khi xác nhận, nhận đồ thực tế đến khi kiểm kê và tạo đơn.</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden filter-search">
            <span class="input-group-text bg-white border-end-0 ps-3"><i class="fas fa-search text-muted"></i></span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm theo mã lịch, tên khách hàng, SĐT, địa chỉ nhận/trả..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="\App\Enums\BookingStatus::options()"
            placeholder="Tất cả trạng thái"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="method" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">Tất cả hình thức nhận</option>
            @foreach(\App\Enums\ReceiveMethod::options() as $value => $label)
                <option value="{{ $value }}" @selected(request('method') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="return_method" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">Tất cả hình thức giao</option>
            @foreach(\App\Enums\ReturnMethod::options() as $value => $label)
                <option value="{{ $value }}" @selected(request('return_method') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead><tr><th scope="col">STT</th><th scope="col">Booking</th><th>Khách hàng</th><th>Nhận / Trả đồ</th><th>Ngày hẹn</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                <tbody>
                @forelse($bookings as $booking)
                    <tr>
                        <td>{{ $bookings->firstItem() + $loop->index }}</td>
                        <td class="text-nowrap fw-semibold">{{ $booking->MaBooking ?: '—' }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <x-admin.customer-avatar :customer="$booking->khachHang" />
                                <div>
                                    <div class="fw-semibold">{{ $booking->khachHang?->HoTen ?: '-' }}</div>
                                    <small class="text-muted">{{ $booking->khachHang?->SoDienThoai ?: 'Chưa có SĐT' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <i class="bi {{ $booking->method_icon }} me-1"></i>Nhận: {{ $booking->method_label }}<br><small>Trả: {{ $booking->return_method_label }}</small>
                        </td>
                        <td>{{ $booking->NgayHen?->format('d/m/Y') ?: '-' }} {{ $booking->GioHen?->format('H:i') ?: '' }}</td>
                        <td><x-admin.status-badge :status="$booking->TrangThai" :enum="\App\Enums\BookingStatus::class" /></td>
                        <td><div class="d-flex gap-2">
                            @can('bookings.view')
                                <a href="{{ route('bookings.show', $booking) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                            @endcan
                            @if($booking->statusEnum() === \App\Enums\BookingStatus::Pending && $booking->donHangs->isEmpty())
                                @can('bookings.confirm')
                                    <x-admin.detail.confirm-form
                                        :action="route('bookings.approve', $booking)"
                                        method="POST"
                                        title="Xác nhận Booking?"
                                        text="Booking sẽ chuyển sang trạng thái Đã xác nhận để tiếp tục phân công nhận đồ."
                                        label="Xác nhận Booking"
                                        icon="bi bi-check2-circle"
                                        variant="btn-order-action"
                                        size=""
                                        :iconOnly="true"
                                        color="#16a34a"
                                        :iconName="'question'"
                                    />
                                    <x-admin.detail.confirm-form
                                        :action="route('bookings.cancel', $booking)"
                                        method="POST"
                                        title="Hủy đặt lịch?"
                                        text="Booking đang chờ sẽ được chuyển sang trạng thái Đã hủy. Bạn có muốn tiếp tục không?"
                                        label="Hủy đặt lịch"
                                        icon="bi bi-x-circle"
                                        variant="btn-order-action delete"
                                        size=""
                                        :iconOnly="true"
                                    />
                                @endcan
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có dữ liệu đặt lịch.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-admin.pagination :paginator="$bookings" label="lịch hẹn" />

@endsection
