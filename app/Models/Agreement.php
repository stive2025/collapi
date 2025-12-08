<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agreement extends Model
{
    /** @use HasFactory<\Database\Factories\AgreementFactory> */
    use HasFactory;

    protected $fillable = [
        'credit_id',
        'total_amount',
        'invoice_id',
        'total_fees',
        'paid_fees',
        'fee_amount',
        'fee_detail', // {[payment_amount,payment_date,payment_value,payment_status]}
        'created_by',
        'updated_by',
        'status'
    ];
}
