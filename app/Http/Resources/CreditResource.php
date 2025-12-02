<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditResource extends JsonResource
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
            'sync_id' => $this->sync_id,
            'agency' => $this->agency,
            'collection_state' => $this->collection_state,
            'frequency' => $this->frequency,
            'payment_date' => $this->payment_date,
            'award_date' => $this->award_date,
            'due_date' => $this->due_date,
            'days_past_due' => $this->days_past_due,
            'total_fees' => $this->total_fees,
            'paid_fees' => $this->paid_fees,
            'pending_fees' => $this->pending_fees,
            'monthly_fee_amount' => $this->monthly_fee_amount,
            'total_amount' => $this->total_amount,
            'capital' => $this->capital,
            'interest' => $this->interest,
            'mora' => $this->mora,
            'safe' => $this->safe,
            'management_collection_expenses' => $this->management_collection_expenses,
            'collection_expenses' => $this->collection_expenses,
            'legal_expenses' => $this->legal_expenses,
            'other_values' => $this->other_values,
            'sync_status' => $this->sync_status,
            'last_sync_date' => $this->last_sync_date,
            'management_status' => $this->management_status,
            'management_tray' => $this->management_tray,
            'user_id' => $this->user_id,
            'business_id' => $this->business_id,
            'clients' => $this->whenLoaded('clients', function() {
                return $this->clients->map(fn($client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'ci' => $client->ci,
                    'type' => $client->pivot->type
                ]);
            }),
            'collection_managements' => $this->whenLoaded('collectionManagements'),
            'collection_calls' => $this->whenLoaded('collectionCalls'),
            'collection_payments' => $this->whenLoaded('collectionPayments'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}