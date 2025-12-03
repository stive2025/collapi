<?php

namespace App\Http\Requests;

use App\Http\Responses\ResponseBase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCollectionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['sometimes', 'date'],
            'payment_value' => ['sometimes', 'numeric'],
            'payment_difference' => ['nullable', 'numeric'],
            'payment_type' => ['sometimes', 'string', 'max:100'],
            'payment_method' => ['sometimes', 'string', 'max:100'],
            'financial_institution' => ['nullable', 'string', 'max:255'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['sometimes', 'string', 'max:100'],
            'payment_prints' => ['sometimes', 'integer'],

            'fee' => ['nullable', 'numeric'],
            'capital' => ['sometimes', 'numeric'],
            'interest' => ['sometimes', 'numeric'],
            'mora' => ['sometimes', 'numeric'],
            'safe' => ['nullable', 'numeric'],
            'management_collection_expenses' => ['nullable', 'numeric'],
            'collection_expenses' => ['nullable', 'numeric'],
            'legal_expenses' => ['nullable', 'numeric'],
            'other_values' => ['sometimes', 'numeric'],

            'prev_dates' => ['nullable', 'string'],

            'with_management' => ['nullable', 'string', 'max:255'],
            'management_auto' => ['nullable', 'integer'],
            'days_past_due_auto' => ['nullable', 'integer'],
            'management_prev' => ['nullable', 'integer'],
            'days_past_due_prev' => ['nullable', 'integer'],
            'post_management' => ['nullable', 'string'],

            'credit_id' => ['sometimes', 'integer', 'exists:credits,id'],
            'business_id' => ['sometimes', 'integer', 'exists:businesses,id'],
            'campain_id' => ['sometimes', 'integer', 'exists:campains,id'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ResponseBase::validationError($validator->errors()->toArray())
        );
    }

    public function messages(): array
    {
        return [
            'payment_date.date' => 'La fecha de pago debe ser una fecha válida.',
            'payment_value.numeric' => 'El valor del pago debe ser numérico.',
            'credit_id.exists' => 'El crédito especificado no existe.',
            'business_id.exists' => 'La empresa especificada no existe.',
            'campain_id.exists' => 'La campaña especificada no existe.',
        ];
    }
}