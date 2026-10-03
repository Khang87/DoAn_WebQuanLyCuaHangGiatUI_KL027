@extends('layouts.app')

@section('title', 'Chi tiết khách hàng - Sky Laundry')
@section('page-title', 'Chi tiết khách hàng')

@section('content')
<x-admin.detail.page-header
    title="Khách hàng {{ $customer->HoTen }}"
    :subtitle="'ID ' . $customer->KhachHangID"
>
    <x-slot:badge>
        <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary">{{ $customer->TrangThai }}</span>
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin khách hàng" icon="bi-person" :iconClass="'bg-primary-subtle text-primary'">
            <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 70px; height: 70px;">
                    {{ mb_substr($customer->HoTen, 0, 1) }}
                </div>
                <h4 class="mb-1 text-dark fw-bold">{{ $customer->HoTen }}</h4>
            </div>
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="ID khách hàng" :value="$customer->KhachHangID" />
                <x-admin.detail.info-item label="Họ và tên" :value="$customer->HoTen" />
                <x-admin.detail.info-item label="Email" :value="$customer->Email ?: '—'" />
                <x-admin.detail.info-item label="Số điện thoại" :value="$customer->SoDienThoai" />
                <x-admin.detail.info-item label="Địa chỉ" :value="$customer->DiaChi ?: '—'" />
                <x-admin.detail.info-item label="Ngày đăng ký" :value="$customer->NgayTao?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Trạng thái" :value="$customer->TrangThai" />
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Lịch sử đơn hàng" icon="bi-clock-history" :iconClass="'bg-secondary-subtle text-secondary'" flush>
            <x-slot:header>
                <span class="text-muted small">{{ $orderCount }} đơn</span>
            </x-slot:header>

            @if($orders->isEmpty())
                <x-admin.detail.empty message="Khách hàng chưa có đơn hàng nào" icon="bi-bag" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover detail-table">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Dịch vụ</th>
                                <th class="text-end">Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th class="text-end">Ngày tạo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                                <tr>
                                    <td>
                                        <span class="fw-semibold">{{ $order->MaDonHang }}</span>
                                    </td>
                                    <td>{{ $order->chiTietDonHangs->map(fn ($item) => $item->dichVu?->TenDichVu)->filter()->unique()->join(', ') ?: '—' }}</td>
                                    <td class="text-end"><x-admin.detail.money :value="$order->ThanhTien" /></td>
                                    <td>
                                        <x-admin.status-badge :status="$order->TrangThai" :enum="\App\Enums\OrderStatus::class" size="px-2 py-1" />
                                    </td>
                                    <td class="text-end">{{ $order->NgayTao?->format('d/m/Y') ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($orders->hasPages())
                    <div class="p-3">{{ $orders->links() }}</div>
                @endif
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4 d-flex flex-column">
        <div class="card shadow-sm border-0 order-first">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @can('customers.edit')
                    <a href="{{ route('customers.edit', $customer->getKey()) }}" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-pencil-alt me-1"></i> Chỉnh sửa
                    </a>
                @endcan

                @can('orders.create')
                    <a href="{{ route('orders.create', ['customer_id' => $customer->getKey()]) }}" class="btn btn-outline-primary w-100 py-2">
                        <i class="fas fa-plus me-1"></i> Tạo đơn hàng mới
                    </a>
                @endcan

                @can('customers.delete')
                    <x-admin.detail.confirm-form
                        :action="route('customers.destroy', $customer->getKey())"
                        title="Xóa khách hàng?"
                        text="Khách hàng có dữ liệu liên quan sẽ không thể xóa."
                        label="Xóa khách hàng"
                        icon="bi-trash"
                        variant="btn-outline-danger"
                        size="py-2"
                        block
                    />
                @endcan

                <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                </a>
            </div>
        </div>

        <x-admin.detail.panel title="Tổng quan tài chính" icon="bi-wallet2" :iconClass="'bg-success-subtle text-success'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Tổng chi tiêu">
                    <x-admin.detail.money :value="$totalSpent" class="detail-summary__total text-primary" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Tổng số đơn hàng" :value="number_format($orderCount)" />
                <x-admin.detail.info-item label="Điểm tích lũy">
                    <span class="badge rounded-pill px-3 py-2 fs-6 fw-bold d-inline-flex align-items-center gap-1"
                          style="background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a;">
                        <i class="bi bi-star-fill me-1"></i>{{ number_format($customer->points ?? 0) }} điểm
                    </span>
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
