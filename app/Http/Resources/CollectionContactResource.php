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
        ];
    }
}