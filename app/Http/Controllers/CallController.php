<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCallRequest;
use App\Http\Responses\ResponseBase;
use App\Models\Client;
use App\Models\CollectionCall;
use App\Models\CollectionContact;
use App\Services\AsteriskService;
use App\Services\WebSocketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CallController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->query('per_page', 15);

            $query = CollectionCall::query();

            if ($request->filled('credit_id')) {
                $query->where('credit_id', $request->query('credit_id'));
            }

            if ($request->filled('state')) {
                $query->where('state', $request->query('state'));
            }

            if ($request->filled('channel')) {
                $query->where('channel', $request->query('channel'));
            }

            if ($request->filled('created_by')) {
                $query->where('created_by', $request->query('created_by'));
            }

            $calls = $query->with( 'credit')->paginate($perPage);

            return ResponseBase::success($calls, 'Llamadas obtenidas correctamente');
        } catch (\Exception $e) {
            Log::error('Error fetching collection calls', [
                'message' => $e->getMessage()
            ]);

            return ResponseBase::error('Error al obtener llamadas', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCallRequest $request)
    {
        try {
            $validated = $request->validated();

            if (!isset($validated['created_by'])) {
                $user = $request->user();
                if (!$user) {
                    return ResponseBase::unauthorized('Usuario no autenticado');
                }
                $validated['created_by'] = $user->id;
            }

            $client = Client::find($validated['client_id']);

            if (!$client) {
                return ResponseBase::error('Cliente no encontrado', null, 404);
            }

            $call = CollectionCall::create($validated);
            $this->updateContactCounters($validated['phone_number'], $validated['state']);
            $call->load('credit');

            try {
                $ws = new WebSocketService();
                $ws->sendCallUpdate(
                    $validated['created_by'],
                    $request->campain_id
                );
            } catch (\Exception $wsError) {
                Log::error('WebSocket notification failed after call creation', [
                    'error' => $wsError->getMessage(),
                    'call_id' => $call->id
                ]);
            }

            return ResponseBase::success(
                $call,
                'Llamada registrada correctamente',
                201
            );
        } catch (\Exception $e) {
            Log::error('Error creating collection call', [
                'message' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return ResponseBase::error(
                'Error al registrar la llamada',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Actualiza los contadores del contacto según el estado de la llamada
     */
    private function updateContactCounters(string $phone_number, string $state): void
    {
        $contact = CollectionContact::where('phone_number', $phone_number)->first();

        if (!$contact) {
            return;
        }

        $effectiveStates = [
            'CONTACTADO'
        ];

        $state = strtoupper($state);

        if (in_array($state, $effectiveStates)) {
            $contact->increment('calls_effective');
        } else {
            $contact->increment('calls_not_effective');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(CollectionCall $collectionCall)
    {
        try {
            $collectionCall->load('credit');
            return ResponseBase::success($collectionCall, 'Llamada obtenida correctamente');
        } catch (\Exception $e) {
            return ResponseBase::error('Error al obtener la llamada', ['error' => $e->getMessage()], 500);
        }
    }

    public function dial(Request $request)
    {
        try {
            $asterisk_service = new AsteriskService(
                env('ASTERISK_SERVER_IP'),
                env('ASTERISK_USERNAME'),
                env('ASTERISK_PASSWORD')
            );

            $originate_call = $asterisk_service->originateCall(
                $request->input('channel', ''),
                $request->input('exten', ''),
                $request->input('context', ''),
                $request->input('priority', '1'),
                $request->input('application') ?? '',
                $request->input('data', ''),
                $request->input('timeout', 30000),
                $request->input('caller_id', ''),
                $request->input('variables', []),
                $request->input('account', ''),
                $request->input('async', 'true'),
                $request->input('action_id', '')
            );

            try {
                $user = $request->user();
                $campainId = $request->input('campain_id');

                if ($user && $campainId) {
                    $ws = new WebSocketService();
                    $ws->sendDialUpdate($user->id, $campainId);
                }
            } catch (\Exception $wsError) {
                Log::error('WebSocket notification failed on dial', [
                    'error' => $wsError->getMessage(),
                    'user_id' => $user->id ?? null
                ]);
            }

            return ResponseBase::success($originate_call, 'Llamada iniciada correctamente');
        } catch (\Exception $e) {
            Log::error('Error dialing call', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return ResponseBase::error(
                'Error al iniciar la llamada',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function hangup(Request $request)
    {
        // Lógica para colgar una llamada ASTERISK
        $asterisk_service = new AsteriskService(
            env('ASTERISK_SERVER_IP'),
            env('ASTERISK_USERNAME'),
            env('ASTERISK_PASSWORD')
        );
        $hangup_call = $asterisk_service->hangup(
            $request->input('channel')
        );
        return ResponseBase::success($hangup_call, 'Llamada colgada correctamente');
    }
}