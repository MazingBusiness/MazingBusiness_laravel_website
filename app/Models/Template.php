<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $table = 'templates';

    protected $fillable = [
        'name',
        'ci_view',
        'pl_view',
        'is_active',
    ];

    public function suppliers()
    {
        return $this->hasMany(Supplier::class, 'template_id');
    }
}
