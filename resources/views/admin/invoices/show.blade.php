@extends('layouts.app')

@section('title', 'Chi tiết hóa đơn - Sky Laundry')
@section('page-title', 'Chi tiết hóa đơn')

@section('content')
@php
    $customer = $invoice->order?->customer;
    $totalPaid = $invoice->total_paid;
    $balance = $invoice->balance;
    $isPaid = $invoice->isPaid();
    $hasDiscount = (float) $invoice->discount_amount > 0;

    // Tính trước: dấu ">" trong biểu thức thuộc tính của thẻ Blade sẽ làm hỏng
    // trình phân tích thẻ, nên không được đặt trực tiếp trong class="...".
    $balanceTextClass = $balance > 0 ? 'text-danger' : 'text-success';
@endphp

<x-admin.detail.page-header
    title="Hóa đơn {{ $invoice->code ?: '#' . $invoice->id }}"
    :back="route('invoices.index')"
    :subtitle="$invoice->invoice_date?->format('d/m/Y')"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$invoice->status" :enum="\App\Enums\InvoiceStatus::class" />
        @if($isPaid)
            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary px-3 py-2 rounded-pill">
                <i class="bi bi-lock-fill me-1"></i>Đã quyết toán
            </span>
        @endif
    </x-slot:badge>

    <x-slot:actions>
        <a href="{{ route('invoices.export-excel', $invoice->id) }}" class="btn btn-outline-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Xuất excel
        </a>
        <button type="button" class="btn btn-outline-info btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>In hóa đơn
        </button>
    </x-slot:actions>
</x-admin.detail.page-header>

@if($isPaid)
    <x-admin.detail.locked text="Hóa đơn đã thanh toán nên bị khóa. Liên hệ Chủ cửa hàng nếu cần điều chỉnh." />
