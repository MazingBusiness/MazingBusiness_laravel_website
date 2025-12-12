<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Http\Requests\ProductRequest;
use App\Http\Requests\OwnBrandProductRequest;
use Illuminate\Support\Facades\File;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Cart;

use App\Models\Product;
use App\Models\ProductTax;
use App\Models\ProductTranslation;
use App\Models\ProductWarehouse;

use App\Models\Seller;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Brand;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Upload;
use App\Models\OwnBrandCategoryGroup;
use App\Models\OwnBrandCategory;
use App\Models\OwnBrandProduct;
use App\Models\InvoiceOrderDetail;
use App\Models\ResetInventoryProduct;
use App\Models\Barcode; // Add this at the top

// For Closing Stock
use App\Models\ProductApi;
use App\Models\OpeningStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceDetail;
use App\Models\Challan;
use App\Models\ChallanDetail;

use App\Models\MarkAsLostItem;


use App\Models\Manager41ProductStock;
use App\Models\Manager41OpeningStock;
use App\Models\Manager41PurchaseInvoiceDetail;
use App\Models\Manager41ChallanDetail;
use App\Models\Manager41Challan;

use App\Services\ProductFlashDealService;
use App\Services\ProductService;
use App\Services\ProductStockService;
use App\Services\ProductTaxService;
use App\Services\GoogleSheetsService;
use Maatwebsite\Excel\Facades\Excel; // Assuming you're using Laravel Excel for export
use Maatwebsite\Excel\Concerns\FromQuery;

use App\Exports\ClosingStockExport;
use App\Exports\ClosingStockDetailsExport;

use App\Http\Controllers\SearchController;
use App\Http\Controllers\ZohoController;
use App\Http\Controllers\OrderController;

use Artisan;
use Cache;
use Carbon\Carbon;
use Combinations;
use CoreComponentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Str;
use PDF;
use Illuminate\Support\Facades\Auth;

use Picqer\Barcode\BarcodeGeneratorPNG;

use Maatwebsite\Excel\Excel as ExcelWriter;

class ProductController extends Controller {
  protected $productService;
  protected $productTaxService;
  protected $productFlashDealService;
  protected $productStockService;
  protected $sheetsService;

  public function __construct(
    ProductService $productService,
    ProductTaxService $productTaxService,
    ProductFlashDealService $productFlashDealService,
    ProductStockService $productStockService,
    GoogleSheetsService $sheetsService
  ) {
    $this->productService          = $productService;
    $this->productTaxService       = $productTaxService;
    $this->productFlashDealService = $productFlashDealService;
    $this->productStockService     = $productStockService;
    $this->sheetsService           = $sheetsService;

    // Staff Permission Check
    $this->middleware(['permission:add_new_product'])->only('create');
    $this->middleware(['permission:show_all_products'])->only('all_products', 'no_images');
    $this->middleware(['permission:show_in_house_products'])->only('admin_products');
    $this->middleware(['permission:show_seller_products'])->only('seller_products');
    $this->middleware(['permission:product_edit'])->only('admin_product_edit', 'seller_product_edit');
    $this->middleware(['permission:product_duplicate'])->only('duplicate');
    $this->middleware(['permission:product_delete'])->only('destroy');
  }
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */

    // mark as lost start
    public function markAsLostPage()
    {
        return view('backend.product.products.mark_as_lost');
    }

   public function getProductStockByPartNo(Request $request)
   {
      $type = $request->input('type'); // 'part_no' or 'name'
      $value = $request->input('value');

      if ($type === 'part_no') {
          $product = Product::where('part_no', $value)->first();
      } else {
          $product = Product::where('name', 'like', '%' . $value . '%')->first();
      }

      if (!$product) {
          return response()->json(['status' => 'error', 'message' => 'Product not found']);
      }

      $part_no = $product->part_no;

      $products = ProductApi::where('part_no', $part_no)->get();

      if ($products->isEmpty()) {
          return response()->json(['status' => 'error', 'message' => 'Stock not found']);
      }

      $stock_by_warehouse = [];
      foreach ($products as $p) {
          $godown = strtolower($p->godown);
          $stock_by_warehouse[$godown] = $p->closing_stock;
      }

      return response()->json([
          'status' => 'success',
          'part_no' => $part_no,
          'product_id' => $product->id,
          'item_name' => $product->name,
          'stock' => $stock_by_warehouse
      ]);
   }

    public function storeMarkAsLost(Request $request)
    {
        $part_no = $request->input('part_no');
        $product_id = $request->input('product_id');
        $item_name = $request->input('item_name');
        $lost_stock = $request->input('lost_stock');
        $reason = $request->input('reason');


        

        $hasAnyQty = false;
        foreach ($lost_stock as $qty) {
            if ((int)$qty > 0) {
                $hasAnyQty = true;
                break;
            }
        }

        if (!$hasAnyQty) {
            return redirect()->back()->withInput()->with('error', 'Please enter at least one lost quantity.');
        }

        foreach ($lost_stock as $warehouse_id => $qty) {
            if ((int)$qty > 0) {
                $lost = MarkAsLostItem::create([
                    'part_no' => $part_no,
                    'product_id' => $product_id,
                    'item_name' => $item_name,
                    'mark_as_lost_qty' => $qty,
                    'warehouse_id' => $warehouse_id,
                    'reason' => $reason,
                    'user_id' => Auth::id(),
                ]);

                // 🔁 Call Zoho API per entry
                if ($reason) {
                    $zoho = new ZohoController();
                    $response =$zoho->zoho_mark_as_lost($lost->id); // 👈 pass the ID just saved
                }

                // ✅ Call inventory update logic
                $requestSubmit = new \Illuminate\Http\Request();
                $requestSubmit->merge([
                    'product_id' => $product_id
                ]);
                $this->inventoryProductEntry($requestSubmit);
            }
        }

        return redirect()->route('mark_as_lost.page')->with('success', 'Stock marked as lost and synced with Zoho.');
    }

    // mark as lost end
    
    public function inventoryProductEntry(Request $request){
        $getResetProductData = ResetInventoryProduct::where('product_id',$request->product_id)->first();
        if($getResetProductData == NULL){
          $productData = Product::where('id',$request->product_id)->first();
          $resetInventoryProductData = ResetInventoryProduct::where('product_id',$request->product_id)->where('part_no',$request->part_no)->first();
          if($resetInventoryProductData == NULL){
            $resetProduct = array();
            $resetProduct['product_id'] = $request->product_id;
            $resetProduct['part_no'] = $productData->part_no;
            ResetInventoryProduct::create($resetProduct);
          }
          
        }
        return 0;
    }
    


 public function generateBarcodePdf(Request $request)
{
    ini_set('pcre.backtrack_limit', 10000000);
    ini_set('pcre.recursion_limit', 10000000);

    try {
        $request->validate([
            'part_no'   => 'required',
            'qty'       => 'required|integer|min:1',
            'copies'    => 'required|integer|min:1',
            'name_mode' => 'nullable|in:item,barcode',
            'mrp'       => 'nullable|numeric|min:0',
        ]);

        $product = Product::where('part_no', $request->part_no)->firstOrFail();

        $qty    = (int) $request->qty;
        $copies = (int) $request->copies;

        // ----- Name selection logic -----
        $nameMode            = $request->input('name_mode', 'item'); // item | barcode
        $incomingBarcodeName = trim((string) $request->input('barcode_name', ''));
        $displayName         = $nameMode === 'barcode'
            ? ($incomingBarcodeName !== '' ? $incomingBarcodeName : (string) ($product->barcode_name ?? ''))
            : (string) $product->name;

        // Save barcode_name when provided
        if ($nameMode === 'barcode' && $incomingBarcodeName !== '') {
            $product->barcode_name = $incomingBarcodeName;
        }

        // ===== MRP logic (IMPORTANT) =====
        // The textbox already shows +20%. We print EXACTLY what user typed.
        // If textbox was empty (rare), fallback = (product->mrp or unit_price) +20%.
        $fallbackBase  = (float) ($product->mrp ?? 0);
        if ($fallbackBase <= 0) {
            $fallbackBase = (float) ($product->unit_price ?? 0);
        }
        $prefilledPlus20 = $fallbackBase > 0 ? ($fallbackBase * 1.20) : 0;

        $inputMrp = $request->filled('mrp')
            ? (float) $request->mrp               // already +20% from UI
            : (float) $prefilledPlus20;           // backend safety

        // If you want no decimals on the label:
        $mrpForPrint = round($inputMrp, 0);
        // If you want 2 decimals instead:
        // $mrpForPrint = number_format($inputMrp, 2, '.', '');

        // ----- Barcode generation -----
        $part_no_prefix = substr($product->part_no, 0, 7);
        $paddedQty      = str_pad($qty, 5, '0', STR_PAD_LEFT);

        $startCount = (int) $product->barcode_count + 1;
        $endCount   = $startCount + $copies - 1;

        $barcodes  = [];
        $generator = new BarcodeGeneratorPNG();

        for ($i = $startCount; $i <= $endCount; $i++) {
            do {
                $microTimestamp = (int) (microtime(true) * 1000000);
                $time5Digit     = substr($microTimestamp, -5);
                $randomNum      = mt_rand(10000, 99999);
                $barcodeNum     = $part_no_prefix . $paddedQty . 'B' . $time5Digit . $randomNum;

                $exists = Barcode::where('barcode', $barcodeNum)->exists();
                usleep(200);
            } while ($exists);

            Barcode::create(['barcode' => $barcodeNum]);

            $barcodes[] = [
                'code'  => $barcodeNum,
                'image' => base64_encode($generator->getBarcode($barcodeNum, $generator::TYPE_CODE_128)),
            ];
        }

        // Update product counters (and barcode_name if provided)
        $product->barcode_count = $endCount;
        $product->save();

        // ----- Optional sections -----
        $importedByChecked = $request->has('imported_by');
        $customImported = $importedByChecked ? [
            'company' => $request->custom_company,
            'address' => $request->custom_address,
            'phone'   => $request->custom_phone,
            'email'   => $request->custom_email,
        ] : null;

        $marketedByChecked = $request->boolean('marketed_by');
        $marketedBy = $marketedByChecked ? [
            'company' => $request->input('marketed_company'),
            'address' => $request->input('marketed_address'),
            'phone'   => $request->input('marketed_phone'),
            'email'   => $request->input('marketed_email'),
        ] : null;

        $countryOfOrigin = $request->input('country_of_origin');
        $mfgMonthYear = $request->filled('mfg_month_year')
            ? \Carbon\Carbon::parse($request->mfg_month_year)->format("M'y")
            : null;

        $fileName = 'barcode-label-' . now()->format('YmdHis') . '.pdf';

        $pdf = Pdf::loadView('backend.labels.barcode_combined', [
            'barcodes'     => $barcodes,
            'copies'       => $copies,

            // 👇 label me print hone wala MRP — EXACT same as textbox
            'mrp'          => $mrpForPrint,

            'part_no'      => $product->part_no,
            'qty'          => $qty,

            // 👇 use display_name in the blade
            'display_name' => $displayName,

            'marketed_by'           => 'ACE TOOLS PRIVATE LIMITED',
            'market_address_line2'  => 'Pal Colony, Village Rithala',
            'market_address_line3'  => 'NEW DELHI - 110085',
            'phone'                 => '9730377752',
            'imported_by_checked'   => $importedByChecked,
            'customImporter'        => $customImported,
            'country_of_origin'     => $countryOfOrigin,
            'mfg_month_year'        => $mfgMonthYear,
            'marketed_by_checked'   => $marketedByChecked,
            'marketedBy'            => $marketedBy,
        ], [], [
            'format'        => [50, 100],
            'orientation'   => 'portrait',
            'margin_top'    => 2,
            'margin_right'  => 2,
            'margin_bottom' => 2,
            'margin_left'   => 2,
        ]);

        $savePath = public_path('barcodes/' . $fileName);
        $pdf->save($savePath);

        return response()->json([
            'success' => true,
            'url'     => url('public/barcodes/' . $fileName)
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'PDF generation failed',
            'error'   => $e->getMessage()
        ], 500);
    }
}










   public function getCategoriesByGroup(Request $request)
   {
      try {
          \Log::info('Fetching categories for group_id: ' . $request->group_id);
          
          $categories = DB::table('categories')
                          ->where('category_group_id', $request->group_id)
                          ->select('id', 'name')
                          ->get();
                          
          return response()->json(['categories' => $categories]);
      } catch (\Exception $e) {
          \Log::error('Error fetching categories: ' . $e->getMessage());
          return response()->json(['error' => 'Something went wrong!'], 500);
      }
   }
   
