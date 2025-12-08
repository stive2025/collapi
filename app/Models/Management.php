<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Management extends Model
{
    /** @use HasFactory<\Database\Factories\ManagementFactory> */
    use HasFactory;
    protected $fillable = [
        'state',
        'substate',
        'observation',
        'promise_date',
        'promise_amount',
        'created_by',
        'call_id',
        'call_collection',
        'days_past_due',
        'paid_fees',
        'pending_fees',
        'managed_amount',
        'client_id',
        'credit_id',
        'campain_id'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
