<?php

namespace App\Models;

use App\Traits\AdjustsTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionCredit extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionCreditFactory> */
    use HasFactory, AdjustsTimestamps;

    protected $fillable = [
        'collection_state', 
        'days_past_due',
        'paid_fees',
        'pending_fees',
        'total_amount',
        'capital',
        'interest',
        'fees_amount',
        'mora',
        'safe',
        'management_collection_expenses',
        'collection_expenses',
        'legal_expenses',
        'other_values',
        'credit_id',
        'campain_id',
        'user_id',
    ];
}
