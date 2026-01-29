<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\Credit;
use App\Models\LegalExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LegalExpenseController extends Controller
{
    /**
     * Listar gastos judiciales
     *
     * @OA\Get(
     *     path="/api/legal-expenses",
     *     summary="Listar gastos judiciales",
     *     description="Obtiene listado de gastos judiciales, opcionalmente filtrado por credit_id",
     *     operationId="getLegalExpensesList",
     *     tags={"Gastos Judiciales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="credit_id",
     *         in="query",
     *         description="ID del crédito para filtrar",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="ID de la empresa",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de gastos judiciales obtenida correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Gastos judiciales obtenidos correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="credit_id", type="integer"),
     *                     @OA\Property(property="business_id", type="integer"),
     *                     @OA\Property(property="created_by", type="integer"),
     *                     @OA\Property(property="creator_name", type="string"),
     *                     @OA\Property(property="modify_date", type="string", format="date"),
     *                     @OA\Property(property="prev_amount", type="number"),
     *                     @OA\Property(property="post_amount", type="number"),
     *                     @OA\Property(property="detail", type="string"),
     *                     @OA\Property(property="total_value", type="number"),
     *                     @OA\Property(property="sync_id", type="string"),
     *                     @OA\Property(property="created_at", type="string"),
     *                     @OA\Property(property="updated_at", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $query = LegalExpense::with('creator');

            if ($request->has('credit_id')) {
                $query->where('credit_id', $request->query('credit_id'));
            }

            if ($request->has('business_id')) {
                $query->where('business_id', $request->query('business_id'));
            }

            $expenses = $query->orderBy('created_at', 'desc')->get();

            $result = $expenses->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'credit_id' => $expense->credit_id,
                    'business_id' => $expense->business_id,
                    'created_by' => $expense->created_by,
                    'creator_name' => $expense->creator?->name,
                    'modify_date' => $expense->modify_date?->format('Y-m-d'),
                    'prev_amount' => (float) $expense->prev_amount,
                    'post_amount' => (float) $expense->post_amount,
                    'detail' => $expense->detail,
                    'total_value' => (float) $expense->total_value,
                    'sync_id' => $expense->sync_id,
                    'created_at' => $expense->created_at,
                    'updated_at' => $expense->updated_at,
                ];
            });

            return ResponseBase::success(
                $result,
                'Gastos judiciales obtenidos correctamente'
            );
        } catch (\Exception $e) {
            Log::error('Error al obtener gastos judiciales', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al obtener gastos judiciales',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Actualizar gasto judicial de un crédito
     *
     * @OA\Patch(
     *     path="/api/legal-expenses/{creditId}",
     *     summary="Actualizar gasto judicial",
     *     description="Actualiza el legal_expenses y total_amount del crédito, e inserta un registro en legal_expenses",
     *     operationId="updateLegalExpense",
     *     tags={"Gastos Judiciales"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="creditId",
     *         in="path",
     *         description="ID del crédito",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"post_amount", "detail"},
     *             @OA\Property(property="post_amount", type="number", description="Nuevo monto de gastos judiciales"),
     *             @OA\Property(property="detail", type="string", description="Detalle del gasto"),
     *             @OA\Property(property="created_by", type="integer", description="ID del usuario que realiza el cambio")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Gasto judicial actualizado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Gasto judicial actualizado correctamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="credit_id", type="integer"),
     *                 @OA\Property(property="prev_legal_expenses", type="number"),
     *                 @OA\Property(property="new_legal_expenses", type="number"),
     *                 @OA\Property(property="new_total_amount", type="number"),
     *                 @OA\Property(property="legal_expense_id", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Crédito no encontrado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function update(Request $request, int $creditId)
    {
        try {
            $request->validate([
                'post_amount' => 'required|numeric|min:0',
                'detail' => 'required|string|max:255',
                'created_by' => 'nullable|integer',
            ]);

            $credit = Credit::find($creditId);

            if (!$credit) {
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            DB::beginTransaction();

            $prevLegalExpenses = (float) ($credit->legal_expenses ?? 0);
            $postAmount = (float) $request->input('post_amount');

            $difference = $postAmount - $prevLegalExpenses;
            $newTotalAmount = (float) ($credit->total_amount ?? 0) + $difference;

            $credit->legal_expenses = $postAmount;
            $credit->total_amount = $newTotalAmount;
            $credit->save();

            $legalExpense = LegalExpense::create([
                'credit_id' => $creditId,
                'business_id' => $credit->business_id,
                'created_by' => $request->input('created_by'),
                'modify_date' => now()->toDateString(),
                'prev_amount' => $prevLegalExpenses,
                'post_amount' => $postAmount,
                'detail' => $request->input('detail'),
                'total_value' => $newTotalAmount,
            ]);

            DB::commit();

            return ResponseBase::success(
                [
                    'credit_id' => $credit->id,
                    'prev_legal_expenses' => $prevLegalExpenses,
                    'new_legal_expenses' => $postAmount,
                    'new_total_amount' => $newTotalAmount,
                    'legal_expense_id' => $legalExpense->id,
                ],
                'Gasto judicial actualizado correctamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar gasto judicial', [
                'credit_id' => $creditId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al actualizar gasto judicial',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
