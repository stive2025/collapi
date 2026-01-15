<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionCallResource extends JsonResource
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
            'phone_number' => $this->phone_number ?? null,
            'client_name' => $this->contact->client->name ?? 'N/D',
            'call_state' => $this->state,
            'call_duration' => $this->duration,
            'created_by_name' => $this->creator->name ?? null,
            'call_channel' => $this->channel,
            'call_media_path' => $this->media_path,
            'created_at' => $this->created_at?->format('Y/m/d H:i:s'),
        ];
    }
}
