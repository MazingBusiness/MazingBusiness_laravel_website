<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;

class ProductCascadeController extends Controller
{
    public function getCategoriesByGroup($groupId)
    {
        $categories = Category::where('category_group_id', $groupId)
                        ->orderBy('name')
                        ->get(['id','name']);
        return response()->json($categories);
    }

    public function getBrandsByCategory($categoryIds)
    {
        $ids = array_filter(explode(',', (string)$categoryIds), fn($v) => $v !== '');

        $brands = Brand::whereHas('products', function ($q) use ($ids) {
                        $q->whereIn('category_id', $ids);
                    })
                    ->distinct()
                    ->orderBy('name')
                    ->get(['id','name']);

        return response()->json($brands);
    }

    public function getProductsByCategoryAndBrand(Request $request)
    {
        $categoryIds = (array) $request->input('category_ids', []);
        $brandIds    = (array) $request->input('brand_ids', []);

        $q = Product::query()
            ->select('id','name','part_no','purchase_price','hsncode','tax');

        if (!empty($categoryIds)) $q->whereIn('category_id', $categoryIds);
        if (!empty($brandIds))    $q->whereIn('brand_id', $brandIds);

        $products = $q->orderBy('name')->limit(1000)->get();

        return response()->json($products);
    }
}
