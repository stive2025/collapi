<?php

namespace App\Services;

class UtilService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function setState($value)
    {
        $value = strtoupper($value);
        if (in_array($value, ['PREJUDICIAL', 'EN TRAMITE JUDICIAL', 'VENCIDO TOTAL', 'CARTERA VENDIDA', 'VENCIDO'])) {
            return 'Vencido';
        } elseif (in_array($value, ['CANCELADO', 'CANCELADO'])) {
            return 'Cancelado';
        } elseif ($value == 'JUDICIAL') {
            return 'Judicial';
        } elseif ($value == 'VIGENTE') {
            return 'Vigente';
        } elseif ($value == 'CONVENIO DE PAGO') {
            return 'CONVENIO DE PAGO';
        }
        return $value;
    }
    
    public function setRange(int $days_past_due){
        if($days_past_due<1){
            return "A) Preventiva";
        }else if($days_past_due==1){
            return "B) 1";
        }else if($days_past_due>=2 & $days_past_due<=5){
            return "C) 2-5";
        }else if($days_past_due>=6 & $days_past_due<=15){
            return "D) 6-15";
        }else if($days_past_due>=16 & $days_past_due<=30){
            return "E) 16-30";
        }else if($days_past_due>=31 & $days_past_due<=60){
            return "F) 31-60";
        }else if($days_past_due>=61 & $days_past_due<=90){
            return "G) 61-90";
        }else if($days_past_due>=91 & $days_past_due<=120){
            return "H) 91-120";
        }else if($days_past_due>=121 & $days_past_due<=180){
            return "I) 121-180";
        }else if($days_past_due>=181 & $days_past_due<=360){
            return "J) 181-360";
        }else if($days_past_due>=361 & $days_past_due<=720){
            return "K) 361-720";
        }else if($days_past_due>=721 & $days_past_due<=1080){
            return "L) 721-1080";
        }else if($days_past_due>1081){
            return "M) Más de 1080";
        }
    }

    public function calculateManagementCollectionExpenses(float $amount, int $days_past_due){
        $gastos = 0;

        // Rangos de monto y días con sus respectivos gastos
        $expenses = [
            // Monto < 100
            ['amount_min' => 0, 'amount_max' => 99.99, 'days_min' => 1, 'days_max' => 30, 'expense' => 6.38],
            ['amount_min' => 0, 'amount_max' => 99.99, 'days_min' => 31, 'days_max' => 60, 'expense' => 16.23],
            ['amount_min' => 0, 'amount_max' => 99.99, 'days_min' => 61, 'days_max' => 90, 'expense' => 23.17],
            ['amount_min' => 0, 'amount_max' => 99.99, 'days_min' => 91, 'days_max' => PHP_INT_MAX, 'expense' => 25.56],

            // Monto 100-199
            ['amount_min' => 100, 'amount_max' => 199, 'days_min' => 1, 'days_max' => 30, 'expense' => 7.35],
            ['amount_min' => 100, 'amount_max' => 199, 'days_min' => 31, 'days_max' => 60, 'expense' => 16.46],
            ['amount_min' => 100, 'amount_max' => 199, 'days_min' => 61, 'days_max' => 90, 'expense' => 23.85],
            ['amount_min' => 100, 'amount_max' => 199, 'days_min' => 91, 'days_max' => PHP_INT_MAX, 'expense' => 26.64],

            // Monto 200-299
            ['amount_min' => 200, 'amount_max' => 299, 'days_min' => 1, 'days_max' => 30, 'expense' => 7.92],
            ['amount_min' => 200, 'amount_max' => 299, 'days_min' => 31, 'days_max' => 60, 'expense' => 17.83],
            ['amount_min' => 200, 'amount_max' => 299, 'days_min' => 61, 'days_max' => 90, 'expense' => 25.27],
            ['amount_min' => 200, 'amount_max' => 299, 'days_min' => 91, 'days_max' => PHP_INT_MAX, 'expense' => 29.03],

            // Monto 300-499
            ['amount_min' => 300, 'amount_max' => 499, 'days_min' => 1, 'days_max' => 30, 'expense' => 8.32],
            ['amount_min' => 300, 'amount_max' => 499, 'days_min' => 31, 'days_max' => 60, 'expense' => 20.34],
            ['amount_min' => 300, 'amount_max' => 499, 'days_min' => 61, 'days_max' => 90, 'expense' => 27.43],
            ['amount_min' => 300, 'amount_max' => 499, 'days_min' => 91, 'days_max' => PHP_INT_MAX, 'expense' => 32.72],

            // Monto 500-999
            ['amount_min' => 500, 'amount_max' => 999, 'days_min' => 1, 'days_max' => 30, 'expense' => 8.63],
            ['amount_min' => 500, 'amount_max' => 999, 'days_min' => 31, 'days_max' => 60, 'expense' => 23.99],
            ['amount_min' => 500, 'amount_max' => 999, 'days_min' => 61, 'days_max' => 90, 'expense' => 30.34],
            ['amount_min' => 500, 'amount_max' => 999, 'days_min' => 91, 'days_max' => PHP_INT_MAX, 'expense' => 37.7],

            // Monto >= 1000
            ['amount_min' => 1000, 'amount_max' => PHP_INT_MAX, 'days_min' => 1, 'days_max' => 30, 'expense' => 8.88],
            ['amount_min' => 1000, 'amount_max' => PHP_INT_MAX, 'days_min' => 31, 'days_max' => 60, 'expense' => 28.78],
            ['amount_min' => 1000, 'amount_max' => PHP_INT_MAX, 'days_min' => 61, 'days_max' => 90, 'expense' => 34.01],
            ['amount_min' => 1000, 'amount_max' => PHP_INT_MAX, 'days_min' => 91, 'days_max' => PHP_INT_MAX, 'expense' => 43.99],
        ];

        foreach ($expenses as $range) {
            if ($amount >= $range['amount_min'] && $amount <= $range['amount_max'] &&
                $days_past_due >= $range['days_min'] && $days_past_due <= $range['days_max']) {
                $gastos = $range['expense'];
                break;
            }
        }

        // Aplicar el 15% adicional
        return round($gastos + $gastos * 0.15, 2);
    }

    /*

    |------------------------------------------------------------------------------------------
    | Collection Credit/Payment Utilities: Métodos necesarios para asociación de gestiones con pagos
    |------------------------------------------------------------------------------------------
    */

    public function getLastSyncDay(int $credit_id, string $date = null){
        if ($date) {
            // Buscar si hay un registro para la fecha específica
            $syncOnDate = \App\Models\CollectionCredit::where('credit_id', $credit_id)
                ->whereDate('created_at', $date)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($syncOnDate) {
                return [
                    'sync' => $syncOnDate,
                    'is_active' => true,
                    'days_past_due' => $syncOnDate->days_past_due ?? 0
                ];
            }

            // Si no hay registro para esa fecha, buscar el último registro anterior
            $lastSync = \App\Models\CollectionCredit::where('credit_id', $credit_id)
                ->whereDate('created_at', '<', $date)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastSync) {
                return [
                    'sync' => $lastSync,
                    'is_active' => false,
                    'days_past_due' => $lastSync->days_past_due ?? 0,
                    'last_active_date' => $lastSync->created_at
                ];
            }

            return null;
        }

        // Sin fecha, devolver el último registro
        $lastSync = \App\Models\CollectionCredit::where('credit_id', $credit_id)
            ->orderBy('created_at', 'desc')
            ->first();

        return $lastSync;
    }

    public function getEffectiveManagements(int $credit_id, string $payment_date){
        $adjustedPaymentDate = \Carbon\Carbon::parse($payment_date)->addHours(5)->format('Y-m-d H:i:s');

        $managements = \App\Models\Management::where('credit_id', $credit_id)
            ->where('created_at', '<=', $adjustedPaymentDate)
            ->whereIn('substate', [
                'CLIENTE SE NIEGA A PAGAR',
                'CLIENTE INDICA QUE NO ES SU DEUDA',
                'COMPROMISO DE PAGO',
                'CONVENIO DE PAGO',
                'MENSAJE A TERCEROS',
                'MENSAJE DE TEXTO',
                'MENSAJE EN BUZON DE VOZ',
                'MENSAJE EN BUZÓN DEL CLIENTE',
                'NOTIFICADO',
                'ENTREGADO AVISO DE COBRANZA',
                'PASAR A TRÁMITE LEGAL',
                'REGESTIÓN',
                'YA PAGO',
                'OFERTA DE PAGO',
                'YA PAGÓ',
                'SOLICITA REFINANCIAMIENTO',
                'ABONO A DEUDA'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return $managements ? $managements : null;
    }

    public function associateManagementsToPayment(string $date){
        // Obtener todos los pagos de la fecha especificada
        $payments = \App\Models\CollectionPayment::whereDate('payment_date', $date)->get();

        foreach ($payments as $payment) {
            $credit_id = $payment->credit_id;
            $payment_date = $payment->payment_date;
            $payment_date_only = \Carbon\Carbon::parse($payment_date)->format('Y-m-d');

            // Obtener información de sincronización del crédito
            $syncInfo = $this->getLastSyncDay($credit_id, $payment_date_only);

            // Obtener gestiones efectivas antes del pago
            $managements = $this->getEffectiveManagements($credit_id, $payment_date);

            $management_auto = null;
            $management_prev = null;
            $days_past_due_auto = null;
            $days_past_due_prev = null;
            $with_management = 'NO';
            $post_management = 'NO';

            if ($managements && $managements->count() > 0) {
                // Hay gestiones antes del pago
                $with_management = 'SI';

                // La gestión más reciente es management_auto
                $management_auto = $managements->first();

                // Calcular días de mora en la fecha del pago según management_auto
                if ($management_auto->days_past_due !== null) {
                    $managementDate = \Carbon\Carbon::parse($management_auto->created_at);
                    $paymentDateCarbon = \Carbon\Carbon::parse($payment_date);
                    $daysDifference = $managementDate->diffInDays($paymentDateCarbon);
                    $days_past_due_auto = $management_auto->days_past_due + $daysDifference;
                }

                // Si hay más de una gestión, la segunda es management_prev
                if ($managements->count() > 1) {
                    $management_prev = $managements->get(1);

                    // Calcular días de mora en la fecha del pago según management_prev
                    if ($management_prev->days_past_due !== null) {
                        $managementPrevDate = \Carbon\Carbon::parse($management_prev->created_at);
                        $paymentDateCarbon = \Carbon\Carbon::parse($payment_date);
                        $daysDifference = $managementPrevDate->diffInDays($paymentDateCarbon);
                        $days_past_due_prev = $management_prev->days_past_due + $daysDifference;
                    }
                }
            } else {
                // No hay gestiones antes del pago, verificar si hay después
                $adjustedPaymentDate = \Carbon\Carbon::parse($payment_date)->addHours(5)->format('Y-m-d H:i:s');
                $managementsAfter = \App\Models\Management::where('credit_id', $credit_id)
                    ->where('created_at', '>', $adjustedPaymentDate)
                    ->whereIn('substate', [
                        'CLIENTE SE NIEGA A PAGAR',
                        'CLIENTE INDICA QUE NO ES SU DEUDA',
                        'COMPROMISO DE PAGO',
                        'CONVENIO DE PAGO',
                        'MENSAJE A TERCEROS',
                        'MENSAJE DE TEXTO',
                        'MENSAJE EN BUZON DE VOZ',
                        'MENSAJE EN BUZÓN DEL CLIENTE',
                        'NOTIFICADO',
                        'ENTREGADO AVISO DE COBRANZA',
                        'PASAR A TRÁMITE LEGAL',
                        'REGESTIÓN',
                        'YA PAGO',
                        'OFERTA DE PAGO',
                        'YA PAGÓ',
                        'SOLICITA REFINANCIAMIENTO',
                        'ABONO A DEUDA'
                    ])
                    ->exists();

                if ($managementsAfter) {
                    $post_management = 'SI';
                }
            }

            // Actualizar el pago con la información calculada
            $payment->update([
                'with_management' => $with_management,
                'management_auto' => $management_auto ? $management_auto->id : null,
                'days_past_due_auto' => $days_past_due_auto,
                'management_prev' => $management_prev ? $management_prev->id : null,
                'days_past_due_prev' => $days_past_due_prev,
                'post_management' => $post_management
            ]);
        }
    }

    public function getMonthNumber($monthName)
    {
        $months = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
        ];

        return $months[strtolower($monthName)] ?? 1;
    }

    public function getLastThreeMonthsCampaigns($businessId)
    {
        $currentMonth = (int) date('m');
        $currentYear = (int) date('Y');

        $months = [];
        for ($i = 3; $i >= 1; $i--) {
            $month = $currentMonth - $i;
            $year = $currentYear;

            if ($month <= 0) {
                $month += 12;
                $year--;
            }

            $months[] = [
                'month' => $month,
                'year' => $year,
                'name' => $this->getMonthName($month)
            ];
        }

        $campaigns = [];
        foreach ($months as $monthData) {
            $startDate = "{$monthData['year']}-{$monthData['month']}-01 00:00:00";
            $endDate = date('Y-m-t 23:59:59', strtotime($startDate));

            $campaign = \App\Models\Campain::where('business_id', $businessId)
                ->where(function($query) use ($startDate, $endDate) {
                    $query->whereBetween('begin_time', [$startDate, $endDate])
                          ->orWhereBetween('end_time', [$startDate, $endDate])
                          ->orWhere(function($q) use ($startDate, $endDate) {
                              $q->where('begin_time', '<=', $startDate)
                                ->where('end_time', '>=', $endDate);
                          });
                })
                ->first();

            $campaigns[] = [
                'month' => $monthData['month'],
                'year' => $monthData['year'],
                'name' => $monthData['name'],
                'campaign_id' => $campaign ? $campaign->id : null
            ];
        }

        return $campaigns;
    }

    public function getMonthName($monthNumber)
    {
        $months = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
        ];

        return $months[$monthNumber] ?? 'ENERO';
    }

    public function getAgentNameForCreditInMonth($creditId, $campaignId)
    {
        if (!$campaignId) {
            return '';
        }

        $collectionCredit = \App\Models\CollectionCredit::where('credit_id', $creditId)
            ->where('campain_id', $campaignId)
            ->orderBy('id', 'DESC')
            ->first();

        if ($collectionCredit && $collectionCredit->user_id) {
            $user = \App\Models\User::find($collectionCredit->user_id);
            return $user ? $user->name : '';
        }

        return '';
    }
}
