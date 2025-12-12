<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Order;
use App\Models\User;
use App\Models\Address;
use Config;
use Hash;
use PDF;
use Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\WhatsAppWebService;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendWhatsAppMessagesJob;
use App\Http\Controllers\InvoiceController;


class PendingDispatchOrder extends Controller
{
    //

    private function getManagerPhone($managerId)
    {
      $managerData = DB::table('users')
          ->where('id', $managerId)
          ->select('phone')
          ->first();

      return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
    }


    public function index(Request $request)
    {
        // Access control: Allow only specific users
        $allowedUserIds = [1, 180, 169, 25606];
        if (!in_array(auth()->user()->id, $allowedUserIds)) {
            return abort(403, 'Unauthorized action.');
        }

        // Sorting parameters
        $sort = $request->input('sort', 'orders.id'); // Default sort by order
        $direction = $request->input('direction', 'desc');

        // Validate sort columns
        $validSortColumns = [
            'orders.id',
            'approvals_data.item_name',
            'approvals_data.approval_date',
            'approvals_data.bill_amount',
            'orders.code',
            'orders.created_at',
            'addresses.company_name'
        ];
        if (!in_array($sort, $validSortColumns)) {
            $sort = 'orders.id';
        }

        // **Step 1: Get Correct Total Orders after Search**
        $totalOrdersQuery = DB::table('approvals_data')
            ->leftJoin('orders', 'approvals_data.order_id', '=', 'orders.id')
            ->leftJoin('addresses', 'approvals_data.party_code', '=', 'addresses.acc_code')
            ->selectRaw('COUNT(DISTINCT approvals_data.order_id) as total_orders');

        // Apply search filter to total orders count
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $totalOrdersQuery->where(function ($q) use ($search) {
                $q->where('orders.code', 'like', "%$search%")
                  ->orWhere('approvals_data.item_name', 'like', "%$search%")
                  ->orWhereRaw('approvals_data.part_no = ?', [$search]) 
                  ->orWhereRaw('approvals_data.party_code = ?', [$search]) 
                  ->orWhere('addresses.company_name', 'like', "%$search%");
            });
        }

        $totalOrders = $totalOrdersQuery->value('total_orders'); // Get correct count after search

        // **Step 2: Get Paginated Order IDs**
        $orderIdsQuery = DB::table('approvals_data')
            ->select('approvals_data.order_id')
            ->leftJoin('orders', 'approvals_data.order_id', '=', 'orders.id')
            ->leftJoin('addresses', 'approvals_data.party_code', '=', 'addresses.acc_code')
            ->groupBy('approvals_data.order_id');

