<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model {
  protected $fillable = [
    'user_id', 'customer_id', 'type', 'code', 'description', 'details', 'discount', 'discount_type', 'start_date', 'end_date', 'max_usage_count', 'new_user_only',
  ];

  public function user() {
    return $this->belongsTo(User::class);
  }
}
