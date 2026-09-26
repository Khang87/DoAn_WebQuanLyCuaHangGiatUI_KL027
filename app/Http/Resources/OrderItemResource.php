<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_name' => $this->item_name,
            'item_type' => $this->item_type,
            'service_id' => $this->service_id,
            'service_name' => $this->whenLoaded('service', fn () => $this->service?->name),
            'garment_id' => $this->garment_id,
            'garment_name' => $this->whenLoaded('garment', fn () => $this->garment?->name),
            'unit' => $this->whenLoaded('service', fn () => $this->service?->unit),
            'price' => (float) $this->price,
            'quantity' => (int) $this->quantity,
            'weight' => (float) $this->weight,
            'subtotal' => (float) $this->subtotal,
            'notes' => $this->notes,
        ];
    }
}
