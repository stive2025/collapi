<?php

namespace App\Exports\Sheets;

use App\Services\UtilService;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ListadoGestionesSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    protected $campainId;
    protected $userName;
    protected $utilService;
    protected $campainName;

    public function __construct($campainId, $userName)
    {
        $this->campainId = $campainId;
        $this->userName = $userName;
        $this->utilService = new UtilService();

        $campain = \App\Models\Campain::find($campainId);
        $this->campainName = $campain ? $campain->name : 'N/A';
    }

    public function title(): string
    {
        return 'Listado Gestiones';
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
            'ID Gestión',
            'ID Cobranza',
            'Campaña',
            'Fecha de Gestión',
            'Crédito Número',
            'Estado Crédito',
            'Titular Cédula',
            'Titular Nombre',
            'Crédito Agencia',
            'Contacto Tipo',
            'Contacto Cédula',
            'Contacto Nombre',
            'Días de Mora (En la gestión)',
            'Rango (En la gestión)',
            'Total Pendiente (En la gestión)',
            'Agente Nombre',
            'Tipo de gestión',
            'Resultado de gestión',
            'Efectividad de gestión',
            'Estado Gestión',
            'Fecha de compromiso',
            'Observaciones',
            'Llamadas',
            'Cant. Llamadas asociadas',
            'Última Llamada - ID',
            'Última llamada - Fecha y hora',
            'Última llamada - Número contactado',
            'Última llamada - Resultado',
            'Última llamada - Duración completa (Seg)',
            'Última llamada - Duración completa (Min)'
        ];
    }

    public function collection()
    {
        $managements = DB::table('management as m')
            ->join('credits as c', 'c.id', '=', 'm.credit_id')
            ->leftJoin('users as u', 'u.id', '=', 'm.created_by')
            ->leftJoin('clients as cl', 'cl.id', '=', 'm.client_id')
            ->where('m.campain_id', $this->campainId)
            ->select(
                'm.id',
                'm.credit_id',
                'm.created_at',
                'c.sync_id',
                'c.collection_state',
                'c.agency',
                'm.days_past_due',
                'm.managed_amount',
                'u.name as agent_name',
                'm.state',
                'm.substate',
                'm.promise_date',
                'm.observation',
                'm.call_id',
                'm.call_collection',
                'cl.ci as contact_ci',
                'cl.name as contact_name'
            )
            ->orderBy('m.created_at', 'asc')
            ->get();

        // Precargar titulares
        $creditIds = $managements->pluck('credit_id')->unique()->toArray();
        $titulares = DB::table('clients as cl')
            ->join('client_credit as cc', 'cc.client_id', '=', 'cl.id')
            ->whereIn('cc.credit_id', $creditIds)
            ->where('cc.type', 'TITULAR')
            ->select('cc.credit_id', 'cl.ci', 'cl.name')
            ->get()
            ->keyBy('credit_id');

        $dataBox = [];

        foreach ($managements as $management) {
            $titular = $titulares[$management->credit_id] ?? null;
            $rango = $this->utilService->setRange((int)($management->days_past_due ?? 0));

            // Determinar tipo de gestión
            $tipoGestion = 'Visita';
            $callIds = [];
            if (!empty($management->call_collection)) {
                $callIds = json_decode($management->call_collection, true) ?? [];
                if (count($callIds) > 0) {
                    $tipoGestion = 'Llamada';
                }
            } elseif ($management->call_id) {
                $callIds = [$management->call_id];
                $tipoGestion = 'Llamada';
            }

            // Obtener información de última llamada
            $ultimaLlamadaId = '';
            $ultimaLlamadaFecha = '';
            $ultimaLlamadaNumero = '';
            $ultimaLlamadaResultado = '';
            $ultimaLlamadaDuracionSeg = '';
            $ultimaLlamadaDuracionMin = '';
            $cantLlamadas = count($callIds);

            if (!empty($callIds)) {
                $ultimaLlamada = DB::table('collection_calls')
                    ->whereIn('id', $callIds)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($ultimaLlamada) {
                    $ultimaLlamadaId = $ultimaLlamada->id;
                    $ultimaLlamadaFecha = $ultimaLlamada->created_at;
                    $ultimaLlamadaNumero = $ultimaLlamada->phone_number ?? '';
                    $ultimaLlamadaResultado = $ultimaLlamada->state ?? '';
                    $ultimaLlamadaDuracionSeg = $ultimaLlamada->duration ?? 0;
                    $ultimaLlamadaDuracionMin = round(($ultimaLlamada->duration ?? 0) / 60, 2);
                }
            }

            // Determinar efectividad
            $efectividad = $this->getEfectividad($management->substate);

            $dataBox[] = [
                $management->id,
                $management->credit_id,
                $this->campainName,
                $management->created_at,
                $management->sync_id,
                $management->collection_state ?? '',
                $titular->ci ?? '',
                $titular->name ?? '',
                $management->agency ?? '',
                '', // Contacto Tipo - depende de la relación
                $management->contact_ci ?? '',
                $management->contact_name ?? '',
                $management->days_past_due ?? 0,
                $rango,
                $this->formatNumber($management->managed_amount),
                $management->agent_name ?? '',
                $tipoGestion,
                $management->state ?? '',
                $efectividad,
                $management->substate ?? '',
                $management->promise_date ?? '',
                $management->observation ?? '',
                implode(',', $callIds),
                $cantLlamadas,
                $ultimaLlamadaId,
                $ultimaLlamadaFecha,
                $ultimaLlamadaNumero,
                $ultimaLlamadaResultado,
                $ultimaLlamadaDuracionSeg,
                $ultimaLlamadaDuracionMin
            ];
        }

        // Footer
        $dataBox[] = [''];
        $dataBox[] = ['Generado por:', $this->userName];
        $dataBox[] = ['Hora y fecha:', date('Y/m/d H:i:s')];

        return collect($dataBox);
    }

    private function getEfectividad($substate)
    {
        $efectivos = ['PAGO', 'COMPROMISO DE PAGO', 'ACUERDO DE PAGO'];
        if (in_array(strtoupper($substate ?? ''), $efectivos)) {
            return 'EFECTIVO';
        }
        return 'NO EFECTIVO';
    }

    private function formatNumber($value)
    {
        if ($value === '' || $value === null) {
            return 0;
        }
        return round(floatval($value), 2);
    }
}
