<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpsPoint extends Model
{
    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'recorded_at',
        'accuracy',
        'battery_percentage',
        'type_status',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
