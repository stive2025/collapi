<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Si es una invoice transformada (objeto stdClass)
        if (isset($this->is_invoice) && $this->is_invoice) {
            return [
                'id' => $this->id,
                'created_by' => $this->created_by,
                'payment_date' => $this->payment_date,
                'payment_deposit_date' => null,
                'payment_value' => $this->payment_value,
                'payment_difference' => $this->payment_difference,
                'payment_type' => $this->payment_type,
                'payment_method' => $this->payment_method,
                'financial_institution' => $this->financial_institution,
                'payment_number' => $this->payment_number,
                'payment_reference' => $this->payment_reference,
                'payment_status' => $this->payment_status,
                'payment_prints' => $this->payment_prints,
                'fee' => $this->fee,
                'capital' => $this->capital,
                'interest' => $this->interest,
                'mora' => $this->mora,
                'safe' => $this->safe,
                'management_collection_expenses' => $this->management_collection_expenses,
                'collection_expenses' => $this->collection_expenses,
                'legal_expenses' => $this->legal_expenses,
                'other_values' => $this->other_values,
                'prev_dates' => $this->prev_dates,
                'with_management' => $this->with_management,
                'management_auto' => $this->management_auto,
                'days_past_due_auto' => $this->days_past_due_auto,
                'management_prev' => $this->management_prev,
                'days_past_due_prev' => $this->days_past_due_prev,
                'post_management' => $this->post_management,
                'credit_id' => $this->credit_id,
                'business_id' => $this->business_id,
                'campain_id' => $this->campain_id,
                'campain_name' => $this->campain?->name,
                'sync_id' => $this->credit?->sync_id,
                'client_name' => $this->credit?->clients()->wherePivot('type', 'TITULAR')->first()?->name ?? $this->credit?->clients()->first()?->name,
                'client_ci' => $this->credit?->clients()->wherePivot('type', 'TITULAR')->first()?->ci ?? $this->credit?->clients()->first()?->ci,
                'created_at' => $this->created_at?->format('Y/m/d H:i:s'),
                'updated_at' => $this->updated_at?->format('Y/m/d H:i:s'),
                'is_invoice' => true,
            ];
        }
        
        $paymentDate = $this->payment_date;
        if (in_array($this->business_id, [1, 2]) && $paymentDate) {
            $paymentDate = $paymentDate->copy()->subHours(5);
        }

        $data = [
            'id' => $this->id,
            'created_by' => $this->created_by,
            'payment_date' => $paymentDate?->format('Y/m/d H:i:s'),
            'payment_deposit_date' => $this->payment_deposit_date ? (is_string($this->payment_deposit_date) ? $this->payment_deposit_date : $this->payment_deposit_date->format('Y/m/d H:i:s')) : null,
            'payment_value' => $this->payment_value,
            'payment_difference' => $this->payment_difference,
            'payment_type' => $this->payment_type,
            'payment_method' => $this->payment_method,
            'financial_institution' => $this->financial_institution,
            'payment_number' => $this->payment_number,
            'payment_reference' => $this->payment_reference,
            'payment_status' => $this->payment_status,
            'payment_prints' => $this->payment_prints,
            'fee' => $this->fee,
            'capital' => $this->capital,
            'interest' => $this->interest,
            'mora' => $this->mora,
            'safe' => $this->safe,
            'management_collection_expenses' => $this->management_collection_expenses,
            'collection_expenses' => $this->collection_expenses,
            'legal_expenses' => $this->legal_expenses,
            'other_values' => $this->other_values,
            'prev_dates' => $this->prev_dates,
            'with_management' => $this->with_management,
            'management_auto' => $this->management_auto,
            'days_past_due_auto' => $this->days_past_due_auto,
            'management_prev' => $this->management_prev,
            'days_past_due_prev' => $this->days_past_due_prev,
            'post_management' => $this->post_management,
            'credit_id' => $this->credit_id,
            'business_id' => $this->business_id,
            'campain_id' => $this->campain_id,
            'campain_name' => $this->campain?->name,

            // Información del crédito
            'sync_id' => $this->credit?->sync_id,

            // Información del cliente (TITULAR o primer cliente)
            'client_name' => $this->getClientName(),
            'client_ci' => $this->getClientCi(),

            'created_at' => $this->created_at?->format('Y/m/d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y/m/d H:i:s'),
        ];

        // Si el pago tiene error de suma, añadir rubros del crédito y los rubros del pago que se restarán
        if ($this->payment_status === 'ERROR_SUM') {
            $credit = $this->credit;

            $creditRubros = null;
            if ($credit) {
                $creditRubros = [
                    'capital' => $credit->capital,
                    'interest' => $credit->interest,
                    'mora' => $credit->mora,
                    'safe' => $credit->safe,
                    'collection_expenses' => $credit->collection_expenses,
                    'legal_expenses' => $credit->legal_expenses,
                    'management_collection_expenses' => $credit->management_collection_expenses,
                    'other_values' => $credit->other_values,
                    'total_amount' => $credit->total_amount,
                    'collection_state' => $credit->collection_state,
                ];
            }

            $rubros = [
                'capital',
                'interest',
                'mora',
                'safe',
                'collection_expenses',
                'legal_expenses',
                'management_collection_expenses',
                'other_values'
            ];

            $rubrosToSubtract = [];
            foreach ($rubros as $rubro) {
                $value = floatval($this->{$rubro} ?? 0);
                if ($value > 0) {
                    $rubrosToSubtract[$rubro] = $value;
                }
            }

            $data['credit_current_rubros'] = $creditRubros;
            $data['payment_rubros_to_subtract'] = $rubrosToSubtract;
        }

        return $data;
    }

    /**
     * Obtiene el nombre del cliente titular o el primer cliente disponible
     */
    private function getClientName(): ?string
    {
        if (!$this->credit) {
            return null;
        }

        // Buscar cliente titular
        $mainClient = $this->credit->clients()->wherePivot('type', 'TITULAR')->first();

        if ($mainClient) {
            return $mainClient->name;
        }

        // Si no hay titular, obtener el primer cliente
        $firstClient = $this->credit->clients()->first();

        return $firstClient?->name;
    }

    /**
     * Obtiene la cédula del cliente titular o el primer cliente disponible
     */
    private function getClientCi(): ?string
    {
        if (!$this->credit) {
            return null;
        }

        // Buscar cliente titular
        $mainClient = $this->credit->clients()->wherePivot('type', 'TITULAR')->first();

        if ($mainClient) {
            return $mainClient->ci;
        }

        // Si no hay titular, obtener el primer cliente
        $firstClient = $this->credit->clients()->first();

        return $firstClient?->ci;
    }
}
