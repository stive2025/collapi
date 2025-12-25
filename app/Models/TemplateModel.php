<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateModel extends Model
{
    protected $fillable = [
        'name',
        'is_active',
    ];

    /**
     * Los padres de este template (muchos-a-muchos)
     */
    public function parents()
    {
        return $this->belongsToMany(
            TemplateModel::class,
            'template_parent',
            'template_id',
            'parent_id'
        )->withTimestamps();
    }

    /**
     * Los hijos de este template (muchos-a-muchos)
     */
    public function children()
    {
        return $this->belongsToMany(
            TemplateModel::class,
            'template_parent',
            'parent_id',
            'template_id'
        )->withTimestamps();
    }
}