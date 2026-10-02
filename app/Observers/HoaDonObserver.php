<?php

namespace App\Observers;

use App\Models\HoaDon;
use App\Models\LichSuThayDoiHoaDon;
use LogicException;

class HoaDonObserver
{
    /**
     * Record accounting-relevant invoice changes using the live schema fields.
     */
    public function updated(HoaDon $hoaDon): void
    {
        $trackedFields = [
            'TrangThai' => 'Trạng thái',
            'TongTien' => 'Tổng tiền',
            'GiamGia' => 'Giảm giá',
            'PhiGiaoHang' => 'Phí giao hàng',
            'ThanhTien' => 'Thành tiền',
        ];
        $changes = $hoaDon->getChanges();
        $changedFields = array_intersect_key($trackedFields, $changes);

        if ($changedFields === []) {
            return;
        }

        $accountId = auth()->id();
        if ($accountId === null) {
            throw new LogicException('Cần có tài khoản đăng nhập để ghi lịch sử thay đổi hóa đơn.');
        }

        foreach ($changedFields as $column => $label) {
            LichSuThayDoiHoaDon::query()->create([
                'HoaDonID' => $hoaDon->HoaDonID,
                'TaiKhoanID' => $accountId,
                'ThoiGian' => now(),
                'TruongThayDoi' => $label,
                'GiaTriCu' => $hoaDon->getRawOriginal($column) === null
                    ? null
                    : (string) $hoaDon->getRawOriginal($column),
                'GiaTriMoi' => $hoaDon->getAttribute($column) === null
                    ? null
                    : (string) $hoaDon->getAttribute($column),
            ]);
        }
    }
}
