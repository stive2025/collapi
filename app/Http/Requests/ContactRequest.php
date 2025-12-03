<?php

namespace App\Http\Requests;

use App\Http\Responses\ResponseBase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data before validation: set created_by from token when creating.
     */
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('post')) {
            $user = $this->user();
            if ($user) {
                $this->merge([
                    'created_by' => $user->id,
                ]);
            }
        }
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:15'],
            'phone_type' => ['nullable', 'string', 'max:50'],
            'phone_status' => ['nullable', 'string', 'max:50'],
            'calls_effective' => ['nullable', 'integer', 'min:0'],
            'calls_not_effective' => ['nullable', 'integer', 'min:0'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'credit_id' => ['nullable', 'integer', 'exists:credits,id'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
        ];

        if ($this->isMethod('post')) {
            $rules['phone_number'] = array_merge(['required'], (array) $rules['phone_number']);
            $rules['phone_type']   = array_merge(['required'], (array) $rules['phone_type']);
            $rules['client_id']    = array_merge(['required'], (array) $rules['client_id']);
            $rules['created_by']   = ['required','integer','exists:users,id'];
        } else {
            foreach ($rules as $key => $r) {
                $rules[$key] = array_merge(['sometimes'], (array) $r);
            }
        }

        return $rules;
    }
    
    /**
     * Responder con formato ResponseBase en caso de validación fallida.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ResponseBase::validationError($validator->errors()->toArray())
        );
    }

    public function messages(): array
    {
        return [
            'phone_number.required' => 'El número de teléfono es obligatorio.',
            'phone_number.max' => 'El número de teléfono no puede exceder 15 caracteres.',
            'phone_type.required' => 'El tipo de teléfono es obligatorio.',
            'phone_type.max' => 'El tipo de teléfono no puede exceder 50 caracteres.',
            'client_id.required' => 'El cliente es obligatorio.',
            'client_id.exists' => 'El cliente especificado no existe.',
            'created_by.required' => 'El usuario creador no fue encontrado (token inválido).',
            'created_by.exists' => 'El usuario creador especificado no existe.',
            'calls_effective.integer' => 'calls_effective debe ser un número entero.',
            'calls_not_effective.integer' => 'calls_not_effective debe ser un número entero.',
            'credit_id.exists' => 'El crédito especificado no existe.'
        ];
    }
}