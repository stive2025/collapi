<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory;
    protected $fillable = [
        'name',
        'ci',
        'type',
        'gender',
        'civil_status',
        'economic_activity'
    ];

    public function credits()
    {
        return $this->belongsToMany(Credit::class, 'client_credit');
    }
}