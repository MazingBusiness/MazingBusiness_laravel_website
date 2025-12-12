<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Category;
use App\Models\CategoryGroup;

class CategoryGroup extends Model {

  protected $fillable = [
    'name'
  ];
  // public function childrenCategories() {
  //   return $this->hasMany(Category::class);
  // }

  public function childrenCategories()
  {
      return $this->hasMany(Category::class)
          ->whereHas('products_current_stock'); // Only include categories that have products with current_stock = 1
  }

  public function categoriesWithProducts()
  {
      return $this->hasMany(Category::class)
                  ->whereHas('products', function (Builder $query) {
                      $query->where('current_stock', '>', 0);
                  });
  }
  
}
