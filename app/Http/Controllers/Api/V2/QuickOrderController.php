<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\BrandListCollection;
use App\Http\Resources\V2\CategoryGroupCollection;
use App\Http\Resources\V2\CategoryListCollection;
use App\Http\Resources\V2\QuickOrderProductCollection;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Product;
use App\Models\User;
use Auth;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QuickOrderController extends Controller
{
    public function quickOrderCategoryGroupList_back()
    {
        // $categoryGroups = CategoryGroup::with(['categoriesWithProducts'])
        //     ->get()
        //     ->filter(function ($categoryGroup) {
        //         return $categoryGroup->categoriesWithProducts->isNotEmpty();
        //     })->sortBy('name');
        
        // $category_menu = DB::table('products')
        //     ->leftJoin('category_groups', 'products.group_id', '=','category_groups.id' )
        //     ->where('category_groups.featured', 1)
        //     ->where('products.part_no','!=','')
        //     ->where('products.current_stock','>','0')
        //     ->orderBy('category_groups.name', 'asc')
        //     ->select('category_groups.*')
        //     ->distinct()
        //     ->get();
        $categoryGroups = CategoryGroup::with(['categoriesWithProducts' => function($query) {
            $query->whereHas('products', function($query) {
                $query->where('products.part_no', '!=', '')
                        ->where('products.current_stock', '>', 0);
            });
        }])
        // ->where('featured', 1)
        ->get()
        ->filter(function ($categoryGroup) {
            return $categoryGroup->categoriesWithProducts->isNotEmpty();
        })
        ->sortBy('name');
        return new CategoryGroupCollection($categoryGroups);
    }

    public function quickOrderCategoryGroupList()
   {
    // Fetch the category groups with their associated categories and products
    $categoryGroups = CategoryGroup::with(['categoriesWithProducts' => function($query) {
        $query->whereHas('products', function($query) {
            $query->where('products.part_no', '!=', '')
                  ->where('products.current_stock', '>', 0);
        });
    }])
    ->get()
    ->filter(function ($categoryGroup) {
        return $categoryGroup->categoriesWithProducts->isNotEmpty();
    })
    // Combine both priority and alphabetical sorting into one step
    ->sortBy(function ($categoryGroup) {
        $priority = 2;  // Default priority for other categories
        if ($categoryGroup->id == 1) {
            $priority = 0;  // Highest priority for group ID 1
        } elseif ($categoryGroup->id == 8) {
            $priority = 1;  // Second priority for group ID 8
        }

        // Combine priority and alphabetical sorting
        return [$priority, strtolower($categoryGroup->name)];
    });

    return new CategoryGroupCollection($categoryGroups);
  }

    


    public function quickOrderCategories(Request $request)
    {
        if ($request->category_group_id) {
            $category_group = CategoryGroup::find($request->category_group_id);

            //  $category_items = Category::select('categories.id','categories.parent_id','categories.name',DB::raw("COALESCE(CONCAT('https://mazingbusiness.com/public/', uploads.file_name), 'https://mazingbusiness.com/public/assets/img/placeholder.jpg') as logo_url"))
            //     ->leftJoin('uploads', 'categories.banner', '=', 'uploads.id')
            //     ->with('parentCategory:id,name')
            //     ->where('categories.category_group_id', $category_group->id)
            //     ->get();

            $category_items = Category::select(
                'categories.id',
                'categories.parent_id',
                'categories.name',
                'categories.slug',
                DB::raw("COALESCE(CONCAT('https://mazingbusiness.com/public/', uploads.file_name), 'https://mazingbusiness.com/public/assets/img/placeholder.jpg') as logo_url")
            )
            ->leftJoin('uploads', 'categories.banner', '=', 'uploads.id')
            ->leftJoin('products', 'products.category_id', '=', 'categories.id')
            ->where('categories.category_group_id', $category_group->id)
            // ->where(function ($query) use ($category_id, $category_group_id) {
            //     $query->where('categories.category_group_id', $category_group->id)
            //           ->orWhere('categories.category_group_id', $category_id);
            // })
            ->where(function ($query) {
                $query->where('products.part_no', '!=', '')
                      ->where('products.current_stock', '>', 0);
            })
            ->orderBy('categories.name', 'asc')
            ->distinct()
            ->get();
            $category_items = $category_items->sortBy([
                            ['parentCategory.name', 'asc'],
                            ['name', 'asc'],
                        ]);
            $responce = ['data' => $category_items,'success' => true,'status' => 200];
            return $responce;
        } else {
            $category_items = Category::select( 'categories.id', 'categories.parent_id','categories.name',DB::raw("COALESCE(CONCAT('https://mazingbusiness.com/public/', uploads.file_name), 'https://mazingbusiness.com/public/assets/img/placeholder.jpg') as logo_url"))
            ->leftJoin('uploads', 'categories.banner', '=', 'uploads.id')
            ->with('parentCategory:id,name')
            ->where('categories.level', 1)
            ->get();
            $category_items = $category_items->sortBy([
                    ['parentCategory.name', 'asc'],
                    ['name', 'asc'],
                ]);
            $responce = ['data' => $category_items,'success' => true,'status' => 200];
            return $responce;
        }
    }

    public function quickOrderBrands(Request $request)
    {        
        // $products = Product::select('id', 'brand_id')->where('published', true)->where('current_stock', 1)->where('approved', true)->orderBy('name', 'asc')->get();
        // $brand_ids = $products->pluck('brand_id');
        // $brands = Brand::selectRaw('id, UPPER(name) as name')->whereIn('id', $brand_ids)->orderBy('name', 'asc')->get();
        // return new BrandListCollection($brands);

        $category_group_id = (array) $request->input('category_group_id');
        $category_id = (array) $request->input('category_id');
        if(count($category_group_id) > 0 AND count($category_id) > 0){
            $category_group_ids_str = implode(',', $category_group_id);
            $category_ids_str = implode(',', $category_id);
            $query = "SELECT DISTINCT `brands`.id,`brands`.name, CONCAT('https://mazingbusiness.com/public/', uploads.file_name) as logo_url
                    FROM `products`
                    INNER JOIN `brands` ON `products`.`brand_id` = `brands`.`id`
                    INNER JOIN `uploads` ON `brands`.`logo` = `uploads`.`id`
                    WHERE `products`.`group_id` IN ($category_group_ids_str)
                    AND `products`.`category_id` IN ($category_ids_str)
                    AND `products`.`published` = 1
                    AND `products`.`current_stock` > 0
                    AND `products`.`approved` = 1";
            $brands = DB::select($query);
            // return response()->json($brands);
        }elseif(count($category_group_id) > 0 AND count($category_id) <= 0){
            $category_group_ids_str = implode(',', $category_group_id);
            $query = "SELECT DISTINCT `brands`.id,`brands`.name, CONCAT('https://mazingbusiness.com/public/', uploads.file_name) as logo_url
                    FROM `products`
                    INNER JOIN `brands` ON `products`.`brand_id` = `brands`.`id`
                    INNER JOIN `uploads` ON `brands`.`logo` = `uploads`.`id`
                    WHERE `products`.`group_id` IN ($category_group_ids_str)
                    AND `products`.`published` = 1
                    AND `products`.`current_stock` > 0
                    AND `products`.`approved` = 1";
            $brands = DB::select($query);
            // return response()->json($brands);
        }elseif(count($category_group_id) <= 0 AND count($category_id) > 0){
            $category_ids_str = implode(',', $category_id);
            $query = "SELECT DISTINCT `brands`.id,`brands`.name, CONCAT('https://mazingbusiness.com/public/', uploads.file_name) as logo_url
                    FROM `products`
                    INNER JOIN `brands` ON `products`.`brand_id` = `brands`.`id`
                    INNER JOIN `uploads` ON `brands`.`logo` = `uploads`.`id`
                    WHERE `products`.`category_id` IN ($category_ids_str)
                    AND `products`.`published` = 1
                    AND `products`.`current_stock` > 0
                    AND `products`.`approved` = 1";
            $brands = DB::select($query);
            // return response()->json($brands);
        }else{
            // $user_warehouse_id = (Auth::check() && Auth::user()->user_type == 'customer') ? Auth::user()->warehouse_id : 1;
            $productQuery  = Product::query()->join('categories', 'products.category_id', '=', 'categories.id')->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');
            $products = $productQuery->select('products.id', 'current_stock', 'brand_id', 'category_groups.name  AS group_name' ,'categories.name  AS category_name' ,'group_id', 'category_id', 'products.name', 'thumbnail_img', 'products.slug', 'min_qty','mrp')->where('published', true)->where('current_stock','>', 0)->where('approved', true)->orderBy('category_groups.name', 'asc')->orderBy('categories.name', 'asc')->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END, CAST(products.mrp AS UNSIGNED) ASC")->get();
            // $products = $this->processProducts($products, $user_warehouse_id);

            $brand_ids = $products->pluck('brand_id');
            $brands = Brand::select('brands.id', 'brands.name', DB::raw("CONCAT('https://mazingbusiness.com/public/', uploads.file_name) as logo_url"))
                        ->join('uploads', 'brands.logo', '=', 'uploads.id')
                        ->whereIn('brands.id', $brand_ids)
                        ->get();
            // return response()->json($brands);
        }
        return ['data' => $brands,'success' => true,'status' => 200];
    }

    public function quickOrderProducts(Request $request)
    {
        // $user_warehouse_id = $request->has('user_warehouse_id') && $request->user_warehouse_id != null ? $request->user_warehouse_id : 1;

        // if ($request->category_group_id && $request->category_group_id != null && false) {
            // $category_group = CategoryGroup::find($request->category_group_id);
            // $categories = Cache::remember('group_' . $category_group->id . '_category_items', 3600, function () use ($category_group) {
            //     $category_items = Category::select('id', 'parent_id', 'name')->with('parentCategory:id,name')->where('category_group_id', $category_group->id)->get();
            //     $category_items = $category_items->sortBy([
            //         ['parentCategory.name', 'asc'],
            //         ['name', 'asc'],
            //     ]);
            //     return $category_items;
            // });
            // $products = Cache::remember('group_' . $category_group->id . '_products_wh_' . $user_warehouse_id, 3600, function () use ($categories, $user_warehouse_id) {
            //     $final_products_list = collect([]);
            //     $products = Product::select('id', 'brand_id', 'category_id', 'name', 'thumbnail_img', 'slug', 'discount', 'discount_type', 'discount_start_date', 'discount_end_date', 'min_qty')->with('stocks')->withSum('stocks', 'qty')->withSum('stocks', 'seller_stock')->whereIn('category_id', $categories->pluck('id'))->where('published', true)->where('approved', true)->whereNotNull('photos')->orderBy('name', 'asc')->get();
            //     $products = $products->filter(function ($value) {
            //         return ($value->stocks_sum_qty + $value->stocks_sum_seller_stock) > $value->min_qty;
            //     });
            //     $products = $this->processProducts($products, $user_warehouse_id);
            //     foreach ($categories as $category) {
            //         $final_products_list = $final_products_list->merge($products->where('category_id', $category->id));
            //     }
            //     return $final_products_list;
            // });
        // } else {
            // $final_products_list = collect([]);
            // Product Name filter
            // if ($srch_prod_name != '') {
            //     $prod_name = preg_replace('/[\'\"]/', '', $srch_prod_name); 
            //     $productQuery->whereRaw("REPLACE(REPLACE(`products`.`name`, '\"', ''), '\'', '') LIKE ?", ['%' . $prod_name . '%'])->orWhere('part_no','like','%' . $prod_name . '%');
            // }
            // $products            = $productQuery->select('id', 'brand_id', 'category_id', 'name', 'thumbnail_img', 'slug', 'min_qty','mrp')->with('stocks')->withSum('stocks', 'qty')->withSum('stocks', 'seller_stock')->where('published', true)->where('approved', true)->orderBy('name', 'asc')->get();
            // $products            = $products->filter(function ($value) {
            //     return true;
            // });
            // $products = $this->processProducts($products, $user_warehouse_id);
            // foreach ($categories as $category) {
            //     $final_products_list = $final_products_list->merge($products->where('category_id', $category->id));
            // }
            // return $products;
        // }
        // Categories filter
        // if ($request->categories && $request->categories !== 'null') {
            // $categories = explode(',', $request->categories);
            // $products = $products->whereIn('category_id', $categories)->values();
        // }
        // Brands filter
        // if ($request->brands && $request->brands !== 'null') {
            // $brands = explode(',', $request->brands);
            // $products = $products->whereIn('brand_id', $brands)->values();
        // }

        //query;

        if($request->header('x-customer-id') !== null){
            $user_id = $request->header('x-customer-id');
        }elseif(Auth::check()){
            $user_id = auth()->user()->id;
        }else{
            $user_id = "";
        }
        
        $category_ids = [];
        $brand_ids = [];
        $category_group_ids = [];
        $searchQuery = '';

        if ($request['group'] != '') {
            $category_group_ids = explode(',', $request['group']);
        }

        if ($request['categories'] != '') {
            $category_ids = explode(',', $request['categories']);
        }

        if ($request['brands'] != '') {
            $brand_ids = explode(',', $request['brands']);
        }
    
        if ($request['query'] != '') {
            $searchQuery = $request['query'];
        }


        // pagination
        $productQuery = Product::query()->join('categories', 'products.category_id', '=', 'categories.id')->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');
        // $productQuery = Product::query()->with('category')->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');

        
        if (!empty($category_group_ids)) {
            $productQuery->whereIn('category_groups.id', $category_group_ids);
        }
        
        if (!empty($category_ids)) {
            $productQuery->whereIn('categories.id', $category_ids);
        }
        
        if (!empty($brand_ids)) {
            $productQuery->whereIn('brand_id', $brand_ids);
        }

        if ($searchQuery != '') {
            $productQuery->where('products.name', 'LIKE', '%' . $searchQuery . '%');
        }
        $productQuery->where('products.current_stock','>' ,'0');
        // $products = $productQuery->select('products.id', 'brand_id', 'category_groups.name  AS group_name' ,'categories.name  AS category_name' ,'group_id', 'category_id', 'products.name', 'thumbnail_img', 'products.slug', 'min_qty','mrp')->where('published', true)->where('current_stock', 1)->where('approved', true)->orderBy('category_groups.name', 'asc')->orderBy('categories.name', 'asc')->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END, CAST(products.mrp AS UNSIGNED) ASC")->get();    
		$products = $productQuery->select('products.id', 'brand_id', 'category_groups.name  AS group_name' ,'categories.name  AS category_name' ,'group_id', 'category_id', 'products.name', 'thumbnail_img', 'products.slug', 'min_qty','mrp')->where('published', true)->where('current_stock', 1)->where('approved', true)->orderByRaw("CASE 
        WHEN category_groups.id = 1 THEN 0 
        WHEN category_groups.id = 8 THEN 1 
        ELSE 2 END")->orderBy('category_groups.name', 'asc')->orderBy('categories.name', 'asc')
        ->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0
                  WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                      SELECT part_no COLLATE utf8mb3_general_ci 
                      FROM products_api
                  ) THEN 1
                  ELSE 2 END")
        ->orderByRaw("CAST(products.mrp AS UNSIGNED) ASC")
        ->get(); 

        $per_page = $request['per_page'] != '' ? $request['per_page'] : 30;
        $products = $products->paginate($per_page);

        // print_r(auth()->user()); die;
        // if($request->header('x-customer-id') !== null){
        //     $user_id = $request->header('x-customer-id');
        // }elseif(Auth::check()){
        //     $user_id = Auth::user()->id;
        // }else{
        //     $user_id = "";
        // }

        $products = $this->processProducts($products,$user_id);
        return new QuickOrderProductCollection($products, $user_id);
    }

    private function processProducts($products,$user_id)
    {
        foreach ($products as $product) {
            $price = 0;
            $discount = 0;

            $user = User::where('id',$user_id)->first();

            if ($user) {
                $discount = $user->discount;
            } 

            if(!is_numeric($discount) || $discount == 0) {
                $discount = 20;
            }

            $product_mrp = Product::where('id', $product->id)->select('mrp')->first();
            if ($product_mrp) {
                $price = $product_mrp->mrp;
            } else {
                $price = 0;
            }
            
            if (!is_numeric($price)) {
                $price = 0;
            }

            $price = $price * ((100 - $discount) / 100);
            if ($price < 50) {
					$price = number_format($price, 2, '.', '');
				} else {
					$price = ceil($price);
				}
            $product->home_discounted_base_price = $price;
            
            $price = $price * 131.6 / 100;
            $product->home_base_price = $price;
			if ($price < 50) {
					$price = number_format($price, 2, '.', '');
				} else {
					$price = ceil($price);
				}
            
            $discount_applicable = false;
            if ($product->discount_start_date == null) {
                $discount_applicable = true;
            } elseif (
                strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
                strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date
            ) {
                $discount_applicable = true;
            }
            if ($discount_applicable) {
                if ($product->discount_type == 'percent') {
                    $price -= ($price * $product->discount) / 100;
                } elseif ($product->discount_type == 'amount') {
                    $price -= $product->discount;
                }
            }

            $tax = 0;
            foreach ($product->taxes as $product_tax) {
                if ($product_tax->tax_type == 'percent') {
                    $tax += ($price * $product_tax->tax) / 100;
                } elseif ($product_tax->tax_type == 'amount') {
                    $tax += $product_tax->tax;
                }
            }
            // $product->home_discounted_base_price = $price + $tax;
        }
        // print_r($products);die;
        return $products;
    }
}
