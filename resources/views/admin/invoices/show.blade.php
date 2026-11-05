@extends('layouts.app')

@section('title', 'Chi tiết hóa đơn - Sky Laundry')
@section('page-title', 'Chi tiết hóa đơn')

@section('content')
@php
    $order = $invoice->donHang;
    $customer = $order?->khachHang;
    $isPaid = $invoice->isPaid();
    $paidPayments = $order?->thanhToans
        ?->where('TrangThai', \App\Enums\PaymentStatus::Paid->value)
        ?? collect();
    $paidPayment = $paidPayments->sortByDesc('ThoiGian')->first();

@endphp

<div class="invoice-print-heading">
    <h1>HÓA ĐƠN {{ $invoice->MaHoaDon }}</h1>
    <div>Ngày lập: {{ $invoice->NgayLap?->format('d/m/Y') ?: now()->format('d/m/Y') }}</div>
    @if($customer?->HoTen)
        <div>Khách hàng: {{ $customer->HoTen }}</div>
    @endif
    @if($customer?->SoDienThoai)
        <div>Số điện thoại: {{ $customer->SoDienThoai }}</div>
    @endif
</div>

<x-admin.detail.page-header
    title="Hóa đơn {{ $invoice->MaHoaDon }}"
    :subtitle="$invoice->NgayLap?->format('d/m/Y')"
    backUrl="{{ \App\Support\OrderWorkflow::listBackUrl(route('invoices.index')) }}"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$invoice->TrangThai" :enum="\App\Enums\InvoiceStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

@if($isPaid)
    <div class="no-print">
        <x-admin.detail.locked text="Hóa đơn đã thanh toán nên chỉ có thể xem, không thể chỉnh sửa." />
    </div>
@endif

