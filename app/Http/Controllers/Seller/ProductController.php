<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\ProductRequest;
use App\Models\AttributeValue;
use App\Models\Cart;
use App\Models\CategoryGroup;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\ProductTax;
use App\Models\ProductTranslation;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use App\Services\ProductFlashDealService;
use App\Services\ProductService;
use App\Services\ProductStockService;
use App\Services\ProductTaxService;
use Artisan;
use App\Models\Upload;
use Auth;
use Carbon\Carbon;
use Combinations;
use Illuminate\Http\Request;
use Str;
use CoreComponentRepository;
use Illuminate\Support\Facades\Http;
class ProductController extends Controller {
  protected $productService;
  protected $productTaxService;
  protected $productFlashDealService;
  protected $productStockService;

  public function __construct(
    ProductService $productService,
    ProductTaxService $productTaxService,
    ProductFlashDealService $productFlashDealService,
    ProductStockService $productStockService
  ) {
    $this->productService          = $productService;
    $this->productTaxService       = $productTaxService;
    $this->productFlashDealService = $productFlashDealService;
    $this->productStockService     = $productStockService;
  }

  public function index(Request $request) {

    $search   = null;
    $sellerId = Seller::where('user_id', Auth::user()->id)->value('id');
    $products = ProductWarehouse::with('product')->where('seller_id', $sellerId)->orderBy('created_at', 'desc');
    // if ($request->has('search')) {
    //   $search   = $request->search;
    //   $products = $products->where('name', 'like', '%' . $search . '%');
    // }
    // Apply search filter if search query exists
    if ($request->has('search') && !empty($request->search)) {
        $search   = $request->search;
        $products = $products->whereHas('product', function ($query) use ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        });
    }

