<?php
// filepath: c:\xampp\htdocs\collapi\app\Http\Resources\ManagementResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ManagementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Verificar si alguna llamada de la gestión fue por WhatsApp
        $isWweb = false;
        if (!empty($this->call_collection)) {
            $callIds = json_decode($this->call_collection, true);
            if (is_array($callIds) && count($callIds) > 0) {
                $isWweb = DB::table('collection_calls')
                    ->whereIn('id', $callIds)
                    ->where('channel', 'WA')
                    ->exists();
            }
        }

        return [
            'id' => $this->id,
            'state' => $this->state,
            'substate' => $this->substate,
            'observation' => $this->observation,
            'promise_date' => $this->promise_date,
            'promise_amount' => $this->promise_amount,
            'created_by' => $this->created_by,
            'created_by_name' => $this->creator->name ?? null,
            'call_id' => $this->call_id,
            'call_collection' => $this->call_collection,
            'days_past_due' => $this->days_past_due,
            'paid_fees' => $this->paid_fees,
            'pending_fees' => $this->pending_fees,
            'managed_amount' => $this->managed_amount,
            'nro_notification' => $this->nro_notification,
            'client_id' => $this->client_id,
            'client_name' => $this->client->name ?? null,
            'client_ci' => $this->client->ci ?? null,
            'client_type' => $this->credit?->clients?->where('id', $this->client_id)->first()?->pivot?->type,
            'credit_id' => $this->credit_id,
            'campain_id' => $this->campain_id,
            'campain_name' => $this->campain->name ?? null,
            'client' => $this->whenLoaded('client'),
            'credit' => $this->whenLoaded('credit'),
            'campain' => $this->whenLoaded('campain'),
            'created_at' => $this->created_at?->format('Y/m/d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y/m/d H:i:s'),
            'is_wweb' => $isWweb,
        ];
    }
}