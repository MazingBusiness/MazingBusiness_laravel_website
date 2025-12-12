<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariationValue extends Model
{
    use HasFactory;
    protected $fillable = ['id', 'product_id','variation_group_id','variation_id','value','status'];
}
