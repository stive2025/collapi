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

class ListadoAsignacionSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    protected $campainId;
    protected $userName;
    protected $utilService;

    public function __construct($campainId, $userName)
    {
        $this->campainId = $campainId;
        $this->userName = $userName;
        $this->utilService = new UtilService();
    }

    public function title(): string
    {
        return 'Listado Asignacion';
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
            'Crédito/Contrato',
            'Monto',
            'Saldo capital',
            'Interes',
            'Mora',
            'Seguro Desgravamen',
            'Gastos Cobranza',
            'Gastos Judiciales',
            'Otros',
            'Agente',
            'Dias_mora',
            'Agencia',
            'Rango'
        ];
    }

    public function collection()
    {
        // Obtener el primer registro de collection_credits por credit_id
        $subquery = DB::table('collection_credits')
            ->select('credit_id', DB::raw('MIN(id) as min_id'))
            ->where('campain_id', $this->campainId)
            ->groupBy('credit_id');

        $credits = DB::table('collection_credits as cc')
            ->joinSub($subquery, 'first_cc', function ($join) {
                $join->on('cc.id', '=', 'first_cc.min_id');
            })
            ->join('credits as c', 'c.id', '=', 'cc.credit_id')
            ->leftJoin('users as u', 'u.id', '=', 'cc.user_id')
            ->where('cc.campain_id', $this->campainId)
            ->select(
                'c.sync_id',
                'cc.total_amount',
                'cc.capital',
                'cc.interest',
                'cc.mora',
                'cc.safe',
                'cc.management_collection_expenses',
                'cc.legal_expenses',
                'cc.other_values',
                'u.name as agent_name',
                'cc.days_past_due',
                'c.agency'
            )
            ->get();

        $dataBox = [];

        foreach ($credits as $credit) {
            $rango = $this->utilService->setRange((int)($credit->days_past_due ?? 0));

            $dataBox[] = [
                $credit->sync_id,
                $this->formatNumber($credit->total_amount),
                $this->formatNumber($credit->capital),
                $this->formatNumber($credit->interest),
                $this->formatNumber($credit->mora),
                $this->formatNumber($credit->safe),
                $this->formatNumber($credit->management_collection_expenses),
                $this->formatNumber($credit->legal_expenses),
                $this->formatNumber($credit->other_values),
                $credit->agent_name ?? '',
                $credit->days_past_due ?? 0,
                $credit->agency ?? '',
                $rango
            ];
        }

        // Footer
        $dataBox[] = [''];
        $dataBox[] = ['Generado por:', $this->userName];
        $dataBox[] = ['Hora y fecha:', date('Y/m/d H:i:s')];

        return collect($dataBox);
    }

    private function formatNumber($value)
    {
        if ($value === '' || $value === null) {
            return 0;
        }
        return round(floatval($value), 2);
    }
}
