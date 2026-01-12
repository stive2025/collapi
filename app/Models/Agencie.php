<?php

namespace App\Models;

use App\Traits\AdjustsTimestamps;
use Illuminate\Database\Eloquent\Model;

class Agencie extends Model
{
    use AdjustsTimestamps;

    protected $fillable = [
        'name'
    ];
}
