<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\CategoryCollection;
use App\Http\Resources\V2\GroupCategoryCollection;
use App\Http\Resources\V2\ProfessionCollection;
use App\Models\Category;
use App\Models\CategoryGroup;
use Cache;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller {

  public function index($parent_id = 0) {
    if (request()->has('parent_id') && is_numeric(request()->get('parent_id'))) {
      $parent_id = request()->get('parent_id');
    }
    $category = DB::table('products')
              ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
              ->where('categories.parent_id', $parent_id)
              ->where('products.part_no','!=','')
              ->where('products.current_stock','>','0')
              ->orderBy('categories.name', 'asc')
              // ->select('categories.id', 'categories.name', 'categories.slug')
              ->distinct()
              ->get();
    return Cache::remember("app.categories-$parent_id", 86400, function () use ($parent_id) {
      // return new CategoryCollection(Category::where('parent_id', $parent_id)->get());
      return new CategoryCollection($category);
    });
  }

  public function featured() {
    return Cache::remember('app.featured_categories', 86400, function () {
      $category = DB::table('products')
              ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
              ->where('categories.featured', '1')
              ->where('products.part_no','!=','')
              ->where('products.current_stock','>','0')
              ->orderBy('categories.name', 'asc')
              // ->select('categories.id', 'categories.name', 'categories.slug')
              ->distinct()
              ->get();
      // return new CategoryCollection(Category::where('featured', 1)->get());
      return new CategoryCollection($category);
    });
  }

  public function home() {
    return Cache::remember('app.home_categories', 86400, function () {
      return new GroupCategoryCollection(CategoryGroup::with('childrenCategories')->whereIn('id', json_decode(get_setting('home_categories')))->get());
    });
  }

  public function top() {
    return Cache::remember('app.top_categories', 86400, function () {
      return new CategoryCollection(Category::where('top', 1)->get());
    });
  }

  public function professions() {
    return Cache::remember('app.professions', 86400, function () {
      return new ProfessionCollection(json_decode(get_setting('home_professions')));
    });
  }
}
