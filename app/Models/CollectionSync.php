<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionSync extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionSyncFactory> */
    use HasFactory;
    protected $fillable=[
        'new_credits', // Nro de créditos o pagos nuevos
        'sync_type', // SYNC-CREDITS, SINC-PAYMENTS
        'state_description', // Descripción del estado del sync
        'code_syncs', // Códigos de créditos sincronizados
        'nro_credits', // Nro de créditos o pagos sincronizados
        'state' // Estado del sync
    ];
}
