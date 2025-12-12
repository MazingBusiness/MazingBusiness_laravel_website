<?php

namespace App\Models;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OwnBrandOrderApproval extends Model {
  protected $guarded  = [];
  protected $fillable = ['customer_id', 'party_code', 'order_id', 'order_code', 'status', 'details', 'attachment'];

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
