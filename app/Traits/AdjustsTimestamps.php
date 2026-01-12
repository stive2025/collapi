<?php

namespace App\Traits;

use Carbon\Carbon;

trait AdjustsTimestamps
{
    /**
     * Obtener el atributo created_at ajustado (restando 5 horas)
     */
    public function getCreatedAtAttribute($value)
    {
        if (!$value) {
            return null;
        }
        
        return Carbon::parse($value)->subHours(5);
    }

    /**
     * Obtener el atributo updated_at ajustado (restando 5 horas)
     */
    public function getUpdatedAtAttribute($value)
    {
        if (!$value) {
            return null;
        }
        
        return Carbon::parse($value)->subHours(5);
    }
}
