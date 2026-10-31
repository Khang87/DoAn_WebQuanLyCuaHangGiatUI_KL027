<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\LichSuThayDoiHoaDon;
use App\Models\NhatKyHeThong;
use App\Models\ThongBao;
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
            // TaiKhoan uses case-sensitive PostgreSQL identifiers for these columns.
            $authId = $data['userauthid'] ?? null;

            if (is_string($authId) && trim($authId) !== '') {
                $existingByAuthId = User::query()->where('UserAuthId', $authId)->first();

                if ($existingByAuthId) {
                    throw new \RuntimeException('Tài khoản với Google ID này đã tồn tại.');
                }
            }

            $existingByEmail = User::query()->where('Email', $data['email'])->first();

            if ($existingByEmail) {
                // Nếu email đã tồn tại, trả về tài khoản cũ thay vì tạo mới
                throw new \RuntimeException('Email này đã được sử dụng cho tài khoản khác.');
            }

            $user = User::create([
                'TenDangNhap' => $data['email'],
                'MatKhau' => Hash::make($data['password']),
                'Email' => $data['email'],
                'SoDienThoai' => $data['phone'] ?? null,
                'NhanVienID' => $data['NhanVienID'] ?? null,
                'KhachHangID' => $data['KhachHangID'] ?? null,
                'TrangThai' => 'Hoạt động',
                'NgayTao' => now(),
                'UserAuthId' => $authId,
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

    /**
     * @return array{
     *     message: ?string,
     *     orders: list<array{id: int, code: string, status: string, payment_status: string}>
     * }
     */
    public function delete(User $user): array
    {
        if ((int) $user->getKey() === (int) auth()->id()) {
            return ['message' => 'Không thể xóa tài khoản đang đăng nhập.', 'orders' => []];
        }

        return DB::transaction(function () use ($user): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->KhachHangID !== null) {
                DB::table('KhachHang')
                    ->where('KhachHangID', $lockedUser->KhachHangID)
                    ->lockForUpdate()
                    ->first();
            }

            if ($lockedUser->NhanVienID !== null) {
                DB::table('NhanVien')
                    ->where('NhanVienID', $lockedUser->NhanVienID)
                    ->lockForUpdate()
                    ->first();
            }

            $blockingOrders = DB::table('DonHang')
                ->where(function ($query) use ($lockedUser): void {
                    $hasProfile = false;

                    if ($lockedUser->KhachHangID !== null) {
                        $query->where('KhachHangID', $lockedUser->KhachHangID);
                        $hasProfile = true;
                    }

                    if ($lockedUser->NhanVienID !== null) {
                        $method = $hasProfile ? 'orWhere' : 'where';
                        $query->{$method}('NhanVienID', $lockedUser->NhanVienID);
                        $hasProfile = true;
                    }

                    if (! $hasProfile) {
                        $query->whereRaw('1 = 0');
                    }
                })
                ->where(function ($query): void {
                    $query->where('TrangThai', '!=', OrderStatus::Cancelled->value)
                        ->where(function ($incomplete): void {
                            $incomplete->where('TrangThai', '!=', OrderStatus::Delivered->value)
                                ->orWhereNotExists(function ($invoice): void {
                                    $invoice->selectRaw('1')
                                        ->from('HoaDon')
                                        ->whereColumn('HoaDon.DonHangID', 'DonHang.DonHangID')
                                        ->where('HoaDon.TrangThai', InvoiceStatus::Paid->value);
                                });
                        });
                })
                ->orderBy('MaDonHang')
                ->addSelect([
                    'paid_invoice_id' => DB::table('HoaDon')
                        ->select('HoaDonID')
                        ->whereColumn('HoaDon.DonHangID', 'DonHang.DonHangID')
                        ->where('HoaDon.TrangThai', InvoiceStatus::Paid->value)
                        ->limit(1),
                ])
                ->lockForUpdate()
                ->get(['DonHangID', 'MaDonHang', 'TrangThai'])
                ->map(fn (object $order): array => [
                    'id' => (int) $order->DonHangID,
                    'code' => (string) $order->MaDonHang,
                    'status' => (string) $order->TrangThai,
                    'payment_status' => $order->paid_invoice_id !== null
                        ? InvoiceStatus::Paid->value
                        : InvoiceStatus::Unpaid->value,
                ])
                ->all();

            if ($blockingOrders !== []) {
                return [
                    'message' => 'Không thể xóa tài khoản vì còn đơn hàng chưa hoàn tất hoặc chưa thanh toán.',
                    'orders' => $blockingOrders,
                ];
            }

            $userId = (int) $lockedUser->getKey();
            $messageIds = TinNhan::query()
                ->where(fn ($query) => $query
                    ->where('NguoiGuiID', $userId)
                    ->orWhere('NguoiNhanID', $userId))
                ->pluck('TinNhanID');

            ThongBao::query()->where('TaiKhoanID', $userId)->delete();

            DB::table('ThongBao')
                ->whereIn('TinNhanID', $messageIds)
                ->update(['TinNhanID' => null]);

            DB::table('TinNhanAn')
                ->where(fn ($query) => $query
                    ->where('TaiKhoanID', $userId)
                    ->orWhereIn('TinNhanID', $messageIds))
                ->delete();

            TinNhan::query()
                ->where(fn ($query) => $query
                    ->where('NguoiGuiID', $userId)
                    ->orWhere('NguoiNhanID', $userId))
                ->delete();

            LichSuThayDoiHoaDon::query()->where('TaiKhoanID', $userId)->delete();
            NhatKyHeThong::query()->where('TaiKhoanID', $userId)->delete();
            DB::table('push_device_tokens')->where('TaiKhoanID', $userId)->delete();
            $lockedUser->vaiTros()->detach();

            $deleted = (bool) $lockedUser->delete();

            if ($deleted) {
                $this->recordAccountAudit($lockedUser, 'Xóa tài khoản', [], []);
            }

            return [
                'message' => $deleted ? null : 'Không thể xóa tài khoản. Vui lòng thử lại.',
                'orders' => [],
            ];
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
}
