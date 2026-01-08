<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Condonation extends Model
{
    protected $fillable = [
        'credit_id',
        'amount',
        'prev_dates',
        'post_dates',
        'created_by',
        'updated_by',
        'reverted_by',
        'reverted_at',
        'status',
    ];

    protected $casts = [
        'reverted_at' => 'datetime',
    ];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(Credit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }
}
