<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CollectionDirection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ClientsImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Normalizar valores recibidos
            $rowArr = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : (array)$row;

            // Determinar el tipo de cliente
            // Si viene la columna 'type' en el Excel, usar ese valor
            // Si no, usar 'TITULAR' por defecto
            $clientType = isset($rowArr['type']) && !empty($rowArr['type'])
                ? strtoupper(trim($rowArr['type']))
                : 'TITULAR';

            $client = Client::firstOrCreate(
                ['ci' => strval($row['ci'])],
                [
                    'name' => $row['name'],
                    'type' => $clientType,
                    'gender' => $row['gender'] ?? '',
                    'civil_status' => $row['civil_status'] ?? '',
                    'economic_activity' => $row['economic_activity'] ?? '',
                ]
            );

            $credit = Credit::where('sync_id', $row['sync_id'])->first();

            if ($credit) {
                // Usar el tipo del cliente para la relación con el crédito
                $client->credits()->syncWithoutDetaching([
                    $credit->id => ['type' => $clientType]
                ]);
            }

            // Solo procesar contactos si NO viene la columna 'type'
            // (modo de importación completa vs modo de contactos)
            if (!isset($rowArr['type']) && isset($row['contactos']) && !empty($row['contactos'])) {
                $contactos = json_decode($row['contactos'], true);

                if (is_array($contactos)) {
                    foreach ($contactos as $contacto) {
                        if (empty($contacto) || !isset($contacto['ci']) || empty($contacto['ci'])) {
                            continue;
                        }

                        $garante = Client::firstOrCreate(
                            ['ci' => strval($contacto['ci'])],
                            [
                                'name' => $contacto['name'] ?? '',
                                'type' => $contacto['tipo'] ?? '',
                                'gender' => $contacto['genero'] ?? '',
                                'civil_status' => $contacto['estado_civil'] ?? '',
                                'economic_activity' => $contacto['actividad_economica'] ?? '',
                            ]
                        );

                        if ($credit) {
                            $garante->credits()->syncWithoutDetaching([
                                $credit->id => ['type' => 'GARANTE']
                            ]);
                        }

                        if (isset($contacto['direccion']) && !empty($contacto['direccion'])) {
                            CollectionDirection::updateOrCreate(
                                [
                                    'client_id' => $garante->id,
                                    'type' => 'DOMICILIO'
                                ],
                                [
                                    'address' => $contacto['direccion'] ?? '',
                                    'province' => $contacto['provincia'] ?? '',
                                    'canton' => $contacto['canton'] ?? '',
                                    'parish' => $contacto['parroquia'] ?? '',
                                    'neighborhood' => $contacto['barrio'] ?? '',
                                    'latitude' => $contacto['latitud'] ?? null,
                                    'longitude' => $contacto['longitud'] ?? null,
                                ]
                            );
                        }
                    }
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            '*.name' => ['nullable', 'string'],
            '*.ci' => ['required'],
            '*.gender' => ['required', 'string'],
            '*.civil_status' => ['required', 'string'],
            '*.sync_id' => ['required'],
            '*.contactos' => ['nullable', 'json'],
        ];
    }

    public function customValidationMessages()
    {
        return [
            // '*.credito_id.exists' => 'El crédito especificado no existe.',
        ];
    }
}