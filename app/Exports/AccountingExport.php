<?php

namespace App\Exports;

use App\Services\UtilService;
use Illuminate\Support\Facades\DB;
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
    protected $businessId;
    protected $group;
    protected $startDate;
    protected $endDate;
    protected $monthName;
    protected $businessName;
    protected $userName;
    protected $utilService;

    public function __construct($businessId, $group, $startDate, $endDate, $monthName, $businessName, $userName)
    {
        $this->businessId = $businessId;
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

        $this->addInvoices($dataBox, $this->businessId);
        usort($dataBox, fn($a, $b) => strtotime($a[6]) - strtotime($b[6]));

        $this->addTotalsAndFooter($dataBox);

        return collect($dataBox);
    }

    private $condonationsCache = [];

    private function loadCondonations($creditIds)
    {
        if (empty($creditIds)) return;

        $startDate = $this->startDate;
        $endDate = $this->endDate;

        if ($this->monthName) {
            $year = date('Y');
            $monthNumber = $this->utilService->getMonthNumber($this->monthName);
            $startDate = date('Y-m-01', strtotime("$year-$monthNumber-01"));
            $endDate = date('Y-m-t', strtotime("$year-$monthNumber-01"));
        }

        $condonations = DB::table('condonations')
            ->whereIn('credit_id', $creditIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'rechazado')
            ->select('credit_id', 'amount', DB::raw('DATE(created_at) as condonation_date'))
            ->get();

        foreach ($condonations as $cond) {
            $key = $cond->credit_id . '_' . $cond->condonation_date;
            $this->condonationsCache[$key] = floatval($cond->amount);
        }
    }

    private function getPaymentsQuery()
    {
        $query = DB::table('collection_payments as cp')
            ->where('cp.business_id', $this->businessId);

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
            $condonation = $this->getCondonationAmount($creditId, $firstPayment->payment_date);

            $dataBox[] = $this->buildPaymentRow($firstPayment, $amounts, $condonation);
        }

        return $dataBox;
    }

    private function processUngroupedPayments($payments)
    {
        $dataBox = [];

        foreach ($payments as $payment) {
            $amounts = $this->sumPaymentAmounts(collect([$payment]));
            $condonation = $this->getCondonationAmount($payment->credit_id, $payment->payment_date);

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
        return [
            $payment->agency,
            $payment->client_ci,
            $payment->client_name,
            $this->businessName . '-' . $payment->sync_id,
            ($payment->comprobante_number ?? $payment->id),
            $payment->payment_deposit_date,
            $payment->payment_date,
            round($condonation, 2),
            round($amounts['capital'], 2),
            round($amounts['interest'], 2),
            round($amounts['mora'], 2),
            round($amounts['managementExpenses'], 2),
            0,
            round($amounts['collectionExpenses'], 2),
            round($amounts['legalExpenses'], 2),
            round($amounts['otherValues'], 2),
            round($amounts['total'], 2),
            round($amounts['capital'] * 0.07, 2),
            $payment->payment_method,
            $payment->financial_institution ?? 'efectivo',
            $payment->payment_reference ?? 'efectivo',
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

    private function getCondonationAmount($creditId, $paymentDate)
    {
        $dateOnly = date('Y-m-d', strtotime($paymentDate));
        $key = $creditId . '_' . $dateOnly;

        return $this->condonationsCache[$key] ?? 0;
    }

    private function addInvoices(&$dataBox, $businessId)
    {
        $invoicesQuery = DB::table('invoices as i')
            ->join('credits as c', 'c.id', '=', 'i.credit_id')
            ->join('client_credit as cc', 'cc.credit_id', '=', 'c.id')
            ->join('clients as cl', function($join) {
                $join->on('cl.id', '=', 'cc.client_id')
                    ->where('cc.type', '=', 'TITULAR');
            })
            ->where('c.business_id', $businessId)
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
            'cl.name as client_name',
            'cl.ci as client_ci'
        )->get();

        foreach ($invoices as $invoice) {
            $invoiceValue = floatval($invoice->invoice_value);
            $taxValue = floatval($invoice->tax_value);
            $baseValue = $invoiceValue - $taxValue;

            $dataBox[] = [
                $invoice->agency,
                $invoice->client_ci,
                $invoice->client_name,
                $this->businessName.'-'.$invoice->sync_id,
                $invoice->invoice_number,
                $invoice->invoice_date,
                $invoice->invoice_date,
                0,
                0,
                0,
                0,
                round($baseValue, 2),
                round($taxValue, 2),
                0,
                0,
                0,
                round($invoiceValue, 2),
                0,
                $invoice->invoice_method,
                $invoice->invoice_institution,
                $invoice->invoice_access_key,
                $this->utilService->setState($invoice->collection_state),
                $invoice->status === 'finalizado' ? 'Facturado' : $invoice->status
            ];
        }
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
            // Verificar que sea un array con datos numéricos (no las filas de totales o footer)
            if (is_array($row) && count($row) >= 23 && is_numeric($row[8])) {
                $totals['condonacion'] += floatval($row[7]);  // CONDONACION
                $totals['capital'] += floatval($row[8]);       // CAPITAL
                $totals['interes'] += floatval($row[9]);       // INTERES
                $totals['mora'] += floatval($row[10]);         // MORA
                $totals['gestion_sefil'] += floatval($row[11]); // GESTIÓN COBRANZA SEFIL
                $totals['iva'] += floatval($row[12]);          // IVA GAS. COB.
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