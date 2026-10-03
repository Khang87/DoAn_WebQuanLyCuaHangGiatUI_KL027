@extends('layouts.app')

@section('title', 'Chi tiết hóa đơn - Sky Laundry')
@section('page-title', 'Chi tiết hóa đơn')

@section('content')
@php
    $order = $invoice->donHang;
    $customer = $order?->khachHang;
    $isPaid = $invoice->isPaid();
    $hasDiscount = (float) $invoice->GiamGia > 0;

    // Tính trước: dấu ">" trong biểu thức thuộc tính của thẻ Blade sẽ làm hỏng
    // trình phân tích thẻ, nên không được đặt trực tiếp trong class="...".
    $balanceTextClass = $balance > 0 ? 'text-danger' : 'text-success';
@endphp

<x-admin.detail.page-header
    title="Hóa đơn {{ $invoice->MaHoaDon ?: '#' . $invoice->HoaDonID }}"
    :subtitle="$invoice->NgayLap?->format('d/m/Y')"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$invoice->TrangThai" :enum="\App\Enums\InvoiceStatus::class" />
        @if($isPaid)
            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary px-3 py-2 rounded-pill">
                <i class="bi bi-lock-fill me-1"></i>Đã quyết toán
            </span>
        @endif
    </x-slot:badge>
</x-admin.detail.page-header>

@if($isPaid)
    <x-admin.detail.locked text="Hóa đơn đã thanh toán nên bị khóa. Liên hệ Chủ cửa hàng nếu cần điều chỉnh." />
@endif

<div class="row g-4 align-items-start">
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
                                <th>STT</th>
                                <th>Tên dịch vụ / Loại đồ</th>
                                <th class="text-end">Đơn vị tính</th>
                                <th class="text-end">Số lượng / Khối lượng</th>
                                <th class="text-end">Đơn giá</th>
                                <th class="text-end">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(($order?->chiTietDonHangs ?? []) as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
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

        @if($balance > 0)
            <x-admin.detail.panel title="Thanh toán" icon="bi-qr-code-scan" :iconClass="'bg-info-subtle text-info'">
                <div class="text-center py-3">
                    <div class="bg-white border rounded d-inline-block p-4">
                        <i class="bi bi-qr-code-scan text-muted" style="font-size: 4rem;"></i>
                        <p class="text-muted small mt-2 mb-0">Quét mã để thanh toán qua ngân hàng / ví điện tử</p>
                    </div>
                    <div class="mt-3">
                        <span class="detail-field__label">Nội dung chuyển khoản</span>
                        <div class="fw-semibold">Thanh toan hoa don {{ $invoice->MaHoaDon }}</div>
                    </div>
                </div>
            </x-admin.detail.panel>
        @endif

        <x-admin.detail.panel title="Lịch sử thay đổi" icon="bi-clock-history" :iconClass="'bg-secondary-subtle text-secondary'">
            @if($invoice->lichSuThayDoiHoaDons->isEmpty())
                <x-admin.detail.empty message="Chưa có lịch sử thay đổi hóa đơn" icon="bi-clock" />
            @else
                <div class="d-flex flex-column gap-3">
                    @foreach($invoice->lichSuThayDoiHoaDons->sortByDesc('ThoiGian') as $change)
                        <div class="border-bottom pb-3">
                            <div class="d-flex justify-content-between gap-3">
                                <strong>{{ $change->TruongThayDoi }}</strong>
                                <small class="text-muted text-nowrap">{{ $change->ThoiGian?->format('d-m-Y H:i') }}</small>
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
            <x-admin.detail.panel title="Tổng hợp" icon="bi-receipt-cutoff" :iconClass="'bg-success-subtle text-success'">
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="detail-summary__label">Tạm tính</span>
                    <x-admin.detail.money :value="$invoice->TongTien" class="fw-semibold" />
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="detail-summary__label">Giảm giá (Voucher + Điểm)</span>
                    <x-admin.detail.money :value="$invoice->GiamGia" :negative="$hasDiscount" class="text-danger" />
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="detail-summary__label">Phí giao hàng</span>
                    <x-admin.detail.money :value="$invoice->PhiGiaoHang" class="fw-semibold" />
                </div>

                <div class="detail-summary d-flex justify-content-between align-items-center">
                    <span class="detail-summary__total">Tổng cộng</span>
                    <x-admin.detail.money :value="$invoice->ThanhTien" class="detail-summary__total text-primary" />
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
                        <input type="hidden" name="status" value="{{ \App\Enums\InvoiceStatus::Paid->value }}">
                    </x-slot:hidden>
                </x-admin.detail.confirm-form>
            @endunless
        </div>

        <x-admin.detail.panel title="Thông tin hóa đơn" icon="bi-file-earmark-text" :iconClass="'bg-secondary-subtle text-secondary'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Mã hóa đơn" :value="$invoice->MaHoaDon" />
                <x-admin.detail.info-item label="Ngày lập" :value="$invoice->NgayLap?->format('d/m/Y') ?: now()->format('d/m/Y')" />
                <x-admin.detail.info-item label="Đơn hàng">
                    @if($order)
                        <a href="{{ route('orders.show', $order->DonHangID) }}" class="text-decoration-none">
                            {{ $order->MaDonHang }}
                        </a>
                    @else
                        <span class="detail-empty-value">—</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$invoice->TrangThai" :enum="\App\Enums\InvoiceStatus::class" :pill="false" />
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

        <div class="card shadow-sm border-0 no-print order-first">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="{{ route('invoices.export-excel', $invoice->HoaDonID) }}" class="btn btn-outline-primary w-100 py-2">
                    <i class="fas fa-file-excel me-1"></i> Xuất excel
                </a>

                <button type="button" class="btn btn-outline-primary w-100 py-2" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> In hóa đơn
                </button>

                @can('invoices.delete')
                    <x-admin.detail.confirm-form
                        :action="route('invoices.destroy', $invoice->HoaDonID)"
                        title="Xóa hóa đơn?"
                        text="Hóa đơn thuộc đơn đã quyết toán chỉ có thể xóa theo quyền quản lý."
                        label="Xóa hóa đơn"
                        icon="bi-trash"
                        variant="btn-outline-danger"
                        size="py-2"
                        block
                    />
                @endcan

                <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                </a>
            </div>
        </div>
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
        .no-print,
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
