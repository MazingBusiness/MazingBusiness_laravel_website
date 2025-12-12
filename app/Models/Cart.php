<?php

namespace App\Models;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model {
  protected $guarded  = [];
  protected $fillable = ['address_id', 'price', 'tax', 'shipping_cost', 'discount', 'product_referral_code', 'coupon_code', 'coupon_applied', 'quantity', 'user_id', 'customer_id', 'temp_user_id', 'owner_id', 'product_id', 'applied_offer_id', 'variation', 'is_carton','is_offer_product','cash_and_carry_item','complementary_item','offer_rewards','is_manager_41'];

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
