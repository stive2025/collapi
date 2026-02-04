<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ListadoLlamadasSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    protected $campainId;
    protected $userName;

    public function __construct($campainId, $userName)
    {
        $this->campainId = $campainId;
        $this->userName = $userName;
    }

    public function title(): string
    {
        return 'Listado Llamadas';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function headings(): array
    {
        return [
            'ID llamada',
            'Crédito',
            'ID Cobranza',
            'Estado Llamada',
            'Efectividad de Llamada',
            'Duración (seg)',
            'Teléfono',
            'Fecha',
            'Agente'
        ];
    }

    public function collection()
    {
        $calls = DB::table('collection_calls as cc')
            ->join('credits as c', 'c.id', '=', 'cc.credit_id')
            ->leftJoin('users as u', 'u.id', '=', 'cc.created_by')
            ->where('cc.campain_id', $this->campainId)
            ->select(
                'cc.id',
                'c.sync_id',
                'cc.credit_id',
                'cc.state',
                'cc.efectivity',
                'cc.duration',
                'cc.phone_number',
                'cc.created_at',
                'u.name as agent_name'
            )
            ->orderBy('cc.created_at', 'asc')
            ->get();

        $dataBox = [];

        foreach ($calls as $call) {
            $dataBox[] = [
                $call->id,
                $call->sync_id,
                $call->credit_id,
                $call->state ?? '',
                $call->efectivity ?? '',
                $call->duration ?? 0,
                $call->phone_number ?? '',
                $call->created_at,
                $call->agent_name ?? ''
            ];
        }

        // Footer
        $dataBox[] = [''];
        $dataBox[] = ['Generado por:', $this->userName];
        $dataBox[] = ['Hora y fecha:', date('Y/m/d H:i:s')];

        return collect($dataBox);
    }
}
