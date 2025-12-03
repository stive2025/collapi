<?php

namespace App\Http\Requests;

use App\Http\Responses\ResponseBase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCallRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data for validation: set created_by from authenticated token.
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'credit_id' => ['required', 'integer', 'exists:credits,id'],
            'contact_id' => [
                'required',
                'integer',
            ],
            'state' => ['required', 'string', 'max:50'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'media_path' => ['nullable', 'string', 'max:255'],
            'channel' => ['required', 'string', 'max:100'],
            'created_by' => ['required', 'integer', 'exists:users,id'],
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
            'credit_id.required' => 'El crédito es obligatorio.',
            'credit_id.exists' => 'El crédito especificado no existe.',
            'contact_id.required' => 'El contacto es obligatorio.',
            'contact_id.exists' => 'El contacto no existe o no pertenece al crédito especificado.',
            'state.required' => 'El estado de la llamada es obligatorio.',
            'state.max' => 'El estado no puede exceder 50 caracteres.',
            'duration.integer' => 'La duración debe ser un número entero.',
            'duration.min' => 'La duración no puede ser negativa.',
            'media_path.max' => 'La ruta del archivo no puede exceder 255 caracteres.',
            'channel.required' => 'El canal es obligatorio.',
            'channel.max' => 'El canal no puede exceder 100 caracteres.',
            'created_by.required' => 'El usuario creador no fue encontrado (token inválido).',
            'created_by.exists' => 'El usuario creador especificado no existe.',
        ];
    }
}