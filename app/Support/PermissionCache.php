<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cache danh sách vai trò + mã quyền của tài khoản trong phạm vi một request.
 *
 * Lấy quyền cần đi qua 3 bảng nối:
 *   TaiKhoan -> TaiKhoan_VaiTro -> VaiTro_Quyen -> Quyen
 * Cache không sống qua request để việc thu hồi quyền hoặc vô hiệu hóa tài khoản
 * có hiệu lực ở request kế tiếp, kể cả khi chạy nhiều worker.
 */
final class PermissionCache
{
    private const REQUEST_KEY = 'rbac.permissions';

    /**
     * Vai trò (TenVaiTro) và mã quyền (MaQuyen) của tài khoản.
     *
     * @return array{roles: list<string>, quyens: list<string>}
     */
    public static function load(int $taiKhoanId): array
    {
        $request = self::request();
        $cached = $request?->attributes->get(self::REQUEST_KEY, []);

        if (isset($cached[$taiKhoanId])) {
            return $cached[$taiKhoanId];
        }

        $payload = self::query($taiKhoanId);

        if ($request !== null) {
            $cached[$taiKhoanId] = $payload;
            $request->attributes->set(self::REQUEST_KEY, $cached);
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    public static function quyens(int $taiKhoanId): array
    {
        return self::load($taiKhoanId)['quyens'];
    }

    /**
     * @return list<string>
     */
    public static function roles(int $taiKhoanId): array
    {
        return self::load($taiKhoanId)['roles'];
    }

    /**
     * Xoá cache của một tài khoản, hoặc của tài khoản đang đăng nhập.
     */
    public static function forget(?int $taiKhoanId = null): void
    {
        $request = self::request();

        if ($request === null) {
            return;
        }

        $cached = $request->attributes->get(self::REQUEST_KEY, []);

        if ($taiKhoanId !== null) {
            unset($cached[$taiKhoanId]);
            $request->attributes->set(self::REQUEST_KEY, $cached);

            return;
        }

        $request->attributes->remove(self::REQUEST_KEY);
    }

    /**
     * Xoá cache phân quyền trong request hiện tại.
     */
    public static function forgetAll(): void
    {
        self::forget();
    }

    /**
     * Một câu truy vấn gộp duy nhất lấy cả vai trò lẫn mã quyền.
     *
     * @return array{roles: list<string>, quyens: list<string>}
     */
    private static function query(int $taiKhoanId): array
    {
        $rows = DB::table('TaiKhoan_VaiTro')
            ->join('TaiKhoan', 'TaiKhoan.TaiKhoanID', '=', 'TaiKhoan_VaiTro.TaiKhoanID')
            ->join('VaiTro', 'VaiTro.VaiTroID', '=', 'TaiKhoan_VaiTro.VaiTroID')
            ->leftJoin('VaiTro_Quyen', 'VaiTro_Quyen.VaiTroID', '=', 'VaiTro.VaiTroID')
            ->leftJoin('Quyen', function ($join) {
                $join->on('Quyen.QuyenID', '=', 'VaiTro_Quyen.QuyenID')
                    ->where('Quyen.TrangThai', '=', 'Hoạt động');
            })
            ->where('TaiKhoan_VaiTro.TaiKhoanID', $taiKhoanId)
            ->where('TaiKhoan.TrangThai', '=', 'Hoạt động')
            ->where('VaiTro.TrangThai', '=', 'Hoạt động')
            ->get([
                'VaiTro.TenVaiTro',
                'Quyen.MaQuyen',
            ]);

        $roles = [];
        $quyens = [];

        foreach ($rows as $row) {
            if ($row->TenVaiTro !== null && ! in_array($row->TenVaiTro, $roles, true)) {
                $roles[] = $row->TenVaiTro;
            }

            if ($row->MaQuyen !== null && ! in_array($row->MaQuyen, $quyens, true)) {
                $quyens[] = $row->MaQuyen;
            }
        }

        return ['roles' => $roles, 'quyens' => $quyens];
    }

    private static function request(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        return app('request');
    }
}
