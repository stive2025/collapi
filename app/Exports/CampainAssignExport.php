<?php

namespace App\Exports;

use App\Models\Credit;
use App\Services\CatalogService;
use App\Services\UtilService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class CampainAssignExport implements FromCollection, WithHeadings, WithEvents, WithDrawings, WithStyles
{
    protected $businessId;
    protected $businessName;
    protected $userName;
    protected $catalogService;
    protected $utilService;
    protected $campaigns;

    public function __construct($businessId, $businessName, $userName)
    {
        $this->businessId = $businessId;
        $this->businessName = $businessName;
        $this->userName = $userName;
        $this->catalogService = new CatalogService();
        $this->utilService = new UtilService();
        $this->campaigns = $this->utilService->getLastThreeMonthsCampaigns($businessId);
    }

    public function headings(): array
    {
        return [
            ['ASIGNACIÓN DE CAMPAÑA'],
            ['N/A: No Asignado       N/P: No Pago      N/D:No Definido     N/G: No Gestionado'],
            ['Cartera: ' . $this->businessName],
            [
                '',
                '',
                '',
                '',
                '',
                '',
                'Información de agentes',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'Información de pagos',
                '',
                '',
                '',
                '',
                '',
                ''
            ],
            [
                "CREDITO",
                "ESTADO SINC.",
                "CÉDULA CONTACTO",
                "NOMBRE CONTACTO",
                "CRÉDITO TIPO",
                "AGENCIA",
                $this->campaigns[0]['name'] ?? 'MES 1',
                $this->campaigns[1]['name'] ?? 'MES 2',
                $this->campaigns[2]['name'] ?? 'MES 3',
                "ESTADO CARTERA",
                "DIAS DE MORA",
                "RANGO",
                "TOTAL PENDIENTE",
                "FECHA ÚLTIMO PAGO/ABONO",
                'P. ' . ($this->campaigns[0]['name'] ?? 'MES 1'),
                'P. ' . ($this->campaigns[1]['name'] ?? 'MES 2'),
                'P. ' . ($this->campaigns[2]['name'] ?? 'MES 3'),
                "FECHA ÚLTIMA GESTIÓN",
                "ESTADO ÚLTIMA GESTIÓN",
                "FECHA ÚLTIMO COMPROMISO",
                "OBSERVACIÓN ÚLTIMA GESTIÓN"
            ]
        ];
    }

    public function collection()
    {
        $credits = Credit::select([
            'credits.id',
            'credits.sync_id',
            'credits.sync_status',
            'credits.total_amount',
            'credits.agency',
            'credits.collection_state',
            'credits.days_past_due'
        ])
        ->where('credits.business_id', $this->businessId)
        ->where('credits.sync_status', 'ACTIVE')
        ->get();

        $creditIds = $credits->pluck('id');

        // $clientsData = [];
        // if ($creditIds->isNotEmpty()) {
        //     $clientCredits = DB::table('client_credit')
        //         ->whereIn('credit_id', $creditIds)
        //         ->orderByRaw("CASE WHEN type = 'TITULAR' THEN 1 WHEN type = 'GARANTE' THEN 2 ELSE 3 END")
        //         ->get()
        //         ->groupBy('credit_id');

        //     $clientIds = $clientCredits->flatten()->pluck('client_id')->unique();
        //     $clients = DB::table('clients')
        //         ->whereIn('id', $clientIds)
        //         ->get()
        //         ->keyBy('id');

        //     foreach ($clientCredits as $creditId => $clientCreditList) {
        //         $titular = $clientCreditList->firstWhere('type', 'TITULAR');
        //         if ($titular) {
        //             $client = $clients->get($titular->client_id);
        //             $clientsData[$creditId] = [
        //                 'ci' => $client->ci ?? '',
        //                 'name' => $client->name ?? '',
        //                 'type' => 'TITULAR'
        //             ];
        //         }
        //     }
        // }

        $clientsData = [];

        if ($creditIds->isNotEmpty()) {

            // 1️⃣ Todas las relaciones cliente-crédito
            $clientCredits = DB::table('client_credit')
                ->whereIn('credit_id', $creditIds)
                ->get();

            // 2️⃣ Clientes involucrados
            $clientIds = $clientCredits->pluck('client_id')->unique();

            $clients = DB::table('clients')
                ->whereIn('id', $clientIds)
                ->get()
                ->keyBy('id');

            // 3️⃣ Titulares por crédito
            $titularesByCredit = $clientCredits
                ->where('type', 'TITULAR')
                ->keyBy('credit_id');

            // 4️⃣ Créditos agrupados por cliente
            $creditsByClient = $clientCredits->groupBy('client_id');

            foreach ($titularesByCredit as $sourceCreditId => $titularRelation) {

                $client = $clients->get($titularRelation->client_id);
                if (!$client) {
                    continue;
                }

                // Créditos donde este cliente participa
                $relatedCredits = $creditsByClient[$titularRelation->client_id];

                foreach ($relatedCredits as $relation) {
                    $clientsData[$relation->credit_id] = [
                        'ci'   => $client->ci ?? '',
                        'name' => $client->name ?? '',
                        'type' => $relation->type // TITULAR o GARANTE en ESE crédito
                    ];
                }
            }
        }
        $paymentsCache = [];
        $managementsCache = [];

        if ($creditIds->isNotEmpty()) {
            $lastManagementSubquery = DB::table('management')
                ->select('credit_id', DB::raw('MAX(id) as max_id'))
                ->whereIn('credit_id', $creditIds)
                ->groupBy('credit_id');

            $managementsData = DB::table('management as m')
                ->joinSub($lastManagementSubquery, 'lm', function($join) {
                    $join->on('m.id', '=', 'lm.max_id');
                })
                ->select('m.credit_id', 'm.created_at', 'm.substate', 'm.observation', 'm.promise_date')
                ->get()
                ->keyBy('credit_id');

            foreach ($managementsData as $creditId => $management) {
                $managementsCache[$creditId] = $management;
            }

            $lastPaymentSubquery = DB::table('collection_payments')
                ->select('credit_id', DB::raw('MAX(payment_date) as max_date'))
                ->whereIn('credit_id', $creditIds)
                ->groupBy('credit_id');

            $paymentsData = DB::table('collection_payments as cp')
                ->joinSub($lastPaymentSubquery, 'lp', function($join) {
                    $join->on('cp.credit_id', '=', 'lp.credit_id')
                        ->on('cp.payment_date', '=', 'lp.max_date');
                })
                ->select('cp.credit_id', 'cp.payment_date')
                ->get()
                ->keyBy('credit_id');

            foreach ($paymentsData as $creditId => $payment) {
                $paymentsCache[$creditId] = $payment;
            }
        }

        $agentsCache = [];
        $tmp = [];

        $now = Carbon::now()->startOfMonth();

        foreach ($this->campaigns as $index => $campaign) {
            if ($campaign['campaign_id'] && $creditIds->isNotEmpty()) {
                $collectionCredits = DB::table(DB::raw("
                        (
                            SELECT 
                                credit_id,
                                user_id,
                                campain_id,
                                date,
                                ROW_NUMBER() OVER (
                                    PARTITION BY 
                                        credit_id,
                                        campain_id,
                                        YEAR(date),
                                        MONTH(date)
                                    ORDER BY date DESC, id DESC
                                ) as rn
                            FROM collection_credits
                            WHERE credit_id IN (" . $creditIds->implode(',') . ")
                            AND campain_id = {$campaign['campaign_id']}
                        ) t
                    "))
                    ->where('rn', 1)
                    ->orderBy('date', 'desc')
                    ->get();

                $userIds = $collectionCredits->pluck('user_id')->unique()->filter();
                $users = DB::table('users')->whereIn('id', $userIds)->get()->keyBy('id');
                
                foreach ($collectionCredits as $cc) {
                    $creditId = $cc->credit_id;

                    $date = Carbon::parse($cc->date)->startOfMonth();

                    // ❌ excluir mes actual
                    if ($date->equalTo($now)) {
                        continue;
                    }

                    // inicializar array del crédito
                    if (!isset($agentsCache[$creditId])) {
                        $agentsCache[$creditId] = [];
                    }

                    // máximo 3 meses
                    if (count($agentsCache[$creditId]) >= 3) {
                        continue;
                    }

                    // obtener nombre del agente
                    $user = $users->get($cc->user_id);
                    $agentName = $user ? $user->name : 'EN ESPERA';

                    $agentsCache[$creditId][] = $agentName;
                }
            }
        }

        $paymentsPerMonthCache = [];
        foreach ($this->campaigns as $index => $campaign) {
            if ($creditIds->isNotEmpty()) {
                $startDate = "{$campaign['year']}-{$campaign['month']}-01";
                $endDate = date('Y-m-t', strtotime($startDate));

                $paymentsPerMonth = DB::table('collection_payments')
                    ->select('credit_id', DB::raw('SUM(payment_value) as total'))
                    ->whereIn('credit_id', $creditIds)
                    ->whereBetween('payment_date', [$startDate, $endDate])
                    ->groupBy('credit_id')
                    ->get()
                    ->keyBy('credit_id');

                foreach ($paymentsPerMonth as $creditId => $payment) {
                    $paymentsPerMonthCache[$creditId][$index] = $payment->total;
                }
            }
        }

        $dataBox = [];

        foreach ($credits as $credit) {
            $clientData = $clientsData[$credit->id] ?? null;

            if (!$clientData) {
                continue;
            }

            $range = $this->utilService->setRange($credit->days_past_due ?? 0);
            $state = $this->utilService->setState($credit->collection_state ?? '');

            $lastManagement = $managementsCache[$credit->id] ?? null;
            $lastManagementDate = $lastManagement ?
                \Carbon\Carbon::parse($lastManagement->created_at)->format('Y-m-d') : '';
            $lastManagementState = $lastManagement ? ($lastManagement->substate ?? '') : '';
            $lastManagementPromise = $lastManagement && $lastManagement->promise_date ?
                \Carbon\Carbon::parse($lastManagement->promise_date)->format('Y-m-d') : '';
            $lastManagementObservation = $lastManagement ? ($lastManagement->observation ?? '') : '';

            $lastPayment = $paymentsCache[$credit->id] ?? null;
            $lastPaymentDate = $lastPayment ?
                \Carbon\Carbon::parse($lastPayment->payment_date)->format('Y-m-d') : '';

            $dataBox[] = [
                $this->businessName . '-' . $credit->sync_id,
                $credit->sync_status ?? '',
                $clientData['ci'],
                $clientData['name'],
                $clientData['type'],
                $credit->agency ?? '',
                $agentsCache[$credit->id][2] ?? '',
                $agentsCache[$credit->id][1] ?? '',
                $agentsCache[$credit->id][0] ?? '',
                $state,
                $credit->days_past_due ?? 0,
                $range,
                number_format($credit->total_amount ?? 0, 2),
                $lastPaymentDate,
                number_format($paymentsPerMonthCache[$credit->id][0] ?? 0, 2),
                number_format($paymentsPerMonthCache[$credit->id][1] ?? 0, 2),
                number_format($paymentsPerMonthCache[$credit->id][2] ?? 0, 2),
                $lastManagementDate,
                $lastManagementState,
                $lastManagementPromise,
                $lastManagementObservation
            ];
        }

        return collect($dataBox);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getRowDimension(1)->setRowHeight(50);
                
                $positionLast = count($this->headings()[4]);
                $column = Coordinate::stringFromColumnIndex($positionLast);

                $cells = "A1:{$column}1";
                $event->sheet->mergeCells($cells);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setSize(16);
                $event->sheet->getDelegate()->getStyle($cells)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle($cells)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

                $cells = "A2:{$column}2";
                $event->sheet->mergeCells($cells);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);

                $cells = "A3:{$column}3";
                $event->sheet->mergeCells($cells);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);

                $cells = "G4:I4";
                $event->sheet->mergeCells($cells);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setSize(12);
                $event->sheet->getDelegate()->getStyle($cells)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
                $event->sheet->getDelegate()->getStyle($cells)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('C6EFCE');
                $event->sheet->getDelegate()->getStyle($cells)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $cells = "O4:Q4";
                $event->sheet->mergeCells($cells);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setBold(true);
                $event->sheet->getDelegate()->getStyle($cells)->getFont()->setSize(12);
                $event->sheet->getDelegate()->getStyle($cells)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
                $event->sheet->getDelegate()->getStyle($cells)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEB9C');
                $event->sheet->getDelegate()->getStyle($cells)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'];
                foreach ($columns as $col) {
                    $event->sheet->getDelegate()->getColumnDimension($col)->setWidth(20);
                }
            }
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A5:U5')->getFont()->setBold(true);
        $sheet->getStyle('A5:U5')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
        $sheet->getStyle('A5:U5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A5:U5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF9619');
        $sheet->getStyle('A5:U5')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'];
        foreach ($columns as $col) {
            $sheet->getStyle("{$col}6:{$col}5000")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        $styles = [];
        foreach ($columns as $col) {
            $styles["{$col}5"] = ['font' => ['size' => 10]];
        }

        return $styles;
    }

    public function drawings()
    {
        $logoPath = public_path('/logo.png');

        if (!file_exists($logoPath)) {
            return [];
        }

        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo de la empresa');
        $drawing->setPath($logoPath);
        $drawing->setHeight(40);
        $drawing->setCoordinates('A1');
        $drawing->setOffsetX(5);
        $drawing->setOffsetY(5);

        return [$drawing];
    }
}