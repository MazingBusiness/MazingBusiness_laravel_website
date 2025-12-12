<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model {

  protected $with = [];

  protected $fillable = [
    'name'
  ];
  // protected $with = ['brand_translations'];
  public function getTranslation($field = '', $lang = false) {
    $lang              = $lang == false ? App::getLocale() : $lang;
    $brand_translation = $this->brand_translations->where('lang', $lang)->first();
    return $brand_translation != null ? $brand_translation->$field : $this->$field;
  }

  public function brand_translations() {
    return $this->hasMany(BrandTranslation::class);
  }

  public function products() {
    return $this->hasMany(Product::class);
  }
  public function get_products() {
    return $this->belongsTo(Product::class);
  }

}
