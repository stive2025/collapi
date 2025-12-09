<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionCall extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionCallFactory> */
    use HasFactory;

    protected $fillable=[
        'state',
        'duration',
        'media_path',
        'channel',
        'created_by',
        'collection_contact_id',
        'credit_id'
    ];

    public function credit()
    {
        return $this->belongsTo(Credit::class, 'credit_id');
    }

}
