<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class SubOrderDetail extends Model {

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'order_id', 'order_type', 'seller_id', 'og_product_warehouse_id', 'product_warehouse_id', 'product_id', 'variation', 'price', 'tax', 'shipping_cost', 'quantity', 'approved_quantity', 'approved_rate', 'payment_status', 'delivery_status', 'shipping_type', 'earn_point', 'cash_and_carry_item', 'new_item', 'applied_offer_id', 'complementary_item', 'offer_rewards', 'sub_order_id','order_details_id', 'challan_quantity', 'warehouse_id', 'type','sent','reallocated','pre_closed','in_transit','pre_closed_status','reallocated_from_sub_order_id','closing_qty','remarks','is_warranty','barcode','conveince_fee_percentage','conveince_fees'
  ];

  // In SubOrderDetail model
  public function sub_order_record()
  {
      return $this->belongsTo(SubOrder::class, 'sub_order_id', 'id');
  }

  public function category() {
    return $this->belongsTo(Category::class);
  }

  public function categoryGroup() {
    return $this->belongsTo(category_groups::class,'group_id');
  }
  public function brand() {
    return $this->belongsTo(Brand::class);
  }

  public function user(){
      return $this->belongsTo(User::class, 'user_id', 'id');
  }

  public function product() {
    return $this->hasMany(Product::class, 'id','product_id');
  }

  public function product_data() {
    return $this->belongsTo(Product::class,'product_id', 'id');
  }

  public function warehouse() {
    return $this->belongsTo(Warehouse::class,'warehouse_id');
  }

  public function parentSubOrder()
  {
      return $this->belongsTo(SubOrder::class, 'sub_order_id')->where('type', 'sub_order');
  }

  // public function btrSubOrder()
  // {
  //     return $this->hasOne(SubOrder::class, 'sub_order_id', 'sub_order_id')->where('type', 'btr');
  // }

  public function btrSubOrder()
  {
      return $this->hasOne(SubOrderDetail::class, 'product_id', 'product_id')
          ->whereColumn('order_id', 'order_id')
          ->where('type', 'btr');
  }

}
