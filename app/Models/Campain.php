<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campain extends Model
{
    /** @use HasFactory<\Database\Factories\CampainFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'state',
        'type',
        'begin_time',
        'end_time',
        'agents',
        'business_id'
    ];

}