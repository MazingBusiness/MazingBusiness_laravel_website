<?php

namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Address;
use App\Models\CategoryGroup;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Upload;
use App\Models\OfferProduct;
use App\Models\NewArrival;
use App\Models\CategoryPricelistUpload;
use App\Models\RewardPointsOfUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\WhatsAppWebService;
use Illuminate\Support\Facades\Http;
use App\Jobs\SendWhatsAppMessagesJob;
use App\Jobs\GenerateTopFiveCategoryPDFJob;
use App\Jobs\CategoryProductsJob;
use App\Jobs\NewArrivalJob;
use App\Jobs\CategoryPricelistUploadPdf;
use Auth;
use PDF;
use Mpdf\Mpdf;
use Mpdf\HtmlParserMode;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\InvoiceController;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TopSellingCategoriesExport;

class WhatsappTopCategories extends Controller
{
 
  public function exportTopSellingCategories(Request $request)
    {
        return Excel::download(new TopSellingCategoriesExport($request), 'top_selling_categories.xlsx');

    }




    // public function exportTopSellingCategories(Request $request): BinaryFileResponse
    // {
    //      $timestamp = now()->format('YmdHis'); // Unique timestamp
    //      $fileName = "top_selling_categories_{$timestamp}.xlsx"; // Dynamic file name

    //    // $response = Excel::download(new TopSellingCategoriesExport($request), $fileName);
    //    $response = Excel::download(new TopSellingCategoriesExport($request),$fileName);
    //    return $response->header("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0")
    //                     ->header("Pragma", "no-cache")
    //                      ->header("Expires", "0");

       


    //  }
    private function getManagerPhone($managerId)
    {

      $managerData = User::where('id', $managerId)->select('phone')->first();
      return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
    }

    public function updateTopFiveCategory($user_id){
        // Fetch all order IDs for the given user
            $orderIds = Order::where('user_id', $user_id)->pluck('id');

             // Fetch all purchased categories based on total product price (without multiplication)
            $allCategories = OrderDetail::select('products.category_id', DB::raw('SUM(order_details.price) as total_spent'))
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->whereIn('order_details.order_id', $orderIds)
                ->groupBy('products.category_id')
                ->orderByDesc('total_spent')
                ->pluck('products.category_id')
                ->toArray();

            // Store all category IDs in the `users` table as JSON
            User::where('id', $user_id)->update(['categories' => json_encode($allCategories)]);
            return;
    }

