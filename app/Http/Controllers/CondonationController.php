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
            Log::error('Error fetching condonations', [
                'message' => $e->getMessage()
            ]);

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

            $condonation = Condonation::create([
                'credit_id' => $validated['credit_id'],
                'prev_dates' => json_encode($prevDates),
                'post_dates' => json_encode($postDates),
                'amount' => (float)$condonedAmount['total_amount'],
                'status' => 'APLICADA',
                'created_by' => $user->id,
            ]);

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

            Log::error('Error creating condonation', [
                'message' => $e->getMessage(),
                'payload' => $request->all()
            ]);

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
            Log::error('Error fetching condonation', [
                'message' => $e->getMessage(),
                'id' => $id
            ]);

            return ResponseBase::error(
                'Error al obtener la condonación',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    //  Autorizar condonación
    public function authorizeCondonation(string $id, Request $request)
    {
        $condonation = Condonation::where('credit_id', $id)
            ->where('status', 'PENDIENTE')
            ->first();

        if (!$condonation) {
            return ResponseBase::notFound('Condonación no encontrada o no está en estado PENDIENTE');
        }
        $condonation->status = 'AUTORIZADA';
        $condonation->updated_by = $request->user()->id;
        $condonation->save();

        // Cargar relaciones para el Resource
        $condonation->load(['credit.clients', 'creator']);

        return ResponseBase::success(
            new \App\Http\Resources\CondonationResource($condonation),
            'Condonación autorizada correctamente'
        );
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

            $prevDates = json_decode($condonation->prev_dates, true);

            if (!$prevDates) {
                DB::rollBack();
                return ResponseBase::error(
                    'No se encontraron valores previos para revertir',
                    null,
                    400
                );
            }

            $credit = Credit::lockForUpdate()->find($condonation->credit_id);

            if (!$credit) {
                DB::rollBack();
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            $credit->update([
                'total_amount' => (float)$prevDates['total_amount'],
                'capital' => (float)$prevDates['capital'],
                'interest' => (float)$prevDates['interest'],
                'mora' => (float)$prevDates['mora'],
                'safe' => (float)$prevDates['safe'],
                'management_collection_expenses' => (float)$prevDates['management_collection_expenses'],
                'collection_expenses' => (float)$prevDates['collection_expenses'],
                'legal_expenses' => (float)$prevDates['legal_expenses'],
                'other_values' => (float)$prevDates['other_values'],
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

            Log::error('Error reverting condonation', [
                'message' => $e->getMessage(),
                'condonation_id' => $id
            ]);

            return ResponseBase::error(
                'Error al revertir la condonación',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}