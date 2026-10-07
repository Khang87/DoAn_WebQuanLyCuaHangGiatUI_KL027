<?php

namespace App\Services;

use App\Models\NhanVien;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

class EmployeeAssignment
{
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
