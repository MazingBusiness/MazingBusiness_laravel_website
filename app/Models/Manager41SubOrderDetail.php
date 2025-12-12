<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager41SubOrderDetail extends Model
{
    protected $table = 'manager_41_sub_order_details';

    protected $fillable = [
        'order_id',
        'sub_order_id',
        'order_type',
        'seller_id',
        'og_product_warehouse_id',
        'product_warehouse_id',
        'product_id',
        'variation',
        'price',
        'tax',
        'shipping_cost',
        'closing_qty',
        'quantity',
        'approved_quantity',
        'approved_rate',
        'payment_status',
        'delivery_status',
        'shipping_type',
        'earn_point',
        'cash_and_carry_item',
        'new_item',
        'applied_offer_id',
        'complementary_item',
        'offer_rewards',
        'warehouse_id',
        'order_details_id',
        'challan_quantity',
        'type',
        'remarks',
        'reallocated',
        'reallocated_from_sub_order_id',
        'pre_closed',
        'pre_closed_status',
        'pre_closed_by',
        'in_transit',
        'challan_qty',
    ];

    public $timestamps = true;

    // --- Relationships ---

    // Add this:
    public function user(){
          return $this->belongsTo(User::class, 'user_id', 'id');
      }

    // Parent SubOrder (Manager-41)
    public function sub_order_record()
    {
        return $this->belongsTo(Manager41SubOrder::class, 'sub_order_id', 'id');
    }

    // Keep parity with your original model helpers
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function categoryGroup()
    {
        return $this->belongsTo(category_groups::class, 'group_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function product()
    {
        return $this->hasMany(Product::class, 'id', 'product_id');
    }

    public function product_data()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function parentSubOrder()
    {
        return $this->belongsTo(Manager41SubOrder::class, 'sub_order_id')->where('type', 'sub_order');
    }

    public function btrSubOrder()
    {
        return $this->hasOne(Manager41SubOrderDetail::class, 'product_id', 'product_id')
            ->whereColumn('order_id', 'order_id')
            ->where('type', 'btr');
    }
}
