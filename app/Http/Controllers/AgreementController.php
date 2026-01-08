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

            $query->with(['credit.clients', 'creator', 'updater']);

            $agreements = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return ResponseBase::success(
                \App\Http\Resources\AgreementResource::collection($agreements),
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

            // Validar que no exista un convenio previo activo
            $existingAgreement = Agreement::where('credit_id', $validated['credit_id'])
                ->whereIn('status', ['pendiente', 'autorizado'])
                ->first();
            
            if ($existingAgreement) {
                DB::rollBack();
                return ResponseBase::error(
                    'Ya existe un convenio activo para este crédito',
                    null,
                    400
                );
            }

            // Validar que el crédito no tenga una condonación activa
            $activeCondonation = \App\Models\Condonation::where('credit_id', $validated['credit_id'])
                ->whereIn('status', ['PENDIENTE', 'APLICADA'])
                ->first();
            
            if ($activeCondonation) {
                DB::rollBack();
                return ResponseBase::error(
                    'El crédito tiene una condonación activa, no se puede crear un convenio',
                    null,
                    400
                );
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

            // Verificar si el usuario es admin
            $isAdmin = in_array(strtolower($user->role), ['admin', 'superadmin']);

            $agreementData = [
                'credit_id' => $validated['credit_id'],
                'total_amount' => $validated['total_amount'],
                'invoice_id' => $validated['invoice_id'] ?? null,
                'total_fees' => $totalFees,
                'paid_fees' => $paidFees,
                'fee_amount' => $validated['fee_amount'],
                'fee_detail' => json_encode($validated['fee_detail']),
                'created_by' => $user->id,
            ];

            if ($isAdmin) {
                // Admin: status autorizado y updated_by, actualiza el crédito
                $agreementData['status'] = 'AUTORIZADO';
                $agreementData['updated_by'] = $user->id;

                $credit->update([
                    'collection_state' => 'CONVENIO DE PAGO',
                    'pending_fees' => $totalFees - $paidFees,
                    'paid_fees' => $paidFees,
                ]);
            } else {
                // No admin: status pendiente, no actualiza el crédito
                $agreementData['status'] = 'PENDIENTE';
            }

            $agreement = Agreement::create($agreementData);

            DB::commit();

            $agreement->load(['credit.clients', 'creator']);

            return ResponseBase::success(
                new \App\Http\Resources\AgreementResource($agreement),
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
            $agreement = Agreement::with(['credit.clients', 'creator', 'updater'])->find($id);

            if (!$agreement) {
                return ResponseBase::notFound('Convenio no encontrado');
            }

            return ResponseBase::success(
                new \App\Http\Resources\AgreementResource($agreement),
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
                    'Solo los administradores pueden actualizar convenios',
                    null,
                    403
                );
            }

            $agreement = Agreement::find($id);

            if (!$agreement) {
                DB::rollBack();
                return ResponseBase::notFound('Convenio no encontrado');
            }

            // Solo se pueden actualizar convenios en estado pendiente
            if ($agreement->status !== 'PENDIENTE') {
                DB::rollBack();
                return ResponseBase::error(
                    'Solo se pueden actualizar convenios en estado pendiente',
                    null,
                    400
                );
            }

            $validated = $request->validate([
                'total_amount' => ['required', 'numeric', 'min:0'],
                'fee_amount' => ['required', 'numeric', 'min:0'],
                'fee_detail' => ['required', 'array', 'min:2'],
                'fee_detail.*.payment_date' => ['required', 'date'],
                'fee_detail.*.payment_value' => ['required', 'numeric', 'min:0'],
                'fee_detail.*.payment_amount' => ['required', 'numeric', 'min:0'],
                'fee_detail.*.payment_status' => ['required', 'string', 'in:PENDIENTE,PAGADA'],
            ]);

            $credit = Credit::lockForUpdate()->find($agreement->credit_id);

            if (!$credit) {
                DB::rollBack();
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            $feeDetailArray = $validated['fee_detail'];
            $totalFees = count($feeDetailArray) - 1;

            $paidFees = 0;

            foreach (array_slice($feeDetailArray, 1) as $fee) {
                if ($fee['payment_status'] === 'PAGADA') {
                    $paidFees++;
                }
            }

            // Actualizar el crédito
            $credit->update([
                'collection_state' => 'CONVENIO DE PAGO',
                'pending_fees' => $totalFees - $paidFees,
                'paid_fees' => $paidFees,
            ]);

            // Actualizar convenio y cambiar status a autorizado
            $agreement->update([
                'total_amount' => $validated['total_amount'],
                'total_fees' => $totalFees,
                'paid_fees' => $paidFees,
                'fee_amount' => $validated['fee_amount'],
                'fee_detail' => json_encode($validated['fee_detail']),
                'updated_by' => $user->id,
                'status' => 'AUTORIZADO',
            ]);

            DB::commit();

            $agreement->load(['credit.clients', 'creator', 'updater']);

            return ResponseBase::success(
                new \App\Http\Resources\AgreementResource($agreement),
                'Convenio actualizado exitosamente'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return ResponseBase::validationError($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating agreement', [
                'message' => $e->getMessage(),
                'id' => $id
            ]);

            return ResponseBase::error(
                'Error al actualizar el convenio',
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

            $agreement = Agreement::where('id', $id)
                ->where('status', 'PENDIENTE')
                ->first();

            if (!$agreement) {
                DB::rollBack();
                return ResponseBase::notFound('Convenio no encontrado o no está en estado pendiente');
            }

            $credit = Credit::lockForUpdate()->find($agreement->credit_id);

            if (!$credit) {
                DB::rollBack();
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            // Calcular cuotas del convenio
            $feeDetail = json_decode($agreement->fee_detail, true);
            $totalFees = count($feeDetail) - 1;
            $paidFees = 0;

            foreach (array_slice($feeDetail, 1) as $fee) {
                if ($fee['payment_status'] === 'PAGADA') {
                    $paidFees++;
                }
            }

            // Actualizar el crédito
            $credit->update([
                'collection_state' => 'CONVENIO DE PAGO',
                'pending_fees' => $totalFees - $paidFees,
                'paid_fees' => $paidFees,
            ]);

            // Actualizar convenio
            $agreement->update([
                'status' => 'AUTORIZADO',
                'updated_by' => $user->id,
            ]);

            DB::commit();

            $agreement->load(['credit.clients', 'creator', 'updater']);

            return ResponseBase::success(
                new \App\Http\Resources\AgreementResource($agreement),
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
     * Revert an agreement
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

            $agreement = Agreement::with('credit')->find($id);

            if (!$agreement) {
                DB::rollBack();
                return ResponseBase::notFound('Convenio no encontrado');
            }

            if ($agreement->status === 'REVERTIDO') {
                DB::rollBack();
                return ResponseBase::error(
                    'Este convenio ya fue revertido',
                    null,
                    400
                );
            }

            if ($agreement->status !== 'AUTORIZADO') {
                DB::rollBack();
                return ResponseBase::error(
                    'Solo se pueden revertir convenios en estado autorizado',
                    null,
                    400
                );
            }

            $credit = Credit::lockForUpdate()->find($agreement->credit_id);

            if (!$credit) {
                DB::rollBack();
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            // Revertir el estado del crédito
            $credit->update([
                'collection_state' => 'VENCIDO',
                'pending_fees' => 0,
                'paid_fees' => 0,
            ]);

            // Actualizar el convenio
            $agreement->update([
                'status' => 'REVERTIDO',
                'updated_by' => $user->id,
            ]);

            DB::commit();

            $agreement->load(['credit.clients', 'creator', 'updater']);

            return ResponseBase::success(
                new \App\Http\Resources\AgreementResource($agreement),
                'Convenio revertido exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error reverting agreement', [
                'message' => $e->getMessage(),
                'agreement_id' => $id
            ]);

            return ResponseBase::error(
                'Error al revertir el convenio',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Deny an agreement
     */
    public function denyAgreement(string $id, Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return ResponseBase::unauthorized('Usuario no autenticado');
            }

            $agreement = Agreement::where('id', $id)
                ->where('status', 'PENDIENTE')
                ->first();

            if (!$agreement) {
                return ResponseBase::notFound('Convenio no encontrado o no está en estado pendiente');
            }

            $agreement->update([
                'status' => 'DENEGADO',
                'updated_by' => $user->id,
            ]);

            $agreement->load(['credit.clients', 'creator', 'updater']);

            return ResponseBase::success(
                new \App\Http\Resources\AgreementResource($agreement),
                'Convenio denegado correctamente'
            );
        } catch (\Exception $e) {
            Log::error('Error denegando convenio', [
                'message' => $e->getMessage(),
                'agreement_id' => $id
            ]);

            return ResponseBase::error(
                'Error al denegar el convenio',
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

            if ($agreement->status === 'AUTORIZADO') {
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
    }
}