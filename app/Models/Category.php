<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Model;

class Category extends Model {
  protected $fillable = [
    'name', 'category_group_id'
  ];
  protected $with = ['category_translations', 'categoryGroup'];

  public function getTranslation($field = '', $lang = false) {
    $lang                 = $lang == false ? App::getLocale() : $lang;
    $category_translation = $this->category_translations->where('lang', $lang)->first();
    return $category_translation != null ? $category_translation->$field : $this->$field;
  }

  public function category_translations() {
    return $this->hasMany(CategoryTranslation::class);
  }

  public function products() {
    return $this->hasMany(Product::class);
  }

  public function products_current_stock() {
    return $this->hasMany(Product::class)->where('current_stock',1);
  }

  public function classified_products() {
    return $this->hasMany(CustomerProduct::class);
  }

  public function categories() {
    return $this->hasMany(Category::class, 'parent_id');
  }

  public function childrenCategories() {
    return $this->hasMany(Category::class, 'parent_id')->with('categories');
  }

  public function childrenCategoriesMini() {
    return $this->hasMany(Category::class, 'parent_id')->with('categories:id,parent_id,name,slug');
  }

  public function categoryGroup() {
    return $this->belongsTo(CategoryGroup::class);
  }

  public function parentCategory() {
    return $this->belongsTo(Category::class, 'parent_id');
  }

  public function attributes() {
    return $this->belongsToMany(Attribute::class);
  }

  public function products_current_stock_41()
  {
      return $this->hasMany(Product::class)
          // ->where('current_stock', 1)
          ->where('is_manager_41', 1);
  }

  
}
