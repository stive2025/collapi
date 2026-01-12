<?php

namespace App\Models;

use App\Traits\AdjustsTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionContact extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionContactFactory> */
    use HasFactory, AdjustsTimestamps;
    
    protected $fillable=[
        'phone_number',
        'phone_type',
        'phone_status',
        'calls_effective',
        'calls_not_effective',
        'created_by',
        'updated_by',
        'deleted_by',
        'client_id'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

}
