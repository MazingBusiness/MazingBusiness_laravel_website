<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class WarrantyClaim extends Model {

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'warranty_user_id', 'ticket_id', 'user_id', 'address_id', 'status','name','phone','email','gstin','aadhar_card','address','address_2','city','postal_code','warehouse_id', 'warehouse_address', 'corrier_info', 'courier_name', 'tracking_no','purchase_invoice_id','invoice_order_id'
  ];

  // Customer (WarrantyUser)
  public function user()
  {
      return $this->belongsTo(WarrantyUser::class, 'warranty_user_id');
  }

  // Line items / products in the claim
  public function details()
  {
      return $this->hasMany(WarrantyClaimDetail::class, 'warranty_claim_id');
  }

  public function sub_order_data() {
    return $this->belongsTo(SubOrder::class, 'id', 'sub_order_id');
  }

}
