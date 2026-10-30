@extends('layouts.app')

@section('title', ($isIntake ? 'Chi tiết phiếu nhận đồ' : 'Chi tiết phiếu giao đồ').' - Sky Laundry')
@section('page-title', $isIntake ? 'Chi tiết phiếu nhận đồ' : 'Chi tiết phiếu giao đồ')

@section('content')
@php
    $booking = $bookingDocument->booking;
    $order = $bookingDocument->donHang ?? $booking?->donHangs->first();
    $inspection = $booking?->kiemKe;
    $decision = $inspection?->DuLieuThucTe['_decision'] ?? [];
    $decisionTimestamp = $decision['confirmed_at'] ?? $decision['decided_at'] ?? null;
    $formattedDecisionTimestamp = $decisionTimestamp
        ? \Illuminate\Support\Carbon::parse($decisionTimestamp)->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s')
        : 'Chưa chốt kiểm kê';
    $statusValue = ! $isIntake
        ? ($bookingDocument->donHang?->giaoNhans->firstWhere('LoaiGiaoNhan', 'GIAO_DO')?->TrangThai ?? $bookingDocument->TrangThai)
        : $bookingDocument->TrangThai;
@endphp

<x-admin.detail.page-header
    title="{{ $isIntake ? 'Trạng thái nhân viên nhận đồ' : 'Trạng thái nhân viên giao đồ' }}"
    :backUrl="route('deliveries.index')"
>
    <x-slot:badge>
        @if($isIntake && $statusValue === 'Hoàn thành')
            <x-admin.status-badge
                :status="\App\Enums\DeliveryStatus::Completed"
                :enum="\App\Enums\DeliveryStatus::class"
                label="Đã nhận đồ"
            />
        @else
            <x-admin.status-badge :status="\App\Enums\DeliveryStatus::parseForLeg($statusValue, $bookingDocument->LoaiGiaoNhan)" :enum="\App\Enums\DeliveryStatus::class" />
        @endif
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4">
    <div class="col-12">
        <x-admin.detail.panel :title="$isIntake ? 'Thông tin phiếu nhận đồ' : 'Thông tin phiếu giao đồ'" icon="bi-receipt" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã phiếu" :value="$displayCode" />
                <x-admin.detail.info-item label="Mã đặt lịch" :value="$booking?->MaBooking ?: '—'" />
                <x-admin.detail.info-item label="Khách hàng" :value="$booking?->khachHang?->HoTen ?: '—'" />
                <x-admin.detail.info-item label="Số điện thoại" :value="$booking?->khachHang?->SoDienThoai ?: '—'" />
                <x-admin.detail.info-item :label="$isIntake ? 'HÌNH THỨC NHÂN VIÊN NHẬN ĐỒ' : 'HÌNH THỨC NHÂN VIÊN GIAO ĐỒ'" :value="$bookingDocument->HinhThuc ?: '—'" />
                <x-admin.detail.info-item :label="$isIntake ? 'Địa chỉ nhận đồ' : 'Địa chỉ giao đồ'" :value="$bookingDocument->DiaChi ?: 'Không yêu cầu địa chỉ giao tận nhà'" />
                <x-admin.detail.info-item :label="$isIntake ? 'Nhân viên phụ trách nhận đồ' : 'Nhân viên phụ trách giao đồ'" :value="$bookingDocument->nhanVien?->HoTen ?: 'Chưa phân công'" />
                <x-admin.detail.info-item label="Nhân viên xác nhận Booking" :value="$booking?->nhanVienXacNhan?->HoTen ?: '—'" />
                <x-admin.detail.info-item :label="$isIntake ? 'Thời gian nhận đồ dự kiến' : 'Thời gian giao đồ dự kiến'" :value="$bookingDocument->ThoiGianDuKien?->format('d/m/Y H:i') ?: 'Chưa lên lịch'" />
                <x-admin.detail.info-item :label="$isIntake ? 'Thời gian nhận đồ thực tế' : 'Thời gian giao đồ thực tế'" :value="$bookingDocument->ThoiGianThucTe?->format('d/m/Y H:i') ?: 'Chưa ghi nhận'" />
                <x-admin.detail.info-item label="Phí dự kiến" :value="number_format((float) $bookingDocument->PhiGiaoNhan, 0, ',', '.') . ' VNĐ'" />
                <x-admin.detail.info-item label="Đơn hàng liên kết">
                    @if($order)
                        @can('orders.view')
                            <a href="{{ route('orders.show', $order) }}">{{ $order->MaDonHang }}</a>
                        @else
                            {{ $order->MaDonHang }}
                        @endcan
                    @else
                        <span class="text-muted">Chưa tạo đơn hàng</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái đặt lịch">
                    @if($booking)
                        <x-admin.status-badge :status="$booking->TrangThai" :enum="\App\Enums\BookingStatus::class" :pill="false" />
                    @else
                        —
                    @endif
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>

        @if($booking)
            <x-admin.detail.panel title="Thông tin Booking" icon="bi-calendar-check" :iconClass="'bg-info-subtle text-info'">
                <x-admin.detail.info-grid :columns="2">
                    <x-admin.detail.info-item label="Nhận đồ" :value="$booking->method_label" />
                    <x-admin.detail.info-item label="Trả đồ" :value="$booking->return_method_label" />
                    <x-admin.detail.info-item label="Ngày hẹn" :value="$booking->NgayHen?->format('d/m/Y') ?: '—'" />
                    <x-admin.detail.info-item label="Ghi chú" :value="$booking->GhiChu ?: '—'" />
                </x-admin.detail.info-grid>
                @can('bookings.view')
                    <a class="btn btn-outline-primary btn-sm mt-3" href="{{ route('bookings.show', $booking) }}">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem Booking
                    </a>
                @endcan
            </x-admin.detail.panel>
        @endif

        @if($inspection)
            <x-admin.detail.panel title="Kết quả kiểm kê" icon="bi-clipboard-check" :iconClass="'bg-success-subtle text-success'">
                <x-admin.detail.info-grid :columns="2">
                    <x-admin.detail.info-item label="Nhân viên chốt kiểm kê" :value="$decision['employee_name'] ?? '—'" />
                    <x-admin.detail.info-item label="Thời gian chốt" :value="$formattedDecisionTimestamp" />
                    @if(array_key_exists('customer_accepted', $decision))
                        <x-admin.detail.info-item label="Khách đồng ý báo giá">
                            {{ $decision['customer_accepted'] ? 'Có' : 'Không' }}
                        </x-admin.detail.info-item>
                    @endif
                    @if(array_key_exists('agreed_total', $decision))
                        <x-admin.detail.info-item label="Tổng tiền khách đồng ý" :value="number_format((float) $decision['agreed_total'], 0, ',', '.') . ' VNĐ'" />
                    @endif
                    @if(array_key_exists('charged_total', $decision))
                        <x-admin.detail.info-item label="Số tiền đã thu" :value="number_format((float) $decision['charged_total'], 0, ',', '.') . ' VNĐ'" />
                    @endif
                    @if(! empty($decision['reason']))
                        <x-admin.detail.info-item label="Lý do khách từ chối" :value="$decision['reason']" />
                    @endif
                </x-admin.detail.info-grid>
            </x-admin.detail.panel>
        @endif
    </div>
</div>
@endsection
