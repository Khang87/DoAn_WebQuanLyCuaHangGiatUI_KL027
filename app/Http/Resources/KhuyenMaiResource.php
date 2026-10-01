<?php

namespace App\Http\Resources;

use App\Enums\RecordStatus;
use App\Models\KhuyenMai;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KhuyenMai
 */
class KhuyenMaiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->KhuyenMaiID,
            'name' => $this->TenKhuyenMai,
            'code' => $this->MaKhuyenMai,
            'discount_type' => $this->LoaiKhuyenMai,
            'discount_value' => (float) $this->GiaTriGiam,
            'min_order_amount' => (float) $this->GiaTriDonToiThieu,
            'max_discount' => $this->MucGiamToiDa === null ? null : (float) $this->MucGiamToiDa,
            'used_count' => $this->usedCount,
            'conditions' => $this->DieuKienApDung,
            'is_first_order_only' => $this->isFirstOrderOnly(),
            'is_valid' => $this->isValid(),
            'starts_at' => $this->NgayBatDau?->toDateString(),
            'expires_at' => $this->NgayKetThuc?->toDateString(),
            'status' => $this->TrangThai,
            'status_label' => RecordStatus::labelFor($this->TrangThai),
        ];
    }
}
