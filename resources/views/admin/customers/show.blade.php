@extends('layouts.app')

@section('title', 'Chi tiết khách hàng - Sky Laundry')
@section('page-title', 'Chi tiết khách hàng')

@section('content')
<x-admin.detail.page-header
    title="Khách hàng {{ $customer->name }}"
    :back="route('customers.index')"
    :subtitle="$customer->code"
>
    <x-slot:badge>
        <x-admin.status-badge
            :status="$customer->deleted_at ? 'inactive' : 'active'"
            :enum="\App\Enums\RecordStatus::class"
        />
    </x-slot:badge>

    <x-slot:actions>
        <a href="{{ route('customers.edit', $customer->id) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>
    </x-slot:actions>
</x-admin.detail.page-header>

@if($customer->deleted_at)
    <x-admin.detail.locked text="Khách hàng đã bị xóa mềm nên không thể sửa hoặc xóa. Hãy khôi phục nếu cần." />
@endif

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin khách hàng" icon="bi-person" :iconClass="'bg-primary-subtle text-primary'">
            <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                <img src="{{ $customer->avatar_url }}" alt="{{ $customer->name }}"
                    class="rounded-circle object-fit-cover border border-2 border-light-subtle shadow-sm"
                    width="70" height="70">
                <div>
                    <h4 class="mb-1 text-dark fw-bold">{{ $customer->name }}</h4>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary">{{ $customer->code }}</span>
                </div>
            </div>
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã khách hàng" :value="$customer->code" />
                <x-admin.detail.info-item label="Họ và tên" :value="$customer->name" />
                <x-admin.detail.info-item label="Email" :value="$customer->email" />
                <x-admin.detail.info-item label="Số điện thoại" :value="$customer->phone" />
                <x-admin.detail.info-item label="Địa chỉ" :value="$customer->address" />
                <x-admin.detail.info-item label="Ngày đăng ký" :value="$customer->created_at?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Trạng thái">
                    @if($customer->deleted_at)
                        <span class="badge bg-danger-subtle text-danger-emphasis border border-danger px-3 py-2 rounded-pill">
                            <i class="fas fa-trash me-1"></i>Đã xóa
                        </span>
                    @else
                        <span class="badge bg-success-subtle text-success-emphasis border border-success px-3 py-2 rounded-pill">
                            <i class="fas fa-check-circle me-1"></i>Hoạt động
                        </span>
                    @endif
                </x-admin.detail.info-item>
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
                                        <span class="fw-semibold">
                                            {{ $order->code }}
                                        </span>
                                    </td>
                                    <td>{{ $order->service?->name ?: '—' }}</td>
                                    <td class="text-end"><x-admin.detail.money :value="$order->total_amount" /></td>
                                    <td>
                                        <x-admin.status-badge :status="$order->status" :enum="\App\Enums\OrderStatus::class" size="px-2 py-1" />
                                    </td>
                                    <td class="text-end">{{ $order->created_at?->format('d/m/Y') }}</td>
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
    <div class="col-lg-4">
        <x-admin.detail.panel title="Tổng quan tài chính" icon="bi-wallet2" :iconClass="'bg-success-subtle text-success'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Tổng chi tiêu">
                    <x-admin.detail.money :value="$totalSpent" class="detail-summary__total text-primary" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Tổng số đơn hàng" :value="number_format($orderCount)" />
                <x-admin.detail.info-item label="Điểm tích lũy">
                    <span class="badge bg-amber-subtle text-amber-emphasis border border-amber px-3 py-2 rounded-pill">
                        <i class="bi bi-star-fill me-1"></i>{{ number_format($customer->points) }} điểm
                    </span>
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
