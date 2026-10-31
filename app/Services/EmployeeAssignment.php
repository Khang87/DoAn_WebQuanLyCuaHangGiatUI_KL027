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
    /**
     * Lấy danh sách nhân viên có thể phân công cho giao nhận.
     * Bao gồm: người trong nhóm "Nhân viên" (có quyền deliveries.*), Chủ cửa hàng, Quản lý.
     */
    public static function deliveryCandidates(?int $currentId = null): Builder
    {
        return NhanVien::query()->where(function ($query) use ($currentId): void {
            $query->where('TrangThai', 'Hoạt động')
                ->whereHas('taiKhoans', fn ($accounts) => $accounts
                    ->where('TrangThai', 'Hoạt động')
                    ->whereHas('vaiTros', fn ($roles) => $roles
                        ->where(fn ($names) => $names
                            ->where('TenVaiTro', 'Nhân viên')
                            ->orWhere('TenVaiTro', 'Chủ cửa hàng')
                            ->orWhere('TenVaiTro', 'like', 'Quản lý%'))));

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

        return Rule::in($employeeIds);
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
            || ! self::bookingIntakeCandidates($user, $currentId)->whereKey($employeeId)->exists()
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
