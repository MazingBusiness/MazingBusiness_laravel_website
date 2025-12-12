<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class WarrantyClaimDetail extends Model {

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'warranty_claim_id', 'warranty_user_id', 'ticket_id', 'barcode','product_id','part_number','invoice_no','purchase_date','warranty_product_part_number','warranty_product_id','warranty_duration','attachment_invoice','attatchment_warranty_card','approval_status'
  ];

  public function claim()
  {
      return $this->belongsTo(WarrantyClaim::class, 'warranty_claim_id');
  }

  public function user()
  {
      return $this->belongsTo(WarrantyUser::class, 'warranty_user_id');
  }
 

  public function product()
  {
      return $this->belongsTo(Product::class, 'product_id');
  }

  
  public function warrantyProduct()
  {
      return $this->belongsTo(Product::class, 'warranty_product_id');
  }

  public function sub_order_data() {
    return $this->belongsTo(SubOrder::class, 'id', 'sub_order_id');
  }

}
