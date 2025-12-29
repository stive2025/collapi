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
            $payments = $query->paginate($perPage);

            return ResponseBase::success($payments, 'Pagos obtenidos correctamente');
        } catch (\Exception $e) {
            Log::error('Error fetching collection payments', [
                'message' => $e->getMessage()
            ]);

            return ResponseBase::error('Error al obtener pagos', ['error' => $e->getMessage()], 500);
        }
    }

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
                'total_amount' => $credit->total_amount
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

            if ((string)$credit->collection_state === 'CONVENIO DE PAGO') {
                $paymentValue = isset($data['payment_value']) ? (float)$data['payment_value'] : 0.0;
                $this->updateAgreementFees($credit->id, $paymentValue);
            }

            if (!empty($data['payment_deposit_date'])) {
                $data['payment_deposit_date'] = Carbon::parse($data['payment_deposit_date']);
                $data['payment_date'] = date('Y-m-d H:i:s', time() - 18000);
            }

            $data['created_by'] = $user->id;

            $payment = CollectionPayment::create($data);

            DB::commit();
            Log::channel('payments')->info('Pago creado correctamente', ['payment' => $payment]);

            return ResponseBase::success($payment, 'Pago creado correctamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('payments')->error('Error al crear el pago', [
                'message' => $e->getMessage(),
                'data' => $data
            ]);

            return ResponseBase::error('Error al crear el pago', ['error' => $e->getMessage()], 500);
        }
    }

    private function updateAgreementFees(int $creditId, float $amountPaid): void
    {
        Log::channel('payments')->info('Actualizando cuotas del convenio', ['credit_id' => $creditId, 'amount_paid' => $amountPaid]);
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

        $remainingAmount = $amountPaid;
        $totalPaidFees = 0;

        foreach ($feeDetail as $index => &$fee) {
            if ($index === 0 || (isset($fee['payment_status']) && $fee['payment_status'] === 'PAGADA')) {
                continue;
            }

            if (!isset($fee['payment_status']) || $fee['payment_status'] !== 'PENDIENTE') {
                continue;
            }

            if ($remainingAmount <= 0) {
                break;
            }

            $feeAmount = (float)($fee['payment_amount'] ?? 0);
            $currentPaid = (float)($fee['payment_value'] ?? 0);
            $feeBalance = $feeAmount - $currentPaid;

            if ($feeBalance <= 0) {
                $fee['payment_status'] = 'PAGADA';
                $totalPaidFees++;
                continue;
            }

            if ($remainingAmount >= $feeBalance) {
                $fee['payment_value'] = $feeAmount;
                $fee['payment_status'] = 'PAGADA';
                $fee['payment_date'] = now()->format('Y-m-d');
                $remainingAmount -= $feeBalance;
                $totalPaidFees++;
            } else {
                $fee['payment_value'] = $currentPaid + $remainingAmount;
                $fee['payment_status'] = 'PENDIENTE';
                $remainingAmount = 0;
            }
        }

        unset($fee);

        $agreement->fee_detail = json_encode($feeDetail);
        $agreement->paid_fees = ($agreement->paid_fees ?? 0) + $totalPaidFees;
        $agreement->pending_fees = max(0, ($agreement->total_fees ?? 0) - $agreement->paid_fees);
        $agreement->save();
        Log::channel('payments')->info('Cuotas del convenio actualizadas', [
            'agreement_id' => $agreement->id,
            'paid_fees' => $agreement->paid_fees,
            'pending_fees' => $agreement->pending_fees
        ]);
    }

    public function show(CollectionPayment $payment)
    {
        try {
            return ResponseBase::success($payment, 'Pago obtenido correctamente');
        } catch (\Exception $e) {
            return ResponseBase::error('Error al obtener el pago', ['error' => $e->getMessage()], 500);
        }
    }

    public function processInvoice(Request $request)
    {
        $payload = $request->all();
        Log::info('processInvoice payload', ['payload' => $payload]);

        try {
            $creditId = $request->input('credito') ?? $request->input('credit_id');
            if (!$creditId) {
                return ResponseBase::validationError(['credito' => ['El id del crédito es obligatorio.']]);
            }

            $invoice = Invoice::where('credito', $creditId)->where('status', 'pendiente')->first();

            if ($invoice === null) {
                $cartera = $request->input('cartera');
                $creditoRow = null;
                if ($cartera) {
                    $creditoRow = DB::table($cartera)->where('ci', $request->input('ci'))->first();
                }

                $invoice = Invoice::create([
                    "fecha" => date('Y/m/d H:i:s', time() - 18000),
                    "byUser" => $request->user()->name ?? 'system',
                    "credito" => $creditoRow->id ?? $creditId,
                    "prevDates" => "N/D",
                    "status" => "pendiente",
                    "postDates" => json_encode([
                        "monto" => 0,
                        "days" => 0,
                        "value" => floatval($request->input('value', 0))
                    ]),
                    "cartera" => $cartera ?? null,
                ]);
            }

            $paymentValue = $request->input('payment_value', $request->input('value', round(json_decode($invoice->postDates)->value ?? 0, 2)));
            $paymentMethod = $request->input('payment_method', $request->input('metodo'));
            $financialInstitution = $request->input('financial_institution', $request->input('idBanco'));
            $paymentReference = $request->input('payment_reference', $request->input('referencia'));

            $syntheticPayload = [
                'value' => $paymentValue,
                'metodo' => $paymentMethod,
                'idBanco' => $financialInstitution,
                'referencia' => $paymentReference,
                'ci' => $request->input('ci', null),
                'name' => $request->input('name', null),
                'telefono' => $request->input('telefono', null),
                'email' => $request->input('email', null),
                'formaPago' => $request->input('formaPago', $paymentMethod),
                'cartera' => $request->input('cartera', $invoice->cartera ?? null),
            ];

            $syntheticRequest = \Illuminate\Http\Request::create('/', 'POST', $syntheticPayload);
            $sofiaResult = $this->sofiaService->facturar($syntheticRequest, $paymentValue);

            Log::info('SofiaService.facturar result', ['result' => $sofiaResult]);

            if (isset($sofiaResult['state']) && $sofiaResult['state'] === 200 && isset($sofiaResult['response']->claveAcceso)) {
                $prev_invoice = json_decode($invoice->postDates);

                $invoice->update([
                    "fecha" => date('Y/m/d H:i:s', time() - 18000),
                    "status" => "finalizado",
                    "clave_acceso" => $sofiaResult['response']->claveAcceso,
                    "postDates" => json_encode([
                        "monto" => $prev_invoice->monto ?? 0,
                        "days" => $prev_invoice->days ?? 0,
                        "value" => round($paymentValue, 2)
                    ]),
                    "pay_way" => $paymentMethod,
                    "code_reference" => $paymentReference,
                    "financial_institution" => $financialInstitution
                ]);

                return ResponseBase::success([
                    "fecha" => date('Y/m/d H:i:s', time() - 18000),
                    "clave_acceso" => $sofiaResult['response']->claveAcceso,
                    "sofia_response" => $sofiaResult['response']
                ], 'Factura procesada correctamente', 200);
            }

            return ResponseBase::error('Error al facturar', ['sofia' => $sofiaResult], 400);
        } catch (\Exception $e) {
            Log::error('Error processing invoice', [
                'message' => $e->getMessage(),
                'payload' => $payload
            ]);

            return ResponseBase::error('Error al procesar la factura', ['error' => $e->getMessage()], 500);
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

    public function getPaymentsResume(){
        try {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startOfDay = Carbon::now()->startOfDay();
            $endOfDay = Carbon::now()->endOfDay();

            $monthBindings = [$startOfMonth, $endOfMonth];
            $dayBindings = [$startOfDay, $endOfDay];

            $paymentResume = CollectionPayment::select(
                    'collection_payments.business_id',
                    'businesses.name as business_name',
                    DB::raw('COUNT(DISTINCT collection_payments.credit_id) as nro_credits_with_payment'),
                    DB::raw('COALESCE(SUM(CASE WHEN collection_payments.payment_date BETWEEN ? AND ? THEN collection_payments.payment_value ELSE 0 END), 0) as total_amount_by_month'),
                    DB::raw('COALESCE(SUM(CASE WHEN collection_payments.payment_date BETWEEN ? AND ? THEN collection_payments.payment_value ELSE 0 END), 0) as total_amount_by_day')
                )
                ->join('businesses', 'collection_payments.business_id', '=', 'businesses.id')
                ->whereBetween('collection_payments.payment_date', [$startOfMonth, $endOfMonth])
                ->groupBy('collection_payments.business_id', 'businesses.name')
                ->orderBy('businesses.name')
                ->addBinding(array_merge($monthBindings, $dayBindings), 'select')
                ->get();

            return ResponseBase::success($paymentResume, 'Resumen de pagos obtenido correctamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener resumen de pagos', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al obtener el resumen de pagos',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}