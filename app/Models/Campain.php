<?php

namespace App\Models;

use App\Traits\AdjustsTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Campain extends Model
{
    /** @use HasFactory<\Database\Factories\CampainFactory> */
    use HasFactory, AdjustsTimestamps;

    protected $fillable = [
        'name',
        'state',
        'type',
        'begin_time',
        'end_time',
        'agents',
        'business_id'
    ];

    protected $appends = ['agents_details'];

    /**
     * Get and set the agents attribute
     */
    protected function agents(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $decoded = json_decode($value, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $temp = json_decode($value, true);
                    if (is_string($temp)) {
                        $decoded = json_decode($temp, true);
                    }
                }

                return $decoded;
            },
            set: function ($value) {
                if (is_null($value)) {
                    return null;
                }

                if (is_array($value)) {
                    return json_encode($value);
                }

                return $value;
            }
        );
    }

    /**
     * Get the agents details with username and id
     */
    protected function agentsDetails(): Attribute
    {
        return Attribute::make(
            get: function () {
                $rawValue = $this->attributes['agents'] ?? null;

                if (is_null($rawValue) || $rawValue === '') {
                    return [];
                }
                
                $agents = null;

                if (is_string($rawValue)) {
                    $agents = json_decode($rawValue, true);

                    if (is_string($agents)) {
                        $agents = json_decode($agents, true);
                    }
                } else {
                    $agents = $rawValue;
                }

                return User::whereIn('id', $agents)
                    ->select('id', 'name')
                    ->get()
                    ->toArray();
            }
        );
    }
}