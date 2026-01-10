<?php

namespace App\Imports;

use App\Models\CollectionContact;
use App\Models\Client;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ContactsImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Buscar cliente por CI
            $client = Client::where('ci', strval($row['client_ci']))->first();

            if (!$client) {
                // Si no existe el cliente, continuar con el siguiente registro
                continue;
            }

            // Crear o actualizar contacto
            CollectionContact::updateOrCreate(
                [
                    'client_id' => $client->id,
                    'phone_number' => $row['phone_number'],
                ],
                [
                    'phone_type' => $row['phone_type'] ?? 'MOVIL',
                    'phone_status' => $row['phone_status'] ?? 'ACTIVE',
                    'created_by' => $row['created_by'] ?? null,
                    'updated_by' => $row['updated_by'] ?? null,
                    'deleted_by' => $row['deleted_by'] ?? null,
                ]
            );
        }
    }

    public function rules(): array
    {
        return [
            '*.phone_number' => ['required', 'string'],
            '*.phone_type' => ['nullable', 'string'],
            '*.phone_status' => ['nullable', 'string'],
            '*.created_by' => ['nullable', 'integer'],
            '*.updated_by' => ['nullable', 'integer'],
            '*.deleted_by' => ['nullable', 'integer'],
            '*.client_ci' => ['required', 'string'],
        ];
    }

    public function customValidationMessages()
    {
        return [
            '*.phone_number.required' => 'El número de teléfono es obligatorio',
            '*.client_ci.required' => 'La cédula del cliente es obligatoria',
        ];
    }
}
