<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class SubOrder extends Model {

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'order_id', 'combined_order_id', 'order_no', 'user_id', 'guest_id', 'seller_id', 'shipping_address_id', 'shipping_address', 'billing_address_id', 'billing_address', 'additional_info', 'shipping_type', 'pickup_point_id', 'carrier_id', 'delivery_status', 'payment_type', 'manual_payment', 'manual_payment_data', 'payment_gateway_status', 'payment_status', 'payment_details', 'grand_total', 'payable_amount', 'payment_discount','coupon_discount', 'code', 'tracking_code', 'date', 'viewed', 'delivery_viewed','order_from','payment_status_viewed','commission_calculated','applied_offer_id','offer_rewards','type','warehouse_id','other_details','status','transport_table_id','transport_id','transport_name','transport_phone','transport_remarks','sub_order_id','sub_order_user_name','early_payment_check','is_warranty','conveince_fee_percentage','conveince_fee_payment_check'
  ];
  public function category() {
    return $this->belongsTo(Category::class);
  }

  public function categoryGroup() {
    return $this->belongsTo(category_groups::class,'group_id');
  }
  public function brand() {
    return $this->belongsTo(Brand::class);
  }

  public function user()
  {
      return $this->belongsTo(User::class, 'user_id', 'id');
  }

  public function order_warehouse() {
    return $this->belongsTo(Warehouse::class,'warehouse_id');
  }

  public function sub_order_details()
  {
      return $this->hasMany(SubOrderDetail::class, 'sub_order_id', 'id');
  }

  public function order()
  {
      return $this->belongsTo(Order::class, 'code', 'code');
  }

  public function shippingAddress()
  {
      return $this->belongsTo(Address::class, 'shipping_address_id');
  }

  



}
