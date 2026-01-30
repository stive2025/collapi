<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionDirectionResource extends JsonResource
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
            'client_id' => $this->client_id,
            'direction' => $this->direction,
            'type' => $this->type,
            'province' => $this->province,
            'canton' => $this->canton,
            'parish' => $this->parish,
            'neighborhood' => $this->neighborhood,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'client_name' => $this->client?->name ?? null,
            'client_ci' => $this->client?->ci ?? null,
            'sync_id' => $this->client?->credits?->first()?->sync_id ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
