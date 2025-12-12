<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager41ChallanDetail extends Model
{
    protected $table      = 'manager_41_challan_details';
    protected $primaryKey = 'id';
    public $incrementing  = true;
    protected $keyType    = 'int';
    public $timestamps    = true;

    protected $fillable = [
        'challan_id',
        'challan_no',
        'product_warehouse_id',
        'product_id',
        'user_id',
        'tax',
        'variation',
        'price',
        'quantity',
        'rate',
        'final_amount',
        'sub_order_id',
        'sub_order_details_id',
        'invoice_status',
        'inventory_status',
    ];

    public function sub_order_data()
    {
        // mirror of your existing relation intent
        return $this->belongsTo(SubOrder::class, 'sub_order_id', 'id');
    }

    public function sub_order_record()
    {
        return $this->belongsTo(SubOrder::class, 'sub_order_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // kept same signature as your current ChallanDetail
    public function product()
    {
        return $this->hasMany(Product::class, 'id', 'product_id');
    }

    public function product_data()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function challan()
    {
        return $this->belongsTo(Manager41Challan::class, 'challan_id');
    }
}
