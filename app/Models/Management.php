<?php

namespace App\Models;

use App\Traits\AdjustsTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Management extends Model
{
    /** @use HasFactory<\Database\Factories\ManagementFactory> */
    use HasFactory, AdjustsTimestamps;
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
        'nro_notification',
        'client_id',
        'credit_id',
        'campain_id'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function credit()
    {
        return $this->belongsTo(Credit::class);
    }

    public function campain()
    {
        return $this->belongsTo(Campain::class);
    }
}
