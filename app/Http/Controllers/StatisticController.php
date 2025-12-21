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
            // Lógica para obtener estadísticas
            $statistics = [
                'daily_active_users' => 200,
                'monthly_revenue' => 50000,
                'new_signups' => 75,
            ];

            return ResponseBase::success(
                $statistics,
                'Estadísticas obtenidas correctamente'
            );
        } catch (\Exception $e) {
            return ResponseBase::error(
                'Error al obtener estadísticas: ' . $e->getMessage(),
                500
            );
        }
    }
}
