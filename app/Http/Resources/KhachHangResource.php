<?php

namespace App\Http\Resources;

use App\Models\KhachHang;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KhachHang
 */
class KhachHangResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->KhachHangID,
            'name' => $this->HoTen,
            'email' => $this->Email,
            'phone' => $this->SoDienThoai,
            'address' => $this->DiaChi,
            'points' => (int) ($this->diemTichLuy->DiemHienTai ?? 0),
            'orders_count' => $this->whenCounted('donHangs'),
            'created_at' => $this->NgayTao?->toIso8601String(),
        ];
    }
}
