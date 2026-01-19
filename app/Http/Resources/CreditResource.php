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
            'management_promise' => $this->management_promise,
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
                            'phone' => $contact->phone_number,
                            'type' => $contact->phone_type,
                            'calls_effective' => $contact->calls_effective,
                            'calls_not_effective' => $contact->calls_not_effective,
                        ])
                    ];
                });
            }),
            'collection_managements' => $this->whenLoaded('collectionManagements', function() {
                return ManagementResource::collection($this->collectionManagements->sortByDesc('created_at')->values());
            }),
            'collection_calls' => $this->whenLoaded('collectionCalls', function() {
                return $this->collectionCalls->sortByDesc('created_at')->values();
            }),
            'collection_payments' => $this->whenLoaded('collectionPayments', function() {
                // Crear colección combinada de pagos e invoices
                $combined = collect();

                // Agregar pagos reales
                foreach ($this->collectionPayments as $payment) {
                    $combined->push($payment);
                }

                // Agregar invoices como pseudo-pagos si están cargadas
                if ($this->relationLoaded('invoices') && $this->invoices) {
                    foreach ($this->invoices as $invoice) {
                        $invoiceAsPayment = new \stdClass();
                        $invoiceAsPayment->id = $invoice->id;
                        $invoiceAsPayment->created_by = $invoice->created_by;
                        $invoiceAsPayment->payment_date = $invoice->invoice_date;
                        $invoiceAsPayment->payment_deposit_date = null;
                        $invoiceAsPayment->payment_value = floatval($invoice->invoice_value);
                        $invoiceAsPayment->payment_difference = 0;
                        $invoiceAsPayment->payment_type = $invoice->invoice_method;
                        $invoiceAsPayment->payment_method = null;
                        $invoiceAsPayment->financial_institution = $invoice->invoice_institution;
                        $invoiceAsPayment->payment_number = $invoice->invoice_access_key;
                        $invoiceAsPayment->payment_reference = null;
                        $invoiceAsPayment->payment_status = $invoice->status;
                        $invoiceAsPayment->payment_prints = 0;
                        $invoiceAsPayment->fee = null;
                        $invoiceAsPayment->capital = 0;
                        $invoiceAsPayment->interest = 0;
                        $invoiceAsPayment->mora = 0;
                        $invoiceAsPayment->safe = 0;
                        $invoiceAsPayment->management_collection_expenses = floatval($invoice->invoice_value);
                        $invoiceAsPayment->collection_expenses = 0;
                        $invoiceAsPayment->legal_expenses = 0;
                        $invoiceAsPayment->other_values = 0;
                        $invoiceAsPayment->prev_dates = null;
                        $invoiceAsPayment->with_management = null;
                        $invoiceAsPayment->management_auto = null;
                        $invoiceAsPayment->days_past_due_auto = null;
                        $invoiceAsPayment->management_prev = null;
                        $invoiceAsPayment->days_past_due_prev = null;
                        $invoiceAsPayment->post_management = null;
                        $invoiceAsPayment->credit_id = $this->id;
                        $invoiceAsPayment->business_id = $this->business_id;
                        $invoiceAsPayment->campain_id = null;
                        $invoiceAsPayment->campain = null;
                        $invoiceAsPayment->credit = $this;
                        $invoiceAsPayment->created_at = $invoice->created_at;
                        $invoiceAsPayment->updated_at = $invoice->updated_at;
                        $invoiceAsPayment->is_invoice = true;

                        $combined->push($invoiceAsPayment);
                    }
                }

                // Ordenar por payment_date descendente
                return CollectionPaymentResource::collection($combined->sortByDesc('payment_date')->values());
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}