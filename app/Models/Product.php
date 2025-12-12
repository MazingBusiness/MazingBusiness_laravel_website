<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class Product extends Model {

  protected $guarded = ['choice_attributes'];

  protected $with = ['product_translations', 'taxes'];

  public function getTranslation($field = '', $lang = false) {
    // $lang                 = $lang == false ? App::getLocale() : $lang;
    // $product_translations = $this->product_translations->where('lang', $lang)->first();
    // return $product_translations != null ? $product_translations->$field : $this->$field;
    return $this->$field;
  }

  public function product_translations() {
    return $this->hasMany(ProductTranslation::class);
  }

  public function category() {
    return $this->belongsTo(Category::class);
  }

  public function categoryGroup() {
    return $this->belongsTo(CategoryGroup::class,'group_id');
  }
  public function brand() {
    return $this->belongsTo(Brand::class);
  }

  public function user() {
    return $this->belongsTo(User::class);
  }

  public function orderDetails() {
    return $this->hasMany(OrderDetail::class);
  }

  public function reviews() {
    return $this->hasMany(Review::class)->where('status', 1);
  }

  public function wishlists() {
    return $this->hasMany(Wishlist::class);
  }

  public function stocks() {
    return $this->hasMany(ProductWarehouse::class);
  }

  public function taxes() {
    return $this->hasMany(ProductTax::class);
  }

  public function flash_deal_product() {
    return $this->hasOne(FlashDealProduct::class);
  }

  public function warehouses() {
    return $this->belongsTo(Warehouse::class);
  }

  public function sellers() {
    return $this->belongsTo(Seller::class, ProductWarehouse::class);
  }

  public function scopePhysical($query) {
    return $query;
  }

  public function scopeDigital($query) {
    return $query;
  }

  public function sellerDetails()
  {
      return $this->belongsTo(Seller::class, 'seller_id');
  }

  public function productsApi()
  {
      return $this->hasMany(ProductsApi::class, 'part_no', 'part_no')->where('closing_stock', '>', 0); // numeric 0
  }

  public function importSupplier()
  {
      return $this->belongsTo(Supplier::class, 'supplier_id');
  }

  public function thumb_img() {
    return $this->hasOne(Upload::class,'id','thumbnail_img');
  }

  
}
