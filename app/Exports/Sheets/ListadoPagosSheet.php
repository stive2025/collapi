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

class ListadoPagosSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    protected $campainId;
    protected $userName;
    protected $utilService;
    protected $campainName;
    protected $seenReferences = [];
    protected $seenCredits = [];
    protected $managementsCache = [];

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
        return 'Listado Pagos';
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
            'ID Pago',
            'ID Cobranza',
            'Campaña',
            'ID Comprobante',
            'Fecha de pago',
            'Contrato/Crédito',
            'Cont. Comprobantes',
            'Cont. Créditos',
            'Titular Cédula',
            'Titular Nombre',
            'Crédito Agencia',
            'Agente asignado',
            'Total Pagado',
            'Pago (Capital)',
            'Pago (Int.)',
            'Pago (Int. Mora)',
            'Pago (Otros)',
            'Pago (Capital + Intereses)',
            'Gestión Automática (G.A.)',
            'ID Gestión (G.A.)',
            'DISTANCIA ENTRE GA Y PAGO',
            'Fecha de gestión relacionada (G.A.)',
            'Pago con gestión posterior (G.A.)',
            'Agente Nombre (G.A.)',
            'Estado Gestión (G.A.)',
            'Fecha de compromiso (En gestión (G.A.))',
            'Observación (G.A.)',
            'Días de Mora (G.A.)',
            'Rango (G.A.)',
            'Total Pendiente (G.A.)',
            'Gestión Previa (G.P.)',
            'ID Gestión (G.P.)',
            'Fecha de gestión relacionada (G.P.)',
            'DISTANCIA ENTRE GP Y PAGO',
            'Agente Nombre (G.P.)',
            'Estado Gestión (G.P.)',
            'Fecha de compromiso (En gestión (G.P.))',
            'Observación (G.P.)',
            'Días de Mora (G.P.)',
            'Rango (G.P.)',
            'Total Pendiente (G.P.)',
            'Días de mora (En Pago - DEFINITIVO)',
            'Rango (En Pago - DEFINITIVO)',
            'VALIDEZ DEL PAGO FACES'
        ];
    }

    public function collection()
    {
        $payments = $this->getPaymentsQuery()->get();

        // Precargar managements
        $managementAutoIds = $payments->pluck('management_auto')->filter()->unique()->toArray();
        $managementPrevIds = $payments->pluck('management_prev')->filter()->unique()->toArray();
        $allManagementIds = array_unique(array_merge($managementAutoIds, $managementPrevIds));

        if (!empty($allManagementIds)) {
            $managements = \App\Models\Management::with('creator')
                ->whereIn('id', $allManagementIds)
                ->get()
                ->keyBy('id');
            $this->managementsCache = $managements;
        }

        $dataBox = [];

        foreach ($payments as $payment) {
            $dataBox[] = $this->buildPaymentRow($payment);
        }

        // Footer
        $dataBox[] = [''];
        $dataBox[] = ['Generado por:', $this->userName];
        $dataBox[] = ['Hora y fecha:', date('Y/m/d H:i:s')];

        return collect($dataBox);
    }

    private function getPaymentsQuery()
    {
        return DB::table('collection_payments as cp')
            ->where('cp.campain_id', $this->campainId)
            ->select(
                'cp.*',
                DB::raw('(SELECT c.sync_id FROM credits c WHERE c.id = cp.credit_id) as sync_id'),
                DB::raw('(SELECT c.agency FROM credits c WHERE c.id = cp.credit_id) as agency'),
                DB::raw('(SELECT cl.name FROM clients cl
                    INNER JOIN client_credit cc ON cc.client_id = cl.id
                    WHERE cc.credit_id = cp.credit_id AND cc.type = "TITULAR"
                    LIMIT 1) as client_name'),
                DB::raw('(SELECT cl.ci FROM clients cl
                    INNER JOIN client_credit cc ON cc.client_id = cl.id
                    WHERE cc.credit_id = cp.credit_id AND cc.type = "TITULAR"
                    LIMIT 1) as client_ci'),
                DB::raw('(SELECT u.name FROM users u
                    INNER JOIN collection_credits ccr ON ccr.user_id = u.id
                    WHERE ccr.credit_id = cp.credit_id AND ccr.campain_id = cp.campain_id
                    ORDER BY ccr.id DESC LIMIT 1) as agent_name')
            )
            ->orderBy('cp.payment_date', 'asc')
            ->orderBy('cp.id', 'asc');
    }

    private function buildPaymentRow($payment)
    {
        // Contador de comprobantes
        $contComprobantes = 0;
        if (!isset($this->seenReferences[$payment->payment_reference])) {
            $this->seenReferences[$payment->payment_reference] = true;
            $contComprobantes = 1;
        }

        // Contador de créditos
        $contCreditos = 0;
        if (!isset($this->seenCredits[$payment->credit_id])) {
            $this->seenCredits[$payment->credit_id] = true;
            $contCreditos = 1;
        }

        $pagoCapitalIntereses = floatval($payment->payment_value) - floatval($payment->other_values);

        // Management Auto
        $gaLabel = 'Sin gestión automática';
        $gaId = '';
        $gaDistancia = '';
        $gaFecha = '';
        $gaPostManagement = $payment->post_management ?? '';
        $gaAgente = '';
        $gaEstado = '';
        $gaFechaCompromiso = '';
        $gaObservacion = '';
        $gaDiasMora = '';
        $gaRango = '';
        $gaTotalPendiente = '';

        if ($payment->management_auto && isset($this->managementsCache[$payment->management_auto])) {
            $managementAuto = $this->managementsCache[$payment->management_auto];
            $gaLabel = 'Con gestión automática';
            $gaId = $managementAuto->id;
            $gaFecha = $managementAuto->created_at ? \Carbon\Carbon::parse($managementAuto->created_at)->format('Y-m-d H:i:s') : '';

            if ($managementAuto->created_at && $payment->payment_date) {
                $fechaGA = \Carbon\Carbon::parse($managementAuto->created_at)->startOfDay();
                $fechaPago = \Carbon\Carbon::parse($payment->payment_date)->startOfDay();
                $gaDistancia = (int) $fechaGA->diffInDays($fechaPago);
            }

            $gaAgente = $managementAuto->creator ? $managementAuto->creator->name : '';
            $gaEstado = $managementAuto->substate ?? '';
            $gaFechaCompromiso = $managementAuto->promise_date ?? '';
            $gaObservacion = $managementAuto->observation ?? '';
            $gaDiasMora = $managementAuto->days_past_due ?? '';
            $gaRango = ($gaDiasMora !== '' && $gaDiasMora !== null) ? $this->utilService->setRange((int)$gaDiasMora) : '';
            $gaTotalPendiente = $managementAuto->managed_amount ?? '';
        }

        // Management Prev
        $gpLabel = 'Sin gestión previa';
        $gpId = '';
        $gpFecha = '';
        $gpDistancia = '';
        $gpAgente = '';
        $gpEstado = '';
        $gpFechaCompromiso = '';
        $gpObservacion = '';
        $gpDiasMora = '';
        $gpRango = '';
        $gpTotalPendiente = '';

        if ($payment->management_prev && isset($this->managementsCache[$payment->management_prev])) {
            $managementPrev = $this->managementsCache[$payment->management_prev];
            $gpLabel = 'Con gestión previa';
            $gpId = $managementPrev->id;
            $gpFecha = $managementPrev->created_at ? \Carbon\Carbon::parse($managementPrev->created_at)->format('Y-m-d H:i:s') : '';

            if ($managementPrev->created_at && $payment->payment_date) {
                $fechaGP = \Carbon\Carbon::parse($managementPrev->created_at)->startOfDay();
                $fechaPago = \Carbon\Carbon::parse($payment->payment_date)->startOfDay();
                $gpDistancia = (int) $fechaGP->diffInDays($fechaPago);
            }

            $gpAgente = $managementPrev->creator ? $managementPrev->creator->name : '';
            $gpEstado = $managementPrev->substate ?? '';
            $gpFechaCompromiso = $managementPrev->promise_date ?? '';
            $gpObservacion = $managementPrev->observation ?? '';
            $gpDiasMora = $managementPrev->days_past_due ?? '';
            $gpRango = ($gpDiasMora !== '' && $gpDiasMora !== null) ? $this->utilService->setRange((int)$gpDiasMora) : '';
            $gpTotalPendiente = $managementPrev->managed_amount ?? '';
        }

        $diasMoraDefinitivo = $payment->days_past_due_auto ?? '';
        $rangoDefinitivo = $diasMoraDefinitivo !== '' && $diasMoraDefinitivo !== null
            ? $this->utilService->setRange((int)$diasMoraDefinitivo)
            : '';

        $validezPago = $payment->with_management === 'SI' ? 'PAGO VÁLIDO' : 'PAGO INVÁLIDO';

        return [
            $payment->id,
            $payment->credit_id,
            $this->campainName,
            $payment->payment_reference,
            $payment->payment_date,
            $payment->sync_id,
            $contComprobantes,
            $contCreditos,
            $payment->client_ci,
            $payment->client_name,
            $payment->agency,
            $payment->agent_name,
            $this->formatNumber($payment->payment_value),
            $this->formatNumber($payment->capital),
            $this->formatNumber($payment->interest),
            $this->formatNumber($payment->mora),
            $this->formatNumber($payment->other_values),
            $this->formatNumber($pagoCapitalIntereses),
            $gaLabel,
            $gaId,
            $gaDistancia,
            $gaFecha,
            $gaPostManagement,
            $gaAgente,
            $gaEstado,
            $gaFechaCompromiso,
            $gaObservacion,
            $gaDiasMora,
            $gaRango,
            $this->formatNumber($gaTotalPendiente),
            $gpLabel,
            $gpId,
            $gpFecha,
            $gpDistancia,
            $gpAgente,
            $gpEstado,
            $gpFechaCompromiso,
            $gpObservacion,
            $gpDiasMora,
            $gpRango,
            $this->formatNumber($gpTotalPendiente),
            $diasMoraDefinitivo,
            $rangoDefinitivo,
            $validezPago
        ];
    }

    private function formatNumber($value)
    {
        if ($value === '' || $value === null) {
            return '';
        }
        return round(floatval($value), 2);
    }
}
