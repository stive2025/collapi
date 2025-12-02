<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionDirection extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionDirectionFactory> */
    use HasFactory;
    protected $fillable=[
        'credit_id',
        'direction_type',
        'value',
        'prelation_order',
        'state'
    ];
}
