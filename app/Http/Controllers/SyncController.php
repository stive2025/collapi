<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\CollectionSync;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $syncs = CollectionSync::orderBy('updated_at', 'desc')->get();

        return ResponseBase::success($syncs, 'Lista de sincronizaciones obtenida');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $new_sync = CollectionSync::create($request->all());
        return ResponseBase::success($new_sync, 'Sincronización creada correctamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $sync_type = $request->sync_type;

        if($sync_type === 'SYNC-CREDITS'){
            $this->processCreditSync();
        } else if ($sync_type === 'SYNC-PAYMENTS'){
            $this->processPaymentSync();
        } else {
            return ResponseBase::validationError(['sync_type' => 'Tipo de sincronización inválido']);
        }

    }

    private function processCreditSync()
    {
        $sync = CollectionSync::where('sync_type', 'SYNC-CREDITS')
            ->where('state', 'ACTIVE')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$sync) {
            return ResponseBase::notFound('No se encontró una sincronización activa de créditos');
        }else{
            return ResponseBase::success($sync, 'Sincronización de créditos encontrada');
        }
    }

    private function processPaymentSync()
    {
        $sync = CollectionSync::where('sync_type', 'SYNC-PAYMENTS')
            ->where('state', 'ACTIVE')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$sync) {
            return ResponseBase::notFound('No se encontró una sincronización activa de pagos');
        }else{
            return ResponseBase::success($sync, 'Sincronización de pagos encontrada');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $update_sync = CollectionSync::find($id);
        if (!$update_sync) {
            return ResponseBase::notFound('Sincronización no encontrada');
        }else{
            $update_sync->update($request->all());
            return ResponseBase::success($update_sync, 'Sincronización actualizada correctamente');
        }
    }

    /**
     * Sincronizar gastos de cobranza (invoices) desde la API externa
     */
    public function syncInvoices(Request $request)
    {
        try {
            $apiUrl = "https://core.sefil.com.ec/api/public/api/gastos-cobranza";
            $response = file_get_contents($apiUrl);
            $invoicesData = json_decode($response, true);

            if (!is_array($invoicesData)) {
                return ResponseBase::error(
                    'No se obtuvieron gastos de cobranza de la API',
                    [],
                    400
                );
            }

            $totalInvoices = count($invoicesData);
            $syncedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($invoicesData as $index => $invoiceData) {
                try {
                    // Buscar usuario por nombre
                    $user = \App\Models\User::where('name', $invoiceData['byUser'] ?? '')->first();
                    if (!$user) {
                        $errors[] = "Usuario '{$invoiceData['byUser']}' no encontrado en índice {$index}";
                        $skippedCount++;
                        continue;
                    }

                    // Buscar crédito por sync_id
                    $credit = \App\Models\Credit::where('sync_id', $invoiceData['sync_id'] ?? '')->first();
                    if (!$credit) {
                        $errors[] = "Crédito con sync_id '{$invoiceData['sync_id']}' no encontrado en índice {$index}";
                        $skippedCount++;
                        continue;
                    }

                    // Obtener cliente TITULAR del crédito
                    $client = $credit->clients()->where('type', 'TITULAR')->first();
                    if (!$client) {
                        // Si no hay titular, tomar el primer cliente
                        $client = $credit->clients()->first();
                    }

                    if (!$client) {
                        $errors[] = "No se encontró cliente para el crédito con sync_id '{$invoiceData['sync_id']}' en índice {$index}";
                        $skippedCount++;
                        continue;
                    }

                    // Extraer value de postDates
                    $invoiceValue = 0;
                    if (!empty($invoiceData['postDates'])) {
                        $postDates = json_decode($invoiceData['postDates'], true);
                        $invoiceValue = isset($postDates['value']) ? (float)$postDates['value'] : 0;
                    }

                    // Preparar datos para la factura
                    $invoiceDate = !empty($invoiceData['fecha']) ? \Carbon\Carbon::parse($invoiceData['fecha'])->format('Y-m-d') : now()->format('Y-m-d');
                    
                    $invoiceDataToCheck = [
                        'invoice_value' => $invoiceValue,
                        'tax_value' => 15,
                        'invoice_institution' => $invoiceData['financial_institution'] ?? null,
                        'invoice_method' => $invoiceData['pay_way'] ?? null,
                        'invoice_access_key' => $invoiceData['clave_acceso'] ?? null,
                        'invoice_number' => $invoiceData['code_reference'] ?? null,
                        'invoice_date' => $invoiceDate,
                        'credit_id' => $credit->id,
                        'client_id' => $client->id,
                        'status' => $invoiceData['status'] ?? 'pendiente',
                        'created_by' => $user->id,
                    ];

                    // Verificar si ya existe un registro con los mismos datos
                    $existingInvoice = \App\Models\Invoice::where($invoiceDataToCheck)->first();
                    
                    if ($existingInvoice) {
                        $skippedCount++;
                        continue;
                    }

                    // Crear la factura
                    \App\Models\Invoice::create($invoiceDataToCheck);
                    $syncedCount++;

                } catch (\Exception $e) {
                    $errors[] = "Error procesando índice {$index}: {$e->getMessage()}";
                    $skippedCount++;
                    \Illuminate\Support\Facades\Log::error("Error sincronizando invoice en índice {$index}", [
                        'error' => $e->getMessage(),
                        'data' => $invoiceData ?? []
                    ]);
                }
            }

            return ResponseBase::success(
                [
                    'total_invoices' => $totalInvoices,
                    'synced' => $syncedCount,
                    'skipped' => $skippedCount,
                    'errors' => !empty($errors) ? $errors : null
                ],
                "Sincronización completada: {$syncedCount} gastos sincronizados, {$skippedCount} omitidos"
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en sincronización de gastos de cobranza', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al sincronizar gastos de cobranza',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Sincronizar condonaciones desde la API externa
     */
    public function syncCondonations(Request $request)
    {
        try {
            $apiUrl = "https://core.sefil.com.ec/api/public/api/condonations";
            $response = file_get_contents($apiUrl);
            $condonationsData = json_decode($response, true);

            if (!is_array($condonationsData)) {
                return ResponseBase::error(
                    'No se obtuvieron condonaciones de la API',
                    [],
                    400
                );
            }

            $totalCondonations = count($condonationsData);
            $syncedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($condonationsData as $index => $condonationData) {
                try {
                    // Buscar crédito por sync_id
                    $credit = \App\Models\Credit::where('sync_id', $condonationData['sync_id'] ?? '')->first();
                    if (!$credit) {
                        $errors[] = "Crédito con sync_id '{$condonationData['sync_id']}' no encontrado en índice {$index}";
                        $skippedCount++;
                        continue;
                    }

                    // Procesar prev_dates y post_dates para que coincidan con los campos de credits
                    $prevDates = [];
                    $postDates = [];

                    if (!empty($condonationData['prevDates'])) {
                        $prevDatesRaw = json_decode($condonationData['prevDates'], true);
                        if (is_array($prevDatesRaw)) {
                            $prevDates = [
                                'capital' => floatval($prevDatesRaw['capital'] ?? 0),
                                'interest' => floatval($prevDatesRaw['interes'] ?? 0),
                                'mora' => floatval($prevDatesRaw['mora'] ?? 0),
                                'safe' => floatval($prevDatesRaw['seguro_desgravamen'] ?? 0),
                                'collection_expenses' => floatval($prevDatesRaw['gastos_cobranza'] ?? 0),
                                'legal_expenses' => floatval($prevDatesRaw['gastos_judiciales'] ?? 0),
                                'other_values' => floatval($prevDatesRaw['otros_valores'] ?? 0),
                            ];
                        }
                    }

                    if (!empty($condonationData['postDates'])) {
                        $postDatesRaw = json_decode($condonationData['postDates'], true);
                        if (is_array($postDatesRaw)) {
                            $postDates = [
                                'capital' => floatval($postDatesRaw['capital'] ?? 0),
                                'interest' => floatval($postDatesRaw['interes'] ?? 0),
                                'mora' => floatval($postDatesRaw['mora'] ?? 0),
                                'safe' => floatval($postDatesRaw['seguro_desgravamen'] ?? 0),
                                'collection_expenses' => floatval($postDatesRaw['gastos_cobranza'] ?? 0),
                                'legal_expenses' => floatval($postDatesRaw['gastos_judiciales'] ?? 0),
                                'other_values' => floatval($postDatesRaw['otros_valores'] ?? 0),
                            ];
                        }
                    }

                    // Calcular el monto de la condonación (suma de diferencias)
                    $amount = 0;
                    if (!empty($prevDates) && !empty($postDates)) {
                        foreach ($prevDates as $key => $prevValue) {
                            $postValue = $postDates[$key] ?? 0;
                            $amount += ($prevValue - $postValue);
                        }
                    }

                    // Buscar usuario creador si viene byUser
                    $createdBy = null;
                    if (!empty($condonationData['byUser'])) {
                        if (is_array($condonationData['byUser']) && !empty($condonationData['byUser'])) {
                            $userName = is_string($condonationData['byUser'][0]) ? $condonationData['byUser'][0] : null;
                            if ($userName) {
                                $user = \App\Models\User::where('name', $userName)->first();
                                $createdBy = $user ? $user->id : null;
                            }
                        } elseif (is_string($condonationData['byUser'])) {
                            $user = \App\Models\User::where('name', $condonationData['byUser'])->first();
                            $createdBy = $user ? $user->id : null;
                        }
                    }

                    $condonationDataToCheck = [
                        'credit_id' => $credit->id,
                        'amount' => $amount,
                        'prev_dates' => json_encode($prevDates),
                        'post_dates' => json_encode($postDates),
                        'status' => $condonationData['status'] ?? 'pendiente',
                        'created_by' => $createdBy,
                    ];

                    // Verificar si ya existe una condonación similar
                    $existingCondonation = \App\Models\Condonation::where('credit_id', $credit->id)
                        ->where('amount', $amount)
                        ->where('status', $condonationDataToCheck['status'])
                        ->first();

                    if ($existingCondonation) {
                        $skippedCount++;
                        continue;
                    }

                    // Crear la condonación
                    \App\Models\Condonation::create($condonationDataToCheck);
                    $syncedCount++;

                } catch (\Exception $e) {
                    $errors[] = "Error procesando índice {$index}: {$e->getMessage()}";
                    $skippedCount++;
                    \Illuminate\Support\Facades\Log::error("Error sincronizando condonación en índice {$index}", [
                        'error' => $e->getMessage(),
                        'data' => $condonationData ?? []
                    ]);
                }
            }

            return ResponseBase::success(
                [
                    'total_condonations' => $totalCondonations,
                    'synced' => $syncedCount,
                    'skipped' => $skippedCount,
                    'errors' => !empty($errors) ? $errors : null
                ],
                "Sincronización completada: {$syncedCount} condonaciones sincronizadas, {$skippedCount} omitidas"
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en sincronización de condonaciones', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al sincronizar condonaciones',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function syncAgreements(Request $request)
    {
        try {
            // Obtener convenios desde la API externa
            $apiUrl = "https://core.sefil.com.ec/api/public/api/convenios";
            $response = file_get_contents($apiUrl);
            $agreementsData = json_decode($response, true);
            
            if (!is_array($agreementsData)) {
                return ResponseBase::error(
                    'No se obtuvieron convenios de la API',
                    [],
                    400
                );
            }

            $errors = [];
            $success = 0;

            foreach ($agreementsData as $agreementData) {
                try {
                    // Validar que tenga credito (sync_id del crédito)
                    if (empty($agreementData['sync_id'])) {
                        $errors[] = [
                            'sync_id' => $agreementData['sync_id'] ?? 'unknown',
                            'error' => 'credito es requerido'
                        ];
                        continue;
                    }

                    // Buscar el crédito por sync_id o por id
                    $credit = \App\Models\Credit::where('sync_id', $agreementData['sync_id'])
                        ->orWhere('id', $agreementData['sync_id'])
                        ->first();
                    
                    if (!$credit) {
                        $errors[] = [
                            'sync_id' => $agreementData['sync_id'] ?? 'unknown',
                            'credito' => $agreementData['sync_id'] ?? 'unknown',
                            'error' => 'Crédito no encontrado'
                        ];
                        continue;
                    }

                    // Parsear original_dates
                    $originalDatesRaw = [];
                    if (!empty($agreementData['original_dates'])) {
                        $decoded = json_decode($agreementData['original_dates'], true);
                        if (is_array($decoded)) {
                            $originalDatesRaw = $decoded;
                        }
                    }

                    // Parsear detail (cuotas) y convertir al formato correcto
                    $feeDetail = [];
                    if (!empty($agreementData['detail'])) {
                        $decoded = json_decode($agreementData['detail'], true);
                        if (is_array($decoded)) {
                            // Convertir formato del API al formato de la aplicación
                            foreach ($decoded as $fee) {
                                $feeDetail[] = [
                                    'payment_date' => $fee['fecha_pago'] ?? null,
                                    'payment_value' => floatval($fee['valor'] ?? 0),
                                    'payment_amount' => floatval($fee['valor'] ?? 0),
                                    'payment_status' => isset($fee['estado']) ? strtoupper($fee['estado']) : 'PENDIENTE'
                                ];
                            }
                        }
                    }

                    // Buscar usuario creador si viene byUser
                    $createdBy = null;
                    if (!empty($agreementData['byUser'])) {
                        if (is_array($agreementData['byUser']) && !empty($agreementData['byUser'])) {
                            $userName = is_string($agreementData['byUser'][0]) ? $agreementData['byUser'][0] : null;
                            if ($userName) {
                                $user = \App\Models\User::where('name', $userName)->first();
                                $createdBy = $user ? $user->id : null;
                            }
                        } elseif (is_string($agreementData['byUser'])) {
                            $user = \App\Models\User::where('name', $agreementData['byUser'])->first();
                            $createdBy = $user ? $user->id : null;
                        }
                    }

                    // Calcular paid_fees y total_fees desde el detail
                    $totalFees = intval($originalDatesRaw['totalFees'] ?? 0);
                    $paidFees = intval($originalDatesRaw['paidFees'] ?? 0);

                    // Determinar status basado en el status del API
                    // Status válidos: PENDIENTE, AUTORIZADO, ANULADO, DENEGADO, RECHAZADO, REVERTIDO
                    $status = 'PENDIENTE';
                    if (!empty($agreementData['status'])) {
                        $apiStatus = strtolower($agreementData['status']);
                        if ($apiStatus === 'anulado') {
                            $status = 'ANULADO';
                        } elseif ($apiStatus === 'autorizado' || $apiStatus === 'aprobado') {
                            $status = 'AUTORIZADO';
                        } elseif ($apiStatus === 'denegado') {
                            $status = 'DENEGADO';
                        } elseif ($apiStatus === 'rechazado') {
                            $status = 'RECHAZADO';
                        } elseif ($apiStatus === 'revertido') {
                            $status = 'REVERTIDO';
                        }
                    }

                    // Buscar o crear el agreement por credit_id
                    // Nota: invoice_id debe ser nullable en la tabla para permitir sincronización sin invoice
                    $agreementUpdateData = [
                        'total_amount' => floatval($originalDatesRaw['totalAmount'] ?? 0),
                        'total_fees' => $totalFees,
                        'paid_fees' => $paidFees,
                        'fee_amount' => floatval($agreementData['valor_cuota'] ?? 0),
                        'fee_detail' => json_encode($feeDetail),
                        'created_by' => $createdBy,
                        'status' => $status,
                        'concept' => $agreementData['concept'] ?? null,
                    ];

                    $agreement = \App\Models\Agreement::updateOrCreate(
                        [
                            'credit_id' => $credit->id,
                        ],
                        $agreementUpdateData
                    );

                    $success++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'sync_id' => $agreementData['sync_id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return ResponseBase::success(
                [
                    'total' => count($agreementsData),
                    'success' => $success,
                    'errors_count' => count($errors),
                    'errors' => $errors
                ],
                "Sincronización de convenios completada: {$success} sincronizados, " . count($errors) . " errores"
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en syncAgreements: ' . $e->getMessage(), [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al sincronizar convenios',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function updateAgreements(Request $request)
    {
        try {
            // Obtener convenios desde la API externa
            $apiUrl = "https://core.sefil.com.ec/api/public/api/convenios";
            $response = file_get_contents($apiUrl);
            $agreementsData = json_decode($response, true);

            if (!is_array($agreementsData)) {
                return ResponseBase::error(
                    'No se obtuvieron convenios de la API',
                    [],
                    400
                );
            }

            $errors = [];
            $updated = 0;
            $skipped = 0;

            foreach ($agreementsData as $agreementData) {
                try {
                    // Validar que tenga sync_id
                    if (empty($agreementData['sync_id'])) {
                        $errors[] = [
                            'sync_id' => $agreementData['sync_id'] ?? 'unknown',
                            'error' => 'sync_id es requerido'
                        ];
                        continue;
                    }

                    // Buscar el crédito por sync_id
                    $credit = \App\Models\Credit::where('sync_id', $agreementData['sync_id'])->first();

                    if (!$credit) {
                        $errors[] = [
                            'sync_id' => $agreementData['sync_id'],
                            'error' => 'Crédito no encontrado'
                        ];
                        continue;
                    }

                    // Buscar el agreement por credit_id
                    $agreement = \App\Models\Agreement::where('credit_id', $credit->id)->first();

                    if (!$agreement) {
                        $skipped++;
                        continue;
                    }

                    // Desactivar timestamps automáticos para poder establecer valores personalizados
                    $agreement->timestamps = false;

                    // Parsear y actualizar created_at desde el campo 'created_at' del API
                    if (!empty($agreementData['created_at'])) {
                        $createdAt = \Carbon\Carbon::parse($agreementData['created_at']);
                        $agreement->created_at = $createdAt;
                    }

                    // Actualizar updated_at si viene en los datos
                    if (!empty($agreementData['updated_at'])) {
                        $updatedAt = \Carbon\Carbon::parse($agreementData['updated_at']);
                        $agreement->updated_at = $updatedAt;
                    }

                    $agreement->save();

                    // Reactivar timestamps para futuras operaciones
                    $agreement->timestamps = true;

                    $updated++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'sync_id' => $agreementData['sync_id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            return ResponseBase::success(
                [
                    'total' => count($agreementsData),
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'errors_count' => count($errors),
                    'errors' => $errors
                ],
                "Actualización de convenios completada: {$updated} actualizados, {$skipped} omitidos, " . count($errors) . " errores"
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en updateAgreements: ' . $e->getMessage(), [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al actualizar convenios',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Sincronizar clientes desde la API externa para créditos sin relación en client_credit
     */
    public function syncClients(Request $request)
    {
        try {
            $creditsWithoutClients = \App\Models\Credit::whereDoesntHave('clients')->get();

            if ($creditsWithoutClients->isEmpty()) {
                return ResponseBase::success(
                    ['total' => 0, 'synced' => 0, 'skipped' => 0],
                    'No hay créditos sin clientes para sincronizar'
                );
            }

            $totalCredits = $creditsWithoutClients->count();
            $syncedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($creditsWithoutClients as $credit) {
                try {
                    $apiUrl = "https://core.sefil.com.ec/api/public/api/contactosyncs?sync_id={$credit->sync_id}";
                    $response = @file_get_contents($apiUrl);

                    if ($response === false) {
                        $errors[] = "No se pudo obtener datos para sync_id '{$credit->sync_id}'";
                        $skippedCount++;
                        continue;
                    }

                    $clientsData = json_decode($response, true);

                    if (!is_array($clientsData) || empty($clientsData)) {
                        $errors[] = "Sin datos de clientes para sync_id '{$credit->sync_id}'";
                        $skippedCount++;
                        continue;
                    }

                    foreach ($clientsData as $clientData) {
                        $ci = $clientData['documento'] ?? null;
                        if (!$ci) {
                            continue;
                        }

                        $client = \App\Models\Client::where('ci', $ci)->first();

                        if (!$client) {
                            $client = \App\Models\Client::create([
                                'ci' => $ci,
                                'name' => $clientData['fullName'] ?? null,
                                'gender' => $clientData['sexo'] ?? null,
                                'civil_status' => $clientData['estado_civil'] ?? null,
                                'economic_activity' => $clientData['sector_economico'] ?? null,
                            ]);
                        }

                        $existingRelation = \Illuminate\Support\Facades\DB::table('client_credit')
                            ->where('client_id', $client->id)
                            ->where('credit_id', $credit->id)
                            ->exists();

                        if (!$existingRelation) {
                            \Illuminate\Support\Facades\DB::table('client_credit')->insert([
                                'client_id' => $client->id,
                                'credit_id' => $credit->id,
                                'type' => $clientData['type'] ?? 'TITULAR',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        if (!empty($clientData['direccion_domicilio'])) {
                            $direccionDom = json_decode($clientData['direccion_domicilio'], true);
                            if (is_array($direccionDom)) {
                                $existingDomicilio = \App\Models\CollectionDirection::where('client_id', $client->id)
                                    ->where('type', 'DOMICILIO')
                                    ->first();

                                if (!$existingDomicilio) {
                                    \App\Models\CollectionDirection::create([
                                        'client_id' => $client->id,
                                        'address' => $direccionDom['address'] ?? null,
                                        'type' => 'DOMICILIO',
                                        'province' => $direccionDom['province'] ?? null,
                                        'canton' => $direccionDom['canton'] ?? null,
                                        'parish' => $direccionDom['parroquia'] ?? null,
                                        'neighborhood' => $direccionDom['neighborhood'] ?? null,
                                        'latitude' => $direccionDom['latitude'] ?? null,
                                        'longitude' => $direccionDom['length'] ?? null,
                                    ]);
                                }
                            }
                        }

                        if (!empty($clientData['direccion_trabajo'])) {
                            $direccionTrabajo = json_decode($clientData['direccion_trabajo'], true);
                            if (is_array($direccionTrabajo)) {
                                $existingTrabajo = \App\Models\CollectionDirection::where('client_id', $client->id)
                                    ->where('type', 'TRABAJO')
                                    ->first();

                                if (!$existingTrabajo) {
                                    \App\Models\CollectionDirection::create([
                                        'client_id' => $client->id,
                                        'address' => $direccionTrabajo['address'] ?? null,
                                        'type' => 'TRABAJO',
                                        'province' => $direccionTrabajo['province'] ?? null,
                                        'canton' => $direccionTrabajo['canton'] ?? null,
                                        'parish' => $direccionTrabajo['parroquia'] ?? null,
                                        'neighborhood' => $direccionTrabajo['neighborhood'] ?? null,
                                        'latitude' => $direccionTrabajo['latitude'] ?? null,
                                        'longitude' => $direccionTrabajo['length'] ?? null,
                                    ]);
                                }
                            }
                        }

                        if (!empty($clientData['mobile_phones'])) {
                            $phones = explode(',', $clientData['mobile_phones']);
                            foreach ($phones as $phone) {
                                $phone = trim($phone);
                                if (!empty($phone)) {
                                    $existingContact = \App\Models\CollectionContact::where('client_id', $client->id)
                                        ->where('phone_number', $phone)
                                        ->first();

                                    if (!$existingContact) {
                                        \App\Models\CollectionContact::create([
                                            'client_id' => $client->id,
                                            'phone_number' => $phone,
                                            'phone_type' => 'CELULAR',
                                            'phone_status' => 'ACTIVE',
                                        ]);
                                    }
                                }
                            }
                        }

                        if (!empty($clientData['landline_phones'])) {
                            $phones = explode(',', $clientData['landline_phones']);
                            foreach ($phones as $phone) {
                                $phone = trim($phone);
                                if (!empty($phone)) {
                                    $existingContact = \App\Models\CollectionContact::where('client_id', $client->id)
                                        ->where('phone_number', $phone)
                                        ->first();

                                    if (!$existingContact) {
                                        \App\Models\CollectionContact::create([
                                            'client_id' => $client->id,
                                            'phone_number' => $phone,
                                            'phone_type' => 'FIJO',
                                            'phone_status' => 'ACTIVE',
                                        ]);
                                    }
                                }
                            }
                        }
                    }

                    $syncedCount++;

                } catch (\Exception $e) {
                    $errors[] = "Error procesando sync_id '{$credit->sync_id}': {$e->getMessage()}";
                    $skippedCount++;
                    \Illuminate\Support\Facades\Log::error("Error sincronizando clientes para sync_id {$credit->sync_id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return ResponseBase::success(
                [
                    'total_credits' => $totalCredits,
                    'synced' => $syncedCount,
                    'skipped' => $skippedCount,
                    'errors' => !empty($errors) ? $errors : null
                ],
                "Sincronización completada: {$syncedCount} créditos sincronizados, {$skippedCount} omitidos"
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en sincronización de clientes', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al sincronizar clientes',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Sincronizar gastos judiciales desde la API externa
     *
     * @OA\Post(
     *     path="/api/sync/legal-expenses",
     *     summary="Sincronizar gastos judiciales",
     *     description="Sincroniza gastos judiciales desde la API externa",
     *     operationId="syncLegalExpenses",
     *     tags={"Sincronización"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sincronización completada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="synced", type="integer"),
     *                 @OA\Property(property="skipped", type="integer"),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function syncLegalExpenses(Request $request)
    {
        try {
            $apiUrl = "https://core.sefil.com.ec/api/public/api/judicial";
            $response = file_get_contents($apiUrl);
            $expensesData = json_decode($response, true);

            if (!is_array($expensesData)) {
                return ResponseBase::error(
                    'No se obtuvieron gastos judiciales de la API',
                    [],
                    400
                );
            }

            $totalExpenses = count($expensesData);
            $syncedCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($expensesData as $index => $expenseData) {
                try {
                    $syncId = $expenseData['sync_id'] ?? null;
                    if (!$syncId) {
                        $errors[] = "Registro sin sync_id en índice {$index}";
                        $skippedCount++;
                        continue;
                    }

                    $credit = \App\Models\Credit::where('sync_id', $syncId)->first();
                    if (!$credit) {
                        $errors[] = "Crédito con sync_id '{$syncId}' no encontrado en índice {$index}";
                        $skippedCount++;
                        continue;
                    }

                    $createdBy = null;
                    if (!empty($expenseData['byUser'])) {
                        $user = \App\Models\User::where('name', $expenseData['byUser'])->first();
                        $createdBy = $user ? $user->id : null;
                    }

                    $modifyDate = !empty($expenseData['modify'])
                        ? \Carbon\Carbon::parse($expenseData['modify'])->format('Y-m-d')
                        : null;

                    $prevAmount = (float) ($expenseData['prevDate'] ?? 0);
                    $postAmount = (float) ($expenseData['postDate'] ?? 0);
                    $totalValue = (float) ($expenseData['total_value'] ?? 0);
                    $detail = $expenseData['detail'] ?? null;
                    $cartera = $expenseData['cartera'] ?? null;

                    $existingExpense = \App\Models\LegalExpense::where('credit_id', $credit->id)
                        ->where('prev_amount', $prevAmount)
                        ->where('post_amount', $postAmount)
                        ->where('detail', $detail)
                        ->first();

                    if ($existingExpense) {
                        $skippedCount++;
                        continue;
                    }

                    $legalExpense = \App\Models\LegalExpense::create([
                        'credit_id' => $credit->id,
                        'business_id' => $credit->business_id,
                        'created_by' => $createdBy,
                        'modify_date' => $modifyDate,
                        'prev_amount' => $prevAmount,
                        'post_amount' => $postAmount,
                        'detail' => $detail,
                        'total_value' => $totalValue,
                        'sync_id' => $cartera,
                    ]);

                    $legalExpense->timestamps = false;
                    if (!empty($expenseData['created_at'])) {
                        $legalExpense->created_at = \Carbon\Carbon::parse($expenseData['created_at']);
                    }
                    if (!empty($expenseData['updated_at'])) {
                        $legalExpense->updated_at = \Carbon\Carbon::parse($expenseData['updated_at']);
                    }
                    $legalExpense->save();
                    $legalExpense->timestamps = true;

                    $syncedCount++;

                } catch (\Exception $e) {
                    $errors[] = "Error procesando índice {$index}: {$e->getMessage()}";
                    $skippedCount++;
                    \Illuminate\Support\Facades\Log::error("Error sincronizando gasto judicial en índice {$index}", [
                        'error' => $e->getMessage(),
                        'data' => $expenseData ?? []
                    ]);
                }
            }

            return ResponseBase::success(
                [
                    'total' => $totalExpenses,
                    'synced' => $syncedCount,
                    'skipped' => $skippedCount,
                    'errors' => !empty($errors) ? $errors : null
                ],
                "Sincronización completada: {$syncedCount} gastos judiciales sincronizados, {$skippedCount} omitidos"
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en sincronización de gastos judiciales', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al sincronizar gastos judiciales',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