@endif

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Dịch vụ đã thực hiện" icon="bi-list-check" :iconClass="'bg-primary-subtle text-primary'" flush>
            <x-slot:header>
                <span class="text-muted small">{{ $invoice->order?->items?->count() ?? 0 }} mục</span>
            </x-slot:header>

            @if($invoice->order?->items?->isEmpty())
                <x-admin.detail.empty message="Chưa có dữ liệu dịch vụ" icon="bi-bag" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover detail-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Tên dịch vụ / Loại đồ</th>
                                <th class="text-end">Đơn vị tính</th>
                                <th class="text-end">Số lượng</th>
                                <th class="text-end">Đơn giá</th>
                                <th class="text-end">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($invoice->order?->items ?? []) as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td class="fw-semibold">{{ $item->service?->name ?: ($item->item_name ?: '—') }}</td>
                                    <td class="text-end">{{ $item->service?->unit ?: 'kg' }}</td>
                                    <td class="text-end">{{ $item->quantity ?? 0 }}</td>
                                    <td class="text-end"><x-admin.detail.money :value="$item->price ?? 0" /></td>
                                    <td class="text-end fw-semibold"><x-admin.detail.money :value="$item->subtotal ?? 0" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.detail.panel>

        @if($balance > 0)
            <x-admin.detail.panel title="Thanh toán" icon="bi-qr-code-scan" :iconClass="'bg-info-subtle text-info'">
                <div class="text-center py-3">
                    <div class="bg-white border rounded d-inline-block p-4">
                        <i class="bi bi-qr-code-scan text-muted" style="font-size: 4rem;"></i>
                        <p class="text-muted small mt-2 mb-0">Quét mã để thanh toán qua ngân hàng / ví điện tử</p>
                    </div>
                    <div class="mt-3">
                        <span class="detail-field__label">Nội dung chuyển khoản</span>
                        <div class="fw-semibold">Thanh toan hoa don {{ $invoice->code }}</div>
                    </div>
                </div>
            </x-admin.detail.panel>
        @endif
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        {{-- Giữ nguyên khối "đầu hóa đơn" để CSS in ẩn/hiện đúng như cũ --}}
        <div class="invoice-header">
            <x-admin.detail.panel title="Tổng hợp" icon="bi-receipt-cutoff" :iconClass="'bg-success-subtle text-success'">
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="detail-summary__label">Tạm tính</span>
                    <x-admin.detail.money :value="$invoice->total_amount" class="fw-semibold" />
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="detail-summary__label">Giảm giá (Voucher + Điểm)</span>
                    <x-admin.detail.money :value="$invoice->discount_amount" :negative="$hasDiscount" class="text-danger" />
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="detail-summary__label">Phí giao hàng</span>
                    <x-admin.detail.money :value="$invoice->delivery_fee" class="fw-semibold" />
                </div>

                <div class="detail-summary d-flex justify-content-between align-items-center">
                    <span class="detail-summary__total">Tổng cộng</span>
                    <x-admin.detail.money :value="$invoice->grand_total" class="detail-summary__total text-primary" />
                </div>

                @if($totalPaid > 0)
                    <div class="d-flex justify-content-between align-items-center py-2 mt-2">
                        <span class="detail-summary__label">Đã thanh toán</span>
                        <x-admin.detail.money :value="$totalPaid" class="text-success fw-semibold" />
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <span class="detail-summary__total">Còn nợ</span>
                        <x-admin.detail.money :value="$balance" class="detail-summary__total {{ $balanceTextClass }}" />
                    </div>
                @endif
            </x-admin.detail.panel>

            @unless($isPaid)
                <x-admin.detail.confirm-form
                    :action="route('invoices.update-status', $invoice)"
                    method="POST"
                    title="Đánh dấu đã thanh toán?"
                    text="Hóa đơn sẽ được chốt và không thể sửa nữa."
                    label="Đánh dấu đã thanh toán"
                    icon="bi-check-lg"
                    variant="btn-outline-success"
                    color="#16a34a"
                    :block="true"
                >
                    <x-slot:hidden>
                        <input type="hidden" name="status" value="paid">
                    </x-slot:hidden>
                </x-admin.detail.confirm-form>
            @endunless
        </div>

        <x-admin.detail.panel title="Thông tin hóa đơn" icon="bi-file-earmark-text" :iconClass="'bg-secondary-subtle text-secondary'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Mã hóa đơn" :value="$invoice->code" />
                <x-admin.detail.info-item label="Ngày lập" :value="$invoice->invoice_date?->format('d/m/Y') ?: now()->format('d/m/Y')" />
                <x-admin.detail.info-item label="Đơn hàng">
                    @if($invoice->order)
                        <a href="{{ route('orders.show', $invoice->order->id) }}" class="text-decoration-none">
                            {{ $invoice->order->code }}
                        </a>
                    @else
                        <span class="detail-empty-value">—</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$invoice->status" :enum="\App\Enums\InvoiceStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            @if($invoice->notes)
                <div class="mt-3">
                    <div class="detail-field__label mb-2">Ghi chú hóa đơn</div>
                    <div class="detail-text">{{ $invoice->notes }}</div>
                </div>
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Khách hàng" icon="bi-person" :iconClass="'bg-info-subtle text-info'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Họ và tên" :value="$customer?->name" />
                <x-admin.detail.info-item label="Số điện thoại" :value="$customer?->phone" />
                <x-admin.detail.info-item label="Địa chỉ" :value="$customer?->address" />
            </x-admin.detail.info-grid>

            @if($invoice->order?->notes)
                <div class="mt-3">
                    <div class="detail-field__label mb-2">Ghi chú đơn hàng</div>
                    <div class="detail-text">{{ $invoice->order->notes }}</div>
                </div>
            @endif
        </x-admin.detail.panel>
    </div>
</div>
@endsection

@push('styles')
<style>
    @media print {
        .sidebar-wrapper,
        .navbar-custom,
        .detail-header,
        .detail-panel__icon,
        .card.bg-light {
            display: none !important;
        }

        .content-wrapper {
            padding: 0 !important;
        }

        .card {
            border: none !important;
            box-shadow: none !important;
        }

        .detail-panel {
            page-break-inside: avoid;
        }
    }
</style>
@endpush
