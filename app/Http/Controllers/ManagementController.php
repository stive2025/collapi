<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagementRequest;
use App\Http\Resources\CollectionCallResource;
use App\Http\Resources\ManagementResource;
use App\Http\Responses\ResponseBase;
use App\Models\CollectionCall;
use App\Models\Management;
use App\Services\WebSocketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        
        $query = Management::query();

        if ($request->filled('state')) {
            $query->where('state', $request->query('state'));
        }

        if ($request->filled('substate')) {
            $query->where('substate', $request->query('substate'));
        }

        if ($request->filled('observation')) {
            $query->where('observation', 'LIKE', '%' . $request->query('observation') . '%');
        }

        if ($request->filled('promise_date')) {
            $query->whereDate('promise_date', $request->query('promise_date'));
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->query('created_by'));
        }

        if ($request->filled('days_past_due')) {
            $query->where('days_past_due', $request->query('days_past_due'));
        }

        if ($request->filled('paid_fees')) {
            $query->where('paid_fees', $request->query('paid_fees'));
        }

        if ($request->filled('pending_fees')) {
            $query->where('pending_fees', $request->query('pending_fees'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->query('client_id'));
        }

        if ($request->filled('client_name')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->query('client_name') . '%');
            });
        }

        if ($request->filled('client_ci')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('ci', $request->query('client_ci'));
            });
        }

        if ($request->filled('credit_id')) {
            $query->where('sync_id','LIKE','%'. $request->query('credit_id').'%');
        }
        
        if ($request->filled('campain_id')) {
            $query->where('campain_id', $request->query('campain_id'));
        }

        $orderBy = $request->query('order_by', 'created_at');
        $orderDir = $request->query('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);
        
        $managements = $query->with([
            'client',
            'credit.clients' => function ($query) {
                $query->select('clients.id', 'clients.name');
            },
            'campain',
            'creator'
        ])->paginate($perPage);

        return ResponseBase::success(
            ManagementResource::collection($managements)->response()->getData(),
            'Gestiones obtenidas correctamente'
        );
    }

    public function indexCallsByManagementID(Request $request, int $management_id)
    {
        $management = Management::where('id', $management_id)->first();

        if (!$management) {
            return ResponseBase::error(
                'Gestión no encontrada',
                [],
                404
            );
        }

        $calls_collection = $management->call_collection;
        $call_ids = json_decode($calls_collection, true);

        if (empty($call_ids) || !is_array($call_ids)) {
            return ResponseBase::success(
                [],
                'No hay llamadas asociadas a esta gestión'
            );
        }

        $calls = CollectionCall::whereIn('id', $call_ids)
            ->with(['contact.client', 'creator'])
            ->get();

        return ResponseBase::success(
            CollectionCallResource::collection($calls),
            'Llamadas obtenidas correctamente'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreManagementRequest $request)
    {
        try {
            $management = Management::create($request->validated());

            $credit = $management->credit;

            if ($credit) {
                $credit->management_status = $management->substate;
                $credit->management_tray = "GESTIONADO";
                $credit->management_promise = $management->promise_date;
                $credit->save();
            }

            // Enviar notificación WebSocket
            try {
                $ws = new WebSocketService();
                $ws->sendManagementUpdate(
                    $management->created_by,
                    $management->campain_id
                );
            } catch (\Exception $wsError) {
                Log::error('WebSocket notification failed after management creation', [
                    'error' => $wsError->getMessage(),
                    'management_id' => $management->id
                ]);
            }

            return ResponseBase::success(
                new ManagementResource($management),
                'Gestión creada correctamente',
                201
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al crear la gestión',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Management $management)
    {
        $management->load([
            'client',
            'credit.clients' => function ($query) {
                $query->select('clients.id', 'clients.name');
            },
            'campain',
            'creator'
        ]);

        return ResponseBase::success(
            new ManagementResource($management),
            'Gestión obtenida correctamente'
        );
    }

    /**
     * Sincronización masiva de gestiones desde la API externa
     */
    public function syncManagements(Request $request)
    {
        try {
            $campainId = $request->campain_id;
            $parseCampainId = $request->parse_campain_id;
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            if (!$startDate || !$endDate) {
                return ResponseBase::error(
                    'Los parámetros start_date y end_date son requeridos',
                    ['start_date' => 'Campo obligatorio', 'end_date' => 'Campo obligatorio'],
                    422
                );
            }

            $parseDateAndAdd5Hours = function($dateString) use (&$debugCounter) {
                static $callCount = 0;
                $callCount++;

                if (empty($dateString)) {
                    if ($callCount <= 2) {
                        Log::warning("parseDateAndAdd5Hours recibió fecha vacía, retornando now()");
                    }
                    return now();
                }

                try {
                    $date = \Carbon\Carbon::parse($dateString);
                    $dateWithOffset = $date->addHours(5);

                    if ($callCount <= 2) {
                        Log::info("parseDateAndAdd5Hours - Input: '{$dateString}', Output: '{$dateWithOffset->toDateTimeString()}'");
                    }

                    return $dateWithOffset;
                } catch (\Exception $e) {
                    Log::error("Error parseando fecha '{$dateString}': {$e->getMessage()}");
                    return now();
                }
            };

            $parsePromiseDate = function($dateString) use ($parseDateAndAdd5Hours) {
                if (empty($dateString) || $dateString === 'N/D' || $dateString === 'n/d') {
                    return null;
                }

                try {
                    return $parseDateAndAdd5Hours($dateString);
                } catch (\Exception $e) {
                    return null;
                }
            };

            $allManagements = [];
            $currentPage = 1;
            $lastPage = 1;

            do {
                $apiUrl = "https://core.sefil.com.ec/api/public/api/managments?campain_id={$campainId}&page={$currentPage}&per_page=100&start_date={$startDate}&end_date={$endDate}";
                $response = file_get_contents($apiUrl);
                $managementsData = json_decode($response, true);

                if (!isset($managementsData['data']['data']) || !is_array($managementsData['data']['data'])) {
                    if ($currentPage === 1) {
                        return ResponseBase::error(
                            'No se obtuvieron gestiones de la API',
                            [],
                            400
                        );
                    }
                    break;
                }

                $allManagements = array_merge($allManagements, $managementsData['data']['data']);

                $lastPage = $managementsData['data']['last_page'] ?? 1;
                $currentPage++;

            } while ($currentPage <= $lastPage);

            $managements = $allManagements;
            $totalManagements = count($managements);
            $syncedCount = 0;
            $callsCache = [];
            $currentCallPage = 1;
            $lastCallPage = 1;

            do {
                try {
                    $callsApiUrl = "https://core.sefil.com.ec/api/public/api/calls?page={$currentCallPage}&per_page=100000";
                    $callsResponse = file_get_contents($callsApiUrl);
                    $callsData = json_decode($callsResponse, true);

                    if (isset($callsData['data']) && is_array($callsData['data'])) {
                        $pageCallsCount = count($callsData['data']);

                        foreach ($callsData['data'] as $call) {
                            if (isset($call['id_call'])) {
                                $callsCache[$call['id_call']] = $call;
                            }
                            if (isset($call['id'])) {
                                $callsCache[$call['id']] = $call;
                            }
                        }

                        $lastCallPage = $callsData['last_page'] ?? 1;
                        $currentCallPage++;
                    } else {
                        Log::warning("No se encontraron datos en página {$currentCallPage}");
                        break;
                    }
                } catch (\Exception $e) {
                    Log::error("Error al obtener página {$currentCallPage} de llamadas: {$e->getMessage()}");
                    break;
                }
            } while ($currentCallPage <= $lastCallPage);

            Log::info("Llamadas cargadas en cache: " . count($callsCache));

            DB::transaction(function () use ($managements, $campainId, $parseCampainId, $parseDateAndAdd5Hours, $parsePromiseDate, $callsCache, &$syncedCount) {
                foreach ($managements as $index => $managementData) {
                    $user = \App\Models\User::where('name', $managementData['byUser'])->first();
                    if (!$user) {
                        Log::warning("Usuario '{$managementData['byUser']}' no encontrado en índice {$index}, se omite");
                        continue;
                    }

                    $client = \App\Models\Client::where('ci', $managementData['client_ci'])->first();
                    if (!$client) {
                        Log::warning("Cliente con CI '{$managementData['client_ci']}' no encontrado en índice {$index}, se omite");
                        continue;
                    }
                    
                    $credit = \App\Models\Credit::where('sync_id', $managementData['sync_id'])->first();

                    if (!$credit) {
                        Log::warning("Crédito con sync_id '{$managementData['sync_id']}' no encontrado en índice {$index}, se omite");
                        continue;
                    }

                    $newCallIds = [];
                    $callIdsExtras = json_decode($managementData['id_calls_extras'], true);

                    if (is_array($callIdsExtras) && !empty($callIdsExtras)) {
                        foreach ($callIdsExtras as $oldCallId) {
                            if (!isset($callsCache[$oldCallId])) {
                                Log::warning("Llamada {$oldCallId} no encontrada en cache, se omite");
                                continue;
                            }

                            $call = $callsCache[$oldCallId];
                            $callUser = \App\Models\User::where('name', $call['byUser'])->first();

                            $contact = null;
                            if (!empty($call['phone_number'])) {
                                $contact = \App\Models\CollectionContact::where('phone', $call['phone_number'])->first();
                            }

                            $newCall = new CollectionCall([
                                'state' => $call['state_call'] ?? 'NO CONTACTADO',
                                'duration' => $call['duration_call'] ?? 0,
                                'media_path' => $call['id_record'] ?? null,
                                'phone_number' => $call['phone'] ?? '',
                                'channel' => $call['channel'] ?? null,
                                'client_id' => $contact ? $contact->client_id : null,
                                'credit_id' => $credit->id,
                                'campain_id' => $parseCampainId,
                                'created_by' => $callUser ? $callUser->id : $user->id,
                            ]);

                            $newCall->created_at = $parseDateAndAdd5Hours($call['fecha'] ?? null);
                            $newCall->updated_at = $parseDateAndAdd5Hours($call['updated_at'] ?? null);
                            $newCall->save();

                            $newCallIds[] = $newCall->id;
                        }
                    }

                    $newManagement = new Management([
                        'state' => $managementData['state_gestion'],
                        'substate' => $managementData['substate_gestion'],
                        'observation' => $managementData['observation'] ?? null,
                        'promise_date' => $parsePromiseDate($managementData['date_promise'] ?? null),
                        'promise_amount' => floatval($managementData['monto_a_pagar']) ?? null,
                        'created_by' => $user->id,
                        'call_id' => null,
                        'call_collection' => json_encode($newCallIds),
                        'days_past_due' => intval($managementData['dias_vencidos']) ?? 0,
                        'paid_fees' => intval($managementData['cuotas_pagadas']) ?? 0,
                        'pending_fees' => intval($managementData['cuotas_pendientes']) ?? 0,
                        'managed_amount' => floatval($managementData['monto']) ?? 0,
                        'nro_notification' => intval($managementData['nro_notification']) ?? null,
                        'client_id' => $client->id,
                        'credit_id' => $credit->id,
                        'campain_id' => $parseCampainId,
                    ]);

                    $newManagement->created_at = $parseDateAndAdd5Hours($managementData['fecha'] ?? null);
                    $newManagement->updated_at = $parseDateAndAdd5Hours($managementData['updated_at'] ?? null);
                    $newManagement->save();

                    $syncedCount++;
                }
            });

            return ResponseBase::success(
                [
                    'total_managements' => $totalManagements,
                    'synced' => $syncedCount
                ],
                "Sincronización completada exitosamente: {$syncedCount} gestiones sincronizadas"
            );

        } catch (\Exception $e) {
            Log::error('Error en sincronización masiva de gestiones', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al sincronizar gestiones',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}