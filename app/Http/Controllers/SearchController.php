<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\AttributeCategory;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Color;
use App\Models\Product;
use App\Models\Search;
use App\Models\Warehouse;
use App\Models\OwnBrandCategory;
use App\Models\OwnBrandCategoryGroup;
use App\Models\OwnBrandProduct;
use App\Models\SubOrder;
use App\Models\Order;

use App\Models\Manager41Order;
use App\Models\Manager41SubOrder;

use App\Models\User;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\OfferCombination;
use Carbon\Carbon;

use App\Utility\CategoryUtility;
use Auth;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\Artisan;
class SearchController extends Controller {



  private function isActingAs41Manager(): bool
  {
      // 1) Agar impersonation chal raha hai to staff user ko check karo
      $user = null;
      if (session()->has('staff_id')) {
          $user = User::find((int) session('staff_id'));
      }

      // 2) Warna current logged-in user
      if (!$user) {
          $user = Auth::user();
      }
      if (!$user) {
          return false;
      }

      // 3) Normalize and match
      $title = strtolower(trim((string) $user->user_title));
      $type  = strtolower(trim((string) $user->user_type));

      if ($type === 'manager_41') {
          return true;
      }

      $aliases = ['manager_41'];
      return in_array($title, $aliases, true);
  }

public function showGroupCategoryProducts(Request $request, $category_group_id = null, $brand_id = null) {

    if ($request->view && (session('view') != $request->view)) {
        session(['view' => $request->view]);
    } else {
        session(['view' => 'grid']);
    }

    $inhouse = $request->inhouse;
    $query = $request->keyword;
    $generic_name = $request->generic_name;
    $type = $request->type;
    $sort_by = $request->sort_by;
    $min_price = $request->min_price;
    $max_price = $request->max_price;
    $seller_id = $request->seller_id;
    $brands = $request->has('brands') ? array_filter($request->brands) : [];
    $attributes = [];
    $selected_attribute_values = [];
    $selected_color = null;

    $conditions = [];
    $products = Product::where($conditions)
        ->where('part_no', '!=', '')
        ->where('current_stock', '>', '0');

    // Apply Inhouse Filter
    if ($inhouse === '1') {
        $products->whereIn('part_no', function ($query) {
            $query->select(DB::raw('part_no COLLATE utf8mb3_general_ci'))->from('products_api');
        });
    } elseif ($inhouse === '2') {
        $products->whereNotIn('part_no', function ($query) {
            $query->select(DB::raw('part_no COLLATE utf8mb3_general_ci'))->from('products_api');
        });
    }

    // Use $category_group_id to fetch all related categories and their products
    if ($category_group_id != null) {
        $category_ids = Category::where('category_group_id', $category_group_id)->pluck('id')->toArray();
        $products->whereIn('category_id', $category_ids);

        $attribute_ids = AttributeCategory::whereIn('category_id', $category_ids)->pluck('attribute_id')->toArray();
        $attributes = Attribute::whereIn('id', $attribute_ids)->where('type', '!=', 'data')->get();
    }

    if ($generic_name != null) {
        $products->where('generic_name', 'like', '%' . $generic_name . '%');
    }

    if ($brand_id != null) {
        $products->where('brand_id', $brand_id);
    } elseif ($brands) {
        $brand_ids = Brand::whereIn('slug', $brands)->pluck('id');
        $products->whereIn('brand_id', $brand_ids);
    }

    if ($type) {
        $category_ids = explode(',', Category::find($request->category_id)->linked_categories);
        if ($type == 'accessories') {
            $category_ids = Category::whereIn('id', $category_ids)->where('category_group_id', 1)->pluck('id');
        } else {
            $category_ids = Category::whereIn('id', $category_ids)->where('category_group_id', 5)->pluck('id');
        }
        $products->whereIn('category_id', $category_ids);
    }

    if ($query != null) {
        $products->where(function ($q) use ($query) {
            foreach (explode(' ', trim($query)) as $word) {
                $q->where('name', 'like', '%' . $word . '%')
                    ->orWhere('tags', 'like', '%' . $word . '%')
                    ->orWhereHas('product_translations', function ($q) use ($word) {
                        $q->where('name', 'like', '%' . $word . '%');
                    })
                    ->orWhereHas('stocks', function ($q) use ($word) {
                        $q->where('variant', 'like', '%' . $word . '%');
                    });
            }
        });
    }

    if ($request->has('color')) {
        $str = '"' . $request->color . '"';
        $products->where('colors', 'like', '%' . $str . '%');
        $selected_color = $request->color;
    }

    if ($request->has('selected_attribute_values')) {
        $selected_attribute_values = $request->selected_attribute_values;
        $products->where(function ($query) use ($selected_attribute_values) {
            foreach ($selected_attribute_values as $value) {
                $str = '"' . $value . '"';
                $query->orWhere('choice_options', 'like', '%' . $str . '%');
            }
        });
    }

    if ($min_price != null && $max_price != null) {
        $products->where('unit_price', '>=', $min_price)->where('unit_price', '<=', $max_price);
    }
	
	

    // Calculate min and max unit price
    $min_total = $products->min('unit_price') !== null ? floor($products->min('unit_price')) : 0;
    $max_total = $products->max('unit_price') !== null ? ceil($products->max('unit_price')) : 0;
	

    // Sorting Logic
    switch ($sort_by) {
          case 'newest':
              $products->orderByRaw("
                  CASE
                      WHEN products.name LIKE '%opel%' AND products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 0
                      WHEN products.name LIKE '%opel%' THEN 1
                      WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 2
                      ELSE 3
                  END, 
                  CAST(products.mrp AS UNSIGNED) ASC
              ");
              $products->orderBy('created_at', 'desc');
              break;

          case 'oldest':
              $products->orderByRaw("
                  CASE
                      WHEN products.name LIKE '%opel%' AND products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 0
                      WHEN products.name LIKE '%opel%' THEN 1
                      WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 2
                      ELSE 3
                  END, 
                  CAST(products.mrp AS UNSIGNED) ASC
              ");
              $products->orderBy('created_at', 'asc');
              break;

          case 'price-asc':
              $products->orderByRaw("
                  CASE
                      WHEN products.name LIKE '%opel%' AND products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 0
                      WHEN products.name LIKE '%opel%' THEN 1
                      WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 2
                      ELSE 3
                  END, 
                  CAST(products.mrp AS UNSIGNED) ASC
              ");
              $products->orderBy('mrp', 'asc');
              break;

          case 'price-desc':
              $products->orderByRaw("
                  CASE
                      WHEN products.name LIKE '%opel%' AND products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 0
                      WHEN products.name LIKE '%opel%' THEN 1
                      WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 2
                      ELSE 3
                  END, 
                  CAST(products.mrp AS UNSIGNED) DESC
              ");
              $products->orderBy('mrp', 'desc');
              break;

          default:
              $products->orderByRaw("
                  CASE
                      WHEN products.name LIKE '%opel%' AND products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 0
                      WHEN products.name LIKE '%opel%' THEN 1
                      WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 2
                      ELSE 3
                  END
              ")
              //->orderBy('products.name', 'ASC') // Sort prioritized items alphabetically
              ->orderByRaw("CAST(products.mrp AS UNSIGNED) ASC"); 
              break;
      }
    $products = $products->with('taxes')->paginate(42)->appends(request()->query());

    $categoryGroup = DB::table('products')
        ->leftJoin('category_groups', 'products.group_id', '=', 'category_groups.id')
        // ->where('category_groups.featured', 1)
        ->where('products.part_no', '!=', '')
        ->where('products.current_stock', '>', '0')
         ->orderByRaw("
        CASE
            WHEN category_groups.id = 1 THEN 0 -- Power Tools (ID = 1)
            WHEN category_groups.id = 8 THEN 1 -- Cordless Tools (ID = 8)
            ELSE 2
        END
    ")
        ->orderBy('category_groups.name', 'asc')
        ->select('category_groups.*')
        ->distinct()
        ->get();

    $catProducts = Product::whereIn('category_id', $category_ids)
        ->where('part_no', '!=', '')
        ->where('current_stock', '>', '0')
        ->get();

    $id_brand = $catProducts->pluck('brand_id')->unique()->toArray();

    // echo "<pre>";
    // print_r($products->toArray());
    // die();

    return view('frontend.show_group_category_products', compact(
        'categoryGroup',
        'products',
        'query',
        'category_group_id',
        'brand_id',
        'brands',
        'sort_by',
        'seller_id',
        'min_price',
        'max_price',
        'min_total',
        'max_total',
        'attributes',
        'selected_attribute_values',
        'selected_color',
        'id_brand'
    ));
}



  
  public function index(Request $request, $category_id = null, $brand_id = null) {

  
    if ($request->view && (session('view') != $request->view)) {
      session(['view' => $request->view]);
    }else{
      session(['view' => 'grid']);
    }
    $inhouse                   = $request->inhouse;
    $query                     = $request->keyword;
    $generic_name              = $request->generic_name;
    $type                      = $request->type;
    $sort_by                   = $request->sort_by;
    $min_price                 = $request->min_price;
    $max_price                 = $request->max_price;
    $seller_id                 = $request->seller_id;
    $brands                    = $request->has('brands') ? array_filter($request->brands) : [];
    $attributes                = [];
    $selected_attribute_values = array();
    $selected_color            = null;

    $conditions = [];
    // $products   = filter_products(Product::where($conditions))->whereNotNull('photos');
    $products   = Product::where($conditions)->where('part_no','!=','')->where('current_stock','>','0');

    // Apply Inhouse Filter - edited by dipak start
    if ($inhouse === '1') {
        // Include only inhouse products
        $products->whereIn('part_no', function($query) {
            $query->select(DB::raw('part_no COLLATE utf8mb3_general_ci'))->from('products_api');
        });
    } elseif ($inhouse === '2') {
        // Exclude inhouse products
        $products->whereNotIn('part_no', function($query) {
            $query->select(DB::raw('part_no COLLATE utf8mb3_general_ci'))->from('products_api');
        });
    }
    //edited by dipak end
  
    if ($generic_name != null) {
  
      if ($request->pid) {
        $p = Product::select('id', 'category_id')->with('category.categoryGroup')->find($request->pid);
        if ($p && $p->category) {
          if ($p->category->categoryGroup->id == 5) {
            $e_categories = Category::where('category_group_id', 5)->get()->pluck('id');
            $products     = $products->whereNotIn('category_id', $e_categories);
          } else {
            $e_categories = Category::where('category_group_id', $p->category->categoryGroup->id)->get()->pluck('id');
            $i_categories = Category::where('category_group_id', 5)->get()->pluck('id');
            $products     = $products->whereIn('category_id', $i_categories)->whereNotIn('category_id', $e_categories);
          }
        }
      }
      $products = $products->where('generic_name', 'like', '%' . $generic_name . '%');
    }

    if ($brand_id != null) {
     
      $products = $products->where('brand_id', $brand_id);
    } elseif ($brands) {
      

      $brand_ids = Brand::whereIn('slug', $brands)->get()->pluck('id');
      $products  = $products->whereIn('brand_id', $brand_ids);
    }

   
   
    if ($type) {
      $category_ids = explode(',', Category::find($request->category_id)->linked_categories);
      if ($type == 'accessories') {
        $category_ids = Category::whereIn('id', $category_ids)->where('category_group_id', 1)->pluck('id');
      } else {
        $category_ids = Category::whereIn('id', $category_ids)->where('category_group_id', 5)->pluck('id');
      }
      $products = $products->whereIn('category_id', $category_ids);
      
    } else {
      
      if ($category_id != null) {
        
        $category_ids   = CategoryUtility::children_ids($category_id);

       
        $category_ids[] = $category_id;
        $products->whereIn('category_id', $category_ids);
        
        $attribute_ids = AttributeCategory::whereIn('category_id', $category_ids)->pluck('attribute_id')->toArray();
        $attributes    = Attribute::whereIn('id', $attribute_ids)->where('type', '!=', 'data')->get();
      }
     
    }
    
    $min_total = floor($products->min('unit_price'));
    $max_total = ceil($products->max('unit_price'));
   
    if ($query != null) {
      $searchController = new SearchController;
      $searchController->store($request);
      $products->where(function ($q) use ($query) {
        foreach (explode(' ', trim($query)) as $word) {
          $q->where('name', 'like', '%' . $word . '%')
            ->orWhere('tags', 'like', '%' . $word . '%')
            ->orWhereHas('product_translations', function ($q) use ($word) {
              $q->where('name', 'like', '%' . $word . '%');
            })
            ->orWhereHas('stocks', function ($q) use ($word) {
              $q->where('variant', 'like', '%' . $word . '%');
            });
        }
      });
 
      $case1 = $query . '%';
      $case2 = '%' . $query . '%';

      $products->orderByRaw("CASE
                WHEN name LIKE '$case1' THEN 1
                WHEN name LIKE '$case2' THEN 2
                ELSE 3
                END");
    }
  
    if ($request->has('color')) {
      $str = '"' . $request->color . '"';
      $products->where('colors', 'like', '%' . $str . '%');
      $selected_color = $request->color;
    }

    if ($request->has('selected_attribute_values')) {
      $selected_attribute_values = $request->selected_attribute_values;
      $products->where(function ($query) use ($selected_attribute_values) {
        foreach ($selected_attribute_values as $key => $value) {
          $str = '"' . $value . '"';

          $query->orWhere('choice_options', 'like', '%' . $str . '%');
        }
      });
    }

   
    
    if ($min_price != null && $max_price != null) {
      $products->where('unit_price', '>=', $min_price)->where('unit_price', '<=', $max_price);
    }
   
    // switch ($sort_by) {
    //   case 'newest':
    //     $products->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END, CAST(products.mrp AS UNSIGNED) ASC");
    //     $products->orderBy('created_at', 'desc');
    //     break;
    //   case 'oldest':
    //     $products->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END, CAST(products.mrp AS UNSIGNED) ASC");
    //     $products->orderBy('created_at', 'asc');
    //     break;
    //   case 'price-asc':
    //     $products->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END, CAST(products.mrp AS UNSIGNED) ASC");
    //     $products->orderBy('mrp', 'asc');
    //     break;
    //   case 'price-desc':
    //     $products->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END, CAST(products.mrp AS UNSIGNED) DESC");
    //     $products->orderBy('mrp', 'desc');
    //     break;
    //   default:
    //     // $products->orderBy('id', 'desc');
    //     $products->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END");
    //     $products->orderBy('products.name', 'ASC');
    //     break;
    // }

    switch ($sort_by) {
          case 'newest':
              $products->orderByRaw("
                  CASE
                      WHEN products.name LIKE '%opel%' AND products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 0
                      WHEN products.name LIKE '%opel%' THEN 1
                      WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 2
                      ELSE 3
                  END, 
                  CAST(products.mrp AS UNSIGNED) ASC
              ");
              $products->orderBy('created_at', 'desc');
              break;

          case 'oldest':
              $products->orderByRaw("
                  CASE
                      WHEN products.name LIKE '%opel%' AND products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 0
                      WHEN products.name LIKE '%opel%' THEN 1
                      WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 2
                      ELSE 3
                  END, 
                  CAST(products.mrp AS UNSIGNED) ASC
              ");
              $products->orderBy('created_at', 'asc');
              break;

          case 'price-asc':
              $products->orderByRaw("
                  CASE
                      WHEN products.name LIKE '%opel%' AND products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 0
                      WHEN products.name LIKE '%opel%' THEN 1
                      WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 2
                      ELSE 3
                  END, 
                  CAST(products.mrp AS UNSIGNED) ASC
              ");
              $products->orderBy('mrp', 'asc');
              break;

          case 'price-desc':
              $products->orderByRaw("
                  CASE
                      WHEN products.name LIKE '%opel%' AND products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 0
                      WHEN products.name LIKE '%opel%' THEN 1
                      WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 2
                      ELSE 3
                  END, 
                  CAST(products.mrp AS UNSIGNED) DESC
              ");
              $products->orderBy('mrp', 'desc');
              break;

          default:
              $products->orderByRaw("
                  CASE
                      WHEN products.name LIKE '%opel%' AND products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 0
                      WHEN products.name LIKE '%opel%' THEN 1
                      WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                          SELECT part_no COLLATE utf8mb3_general_ci FROM products_api
                      ) THEN 2
                      ELSE 3
                  END
              ")
              //->orderBy('products.name', 'ASC') // Sort prioritized items alphabetically
              ->orderByRaw("CAST(products.mrp AS UNSIGNED) ASC"); 
              break;
      }



    $products = $products->with('taxes')->paginate(42)->appends(request()->query());
    // $categoryGroup=CategoryGroup::orderBy('name','asc')->get();

    $categoryGroup = DB::table('products')
              ->leftJoin('category_groups', 'products.group_id', '=','category_groups.id' )
              ->where('category_groups.featured', 1)
              ->where('products.part_no','!=','')
              ->where('products.current_stock','>','0')
              ->orderBy('category_groups.name', 'asc')
              ->select('category_groups.*')
              ->distinct()
              ->get();

    $catProducts = Product::where('category_id',$category_id)->where('part_no','!=','')->where('current_stock','!=','0')->get();
    $id_brand = $catProducts->pluck('brand_id')->unique()->toArray();
   
    // echo "<pre>";
    // print_r($products);
    // die();
    return view('frontend.product_listing', compact('categoryGroup','products', 'query', 'category_id', 'brand_id', 'brands', 'sort_by', 'seller_id', 'min_price', 'max_price', 'min_total', 'max_total', 'attributes', 'selected_attribute_values', 'selected_color','id_brand'));
  }
  

  public function listing(Request $request) {
    return $this->index($request);
  }

  public function listingByCategory(Request $request, $category_slug) {
    // $category = Category::where('slug', $category_slug)->first();
    $category = DB::table('products')
                      ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                      ->where('products.part_no','!=','')
                      ->where('products.current_stock','>','0')
                      ->where('categories.slug',$category_slug)
                      ->orderBy('categories.name', 'asc')
                      ->select('categories.id', 'categories.name', 'categories.slug')
                      ->distinct()
                      ->first();
    if ($category != null) {
      return $this->index($request, $category->id);
    }
    abort(404);
  }

  public function listingByBrand(Request $request, $brand_slug) {
    $brand = Brand::where('slug', $brand_slug)->first();
    if ($brand != null) {
      return $this->index($request, null, $brand->id);
    }
    abort(404);
  }

  //Suggestional Search
  public function ajax_search(Request $request) {
    $keywords = array();
    $query    = $request->search;
    // $products = Product::where('published', 1)->whereNotNull('photos')->where('tags', 'like', '%' . $query . '%')->get();
    $products = Product::where('published', 1)->where('tags', 'like', '%' . $query . '%')->get();
    foreach ($products as $key => $product) {
      foreach (explode(',', $product->tags) as $key => $tag) {
        if (stripos($tag, $query) !== false) {
          if (sizeof($keywords) > 5) {
            break;
          } else {
            if (!in_array(strtolower($tag), $keywords)) {
              array_push($keywords, strtolower($tag));
            }
          }
        }
      }
    }

    // $products_query = filter_products(Product::query());
    // $products_query = $products->where('published', '1')->where('added_by', 'admin')
    // $products_query = Product::query();
    // $products_query = $products_query->where('published', 1)
    //   ->where(function ($q) use ($query) {
    //     foreach (explode(' ', trim($query)) as $word) {
    //       $q->where('name', 'like', '%' . $word . '%')
    //         ->orWhere('tags', 'like', '%' . $word . '%')
    //         // ->orWhereHas('product_translations', function ($q) use ($word) {
    //         //   $q->where('name', 'like', '%' . $word . '%');
    //         // })
    //         ->orWhereHas('stocks', function ($q) use ($word) {
    //           $q->where('variant', 'like', '%' . $word . '%');
    //         });
    //     }
    //   });
    // $case1 = $query . '%';
    // $case2 = '%' . $query . '%';

    // $products_query->orderByRaw("CASE
    //             WHEN name LIKE '$case1' THEN 1
    //             WHEN name LIKE '$case2' THEN 2
    //             ELSE 3
    //             END");
    // // $products = $products_query->limit(3)->get();
    // $products = $products_query->get();

    // $products_query = Product::query();
    // $products_query = $products_query->where('published', 1)
    //     ->where(function ($q) use ($query) {
    //         foreach (explode(' ', trim($query)) as $word) {
    //             $q->where(function($subQuery) use ($word) {
    //                 // Match the word exactly or by sound
    //                 $subQuery->where('name', 'like', '%' . $word . '%')
    //                     ->orWhereRaw("SOUNDEX(name) = SOUNDEX(?)", [$word])
    //                     ->orWhere('tags', 'like', '%' . $word . '%')
    //                     ->orWhereRaw("SOUNDEX(tags) = SOUNDEX(?)", [$word])
    //                     ->orWhereHas('product_translations', function ($q) use ($word) {
    //                         $q->where('name', 'like', '%' . $word . '%')
    //                           ->orWhereRaw("SOUNDEX(name) = SOUNDEX(?)", [$word]);
    //                     })
    //                     ->orWhereHas('stocks', function ($q) use ($word) {
    //                         $q->where('variant', 'like', '%' . $word . '%')
    //                           ->orWhereRaw("SOUNDEX(variant) = SOUNDEX(?)", [$word]);
    //                     });
    //             });
    //         }
    //     });
    // $case1 = $query . '%';
    // $case2 = '%' . $query . '%';
    // $products_query->orderByRaw("CASE
    //             WHEN name LIKE '$case1' THEN 1
    //             WHEN name LIKE '$case2' THEN 2
    //             ELSE 3
    //             END");

    // // Execute the query
    // $products = $products_query->get();

    // $products = Product::where('name', 'like', '%' . $query . '%')->orWhere('tags', 'like', '%' . $query . '%')->orderBy('name','ASC')->limit(100)->get();
    $cacheKey = 'products_search_' . md5($query);
    $products = Cache::remember($cacheKey, 60, function() use ($query) {
        return Product::where('published', 1)
                      ->where('current_stock','>','0')
                      ->where('name', 'like', '%' . $query . '%')
                      ->orWhere('tags', 'like', '%' . $query . '%')                      
                      ->orderBy('name', 'ASC')
                      ->limit(50)
                      ->get();
    });
    // $categories = Category::where('name', 'like', '%' . $query . '%')->get()->take(3);
    $categories = Category::where('name', 'like', '%' . $query . '%')->orWhereRaw("SOUNDEX(name) = SOUNDEX(?)", [$query])->get();

    $brands = Brand::where(function($q) use ($query) {
      $q->where('name', 'like', '%' . $query . '%')
            ->orWhereRaw("SOUNDEX(name) = SOUNDEX(?)", [$query]);
      })
      ->where('slug', '!=', NULL)
      ->get();

    if (sizeof($keywords) > 0 || sizeof($categories) > 0 || sizeof($products) > 0) {
      return view('frontend.partials.search_content', compact('products', 'categories', 'keywords', 'brands'));
    }
    return '0';
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request) {
    $search = Search::where('query', $request->keyword)->first();
    if ($search != null) {
      $search->count = $search->count + 1;
      $search->save();
    } else {
      $search        = new Search;
      $search->query = $request->keyword;
      $search->save();
    }
  }

public function manager41QuickOrderList($category_group_id, Request $request)
{
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');

    $user_warehouse_id = (Auth::check() && Auth::user()->user_type == 'customer') ? Auth::user()->warehouse_id : 1;
    $fullUrl      = $request->fullUrl();
    $order_id     = "";
    $sub_order_id = "";
    $redirect_url = "";
    $user_id      = "";

    $queyparam = substr($fullUrl, strrpos("/$fullUrl", '?'));

    if (isset($request->order_id)) {
        $order_id      = $request->order_id;
        $encryptedId   = (string) $request->order_id;
        $temp_order_id = decrypt($encryptedId);
        $orderData     = Manager41Order::where('id', $temp_order_id)->first();
        $user          = User::where('id', $orderData->user_id)->first();
        $user_warehouse_id = $user->warehouse_id;
        $user_id           = $user->id;
    }

    if (isset($request->sub_order_id)) {
        $sub_order_id      = $request->sub_order_id;
        $encryptedId       = (string) $request->sub_order_id;
        $temp_sub_order_id = decrypt($encryptedId);
        $subOrderData      = Manager41SubOrder::where('id', $temp_sub_order_id)->first();
        $user              = User::where('id', $subOrderData->user_id)->first();
        $user_warehouse_id = $user->warehouse_id;
        $user_id           = $user->id;
    }

    if (isset($request->redirect_url)) {
        $redirect_url = $request->redirect_url;
    }

    $category_groups     = CategoryGroup::select('id', 'name')->get();
    $category_group      = $categories = $brands = $selected_brands = $selected_categories = $products = [];
    $srch_prod_name      = $request->has('prod_name') ? $request->prod_name : '';
    $selected_cat_groups = $request->has('cat_groups') ? $request->cat_groups : [];
    $selected_categories = $request->has('categories') ? $request->categories : [];

    if ($category_group_id) {
        $category_group = CategoryGroup::find($category_group_id);

        $categories = Cache::remember('group_' . $category_group->id . '_category_items', 3600, function () use ($category_group) {
            $category_items = Category::select('id', 'parent_id', 'name')
                ->with('parentCategory:id,name')
                ->where('category_group_id', $category_group->id)
                ->get();

            $category_items = $category_items->sortBy([
                ['parentCategory.name', 'asc'],
                ['name', 'asc'],
            ]);
            return $category_items;
        });

        $products = Cache::remember('group_' . $category_group->id . '_products_wh_' . $user_warehouse_id, 3600, function () use ($categories, $user_warehouse_id) {
            $final_products_list = collect([]);

            // IMPORTANT: include is_manager_41 + mrp_41_price and alias effective MRP as `mrp`
            $products = Product::select(
                    'products.id',
                    'products.current_stock',
                    'products.brand_id',
                    'products.group_id',
                    'products.category_id',
                    'products.name',
                    'products.thumbnail_img',
                    'products.slug',
                    'products.discount',
                    'products.discount_type',
                    'products.discount_start_date',
                    'products.discount_end_date',
                    'products.min_qty',
                    'products.is_manager_41',
                    'products.mrp_41_price',
                    'products.part_no'
                )
                // expose effective MRP as `mrp` so Blade/logic can keep using $product->mrp
                ->selectRaw('COALESCE(NULLIF(products.mrp_41_price, 0), products.mrp) AS mrp')
                ->whereIn('category_id', $categories->pluck('id'))
                ->where('published', true)
                ->where('approved', true)
                ->where('products.is_manager_41', 1) // only M-41 products
                ->orderBy('products.name', 'asc')
                ->get();

            // keep only is_manager_41 == 1 (property available now)
            $products = $products->filter(function ($p) {
                return (int) $p->is_manager_41 === 1;
            });

            // ⭐ use M-41 price aware processor
            $products = $this->processProductsManager41($products, $user_warehouse_id);

            foreach ($categories as $category) {
                $final_products_list = $final_products_list->merge($products->where('category_id', $category->id));
            }
            return $final_products_list;
        });

        $brands = Cache::remember('group_' . $category_group->id . '_brand_items', 3600, function () use ($products) {
            $brand_ids = $products->pluck('brand_id');
            return Brand::select('id', 'name')->whereIn('id', $brand_ids)->orderBy('name', 'ASC')->get();
        });
    } else {
        // No Group Selected
        $categories = Cache::remember('all_category_items', 1, function () {
            $category_items = Category::select('id', 'parent_id', 'name')
                ->with('parentCategory:id,name')
                ->where('level', 1)
                ->get();

            $category_items = $category_items->sortBy([
                ['parentCategory.name', 'asc'],
                ['name', 'asc'],
            ]);
            return $category_items;
        });

        $products = Cache::remember('all_product_items_wh_' . $user_warehouse_id, 5, function () use ($categories, $user_warehouse_id, $srch_prod_name, $user_id) {
            $final_products_list = collect([]);

            $productQuery = Product::query()
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');

            if ($srch_prod_name !== '') {
                $prod_name = preg_replace('/[\'\"]/', '', $srch_prod_name);
                $productQuery->where(function ($q) use ($prod_name) {
                    $q->whereRaw("REPLACE(REPLACE(`products`.`name`, '\"', ''), '\\'', '') LIKE ?", ['%' . $prod_name . '%'])
                      ->orWhere('products.part_no', 'like', '%' . $prod_name . '%');
                });
            }

            $products = $productQuery
                ->select(
                    'products.id',
                    'products.current_stock',
                    'products.is_manager_41',
                    'products.brand_id',
                    'category_groups.name AS group_name',
                    'categories.name AS category_name',
                    'products.group_id',
                    'products.category_id',
                    'products.name',
                    'products.thumbnail_img',
                    'products.slug',
                    'products.cash_and_carry_item',
                    'products.min_qty',
                    'products.mrp',          // keep original mrp (might be used by fallback)
                    'products.mrp_41_price', // include M-41 price
                    'products.part_no'
                )
                // expose effective MRP as `mrp` so Blade/logic stays unchanged
                ->addSelect(\DB::raw('COALESCE(NULLIF(products.mrp_41_price, 0), products.mrp) AS mrp'))
                ->where('products.published', true)
                ->where('products.is_manager_41', 1)
                ->where('products.approved', true)
                ->orderByRaw("CASE 
                    WHEN category_groups.id = 1 THEN 0 
                    WHEN category_groups.id = 8 THEN 1 
                    ELSE 2 END")
                ->orderBy('category_groups.name', 'asc')
                ->orderBy('categories.name', 'asc')
                ->orderByRaw("CASE 
                    WHEN products.name LIKE '%opel%' THEN 0
                    WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                        SELECT part_no COLLATE utf8mb3_general_ci 
                        FROM manager_41_product_stocks
                    ) THEN 1
                    ELSE 2 END")
                ->orderByRaw("CASE 
                    WHEN products.name LIKE '%opel%' THEN 0 
                    ELSE 1 END")
                // sort by effective Manager-41 price
                ->orderByRaw("CAST(COALESCE(NULLIF(products.mrp_41_price, 0), products.mrp) AS UNSIGNED) ASC")
                ->get();

            // keep all (you had return true)
            $products = $products->filter(function ($v) {
                return true;
            });

            // ⭐ use M-41 price aware processor
            $products = $this->processProductsManager41($products, $user_warehouse_id, $user_id);

            foreach ($categories as $category) {
                $final_products_list = $final_products_list->merge($products->where('category_id', $category->id));
            }
            return $products;
        });

        $brands = Cache::remember('all_brand_items', 3600, function () use ($products) {
            $brand_ids = $products->pluck('brand_id');
            return Brand::select('id', 'name')->whereIn('id', $brand_ids)->orderBy('name', 'ASC')->get();
        });
    }

    // Category group filter
    if ($request->cat_groups && array_filter($request->cat_groups)) {
        $selected_cat_groups = array_filter($request->cat_groups);
        $products = $products->whereIn('group_id', $selected_cat_groups);
    }

    // Categories filter
    if ($request->categories && array_filter($request->categories)) {
        $selected_categories = array_filter($request->categories);
        $products = $products->whereIn('category_id', $selected_categories);
    }

    // Brands filter
    if ($request->brands && array_filter($request->brands)) {
        $selected_brands = array_filter($request->brands);
        $products = $products->whereIn('brand_id', $selected_brands);
    }

    // determine 41-manager once, here:
    $is41Manager = $this->isActingAs41Manager();

    // Pagination (assumes your Collection::paginate macro is present)
    $products = $products->paginate(30);

    if ($request->ajax()) {
        $view = view('frontend.partials.manager41_quickorder_list_box', compact('products', 'is41Manager'))->render();
        return response()->json(['html' => $view]);
    }

    if (isset(Auth::user()->id) && Auth::user()->id == '24185') {
        $products = $this->addOfferTag($products);
    }

    return view('frontend.quickorder', compact(
        'queyparam',
        'srch_prod_name',
        'brands',
        'selected_brands',
        'categories',
        'selected_categories',
        'selected_cat_groups',
        'category_group',
        'category_groups',
        'products',
        'order_id',
        'sub_order_id',
        'redirect_url',
        'is41Manager'
    ));
}




  public function quickOrderList($category_group_id = null, Request $request) {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');

   // Manager-41 login 
    if ($this->isActingAs41Manager()) 
    { 
      return $this->manager41QuickOrderList($category_group_id, $request); 
    }
  

    $user_warehouse_id = (Auth::check() && Auth::user()->user_type == 'customer') ? Auth::user()->warehouse_id : 1;
    $fullUrl = $request->fullUrl();
    $order_id = "";
    $sub_order_id = "";
    $redirect_url = "";
    $user_id = "";
    $queyparam = substr($fullUrl, strrpos("/$fullUrl", '?'));
    if(isset($request->order_id)){ 
      $order_id = $request->order_id;
      $encryptedId = (string) $request->order_id;
      $temp_order_id = decrypt($encryptedId);
      $orderData = Order::where('id',$temp_order_id)->first();
      $user = User::where('id',$orderData->user_id)->first();
      $user_warehouse_id = $user->warehouse_id;
      $user_id = $user->id;
    }
    if(isset($request->sub_order_id)){
      $sub_order_id = $request->sub_order_id;
      $encryptedId = (string) $request->sub_order_id;
      $temp_sub_order_id= decrypt($encryptedId);
      $subOrderData = SubOrder::where('id',$temp_sub_order_id)->first();
      $user = User::where('id',$subOrderData->user_id)->first();
      $user_warehouse_id = $user->warehouse_id;
      $user_id = $user->id;
    }

    if(isset($request->redirect_url)){
      $redirect_url= $request->redirect_url;
    }

    $category_groups = CategoryGroup::select('id', 'name')->get();
    $category_group = $categories = $brands = $selected_brands = $selected_categories = $products = [];
    $srch_prod_name = $request->has('prod_name') ? $request->prod_name : '';
    $selected_cat_groups = $request->has('cat_groups') ? $request->cat_groups : [];
    $selected_categories = $request->has('categories') ? $request->categories : [];    
    
    if ($category_group_id) {
        $category_group = CategoryGroup::find($category_group_id);
        $categories = Cache::remember('group_' . $category_group->id . '_category_items', 3600, function () use ($category_group) {
            $category_items = Category::select('id', 'parent_id', 'name')->with('parentCategory:id,name')
                ->where('category_group_id', $category_group->id)
                ->withCount('products_current_stock')
                ->having('products_current_stock_count', '>', 0)
                ->get();
            $category_items = $category_items->sortBy([
                ['parentCategory.name', 'asc'],
                ['name', 'asc'],
            ]);
            return $category_items;
        });

        $products = Cache::remember('group_' . $category_group->id . '_products_wh_' . $user_warehouse_id, 3600, function () use ($categories, $user_warehouse_id) {
            $final_products_list = collect([]);
            $products = Product::select('id', 'current_stock', 'brand_id', 'group_id', 'category_id', 'name', 'thumbnail_img', 'slug', 'discount', 'discount_type', 'discount_start_date', 'discount_end_date', 'min_qty')
                ->whereIn('category_id', $categories->pluck('id'))
                ->where('published', true)
                ->where('approved', true)
                ->where('current_stock', 1)
                ->orderBy('name', 'asc')
                ->get();
            $products = $products->filter(function ($value) {
                return ($value->stocks_sum_qty + $value->stocks_sum_seller_stock) > $value->min_qty;
            });
            $products = $this->processProducts($products, $user_warehouse_id);
            foreach ($categories as $category) {
                $final_products_list = $final_products_list->merge($products->where('category_id', $category->id));
            }
            return $final_products_list;
        });

        $brands = Cache::remember('group_' . $category_group->id . '_brand_items', 3600, function () use ($products) {
            $brand_ids = $products->pluck('brand_id');
            return Brand::select('id', 'name')->whereIn('id', $brand_ids)->orderBy('name', 'ASC')->get();
        });
    } else {
        // No Group Selected
        $categories = Cache::remember('all_category_items', 1, function () {
            $category_items = Category::select('id', 'parent_id', 'name')->with('parentCategory:id,name')
                ->where('level', 1)
                ->withCount('products_current_stock')
                ->having('products_current_stock_count', '>', 0)
                ->get();
            $category_items = $category_items->sortBy([
                ['parentCategory.name', 'asc'],
                ['name', 'asc'],
            ]);
            return $category_items;
        });

        $products = Cache::remember('all_product_items_wh_' . $user_warehouse_id, 5, function () use ($categories, $user_warehouse_id, $srch_prod_name, $user_id) {
            $final_products_list = collect([]);
            $productQuery = Product::query()->join('categories', 'products.category_id', '=', 'categories.id')
                ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');
            // Product Name filter
            if ($srch_prod_name != '') {
                $prod_name = preg_replace('/[\'\"]/', '', $srch_prod_name);
                $productQuery->whereRaw("REPLACE(REPLACE(`products`.`name`, '\"', ''), '\'', '') LIKE ?", ['%' . $prod_name . '%'])
                    ->orWhere('part_no', 'like', '%' . $prod_name . '%');
            }
            
            // Prioritize category groups 1 (Power Tools) and 8 (Cordless Tools) and Opel products within each category
           $products = $productQuery
              ->select(
                  'products.id',
                  'current_stock',
                  'brand_id',
                  'category_groups.name AS group_name',
                  'categories.name AS category_name',
                  'group_id',
                  'category_id',
                  'products.name',
                  'thumbnail_img',
                  'products.slug',
                  'products.cash_and_carry_item',
                  'min_qty',
                  'mrp',
                  'part_no',
                  'is_warranty',
                  'warranty_duration',
                  
              )
              ->where('published', true)
              ->where('current_stock', 1)
              ->where('approved', true)
              ->orderByRaw("CASE 
                  WHEN category_groups.id = 1 THEN 0 
                  WHEN category_groups.id = 8 THEN 1 
                  ELSE 2 END")
              ->orderBy('category_groups.name', 'asc')
              ->orderBy('categories.name', 'asc')
              ->orderByRaw("CASE 
                  WHEN products.name LIKE '%opel%' THEN 0
                  WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                      SELECT part_no COLLATE utf8mb3_general_ci 
                      FROM products_api
                  ) THEN 1
                  ELSE 2 END")
              ->orderByRaw("CASE 
                  WHEN products.name LIKE '%opel%' THEN 0 
                  ELSE 1 END")
              ->orderByRaw("CAST(products.mrp AS UNSIGNED) ASC")
              ->get();


            $products = $products->filter(function ($value) {
                return true;
            });
            $products = $this->processProducts($products, $user_warehouse_id, $user_id);
            foreach ($categories as $category) {
                $final_products_list = $final_products_list->merge($products->where('category_id', $category->id));
            }
            return $products;
        });

        $brands = Cache::remember('all_brand_items', 3600, function () use ($products) {
            $brand_ids = $products->pluck('brand_id');
            return Brand::select('id', 'name')->whereIn('id', $brand_ids)->orderBy('name', 'ASC')->get();
        });
    }   

    // Categories group filter
    if ($request->cat_groups && array_filter($request->cat_groups)) {
        $selected_cat_groups = array_filter($request->cat_groups);
        $products = $products->whereIn('group_id', array_filter($request->cat_groups));
    }

    // Categories filter
    if ($request->categories && array_filter($request->categories)) {
        $selected_categories = array_filter($request->categories);
        $products = $products->whereIn('category_id', array_filter($request->categories));
    }

    // Brands filter
    if ($request->brands && array_filter($request->brands)) {
        $selected_brands = array_filter($request->brands);
        $products = $products->whereIn('brand_id', array_filter($request->brands));
    }
    
    // Pagination
    $products = $products->paginate(30);
    // echo "<pre>"; print_r($products);die;
    if ($request->ajax()) {
        $view = view('frontend.partials.quickorder_list_box', compact('products'))->render();
        return response()->json(['html' => $view]);
    }
    if(isset(Auth::user()->id) AND Auth::user()->id == '24185'){
      $products = $this->addOfferTag($products);
      // echo "<pre>"; print_r($products); die;
    }
    // echo "<pre>"; print_r($products); die;
    return view('frontend.quickorder', compact('queyparam', 'srch_prod_name', 'brands', 'selected_brands', 'categories', 'selected_categories', 'selected_cat_groups', 'category_group', 'category_groups', 'products', 'order_id', 'sub_order_id', 'redirect_url'));
  }

  public function addOfferTag($carts){
    $userDetails = User::with(['get_addresses' => function ($query) {
        $query->where('set_default', 1);
    }])->where('id', Auth::user()->id)->first();
    // echo "<pre>"; print_r($userDetails);die;
    $state_id = $userDetails->get_addresses[0]->state_id;
    $currentDate = Carbon::now(); // Get the current date and time
    foreach($carts as $cKey=>$cValue){
        $offerCount = 0;
        $productId=$cValue->id;
        $offers = Offer::with('offerProducts')
        ->where('status', 1) // Check for offer status
        ->where(function ($query) use ($userDetails) {
            $query->where('manager_id', $userDetails->manager_id)
                ->orWhereNull('manager_id');
        })            
        ->where(function ($query) use ($state_id) {
            $query->where('state_id', $state_id)
                ->orWhereNull('state_id');
        })
        ->whereDate('offer_validity_start', '<=', $currentDate) // Start date condition
        ->whereDate('offer_validity_end', '>=', $currentDate) // End date condition
        ->whereHas('offerProducts', function ($query) use ($productId) {
            $query->where('product_id', $productId);
        })->get();
        $offerCount = $offers->count();
        if($offerCount > 0){
            $cValue->offer = $offers;
        }else{
            $cValue->offer = "";
        }
    }
    return $carts;
  }



  public function quickOrderList_back($category_group_id = null, Request $request) {
   
    $fullUrl = $request->fullUrl();
    $queyparam =  substr($fullUrl, strrpos("/$fullUrl", '?'));
   
    $category_groups   = CategoryGroup::select('id', 'name')->get();
  
    $category_group    = $categories    = $brands    = $selected_brands    = $selected_categories   = $products    = [];
    $srch_prod_name     = $request->has('prod_name')? $request->prod_name : '';
    $selected_cat_groups = $request->has('cat_groups')? $request->cat_groups : [];
    $selected_categories = $request->has('categories')? $request->categories : [];
    
    $user_warehouse_id = (Auth::check() && Auth::user()->user_type == 'customer') ? Auth::user()->warehouse_id : 1;
    
    if ($category_group_id) {
      $category_group = CategoryGroup::find($category_group_id);
      $categories     = Cache::remember('group_' . $category_group->id . '_category_items', 3600, function () use ($category_group) {
        $category_items = Category::select('id', 'parent_id', 'name')->with('parentCategory:id,name')->where('category_group_id', $category_group->id)->withCount('products_current_stock')->having('products_current_stock_count', '>', 0)->get();
        $category_items = $category_items->sortBy([
          ['parentCategory.name', 'asc'],
          ['name', 'asc'],
        ]);
        return $category_items;
      });
      $products = Cache::remember('group_' . $category_group->id . '_products_wh_' . $user_warehouse_id, 3600, function () use ($categories, $user_warehouse_id) {
        $final_products_list = collect([]);
        // $products            = Product::with('stocks')->select('id', 'brand_id', 'category_id', 'name', 'thumbnail_img', 'slug', 'discount', 'discount_type', 'discount_start_date', 'discount_end_date', 'min_qty')->withSum('stocks', 'qty')->withSum('stocks', 'seller_stock')->whereIn('category_id', $categories->pluck('id'))->where('published', true)->where('approved', true)->whereNotNull('photos')->orderBy('name', 'asc')->get();
        $products            = Product::select('id', 'current_stock', 'brand_id', 'group_id', 'category_id', 'name', 'thumbnail_img', 'slug', 'discount', 'discount_type', 'discount_start_date', 'discount_end_date', 'min_qty')->whereIn('category_id', $categories->pluck('id'))->where('published', true)->where('approved', true)->where('current_stock', 1)->orderBy('name', 'asc')->get();
        $products            = $products->filter(function ($value) {
          return ($value->stocks_sum_qty + $value->stocks_sum_seller_stock) > $value->min_qty;
        });
        $products = $this->processProducts($products, $user_warehouse_id);
        foreach ($categories as $category) {
          $final_products_list = $final_products_list->merge($products->where('category_id', $category->id));
        }
        return $final_products_list;
      });
        $brands = Cache::remember('group_' . $category_group->id . '_brand_items', 3600, function () use ($products) {
          $brand_ids = $products->pluck('brand_id');
          return Brand::select('id', 'name')->whereIn('id', $brand_ids)->orderBy('name','ASC')->get();
        });
    } else {    
      // No Group Selected
      $categories = Cache::remember('all_category_items', 1, function () {
        $category_items = Category::select('id', 'parent_id', 'name')->with('parentCategory:id,name')->where('level', 1)->withCount('products_current_stock')->having('products_current_stock_count', '>', 0)->get();
        $category_items = $category_items->sortBy([
          ['parentCategory.name', 'asc'],
          ['name', 'asc'],
        ]);
        return $category_items;
      });
      $products = Cache::remember('all_product_items_wh_' . $user_warehouse_id, 5, function () use ($categories, $user_warehouse_id, $srch_prod_name) {
        $final_products_list = collect([]);
        // $products         = Product::select('id', 'brand_id', 'category_id', 'name', 'thumbnail_img', 'slug', 'min_qty')->with('stocks')->withSum('stocks', 'qty')->withSum('stocks', 'seller_stock')->where('published', true)->where('approved', true)->whereNotNull('photos')->orderBy('name', 'asc')->get();
        $productQuery        = Product::query()->join('categories', 'products.category_id', '=', 'categories.id')->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');
         // Product Name filter
        if ($srch_prod_name != '') {
          $prod_name = preg_replace('/[\'\"]/', '', $srch_prod_name); 
          $productQuery->whereRaw("REPLACE(REPLACE(`products`.`name`, '\"', ''), '\'', '') LIKE ?", ['%' . $prod_name . '%'])->orWhere('part_no','like','%' . $prod_name . '%');
        }
        // $products            = $productQuery->select('id', 'brand_id', 'category_id', 'name', 'thumbnail_img', 'slug', 'min_qty','mrp')->with('stocks')->withSum('stocks', 'qty')->withSum('stocks', 'seller_stock')->where('published', true)->where('approved', true)->orderBy('name', 'asc')->get();
        $products            = $productQuery->select('products.id', 'current_stock', 'brand_id', 'category_groups.name  AS group_name' ,'categories.name  AS category_name' ,'group_id', 'category_id', 'products.name', 'thumbnail_img', 'products.slug', 'min_qty','mrp')->where('published', true)->where('current_stock', 1)->where('approved', true)->orderBy('category_groups.name', 'asc')->orderBy('categories.name', 'asc')->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END, CAST(products.mrp AS UNSIGNED) ASC")->get();
        $products            = $products->filter(function ($value) {
          return true;
        });
        $products = $this->processProducts($products, $user_warehouse_id);
        foreach ($categories as $category) {
          $final_products_list = $final_products_list->merge($products->where('category_id', $category->id));
        }
        return $products;
      });
      
      $brands = Cache::remember('all_brand_items', 3600, function () use ($products) {
        $brand_ids = $products->pluck('brand_id');
        return Brand::select('id', 'name')->whereIn('id', $brand_ids)->orderBy('name','ASC')->get();
      }); 
      // echo "<pre>";
      //  print_r($brands->count());die;
    }
    // Categories group filter
    if ($request->cat_groups && array_filter($request->cat_groups)) {
      $selected_cat_groups = array_filter($request->cat_groups);
      $products = $products->whereIn('group_id', array_filter($request->cat_groups));
    }

    // Categories filter
    if ($request->categories && array_filter($request->categories)) {
      $selected_categories = array_filter($request->categories);
      $products = $products->whereIn('category_id', array_filter($request->categories));
    }
    
    // Brands filter
    if ($request->brands && array_filter($request->brands)) {
      $selected_brands = array_filter($request->brands);
      $products        = $products->whereIn('brand_id', array_filter($request->brands));
    }
    // Pagination
    $products = $products->paginate(30);
    
    if ($request->ajax()) {
      $view = view('frontend.partials.quickorder_list_box', compact('products'))->render();
      return response()->json(['html' => $view]);
    }
   
    return view('frontend.quickorder', compact('queyparam','srch_prod_name','brands', 'selected_brands', 'categories', 'selected_categories','selected_cat_groups', 'category_group', 'category_groups', 'products'));
  }





  public function quickOrderSearchListManager41(Request $request)
{
    $is41Manager          = $this->isActingAs41Manager();
    $category_group       = $categories = $brands = $selected_brands = $selected_categories = $products = [];
    $srch_prod_name       = $request->has('prod_name') ? $request->prod_name : '';
    $selected_cat_groups  = $request->has('cat_groups') ? $request->cat_groups : [];
    $selected_categories  = $request->has('categories') ? $request->categories : [];
    $selected_brands      = $request->has('brands') ? $request->brands : [];
    $order_id             = $request->has('order_id') ? $request->order_id : '';
    $inhouse              = $request->has('inhouse') ? $request->inhouse : false; // Inhouse filter

    // Default warehouse (customer ke liye apna, warna 1)
    $user_warehouse_id = (Auth::check() && Auth::user()->user_type == 'customer')
        ? Auth::user()->warehouse_id
        : 1;

    $productQuery = Product::query()
        ->join('categories', 'products.category_id', '=', 'categories.id')
        ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
        ->where('products.is_manager_41', 1); // 🔴 Manager-41 only

    // Product Name or Part No filter
    if ($srch_prod_name !== '') {
        $prod_name = preg_replace('/[\'\"]/', '', $srch_prod_name);
        $productQuery->where(function ($query) use ($prod_name) {
            $query->whereRaw("REPLACE(REPLACE(products.name, '\"', ''), '\\'', '') LIKE ?", ['%' . $prod_name . '%'])
                  ->orWhere('products.part_no', 'like', '%' . $prod_name . '%');
        });
    }

    // IMPORTANT: include is_manager_41 + mrp_41_price and expose effective MRP as `mrp`
    $productQuery->select(
            'products.id',
            'products.current_stock',
            'products.brand_id',
            'category_groups.name AS group_name',
            'categories.name AS category_name',
            'products.group_id',
            'products.category_id',
            'products.name',
            'products.thumbnail_img',
            'products.slug',
            'products.cash_and_carry_item',
            'products.min_qty',
            'products.mrp',           // keep original mrp too (used by fallback/diagnostics)
            'products.mrp_41_price',  // Manager-41 specific price
            'products.part_no',
            'products.is_manager_41'  // used by Blade
        )
        // ⭐ Alias effective Manager-41 price to `mrp` so Blade keeps working
        ->addSelect(\DB::raw('COALESCE(NULLIF(products.mrp_41_price, 0), products.mrp) AS mrp'))
        ->where('products.published', true)
        // ->where('products.current_stock', '>', 0)
        ->where('products.approved', true)
        ->orderByRaw("CASE 
            WHEN category_groups.id = 1 THEN 0 
            WHEN category_groups.id = 8 THEN 1 
            ELSE 2 END")
        ->orderBy('category_groups.name', 'asc')
        ->orderBy('categories.name', 'asc')
        // 🔁 Manager-41 prioritization: part_no present in manager_41_product_stocks first (after Opel)
        ->orderByRaw("CASE 
            WHEN products.name LIKE '%opel%' THEN 0
            WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                SELECT part_no COLLATE utf8mb3_general_ci 
                FROM manager_41_product_stocks
            ) THEN 1
            ELSE 2 END")
        // 🔢 Sort by effective Manager-41 price (mrp_41_price if >0 else mrp)
        ->orderByRaw('CAST(COALESCE(NULLIF(products.mrp_41_price, 0), products.mrp) AS UNSIGNED) ASC');

    // Inhouse Filter (Manager-41 ke liye: manager_41_product_stocks)
    if ($inhouse == '1') {
        $productQuery->whereIn('products.part_no', function ($query) {
            $query->select(\DB::raw('part_no COLLATE utf8mb3_general_ci'))
                  ->from('manager_41_product_stocks');
        });
    } elseif ($inhouse == '2') {
        $productQuery->whereNotIn('products.part_no', function ($query) {
            $query->select(\DB::raw('part_no COLLATE utf8mb3_general_ci'))
                  ->from('manager_41_product_stocks');
        });
    }

    // Category Groups filter
    if ($request->cat_groups && array_filter($request->cat_groups)) {
        $selected_cat_groups = array_filter($request->cat_groups);
        $productQuery->whereIn('products.group_id', $selected_cat_groups);
    }

    // Categories filter
    if ($request->categories && array_filter($request->categories)) {
        $selected_categories = array_filter($request->categories);
        $productQuery->whereIn('products.category_id', $selected_categories);
    }

    // Brands filter
    if ($request->brands && array_filter($request->brands)) {
        $selected_brands = array_filter($request->brands);
        $productQuery->whereIn('products.brand_id', $selected_brands);
    }

    // Execute Query
    $products = $productQuery->get();

    // 👇 Manager-41 aware processing (prefers mrp_41_price)
    if (method_exists($this, 'processProductsManager41')) {
        $products = $this->processProductsManager41($products, $user_warehouse_id);
    } else {
        // Fallbacks: try your legacy processor
        if (method_exists($this, 'processProducts')) {
            try {
                $products = $this->processProducts($products, $user_warehouse_id, true);
            } catch (\ArgumentCountError $e) {
                $products = $this->processProducts($products, $user_warehouse_id);
            }
        }
    }

    if (Auth::check() && (string) Auth::user()->id === '24185') {
        if (method_exists($this, 'addOfferTag')) {
            $products = $this->addOfferTag($products);
        }
    }

    $view = view('frontend.partials.manager41_quickorder_list_box', compact('products', 'order_id', 'is41Manager'))->render();

    // Optional: clear stray buffers if any package echoed
    if (ob_get_level()) { @ob_end_clean(); }

    return response()->json(['html' => $view]);
}


  public function quickOrderSearchList(Request $request) {

      if (method_exists($this, 'isActingAs41Manager') && $this->isActingAs41Manager()) {
          return $this->quickOrderSearchListManager41($request);
      }
      $category_group = $categories = $brands = $selected_brands = $selected_categories = $products = [];
      $srch_prod_name = $request->has('prod_name') ? $request->prod_name : '';
      $selected_cat_groups = $request->has('cat_groups') ? $request->cat_groups : [];
      $selected_categories = $request->has('categories') ? $request->categories : [];
      $selected_brands = $request->has('brands') ? $request->brands : [];
      $order_id = $request->has('order_id') ? $request->order_id : '';

      $inhouse = $request->has('inhouse') ? $request->inhouse : false; // Inhouse filter

      $user_warehouse_id = (Auth::check() && Auth::user()->user_type == 'customer') ? Auth::user()->warehouse_id : 1;

      $productQuery = Product::query()
          ->join('categories', 'products.category_id', '=', 'categories.id')
          ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');

      // Product Name or Part No filter
      if ($srch_prod_name != '') {
          $prod_name = preg_replace('/[\'\"]/', '', $srch_prod_name);

          $productQuery->where(function($query) use ($prod_name) {
              $query->whereRaw("REPLACE(REPLACE(products.name, '\"', ''), '\'', '') LIKE ?", ['%' . $prod_name . '%'])
                  ->orWhere('products.part_no', 'like', '%' . $prod_name . '%');
          });
      }

      $productQuery->select(
          'products.id',
          'current_stock',
          'brand_id',
          'category_groups.name AS group_name',
          'categories.name AS category_name',
          'group_id',
          'category_id',
          'products.name',
          'thumbnail_img',
          'products.slug',
          'products.cash_and_carry_item',
          'min_qty',
          'mrp',
          'part_no',
          'is_warranty',
          'warranty_duration',
      )
      ->where('products.published', true)
      ->where('products.current_stock', '>', 0)
      ->where('products.approved', true)
      ->orderByRaw("CASE 
          WHEN category_groups.id = 1 THEN 0 
          WHEN category_groups.id = 8 THEN 1 
          ELSE 2 END")
      ->orderBy('category_groups.name', 'asc')
      ->orderBy('categories.name', 'asc')
      ->orderByRaw("CASE 
          WHEN products.name LIKE '%opel%' THEN 0
          WHEN products.part_no COLLATE utf8mb3_general_ci IN (
              SELECT part_no COLLATE utf8mb3_general_ci 
              FROM products_api
          ) THEN 1
          ELSE 2 END")
      ->orderByRaw("CAST(products.mrp AS UNSIGNED) ASC");

      // Apply Inhouse Filter
      if ($inhouse == '1') {
          $productQuery->whereIn('products.part_no', function ($query) {
              $query->select(DB::raw('part_no COLLATE utf8mb3_general_ci'))
                    ->from('products_api')
                    ->where('closing_stock', '>', 0);
          });
      } elseif ($inhouse == '2') {
          $productQuery->whereNotIn('products.part_no', function ($query) {
              $query->select(DB::raw('part_no COLLATE utf8mb3_general_ci'))
                    ->from('products_api')
                    ->where('closing_stock', '>', 0);
          });
      }

      // Categories group filter
      if ($request->cat_groups && array_filter($request->cat_groups)) {
          $selected_cat_groups = array_filter($request->cat_groups);
          $productQuery->whereIn('group_id', $selected_cat_groups);
      }

      // Categories filter
      if ($request->categories && array_filter($request->categories)) {
          $selected_categories = array_filter($request->categories);
          $productQuery->whereIn('category_id', $selected_categories);
      }

      // Brands filter
      if ($request->brands && array_filter($request->brands)) {
          $selected_brands = array_filter($request->brands);
          $productQuery->whereIn('brand_id', $selected_brands);
      }

      // Execute Query
      $products = $productQuery->get();

      $products = $this->processProducts($products, $user_warehouse_id);
      if(Auth::user()->id == '24185'){
        $products = $this->addOfferTag($products);
        // echo "<pre>"; print_r($products); die;
      }
      $view = view('frontend.partials.quickorder_list_box', compact('products','order_id'))->render();
      return response()->json(['html' => $view]);
  }
 public function org_23_12_2024quickOrderSearchList(Request $request) {
  
    $category_group    = $categories    = $brands    = $selected_brands    = $selected_categories   = $products    = [];
    $srch_prod_name     = $request->has('prod_name')? $request->prod_name : '';
    $selected_cat_groups = $request->has('cat_groups')? $request->cat_groups : [];
    $selected_categories = $request->has('categories')? $request->categories : [];
    $selected_brands = $request->has('brands')? $request->brands : [];

     //edited by dipak start
    $inhouse = $request->has('inhouse') ? $request->inhouse : false; // Inhouse filter

     //edited by dipak end
    
    $user_warehouse_id = (Auth::check() && Auth::user()->user_type == 'customer') ? Auth::user()->warehouse_id : 1;
    $category_group_id = "";
    //---------------------------------------------------
    // $productQuery = Product::query()->join('categories', 'products.category_id', '=', 'categories.id')->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');
    // // Product Name filter
    // if ($srch_prod_name != '') {
    //   $prod_name = preg_replace('/[\'\"]/', '', $srch_prod_name); 
    //   $productQuery->whereRaw("REPLACE(REPLACE(products.name, '\"', ''), '\'', '') LIKE ?", ['%' . $prod_name . '%'])->orWhere('part_no','like','%' . $prod_name . '%');
    // }
    // $products = $productQuery->select('products.id', 'current_stock', 'brand_id', 'category_groups.name  AS group_name' ,'categories.name  AS category_name' ,'group_id', 'category_id', 'products.name', 'thumbnail_img', 'products.slug', 'min_qty','mrp')->where('published', true)->where('current_stock', 1)->where('approved', true)->orderBy('category_groups.name', 'asc')->orderBy('categories.name', 'asc')->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END, CAST(products.mrp AS UNSIGNED) ASC");
    //-------------------------------------------------------------------

    $productQuery = Product::query()
        ->join('categories', 'products.category_id', '=', 'categories.id')
        ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');

    /// Product Name or Part No filter
    if ($srch_prod_name != '') {
      $prod_name = preg_replace('/[\'\"]/', '', $srch_prod_name);

      // Wrapping the SOUNDEX logic with where closure to ensure proper grouping of OR conditions
      $productQuery->where(function($query) use ($prod_name) {
          $query->whereRaw("REPLACE(REPLACE(products.name, '\"', ''), '\'', '') LIKE ?", ['%' . $prod_name . '%'])
                ->orWhere('products.part_no', 'like', '%' . $prod_name . '%');
                // ->orWhereRaw("SOUNDEX(products.name) = SOUNDEX(?)", [$prod_name]);
                // You can uncomment the line below if you want to search part_no using SOUNDEX
                // ->orWhereRaw("SOUNDEX(products.part_no) = SOUNDEX(?)", [$prod_name]);
      });
    }



    $products = $productQuery->select(
            'products.id', 'current_stock', 'brand_id',
            'category_groups.name AS group_name',
            'categories.name AS category_name',
            'group_id', 'category_id', 'products.name',
            'thumbnail_img', 'products.slug','products.cash_and_carry_item', 'min_qty', 'mrp','part_no'
        )
      ->where('products.published', true)
      ->where('products.current_stock', '>', 0)
      ->where('products.approved', true)
    ->orderByRaw("CASE 
        WHEN category_groups.id = 1 THEN 0 
        WHEN category_groups.id = 8 THEN 1 
        ELSE 2 
      END") 
      ->orderBy('category_groups.name', 'asc')
      ->orderBy('categories.name', 'asc')
      ->orderByRaw("CASE 
        WHEN products.name LIKE '%opel%' THEN 0 
        WHEN products.name LIKE '%sigma%' THEN 1 
        ELSE 2 
      END")
      ->orderBy('products.name', 'ASC');



    //edited by dipak start
      // Apply Inhouse Filter 
    if ($inhouse == '1') {
        $productQuery->whereIn('products.part_no', function($query) {
            $query->select(DB::raw('part_no COLLATE utf8mb3_general_ci')) // Force collation for part_no
                  ->from('products_api');
        });
    }elseif($inhouse == '2') {
        $productQuery->whereNotIn('products.part_no', function($query) {
            $query->select(DB::raw('part_no COLLATE utf8mb3_general_ci')) // Force collation for part_no
                  ->from('products_api');
        });
    }

   // edited by dipak end

    // Categories group filter
    if ($request->cat_groups && array_filter($request->cat_groups)) {
        $selected_cat_groups = array_filter($request->cat_groups);
        $products->whereIn('group_id', $selected_cat_groups);
    }

    // Categories filter
    if ($request->categories && array_filter($request->categories)) {
        $selected_categories = array_filter($request->categories);
        $products->whereIn('category_id', $selected_categories);
    }

    // Brands filter
    if ($request->brands && array_filter($request->brands)) {
        $selected_brands = array_filter($request->brands);
        $products->whereIn('brand_id', $selected_brands);
    }
    // To get the count of results
    $totalResults = $products->count(); // This returns the total number of results
    
    if($totalResults <= 0){
      $productQuery = Product::query()
        ->join('categories', 'products.category_id', '=', 'categories.id')
        ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');
      $products = $productQuery->select(
              'products.id', 'current_stock', 'brand_id',
              'category_groups.name AS group_name',
              'categories.name AS category_name',
              'group_id', 'category_id', 'products.name',
              'thumbnail_img', 'products.slug', 'min_qty', 'mrp'
        )
        ->where('products.group_id', '1')
        ->where('products.brand_id', '187')
        ->where('products.published', true)
        ->where('products.current_stock', '>', 0)
        ->where('products.approved', true)
        ->orderBy('category_groups.name', 'asc')
        ->orderBy('categories.name', 'asc')
        ->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END")
        ->orderBy('products.name', 'ASC');
    }
    $products = $products->get();
    // echo $totalResults;
    // echo "<pre>";
    
    $products = $this->processProducts($products, $user_warehouse_id);
    // print_r($products);die;
    $view = view('frontend.partials.quickorder_list_box', compact('products'))->render();
    return response()->json(['html' => $view]);    
  }


  public function getCategoriesByGroupManager41(Request $request)
{
    // Expect: ['1','2'] or ['select_all'] or single value
    $groupIds = $request->input('category_group_id', []);

    // normalize to array
    if (!is_array($groupIds)) {
        $groupIds = [$groupIds];
    }

    // trim/normalize values
    $groupIds = array_map(static fn($v) => is_string($v) ? trim($v) : $v, $groupIds);

    // "select_all" -> all categories that have 41-stock products
    if (!empty($groupIds) && (string)$groupIds[0] === 'select_all') {
        $categories = Category::whereHas('products_current_stock_41')   // uses your new relation
            ->with('products_current_stock_41')                         // eager-load only 41 stock products
            ->orderBy('name', 'ASC')
            ->get();

        return response()->json($categories);
    }

    // specific groups (and not 0)
    if (!empty($groupIds) && (string)$groupIds[0] !== '0') {
        $query = Category::whereHas('products_current_stock_41')
            ->with('products_current_stock_41');

        // apply selected group filter if provided
        $ids = array_filter($groupIds, fn($v) => (string)$v !== '0');
        if (!empty($ids)) {
            $query->whereIn('category_group_id', $ids);
        }

        $categories = $query->orderBy('name', 'ASC')->get();
        return response()->json($categories);
    }

    // invalid / empty input
    return response()->json(0);
}




  public function getCategoriesByGroup(Request $request)
  {  

      if (method_exists($this, 'isActingAs41Manager') && $this->isActingAs41Manager()) {
            return $this->getCategoriesByGroupManager41($request);
      } 
      $category_group_id = $request->input('category_group_id');

      if ($category_group_id != 0 && $category_group_id[0] != 'select_all') {
          $query = Category::whereHas('products_current_stock', function ($query) {
              $query->where('current_stock', 1); // Ensure only categories with stock products are fetched
          })->with('products_current_stock');

          if (count($category_group_id)) {
              $query->whereIn('category_group_id', $category_group_id);
          }

          $query->orderBy('name', 'ASC');
          $categories = $query->get();
          return response()->json($categories);
      } elseif ($category_group_id[0] == 'select_all') {
          $categories = Category::whereHas('products_current_stock', function ($query) {
              $query->where('current_stock', 1); // Ensure only categories with stock products are fetched
          })->with('products_current_stock')->orderBy('name', 'ASC')->get();
          
          return response()->json($categories);
      } else {
          return response()->json(0);
      }        
  }

  public function getBrandByCategoriesGroupAndCategory(Request $request)
  {    
      $category_group_id = $request->input('category_group_id');
      $category_id = $request->input('category_id');
      if($request->input('from_admin')!==null){
        return Brand::select('id', 'name')->where('name','!=','')->orderBy('name','asc')->get();
      }else{
        if($category_group_id != 0 AND $category_id){        
          $brands = Product::query()
          ->join('brands', 'products.brand_id', '=', 'brands.id')
          ->when(count($category_group_id), function($query) use ($category_group_id) {
              $query->whereIn('products.group_id', $category_group_id);
          })
          ->when(count($category_id), function($query) use ($category_id) {
              $query->whereIn('products.category_id', $category_id);
          })
          ->where('published', true)->where('current_stock', 1)->where('approved', true)
          ->distinct()
          ->select('brands.*')
          ->get();

          return response()->json($brands);
        }else{
          $user_warehouse_id = (Auth::check() && Auth::user()->user_type == 'customer') ? Auth::user()->warehouse_id : 1;
          $productQuery  = Product::query()->join('categories', 'products.category_id', '=', 'categories.id')->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');
          $products = $productQuery->select('products.id', 'current_stock', 'brand_id', 'category_groups.name  AS group_name' ,'categories.name  AS category_name' ,'group_id', 'category_id', 'products.name', 'thumbnail_img', 'products.slug', 'min_qty','mrp')->where('published', true)->where('current_stock', 1)->where('approved', true)->orderBy('category_groups.name', 'asc')->orderBy('categories.name', 'asc')->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END, CAST(products.mrp AS UNSIGNED) ASC")->get();
          $products = $this->processProducts($products, $user_warehouse_id);        
          $brand_ids = $products->pluck('brand_id');
          return Brand::select('id', 'name')->whereIn('id', $brand_ids)->get();
        }
      }
      
  }

  // Search From Admin Start

  public function getBrandsFromAdmin(Request $request)
  {
      $seller_id = $request->input('seller_id'); 
      $category_group_id = $request->input('category_group_id');
      $category_id = $request->input('category_id');     
      if($seller_id != 0 AND $category_group_id != 0 AND $category_id != 0){        
        $brands = Product::query()
          ->join('brands', 'products.brand_id', '=', 'brands.id')
          ->when(!empty($seller_id), function($query) use ($seller_id) {
              $query->whereIn('products.seller_id', $seller_id);
          })
          ->join('category_groups', 'products.group_id', '=', 'category_groups.id')
          ->when(!empty($category_group_id), function($query) use ($category_group_id) {
              $query->whereIn('products.group_id', $category_group_id);
          })
          ->join('categories', 'products.category_id', '=', 'categories.id')
          ->when(!empty($category_id), function($query) use ($category_id) {
              $query->whereIn('products.category_id', $category_id);
          })
          ->distinct()
          ->select('brands.*')
          ->get();
        return response()->json($brands);
      }elseif($seller_id != 0 AND $category_group_id != 0 AND $category_id == 0){
        $brands = Product::query()
          ->join('brands', 'products.brand_id', '=', 'brands.id')
          ->when(!empty($seller_id), function($query) use ($seller_id) {
              $query->whereIn('products.seller_id', $seller_id);
          })
          ->join('category_groups', 'products.group_id', '=', 'category_groups.id')
          ->when(!empty($category_group_id), function($query) use ($category_group_id) {
              $query->whereIn('products.group_id', $category_group_id);
          })
          ->distinct()
          ->select('brands.*')
          ->get();
        return response()->json($brands);
      }elseif($seller_id == 0 AND $category_group_id != 0 AND $category_id == 0){
        $brands = Product::query()
          ->join('brands', 'products.brand_id', '=', 'brands.id')
          ->join('category_groups', 'products.group_id', '=', 'category_groups.id')
          ->when(!empty($category_group_id), function($query) use ($category_group_id) {
              $query->whereIn('products.group_id', $category_group_id);
          })
          ->distinct()
          ->select('brands.*')
          ->get();
        return response()->json($brands);
      }elseif($seller_id == 0 AND $category_group_id != 0 AND $category_id != 0){
        $brands = Product::query()
          ->join('brands', 'products.brand_id', '=', 'brands.id')
          ->join('category_groups', 'products.group_id', '=', 'category_groups.id')
          ->when(!empty($category_group_id), function($query) use ($category_group_id) {
              $query->whereIn('products.group_id', $category_group_id);
          })
          ->join('categories', 'products.category_id', '=', 'categories.id')
          ->when(!empty($category_id), function($query) use ($category_id) {
              $query->whereIn('products.category_id', $category_id);
          })
          ->distinct()
          ->select('brands.*')
          ->get();
        return response()->json($brands);
      }elseif($seller_id != 0 AND $category_group_id == 0 AND $category_id == 0){
        $brands = Product::query()
          ->join('brands', 'products.brand_id', '=', 'brands.id')
          ->when(!empty($seller_id), function($query) use ($seller_id) {
              $query->whereIn('products.seller_id', $seller_id);
          })
          ->distinct()
          ->select('brands.*')
          ->get();
        return response()->json($brands);
      }else{
        return Brand::select('id', 'name')->where('name','!=','')->orderBy('name','asc')->get();
      }    
  }

  public function getCatGroupBySellerWise(Request $request)
  {
      $seller_id = $request->input('seller_id');      
      if($seller_id != 0){        
        $catGroup = Product::query()
          ->join('category_groups', 'products.group_id', '=', 'category_groups.id')
          ->when(!empty($seller_id), function($query) use ($seller_id) {
              $query->whereIn('products.seller_id', $seller_id);
          })
          ->distinct()
          ->select('category_groups.id', 'category_groups.name')
          ->orderBy('category_groups.name', 'ASC')
          ->get();
        return response()->json($catGroup);
      }else{
        return CategoryGroup::orderBy('name','asc')->get();;
      }    
  }

  public function getCategoriesFromAdmin(Request $request)
  {   
      $seller_id = $request->input('seller_id');
      $category_group_id = $request->input('category_group_id');
      if($seller_id != 0 AND $category_group_id != 0){
        $categories = Product::query()
          ->join('categories', 'products.category_id', '=', 'categories.id')
          ->when(!empty($seller_id), function($query) use ($seller_id) {
              $query->whereIn('products.seller_id', $seller_id);
          })
          ->when(!empty($category_group_id), function($query) use ($category_group_id) {
              $query->whereIn('products.group_id', $category_group_id);
          })
          ->distinct()
          ->select('categories.id', 'categories.name')
          ->orderBy('categories.name', 'ASC')
          ->get();
        return response()->json($categories);
      }elseif($seller_id == 0 AND $category_group_id != 0){
        $categories = Product::query()
          ->join('categories', 'products.category_id', '=', 'categories.id')
          ->when(!empty($category_group_id), function($query) use ($category_group_id) {
              $query->whereIn('products.group_id', $category_group_id);
          })
          ->distinct()
          ->select('categories.id', 'categories.name')
          ->orderBy('categories.name', 'ASC')
          ->get();
        return response()->json($categories);
      }else{
        return 0;
      }        
  }
  public function getOwnBrandCategoriesFromAdmin(Request $request)
  {   $category_group_id = $request->input('category_group_id');
      if($category_group_id != 0){
        $categories = OwnBrandProduct::query()
          ->join('own_brand_categories', 'own_brand_products.category_id', '=', 'own_brand_categories.id')
          ->when(!empty($category_group_id), function($query) use ($category_group_id) {
              $query->whereIn('own_brand_products.group_id', $category_group_id);
          })
          ->distinct()
          ->select('own_brand_categories.id', 'own_brand_categories.name')
          ->orderBy('own_brand_categories.name', 'ASC')
          ->get();
        return response()->json($categories);
      }else{
        return 0;
      }        
  }
  public function getBrandByCategoriesGroupAndCategoryFromAdmin(Request $request)
  {    
    $category_group_id = $request->input('category_group_id');
    $category_id = $request->input('category_id');
    if($category_group_id != 0 AND $category_id){        
      $brands = Product::query()
      ->join('brands', 'products.brand_id', '=', 'brands.id')
      ->when(count($category_group_id), function($query) use ($category_group_id) {
          $query->whereIn('products.group_id', $category_group_id);
      })
      ->when(count($category_id), function($query) use ($category_id) {
          $query->whereIn('products.category_id', $category_id);
      })
      // ->where('published', true)->where('current_stock', 1)->where('approved', true)
      ->distinct()
      ->select('brands.*')
      ->get();
      return response()->json($brands);
    }else{
      return Brand::select('id', 'name')->where('name','!=','')->orderBy('name','asc')->get();
    }
      
  }

  // Search From Admin End

  private function processProducts($products, $user_warehouse_id, $user_id="") {
    foreach ($products as $product) {
      $price = $markup = $wmarkup = 0;
      // if ($product->brand->markup) {
      //   $markup = $product->brand->markup;
      // } else if ($product->category->markup) {
      //   $markup = $product->category->markup;
      // }
      // $same_warehouse_stock = $product->stocks->where('warehouse_id', $user_warehouse_id)->first();
      // if ($same_warehouse_stock) {
      //   $price = $same_warehouse_stock->price;
      // } else {
      //   $warehouse = Warehouse::find($user_warehouse_id);
      //   foreach ($warehouse->markup as $wamarkup) {
      //     $p_stock = $product->stocks->where('warehouse_id', $wamarkup['warehouse_id'])->first();
      //     $wmarkup += $wamarkup['markup'];
      //     if ($p_stock) {
      //       $price = $p_stock->price;
      //       break;
      //     }
      //   }
      // }
      // $price += $price * ($markup + $wmarkup) / 100;
      // $tax = 0;
      // foreach ($product->taxes as $product_tax) {
      //   if ($product_tax->tax_type == 'percent') {
      //     $tax += ($price * $product_tax->tax) / 100;
      //   } elseif ($product_tax->tax_type == 'amount') {
      //     $tax += $product_tax->tax;
      //   }
      // }
      // $product->home_base_price = $price + $tax;

      // Log a message to the browser console
      if($user_id == ""){
        $user = Auth::user();
      }else{
        $user = User::where('id',$user_id)->first();
      }
      

      $discount = 0;

      if ($user) {
          $discount = $user->discount;
      } else {
          // echo "<script>console.log('User not logged in');</script>";
      }

      if(!is_numeric($discount) || $discount == 0) {
        $discount = 20;
      }

      //$product_mrp = Product::where('id', $product->id)->select('mrp')->first();
      if ($product->mrp) {
          $price = $product->mrp;
      } else {
        $price = 0;
      }
      
      if (!is_numeric($price)) {
        $price = 0;
      }

      $price = $price * ((100 - $discount) / 100);
      $product->home_discounted_base_price = $price;
      
      $price = $price * 131.6 / 100;
      $product->home_base_price = $price;
      
      // $discount_applicable      = false;
      // if ($product->discount_start_date == null) {
      //   $discount_applicable = true;
      // } elseif (
      //   strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
      //   strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date
      // ) {
      //   $discount_applicable = true;
      // }
      // if ($discount_applicable) {
      //   if ($product->discount_type == 'percent') {
      //     $price -= ($price * $product->discount) / 100;
      //   } elseif ($product->discount_type == 'amount') {
      //     $price -= $product->discount;
      //   }
      // }

      // $tax = 0;
      // foreach ($product->taxes as $product_tax) {
      //   if ($product_tax->tax_type == 'percent') {
      //     $tax += ($price * $product_tax->tax) / 100;
      //   } elseif ($product_tax->tax_type == 'amount') {
      //     $tax += $product_tax->tax;
      //   }
      // }
      // $product->home_discounted_base_price = $price + $tax;
    }
    return $products;
  }



  private function processProductsManager41($products, $user_warehouse_id, $user_id = "")
  {
      foreach ($products as $product) {
          // Prefer Manager-41 price; fallback to normal MRP
          $base = 0.0;

          if (isset($product->mrp_41_price) && is_numeric($product->mrp_41_price) && (float)$product->mrp_41_price > 0) {
              $base = (float) $product->mrp_41_price;
          } elseif (isset($product->mrp) && is_numeric($product->mrp) && (float)$product->mrp > 0) {
              $base = (float) $product->mrp;
          }

          // Manager-41: NO user discount, NO product discount, NO multipliers
          $product->home_discounted_base_price = $base;
          $product->home_base_price            = $base;
      }

      return $products;
  }
}
