<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\AdminStatementController;
use App\Mail\InvoiceEmailManager;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Carrier;
use App\Models\CombinedOrder;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Pincode;
use App\Models\City;
use App\Models\State;
use App\Models\ProductWarehouse;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\RewardPointsOfUser;
use App\Models\RewardUser;
use App\Models\Barcode;
use App\Models\OwnBrandCategoryGroup;
use App\Models\OwnBrandCategory;
use App\Models\OwnBrandProduct;
use App\Models\OwnBrandOrder;
use App\Models\OwnBrandOrderDetail;

use App\Models\SubOrder;
use App\Models\SubOrderDetail;
use App\Models\Challan;
use App\Models\ChallanDetail;
use App\Models\PurchaseBag;
use App\Models\ResetProduct;
use App\Models\ResetProductT;
use App\Models\ResetInventoryProduct;
use App\Models\OpeningStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceDetail;

use App\Models\DebitNoteInvoice;
use App\Models\DebitNoteInvoiceDetail;

use App\Models\InvoiceOrderDetail;
use App\Models\ProductApi;
use App\Models\PaymentHistory;

use App\Models\MarkAsLostItem;


use App\Models\Manager41SubOrder;
use App\Models\Manager41SubOrderDetail;
use App\Models\Manager41Order;
use App\Models\Manager41OrderDetail;

use App\Http\Controllers\ZohoController;

use App\Utility\NotificationUtility;
use App\Utility\SmsUtility;
use App\Utility\WhatsAppUtility;
use Auth;
use CoreComponentRepository;
use App\Services\WhatsAppWebService;
use Carbon\Carbon;

use Illuminate\Support\Facades\Route;
use Mail;
use PDF;
use Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// use App\Services\StatementCalculationService;
use Illuminate\Support\Facades\Schema;

use App\Exports\PendingOrdersExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Staff;

class OrderController extends Controller {
  // protected $statementCalculationService;
  // public function __construct(StatementCalculationService $statementCalculationService) {
  public function __construct() {  
    // Staff Permission Check
    $this->middleware(['permission:view_all_orders|view_inhouse_orders|view_seller_orders|view_pickup_point_orders'])->only('all_orders');
     $this->middleware(['permission:view_order_details'])->only('show');
    // $this->middleware(['permission:delete_order'])->only('destroy');
    // Inject the service
    // $this->statementCalculationService = $statementCalculationService;


  }

  private function getManagerPhone($managerId)
  {
      $managerData = DB::table('users')
          ->where('id', $managerId)
          ->select('phone')
          ->first();

      return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
  }

  // Send Approved Product Whatsapp
  private function sendApprovedProductWhatsApp($orderId, $pdfUrl)
  {
        $WhatsAppWebService=new WhatsAppWebService();
        $order = Order::with(['sub_order'])->findOrFail($orderId);
        $address = Address::find($order->address_id);

        if (!$address) {
            return response()->json(['error' => 'Customer address not found']);
        }

        // Map acc_code тЖТ party_code to get user
        $user = User::where('party_code', $address->acc_code)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found for acc_code: ' . $address->acc_code]);
        }

        $manager_phone = $this->getManagerPhone($user->manager_id ?? null);
        $customer_phone = $address->phone;

        $templateData = [
            'name' => 'utility_product_approved',
            'language' => 'en_US',
            'components' => [
                [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'document',
                            'document' => [
                                'link' => $pdfUrl,
                                'filename' => basename($pdfUrl),
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $address->company_name],
                        ['type' => 'text', 'text' => $order->code],
                        ['type' => 'text', 'text' => \Carbon\Carbon::parse($order->created_at)->format('Y-m-d')],
                        ['type' => 'text', 'text' => $manager_phone],
                    ],
                ],
            ],
        ];

        // Send WhatsApp to customer
        $customerResponse = $WhatsAppWebService->sendTemplateMessage($customer_phone, $templateData);
       // $customerResponse = $WhatsAppWebService->sendTemplateMessage("7044300330", $templateData);

        // Send WhatsApp to manager
       // $managerResponse = $WhatsAppWebService->sendTemplateMessage("7044300330", $templateData);
        $managerResponse = $WhatsAppWebService->sendTemplateMessage($manager_phone, $templateData);

        return response()->json([
            'customer_response' => $customerResponse,
            'manager_response' => $managerResponse,
        ]);
  }

  // Generate Approved product pdf
  public function generateApprovalPDF($orderId = 6867)
  {
      $order = Order::with(['sub_order.sub_order_details.product_data'])->findOrFail($orderId);

        $shippingAddress = Address::find($order->sub_order->first()->shipping_address_id); // take any one, assuming same

        $groupedSubOrders = [];

        foreach ($order->sub_order as $subOrder) {
            if ($subOrder->type !== 'sub_order') continue;

            $approved = collect();
            $unavailable = collect();

            foreach ($subOrder->sub_order_details as $detail) {
                if ($detail->approved_quantity > 0) {
                    $approved->push([
                        'product_name' => $detail->product_data->name ?? '',
                        'part_no' => $detail->product_data->part_no ?? '',
                        'slug' => $detail->product_data->slug ?? '',
                        'order_qty' => $detail->quantity,
                        'approved_qty' => $detail->approved_quantity,
                        'rate' => $detail->approved_rate,
                        'bill_amount' => $detail->approved_quantity * $detail->approved_rate,
                        'is_new' => $detail->new_item
                    ]);
                } else {
                    $unavailable->push([
                        'product_name' => $detail->product_data->name ?? '',
                        'part_no' => $detail->product_data->part_no ?? '',
                        'slug' => $detail->product_data->slug ?? '',
                        'qty' => $detail->quantity
                    ]);
                }
            }

            $groupedSubOrders[] = [
                'warehouse_name' => $subOrder->order_warehouse->name ?? 'N/A',
                'sub_order_id' => $subOrder->id,
                'approvedProducts' => $approved,
                'unavailableItems' => $unavailable,
            ];
        }

        $pdfData = [
            'order' => $order,
            'userDetails' => $shippingAddress,
            'groupedSubOrders' => $groupedSubOrders
        ];

        $pdf = PDF::loadView('backend.sales.approved_products', $pdfData);
        $fileName = 'approved-products-grouped-' . $orderId . '-' . uniqid() . '.pdf';
        $filePath = public_path('approved_products_pdf/' . $fileName);
        $pdf->save($filePath);

        return url('public/approved_products_pdf/' . $fileName);
  }


  public function generateDispatchPDF($challanId)
  {
        try {
            $challan = Challan::with(['challan_details.product_data', 'sub_order'])->findOrFail($challanId);

            // Ensure the sub_order relationship is loaded to access order data
            $subOrder = $challan->sub_order;
            $order = $subOrder ? $subOrder->order : null;

            if (!$order) {
                return response()->json(['error' => 'Order not found for the provided challan.'], 404);
            }

            $userDetails = $challan->user;

            $dispatchData = $challan->challan_details->map(function ($detail) {
                $product = $detail->product_data;
                return [
                    'part_no'    => $product ? $product->part_no : '-',
                    'item_name'  => $product ? $product->name : '-',
                    'slug'       => $product ? $product->slug : null,
                    'billed_qty' => $detail->quantity,
                    'rate'       => $detail->rate,
                    'bill_amount'=> $detail->final_amount,
                ];
            });

            $pdfData = [
                'dispatchData' => $dispatchData,
                'userDetails'  => $userDetails,
                'order'        => $order,
            ];

            $pdf = PDF::loadView('backend.invoices.dispatch_product', $pdfData);
            $fileName = 'dispatch-data-' . $challanId . '-' . uniqid() . '.pdf';
            $filePath = public_path('approved_products_pdf/' . $fileName);
            $pdf->save($filePath);

            $pdfUrl=url('public/approved_products_pdf/' . $fileName);
            return $pdfUrl;

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
   }



   private function sendDispatchNotification($challanId, $pdfUrl)
    {
        try {
            $WhatsAppWebService = new WhatsAppWebService();

            // Fetch Challan Data with Sub Order and User
            $challan = Challan::with(['user', 'sub_order'])->findOrFail($challanId);
            $userDetails = $challan->user;

            // Ensure the sub_order relationship is loaded to access order data
            $subOrder = $challan->sub_order;
            $order = $subOrder ? $subOrder->order : null;

            if (!$order) {
                return response()->json(['error' => 'Order not found for the provided challan.'], 404);
            }

            // Extract order details
            $orderCode = $order->code;
            $orderDate = \Carbon\Carbon::parse($order->created_at)->format('Y-m-d');

            // Fetch Manager Phone
            $manager_phone = $this->getManagerPhone($userDetails->manager_id ?? null);
            $customer_phone = $userDetails->phone;

            // WhatsApp Template Data
            $templateData = [
                'name' => 'utiltiy_product_dispatch',
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => [
                                    'link' => $pdfUrl,
                                    'filename' => basename($pdfUrl),
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $userDetails->company_name],
                            ['type' => 'text', 'text' => $orderCode],      // Order Code
                            ['type' => 'text', 'text' => $challan->warehouse],
                            ['type' => 'text', 'text' => $orderDate],      // Order Created Date
                            ['type' => 'text', 'text' => $manager_phone],
                        ],
                    ],
                ],
            ];



            // Send WhatsApp to Customer
            //$customerResponse = $WhatsAppWebService->sendTemplateMessage("7044300330", $templateData);
             $customerResponse = $WhatsAppWebService->sendTemplateMessage($customer_phone, $templateData);

            // Send WhatsApp to Manager
             $managerResponse = $WhatsAppWebService->sendTemplateMessage($manager_phone, $templateData);

            return response()->json([
                'customer_response' => $customerResponse,
                 'manager_response' => $managerResponse,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

  // All Orders

public function all_orders(Request $request)
{
    CoreComponentRepository::instantiateShopRepository();

    $date            = $request->date;
    $sort_search     = null;
    $delivery_status = null;
    $payment_status  = '';

    $admin_user_id = User::where('user_type', 'admin')->first()->id;

    // Who is 41 manager?
    // ─────────────────────────────────────────────────────────────────────────
    // Old:
    // $title = strtolower(trim((string) Auth::user()->user_title));
    // $is41Manager = in_array($title, ['manager_41'], true);
    //
    // New: robust check (uses helper if available)
    // ─────────────────────────────────────────────────────────────────────────
    $is41Manager =
        (method_exists($this, 'isActingAs41Manager') && $this->isActingAs41Manager()) // [CHANGED]
        || (function () { // [ADDED]
            $user = Auth::user(); // [ADDED]
            $raw = strtolower(preg_replace('/[\s\-_]+/', '', (string) ($user->user_title ?? ''))); // [ADDED]
            return in_array($raw, ['manager41','41manager','manager_41'], true) // [ADDED]
                || (bool) ($user->is_manager_41 ?? false); // [ADDED]
        })(); // [ADDED]

    // Distinct Salezing statuses for filter dropdown (kept same)
    $salzing_statuses = DB::table('salezing_logs')->distinct()->pluck('response');

    // Only touch the original orders table for this auto-approval sync
    if (!$is41Manager) {
        DB::table('orders')
            ->join('order_approvals', 'orders.code', '=', 'order_approvals.code')
            ->where('order_approvals.status', 'Approved')
            ->update(['orders.delivery_status' => 'Approved']);
    }

    // Dynamic table alias to reuse the same filter code
    $table = $is41Manager ? 'manager_41_orders' : 'orders'; // [CHANGED]

    // Debug breadcrumbs (remove after verification)                                // [ADDED]
    \Log::debug('all_orders table pick', ['is41Manager' => $is41Manager, 'table' => $table]); // [ADDED]

    // Base select with common joins (works for both tables)
    $orders = ($is41Manager ? Manager41Order::query() : Order::query()) // [CHANGED]
        ->select(
            $table . '.*',
            'addresses.company_name',
            'addresses.due_amount',
            'addresses.overdue_amount',
            'addresses.dueDrOrCr',
            'addresses.overdueDrOrCr',
            'salezing_logs.response',
            'salezing_logs.status',
            'users.warehouse_id',
            'manager_users.name as manager_name',
            'warehouses.name as warehouse_name'
        )
        ->join('addresses', $table . '.address_id', '=', 'addresses.id')
        // keep Salezing log join; safe if no matching rows
        ->leftJoin(
            'salezing_logs',
            DB::raw('CAST(' . $table . '.code AS CHAR CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci)'),
            '=',
            'salezing_logs.code'
        )
        ->join('users', $table . '.user_id', '=', 'users.id')
        ->leftJoin('users as manager_users', 'users.manager_id', '=', 'manager_users.id')
        ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
        ->orderBy($table . '.id', 'desc');

    // Route/permission-specific filtering (preserved)
    if (Route::currentRouteName() == 'inhouse_orders.index' && Auth::user()->can('view_inhouse_orders')) {
        $orders->where($table . '.seller_id', '=', $admin_user_id);
    } elseif (Route::currentRouteName() == 'seller_orders.index' && Auth::user()->can('view_seller_orders')) {
        $orders->where($table . '.seller_id', '!=', $admin_user_id);
    }

    // Search
    if ($request->search) {
        $sort_search = $request->search;
        $orders->where($table . '.code', 'like', '%' . $sort_search . '%');
    }

    // Payment status
    if ($request->payment_status !== null && $request->payment_status !== '') {
        $payment_status = $request->payment_status;
        $orders->where($table . '.payment_status', $payment_status);
    }

    // Delivery status
    if ($request->delivery_status !== null && $request->delivery_status !== '') {
        $delivery_status = $request->delivery_status;
        $orders->where($table . '.delivery_status', $delivery_status);
    }

    // Date range
    if ($date != null) {
        $orders->whereBetween($table . '.created_at', [
            date('Y-m-d', strtotime(explode(' to ', $date)[0])) . ' 00:00:00',
            date('Y-m-d', strtotime(explode(' to ', $date)[1])) . ' 23:59:59',
        ]);
    }

    // Salezing status filter (still works if there are no matches)
    if ($request->salzing_status != null && $request->salzing_status !== '') {
        $orders->where('salezing_logs.response', $request->salzing_status);
    }

    // Common constraints
    $orders->where($table . '.delete_status', '0');

    // NOTE: This date floor hides anything before 2025-04-05.
    // Keep if intentional; otherwise comment out for manager_41 view.
    $orders->where($table . '.created_at', '>=', '2025-04-05'); // (intentional) // [REVIEW]

    // Warehouse scoping for non-root users
    // Old: always applied for non-root users.
    // New: skip this constraint for 41 Manager to avoid hiding their 41 orders. // [CHANGED]
    if (Auth::id() != 1 && !$is41Manager) { // [CHANGED]
        $orders->where('users.warehouse_id', Auth::user()->warehouse_id); // [UNCHANGED]
    }

    // Count after filters (debug)                                                   // [ADDED]
    \Log::debug('all_orders count after filters', [
        'table' => $table,
        'count' => (clone $orders)->count()
    ]); // [ADDED]

    $orders = $orders->paginate(15);

    return view('backend.sales.index', compact(
        'orders',
        'sort_search',
        'payment_status',
        'delivery_status',
        'date',
        'salzing_statuses',
        'is41Manager'
    ));
}

  public function back_all_orders(Request $request) {

    CoreComponentRepository::instantiateShopRepository();

    $date            = $request->date;
    $sort_search     = null;
    $delivery_status = null;
    $payment_status  = '';

    $admin_user_id = User::where('user_type', 'admin')->first()->id;

    // Get distinct Salezing Order Punch Status responses
    $salzing_statuses = DB::table('salezing_logs')->distinct()->pluck('response');

    // Update the orders table where the related code in order_approvals is approved
    DB::table('orders')
        ->join('order_approvals', 'orders.code', '=', 'order_approvals.code')
        ->where('order_approvals.status', 'Approved')
        ->update(['orders.delivery_status' => 'Approved']);

     // Call the updateOrderDetails function to handle updating order details
    //  $result = $this->updateOrderDetails();

    // Start building the query with the necessary joins
    $orders = Order::select(
      'orders.*', 
      'addresses.company_name', 
      'addresses.due_amount',
        'addresses.overdue_amount',
        'addresses.dueDrOrCr',
        'addresses.overdueDrOrCr',
      'salezing_logs.response', 
      'salezing_logs.status', 
      'users.warehouse_id', 
      'manager_users.name as manager_name', 
      'warehouses.name as warehouse_name' // Select warehouse name and alias it as warehouse_name
    )
    ->join('addresses', 'orders.address_id', '=', 'addresses.id')
    ->leftJoin('salezing_logs', DB::raw('CAST(orders.code AS CHAR CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci)'), '=', 'salezing_logs.code')
    ->join('users', 'orders.user_id', '=', 'users.id') // Join users table
    ->leftJoin('users as manager_users', 'users.manager_id', '=', 'manager_users.id') // Join for manager details
    ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id') // Join warehouses table to get warehouse name
    ->orderBy('orders.id', 'desc');

    // Apply filters based on the current route and user permissions
    if (Route::currentRouteName() == 'inhouse_orders.index' && Auth::user()->can('view_inhouse_orders')) {
        $orders = $orders->where('orders.seller_id', '=', $admin_user_id);
    } else if (Route::currentRouteName() == 'seller_orders.index' && Auth::user()->can('view_seller_orders')) {
        $orders = $orders->where('orders.seller_id', '!=', $admin_user_id);
    }

    // Apply search filters
    if ($request->search) {
        $sort_search = $request->search;
        $orders = $orders->where('orders.code', 'like', '%' . $sort_search . '%');
    }
    if ($request->payment_status != null) {
        $orders = $orders->where('orders.payment_status', $request->payment_status);
        $payment_status = $request->payment_status;
    }
    if ($request->delivery_status != null) {
        $orders = $orders->where('orders.delivery_status', $request->delivery_status);
        $delivery_status = $request->delivery_status;
    }
    if ($date != null) {
        $orders = $orders->whereBetween('orders.created_at', [
            date('Y-m-d', strtotime(explode(" to ", $date)[0])) . ' 00:00:00',
            date('Y-m-d', strtotime(explode(" to ", $date)[1])) . ' 23:59:59'
        ]);
    }

    // Apply Salzing Order Punch Status filter if selected
    if ($request->salzing_status != null) {
        $orders = $orders->where('salezing_logs.response', $request->salzing_status);
    }
    // $orders = $orders->where('orders.created_at', '>=', '2025-04-05');
    $orders = $orders->where('orders.delete_status', '0')->where('orders.created_at', '>=', '2025-04-05');
    if (Auth::id() != 1) {
      $orders = $orders->where('users.warehouse_id', Auth::user()->warehouse_id);
    }
    // Paginate and return the view with the orders and additional filters
    $orders = $orders->paginate(15);
    
    return view('backend.sales.index', compact('orders', 'sort_search', 'payment_status', 'delivery_status', 'date', 'salzing_statuses'));
  }

  public function all_unpushed_orders(Request $request) {

    CoreComponentRepository::instantiateShopRepository();

    $date            = $request->date;
    $sort_search     = null;
    $delivery_status = null;
    $payment_status  = '';

    $admin_user_id = User::where('user_type', 'admin')->first()->id;

    // Get distinct Salezing Order Punch Status responses
    $salzing_statuses = DB::table('salezing_logs')->distinct()->pluck('response');

    // // Update the orders table where the related code in order_approvals is approved
    // DB::table('orders')
    //     ->join('order_approvals', 'orders.code', '=', 'order_approvals.code')
    //     ->where('order_approvals.status', 'Approved')
    //     ->update(['orders.delivery_status' => 'Approved']);

    //  // Call the updateOrderDetails function to handle updating order details
    // //  $result = $this->updateOrderDetails();

    // // Start building the query with the necessary joins
    // $orders = Order::select(
    //   'orders.*', 
    //   'addresses.company_name', 
    //   'salezing_logs.response', 
    //   'salezing_logs.status', 
    //   'users.warehouse_id', 
    //   'manager_users.name as manager_name', 
    //   'warehouses.name as warehouse_name' // Select warehouse name and alias it as warehouse_name
    // )
    // ->join('addresses', 'orders.address_id', '=', 'addresses.id')
    // ->rightJoin('salezing_logs', DB::raw('CAST(orders.code AS CHAR CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci)'), '=', 'salezing_logs.code')
    // ->join('users', 'orders.user_id', '=', 'users.id') // Join users table
    // ->leftJoin('users as manager_users', 'users.manager_id', '=', 'manager_users.id') // Join for manager details
    // ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id') // Join warehouses table to get warehouse name
    // ->orderBy('orders.id', 'desc')->get()->toArray();

    $orders = Order::with('user:id,manager_id,warehouse_id,company_name','user.getManager:id,name','user.user_warehouse:id,name','order_approval:id,code,party_code,status')->where('payment_gateway_status','0')->orderBy('orders.id', 'desc');

    

    // Apply filters based on the current route and user permissions
    if (Route::currentRouteName() == 'inhouse_orders.index' && Auth::user()->can('view_inhouse_orders')) {
        $orders = $orders->where('orders.seller_id', '=', $admin_user_id);
    } else if (Route::currentRouteName() == 'seller_orders.index' && Auth::user()->can('view_seller_orders')) {
        $orders = $orders->where('orders.seller_id', '!=', $admin_user_id);
    }

    // Apply search filters
    if ($request->search) {
        $sort_search = $request->search;
        $orders = $orders->where('orders.code', 'like', '%' . $sort_search . '%');
    }
    if ($request->payment_status != null) {
        $orders = $orders->where('orders.payment_status', $request->payment_status);
        $payment_status = $request->payment_status;
    }
    if ($request->delivery_status != null) {
        $orders = $orders->where('orders.delivery_status', $request->delivery_status);
        $delivery_status = $request->delivery_status;
    }
    if ($date != null) {
        $orders = $orders->whereBetween('orders.created_at', [
            date('Y-m-d', strtotime(explode(" to ", $date)[0])) . ' 00:00:00',
            date('Y-m-d', strtotime(explode(" to ", $date)[1])) . ' 23:59:59'
        ]);
    }

    // Apply Salzing Order Punch Status filter if selected
    if ($request->salzing_status != null) {
        $orders = $orders->where('salezing_logs.response', $request->salzing_status);
    }

    // Paginate and return the view with the orders and additional filters
    $orders = $orders->paginate(15);
    
    return view('backend.sales.unpushed_orders', compact('orders', 'sort_search', 'payment_status', 'delivery_status', 'date', 'salzing_statuses'));
  }

  public function bulkOrderPushToSalezing(Request $request) {
    // print_r($request->selectedIds);
    foreach($request->selectedIds as $key=>$value){
      $this->pushOrderToSalzing($value);
    }
    return true;
  }

  public function pushOrderToSalzing($id)
  {
      // Fetch order data based on combined_order_id
      $orderData = DB::table('orders')->where('combined_order_id', $id)->first();

      if ($orderData) {
          // Check if a record exists in salezing_logs with the same order code
          $existingLog = DB::table('salezing_logs')->where('code', $orderData->code)->first();

          // If a record exists, delete it
          if ($existingLog) {
              DB::table('salezing_logs')->where('code', $orderData->code)->delete();
          }

          // Prepare the data to be pushed to the Salezing API
          $result = array();
          $result['code'] = $orderData->code;

          // // Push order data to Salezing API
          // $response = Http::withHeaders([
          //     'Content-Type' => 'application/json',
          // ])->post('https://mazingbusiness.com/api/v2/order-push', $result);

          // // Handle the response from the API
          // if ($response->successful()) {

          //   $orderData->payment_gateway_status = '2';
          //   $orderData->save();
          //   // Set a success message
          //   return true;
              
          // } else {
          //     // Set a failure message
          //     return false;
          // }
          $orderData->payment_gateway_status = '2';
          $orderData->save();
      }else {
          // If no order data found, set an error message
          session()->flash('error', 'Order not found.');
      }
  }

  public function updateOrderDetails($code = '20240926-10392357') {
    // Step 1: Fetch data from `order_approvals` using the provided code
    $orderApproval = DB::table('order_approvals')
        ->where('code', $code)
        ->first();

    if (!$orderApproval) {
        return ['error' => 'Order not found'];
    }

    // Step 2: Get the JSON details from the `order_approvals` table
    $details = json_decode($orderApproval->details, true);

    // Step 3: Get the order ID from the `orders` table using the `code`
    $order = DB::table('orders')
        ->where('code', $orderApproval->code)
        ->first();

    if (!$order) {
        return ['error' => 'Order ID not found'];
    }

    $orderId = $order->id;

    // Step 4: Loop through the JSON details and search for each `part_no` in the `products` table
    foreach ($details as $item) {
        $partNo = $item['part_no'];
        $quantity = $item['order_qty'];
        $billAmount = $item['bill_amount']; // Use the bill_amount as price directly

        // Step 5: Get the product ID from the `products` table using `part_no`
        $product = DB::table('products')
            ->where('part_no', $partNo)
            ->first();

        if ($product) {
            // Step 6: Update the `quantity` and `price` (using `bill_amount`) in the `order_details` table
            DB::table('order_details')
                ->where('order_id', $orderId)
                ->where('product_id', $product->id)
                ->update([
                    'approved_quantity' => (int)$quantity,
                    'sz_bill_amount' => $billAmount // Directly use the `bill_amount` as the price
                ]);
        }
    }

    return ['success' => 'Order details updated successfully'];
  }

  public function show($id)
  {
        $decryptedId = decrypt($id);

        // ЁЯСЙ If Manager-41 context, show from manager_41_orders
        if ($this->isActingAs41Manager()) {
            return $this->manager41OrderShow($decryptedId);
        }

        // ЁЯФ╣ Existing (non-41) flow
        $order = \App\Models\Order::with(['orderDetails.product.stocks', 'delivery_boy', 'pickup_point', 'carrier', 'user'])
                    ->findOrFail($decryptedId);

        $order_shipping_address = json_decode($order->shipping_address ?: '{}');
        $city = $order_shipping_address->city ?? ($order->user->city ?? null);

        $delivery_boys = \App\Models\User::where('user_type', 'delivery_boy')
                            ->when($city, fn($q) => $q->where('city', $city))
                            ->get();

        $order->viewed = 1;
        $order->save();

        return view('backend.sales.show', compact('order', 'delivery_boys'));
    }

    // Manager-41 version of Order Show
    public function manager41OrderShow($id)
    {
        // Load from manager_41_orders + typical relations (names same as normal where possible)
        $order = Manager41Order::with([
                    'orderDetails.product.stocks',   // details & product
                    'delivery_boy',                  // optional: keep if relation exists
                    'pickup_point',                  // optional
                    'carrier',                       // optional
                    'user',
                ])->findOrFail($id);

        $order_shipping_address = json_decode($order->shipping_address ?: '{}');
        $city = $order_shipping_address->city ?? ($order->user->city ?? null);

        $delivery_boys = \App\Models\User::where('user_type', 'delivery_boy')
                            ->when($city, fn($q) => $q->where('city', $city))
                            ->get();

        // Mark viewed if column exists on M41 table
        try {
            $order->viewed = 1;
            $order->save();
        } catch (\Throwable $e) {
            // ignore if 'viewed' column not present for M41
        }

        // Reuse same blade; it already uses optional() checks
        // (If you made a separate blade, swap the view name here)
        return view('backend.sales.show', compact('order', 'delivery_boys'));
    }




  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create() {
    //
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param \Illuminate\Http\Request $request
   * @return \Illuminate\Http\Response
   */

  public function manager41Store(Request $request) {

        $carts = Cart::where('user_id', Auth::user()->id)->where('is_manager_41', 1)->get();
        if ($carts->isEmpty()) {
          flash(translate('Your cart is empty'))->warning();
          return redirect()->route('home');
        }else{
          flash(translate('working!'))->warning();
        }

        $address         = Address::where('id', $carts[0]['address_id'])->first();
        $pincode         = Pincode::where('pincode', $address->postal_code)->first();
        $shippingAddress = [];
        $address_id = null;
        if ($address != null) {
          $address_id = $address->id;
          $shippingAddress['name']         = Auth::user()->name;
          $shippingAddress['company_name'] = Auth::user()->company_name;
          $shippingAddress['gstin']        = Auth::user()->gstin;
          $shippingAddress['email']        = Auth::user()->email;
          $shippingAddress['address']      = $address->address;
          $shippingAddress['country']      = $address->country->name;
          $shippingAddress['state']        = $address->state->name;
          //$shippingAddress['city']         = $address->city->name;
          $shippingAddress['city']         = $pincode->city;
          $shippingAddress['postal_code']  = $address->postal_code;
          $shippingAddress['phone']        = $address->phone; 
          if ($address->latitude || $address->longitude) {
            $shippingAddress['lat_lang'] = $address->latitude . ',' . $address->longitude;
          }
        }

        // тмЗя╕П Manager-41 Combined Order
        $combined_order                   = new \App\Models\Manager41CombinedOrder;
        $combined_order->user_id          = Auth::user()->id;
        $combined_order->shipping_address = json_encode($shippingAddress);
        $combined_order->save();

        $admin_items = array();

        $seller_products = array();
        foreach ($carts as $cartItem) {
            $product_ids = array();
            $product = Product::find($cartItem['product_id']);
            if (isset($seller_products[$product->user_id])) {
                $product_ids = $seller_products[$product->user_id];
            }
            array_push($product_ids, $cartItem);
            $seller_products[$product->user_id] = $product_ids;
        }
        
        $no_credit_item_flag = 0;
        $cash_and_carry_item_subtotal = 0;
        $rewardsValue = 0;
        foreach ($seller_products as $seller_product) {
          
          // тмЗя╕П Manager-41 Order
          $order = new Manager41Order;
          $order->combined_order_id = $combined_order->id;
          $order->user_id = Auth::user()->id;
          $order->shipping_address = $combined_order->shipping_address;

          $order->additional_info = $request->additional_info;
          $order->address_id = $address_id;

          $order->payment_type = $request->payment_option;
          $order->delivery_viewed = '0';
          $order->payment_status_viewed = '0';
          $order->payment_gateway_status = '0';
          $order->payable_amount = $request->payable_amount;
          $order->code = date('Ymd-His') . rand(10, 99);
          $order->date = strtotime('now');
          $order->payment_gateway_status = '0';
          $order->order_from = 'website';
          $order->save();

          $subtotal = 0;
          $tax = 0;
          $shipping = 0;
          $coupon_discount = 0;

          //Order Details Storing
          foreach ($seller_product as $cartItem) {
              $admin_items_line = array();

              $product = Product::find($cartItem['product_id']);
              if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606)){
                $subtotal += $cartItem['price'] * $cartItem['quantity'];
                $tax +=  cart_product_tax($cartItem, $product, false, Auth::user()->id) * $cartItem['quantity'];
              }else{
                $subtotal += cart_product_price($cartItem, $product, false, false, Auth::user()->id) * $cartItem['quantity'];
                $tax +=  cart_product_tax($cartItem, $product, false, Auth::user()->id) * $cartItem['quantity'];
              }
              
              $coupon_discount += $cartItem['discount'];

              $product_variation = $cartItem['variation'];
              if($product_variation != ""){
                  $product_stock = $product->stocks->where('product_id', $cartItem['product_id'])->first();
                  if ($product->digital != 1 && isset($product_stock->qty) && $cartItem['quantity'] > $product_stock->qty && false) {
                      flash(translate('The requested quantity is not available for ') . $product->getTranslation('name'))->warning();
                      $order->delete();
                      return redirect()->route('cart')->send();
                  } elseif ($product->digital != 1) {
                      // $product_stock->qty -= $cartItem['quantity'];
                      $product_stock->save();
                  }
              }
              
              // тмЗя╕П Manager-41 Order Detail
              $order_detail = new Manager41OrderDetail;
              $order_detail->order_id = $order->id;

              // extra mandatory fields in manager_41_order_details:
              $order_detail->order_type = ($product->added_by ?? 'admin') === 'seller' ? 'seller' : 'warehouse';
              $order_detail->seller_id  = $product->user_id ?? null;
              $order_detail->og_product_warehouse_id = 0;
              $order_detail->product_warehouse_id    = 0;

              $order_detail->product_id = $product->id;
              $order_detail->variation = $product_variation;

              if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606)){
                $order_detail->tax = cart_product_tax($cartItem, $product, false,Auth::user()->id) * $cartItem['quantity'];
                $order_detail->price = $cartItem['price'] * $cartItem['quantity'];
              }else{
                $order_detail->tax = cart_product_tax($cartItem, $product, false,Auth::user()->id) * $cartItem['quantity'];
                $order_detail->price = $cartItem['price'] * $cartItem['quantity'];
              }
              
              $order_detail->shipping_type = $cartItem['shipping_type'];
              $order_detail->shipping_cost = $cartItem['shipping_cost'];
              $order_detail->product_referral_code = $cartItem['product_referral_code'];
              $order_detail->cash_and_carry_item = (string) ($cartItem['cash_and_carry_item'] ?? '0');
              if($cartItem['cash_and_carry_item'] == 1){
                $no_credit_item_flag = 1;
                $cash_and_carry_item_subtotal += $cartItem['price'] * $cartItem['quantity'];
              }

              $admin_items_line['name'] = $product->name;
              $admin_items_line['new_part_no'] = $product->alias_name;
              $admin_items_line['part_no'] = $product->part_no;
              if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606)){
                $admin_items_line['net_price'] = $cartItem['price'];
              }else{
                $admin_items_line['net_price'] = cart_product_price($cartItem, $product, false, false, Auth::user()->id);
              }
              $admin_items_line['quantity'] = $cartItem['quantity'];
              $admin_items[] = $admin_items_line;

              $shipping += $order_detail->shipping_cost;

              $order_detail->quantity = $cartItem['quantity'];
              $order_detail->applied_offer_id = $cartItem['applied_offer_id'];
              $order_detail->complementary_item = (string) ($cartItem['complementary_item'] ?? '0');
              $order_detail->offer_rewards = $cartItem['offer_rewards'];
              if($rewardsValue == 0 AND $cartItem['offer_rewards'] != ""){
                $rewardsValue = $cartItem['offer_rewards'];
              }
              if (addon_is_activated('club_point')) {
                  $order_detail->earn_point = $product->earn_point;
              }

              // defaults required by manager_41_order_details
              $order_detail->payment_status = 'unpaid';
              $order_detail->delivery_status = 'pending';

              // compute final_amount (price + tax + shipping)
              $order_detail->final_amount = (int) round(($order_detail->price ?? 0) + ($order_detail->tax ?? 0) + ($order_detail->shipping_cost ?? 0));

              $order_detail->save();

              $product->num_of_sale += $cartItem['quantity'];
              $product->save();

              $order->seller_id = $product->user_id;
              $order->shipping_type = $cartItem['shipping_type'];
              
              if ($cartItem['shipping_type'] == 'pickup_point') {
                  $order->pickup_point_id = $cartItem['pickup_point'];
              }
              if ($cartItem['shipping_type'] == 'carrier') {
                  $order->carrier_id = $cartItem['carrier_id'];
              }

              if ($product->added_by == 'seller' && $product->user->seller != null) {
                  $seller = $product->user->seller;
                  $seller->num_of_sale += $cartItem['quantity'];
                  $seller->save();
              }

              if (addon_is_activated('affiliate_system')) {
                  if ($order_detail->product_referral_code) {
                      $referred_by_user = User::where('referral_code', $order_detail->product_referral_code)->first();

                      $affiliateController = new AffiliateController;
                      $affiliateController->processAffiliateStats($referred_by_user->id, 0, $order_detail->quantity, 0, 0);
                  }
              }
          }

          $order->grand_total = $subtotal  + $shipping;

          if ($seller_product[0]->coupon_code != null) {
              $order->coupon_discount = $coupon_discount;
              $order->grand_total -= $coupon_discount;

              $coupon_usage = new CouponUsage;
              $coupon_usage->user_id = Auth::user()->id;
              $coupon_usage->coupon_id = Coupon::where('code', $seller_product[0]->coupon_code)->first()->id;
              $coupon_usage->save();
          }

          $total = $combined_order->grand_total + $order->grand_total;
          $combined_order->grand_total = $total;

          $order->save();

        }

        $user_mobile = substr(Auth::user()->phone, -10);
        $party_code = Auth::user()->old_party_code;  

        $combined_order->save();

        // Rewards Offer submit in rewards table
        if($rewardsValue != 0){
          $user =  // Fetch the user and associated data
          $user = User::with('warehouse')->findOrFail(Auth::user()->id);

          // Create a new record in the RewardPointsOfUser model
          RewardPointsOfUser::create([
              'party_code' => $user->party_code,
              'rewards_from' => 'Offer', // Default value
              'warehouse_id' => $user->warehouse->id ?? null,
              'warehouse_name' => $user->warehouse->name ?? null,
              'rewards' => $rewardsValue,
              'dr_or_cr' => 'dr', // Default value
          ]);
        }
        
        // $order_detail                 = $order_detail->toArray();
        // $order_detail['product_name'] = Product::where('id', $order_detail['product_id'])->first('name');
        // тмЗя╕П Manager-41 Order fetch for PDF
        $order                        = Manager41Order::findOrFail($order_detail['order_id']);
        $font_family                  = "'Roboto','sans-serif'";
        $direction                    = 'ltr';
        $text_align                   = 'left';
        $not_text_align               = 'right';
        $config                       = [];
        $pdf                          = PDF::loadView('backend.invoices.proformainvoice', [
          'order'          => $order,
          'font_family'    => $font_family,
          'direction'      => $direction,
          'text_align'     => $text_align,
          'not_text_align' => $not_text_align,
        ], [], $config);

        // // Prepare the data for the API request
        //   $data = [
        //     "order_id" => $order->code,
        //     "client" => $user_mobile,
        //     "partycode" => $party_code,
        //     "discount" => "",
        //     "items" => $admin_items
        // ];
      
        // // Send the HTTP request to the API endpoint
        // try {
         
        //   $response = Http::post('https://admin.mazingbusiness.com/api/order.php', $data);
        //   // Handle the response as needed
        //   $responseData = $response->json(); // Assuming the response is JSON
        //   // dd($responseData); // Print the response data for debugging
        // } catch (\Exception $e) {
        //   // Handle exceptions if the request fails
        //   //dd($e->getMessage());
        // }
           
        $data = array();    
        //------------ Calculation for pass the order to salzing or not end
        try {
          if(Auth::user()->id != '24185'){
            // // Push order data to Salezing
            // $result=array();
            // $result['code']= $order->code;
            // $response = Http::withHeaders([
            //     'Content-Type' => 'application/json',
            // ])->post('https://mazingbusiness.com/api/v2/order-push', $result);
            // \Log::info('Salzing Order Push From Website Status: '  . json_encode($response->json(), JSON_PRETTY_PRINT));
          }else{
            // //------------ Calculation for pass the order to salzing or not start 
            // $calculationResponse = $this->statementCalculationService->calculateForOneCompany(Auth::user()->id, 'live');
            // // Decode the JSON response to an array
            // $calculationResponse = $calculationResponse->getData(true);

            // $overdueAmount = $calculationResponse['overdueAmount'];
            // $dueAmount = $calculationResponse['dueAmount'];

            // $credit_limit = Auth::user()->credit_limit;
            // $current_limit = $dueAmount - $overdueAmount;
            // $currentAvailableCreditLimit = $credit_limit - $current_limit;
            // $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
            // //-------------------------- This is for case 2 ------------------------------
            // if($current_limit == 0){        
            //     if($total > $currentAvailableCreditLimit){
            //         $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
            //     }else{
            //         $exceededAmount = $overdueAmount;
            //     }

            // }else{
            //     if($total > $currentAvailableCreditLimit)
            //     {
            //         $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
            //     }else{
            //         $exceededAmount = $overdueAmount;
            //     }
            // }
            // //----------------------------------------------------------------------------
            // $payableAmount = $exceededAmount + $cash_and_carry_item_subtotal;
          }

        } catch (\Exception $e) {
          // Handle exceptions if the request fails
          //dd($e->getMessage());
        }
        // Send email
        // Mail::send('emails.order_placed', $order_detail, function ($message) use ($pdf, $order) {
          // $message->to('kburhanuddin12@gmail.com', 'Mazing Business')->subject('New Order has been placed on Mazing Business')->attachData($pdf->output(), 'order-' . $order->code . '.pdf');
          // $message->from(env('MAIL_FROM_ADDRESS'), 'Mazing Business');
        // });
        $request->session()->put('combined_order_id', $combined_order->id);
    }
  public function store(Request $request) {

   
    // ЁЯФР If staff is a Manager 41, route to manager41Store()
    $staffId = session('staff_id'); // yahi original staff id hai
    
    if ($staffId) {
        $isManager41 = User::where('id', $staffId)
            ->where('user_title', 'manager_41')
            ->exists();
            
        if ($isManager41) {
            return $this->manager41Store($request); // <-- aapka special flow
        }
    }
    

    $carts = Cart::where('user_id', Auth::user()->id)->where('is_manager_41','0')->get();
    if ($carts->isEmpty()) {
      flash(translate('Your cart is empty'))->warning();
      return redirect()->route('home');
    }else{
      flash(translate('working!'))->warning();
    }

    $address         = Address::where('id', $carts[0]['address_id'])->first();
    $pincode         = Pincode::where('pincode', $address->postal_code)->first();
    $shippingAddress = [];
    $address_id = null;
    if ($address != null) {
      $address_id = $address->id;
      $shippingAddress['name']         = Auth::user()->name;
      $shippingAddress['company_name'] = Auth::user()->company_name;
      $shippingAddress['gstin']        = Auth::user()->gstin;
      $shippingAddress['email']        = Auth::user()->email;
      $shippingAddress['address']      = $address->address;
      $shippingAddress['country']      = $address->country->name;
      $shippingAddress['state']        = $address->state->name;
      //$shippingAddress['city']         = $address->city->name;
      $shippingAddress['city']         = $pincode->city;
      $shippingAddress['postal_code']  = $address->postal_code;
      $shippingAddress['phone']        = $address->phone; 
      if ($address->latitude || $address->longitude) {
        $shippingAddress['lat_lang'] = $address->latitude . ',' . $address->longitude;
      }
    }

    $combined_order                   = new CombinedOrder;
    $combined_order->user_id          = Auth::user()->id;
    $combined_order->shipping_address = json_encode($shippingAddress);
    $combined_order->save();

    $admin_items = array();

    $seller_products = array();
    foreach ($carts as $cartItem) {
        $product_ids = array();
        $product = Product::find($cartItem['product_id']);
        if (isset($seller_products[$product->user_id])) {
            $product_ids = $seller_products[$product->user_id];
        }
        array_push($product_ids, $cartItem);
        $seller_products[$product->user_id] = $product_ids;
    }
    
    $no_credit_item_flag = 0;
    $cash_and_carry_item_subtotal = 0;
    $rewardsValue = 0;
    foreach ($seller_products as $seller_product) {
      
      $order = new Order;
      $order->combined_order_id = $combined_order->id;
      $order->user_id = Auth::user()->id;
      $order->shipping_address = $combined_order->shipping_address;

      $order->additional_info = $request->additional_info;
      $order->address_id = $address_id;
      // $order->shipping_type = $carts[0]['shipping_type'];
      // if ($carts[0]['shipping_type'] == 'pickup_point') {
      //     $order->pickup_point_id = $cartItem['pickup_point'];
      // }
      // if ($carts[0]['shipping_type'] == 'carrier') {
      //     $order->carrier_id = $cartItem['carrier_id'];
      // }

      $order->payment_type = $request->payment_option;
      $order->delivery_viewed = '0';
      $order->payment_status_viewed = '0';
      $order->payment_gateway_status = '0';
      $order->payable_amount = $request->payable_amount;
      $order->code = date('Ymd-His') . rand(10, 99);
      $order->date = strtotime('now');
      $order->payment_gateway_status = '0';
      $order->order_from = 'website';
      $order->conveince_fee_percentage = $request->conveince_fee_percentage;
      $order->save();

      $subtotal = 0;
      $tax = 0;
      $shipping = 0;
      $coupon_discount = 0;

      //Order Details Storing
      foreach ($seller_product as $cartItem) {
          $admin_items_line = array();

          $product = Product::find($cartItem['product_id']);
          if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606)){
            $subtotal += $cartItem['price'] * $cartItem['quantity'];
            // $tax +=  $cartItem['price'] * $cartItem['quantity'];
            $tax +=  cart_product_tax($cartItem, $product, false, Auth::user()->id) * $cartItem['quantity'];
          }else{
            $subtotal += cart_product_price($cartItem, $product, false, false, Auth::user()->id) * $cartItem['quantity'];
            $tax +=  cart_product_tax($cartItem, $product, false, Auth::user()->id) * $cartItem['quantity'];
          }
          
          $coupon_discount += $cartItem['discount'];

          $product_variation = $cartItem['variation'];
          if($product_variation != ""){
              // $product_stock = $product->stocks->where('variant', $product_variation)->first();
              $product_stock = $product->stocks->where('product_id', $cartItem['product_id'])->first();
              // echo "<pre>...";print_r($cartItem);print_r($product_stock);die;
            if ($product->digital != 1 && isset($product_stock->qty) && $cartItem['quantity'] > $product_stock->qty && false) {
                flash(translate('The requested quantity is not available for ') . $product->getTranslation('name'))->warning();
                $order->delete();
                return redirect()->route('cart')->send();
            } elseif ($product->digital != 1) {
                // echo '...'.$cartItem['product_id'];
                // $product_stock->qty -= $cartItem['quantity'];
                $product_stock->save();
            }
          }
          

          $order_detail = new OrderDetail;
          $order_detail->order_id = $order->id;
          $order_detail->seller_id = $product->user_id;
          $order_detail->product_id = $product->id;
          $order_detail->variation = $product_variation;
          $order_detail->conveince_fee_percentage = $request->conveince_fee_percentage;
          if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606)){
            // $order_detail->tax = $cartItem['price'] * $cartItem['quantity'];
            $order_detail->tax = cart_product_tax($cartItem, $product, false,Auth::user()->id) * $cartItem['quantity'];
            $order_detail->price = $cartItem['price'] * $cartItem['quantity'];
          }else{
            $order_detail->tax = cart_product_tax($cartItem, $product, false,Auth::user()->id) * $cartItem['quantity'];
            // $order_detail->price = cart_product_price($cartItem, $product, false, false,Auth::user()->id) * $cartItem['quantity'];
            $order_detail->price = $cartItem['price'] * $cartItem['quantity'];
          }
          
          
          $order_detail->shipping_type = $cartItem['shipping_type'];
          $order_detail->product_referral_code = $cartItem['product_referral_code'];
          $order_detail->shipping_cost = $cartItem['shipping_cost'];
          $order_detail->cash_and_carry_item = $cartItem['cash_and_carry_item'];
          if($cartItem['cash_and_carry_item'] == 1){
            $no_credit_item_flag = 1;
            $cash_and_carry_item_subtotal += $cartItem['price'] * $cartItem['quantity'];
          }
          $admin_items_line['name'] = $product->name;
          $admin_items_line['new_part_no'] = $product->alias_name;
          $admin_items_line['part_no'] = $product->part_no;
          if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606)){
            $admin_items_line['net_price'] = $cartItem['price'];
          }else{
            $admin_items_line['net_price'] = cart_product_price($cartItem, $product, false, false, Auth::user()->id);
          }
          
          $admin_items_line['quantity'] = $cartItem['quantity'];

          $admin_items[] = $admin_items_line;

          $shipping += $order_detail->shipping_cost;
          //End of storing shipping cost

          $order_detail->quantity = $cartItem['quantity'];
          $order_detail->applied_offer_id = $cartItem['applied_offer_id'];
          $order_detail->complementary_item = $cartItem['complementary_item'];
          $order_detail->offer_rewards = $cartItem['offer_rewards'];
          if($rewardsValue == 0 AND $cartItem['offer_rewards'] != ""){
            $rewardsValue = $cartItem['offer_rewards'];
          }
          if (addon_is_activated('club_point')) {
              $order_detail->earn_point = $product->earn_point;
          }
          $order_detail->save();
          //echo "<pre>";print_r($order_detail);die;

          $product->num_of_sale += $cartItem['quantity'];
          $product->save();

          $order->seller_id = $product->user_id;
          $order->shipping_type = $cartItem['shipping_type'];
          
          if ($cartItem['shipping_type'] == 'pickup_point') {
              $order->pickup_point_id = $cartItem['pickup_point'];
          }
          if ($cartItem['shipping_type'] == 'carrier') {
              $order->carrier_id = $cartItem['carrier_id'];
          }

          if ($product->added_by == 'seller' && $product->user->seller != null) {
              $seller = $product->user->seller;
              $seller->num_of_sale += $cartItem['quantity'];
              $seller->save();
          }

          if (addon_is_activated('affiliate_system')) {
              if ($order_detail->product_referral_code) {
                  $referred_by_user = User::where('referral_code', $order_detail->product_referral_code)->first();

                  $affiliateController = new AffiliateController;
                  $affiliateController->processAffiliateStats($referred_by_user->id, 0, $order_detail->quantity, 0, 0);
              }
          }
      }

      $order->grand_total = $subtotal  + $shipping;

      if ($seller_product[0]->coupon_code != null) {
          $order->coupon_discount = $coupon_discount;
          $order->grand_total -= $coupon_discount;

          $coupon_usage = new CouponUsage;
          $coupon_usage->user_id = Auth::user()->id;
          $coupon_usage->coupon_id = Coupon::where('code', $seller_product[0]->coupon_code)->first()->id;
          $coupon_usage->save();
      }

      $total = $combined_order->grand_total += $order->grand_total;

      $order->save();

    }

    $user_mobile = substr(Auth::user()->phone, -10);
    $party_code = Auth::user()->old_party_code;  

    $combined_order->save();

    // Rewards Offer submit in rewards table
    if($rewardsValue != 0){
      $user =  // Fetch the user and associated data
      $user = User::with('warehouse')->findOrFail(Auth::user()->id);

      // Create a new record in the RewardPointsOfUser model
      RewardPointsOfUser::create([
          'party_code' => $user->party_code,
          'rewards_from' => 'Offer', // Default value
          'warehouse_id' => $user->warehouse->id ?? null,
          'warehouse_name' => $user->warehouse->name ?? null,
          'rewards' => $rewardsValue,
          'dr_or_cr' => 'dr', // Default value
      ]);
    }
    
    // $order_detail                 = $order_detail->toArray();
    // $order_detail['product_name'] = Product::where('id', $order_detail['product_id'])->first('name');
    $order                        = Order::findOrFail($order_detail['order_id']);
    $font_family                  = "'Roboto','sans-serif'";
    $direction                    = 'ltr';
    $text_align                   = 'left';
    $not_text_align               = 'right';
    $config                       = [];
    $pdf                          = PDF::loadView('backend.invoices.proformainvoice', [
      'order'          => $order,
      'font_family'    => $font_family,
      'direction'      => $direction,
      'text_align'     => $text_align,
      'not_text_align' => $not_text_align,
    ], [], $config);

    // // Prepare the data for the API request
    //   $data = [
    //     "order_id" => $order->code,
    //     "client" => $user_mobile,
    //     "partycode" => $party_code,
    //     "discount" => "",
    //     "items" => $admin_items
    // ];
  
    // // Send the HTTP request to the API endpoint
    // try {
     
    //   $response = Http::post('https://admin.mazingbusiness.com/api/order.php', $data);
    //   // Handle the response as needed
    //   $responseData = $response->json(); // Assuming the response is JSON
    //   // dd($responseData); // Print the response data for debugging
    // } catch (\Exception $e) {
    //   // Handle exceptions if the request fails
    //   //dd($e->getMessage());
    // }
       
    $data = array();    
    //------------ Calculation for pass the order to salzing or not end
    try {
      if(Auth::user()->id != '24185'){
        // // Push order data to Salezing
        // $result=array();
        // $result['code']= $order->code;
        // $response = Http::withHeaders([
        //     'Content-Type' => 'application/json',
        // ])->post('https://mazingbusiness.com/api/v2/order-push', $result);
        // \Log::info('Salzing Order Push From Website Status: '  . json_encode($response->json(), JSON_PRETTY_PRINT));
      }else{
        // //------------ Calculation for pass the order to salzing or not start 
        // $calculationResponse = $this->statementCalculationService->calculateForOneCompany(Auth::user()->id, 'live');
        // // Decode the JSON response to an array
        // $calculationResponse = $calculationResponse->getData(true);

        // $overdueAmount = $calculationResponse['overdueAmount'];
        // $dueAmount = $calculationResponse['dueAmount'];

        // $credit_limit = Auth::user()->credit_limit;
        // $current_limit = $dueAmount - $overdueAmount;
        // $currentAvailableCreditLimit = $credit_limit - $current_limit;
        // $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
        // //-------------------------- This is for case 2 ------------------------------
        // if($current_limit == 0){        
        //     if($total > $currentAvailableCreditLimit){
        //         $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
        //     }else{
        //         $exceededAmount = $overdueAmount;
        //     }

        // }else{
        //     if($total > $currentAvailableCreditLimit)
        //     {
        //         $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
        //     }else{
        //         $exceededAmount = $overdueAmount;
        //     }
        // }
        // //----------------------------------------------------------------------------
        // $payableAmount = $exceededAmount + $cash_and_carry_item_subtotal;
      }

    } catch (\Exception $e) {
      // Handle exceptions if the request fails
      //dd($e->getMessage());
    }
    // Send email
    // Mail::send('emails.order_placed', $order_detail, function ($message) use ($pdf, $order) {
      // $message->to('kburhanuddin12@gmail.com', 'Mazing Business')->subject('New Order has been placed on Mazing Business')->attachData($pdf->output(), 'order-' . $order->code . '.pdf');
      // $message->from(env('MAIL_FROM_ADDRESS'), 'Mazing Business');
    // });
    $request->session()->put('combined_order_id', $combined_order->id);
  }

  

  /**
   * Display the specified resource.
   *
   * @param int $id
   * @return \Illuminate\Http\Response
   */

  /**
   * Show the form for editing the specified resource.
   *
   * @param int $id
   * @return \Illuminate\Http\Response
   */
  public function edit($id) {
    //
  }

  /**
   * Update the specified resource in storage.
   *
   * @param \Illuminate\Http\Request $request
   * @param int $id
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, $id) {
    //
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param int $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id) {
    $order = Order::findOrFail($id);
    if ($order != null) {
      foreach ($order->orderDetails as $key => $orderDetail) {
        // try {
        //   $product_stock = ProductStock::where('product_id', $orderDetail->product_id)->where('variant', $orderDetail->variation)->first();
        //   if ($product_stock != null) {
        //     $product_stock->qty += $orderDetail->quantity;
        //     $product_stock->save();
        //   }
        // } catch (\Exception $e) {
        // }
        $orderDetail->delete_status = 1;
        $orderDetail->save();
        // $orderDetail->delete();
      }
      $order->delete_status = '1';
      $order->save();
      // $order->delete();
      flash(translate('Order has been deleted successfully'))->success();
    } else {
      flash(translate('Something went wrong'))->error();
    }
    return back();
  }

  public function bulk_order_delete(Request $request) {
    if ($request->id) {
      foreach ($request->id as $order_id) {
        $this->destroy($order_id);
      }
    }

    return 1;
  }

  public function order_details(Request $request) {
    $order = Order::findOrFail($request->order_id);
    $order->save();
    return view('seller.order_details_seller', compact('order'));
  }

  public function update_delivery_status(Request $request) {
    $order = Order::findOrFail($request->order_id);
    if ($request->status == 'on_the_way' && !$order->tracking_code) {
      return 0;
    }
    $order->delivery_viewed = '0';
    $order->delivery_status = $request->status;
    $order->save();

    if ($request->status == 'cancelled' && $order->payment_type == 'wallet') {
      $user = User::where('id', $order->user_id)->first();
      $user->balance += $order->grand_total;
      $user->save();
    }

    if (Auth::user()->user_type == 'seller') {
      foreach ($order->orderDetails->where('seller_id', Auth::user()->id) as $key => $orderDetail) {
        $orderDetail->delivery_status = $request->status;
        $orderDetail->save();
        if ($request->status == 'cancelled') {
          $variant = $orderDetail->variation;
          if ($orderDetail->variation == null) {
            $variant = '';
          }
          $product_stock = ProductWarehouse::where('product_id', $orderDetail->product_id)
            ->where('variant', $variant)
            ->first();
          if ($product_stock != null) {
            $product_stock->qty += $orderDetail->quantity;
            $product_stock->save();
          }
        }
      }
    } else {
      foreach ($order->orderDetails as $key => $orderDetail) {
        $orderDetail->delivery_status = $request->status;
        $orderDetail->save();
        if ($request->status == 'cancelled') {
          $variant = $orderDetail->variation;
          if ($orderDetail->variation == null) {
            $variant = '';
          }
          $product_stock = ProductWarehouse::where('product_id', $orderDetail->product_id)
            ->where('variant', $variant)
            ->first();
          if ($product_stock != null) {
            $product_stock->qty += $orderDetail->quantity;
            $product_stock->save();
          }
        }

        if (addon_is_activated('affiliate_system')) {
          if (($request->status == 'delivered' || $request->status == 'cancelled') &&
            $orderDetail->product_referral_code
          ) {

            $no_of_delivered = 0;
            $no_of_canceled  = 0;

            if ($request->status == 'delivered') {
              $no_of_delivered = $orderDetail->quantity;
            }
            if ($request->status == 'cancelled') {
              $no_of_canceled = $orderDetail->quantity;
            }

            $referred_by_user = User::where('referral_code', $orderDetail->product_referral_code)->first();

            $affiliateController = new AffiliateController;
            $affiliateController->processAffiliateStats($referred_by_user->id, 0, 0, $no_of_delivered, $no_of_canceled);
          }
        }
      }
    }
    if (addon_is_activated('otp_system')) {
      if ($order->delivery_status == 'confirmed') {
        WhatsAppUtility::orderConfirmed($order->user, $order);
      } elseif ($order->delivery_status == 'on_the_way') {
        WhatsAppUtility::orderShipped($order->user, $order);
      } elseif ($order->delivery_status == 'cancelled') {
        WhatsAppUtility::orderCancelled($order->user, $order);
      }
    }

    //sends Notifications to user
    NotificationUtility::sendNotification($order, $request->status);
    if (get_setting('google_firebase') == 1 && $order->user->device_token != null) {
      $request->device_token = $order->user->device_token;
      $request->title        = "Order updated !";
      $status                = str_replace("_", "", $order->delivery_status);
      $request->text         = " Your order {$order->code} has been {$status}";

      $request->type    = "order";
      $request->id      = $order->id;
      $request->user_id = $order->user->id;

      NotificationUtility::sendFirebaseNotification($request);
    }

    if (addon_is_activated('delivery_boy')) {
      if (Auth::user()->user_type == 'delivery_boy') {
        $deliveryBoyController = new DeliveryBoyController;
        $deliveryBoyController->store_delivery_history($order);
      }
    }

    return 1;
  }

  public function update_tracking_code(Request $request) {
    $order                = Order::findOrFail($request->order_id);
    $order->tracking_code = $request->tracking_code;
    $order->save();

    return 1;
  }

  public function update_payment_status(Request $request) {
    $order                        = Order::findOrFail($request->order_id);
    $order->payment_status_viewed = '0';
    $order->save();

    if (Auth::user()->user_type == 'seller') {
      foreach ($order->orderDetails->where('seller_id', Auth::user()->id) as $key => $orderDetail) {
        $orderDetail->payment_status = $request->status;
        $orderDetail->save();
      }
    } else {
      foreach ($order->orderDetails as $key => $orderDetail) {
        $orderDetail->payment_status = $request->status;
        $orderDetail->save();
      }
    }

    $order->payment_status = $status = $request->status;
    if ($order->payment_status == 'paid') {
      foreach ($order->orderDetails as $key => $orderDetail) {
        if ($orderDetail->payment_status != 'paid') {
          $status = 'unpaid';
        }
      }
      $latest_confirmed_order = Order::where('code', 'like', 'MZ%')->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
      if ($latest_confirmed_order) {
        $code                   = explode('/', $latest_confirmed_order->code);
        $latest_confirmed_order = 'MZ/' . str_pad((((int) $code[1]) + 1), 5, '0', STR_PAD_LEFT) . '/' . (date('m') > 3 ? date('y') . '-' . ((int) date('y') + 1) : (int) date('y') - 1 . '-' . date('y'));
      } else {
        $latest_confirmed_order = 'MZ/00001/' . (date('m') > 3 ? date('y') . '-' . ((int) date('y') + 1) : (int) date('y') - 1 . '-' . date('y'));
      }
      $order->code = $latest_confirmed_order;
    }
    if ($order->payment_status == 'request-details') {
      $status = 'request-details';
    }
    $order->payment_status = $status;
    $order->save();

    if (
      $order->payment_status == 'paid' &&
      $order->commission_calculated == 0
    ) {
      calculateCommissionAffilationClubPoint($order);
    }

    //sends Notifications to user
    NotificationUtility::sendNotification($order, $request->status);
    if (get_setting('google_firebase') == 1 && $order->user->device_token != null && $status == 'paid') {
      $request->device_token = $order->user->device_token;
      $request->title        = "Order updated !";
      $status                = str_replace("_", "", $order->payment_status);
      $request->text         = " Your order {$order->code} has been {$status}";

      $request->type    = "order";
      $request->id      = $order->id;
      $request->user_id = $order->user->id;

      NotificationUtility::sendFirebaseNotification($request);
    }

    if (addon_is_activated('otp_system') && $status == 'paid') {
      WhatsAppUtility::paymentConfirmation($order->user, $order);
    }
    if (addon_is_activated('otp_system') && $order->payment_status == 'request-details') {
      WhatsAppUtility::requestPaymentConfirmation($order->user, $order);
    }
    return 1;
  }

  public function assign_delivery_boy(Request $request) {
    if (addon_is_activated('delivery_boy')) {

      $order                        = Order::findOrFail($request->order_id);
      $order->assign_delivery_boy   = $request->delivery_boy;
      $order->delivery_history_date = date("Y-m-d H:i:s");
      $order->save();

      $delivery_history = \App\Models\DeliveryHistory::where('order_id', $order->id)
        ->where('delivery_status', $order->delivery_status)
        ->first();

      if (empty($delivery_history)) {
        $delivery_history = new \App\Models\DeliveryHistory;

        $delivery_history->order_id        = $order->id;
        $delivery_history->delivery_status = $order->delivery_status;
        $delivery_history->payment_type    = $order->payment_type;
      }
      $delivery_history->delivery_boy_id = $request->delivery_boy;

      $delivery_history->save();

      if (env('MAIL_USERNAME') != null && get_setting('delivery_boy_mail_notification') == '1') {
        $array['view']    = 'emails.invoice';
        $array['subject'] = translate('You are assigned to delivery an order. Order code') . ' - ' . $order->code;
        $array['from']    = env('MAIL_FROM_ADDRESS');
        $array['order']   = $order;

        try {
          Mail::to($order->delivery_boy->email)->queue(new InvoiceEmailManager($array));
        } catch (\Exception $e) {
        }
      }

      if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'assign_delivery_boy')->first()->status == 1) {
        try {
          SmsUtility::assign_delivery_boy($order->delivery_boy->phone, $order->code);
        } catch (\Exception $e) {
        }
      }
    }

    return 1;
  }

  public function updateStatus(Request $request)
  {
      // Retrieve input data from the request
      $party_code = $request->input('party_code');
      $code = $request->input('code');
      $status = $request->input('status');
      $details = $request->input('details');
      $timestamp = $request->input('timestamp');

      // Log the input data for debugging
      Log::info('Party Code: ' . $party_code);
      Log::info('Code: ' . $code);
      Log::info('Status: ' . $status);
      Log::info('Details: ' . json_encode($details));
      Log::info('Timestamp: ' . $timestamp);

      // Validate the input data
      $validator = \Validator::make($request->all(), [
          'party_code' => 'required|string',
          'code' => 'required|string',
          'status' => 'required|string',
          'details' => 'required|array',
          'timestamp' => 'required|date_format:Y-m-d H:i:s',
      ]);

      if ($validator->fails()) {
          return response()->json(['status' => 'Error', 'message' => $validator->errors()], 400);
      }

      // Convert details array to JSON string
      $detailsJson = json_encode($details);

      // Insert data into the database
      try {
          DB::table('order_approvals')->insert([
              'party_code' => $party_code,
              'code' => $code,
              'status' => $status,
              'details' => $detailsJson,
              'timestamp' => $timestamp,
          ]);

          return response()->json([
              'status' => 'Success',
              'message' => 'Order status updated successfully',
              'data' => [
                  'party_code' => $party_code,
                  'code' => $code,
                  'status' => $status,
                  'timestamp' => $timestamp,
              ],
          ]);

      } catch (\Exception $e) {
          return response()->json(['status' => 'Error', 'message' => 'Database error: ' . $e->getMessage()], 500);
      }
  }

  public function all_international_orders(Request $request) {

    $date            = $request->date;
    $sort_search     = null;
    $delivery_status = null;
    $payment_status  = '';

    $admin_user_id = User::where('user_type', 'admin')->first()->id;

    // Start building the query with the necessary joins
    $orders = OwnBrandOrder::select(
      'own_brand_orders.*',
      'users.company_name as customer_company_name', 
      'users.name as customer_name'
    )
    ->join('users', 'own_brand_orders.customer_id', '=', 'users.id') // Join users table
    ->where('own_brand_orders.delivery_status','pending')
    ->with('orderDetails')
    ->orderBy('own_brand_orders.id', 'DESC');


    // Apply search filters
    if ($request->search) {
        $sort_search = $request->search;
        $orders = $orders->where('orders.code', 'like', '%' . $sort_search . '%');
    }
    if ($request->payment_status != null) {
        $orders = $orders->where('orders.payment_status', $request->payment_status);
        $payment_status = $request->payment_status;
    }
    if ($request->delivery_status != null) {
        $orders = $orders->where('orders.delivery_status', $request->delivery_status);
        $delivery_status = $request->delivery_status;
    }
    if ($date != null) {
        $orders = $orders->whereBetween('orders.created_at', [
            date('Y-m-d', strtotime(explode(" to ", $date)[0])) . ' 00:00:00',
            date('Y-m-d', strtotime(explode(" to ", $date)[1])) . ' 23:59:59'
        ]);
    }

    // Paginate and return the view with the orders and additional filters
    $orders = $orders->paginate(15);
    // echo "<pre>";
    // print_r($orders->toArray());
    // die();
    
    return view('backend.sales.all_international_orders', compact('orders', 'sort_search', 'payment_status', 'delivery_status', 'date'));
  }

  public function all_international_in_review_orders(Request $request) {

    $date            = $request->date;
    $sort_search     = null;
    $delivery_status = null;
    $payment_status  = '';

    $admin_user_id = User::where('user_type', 'admin')->first()->id;

    // Start building the query with the necessary joins
    $orders = OwnBrandOrder::select(
      'own_brand_orders.*',
      'users.company_name as customer_company_name', 
      'users.name as customer_name'
    )
    ->join('users', 'own_brand_orders.customer_id', '=', 'users.id') // Join users table
    ->where('own_brand_orders.delivery_status','in_review')
    ->with('orderDetails')
    ->orderBy('own_brand_orders.id', 'DESC');


    // Apply search filters
    if ($request->search) {
        $sort_search = $request->search;
        $orders = $orders->where('orders.code', 'like', '%' . $sort_search . '%');
    }
    if ($request->payment_status != null) {
        $orders = $orders->where('orders.payment_status', $request->payment_status);
        $payment_status = $request->payment_status;
    }
    if ($request->delivery_status != null) {
        $orders = $orders->where('orders.delivery_status', $request->delivery_status);
        $delivery_status = $request->delivery_status;
    }
    if ($date != null) {
        $orders = $orders->whereBetween('orders.created_at', [
            date('Y-m-d', strtotime(explode(" to ", $date)[0])) . ' 00:00:00',
            date('Y-m-d', strtotime(explode(" to ", $date)[1])) . ' 23:59:59'
        ]);
    }

    // Paginate and return the view with the orders and additional filters
    $orders = $orders->paginate(15);
    
    return view('backend.sales.all_international_in_review_orders', compact('orders', 'sort_search', 'payment_status', 'delivery_status', 'date'));
  }

  public function all_international_approved_orders(Request $request) {

    $date            = $request->date;
    $sort_search     = null;
    $delivery_status = null;
    $payment_status  = '';

    $admin_user_id = User::where('user_type', 'admin')->first()->id;

    // Start building the query with the necessary joins
    $orders = OwnBrandOrder::select(
      'own_brand_orders.*',
      'users.company_name as customer_company_name', 
      'users.name as customer_name'
    )
    ->join('users', 'own_brand_orders.customer_id', '=', 'users.id') // Join users table
    ->where('own_brand_orders.delivery_status','confirm')
    ->with('orderDetails')
    ->orderBy('own_brand_orders.id', 'DESC');


    // Apply search filters
    if ($request->search) {
        $sort_search = $request->search;
        $orders = $orders->where('orders.code', 'like', '%' . $sort_search . '%');
    }
    if ($request->payment_status != null) {
        $orders = $orders->where('orders.payment_status', $request->payment_status);
        $payment_status = $request->payment_status;
    }
    if ($request->delivery_status != null) {
        $orders = $orders->where('orders.delivery_status', $request->delivery_status);
        $delivery_status = $request->delivery_status;
    }
    if ($date != null) {
        $orders = $orders->whereBetween('orders.created_at', [
            date('Y-m-d', strtotime(explode(" to ", $date)[0])) . ' 00:00:00',
            date('Y-m-d', strtotime(explode(" to ", $date)[1])) . ' 23:59:59'
        ]);
    }

    // Paginate and return the view with the orders and additional filters
    $orders = $orders->paginate(15);
    
    return view('backend.sales.all_international_approved_orders', compact('orders', 'sort_search', 'payment_status', 'delivery_status', 'date'));
  }

  public function international_pending_order_details($id) {

    $order = OwnBrandOrder::with(['orderDetails' => function($query) {
        $query->whereNull('deleted_at');
    },'product'])->findOrFail(decrypt($id));

    $deleteOrderProduct = OwnBrandOrder::with(['orderDetails' => function($query) {
        $query->onlyTrashed(); // Use onlyTrashed() to get soft-deleted order details
    },'product'])->findOrFail(decrypt($id));
    return view('backend.sales.international_pending_order_details', compact('order','deleteOrderProduct'));
  }

  public function impexOrderPdf($order_code)
  {
    
      $invoiceController = new InvoiceController();
      $file_url = $invoiceController->invoice_combined_order($order_code);

      // Fetch file contents from URL
      $fileContents = file_get_contents($file_url);

      if (!$fileContents) {
          abort(404, 'File not found');
      }

      // Extract filename from URL
      $fileName = basename($file_url);

      // Return file as a download response
      return response()->streamDownload(function () use ($fileContents) {
          echo $fileContents;
      }, $fileName);
  }

  public function sendImpexOrderWhatsApp($order_code){
    $invoiceController = new InvoiceController();
      $file_url = $invoiceController->invoice_combined_order($order_code);

      $order = OwnBrandOrder::where('order_code', $order_code)->first();
      $user = User::where('id', $order->customer_id)->first();


      $file_name="Impex Order Invoice";
      $to=["7044300330"];
      $templateData = [
          'name' => 'utility_order_template',
          'language' => 'en_US', 
          'components' => [
          [
              'type' => 'header',
                  'parameters' => [
                      [
                          'type' => 'document', // Use 'image', 'video', etc. as needed
                          'document' => [
                              'link' => $file_url,
                              'filename' => $file_name,
                          ]
                      ]
                  ]
              ],
              [
                  'type' => 'body',
                  'parameters' => [
                      ['type' => 'text','text' => $user->company_name],
                      ['type' => 'text','text' => $order_code],
                      ['type' => 'text','text' => $order->created_at],
                      ['type' => 'text','text' => $order->grand_total ]
                  ],
              ],
          
          ],
      ];

    

      $this->WhatsAppWebService=new WhatsAppWebService();
      foreach($to as $person_to_send){
        $response = $this->WhatsAppWebService->sendTemplateMessage($person_to_send, $templateData);
      
      }
      

  }


  public function international_confirm_order_details($id) {

    $order = OwnBrandOrder::with(['orderDetails' => function($query) {
        $query->whereNull('deleted_at');
    },'product'])->findOrFail(decrypt($id));

    $deleteOrderProduct = OwnBrandOrder::with(['orderDetails' => function($query) {
        $query->onlyTrashed(); // Use onlyTrashed() to get soft-deleted order details
    },'product'])->findOrFail(decrypt($id));
    return view('backend.sales.international_confirm_order_details', compact('order','deleteOrderProduct'));
  }

  public function international_order_update_delivery_status(Request $request) {
    $order = OwnBrandOrder::findOrFail($request->order_id);
    $order->delivery_status = $request->status;
    $order->save();
    $uodateArray = array();
    $uodateArray['delivery_status'] = $request->status;
    $order = OwnBrandOrderDetail::where('order_id',$request->order_id)->update($uodateArray);
    return true;
  }

  public function international_order_update_confirm_status(Request $request) {
    $order = OwnBrandOrder::findOrFail($request->order_id);
    $order->delivery_status = 'confirm';
    $order->advance_amount = $request->advance_amount;
    $order->save();
    $uodateArray = array();
    $uodateArray['delivery_status'] = $request->status;
    $order = OwnBrandOrderDetail::where('order_id',$request->order_id)->update($uodateArray);
    return true;
  }

  public function international_order_update_payment_status(Request $request) {

    $order = OwnBrandOrder::findOrFail($request->order_id);
    $order->payment_status = $request->status;
    $order->save();
    $uodateArray = array();
    $uodateArray['payment_status'] = $request->status;
    $order = OwnBrandOrderDetail::where('order_id',$request->order_id)->update($uodateArray); 
    return true;
  }

  public function international_order_update_qty(Request $request) {
    $orderDetails = OwnBrandOrderDetail::findOrFail($request->order_detail_id);
    $order = OwnBrandOrder::where('id',$orderDetails->order_id)->first();
    $subtotal = $orderDetails->unit_price * $request->qty;
    $orderDetails->total_price = $orderDetails->unit_price * $request->qty;
    $orderDetails->quantity = $request->qty;
    $orderDetails->save();

    $grand_total = OwnBrandOrderDetail::where('order_id', $orderDetails->order_id)->sum('total_price');
    $updateArray = array();
    $updateArray['grand_total'] = $grand_total;
    OwnBrandOrder::where('id',$orderDetails->order_id)->update($updateArray);

    return response()->json([
        'subtotal' => $subtotal,
        'grandTotal' => $grand_total,
        'currency' => $order->currency
    ], 200);

  }

  public function international_order_update_unit_price(Request $request) {
    $orderDetails = OwnBrandOrderDetail::findOrFail($request->order_detail_id);
    $order = OwnBrandOrder::where('id',$orderDetails->order_id)->first();
    $subtotal = $orderDetails->quantity * $request->unit_price;
    $orderDetails->total_price = $orderDetails->quantity * $request->unit_price;
    $orderDetails->unit_price = $request->unit_price;
    $orderDetails->save();

    $grand_total = OwnBrandOrderDetail::where('order_id', $orderDetails->order_id)->sum('total_price');
    $updateArray = array();
    $updateArray['grand_total'] = $grand_total;
    OwnBrandOrder::where('id',$orderDetails->order_id)->update($updateArray);

    return response()->json([
        'subtotal' => $subtotal,
        'grandTotal' => $grand_total,
        'currency' => $order->currency
    ], 200);
  }

  public function international_order_update_brand(Request $request) {
    $orderDetails = OwnBrandOrderDetail::findOrFail($request->order_detail_id);    
    $orderDetails->brand_name = $request->brand_name;
    $orderDetails->brand = $request->brand;
    $orderDetails->save();

    return response()->json([
        'brand' => $request->brand,
        'brand_name' => $request->brand_name,
        'order_detail_id' => $request->order_detail_id
    ], 200);
  }

  public function international_order_add_or_update_comment_and_days_of_delivery(Request $request) {
    $orderDetails = OwnBrandOrderDetail::findOrFail($request->order_detail_id);    
    $orderDetails->comment = $request->comment;
    $orderDetails->days_of_delivery = $request->days_of_delivery;
    $orderDetails->save();

    return response()->json([
        'comment' => $request->comment,
        'days_of_delivery' => $request->days_of_delivery,
        'order_detail_id' => $request->order_detail_id
    ], 200);
  }

  public function international_order_delete_product(Request $request) {
    $orderDetails = OwnBrandOrderDetail::findOrFail($request->order_detail_id);
    $order_id = $orderDetails->order_id;
    $orderDetails->delete();

    $grandTotal = OwnBrandOrderDetail::where('order_id', $order_id)->sum('total_price');
    $updateArray = array();
    $updateArray['grand_total'] = $grandTotal;
    OwnBrandOrder::where('id',$order_id)->update($updateArray);
    $order = OwnBrandOrder::where('id',$order_id)->first();
    $deleteOrderProduct = OwnBrandOrder::with(['orderDetails' => function($query) {
          $query->onlyTrashed(); // Use onlyTrashed() to get soft-deleted order details
      },'product'])->findOrFail($order_id);
    $html = "";
    $html .= '<table class="table-bordered invoice-summary table"><thead><tr class="bg-trans-dark"><th data-breakpoints="lg" class="min-col">#</th><th width="10%">Photo</th><th class="text-uppercase">Description</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Qty</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Unit Price</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Total</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Action</th></tr></thead><tbody>';
    foreach ($deleteOrderProduct->orderDetails as $key => $orderDetail){
      $html .= '<tr id="tr_reverse_'.$orderDetail->id.'"><td>'. ($key + 1) .'</td><td>';
      // Display product image or "N/A"
      if ($orderDetail->product != null) {
          $html .= '<a href="#" target="_blank"><img height="50" src="'. uploaded_asset($orderDetail->product->thumbnail_img) .'"></a>';
      } else {
          $html .= '<strong>'. translate('N/A') .'</strong>';
      }
      $html .= '</td><td>';

      // Display product name, brand, and part number
      if ($orderDetail->product != null) { 
          $html .= '<strong><a href="#" target="_blank" class="text-muted">' . $orderDetail->product->name . '</a></strong><br><small id="small_brand_'.$orderDetail->id.'">';
          if ($orderDetail->brand_name != "" || $orderDetail->brand_name != null) {
              $html .= $orderDetail->brand . ' : ' . $orderDetail->brand_name;
          } else {
              $html .= $orderDetail->brand;
          }
          $html .= '</small><br><small>'. translate('Part No.') .': ' . $orderDetail->product->part_no . '</small>';
      } else {
          $html .= '<strong>'. translate('Product Unavailable') .'</strong>';
      }

      $html .= '</td><td class="text-center" style="justify-content: center; align-items: center;">';
      // Quantity input with readonly attribute
      $html .= '<input type="text" value="'. $orderDetail->quantity .'" name="quantity_'. $orderDetail->id .'" id="quantity_'. $orderDetail->id .'" class="form-control" style="width: 100px; text-align: center; margin: auto;" oninput="updateQty(this.value, '. $orderDetail->id .')" readonly>';
      $html .= '</td><td class="text-center">';

      // Unit price input with currency on the left
      $html .= '<div style="position: relative; display: inline-flex; align-items: center;">';
      $html .= '<span style="position: absolute; left: 10px; padding-right: 5px; color: #888;">'. $order->currency .'</span>';
      $html .= '<input type="text" value="'. $orderDetail->unit_price .'" name="unit_price" id="unit_price" class="form-control" style="padding-left: 30px; width: 100px; text-align: left; margin: auto;" oninput="updateUnitPrice(this.value, '. $orderDetail->id .')" readonly>';
      $html .= '</div>';

      $html .= '</td><td class="text-center">';
      // Display total price with currency symbol
      $html .= '<span id="spanTotalPrice_'. $orderDetail->id .'">'. $order->currency .' '. $orderDetail->total_price .'</span>';
      $html .= '</td><td class="text-center">';
      // Display green arrow icon
      $html .= '<i class="las la-arrow-circle-up" style="color: green; font-size: 24px; cursor:pointer;" onclick="productReverse('.$orderDetail->id.')"></i>';
      $html .= '</td></tr>';
    }
    $html .= '</tbody></table>';
    return response()->json([
        'grandTotal' => $grandTotal,
        'currency' => $order->currency,
        'html' => $html
    ], 200);
  }

  public function international_order_reverse_product(Request $request) {
    $orderDetails = OwnBrandOrderDetail::withTrashed()->findOrFail($request->order_detail_id);
    $order_id = $orderDetails->order_id;
    $orderDetails->restore();

    $grandTotal = OwnBrandOrderDetail::where('order_id', $order_id)->sum('total_price');
    $updateArray = array();
    $updateArray['grand_total'] = $grandTotal;
    OwnBrandOrder::where('id',$order_id)->update($updateArray);
    
    $order = OwnBrandOrder::with(['orderDetails' => function($query) {
      $query->whereNull('deleted_at');
      },'product'])->findOrFail($order_id);
    $html = "";
    $html .= '<table class="table-bordered invoice-summary table"><thead><tr class="bg-trans-dark"><th data-breakpoints="lg" class="min-col">#</th><th width="10%">Photo</th><th class="text-uppercase">Description</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Brand</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Comment <br/> and <br/>Days of Delivery</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Qty</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Unit Price</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Total</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Action</th></tr></thead><tbody>';
    foreach ($order->orderDetails as $key => $orderDetail){
      $html .= '<tr id="tr_'.$orderDetail->id.'"><td>'. ($key + 1) .'</td><td>';
      // Display product image or "N/A"
      if ($orderDetail->product != null) {
          $html .= '<a href="#" target="_blank"><img height="50" src="'. uploaded_asset($orderDetail->product->thumbnail_img) .'"></a>';
      } else {
          $html .= '<strong>'. translate('N/A') .'</strong>';
      }
      $html .= '</td><td>';

      // Display product name, brand, and part number
      if ($orderDetail->product != null) { 
          $html .= '<strong><a href="#" target="_blank" class="text-muted">' . $orderDetail->product->name . '</a></strong><br><small id="small_brand_'.$orderDetail->id.'">';
          if ($orderDetail->brand_name != "" || $orderDetail->brand_name != null) {
              $html .= $orderDetail->brand . ' : ' . $orderDetail->brand_name;
          } else {
              $html .= $orderDetail->brand;
          }
          $html .= '</small><br><small>'. translate('Part No.') .': ' . $orderDetail->product->part_no . '</small>';
      } else {
          $html .= '<strong>'. translate('Product Unavailable') .'</strong>';
      }
      $html .= '<td class="text-center" style="justify-content: center; align-items: center;" id="trBrand_{{$orderDetail->id}}">';
      $html .= '<i class="las la-highlighter" style="color: green; font-size: 24px; cursor:pointer;" data-brand="'.$orderDetail->brand.'" data-brand-name="'.$orderDetail->brand_name.'" data-orderdetails-id="'.$orderDetail->id.'" onclick="openModal(this)"></i>';
      $html .= '</td>';
      $html .= '<td class="text-center" style="justify-content: center; align-items: center;" id="trBrand_{{$orderDetail->id}}">';
      $html .= '<i class="las la-comment" style="color: #25bcf1; font-size: 24px; cursor:pointer;" data-comment="'.$orderDetail->comment.'"  data-days-of-delivery="'.$orderDetail->days_of_delivery.'" data-orderdetails-id="'.$orderDetail->id.'" onclick="openCommentModal(this)"></i>';
      $html .= '</td>';
      $html .= '</td><td class="text-center" style="justify-content: center; align-items: center;">';
      // Quantity input with readonly attribute
      $html .= '<input type="text" value="'. $orderDetail->quantity .'" name="quantity_'. $orderDetail->id .'" id="quantity_'. $orderDetail->id .'" class="form-control" style="width: 100px; text-align: center; margin: auto;" oninput="updateQty(this.value, '. $orderDetail->id .')">';
      $html .= '</td><td class="text-center">';

      // Unit price input with currency on the left
      $html .= '<div style="position: relative; display: inline-flex; align-items: center;">';
      $html .= '<span style="position: absolute; left: 10px; padding-right: 5px; color: #888;">'. $order->currency .'</span>';
      $html .= '<input type="text" value="'. $orderDetail->unit_price .'" name="unit_price" id="unit_price" class="form-control" style="padding-left: 30px; width: 100px; text-align: left; margin: auto;" oninput="updateUnitPrice(this.value, '. $orderDetail->id .')">';
      $html .= '</div>';

      $html .= '</td><td class="text-center">';
      // Display total price with currency symbol
      $html .= '<span id="spanTotalPrice_'. $orderDetail->id .'">'. $order->currency .' '. $orderDetail->total_price .'</span>';
      $html .= '</td><td class="text-center">';
      $html .= '<i class="las la-trash" style="color: red; font-size: 24px; cursor:pointer;" onclick="productDelete('.$orderDetail->id.')"></i>';
      $html .= '</td></tr>';
    }
    $html .= '</tbody></table>';
    return response()->json([
        'grandTotal' => $grandTotal,
        'currency' => $order->currency,
        'html' => $html
    ], 200);
  }

  public function getOwnBrandProductList(Request $request){
    $orderId = $request->input('order_id');
    $order = OwnBrandOrder::where('id',$orderId)->first();
    
    // $products = OwnBrandProduct::all(); // Fetch all products (adjust if needed)
    $category_group    = $categories    = $brands    = $selected_brands    = $selected_categories   = $products    = [];
    $products = OwnBrandProduct::query();
    $products = Cache::remember('all_product_items', 5, function () use ($categories) {
        // Build the query
        $productQuery = OwnBrandProduct::query()
            ->join('own_brand_categories', 'own_brand_products.category_id', '=', 'own_brand_categories.id')
            ->join('own_brand_category_groups', 'own_brand_categories.category_group_id', '=', 'own_brand_category_groups.id');
        
        // Select columns and apply sorting
        $productQuery->select(
                'own_brand_products.id',
                'own_brand_category_groups.name AS group_name',
                'own_brand_categories.name AS category_name',
                'group_id',
                'category_id',
                'own_brand_products.name',
                'thumbnail_img',
                'own_brand_products.slug',
                'own_brand_products.dollar_purchase_price',
                'own_brand_products.inr_bronze',
                'own_brand_products.inr_silver',
                'own_brand_products.inr_gold',
                'own_brand_products.doller_bronze',
                'own_brand_products.doller_silver',
                'own_brand_products.doller_gold'             
            )
            ->where('published', true)
            ->where('approved', true)
            ->orderBy('own_brand_category_groups.name', 'asc')
            ->orderBy('own_brand_categories.name', 'asc');
        
        // Apply pagination directly on the query and return paginated result
        return $productQuery->paginate(50);  // Cache the paginated query result
    });
    // echo "<pre>";print_r($products);die;
    $products = $this->processProducts($products,$order->user->id);

    // Apply pagination directly on the query and return paginated result
    // return $productQuery->paginate(50);  // Cache the paginated query result
    // dd($products);
    return view('backend.sales.international_product_list', compact('products'));
  }

  private function processProducts($products,$user_id) {
    foreach ($products as $product) {
        $price = 0;
        $markup = 1;     
        // Log a message to the browser console    
        $user = User::where('id',$user_id)->first();    
        $price = $product->dollar_purchase_price;
        $userPhone = $user->phone;
        $firstTwoChars = substr($userPhone, 0, 3);
        if($firstTwoChars == "+91"){
            if($user->profile_type == 'Bronze'){
                $markup = $product->inr_bronze;
            }elseif($user->profile_type == 'Silver'){
                $markup = $product->inr_silver;
            }elseif($user->profile_type == 'Gold'){
                $markup = $product->inr_gold;
            }
            $price = 'тВ╣'.round(($price*$markup));
        }else{
            if($user->profile_type == 'Bronze'){
                $markup = $product->doller_bronze;
            }elseif($user->profile_type == 'Silver'){
                $markup = $product->doller_silver;
            }elseif($user->profile_type == 'Gold'){
                $markup = $product->doller_gold;
            }
            // echo $markup; die;
            $price = '$'.number_format($price+(($price*$markup)/100),2);
        }
        $product->convertPrice = $price;
    }
    return $products;
  }

  public function international_order_add_product(Request $request) {
      $orderId = $request->order_id;
      $order = OwnBrandOrder::where('id',$orderId)->first();
      $grandtotal = $order->grand_total;
      $user = $order->user;
      $userPhone = $user->phone;
      $firstTwoChars = substr($userPhone, 0, 3);
      if($firstTwoChars == "+91"){
          $currency = 'тВ╣';
      }else{
          $currency = '$';
      }    
      $product = OwnBrandProduct::find($request->product_id);
      if($currency == "тВ╣"){
          if($user->profile_type == 'Bronze'){
              $markup = $product->inr_bronze;
          }elseif($user->profile_type == 'Silver'){
              $markup = $product->inr_silver;
          }elseif($user->profile_type == 'Gold'){
              $markup = $product->inr_gold;
          }
          $unitPrice = round(($product->dollar_purchase_price*$markup));
      }elseif($currency == '$'){
          if($user->profile_type == 'Bronze'){
              $markup = $product->doller_bronze;
          }elseif($user->profile_type == 'Silver'){
              $markup = $product->doller_silver;
          }elseif($user->profile_type == 'Gold'){
              $markup = $product->doller_gold;
          }
          $unitPrice = number_format($product->dollar_purchase_price+(($product->dollar_purchase_price*$markup)/100),2);
      }
      
      $addFlag = 0;
      $ownBrandOrderDetailData = OwnBrandOrderDetail::where('product_id',$request->product_id)->where('order_id',$orderId)->first();
      if($ownBrandOrderDetailData === NULL){
        $product = OwnBrandProduct::find($request->product_id);
        $orderDetails = array();
        $orderDetails['order_id'] = $orderId;
        $orderDetails['order_code'] = $order->order_code;
        $orderDetails['product_id'] = $request->product_id;
        $orderDetails['name'] = $product->name;
        $orderDetails['slug'] = $product->slug;
        $orderDetails['brand'] = 'Our Brand - OPEL';
        // $orderDetails['brand_name'] = $key->brand_name;
        $orderDetails['unit_price'] = $unitPrice;
        // $orderDetails['tax'] = $key->tax;
        $orderDetails['quantity'] = $product->min_order_qty_1;
        $orderDetails['total_price'] = $product->min_order_qty_1 * $unitPrice;
        $orderDetails['purchase_time_unit_price'] = $unitPrice;
        $orderDetails['purchase_time_quantity'] = $product->min_order_qty_1;
        $orderDetails['purchase_time_brand'] = 'Our Brand - OPEL';
        $orderDetailsData = OwnBrandOrderDetail::create($orderDetails);

        $updateArray = array();
        $updateArray['grand_total'] = $grandtotal = $order->grand_total + ($product->min_order_qty_1 * $unitPrice);
        $orderDetails = OwnBrandOrder::where('order_code', $order->order_code)->update($updateArray);
        $addFlag = 1;
      }

      // echo "<pre>"; print_r($updateArray);die;

      $order = OwnBrandOrder::with(['orderDetails' => function($query) {
        $query->whereNull('deleted_at');
        },'product'])->findOrFail($orderId);
      $html = "";
      $html .= '<table class="table-bordered invoice-summary table"><thead><tr class="bg-trans-dark"><th data-breakpoints="lg" class="min-col">#</th><th width="10%">Photo</th><th class="text-uppercase">Description</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Brand</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Comment <br/> and <br/>Days of Delivery</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Qty</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Unit Price</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Total</th><th data-breakpoints="lg" class="min-col text-uppercase text-center">Action</th></tr></thead><tbody>';
      foreach ($order->orderDetails as $key => $orderDetail){
        $html .= '<tr id="tr_'.$orderDetail->id.'"><td>'. ($key + 1) .'</td><td>';
        // Display product image or "N/A"
        if ($orderDetail->product != null) {
            $html .= '<a href="#" target="_blank"><img height="50" src="'. uploaded_asset($orderDetail->product->thumbnail_img) .'"></a>';
        } else {
            $html .= '<strong>'. translate('N/A') .'</strong>';
        }
        $html .= '</td><td>';
  
        // Display product name, brand, and part number
        if ($orderDetail->product != null) { 
            $html .= '<strong><a href="#" target="_blank" class="text-muted">' . $orderDetail->product->name . '</a></strong><br><small id="small_brand_'.$orderDetail->id.'">';
            if ($orderDetail->brand_name != "" || $orderDetail->brand_name != null) {
                $html .= $orderDetail->brand . ' : ' . $orderDetail->brand_name;
            } else {
                $html .= $orderDetail->brand;
            }
            $html .= '</small><br><small>'. translate('Part No.') .': ' . $orderDetail->product->part_no . '</small>';
        } else {
            $html .= '<strong>'. translate('Product Unavailable') .'</strong>';
        }
        $html .= '<td class="text-center" style="justify-content: center; align-items: center;" id="trBrand_{{$orderDetail->id}}">';
        $html .= '<i class="las la-highlighter" style="color: green; font-size: 24px; cursor:pointer;" data-brand="'.$orderDetail->brand.'" data-brand-name="'.$orderDetail->brand_name.'" data-orderdetails-id="'.$orderDetail->id.'" onclick="openModal(this)"></i>';
        $html .= '</td>';
        $html .= '<td class="text-center" style="justify-content: center; align-items: center;" id="trBrand_{{$orderDetail->id}}">';
        $html .= '<i class="las la-comment" style="color: #25bcf1; font-size: 24px; cursor:pointer;" data-comment="'.$orderDetail->comment.'"  data-days-of-delivery="'.$orderDetail->days_of_delivery.'" data-orderdetails-id="'.$orderDetail->id.'" onclick="openCommentModal(this)"></i>';
        $html .= '</td>';
        $html .= '</td><td class="text-center" style="justify-content: center; align-items: center;">';
        // Quantity input with readonly attribute
        $html .= '<input type="text" value="'. $orderDetail->quantity .'" name="quantity_'. $orderDetail->id .'" id="quantity_'. $orderDetail->id .'" class="form-control" style="width: 100px; text-align: center; margin: auto;" oninput="updateQty(this.value, '. $orderDetail->id .')">';
        $html .= '</td><td class="text-center">';
  
        // Unit price input with currency on the left
        $html .= '<div style="position: relative; display: inline-flex; align-items: center;">';
        $html .= '<span style="position: absolute; left: 10px; padding-right: 5px; color: #888;">'. $order->currency .'</span>';
        $html .= '<input type="text" value="'. $orderDetail->unit_price .'" name="unit_price" id="unit_price" class="form-control" style="padding-left: 30px; width: 100px; text-align: left; margin: auto;" oninput="updateUnitPrice(this.value, '. $orderDetail->id .')">';
        $html .= '</div>';
  
        $html .= '</td><td class="text-center">';
        // Display total price with currency symbol
        $html .= '<span id="spanTotalPrice_'. $orderDetail->id .'">'. $order->currency .' '. $orderDetail->total_price .'</span>';
        $html .= '</td><td class="text-center">';
        $html .= '<i class="las la-trash" style="color: red; font-size: 24px; cursor:pointer;" onclick="productDelete('.$orderDetail->id.')"></i>';
        $html .= '</td></tr>';
      }
      $html .= '</tbody></table>';

      return response()->json([
          'grandTotal' => $grandtotal,
          'currency' => $order->currency,
          'addFlag' => $addFlag,
          'html' => $html
      ], 200);
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

    public function manager41SplitOrder($order_id, $redirect = "", $data_status = "")
    {
        try {
            // Pull from Manager-41 order tables
            $orderData = Manager41Order::with([
                'orderDetails' => function ($query) {
                    $query->whereNull('regret_qty')->orWhere('regret_qty', 0);
                },
                'user',
                'sub_order'
            ])->where('id', $order_id)->first();

            // Sub-orders for Manager-41
            $btrOrder      = Manager41SubOrder::where('order_id', $order_id);
            $btrOrderCount = $btrOrder->where('type', 'btr')->count();

            if ($btrOrderCount <= 0) {
                $btrOrderDetails = Manager41SubOrder::where('user_id', $orderData->user_id)
                    ->where('status', 'completed')
                    ->orderBy('id', 'DESC')
                    ->get();
            } else {
                $btrOrderDetails = $btrOrder->get();
            }

            $orderDetails            = $orderData->orderDetails;
            $userDetails             = $orderData->user;
            $allAddressesForThisUser = $userDetails->get_addresses;
            $shippingAddress         = $userDetails->get_addresses->where('id', $orderData->address_id)->first();

            // Shared lookups
            $allWareHouse     = Warehouse::where('active', '1')->get();
            $allTransportData = Carrier::orderBy('name', 'ASC')->get();

            // If you keep a common payment history table against bill_number (order code), you can still use it
            $paymentHistory = PaymentHistory::where('bill_number', $orderData->code)
                ->where('status', 'SUCCESS')
                ->count();

            $address = Address::where('id', $orderData->address_id)->first();

            $userId = $orderData->user_id;
            $lastBtrOrder = Manager41SubOrder::where('type', 'btr')
                ->whereHas('order', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->orderBy('id', 'desc')
                ->first();

            $lastBtrOrderWarehouseId = $lastBtrOrder->warehouse_id ?? "";

            $firstSubOrder = $btrOrderDetails->first();
            if ($firstSubOrder && $firstSubOrder->user) {
                $partyCode        = $firstSubOrder->user->party_code;
                $getRewardsOfUser = RewardUser::where('party_code', $partyCode)->get();
            } else {
                $getRewardsOfUser = collect();
            }

            // Reuse the same view; it expects the same variable names
            return view(
                'backend.sales.split_order',
                compact(
                    'orderData',
                    'orderDetails',
                    'userDetails',
                    'allAddressesForThisUser',
                    'shippingAddress',
                    'allWareHouse',
                    'allTransportData',
                    'btrOrderDetails',
                    'btrOrderCount',
                    'paymentHistory',
                    'address',
                    'redirect',
                    'getRewardsOfUser',
                    'lastBtrOrderWarehouseId'
                )
            );
        } catch (\Exception $e) {
            $errorCode    = $e->getCode();
            $errorMessage = $errorCode == 23000 ? __("direct link already exists") : $e->getMessage();
            $response     = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
            return $response;
        }
    }


  public function splitOrder($order_id,$redirect="",$data_status=""){
    // If the acting staff in session is a 41-Manager, switch to the 41 flow
    if ($this->isActingAs41Manager()) {
        return $this->manager41SplitOrder($order_id, $redirect, $data_status);
    }
    
    // ---------- Original flow (uses standard Order/SubOrder) ----------
    try{
      $orderData = Order::with([
          'orderDetails' => function ($query) {
              $query->whereNull('regret_qty')->orWhere('regret_qty', 0);
          },
          'user',
          'sub_order'
      ])->where('id', $order_id)->first();
      
      $btrOrder = SubOrder::where('order_id',$order_id);
      $btrOrderCount = $btrOrder->where('type','btr')->count();
      if($btrOrderCount <= 0){
        $btrOrderDetails = SubOrder::where('user_id',$orderData->user_id)->where('status','completed')->orderBy('id','DESC')->get();
      }else{
        $btrOrderDetails = $btrOrder->get();
      }
      // echo "<pre>"; echo $btrOrderCount; print_r($btrOrderDetails);die;
      
      $orderDetails = $orderData->orderDetails;
      $userDetails = $orderData->user;
      $allAddressesForThisUser = $orderData->user->get_addresses;      
      $shippingAddress = $userDetails->get_addresses->where('id', $orderData->address_id)->first();
      
      $allWareHouse = Warehouse::where('active','1')->get();
      // $allProduct = Product::where('current_stock','>', '0')->get();
      $allTransportData = Carrier::orderBy('name','ASC')->get();

      $paymentHistory = PaymentHistory::where('bill_number',$orderData->code)->where('status','SUCCESS')->count();
      $address = Address::where('id',$orderData->address_id)->first();
      $userId = $orderData->user_id;
      $lastBtrOrder = SubOrder::where('type', 'btr')->whereHas('order', function ($q) use ($userId) {
          $q->where('user_id', $userId);
      })
      ->orderBy('id', 'desc') // or created_at
      ->first();

      // 1. Get the last BTR sub-order for this user
        $lastBtrOrder = SubOrder::where('type', 'btr')
            ->whereHas('order', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->orderByDesc('id')   // or ->orderByDesc('created_at')
            ->first();

        if (!$lastBtrOrder) {
            $lastBtrOrders = collect();
            $lastBtrWarehouseIds = array();
        } else {
            $lastBtrOrders = SubOrder::where('type', 'btr')
            ->where('code', $lastBtrOrder->code)
            ->whereHas('order', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->get();
            $lastBtrWarehouseIds = $lastBtrOrders->pluck('warehouse_id')->unique()->values()->toArray();
        }


      $firstSubOrder = $btrOrderDetails->first();

      if ($firstSubOrder && $firstSubOrder->user) {
          $partyCode = $firstSubOrder->user->party_code;
          $getRewardsOfUser = RewardUser::where('party_code', $partyCode)->get();
      } else {
          $getRewardsOfUser = collect(); // empty collection fallback
      }
      // $getRewardsOfUser = RewardPointsOfUser::where('party_code', $btrOrderDetails->user->party_code)->get();
      // echo "<pre>"; print_r($getRewardsOfUser); die;

      return view('backend.sales.split_order', compact('orderData','orderDetails','userDetails','allAddressesForThisUser','shippingAddress','allWareHouse','allTransportData','btrOrderDetails','btrOrderCount','paymentHistory','address','redirect','getRewardsOfUser','lastBtrWarehouseIds'));
    } catch (\Exception $e) {
        $errorCode = $e->getCode();
        if ($errorCode == 23000) {
            $errorMessage = __("direct link already exists");
        } else {
            $errorMessage = $e->getMessage();//"something went wrong, please try again";
        }
        $response = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }        
    return $response; 
  }


    public function manager41SplitOrderDetails($order_id)
    {
        try {
            $orderData = Manager41SubOrder::with('sub_order_details','user')
                ->where('id', $order_id)
                ->first();

            if (!$orderData) {
                return back()->with('error', 'Manager 41 sub order not found.');
            }

            $orderDetails = Manager41SubOrderDetail::with('product','user','btrSubOrder:id,id,order_id,product_id,sub_order_id,type')
                ->where('sub_order_id', $order_id)
                ->where('pre_closed_status', '0')
                ->get();

            $userDetails = $orderData->user;

            // Use address_id if present; else fallback to shipping_address_id (manager_41 schema parity)
            $addressId = $orderData->address_id ?? $orderData->shipping_address_id;
            $allAddressesForThisUser = $userDetails->get_addresses;
            $shippingAddress = $userDetails->get_addresses->where('id', $addressId)->first();

            $allWareHouse     = Warehouse::where('active', '1')->get();
            $allTransportData = Carrier::orderBy('name', 'ASC')->get();

            // Parent of me (if I am BTR) and child (if I have a BTR) тАФ manager_41 tables
            $hasBTR = Manager41SubOrder::with('sub_order_details','user')
                ->where('id', $orderData->sub_order_id)
                ->where('type', 'sub_order')
                ->first();
            $hasBTRId = $hasBTR ? $hasBTR->id : "";

            $hasBTROrder = Manager41SubOrder::with('sub_order_details','user')
                ->where('sub_order_id', $orderData->id)
                ->where('type', 'btr')
                ->first();
            $hasBTROrderId = $hasBTROrder ? $hasBTROrder->id : "";

            // Reuse the same view тАФ structure matches
            return view('backend.sales.split_order_details', compact(
                'orderData','orderDetails','userDetails','allAddressesForThisUser',
                'shippingAddress','allWareHouse','allTransportData','hasBTRId','hasBTROrderId'
            ));

        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = ($errorCode == 23000) ? __("direct link already exists") : $e->getMessage();
            $response = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
        }
        return $response;
    }

  public function splitOrderDetails($order_id){
    // If the current login is manager_41, delegate to the 41-manager version
    if ($this->isActingAs41Manager()) {
        return $this->manager41SplitOrderDetails($order_id);
    }
    try{
      $orderData = SubOrder::with('sub_order_details','user')->where('id',$order_id)->first();
      $orderDetails = SubOrderDetail::with('product','user','btrSubOrder:id,id,order_id,product_id,sub_order_id,type')->where('sub_order_id',$order_id)->where('pre_closed_status','0')->get();   

      // ★ ADDED: part_no => purchase price (fallback to unit_price)
      $allProductRates = [];
      foreach ($orderDetails as $sod) {
          foreach ($sod->product as $prod) {
              $pn = (string) ($prod->part_no ?? '');
              if ($pn === '') continue;
              $buy = (float) ($prod->purchase_price ?? $prod->unit_price ?? 0);
              $allProductRates[$pn] = $buy;   // keep RAW key; Blade will use RAW too
          }
      }
      // ★ ADDED END

      $userDetails = $orderData->user;
      // echo "<pre>"; print_r($orderDetails);die;
      $allAddressesForThisUser = $orderData->user->get_addresses;      
      $shippingAddress = $userDetails->get_addresses->where('id', $orderData->address_id)->first();
      
      $allWareHouse = Warehouse::where('active','1')->get(); 
    //   echo $userDetails->id; die;
      $selectedTransportData = SubOrder::where('user_id',$userDetails->id)->whereNotNull('transport_id')->orderBy('id','DESC')->first(); 
      $allTransportData = Carrier::orderBy('name','ASC')->get();

      $hasBTR = SubOrder::with('sub_order_details','user')->where('id',$orderData->sub_order_id)->where('type','sub_order')->first();
      
      $hasBTRId = "";
      if($hasBTR != NULL){
        $hasBTRId = $hasBTR->id;
      }

      $hasBTROrder = SubOrder::with('sub_order_details','user')->where('sub_order_id',$orderData->id)->where('type','btr')->first();
      $hasBTROrderId = "";
      if($hasBTROrder != NULL){
        $hasBTROrderId = $hasBTROrder->id;
      }

      // echo "<pre>"; print_r($hasBTROrder); die;
      return view('backend.sales.split_order_details', compact('orderData','orderDetails','userDetails','allAddressesForThisUser','shippingAddress','allWareHouse','allTransportData','hasBTRId','hasBTROrderId','allProductRates','selectedTransportData'));

    } catch (\Exception $e) {
        $errorCode = $e->getCode();
        if ($errorCode == 23000) {
            $errorMessage = __("direct link already exists");
        } else {
            $errorMessage = $e->getMessage();//"something went wrong, please try again";
        }
        $response = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }        
    return $response;
  }

  public function changeOrderAddress(Request $request){
    try{ 

      $user = User::where('id',$request->user_id)->first();
      $addressDetails = Address::where('user_id',$request->user_id)->orderBy('id','DESC')->first();      

      $address = new Address;
      $address->user_id = $request->user_id;
      
      $count = 10;
      if($addressDetails !== null){
        $party_code = $user->party_code;
        $lastAccCode = $addressDetails->acc_code;
        if($party_code == $lastAccCode){
          $address->acc_code = $lastAccCode.$count;
        }else{
          $prefix = "OPEL";
          $numericPart = str_replace($prefix, "", $lastAccCode);
          $incrementedNumericPart = (int)$numericPart + 1;
          $paddedIncrementedNumericPart = str_pad($incrementedNumericPart, strlen($numericPart), "0", STR_PAD_LEFT);
          $newAccCode = $prefix . $paddedIncrementedNumericPart;

          $address->acc_code = $newAccCode;
        }
      }else{
        $address->acc_code      = $user->party_code;
      }
      
      $address->address      = $request->address;
      $address->address_2      = $request->address_2;
      $address->company_name = $request->company_name;
      if ($request->gstin) {
        
        $pincode = Pincode::where('pincode', $request->postal_code)->first();
        $city = City::where('name', $pincode->city)->first();
        $state = State::where('name', $pincode->state)->first();
        
        if(!isset($city->id)){
          $city = City::create([
            'name'                   => $pincode->city,
            'state_id'           => $state->id
          ]);
        }else{
          $city = $city->id;
        }
        
        $country_id = 101;
        $city_id = $city ;

        $state_id = $state->id;
      }else{
        $country_id = $request->country_id;
        $city_id = $request->city_id;
        $state_id = $request->state_id;
      }
      
      $address->gstin       = $request->gstin;
      $address->country_id  = $country_id;
      $address->state_id    = $state_id;
      $address->city_id     = $city_id;
      $address->city        = $request->city;
      $address->longitude   = $request->longitude;
      $address->latitude    = $request->latitude;
      $address->postal_code = $request->postal_code;
      $address->phone       = $user->phone;      
      $address->save();

      // Call Zoho function directly
      $zoho = new ZohoController();
      $res = $zoho->createNewCustomerInZoho($address->acc_code); // pass the party_code
      $res = $res->getData(); // returns stdClass
      $zoho_customer_id = $res->zoho_customer_id;
      $address_id = $address->id;
      // echo "<pre>"; print_r($res->zoho_customer_id);
      // echo "<pre>"; print_r($address); die;
      $response = ['res' => true, 'msg' => "Successfully insert data.", 'address_id' => $address_id, 'zoho_customer_id' => $zoho_customer_id ];
      
      return $response;
      // $splitOrderData = SubOrder::create($splitOrder);
      // echo "<pre>"; print_r($splitOrder);print_r($request->all());die;
    } catch (\Exception $e) {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();//"something went wrong, please try again";
        $response = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }        
    return $response; 
  }

  public function manager41SaveSplitOrder(Request $request)
{
    
    try {
        $order_id     = $request->order_id;
        $redirect_url = $request->redirect_url;

        // Wipe any previously generated Manager-41 sub orders for this order
        $existingCount = Manager41SubOrder::where('order_id', $order_id)->count();
        if ($existingCount > 0) {
            Manager41SubOrder::where('order_id', $order_id)->delete();

            // Remove related Manager-41 sub order details
            $subOrderDetailIds = Manager41SubOrderDetail::where('order_id', $order_id)->pluck('id');
            Manager41SubOrderDetail::whereIn('id', $subOrderDetailIds)->delete();

            // NOTE: You asked to avoid negative-inventory & extra side tables for Manager-41,
            // so we are NOT touching PurchaseBag or any negative-stock entries here.
        }

        $order_details_id_array = explode(',', $request->order_details_id);

        // Validation like original (BTR & transport checks), but only if not draft
        if ($request->order_status !== "draft") {
            foreach ($order_details_id_array as $odValue) {
                $kolkataQty = $request->input('Kolkata_allocate_qty_' . $odValue);
                $delhiQty   = $request->input('Delhi_allocate_qty_' . $odValue);
                $mumbaiQty  = $request->input('Mumbai_allocate_qty_' . $odValue);

                if ($request->warehouse_id != 1
                    && (!isset($request->btr_warehouse_1) && $request->btr_transport_id_1 == "" && $kolkataQty != "")
                ) {
                    return redirect()->back()->withInput()->with('error', 'Please enter the transport value for Kolkata.');
                }

                if ($request->warehouse_id != 2
                    && (!isset($request->btr_warehouse_2) && $request->btr_transport_id_2 == "" && $delhiQty != "")
                ) {
                    return redirect()->back()->withInput()->with('error', 'Please enter the transport value for Delhi.');
                }

                if ($request->warehouse_id != 6
                    && (!isset($request->btr_warehouse_6) && $request->btr_transport_id_6 == "" && $mumbaiQty != "")
                ) {
                    return redirect()->back()->withInput()->with('error', 'Please enter the transport value for Mumbai.');
                }
            }

            if (!isset($request->btr_verification)) {
                return redirect()->back()->withInput()->with('error', 'Please check BTR Verification.');
            }
        }

        $userDetails = User::where('id', $request['user_id'])->first();
        $orderData   = Manager41Order::with('orderDetails', 'user')
                        ->where('id', $request->order_id)->first();

        // Separate home warehouse and others
        $homeWarehouseId = (int) $request->warehouse_id;
        $allWarehouses   = Warehouse::where('active', '1')->get();

        $otherWarehouses = [];
        $homeBranch      = [];

        foreach ($allWarehouses as $wh) {
            if ((int)$wh->id !== $homeWarehouseId) {
                $otherWarehouses[$wh->id] = $wh->name;
            } else {
                $homeBranch[$wh->id] = $wh->name;
            }
        }

        // BTR order flag from other warehouses
        $btrOrderFlag = 0;
        foreach ($otherWarehouses as $owKey => $owName) {
            $btrWarehouse    = $request->input('btr_warehouse_' . $owKey);
            $btrTransportName= $request->input('btr_transport_name_' . $owKey);
            if (!empty($btrWarehouse) && $btrOrderFlag == 0) {
                $btrOrderFlag = 1;
            }
        }

        // Check if we need to insert home branch record
        $homeBranchDataInsertFlag = 0;
        foreach ($homeBranch as $hbKey => $hbName) {
            foreach ($order_details_id_array as $odValue) {
                $branchQty = $request->input($hbName . '_allocate_qty_' . $odValue);
                if (($branchQty > 0) || ($btrOrderFlag > 0)) {
                    $homeBranchDataInsertFlag = 1;
                }
            }
        }

        $sub_order_id = null;
        $order_status = ($request['order_status'] === 'completed') ? 'completed' : 'draft';

        // ===========================
        // Insert for HOME BRANCH
        // ===========================
        if ($homeBranchDataInsertFlag == 1) {

            $warehouseData  = Warehouse::find($homeWarehouseId);
            $lastCompleted  = Manager41SubOrder::where('warehouse_id', $homeWarehouseId)
                                ->where('status', 'completed')
                                ->orderBy('id', 'DESC')->first();

            $number = 1;
            $lastOrderNoParts = [];
            if ($lastCompleted) {
                $lastOrderNoParts   = explode('/', $lastCompleted->order_no);
                $secondLastElement  = $lastOrderNoParts[count($lastOrderNoParts) - 2] ?? 0;
                $number             = ((int)$secondLastElement) + 1;
            }
            if (!empty($lastOrderNoParts) && ($this->getFinancialYear() != end($lastOrderNoParts))) {
                $number = 1;
            }

            $order_no = '';
            if ($order_status === 'completed') {
                // 3-letter warehouse code + "41" for Manager-41
                $prefix = strtoupper(substr($warehouseData->name, 0, 3)) . '41';

                $order_no = sprintf(
                    'SO/%s/%06d/%s',
                    $prefix,
                    (int) $number,
                    $this->getFinancialYear()  // e.g. "25-26"
                );
            }

            // Build Manager-41 SubOrder payload (mirror your fields)
            $splitOrder = [
                'order_id'             => $request['order_id'],
                'combined_order_id'    => $request['combined_order_id'],
                'order_no'             => $order_no,
                'user_id'              => $request['user_id'],
                'seller_id'            => $orderData->seller_id,
                'shipping_address_id'  => $request['ship_to'],
                'shipping_address'     => $this->jsonAddress($request['user_id'], $request['ship_to']),
                'billing_address_id'   => $request['bill_to'],
                'billing_address'      => $this->jsonAddress($request['user_id'], $request['bill_to']),
                'additional_info'      => $orderData->additional_info,
                'shipping_type'        => $orderData->shipping_type,
                'payment_status'       => $orderData->payment_status,
                'payment_details'      => $orderData->payment_details,
                'grand_total'          => $orderData->grand_total,
                'payable_amount'       => $orderData->payable_amount,
                'payment_discount'     => $orderData->payment_discount,
                'coupon_discount'      => $orderData->coupon_discount,
                'code'                 => $orderData->code,
                'date'                 => $orderData->date,
                'viewed'               => $orderData->viewed,
                'order_from'           => $orderData->order_from,
                'payment_status_viewed'=> $orderData->payment_status_viewed,
                'commission_calculated'=> $orderData->commission_calculated,
                'status'               => $order_status,
                'warehouse_id'         => $homeWarehouseId,
                'sub_order_user_name'  => $userDetails->name,
            ];

            // Transport info for home branch
            $btrSplitOrder = [
                'type'               => 'sub_order',
                'transport_remarks'  => $request['btr_transport_remarks_'.$homeWarehouseId] ?? null,
                'transport_id'       => $request->input('btr_transport_id_'.$homeWarehouseId),
                'transport_table_id' => $request->input('btr_transport_table_id_'.$homeWarehouseId),
                'transport_name'     => $request->input('btr_transport_name_'.$homeWarehouseId),
                'transport_phone'    => $request->input('btr_transport_mobile_'.$homeWarehouseId),
            ];

            $homeSubOrder = Manager41SubOrder::create(array_merge($splitOrder, $btrSplitOrder));
            $sub_order_id = $homeSubOrder->id;

            // Create sub order details for each selected order_detail row
            foreach ($order_details_id_array as $odValue) {
                $orderDetailsData = Manager41OrderDetail::with('product')->where('id', $odValue)->first();

                $splitOrderDetails = [
                    'order_id'               => $orderDetailsData->order_id,
                    'order_type'             => $orderDetailsData->order_type ?? null,
                    'seller_id'              => $orderDetailsData->seller_id,
                    'og_product_warehouse_id'=> $orderDetailsData->og_product_warehouse_id,
                    'product_warehouse_id'   => $orderDetailsData->product_warehouse_id,
                    'product_id'             => $orderDetailsData->product_id,
                    'variation'              => $orderDetailsData->variation,
                    'shipping_cost'          => $orderDetailsData->shipping_cost,
                    'quantity'               => $orderDetailsData->quantity,
                    'payment_status'         => $orderDetailsData->payment_status,
                    'delivery_status'        => $orderDetailsData->delivery_status,
                    'shipping_type'          => $orderDetailsData->shipping_type,
                    'earn_point'             => $orderDetailsData->earn_point,
                    'cash_and_carry_item'    => $orderDetailsData->cash_and_carry_item,
                    'applied_offer_id'       => $orderDetailsData->applied_offer_id,
                    'complementary_item'     => $orderDetailsData->complementary_item,
                    'offer_rewards'          => $orderDetailsData->offer_rewards,
                    'remarks'                => $request['remark_'.$odValue] ?? null,
                ];

                // Allocate qty for home warehouse
                $whName   = $warehouseData->name;
                $quantity = (int) $request->input($whName . '_allocate_qty_' . $odValue);

                // Closing stock lookup (shared table)
                $closingStocksData = DB::table('products_api')
                    ->where('part_no', optional($orderDetailsData->product)->part_no)
                    ->where('godown', $whName)->first();
                $closingStock = $closingStocksData ? (int) $closingStocksData->closing_stock : 0;

                if ($quantity > 0) {
                    $detailToCreate = array_merge($splitOrderDetails, [
                        'price'            => ($orderDetailsData->price / max(1, (int)$orderDetailsData->quantity)),
                        'tax'              => (($orderDetailsData->price / max(1, (int)$orderDetailsData->quantity)) * 0.18),
                        'sub_order_id'     => $sub_order_id,
                        'order_details_id' => $odValue,
                        'challan_quantity' => null,
                        'approved_quantity'=> $quantity,
                        'closing_qty'      => $closingStock,
                        'approved_rate'    => $request['rate_'.$odValue] ?? null,
                        'warehouse_id'     => $homeWarehouseId,
                        'type'             => 'sub_order',
                    ]);

                    Manager41SubOrderDetail::create($detailToCreate);

                    // Update regret qty in Manager-41 order detail
                    $originalQty = (int) $orderDetailsData->quantity;
                    if (($originalQty - $quantity) > 0) {
                        $orderDetailsData->regret_qty = $originalQty - $quantity;
                    }
                    $orderDetailsData->save();

                    // NOTE: No negativeStockEntry calls in Manager-41 version
                }
            }

            // Rewards save/update for home branch
            $logisticRewards = $request['rewards_'.$homeWarehouseId] ?? null;
            $existingReward  = RewardUser::where('user_id', $request['user_id'])
                                ->where('warehouse_id', $homeWarehouseId)->first();

            if ($existingReward) {
                $existingReward->rewards_percentage = $logisticRewards;
                $existingReward->save();
            } elseif ($logisticRewards !== null && $logisticRewards !== '') {
                RewardUser::create([
                    'user_id'            => $userDetails->id,
                    'company_name'       => $userDetails->company_name,
                    'party_code'         => $userDetails->party_code,
                    'assigned_warehouse' => optional($userDetails->user_warehouse)->name,
                    'city'               => $userDetails->city,
                    'warehouse_id'       => $homeWarehouseId,
                    'warehouse_name'     => $warehouseData->name,
                    'preference'         => ((float)$logisticRewards < 0 ? 1 : 0),
                    'rewards_percentage' => $logisticRewards,
                ]);
            }
        }

        // ===========================
        // Insert for OTHER BRANCHES
        // ===========================
        foreach ($otherWarehouses as $owKey => $owName) {
            $btrWarehouse     = $request->input('btr_warehouse_' . $owKey);
            $btrTransportName = $request->input('btr_transport_name_' . $owKey);

            // Generate order number if needed
            $warehouseData = Warehouse::find($owKey);

            $lastCompleted = Manager41SubOrder::where('warehouse_id', $owKey)
                                ->where('status', 'completed')
                                ->orderBy('id', 'DESC')->first();

            $number = 1;
            if ($lastCompleted) {
                $parts = explode('/', $lastCompleted->order_no);
                $secondLast = $parts[count($parts) - 2] ?? 0;
                $number = ((int)$secondLast) + 1;
            }
           $order_no = "";
            if ($request['order_status'] === 'completed') {
                // 3-letter warehouse prefix + "41"
                $prefix = strtoupper(substr($warehouseData->name, 0, 3)) . '41';

                $order_no = sprintf(
                    'SO/%s/%06d/%s',
                    $prefix,
                    (int) $number,
                    $this->getFinancialYear() // should return like "25-26"
                );
            }

            // Build base payload
            $splitOrder = [
                'order_id'             => $request['order_id'],
                'combined_order_id'    => $request['combined_order_id'],
                'order_no'             => $order_no,
                'user_id'              => $request['user_id'],
                'seller_id'            => $orderData->seller_id,
                'shipping_address_id'  => $request['ship_to'],
                'shipping_address'     => $this->jsonAddress($request['user_id'], $request['ship_to']),
                'billing_address_id'   => $request['bill_to'],
                'billing_address'      => $this->jsonAddress($request['user_id'], $request['bill_to']),
                'additional_info'      => $orderData->additional_info,
                'shipping_type'        => $orderData->shipping_type,
                'payment_status'       => $orderData->payment_status,
                'payment_details'      => $orderData->payment_details,
                'grand_total'          => $orderData->grand_total,
                'payable_amount'       => $orderData->payable_amount,
                'payment_discount'     => $orderData->payment_discount,
                'coupon_discount'      => $orderData->coupon_discount,
                'code'                 => $orderData->code,
                'date'                 => $orderData->date,
                'viewed'               => $orderData->viewed,
                'order_from'           => $orderData->order_from,
                'payment_status_viewed'=> $orderData->payment_status_viewed,
                'commission_calculated'=> $orderData->commission_calculated,
                'status'               => $order_status,
                'warehouse_id'         => $owKey,
            ];

            // If BTR (virtual customer/warehouse redirection)
            if (!empty($btrWarehouse)) {
                $splitOrder['sub_order_id'] = $sub_order_id;
                $splitOrder['type']         = 'btr';
                $splitOrder['transport_remarks'] = $request->input('btr_transport_remarks_' . $owName);
            } elseif (empty($btrWarehouse) && !empty($btrTransportName)) {
                // Only transport provided -> still sub_order, but with transport data
                $splitOrder['type']               = 'sub_order';
                $splitOrder['sub_order_id']       = $sub_order_id;
                $splitOrder['other_details']      = "";
                $splitOrder['transport_id']       = $request->input('btr_transport_id_' . $owKey);
                $splitOrder['transport_table_id'] = $request->input('btr_transport_table_id_' . $owKey);
                $splitOrder['transport_name']     = $btrTransportName;
                $splitOrder['transport_phone']    = $request->input('btr_transport_mobile_' . $owKey);
                $splitOrder['transport_remarks']  = $request->input('btr_transport_remarks_' . $owKey);
            } else {
                // Normal sub_order
                $splitOrder['type'] = 'sub_order';
            }

            // If BTR, change sub_order user to branch virtual customer details
            if (!empty($btrWarehouse)) {
                if ($homeBranch[$homeWarehouseId] == 'Kolkata') {
                    $branchUser = User::find(27091);
                    $address_id = 5202;
                } elseif ($homeBranch[$homeWarehouseId] == 'Delhi') {
                    $branchUser = User::find(27093);
                    $address_id = 5205;
                } elseif ($homeBranch[$homeWarehouseId] == 'Mumbai') {
                    $branchUser = User::find(27092);
                    $address_id = 5204;
                } else {
                    $branchUser = $userDetails;
                    $address_id = $request['ship_to'];
                }

                $splitOrder['sub_order_user_name'] = $branchUser->company_name;
                $splitOrder['user_id']             = $branchUser->id;
                $splitOrder['shipping_address_id'] = $address_id;
                $splitOrder['shipping_address']    = $this->jsonAddress($branchUser->id, $address_id);
                $splitOrder['billing_address_id']  = $address_id;
                $splitOrder['billing_address']     = $this->jsonAddress($branchUser->id, $address_id);
            } else {
                $splitOrder['sub_order_user_name'] = $userDetails->name;
            }

            // Insert only if at least one line has qty for this warehouse
            $shouldInsert = false;
            foreach ($order_details_id_array as $odValue) {
                $whName   = $warehouseData->name;
                $quantity = $request->input($whName . '_allocate_qty_' . $odValue);
                if (!empty($quantity) && (int)$quantity > 0) {
                    $shouldInsert = true;
                    break;
                }
            }

            if ($shouldInsert) {
                $otherSubOrder = Manager41SubOrder::create($splitOrder);
                $other_sub_order_id = $otherSubOrder->id;

                // Rewards update for this warehouse
                $logisticRewards = $request['rewards_'.$owKey] ?? null;
                $existingReward  = RewardUser::where('user_id', $request['user_id'])
                                        ->where('warehouse_id', $owKey)->first();

                if ($existingReward) {
                    $existingReward->rewards_percentage = $logisticRewards;
                    $existingReward->save();
                } elseif ($logisticRewards !== null && $logisticRewards !== '') {
                    RewardUser::create([
                        'user_id'            => $userDetails->id,
                        'company_name'       => $userDetails->company_name,
                        'party_code'         => $userDetails->party_code,
                        'assigned_warehouse' => optional($userDetails->user_warehouse)->name,
                        'city'               => $userDetails->city,
                        'warehouse_id'       => $owKey,
                        'warehouse_name'     => $warehouseData->name,
                        'preference'         => ((float)$logisticRewards < 0 ? 1 : 0),
                        'rewards_percentage' => $logisticRewards,
                    ]);
                }

                // Insert details for this warehouse
                foreach ($order_details_id_array as $odValue) {
                    $whName   = $warehouseData->name;
                    $quantity = (int) $request->input($whName . '_allocate_qty_' . $odValue);

                    if (!empty($quantity) && $quantity > 0) {
                        $orderDetailsData = Manager41OrderDetail::with('product')->where('id', $odValue)->first();

                        $splitOrderDetails = [
                            'order_id'               => $orderDetailsData->order_id,
                            'order_type'             => $orderDetailsData->order_type ?? null,
                            'seller_id'              => $orderDetailsData->seller_id,
                            'og_product_warehouse_id'=> $orderDetailsData->og_product_warehouse_id,
                            'product_warehouse_id'   => $orderDetailsData->product_warehouse_id,
                            'product_id'             => $orderDetailsData->product_id,
                            'variation'              => $orderDetailsData->variation,
                            'shipping_cost'          => $orderDetailsData->shipping_cost,
                            'quantity'               => $orderDetailsData->quantity,
                            'payment_status'         => $orderDetailsData->payment_status,
                            'delivery_status'        => $orderDetailsData->delivery_status,
                            'shipping_type'          => $orderDetailsData->shipping_type,
                            'earn_point'             => $orderDetailsData->earn_point,
                            'cash_and_carry_item'    => $orderDetailsData->cash_and_carry_item,
                            'applied_offer_id'       => $orderDetailsData->applied_offer_id,
                            'complementary_item'     => $orderDetailsData->complementary_item,
                            'offer_rewards'          => $orderDetailsData->offer_rewards,
                            'remarks'                => $request['remark_'.$odValue] ?? null,
                        ];

                        $closingStocksData = DB::table('products_api')
                            ->where('part_no', optional($orderDetailsData->product)->part_no)
                            ->where('godown', $whName)->first();
                        $closingStock = $closingStocksData ? (int)$closingStocksData->closing_stock : 0;

                        $orderType = (!empty($btrWarehouse)) ? 'btr' : 'sub_order';

                        $detailToCreate = array_merge($splitOrderDetails, [
                            'price'            => ($orderDetailsData->price / max(1, (int)$orderDetailsData->quantity)),
                            'tax'              => (($orderDetailsData->price / max(1, (int)$orderDetailsData->quantity)) * 0.18),
                            'sub_order_id'     => $other_sub_order_id,
                            'order_details_id' => $odValue,
                            'challan_quantity' => null,
                            'approved_quantity'=> $quantity,
                            'closing_qty'      => $closingStock,
                            'approved_rate'    => $request['rate_'.$odValue] ?? null,
                            'warehouse_id'     => $owKey,
                            'type'             => $orderType,
                        ]);

                        $createdDetail = Manager41SubOrderDetail::create($detailToCreate);

                        // Update regret qty in Manager-41 order detail
                        $originalQty = (int) $orderDetailsData->quantity;
                        if (($originalQty - $quantity) > 0) {
                            $orderDetailsData->regret_qty = $originalQty - $quantity;
                        }
                        $orderDetailsData->save();

                        // If BTR, update/increment home-branch sub-order detail in-transit for the same product
                        if ($orderType === 'btr' && $sub_order_id) {
                            $homeBranchDetail = Manager41SubOrderDetail::where('sub_order_id', $sub_order_id)
                                ->where('product_id', $orderDetailsData->product_id)
                                ->where('warehouse_id', $homeWarehouseId)
                                ->first();

                            if ($homeBranchDetail) {
                                $homeBranchDetail->approved_quantity = (int)$homeBranchDetail->approved_quantity + $quantity;
                                $homeBranchDetail->in_transit        = (int)$homeBranchDetail->in_transit + $quantity;
                                $homeBranchDetail->save();
                            } else {
                                // Create a mirror row on home-branch marking in_transit
                                $mirror = $detailToCreate;
                                $mirror['sub_order_id'] = $sub_order_id;
                                $mirror['type']         = 'sub_order';
                                $mirror['in_transit']   = $quantity;
                                $mirror['warehouse_id'] = $homeWarehouseId;

                                Manager41SubOrderDetail::create($mirror);
                            }
                        }
                    }
                }
            }
        }

        // If draft -> back to listing; if add_new_product -> go to add flow
        if ($request['order_status'] === 'draft') {
            return redirect()->route('all_orders.index')->send();
        } elseif ($request['order_status'] === 'add_new_product') {
            $encOrderId = encrypt($request->order_id);
            return redirect()->route('products.quickorder', [
                'order_id'    => $encOrderId,
                'redirect_url'=> $redirect_url
            ])->send();
        }

       

        

        return redirect()->route($redirect_url)->send();

    } catch (\Exception $e) {
        $errorCode    = $e->getCode();
        $errorMessage = $e->getMessage();
        return ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }
}


public function saveSplitOrder(Request $request){

    // If current logged-in user is manager_41, use the Manager-41 save flow
    if ($this->isActingAs41Manager()) {
        return $this->manager41SaveSplitOrder($request);
    }

    try{
        $order_id     = $request->order_id;
        $redirect_url = $request->redirect_url;
        // $redirect_url = "all_orders.index";

        // If suborders already exist, purge and reset related detail/link tables
        $getLastRecordCount = SubOrder::where('order_id',$order_id)->count();
        if($getLastRecordCount > 0){
            SubOrder::where('order_id',$order_id)->delete();

            // Step 1: Get all sub_order_detail IDs for the order
            $subOrderDetailIds = SubOrderDetail::where('order_id', $order_id)->pluck('id');

            // Step 2: Delete related PurchaseBag records
            PurchaseBag::whereIn('sub_order_details_id', $subOrderDetailIds)->delete();

            // Step 3: Delete SubOrderDetail records
            SubOrderDetail::whereIn('id', $subOrderDetailIds)->delete();
        }

        $order_details_id_array = explode(',',$request->order_details_id);

        // Basic validations (BTR/transporter and verification) if not draft
        if($request->order_status == "completed"){
            foreach($order_details_id_array as $odKey => $odValue) {
                $kolkataQty = $request->input('Kolkata_allocate_qty_' . $odValue);
                $delhiQty   = $request->input('Delhi_allocate_qty_' . $odValue);
                $mumbaiQty  = $request->input('Mumbai_allocate_qty_' . $odValue);

                if($request->warehouse_id != 1 AND (!isset($request->btr_warehouse_1) AND $request->btr_transport_id_1 == "" AND $kolkataQty != "")){
                    return redirect()->back()->withInput()->with('error', 'Please enter the transport value for Kolkata.');
                }

                if($request->warehouse_id != 2 AND (!isset($request->btr_warehouse_2) AND $request->btr_transport_id_2 == "" AND $delhiQty != "")){
                    return redirect()->back()->withInput()->with('error', 'Please enter the transport value for Delhi.');
                }

                if($request->warehouse_id != 6 AND (!isset($request->btr_warehouse_6) AND $request->btr_transport_id_6 == "" AND $mumbaiQty != "")){
                    return redirect()->back()->withInput()->with('error', 'Please enter the transport value for Mumbai.');
                }
            }
            if(!isset($request->btr_verification)){
                return redirect()->back()->withInput()->with('error', 'Please check BTR Varification.');
            }
        }
        

        $userDetails = User::where('id',$request['user_id'])->first();
        $orderData   = Order::with('orderDetails','user')->where('id',$request->order_id)->first();
        // Seperate home wareHouse and other wareHouse
        $homwBranchId = $request->warehouse_id;
        $allWareHouse = Warehouse::where('active','1')->get();
        $other_warehouse = array();
        $home_branch     = array();

        foreach($allWareHouse as $awKey){
            if($awKey->id != $homwBranchId){
                $other_warehouse[$awKey->id] = $awKey->name;
            }else{
                $home_branch[$awKey->id] = $awKey->name;
            }
        }

        // Set btr order flag (if any other branch is marked for BTR)
        $btrOrderFlag = 0;
        foreach($other_warehouse as $owKey=>$owValue){
            $btrWarehouse     = $request->input('btr_warehouse_' . $owKey);
            $btrTransportName = $request->input('btr_transport_name_' . $owKey);
            if (!empty($btrWarehouse) AND $btrOrderFlag == 0) {
                $btrOrderFlag = 1;
            }
        }

        // For Home Branch: decide if insertion is needed (qty>0 OR BTR exists)
        $homeBranchDataInsertFlag = 0;
        foreach($home_branch as $hbKey=>$hbValue){
            foreach($order_details_id_array as $odKey => $odValue) {
                $branchQty = $request->input($hbValue.'_allocate_qty_' . $odValue);
                if($branchQty > 0 OR $btrOrderFlag > 0){
                    $homeBranchDataInsertFlag = 1;
                }
            }
        }

        // Insert data for home branch
        $sub_order_id = null;
        $order_status = ($request['order_status'] == 'completed') ? 'completed' : 'draft';

        if($homeBranchDataInsertFlag == 1){
            // Generate Order No for home branch
            $warehouseData  = Warehouse::where('id',$homwBranchId)->first();
            $getLastRecord  = SubOrder::where('warehouse_id',$homwBranchId)->where('status','completed')->orderBy('id','DESC')->first();
            $lastOrderNo    = array();
            if($getLastRecord != NULL){
                $lastOrderNo = explode('/',$getLastRecord->order_no);
                $secondLastElement = $lastOrderNo[count($lastOrderNo) - 2];
                $number = $secondLastElement+1;
            }else{
                $number = 1;
            }
            if($this->getFinancialYear() != end($lastOrderNo)){
                $number = 1;
            }
            $order_no = "";
            if($request['order_status'] == 'completed'){
                $order_no = 'SO/'.strtoupper(substr($warehouseData->name,0,3)).'/'.str_pad($number, 6, '0', STR_PAD_LEFT).'/'.$this->getFinancialYear();
            }

            // SubOrder data (home)
            $splitOrder = array();
            $splitOrder['order_id']               = $request['order_id'];
            $splitOrder['combined_order_id']      = $request['combined_order_id'];
            $splitOrder['order_no']               = $order_no;
            $splitOrder['user_id']                = $request['user_id'];
            $splitOrder['seller_id']              = $orderData->seller_id;
            $splitOrder['shipping_address_id']    = $request['ship_to'];
            $splitOrder['shipping_address']       = $this->jsonAddress($request['user_id'], $request['ship_to']);
            $splitOrder['billing_address_id']     = $request['bill_to'];
            $splitOrder['billing_address']        = $this->jsonAddress($request['user_id'], $request['bill_to']);
            $splitOrder['additional_info']        = $orderData->additional_info;
            $splitOrder['shipping_type']          = $orderData->shipping_type;
            $splitOrder['payment_status']         = $orderData->payment_status;
            $splitOrder['payment_details']        = $orderData->payment_details;
            $splitOrder['grand_total']            = $orderData->grand_total;
            $splitOrder['payable_amount']         = $orderData->payable_amount;
            $splitOrder['payment_discount']       = $orderData->payment_discount;
            $splitOrder['coupon_discount']        = $orderData->coupon_discount;
            $splitOrder['code']                   = $orderData->code;
            $splitOrder['date']                   = $orderData->date;
            $splitOrder['viewed']                 = $orderData->viewed;
            $splitOrder['order_from']             = $orderData->order_from;
            $splitOrder['payment_status_viewed']  = $orderData->payment_status_viewed;
            $splitOrder['commission_calculated']  = $orderData->commission_calculated;
            $splitOrder['status']                 = $order_status;
            $splitOrder['warehouse_id']           = $homwBranchId;
            $splitOrder['sub_order_user_name']    = $userDetails->name;
            $splitOrder['early_payment_check']    = $request->has('early_payment_check') ? 1 : 0;
            $splitOrder['conveince_fee_payment_check']    = $request->has('conveince_fee_payment_check') ? 1 : 0;
            

            // Transport info for home (if given)
            $btrSplitOrder = array();
            $btrSplitOrder['type']               = 'sub_order';
            $btrSplitOrder['transport_remarks']  = $request['btr_transport_remarks_'.$homwBranchId] ?? null;
            $btrSplitOrder['transport_id']       = $request->input('btr_transport_id_'.$homwBranchId);
            $btrSplitOrder['transport_table_id'] = $request->input('btr_transport_table_id_'.$homwBranchId);
            $btrSplitOrder['transport_name']     = $request->input('btr_transport_name_'.$homwBranchId);
            $btrSplitOrder['transport_phone']    = $request->input('btr_transport_mobile_'.$homwBranchId);
            $btrSplitOrder['transport_remarks']  = $request->input('btr_transport_remarks_'.$homwBranchId);

            $splitOrderArray = array_merge($splitOrder,$btrSplitOrder);
            if($splitOrderArray['type'] == 'sub_order' AND $splitOrder['conveince_fee_payment_check'] == 1){
                $splitOrderArray['conveince_fee_percentage'] = $orderData->conveince_fee_percentage;
            }
            // Create home SubOrder
            $homeBranchOrder = SubOrder::create($splitOrderArray);

            $orderData->sub_order_status = 1;
            $orderData->update();

            $sub_order_id    = $homeBranchOrder->id;

            // [WARRANTY] flag accumulator for this SubOrder (home)
            $homeHasWarranty = 0;

            // Create SubOrderDetails for home
            foreach($order_details_id_array as $odKey => $odValue) {
                $orderDetailsData = OrderDetail::with('product')->where('id',$odValue)->first();

                $splitOrderDetails = array();
                $splitOrderDetails['order_id']               = $orderDetailsData->order_id;
                $splitOrderDetails['order_type']             = $orderDetailsData->order_type;
                $splitOrderDetails['seller_id']              = $orderDetailsData->seller_id;
                $splitOrderDetails['og_product_warehouse_id']= $orderDetailsData->og_product_warehouse_id;
                $splitOrderDetails['product_warehouse_id']   = $orderDetailsData->product_warehouse_id;
                $splitOrderDetails['product_id']             = $orderDetailsData->product_id;
                $splitOrderDetails['variation']              = $orderDetailsData->variation;
                $splitOrderDetails['shipping_cost']          = $orderDetailsData->shipping_cost;
                $splitOrderDetails['quantity']               = $orderDetailsData->quantity;
                $splitOrderDetails['payment_status']         = $orderDetailsData->payment_status;
                $splitOrderDetails['delivery_status']        = $orderDetailsData->delivery_status;
                $splitOrderDetails['shipping_type']          = $orderDetailsData->shipping_type;
                $splitOrderDetails['pickup_point_id']        = $orderDetailsData->pickup_point_id;
                $splitOrderDetails['product_referral_code']  = $orderDetailsData->product_referral_code;
                $splitOrderDetails['earn_point']             = $orderDetailsData->earn_point;
                $splitOrderDetails['cash_and_carry_item']    = $orderDetailsData->cash_and_carry_item;
                $splitOrderDetails['applied_offer_id']       = $orderDetailsData->applied_offer_id;
                $splitOrderDetails['complementary_item']     = $orderDetailsData->complementary_item;
                $splitOrderDetails['offer_rewards']          = $orderDetailsData->offer_rewards;
                $splitOrderDetails['remarks']                = $request['remark_'.$odValue] ?? null;

                $wareHouseData = Warehouse::where('id',$homwBranchId)->first();
                $quantity      = $request->input($wareHouseData->name.'_allocate_qty_' . $odValue);

                $closingStocksData = DB::table('products_api')
                    ->where('part_no', $orderDetailsData->product->part_no)
                    ->where('godown',  $wareHouseData->name)
                    ->first();
                $closingStock = $closingStocksData ? (int)$closingStocksData->closing_stock : 0;

                if($quantity > 0){
                    $btrSplitOrderDetails = array();
                    $btrSplitOrderDetails['price']             = ($orderDetailsData->price / $orderDetailsData->quantity);
                    $btrSplitOrderDetails['tax']               = (($orderDetailsData->price / $orderDetailsData->quantity) * 0.18 );
                    $btrSplitOrderDetails['sub_order_id']      = $sub_order_id;
                    $btrSplitOrderDetails['order_details_id']  = $odValue;
                    $btrSplitOrderDetails['challan_quantity']  = "";
                    $btrSplitOrderDetails['pre_close_quantity']= "";
                    $btrSplitOrderDetails['approved_quantity'] = $quantity;
                    $btrSplitOrderDetails['closing_qty']       = $closingStock;
                    $btrSplitOrderDetails['approved_rate']     = $request['rate_'.$odValue];
                    $btrSplitOrderDetails['warehouse_id']      = $homwBranchId;
                    $btrSplitOrderDetails['type']              = 'sub_order';

                    // [WARRANTY] capture per-line from form checkbox for home branch
                    $btrSplitOrderDetails['is_warranty'] = (int)$request->input('warranty_'.$odValue, 0);

                    $btrSplitOrderArray     = array_merge($splitOrderDetails,$btrSplitOrderDetails);

                    if($btrSplitOrderArray['type'] == 'sub_order' AND $splitOrder['conveince_fee_payment_check'] == 1){
                        $btrSplitOrderArray['conveince_fee_percentage'] = $orderData->conveince_fee_percentage;
                        $btrSplitOrderArray['conveince_fees'] = (($btrSplitOrderArray['approved_rate'] * $quantity) * $orderData->conveince_fee_percentage) / 100;
                    }

                    $homeBranchOrderDetails = SubOrderDetail::create($btrSplitOrderArray);

                    // [WARRANTY] roll-up flag if any line has warranty=1
                    if (!empty($btrSplitOrderDetails['is_warranty'])) {
                        $homeHasWarranty = 1;
                    }

                    // Negative stock reset seed
                    $getResetProductData = ResetProduct::where('product_id',$orderDetailsData->product_id)->first();
                    if($getResetProductData == NULL){
                        $productData = Product::where('id',$orderDetailsData->product_id)->first();
                        $resetProduct = array();
                        $resetProduct['product_id'] = $orderDetailsData->product_id;
                        $resetProduct['part_no']    = $productData->part_no;
                        ResetProduct::create($resetProduct);
                    }

                    // Update regret qty
                    if(($orderDetailsData->quantity - $quantity) > 0 ){
                        $orderDetailsData->regret_qty = $orderDetailsData->quantity - $quantity;
                    }
                    $orderDetailsData->save();

                    // Negative stock entry if required
                    if($closingStock < $quantity AND $order_status == 'completed' AND $order_no != ""){
                        $requestSubmit = new \Illuminate\Http\Request();
                        $requestSubmit->merge([
                            'order_no'            => $homeBranchOrder->order_no,
                            'sub_order_details_id'=> $homeBranchOrderDetails->id
                        ]);
                        $this->negativeStockEntry($requestSubmit);
                    }
                }
                if($homeHasWarranty == 1){
                    $warranty_duration = $request->input('warranty_duration_'.$odValue, 0);
                    if($warranty_duration > 0){
                        $this->updateWarrantyForProduct($orderDetailsData->product_id, $warranty_duration);
                    }                        
                }
            }

            // [WARRANTY] set SubOrder.is_warranty for HOME (1 if any detail had warranty)
            $homeBranchOrder->update(['is_warranty' => $homeHasWarranty]);

            // Save / Update Logistic rewards for home
            $logicticRewards     = $request['rewards_'.$homwBranchId];
            $getLogisticRewards  = RewardUser::where('user_id',$request['user_id'])->where('warehouse_id',$homwBranchId)->first();
            if($getLogisticRewards !== null){
                $getLogisticRewards->rewards_percentage = $logicticRewards;
                $getLogisticRewards->save();
            }elseif($logicticRewards != ""){
                $getLogisticRewardsArray = array();
                $getLogisticRewardsArray['user_id']          = $userDetails->id;
                $getLogisticRewardsArray['company_name']     = $userDetails->company_name;
                $getLogisticRewardsArray['party_code']       = $userDetails->party_code;
                $getLogisticRewardsArray['assigned_warehouse']= $userDetails->user_warehouse->name;
                $getLogisticRewardsArray['city']             = $userDetails->city;
                $getLogisticRewardsArray['warehouse_id']     = $homwBranchId;
                $getLogisticRewardsArray['warehouse_name']   = $warehouseData->name;
                $getLogisticRewardsArray['preference']       = $logicticRewards<0?1:0;
                $getLogisticRewardsArray['rewards_percentage']= $logicticRewards;
                RewardUser::create($getLogisticRewardsArray);
            }
        }
        
        // Insert data for other branches
        foreach($other_warehouse as $owKey=>$owValue){
            $btrOrderFlag    = 0;
            $btrWarehouse    = $request->input('btr_warehouse_' . $owKey);
            $btrTransportName= $request->input('btr_transport_name_' . $owKey);
            
            if (!empty($btrWarehouse) AND $btrOrderFlag == 0) {
                $btrOrderFlag = 1;
            }

            // Generate Order No for other branch
            $number       = "";
            $order_no     = "";
            $warehouseData= Warehouse::where('id',$owKey)->first();
            $getLastRecord= SubOrder::where('warehouse_id',$owKey)->where('status','completed')->orderBy('id','DESC')->first();
            if($getLastRecord != NULL){
                $lastOrderNo = explode('/',$getLastRecord->order_no);
                $secondLastElement = $lastOrderNo[count($lastOrderNo) - 2];
                $number = $secondLastElement+1;
            }else{
                $number = 1;
            }
            if($request['order_status'] == 'completed'){
                $order_no = 'SO/'.strtoupper(substr($warehouseData->name,0,3)).'/'.str_pad($number, 6, '0', STR_PAD_LEFT).'/'.$this->getFinancialYear();
            }
            
            // Default as normal sub_order
            $orderType   = 'sub_order';
            $splitOrder  = array();
            $splitOrder['order_id']               = $request['order_id'];
            $splitOrder['combined_order_id']      = $request['combined_order_id'];
            $splitOrder['order_no']               = $order_no;
            $splitOrder['user_id']                = $request['user_id'];
            $splitOrder['seller_id']              = $orderData->seller_id;
            $splitOrder['shipping_address_id']    = $request['ship_to'];
            $splitOrder['shipping_address']       = $this->jsonAddress($request['user_id'], $request['ship_to']);
            $splitOrder['billing_address_id']     = $request['bill_to'];
            $splitOrder['billing_address']        = $this->jsonAddress($request['user_id'], $request['bill_to']);
            $splitOrder['additional_info']        = $orderData->additional_info;
            $splitOrder['shipping_type']          = $orderData->shipping_type;
            $splitOrder['payment_status']         = $orderData->payment_status;
            $splitOrder['payment_details']        = $orderData->payment_details;
            $splitOrder['grand_total']            = $orderData->grand_total;
            $splitOrder['payable_amount']         = $orderData->payable_amount;
            $splitOrder['payment_discount']       = $orderData->payment_discount;
            $splitOrder['coupon_discount']        = $orderData->coupon_discount;
            $splitOrder['code']                   = $orderData->code;
            $splitOrder['date']                   = $orderData->date;
            $splitOrder['viewed']                 = $orderData->viewed;
            $splitOrder['order_from']             = $orderData->order_from;
            $splitOrder['payment_status_viewed']  = $orderData->payment_status_viewed;
            $splitOrder['commission_calculated']  = $orderData->commission_calculated;
            $splitOrder['status']                 = $order_status;
            $splitOrder['warehouse_id']           = $owKey;
            $splitOrder['conveince_fee_payment_check']    = $request->has('conveince_fee_payment_check') ? 1 : 0;
            
            // If BTR for this other branch, replace customer with branch-user
            if($btrOrderFlag == 0){
                $splitOrder['sub_order_user_name'] = $userDetails->name;
            }else{
                if($home_branch[$homwBranchId] == 'Kolkata'){
                    $branchUserDetails = User::where('id', 27091)->first();
                    $address_id = 5202;
                }elseif($home_branch[$homwBranchId] == 'Delhi'){
                    $branchUserDetails = User::where('id', 27093)->first();
                    $address_id = 5205;
                }elseif($home_branch[$homwBranchId] == 'Mumbai'){
                    $branchUserDetails = User::where('id', 27092)->first();
                    $address_id = 5204;
                }
                $splitOrder['sub_order_user_name']  = $branchUserDetails->company_name;
                $splitOrder['user_id']              = $branchUserDetails->id;
                $splitOrder['shipping_address_id']  = $address_id;
                $splitOrder['shipping_address']     = $this->jsonAddress($branchUserDetails->id, $address_id);
                $splitOrder['billing_address_id']   = $address_id;
                $splitOrder['billing_address']      = $this->jsonAddress($branchUserDetails->id, $address_id);
            }
            
            $btrSplitOrder = array();
            if (!empty($btrWarehouse)) {
                // Mark as BTR suborder
                $btrSplitOrder['sub_order_id']     = $sub_order_id;
                $btrSplitOrder['type']             = 'btr';
                $btrSplitOrder['transport_remarks']= $request->input('btr_transport_remarks_' . $owValue);
                $orderType = 'btr';
            } elseif (empty($btrWarehouse) && !empty($btrTransportName)) {
                // Normal sub_order but with transport data
                $btrSplitOrder['type']               = 'sub_order';
                $btrSplitOrder['sub_order_id']       = $sub_order_id;
                $btrSplitOrder['warehouse_id']       = $owKey;
                $btrSplitOrder['other_details']      = "";
                $btrSplitOrder['status']             = $order_status;
                $btrSplitOrder['transport_id']       = $request->input('btr_transport_id_' . $owKey);
                $btrSplitOrder['transport_table_id'] = $request->input('btr_transport_table_id_' . $owKey);
                $btrSplitOrder['transport_name']     = $btrTransportName;
                $btrSplitOrder['transport_phone']    = $request->input('btr_transport_mobile_' . $owKey);
                $btrSplitOrder['transport_remarks']  = $request->input('btr_transport_remarks_' . $owKey);
                $orderType = 'sub_order';
            }else{
                $splitOrder['type'] = "sub_order";
            }
            
            // Will we insert suborder for this branch? (any qty?)
            $subOrderInsertFlag = 0;
            foreach($order_details_id_array as $odKey => $odValue) {
                $wareHouseData = Warehouse::where('id',$owKey)->first();
                $quantity      = $request->input($wareHouseData->name.'_allocate_qty_' . $odValue);
                if($quantity != "" AND $quantity > 0){
                    $subOrderInsertFlag = 1;
                    break;
                }
            }
            
            $wareHouseData = Warehouse::where('id',$owKey)->first();
            
            if($subOrderInsertFlag == 1){
                
                $splitOrderArray   = array_merge($splitOrder,$btrSplitOrder);
                
                if($splitOrderArray['type'] == 'sub_order' AND $splitOrder['conveince_fee_payment_check'] == 1){
                    $splitOrderArray['conveince_fee_percentage'] = $orderData->conveince_fee_percentage;
                }
                // echo "<pre>"; print_r($splitOrder);die;
                $otherBranchOrder  = SubOrder::create($splitOrderArray);
                $other_branch_sub_order_id = $otherBranchOrder->id;

                $orderData->sub_order_status = 1;
                $orderData->update();
                
                // [WARRANTY] flag accumulator for this other branch SubOrder
                $otherHasWarranty = 0;

                // Update logistic rewards for branch
                $logicticRewards    = $request['rewards_'.$owKey];
                $getLogisticRewards = RewardUser::where('user_id',$request['user_id'])->where('warehouse_id',$owKey)->first();
                if($getLogisticRewards !== null){
                    $getLogisticRewards->rewards_percentage = $logicticRewards;
                    $getLogisticRewards->save();
                }elseif($logicticRewards != ""){
                    $getLogisticRewardsArray = array();
                    $getLogisticRewardsArray['user_id']           = $userDetails->id;
                    $getLogisticRewardsArray['company_name']      = $userDetails->company_name;
                    $getLogisticRewardsArray['party_code']        = $userDetails->party_code;
                    $getLogisticRewardsArray['assigned_warehouse']= $userDetails->user_warehouse->name;
                    $getLogisticRewardsArray['city']              = $userDetails->city;
                    $getLogisticRewardsArray['warehouse_id']      = $owKey;
                    $getLogisticRewardsArray['warehouse_name']    = $warehouseData->name;
                    $getLogisticRewardsArray['preference']        = $logicticRewards<0?1:0;
                    $getLogisticRewardsArray['rewards_percentage']= $logicticRewards;
                    RewardUser::create($getLogisticRewardsArray);
                }

                // Create SubOrderDetails for this branch
                foreach($order_details_id_array as $odKey => $odValue) {
                    $wareHouseData = Warehouse::where('id',$owKey)->first();
                    $quantity      = $request->input($wareHouseData->name.'_allocate_qty_' . $odValue);
                    if($quantity != "" AND $quantity > 0){
                        $orderDetailsData = OrderDetail::with('product')->where('id',$odValue)->first();

                        $splitOrderDetails = array();
                        $splitOrderDetails['order_id']               = $orderDetailsData->order_id;
                        $splitOrderDetails['order_type']             = $orderDetailsData->order_type;
                        $splitOrderDetails['seller_id']              = $orderDetailsData->seller_id;
                        $splitOrderDetails['og_product_warehouse_id']= $orderDetailsData->og_product_warehouse_id;
                        $splitOrderDetails['product_warehouse_id']   = $orderDetailsData->product_warehouse_id;
                        $splitOrderDetails['product_id']             = $orderDetailsData->product_id;
                        $splitOrderDetails['variation']              = $orderDetailsData->variation;
                        $splitOrderDetails['shipping_cost']          = $orderDetailsData->shipping_cost;
                        $splitOrderDetails['quantity']               = $orderDetailsData->quantity;
                        $splitOrderDetails['payment_status']         = $orderDetailsData->payment_status;
                        $splitOrderDetails['delivery_status']        = $orderDetailsData->delivery_status;
                        $splitOrderDetails['shipping_type']          = $orderDetailsData->shipping_type;
                        $splitOrderDetails['pickup_point_id']        = $orderDetailsData->pickup_point_id;
                        $splitOrderDetails['product_referral_code']  = $orderDetailsData->product_referral_code;
                        $splitOrderDetails['earn_point']             = $orderDetailsData->earn_point;
                        $splitOrderDetails['cash_and_carry_item']    = $orderDetailsData->cash_and_carry_item;
                        $splitOrderDetails['applied_offer_id']       = $orderDetailsData->applied_offer_id;
                        $splitOrderDetails['complementary_item']     = $orderDetailsData->complementary_item;
                        $splitOrderDetails['offer_rewards']          = $orderDetailsData->offer_rewards;
                        $splitOrderDetails['remarks']                = $request['remark_'.$odValue] ?? null;

                        $closingStocksData = DB::table('products_api')
                            ->where('part_no', $orderDetailsData->product->part_no)
                            ->where('godown',  $wareHouseData->name)
                            ->first();
                        $closingStock = $closingStocksData ? (int)$closingStocksData->closing_stock : 0;

                        $btrSplitOrderDetails = array();
                        $btrSplitOrderDetails['price']             = ($orderDetailsData->price / $orderDetailsData->quantity);
                        $btrSplitOrderDetails['tax']               = (($orderDetailsData->price / $orderDetailsData->quantity) * 0.18 );
                        $btrSplitOrderDetails['sub_order_id']      = $other_branch_sub_order_id;
                        $btrSplitOrderDetails['order_details_id']  = $odValue;
                        $btrSplitOrderDetails['challan_quantity']  = "";
                        $btrSplitOrderDetails['pre_close_quantity']= "";
                        $btrSplitOrderDetails['approved_quantity'] = $quantity;
                        $btrSplitOrderDetails['closing_qty']       = $closingStock;
                        $btrSplitOrderDetails['approved_rate']     = $request['rate_'.$odValue];
                        $btrSplitOrderDetails['warehouse_id']      = $owKey;
                        $btrSplitOrderDetails['type']              = $orderType;

                        // [WARRANTY] Only for normal sub_order lines (NOT for BTR)
                        if ($orderType === 'sub_order') {
                            $btrSplitOrderDetails['is_warranty'] = (int)$request->input('warranty_'.$odValue, 0);
                        }

                        $btrSplitOrderArray = array_merge($splitOrderDetails,$btrSplitOrderDetails);

                        if($btrSplitOrderArray['type'] == 'sub_order' AND $splitOrder['conveince_fee_payment_check'] == 1){
                            $btrSplitOrderArray['conveince_fee_percentage'] = $orderData->conveince_fee_percentage;
                            $btrSplitOrderArray['conveince_fees'] = (($btrSplitOrderArray['approved_rate'] * $quantity) * $orderData->conveince_fee_percentage) / 100;
                        }
                        $createdDetail      = SubOrderDetail::create($btrSplitOrderArray);

                        // [WARRANTY] roll-up for this suborder (only if orderType is sub_order)
                        if (($orderType === 'sub_order') && !empty($btrSplitOrderDetails['is_warranty'])) {
                            $otherHasWarranty = 1;
                        }

                        // Negative stock reset seed
                        $getResetProductData = ResetProduct::where('product_id',$orderDetailsData->product_id)->first();
                        if($getResetProductData == NULL){
                            $productData = Product::where('id',$orderDetailsData->product_id)->first();
                            $resetProduct = array();
                            $resetProduct['product_id'] = $orderDetailsData->product_id;
                            $resetProduct['part_no']    = $productData->part_no;
                            ResetProduct::create($resetProduct);
                        }

                        // Update regret qty on main order detail
                        if(($orderDetailsData->quantity - $quantity) > 0 ){
                            $orderDetailsData->regret_qty = $orderDetailsData->quantity - $quantity;
                        }
                        $orderDetailsData->save();

                        // Negative stock entry if required
                        if($closingStock < $quantity AND $order_status == 'completed' AND $order_no != ""){
                            $requestSubmit = new \Illuminate\Http\Request();
                            $requestSubmit->merge([
                                'order_no'             => $otherBranchOrder->order_no,
                                'sub_order_details_id' => $createdDetail->id
                            ]);
                            $this->negativeStockEntry($requestSubmit);
                        }

                        // If BTR line created, add/update mirror on home branch (no warranty here)
                        if($orderType == 'btr'){
                            $getHomeBranchSubOrderDetails = SubOrderDetail::where('sub_order_id',$sub_order_id)
                                ->where('product_id',$orderDetailsData->product_id)
                                ->where('warehouse_id',$homwBranchId)
                                ->first();

                            if(isset($getHomeBranchSubOrderDetails->approved_quantity)){
                                $getHomeBranchSubOrderDetails->approved_quantity = $getHomeBranchSubOrderDetails->approved_quantity + $quantity;
                                $getHomeBranchSubOrderDetails->in_transit        = $getHomeBranchSubOrderDetails->in_transit + $quantity;
                                // [WARRANTY] never mark warranty on this mirror line
                                $getHomeBranchSubOrderDetails->is_warranty       = 0;
                                $getHomeBranchSubOrderDetails->save();
                            }else{
                                // Build from the same array but force mirror props
                                $btrSplitOrderArray['sub_order_id'] = $sub_order_id;
                                $btrSplitOrderArray['type']         = 'sub_order';
                                $btrSplitOrderArray['in_transit']   = $quantity;
                                $btrSplitOrderArray['warehouse_id'] = $homwBranchId;
                                // [WARRANTY] ensure not set on mirror
                                $btrSplitOrderArray['is_warranty']  = 0;

                                $homeBranchOrderMirror = SubOrderDetail::create($btrSplitOrderArray);

                                // Negative stock reset seed for mirror
                                $getResetProductData = ResetProduct::where('product_id',$orderDetailsData->product_id)->first();
                                if($getResetProductData == NULL){
                                    $productData = Product::where('id',$orderDetailsData->product_id)->first();
                                    $resetProduct = array();
                                    $resetProduct['product_id'] = $orderDetailsData->product_id;
                                    $resetProduct['part_no']    = $productData->part_no;
                                    ResetProduct::create($resetProduct);
                                }
                            }
                        }
                        if($otherHasWarranty == 1){
                            $warranty_duration = $request->input('warranty_duration_'.$odValue, 0);
                            if($warranty_duration > 0){
                                $this->updateWarrantyForProduct($orderDetailsData->product_id, $warranty_duration);
                            }                        
                        }
                    }
                    
                }

                // [WARRANTY] Set SubOrder.is_warranty for OTHER BRANCH
                // Only if it's a normal sub_order; for BTR there is "no warranty logic"
                if ($orderType === 'sub_order') {
                    $otherBranchOrder->update(['is_warranty' => $otherHasWarranty]);
                }
            }
            // echo "<br/>..";print_r($wareHouseData);
        }
        // echo ".aa.".$mumbaiQty; die;
        if($request['order_status'] == 'draft'){
            return redirect()->route('all_orders.index')->send();
        }elseif($request['order_status'] == 'add_new_product'){
            $order_id = encrypt($request->order_id);
            return redirect()->route('products.quickorder', ['order_id' => $order_id,'redirect_url' => $redirect_url])->send();
        }

        $response = ['res' => true, 'msg' => "Successfully insert data."];

        // whatsapp part start
        $pdfUrl = $this->generateApprovalPDF($request->order_id);
        $this->sendApprovedProductWhatsApp($request->order_id, $pdfUrl);
        // whatsapp part end

        // GST & Zoho customer update part start
        try {
            $shippingAddress = Address::find($request['ship_to']);

            if ($shippingAddress && $shippingAddress->gstin) {
                $gstResponse = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->post('https://appyflow.in/api/verifyGST', [
                    'key_secret' => env('APPYFLOW_KEYSECRET'),
                    'gstNo' => $shippingAddress->gstin,
                ]);

                $isGstValid = false;

                if ($gstResponse->successful()) {
                    $gstData = json_decode($gstResponse->body(), true);
                    if (isset($gstData['taxpayerInfo']['gstin']) && $gstData['taxpayerInfo']['sts'] === 'Active') {
                        $isGstValid = true;
                    }
                }

                if (!$isGstValid && $shippingAddress->zoho_customer_id) {
                    $zohoController = new ZohoController();
                    $zohoController->updateCustomerInZoho($shippingAddress->zoho_customer_id);
                }
            } else {
                if ($shippingAddress && $shippingAddress->zoho_customer_id) {
                    $zohoController = new ZohoController();
                    $zohoController->updateCustomerInZoho($shippingAddress->zoho_customer_id);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Zoho GST Validation or Customer Update Error: '.$e->getMessage());
        }
        // GST & Zoho customer update part end
        
        return redirect()->route($redirect_url)->send();

    } catch (\Exception $e) {
        $errorCode    = $e->getCode();
        $errorMessage = $e->getMessage();
        $response     = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }
    return $response;
}

public function updateWarrantyForProduct($product_id, $warranty_duration){
    $selectProduct = Product::where('id', $product_id)->first();
    $selectProduct->warranty_duration = $warranty_duration;
    $selectProduct->is_warranty = '1';
    $selectProduct->save();
    return true;
}

  // public function saveSplitOrder(Request $request){

  //     // If current logged-in user is manager_41, use the Manager-41 save flow
  //     if ($this->isActingAs41Manager()) {
  //         return $this->manager41SaveSplitOrder($request);
  //     }

  //     try{
  //       $order_id = $request->order_id;
  //       $redirect_url = $request->redirect_url;
  //       // echo "<pre>"; print_r($request->all()); die;
  //       $getLastRecordCount = SubOrder::where('order_id',$order_id)->count();
  //       if($getLastRecordCount > 0){
  //         SubOrder::where('order_id',$order_id)->delete();

  //         // Step 1: Get all sub_order_detail IDs for the order
  //         $subOrderDetailIds = SubOrderDetail::where('order_id', $order_id)->pluck('id');

  //         // Step 2: Delete related PurchaseBag records
  //         PurchaseBag::whereIn('sub_order_details_id', $subOrderDetailIds)->delete();

  //         // Step 3: Delete SubOrderDetail records
  //         SubOrderDetail::whereIn('id', $subOrderDetailIds)->delete();

  //       }
        
  //       $order_details_id_array = explode(',',$request->order_details_id);
  //       if($request->order_status != "draft"){
  //         foreach($order_details_id_array as $odKey => $odValue) {
  //             $kolkataQty = $request->input('Kolkata_allocate_qty_' . $odValue);
  //             $delhiQty   = $request->input('Delhi_allocate_qty_' . $odValue);
  //             $mumbaiQty  = $request->input('Mumbai_allocate_qty_' . $odValue);

  //             // Check if all fields are empty
  //             // if(empty($kolkataQty) && empty($delhiQty) && empty($mumbaiQty)) {
  //             //     $productDetails = Product::find($request->input('product_id_' . $odValue));
  //             //     return redirect()->back()->withInput()->with('error', 'Please enter allocate quantity for ' . $productDetails->name);
  //             // }

  //             if($request->warehouse_id != 1 AND (!isset($request->btr_warehouse_1) AND $request->btr_transport_id_1 == "" AND $kolkataQty != "")){
  //               return redirect()->back()->withInput()->with('error', 'Please enter the transport value for Kolkata.');
  //             }

  //             if($request->warehouse_id != 2 AND (!isset($request->btr_warehouse_2) AND $request->btr_transport_id_2 == "" AND $delhiQty != "")){
  //               return redirect()->back()->withInput()->with('error', 'Please enter the transport value for Delhi.');
  //             }

  //             if($request->warehouse_id != 6 AND (!isset($request->btr_warehouse_6) AND $request->btr_transport_id_6 == "" AND $mumbaiQty != "")){
  //               return redirect()->back()->withInput()->with('error', 'Please enter the transport value for Mumbai.');
  //             }
  //         }
  //         if(!isset($request->btr_verification)){
  //           return redirect()->back()->withInput()->with('error', 'Please check BTR Varification.');
  //         }
  //       }
  //       $userDetails = User::where('id',$request['user_id'])->first();
  //       $orderData = Order::with('orderDetails','user')->where('id',$request->order_id)->first();
        
  //       // Seperate home wareHouse and other wareHouse
  //       $homwBranchId = $request->warehouse_id;
  //       $allWareHouse = Warehouse::where('active','1')->get();
  //       $other_warehouse = array();
  //       $home_branch = array();
  //       foreach($allWareHouse as $awKey){
  //         if($awKey->id != $homwBranchId){
  //           $other_warehouse[$awKey->id]=$awKey->name;
  //         }else{
  //           $home_branch[$awKey->id]=$awKey->name;          
  //         }
  //       }
  //       // echo "<pre>"; print_r($home_branch);print_r($other_warehouse);die;

  //       // Set btr order flag.
  //       $btrOrderFlag = 0;
  //       foreach($other_warehouse as $owKey=>$owValue){
  //         $btrWarehouse = $request->input('btr_warehouse_' . $owKey);
  //         $btrTransportName = $request->input('btr_transport_name_' . $owKey);            
  //         if (!empty($btrWarehouse) AND $btrOrderFlag == 0) { // Check if warehouse field is not empty
  //           $btrOrderFlag = 1;
  //         }
  //       }
  //       // For Home Branch
  //       $homeBranchDataInsertFlag = 0;
  //       $btrFlag = 0;
  //       foreach($home_branch as $hbKey=>$hbValue){
  //         foreach($order_details_id_array as $odKey => $odValue) {
  //           $branchQty   = $request->input($hbValue.'_allocate_qty_' . $odValue);
  //           if($branchQty > 0 OR $btrOrderFlag > 0){
  //             $homeBranchDataInsertFlag = 1;
  //           }
  //         }
  //       }
        
  //       // Insert data for home branch
  //       $sub_order_id = null;
  //       $order_status = 'draft';
  //       if($request['order_status'] == 'completed'){
  //         $order_status = 'completed';
  //       }
        
  //       if($homeBranchDataInsertFlag == 1){
  //         // Generate Order No
  //         $warehouseData = Warehouse::where('id',$homwBranchId)->first();
  //         $getLastRecord = SubOrder::where('warehouse_id',$homwBranchId)->where('status','completed')->orderBy('id','DESC')->first();
  //         $lastOrderNo=array();
  //         if($getLastRecord != NULL){
  //           $lastOrderNo = explode('/',$getLastRecord->order_no);
  //           $secondLastElement = $lastOrderNo[count($lastOrderNo) - 2];
  //           $number = $secondLastElement+1;
  //         }else{
  //           $number = 1;
  //         }
  //         if($this->getFinancialYear() != end($lastOrderNo)){
  //           $number = 1;
  //         }
  //         $order_no = "";
  //         if($request['order_status'] == 'completed'){
  //             $order_no = 'SO/'.strtoupper(substr($warehouseData->name,0,3)).'/'.str_pad($number, 6, '0', STR_PAD_LEFT).'/'.$this->getFinancialYear();
  //         }

  //         $splitOrder = array();
  //         $splitOrder['order_id'] = $request['order_id'];
  //         $splitOrder['combined_order_id'] = $request['combined_order_id'];
  //         $splitOrder['order_no'] = $order_no;
  //         $splitOrder['user_id'] = $request['user_id'];
  //         $splitOrder['seller_id'] = $orderData->seller_id;
  //         $splitOrder['shipping_address_id'] = $request['ship_to'];
  //         $splitOrder['shipping_address'] = $this->jsonAddress($request['user_id'], $request['ship_to']);
  //         $splitOrder['billing_address_id'] = $request['bill_to'];
  //         $splitOrder['billing_address'] = $this->jsonAddress($request['user_id'], $request['bill_to']);
  //         $splitOrder['additional_info'] = $orderData->additional_info;
  //         $splitOrder['shipping_type'] = $orderData->shipping_type;
  //         $splitOrder['payment_status'] = $orderData->payment_status;
  //         $splitOrder['payment_details'] = $orderData->payment_details;
  //         $splitOrder['grand_total'] = $orderData->grand_total;
  //         $splitOrder['payable_amount'] = $orderData->payable_amount;
  //         $splitOrder['payment_discount'] = $orderData->payment_discount;
  //         $splitOrder['coupon_discount'] = $orderData->coupon_discount;
  //         $splitOrder['code'] = $orderData->code;
  //         $splitOrder['date'] = $orderData->date;
  //         $splitOrder['viewed'] = $orderData->viewed;
  //         $splitOrder['order_from'] = $orderData->order_from;
  //         $splitOrder['payment_status_viewed'] = $orderData->payment_status_viewed;
  //         $splitOrder['commission_calculated'] = $orderData->commission_calculated;
  //         $splitOrder['status'] = $order_status;
  //         $splitOrder['warehouse_id'] = $homwBranchId;
  //         $splitOrder['sub_order_user_name'] = $userDetails->name;
  //         $splitOrder['early_payment_check'] = $request->has('early_payment_check') ? 1 : 0;

  //         $btrSplitOrder = array();
  //         $btrSplitOrder['type'] = 'sub_order';
  //         $btrSplitOrder['transport_remarks'] = $request['btr_transport_remarks_'.$homwBranchId];
  //         $btrSplitOrder['transport_id'] = $request->input('btr_transport_id_'.$homwBranchId);
  //         $btrSplitOrder['transport_table_id'] = $request->input('btr_transport_table_id_'.$homwBranchId);
  //         $btrSplitOrder['transport_name'] = $request->input('btr_transport_name_'.$homwBranchId);
  //         $btrSplitOrder['transport_phone'] = $request->input('btr_transport_mobile_'.$homwBranchId);
  //         $btrSplitOrder['transport_remarks'] = $request->input('btr_transport_remarks_'.$homwBranchId);
  //         $splitOrderArray = array_merge($splitOrder,$btrSplitOrder);

  //         // $logistic = array();
  //         // $logistic['user_id']=$userDetails->id;
  //         // $logistic['company_name']=$userDetails->company_name;
  //         // $logistic['party_code']=$userDetails->party_code;
  //         // $logistic['assigned_warehouse']="";
  //         // $logistic['city']=$userDetails->city;
  //         // $logistic['warehouse_id']=$homwBranchId;
  //         // $logistic['warehouse_name']=$warehouseData->name;
  //         // $logistic['preference']="";
  //         // $logistic['rewards_percentage']=$request['rewards_'.$homwBranchId];

          


  //         // echo "<pre>"; print_r($homeBranchDataInsertFlag); die;
  //         // Insert for home branch
  //         $homeBranchOrder = SubOrder::create($splitOrderArray);
  //         $sub_order_id = $homeBranchOrder->id;

  //         foreach($order_details_id_array as $odKey => $odValue) {
  //           $orderDetailsData = OrderDetail::with('product')->where('id',$odValue)->first();
            
  //           // echo "<pre>";echo $odValue; print_r($order_details_id_array); print_r($orderDetailsData);print_r($request->all()); die;
  //           $splitOrderDetails = array();
  //           $splitOrderDetails['order_id'] = $orderDetailsData->order_id;
  //           $splitOrderDetails['order_type'] = $orderDetailsData->order_type;
  //           $splitOrderDetails['seller_id'] = $orderDetailsData->seller_id;
  //           $splitOrderDetails['og_product_warehouse_id'] = $orderDetailsData->og_product_warehouse_id;
  //           $splitOrderDetails['product_warehouse_id'] = $orderDetailsData->product_warehouse_id;
  //           $splitOrderDetails['product_id'] = $orderDetailsData->product_id;
  //           $splitOrderDetails['variation'] = $orderDetailsData->variation;
  //           // $splitOrderDetails['price'] = $orderDetailsData->price;          
  //           $splitOrderDetails['shipping_cost'] = $orderDetailsData->shipping_cost;
  //           $splitOrderDetails['quantity'] = $orderDetailsData->quantity;
  //           $splitOrderDetails['payment_status'] = $orderDetailsData->payment_status;
  //           $splitOrderDetails['delivery_status'] = $orderDetailsData->delivery_status;
  //           $splitOrderDetails['shipping_type'] = $orderDetailsData->shipping_type;
  //           $splitOrderDetails['pickup_point_id'] = $orderDetailsData->pickup_point_id;
  //           $splitOrderDetails['product_referral_code'] = $orderDetailsData->product_referral_code;
  //           $splitOrderDetails['earn_point'] = $orderDetailsData->earn_point;
  //           $splitOrderDetails['cash_and_carry_item'] = $orderDetailsData->cash_and_carry_item;
  //           // $splitOrderDetails['new_item'] = $orderDetailsData->$new_item;
  //           $splitOrderDetails['applied_offer_id'] = $orderDetailsData->applied_offer_id;
  //           $splitOrderDetails['complementary_item'] = $orderDetailsData->complementary_item;
  //           $splitOrderDetails['offer_rewards'] = $orderDetailsData->offer_rewards;
  //           $splitOrderDetails['remarks'] = $request['remark_'.$odValue];

  //           // -------------------------------------- Split Order Details ------------------------------------------
  //           $wareHouseData = Warehouse::where('id',$homwBranchId)->first();
  //           $quantity = $request->input($wareHouseData->name.'_allocate_qty_' . $odValue);

  //           $closingStocksData = DB::table('products_api')->where('part_no', $orderDetailsData->product->part_no)->where('godown', $wareHouseData->name)->first();
  //           $closingStock = $closingStocksData ? (int)$closingStocksData->closing_stock : 0;

  //           if($quantity > 0){
  //             $btrSplitOrderDetails = array();
  //             $btrSplitOrderDetails['price'] = ($orderDetailsData->price / $orderDetailsData->quantity);
  //             $btrSplitOrderDetails['tax'] = (($orderDetailsData->price / $orderDetailsData->quantity) * 0.18 );
  //             $btrSplitOrderDetails['sub_order_id'] = $sub_order_id;
  //             $btrSplitOrderDetails['order_details_id'] = $odValue;
  //             $btrSplitOrderDetails['challan_quantity'] = "";
  //             $btrSplitOrderDetails['pre_close_quantity'] = "";
  //             $btrSplitOrderDetails['approved_quantity'] = $quantity;
  //             $btrSplitOrderDetails['closing_qty'] = $closingStock;
  //             $btrSplitOrderDetails['approved_rate'] = $request['rate_'.$odValue];
  //             $btrSplitOrderDetails['warehouse_id'] = $homwBranchId;
  //             $btrSplitOrderDetails['type'] = 'sub_order';

  //             $btrSplitOrderArray = array_merge($splitOrderDetails,$btrSplitOrderDetails);
  //             // echo "<pre>"; print_r($btrSplitOrderArray); die;
  //             $homeBranchOrderDetails = SubOrderDetail::create($btrSplitOrderArray);

  //             // This entry for negative stock reset
  //             $getResetProductData = ResetProduct::where('product_id',$orderDetailsData->product_id)->first();
  //             if($getResetProductData == NULL){
  //               $productData = Product::where('id',$orderDetailsData->product_id)->first();
  //               $resetProduct = array();
  //               $resetProduct['product_id'] = $orderDetailsData->product_id;
  //               $resetProduct['part_no'] = $productData->part_no;
  //               ResetProduct::create($resetProduct);
  //             }

  //             // Insert regrate qty into order details
  //             if(($orderDetailsData->quantity - $quantity) > 0 ){
  //               $orderDetailsData->regret_qty = $orderDetailsData->quantity - $quantity;
  //             } 
  //             $orderDetailsData->save();
              
  //             if($closingStock < $quantity AND $order_status == 'completed' AND $order_no != ""){
  //               $requestSubmit = new \Illuminate\Http\Request();
  //               $requestSubmit->merge([
  //                   'order_no' => $homeBranchOrder->order_no,
  //                   'sub_order_details_id' => $homeBranchOrderDetails->id
  //               ]);              
  //               $this->negativeStockEntry($requestSubmit);
  //             }
  //           }                    
  //         }

  //         $logicticRewards = $request['rewards_'.$homwBranchId];
  //         $getLogisticRewards = RewardUser::where('user_id',$request['user_id'])->where('warehouse_id',$homwBranchId)->first();
  //         // if($getLogisticRewards !== null){
  //         if($getLogisticRewards !== null){
  //             $getLogisticRewards->rewards_percentage = $logicticRewards;
  //             $getLogisticRewards->save();
  //         }elseif($logicticRewards != ""){
  //           $getLogisticRewardsArray = array();
  //           $getLogisticRewardsArray['user_id'] = $userDetails->id;
  //           $getLogisticRewardsArray['company_name'] = $userDetails->company_name;
  //           $getLogisticRewardsArray['party_code'] = $userDetails->party_code;
  //           $getLogisticRewardsArray['assigned_warehouse'] = $userDetails->user_warehouse->name;
  //           $getLogisticRewardsArray['city'] = $userDetails->city;
  //           $getLogisticRewardsArray['warehouse_id'] = $homwBranchId;
  //           $getLogisticRewardsArray['warehouse_name'] = $warehouseData->name;
  //           $getLogisticRewardsArray['preference'] = $logicticRewards<0?1:0;
  //           $getLogisticRewardsArray['rewards_percentage'] = $logicticRewards;
  //           RewardUser::create($getLogisticRewardsArray);
  //         }
  //       }
        
  //       // Insert data for other branch
  //       $homeBranchDataInsertFlag = 0;
  //       $btrFlag = 0;
  //       foreach($other_warehouse as $owKey=>$owValue){
  //         $btrOrderFlag = 0;
  //         $btrWarehouse = $request->input('btr_warehouse_' . $owKey);
  //         $btrTransportName = $request->input('btr_transport_name_' . $owKey);            
  //         if (!empty($btrWarehouse) AND $btrOrderFlag == 0) { // Check if warehouse field is not empty
  //           $btrOrderFlag = 1;
  //         }
          
  //         // Generate Order No
  //         $number="";
  //         $order_no="";
  //         $warehouseData = Warehouse::where('id',$owKey)->first();
  //         $getLastRecord = SubOrder::where('warehouse_id',$owKey)->where('status','completed')->orderBy('id','DESC')->first();
  //         // echo "<pre>"; print_r($getLastRecord);
  //         if($getLastRecord != NULL){
  //           $lastOrderNo = explode('/',$getLastRecord->order_no);
  //           $secondLastElement = $lastOrderNo[count($lastOrderNo) - 2];
  //           $number = $secondLastElement+1;
  //         }else{
  //           $number = 1;
  //         }
  //         // echo $getLastRecord->order_no;echo $homeBranchDataInsertFlag. "Hello"; die;
  //         // if($this->getFinancialYear() != end($lastOrderNo)){
  //         //   $number = 1;
  //         // }
          
  //         $order_no = "";
  //         if($request['order_status'] == 'completed'){
  //           $order_no = 'SO/'.strtoupper(substr($warehouseData->name,0,3)).'/'.str_pad($number, 6, '0', STR_PAD_LEFT).'/'.$this->getFinancialYear();
  //         }
  //         $orderType = 'sub_order';
  //         $splitOrder = array();
  //         $splitOrder['order_id'] = $request['order_id'];
  //         $splitOrder['combined_order_id'] = $request['combined_order_id'];
  //         $splitOrder['order_no'] = $order_no;
  //         $splitOrder['user_id'] = $request['user_id'];
  //         $splitOrder['seller_id'] = $orderData->seller_id;
  //         $splitOrder['shipping_address_id'] = $request['ship_to'];
  //         $splitOrder['shipping_address'] = $this->jsonAddress($request['user_id'], $request['ship_to']);
  //         $splitOrder['billing_address_id'] = $request['bill_to'];
  //         $splitOrder['billing_address'] = $this->jsonAddress($request['user_id'], $request['bill_to']);
  //         $splitOrder['additional_info'] = $orderData->additional_info;
  //         $splitOrder['shipping_type'] = $orderData->shipping_type;
  //         $splitOrder['payment_status'] = $orderData->payment_status;
  //         $splitOrder['payment_details'] = $orderData->payment_details;
  //         $splitOrder['grand_total'] = $orderData->grand_total;
  //         $splitOrder['payable_amount'] = $orderData->payable_amount;
  //         $splitOrder['payment_discount'] = $orderData->payment_discount;
  //         $splitOrder['coupon_discount'] = $orderData->coupon_discount;
  //         $splitOrder['code'] = $orderData->code;
  //         $splitOrder['date'] = $orderData->date;
  //         $splitOrder['viewed'] = $orderData->viewed;
  //         $splitOrder['order_from'] = $orderData->order_from;
  //         $splitOrder['payment_status_viewed'] = $orderData->payment_status_viewed;
  //         $splitOrder['commission_calculated'] = $orderData->commission_calculated;
  //         $splitOrder['status'] = $order_status;
  //         $splitOrder['warehouse_id'] = $owKey;
  //         $btrSplitOrder = array();
  //         if($btrOrderFlag == 0){
  //           $splitOrder['sub_order_user_name'] = $userDetails->name;
  //         }else{
  //           if($home_branch[$homwBranchId] == 'Kolkata'){
  //             $branchUserDetails = User::where('id', 27091)->first();
  //             $address_id = 5202;
  //           }elseif($home_branch[$homwBranchId] == 'Delhi'){
  //             $branchUserDetails = User::where('id', 27093)->first();
  //             $address_id = 5205;
  //           }elseif($home_branch[$homwBranchId] == 'Mumbai'){
  //             $branchUserDetails = User::where('id', 27092)->first();
  //             $address_id = 5204;
  //           }
  //           $splitOrder['sub_order_user_name'] = $branchUserDetails->company_name;;
  //           $splitOrder['user_id'] = $branchUserDetails->id;
  //           $splitOrder['shipping_address_id'] = $address_id;
  //           $splitOrder['shipping_address'] = $this->jsonAddress($branchUserDetails->id, $address_id);
  //           $splitOrder['billing_address_id'] = $address_id;
  //           $splitOrder['billing_address'] = $this->jsonAddress($branchUserDetails->id, $address_id);
  //         }

  //         if (!empty($btrWarehouse)) { // Check if warehouse field is not empty
  //           $btrSplitOrder['sub_order_id'] = $sub_order_id;
  //           $btrSplitOrder['type'] = 'btr';
  //           $btrSplitOrder['transport_remarks'] = $request->input('btr_transport_remarks_' . $owValue);
  //           // echo "<pre>"; print_r($btrSplitOrder);die;
  //           $orderType = 'btr';
  //         } elseif (empty($btrWarehouse) && !empty($btrTransportName)) { // Check transport name when warehouse is empty
  //           $btrSplitOrder['type'] = 'sub_order';
  //           $btrSplitOrder['sub_order_id'] = $sub_order_id;
  //           $btrSplitOrder['warehouse_id'] = $owKey;
  //           $btrSplitOrder['other_details'] = "";
  //           $btrSplitOrder['status'] = $order_status;
  //           $btrSplitOrder['transport_id'] = $request->input('btr_transport_id_' . $owKey);
  //           $btrSplitOrder['transport_table_id'] = $request->input('btr_transport_table_id_' . $owKey);
  //           $btrSplitOrder['transport_name'] = $btrTransportName;
  //           $btrSplitOrder['transport_phone'] = $request->input('btr_transport_mobile_' . $owKey);
  //           $btrSplitOrder['transport_remarks'] = $request->input('btr_transport_remarks_' . $owKey);
  //           $orderType = 'sub_order';
  //         }
  //         $subOrderInsertFlag = 0;
  //         foreach($order_details_id_array as $odKey => $odValue) {
  //           $wareHouseData = Warehouse::where('id',$owKey)->first();
  //           $quantity = $request->input($wareHouseData->name.'_allocate_qty_' . $odValue);
  //           if($quantity != "" AND $quantity > 0){
  //             $subOrderInsertFlag = 1;
  //             break;
  //           }
  //         }
  //         $wareHouseData = Warehouse::where('id',$owKey)->first();
  //         // echo $subOrderInsertFlag; die;
  //         if($subOrderInsertFlag == 1){
  //           $splitOrderArray = array_merge($splitOrder,$btrSplitOrder);
            
  //           $otherBranchOrder = SubOrder::create($splitOrderArray);

  //           $other_branch_sub_order_id = $otherBranchOrder->id;

  //           // Update logistic rewards
  //           $logicticRewards = $request['rewards_'.$owKey];
  //           $getLogisticRewards = RewardUser::where('user_id',$request['user_id'])->where('warehouse_id',$owKey)->first();
  //           // echo "<pre>"; echo $owKey; print_r($getLogisticRewards); die;
  //           if($getLogisticRewards !== null){
  //           // if(isset($getLogisticRewards->warehouse_name)){  
  //               $getLogisticRewards->rewards_percentage = $logicticRewards;
  //               $getLogisticRewards->save();
  //           }elseif($logicticRewards != ""){
  //             $getLogisticRewardsArray = array();
  //             $getLogisticRewardsArray['user_id'] = $userDetails->id;
  //             $getLogisticRewardsArray['company_name'] = $userDetails->company_name;
  //             $getLogisticRewardsArray['party_code'] = $userDetails->party_code;
  //             $getLogisticRewardsArray['assigned_warehouse'] = $userDetails->user_warehouse->name;
  //             $getLogisticRewardsArray['city'] = $userDetails->city;
  //             $getLogisticRewardsArray['warehouse_id'] = $owKey;
  //             $getLogisticRewardsArray['warehouse_name'] = $warehouseData->name;
  //             $getLogisticRewardsArray['preference'] = $logicticRewards<0?1:0;
  //             $getLogisticRewardsArray['rewards_percentage'] = $logicticRewards;
  //             RewardUser::create($getLogisticRewardsArray);
  //           }

  //           foreach($order_details_id_array as $odKey => $odValue) {
  //             $wareHouseData = Warehouse::where('id',$owKey)->first();
  //             $quantity = $request->input($wareHouseData->name.'_allocate_qty_' . $odValue);
  //             if($quantity != "" AND $quantity > 0){
  //               $orderDetailsData = OrderDetail::with('product')->where('id',$odValue)->first();
  //               $splitOrderDetails = array();
  //               $splitOrderDetails['order_id'] = $orderDetailsData->order_id;
  //               $splitOrderDetails['order_type'] = $orderDetailsData->order_type;
  //               $splitOrderDetails['seller_id'] = $orderDetailsData->seller_id;
  //               $splitOrderDetails['og_product_warehouse_id'] = $orderDetailsData->og_product_warehouse_id;
  //               $splitOrderDetails['product_warehouse_id'] = $orderDetailsData->product_warehouse_id;
  //               $splitOrderDetails['product_id'] = $orderDetailsData->product_id;
  //               $splitOrderDetails['variation'] = $orderDetailsData->variation;
  //               // $splitOrderDetails['price'] = $orderDetailsData->price;
  //               // $splitOrderDetails['tax'] = $orderDetailsData->tax;
  //               $splitOrderDetails['shipping_cost'] = $orderDetailsData->shipping_cost;
  //               $splitOrderDetails['quantity'] = $orderDetailsData->quantity;
  //               $splitOrderDetails['payment_status'] = $orderDetailsData->payment_status;
  //               $splitOrderDetails['delivery_status'] = $orderDetailsData->delivery_status;
  //               $splitOrderDetails['shipping_type'] = $orderDetailsData->shipping_type;
  //               $splitOrderDetails['pickup_point_id'] = $orderDetailsData->pickup_point_id;
  //               $splitOrderDetails['product_referral_code'] = $orderDetailsData->product_referral_code;
  //               $splitOrderDetails['earn_point'] = $orderDetailsData->earn_point;
  //               $splitOrderDetails['cash_and_carry_item'] = $orderDetailsData->cash_and_carry_item;
  //               // $splitOrderDetails['new_item'] = $orderDetailsData->$new_item;
  //               $splitOrderDetails['applied_offer_id'] = $orderDetailsData->applied_offer_id;
  //               $splitOrderDetails['complementary_item'] = $orderDetailsData->complementary_item;
  //               $splitOrderDetails['offer_rewards'] = $orderDetailsData->offer_rewards;
  //               $splitOrderDetails['remarks'] = $request['remark_'.$odValue];

  //               // -------------------------------------- Split Order Details ------------------------------------------ 
                
  //               $closingStocksData = DB::table('products_api')->where('part_no', $orderDetailsData->product->part_no)->where('godown', $wareHouseData->name)->first();
  //               $closingStock = $closingStocksData ? (int)$closingStocksData->closing_stock : 0;

  //               $btrSplitOrderDetails = array();
  //               $btrSplitOrderDetails['price'] = ($orderDetailsData->price / $orderDetailsData->quantity);
  //               $btrSplitOrderDetails['tax'] = (($orderDetailsData->price / $orderDetailsData->quantity) * 0.18 );
  //               $btrSplitOrderDetails['sub_order_id'] = $other_branch_sub_order_id;
  //               $btrSplitOrderDetails['order_details_id'] = $odValue;
  //               $btrSplitOrderDetails['challan_quantity'] = "";
  //               $btrSplitOrderDetails['pre_close_quantity'] = "";
  //               $btrSplitOrderDetails['approved_quantity'] = $quantity;
  //               $btrSplitOrderDetails['closing_qty'] = $closingStock;
  //               $btrSplitOrderDetails['approved_rate'] = $request['rate_'.$odValue];
  //               $btrSplitOrderDetails['warehouse_id'] = $owKey;
  //               $btrSplitOrderDetails['type'] = $orderType;
  //               $btrSplitOrderArray = array_merge($splitOrderDetails,$btrSplitOrderDetails);
  //               $homeBranchOrder = SubOrderDetail::create($btrSplitOrderArray);

  //               // This entry for negative stock reset
  //               $getResetProductData = ResetProduct::where('product_id',$orderDetailsData->product_id)->first();
  //               if($getResetProductData == NULL){
  //                 $productData = Product::where('id',$orderDetailsData->product_id)->first();
  //                 $resetProduct = array();
  //                 $resetProduct['product_id'] = $orderDetailsData->product_id;
  //                 $resetProduct['part_no'] = $productData->part_no;
  //                 ResetProduct::create($resetProduct);
  //               }

  //               // Insert regrate qty into order details
  //               if(($orderDetailsData->quantity - $quantity) > 0 ){
  //                 $orderDetailsData->regret_qty = $orderDetailsData->quantity - $quantity;
  //               } 
  //               $orderDetailsData->save();

  //               if($closingStock < $quantity AND $order_status == 'completed' AND $order_no != ""){
  //                 $requestSubmit = new \Illuminate\Http\Request();
  //                 $requestSubmit->merge([
  //                     'order_no' => $otherBranchOrder->order_no,
  //                     'sub_order_details_id' => $homeBranchOrder->id
  //                 ]);
  //                 $this->negativeStockEntry($requestSubmit);
  //               }
  //               if($orderType == 'btr'){
  //                 // echo $sub_order_id;
  //                 $getHomeBranchSubOrderDetails = SubOrderDetail::where('sub_order_id',$sub_order_id)->where('product_id',$orderDetailsData->product_id)->where('warehouse_id',$homwBranchId)->first();
  //                 // echo "<pre>"; print_r($getHomeBranchSubOrderDetails); die;
  //                 if(isset($getHomeBranchSubOrderDetails->approved_quantity)){
  //                   $getHomeBranchSubOrderDetails->approved_quantity = $getHomeBranchSubOrderDetails->approved_quantity + $quantity;
  //                   $getHomeBranchSubOrderDetails->in_transit = $getHomeBranchSubOrderDetails->in_transit + $quantity;
  //                   $getHomeBranchSubOrderDetails->save();
  //                 }else{
  //                   $btrSplitOrderArray['sub_order_id'] = $sub_order_id;
  //                   $btrSplitOrderArray['type'] = 'sub_order';                  
  //                   $btrSplitOrderArray['in_transit'] =  $quantity;
  //                   $btrSplitOrderArray['warehouse_id'] =  $homwBranchId;
  //                   $homeBranchOrder = SubOrderDetail::create($btrSplitOrderArray);

  //                   // This entry for negative stock reset
  //                   $getResetProductData = ResetProduct::where('product_id',$orderDetailsData->product_id)->first();
  //                   if($getResetProductData == NULL){
  //                     $productData = Product::where('id',$orderDetailsData->product_id)->first();
  //                     $resetProduct = array();
  //                     $resetProduct['product_id'] = $orderDetailsData->product_id;
  //                     $resetProduct['part_no'] = $productData->part_no;
  //                     ResetProduct::create($resetProduct);
  //                   }
  //                 }
  //               }
  //             }          
  //           }
  //         }
  //       }
        
  //       if($request['order_status'] == 'draft'){
  //         return redirect()->route('all_orders.index')->send();
  //       }elseif($request['order_status'] == 'add_new_product'){
  //         $order_id =  encrypt($request->order_id);
  //         return redirect()->route('products.quickorder', ['order_id' => $order_id,'redirect_url' => $redirect_url])->send();
  //       }

  //       $response = ['res' => true, 'msg' => "Successfully insert data."];

  //       // whatsapp part start
  //       $pdfUrl = $this->generateApprovalPDF($request->order_id);
  //       $this->sendApprovedProductWhatsApp($request->order_id, $pdfUrl);
  //       // whatsapp part end

        
  //       // GST & Zoho customer update part start
  //       try {
  //           $shippingAddress = Address::find($request['ship_to']);

  //           if ($shippingAddress && $shippingAddress->gstin) {
  //               $gstResponse = Http::withHeaders([
  //                   'Content-Type' => 'application/json',
  //               ])->post('https://appyflow.in/api/verifyGST', [
  //                   'key_secret' => env('APPYFLOW_KEYSECRET'),
  //                   'gstNo' => $shippingAddress->gstin,
  //               ]);

  //               $isGstValid = false;

  //               if ($gstResponse->successful()) {
  //                   $gstData = json_decode($gstResponse->body(), true);
  //                   if (isset($gstData['taxpayerInfo']['gstin']) && $gstData['taxpayerInfo']['sts'] === 'Active') {
  //                       $isGstValid = true;
  //                       // $zohoController = new ZohoController();
  //                       // $zohoController->updateCustomerInZoho($shippingAddress->zoho_customer_id);
  //                   }
  //               }

  //               if (!$isGstValid && $shippingAddress->zoho_customer_id) {
  //                   $zohoController = new ZohoController();
  //                   $zohoController->updateCustomerInZoho($shippingAddress->zoho_customer_id);
  //               }
  //           } else {
  //               if ($shippingAddress && $shippingAddress->zoho_customer_id) {
  //                   $zohoController = new ZohoController();
  //                   $zohoController->updateCustomerInZoho($shippingAddress->zoho_customer_id);
  //               }
  //           }
  //       } catch (\Exception $e) {
  //           \Log::error('Zoho GST Validation or Customer Update Error: '.$e->getMessage());
  //       }
  //       // GST & Zoho customer update part end
  //       return redirect()->route($redirect_url)->send();
  //       // $splitOrderData = SubOrder::create($splitOrder);
  //       // echo "<pre>"; print_r($splitOrder);print_r($request->all());die;
  //     } catch (\Exception $e) {
  //         $errorCode = $e->getCode();
  //         $errorMessage = $e->getMessage();//"something went wrong, please try again";
  //         $response = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
  //     }        
  //     return $response; 
  // }


  public function saveChallan(Request $request){
    
    try{        
      $sub_order_data = SubOrder::where('id',$request['sub_order_id'])->first();      
      $warehouseData = Warehouse::where('name',$request['warehouse'])->first();
      $getLastRecord = Challan::where('warehouse_id',$warehouseData->id)->orderBy('id','DESC')->first();
      $lastOrderNo=array();
      if($getLastRecord != NULL){
        $lastOrderNo = explode('/',$getLastRecord->challan_no);
        $secondLastElement = $lastOrderNo[count($lastOrderNo) - 2];
        $number = $secondLastElement+1;
      }else{
        $number = 1;
      }
      if($this->getFinancialYear() != end($lastOrderNo)){
        $number = 1;
      }
      $challan_no = 'DO/'.strtoupper(substr($warehouseData->name,0,3)).'/'.str_pad($number, 6, '0', STR_PAD_LEFT).'/'.$this->getFinancialYear();

      $part_no_array = explode(',',$request['part_number']);
      $sub_order_detail_id = explode(',',$request['sub_order_detail_id']);
      $grand_total = 0;
      $isnertFlag = 0;
      foreach($part_no_array as $key => $value){
        $grand_total += $request['billed_amount_'.$value];
        if($isnertFlag == 0 AND $request['billed_qty_'.$value] != "" AND $request['billed_qty_'.$value] > 0){
          $isnertFlag = 1;
        }
        $hsncode = $request['hsncode_'.$value];
        if(strlen($hsncode) < 8 AND $request['billed_qty_'.$value] != "" AND $request['billed_qty_'.$value] > 0){
          return redirect()->back()->withInput()->with('error', 'Please update HSN Code for '.$value);
        }
      }
      if($isnertFlag == 0){
        return redirect()->back()->withInput()->with('error', 'Please scan atleast one item for challan submit.');
      }

      if($isnertFlag == 1){
        $challanData = array();
        $challanData['challan_no'] = $challan_no;
        $challanData['challan_date'] = date('Y-m-d');
        $challanData['sub_order_id'] = $request['sub_order_id'];
        $challanData['user_id'] = $request['user_id'];
        $challanData['shipping_address_id'] = $sub_order_data->shipping_address_id;
        $challanData['shipping_address'] = $sub_order_data->shipping_address;
        // $challanData['additional_info'] = $request['additional_info'];
        // $challanData['shipping_type'] = $request['shipping_type'];
        // $challanData['place_of_suply'] = $userDetails->name;
        // $challanData['carrier_id'] = $userDetails->name;
        $challanData['grand_total'] = $grand_total;
        $challanData['warehouse_id'] = $warehouseData->id;
        $challanData['warehouse'] = $warehouseData->name;
        // $challanData['remarks'] = $userDetails->name;
        $challanData['transport_name'] = $request['transport_name'];
        $challanData['transport_id'] = $request['transport_name'];
        $challanData['transport_phone'] = $request['transport_phone'];
        $challanData['early_payment_check'] = $sub_order_data->early_payment_check;
        $challanData['conveince_fee_payment_check'] = $sub_order_data->conveince_fee_payment_check;;
        $challanData['conveince_fee_percentage'] = $sub_order_data->conveince_fee_percentage;
        $challanInsertData = Challan::create($challanData);

        foreach($part_no_array as $key => $value){
          if($request['billed_qty_'.$value] > 0 AND $request['billed_qty_'.$value] != ""){
            $getSubOrderDetailsData = SubOrderDetail::with('product','user')->where('id',$sub_order_detail_id[$key])->first();
            // echo $sub_order_detail_id[$key]."<pre>"; print_r($getSubOrderDetailsData);die;
            $challanDetailsData['challan_id'] = $challanInsertData->id;
            $challanDetailsData['challan_no'] = $challanInsertData->challan_no;
            $challanDetailsData['user_id'] = $request['user_id'];
            $challanDetailsData['product_warehouse_id'] = $getSubOrderDetailsData->product_warehouse_id;
            $challanDetailsData['product_id'] = $getSubOrderDetailsData->product_id;
            $challanDetailsData['tax'] = $getSubOrderDetailsData->tax;
            $challanDetailsData['variation'] = $getSubOrderDetailsData->variation;
            $challanDetailsData['price'] = $getSubOrderDetailsData->price;
            $challanDetailsData['quantity'] = $request['billed_qty_'.$value];
            $challanDetailsData['rate'] = $request['rate_'.$value];
            $challanDetailsData['final_amount'] = $request['billed_amount_'.$value];
            $challanDetailsData['sub_order_id'] = $getSubOrderDetailsData->sub_order_id;
            $challanDetailsData['sub_order_details_id'] = $getSubOrderDetailsData->id;
            $challanDetailsData['conveince_fee_percentage'] = $getSubOrderDetailsData->conveince_fee_percentage;
            $challanDetailsData['conveince_fees'] = $getSubOrderDetailsData->conveince_fees;
            $challanDetailsInsertData = ChallanDetail::create($challanDetailsData);
            $getSubOrderDetailsData->challan_qty = $getSubOrderDetailsData->challan_qty + $request['billed_qty_'.$value];
            $getSubOrderDetailsData->save();

            $requestSubmit = new \Illuminate\Http\Request();
            $requestSubmit->merge([
                'product_id' => $getSubOrderDetailsData->product_id
            ]);              
            $this->inventoryProductEntry($requestSubmit);

          }
        }

        /** Whatsapp code start  */
        try {
            // Generate Dispatch PDF
            $pdfUrl = $this->generateDispatchPDF($challanInsertData->id);
            // Send WhatsApp Notification
            $this->sendDispatchNotification($challanInsertData->id, $pdfUrl);
            
        } catch (\Exception $e) {
            // Handle exceptions
            \Log::error('Dispatch PDF/WhatsApp Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate PDF or send WhatsApp notification.');
        }
        /** Whatsapp code end  */
      }
      
      $response = ['res' => true, 'msg' => "Successfully insert data."];
      // return redirect()->route('order.allChallan')->send();
      return redirect()->back();
      // $splitOrderData = SubOrder::create($splitOrder);
      // echo "<pre>"; print_r($splitOrder);print_r($request->all());die;
    } catch (\Exception $e) {
        $errorCode = $e->getCode();
        if ($errorCode == 23000) {
            $errorMessage = __("direct link already exists");
        } else {
            $errorMessage = $e->getMessage();//"something went wrong, please try again";
        }
        $response = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }        
    return $response; 
  }


  


  public function updateHsncode(Request $request)
  {
      $request->validate([
          'part_no' => 'required',
          'hsncode' => 'required|max:10|min:6'
      ]);
      $product = Product::where('part_no', $request->part_no)->first();
      if ($product) {
          $product->hsncode = $request->hsncode;
          $product->save();
          return response()->json(['success' => true]);
      }
      return response()->json(['success' => false, 'message' => 'Product not found']);
  }

  public function challanDetails($id){
    try{
      $orderData = Challan::with('challan_details','user')->where('id',$id)->first();
      $orderDetails = ChallanDetail::with('product','user')->where('challan_id',$id)->get();       
      $userDetails = $orderData->user;
      // echo "<pre>"; print_r($orderDetails);die;
      $allAddressesForThisUser = $orderData->user->get_addresses;      
      $shippingAddress = $userDetails->get_addresses->where('id', $orderData->address_id)->first();
      
      $allWareHouse = Warehouse::where('active','1')->get();  
      $allTransportData = Carrier::orderBy('name','ASC')->get();

      return view('backend.sales.challan_details', compact('orderData','orderDetails','userDetails','allAddressesForThisUser','shippingAddress','allWareHouse','allTransportData'));

    } catch (\Exception $e) {
        $errorCode = $e->getCode();
        if ($errorCode == 23000) {
            $errorMessage = __("direct link already exists");
        } else {
            $errorMessage = $e->getMessage();//"something went wrong, please try again";
        }
        $response = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }        
    return $response;
  }

  public function allChallan(Request $request){
    try{
      $orderData = Challan::with('challan_details', 'user')
      // ->where(function ($query) {
      //     // Include SubOrders where at least one sub_order_detail has pre_closed_status = 1
      //     $query->whereHas('sub_order_details', function ($query) {
      //         $query->where('pre_closed_status', "1");
      //     });
      // })
      ->orderBy('id', 'DESC')
      ->paginate(20);
      return view('backend.sales.all_challan', compact('orderData'));
    } catch (\Exception $e) {
        $errorCode = $e->getCode();
        if ($errorCode == 23000) {
            $errorMessage = __("direct link already exists");
        } else {
            $errorMessage = $e->getMessage();//"something went wrong, please try again";
        }
        $response = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }        
    return $response;
  }

  public function addCarriers(Request $request){
      try {
          if (!isset($request->name)) {
              return response()->json(['success' => false, 'message' => 'Please enter carrier name.']);
          } elseif (!isset($request->mobile_no)) {
              return response()->json(['success' => false, 'message' => 'Please enter mobile no.']);
          } elseif (!isset($request->gstin)) {
              return response()->json(['success' => false, 'message' => 'Please enter GST no.']);
          } else {
              $getOldDataCount = Carrier::where('gstin', $request->gstin)->count();
              if ($getOldDataCount > 0) {
                  return response()->json(['success' => false, 'message' => 'GST no. already in Carrier list']);
              }

              // Create the new carrier record
              $carrierArray = array();
              $carrierArray['name'] = $request['name'];
              $carrierArray['mobile_no'] = $request['mobile_no'];
              $carrierArray['gstin'] = $request['gstin'];
              $carrierArray['phone_no'] = $request['phone_no'];
              $homeBranchOrder = Carrier::create($carrierArray);

              return response()->json(['success' => true, 'message' => 'Successfully added carrier.', 'new_transport' => $homeBranchOrder]);
          }
      } catch (\Exception $e) {
          $errorCode = $e->getCode();
          $errorMessage = $e->getMessage();

          return response()->json([
              'success' => false,
              'message' => $errorMessage,
              'error_code' => $errorCode
          ]);
      }
  }

  public function subOrderreallocationSplitOrder($sub_order_id){

    try{
        $subOrderDetailsData = SubOrderDetail::with('sub_order_record')->where('sub_order_id',$sub_order_id)->get();
        $order_warehouse = array();
        foreach ($subOrderDetailsData as $orderDetail) {
            $subOrderData = $orderDetail->sub_order_record;
            $userDetails = $orderDetail->sub_order_record->user;
            $allAddressesForThisUser = $orderDetail->sub_order_record->user->get_addresses;      
            $shippingAddress = $userDetails->get_addresses->where('id', $subOrderData->shipping_address_id)->first();
            $order_warehouse = $orderDetail->sub_order_record->order_warehouse;
            $orderDetail->price = $orderDetail->price * $orderDetail->quantity;
        }
        $sub_order_details_id = $sub_order_id;
        
        $allWareHouse = Warehouse::where('active','1')->get();
        $allTransportData = Carrier::orderBy('name','ASC')->get();      
        
        $btrOrderDetails = SubOrder::where('user_id',$subOrderDetailsData[0]->sub_order_record->user_id)->where('status','completed')->orderBy('id','DESC')->get();
        // echo "<pre>"; print_r($subOrderDetailsData->sub_order_record); die;
        return view('backend.sales.sub_order_reallocation_split_order', compact('subOrderDetailsData','subOrderData','userDetails','allAddressesForThisUser','shippingAddress','allWareHouse','allTransportData','sub_order_details_id','order_warehouse','btrOrderDetails'));
    } catch (\Exception $e) {
      $response = ['res' => false, 'msg' => $e->getMessage()];
    }        
    return $response; 
  }
  

  public function reallocationSplitOrder($sub_order_id){
    try{
      $subOrderDetailsData = SubOrderDetail::with('sub_order_record')->where('id',$sub_order_id)->get();
      $order_warehouse = array();
      foreach ($subOrderDetailsData as $orderDetail) {
        $subOrderData = $orderDetail->sub_order_record;
        $userDetails = $orderDetail->sub_order_record->user;
        $allAddressesForThisUser = $orderDetail->sub_order_record  ->user->get_addresses;      
        $shippingAddress = $userDetails->get_addresses->where('id', $subOrderData->shipping_address_id)->first();
        $order_warehouse = $orderDetail->sub_order_record->order_warehouse;
      }
      $sub_order_details_id = $sub_order_id;
      
      $allWareHouse = Warehouse::where('active','1')->get();
      $allTransportData = Carrier::orderBy('name','ASC')->get();      
      // echo "<pre>"; print($subOrderData->address_id); die;
      return view('backend.sales.reallocation_split_order', compact('subOrderDetailsData','subOrderData','userDetails','allAddressesForThisUser','shippingAddress','allWareHouse','allTransportData','sub_order_details_id','order_warehouse'));
    } catch (\Exception $e) {
      $response = ['res' => false, 'msg' => $e->getMessage()];
    }        
    return $response; 
  }

  public function saveReAllocationOrder(Request $request){
    try{
      $order_id = $request->order_id;
      $getMainSplitOrder = SubOrder::where('id',$request->sub_order_id)->first();

      $order_details_id_array = $request->order_details_id;
      // echo "<pre>"; print_r($order_details_id_array);die;
      foreach($order_details_id_array as $odKey => $odValue) {
          $kolkataQty = $request->input('Kolkata_allocate_qty_' . $odValue);
          $delhiQty   = $request->input('Delhi_allocate_qty_' . $odValue);
          $mumbaiQty  = $request->input('Mumbai_allocate_qty_' . $odValue);
          // Check if all fields are empty
          if(empty($kolkataQty) && empty($delhiQty) && empty($mumbaiQty)) {
              $productDetails = Product::find($request->input('product_id_' . $odValue));
              return redirect()->back()->withInput()->with('error', 'Please enter allocate quantity for ' . $productDetails->name);
          }
          if($request->warehouse_id != 1 AND (!isset($request->btr_warehouse_1) AND $request->btr_transport_id_1 == "" AND $kolkataQty != "")){
            return redirect()->back()->withInput()->with('error', 'Please enter the btr value for Kolkata.');
          }

          if($request->warehouse_id != 2 AND (!isset($request->btr_warehouse_2) AND $request->btr_transport_id_2 == "" AND $delhiQty != "")){
            return redirect()->back()->withInput()->with('error', 'Please enter the btr value for Delhi.');
          }

          if($request->warehouse_id != 6 AND (!isset($request->btr_warehouse_6) AND $request->btr_transport_id_6 == "" AND $mumbaiQty != "")){
            return redirect()->back()->withInput()->with('error', 'Please enter the btr value for Mumbai.');
          }
      }

      if(!isset($request->btr_verification)){
        return redirect()->back()->withInput()->with('error', 'Please check BTR Varification.');
      }
      
      // echo "<pre>"; print_r($request->all());
      $userDetails = User::where('id',$request['user_id'])->first();
      $orderData = Order::with('orderDetails','user')->where('id',$request->order_id)->first();
      
      // Seperate home wareHouse and other wareHouse
      $homwBranchId = $request->warehouse_id;
      $allWareHouse = Warehouse::where('active','1')->get();
      $other_warehouse = array();
      $home_branch = array();
      foreach($allWareHouse as $awKey){
        if($awKey->id != $homwBranchId){
          $other_warehouse[$awKey->id]=$awKey->name;
        }else{
          $home_branch[$awKey->id]=$awKey->name;          
        }
      }
      // echo "<pre>"; print_r($home_branch);print_r($other_warehouse);die;

      // Set btr order flag.
      $btrOrderFlag = 0;
      foreach($other_warehouse as $owKey=>$owValue){
        $btrWarehouse = $request->input('btr_warehouse_' . $owKey);
        $btrTransportName = $request->input('btr_transport_name_' . $owKey);            
        if (!empty($btrWarehouse) AND $btrOrderFlag == 0) { // Check if warehouse field is not empty
          $btrOrderFlag = 1;
        }
      }
      // echo $btrWarehouse; die;
      // For Home Branch
      $homeBranchDataInsertFlag = 0;
      $btrFlag = 0;
      foreach($home_branch as $hbKey=>$hbValue){
        foreach($order_details_id_array as $odKey => $odValue) {
          $branchQty   = $request->input($hbValue.'_allocate_qty_' . $odValue);
          if($branchQty > 0 OR $btrOrderFlag > 0){
            $homeBranchDataInsertFlag = 1;
          }
        }
      }
      
      // Insert data for home branch
      $sub_order_id = null;
      if($homeBranchDataInsertFlag == 1){
        // Generate Order No
        $warehouseData = Warehouse::where('id',$homwBranchId)->first();
        $getLastRecord = SubOrder::where('warehouse_id',$homwBranchId)->where('status','completed')->orderBy('id','DESC')->first();
        $lastOrderNo=array();
        if($getLastRecord != NULL){
          $lastOrderNo = explode('/',$getLastRecord->order_no);
          $secondLastElement = $lastOrderNo[count($lastOrderNo) - 2];
          $number = $secondLastElement+1;
        }else{
          $number = 1;
        }
        if($this->getFinancialYear() != end($lastOrderNo)){
          $number = 1;
        }
        $order_no = "";
        if($request['order_status'] == 'completed'){
            $order_no = 'SO/'.strtoupper(substr($warehouseData->name,0,3)).'/'.str_pad($number, 6, '0', STR_PAD_LEFT).'/'.$this->getFinancialYear();
        }                
        
        $splitOrder = array();
        $splitOrder['order_id'] = $request['order_id'];
        $splitOrder['combined_order_id'] = $request['combined_order_id'];
        $splitOrder['order_no'] = $order_no;
        $splitOrder['user_id'] = $request['user_id'];
        $splitOrder['seller_id'] = $orderData->seller_id;
        $splitOrder['shipping_address_id'] = $request['ship_to'];
        $splitOrder['shipping_address'] = $this->jsonAddress($request['user_id'], $request['ship_to']);
        $splitOrder['billing_address_id'] = $request['bill_to'];
        $splitOrder['billing_address'] = $this->jsonAddress($request['user_id'], $request['bill_to']);
        $splitOrder['additional_info'] = $orderData->additional_info;
        $splitOrder['shipping_type'] = $orderData->shipping_type;
        $splitOrder['payment_status'] = $orderData->payment_status;
        $splitOrder['payment_details'] = $orderData->payment_details;
        $splitOrder['grand_total'] = $orderData->grand_total;
        $splitOrder['payable_amount'] = $orderData->payable_amount;
        $splitOrder['payment_discount'] = $orderData->payment_discount;
        $splitOrder['coupon_discount'] = $orderData->coupon_discount;
        $splitOrder['code'] = $orderData->code;
        $splitOrder['date'] = $orderData->date;
        $splitOrder['viewed'] = $orderData->viewed;
        $splitOrder['order_from'] = $orderData->order_from;
        $splitOrder['payment_status_viewed'] = $orderData->payment_status_viewed;
        $splitOrder['commission_calculated'] = $orderData->commission_calculated;
        $splitOrder['status'] = $request['order_status'];
        $splitOrder['warehouse_id'] = $homwBranchId;
        $splitOrder['sub_order_user_name'] = $userDetails->name;
        $splitOrder['early_payment_check'] = $request->has('early_payment_check') ? 1 : 0;
        $btrSplitOrder = array();
        $btrSplitOrder['type'] = 'sub_order';        
        $btrSplitOrder['transport_remarks'] = $request['btr_transport_remarks_'.$homwBranchId];
        $splitOrderArray = array_merge($splitOrder,$btrSplitOrder);
        // echo "<pre>"; print_r($homeBranchDataInsertFlag); die;
        // Insert for home branch
        $homeBranchOrder = SubOrder::create($splitOrderArray);
        $sub_order_id = $homeBranchOrder->id;

        $orderData->sub_order_status = 1;
        $orderData->update();

        $getMainSplitOrder->re_allocated_sub_order_id = $homeBranchOrder->id;
        $getMainSplitOrder->save();

        foreach($order_details_id_array as $odKey => $odValue) {
          $orderDetailsData = OrderDetail::with('product')->where('id',$odValue)->first();
          $getMainSplitOrderDetails = SubOrderDetail::where('id',$request->sub_order_details_id)->where('product_id',$orderDetailsData->product_id)->first();

          $wareHouseData = Warehouse::where('id',$homwBranchId)->first();
          $quantity = $request->input($wareHouseData->name.'_allocate_qty_' . $odValue);
          
          if($getMainSplitOrderDetails != NULL){
            $getMainSplitOrderDetails->reallocated = $getMainSplitOrderDetails->reallocated + ($quantity ? $quantity : 0);
            $getMainSplitOrderDetails->save();
          }
          $splitOrderDetails = array();
          
          $splitOrderDetails['order_id'] = $orderDetailsData->order_id;
          $splitOrderDetails['order_type'] = $orderDetailsData->order_type;
          $splitOrderDetails['seller_id'] = $orderDetailsData->seller_id;
          $splitOrderDetails['og_product_warehouse_id'] = $orderDetailsData->og_product_warehouse_id;
          $splitOrderDetails['product_warehouse_id'] = $orderDetailsData->product_warehouse_id;
          $splitOrderDetails['product_id'] = $orderDetailsData->product_id;
          $splitOrderDetails['variation'] = $orderDetailsData->variation;
          // $splitOrderDetails['price'] = $orderDetailsData->price;
          // $splitOrderDetails['tax'] = $orderDetailsData->tax;
          $splitOrderDetails['shipping_cost'] = $orderDetailsData->shipping_cost;
          $splitOrderDetails['quantity'] = $orderDetailsData->quantity;
          $splitOrderDetails['payment_status'] = $orderDetailsData->payment_status;
          $splitOrderDetails['delivery_status'] = $orderDetailsData->delivery_status;
          $splitOrderDetails['shipping_type'] = $orderDetailsData->shipping_type;
          $splitOrderDetails['pickup_point_id'] = $orderDetailsData->pickup_point_id;
          $splitOrderDetails['product_referral_code'] = $orderDetailsData->product_referral_code;
          $splitOrderDetails['earn_point'] = $orderDetailsData->earn_point;
          $splitOrderDetails['cash_and_carry_item'] = $orderDetailsData->cash_and_carry_item;
          // $splitOrderDetails['new_item'] = $orderDetailsData->$new_item;
          $splitOrderDetails['applied_offer_id'] = $orderDetailsData->applied_offer_id;
          $splitOrderDetails['complementary_item'] = $orderDetailsData->complementary_item;
          $splitOrderDetails['offer_rewards'] = $orderDetailsData->offer_rewards;
          $splitOrderDetails['remarks'] = $request['remark_'.$odValue];
          $splitOrderDetails['reallocated_from_sub_order_id'] = $getMainSplitOrderDetails->id;         
          
          if($quantity > 0){
            $btrSplitOrderDetails = array();
            $btrSplitOrderDetails['price'] = ($orderDetailsData->price / $orderDetailsData->quantity);
            $btrSplitOrderDetails['tax'] = (($orderDetailsData->price / $orderDetailsData->quantity) * 0.18 );
            $btrSplitOrderDetails['sub_order_id'] = $sub_order_id;
            $btrSplitOrderDetails['order_details_id'] = $odValue;
            $btrSplitOrderDetails['challan_quantity'] = "";
            $btrSplitOrderDetails['pre_close_quantity'] = "";
            $btrSplitOrderDetails['approved_quantity'] = $quantity;
            $btrSplitOrderDetails['approved_rate'] = $request['rate_'.$odValue];
            $btrSplitOrderDetails['warehouse_id'] = $homwBranchId;
            $btrSplitOrderDetails['type'] = 'sub_order';
            
            $btrSplitOrderArray = array_merge($splitOrderDetails,$btrSplitOrderDetails);
            $homeBranchOrder = SubOrderDetail::create($btrSplitOrderArray);

            // This entry for negative stock reset
            $getResetProductData = ResetProduct::where('product_id',$orderDetailsData->product_id)->first();
            if($getResetProductData == NULL){
              $productData = Product::where('id',$orderDetailsData->product_id)->first();
              $resetProduct = array();
              $resetProduct['product_id'] = $orderDetailsData->product_id;
              $resetProduct['part_no'] = $productData->part_no;
              ResetProduct::create($resetProduct);
            }
          }          
        }
      }
      
      // Insert data for other branch
      $homeBranchDataInsertFlag = 0;
      $btrFlag = 0;
      foreach($other_warehouse as $owKey=>$owValue){
        
        $btrOrderFlag = 0;
        $btrWarehouse = $request->input('btr_warehouse_' . $owKey);
        $btrTransportName = $request->input('btr_transport_name_' . $owKey);            
        if (!empty($btrWarehouse) AND $btrOrderFlag == 0) { // Check if warehouse field is not empty
          $btrOrderFlag = 1;
        }
        
        // Generate Order No
        $number="";
        $order_no="";
        $warehouseData = Warehouse::where('id',$owKey)->first();
        $getLastRecord = SubOrder::where('warehouse_id',$owKey)->orderBy('id','DESC')->first();
        if($getLastRecord != NULL){
          $lastOrderNo = explode('/',$getLastRecord->order_no);
          $secondLastElement = $lastOrderNo[count($lastOrderNo) - 2];
          $number = $secondLastElement+1;
        }else{
          $number = 1;
        }
        $order_no = "";
        if($request['order_status'] == 'completed'){
          $order_no = 'SO/'.strtoupper(substr($warehouseData->name,0,3)).'/'.str_pad($number, 6, '0', STR_PAD_LEFT).'/'.$this->getFinancialYear();
        }

        $orderType = 'sub_order';
        $splitOrder = array();
        $splitOrder['order_id'] = $request['order_id'];
        $splitOrder['combined_order_id'] = $request['combined_order_id'];
        $splitOrder['order_no'] = $order_no;
        $splitOrder['user_id'] = $request['user_id'];
        $splitOrder['seller_id'] = $orderData->seller_id;
        $splitOrder['shipping_address_id'] = $request['ship_to'];
        $splitOrder['shipping_address'] = $this->jsonAddress($request['user_id'], $request['ship_to']);
        $splitOrder['billing_address_id'] = $request['bill_to'];
        $splitOrder['billing_address'] = $this->jsonAddress($request['user_id'], $request['bill_to']);
        $splitOrder['additional_info'] = $orderData->additional_info;
        $splitOrder['shipping_type'] = $orderData->shipping_type;
        $splitOrder['payment_status'] = $orderData->payment_status;
        $splitOrder['payment_details'] = $orderData->payment_details;
        $splitOrder['grand_total'] = $orderData->grand_total;
        $splitOrder['payable_amount'] = $orderData->payable_amount;
        $splitOrder['payment_discount'] = $orderData->payment_discount;
        $splitOrder['coupon_discount'] = $orderData->coupon_discount;
        $splitOrder['code'] = $orderData->code;
        $splitOrder['date'] = $orderData->date;
        $splitOrder['viewed'] = $orderData->viewed;
        $splitOrder['order_from'] = $orderData->order_from;
        $splitOrder['payment_status_viewed'] = $orderData->payment_status_viewed;
        $splitOrder['commission_calculated'] = $orderData->commission_calculated;
        $splitOrder['status'] = $request['order_status'];
        $splitOrder['early_payment_check'] = $request->has('early_payment_check') ? 1 : 0;
        $splitOrder['warehouse_id'] = $owKey;
        $btrSplitOrder = array();
        if($btrOrderFlag == 0){
          $splitOrder['sub_order_user_name'] = $userDetails->name;
        }else{
          if($home_branch[$homwBranchId] == 'Kolkata'){
            $branchUserDetails = User::where('id', 27091)->first();
            $address_id = 5202;
          }elseif($home_branch[$homwBranchId] == 'Delhi'){
            $branchUserDetails = User::where('id', 27093)->first();
            $address_id = 5205;
          }elseif($home_branch[$homwBranchId] == 'Mumbai'){
            $branchUserDetails = User::where('id', 27092)->first();
            $address_id = 5204;
          }
          $splitOrder['sub_order_user_name'] = $branchUserDetails->company_name;;
          $splitOrder['user_id'] = $branchUserDetails->id;
          $splitOrder['shipping_address_id'] = $address_id;
          $splitOrder['shipping_address'] = $this->jsonAddress($branchUserDetails->id, $address_id);
          $splitOrder['billing_address_id'] = $address_id;
          $splitOrder['billing_address'] = $this->jsonAddress($branchUserDetails->id, $address_id);
        }
        
        if (!empty($btrWarehouse)) { // Check if warehouse field is not empty
          $btrSplitOrder['sub_order_id'] = $sub_order_id;
          $btrSplitOrder['type'] = 'btr';
          $btrSplitOrder['transport_remarks'] = $request->input('btr_transport_remarks_' . $owValue);
          $orderType = 'btr';
        } elseif (empty($btrWarehouse) && !empty($btrTransportName)) { // Check transport name when warehouse is empty
          $btrSplitOrder['type'] = 'sub_order';
          $btrSplitOrder['sub_order_id'] = $sub_order_id;
          $btrSplitOrder['warehouse_id'] = $owKey;
          $btrSplitOrder['other_details'] = "";
          $btrSplitOrder['status'] = $request->input('order_status');
          $btrSplitOrder['transport_id'] = $request->input('btr_transport_id_' . $owKey);
          $btrSplitOrder['transport_table_id'] = $request->input('btr_transport_table_id_' . $owKey);
          $btrSplitOrder['transport_name'] = $btrTransportName;
          $btrSplitOrder['transport_phone'] = $request->input('btr_transport_mobile_' . $owKey);
          $btrSplitOrder['transport_remarks'] = $request->input('btr_transport_remarks_' . $owKey);
          $orderType = 'sub_order';
        }
        
        // $splitOrderArray = array_merge($splitOrder,$btrSplitOrder);
        $subOrderInsertFlag = 0;
        foreach($order_details_id_array as $odKey => $odValue) {
          $wareHouseData = Warehouse::where('id',$owKey)->first();
          $quantity = $request->input($wareHouseData->name.'_allocate_qty_' . $odValue);
          if($quantity != "" AND $quantity > 0){
            $subOrderInsertFlag = 1;
            break;
          }
        }
        
        if($subOrderInsertFlag == 1){
          $splitOrderArray = array_merge($splitOrder,$btrSplitOrder);
          
          $otherBranchOrder = SubOrder::create($splitOrderArray);
          $other_branch_sub_order_id = $otherBranchOrder->id;

          $orderData->sub_order_status = 1;
          $orderData->update();

          $getMainSplitOrder->re_allocated_sub_order_id = $otherBranchOrder->id;
          $getMainSplitOrder->save();
          
          foreach($order_details_id_array as $odKey => $odValue) {
            $wareHouseData = Warehouse::where('id',$owKey)->first();
            $quantity = $request->input($wareHouseData->name.'_allocate_qty_' . $odValue);            

            $getMainSplitOrderDetails = SubOrderDetail::where('id',$request->sub_order_details_id)->first();
            
            if($getMainSplitOrderDetails != NULL){
              $getMainSplitOrderDetails->reallocated = $getMainSplitOrderDetails->reallocated + ($quantity ? $quantity : 0);
              $getMainSplitOrderDetails->save();
            }
            // echo "<pre>".$request->sub_order_id; print_r($splitOrderArray); die;
            

            if($quantity != "" AND $quantity > 0){
              
              $orderDetailsData = OrderDetail::with('product')->where('id',$odValue)->first();
              $splitOrderDetails = array();
              $splitOrderDetails['order_id'] = $orderDetailsData->order_id;
              $splitOrderDetails['order_type'] = $orderDetailsData->order_type;
              $splitOrderDetails['seller_id'] = $orderDetailsData->seller_id;
              $splitOrderDetails['og_product_warehouse_id'] = $orderDetailsData->og_product_warehouse_id;
              $splitOrderDetails['product_warehouse_id'] = $orderDetailsData->product_warehouse_id;
              $splitOrderDetails['product_id'] = $orderDetailsData->product_id;
              $splitOrderDetails['variation'] = $orderDetailsData->variation;
              // $splitOrderDetails['price'] = $orderDetailsData->price;
              // $splitOrderDetails['tax'] = $orderDetailsData->tax;
              $splitOrderDetails['shipping_cost'] = $orderDetailsData->shipping_cost;
              $splitOrderDetails['quantity'] = $orderDetailsData->quantity;
              $splitOrderDetails['payment_status'] = $orderDetailsData->payment_status;
              $splitOrderDetails['delivery_status'] = $orderDetailsData->delivery_status;
              $splitOrderDetails['shipping_type'] = $orderDetailsData->shipping_type;
              $splitOrderDetails['pickup_point_id'] = $orderDetailsData->pickup_point_id;
              $splitOrderDetails['product_referral_code'] = $orderDetailsData->product_referral_code;
              $splitOrderDetails['earn_point'] = $orderDetailsData->earn_point;
              $splitOrderDetails['cash_and_carry_item'] = $orderDetailsData->cash_and_carry_item;
              // $splitOrderDetails['new_item'] = $orderDetailsData->$new_item;
              $splitOrderDetails['applied_offer_id'] = $orderDetailsData->applied_offer_id;
              $splitOrderDetails['complementary_item'] = $orderDetailsData->complementary_item;
              $splitOrderDetails['offer_rewards'] = $orderDetailsData->offer_rewards;
              $splitOrderDetails['remarks'] = $request['remark_'.$odValue];
              $splitOrderDetails['reallocated_from_sub_order_id'] = $getMainSplitOrderDetails->id;
              // -------------------------------------- Split Order Details ------------------------------------------            
              $btrSplitOrderDetails = array();
              $btrSplitOrderDetails['sub_order_id'] = $other_branch_sub_order_id;
              $btrSplitOrderDetails['order_details_id'] = $odValue;
              $btrSplitOrderDetails['challan_quantity'] = "";
              $btrSplitOrderDetails['pre_close_quantity'] = "";
              $btrSplitOrderDetails['approved_quantity'] = $quantity;
              $btrSplitOrderDetails['approved_rate'] = $request['rate_'.$odValue];
              $btrSplitOrderDetails['warehouse_id'] = $owKey;
              $btrSplitOrderDetails['type'] = $orderType;
              $btrSplitOrderDetails['price'] = ($orderDetailsData->price / $orderDetailsData->quantity);
              $btrSplitOrderDetails['tax'] = (($orderDetailsData->price / $orderDetailsData->quantity) * 0.18 );
              $btrSplitOrderArray = array_merge($splitOrderDetails,$btrSplitOrderDetails);
              $homeBranchOrder = SubOrderDetail::create($btrSplitOrderArray);

              // This entry for negative stock reset
              $getResetProductData = ResetProduct::where('product_id',$orderDetailsData->product_id)->first();
              if($getResetProductData == NULL){
                $productData = Product::where('id',$orderDetailsData->product_id)->first();
                $resetProduct = array();
                $resetProduct['product_id'] = $orderDetailsData->product_id;
                $resetProduct['part_no'] = $productData->part_no;
                ResetProduct::create($resetProduct);
              }

              if($orderType == 'btr'){
                
                $getHomeBranchSubOrderDetails = SubOrderDetail::where('sub_order_id',$sub_order_id)->where('product_id',$orderDetailsData->product_id)->where('warehouse_id',$homwBranchId)->first();
                // echo "<pre>"; print_r($getHomeBranchSubOrderDetails); die;
                if(isset($getHomeBranchSubOrderDetails->approved_quantity)){
                  $getHomeBranchSubOrderDetails->approved_quantity = $getHomeBranchSubOrderDetails->approved_quantity + $quantity;
                  $getHomeBranchSubOrderDetails->in_transit = $getHomeBranchSubOrderDetails->in_transit + $quantity;
                  $getHomeBranchSubOrderDetails->save();
                }else{
                  $btrSplitOrderArray['sub_order_id'] = $sub_order_id;
                  $btrSplitOrderArray['type'] = 'sub_order';                  
                  $btrSplitOrderArray['in_transit'] =  $quantity;
                  $btrSplitOrderArray['warehouse_id'] =  $homwBranchId;
                  $homeBranchOrder = SubOrderDetail::create($btrSplitOrderArray);

                  // This entry for negative stock reset
                  $getResetProductData = ResetProduct::where('product_id',$orderDetailsData->product_id)->first();
                  if($getResetProductData == NULL){
                    $productData = Product::where('id',$orderDetailsData->product_id)->first();
                    $resetProduct = array();
                    $resetProduct['product_id'] = $orderDetailsData->product_id;
                    $resetProduct['part_no'] = $productData->part_no;
                    ResetProduct::create($resetProduct);
                  }
                  // echo "Hello"; print_r($homeBranchOrder); die;
                }                
              }
            }          
          } 
        }
      }
      $response = ['res' => true, 'msg' => "Successfully insert data."];
      return redirect()->route('order.allSplitOrder')->send();
      // $splitOrderData = SubOrder::create($splitOrder);
      // echo "<pre>"; print_r($splitOrder);print_r($request->all());die;
    } catch (\Exception $e) {
        $errorCode = $e->getCode();
        if ($errorCode == 23000) {
            $errorMessage = __("direct link already exists");
        } else {
            $errorMessage = $e->getMessage();//"something went wrong, please try again";
        }
        $response = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }        
    return $response; 
  }

  public function saveSubOrderReAllocationOrder(Request $request){
    try{
      $order_id = $request->order_id;
      $getMainSplitOrder = SubOrder::where('id',$request->sub_order_id)->first();
      $subOrderDetailIds = $getMainSplitOrder->sub_order_details->pluck('id')->toArray();

      $order_details_id_array = $request->order_details_id;
      $sub_order_details_id_array = $request->sub_order_details_id;

      foreach($order_details_id_array as $odKey => $odValue) {
          $kolkataQty = $request->input('Kolkata_allocate_qty_' . $odValue);
          $delhiQty   = $request->input('Delhi_allocate_qty_' . $odValue);
          $mumbaiQty  = $request->input('Mumbai_allocate_qty_' . $odValue);
          // Check if all fields are empty
          // if(empty($kolkataQty) && empty($delhiQty) && empty($mumbaiQty)) {
          //     $productDetails = Product::find($request->input('product_id_' . $odValue));
          //     return redirect()->back()->withInput()->with('error', 'Please enter allocate quantity for ' . $productDetails->name);
          // }
          if($request->warehouse_id != 1 AND (!isset($request->btr_warehouse_1) AND $request->btr_transport_id_1 == "" AND $kolkataQty != "")){
            return redirect()->back()->withInput()->with('error', 'Please enter the btr value for Kolkata.');
          }

          if($request->warehouse_id != 2 AND (!isset($request->btr_warehouse_2) AND $request->btr_transport_id_2 == "" AND $delhiQty != "")){
            return redirect()->back()->withInput()->with('error', 'Please enter the btr value for Delhi.');
          }

          if($request->warehouse_id != 6 AND (!isset($request->btr_warehouse_6) AND $request->btr_transport_id_6 == "" AND $mumbaiQty != "")){
            return redirect()->back()->withInput()->with('error', 'Please enter the btr value for Mumbai.');
          }
      }

      if(!isset($request->btr_verification)){
        return redirect()->back()->withInput()->with('error', 'Please check BTR Varification.');
      }
      
      
      $userDetails = User::where('id',$request['user_id'])->first();
      $orderData = Order::with('orderDetails','user')->where('id',$request->order_id)->first();
      
      // Seperate home wareHouse and other wareHouse
      $homwBranchId = $request->warehouse_id;
      $allWareHouse = Warehouse::where('active','1')->get();
      $other_warehouse = array();
      $home_branch = array();
      foreach($allWareHouse as $awKey){
        if($awKey->id != $getMainSplitOrder->warehouse_id){
          $other_warehouse[$awKey->id]=$awKey->name;
        }else{
          $home_branch[$awKey->id]=$awKey->name;
          $homwBranchId = $awKey->id;          
        }
      }
      // echo "<pre>"; print_r($home_branch);print_r($other_warehouse);die;

      // Set btr order flag.
      $btrOrderFlag = 0;
      foreach($other_warehouse as $owKey=>$owValue){
        $btrWarehouse = $request->input('btr_warehouse_' . $owKey);
        $btrTransportName = $request->input('btr_transport_name_' . $owKey);            
        if (!empty($btrWarehouse) AND $btrOrderFlag == 0) { // Check if warehouse field is not empty
          $btrOrderFlag = 1;
        }
      }
      // echo $btrWarehouse; die;
      // For Home Branch
      $homeBranchDataInsertFlag = 0;
      $btrFlag = 0;
      foreach($home_branch as $hbKey=>$hbValue){
        foreach($order_details_id_array as $odKey => $odValue) {
          $branchQty   = $request->input($hbValue.'_allocate_qty_' . $odValue);
          if($branchQty > 0 OR $btrOrderFlag > 0){
            $homeBranchDataInsertFlag = 1;
          }
        }
      }
      
      // echo $homwBranchId; die;
      // Insert data for home branch
      $sub_order_id = null;
      if($homeBranchDataInsertFlag == 1){        
        if($getMainSplitOrder->sub_order_id != NULL){          
          $getParentOrder = SubOrder::where('id',$getMainSplitOrder->sub_order_id)->first();
          $getParentOrderDetails = SubOrderDetail::where('id', $getParentOrder->id)->where('warehouse_id', $homwBranchId)->first();
          $getParentOrderDetails->in_transit = $getParentOrderDetails->in_transit - $branchQty;
          $getParentOrderDetails->in_transit - $branchQty;
          $getParentOrderDetails->save();          

          $getMainSplitOrderDetails = SubOrderDetail::where('id', $request->sub_order_id)->where('warehouse_id', $homwBranchId)->first();          
          if ($getMainSplitOrderDetails) {
              $getMainSplitOrderDetails->reallocated = $branchQty;
              $getMainSplitOrderDetails->save();
          }          
        }else{
          // Generate Order No
          $warehouseData = Warehouse::where('id',$homwBranchId)->first();
          $getLastRecord = SubOrder::where('warehouse_id',$homwBranchId)->where('status','completed')->orderBy('id','DESC')->first();
          $lastOrderNo=array();
          if($getLastRecord != NULL){
            $lastOrderNo = explode('/',$getLastRecord->order_no);
            $secondLastElement = $lastOrderNo[count($lastOrderNo) - 2];
            $number = $secondLastElement+1;
          }else{
            $number = 1;
          }
          if($this->getFinancialYear() != end($lastOrderNo)){
            $number = 1;
          }
          $order_no = "";
          if($request['order_status'] == 'completed'){
              $order_no = 'SO/'.strtoupper(substr($warehouseData->name,0,3)).'/'.str_pad($number, 6, '0', STR_PAD_LEFT).'/'.$this->getFinancialYear();
          }                
          
          $splitOrder = array();
          $splitOrder['order_id'] = $request['order_id'];
          $splitOrder['combined_order_id'] = $request['combined_order_id'];
          $splitOrder['order_no'] = $order_no;
          $splitOrder['user_id'] = $request['user_id'];
          $splitOrder['seller_id'] = $orderData->seller_id;
          $splitOrder['shipping_address_id'] = $request['ship_to'];
          $splitOrder['shipping_address'] = $this->jsonAddress($request['user_id'], $request['ship_to']);
          $splitOrder['billing_address_id'] = $request['bill_to'];
          $splitOrder['billing_address'] = $this->jsonAddress($request['user_id'], $request['bill_to']);
          $splitOrder['additional_info'] = $orderData->additional_info;
          $splitOrder['shipping_type'] = $orderData->shipping_type;
          $splitOrder['payment_status'] = $orderData->payment_status;
          $splitOrder['payment_details'] = $orderData->payment_details;
          $splitOrder['grand_total'] = $orderData->grand_total;
          $splitOrder['payable_amount'] = $orderData->payable_amount;
          $splitOrder['payment_discount'] = $orderData->payment_discount;
          $splitOrder['coupon_discount'] = $orderData->coupon_discount;
          $splitOrder['code'] = $orderData->code;
          $splitOrder['date'] = $orderData->date;
          $splitOrder['viewed'] = $orderData->viewed;
          $splitOrder['order_from'] = $orderData->order_from;
          $splitOrder['payment_status_viewed'] = $orderData->payment_status_viewed;
          $splitOrder['commission_calculated'] = $orderData->commission_calculated;
          $splitOrder['status'] = $request['order_status'];
          $splitOrder['warehouse_id'] = $homwBranchId;
          $splitOrder['sub_order_user_name'] = $userDetails->name;
          $splitOrder['conveince_fee_payment_check']    = $request->has('conveince_fee_payment_check') ? 1 : 0;
          $btrSplitOrder = array();
          $btrSplitOrder['type'] = 'sub_order';
          $btrSplitOrder['transport_remarks'] = $request['btr_transport_remarks_'.$homwBranchId];
          $splitOrderArray = array_merge($splitOrder,$btrSplitOrder);
          
          if($splitOrderArray['type'] == 'sub_order' AND $splitOrder['conveince_fee_payment_check'] == 1){
            $splitOrderArray['conveince_fee_percentage'] = $orderData->conveince_fee_percentage;
          }
          // Insert for home branch
          $homeBranchOrder = SubOrder::create($splitOrderArray);
          $sub_order_id = $homeBranchOrder->id;

          $orderData->sub_order_status = 1;
          $orderData->update();

          $getMainSplitOrder->re_allocated_sub_order_id = $homeBranchOrder->id;
          $getMainSplitOrder->save();

          foreach($order_details_id_array as $odKey => $odValue) {
            $orderDetailsData = OrderDetail::with('product')->where('id',$odValue)->first();
            $getMainSplitOrderDetails = SubOrderDetail::where('id',$sub_order_details_id_array[$odKey])->where('product_id',$orderDetailsData->product_id)->first();

            $wareHouseData = Warehouse::where('id',$homwBranchId)->first();
            $quantity = $request->input($wareHouseData->name.'_allocate_qty_' . $odValue);
            
            if($getMainSplitOrderDetails != NULL){
              $getMainSplitOrderDetails->reallocated = $getMainSplitOrderDetails->reallocated + ($quantity ? $quantity : 0);
              $getMainSplitOrderDetails->save();
            }
            $splitOrderDetails = array();
            
            $splitOrderDetails['order_id'] = $orderDetailsData->order_id;
            $splitOrderDetails['order_type'] = $orderDetailsData->order_type;
            $splitOrderDetails['seller_id'] = $orderDetailsData->seller_id;
            $splitOrderDetails['og_product_warehouse_id'] = $orderDetailsData->og_product_warehouse_id;
            $splitOrderDetails['product_warehouse_id'] = $orderDetailsData->product_warehouse_id;
            $splitOrderDetails['product_id'] = $orderDetailsData->product_id;
            $splitOrderDetails['variation'] = $orderDetailsData->variation;
            // $splitOrderDetails['price'] = $orderDetailsData->price;
            // $splitOrderDetails['tax'] = $orderDetailsData->tax;
            $splitOrderDetails['shipping_cost'] = $orderDetailsData->shipping_cost;
            $splitOrderDetails['quantity'] = $orderDetailsData->quantity;
            $splitOrderDetails['payment_status'] = $orderDetailsData->payment_status;
            $splitOrderDetails['delivery_status'] = $orderDetailsData->delivery_status;
            $splitOrderDetails['shipping_type'] = $orderDetailsData->shipping_type;
            $splitOrderDetails['pickup_point_id'] = $orderDetailsData->pickup_point_id;
            $splitOrderDetails['product_referral_code'] = $orderDetailsData->product_referral_code;
            $splitOrderDetails['earn_point'] = $orderDetailsData->earn_point;
            $splitOrderDetails['cash_and_carry_item'] = $orderDetailsData->cash_and_carry_item;
            // $splitOrderDetails['new_item'] = $orderDetailsData->$new_item;
            $splitOrderDetails['applied_offer_id'] = $orderDetailsData->applied_offer_id;
            $splitOrderDetails['complementary_item'] = $orderDetailsData->complementary_item;
            $splitOrderDetails['offer_rewards'] = $orderDetailsData->offer_rewards;
            $splitOrderDetails['remarks'] = $request['remark_'.$odValue];
            $splitOrderDetails['reallocated_from_sub_order_id'] = $getMainSplitOrderDetails->id;         
            
            if($quantity > 0){
              $btrSplitOrderDetails = array();
              $btrSplitOrderDetails['price'] = ($orderDetailsData->price / $orderDetailsData->quantity);
              $btrSplitOrderDetails['tax'] = (($orderDetailsData->price / $orderDetailsData->quantity) * 0.18 );
              $btrSplitOrderDetails['sub_order_id'] = $sub_order_id;
              $btrSplitOrderDetails['order_details_id'] = $odValue;
              $btrSplitOrderDetails['challan_quantity'] = "";
              $btrSplitOrderDetails['pre_close_quantity'] = "";
              $btrSplitOrderDetails['approved_quantity'] = $quantity;
              $btrSplitOrderDetails['approved_rate'] = $request['rate_'.$odValue];
              $btrSplitOrderDetails['warehouse_id'] = $homwBranchId;
              $btrSplitOrderDetails['type'] = 'sub_order';
              
              $btrSplitOrderArray = array_merge($splitOrderDetails,$btrSplitOrderDetails);

              if($btrSplitOrderArray['type'] == 'sub_order' AND $splitOrder['conveince_fee_payment_check'] == 1){
                $btrSplitOrderArray['conveince_fee_percentage'] = $orderData->conveince_fee_percentage;
                $btrSplitOrderArray['conveince_fees'] = (($btrSplitOrderArray['approved_rate'] * $quantity) * $orderData->conveince_fee_percentage) / 100;
              }

              $homeBranchOrder = SubOrderDetail::create($btrSplitOrderArray);

              // This entry for negative stock reset
              $getResetProductData = ResetProduct::where('product_id',$orderDetailsData->product_id)->first();
              if($getResetProductData == NULL){
                $productData = Product::where('id',$orderDetailsData->product_id)->first();
                $resetProduct = array();
                $resetProduct['product_id'] = $orderDetailsData->product_id;
                $resetProduct['part_no'] = $productData->part_no;
                ResetProduct::create($resetProduct);
              }
            }          
          }
        }
      }
      
      // Insert data for other branch
      $homeBranchDataInsertFlag = 0;
      $btrFlag = 0;
      foreach($other_warehouse as $owKey=>$owValue){
        
        $btrOrderFlag = 0;
        $btrWarehouse = $request->input('btr_warehouse_' . $owKey);
        $btrTransportName = $request->input('btr_transport_name_' . $owKey);            
        if (!empty($btrWarehouse) AND $btrOrderFlag == 0) { // Check if warehouse field is not empty
          $btrOrderFlag = 1;
        }
        
        // Generate Order No
        $number="";
        $order_no="";
        $warehouseData = Warehouse::where('id',$owKey)->first();
        $getLastRecord = SubOrder::where('warehouse_id',$owKey)->orderBy('id','DESC')->first();
        if($getLastRecord != NULL){
          $lastOrderNo = explode('/',$getLastRecord->order_no);
          $secondLastElement = $lastOrderNo[count($lastOrderNo) - 2];
          $number = $secondLastElement+1;
        }else{
          $number = 1;
        }
        $order_no = "";
        if($request['order_status'] == 'completed'){
          $order_no = 'SO/'.strtoupper(substr($warehouseData->name,0,3)).'/'.str_pad($number, 6, '0', STR_PAD_LEFT).'/'.$this->getFinancialYear();
        }
        // echo $order_no; die;
        $orderType = 'sub_order';
        $splitOrder = array();
        $splitOrder['order_id'] = $request['order_id'];
        $splitOrder['combined_order_id'] = $request['combined_order_id'];
        $splitOrder['order_no'] = $order_no;
        $splitOrder['user_id'] = $request['user_id'];
        $splitOrder['seller_id'] = $orderData->seller_id;
        $splitOrder['shipping_address_id'] = $request['ship_to'];
        $splitOrder['shipping_address'] = $this->jsonAddress($request['user_id'], $request['ship_to']);
        $splitOrder['billing_address_id'] = $request['bill_to'];
        $splitOrder['billing_address'] = $this->jsonAddress($request['user_id'], $request['bill_to']);
        $splitOrder['additional_info'] = $orderData->additional_info;
        $splitOrder['shipping_type'] = $orderData->shipping_type;
        $splitOrder['payment_status'] = $orderData->payment_status;
        $splitOrder['payment_details'] = $orderData->payment_details;
        $splitOrder['grand_total'] = $orderData->grand_total;
        $splitOrder['payable_amount'] = $orderData->payable_amount;
        $splitOrder['payment_discount'] = $orderData->payment_discount;
        $splitOrder['coupon_discount'] = $orderData->coupon_discount;
        $splitOrder['code'] = $orderData->code;
        $splitOrder['date'] = $orderData->date;
        $splitOrder['viewed'] = $orderData->viewed;
        $splitOrder['order_from'] = $orderData->order_from;
        $splitOrder['payment_status_viewed'] = $orderData->payment_status_viewed;
        $splitOrder['commission_calculated'] = $orderData->commission_calculated;
        $splitOrder['status'] = $request['order_status'];
        $splitOrder['conveince_fee_payment_check']    = $request->has('conveince_fee_payment_check') ? 1 : 0;
        $splitOrder['warehouse_id'] = $owKey;
        
        $btrSplitOrder = array();
        if($btrOrderFlag == 0){
          $splitOrder['sub_order_user_name'] = $userDetails->name;
        }else{
          if($home_branch[$homwBranchId] == 'Kolkata'){
            $branchUserDetails = User::where('id', 27091)->first();
            $address_id = 5202;
          }elseif($home_branch[$homwBranchId] == 'Delhi'){
            $branchUserDetails = User::where('id', 27093)->first();
            $address_id = 5205;
          }elseif($home_branch[$homwBranchId] == 'Mumbai'){
            $branchUserDetails = User::where('id', 27092)->first();
            $address_id = 5204;
          }
          $splitOrder['sub_order_user_name'] = $branchUserDetails->company_name;;
          $splitOrder['user_id'] = $branchUserDetails->id;
          $splitOrder['shipping_address_id'] = $address_id;
          $splitOrder['shipping_address'] = $this->jsonAddress($branchUserDetails->id, $address_id);
          $splitOrder['billing_address_id'] = $address_id;
          $splitOrder['billing_address'] = $this->jsonAddress($branchUserDetails->id, $address_id);
        }
        
        if (!empty($btrWarehouse)) { // Check if warehouse field is not empty
          $btrSplitOrder['sub_order_id'] = $sub_order_id;
          $btrSplitOrder['type'] = 'btr';
          $btrSplitOrder['transport_remarks'] = $request->input('btr_transport_remarks_' . $owValue);
          $orderType = 'btr';
        } elseif (empty($btrWarehouse) && !empty($btrTransportName)) { // Check transport name when warehouse is empty
          $btrSplitOrder['type'] = 'sub_order';
          $btrSplitOrder['sub_order_id'] = $sub_order_id;
          $btrSplitOrder['warehouse_id'] = $owKey;
          $btrSplitOrder['other_details'] = "";
          $btrSplitOrder['status'] = $request->input('order_status');
          $btrSplitOrder['transport_id'] = $request->input('btr_transport_id_' . $owKey);
          $btrSplitOrder['transport_table_id'] = $request->input('btr_transport_table_id_' . $owKey);
          $btrSplitOrder['transport_name'] = $btrTransportName;
          $btrSplitOrder['transport_phone'] = $request->input('btr_transport_mobile_' . $owKey);
          $btrSplitOrder['transport_remarks'] = $request->input('btr_transport_remarks_' . $owKey);
          $orderType = 'sub_order';
        }
        
        // $splitOrderArray = array_merge($splitOrder,$btrSplitOrder);
        $subOrderInsertFlag = 0;
        foreach($order_details_id_array as $odKey => $odValue) {
          $wareHouseData = Warehouse::where('id',$owKey)->first();
          $quantity = $request->input($wareHouseData->name.'_allocate_qty_' . $odValue);
          if($quantity != "" AND $quantity > 0){
            $subOrderInsertFlag = 1;
            break;
          }
        }
        
        if($subOrderInsertFlag == 1){
          $splitOrderArray = array_merge($splitOrder,$btrSplitOrder);
          if($splitOrderArray['type'] == 'sub_order' AND $splitOrder['conveince_fee_payment_check'] == 1){
            $splitOrderArray['conveince_fee_percentage'] = $orderData->conveince_fee_percentage;
          }
          $otherBranchOrder = SubOrder::create($splitOrderArray);
          $other_branch_sub_order_id = $otherBranchOrder->id;

          $orderData->sub_order_status = 1;
          $orderData->update();

          $getMainSplitOrder->re_allocated_sub_order_id = $otherBranchOrder->id;
          $getMainSplitOrder->save();
          
          foreach($order_details_id_array as $odKey => $odValue) {
            $wareHouseData = Warehouse::where('id',$owKey)->first();
            $quantity = $request->input($wareHouseData->name.'_allocate_qty_' . $odValue);            

            $getMainSplitOrderDetails = SubOrderDetail::where('id',$sub_order_details_id_array[$odKey])->first();
            
            if($getMainSplitOrderDetails != NULL){
              $getMainSplitOrderDetails->reallocated = $getMainSplitOrderDetails->reallocated + ($quantity ? $quantity : 0);
              $getMainSplitOrderDetails->save();
            }
            // echo "<pre>".$request->sub_order_id; print_r($splitOrderArray); die;

            if($quantity != "" AND $quantity > 0){
              
              $orderDetailsData = OrderDetail::with('product')->where('id',$odValue)->first();
              $splitOrderDetails = array();
              $splitOrderDetails['order_id'] = $orderDetailsData->order_id;
              $splitOrderDetails['order_type'] = $orderDetailsData->order_type;
              $splitOrderDetails['seller_id'] = $orderDetailsData->seller_id;
              $splitOrderDetails['og_product_warehouse_id'] = $orderDetailsData->og_product_warehouse_id;
              $splitOrderDetails['product_warehouse_id'] = $orderDetailsData->product_warehouse_id;
              $splitOrderDetails['product_id'] = $orderDetailsData->product_id;
              $splitOrderDetails['variation'] = $orderDetailsData->variation;
              // $splitOrderDetails['price'] = $orderDetailsData->price;
              // $splitOrderDetails['tax'] = $orderDetailsData->tax;
              $splitOrderDetails['shipping_cost'] = $orderDetailsData->shipping_cost;
              $splitOrderDetails['quantity'] = $orderDetailsData->quantity;
              $splitOrderDetails['payment_status'] = $orderDetailsData->payment_status;
              $splitOrderDetails['delivery_status'] = $orderDetailsData->delivery_status;
              $splitOrderDetails['shipping_type'] = $orderDetailsData->shipping_type;
              $splitOrderDetails['pickup_point_id'] = $orderDetailsData->pickup_point_id;
              $splitOrderDetails['product_referral_code'] = $orderDetailsData->product_referral_code;
              $splitOrderDetails['earn_point'] = $orderDetailsData->earn_point;
              $splitOrderDetails['cash_and_carry_item'] = $orderDetailsData->cash_and_carry_item;
              // $splitOrderDetails['new_item'] = $orderDetailsData->$new_item;
              $splitOrderDetails['applied_offer_id'] = $orderDetailsData->applied_offer_id;
              $splitOrderDetails['complementary_item'] = $orderDetailsData->complementary_item;
              $splitOrderDetails['offer_rewards'] = $orderDetailsData->offer_rewards;
              $splitOrderDetails['remarks'] = $request['remark_'.$odValue];
              $splitOrderDetails['reallocated_from_sub_order_id'] = $getMainSplitOrderDetails->id;
              // -------------------------------------- Split Order Details ------------------------------------------            
              $btrSplitOrderDetails = array();
              $btrSplitOrderDetails['price'] = ($orderDetailsData->price / $orderDetailsData->quantity);
              $btrSplitOrderDetails['tax'] = (($orderDetailsData->price / $orderDetailsData->quantity) * 0.18 );
              $btrSplitOrderDetails['sub_order_id'] = $other_branch_sub_order_id;
              $btrSplitOrderDetails['order_details_id'] = $odValue;
              $btrSplitOrderDetails['challan_quantity'] = "";
              $btrSplitOrderDetails['pre_close_quantity'] = "";
              $btrSplitOrderDetails['approved_quantity'] = $quantity;
              $btrSplitOrderDetails['approved_rate'] = $request['rate_'.$odValue];
              $btrSplitOrderDetails['warehouse_id'] = $owKey;
              $btrSplitOrderDetails['type'] = $orderType;
              $btrSplitOrderArray = array_merge($splitOrderDetails,$btrSplitOrderDetails);

              if($btrSplitOrderArray['type'] == 'sub_order' AND $splitOrder['conveince_fee_payment_check'] == 1){
                $btrSplitOrderArray['conveince_fee_percentage'] = $orderData->conveince_fee_percentage;
                $btrSplitOrderArray['conveince_fees'] = (($btrSplitOrderArray['approved_rate'] * $quantity) * $orderData->conveince_fee_percentage) / 100;
              }

              $homeBranchOrder = SubOrderDetail::create($btrSplitOrderArray);

              // This entry for negative stock reset
              $getResetProductData = ResetProduct::where('product_id',$orderDetailsData->product_id)->first();
              if($getResetProductData == NULL){
                $productData = Product::where('id',$orderDetailsData->product_id)->first();
                $resetProduct = array();
                $resetProduct['product_id'] = $orderDetailsData->product_id;
                $resetProduct['part_no'] = $productData->part_no;
                ResetProduct::create($resetProduct);
              }

              if($orderType == 'btr'){
                
                $getHomeBranchSubOrderDetails = SubOrderDetail::where('sub_order_id',$sub_order_id)->where('product_id',$orderDetailsData->product_id)->where('warehouse_id',$homwBranchId)->first();
                // echo "<pre>"; print_r($getHomeBranchSubOrderDetails); die;
                if(isset($getHomeBranchSubOrderDetails->approved_quantity)){
                  $getHomeBranchSubOrderDetails->approved_quantity = $getHomeBranchSubOrderDetails->approved_quantity + $quantity;
                  $getHomeBranchSubOrderDetails->in_transit = $getHomeBranchSubOrderDetails->in_transit + $quantity;
                  $getHomeBranchSubOrderDetails->save();
                }else{
                  $btrSplitOrderArray['sub_order_id'] = $sub_order_id;
                  $btrSplitOrderArray['type'] = 'sub_order';                  
                  $btrSplitOrderArray['in_transit'] =  $quantity;
                  $btrSplitOrderArray['warehouse_id'] =  $homwBranchId;
                  $homeBranchOrder = SubOrderDetail::create($btrSplitOrderArray);

                  // This entry for negative stock reset
                  $getResetProductData = ResetProduct::where('product_id',$orderDetailsData->product_id)->first();
                  if($getResetProductData == NULL){
                    $productData = Product::where('id',$orderDetailsData->product_id)->first();
                    $resetProduct = array();
                    $resetProduct['product_id'] = $orderDetailsData->product_id;
                    $resetProduct['part_no'] = $productData->part_no;
                    ResetProduct::create($resetProduct);
                  }

                  // echo "Hello"; print_r($homeBranchOrder); die;
                }                
              }
              
            }
            
          } 
          
        }
      }
    
      $response = ['res' => true, 'msg' => "Successfully insert data."];
      return redirect()->route('order.allSplitOrder')->send();
      // $splitOrderData = SubOrder::create($splitOrder);
      // echo "<pre>"; print_r($splitOrder);print_r($request->all());die;
    } catch (\Exception $e) {
        $errorCode = $e->getCode();
        if ($errorCode == 23000) {
            $errorMessage = __("direct link already exists");
        } else {
            $errorMessage = $e->getMessage();//"something went wrong, please try again";
        }
        $response = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }        
    return $response; 
  }

  public function addNewProductByScan(Request $request)
  {
      $sub_order_id = $request->sub_order_id;
      $productDetailsData = Product::where('part_no', $request->part_number)->first();
      $subOrderDetailsData = SubOrderDetail::where('sub_order_id', $request->sub_order_id)->first();
      // echo "<pre>"; print_r($subOrderDetailsData);die;
      $subOrderDetailsData->sub_order_record->user->name;


      if (!$productDetailsData) {
          return response()->json(['success' => false, 'message' => 'Product not found']);
      }

      $price = home_discounted_base_price($productDetailsData, false, $request->user_id);
      
      $btrSplitOrderDetails = [
          'sub_order_id' => $request->sub_order_id,
          'approved_quantity' => $request->quantity,
          'approved_rate' => $price,
          'warehouse_id' => $request->warehouse_id,
          'type' => $subOrderDetailsData->type
      ];

      $homeBranchOrder = SubOrderDetail::create(array_merge([
          'seller_id' => $productDetailsData->seller_id,
          'product_id' => $productDetailsData->id,
          'variation' => $productDetailsData->variation,
          'price' => $price,
          'tax' => $productDetailsData->tax,
          'shipping_cost' => $productDetailsData->shipping_cost,
          'quantity' => $request->quantity,
          'cash_and_carry_item' => $productDetailsData->cash_and_carry_item,
          'new_item' => "1"
      ], $btrSplitOrderDetails));

      // This entry for negative stock reset
      $getResetProductData = ResetProduct::where('product_id',$productDetailsData->id)->first();
      if($getResetProductData == NULL){
        $productData = Product::where('id',$productDetailsData->id)->first();
        $resetProduct = array();
        $resetProduct['product_id'] = $productDetailsData->id;
        $resetProduct['part_no'] = $productData->part_no;
        ResetProduct::create($resetProduct);
      }

      return response()->json([
          'success' => true,
          'message' => 'Product added successfully',
          'data' => [
              'id' => $homeBranchOrder->id,
              'sub_order_id' => $homeBranchOrder->sub_order_id,
              'part_no' => $productDetailsData->part_no,
              'name' => $productDetailsData->name,
              'seller_name' => $productDetailsData->sellerDetails->user->name ?? '',
              'seller_location' => $productDetailsData->sellerDetails->user->user_warehouse->name ?? '',
              'order_for' => $subOrderDetailsData->sub_order_record->user->name ?? '',
              'hsncode' => $productDetailsData->hsncode,
              'tax' => $productDetailsData->tax,
              'closing_stock' => $productDetailsData->stocks->where('warehouse_id', $request->warehouse_id)->first()->qty ?? 0,
              'price' => $price,
              'type' => $homeBranchOrder->type,
              'quantity' => $request->quantity,
              'billed_amount' => $price*$request->quantity
          ]
      ]);
  }

  public function getAllTransportData()
  {
      // Fetch all transporters from the database
      $allTransportData = Carrier::orderBy('name','ASC')->get(); // Assuming you have a model called Transport
      // Return the data as JSON
      return response()->json([
          'success' => true,
          'data' => $allTransportData
      ]);
  }


  /**
 * Manager-41: Pending for approval list (mirrors allPendingForApprovalOrder, but on mgr-41 tables)
 */

  private function manager41AllPendingForApprovalOrder(Request $request)
{
    $date            = $request->date;
    $sort_search     = null;
    $delivery_status = null;
    $payment_status  = '';
    $salzing_statuses = collect([]);

    $user  = auth()->user();
    $userId = (int) ($user->id ?? 0);
    $loggedWarehouseId = $user->warehouse_id ?? null;

    // ЁЯСЗ special-case: user 27604 sees ALL warehouses
    $forceAll = ($userId === 27604);

    // (A) Manager dropdown query
    $forManagerOrders = Manager41Order::select(
            'manager_41_orders.*',
            'addresses.company_name',
            'users.warehouse_id',
            'manager_users.name as manager_name',
            'warehouses.name as warehouse_name'
        )
        ->join('addresses', 'manager_41_orders.address_id', '=', 'addresses.id')
        ->join('users',     'manager_41_orders.user_id',    '=', 'users.id')
        ->leftJoin('users as manager_users', 'users.manager_id', '=', 'manager_users.id')
        ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
        ->whereNotIn('manager_41_orders.code', function ($q) {
            $q->select('code')->from('manager_41_sub_orders');
        })
        ->where('manager_41_orders.delete_status', 0)
        ->where('manager_41_orders.created_at', '>=', '2025-04-05')
        ->orderBy('manager_41_orders.id', 'DESC');

    // тЭЧ apply warehouse scope only if NOT 27604 and NOT superadmin
    if (!$forceAll && $userId !== 1 && $loggedWarehouseId) {
        $forManagerOrders->where('users.warehouse_id', $loggedWarehouseId);
    }

    $forManagerOrders = $forManagerOrders->get();

    $managerList = $forManagerOrders
        ->map(function ($o) {
            return [
                'id'   => optional($o->user->getManager)->id,
                'name' => optional($o->user->getManager)->name,
            ];
        })
        ->filter(fn ($m) => $m['id'])
        ->unique('id')
        ->pluck('name', 'id')
        ->toArray();

    // (B) Main list
    $orders = Manager41Order::select(
            'manager_41_orders.*',
            'addresses.company_name',
            'addresses.due_amount',
            'addresses.overdue_amount',
            'addresses.dueDrOrCr',
            'addresses.overdueDrOrCr',
            'users.warehouse_id',
            'manager_users.name as manager_name',
            'warehouses.name as warehouse_name'
        )
        ->join('addresses', 'manager_41_orders.address_id', '=', 'addresses.id')
        ->join('users',     'manager_41_orders.user_id',    '=', 'users.id')
        ->leftJoin('users as manager_users', 'users.manager_id', '=', 'manager_users.id')
        ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
        ->whereNotIn('manager_41_orders.code', function ($q) {
            $q->select('code')->from('manager_41_sub_orders');
        })
        ->where('manager_41_orders.delete_status', 0)
        ->where('manager_41_orders.created_at', '>=', '2025-04-05')
        ->orderBy('manager_41_orders.id', 'desc');

    // тЭЧ same scope rule here too
    if (!$forceAll && $userId !== 1 && $loggedWarehouseId) {
        $orders->where('users.warehouse_id', $loggedWarehouseId);
    }

    // Filters (as-is)
    if ($request->search) {
        $sort_search = $request->search;
        $orders->where('manager_41_orders.code', 'like', '%' . $sort_search . '%');
    }
    if ($request->manager) {
        $orders->where('users.manager_id', $request->manager);
    }
    if ($request->payment_status !== null && $request->payment_status !== '') {
        $orders->where('manager_41_orders.payment_status', $request->payment_status);
        $payment_status = $request->payment_status;
    }
    if ($request->delivery_status !== null && $request->delivery_status !== '') {
        $orders->where('manager_41_orders.delivery_status', $request->delivery_status);
        $delivery_status = $request->delivery_status;
    }
    if ($date) {
        $orders->whereBetween('manager_41_orders.created_at', [
            date('Y-m-d', strtotime(explode(' to ', $date)[0])) . ' 00:00:00',
            date('Y-m-d', strtotime(explode(' to ', $date)[1])) . ' 23:59:59',
        ]);
    }

    $orders = $orders->distinct()->paginate(15);

    return view(
        'backend.sales.allPendingForApprovalOrder',
        compact('orders', 'sort_search', 'payment_status', 'delivery_status', 'date', 'salzing_statuses', 'managerList')
    );
}

private function __manager41AllPendingForApprovalOrder(Request $request)
{

    $date            = $request->date;
    $sort_search     = null;
    $delivery_status = null;
    $payment_status  = '';

    // Manager-41 side: no Salezing log in most setups; send empty list to the same view
    $salzing_statuses = collect([]);

    $user  = auth()->user();
    $userId = (string) ($user->id ?? '');
    $loggedWarehouseId = $user->warehouse_id ?? null;

    // ----- (A) Build тАЬmanager listтАЭ from manager_41_orders still not split -----
    $forManagerOrders = Manager41Order::select(
            'manager_41_orders.*',
            'addresses.company_name',
            'users.warehouse_id',
            'manager_users.name as manager_name',
            'warehouses.name as warehouse_name'
        )
        ->join('addresses', 'manager_41_orders.address_id', '=', 'addresses.id')
        ->join('users',     'manager_41_orders.user_id',    '=', 'users.id')
        ->leftJoin('users as manager_users', 'users.manager_id', '=', 'manager_users.id')
        ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
        ->whereNotIn('manager_41_orders.code', function ($q) {
            $q->select('code')->from('manager_41_sub_orders');
        })
        ->where('manager_41_orders.delete_status', 0)
        ->where('manager_41_orders.created_at', '>=', '2025-04-05')
        ->orderBy('manager_41_orders.id', 'DESC');

    // Restrict by user warehouse (non-superadmin)
    if ($userId !== '1' && $loggedWarehouseId) {
        $forManagerOrders->where('users.warehouse_id', $loggedWarehouseId);
    }

    $forManagerOrders = $forManagerOrders->get();

    // Build Manager dropdown (same shape the view expects)
    $managerList = $forManagerOrders
        ->map(function ($o) {
            return [
                'id'   => optional($o->user->getManager)->id,
                'name' => optional($o->user->getManager)->name,
            ];
        })
        ->filter(fn ($m) => $m['id'])
        ->unique('id')
        ->pluck('name', 'id')
        ->toArray();

    // ----- (B) Main list query for Manager-41 pending orders -----
    $orders = Manager41Order::select(
            'manager_41_orders.*',
            'addresses.company_name',
            'addresses.due_amount',
            'addresses.overdue_amount',
            'addresses.dueDrOrCr',
            'addresses.overdueDrOrCr',
            'users.warehouse_id',
            'manager_users.name as manager_name',
            'warehouses.name as warehouse_name'
        )
        ->join('addresses', 'manager_41_orders.address_id', '=', 'addresses.id')
        ->join('users',     'manager_41_orders.user_id',    '=', 'users.id')
        ->leftJoin('users as manager_users', 'users.manager_id', '=', 'manager_users.id')
        ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
        ->whereNotIn('manager_41_orders.code', function ($q) {
            $q->select('code')->from('manager_41_sub_orders');
        })
        ->where('manager_41_orders.delete_status', 0)
        ->where('manager_41_orders.created_at', '>=', '2025-04-05')
        ->orderBy('manager_41_orders.id', 'desc');

    // Restrict by warehouse for non-superadmin users
    if ($userId !== '1' && $loggedWarehouseId) {
        $orders->where('users.warehouse_id', $loggedWarehouseId);
    }

    // ----- Filters (mirror original) -----
    if ($request->search) {
        $sort_search = $request->search;
        $orders->where('manager_41_orders.code', 'like', '%' . $sort_search . '%');
    }

    if ($request->manager) {
        $orders->where('users.manager_id', $request->manager);
    }

    if ($request->payment_status !== null && $request->payment_status !== '') {
        $orders->where('manager_41_orders.payment_status', $request->payment_status);
        $payment_status = $request->payment_status;
    }

    if ($request->delivery_status !== null && $request->delivery_status !== '') {
        $orders->where('manager_41_orders.delivery_status', $request->delivery_status);
        $delivery_status = $request->delivery_status;
    }

    if ($date) {
        $orders->whereBetween('manager_41_orders.created_at', [
            date('Y-m-d', strtotime(explode(' to ', $date)[0])) . ' 00:00:00',
            date('Y-m-d', strtotime(explode(' to ', $date)[1])) . ' 23:59:59',
        ]);
    }

    // (No Salezing filter on 41; skip)

    // Distinct + paginate
    $orders = $orders->distinct()->paginate(15);

    return view(
        'backend.sales.allPendingForApprovalOrder',
        compact('orders', 'sort_search', 'payment_status', 'delivery_status', 'date', 'salzing_statuses', 'managerList')
    );
}


   public function allPendingForApprovalOrder(Request $request) {



    if ($this->isActingAs41Manager()) {
        return $this->manager41AllPendingForApprovalOrder($request);
    }
    $date = $request->date;
    $sort_search     = null;
    $delivery_status = null;
    $payment_status  = '';
    // Get distinct Salezing Order Punch Status responses
    // $salzing_statuses = DB::table('salezing_logs')->distinct()->pluck('response');

    $admin_user_id = User::where('user_type', 'admin')->first()->id;
    $user = auth()->user();
    $userId = $user->id;
    $logedUserWarehouseId = $user->warehouse_id;

    // Define super admin and special staff
    $superAdminId = 1;
    $specialStaffIds = [180, 169, 25606];

    // Start building the query with the necessary joins
    // $forManagerOrders = Order::select(
    //   'orders.*', 
    //   'addresses.company_name', 
    //   'salezing_logs.response', 
    //   'salezing_logs.status', 
    //   'users.warehouse_id', 
    //   'manager_users.name as manager_name', 
    //   'warehouses.name as warehouse_name' // Select warehouse name and alias it as warehouse_name
    // )
    // ->join('addresses', 'orders.address_id', '=', 'addresses.id')
    // ->leftJoin('salezing_logs', DB::raw('CAST(orders.code AS CHAR CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci)'), '=', 'salezing_logs.code')
    // ->join('users', 'orders.user_id', '=', 'users.id') // Join users table
    // ->leftJoin('users as manager_users', 'users.manager_id', '=', 'manager_users.id') // Join for manager details
    // ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id') // Join warehouses table to get warehouse name
    // ->whereNotIn('orders.code', function($query) {
    //     $query->select('code')->from('sub_orders');
    // })
    // ->where('orders.delete_status', 0)
    // ->where('orders.created_at', '>=', '2025-04-05')
    // ->orderBy('id', 'DESC');

    // // Apply filters based on the current route and user permissions
    // if (Route::currentRouteName() == 'inhouse_orders.index' && Auth::user()->can('view_inhouse_orders')) {
    //     $forManagerOrders = $forManagerOrders->where('orders.seller_id', '=', $admin_user_id);
    // } else if (Route::currentRouteName() == 'seller_orders.index' && Auth::user()->can('view_seller_orders')) {
    //     $forManagerOrders = $forManagerOrders->where('orders.seller_id', '!=', $admin_user_id);
    // }

    // if($userId != "1"){
    //   $forManagerOrders = $forManagerOrders->where('users.warehouse_id',$logedUserWarehouseId);
    // }
    // $forManagerOrders = $forManagerOrders->get();
    // // echo "<pre>"; print_r($forManagerOrders); die;
    // $managerList = $forManagerOrders
    // ->map(function ($subOrder) {
    //     return [
    //         'id' => optional($subOrder->user->getManager)->id,
    //         'name' => optional($subOrder->user->getManager)->name,
    //     ];
    // })
    // ->filter(fn($m) => $m['id']) // Remove null managers
    // ->unique('id') // Keep only unique manager IDs
    // ->pluck('name', 'id') // Set manager_id as key, name as value
    // ->toArray();

    $managerList = array();

    

    // Start building the query with the necessary joins
    $orders = Order::select(
      'orders.*', 
      'addresses.company_name', 
      'addresses.due_amount',
      'addresses.overdue_amount',
      'addresses.dueDrOrCr',
      'addresses.overdueDrOrCr',
      'salezing_logs.response', 
      'salezing_logs.status', 
      'users.warehouse_id', 
      'manager_users.name as manager_name', 
      'warehouses.name as warehouse_name' // Select warehouse name and alias it as warehouse_name
    )
    ->join('addresses', 'orders.address_id', '=', 'addresses.id')
    // ->leftJoin('salezing_logs', DB::raw('CAST(orders.code AS CHAR CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci)'), '=', 'salezing_logs.code')
    ->leftJoin(DB::raw('(
        SELECT * FROM salezing_logs AS sl1
        WHERE id IN (
            SELECT MAX(id)
            FROM salezing_logs
            GROUP BY code
        )
    ) as salezing_logs'), DB::raw('CAST(orders.code AS CHAR CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci)'), '=', 'salezing_logs.code')

    ->join('users', 'orders.user_id', '=', 'users.id') // Join users table
    ->leftJoin('users as manager_users', 'users.manager_id', '=', 'manager_users.id') // Join for manager details
    ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id') // Join warehouses table to get warehouse name
    // ->whereNotIn('orders.code', function($query) {
    //     $query->select('code')->from('sub_orders');
    // })
    ->where('orders.sub_order_status','0')    
    ->orderBy('orders.id', 'desc');

    // Apply filters based on the current route and user permissions
    if (Route::currentRouteName() == 'inhouse_orders.index' && Auth::user()->can('view_inhouse_orders')) {
        $orders = $orders->where('orders.seller_id', '=', $admin_user_id);
    } else if (Route::currentRouteName() == 'seller_orders.index' && Auth::user()->can('view_seller_orders')) {
        $orders = $orders->where('orders.seller_id', '!=', $admin_user_id);
    }

    if($userId != "1"){
      $orders = $orders->where('users.warehouse_id',$logedUserWarehouseId);
    }


    // Apply search filters
    if ($request->search) {
        $sort_search = $request->search;
        $orders = $orders->where('orders.code', 'like', '%' . $sort_search . '%');
    }
    // if ($request->manager) {
    //     $sort_search = $request->manager;
    //     $orders = $orders->where('users.manager_id', $request->manager);
    // }
    if ($request->payment_status != null) {
        $orders = $orders->where('orders.payment_status', $request->payment_status);
        $payment_status = $request->payment_status;
    }
    if ($request->delivery_status != null) {
        $orders = $orders->where('orders.delivery_status', $request->delivery_status);
        $delivery_status = $request->delivery_status;
    }
    if ($date != null) {
        $orders = $orders->whereBetween('orders.created_at', [
            date('Y-m-d', strtotime(explode(" to ", $date)[0])) . ' 00:00:00',
            date('Y-m-d', strtotime(explode(" to ", $date)[1])) . ' 23:59:59'
        ]);
    }

    // Apply Salzing Order Punch Status filter if selected
    // if ($request->salzing_status != null) {
    //     $orders = $orders->where('salezing_logs.response', $request->salzing_status);
    // }
    $orders = $orders->where('orders.delete_status', '0')->where('orders.created_at', '>=', '2025-04-05');
    if (Auth::id() != 1) {
      $orders = $orders->where('users.warehouse_id', Auth::user()->warehouse_id);
    }

    // // 1) Turn it into a subquery of IDs (drop any ORDER BYs)
    // $idsSubquery = (clone $orders)
    //     ->reorder()               // <-- fix: singular
    //     ->select('orders.id');

    // // 2) Do an UPDATE ... JOIN (avoids MySQL 1093 error)
    // DB::table('orders as o')
    // ->joinSub($idsSubquery, 'ids', 'ids.id', '=', 'o.id')
    // ->update([
    //     'o.sub_order_status' => 1,
    //     'o.updated_at'       => now(),
    // ]);
    // Paginate and return the view with the orders and additional filters
    $orders = $orders->distinct()->paginate(15);
    // $orders = $orders->distinct()->get();

    // foreach($orders as $key => $order){
    //     $getSplitOrder = SubOrder::where('code',$order->code)->first();
    //     $data_status=0;
    //     if($getSplitOrder != NULL){
    //         $data_status=1;
    //     }
    //     if($getSplitOrder == NULL OR $getSplitOrder->status == 'draft'){

    //     }else{
    //         $order->sub_order_status = 1;
    //         $order->save();
    //     }
    // }
    // echo "Update values.";die;
    // echo "<pre>"; print_r($orders); die;
    
    return view('backend.sales.allPendingForApprovalOrder', compact('orders', 'sort_search', 'payment_status', 'delivery_status', 'date', 'managerList'));

   }



public function manager41AllSplitOrder(Request $request)
{
    try {
        $user   = auth()->user();
        $userId = $user->id;

        // ---- Access control (Super Admin, special staff, role_id 4, OR manager_41) ----
        $superAdminId    = 27604;
        $specialStaffIds = [180, 169, 25606];
        $staff           = \App\Models\Staff::where('user_id', $userId)->first();

        $title       = strtolower(trim((string) $user->user_title));
        $type        = strtolower(trim((string) $user->user_type));
        $is41Manager = ($title === 'manager_41' || $type === 'manager_41');

        $isSuperAdmin       = ($userId == $superAdminId);
        $hasWarehouseAccess = $isSuperAdmin
                           || in_array($userId, $specialStaffIds, true)
                           || ($staff && (int)$staff->role_id === 4)
                           || $is41Manager;

        if (!$hasWarehouseAccess) {
            return back()->with('error', 'Access denied. You are not authorized to view orders.');
        }

        // Map warehouse_id to order prefix
        $warehousePrefixes = [
            1 => 'KOL',
            2 => 'DEL',
            6 => 'MUM',
        ];

        // ---- Build "manager list" for filter dropdown (same logic, manager41 tables) ----
        $forManager = Manager41SubOrder::with(['sub_order_details', 'user.getManager', 'user'])
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereHas('sub_order_details', function ($q) {
                    $q->where('pre_closed_status', "0")
                      ->where(function ($qq) {
                          $qq->whereColumn('challan_qty', '<', 'approved_quantity')
                             ->orWhereNull('challan_qty');
                      })
                      ->whereRaw(
                          'IFNULL(pre_closed, 0) + IFNULL(reallocated, 0) + IFNULL(challan_qty, 0) < approved_quantity'
                      );
                });
            })
            ->when(!$isSuperAdmin, function ($query) use ($user, $warehousePrefixes) {
                $prefix = $warehousePrefixes[$user->warehouse_id] ?? null;
                if (!$prefix) {
                    abort(403, 'Invalid warehouse assigned to user.');
                }
                $query->where('order_no', 'LIKE', "%{$prefix}%");
            })
            ->get()
            ->sortBy(fn ($o) => optional($o->user->getManager)->name);

        $managerList = $forManager
            ->map(fn ($so) => ['id' => optional($so->user->getManager)->id, 'name' => optional($so->user->getManager)->name])
            ->filter(fn ($m) => $m['id'])
            ->unique('id')
            ->pluck('name', 'id')
            ->toArray();

        // ---- Main query (manager41 tables) ----
        $query = Manager41SubOrder::with('sub_order_details', 'user')
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereHas('sub_order_details', function ($q) {
                    $q->where('pre_closed_status', "0")
                      ->where(function ($qq) {
                          $qq->whereColumn('challan_qty', '<', 'approved_quantity')
                             ->orWhereNull('challan_qty');
                      })
                      ->whereRaw(
                          'IFNULL(pre_closed, 0) + IFNULL(reallocated, 0) + IFNULL(challan_qty, 0) < approved_quantity'
                      );
                });
            });

        // Search (text or numeric subtotal)
        if ($request->search) {
            $sort_search = $request->search;
            $query->where(function ($q) use ($sort_search) {
                if (is_numeric($sort_search)) {
                    $q->whereHas('sub_order_details', function ($q2) use ($sort_search) {
                        $q2->selectRaw('sub_order_id, SUM(price * approved_quantity) as subtotal')
                           ->havingRaw('CEIL(SUM(price * approved_quantity)) = ?', [(float) $sort_search]);
                    });
                } else {
                    $q->where('order_no', 'like', '%' . $sort_search . '%')
                      ->orWhereHas('sub_order_details.product_data', function ($q2) use ($sort_search) {
                          $q2->where('part_no', 'like', '%' . $sort_search . '%');
                      })
                      ->orWhereHas('sub_order_details.product_data', function ($q2) use ($sort_search) {
                          $q2->where('name', 'like', '%' . $sort_search . '%');
                      })
                      ->orWhereHas('user', function ($q2) use ($sort_search) {
                          $q2->where('company_name', 'like', '%' . $sort_search . '%');
                      });
                }
            });
        }

        // Manager filter
        if ($request->manager && $request->manager !== '') {
            $manager = $request->manager;
            $query->whereHas('user.getManager', function ($q) use ($manager) {
                $q->where('id', $manager);
            });
        }

        // Warehouse filter for non-super admin
        if (!$isSuperAdmin) {
            $prefix = $warehousePrefixes[$user->warehouse_id] ?? null;
            if (!$prefix) {
                return back()->with('error', 'Invalid warehouse assigned to user.');
            }
            $query->where('order_no', 'LIKE', "%{$prefix}%");
        }

        $orderData = $query->orderBy('id', 'DESC')->paginate(20);

        // Add sub_total
        foreach ($orderData as $order) {
            $order->sub_total = $order->sub_order_details->sum(
                fn ($detail) => $detail->price * $detail->approved_quantity
            );
        }

        return view('backend.sales.all_split_order', compact('orderData', 'managerList'));

    } catch (\Exception $e) {
        $errorCode    = $e->getCode();
        $errorMessage = ($errorCode == 23000) ? __("direct link already exists") : $e->getMessage();
        return ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }
}

  public function allSplitOrder(Request $request) {

    // if current logged-in user is a 41 manager, use the special handler
    if ($this->isActingAs41Manager()) {
        return $this->manager41AllSplitOrder($request);
    }
    try {
        $user = auth()->user();
        $userId = $user->id;

        // Define super admin and special staff
        $superAdminId = 1;
        $specialStaffIds = [180, 169, 25606];

        // Check staff table
        $staff = \App\Models\Staff::where('user_id', $userId)->first();

        // Access rules
        $isSuperAdmin = $userId == $superAdminId;
        $hasWarehouseAccess = false;

        if ($isSuperAdmin) {
            $hasWarehouseAccess = true;
        } elseif (in_array($userId, $specialStaffIds)) {
            $hasWarehouseAccess = true;
        } elseif ($staff && $staff->role_id == 4) {
            $hasWarehouseAccess = true;
        }

        // Access denied
        if (!$hasWarehouseAccess) {
            return back()->with('error', 'Access denied. You are not authorized to view orders.');
        }

        // Map warehouse_id to order prefix
        $warehousePrefixes = [
            1 => 'KOL',
            2 => 'DEL',
            6 => 'MUM',
        ];

        $forManager = SubOrder::with(['sub_order_details', 'user.getManager', 'user'])
        ->where('status', 'completed')
        ->where(function ($query) {
            $query->whereHas('sub_order_details', function ($query) {
                $query->where('pre_closed_status', "0")
                    ->where(function ($q) {
                        $q->whereColumn('challan_qty', '<', 'approved_quantity')
                          ->orWhereNull('challan_qty');
                    })
                    ->whereRaw(
                        'IFNULL(pre_closed, 0) + IFNULL(reallocated, 0) + IFNULL(challan_qty, 0) < approved_quantity'
                    );
            });
        })
        ->when(!$isSuperAdmin, function ($query) use ($user, $warehousePrefixes) {
            $prefix = $warehousePrefixes[$user->warehouse_id] ?? null;
            if (!$prefix) {
                abort(403, 'Invalid warehouse assigned to user.');
            }
            $query->where('order_no', 'LIKE', "%{$prefix}%");
        })
        ->get()
        ->sortBy(function ($order) {
            return optional($order->user->getManager)->name;
        });

        $managerList = $forManager
        ->map(function ($subOrder) {
            return [
                'id' => optional($subOrder->user->getManager)->id,
                'name' => optional($subOrder->user->getManager)->name,
            ];
        })
        ->filter(fn($m) => $m['id']) // Remove null managers
        ->unique('id') // Keep only unique manager IDs
        ->pluck('name', 'id') // Set manager_id as key, name as value
        ->toArray();


        $query = SubOrder::with('sub_order_details', 'user')
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereHas('sub_order_details', function ($query) {
                    $query->where('pre_closed_status', "0")
                        ->where(function ($q) {
                            $q->whereColumn('challan_qty', '<', 'approved_quantity')
                              ->orWhereNull('challan_qty');
                        })
                        ->whereRaw(
                            'IFNULL(pre_closed, 0) + IFNULL(reallocated, 0) + IFNULL(challan_qty, 0) < approved_quantity'
                        );
                });
            });

        // Apply search text filters (text or number)
        if ($request->search) {
            $sort_search = $request->search;
            $query->where(function ($q) use ($sort_search) {
              // Numeric subtotal filter only if it's numeric
              if (is_numeric($sort_search)) {
                $q->whereHas('sub_order_details', function ($q2) use ($sort_search) {
                    $q2->selectRaw('sub_order_id, SUM(price * approved_quantity) as subtotal')
                        ->havingRaw('CEIL(SUM(price * approved_quantity)) = ?', [(float) $sort_search]);
                });
              }else{
                $q->where('order_no', 'like', '%' . $sort_search . '%')
                ->orWhereHas('sub_order_details.product_data', function ($q2) use ($sort_search) {
                    $q2->where('part_no', 'like', '%' . $sort_search . '%');
                })
                ->orWhereHas('sub_order_details.product_data', function ($q2) use ($sort_search) {
                    $q2->where('name', 'like', '%' . $sort_search . '%');
                })
                ->orWhereHas('user', function ($q2) use ($sort_search) {
                    $q2->where('company_name', 'like', '%' . $sort_search . '%');
                });
                
              }
            });
        }
        // Apply manager filter (always independent AND filter)
        if ($request->manager AND $request->manager!= "" ) {
            $manager = $request->manager;
            $query->whereHas('user.getManager', function ($q) use ($manager) {
                $q->where('id', $manager);
            });
        }

        // Apply warehouse filter if not super admin
        if (!$isSuperAdmin) {
            $prefix = $warehousePrefixes[$user->warehouse_id] ?? null;
            if (!$prefix) {
                return back()->with('error', 'Invalid warehouse assigned to user.');
            }
            $query->where('order_no', 'LIKE', "%{$prefix}%");
        }

        // Final data
        $orderData = $query->orderBy('id', 'DESC')->paginate(20);
        // Add sub_total to each order
        foreach ($orderData as $order) {
          $order->sub_total = $order->sub_order_details->sum(function ($detail) {
              return $detail->price * $detail->approved_quantity;
          });
        }

        // echo "<pre>"; print_r($orderData); die;
        return view('backend.sales.all_split_order', compact('orderData','managerList'));

      } catch (\Exception $e) {
          $errorCode = $e->getCode();
          $errorMessage = ($errorCode == 23000)
              ? __("direct link already exists")
              : $e->getMessage();

          return ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
      }
  } 
  


      // Manager-41: simple pre-close (no BTR, no notifications)
    public function manager41SavePreClose(Request $request){
        try {
            // Basic validation
            if (!$request->filled('sub_order_details_id')) {
                return back()->withInput()->with('error', 'Please enter sub order details id.');
            }
            // allow "0", so use is_numeric instead of filled()
            if (!is_numeric($request->pre_closed)) {
                return back()->withInput()->with('error', 'Please enter pre closed quantity.');
            }
    
            // Load Manager-41 detail row
            $detail = Manager41SubOrderDetail::find($request->sub_order_details_id);
            if (!$detail) {
                return back()->with('error', 'Sub Order Details not found.');
            }
    
            // Numbers weтАЩll use
            $approved   = (int) ($detail->approved_quantity ?? 0);
            $challanQty = (int) ($detail->challan_qty ?? 0);
            $already    = (int) ($detail->pre_closed ?? 0);
            $addReq     = max((int) $request->pre_closed, 0);
    
            // DonтАЩt let pre_closed exceed (approved - challan - already)
            $maxCanClose = max($approved - $challanQty - $already, 0);
            $toAdd       = min($addReq, $maxCanClose);
    
            // Apply updates
            $detail->pre_closed_by = $detail->warehouse_id;
            $detail->pre_closed    = $already + $toAdd;
    
            // Mark done if (pre_closed + challan) meets/exceeds approved
            if (($detail->pre_closed + $challanQty) >= $approved) {
                $detail->pre_closed_status = '1';
            }
    
            // No BTR, no in_transit handling here
            $detail->save();
    
            // Redirect
            if ($request->filled('redirect')) {
                return redirect()->route($request->redirect)->with('success_msg', 'Successfully Updated.');
            }
    
            // Fallback: return to previous page
            return back()->with('success_msg', 'Successfully Updated.');
    
        } catch (\Exception $e) {
            $errorCode    = $e->getCode();
            $errorMessage = ($errorCode == 23000) ? __("direct link already exists") : $e->getMessage();
            return back()->with('error', $errorMessage);
        }
    }

    public function savePreClose(Request $request){
        // If 41 Manager, route to the Manager-41 version (unchanged)
        if ($this->isActingAs41Manager()) {
            return $this->manager41SavePreClose($request);
        }
    
        // Basic validation
        if (!$request->filled('sub_order_details_id')) {
            return back()->withInput()->with('error', 'Please enter sub order details id.');
        }
        if (!$request->filled('sub_order_type')) {
            return back()->withInput()->with('error', 'Please provide sub_order_type (sub_order or btr).');
        }
    
        // Optional flags to select behavior:
        // - propagate_to_main = 1   → Case 2
        // - close_linked_btr  = 1   → Case 3
        // No flags:
        //   sub_order  → Case 1
        //   btr        → Case 4
    
        try {
            DB::transaction(function () use ($request) {
    
                // Helper: coalesce ints
                $i = function ($v) { return (int) ($v ?? 0); };
    
                // Load current row with lock
                /** @var SubOrderDetail $current */
                $current = SubOrderDetail::lockForUpdate()->findOrFail($request->sub_order_details_id);
    
                // Identify linked rows (if any)
                $btrOrderId = $request->input('has_btr_order_id'); // may be null / ""
                $main = null;  // type='sub_order'
                $btr  = null;  // type='btr'
    
                if ($request->sub_order_type === 'sub_order') {
                    $main = $current; // current is the main line
                    if (!empty($btrOrderId)) {
                        $btr = SubOrderDetail::where([
                            'product_id'   => $main->product_id,
                            'sub_order_id' => $btrOrderId,
                            'type'         => 'btr',
                        ])->lockForUpdate()->first();
                    }
                } else { // current is a BTR line
                    $btr = $current;
                    // Find its main by product + order_details_id (your current convention)
                    $main = SubOrderDetail::where([
                        'product_id'       => $btr->product_id,
                        'order_details_id' => $btr->order_details_id,
                        'type'             => 'sub_order',
                    ])->lockForUpdate()->first();
                }
    
                // Small guards for cases that need a linked line
                $propagateToMain = $request->boolean('propagate_to_main'); // Case 2
                $closeLinkedBtr  = $request->boolean('close_linked_btr');  // Case 3
    
                if ($request->sub_order_type === 'btr' && $propagateToMain && !$main) {
                    throw new \RuntimeException("Linked main sub_order not found for this BTR line.");
                }
                if ($request->sub_order_type === 'sub_order' && $closeLinkedBtr && !$btr) {
                    throw new \RuntimeException("Linked BTR sub_order not found for this main line.");
                }
    
                // Canonical getters
                $getNums = function (SubOrderDetail $row) use ($i) {
                    return [
                        'approved'   => $i($row->approved_quantity),
                        'challan'    => $i($row->challan_quantity), // treat null as 0 per your rule
                        'in_transit' => $i($row->in_transit),
                        'pre_closed' => $i($row->pre_closed),
                    ];
                };
    
                // Canonical setter to recompute status per rule:
                // status = 1 iff challan_qty + (pre_closed + in_transit) >= approved_quantity
                $recomputeStatus = function (SubOrderDetail $row) use ($getNums) {
                    ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($row);
                    $row->pre_closed_status = (($C + ($P + $T)) >= $A) ? 1 : 0;
                };
    
                // Clamp add to pre_closed (never exceed approved - challan - in_transit)
                $precloseDelta = function (SubOrderDetail $row, int $add) use ($getNums) : int {
                    ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($row);
                    $remaining = max(0, $A - $C - $T - $P);
                    $delta     = max(0, min($add, $remaining));
                    $row->pre_closed = $P + $delta;
                    $row->pre_closed_by = $row->warehouse_id;
                    return $delta;
                };
    
                // Move some quantity from in_transit → pre_closed (bounded by both)
                $moveTransitToPreclose = function (SubOrderDetail $row, int $qty) use ($getNums) : int {
                    ['in_transit'=>$T] = $getNums($row);
                    $delta = max(0, min($qty, $T));
                    $row->in_transit = $T - $delta;
                    // Add same delta into pre_closed, but still cap total against approved - challan - new_in_transit
                    return $delta;
                };
    
                // ===== CASES =====
    
                if ($request->sub_order_type === 'sub_order' && !$closeLinkedBtr) {
                    // --------------------------------
                    // CASE 1: Close remaining on MAIN based on challan vs approved (no BTR propagation)
                    // remaining_to_preclose = approved - challan - in_transit - pre_closed
                    ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($main);
                    $remaining = max(0, $A - $C - $T - $P);
                    $precloseDelta($main, $remaining);
                    $recomputeStatus($main);
    
                    $main->save();
                }
                elseif ($request->sub_order_type === 'btr' && !$propagateToMain) {
                    // --------------------------------
                    // CASE 4: Close BTR only as per Case 1
                    ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($btr);
                    $remaining = max(0, $A - $C - $T - $P);
                    $precloseDelta($btr, $remaining);
                    $recomputeStatus($btr);
    
                    $btr->save();
                }
                elseif ($request->sub_order_type === 'btr' && $propagateToMain) {
                    // --------------------------------
                    // CASE 2: Close BTR as in Case 1, then propagate the **BTR newly preclosed qty**
                    // to MAIN by moving it out of MAIN in_transit → MAIN pre_closed (DO NOT force status=1)
                    // Step 2 status rule applies naturally via recomputeStatus.
    
                    // Step 2.1: Close BTR per Case 1
                    ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($btr);
                    $btrRemaining = max(0, $A - $C - $T - $P);
                    $btrClosedNow = $precloseDelta($btr, $btrRemaining); // how much we actually added now
                    $recomputeStatus($btr);
                    $btr->save();
    
                    if ($btrClosedNow > 0 && $main) {
                        // Step 2.2: Subtract this "closed on BTR" qty from MAIN in_transit
                        // and add to MAIN pre_closed (bounded by its own constraints)
                        $moved = $moveTransitToPreclose($main, $btrClosedNow); // reduce in_transit first
                        if ($moved > 0) {
                            $precloseDelta($main, $moved); // push same quantity into pre_closed, capped
                        }
                        // DO NOT force status to 1; recompute by the rule
                        $recomputeStatus($main);
                        $main->save();
                    }
                }
                elseif ($request->sub_order_type === 'sub_order' && $closeLinkedBtr) {
                    // --------------------------------
                    // CASE 3: Close MAIN; quantity to move = approved - (challan + in_transit) - pre_closed
                    // (If challan is null → treat as 0; already handled)
                    // After closing main, use the SAME quantity to close linked BTR and set BTR status = 1.
    
                    // 3.1 Close MAIN with its remaining qty
                    ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($main);
                    $mainRemaining = max(0, $A - $C - $T - $P);
                    $mainClosedNow = $precloseDelta($main, $mainRemaining);
                    // In this case you said: "update the status also" (force close main if we just consumed all remaining)
                    $recomputeStatus($main);
                    $main->save();
    
                    if ($btr && $mainClosedNow > 0) {
                        // 3.2 Close linked BTR with the SAME qty (capped by BTR's own remaining)
                        ['approved'=>$BA,'challan'=>$BC,'in_transit'=>$BT,'pre_closed'=>$BP] = $getNums($btr);
                        $btrRemaining = max(0, $BA - $BC - $BT - $BP);
                        $btrDelta     = min($mainClosedNow, $btrRemaining);
    
                        if ($btrDelta > 0) {
                            $precloseDelta($btr, $btrDelta);
                        }
                        // For Case 3: "update the status to 1" for BTR after closing
                        $recomputeStatus($btr);
                        $btr->pre_closed_status = 1; // explicit per your instruction
                        $btr->save();
                    }
                }
    
                // ---- Optional: WhatsApp/PDF hooks unchanged logic ----
                // Trigger messages only on main closure actions (Case 1 & Case 3) or when BTR triggers a main adjust (Case 2)
                try {
                    if (
                        ($request->sub_order_type === 'sub_order') ||
                        ($request->sub_order_type === 'btr' && $propagateToMain)
                    ) {
                        $subOrderDetailId = (int) $request->sub_order_details_id;
                        $pdfUrl = $this->generateUnavailableProductsPDF($subOrderDetailId);
                        $this->sendUnavaliableProductsNotification($subOrderDetailId, $pdfUrl);
                    }
                } catch (\Exception $ex) {
                    \Log::error('Error generating PDF or sending WhatsApp: ' . $ex->getMessage());
                }
            });
    
            if ($request->filled('redirect')) {
                return redirect()->route($request->redirect)->with('success_msg', 'Successfully Updated.');
            }
            return redirect()
                ->route('order.splitOrderDetails', ['order_id' => $request->sub_order_id])
                ->with('success_msg', 'Successfully Updated.');
    
        } catch (\Throwable $e) {
            $errorCode = (int) $e->getCode();
            $errorMessage = ($errorCode === 23000) ? __("direct link already exists") : $e->getMessage();
            return back()->with('error', $errorMessage)->withInput();
        }
    }

  public function __savePreClose(Request $request)
  {

    // If 41 Manager, route to the Manager-41 version
        if ($this->isActingAs41Manager()) {
            return $this->manager41SavePreClose($request);
        }

        try {
            if (!isset($request->sub_order_details_id)) {
                return redirect()->back()->withInput()->with('error', 'Please enter sub order details id.');
            } elseif (!isset($request->pre_closed) && !isset($request->main_branch_pre_closed)) {
                return redirect()->back()->withInput()->with('error', 'Please enter pre closed quantity.');
            } elseif (isset($request->main_branch_pre_closed) && $request->main_branch_pre_closed > $request->btr_qty) {
                return redirect()->back()->withInput()->with('error', 'Please enter BTR Pre Close Quantity not more than '.$request->btr_qty);
            }
    
            $getData = SubOrderDetail::where('id', $request->sub_order_details_id)->first();
            if (!$getData) {
                return redirect()->back()->with('error', 'Sub Order Details not found.');
            }
    
            $getData->pre_closed_by = $getData->warehouse_id;
            $getData->pre_closed = $getData->pre_closed + $request->pre_closed;
            $getData->in_transit = 0;
    
            $btr_order_id = $request->has_btr_order_id;
            $pre_closed = $request->pre_closed;
            $btr_pre_closed = $request->main_branch_pre_closed ?: 0;
            $btr_qty = $request->btr_qty;
            $sub_order_qty = $request->sub_order_qty;
    
            if ($request->sub_order_type == 'btr' && isset($request->main_order_close) && $request->main_order_close == 1) {
                $getMainOrderData = SubOrderDetail::where('product_id', $getData->product_id)
                    ->where('order_details_id', $getData->order_details_id)
                    ->where('type', 'sub_order')
                    ->first();
    
                if ($getMainOrderData && $getMainOrderData->approved_quantity == ($getMainOrderData->pre_closed + $pre_closed)) {
                    $getMainOrderData->pre_closed_status = '1';
                    $getData->pre_closed_status = '1';
                }
    
                if ($getMainOrderData) {
                    $getMainOrderData->pre_closed_by = $getData->warehouse_id;
                    $getMainOrderData->pre_closed += $pre_closed;
                    $getMainOrderData->in_transit -= $pre_closed;
                    $getMainOrderData->save();
                }
    
                $getData->pre_closed_by = $getData->warehouse_id;
    
            } elseif ($request->sub_order_type == 'btr' && !isset($request->main_order_close)) {
                $getMainOrderData = SubOrderDetail::where('product_id', $getData->product_id)
                    ->where('order_details_id', $getData->order_details_id)
                    ->where('type', 'sub_order')
                    ->first();
    
                if ($getMainOrderData && $getMainOrderData->approved_quantity == ($getMainOrderData->pre_closed + $pre_closed)) {
                    $getMainOrderData->pre_closed_status = '1';
                    $getData->pre_closed_status = '1';
                }
    
                if ($getMainOrderData) {
                    $getMainOrderData->pre_closed_by = $getData->warehouse_id;
                    $getMainOrderData->in_transit -= $pre_closed;
                    $getMainOrderData->save();
                }
    
                $getData->pre_closed_by = $getData->warehouse_id;
    
            } elseif ($request->sub_order_type == 'sub_order') {
                $getBTROrderData = null;
                $btrPreClosedQty = 0;
    
                if ($btr_order_id != "") {
                    $getBTROrderData = SubOrderDetail::where('product_id', $getData->product_id)
                        ->where('sub_order_id', $btr_order_id)
                        ->where('type', 'btr')
                        ->first();
    
                    // Safe exit if BTR expected but not found
                    if (!$getBTROrderData) {
                        return redirect()->back()->with('error', 'BTR Sub Order not found for this product.');
                    }
                }
    
                $getMainOrderData = SubOrderDetail::where('product_id', $getData->product_id)
                    ->where('sub_order_id', $getData->sub_order_id)
                    ->where('type', 'sub_order')
                    ->first();
    
                if ($getMainOrderData) {
                    if ($pre_closed != "" && $getMainOrderData->approved_quantity == ($getMainOrderData->pre_closed + $pre_closed)) {
                        $getMainOrderData->pre_closed_status = '1';
    
                        if ($btr_order_id != "" && $getBTROrderData) {
                            $getBTROrderData->pre_closed_status = '1';
                            $getBTROrderData->pre_closed_by = $getData->warehouse_id;
                            $getBTROrderData->pre_closed = $getBTROrderData->approved_quantity;
                        }
                    }
    
                    $getMainOrderData->pre_closed_by = $getData->warehouse_id;
                    $getMainOrderData->pre_closed += $pre_closed;
                }
    
                if ($pre_closed != "") {
                    if ($pre_closed <= $btr_qty && $btr_order_id != "") {
                        if ($pre_closed < $btr_pre_closed) {
                            $getMainOrderData->in_transit -= $btr_pre_closed;
                        } else {
                            $getMainOrderData->in_transit = $getMainOrderData->in_transit ? $getMainOrderData->in_transit - $pre_closed : $pre_closed;
                        }
    
                        if ($getBTROrderData && $getMainOrderData->approved_quantity >= ($getMainOrderData->pre_closed + $pre_closed)) {
                            $btrPreClosedQty = $btr_pre_closed ?: $pre_closed;
                            $getBTROrderData->pre_closed += $btrPreClosedQty;
                            $getBTROrderData->pre_closed_by = $getData->warehouse_id;
                        }
                    } elseif ($pre_closed > $btr_qty && $btr_order_id != "") {
                        if (!$btr_pre_closed) {
                            if ($getBTROrderData && $getBTROrderData->pre_closed_status == 0) {
                                $getMainOrderData->in_transit = $getMainOrderData->approved_quantity - $pre_closed;
    
                                $btrPreClosedQty = $pre_closed - $getBTROrderData->approved_quantity;
                                $getBTROrderData->pre_closed = $btrPreClosedQty;
                                $getBTROrderData->pre_closed_by = $getData->warehouse_id;
    
                                if ($pre_closed == $sub_order_qty) {
                                    $getMainOrderData->pre_closed_status = '1';
                                    $getBTROrderData->pre_closed_status = '1';
                                }
                            }
                        } else {
                            $getMainOrderData->in_transit = $getMainOrderData->approved_quantity - ($pre_closed + $btr_pre_closed);
    
                            if ($getBTROrderData) {
                                $btrPreClosedQty = ($pre_closed - $getBTROrderData->approved_quantity) + $btr_pre_closed;
                                $getBTROrderData->pre_closed = $btrPreClosedQty;
                                $getBTROrderData->pre_closed_by = $getData->warehouse_id;
                            }
                        }
                    } else {
                        $getMainOrderData->pre_closed += ($pre_closed + $btr_pre_closed);
                        $getMainOrderData->pre_closed_by = $getData->warehouse_id;
                    }
                } elseif ($pre_closed == "" && $btr_pre_closed != "") {
                    $getMainOrderData->in_transit -= $btr_pre_closed;
    
                    if ($getBTROrderData) {
                        $btrPreClosedQty = $btr_pre_closed;
                        $getBTROrderData->pre_closed = $btrPreClosedQty;
                        $getBTROrderData->pre_closed_by = $getData->warehouse_id;
                    }
                }
    
                if ($btrPreClosedQty == $btr_qty && $getBTROrderData) {
                    $getBTROrderData->pre_closed_status = '1';
                }
    
                if (($pre_closed + $btr_pre_closed) == $getMainOrderData->approved_quantity) {
                    $getMainOrderData->pre_closed_status = '1';
                    $getMainOrderData->pre_closed = $pre_closed + $btr_pre_closed;
                    $getMainOrderData->pre_closed_by = $getData->warehouse_id;
                } else {
                    if ($getMainOrderData->pre_closed === null) {
                        $getMainOrderData->pre_closed = $pre_closed + $btr_pre_closed;
                        $getMainOrderData->pre_closed_by = $getData->warehouse_id;
                    }
                }
    
                $getMainOrderData->save();
                if ($getBTROrderData) {
                    $getBTROrderData->save();
                }
            }
    
            if ($request->sub_order_type != 'sub_order') {
                $getData->save();
            }
    
            // WhatsApp Notification: for sub_order or btr + main close
            if ($request->sub_order_type == 'sub_order' || ($request->sub_order_type == 'btr' && isset($request->main_order_close) && $request->main_order_close == 1)) {
                try {
                    $subOrderDetailId = $request->sub_order_details_id;
                    $pdfUrl = $this->generateUnavailableProductsPDF($subOrderDetailId);
                    $this->sendUnavaliableProductsNotification($subOrderDetailId, $pdfUrl);
                } catch (\Exception $ex) {
                    \Log::error('Error generating PDF or sending WhatsApp: ' . $ex->getMessage());
                }
            }
    
            if (isset($request->redirect)) {
                return redirect()->route($request->redirect)->with('success_msg', 'Successfully Updated.');
            }
    
            return redirect()->route('order.splitOrderDetails', ['order_id' => $request->sub_order_id])->with('success_msg', 'Successfully Updated.');
    
        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = ($errorCode == 23000) ? __("direct link already exists") : $e->getMessage();
            return redirect()->back()->with('error', $errorMessage);
        }
  }

  public function savePreCloseBackup11_06_2025(Request $request){
    try{
      if(!isset($request->sub_order_details_id)){
        return redirect()->back()->withInput()->with('error', 'Please enter sub order details id.');
      }elseif(!isset($request->pre_closed)){
        return redirect()->back()->withInput()->with('error', 'Please enter pre closed quantity.');
      }else{
        $getData = SubOrderDetail::where('id',$request->sub_order_details_id)->first();
        if (!$getData) {
            return redirect()->back()->with('error', 'Sub Order Details not found.');
        }

        $getData->pre_closed_by = $getData->warehouse_id;

        $getData->pre_closed = $getData->pre_closed + $request->pre_closed;
        $getData->in_transit = 0;

        $btr_order_id = $request->has_btr_order_id;
        $main_branch_pre_closed = $request->main_branch_pre_closed;

        // echo "<pre>"; print_r($request->all());die;        
        if($request->sub_order_type == 'btr' AND isset($request->main_order_close) AND $request->main_order_close == 1){          
          $getMainOrderData = SubOrderDetail::where('product_id',$getData->product_id)->where('order_details_id',$getData->order_details_id)->where('type','sub_order')->first();
          if($getMainOrderData->approved_quantity == ($getMainOrderData->pre_closed + $request->pre_closed)){
            $getMainOrderData->pre_closed_status = '1';
            $getData->pre_closed_status = '1';
          }
          
          $getMainOrderData->pre_closed_by = $getData->warehouse_id;
          $getMainOrderData->pre_closed = $getMainOrderData->pre_closed + $request->pre_closed;
          $getMainOrderData->in_transit = $getMainOrderData->in_transit - $request->pre_closed;
          $getMainOrderData->save();

          $getData->pre_closed_by = $getData->warehouse_id;

        }elseif($request->sub_order_type == 'btr' AND !isset($request->main_order_close)){
          $getMainOrderData = SubOrderDetail::where('product_id',$getData->product_id)->where('order_details_id',$getData->order_details_id)->where('type','sub_order')->first();
          if($getMainOrderData->approved_quantity == ($getMainOrderData->pre_closed + $request->pre_closed)){
            $getMainOrderData->pre_closed_status = '1';
            $getData->pre_closed_status = '1';
          }
          
          $getMainOrderData->pre_closed_by = $getData->warehouse_id;
          $getMainOrderData->in_transit = $getMainOrderData->in_transit - $request->pre_closed;
          $getMainOrderData->save();

          $getData->pre_closed_by = $getData->warehouse_id;
        }elseif($request->sub_order_type == 'sub_order'){
          if($btr_order_id != "" AND $main_branch_pre_closed != "") {
            $getMainOrderData = SubOrderDetail::where('product_id',$getData->product_id)->where('sub_order_id',$btr_order_id)->where('type','btr')->first();
          }else{
            $getMainOrderData = SubOrderDetail::where('product_id',$getData->product_id)->where('order_details_id',$getData->order_details_id)->where('type','btr')->first();
          }
          
          if($getMainOrderData != NULL){
            if($getMainOrderData->approved_quantity == ($getMainOrderData->pre_closed + $request->pre_closed)){
              $getData->pre_closed_status = '1';
            }
            $getMainOrderData->pre_closed_by = $getData->warehouse_id;
            if($btr_order_id != "" AND $main_branch_pre_closed != "") {
              $getMainOrderData->pre_closed = $main_branch_pre_closed;
              $getMainOrderData->in_transit = $getMainOrderData->in_transit - $main_branch_pre_closed;
            }elseif($btr_order_id != "" AND $main_branch_pre_closed == ""){
              $getMainOrderData->pre_closed_status = '0';
            }else{
              if($getMainOrderData->approved_quantity == ($getMainOrderData->pre_closed + $request->pre_closed)){
                $getMainOrderData->pre_closed_status = '1';
              }
              $getMainOrderData->pre_closed = $getMainOrderData->pre_closed + $request->pre_closed;              
            } 
            $getMainOrderData->save();
          }

          
        }

        $getData->save();

        // Separate WhatsApp logic for sub_order
        if ($request->sub_order_type == 'sub_order') {
            try {
                $subOrderDetailId = $request->sub_order_details_id;
                $pdfUrl = $this->generateUnavailableProductsPDF($subOrderDetailId);
                $this->sendUnavaliableProductsNotification($subOrderDetailId, $pdfUrl);
            } catch (\Exception $ex) {
                \Log::error('Error generating PDF or sending WhatsApp for SUB_ORDER: ' . $ex->getMessage());
            }
        }

        // Separate WhatsApp logic for BTR + main_order_close
        if ($request->sub_order_type == 'btr' && isset($request->main_order_close) && $request->main_order_close == 1) {
            try {
            
                $subOrderDetailId = $request->sub_order_details_id;
                $pdfUrl = $this->generateUnavailableProductsPDF($subOrderDetailId);
                $this->sendUnavaliableProductsNotification($subOrderDetailId, $pdfUrl);
            } catch (\Exception $ex) {
                \Log::error('Error generating PDF or sending WhatsApp for BTR: ' . $ex->getMessage());
            }
        }
        
        if(isset($request->redirect)){
          return redirect()->route($request->redirect);
        }else{
          return redirect()->route('order.splitOrderDetails', ['order_id' => $request->sub_order_id])->with('success_msg', 'Successfully Updated.');
        }
        
      }
    } catch (\Exception $e) {
        $errorCode = $e->getCode();
        if ($errorCode == 23000) {
            $errorMessage = __("direct link already exists");
        } else {
            $errorMessage = $e->getMessage();//"something went wrong, please try again";
        }
        return redirect()->back()->with('error', $errorMessage);
    }  
  }

    public function generateUnavailableProductsPDF($sub_order_details_id)
    {
        // Step 1: Get the specific SubOrderDetail with product
        $subOrderDetail = SubOrderDetail::with('product_data')->findOrFail($sub_order_details_id);

        // Step 2: Get related SubOrder and relations
        $subOrder = SubOrder::with(['user', 'order_warehouse', 'order'])->findOrFail($subOrderDetail->sub_order_id);

        $order = $subOrder->order;
        $userDetails = $subOrder->user;
        $warehouseName = $subOrder->order_warehouse->name ?? 'N/A';

        $approvedProducts = [];
        $unavailableItems = [];

        $product = $subOrderDetail->product_data;

        if ($product) {
            $entry = [
                'product_name' => $product->name,
                'slug' => $product->slug,
                'part_no' => $product->part_no,
                'rate' => $subOrderDetail->approved_rate ?? 0,
                'approved_qty' => $subOrderDetail->approved_quantity ?? 0,
                'pre_closed' => $subOrderDetail->pre_closed ?? 0,
                'bill_amount' => ($subOrderDetail->approved_quantity ?? 0) * ($subOrderDetail->approved_rate ?? 0),
                'is_new' => $subOrderDetail->new_item == 1,
            ];

            if ($subOrderDetail->approved_quantity > 0) {
                $approvedProducts[] = $entry;
            } else {
                $unavailableItems[] = [
                    'product_name' => $product->name,
                    'part_no' => $product->part_no,
                    'qty' => $subOrderDetail->pre_closed ?? 0,
                ];
            }
        }

        $groupedSubOrders = collect([[
            'warehouse_name' => $warehouseName,
            'approvedProducts' => $approvedProducts,
            'unavailableItems' => $unavailableItems,
        ]]);

        $pdfData = compact('order', 'userDetails', 'groupedSubOrders');

        $pdf = PDF::loadView('backend.sales.unavailable_products', $pdfData);
        $fileName = 'unavailable-products-' . $sub_order_details_id . '-' . uniqid() . '.pdf';
        $filePath = public_path('approved_products_pdf/' . $fileName);
        $pdf->save($filePath);

        $pdfURL = url('public/approved_products_pdf/' . $fileName);

        return $pdfURL;
    }

    private function sendUnavaliableProductsNotification($subOrderDetailsId, $pdfUrl)
    {
        try {
            $WhatsAppWebService = new WhatsAppWebService();

            // тЬЕ Get SubOrderDetail with product + relations
            $subOrderDetail = \App\Models\SubOrderDetail::findOrFail($subOrderDetailsId);

            // тЬЕ Determine if it's a BTR type
            if ($subOrderDetail->type === 'btr') {

                // тЬЕ For BTR тЖТ get actual sub_order_id from sub_order_details
                $btrSubOrder = \App\Models\SubOrder::findOrFail($subOrderDetail->sub_order_id);

                // тЬЕ From that row, get original sub_order_id
                $originalSubOrder = \App\Models\SubOrder::findOrFail($btrSubOrder->sub_order_id);
                

                // тЬЕ Get shipping address and phone from that suborder
                $shippingAddress = \App\Models\Address::find($originalSubOrder->shipping_address_id);
                $companyName = $shippingAddress->company_name ?? 'Valued Customer';
                $customerPhone = $shippingAddress->phone ?? null;

                // тЬЕ Get related order
                $order = \App\Models\Order::find($originalSubOrder->order_id);
                $orderCode = $order->code;
                $orderDate = \Carbon\Carbon::parse($order->created_at)->format('Y-m-d');

                // тЬЕ Manager Phone from BTR User
                $btrUser = \App\Models\User::find($btrSubOrder->user_id);
                $managerPhone = $this->getManagerPhone($btrUser->manager_id ?? null);
            } else {
                // тЬЕ Default flow for regular sub_order
                $subOrder = \App\Models\SubOrder::with(['user', 'order'])->findOrFail($subOrderDetail->sub_order_id);
                $user = $subOrder->user;
                $order = $subOrder->order;

                if (!$order || !$user) {
                    return response()->json(['error' => 'Missing order or user info.'], 404);
                }

                $shippingAddress = \App\Models\Address::find($subOrder->shipping_address_id);
                $companyName = $shippingAddress->company_name ?? ($user->company_name ?? 'Valued Customer');
                $customerPhone = $user->phone;
                $orderCode = $order->code;
                $orderDate = \Carbon\Carbon::parse($order->created_at)->format('Y-m-d');
                $managerPhone = $this->getManagerPhone($user->manager_id ?? null);
            }

            // тЬЕ Upload PDF and fetch media ID
            $media = $WhatsAppWebService->uploadMedia($pdfUrl);
            if (!isset($media['media_id'])) {
                return response()->json(['error' => 'Failed to upload media to WhatsApp.'], 500);
            }

            // тЬЕ WhatsApp Template Payload
            $templateData = [
                'name' => 'utility_unavailable_products',
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => [
                                    'filename' => 'Unavailable_Products_' . $orderCode . '.pdf',
                                    'id' => $media['media_id'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $companyName],   // {{1}}
                            ['type' => 'text', 'text' => $orderCode],     // {{2}}
                            ['type' => 'text', 'text' => $orderDate],     // {{3}}
                            ['type' => 'text', 'text' => $managerPhone],  // {{4}}
                        ],
                    ],
                ],
            ];

            // тЬЕ Send WhatsApp to Customer (For testing hardcoded 7044300330)
            $customerResponse = $WhatsAppWebService->sendTemplateMessage($customerPhone ?? '7044300330', $templateData);
            //$customerResponse = $WhatsAppWebService->sendTemplateMessage('7044300330', $templateData);

            // тЬЕ (Optional) Send WhatsApp to Manager
            $managerResponse = null;
            if ($managerPhone) {
                $managerResponse = $WhatsAppWebService->sendTemplateMessage($managerPhone, $templateData);
            }
            
            $extraResponse = $WhatsAppWebService->sendTemplateMessage('9894753728', $templateData);

            return response()->json([
                'customer_response' => $customerResponse,
                'manager_response' => $managerResponse,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
  


// ===== Manager-41 version =====
public function manager41AllPreClosedOrder(Request $request)
{
    $user = Auth::user();

    // ЁЯФУ Allow-all flag for special user
    $forceAll = ((int)($user->id ?? 0) === 27604);

    // Optional: restrict by logged-in warehouse using order prefix
    $warehousePrefixes = [
        1 => 'KOL',
        2 => 'DEL',
        6 => 'MUM',
    ];
    // тЭЧ if special user => null (no restriction)
    $ownPrefix = $forceAll ? null : ($warehousePrefixes[$user->warehouse_id ?? null] ?? null);

    // ---------- Build manager dropdown ----------
    $forManager = Manager41SubOrder::with(['sub_order_details','user.getManager','user'])
        ->where('status','completed')
        ->where(function ($q) {
            $q->whereHas('sub_order_details', function ($q2) {
                $q2->where('pre_closed_status','1')
                   ->where(function ($q3) {
                       $q3->whereColumn('challan_qty','<','approved_quantity')
                          ->orWhereNull('challan_qty');
                   })
                   ->whereRaw('IFNULL(pre_closed,0)+IFNULL(reallocated,0)+IFNULL(challan_qty,0) < approved_quantity');
            });
        })
        // тЬЕ apply prefix filter only when not special user
        ->when($ownPrefix, fn($q) => $q->where('order_no','like',"%{$ownPrefix}%"))
        ->get()
        ->sortBy(fn($so) => optional($so->user->getManager)->name);

    $managerList = $forManager
        ->map(fn($so) => ['id'=>optional($so->user->getManager)->id,'name'=>optional($so->user->getManager)->name])
        ->filter(fn($m) => $m['id'])
        ->unique('id')
        ->pluck('name','id')
        ->toArray();

    // ---------- Main dataset ----------
    $orders = Manager41SubOrder::with(['sub_order_details','user.getManager','user'])
        ->where('status','completed')
        ->whereHas('sub_order_details', fn($q) => $q->where('pre_closed_status','1'));

    // ЁЯФО Search
    if ($request->filled('search')) {
        $term = trim($request->search);
        $orders->where(function ($q) use ($term) {
            if (is_numeric($term)) {
                $q->whereIn('id', function ($sub) use ($term) {
                    $sub->select('sub_order_id')
                        ->from('manager_41_sub_order_details')
                        ->groupBy('sub_order_id')
                        ->havingRaw('CEIL(SUM(price * approved_quantity)) = ?', [(float)$term]);
                });
            } else {
                $q->where('order_no','like',"%{$term}%")
                  ->orWhereHas('sub_order_details.product', function ($q2) use ($term) {
                      $q2->where('part_no','like',"%{$term}%")
                         ->orWhere('name','like',"%{$term}%");
                  })
                  ->orWhereHas('sub_order_details.product_data', function ($q2) use ($term) {
                      $q2->where('part_no','like',"%{$term}%")
                         ->orWhere('name','like',"%{$term}%");
                  })
                  ->orWhereHas('user', fn($q2) => $q2->where('company_name','like',"%{$term}%"));
            }
        });
    }

    // ЁЯСд Manager filter
    if ($request->filled('manager')) {
        $orders->whereHas('user.getManager', fn($q) => $q->where('id', $request->manager));
    }

    // тЬЕ prefix filter only when not special user
    if ($ownPrefix) {
        $orders->where('order_no','like',"%{$ownPrefix}%");
    }

    $orderData = $orders->orderBy('id','DESC')->paginate(20);

    return view('backend.sales.all_pre_closed_order', compact('orderData','managerList'));
}



  public function allPreClosedOrder(Request $request){

    // ЁЯСЗ short-circuit for Manager-41
    if ($this->isActingAs41Manager()) {
        return $this->manager41AllPreClosedOrder($request);
    }
    try{
        $user = auth()->user();
        $userId = $user->id;

        // Define super admin and special staff
        $superAdminId = 1;
        $specialStaffIds = [180, 169, 25606];

        // Check staff table
        $staff = \App\Models\Staff::where('user_id', $userId)->first();

        // Access rules
        $isSuperAdmin = $userId == $superAdminId;
        $hasWarehouseAccess = false;

        if ($isSuperAdmin) {
            $hasWarehouseAccess = true;
        } elseif (in_array($userId, $specialStaffIds)) {
            $hasWarehouseAccess = true;
        } elseif ($staff && $staff->role_id == 4) {
            $hasWarehouseAccess = true;
        }

        // Access denied
        if (!$hasWarehouseAccess) {
            return back()->with('error', 'Access denied. You are not authorized to view orders.');
        }

        // Map warehouse_id to order prefix
        $warehousePrefixes = [
            1 => 'KOL',
            2 => 'DEL',
            6 => 'MUM',
        ];
        $forManager = SubOrder::with(['sub_order_details', 'user.getManager', 'user'])
        ->where('status', 'completed')
        ->where(function ($query) {
            $query->whereHas('sub_order_details', function ($query) {
                $query->where('pre_closed_status', "1")
                    ->where(function ($q) {
                        $q->whereColumn('challan_qty', '<', 'approved_quantity')
                          ->orWhereNull('challan_qty');
                    })
                    ->whereRaw(
                        'IFNULL(pre_closed, 0) + IFNULL(reallocated, 0) + IFNULL(challan_qty, 0) < approved_quantity'
                    );
            });
        })
        ->when(!$isSuperAdmin, function ($query) use ($user, $warehousePrefixes) {
            $prefix = $warehousePrefixes[$user->warehouse_id] ?? null;
            if (!$prefix) {
                abort(403, 'Invalid warehouse assigned to user.');
            }
            $query->where('order_no', 'LIKE', "%{$prefix}%");
        })
        ->get()
        ->sortBy(function ($order) {
            return optional($order->user->getManager)->name;
        });

        $managerList = $forManager
        ->map(function ($subOrder) {
            return [
                'id' => optional($subOrder->user->getManager)->id,
                'name' => optional($subOrder->user->getManager)->name,
            ];
        })
        ->filter(fn($m) => $m['id']) // Remove null managers
        ->unique('id') // Keep only unique manager IDs
        ->pluck('name', 'id') // Set manager_id as key, name as value
        ->toArray();
        
        
      $orderData = SubOrder::with('sub_order_details', 'user')
        ->where('status', 'completed')
        ->whereHas('sub_order_details', function ($dbQuery) {
            $dbQuery->where('pre_closed_status', '1');
        });
    
      // Apply search filters
      if ($request->search) {
          $sort_search = $request->search;
          $orderData->where(function ($q) use ($sort_search) {
              if (is_numeric($sort_search)) {
                  // Filter using raw subquery (since aggregation inside whereHas is not allowed)
                  $q->whereIn('id', function ($subQuery) use ($sort_search) {
                      $subQuery->select('sub_order_id')
                          ->from('sub_order_details')
                          ->groupBy('sub_order_id')
                          ->havingRaw('CEIL(SUM(price * approved_quantity)) = ?', [(float) $sort_search]);
                  });
              } else {
                  $q->where('order_no', 'like', '%' . $sort_search . '%')
                    ->orWhereHas('sub_order_details.product_data', function ($q2) use ($sort_search) {
                        $q2->where('part_no', 'like', '%' . $sort_search . '%');
                    })
                    ->orWhereHas('sub_order_details.product_data', function ($q2) use ($sort_search) {
                        $q2->where('name', 'like', '%' . $sort_search . '%');
                    })
                    ->orWhereHas('user', function ($q2) use ($sort_search) {
                        $q2->where('company_name', 'like', '%' . $sort_search . '%');
                    });
              }
          });
      }
    
      // Apply manager filter
      if ($request->manager && $request->manager != "") {
          $manager = $request->manager;
          $orderData->whereHas('user.getManager', function ($q) use ($manager) {
              $q->where('id', $manager);
          });
      }
    
      // Final result
      $orderData = $orderData->orderBy('id', 'DESC')->paginate(20);
      
      return view('backend.sales.all_pre_closed_order', compact('orderData','managerList'));
    } catch (\Exception $e) {
        $errorCode = $e->getCode();
        if ($errorCode == 23000) {
            $errorMessage = __("direct link already exists");
        } else {
            $errorMessage = $e->getMessage();//"something went wrong, please try again";
        }
        $response = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }        
    return $response; 
  }



    public function manager41PreClosedOrderDetails($sub_order_id)
    {
        try {
            // Load the sub order + only those details that are pre-closed
            $orderData = Manager41SubOrder::with([
                    // only pre-closed rows
                    'sub_order_details' => function ($q) {
                        $q->where('pre_closed_status', '1')
                          // load whichever relation your detail exposes
                          ->with(['product', 'product_data', 'user']);
                    },
                    'user.get_addresses',
                ])
                ->where('status', 'completed')
                ->findOrFail($sub_order_id);

            // These are exactly like your non-41 version
            $orderDetails             = $orderData->sub_order_details;     // already filtered
            $userDetails              = $orderData->user;                  // the (possibly branch) user on this sub order
            $allAddressesForThisUser  = $userDetails->get_addresses ?? collect();
            $shippingAddress          = $allAddressesForThisUser
                                          ->where('id', $orderData->shipping_address_id)
                                          ->first();

            $allWareHouse     = \App\Models\Warehouse::where('active', '1')->get();
            $allTransportData = \App\Models\Carrier::orderBy('name', 'ASC')->get();

            // Use a 41-specific blade, or reuse your existing one if it already matches the fields
            return view(
                'backend.sales.pre_closed_order_details',
                compact('orderData','orderDetails','userDetails','allAddressesForThisUser','shippingAddress','allWareHouse','allTransportData')
            );

            // If you want to reuse the same blade:
            // return view('backend.sales.pre_closed_order_details', compact(...));
        } catch (\Exception $e) {
            \Log::error('M41 preClosed details error: '.$e->getMessage(), ['sub_order_id' => $sub_order_id]);
            return back()->with('error', 'Unable to load Pre-Closed Order details.');
        }
    }

  public function preClosedOrderDetails($order_id){

    // ЁЯСЙ If manager-41 context, hand off to the 41 handler
    if ($this->isActingAs41Manager()) {
        return $this->manager41PreClosedOrderDetails($order_id);
    }

    try{
      // echo $order_id; die;
      $orderData = SubOrder::with('sub_order_details','user')->where('id',$order_id)->first();
      $orderDetails = SubOrderDetail::with('product','user')->where('sub_order_id',$order_id)->where('pre_closed_status', "1")->get();       
      $userDetails = $orderData->user;
      // echo "<pre>"; print_r($orderDetails);die;
      $allAddressesForThisUser = $orderData->user->get_addresses;      
      $shippingAddress = $userDetails->get_addresses->where('id', $orderData->shipping_address_id)->first();
      // echo "<pre>"; print_r($shippingAddress);die;
      $allWareHouse = Warehouse::where('active','1')->get();  
      $allTransportData = Carrier::orderBy('name','ASC')->get();

      return view('backend.sales.pre_closed_order_details', compact('orderData','orderDetails','userDetails','allAddressesForThisUser','shippingAddress','allWareHouse','allTransportData'));
    } catch (\Exception $e) {
        $response = ['res' => false, 'msg' => $e->getMessage()];
    }        
    return $response;
  }

  public function generateAllUnavailableProductsPDF($sub_order_id)
  {
        // generate all unavailable products pdf
        $subOrder = SubOrder::with(['user', 'order', 'order_warehouse', 'sub_order_details.product_data'])->findOrFail($sub_order_id);

        $order = $subOrder->order;
        $userDetails = $subOrder->user;

        $warehouseName = $subOrder->order_warehouse->name ?? 'N/A';

        $approvedProducts = [];
        $unavailableItems = [];

        foreach ($subOrder->sub_order_details as $detail) {
            $product = $detail->product_data;
            if (!$product) continue;

            // тЬЕ NEW: skip if pre_closed is null
            if (is_null($detail->pre_closed)) continue;
            
            $entry = [
                'product_name' => $product->name,
                'slug'         => $product->slug,
                'part_no'      => $product->part_no,
                'rate'         => $detail->approved_rate ?? 0,
                'approved_qty' => $detail->approved_quantity ?? 0,
                'pre_closed'   => $detail->pre_closed ?? 0,
                'bill_amount'  => ($detail->approved_quantity ?? 0) * ($detail->approved_rate ?? 0),
                'is_new'       => $detail->new_item == 1,
            ];

            if ($detail->approved_quantity > 0) {
                $approvedProducts[] = $entry;
            } else {
                $unavailableItems[] = [
                    'product_name' => $product->name,
                    'part_no'      => $product->part_no,
                    'qty'          => $detail->pre_closed ?? 0,
                ];
            }
        }

        // Wrap into single warehouse section
        $groupedSubOrders = collect([[
            'warehouse_name'     => $warehouseName,
            'approvedProducts'   => $approvedProducts,
            'unavailableItems'   => $unavailableItems,
        ]]);

        $pdfData = compact('order', 'userDetails', 'groupedSubOrders');

        $pdf = PDF::loadView('backend.sales.unavailable_products', $pdfData);
        $fileName = 'unavailable-products-' . $sub_order_id . '-' . uniqid() . '.pdf';
        $filePath = public_path('approved_products_pdf/' . $fileName);
        $pdf->save($filePath);
        $pdfURL=url('public/approved_products_pdf/' . $fileName);
       
        return url('public/approved_products_pdf/' . $fileName);
  }
  private function sendAllUnavaliableProductsNotification($subOrderId, $pdfUrl)
  {
        // send whatsapp for all unavaliable product
        try {
            $WhatsAppWebService = new WhatsAppWebService();

            // Fetch SubOrder with relations
            $subOrder = SubOrder::with(['user', 'order'])->findOrFail($subOrderId);
            $user = $subOrder->user;
            $order = $subOrder->order;

            $shippingAddress = \App\Models\Address::find($subOrder->shipping_address_id);
            $companyName = $shippingAddress->company_name ?? ($user->company_name ?? 'Valued Customer');

            if (!$order || !$user) {
                return response()->json(['error' => 'Missing order or user info.'], 404);
            }

            // Upload PDF as document and fetch media ID
            $media = $WhatsAppWebService->uploadMedia($pdfUrl);
            if (!isset($media['media_id'])) {
                return response()->json(['error' => 'Failed to upload media.'], 500);
            }

            // Order Details
            $orderCode = $order->code;
            $orderDate = \Carbon\Carbon::parse($order->created_at)->format('Y-m-d');
            $customerPhone = $user->phone;

            // Manager Contact
            $managerPhone = $this->getManagerPhone($user->manager_id ?? null);

            // WhatsApp Template Payload
            $templateData = [
                'name' => 'utility_unavailable_products',
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => [
                                    'filename' => 'Unavailable_Products_' . $orderCode . '.pdf',
                                    'id' => $media['media_id'],
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $companyName], // {{1}}
                            ['type' => 'text', 'text' => $orderCode],                                // {{2}}
                            ['type' => 'text', 'text' => $orderDate],                                // {{3}}
                            ['type' => 'text', 'text' => $managerPhone],                             // {{4}}
                        ],
                    ],
                ],
            ];

            // Send WhatsApp to Customer
            $customerResponse = $WhatsAppWebService->sendTemplateMessage('9894753728', $templateData);
           // $customerResponse = $WhatsAppWebService->sendTemplateMessage($customerPhone, $templateData);

            // Send WhatsApp to Manager (optional)
            $managerResponse = null;
            if ($managerPhone) {
               // $managerResponse = $WhatsAppWebService->sendTemplateMessage($managerPhone, $templateData);
            }
            // Send WhatsApp to fixed number
            //$extraResponse = $WhatsAppWebService->sendTemplateMessage('9894753728', $templateData);

            return response()->json([
                'customer_response' => $customerResponse,
                'manager_response' => $managerResponse,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
  }

  public function manager41PreClosedOrder($sub_order_id)
  {
        try {
            // Load Manager-41 sub-order + its details
            $subOrder = Manager41SubOrder::with('sub_order_details')
                ->findOrFail($sub_order_id);

            foreach ($subOrder->sub_order_details as $detail) {
                // Skip if already pre-closed
                if ((string)$detail->pre_closed_status === '1') {
                    continue;
                }

                $approved   = (int) ($detail->approved_quantity ?? 0);
                $challanQty = (int) ($detail->challan_qty ?? 0);
                $toClose    = max($approved - $challanQty, 0);

                $detail->pre_closed_status = '1';
                $detail->pre_closed_by     = $subOrder->warehouse_id; // who closed
                $detail->pre_closed        = $toClose;
                // keep column only if it exists in your Manager41SubOrderDetail table
                if (Schema::hasColumn($detail->getTable(), 'in_transit')) {
                    $detail->in_transit = 0;
                }
                $detail->save();
            }

            // Send user back to listing; if route absent, just back()
            try {
                return redirect()->route('order.allSplitOrder')
                    ->with('success', 'All pending items pre-closed for Manager-41 order.');
            } catch (\Throwable $e) {
                return back()->with('success', 'All pending items pre-closed for Manager-41 order.');
            }

        } catch (\Exception $e) {
            return ['res' => false, 'msg' => $e->getMessage()];
        }
  }

  public function preClosedOrder($order_id, $type, $btr=""){

    // ЁЯСЙ If acting as Manager-41, hand over to the Manager-41 version (no BTR, no PDFs)
    if (method_exists($this, 'isActingAs41Manager') && $this->isActingAs41Manager()) {
        return $this->manager41PreClosedOrder($order_id);
    }


    try{
      $orderData = SubOrder::with('sub_order_details','user')->where('id',$order_id)->first();
      $orderDetails = SubOrderDetail::with('product','user')->where('sub_order_id',$order_id)->where('pre_closed_status','<>', '1')->get();
    //   echo "<pre>"; print_r($orderDetails); die;
      
      if($type == 'btr' AND $btr == 'yes'){
        foreach($orderDetails as $ofKey=>$odValue){
          $getMainOrderData = SubOrderDetail::where('product_id',$odValue->product_id)->whereNull('reallocated')->where('order_details_id',$odValue->order_details_id)->where('type','sub_order')->first();
            // echo "<pre>"; print_r($odValue->challan_qty); die;
          if ($getMainOrderData !== null) {
            // if(($getMainOrderData->pre_closed + $getMainOrderData->reallocated + $getMainOrderData->challan_qty) < $getMainOrderData->approved_quantity){
                // $challan_qty = $getMainOrderData->challan_qty === null ? 0 : $getMainOrderData->challan_qty;
                $challan_qty = $odValue->challan_qty === null ? 0 : $odValue->challan_qty;
                if($getMainOrderData->approved_quantity - $challan_qty == 0){
                    $getMainOrderData->pre_closed_status = '1';
                }                
                $getMainOrderData->pre_closed_by = $orderData->warehouse_id;
                $getMainOrderData->pre_closed = $getMainOrderData->approved_quantity - $challan_qty;
                $getMainOrderData->in_transit = 0;
                $getMainOrderData->save();

                $odValue->pre_closed_status = '1';
                $odValue->pre_closed = $getMainOrderData->approved_quantity - $challan_qty;
                $odValue->pre_closed_by = $odValue->warehouse_id;
                $odValue->save();
                // die;
            // }
               
          }       
        }
        // Find BTR order using sub_order_id
        $btrSubOrder = SubOrder::where('sub_order_id', $order_id)
            ->where('type', 'btr')
            ->first();
        if ($btrSubOrder) {
            try {
                $pdfUrl = $this->generateAllUnavailableProductsPDF($btrSubOrder->id);
                $this->sendAllUnavaliableProductsNotification($btrSubOrder->id, $pdfUrl);
            } catch (\Exception $e) {
                \Log::error('PDF/WhatsApp error for BTR: ' . $e->getMessage());
            }
        }
      }elseif($type == 'btr' AND $btr == 'no'){
        // echo "BTR-NO"; die;
        foreach($orderDetails as $ofKey=>$odValue){
          $getMainOrderData = SubOrderDetail::where('product_id',$odValue->product_id)->whereNull('reallocated')->where('type','sub_order')->where('pre_closed_status','<>','1')->first();

          if ($getMainOrderData !== null) {
            // if(($getMainOrderData->pre_closed + $getMainOrderData->reallocated + $getMainOrderData->challan_qty) < $getMainOrderData->approved_quantity){
            //   $challan_qty = $getMainOrderData->challan_qty === null ? 0 : $getMainOrderData->challan_qty;
            $challan_qty = $odValue->challan_qty === null ? 0 : $odValue->challan_qty;
            if($getMainOrderData->approved_quantity - $challan_qty == 0){
                $getMainOrderData->pre_closed_status = '1';
            } 
            $getMainOrderData->pre_closed_status = '1';
            $getMainOrderData->pre_closed_by = $orderData->warehouse_id;
            $getMainOrderData->pre_closed = $getMainOrderData->approved_quantity - $challan_qty;
            $getMainOrderData->in_transit = 0;
            $getMainOrderData->save();

            $odValue->pre_closed_status = '1';
            $odValue->pre_closed_by = $odValue->warehouse_id;
            $odValue->pre_closed = $getMainOrderData->approved_quantity;
            $odValue->save();
            // }
          }
        }
      }elseif($type == 'sub_order'){ 
        // echo "SUB-ORDER"; die;
        // echo "<pre>".$type;die;       
        foreach($orderDetails as $ofKey=>$odValue){
          // $getMainOrderData = SubOrderDetail::where('product_id', $odValue->product_id)->where('pre_closed_status', '0')->where('order_details_id', $odValue->order_details_id)->where('type', 'sub_order')->whereRaw('COALESCE(approved_quantity, 0) - COALESCE(challan_qty, 0) > 0')->first();

          $getMainOrderData = SubOrderDetail::where('product_id', $odValue->product_id)->where('pre_closed_status','<>', '1')->where('order_details_id', $odValue->order_details_id)->where('type', 'sub_order')->first();          
           
          if ($getMainOrderData !== null) {
            
            $challan_qty = $getMainOrderData->challan_qty === null ? 0 : $getMainOrderData->challan_qty;
            if($getMainOrderData->approved_quantity - $challan_qty == 0){
                $getMainOrderData->pre_closed_status = '1';
            } 
            $getMainOrderData->pre_closed_by = $orderData->warehouse_id;
            $getMainOrderData->pre_closed = $getMainOrderData->approved_quantity - $challan_qty;
            $getMainOrderData->in_transit = '0';
            $getMainOrderData->save();
            

            $odValue->pre_closed_status = '1';
            $odValue->pre_closed_by = $odValue->warehouse_id;
            $odValue->pre_closed = $getMainOrderData->approved_quantity - $challan_qty;
            $odValue->save();
            // echo "<pre>...."; print_r($getMainOrderData); die;
          }
        }
        // Only PDF + WhatsApp in try-catch block
        try {
          $pdfUrl = $this->generateAllUnavailableProductsPDF($order_id);
           $this->sendAllUnavaliableProductsNotification($order_id, $pdfUrl);
        } catch (\Exception $e) {
            \Log::error('PDF/WhatsApp error: ' . $e->getMessage());
            
        }
      }

      // $orderDetails->pre_closed_status = '1';
      // $orderDetails->pre_closed_by = $orderDetails->warehouse_id;
      // $orderDetails->save();

      return redirect()->route('order.allSplitOrder');
    } catch (\Exception $e) {
        $response = ['res' => false, 'msg' => $e->getMessage()];
    }        
    return $response;
  }

  public function undoPreClodeOrder(Request $request) {
    try {
        $order_id = $request->order_id;
        $product_id = $request->product_id;
        $subOrderDetails = SubOrderDetail::where('order_id',$order_id)->where('product_id',$product_id)->where('pre_closed_status', "1")->get();
        $subOrderBtrDetails = SubOrderDetail::where('order_id',$order_id)->where('product_id',$product_id)->where('pre_closed_status', "1")->where('type', "btr")->first(); 
        foreach($subOrderDetails as $subOrderDetail){
          if($subOrderDetail->type == 'sub_order'){
            $subOrderDetail->pre_closed_status = '0';
            $subOrderDetail->pre_closed = NULL;
            $subOrderDetail->pre_closed_by = NULL;                       
            if($subOrderBtrDetails != NULL ){
              $subOrderDetail->in_transit = (string) $subOrderDetail->approved_quantity;
            }            
            $subOrderDetail->save();
          }else{
            $subOrderDetail->pre_closed = NULL;
            $subOrderDetail->pre_closed_status = '0';
            $subOrderDetail->pre_closed_by = NULL;
            $subOrderDetail->save();
          }
        }
        return response()->json(['msg' => 'Successfully undo the order from preclosed.'], 200);
      } catch (\Exception $e) {
          $errorCode = $e->getCode();
          $errorMessage = ($errorCode == 23000)
              ? __("direct link already exists")
              : $e->getMessage();

          return response()->json(['res' => false, 'msg' => $errorMessage, 'data' => $errorCode]);
      }
  }

  public function undoPreCloseSubOrder(Request $request) {
    try {
        $order_id = $request->order_id;
        $sub_order_id = $request->sub_order_id;
        $subOrderDetails = SubOrderDetail::where('order_id',$order_id)->where('pre_closed_status', "1")->get();
        
        foreach($subOrderDetails as $subOrderDetail){

          $subOrderBtrDetails = SubOrderDetail::where('id',$order_id)->where('product_id',$subOrderDetail->product_id)->where('pre_closed_status', "1")->where('type', "btr")->first();

          if($subOrderDetail->type == 'sub_order'){
            $subOrderDetail->pre_closed_status = '0';
            $subOrderDetail->pre_closed = NULL;
            $subOrderDetail->pre_closed_by = NULL;                       
            {
              $subOrderDetail->in_transit = (string) $subOrderDetail->approved_quantity;
            }            
            $subOrderDetail->save();
          }else if($subOrderBtrDetails == NULL ){
            $subOrderDetail->pre_closed = NULL;
            $subOrderDetail->pre_closed_status = '0';
            $subOrderDetail->pre_closed_by = NULL;
            $subOrderDetail->save();
          }
        }
        return response()->json(['msg' => 'Successfully undo the order from preclosed.'], 200);
      } catch (\Exception $e) {
          $errorCode = $e->getCode();
          $errorMessage = ($errorCode == 23000)
              ? __("direct link already exists")
              : $e->getMessage();
          return response()->json(['res' => false, 'msg' => $errorMessage, 'data' => $errorCode]);
      }
  }

  public function undoPreCloseOrder(Request $request) {
    try {
        $order_id = $request->order_id;
        $sub_order_id = $request->sub_order_id;
        $product_id = $request->product_id;
        $subOrderDetails = SubOrderDetail::where('order_id',$order_id)->where('product_id', $product_id)->where('sub_order_id', $sub_order_id)->where('pre_closed_status', "1")->get();
        
        foreach($subOrderDetails as $subOrderDetail){

          $subOrderBtrDetails = SubOrderDetail::where('id',$order_id)->where('product_id',$subOrderDetail->product_id)->where('sub_order_id', $sub_order_id)->where('pre_closed_status', "1")->where('type', "btr")->first();

          if($subOrderDetail->type == 'sub_order'){
            $subOrderDetail->pre_closed_status = '0';
            $subOrderDetail->pre_closed = NULL;
            $subOrderDetail->pre_closed_by = NULL;                       
            {
              $subOrderDetail->in_transit = (string) $subOrderDetail->approved_quantity;
            }            
            $subOrderDetail->save();
          }else if($subOrderBtrDetails == NULL ){
            $subOrderDetail->pre_closed = NULL;
            $subOrderDetail->pre_closed_status = '0';
            $subOrderDetail->pre_closed_by = NULL;
            $subOrderDetail->save();
          }
        }
        return response()->json(['msg' => 'Successfully undo the order from preclosed.'], 200);
      } catch (\Exception $e) {
          $errorCode = $e->getCode();
          $errorMessage = ($errorCode == 23000)
              ? __("direct link already exists")
              : $e->getMessage();

          return response()->json(['res' => false, 'msg' => $errorMessage, 'data' => $errorCode]);
      }
  }

  public function allPendingOrder(Request $request) {
    try {
        $user = auth()->user();
        $userId = $user->id;

        // Define super admin and special staff
        $superAdminId = 1;
        $specialStaffIds = [180, 169, 25606];

        // Check staff table
        $staff = \App\Models\Staff::where('user_id', $userId)->first();

        // Access rules
        $isSuperAdmin = $userId == $superAdminId;
        $hasWarehouseAccess = false;

        if ($isSuperAdmin) {
            $hasWarehouseAccess = true;
        } elseif (in_array($userId, $specialStaffIds)) {
            $hasWarehouseAccess = true;
        } elseif ($staff && $staff->role_id == 4) {
            $hasWarehouseAccess = true;
        }

        // Access denied
        if (!$hasWarehouseAccess) {
            return back()->with('error', 'Access denied. You are not authorized to view orders.');
        }

        // Map warehouse_id to order prefix
        $warehousePrefixes = [
            1 => 'KOL',
            2 => 'DEL',
            6 => 'MUM',
        ];

        // $query = SubOrderDetail::selectRaw('*, approved_quantity - (IFNULL(pre_closed, 0) + IFNULL(reallocated, 0) + IFNULL(challan_qty, 0)) as pending_qty')
        // ->with(['sub_order_record.user', 'product_data']) // eager load related models
        // ->where('pre_closed_status', '0')
        // ->where(function ($q) {
        //     $q->whereColumn('challan_qty', '<', 'approved_quantity')
        //       ->orWhereNull('challan_qty');
        // })
        // ->whereRaw('approved_quantity - (IFNULL(pre_closed, 0) + IFNULL(reallocated, 0) + IFNULL(challan_qty, 0)) > 0')
        // ->whereHas('sub_order_record', function ($q) {
        //     $q->where('order_no', '!=', '');
        // });

        // if ($request->search) {
        //   $sort_search = $request->search;
        //   $query = $query->where(function ($q) use ($sort_search) {
        //       $q->whereHas('sub_order_record', function ($q2) use ($sort_search) {
        //           $q2->where('order_no', 'like', '%' . $sort_search . '%');
        //       })->orWhereHas('product_data', function ($q2) use ($sort_search) {
        //           $q2->where('part_no', 'like', '%' . $sort_search . '%');
        //       })->orWhereHas('product_data', function ($q2) use ($sort_search) {
        //           $q2->where('name', 'like', '%' . $sort_search . '%');
        //       })->orWhereHas('sub_order_record.user', function ($q2) use ($sort_search) {
        //           $q2->where('company_name', 'like', '%' . $sort_search . '%');
        //       });
        //   });
        // }
        
        // // Apply warehouse filter if NOT super admin
        // if (!$isSuperAdmin) {
        //     $prefix = $warehousePrefixes[$user->warehouse_id] ?? null;
        //     if (!$prefix) {
        //         return back()->with('error', 'Invalid warehouse assigned to user.');
        //     }
        //     $query->whereHas('sub_order_record', function ($q) use ($prefix) {
        //         $q->where('order_no', 'LIKE', "%{$prefix}%");
        //     });
        // }        
        // $orderData = $query->orderBy('id', 'DESC')->paginate(100);

        $query = SubOrderDetail::selectRaw('*, approved_quantity - (IFNULL(pre_closed, 0) + IFNULL(reallocated, 0) + IFNULL(challan_qty, 0)) as pending_qty')
            ->with([
                'sub_order_record.user',
                'product_data',
                'parentSubOrder:id,id', // eager load parent sub_order (type = sub_order)
                // 'btrSubOrder:id,id,sub_order_id,type' // eager load btr order (type = btr)
                'btrSubOrder:id,id,order_id,product_id,sub_order_id,type'

            ])
            ->where('pre_closed_status', '0')
            ->where(function ($q) {
                $q->whereColumn('challan_qty', '<', 'approved_quantity')
                  ->orWhereNull('challan_qty');
            })
            ->whereRaw('approved_quantity - (IFNULL(pre_closed, 0) + IFNULL(reallocated, 0) + IFNULL(challan_qty, 0)) > 0')
            ->whereHas('sub_order_record', function ($q) {
                $q->where('order_no', '!=', '');
            });

        if ($request->search) {
            $sort_search = $request->search;
            $query->where(function ($q) use ($sort_search) {
                $q->whereHas('sub_order_record', function ($q2) use ($sort_search) {
                    $q2->where('order_no', 'like', '%' . $sort_search . '%');
                })->orWhereHas('product_data', function ($q2) use ($sort_search) {
                    $q2->where('part_no', 'like', '%' . $sort_search . '%');
                })->orWhereHas('product_data', function ($q2) use ($sort_search) {
                    $q2->where('name', 'like', '%' . $sort_search . '%');
                })->orWhereHas('sub_order_record.user', function ($q2) use ($sort_search) {
                    $q2->where('company_name', 'like', '%' . $sort_search . '%');
                });
            });
        }

        if (!$isSuperAdmin) {
            $prefix = $warehousePrefixes[$user->warehouse_id] ?? null;
            if (!$prefix) {
                return back()->with('error', 'Invalid warehouse assigned to user.');
            }
            $query->whereHas('sub_order_record', function ($q) use ($prefix) {
                $q->where('order_no', 'LIKE', "%{$prefix}%");
            });
        }

        $orderData = $query->orderBy('id', 'DESC')->paginate(100);

        // $hasBTR = SubOrder::with('sub_order_details','user')->where('id',$orderData->sub_order_id)->where('type','sub_order')->first();
      
        // $hasBTRId = "";
        // if($hasBTR != NULL){
        //   $hasBTRId = $hasBTR->id;
        // }

        // $hasBTROrder = SubOrder::with('sub_order_details','user')->where('sub_order_id',$orderData->id)->where('type','btr')->first();
        // $hasBTROrderId = "";
        // if($hasBTROrder != NULL){
        //   $hasBTROrderId = $hasBTROrder->id;
        // }

        // echo "<pre>"; print_r($hasBTROrder); die;
        // return view('backend.sales.split_order_details', compact('orderData','orderDetails','userDetails','allAddressesForThisUser','shippingAddress','allWareHouse','allTransportData','hasBTRId','hasBTROrderId'));

        return view('backend.sales.allPendingOrder', compact('orderData'));

      } catch (\Exception $e) {
          $errorCode = $e->getCode();
          $errorMessage = ($errorCode == 23000)
              ? __("direct link already exists")
              : $e->getMessage();

          return ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
      }
  }  


public function allPendingOrderExport(Request $request)
{
    $user   = auth()->user();
    $userId = $user->id;

    // Access control (same logic as listing, bas concise)
    $superAdminId    = 1;
    $specialStaffIds = [180, 169, 25606];

    $staff = Staff::where('user_id', $userId)->first();

    $isSuperAdmin       = ($userId == $superAdminId);
    $hasWarehouseAccess = $isSuperAdmin
        || in_array($userId, $specialStaffIds)
        || ($staff && $staff->role_id == 4);

    if (!$hasWarehouseAccess) {
        return back()->with('error', 'Access denied. You are not authorized to export orders.');
    }

    // Unique filename (microtime + datetime to avoid browser cache)
    $micro    = (int) (microtime(true) * 1000);
    $fileName = 'pending_orders_' . now()->format('Ymd_His') . '_' . $micro . '.xlsx';

    $response = Excel::download(
        new PendingOrdersExport($request->search, $user, $isSuperAdmin),
        $fileName
    );

    // No-cache headers (extra safety)
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');

    return $response;
}



  private function jsonAddress($user_id, $address_id){
    $user = User::where('id',$user_id)->first();
    $address = Address::where('id', $address_id)->first();
    $shippingAddress = [];
    $address_id = null;
    if ($address != null) {
      $address_id = $address->id;
      $shippingAddress['name']         = $user->name;
      $shippingAddress['company_name'] = $user->company_name;
      $shippingAddress['gstin']        = $user->gstin;
      $shippingAddress['email']        = $user->email;
      $shippingAddress['address']      = $address->address;
      $shippingAddress['country']      = $address->country->name;
      $shippingAddress['state']        = $address->state->name;
      $shippingAddress['city']         = $address->city;
      $shippingAddress['postal_code']  = $address->postal_code;
      $shippingAddress['phone']        = $address->phone; 
      if ($address->latitude || $address->longitude) {
        $shippingAddress['lat_lang'] = $address->latitude . ',' . $address->longitude;
      }
    }
    return json_encode($shippingAddress);
  }

  private function getFinancialYear() {
      $currentYear = date('Y');  // Get current year (e.g., 2024)
      $currentMonth = date('m'); // Get current month (e.g., 03 for March)

      if ($currentMonth >= 4) {
          // Financial year starts in April
          $fyStart = substr($currentYear, -2);         // Last two digits of the current year (e.g., "24")
          $fyEnd = substr($currentYear + 1, -2);       // Last two digits of next year (e.g., "25")
      } else {
          // Before April, it's still the previous financial year
          $fyStart = substr($currentYear - 1, -2);     // Last two digits of last year (e.g., "23")
          $fyEnd = substr($currentYear, -2);          // Last two digits of current year (e.g., "24")
      }
      return $fyStart . '-' . $fyEnd;
  }

  public function negativeStockEntry(Request $request){
    if($request->all()) {
      $getPurchaseBagRecord=PurchaseBag::where('order_no',$request->order_no)->where('sub_order_details_id',$request->sub_order_details_id)->first();
      if($getPurchaseBagRecord == NULL){
        $getSubOrderRecord=SubOrder::where('order_no',$request->order_no)->first();
        $getSubOrderDetailsRecord=SubOrderDetail::where('id',$request->sub_order_details_id)->where('in_transit',NULL)->first();
        if($getSubOrderDetailsRecord != NULL AND $getSubOrderRecord->status == 'completed'){
          $warehouseDetails = $getSubOrderDetailsRecord->warehouse;

          $purchaseBagOrder = array();
          $purchaseBagOrder['branch'] = $warehouseDetails->name;
          $purchaseBagOrder['order_date'] = date('Y-m-d');
          $purchaseBagOrder['order_no'] = $request->order_no;
          $purchaseBagOrder['sub_order_details_id'] = $request->sub_order_details_id;
          $purchaseBagOrder['part_no'] =  $getSubOrderDetailsRecord->product_data->part_no;
          $purchaseBagOrder['party'] = $getSubOrderRecord->user->company_name;
          $purchaseBagOrder['item'] = $getSubOrderDetailsRecord->product_data->name;
          $purchaseBagOrder['order_qty'] = $getSubOrderDetailsRecord->approved_quantity;
          $purchaseBagOrder['closing_qty'] = $getSubOrderDetailsRecord->closing_qty;
          $purchaseBagOrder['to_be_ordered'] = abs($getSubOrderDetailsRecord->closing_qty - $getSubOrderDetailsRecord->approved_quantity);
          $purchaseBagOrder['age'] = 1;
          $purchaseBagOrder['delete_status'] = 0;
          $purchaseBagOrderData = PurchaseBag::create($purchaseBagOrder);
        }        
      }      
    }else{
      $getResetProductData = ResetProduct::orderBy('id','ASC')->take(25)->get();

      foreach($getResetProductData as $rpKey=>$rpValue){
        // Update Code Delete all which is not create PO of this product.
        PurchaseBag::where('part_no',$rpValue->part_no)->where('delete_status',0)->delete();

        $getSubOrderDetailsRecord = SubOrderDetail::where(function ($query) {
            $query->whereNull('in_transit')->orWhere('in_transit', 0);
        })->where('product_id', $rpValue->product_id)->whereNull('challan_qty')
        ->whereHas('sub_order_record', function ($query) {
            $query->where('status', 'completed');
        })->orderBy('id', 'ASC')->get();

        $closingStock = [];
        $productOrderWarehouseWise = [];

        $currentStocksData = ProductApi::where('part_no', $rpValue->part_no)->get();
        foreach($currentStocksData as $cKey=>$cValue){
          $key = $rpValue->part_no . '_' . $cValue->godown;
          $closingStock[$key]= $cValue->closing_stock;
        }

        // Get closing stock of orders
        foreach ($getSubOrderDetailsRecord as $subOrderKey => $subOrderValue) {
          $partNo = $subOrderValue->product[0]->part_no ?? null;
          $godown = $subOrderValue->warehouse->name ?? null;
          if ($partNo && $godown) {
            $key = $partNo . '_' . $godown;
            if(isset($closingStock[$key])){
                $closingStock[$key] = $closingStock[$key] - ($subOrderValue->approved_quantity - ($subOrderValue->pre_closed + $subOrderValue->reallocated + $subOrderValue->in_transit + $subOrderValue->challan_qty));
            }else{
              $closingStock[$key] =  - ($subOrderValue->approved_quantity - ($subOrderValue->pre_closed + $subOrderValue->reallocated + $subOrderValue->in_transit + $subOrderValue->challan_qty));
            }           
            
            if($closingStock[$key] < 0){                          
              $purchaseBagOrder = array();
              $getPurchaseBagRecord=PurchaseBag::where('order_no',$subOrderValue->sub_order_record->order_no)->where('sub_order_details_id',$subOrderValue->id)->first();
              if($getPurchaseBagRecord == NULL){
                $purchaseBagOrder['branch'] = $godown;
                $purchaseBagOrder['order_date'] = date('Y-m-d');
                $purchaseBagOrder['order_no'] = $subOrderValue->sub_order_record->order_no;
                $purchaseBagOrder['sub_order_details_id'] = $subOrderValue->id;
                $purchaseBagOrder['part_no'] =  $partNo;
                $purchaseBagOrder['party'] = $subOrderValue->sub_order_record->user->company_name;
                $purchaseBagOrder['item'] = $subOrderValue->product[0]->name;
                $purchaseBagOrder['order_qty'] = $subOrderValue->approved_quantity;
                $purchaseBagOrder['closing_qty'] = $closingStock[$key];
                $purchaseBagOrder['to_be_ordered'] = abs($closingStock[$key]);
                $purchaseBagOrder['age'] = 0;
                $purchaseBagOrder['delete_status'] = 0;
                $purchaseBagOrderData = PurchaseBag::create($purchaseBagOrder);
              }
              $closingStock[$key] = 0;
            }
          }        
        }
        $rpValue->delete();
      }
    }
    return true;
  }

  public function negativeStockEntryV2(Request $request){
    if($request->all()) {
      $getPurchaseBagRecord=PurchaseBag::where('order_no',$request->order_no)->where('sub_order_details_id',$request->sub_order_details_id)->first();
      if($getPurchaseBagRecord == NULL){
        $getSubOrderRecord=SubOrder::where('order_no',$request->order_no)->first();
        $getSubOrderDetailsRecord=SubOrderDetail::where('id',$request->sub_order_details_id)->where('in_transit',NULL)->first();
        if($getSubOrderDetailsRecord != NULL AND $getSubOrderRecord->status == 'completed'){
          $warehouseDetails = $getSubOrderDetailsRecord->warehouse;

          $purchaseBagOrder = array();
          $purchaseBagOrder['branch'] = $warehouseDetails->name;
          $purchaseBagOrder['order_date'] = date('Y-m-d');
          $purchaseBagOrder['order_no'] = $request->order_no;
          $purchaseBagOrder['sub_order_details_id'] = $request->sub_order_details_id;
          $purchaseBagOrder['part_no'] =  $getSubOrderDetailsRecord->product_data->part_no;
          $purchaseBagOrder['party'] = $getSubOrderRecord->user->company_name;
          $purchaseBagOrder['item'] = $getSubOrderDetailsRecord->product_data->name;
          $purchaseBagOrder['order_qty'] = $getSubOrderDetailsRecord->approved_quantity;
          $purchaseBagOrder['closing_qty'] = $getSubOrderDetailsRecord->closing_qty;
          $purchaseBagOrder['to_be_ordered'] = abs($getSubOrderDetailsRecord->closing_qty - $getSubOrderDetailsRecord->approved_quantity);
          $purchaseBagOrder['age'] = 1;
          $purchaseBagOrder['delete_status'] = 0;
          $purchaseBagOrderData = PurchaseBag::create($purchaseBagOrder);
        }        
      }      
    }else{
      $getResetProductData = ResetProductT::orderBy('id','ASC')->take(25)->get();
    //   echo "<pre>...."; print_r($getResetProductData); die;
      foreach($getResetProductData as $rpKey=>$rpValue){
        // Update Code Delete all which is not create PO of this product.
        PurchaseBag::where('part_no',$rpValue->part_no)->where('delete_status',0)->delete();

        // $getSubOrderDetailsRecord = SubOrderDetail::where(function ($query) {
        //     $query->whereNull('in_transit')->orWhere('in_transit', 0);
        // })->where('product_id',$rpValue->product_id)->orderBy('id', 'ASC')->get();

        $getSubOrderDetailsRecord = SubOrderDetail::where(function ($query) {
            $query->whereNull('in_transit')->orWhere('in_transit', 0);
        })->where('product_id', $rpValue->product_id)->where('challan_qty', NULL)
        ->whereHas('sub_order_record', function ($query) {
            $query->where('status', 'completed');
        });
        $count   = (clone $getSubOrderDetailsRecord)->count();   // COUNT(*) at DB
        $getSubOrderDetailsRecord = $getSubOrderDetailsRecord->orderBy('id')->get();


        
        $closingStock = [];
        $productOrderWarehouseWise = [];

        $currentStocksData = ProductApi::where('part_no', $rpValue->part_no)->get();
        foreach($currentStocksData as $cKey=>$cValue){
          $key = $rpValue->part_no . '_' . $cValue->godown;
          $closingStock[$key]= $cValue->closing_stock;
        }
        // Get closing stock of orders
        foreach ($getSubOrderDetailsRecord as $subOrderKey => $subOrderValue) {
            $partNo = $subOrderValue->product[0]->part_no ?? null;
            $godown = $subOrderValue->warehouse->name ?? null;
            if ($partNo && $godown) {
                $key = $partNo . '_' . $godown;
                if(isset($closingStock[$key])){
                    // echo $closingStock[$key];
                    $closingStock[$key] = $closingStock[$key] - ($subOrderValue->approved_quantity - ($subOrderValue->pre_closed + $subOrderValue->reallocated + $subOrderValue->in_transit + $subOrderValue->challan_qty));
                }else{
                    $closingStock[$key] =  - ($subOrderValue->approved_quantity - ($subOrderValue->pre_closed + $subOrderValue->reallocated + $subOrderValue->in_transit + $subOrderValue->challan_qty));
                }
                if($closingStock[$key] < 0){
                    $getPurchaseBagRecord=PurchaseBag::where('order_no',$subOrderValue->sub_order_record->order_no)->where('sub_order_details_id',$subOrderValue->id)->first();
                    if($getPurchaseBagRecord == NULL){
                        $purchaseBagOrder['branch'] = $godown;
                        $purchaseBagOrder['order_date'] = date('Y-m-d');
                        $purchaseBagOrder['order_no'] = $subOrderValue->sub_order_record->order_no;
                        $purchaseBagOrder['sub_order_details_id'] = $subOrderValue->id;
                        $purchaseBagOrder['part_no'] =  $partNo;
                        $purchaseBagOrder['party'] = $subOrderValue->sub_order_record->user->company_name;
                        $purchaseBagOrder['item'] = $subOrderValue->product[0]->name;
                        $purchaseBagOrder['order_qty'] = $subOrderValue->approved_quantity;
                        $purchaseBagOrder['closing_qty'] = $closingStock[$key];
                        $purchaseBagOrder['to_be_ordered'] = abs($closingStock[$key]);
                        $purchaseBagOrder['age'] = 0;
                        $purchaseBagOrder['delete_status'] = 0;
                        // $purchaseBagOrderData = PurchaseBag::create($purchaseBagOrder);
                    }
                    $closingStock[$key] = 0;
                }
            }
        }
        $rpValue->delete();
      }
    }
    return true;
  }

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
    return 1;
  }

 


  public function inventoryEntry(Request $request){
    $getResetProductData = ResetInventoryProduct::orderBy('id','ASC')->take(50)->get();
    $msg = "Nothing Sync";
    foreach($getResetProductData as $rpKey=>$rpValue){
        $getwarehouse = Warehouse::where('active','1')->get();
        foreach($getwarehouse as $wKey=>$wValue){
          $purchaseInvoiceDetailTotalQty = 0;
          $debitNoteInvoiceTotalQty = 0;
          $invoiceDetailTotalQty = 0;
          $challanDetailTotalQty = 0;
          $currentStocksQty = 0;
          $markAsLostQty = 0;

          $currentStocksData = ProductApi::where('part_no', $rpValue->part_no)->where('godown', $wValue->name)->first();
          
        
          $openingStocksData = OpeningStock::where('part_no', $rpValue->part_no)
          ->where('godown', $wValue->name)
          ->first();
          if($openingStocksData != NULL){
            $currentStocksQty = $openingStocksData->closing_stock;
          }

          //$getPurchaseInvoiceDetailRecord = PurchaseInvoiceDetail::where('part_no',$rpValue->part_no)->where('inventory_status','0')
            //                                ->whereHas('purchaseInvoice', function ($query) use ($wValue) {
              //                                  $query->where('warehouse_id', $wValue->id);
                //                            })->orderBy('id', 'ASC')->get();
        
          $getPurchaseInvoiceDetailRecord = PurchaseInvoiceDetail::where('part_no', $rpValue->part_no)
                        ->whereDate('created_at', '>=', '2025-04-01')
                        ->whereHas('purchaseInvoice', function ($query) use ($wValue) {
                          $query->where('warehouse_id', $wValue->id);
                        })
                        ->orderBy('id', 'ASC')
                        ->get();
        
          if ($getPurchaseInvoiceDetailRecord->isNotEmpty()) {
            $purchaseInvoiceDetailTotalQty = $getPurchaseInvoiceDetailRecord->sum('qty');
          }


          $getDebitNoteInvoiceRecord = DebitNoteInvoiceDetail::where('part_no', $rpValue->part_no)
                        ->whereDate('created_at', '>=', '2025-04-01')
                        ->whereHas('debitNoteInvoice', function ($query) use ($wValue) {
                          $query->where('warehouse_id', $wValue->id);
                        })->orderBy('id', 'ASC')->get();

          if ($getDebitNoteInvoiceRecord->isNotEmpty()) {
            $debitNoteInvoiceTotalQty = $getDebitNoteInvoiceRecord->sum('qty');
          }              

          // $getInvoiceDetailRecord = InvoiceOrderDetail::where('part_no',$rpValue->part_no)->where('inventory_status','0')
          //                           ->whereHas('invoiceOrder', function ($query) use ($wValue) {
          //                               $query->where('warehouse_id', $wValue->id);
          //                           })->orderBy('id', 'ASC')->get();                                  
          // if ($getInvoiceDetailRecord->isNotEmpty()) {
          //   $invoiceDetailTotalQty = $getInvoiceDetailRecord->sum('billed_qty');
          // }

          $getChallanDetailRecord = ChallanDetail::where('product_id',$rpValue->product_id)->whereDate('created_at', '>=', '2025-04-01')
                                    ->whereHas('challan', function ($query) use ($wValue) {
                                        $query->where('warehouse_id', $wValue->id);
                                    })->orderBy('id', 'ASC')->get();
          if ($getChallanDetailRecord->isNotEmpty()) {
            $challanDetailTotalQty = $getChallanDetailRecord->sum('quantity');
          }

          // Mark as lost quantity.
          $totalMarkAsLostQty = MarkAsLostItem::where('product_id', $rpValue->product_id)->where('warehouse_id', $wValue->id)->sum('mark_as_lost_qty');

          $stock = (($currentStocksQty + $purchaseInvoiceDetailTotalQty) - ($invoiceDetailTotalQty + $challanDetailTotalQty)) - $debitNoteInvoiceTotalQty - $totalMarkAsLostQty;

          // Update inventory status.
          // PurchaseInvoiceDetail::where('part_no', $rpValue->part_no)->where('inventory_status', '0')
          //                         ->whereHas('purchaseInvoice', function ($query) use ($wValue) {
          //                             $query->where('warehouse_id', $wValue->id);
          //                         })->update(['inventory_status' => '1']);

          // InvoiceOrderDetail::where('part_no',$rpValue->part_no)->where('inventory_status','0')
          //                           ->whereHas('invoiceOrder', function ($query) use ($wValue) {
          //                               $query->where('warehouse_id', $wValue->id);
          //                           })->update(['inventory_status' => '1']);

          // ChallanDetail::where('product_id',$rpValue->product_id)->where('invoice_status','0')->where('inventory_status','0')
          //                           ->whereHas('challan', function ($query) use ($wValue) {
          //                               $query->where('warehouse_id', $wValue->id);
          //                           })->update(['inventory_status' => '1']);



          if($currentStocksData == NULL){
            $getProductDetails = Product::where('part_no',$rpValue->part_no)->first();
            $productApi = array();
            $productApi['part_no'] = $rpValue->part_no;
            $productApi['name'] = $getProductDetails->name;
            $productApi['group'] = "";
            $productApi['category'] = "";
            $productApi['closing_stock'] = $stock;
            $productApi['list_price'] = $getProductDetails->mrp;
            $productApi['godown'] = $wValue->name;  
            if($stock > 0){
              $purchaseBagOrderData = ProductApi::create($productApi);
            }
          }else{
            // if($stock > 0){
            //   $currentStocksData->closing_stock = $stock;
            //   $currentStocksData->save();
            // }else{
            //   $currentStocksData->closing_stock = '0';
            //   $currentStocksData->save();
            //   // $currentStocksData->delete();
            // }
            $currentStocksData->closing_stock = $stock;
            $currentStocksData->save();
          }        
        }
      $rpValue->delete();
    }
    if(count($getResetProductData) > 0){
      $msg = count($getResetProductData) ."record sync";
    }
    return $msg;
  }




    public function storeBarcode(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'sub_order_detail_id' => 'required|integer|exists:sub_order_details,id',
            'barcode'             => 'required|string',
        ]);

        $newCode = trim($data['barcode']);

        return \DB::transaction(function () use ($data, $newCode) {

           

            // тЬЕ SOD-LEVEL duplicate check
            $sod      = \App\Models\SubOrderDetail::lockForUpdate()->findOrFail($data['sub_order_detail_id']);
            $existing = trim((string) $sod->barcode);
            $codesArr = $existing
                ? array_filter(array_map('trim', preg_split('/[\r\n,]+/', $existing)))
                : [];

            if (in_array($newCode, $codesArr, true)) {
                return response()->json([
                    'success'   => true,
                    'duplicate' => true,     // <- duplicate рд╕рд┐рд░реНрдл рдЗрд╕реА SOD рдкрд░ рдорд╛рдирд╛ рдЬрд╛рдПрдЧрд╛
                    'message'   => 'This barcode is already scanned for this item.',
                ]);
            }

            // тЬЕ append in SOD.barcode
            $codesArr[]   = $newCode;
            $sod->barcode = implode(',', $codesArr);
            $sod->save();

            // тЬЕ barcodes table: create/update + is_warranty flag set
            try {
                \App\Models\Barcode::updateOrCreate(
                    ['barcode'     => $newCode],                              // key
                    ['is_warranty' => (int) $sod->is_warranty === 1 ? 1 : 0]  // value
                );
            } catch (\Illuminate\Database\QueryException $e) {
                // (optional) рдЕрдЧрд░ unique index рд╣реИ рдФрд░ rare race-condition рдореЗрдВ рд▓рдЧреЗ,
                // рддреЛ safe рддрд░реАрдХреЗ рд╕реЗ "already" рдмрддрд╛ рджреЛ. рдЪрд╛рд╣реЗрдВ рддреЛ рдпреЗ catch рд╣рдЯрд╛ рднреА рд╕рдХрддреЗ рд╣реИрдВ.
                if ($e->getCode() === '23000') {
                    return response()->json([
                        'success'   => true,
                        'duplicate' => false, // global duplicate рдХреЛ рдЕрдм block рдирд╣реАрдВ рдХрд░ рд░рд╣реЗ
                        'saved'     => $newCode,
                        'all'       => $sod->barcode,
                        'note'      => 'Barcode already existed globally; SOD saved.',
                    ]);
                }
                throw $e;
            }

            return response()->json([
                'success'   => true,
                'duplicate' => false,
                'saved'     => $newCode,
                'all'       => $sod->barcode,
            ]);
        });
    }
    
    
    public function additionalWhatsapp($user, $first_order)
    {
        // Step 1: Get Order Address Info
        $orderWithAddress = Order::select('orders.id', 'addresses.acc_code', 'addresses.company_name')
            ->join('addresses', 'orders.address_id', '=', 'addresses.id')
            ->where('orders.id', $first_order->id)
            ->first();
    
        if (!$orderWithAddress) {
            return false; // Exit if no address found
        }
    
        $party_code   = $orderWithAddress->acc_code;
        $company_name = $orderWithAddress->company_name ?? 'Customer';
        //$managerPhone = $this->getManagerPhone($user->manager_id);
        $managerPhone = "9730377752";
    
        // Step 2: Calculate due and overdue from addresses table
        $dueData = DB::table('addresses')
            ->where('user_id', $user->id)
            ->selectRaw('
                SUM(CAST(NULLIF(due_amount, "") AS DECIMAL(10,2))) as total_due,
                SUM(CAST(NULLIF(overdue_amount, "") AS DECIMAL(10,2))) as total_overdue
            ')
            ->first();
    
        $dueAmount     = $dueData->total_due ?? 0;
        $overdueAmount = $dueData->total_overdue ?? 0;
    
        // Step 3: Generate PDF if needed
        $adminStatementController = new AdminStatementController();
        $pdf_url = $adminStatementController->generateStatementPdf($party_code, $dueAmount, $overdueAmount, $user);
    
        $pdfUrl            = $pdf_url ?? '';
        $statement_button  = basename($pdfUrl);
    
        // Step 4: Only send if due or overdue exists
        if ($dueAmount <= 0 && $overdueAmount <= 0) {
            return false;
        }
    
        // Prepare the message
        if ($dueAmount > 0 && $overdueAmount > 0) {
            $messageLine = "You have a due of *₹" . number_format($dueAmount, 2) . "* and an overdue of *₹" . number_format($overdueAmount, 2) . "*.";
        } elseif ($dueAmount > 0) {
            $messageLine = "You have a due amount of *₹" . number_format($dueAmount, 2) . "*.";
        } else {
            $messageLine = "You have an overdue amount of *₹" . number_format($overdueAmount, 2) . "*.";
        }
    
        // WhatsApp Template
        $reminder_template_data = [
            'name'      => 'utility_due_overdue_template',
            'language'  => 'en_US',
            'components'=> [
                [
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $company_name],
                        ['type' => 'text', 'text' => $messageLine],
                    ],
                ],
                [
                    'type'     => 'button',
                    'sub_type' => 'url',
                    'index'    => '0',
                    'parameters' => [
                        ['type' => 'text', 'text' => $statement_button],
                    ],
                ],
            ],
        ];
    
        // Step 5: Send WhatsApp to customer and manager
        $customer_phone = json_decode($first_order->shipping_address)->phone ?? null;
    
        $this->WhatsAppWebService = new WhatsAppWebService();
    
        if ($customer_phone) {
            $this->WhatsAppWebService->sendTemplateMessage($customer_phone, $reminder_template_data);
        }
    
        if ($managerPhone) {
            $this->WhatsAppWebService->sendTemplateMessage($managerPhone, $reminder_template_data);
        }
    
        return true;
    }
    
    public function sendAdditionalWhatsappAjax(Request $request, $order_id)
    {
        try {
            $order = Order::with('user')->findOrFail($order_id);
            $user  = $order->user;
    
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found for this order.',
                ], 200);
            }
    
            $sent = $this->additionalWhatsapp($user, $order);
    
            if ($sent === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'No due/overdue found. WhatsApp was not sent.',
                ], 200);
            }
    
            return response()->json([
                'success' => true,
                'message' => 'WhatsApp reminder sent successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send WhatsApp: '.$e->getMessage(),
            ], 500);
        }
    }

}