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
    
    public function rules(): array
    {
        return [
            'credit_id' => ['required', 'integer', 'exists:credits,id'],
            'client_id' => [
                'required',
                'integer',
                'exists:clients,id',
            ],
            'phone_number' => ['required', 'string', 'max:20'],
            'state' => ['required', 'string', 'max:50'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'media_path' => ['nullable', 'string', 'max:255'],
            'channel' => ['required', 'string', 'max:100'],
            'created_by' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get validated data with created_by from authenticated user
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if (is_array($validated) && !isset($validated['created_by'])) {
            $user = $this->user();
            if ($user) {
                $validated['created_by'] = $user->id;
            }
        }

        return $validated;
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
            'client_id.required' => 'El cliente es obligatorio.',
            'client_id.exists' => 'El cliente no existe o no pertenece al crédito especificado.',
            'phone_number.required' => 'El número de teléfono es obligatorio.',
            'phone_number.max' => 'El número de teléfono no puede exceder 20 caracteres.',
            'state.required' => 'El estado de la llamada es obligatorio.',
            'state.max' => 'El estado no puede exceder 50 caracteres.',
            'duration.integer' => 'La duración debe ser un número entero.',
            'duration.min' => 'La duración no puede ser negativa.',
            'media_path.max' => 'La ruta del archivo no puede exceder 255 caracteres.',
            'channel.required' => 'El canal es obligatorio.',
            'channel.max' => 'El canal no puede exceder 100 caracteres.',
            'created_by.exists' => 'El usuario creador especificado no existe.',
        ];
    }
}