        // Apply search filter at `order_id` level
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $orderIdsQuery->where(function ($q) use ($search) {
                $q->where('orders.code', 'like', "%$search%")
                  ->orWhere('approvals_data.item_name', 'like', "%$search%")
                  ->orWhereRaw('approvals_data.part_no = ?', [$search]) 
                  ->orWhereRaw('approvals_data.party_code = ?', [$search]) 
                  ->orWhere('addresses.company_name', 'like', "%$search%");
            });
        }

        // Apply sorting before fetching order_ids
        $orderIdsQuery->orderBy($sort, $direction);

        // **Paginate at `order_id` level**
        $perPage = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $orderIds = $orderIdsQuery->skip(($currentPage - 1) * $perPage)->take($perPage)->pluck('order_id');

        // **Step 3: Fetch Full Data for the Paginated `order_id`s**
        $query = DB::table('approvals_data')
            ->leftJoin('orders', 'approvals_data.order_id', '=', 'orders.id')
            ->leftJoin('addresses', 'approvals_data.party_code', '=', 'addresses.acc_code')
            ->whereIn('approvals_data.order_id', $orderIds)  // Fetch only paginated orders
            ->select(
                'approvals_data.id',
                'approvals_data.order_id',
                'approvals_data.part_no',
                'approvals_data.item_name',
                'approvals_data.order_qty',
                'approvals_data.billed_qty',
                'approvals_data.net_rate as rate',
                'approvals_data.gross',
                'approvals_data.bill_amount',
                'approvals_data.approval_date',
                'approvals_data.party_code',
                'approvals_data.manually_cancel_item',
                'orders.code as order_code',
                'orders.created_at as order_date',
                'addresses.company_name',
                DB::raw('(CASE 
                    WHEN EXISTS (SELECT 1 FROM bills_data WHERE bills_data.order_id = approvals_data.order_id 
                        AND bills_data.part_no = approvals_data.part_no) THEN "Completed"
                    WHEN EXISTS (SELECT 1 FROM dispatch_data WHERE dispatch_data.order_id = approvals_data.order_id 
                        AND dispatch_data.part_no = approvals_data.part_no) THEN "Material in transit"
                    WHEN approvals_data.approval_date IS NOT NULL THEN "Pending for Dispatch"
                    ELSE "Material unavailable" 
                END) as status')
            );

        // Fetch paginated records as collection
        $data = collect($query->get());

        // Ensure Proper Grouping by `order_id`
        $groupedData = $data->groupBy('order_id');

        // **Step 4: Fix Pagination**
        $pagination = new LengthAwarePaginator(
            $groupedData,
            $totalOrders,  // **FIX: Total number of paginated orders**
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        // Pass grouped data to the view
        return view('backend.pending_dispatch_orders.index', [
            'data' => $groupedData,  // Ensuring data is paginated based on orders
            'pagination' => $pagination,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
 public function orgnewindex(Request $request)
    {


        // Access control: Allow only specific users
        $allowedUserIds = [1, 180, 169, 25606]; // List of allowed user IDs
        if (!in_array(auth()->user()->id, $allowedUserIds)) {
            return abort(403, 'Unauthorized action.');
        }

        // Sorting parameters
        $sort = $request->input('sort', 'approvals_data.id'); // Default sort column
        $direction = $request->input('direction', 'desc'); // Default sort direction

        // Validate sort columns
        $validSortColumns = [
            'approvals_data.id',
            'approvals_data.item_name',
            'approvals_data.approval_date',
            'approvals_data.bill_amount',
            'orders.code',
            'orders.created_at', // Added sorting for order_date
            'addresses.company_name'
        ];
        if (!in_array($sort, $validSortColumns)) {
            $sort = 'approvals_data.id';
        }

        // Query the approvals_data table with the required joins
        $query = DB::table('approvals_data')
            ->leftJoin('orders', 'approvals_data.order_id', '=', 'orders.id')
            ->leftJoin('addresses', 'approvals_data.party_code', '=', 'addresses.acc_code') // No need for COLLATE now
            ->select(
                'approvals_data.id',
                'approvals_data.order_id',
                'approvals_data.part_no',
                'approvals_data.item_name',
                'approvals_data.order_qty',
                'approvals_data.billed_qty',
                'approvals_data.net_rate as rate',
                'approvals_data.gross',
                'approvals_data.bill_amount',
                'approvals_data.approval_date',
                'approvals_data.party_code',
                'approvals_data.manually_cancel_item',
                'orders.code as order_code',
                'orders.created_at as order_date',
                'addresses.company_name',
                DB::raw('(CASE 
                    WHEN EXISTS (SELECT 1 FROM bills_data WHERE bills_data.order_id = approvals_data.order_id 
                        AND bills_data.part_no = approvals_data.part_no) THEN "Completed"
                    WHEN EXISTS (SELECT 1 FROM dispatch_data WHERE dispatch_data.order_id = approvals_data.order_id 
                        AND dispatch_data.part_no = approvals_data.part_no) THEN "Material in transit"
                    WHEN approvals_data.approval_date IS NOT NULL THEN "Pending for Dispatch"
                    ELSE "Material unavailable" 
                END) as status')
            );

            // $data = $query->limit(10)->get();
            // echo "<pre>";
            // print_r($data);
            // die();

        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('orders.code', 'like', "%$search%")
                  ->orWhere('approvals_data.item_name', 'like', "%$search%")
                  // ->orWhere('approvals_data.part_no', 'like', "%$search%")
                  // ->orWhere('approvals_data.party_code', 'like', "%$search%")
                  ->orWhere('approvals_data.part_no', $search)
                    ->orWhere('approvals_data.party_code', $search)
                  ->orWhere('addresses.company_name', 'like', "%$search%");
            });
        }
       
        // Apply sorting
        $query->orderBy($sort, $direction);
        $data = $query->get();

        // echo "<pre>";
            // print_r($data);
            // die();

        // Group data by order_id
        $groupedData = $data->groupBy('order_id');

        // Paginate grouped data
        $perPage = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pagedData = $groupedData->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $pagination = new LengthAwarePaginator(
            $pagedData,
            $groupedData->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        // Pass data to the view
        return view('backend.pending_dispatch_orders.index', [
            'data' => $pagedData,
            'pagination' => $pagination,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }
    public function org_backup_index_5_march_2025(Request $request)
    {
        // Access control: Allow only specific users
        $allowedUserIds = [1, 180, 169, 25606]; // List of allowed user IDs
        if (!in_array(auth()->user()->id, $allowedUserIds)) {
            return abort(403, 'Unauthorized action.');
        }

        // Sorting parameters
        $sort = $request->input('sort', 'approvals_data.id'); // Default sort column
        $direction = $request->input('direction', 'desc'); // Default sort direction

        // Validate sort columns
        $validSortColumns = [
            'approvals_data.id',
            'approvals_data.item_name',
            'approvals_data.approval_date',
            'approvals_data.bill_amount',
            'orders.code',
            'orders.created_at', // Added sorting for order_date
            'addresses.company_name'
        ];
        if (!in_array($sort, $validSortColumns)) {
            $sort = 'approvals_data.id';
        }

        // Query the approvals_data table with the required joins
        $query = DB::table('approvals_data')
            ->leftJoin('orders', 'approvals_data.order_id', '=', 'orders.id')
            ->leftJoin('addresses', function ($join) {
                // Apply collation fix for party_code and acc_code
                $join->on(DB::raw('approvals_data.party_code COLLATE utf8mb3_unicode_ci'), '=', DB::raw('addresses.acc_code COLLATE utf8mb3_unicode_ci'));
            })
            ->leftJoin('dispatch_data', function ($join) {
                $join->on('approvals_data.order_id', '=', 'dispatch_data.order_id')
                    ->on('approvals_data.part_no', '=', 'dispatch_data.part_no');
            })
            ->leftJoin('bills_data', function ($join) {
                $join->on('approvals_data.order_id', '=', 'bills_data.order_id')
                    ->on('approvals_data.part_no', '=', 'bills_data.part_no');
            })
            ->select(
                'approvals_data.id',
                'approvals_data.order_id',
                'approvals_data.part_no',
                'approvals_data.item_name',
                'approvals_data.order_qty',
                'approvals_data.billed_qty',
                'approvals_data.net_rate as rate',
                'approvals_data.gross',
                'approvals_data.bill_amount',
                'approvals_data.approval_date',
                'approvals_data.party_code',
                'approvals_data.manually_cancel_item',
                'orders.code as order_code',
                'orders.created_at as order_date',
                'addresses.company_name',
                DB::raw('CASE 
                    WHEN bills_data.invoice_no IS NOT NULL THEN "Completed"
                    WHEN dispatch_data.id IS NOT NULL THEN "Material in transit"
                    WHEN approvals_data.approval_date IS NOT NULL THEN "Pending for Dispatch"
                    ELSE "Material unavailable" 
                END as status')
            );



        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('orders.code', 'like', "%$search%")
                  ->orWhere('approvals_data.item_name', 'like', "%$search%")
                  ->orWhere('approvals_data.part_no', 'like', "%$search%")
                  ->orWhere('approvals_data.party_code', 'like', "%$search%")
                  ->orWhere('addresses.company_name', 'like', "%$search%");
            });
        }
       
        // Apply sorting
        $query->orderBy($sort, $direction);
        $data = $query->get();

        // Group data by order_id
        $groupedData = $data->groupBy('order_id');

        // Paginate grouped data
        $perPage = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pagedData = $groupedData->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $pagination = new LengthAwarePaginator(
            $pagedData,
            $groupedData->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        // Pass data to the view
        return view('backend.pending_dispatch_orders.index', [
            'data' => $pagedData,
            'pagination' => $pagination,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function backup2index(Request $request)
    {
        // Access control: Allow only specific users
        $allowedUserIds = [1, 180, 169, 25606]; // List of allowed user IDs
        if (!in_array(auth()->user()->id, $allowedUserIds)) {
            return abort(403, 'Unauthorized action.');
        }

        // Sorting parameters
        $sort = $request->input('sort', 'approvals_data.id'); // Default sort column
        $direction = $request->input('direction', 'desc'); // Default sort direction

        // Validate sort columns
        $validSortColumns = ['approvals_data.id', 'approvals_data.item_name', 'approvals_data.approval_date', 'approvals_data.bill_amount', 'orders.code', 'addresses.company_name'];
        if (!in_array($sort, $validSortColumns)) {
            $sort = 'approvals_data.id';
        }

        // Query the approvals_data table with the required joins
        $query = DB::table('approvals_data')
            ->leftJoin('orders', 'approvals_data.order_id', '=', 'orders.id')
            ->leftJoin('addresses', function ($join) {
                // Apply collation fix for party_code and acc_code
                $join->on(DB::raw('approvals_data.party_code COLLATE utf8mb3_unicode_ci'), '=', DB::raw('addresses.acc_code COLLATE utf8mb3_unicode_ci'));
            })
            ->select(
                'approvals_data.id',
                'approvals_data.order_id',
                'approvals_data.part_no',
                'approvals_data.item_name',
                'approvals_data.order_qty',
                'approvals_data.billed_qty',
                'approvals_data.net_rate as rate',
                'approvals_data.gross',
                'approvals_data.bill_amount',
                'approvals_data.approval_date',
                'approvals_data.party_code',
                'approvals_data.manually_cancel_item',
                'orders.code as order_code',
                'orders.created_at as order_date',
                'addresses.company_name'
            );
            // ->whereNotExists(function ($subquery) {
            //     $subquery->select(DB::raw(1))
            //         ->from('dispatch_data')
            //         ->whereColumn('dispatch_data.order_id', 'approvals_data.order_id')
            //         ->whereColumn('dispatch_data.part_no', 'approvals_data.part_no'); // Ensure part_no also doesn't match
            // });

        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('orders.code', 'like', "%$search%")
                  ->orWhere('approvals_data.item_name', 'like', "%$search%")
                  ->orWhere('approvals_data.part_no', 'like', "%$search%")
                  ->orWhere('addresses.company_name', 'like', "%$search%");
            });
        }

        // Apply sorting
        $query->orderBy($sort, $direction);
        $data = $query->get();

        // Group data by order_id
        $groupedData = $data->groupBy('order_id');

        // Paginate grouped data
        $perPage = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pagedData = $groupedData->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $pagination = new LengthAwarePaginator(
            $pagedData,
            $groupedData->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        // Pass data to the view
        return view('backend.pending_dispatch_orders.index', [
            'data' => $pagedData,
            'pagination' => $pagination,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function __index(Request $request)
    {
        // Access control: Allow only specific users
        $allowedUserIds = [1, 180, 169, 25606]; // List of allowed user IDs
        if (!in_array(auth()->user()->id, $allowedUserIds)) {
            return abort(403, 'Unauthorized action.');
        }

        // Sorting parameters
        $sort = $request->input('sort', 'approvals_data.id'); // Default sort column
        $direction = $request->input('direction', 'desc'); // Default sort direction

        // Validate sort columns
        $validSortColumns = ['approvals_data.id', 'approvals_data.item_name', 'approvals_data.approval_date', 'approvals_data.bill_amount', 'orders.code', 'addresses.company_name'];
        if (!in_array($sort, $validSortColumns)) {
            $sort = 'approvals_data.id';
        }

        // Query the approvals_data table with join to orders and addresses tables
        $query = DB::table('approvals_data')
            ->leftJoin('orders', 'approvals_data.order_id', '=', 'orders.id')
            ->leftJoin('dispatch_data', 'approvals_data.order_id', '=', 'dispatch_data.order_id') // Join with dispatch_data table
            ->leftJoin('addresses', function ($join) {
                // Apply collation fix for party_code and acc_code
                $join->on(DB::raw('approvals_data.party_code COLLATE utf8mb3_unicode_ci'), '=', DB::raw('addresses.acc_code COLLATE utf8mb3_unicode_ci'));
            })
            ->select(
                'approvals_data.id',
                'approvals_data.order_id',
                'approvals_data.part_no',
                'approvals_data.item_name',
                'approvals_data.order_qty',
                'approvals_data.billed_qty',
                'approvals_data.net_rate as rate',
                'approvals_data.gross',
                'approvals_data.bill_amount',
                'approvals_data.approval_date',
                'approvals_data.party_code',
                'approvals_data.manually_cancel_item',
                'orders.code as order_code',
                'orders.created_at as order_date',
                'addresses.company_name' // Select the company name from addresses table
            )
            ->whereNull('dispatch_data.order_id'); // Filter rows not present in dispatch_data

        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('orders.code', 'like', "%$search%")
                  ->orWhere('approvals_data.item_name', 'like', "%$search%")
                  ->orWhere('approvals_data.part_no', 'like', "%$search%")
                  ->orWhere('addresses.company_name', 'like', "%$search%"); // Add search by company name
            });
        }

        // Apply sorting to query
        $query->orderBy($sort, $direction);
        $data = $query->get();

        // Group data by order_id
        $groupedData = $data->groupBy('order_id');

        // Paginate grouped data
        $perPage = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pagedData = $groupedData->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $pagination = new LengthAwarePaginator(
            $pagedData,
            $groupedData->count(),
            $perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        // Pass data to the view
        return view('backend.pending_dispatch_orders.index', [
            'data' => $pagedData,
            'pagination' => $pagination,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }


    public function generateApprovalPdfURL($orderId, $partyCode)
    {
        // Fetch approved products, including their order quantities and product names
        $approvedProducts = DB::table('approvals_data')
            ->join('products', 'approvals_data.product_id', '=', 'products.id') // Join with products table
            ->leftJoin('order_details', function ($join) use ($orderId) {
                $join->on('approvals_data.product_id', '=', 'order_details.product_id')
                     ->where('order_details.order_id', $orderId);
            })
            ->where('approvals_data.order_id', $orderId)
            ->where('approvals_data.party_code', $partyCode)
            ->where('approvals_data.approval_status', 'Approved') // Only include approved items
            ->select(
                'approvals_data.product_id',
                'products.name as product_name',
                'products.part_no as part_no',
                'products.slug as slug',
                'approvals_data.order_qty as approved_qty',
                'order_details.quantity as order_qty', // Fetch order quantity
                'approvals_data.rate',
                'approvals_data.bill_amount',
                'approvals_data.is_new' // Include is_new
            )
            ->get();

        // Fetch unavailable items from the order_details table
        $unavailableItems = DB::table('order_details')
            ->leftJoin('approvals_data', function ($join) use ($orderId) {
                $join->on('order_details.product_id', '=', 'approvals_data.product_id')
                     ->where('approvals_data.order_id', $orderId);
            })
            ->join('products', 'order_details.product_id', '=', 'products.id') // Join with products table
            ->where('order_details.order_id', $orderId)
            ->whereNull('approvals_data.product_id') // Fetch items not in approvals_data
            ->select(
                'order_details.product_id',
                'products.name as product_name',
                'products.part_no as part_no',
                'products.slug as slug',
                'order_details.quantity as qty'
            )
            ->get();

        // Check if there are any approved products or unavailable items
        if ($approvedProducts->isEmpty() && $unavailableItems->isEmpty()) {
            return response()->json(['message' => 'No approved or unavailable products found for the specified order and party.'], 404);
        }


         // Fetch user details based on party_code 
         $userDetails = DB::table('users')->where('party_code', $partyCode)->select('company_name','manager_id','phone')
            ->first();
            // Check if userDetails exists
        if (!$userDetails) {
            return response()->json(['error' => 'User not found for the provided party code.'], 404);
        }

        $manager_phone= $this->getManagerPhone($userDetails->manager_id);
        // Check if manager phone is found
        if (!$manager_phone) {
            return response()->json([
                'error' => 'Manager phone not available for the provided manager ID.',
                'manager_id' => $userDetails->manager_id
            ], 404);
        }

        $order = DB::table('orders')->where('id', $orderId)->select('id','code','created_at')->first();

        // Prepare data for the PDF
        $pdfData = [
            'approvedProducts' => $approvedProducts,
            'unavailableItems' => $unavailableItems,
            'orderId' => $orderId,
            'partyCode' => $partyCode,
            'userDetails'=>$userDetails,
            'manager_phone'=>$manager_phone,
            'order'=>$order
        ];

        // Load the PDF view and pass the data
        $pdf = PDF::loadView('backend.invoices.approved_products', $pdfData);
        // Define the file name and path
        $fileName = 'approved-products-' . $orderId . '-' . uniqid() . '.pdf';
        $filePath = public_path('approved_products_pdf/' . $fileName);
        // Save the PDF to the specified directory
        $pdf->save($filePath);
        // Generate the public URL
        $publicUrl = url('public/approved_products_pdf/' . $fileName);
        return $publicUrl;
    }


    public function downloadApprovalPdfURL($orderId, $partyCode)
    {
        // Fetch approved products, including their order quantities and product names
        $approvedProducts = DB::table('approvals_data')
            ->join('products', 'approvals_data.product_id', '=', 'products.id')
            ->leftJoin('order_details', function ($join) use ($orderId) {
                $join->on('approvals_data.product_id', '=', 'order_details.product_id')
                     ->where('order_details.order_id', $orderId);
            })
            ->where('approvals_data.order_id', $orderId)
            ->where('approvals_data.party_code', $partyCode)
            ->where('approvals_data.approval_status', 'Approved')
            ->select(
                'approvals_data.product_id',
                'products.name as product_name',
                'products.part_no as part_no',
                'products.slug as slug',
                'approvals_data.order_qty as approved_qty',
                'order_details.quantity as order_qty',
                'approvals_data.rate',
                'approvals_data.bill_amount',
                'approvals_data.is_new'
            )
            ->get();

        // Fetch unavailable items from the order_details table
        $unavailableItems = DB::table('order_details')
            ->leftJoin('approvals_data', function ($join) use ($orderId) {
                $join->on('order_details.product_id', '=', 'approvals_data.product_id')
                     ->where('approvals_data.order_id', $orderId);
            })
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->where('order_details.order_id', $orderId)
            ->whereNull('approvals_data.product_id')
            ->select(
                'order_details.product_id',
                'products.name as product_name',
                'products.part_no as part_no',
                'products.slug as slug',
                'order_details.quantity as qty'
            )
            ->get();

        // Check if there are any approved products or unavailable items
        if ($approvedProducts->isEmpty() && $unavailableItems->isEmpty()) {
            return response()->json(['message' => 'No approved or unavailable products found for the specified order and party.'], 404);
        }

        // Fetch user details based on party_code
        $userDetails = DB::table('users')->where('party_code', $partyCode)->select('company_name', 'manager_id', 'phone')->first();

        if (!$userDetails) {
            return response()->json(['error' => 'User not found for the provided party code.'], 404);
        }

        $manager_phone = $this->getManagerPhone($userDetails->manager_id);
        if (!$manager_phone) {
            return response()->json([
                'error' => 'Manager phone not available for the provided manager ID.',
                'manager_id' => $userDetails->manager_id
            ], 404);
        }

        $order = DB::table('orders')->where('id', $orderId)->select('id', 'code', 'created_at')->first();

        // Prepare data for the PDF
        $pdfData = [
            'approvedProducts' => $approvedProducts,
            'unavailableItems' => $unavailableItems,
            'orderId' => $orderId,
            'partyCode' => $partyCode,
            'userDetails' => $userDetails,
            'manager_phone' => $manager_phone,
            'order' => $order
        ];

        // Load the PDF view and pass the data
        $pdf = PDF::loadView('backend.invoices.approved_products', $pdfData);

        // Define the file name and path
        $fileName = 'approved-products-' . $orderId . '-' . uniqid() . '.pdf';
        $filePath = public_path('approved_products_pdf/' . $fileName);

        // Save the PDF to the specified directory
        $pdf->save($filePath);

        // Offer the file for download
        return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
    }




    public function sendWhatsAppMessage ($order_id,$party_code)
    {

       // Validate the input parameters
        if (!$order_id || !$party_code) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request. Order ID and ID are required.',
            ], 400);
        }

        // Fetch the first matching record from the addresses table
        $userDetails = DB::table('addresses')->where('acc_code', $party_code)->first();

        if (!$userDetails) {
            return response()->json([
                'success' => false,
                'message' => 'No record found in addresses table for the provided Party Code.',
            ], 404);
        }

      

        $managerId = DB::table('users')->where('party_code', $party_code)->pluck('manager_id')->first();  
        if (!$managerId) {
            return response()->json([
                'success' => false,
                'message' => 'No manager found for the provided Party Code.',
            ], 404);
        }
        $manager_phone=$this->getManagerPhone($managerId);

       

         // Fetch the order details from the orders table
        $order = DB::table('orders')->where('id', $order_id)->first();
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'No record found in orders table for the provided Order ID.',
            ], 404);
        }
        
        $publicUrl=$this->generateApprovalPdfURL($order_id,$party_code);

        // whatsapp sending code start 
          $templateData = [
                    'name' => 'utility_product_approved', // Replace with your template name, e.g., 
                    'language' => 'en_US', // Replace with your desired language code
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                ['type' => 'document', 'document' => ['link' => $publicUrl,'filename' => basename($publicUrl),]],
                            ],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $userDetails->company_name],
                                ['type' => 'text', 'text' => $order->code],
                                ['type' => 'text', 'text' => $order->created_at],
                                ['type' => 'text', 'text' => $manager_phone],

                            ],
                        ],
                    ],
                ];


            $to=$userDetails->phone;
                
            $whatsAppWebService = new WhatsAppWebService();
        
            $response =$whatsAppWebService->sendTemplateMessage($to,$templateData);


           // Check the response structure and message status
        if (isset($response['messages'][0]['message_status']) && $response['messages'][0]['message_status'] === 'accepted') {
            $messageId = $response['messages'][0]['id']; // Extract message ID
            return response()->json([
                'success' => true,
                'message' => 'WhatsApp message sent successfully.',
                'data' => [
                    'message_id' => $messageId,
                    'recipient' => $response['contacts'][0]['input'] ?? 'Unknown',
                ],
            ]);

             // Update is_processed to true after generating the PDF
                        // DB::table('order_approvals')
                        //     ->where('code', $order->code)
                        //     ->where('party_code', $partyCode)
                        //     ->update(['is_processed' => true]);
        } else {
            $error = $response['error'] ?? 'Failed to send message due to an unknown error.';
            return response()->json([
                'success' => false,
                'message' => $error,
            ], 500);
        }

        // whatsapp sending code end
    }

    public function updateBilledQuantity(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:approvals_data,id',
            'order_qty' => 'required|numeric|min:0',
        ]);

        try {
            // Find the record
            $approvalData = DB::table('approvals_data')->where('id', $validated['id'])->first();

            if (!$approvalData) {
                return response()->json(['success' => false, 'message' => 'Record not found.']);
            }

            // Update the record
            DB::table('approvals_data')
                ->where('id', $validated['id'])
                ->update(['order_qty' => $validated['order_qty']]);

            return response()->json([
                'success' => true,
                'message' => 'Order quantity updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the record: ' . $e->getMessage(),
            ]);
        }
    }


   public function cancelItem(Request $request)
    {
        // Validate the request to ensure an ID is provided
        $request->validate([
            'id' => 'required|integer', // Validate that ID is an integer
        ]);

        // Fetch the item from the approvals_data table using the provided ID
        $item = DB::table('approvals_data')->where('id', $request->id)->first();

        // Check if the item exists
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item not found.']);
        }

        // Update the 'manually_cancel_item' column to mark the item as canceled
        $updated = DB::table('approvals_data')
            ->where('id', $request->id)
            ->update(['manually_cancel_item' => 1]); // Set to 1 to indicate cancellation

        // Check if the update was successful
        if ($updated) {
            return response()->json(['success' => true, 'message' => 'Item successfully canceled.']);
        }

        // If the update failed, return an error response
        return response()->json(['success' => false, 'message' => 'Failed to cancel the item.']);
    }

}
