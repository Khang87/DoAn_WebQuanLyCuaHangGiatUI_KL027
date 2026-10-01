<?php

namespace App\Services;

use App\Models\DonHangTrangthai;
use App\Models\LichSuThayDoiHoaDon;
use App\Models\TinNhan;
use App\Models\User;
use App\Models\VaiTro;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = User::query()->with(['vaiTros', 'nhanVien', 'khachHang']);

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($accountQuery) use ($search): void {
                $accountQuery->where('TenDangNhap', 'like', "%{$search}%")
                    ->orWhere('Email', 'like', "%{$search}%")
                    ->orWhere('SoDienThoai', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['role'])) {
            $role = VaiTro::query()->get()->first(
                fn (VaiTro $candidate): bool => $candidate->slug === $filters['role'],
            );

            if ($role) {
                $query->whereHas('vaiTros', fn ($roleQuery) => $roleQuery->where('VaiTro.VaiTroID', $role->VaiTroID));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (($filters['status'] ?? null) === 'active') {
            $query->where('TrangThai', 'Hoạt động');
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->whereIn('TrangThai', ['Khóa', 'Ngừng hoạt động']);
        }

        $sortMap = [
            'created_at_desc' => ['NgayTao', 'desc'],
            'created_at_asc' => ['NgayTao', 'asc'],
            'name_asc' => ['TenDangNhap', 'asc'],
            'name_desc' => ['TenDangNhap', 'desc'],
            'email_asc' => ['Email', 'asc'],
            'email_desc' => ['Email', 'desc'],
        ];
        [$sortBy, $sortOrder] = $sortMap[$filters['sort'] ?? 'latest'] ?? ['NgayTao', 'desc'];

        return $query->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?User
    {
        return User::query()->with(['vaiTros', 'nhanVien', 'khachHang'])->find($id);
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'TenDangNhap' => $data['email'],
                'MatKhau' => Hash::make($data['password']),
                'Email' => $data['email'],
                'SoDienThoai' => $data['phone'] ?? null,
                'NhanVienID' => $data['NhanVienID'] ?? null,
                'KhachHangID' => $data['KhachHangID'] ?? null,
                'TrangThai' => 'Hoạt động',
                'NgayTao' => now(),
            ]);

            $this->syncRole($user, $data['role']);

            return $user->load(['vaiTros', 'nhanVien', 'khachHang']);
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $attributes = [
                'TenDangNhap' => $data['email'],
                'Email' => $data['email'],
                'SoDienThoai' => $data['phone'] ?? null,
            ];

            if (! empty($data['password'])) {
                $attributes['MatKhau'] = Hash::make($data['password']);
            }

            $user->update($attributes);

            if (isset($data['role'])) {
                $this->syncRole($user, $data['role']);
            }

            return $user->fresh(['vaiTros', 'nhanVien', 'khachHang']);
        });
    }

    public function delete(User $user): bool
    {
        if ((int) $user->getKey() === (int) auth()->id()) {
            return false;
        }

        if ($this->hasRelatedRecords($user)) {
            $user->update(['TrangThai' => 'Ngừng hoạt động']);

            return true;
        }

        return (bool) $user->delete();
    }

    public function restore(int $id): ?User
    {
        $user = User::find($id);
        $user?->update(['TrangThai' => 'Hoạt động']);

        return $user;
    }

    private function syncRole(User $user, string $roleSlug): void
    {
        $role = VaiTro::query()
            ->where('TrangThai', 'Hoạt động')
            ->get()
            ->first(fn (VaiTro $candidate): bool => $candidate->slug === $roleSlug);

        if (! $role) {
            throw ValidationException::withMessages([
                'role' => 'Vai trò được chọn không còn hoạt động.',
            ]);
        }

        $user->vaiTros()->sync([$role->VaiTroID]);
    }

    private function hasRelatedRecords(User $user): bool
    {
        $userId = (int) $user->getKey();

        return $user->vaiTros()->exists()
            || $user->thongBaos()->exists()
            || TinNhan::query()
                ->where('NguoiGuiID', $userId)
                ->orWhere('NguoiNhanID', $userId)
                ->exists()
            || LichSuThayDoiHoaDon::where('TaiKhoanID', $userId)->exists()
            || DonHangTrangthai::where('taikhoanid', $userId)->exists();
    }
}
