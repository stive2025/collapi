<?php

namespace App\Models;

use App\Traits\AdjustsTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessFactory> */
    use HasFactory, AdjustsTimestamps;

    protected $fillable=[
        'name',
        'state',
        'prelation_order'
    ];

    public function credits()
    {
        return $this->hasMany(Credit::class);
    }
}
