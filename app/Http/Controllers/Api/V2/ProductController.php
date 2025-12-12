<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\FlashDealCollection;
use App\Http\Resources\V2\ProductDetailCollection;
use App\Http\Resources\V2\ProductMiniCollection;
use App\Http\Resources\V2\ProductSellerCollection;
use Illuminate\Support\Facades\DB;
use App\Models\Color;
use App\Models\FlashDeal;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Shop;
use App\Models\Warehouse;
use App\Models\Brand;
use App\Models\Upload;
use App\Utility\CategoryUtility;
use App\Utility\SearchUtility;
use Cache;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mail;
use App\Models\User;

use Mpdf\Mpdf;
use App\Models\PdfReport;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;

// Download PDF with Job And Queue
use App\Jobs\GeneratePdfReportJob;
use Illuminate\Support\Facades\Storage;
// use App\Services\WhatsAppService;

class ProductController extends Controller {

  protected $whatsappService;

  // public function __construct(WhatsAppService $whatsappService)
  // {
  //     $this->whatsappService = $whatsappService;
  // }

  public function index() {
    // return new ProductMiniCollection(Product::whereNotNull('photos')->latest()->paginate(10));
    return new ProductMiniCollection(Product::where('current_stock','>', '0')->latest()->paginate(10));
  }

  public function show($id, Request $request) {
    // return Product::where('id', $id)->get();
    // if (Product::findOrFail($id)->digital==0) {
    //     return new ProductDetailCollection(Product::where('id', $id)->get());
    // }elseif (Product::findOrFail($id)->digital==1) {
    //     return new DigitalProductDetailCollection(Product::where('id', $id)->get());
    // }
    if($request->header('x-customer-id') !== null){
        $user_id = $request->header('x-customer-id');
    }elseif(Auth::check()){
        $user_id = auth()->user()->id;
    }else{
      $user_id = "";
    }
    // echo $user_id;die;
    return new ProductDetailCollection(Product::where('id', $id)->get(),$user_id);
    // try {
    //     $product = Product::findOrFail($id);
    //     // $product = new ProductDetailCollection(Product::where('id', $id)->get());

    //     $product['attributes'] = array();
    //     $product['choice_options'] = array();
    //     $product['colors'] = array();
    //     $product['variations'] = array();
    //     // $product['earn_point'] = "0";

    //     $brand_id = $product['brand_id'];
    //     $brand = Brand::find($brand_id);
    //     $product['brand'] = $brand;

    //     $photo_id = $product['photos'];
    //     $thumbnail_img_id = $product['thumbnail_img'];

    //     // Assuming 'uploads' table has 'filename' and 'path' columns
    //     $photo = Upload::find($photo_id);
    //     $thumbnail = Upload::find($thumbnail_img_id);

    //     // Check if the records are found
    //     if (!$photo) {
    //         $photoUrl = null; // Handle not found case
    //     } else {
    //         // Generate the URL using the route to display the image
    //         $photoUrl = $photo->file_name;
    //     }

    //     if (!$thumbnail) {
    //         $thumbnailUrl = null; // Handle not found case
    //     } else {
    //         // Generate the URL using the route to display the image
    //         $thumbnailUrl = $thumbnail->file_name;
    //     }
    //     $product['photos'] = "https://storage.googleapis.com/mazing/".$photoUrl;
    //     $product['thumbnail_img'] = "https://storage.googleapis.com/mazing/".$thumbnailUrl;

        

    
    //     // Product found
    //     return response()->json([
    //         'data' => $product,
    //         'success' => true,
    //         'status' => 200,
    //         'message' => 'Product found',
    //     ], 200);
    // } catch (ModelNotFoundException $e) {
    //     // Product not found
    //     return response()->json([
    //         'data' => [],
    //         'success' => false,
    //         'status' => 404,
    //         'message' => 'Product not found',
    //     ], 404);
    // }
  }

  public function seller($id, Request $request) {
    $shop     = Shop::findOrFail($id);
    // $products = Product::where('added_by', 'seller')->whereNotNull('photos')->where('user_id', $shop->user_id);
    $products = Product::where('current_stock','>', '0')->where('added_by', 'seller')->where('user_id', $shop->user_id);
    if ($request->name != "" || $request->name != null) {
      $products = $products->where('name', 'like', '%' . $request->name . '%');
    }
    $products->where('published', 1);
    return new ProductMiniCollection($products->latest()->paginate(10));
  }

