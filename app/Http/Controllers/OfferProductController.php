<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Http\Requests\ProductRequest;
use App\Http\Requests\OwnBrandProductRequest;

use App\Models\AttributeValue;
use App\Models\Cart;

use App\Models\Product;
use App\Models\ProductTax;
use App\Models\ProductTranslation;
use App\Models\ProductWarehouse;

use App\Models\Seller;
use App\Models\Category;
use App\Models\State;
use App\Models\CategoryGroup;
use App\Models\Brand;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Upload;
use App\Models\OwnBrandCategoryGroup;
use App\Models\OwnBrandCategory;
use App\Models\OwnBrandProduct;
use App\Models\OfferProduct;
use App\Models\OfferCombination;
use App\Models\Offer;
use App\Services\ProductFlashDealService;
use App\Services\ProductService;
use App\Services\ProductStockService;
use App\Services\ProductTaxService;
use App\Services\GoogleSheetsService;

use App\Http\Controllers\SearchController;

use Artisan;
use Cache;
use Carbon\Carbon;
use Combinations;
use CoreComponentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Str;
use Illuminate\Support\Facades\Auth;

class OfferProductController extends Controller
{
    //

    public function showAddOfferProductPage()
    {
        // Fetch categories with children categories

        $categories = Category::where('parent_id', 0)
            ->with('childrenCategories')
            ->get();
            
            $brands = Brand::orderBy('name')->get();
            $products = Product::where('current_stock', 1)->orderBy('name')->get();

            // Fetch all warehouses
            $states = State::where('country_id', 101)->get();

            // Fetch managers (staff with role_id 5)
            $managers = User::join('staff', 'users.id', '=', 'staff.user_id')
                ->where('staff.role_id', 5)
                ->select('users.*')->get();

        // Load the view and pass categories
        return view('backend.offer_products.add', compact('categories','brands','states','managers','products'));
    }

    
 public function saveOfferProduct(Request $request)
{
    // Process the offer validity dates
    if ($request->filled('offer_validity')) {
        [$start_date, $end_date] = explode(' to ', $request->offer_validity);
        $start_date = date('Y-m-d H:i:s', strtotime($start_date));
        $end_date = date('Y-m-d H:i:s', strtotime($end_date));
    } else {
        $start_date = '';
        $end_date = '';
    }

    // Handle file upload for offer banner, use blank if not set
    $banner_id = $request->offer_banner ?? '';

    // Generate a unique offer ID
    $offer_id = 'OFF' . strtoupper(uniqid());

    // Process multi-select fields (category_id, brand_id, product_ids)
    $category_ids = $request->filled('category_ids') ? $request->category_ids : [];
    $brand_ids = $request->filled('brand_ids') ? $request->brand_ids : [];
    $product_ids = $request->filled('product_ids') ? $request->product_ids : [];
    // Process multi-select complementary items
    $complementary_items = $request->filled('complementary_items') ? $request->complementary_items : [];

    // Insert the main offer data into the `offers` table



    DB::table('offers')->insert([
        'offer_id' => $offer_id,
        'state_id' => $request->state_id,
        'manager_id' => $request->manager_id,
        'offer_name' => $request->offer_name ?? '',
        'category_id' => json_encode($category_ids),
        'brand_id' => json_encode($brand_ids),
        'product_code' => json_encode($product_ids),
        'offer_validity_start' => $start_date,
        'offer_validity_end' => $end_date,
        'count' => '0',
        'offer_description' => $request->offer_description ?? '',
        'offer_banner' => $banner_id,
        'offer_type' => $request->offer_type ?? '',
        'offer_value' => $request->offer_value ?? '0',
        'value_type' => $request->value_type ?? '',
        'status' => $request->featured ? 1 : 1,
        'discount_percent' => $request->discount_percent ?? '0',

        'per_user' => $request->uses_per_user,
        'max_uses' => $request->max_uses,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

   
    // Insert complementary items into `offer_combinations` table

    foreach ($complementary_items as $part_no) {
        $product_id=Product::where('part_no', $part_no)->value('id');
        OfferCombination::create([
            'offer_id' => $offer_id,
            'free_product_part_no' => $part_no,
            'product_id'=>$product_id,
            'free_product_qty' => 1, // Default qty to 1 if not provided
        ]);
    }

    // Fetch matching products from `products` table based on provided `product_ids`, `brand_id`, and `category_id`
    // Ensure at least one filter is provided before querying products
    if (!empty($product_ids) || !empty($brand_ids) || !empty($category_ids)) {
        $productsQuery = DB::table('products');
        if (!empty($product_ids)) {
            $productsQuery->whereIn('part_no', $product_ids);
        }
        if (!empty($brand_ids)) {
            $productsQuery->whereIn('brand_id', $brand_ids);
        }
        if (!empty($category_ids)) {
            $productsQuery->whereIn('category_id', $category_ids);
        }
        $products = $productsQuery->get();
        // echo "<pre>";
        // print_r($products);
        // die();

        // Insert each matching product into `offers_product` table
        foreach ($products as $product) {
            // Calculate offer price based on `value_type`
            if ($request->value_type === 'percent' && isset($product->mrp)) {
                //$offer_price = $product->mrp - ($product->mrp * ($request->offer_value / 100));
                $offer_price ="";
            } else {
                $offer_price = $request->offer_value ?? 0;
            }

             $product_id=Product::where('part_no', $product->part_no)->value('id');

            // OfferProduct::create([
            //     'offer_id' => $offer_id,
            //     'part_no' => $product->part_no ?? '',
            //     'product_id'=>$product_id,
            //     'name' => $product->name ?? '',
            //     'mrp' => $product->mrp ?? '0',
            //     'offer_price' => $offer_price,
            //     'min_qty' => $product->min_qty ?? '1',
            //     'discount_type' => $request->value_type ?? '',
            //     'offer_discount_percent' => $request->offer_value ?? '0',
            //     'created_at' => now(),
            //     'updated_at' => now(),
            // ]);
              // Ensure discount_type and offer_discount_percent are null for offer_type = 2
            $discount_type = ($request->offer_type == '2') ? null : $request->value_type;
            $offer_discount_percent = ($request->offer_type == '2') ? null : $request->offer_value;

            OfferProduct::create([
                'offer_id' => $offer_id,
                'part_no' => $product->part_no ?? '',
                'product_id' => $product_id,
                'name' => $product->name ?? '',
                'mrp' => $product->mrp ?? '0',
                'offer_price' => $offer_price,
                'min_qty' => $product->min_qty ?? '1',
                'discount_type' => $discount_type, // Set to null for offer_type 2
                'offer_discount_percent' => $offer_discount_percent, // Set to null for offer_type 2
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
    return redirect()->route('offers.index')->with('status', 'Offer Product added successfully');
}


public function saveSelections(Request $request)
{
    

    $offer_id = $request->input('offer_id'); // Assuming this is passed as part of the request
    $productIds = $request->input('product_ids'); // Selected product IDs

    // Fetch the products from the database based on selected IDs
    $products = Product::whereIn('part_no', $productIds)->get();

    // echo "<pre>";
    // print_r($productIds);
    // die();

    // Insert each product with calculated offer price
    foreach ($products as $product) {
        // Calculate offer price based on `value_type`
       
        $product_id=Product::where('part_no', $product->part_no)->value('id');
        // Insert into the OfferProduct table
        OfferProduct::create([
            'offer_id' => $offer_id,
            'part_no' => $product->part_no ?? '',
            'product_id'=>$product_id,
            'name' => $product->name ?? '',
            'mrp' => $product->mrp ?? '0',
            'min_qty' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Redirect with a success message
    return redirect()->back()->with('status', translate('Products added with offer successfully!'));
}

public function delete($id)
{
    try {
        // Find the offer product by ID
        $offerProduct = OfferProduct::findOrFail($id);

        // Delete the product
        $offerProduct->delete();

        return response()->json(['success' => true, 'message' => translate('Product deleted successfully!')]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => translate('Unable to delete the product!')]);
    }
}

public function offerUpdate(Request $request, $id)
{
    // Retrieve the offer


    
    $offer = Offer::where('offer_id', $id)->first();
    if (!$offer) {
        abort(404, 'Offer not found');
    }

    // Process the offer validity dates
    $start_date = '';
    $end_date = '';
    if ($request->filled('offer_validity') && is_string($request->offer_validity)) {
        [$start_date, $end_date] = explode(' to ', $request->offer_validity);
        $start_date = date('Y-m-d H:i:s', strtotime($start_date));
        $end_date = date('Y-m-d H:i:s', strtotime($end_date));
    }

    // Prepare data for updating the `offers` table
    $data = [
        'state_id' => $request->state_id,
        'manager_id' => $request->manager_id,
        'offer_name' => $request->offer_name,
        // 'category_id' => json_encode($request->category_id),
        // 'brand_id' => json_encode($request->brand_id),
        // 'product_code' => json_encode(array_map('trim', explode(',', $request->product_codes))),
        'offer_validity_start' => $start_date,
        'offer_validity_end' => $end_date,
        'offer_description' => $request->offer_description,
        'offer_banner' => $request->offer_banner,
        'offer_type' => $request->offer_type,
        'offer_value' => $request->offer_value,
        'value_type' => $request->value_type,
        'discount_percent' => $request->discount_percent,
        'status' => $request->has('featured') ? 1 : 0,
         'per_user' => $request->uses_per_user,
        'max_uses' => $request->max_uses,
    ];

    // Update the offer in the `offers` table
    Offer::where('offer_id', $id)->update($data);

    // Handle complementary items in `offer_combinations` table
    // DB::transaction(function() use ($request, $id) {
    //     // DB::table('offer_combinations')->where('offer_id', $id)->delete();
    //     OfferCombination::where('offer_id', $id)->delete();
    //     if ($request->filled('complementary_items')) {
    //         foreach ($request->complementary_items as $index => $item) {
    //             if (!empty($item['part_no']) && !empty($item['quantity'])) {
    //                 // DB::table('offer_combinations')->insert([
    //                 //     'offer_id' => $id,
    //                 //     'free_product_part_no' => trim($item['part_no']),
    //                 //     'free_product_qty' => (int)trim($item['quantity'])
    //                 // ]);
    //                 OfferCombination::create([
    //                     'offer_id' => $id,
    //                     'free_product_part_no' => trim($item['part_no']),
    //                     'free_product_qty' => (int)trim($item['quantity']),
    //                 ]);
    //             }
    //         }
    //     }
    // });

    // Update product codes in `offers_product` table
    // $product_codes = array_map('trim', explode(',', $request->product_codes));
    // OfferProduct::where('offer_id', $id)->delete();

    // $productsQuery = DB::table('products')->whereIn('part_no', $product_codes);
    // if (!empty($request->brand_id)) {
    //     $productsQuery->whereIn('brand_id', $request->brand_id);
    // }
    // if (!empty($request->category_id)) {
    //     $productsQuery->whereIn('category_id', $request->category_id);
    // }
    // $products = $productsQuery->get();

    // foreach ($products as $product) {
    //     $offer_price = $request->value_type === 'percent' && isset($product->mrp) ? 
    //                    $product->mrp - ($product->mrp * ($request->offer_value / 100)) : 
    //                    $request->offer_value;

      

    //    OfferProduct::create([
    //         'offer_id' => $id,
    //         'part_no' => $product->part_no ?? '',
    //         'name' => $product->name ?? '',
    //         'mrp' => $product->mrp ?? '0',
    //         'offer_price' => $offer_price,
    //         'min_qty' => $product->min_qty ?? '1',
    //         'discount_type' => $request->value_type ?? '',
    //         'offer_discount_percent' => $request->offer_value ?? '0',
    //         'created_at' => now(),
    //         'updated_at' => now(),
    //     ]);
    // }

    return redirect()->back()->with('success', 'Offer updated successfully');
}

   public function _backupsaveOfferProduct(Request $request)
{
    // Process the offer validity dates, use empty strings if not set
    if ($request->filled('offer_validity')) {
        [$start_date, $end_date] = explode(' to ', $request->offer_validity);
        $start_date = date('Y-m-d H:i:s', strtotime($start_date));
        $end_date = date('Y-m-d H:i:s', strtotime($end_date));
    } else {
        $start_date = '';
        $end_date = '';
    }

    // Handle file upload for offer banner, use blank if not set
    $banner_id = $request->offer_banner ?? '';

    // Generate a unique offer ID
    $offer_id = 'OFF' . strtoupper(uniqid());

    // Split product codes by commas and handle cases where product codes are not set
    $product_codes = $request->filled('product_codes') ? array_map('trim', explode(',', $request->product_codes)) : [];

    // Insert the main offer data into the `offers` table
    DB::table('offers')->insert([
        'offer_id' => $offer_id,
        'state_id' => $request->state_id ?? '',
        'manager_id' => $request->manager_id ?? '',
        'offer_name' => $request->offer_name ?? '',
        'category_id' => json_encode($request->category_id ?? []),
        'brand_id' => json_encode($request->brand_id ?? []),
        'product_code' => json_encode($product_codes),
        'offer_validity_start' => $start_date,
        'offer_validity_end' => $end_date,
        'count' => '0',
        'offer_description' => $request->offer_description ?? '',
        'offer_banner' => $banner_id,
        'offer_type' => $request->offer_type ?? '',
        'offer_value' => $request->offer_value ?? '0',
        'value_type' => $request->value_type ?? '',
        'status' => $request->featured ? 1 : 1,
        'discount_percent' => $request->offer_value ?? '0',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Process and insert complementary items into `offer_combinations` table
    if ($request->filled('complementary_items')) {
        $partNos = explode(',', $request->complementary_items);

        foreach ($partNos as $part_no) {
            OfferCombination::create([
                'offer_id' => $offer_id,
                'free_product_part_no' => trim($part_no),
                'free_product_qty' => 1, // Default qty to 1 if not provided
            ]);
        }
    }

    // Fetch matching products from `products` table based on provided `product_codes`, `brand_id`, and `category_id`
    $productsQuery = DB::table('products');
    if (!empty($product_codes)) {
        $productsQuery->whereIn('part_no', $product_codes);
    }
    if (!empty($request->brand_id)) {
        $productsQuery->whereIn('brand_id', $request->brand_id);
    }
    if (!empty($request->category_id)) {
        $productsQuery->whereIn('category_id', $request->category_id);
    }
    $products = $productsQuery->get();

    // Insert each matching product into `offers_product` table
    foreach ($products as $product) {
        // Calculate offer price based on `value_type`
        if ($request->value_type === 'percent' && isset($product->mrp)) {
            //$offer_price = $product->mrp - ($product->mrp * ($request->offer_value / 100));
            $offer_price ="";
        } else {
            $offer_price = $request->offer_value ?? 0;
        }



        // Insert the product into offers_product table with calculated offer_price
        // OfferProduct->insert([
        //     'offer_id' => $offer_id,
        //     'part_no' => $product->part_no ?? '',
        //     'name' => $product->name ?? '',
        //     'mrp' => $product->mrp ?? '0',
        //     'offer_price' => $offer_price, // Ensured to have a value
        //     'min_qty' => $product->min_qty ?? '1',
        //     'discount_type' => $request->value_type ?? '',
        //     'offer_discount_percent' => $request->offer_value ?? '0',
        //     'created_at' => now(),
        //     'updated_at' => now(),
        // ]);
        OfferProduct::create([
            'offer_id' => $offer_id,
            'part_no' => $product->part_no ?? '',
            'name' => $product->name ?? '',
            'mrp' => $product->mrp ?? '0',
            'offer_price' => $offer_price, // Ensured to have a value
            'min_qty' => $product->min_qty ?? '1',
            'discount_type' => $request->value_type ?? '',
            'offer_discount_percent' => $request->offer_value ?? '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return redirect()->route('offer-products.create')->with('status', 'Offer Product added successfully');
}



    public function listOffersProduct(Request $request)
    {

        // Fetch all items from the offers_product table
        
        $offers_products = OfferProduct::all();
        // Return the view with offers products data
        return view('backend.offer_products.offers_product_list', compact('offers_products'));
    }
    public function getBrandList()
    {
        $brands = Brand::all(['id', 'name']); // Fetch all brands
        return response()->json($brands);
    }

    public function getCategoryList()
    {
        $categories = Category::all(['id', 'name']); // Fetch all categories
        return response()->json($categories);
    }
    public function getProductsByBrand($brandId)
    {
        // Get products in the selected brand - today created
        $products = Product::whereIn('brand_id', is_array($brandId) ? $brandId : [$brandId])
            ->where('published', true)
            ->where('current_stock', '>', 0)
            ->get(['id', 'name','part_no']);

        return response()->json($products);
    }

   public function getBrandsByCategory($categoryIds)
    {
        // Convert category IDs to an array if not already
        $categoryIds = explode(',', $categoryIds);

        // Fetch brands associated with the selected categories
        $brands = Brand::whereHas('products', function ($query) use ($categoryIds) {
            $query->whereIn('category_id', $categoryIds);
        })->distinct()
          ->get(['id', 'name']);

        return response()->json($brands);
    }

    public function getProductsByCategory($categoryId)
    {
        // Get products in the selected category
        $products = Product::whereIn('category_id', is_array($categoryId) ? $categoryId : [$categoryId])
            ->where('published', true)
            ->where('current_stock', '>', 0)
            ->get(['id', 'name','part_no']);

        return response()->json($products);
    }

     public function getProductsByCategoryAndBrand(Request $request)
    {
        $categoryIds = $request->input('category_ids', []); // Categories to filter
        $brandIds = $request->input('brand_ids', []);       // Brands to filter

        $query = Product::query();

        // If no category or brand is selected, fetch all products
        if (empty($categoryIds) && empty($brandIds)) {
            $products = $query->where('published', true)
                              ->where('current_stock', '>', 0)
                              ->get(['id', 'name', 'part_no']);
        } else {
            // Apply category filter if provided
            if (!empty($categoryIds)) {
                $query->whereIn('category_id', $categoryIds);
            }

            // Apply brand filter if provided
            if (!empty($brandIds)) {
                $query->whereIn('brand_id', $brandIds);
            }

            // Fetch products filtered by categories and/or brands
            $products = $query->where('published', true)
                              ->where('current_stock', '>', 0)
                              ->get(['id', 'name', 'part_no']);
        }

        return response()->json($products);
    }


    public function offerLising(Request $request)
    {

       // Initialize the query
        $query = Offer::query();

        // Check if there's a search query
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;

            // Search by offer name or check if product_code contains the part number
            $query->where(function ($q) use ($search) {
                $q->where('offer_name', 'like', '%' . $search . '%')
                  ->orWhereJsonContains('product_code', $search);
            });
        }

        // Fetch the offers and calculate product counts
        $offers = $query->get()->map(function ($offer) {
            // Count the number of products in the `offer_products` table for this offer
            $productCount = DB::table('offer_products')
                ->where('offer_id', $offer->offer_id)
                ->count();

            // Add product count to the offer object
            $offer->product_count = $productCount;

            return $offer;
        });

        // Pass the data and search term to the view
        return view('backend.offer_products.index', compact('offers', 'request'));
    }

    public function offerUpdateStatus(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'offer_id' => 'required|exists:offers,id',
                'status' => 'required|boolean',
            ]);

            // Update the status in the offers table
            DB::table('offers')
                ->where('id', $request->offer_id)
                ->update(['status' => $request->status]);

            return response()->json(['success' => true, 'message' => 'Status updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update status']);
        }
    }

    public function addComplementryProduct(Request $request)
    {
        // this function is running for model 
            $request->validate([
                'offer_id' => 'required|string|max:255',
                'part_no' => 'required|string|max:255',
               
                'quantity' => 'required|integer|min:1',
            ]);
            $product_id=Product::where('part_no', $request->part_no)->value('id');

            OfferCombination::create([
                'offer_id' => $request->offer_id,
                'free_product_part_no' => $request->part_no,
                'product_id'=>$product_id,
                'free_product_qty' => $request->quantity,
            ]);

            return redirect()->back()->with('status', translate('Complementary item added successfully.'));
    }


    public function destroy($id)
    {
        // Find and delete the complementary item
       
        OfferCombination::where('id', $id)->delete();
        // Redirect back with a success message
        return redirect()->back()->with('status', translate('Complementary item deleted successfully.'));
    }

    public function offerView($offer_id)
    {
        // Fetch offer products for the specified offer_id
        $offer_type = Offer::where('offer_id', $offer_id)->pluck('offer_type')->first();
        $offerName = Offer::where('offer_id', $offer_id)->pluck('offer_name')->first();

        $categories = Category::where('parent_id', 0)
            ->with('childrenCategories')
            ->get();

        $brands = Brand::orderBy('name')->get();

        // Fetch offer products and join with the offers table to get offer_type
        $offers_products = OfferProduct::where('offer_products.offer_id', $offer_id)
            ->join('offers', 'offer_products.offer_id', '=', 'offers.offer_id')
            ->select('offer_products.*', 'offers.offer_type') // Select required columns
            ->get();

        // Fetch complementary items from the offer_combinations table based on the offer_id
        $complementary_products = OfferCombination::join('products', DB::raw('CONVERT(offer_combinations.free_product_part_no USING utf8mb3)'), '=', DB::raw('CONVERT(products.part_no USING utf8mb3)'))
            ->where('offer_combinations.offer_id', $offer_id)
            ->select('offer_combinations.id', 'products.part_no', 'products.name', 'products.mrp', 'offer_combinations.free_product_qty as min_qty')
            ->get();

        $products = Product::where('current_stock', '>', 0)->get();

        // echo "<pre>";
        // print_r($offer_type);
        // die();

        return view('backend.offer_products.offers_product_list', compact('products', 'offers_products', 'complementary_products', 'offer_id', 'categories', 'brands','offer_type','offerName'));
    }


   public function offerEdit($offer_id)
    {
        // Fetch the offer data by ID
        $offer = Offer::where('offer_id', $offer_id)->first();

        // Fetch additional data for dropdowns
        $categories = Category::with('childrenCategories')->get();
       // Fetch all warehouses
        $states = State::where('country_id', 101)->get();

            // Fetch managers (staff with role_id 5)
        $managers = User::join('staff', 'users.id', '=', 'staff.user_id')
                ->where('staff.role_id', 5)
                ->select('users.*')->get();

        $managers = User::join('staff', 'users.id', '=', 'staff.user_id')
                        ->where('staff.role_id', 5)
                        ->select('users.*')
                        ->get();
        $brands = Brand::orderBy('name')->get(); // Fetch all brands

         // Fetch complementary items from the offer_combinations table
        $products = Product::where('current_stock', 1)->orderBy('name')->get();
        $complementary_items = OfferCombination::where('offer_id', $offer_id)->get();
       // echo "<pre>";
       // print_r($offer);
       // die();

        return view('backend.offer_products.edit_offer', compact('offer', 'categories', 'states', 'managers', 'brands','complementary_items','products'));
    }

   public function deleteOffer(Request $request, $offer_id)
    {
        try {
            // Begin transaction
            DB::beginTransaction();

            // Delete from related tables using models
            OfferCombination::where('offer_id', $offer_id)->delete();
            OfferProduct::where('offer_id', $offer_id)->delete();

            // Delete the main offer using the model
            Offer::where('offer_id', $offer_id)->delete();

            // Commit transaction
            DB::commit();

            return redirect()->route('offers.index')->with('status', 'Offer and related data deleted successfully.');
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollback();

            return redirect()->route('offers.index')->with('error', 'Failed to delete offer. Please try again.');
        }
    }






public function bulkUpdate(Request $request)
{

    $offerUpdates = $request->input('offers', []);

    foreach ($offerUpdates as $offerId => $offerData) {

         $product_id=Product::where('part_no', $offerData['product_id'])->value('id');
         // Retrieve product_id from the offer data
        


        OfferProduct::where('id', $offerId)->update([
            'product_id' => $product_id, // Ensure your OfferProduct table has this column
            'offer_price' => $offerData['offer_price'] ?? null,
            'min_qty' => $offerData['min_qty'] ?? 1,
            'discount_type' => $offerData['discount_type'] ?? 'amount',
            'offer_discount_percent' => $offerData['offer_discount_percent'] ?? 0,
            'updated_at' => now(),
        ]);
    }

     return redirect()->back()->with('success', 'Offer updated successfully');
}


public function showOfferPage(Request $request)
{

    // Check if user is authenticated
    if (!Auth::check()) {
        return redirect()->route('login')->with('message', 'Please log in to view the offers.');
    }

    // Retrieve categories (only the first 5 if needed)
    $categories = Category::take(100)->get();

    // Retrieve products with offers from offers_product table
    $allProducts = DB::table('offer_products')
        ->join('products', DB::raw("CONVERT(offer_products.part_no USING utf8mb4) COLLATE utf8mb4_unicode_ci"), '=', DB::raw("CONVERT(products.part_no USING utf8mb4) COLLATE utf8mb4_unicode_ci"))
        ->select(
            'products.*',
            'offer_products.offer_price',
            'offer_products.min_qty as offers_min_qty',
            'offer_products.discount_type',
            'offer_products.offer_discount_percent'
        )
        ->take(12)
        ->get();

    // Retrieve combo offers grouped by combo_id
    
    $comboOffers = OfferCombination::join('offer_combination_products', 'offer_combinations.id', '=', 'offer_combination_products.offer_combination_id')
        ->join('products as combo_products', 'offer_combination_products.product_part_no', '=', 'combo_products.part_no')
        ->leftJoin('products as free_products', 'offer_combinations.free_product_part_no', '=', 'free_products.part_no')
        ->select(
            'offer_combinations.id as combination_id',
            'offer_combinations.offer_id',
            'offer_combinations.free_product_part_no',
            'offer_combinations.free_product_qty',
            'free_products.name as free_product_name',
            'free_products.part_no as free_product_part_no',
            'free_products.photos as free_product_photo',
            'combo_products.name as product_name',
            'combo_products.slug as product_slug',
            'combo_products.photos as product_photo',
            'combo_products.mrp as product_mrp',
            'offer_combination_products.required_qty',
            'offer_combination_products.combo_id'
        )
        ->get()
        ->groupBy('combo_id');

    // Pass data to the view
    return view('frontend.offerspages.offer_page', compact('categories', 'allProducts', 'comboOffers'));
}

   


   public function getCategoryProducts(Request $request)
   {
        $categoryId = $request->category_id;

        // Retrieve 12 products within the specified category that have an offer
        $products = DB::table('offer_products')
            ->join('products', function($join) use ($categoryId) {
                $join->on(DB::raw("CONVERT(offer_products.part_no USING utf8mb4) COLLATE utf8mb4_unicode_ci"), '=', DB::raw("CONVERT(products.part_no USING utf8mb4) COLLATE utf8mb4_unicode_ci"))
                     ->where('products.category_id', '=', $categoryId);
            })
            ->select(
                'products.*',
                'products.mrp',  // MRP from products table
                'offer_products.offer_price',
                'offer_products.min_qty as offers_min_qty',
                'offer_products.discount_type',
                'offer_products.offer_discount_percent'
            )
            ->take(12)
            ->get();

        // Render only the product cards to return as response
        return view('frontend.offerspages.product_listing', compact('products'))->render();
    }

  public function saveComplementaryItems(Request $request)
    {
        // Validate the request


        $request->validate([
            'offer_id' => 'required|string',
            'complementary_items' => 'required|array',
            'complementary_items.*.quantity' => 'required|integer|min:1',
        ]);

        // Find the offer by ID
        $offer = DB::table('offers')->where('offer_id', $request->offer_id)->first();

        if (!$offer) {
            return redirect()->back()->withErrors(['error' => 'Offer not found']);
        }

        // Prepare data for insertion in `offer_combinations` table
        $complementaryItems = [];

        foreach ($request->complementary_items as $part_no => $item) {
             $product_id=Product::where('part_no', $part_no)->value('id');
            $complementaryItems[] = [
                'offer_id' => $request->offer_id,
                'free_product_part_no' => $part_no,
                'product_id'=>$product_id,
                'free_product_qty' => $item['quantity'],
            ];
        }

        // Delete existing complementary items for this offer to avoid duplicates
        // DB::table('offer_combinations')->where('offer_id', $request->offer_id)->delete();
        OfferCombination::where('offer_id', $request->offer_id)->delete();

        // Insert the new complementary items into the `offer_combinations` table
        // DB::table('offer_combinations')->insert($complementaryItems);
        OfferCombination::insert($complementaryItems);

        return redirect()->back()->with('status', 'Complementary items updated successfully');
    }

     // Display form to create a new combo set
     // Display form to create a new combo set
    public function showComboSetCreateForm()
    {
        // Fetch all products directly from the products table
        $products = DB::table('products')->get();
        $offers = DB::table('offers')->get();

    // Fetch all categories with active products
       $categories = Category::has('products')->get();
      
        return view('backend.offer_products.combo_set_create', compact('products', 'offers','categories'));
    }

    public function getFreeProduct(Request $request)
    {
        $request->validate([
            'offer_id' => 'required|exists:offer_combinations,offer_id'
        ]);



        // Force both fields to the same collation for comparison
        // $freeProduct = DB::table('offer_combinations')
        //     ->join('products', DB::raw("CONVERT(offer_combinations.free_product_part_no USING utf8mb3)"), '=', DB::raw("CONVERT(products.part_no USING utf8mb3)"))
        //     ->where('offer_combinations.offer_id', $request->offer_id)
        //     ->select('offer_combinations.free_product_part_no', 'offer_combinations.free_product_qty', 'products.name','offer_combinations.id')
        //     ->get();
        $freeProduct = OfferCombination::join('products', DB::raw("CONVERT(offer_combinations.free_product_part_no USING utf8mb3)"), '=', DB::raw("CONVERT(products.part_no USING utf8mb3)"))
            ->where('offer_combinations.offer_id', $request->offer_id)
            ->select(
                'offer_combinations.free_product_part_no',
                'offer_combinations.free_product_qty',
                'products.name',
                'offer_combinations.id'
            )
            ->get();

        return response()->json($freeProduct);
    }


    public function getAllProductsByCategory(Request $request)
    {
        $categoryId = $request->category_id;
        
        // Fetch products based on the selected category
        $products = DB::table('products')
            ->where('category_id', $categoryId)
            ->get(['id', 'name', 'part_no']); // Adjust columns as necessary

        return response()->json($products);
    }

    public function storeOfferCombination(Request $request)
    {
        // Retrieve offer combination ID from the selected free product 
        $offerCombinationId = $request->input('free_product');
        $offer_id = $request->input('offer_id');
        $combo_id=Str::uuid();

        // Retrieve product part numbers and quantities
        $productPartNumbers = $request->input('product_id'); // array of product part numbers
        $quantities = $request->input('product_qty'); // array of quantities

        foreach ($productPartNumbers as $index => $productPartNo) {
            DB::table('offer_combination_products')->insert([
                'offer_id'=>$offer_id,
                'combo_id' => $combo_id,
                'offer_combination_id' => $offerCombinationId,
                'product_part_no' => $productPartNo,
                'required_qty' => $quantities[$index]
            ]);
        }

        return redirect()->back()->with('success', 'Offer combination products added successfully.');
        
    }

   public function combinedProductList()
    {
       $offerCombinations = OfferCombination::join('offer_combination_products', function ($join) {
            $join->on(DB::raw('CONVERT(offer_combinations.id USING utf8mb4)'), '=', DB::raw('CONVERT(offer_combination_products.offer_combination_id USING utf8mb4)'));
        })
        ->join('products as combination_products', function ($join) {
            $join->on(DB::raw('CONVERT(offer_combination_products.product_part_no USING utf8mb4)'), '=', DB::raw('CONVERT(combination_products.part_no USING utf8mb4)'));
        })
        ->leftJoin('products as free_products', function ($join) {
            $join->on(DB::raw('CONVERT(offer_combinations.free_product_part_no USING utf8mb4)'), '=', DB::raw('CONVERT(free_products.part_no USING utf8mb4)'));
        })
        ->select(
            'offer_combinations.id as combination_id',
            'offer_combinations.offer_id',
            'offer_combinations.free_product_part_no',
            'offer_combinations.free_product_qty',
            'offer_combination_products.combo_id', // Select combo_id
            'free_products.name as free_product_name',
            'combination_products.name as product_name',
            'offer_combination_products.product_part_no',
            'offer_combination_products.required_qty'
        )
        ->get()
        ->groupBy('combo_id'); // Group by combo_id instead of combination_id



        return view('backend.offer_products.offer_combinations_products', compact('offerCombinations'));
    }


     public function deleteOfferCombination($id)
    {
        
        // Find the offer combination where combo_id matches the given $id
        $offerCombination = \DB::table('offer_combination_products')->where('combo_id', $id)->delete();
        echo "<pre>";
        print_r($offerCombination);
        die();

        // Redirect back with a success message
    return redirect()->back()->with('success', 'Offer combination deleted successfully.');
    }



}