  public function admin_products(Request $request) {

    CoreComponentRepository::instantiateShopRepository();



    $type        = 'In House';
    $col_name    = null;
    $query       = null;
    $sort_search = null;

    $products = Product::where('added_by', 'admin');

    if ($request->type != null) {
      $var       = explode(",", $request->type);
      $col_name  = $var[0];
      $query     = $var[1];
      $products  = $products->orderBy($col_name, $query);
      $sort_type = $request->type;
    }
    if ($request->search != null) {
      $sort_search = $request->search;
      $products    = $products
        ->where('name', 'like', '%' . $sort_search . '%')
        ->orWhereHas('stocks', function ($q) use ($sort_search) {
          $q->where('part_no', 'like', '%' . $sort_search . '%');
        });
    }

    $products = $products->orderBy('created_at', 'desc')->paginate(15);

    return view('backend.product.products.index', compact('products', 'type', 'col_name', 'query', 'sort_search'));
  }

  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function seller_products(Request $request) {
    $col_name    = null;
    $query       = null;
    $seller_id   = null;
    $sort_search = null;
    $products    = Product::where('added_by', 'seller');
    if ($request->has('user_id') && $request->user_id != null) {
      $products  = $products->where('user_id', $request->user_id);
      $seller_id = $request->user_id;
    }
    if ($request->search != null) {
      $products = $products
        ->where('name', 'like', '%' . $request->search . '%');
      $sort_search = $request->search;
    }
    if ($request->type != null) {
      $var       = explode(",", $request->type);
      $col_name  = $var[0];
      $query     = $var[1];
      $products  = $products->orderBy($col_name, $query);
      $sort_type = $request->type;
    }

    $products = $products->orderBy('created_at', 'desc')->paginate(15);
    $type     = 'Seller';

    return view('backend.product.products.index', compact('products', 'type', 'col_name', 'query', 'seller_id', 'sort_search'));
  }



public function all_products(Request $request)
{
    $col_name    = null;
    $query       = null;
    $seller_id   = null;
    $sort_search = null;

    $products = Product::orderBy('products.created_at', 'desc')
        ->leftJoin('uploads', 'uploads.id', '=', 'products.thumbnail_img')
        ->select('products.*', 'uploads.file_name as thumbnail_image');

    // ✅ Sirf manager_41 login ke liye product list restrict
    // (a) agar aapke paas helper hai:
    // $is41 = $this->isActingAs41Manager();
    // (b) warna inline title check:
    $title = strtolower(trim((string) optional(Auth::user())->user_title));
    $is41  = ($title === 'manager_41');

    if ($is41) {
        // Dono possible columns handle: is_manager_41 ya is_manger_41
        $products = $products->whereRaw('COALESCE(products.is_manager_41, products.is_manager_41) = 1');
    }

    if ($request->has('user_id') && $request->user_id != null) {
        $products  = $products->where('products.user_id', $request->user_id);
        $seller_id = $request->user_id;
    }

    if ($request->search != null) {
        $sort_search = strtolower(trim($request->search));

        if ($sort_search === 'unsync') {
            $products = $products->whereNull('products.zoho_item_id');
        } else {
            $products = $products->where(function ($q) use ($sort_search) {
                $q->where('products.name', 'like', '%' . $sort_search . '%')
                  ->orWhereHas('stocks', function ($q2) use ($sort_search) {
                      $q2->where('part_no', 'like', '%' . $sort_search . '%');
                  });
            });
        }
    }

    if ($request->type != null) {
        $var       = explode(",", $request->type);
        $col_name  = $var[0] ?? null;
        $query     = $var[1] ?? null;
        if ($col_name && $query) {
            $products = $products->orderBy($col_name, $query);
        }
        $sort_type = $request->type;
    }

    $products = $products->paginate(15);
    $type     = 'All';

    $seller = Seller::join('users', 'sellers.user_id', '=', 'users.id')
        ->orderBy('users.name', 'ASC')
        ->get(['sellers.id', 'users.name']);

    $category_group = CategoryGroup::orderBy('name','asc')->get();
    $brands         = Brand::where('name','!=','')->orderBy('name','asc')->get();

    return view('backend.product.products.index', compact(
        'products', 'type', 'col_name', 'query', 'seller_id', 'sort_search', 'seller', 'category_group', 'brands'
    ));
}

  public function back_all_products(Request $request) {


    $col_name    = null;
    $query       = null;
    $seller_id   = null;
    $sort_search = null;
    // $products    = Product::orderBy('created_at', 'desc');
    // Join the uploads table with the product table based on thumbnail_img and id
    $products = Product::orderBy('products.created_at', 'desc')
                ->leftJoin('uploads', 'uploads.id', '=', 'products.thumbnail_img')
                ->select('products.*', 'uploads.file_name as thumbnail_image');

    if ($request->has('user_id') && $request->user_id != null) {
      $products  = $products->where('user_id', $request->user_id);
      $seller_id = $request->user_id;
    }
   

    if ($request->search != null) {
        $sort_search = strtolower(trim($request->search));

        if ($sort_search === 'unsync') {
            $products = $products->whereNull('zoho_item_id');
        } else {
            $products = $products->where(function ($q) use ($sort_search) {
                $q->where('name', 'like', '%' . $sort_search . '%')
                  ->orWhereHas('stocks', function ($q2) use ($sort_search) {
                      $q2->where('part_no', 'like', '%' . $sort_search . '%');
                  });
            });
        }
    }
    if ($request->type != null) {
      $var       = explode(",", $request->type);
      $col_name  = $var[0];
      $query     = $var[1];
      $products  = $products->orderBy($col_name, $query);
      $sort_type = $request->type;
    }

    $products = $products->paginate(15);
    $type     = 'All';

    // $seller = Seller::with('user')->get();
    $seller = $sellers = Seller::join('users', 'sellers.user_id', '=', 'users.id')
    ->orderBy('users.name', 'ASC')
    ->get(['sellers.id', 'users.name']);
    $category_group=CategoryGroup::orderBy('name','asc')->get();
    $brands=Brand::where('name','!=','')->orderBy('name','asc')->get();
    // foreach($seller as $key=>$value){
    //   echo "<pre>"; print($value->user);
    // }
   // echo "<pre>";print_r($seller);die;
    // echo "<pre>";
    // print_r($products->toArray());
    // die();

    return view('backend.product.products.index', compact('products', 'type', 'col_name', 'query', 'seller_id', 'sort_search', 'seller', 'category_group', 'brands'));
  }


   private function isActingAs41Manager(): bool
{
    $user = Auth::user();
    if (!$user) {
        return false;
    }

    // Normalize
    $title = strtolower(trim((string) $user->user_title));
    $type  = strtolower(trim((string) $user->user_type));

    // Exactly check for manager_41 on current login
    if ($title === 'manager_41' || $type === 'manager_41') {
        return true;
    }

    // (Optional) tolerate common variants
    $aliases = ['41_manager'];
    return in_array($title, $aliases, true);
}