  public function sellerSaleszing(Request $request, $id) {
    $warehouse = Warehouse::where('seller_saleszing_id', $id)->firstOrFail();
    $products  = ProductWarehouse::with('product', 'product.taxes', 'seller.user')->where('warehouse_id', $warehouse->id)->latest()->get();
    return new ProductSellerCollection($products);
  }

  public function importClients(){
    return 'Yes';
  }

  public function category($id, Request $request) {
    $category_ids   = CategoryUtility::children_ids($id);
    $category_ids[] = $id;

    // $products = Product::whereIn('category_id', $category_ids)->whereNotNull('photos')->physical();
    $products = Product::where('current_stock','>', '0')->whereIn('category_id', $category_ids)->physical();
    if ($request->name != "" || $request->name != null) {
      $products = $products->where('name', 'like', '%' . $request->name . '%');
    }
    if ($request->has('selected_attribute_values') && $request->selected_attribute_values != null) {
      // $products = Product::whereJsonContains('choice_options', ['values' => $request->selected_attribute_values])->where('category_id', $category_ids)->whereNotNull('photos')->get();
      $products = Product::whereJsonContains('choice_options', ['values' => $request->selected_attribute_values])->where('category_id', $category_ids)->get();
      $products->where('published', 1);
      return new ProductMiniCollection(filter_products($products)->paginate(10));
    }
    return new ProductMiniCollection(filter_products($products)->latest()->paginate(10));
  }

  public function brand($id, Request $request) {
    // $products = Product::where('brand_id', $id)->whereNotNull('photos')->physical();
    $products = Product::where('current_stock','>', '0')->where('brand_id', $id);
    // print_r($products);
    if ($request->name != "" || $request->name != null) {
      $products = $products->where('name', 'like', '%' . $request->name . '%');
    }

    return new ProductMiniCollection($products->latest()->paginate(10));
  }

  public function todaysDeal() {
    return Cache::remember('app.todays_deal', 86400, function () {
      // $products = Product::where('todays_deal', 1)->whereNotNull('photos')->physical();
      $products = Product::where('current_stock','>', '0')->where('todays_deal', 1)->physical();
      return new ProductMiniCollection(filter_products($products)->limit(20)->latest()->get());
    });
  }

  public function flashDeal() {
    return Cache::remember('app.flash_deals', 86400, function () {
      $flash_deals = FlashDeal::where('status', 1)->where('featured', 1)->where('start_date', '<=', strtotime(date('d-m-Y')))->where('end_date', '>=', strtotime(date('d-m-Y')))->get();
      return new FlashDealCollection($flash_deals);
    });
  }

  public function featured() {
    // $products = Product::where('featured', 1)->whereNotNull('photos')->physical();
    $products = Product::where('current_stock','>', '0')->where('featured', 1)->physical();
    return new ProductMiniCollection(filter_products($products)->latest()->paginate(10));
  }

  public function digital() {
    $products = Product::where('current_stock','>', '0')->digital();
    return new ProductMiniCollection(filter_products($products)->latest()->paginate(10));
  }

  public function bestSeller() {
    // return $products = Product::where('current_stock','!=', '0')->orderBy('num_of_sale', 'desc')->limit(20)->get();
    // return new ProductMiniCollection(filter_products($products)->limit(20)->get());
    // return $products;
    return Cache::remember('app.best_selling_products', 86400, function () {
      // $products = Product::orderBy('num_of_sale', 'desc')->whereNotNull('photos')->physical();
      $products = Product::where('current_stock','!=', '0')->orderBy('num_of_sale', 'desc')->physical();
      return new ProductMiniCollection(filter_products($products)->limit(20)->get());
    });
  }

  public function new () {
    return Cache::remember('app.new_products', 86400, function () {
      // $products = Product::orderBy('created_at', 'desc')->whereNotNull('photos')->physical();
      $products = Product::orderBy('created_at', 'desc')->physical();
      return new ProductMiniCollection(filter_products($products)->limit(20)->get());
    });
  }

