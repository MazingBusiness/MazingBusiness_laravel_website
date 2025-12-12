<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductWarehouse extends Model {
  protected $fillable = ['product_id', 'warehouse_id', 'seller_id', 'part_no', 'variant', 'print_name', 'hsncode', 'model_no', 'seller_sku', 'price', 'carton_price', 'piece_per_carton', 'qty', 'seller_stock', 'height', 'length', 'breadth', 'cbm', 'carton_cbm', 'weight', 'sz_category', 'sz_group', 'sz_manual_price','parent_id','variation_1','variation_2','variation_3','variation_4','value_1','value_2','value_3','value_4','is_manager_41'];

  public function warehouse() {
    return $this->belongsTo(Warehouse::class);
  }

  public function seller() {
    return $this->belongsTo(Seller::class);
  }

  public function product() {
    return $this->belongsTo(Product::class);
  }
}
