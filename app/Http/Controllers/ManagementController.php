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
     * 
     * @OA\Get(
     *     path="/api/managements",
     *     summary="Listar gestiones",
     *     description="Obtiene una lista paginada de gestiones con filtros opcionales",
     *     operationId="getManagementsList",
     *     tags={"Gestiones"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Número de gestiones por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="state",
     *         in="query",
     *         description="Filtrar por estado de la gestión",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="substate",
     *         in="query",
     *         description="Filtrar por subestado de la gestión",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="observation",
     *         in="query",
     *         description="Buscar en las observaciones",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="promise_date",
     *         in="query",
     *         description="Filtrar por fecha de promesa de pago (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Fecha inicial del rango (YYYY-MM-DD o YYYY/MM/DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Fecha final del rango (YYYY-MM-DD o YYYY/MM/DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="created_by",
     *         in="query",
     *         description="Filtrar por ID del usuario que creó la gestión",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="client_id",
     *         in="query",
     *         description="Filtrar por ID del cliente",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="client_name",
     *         in="query",
     *         description="Buscar por nombre del cliente",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="client_ci",
     *         in="query",
     *         description="Buscar por cédula del cliente",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="credit_id",
     *         in="query",
     *         description="Buscar por sync_id del crédito",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="campain_id",
     *         in="query",
     *         description="Filtrar por ID de campaña",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="order_by",
     *         in="query",
     *         description="Campo para ordenar",
     *         required=false,
     *         @OA\Schema(type="string", default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="order_dir",
     *         in="query",
     *         description="Dirección del ordenamiento",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de gestiones obtenida correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Gestiones obtenidas correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="state", type="string"),
     *                         @OA\Property(property="substate", type="string"),
     *                         @OA\Property(property="observation", type="string"),
     *                         @OA\Property(property="promise_date", type="string", format="date-time"),
     *                         @OA\Property(property="promise_amount", type="number"),
     *                         @OA\Property(property="created_by", type="integer"),
     *                         @OA\Property(property="client_id", type="integer"),
     *                         @OA\Property(property="credit_id", type="integer"),
     *                         @OA\Property(property="campain_id", type="integer")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
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

        // Filtrar por rango de fechas de creación (acepta formatos YY/MM/DD y YY-MM-DD)
        if ($request->filled('start_date')) {
            $start = $this->parseIncomingDate($request->query('start_date'));
            if ($start) {
                $query->whereDate('created_at', '>=', $start);
            }
        }

        if ($request->filled('end_date')) {
            $end = $this->parseIncomingDate($request->query('end_date'));
            if ($end) {
                $query->whereDate('created_at', '<=', $end);
            }
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
            $syncIdSearch = $request->query('credit_id');
            
            // Si contiene "-", extraer solo lo que está después del "-"
            if (strpos($syncIdSearch, '-') !== false) {
                $parts = explode('-', $syncIdSearch);
                $syncIdSearch = end($parts);
            }
            
            // Buscar el crédito por sync_id
            $credit = \App\Models\Credit::where('sync_id', 'LIKE', '%' . $syncIdSearch . '%')->first();
            
            if ($credit) {
                $query->where('credit_id', $credit->id);
            } else {
                // Si no se encuentra, no retornar ningún resultado
                $query->whereRaw('1 = 0');
            }
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
     * 
     * @OA\Post(
     *     path="/api/managements",
     *     summary="Crear una nueva gestión",
     *     description="Crea una nueva gestión y actualiza el estado del crédito asociado",
     *     operationId="createManagement",
     *     tags={"Gestiones"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"state", "substate", "created_by", "credit_id"},
     *             @OA\Property(property="state", type="string", example="CONTACTADO"),
     *             @OA\Property(property="substate", type="string", example="COMPROMISO_PAGO"),
     *             @OA\Property(property="observation", type="string", example="Cliente acepta realizar pago"),
     *             @OA\Property(property="promise_date", type="string", format="date-time", example="2026-01-25T10:00:00Z"),
     *             @OA\Property(property="promise_amount", type="number", example=150.50),
     *             @OA\Property(property="created_by", type="integer", example=1),
     *             @OA\Property(property="call_id", type="integer", example=100),
     *             @OA\Property(property="call_collection", type="string", example="[1,2,3]"),
     *             @OA\Property(property="days_past_due", type="integer", example=30),
     *             @OA\Property(property="paid_fees", type="integer", example=5),
     *             @OA\Property(property="pending_fees", type="integer", example=7),
     *             @OA\Property(property="managed_amount", type="number", example=500.00),
     *             @OA\Property(property="nro_notification", type="string", example="NOT-001"),
     *             @OA\Property(property="client_id", type="integer", example=10),
     *             @OA\Property(property="credit_id", type="integer", example=50),
     *             @OA\Property(property="campain_id", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Gestión creada correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Gestión creada correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="state", type="string"),
     *                 @OA\Property(property="substate", type="string"),
     *                 @OA\Property(property="observation", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al crear la gestión"
     *     )
     * )
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
            
            try {
                $ws = new WebSocketService();
                $ws->sendManagementUpdate(
                    $management->created_by,
                    $management->campain_id
                );
            } catch (\Exception $wsError) {
                Log::error('Error enviando notificación WebSocket después de la creación de la gestión', [
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
     * 
     * @OA\Get(
     *     path="/api/managements/{id}",
     *     summary="Obtener una gestión específica",
     *     description="Retorna los datos de una gestión por su ID con relaciones cargadas",
     *     operationId="getManagementById",
     *     tags={"Gestiones"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la gestión",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Gestión obtenida correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Gestión obtenida correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="state", type="string"),
     *                 @OA\Property(property="substate", type="string"),
     *                 @OA\Property(property="observation", type="string"),
     *                 @OA\Property(property="promise_date", type="string", format="date-time"),
     *                 @OA\Property(property="promise_amount", type="number"),
     *                 @OA\Property(
     *                     property="client",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="ci", type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="credit",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="sync_id", type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="campain",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="creator",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Gestión no encontrada"
     *     )
     * )
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

            foreach ($managements as $index => $managementData) {
                try {
                    $user = \App\Models\User::where('name', $managementData['byUser'])->first();
                    if (!$user) {
                        Log::warning("Usuario '{$managementData['byUser']}' no encontrado en índice {$index}, se omite");
                        continue;
                    }

                    $credit = \App\Models\Credit::where('sync_id', $managementData['sync_id'])->first();

                    if (!$credit) {
                        Log::warning("Crédito con sync_id '{$managementData['sync_id']}' no encontrado en índice {$index}, se omite");
                        continue;
                    }

                    $client = \App\Models\Client::firstOrCreate(
                        ['ci' => $managementData['client_ci']],
                        [
                            'name' => $managementData['client_name'] ?? $managementData['nombre_cliente'] ?? 'Cliente ' . $managementData['client_ci'],
                            'gender' => $managementData['gender'] ?? $managementData['genero'] ?? null,
                            'civil_status' => $managementData['civil_status'] ?? $managementData['estado_civil'] ?? null,
                            'economic_activity' => $managementData['economic_activity'] ?? $managementData['actividad_economica'] ?? null,
                        ]
                    );

                    // Si el cliente fue creado recientemente, crear la relación con el crédito
                    if ($client->wasRecentlyCreated) {
                        $clientType = $managementData['type'] ?? $managementData['tipo'] ?? null;
                        $client->credits()->attach($credit->id, ['type' => $clientType]);
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

                            $newCall = CollectionCall::firstOrCreate(
                                [
                                    'state' => $call['state_call'] ?? 'NO CONTACTADO',
                                    'duration' => $call['duration_call'] ?? 0,
                                    'media_path' => $call['id_record'] ?? null,
                                    'phone_number' => $call['phone'] ?? '',
                                    'client_id' => $contact ? $contact->client_id : null,
                                    'credit_id' => $credit->id,
                                ],
                                [
                                    'channel' => $call['channel'] ?? null,
                                    'campain_id' => $parseCampainId,
                                    'created_by' => $callUser ? $callUser->id : $user->id,
                                ]
                            );

                            if ($newCall->wasRecentlyCreated) {
                                $newCall->created_at = $parseDateAndAdd5Hours($call['fecha'] ?? null);
                                $newCall->updated_at = $parseDateAndAdd5Hours($call['updated_at'] ?? null);
                                $newCall->save();
                            }

                            $newCallIds[] = $newCall->id;
                        }
                    }

                    // Preparar campos para verificación de existencia exacta
                    $stateVal = $managementData['state_gestion'] ?? null;
                    $substateVal = $managementData['substate_gestion'] ?? null;
                    $observationVal = $managementData['observation'] ?? null;
                    $promiseDateObj = $parsePromiseDate($managementData['date_promise'] ?? null);
                    $promiseDateVal = $promiseDateObj ? $promiseDateObj->format('Y-m-d H:i:s') : null;
                    $promiseAmountVal = isset($managementData['monto_a_pagar']) ? floatval($managementData['monto_a_pagar']) : null;
                    $createdByVal = $user->id;
                    $callIdVal = null;
                    $callCollectionVal = json_encode($newCallIds);

                    // Verificar existencia exacta de la gestión por todos los campos requeridos
                    $exists = Management::where('credit_id', $credit->id)
                        ->where('campain_id', $parseCampainId)
                        ->where('state', $stateVal)
                        ->where('substate', $substateVal)
                        ->where(function ($q) use ($observationVal) {
                            if ($observationVal === null) {
                                $q->whereNull('observation');
                            } else {
                                $q->where('observation', $observationVal);
                            }
                        })
                        ->where(function ($q) use ($promiseDateVal) {
                            if ($promiseDateVal === null) {
                                $q->whereNull('promise_date');
                            } else {
                                $q->where('promise_date', $promiseDateVal);
                            }
                        })
                        ->where(function ($q) use ($promiseAmountVal) {
                            if ($promiseAmountVal === null) {
                                $q->whereNull('promise_amount');
                            } else {
                                $q->where('promise_amount', $promiseAmountVal);
                            }
                        })
                        ->where('created_by', $createdByVal)
                        ->where(function ($q) use ($callIdVal) {
                            if ($callIdVal === null) {
                                $q->whereNull('call_id');
                            } else {
                                $q->where('call_id', $callIdVal);
                            }
                        })
                        ->where(function ($q) use ($callCollectionVal) {
                            if ($callCollectionVal === null) {
                                $q->whereNull('call_collection');
                            } else {
                                $q->where('call_collection', $callCollectionVal);
                            }
                        })
                        ->exists();

                    if ($exists) {
                        // Ya existe una gestión igual; registrar en logs y omitir creación
                        Log::info('Gestión duplicada omitida en sincronización', [
                            'credit_sync_id' => $managementData['sync_id'] ?? null,
                            'credit_id' => $credit->id ?? null,
                            'campain_id' => $parseCampainId,
                            'state' => $stateVal,
                            'substate' => $substateVal,
                            'observation' => $observationVal,
                            'promise_date' => $promiseDateVal,
                            'promise_amount' => $promiseAmountVal,
                            'created_by' => $createdByVal,
                            'call_collection' => $callCollectionVal,
                            'index' => $index
                        ]);

                        $syncedCount++;
                        continue;
                    }

                    $newManagement = new Management([
                        'state' => $stateVal,
                        'substate' => $substateVal,
                        'observation' => $observationVal,
                        'promise_date' => $promiseDateObj,
                        'promise_amount' => $promiseAmountVal,
                        'created_by' => $createdByVal,
                        'call_id' => $callIdVal,
                        'call_collection' => $callCollectionVal,
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

                    Log::info('Gestión creada por sincronización', [
                        'management_id' => $newManagement->id,
                        'credit_sync_id' => $managementData['sync_id'] ?? null,
                        'credit_id' => $credit->id ?? null,
                        'campain_id' => $parseCampainId,
                        'state' => $stateVal,
                        'substate' => $substateVal,
                        'created_by' => $createdByVal,
                        'index' => $index
                    ]);

                    $syncedCount++;
                } catch (\Exception $e) {
                    Log::error("Error al sincronizar gestión en índice {$index}: {$e->getMessage()}");
                    // Continuar con la siguiente gestión sin detener el proceso
                    continue;
                }
            }

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

    /**
     * Intenta parsear fechas entrantes en varios formatos y devuelve YYYY-MM-DD o null.
     * Acepta formatos como YY/MM/DD, YY-MM-DD, YYYY/MM/DD, YYYY-MM-DD.
     */
    private function parseIncomingDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) return null;

        $formats = ['y/m/d', 'y-m-d', 'Y/m/d', 'Y-m-d'];

        foreach ($formats as $fmt) {
            try {
                $d = \Carbon\Carbon::createFromFormat($fmt, $dateStr);
                if ($d !== false) {
                    return $d->format('Y-m-d');
                }
            } catch (\Exception $e) {
                // continuar con el siguiente formato
            }
        }

        // Fallback: intentar strtotime para formatos comunes
        $ts = strtotime($dateStr);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    /**
     * Buscar créditos para envío de mensajes
     */
    public function getCreditsForMessages(Request $request)
    {
        try {
            $validated = $request->validate([
                'campain_id' => 'required|integer|exists:campains,id',
                'sync_ids' => 'nullable|array',
                'sync_ids.*' => 'string',
                'user_id' => 'nullable|integer|exists:users,id',
                'exclude_ids' => 'nullable|array',
                'exclude_ids.*' => 'integer',
                'include_ids' => 'nullable|array',
                'include_ids.*' => 'integer',
                'not_in_management' => 'nullable|array',
                'not_in_management.*' => 'string',
                'days_past_due_min' => 'nullable|integer',
                'days_past_due_max' => 'nullable|integer',
                'management_trays' => 'nullable|array',
                'management_trays.*' => 'string',
                'agencies' => 'nullable|array',
                'agencies.*' => 'string',
                'collection_state' => 'nullable|string',
                'status' => 'nullable|string',
                'limit' => 'nullable|integer|max:5000',
                'not_effective_managements' => 'nullable|boolean'
            ]);

            $campainId = $validated['campain_id'];

            // Obtener el business_id de la campaña
            $campain = DB::table('campains')->where('id', $campainId)->first();

            if (!$campain) {
                return ResponseBase::error(
                    'Campaña no encontrada',
                    [],
                    404
                );
            }

            $businessId = $campain->business_id;

            // Construir query base
            $query = DB::table('credits')
                ->select(
                    'credits.id',
                    'credits.sync_id',
                    'credits.days_past_due',
                    'credits.total_amount',
                    'credits.monthly_fee_amount',
                    'credits.payment_date',
                    'credits.user_id',
                    'credits.management_tray',
                    'credits.collection_state',
                    'credits.agency as agency_name'
                )
                ->where('credits.business_id', $businessId);

            // Aplicar filtros opcionales
            if (!empty($validated['sync_ids'])) {
                $query->whereIn('credits.sync_id', $validated['sync_ids']);
            }

            if (!empty($validated['user_id'])) {
                $query->where('credits.user_id', $validated['user_id']);
            }

            if (!empty($validated['exclude_ids'])) {
                $query->whereNotIn('credits.id', $validated['exclude_ids']);
            }

            if (!empty($validated['include_ids'])) {
                $query->whereIn('credits.id', $validated['include_ids']);
            }

            if (!empty($validated['days_past_due_min']) && !empty($validated['days_past_due_max'])) {
                $query->whereBetween('credits.days_past_due', [
                    $validated['days_past_due_min'],
                    $validated['days_past_due_max']
                ]);
            }

            if (!empty($validated['management_trays'])) {
                $query->whereIn('credits.management_tray', $validated['management_trays']);
            }

            if (!empty($validated['agencies'])) {
                $query->whereIn('credits.agency', $validated['agencies']);
            }

            if (!empty($validated['collection_state'])) {
                $query->where('credits.collection_state', $validated['collection_state']);
            }

            if (!empty($validated['status'])) {
                $query->where('credits.sync_status', $validated['status']);
            }

            if (!empty($validated['not_effective_managements']) && $validated['not_effective_managements'] === true) {
                $query->whereNotExists(function ($subQuery) use ($campainId) {
                    $subQuery->select(DB::raw(1))
                        ->from('management')
                        ->whereColumn('management.credit_id', 'credits.id')
                        ->where('management.campain_id', $campainId)
                        ->whereIn('management.substate', [
                            'OFERTA DE PAGO',
                            'VISITA CAMPO',
                            'MENSAJE DE TEXTO'
                        ]);
                });
            }

            // Filtro de estados de gestión excluidos
            if (!empty($validated['not_in_management'])) {
                $query->whereNotExists(function ($subQuery) use ($campainId, $validated) {
                    $subQuery->select(DB::raw(1))
                        ->from('management')
                        ->whereColumn('management.credit_id', 'credits.id')
                        ->where('management.campain_id', $campainId)
                        ->whereIn('management.substate', $validated['not_in_management']);
                });
            }

            // Excluir créditos cuya última gestión fue "VISITA CAMPO" (indistintamente del campain_id)
            $query->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('management as m1')
                    ->whereColumn('m1.credit_id', 'credits.id')
                    ->where('m1.substate', 'VISITA CAMPO')
                    ->whereNotExists(function ($innerQuery) {
                        $innerQuery->select(DB::raw(1))
                            ->from('management as m2')
                            ->whereColumn('m2.credit_id', 'm1.credit_id')
                            ->whereColumn('m2.id', '>', 'm1.id');
                    });
            });

            // Aplicar límite
            if (!empty($validated['limit'])) {
                $query->limit($validated['limit']);
            }

            $credits = $query->get();

            $data = [];

            foreach ($credits as $credit) {
                // Obtener los clientes del crédito
                $clientIds = DB::table('client_credit')
                    ->where('credit_id', $credit->id)
                    ->pluck('client_id');

                if ($clientIds->isEmpty()) {
                    continue;
                }

                // Obtener contactos activos de los clientes
                $contacts = DB::table('collection_contacts')
                    ->whereIn('client_id', $clientIds)
                    ->where('phone_status', 'ACTIVE')
                    ->get();

                foreach ($contacts as $contact) {
                    // Verificar si ya existe un SMS enviado en esta campaña para este crédito y este cliente
                    $hasSmsInCampaign = DB::table('management')
                        ->where('credit_id', $credit->id)
                        ->where('campain_id', $campainId)
                        ->where('client_id', $contact->client_id)
                        ->where('substate', 'MENSAJE DE TEXTO')
                        ->exists();

                    if ($hasSmsInCampaign) {
                        continue; // Saltar este contacto
                    }

                    // Verificar si el teléfono es efectivo (tiene llamadas contactadas)
                    $phoneState = 'No efectivo';
                    $hasContactedCalls = DB::table('collection_calls')
                        ->where('phone_number', $contact->phone_number)
                        ->where('state', 'CONTACTADO')
                        ->exists();

                    if ($hasContactedCalls) {
                        $phoneState = 'Efectivo';
                    }

                    // Obtener última gestión del crédito en la campaña
                    $lastManagement = DB::table('management')
                        ->where('credit_id', $credit->id)
                        ->where('campain_id', $campainId)
                        ->orderBy('id', 'DESC')
                        ->first();

                    // Obtener tipo de cliente desde la tabla pivot client_credit y nombre del cliente
                    $pivot = DB::table('client_credit')
                        ->where('credit_id', $credit->id)
                        ->where('client_id', $contact->client_id)
                        ->first();

                    $clientType = $pivot->type ?? '';
                    $clientRow = DB::table('clients')->where('id', $contact->client_id)->first();
                    $clientName = $clientRow->name ?? '';

                    $formattedName = trim('Sr(a) ' . ($clientType ? $clientType . ' ' : '') . $clientName);

                    $data[] = [
                        'telefono' => $contact->phone_number,
                        'name' => $formattedName,
                        'dias_vencidos' => $credit->days_past_due,
                        'total_pendiente' => $credit->total_amount,
                        'credito' => $credit->sync_id,
                        'agencia' => $credit->agency_name,
                        'ci' => $clientRow->ci ?? '',
                        'bandeja' => $credit->management_tray,
                        'type' => $contact->phone_type,
                        'cuota' => floatval($credit->monthly_fee_amount ?? 0),
                        'ult_gestion' => $lastManagement ? $lastManagement->substate : '',
                        'fecha_ult_gestion' => $lastManagement ? $lastManagement->created_at : '',
                        'agente_actual' => $credit->user_id,
                        'fecha_pago' => $credit->payment_date,
                        'fecha_contacto' => $contact->created_at,
                        'estado_contacto' => $phoneState,
                        'id' => $credit->id,
                    ];
                }
            }

            return ResponseBase::success(
                $data,
                'Créditos obtenidos correctamente para envío de mensajes'
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::error(
                'Error de validación',
                $e->errors(),
                422
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener créditos para mensajes',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Carga masiva de gestiones desde un array de datos
     */
    public function bulkStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'data' => 'required|array|min:1',
                'data.*.ci' => 'required|string',
                'data.*.sync_id' => 'required|string',
                'data.*.phone' => 'required|string',
                'campain_id' => 'required|integer|exists:campains,id',
                'state' => 'required|string',
                'substate' => 'required|string',
                'promise_date' => 'nullable|date',
            ]);

            $user = $request->user();
            if (!$user) {
                return ResponseBase::unauthorized('Token inválido o expirado');
            }

            $results = [
                'created' => 0,
                'errors' => []
            ];

            foreach ($validated['data'] as $index => $item) {
                try {
                    // Buscar cliente por CI
                    $client = \App\Models\Client::where('ci', $item['ci'])->first();
                    if (!$client) {
                        $results['errors'][] = [
                            'index' => $index,
                            'ci' => $item['ci'],
                            'error' => 'Cliente no encontrado'
                        ];
                        continue;
                    }

                    // Buscar crédito por sync_id (con lógica flexible para prefijos)
                    $syncIdSearch = $item['sync_id'];
                    $numericPart = $syncIdSearch;

                    if (strpos($syncIdSearch, '-') !== false) {
                        $parts = explode('-', $syncIdSearch);
                        $numericPart = end($parts);
                    }

                    $credit = \App\Models\Credit::where('sync_id', $syncIdSearch)
                        ->orWhere('sync_id', $numericPart)
                        ->orWhere('sync_id', 'LIKE', '%-' . $numericPart)
                        ->first();

                    if (!$credit) {
                        $results['errors'][] = [
                            'index' => $index,
                            'sync_id' => $item['sync_id'],
                            'error' => 'Crédito no encontrado'
                        ];
                        continue;
                    }

                    // Crear la gestión
                    $management = Management::create([
                        'state' => $validated['state'],
                        'substate' => $validated['substate'],
                        'observation' => 'SMS a ' . $item['phone'],
                        'promise_date' => $validated['promise_date'] ?? null,
                        'created_by' => $user->id,
                        'client_id' => $client->id,
                        'credit_id' => $credit->id,
                        'campain_id' => $validated['campain_id'],
                        'days_past_due' => $credit->days_past_due ?? 0,
                        'paid_fees' => $credit->paid_fees ?? 0,
                        'pending_fees' => $credit->pending_fees ?? 0,
                        'managed_amount' => $credit->total_amount ?? 0,
                    ]);

                    // Actualizar estado del crédito
                    $credit->management_status = $validated['substate'];
                    $credit->management_tray = "GESTIONADO";
                    if (!empty($validated['promise_date'])) {
                        $credit->management_promise = $validated['promise_date'];
                    }
                    $credit->save();

                    $results['created']++;

                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'index' => $index,
                        'ci' => $item['ci'] ?? null,
                        'sync_id' => $item['sync_id'] ?? null,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return ResponseBase::success(
                $results,
                "Carga masiva completada: {$results['created']} gestiones creadas",
                201
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al crear gestiones masivas',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}