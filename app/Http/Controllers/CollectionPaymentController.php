<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCollectionPaymentRequest;
use App\Http\Responses\ResponseBase;
use App\Models\CollectionPayment;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Agreement;
use App\Services\SofiaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CollectionPaymentController extends Controller
{
    private SofiaService $sofiaService;

    public function __construct(SofiaService $sofiaService)
    {
        $this->sofiaService = $sofiaService;
    }

    /**
     * Summary of index
     * @description Lista y filtra los pagos de cobranza
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->query('per_page', 15);

            $query = CollectionPayment::query();

            if ($request->filled('credit_id')) {
                $query->where('credit_id', $request->query('credit_id'));
            }

            if ($request->filled('business_id')) {
                $query->where('business_id', $request->query('business_id'));
            }

            if ($request->filled('campain_id')) {
                $query->where('campain_id', $request->query('campain_id'));
            }

            if ($request->filled('payment_method')) {
                $query->where('payment_method', $request->query('payment_method'));
            }

            if ($request->filled('financial_institution')) {
                $query->where('financial_institution', $request->query('financial_institution'));
            }

            if ($request->filled('payment_reference')) {
                $query->where('payment_reference', $request->query('payment_reference'));
            }

            if ($request->filled('with_management')) {
                $query->where('with_management', $request->query('with_management'));
            }

            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->query('payment_status'));
            }

            $orderBy = $request->query('order_by', 'payment_date');
            $orderDir = $request->query('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);

            // Cargar relaciones necesarias para el Resource
            $query->with(['credit.clients', 'campain']);

            $payments = $query->paginate($perPage);

            return ResponseBase::success(
                \App\Http\Resources\CollectionPaymentResource::collection($payments),
                'Pagos obtenidos correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error('Error al obtener pagos', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Summary of store
     * @description Crea un nuevo pago de crédito y actualiza el estado del crédito asociado
     * @param StoreCollectionPaymentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreCollectionPaymentRequest $request)
    {
        Log::channel('payments')->info('Procesando y validando pago', ['payload' => $request->all()]);
        $data = $request->validated();
        Log::channel('payments')->info('Datos validados para el pago', ['data' => $data]);

        DB::beginTransaction();

        try {
            $user = $request->user();
            if (!$user) {
                DB::rollBack();
                return ResponseBase::unauthorized('Token inválido o expirado');
            }

            $credit = Credit::lockForUpdate()->find($data['credit_id']);

            if (!$credit) {
                DB::rollBack();
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            $validationResult = $this->validatePaymentRubros($credit, $data);
            
            if ($validationResult !== null) {
                Log::channel('payments')->warning('Validación de rubros de pago fallida', ['validation_result' => $validationResult]);
                DB::rollBack();
                return ResponseBase::error(
                    $validationResult['summary'],
                    $validationResult['errors'],
                    422
                );
            }

            $data['prev_dates'] = json_encode([
                'capital' => $credit->capital,
                'interest' => $credit->interest,
                'mora' => $credit->mora,
                'safe' => $credit->safe,
                'collection_expenses' => $credit->collection_expenses,
                'legal_expenses' => $credit->legal_expenses,
                'management_collection_expenses' => $credit->management_collection_expenses,
                'other_values' => $credit->other_values,
                'total_amount' => $credit->total_amount,
                'collection_state' => $credit->collection_state
            ]);

            $credit->capital = max(0, $credit->capital - (isset($data['capital']) ? (float)$data['capital'] : 0.0));
            $credit->interest = max(0, $credit->interest - (isset($data['interest']) ? (float)$data['interest'] : 0.0));
            $credit->mora = max(0, $credit->mora - (isset($data['mora']) ? (float)$data['mora'] : 0.0));
            $credit->safe = max(0, $credit->safe - (isset($data['safe']) ? (float)$data['safe'] : 0.0));
            $credit->collection_expenses = max(0, $credit->collection_expenses - (isset($data['collection_expenses']) ? (float)$data['collection_expenses'] : 0.0));
            $credit->legal_expenses = max(0, $credit->legal_expenses - (isset($data['legal_expenses']) ? (float)$data['legal_expenses'] : 0.0));
            $credit->management_collection_expenses = max(0, $credit->management_collection_expenses - (isset($data['management_collection_expenses']) ? (float)$data['management_collection_expenses'] : 0.0));
            $credit->other_values = max(0, $credit->other_values - (isset($data['other_values']) ? (float)$data['other_values'] : 0.0));
            $credit->total_amount =
                $credit->capital +
                $credit->interest +
                $credit->mora +
                $credit->safe +
                $credit->collection_expenses +
                $credit->legal_expenses +
                $credit->management_collection_expenses +
                $credit->other_values;

            if (isset($data['payment_type']) && (string)$data['payment_type'] === 'TOTAL') {
                $credit->collection_state = 'CANCELADO';
            }

            $credit->save();

            if ((string)$credit->collection_state === 'CONVENIO DE PAGO' || (string)$credit->collection_state === 'Convenio de pago') {
                $paymentValue = isset($data['payment_value']) ? (float)$data['payment_value'] : 0.0;
                $this->updateAgreementFees($credit->id, $paymentValue);
            }
            
            if (!empty($data['payment_deposit_date'])) {
                $data['payment_deposit_date'] = Carbon::parse($data['payment_deposit_date']);
                $data['payment_date'] = date('Y-m-d H:i:s', time() - 18000);
            }

            $data['created_by'] = $user->id;

            // Asignar payment_number secuencial global (sin importar credit_id o business_id)
            $lastNumber = DB::table('collection_payments')
                ->whereNotNull('payment_number')
                ->lockForUpdate()
                ->max('payment_number');

            // Si existe un último número, incrementar; si no, empezar en 1
            $data['payment_number'] = $lastNumber ? (int)$lastNumber + 1 : 1;
            $data['payment_status'] = 'guardado';
            
            $payment = CollectionPayment::create($data);

            // Cargar relaciones para el Resource
            $payment->load(['credit.clients', 'campain']);

            DB::commit();
            Log::channel('payments')->info('Pago creado correctamente', ['payment' => $payment]);

            return ResponseBase::success(
                new \App\Http\Resources\CollectionPaymentResource($payment),
                'Pago creado correctamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('payments')->error('Error al crear el pago', [
                'message' => $e->getMessage(),
                'data' => $data
            ]);

            return ResponseBase::error('Error al crear el pago', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Summary of updateAgreementFees
     * @description Actualiza las cuotas pagadas y pendientes de un convenio de pago asociado a un crédito
     * @param int $creditId
     * @param float $amountPaid
     * @return void
     */
    private function updateAgreementFees(int $creditId, float $amountPaid): void
    {
        Log::channel('payments')->info('Actualizando cuotas del convenio', ['credit_id' => $creditId, 'amount_paid' => $amountPaid]);
        $agreement = Agreement::where('credit_id', $creditId)
            ->where('status', 'AUTORIZADO')
            ->first();

        if (!$agreement || empty($agreement->fee_detail)) {
            return;
        }

        $feeDetail = json_decode($agreement->fee_detail, true);

        if (!is_array($feeDetail)) {
            return;
        }

        $remainingAmount = $amountPaid;
        $totalPaidFees = 0;

        foreach ($feeDetail as $index => &$fee) {
            if ($index === 0) {
                continue;
            }

            if (isset($fee['payment_status']) && $fee['payment_status'] === 'PAGADO') {
                continue;
            }

            if ($remainingAmount <= 0) {
                break;
            }

            $pendingBalance = (float)($fee['payment_value'] ?? 0);

            if ($pendingBalance <= 0) {
                $fee['payment_status'] = 'PAGADO';
                $fee['payment_date'] = now()->format('Y-m-d');
                $totalPaidFees++;
                continue;
            }

            if ($remainingAmount >= $pendingBalance) {
                $fee['payment_value'] = 0;
                $fee['payment_status'] = 'PAGADO';
                $fee['payment_date'] = now()->format('Y-m-d');
                $remainingAmount -= $pendingBalance;
                $totalPaidFees++;
            } else {
                $fee['payment_value'] = $pendingBalance - $remainingAmount;
                $remainingAmount = 0;
                break;
            }
        }

        unset($fee);

        $agreement->fee_detail = json_encode($feeDetail);
        $agreement->paid_fees = ($agreement->paid_fees ?? 0) + $totalPaidFees;
        $agreement->save();
        Log::channel('payments')->info('Cuotas del convenio actualizadas', [
            'agreement_id' => $agreement->id,
            'paid_fees' => $agreement->paid_fees,
            'total_paid_fees_added' => $totalPaidFees,
            'remaining_amount' => $remainingAmount
        ]);

        $this->checkAndCancelCreditIfAgreementCompleted($creditId, $agreement);
    }

    /**
     * Verifica si todas las cuotas del convenio están pagadas y actualiza el crédito a CANCELADO
     * @param int $creditId
     * @param Agreement $agreement
     * @return void
     */
    private function checkAndCancelCreditIfAgreementCompleted(int $creditId, Agreement $agreement): void
    {
        $feeDetail = json_decode($agreement->fee_detail, true);

        if (!is_array($feeDetail) || count($feeDetail) <= 1) {
            return;
        }

        $allFeesPaid = true;
        foreach ($feeDetail as $index => $fee) {
            if ($index === 0) {
                continue;
            }

            if (!isset($fee['payment_status']) || $fee['payment_status'] !== 'PAGADO') {
                $allFeesPaid = false;
                break;
            }
        }

        if ($allFeesPaid) {
            $agreement->status = 'COMPLETADO';
            $agreement->save();

            $credit = Credit::find($creditId);
            if ($credit) {
                $credit->collection_state = 'CANCELADO';
                $credit->sync_status = 'INACTIVE';
                $credit->save();
            }

            Log::channel('payments')->info('Convenio completado y crédito cancelado', [
                'credit_id' => $creditId,
                'agreement_id' => $agreement->id,
                'agreement_status' => 'COMPLETADO',
                'collection_state' => 'CANCELADO',
                'sync_status' => 'INACTIVE'
            ]);
        }
    }

    /**
     * Summary of show
     * @description Obtiene los detalles de un pago de cobranza específico
     * @param CollectionPayment $payment
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(CollectionPayment $payment)
    {
        try {
            $payment->load(['credit.clients', 'campain']);

            if ($payment->payment_status === 'ERROR_SUM') {
                $credit = $payment->credit;

                $creditRubros = null;
                if ($credit) {
                    $creditRubros = [
                        'capital' => $credit->capital,
                        'interest' => $credit->interest,
                        'mora' => $credit->mora,
                        'safe' => $credit->safe,
                        'collection_expenses' => $credit->collection_expenses,
                        'legal_expenses' => $credit->legal_expenses,
                        'management_collection_expenses' => $credit->management_collection_expenses,
                        'other_values' => $credit->other_values,
                        'total_amount' => $credit->total_amount,
                        'collection_state' => $credit->collection_state,
                    ];
                }

                $rubros = [
                    'capital',
                    'interest',
                    'mora',
                    'safe',
                    'collection_expenses',
                    'legal_expenses',
                    'management_collection_expenses',
                    'other_values'
                ];

                $rubrosToSubtract = [];
                foreach ($rubros as $rubro) {
                    $value = floatval($payment->{$rubro} ?? 0);
                    if ($value > 0) {
                        $rubrosToSubtract[$rubro] = $value;
                    }
                }

                return ResponseBase::success([
                    'payment' => new \App\Http\Resources\CollectionPaymentResource($payment),
                    'credit_current_rubros' => $creditRubros,
                    'payment_rubros_to_subtract' => $rubrosToSubtract,
                ], 'Pago obtenido correctamente (ERROR_SUM)');
            }

            return ResponseBase::success(
                new \App\Http\Resources\CollectionPaymentResource($payment),
                'Pago obtenido correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error('Error al obtener el pago', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Summary of revertPayment
     * @description Revierte un pago y restaura los valores originales del crédito desde prev_dates
     * @param int $paymentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function revertPayment(int $paymentId)
    {
        DB::beginTransaction();

        try {
            $payment = CollectionPayment::find($paymentId);

            if (!$payment) {
                DB::rollBack();
                return ResponseBase::error('Pago no encontrado', null, 404);
            }

            if (empty($payment->prev_dates)) {
                DB::rollBack();
                return ResponseBase::error('El pago no tiene información de valores previos (prev_dates) para revertir', null, 422);
            }

            // Validar que no hayan pasado más de 24 horas desde la creación del pago
            $paymentCreatedAt = Carbon::parse($payment->created_at);
            $now = Carbon::now();
            $hoursSinceCreation = $paymentCreatedAt->diffInHours($now);

            if ($hoursSinceCreation > 24) {
                DB::rollBack();
                return ResponseBase::error(
                    'No se puede revertir el pago. Han pasado más de 24 horas desde su creación',
                    [
                        'payment_created_at' => $paymentCreatedAt->format('Y-m-d H:i:s'),
                        'hours_since_creation' => $hoursSinceCreation,
                        'max_hours_allowed' => 24
                    ],
                    422
                );
            }

            // Validar que el pago no haya sido revertido previamente
            if ($payment->payment_status === 'revertido') {
                DB::rollBack();
                return ResponseBase::error('El pago ya fue revertido previamente', null, 422);
            }

            // Validar que sea el último pago del crédito (excluyendo pagos revertidos)
            $lastPayment = CollectionPayment::where('credit_id', $payment->credit_id)
                ->where('payment_status', '!=', 'revertido')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if (!$lastPayment || $lastPayment->id !== $payment->id) {
                DB::rollBack();
                return ResponseBase::error(
                    'Solo se puede revertir el último pago registrado del crédito',
                    [
                        'attempted_payment_id' => $payment->id,
                        'last_payment_id' => $lastPayment?->id,
                        'message' => 'Revertir pagos anteriores causaría inconsistencias en los valores del crédito'
                    ],
                    422
                );
            }

            $credit = Credit::lockForUpdate()->find($payment->credit_id);

            if (!$credit) {
                DB::rollBack();
                return ResponseBase::error('Crédito asociado al pago no encontrado', null, 404);
            }

            $prevDates = json_decode($payment->prev_dates, true);

            if (!is_array($prevDates)) {
                DB::rollBack();
                return ResponseBase::error('Los datos de prev_dates no son válidos', null, 422);
            }

            $oldCreditValues = [
                'capital' => $credit->capital,
                'interest' => $credit->interest,
                'mora' => $credit->mora,
                'safe' => $credit->safe,
                'collection_expenses' => $credit->collection_expenses,
                'legal_expenses' => $credit->legal_expenses,
                'management_collection_expenses' => $credit->management_collection_expenses,
                'other_values' => $credit->other_values,
                'total_amount' => $credit->total_amount,
                'collection_state' => $credit->collection_state
            ];

            $credit->capital = floatval($prevDates['capital'] ?? 0);
            $credit->interest = floatval($prevDates['interest'] ?? 0);
            $credit->mora = floatval($prevDates['mora'] ?? 0);
            $credit->safe = floatval($prevDates['safe'] ?? 0);
            $credit->collection_expenses = floatval($prevDates['collection_expenses'] ?? 0);
            $credit->legal_expenses = floatval($prevDates['legal_expenses'] ?? 0);
            $credit->management_collection_expenses = floatval($prevDates['management_collection_expenses'] ?? 0);
            $credit->other_values = floatval($prevDates['other_values'] ?? 0);
            $credit->total_amount = floatval($prevDates['total_amount'] ?? 0);

            // Restaurar el collection_state desde prev_dates
            if (isset($prevDates['collection_state'])) {
                $credit->collection_state = $prevDates['collection_state'];
            } elseif ($credit->collection_state === 'CANCELADO') {
                // Fallback para pagos antiguos sin collection_state en prev_dates
                $credit->collection_state = 'Vencido';
            }

            $credit->save();

            // Revertir cuotas de convenio si el estado restaurado es CONVENIO DE PAGO
            if ((string)$credit->collection_state === 'CONVENIO DE PAGO') {
                $this->revertAgreementFees($credit->id, floatval($payment->payment_value));
            }

            $payment->payment_status = 'revertido';
            $payment->save();

            DB::commit();

            Log::channel('payments')->info('Pago revertido correctamente', [
                'payment_id' => $paymentId,
                'credit_id' => $credit->id,
                'old_values' => $oldCreditValues,
                'restored_values' => $prevDates
            ]);

            return ResponseBase::success([
                'payment' => $payment,
                'credit' => $credit,
                'previous_credit_values' => $oldCreditValues,
                'restored_credit_values' => $prevDates
            ], 'Pago revertido correctamente y crédito restaurado', 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('payments')->error('Error al revertir el pago', [
                'payment_id' => $paymentId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error('Error al revertir el pago', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Summary of revertAgreementFees
     * @description Revierte las cuotas pagadas de un convenio de pago asociado a un crédito
     * @param int $creditId
     * @param float $amountToRevert
     * @return void
     */
    private function revertAgreementFees(int $creditId, float $amountToRevert): void
    {
        Log::channel('payments')->info('Revirtiendo cuotas del convenio', ['credit_id' => $creditId, 'amount_to_revert' => $amountToRevert]);

        $agreement = Agreement::where('credit_id', $creditId)
            ->where('status', 'autorizado')
            ->first();

        if (!$agreement || empty($agreement->fee_detail)) {
            return;
        }

        $feeDetail = json_decode($agreement->fee_detail, true);

        if (!is_array($feeDetail)) {
            return;
        }

        $remainingAmount = $amountToRevert;
        $totalRevertedFees = 0;

        for ($index = count($feeDetail) - 1; $index > 0; $index--) {
            $fee = &$feeDetail[$index];

            if (!isset($fee['payment_status'])) {
                continue;
            }

            if ($remainingAmount <= 0) {
                break;
            }

            $currentPaid = floatval($fee['payment_value'] ?? 0);

            if ($currentPaid <= 0) {
                continue;
            }

            if ($fee['payment_status'] === 'PAGADO') {
                $feeAmount = floatval($fee['payment_amount'] ?? 0);

                if ($remainingAmount >= $feeAmount) {
                    $fee['payment_value'] = 0;
                    $fee['payment_status'] = 'PENDIENTE';
                    unset($fee['payment_date']);
                    $remainingAmount -= $feeAmount;
                    $totalRevertedFees++;
                } else {
                    $fee['payment_value'] = $feeAmount - $remainingAmount;
                    $fee['payment_status'] = 'PENDIENTE';
                    unset($fee['payment_date']);
                    $remainingAmount = 0;
                }
            } elseif ($fee['payment_status'] === 'PENDIENTE' && $currentPaid > 0) {
                if ($remainingAmount >= $currentPaid) {
                    $fee['payment_value'] = 0;
                    $remainingAmount -= $currentPaid;
                } else {
                    $fee['payment_value'] = $currentPaid - $remainingAmount;
                    $remainingAmount = 0;
                }
            }
        }

        unset($fee);

        $agreement->fee_detail = json_encode($feeDetail);
        $agreement->paid_fees = max(0, ($agreement->paid_fees ?? 0) - $totalRevertedFees);
        $agreement->save();

        Log::channel('payments')->info('Cuotas del convenio revertidas', [
            'agreement_id' => $agreement->id,
            'paid_fees' => $agreement->paid_fees,
            'reverted_fees' => $totalRevertedFees
        ]);
    }

    /**
     * Summary of processInvoice
     * @description Procesa la factura asociada a un gasto de cobranza
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processInvoice(Request $request)
    {
        $payload = $request->all();
        Log::info('processInvoice payload', ['payload' => $payload]);

        try {
            $creditId = $request->input('credito') ?? $request->input('credit_id');
            if (!$creditId) {
                return ResponseBase::validationError(['credito' => ['El id del crédito es obligatorio.']]);
            }

            // Obtener el crédito
            $credit = Credit::find($creditId);
            if (!$credit) {
                return ResponseBase::error('Crédito no encontrado', null, 404);
            }

            // Obtener el cliente titular del crédito con sus datos
            $client = DB::table('client_credit as cc')
                ->join('clients as cl', 'cl.id', '=', 'cc.client_id')
                ->where('cc.credit_id', $creditId)
                ->where('cc.type', 'TITULAR')
                ->select('cl.id', 'cl.ci', 'cl.name')
                ->first();

            if (!$client) {
                return ResponseBase::error('No se encontró cliente titular para el crédito', null, 404);
            }

            // Obtener el teléfono del cliente (primer contacto activo)
            $phone = DB::table('collection_contacts')
                ->where('client_id', $client->id)
                ->where('phone_status', 'ACTIVE')
                ->value('phone_number');

            // Obtener la dirección del cliente (primera dirección disponible)
            $direction = DB::table('collection_directions')
                ->where('client_id', $client->id)
                ->orderBy('id', 'desc')
                ->value('address');

            if (!$direction) {
                $direction = 'Sin dirección registrada';
            }

            // Obtener el nombre del business/cartera
            $cartera = $request->input('cartera');
            $businessName = null;
            if ($cartera) {
                $businessName = DB::table('businesses')->where('id', $cartera)->value('name');
            }

            $paymentValue = floatval($request->input('payment_value', $request->input('value', 0)));
            $paymentMethod = strtoupper($request->input('payment_method', $request->input('metodo', 'EFECTIVO')));
            $financialInstitution = $request->input('financial_institution', $request->input('idBanco'));
            $paymentReference = $request->input('payment_reference', $request->input('referencia'));

            // Preparar datos para Sofia
            $syntheticPayload = [
                'value' => $paymentValue,
                'metodo' => $paymentMethod,
                'idBanco' => $financialInstitution,
                'referencia' => $paymentReference,
                'ci' => $client->ci,
                'name' => $client->name,
                'telefono' => $phone ?? '0000000000',
                'email' => $client->ci . '@sinmail.com',
                'formaPago' => $request->input('formaPago', 'OTROS_FINANCIERO'),
                'cartera' => $businessName,
            ];

            Log::channel('payments')->info('processInvoice: Data a enviar a Sofia', [
                'credit_id' => $creditId,
                'payload' => $syntheticPayload
            ]);

            $syntheticRequest = \Illuminate\Http\Request::create('/', 'POST', $syntheticPayload);
            $sofiaResult = $this->sofiaService->facturar($syntheticRequest, $paymentValue);
            Log::channel('payments')->info('SofiaService.facturar result', ['result' => $sofiaResult]);

            if (isset($sofiaResult['state']) && $sofiaResult['state'] === 200 && isset($sofiaResult['response']->claveAcceso)) {
                $ivaPercent = 15;
                $totalConIva = round($paymentValue, 2);
                $subtotalSinIva = round($totalConIva / (1 + ($ivaPercent / 100)), 2);
                $valorIva = round($totalConIva - $subtotalSinIva, 2);

                $invoice = Invoice::create([
                    "invoice_value" => $totalConIva,
                    "tax_value" => $ivaPercent,
                    "invoice_institution" => $financialInstitution ?? '',
                    "invoice_method" => $paymentMethod,
                    "invoice_access_key" => $sofiaResult['response']->claveAcceso,
                    "invoice_number" => $paymentReference ?? '',
                    "invoice_date" => date('Y-m-d'),
                    "credit_id" => $creditId,
                    "client_id" => $client->id,
                    "status" => "finalizado",
                    "created_by" => $request->user()->id ?? 1
                ]);

                Log::channel('payments')->info('processInvoice: Invoice creado', [
                    'invoice_id' => $invoice->id,
                    'credit_id' => $creditId,
                    'client_id' => $client->id,
                    'clave_acceso' => $sofiaResult['response']->claveAcceso
                ]);

                // Si el crédito tiene convenio de pago, marcar la primera cuota (gasto de cobranza) como PAGADA
                if ($credit->collection_state === 'CONVENIO DE PAGO' || $credit->collection_state === 'Convenio de pago') {
                    $agreement = Agreement::where('credit_id', $creditId)
                        ->where('status', 'AUTORIZADO')
                        ->first();

                    if ($agreement) {
                        $feeDetail = json_decode($agreement->fee_detail, true);

                        if (is_array($feeDetail) && count($feeDetail) > 0) {
                            // La primera cuota (índice 0) es el gasto de cobranza
                            $feeDetail[0]['payment_status'] = 'PAGADO';

                            $agreement->update([
                                'fee_detail' => json_encode($feeDetail),
                            ]);

                            Log::channel('payments')->info('processInvoice: Primera cuota del convenio marcada como PAGADA', [
                                'credit_id' => $creditId,
                                'agreement_id' => $agreement->id
                            ]);
                        }
                    }
                }

                return ResponseBase::success([
                    "ci" => $client->ci,
                    "name" => $client->name,
                    "direction" => $direction,
                    "access_key" => $sofiaResult['response']->claveAcceso,
                    "clave_acceso" => $sofiaResult['response']->claveAcceso,
                    "date" => date('Y/m/d H:i:s', time() - 18000),
                    "fecha" => date('Y/m/d H:i:s', time() - 18000),
                    "value" => $paymentValue,
                    "subtotal_sin_iva" => $subtotalSinIva,
                    "valor_iva" => $valorIva,
                    "total_con_iva" => $totalConIva,
                    "invoice_id" => $invoice->id,
                    "sofia_response" => $sofiaResult['response']
                ], 'Factura procesada correctamente', 200);
            }

            return ResponseBase::error('Error al facturar', ['sofia' => $sofiaResult], 400);
        } catch (\Exception $e) {
            Log::channel('payments')->error('Error processing invoice', [
                'message' => $e->getMessage(),
                'payload' => $payload
            ]);

            return ResponseBase::error('Error al procesar la factura', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @description Obtiene la configuración de Sofia (cuentas bancarias, formas de pago, etc.)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSofiaConfig()
    {
        try {
            $config = $this->sofiaService->getConfig();

            if ($config === null) {
                Log::channel('payments')->info('getSofiaConfig: No se pudo obtener configuración de Sofia, usando valores por defecto');

                // Devolver estructura vacía pero válida para que el frontend no falle
                return response()->json([
                    'contribuyentes' => [
                        'contrib' => [
                            [
                                'cuentasBancarias' => [
                                    'cuenta' => []
                                ]
                            ]
                        ]
                    ],
                    'formasPago' => [
                        'formaPago' => []
                    ]
                ]);
            }

            return response()->json($config);
        } catch (\Exception $e) {
            return response()->json([
                'contribuyentes' => [
                    'contrib' => [
                        [
                            'cuentasBancarias' => [
                                'cuenta' => []
                            ]
                        ]
                    ]
                ],
                'formasPago' => [
                    'formaPago' => []
                ]
            ]);
        }
    }

    private function validatePaymentRubros(Credit $credit, array $data): ?array
    {
        $rubros = [
            'capital' => 'Capital',
            'interest' => 'Interés',
            'mora' => 'Mora',
            'safe' => 'Seguro',
            'collection_expenses' => 'Gastos de cobranza',
            'legal_expenses' => 'Gastos legales',
            'management_collection_expenses' => 'Gastos de gestión de cobranza',
            'other_values' => 'Otros valores'
        ];

        $errors = [];

        foreach ($rubros as $field => $label) {
            $paymentAmount = isset($data[$field]) ? (float)$data[$field] : 0.0;
            
            if ($paymentAmount > 0) {
                $creditAmount = (float)($credit->$field ?? 0);
                
                if ($creditAmount <= 0) {
                    $errors[] = [
                        'field' => $field,
                        'label' => $label,
                        'payment_amount' => $paymentAmount,
                        'credit_amount' => $creditAmount,
                        'message' => "No se puede registrar un pago de {$paymentAmount} en {$label} porque el crédito tiene este rubro en cero."
                    ];
                }

                if ($creditAmount > 0 && $paymentAmount > $creditAmount) {
                    $errors[] = [
                        'field' => $field,
                        'label' => $label,
                        'payment_amount' => $paymentAmount,
                        'credit_amount' => $creditAmount,
                        'message' => "El pago de {$paymentAmount} en {$label} excede el saldo disponible de {$creditAmount}."
                    ];
                }
            }
        }

        if (!empty($errors)) {
            return [
                'valid' => false,
                'errors' => $errors,
                'summary' => 'Se están introduciendo valores en rubros que están en cero o exceden el saldo disponible.'
            ];
        }

        return null;
    }

    /**
     * Summary of getPaymentsResume
     * @description Obtiene un resumen de pagos por negocio para el mes y día actual
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentsResume(){
        try {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startOfDay = Carbon::now()->startOfDay();
            $endOfDay = Carbon::now()->endOfDay();

            $monthBindings = [$startOfMonth, $endOfMonth];
            $dayBindings = [$startOfDay, $endOfDay];

            $paymentResume = DB::table('businesses')
                ->select(
                    'businesses.id as business_id',
                    'businesses.name as business_name',
                    DB::raw('COUNT(DISTINCT CASE WHEN collection_payments.payment_date BETWEEN ? AND ? THEN collection_payments.credit_id END) as nro_credits_with_payment'),
                    DB::raw('COALESCE(SUM(CASE WHEN collection_payments.payment_date BETWEEN ? AND ? THEN collection_payments.payment_value ELSE 0 END), 0) as total_amount_by_month'),
                    DB::raw('COALESCE(SUM(CASE WHEN collection_payments.payment_date BETWEEN ? AND ? THEN collection_payments.payment_value ELSE 0 END), 0) as total_amount_by_day')
                )
                ->leftJoin('collection_payments', 'businesses.id', '=', 'collection_payments.business_id')
                ->groupBy('businesses.id', 'businesses.name')
                ->orderBy('businesses.name')
                ->addBinding(array_merge($monthBindings, $monthBindings, $dayBindings), 'select')
                ->get();

            return ResponseBase::success($paymentResume, 'Resumen de pagos obtenido correctamente');
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener el resumen de pagos',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
    
    public function syncPayments(Request $request){
        try {
            $businessName = $request->input('business_name', 'SEFIL_1');

            if (!in_array($businessName, ['SEFIL_1', 'SEFIL_2'])) {
                return ResponseBase::error(
                    'El business_name debe ser SEFIL_1 o SEFIL_2',
                    ['business_name' => 'Valor inválido'],
                    422
                );
            }

            $parseDateAndAdd5Hours = function($dateString) {
                static $callCount = 0;
                $callCount++;

                if (empty($dateString)) {
                    if ($callCount <= 2) {
                        Log::warning("parseDateAndAdd5Hours recibió fecha vacía, retornando now()");
                    }
                    return now();
                }

                try {
                    $date = \Carbon\Carbon::parse($dateString);
                    $dateWithOffset = $date->addHours(5);

                    if ($callCount <= 2) {
                        Log::info("parseDateAndAdd5Hours - Input: '{$dateString}', Output: '{$dateWithOffset->toDateTimeString()}'");
                    }

                    return $dateWithOffset;
                } catch (\Exception $e) {
                    Log::error("Error parseando fecha '{$dateString}': {$e->getMessage()}");
                    return now();
                }
            };

            $apiUrl = "https://core.sefil.com.ec/api/public/api/vouchers";
            $response = file_get_contents($apiUrl);
            $payments = json_decode($response, true);

            if (!is_array($payments)) {
                return ResponseBase::error(
                    'No se obtuvieron pagos de la API',
                    [],
                    400
                );
            }

            $totalPayments = count($payments);
            $syncedCount = 0;

            foreach ($payments as $index => $paymentData) {
                    // Buscar crédito por sync_id
                    $credit = \App\Models\Credit::where('sync_id', $paymentData['sync_id'])->first();
                    if (!$credit) {
                        Log::warning("Crédito con sync_id '{$paymentData['sync_id']}' no encontrado en índice {$index}, se omite");
                        continue;
                    }

                    // Buscar campaña por business_id
                    $campain = \App\Models\Campain::where('business_id', $credit->business_id)->first();
                    if (!$campain) {
                        Log::warning("Campaña para business_id '{$credit->business_id}' no encontrada en índice {$index}, se omite");
                        continue;
                    }

                    // Procesar según el business_name
                    if ($businessName === 'SEFIL_1') {
                        // Lógica original de SEFIL_1
                        $user = \App\Models\User::where('name', $paymentData['byUser'])->first();
                        if (!$user) {
                            Log::warning("Usuario '{$paymentData['byUser']}' no encontrado en índice {$index}, se omite");
                            continue;
                        }

                        // Parsear detalle
                        $detalle = json_decode($paymentData['detalle'], true);
                        $prevDates = json_decode($paymentData['prevDates'], true);

                        // Determinar valores según tipo de transacción
                        $isTotalPayment = strtolower($paymentData['tipo_transaccion'] ?? '') === 'total';

                        $paymentValues = [];
                        if ($isTotalPayment && is_array($prevDates)) {
                            // Para pago total, usar prev_dates
                            $paymentValues = [
                                'capital' => floatval($prevDates['saldo_capital'] ?? 0),
                                'interest' => floatval($prevDates['interes'] ?? 0),
                                'mora' => floatval($prevDates['mora'] ?? 0),
                                'safe' => floatval($prevDates['seguro_desgravamen'] ?? 0),
                                'collection_expenses' => floatval($prevDates['gastos_cobranza'] ?? 0),
                                'legal_expenses' => floatval($prevDates['gastos_judiciales'] ?? 0),
                                'management_collection_expenses' => 0,
                                'other_values' => floatval($prevDates['otros_valores'] ?? 0),
                            ];
                        } elseif (is_array($detalle)) {
                            // Para pago parcial, usar detalle
                            $paymentValues = [
                                'capital' => floatval($detalle['saldo_capital'] ?? 0),
                                'interest' => floatval($detalle['interes'] ?? 0),
                                'mora' => floatval($detalle['mora'] ?? 0),
                                'safe' => floatval($detalle['seguro_desgravamen'] ?? 0),
                                'collection_expenses' => floatval($detalle['gastos_cobranza'] ?? 0),
                                'legal_expenses' => floatval($detalle['gastos_judiciales'] ?? 0),
                                'management_collection_expenses' => 0,
                                'other_values' => floatval($detalle['otros_valores'] ?? 0),
                            ];
                        }

                        // Crear el pago para SEFIL_1
                        $newPayment = new CollectionPayment([
                            'payment_date' => $parseDateAndAdd5Hours($paymentData['fecha'] ?? null),
                            'payment_deposit_date' => !empty($paymentData['fecha_deposito'])
                                ? Carbon::parse($paymentData['fecha_deposito'])
                                : null,
                            'payment_type' => $paymentData['forma_pago'] ?? null,
                            'payment_value' => floatval($paymentData['valor_recibido'] ?? 0),
                            'payment_difference' => floatval($paymentData['valor_devuelto'] ?? 0),
                            'financial_institution' => $paymentData['institucion_financiera'] ?? null,
                            'payment_reference' => $paymentData['codigo_deposito'] ?? null,
                            'payment_status' => $paymentData['status'] ?? null,
                            'payment_number' => $paymentData['id'] ?? null,
                            'payment_prints' => intval($paymentData['status_print'] ?? 0),
                            'prev_dates' => $paymentData['prevDates'] ?? null,
                            'capital' => $paymentValues['capital'] ?? 0,
                            'interest' => $paymentValues['interest'] ?? 0,
                            'mora' => $paymentValues['mora'] ?? 0,
                            'safe' => $paymentValues['safe'] ?? 0,
                            'collection_expenses' => $paymentValues['collection_expenses'] ?? 0,
                            'legal_expenses' => $paymentValues['legal_expenses'] ?? 0,
                            'management_collection_expenses' => $paymentValues['management_collection_expenses'] ?? 0,
                            'other_values' => $paymentValues['other_values'] ?? 0,
                            'created_by' => $user->id,
                            'credit_id' => $credit->id,
                            'business_id' => $credit->business_id,
                            'campain_id' => $campain->id,
                        ]);

                        $newPayment->created_at = $parseDateAndAdd5Hours($paymentData['created_at'] ?? null);
                        $newPayment->updated_at = $parseDateAndAdd5Hours($paymentData['updated_at'] ?? null);
                        $newPayment->save();

                    } else if ($businessName === 'SEFIL_2') {
                        // Lógica de FACES para SEFIL_2
                        // Preparar valores de los rubros para FACES
                        $paymentValues = [
                            'capital' => floatval($paymentData['capital'] ?? 0),
                            'interest' => floatval($paymentData['interes'] ?? 0),
                            'mora' => floatval($paymentData['mora'] ?? 0),
                            'safe' => floatval($paymentData['seguro_desgravamen'] ?? 0),
                            'collection_expenses' => floatval($paymentData['gastos_cobranza'] ?? 0),
                            'legal_expenses' => floatval($paymentData['gastos_judiciales'] ?? 0),
                            'management_collection_expenses' => floatval($paymentData['gastos_cobranza_faces'] ?? 0),
                            'other_values' => floatval($paymentData['otros_valores'] ?? 0),
                        ];

                        // Crear el pago para FACES
                        $newPayment = new CollectionPayment([
                            'payment_date' => $parseDateAndAdd5Hours($paymentData['fecha_pago'] ?? null),
                            'payment_type' => 'FACES',
                            'payment_value' => floatval($paymentData['total'] ?? 0),
                            'financial_institution' => 'FACES',
                            'payment_reference' => $paymentData['id_comprobante'] ?? 'FACES',
                            'payment_status' => $paymentData['estado'] ?? null,
                            'payment_number' => $paymentData['id'] ?? null,
                            'capital' => $paymentValues['capital'],
                            'interest' => $paymentValues['interest'],
                            'mora' => $paymentValues['mora'],
                            'safe' => $paymentValues['safe'],
                            'collection_expenses' => $paymentValues['collection_expenses'],
                            'legal_expenses' => $paymentValues['legal_expenses'],
                            'management_collection_expenses' => $paymentValues['management_collection_expenses'],
                            'other_values' => $paymentValues['other_values'],
                            'created_by' => null,
                            'credit_id' => $credit->id,
                            'business_id' => $credit->business_id,
                            'campain_id' => $campain->id,
                        ]);

                        $newPayment->save();
                    }

                    $syncedCount++;
                }

            return ResponseBase::success(
                [
                    'total_payments' => $totalPayments,
                    'synced' => $syncedCount,
                    'business_name' => $businessName
                ],
                "Sincronización completada exitosamente para {$businessName}: {$syncedCount} pagos sincronizados"
            );

        } catch (\Exception $e) {
            Log::channel('payments')->info('Error en sincronización masiva de pagos', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al sincronizar pagos',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Aplicar un pago con ERROR_SUM al crédito
     * Este método permite forzar la aplicación de un pago que tenía errores de suma
     */
    public function applyPayment(int $paymentId)
    {
        try {
            // Buscar el pago
            $payment = CollectionPayment::find($paymentId);

            if (!$payment) {
                return ResponseBase::notFound('Pago no encontrado');
            }

            // Verificar que el pago esté en estado ERROR_SUM
            if ($payment->payment_status !== 'ERROR_SUM') {
                return ResponseBase::error(
                    'El pago no está en estado ERROR_SUM',
                    ['current_status' => $payment->payment_status],
                    400
                );
            }

            // Buscar el crédito asociado
            $credit = Credit::find($payment->credit_id);

            if (!$credit) {
                return ResponseBase::notFound('Crédito no encontrado');
            }

            // Aplicar el pago al crédito: restar por rubro pero evitar negativos.
            // Si una resta produciría un valor negativo, se clampeará a 0.
            $rubros = [
                'capital',
                'interest',
                'mora',
                'safe',
                'management_collection_expenses',
                'collection_expenses',
                'legal_expenses',
                'other_values'
            ];

            foreach ($rubros as $r) {
                $current = floatval($credit->{$r} ?? 0);
                $toSubtract = floatval($payment->{$r} ?? 0);
                // Registrar el resultado, incluso si queda negativo
                $credit->{$r} = $current - $toSubtract;
            }

            // Recalcular total_amount: sumar los rubros pero tratar los negativos como 0
            $credit->total_amount =
                max(0, floatval($credit->capital ?? 0)) +
                max(0, floatval($credit->interest ?? 0)) +
                max(0, floatval($credit->mora ?? 0)) +
                max(0, floatval($credit->safe ?? 0)) +
                max(0, floatval($credit->management_collection_expenses ?? 0)) +
                max(0, floatval($credit->collection_expenses ?? 0)) +
                max(0, floatval($credit->legal_expenses ?? 0)) +
                max(0, floatval($credit->other_values ?? 0));

            // Actualizar cuotas pagadas si hay información de cuota
            if ($payment->fee !== null) {
                $credit->paid_fees = $payment->fee;
                $credit->pending_fees = intval($credit->total_fees ?? 0) - $payment->fee;
            }

            // Actualizar fecha de pago
            $credit->payment_date = $payment->payment_date;

            // Actualizar estado si está cancelado
            if ($credit->total_amount <= 0) {
                $credit->collection_state = 'Cancelado';
            }

            // Guardar el crédito
            $credit->save();

            // Actualizar el estado del pago a GUARDADO
            $payment->payment_status = 'GUARDADO';
            $payment->save();

            return ResponseBase::success([
                'payment' => $payment,
                'credit' => $credit
            ], 'Pago aplicado correctamente al crédito');

        } catch (\Exception $e) {
            Log::channel('payments')->info('Error al aplicar pago', [
                'payment_id' => $paymentId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al aplicar el pago',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

}