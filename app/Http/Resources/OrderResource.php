<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'status_label' => \App\Enums\OrderStatus::parse($this->status)->label(),
            'is_locked' => $this->isLocked(),
            'can_edit' => $this->canEdit(),
            'can_delete' => $this->canDelete(),
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'service' => ServiceResource::make($this->whenLoaded('service')),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
                'role' => $this->employee->role,
            ]),
            'promotion' => PromotionResource::make($this->whenLoaded('promotion')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'amounts' => [
                'subtotal' => (float) $this->subtotal,
                'discount_by_promotion' => (float) $this->discount_by_promotion,
                'discount_by_points' => (float) $this->discount_by_points,
                'discount_total' => (float) $this->discount_by_promotion + (float) $this->discount_by_points,
                'total_amount' => (float) $this->total_amount,
                'formatted_total_amount' => format_currency($this->total_amount),
            ],
            'points_used' => (int) $this->points_used,
            'weight_kg' => $this->weight_kg,
            'quantity_items' => $this->quantity_items,
            'booking_id' => $this->booking_id,
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice === null ? null : [
                'id' => $this->invoice->id,
                'code' => $this->invoice->code,
                'status' => $this->invoice->status,
                'status_label' => $this->invoice->getStatusLabel(),
                'total' => (float) $this->invoice->total,
                'grand_total' => (float) $this->invoice->grand_total,
            ]),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
