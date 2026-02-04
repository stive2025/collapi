<?php

namespace App\Exports;

use App\Services\UtilService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class AccountingExport implements FromCollection, WithHeadings, WithCustomStartCell, WithEvents, WithDrawings, ShouldAutoSize, WithStyles, WithColumnWidths
{
    protected $businessIds;
    protected $group;
    protected $startDate;
    protected $endDate;
    protected $monthName;
    protected $businessName;
    protected $userName;
    protected $utilService;

    public function __construct($businessIds, $group, $startDate, $endDate, $monthName, $businessName, $userName)
    {
        $this->businessIds = is_array($businessIds) ? $businessIds : [$businessIds];
        $this->group = $group;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->monthName = $monthName;
        $this->businessName = $businessName;
        $this->userName = $userName;
        $this->utilService = new UtilService();
    }

    public function columnWidths(): array
    {
        return [
            'L' => 10,
            'M' => 10,
            'N' => 10,
            'Q' => 10,
            'R' => 20,
            'S' => 10,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A9:W9')->getFont()->setBold(true);
        $sheet->getStyle('A9:W9')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
        $sheet->getStyle('A9:W9')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A9:W9')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF9619');

        $sheet->getStyle('I9:M9')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('C6EFCE');
        $sheet->getStyle('N9:R9')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEB9C');

        $sheet->getStyle('A9:Y9')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        for ($col = 'A'; $col <= 'X'; $col++) {
            $sheet->getStyle($col . '8:' . $col . '500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        $sheet->getProtection()->setSheet(true);

        return [
            'A9' => [
                'font' => ['size' => 10],
                'borders' => [
                    'top' => [
                        'color' => [
                            'rgb' => '808080'
                        ]
                    ]
                ]
            ],
        ];
    }

    public function drawings()
    {
        $logoPath = public_path('logo.png');

        if (!file_exists($logoPath)) {
            return [];
        }

        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('This is my logo');
        $drawing->setPath($logoPath);
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');

        return $drawing;
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function headings(): array
    {
        $dateRange = $this->monthName ? 'Mes: ' . $this->monthName : 'Fecha: ' . $this->startDate . '-' . $this->endDate;

        return [
            ['PAGOS PARA CONTABILIDAD'],
            [$dateRange],
            ['Cartera: ' . $this->businessName],
            [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'Valores Recuperados',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                ''
            ],
            [
                "AGENCIA",
                "CEDULA",
                "NOMBRE",
                "ID CREDITO",
                "ID COMPROBANTE",
                "FECHA DEPOSITO",
                "FECHA DE PAGO",
                "CONDONACION",
                "CAPITAL",
                "INTERES",
                "MORA",
                "GESTIÓN COBRANZA SEFIL",
                "IVA GAS. COB.",
                "GESTIÓN COBRANZA FACES",
                "GASTOS JUDICIALES",
                "OTROS",
                "TOTAL PAGADO",
                "CAPITAL CONTABLE RECUPERADO",
                "FORMA DE PAGO",
                "INSTITUCION FINANCIERA",
                "REFERENCIA",
                "ESTADO DEL CRÉDITO",
                "ESTADO DEL COMPROBANTE"
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $position_last = count($this->headings()[4]);
                $column = Coordinate::stringFromColumnIndex($position_last);

                $cells = "A1:{$column}3";
                $event->sheet->mergeCells($cells);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);

                $cells = "A4:{$column}4";
                $event->sheet->mergeCells($cells);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);

                $cells = "I8:R8";
                $event->sheet->mergeCells($cells);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setSize(12);
                $event->sheet->getDelegate()->getStyle($cells)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
                $event->sheet->getDelegate()->getStyle($cells)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('C6EFCE');

                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestDataRow();

                for ($row = 2; $row <= $highestRow; $row++) {
                    $cellValue = $sheet->getCell("A{$row}")->getValue();
                    $estado = $sheet->getCell("W{$row}")->getValue();

                    // Aplicar estilo a la fila de TOTALES
                    if (strtoupper($cellValue) === 'TOTALES') {
                        $sheet->getStyle("A{$row}:W{$row}")->getFont()->setBold(true);
                        $sheet->getStyle("A{$row}:W{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FF9619');
                    }
                    // Aplicar estilo rojo a filas revertidas/anuladas
                    elseif (strtolower($estado) === 'revertido' || strtolower($estado) === 'anulado') {
                        $sheet->getStyle("A{$row}:W{$row}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFFF0000');
                    }
                }
            }
        ];
    }

    public function collection()
    {
        $payments = $this->getPaymentsQuery()->get();

        // Cargar todas las condonaciones de una vez para evitar N+1 queries
        $creditIds = $payments->pluck('credit_id')->unique()->toArray();
        $this->loadCondonations($creditIds);

        $dataBox = [];

        if ($this->group === 'true') {
            $dataBox = $this->processGroupedPayments($payments);
        } else {
            $dataBox = $this->processUngroupedPayments($payments);
        }

        $this->addInvoices($dataBox, $this->businessIds);
        usort($dataBox, fn($a, $b) => strtotime($a[6]) - strtotime($b[6]));

        $this->addTotalsAndFooter($dataBox);

        return collect($dataBox);
    }

    private $condonationsCache = [];

    private function loadCondonations($creditIds)
    {
        if (empty($creditIds)) return;

        // Log::info('AccountingExport: Cargando condonaciones', [
        //     'total_credits' => count($creditIds),
        //     'credit_ids' => $creditIds
        // ]);
        
        $condonations = DB::table('condonations')
            ->whereIn('credit_id', $creditIds)
            ->whereIn('status', ['autorizado', 'AUTORIZADA'])
            ->select('credit_id', 'amount')
            ->get();

        // Log::info('AccountingExport: Condonaciones cargadas', [
        //     'total_credits' => count($creditIds),
        //     'total_condonations' => $condonations->count(),
        //     'condonations' => $condonations->toArray()
        // ]);

        foreach ($condonations as $cond) {
            // Si ya existe una condonación para este crédito, sumar los montos
            if (isset($this->condonationsCache[$cond->credit_id])) {
                $this->condonationsCache[$cond->credit_id] += floatval($cond->amount);
            } else {
                $this->condonationsCache[$cond->credit_id] = floatval($cond->amount);
            }
        }
    }

    private function getPaymentsQuery()
    {
        $query = DB::table('collection_payments as cp')
            ->whereIn('cp.business_id', $this->businessIds);

        if ($this->monthName) {
            $monthNumber = $this->utilService->getMonthNumber($this->monthName);
            $query->whereYear('cp.payment_date', date('Y'))->whereMonth('cp.payment_date', $monthNumber);
        } else {
            $query->whereBetween('cp.payment_date', [$this->startDate, $this->endDate]);
        }

        return $query->select(
            'cp.*',
            DB::raw('(SELECT c.sync_id FROM credits c WHERE c.id = cp.credit_id) as sync_id'),
            DB::raw('(SELECT c.agency FROM credits c WHERE c.id = cp.credit_id) as agency'),
            DB::raw('(SELECT c.collection_state FROM credits c WHERE c.id = cp.credit_id) as collection_state'),
            DB::raw('(SELECT cl.name FROM clients cl
                INNER JOIN client_credit cc ON cc.client_id = cl.id
                WHERE cc.credit_id = cp.credit_id AND cc.type = "TITULAR"
                LIMIT 1) as client_name'),
            DB::raw('(SELECT cl.ci FROM clients cl
                INNER JOIN client_credit cc ON cc.client_id = cl.id
                WHERE cc.credit_id = cp.credit_id AND cc.type = "TITULAR"
                LIMIT 1) as client_ci'),
            DB::raw('(
            SELECT COUNT(1)
            FROM collection_payments cp2
            WHERE cp2.business_id = cp.business_id
              AND (
                cp2.payment_date < cp.payment_date
                OR (cp2.payment_date = cp.payment_date AND cp2.id <= cp.id)
              )
            ) as comprobante_number')
        );
    }

    private function processGroupedPayments($payments)
    {
        $groupedPayments = $payments->groupBy('credit_id');
        $dataBox = [];

        foreach ($groupedPayments as $creditId => $creditPayments) {
            $amounts = $this->sumPaymentAmounts($creditPayments);
            $firstPayment = $creditPayments->first();
            $condonation = $this->getCondonationAmount($creditId);

            $dataBox[] = $this->buildPaymentRow($firstPayment, $amounts, $condonation);
        }

        return $dataBox;
    }

    private function processUngroupedPayments($payments)
    {
        $dataBox = [];

        foreach ($payments as $payment) {
            $amounts = $this->sumPaymentAmounts(collect([$payment]));
            $condonation = $this->getCondonationAmount($payment->credit_id);

            $dataBox[] = $this->buildPaymentRow($payment, $amounts, $condonation);
        }

        return $dataBox;
    }

    private function sumPaymentAmounts($payments)
    {
        $amounts = ['capital' => 0, 'interest' => 0, 'mora' => 0, 'managementExpenses' => 0,
                    'collectionExpenses' => 0, 'legalExpenses' => 0, 'otherValues' => 0];

        foreach ($payments as $payment) {
            if ($payment->payment_status === 'guardado') {
                $amounts['capital'] += floatval($payment->capital);
                $amounts['interest'] += floatval($payment->interest);
                $amounts['mora'] += floatval($payment->mora);
                $amounts['managementExpenses'] += floatval($payment->management_collection_expenses);
                $amounts['collectionExpenses'] += floatval($payment->collection_expenses);
                $amounts['legalExpenses'] += floatval($payment->legal_expenses);
                $amounts['otherValues'] += floatval($payment->other_values) + floatval($payment->safe);
            }
        }

        $amounts['total'] = $amounts['capital'] + $amounts['interest'] + $amounts['mora'] +
                        $amounts['managementExpenses'] + $amounts['collectionExpenses'] +
                        $amounts['legalExpenses'] + $amounts['otherValues'];

        return $amounts;
    }

    private function buildPaymentRow($payment, $amounts, $condonation)
    {
        $rawMethod = $payment->payment_method ?? $payment->payment_type ?? null;
        $method = $rawMethod ?? 'efectivo';

        $institution = $payment->financial_institution ?? null;
        $reference = $payment->payment_reference ?? null;

        if (is_string($rawMethod) && strtolower(trim($rawMethod)) === 'efectivo') {
            $institution = '';
            $reference = '';
        } else {
            if (empty($institution) && $rawMethod === null) {
                $institution = 'FACES';
            }

            $institution = $institution ?? '';
            $reference = $reference ?? '';
        }

        return [
            $payment->agency,
            $payment->client_ci,
            $payment->client_name,
            $this->businessName . '-' . $payment->sync_id,
            ($payment->payment_number !== null ? $payment->payment_number : 'FACES'),
            $payment->payment_deposit_date,
            $payment->payment_date,
            $this->formatForExcel($condonation),
            $this->formatForExcel($amounts['capital']),
            $this->formatForExcel($amounts['interest']),
            $this->formatForExcel($amounts['mora']),
            $this->formatForExcel($amounts['managementExpenses']),
            $this->formatForExcel(0),
            $this->formatForExcel($amounts['collectionExpenses']),
            $this->formatForExcel($amounts['legalExpenses']),
            $this->formatForExcel($amounts['otherValues']),
            $this->formatForExcel($amounts['total']),
            $this->formatForExcel($amounts['capital'] * 0.07),
            $method,
            $institution,
            $reference,
            $this->utilService->setState($payment->collection_state),
            $payment->payment_status
        ];
    }

    private function addTotalsAndFooter(&$dataBox)
    {
        $totals = $this->calculateTotals($dataBox);

        $dataBox[] = [
            "TOTALES", "", "", "", "", "", "",
            $totals['condonacion'], $totals['capital'], $totals['interes'], $totals['mora'],
            $totals['gestion_sefil'], $totals['iva'], $totals['gestion_faces'], $totals['judicial'],
            $totals['otros'], $totals['total'], $totals['capital_contable'],
            "", "", "", "", ""
        ];

        $dataBox[] = [""];
        $dataBox[] = ["Generado por:", $this->userName];
        $dataBox[] = ["Hora y fecha:", date('Y/m/d H:i:s')];
    }

    private function getCondonationAmount($creditId)
    {
        $amount = $this->condonationsCache[$creditId] ?? 0;

        if ($amount > 0) {
            Log::info('AccountingExport: Condonación encontrada', [
                'credit_id' => $creditId,
                'amount' => $amount
            ]);
        }

        return $amount;
    }

    private function addInvoices(&$dataBox, $businessIds)
    {
        $invoicesQuery = DB::table('invoices as i')
            ->join('credits as c', 'c.id', '=', 'i.credit_id')
            ->join('businesses as b', 'b.id', '=', 'c.business_id')
            ->join('client_credit as cc', 'cc.credit_id', '=', 'c.id')
            ->join('clients as cl', function($join) {
                $join->on('cl.id', '=', 'cc.client_id')
                    ->where('cc.type', '=', 'TITULAR');
            })
            ->whereIn('c.business_id', $businessIds)
            ->whereIn('i.status', ['finalizado', 'anulado']);

        if ($this->monthName) {
            $year = date('Y');
            $monthNumber = $this->utilService->getMonthNumber($this->monthName);
            $invoicesQuery->whereYear('i.invoice_date', $year)
                        ->whereMonth('i.invoice_date', $monthNumber);
        } else {
            $invoicesQuery->whereBetween('i.invoice_date', [$this->startDate, $this->endDate]);
        }

        $invoices = $invoicesQuery->select(
            'i.*',
            'c.sync_id',
            'c.agency',
            'c.collection_state',
            'b.name as business_name',
            'cl.name as client_name',
            'cl.ci as client_ci'
        )->get();

        foreach ($invoices as $invoice) {
            $invoiceValue = floatval($invoice->invoice_value);
            $taxPercent = floatval($invoice->tax_value);

            if ($taxPercent > 0) {
                $baseValue = $invoiceValue / (1 + ($taxPercent / 100));
            } else {
                $baseValue = 0;
            }

            $monetaryIva = $invoiceValue - $baseValue;

            $dataBox[] = [
                $invoice->agency,
                $invoice->client_ci,
                $invoice->client_name,
                $invoice->business_name . '-' . $invoice->sync_id,
                $this->formatInvoiceAccessForIdCredito($invoice->invoice_access_key),
                $invoice->invoice_date,
                $invoice->invoice_date,
                0,
                0,
                0,
                0,
                $this->formatForExcel($baseValue),
                $this->formatForExcel($monetaryIva),
                0,
                0,
                0,
                $this->formatForExcel($invoiceValue),
                0,
                $invoice->invoice_method,
                $invoice->invoice_institution,
                $invoice->invoice_reference,
                $this->utilService->setState($invoice->collection_state),
                $invoice->status === 'finalizado' ? 'Facturado' : $invoice->status
            ];
        }
    }

    private function formatInvoiceAccessForIdCredito($accessKey)
    {
        if (empty($accessKey) || strlen($accessKey) < 35) {
            return null;
        }

        $segment = substr($accessKey, 24, 15);
        Log::info('Formatting invoice access key segment', ['segment' => $segment]);
        
        $part1 = substr($segment, 0, 3);
        $part2 = substr($segment, 3, 3);
        $part3 = substr($segment, 6); 

        return sprintf('%s-%s-%s', $part1, $part2, $part3);
    }

    private function formatForExcel($value)
    {
        $num = floatval($value);

        if ($num < 0) {
            return '';
        }

        if ($num == 0) {
            return 0;
        }

        return round($num, 2);
    }

    private function calculateTaxPercent($taxValue, $invoiceValue)
    {
        $inv = floatval($invoiceValue);
        if ($inv <= 0) return 0;

        $percent = (floatval($taxValue) / 100) * $inv;
        return $percent;
    }

    private function calculateTotals($dataBox)
    {
        $totals = [
            'condonacion' => 0,
            'capital' => 0,
            'interes' => 0,
            'mora' => 0,
            'gestion_sefil' => 0,
            'iva' => 0,
            'gestion_faces' => 0,
            'judicial' => 0,
            'otros' => 0,
            'total' => 0,
            'capital_contable' => 0
        ];

        foreach ($dataBox as $row) {
            if (is_array($row) && count($row) >= 23 && is_numeric($row[8])) {
                $totals['condonacion'] += floatval($row[7]);  // CONDONACION
                $totals['capital'] += floatval($row[8]);       // CAPITAL
                $totals['interes'] += floatval($row[9]);       // INTERES
                $totals['mora'] += floatval($row[10]);         // MORA
                $totals['gestion_sefil'] += floatval($row[11]); // GESTIÓN COBRANZA SEFIL
                $ivaValue = floatval($row[12]);
                $isInvoiceRow = (floatval($row[8]) == 0 && floatval($row[16]) > 0 && floatval($row[11]) >= 0);
                if ($isInvoiceRow) {
                    $monetaryIva = floatval($row[16]) - floatval($row[11]);
                    $totals['iva'] += $monetaryIva;
                } else {
                    $totals['iva'] += $ivaValue;
                }
                $totals['gestion_faces'] += floatval($row[13]); // GESTIÓN COBRANZA FACES
                $totals['judicial'] += floatval($row[14]);     // GASTOS JUDICIALES
                $totals['otros'] += floatval($row[15]);        // OTROS
                $totals['total'] += floatval($row[16]);        // TOTAL PAGADO
                $totals['capital_contable'] += floatval($row[17]); // CAPITAL CONTABLE RECUPERADO
            }
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        return $totals;
    }
}