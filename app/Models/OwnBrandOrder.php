<?php

namespace App\Models;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OwnBrandOrder extends Model {
  protected $guarded  = [];
  protected $fillable = ['user_id', 'customer_id', 'address_id', 'shipping_address', 'shipping_type', 'shipping_cost', 'pickup_point_id', 'carrier_id', 'delivery_status', 'payment_type', 'manual_payment', 'manual_payment_data', 'payment_status', 'payment_details', 'currency', 'grand_total', 'advance_amount', 'payment_discount', 'coupon_discount', 'order_code', 'tracking_code', 'delivery_viewed', 'payment_status_viewed', 'commission_calculated'];

  public function user() {
    return $this->belongsTo(User::class, 'customer_id');
  }

  public function product() {
    return $this->belongsTo(Product::class);
  }

  public function address() {
    return $this->belongsTo(Address::class);
  }

  public function orderDetails()
  {
      return $this->hasMany(OwnBrandOrderDetail::class, 'order_code', 'order_code');
  }
}
