<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Credit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ClientsImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Buscar o crear cliente por CI
            $client = Client::firstOrCreate(
                ['ci' => $row['ci']],
                [
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'gender' => $row['gender'],
                    'civil_status' => $row['civil_status'],
                    'economic_activity' => $row['economic_activity'],
                ]
            );

            // Verificar si el crédito existe
            $credit = Credit::where('sync_id',$row['credit_id'])->first();

            if ($credit) {
                // Asociar el crédito al cliente sin duplicar
                $client->credits()->syncWithoutDetaching([$credit->id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            '*.name' => ['nullable', 'string'],
            '*.ci' => ['required', 'string'],
            '*.type' => ['required', 'string'],
            '*.gender' => ['required', 'string'],
            '*.civil_status' => ['required', 'string'],
            // '*.economic_activity' => ['required', 'string'],
            // '*.credito_id' => ['required', 'exists:credits,id'],
        ];
    }

    public function customValidationMessages()
    {
        return [
            // '*.credito_id.exists' => 'El crédito especificado no existe.',
        ];
    }
}