<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use Illuminate\Http\Request;

class StatisticController extends Controller
{
    // Obtener métricas estadísticas (Reporte pagos con gestión)
    // GET /api/statistics/metrics
    // Este reporte devuelve los valores:  total,nro_credits,total_general,total_campain,total_castigado,total_vencido,nro_credits_total
    public function getMetrics(Request $request)
    {
        try {
            // Lógica para obtener métricas estadísticas
            $metrics = [
                'total_users' => 1500,
                'active_sessions' => 300,
                'total_revenue' => 125000,
            ];

            return ResponseBase::success(
                $metrics,
                'Métricas obtenidas correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener métricas: ' . $e->getMessage(),
                500
            );
        }
    }

    public function getPaymentsWithManagement(Request $request)
    {
        try {
            $activeCampain = \App\Models\Campain::where('state', 'ACTIVE')
                ->where('type', 'api')
                ->select('id', 'name', 'agents')
                ->first();

            if (!$activeCampain) {
                return ResponseBase::error(
                    'No hay campaña activa de tipo API',
                    null,
                    404
                );
            }

            $campainId = $activeCampain->id;
            $agentIds = $activeCampain->agents ?? [];

            // Estadísticas generales de la campaña activa
            $generalStats = \App\Models\CollectionPayment::where('campain_id', $campainId)
                ->selectRaw('
                    SUM(payment_value) as total_general,
                    SUM(CASE WHEN with_management = "SI" THEN payment_value ELSE 0 END) as total_with_management,
                    COUNT(DISTINCT CASE WHEN with_management = "SI" THEN credit_id ELSE NULL END) as total_credits,
                    MAX(updated_at) as last_update
                ')
                ->first();

            // Pagos con gestión donde la gestión también es de la campaña activa
            $totalInCampain = \App\Models\CollectionPayment::where('collection_payments.campain_id', $campainId)
                ->where('collection_payments.with_management', 'SI')
                ->whereNotNull('collection_payments.management_auto')
                ->join('management', 'collection_payments.management_auto', '=', 'management.id')
                ->where('management.campain_id', $campainId)
                ->sum('collection_payments.payment_value');

            // Pagos con gestión de créditos castigados
            $totalPunished = \App\Models\CollectionPayment::where('collection_payments.campain_id', $campainId)
                ->where('collection_payments.with_management', 'SI')
                ->join('credits', 'collection_payments.credit_id', '=', 'credits.id')
                ->where('credits.collection_state', 'CASTIGADO')
                ->sum('collection_payments.payment_value');

            // Pagos con gestión de créditos vencidos
            $totalOverdue = \App\Models\CollectionPayment::where('collection_payments.campain_id', $campainId)
                ->where('collection_payments.with_management', 'SI')
                ->join('credits', 'collection_payments.credit_id', '=', 'credits.id')
                ->where('credits.collection_state', 'VENCIDO')
                ->sum('collection_payments.payment_value');
            
            // Usuarios registrados en el campo agents de la campaña
            $agents = \App\Models\User::whereIn('id', $agentIds)
                ->select('id', 'name')
                ->get()
                ->map(function($user) use ($campainId) {
                    // Obtener pagos con gestión donde el usuario creó la gestión asociada, la gestión es de la campaña activa y days_past_due_auto > 60
                    $paymentsData = \App\Models\CollectionPayment::where('collection_payments.campain_id', $campainId)
                        ->where('collection_payments.with_management', 'SI')
                        ->whereNotNull('collection_payments.management_auto')
                        ->where('collection_payments.days_past_due_auto', '>', 60)
                        ->join('management', 'collection_payments.management_auto', '=', 'management.id')
                        ->where('management.created_by', $user->id)
                        ->where('management.campain_id', $campainId)
                        ->selectRaw('SUM(collection_payments.payment_value) as total, COUNT(DISTINCT collection_payments.credit_id) as nro_credits')
                        ->first();

                    return [
                        'name' => $user->name,
                        'total_with_management_in_campain' => (float) ($paymentsData->total ?? 0),
                        'nro_credits' => (int) ($paymentsData->nro_credits ?? 0),
                    ];
                })
                ->values();

            $statistics = [
                'total_general' => (float) ($generalStats->total_general ?? 0),
                'total_general_with_management' => (float) ($generalStats->total_with_management ?? 0),
                'total_with_management_in_campain' => (float) $totalInCampain,
                'total_credits_with_payment' => (int) ($generalStats->total_credits ?? 0),
                'total_general_punished' => (float) $totalPunished,
                'total_general_overdue' => (float) $totalOverdue,
                'last_update' => $generalStats->last_update ? \Carbon\Carbon::parse($generalStats->last_update)->format('Y-m-d H:i:s') : null,
                'campain_id' => $campainId,
                'campain_name' => $activeCampain->name,
                'agents' => $agents,
            ];

            return ResponseBase::success(
                $statistics,
                'Estadísticas obtenidas correctamente'
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al obtener estadísticas de pagos', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al obtener estadísticas',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    public function getPaymentsWithManagementDetail(Request $request)
    {
        try {
            $perPage = (int) $request->query('per_page', 15);
            
            $query = \App\Models\CollectionPayment::query()
                ->select(
                    'collection_payments.id',
                    'collection_payments.payment_value',
                    'collection_payments.payment_date',
                    'collection_payments.with_management',
                    'collection_payments.management_auto',
                    'collection_payments.days_past_due_auto',
                    'collection_payments.credit_id',
                    'collection_payments.campain_id'
                )
                ->with([
                    'credit' => function($q) {
                        $q->select('id', 'sync_id', 'collection_state', 'days_past_due', 'agency')
                            ->with(['clients' => function($clientQuery) {
                                $clientQuery->select('clients.id', 'clients.name', 'clients.ci');
                            }]);
                    }
                ]);

            // Filtros
            if ($request->filled('credit_name')) {
                $query->whereHas('credit.clients', function($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->query('credit_name') . '%');
                });
            }

            if ($request->filled('client_ci')) {
                $query->whereHas('credit.clients', function($q) use ($request) {
                    $q->where('ci', 'LIKE', '%' . $request->query('client_ci') . '%');
                });
            }

            if ($request->filled('agency')) {
                $query->whereHas('credit', function($q) use ($request) {
                    $q->where('agency', $request->query('agency'));
                });
            }

            if ($request->filled('collection_state')) {
                $query->whereHas('credit', function($q) use ($request) {
                    $q->where('collection_state', $request->query('collection_state'));
                });
            }

            if ($request->filled('days_past_due_min')) {
                $query->whereHas('credit', function($q) use ($request) {
                    $q->where('days_past_due', '>=', $request->query('days_past_due_min'));
                });
            }

            if ($request->filled('days_past_due_max')) {
                $query->whereHas('credit', function($q) use ($request) {
                    $q->where('days_past_due', '<=', $request->query('days_past_due_max'));
                });
            }

            if ($request->filled('management_type')) {
                $type = $request->query('management_type');
                if ($type === 'with') {
                    $query->where('with_management', 'SI');
                } elseif ($type === 'without') {
                    $query->where('with_management', 'NO');
                }
            }

            if ($request->filled('agent_id')) {
                $query->whereHas('managementAuto', function($q) use ($request) {
                    $q->where('created_by', $request->query('agent_id'));
                });
            }

            if ($request->filled('campain_id')) {
                $query->where('campain_id', $request->query('campain_id'));
            }

            $orderBy = $request->query('order_by', 'payment_date');
            $orderDir = $request->query('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);

            $payments = $query->paginate($perPage);

            // Transformar los datos
            $payments->getCollection()->transform(function($payment) {
                $credit = $payment->credit;
                $client = $credit && $credit->clients ? $credit->clients->first() : null;

                // Contar gestiones efectivas y no efectivas del crédito
                $effectiveCount = \App\Models\Management::where('credit_id', $payment->credit_id)
                    ->whereIn('substate', [
                        'COMPROMISO DE PAGO',
                        'CONVENIO DE PAGO',
                        'YA PAGO',
                        'YA PAGÓ',
                        'ABONO A DEUDA',
                        'OFERTA DE PAGO'
                    ])
                    ->count();

                $nonEffectiveCount = \App\Models\Management::where('credit_id', $payment->credit_id)
                    ->whereNotIn('substate', [
                        'COMPROMISO DE PAGO',
                        'CONVENIO DE PAGO',
                        'YA PAGO',
                        'YA PAGÓ',
                        'ABONO A DEUDA',
                        'OFERTA DE PAGO'
                    ])
                    ->count();

                // Sumar pagos con y sin gestión del mismo crédito
                $totalWithManagement = \App\Models\CollectionPayment::where('credit_id', $payment->credit_id)
                    ->where('with_management', 'SI')
                    ->sum('payment_value');

                $totalWithoutManagement = \App\Models\CollectionPayment::where('credit_id', $payment->credit_id)
                    ->where('with_management', 'NO')
                    ->sum('payment_value');

                // Obtener el agente de la gestión
                $agent = null;
                if ($payment->management_auto) {
                    $management = \App\Models\Management::with('creator')->find($payment->management_auto);
                    if ($management && $management->creator) {
                        $agent = $management->creator->name;
                    }
                }

                return [
                    'payment_id' => $payment->id,
                    'credit_name' => $client ? $client->name : 'N/A',
                    'credit_sync_id' => $credit ? $credit->sync_id : 'N/A',
                    'client_ci' => $client ? $client->ci : 'N/A',
                    'agency' => $credit ? $credit->agency : 'N/A',
                    'collection_state' => $credit ? $credit->collection_state : 'N/A',
                    'days_past_due' => $credit ? $credit->days_past_due : 0,
                    'management_type' => $payment->with_management,
                    'effective_managements_count' => $effectiveCount,
                    'non_effective_managements_count' => $nonEffectiveCount,
                    'total_paid_with_management' => (float) $totalWithManagement,
                    'total_paid_without_management' => (float) $totalWithoutManagement,
                    'agent' => $agent ?? 'N/A',
                    'payment_value' => (float) $payment->payment_value,
                    'payment_date' => $payment->payment_date,
                ];
            });

            return ResponseBase::success(
                $payments,
                'Detalle de pagos con gestión obtenido correctamente'
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error al obtener detalle de pagos con gestión', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al obtener detalle de pagos',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
