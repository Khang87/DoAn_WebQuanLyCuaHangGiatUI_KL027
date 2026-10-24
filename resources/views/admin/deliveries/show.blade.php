@extends('layouts.app')

@section('title', 'Chi tiết phiếu giao nhận - Sky Laundry')
@section('page-title', 'Chi tiết phiếu giao nhận')

@section('content')
@php
    $deliveryStatus = \App\Enums\DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan);
    $activeDeliveryStatus = $delivery->LoaiGiaoNhan === 'NHAN_DO'
        ? \App\Enums\DeliveryStatus::Picking
        : \App\Enums\DeliveryStatus::Delivering;
    $deliveryProgressSteps = [
        [
            'status' => \App\Enums\DeliveryStatus::Pending,
            'label' => $delivery->LoaiGiaoNhan === 'GIAO_DO' && $delivery->HinhThuc === 'Tại cửa hàng'
                ? 'Chờ khách đến nhận tại cửa hàng'
                : 'Chờ xác nhận',
        ],
        ['status' => $activeDeliveryStatus, 'label' => $activeDeliveryStatus->label()],
        ['status' => \App\Enums\DeliveryStatus::Completed, 'label' => 'Hoàn thành'],
    ];
    $completedDeliverySteps = match ($deliveryStatus) {
        \App\Enums\DeliveryStatus::Picking, \App\Enums\DeliveryStatus::Delivering => 1,
        \App\Enums\DeliveryStatus::Completed => count($deliveryProgressSteps),
        default => 0,
    };
@endphp

@push('styles')
    <style>
        .order-detail-timeline {
            display: grid;
            gap: 0.25rem;
        }

        .order-detail-timeline .detail-timeline__item {
            min-height: 2.5rem;
            padding: 0.375rem 0.625rem;
            border-radius: 0.5rem;
        }

        .order-detail-timeline .detail-timeline__dot {
            display: inline-flex;
            width: 1.25rem;
            height: 1.25rem;
            align-items: center;
            justify-content: center;
            border: 2px solid #cbd5e1;
            background: #fff;
            color: #fff;
            font-size: 0.75rem;
        }

        .order-detail-timeline .detail-timeline__item--done {
            color: #166534;
        }

        .order-detail-timeline .detail-timeline__item--done .detail-timeline__dot {
            border-color: #16a34a;
            background: #16a34a;
        }

        .order-detail-timeline .detail-timeline__item--current {
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px #93c5fd;
            color: #1e3a8a;
        }

        .order-detail-timeline .detail-timeline__item--current .detail-timeline__dot {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px #dbeafe;
        }

        .order-detail-timeline .detail-timeline__item--muted {
            color: #64748b;
        }
    </style>
@endpush

<x-admin.detail.page-header
    title="Chi tiết phiếu giao nhận"
    :subtitle="$displayCode . ' · ' . ($delivery->LoaiGiaoNhan === 'NHAN_DO' ? 'Nhận đồ' : 'Giao đồ') . ' · ' . ($delivery->ThoiGianDuKien?->format('d/m/Y') ?: 'Chưa hẹn ngày')"
    backUrl="{{ route('deliveries.index') }}"
