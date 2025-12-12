<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CiItemDetail extends Model
{
    public $timestamps = true;
    protected $table = 'ci_item_details';

    protected $fillable = [
        'ci_id',
        'product_id',
        'supplier_id',
        'item_name',
        'weight_per_carton',
        'cbm_per_carton',
        'quantity',
        'dollar_price',
        
        'total_no_of_packages',
        'total_weight',
        'total_cbm',
        'import_photo_id',
    ];

    protected $casts = [
        'product_id'        => 'integer',
        'weight_per_carton' => 'float',
        'cbm_per_carton'    => 'float',
        'quantity'          => 'integer',
        'dollar_price'      => 'float',
         'supplier_id'          => 'integer',  // 👈 ADD
    ];

    public function ci()
    {
        return $this->belongsTo(CiDetail::class, 'ci_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id'); // 👈 used in summary
    }
}
