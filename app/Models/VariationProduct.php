<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariationProduct extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'product_id','variation_group_id','variation_id','status'];
}
