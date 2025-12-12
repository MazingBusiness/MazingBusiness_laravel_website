<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class Challan extends Model {

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'challan_no', 'challan_date', 'sub_order_id', 'user_id', 'shipping_address', 'additional_info', 'shipping_address', 'place_of_suply', 'carrier_id', 'grand_total', 'warehouse', 'warehouse_id', 'remarks', 'transport_name', 'transport_id', 'transport_phone', 'shipping_address_id', 'status','invoice_status','early_payment_check','is_warranty','conveince_fee_percentage','conveince_fee_payment_check'
  ];

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id', 'id');
  }

  public function order_warehouse() 
  {
    return $this->belongsTo(Warehouse::class,'warehouse_id');
  }

  public function challan_details()
  {
    return $this->hasMany(ChallanDetail::class, 'challan_id', 'id');
  }

  public function order()
  {
    return $this->belongsTo(Order::class, 'code', 'code');
  }

   /**
   * Relationship with SubOrder model
   */
  public function sub_order()
  {
    return $this->belongsTo(SubOrder::class, 'sub_order_id', 'id');
  }

}