    $products = $products->paginate(10);
    return view('seller.product.products.index', compact('products', 'search'));
  }

  public function create(Request $request) {
    if (addon_is_activated('seller_subscription')) {
      if (seller_package_validity_check()) {
        $categories = Category::where('parent_id', 0)
          ->where('digital', 0)
          ->with('childrenCategories')
          ->get();
        return view('seller.product.products.create', compact('categories'));
      } else {
        flash(translate('Please upgrade your package.'))->warning();
        return back();
      }
    }
    $categories = Category::where('parent_id', 0)
      ->where('digital', 0)
      ->with('childrenCategories')
      ->get();
    return view('seller.product.products.create', compact('categories'));
  }

  public function store(ProductRequest $request) {
    if (addon_is_activated('seller_subscription')) {
      if (!seller_package_validity_check()) {
        flash(translate('Please upgrade your package.'))->warning();
        return redirect()->route('seller.products');
      }
    }

    $product = $this->productService->store($request->except([
      '_token', 'sku', 'choice', 'tax_id', 'tax', 'tax_type', 'flash_deal_id', 'flash_discount', 'flash_discount_type',
    ]));
    $request->merge(['product_id' => $product->id]);

    //VAT & Tax
    if ($request->tax_id) {
      $this->productTaxService->store($request->only([
        'tax_id', 'tax', 'tax_type', 'product_id',
      ]));
    }

    //Product Stock
    $this->productStockService->store($request->only([
      'colors_active', 'colors', 'choice_no', 'unit_price', 'sku', 'current_stock', 'product_id',
    ]), $product);

    // Product Translations
    $request->merge(['lang' => env('DEFAULT_LANGUAGE')]);
    ProductTranslation::create($request->only([
      'lang', 'name', 'unit', 'description', 'product_id',
    ]));

    flash(translate('Product has been inserted successfully'))->success();

    Artisan::call('view:clear');
    Artisan::call('cache:clear');

    return redirect()->route('seller.products');
  }

  public function edit(Request $request, $id) {


    $product = Product::findOrFail($id);

    // if (Auth::user()->id != $product->user_id) {
    //   flash(translate('This product is not yours.'))->warning();
    //   return back();
    // }

    $lang       = $request->lang;
    $tags       = json_decode($product->tags);
    $categories = Category::where('parent_id', 0)
     // ->where('digital', 0)
      ->with('childrenCategories')
      ->get();
     
    return view('seller.product.products.edit', compact('product', 'categories', 'tags', 'lang'));
  }


  public function updateSellerProducts(Request $request)
  {
      // Validate incoming data
      $request->validate([
          'part_no' => 'required|string|exists:products,part_no',
          'brand_id' => 'required|integer|exists:brands,id',
          'group_id' => 'required|integer|exists:category_groups,id',
          'category_id' => 'required|integer|exists:categories,id',
          'name' => 'required|string|max:255',
          'purchase_price' => 'required|numeric|min:0',
          'current_stock' => 'nullable|integer|min:0',
          'tax' => 'nullable|numeric|min:0',
          'tax_type' => 'nullable|string|in:flat,percent',
          'published' => 'nullable|boolean',
          'approved' => 'nullable|boolean',
      ]);

      // Retrieve the product
      $part_no = $request->part_no;
      $product = Product::where('part_no', $part_no)->first();

      if (!$product) {
          return back()->withErrors(['error' => 'Product not found.']);
      }

      // Calculate piece by carton
      $piece_by_carton = 0;
      if ($request->mrp > 0) {
          $piece_by_carton = round(50000 / ($request->mrp - (($request->mrp * 24) / 100)));
      }

      // Update product details
      $product->update([
          'brand_id' => $request->brand_id,
          'group_id' => $request->group_id,
          'category_id' => $request->category_id,
          'seller_id' => $request->seller_id,
          'user_id' => 1, // Default user ID
          'meta_title' => $request->meta_title ?? $product->meta_title,
          'meta_description' => $request->meta_description ?? '',
          'meta_keywords' => $request->meta_keywords ?? '',
          'slug' => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $request->name)),
          'attributes' => '[]',
          'choice_options' => '[]',
          'colors' => '[]',
          'variations' => '[]',
          'name' => $request->name,
          'alias_name' => $request->alias_name ?? $product->alias_name,
          'billing_name' => $request->billing_name ?? $product->billing_name,
          'mrp' => $request->mrp ?? $product->mrp,
          'warehouse_id' => $request->warehouse_id ?? $product->warehouse_id,
          'current_stock' => $request->current_stock ?? $product->current_stock,
          'seller_stock' => $request->seller_stock ?? $product->seller_stock,
          'hsncode' => $request->hsncode ?? $product->hsncode,
          'tax' => $request->tax ?? $product->tax,
          'tax_type' => $request->tax_type ?? $product->tax_type,
          'weight' => $request->weight ?? $product->weight,
          'piece_by_carton' => $piece_by_carton,
          'purchase_price' => $request->purchase_price,
          'approved' => $request->approved ?? $product->approved,
          'published' => $request->published ?? $product->published,
          'description' => $request->description,
      ]);


      // Update product warehouse details
      $productWarehouse = ProductWarehouse::where('part_no', $part_no)->first();

      if ($productWarehouse) {
          $productWarehouse->update([
              'warehouse_id' => $request->warehouse_id,
              'seller_id' => $request->seller_id,
              'hsncode' => $request->hsncode ?? $product->hsncode,
              'seller_stock' => 1,
              'sz_manual_price' => $request->mrp ?? $product->mrp,
              'variant' => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $request->name)),
          ]);
      }

      // Update product translations
      ProductTranslation::updateOrCreate(
          [
              'lang' => $request->lang,
              'product_id' => $product->id,
          ],
          [
              'name' => $request->name,
              'unit' => $request->unit,
              'description' => $request->description,
          ]
      );

      // Push item data to Salezing
      $result = ['part_no' => $part_no];
      $response = Http::withHeaders(['Content-Type' => 'application/json'])
          ->post('https://mazingbusiness.com/api/v2/item-push', $result);

      \Log::info('Salzing Item Push Status: ' . json_encode($response->json(), JSON_PRETTY_PRINT));

      // Clear caches
      Artisan::call('view:clear');
      Artisan::call('cache:clear');

      // Success message
      flash(translate('Product has been updated successfully'))->success();

      return back();
  }

 public function seller_products_edit(Request $request, $id) {

    $product = Product::findOrFail($id);

    $lang = $request->lang ?? 'en';
    $tags = json_decode($product->tags);

    // Fetch sellers with their associated users
    $sellers = Seller::join('users', 'sellers.user_id', '=', 'users.id')
        ->select('sellers.*', 'users.name as user_name')
        ->get();


    // Fetch category groups, categories, and warehouses
    $categoryGroups = CategoryGroup::orderBy('name', 'ASC')->get();
    $categories = Category::where('category_group_id', $product->group_id)->get();
    $warehouses = Warehouse::orderBy('name', 'ASC')->get();



    // Fetch product photos if available
    $photos = [];
    if (!empty($product->photos)) {
        $proPhotosArray = explode(',', $product->photos);
        $photos = Upload::whereIn('id', $proPhotosArray)->get();
    }


    // Return the view for editing the product
    return view('seller.product.products.seller_edit', compact(
        'product', 'categoryGroups', 'categories', 'warehouses', 'photos', 'tags', 'sellers', 'lang'
    ));
}


  public function update(ProductRequest $request, Product $product) {

    
    //Product
    $product = $this->productService->update($request->except([
      '_token', 'sku', 'choice', 'tax_id', 'tax', 'tax_type', 'flash_deal_id', 'flash_discount', 'flash_discount_type',
    ]), $product);

    //Product Stock
    foreach ($product->stocks as $key => $stock) {
      $stock->delete();
    }
    $request->merge(['product_id' => $product->id]);
    $this->productStockService->store($request->only([
      'colors_active', 'colors', 'choice_no', 'unit_price', 'sku', 'current_stock', 'product_id',
    ]), $product);

    //VAT & Tax
    if ($request->tax_id) {
      ProductTax::where('product_id', $product->id)->delete();
      $request->merge(['product_id' => $product->id]);
      $this->productTaxService->store($request->only([
        'tax_id', 'tax', 'tax_type', 'product_id',
      ]));
    }

    // Product Translations
    ProductTranslation::where('lang', $request->lang)
      ->where('product_id', $request->product_id)
      ->update($request->only([
        'lang', 'name', 'unit', 'description', 'product_id',
      ]));

    flash(translate('Product has been updated successfully'))->success();

    Artisan::call('view:clear');
    Artisan::call('cache:clear');

    return back();
  }

  public function sku_combination(Request $request) {
    $options = array();
    if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
      $colors_active = 1;
      array_push($options, $request->colors);
    } else {
      $colors_active = 0;
    }

    $unit_price   = $request->unit_price;
    $product_name = $request->name;

    if ($request->has('choice_no')) {
      foreach ($request->choice_no as $key => $no) {
        $name = 'choice_options_' . $no;
        $data = array();
        foreach ($request[$name] as $key => $item) {
          array_push($data, $item);
        }
        array_push($options, $data);
      }
    }

    $combinations = Combinations::makeCombinations($options);
    return view('backend.product.products.sku_combinations', compact('combinations', 'unit_price', 'colors_active', 'product_name'));
  }

  public function sku_combination_edit(Request $request) {
    $product = Product::findOrFail($request->id);

    $options = array();
    if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
      $colors_active = 1;
      array_push($options, $request->colors);
    } else {
      $colors_active = 0;
    }

    $product_name = $request->name;
    $unit_price   = $request->unit_price;

    if ($request->has('choice_no')) {
      foreach ($request->choice_no as $key => $no) {
        $name = 'choice_options_' . $no;
        $data = array();
        foreach ($request[$name] as $key => $item) {
          array_push($data, $item);
        }
        array_push($options, $data);
      }
    }

    $combinations = Combinations::makeCombinations($options);
    return view('backend.product.products.sku_combinations_edit', compact('combinations', 'unit_price', 'colors_active', 'product_name', 'product'));
  }

  public function add_more_choice_option(Request $request) {
    $all_attribute_values = AttributeValue::with('attribute')->where('attribute_id', $request->attribute_id)->get();

    $html = '';

    foreach ($all_attribute_values as $row) {
      $html .= '<option value="' . $row->value . '">' . $row->value . '</option>';
    }

    echo json_encode($html);
  }

  public function updatePublished(Request $request) {
    $product            = Product::findOrFail($request->id);
    $product->published = $request->status;
    if (addon_is_activated('seller_subscription') && $request->status == 1) {
      $shop = $product->user->shop;
      if (
        $shop->package_invalid_at == null
        || Carbon::now()->diffInDays(Carbon::parse($shop->package_invalid_at), false) < 0
        || $shop->product_upload_limit <= $shop->user->products()->where('published', 1)->count()
      ) {
        return 2;
      }
    }
    $product->save();
    return 1;
  }

  public function updateFeatured(Request $request) {
    $product                  = Product::findOrFail($request->id);
    $product->seller_featured = $request->status;
    if ($product->save()) {
      Artisan::call('view:clear');
      Artisan::call('cache:clear');
      return 1;
    }
    return 0;
  }

  public function duplicate($id) {
    $product = Product::find($id);
    if (Auth::user()->id != $product->user_id) {
      flash(translate('This product is not yours.'))->warning();
      return back();
    }
    if (addon_is_activated('seller_subscription')) {
      if (!seller_package_validity_check()) {
        flash(translate('Please upgrade your package.'))->warning();
        return back();
      }
    }

    if (Auth::user()->id == $product->user_id) {
      $product_new       = $product->replicate();
      $product_new->slug = $product_new->slug . '-' . Str::random(5);
      $product_new->save();

      //Product Stock
      $this->productStockService->product_duplicate_store($product->stocks, $product_new);

      //VAT & Tax
      $this->productTaxService->product_duplicate_store($product->taxes, $product_new);

      flash(translate('Product has been duplicated successfully'))->success();
      return redirect()->route('seller.products');
    } else {
      flash(translate('This product is not yours.'))->warning();
      return back();
    }
  }

  public function destroy($id) {
    $product = Product::findOrFail($id);

    if (Auth::user()->id != $product->user_id) {
      flash(translate('This product is not yours.'))->warning();
      return back();
    }

    $product->product_translations()->delete();
    $product->stocks()->delete();
    $product->taxes()->delete();

    if (Product::destroy($id)) {
      Cart::where('product_id', $id)->delete();

      flash(translate('Product has been deleted successfully'))->success();

      Artisan::call('view:clear');
      Artisan::call('cache:clear');

      return back();
    } else {
      flash(translate('Something went wrong'))->error();
      return back();
    }
  }
}