>
    <x-slot:badge>
        <x-admin.status-badge :status="\App\Enums\DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan)" :enum="\App\Enums\DeliveryStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    {{-- ============ CỘT CHÍNH (7/12) ============ --}}
    <div class="col-lg-7">
        <x-admin.detail.panel title="Thông tin giao nhận" icon="bi-truck" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã phiếu" :value="$displayCode" />
                <x-admin.detail.info-item label="Loại giao nhận">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        <i class="bi {{ $delivery->LoaiGiaoNhan === 'NHAN_DO' ? 'bi-box-arrow-in-down' : 'bi-truck' }} me-1"></i>{{ $delivery->LoaiGiaoNhan === 'NHAN_DO' ? 'Nhận đồ' : 'Giao đồ' }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Hình thức" :value="$delivery->HinhThuc" />
                <x-admin.detail.info-item label="Đơn hàng">
                    @if($delivery->donHang)
                        @can('orders.view')
                        <a href="{{ route('orders.show', $delivery->donHang->DonHangID) }}" class="text-decoration-none">
                            {{ $delivery->donHang->MaDonHang }}
                        </a>
                        @else
                        {{ $delivery->donHang->MaDonHang }}
                        @endcan
                    @else
                        <span class="detail-empty-value">—</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="\App\Enums\DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan)" :enum="\App\Enums\DeliveryStatus::class" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Ngày giao nhận" :value="$delivery->ThoiGianDuKien?->format('d/m/Y')" />
                <x-admin.detail.info-item label="Giờ giao nhận" :value="$delivery->ThoiGianDuKien?->format('H:i')" />
                <x-admin.detail.info-item label="Thời gian bàn giao thực tế" :value="$delivery->ThoiGianThucTe?->format('d/m/Y H:i') ?: 'Chưa hoàn tất'" />
                <x-admin.detail.info-item label="Nhân viên phụ trách" :value="$delivery->nhanVien?->HoTen ?: 'Chưa phân công'" />
                <x-admin.detail.info-item label="Ngày tạo" :value="$delivery->ThoiGianDuKien?->format('d/m/Y H:i')" />
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Địa chỉ</div>
                <div class="detail-text">{{ $delivery->DiaChi ?: 'Chưa cập nhật địa chỉ.' }}</div>
            </div>

            @if($delivery->GhiChu)
                @can('customers.view')
                <div class="mt-3">
                    <div class="detail-field__label mb-2">Ghi chú</div>
                    <div class="detail-text">{{ $delivery->GhiChu }}</div>
                </div>
                @endcan
            @endif
        </x-admin.detail.panel>

        @if(config('giatui.booking_two_step') && $delivery->LoaiGiaoNhan === 'GIAO_DO')
            @php
                $linkedBooking = $delivery->donHang?->booking;
                $returnNote = $linkedBooking?->giaoNhanDuKiens->firstWhere('LoaiGiaoNhan', 'GIAO_DO');
                $approvedInspection = $linkedBooking?->kiemKe;
            @endphp
            @if($returnNote?->DonHangID === $delivery->DonHangID
                && $returnNote?->BookingKiemKeID === $approvedInspection?->BookingKiemKeID)
                <x-admin.detail.panel title="Đồ trả từ phiếu nhận" icon="bi-clipboard-check" :iconClass="'bg-info-subtle text-info'">
                    <p class="small text-muted mb-2">
                        Đã đối soát theo Booking {{ $linkedBooking->MaBooking }} · kiểm kê phiên bản {{ $approvedInspection->PhienBan }}.
                        @can('bookings.view')<a href="{{ route('bookings.show', $linkedBooking) }}">Xem Booking</a>@endcan
                    </p>
                    <ul class="small mb-0 ps-3" data-delivery-return-items>
                        @foreach($delivery->donHang->chiTietDonHangs as $item)
                            <li>{{ $item->item_name }} · {{ $item->KhoiLuong ?: $item->SoLuong }} {{ $item->unit }} · {{ $item->TinhTrangTruocKhiGiat }}</li>
                        @endforeach
                    </ul>
                </x-admin.detail.panel>
            @endif
        @endif

        <x-admin.detail.panel title="Tiến độ giao nhận" icon="bi-clock-history" :iconClass="'bg-secondary-subtle text-secondary'">
            <div class="detail-timeline order-detail-timeline">
                @foreach($deliveryProgressSteps as $index => $step)
                    @php
                        $isCurrentStep = $deliveryStatus === $step['status'];
                        $isCompletedStep = $index < $completedDeliverySteps;
                        $timelineState = $isCompletedStep ? 'done' : ($isCurrentStep ? 'current' : 'muted');
                    @endphp
                    <div class="detail-timeline__item detail-timeline__item--{{ $timelineState }}">
                        <span class="detail-timeline__dot">
                            @if($isCompletedStep)<i class="bi bi-check-lg" aria-hidden="true"></i>@endif
                        </span>
                        <span>{{ $step['label'] }}</span>
                        @if($isCurrentStep)
                            <span class="badge {{ $isCompletedStep ? 'bg-success-subtle text-success-emphasis border border-success' : 'bg-primary-subtle text-primary-emphasis border border-primary' }} px-2 py-1 rounded-pill ms-auto">
                                {{ $isCompletedStep ? 'Đã hoàn tất' : 'Hiện tại' }}
                            </span>
                        @endif
                    </div>
                @endforeach

                @if($deliveryStatus === \App\Enums\DeliveryStatus::Cancelled)
                    <div class="detail-timeline__item text-danger fw-semibold">
                        <span class="detail-timeline__dot bg-danger"></span>
                        <span>Đã hủy</span>
                        <span class="badge bg-danger-subtle text-danger-emphasis border border-danger px-2 py-1 rounded-pill ms-auto">Hiện tại</span>
                    </div>
                @endif
            </div>
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (5/12) ============ --}}
    <div class="col-lg-5 d-flex flex-column">
        {{-- Mọi nút hành động của phiếu nằm ở cột "Thao tác" trong danh sách. --}}
        @if($delivery->LoaiGiaoNhan === 'GIAO_DO' && $deliveryStatus === \App\Enums\DeliveryStatus::Cancelled)
            <p class="small text-muted">Lịch giao đã hủy; phiếu chỉ còn để tra cứu lịch sử.</p>
        @endif

        <x-admin.detail.panel title="Khách hàng" icon="bi-person" :iconClass="'bg-secondary-subtle text-secondary'">
            @php
                $deliveryCustomer = $delivery->donHang?->khachHang;
            @endphp

            @if($deliveryCustomer)
                <x-admin.detail.info-grid :columns="1">
                    <x-admin.detail.info-item label="Họ và tên" :value="$deliveryCustomer->HoTen" />
                    <x-admin.detail.info-item label="Số điện thoại" :value="$deliveryCustomer->SoDienThoai" />
                    <x-admin.detail.info-item label="Địa chỉ" :value="$deliveryCustomer->DiaChi" />
                </x-admin.detail.info-grid>
                <div class="mt-3">
                    <a href="{{ route('customers.show', $deliveryCustomer->KhachHangID) }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Hồ sơ khách hàng
                    </a>
                </div>
            @else
                <x-admin.detail.empty message="Phiếu giao nhận chưa gắn khách hàng" icon="bi-person" />
            @endif
        </x-admin.detail.panel>
    </div>
</div>
@endsection
