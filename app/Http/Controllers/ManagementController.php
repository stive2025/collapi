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
            $query->where('credit_id', $request->query('credit_id'));
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

    public function syncManagements(Request $request)
    {
        try {
            $validated = $request->validate([
                'campain_id' => 'required|integer|exists:campains,id'
            ]);

            $campainId = $validated['campain_id'];
            $apiUrl = "https://core.sefil.com.ec/api/public/api/managments?campain_id={$campainId}";

            $response = file_get_contents($apiUrl);
            $managementsData = json_decode($response, true);

            if (!isset($managementsData['data']) || !is_array($managementsData['data'])) {
                return ResponseBase::error(
                    'No se obtuvieron gestiones de la API',
                    [],
                    400
                );
            }

            $totalManagements = count($managementsData['data']);
            $syncedCount = 0;

            DB::transaction(function () use ($managementsData, $campainId, &$syncedCount) {
                foreach ($managementsData['data'] as $index => $managementData) {
                    $user = \App\Models\User::where('name', $managementData['byUser'])->first();
                    if (!$user) {
                        throw new \Exception("Usuario '{$managementData['byUser']}' no encontrado en índice {$index}");
                    }

                    $client = \App\Models\Client::where('ci', $managementData['client_ci'])->first();
                    if (!$client) {
                        throw new \Exception("Cliente con CI '{$managementData['client_ci']}' no encontrado en índice {$index}");
                    }

                    $credit = \App\Models\Credit::find($managementData['id_credit']);
                    if (!$credit) {
                        throw new \Exception("Crédito con ID '{$managementData['id_credit']}' no encontrado en índice {$index}");
                    }

                    $newCallIds = [];
                    $callIdsExtras = json_decode($managementData['id_calls_extras'], true);

                    if (is_array($callIdsExtras) && !empty($callIdsExtras)) {
                        foreach ($callIdsExtras as $oldCallId) {
                            $callApiUrl = "https://core.sefil.com.ec/api/public/api/calls/{$oldCallId}";
                            $callResponse = file_get_contents($callApiUrl);
                            $callData = json_decode($callResponse, true);

                            if (isset($callData['call'])) {
                                $call = $callData['call'];

                                $callUser = \App\Models\User::where('name', $call['byUser'])->first();

                                $newCall = CollectionCall::create([
                                    'state_call' => $call['state_call'] ?? '',
                                    'duration_call' => $call['duration_call'] ?? 0,
                                    'phone' => $call['phone'] ?? '',
                                    'channel' => $call['channel'] ?? null,
                                    'credit_id' => $credit->id,
                                    'campain_id' => $managementData['parse_campain_id'],
                                    'created_by' => $callUser ? $callUser->id : $user->id,
                                    'created_at' => $call['fecha'] ?? now(),
                                    'updated_at' => $call['updated_at'] ?? now(),
                                ]);

                                $newCallIds[] = $newCall->id;
                            }
                        }
                    }

                    Management::create([
                        'state' => $managementData['state_gestion'],
                        'substate' => $managementData['substate_gestion'],
                        'observation' => $managementData['observation'] ?? null,
                        'promise_date' => $managementData['date_promise'] ?? now(),
                        'promise_amount' => $managementData['monto_a_pagar'] ?? null,
                        'created_by' => $user->id,
                        'call_id' => null,
                        'call_collection' => json_encode($newCallIds),
                        'days_past_due' => $managementData['dias_vencidos'] ?? 0,
                        'paid_fees' => $managementData['cuotas_pagadas'] ?? 0,
                        'pending_fees' => $managementData['cuotas_pendientes'] ?? 0,
                        'managed_amount' => $managementData['monto'] ?? 0,
                        'nro_notification' => $managementData['nro_notification'] ?? null,
                        'client_id' => $client->id,
                        'credit_id' => $credit->id,
                        'campain_id' => $managementData['parse_campain_id'],
                        'created_at' => $managementData['fecha'] ?? now(),
                        'updated_at' => $managementData['updated_at'] ?? now(),
                    ]);

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