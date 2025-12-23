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
        'phone_number',
        'created_by',
        'client_id',
        'credit_id'
    ];

    public function credit()
    {
        return $this->belongsTo(Credit::class, 'credit_id');
    }

    public function contact()
    {
        return $this->belongsTo(CollectionContact::class, 'collection_contact_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