  public function no_images(Request $request) {
    $col_name    = null;
    $query       = null;
    $seller_id   = null;
    $sort_search = null;
    $products    = Product::whereNull('photos')->orderBy('created_at', 'desc');
    if ($request->has('user_id') && $request->user_id != null) {
      $products  = $products->where('user_id', $request->user_id);
      $seller_id = $request->user_id;
    }
    if ($request->search != null) {
      $sort_search = $request->search;
      $products    = $products
        ->where('name', 'like', '%' . $sort_search . '%')
        ->orWhereHas('stocks', function ($q) use ($sort_search) {
          $q->where('part_no', 'like', '%' . $sort_search . '%');
        });
    }
    if ($request->type != null) {
      $var       = explode(",", $request->type);
      $col_name  = $var[0];
      $query     = $var[1];
      $products  = $products->orderBy($col_name, $query);
      $sort_type = $request->type;
    }

    $products = $products->paginate(15);
    $type     = 'All';

    return view('backend.product.products.no-images', compact('products', 'type', 'col_name', 'query', 'seller_id', 'sort_search'));
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create() {
    CoreComponentRepository::initializeCache();

    $categories = Category::where('parent_id', 0)
      ->with('childrenCategories')
      ->get();

    return view('backend.product.products.create', compact('categories'));
  }

  public function add_more_choice_option(Request $request) {
    $all_attribute_values = AttributeValue::with('attribute')->where('attribute_id', $request->attribute_id)->get();

    $html = '';

    foreach ($all_attribute_values as $row) {
      $html .= '<option value="' . $row->value . '">' . $row->value . '</option>';
    }

    echo json_encode($html);
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(ProductRequest $request) {
    $product = $this->productService->store($request->except([
      '_token', 'warehouse_id', 'seller_sku', 'choice', 'tax_id', 'tax', 'tax_type', 'flash_deal_id', 'flash_discount', 'flash_discount_type',
    ]));
    $request->merge(['product_id' => $product->id]);

    //VAT & Tax
    if ($request->tax_id) {
      $this->productTaxService->store($request->only([
        'tax_id', 'tax', 'tax_type', 'product_id',
      ]));
    }

    //Flash Deal
    $this->productFlashDealService->store($request->only([
      'flash_deal_id', 'flash_discount', 'flash_discount_type',
    ]), $product);

    //Product Stock
    $this->productStockService->store($request->only([
      'warehouse_id', 'seller_id', 'colors_active', 'colors', 'choice_no', 'unit_price', 'carton_price', 'piece_per_carton', 'seller_sku', 'current_stock', 'seller_stock', 'product_id', 'part_no', 'model_no', 'cbm', 'carton_cbm', 'length', 'breadth', 'height', 'weight',
    ]), $product);

    // Product Translations
    $request->merge(['lang' => env('DEFAULT_LANGUAGE')]);
    ProductTranslation::create($request->only([
      'lang', 'name', 'unit', 'description', 'product_id',
    ]));

    flash(translate('Product has been inserted successfully'))->success();

    Artisan::call('view:clear');
    Artisan::call('cache:clear');

    return redirect()->route('products.admin');
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function show($id) {
    //
  }

  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function org_admin_product_edit(Request $request, $id) {


    CoreComponentRepository::initializeCache();

    $product = Product::findOrFail($id);

    $lang       = $request->lang;
    $tags       = json_decode($product->tags);

    $sellers = Seller::join('users', 'sellers.user_id', '=', 'users.id')->select('sellers.*', 'users.name as user_name')->get();

    $categoryGroups = CategoryGroup::orderBy('name','ASC')->get();

    $categories = Category::where('category_group_id', $product->group_id)->get();

    $warehouses =  Warehouse::orderBy('name','ASC')->get();

    $photos = array();
    if($product->photos != "" OR $product->photos != NULL){
      $proPhotosArray = explode(',',$product->photos);
      $photos = Upload::whereIn('id', $proPhotosArray)->get();
    }
    // print_r($photos);die;
    
    return view('backend.product.products.edit', compact('product', 'categoryGroups', 'categories', 'warehouses', 'photos', 'tags', 'sellers', 'lang'));
  }
  public function admin_product_edit(Request $request, $id)
  {

      CoreComponentRepository::initializeCache();


      $product = Product::findOrFail($id);

      $lang = $request->lang;
      $tags = json_decode($product->tags);

      $sellers = Seller::join('users', 'sellers.user_id', '=', 'users.id')
          ->select('sellers.*', 'users.name as user_name')
          ->get();

      $categoryGroups = CategoryGroup::orderBy('name', 'ASC')->get();

      $categories = Category::where('category_group_id', $product->group_id)->get();

      $warehouses = Warehouse::orderBy('name', 'ASC')->get();

      $photos = [];
      if ($product->photos != "" || $product->photos != null) {
          $proPhotosArray = explode(',', $product->photos);
          $photos = Upload::whereIn('id', $proPhotosArray)->get();
      }

     // Fetch product variations and attributes
    $attributeVariations = [];
    $combinedIds = array_unique(array_merge(
        json_decode($product->attributes, true) ?? [],
        json_decode($product->variations, true) ?? []
    ));

    if (!empty($combinedIds)) {
        // Fetch attribute values based on the combined IDs
        $attributeValues = AttributeValue::whereIn('id', $combinedIds)->get();

        // Map attributes and their values
        $attributeVariations = Attribute::whereIn('id', $attributeValues->pluck('attribute_id'))
            ->get()
            ->map(function ($attribute) use ($attributeValues) {
                return [
                    'attribute_id' => $attribute->id,
                    'attribute_name' => $attribute->name,
                    'is_variation' => $attribute->is_variation,
                    'values' => $attributeValues->where('attribute_id', $attribute->id)->pluck('value', 'id'),
                ];
            });
    }

    $allAttributes = Attribute::all();
    $allValues = AttributeValue::all();

          return view('backend.product.products.edit', compact(
              'product',
              'categoryGroups',
              'categories',
              'warehouses',
              'photos',
              'tags',
              'sellers',
              'lang',
              'attributeVariations',
             'allAttributes',
             'allValues'
          ));
  }




public function deleteFile($part_no)
{
    // Fetch all files linked to the given part_no
    $files = Upload::where('file_original_name', $part_no)->get();

    if ($files->isEmpty()) {
        return redirect()->back()->with('error', 'File not found.');
    }

    foreach ($files as $file) {
        // Construct the full file path
        $filePath = public_path($file->file_name);

        // Debugging: Check if the file path is correct
        \Log::info("Deleting file: " . $filePath);

        // Delete the actual file if it exists
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
    }

    // Delete file records from the `uploads` table
    Upload::where('file_original_name', $part_no)->forceDelete();


    // Update the `products` table (set `photos` and `thumbnail_img` to NULL)
    Product::where('part_no', $part_no)->update([
        'photos' => null,
        'thumbnail_img' => null
    ]);

    return redirect()->back()->with('success', 'Files deleted successfully.');
}

public function addVariation(Request $request)
{
    // Fetch the product
    $product = Product::findOrFail($request->product_id);

    $attributeId = null;
    $valueId = null;

    // Handle attribute selection or creation
    if (!empty($request->new_attribute)) {
        $attribute = Attribute::firstOrCreate(
            ['name' => $request->new_attribute],
            [
                'is_variation' => $request->is_variations ? 1 : 0, // Use checkbox value
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $attributeId = $attribute->id;
    } elseif (!empty($request->selected_attribute)) {
        $attributeId = $request->selected_attribute;
    }

    // Handle value selection or creation
    if (!empty($request->new_value)) {
        $value = AttributeValue::firstOrCreate(
            ['attribute_id' => $attributeId, 'value' => $request->new_value],
            ['created_at' => now(), 'updated_at' => now()]
        );
        $valueId = $value->id;
    } elseif (!empty($request->selected_value)) {
        $valueId = $request->selected_value;
    }

    // Add the attribute_values ID to the attributes column
    if ($valueId) {
        $existingAttributes = json_decode($product->attributes, true) ?? [];
        $existingAttributes[] = $valueId; // Add the value ID
        $product->attributes = json_encode(array_unique($existingAttributes));
    }

    // Add the attribute_values ID to the variations column if is_variation is 1
    if ($attributeId) {
        $attribute = Attribute::find($attributeId);

        if ($attribute && $attribute->is_variation == 1 && $valueId) {
            $existingVariations = json_decode($product->variations, true) ?? [];
            $existingVariations[] = $valueId;
            $product->variations = json_encode(array_unique($existingVariations));
        }
    }

    $product->save();

    return redirect()->back()->with('success', translate('Variation added successfully!'));
}





  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function seller_product_edit(Request $request, $id) {

    $product = Product::findOrFail($id);
    $lang    = $request->lang;
    $tags    = json_decode($product->tags);
    // $categories = Category::all();
    $categories = Category::where('parent_id', 0)
      ->with('childrenCategories')
      ->get();
    
    
    return view('backend.product.products.edit', compact('product', 'categories', 'tags', 'lang'));
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */

  public function update(ProductRequest $request, Product $product)
{
    // Update the variant_parent_part_no field (DB column: variation_parent_part_no)
    $variant_parent_part_no = $request->input('variant_parent_part_no');

    $part_no = $request->part_no;

    // Always re-fetch product by part_no (as you already do)
    $product = Product::where('part_no', $part_no)->firstOrFail();

    // Piece by carton logic
    if ($request->mrp == 0) {
        $piece_by_carton = 0;
    } else {
        $piece_by_carton = round(50000 / ($request->mrp - (($request->mrp * 24) / 100)));
    }

    // Main product update
    $product->update([
        'brand_id'                 => $request->brand_id,
        'group_id'                 => $request->group_id,
        'category_id'              => $request->category_id,
        'seller_id'                => $request->seller_id,
        'user_id'                  => 1,
        'meta_title'               => $request->meta_title ?? '',
        'meta_description'         => $request->meta_description ?? '',
        'meta_keywords'            => $request->meta_keywords ?? '',
        'slug'                     => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $request->name ?? '')),
        'choice_options'           => '[]',
        'colors'                   => '[]',
        'name'                     => $request->name ?? $product->name,
        'alias_name'               => $request->alias_name ?? $product->alias_name,
        'billing_name'             => $request->billing_name ?? $product->billing_name,
        'mrp'                      => $request->mrp ?? $product->mrp,
        'mrp_41_price'             => $request->has('mrp_41_price') ? trim((string) $request->mrp_41_price) : null,
        'warehouse_id'             => $request->warehouse_id,
        'current_stock'            => $request->current_stock ?? $product->current_stock ?? 0,
        'seller_stock'             => $request->seller_stock ?? $product->seller_stock ?? 0,
        'hsncode'                  => $request->hsncode ?? $product->hsncode,
        'tax'                      => $request->tax ?? $product->tax,
        'tax_type'                 => $request->tax_type ?? $product->tax_type,
        'generic_name'             => $request->generic_name ?? $product->generic_name,
        'weight'                   => $request->weight ?? $product->weight,
        'piece_by_carton'          => $piece_by_carton,
        'purchase_price'           => $request->purchase_price ?? $product->purchase_price,
        'approved'                 => $request->approved ?? $product->approved,
        'published'                => $request->published ?? $product->published,
        'description'              => $request->description,

        // ✅ Save main product images (from Product Images section)
        'photos'                   => $request->photos ?? $product->photos,
        'thumbnail_img'            => $request->thumbnail_img ?? $product->thumbnail_img,

        // ✅ Save Import Product Images (from new section)
        'import_photos'            => $request->import_photos ?? $product->import_photos,
        'import_thumbnail_img'     => $request->import_thumbnail_img ?? $product->import_thumbnail_img,

        // ✅ Save parent part no for variations
        'variation_parent_part_no' => $variant_parent_part_no ?? $product->variation_parent_part_no,
    ]);

    /*
     * ATTRIBUTE + VARIATIONS LOGIC (unchanged, just kept intact)
     */
    $attributes = [];
    $variations = [];

    // Fetch the updated `is_variation` data from the request
    $isVariationUpdates = $request->input('is_variation', []);

    if ($request->has('attribute_values')) {
        foreach ($request->input('attribute_values') as $attributeId => $values) {

            $attribute = \App\Models\Attribute::find($attributeId);
            if (!$attribute) {
                \Log::warning("Attribute not found: {$attributeId}");
                continue;
            }

            // Update is_variation based on the checkbox input
            if (isset($isVariationUpdates[$attributeId]) && $isVariationUpdates[$attributeId] == 1) {
                $attribute->update(['is_variation' => 1]);
            } else {
                $attribute->update(['is_variation' => 0]);
            }

            foreach ($values as $value) {
                // Check if the attribute value exists
                $existingValue = \App\Models\AttributeValue::where('attribute_id', $attribute->id)
                    ->where('value', $value)
                    ->first();

                if ($existingValue) {
                    // Add the value ID to attributes
                    $attributes[] = $existingValue->id;

                    // Add to variations if is_variation is 1
                    if ($attribute->is_variation == 1) {
                        $variations[] = $existingValue->id;
                    }
                } else {
                    // Create a new attribute value
                    $newValue = \App\Models\AttributeValue::create([
                        'attribute_id' => $attribute->id,
                        'value'        => $value,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);

                    $attributes[] = $newValue->id;

                    if ($attribute->is_variation == 1) {
                        $variations[] = $newValue->id;
                    }
                }
            }
        }
    }

    // Ensure attributes and variations are unique
    $attributes = array_unique($attributes);
    $variations = array_unique($variations);

    \Log::info('Final Attributes:', $attributes);
    \Log::info('Final Variations:', $variations);

    // Update attributes and variations in the product table
    $product->update([
        'attributes'       => json_encode($attributes),
        'variations'       => json_encode($variations),
        'variant_product'  => count($variations) > 0 ? 1 : 0,
    ]);

    /*
     * PRODUCT WAREHOUSE UPDATE
     */
    $productWarehouse = ProductWarehouse::where('part_no', $part_no)->first();
    if ($productWarehouse) {
        $productWarehouse->update([
            'warehouse_id'    => $request->warehouse_id,
            'seller_id'       => $request->seller_id,
            'hsncode'         => $request->hsncode ?? $product->hsncode,
            'seller_stock'    => 1,
            'sz_manual_price' => $request->mrp ?? $product->mrp,
            'variant'         => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $request->name ?? '')),
        ]);
    }

    /*
     * PRODUCT TRANSLATIONS
     */
    ProductTranslation::updateOrCreate(
        $request->only(['lang', 'product_id']),
        $request->only(['name', 'unit', 'description'])
    );

    /*
     * PUSH ITEM DATA TO SALEZING
     */
    $result = [];
    $result['part_no'] = $part_no;
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->post('https://mazingbusiness.com/api/v2/item-push', $result);

    \Log::info('Salzing Item Push Status: ' . json_encode($response->json(), JSON_PRETTY_PRINT));

    flash(translate('Product has been updated successfully'))->success();

    Artisan::call('view:clear');
    Artisan::call('cache:clear');

    return back();
}

  public function backups_update(ProductRequest $request, Product $product) {
    // updated on 19 nov 2025
    // Update the variant_parent_part_no field
    $variant_parent_part_no= $request->input('variant_parent_part_no');
 
    // echo "<pre>";print_r($request->all());die;
    $part_no = $request->part_no;
    $product = Product::where('part_no', $part_no)->first();
    if($request->mrp == 0){
      $piece_by_carton = 0;
    }else{
      $piece_by_carton = round(50000/($request->mrp - (($request->mrp*24)/100)));
    }
    $product->update([
        'brand_id' => $request->brand_id,
        'group_id' => $request->group_id,
        'category_id' => $request->category_id,
        'seller_id' => $request->seller_id,
        'user_id' => 1,
        'meta_title' => $request->meta_title ?? '',
         'meta_description' => $request->meta_description ?? '',
          'meta_keywords' => $request->meta_keywords ?? '',
        'slug' => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $request->name ?? '')),
        // 'attributes'=>'[]',
        // 'variations'=>'[]',
        'choice_options'=>'[]',
        'colors'=>'[]',
        'name' => $request->name ?? $product->name,
        'alias_name' => $request->alias_name ?? $product->alias_name,
        'billing_name' => $request-> billing_name?? $product->billing_name,                    
        'mrp' => $request->mrp ?? $product->mrp,
        'warehouse_id' => $request->warehouse_id,                    
        'current_stock' => $request->current_stock ?? $product->current_stock ?? 0,
        'seller_stock' => $request->seller_stock ?? $product->seller_stock ?? 0,
        'hsncode' => $request->hsncode ?? $product->hsncode,
        'tax' => $request->tax ?? $product->tax,
        'tax_type' => $request->tax_type ?? $product->tax_type,
        'generic_name' => $request->generic_name ?? $product->generic_name,
        'weight' => $request->weight ?? $product->weight,
        'piece_by_carton' => $piece_by_carton,
        'purchase_price' => $request->purchase_price ?? $product->purchase_price,
        'approved' => $request->approved ?? $product->approved,
        'published' => $request->published ?? $product->published,
        'description' => $request->description,
        
    ]);

        
        // Handle attributes and variations
            $attributes = [];
            $variations = [];

            // Fetch the updated `is_variation` data from the request
            $isVariationUpdates = $request->input('is_variation', []);

            if ($request->has('attribute_values')) {
                foreach ($request->input('attribute_values') as $attributeId => $values) {
                    foreach ($values as $value) {
                        // Find the attribute by ID
                        $attribute = \App\Models\Attribute::find($attributeId);

                        // If the attribute does not exist, skip this value
                        if (!$attribute) {
                            \Log::warning("Attribute not found: {$attributeId}");
                            continue;
                        }

                        // Update is_variation based on the checkbox input
                        if (isset($isVariationUpdates[$attributeId]) && $isVariationUpdates[$attributeId] == 1) {
                            $attribute->update(['is_variation' => 1]);
                        } else {
                            $attribute->update(['is_variation' => 0]);
                        }

                        // Check if the attribute value exists
                        $existingValue = \App\Models\AttributeValue::where('attribute_id', $attribute->id)
                            ->where('value', $value)
                            ->first();

                        if ($existingValue) {
                            // Add the value ID to attributes
                            $attributes[] = $existingValue->id;

                            // Add to variations if is_variation is 1
                            if ($attribute->is_variation == 1) {
                                $variations[] = $existingValue->id;
                            }
                        } else {
                            // If the value doesn't exist, create a new attribute value
                            $newValue = \App\Models\AttributeValue::create([
                                'attribute_id' => $attribute->id,
                                'value' => $value,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            // Add the new value's ID to attributes
                            $attributes[] = $newValue->id;

                            // Add to variations if is_variation is 1
                            if ($attribute->is_variation == 1) {
                                $variations[] = $newValue->id;
                            }
                        }
                    }
                }
            }

            // Ensure attributes and variations are unique
            $attributes = array_unique($attributes);
            $variations = array_unique($variations);

            // Debugging logs
            \Log::info('Final Attributes:', $attributes);
            \Log::info('Final Variations:', $variations);

            // Update attributes and variations in the product table
            $product->update([
                'attributes' => json_encode($attributes), // Store only attribute values
                'variations' => json_encode($variations), // Store variations where is_variation = 1
                'variant_product' => count($variations) > 0 ? 1 : 0,
            ]);

        // end of attribute and variations


    $productWarehouse = ProductWarehouse::where('part_no', $part_no)->first();
    $productWarehouse->update([
        'warehouse_id' => $request->warehouse_id,
        'seller_id' => $request->seller_id,
        'hsncode' => $request->hsncode ?? $product->hsncode,
        'seller_stock' => 1,
        'sz_manual_price' => $request->mrp ?? $product->mrp,
        'variant' => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $request->name ?? '')),
    ]);

    //Product
    // $product = $this->productService->update($request->except([
    //   '_token', 'sku', 'choice', 'tax_id', 'tax', 'tax_type', 'flash_deal_id', 'flash_discount', 'flash_discount_type',
    // ]), $product);

    // //Product Stock
    // foreach ($product->stocks as $key => $stock) {
    //   $stock->delete();
    // }

    // $request->merge(['product_id' => $product->id]);
    // $this->productStockService->store($request->only([
    //   'colors_active', 'colors', 'choice_no', 'unit_price', 'sku', 'current_stock', 'product_id',
    // ]), $product);

    // //Flash Deal
    // $this->productFlashDealService->store($request->only([
    //   'flash_deal_id', 'flash_discount', 'flash_discount_type',
    // ]), $product);

    // //VAT & Tax
    // if ($request->tax_id) {
    //   ProductTax::where('product_id', $product->id)->delete();
    //   $this->productTaxService->store($request->only([
    //     'tax_id', 'tax', 'tax_type', 'product_id',
    //   ]));
    // }

    // Product Translations
    ProductTranslation::updateOrCreate(
      $request->only([
        'lang', 'product_id',
      ]),
      $request->only([
        'name', 'unit', 'description',
      ])
    );

    // Push item data to Salezing
    $result=array();
    $result['part_no']= $part_no;
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->post('https://mazingbusiness.com/api/v2/item-push', $result);
    \Log::info('Salzing Item Push Status: '  . json_encode($response->json(), JSON_PRETTY_PRINT));
    
    flash(translate('Product has been updated successfully'))->success();

    Artisan::call('view:clear');
    Artisan::call('cache:clear');

    return back();
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id) {
    $product = Product::findOrFail($id);

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

  public function bulk_product_delete(Request $request) {
    if ($request->id) {
      foreach ($request->id as $product_id) {
        $this->destroy($product_id);
      }
    }

    return 1;
  }

  /**
   * Duplicates the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function duplicate(Request $request, $id) {
    $product = Product::find($id);

    $product_new       = $product->replicate();
    $product_new->slug = $product_new->slug . '-' . Str::random(5);
    $product_new->save();

    //Product Stock
    $this->productStockService->product_duplicate_store($product->stocks, $product_new);

    //VAT & Tax
    $this->productTaxService->product_duplicate_store($product->taxes, $product_new);

    flash(translate('Product has been duplicated successfully'))->success();
    if ($request->type == 'In House') {
      return redirect()->route('products.admin');
    } elseif ($request->type == 'Seller') {
      return redirect()->route('products.seller');
    } elseif ($request->type == 'All') {
      return redirect()->route('products.all');
    }

  }

  public function get_products_by_brand(Request $request) {
    $products = Product::where('brand_id', $request->brand_id)->get();
    return view('partials.product_select', compact('products'));
  }

  public function updateTodaysDeal(Request $request) {
    $product              = Product::findOrFail($request->id);
    $product->todays_deal = $request->status;
    $product->save();
    Cache::forget('todays_deal_products');
    return 1;
  }

  public function updatePublished(Request $request) {
    $product            = Product::findOrFail($request->id);
    $product->published = $request->status;

    if ($product->added_by == 'seller' && addon_is_activated('seller_subscription') && $request->status == 1) {
      $shop = $product->user->shop;
      if (
        $shop->package_invalid_at == null
        || Carbon::now()->diffInDays(Carbon::parse($shop->package_invalid_at), false) < 0
        || $shop->product_upload_limit <= $shop->user->products()->where('published', 1)->count()
      ) {
        return 0;
      }
    }

    $product->save();

    Artisan::call('view:clear');
    Artisan::call('cache:clear');
    return 1;
  }

  public function updateProductApproval(Request $request) {
    $product           = Product::findOrFail($request->id);
    $product->approved = $request->approved;

    if ($product->added_by == 'seller' && addon_is_activated('seller_subscription')) {
      $shop = $product->user->shop;
      if (
        $shop->package_invalid_at == null
        || Carbon::now()->diffInDays(Carbon::parse($shop->package_invalid_at), false) < 0
        || $shop->product_upload_limit <= $shop->user->products()->where('published', 1)->count()
      ) {
        return 0;
      }
    }

    $product->save();

    Artisan::call('view:clear');
    Artisan::call('cache:clear');
    return 1;
  }

  public function updateFeatured(Request $request) {
    $product           = Product::findOrFail($request->id);
    $product->featured = $request->status;
    if ($product->save()) {
      Artisan::call('view:clear');
      Artisan::call('cache:clear');
      return 1;
    }
    return 0;
  }
  public function updateOwnBrandPublished(Request $request) {
      $product           = OwnBrandProduct::findOrFail($request->id);
      $product->published = $request->status;
      if ($product->save()) {
        Artisan::call('view:clear');
        Artisan::call('cache:clear');
        return 1;
      }
      return 0;
  }
  public function updateOwnBrandProductApproval(Request $request) {
      $product           = OwnBrandProduct::findOrFail($request->id);
      $product->approved = $request->approved;
      if ($product->save()) {
        Artisan::call('view:clear');
        Artisan::call('cache:clear');
        return 1;
      }
      return 0;
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
        // foreach (json_decode($request[$name][0]) as $key => $item) {
        foreach ($request[$name] as $key => $item) {
          // array_push($data, $item->value);
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
        // foreach (json_decode($request[$name][0]) as $key => $item) {
        foreach ($request[$name] as $key => $item) {
          // array_push($data, $item->value);
          array_push($data, $item);
        }
        array_push($options, $data);
      }
    }

    $combinations = Combinations::makeCombinations($options);
    return view('backend.product.products.sku_combinations_edit', compact('combinations', 'unit_price', 'colors_active', 'product_name', 'product'));
  }

  public function exportDataToGoogleSheet(Request $request)
  {
    $seller_id = $request->input('seller_id') ?? 0;
    $category_group_id = $request->input('category_group_id') ?? 0;
    $category_id = $request->input('category_id') ?? 0;
    $brand_id = $request->input('brand_id') ?? 0;
    $stock = $request->input('stock') ?? 2;
    // Fetch product data from the database
    $products = Product::query()
    ->join('category_groups', 'products.group_id', '=', 'category_groups.id')
    ->join('categories', 'products.category_id', '=', 'categories.id')
    ->join('brands', 'products.brand_id', '=', 'brands.id')
    ->join('warehouses', 'products.warehouse_id', '=', 'warehouses.id')
    ->join('sellers', 'products.seller_id', '=', 'sellers.id') // Join with sellers table
    ->join('users', 'sellers.user_id', '=', 'users.id') // Join with users table using sellers.user_id
    ->when(!empty($seller_id) && $seller_id != 0, function($query) use ($seller_id) {
        $query->whereIn('products.seller_id', $seller_id);
    })
    ->when(!empty($category_group_id) && $category_group_id != 0, function($query) use ($category_group_id) {
        $query->whereIn('products.group_id', $category_group_id);
    })
    ->when(!empty($category_id) && $category_id != 0, function($query) use ($category_id) {
        $query->whereIn('products.category_id', $category_id);
    })
    ->when(!empty($brand_id) && $brand_id != 0, function($query) use ($brand_id) {
        $query->whereIn('products.brand_id', $brand_id);
    })
    ->when(!empty($stock), function($query) use ($stock) {
        if ($stock == 1) {
            $query->where('products.current_stock', '>', 0); // Ensure products with stock
        } elseif ($stock == 0) {
            $query->where('products.current_stock', '<=', 0); // Ensure products without stock
        }
    })
    ->select(
        'products.*', 
        'brands.name as brand_name', 
        'category_groups.name as category_group_name',
        'categories.name as category_name', 
        'warehouses.name as warehouse_name',
        'users.name as seller_name' // Fetch the user's name through sellers and users tables
    )
    ->orderBy('products.name', 'ASC')
    ->get();


    // Convert the data to an array of arrays									
    $values = $products->map(function ($product) {
        return [
            $product->part_no ?? '',
            $product->brand_name ?? '',
            $product->name ?? '',
            $product->alias_name ?? '',
            $product->billing_name ?? '',            
            $product->category_group_name ?? '',
            $product->category_name ?? '', 
            $product->mrp ?? '',
            $product->warehouse_name ?? '',            
            $product->seller_name ?? '',
            $product->seller_stock ?? '',
            $product->hsncode ?? '',
            $product->tax ?? '',
            $product->generic_name ?? '',
            $product->weight ?? '',
            $product->purchase_price ?? '',
            $product->cash_and_carry_item ?? ''            
        ];
    })->toArray();
    // dd($values);
    
    // Clear previous data
    $clearRange = config('sheets.clear_range');
    $this->sheetsService->clearData($clearRange);

    // Specify the range where you want to start inserting the data (e.g., starting from cell A1)
    $range = config('sheets.range');

    // Append data to Google Sheets
    $this->sheetsService->appendData($range, $values);

    return response()->json(['status' => 'success']);
  }

  public function updateProductsFromGoogleSheet()
  {
      // Specify the range of data you want to fetch from the Google Sheet
      $range = config('sheets.get_data_range'); // e.g., 'Sheet1!A2:M' to fetch from A2 to M

      // Fetch data from Google Sheets
      $rows = $this->sheetsService->getData($range);

      // Process each row of data
      foreach ($rows as $row) {
          // Assuming the following order of data in the sheet:
          $part_no = $row[0]; // part_no should be in the first column
          $brand_name =  $row[1];
          $category_group_name = $row[5];
          $category_name = $row[6];
          $warehouse_name = $row[8];
          $seller_name = $row[9];          

          $brandData = Brand::where('name',$brand_name)->first();
          if (!$brandData) {
              $slug = strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $brand_name ?? ''));
              $brandData = Brand::create(['name' => $brand_name,'slug' => $slug]);
          }
          $brand_id = $brandData->id;
          $categoryData = Category::where('name', $category_name)->first();
          $catGroupData = CategoryGroup::where('name', $category_group_name)->first();
          if (!$catGroupData) {
			  $slug = strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $category_group_name ?? ''));
              $catGroupData = CategoryGroup::create(['name' => $category_group_name,'slug' => $slug]);
              if (!$categoryData) {
				 $slug = strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $category_name ?? '')); 
                 $categoryData = Category::create(['name' => $category_name,'category_group_id'=>$catGroupData->id,'slug' => $slug]);
              }
          }
          $category_group_id = $catGroupData->id;
          $categoryData = Category::where('name', $category_name)->first();
          if (!$categoryData) {
			  $slug = strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $category_name ?? ''));
              $categoryData = Category::create(['name' => $category_name,'category_group_id'=>$catGroupData->id,'slug' => $slug]);
          }
          $category_id = $categoryData->id;

          $sellerUserData = User::where('name', $seller_name)->first();
          $sellerData = Seller::where('user_id',$sellerUserData->id)->first();
          $seller_id = $sellerData->id;

          $warehouseData = Warehouse::where('name', $warehouse_name)->first();
          $warehouse_id = $warehouseData->id;
          
          if($row[7] == 0){
            $piece_by_carton = 0;
          }else{
            $piece_by_carton = round(50000/($row[7] - (($row[7]*24)/100)));
          }

          if($part_no != ""){           
            // Find the product by part_no
            $product = Product::where('part_no', $part_no)->first();
            // If the product exists, update its details
            if ($product) {                
                $product->update([
                    'brand_id' => $brand_id,
                    'group_id' => $category_group_id,
                    'category_id' => $category_id,
                    'seller_id' => $seller_id,
                    'user_id' => 1,
                    'meta_title' => $row[2] ?? '',
                    'slug' => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $row[2] ?? '')),
                    'attributes'=>'[]',
                    'choice_options'=>'[]',
                    'colors'=>'[]',
                    'variations'=>'[]',
                    'name' => $row[2] ?? $product->name,
                    'alias_name' => $row[3] ?? $product->alias_name,
                    'billing_name' => $row[4] ?? $product->billing_name,                    
                    'mrp' => $row[7] ?? $product->mrp,
                    'warehouse_id' => $warehouse_id,                    
                    'seller_stock' => $row[10] ?? $product->seller_stock,
                    'hsncode' => $row[11] ?? $product->hsncode,
                    'tax' => $row[12] ?? $product->tax,
                    'generic_name' => $row[13] ?? $product->generic_name,
                    'weight' => $row[14] ?? $product->weight,
                    'piece_by_carton' => $piece_by_carton,
                    'purchase_price' => $row[15] ?? $product->purchase_price,
                    'cash_and_carry_item' => $row[16] ?? $product->cash_and_carry_item,
                ]);
                $productWarehouse = ProductWarehouse::where('part_no', $part_no)->first();
                $productWarehouse->update([
                    'warehouse_id' => $warehouse_id,
                    'seller_id' => $seller_id,
                    'hsncode' => $row[11] ?? $product->hsncode,
                    'seller_stock' => 1,
                    'sz_manual_price' => $row[7] ?? $product->mrp,
                    'variant' => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $row[2] ?? '')),
                ]);

                // Trigger Zoho items update
                $zoho = new ZohoController(); // adjust namespace if needed
                $zoho->updateItemInZoho($part_no);
            }
          }else{
            $productData = Product::orderBy('part_no','DESC')->first();
            $part_no = 'MZ'.(str_replace('MZ', '', $productData->part_no) + 1);
            $data = [
                'brand_id' => $brand_id,
                'group_id' => $category_group_id,
                'category_id' => $category_id,
                'seller_id' => $seller_id,
                'user_id' => 1,
                'meta_title' => $row[2] ?? '',
                'slug' => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $row[2] ?? '')),
                'attributes'=>'[]',
                'choice_options'=>'[]',
                'colors'=>'[]',
                'variations'=>'[]',
                'part_no' => $part_no,
                'name' => $row[2] ?? '',
                'alias_name' => $row[3] ?? '',
                'billing_name' => $row[4] ?? '',
                'mrp' => $row[7] ?? '',
                'warehouse_id' => $warehouse_id ?? '',
                'seller_stock' => $row[10] ?? '',
                'hsncode' => $row[11] ?? '',
                'tax' => $row[12] ?? '',
                'generic_name' => $row[13] ?? '',
                'weight' => $row[14] ?? '',
                'piece_by_carton' => $piece_by_carton,
                'purchase_price' => $row[15] ?? '',
                'cash_and_carry_item' => $row[16] ?? '0',
            ];
            $productData = Product::create($data);

            $data_pw = [
                'product_id' => $productData->id,
                'part_no' => $part_no,
                'warehouse_id' => $warehouse_id,
                'seller_id' => $seller_id,
                'hsncode' => $row[11] ?? $product->hsncode,
                'seller_stock' => 1,
                'sz_manual_price' => $row[7] ?? $product->mrp,
                'variant' => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $row[2] ?? '')),
            ];
            // Create a new product record in the database
            ProductWarehouse::create($data_pw);            
          }

          //  Call Zoho new item push after creating product
            $zoho = new ZohoController();
            $zoho->newItemPushInZoho($part_no);

          // Push item data to Salezing
          // $result=array();
          // $result['part_no']= $part_no;
          //print_r($result);
          // $response = Http::withHeaders([
          //     'Content-Type' => 'application/json',
          // ])->post('https://mazingbusiness.com/api/v2/item-push', $result);
          // \Log::info('Salzing Item Push Status: '  . json_encode($response->json(), JSON_PRETTY_PRINT));
      }
      return response()->json(['status' => 'success']);
  }

  public function allOwnBrandProducts(Request $request) {
   
    $col_name    = null;
    $query       = null;
    $seller_id   = null;
    $sort_search = null;
    $products    = OwnBrandProduct::orderBy('created_at', 'desc');
    
    if ($request->search != null) {
      $sort_search = $request->search;
      $products    = $products
        ->where('name', 'like', '%' . $sort_search . '%')
        ->orWhere('part_no', 'like', '%' . $sort_search . '%');
    }
    if ($request->type != null) {
      $var       = explode(",", $request->type);
      $col_name  = $var[0];
      $query     = $var[1];
      $products  = $products->orderBy($col_name, $query);
      $sort_type = $request->type;
    }

    $products = $products->paginate(15);
    $type     = 'All';

    $category_group=OwnBrandCategoryGroup::orderBy('name','asc')->get();

    return view('backend.product.products.allOwnBrandProducts', compact('products', 'type', 'col_name', 'query',  'sort_search', 'category_group'));
  }
  
  public function createOrUpdateTheOwnBrandProductsFromGoogleSheet()
  {
      // Specify the range of data you want to fetch from the Google Sheet
      $range = config('sheets.get_own_brand_data_range'); // e.g., 'Sheet1!A2:M' to fetch from A2 to M
      // $range = 'OwnBrandProduct'; // Fetches the entire sheet dynamically
      $rows = $this->sheetsService->getData($range);
      // Fetch data from Google Sheets
      // $rows = $this->sheetsService->getData($range);
      // echo "<pre>";print_r($rows);die;
      // Process each row of data
      foreach ($rows as $row) {
        // echo "<pre>"; print_r($row[17]);die;
        // Assuming the following order of data in the sheet:
        $part_no = !empty($row[0]) ? $row[0] : ''; // part_no should be in the first column
        $product_name =  !empty($row[1]) ? $row[1] : '';
        $alias_name = !empty($row[2]) ? $row[2] : '';;
        $category_group_name = !empty($row[3]) ? $row[3] : '';
        $category_name = !empty($row[4]) ? $row[4] : '';
        $dollar_purchase_price = !empty($row[5]) ? $row[5] : '';
        $inr_br = !empty($row[6]) ? $row[6] : '';
        $inr_sl = !empty($row[7]) ? $row[7] : '';
        $inr_go = !empty($row[8]) ? $row[8] : '';
        $doller_br = !empty($row[9]) ? $row[9] : '';
        $doller_sl = !empty($row[10]) ? $row[10] : '';
        $doller_go = !empty($row[11]) ? $row[11] : '';
        $weight = !empty($row[12]) ? $row[12] : '';
        $moq1 = !empty($row[13]) ? $row[13] : '';
        $moq2 = !empty($row[14]) ? $row[14] : '';
        $compatable_model = !empty($row[15]) ? $row[15] : '';
        $country_origin = !empty($row[16]) ? $row[16] : '';
        $cbm = !empty($row[17]) ? $row[17] : '';
        $description = !empty($row[18]) ? $row[18] : '';
        // print_r($product);die;
        $categoryData = OwnBrandCategory::where('name', $category_name)->first();
        $catGroupData = OwnBrandCategoryGroup::where('name', $category_group_name)->first();
        if (!$catGroupData) {
            $catGroupData = OwnBrandCategoryGroup::create(['name' => $category_group_name,'slug'=>strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $category_group_name ?? ''). '-' . Str::random(5))]);
            if (!$categoryData) {
              $categoryData = OwnBrandCategory::create(['name' => $category_name,'category_group_id'=>$catGroupData->id,'slug'=>strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $category_name ?? ''). '-' . Str::random(5))]);
            }
        }
        $category_group_id = $catGroupData->id;
        $categoryData = OwnBrandCategory::where('name', $category_name)->first();
        if (!$categoryData) {
            $categoryData = OwnBrandCategory::create(['name' => $category_name,'category_group_id'=>$catGroupData->id,'slug'=>strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $category_name ?? ''). '-' . Str::random(5))]);
        }
        $category_id = $categoryData->id;          

        if($part_no != ""){           
          // Find the product by part_no
          $product = OwnBrandProduct::where('part_no', $part_no)->first();
          
          // If the product exists, update its details
          if ($product !== NULL) {                
              $product->update([
                  'part_no' => $part_no,
                  'alias_name' => $alias_name,
                  'dollar_purchase_price' => $dollar_purchase_price,
                  'name' => $product_name,
                  'group_id' => $category_group_id,
                  'category_id' => $category_id,
                  'slug' => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $product_name ?? ''). '-' . Str::random(5)),
                  'description'=>$description,
                  'min_order_qty_1'=>$moq1,
                  'min_order_qty_2'=>$moq2,
                  'weight'=>$weight,
                  'country_of_origin' => $country_origin,
                  'compatable_model' => $compatable_model,
                  'cbm' => $cbm,
                  'inr_bronze' => $inr_br,
                  'inr_silver' => $inr_sl,
                  'inr_gold' => $inr_go,
                  'doller_bronze' => $doller_br,
                  'doller_silver' => $doller_sl,
                  'doller_gold' => $doller_go,
              ]);
          }
        }else{
          $productData = OwnBrandProduct::orderBy('part_no','DESC')->first();
          if($productData!==NULL){
            $number = str_replace('IMZ', '', $productData->part_no) + 1;
            $number = str_pad($number, 5, '0', STR_PAD_LEFT);
            $part_no = 'IMZ'.$number;
          }else{
            $part_no = 'IMZ00001';
          }

          $product = new OwnBrandProduct();
          $product->part_no = $part_no;
          $product->alias_name = $alias_name;
          $product->dollar_purchase_price = $dollar_purchase_price;
          $product->name = $product_name;
          $product->group_id = $category_group_id;
          $product->category_id = $category_id;
          $product->slug = strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $product_name ?? ''). '-' . Str::random(5));
          $product->description = $description;
          $product->min_order_qty_1 = $moq1;
          $product->min_order_qty_2 = $moq2;
          $product->weight = $weight;
          $product->country_of_origin = $country_origin;
          $product->compatable_model = $compatable_model;
          $product->cbm = $cbm;
          $product->inr_bronze = $inr_br;
          $product->inr_silver = $inr_sl;
          $product->inr_gold = $inr_go;
          $product->doller_bronze = $doller_br;
          $product->doller_silver = $doller_sl;
          $product->doller_gold = $doller_go;
          $product->save();
          
          // $data = [
          //     'part_no' => $part_no,
          //     'alias_name' => $alias_name,
          //     'dollar_purchase_price' => $dollar_purchase_price,
          //     'name' => $product_name,
          //     'group_id' => $category_group_id,
          //     'category_id' => $category_id,
          //     'slug' => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $product_name ?? ''). '-' . Str::random(5)),
          //     'description'=>$description,
          //     'min_order_qty_1'=>$moq1,
          //     'min_order_qty_2'=>$moq2,
          //     'weight'=>$weight,
          //     'country_of_origin' => $country_origin,
          //     'compatable_model' => $compatable_model,
          //     'cbm' => $cbm,
          //     'inr_bronze' => $inr_br,
          //     'inr_silver' => $inr_sl,
          //     'inr_gold' => $inr_go,
          //     'doller_bronze' => $doller_br,
          //     'doller_silver' => $doller_sl,
          //     'doller_gold' => $doller_go,
          // ];
          
          // $productData = OwnBrandProduct::create($data); dd($data);           
        }
        // Push item data to Salezing
        // $result=array();
        // $result['part_no']= $part_no;

        //print_r($result);
        // $response = Http::withHeaders([
        //     'Content-Type' => 'application/json',
        // ])->post('https://mazingbusiness.com/api/v2/item-push', $result);
        // \Log::info('Salzing Item Push Status: '  . json_encode($response->json(), JSON_PRETTY_PRINT));
      }
      return response()->json(['status' => 'success']);
  }

  public function own_brand_product_edit(Request $request, $id) {
    CoreComponentRepository::initializeCache();

    $product = OwnBrandProduct::findOrFail($id);

    $lang       = $request->lang;
    $tags       = json_decode($product->tags);

    $categoryGroups = OwnBrandCategoryGroup::orderBy('name','ASC')->get();
    $categories = OwnBrandCategory::where('category_group_id', $product->group_id)->get();
    $photos = array();
    if($product->photos != "" OR $product->photos != NULL){
      $proPhotosArray = explode(',',$product->photos);
      $photos = Upload::whereIn('id', $proPhotosArray)->get();
    }
    // print_r($photos);die;
    
    return view('backend.product.products.own_brand_product_edit', compact('product', 'categoryGroups', 'categories', 'photos', 'lang'));
  }

  public function ownBrandProductUpdate(OwnBrandProductRequest $request, Product $product) {

    // echo "<pre>";print_r($request->all());die;
    $part_no = $request->part_no;
    $product = OwnBrandProduct::where('part_no', $part_no)->first();
    
    $product->update([
        'part_no' => $request->part_no,
        'alias_name' => $request->alias_name,
        'mrp' => $request->mrp,
        'name' => $request->name,
        'group_id' =>  $request->group_id,
        'category_id' => $request->category_id,
        'slug' => strtolower(str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $request->name ?? '')),
        'description'=>$request->description,
        'approved'=>$request->approved,
        'min_order_qty_1'=>$request->min_order_qty_1,
        'min_order_qty_2'=>$request->min_order_qty_2,
        'weight' => $request->weight ?? $product->weight,
        'country_of_origin' => $request->country_of_origin ?? $product->country_of_origin,
        'compatable_model' => $request-> compatable_model?? $product->compatable_model,                    
        'cbm' => $request->cbm ?? $product->cbm,
        'inr_bronze' => $request->inr_bronze ?? $product->inr_bronze,                    
        'inr_silver' => $request->inr_silver ?? $product->inr_silver,
        'inr_gold' => $request->inr_gold ?? $product->inr_gold,
        'doller_bronze' => $request->doller_bronze ?? $product->doller_bronze,
        'doller_silver' => $request->doller_silver ?? $product->doller_silver,
        'doller_gold' => $request->doller_gold ?? $product->doller_gold,
        'meta_title' => $request->meta_title ?? $product->meta_title,
        'meta_keywords' => $request->meta_keywords ?? $product->meta_keywords,
        'meta_description' => $request->meta_description,
        'published' => $request->published ?? $product->published
    ]);

    // Product Translations
    // ProductTranslation::updateOrCreate(
    //   $request->only([
    //     'lang', 'product_id',
    //   ]),
    //   $request->only([
    //     'name', 'unit', 'description',
    //   ])
    // );

    // // Push item data to Salezing
    // $result=array();
    // $result['part_no']= $part_no;
    // $response = Http::withHeaders([
    //     'Content-Type' => 'application/json',
    // ])->post('https://mazingbusiness.com/api/v2/item-push', $result);
    // \Log::info('Salzing Item Push Status: '  . json_encode($response->json(), JSON_PRETTY_PRINT));
    
    flash(translate('Product has been updated successfully'))->success();

    Artisan::call('view:clear');
    Artisan::call('cache:clear');

    return back();
  }

  public function ownBrandProductDelete($id) {
    $product = OwnBrandProduct::findOrFail($id);

    if (OwnBrandProduct::destroy($id)) {
      // Cart::where('product_id', $id)->delete();

      flash(translate('Product has been deleted successfully'))->success();

      Artisan::call('view:clear');
      Artisan::call('cache:clear');

      return back();
    } else {
      flash(translate('Something went wrong'))->error();
      return back();
    }
  }


/**
 * Manager-41 closing stock listing (no debit note part).
 */
public function manager41ClosingStock(Request $request)
{
    $search_text = $request->search_text;

    $warehouses = Warehouse::where('active', 1)
        ->orderBy('id', 'ASC')
        ->get();

    // Base list: distinct part_no (+ a stable name)
    $productsQ = Manager41ProductStock::query();

    if (!empty($search_text)) {
        $s = trim($search_text);
        $productsQ->where(function ($q) use ($s) {
            $q->where('part_no', 'like', "%{$s}%")
              ->orWhere('name', 'like', "%{$s}%");
        });
    }

    // Distinct part numbers with a representative name
    $products = $productsQ
        ->select('part_no', DB::raw('MAX(name) as name'))
        ->groupBy('part_no')
        ->orderBy('name', 'ASC')
        ->paginate(15);

    // Enrich each row with per-warehouse figures (no debit-note)
    $products->getCollection()->transform(function ($product) use ($warehouses) {
        $warehouseData = [];

        // get product_id once for challan linkage (if Product exists)
        $productId = Product::where('part_no', $product->part_no)->value('id');

        foreach ($warehouses as $warehouse) {
            // Branch (mirror) stock for this godown
            $branchStock = Manager41ProductStock::where('part_no', $product->part_no)
                ->where('godown', $warehouse->name) // godown stores warehouse name
                ->value('closing_stock');

            // Opening stock (match either warehouse_id or stored godown name)
            $openingQty = Manager41OpeningStock::where('part_no', $product->part_no)
                ->where(function ($q) use ($warehouse) {
                    $q->where('warehouse_id', $warehouse->id)
                      ->orWhere('godown', $warehouse->name);
                })
                ->sum('closing_stock');

            // Purchases (sum qty) for this warehouse
            $purchaseQty = Manager41PurchaseInvoiceDetail::where('part_no', $product->part_no)
                ->whereHas('purchaseInvoice', function ($q) use ($warehouse) {
                    $q->where('warehouse_id', $warehouse->id);
                })
                ->sum('qty');

            // Sales via challans (sum quantity) for this warehouse
            $saleQty = 0;
            if ($productId) {
                $saleQty = Manager41ChallanDetail::where('product_id', $productId)
                    ->whereHas('challan', function ($q) use ($warehouse) {
                        $q->where('warehouse_id', $warehouse->id);
                    })
                    ->sum('quantity');
            }

            $warehouseData[] = [
                'warehouse_id'   => $warehouse->id,
                'warehouse_name' => $warehouse->name,
                'branch_stock'   => (float) ($branchStock ?? 0),
                'opening_stock'  => (float) $openingQty,
                'purchase_qty'   => (float) $purchaseQty,
                'sale_qty'       => (float) $saleQty,
                'debit_note_qty' => 0,     // <- add this
                // ❌ no debit note in Manager-41 view
            ];
        }

        $product->warehouse = $warehouseData;
        return $product;
    });
    $isManager41 = method_exists($this, 'isActingAs41Manager') && $this->isActingAs41Manager();

    

    return view('backend.product.closingStock.index', compact('products', 'warehouses', 'search_text','isManager41'));
}
  public function closingStock(Request $request){

     // If this login/session is acting as Manager-41, route to the M41 variant
    if ($this->isActingAs41Manager()) {
        return $this->manager41ClosingStock($request);
    }

    $search_text = $request->search_text;
    
    $warehouses = Warehouse::where('active', 1)->orderBy('id', 'ASC')->get();

    $products = ProductApi::with('productDetails');

    if ($request->search_text != "") {
        $search = $request->search_text;

        $products = $products->where(function ($query) use ($search) {
            $query->where('part_no', 'like', "%$search%")
                  ->orWhere('name', 'like', "%$search%");
        });
    }

    // Use distinct part_no with name for safe pagination
    $products = $products->select('part_no', 'name')->distinct()->orderBy('name', 'ASC')->paginate(15);

    // Now transform each product to append warehouse stock
    $products->getCollection()->transform(function ($product) use ($warehouses) {
        $warehouseData = [];
        foreach ($warehouses as $warehouse) {
            $stockData = ProductApi::with('openingProductStock', 'purchaseQty', 'saleQty.challan')->where('part_no', $product->part_no)
                ->where('godown', $warehouse->name) // this is correct if godown stores warehouse name
                ->first();
            $saleQty = $stockData ? $stockData->saleQtyForWarehouse($warehouse->id)->get() : collect();
            $purchaseQty = $stockData ? $stockData->purchaseQtyForWarehouse($warehouse->id)->get() : collect();
            $openingQty = $stockData ? $stockData->openingProductStockQtyForWarehouse($warehouse->id)->get() : collect();
            $debitNoteQty = $stockData ? $stockData->debitNoteQtyForWarehouse($warehouse->id)->get() : collect();
            $warehouseData[] = [
                'warehouse_id'   => $warehouse->id,
                'warehouse_name' => $warehouse->name,
                'branch_stock'  => $stockData ? $stockData->closing_stock : 0,
                'opening_stock' => $openingQty->sum('closing_stock'),
                'purchase_qty' => $purchaseQty->sum('qty'),
                'sale_qty' => $saleQty->sum('quantity'),
                'debit_note_qty' => $debitNoteQty->sum('qty'),
            ];
        }
        $product->warehouse = $warehouseData;
        return $product;
    });

    // echo "<pre>";
    // print_r($products->toArray());
    // die();
    $isManager41 = method_exists($this, 'isActingAs41Manager') && $this->isActingAs41Manager();
    
    return view('backend.product.closingStock.index', compact('products','warehouses','search_text','isManager41'));
  }




    private function manager41GetStockTransaction(Request $request)
    {
        $warehouseId   = (int) $request->warehouseId;
        $part_no       = trim($request->part_no ?? '');
        $warehouseName = $request->warehouseName; // godown = warehouse name in 41 stock
        $combinedData  = [];

        if ($part_no === '' || !$warehouseId) {
            return response()->json($combinedData);
        }

        // Load 41 stock row for this part/warehouse-name (godown)
        $stockData = Manager41ProductStock::with([
                'openingProductStock',                 // Manager41OpeningStock
                'purchaseQty.purchaseInvoice.address',// Manager41PurchaseInvoice(+address)
            ])
            ->where('part_no', $part_no)
            ->where('godown', $warehouseName)
            ->first();

        if (!$stockData) {
            return response()->json($combinedData);
        }

        /* ========== Opening Stock (41) ========== */
        // hasOne -> use get() for parity with your old code; sum() still works
        $openingRows = $stockData->openingProductStockQtyForWarehouse($warehouseId)->get();
        if ($openingRows->isNotEmpty()) {
            $combinedData[] = [
                'type'           => 'Opening Stock',
                'quantity'       => (float) $openingRows->sum('closing_stock'),
                'created_at'     => date('Y') . '-04-01', // FY start
                'details'        => $stockData->openingProductStock,
                'voucher_number' => '',
                'party_name'     => '',
            ];
        }

        /* ========== Purchases & Credit Notes (41) ========== */
        $purchaseDetails = $stockData->purchaseQtyForWarehouse($warehouseId)->get();
        foreach ($purchaseDetails as $pd) {
            $pi = $pd->purchaseInvoice; // may be null if data inconsistent
            if (!$pi) {
                continue;
            }

            // Type
            $type = ($pi->purchase_invoice_type === 'seller') ? 'Purchase' : 'Credit Note';

            // Voucher number
            $voucher = '';
            if ($pi->purchase_invoice_type === 'seller') {
                $voucher = $pd->purchase_invoice_no ?: $pi->purchase_no;
            } else {
                $voucher = $pi->credit_note_number ?: $pi->purchase_no;
            }

            // Party name
            $party = '';
            if ($pi->purchase_invoice_type === 'seller') {
                $si = $pi->seller_info; // cast to array in Model; keep defensive
                if (is_string($si)) {
                    $decoded = json_decode($si, true);
                    $party   = $decoded['seller_name'] ?? '';
                } elseif (is_array($si)) {
                    $party = $si['seller_name'] ?? '';
                }
            } else {
                $party = optional($pi->address)->company_name ?? '';
            }

            $combinedData[] = [
                'type'           => $type,
                'quantity'       => (float) ($pd->qty ?? 0),
                'created_at'     => optional($pd->created_at)->format('Y-m-d'),
                'details'        => $pd,
                'voucher_number' => $voucher ?: '',
                'party_name'     => $party,
            ];
        }

        /* ========== Sales from Challans ONLY (41) ========== */
        $saleRows = $stockData->saleQtyForWarehouse($warehouseId)
            ->with(['challan.address'])
            ->get();

        foreach ($saleRows as $row) {
            $challan = $row->challan;

            // Party from challan address, else decode shipping_address (array/json)
            $party = '';
            if ($challan && $challan->address) {
                $party = $challan->address->company_name ?? '';
            } else {
                $sa = $challan->shipping_address ?? null;
                if (is_string($sa)) {
                    $decoded = json_decode($sa, true);
                    $party   = $decoded['company_name'] ?? '';
                } elseif (is_array($sa)) {
                    $party = $sa['company_name'] ?? '';
                }
            }

            if ($party !== '') {
                $combinedData[] = [
                    'type'           => 'Challan', // strictly challan-based sales
                    'quantity'       => (float) ($row->quantity ?? 0),
                    'created_at'     => ($challan && $challan->challan_date)
                        ? date('Y-m-d', strtotime($challan->challan_date))
                        : optional($row->created_at)->format('Y-m-d'),
                    'details'        => $challan ?? $row,
                    'voucher_number' => $challan->challan_no ?? '-',
                    'party_name'     => $party,
                    'challan_id_'    => $row->challan_id,
                ];
            }
        }

        /* ========== Sort & Return ========== */
        usort($combinedData, function ($a, $b) {
            return strtotime($a['created_at']) <=> strtotime($b['created_at']);
        });

        return response()->json($combinedData);
    }

  public function getStockTransaction(Request $request)
  {

    // Manager-41 login/impersonation पर सीधे Manager-41 वाला flow
    if ($this->isActingAs41Manager()) {
        return $this->manager41GetStockTransaction($request);
    }
      $warehouseId = $request->warehouseId;
      $part_no = $request->part_no;
      $warehouseName = $request->warehouseName;
      $combinedData = [];

      $stockData = ProductApi::with('openingProductStock', 'purchaseQty', 'saleQty.challan')
          ->where('part_no', $part_no)
          ->where('godown', $warehouseName)
          ->first();

      if (!$stockData) {
          return response()->json($combinedData);
      }      
      $openingQty = $stockData ? $stockData->openingProductStockQtyForWarehouse($warehouseId)->get() : collect();
      if ($openingQty) {
          $combinedData[] = [
              'type' => 'Opening Stock',
              'quantity' => $openingQty->sum('closing_stock') ?? 0,
              'created_at' => date('Y') . '-04-01',
              'details' => $stockData->openingProductStock,
              'voucher_number' => '',
              'party_name' => ''
          ];
      }

      // Purchase Quantity
      $purchaseQty = $stockData ? $stockData->purchaseQtyForWarehouse($warehouseId)->get() : collect();
      foreach ($purchaseQty as $purchase) {
          $number = $purchase->purchaseInvoice->purchase_invoice_type == 'seller' ? $purchase->purchase_invoice_no : $purchase->purchaseInvoice->credit_note_number;
          $party_name = '';
          
          if ($purchase->purchaseInvoice->purchase_invoice_type == 'seller') {
            $sellerInfo = $purchase->purchaseInvoice->seller_info;
              if (is_string($sellerInfo)) {
                  $decoded = json_decode($sellerInfo, true);
                  $party_name = $decoded['seller_name'] ?? '';
              } elseif (is_array($sellerInfo)) {
                  $party_name = $sellerInfo['seller_name'] ?? '';
              }
          }else{
              $sellerInfo = $purchase->purchaseInvoice->address;
              $party_name = $sellerInfo->company_name ?? '';
          }

          $combinedData[] = [
              'type' => $purchase->purchaseInvoice->purchase_invoice_type == 'seller'?'Purchase':'Credit Note',
              'quantity' => $purchase->qty ?? 0,
              'created_at' => $purchase->created_at,
              'details' => $purchase,
              'voucher_number' => $number,
              'party_name' => $party_name
          ];
      }

      // Debit Note Quantity
      $debitNoteQty = $stockData ? $stockData->debitNoteQtyForWarehouse($warehouseId)->get() : collect();
      foreach ($debitNoteQty as $debitNote) {
          $number = $debitNote->debitNoteInvoice->debit_note_no;
          
          if ($debitNote->debitNoteInvoice->debit_note_type == 'seller') {
            $sellerInfo = $debitNote->debitNoteInvoice->seller_info;
              if (is_string($sellerInfo)) {
                  $decoded = json_decode($sellerInfo, true);
                  $party_name = $decoded['seller_name'] ?? '';
              } elseif (is_array($sellerInfo)) {
                  $party_name = $sellerInfo['seller_name'] ?? '';
              }
          }else{
              $sellerInfo = $debitNote->debitNoteInvoice->address;
              $party_name = $sellerInfo->company_name ?? '';
          }

          $combinedData[] = [
              'type' => $debitNote->debitNoteInvoice->debit_note_type == 'seller'?'Debit Note':'Debit Note',
              'quantity' => $debitNote->qty ?? 0,
              'created_at' => $debitNote->created_at,
              'details' => $debitNote,
              'voucher_number' => $debitNote->debitNoteInvoice->debit_note_number,
              'party_name' => $party_name
          ];
      }


      // Sale Quantity
      $saleQty = $stockData ? $stockData->saleQtyForWarehouse($warehouseId)->get() : collect();
      
      foreach ($saleQty as $sale) {
          $party_name = "";
          $details = null;
          // echo "<br>".$sale->challan->id.;
          if ($sale->challan->invoice_status == 1) {
              $details = InvoiceOrderDetail::where('part_no', $part_no)
                  ->where('challan_id', $sale->challan_id)
                  ->whereHas('invoiceOrder', function ($q) {
                      $q->where('invoice_cancel_status', 0);
                  })
                  ->first();
              $number = $details->invoiceOrder->invoice_no ?? '-';
              $sellerInfo = $details->invoiceOrder->party_info;
              if (is_string($sellerInfo)) {
                  $decoded = json_decode($sellerInfo, true);
                  $party_name = $decoded['company_name'] ?? '';
              } elseif (is_array($sellerInfo)) {
                  $party_name = $sellerInfo['company_name'] ?? '';
              }
          } else {
              $details = $sale->challan;
              $number = $sale->challan->challan_no ?? '-';
              $sellerInfo = $details->shipping_address;
              if (is_string($sellerInfo)) {
                  $decoded = json_decode($sellerInfo, true);
                  $party_name = $decoded['company_name'] ?? '';
              } elseif (is_array($sellerInfo)) {
                  $party_name = $sellerInfo['company_name'] ?? '';
              }              
          }
          // echo "<br>".$sale->challan->id.'----'.$sale->challan->invoice_status.'......'.$party_name;
          if($party_name != ""){
            $combinedData[] = [
                'type' => $sale->challan->invoice_status == 1 ? 'Invoice' : 'Challan',
                'quantity' => $sale->quantity,
                'created_at' => date('Y-m-d', strtotime(
                    $details->invoiceOrder->created_at ?? $details->created_at
                )),
                'details' => $details,
                'voucher_number' => $number,
                'party_name' => $party_name,
                'challan_id_' => $sale->challan->id
            ];
          }          
      }
      // echo "<pre>"; print_r($combinedData);
      // die;

      usort($combinedData, function ($a, $b) {
          return strtotime($a['created_at']) <=> strtotime($b['created_at']);
      });

      return response()->json($combinedData);
  }
  

  public function manager41closingStockExport(Request $request)
{
    $search = trim($request->search_text ?? '');

    // Active warehouses
    $warehouses = Warehouse::where('active', 1)->orderBy('id', 'ASC')->get();

    // Base: Manager-41 product stocks
    $productsQ = Manager41ProductStock::with([
        // Pull related product to access category/categoryGroup names
        'productDetails.categoryGroup',
        'productDetails.category',
    ]);

    if ($search !== '') {
        $productsQ->where(function ($q) use ($search) {
            $q->where('part_no', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%");
        });
    }

    // Distinct part_no + name (safe for pagination/export)
    $products = $productsQ
        ->select('part_no', 'name')
        ->distinct()
        ->orderBy('name', 'ASC')
        ->get();

    // For each product, compute per-warehouse stats
    $products->transform(function ($product) use ($warehouses) {
        $warehouseData = [];

        foreach ($warehouses as $warehouse) {
            // manager_41_product_stocks.godown stores the WAREHOUSE NAME
            $stockData = Manager41ProductStock::with([
                    'openingProductStock',
                ])
                ->where('part_no', $product->part_no)
                ->where('godown', $warehouse->name)
                ->first();

            // Purchases & sales are drawn via Manager-41 relations
            $purchaseQty = $stockData
                ? $stockData->purchaseQtyForWarehouse($warehouse->id)->get()
                : collect();

            $saleQty = $stockData
                ? $stockData->saleQtyForWarehouse($warehouse->id)->get()
                : collect();

            $warehouseData[] = [
                'warehouse_id'    => $warehouse->id,
                'warehouse_name'  => $warehouse->name,
                'branch_stock'    => $stockData ? (float)$stockData->closing_stock : 0.0,
                'opening_stock'   => $stockData && $stockData->openingProductStock
                                        ? (float)$stockData->openingProductStock->closing_stock
                                        : 0.0,
                'purchase_qty'    => (float)$purchaseQty->sum('qty'),
                'sale_qty'        => (float)$saleQty->sum('quantity'),
            ];
        }

        $product->warehouse = $warehouseData;
        return $product;
    });

    // Shape rows for export
    $processedData = [];
    foreach ($products as $product) {
        $totalStock = 0;
        $kolkata = 0; $delhi = 0; $mumbai = 0;

        foreach ($product->warehouse as $w) {
            $totalStock += $w['branch_stock'];
            if ($w['warehouse_id'] == 1) {
                $kolkata = $w['branch_stock'];
            } elseif ($w['warehouse_id'] == 2) {
                $delhi = $w['branch_stock'];
            } elseif ($w['warehouse_id'] == 6) {
                $mumbai = $w['branch_stock'];
            }
        }

        $processedData[] = [
            'Part No'        => $product->part_no,
            'Item Name'      => $product->name,
            'MRP' => (is_numeric(optional($product->productDetails)->mrp_41_price) ? (float) optional($product->productDetails)->mrp_41_price : 0.0),

            'Category Group' => optional(optional($product->productDetails)->categoryGroup)->name ?? '',
            'Category'       => optional(optional($product->productDetails)->category)->name ?? '',
            'Kolkata'        => $kolkata,
            'Delhi'          => $delhi,
            'Mumbai'         => $mumbai,
            'Total Stock'    => $totalStock,
        ];
    }

    // Filename: differentiate if you like
       $filename = 'closingStock41_'
        . now('Asia/Kolkata')->format('Y-m-d_H-i-s_u') // microsecond precision
        . '_' . Str::random(6)
        . '.xlsx';

    return Excel::download(
        new ClosingStockExport($processedData),
        $filename,
        ExcelWriter::XLSX,
        [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]
    );

}
  public function closingStockExport(Request $request){

    // If acting as Manager-41, switch to the 41-specific export
    if (method_exists($this, 'isActingAs41Manager') && $this->isActingAs41Manager()) {
        return $this->manager41closingStockExport($request);
    }

    $search_text = $request->search_text;
    
    $warehouses = Warehouse::where('active', 1)->orderBy('id', 'ASC')->get();

    $products = ProductApi::with('productDetails');
    

    if ($request->search_text != "") {
        $search = $request->search_text;

        $products = $products->where(function ($query) use ($search) {
            $query->where('part_no', 'like', "%$search%")
                  ->orWhere('name', 'like', "%$search%");
        });
    }

    // Use distinct part_no with name for safe pagination
    $products = $products->select('part_no', 'name')->distinct()->orderBy('name', 'ASC')->get();

    // echo "<pre>";
    // print_r($products->toArray());
    // die();

    // Now transform each product to append warehouse stock
    $products->transform(function ($product) use ($warehouses) {
        $warehouseData = [];
        foreach ($warehouses as $warehouse) {
            $stockData = ProductApi::with('openingProductStock', 'purchaseQty', 'saleQty.challan')->where('part_no', $product->part_no)
                ->where('godown', $warehouse->name) // this is correct if godown stores warehouse name
                ->first();
            $saleQty = $stockData ? $stockData->saleQtyForWarehouse($warehouse->id)->get() : collect();
            $purchaseQty = $stockData ? $stockData->purchaseQtyForWarehouse($warehouse->id)->get() : collect();
            $warehouseData[] = [
                'warehouse_id'   => $warehouse->id,
                'warehouse_name' => $warehouse->name,
                'branch_stock'  => $stockData ? $stockData->closing_stock : 0,
                'opening_stock' => isset($stockData->openingProductStock) ? $stockData->openingProductStock->closing_stock : 0,
                'purchase_qty' => $purchaseQty->sum('qty'),
                'sale_qty' => $saleQty->sum('quantity'),
            ];
        }
        $product->warehouse = $warehouseData;
        return $product;
    });
    $processedData = array();
    foreach ($products as $key => $product){
        $totalStock = 0;
        $kolkata = 0;
        $delhi = 0;
        $mumbai = 0;
        foreach($product->warehouse as $wStockKey => $wStockValue){
            $totalStock += $wStockValue['branch_stock'];
            if($wStockValue['warehouse_id'] == 1){
                $kolkata = $wStockValue['branch_stock'];
            }elseif($wStockValue['warehouse_id'] == 2){
                $delhi = $wStockValue['branch_stock'];
            }elseif($wStockValue['warehouse_id'] == 6){
                $mumbai = $wStockValue['branch_stock'];
            }
            
        }
        $processedData[] =[
                    'Part No' => $product->part_no,
                    'Item Name' => $product->name,
                    'Mrp' => $product->productDetails->mrp,
                    'Category Group' => $product->productDetails->categoryGroup->name,
                    'Category' => isset($product->productDetails->category->name) ? $product->productDetails->category->name : '',
                    'Kolkata' => $kolkata,
                    'Delhi' => $delhi,
                    'Mumbai' => $mumbai,
                    'Total Stock' => $totalStock,
                ];
    }
    
    return Excel::download(new ClosingStockExport($processedData), 'closingStock'.date('d-m-Y').'.xlsx');
    
  }


    public function manager41ClosingStockExportDetails(Request $request)
    {
        $warehouseId   = (int)$request->warehouseId;
        $part_no       = trim($request->part_no ?? '');
        $warehouseName = $request->warehouseName;

        $processedData = [];
        $combinedData  = [];

        // Load from Manager-41 stock
        $stockData = Manager41ProductStock::with([
                'openingProductStock',
                'purchaseQty.purchaseInvoice.address',
                'saleQty.challan.address',
            ])
            ->where('part_no', $part_no)
            ->where('godown', $warehouseName) // 41: godown holds warehouse NAME
            ->first();

        // Opening Stock
        $openingQty = $stockData ? $stockData->openingProductStockQtyForWarehouse($warehouseId)->get() : collect();
        $runningBalance = 0;
        if ($openingQty->isNotEmpty()) {
            $sumOpen = (float)$openingQty->sum('closing_stock');
            $drQty = 0.0; $crQty = 0.0;

            if ($sumOpen <= 0) {
                $drQty = $sumOpen;
                $runningBalance -= $drQty;
            } else {
                $crQty = $sumOpen;
                $runningBalance += $crQty;
            }

            $processedData[] = [
                'Date'          => date('Y') . '-04-01',
                'Voucher Type'  => 'Opening Stock',
                'Voucher Number'=> '',
                'Party Name'    => '',
                'DrQty'         => $drQty,
                'CrQty'         => $crQty,
                // 'Running Qty' => $runningBalance
            ];
        }

        // Purchases & Credit Notes (41)
        $purchaseQty = $stockData ? $stockData->purchaseQtyForWarehouse($warehouseId)->get() : collect();
        foreach ($purchaseQty as $purchase) {
            $pi = $purchase->purchaseInvoice;
            if (!$pi) continue;

            $number = ($pi->purchase_invoice_type === 'seller')
                ? ($purchase->purchase_invoice_no ?: $pi->purchase_no)
                : ($pi->credit_note_number ?: $pi->purchase_no);

            // party name
            $party_name = '';
            if ($pi->purchase_invoice_type === 'seller') {
                $sellerInfo = $pi->seller_info;
                if (is_string($sellerInfo)) {
                    $decoded    = json_decode($sellerInfo, true);
                    $party_name = $decoded['seller_name'] ?? '';
                } elseif (is_array($sellerInfo)) {
                    $party_name = $sellerInfo['seller_name'] ?? '';
                }
            } else {
                $party_name = optional($pi->address)->company_name ?? '';
            }

            $drQty = 0.0; $crQty = 0.0;
            $type  = ($pi->purchase_invoice_type === 'seller') ? 'Purchase' : 'Credit Note';

            if ((float)$purchase->qty <= 0) {
                $drQty = (float)$purchase->qty;
                $runningBalance -= $drQty;
            } else {
                $crQty = (float)$purchase->qty;
                $runningBalance += $crQty;
            }

            $combinedData[] = [
                'Date'           => optional($purchase->created_at)->format('Y-m-d'),
                'Voucher Type'   => $type,
                'Voucher Number' => $number,
                'Party Name'     => $party_name,
                'DrQty'          => $drQty,
                'CrQty'          => $crQty,
                // 'Running Qty'  => $runningBalance
            ];
        }

        // Sales from Challans ONLY (no invoice lookups)
        $saleQty = $stockData ? $stockData->saleQtyForWarehouse($warehouseId)->with('challan.address')->get() : collect();
        foreach ($saleQty as $sale) {
            $challan = $sale->challan;

            $number = $challan->challan_no ?? '-';

            // party from challan address or shipping_address (json/array)
            $party_name = '';
            if ($challan && $challan->address) {
                $party_name = $challan->address->company_name ?? '';
            } else {
                $sellerInfo = $challan->shipping_address ?? null;
                if (is_string($sellerInfo)) {
                    $decoded    = json_decode($sellerInfo, true);
                    $party_name = $decoded['company_name'] ?? '';
                } elseif (is_array($sellerInfo)) {
                    $party_name = $sellerInfo['company_name'] ?? '';
                }
            }

            $drQty = 0.0; $crQty = 0.0;
            $type  = 'Challan';

            // sale reduces stock => treat as Dr (subtract)
            if ((float)$sale->quantity >= 0) {
                $drQty = (float)$sale->quantity;
                $runningBalance -= $drQty;
            } else {
                $crQty = (float)$sale->quantity;
                $runningBalance += $crQty;
            }

            $combinedData[] = [
                'Date'           => ($challan && $challan->challan_date)
                                    ? date('Y-m-d', strtotime($challan->challan_date))
                                    : optional($sale->created_at)->format('Y-m-d'),
                'Voucher Type'   => $type,
                'Voucher Number' => $number,
                'Party Name'     => $party_name,
                'DrQty'          => $drQty,
                'CrQty'          => $crQty,
                // 'Running Qty'  => $runningBalance
            ];
        }

        // Sort by date
        usort($combinedData, function ($a, $b) {
            return strtotime($a['Date']) <=> strtotime($b['Date']);
        });

        // Merge and compute running qty column
        $merged = array_merge($processedData, $combinedData);
        $runningBalance = 0.0;
        foreach ($merged as &$row) {
            $runningBalance += (float)$row['CrQty'];
            $runningBalance -= (float)$row['DrQty'];
            $row['Running Qty'] = $runningBalance; // unified key
        }
        unset($row);

        // Closing Stock line
        $drQtyClose = 0.0; $crQtyClose = 0.0;
        if ($runningBalance >= 0) {
            $drQtyClose = $runningBalance;
        } else {
            $crQtyClose = $runningBalance; // keep your original sign logic
        }
        $merged[] = [
            'Date'           => date('Y-m-d'),
            'Voucher Type'   => 'Closing Stock',
            'Voucher Number' => '',
            'Party Name'     => '',
            'DrQty'          => $drQtyClose,
            'CrQty'          => $crQtyClose,
            'Running Qty'    => '',
        ];

        $partNumber = $part_no;
        $itemName   = $stockData->name ?? '';

        return Excel::download(
            new ClosingStockDetailsExport($merged, $partNumber, $itemName),
            $part_no.'-closingStockDetails_'.date('Y-m-d').'.xlsx'
        );
    }
  
  public function closingStockExportDetails(Request $request){


     if (method_exists($this, 'isActingAs41Manager') && $this->isActingAs41Manager()) {
            return $this->manager41ClosingStockExportDetails($request);
     }


    $warehouseId = $request->warehouseId;
    $part_no = $request->part_no;
    $warehouseName = $request->warehouseName;
    $processedData = [];
    $combinedData = [];
    
    $stockData = ProductApi::with('openingProductStock', 'purchaseQty', 'saleQty.challan')
      ->where('part_no', $part_no)
      ->where('godown', $warehouseName)
      ->first();
          
    $openingQty = $stockData ? $stockData->openingProductStockQtyForWarehouse($warehouseId)->get() : collect();
    $runningBalance = 0;
    if ($openingQty) {
        $drQty = 0;
        $crQty = 0;
        if($openingQty->sum('closing_stock') <= 0){
           $drQty = $openingQty->sum('closing_stock');
           $runningBalance -= $drQty;
        }else{
            $crQty = $openingQty->sum('closing_stock');
            $runningBalance += $crQty;
        }
      $processedData[] = [
          'Date' => date('Y') . '-04-01',
          'Voucher Type' => 'Opening Stock',
          'Voucher Number' => '',
          'Party Name' => '',
          'DrQty' => $drQty,
          'CrQty' => $crQty,
          //'Running Qty' => $runningBalance
      ];
    }
    
    // Purchase Quantity
    $purchaseQty = $stockData ? $stockData->purchaseQtyForWarehouse($warehouseId)->get() : collect();
    foreach ($purchaseQty as $purchase) {
      $number = $purchase->purchaseInvoice->purchase_invoice_type == 'seller' ? $purchase->purchase_invoice_no : $purchase->purchaseInvoice->credit_note_number;
      $party_name = '';
      
      if ($purchase->purchaseInvoice->purchase_invoice_type == 'seller') {
        $sellerInfo = $purchase->purchaseInvoice->seller_info;
          if (is_string($sellerInfo)) {
              $decoded = json_decode($sellerInfo, true);
              $party_name = $decoded['seller_name'] ?? '';
          } elseif (is_array($sellerInfo)) {
              $party_name = $sellerInfo['seller_name'] ?? '';
          }
      }else{
          $sellerInfo = $purchase->purchaseInvoice->address;
          $party_name = $sellerInfo->company_name ?? '';
      }
    
      //   $combinedData[] = [
      //       'type' => $purchase->purchaseInvoice->purchase_invoice_type == 'seller'?'Purchase':'Credit Note',
      //       'quantity' => $purchase->qty ?? 0,
      //       'created_at' => $purchase->created_at,
      //       'details' => $purchase,
      //       'voucher_number' => $number,
      //       'party_name' => $party_name
      //   ];
      
        $drQty = 0;
        $crQty = 0;
        $type = $purchase->purchaseInvoice->purchase_invoice_type == 'seller'?'Purchase':'Credit Note';
        if($purchase->qty <= 0){
           $drQty = $purchase->qty;
           $runningBalance -= $drQty;
        }else{
            $crQty = $purchase->qty;
            $runningBalance += $crQty;
        }
        $combinedData[] = [
          'Date' => date('Y-m-d', strToTime($purchase->created_at)),
          'Voucher Type' => $type,
          'Voucher Number' => $number,
          'Party Name' => $party_name,
          'DrQty' => $drQty,
          'CrQty' => $crQty,
          //'Running Qty' => $runningBalance
        ];
    }
        
    // Sale Quantity
    $saleQty = $stockData ? $stockData->saleQtyForWarehouse($warehouseId)->get() : collect();
    foreach ($saleQty as $sale) {
      $party_name = "";
      $details = null;
      if ($sale->challan->invoice_status == 1) {
          $details = InvoiceOrderDetail::where('part_no', $part_no)
            ->where('challan_id', $sale->challan_id)
            ->whereHas('invoiceOrder', function ($q) {
                $q->where('invoice_cancel_status', 0);
            })
            ->first();
          $number = $details->invoiceOrder->invoice_no ?? '-';
          $sellerInfo = $details->invoiceOrder->party_info;
          if (is_string($sellerInfo)) {
              $decoded = json_decode($sellerInfo, true);
              $party_name = $decoded['company_name'] ?? '';
          } elseif (is_array($sellerInfo)) {
              $party_name = $sellerInfo['company_name'] ?? '';
          }
      } else {
          $details = $sale->challan;
          $number = $sale->challan->challan_no ?? '-';
          $sellerInfo = $details->shipping_address;
          if (is_string($sellerInfo)) {
              $decoded = json_decode($sellerInfo, true);
              $party_name = $decoded['company_name'] ?? '';
          } elseif (is_array($sellerInfo)) {
              $party_name = $sellerInfo['company_name'] ?? '';
          }              
      }
      
        $drQty = 0;
        $crQty = 0;
        $type = $sale->challan->invoice_status == 1 ? 'Invoice' : 'Challan';
        if($sale->quantity >= 0){
           $drQty = $sale->quantity;
           $runningBalance -= $drQty;
        }else{
            $crQty = $sale->quantity;
            $runningBalance += $crQty;
        }
        $combinedData[] = [
          'Date' => date('Y-m-d', strToTime($sale->created_at)),
          'Voucher Type' => $type,
          'Voucher Number' => $number,
          'Party Name' => $party_name,
          'DrQty' => $drQty,
          'CrQty' => $crQty,
          //'Running Qty' => $runningBalance
        ];
    }
    
    usort($combinedData, function ($a, $b) {
      return strtotime($a['Date']) <=> strtotime($b['Date']);
    });
    $merged = array_merge($processedData, $combinedData);
    $runningBalance = 0;
    foreach ($merged as &$mValue) {
        $runningBalance += $mValue['CrQty'];
        $runningBalance -= $mValue['DrQty'];
        $mValue['RunningQty'] = $runningBalance;
    }
    
    $drQty = 0;
    $crQty = 0;
    if($runningBalance >= 0){
       $drQty = $runningBalance;
    }else{
        $crQty = $runningBalance;
    }
    $merged[]=[
            'Date' => date('Y-m-d'),
            'Voucher Type' => 'Closing Stock',
            'Voucher Number' => '',
            'Party Name' => '',
            'DrQty' => $drQty,
            'CrQty' => $crQty,
            'Running Qty' => ''
        ];
    // echo "<pre>"; print_r($merged);die;
    
    $partNumber = $part_no;
    $itemName = $stockData->name;
    return Excel::download(new ClosingStockDetailsExport($merged, $partNumber, $itemName), $part_no.'closingStockDetails_'.date('d-m-Y').'.xlsx');
    
  }
  
}