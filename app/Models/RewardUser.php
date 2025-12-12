<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardUser extends Model {
  protected $guarded  = [];
  protected $fillable = ['user_id','company_name','party_code', 'warehouse_id', 'assigned_warehouse', 'city', 'warehouse_name', 'preference', 'rewards_percentage'];

  public function user_data() {
    return $this->belongsTo(User::class,'party_code','party_code');
  }

  public function warehouse() {
    return $this->belongsTo(Warehouse::class,'warehouse_id');
  }

  public function address_with_party_code()
  {
      return $this->hasOne(Address::class, 'acc_code', 'party_code');
  }

}
