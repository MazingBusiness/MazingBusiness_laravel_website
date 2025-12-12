<?php

namespace App\Models;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OwnBrandCart extends Model {
  protected $guarded  = [];
  protected $fillable = ['owner_id', 'user_id', 'customer_id', 'address_id', 'product_id', 'brand', 'brand_name', 'unit_price', 'tax', 'shipping_cost', 'shipping_type', 'pickup_point', 'carrier_id', 'discount', 'product_referral_code', 'coupon_code', 'coupon_applied', 'quantity', 'total_price', 'currency', 'created_at', 'updated_at'];
  public function user() {
    return $this->belongsTo(User::class);
  }

  public function product() {
    return $this->belongsTo(Product::class);
  }

  public function address() {
    return $this->belongsTo(Address::class);
  }
}
