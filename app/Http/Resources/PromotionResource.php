<?php

namespace App\Http\Resources;

use App\Enums\RecordStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Promotion
 */
class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'min_order_amount' => (float) $this->min_order_amount,
            'max_discount' => $this->max_discount === null ? null : (float) $this->max_discount,
            'usage_limit' => $this->usage_limit,
            'used_count' => (int) $this->used_count,
            'quantity' => $this->quantity,
            'remaining' => $this->quantity === null ? null : max(0, (int) $this->quantity - (int) $this->used_count),
            'conditions' => $this->conditions,
            'is_first_order_only' => $this->isFirstOrderOnly(),
            'is_valid' => $this->isValid(),
            'starts_at' => $this->starts_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'status' => $this->status,
            'status_label' => RecordStatus::parse($this->status)->label(),
        ];
    }
}
