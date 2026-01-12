<?php

namespace App\Models;

use App\Traits\AdjustsTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionDirection extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionDirectionFactory> */
    use HasFactory, AdjustsTimestamps;
    protected $fillable=[
        'client_id',
        'direction',
        'type',
        'province',
        'canton',
        'parish',
        'neighborhood',
        'latitude',
        'longitude',
    ];
    
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
