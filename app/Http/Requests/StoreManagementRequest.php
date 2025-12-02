<?php

namespace App\Http\Requests;

use App\Http\Responses\ResponseBase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'state' => ['required', 'string', 'max:100'],
            'substate' => ['required', 'string', 'max:100'],
            'observation' => ['nullable', 'string'],
            'promise_date' => ['required', 'date'],
            'promise_amount' => ['nullable', 'numeric', 'min:0'],
            'created_by' => ['required', 'integer', 'exists:users,id'],
            'call_id' => ['nullable', 'integer', 'exists:collection_calls,id'],
            'call_collection' => ['nullable', 'string', 'max:255'],
            'days_past_due' => ['required', 'integer', 'min:0'],
            'paid_fees' => ['required', 'integer', 'min:0'],
            'pending_fees' => ['required', 'integer', 'min:0'],
            'managed_amount' => ['nullable', 'numeric', 'min:0'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'credit_id' => ['required', 'integer', 'exists:credits,id'],
            'campain_id' => ['required', 'integer', 'exists:campains,id']
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ResponseBase::validationError($validator->errors()->toArray())
        );
    }
    
    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'El cliente es obligatorio.',
            'client_id.exists' => 'El cliente especificado no existe o no pertenece al crédito.',
            'credit_id.required' => 'El crédito es obligatorio.',
            'credit_id.exists' => 'El crédito especificado no existe o no pertenece a la campaña.',
            'campain_id.required' => 'La campaña es obligatoria.',
            'campain_id.exists' => 'La campaña especificada no existe.',
            'created_by.required' => 'El usuario creador es obligatorio.',
            'created_by.exists' => 'El usuario especificado no existe.',
            'call_id.exists' => 'La llamada especificada no existe.',
            'state.required' => 'El estado es obligatorio.',
            'substate.required' => 'El subestado es obligatorio.',
            'promise_date.required' => 'La fecha de promesa es obligatoria.',
        ];
    }
}