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
        $firstDirection = $this->clients?->first()?->directions?->first();
        
        return [
            'id' => $this->id,
            'sync_id' => $this->sync_id,
            'agency' => $this->agency,
            'province' => $firstDirection?->province,
            'canton' => $firstDirection?->canton,
            'parish' => $firstDirection?->parish,
            'neighborhood' => $firstDirection?->neighborhood,
            'direction_type' => $firstDirection?->type,
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
            'agent_name' => $this->user?->name ?? null,
            'business_id' => $this->business_id,
            'business_name' => $this->business?->name ?? null,
            'clients' => $this->whenLoaded('clients', function() {
                return $this->clients->map(function($client) {
                    return [
                        'id' => $client->id,
                        'name' => $client->name,
                        'ci' => $client->ci,
                        'type' => $client->pivot->type,
                        'directions' => $client->directions?->map(fn($dir) => [
                            'id' => $dir->id,
                            'direction' => $dir->direction,
                            'type' => $dir->type,
                            'province' => $dir->province,
                            'canton' => $dir->canton,
                            'parish' => $dir->parish,
                            'neighborhood' => $dir->neighborhood,
                            'latitude' => $dir->latitude,
                            'longitude' => $dir->longitude,
                        ]),
                        'collection_contacts' => $client->collectionContacts?->map(fn($contact) => [
                            'id' => $contact->id,
                            'client_name' => $contact->client_name,
                            'client_type' => $contact->client_type,
                            'client_ci' => $contact->client_ci,
                            'phone' => $contact->phone,
                            'email' => $contact->email,
                            'type' => $contact->type,
                        ])
                    ];
                });
            }),
            'collection_managements' => $this->whenLoaded('collectionManagements'),
            'collection_calls' => $this->whenLoaded('collectionCalls'),
            'collection_payments' => $this->whenLoaded('collectionPayments'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}