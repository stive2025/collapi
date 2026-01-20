<?php

namespace App\Exports;

use App\Services\UtilService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
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

class PaymentsConsolidatedExport implements FromCollection, WithHeadings, WithCustomStartCell, WithEvents, WithDrawings, ShouldAutoSize, WithStyles
{
    protected $campainId;
    protected $campainName;
    protected $userName;
    protected $utilService;
    protected $seenReferences = [];
    protected $seenCredits = [];
    protected $managementsCache = [];

    public function __construct($campainId, $userName)
    {
        $this->campainId = $campainId;
        $this->userName = $userName;
        $this->utilService = new UtilService();

        // Obtener nombre de campaña
        $campain = \App\Models\Campain::find($campainId);
        $this->campainName = $campain ? $campain->name : 'N/A';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A6:AV6')->getFont()->setBold(true);
        $sheet->getStyle('A6:AV6')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('A6:AV6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A6:AV6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('4472C4');
        $sheet->getStyle('A6:AV6')->getFont()->getColor()->setARGB('FFFFFF');
        $sheet->getStyle('A6:AV6')->getAlignment()->setWrapText(true);

        return [];
    }

    public function drawings()
    {
        $logoPath = public_path('logo.png');

        if (!file_exists($logoPath)) {
            return [];
        }

        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');
        $drawing->setPath($logoPath);
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');

        return $drawing;
    }

    public function startCell(): string
    {
        return 'A3';
    }

    public function headings(): array
    {
        return [
            ['REPORTE DE PAGOS CONSOLIDADO'],
            ['Campaña: ' . $this->campainName],
            [
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
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $position_last = count($this->headings()[2]);
                $column = Coordinate::stringFromColumnIndex($position_last);

                $cells = "A3:{$column}3";
                $event->sheet->mergeCells($cells);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setSize(14);

                $cells = "A4:{$column}4";
                $event->sheet->mergeCells($cells);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);
            }
        ];
    }

