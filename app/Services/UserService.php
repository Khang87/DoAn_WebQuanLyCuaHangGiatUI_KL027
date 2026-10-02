<?php

namespace App\Services;

use App\Models\LichSuThayDoiHoaDon;
use App\Models\NhatKyHeThong;
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
            $user->load('vaiTros');
            $this->recordAccountAudit(
                $user,
                'Tạo tài khoản',
                [],
                $this->accountAuditSnapshot($user),
            );

            return $user->load(['vaiTros', 'nhanVien', 'khachHang']);
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->load('vaiTros');
            $before = $this->accountAuditSnapshot($user);
            $attributes = [];

            if (array_key_exists('email', $data)) {
                $attributes['TenDangNhap'] = $data['email'];
                $attributes['Email'] = $data['email'];
            }

            if (array_key_exists('phone', $data)) {
                $attributes['SoDienThoai'] = $data['phone'];
            }

            if (array_key_exists('username', $data)) {
                $attributes['TenDangNhap'] = $data['username'];
            }

            $passwordChanged = ! empty($data['password']);

            if ($passwordChanged) {
                $attributes['MatKhau'] = Hash::make($data['password']);
            }

            if ($attributes !== []) {
                $user->update($attributes);
            }

            if (isset($data['role'])) {
                $this->syncRole($user, $data['role']);
            }

            $updatedUser = $user->fresh(['vaiTros', 'nhanVien', 'khachHang']);
            $after = $this->accountAuditSnapshot($updatedUser);

            if ($before !== $after || $passwordChanged) {
                $action = $passwordChanged && array_diff(array_keys($data), ['password']) === []
                    ? 'Đổi mật khẩu tài khoản'
                    : 'Thay đổi tài khoản';
                $this->recordAccountAudit($updatedUser, $action, $before, $after);
            }

            return $updatedUser;
        });
    }

    public function delete(User $user): bool
    {
        if ((int) $user->getKey() === (int) auth()->id()) {
            return false;
        }

        return DB::transaction(function () use ($user): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $before = $this->accountAuditSnapshot($lockedUser);

            if ($this->hasRelatedRecords($lockedUser)) {
                $lockedUser->update(['TrangThai' => 'Ngừng hoạt động']);
                $after = $this->accountAuditSnapshot($lockedUser->fresh('vaiTros'));

                if ($before !== $after) {
                    $this->recordAccountAudit($lockedUser, 'Vô hiệu hóa tài khoản', $before, $after);
                }

                return true;
            }

            $deleted = (bool) $lockedUser->delete();

            if ($deleted) {
                $this->recordAccountAudit($lockedUser, 'Xóa tài khoản', $before, []);
            }

            return $deleted;
        });
    }

    public function restore(int $id): ?User
    {
        return DB::transaction(function () use ($id): ?User {
            $user = User::query()->lockForUpdate()->find($id);

            if ($user === null) {
                return null;
            }

            $before = $this->accountAuditSnapshot($user);
            $user->update(['TrangThai' => 'Hoạt động']);
            $user->refresh();
            $after = $this->accountAuditSnapshot($user->load('vaiTros'));

            if ($before !== $after) {
                $this->recordAccountAudit($user, 'Khôi phục tài khoản', $before, $after);
            }

            return $user;
        });
    }

    public function setStatus(User $user, string $status): User
    {
        return DB::transaction(function () use ($user, $status): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $before = $this->accountAuditSnapshot($lockedUser);
            $lockedUser->update(['TrangThai' => $status]);
            $lockedUser->refresh();
            $after = $this->accountAuditSnapshot($lockedUser->load('vaiTros'));

            if ($before !== $after) {
                $this->recordAccountAudit($lockedUser, 'Thay đổi trạng thái tài khoản', $before, $after);
            }

            return $lockedUser;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function accountAuditSnapshot(User $user): array
    {
        return [
            'TaiKhoanID' => (int) $user->getKey(),
            'TenDangNhap' => $user->TenDangNhap,
            'Email' => $user->Email,
            'SoDienThoai' => $user->SoDienThoai,
            'NhanVienID' => $user->NhanVienID,
            'KhachHangID' => $user->KhachHangID,
            'TrangThai' => $user->TrangThai,
            'VaiTroIDs' => $user->vaiTros
                ->pluck('VaiTroID')
                ->map(fn ($roleId): int => (int) $roleId)
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordAccountAudit(User $user, string $action, array $before, array $after): void
    {
        NhatKyHeThong::create([
            'TaiKhoanID' => auth()->id(),
            'HanhDong' => $action,
            'BangDuLieu' => 'TaiKhoan',
            'BanGhiID' => $user->getKey(),
            'DuLieuCu' => $before,
            'DuLieuMoi' => $after,
            'ThoiGian' => now(),
            'IPAddress' => request()->ip(),
            'UserAgent' => request()->userAgent(),
        ]);
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
                || NhatKyHeThong::where('TaiKhoanID', $userId)->exists();
    }
}
