<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Credit extends Model
{
    /** @use HasFactory<\Database\Factories\CreditFactory> */
    use HasFactory;

    protected $fillable = [
        'sync_id',
        'agency',
        'collection_state',
        'frequency',
        'payment_date',
        'award_date',
        'due_date',
        'days_past_due',
        'total_fees',
        'paid_fees',
        'pending_fees',
        'monthly_fee_amount',
        'total_amount',
        'capital',
        'interest',
        'mora',
        'safe',
        'management_collection_expenses',
        'collection_expenses',
        'legal_expenses',
        'other_values',
        'sync_status',
        'last_sync_date',
        'management_status',
        'management_tray',
        'management_promise',
        'date_offer',
        'date_promise',
        'date_notification',
        'user_id',
        'business_id',
    ];

    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_credit');
    }

    public function collectionCalls()
    {
        return $this->hasMany(CollectionCall::class);
    }

    public function collectionManagements()
    {
        return $this->hasMany(Management::class);
    }

    public function collectionPayments()
    {
        return $this->hasMany(CollectionPayment::class);
    }
}
