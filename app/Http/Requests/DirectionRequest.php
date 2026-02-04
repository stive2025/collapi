<?php

namespace App\Http\Requests;

use App\Http\Responses\ResponseBase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class DirectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'type' => ['nullable', 'string', 'max:50'],
            'province' => ['nullable', 'string', 'max:100'],
            'canton' => ['nullable', 'string', 'max:100'],
            'parish' => ['nullable', 'string', 'max:100'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'string', 'max:50'],
            'longitude' => ['nullable', 'string', 'max:50'],
        ];

        if ($this->isMethod('post')) {
            $rules['client_id'] = array_merge(['required'], (array) $rules['client_id']);
            $rules['direction'] = array_merge(['required'], (array) $rules['direction']);
            $rules['type'] = array_merge(['required'], (array) $rules['type']);
        } else {
            foreach ($rules as $key => $r) {
                $rules[$key] = array_merge(['sometimes'], (array) $r);
            }
        }

        return $rules;
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
            'client_id.required' => 'El cliente es obligatorio.',
            'client_id.exists' => 'El cliente especificado no existe.',
            'address.required' => 'La dirección es obligatoria.',
            'address.max' => 'La dirección no puede exceder 500 caracteres.',
            'type.required' => 'El tipo de dirección es obligatorio.',
            'type.max' => 'El tipo no puede exceder 50 caracteres.',
            'province.max' => 'La provincia no puede exceder 100 caracteres.',
            'canton.max' => 'El cantón no puede exceder 100 caracteres.',
            'parish.max' => 'La parroquia no puede exceder 100 caracteres.',
            'neighborhood.max' => 'El barrio no puede exceder 255 caracteres.',
        ];
    }
}
