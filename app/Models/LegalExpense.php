<?php

namespace App\Models;

use App\Traits\AdjustsTimestamps;
use Illuminate\Database\Eloquent\Model;

class LegalExpense extends Model
{
    use AdjustsTimestamps;

    protected $fillable = [
        'credit_id',
        'business_id',
        'created_by',
        'modify_date',
        'prev_amount',
        'post_amount',
        'detail',
        'total_value',
        'sync_id',
    ];

    protected $casts = [
        'modify_date' => 'date',
        'prev_amount' => 'decimal:2',
        'post_amount' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    public function credit()
    {
        return $this->belongsTo(Credit::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
