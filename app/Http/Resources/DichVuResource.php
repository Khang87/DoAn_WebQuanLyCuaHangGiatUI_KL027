<?php

namespace App\Http\Resources;

use App\Enums\RecordStatus;
use App\Models\DichVu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DichVu
 */
class DichVuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->DichVuID,
            'name' => $this->TenDichVu,
            'type' => $this->loaiDichVu?->TenLoaiDichVu,
            'unit' => $this->donViTinh?->KyHieu,
            'price' => (float) ($this->bangGia?->DonGia ?? 0),
            'formatted_price' => isset($this->bangGia?->DonGia) ? format_currency($this->bangGia->DonGia) : null,
            'status' => $this->TrangThai,
            'status_label' => RecordStatus::parse($this->TrangThai)->label(),
            'category' => $this->whenLoaded('loaiDichVu', fn () => [
                'id' => $this->loaiDichVu?->LoaiDichVuID,
                'name' => $this->loaiDichVu?->TenLoaiDichVu,
            ]),
            'description' => $this->MoTa,
        ];
    }
}
