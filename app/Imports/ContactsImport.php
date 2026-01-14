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
            // Normalizar valores recibidos y aceptar múltiples nombres de columna
            $rowArr = $row instanceof \Illuminate\Support\Collection ? $row->toArray() : (array)$row;

            $rawCi = $this->getRowValue($rowArr, ['client_ci', 'ci', 'cedula', 'cédula']);
            $ci = $rawCi;

            // intentar buscar cliente por CI tal cual
            $client = $rawCi ? Client::where('ci', $ci)->first() : null;

            // si no existe, intentar con solo dígitos (por si vienen con guiones/espacios)
            if (!$client) {
                $ciDigits = preg_replace('/\D+/', '', $rawCi);
                if (!empty($ciDigits)) {
                    $client = Client::where('ci', $ciDigits)->first();
                }
            }

            if (!$client) {
                // Si no existe el cliente, crear uno nuevo con CI y nombre (si viene)
                $ciToUse = $rawCi;
                // fallback to digits-only CI if available
                $ciDigits = $rawCi ? preg_replace('/\D+/', '', $rawCi) : '';
                if (empty($ciToUse) && !empty($ciDigits)) {
                    $ciToUse = $ciDigits;
                }

                $clientName = $this->getRowValue($rowArr, ['client_name', 'name', 'nombre']) ?? 'SIN NOMBRE';

                try {
                    $client = Client::create([
                        'name' => $clientName,
                        'ci' => $ciToUse,
                    ]);

                    Log::info('ContactsImport: cliente creado automaticamente', [
                        'client_ci' => $ciToUse,
                        'client_name' => $clientName,
                        'client_id' => $client->id
                    ]);
                } catch (\Exception $e) {
                    Log::error('ContactsImport: error al crear cliente automaticamente', [
                        'client_ci' => $ciToUse,
                        'client_name' => $clientName,
                        'error' => $e->getMessage(),
                        'row' => $rowArr
                    ]);

                    // Si no pudimos crear el cliente, omitimos la fila
                    continue;
                }
            }

            // Normalizar número de teléfono (acepta varios nombres de columna)
            $rawPhone = $this->getRowValue($rowArr, ['phone_number', 'phone', 'telefono', 'telefono_contacto']);
            if (empty($rawPhone)) {
                Log::warning('ContactsImport: fila omitida sin phone_number', [
                    'client_ci' => $rawCi,
                    'row' => $rowArr
                ]);

                continue; // sin teléfono no tiene sentido crear contacto
            }

            // Normalizar a solo dígitos (mantener prefijo + si existe)
            $phoneDigits = preg_replace('/[^0-9]+/', '', (string)$rawPhone);
            if (empty($phoneDigits)) {
                Log::warning('ContactsImport: telefono normalizado vacío, fila omitida', [
                    'client_ci' => $rawCi,
                    'raw_phone' => $rawPhone,
                    'row' => $rowArr
                ]);

                continue;
            }

            // Intentar preservar o reconstruir un cero inicial perdido por Excel
            $phone = $phoneDigits;

            // Si el rawPhone original es string y comienza con 0, preserve
            $rawIsStringWithZero = is_string($rawPhone) && preg_match('/^0+\d+$/', $rawPhone);
            if ($rawIsStringWithZero) {
                // Ensure preserved leading zeros from original string
                $phone = $rawPhone;
            } else {
                // Heurística: si quedaron 8 dígitos, es probable que falte un 0 inicial (móvil URUY: 9 dígitos)
                if (strlen($phoneDigits) === 8) {
                    $phone = '0' . $phoneDigits;
                    Log::info('ContactsImport: agregado cero inicial al telefono reconstruido', [
                        'original' => $rawPhone,
                        'normalized' => $phone,
                        'client_ci' => $rawCi
                    ]);
                }
            }

            // Crear o actualizar contacto
            $contact = CollectionContact::updateOrCreate(
                [
                    'client_id' => $client->id,
                    'phone_number' => $phone,
                ],
                [
                    'phone_type' => $this->getRowValue($rowArr, ['phone_type']) ?? 'MOVIL',
                    'phone_status' => $this->getRowValue($rowArr, ['phone_status']) ?? 'ACTIVE',
                    'created_by' => $this->getRowValue($rowArr, ['created_by']) ?? null,
                    'updated_by' => $this->getRowValue($rowArr, ['updated_by']) ?? null,
                    'deleted_by' => $this->getRowValue($rowArr, ['deleted_by']) ?? null,
                ]
            );

            if (!empty($contact->wasRecentlyCreated)) {
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

            // (Removed) no recuperar el contacto inmediatamente después de crearlo.

            // Si se activa el modo debug por variable de entorno, detener la importación
            if (env('CONTACTS_IMPORT_DEBUG', false)) {
                throw new \Exception('ContactsImport: debug stop after contact_id=' . $contact->id);
            }

            // (Removed) skip post-save retrieval to avoid extra DB read.
        }
    }

    /**
     * Get the first non-empty value from the row for a list of possible keys.
     */
    private function getRowValue(array $row, array $keys)
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null && trim((string)$row[$k]) !== '') {
                return trim((string)$row[$k]);
            }
        }

        return null;
    }

    public function rules(): array
    {
        return [
            // Allow phone_number to be numeric or string (Excel may provide it as numeric cell)
            '*.phone_number' => ['required'],
            '*.phone_type' => ['nullable', 'string'],
            '*.phone_status' => ['nullable', 'string'],
            '*.created_by' => ['nullable', 'integer'],
            '*.updated_by' => ['nullable', 'integer'],
            '*.deleted_by' => ['nullable', 'integer'],
            '*.client_ci' => ['required'],
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
