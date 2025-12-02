<?php

namespace App\Http\Controllers;

use Illuminate\Auth\AuthenticationException;
use App\Http\Requests\StoreCallRequest;
use App\Http\Responses\ResponseBase;
use App\Models\CollectionCall;
use App\Services\AsteriskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CallController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $calls = CollectionCall::paginate(request('per_page', 15));
        
        return ResponseBase::success(
            $calls,
            'Llamadas obtenidas correctamente'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCallRequest $request)
    {
        try {
            $data_call = [
                'state' => $request->validated()['state'],
                'duration' => $request->validated()['duration'] ?? null,
                'media_path' => $request->validated()['media_path'] ?? null,
                'channel' => $request->validated()['channel'],
                'created_by' => Auth::id(),
                'collection_contact_id' => $request->validated()['contact_id'],
                'credit_id' => $request->validated()['credit_id']
            ];

            $create_call = CollectionCall::create($data_call);
            
            return ResponseBase::success(
                $create_call,
                'Llamada guardada correctamente',
                201
            );
        } catch (\Throwable $th) {
            Log::channel('calls')->error('Error creating call', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
                'data' => $data_call ?? null
            ]);

            return ResponseBase::error(
                'Error al guardar la llamada',
                ['error' => $th->getMessage()],
                500
            );
        }
    }

    /**
     * Iniciar una llamada mediante Asterisk
     */
    public function dial(Request $request)
    {
        try {
            $validated = $request->validate([
                'channel' => ['required', 'string'],
                'exten' => ['required', 'string'],
                'context' => ['required', 'string'],
                'priority' => ['required', 'integer'],
                'timeout' => ['required', 'integer'],
                'caller_id' => ['required', 'string'],
                'application' => ['nullable', 'string'],
                'data' => ['nullable', 'string'],
                'variables' => ['nullable', 'array'],
                'account' => ['nullable', 'string'],
                'async' => ['nullable', 'boolean'],
                'action_id' => ['nullable', 'string'],
            ]);

            $manager_asterisk = new AsteriskService(
                "172.20.1.107",
                "call_master",
                "s3f1l_c@11"
            );

            $result = $manager_asterisk->originateCall(
                $validated['channel'],
                $validated['exten'],
                $validated['context'],
                $validated['priority'],
                $validated['application'] ?? '',
                $validated['data'] ?? '',
                $validated['timeout'],
                $validated['caller_id'],
                $validated['variables'] ?? [],
                $validated['account'] ?? '',
                $validated['async'] ?? false,
                $validated['action_id'] ?? ''
            );

            return ResponseBase::success(
                $result,
                'Llamada iniciada correctamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::channel('calls')->error('Error dialing call', [
                'message' => $e->getMessage()
            ]);

            return ResponseBase::error(
                'Error al iniciar la llamada',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Colgar una llamada activa
     */
    public function hangup(Request $request)
    {
        try {
            $validated = $request->validate([
                'channel' => ['required', 'string'],
            ]);

            $manager_asterisk = new AsteriskService(
                "172.20.1.107",
                "call_master",
                "s3f1l_c@11"
            );

            $hangup = $manager_asterisk->hangup($validated['channel']);
            
            return ResponseBase::success(
                $hangup,
                'Llamada colgada correctamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::channel('calls')->error('Error hanging up call', [
                'message' => $e->getMessage()
            ]);

            return ResponseBase::error(
                'Error al colgar la llamada',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}