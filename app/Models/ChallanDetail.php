<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class ChallanDetail extends Model {

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'challan_id', 'challan_no', 'product_warehouse_id', 'product_id', 'user_id', 'tax', 'variation', 'price', 'quantity', 'rate', 'final_amount', 'sub_order_id', 'sub_order_details_id','invoice_status','inventory_status','is_warranty','barcode','conveince_fee_percentage','conveince_fees'
  ];

  public function sub_order_data() {
    return $this->belongsTo(SubOrder::class, 'id', 'sub_order_id');
  }

  // In SubOrderDetail model
  public function sub_order_record()
  {
      return $this->belongsTo(SubOrder::class, 'sub_order_id', 'id');
  }

  public function category() {
    return $this->belongsTo(Category::class);
  }

  public function user(){
      return $this->belongsTo(User::class, 'user_id', 'id');
  }

  public function product() {
    return $this->hasMany(Product::class, 'id','product_id');
  }

  public function product_data() {
    return $this->belongsTo(Product::class,'product_id', 'id');
  }

  public function challan()
  {
      return $this->belongsTo(Challan::class, 'challan_id');
  }

}
