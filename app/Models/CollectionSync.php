<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionSync extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionSyncFactory> */
    use HasFactory;
    protected $fillable=[
        'credit_id',
        'sync_type',
        'sync_date',
        'state'
    ];
}
