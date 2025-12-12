<?php

namespace App\Models;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OwnBrandOrderDetail extends Model {
  use SoftDeletes;
  protected $guarded  = [];
  protected $fillable = ['order_id','order_code', 'product_id', 'name', 'slug', 'brand', 'brand_name', 'unit_price', 'tax', 'quantity', 'payment_status', 'total_price', 'delivery_status', 'purchase_time_unit_price', 'purchase_time_quantity', 'purchase_time_brand', 'purchase_time_brand_name','comment','days_of_delivery'];

  public function user() {
    return $this->belongsTo(User::class);
  }

  public function product() {
    return $this->belongsTo(OwnBrandProduct::class);
  }

  public function address() {
    return $this->belongsTo(Address::class);
  }

  public function order() {
    return $this->belongsTo(OwnBrandOrder::class);
  }

}
