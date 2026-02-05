<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ResumePaymentsWithManagement implements FromCollection, WithCustomStartCell, WithDrawings, ShouldAutoSize, WithEvents
{
    protected $campainId;
    protected $userName;
    protected $businessName;
    protected $period;
    protected $agencyCount = 0;

    protected $ranges = [
        ['min' => 1,    'max' => 5,      'useCapital' => false, 'label' => '2 a 5'],
        ['min' => 6,    'max' => 15,     'useCapital' => false, 'label' => '6 a 15'],
        ['min' => 16,   'max' => 30,     'useCapital' => false, 'label' => '16 a 30'],
        ['min' => 31,   'max' => 60,     'useCapital' => true,  'label' => '31 a 60'],
        ['min' => 61,   'max' => 90,     'useCapital' => true,  'label' => '61 a 90'],
        ['min' => 91,   'max' => 120,    'useCapital' => false, 'label' => '91 a 120'],
        ['min' => 121,  'max' => 180,    'useCapital' => false, 'label' => '121 a 180'],
        ['min' => 181,  'max' => 360,    'useCapital' => false, 'label' => '181 a 360'],
        ['min' => 361,  'max' => 719,    'useCapital' => false, 'label' => '361 a 719'],
        ['min' => 720,  'max' => 1080,   'useCapital' => false, 'label' => '720 A 1080'],
        ['min' => 1081, 'max' => 999999, 'useCapital' => false, 'label' => '> 1080'],
    ];

    public function __construct($campainId, $userName)
    {
        $this->campainId = $campainId;
        $this->userName  = $userName;

        $monthsInSpanish = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
        ];
        $this->period = $monthsInSpanish[(int) date('m')] . ' ' . date('Y');

        $campain = \App\Models\Campain::find($campainId);
        if ($campain) {
            $business = \App\Models\Business::find($campain->business_id);
            $this->businessName = $business ? strtoupper($business->name) : 'N/A';
        } else {
            $this->businessName = 'N/A';
        }
    }

    // ─── Drawing ─────────────────────────────────────────────────────────────
    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');
        $drawing->setPath(public_path('/logo.png'));
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');
        return $drawing;
    }

    public function startCell(): string
    {
        return 'A1';
    }

    // ─── AfterSheet: merges, estilos, formatos numéricos ─────────────────────
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->getDelegate();
                $lastCol = 'AF';

                // ── 1. TÍTULO (Row 1) ──────────────────────────────────────
                $sheet->mergeCells('C1:' . $lastCol . '1');
                $sheet->getStyle('C1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // ── 2. INFO HEADER labels B3:B7 (negrita) ─────────────────
                $sheet->getStyle('B3:B7')->getFont()->setBold(true);

                // Periodo value → azul + negrita
                $sheet->getStyle('D3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '0000FF']],
                ]);

                // ── 3. ESTILO NARANJA en headers (rows 9-10) ──────────────
                $orangeStyle = [
                    'font' => ['bold' => true, 'size' => 9],
                    'fill' => [
                        'fillType'  => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FF9619'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => 'FFFFFF'],
                        ],
                    ],
                ];
                $sheet->getStyle('A9:' . $lastCol . '10')->applyFromArray($orangeStyle);

                // Borde exterior grueso en bloque header
                $sheet->getStyle('A9:' . $lastCol . '10')->applyFromArray([
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_THICK,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $sheet->getRowDimension(9)->setRowHeight(20);
                $sheet->getRowDimension(10)->setRowHeight(28);

                // ── 4. MERGEOS en fila 9 ───────────────────────────────────
                $sheet->mergeCells('A9:A10');   // Agencia
                $sheet->mergeCells('B9:E9');    // CARTERA ASIGNADA
                $sheet->mergeCells('F9:F10');   // Cartera Preventiva

                // Cada rango: 2 columnas empezando en G (col 7)
                for ($i = 0; $i < 11; $i++) {
                    $c1 = Coordinate::stringFromColumnIndex(7 + $i * 2);
                    $c2 = Coordinate::stringFromColumnIndex(7 + $i * 2 + 1);
                    $sheet->mergeCells($c1 . '9:' . $c2 . '9');
                }

                $sheet->mergeCells('AC9:AF9');  // CARTERA RECUPERADA

                // ── 5. DATOS: bordes, centrado, fila TOTAL ─────────────────
                $dataStart = 11;
                $totalRow  = $dataStart + $this->agencyCount;  // fila TOTAL

                if ($this->agencyCount > 0) {
                    // Bordes finos en datos + TOTAL
                    $sheet->getStyle('A' . $dataStart . ':' . $lastCol . $totalRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'D0D0D0'],
                            ],
                        ],
                    ]);

                    // Centrar columnas numéricas (B en adelante)
                    $sheet->getStyle('B' . $dataStart . ':' . $lastCol . $totalRow)->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                // Fila TOTAL: negrita + fondo gris + borde inferior doble
                $sheet->getStyle('A' . $totalRow . ':' . $lastCol . $totalRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType'  => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F2F2F2'],
                    ],
                    'borders' => [
                        'bottom' => [
                            'borderStyle' => Border::BORDER_DOUBLE,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // ── 6. FORMATO NUMÉRICO ────────────────────────────────────
                $pctFmt    = '0.00"%"';
                $numFmt    = '#,##0.00';
                $moneyFmt  = '"$ "#,##0.00';

                // Porcentajes: C, E (asignado), AD, AF (recuperado)
                foreach (['C', 'E'] as $col) {
                    $sheet->getStyle($col . $dataStart . ':' . $col . $totalRow)
                        ->getNumberFormat()->setFormatCode($pctFmt);
                }
                $sheet->getStyle('AD' . $dataStart . ':AD' . $totalRow)
                    ->getNumberFormat()->setFormatCode($pctFmt);
                $sheet->getStyle('AF' . $dataStart . ':AF' . $totalRow)
                    ->getNumberFormat()->setFormatCode($pctFmt);

                // Monto asignado (D)
                $sheet->getStyle('D' . $dataStart . ':D' . $totalRow)
                    ->getNumberFormat()->setFormatCode($numFmt);

                // Total recuperado por rango: cols H(8), J(10), L(12)… AB(28)
                for ($i = 0; $i < 11; $i++) {
                    $col = Coordinate::stringFromColumnIndex(8 + $i * 2);
                    $sheet->getStyle($col . $dataStart . ':' . $col . $totalRow)
                        ->getNumberFormat()->setFormatCode($numFmt);
                }

                // Monto recuperado (AE) → con signo $
                $sheet->getStyle('AE' . $dataStart . ':AE' . $totalRow)
                    ->getNumberFormat()->setFormatCode($moneyFmt);

                // ── 7. COSTOS / VALOR / TOTAL A PAGAR ──────────────────────────
                $costosExcelRow      = $totalRow + 2;
                $valorExcelRow       = $totalRow + 3;
                $totalAPagarExcelRow = $totalRow + 4;
                $formulaCols = ['G', 'H', 'J', 'L', 'N', 'P', 'S', 'U', 'W', 'Y', 'AA', 'AC'];

                // COSTOS – etiqueta + placeholders 0 (el usuario los edita en Excel)
                $sheet->setCellValue("A{$costosExcelRow}", 'COSTOS');
                foreach ($formulaCols as $col) {
                    $sheet->setCellValue("{$col}{$costosExcelRow}", 0);
                }

                // VALOR – fórmulas = COSTOS × TOTAL
                $sheet->setCellValue("A{$valorExcelRow}", 'VALOR');
                foreach ($formulaCols as $col) {
                    $sheet->setCellValue("{$col}{$valorExcelRow}", "={$col}{$costosExcelRow}*{$col}{$totalRow}");
                }

                // TOTAL A PAGAR – suma de la fila VALOR
                $sheet->setCellValue("A{$totalAPagarExcelRow}", 'TOTAL A PAGAR');
                $sumParts = array_map(fn($col) => "{$col}{$valorExcelRow}", $formulaCols);
                $sheet->setCellValue("B{$totalAPagarExcelRow}", '=' . implode('+', $sumParts));

                // Estilos: negrita + fondo claro + bordes para las 3 filas
                foreach ([$costosExcelRow, $valorExcelRow, $totalAPagarExcelRow] as $sRow) {
                    $sheet->getStyle('A' . $sRow . ':' . $lastCol . $sRow)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => [
                            'fillType'  => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E8F0FE'],
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'B0C4DE'],
                            ],
                        ],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                // Etiquetas columna A: alineadas a la izquierda
                foreach ([$costosExcelRow, $valorExcelRow, $totalAPagarExcelRow] as $sRow) {
                    $sheet->getStyle('A' . $sRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // TOTAL A PAGAR: borde inferior doble + fondo más oscuro
                $sheet->getStyle('A' . $totalAPagarExcelRow . ':' . $lastCol . $totalAPagarExcelRow)->applyFromArray([
                    'fill' => [
                        'fillType'  => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'CCE0FF'],
                    ],
                    'borders' => [
                        'bottom' => [
                            'borderStyle' => Border::BORDER_DOUBLE,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Formato numérico: VALOR con decimales, TOTAL A PAGAR con signo $
                foreach ($formulaCols as $col) {
                    $sheet->getStyle($col . $valorExcelRow)->getNumberFormat()->setFormatCode('#,##0.00');
                }
                $sheet->getStyle('B' . $totalAPagarExcelRow)->getNumberFormat()->setFormatCode('"$ "#,##0.00');

                // ── 8. FOOTER labels negrita ────────────────────────────────────
                $footerRow = $totalRow + 6;
                $sheet->getStyle('A' . $footerRow)->getFont()->setBold(true);
                $sheet->getStyle('A' . ($footerRow + 1))->getFont()->setBold(true);
            },
        ];
    }

    // ─── Data ─────────────────────────────────────────────────────────────────
    public function collection()
    {
        // ── 0. Filas de encabezado (rows 1-10) ─────────────────────────────────
        $dayNames   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
        $monthNames = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $elaboratedDate = strtolower($dayNames[(int) date('w')])
            . ', ' . (int) date('j')
            . ' de ' . $monthNames[(int) date('n') - 1]
            . ' de ' . date('Y');

        $groupHeaders = [
            'Agencia',
            'CARTERA ASIGNADA',
            '', '', '',
            'Cartera Preventiva con gestión',
        ];
        foreach ($this->ranges as $range) {
            $groupHeaders[] = $range['label'];
            $groupHeaders[] = '';
        }
        $groupHeaders[] = 'CARTERA RECUPERADA';
        $groupHeaders[] = '';
        $groupHeaders[] = '';
        $groupHeaders[] = '';

        $detailHeaders = [
            '',
            'No. Créditos',
            '%',
            'Monto',
            '%',
            '',
        ];
        foreach ($this->ranges as $range) {
            $detailHeaders[] = 'No. Créditos';
            $detailHeaders[] = 'Total recuperado';
        }
        $detailHeaders[] = 'No. Créditos';
        $detailHeaders[] = '%';
        $detailHeaders[] = 'Monto';
        $detailHeaders[] = '%';

        $headerRows = [
            ['', '', 'REPORTE MENSUAL DE GESTIONES DE CALL CENTER / COBRANZAS'], // Row 1
            [''],                                                                // Row 2
            ['', 'Periodo:',     '', $this->period],                             // Row 3
            ['', 'Generado:',    '', $this->userName],                           // Row 4
            ['', 'EMPRESA:',     '', $this->businessName],                       // Row 5
            ['', 'Responsable:', '', $this->userName],                           // Row 6
            ['', 'Elaborado:',   '', $elaboratedDate],                           // Row 7
            [''],                                                                // Row 8
            $groupHeaders,                                                       // Row 9
            $detailHeaders,                                                      // Row 10
        ];

        // ── 1. Créditos asignados (collection_credits) ────────────────────────
        $allCredits = DB::table('collection_credits as cc')
            ->join('credits as c', 'c.id', '=', 'cc.credit_id')
            ->where('cc.campain_id', $this->campainId)
            ->select(
                'cc.credit_id',
                'c.agency',
                DB::raw('MAX(cc.days_past_due) as days_past_due'),
                DB::raw('MAX(cc.total_amount) as total_amount')
            )
            ->groupBy('cc.credit_id', 'c.agency')
            ->get();

        // ── 2. Agencias ordenadas ──────────────────────────────────────────────
        $agencies = $allCredits->pluck('agency')->unique()->sort()->values()->toArray();
        $this->agencyCount = count($agencies);

        // ── 3. Totales globales (dias > 0) ─────────────────────────────────────
        $assignedAll      = $allCredits->filter(fn($c) => (int)$c->days_past_due > 0);
        $totalNroCreditos = $assignedAll->count();
        $totalMonto       = (float) $assignedAll->sum('total_amount');

        // ── 4. Pagos con gestión ───────────────────────────────────────────────
        $creditIds = $allCredits->pluck('credit_id')->toArray();

        $allPayments = DB::table('collection_payments as cp')
            ->join('credits as c', 'c.id', '=', 'cp.credit_id')
            ->whereIn('cp.credit_id', $creditIds)
            ->where('cp.campain_id', $this->campainId)
            ->where('cp.with_management', 'SI')
            ->where('cp.days_past_due_auto', '>', 0)
            ->select(
                'cp.payment_reference',
                'cp.payment_value',
                'cp.capital',
                'cp.other_values',
                'cp.days_past_due_auto',
                'c.agency'
            )
            ->get();

        $paymentsByAgency = $allPayments->groupBy('agency');

        // ── 5. Preventivos por agencia ─────────────────────────────────────────
        $preventiveByAgency = $allCredits
            ->filter(fn($c) => (int)$c->days_past_due === 0)
            ->groupBy('agency');

        // ── 6. Construir filas ─────────────────────────────────────────────────
        $dataReporte      = [];
        $totalCounts      = array_fill(0, 11, 0);
        $totalAmounts     = array_fill(0, 11, 0.0);
        $totalPreventivas = 0;

        foreach ($agencies as $agency) {
            $agencyAssigned = $allCredits->filter(
                fn($c) => $c->agency === $agency && (int)$c->days_past_due > 0
            );
            $nroCreditos   = $agencyAssigned->count();
            $montoAsignado = (float) $agencyAssigned->sum('total_amount');

            // Preventivas con gestión efectiva
            $preventiveIds = isset($preventiveByAgency[$agency])
                ? $preventiveByAgency[$agency]->pluck('credit_id')->toArray()
                : [];

            $gestiones_preventivas = 0;
            if (!empty($preventiveIds)) {
                $gestiones_preventivas = DB::table('management')
                    ->where('campain_id', $this->campainId)
                    ->whereIn('credit_id', $preventiveIds)
                    ->whereIn('substate', [
                        'OFERTA DE PAGO',
                        'CLIENTE SE NIEGA A PAGAR',
                        'CLIENTE INDICA QUE NO ES SU DEUDA',
                        'COMPROMISO DE PAGO',
                        'MENSAJE A TERCEROS',
                        'MENSAJE DE TEXTO',
                        'MENSAJE EN BUZON DE VOZ',
                        'YA PAGO'
                    ])
                    ->where('days_past_due', 0)
                    ->count();
            }
            $totalPreventivas += $gestiones_preventivas;

            // Clasificar pagos por rango
            $agencyPayments = $paymentsByAgency[$agency] ?? collect();
            $rangeCounts    = [];
            $rangeAmounts   = [];

            foreach ($this->ranges as $i => $range) {
                $filtered = $agencyPayments->filter(
                    fn($p) => (int)$p->days_past_due_auto >= $range['min']
                           && (int)$p->days_past_due_auto <= $range['max']
                );

                $grouped         = $filtered->groupBy('payment_reference');
                $rangeCounts[$i] = $grouped->count();

                $rangeAmounts[$i] = 0.0;
                foreach ($grouped as $payments) {
                    foreach ($payments as $payment) {
                        $rangeAmounts[$i] += $range['useCapital']
                            ? floatval($payment->capital)
                            : floatval($payment->payment_value) - floatval($payment->other_values);
                    }
                }

                $totalCounts[$i]  += $rangeCounts[$i];
                $totalAmounts[$i] += $rangeAmounts[$i];
            }

            $totalCreditos    = array_sum($rangeCounts);
            $totalMontoPagado = array_sum($rangeAmounts);

            // Fila: índices [28]=No.Créditos rec, [29]=% placeholder, [30]=Monto rec, [31]=% placeholder
            $row = [
                strtoupper($agency),                                                          // [0]  A
                $nroCreditos,                                                                 // [1]  B
                $totalNroCreditos > 0 ? round(($nroCreditos / $totalNroCreditos) * 100, 2) : 0, // [2]  C
                round($montoAsignado, 2),                                                     // [3]  D
                $totalMonto > 0 ? round(($montoAsignado / $totalMonto) * 100, 2) : 0,        // [4]  E
                $gestiones_preventivas,                                                       // [5]  F
            ];

            foreach ($this->ranges as $i => $range) {
                $row[] = $rangeCounts[$i];              // No. Créditos del rango
                $row[] = round($rangeAmounts[$i], 2);   // Total recuperado del rango
            }

            $row[] = $totalCreditos;            // [28] AC
            $row[] = 0;                         // [29] AD – placeholder, se calcula después
            $row[] = round($totalMontoPagado, 2); // [30] AE
            $row[] = 0;                         // [31] AF – placeholder

            $dataReporte[] = $row;
        }

        // ── 7. Calcular % distribución CARTERA RECUPERADA ─────────────────────
        $grandCreditos = array_sum($totalCounts);
        $grandMonto    = array_sum($totalAmounts);

        foreach ($dataReporte as &$row) {
            $row[29] = $grandCreditos > 0 ? round(($row[28] / $grandCreditos) * 100, 2) : 0;
            $row[31] = $grandMonto    > 0 ? round(($row[30] / $grandMonto)    * 100, 2) : 0;
        }
        unset($row);

        // ── 8. Fila TOTAL ──────────────────────────────────────────────────────
        $totalRow = [
            'TOTAL',
            $totalNroCreditos,
            100,
            round($totalMonto, 2),
            100,
            $totalPreventivas,
        ];

        foreach ($this->ranges as $i => $range) {
            $totalRow[] = $totalCounts[$i];
            $totalRow[] = round($totalAmounts[$i], 2);
        }

        $totalRow[] = $grandCreditos;
        $totalRow[] = 100;
        $totalRow[] = round($grandMonto, 2);
        $totalRow[] = 100;

        $dataReporte[] = $totalRow;

        // ── 9. COSTOS / VALOR / TOTAL A PAGAR (placeholders; se rellenan en AfterSheet)
        $dataReporte[] = [''];   // spacer        (totalRow + 1)
        $dataReporte[] = [''];   // COSTOS        (totalRow + 2)
        $dataReporte[] = [''];   // VALOR         (totalRow + 3)
        $dataReporte[] = [''];   // TOTAL A PAGAR (totalRow + 4)

        // ── 10. Footer ─────────────────────────────────────────────────────────
        $dataReporte[] = [''];   // spacer        (totalRow + 5)
        $dataReporte[] = ['Generado por:', $this->userName];
        $dataReporte[] = ['Hora y fecha:', date('Y/m/d H:i:s')];

        return collect(array_merge($headerRows, $dataReporte));
    }
}
