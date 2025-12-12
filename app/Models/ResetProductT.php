<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResetProductT extends Model
{
    protected $table = 'reset_product_ts';

    protected $fillable = [
        'product_id',
        'part_no',        
    ];

    public $timestamps = true;

    public function sub_order_details()
    {
        return $this->hasMany(SubOrderDetail::class, 'product_id', 'product_id');
    }
}
