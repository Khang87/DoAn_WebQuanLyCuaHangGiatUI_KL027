<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\DonHang
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->DonHangID,
            'code' => $this->MaDonHang,
            'status' => $this->TrangThai,
            'status_label' => \App\Enums\OrderStatus::parse($this->TrangThai)->label(),
            'is_locked' => $this->isLocked(),
            'can_edit' => $this->canEdit(),
            'can_delete' => $this->canDelete(),
            'customer' => CustomerResource::make($this->whenLoaded('khachHang')),
            'service' => ServiceResource::make($this->whenLoaded('dichVu')),
            'employee' => $this->whenLoaded('nhanVien', fn () => [
                'id' => $this->nhanVien->NhanVienID,
                'name' => $this->nhanVien->HoTen,
                'role' => $this->nhanVien->ChucDanh,
            ]),
            'promotion' => PromotionResource::make($this->whenLoaded('khuyenMai')),
            'items' => OrderItemResource::collection($this->whenLoaded('chiTietDonHangs')),
            'amounts' => [
                'subtotal' => (float) $this->TongTien,
                'discount_by_promotion' => (float) $this->TienGiamKhuyenMai,
                'discount_by_points' => (float) $this->TienGiamDoDiem,
                'discount_total' => (float) $this->TienGiamKhuyenMai + (float) $this->TienGiamDoDiem,
                'total_amount' => (float) $this->ThanhTien,
                'formatted_total_amount' => format_currency($this->ThanhTien),
            ],
            'points_used' => (int) $this->DiemSuDung,
            'booking_id' => $this->BookingID,
            'invoice' => $this->whenLoaded('hoaDons', fn () => $this->hoaDons->isEmpty() ? null : [
                'id' => $this->hoaDons->first()->HoaDonID,
                'code' => $this->hoaDons->first()->MaHoaDon,
                'status' => $this->hoaDons->first()->TrangThai,
                'total' => (float) $this->hoaDons->first()->TongTien,
            ]),
            'notes' => $this->GhiChu,
            'created_at' => $this->NgayTao?->toIso8601String(),
            'updated_at' => $this->NgayCapNhat?->toIso8601String(),
        ];
    }
}