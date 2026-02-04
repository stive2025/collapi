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
        // Obtener fechas de la campaña
        $campain = \App\Models\Campain::find($this->campainId);
        $startDate = $campain ? $campain->begin_time : null;
        $endDate = $campain ? $campain->end_time : null;

        $creditIds = DB::table('collection_credits')
            ->where('campain_id', $this->campainId)
            ->pluck('credit_id')
            ->unique()
            ->toArray();

        $query = DB::table('collection_calls as cc')
            ->join('credits as c', 'c.id', '=', 'cc.credit_id')
            ->leftJoin('users as u', 'u.id', '=', 'cc.created_by')
            ->whereIn('cc.credit_id', $creditIds);

        // Filtrar por fechas de la campaña
        if ($startDate && $endDate) {
            $query->whereBetween('cc.created_at', [$startDate, $endDate]);
        }

        $calls = $query->select(
                'cc.id',
                'c.sync_id',
                'cc.credit_id',
                'cc.state',
                'cc.duration',
                'cc.phone_number',
                'cc.created_at',
                'u.name as agent_name'
            )
            ->orderBy('cc.created_at', 'asc')
            ->get();

        $dataBox = [];

        foreach ($calls as $call) {
            // Determinar efectividad basada en el estado
            $efectividad = $this->getEfectividad($call->state);

            $dataBox[] = [
                $call->id,
                $call->sync_id,
                $call->credit_id,
                $call->state ?? '',
                $efectividad,
                $call->duration ?? 0,
                $call->phone_number ?? '',
                $call->created_at,
                $call->agent_name ?? ''
            ];
        }

        return collect($dataBox);
    }

    private function getEfectividad($state)
    {
        $efectivos = ['CONTACTADO'];
        if (in_array(strtoupper($state ?? ''), $efectivos)) {
            return 'EFECTIVA';
        }
        return 'NO EFECTIVA';
    }
}
