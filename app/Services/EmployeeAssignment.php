<?php

namespace App\Services;

use App\Models\NhanVien;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationException;

class EmployeeAssignment
{
    /** Stub employee IDs used by the booking intake combobox while mock staff data is enabled. */
    public const MOCK_EMPLOYEE_IDS = [9001, 9002, 9003, 9004];

    /**
     * Lấy danh sách nhân viên có thể phân công cho giao nhận.
     * Bao gồm: người trong nhóm "Nhân viên" (có quyền deliveries.*), Chủ cửa hàng, Quản lý.
     */
    public static function deliveryCandidates(?int $currentId = null): Builder
    {
        return NhanVien::query()->where(function ($query) use ($currentId): void {
            // Filter active employees who are in "Nhân viên" role (simple approach)
            $query->where('TrangThai', 'Hoạt động')
                  ->whereDoesntHave('taiKhoans.vaiTros', fn ($q) => $q->where('TenVaiTro', 'Chủ cửa hàng'))
                  ->whereDoesntHave('taiKhoans.vaiTros', fn ($q) => $q->where('TenVaiTro', 'like', '%Quản lý%'));
            
            // Include users with deliveries.edit permission OR not in owner/manager roles
            $query->orWhereDoesntHave('taiKhoans.vaiTros'); // Users without any role assignment
            
            if ($currentId !== null) {
                $query->orWhereKey($currentId);
            }
        })->orderBy('HoTen')->orderBy('NhanVienID');
    }

    public static function canAssignBookingIntake(?User $user): bool
    {
        return $user !== null
            && $user->isManager()
            && $user->canPermission('deliveries.edit');
    }

    public static function bookingIntakeCandidates(User $user, ?int $currentId = null): Builder
    {
        $query = self::deliveryCandidates($currentId);
        $selfId = $user->NhanVienID ? (int) $user->NhanVienID : null;

        if ($selfId !== null && NhanVien::query()
            ->whereKey($selfId)
            ->where('TrangThai', 'Hoạt động')
            ->exists()) {
            $query->orWhereKey($selfId);
        }

        return $query;
    }

    public static function bookingIntakeRule(User $user, ?int $currentId = null): In
    {
        $employeeIds = self::bookingIntakeCandidates($user, $currentId)
            ->pluck('NhanVienID')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        // Mock phase: allow stub employee IDs used by the booking intake picker until real staff data is wired.
        return Rule::in(array_values(array_unique([...$employeeIds, ...self::MOCK_EMPLOYEE_IDS])));
    }

    public static function assertBookingIntakeAssignable(
        ?User $user,
        ?int $employeeId,
        string $field,
        ?int $currentId = null,
    ): void {
        if (! self::canAssignBookingIntake($user)) {
            throw ValidationException::withMessages([
                $field => 'Bạn không có quyền thay đổi nhân viên phụ trách phiếu nhận đồ.',
            ]);
        }

        if (
            $employeeId === null
            || (! self::bookingIntakeCandidates($user, $currentId)->whereKey($employeeId)->exists()
                && ! in_array($employeeId, self::MOCK_EMPLOYEE_IDS, true))
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
