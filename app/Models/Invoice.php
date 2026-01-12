<?php

namespace App\Models;

use App\Traits\AdjustsTimestamps;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use AdjustsTimestamps;

    protected $fillable = [
        'invoice_value',
        'tax_value',
        'invoice_institution',
        'invoice_method',
        'invoice_access_key',
        'invoice_number',
        'invoice_date',
        'credit_id',
        'client_id',
        'status',
        'created_by'
    ];

    public function credit()
    {
        return $this->belongsTo(\App\Models\Credit::class);
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
