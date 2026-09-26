@extends('layouts.app')

@section('title', 'Chi tiết tài khoản - Sky Laundry')
@section('page-title', 'Chi tiết tài khoản')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Thông tin tài khoản</h4>
    <div class="d-flex gap-2">
        <a href="{{ route('accounts.edit', $account->id) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>
        @if(!$account->isManager())
        <form action="{{ route('accounts.reset-password', $account->id) }}" method="POST" class="d-inline" id="resetPasswordForm">
            @csrf
            <button type="submit" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-key me-1"></i>Đặt lại mật khẩu
            </button>
        </form>
        @endif
        @if($account->id !== auth()->id())
        <form action="{{ route('accounts.toggle-status', $account->id) }}" method="POST" class="d-inline" id="toggleStatusAccountShowForm">
            @csrf
            <button type="submit" class="btn btn-outline-{{ $account->deleted_at ? 'success' : 'danger' }} btn-sm">
                <i class="bi bi-{{ $account->deleted_at ? 'unlock' : 'lock' }} me-1"></i>
                {{ $account->deleted_at ? 'Kích hoạt' : 'Khóa tài khoản' }}
            </button>
        </form>
        @endif
        <form action="{{ route('accounts.destroy', $account->id) }}" method="POST" class="d-inline" id="deleteAccountShowForm">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash"></i> Xóa
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Họ tên</strong></td>
                        <td>{{ $account->name }}</td>
                    </tr>
                    <tr>
                        <td><strong>Email</strong></td>
                        <td>{{ $account->email }}</td>
                    </tr>
                    <tr>
                        <td><strong>Số điện thoại</strong></td>
                        <td>{{ $account->phone ?: 'Chưa cập nhật' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Vai trò</strong></td>
                        <td>
                            @if($account->isManager())
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-shield-alt me-1"></i>Quản lý</span>
                            @elseif($account->role === 'staff')
                                <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-user-tie me-1"></i>Nhân viên</span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-user me-1"></i>Khách hàng</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Ngày tạo</strong></td>
                        <td>{{ $account->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Trạng thái</strong></td>
                        <td>
                            @if($account->deleted_at)
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Khóa</span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Đơn hàng</strong></td>
                        <td>{{ $account->orders?->count() ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td><strong>Giao nhận</strong></td>
                        <td>{{ $account->deliveries?->count() ?? 0 }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @if($account->deleted_at)
        <div class="mt-3">
            <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-trash me-1"></i>Đã xóa</span>
        </div>
        @endif
    </div>
</div>

<a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary mt-3">
    <i class="bi bi-arrow-left me-1"></i>Quay lại
</a>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const resetForm = document.getElementById('resetPasswordForm');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn đặt lại mật khẩu cho tài khoản này?')) {
                        resetForm.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Đặt lại mật khẩu?',
                    text: 'Mật khẩu sẽ được đặt lại về mặc định.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#f59e0b',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Đặt lại',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        resetForm.submit();
                    }
                });
            });
        }

        const toggleForm = document.getElementById('toggleStatusAccountShowForm');
        if (toggleForm) {
            toggleForm.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    toggleForm.submit();
                    return;
                }

                const button = toggleForm.querySelector('button');
                const isActive = button && button.classList.contains('btn-outline-danger');
                const actionText = isActive ? 'khóa' : 'kích hoạt';

                Swal.fire({
                    title: 'Xác nhận ' + actionText + ' tài khoản?',
                    text: 'Tài khoản sẽ được ' + actionText + '.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#2563eb',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: actionText.charAt(0).toUpperCase() + actionText.slice(1),
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        toggleForm.submit();
                    }
                });
            });
        }

        const deleteForm = document.getElementById('deleteAccountShowForm');
        if (deleteForm) {
            deleteForm.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa tài khoản này?')) {
                        deleteForm.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa tài khoản?',
                    text: 'Hành động này không thể hoàn tác.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Xóa',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteForm.submit();
                    }
                });
            });
        }
    });
</script>
@endpush
