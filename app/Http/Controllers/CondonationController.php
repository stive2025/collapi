<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\Condonation;
use App\Models\Credit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CondonationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->query('per_page', 15);

            $query = Condonation::query();

            if ($request->filled('credit_id')) {
                $query->where('credit_id', $request->query('credit_id'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }

            // Cargar relaciones necesarias para el Resource
            $query->with(['credit.clients', 'creator']);

            $condonations = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return ResponseBase::success(
                \App\Http\Resources\CondonationResource::collection($condonations),
                'Condonaciones obtenidas correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener condonaciones',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $user = $request->user();
            if (!$user) {
                DB::rollBack();
                return ResponseBase::unauthorized('Usuario no autenticado');
            }

            $validated = $request->validate([
                'credit_id' => ['required', 'integer', 'exists:credits,id'],
                'post_dates' => ['required', 'array'],
                'post_dates.total_amount' => ['required', 'numeric', 'min:0'],
                'post_dates.capital' => ['required', 'numeric', 'min:0'],
                'post_dates.interest' => ['required', 'numeric', 'min:0'],
                'post_dates.mora' => ['required', 'numeric', 'min:0'],
                'post_dates.safe' => ['required', 'numeric', 'min:0'],
                'post_dates.management_collection_expenses' => ['required', 'numeric', 'min:0'],
                'post_dates.collection_expenses' => ['required', 'numeric', 'min:0'],
                'post_dates.legal_expenses' => ['required', 'numeric', 'min:0'],
                'post_dates.other_values' => ['required', 'numeric', 'min:0'],
            ]);

            $credit = Credit::lockForUpdate()->find($validated['credit_id']);

            if (!$credit) {
                DB::rollBack();
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            // Validar que el crédito no esté en convenio de pago
            if ($credit->collection_state === 'CONVENIO DE PAGO') {
                DB::rollBack();
                return ResponseBase::error(
                    'No se puede crear una condonación para un crédito en convenio de pago',
                    null,
                    400
                );
            }

            // Validar que no exista una condonación previa
            $existingCondonation = Condonation::where('credit_id', $validated['credit_id'])->first();
            if ($existingCondonation) {
                DB::rollBack();
                return ResponseBase::error(
                    'Ya existe una condonación para este crédito',
                    null,
                    400
                );
            }

            $prevDates = [
                'total_amount' => (float)($credit->total_amount ?? 0),
                'capital' => (float)($credit->capital ?? 0),
                'interest' => (float)($credit->interest ?? 0),
                'mora' => (float)($credit->mora ?? 0),
                'safe' => (float)($credit->safe ?? 0),
                'management_collection_expenses' => (float)($credit->management_collection_expenses ?? 0),
                'collection_expenses' => (float)($credit->collection_expenses ?? 0),
                'legal_expenses' => (float)($credit->legal_expenses ?? 0),
                'other_values' => (float)($credit->other_values ?? 0),
            ];

            $postDates = $validated['post_dates'];
            $condonedAmount = [
                'total_amount' => $prevDates['total_amount'] - (float)$postDates['total_amount'],
                'capital' => $prevDates['capital'] - (float)$postDates['capital'],
                'interest' => $prevDates['interest'] - (float)$postDates['interest'],
                'mora' => $prevDates['mora'] - (float)$postDates['mora'],
                'safe' => $prevDates['safe'] - (float)$postDates['safe'],
                'management_collection_expenses' => $prevDates['management_collection_expenses'] - (float)$postDates['management_collection_expenses'],
                'collection_expenses' => $prevDates['collection_expenses'] - (float)$postDates['collection_expenses'],
                'legal_expenses' => $prevDates['legal_expenses'] - (float)$postDates['legal_expenses'],
                'other_values' => $prevDates['other_values'] - (float)$postDates['other_values'],
            ];

            // Verificar si el usuario es admin
            $isAdmin = in_array(strtolower($user->role), ['admin', 'superadmin']);

            $condonationData = [
                'credit_id' => $validated['credit_id'],
                'prev_dates' => json_encode($prevDates),
                'post_dates' => json_encode($postDates),
                'amount' => (float)$condonedAmount['total_amount'],
                'created_by' => $user->id,
            ];

            if ($isAdmin) {
                // Admin: status AUTORIZADA y updated_by
                $condonationData['status'] = 'AUTORIZADA';
                $condonationData['updated_by'] = $user->id;

                // Restar valores del crédito
                $credit->update([
                    'total_amount' => (float)$postDates['total_amount'],
                    'capital' => (float)$postDates['capital'],
                    'interest' => (float)$postDates['interest'],
                    'mora' => (float)$postDates['mora'],
                    'safe' => (float)$postDates['safe'],
                    'management_collection_expenses' => (float)$postDates['management_collection_expenses'],
                    'collection_expenses' => (float)$postDates['collection_expenses'],
                    'legal_expenses' => (float)$postDates['legal_expenses'],
                    'other_values' => (float)$postDates['other_values'],
                ]);
            } else {
                // No admin: status PENDIENTE, no restar valores
                $condonationData['status'] = 'PENDIENTE';
            }

            $condonation = Condonation::create($condonationData);

            DB::commit();

            // Cargar relaciones para el Resource
            $condonation->load(['credit.clients', 'creator']);

            return ResponseBase::success(
                new \App\Http\Resources\CondonationResource($condonation),
                'Condonación aplicada exitosamente',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseBase::error(
                'Error al crear la condonación',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $condonation = Condonation::with(['credit.clients', 'creator'])->find($id);

            if (!$condonation) {
                return ResponseBase::notFound('Condonación no encontrada');
            }

            return ResponseBase::success(
                new \App\Http\Resources\CondonationResource($condonation),
                'Condonación obtenida correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener la condonación',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();

        try {
            $user = $request->user();
            if (!$user) {
                DB::rollBack();
                return ResponseBase::unauthorized('Usuario no autenticado');
            }

            // Verificar que el usuario sea admin
            $isAdmin = in_array(strtolower($user->role), ['admin', 'superadmin']);
            if (!$isAdmin) {
                DB::rollBack();
                return ResponseBase::error(
                    'Solo los administradores pueden actualizar condonaciones',
                    null,
                    403
                );
            }

            $condonation = Condonation::find($id);

            if (!$condonation) {
                DB::rollBack();
                return ResponseBase::notFound('Condonación no encontrada');
            }

            // Solo se pueden actualizar condonaciones en estado PENDIENTE
            if ($condonation->status !== 'PENDIENTE') {
                DB::rollBack();
                return ResponseBase::error(
                    'Solo se pueden actualizar condonaciones en estado PENDIENTE',
                    null,
                    400
                );
            }

            $validated = $request->validate([
                'post_dates' => ['required', 'array'],
                'post_dates.total_amount' => ['required', 'numeric', 'min:0'],
                'post_dates.capital' => ['required', 'numeric', 'min:0'],
                'post_dates.interest' => ['required', 'numeric', 'min:0'],
                'post_dates.mora' => ['required', 'numeric', 'min:0'],
                'post_dates.safe' => ['required', 'numeric', 'min:0'],
                'post_dates.management_collection_expenses' => ['required', 'numeric', 'min:0'],
                'post_dates.collection_expenses' => ['required', 'numeric', 'min:0'],
                'post_dates.legal_expenses' => ['required', 'numeric', 'min:0'],
                'post_dates.other_values' => ['required', 'numeric', 'min:0'],
            ]);

            $credit = Credit::lockForUpdate()->find($condonation->credit_id);

            if (!$credit) {
                DB::rollBack();
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            $prevDates = json_decode($condonation->prev_dates, true);
            $postDates = $validated['post_dates'];

            // Recalcular el monto condonado
            $condonedAmount = [
                'total_amount' => ($prevDates['total_amount'] ?? 0) - (float)$postDates['total_amount'],
                'capital' => ($prevDates['capital'] ?? 0) - (float)$postDates['capital'],
                'interest' => ($prevDates['interest'] ?? 0) - (float)$postDates['interest'],
                'mora' => ($prevDates['mora'] ?? 0) - (float)$postDates['mora'],
                'safe' => ($prevDates['safe'] ?? 0) - (float)$postDates['safe'],
                'management_collection_expenses' => ($prevDates['management_collection_expenses'] ?? 0) - (float)$postDates['management_collection_expenses'],
                'collection_expenses' => ($prevDates['collection_expenses'] ?? 0) - (float)$postDates['collection_expenses'],
                'legal_expenses' => ($prevDates['legal_expenses'] ?? 0) - (float)$postDates['legal_expenses'],
                'other_values' => ($prevDates['other_values'] ?? 0) - (float)$postDates['other_values'],
            ];

            // Restar valores del crédito
            $credit->update([
                'total_amount' => (float)$postDates['total_amount'],
                'capital' => (float)$postDates['capital'],
                'interest' => (float)$postDates['interest'],
                'mora' => (float)$postDates['mora'],
                'safe' => (float)$postDates['safe'],
                'management_collection_expenses' => (float)$postDates['management_collection_expenses'],
                'collection_expenses' => (float)$postDates['collection_expenses'],
                'legal_expenses' => (float)$postDates['legal_expenses'],
                'other_values' => (float)$postDates['other_values'],
            ]);

            // Actualizar condonación y cambiar status a APLICADA
            $condonation->update([
                'post_dates' => json_encode($postDates),
                'amount' => (float)$condonedAmount['total_amount'],
                'updated_by' => $user->id,
                'status' => 'AUTORIZADA',
            ]);

            DB::commit();

            // Cargar relaciones para el Resource
            $condonation->load(['credit.clients', 'creator']);

            return ResponseBase::success(
                new \App\Http\Resources\CondonationResource($condonation),
                'Condonación actualizada exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseBase::error(
                'Error al actualizar la condonación',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    //  Autorizar condonación
    public function authorizeCondonation(string $id, Request $request)
    {
        DB::beginTransaction();

        try {
            $user = $request->user();
            if (!$user) {
                DB::rollBack();
                return ResponseBase::unauthorized('Usuario no autenticado');
            }

            $condonation = Condonation::where('id', $id)
                ->where('status', 'PENDIENTE')
                ->first();

            if (!$condonation) {
                DB::rollBack();
                return ResponseBase::notFound('Condonación no encontrada o no está en estado PENDIENTE');
            }

            $credit = Credit::lockForUpdate()->find($condonation->credit_id);

            if (!$credit) {
                DB::rollBack();
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            // Obtener los valores post_dates para aplicar al crédito
            $postDates = json_decode($condonation->post_dates, true);

            if (!$postDates) {
                DB::rollBack();
                return ResponseBase::error('No se encontraron valores para aplicar', null, 400);
            }

            // Restar valores del crédito
            $credit->update([
                'total_amount' => (float)$postDates['total_amount'],
                'capital' => (float)$postDates['capital'],
                'interest' => (float)$postDates['interest'],
                'mora' => (float)$postDates['mora'],
                'safe' => (float)$postDates['safe'],
                'management_collection_expenses' => (float)$postDates['management_collection_expenses'],
                'collection_expenses' => (float)$postDates['collection_expenses'],
                'legal_expenses' => (float)$postDates['legal_expenses'],
                'other_values' => (float)$postDates['other_values'],
            ]);

            // Actualizar condonación
            $condonation->status = 'AUTORIZADA';
            $condonation->updated_by = $user->id;
            $condonation->save();

            DB::commit();

            // Cargar relaciones para el Resource
            $condonation->load(['credit.clients', 'creator']);

            return ResponseBase::success(
                new \App\Http\Resources\CondonationResource($condonation),
                'Condonación autorizada correctamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseBase::error(
                'Error al autorizar la condonación',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Comprueba si un crédito ya tuvo (o tiene) condonaciones.
     *
     * @param int $creditId
     * @param array|null $statuses Opcional: lista de estados a filtrar (si es null comprueba cualquier condonación)
     * @return bool
     */
    protected function creditHasCondonation(int $creditId, ?array $statuses = null): bool
    {
        $query = Condonation::where('credit_id', $creditId);

        if (is_array($statuses) && count($statuses) > 0) {
            $query->whereIn('status', $statuses);
        }

        return $query->exists();
    }

    /**
     * Endpoint público para comprobar si un crédito ya tuvo condonaciones.
     * Query param opcional `statuses` acepta lista separada por comas para filtrar por estados.
     */
    public function checkCreditCondonation(Request $request, int $creditId)
    {
        try {
            $statusesParam = $request->query('statuses');
            $statuses = null;
            if (!empty($statusesParam)) {
                $statuses = array_filter(array_map('trim', explode(',', $statusesParam)));
            }

            $exists = $this->creditHasCondonation($creditId, $statuses);

            return ResponseBase::success([
                'credit_id' => $creditId,
                'has_condonation' => $exists,
                'statuses' => $statuses
            ], 'Comprobación de condonación realizada correctamente');
        } catch (\Exception $e) {
            return ResponseBase::error('Error al comprobar condonaciones del crédito', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Revert a condonation (restore previous credit values)
     */
    public function revert(string $id, Request $request)
    {
        DB::beginTransaction();

        try {
            $user = $request->user();
            if (!$user) {
                DB::rollBack();
                return ResponseBase::unauthorized('Usuario no autenticado');
            }

            $condonation = Condonation::with('credit')->find($id);

            if (!$condonation) {
                DB::rollBack();
                return ResponseBase::notFound('Condonación no encontrada');
            }

            if ($condonation->status === 'REVERTIDA') {
                DB::rollBack();
                return ResponseBase::error(
                    'Esta condonación ya fue revertida',
                    null,
                    400
                );
            }

            if ($condonation->status !== 'AUTORIZADA') {
                DB::rollBack();
                return ResponseBase::error(
                    'Solo se pueden revertir condonaciones en estado APLICADA',
                    null,
                    400
                );
            }

            // Validar que no hayan pasado más de 24 horas
            $createdAt = \Carbon\Carbon::parse($condonation->created_at);
            $now = \Carbon\Carbon::now();
            $hoursDiff = $createdAt->diffInHours($now);

            if ($hoursDiff > 24) {
                DB::rollBack();
                return ResponseBase::error(
                    'Solo se puede revertir una condonación dentro de las 24 horas de su creación',
                    ['horas_transcurridas' => $hoursDiff],
                    400
                );
            }

            $prevDates = json_decode($condonation->prev_dates, true);
            $postDates = json_decode($condonation->post_dates, true);

            if (!$prevDates || !$postDates) {
                DB::rollBack();
                return ResponseBase::error(
                    'No se encontraron valores para revertir',
                    null,
                    400
                );
            }

            $credit = Credit::lockForUpdate()->find($condonation->credit_id);

            if (!$credit) {
                DB::rollBack();
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            // Calcular valores condonados y sumarlos al crédito actual
            $condonedAmount = [
                'total_amount' => (float)$prevDates['total_amount'] - (float)$postDates['total_amount'],
                'capital' => (float)$prevDates['capital'] - (float)$postDates['capital'],
                'interest' => (float)$prevDates['interest'] - (float)$postDates['interest'],
                'mora' => (float)$prevDates['mora'] - (float)$postDates['mora'],
                'safe' => (float)$prevDates['safe'] - (float)$postDates['safe'],
                'management_collection_expenses' => (float)$prevDates['management_collection_expenses'] - (float)$postDates['management_collection_expenses'],
                'collection_expenses' => (float)$prevDates['collection_expenses'] - (float)$postDates['collection_expenses'],
                'legal_expenses' => (float)$prevDates['legal_expenses'] - (float)$postDates['legal_expenses'],
                'other_values' => (float)$prevDates['other_values'] - (float)$postDates['other_values'],
            ];

            // Sumar los valores condonados al crédito actual
            $credit->update([
                'total_amount' => (float)$credit->total_amount + $condonedAmount['total_amount'],
                'capital' => (float)$credit->capital + $condonedAmount['capital'],
                'interest' => (float)$credit->interest + $condonedAmount['interest'],
                'mora' => (float)$credit->mora + $condonedAmount['mora'],
                'safe' => (float)$credit->safe + $condonedAmount['safe'],
                'management_collection_expenses' => (float)$credit->management_collection_expenses + $condonedAmount['management_collection_expenses'],
                'collection_expenses' => (float)$credit->collection_expenses + $condonedAmount['collection_expenses'],
                'legal_expenses' => (float)$credit->legal_expenses + $condonedAmount['legal_expenses'],
                'other_values' => (float)$credit->other_values + $condonedAmount['other_values'],
            ]);

            $condonation->update([
                'status' => 'REVERTIDA',
                'reverted_by' => $user->id,
                'reverted_at' => now(),
            ]);

            DB::commit();

            $condonation->load(['credit.clients', 'creator', 'reverter']);

            return ResponseBase::success(
                new \App\Http\Resources\CondonationResource($condonation),
                'Condonación revertida exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseBase::error(
                'Error al revertir la condonación',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Deny a condonation
     */
    public function denyCondonation(string $id, Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return ResponseBase::unauthorized('Usuario no autenticado');
            }

            $condonation = Condonation::where('id', $id)
                ->where('status', 'PENDIENTE')
                ->first();

            if (!$condonation) {
                return ResponseBase::notFound('Condonación no encontrada o no está en estado PENDIENTE');
            }

            $condonation->update([
                'status' => 'DENEGADA',
                'updated_by' => $user->id,
            ]);

            // Cargar relaciones para el Resource
            $condonation->load(['credit.clients', 'creator']);

            return ResponseBase::success(
                new \App\Http\Resources\CondonationResource($condonation),
                'Condonación denegada correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al denegar la condonación',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}