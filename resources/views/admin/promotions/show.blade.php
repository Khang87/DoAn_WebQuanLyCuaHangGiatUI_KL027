@extends('layouts.app')

@section('title', 'Chi tiết khuyến mãi - Sky Laundry')
@section('page-title', 'Chi tiết khuyến mãi')

@section('content')
<x-admin.detail.page-header
    :title="'Khuyến mãi ' . $promotion->MaKhuyenMai"
    :subtitle="$promotion->TenKhuyenMai"
>
    <x-slot:badge>
        <span class="badge {{ $promotion->status_badge_class }} px-3 py-2 rounded-pill">
            {{ $promotion->status_label }}
        </span>
        <span class="badge {{ $promotion->isValid() ? 'bg-success-subtle text-success-emphasis border border-success' : 'bg-secondary-subtle text-secondary-emphasis border border-secondary' }} px-3 py-2 rounded-pill">
            <i class="bi {{ $promotion->isValid() ? 'bi-check-circle' : 'bi-x-circle' }} me-1"></i>
            {{ $promotion->isValid() ? 'Đang áp dụng' : 'Không áp dụng' }}
        </span>
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin khuyến mãi" icon="bi-megaphone" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Tên khuyến mãi" :value="$promotion->TenKhuyenMai" />
                <x-admin.detail.info-item label="Mã khuyến mãi">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        {{ $promotion->MaKhuyenMai }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Loại giảm">
                    <span class="badge {{ $promotion->discountTypeBadgeClass() }} px-3 py-2 rounded-pill">
                        {{ $promotion->discountTypeLabel() }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Giá trị giảm" :value="$promotion->discountSummary()" />
                <x-admin.detail.info-item label="Đơn hàng tối thiểu">
                    @if($promotion->GiaTriDonToiThieu !== null)
                        <x-admin.detail.money :value="$promotion->GiaTriDonToiThieu" />
                    @else
                        <span class="detail-empty-value">Không yêu cầu</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Mức giảm tối đa">
                    @if($promotion->MucGiamToiDa !== null)
                        <x-admin.detail.money :value="$promotion->MucGiamToiDa" />
                    @else
                        <span class="detail-empty-value">Không giới hạn</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Số lượt sử dụng" :value="$promotion->usageLabel()" />
                <x-admin.detail.info-item label="Ngày bắt đầu" :value="$promotion->NgayBatDau?->format('d/m/Y')" />
                <x-admin.detail.info-item label="Ngày kết thúc" :value="$promotion->NgayKetThuc?->format('d/m/Y')" />
                <x-admin.detail.info-item label="Trạng thái">
                    <span class="badge {{ $promotion->status_badge_class }} px-3 py-2 rounded-pill">
                        {{ $promotion->status_label }}
                    </span>
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Điều kiện áp dụng</div>
                @if($promotion->DieuKienApDung)
                    <div class="detail-text">
                        {{ $promotion->isFirstOrderOnly() ? 'Chỉ áp dụng cho đơn hàng đầu tiên' : $promotion->DieuKienApDung }}
                    </div>
                @else
                    <span class="detail-empty-value">Không có điều kiện bổ sung</span>
                @endif
            </div>
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Đơn hàng áp dụng khuyến mãi" icon="bi-receipt" :iconClass="'bg-info-subtle text-info'" flush>
            @if($orders->isEmpty())
                <x-admin.detail.empty message="Chưa có đơn hàng sử dụng chương trình này" icon="bi-receipt" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover detail-table">
                        <thead>
                            <tr>
                                <th>Mã đơn hàng</th>
                                <th>Khách hàng</th>
                                <th>Trạng thái</th>
                                <th class="text-end">Tiền giảm</th>
                                <th class="text-end">Thành tiền</th>
                                <th>Ngày tạo</th>
                                @can('orders.view')
                                    <th class="text-end">Thao tác</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                                <tr>
                                    <td class="fw-semibold">{{ $order->MaDonHang }}</td>
                                    <td>{{ $order->khachHang?->HoTen ?? '—' }}</td>
                                    <td>
                                        @php($orderStatus = \App\Enums\OrderStatus::parse($order->TrangThai))
                                        <span class="badge {{ $orderStatus->badgeClass() }} px-2 py-1 rounded-pill">
                                            {{ $orderStatus->label() }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <x-admin.detail.money :value="$order->TienGiamKhuyenMai" />
                                    </td>
                                    <td class="text-end">
                                        <x-admin.detail.money :value="$order->ThanhTien" />
                                    </td>
                                    <td>{{ $order->NgayTao?->format('d/m/Y H:i') ?? '—' }}</td>
                                    @can('orders.view')
                                        <td class="text-end">
                                            <a href="{{ route('orders.show', $order->DonHangID) }}" class="btn btn-sm btn-outline-primary" title="Xem đơn hàng">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($orders->hasPages())
                    <div class="p-3">{{ $orders->links('pagination::bootstrap-5') }}</div>
                @endif
            @endif
        </x-admin.detail.panel>
    </div>

    <div class="col-lg-4 d-flex flex-column">
        <x-admin.detail.panel title="Tổng quan sử dụng" icon="bi-bar-chart" :iconClass="'bg-success-subtle text-success'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Số đơn hàng áp dụng" :value="number_format($orderCount)" />
                <x-admin.detail.info-item label="Tổng tiền đã giảm">
                    <x-admin.detail.money :value="$totalDiscount" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Điều kiện đơn tối thiểu">
                    @if($promotion->GiaTriDonToiThieu !== null)
                        <x-admin.detail.money :value="$promotion->GiaTriDonToiThieu" />
                    @else
                        <span class="detail-empty-value">Không yêu cầu</span>
                    @endif
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>

        <div class="card shadow-sm border-0 order-first">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @can('promotions.edit')
                    <a href="{{ route('promotions.edit', $promotion->KhuyenMaiID) }}" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-pencil-alt me-1"></i>Chỉnh sửa
                    </a>
                @endcan

                @can('promotions.delete')
                    <x-admin.detail.confirm-form
                        :action="route('promotions.destroy', $promotion->KhuyenMaiID)"
                        title="Xóa chương trình khuyến mãi?"
                        text="Chương trình đã được dùng sẽ chuyển sang trạng thái tạm ngưng."
                        label="Xóa chương trình khuyến mãi"
                        icon="bi-trash"
                        variant="btn-outline-danger"
                        size="py-2"
                        block
                    />
                @endcan

                <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
