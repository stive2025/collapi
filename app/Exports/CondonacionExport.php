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
        // Cabecera principal (A8:M8)
        $sheet->getStyle('A8:M8')->getFont()->setBold(true);
        $sheet->getStyle('A8:M8')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A8:M8')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF9619');
        $sheet->getStyle('A8:M8')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);

        // Subcabecera (F7:M7) - Valores Condonados
        $sheet->getStyle('F7:M7')->getFont()->setBold(true);
        $sheet->getStyle('F7:M7')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F7:M7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEB9C');
        $sheet->getStyle('F7:M7')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);

        // Centrado de todas las columnas de datos
        foreach (range('A', 'M') as $col) {
            $sheet->getStyle($col.'9:'.$col.'500')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }
        return [];
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
        return 'A8';
    }

    public function headings(): array
    {
        return [
            ['HISTÓRICO DE CONDONACIONES'],
            [''],
            ['Empresa: '.(request('business_id') ? DB::table('businesses')->where('id', request('business_id'))->value('name') : '')],
            [''],
            [
                'CEDULA',
                'NOMBRE',
                'ID CREDITO',
                'FECHA',
                'NRO. CONDONACIÓN',
                'CAPITAL',
                'INTERES',
                'MORA',
                'SEGURO DESGRAVAMEN',
                'GASTOS DE COBRANZA',
                'GASTOS JUDICIALES',
                'OTROS',
                'TOTAL CONDONADO',
            ]
        ];
    }

    public function registerEvents(): array{
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Merge para título principal
                $event->sheet->mergeCells('A8:M8');
                $event->sheet->getDelegate()->getStyle('A8:M8')->getFont()->setBold(true);
                $event->sheet->getDelegate()->getStyle('A8:M8')->getFont()->setSize(14);
                // Merge para Empresa
                $event->sheet->mergeCells('A10:M10');
                $event->sheet->getDelegate()->getStyle('A10:M10')->getFont()->setBold(true);
                // Merge para Valores Condonados
                $event->sheet->mergeCells('F12:M12');
                $event->sheet->getDelegate()->getStyle('F12:M12')->getFont()->setBold(true);
                $event->sheet->getDelegate()->getStyle('F12:M12')->getFont()->setSize(12);
                $event->sheet->getDelegate()->getStyle('F12:M12')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
                $event->sheet->getDelegate()->getStyle('F12:M12')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEB9C');
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
        $query = DB::table('condonations');
        if (request()->filled('business_id')) {
            $creditIds = DB::table('credits')
                ->where('business_id', request('business_id'))
                ->pluck('id');
            $query->whereIn('credito', $creditIds);
        }
        if (request()->filled('agente')) {
            $query->where('byUser', request('agente'));
        }
        if (request()->filled('start_date') && request()->filled('end_date')) {
            $query->whereBetween(DB::raw('DATE(fecha)'), [request('start_date'), request('end_date')]);
        }
        $creditos = $query->get();

        $data_box = [];
        foreach ($creditos as $condonacion) {
            $prev = isset($condonacion->prevDates) ? json_decode($condonacion->prevDates) : null;
            $post = isset($condonacion->postDates) ? json_decode($condonacion->postDates) : null;

            $capital = $interes = $mora = $seguro_desgravamen = $gastos_cobranza = $gastos_judiciales = $otros_valores = $total_condonado = 0;
            if ($prev && $post) {
                $capital = floatval($prev->capital ?? 0) - floatval($post->capital ?? 0);
                $interes = floatval($prev->interest ?? 0) - floatval($post->interest ?? 0);
                $mora = floatval($prev->mora ?? 0) - floatval($post->mora ?? 0);
                $seguro_desgravamen = floatval($prev->safe ?? 0) - floatval($post->safe ?? 0);
                $gastos_cobranza = floatval($prev->management_collection_expenses ?? 0) - floatval($post->management_collection_expenses ?? 0);
                $gastos_judiciales = floatval($prev->legal_expenses ?? 0) - floatval($post->legal_expenses ?? 0);
                $otros_valores = floatval($prev->other_values ?? 0) - floatval($post->other_values ?? 0);
                $total_condonado = $capital + $interes + $mora + $seguro_desgravamen + $gastos_cobranza + $gastos_judiciales + $otros_valores;
            }

            // Buscar datos del cliente y crédito
            $creditId = property_exists($condonacion, 'credito') ? $condonacion->credito : ($condonacion->id ?? null);
            $client = null;
            $credit = null;
            $business_name = '';
            if ($creditId) {
                $client = DB::table('clients')
                    ->join('client_credit', 'clients.id', '=', 'client_credit.client_id')
                    ->where('client_credit.credit_id', $creditId)
                    ->where('client_credit.type', 'TITULAR')
                    ->select('clients.ci', 'clients.name')
                    ->first();
                $credit = DB::table('credits')->where('id', $creditId)->first();
                if ($credit && isset($credit->business_id)) {
                    $business = DB::table('businesses')->where('id', $credit->business_id)->first();
                    $business_name = $business->name ?? '';
                }
            }
            $id_credito = ($business_name && $credit && isset($credit->sync_id)) ? ($business_name.'-'.$credit->sync_id) : ($creditId ?? '');

            $data_prev = [
                'CEDULA' => $client->ci ?? '',
                'NOMBRE' => $client->name ?? '',
                'ID CREDITO' => $id_credito,
                'FECHA' => $condonacion->fecha ?? '',
                'NRO. CONDONACIÓN' => $condonacion->id ?? '',
                'CAPITAL' => $this->rewriteValue($capital),
                'INTERES' => $this->rewriteValue($interes),
                'MORA' => $this->rewriteValue($mora),
                'SEGURO DESGRAVAMEN' => $this->rewriteValue($seguro_desgravamen),
                'GASTOS DE COBRANZA' => $this->rewriteValue($gastos_cobranza),
                'GASTOS JUDICIALES' => $this->rewriteValue($gastos_judiciales),
                'OTROS' => $this->rewriteValue($otros_valores),
                'TOTAL CONDONADO' => $this->rewriteValue($total_condonado),
            ];
            $data_box[] = $data_prev;
        }
        // Pie de página
        $data_box[] = [ 'CEDULA' => '', 'NOMBRE' => '' ];
        $data_box[] = [ 'CEDULA' => 'Generado por:', 'NOMBRE' => request('user') ];
        $data_box[] = [ 'CEDULA' => 'Hora y fecha:', 'NOMBRE' => date('Y/m/d H:i:s', time()-18000) ];
        return collect($data_box);
    }
}