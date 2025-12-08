<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Condonation extends Model
{
    protected $fillable = [
        'credit_id',
        'amount',
        'prev_dates',
        'post_dates',
        'created_by',
        'updated_by',
        'status',
    ];
}
