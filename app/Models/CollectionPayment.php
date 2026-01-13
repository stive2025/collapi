<?php

namespace App\Models;

use App\Traits\AdjustsTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionPayment extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionPaymentFactory> */
    use HasFactory, AdjustsTimestamps;

    protected $fillable = [
        'created_by',
        'payment_date',
        'payment_deposit_date',
        'payment_value',
        'payment_difference',
        'payment_type',
        'payment_method',
        'financial_institution',
        'payment_reference',
        'payment_status',
        'payment_prints',
        'payment_number',
        'fee',
        'capital',
        'interest',
        'mora',
        'safe',
        'management_collection_expenses',
        'collection_expenses',
        'legal_expenses',
        'other_values',
        'prev_dates',
        'with_management',
        'management_auto',
        'days_past_due_auto',
        'management_prev',
        'days_past_due_prev',
        'post_management',
        'credit_id',
        'business_id',
        'campain_id',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'payment_value' => 'float',
        'payment_difference' => 'float',
        'payment_prints' => 'integer',
        'fee' => 'float',
        'capital' => 'float',
        'interest' => 'float',
        'mora' => 'float',
        'safe' => 'float',
        'management_collection_expenses' => 'float',
        'collection_expenses' => 'float',
        'legal_expenses' => 'float',
        'other_values' => 'float',
        'management_auto' => 'integer',
        'days_past_due_auto' => 'integer',
        'management_prev' => 'integer',
        'days_past_due_prev' => 'integer',
    ];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function campain(): BelongsTo
    {
        return $this->belongsTo(Campain::class);
    }

    public function managementAuto(): BelongsTo
    {
        return $this->belongsTo(Management::class, 'management_auto');
    }

    public function managementPrev(): BelongsTo
    {
        return $this->belongsTo(Management::class, 'management_prev');
    }
}