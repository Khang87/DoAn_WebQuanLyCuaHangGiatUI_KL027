<?php

namespace App\Services;

use App\Models\NhanVien;
use App\Models\User;
use App\Models\VaiTro;
use App\Support\QuyenMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationException;

class EmployeeAssignment
{
    /** Lấy nhân viên có tài khoản nội bộ đang hoạt động được cấp quyền sửa giao nhận. */
    public static function deliveryCandidates(): Builder
    {
        $deliveryPermissions = array_values(array_unique(array_filter([
            QuyenMapper::resolveMaQuyen('deliveries.edit'),
            QuyenMapper::FULL_ACCESS,
        ])));
        $roleNameColumn = DB::connection()->getQueryGrammar()->wrap('TenVaiTro');

        return NhanVien::query()
            ->where('TrangThai', 'Hoạt động')
            ->whereHas('taiKhoans', fn ($accounts) => $accounts
                ->where('TrangThai', 'Hoạt động')
                ->whereHas('vaiTros', fn ($roles) => $roles
                    ->where('TrangThai', 'Hoạt động')
                    ->whereRaw("LOWER(TRIM({$roleNameColumn})) NOT IN (?, ?)", ['khách hàng', 'customer'])
                    ->where(fn ($eligibleRoles) => $eligibleRoles
                        // User::canPermission grants the owner role full access implicitly.
                        ->whereRaw("LOWER(TRIM({$roleNameColumn})) = ?", [mb_strtolower(VaiTro::OWNER)])
                        ->orWhereHas('quyens', fn ($permissions) => $permissions
                            ->where('TrangThai', 'Hoạt động')
                            ->whereIn('MaQuyen', $deliveryPermissions)))))
            ->orderBy('HoTen')->orderBy('NhanVienID');
    }

    public static function canAssignBookingIntake(?User $user): bool
    {
        return $user !== null && $user->canPermission('deliveries.edit');
    }

    /** A management title is not required when the account has the RBAC permission. */
    public static function canManageAssignments(?User $user): bool
    {
        return $user === null
            || $user->isManager()
            || $user->canPermission('deliveries.edit');
    }

    public static function bookingIntakeCandidates(): Builder
    {
        return self::deliveryCandidates();
    }

    public static function bookingIntakeRule(): In
    {
        $employeeIds = self::bookingIntakeCandidates()
            ->pluck('NhanVienID')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return Rule::in($employeeIds);
    }

    public static function assertBookingIntakeAssignable(
        ?User $user,
        ?int $employeeId,
        string $field,
    ): void {
        if (! self::canAssignBookingIntake($user)) {
            throw ValidationException::withMessages([
                $field => 'Bạn không có quyền thay đổi nhân viên phụ trách phiếu nhận đồ.',
            ]);
        }

        if (
            $employeeId === null
            || ! self::bookingIntakeCandidates()->whereKey($employeeId)->exists()
        ) {
            throw ValidationException::withMessages([
                $field => 'Nhân viên được chọn không thuộc phạm vi phân công hợp lệ.',
            ]);
        }
    }

    /** Existing attribution may be retained; only new assignments require active staff. */
    public static function rule(?int $currentId = null): Exists
    {
        return Rule::exists('NhanVien', 'NhanVienID')->where(fn ($query) => $query
            ->where(fn ($employee) => $employee->where('TrangThai', 'Hoạt động')
                ->when($currentId !== null, fn ($q) => $q->orWhere('NhanVienID', $currentId))));
    }

    public static function assertAssignable(?int $employeeId, string $field, ?int $currentId = null): void
    {
        if ($employeeId !== null && $employeeId === $currentId) {
            return;
        }

        if (! $employeeId || ! NhanVien::query()->whereKey($employeeId)->where('TrangThai', 'Hoạt động')->exists()) {
            throw ValidationException::withMessages([$field => 'Vui lòng chọn nhân viên đang hoạt động.']);
        }
    }
}
