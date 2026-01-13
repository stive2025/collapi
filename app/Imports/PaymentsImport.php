<?php

namespace App\Imports;

use App\Models\Business;
use App\Models\CollectionPayment;
use App\Models\Credit;
use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

HeadingRowFormatter::default('none');

class PaymentsImport implements
    ToModel,
    WithHeadingRow,
    WithBatchInserts,
    WithValidation,
    SkipsEmptyRows,
    SkipsOnFailure
{
    use SkipsFailures;

    protected $businessId;
    protected $campainId;
    protected $importedCount = 0;
    protected $skippedCount = 0;

    public function __construct($businessId, $campainId = null)
    {
        $this->businessId = $businessId;
        $this->campainId = $campainId;
    }

    public function rules(): array
    {
        return [
            'AGENCIA' => ['nullable', 'string'],
            'USUARIO_CAUSAL' => ['nullable', 'string'],
            'CAUSAL' => ['nullable'],
            'FECHA VENTA DE CARTERA' => ['nullable'],
            'IDENTIFICACIÓN' => ['required', 'string'],
            'NOMBRE' => ['nullable', 'string'],
            'ESTADO' => ['nullable', 'string'],
            'CREDITO' => ['required', 'string'],
            'CUOTA' => ['nullable'],
            'FECHA_RECUPERACION' => ['nullable'],
            'FECHA_RECAUDACION' => ['required'],
            'CAPITAL' => ['required', 'numeric'],
            'INTERES' => ['required', 'numeric'],
            'SEGURO' => ['required', 'numeric'],
            'MORA' => ['required', 'numeric'],
            'MORA_AD' => ['required', 'numeric'],
            'JUDICIAL' => ['required', 'numeric'],
            'PREJUDICIAL' => ['required', 'numeric'],
            'PREJUDICIAL_AD' => ['required', 'numeric'],
            'OTROS' => ['required', 'numeric'],
            'OTROS_AD' => ['required', 'numeric'],
            'COMISION_CANAL' => ['required', 'numeric'],
            'TOTAL' => ['required', 'numeric'],
        ];
    }

    public function model(array $row)
    {
        try {
            $credit = Credit::where('sync_id', $row['CREDITO'])->first();

            if (!$credit) {
                Log::warning("Crédito no encontrado: {$row['CREDITO']}");
                $this->skippedCount++;
                return null;
            }

            // Parsear fecha de recaudación
            $paymentDate = $this->parseDate($row['FECHA_RECAUDACION']);
            if (!$paymentDate) {
                Log::warning("Fecha de recaudación inválida para crédito: {$row['CREDITO']}");
                $this->skippedCount++;
                return null;
            }

            // Calcular valores
            $capital = floatval($row['CAPITAL']);
            $interest = floatval($row['INTERES']);
            $mora = floatval($row['MORA']);
            $safe = floatval($row['SEGURO']);
            $managementExpenses = floatval($row['PREJUDICIAL']); // SEFIL
            $collectionExpenses = floatval($row['PREJUDICIAL_AD']); // FACES
            $legalExpenses = floatval($row['JUDICIAL']);

            // Otros valores incluyen: OTROS, OTROS_AD, MORA_AD, COMISION_CANAL
            $otherValues = floatval($row['OTROS']) +
                          floatval($row['OTROS_AD']) +
                          floatval($row['MORA_AD']) +
                          floatval($row['COMISION_CANAL']);

            $totalValue = $capital + $interest + $mora + $safe +
                         $managementExpenses + $collectionExpenses +
                         $legalExpenses + $otherValues;

            // Verificar si ya existe un pago con los mismos datos
            $existingPayment = CollectionPayment::where('credit_id', $credit->id)
                ->whereDate('payment_date', $paymentDate)
                ->where('payment_value', $totalValue)
                ->first();

            if ($existingPayment) {
                Log::info("Pago duplicado omitido para crédito: {$row['CREDITO']}");
                $this->skippedCount++;
                return null;
            }

            // Obtener información del cliente titular
            $client = $credit->clients()->wherePivot('type', 'TITULAR')->first();
            $syncId = $credit->sync_id;
            $clientName = $client ? $client->name : $row['NOMBRE'];
            $clientCi = $client ? $client->ci : $row['IDENTIFICACIÓN'];

            // Parsear cuota(s)
            $fee = null;
            if (!empty($row['CUOTA'])) {
                $cuotas = explode(',', $row['CUOTA']);
                $fee = intval(trim(end($cuotas))); // Última cuota
            }

            // Validar que ningún rubro quede negativo al restar el pago
            $paymentStatus = 'GUARDADO'; // Estado por defecto si todo está bien
            $hasNegativeBalance = false;

            // Verificar cada rubro del crédito
            if ((floatval($credit->capital ?? 0) - $capital) < 0) {
                $hasNegativeBalance = true;
                Log::warning("Capital quedaría negativo para crédito: {$row['CREDITO']} - Credit: {$credit->capital}, Payment: {$capital}");
            }
            if ((floatval($credit->interest ?? 0) - $interest) < 0) {
                $hasNegativeBalance = true;
                Log::warning("Interés quedaría negativo para crédito: {$row['CREDITO']} - Credit: {$credit->interest}, Payment: {$interest}");
            }
            if ((floatval($credit->mora ?? 0) - $mora) < 0) {
                $hasNegativeBalance = true;
                Log::warning("Mora quedaría negativa para crédito: {$row['CREDITO']} - Credit: {$credit->mora}, Payment: {$mora}");
            }
            if ((floatval($credit->safe ?? 0) - $safe) < 0) {
                $hasNegativeBalance = true;
                Log::warning("Seguro quedaría negativo para crédito: {$row['CREDITO']} - Credit: {$credit->safe}, Payment: {$safe}");
            }
            if ((floatval($credit->management_collection_expenses ?? 0) - $managementExpenses) < 0) {
                $hasNegativeBalance = true;
                Log::warning("Gastos de cobranza SEFIL quedarían negativos para crédito: {$row['CREDITO']} - Credit: {$credit->management_collection_expenses}, Payment: {$managementExpenses}");
            }
            if ((floatval($credit->collection_expenses ?? 0) - $collectionExpenses) < 0) {
                $hasNegativeBalance = true;
                Log::warning("Gastos de cobranza FACES quedarían negativos para crédito: {$row['CREDITO']} - Credit: {$credit->collection_expenses}, Payment: {$collectionExpenses}");
            }
            if ((floatval($credit->legal_expenses ?? 0) - $legalExpenses) < 0) {
                $hasNegativeBalance = true;
                Log::warning("Gastos judiciales quedarían negativos para crédito: {$row['CREDITO']} - Credit: {$credit->legal_expenses}, Payment: {$legalExpenses}");
            }
            if ((floatval($credit->other_values ?? 0) - $otherValues) < 0) {
                $hasNegativeBalance = true;
                Log::warning("Otros valores quedarían negativos para crédito: {$row['CREDITO']} - Credit: {$credit->other_values}, Payment: {$otherValues}");
            }

            // Si hay negativos, marcar como ERROR_SUM y NO actualizar el crédito
            if ($hasNegativeBalance) {
                $paymentStatus = 'ERROR_SUM';
            } else {
                // Si todo está bien, actualizar el crédito restando los valores del pago
                $credit->capital = floatval($credit->capital ?? 0) - $capital;
                $credit->interest = floatval($credit->interest ?? 0) - $interest;
                $credit->mora = floatval($credit->mora ?? 0) - $mora;
                $credit->safe = floatval($credit->safe ?? 0) - $safe;
                $credit->management_collection_expenses = floatval($credit->management_collection_expenses ?? 0) - $managementExpenses;
                $credit->collection_expenses = floatval($credit->collection_expenses ?? 0) - $collectionExpenses;
                $credit->legal_expenses = floatval($credit->legal_expenses ?? 0) - $legalExpenses;
                $credit->other_values = floatval($credit->other_values ?? 0) - $otherValues;

                // Actualizar total_amount
                $credit->total_amount = $credit->capital + $credit->interest + $credit->mora +
                                    $credit->safe + $credit->management_collection_expenses +
                                    $credit->collection_expenses + $credit->legal_expenses +
                                    $credit->other_values;

                // Actualizar cuotas pagadas si hay información de cuota
                if ($fee !== null) {
                    $credit->paid_fees = $fee;
                    $credit->pending_fees = intval($credit->total_fees ?? 0) - $fee;
                }

                // Actualizar fecha de pago
                $credit->payment_date = $paymentDate;

                // Actualizar estado si está cancelado
                if ($credit->total_amount <= 0) {
                    $credit->collection_state = 'Cancelado';
                }

                $credit->save();
            }

            // Crear el pago
            $payment = new CollectionPayment([
                'created_by' => null,
                'payment_date' => $paymentDate,
                'payment_deposit_date' => null,
                'payment_value' => $totalValue,
                'payment_difference' => null,
                'payment_type' => $row['USUARIO_CAUSAL'] ?? 'EFECTIVO',
                'payment_method' => null,
                'financial_institution' => null,
                'payment_reference' => $row['CREDITO'],
                'payment_status' => $paymentStatus,
                'payment_prints' => null,
                'fee' => $fee,
                'capital' => $capital,
                'interest' => $interest,
                'mora' => $mora,
                'safe' => $safe,
                'management_collection_expenses' => $managementExpenses,
                'collection_expenses' => $collectionExpenses,
                'legal_expenses' => $legalExpenses,
                'other_values' => $otherValues,
                'prev_dates' => null,
                'with_management' => 'NO',
                'management_auto' => null,
                'days_past_due_auto' => null,
                'management_prev' => null,
                'days_past_due_prev' => null,
                'post_management' => 'NO',
                'credit_id' => $credit->id,
                'business_id' => $this->businessId,
                'campain_id' => $this->campainId,
            ]);

            // Agregar información adicional como atributos calculados
            $payment->sync_id = $syncId;
            $payment->client_name = $clientName;
            $payment->client_ci = $clientCi;

            $this->importedCount++;

            return $payment;

        } catch (\Exception $e) {
            Log::error("Error al importar pago: {$e->getMessage()}", [
                'row' => $row,
                'trace' => $e->getTraceAsString()
            ]);
            $this->skippedCount++;
            return null;
        }
    }

    /**
     * Parsear fecha de Excel o string
     */
    private function parseDate($dateValue)
    {
        if (empty($dateValue)) {
            return null;
        }

        try {
            // Si es un número (fecha de Excel)
            if (is_numeric($dateValue)) {
                return ExcelDate::excelToDateTimeObject($dateValue);
            }

            // Si es un string, intentar parsearlo
            return \Carbon\Carbon::parse($dateValue);
        } catch (\Exception $e) {
            Log::error("Error parseando fecha: {$dateValue} - {$e->getMessage()}");
            return null;
        }
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }
}