    public function insertTopFiveCategory()
    {
        ini_set('pcre.backtrack_limit', 10000000);
        ini_set('pcre.recursion_limit', 10000000);

        GenerateTopFiveCategoryPDFJob::dispatch();
        CategoryPricelistUpload::truncate();// table truncate
        return response()->json(['message' => 'Inserted all categories.']);
    }
public function topSellingCategories(Request $request)
{
    $sort_search = $request->input('search', null);
    $filter = $request->input('filter', null);
    $sort_by = $request->input('sort_by', 'total_categories_amount'); 
    $sort_order = $request->input('sort_order', 'desc'); 
    $user = Auth::user();

    $staffUsers = User::whereHas('roles', function ($query) {
        $query->where('role_id', 5);
    })->select('id', 'name')->get();

    $query = User::query()
        ->with([
            'warehouse:id,name',
            'manager:id,name',
            'address_by_party_code:id,city,user_id,acc_code,due_amount,overdue_amount',
            'total_due_amounts'
        ])
        ->where('user_type', 'customer')
        ->whereNotNull('email_verified_at');

    if (!in_array($user->id, ['180', '25606', '169']) && $user->user_type != 'admin') {
        $query->where('manager_id', $user->id);
    }

    if ($sort_search) {
        $query->where(function ($q) use ($sort_search) {
            $q->where('party_code', 'like', "%$sort_search%")
                ->orWhere('phone', 'like', "%$sort_search%")
                ->orWhere('name', 'like', "%$sort_search%")
                ->orWhere('gstin', 'like', "%$sort_search%")
                ->orWhere('company_name', 'like', "%$sort_search%")
                ->orWhereHas('warehouse', fn($subQuery) => $subQuery->where('name', 'like', "%$sort_search%"))
                ->orWhereHas('manager', fn($subQuery) => $subQuery->where('name', 'like', "%$sort_search%"))
                ->orWhereHas('address_by_party_code', fn($subQuery) => $subQuery->where('city', 'like', "%$sort_search%"));
        });
    }

    if ($filter) {
        $query->when($filter === 'approved', fn($q) => $q->where('banned', '0'))
              ->when($filter === 'un_approved', fn($q) => $q->where('banned', '1'));
    }

    if ($request->filled('warehouse')) {
        $query->whereIn('warehouse_id', $request->input('warehouse'));
    }

    if ($request->filled('manager')) {
        $query->whereIn('manager_id', $request->input('manager'));
    }

    if ($request->filled('city')) {
        $query->whereHas('address_by_party_code', fn($q) => $q->where('city', $request->input('city')));
    }

    if ($request->filled('discount')) {
        $query->where('discount', $request->input('discount'));
    }

    $users = $query->get();
    
    // Get all user IDs at once
    $userIds = $users->pluck('id')->toArray();

    // Get all order IDs for users in one query
    $orderIdsByUser = Order::whereIn('user_id', $userIds)
        ->pluck('id', 'user_id')
        ->toArray();

    // Fetch all category spending data in bulk
    $categorySpending = OrderDetail::select(
            'products.category_id',
            'orders.user_id',
            DB::raw('SUM(order_details.price) as total_spent'),
            DB::raw('MAX(order_details.created_at) as latest_purchase_date')
        )
        ->join('products', 'order_details.product_id', '=', 'products.id')
        ->join('orders', 'order_details.order_id', '=', 'orders.id')
        ->whereIn('orders.user_id', $userIds)
        ->groupBy('orders.user_id', 'products.category_id')
        ->orderByDesc('total_spent')
        ->get()
        ->groupBy('user_id');

    // Fetch all category names in a single query
    $categoryNames = Category::whereIn('id', $categorySpending->pluck('category_id')->unique())->pluck('name', 'id');

    // Assign category spending data to users
    foreach ($users as $user) {
        $user->total_categories_amount = 0;
        $user->category_details = collect();

        if (isset($categorySpending[$user->id])) {
            $user->category_details = $categorySpending[$user->id]->map(function ($item) use ($categoryNames) {
                return [
                    'name' => $categoryNames[$item->category_id] ?? 'Unknown',
                    'total_spent' => $item->total_spent,
                    'latest_purchase_date' => $item->latest_purchase_date,
                ];
            });

            // Calculate total category spending
            $user->total_categories_amount = $user->category_details->sum('total_spent');
        }
    }

    // Sorting Logic
    if ($sort_by === 'due_amount') {
        $users = $users->sortBy(fn($user) => optional($user->total_due_amounts->first())->total_due_amount ?? 0, SORT_REGULAR, $sort_order === 'desc');
    } elseif ($sort_by === 'overdue_amount') {
        $users = $users->sortBy(fn($user) => optional($user->total_due_amounts->first())->total_overdue_amount ?? 0, SORT_REGULAR, $sort_order === 'desc');
    } else {
        $users = $users->sortByDesc('total_categories_amount')->values();
    }

    // Paginate results
    $perPage = 15;
    $currentPage = LengthAwarePaginator::resolveCurrentPage();
    $users = new LengthAwarePaginator(
        $users->forPage($currentPage, $perPage),
        $users->count(),
        $perPage,
        $currentPage,
        ['path' => request()->url(), 'query' => request()->query()]
    );

    $cities = Address::distinct()->pluck('city');
    $discounts = User::whereNotNull('discount')->where('discount', '!=', '')->distinct()->pluck('discount');

    return view('backend.customer.customers.Customer_top_selling_category', compact(
        'users', 'sort_search', 'filter', 'sort_by', 'sort_order', 'cities', 'discounts', 'staffUsers'
    ));
}

    
    public function slow_topSellingCategories(Request $request)
    {


        $sort_search = $request->input('search', null);
        $filter = $request->input('filter', null);
        $sort_by = $request->input('sort_by', 'total_categories_amount'); // Default sorting
        $sort_order = $request->input('sort_order', 'desc'); // Default to descending order
        $user = Auth::user();

        $staffUsers = User::whereHas('roles', function ($query) {
            $query->where('role_id', 5);
        })->select('id', 'name')->get();

       

        $query = User::query()
            ->with([
                'warehouse:id,name',
                'manager:id,name',
                'address_by_party_code:id,city,user_id,acc_code,due_amount,overdue_amount',
                'total_due_amounts'
            ])
            ->whereIn('user_type', ['customer'])
            ->whereNotNull('email_verified_at');

        if (!in_array($user->id, ['180', '25606', '169']) && $user->user_type != 'admin') {
            $query->where('manager_id', $user->id);
        }

        

        if ($sort_search) {
            $query->where(function ($q) use ($sort_search) {
                $q->where('party_code', 'like', "%$sort_search%")
                    ->orWhere('phone', 'like', "%$sort_search%")
                    ->orWhere('name', 'like', "%$sort_search%")
                    ->orWhere('gstin', 'like', "%$sort_search%")
                    ->orWhere('company_name', 'like', "%$sort_search%")
                    ->orWhereHas('warehouse', fn($subQuery) => $subQuery->where('name', 'like', "%$sort_search%"))
                    ->orWhereHas('manager', fn($subQuery) => $subQuery->where('name', 'like', "%$sort_search%"))
                    ->orWhereHas('address_by_party_code', fn($subQuery) => $subQuery->where('city', 'like', "%$sort_search%"));
            });
        }

        if ($filter) {
            $query->when($filter === 'approved', fn($q) => $q->where('banned', '0'))
                  ->when($filter === 'un_approved', fn($q) => $q->where('banned', '1'));
        }

        if ($request->filled('warehouse')) {
            $query->whereIn('warehouse_id', $request->input('warehouse'));
        }

        if ($request->filled('manager')) {
            $query->whereIn('manager_id', $request->input('manager'));
        }

        if ($request->filled('city')) {
            $query->whereHas('address_by_party_code', fn($q) => $q->where('city', $request->input('city')));
        }

        if ($request->filled('discount')) {
            $query->where('discount', $request->input('discount'));
        }

        $users = $query->get();


        // echo "<pre>";
        // print_r($users->toArray());
        // die();



        // Process each user to calculate spending per category and fetch the latest purchase date
        foreach ($users as $user) {
            if ($user->categories) {
                $categoryIds = json_decode($user->categories, true);
                $orderIds = Order::where('user_id', $user->id)->pluck('id');

                // Fetch total spent per category and the latest purchase date
                $categorySpending = OrderDetail::select(
                        'products.category_id',
                        DB::raw('SUM(order_details.price) as total_spent'),
                        DB::raw('MAX(order_details.created_at) as latest_purchase_date')
                    )
                    ->join('products', 'order_details.product_id', '=', 'products.id')
                    ->whereIn('order_details.order_id', $orderIds)
                    ->whereIn('products.category_id', $categoryIds)
                    ->groupBy('products.category_id')
                    ->orderByDesc('total_spent')
                    ->get();

                // Calculate total category spending
                $user->total_categories_amount = $categorySpending->sum('total_spent');

                // Attach category names, spending, and latest purchase date to the user
                $user->category_details = $categorySpending->map(function ($item) {
                    $categoryName = Category::find($item->category_id)->name ?? 'Unknown';
                    return [
                        'name' => $categoryName,
                        'total_spent' => $item->total_spent,
                        'latest_purchase_date' => $item->latest_purchase_date,
                    ];
                });
            } else {
                $user->total_categories_amount = 0;
                $user->category_details = collect();
            }
        }


        // Sorting Logic
        if ($sort_by === 'due_amount') {
            $users = $users->sortBy(fn($user) => optional($user->total_due_amounts->first())->total_due_amount ?? 0, SORT_REGULAR, $sort_order === 'desc');
        } elseif ($sort_by === 'overdue_amount') {
            $users = $users->sortBy(fn($user) => optional($user->total_due_amounts->first())->total_overdue_amount ?? 0, SORT_REGULAR, $sort_order === 'desc');
        } else {
            $users = $users->sortByDesc('total_categories_amount')->values();
        }

        // Paginate results
        $perPage = 15;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $users = new LengthAwarePaginator(
            $users->forPage($currentPage, $perPage),
            $users->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $cities = Address::distinct()->pluck('city');
        $discounts = User::whereNotNull('discount')->where('discount', '!=', '')->distinct()->pluck('discount');

        return view('backend.customer.customers.Customer_top_selling_category', compact(
            'users', 'sort_search', 'filter', 'sort_by', 'sort_order', 'cities', 'discounts', 'staffUsers'
        ));
    }




    public function downloadTopSellingCategory(Request $request)
    {
        $groupId = uniqid('group_', true);
        $user_id = $request->query('user_id');

        if (!$user_id) {
            return response()->json(['error' => 'User ID is required'], 400);
        }

        $allusers = User::where('user_type', 'customer')->where('id', $user_id)->get();

        foreach ($allusers as $user) {
            $this->whatsAppWebService = new WhatsAppWebService();
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $categories = json_decode($user->categories, true) ?? [];
            $lastSentCategories = json_decode($user->last_sent_categories, true) ?? [];

            if (empty($lastSentCategories)) {
                return response()->json(['error' => 'No last sent categories found'], 400);
            }

            $allProducts = [];
            $requiredProductCount = 200;
            $categoryNames = [];
            $addedCategories = [];

            // Start processing from the last category in lastSentCategories array
            $reversedCategories = array_reverse($lastSentCategories);

            foreach ($reversedCategories as $categoryId) {
                // Fetch products for the current category
                $products = DB::select(DB::raw("SELECT 
                    `products`.`id`, 
                    `part_no`, 
                    `brand_id`, 
                    `category_groups`.`name` as `group_name`, 
                    `categories`.`name` as `category_name`, 
                    `group_id`, 
                    `category_id`, 
                    `products`.`name`, 
                    `thumbnail_img`, 
                    `products`.`slug`, 
                    `min_qty`, 
                    `mrp`
                FROM `products`
                INNER JOIN `categories` ON `products`.`category_id` = `categories`.`id`
                INNER JOIN `category_groups` ON `categories`.`category_group_id` = `category_groups`.`id`
                WHERE `products`.`category_id` = :categoryId
                AND `published` = 1 
                AND `current_stock` = 1 
                AND `approved` = 1 
                AND `num_of_sale` > 0
                ORDER BY 
                    CASE 
                        WHEN `category_groups`.`id` = 1 THEN 0  
                        WHEN `category_groups`.`id` = 8 THEN 1  
                        ELSE 2 
                    END, 
                    `category_groups`.`name` ASC, 
                    `categories`.`name` ASC, 
                    CASE 
                                WHEN `products`.`name` LIKE '%opel%' THEN 0  -- Opel products priority
                                WHEN `products`.`part_no` COLLATE utf8mb3_general_ci IN (
                                    SELECT `part_no` COLLATE utf8mb3_general_ci FROM `products_api`
                                ) THEN 1
                                ELSE 2 
                    END, 
                    CASE 
                        WHEN `products`.`name` LIKE '%opel%' THEN 0  
                        ELSE 1 
                    END, 
                    CAST(`products`.`mrp` AS UNSIGNED) ASC, 
                    `products`.`name` ASC"), ['categoryId' => $categoryId]);

                $availableProductCount = count($products);
                $remainingCount = $requiredProductCount - count($allProducts);

                if ($availableProductCount == 0) {
                    continue;
                }

                if ($availableProductCount <= $remainingCount) {
                    $categoryNames[] = DB::table('categories')->where('id', $categoryId)->value('name');
                    $addedCategories[] = $categoryId;
                    $selectedProducts = array_slice($products, 0, $remainingCount);
                } elseif ($availableProductCount > $remainingCount) {
                    continue;
                }

                foreach ($selectedProducts as $product) {
                    $mrp = is_numeric($product->mrp) ? (float) $product->mrp : 0;
                    $discount = isset($user->discount) && is_numeric($user->discount) ? (float) $user->discount : 0;
                    $calculated_price = (100 - $discount) * $mrp / 100;
                    $product->price = format_price_in_rs(ceil_price(is_numeric($calculated_price) ? $calculated_price : 0));
                }

                $allProducts = array_merge($allProducts, $selectedProducts);

                if (count($allProducts) >= $requiredProductCount) {
                    break;
                }
            }

            if (empty($allProducts)) {
                return response()->json(['error' => 'No products found'], 400);
            }

            // Generate PDF
            $fileName = time() . '_top_categories_' . $user->id . '.pdf';
            $filePath = public_path('pdfs/' . $fileName);

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'default_font_size' => 10,
                'default_font' => 'Arial',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 40,
                'margin_bottom' => 20,
                'margin_header' => 10,
                'margin_footer' => 10,
            ]);

            // PDF Header
            $mpdf->SetHTMLHeader('
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr><td style="text-align: right;">
                        <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" width="100%" />
                    </td></tr>
                </table>
            ');

            // PDF Footer
            $mpdf->SetHTMLFooter('
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr bgcolor="#174e84">
                        <td style="height: 40px; text-align: center; color: #fff; font-family: Arial; font-weight: bold;">
                            Mazing Business Price List - ' . date('d-m-Y h:i:s A') . '
                        </td>
                    </tr>
                </table>
            ');

            // PDF Content
            $html = '<table width="100%" border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse;">
                <thead>
                    <tr>
                        <th width="5%">SN</th>
                        <th width="10%">Part No</th>
                        <th width="10%">Image</th>
                        <th width="30%">Item Name</th>
                        <th width="15%">Item Group</th>
                        <th width="15%">Category</th>
                        <th width="15%">Net Price</th>
                    </tr>
                </thead>
                <tbody>';

            $serialNumber = 1;
            foreach ($allProducts as $product) {
                $thumbnail = Upload::find($product->thumbnail_img);
                $photoUrl = $thumbnail ? asset('public/' . $thumbnail->file_name) : asset('public/assets/img/placeholder.jpg');

                $isNoCreditItem = Product::where('part_no', $product->part_no)->value('cash_and_carry_item') == 1;
                $isFastDispatch = DB::table('products_api')->where('part_no', $product->part_no)->exists();
                $offerProduct = OfferProduct::where('part_no', $product->part_no)->exists();
                $fastDispatchImage = asset('public/uploads/fast_dispatch.jpg');
                $offerProductImage = asset('public/uploads/offers-icon.png');

                $html .= '<tr style="height: 75px;">
                    <td width="5%" style="border: 2px solid #000; text-align: center;">' . $serialNumber++ . '</td>
                    <td width="10%" style="border: 2px solid #000; text-align: center;">' . htmlspecialchars($product->part_no) . '</td>
                    <td width="10%" style="border: 2px solid #000; text-align: center;"><img src="' . htmlspecialchars($photoUrl) . '" width="80"></td>
                    <td width="30%" style="border: 2px solid #000; text-align: left;">' . htmlspecialchars($product->name);
                
                if ($isNoCreditItem) {
                    $html .= '<br><span style="background:#dc3545;color:#fff;font-size:10px;border-radius:3px;padding:2px 5px;">No Credit Item</span>';
                }
                if ($isFastDispatch) {
                    $html .= '<br><img src="' . $fastDispatchImage . '" width="68">';
                }
                if ($offerProduct) {
                    $html .= '<br><img src="' . $offerProductImage . '" width="68">';
                }

                $html .= '</td><td width="15%" style="border: 2px solid #000; text-align: center;">' . htmlspecialchars($product->group_name) . '</td>
                    <td width="15%" style="border: 2px solid #000; text-align: center;">' . htmlspecialchars($product->category_name) . '</td>
                    <td width="15%" style="border: 2px solid #000; text-align: center;">' . $product->price . '</td>
                </tr>';
            }

            $html .= '</tbody></table>';
            $mpdf->WriteHTML($html);
            $mpdf->Output($filePath, 'F');

            // return response()->download($filePath);
            return response()->download($filePath, $fileName, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);
        }
    }


    // sending whatsapp code
      public function whatsappTopFiveCategory(Request $request){


        $groupId = uniqid('group_', true);
        $user_id = $request->query('user_id');

        if (!$user_id) {
            return response()->json(['error' => 'User ID is required'], 400);
        }

        $allusers = User::where('user_type', 'customer')->where('id', $user_id)->get();

        foreach ($allusers as $user) {
            $this->whatsAppWebService = new WhatsAppWebService();
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $categories = json_decode($user->categories, true) ?? [];
            $lastSentCategories = json_decode($user->last_sent_categories, true) ?? [];

            if (empty($lastSentCategories)) {
                return response()->json(['error' => 'No last sent categories found'], 400);
            }

            $allProducts = [];
            $requiredProductCount = 200;
            $categoryNames = [];
            $addedCategories = [];

            // Start processing from the last category in lastSentCategories array
            $reversedCategories = array_reverse($lastSentCategories);

            foreach ($reversedCategories as $categoryId) {
                // Fetch products for the current category
                $products = DB::select(DB::raw("SELECT 
                    `products`.`id`, 
                    `part_no`, 
                    `brand_id`, 
                    `category_groups`.`name` as `group_name`, 
                    `categories`.`name` as `category_name`, 
                    `group_id`, 
                    `category_id`, 
                    `products`.`name`, 
                    `thumbnail_img`, 
                    `products`.`slug`, 
                    `min_qty`, 
                    `mrp`
                FROM `products`
                INNER JOIN `categories` ON `products`.`category_id` = `categories`.`id`
                INNER JOIN `category_groups` ON `categories`.`category_group_id` = `category_groups`.`id`
                WHERE `products`.`category_id` = :categoryId
                AND `published` = 1 
                AND `current_stock` = 1 
                AND `approved` = 1 
                AND `num_of_sale` > 0
                ORDER BY 
                    CASE 
                        WHEN `category_groups`.`id` = 1 THEN 0  
                        WHEN `category_groups`.`id` = 8 THEN 1  
                        ELSE 2 
                    END, 
                    `category_groups`.`name` ASC, 
                    `categories`.`name` ASC, 
                    CASE 
                                WHEN `products`.`name` LIKE '%opel%' THEN 0  -- Opel products priority
                                WHEN `products`.`part_no` COLLATE utf8mb3_general_ci IN (
                                    SELECT `part_no` COLLATE utf8mb3_general_ci FROM `products_api`
                                ) THEN 1
                                ELSE 2 
                    END, 
                    CASE 
                        WHEN `products`.`name` LIKE '%opel%' THEN 0  
                        ELSE 1 
                    END, 
                    CAST(`products`.`mrp` AS UNSIGNED) ASC, 
                    `products`.`name` ASC"), ['categoryId' => $categoryId]);

                $availableProductCount = count($products);
                $remainingCount = $requiredProductCount - count($allProducts);

                if ($availableProductCount == 0) {
                    continue;
                }

                if ($availableProductCount <= $remainingCount) {
                    $categoryNames[] = DB::table('categories')->where('id', $categoryId)->value('name');
                    $addedCategories[] = $categoryId;
                    $selectedProducts = array_slice($products, 0, $remainingCount);
                } elseif ($availableProductCount > $remainingCount) {
                    continue;
                }

                foreach ($selectedProducts as $product) {
                    $mrp = is_numeric($product->mrp) ? (float) $product->mrp : 0;
                    $discount = isset($user->discount) && is_numeric($user->discount) ? (float) $user->discount : 0;
                    $calculated_price = (100 - $discount) * $mrp / 100;
                    $product->price = format_price_in_rs(ceil_price(is_numeric($calculated_price) ? $calculated_price : 0));
                }

                $allProducts = array_merge($allProducts, $selectedProducts);

                if (count($allProducts) >= $requiredProductCount) {
                    break;
                }
            }

            if (empty($allProducts)) {
                return response()->json(['error' => 'No products found'], 400);
            }

            // Generate PDF
            $fileName = time() . '_top_categories_' . $user->id . '.pdf';
            $filePath = public_path('pdfs/' . $fileName);

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'default_font_size' => 10,
                'default_font' => 'Arial',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 40,
                'margin_bottom' => 20,
                'margin_header' => 10,
                'margin_footer' => 10,
            ]);

            // PDF Header
            $mpdf->SetHTMLHeader('
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr><td style="text-align: right;">
                        <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" width="100%" />
                    </td></tr>
                </table>
            ');

            // PDF Footer
            $mpdf->SetHTMLFooter('
                <table width="100%" border="0" cellpadding="0" cellspacing="0">
                    <tr bgcolor="#174e84">
                        <td style="height: 40px; text-align: center; color: #fff; font-family: Arial; font-weight: bold;">
                            Mazing Business Price List - ' . date('d-m-Y h:i:s A') . '
                        </td>
                    </tr>
                </table>
            ');

            // PDF Content
            $html = '<table width="100%" border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse;">
                <thead>
                    <tr>
                        <th width="5%">SN</th>
                        <th width="10%">Part No</th>
                        <th width="10%">Image</th>
                        <th width="30%">Item Name</th>
                        <th width="15%">Item Group</th>
                        <th width="15%">Category</th>
                        <th width="15%">Net Price</th>
                    </tr>
                </thead>
                <tbody>';

            $serialNumber = 1;
            foreach ($allProducts as $product) {
                $thumbnail = Upload::find($product->thumbnail_img);
                $photoUrl = $thumbnail ? asset('public/' . $thumbnail->file_name) : asset('public/assets/img/placeholder.jpg');

                $isNoCreditItem = Product::where('part_no', $product->part_no)->value('cash_and_carry_item') == 1;
                $isFastDispatch = DB::table('products_api')->where('part_no', $product->part_no)->exists();
                $offerProduct = OfferProduct::where('part_no', $product->part_no)->exists();
                $fastDispatchImage = asset('public/uploads/fast_dispatch.jpg');
                $offerProductImage = asset('public/uploads/offers-icon.png');

                $html .= '<tr style="height: 75px;">
                    <td width="5%" style="border: 2px solid #000; text-align: center;">' . $serialNumber++ . '</td>
                    <td width="10%" style="border: 2px solid #000; text-align: center;">' . htmlspecialchars($product->part_no) . '</td>
                    <td width="10%" style="border: 2px solid #000; text-align: center;"><img src="' . htmlspecialchars($photoUrl) . '" width="80"></td>
                    <td width="30%" style="border: 2px solid #000; text-align: left;">' . htmlspecialchars($product->name);
                
                if ($isNoCreditItem) {
                    $html .= '<br><span style="background:#dc3545;color:#fff;font-size:10px;border-radius:3px;padding:2px 5px;">No Credit Item</span>';
                }
                if ($isFastDispatch) {
                    $html .= '<br><img src="' . $fastDispatchImage . '" width="68">';
                }
                if ($offerProduct) {
                    $html .= '<br><img src="' . $offerProductImage . '" width="68">';
                }

                $html .= '</td><td width="15%" style="border: 2px solid #000; text-align: center;">' . htmlspecialchars($product->group_name) . '</td>
                    <td width="15%" style="border: 2px solid #000; text-align: center;">' . htmlspecialchars($product->category_name) . '</td>
                    <td width="15%" style="border: 2px solid #000; text-align: center;">' . $product->price . '</td>
                </tr>';
            }

            $html .= '</tbody></table>';
            $mpdf->WriteHTML($html);
            $mpdf->Output($filePath, 'F');
            $pdfUrl = url('public/pdfs/' . $fileName);
            
            // return response()->download($filePath);

            // WhatsApp Template
            $pdfUrl = url('public/pdfs/' . $fileName);
            $image_url = 'https://mazingbusiness.com/public/assets/img/1000105696.jpg';
            $document_file_name = basename($pdfUrl);
            $managerPhone = $this->getManagerPhone($user->manager_id);

            $media_id = $this->whatsAppWebService->uploadMedia($pdfUrl);
        
                $templateData = [
                    'name' => 'utility_pricelist_pdf',
                    'language' => 'en_US', 
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                ['type' => 'document', 'document' => ['filename' => $document_file_name,'id' => $media_id['media_id']]],
                               
                            ],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $user->name],  // Customer Name
                                ['type' => 'text', 'text' => $managerPhone]  // Manager Phone
                            ],
                        ],
                       
                    ]
                ];
                //$whatsappNumbers=[$addressData->phone,$manager_phone];
                $whatsappNumbers=[$user->phone];
                foreach ($whatsappNumbers as $number) {
                    if (!empty($number)) {
                        $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($number, $templateData);
                       

                        // Check response and update status
                        if (isset($jsonResponse['messages'][0]['message_status']) && $jsonResponse['messages'][0]['message_status'] === 'accepted') {

                            return response()->json(['success' => true, 'message' => 'PDF sent successfully']);
                           echo "Whatsapp sent to user";
                           // Log::info("whatsapp sent");
                        } else {
                            return response()->json(['success' => false, 'message' => 'Failed to send PDF']);
                            $error = $jsonResponse['messages'][0]['message_status'] ?? 'unknown';
                           // Log::error("Failed To sent");
                        }
                    }
                }
            }
       
      }