  public function related($id) {
    return Cache::remember("app.related_products-$id", 86400, function () use ($id) {
      $product  = Product::find($id);
      // $products = Product::where('category_id', $product->category_id)->where('id', '!=', $id)->whereNotNull('photos')->physical();
      $products = Product::where('current_stock','>', '0')->where('category_id', $product->category_id)->where('id', '!=', $id)->physical();
      return new ProductMiniCollection(filter_products($products)->limit(10)->get());
    });
  }

  public function topFromSeller($id) {
    return Cache::remember("app.top_from_this_seller_products-$id", 86400, function () use ($id) {
      $product  = Product::find($id);
      // print_r($product);die;
      // $products = Product::where('user_id', $product->user_id)->whereNotNull('photos')->orderBy('num_of_sale', 'desc')->physical();
      $products = Product::where('current_stock','>', '0')->where('user_id', $product->user_id)->orderBy('num_of_sale', 'desc')->physical();

      return new ProductMiniCollection(filter_products($products)->limit(10)->get());
    });
  }

  public function search(Request $request) {

    if($request->header('x-customer-id') !== null){
        $user_id = $request->header('x-customer-id');
    }elseif(Auth::check()){
        $user_id = auth()->user()->id;
    }else{
        $user_id = "";
    }
    $perPageCount = 10;
    $sort_by = $request->sort_key;
    $name    = $request->name;
    $min     = $request->min;
    $max     = $request->max;

    // $category_group    = $categories    = $brands    = $selected_brands    = $selected_categories   = $products    = [];
    // $srch_prod_name     = $request->has('prod_name')? $request->prod_name : '';
    // $selected_cat_groups = $request->has('cat_groups')? $request->cat_groups : [];
    // $selected_categories = $request->has('categories')? $request->categories : [];
    // $selected_brands = $request->has('brands')? $request->brands : [];

    // $cat_group_ids = [];
    // $category_ids = [];
    // $brand_ids    = [];


    if($sort_by != '' OR $name != "" OR $min != "" OR $max != "" OR isset($request->cat_groups) OR isset($request->categories) OR isset($request->brands)){
      // die;
      $productQuery = Product::query()
      ->join('categories', 'products.category_id', '=', 'categories.id')
      ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');
      if($sort_by == 'top_rated' OR isset($request->cat_groups) OR isset($request->categories) OR isset($request->brands)){
        $productQuery->leftJoin('products_api', function ($join) {
            $join->on('products.part_no', '=', DB::raw("products_api.part_no COLLATE utf8mb3_unicode_ci"));
        });
      }

      $products = $productQuery->select( 'products.*')
          ->where('published', true)
          ->where('current_stock', '>', 0)
          ->where('approved', true);        
          if($sort_by != 'new_arival' AND $sort_by != 'new_arrival' AND $sort_by != 'popularity' AND $sort_by != 'top_rated' OR (isset($request->cat_groups) OR isset($request->categories) OR isset($request->brands))){
            if(isset($request->cat_groups) OR isset($request->categories) OR isset($request->brands)){
              $products->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0
                        WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                            SELECT part_no COLLATE utf8mb3_general_ci 
                            FROM products_api
                        ) THEN 1
                        ELSE 2 END")
              ->orderByRaw("CAST(products.mrp AS UNSIGNED) ASC")->groupBy('products.id');
            }else{
              $products->orderBy('category_groups.name', 'asc')->orderBy('categories.name', 'asc')
              ->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END")        
              ->where(function ($query) use ($request) {
                  $query->where('products.name', 'LIKE', '%' . $request->name . '%')
                        ->orWhere('products.part_no', 'LIKE', '%' . $request->name . '%');
              })
              ->orderBy('products.name', 'ASC');
            }
            
          }     
      
      if ($request->cat_groups != null && $request->cat_groups != "") {
          $selected_cat_groups = explode(',', $request->cat_groups);
          $products = $products->whereIn('group_id', array_filter($selected_cat_groups));
      }

      if ($request->categories != null && $request->categories != "") {
          $selected_categories = explode(',', $request->categories);
          $products = $products->whereIn('category_id', array_filter($selected_categories));
      }

      if ($request->brands != null && $request->brands != "") {
          $selected_brands = explode(',', $request->brands);
          $products = $products->whereIn('brand_id', array_filter($selected_brands));
      }

      if ($min != null && $min != "" && is_numeric($min)) {
        $products->where('unit_price', '>=', $min);
      }

      if ($max != null && $max != "" && is_numeric($max)) {
        $products->where('unit_price', '<=', $max);
      }
      switch ($sort_by) {
        case 'price_low_to_high':
          $products->orderBy('unit_price', 'asc');
          break;

        case 'price_high_to_low':
          $products->orderBy('unit_price', 'desc');
          break;

        case 'new_arival':
          $products->orderBy('created_at', 'desc');
          $perPageCount = 20;
          break;

        case 'new_arrival':
          $products->orderBy('created_at', 'desc');
          $perPageCount = 20;
          break;
        
        case 'popularity':
          $products->orderBy('num_of_sale', 'desc');
          $perPageCount = 20;
          break;

        case 'top_rated':
          $products->orderBy('rating', 'desc');
          $perPageCount = 20;
          break;
      }
    }else{
      // pagination
      $productQuery = Product::query()->join('categories', 'products.category_id', '=', 'categories.id')->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id');      
      $productQuery->where('products.current_stock','>' ,'0');  
      $products = $productQuery->select('products.id', 'brand_id', 'category_groups.name  AS group_name' ,'categories.name  AS category_name' ,'group_id', 'category_id', 'products.name', 'thumbnail_img', 'products.slug', 'min_qty','mrp')->where('published', true)->where('approved', true)->orderByRaw("CASE 
        WHEN category_groups.id = 1 THEN 0 
        WHEN category_groups.id = 8 THEN 1 
        ELSE 2 END")->orderBy('category_groups.name', 'asc')->orderBy('categories.name', 'asc')
        // ->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0
        //           WHEN products.part_no COLLATE utf8mb3_general_ci IN (
        //               SELECT part_no COLLATE utf8mb3_general_ci 
        //               FROM products_api
        //           ) THEN 1
        //           ELSE 2 END")
        ->orderByRaw("
            CASE 
                WHEN products.part_no COLLATE utf8mb3_general_ci IN (
                    SELECT part_no COLLATE utf8mb3_general_ci 
                    FROM products_api
                ) THEN 0
                ELSE 1 
            END
        ")
        ->orderByRaw("CAST(products.mrp AS UNSIGNED) ASC"); 
    }
    // return new ProductMiniCollection(filter_products($products)->paginate(10), $user_id);

    return new ProductMiniCollection($products->paginate($perPageCount), $user_id);
  }

  public function variantPrice(Request $request) {
    if($request->header('x-customer-id') !== null){
        $user_id = $request->header('x-customer-id');
    }elseif(Auth::check()){
        $user_id = auth()->user()->id;
    }else{
        $user_id = "";
    }
    
    $product = Product::findOrFail($request->id);
    $str     = '';
    $tax     = 0;
    $quantity = $tax = $ctax = $max_limit = $markup = $wmarkup = $price = $carton_price = 0;
    $discount = 0;

    if ($request->has('color') && $request->color != "") {
      $str = Color::where('code', '#' . $request->color)->first()->name;
    }

    $var_str = str_replace(',', '-', $request->variants);
    $var_str = str_replace(' ', '', $var_str);

    if ($var_str != "") {
      $temp_str = $str == "" ? $var_str : '-' . $var_str;
      $str .= $temp_str;
    }

    $product_stocks = $product->stocks->where('variant', $str);

    $user = User::find($user_id);

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
    $price = ceil($price);
    
    $product_bulk_qty = Product::where('id', $product->id)->select('piece_by_carton')->first();
    if ($product_bulk_qty) {
      $bulk_qty = (int)$product_bulk_qty->piece_by_carton;
    } else {
      $bulk_qty = ceil(50000 / $price);
    }
    
    // if($request->quantity >= $bulk_qty)
    // {
    $bulk_price = $price * 0.98;
    // }
    $bulk_message = "Purchase ".$bulk_qty." or more and get each for ".format_price(convert_price($bulk_price))." instead of ".format_price(convert_price($price));

    $bulk_price = ceil($bulk_price);


    return response()->json(
    [
      'result' => true,
      'data' => [
        'product_id'   => $product->id,
        'variant'      => $str,
        'price'        => (float) convert_price($price),
        'price_string' => format_price(convert_price($price)),
        'bulk_price'   => (float) convert_price($bulk_price),
        'bulk_price_string'   => format_price(convert_price($bulk_price)),
        'bulk_qty'     => $bulk_qty,
        'stock'        => intval($quantity),
        'bulk_message' => $bulk_message,
        'image'        => $product->thumbnail_img == null ? "" : uploaded_asset($product->thumbnail_img),
      ]
    ]);
  }

  public function lookingForProduct(Request $request) {
    // Validation
    $validator = Validator::make($request->all(), [
      'product' => 'required|min:2|max:255',
      'name'    => 'required|min:2|max:255',
      'contact' => 'required|numeric|digits:10',
    ]);
    if ($validator->fails()) {
      return response()->json([
        'message' => $validator->errors(),
      ]);
    }

    $data['product'] = $request->product;
    $data['name']    = $request->name;
    $data['contact'] = $request->contact;

    // Send Mail
    Mail::send('emails.looking_for_product', $data, function ($message) {
      $message->to(env('MAIL_FROM_ADDRESS'), 'Mazing Business')->subject('someone is looking for product');
      $message->from(env('MAIL_FROM_ADDRESS'), 'Mazing Business');
    });

    return response()->json([
      'message' => 'Your query has been sent, Thank you',
    ]);
  }

  public function downloadExcel(Request $request) {
    //print_r($request->all());die;
    
    $search = $request->prod_name != "" ? $request->prod_name : "";

    // Cat Groups
    $stringCatGroups = ($request->cat_groups != "") ? '(' . $request->cat_groups . ')' : '';
    $group = ($request->cat_groups != "") ? " AND `group_id` IN $stringCatGroups " : '';

    // Categories
    $stringCategories = ($request->categories != "") ? '(' . $request->categories . ')' : '';
    $category = ($request->categories != "") ? " AND `category_id` IN $stringCategories " : '';

    // Brands
    $stringBrands = ($request->brands != "") ? '(' . $request->brands . ')' : '';
    $brand = ($request->brands != "") ? " AND `brand_id` IN $stringBrands " : '';

    $results = DB::select(DB::raw("
        SELECT `products`.`id`, `part_no`, `brand_id`, `category_groups`.`name` as `group_name`, 
              `categories`.`name` as `category_name`, `group_id`, `category_id`, `products`.`name`, 
              `thumbnail_img`, `products`.`slug`, `min_qty`, `mrp` 
        FROM `products` 
        INNER JOIN `categories` ON `products`.`category_id` = `categories`.`id` 
        INNER JOIN `category_groups` ON `categories`.`category_group_id` = `category_groups`.`id` 
        WHERE `products`.`name` LIKE '%$search%' $group $category $brand 
            AND `published` = 1 
            AND `current_stock` = 1 
            AND `approved` = 1 
        ORDER BY `category_groups`.`name` ASC, `categories`.`name` ASC, 
                CASE WHEN `products`.`name` LIKE '%$search%' THEN 0 ELSE 1 END, 
                CAST(`products`.`mrp` AS UNSIGNED) ASC
    "));
    $resultsArray = json_decode(json_encode($results), true);
    $user = User::where('id', $request->user_id)->first();
    $discount = $user->discount == "" ? 0 : $user->discount;
    $client_name = $user->name;
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->getStyle("A1:G1")->getFont()->setName('Calibri')->setSize(14)->setBold(true);
    $sheet->getStyle("A2:G2")->getFont()->setName('Calibri')->setSize(11)->setBold(true);
    $sheet->getStyle("A3:G2000")->getFont()->setName('Calibri')->setSize(11);

    $sheet->getStyle("A2:G2")->getAlignment()->setHorizontal('center');
    $sheet->getStyle('A1:A1000')->getAlignment()->setHorizontal('center');
    $sheet->getStyle('B1:B1000')->getAlignment()->setHorizontal('center');
    $sheet->getStyle('D1:D1000')->getAlignment()->setHorizontal('center');

    $text = 'Mazing Business Price List ('.date('d-m-Y h:i:s A', strtotime('now')).')';
    $sheet->setCellValue('A1', $text);
    $sheet->mergeCells("A1:F1");

    $sheet->setCellValue('A2', 'SN');
    $sheet->setCellValue('B2', 'Part No.');
    $sheet->setCellValue('C2', 'Item');
    $sheet->setCellValue('D2', 'Group');
    $sheet->setCellValue('E2', 'Category');
    $sheet->setCellValue('F2', 'Price');

    $ex_row=3;
    $i=1;

    foreach($resultsArray as $key=>$row){
      $net_price = ceil((100-$discount) * $row['mrp'] / 100);
      $net_price = number_format($net_price,2, '.', '');

      $list_price = $net_price * 131.6 / 100;
      $list_price = number_format($list_price,2, '.', '');

      if($request->type == 'net'){
          $price = $net_price;
      }else{
          $price = $list_price;
      }

      $tmp = 'A'.$ex_row;
      $sheet->setCellValue($tmp, $i);
      $tmp = 'B'.$ex_row;
      $sheet->setCellValue($tmp, $row['part_no']);
      $tmp = 'C'.$ex_row;
      $sheet->setCellValue($tmp, $row['name']);
      $tmp = 'D'.$ex_row;
      $sheet->setCellValue($tmp, $row['group_name']);
      $tmp = 'E'.$ex_row;
      $sheet->setCellValue($tmp, $row['category_name']);
      $tmp = 'F'.$ex_row;
      $sheet->setCellValue($tmp, $price);

      $ex_row++;
      $i++;
    }

    foreach(range('A','F') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    $name = strtolower(str_replace(' ','_',$client_name)).'_'.date('d-m-Y').'.xlsx';
    
    
    // $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    // $writer->save('mazing_business.xlsx');
    // header('Content-Type: application/vnd.ms-excel');
    // header('Content-Disposition: attachment; filename="'.$name.'"');
    // $writer->save("php://output");

    // Construct the full URL to access the saved file
    $baseUrl = '/download/excel'; // This assumes your Laravel application is configured properly
    $filename = 'mazing_business_'.time().'.xlsx';
    $fileUrl = $baseUrl . '/' . $filename;    
    $savePath = public_path($fileUrl); // Adjust the path as per your server setup
    $writer = new Xlsx($spreadsheet);
    $writer->save($savePath);

    $response['message']='Excel file created successfully.';
    $response['filename']=url('/').'/public'.$fileUrl;
    return response()->json([
        "data" => $response,
        "success" => true,
        "status" => 200
    ]);
  }

  public function downloadPdf(Request $request) {
    try{

        $data = $request->input('data');
        
        $user= User::where('id', $request->user_id)->first();
        $client_name = $user->name;
        $discount = $user->discount == "" ? 0 : $user->discount;
        $filename = strtolower(str_replace(' ','_',$client_name)).'_'.rand(100,999).'_'.date('d-m-Y').'.pdf';   

        $group = "";
        $category = "";
        $brand = "";
        $search = "";
        $type = $request->type;
        foreach ($request->all() as $key => $value) {
            if($key == "prod_name"){
              $search = $value;
            }
            if($key == "cat_groups" && !empty($value)){
                $string = '(' . $value . ')';
                $group = " AND `group_id` IN $string ";
            }

            if($key == "categories" && !empty($value)){
                $string = '(' . $value . ')';
                $category = " AND `category_id` IN $string ";
            }

            if($key == "brands" && !empty($value)){
                $string = '(' . $value . ')';
                $brand = " AND `brand_id` IN $string ";
            }
        }
        $data['client_name'] = $client_name;
        $data['search'] = $request->input('prod_name');
        $data['group'] = $group;
        $data['brand'] = $brand;
        $data['category'] = $category;
        $data['search'] = $search;
        $data['type'] = $type;
        $data['user_id'] = $request->user_id;

        // Create a new entry in the pdf_reports table
        PdfReport::create([
            'user_id' => $request->user_id,
            'filename' => $filename,
            'status' => 'pending'
        ]);

        GeneratePdfReportJob::dispatch($data, $filename);
        $response['message']='PDF generation complete';
        $response['filename']=$filename;
        return response()->json([
            "data" => $response,
            "success" => true,
            "status" => 200
        ]);
    }catch (\Mpdf\MpdfException $e) {
        echo 'PDF generation error: ' . $e->getMessage();
    }
  }

  public function __downloadPdf(Request $request) {
    // print_r($request->all());die;
    try{
          $group = "";
          $category = "";
          $brand = "";
          $search = "";
          $type = $request->type;
          // print_r($request->all());die;
          foreach ($request->all() as $key => $value) {
              if($key == "prod_name"){
                $search = $value;
              }
              if($key == "cat_groups" && !empty($value)){
                  $string = '(' . $value . ')';
                  $group = " AND `group_id` IN $string ";
              }

              if($key == "categories" && !empty($value)){
                  $string = '(' . $value . ')';
                  $category = " AND `category_id` IN $string ";
              }

              if($key == "brands" && !empty($value)){
                  $string = '(' . $value . ')';
                  $brand = " AND `brand_id` IN $string ";
              }
          }
          
          $results = DB::select(DB::raw("SELECT `products`.`id`, `part_no`, `brand_id`, `category_groups`.`name` as `group_name`, `categories`.`name` as `category_name`, `group_id`, `category_id`, `products`.`name`, `thumbnail_img`, `products`.`slug`, `min_qty`, `mrp` from `products` INNER JOIN `categories` ON `products`.`category_id` = `categories`.`id` INNER JOIN `category_groups` on `categories`.`category_group_id` = `category_groups`.`id` WHERE products.name LIKE '%$search%' $group $category $brand AND `published` = 1 AND `current_stock` = 1 and `approved` = 1 order by `category_groups`.`name` asc, `categories`.`name` asc, CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END, CAST(products.mrp AS UNSIGNED) ASC"));
          $resultsArray = json_decode(json_encode($results), true);
          
          $user= User::where('id', $request->user_id)->first();
          $client_name = $user->name;
          $discount = $user->discount;
          // Create PDF
          $htmlContent ='<table width="95%" style="border-collapse: collapse;position: relative;top: 50px;left: 32px;margin-bottom: 20px;">
            <thead>
                <tr>
                    <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color:#174e84;padding-top: 7px;padding-bottom: 7px;">SN</th>
                    <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color:#174e84;padding-top: 7px;padding-bottom: 7px;">PART NO</th>
                    <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color:#174e84;padding-top: 7px;padding-bottom: 7px;">IMAGE</th>
                    <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color:#174e84;padding-top: 7px;padding-bottom: 7px;">ITEM</th>
                    <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color:#174e84;padding-top: 7px;padding-bottom: 7px;">GROUP</th>
                    <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color:#174e84;padding-top: 7px;padding-bottom: 7px;">CATEGORY</th>
                    <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color:#174e84;padding-top: 7px;padding-bottom: 7px;">NET PRICE</th>
                </tr>
            </thead>
            <tbody>';
            $count = 0;
            foreach($resultsArray as $key=>$row){              
              // Assuming 'uploads' table has 'filename' and 'path' columns
              $thumbnail = Upload::find($row['thumbnail_img']);
              // Check if the records are found
              if (!$thumbnail) {
                  $thumbnailUrl = null; // Handle not found case
              } else {
                  // Generate the URL using the route to display the image
                  $thumbnailUrl = $thumbnail->file_name;
                  //$photo_url = "https://storage.googleapis.com/mazing/".$thumbnailUrl;
				  $photo_url = env('UPLOADS_BASE_URL') . '/' . $thumbnailUrl;

              }
              
              $net_price = ceil((100-$discount) * $row['mrp'] / 100);
              $net_price = number_format($net_price,2, '.', '');

              $list_price = $net_price * 131.6 / 100;
              $list_price = number_format($list_price,2, '.', '');

              if($type == 'net'){
                  $price = $net_price;
              }else{
                  $price = $list_price;
              }
              $htmlContent .='<tr style="height: 75px;">
                              <td width="5%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$count++.'</td>
                              <td width="10%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$row['part_no'].'</td>
                              <td width="7%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;"><img src="'.$photo_url.'" alt="" style="width: 80px;"></td>
                              <td width="32%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$row['name'].'</td>
                              <td width="15%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$row['group_name'].'</td>
                              <td width="15%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$row['category_name'].'</td>
                              <td width="15%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$price.'</td>
                          </tr>';
            }
        $htmlContent .='</tbody>
          </table>';
          $header = '<table width="100%" border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="text-align: right; position: relative;">                                
                                <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" width="100%" alt="Header Image" style="display: block;"/>                                
                            </td>
                        </tr>
                    </table>';
          $footer = '<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left" class="col">
                          <tbody>
                              <tr>
                                  <td style="height: 55px;text-align: center;color: #174e84;font-family: Arial;font-weight: bold;">
                                      All prices are NET Prices, and all the products in the PDF are available.
                                  </td>
                              </tr>
                              <tr bgcolor="#174e84">
                                  <td style="height: 40px;text-align: center;color: #fff;font-family: Arial;font-weight: bold;">
                                      Mazing Business Price List for - '.$client_name.' ('.date('d-m-Y h:i:s A').')
                                  </td>
                              </tr>
                          </tbody>
                      </table>';
          $mpdf = new \Mpdf\Mpdf(['format' => 'A4']);
          $mpdf->setHTMLHeader($header);
          $mpdf->SetMargins(0, 10, 40, 10); // Set margins to 10mm on all sides
          // Set header content with explicit height
          $mpdf->setHTMLFooter($footer);
          // Set auto page break
          $mpdf->SetAutoPageBreak(true, 30); // Enable auto page break with a margin of 30mm
          $mpdf->AddPageByArray(['size' => 'A4']);
          // Add HTML content
          $mpdf->WriteHTML($htmlContent);
          
          $pdfPath = base_path('public/download/pdf/');
          if (!file_exists($pdfPath)) {
              mkdir($pdfPath, 0755, true);
          }         
          $fileName = strtolower(str_replace(' ','_',$client_name)).'_'.date('d-m-Y').'.pdf';
          $mpdf->Output($pdfPath . '/' . $fileName, 'F');
          $pdfPath = 'https://mazingbusiness.com/public/download/pdf/'.$fileName;
          // Construct the full URL to access the saved file
          // $baseUrl = '/download/pdf'; // This assumes your Laravel application is configured properly
          // $filename = strtolower(str_replace(' ','_',$client_name)).'_'.date('d-m-Y').'.pdf';
          // $fileUrl = $baseUrl . '/' . $filename;   

          // $savePath = public_path($fileUrl); // Adjust the path as per your server setup
          // $writer = new Xlsx($spreadsheet);
          // $writer->save($savePath);
          // $response = ['res' => true, 'msg' => 'Successfully Create Pdf', 'data' => $pdfPath];

          return response()->json([
              'res' => true, 'msg' => 'Successfully Create Pdf', 'data' => $pdfPath
          ], 200);
    }catch (\Mpdf\MpdfException $e) {
        echo 'PDF generation error: ' . $e->getMessage();
    }
  }

  public function whatsappMessage(){
    $getDownloadData = PdfReport::where('user_id','!=',null)->where('status','completed')->where('download_status','0')->orderBy('created_at','ASC')->first();
    if(isset($getDownloadData->user_id)){
      $user = User::where('id', $getDownloadData->user_id)->first();
      if ($user) {
          $getDownloadData->phone = $user->phone;
      } else {
          $getDownloadData->phone = null; // Handle case where user is not found
      }
      // $updateData = ['download_status' => 1];
      // PdfReport::where('user_id','!=',null)->where('status','completed')->where('download_status','0')->where('id',$getDownloadData->id)->where('download_status','0')->update($updateData);
      return response()->json([
          'message' => 'All Download List',
          'result' => $getDownloadData
      ]);
    }else{
      return response()->json([
          'message' => 'Download list is empty',
          'result' => ''
      ]);
    }
  }
  public function whatsappSendMsgStatus(Request $request){
    $status = $request->status;
    $id = $request->fid;
    if($status == 1 and $id != ""){
      $getDownloadData = PdfReport::where('id', $id)->where('status','completed')->where('download_status','0')->orderBy('created_at','ASC')->first();
      if(isset($getDownloadData->user_id)){
        $updateData = ['download_status' => 1];
        PdfReport::where('id', $id)->where('status','completed')->where('download_status','0')->where('id',$getDownloadData->id)->where('download_status','0')->update($updateData);
        return response()->json([
            'message' => 'Updated',
            'stats' => true
        ]);
      }else{
        return response()->json([
            'message' => 'Record didn\'t get',
            'stats' => false
        ]);
      }
    }else{
      return response()->json([
        'message' => 'Failed',
        'stats' => false
    ]);
    }
  }

}