<?php

namespace App\Http\Requests;

use App\Http\Responses\ResponseBase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreCollectionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('post')) {
            $user = $this->user();
            if (!$user) {
                throw new HttpResponseException(ResponseBase::unauthorized('Token inválido o expirado'));
            }

            $this->merge([
                'created_by' => $user->id,
            ]);
        }
    }

    public function rules(): array
    {
        $financialInstitution = $this->input('financial_institution');

        return [
            'payment_date' => ['nullable', 'date'],
            'payment_deposit_date' => ['nullable', 'date'],
            'payment_value' => ['required', 'numeric'],
            'payment_difference' => ['nullable', 'numeric'],
            'payment_type' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', 'string', 'max:100'],
            'financial_institution' => ['nullable', 'string', 'max:255'],
            'payment_reference' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('collection_payments', 'payment_reference')->where(function ($query) use ($financialInstitution) {
                    if ($financialInstitution === null) {
                        return $query->whereNull('financial_institution');
                    }
                    return $query->where('financial_institution', $financialInstitution);
                }),
            ],
            'payment_status' => ['nullable', 'string', 'max:100'],
            'payment_prints' => ['nullable', 'integer'],

            'fee' => ['nullable', 'numeric'],
            'capital' => ['required', 'numeric'],
            'interest' => ['required', 'numeric'],
            'mora' => ['required', 'numeric'],
            'safe' => ['nullable', 'numeric'],
            'management_collection_expenses' => ['nullable', 'numeric'],
            'collection_expenses' => ['nullable', 'numeric'],
            'legal_expenses' => ['nullable', 'numeric'],
            'other_values' => ['required', 'numeric'],

            'prev_dates' => ['nullable', 'string'],

            'with_management' => ['nullable', 'string', 'max:255'],
            'management_auto' => ['nullable', 'integer'],
            'days_past_due_auto' => ['nullable', 'integer'],
            'management_prev' => ['nullable', 'integer'],
            'days_past_due_prev' => ['nullable', 'integer'],
            'post_management' => ['nullable', 'string'],

            'credit_id' => ['required', 'integer', 'exists:credits,id'],
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
            'campain_id' => ['required', 'integer', 'exists:campains,id'],
            'created_by' => ['required', 'integer', 'exists:users,id'],
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
            'payment_value.required' => 'El valor del pago es obligatorio.',
            'payment_type.required' => 'El tipo de pago es obligatorio.',
            'payment_method.required' => 'El método de pago es obligatorio.',
            'capital.required' => 'El capital es obligatorio.',
            'interest.required' => 'El interés es obligatorio.',
            'mora.required' => 'La mora es obligatoria.',
            'other_values.required' => 'Los otros valores son obligatorios.',
            'credit_id.required' => 'El crédito es obligatorio.',
            'credit_id.exists' => 'El crédito especificado no existe.',
            'business_id.required' => 'La empresa es obligatoria.',
            'business_id.exists' => 'La empresa especificada no existe.',
            'campain_id.required' => 'La campaña es obligatoria.',
            'campain_id.exists' => 'La campaña especificada no existe.',
            'created_by.required' => 'El usuario creador no fue encontrado (token inválido).',
            'created_by.exists' => 'El usuario creador especificado no existe.',
            'payment_reference.unique' => 'La referencia de pago ya existe para la institución financiera especificada.',
            'payment_deposit_date.date' => 'La fecha de depósito de pago no es una fecha válida.',
        ];
    }
}