<div class="row g-4 align-items-start invoice-detail-layout">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Dịch vụ đã thực hiện" icon="bi-list-check" :iconClass="'bg-primary-subtle text-primary'" flush>
            <x-slot:header>
                <span class="text-muted small">{{ $order?->chiTietDonHangs?->count() ?? 0 }} mục</span>
            </x-slot:header>

            @if($order?->chiTietDonHangs?->isEmpty())
                <x-admin.detail.empty message="Chưa có dữ liệu dịch vụ" icon="bi-bag" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover detail-table">
                        <thead>
                            <tr>
                                <th scope="col">STT</th>
                                <th>Tên dịch vụ / Loại đồ</th>
                                <th class="text-end">Đơn vị tính</th>
                                <th class="text-end">Số lượng / Khối lượng</th>
                                <th class="text-end">Đơn giá</th>
                                <th class="text-end">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($order?->chiTietDonHangs ?? []) as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td class="fw-semibold">{{ $item->dichVu?->TenDichVu ?: ($item->loaiDoGiat?->TenLoaiDoGiat ?: '—') }}</td>
                                    <td class="text-end">{{ $item->donViTinh?->KyHieu ?? $item->donViTinh?->TenDonViTinh ?? '—' }}</td>
                                    <td class="text-end">{{ format_quantity_weight($item->SoLuong, $item->KhoiLuong) ?: '—' }}</td>
                                    <td class="text-end"><x-admin.detail.money :value="$item->DonGia ?? 0" /></td>
                                    <td class="text-end fw-semibold"><x-admin.detail.money :value="$item->ThanhTien ?? 0" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.detail.panel>

        @if($balance > 0 && $order?->booking?->PhuongThucThanhToan === 'Chuyển khoản' && $balance == $invoice->ThanhTien)
            <x-admin.detail.panel title="Chuyển khoản MB Bank" icon="bi-qr-code-scan" :iconClass="'bg-info-subtle text-info'">
                <x-admin.vietqr-payment
                    :amount="$balance"
                    :memo="\App\Support\BankTransferReference::forOrder($order->DonHangID)"
                    id-prefix="invoice-vietqr"
                />
            </x-admin.detail.panel>
        @endif

        <x-admin.detail.panel title="Lịch sử thay đổi" icon="bi-clock-history" :iconClass="'bg-secondary-subtle text-secondary'" class="no-print">
            @if($invoice->lichSuThayDoiHoaDons->isEmpty())
                <x-admin.detail.empty message="Chưa có lịch sử thay đổi hóa đơn" icon="bi-clock" />
            @else
                <div class="d-flex flex-column gap-3">
                    @foreach($invoice->lichSuThayDoiHoaDons->sortByDesc('ThoiGian') as $change)
                        <div class="border-bottom pb-3">
                            <div class="d-flex justify-content-between gap-3">
                                <strong>{{ $change->TruongThayDoi }}</strong>
                                <small class="text-muted text-nowrap">{{ $change->ThoiGian?->format('d/m/Y H:i') }}</small>
                            </div>
                            <div class="small mt-1">
                                <span class="text-muted">{{ $change->GiaTriCu ?? '—' }}</span>
                                <i class="bi bi-arrow-right mx-1" aria-hidden="true"></i>
                                <span>{{ $change->GiaTriMoi ?? '—' }}</span>
                            </div>
                            <small class="text-muted">
                                Người thực hiện: {{ $change->taiKhoan?->TenDangNhap ?? '—' }}
                            </small>
                            @if($change->LyDo)
                                <div class="small text-muted mt-1">{{ $change->LyDo }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4 d-flex flex-column">
        {{-- Giữ nguyên khối "đầu hóa đơn" để CSS in ẩn/hiện đúng như cũ --}}
        <div class="invoice-header">
            <x-admin.detail.panel title="Thanh toán" icon="bi-receipt-cutoff" :iconClass="'bg-success-subtle text-success'">
                @include('admin.orders.partials.payment-breakdown', [
                    'order' => $order,
                    'invoice' => $invoice,
                    'matchMobile' => true,
                ])
                @if($paidPayment || $order?->booking?->PhuongThucThanhToan)
                    <div class="border-top mt-3 pt-3">
                        <small class="text-muted d-block mt-2">
                            @if($paidPayment?->MaGiaoDich)
                                Mã giao dịch: {{ $paidPayment->MaGiaoDich }}
                            @else
                                Hóa đơn đã được thanh toán.
                            @endif
                        </small>
                    </div>
                @endif
            </x-admin.detail.panel>

        </div>

        <x-admin.detail.panel title="Thông tin hóa đơn" icon="bi-file-earmark-text" :iconClass="'bg-secondary-subtle text-secondary'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Mã hóa đơn" :value="$invoice->MaHoaDon" />
                <x-admin.detail.info-item label="Ngày lập" :value="$invoice->NgayLap?->format('d/m/Y') ?: now()->format('d/m/Y')" />
                <x-admin.detail.info-item label="Đơn hàng">
                    @if($order)
                        @can('orders.view')
                        <a href="{{ route('orders.show', $order->DonHangID) }}" class="text-decoration-none">
                            {{ $order->MaDonHang }}
                        </a>
                        @else
                        {{ $order->MaDonHang }}
                        @endcan
                    @else
                        <span class="detail-empty-value">—</span>
                    @endif
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            @if($invoice->GhiChu)
                <div class="mt-3">
                    <div class="detail-field__label mb-2">Ghi chú hóa đơn</div>
                    <div class="detail-text">{{ $invoice->GhiChu }}</div>
                </div>
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Khách hàng" icon="bi-person" :iconClass="'bg-info-subtle text-info'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Họ và tên" :value="$customer?->HoTen" />
                <x-admin.detail.info-item label="Số điện thoại" :value="$customer?->SoDienThoai" />
                <x-admin.detail.info-item label="Địa chỉ" :value="$customer?->DiaChi" />
            </x-admin.detail.info-grid>

            @if($order?->GhiChu)
                <div class="mt-3">
                    <div class="detail-field__label mb-2">Ghi chú đơn hàng</div>
                    <div class="detail-text">{{ $order->GhiChu }}</div>
                </div>
            @endif
        </x-admin.detail.panel>
    </div>
</div>
@endsection

@push('styles')
<style>
    .invoice-print-heading {
        display: none;
    }

    @page {
        size: A4 portrait;
        margin: 14mm;
    }

    @media print {
        .sidebar-wrapper,
        .navbar-custom,
        .detail-panel__icon,
        .no-print,
        .card.bg-light,
        .detail-header {
            display: none !important;
        }

        body,
        .main-wrapper {
            margin: 0 !important;
            background: #fff !important;
            color: #000 !important;
        }

        .main-wrapper {
            width: 100% !important;
            min-height: 0 !important;
        }

        .content-wrapper {
            padding: 0 !important;
        }

        .invoice-print-heading {
            display: block !important;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #999;
            color: #000 !important;
            font-size: 10pt;
        }

        .invoice-print-heading h1 {
            margin: 0 0 0.5rem;
            font-size: 18pt;
            font-weight: 700;
        }

        .invoice-detail-layout {
            display: block !important;
            margin: 0 !important;
        }

        .invoice-detail-layout > [class*="col-"] {
            width: 100% !important;
            max-width: 100% !important;
            flex: 0 0 100% !important;
            padding: 0 !important;
        }

        .card {
            border: none !important;
            box-shadow: none !important;
        }

        .detail-panel {
            margin-bottom: 0.75rem !important;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .table-responsive {
            overflow: visible !important;
        }

        .detail-table {
            width: 100% !important;
            border-collapse: collapse !important;
            color: #000 !important;
            font-size: 9pt;
        }

        .detail-table th,
        .detail-table td {
            padding: 0.35rem !important;
            border: 1px solid #999 !important;
            background: #fff !important;
            color: #000 !important;
        }

        a {
            color: #000 !important;
            text-decoration: none !important;
        }
    }
</style>
@endpush
