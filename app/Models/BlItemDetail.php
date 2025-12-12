<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlItemDetail extends Model
{
    public $timestamps = true;
    protected $table = 'bl_item_details';

    protected $fillable = [
        'bl_id',
        'product_id',
        'item_name',
        'weight_per_carton',
        'cbm_per_carton',
        'quantity',
        'dollar_price',
        'supplier_invoice_no',
        'supplier_invoice_date',
        
        'total_no_of_packages',
        'total_weight',
        'total_cbm',
        'supplier_id',
        'import_photo_id',
    ];

    protected $casts = [
        'product_id'           => 'integer',
        'weight_per_carton'    => 'float',
        'cbm_per_carton'       => 'float',
        'quantity'             => 'integer',
        'dollar_price'         => 'float',
        'supplier_invoice_date'=> 'date',
    ];

    public function bl()
    {
        return $this->belongsTo(BlDetail::class, 'bl_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
