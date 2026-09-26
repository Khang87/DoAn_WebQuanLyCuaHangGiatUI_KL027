<?php

namespace App\Http\Resources;

use App\Enums\RecordStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Garment
 */
class GarmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'price' => (float) $this->price,
            'formatted_price' => format_currency($this->price),
            'condition_note' => $this->condition_note,
            'status' => $this->status,
            'status_label' => RecordStatus::parse($this->status)->label(),
        ];
    }
}
