<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionPayment extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionPaymentFactory> */
    use HasFactory;
    protected $fillable=[
        'credit_id',
        'payment_type',
        'amount',
        'payment_date',
        'state'
    ];
}
