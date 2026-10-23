@extends('layouts.app')

@section('title', 'Chi tiết tài khoản - Sky Laundry')
@section('page-title', 'Chi tiết tài khoản')

@section('content')
@php
    $roleLabel = $account->vaiTros->pluck('TenVaiTro')->join(', ') ?: 'Chưa gán vai trò';
    $roleClass = $account->isOwner()
        ? 'bg-danger-subtle text-danger-emphasis border border-danger'
        : 'bg-primary-subtle text-primary-emphasis border border-primary';
    $roleIcon = $account->isOwner() ? 'fa-shield-alt' : 'fa-user-tie';
@endphp

<x-admin.detail.page-header
    title="Tài khoản {{ $account->name }}"
    :subtitle="$account->email"
    backUrl="{{ route('accounts.index') }}"
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
    <div class="col-12">
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
                    />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
