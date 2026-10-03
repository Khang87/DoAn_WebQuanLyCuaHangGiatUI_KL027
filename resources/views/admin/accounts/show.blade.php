@extends('layouts.app')

@section('title', 'Chi tiết tài khoản - Sky Laundry')
@section('page-title', 'Chi tiết tài khoản')

@section('content')
@php
    $roleLabel = $account->isManager() ? 'Quản lý' : ($account->role === 'staff' ? 'Nhân viên' : 'Khách hàng');
    $roleClass = $account->isManager()
        ? 'bg-danger-subtle text-danger-emphasis border border-danger'
        : ($account->role === 'staff' ? 'bg-primary-subtle text-primary-emphasis border border-primary' : 'bg-success-subtle text-success-emphasis border border-success');
    $roleIcon = $account->isManager() ? 'fa-shield-alt' : ($account->role === 'staff' ? 'fa-user-tie' : 'fa-user');
@endphp

<x-admin.detail.page-header
    title="Tài khoản {{ $account->name }}"
    :subtitle="$account->email"
>
    <x-slot:badge>
        <span class="badge {{ $roleClass }} px-3 py-2 rounded-pill">
            <i class="fas {{ $roleIcon }} me-1"></i>{{ $roleLabel }}
        </span>
        <x-admin.status-badge
            :status="$account->deleted_at ? 'inactive' : 'active'"
            :enum="\App\Enums\RecordStatus::class"
        />
    </x-slot:badge>
</x-admin.detail.page-header>

@if($account->deleted_at)
    <x-admin.detail.locked text="Tài khoản đã bị xóa mềm nên không thể sửa hoặc xóa. Hãy kích hoạt lại nếu cần." />
@endif

<div class="row g-4 align-items-start">
    <div class="col-lg-7">
        <x-admin.detail.panel title="Thông tin tài khoản" icon="bi-person-badge" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Họ và tên" :value="$account->name" />
                <x-admin.detail.info-item label="Email" :value="$account->email" />
                <x-admin.detail.info-item label="Số điện thoại" :value="$account->phone ?: 'Chưa cập nhật'" />
                <x-admin.detail.info-item label="Vai trò">
                    <span class="badge {{ $roleClass }} px-3 py-2 rounded-pill">
                        <i class="fas {{ $roleIcon }} me-1"></i>{{ $roleLabel }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Ngày tạo" :value="$account->created_at?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge
                        :status="$account->deleted_at ? 'inactive' : 'active'"
                        :enum="\App\Enums\RecordStatus::class"
                        :pill="false"
                    />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Hoạt động" icon="bi-activity" :iconClass="'bg-secondary-subtle text-secondary'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Đơn hàng đã xử lý" :value="number_format($account->orders?->count() ?? 0)" />
                <x-admin.detail.info-item label="Phiếu giao nhận" :value="number_format($account->deliveries?->count() ?? 0)" />
                <x-admin.detail.info-item label="Cập nhật lần cuối" :value="$account->updated_at?->format('d/m/Y H:i')" />
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>

    <div class="col-lg-5 d-flex flex-column">
        <div class="card shadow-sm border-0 sticky-top order-first" style="top: 20px;">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @can('accounts.edit')
                    <a href="{{ route('accounts.edit', $account->getKey()) }}" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-pencil-alt me-1"></i> Chỉnh sửa
                    </a>
                @endcan

@unless($account->isManager())
            <x-admin.detail.confirm-form
                :action="route('accounts.reset-password', $account->getKey())"
                method="POST"
                title="Đặt lại mật khẩu?"
                text="Mật khẩu sẽ được đặt lại về mặc định."
                label="Đặt lại mật khẩu"
                icon="bi-key"
                variant="btn-outline-primary"
                color="#f59e0b"
                size="py-2"
                block
                :iconName="'question'"
            />
        @endunless

        @if($account->getKey() !== auth()->id())
            <x-admin.detail.confirm-form
                :action="route('accounts.toggle-status', $account->getKey())"
                method="POST"
                title="{{ $account->deleted_at ? 'Kích hoạt' : 'Tạm ngưng' }} tài khoản này?"
                text="Tài khoản sẽ {{ $account->deleted_at ? 'được kích hoạt trở lại' : 'bị tạm ngưng' }}."
                label="{{ $account->deleted_at ? 'Kích hoạt' : 'Tạm ngưng' }}"
                :icon="$account->deleted_at ? 'bi-unlock' : 'bi-lock'"
                variant="btn-outline-primary"
                color="#2563eb"
                size="py-2"
                block
                :iconName="'question'"
            />
        @endif

        @can('accounts.delete')
            <x-admin.detail.confirm-form
                :action="route('accounts.destroy', $account->getKey())"
                title="Xóa tài khoản?"
                text="Hành động này không thể hoàn tác."
                label="Xóa tài khoản"
                icon="bi-trash"
                variant="btn-outline-danger"
                size="py-2"
                block
            />
        @endcan

                <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
