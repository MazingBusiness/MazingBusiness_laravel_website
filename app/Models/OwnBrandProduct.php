<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class OwnBrandProduct extends Model {
  protected $fillable = [
    'part_no','alias_name','mrp','name','group_id','category_id','photos','thumbnail_img','video_link','description','approved','min_order_qty_1','min_order_qty_2','weight','country_of_origin','compatable_model','cbm','inr_bronze','inr_silver','inr_gold','doller_bronze','doller_silver','doller_gold','meta_title','meta_keywords','meta_description','meta_img','slug','rating','barcode','created_at','updated_at'
  ];
  protected $guarded = ['choice_attributes'];

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
    return $this->belongsTo(OwnBrandCategory::class);
  }

  public function categoryGroup() {
    return $this->belongsTo(OwnBrandCategoryGroup::class,'group_id');
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
}
