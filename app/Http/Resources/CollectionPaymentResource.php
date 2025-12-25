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
        return [
            'id' => $this->id,
            'created_by' => $this->created_by,
            'payment_date' => $this->payment_date?->format('Y/m/d H:i:s'),
            'payment_deposit_date' => $this->payment_deposit_date ? (is_string($this->payment_deposit_date) ? $this->payment_deposit_date : $this->payment_deposit_date->format('Y/m/d H:i:s')) : null,
            'payment_value' => $this->payment_value,
            'payment_difference' => $this->payment_difference,
            'payment_type' => $this->payment_type,
            'payment_method' => $this->payment_method,
            'financial_institution' => $this->financial_institution,
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
            'created_at' => $this->created_at?->format('Y/m/d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y/m/d H:i:s'),
        ];
    }
}
