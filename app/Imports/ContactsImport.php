<?php

namespace App\Imports;

use App\Models\CollectionContact;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ContactsImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Normalizar valores recibidos
            $rawCi = isset($row['client_ci']) ? trim((string)$row['client_ci']) : '';
            $ci = $rawCi;
            // intentar buscar cliente por CI tal cual
            $client = Client::where('ci', $ci)->first();

            // si no existe, intentar con solo dígitos (por si vienen con guiones/espacios)
            if (!$client) {
                $ciDigits = preg_replace('/\D+/', '', $rawCi);
                if (!empty($ciDigits)) {
                    $client = Client::where('ci', $ciDigits)->first();
                }
            }

            if (!$client) {
                // Si no existe el cliente, registrar y continuar con el siguiente registro
                Log::warning('ContactsImport: cliente no encontrado, fila omitida', [
                    'client_ci' => $rawCi,
                    'phone' => isset($row['phone_number']) ? $row['phone_number'] : null,
                    'row' => is_array($row) ? $row : (array)$row
                ]);

                continue;
            }

            // Normalizar número de teléfono
            $rawPhone = isset($row['phone_number']) ? trim((string)$row['phone_number']) : '';
            $phone = $rawPhone;
            if (empty($phone)) {
                Log::warning('ContactsImport: fila omitida sin phone_number', [
                    'client_ci' => $rawCi,
                    'row' => is_array($row) ? $row : (array)$row
                ]);

                continue; // sin teléfono no tiene sentido crear contacto
            }

            // Crear o actualizar contacto
            $contact = CollectionContact::updateOrCreate(
                [
                    'client_id' => $client->id,
                    'phone_number' => $phone,
                ],
                [
                    'phone_type' => $row['phone_type'] ?? 'MOVIL',
                    'phone_status' => $row['phone_status'] ?? 'ACTIVE',
                    'created_by' => $row['created_by'] ?? null,
                    'updated_by' => $row['updated_by'] ?? null,
                    'deleted_by' => $row['deleted_by'] ?? null,
                ]
            );

            if (isset($contact->wasRecentlyCreated) && $contact->wasRecentlyCreated) {
                Log::info('ContactsImport: contacto creado', [
                    'client_id' => $client->id,
                    'client_ci' => $rawCi,
                    'phone' => $phone,
                    'contact_id' => $contact->id
                ]);
            } else {
                Log::info('ContactsImport: contacto actualizado', [
                    'client_id' => $client->id,
                    'client_ci' => $rawCi,
                    'phone' => $phone,
                    'contact_id' => $contact->id
                ]);
            }

            // Verificar inmediatamente que el registro existe en la BD y loguearlo
            try {
                $found = CollectionContact::find($contact->id);
                Log::debug('ContactsImport: verificacion en DB', [
                    'found' => $found ? $found->toArray() : null,
                    'database' => DB::getDatabaseName(),
                    'contact_id' => $contact->id
                ]);
            } catch (\Exception $e) {
                Log::error('ContactsImport: error al verificar en DB', ['error' => $e->getMessage()]);
            }

            // Si se activa el modo debug por variable de entorno, detener la importación
            if (env('CONTACTS_IMPORT_DEBUG', false)) {
                throw new \Exception('ContactsImport: debug stop after contact_id=' . $contact->id);
            }

            // Verificar lectura desde la base de datos inmediatamente después de guardar
            try {
                $dbName = DB::connection()->getDatabaseName();
            } catch (\Exception $e) {
                $dbName = 'unknown';
            }

            $found = CollectionContact::where('id', $contact->id)->first();

            if ($found) {
                Log::info('ContactsImport: verificación exitosa, registro recuperado', [
                    'contact' => $found->toArray(),
                    'database' => $dbName
                ]);
            } else {
                Log::error('ContactsImport: registro NO encontrado tras guardar', [
                    'expected_contact_id' => $contact->id,
                    'client_id' => $client->id,
                    'client_ci' => $rawCi,
                    'phone' => $phone,
                    'database' => $dbName,
                    'row' => is_array($row) ? $row : (array)$row
                ]);
            }
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
