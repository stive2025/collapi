<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\Campain;
use App\Models\CollectionCall;
use App\Models\Credit;
use App\Models\Management;
use App\Models\User;
use App\Services\WebSocketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = User::query();

        if ($request->filled('agents')) {
            $query->whereNotIn('role',['superadmin','query']);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active',$request->is_active);
        }

        $users = $query->paginate($perPage);

        return ResponseBase::success(
            $users,
            'Usuarios obtenidos correctamente'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:255', 'unique:users,username'],
                'extension' => ['nullable', 'string', 'max:50'],
                'permission' => ['nullable', 'json'],
                'phone' => ['nullable', 'string', 'max:20'],
                'password' => ['required', 'string', 'min:6'],
                'role' => ['required', 'string', 'max:50'],
                'created_by' => ['nullable', 'integer', 'exists:users,id'],
            ]);

            $validated['password'] = Hash::make($validated['password']);
            $validated['is_active'] = true;

            $user = User::create($validated);

            // No retornar el password en la respuesta
            $user->makeHidden(['password']);

            return ResponseBase::success(
                $user,
                'Usuario creado exitosamente',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error creating user', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al crear el usuario',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        // No mostrar el password
        $user->makeHidden(['password']);
        
        return ResponseBase::success(
            $user,
            'Usuario obtenido correctamente'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'username' => ['sometimes', 'string', 'max:255', 'unique:users,username,' . $user->id],
                'email' => ['nullable', 'email', 'unique:users,email,' . $user->id],
                'extension' => ['nullable', 'string', 'max:50'],
                'permission' => ['nullable', 'json'],
                'phone' => ['nullable', 'string', 'max:20'],
                'password' => ['sometimes', 'string', 'min:6'],
                'role' => ['sometimes', 'string', 'max:50'],
                'status' => ['sometimes', 'string', 'max:50'],
                'is_active' => ['sometimes', 'boolean']
            ]);

            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }

            $user->update($validated);

            if (isset($validated['status'])) {
                try {
                    $ws = new WebSocketService();
                    $status = $validated['status'];

                    if ($status === 'CONECTADO') {
                        $ws->sendLoginUpdate($user->id);
                    } elseif ($status === 'FUERA DE LÍNEA') {
                        $ws->sendLogoutUpdate($user->id);
                    } elseif ($status === 'EN LLAMADA') {
                        $campainId = $request->input('campain_id');
                        if ($campainId) {
                            $ws->sendDialUpdate($user->id, $campainId);
                        }
                    } else {
                        $campainId = $request->input('campain_id', 'ALL');
                        $ws->sendUserUpdate($user->id, $status, $campainId);
                    }
                } catch (\Exception $wsError) {
                    Log::error('WebSocket notification failed on user update', [
                        'error' => $wsError->getMessage(),
                        'user_id' => $user->id,
                        'status' => $validated['status'] ?? null
                    ]);
                }
            }

            $user->makeHidden(['password']);

            return ResponseBase::success(
                $user,
                'Usuario actualizado exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error updating user', [
                'message' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return ResponseBase::error(
                'Error al actualizar el usuario',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage (desactivar usuario).
     */
    public function destroy(User $user)
    {
        try {
            $user->update([
                'permission' => '[]',
                'extension' => ''
            ]);

            $user->makeHidden(['password']);

            return ResponseBase::success(
                $user,
                'Usuario desactivado exitosamente'
            );
        } catch (\Exception $e) {
            Log::error('Error deactivating user', [
                'message' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return ResponseBase::error(
                'Error al desactivar el usuario',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Monitor de agentes - devuelve estadísticas de usuarios activos en campañas
     */
    public function monitor(Request $request)
    {
        try {
            $campainQuery = Campain::where('id', $request->campain_id);

            if ($request->filled('business_id')) {
                $campainQuery->where('business_id', $request->business_id);
            }
            
            $activeCampain = $campainQuery->first();

            if (!$activeCampain) {
                return ResponseBase::success(
                    [],
                    'No hay campañas activas'
                );
            }

            $agentIds = $activeCampain->agents ?? [];

            if (empty($agentIds)) {
                return ResponseBase::success(
                    [],
                    'No hay agentes asignados a la campaña activa'
                );
            }

            $users = User::whereIn('id', $agentIds)
                ->where('is_active', true)
                ->get();

            $today = now()->startOfDay();
            $monitorData = [];

            foreach ($users as $user) {
                $nroCredits = Credit::where('user_id', $user->id)
                    ->where('sync_status','ACTIVE')
                    ->where('business_id', $activeCampain->business_id)
                    ->count();

                $nroGestions = Management::where('created_by', $user->id)
                    ->where('campain_id', $activeCampain->id)
                    ->count();

                $nroGestionsDia = Management::where('created_by', $user->id)
                    ->where('campain_id', $activeCampain->id)
                    ->whereDate('created_at', $today)
                    ->count();

                $nroGestionsEfec = Management::where('created_by', $user->id)
                    ->where('campain_id', $activeCampain->id)
                    ->whereIn('state', ['CONVENIO DE PAGO', 'OFERTA DE PAGO', 'COMPROMISO_PAGO'])
                    ->count();

                $nroGestionsEfecDia = Management::where('created_by', $user->id)
                    ->where('campain_id', $activeCampain->id)
                    ->whereIn('state', ['CONVENIO DE PAGO', 'OFERTA DE PAGO', 'COMPROMISO_PAGO'])
                    ->whereDate('created_at', $today)
                    ->count();

                $nroPendientes = Credit::where('user_id', $user->id)
                    ->where('sync_status','ACTIVE')
                    ->where('business_id', $activeCampain->business_id)
                    ->where('management_tray', 'PENDIENTE')
                    ->count();

                $nroProceso = Credit::where('user_id', $user->id)
                    ->where('sync_status','ACTIVE')
                    ->where('business_id', $activeCampain->business_id)
                    ->where('management_tray', 'EN PROCESO')
                    ->count();

                $nroProcesoDia = Credit::where('user_id', $user->id)
                    ->where('sync_status','ACTIVE')
                    ->where('business_id', $activeCampain->business_id)
                    ->where('management_tray', 'EN PROCESO')
                    ->whereDate('last_sync_date', $today)
                    ->count();

                $nroCalls = CollectionCall::where('created_by', $user->id)
                    ->whereDate('created_at', $today)
                    ->count();

                $nroCallsAcum = CollectionCall::where('created_by', $user->id)
                    ->whereHas('credit', function($q) use ($activeCampain) {
                        $q->where('business_id', $activeCampain->business_id);
                    })
                    ->count();

                $timeElapsed = abs(now()->diffInSeconds($user->updated_at));
                $hours = floor($timeElapsed / 3600);
                $minutes = floor(($timeElapsed % 3600) / 60);
                $seconds = $timeElapsed % 60;
                $timeState = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);

                $monitorData[] = [
                    'name' => $user->name,
                    'campain_id' => $activeCampain->id,
                    'campain_name' => $activeCampain->name,
                    'user_state' => $user->status,
                    'time_state' => $timeState,
                    'user_id' => $user->id,
                    'data' => [
                        'nro_credits' => $nroCredits,
                        'nro_gestions' => $nroGestions,
                        'nro_gestions_dia' => $nroGestionsDia,
                        'nro_gestions_efec' => $nroGestionsEfec,
                        'nro_gestions_efec_dia' => $nroGestionsEfecDia,
                        'nro_pendientes' => $nroPendientes,
                        'nro_proceso' => $nroProceso,
                        'nro_proceso_dia' => $nroProcesoDia,
                        'nro_calls' => $nroCalls,
                        'nro_calls_acum' => $nroCallsAcum,
                    ]
                ];
            }

            return ResponseBase::success(
                $monitorData,
                'Monitor de agentes obtenido correctamente'
            );

        } catch (\Exception $e) {
            Log::error('Error in monitor endpoint', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al obtener el monitor de agentes',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}