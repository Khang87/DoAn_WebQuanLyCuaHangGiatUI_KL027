@extends('layouts.app')

@section('title', 'Chi tiết đơn hàng - Sky Laundry')
@section('page-title', 'Chi tiết đơn hàng')

@section('content')
@php
    $isPaid = $order->is_paid;
    $isOwner = auth()->user()?->isOwner() ?? false;
    $canEdit = ! $isPaid || $isOwner;

    // Tính trước để tránh dấu ">" trong biểu thức thuộc tính của thẻ Blade.
    $hasPromotionDiscount = (float) $order->discount_by_promotion > 0;
    $hasPointsDiscount = (float) $order->discount_by_points > 0;
    $statusKeys = array_keys($statusFlow);
    $currentStatusIndex = array_search($order->status, $statusKeys, true);
    $isCancelled = $order->status === \App\Enums\OrderStatus::Cancelled->value;
    $isReceiving = $order->status === \App\Enums\OrderStatus::Pending->value;
    $currentStatusIsCompleted = \App\Enums\OrderStatus::parse($order->status)->isCompletedMilestone();
    $inspectionItems = old('items');
    if (! is_array($inspectionItems)) {
        $inspectionItems = $order->chiTietDonHangs->map(fn ($item) => [
            'ChiTietDonHangID' => $item->ChiTietDonHangID,
            'DichVuID' => $item->DichVuID,
            'LoaiDoGiatID' => $item->LoaiDoGiatID,
            'DonViTinhID' => $item->DonViTinhID,
            'SoLuong' => $item->SoLuong,
            'KhoiLuong' => $item->KhoiLuong,
            'GhiChu' => $item->GhiChu,
        ])->values()->all();
    }
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

        .order-detail-page .detail-summary__label {
            color: #475569;
        }

        .order-detail-page .detail-field__label {
            color: #475569;
        }
    </style>
@endpush

<x-admin.detail.page-header
    title="Đơn hàng {{ $order->code }}"
    :subtitle="$order->created_at?->format('d/m/Y H:i')"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$order->status" :enum="\App\Enums\OrderStatus::class" />
        @if($isPaid)
            <span class="badge {{ $isOwner ? 'bg-success-subtle text-success-emphasis border-success' : 'bg-secondary-subtle text-secondary-emphasis border-secondary' }} border px-3 py-2 rounded-pill">
                <i class="bi {{ $isOwner ? 'bi-shield-check' : 'bi-lock-fill' }} me-1"></i>
                {{ $isOwner ? 'Đã quyết toán · Chủ cửa hàng được phép điều chỉnh' : 'Đã quyết toán' }}
            </span>
        @endif
        @if($isReceiving)
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 rounded-pill">
                <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>Chờ kiểm tra và tiếp nhận thực tế
            </span>
        @endif
    </x-slot:badge>
</x-admin.detail.page-header>

@if($order->is_locked && ! $isOwner)
    <x-admin.detail.locked text="Đơn hàng đã quyết toán nên bị khóa sửa/xóa. Liên hệ Chủ cửa hàng nếu cần điều chỉnh." />
@endif

