<?php

namespace App\Http\Resources;

use App\Enums\RecordStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'unit' => $this->unit,
            'price' => (float) $this->price,
            'formatted_price' => format_currency($this->price),
            'status' => $this->status,
            'status_label' => RecordStatus::parse($this->status)->label(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'description' => $this->description,
        ];
    }
}
