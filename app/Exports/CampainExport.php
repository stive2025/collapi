<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CampainExport implements FromCollection, WithHeadings, WithColumnFormatting, ShouldAutoSize, WithColumnWidths
{
    protected $campainId;

    public function __construct($campainId)
    {
        $this->campainId = $campainId;
    }

    public function columnFormats(): array
    {
        return [
            'I' => NumberFormat::FORMAT_DATE_DATETIME, // fecha_oferta_pago
            'J' => NumberFormat::FORMAT_DATE_DATETIME, // fecha_regestion_oferta
            'L' => NumberFormat::FORMAT_DATE_DATETIME, // fecha_ultima_gestion
            'M' => NumberFormat::FORMAT_DATE_DATETIME, // promise
            'T' => NumberFormat::FORMAT_DATE_DDMMYYYY, // payment_date
            'U' => NumberFormat::FORMAT_DATE_DDMMYYYY, // due_date
            'W' => NumberFormat::FORMAT_DATE_DATETIME, // updated_at
        ];
    }

    public function columnWidths(): array
    {
        return [
            'L' => 10,
            'M' => 10,
            'N' => 10,
            'Q' => 10,
            'R' => 20,
            'S' => 10
        ];
    }

    public function headings(): array
    {
        return [
            'sync_id',
            'agente',
            'collection_state',
            'Rango',
            'dias_vencidos',
            'tray',
            'cantidad_gestiones_efectivas',
            'cantidad_gestiones_no_efectivas',
            'fecha_oferta_pago',
            'fecha_ult_regestion',
            'status_management',
            'fecha_ultima_gestion',
            'promise',
            'name',
            'Agencia',
            'ci',
            'total_amount',
            'pending_fees',
            'total_fees',
            'payment_date',
            'due_date',
            'frequency',
            'fecha_proceso',
            'oferta',
            'compromiso',
            'notificacion',
            'created_at',
            'id',
        ];
    }

    public function collection()
    {
        $campainId = $this->campainId;

        $campain = DB::table('campains')->where('id', $campainId)->first();

        if (!$campain) {
            return collect([]);
        }

        $businessId = $campain->business_id;

        $business = DB::table('businesses')->where('id', $businessId)->first();
        $businessName = $business ? $business->name : '';

        $collectionCampain = DB::table('credits as c')
            ->select(
                DB::raw('CONCAT("' . $businessName . '-", c.sync_id) as sync_id'),
                'u.name as Agente',
                'c.collection_state',
                DB::raw("
                    CASE
                        WHEN c.days_past_due = 1 THEN 'B) 1'
                        WHEN c.days_past_due BETWEEN 2 AND 5 THEN 'C) 2-5'
                        WHEN c.days_past_due BETWEEN 6 AND 15 THEN 'D) 6-15'
                        WHEN c.days_past_due BETWEEN 16 AND 30 THEN 'E) 16-30'
                        WHEN c.days_past_due BETWEEN 31 AND 60 THEN 'F) 31-60'
                        WHEN c.days_past_due BETWEEN 61 AND 90 THEN 'G) 61-90'
                        WHEN c.days_past_due BETWEEN 91 AND 120 THEN 'H) 91-120'
                        WHEN c.days_past_due BETWEEN 121 AND 180 THEN 'I) 121-180'
                        WHEN c.days_past_due BETWEEN 181 AND 360 THEN 'J) 181-360'
                        WHEN c.days_past_due BETWEEN 361 AND 719 THEN 'K) 361-719'
                        WHEN c.days_past_due BETWEEN 720 AND 1080 THEN 'L) 720-1080'
                        WHEN c.days_past_due >= 1081 THEN 'M) Más de 1081'
                        ELSE 'A) Preventiva'
                    END as Rango
                "),
                'c.days_past_due as dias_vencidos',
                'c.management_tray as tray',
                'mg.cantidad_gestiones_efectivas',
                'mg.cantidad_gestiones_no_efectivas',
                DB::raw("DATE_FORMAT(mg.fecha_oferta_pago, '%d/%m/%Y %H:%i:%s') as fecha_oferta_pago"),
                DB::raw("DATE_FORMAT(mg.fecha_regestion_oferta, '%d/%m/%Y %H:%i:%s') as fecha_regestion_oferta"),
                'mg.status_management',
                DB::raw("DATE_FORMAT(mg.fecha_ultima_gestion, '%d/%m/%Y %H:%i:%s') as fecha_ultima_gestion"),
                DB::raw("DATE_FORMAT(c.management_promise, '%d/%m/%Y %H:%i:%s') as promise"),
                'cl.name as Cliente_nombre',
                'c.agency as Agencia',
                'cl.ci as Cliente_cedula',
                'c.total_amount',
                'c.pending_fees',
                'c.total_fees',
                DB::raw("DATE_FORMAT(c.payment_date, '%d/%m/%Y') as payment_date"),
                DB::raw("DATE_FORMAT(c.due_date, '%d/%m/%Y') as due_date"),
                'c.frequency',
                DB::raw("DATE_FORMAT(c.last_sync_date, '%d/%m/%Y') as fecha_proceso"),
                'c.date_offer as oferta',
                'c.date_promise as compromiso',
                'c.date_notification as notificacion',
                DB::raw("DATE_FORMAT(c.created_at, '%d/%m/%Y %H:%i:%s') as created_at"),
                'c.id as id'
            )
            ->where('c.business_id', $businessId)
            ->where('c.sync_status', 'ACTIVE')
            ->join('client_credit as cc', 'cc.credit_id', '=', 'c.id')
            ->join('clients as cl', function($join) {
                $join->on('cl.id', '=', 'cc.client_id')
                    ->where('cc.type', '=', 'TITULAR');
            })
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->leftJoin(DB::raw("(
                SELECT
                    credit_id,
                    SUM(CASE WHEN substate IN ('OFERTA DE PAGO', 'REGESTION DE OFERTA') THEN 1 ELSE 0 END) as cantidad_gestiones_efectivas,
                    SUM(CASE WHEN substate NOT IN ('OFERTA DE PAGO', 'REGESTION DE OFERTA') THEN 1 ELSE 0 END) as cantidad_gestiones_no_efectivas,
                    MAX(CASE WHEN substate = 'OFERTA DE PAGO' THEN created_at END) as fecha_oferta_pago,
                    MAX(CASE WHEN substate = 'REGESTION DE OFERTA' THEN created_at END) as fecha_regestion_oferta,
                    MAX(created_at) as fecha_ultima_gestion,
                    SUBSTRING_INDEX(GROUP_CONCAT(substate ORDER BY id DESC), ',', 1) as status_management
                FROM management
                WHERE campain_id = {$campainId}
                GROUP BY credit_id
            ) as mg"), function($join) {
                $join->on('mg.credit_id', '=', 'c.id');
            })
            ->get();

        return collect($collectionCampain);
    }
}