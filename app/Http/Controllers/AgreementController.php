<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Models\Agreement;
use App\Models\Credit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgreementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->query('per_page', 15);

            $query = Agreement::query();

            if ($request->filled('credit_id')) {
                $query->where('credit_id', $request->query('credit_id'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }

            $agreements = $query->with('credit', 'creator', 'updater')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return ResponseBase::success(
                $agreements,
                'Convenios obtenidos correctamente'
            );
        } catch (\Exception $e) {
            Log::error('Error fetching agreements', [
                'message' => $e->getMessage()
            ]);

            return ResponseBase::error(
                'Error al obtener convenios',
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
                'total_amount' => ['required', 'numeric', 'min:0'],
                'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
                'fee_amount' => ['required', 'numeric', 'min:0'],
                'fee_detail' => ['required', 'array', 'min:2'],
                'fee_detail.*.payment_date' => ['required', 'date'],
                'fee_detail.*.payment_value' => ['required', 'numeric', 'min:0'],
                'fee_detail.*.payment_amount' => ['required', 'numeric', 'min:0'],
                'fee_detail.*.payment_status' => ['required', 'string', 'in:PENDIENTE,PAGADA'],
            ]);

            $credit = Credit::lockForUpdate()->find($validated['credit_id']);

            if (!$credit) {
                DB::rollBack();
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            $this->generateCollectionExpense($credit, $validated);

            $feeDetailArray = $validated['fee_detail'];
            $totalFees = count($feeDetailArray) - 1;

            $paidFees = 0;

            foreach (array_slice($feeDetailArray, 1) as $fee) {
                if ($fee['payment_status'] === 'PAGADA') {
                    $paidFees++;
                }
            }

            $agreement = Agreement::create([
                'credit_id' => $validated['credit_id'],
                'total_amount' => $validated['total_amount'],
                'invoice_id' => $validated['invoice_id'] ?? null,
                'total_fees' => $totalFees,
                'paid_fees' => $paidFees,
                'fee_amount' => $validated['fee_amount'],
                'fee_detail' => json_encode($validated['fee_detail']),
                'created_by' => $user->id,
                'status' => 'pendiente',
            ]);

            $credit->update([
                'collection_state' => 'CONVENIO DE PAGO',
                'pending_fees' => $totalFees - $paidFees,
                'paid_fees' => $paidFees,
            ]);

            DB::commit();

            return ResponseBase::success(
                $agreement->load('credit', 'creator'),
                'Convenio de pago creado exitosamente',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating agreement', [
                'message' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return ResponseBase::error(
                'Error al crear el convenio',
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
            $agreement = Agreement::with('credit', 'creator', 'updater')->find($id);

            if (!$agreement) {
                return ResponseBase::notFound('Convenio no encontrado');
            }

            $agreement->fee_detail = json_decode($agreement->fee_detail, true);

            return ResponseBase::success(
                $agreement,
                'Convenio obtenido correctamente'
            );
        } catch (\Exception $e) {
            Log::error('Error fetching agreement', [
                'message' => $e->getMessage(),
                'id' => $id
            ]);

            return ResponseBase::error(
                'Error al obtener el convenio',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Authorize an agreement
     */
    public function authorizeAgreement(Request $request, string $id)
    {
        DB::beginTransaction();

        try {
            $user = $request->user();
            if (!$user) {
                DB::rollBack();
                return ResponseBase::unauthorized('Usuario no autenticado');
            }

            $agreement = Agreement::with('credit')->find($id);

            if (!$agreement) {
                DB::rollBack();
                return ResponseBase::notFound('Convenio no encontrado');
            }

            if ($agreement->status === 'autorizado') {
                DB::rollBack();
                return ResponseBase::error('El convenio ya está autorizado', null, 400);
            }

            $agreement->update([
                'status' => 'autorizado',
                'updated_by' => $user->id,
            ]);

            DB::commit();

            return ResponseBase::success(
                $agreement->load('credit', 'creator', 'updater'),
                'Convenio autorizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error authorizing agreement', [
                'message' => $e->getMessage(),
                'agreement_id' => $id
            ]);

            return ResponseBase::error(
                'Error al autorizar el convenio',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();

        try {
            $agreement = Agreement::with('credit')->find($id);

            if (!$agreement) {
                DB::rollBack();
                return ResponseBase::notFound('Convenio no encontrado');
            }

            if ($agreement->status === 'autorizado') {
                $agreement->credit->update([
                    'collection_state' => 'VENCIDO',
                ]);
            }

            $agreement->delete();

            DB::commit();

            return ResponseBase::success(
                null,
                'Convenio eliminado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error deleting agreement', [
                'message' => $e->getMessage(),
                'agreement_id' => $id
            ]);

            return ResponseBase::error(
                'Error al eliminar el convenio',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Genera el gasto de cobranza y lo agrega al fee_detail
     * 
     * @param Credit $credit
     * @param array $validated
     * @return void
     */
    private function generateCollectionExpense(Credit $credit, array &$validated): void
    {
        // TODO: Implementar lógica de generación de gasto de cobranza
    }
}