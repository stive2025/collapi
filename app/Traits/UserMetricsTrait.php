<?php

namespace App\Traits;

use App\Models\Management;
use App\Models\Credit;
use App\Models\CollectionCall;
use App\Models\Campain;

trait UserMetricsTrait
{
    /**
     * Calculate user metrics for a specific campaign
     *
     * @param int $userId
     * @param int|null $campainId
     * @return array
     */
    protected function calculateUserMetrics(int $userId, ?int $campainId = null): array
    {
        $campain = $campainId ? Campain::find($campainId) : null;
        $today = now()->startOfDay();

        // Si no hay campaña, retornar métricas vacías
        if (!$campain) {
            return [
                'nro_credits' => 0,
                'nro_gestions' => 0,
                'nro_gestions_dia' => 0,
                'nro_gestions_efec' => 0,
                'nro_gestions_efec_dia' => 0,
                'nro_pendientes' => 0,
                'nro_proceso' => 0,
                'nro_proceso_dia' => 0,
                'nro_calls' => 0,
                'nro_calls_acum' => 0,
            ];
        }

        // Créditos asignados
        $nroCredits = Credit::where('user_id', $userId)
            ->where('business_id', $campain->business_id)
            ->count();

        // Gestiones en la campaña
        $nroGestions = Management::where('created_by', $userId)
            ->where('campain_id', $campain->id)
            ->count();

        // Gestiones del día
        $nroGestionsDia = Management::where('created_by', $userId)
            ->where('campain_id', $campain->id)
            ->whereDate('created_at', $today)
            ->count();

        // Gestiones efectivas en la campaña
        $nroGestionsEfec = Management::where('created_by', $userId)
            ->where('campain_id', $campain->id)
            ->whereIn('state', ['EFECTIVA', 'PROMESA_PAGO', 'COMPROMISO_PAGO'])
            ->count();

        // Gestiones efectivas del día
        $nroGestionsEfecDia = Management::where('created_by', $userId)
            ->where('campain_id', $campain->id)
            ->whereIn('state', ['EFECTIVA', 'PROMESA_PAGO', 'COMPROMISO_PAGO'])
            ->whereDate('created_at', $today)
            ->count();

        // Créditos pendientes
        $nroPendientes = Credit::where('user_id', $userId)
            ->where('business_id', $campain->business_id)
            ->where('management_tray', 'PENDIENTE')
            ->count();

        // Créditos en proceso
        $nroProceso = Credit::where('user_id', $userId)
            ->where('business_id', $campain->business_id)
            ->where('management_tray', 'EN PROCESO')
            ->count();

        // Créditos en proceso del día
        $nroProcesoDia = Credit::where('user_id', $userId)
            ->where('business_id', $campain->business_id)
            ->where('management_tray', 'EN PROCESO')
            ->whereDate('last_sync_date', $today)
            ->count();

        // Llamadas del día
        $nroCalls = CollectionCall::where('created_by', $userId)
            ->whereDate('created_at', $today)
            ->count();

        // Llamadas acumuladas en la campaña
        $nroCallsAcum = CollectionCall::where('created_by', $userId)
            ->whereHas('credit', function($q) use ($campain) {
                $q->where('business_id', $campain->business_id);
            })
            ->count();

        return [
            'nro_credits' => $nroCredits,
            'nro_gestions' => $nroGestions,
            'nro_gestions_dia' => $nroGestionsDia,
            'nro_gestions_efec' => $nroGestionsEfec,
            'nro_gestions_efec_dia' => $nroGestionsEfecDia,
            'nro_pendientes' => $nroPendientes,
            'nro_proceso' => $nroProceso,
            'nro_proceso_dia' => $nroProcesoDia,
            'nro_calls' => $nroCalls,
            'nro_calls_acum' => $nroCallsAcum,
        ];
    }

    /**
     * Calculate time in state for a user
     *
     * @param \App\Models\User $user
     * @return string
     */
    protected function calculateTimeState($user): string
    {
        $timeElapsed = abs(now()->diffInSeconds($user->updated_at));
        $hours = floor($timeElapsed / 3600);
        $minutes = floor(($timeElapsed % 3600) / 60);
        $seconds = $timeElapsed % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}