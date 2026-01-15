<?php

namespace App\Exports;

use App\Services\UtilService;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
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

class DireccionesExport implements FromCollection,WithHeadings,WithColumnFormatting,WithCustomStartCell,WithDrawings,ShouldAutoSize,WithStyles,WithEvents
{
    protected $business_id;
    protected $user_id;
    protected $agencies;
    protected $business_name;
    protected $user_name;
    protected $utilService;

    public function __construct($business_id, $user_id, $agencies, $business_name, $user_name)
    {
        $this->business_id = $business_id;
        $this->user_id = $user_id;
        $this->agencies = $agencies;
        $this->business_name = $business_name;
        $this->user_name = $user_name;
        $this->utilService = new UtilService();
    }

   public function columnFormats(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A10:O10')->getFont()->setBold(true);
        $sheet->getStyle('A10:O10')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
        $sheet->getStyle('A10:O10')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A10:O10')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF9619');
        $sheet->getStyle('A10:O10')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        $sheet->getStyle('A10:A500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B10:B500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C10:C500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D10:D500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E10:E500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F10:F500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G10:G500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H10:H500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I10:I500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('J10:J500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('K10:K500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('L10:L500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('M10:M500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('N10:N500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('O10:O500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        return [
            'A13'  => [
                'font' => ['size' => 10],
                'borders' => [
                    'top'=>[
                        'color'=>[
                            'rgb' => '808080'
                        ]
                    ]
                ]
            ],
            'B13'  => ['font' => ['size' => 10]],
            'C13'  => ['font' => ['size' => 10]],
            'D13'  => ['font' => ['size' => 10]],
            'E13'  => ['font' => ['size' => 10]],
            'F13'  => ['font' => ['size' => 10]],
            'G13'  => ['font' => ['size' => 10]],
            'H13'  => ['font' => ['size' => 10]]
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
        return 'A4';
    }

    public function headings(): array
    {
        $agencies_array = is_array($this->agencies) ? $this->agencies : json_decode($this->agencies, true);
        $agencies_string = is_array($agencies_array) ? implode(', ', $agencies_array) : $this->agencies;

        return [
            ['LISTADO DE DIRECCIONES'],
            ['Agente: ' . $this->user_name],
            ['Cartera: ' . $this->business_name],
            ['Fecha corte: ' . date('Y/m/d H:i:s', time() - 18000)],
            ['Agencia(s): ' . $agencies_string],
            [''],
            [
                "Crédito / Operación",
                "Cliente cédula",
                "Cliente nombre",
                "Cliente tipo",
                "Días de Mora",
                "Total Pendiente",
                "Provincia",
                "Canton",
                "Parroquia",
                "Direccion",
                "Canton_microempresa",
                "Parroquia_microempresa",
                "Direccion_microempresa",
                "Geolocalización",
                "Fecha_Ult_Gestion"
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $position_last = count($this->headings()[4]);

                $column = Coordinate::stringFromColumnIndex($position_last);

                $cells = "A8:N8";
                $event->sheet->mergeCells($cells);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setSize(12);
                $event->sheet->getDelegate()->getStyle($cells)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('F5DA27');
            }
        ];
    }

    public function collection()
    {
        $agencies_array = is_array($this->agencies) ? $this->agencies : json_decode($this->agencies, true);

        $data_direcciones = DB::table('credits as cr')
            ->select([
                'cr.sync_id as credit_operation',
                'cl.ci as client_document',
                'cl.name as client_name',
                'cc.type as client_type',
                'cr.days_past_due as days_overdue',
                'cr.total_amount as total_pending',
                'cd_dom.province as province',
                'cd_dom.canton as canton',
                'cd_dom.parish as parroquia',
                'cd_dom.address as address',
                'cd_work.canton as canton_microempresa',
                'cd_work.parish as parroquia_microempresa',
                'cd_work.address as address_microempresa',
                DB::raw('CONCAT_WS(",", cd_dom.latitude, cd_dom.longitude) as geolocation'),
                DB::raw("DATE_FORMAT(mg.fecha, '%Y-%m-%d %H:%i:%s') as last_management_date")
            ])
            ->leftJoin(DB::raw('
                (
                    SELECT m.credit_id, MAX(m.created_at) as fecha
                    FROM management m
                    WHERE m.substate IN ("OFERTA DE PAGO", "COMPROMISO DE PAGO", "CONVENIO DE PAGO", "VISITA CAMPO")
                    GROUP BY m.credit_id
                ) as mg
            '), 'mg.credit_id', '=', 'cr.id')
            ->leftJoin(DB::raw('
                (
                    SELECT m1.credit_id, m1.substate as last_substate
                    FROM management m1
                    INNER JOIN (
                        SELECT credit_id, MAX(created_at) as max_date
                        FROM management
                        GROUP BY credit_id
                    ) m2 ON m1.credit_id = m2.credit_id AND m1.created_at = m2.max_date
                ) as last_mg
            '), 'last_mg.credit_id', '=', 'cr.id')
            ->join('client_credit as cc', 'cc.credit_id', '=', 'cr.id')
            ->join('clients as cl', 'cl.id', '=', 'cc.client_id')
            ->leftJoin('collection_directions as cd_dom', function ($join) {
                $join->on('cd_dom.client_id', '=', 'cl.id')
                    ->where('cd_dom.type', '=', 'DOMICILIO');
            })
            ->leftJoin('collection_directions as cd_work', function ($join) {
                $join->on('cd_work.client_id', '=', 'cl.id')
                    ->where('cd_work.type', '=', 'TRABAJO');
            })
            ->where('cr.business_id', $this->business_id)
            ->where('cr.user_id', $this->user_id)
            ->where('cr.sync_status', 'ACTIVE')
            ->where(function ($query) {
                $query->where('cr.management_status', 'VISITA CAMPO')
                    ->orWhere('last_mg.last_substate', 'VISITA CAMPO');
            })
            ->whereIn('cr.agency', $agencies_array)
            ->orderBy('cr.sync_id')
            ->get();

        // Aplicar cálculo de gastos de cobranza solo para SEFIL_1 y SEFIL_2
        $shouldCalculateExpenses = in_array($this->business_name, ['SEFIL_1', 'SEFIL_2']);

        if ($shouldCalculateExpenses) {
            $data_direcciones->transform(function ($credit) {
                $calculatedExpenses = $this->utilService->calculateManagementCollectionExpenses(
                    $credit->total_pending ?? 0,
                    $credit->days_overdue ?? 0
                );
                $credit->total_pending = floatval($credit->total_pending ?? 0) + $calculatedExpenses;
                return $credit;
            });
        }

        return collect($data_direcciones);
    }
}
