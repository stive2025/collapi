<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionContactResource extends JsonResource
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
            'phone_number' => $this->phone_number,
            'phone_type' => $this->phone_type,
            'phone_status' => $this->phone_status,
            'calls_effective' => $this->calls_effective ?? 0,
            'calls_not_effective' => $this->calls_not_effective ?? 0,
            'client_id' => $this->client_id ?? null,
            'client_name' => $this->client?->name ?? null,
            'client_ci' => $this->client?->ci ?? null,
            'client_type' => $this->client?->credits?->first()?->pivot?->type ?? null,
            'sync_id' => $this->client?->credits?->first()?->sync_id ?? null,
            'is_external' => is_null($this->created_by) && is_null($this->updated_by) && is_null($this->deleted_by),
        ];
    }
}