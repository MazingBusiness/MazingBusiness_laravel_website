<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\BrandCollection;
use App\Http\Resources\V2\CategoryCollection;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryGroup;
use Cache;

class FilterController extends Controller
{
    public function categories()
    {
        //if you want to show base categories
        return Cache::remember('app.filter_categories', 86400, function () {
            return new CategoryCollection(Category::orderByRaw("
            CASE 
                    WHEN category_group_id = 1 THEN 0
                    WHEN category_group_id = 8 THEN 1
                    ELSE 2
                END, name ASC
            ")->get());
        });
    }

    public function brands()
{
    // Show only top 20 brands with caching
    return Cache::remember('app.filter_brands', 86400, function () {
        return new BrandCollection(
            Brand::with(['products' => function ($query) {
                $query->select('id', 'brand_id', 'name', 'current_stock') // Select only required columns
                      ->where('current_stock', '>', 0);
            }])
            ->select('id', 'name') // Select only necessary columns for brands
            ->whereHas('products', function ($query) {
                $query->where('current_stock', '>', 0);
            })
            ->orderBy('name', 'ASC')->get()
        );
    });
}


}
