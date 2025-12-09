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
        'gender',
        'civil_status',
        'economic_activity'
    ];

    public function credits()
    {
        return $this->belongsToMany(Credit::class, 'client_credit');
    }

    public function collectionContacts()
    {
        return $this->hasMany(CollectionContact::class);
    }
    
    public function directions()
    {
        return $this->hasMany(CollectionDirection::class);
    }

    public function collectionCredits()
    {
        return $this->hasManyThrough(CollectionCredit::class, Credit::class, 'id', 'credit_id', 'id', 'id');
    }

    public function collectionCalls()
    {
        return $this->hasManyThrough(CollectionCall::class, Credit::class, 'id', 'credit_id', 'id', 'id');
    }
}