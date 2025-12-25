<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateModel extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'parent_id',
    ];

    public function parent()
    {
        return $this->belongsTo(TemplateModel::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(TemplateModel::class, 'parent_id');
    }
}