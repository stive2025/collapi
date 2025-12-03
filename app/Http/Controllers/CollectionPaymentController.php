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
     * Display a listing of the resource.
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

            $payments = $query->paginate($perPage);

            return ResponseBase::success($payments, 'Pagos obtenidos correctamente');
        } catch (\Exception $e) {
            Log::error('Error fetching collection payments', [
                'message' => $e->getMessage()
            ]);

            return ResponseBase::error('Error al obtener pagos', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCollectionPaymentRequest $request)
    {
        $data = $request->validated();
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

            if ((string)$credit->collection_state === 'CONVENIO DE PAGO') {
                $paidCapital = isset($data['capital']) ? (float)$data['capital'] : 0.0;
                $paidInterest = isset($data['interest']) ? (float)$data['interest'] : 0.0;
                $paidMora = isset($data['mora']) ? (float)$data['mora'] : 0.0;
                $paidOther = isset($data['other_values']) ? (float)$data['other_values'] : 0.0;
                $paymentValue = isset($data['payment_value']) ? (float)$data['payment_value'] : 0.0;

                $credit->capital = max(0, (float)$credit->capital - $paidCapital);
                $credit->interest = max(0, (float)$credit->interest - $paidInterest);
                $credit->mora = max(0, (float)$credit->mora - $paidMora);
                $credit->other_values = max(0, (float)($credit->other_values ?? 0) - $paidOther);
                $credit->total_amount = max(0, (float)$credit->total_amount - $paymentValue);

                $prints = isset($data['payment_prints']) ? (int)$data['payment_prints'] : 1;
                if (isset($credit->paid_fees)) {
                    $credit->paid_fees = (int)$credit->paid_fees + $prints;
                } else {
                    $credit->paid_fees = $prints;
                }
                if (isset($credit->pending_fees)) {
                    $credit->pending_fees = max(0, (int)$credit->pending_fees - $prints);
                }

                if (
                    (float)$credit->capital <= 0.0 &&
                    (float)$credit->interest <= 0.0 &&
                    (float)$credit->mora <= 0.0 &&
                    ($credit->pending_fees ?? 0) === 0
                ) {
                    $credit->collection_state = 'PAGADO';
                }

                $credit->save();
            }

            if (!empty($data['payment_date'])) {
                $data['payment_date'] = Carbon::parse($data['payment_date']);
            }

            $data['created_by'] = $user->id;
            $payment = CollectionPayment::create($data);

            DB::commit();
            return ResponseBase::success($payment, 'Pago creado correctamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error creating collection payment (rollback)', [
                'message' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return ResponseBase::error('Error al crear el pago', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
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

                $cartera = $invoice->cartera;
                $creditoRow = null;
                if ($cartera) {
                    $creditoRow = DB::table($cartera)->where('id', $invoice->credito)->first();
                }

                if ($creditoRow && (isset($creditoRow->collectionState) && $creditoRow->collectionState === "CONVENIO DE PAGO")) {
                    $agreement = Agreement::where('credito', $creditoRow->id)
                        ->where('cartera', $invoice->cartera)
                        ->where('status', 'autorizado')
                        ->first();

                    if ($agreement) {
                        $cuotas = json_decode($agreement->detail);
                        $i = 0;
                        $siguiente_cuota = 0;

                        foreach ($cuotas as $cuota) {
                            if (intval($cuota->cuota) === 1 && $i === 0) {
                                $cuota->estado = "PAGADO";
                                $i++;
                            } elseif ($i === 1) {
                                $siguiente_cuota = $cuota->valor;
                                $i++;
                            }
                        }

                        $update_convenio = $cuotas;

                        Agreement::where('credito', $creditoRow->id)
                            ->where('cartera', $invoice->cartera)
                            ->where('status', 'autorizado')
                            ->update([
                                "detail" => json_encode($update_convenio),
                                "valor_cuota" => $siguiente_cuota
                            ]);
                    }
                }

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
}
