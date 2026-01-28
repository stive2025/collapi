<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\Campain;
use App\Models\Credit;
use App\Models\CollectionDirection;
use App\Models\Management;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FieldTripController extends Controller
{
    /**
     * Display a listing of the resource.
     * Devuelve créditos con management_status VISITA CAMPO o cuya última gestión sea VISITA CAMPO
     * 
     * @OA\Get(
     *     path="/api/field-trips",
     *     summary="Listar visitas de campo",
     *     description="Obtiene créditos con estado de VISITA CAMPO o cuya última gestión sea VISITA CAMPO",
     *     operationId="getFieldTripsList",
     *     tags={"Visitas de Campo"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="ID de la empresa",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="ID del usuario",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de visitas de campo obtenida correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Visitas de campo obtenidas correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="credit_id", type="integer"),
     *                     @OA\Property(property="sync_id", type="string"),
     *                     @OA\Property(property="client_name", type="string"),
     *                     @OA\Property(property="client_ci", type="string"),
     *                     @OA\Property(
     *                         property="direction",
     *                         type="object",
     *                         @OA\Property(property="address", type="string"),
     *                         @OA\Property(property="reference", type="string"),
     *                         @OA\Property(property="type", type="string")
     *                     ),
     *                     @OA\Property(
     *                         property="managements",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="state", type="string"),
     *                             @OA\Property(property="substate", type="string"),
     *                             @OA\Property(property="observation", type="string"),
     *                             @OA\Property(property="type", type="string", example="field_visit")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación - Faltan parámetros requeridos"
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'business_id' => 'required|integer',
                'user_id' => 'required|integer',
            ]);

            $businessId = $request->query('business_id');
            $userId = $request->query('user_id');

            $campain = Campain::where('state', 'ACTIVE')->where('business_id', $businessId)->first();

            $credits = Credit::where('business_id', $businessId)
                ->where('user_id', $userId)
                ->where('sync_status', 'ACTIVE')
                ->where('approve_field_trip', 1)
                ->with(['clients'])
                ->get();

            $result = $credits->map(function ($credit) use ($campain) {
                $clients = $credit->clients->map(function ($client) {
                    return [
                        'id' => $client->id,
                        'name' => $client->name,
                        'ci' => $client->ci,
                        'type' => $client->pivot->type ?? null,
                    ];
                });

                $mainClient = $credit->clients->firstWhere('pivot.type', 'TITULAR') ?? $credit->clients->first();

                $direction = null;
                if ($mainClient) {
                    $direction = CollectionDirection::where('client_id', $mainClient->id)
                        ->where('type', 'DOMICILIO')
                        ->first();

                    if (!$direction) {
                        $direction = CollectionDirection::where('client_id', $mainClient->id)->first();
                    }
                }

                $managements = Management::where('credit_id', $credit->id)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($management) {
                        $type = 'field_visit';
                        if (!empty($management->call_collection)) {
                            $callIds = json_decode($management->call_collection, true);
                            if (is_array($callIds) && count($callIds) > 0) {
                                $hasWA = DB::table('collection_calls')
                                    ->whereIn('id', $callIds)
                                    ->where('channel', 'WA')
                                    ->exists();
                                $type = $hasWA ? 'whatsapp' : 'phone_call';
                            }
                        } elseif ($management->call_id) {
                            $call = DB::table('collection_calls')->find($management->call_id);
                            $type = ($call && $call->channel === 'WA') ? 'whatsapp' : 'phone_call';
                        }

                        $clientName = null;
                        if ($management->client_id) {
                            $mgClient = \App\Models\Client::find($management->client_id);
                            $clientName = $mgClient ? $mgClient->name : null;
                        }

                        return [
                            'id' => $management->id,
                            'credit_id' => (string) $management->credit_id,
                            'user_id' => (string) $management->created_by,
                            'state' => $management->state,
                            'substate' => $management->substate,
                            'promise_date' => $management->promise_date,
                            'observation' => $management->observation,
                            'client_name' => $clientName,
                            'type' => $type,
                            'created_at' => $management->created_at,
                            'updated_at' => $management->updated_at,
                        ];
                    });

                return [
                    'id' => $credit->id,
                    'field_trip_id' => $credit->id,
                    'clients' => $clients,
                    'amount' => (float) ($credit->total_amount ?? 0),
                    'address' => $direction ? $direction->direction : null,
                    'managements' => $managements,
                    'campain_id' => ($campain != null) ? $campain->id : null,
                ];
            });

            return ResponseBase::success(
                $result,
                'Listado de visitas de campo obtenido correctamente'
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error al obtener listado de visitas de campo', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al obtener listado de visitas de campo',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Aprobar o desaprobar visita de campo para un crédito
     *
     * @param Request $request
     * @param int $creditId
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleApproval(Request $request, int $creditId)
    {
        try {
            $request->validate([
                'approve' => 'required|boolean',
            ]);

            $credit = Credit::find($creditId);

            if (!$credit) {
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            $approve = $request->input('approve');
            $credit->approve_field_trip = $approve;

            $newStatus = $approve ? 'VISITA APROBADA' : 'VISITA NO APROBADA';
            $credit->management_status = $newStatus;

            $lastManagement = Management::where('credit_id', $creditId)
                ->where('substate', 'VISITA CAMPO')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastManagement) {
                $lastManagement->substate = $newStatus;
                $lastManagement->save();
            }

            $credit->save();

            return ResponseBase::success(
                [
                    'credit_id' => $credit->id,
                    'approve_field_trip' => $credit->approve_field_trip,
                    'management_status' => $credit->management_status,
                ],
                $approve
                    ? 'Visita de campo aprobada correctamente'
                    : 'Visita de campo desaprobada correctamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Error al actualizar aprobación de visita de campo', [
                'credit_id' => $creditId,
                'message' => $e->getMessage(),
            ]);

            return ResponseBase::error(
                'Error al actualizar aprobación de visita de campo',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
