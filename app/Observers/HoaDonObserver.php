<?php

namespace App\Observers;

use App\Models\HoaDon;
use App\Models\LichSuThayDoiHoaDon;
use Illuminate\Support\Facades\Log;

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
            // Background updates (for example, a confirmed SePay webhook) have
            // no logged-in user. The audit table requires TaiKhoanID, so record
            // the system change in application logs instead of aborting payment.
            Log::info('Invoice changed without an authenticated account; user audit row skipped.', [
                'invoice_id' => $hoaDon->HoaDonID,
                'fields' => array_values($changedFields),
            ]);

            return;
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
