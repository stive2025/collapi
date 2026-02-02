<?php

namespace App\Exports;

use Carbon\Carbon;
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

class CondonacionExport implements FromCollection, WithHeadings, WithColumnFormatting, WithCustomStartCell, WithEvents, WithDrawings, ShouldAutoSize, WithStyles
{
    public function columnFormats(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A9:M9')->getFont()->setBold(true);
        $sheet->getStyle('A9:M9')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
        $sheet->getStyle('A9:M9')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A9:M9')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF9619');
        $sheet->getStyle('E9:M9')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEB9C');
        for ($col = 'A'; $col <= 'M'; $col++) {
            $sheet->getStyle($col . '8:' . $col . '500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }
        return [
            'A9'  => [ 'font' => ['size' => 10], 'borders' => [ 'top'=>[ 'color'=>[ 'rgb' => '808080' ] ] ] ],
            'B9'  => ['font' => ['size' => 10]],
            'C9'  => ['font' => ['size' => 10]],
            'D9'  => ['font' => ['size' => 10]],
            'E9'  => ['font' => ['size' => 10]],
            'F9'  => ['font' => ['size' => 10]],
            'G9'  => ['font' => ['size' => 10]],
            'H9'  => ['font' => ['size' => 10]],
            'I9'  => ['font' => ['size' => 10]],
            'J9'  => ['font' => ['size' => 10]],
            'K9'  => ['font' => ['size' => 10]],
            'L9'  => ['font' => ['size' => 10]],
            'M9'  => ['font' => ['size' => 10]]
        ];
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('This is my logo');
        $drawing->setPath(public_path('/logo.png'));
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
        return [
            ['HISTÓRICO DE CONDONACIONES'],
            ['Fecha: '.request('fecha_inicio').'-'.request('fecha_final')],
            ['Cartera:'.request('cartera')],
            [ '', '', '', '', '', 'Valores Condonados', '', '', '', '', '', '', '', '' ],
            [
                'CEDULA', 'NOMBRE', 'ID CREDITO', 'FECHA', 'NRO. CONDONACIÓN',
                'CAPITAL', 'INTERES', 'MORA', 'SEGURO DESGRAVAMEN', 'GASTOS DE COBRANZA', 'GASTOS JUDICIALES', 'OTROS', 'TOTAL CONDONADO'
            ]
        ];
    }

    public function registerEvents(): array {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $position_last = count($this->headings()[4]);
                $column = Coordinate::stringFromColumnIndex($position_last);
                foreach ([1,4,5,6,7] as $row) {
                    $cells = "A{$row}:{$column}{$row}";
                    $event->sheet->mergeCells($cells);
                    $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);
                }
                $cells = "F8:M8";
                $event->sheet->mergeCells($cells);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setSize(12);
                $event->sheet->getDelegate()->getStyle($cells)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
                $event->sheet->getDelegate()->getStyle($cells)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEB9C');
            }
        ];
    }

    public function rewriteValue($value){
        if($value>0){
            return bcdiv($value,'1',2);
        }else{
            return strval("0");
        }
    }

    public function collection()
    {
        $creditos = DB::table('condonations')
            ->when(request()->filled('agente'), function($query){
                $query->where('byUser', request('agente'));
            })
            ->when(request()->filled('fecha_inicio'), function($query){
                $query->whereBetween(DB::raw('DATE(fecha)'), [request('fecha_inicio'), request('fecha_final')]);
            })
            ->when(request()->filled('cartera'), function($query){
                if(request('cartera')!=""){
                    $query->where('cartera', request('cartera'));
                }
            })->get();

        $data_box = [];
        foreach ($creditos as $credito) {
            $prev = json_decode($credito->prevDates);
            $post = json_decode($credito->postDates);
            $capital = floatval($prev->capital ?? 0) - floatval($post->capital ?? 0);
            $interes = floatval($prev->interest ?? 0) - floatval($post->interest ?? 0);
            $mora = floatval($prev->mora ?? 0) - floatval($post->mora ?? 0);
            $seguro_desgravamen = floatval($prev->safe ?? 0) - floatval($post->safe ?? 0);
            $gastos_cobranza = floatval($prev->management_collection_expenses ?? 0) - floatval($post->management_collection_expenses ?? 0);
            $gastos_judiciales = floatval($prev->legal_expenses ?? 0) - floatval($post->legal_expenses ?? 0);
            $otros_valores = floatval($prev->other_values ?? 0) - floatval($post->other_values ?? 0);
            $total_condonado = $capital + $interes + $mora + $seguro_desgravamen + $gastos_cobranza + $gastos_judiciales + $otros_valores;
            $cliente = DB::table($credito->cartera)->where('id', $credito->credito)->first();
            $data_prev = [
                'CEDULA' => $cliente->ci ?? '',
                'NOMBRE' => $cliente->name ?? '',
                'ID CREDITO' => $credito->cartera . '_' . ($cliente->credito ?? ''),
                'FECHA' => $credito->fecha,
                'NRO. CONDONACIÓN' => $credito->id,
                'CAPITAL' => $this->rewriteValue($capital),
                'INTERES' => $this->rewriteValue($interes),
                'MORA' => $this->rewriteValue($mora),
                'SEGURO DESGRAVAMEN' => $this->rewriteValue($seguro_desgravamen),
                'GASTOS DE COBRANZA' => $this->rewriteValue($gastos_cobranza),
                'GASTOS JUDICIALES' => $this->rewriteValue($gastos_judiciales),
                'OTROS' => $this->rewriteValue($otros_valores),
                'TOTAL CONDONADO' => $this->rewriteValue($total_condonado)
            ];
            $data_box[] = $data_prev;
        }
        // Footer
        $data_box[] = [ 'CEDULA' => '', 'NOMBRE' => '' ];
        $data_box[] = [ 'CEDULA' => 'Generado por:', 'NOMBRE' => request('user') ];
        $data_box[] = [ 'CEDULA' => 'Hora y fecha:', 'NOMBRE' => date('Y/m/d H:i:s', time()-18000) ];
        return collect($data_box);
    }
}