<div class="order-detail-page">
<div class="row g-4 align-items-start">
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin đơn hàng" icon="bi-receipt" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Khách hàng">
                    @if($order->customer)
                        <a href="{{ route('customers.show', $order->customer->getKey()) }}" class="text-decoration-none">
                            {{ $order->customer->name }}
                        </a>
                    @else
                        <span class="detail-empty-value">Khách hàng đã bị xóa</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Số điện thoại" :value="$order->customer?->phone" />
                <x-admin.detail.info-item label="Nhân viên phụ trách" :value="$order->employee?->HoTen ?: '—'" />
                <x-admin.detail.info-item
                    label="Dịch vụ"
                    :value="$order->chiTietDonHangs->map(fn ($item) => $item->dichVu?->TenDichVu)->filter()->unique()->join(', ') ?: '—'"
                />
                <x-admin.detail.info-item label="Nguồn đơn">
                    @if($order->booking)
                        <a href="{{ route('bookings.show', $order->booking) }}" class="text-decoration-none">
                            <i class="bi bi-calendar-check me-1"></i>Lịch hẹn {{ $order->booking->code }}
                        </a>
                    @else
                        <span class="detail-empty-value">Nhập trực tiếp</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item
                    label="Tình trạng hóa đơn"
                    :value="$order->hoaDons->first()?->TrangThai ?: 'Chưa lập hóa đơn'"
                />
            </x-admin.detail.info-grid>

            @if($order->notes)
                <div class="mt-4">
                    <div class="detail-field__label mb-2">Ghi chú</div>
                    <div class="detail-text">{{ $order->notes }}</div>
                </div>
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Chi tiết mặt hàng" icon="bi-list-check" :iconClass="'bg-info-subtle text-info'" flush>
            <x-slot:header>
                <span class="text-muted small">{{ $order->chiTietDonHangs->count() }} mục</span>
            </x-slot:header>

            @if($isReceiving && auth()->user()?->can('orders.edit'))
                <form action="{{ route('orders.complete-receiving', $order) }}" method="POST" id="receivingInspectionForm">
                    @csrf
                    <div class="alert alert-warning mx-3 mt-3 mb-0" role="status">
                        <strong>Đơn hàng đang chờ kiểm tra.</strong>
                        Kiểm tra số lượng thực tế và ghi tình trạng từng mặt hàng trước khi cho phép bắt đầu giặt.
                        Tình trạng được lưu trong cột ghi chú hiện có của từng dòng (không thay đổi schema).
                    </div>
                    @error('items')<div class="alert alert-danger mx-3 mt-3 mb-0">{{ $message }}</div>@enderror
                    <div class="table-responsive">
                        <table class="table table-hover detail-table align-middle mb-0" id="receivingItemsTable">
                            <thead>
                                <tr>
                                    <th>Dịch vụ</th>
                                    <th>Loại đồ giặt</th>
                                    <th>Đơn vị</th>
                                    <th class="text-end">Số lượng</th>
                                    <th class="text-end">Khối lượng (kg)</th>
                                    <th class="text-end">Đơn giá hiện hành</th>
                                    <th>Tình trạng trước khi giặt</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($inspectionItems as $index => $inspectionItem)
                                    @php
                                        $selectedUnitId = $inspectionItem['DonViTinhID'] ?? '';
                                        $selectedUnit = $units->firstWhere('DonViTinhID', $selectedUnitId);
                                        $isWeightUnit = $selectedUnit?->isWeightUnit() ?? false;
                                    @endphp
                                    <tr data-inspection-row>
                                        <td>
                                            <input type="hidden" name="items[{{ $index }}][ChiTietDonHangID]" value="{{ $inspectionItem['ChiTietDonHangID'] ?? '' }}">
                                            <select class="form-select form-select-sm" name="items[{{ $index }}][DichVuID]" required>
                                                <option value="">Chọn dịch vụ</option>
                                                @foreach($services as $service)
                                                    <option value="{{ $service->DichVuID }}" @selected(($inspectionItem['DichVuID'] ?? '') == $service->DichVuID)>{{ $service->TenDichVu }}</option>
                                                @endforeach
                                            </select>
                                            @error("items.$index.DichVuID")<div class="text-danger small">{{ $message }}</div>@enderror
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" name="items[{{ $index }}][LoaiDoGiatID]" required>
                                                <option value="">Chọn loại đồ</option>
                                                @foreach($garments as $garment)
                                                    <option value="{{ $garment->LoaiDoGiatID }}" @selected(($inspectionItem['LoaiDoGiatID'] ?? '') == $garment->LoaiDoGiatID)>{{ $garment->TenLoaiDoGiat }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" name="items[{{ $index }}][DonViTinhID]" data-inspection-unit required>
                                                <option value="">Chọn đơn vị</option>
                                                @foreach($units as $unit)
                                                    <option value="{{ $unit->DonViTinhID }}" data-weight-unit="{{ $unit->isWeightUnit() ? 'true' : 'false' }}" @selected($selectedUnitId == $unit->DonViTinhID)>{{ $unit->KyHieu ?: $unit->TenDonViTinh }}</option>
                                                @endforeach
                                            </select>
                                            @error("items.$index.DonViTinhID")<div class="text-danger small">{{ $message }}</div>@enderror
                                        </td>
                                        <td class="text-end">
                                            <input class="form-control form-control-sm text-end" type="number" min="1" step="1" name="items[{{ $index }}][SoLuong]" value="{{ $isWeightUnit ? '' : ($inspectionItem['SoLuong'] ?? '') }}" data-inspection-quantity @disabled($isWeightUnit) @required(! $isWeightUnit)>
                                            @error("items.$index.SoLuong")<div class="text-danger small">{{ $message }}</div>@enderror
                                        </td>
                                        <td class="text-end">
                                            <input class="form-control form-control-sm text-end" type="number" min="0.01" step="0.01" name="items[{{ $index }}][KhoiLuong]" value="{{ $isWeightUnit ? ($inspectionItem['KhoiLuong'] ?? '') : '' }}" data-inspection-weight @disabled(! $isWeightUnit) @required($isWeightUnit)>
                                            @error("items.$index.KhoiLuong")<div class="text-danger small">{{ $message }}</div>@enderror
                                        </td>
                                        <td class="text-end text-nowrap">
                                            {{ $order->chiTietDonHangs->firstWhere('ChiTietDonHangID', $inspectionItem['ChiTietDonHangID'] ?? null)?->DonGia !== null
                                                ? number_format((float) $order->chiTietDonHangs->firstWhere('ChiTietDonHangID', $inspectionItem['ChiTietDonHangID'] ?? null)->DonGia, 0, ',', '.').' đ'
                                                : 'Tính theo bảng giá' }}
                                        </td>
                                        <td>
                                            <textarea class="form-control form-control-sm" rows="2" name="items[{{ $index }}][GhiChu]" maxlength="500" placeholder="VD: rách, ố màu, bung chỉ..." required>{{ $inspectionItem['GhiChu'] ?? '' }}</textarea>
                                            @error("items.$index.GhiChu")<div class="text-danger small">{{ $message }}</div>@enderror
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-inspection-row aria-label="Xóa mặt hàng"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between gap-2 p-3 border-top">
                        <button type="button" class="btn btn-outline-primary" id="addInspectionItem">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Thêm mặt hàng thực tế
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Kiểm tra &amp; Hoàn tất tiếp nhận
                        </button>
                    </div>
                </form>
                <template id="receivingItemTemplate">
                    <tr data-inspection-row>
                        <td><input type="hidden" name="items[__INDEX__][ChiTietDonHangID]" value=""><select class="form-select form-select-sm" name="items[__INDEX__][DichVuID]" required><option value="">Chọn dịch vụ</option>@foreach($services as $service)<option value="{{ $service->DichVuID }}">{{ $service->TenDichVu }}</option>@endforeach</select></td>
                        <td><select class="form-select form-select-sm" name="items[__INDEX__][LoaiDoGiatID]" required><option value="">Chọn loại đồ</option>@foreach($garments as $garment)<option value="{{ $garment->LoaiDoGiatID }}">{{ $garment->TenLoaiDoGiat }}</option>@endforeach</select></td>
                        <td><select class="form-select form-select-sm" name="items[__INDEX__][DonViTinhID]" data-inspection-unit required><option value="">Chọn đơn vị</option>@foreach($units as $unit)<option value="{{ $unit->DonViTinhID }}" data-weight-unit="{{ $unit->isWeightUnit() ? 'true' : 'false' }}">{{ $unit->KyHieu ?: $unit->TenDonViTinh }}</option>@endforeach</select></td>
                        <td class="text-end"><input class="form-control form-control-sm text-end" type="number" min="1" step="1" name="items[__INDEX__][SoLuong]" data-inspection-quantity required></td>
                        <td class="text-end"><input class="form-control form-control-sm text-end" type="number" min="0.01" step="0.01" name="items[__INDEX__][KhoiLuong]" data-inspection-weight disabled></td>
                        <td class="text-end text-nowrap">Tính theo bảng giá</td>
                        <td><textarea class="form-control form-control-sm" rows="2" name="items[__INDEX__][GhiChu]" maxlength="500" placeholder="VD: rách, ố màu, bung chỉ..." required></textarea></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-inspection-row aria-label="Xóa mặt hàng"><i class="bi bi-trash" aria-hidden="true"></i></button></td>
                    </tr>
                </template>
            @else
                @if($isReceiving)
                    <div class="alert alert-warning mx-3 mt-3 mb-0" role="status">
                        Đơn hàng đang chờ kiểm tra và tiếp nhận thực tế.
                    </div>
                @endif
                @if($order->chiTietDonHangs->isEmpty())
                <x-admin.detail.empty message="Đơn hàng chưa có mặt hàng nào" icon="bi-bag" />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover detail-table">
                            <thead>
                                <tr>
                                    <th>Dịch vụ</th>
                                    <th>Loại đồ giặt</th>
                                    <th class="text-end">Đơn giá</th>
                                    <th class="text-end">Số lượng / Khối lượng</th>
                                    <th class="text-end">Thành tiền</th>
                                    <th>Tình trạng trước khi giặt</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->chiTietDonHangs as $item)
                                    <tr>
                                        <td class="fw-semibold">{{ $item->dichVu?->TenDichVu ?: '—' }}</td>
                                        <td>{{ $item->loaiDoGiat?->TenLoaiDoGiat ?: '—' }}</td>
                                        <td class="text-end"><x-admin.detail.money :value="$item->DonGia" /></td>
                                        <td class="text-end">
                                            {{ format_quantity_weight($item->SoLuong, $item->KhoiLuong) ?: '—' }}
                                        </td>
                                        <td class="text-end fw-semibold"><x-admin.detail.money :value="$item->ThanhTien" /></td>
                                        <td>{{ $item->GhiChu ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Lịch sử trạng thái" icon="bi-clock-history" :iconClass="'bg-secondary-subtle text-secondary'">
            <div class="detail-timeline order-detail-timeline">
                @foreach($statusFlow as $key => $label)
                    @php
                        $isCurrentStatus = $key === $order->status;
                        $isCompletedStatus = ! $isCancelled
                            && $currentStatusIndex !== false
                            && ($loop->index < $currentStatusIndex || ($isCurrentStatus && $currentStatusIsCompleted));
                        $timelineState = $isCompletedStatus
                            ? 'done'
                            : ($isCurrentStatus ? 'current' : 'muted');
                    @endphp
                    <div class="detail-timeline__item detail-timeline__item--{{ $timelineState }}">
                        <span class="detail-timeline__dot">
                            @if($isCompletedStatus)<i class="bi bi-check-lg" aria-hidden="true"></i>@endif
                        </span>
                        <span>{{ $label }}</span>
                        @if($isCurrentStatus)
                            <span class="badge {{ $isCompletedStatus ? 'bg-success-subtle text-success-emphasis border border-success' : 'bg-primary-subtle text-primary-emphasis border border-primary' }} px-2 py-1 rounded-pill ms-auto">
                                {{ $isCompletedStatus ? 'Đã hoàn tất' : 'Hiện tại' }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4 d-flex flex-column">
        <x-admin.detail.panel title="Tổng thanh toán" icon="bi-calculator" :iconClass="'bg-success-subtle text-success'">
            <div class="d-flex justify-content-between align-items-center py-2">
                <span class="detail-summary__label">Tạm tính</span>
                <x-admin.detail.money :value="$order->subtotal" class="fw-semibold" />
            </div>
            @if($hasPromotionDiscount)
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="detail-summary__label">Tiền giảm voucher</span>
                    <x-admin.detail.money :value="$order->discount_by_promotion" :negative="true" class="text-success fw-semibold" />
                </div>
            @endif
            @if($hasPointsDiscount)
                <div class="d-flex justify-content-between align-items-start py-2">
                    <span class="detail-summary__label">
                        Tiền giảm do điểm
                        @if($order->points_used > 0)
                            <small class="d-block">({{ number_format($order->points_used) }} điểm)</small>
                        @endif
                    </span>
                    <x-admin.detail.money :value="$order->discount_by_points" :negative="true" class="text-success fw-semibold" />
                </div>
            @endif

            <div class="detail-summary d-flex justify-content-between align-items-center">
                <span class="detail-summary__total">Tổng thanh toán</span>
                <x-admin.detail.money :value="$order->total_amount" class="detail-summary__total text-primary" />
            </div>

            @if($order->promotion)
                <div class="d-flex justify-content-end">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        <i class="fas fa-ticket me-1"></i>{{ $order->promotion->name }} ({{ $order->promotion->code }})
                    </span>
                </div>
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Thông tin giao nhận" icon="bi-truck" :iconClass="'bg-warning-subtle text-warning-emphasis'">
            @if($order->giaoNhans->isNotEmpty())
                @foreach($order->giaoNhans as $delivery)
                    <div class="{{ $loop->first ? '' : 'border-top pt-3 mt-3' }}">
                        <x-admin.detail.info-grid :columns="1">
                            <x-admin.detail.info-item label="Loại giao nhận">
                                <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                                    <i class="fas {{ $delivery->LoaiGiaoNhan === 'NHAN_DO' ? 'fa-box-open' : 'fa-truck' }} me-1"></i>
                                    {{ $delivery->LoaiGiaoNhan === 'NHAN_DO' ? 'Nhận đồ' : 'Giao đồ' }}
                                </span>
                            </x-admin.detail.info-item>
                            <x-admin.detail.info-item label="Hình thức" :value="$delivery->HinhThuc" />
                            <x-admin.detail.info-item label="Trạng thái">
                                <x-admin.status-badge :status="$delivery->TrangThai" :enum="\App\Enums\DeliveryStatus::class" :pill="false" />
                            </x-admin.detail.info-item>
                            <x-admin.detail.info-item label="Thời gian dự kiến" :value="$delivery->ThoiGianDuKien?->format('d/m/Y H:i')" />
                            <x-admin.detail.info-item label="Địa chỉ" :value="$delivery->DiaChi ?: '—'" />
                        </x-admin.detail.info-grid>

                        @if($delivery->GhiChu)
                            <div class="mt-3">
                                <div class="detail-field__label mb-2">Ghi chú giao nhận</div>
                                <div class="detail-text">{{ $delivery->GhiChu }}</div>
                            </div>
                        @endif

                        <div class="mt-3">
                            <a href="{{ route('deliveries.show', $delivery->GiaoNhanID) }}" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Chi tiết phiếu giao nhận
                            </a>
                        </div>
                    </div>
                @endforeach
            @else
                <x-admin.detail.empty message="Đơn hàng chưa có lịch giao nhận" icon="bi-truck" />
            @endif
        </x-admin.detail.panel>

        <div class="card shadow-sm border-0 order-first">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @if(! $isPaid && $order->TrangThai !== \App\Enums\OrderStatus::Cancelled->value)
                    @can('payments.create')
                        <a href="{{ route('payments.create', ['order_id' => $order->getKey()]) }}"
                           class="btn btn-success w-100 py-2"
                           data-payment-link>
                            <i class="bi bi-credit-card me-1" aria-hidden="true"></i> Thanh toán
                        </a>
                    @endcan
                @endif

                @if($canEdit && ! $isReceiving)
                    @can('orders.edit')
                        <a href="{{ route('orders.edit', $order->getKey()) }}" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-pencil-alt me-1" aria-hidden="true"></i> Chỉnh sửa
                        </a>
                    @endcan
                @endif

                @if(auth()->user()?->hasRole(['owner', 'manager', 'staff']))
                    <a href="{{ route('admin.messages.index', ['order_id' => $order->DonHangID]) }}" class="btn btn-outline-primary w-100 py-2">
                        <i class="bi bi-chat-dots me-1" aria-hidden="true"></i> Nhắn tin khách hàng
                    </a>
                @endif

                @if($order->hoaDons->isNotEmpty())
                    <a href="{{ route('invoices.show', $order->hoaDons->first()->HoaDonID) }}" class="btn btn-outline-primary w-100 py-2">
                        <i class="fas fa-file-invoice me-1" aria-hidden="true"></i> Xem hóa đơn
                    </a>
                @elseif(in_array($order->TrangThai, ['Hoàn thành giặt', 'Đang giao', 'Đã giao'], true))
                    @can('invoices.create')
                        <a href="{{ route('invoices.create', ['order_id' => $order->getKey()]) }}" class="btn btn-outline-primary w-100 py-2">
                            <i class="fas fa-file-invoice me-1" aria-hidden="true"></i> Tạo hóa đơn
                        </a>
                    @endcan
                @endif

                @can('orders.delete')
                    <x-admin.detail.confirm-form
                        :action="route('orders.destroy', $order->getKey())"
                        title="Xóa đơn hàng?"
                        text="Đơn hàng đã quyết toán chỉ có thể xóa theo quyền chủ cửa hàng."
                        label="Xóa đơn hàng"
                        icon="bi-trash"
                        variant="btn-outline-danger"
                        size="py-2"
                        block
                    />
                @endcan

                <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Quay lại danh sách
                </a>
            </div>
        </div>

    </div>

</div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inspectionTable = document.querySelector('#receivingItemsTable tbody');
        const inspectionTemplate = document.getElementById('receivingItemTemplate');
        const addInspectionItem = document.getElementById('addInspectionItem');
        let nextInspectionIndex = inspectionTable ? inspectionTable.querySelectorAll('[data-inspection-row]').length : 0;

        function syncInspectionRow(row) {
            const unit = row.querySelector('[data-inspection-unit]');
            const quantity = row.querySelector('[data-inspection-quantity]');
            const weight = row.querySelector('[data-inspection-weight]');
            if (!unit || !quantity || !weight) {
                return;
            }

            const isWeightUnit = unit.selectedOptions[0]?.dataset.weightUnit === 'true';
            quantity.disabled = isWeightUnit;
            quantity.required = !isWeightUnit;
            if (isWeightUnit) {
                quantity.value = '';
            }
            weight.disabled = !isWeightUnit;
            weight.required = isWeightUnit;
            if (!isWeightUnit) {
                weight.value = '';
            }
        }

        if (inspectionTable) {
            inspectionTable.querySelectorAll('[data-inspection-row]').forEach(function(row) {
                const unit = row.querySelector('[data-inspection-unit]');
                unit?.addEventListener('change', function() {
                    syncInspectionRow(row);
                });
                row.querySelector('[data-remove-inspection-row]')?.addEventListener('click', function() {
                    row.remove();
                });
            });
        }

        addInspectionItem?.addEventListener('click', function() {
            const html = inspectionTemplate.innerHTML.replaceAll('__INDEX__', String(nextInspectionIndex++));
            const template = document.createElement('template');
            template.innerHTML = html.trim();
            const row = template.content.firstElementChild;
            inspectionTable.appendChild(row);
            row.querySelector('[data-inspection-unit]').addEventListener('change', function() {
                syncInspectionRow(row);
            });
            row.querySelector('[data-remove-inspection-row]').addEventListener('click', function() {
                row.remove();
            });
        });

        document.querySelectorAll('[data-payment-link]').forEach(function(link) {
            link.addEventListener('click', function() {
                if (link.dataset.loading === 'true') {
                    return;
                }

                link.dataset.loading = 'true';
                link.setAttribute('aria-disabled', 'true');
                link.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Đang chuyển...';
            });
        });
    });
</script>
@endpush
