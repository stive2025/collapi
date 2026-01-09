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
            $firstDayOfMonth = now()->startOfMonth();
            $agentIds = $activeCampain->agents ?? []; // Array de IDs de usuarios

            $generalStats = \App\Models\CollectionPayment::where('campain_id', $campainId)
                ->selectRaw('
                    SUM(CASE WHEN with_management = "SI" THEN payment_value ELSE 0 END) as total_with_management,
                    SUM(payment_value) as total_general,
                    COUNT(DISTINCT credit_id) as total_credits,
                    MAX(updated_at) as last_update
                ')
                ->first();

            $detailedStats = \App\Models\CollectionPayment::where('collection_payments.campain_id', $campainId)
                ->where('collection_payments.with_management', 'SI')
                ->join('credits', 'collection_payments.credit_id', '=', 'credits.id')
                ->leftJoin('collection_credits', function($join) use ($campainId) {
                    $join->on('credits.id', '=', 'collection_credits.credit_id')
                        ->where('collection_credits.campain_id', '=', $campainId);
                })
                ->selectRaw('
                    SUM(CASE 
                        WHEN collection_credits.created_at IS NOT NULL AND collection_credits.created_at >= ? 
                        THEN collection_payments.payment_value 
                        ELSE 0 
                    END) as total_in_campain,
                    SUM(CASE 
                        WHEN credits.collection_state = "CASTIGADO" 
                        THEN collection_payments.payment_value 
                        ELSE 0 
                    END) as total_punished,
                    SUM(CASE 
                        WHEN credits.collection_state = "VENCIDO" 
                        THEN collection_payments.payment_value 
                        ELSE 0 
                    END) as total_overdue
                ', [$firstDayOfMonth])
                ->first();
            
            // Usuarios registrados en el campo agents de la campaña
            $agents = \App\Models\User::whereIn('id', $agentIds)
                ->select('id', 'name')
                ->get()
                ->map(function($user) use ($campainId, $firstDayOfMonth) {
                    // Obtener IDs de créditos del usuario activos desde el primer día del mes
                    $creditIds = \App\Models\CollectionCredit::where('campain_id', $campainId)
                        ->where('user_id', $user->id)
                        ->where('created_at', '>=', $firstDayOfMonth)
                        ->pluck('credit_id');
                    
                    // Sumar pagos con gestión de esos créditos (devuelve 0 si no hay)
                    $total = 0;
                    if ($creditIds->isNotEmpty()) {
                        $total = \App\Models\CollectionPayment::where('campain_id', $campainId)
                            ->where('with_management', 'SI')
                            ->whereIn('credit_id', $creditIds)
                            ->sum('payment_value');
                    }
                    
                    return [
                        'name' => $user->name,
                        'total_with_management_in_campain' => (float) ($total ?? 0),
                    ];
                })
                ->values(); // Re-indexar el array

            $statistics = [
                'total_general_with_management' => (float) ($generalStats->total_with_management ?? 0),
                'total_general' => (float) ($generalStats->total_general ?? 0),
                'total_with_management_in_campain' => (float) ($detailedStats->total_in_campain ?? 0),
                'total_credits_with_payment' => (int) ($generalStats->total_credits ?? 0),
                'total_general_punished' => (float) ($detailedStats->total_punished ?? 0),
                'total_general_overdue' => (float) ($detailedStats->total_overdue ?? 0),
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
}