    public function newArrivalApi()
    {

        // main code start 

            set_time_limit(-1);  // No timeout
            header('Keep-Alive: timeout=86400, max=100');
            header('Cache-Control: no-cache');
            header('Connection: Keep-Alive');
            ini_set('memory_limit', '-1');
			//$groupId="group_67d2a74d6f27c8.50211675";
            $groupId = uniqid('group_', true);
            NewArrival::truncate();
            NewArrivalJob::dispatch($groupId);
            //SendWhatsAppMessagesJob::dispatch($groupId);

            return response()->json(['success' => true, 'msg' => "Done."]); 

        // main code end


        // ini_set('pcre.backtrack_limit', 10000000);
        // ini_set('pcre.recursion_limit', 10000000);
        // ini_set('memory_limit', '-1'); // No memory limit
        // $users = User::where('id', 24198)->get();
        // $type = 'net';

        // // echo "test";
        // // die();

        // $this->whatsAppWebService = new WhatsAppWebService();
        // $groupId = uniqid('group_', true);

        // foreach($users as $user){
        //     // Fetch products updated within the last 10 days with category & group name

        //     $discount = $user->discount == "" ? 0 : $user->discount;
        //     $products = DB::select(DB::raw("
        //         SELECT 
        //             `products`.`id`, 
        //             `products`.`part_no`, 
        //             `products`.`brand_id`, 
        //             `category_groups`.`name` as `group_name`, 
        //             `categories`.`name` as `category_name`, 
        //             `products`.`group_id`, 
        //             `products`.`category_id`, 
        //             `products`.`name`, 
        //             `products`.`thumbnail_img`, 
        //             `products`.`slug`, 
        //             `products`.`min_qty`, 
        //             `products`.`mrp`
        //         FROM `products`
        //         INNER JOIN `categories` ON `products`.`category_id` = `categories`.`id`
        //         INNER JOIN `category_groups` ON `categories`.`category_group_id` = `category_groups`.`id`
        //         WHERE `products`.`updated_at` >= DATE_SUB(CURDATE(), INTERVAL 10 DAY)
        //         AND `products`.`published` = 1 
        //         AND `products`.`current_stock` = 1 
        //         AND `products`.`approved` = 1
        //         ORDER BY 
        //             CASE 
        //                 WHEN `category_groups`.`id` = 1 THEN 0  -- Power Tools group first
        //                 WHEN `category_groups`.`id` = 8 THEN 1  -- Cordless Tools second
        //                 ELSE 2 
        //             END, 
        //             `category_groups`.`name` ASC, 
        //             `categories`.`name` ASC, 
        //             CASE 
        //                 WHEN `products`.`name` LIKE '%opel%' THEN 0  -- Opel products first
        //                 WHEN `products`.`part_no` COLLATE utf8mb3_general_ci IN (
        //                     SELECT `part_no` COLLATE utf8mb3_general_ci FROM `products_api`
        //                 ) THEN 1
        //                 ELSE 2 
        //             END, 
        //             CAST(`products`.`mrp` AS UNSIGNED) ASC, 
        //             `products`.`name` ASC
        //         -- LIMIT 500
        //     "));

        //     if (empty($products)) {
        //         return response()->json(['error' => 'No products found in the last 10 days'], 404);
        //     }

        //     $fileName = time() . '_new_arrival_products.pdf';
        //     $filePath = public_path('pdfs/' . $fileName);

        //     try {
        //         $mpdf = new \Mpdf\Mpdf([
        //             'mode' => 'utf-8',
        //             'format' => 'A4',
        //             'default_font_size' => 10,
        //             'default_font' => 'Arial',
        //             'margin_left' => 10,
        //             'margin_right' => 10,
        //             'margin_top' => 40,
        //             'margin_bottom' => 20,
        //             'margin_header' => 10,
        //             'margin_footer' => 10,
        //         ]);

        //       // PDF Header
        //         $mpdf->SetHTMLHeader('
        //             <table width="100%" border="0">
        //                 <tr>
        //                     <td style="text-align: right;">
        //                         <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" width="100%" />
        //                     </td>
        //                 </tr>
        //             </table>
        //         ');

        //         // PDF Footer
        //         $mpdf->SetHTMLFooter('
        //             <table width="100%" border="0">
        //                 <tr bgcolor="#174e84">
        //                     <td style="height: 40px; text-align: center; color: #fff; font-weight: bold;">
        //                         Mazing Business - New Arrivals as of ' . date('d-m-Y h:i:s A') . '
        //                     </td>
        //                 </tr>
        //             </table>
        //         ');

        //         // NEW ARRIVAL Title with Styling
        //         $html = '<div style="text-align: center; font-size: 22px; font-weight: bold; color: black; margin-bottom: 10px; text-transform: uppercase;">
        //                     NEW ARRIVAL
        //                 </div>';

        //         // Table Header
        //         $html .= '<table width="100%" border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse;">
        //             <thead>
        //                 <tr style="background-color:#f1f1f1;">
        //                     <th width="5%">SN</th>
        //                     <th width="10%">PART NO</th>
        //                     <th width="10%">IMAGE</th>
        //                     <th width="30%">ITEM NAME</th>
        //                     <th width="15%">ITEM GROUP</th>
        //                     <th width="15%">CATEGORY</th>
        //                     <th width="15%">NET PRICE</th>
        //                 </tr>
        //             </thead>
        //             <tbody>';

        //         $serialNumber = 1;

        //         foreach ($products as $product) {
                    
        //             $thumbnail = $product->thumbnail_img 
        //                 ? uploaded_asset($product->thumbnail_img) 
        //                 : asset('uploads/placeholder.jpg');

        //             // Check for No Credit Item
        //             $isNoCreditItem = Product::where('part_no', $product->part_no)->value('cash_and_carry_item') == 1;
        //             $noCreditBadge = $isNoCreditItem
        //                 ? '<br/><span style="background:#dc3545;color:#fff;font-size:10px;border-radius:3px;padding:2px 5px;">No Credit Item</span>'
        //                 : '';

        //             // Check for Fast Dispatch
        //             $isFastDispatch = DB::table('products_api')->where('part_no', $product->part_no)->exists();
        //             $fastDispatchImage = public_path('uploads/fast_dispatch.jpg');
        //             $fastDispatchBadge = $isFastDispatch
        //                 ? '<br/><img src="' . $fastDispatchImage . '" alt="Fast Delivery" style="width: 80px; height: 20px; margin-top: 5px;">'
        //                 : '';

        //             // Format price
        //             $netPrice = ceil_price((100-$discount) * $product->mrp / 100);
        //             $list_price = ceil_price($netPrice * 131.6 / 100);
        //             $price = $type == 'net' ? format_price_in_rs($netPrice) : format_price_in_rs($list_price);

        //             // Append rows to table
        //             $html .= '<tr>
        //                 <td width="5%" style="text-align: center;">' . $serialNumber++ . '</td>
        //                 <td width="10%" style="text-align: center;">' . htmlspecialchars($product->part_no) . $noCreditBadge . '</td>
        //                 <td width="10%" style="text-align: center;">
        //                     <img src="' . $thumbnail . '" style="width: 60px; height: 60px;">
        //                 </td>
        //                 <td width="30%" style="text-align: left; font-weight: bold;">
        //                     <a href="' . route('product', ['slug' => $product->slug]) . '" target="_blank">' . htmlspecialchars($product->name) . '</a>' . $fastDispatchBadge . '
        //                 </td>
        //                 <td width="15%" style="text-align: center;">' . htmlspecialchars($product->group_name ?? 'N/A') . '</td>
        //                 <td width="15%" style="text-align: center;">' . htmlspecialchars($product->category_name ?? 'N/A') . '</td>
        //                 <td width="15%" style="text-align: center;">' . $price . '</td>
        //             </tr>';
        //         }

        //         $html .= '</tbody></table>';

        //         // Write content to PDF
        //         $mpdf->WriteHTML($html);
        //         $mpdf->Output($filePath, 'F');

        //     // ***************************whatsapp code start ***************  

        //          $pdfUrl=url('public/pdfs/' . $fileName);
        //          $document_file_name=basename($pdfUrl);
        //          $media_id = $this->whatsAppWebService->uploadMedia($pdfUrl);
        //          $managerPhone=$this->getManagerPhone($user->manager_id);

        //          $media=$this->whatsAppWebService->uploadMedia($pdfUrl);

        //         $templateData = [
        //             'name' => 'utility_pricelist_pdf',
        //             'language' => 'en_US', 
        //             'components' => [
        //                 [
        //                     'type' => 'header',
        //                     'parameters' => [
        //                         ['type' => 'document', 'document' => ['filename' => $document_file_name,'id' => $media_id['media_id']]],
                               
        //                     ],
        //                 ],
        //                 [
        //                     'type' => 'body',
        //                     'parameters' => [
        //                         ['type' => 'text', 'text' => $user->name],  // Customer Name
        //                         ['type' => 'text', 'text' => $managerPhone]  // Manager Phone
        //                     ],
        //                 ],
                       
        //             ]
        //         ];

                

        //         DB::table('wa_sales_queue')->insert([
        //             'group_id' => $groupId,'callback_data' => $templateData['name'],
        //             'recipient_type' => 'individual',
        //              //'to_number' => $user->phone ?? '',
        //              'to_number' => '9894753728',
        //              'file_name' => 'new_arrival',
        //              'type' => 'template','file_url' => $pdfUrl,
        //             'content' => json_encode($templateData),'status' => 'pending',
        //             'created_at' => now(),'updated_at' => now()
        //         ]);

        //          // Delete the PDF file after queuing the message
        //         if (file_exists($filePath)) {
        //             unlink($filePath); // This will delete the file
        //         }
        //     // **********************************whatsapp code end *****************************

        //     } catch (\Exception $e) {
        //         return response()->json(['error' => 'PDF generation failed', 'message' => $e->getMessage()], 500);
        //     }
        //  }

        //  SendWhatsAppMessagesJob::dispatch($groupId);

        // return response()->json([
        //     'success' => true,
        //     'message' => 'PDF generated & sent to whatsapp successfully',
        //     // 'pdf_url' => url('public/pdfs/' . $fileName)
        // ]);


        // return response()->download($filePath);
    }


    public function generateCategoryPdf()
    {

        $groupId = uniqid('group_', true);
        

        // Increase memory limit and execution time to avoid timeouts
            set_time_limit(-1);  // No timeout
            header('Keep-Alive: timeout=86400, max=100');
            header('Cache-Control: no-cache');
            header('Connection: Keep-Alive');
            ini_set('memory_limit', '512M');


       // CategoryPricelistUploadPdf::dispatch($groupId);

        CategoryProductsJob::dispatch($groupId);
        // SendWhatsAppMessagesJob::dispatch($groupId);
        return response()->json(['success' => true, 'msg' => "Done."]);
         // return response()->json(['success' => true, 'pdf_url' => url('public/pdfs/' . $fileName)]);

        
    }





    


    

}
