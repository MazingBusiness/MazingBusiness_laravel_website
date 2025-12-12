<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResetInventoryProduct extends Model
{
    protected $table = 'reset_inventory_products';

    protected $fillable = [
        'product_id',
        'part_no',        
    ];

    public $timestamps = true;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
