<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardPointsOfUser extends Model {
  protected $guarded  = [];
  protected $fillable = ['party_code','invoice_no', 'rewards_from', 'warehouse_id', 'warehouse_name', 'rewards', 'credit_rewards', 'remaining_rewards', 'reward_complete_status', 'dr_or_cr','voucher_date','canceled_on','cancel_reason','notes','is_processed','msg_id'];

  public function user_data() {
    return $this->belongsTo(User::class,'party_code','party_code');
  }

  public function warehouse() {
    return $this->belongsTo(Warehouse::class,'warehouse_id');
  }

  public function get_user_addresses()
  {
      return $this->hasOne(Address::class, 'acc_code', 'party_code');
  }

  public function cloudResponses()
  {
      return $this->hasMany(CloudResponse::class, 'msg_id', 'msg_id');
  }

  /**
   * Latest WhatsApp status (1 row) by created_at/id
   */
  public function latestCloudResponse()
  {
      return $this->hasOne(CloudResponse::class, 'msg_id', 'msg_id')->latestOfMany();
  }

  public function getWaStatusAttribute()
  {
      return optional($this->latestCloudResponse)->status;
  }

}
