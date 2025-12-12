<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OwnBrandCategory extends Model {
  use SoftDeletes;
  protected $fillable = [
    'name', 'category_group_id','name','banner','icon','slug','meta_title','meta_keywords','meta_description','page_description','created_at','updated_at'
  ];
  protected $with = ['categoryGroup'];


  // public function category_translations() {
  //   return $this->hasMany(CategoryTranslation::class);
  // }

  public function products() {
    return $this->hasMany(Product::class);
  }

  public function categoryGroup() {
    return $this->belongsTo(OwnBrandCategoryGroup::class);
  }

}
