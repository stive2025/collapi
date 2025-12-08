<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
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
}
