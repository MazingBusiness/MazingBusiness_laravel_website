<?php

namespace App\Models;

use App;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class OwnBrandCategoryGroup extends Model {
  use SoftDeletes;
  protected $fillable = [
    'name','banner','icon','slug','meta_title','meta_description','created_at','updated_at'
  ];
  public function childrenCategories() {
    return $this->hasMany(OwnBrandCategory::class);
  }

  public function categoriesWithProducts()
  {
      return $this->hasMany(OwnBrandCategory::class)
                  ->whereHas('products', function (Builder $query) {
                      $query->where('current_stock', '>', 0);
                  });
  }
}
