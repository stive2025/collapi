<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Campain extends Model
{
    /** @use HasFactory<\Database\Factories\CampainFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'state',
        'type',
        'begin_time',
        'end_time',
        'agents',
        'business_id'
    ];

    protected $casts = [
        'agents' => 'array',
    ];

    protected $appends = ['agents_details'];

    /**
     * Get the agents details with username and id
     */
    protected function agentsDetails(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->agents || !is_array($this->agents)) {
                    return [];
                }

                return User::whereIn('id', $this->agents)
                    ->select('id', 'name')
                    ->get()
                    ->toArray();
            }
        );
    }
}