    public function collection()
    {
        try {
            Log::info('PaymentsConsolidatedExport: Iniciando exportación', ['campain_id' => $this->campainId]);

            $payments = $this->getPaymentsQuery()->get();

            Log::info('PaymentsConsolidatedExport: Pagos obtenidos', ['total' => $payments->count()]);

            // Precargar todos los management_auto y management_prev de una vez
            $managementAutoIds = $payments->pluck('management_auto')->filter()->unique()->toArray();
            $managementPrevIds = $payments->pluck('management_prev')->filter()->unique()->toArray();
            $allManagementIds = array_unique(array_merge($managementAutoIds, $managementPrevIds));

            $this->managementsCache = [];
            if (!empty($allManagementIds)) {
                $managements = \App\Models\Management::with('creator')
                    ->whereIn('id', $allManagementIds)
                    ->get()
                    ->keyBy('id');
                $this->managementsCache = $managements;
            }

            Log::info('PaymentsConsolidatedExport: Managements precargados', ['total' => count($this->managementsCache)]);

            $dataBox = [];

            foreach ($payments as $index => $payment) {
                try {
                    $dataBox[] = $this->buildPaymentRow($payment);

                    if ($index % 100 === 0) {
                        Log::info('PaymentsConsolidatedExport: Procesando fila', ['index' => $index]);
                    }
                } catch (\Exception $e) {
                    Log::error('PaymentsConsolidatedExport: Error en fila ' . $index, [
                        'payment_id' => $payment->id ?? 'N/A',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Agregar footer
            $dataBox[] = [""];
            $dataBox[] = ["Generado por:", $this->userName];
            $dataBox[] = ["Hora y fecha:", date('Y/m/d H:i:s')];

            Log::info('PaymentsConsolidatedExport: Exportación completada', ['filas' => count($dataBox)]);

            return collect($dataBox);
        } catch (\Exception $e) {
            Log::error('PaymentsConsolidatedExport: Error general', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
        // Contador de comprobantes (1 solo en primera aparición)
        $contComprobantes = 0;
        if (!isset($this->seenReferences[$payment->payment_reference])) {
            $this->seenReferences[$payment->payment_reference] = true;
            $contComprobantes = 1;
        }

        // Contador de créditos (1 solo en primera aparición)
        $contCreditos = 0;
        if (!isset($this->seenCredits[$payment->credit_id])) {
            $this->seenCredits[$payment->credit_id] = true;
            $contCreditos = 1;
        }

        // Calcular Pago (Capital + Intereses) = payment_value - other_values
        $pagoCapitalIntereses = floatval($payment->payment_value) - floatval($payment->other_values);

        // Obtener datos de management_auto desde cache
        $managementAuto = null;
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

            // Calcular distancia entre GA y pago
            if ($managementAuto->created_at && $payment->payment_date) {
                $fechaGA = \Carbon\Carbon::parse($managementAuto->created_at);
                $fechaPago = \Carbon\Carbon::parse($payment->payment_date);
                $gaDistancia = $fechaGA->diffInDays($fechaPago);
            }

            $gaAgente = $managementAuto->creator ? $managementAuto->creator->name : '';
            $gaEstado = $managementAuto->substate ?? '';
            $gaFechaCompromiso = $managementAuto->promise_date ?? '';
            $gaObservacion = $managementAuto->observation ?? '';
            $gaDiasMora = $managementAuto->days_past_due ?? '';
            $gaRango = ($gaDiasMora !== '' && $gaDiasMora !== null) ? $this->utilService->setRange((int)$gaDiasMora) : '';
            $gaTotalPendiente = $managementAuto->managed_amount ?? '';
        }

        // Obtener datos de management_prev desde cache
        $managementPrev = null;
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

            // Calcular distancia entre GP y pago
            if ($managementPrev->created_at && $payment->payment_date) {
                $fechaGP = \Carbon\Carbon::parse($managementPrev->created_at);
                $fechaPago = \Carbon\Carbon::parse($payment->payment_date);
                $gpDistancia = $fechaGP->diffInDays($fechaPago);
            }

            $gpAgente = $managementPrev->creator ? $managementPrev->creator->name : '';
            $gpEstado = $managementPrev->substate ?? '';
            $gpFechaCompromiso = $managementPrev->promise_date ?? '';
            $gpObservacion = $managementPrev->observation ?? '';
            $gpDiasMora = $managementPrev->days_past_due ?? '';
            $gpRango = ($gpDiasMora !== '' && $gpDiasMora !== null) ? $this->utilService->setRange((int)$gpDiasMora) : '';
            $gpTotalPendiente = $managementPrev->managed_amount ?? '';
        }

        // Días de mora definitivo y rango
        $diasMoraDefinitivo = $payment->days_past_due_auto ?? '';
        $rangoDefinitivo = $diasMoraDefinitivo !== '' && $diasMoraDefinitivo !== null
            ? $this->utilService->setRange((int)$diasMoraDefinitivo)
            : '';

        // Validez del pago FACES
        $validezPago = $payment->with_management === 'SI' ? 'PAGO VÁLIDO' : 'PAGO INVÁLIDO';

        return [
            $payment->id,                                           // ID Pago
            $payment->credit_id,                                    // ID Cobranza
            $this->campainName,                                     // Campaña
            $payment->payment_reference,                            // ID Comprobante
            $payment->payment_date,                                 // Fecha de pago
            $payment->sync_id,                                      // Contrato/Crédito
            $contComprobantes,                                      // Cont. Comprobantes
            $contCreditos,                                          // Cont. Créditos
            $payment->client_ci,                                    // Titular Cédula
            $payment->client_name,                                  // Titular Nombre
            $payment->agency,                                       // Crédito Agencia
            $payment->agent_name,                                   // Agente asignado
            $this->formatNumber($payment->payment_value),           // Total Pagado
            $this->formatNumber($payment->capital),                 // Pago (Capital)
            $this->formatNumber($payment->interest),                // Pago (Int.)
            $this->formatNumber($payment->mora),                    // Pago (Int. Mora)
            $this->formatNumber($payment->other_values),            // Pago (Otros)
            $this->formatNumber($pagoCapitalIntereses),             // Pago (Capital + Intereses)
            $gaLabel,                                               // Gestión Automática (G.A.)
            $gaId,                                                  // ID Gestión (G.A.)
            $gaDistancia,                                           // DISTANCIA ENTRE GA Y PAGO
            $gaFecha,                                               // Fecha de gestión relacionada (G.A.)
            $gaPostManagement,                                      // Pago con gestión posterior (G.A.)
            $gaAgente,                                              // Agente Nombre (G.A.)
            $gaEstado,                                              // Estado Gestión (G.A.)
            $gaFechaCompromiso,                                     // Fecha de compromiso (En gestión (G.A.))
            $gaObservacion,                                         // Observación (G.A.)
            $gaDiasMora,                                            // Días de Mora (G.A.)
            $gaRango,                                               // Rango (G.A.)
            $this->formatNumber($gaTotalPendiente),                 // Total Pendiente (G.A.)
            $gpLabel,                                               // Gestión Previa (G.P.)
            $gpId,                                                  // ID Gestión (G.P.)
            $gpFecha,                                               // Fecha de gestión relacionada (G.P.)
            $gpDistancia,                                           // DISTANCIA ENTRE GP Y PAGO
            $gpAgente,                                              // Agente Nombre (G.P.)
            $gpEstado,                                              // Estado Gestión (G.P.)
            $gpFechaCompromiso,                                     // Fecha de compromiso (En gestión (G.P.))
            $gpObservacion,                                         // Observación (G.P.)
            $gpDiasMora,                                            // Días de Mora (G.P.)
            $gpRango,                                               // Rango (G.P.)
            $this->formatNumber($gpTotalPendiente),                 // Total Pendiente (G.P.)
            $diasMoraDefinitivo,                                    // Días de mora (En Pago - DEFINITIVO)
            $rangoDefinitivo,                                       // Rango (En Pago - DEFINITIVO)
            $validezPago                                            // VALIDEZ DEL PAGO FACES
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
