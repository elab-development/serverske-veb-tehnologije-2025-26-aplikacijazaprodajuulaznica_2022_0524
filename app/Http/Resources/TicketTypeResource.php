<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'quantity_total' => $this->quantity_total,
            'quantity_available' => $this->quantity_available,
            'max_per_order' => $this->max_per_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'event' => new EventResource($this->whenLoaded('event')),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
        ];
    }
}
