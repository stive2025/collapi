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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->getDelegate();
                $lastCol = 'AG';

                $sheet->mergeCells('C1:' . $lastCol . '1');
                $sheet->getStyle('C1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle('B3:B7')->getFont()->setBold(true);

                $sheet->getStyle('D3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '0000FF']],
                ]);

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

                $sheet->mergeCells('B9:B10');
                $sheet->mergeCells('C9:F9');
                $sheet->mergeCells('G9:G10');

                for ($i = 0; $i < 11; $i++) {
                    $c1 = Coordinate::stringFromColumnIndex(8 + $i * 2);
                    $c2 = Coordinate::stringFromColumnIndex(8 + $i * 2 + 1);
                    $sheet->mergeCells($c1 . '9:' . $c2 . '9');
                }

                $sheet->mergeCells('AD9:AG9');

                $dataStart = 11;
                $totalRow  = $dataStart + $this->agencyCount;

                if ($this->agencyCount > 0) {
                    $sheet->getStyle('A' . $dataStart . ':' . $lastCol . $totalRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'D0D0D0'],
                            ],
                        ],
                    ]);

                    $sheet->getStyle('C' . $dataStart . ':' . $lastCol . $totalRow)->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

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

                $pctFmt    = '0.00"%"';
                $numFmt    = '#,##0.00';
                $moneyFmt  = '"$ "#,##0.00';

                foreach (['D', 'F'] as $col) {
                    $sheet->getStyle($col . $dataStart . ':' . $col . $totalRow)
                        ->getNumberFormat()->setFormatCode($pctFmt);
                }
                $sheet->getStyle('AE' . $dataStart . ':AE' . $totalRow)
                    ->getNumberFormat()->setFormatCode($pctFmt);
                $sheet->getStyle('AG' . $dataStart . ':AG' . $totalRow)
                    ->getNumberFormat()->setFormatCode($pctFmt);

                $sheet->getStyle('E' . $dataStart . ':E' . $totalRow)
                    ->getNumberFormat()->setFormatCode($numFmt);

                for ($i = 0; $i < 11; $i++) {
                    $col = Coordinate::stringFromColumnIndex(9 + $i * 2);
                    $sheet->getStyle($col . $dataStart . ':' . $col . $totalRow)
                        ->getNumberFormat()->setFormatCode($numFmt);
                }

                $sheet->getStyle('AF' . $dataStart . ':AF' . $totalRow)
                    ->getNumberFormat()->setFormatCode($moneyFmt);

                $costosExcelRow      = $totalRow + 2;
                $valorExcelRow       = $totalRow + 3;
                $totalAPagarExcelRow = $totalRow + 4;
                $formulaCols = ['G', 'H', 'J', 'L', 'N', 'P', 'S', 'U', 'W', 'Y', 'AA', 'AC'];

                $sheet->setCellValue("A{$costosExcelRow}", 'COSTOS');
                $costosValues = [
                    'G'  => 0.75,
                    'H'  => 0.85,
                    'J'  => 0.95,
                    'L'  => 1.05,
                    'N'  => 1.15,
                    'P'  => 1.50,
                    'S'  => 0.09,
                    'U'  => 0.124,
                    'W'  => 0.13,
                    'Y'  => 0.14,
                    'AA' => 0.17,
                    'AC' => 0.17,
                ];
                foreach ($formulaCols as $col) {
                    $sheet->setCellValue("{$col}{$costosExcelRow}", $costosValues[$col] ?? 0);
                }

                $sheet->setCellValue("A{$valorExcelRow}", 'VALOR');
                foreach ($formulaCols as $col) {
                    $sheet->setCellValue("{$col}{$valorExcelRow}", "={$col}{$costosExcelRow}*{$col}{$totalRow}");
                }

                $sheet->setCellValue("A{$totalAPagarExcelRow}", 'TOTAL A PAGAR');
                $sumParts = array_map(fn($col) => "{$col}{$valorExcelRow}", $formulaCols);
                $sheet->setCellValue("B{$totalAPagarExcelRow}", '=' . implode('+', $sumParts));

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

                foreach ([$costosExcelRow, $valorExcelRow, $totalAPagarExcelRow] as $sRow) {
                    $sheet->getStyle('A' . $sRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

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

                foreach ($formulaCols as $col) {
                    $sheet->getStyle($col . $valorExcelRow)->getNumberFormat()->setFormatCode('#,##0.00');
                }
                $sheet->getStyle('B' . $totalAPagarExcelRow)->getNumberFormat()->setFormatCode('"$ "#,##0.00');
                
                $footerRow = $totalRow + 6;
                $sheet->getStyle('A' . $footerRow)->getFont()->setBold(true);
                $sheet->getStyle('A' . ($footerRow + 1))->getFont()->setBold(true);
            },
        ];
    }

    public function collection()
    {
        $dayNames   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
        $monthNames = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $elaboratedDate = strtolower($dayNames[(int) date('w')])
            . ', ' . (int) date('j')
            . ' de ' . $monthNames[(int) date('n') - 1]
            . ' de ' . date('Y');

        $groupHeaders = [
            '',
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
            ['', '', 'REPORTE MENSUAL DE GESTIONES DE CALL CENTER / COBRANZAS'],
            [''],
            ['', 'Periodo:',     '', $this->period],
            ['', 'Generado:',    '', $this->userName],
            ['', 'EMPRESA:',     '', $this->businessName],
            ['', 'Responsable:', '', $this->userName],
            ['', 'Elaborado:',   '', $elaboratedDate],
            [''],
            $groupHeaders,
            $detailHeaders,
        ];

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

        $agencies = $allCredits->pluck('agency')->unique()->sort()->values()->toArray();
        $this->agencyCount = count($agencies);

        $assignedAll      = $allCredits->filter(fn($c) => (int)$c->days_past_due > 0);
        $totalNroCreditos = $assignedAll->count();
        $totalMonto       = (float) $assignedAll->sum('total_amount');

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

        $preventiveByAgency = $allCredits
            ->filter(fn($c) => (int)$c->days_past_due === 0)
            ->groupBy('agency');
        
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

            $row = [
                '',
                strtoupper($agency),
                $nroCreditos,
                $totalNroCreditos > 0 ? round(($nroCreditos / $totalNroCreditos) * 100, 2) : 0,
                round($montoAsignado, 2),
                $totalMonto > 0 ? round(($montoAsignado / $totalMonto) * 100, 2) : 0,
                $gestiones_preventivas,
            ];

            foreach ($this->ranges as $i => $range) {
                $row[] = $rangeCounts[$i];
                $row[] = round($rangeAmounts[$i], 2);
            }

            $row[] = $totalCreditos;
            $row[] = 0;
            $row[] = round($totalMontoPagado, 2);
            $row[] = 0;

            $dataReporte[] = $row;
        }

        $grandCreditos = array_sum($totalCounts);
        $grandMonto    = array_sum($totalAmounts);

        foreach ($dataReporte as &$row) {
            $row[30] = $grandCreditos > 0 ? round(($row[29] / $grandCreditos) * 100, 2) : 0;
            $row[32] = $grandMonto    > 0 ? round(($row[31] / $grandMonto)    * 100, 2) : 0;
        }
        unset($row);

        $totalRow = [
            '',
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

        $dataReporte[] = [''];
        $dataReporte[] = [''];
        $dataReporte[] = [''];
        $dataReporte[] = [''];

        return collect(array_merge($headerRows, $dataReporte));
    }
}
