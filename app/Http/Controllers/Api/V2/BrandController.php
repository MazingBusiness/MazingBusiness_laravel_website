<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\BrandCollection;
use App\Models\Brand;
use App\Utility\SearchUtility;
use Cache;
use Illuminate\Http\Request;

class BrandController extends Controller {
  public function index(Request $request) {
    $brand_query = Brand::query()->has('products');
    if ($request->name != "" || $request->name != null) {
      $brand_query->where('name', 'like', '%' . $request->name . '%');
      SearchUtility::store($request->name);
    }
    return new BrandCollection($brand_query->paginate(10));
  }

  public function top(Request $request) {
    return Cache::remember('app.top_brands', 86400, function () {
      $top10_brands = json_decode(get_setting('top10_brands'));
      return new BrandCollection(Brand::select('id', 'slug', 'logo', 'name')->whereIn('id', $top10_brands)->get());
    });
  }
}
