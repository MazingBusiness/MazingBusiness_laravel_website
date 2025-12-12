<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MakePurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\PurchaseBag;
use App\Models\Product;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Brand;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Address;
use App\Models\ProductApi;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceDetail;
use App\Models\Staff;
use App\Models\DebitNoteInvoice;
use App\Models\DebitNoteInvoiceDetail;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\State;
use App\Models\Challan;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\SubOrderDetail;
use App\Models\ChallanDetail;
use App\Models\Carrier;
use App\Models\InvoiceOrderDetail;
use App\Models\InvoiceOrder;
use App\Models\ResetInventoryProduct;
use App\Models\OrderLogistic;
use App\Models\EwayBill;
use App\Models\RewardUser;
use App\Models\RewardPointsOfUser;

use App\Models\Barcode;
use NumberFormatter;
use App\Http\Controllers\ZohoController;

use Illuminate\Support\Facades\DB;

use Illuminate\Http\JsonResponse;
use App\Services\WhatsAppWebService;
use App\Exports\PurchaseOrderExport;
use Exception;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use Config;
use Hash;
use PDF;
use Session;
use App\Models\Currency;
use App\Models\Language;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

use App\Exports\FinalPurchaseInvoiceDetails;
use App\Exports\DebitNoteInvoiceExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use App\Exports\FinalCreditInvoiceDetails;
use App\Exports\PurchaseOrderExportGlobal;

use App\Services\PdfContentService;

class PurchaseOrderController extends Controller
{
    

     public function exportSupplyOrder($id)
    {
        $order = MakePurchaseOrder::with(['details.product', 'warehouse'])->findOrFail($id);

        return Excel::download(
            new PurchaseOrderExport($order),
            'Supply_Order_' . $order->purchase_order_no . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX,
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'        => 'no-cache',
                'Expires'       => '0',
            ]
        );
    }

    public function exportAllSupplyOrders()
    {
        return Excel::download(
            new PurchaseOrderExportGlobal(),
            'All_Supply_Orders.xlsx',
            \Maatwebsite\Excel\Excel::XLSX,
            [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'        => 'no-cache',
                'Expires'       => '0',
            ]
        );
    }

    //manual po part start

    // Controller
public function viewManualMakePurchaseOrder41()
{
    // same warehouse filter
    $warehouses = Warehouse::whereIn('id', [1, 2, 6])->get();

    $all_sellers = DB::table('shops')
        ->join('sellers', 'shops.seller_id', '=', 'sellers.id')
        ->join('users', 'sellers.user_id', '=', 'users.id')
        ->select(
            'sellers.id as seller_id',
            'shops.name as seller_name',
            'shops.address as seller_address',
            'sellers.gstin',
            'shops.phone as seller_phone',
            'users.state as state_name'
        )
        ->get();

    $states = State::all();
    $orders = collect(); // Empty on first load

    $products = DB::table('products')
        ->select('id','name','part_no','purchase_price','hsncode','tax')
        ->where('current_stock','1')
        ->get();

    $categoryGroups = CategoryGroup::all();
    $categories     = Category::all();
    $brands         = Brand::all();

    $all_customers = DB::table('addresses')
        ->leftJoin('states', 'addresses.state_id', '=', 'states.id')
        ->select(
            'addresses.id',
            'addresses.company_name',
            'addresses.address',
            'addresses.phone',
            'addresses.gstin',
            'addresses.acc_code',
            'addresses.city',
            'states.name as state_name'
        )
        ->get();

    // new blade name:
    return view(
        'backend.po.manual_make_purchase_order_41',
        compact('warehouses','all_sellers','orders','products','categoryGroups','categories','brands','states','all_customers')
    );
}

      public function viewManualMakePurchaseOrder()
        {
            $warehouses = Warehouse::whereIn('id', [1, 2, 6])->get(); // only specific warehouses
            $all_sellers = DB::table('shops')
            ->join('sellers', 'shops.seller_id', '=', 'sellers.id')
            ->join('users', 'sellers.user_id', '=', 'users.id')
            // ->leftJoin('states', 'users.state', '=', 'states.id')
            ->select(
                'sellers.id as seller_id',
                'shops.name as seller_name',
                'shops.address as seller_address',
                'sellers.gstin',
                'shops.phone as seller_phone',
                'users.state as state_name' // ✅ Correct
            )
            ->get();

            $states=State::all();
            $orders = collect(); // Empty on first load
            $products = DB::table('products')->select('id', 'name', 'part_no', 'purchase_price')->where('current_stock','1')->get();
            $categoryGroups = CategoryGroup::all();
            $categories = Category::all(); // or based on group dynamically later via AJAX
            $brands = Brand::all(); // or filtered by category

            $all_customers = DB::table('addresses')
            ->leftJoin('states', 'addresses.state_id', '=', 'states.id')
            ->select(
                'addresses.id',
                'addresses.company_name',
                'addresses.address',
                'addresses.phone',
                'addresses.gstin', 
                'addresses.acc_code', 
                'addresses.city', // ✅ Add this
                'states.name as state_name'
            )
            ->get();

            return view('backend.po.manual_make_purchase_order', compact('warehouses', 'all_sellers', 'orders','products','categoryGroups','categories','brands','states','all_customers'));
        }


 public function editManualPurchaseOrder($id)
{
    $invoice = PurchaseInvoice::with('purchaseInvoiceDetails')->findOrFail($id);

    // ✅ Revert received/pending in PO detail
    foreach ($invoice->purchaseInvoiceDetails as $detail) {
        $partNo = $detail->part_no;
        $poNo = $detail->purchase_order_no;
        $qtyFromInvoice = $detail->qty;
    
        if ($poNo && $poNo !== 'Manual Entry') {
            $poDetail = PurchaseOrderDetail::where('part_no', $partNo)
                ->where('purchase_order_no', $poNo)
                ->first();
    
            if ($poDetail) {
                $orderedQty = $poDetail->received + $poDetail->pending;
    
                $newReceived = max(0, $poDetail->received - $qtyFromInvoice);
                $newPending = $poDetail->pending + $qtyFromInvoice;
    
                // ✅ Only update pending if it does not exceed the original ordered quantity
                if ($newPending <= $orderedQty) {
                    $poDetail->update([
                        'received' => $newReceived,
                        'pending' => $newPending,
                        'updated_at' => now(),
                    ]);
                } else {
                    // ✅ Only update received (don't increase pending beyond ordered)
                    $poDetail->update([
                        'received' => $newReceived,
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    $warehouses = Warehouse::whereIn('id', [1, 2, 6])->get();

    $all_sellers = DB::table('shops')
        ->join('sellers', 'shops.seller_id', '=', 'sellers.id')
        ->join('users', 'sellers.user_id', '=', 'users.id')
        ->select(
            'sellers.id as seller_id',
            'shops.name as seller_name',
            'shops.address as seller_address',
            'sellers.gstin',
            'shops.phone as seller_phone',
            'users.state as state_name'
        )
        ->get();

    $states = State::all();

    $products = Product
        ::select('id', 'name', 'part_no', 'purchase_price', 'hsncode', 'tax')
       // ->where('current_stock', '1')
        ->get();

    $categoryGroups = CategoryGroup::all();
    $categories = Category::all();
    $brands = Brand::all();

    $all_customers = DB::table('addresses')
        ->leftJoin('states', 'addresses.state_id', '=', 'states.id')
        ->select(
            'addresses.id',
            'addresses.company_name',
            'addresses.address',
            'addresses.phone',
            'addresses.gstin',
            'addresses.acc_code',
            'addresses.city',
            'states.name as state_name'
        )
        ->get();

    // ✅ Get seller info from seller_id for pre-fill
    $sellerInfo = null;

    if ($invoice->seller_id) {
        $sellerInfo = DB::table('shops')
            ->join('sellers', 'shops.seller_id', '=', 'sellers.id')
            ->join('users', 'sellers.user_id', '=', 'users.id')
            ->leftJoin('states', 'users.state', '=', 'states.id')
            ->where('sellers.id', $invoice->seller_id)
            ->select(
                'sellers.id as seller_id',
                'shops.name as seller_name',
                'shops.address as seller_address',
                'sellers.gstin',
                'shops.phone as seller_phone',
                'states.name as state_name'
            )
            ->first();
    }

    // ✅ Generate product rows
    $productRows = [];
    foreach ($invoice->purchaseInvoiceDetails as $index => $detail) {
        
        $product = $products->firstWhere('part_no', $detail->part_no);
       
        $gstRate = $detail->tax;
        $price = $detail->price;
        $priceWithGst = $product->purchase_price;
        $subtotal = round($price * $detail->qty, 2);

        $productRows[] = [
            'index' => $index,
            'product_id' => $product->id ?? '',
            'part_no' => $detail->part_no,
            'product_name' => $product->name ?? '',
            'purchase_price' => $priceWithGst,
            'hsncode' => $detail->hsncode,
            'price_without_gst' => $price,
            'quantity' => $detail->qty,
            'purchase_order_no' => $detail->purchase_order_no,
            'line_total' => $subtotal,
            'gst' => $gstRate
        ];
    }

    return view('backend.po.edit_manual_make_purchase_order', compact(
        'invoice',
        'warehouses',
        'all_sellers',
        'states',
        'products',
        'categoryGroups',
        'categories',
        'brands',
        'all_customers',
        'productRows',
        'sellerInfo'
    ));
}



public function saveManualPurchaseOrderEdit(Request $request)
{
    // $all_order=$request->input('orders');
    // echo "<pre>";
    // print_r($all_order);
    // die();
    $validatedData = $request->validate([
        'purchase_invoice_id' => 'required|integer|exists:purchase_invoices,id',

        'seller_info.seller_name' => 'required|string',
        'seller_info.seller_phone' => 'required|string',
        'seller_info.state_name' => 'required|string',
        'seller_info.address' => 'nullable|string',
        'seller_info.gstin' => 'nullable|string',

        'orders.*.part_no' => 'required|string',
        'orders.*.product_name' => 'required|string',
        'orders.*.quantity' => 'required|integer|min:1',
        'orders.*.purchase_price' => 'required|numeric|min:0',

        'seller_invoice_no' => 'nullable|string',
        'seller_invoice_date' => 'nullable|date',
    ]);

    $invoiceId = $request->purchase_invoice_id;
    $sellerInfo = $request->input('seller_info');
    $sellerId = $sellerInfo['seller_id'] ?? null;

    if (empty($sellerId)) {
        $sellerId = $this->createSellerFromManualEntry($sellerInfo);
        $sellerInfo['seller_id'] = $sellerId;
    }

    $warehouseId = $request->input('warehouse_id');
    $companyState = User::where('id', Warehouse::where('id', $warehouseId)->value('user_id'))->value('state');
    $sellerState = strtoupper($sellerInfo['state_name']);

    $invoice = PurchaseInvoice::findOrFail($invoiceId);
    $purchaseNo = $invoice->purchase_no;
    $purchaseOrderNo = $invoice->purchase_order_no;

    // 🧹 Delete existing details
    PurchaseInvoiceDetail::where('purchase_invoice_id', $invoiceId)->delete();

    $total = 0;
    $totalCgst = 0;
    $totalSgst = 0;
    $totalIgst = 0;

    foreach ($request->input('orders') as $product) {
        $qty = (float) $product['quantity'];
        $rate = (float) $product['price_without_gst'];
        $PurchasePrice = (float) $product['purchase_price'];
        $partNo = $product['part_no'];
        $hsncode = $product['hsncode'] ?? '';
        $selectedPO = $product['purchase_order_no'] ?? null;



        $productModel = Product::where('part_no', $partNo)->first();
        $tax = $productModel->tax ?? 0;
        $taxType = $productModel->tax_type ?? 'percent';

        $price = $rate;
        //if ($taxType === 'percent') {
        //    $price = round($rate / (1 + ($tax / 100)), 2);
        //}

        $grossAmt = round($price * $qty, 2);
        $cgst = $sgst = $igst = 0;

        if ($sellerState === strtoupper($companyState)) {
            $cgst = $sgst = round(($grossAmt * ($tax / 2)) / 100, 2);
            $totalCgst += $cgst;
            $totalSgst += $sgst;
        } else {
            $igst = round(($grossAmt * $tax) / 100, 2);
            $totalIgst += $igst;
        }

        $orderNo = PurchaseOrderDetail::where('purchase_order_no', $selectedPO)
            ->where('part_no', $partNo)
            ->value('order_no') ?? 'Manual Entry';

        PurchaseInvoiceDetail::create([
            'purchase_invoice_id' => $invoiceId,
            'purchase_invoice_no' => $purchaseNo,
            'purchase_order_no'   => $selectedPO,
            'part_no'             => $partNo,
            'qty'                 => $qty,
            'order_no'            => $orderNo,
            'hsncode'             => $hsncode,
            'price'               => $price,
            'gross_amt'           => $grossAmt,
            'cgst'                => $cgst,
            'sgst'                => $sgst,
            'igst'                => $igst,
            'tax'                 => $tax,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        
        Product::where('part_no', $partNo)->update([
            'purchase_price' => $PurchasePrice,
        ]);

        $total += $grossAmt;

        if ($productModel) {
            $productModel->update(['hsncode' => $hsncode]);
            $productModel->update(['purchase_price' => $PurchasePrice]);
        }

        // Inventory entry
        $requestSubmit = new \Illuminate\Http\Request();
        $requestSubmit->merge(['product_id' => $productModel->id]);
        $this->inventoryProductEntry($requestSubmit);
    }

    // 📝 Update purchase invoice
    $invoice->update([
        'seller_id'            => $sellerId,
        'warehouse_id'         => $warehouseId,
        'seller_info' => [
            'seller_id'      => (string) $sellerId,
            'seller_name'    => $sellerInfo['seller_name'] ?? '',
            'seller_address' => str_replace(["\r", "\n"], '', $sellerInfo['address'] ?? ''),
            'seller_gstin'   => $sellerInfo['gstin'] ?? '',
            'seller_phone'   => $sellerInfo['seller_phone'] ?? '',
            'state_name'     => $sellerInfo['state_name'] ?? null,
        ],
        'total'                => $total,
        'total_cgst'           => $totalCgst,
        'total_sgst'           => $totalSgst,
        'total_igst'           => $totalIgst,
        'seller_invoice_no'    => $request->input('seller_invoice_no'),
        'seller_invoice_date'  => $request->input('seller_invoice_date'),
        'updated_at'           => now(),
    ]);

    // ✅ Update PurchaseOrderDetail (received, pending)
    foreach ($request->input('orders') as $product) {
        $qty = (float) $product['quantity'];
        $partNo = $product['part_no'];
        $selectedPO = $product['purchase_order_no'] ?? null;
        $hsncode = $product['hsncode'] ?? '';

        if ($selectedPO && $selectedPO !== 'Manual Entry') {
            $poDetail = PurchaseOrderDetail::where('part_no', $partNo)
                ->where('purchase_order_no', $selectedPO)
                ->orderBy('id', 'desc')
                ->first();

            if ($poDetail) {
                $orderNo = $poDetail->order_no ?? 'Manual Entry';
                $received = $poDetail->received + $qty;
                $pending = max(0, $poDetail->pending - $qty);

                PurchaseOrderDetail::where('id', $poDetail->id)->update([
                    'received' => $received,
                    'pending'  => $pending,
                    'hsncode'  => $hsncode,
                    'order_no' => $orderNo,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    // ✅ Update is_closed status in MakePurchaseOrder
    $updatedPOs = collect($request->input('orders'))->pluck('purchase_order_no')->unique()->filter();
    foreach ($updatedPOs as $poNo) {
        $makePurchaseOrderId = PurchaseOrderDetail::where('purchase_order_no', $poNo)->value('make_purchase_order_id');

        if ($makePurchaseOrderId) {
            $hasPending = PurchaseOrderDetail::where('make_purchase_order_id', $makePurchaseOrderId)
                ->where('pending', '>', 0)
                ->exists();

            MakePurchaseOrder::where('id', $makePurchaseOrderId)->update([
                'is_closed' => $hasPending ? 0 : 1,
                'updated_at' => now(),
            ]);
        }
    }

   
    // 🔁 Push to Zoho after update
    try {
        $zoho = new ZohoController();
        $zoho->updateVendorBill($invoiceId);
    } catch (\Exception $e) {
        \Log::error('Zoho Vendor Bill update failed: ' . $e->getMessage());
    }

    return redirect()->route('purchase.invoices.list')->with('status', 'Purchase Invoice updated successfully.');
}
public function removeProductFromInventory(Request $request)
{
    $partNo = $request->part_no;

    $product = Product::where('part_no', $partNo)->first();

    if (!$product) {
        return response()->json(['success' => false, 'message' => 'Product not found.']);
    }

    try {
        $req = new \Illuminate\Http\Request();
        $req->merge(['product_id' => $product->id]);
        $this->inventoryProductEntry($req);

        return response()->json(['success' => true]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
}




    public function getProductPOs(Request $request)
    {
        $partNo = $request->part_no;
        $sellerId = $request->seller_id;

        $purchaseOrders = PurchaseOrderDetail::where('part_no', $partNo)
            ->whereHas('makePurchaseOrder', function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId)
                      ->where('is_closed', '!=', 1)
                      ->where('force_closed', '!=', 1);
            })
            ->with('makePurchaseOrder:id,purchase_order_no,is_closed,force_closed,created_at') // ✅ include created_at
            ->get();

        $poList = $purchaseOrders->map(function ($detail) {
            return [
                'po' => $detail->makePurchaseOrder->purchase_order_no,
                'pending' => $detail->pending,
                'date' => optional($detail->makePurchaseOrder->created_at)->format('d-m-Y'), // ✅ format date
            ];
        })->unique('po')->values();

        return response()->json($poList);
    }


    //manual po end

    public function getCategoriesByGroup($groupId)
    {
        $categories = Category::where('category_group_id', $groupId)->get(['id', 'name']);
        return response()->json($categories);
    }
     public function getProductsByCategoryAndBrand(Request $request)
    {
        $categoryIds = $request->input('category_ids', []); // Categories to filter
        $brandIds = $request->input('brand_ids', []);       // Brands to filter

        $query = Product::query();

        // If no category or brand is selected, fetch all products
        if (empty($categoryIds) && empty($brandIds)) {
            $products = $query->where('published', true)
                              //->where('current_stock', '>', 0)
                              ->get(['id', 'name', 'part_no','purchase_price','hsncode','tax']);
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
                              //->where('current_stock', '>', 0)
                              ->get(['id', 'name', 'part_no','hsncode','purchase_price','tax']);
        }

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
    public function transferPurchaseOrderData()
    {
        // Fetch all data from the existing table
        $oldOrders = DB::table('final_purchase_order')->get();

        foreach ($oldOrders as $oldOrder) {
            // Insert into new_final_purchase_orders
            $newOrder = MakePurchaseOrder::create([
                'purchase_order_no' => $oldOrder->purchase_order_no,
                'date' => $oldOrder->date,
                'seller_id' => $oldOrder->seller_id,
                'seller_info' => $oldOrder->seller_info,
                'product_invoice' => $oldOrder->product_invoice,
                'convert_to_purchase_status' => $oldOrder->convert_to_purchase_status,
                'is_closed' => $oldOrder->is_closed,
                'force_closed' => $oldOrder->force_closed,
                'order_no' => $oldOrder->order_no
            ]);

            // Decode JSON product_info
            $productDetails = json_decode($oldOrder->product_info, true);

            if (is_array($productDetails)) {
                foreach ($productDetails as $product) {
                    PurchaseOrderDetail::create([
                        'make_purchase_order_id' => $newOrder->id, // ✅ Correct foreign key
                        'purchase_order_no' => $newOrder->purchase_order_no, // ✅ Fixing NULL issue
                        'part_no' => $product['part_no'] ?? null,
                        'qty' => $product['qty'] ?? 0,
                        'order_no' => $product['order_no'] ?? null,
                        'seller_info' => $oldOrder->seller_info,
                        'age' => $product['age'] ?? 0,
                        'hsncode' => $product['hsncode'] ?? null,
                        'received' => 0,
                        'pre_close' => 0,
                        'pending' => $product['qty'] ?? 0 // Default pending as ordered qty
                    ]);
                }
            }
        }

        return response()->json(['message' => 'Data transferred successfully'], 200);
    }


    public function purchaseOrder(Request $request) {
       
        // Retrieve the list of sellers for the dropdown
        $sellers = DB::table('users')
            ->join('sellers', 'users.id', '=', 'sellers.user_id')
            ->select('users.id', 'users.name')
            ->orderBy('users.name', 'asc')
            ->get();
    
       // Start query for purchase orders
        $query = PurchaseBag::leftJoin('products', 'purchase_bags.part_no', '=', 'products.part_no')
        ->leftJoin('sellers', 'products.seller_id', '=', 'sellers.id')
        ->leftJoin('users', 'sellers.user_id', '=', 'users.id')
        ->leftJoin('shops', 'sellers.id', '=', 'shops.seller_id') // Join sellers with shops
        ->select(
            'purchase_bags.*',
            'products.seller_id',
            'sellers.user_id',
            'users.name as seller_name',
            'shops.name as seller_company_name' // Retrieve name as seller_company_name
        )
        // Add the condition to exclude records where delete_status is 1
        ->where('purchase_bags.delete_status', '!=', 1);
    
        // Apply seller name filter if provided
        if ($request->filled('sellerName')) {
            $query->where('users.id', '=', $request->sellerName);
        }
    
        // Apply sorting if provided
        if ($request->filled('sort') && $request->filled('direction')) {
            $query->orderBy($request->sort, $request->direction);
        } else {
            $query->orderBy('purchase_bags.id', 'asc'); // Default sorting
        }
    
        // Get paginated results
        $purchaseOrders = $query->paginate(100)->appends($request->all());
        // echo "<pre>";
        // print_r($purchaseOrders->toArray());
        // die();
    
        return view('backend.po.purchase_bags', compact('purchaseOrders', 'sellers'));
    }

    public function makePurchaseOrder(Request $request){
         // Get the selected orders' IDs
            $selectedOrders = $request->input('selectedOrders', []);

            $states=State::all();
            
            // Fetch the selected orders from the database, grouping by part_no and combining order_no and quantities
            $orders = PurchaseBag::whereIn('purchase_bags.id', $selectedOrders)
                ->leftJoin('products', 'purchase_bags.part_no', '=', 'products.part_no')
                ->leftJoin('sellers', 'products.seller_id', '=', 'sellers.id')
                ->leftJoin('shops', 'sellers.id', '=', 'shops.seller_id')
                ->select(
                    'purchase_bags.part_no',
                    'purchase_bags.item',
                    DB::raw('GROUP_CONCAT(DISTINCT purchase_bags.order_no ORDER BY purchase_bags.order_no ASC SEPARATOR ", ") as order_no'),
                    DB::raw('GROUP_CONCAT(DISTINCT purchase_bags.age ORDER BY purchase_bags.age ASC SEPARATOR ", ") as age'),
                    DB::raw('GROUP_CONCAT(DISTINCT DATE_FORMAT(purchase_bags.order_date, "%d/%m/%y") ORDER BY purchase_bags.order_date ASC SEPARATOR ", ") as order_date'),
                    DB::raw('SUM(purchase_bags.to_be_ordered) as total_quantity'),
                    'products.seller_id',
                    'products.purchase_price',
                    'shops.name as seller_company_name',
                    'shops.address as seller_address',
                    'sellers.gstin as seller_gstin',
                    'shops.phone as seller_phone'
                )
                ->groupBy('purchase_bags.part_no', 'purchase_bags.item', 'products.seller_id', 'products.purchase_price', 'shops.name', 'shops.address', 'sellers.gstin', 'shops.phone')
                ->get();

                // Fetch warehouses with id 1, 2, and 6
              $warehouses = DB::table('warehouses')
                  ->whereIn('id', [1, 2, 6])
                  ->select('id', 'name')
                  ->get();

                   $all_sellers = DB::table('shops')
                ->join('sellers', 'shops.seller_id', '=', 'sellers.id')
                ->select(
                    'sellers.id as seller_id',
                    'shops.name as seller_name',
                    'shops.address as seller_address',
                    'sellers.gstin',
                    'shops.phone as seller_phone'
                )
                ->get();

                  // Get the current seller ID from the first order in the collection (if any orders exist)
              // $current_seller_id = $orders->isNotEmpty() ? $orders->first()->seller_id : null;
            $current_seller_name = $orders->isNotEmpty() ? $orders->first()->seller_company_name : null;
              // echo $current_seller_id;
              // die();

            // Check if $orders is empty
            // Ensure $orders is not null
            if ($orders->isEmpty()) {
              $orders = collect(); // This will make $orders an empty collection
          }
               return view('backend.po.make_purchase_order', compact('orders', 'warehouses','all_sellers','current_seller_name','states'));
    }


    public function deletePurchaseBagItem($id, Request $request)
    {
        // Validate the ID exists
        $order = PurchaseBag::where('id', $id)->first();
       
        
        if (!$order) {
            return redirect()->back()->with('status', 'Purchase order not found!');
        }

        // Get the order_id from the $order object
        $orderId = $order->order_no; // Assuming 'order_id' is the correct field name

        // Delete the purchase order
        // DB::table('purchase_order')->where('id', $id)->delete();
         // Update the delete_status to 1 instead of deleting the purchase order
        PurchaseBag::
        where('id', $id)
        ->update(['delete_status' => 1]);

        // Redirect back with a success message, including the order_id
        return redirect()->back()->with('status', "Purchase order with Order ID $orderId deleted successfully!")->withInput($request->all());
    }

    public function supplyOrderLising(Request $request)
    {
        // Get search and sorting parameters
        $search = $request->input('search');
        $sortColumn = $request->input('sort_column', 'date');
        $sortOrder = $request->input('sort_order', 'desc');

        // Fetch orders with product details (Eager Loading)
        $orders = MakePurchaseOrder::with('details.product') // ✅ This will load related product details
            ->join('sellers', 'make_purchase_orders.seller_id', '=', 'sellers.id')
            ->select(
                'make_purchase_orders.*',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_name')) as seller_name"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_phone')) as seller_phone"),
                'make_purchase_orders.force_closed'
            )
            ->where('is_closed', '=', 0);

        // Apply search filter
        if ($search) {
            $orders->where(function ($query) use ($search) {
                $query->where('make_purchase_orders.purchase_order_no', 'LIKE', '%' . $search . '%')
                      ->orWhere(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_name'))"), 'LIKE', '%' . $search . '%');
            });
        }

        // Apply sorting
        $orders->orderBy($sortColumn, $sortOrder);


        // Paginate results
        $orders = $orders->paginate(100)->withQueryString();
        

        // Return the view with orders
        return view('backend.po.supply_order_listing', compact('orders', 'search', 'sortColumn', 'sortOrder'));
    }

    public function forceClose($id)
    {
        // Fetch the purchase order by ID
        $purchaseOrder = MakePurchaseOrder::find($id);

        if (!$purchaseOrder) {
            return redirect()->back()->with('error', 'Purchase order not found.');
        }

        // Just update the force_closed field
        $purchaseOrder->update([
            'force_closed' => 1,
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('status', 'Purchase order force closed successfully.');
    }



    public function productInformation(Request $request)
    {
        // ini_set('memory_limit', '512M');
        ini_set('memory_limit', '-1'); 
        $categories = Category::where('parent_id', 0)
                ->with('childrenCategories')
                ->get();
        $categoryGroups = CategoryGroup::orderBy('name')->get();
                
        $brands = Brand::orderBy('name')->get();
        $states = State::all();

        $allProducts = Product::where('current_stock', '>', 0)->get();
        $purchaseOrderNos = $request->query('purchase_order_nos');

        if (!empty($purchaseOrderNos)) {
            $purchaseOrderNos = explode(',', urldecode($purchaseOrderNos));
        } else {
            return redirect()->back()->with('error', 'Please select at least one order.');
        }

        $orders = MakePurchaseOrder::with(['details' => function ($query) {
            $query->where('pending', '>', 0);
        }])->whereIn('purchase_order_no', $purchaseOrderNos)->get();


        if ($orders->isEmpty()) {
            return redirect()->back()->with('error', 'No orders found with pending quantity for the selected purchase orders.');
        }

        $uniqueSellers = $orders->pluck('seller_id')->unique();

        if ($uniqueSellers->count() > 1) {
            return redirect()->back()->with('error', 'You cannot select POs from different sellers. Please select POs from the same seller.');
        }

        $order = $orders->first();
        $sellerInfo = json_decode($order->seller_info, true);
        $purchaseNo = "";

        $productInfo = [];
        $partPurchaseOrders = [];
        $poPendingQty = [];

        foreach ($orders as $singleOrder) {
            foreach ($singleOrder->details as $product) {
                $pendingQty = $product->pending;

                if ($pendingQty > 0) {
                    $productDetails = Product::where('part_no', $product->part_no)->first();

                    // Track the PO this part_no is in
                    $partPurchaseOrders[$product->part_no][] = $singleOrder->purchase_order_no;

                    // Store pending qty for each PO-part_no
                    $poPendingQty[$product->part_no][$singleOrder->purchase_order_no] = $pendingQty;

                    $taxRate = $product->tax ?? 0;
                    $purchasePrice = $productDetails->purchase_price ?? 0;

                    // Calculate exclusive price
                    $priceExclGST = $taxRate > 0 ? ($purchasePrice / (1 + ($taxRate / 100))) : $purchasePrice;

                    $productInfo[] = [
                        'purchase_order_no' => $singleOrder->purchase_order_no,
                        'purchase_order_details_id' => $product->id,
                        'part_no' => $product->part_no,
                        'qty' => $product->qty,
                        'tax' => $taxRate,
                        'pending' => $pendingQty,
                        'order_no' => $product->order_no,
                        'age' => $product->age,
                        'hsncode' => $productDetails->hsncode ?? '',
                        'product_name' => $productDetails->name ?? 'Unknown',
                        'purchase_price' => $purchasePrice,
                        'exclusive_price' => round($priceExclGST, 2),
                        'subtotal' => $pendingQty * $purchasePrice,
                    ];
                }
            }
        }
       

        // Fetch all possible PO numbers per part from PurchaseOrderDetail (even if pending is 0)
        foreach ($partPurchaseOrders as $partNo => $existingPOs) {
            // $allPOs = PurchaseOrderDetail::where('part_no', $partNo)
            //     ->pluck('purchase_order_no')
            //     ->unique()
            //     ->toArray();
            $allPOs = PurchaseOrderDetail::where('part_no', $partNo)
            ->whereHas('makePurchaseOrder', function ($q) {
                $q->where('is_closed', '!=', 1)->where('force_closed', '!=', 1);
            })
            ->pluck('purchase_order_no')
            ->unique()
            ->toArray();

            $mergedPOs = array_unique(array_merge($existingPOs, $allPOs));
            $partPurchaseOrders[$partNo] = $mergedPOs;

            foreach ($mergedPOs as $po) {
                // $pendingQty = PurchaseOrderDetail::where('part_no', $partNo)
                //     ->where('purchase_order_no', $po)
                //     ->value('pending') ?? 0;
                $pendingQty = PurchaseOrderDetail::where('part_no', $partNo)
                    ->where('purchase_order_no', $po)
                    ->whereHas('makePurchaseOrder', function ($q) {
                        $q->where('is_closed', '!=', 1)->where('force_closed', '!=', 1);
                    })
                    ->value('pending') ?? 0;

                $poPendingQty[$partNo][$po] = $pendingQty;
            }
        }

         $all_sellers = DB::table('shops')
        ->join('sellers', 'shops.seller_id', '=', 'sellers.id')
        ->join('users', 'sellers.user_id', '=', 'users.id')
        // ->leftJoin('states', 'users.state', '=', 'states.id')
        ->select(
            'sellers.id as seller_id',
            'shops.name as seller_name',
            'shops.address as seller_address',
            'sellers.gstin',
            'shops.phone as seller_phone',
            'users.state as state_name' // ✅ Correct
        )
        ->get();

        return view('backend.po.products_information', compact(
            'order',
            'productInfo',
            'sellerInfo',
            'purchaseNo',
            'partPurchaseOrders',
            'poPendingQty',
            'purchaseOrderNos',
            'allProducts',
            'categories',
            'brands',
            'categoryGroups',
            'states',
            'all_sellers'
        ));
    }

    public function getProductRows(Request $request)
    {
        $orderId = $request->order_id;

        $order = MakePurchaseOrder::with('details')->findOrFail($orderId);
        $details = $order->details;

        $html = '';

        foreach ($details as $index => $detail) {
            $product = \App\Models\Product::where('part_no', $detail->part_no)->first();
            $subtotal = $detail->qty * $detail->purchase_price;

            $html .= '<tr class="text-center align-middle">';
            $html .= '<td class="align-middle font-weight-bold text-dark">' . $detail->part_no . '</td>';

            $html .= '<td><input type="text" name="products[' . $index . '][hsncode]" value="' . $detail->hsncode . '" class="form-control form-control-sm text-center border-primary"></td>';

            $html .= '<td class="text-left align-middle">' . ($product->name ?? '') . '</td>';

            $html .= '<td><select name="products[' . $index . '][purchase_order_no]" class="form-control form-control-sm border-primary">';
            $html .= '<option value="' . $detail->order_no . '">' . $detail->order_no . ' (' . $detail->qty . ')</option>';
            $html .= '</select></td>';

            $html .= '<td><input type="number" name="products[' . $index . '][pending]" value="' . $detail->qty . '" class="form-control form-control-sm qty-input text-center border-secondary" data-index="' . $index . '"></td>';

            $html .= '<td><input type="number" name="products[' . $index . '][purchase_price]" value="' . $detail->purchase_price . '" class="form-control form-control-sm price-input text-center border-secondary" data-index="' . $index . '"></td>';

            $html .= '<td class="subtotal text-right text-muted align-middle" data-index="' . $index . '">₹ ' . number_format($subtotal, 2) . '</td>';

            $html .= '<td>
                        <a href="javascript:void(0)" class="delete-row"
                           data-index="' . $index . '"
                           data-id="' . $detail->part_no . '"
                           data-po="' . $detail->order_no . '"
                           data-purchase_order_details_id="' . $detail->id . '"
                           data-pending="' . $detail->qty . '">
                            <i class="las la-trash" style="font-size: 25px; color:#f00; cursor:pointer;"></i>
                        </a>
                      </td>';
            $html .= '</tr>';

            // Hidden inputs
            $html .= '<input type="hidden" name="products[' . $index . '][part_no]" value="' . $detail->part_no . '">';
            $html .= '<input type="hidden" name="products[' . $index . '][order_no]" value="' . $detail->order_no . '">';
            $html .= '<input type="hidden" name="products[' . $index . '][age]" value="' . $detail->age . '">';
        }

        return response()->json(['html' => $html]);
    }
    

    public function addToPurchaseOrderDetails(Request $request)
    {
        try {
            // Fetch `make_purchase_order_id` from `make_purchase_orders`
            $makePurchaseOrder = MakePurchaseOrder::where('purchase_order_no', $request->purchase_order_no)->first();

            if (!$makePurchaseOrder) {
                return response()->json(['error' => 'Purchase order not found'], 404);
            }

            // Validate product existence
            $product = Product::find($request->product_id);
            if (!$product) {
                return response()->json(['error' => 'Product not found'], 404);
            }

            // Insert data into `purchase_order_details`
            $detail = PurchaseOrderDetail::create([
                'make_purchase_order_id' => $makePurchaseOrder->id,
                'seller_info' => $makePurchaseOrder->seller_info,
                'purchase_order_no' => $request->purchase_order_no,
                'part_no' => $request->part_no,
                'hsncode' => $request->hsncode,
                'tax' => $product->tax,
                // 'purchase_price' => $request->purchase_price,
                'qty' => $request->quantity,
                'pending' => $request->quantity,
                'pre_close' => 0,
                'received' => 0,
                'pending' => $request->quantity,
                'order_no' => $request->order_no,  // ✅ New field added
                'age' => $request->age,  // ✅ New field added
            ]);

            // return response()->json(['message' => 'Product added successfully!']);
            return response()->json([
                'message' => 'Product added successfully!',
                'purchase_order_details_id' => $detail->id // ✅ return the inserted ID
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function showProductInfo($id)
    {

        // Fetch the purchase order using Eloquent with related products
        //$order = MakePurchaseOrder::with('details')->findOrFail($id);
        $order = MakePurchaseOrder::with([
            'details' => function ($q) {
                $q->where('pending', '>', 0);
            }
        ])->findOrFail($id);

        // Decode seller info from JSON
        $sellerInfo = json_decode($order->seller_info, true);
        $sellerPurchaseOrders = MakePurchaseOrder::whereRaw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_name')) = ?", [$sellerInfo['seller_name']])
        ->pluck('purchase_order_no');


        // Fetch final purchase order if converted
        $purchaseNo = "";
        
        // Process product details
        $productInfo = [];
        foreach ($order->details as $product) {
            $productDetails = Product::where('part_no', $product->part_no)->first();


            $productInfo[] = [
                'part_no' => $product->part_no,
                'qty' => $product->qty,
                'order_no' => $product->order_no,
                'age' => $product->age,
                'hsncode' => $productDetails->hsncode,
                'product_name' => $productDetails->name ?? 'Unknown',
                'purchase_price' => $productDetails->purchase_price ?? 'N/A',
                'subtotal' => $product->qty * ($productDetails->purchase_price ?? 0),
            ];
        }

       
        // Pass the seller information and product information to the view
        return view('backend.po.product_info', compact('order', 'productInfo', 'sellerInfo', 'purchaseNo'));
    }

    public function updatePreCloseAndStock(Request $request)
    {
        try {
            // ✅ Find the product detail entry
            $product = PurchaseOrderDetail::find($request->purchase_order_details_id);

            if (!$product) {
                return response()->json(['error' => 'Product not found'], 404);
            }

            // ✅ CASE 1: Unavailable by Seller (Checkbox Checked)
            if ($request->unavailable_by_seller) {
                $product->update([
                    'pre_close' => $product->pre_close + $product->pending, // Add to previous pre_close
                    'pending' => 0,
                    'updated_at' => now()
                ]);

                // ✅ Set stock to 0 in products table
                Product::where('part_no', $product->part_no)->update([
                    'seller_stock' => 0,
                    'current_stock' => 0
                ]);

                return response()->json(['message' => 'Product marked as unavailable, stock set to 0'], 200);
            }

            // ✅ CASE 2: Partial Pre-Close
            if ($request->quantity > 0 && $request->quantity < $product->pending) {
                $newPending = $product->pending - $request->quantity;
                $newPreClose = $product->pre_close + $request->quantity;

                $product->update([
                    'pending' => $newPending,
                    'pre_close' => $newPreClose,
                    'updated_at' => now()
                ]);

                return response()->json(['message' => 'Product quantity updated, pre-close recorded'], 200);
            }

            // ✅ CASE 3: Full Quantity Pre-Close (quantity == pending)
            if ($request->quantity == $product->pending) {
                $product->update([
                    'pre_close' => $product->pre_close + $product->pending,
                    'pending' => 0,
                    'updated_at' => now()
                ]);

                // ✅ Set stock to 0
                Product::where('part_no', $product->part_no)->update([
                    'seller_stock' => 0,
                    'current_stock' => 0
                ]);

                return response()->json(['message' => 'Product fully pre-closed, stock set to 0'], 200);
            }

            // ✅ Fallback: if none matched
            return response()->json(['message' => 'No updates performed.'], 200);

        } catch (\Exception $e) {
            \Log::error("Error in updatePreCloseAndStock: " . $e->getMessage());
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }


    public function convertToPurchase(Request $request)
    {
        $validatedData = $request->validate([
            'seller_invoice_no' => 'required|string|max:255',
            'seller_invoice_date' => 'required|date',
            'products.*.hsncode' => 'required|string|max:255',
            'products.*.pending' => 'required|integer|min:0',
            'products.*.purchase_price' => 'required|numeric|min:0',
            'products.*.purchase_order_no' => 'required|string',
        ]);

        $purchaseOrderNos = collect($request->input('products'))->pluck('purchase_order_no')->unique()->values()->toArray();

        $sellerInfo = [
            'seller_name' => $request->input('seller_info.seller_name'),
            'seller_address' => $request->input('seller_info.seller_address'),
            'seller_gstin' => $request->input('seller_info.seller_gstin'),
            'seller_phone' => $request->input('seller_info.seller_phone'),
            'state_name' => $request->input('seller_info.state_name'),
        ];

        $sellerId = $request->input('seller_id');
        if (empty($sellerId)) {
            $sellerId = $this->createSellerFromManualEntry($sellerInfo);
             $sellerInfo['seller_id'] = $sellerId; // ✅ Set it here
        }

        $orders = MakePurchaseOrder::whereIn('purchase_order_no', $purchaseOrderNos)->get();
        if ($orders->isEmpty()) {
            return redirect()->back()->with('error', 'No matching purchase orders found.');
        }

        // ✅ Ensure all POs belong to the same warehouse
        $uniqueWarehouses = $orders->pluck('warehouse_id')->unique();
        if ($uniqueWarehouses->count() > 1) {
            return redirect()->back()->with('error', 'All selected purchase orders must belong to the same warehouse.');
        }

        $updatedProductsPerPO = [];

        foreach ($orders as $order) {
            $isClosed = 1;
            foreach ($request->input('products') as $productData) {
                // ✅ Skip product if quantity is 0
                if (intval($productData['pending']) <= 0) {
                    continue;
                }
                $partNo = $productData['part_no'];
                $newQty = $productData['pending'];
                $hsncode = $productData['hsncode'];
                $purchasePrice = $productData['purchase_price'];
                $orderNo = $productData['order_no'];
                $selectedPO = $productData['purchase_order_no'];

                if ($selectedPO !== $order->purchase_order_no) continue;

                $originalProduct = PurchaseOrderDetail::where('make_purchase_order_id', $order->id)
                    ->where('part_no', $partNo)
                    ->where('purchase_order_no', $selectedPO)
                    ->first();

                if (!$originalProduct) continue;

                $received = $originalProduct->received + $newQty;
                $pending = max(0, $originalProduct->pending - $newQty);

                if ($pending > 0) $isClosed = 0;

                PurchaseOrderDetail::where('make_purchase_order_id', $order->id)
                    ->where('part_no', $partNo)
                    ->where('purchase_order_no', $selectedPO)
                    ->update([
                        'hsncode' => $hsncode,
                        'received' => $received,
                        'pending' => $pending,
                        'order_no' => $orderNo,
                        'updated_at' => now(),
                    ]);

                // new added product update    
                Product::where('part_no', $partNo)
                    ->update(['hsncode' => $hsncode]);

                $updatedProductsPerPO[$selectedPO][] = [
                    'part_no' => $partNo,
                    'qty' => $newQty,
                    'purchase_price' => $purchasePrice,
                    'order_no' => $orderNo,
                    'hsncode' => $hsncode,
                ];
            }

            MakePurchaseOrder::where('id', $order->id)->update([
                'is_closed' => $isClosed,
                'updated_at' => now(),
            ]);
        }

        $lastPurchase = PurchaseInvoice::orderBy('id', 'desc')->first();
        $newPurchaseNumber = $lastPurchase ? intval(substr($lastPurchase->purchase_no, 3)) + 1 : 1;
        $purchaseNo = 'pn-' . str_pad($newPurchaseNumber, 3, '0', STR_PAD_LEFT);

        $warehouseId = $orders->first()->warehouse_id ?? null; // ✅ Extract warehouse ID
        $purchaseInvoiceId = PurchaseInvoice::insertGetId([
            'purchase_no' => $purchaseNo,
            'purchase_order_no' => !empty($purchaseOrderNos) ? implode(',', $purchaseOrderNos) : null,
            'seller_invoice_no' => $request->input('seller_invoice_no'),
            'seller_id' => $sellerId,
            'warehouse_id'=>$warehouseId,
            'seller_invoice_date' => $request->input('seller_invoice_date'),
            'seller_info' => json_encode($sellerInfo, JSON_UNESCAPED_UNICODE),
            'purchase_invoice_type' => 'seller',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;

        $user_id=Warehouse::where('id',$warehouseId)->value('user_id');
        $companyState = User::where('id',$user_id)->value('state'); // Replace with your company's default warehouse state

        foreach ($updatedProductsPerPO as $poNo => $products) {
            foreach ($products as $product) {
                $gstPercent = 18; // You can fetch from DB if needed
                $qty = (float) $product['qty'];
                $rate = (float) $product['purchase_price'];
                $price = round($rate / (1 + ($gstPercent / 100)), 2);
                $grossAmt = round($price * $qty, 2);

                $cgst = 0;
                $sgst = 0;
                $igst = 0;

                $sellerState = strtoupper($sellerInfo['state_name']);

                if ($sellerState === strtoupper($companyState)) {
                    $cgst = round(($grossAmt * ($gstPercent / 2)) / 100, 2);
                    $sgst = round(($grossAmt * ($gstPercent / 2)) / 100, 2);
                    $totalCgst += $cgst;
                    $totalSgst += $sgst;
                } else {
                    $igst = round(($grossAmt * $gstPercent) / 100, 2);
                    $totalIgst += $igst;
                }

                PurchaseInvoiceDetail::create([
                    'purchase_invoice_id' => $purchaseInvoiceId,
                    'purchase_invoice_no' => $purchaseNo,
                    'purchase_order_no'   => $poNo,
                    'part_no'             => $product['part_no'],
                    'qty'                 => $qty,
                    'order_no'            => $product['order_no'],
                    'hsncode'             => $product['hsncode'],
                    'price'               => $price,
                    'gross_amt'           => $grossAmt,
                    'cgst'                => $cgst,
                    'sgst'                => $sgst,
                    'igst'                => $igst,
                    'tax'                 => $gstPercent,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $productID=Product::where('part_no',$product['part_no'])->value('id');
                $requestSubmit = new \Illuminate\Http\Request();   
                 $requestSubmit->merge([
                        'product_id' => $productID
                    ]);              
                $this->inventoryProductEntry($requestSubmit);
            }
        }

        // Update totals in purchase invoice
        PurchaseInvoice::where('id', $purchaseInvoiceId)->update([
            'total_cgst' => $totalCgst,
            'total_sgst' => $totalSgst,
            'total_igst' => $totalIgst,
        ]);

        $zoho = new ZohoController();
        $res= $zoho->createVendorBill($purchaseInvoiceId);

        return redirect()->route('admin.supplyOrderLising')->with('status', 'Purchase orders converted successfully!');
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
        return 0;
    }

 

    public function getSellerInfo($seller_name)
    {
        // Fetch the seller information based on the seller name from final_purchase_order
        $seller = DB::table('make_purchase_orders')
            ->select(DB::raw('JSON_UNQUOTE(JSON_EXTRACT(seller_info, "$.seller_name")) as seller_name'),
                     DB::raw('JSON_UNQUOTE(JSON_EXTRACT(seller_info, "$.seller_address")) as seller_address'),
                     DB::raw('JSON_UNQUOTE(JSON_EXTRACT(seller_info, "$.seller_gstin")) as seller_gstin'),
                     DB::raw('JSON_UNQUOTE(JSON_EXTRACT(seller_info, "$.seller_phone")) as seller_phone'))
            ->where(DB::raw('JSON_UNQUOTE(JSON_EXTRACT(seller_info, "$.seller_name"))'), $seller_name)
            ->first();

        // Return the seller information as a JSON response
        return response()->json($seller);
    }


public function manager41ManualConvertToPurchase(Request $request)
{
    $validatedData = $request->validate([
        'seller_info.seller_name'  => 'required|string',
        'seller_info.seller_phone' => 'required|string',
        'seller_info.state_name'   => 'required|string',
        'warehouse_id'             => 'required|integer',
        'orders.*.part_no'         => 'required|string',
        'orders.*.product_name'    => 'required|string',
        'orders.*.quantity'        => 'required|integer|min:1',
        'orders.*.purchase_price'  => 'required|numeric|min:0', // WITH GST coming from blade
    ]);

    $sellerInfo  = $request->input('seller_info', []);
    $warehouseId = (int) $request->input('warehouse_id');

    // ensure seller
    $sellerId = $sellerInfo['seller_id'] ?? null;
    if (empty($sellerId)) {
        $sellerId = $this->createSellerFromManualEntry($sellerInfo);
        $sellerInfo['seller_id'] = $sellerId;
    }

    // attachment
    $attachmentPath = null;
    if ($request->hasFile('attachment')) {
        $file = $request->file('attachment');
        $filename = time().'_'.$file->getClientOriginalName();
        $dest = public_path('purchase_invoice_attachment');
        if (!is_dir($dest)) @mkdir($dest, 0775, true);
        $file->move($dest, $filename);
        $attachmentPath = 'purchase_invoice_attachment/'.$filename;
    }

    // purchase number from Manager41 table
    $last = \App\Models\Manager41PurchaseInvoice::orderBy('id','desc')->first();
    $num  = $last ? intval(substr($last->purchase_no, 3)) + 1 : 1;
    $purchaseNo = 'pn-' . str_pad($num, 3, '0', STR_PAD_LEFT);

    // state logic for GST split
    $companyState = \App\Models\User::where('id', \App\Models\Warehouse::where('id', $warehouseId)->value('user_id'))->value('state');
    $sellerState  = strtoupper($sellerInfo['state_name'] ?? '');

    // header
    $invoiceId = \App\Models\Manager41PurchaseInvoice::insertGetId([
        'purchase_no'           => $purchaseNo,
        'purchase_order_no'     => 'Manual Entry',
        'seller_invoice_no'     => $request->input('seller_invoice_no'),
        'seller_invoice_date'   => $request->input('seller_invoice_date'),
        'warehouse_id'          => $warehouseId,
        'purchase_invoice_type' => 'seller',
        'seller_id'             => $sellerId,
        'seller_info'           => json_encode($sellerInfo),
        'invoice_attachment'    => $attachmentPath,
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);

    $totalCgst = 0; $totalSgst = 0; $totalIgst = 0;

    foreach ($request->input('orders', []) as $row) {
        $qty     = (float) $row['quantity'];
        $rateGst = (float) $row['purchase_price']; // WITH GST
        $partNo  = $row['part_no'];
        $hsncode = $row['hsncode'] ?? '';
        $selectedPO = $row['purchase_order_no'] ?? 'Manual Entry';

        $product = \App\Models\Product::where('part_no',$partNo)->first();
        $tax     = $product->tax ?? 0;              // percent
        $taxType = $product->tax_type ?? 'percent'; // 'percent' or 'fixed'

        // Convert WITH-GST rate → tax-exclusive price we store
        $price = $rateGst;
        if ($taxType === 'percent') {
            $price = round($rateGst / (1 + ($tax/100)), 2);
        } else {
            $price = max($rateGst - (float)$tax, 0);
        }

        $grossAmt = round($price * $qty, 2);

        // GST split like normal manual flow
        $cgst=0; $sgst=0; $igst=0;
        if (strtoupper($companyState) === $sellerState) {
            $cgst = round(($grossAmt * ($tax/2)) / 100, 2);
            $sgst = round(($grossAmt * ($tax/2)) / 100, 2);
            $totalCgst += $cgst; $totalSgst += $sgst;
        } else {
            $igst = round(($grossAmt * $tax) / 100, 2);
            $totalIgst += $igst;
        }

        \App\Models\Manager41PurchaseInvoiceDetail::create([
            'purchase_invoice_id' => $invoiceId,
            'purchase_invoice_no' => $purchaseNo,
            'purchase_order_no'   => $selectedPO,
            'part_no'             => $partNo,
            'qty'                 => $qty,
            'order_no'            => $selectedPO,
            'hsncode'             => $hsncode,
            'price'               => $price,     // tax-exclusive
            'gross_amt'           => $grossAmt,
            'cgst'                => $cgst,
            'sgst'                => $sgst,
            'igst'                => $igst,
            'tax'                 => $tax,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        if ($product) {
            $product->update(['hsncode' => $hsncode]);
            $req = new \Illuminate\Http\Request();
            $req->merge(['product_id' => $product->id]);
            $this->manager41InventoryProductEntry($req);
        }
    }

    \App\Models\Manager41PurchaseInvoice::where('id',$invoiceId)->update([
        'total_cgst' => $totalCgst,
        'total_sgst' => $totalSgst,
        'total_igst' => $totalIgst,
    ]);

    return redirect()->route('purchase.invoices.list')
        ->with('status','Manager-41: Manual Purchase converted successfully!');
}

   public function _manager41ManualConvertToPurchase(Request $request)
    {
        $validatedData = $request->validate([
            'seller_info.seller_name' => 'required|string',
            'seller_info.seller_phone'=> 'required|string',
            'seller_info.state_name'  => 'required|string',
            'warehouse_id'           => 'required|integer',
            'orders.*.part_no'        => 'required|string',
            'orders.*.product_name'   => 'required|string',
            'orders.*.quantity'       => 'required|integer|min:1',
            'orders.*.purchase_price' => 'required|numeric|min:0',
        ]);

        $sellerInfo  = $request->input('seller_info', []);
        $warehouseId = (int) $request->input('warehouse_id');

        // 1) ensure/resolve seller — THIS is where your seller function is used
        $sellerId = $sellerInfo['seller_id'] ?? null;
        if (empty($sellerId)) {
            // must return the new seller's ID
            $sellerId = $this->createSellerFromManualEntry($sellerInfo);
            $sellerInfo['seller_id'] = $sellerId; // keep it in the payload we store
        }

        // 2) optional attachment
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = time().'_'.$file->getClientOriginalName();
            $dest = public_path('purchase_invoice_attachment');
            if (!is_dir($dest)) {
                @mkdir($dest, 0775, true);
            }
            $file->move($dest, $filename);
            $attachmentPath = 'purchase_invoice_attachment/'.$filename;
        }

        // 3) generate purchase_no from Manager41PurchaseInvoice table
        $last = \App\Models\Manager41PurchaseInvoice::orderBy('id','desc')->first();
        $num  = $last ? intval(substr($last->purchase_no, 3)) + 1 : 1;
        $purchaseNo = 'pn-' . str_pad($num, 3, '0', STR_PAD_LEFT);

        // 4) create header — NOW INCLUDING seller_id
        $invoiceId = \App\Models\Manager41PurchaseInvoice::insertGetId([
            'purchase_no'           => $purchaseNo,
            'purchase_order_no'     => 'Manual Entry',
            'seller_invoice_no'     => $request->input('seller_invoice_no'),
            'seller_invoice_date'   => $request->input('seller_invoice_date'),
            'warehouse_id'          => $warehouseId,
            'purchase_invoice_type' => 'seller',
            'seller_id'             => $sellerId,                 // 👈 stored here
            'seller_info'           => json_encode($sellerInfo),  // contains seller_id too
            'invoice_attachment'    => $attachmentPath,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        $totalCgst = 0; $totalSgst = 0; $totalIgst = 0;

        // 5) details + inventory
        foreach ($request->input('orders', []) as $row) {
            $qty     = (float) $row['quantity'];
            $rate    = (float) $row['purchase_price'];
            $partNo  = $row['part_no'];
            $hsncode = $row['hsncode'] ?? '';

            $product = \App\Models\Product::where('part_no',$partNo)->first();
            $tax     = $product->tax ?? 0;
            $taxType = $product->tax_type ?? 'percent';

            $price   = ($taxType === 'percent') ? round($rate / (1 + ($tax/100)), 2)
                                                : max($rate - $tax, 0);
            $gross   = round($price * $qty, 2);

            // keep GST splits zero in this manager_41 variant (as you asked)
            $cgst = 0; $sgst = 0; $igst = 0;

            \App\Models\Manager41PurchaseInvoiceDetail::create([
                'purchase_invoice_id' => $invoiceId,
                'purchase_invoice_no' => $purchaseNo,
                'purchase_order_no'   => $row['purchase_order_no'] ?? 'Manual Entry',
                'part_no'             => $partNo,
                'qty'                 => $qty,
                'order_no'            => $row['purchase_order_no'] ?? 'Manual Entry',
                'hsncode'             => $hsncode,
                'price'               => $price,
                'gross_amt'           => $gross,
                'cgst'                => $cgst,
                'sgst'                => $sgst,
                'igst'                => $igst,
                'tax'                 => $tax,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            if ($product) {
                // sync HSN on product
                $product->update(['hsncode' => $hsncode]);

                // inventory update (manager41 variant)
                $req = new \Illuminate\Http\Request();
                $req->merge(['product_id' => $product->id]);
                $this->manager41InventoryProductEntry($req);
            }
        }

        // 6) header totals
        \App\Models\Manager41PurchaseInvoice::where('id',$invoiceId)->update([
            'total_cgst' => $totalCgst,
            'total_sgst' => $totalSgst,
            'total_igst' => $totalIgst,
        ]);

        return redirect()->route('purchase.invoices.list')
            ->with('status','Manager-41: Manual Purchase converted successfully!');
    }


    public function manualConvertToPurchase(Request $request)
    {

        if ($this->isActingAs41Manager()) {
            return $this->manager41ManualConvertToPurchase($request);
        }
        $validatedData = $request->validate([
            'seller_info.seller_name' => 'required|string',
            'seller_info.seller_phone' => 'required|string',
            'seller_info.state_name' => 'required|string',

            'seller_info.seller_address' => 'nullable|string',
            'seller_info.seller_gstin' => 'nullable|string',
         

            'orders.*.part_no' => 'required|string',
            'orders.*.product_name' => 'required|string',
            'orders.*.quantity' => 'required|integer|min:1',
            'orders.*.purchase_price' => 'required|numeric|min:0',
        ]);

        $sellerInfo = $request->input('seller_info');

        // ✅ File upload setup
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = time() . '_' . $file->getClientOriginalName();
            $destinationPath = public_path('purchase_invoice_attachment');
            $file->move($destinationPath, $filename);
            $attachmentPath = 'purchase_invoice_attachment/' . $filename;
        }

        $sellerId = $sellerInfo['seller_id'] ?? null;

        if (empty($sellerId)) {
            $sellerId = $this->createSellerFromManualEntry($sellerInfo);
             $sellerInfo['seller_id'] = $sellerId; // ✅ Set it here
        }
        
        $lastPurchase = PurchaseInvoice::orderBy('id', 'desc')->first();
        $newPurchaseNumber = $lastPurchase ? intval(substr($lastPurchase->purchase_no, 3)) + 1 : 1;
        $purchaseNo = 'pn-' . str_pad($newPurchaseNumber, 3, '0', STR_PAD_LEFT);

        $warehouseId = $request->input('warehouse_id');
        $companyState = User::where('id', Warehouse::where('id', $warehouseId)->value('user_id'))->value('state');
        $sellerState = strtoupper($sellerInfo['state_name']);

        $purchaseInvoiceId = PurchaseInvoice::insertGetId([
            'purchase_no' => $purchaseNo,
            'purchase_order_no' => 'Manual Entry',
            'seller_invoice_no' => $request->input('seller_invoice_no'),
            'seller_id' => $sellerId,
            'warehouse_id' => $warehouseId,
            'seller_invoice_date' => $request->input('seller_invoice_date'),
            'purchase_invoice_type' => 'seller',
            'seller_info' => json_encode($sellerInfo),
            'invoice_attachment' => $attachmentPath, // ✅ Save uploaded file path
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;

        foreach ($request->input('orders') as $product) {
            $qty = (float) $product['quantity'];
            $rate = (float) $product['purchase_price'];
            $partNo = $product['part_no'];
            $hsncode = $product['hsncode'] ?? '';
            $selectedPO = $product['purchase_order_no'] ?? null;

            // 🔍 Get tax & tax_type from product
            $productModel = Product::where('part_no', $partNo)->first();
            $tax = $productModel->tax ?? 0;
            $taxType = $productModel->tax_type ?? 'percent'; // percent or fixed

            // fallback for purchase_order_no
            if (!$selectedPO) {
                $selectedPO = 'Manual Entry';
                //continue;
            }

            // 🔍 Find the matching PurchaseOrderDetail
            $poDetail = PurchaseOrderDetail::where('part_no', $partNo)
                ->where('purchase_order_no', $selectedPO)
                ->orderBy('id', 'desc')
                ->first();
            $orderNo = $poDetail->order_no ?? 'Manual Entry';

            if ($poDetail) {

                $orderNo = $poDetail->order_no ?? 'Manual Entry';

                // Update PO received & pending
                $received = $poDetail->received + $qty;
                $pending = max(0, $poDetail->pending - $qty);
                PurchaseOrderDetail::where('id', $poDetail->id)->update([
                    'received' => $received,
                    'pending' => $pending,
                    'hsncode' => $hsncode,
                    'order_no' => $orderNo,
                    'updated_at' => now(),
                ]);
            }

            // ✅ Tax-exclusive price
            $price = $rate;
            if ($taxType === 'percent') {
                $price = round($rate / (1 + ($tax / 100)), 2);
            }

            $grossAmt = round($price * $qty, 2);

            $cgst = $sgst = $igst = 0;
            if ($sellerState === strtoupper($companyState)) {
                $cgst = $sgst = round(($grossAmt * ($tax / 2)) / 100, 2);
                $totalCgst += $cgst;
                $totalSgst += $sgst;
            } else {
                $igst = round(($grossAmt * $tax) / 100, 2);
                $totalIgst += $igst;
            }

            // Insert into PurchaseInvoiceDetail
            PurchaseInvoiceDetail::create([
                'purchase_invoice_id' => $purchaseInvoiceId,
                'purchase_invoice_no' => $purchaseNo,
                'purchase_order_no'   => $selectedPO,
                'part_no'             => $partNo,
                'qty'                 => $qty,
                'order_no'            => $orderNo,
                'hsncode'             => $hsncode,
                'price'               => $price,
                'gross_amt'           => $grossAmt,
                'cgst'                => $cgst,
                'sgst'                => $sgst,
                'igst'                => $igst,
                'tax'                 => $tax,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // ✅ Update product HSN if needed
            if ($productModel) {
                $productModel->update(['hsncode' => $hsncode]);
                $productModel->update(['purchase_price' => $rate]);
            }

            // ✅ Inventory entry
            $requestSubmit = new \Illuminate\Http\Request();
            $requestSubmit->merge(['product_id' => $productModel->id]);
            $this->inventoryProductEntry($requestSubmit);
        }

        // ✅ Update totals
        PurchaseInvoice::where('id', $purchaseInvoiceId)->update([
            'total_cgst' => $totalCgst,
            'total_sgst' => $totalSgst,
            'total_igst' => $totalIgst,
        ]);

        $zoho = new ZohoController();
        $res= $zoho->createVendorBill($purchaseInvoiceId);


        $updatedPOs = collect($request->input('orders'))->pluck('purchase_order_no')->unique()->filter();
        foreach ($updatedPOs as $poNo) {
            $makePurchaseOrderId = PurchaseOrderDetail::where('purchase_order_no', $poNo)->value('make_purchase_order_id');

            if ($makePurchaseOrderId) {
                $hasPending = PurchaseOrderDetail::where('make_purchase_order_id', $makePurchaseOrderId)
                    ->where('pending', '>', 0)
                    ->exists();

                MakePurchaseOrder::where('id', $makePurchaseOrderId)->update([
                    'is_closed' => $hasPending ? 0 : 1,
                    'updated_at' => now(),
                ]);
            }
        }

        // Send WhatsApp PDF after everything is done
        $this->sendPurchaseInvoicePdfOnWhatsApp($purchaseInvoiceId);

        return redirect()->route('purchase.invoices.list')->with('status', 'Manual Purchase converted successfully!');
    }



    //save manual purchase order customer  

    private function manager41InsertServiceData(Request $request)
{
    $warehouseId = (int) $request->input('warehouse_id');
    $addressId   = (int) $request->input('address_id');
    $note        = $request->input('note');
    $sacCode     = $request->input('sac_code');
    $rate        = (float) $request->input('rate');
    $quantity    = (int) $request->input('quantity');

    $warehouse = \App\Models\Warehouse::findOrFail($warehouseId);

    // credit note no from Manager41PurchaseInvoice
    $whCode = strtoupper(substr($warehouse->name, 0, 3));
    $lastCN = \App\Models\Manager41PurchaseInvoice::where('credit_note_number','LIKE',$whCode.'/CN/%')
              ->orderBy('id','desc')->value('credit_note_number');
    $next3  = $lastCN ? str_pad(intval(substr($lastCN,-3)) + 1, 3, '0', STR_PAD_LEFT) : '001';
    $creditNoteNumber = $whCode . '/CN/' . $next3;

    // purchase_no
    $last = \App\Models\Manager41PurchaseInvoice::orderBy('id','desc')->first();
    $num  = $last ? intval(substr($last->purchase_no, 3)) + 1 : 1;
    $purchaseNo = 'pn-' . str_pad($num, 3, '0', STR_PAD_LEFT);

    $invoiceId = \App\Models\Manager41PurchaseInvoice::insertGetId([
        'purchase_no'          => $purchaseNo,
        'purchase_order_no'    => 'Service Entry',
        'seller_invoice_no'    => $request->input('seller_invoice_no'),
        'seller_invoice_date'  => $request->input('seller_invoice_date'),
        'addresses_id'         => $addressId,
        'warehouse_id'         => $warehouseId,
        'purchase_invoice_type'=> 'customer',
        'credit_note_number'   => $creditNoteNumber,
        'created_at'           => now(),
        'updated_at'           => now(),
    ]);

    // tax calc @18%
    $gstRate = 18;
    $priceExGST  = round($rate / (1 + ($gstRate/100)), 2);
    $grossAmount = round($priceExGST * $quantity, 2);
    $cgst = round($grossAmount * 0.09, 2);
    $sgst = round($grossAmount * 0.09, 2);
    $igst = 0;

    \App\Models\Manager41PurchaseInvoiceDetail::create([
        'purchase_invoice_id' => $invoiceId,
        'purchase_invoice_no' => $purchaseNo,
        'purchase_order_no'   => 'Service Entry',
        'part_no'             => $note,
        'hsncode'             => $sacCode,
        'qty'                 => $quantity,
        'price'               => $priceExGST,
        'gross_amt'           => $grossAmount,
        'cgst'                => $cgst,
        'sgst'                => $sgst,
        'igst'                => $igst,
        'tax'                 => 18,
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    \App\Models\Manager41PurchaseInvoice::where('id',$invoiceId)->update([
        'total_cgst' => $cgst,
        'total_sgst' => $sgst,
        'total_igst' => $igst,
    ]);

    return redirect()->route('purchase.invoices.list')
        ->with('status', 'Manager-41: Service Entry created with Credit Note: '.$creditNoteNumber);
}


    public function saveManualPurchaseOrderCustomerFoReward(Request $request)
    {

        $validatedData = $request->validate([
            'warehouse_id' => 'required|integer',
            'address_id' => 'required|integer',
            'orders.*.part_no' => 'required|string',
            'orders.*.product_name' => 'required|string',
            'orders.*.quantity' => 'required|integer|min:1',
            'orders.*.purchase_price' => 'required|numeric|min:0',
        ]);

        // service wala part start
        $creditNoteType = $request->input('credit_note_type');

        if ($creditNoteType === 'service') {
             //return "Work is underway.";
            $creditNoteNumber=$this->insertServiceDataForReward($request);
        } 
        return $creditNoteNumber;
    }

    private function insertServiceDataForReward(Request $request)
    {

       
        $warehouseId = $request->input('warehouse_id');
        $addressId = $request->input('address_id');
        $note = $request->input('note');
        $sacCode = $request->input('sac_code');
        $rate = (float) $request->input('rate');
        $quantity = (int) $request->input('quantity');

        $address = Address::with('state')->findOrFail($addressId);
        $customerState = strtoupper($address->state->name ?? '');

        $warehouse = Warehouse::findOrFail($warehouseId);
        $companyState = strtoupper(User::where('id', $warehouse->user_id)->value('state'));
        $warehouseCode = strtoupper(substr($warehouse->name, 0, 3));  // First 3 letters of the warehouse name

        // Generate Credit Note Number
        $lastCreditNote = PurchaseInvoice::where('credit_note_number', 'LIKE', $warehouseCode . '/CN/%')
            ->orderBy('id', 'desc')
            ->value('credit_note_number');

        if ($lastCreditNote) {
            $lastNumber = intval(substr($lastCreditNote, -3)); // Extract last 3 digits
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        $creditNoteNumber = $warehouseCode . '/CN/' . $newNumber;

        // Generate Purchase Invoice Number
        $lastPurchase = PurchaseInvoice::orderBy('id', 'desc')->first();
        $newPurchaseNumber = $lastPurchase ? intval(substr($lastPurchase->purchase_no, 3)) + 1 : 1;
        $purchaseNo = 'pn-' . str_pad($newPurchaseNumber, 3, '0', STR_PAD_LEFT);

        // Insert Data in PurchaseInvoice
        $purchaseInvoiceId = PurchaseInvoice::insertGetId([
            'purchase_no' => $purchaseNo,
            'purchase_order_no' => 'Reward Entry',
            'seller_invoice_no' => $creditNoteNumber,
            'seller_invoice_date' => now(),
            'addresses_id' => $addressId,
            'warehouse_id' => $warehouseId,
            'purchase_invoice_type' => 'customer',
            'credit_note_number' => $creditNoteNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Calculate Tax

        $gstRate = 18; // 18% GST
        $priceExcludingGST = round($rate / (1 + ($gstRate / 100)), 2);
        
        $grossAmount = round($priceExcludingGST * $quantity, 2);
        $cgst = $sgst = $igst = 0;

        if ($customerState === $companyState) {
            $cgst = round($grossAmount * 0.09, 2);
            $sgst = round($grossAmount * 0.09, 2);
        } else {
            $igst = round($grossAmount * 0.18, 2);
        }

        // Insert Data in PurchaseInvoiceDetails
        PurchaseInvoiceDetail::create([
            'purchase_invoice_id' => $purchaseInvoiceId,
            'purchase_invoice_no' => $purchaseNo,
            'purchase_order_no' => 'Reward Entry',
            'part_no' => $note,
            'hsncode' => $sacCode,
            'qty' => $quantity,
            'price' => $priceExcludingGST,
            'gross_amt' => $grossAmount,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => $igst,
            'tax' => 18,  // Assuming GST is 18%
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update Tax Totals in PurchaseInvoice
        PurchaseInvoice::where('id', $purchaseInvoiceId)->update([
            'total_cgst' => $cgst,
            'total_sgst' => $sgst,
            'total_igst' => $igst,
        ]);



        // 🔹 INSERT into reward_points_of_users (as requested) — BEFORE Zoho
        try {
            RewardPointsOfUser::create([
                'party_code'             => $address->acc_code,     // party code
                'invoice_no'             => $creditNoteNumber,      // credit note number
                'rewards_from'           => 'Credit Note',          // source label
                'rewards'           => $rate,          // source label
                'warehouse_id'           => $warehouseId,
                'warehouse_name'         => $warehouse->name,
                'dr_or_cr'               => 'cr',                   // always Cr
                'is_processed'           => 1,                      // processed
                'reward_complete_status' => 1,                      // claimed/completed
                // OPTIONAL (uncomment if you want to store amounts/dates/notes):
                // 'credit_rewards'      => $rate,                  // credited reward amount
                // 'voucher_date'        => now()->toDateString(),
                 'notes'               => 'Auto-created on Reward Entry',
            ]);
        } catch (\Throwable $e) {
            \Log::error('RewardPointsOfUser insert failed: ' . $e->getMessage());
            // continue flow even if this insert fails
        }

        // 🔁 Push to Zoho after creating service invoice
        try {
            $zohoController = new ZohoController();
            $res= $zohoController->createZohoServiceCreditNote($purchaseInvoiceId);
        } catch (\Exception $e) {
            \Log::error('Zoho service credit note error: ' . $e->getMessage());
        }


        // return redirect()->route('purchase.credit.note.list')->with('status', 'Service Entry created successfully with Credit Note: ' . $creditNoteNumber);
        return $creditNoteNumber;
    }
    private function insertServiceData(Request $request)
    {

         if ($this->isActingAs41Manager()) {
            return $this->manager41InsertServiceData($request);
        }
        $warehouseId = $request->input('warehouse_id');
        $addressId = $request->input('address_id');
        $note = $request->input('note');
        $sacCode = $request->input('sac_code');
        $rate = (float) $request->input('rate');
        $quantity = (int) $request->input('quantity');

        $address = Address::with('state')->findOrFail($addressId);
        $customerState = strtoupper($address->state->name ?? '');

        $warehouse = Warehouse::findOrFail($warehouseId);
        $companyState = strtoupper(User::where('id', $warehouse->user_id)->value('state'));
        $warehouseCode = strtoupper(substr($warehouse->name, 0, 3));  // First 3 letters of the warehouse name

        // Generate Credit Note Number
        $lastCreditNote = PurchaseInvoice::where('credit_note_number', 'LIKE', $warehouseCode . '/CN/%')
            ->orderBy('id', 'desc')
            ->value('credit_note_number');

        if ($lastCreditNote) {
            $lastNumber = intval(substr($lastCreditNote, -3)); // Extract last 3 digits
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        $creditNoteNumber = $warehouseCode . '/CN/' . $newNumber;

        // Generate Purchase Invoice Number
        $lastPurchase = PurchaseInvoice::orderBy('id', 'desc')->first();
        $newPurchaseNumber = $lastPurchase ? intval(substr($lastPurchase->purchase_no, 3)) + 1 : 1;
        $purchaseNo = 'pn-' . str_pad($newPurchaseNumber, 3, '0', STR_PAD_LEFT);

        // Insert Data in PurchaseInvoice
        $purchaseInvoiceId = PurchaseInvoice::insertGetId([
            'purchase_no' => $purchaseNo,
            'purchase_order_no' => 'Service Entry',
            'seller_invoice_no' => $request->input('seller_invoice_no'),
            'seller_invoice_date' => $request->input('seller_invoice_date'),
            'addresses_id' => $addressId,
            'warehouse_id' => $warehouseId,
            'purchase_invoice_type' => 'customer',
            'credit_note_number' => $creditNoteNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Calculate Tax

        $gstRate = 18; // 18% GST
        $priceExcludingGST = round($rate / (1 + ($gstRate / 100)), 2);
        
        $grossAmount = round($priceExcludingGST * $quantity, 2);
        $cgst = $sgst = $igst = 0;

        if ($customerState === $companyState) {
            $cgst = round($grossAmount * 0.09, 2);
            $sgst = round($grossAmount * 0.09, 2);
        } else {
            $igst = round($grossAmount * 0.18, 2);
        }

        // Insert Data in PurchaseInvoiceDetails
        PurchaseInvoiceDetail::create([
            'purchase_invoice_id' => $purchaseInvoiceId,
            'purchase_invoice_no' => $purchaseNo,
            'purchase_order_no' => 'Service Entry',
            'part_no' => $note,
            'hsncode' => $sacCode,
            'qty' => $quantity,
            'price' => $priceExcludingGST,
            'gross_amt' => $grossAmount,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => $igst,
            'tax' => 18,  // Assuming GST is 18%
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update Tax Totals in PurchaseInvoice
        PurchaseInvoice::where('id', $purchaseInvoiceId)->update([
            'total_cgst' => $cgst,
            'total_sgst' => $sgst,
            'total_igst' => $igst,
        ]);

        // 🔁 Push to Zoho after creating service invoice
        try {
            $zohoController = new ZohoController();
           $res= $zohoController->createZohoServiceCreditNote($purchaseInvoiceId);
        } catch (\Exception $e) {
            \Log::error('Zoho service credit note error: ' . $e->getMessage());
        }


        return redirect()->route('purchase.credit.note.list')->with('status', 'Service Entry created successfully with Credit Note: ' . $creditNoteNumber);
    }


    public function manager41SaveManualPurchaseOrderCustomer(Request $request)
    {
        $validatedData = $request->validate([
            'warehouse_id'            => 'required|integer',
            'address_id'              => 'required|integer',
            'orders.*.part_no'        => 'required|string',
            'orders.*.product_name'   => 'required|string',
            'orders.*.quantity'       => 'required|integer|min:1',
            'orders.*.purchase_price' => 'required|numeric|min:0', // WITH GST
        ]);

        // Service branch stays same
        if ($request->input('credit_note_type') === 'service') {
            return $this->manager41InsertServiceData($request);
        }

        $address     = \App\Models\Address::with('state')->findOrFail($request->input('address_id'));
        $warehouseId = (int) $request->input('warehouse_id');
        $warehouse   = \App\Models\Warehouse::findOrFail($warehouseId);

        // Generate purchase_no
        $last = \App\Models\Manager41PurchaseInvoice::orderBy('id','desc')->first();
        $num  = $last ? intval(substr($last->purchase_no, 3)) + 1 : 1;
        $purchaseNo = 'pn-' . str_pad($num, 3, '0', STR_PAD_LEFT);

        // Credit Note no. by warehouse prefix
        $whCode = strtoupper(substr($warehouse->name, 0, 3));
        $lastCN = \App\Models\Manager41PurchaseInvoice::where('credit_note_number','LIKE',$whCode.'/CN/%')
                  ->orderBy('id','desc')->value('credit_note_number');
        $next3  = $lastCN ? str_pad(intval(substr($lastCN, -3)) + 1, 3, '0', STR_PAD_LEFT) : '001';
        $creditNoteNumber = $whCode . '/CN/' . $next3;

        // Company & Customer state for GST split
        $companyState  = strtoupper(\App\Models\User::where('id', \App\Models\Warehouse::where('id', $warehouseId)->value('user_id'))->value('state'));
        $customerState = strtoupper($address->state->name ?? $address->state_name ?? $address->state ?? '');

        $invoiceId = \App\Models\Manager41PurchaseInvoice::insertGetId([
            'purchase_no'           => $purchaseNo,
            'purchase_order_no'     => 'Goods Return',
            'seller_invoice_no'     => $request->input('seller_invoice_no'),
            'seller_invoice_date'   => $request->input('seller_invoice_date'),
            'addresses_id'          => $request->input('address_id'),
            'purchase_invoice_type' => 'customer',
            'credit_note_number'    => $creditNoteNumber,
            'warehouse_id'          => $warehouseId,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        $totalCgst = 0; $totalSgst = 0; $totalIgst = 0;

        foreach ($request->input('orders') as $prod) {
            $qty      = (float) $prod['quantity'];
            $rateGst  = (float) $prod['purchase_price'];     // WITH GST (from blade)
            $partNo   = $prod['part_no'];
            $hsncode  = $prod['hsncode'] ?? '';
            $selectedPO = 'Goods Return';

            // Product tax metadata
            $product  = \App\Models\Product::where('part_no', $partNo)->first();
            $tax      = $product->tax ?? 18;                 // % — fallback to 18 if missing
            $taxType  = $product->tax_type ?? 'percent';     // 'percent' or 'fixed'

            // Convert WITH-GST rate -> tax-exclusive price to store
            if ($taxType === 'percent') {
                $price = round($rateGst / (1 + ($tax / 100)), 2);
            } else {
                // treat fixed tax as amount included in given rate
                $price = max($rateGst - (float)$tax, 0);
            }

            $gross = round($price * $qty, 2);

            // GST split based on state
            $cgst = 0; $sgst = 0; $igst = 0;
            if ($companyState === $customerState) {
                // Intra-state: CGST + SGST
                $cgst = round(($gross * ($tax / 2)) / 100, 2);
                $sgst = round(($gross * ($tax / 2)) / 100, 2);
                $totalCgst += $cgst; $totalSgst += $sgst;
            } else {
                // Inter-state: IGST
                $igst = round(($gross * $tax) / 100, 2);
                $totalIgst += $igst;
            }

            \App\Models\Manager41PurchaseInvoiceDetail::create([
                'purchase_invoice_id' => $invoiceId,
                'purchase_invoice_no' => $purchaseNo,
                'purchase_order_no'   => $selectedPO,
                'part_no'             => $partNo,
                'qty'                 => $qty,
                'order_no'            => $selectedPO,
                'hsncode'             => $hsncode,
                'price'               => $price,     // tax-exclusive
                'gross_amt'           => $gross,
                'cgst'                => $cgst,
                'sgst'                => $sgst,
                'igst'                => $igst,
                'tax'                 => $tax,       // percent stored for reference
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // Update Product HSN & inventory (stock comes back on return)
            $productId = $product->id ?? \App\Models\Product::where('part_no',$partNo)->value('id');
            if ($productId) {
                \App\Models\Product::where('id', $productId)->update(['hsncode' => $hsncode]);

                $req = new \Illuminate\Http\Request();
                $req->merge(['product_id' => $productId]);
                $this->manager41InventoryProductEntry($req);
            }
        }

        \App\Models\Manager41PurchaseInvoice::where('id', $invoiceId)->update([
            'total_cgst' => $totalCgst,
            'total_sgst' => $totalSgst,
            'total_igst' => $totalIgst,
        ]);

        return redirect()->route('purchase.invoices.list')
            ->with('status', 'Manager-41: Customer Purchase Invoice created successfully!');
    }



    public function saveManualPurchaseOrderCustomer(Request $request)
    {

        if ($this->isActingAs41Manager()) {
            return $this->manager41SaveManualPurchaseOrderCustomer($request);
        }
        $validatedData = $request->validate([
            'warehouse_id' => 'required|integer',
            'address_id' => 'required|integer',
            'orders.*.part_no' => 'required|string',
            'orders.*.product_name' => 'required|string',
            'orders.*.quantity' => 'required|integer|min:1',
            'orders.*.purchase_price' => 'required|numeric|min:0',
        ]);

        // service wala part start
        $creditNoteType = $request->input('credit_note_type');

        if ($creditNoteType === 'service') {
             //return "Work is underway.";
            return $this->insertServiceData($request);
        } 
        // service wala part end

        $address = Address::with('state')->findOrFail($request->input('address_id'));
        $customerState = strtoupper($address->state->name ?? '');
        $warehouseId = $request->input('warehouse_id');
        $companyState = strtoupper(User::where('id', Warehouse::where('id', $warehouseId)->value('user_id'))->value('state'));

        $lastPurchase = PurchaseInvoice::orderBy('id', 'desc')->first();
        $newPurchaseNumber = $lastPurchase ? intval(substr($lastPurchase->purchase_no, 3)) + 1 : 1;
        $purchaseNo = 'pn-' . str_pad($newPurchaseNumber, 3, '0', STR_PAD_LEFT);

        // credit not number generation code start
         $warehouse = Warehouse::findOrFail($request->input('warehouse_id'));
         $warehouseCode = strtoupper(substr($warehouse->name, 0, 3));  // First 3 letters of the warehouse name

        // ✅ Generate Credit Note Number
         $lastCreditNote = PurchaseInvoice::where('credit_note_number', 'LIKE', $warehouseCode . '/CN/%')
            ->orderBy('id', 'desc')
            ->value('credit_note_number');

         if ($lastCreditNote) {
            $lastNumber = intval(substr($lastCreditNote, -3)); // Extract last 3 digits
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
         } else {
            $newNumber = '001';
         }

        $creditNoteNumber = $warehouseCode . '/CN/' . $newNumber;
        // credit note number geneeration code end

        $purchaseInvoiceId = PurchaseInvoice::insertGetId([
            'purchase_no' => $purchaseNo,
            'purchase_order_no' => 'Goods Return',
            'seller_invoice_no' => $request->input('seller_invoice_no'),
            'seller_invoice_date' => $request->input('seller_invoice_date'),
            'addresses_id' => $request->input('address_id'),
            'purchase_invoice_type' => 'customer',
            'credit_note_number' => $creditNoteNumber,
            'warehouse_id' => $warehouseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $totalCgst = $totalSgst = $totalIgst = 0;

        foreach ($request->input('orders') as $product) {
            $qty = (float) $product['quantity'];
            $rate = (float) $product['purchase_price'];
            $hsncode = $product['hsncode'] ?? '';
            $price = round($rate / 1.18, 2);
            $grossAmt = round($price * $qty, 2);

            $cgst = $sgst = $igst = 0;

            if ($customerState === $companyState) {
                $cgst = $sgst = round($grossAmt * 0.09, 2);
                $totalCgst += $cgst;
                $totalSgst += $sgst;
            } else {
                $igst = round($grossAmt * 0.18, 2);
                $totalIgst += $igst;
            }

            PurchaseInvoiceDetail::create([
                'purchase_invoice_id' => $purchaseInvoiceId,
                'purchase_invoice_no' => $purchaseNo,
                'purchase_order_no' => 'Goods Return',
                'part_no' => $product['part_no'],
                'qty' => $qty,
                'order_no' => 'Goods Return',
                'hsncode' => $hsncode,
                'price' => $price,
                'gross_amt' => $grossAmt,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'tax' => 18,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productID = Product::where('part_no', $product['part_no'])->value('id');
            if ($productID) {
                Product::where('id', $productID)->update(['hsncode' => $hsncode]);

                $requestSubmit = new \Illuminate\Http\Request();
                $requestSubmit->merge(['product_id' => $productID]);
                $this->inventoryProductEntry($requestSubmit);
            }
        }

        PurchaseInvoice::where('id', $purchaseInvoiceId)->update([
            'total_cgst' => $totalCgst,
            'total_sgst' => $totalSgst,
            'total_igst' => $totalIgst,
        ]);

        // ✅ Call ZohoController and trigger the credit note creation
        try {
            $zohoController = new ZohoController();
            $zohoController->createZohoCreditNote($purchaseInvoiceId);
        } catch (\Exception $e) {
            \Log::error('Error creating Zoho Credit Note: ' . $e->getMessage());
        }


        return redirect()->route('purchase.credit.note.list')->with('status', 'Customer Purchase Invoice created successfully!');
    }


    public function manager41SaveManualPurchaseOrder(Request $request)
    {
        // Customer branch -> reuse the manager41 customer handler
        $partyType = $request->input('party_type'); // 'seller' or 'customer'
        $action    = $request->input('action');

        if ($partyType === 'customer' && $action === 'convert') {
            return $this->manager41SaveManualPurchaseOrderCustomer($request);
        }

        // Seller branch (no PO/Zoho)
        $validatedData = $request->validate([
            'warehouse_id'                 => 'required|integer',
            'seller_info.seller_name'      => 'required|string|max:255',
            'seller_info.seller_address'   => 'nullable|string',
            'seller_info.seller_gstin'     => 'nullable|string',
            'seller_info.seller_phone'     => 'required|string|max:15',
            'orders.*.product_id'          => 'required|integer',
            'orders.*.part_no'             => 'required|string',
            'orders.*.product_name'        => 'required|string',
            'orders.*.purchase_price'      => 'required|numeric|min:0',
            'orders.*.quantity'            => 'required|integer|min:1',
        ]);

        // If “Convert” button used -> call manager41 convert
        if ($action === 'convert') {
            return $this->manager41ManualConvertToPurchase($request);
        }

        $sellerInfo  = $request->input('seller_info', []);
        $warehouseId = (int) $request->input('warehouse_id');

        // Generate purchase_no (pn-###) from Manager41PurchaseInvoice
        $last = \App\Models\Manager41PurchaseInvoice::orderBy('id','desc')->first();
        $num  = $last ? intval(substr($last->purchase_no, 3)) + 1 : 1;
        $purchaseNo = 'pn-' . str_pad($num, 3, '0', STR_PAD_LEFT);

        // Create invoice header
        $invoiceId = \App\Models\Manager41PurchaseInvoice::insertGetId([
            'purchase_no'         => $purchaseNo,
            'purchase_order_no'   => 'Manual Entry',
            'seller_invoice_no'   => $request->input('seller_invoice_no'),
            'seller_invoice_date' => $request->input('seller_invoice_date'),
            'purchase_invoice_type' => 'seller',
            'warehouse_id'        => $warehouseId,
            'seller_info'         => json_encode($sellerInfo),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $totalCgst = 0; $totalSgst = 0; $totalIgst = 0;

        // Insert lines
        foreach ($request->input('orders', []) as $row) {
            $qty     = (float) $row['quantity'];
            $rate    = (float) $row['purchase_price'];
            $partNo  = $row['part_no'];
            $hsncode = $row['hsncode'] ?? '';

            $productModel = \App\Models\Product::where('part_no', $partNo)->first();
            $tax      = $productModel->tax ?? 0;
            $taxType  = $productModel->tax_type ?? 'percent';

            // tax-exclusive unit price
            $price    = ($taxType === 'percent') ? round($rate / (1 + ($tax/100)), 2) : max($rate - $tax, 0);
            $grossAmt = round($price * $qty, 2);

            // intra/inter based only on seller vs company state (optional). Keep 0s if unknown.
            $cgst = 0; $sgst = 0; $igst = 0;

            \App\Models\Manager41PurchaseInvoiceDetail::create([
                'purchase_invoice_id' => $invoiceId,
                'purchase_invoice_no' => $purchaseNo,
                'purchase_order_no'   => 'Manual Entry',
                'part_no'             => $partNo,
                'qty'                 => $qty,
                'order_no'            => 'Manual Entry',
                'hsncode'             => $hsncode,
                'price'               => $price,
                'gross_amt'           => $grossAmt,
                'cgst'                => $cgst,
                'sgst'                => $sgst,
                'igst'                => $igst,
                'tax'                 => $tax,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // update product HSN (optional)
            if ($productModel) {
                $productModel->update(['hsncode' => $hsncode]);
                $req = new \Illuminate\Http\Request();
                $req->merge(['product_id' => $productModel->id]);
                $this->manager41InventoryProductEntry($req);
            }
        }

        // Save totals (all 0 here unless you later compute)
        \App\Models\Manager41PurchaseInvoice::where('id', $invoiceId)->update([
            'total_cgst' => $totalCgst,
            'total_sgst' => $totalSgst,
            'total_igst' => $totalIgst,
        ]);

        return redirect()->route('purchase.invoices.list')
            ->with('status', 'Manager-41: Manual purchase saved successfully!');
    }

   public function saveManualPurchaseOrder(Request $request)
    {

        if ($this->isActingAs41Manager()) {
            return $this->manager41SaveManualPurchaseOrder($request);
        }

        $partyType = $request->input('party_type'); // 'seller' or 'customer'
        $action = $request->input('action');
        if ($partyType === 'customer' && $action === 'convert') {
           // echo  "Sorry for Inconvinience - Working on it";
           //  return;
            return $this->saveManualPurchaseOrderCustomer($request);
            // Handle customer logic
        } else {
            // Handle seller logic
        
            // Validation
            $validatedData = $request->validate([
                'warehouse_id' => 'required|integer',
                'seller_info.seller_name' => 'required|string|max:255',
                'seller_info.seller_address' => 'nullable|string',
                'seller_info.seller_gstin' => 'nullable|string',
                'seller_info.seller_phone' => 'required|string|max:15',
                'orders.*.product_id' => 'required|integer',
                'orders.*.part_no' => 'required|string',
                'orders.*.product_name' => 'required|string',
                'orders.*.purchase_price' => 'required|numeric|min:0',
                'orders.*.quantity' => 'required|integer|min:1',
            ]);

            $sellerInfo = $request->input('seller_info');
            $sellerId = $sellerInfo['seller_id'] ?? null;

            //convert to purchse code start 
             $action = $request->input('action');

              if ($action === 'convert') {
                return $this->manualConvertToPurchase($request); // 👉 Call the convert method
              }

            // convert to purchase code end

            // If no seller_id selected, create new seller
            if (empty($sellerId)) {
                try {
                    $sellerId = $this->createSellerFromManualEntry($sellerInfo);
                    $sellerInfo['seller_id'] = $sellerId; // ✅ Set it here
                } catch (\Exception $e) {

                    return redirect()->back()->with('error', 'Seller creation failed: ' . $e->getMessage());
                }
            }

            // Generate new PO number
            $lastOrder = MakePurchaseOrder::orderBy('id', 'desc')->first();
            $newOrderNumber = $lastOrder ? intval(substr($lastOrder->purchase_order_no, 3)) + 1 : 1;
            $purchaseOrderNo = 'po-' . str_pad($newOrderNumber, 3, '0', STR_PAD_LEFT);

            $products = $request->input('orders', []);
            $productDetails = [];

            foreach ($products as $product) {

                $hsncode = $product['hsncode'] ?? null;
                Product::where('id', $product['product_id'])->update([
                    'purchase_price' => $product['purchase_price'],
                    'hsncode' => $hsncode
                ]);

                //$hsncode = Product::where('id', $product['product_id'])->value('hsncode');

                $productDetails[] = [
                    'product_id' => $product['product_id'],
                    'part_no' => $product['part_no'],
                    'product_name' => $product['product_name'],
                    'purchase_price' => $product['purchase_price'],
                    'qty' => $product['quantity'],
                    'hsncode' => $hsncode
                ];
            }

            // Insert Purchase Order
            try {
                $newOrder = MakePurchaseOrder::create([
                    'purchase_order_no' => $purchaseOrderNo,
                    'order_no' => 'Manual Entry',
                    'date' => now()->format('Y-m-d'),
                    'seller_id' => $sellerId,
                    'product_invoice' => json_encode($productDetails),
                    'seller_info' => json_encode($sellerInfo),
                    'warehouse_id' => $request->input('warehouse_id')  // ✅ add this
                ]);

                foreach ($productDetails as $product) {
                    $itemDetails = Product::where('part_no', $product['part_no'])->first(); // fetch tax
                    PurchaseOrderDetail::create([
                        'make_purchase_order_id' => $newOrder->id,
                        'purchase_order_no' => $purchaseOrderNo,
                        'part_no' => $product['part_no'],
                        'qty' => $product['qty'],
                        'order_no' => 'Manual Entry',
                        'age' => '-',
                        'hsncode' => $product['hsncode'],
                        'received' => 0,
                        'pre_close' => 0,
                        'pending' => $product['qty'],
                        'seller_info' => json_encode($sellerInfo),
                       'tax' => $itemDetails->tax ?? 0, // ✅ add this
                    ]);
                }

                return redirect()->route('admin.supplyOrderLising')->with('status', 'Manual purchase order saved successfully!');

            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Error saving purchase order: ' . $e->getMessage());
            }
        }
    }



    public function searchByPartNo(Request $request)
    {
        $searchBy = $request->input('search_by'); // 'part_no' or 'name'
        $searchValue = $request->input('search_value');
        $sellerId = $request->input('seller_id');

        if (!$searchValue) {
            return response()->json([]);
        }

        $query = Product::select('id', 'part_no', 'name', 'purchase_price', 'hsncode', 'tax')
                        ->where('published', 1);

        // CASE 1: Search by part_no
        if ($searchBy == 'part_no') {
            $product = $query->where('part_no', 'LIKE', $searchValue . '%')->limit(1)->first();

            if ($product && $sellerId) {
                $poList = PurchaseOrderDetail::where('part_no', $product->part_no)
                    ->whereHas('makePurchaseOrder', function ($q) use ($sellerId) {
                        $q->where('seller_id', $sellerId)
                          ->where('is_closed', '!=', 1)
                          ->where('force_closed', '!=', 1);
                    })
                    ->with('makePurchaseOrder:id,purchase_order_no,is_closed,force_closed,created_at') // ✅ add created_at
                    ->get()
                    ->map(function ($detail) {
                        return [
                            'po' => $detail->makePurchaseOrder->purchase_order_no,
                            'pending' => $detail->pending,
                            'date' => optional($detail->makePurchaseOrder->created_at)->format('d-m-Y'), // ✅ formatted
                        ];
                    })
                    ->unique('po')
                    ->values();

                $product->po_list = $poList;
            }

            return response()->json($product);
        }

        // CASE 2: Search by name
        if ($searchBy == 'name') {
            $products = $query->where('name', 'LIKE', '%' . $searchValue . '%')->get();
            return response()->json($products);
        }

        return response()->json([]);
    }


    private function backup_createSellerFromManualEntry($sellerInfo)
    {
        DB::beginTransaction();

        try {
            \Log::info('Creating new seller with info:', $sellerInfo);

            // 1. Insert into users
            $userId = DB::table('users')->insertGetId([
                'name' => $sellerInfo['seller_name'],
                'user_type' => 'seller',
                'state' => $sellerInfo['state_name'], // ✅ must be passed
                'email' => uniqid('seller_') . '@dummy.com',
                'phone' => $sellerInfo['seller_phone'],
                'password' => Hash::make('defaultpassword'),
                'warehouse_id'=>1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            \Log::info('User created: ' . $userId);

            // 2. Insert into sellers
            $sellerId = DB::table('sellers')->insertGetId([
                'user_id' => $userId,
                'verification_status' => 1,
                'gstin' => $sellerInfo['seller_gstin'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            \Log::info('Seller created: ' . $sellerId);

            // 3. Insert into shops
            DB::table('shops')->insert([
                'seller_id' => $sellerId,
                'name' => $sellerInfo['seller_name'],
                'address' => $sellerInfo['seller_address'] ?? '',
                'phone' => $sellerInfo['seller_phone'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            \Log::info('Shop created for seller: ' . $sellerId);

            // Seller Creation In Zoho 
            $zoho = new ZohoController();
            $res= $zoho->createNewSellerInZoho($userId);

            DB::commit();
            return $sellerId;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Seller creation failed: ' . $e->getMessage());
            throw new \Exception("Seller creation failed: " . $e->getMessage());
        }
    }

    // create seller with customer start
    private function createSellerFromManualEntry(array $sellerInfo)
    {
        DB::beginTransaction();

        try {
            \Log::info('Creating new seller with info:', $sellerInfo);

            // 1) User (dummy email via model)
            $user = new User();
            $user->name         = $sellerInfo['seller_name'];
            $user->user_type    = 'seller';
            $user->state        = $sellerInfo['state_name'];          // string state
            $user->email        = uniqid('seller_') . '@dummy.com';   // dummy unique email
            $user->phone        = $sellerInfo['seller_phone'];
            $user->password     = Hash::make('defaultpassword');
            $user->warehouse_id = 1;
            $user->save();

            \Log::info('User created: '.$user->id);

            // 2) Seller (model) — customer_user_id abhi NA set karein
            $seller = new Seller();
            $seller->user_id             = $user->id;
            $seller->verification_status = 1;
            $seller->gstin               = $sellerInfo['seller_gstin'] ?? null;
            $seller->save();

            \Log::info('Seller created: '.$seller->id);

            // 3) Shop (model)
            $shop = new Shop();
            $shop->seller_id = $seller->id;
            $shop->name      = $sellerInfo['seller_name'];
            $shop->address   = $sellerInfo['seller_address'] ?? '';
            $shop->phone     = $sellerInfo['seller_phone'];
            $shop->save();

            \Log::info('Shop created for seller: '.$seller->id);

            // commit local writes
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Seller creation failed: '.$e->getMessage());
            throw new \Exception("Seller creation failed: ".$e->getMessage());
        }

        // ---------------- External (post-commit) ----------------

        // A) Zoho seller (best-effort)
        try {
            $zoho = new ZohoController();
            $zoho->createNewSellerInZoho($user->id);
        } catch (\Throwable $ex) {
            \Log::warning('Zoho seller create failed: '.$ex->getMessage());
        }

        // B) GST verify → sign-up  → party_code + customer_id  → Zoho customer
        try {
            $result = $this->gstVerifyAndSignup([
                'gstin'        => $sellerInfo['seller_gstin'] ?? '',
                'phone'        => $sellerInfo['seller_phone'] ?? '',
                'name'         => $sellerInfo['seller_name'] ?? '',
                'address'      => $sellerInfo['seller_address'] ?? '',
                'address2'     => $sellerInfo['address2'] ?? ($sellerInfo['city_name'] ?? ''),
                'city'         => $sellerInfo['city_name'] ?? ($user->city ?? ''),
                'company_name' => $sellerInfo['company_name'] ?? ($sellerInfo['seller_name'] ?? ''),
                'postal_code'  => $sellerInfo['postal_code'] ?? ($user->postal_code ?? ''),
                'state'        => $sellerInfo['state_id'] ?? ($sellerInfo['state_name'] ?? ($user->state ?? '')),
                // 'email'     => (omit to auto-generate for signup)
            ]);

            $partyCode      = $result['party_code']  ?? null;
            $remoteUserId   = $result['customer_id'] ?? null; // 👈 signup API ka data.id

            \Log::info('Signup result (manual entry): ', ['party_code' => $partyCode, 'remote_user_id' => $remoteUserId]);

            // Zoho customer create (if party code present)
            if ($partyCode) {
                try {
                    $zoho = new ZohoController();
                    $zoho->createNewCustomerInZoho($partyCode);
                } catch (\Throwable $zx) {
                    \Log::warning('Zoho customer create failed: '.$zx->getMessage());
                }
            } else {
                \Log::warning('No party_code from signup; Zoho customer create skipped.');
            }

            // ✅ Sellers table me customer_user_id = "customer ke user id" (REMOTE) set karo
            if ($remoteUserId) {
                $seller->customer_user_id = $remoteUserId;
                $seller->save();

                \Log::info('Updated sellers.customer_user_id with REMOTE customer id', [
                    'seller_id'       => $seller->id,
                    'remote_user_id'  => $remoteUserId,
                ]);
            } else {
                \Log::warning('No remote customer id in signup response; sellers.customer_user_id not updated.');
            }

        } catch (\Throwable $ex) {
            \Log::warning('GST verify → signup flow failed: '.$ex->getMessage());
        }

        return $seller->id;
    }




    private function gstVerifyAndSignup(array $data): ?array
    {
        try {
            // 1) Verify GST
            $verifyUrl = 'http://mazingbusiness.com/mazing_business_react/api/user/verify-gst-for-registration';
            $verifyRes = Http::asForm()->post($verifyUrl, [
                'gst_number' => $data['gstin'] ?? '',
            ]);

            if (!$verifyRes->ok() || $verifyRes->json('res') !== true) {
                \Log::warning('GST verify failed', ['status'=>$verifyRes->status(),'body'=>$verifyRes->body()]);
                return null;
            }

            $gstDataJson = json_encode($verifyRes->json('gst_data'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // 2) Sign-up payload
            $phone10     = $this->normalizePhone($data['phone'] ?? null);
            $emailForApi = $data['email'] ?? $this->makeCustomerSignupEmail($data['name'] ?? null, $data['gstin'] ?? null);

            $payload = [
                'gstin'        => $data['gstin']        ?? '',
                'gst_data'     => $gstDataJson,
                'phone'        => $phone10,
                'name'         => $data['name']         ?? '',
                'aadhar_card'  => $data['aadhar_card']  ?? '',
                'address'      => $data['address']      ?? '',
                'address2'     => $data['address2']     ?? '',
                'city'         => $data['city']         ?? '',
                'company_name' => $data['company_name'] ?? ($data['name'] ?? ''),
                'postal_code'  => $data['postal_code']  ?? '',
                'state'        => $data['state']        ?? '',
                'email'        => $emailForApi,
            ];

            // 3) Sign-up
            $signupUrl = 'http://mazingbusiness.com/mazing_business_react/api/user/sign-up';
            $res = Http::asForm()->post($signupUrl, array_filter($payload, fn($v) => $v !== null && $v !== ''));

            \Log::info('Sign-up API', ['status'=>$res->status(),'body'=>$res->body()]);

            if ($res->successful()) {
                $json       = $res->json();
                $partyCode  = data_get($json, 'data.party_code');
                $customerId = data_get($json, 'data.id'); // 👈 remote customer user id

                return [
                    'party_code'  => $partyCode ?: null,
                    'customer_id' => $customerId ?: null,
                ];
            }

            return null;

        } catch (\Throwable $e) {
            \Log::error('gstVerifyAndSignup failed: '.$e->getMessage());
            return null;
        }
    }


    // Phone → last 10 digits
    private function normalizePhone($raw): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);
        return $digits ? substr($digits, -10) : null;
    }

    // Signup ke liye unique email (local DB me save NAHI hota)
    private function makeCustomerSignupEmail(?string $name, ?string $gstin = null): string
    {
        $slug  = Str::slug($name ?: 'customer', '_');
        $token = substr(Str::uuid()->toString(), 0, 8);
        return "cust_{$slug}_{$token}@dummy.com";
    }

     // create seller with customer end


    public function saveMakePurchaseOrder(Request $request)
    {


        // Validate the input data
        $validatedData = $request->validate([
            'orders.*.quantity' => 'required|integer|min:0',
            'orders.*.purchase_price' => 'required|numeric|min:0',
            'orders.*.order_no' => 'required|string',
            'seller_info.seller_name' => 'required|string|max:255',
            'seller_info.seller_phone' => 'required|string|max:15',
            'warehouse_id' => 'required'
        ], [
            'orders.*.quantity.required' => 'Quantity is required for each item.',
            'orders.*.quantity.integer' => 'Quantity must be a valid number.',
            'orders.*.purchase_price.required' => 'Purchase price is required for each item.',
            'orders.*.purchase_price.numeric' => 'Purchase price must be a valid number.',
            'orders.*.order_no.required' => 'Order number is required.',
            'seller_info.seller_name.required' => 'Seller name is required.',
            'seller_info.seller_phone.required' => 'Seller phone is required.',
            'warehouse_id.required' => 'Warehouse selection is required.', 
        ]);

        $sellerId = null;
        $sellerInfo = [
            'seller_name' => $request->input('seller_info.seller_name'),
            'seller_address' => $request->input('seller_info.seller_address'),
            'seller_gstin' => $request->input('seller_info.seller_gstin'),
            'seller_phone' => $request->input('seller_info.seller_phone'),
            'state_name' => $request->input('seller_info.state_name'), // must be in your form
        ];

        if ($request->input('seller_info.seller_id') === 'create') {
           
            $sellerId = $this->createSellerFromManualEntry($sellerInfo);
        } else {
            $sellerId = $request->input('seller_info.seller_id');
        }


        $productInfo = [];
        $orderNumbers = [];
        $productInfoWithOutZeroQty = [];

        foreach ($request->input('orders') as $orderId => $orderData) {
            $partNo = $orderData['part_no'];
            $quantity = $orderData['quantity'];
            $purchasePrice = $orderData['purchase_price'];
            $currentSellerId = $orderData['seller_id'];
            $orderNo = $orderData['order_no'];
            $orderDate =  $orderData['order_date'];
            $age =  $orderData['age'];
            $hsncode = Product::where('part_no', $partNo)->value('hsncode');


            if (!$sellerId) {
                $sellerId = $currentSellerId;
            }

            Product::where('part_no', $partNo)->update(['purchase_price' => $purchasePrice]);

            $orderNoWithDate = $orderNo . " ($orderDate)";

            $productInfo[] = [
                'part_no' => $partNo,
                'qty' => $quantity,
                'order_no' => $orderNoWithDate,
                'age' => $age,
                'hsncode' => $hsncode 
            ];

            if ($quantity != 0) {
                $productInfoWithOutZeroQty[] = [
                    'part_no' => $partNo,
                    'qty' => $quantity,
                    'order_no' => $orderNoWithDate,
                    'age' => $age,
                    'hsncode' => $hsncode
                ];

                PurchaseBag::where('part_no', $partNo)
                ->where('order_no', $orderNo)
                ->update(['delete_status' => 1]);
            }

            $orderNumbers[] = $orderNoWithDate;
        }

        $lastOrder = MakePurchaseOrder::
             orderBy('id', 'desc')
            ->first();

        if ($lastOrder) {
            $lastOrderNumber = intval(substr($lastOrder->purchase_order_no, 3));
            $newOrderNumber = $lastOrderNumber + 1;
        } else {
            $newOrderNumber = 1;
        }

        $purchaseOrderNo = 'po-' . str_pad($newOrderNumber, 3, '0', STR_PAD_LEFT);

        $orderNumbersString = implode(',', array_unique($orderNumbers));

        $sellerInfo = [
            'seller_name' => $request->input('seller_info.seller_name'),
            'seller_address' => $request->input('seller_info.seller_address'),
            'seller_gstin' => $request->input('seller_info.seller_gstin'),
            'seller_phone' => $request->input('seller_info.seller_phone'),
        ];

        
       
        try {
            // Insert into new_final_purchase_orders
            $newOrder = MakePurchaseOrder::create([
                    'purchase_order_no' => $purchaseOrderNo,
                    'order_no' => $orderNumbersString,
                    'date' => now()->format('Y-m-d'),
                    'seller_id' => $sellerId,
                    'product_invoice' => json_encode($productInfoWithOutZeroQty),
                    'warehouse_id'=>$request->input('warehouse_id'),
                    'seller_info' => json_encode($sellerInfo),
                ]);

                $makePurchaseOrderId = $newOrder->id; // ✅ Retrieve inserted ID


                // Insert product details into purchase_order_details
                foreach ($productInfoWithOutZeroQty as $product) {

                    $itemDetails=Product::where('part_no',$product['part_no'])->first();
                    PurchaseOrderDetail::create([
                        'make_purchase_order_id' => (int)$makePurchaseOrderId, // ✅ Now inserting ID
                        'purchase_order_no' => $purchaseOrderNo,
                        'part_no' => $product['part_no'],
                        'qty' => $product['qty'],
                        'order_no' => $product['order_no'],
                        'age' => $product['age'],
                        'hsncode' => $product['hsncode'],
                        'tax'=>$itemDetails->tax,
                        'received' => 0,
                        'pre_close' => 0,
                        'pending' => $product['qty'],
                        'seller_info' => json_encode($sellerInfo),
                    ]);
                }

            
        } catch (\Exception $e) {
           
            return $e->getMessage();
            return redirect()->route('admin.purchase_order')->with('error', 'Error: ' . $e->getMessage());
        }

        $seller_warehouse = MakePurchaseOrder::join('sellers as s', 'make_purchase_orders.seller_id', '=', 's.id')
            ->join('users as u', 's.user_id', '=', 'u.id')
            ->where('make_purchase_orders.purchase_order_no', $purchaseOrderNo)
            ->value('u.warehouse_id');
        $invoiceController = new InvoiceController();
        $fileUrls = [
            $this->purchase_order_pdf_invoice($purchaseOrderNo),
            $this->packing_list_pdf_invoice($purchaseOrderNo)
        ];

        // echo $this->purchase_order_pdf_invoice($purchaseOrderNo);
        // die();

       

        $fileNames = ["Purchase Order", "Packing List"];

        $sellerPhone = MakePurchaseOrder::
            where('purchase_order_no', $purchaseOrderNo)
            ->value(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_phone'))"));

        // List of phone numbers to send the message to
        $toNumbers = [
            // $sellerPhone,  // Original seller phone number from the database
            '+919930791952', // Additional phone number 1
            '+919894753728'
        ];


        // Manager phone numbers based on seller_warehouse condition
        // Get the selected warehouse from the request
         $warehouseId = $request->warehouse_id;
        if ($warehouseId == 2) {
            $toNumbers[] = '+919763268640';  // Manager 1 (m1) //delhi
        } elseif ($warehouseId == 6) {
            $toNumbers[] = '+919860433981';  // Manager 2 (m2) mumbai
        }

        // Loop through each phone number and send the WhatsApp message with retry logic
        foreach ($toNumbers as $to) {
            foreach ($fileUrls as $index => $fileUrl) {
                $templateData = [
                    'name' => 'utility_purchase_order',
                    'language' => 'en_US',
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                [
                                    'type' => 'document',
                                    'document' => [
                                        'link' => $fileUrl,
                                        'filename' => $fileNames[$index],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' =>$purchaseOrderNo],
                            ],
                        ],
                    ],
                ];

                // Retry mechanism
                $retryCount = 0;
                $maxRetries = 1;
                $messageSent = false;

                while ($retryCount < $maxRetries && !$messageSent) {
                    try {
                        $this->WhatsAppWebService = new WhatsAppWebService();
                        $response1 = $this->WhatsAppWebService->sendTemplateMessage($to, $templateData);

                        if (isset($response1['messages'][0]['id'])) {
                            $messageId = $response1['messages'][0]['id'];

                            sleep(2); // Delay for 1 second before checking the status
                            // Call the function to get the message status
                            $messageStatus = $this->getMessageStatusById($messageId);

                            if ($messageStatus === 'sent') {
                                $messageSent = true;  // Mark as sent
                                break;  // Break out of the retry loop
                            } else {
                                throw new Exception("Message sending failed");
                            }
                        } else {
                            throw new Exception("Message ID not found in the response");
                        }
                    } catch (Exception $e) {
                        $retryCount++;
                        if ($retryCount >= $maxRetries) {
                            // Log or handle failure after 3 retries
                            Log::error("Failed to send message to $to after $maxRetries attempts. Error: " . $e->getMessage());
                            // Optionally, you can notify admin via email or other methods
                        } else {
                            // You may introduce a short delay before retrying (optional)
                            sleep(2); // Delay for 2 seconds before retrying
                        }
                    }
                }
            }
        }

        return redirect()->route('admin.purchasebag')->with('status', 'Purchase order saved successfully!');
    }

    

    // MANAGER-41 — identical logic but using Manager-41 models
    public function manager41ShowPurchaseInvoiceList(Request $request)
    {
        $products = \App\Models\Product::select('part_no', 'name')->get()->keyBy('part_no');

        $query = \App\Models\Manager41PurchaseInvoice::with([
                'makePurchaseOrder',
                'purchaseInvoiceDetails',
                'address.state'
            ])
            // ->where('purchase_invoice_type', '!=', 'customer')
            ->orderBy('id', 'DESC');

        if ($request->has('search') && !empty($request->search)) {
            $search = trim(strtolower($request->search));

            if ($search === 'unsync') {
                $query->whereNull('zoho_bill_id');
            } else {
                $query->where(function ($q) use ($search) {
                    $q->where('purchase_no', 'like', '%' . $search . '%')
                      ->orWhere('credit_note_number', 'like', '%' . $search . '%')
                      ->orWhereHas('address', function ($q2) use ($search) {
                          $q2->where('company_name', 'like', '%' . $search . '%')
                             ->orWhere('phone', 'like', '%' . $search . '%');
                      })
                      ->orWhereJsonContains('seller_info->seller_name', $search)
                      ->orWhereJsonContains('seller_info->seller_phone', $search);
                });
            }
        }

        $purchases = $query->paginate(50)->withQueryString();
        // echo "<pre>";
        // print_r($purchases->toArray());
        // die;

        // Reuse the same view
        return view('backend.po.purchase_invoice_list', compact('purchases', 'products'));
    }
   public function showPurchaseInvoiceList(Request $request)
    {

         if ($this->isActingAs41Manager()) {
            return $this->manager41ShowPurchaseInvoiceList($request);
        }

        $products = Product::select('part_no', 'name')->get()->keyBy('part_no');

        $query = PurchaseInvoice::with([
            'makePurchaseOrder',
            'purchaseInvoiceDetails',
            'address.state'
        ])->where('purchase_invoice_type', '!=', 'customer')->orderBy('id', 'DESC');

        if ($request->has('search') && !empty($request->search)) {
            $search = trim(strtolower($request->search));

            if ($search === 'unsync') {
                $query->whereNull('zoho_bill_id');
            } else {
                $query->where(function ($q) use ($search) {
                    $q->where('purchase_no', 'like', '%' . $search . '%')
                      ->orWhere('credit_note_number', 'like', '%' . $search . '%')
                      ->orWhereHas('address', function ($q2) use ($search) {
                          $q2->where('company_name', 'like', '%' . $search . '%')
                             ->orWhere('phone', 'like', '%' . $search . '%');
                      })
                      ->orWhereJsonContains('seller_info->seller_name', $search)
                      ->orWhereJsonContains('seller_info->seller_phone', $search);
                });
            }
        }

        // ✅ Pagination (15 per page, aap chaaho to number change kar sakte ho)
        $purchases = $query->paginate(50)->withQueryString();

        return view('backend.po.purchase_invoice_list', compact('purchases', 'products'));
    }

    public function showPurchaseCreditNoteList(Request $request)
    {
        $products = Product::select('part_no', 'name')->get()->keyBy('part_no');

        $query = PurchaseInvoice::with([
            'makePurchaseOrder',
            'purchaseInvoiceDetails',
            'address.state'
        ])->where('purchase_invoice_type', 'customer') // Filter only credit notes
          ->orderBy('id', 'DESC');

        if ($request->has('search') && !empty($request->search)) {
            $search = trim(strtolower($request->search));

            if ($search === 'unsync') {
                $query->where(function ($q) {
                    $q->whereNull('zoho_creditnote_id')
                      ->orWhere('zoho_creditnote_id', '');
                });
            } else {
                $query->where(function ($q) use ($search) {
                    $q->where('purchase_no', 'like', '%' . $search . '%')
                      ->orWhere('seller_invoice_no', 'like', '%' . $search . '%')
                      ->orWhere('credit_note_number', 'like', '%' . $search . '%')
                      ->orWhereHas('address', function ($q2) use ($search) {
                          $q2->where('company_name', 'like', '%' . $search . '%')
                             ->orWhere('phone', 'like', '%' . $search . '%');
                      })
                      ->orWhereJsonContains('seller_info->seller_name', $search)
                      ->orWhereJsonContains('seller_info->seller_phone', $search);
                });
            }
        }

        $purchases = $query->paginate(50)->withQueryString();

        return view('backend.po.purchase_credit_note', compact('purchases', 'products'));
    }

    public function generateIRP($zoho_creditnote_id)
    {
        try {
            $zoho = new ZohoController();
            $response = $zoho->pushCreditNoteToIRP($zoho_creditnote_id, false);

            // ✅ Safely decode the response
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $json = $response->getData(true);
            } elseif (is_array($response)) {
                $json = $response;
            } else {
                $json = json_decode((string)$response, true);
            }

            // ✅ Return proper JSON response
            return response()->json($json);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Exception occurred: ' . $e->getMessage()
            ], 500);
        }
    }


    public function viewPurchaseInvoiceProducts($id)
    {
        $invoice = PurchaseInvoice::with([
            'purchaseInvoiceDetails',
            'address.state'
        ])->where('id', $id)->firstOrFail();

        return view('backend.po.view_invoice_details', compact('invoice'));
    }
    public function exportCreditNoteInvoice($id)
    {
        return Excel::download(new FinalCreditInvoiceDetails($id), 'credit_note_invoice.xlsx', \Maatwebsite\Excel\Excel::XLSX, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }
    
    public function downloadCreditNoteInvoicePDF($id)
    {

      
        // Fetch invoice + relations
        $invoice = PurchaseInvoice::with([
            'address.state',           // Address belongsTo State
            'address.city',            // Address belongsTo City
            'purchaseInvoiceDetails',  // hasMany PurchaseInvoiceDetail (purchase_invoice_id)
        ])->findOrFail($id);

        // Customer info comes from addresses_id relation
        $customerInfo = $invoice->address;

        // Prefer the relation for details. Fallback to purchase_no only if needed.
        $details = $invoice->purchaseInvoiceDetails;
        if ($details->isEmpty()) {
            $details = PurchaseInvoiceDetail::where('purchase_invoice_no', $invoice->purchase_no)->get();
        }

        // Preload product meta in one go by part_no
        $partNos   = $details->pluck('part_no')->filter()->unique()->values();
        $products  = Product::whereIn('part_no', $partNos)
            ->get(['part_no','name','slug','thumbnail_img'])
            ->keyBy('part_no');

        // Build product rows (rate = base + 18%)
        $productInfo = $details->map(function ($detail) use ($products) {
            $p          = $products->get($detail->part_no);
            $baseRate   = (float) ($detail->price ?? 0);
            $rateWithTx = round($baseRate * 1.18, 2);
            $qty        = (float) ($detail->qty ?? 0);

            return (object) [
                'order_no'          => $detail->order_no ?? '-',
                'purchase_order_no' => $detail->purchase_order_no ?? '-',
                'part_no'           => $detail->part_no ?? '-',
                'product_name'      => $p->name ?? 'N/A',
                'slug'              => $p->slug ?? '',
                'thumbnail_img'     => $p->thumbnail_img ?? null,
                'hsncode'           => $detail->hsncode ?? '-',
                'qty'               => $qty,
                'rate'              => $rateWithTx,
                'subtotal'          => round($qty * $rateWithTx, 2),
            ];
        });

        $totalAmount = round($productInfo->sum('subtotal'), 2);

        $pdf = PDF::loadView('backend.po.credit_invoice_pdf', [
            'invoice'       => $invoice,
            // 'sellerInfo'  => $invoice->seller_info, // keep if you still need it elsewhere
            'customerInfo'  => $customerInfo,        // ✅ now available in Blade
            'productInfo'   => $productInfo,
            'totalAmount'   => $totalAmount,
            'direction'     => 'ltr',
            'text_align'    => 'left',
            'not_text_align'=> 'right',
            'font_family'   => 'DejaVu Sans',
            'logo'          => true,
        ]);

        // Name it as credit note if appropriate
        $fileName = ($invoice->credit_note_number ?: $invoice->purchase_no) . '_credit_note.pdf';

        return $pdf->download($fileName);
    }


   


    public function getCreditNoteInvoicePDFURL($id)
    {
        // 1) Fetch invoice + relations (lazy is fine, but we can eager-load address)
        $invoice = PurchaseInvoice::with('address')->where('id', $id)->firstOrFail();

        // 2) Customer/Seller info
        $customerInfo = $invoice->address;     // ✅ from addresses_id
        $sellerInfo   = $invoice->seller_info; // (array cast)

        // 3) Details (as you wrote: by purchase_no)
        $details = PurchaseInvoiceDetail::where('purchase_invoice_no', $invoice->purchase_no)->get();

        // 4) Build product-wise info (rate = base + 18% GST) — keep your style
        $productInfo = $details->map(function ($detail) {
            $product = Product::where('part_no', $detail->part_no)->first();

            $baseRate    = (float) ($detail->price ?? 0);
            $rateWithTax = round($baseRate * 1.18, 2);
            $qty         = (float) ($detail->qty ?? 0);
            $subtotal    = round($qty * $rateWithTax, 2);

            return (object) [
                'order_no'          => $detail->order_no ?? '-',
                'purchase_order_no' => $detail->purchase_order_no ?? '-',
                'part_no'           => $detail->part_no ?? '-',
                'product_name'      => $product->name ?? 'N/A',
                'slug'              => $product->slug ?? '',
                'thumbnail_img'     => $product->thumbnail_img ?? null,
                'hsncode'           => $detail->hsncode ?? '-',
                'qty'               => $qty,
                'rate'              => $rateWithTax, // inclusive of 18% GST
                'subtotal'          => $subtotal,
            ];
        });

        // 5) Totals
        $totalAmount = round($productInfo->sum('subtotal'), 2);

        // 6) Render PDF (your chosen blade name kept)
        $pdf = \PDF::loadView('backend.po.credit_invoice_pdf', [
            'invoice'        => $invoice,
            'sellerInfo'     => $sellerInfo,
            'customerInfo'   => $customerInfo,   // ✅ now available in Blade
            'productInfo'    => $productInfo,
            'totalAmount'    => $totalAmount,
            'direction'      => 'ltr',
            'text_align'     => 'left',
            'not_text_align' => 'right',
            'font_family'    => 'DejaVu Sans',
            'logo'           => true,
        ]);

        // 7) Ensure directory: public/credit_note_pdf  (comment fixed)
        $dirPath = public_path('credit_note_pdf');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dirPath);

        // 8) Safe filename and save (keep purchase_no as you did)
        $safePurchaseNo = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $invoice->purchase_no);
        $fileName  = $safePurchaseNo . '_invoice.pdf';
        $filePath  = $dirPath . DIRECTORY_SEPARATOR . $fileName;

        $pdf->save($filePath);

        // 9) Return FULL URL using url('public/...') as requested
        $publicUrl = url('public/credit_note_pdf/' . $fileName);
        return $publicUrl;
    }




    public function export($id)
    {

        return Excel::download(new FinalPurchaseInvoiceDetails($id), 'final_purchases.xlsx', \Maatwebsite\Excel\Excel::XLSX, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    public function exportDebitNoteInvoice($id)
    {
        return Excel::download(new DebitNoteInvoiceExport($id), 'debit_note_invoice.xlsx', \Maatwebsite\Excel\Excel::XLSX, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }


    public function purchase_order_pdf_invoice($purchase_order_no) {

        if (Session::has('currency_code')) {
            $currency_code = Session::get('currency_code');
        } else {
            $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
        }
        $language_code = Session::get('locale', Config::get('app.locale'));

        if (Language::where('code', $language_code)->first()->rtl == 1) {
            $direction = 'rtl';
            $text_align = 'right';
            $not_text_align = 'left';
        } else {
            $direction = 'ltr';
            $text_align = 'left';
            $not_text_align = 'right';
        }

        if ($currency_code == 'BDT' || $language_code == 'bd') {
            $font_family = "'Hind Siliguri','sans-serif'";
        } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
            $font_family = "'Hanuman','sans-serif'";
        } elseif ($currency_code == 'AMD') {
            $font_family = "'arnamu','sans-serif'";
        } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
            $font_family = "'Baloo Bhaijaan 2','sans-serif'";
        } elseif ($currency_code == 'THB') {
            $font_family = "'Kanit','sans-serif'";
        } else {
            $font_family = "'Roboto','sans-serif'";
        }

        // ✅ Fetch the purchase order details
        $order = MakePurchaseOrder::where('purchase_order_no', $purchase_order_no)->first();

        if (!$order) {
            throw new Exception("Purchase order not found: " . $purchase_order_no);
        }

        // ✅ Decode the seller_info JSON
        $sellerInfo = json_decode($order->seller_info, true);

        // ✅ Fetch product details from `purchase_order_details` table
        $productInfo = DB::table('purchase_order_details')
            ->join('products', 'purchase_order_details.part_no', '=', 'products.part_no')
            ->where('purchase_order_details.make_purchase_order_id', $order->id)
            ->select(
                'purchase_order_details.part_no',
                'purchase_order_details.qty',
                'purchase_order_details.order_no',
                'purchase_order_details.age',
                'purchase_order_details.hsncode',
                'products.name as product_name',
                'products.purchase_price'
            )
            ->get()
            ->map(function ($product) {
                $product->subtotal = $product->qty * $product->purchase_price;
                return $product;
            });

        // ✅ If no product info is found, handle gracefully
        if ($productInfo->isEmpty()) {
            throw new Exception("No product details found for purchase order: " . $purchase_order_no);
        }

        // ✅ Logo Path
        $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';

        // ✅ PDF View
        $view = 'backend.po.purchase_order_pdf';

        // ✅ Generate PDF Filename
        $randomNumber = rand(1000, 9999);
        $fileName = 'purchase_order-' . $randomNumber . '.pdf';

        // ✅ Generate PDF
        $pdf = PDF::loadView($view, [
            'logo' => $logo,
            'font_family' => $font_family,
            'direction' => $direction,
            'text_align' => $text_align,
            'not_text_align' => $not_text_align,
            'order' => $order,
            'sellerInfo' => $sellerInfo,
            'productInfo' => $productInfo
        ], [], []);

        // ✅ Save PDF
        $filePath = public_path('purchase_order_pdf/' . $fileName);
        $pdf->save($filePath);

        // ✅ Return Public URL
        return url('public/purchase_order_pdf/' . $fileName);
    }


    public function purchase_order_pdf_invoice_download($purchase_order_no) {
        if (Session::has('currency_code')) {
            $currency_code = Session::get('currency_code');
        } else {
            $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
        }
        $language_code = Session::get('locale', Config::get('app.locale'));

        if (Language::where('code', $language_code)->first()->rtl == 1) {
            $direction = 'rtl';
            $text_align = 'right';
            $not_text_align = 'left';
        } else {
            $direction = 'ltr';
            $text_align = 'left';
            $not_text_align = 'right';
        }

        if ($currency_code == 'BDT' || $language_code == 'bd') {
            $font_family = "'Hind Siliguri','sans-serif'";
        } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
            $font_family = "'Hanuman','sans-serif'";
        } elseif ($currency_code == 'AMD') {
            $font_family = "'arnamu','sans-serif'";
        } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
            $font_family = "'Baloo Bhaijaan 2','sans-serif'";
        } elseif ($currency_code == 'THB') {
            $font_family = "'Kanit','sans-serif'";
        } else {
            $font_family = "'Roboto','sans-serif'";
        }

        // ✅ Fetch the purchase order details
        $order = MakePurchaseOrder::where('purchase_order_no', $purchase_order_no)->first();

        if (!$order) {
            throw new Exception("Purchase order not found: " . $purchase_order_no);
        }

        // ✅ Decode the seller_info JSON
        $sellerInfo = json_decode($order->seller_info, true);

        // ✅ Fetch product details from `purchase_order_details` table
        $productInfo = DB::table('purchase_order_details')
            ->join('products', 'purchase_order_details.part_no', '=', 'products.part_no')
            ->where('purchase_order_details.make_purchase_order_id', $order->id)
            ->select(
                'purchase_order_details.part_no',
                'purchase_order_details.qty',
                'purchase_order_details.order_no',
                'purchase_order_details.age',
                'purchase_order_details.hsncode',
                'products.name as product_name',
                'products.purchase_price'
            )
            ->get()
            ->map(function ($product) {
                $product->subtotal = $product->qty * $product->purchase_price;
                return $product;
            });

        // ✅ If no product info is found, handle gracefully
        if ($productInfo->isEmpty()) {
            throw new Exception("No product details found for purchase order: " . $purchase_order_no);
        }

        // ✅ Logo Path
        $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';

        // ✅ PDF View
        $view = 'backend.po.purchase_order_pdf';

        // ✅ Generate PDF Filename
        $randomNumber = rand(1000, 9999);
        $fileName = 'purchase_order-' . $randomNumber . '.pdf';

        // ✅ Generate PDF
        $pdf = PDF::loadView($view, [
            'logo' => $logo,
            'font_family' => $font_family,
            'direction' => $direction,
            'text_align' => $text_align,
            'not_text_align' => $not_text_align,
            'order' => $order,
            'sellerInfo' => $sellerInfo,
            'productInfo' => $productInfo
        ], [], []);

        // ✅ Save PDF
        $filePath = public_path('purchase_order_pdf/' . $fileName);
        $pdf->save($filePath);

        // ✅ Return Public URL
        return response()->download($filePath, $fileName, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ])->deleteFileAfterSend(true);
        //return url('public/purchase_order_pdf/' . $fileName);
    }



    public function packing_list_pdf_invoice($purchase_order_no) {
        // ✅ Get currency & language settings
       if (Session::has('currency_code')) {
                $currency_code = Session::get('currency_code');
            } else {
                $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
            }
            $language_code = Session::get('locale', Config::get('app.locale'));

            if (Language::where('code', $language_code)->first()->rtl == 1) {
                $direction = 'rtl';
                $text_align = 'right';
                $not_text_align = 'left';
            } else {
                $direction = 'ltr';
                $text_align = 'left';
                $not_text_align = 'right';
            }

            if ($currency_code == 'BDT' || $language_code == 'bd') {
                $font_family = "'Hind Siliguri','sans-serif'";
            } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
                $font_family = "'Hanuman','sans-serif'";
            } elseif ($currency_code == 'AMD') {
                $font_family = "'arnamu','sans-serif'";
            } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
                $font_family = "'Baloo Bhaijaan 2','sans-serif'";
            } elseif ($currency_code == 'THB') {
                $font_family = "'Kanit','sans-serif'";
            } else {
                $font_family = "'Roboto','sans-serif'";
            }

        // ✅ Fetch the purchase order details
        $order = DB::table('make_purchase_orders')
            ->join('sellers', 'make_purchase_orders.seller_id', '=', 'sellers.id')
            ->join('shops', 'sellers.id', '=', 'shops.seller_id')
            ->where('make_purchase_orders.purchase_order_no', $purchase_order_no)
            ->select(
                'make_purchase_orders.*',
                'shops.name as seller_company_name',
                'shops.address as seller_address',
                'sellers.gstin as seller_gstin',
                'shops.phone as seller_phone'
            )
            ->first();

        if (!$order) {
            throw new Exception("Purchase order not found: " . $purchase_order_no);
        }

        // ✅ Fetch product details from `purchase_order_details` instead of JSON
        $productInfo = DB::table('purchase_order_details')
            ->join('products', 'purchase_order_details.part_no', '=', 'products.part_no')
            ->where('purchase_order_details.make_purchase_order_id', $order->id)
            ->select(
                'purchase_order_details.part_no',
                'purchase_order_details.qty',
                'purchase_order_details.order_no',
                'purchase_order_details.age',
                'purchase_order_details.hsncode',
                'products.name as product_name',
                'products.purchase_price'
            )
            ->get()
            ->map(function ($product) {
                $product->subtotal = $product->qty * $product->purchase_price;
                return $product;
            });

        if ($productInfo->isEmpty()) {
            throw new Exception("No product details found for purchase order: " . $purchase_order_no);
        }

        // ✅ Fetch Seller Name from JSON
        $sellerName = DB::table('make_purchase_orders')
            ->where('purchase_order_no', $purchase_order_no)
            ->value(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_name'))"));

        // ✅ Logo Path
        $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';

        // ✅ Generate PDF Filename
        $randomNumber = rand(1000, 9999);
        $fileName = 'packing_list-' . $randomNumber . '.pdf';

        // ✅ Generate PDF
        $pdf = PDF::loadView('backend.po.packing_list', [
            'logo' => $logo,
            'font_family' => $font_family,
            'direction' => $direction,
            'text_align' => $text_align,
            'not_text_align' => $not_text_align,
            'order' => $order,
            'seller_name' => $sellerName,
            'productInfo' => $productInfo
        ]);

        // ✅ Save PDF
        $filePath = public_path('packing_list_pdf/' . $fileName);
        $pdf->save($filePath);

        // ✅ Return Public URL
        return url('public/packing_list_pdf/' . $fileName);
    }



     public function packing_list_pdf_invoice_download($purchase_order_no) {
        // ✅ Get currency & language settings
       if (Session::has('currency_code')) {
                $currency_code = Session::get('currency_code');
            } else {
                $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
            }
            $language_code = Session::get('locale', Config::get('app.locale'));

            if (Language::where('code', $language_code)->first()->rtl == 1) {
                $direction = 'rtl';
                $text_align = 'right';
                $not_text_align = 'left';
            } else {
                $direction = 'ltr';
                $text_align = 'left';
                $not_text_align = 'right';
            }

            if ($currency_code == 'BDT' || $language_code == 'bd') {
                $font_family = "'Hind Siliguri','sans-serif'";
            } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
                $font_family = "'Hanuman','sans-serif'";
            } elseif ($currency_code == 'AMD') {
                $font_family = "'arnamu','sans-serif'";
            } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
                $font_family = "'Baloo Bhaijaan 2','sans-serif'";
            } elseif ($currency_code == 'THB') {
                $font_family = "'Kanit','sans-serif'";
            } else {
                $font_family = "'Roboto','sans-serif'";
            }

        // ✅ Fetch the purchase order details
        $order = MakePurchaseOrder::
            join('sellers', 'make_purchase_orders.seller_id', '=', 'sellers.id')
            ->join('shops', 'sellers.id', '=', 'shops.seller_id')
            ->where('make_purchase_orders.purchase_order_no', $purchase_order_no)
            ->select(
                'make_purchase_orders.*',
                'shops.name as seller_company_name',
                'shops.address as seller_address',
                'sellers.gstin as seller_gstin',
                'shops.phone as seller_phone'
            )
            ->first();

        if (!$order) {
            throw new Exception("Purchase order not found: " . $purchase_order_no);
        }

        // ✅ Fetch product details from `purchase_order_details` instead of JSON
        $productInfo = PurchaseOrderDetail::
            join('products', 'purchase_order_details.part_no', '=', 'products.part_no')
            ->where('purchase_order_details.make_purchase_order_id', $order->id)
            ->select(
                'purchase_order_details.part_no',
                'purchase_order_details.qty',
                'purchase_order_details.order_no',
                'purchase_order_details.age',
                'purchase_order_details.hsncode',
                'products.name as product_name',
                'products.purchase_price'
            )
            ->get()
            ->map(function ($product) {
                $product->subtotal = $product->qty * $product->purchase_price;
                return $product;
            });

        if ($productInfo->isEmpty()) {
            throw new Exception("No product details found for purchase order: " . $purchase_order_no);
        }

        // ✅ Fetch Seller Name from JSON
        $sellerName = MakePurchaseOrder::
            where('purchase_order_no', $purchase_order_no)
            ->value(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_name'))"));

        // ✅ Logo Path
        $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';

        // ✅ Generate PDF Filename
        $randomNumber = rand(1000, 9999);
        $fileName = 'packing_list-' . $randomNumber . '.pdf';

        // ✅ Generate PDF
        $pdf = PDF::loadView('backend.po.packing_list', [
            'logo' => $logo,
            'font_family' => $font_family,
            'direction' => $direction,
            'text_align' => $text_align,
            'not_text_align' => $not_text_align,
            'order' => $order,
            'seller_name' => $sellerName,
            'productInfo' => $productInfo
        ]);

        // ✅ Save PDF
        $filePath = public_path('packing_list_pdf/' . $fileName);
        $pdf->save($filePath);

        // ✅ Return Public URL
        // return url('public/packing_list_pdf/' . $fileName);
        return response()->download($filePath, $fileName, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ])->deleteFileAfterSend(true);
    }


    public function downloadInvoicePDF($id)
    {
        // Fetch invoice
        $invoice = PurchaseInvoice::where('id', $id)->firstOrFail();

        // Fetch related seller info and details
        $sellerInfo = $invoice->seller_info;
        $details = PurchaseInvoiceDetail::where('purchase_invoice_no', $invoice->purchase_no)->get();

        // Prepare product-wise info
        $productInfo = $details->map(function ($detail) {
            $product = Product::where('part_no', $detail->part_no)->first();

            $rate = $product->purchase_price ?? 0;
            $qty = $detail->qty ?? 0;
            $subtotal = $qty * $rate;

            return (object)[
                'order_no' => $detail->order_no ?? '-', // Ensure these columns exist
                'purchase_order_no' => $detail->purchase_order_no ?? '-',
                'part_no' => $detail->part_no ?? '-',
                'product_name' => $product->name ?? 'N/A',
                'slug' => $product->slug ?? '',
                'thumbnail_img' => $product->thumbnail_img ?? null,
                'hsncode' => $detail->hsncode ?? '-',
                'qty' => $qty,
                'rate' => $rate,
                'subtotal' => $subtotal
            ];
        });

        // Calculate total
        $totalAmount = $productInfo->sum('subtotal');

        // Generate PDF
        $pdf = PDF::loadView('backend.po.purchase_invoice_pdf', [
            'invoice' => $invoice,
            'sellerInfo' => $sellerInfo,
            'productInfo' => $productInfo,
            'totalAmount' => $totalAmount,
            'direction' => 'ltr',
            'text_align' => 'left',
            'not_text_align' => 'right',
            'font_family' => 'DejaVu Sans',
            'logo' => true
        ]);

        return $pdf->download($invoice->purchase_no . '_invoice.pdf');
    }




  //temp function 


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

    public function manager41AllChallan(Request $request)
    {
       $is41Manager = $this->isActingAs41Manager();
        try {
            $user   = auth()->user();
            $userId = $user->id;

            // Access: allow super admin, special staff, staff role 4, and manager_41
            $superAdminId    = 27604;
            $specialStaffIds = [180, 169, 25606];
            $staff           = \App\Models\Staff::where('user_id', $userId)->first();

            $isSuperAdmin       = ($userId == $superAdminId);
            $hasWarehouseAccess = false;

            if ($isSuperAdmin || in_array($userId, $specialStaffIds, true)) {
                $hasWarehouseAccess = true;
            } elseif ($staff && (int)$staff->role_id === 4) {
                $hasWarehouseAccess = true;
            } elseif ($this->isActingAs41Manager()) {
                $hasWarehouseAccess = true;
            }

            if (!$hasWarehouseAccess) {
                return back()->with('error', 'Access denied. You are not authorized to view challans.');
            }

            // Warehouse prefixes
            $warehousePrefixes = [
                1 => 'KOL',
                2 => 'DEL',
                6 => 'MUM',
            ];

            // ⬇️ Manager-41 models (no aliases)
            $query = \App\Models\Manager41Challan::with(['challan_details', 'user', 'sub_order']);

            // Restrict by warehouse prefix if not super admin
            if (!$isSuperAdmin) {
                $prefix = $warehousePrefixes[$user->warehouse_id] ?? null;
                if (!$prefix) {
                    return back()->with('error', 'Invalid warehouse assigned to user.');
                }
                $query->where('challan_no', 'LIKE', "%{$prefix}%");
            }

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('challan_no', 'LIKE', '%' . $search . '%')
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('company_name', 'LIKE', '%' . $search . '%');
                      })
                      // Manager-41: order_no is on the manager41 sub_order itself
                      ->orWhereHas('sub_order', function ($sq) use ($search) {
                          $sq->where('order_no', 'LIKE', '%' . $search . '%');
                      });
                });
            }

            $orderData = $query->where('invoice_status', '0')
                               ->orderBy('id', 'DESC')
                               ->paginate(50);

            // Reuse the same view (it reads ->challan_details, ->user, ->sub_order)
            return view('backend.sales.all_challan', compact('orderData','is41Manager'));

        } catch (\Exception $e) {
            $code = $e->getCode();
            $msg  = ($code == 23000) ? __("direct link already exists") : $e->getMessage();
            return ['res' => false, 'msg' => $msg, 'data' => $code];
        }
    }
    public function manager41ChallanPdf($id)
    {
        // sirf manager-41 ko allow
        if (!$this->isActingAs41Manager()) {
            abort(403, 'Access denied');
        }

        // Manager-41 challan + relations
        $challan = \App\Models\Manager41Challan::with([
            'challan_details.product_data', // product name, etc.
            'user',                         // customer
            'sub_order',
            'order_warehouse'               // warehouse name
        ])->findOrFail($id);

        $items      = $challan->challan_details;
        $totalQty   = (int) $items->sum('quantity');
        $subTotal   = (float) $items->sum('final_amount');
        $grandTotal = $subTotal; // (tax/charges ho to yahan add kar dena)

        // Amount in words (built-in Intl; fallback empty)
        $amountWords = '';
        if (class_exists('\NumberFormatter')) {
            $fmt = new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT);
            $amountWords = ucfirst($fmt->format((int) round($grandTotal))) . ' rupees only';
        }

        // Optional balances (agar aapke pass ledger ho to usse nikaal lo)
        $previousBalance = 0.00;
        $netBalance      = $previousBalance + $grandTotal;

        $pdf = Pdf::loadView('backend.sales.manager41_challan_pdf', compact(
            'challan','items','totalQty','subTotal','grandTotal','amountWords','previousBalance','netBalance'
        )); // screenshot jaisa compact paper

        // stream ya download—jo chaho
        return $pdf->stream($challan->challan_no . '.pdf');
    }
    public function allChallan(Request $request)
    {

         // 👉 If current login is manager_41, jump to the Manager-41 version
        if ($this->isActingAs41Manager()) {
            return $this->manager41AllChallan($request);
        }

        try {
            $user = auth()->user();
            $userId = $user->id;

            // Super admin and special staff
            $superAdminId = 1;
            $specialStaffIds = [180, 169, 25606];

            // Check staff table
            $staff = Staff::where('user_id', $userId)->first();

            // Access logic
            $isSuperAdmin = $userId == $superAdminId;
            $hasWarehouseAccess = false;

            if ($isSuperAdmin || in_array($userId, $specialStaffIds)) {
                $hasWarehouseAccess = true;
            } elseif ($staff && $staff->role_id == 4) {
                $hasWarehouseAccess = true;
            }

            if (!$hasWarehouseAccess) {
                return back()->with('error', 'Access denied. You are not authorized to view challans.');
            }

            // Warehouse prefixes
            $warehousePrefixes = [
                1 => 'KOL',
                2 => 'DEL',
                6 => 'MUM',
            ];

            $query = Challan::with('challan_details', 'user');

            // Restrict based on warehouse prefix if not super admin
            if (!$isSuperAdmin) {
                $prefix = $warehousePrefixes[$user->warehouse_id] ?? null;

                if (!$prefix) {
                    return back()->with('error', 'Invalid warehouse assigned to user.');
                }

                $query->where('challan_no', 'LIKE', "%{$prefix}%");
            }

            //$orderData = $query->where('invoice_status','0')->orderBy('id', 'DESC')->paginate(50);
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('challan_no', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('company_name', 'LIKE', '%' . $search . '%');
                    })
                    ->orWhereHas('sub_order.order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_no', 'LIKE', '%' . $search . '%');
                    });
                });
            }

            $orderData = $query->where('invoice_status', '0')->orderBy('id', 'DESC')->paginate(50);

            return view('backend.sales.all_challan', compact('orderData'));

        } catch (\Exception $e) {
            $errorCode = $e->getCode();
            $errorMessage = ($errorCode == 23000)
                ? __("direct link already exists")
                : $e->getMessage();

            return ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
        }
    }


   

    public function cancelChallan($id)
    {
        $challan = Challan::find($id);

        if (!$challan) {
            return redirect()->back()->with('error', 'Challan not found.');
        }

        $subOrderId = $challan->sub_order_id;

        // Fetch all challan_details for this challan
        $challanDetails = ChallanDetail::where('sub_order_id', $subOrderId)
                            ->where('challan_id', $challan->id) // ensure matching challan_id
                            ->get();
       

        foreach ($challanDetails as $detail) {
            // 1. Update challan_qty in sub_order_details
            $sub = SubOrderDetail::find($detail->sub_order_details_id);


            if ($sub) {
                $sub->challan_qty = max(0, ($sub->challan_qty ?? 0) - $detail->quantity);
                $sub->save();
            }

            // 2. Call inventory update with product_id
            if ($detail->product_id) {
                $requestSubmit = new Request();
                $requestSubmit->merge([
                    'product_id' => $detail->product_id
                ]);
                $this->inventoryProductEntry($requestSubmit);
            }

            // 3. Delete challan detail
            $detail->delete();
        }

        // 4. Delete the challan itself
        $challan->delete();

        return redirect()->back()->with('success', 'Challan cancelled and records deleted successfully.');
    }




public function manager41SaveChallan(Request $request)
{
    try {
        // 1) Fetch sub-order from manager_41 table
        $subOrder = \App\Models\Manager41SubOrder::where('id', $request['sub_order_id'])->first();
        if (!$subOrder) {
            return back()->with('error', 'Invalid sub-order.');
        }

        // 2) Resolve warehouse and generate challan number (manager_41 challans)
        $warehouse = \App\Models\Warehouse::where('name', $request['warehouse'])->firstOrFail();

        $last = \App\Models\Manager41Challan::where('warehouse_id', $warehouse->id)
            ->orderBy('id', 'DESC')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('/', $last->challan_no);
            $seq   = (int) ($parts[count($parts) - 2] ?? 0) + 1;
            if ($this->getFinancialYear() != end($parts)) {
                $seq = 1;
            }
        }

        // $challan_no = 'DO/' . strtoupper(substr($warehouse->name, 0, 3)) . '/'
        //             . str_pad($seq, 6, '0', STR_PAD_LEFT) . '/'
        //             . $this->getFinancialYear();
        $prefix = strtoupper(substr($warehouse->name, 0, 3)) . '41';
       $challan_no = sprintf('%s/%06d/%s', $prefix, (int)$seq, $this->getFinancialYear());

        // 3) Validate lines + compute totals
        $partNos   = array_filter(explode(',', $request['part_number']));
        $detailIds = array_filter(explode(',', $request['sub_order_detail_id']));

        $grand_total = 0;
        $insertFlag  = 0;

        foreach ($partNos as $pn) {
            $grand_total += (float) ($request['billed_amount_' . $pn] ?? 0);
            $qty = (int) ($request['billed_qty_' . $pn] ?? 0);
            if (!$insertFlag && $qty > 0) {
                $insertFlag = 1;
            }
        }

        if (!$insertFlag) {
            return redirect()->back()->withInput()->with('error', 'Please scan atleast one item for challan submit.');
        }

        // 4) Create challan (manager_41 table)
        $challan = \App\Models\Manager41Challan::create([
            'challan_no'          => $challan_no,
            'challan_date'        => date('Y-m-d'),
            'sub_order_id'        => $subOrder->id,
            'user_id'             => $request['user_id'],
            'shipping_address_id' => $subOrder->shipping_address_id,
            'shipping_address'    => $subOrder->shipping_address,
            'grand_total'         => $grand_total,
            'warehouse_id'        => $warehouse->id,
            'warehouse'           => $warehouse->name,
            'transport_name'      => $request['transport_name'] ?? null,
            'transport_id'        => $request['transport_name'] ?? null,
            'transport_phone'     => $request['transport_phone'] ?? null,
        ]);

        // 5) Insert challan lines + bump challan_qty on manager_41 sub_order_details
        foreach ($partNos as $idx => $pn) {
            $qty = (int) ($request['billed_qty_' . $pn] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $detailId = $detailIds[$idx] ?? null;
            $sod = \App\Models\Manager41SubOrderDetail::find($detailId);
            if (!$sod) {
                continue;
            }

            \App\Models\Manager41ChallanDetail::create([
                'challan_id'            => $challan->id,
                'challan_no'            => $challan->challan_no,
                'user_id'               => $request['user_id'],
                'product_warehouse_id'  => $sod->product_warehouse_id,
                'product_id'            => $sod->product_id,
                'tax'                   => $sod->tax,
                'variation'             => $sod->variation,
                'price'                 => $sod->price,
                'quantity'              => $qty,
                'rate'                  => $request['rate_' . $pn] ?? null,
                'final_amount'          => $request['billed_amount_' . $pn] ?? null,
                'sub_order_id'          => $sod->sub_order_id,
                'sub_order_details_id'  => $sod->id,
            ]);

            // Update challan qty on the sub order detail
            $sod->challan_qty = (int) $sod->challan_qty + $qty;
            $sod->save();

            // === Manager-41 Inventory Sync marker (as you requested) ===
            $requestSubmit = new \Illuminate\Http\Request();
            $requestSubmit->merge([
                'product_id' => $sod->product_id,
            ]);
            $this->manager41InventoryProductEntry($requestSubmit);
        }

        // 6) Redirect to manager_41 challan list
        return redirect()
            ->route('order.allChallan')
            ->with('success', 'Challan created successfully.');

    } catch (\Exception $e) {
        $errorCode    = $e->getCode();
        $errorMessage = $e->getMessage();
        return ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }
}


public function manager41InventoryProductEntry(Request $request)
    {
        // Check in Manager-41 reset list
        $existsByProduct = \App\Models\Manager41ResetInventoryProduct::where('product_id', $request->product_id)->first();
        if ($existsByProduct === null) {
            $product = \App\Models\Product::where('id', $request->product_id)->first();

            // Avoid duplicate (same product_id + part_no)
            $existsByPair = \App\Models\Manager41ResetInventoryProduct::where('product_id', $request->product_id)
                ->where('part_no', $request->part_no)
                ->first();

            if ($existsByPair === null) {
                \App\Models\Manager41ResetInventoryProduct::create([
                    'product_id' => $request->product_id,
                    'part_no'    => $product ? $product->part_no : $request->part_no,
                ]);
            }
        }

        return 1;
    }



    protected function getForcedChallanPdfURL(int $challanId): string
    {
        $challan = Challan::with([
            'sub_order',
            'order_warehouse',
            'user',
            'challan_details.product_data',
        ])->findOrFail($challanId);

        // Branch code by warehouse
        $branchMap = [1 => 'KOL', 2 => 'DEL', 6 => 'MUM'];
        $branchCode = $branchMap[$challan->warehouse_id] ?? 'DEL';

        $branchDetailsAll = [
            'KOL' => [
                'gstin' => '19ABACA4198B1ZS',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => '257B, BIPIN BEHARI GANGULY STREET',
                'address_2' => '2ND FLOOR',
                'address_3' => '',
                'city' => 'KOLKATA',
                'state' => 'WEST BENGAL',
                'postal_code' => '700012',
                'contact_name' => 'Amir Madraswala',
                'phone' => '9709555576',
                'email' => 'acetools505@gmail.com',
            ],
            'MUM' => [
                'gstin' => '27ABACA4198B1ZV',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => 'HARIHAR COMPLEX F-8, HOUSE NO-10607, ANUR DEPODE ROAD',
                'address_2' => 'GODOWN NO.7, GROUND FLOOR',
                'address_3' => 'BHIWANDI',
                'city' => 'MUMBAI',
                'state' => 'MAHARASHTRA',
                'postal_code' => '421302',
                'contact_name' => 'Hussain',
                'phone' => '9930791952',
                'email' => 'acetools505@gmail.com',
            ],
            'DEL' => [
                'gstin' => '07ABACA4198B1ZX',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => 'Khasra No. 58/15',
                'address_2' => 'Pal Colony',
                'address_3' => 'Village Rithala',
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'postal_code' => '110085',
                'contact_name' => 'Mustafa Worliwala',
                'phone' => '9730377752',
                'email' => 'acetools505@gmail.com',
            ],
        ];
        $branchDetails = $branchDetailsAll[$branchCode];

        // Shipping (prefer model; fallback JSON on challan)
        $shipping = null;
        if (!empty($challan->shipping_address_id)) {
            $shipping = Address::with('state')->find($challan->shipping_address_id);
        } else {
            $raw = $challan->shipping_address;
            $arr = is_string($raw) ? json_decode($raw, true) : (array) $raw;
            $shipping = (object) $arr;
        }

        // Minimal "invoice" stub (blade expects $invoice)
        $invoiceStub = (object) [
            'id'         => 0,
            'invoice_no' => 'N/A',
            'created_at' => now(),
            'warehouse_id' => $challan->warehouse_id,
            'party_info'   => $challan->shipping_address,
            'shipping_address_id' => $challan->shipping_address_id,
        ];

        // Render forced-notification PDF (use the NEW blade)
        $pdf = \PDF::loadView('backend.sales.challan_forced_notification_pdf', [
            'invoice'        => $invoiceStub,
            'challan'        => $challan,
            'details'        => $challan->challan_details,
            'branchDetails'  => $branchDetails,
            'billingDetails' => null,
            'shipping'       => $shipping,
            'logistic'       => null,
            'eway'           => null,
        ]);

        // Save file
        $dir = public_path('challan_forced_notifications');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $safeNo = Str::of((string) $challan->challan_no)->replace('/', '_')->replace('\\', '_');
        $file   = 'FORCED_CHALLAN_'.time().'_'.$safeNo.'.pdf';
        $path   = $dir.DIRECTORY_SEPARATOR.$file;

        $pdf->save($path);

        return url('public/challan_forced_notifications/'.$file);
    }

  public function saveChallan(Request $request){
     if ($this->isActingAs41Manager()) {
        return $this->manager41SaveChallan($request);
    }
    try{
        $isForced = (int) $request->input('force_create', 0);
        
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
        // 👇 ADD THIS BEFORE create()
        $challanData['is_warranty'] = SubOrderDetail::whereIn('id', $sub_order_detail_id)
            ->where('is_warranty', 1)
            ->exists() ? 1 : 0;
        $challanInsertData = Challan::create($challanData);


        foreach($part_no_array as $key => $value){
          if($request['billed_qty_'.$value] > 0 AND $request['billed_qty_'.$value] != ""){
            $getSubOrderDetailsData = SubOrderDetail::with('product','user')->where('id',$sub_order_detail_id[$key])->first();
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
            $challanDetailsData['barcode'] = (($b = trim((string)($getSubOrderDetailsData->barcode ?? ''))) !== '' ? $b : null);
            $challanDetailsData['is_warranty'] = (int) ($getSubOrderDetailsData->is_warranty == 1);

            $challanDetailsInsertData = ChallanDetail::create($challanDetailsData);
            $getSubOrderDetailsData->challan_qty = $getSubOrderDetailsData->challan_qty + $request['billed_qty_'.$value];
            $getSubOrderDetailsData->save();

          }
        }
        // ... after details loop ends and $challanInsertData is created
        if ($isForced === 1) {
            $this->sendForcefullyCreatedChallanNotification($challanInsertData->id);
        }
      }
      
      $response = ['res' => true, 'msg' => "Successfully insert data."];
      return redirect()->route('order.allChallan')->send();
      // $splitOrderData = SubOrder::create($splitOrder);
      // echo "<pre>"; print_r($splitOrder);print_r($request->all());die;
    } catch (\Exception $e) {
        $errorCode = $e->getCode();
        if ($errorCode == 23000) {
            // $errorMessage = __("direct link already exists");
             $errorMessage = $e->getMessage();
        } else {
            $errorMessage = $e->getMessage();//"something went wrong, please try again";
        }
        $response = ['res' => false, 'msg' => $errorMessage, 'data' => $errorCode];
    }        
    return $response; 
  }

  public function sendForcefullyCreatedChallanNotification(int $challanId): void
    {
        // Load challan + eager
        $challan = Challan::with(['challan_details.product_data', 'user'])->findOrFail($challanId);

        // --- Under-priced part nos (highlighted only) ---
        $underPriced = [];
        foreach ($challan->challan_details as $d) {
            $p        = $d->product_data;
            if (!$p || empty($p->part_no)) continue;

            $purchase = (float) ($p->purchase_price ?? $p->unit_price ?? 0);
            $rate     = (float) ($d->rate ?? 0);

            if ($rate < $purchase) {
                $underPriced[$p->part_no] = $p->part_no; // unique
            }
        }
        $partNos = implode(', ', array_values($underPriced)) ?: 'N/A';

        // Agar kuch bhi under-priced nahi nikla, to quietly return (optional)
        if ($partNos === 'N/A') {
            \Log::info('[WA] Forced challan: no under-priced rows; skipping WA.', ['challan_id' => $challanId]);
            return;
        }

        // --- PDF: forced-notification PDF (only highlighted rows ideally) ---
        // aapka helper:
        $pdfUrl = $this->getForcedChallanPdfURL($challanId);

        $challanNo   = (string) $challan->challan_no;
        $challanDate = \Carbon\Carbon::parse($challan->challan_date ?? now())->format('d-m-Y');

        // --- Recipients ---
        $rawRecipients = [
            '9894753728',
            '9709555576',
            '9930791952',
            '9730377752',
        ];
        // warehouse wise add-ons
        $whId = (int) $challan->warehouse_id;
        if ($whId === 2) {
            $rawRecipients[] = '8210259914';
        } elseif ($whId === 6) {
            $rawRecipients[] = '9860433981';
        }

        $recipients = collect($rawRecipients)->filter()->map(function ($n) {
            $d = preg_replace('/\D/', '', (string) $n);
            if (strlen($d) === 10) $d = '91' . $d;
            return (strlen($d) === 12 && str_starts_with($d, '91')) ? ('+' . $d) : null;
        })->filter()->unique()->values()->all();

        // --- Upload media once ---
        $wa = new WhatsAppWebService();
        $media = $wa->uploadMedia($pdfUrl);
        $mediaId = is_array($media) ? ($media['media_id'] ?? $media['id'] ?? null) : null;
        if (!$mediaId) {
            \Log::error('[WA] Media upload failed for challan '.$challanId, ['response' => $media]);
            return;
        }

        // --- Template payload ---
        // NOTE: yahan template name aapke WhatsApp BSP me jo approved ho, wahi use karein.
        // Agar aap "force_invoice_notice" hi reuse karte ho to name wahi rakho.
        $templateData = [
            'name'      => 'force_invoice_notice', // or 'force_invoice_notice' if that’s your approved name
            'language'  => 'en_US',
            'components'=> [
                [
                    'type'       => 'header',
                    'parameters' => [[
                        'type'     => 'document',
                        'document' => [
                            'filename' => "Challan - {$challanNo}.pdf",
                            'id'       => $mediaId,
                        ],
                    ]],
                ],
                [
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $challanNo],   // {{1}} Challan No
                        ['type' => 'text', 'text' => $partNos],     // {{2}} UNDER-PRICED part nos
                        ['type' => 'text', 'text' => $challanDate], // {{3}} Challan Date
                    ],
                ],
            ],
        ];

                       
        foreach ($recipients as $to) {
            try {
                $resp = $wa->sendTemplateMessage($to, $templateData);
                \Log::info('[WA] Forced challan notice sent', [
                    'to' => $to,
                    'challan_id' => $challanId,
                    'resp' => $resp,
                ]);
            } catch (\Throwable $e) {
                \Log::error('[WA] Send failed', [
                    'to' => $to,
                    'challan_id' => $challanId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }



  public function challanDetails($id){
    // If current user is manager_41, use the Manager-41 data source
    if ($this->isActingAs41Manager()) {
        return $this->manager41ChallanDetails($id);
    }
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

  public function manager41ChallanDetails($id)
  {
        try {
            // Use your Manager-41 models directly (no aliases)
            $orderData    = \App\Models\Manager41Challan::with('challan_details','user')->where('id',$id)->first();
            $orderDetails = \App\Models\Manager41ChallanDetail::with('product','user')->where('challan_id',$id)->get();
            $userDetails  = $orderData->user;

            $allAddressesForThisUser = $userDetails->get_addresses;
            // Manager-41 challan also stores shipping_address_id
            $shippingAddress = $userDetails->get_addresses->where('id', $orderData->shipping_address_id)->first();

            $allWareHouse      = \App\Models\Warehouse::where('active','1')->get();
            $allTransportData  = \App\Models\Carrier::orderBy('name','ASC')->get();

            // Reuse the same view; it reads ->challan_details/->user/->shipping_address_id
            return view('backend.sales.challan_details', compact(
                'orderData','orderDetails','userDetails','allAddressesForThisUser','shippingAddress','allWareHouse','allTransportData'
            ));

        } catch (\Exception $e) {
            $code = $e->getCode();
            $msg  = ($code == 23000) ? __("direct link already exists") : $e->getMessage();
            return ['res' => false, 'msg' => $msg, 'data' => $code];
        }
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

//adding coding start here
    public function viewSelectedChallansProducts(Request $request)
    {
        $ids = $request->get('challan_ids');
    
        if (!$ids) {
            return redirect()->route('order.allChallan')->with('error', 'No challans selected.');
        }
    
        // ✅ robust parse
        $challanIds = array_values(array_filter(array_map('intval', explode(',', $ids))));
        if (empty($challanIds)) {
            return redirect()->route('order.allChallan')->with('error', 'Invalid challan IDs.');
        }
    
        // ✅ eager-load exactly the fields we need from Product
        $challans = Challan::with([
            'user',
            'challan_details.product_data:id,part_no,name,purchase_price,unit_price,hsncode,tax'
        ])->whereIn('id', $challanIds)->get();
    
        if ($challans->isEmpty()) {
            return redirect()->route('order.allChallan')->with('error', 'Challan data not found.');
        }
    
        // ✅ same warehouse check
        $warehouseIds = $challans->pluck('warehouse_id')->unique();
        if ($warehouseIds->count() > 1) {
            return redirect()->route('order.allChallan')->with('error', 'All selected challans must belong to the same warehouse.');
        }
    
        // ✅ same customer check
        $userIds = $challans->pluck('user_id')->unique();
        if ($userIds->count() > 1) {
            return redirect()->route('order.allChallan')->with('error', 'All selected challans must belong to the same customer.');
        }
    
        // ✅ decode shipping JSON safely
        foreach ($challans as $challan) {
            $challan->shipping_info = json_decode((string) $challan->shipping_address, true);
        }
    
        // ✅ Build a map of part_no => purchase price (fallback to unit_price)
        // Keep keys EXACT as they appear on each row, so your Blade lookup matches.
        $allProductRates = [];
        foreach ($challans as $challan) {
            foreach ($challan->challan_details as $detail) {
                $p = optional($detail->product_data);
                if (!$p || !$p->part_no) {
                    continue;
                }
                $purchase = (float) ($p->purchase_price ?? $p->unit_price ?? 0);
                $allProductRates[$p->part_no] = $purchase;
            }
        }
    
        // ✅ (Optional but useful) Precompute under-priced part numbers on server too
        // Condition: product purchase_price > challan billed rate
        $underPricedPartNos = [];
        foreach ($challans as $challan) {
            foreach ($challan->challan_details as $detail) {
                $p = optional($detail->product_data);
                if (!$p || !$p->part_no) continue;
    
                $rate      = (float) ($detail->rate ?? 0);
                $purchase  = (float) ($allProductRates[$p->part_no] ?? 0);
    
                if ($purchase > $rate) {
                    // store original case part_no (nice for display)
                    $underPricedPartNos[$p->part_no] = $p->part_no;
                }
            }
        }
        $underPricedPartNos = array_values($underPricedPartNos);
    
        return view('backend.sales.selected_challans_products', [
            'challans'            => $challans,
            'challanIds'          => $challanIds,
            'allProductRates'     => $allProductRates,     // for data-purchase-price in rows
            'underPricedPartNos'  => $underPricedPartNos,  // handy if you want to pre-highlight/confirm
        ]);
    }

    public function sendForcefullyCreatedInvoiceNotification($invoiceId){
        // Invoice + lines
        $invoice = InvoiceOrder::with(['invoice_products', 'warehouse', 'user'])->findOrFail($invoiceId);

        // --- A) Under-priced part numbers (highlighted only) ---
        // Build purchase-price map from Products by part_no
        $lineItems   = collect($invoice->invoice_products ?? []);
        $partNosList = $lineItems->pluck('part_no')->filter()->unique()->values();

        $products = Product::whereIn('part_no', $partNosList)->get(['part_no','purchase_price','unit_price']);
        $purchaseMap = $products->mapWithKeys(function ($p) {
            $purchase = (float) ($p->purchase_price ?? $p->unit_price ?? 0);
            return [$p->part_no => $purchase];
        });

        // Pick only those part_nos where billed rate < purchase price
        $underPricedPartNos = [];
        foreach ($lineItems as $li) {
            $pn   = (string) ($li->part_no ?? '');
            if ($pn === '' || !isset($purchaseMap[$pn])) continue;

            $rate     = (float) ($li->rate ?? 0);
            $purchase = (float) $purchaseMap[$pn];

            if ($rate < $purchase) {
                $underPricedPartNos[$pn] = $pn; // keep unique
            }
        }
        $partNos = implode(', ', array_values($underPricedPartNos)) ?: 'N/A'; // only highlighted

        // --- B) PDF (use forced-challan PDF if available, else fallback) ---
        $pdfURl = $this->getChallanPdfURLByInvoice($invoice->id);
       

        $invoiceNo   = $invoice->invoice_no;
        $invoiceDate = $invoice->created_at->format('d-m-Y');

        // --- C) Recipients (base) ---
        $rawRecipients = [
            '9894753728',
            '9709555576',
            '9930791952',
            '9730377752',
        ];

        // Warehouse-based additions
        $whId = (int) $invoice->warehouse_id;
        if ($whId === 2) {
            $rawRecipients[] = '8210259914';
        } elseif ($whId === 6) {
            $rawRecipients[] = '9860433981';
        }

        // Normalize to +91XXXXXXXXXX and dedupe
        $recipients = collect($rawRecipients)
            ->filter()
            ->map(function ($n) {
                $digits = preg_replace('/\D/', '', (string) $n);
                if (strlen($digits) === 10) $digits = '91' . $digits;
                if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
                    return '+' . $digits;
                }
                return null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        // --- D) Upload PDF once ---
        $WhatsAppWebService = new WhatsAppWebService();
        $media = $WhatsAppWebService->uploadMedia($pdfURl);
        $mediaId = is_array($media) ? ($media['media_id'] ?? $media['id'] ?? null) : null;

        if (!$mediaId) {
            \Log::error('[WA] Media upload failed for invoice '.$invoice->id, ['response' => $media]);
            return;
        }

        // --- E) Template payload (part nos = highlighted only) ---
        $templateData = [
            'name'      => 'force_invoice_notice',
            'language'  => 'en_US',
            'components'=> [
                [
                    'type'       => 'header',
                    'parameters' => [[
                        'type'     => 'document',
                        'document' => [
                            'filename' => "Invoice - {$invoiceNo}.pdf",
                            'id'       => $mediaId,
                        ],
                    ]],
                ],
                [
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $invoiceNo],   // {{1}} Invoice No
                        ['type' => 'text', 'text' => $partNos],     // {{2}} Part Nos (UNDER-PRICED ONLY)
                        ['type' => 'text', 'text' => $invoiceDate], // {{3}} Invoice Date
                    ],
                ],
            ],
        ];

        // --- F) Send ---
        foreach ($recipients as $to) {
            try {
                $resp = $WhatsAppWebService->sendTemplateMessage($to, $templateData);
                \Log::info('[WA] Force invoice notice sent', [
                    'to' => $to,
                    'invoice_id' => $invoice->id,
                    'resp' => $resp
                ]);
            } catch (\Throwable $e) {
                \Log::error('[WA] Send failed', [
                    'to' => $to,
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public function __sendForcefullyCreatedInvoiceNotification($invoiceId){
        $invoice = InvoiceOrder::with(['invoice_products', 'warehouse', 'user'])->findOrFail($invoiceId);
    
        // 1) Prepare values
        $pdfURl     = $this->getChallanPdfURLByInvoice($invoice->id);
        $invoiceNo  = $invoice->invoice_no;
        $invoiceDate= $invoice->created_at->format('d-m-Y');
        $partNos    = $invoice->invoice_products->pluck('part_no')->unique()->implode(', ');
    
        // 2) Recipients — add more here if needed
        $rawRecipients = [
            // If you also want the customer/manager, push them here:
            // optional: $invoice->user?->phone,
            '9894753728',   // existing
            '9709555576',
            '9930791952',
            '9730377752',
        ];
    
        // Normalize to +91XXXXXXXXXX and dedupe
        $recipients = collect($rawRecipients)
            ->filter() // remove null/empty
            ->map(function ($n) {
                $digits = preg_replace('/\D/', '', (string) $n);         // keep only digits
                if (strlen($digits) === 10) $digits = '91' . $digits;     // assume India local -> add 91
                if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
                    return '+' . $digits;                                 // E.164
                }
                return null; // drop invalids
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    
        // 3) Upload PDF once
        $WhatsAppWebService = new WhatsAppWebService();
        $media = $WhatsAppWebService->uploadMedia($pdfURl);
        $mediaId = is_array($media) ? ($media['media_id'] ?? $media['id'] ?? null) : null;
    
        if (!$mediaId) {
            \Log::error('[WA] Media upload failed for invoice '.$invoice->id, ['response' => $media]);
            return; // or throw
        }
    
        // 4) Template payload (reuse for each recipient)
        $templateData = [
            'name'      => 'force_invoice_notice',
            'language'  => 'en_US',
            'components'=> [
                [
                    'type'       => 'header',
                    'parameters' => [[
                        'type'     => 'document',
                        'document' => [
                            'filename' => "Invoice - {$invoiceNo}.pdf",
                            'id'       => $mediaId,
                        ],
                    ]],
                ],
                [
                    'type'       => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $invoiceNo],   // {{1}} Invoice No
                        ['type' => 'text', 'text' => $partNos],     // {{2}} Part Nos
                        ['type' => 'text', 'text' => $invoiceDate], // {{3}} Invoice Date
                    ],
                ],
            ],
        ];
    
        // 5) Send to all recipients
        foreach ($recipients as $to) {
            try {
                $resp = $WhatsAppWebService->sendTemplateMessage($to, $templateData);
                \Log::info('[WA] Force invoice notice sent', ['to' => $to, 'invoice_id' => $invoice->id, 'resp' => $resp]);
            } catch (\Throwable $e) {
                \Log::error('[WA] Send failed', ['to' => $to, 'invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            }
        }
    }


    public function testForcefullyCreatedInvoiceWhatsApp()
    {
        $invoiceId = 2707;

        try {
            $this->sendForcefullyCreatedInvoiceNotification($invoiceId);
            return response()->json(['status' => 'success', 'message' => 'Test WhatsApp sent for invoice ID ' . $invoiceId]);
        } catch (\Exception $e) {
            \Log::error('WhatsApp test failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed: ' . $e->getMessage()]);
        }
    }


    public function saveFromChallans(Request $request)
    {
        $userId = Auth::id();

        $isForced = $request->input('force_create') == 1;
        $early_payment_check = $request->input('early_payment_check');

        $challanIds = explode(',', $request->challan_ids);

        $challans = Challan::with(['challan_details.product_data'])
            ->whereIn('id', $challanIds)->get();

        // ✅ Block duplicate invoice on active challans (simple guard)
        $existingInvoice = InvoiceOrder::where('challan_id', $challanIds)
            ->where('invoice_cancel_status', 0)
            ->first();

        if ($existingInvoice) {
            return response()->json(['status' => 'error', 'message' => 'Invoice already exists for the provided challan IDs.']);
        }

        if ($challans->isEmpty()) {
            return back()->with('error', 'No challans found.');
        }

        $firstChallan = $challans->first();

        $user = User::find($firstChallan->user_id);
        $partyCode = $user ? $user->party_code : null;

        $warehouse = Warehouse::with('state')->find($firstChallan->warehouse_id);
        $warehouseCode = $warehouse ? strtoupper(substr($warehouse->name, 0, 3)) : 'WH';

        $lastInvoice = InvoiceOrder::where('warehouse_id', $firstChallan->warehouse_id)
            ->where('invoice_no', 'LIKE', "$warehouseCode/%")
            ->orderBy('id', 'desc')
            ->first();

        $lastNumber = 0;
        if ($lastInvoice) {
            $parts = explode('/', $lastInvoice->invoice_no);
            $lastNumber = isset($parts[1]) ? (int)$parts[1] : 0;
        }

        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        $invoiceNo = $warehouseCode . '/' . $newNumber . '/25-26';

        $shippingAddressId = $firstChallan->shipping_address_id;
        $shippingAddress = Address::find($shippingAddressId);
        $shippingStateId = $shippingAddress->state_id ?? null;
        $shippingStateName = $shippingStateId ? optional(State::find($shippingStateId))->name : null;
        $warehouseStateName = $warehouse->state->name ?? null;

        $invoice = new InvoiceOrder();
        $invoice->party_code = $partyCode;
        $invoice->invoice_no = $invoiceNo;
        $invoice->warehouse_id = $firstChallan->warehouse_id;
        $invoice->user_id = $firstChallan->user_id;
        $invoice->party_info = $firstChallan->shipping_address;
        $invoice->transport_name = $firstChallan->transport_name;
        $invoice->transport_id = $firstChallan->transport_id;
        $invoice->challan_no = implode(',', $challans->pluck('challan_no')->toArray());
        $invoice->challan_id = implode(',', $challans->pluck('id')->toArray());
        $invoice->sub_order_id = implode(',', $challans->pluck('sub_order_id')->unique()->toArray());
        $invoice->shipping_address_id = $shippingAddressId;
        $invoice->early_payment_check = $request->early_payment_check;
        $invoice->is_warranty = $challans->contains(fn($c) => $c->challan_details->contains('is_warranty', 1)) ? 1 : 0;
        $invoice->conveince_fee_percentage = $firstChallan->conveince_fee_percentage;
        $invoice->conveince_fee_payment_check = $firstChallan->conveince_fee_payment_check;
        $invoice->save();

        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;

        $grandTotalInclTaxProducts = 0.0; // products only, inclusive of their GST (from challan)
        $subtotalProducts          = 0.0; // products ex-tax base

        // ✅ Accumulate convenience fee (assumed GST-inclusive line sums from challan details)
        $convFeeTotal = 0.0;

        foreach ($challans as $challan) {
            foreach ($challan->challan_details as $detail) {
                $product = $detail->product_data;
                if (!$product) continue;

                $gstPercent = (float)($product->tax ?? 0);
                $rate = is_numeric($detail->rate) ? (float)$detail->rate : 0.0;
                $qty  = is_numeric($detail->quantity) ? (float)$detail->quantity : 0.0;

                // ex-tax price and amount
                $price    = round($rate / (1 + ($gstPercent / 100)), 2);
                $grossAmt = round($price * $qty, 2);

                $cgst = 0.0; $sgst = 0.0; $igst = 0.0;

                // Product GST split
                if ($shippingStateName && $shippingStateName === $warehouseStateName) {
                    $cgst = round(($grossAmt * ($gstPercent / 2)) / 100, 2);
                    $sgst = round(($grossAmt * ($gstPercent / 2)) / 100, 2);
                    $totalCgst += $cgst;
                    $totalSgst += $sgst;
                } else {
                    $igst = round(($grossAmt * $gstPercent) / 100, 2);
                    $totalIgst += $igst;
                }

                $grandTotalInclTaxProducts += (float)$detail->final_amount; // inclusive (as on challan)
                $subtotalProducts          += $grossAmt;                    // ex-tax base

                // ✅ add line convenience fee (GST-inclusive value as saved on challan line)
                $convFeeTotal += (float)($detail->conveince_fees ?? 0);

                // Create invoice detail
                $iod = InvoiceOrderDetail::create([
                    'invoice_order_id'         => $invoice->id,
                    'part_no'                  => $product->part_no,
                    'item_name'                => $product->name,
                    'hsn_no'                   => $product->hsncode,
                    'gst'                      => $gstPercent,
                    'billed_qty'               => $qty,
                    'rate'                     => $rate,
                    'billed_amt'               => round($rate * $qty, 0),
                    'challan_no'               => $challan->challan_no,
                    'challan_id'               => $challan->id,
                    'sub_order_id'             => $challan->sub_order_id,
                    'sub_order_details_id'     => $detail->sub_order_details_id,
                    'cgst'                     => $cgst,
                    'sgst'                     => $sgst,
                    'igst'                     => $igst,
                    'price'                    => $price,
                    'gross_amt'                => $grossAmt,
                    'is_warranty'              => (int)($detail->is_warranty == 1),
                    'barcode'                  => (($b = trim((string)($detail->barcode ?? ''))) !== '' ? $b : null),
                    'conveince_fee_percentage' => $detail->conveince_fee_percentage,
                    'conveince_fees'           => $detail->conveince_fees,
                ]);

                // Punch barcodes (comma/newline)
                $codes = array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)$detail->barcode)));
                foreach ($codes as $code) {
                    Barcode::updateOrCreate(
                        ['barcode' => $code],
                        [
                            'is_warranty'              => (int)($detail->is_warranty == 1),
                            'invoice_order_id'         => $invoice->id,
                            'invoice_order_details_id' => $iod->id,
                        ]
                    );
                }

                // Inventory entry
                $productID = Product::where('part_no', $product->part_no)->value('id');
                $requestSubmit = new \Illuminate\Http\Request();
                $requestSubmit->merge(['product_id' => $productID]);
                $this->inventoryProductEntry($requestSubmit);
            }
        }

        // ===== Convenience Fee GST handling (split) =====
        // convFeeTotal is GST-inclusive; extract GST and base:
        // GST = (inclusive / 1.18) * 0.18 ; BASE = inclusive - GST
        $convFeeGst  = round((($convFeeTotal / 1.18) * 0.18), 2);  // <-- FIXED per your note
        $convFeeBase = round($convFeeTotal - $convFeeGst, 2);

        if ($convFeeGst > 0) {
            if ($shippingStateName && $shippingStateName === $warehouseStateName) {
                $convCGST = round($convFeeGst / 2, 2);
                $convSGST = $convFeeGst - $convCGST; // keep sum exact
                $totalCgst += $convCGST;
                $totalSgst += $convSGST;
            } else {
                $totalIgst += $convFeeGst;
            }
        }

        // ✅ Totals before rewards (DO NOT double count fee GST)
        $invoice->total_cgst = $totalCgst;
        $invoice->total_sgst = $totalSgst;
        $invoice->total_igst = $totalIgst;

        // Grand total = products ex-tax + all taxes (incl. fee GST) + fee BASE (ex-tax part)
        // $invoice->grand_total = round($subtotalProducts + $totalCgst + $totalSgst + $totalIgst + $convFeeBase, 0);
        $grandTotalPaise = round(
            ($subtotalProducts + $totalCgst + $totalSgst + $totalIgst + $convFeeBase) * 100
        ); // integer paise

        // 2. Convert to rupees and round to nearest whole rupee
        $roundedRupees = round($grandTotalPaise / 100); // e.g. 118.52 → 119

        // 3. Store as "119.00" in decimal column
        $invoice->grand_total = number_format($roundedRupees, 2, '.', '');
        $invoice->save();

        // ===== Logistic Rewards (if applicable) =====
        $getRewardsData = RewardUser::where('party_code', $partyCode)
            ->where('warehouse_id', $firstChallan->warehouse_id)
            ->where('preference', '1')
            ->first();

        if ($getRewardsData !== null) {
            $getRewardsPointData = RewardPointsOfUser::where('party_code', $partyCode)
                ->where('invoice_no', $invoiceNo)
                ->first();

            if ($getRewardsPointData == null) {
                // Rewards based on products inclusive amount (business rule from earlier code)
                $rewards = floor(($grandTotalInclTaxProducts * $getRewardsData->rewards_percentage) / 100);

                $rewardPoint = new RewardPointsOfUser();
                $rewardPoint->party_code     = $partyCode;
                $rewardPoint->invoice_no     = $invoiceNo;
                $rewardPoint->rewards_from   = 'Logistic';
                $rewardPoint->warehouse_id   = $warehouse->id;
                $rewardPoint->warehouse_name = $warehouse->name;
                $rewardPoint->rewards        = $rewards;
                $rewardPoint->save();

                $invoice->rewards_from = 'Logistic';
                $invoice->rewards_discount = $rewards;

                // Reduce taxes proportionally for reward (assumed GST@18% on reward)
                if ($rewards > 0) {
                    $taxAmount = round(($rewards * 18) / 100, 2);

                    if ($shippingStateName && $shippingStateName === $warehouseStateName) {
                        $halfTax = round($taxAmount / 2, 2);
                        $totalCgst -= $halfTax;
                        $totalSgst -= $halfTax;
                    } else {
                        $totalIgst -= $taxAmount;
                    }

                    $subtotalProducts -= $rewards; // ex-tax base reduced by reward
                }

                // Recompute final totals (still add only fee BASE, GST of fee already inside totals)
                $invoice->total_cgst  = $totalCgst;
                $invoice->total_sgst  = $totalSgst;
                $invoice->total_igst  = $totalIgst;
                // $invoice->grand_total = round($subtotalProducts + $totalCgst + $totalSgst + $totalIgst + $convFeeBase, 0);
                $grandTotalPaise = round(
                    ($subtotalProducts + $totalCgst + $totalSgst + $totalIgst + $convFeeBase) * 100
                ); // integer paise

                // 2. Convert to rupees and round to nearest whole rupee
                $roundedRupees = round($grandTotalPaise / 100); // e.g. 118.52 → 119

                // 3. Store as "119.00" in decimal column
                $invoice->grand_total = number_format($roundedRupees, 2, '.', '');
                $invoice->save();

                // WhatsApp notification for rewards
                $this->sendLogisticRewardWhatsAppNotification($invoice->id);
            }
        }

        Challan::whereIn('id', $challanIds)->update(['invoice_status' => 1]);

        if ($isForced) {
            $this->sendForcefullyCreatedInvoiceNotification($invoice->id);
        }

        // Push to Zoho (kept as-is)
        $zohoController = new ZohoController();
        $response = $zohoController->createInvoice($invoice->id);
        if (isset($response['code']) && $response['code'] !== 0) {
            \Log::error('Zoho Invoice Error', $response);
        }

        return redirect()->route('order.allChallan')->with('success_msg', 'Invoice created successfully.');
    }




    public function updateFromChallans($invoiceId)
{
    $invoice = InvoiceOrder::with('invoice_products')->findOrFail($invoiceId);

    $challanIds = explode(',', $invoice->challan_id);

    $challans = Challan::with(['challan_details.product_data'])
        ->whereIn('id', $challanIds)->get();

    if ($challans->isEmpty()) {
        return back()->with('error', 'No challans found.');
    }

    // Delete old details
    InvoiceOrderDetail::where('invoice_order_id', $invoice->id)->delete();

    $firstChallan = $challans->first();
    $shippingAddress = Address::find($firstChallan->shipping_address_id);
    $warehouse = Warehouse::with('state')->find($firstChallan->warehouse_id);

    $shippingStateName = optional(optional($shippingAddress)->state)->name;
    $warehouseStateName = optional($warehouse->state)->name;

    $totalCgst = $totalSgst = $totalIgst = $grandTotal = $subtotal = 0;

    foreach ($challans as $challan) {
        foreach ($challan->challan_details as $detail) {
            $product = $detail->product_data;

            if ($product) {
                $gstPercent = (float)($product->tax ?? 0);
                $rate = is_numeric($detail->rate) ? (float)$detail->rate : 0;
                $qty = is_numeric($detail->quantity) ? (float)$detail->quantity : 0;



                $price = round($rate / (1 + ($gstPercent / 100)), 2);
                $grossAmt = round($price * $qty, 2);

                $cgst = $sgst = $igst = 0;

                if ($shippingStateName === $warehouseStateName) {
                    $cgst = round(($grossAmt * ($gstPercent / 2)) / 100, 2);
                    $sgst = round(($grossAmt * ($gstPercent / 2)) / 100, 2);
                    $totalCgst += $cgst;
                    $totalSgst += $sgst;
                } else {
                    $igst = round(($grossAmt * $gstPercent) / 100, 2);
                    $totalIgst += $igst;
                }

                $grandTotal += $detail->final_amount;
                $subtotal += $grossAmt;


                //  echo "<pre>";
                // print_r([
                //     'invoice_order_id'      => $invoice->id,
                //     'part_no'               => $product->part_no,
                //     'item_name'             => $product->name,
                //     'hsn_no'                => $product->hsncode,
                //     'gst'                   => $gstPercent,
                //     'billed_qty'            => $qty,
                //     'rate'                  => $rate,
                //     'billed_amt'            => round($rate * $qty, 0),
                //     'challan_no'            => $challan->challan_no,
                //     'challan_id'            => $challan->id,
                //     'sub_order_id'          => $challan->sub_order_id,
                //     'sub_order_details_id'  => $detail->sub_order_details_id,
                //     'cgst'                  => $cgst,
                //     'sgst'                  => $sgst,
                //     'igst'                  => $igst,
                //     'price'                 => $price,
                //     'gross_amt'             => $grossAmt,
                // ]);
                // die();

                InvoiceOrderDetail::create([
                    'invoice_order_id'      => $invoice->id,
                    'part_no'               => $product->part_no,
                    'item_name'             => $product->name,
                    'hsn_no'                => $product->hsncode,
                    'gst'                   => $gstPercent,
                    'billed_qty'            => $qty,
                    'rate'                  => $rate,
                    'billed_amt'            => round($rate * $qty, 0),
                    'challan_no'            => $challan->challan_no,
                    'challan_id'            => $challan->id,
                    'sub_order_id'          => $challan->sub_order_id,
                    'sub_order_details_id'  => $detail->sub_order_details_id,
                    'cgst'                  => $cgst,
                    'sgst'                  => $sgst,
                    'igst'                  => $igst,
                    'price'                 => $price,
                    'gross_amt'             => $grossAmt,
                ]);
            }
        }
    }

    $invoice->total_cgst = $totalCgst;
    $invoice->total_sgst = $totalSgst;
    $invoice->total_igst = $totalIgst;
    // $invoice->grand_total = round($subtotal + $totalCgst + $totalSgst + $totalIgst, 0);
    $grandTotalPaise = round(
        ($subtotal + $totalCgst + $totalSgst + $totalIgst) * 100
    ); // integer paise

    // 2. Convert to rupees and round to nearest whole rupee
    $roundedRupees = round($grandTotalPaise / 100); // e.g. 118.52 → 119

    // 3. Store as "119.00" in decimal column
    $invoice->grand_total = number_format($roundedRupees, 2, '.', '');
    $invoice->save();
    echo "Success";
    die();

    return redirect()->route('order.allChallan')->with('success_msg', 'Invoice updated successfully.');
}


    public function getInvoiceOrderPdfUrl($id)
    {
        $invoice = InvoiceOrder::with('invoice_products')->findOrFail($id);
        $eway = EwayBill::where('invoice_order_id', $id)->first();

        if (is_string($invoice->party_info)) {
            $invoice->party_info = json_decode($invoice->party_info, true);
        }

        $shipping = Address::find($invoice->shipping_address_id);

        $billingDetails = (object) [
            'company_name' => $invoice->party_info['company_name'] ?? 'N/A',
            'address' => $invoice->party_info['address'] ?? 'N/A',
            'gstin' => $invoice->party_info['gstin'] ?? 'N/A',
        ];

        $manager_phone = '9999241558';

        $branchMap = [
            1 => 'KOL',
            2 => 'DEL',
            6 => 'MUM'
        ];

        $branchDetailsAll = [
            'KOL' => [
                'gstin' => '19ABACA4198B1ZS',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => '257B, BIPIN BEHARI GANGULY STREET',
                'address_2' => '2ND FLOOR',
                'address_3' => '',
                'city' => 'KOLKATA',
                'state' => 'WEST BENGAL',
                'postal_code' => '700012',
                'contact_name' => 'Amir Madraswala',
                'phone' => '9709555576',
                'email' => 'acetools505@gmail.com',
            ],
            'MUM' => [
                'gstin' => '27ABACA4198B1ZV',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => 'HARIHAR COMPLEX F-8, HOUSE NO-10607, ANUR DEPODE ROAD',
                'address_2' => 'GODOWN NO.7, GROUND FLOOR',
                'address_3' => 'BHIWANDI',
                'city' => 'MUMBAI',
                'state' => 'MAHARASHTRA',
                'postal_code' => '421302',
                'contact_name' => 'Hussain',
                'phone' => '9930791952',
                'email' => 'acetools505@gmail.com',
            ],
            'DEL' => [
                'gstin' => '07ABACA4198B1ZX',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => 'Khasra No. 58/15',
                'address_2' => 'Pal Colony',
                'address_3' => 'Village Rithala',
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'postal_code' => '110085',
                'contact_name' => 'Mustafa Worliwala',
                'phone' => '9730377752',
                'email' => 'acetools505@gmail.com',
            ],
        ];

        $branchCode = $branchMap[$invoice->warehouse_id] ?? 'DEL';
        $branchDetails = $branchDetailsAll[$branchCode] ?? [];

        $logistic = OrderLogistic::where('invoice_no', $invoice->invoice_no)->orderByDesc('id')->first();

        $pdfContentService = new PdfContentService();
        $pdfContentBlock   = $pdfContentService->buildBlockForType('invoice');

        $pdf = PDF::loadView('backend.sales.invoice_pdf', compact(
            'invoice',
            'billingDetails',
            'manager_phone',
            'branchDetails',
            'shipping',
            'logistic',
            'eway',
            'pdfContentBlock' // ✅ Blade me use hoga
        ));

        $pdfDir = public_path('purchase_history_invoice');
        if (!file_exists($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }

        $fileName = str_replace('/', '_', $invoice->invoice_no) . '.pdf';
        $filePath = $pdfDir . '/' . $fileName;

        $pdf->save($filePath);

        return url('public/purchase_history_invoice/' . $fileName);
    }


    public function sendLogisticRewardWhatsAppNotification($invoiceId)
    {
        $invoice = InvoiceOrder::with('warehouse', 'address')->findOrFail($invoiceId);

        // Decode JSON if party_info is stored as JSON
        if (is_string($invoice->party_info)) {
            $invoice->party_info = json_decode($invoice->party_info, true);
        }

        $companyName = $invoice->party_info['company_name'] ?? 'N/A'; // {{1}}
        $rewardAmount = $invoice->rewards_discount ?? 0;             // {{2}}

        $invoiceNo = $invoice->invoice_no;                           // {{3}}
        $invoiceDate = $invoice->created_at->format('d-m-Y');        // {{4}}
        $invoiceAmount = $invoice->grand_total ?? 0;                 // {{5}}
        $rewardType = "Transport Discount";          // {{6}}

        // Define sales rep per branch (can be improved via DB)
        $userDetails = User::find($invoice->user_id);
        $manager_phone = $this->getManagerPhone($userDetails->manager_id ?? null);

        // Generate invoice PDF URL
        $pdfUrl = $this->getInvoicePdfURL($invoiceId);

        $WhatsAppWebService = new WhatsAppWebService();
        $media_id = $WhatsAppWebService->uploadMedia($pdfUrl);
        $placeOfDispatch = $invoice->warehouse->name ?? 'N/A';

        $templateData = [
            'name' => 'utility_transport_discount',
            'language' => 'en_US',
            'components' => [
                [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'document',
                            'document' => [
                                'filename' => "transport_discount.pdf",
                                'id' => $media_id['media_id'],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $companyName],          // {{1}}
                        ['type' => 'text', 'text' =>  $rewardAmount],   // {{2}}
                        ['type' => 'text', 'text' => $invoiceNo],            // {{3}}
                        ['type' => 'text', 'text' => $invoiceDate],          // {{4}}
                        ['type' => 'text', 'text' =>  $invoiceAmount],  // {{5}}
                        ['type' => 'text', 'text' =>  $placeOfDispatch],  // {{6}}
                        ['type' => 'text', 'text' => $rewardType],           // {{7}}
                        ['type' => 'text', 'text' => $manager_phone],             // {{8}}
                    ]
                ]
            ]
        ];

        // Get recipient number from address table
        $recipientNumber = $invoice->address->phone ?? null;

        if (!$recipientNumber) {
            return response()->json([
                'status' => 'failed',
                'message' => 'No phone number found in shipping address.'
            ]);
        }

        $res = $WhatsAppWebService->sendTemplateMessage($recipientNumber, $templateData);

        return response()->json([
            'status' => 'Reward WhatsApp sent',
            'template_data' => $templateData,
            'whatsapp_response' => $res
        ]);
    }


    

    public function updateInvoiceFromChallans($invoiceId)
    {
        $invoice = InvoiceOrder::findOrFail($invoiceId);
        $challanIds = explode(',', $invoice->challan_id); // Get challans from invoice

        $challans = Challan::with(['challan_details.product_data'])
            ->whereIn('id', $challanIds)->get();

        if ($challans->isEmpty()) {
            return back()->with('error', 'No challans found.');
        }

        // Reset values
        $subtotal = 0;
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;
        $grandTotal = 0;
        // Delete old invoice details
        InvoiceOrderDetail::where('invoice_order_id', $invoiceId)->delete();

        $shippingAddress = Address::find($invoice->shipping_address_id);
        $shippingState = optional(State::find($shippingAddress->state_id))->name ?? null;

        $warehouse = Warehouse::with('state')->find($invoice->warehouse_id);
        $warehouseState = $warehouse->state->name ?? null;

        foreach ($challans as $challan) {
            foreach ($challan->challan_details as $detail) {
                $product = $detail->product_data;
                if (!$product) continue;

                $gstPercent = (float)($product->tax ?? 0);
                $rate = (float)$detail->rate;
                $qty = (float)$detail->quantity;

                $price = round($rate / (1 + ($gstPercent / 100)), 2);
                $grossAmt = round($price * $qty, 2);

                $cgst = 0; $sgst = 0; $igst = 0;

                if ($shippingState === $warehouseState) {
                    $cgst = round(($grossAmt * ($gstPercent / 2)) / 100, 2);
                    $sgst = round(($grossAmt * ($gstPercent / 2)) / 100, 2);
                    $totalCgst += $cgst;
                    $totalSgst += $sgst;
                } else {
                    $igst = round(($grossAmt * $gstPercent) / 100, 2);
                    $totalIgst += $igst;
                }

                $grandTotal += $detail->final_amount;
                $subtotal += $grossAmt;

                InvoiceOrderDetail::create([
                    'invoice_order_id'      => $invoice->id,
                    'part_no'               => $product->part_no,
                    'item_name'             => $product->name,
                    'hsn_no'                => $product->hsncode,
                    'gst'                   => $gstPercent,
                    'billed_qty'            => $qty,
                    'rate'                  => $rate,
                    'billed_amt'            => $detail->final_amount,
                    'challan_no'            => $challan->challan_no,
                    'challan_id'            => $challan->id,
                    'sub_order_id'          => $challan->sub_order_id,
                    'sub_order_details_id'  => $detail->sub_order_details_id,
                    'cgst'                  => $cgst,
                    'sgst'                  => $sgst,
                    'igst'                  => $igst,
                    'price'                 => $price,
                    'gross_amt'             => $grossAmt,
                ]);
            }
        }

        // Reward logic
        $rewards = 0;
        $rewardData = RewardUser::where('party_code', $invoice->party_code)
            ->where('warehouse_id', $invoice->warehouse_id)
            ->where('preference', '1')->first();

        if ($rewardData) {
            $rewards = floor(($grandTotal * $rewardData->rewards_percentage) / 100);
            //$rewardBase = round($rewards / 1.18, 2);
            $taxOnReward = round($rewards * 0.18, 2);

            if ($shippingState === $warehouseState) {
                $half = round($taxOnReward / 2, 2);
                $totalCgst -= $half;
                $totalSgst -= $half;
            } else {
                $totalIgst -= $taxOnReward;
            }

            $subtotal -= $rewards;

            RewardPointsOfUser::updateOrCreate(
                ['party_code' => $invoice->party_code, 'invoice_no' => $invoice->invoice_no],
                [
                    'rewards' => $rewards,
                    'warehouse_id' => $invoice->warehouse_id,
                    'warehouse_name' => $warehouse->name,
                    'rewards_from' => 'Logistic',
                ]
            );
            $invoice->rewards_from = 'Logistic';
            $invoice->rewards_discount = $rewards;
        }

        // Update invoice final values
        $invoice->total_cgst = $totalCgst;
        $invoice->total_sgst = $totalSgst;
        $invoice->total_igst = $totalIgst;
        // $invoice->grand_total = round($subtotal + $totalCgst + $totalSgst + $totalIgst, 0); 12.04
        $grandTotalPaise = round(
            ($subtotal + $totalCgst + $totalSgst + $totalIgst) * 100
        ); 
        // 2. Convert to rupees and round to nearest whole rupee
        $roundedRupees = round($grandTotalPaise / 100); // e.g. 118.52 → 119

        // 3. Store as "119.00" in decimal column
        $invoice->grand_total = number_format($roundedRupees, 2, '.', '');
        $invoice->save();

        return redirect()->route('order.allChallan')->with('success_msg', 'Invoice updated successfully.');
    }


    public function invoicedOrders(Request $request)
{
    $user = auth()->user();

    if (! $user) {
        return back()->with('error', 'User not authenticated.');
    }

    // ✅ Base query: sirf invoice_orders + relations, koi order_logistics join nahi
    $query = InvoiceOrder::query()
        ->with(['address', 'shipping_address', 'warehouse'])
        ->select('invoice_orders.*');

    // ✅ Non-admin users ko unke warehouse tak restrict karo
    if ($user->id !== 1) {
        if (! $user->warehouse_id) {
            return back()->with('error', 'Warehouse information not found for this user.');
        }

        $query->where('invoice_orders.warehouse_id', $user->warehouse_id);
    }

    // ✅ Search
    $search = trim((string) $request->get('search', ''));
    if ($search !== '') {
        $lowerSearch = strtolower($search);

        $query->where(function ($q) use ($lowerSearch, $search) {

            // 🔹 1) UNSYNC → Zoho invoice id missing
            if ($lowerSearch === 'unsync') {
                $q->where(function ($sub) {
                    $sub->whereNull('invoice_orders.zoho_invoice_id')
                        ->orWhere('invoice_orders.zoho_invoice_id', '');
                });
                return;
            }

            // 🔹 2) NOGSTIN → address me gstin khali
            if ($lowerSearch === 'nogstin') {
                $q->whereExists(function ($subQuery) {
                    $subQuery->select(DB::raw(1))
                        ->from('addresses')
                        // ⚠️ party_code = acc_code with same COLLATE to avoid error
                        ->whereRaw(
                            'invoice_orders.party_code COLLATE utf8mb3_general_ci = ' .
                            'addresses.acc_code COLLATE utf8mb3_general_ci'
                        )
                        ->where(function ($where) {
                            $where->whereNull('addresses.gstin')
                                  ->orWhere('addresses.gstin', '');
                        });
                });
                return;
            }

            // 🔹 3) Normal text search
            $q->where(function ($sub) use ($search) {
                // Invoice / Challan
                $sub->where('invoice_orders.invoice_no', 'like', '%' . $search . '%')
                    ->orWhere('invoice_orders.challan_no', 'like', '%' . $search . '%');

                // Warehouse name (safe: int join)
                $sub->orWhereHas('warehouse', function ($w) use ($search) {
                    $w->where('name', 'like', '%' . $search . '%');
                });

                // Address: company_name / acc_code / city (manual EXISTS with COLLATE)
                $sub->orWhereExists(function ($addrQuery) use ($search) {
                    $addrQuery->select(DB::raw(1))
                        ->from('addresses')
                        ->whereRaw(
                            'invoice_orders.party_code COLLATE utf8mb3_general_ci = ' .
                            'addresses.acc_code COLLATE utf8mb3_general_ci'
                        )
                        ->where(function ($where) use ($search) {
                            $where->where('company_name', 'like', '%' . $search . '%')
                                  ->orWhere('acc_code', 'like', '%' . $search . '%')
                                  ->orWhere('city', 'like', '%' . $search . '%');
                        });
                });
            });
        });
    }

    // ✅ Ab sirf invoice_orders par paginate (bohot light ho gaya)
    $invoices = $query
        ->orderByDesc('invoice_orders.id')
        ->paginate(20)
        ->appends($request->all());

    // --------------------------------------------------
    // ⚡ Step 2: current page ke invoices ke liye hi logistics lao
    // --------------------------------------------------
    if ($invoices->count() > 0) {
        $invoiceNos = $invoices->pluck('invoice_no')->filter()->unique();

        if ($invoiceNos->isNotEmpty()) {
            // Har invoice_no ka latest logistics id
            $latestLogisticsSub = DB::table('order_logistics')
                ->selectRaw('MAX(id) as id, invoice_no')
                ->whereIn('invoice_no', $invoiceNos)
                ->groupBy('invoice_no');

            // Un latest rows se add_status map banao
            $logisticsMap = DB::table('order_logistics as ol')
                ->joinSub($latestLogisticsSub, 'latest_ol', function ($join) {
                    $join->on('ol.id', '=', 'latest_ol.id');
                })
                ->pluck('ol.add_status', 'latest_ol.invoice_no');

            // Map ko InvoiceOrder objects par inject karo
            $invoices->getCollection()->transform(function ($inv) use ($logisticsMap) {
                $inv->add_status = $logisticsMap[$inv->invoice_no] ?? null;
                return $inv;
            });
        }
    }

    $warehouses = Warehouse::whereNotNull('eway_address_id')->get();
    $states     = State::where('status', 1)->orderBy('name')->get();

    return view('backend.sales.invoiced_orders', compact('invoices', 'warehouses', 'states'));
}

    public function btr1Receipts(Request $request)
    {
        $user = auth()->user();

        if (!$user || !$user->warehouse_id) {
            return back()->with('error', 'Your warehouse information is missing.');
        }

        $warehouse = Warehouse::find($user->warehouse_id);
        if (!$warehouse || !$warehouse->name) {
            return back()->with('error', 'Warehouse not found.');
        }

        $filter = $request->query('filter', 'pending');
        $city = strtoupper(trim($warehouse->name));
        $targetCompany = 'ACE TOOLS PRIVATE LIMITED - ' . $city;

        $query = InvoiceOrder::with([
            'user',
            'warehouse',
            'shipping_address',
            'invoice_products.subOrder'
        ]);

        if ($filter === 'received') {
            $query->where('btr_received_status', 1);
        } elseif ($filter === 'pending') {
            $query->where('btr_received_status', 0);
        }

        $allInvoices = $query->orderBy('id', 'DESC')->get();

        $invoices = $allInvoices->filter(function ($invoice) use ($targetCompany) {
            $party = json_decode($invoice->party_info, true);
            return strtoupper(trim($party['company_name'] ?? '')) === $targetCompany;
        });

        foreach ($invoices as $inv) {
            $prefix = strtoupper(substr($inv->invoice_no, 0, 3));
            $sellerMap = ['KOL' => 1, 'DEL' => 2, 'MUM' => 5];
            $sellerId = $sellerMap[$prefix] ?? null;

            $shop = $sellerId ? Shop::where('seller_id', $sellerId)->first() : null;
            $inv->party_display_name = $shop->name ?? 'N/A';

            $inv->order_nos = [];

            if (!empty($inv->sub_order_id)) {
                $orderNos = [];

                foreach (explode(',', $inv->sub_order_id) as $id) {
                    $subOrder = SubOrder::find($id);
                    if ($subOrder) {
                        $linked = $subOrder->sub_order_id ? SubOrder::find($subOrder->sub_order_id) : null;
                        $orderNo = $linked->order_no ?? $subOrder->order_no;

                        if (Str::startsWith($orderNo, 'SO/KOL')) {
                            $orderNos[] = $orderNo;
                        }
                    }
                }

                $inv->order_nos = array_unique($orderNos);
            }

            // ✅ Loop through each product and get final order_no via double sub_order lookup
            foreach ($inv->invoice_products as $prod) {
                $saleOrderNo = null;

                if ($prod->subOrder) {
                    $linkedSubOrder = $prod->subOrder->sub_order_id 
                        ? SubOrder::find($prod->subOrder->sub_order_id)
                        : null;

                    $saleOrderNo = $linkedSubOrder ? $linkedSubOrder->order_no : $prod->subOrder->order_no;
                }

                $prod->sale_order_no = $saleOrderNo ?? 'N/A';
            }
        }

        return view('backend.sales.btr_receipts', compact('invoices', 'filter'));
    }

    public function btrReceipts(Request $request)
    {
        $user = auth()->user();

        if (!$user || !$user->warehouse_id) {
            return back()->with('error', 'Your warehouse information is missing.');
        }

        $warehouse = Warehouse::find($user->warehouse_id);
        if (!$warehouse || !$warehouse->name) {
            return back()->with('error', 'Warehouse not found.');
        }

        $filter = $request->query('filter', 'pending'); // default = pending
        $city = strtoupper(trim($warehouse->name));
        $targetCompany = 'ACE TOOLS PRIVATE LIMITED - ' . $city;

        $query = InvoiceOrder::with([
            'user',
            'warehouse',
            'shipping_address',
            'invoice_products.subOrder'
        ]);
        
        $query->where(function ($q) {
            $q->whereNull('invoice_cancel_status')
            ->orWhere('invoice_cancel_status', '!=', 1);
        });

        if ($filter === 'received') {
            $query->where('btr_received_status', 1);
        } elseif ($filter === 'pending') {
            $query->where('btr_received_status', 0);
        }

        $allInvoices = $query->orderBy('id', 'DESC')->get();

        $invoices = $allInvoices->filter(function ($invoice) use ($targetCompany) {
            $party = json_decode($invoice->party_info, true);
            return strtoupper(trim($party['company_name'] ?? '')) === $targetCompany;
        });

        foreach ($invoices as $inv) {
            $prefix = strtoupper(substr($inv->invoice_no, 0, 3));
            $sellerMap = ['KOL' => 1, 'DEL' => 2, 'MUM' => 5];
            $sellerId = $sellerMap[$prefix] ?? null;
            $shop = $sellerId ? Shop::where('seller_id', $sellerId)->first() : null;
            $inv->party_display_name = $shop->name ?? 'N/A';

            // Collect all sale order numbers (no KOL-only filter)
            $inv->order_nos = [];
            if (!empty($inv->sub_order_id)) {
                $orderNos = [];

                foreach (explode(',', $inv->sub_order_id) as $id) {
                    $subOrder = SubOrder::find($id);
                    if ($subOrder) {
                        $linked = $subOrder->sub_order_id ? SubOrder::find($subOrder->sub_order_id) : null;
                        $orderNo = $linked->order_no ?? $subOrder->order_no;
                        $orderNos[] = $orderNo;
                    }
                }

                $inv->order_nos = array_unique($orderNos);
            }

            // Assign sale_order_no and to_company_name for each product
           foreach ($inv->invoice_products as $prod) {
                $saleOrderNo = null;
                $toCompany = null;

                if ($prod->subOrder) {
                    $linkedSubOrder = $prod->subOrder->sub_order_id
                        ? SubOrder::find($prod->subOrder->sub_order_id)
                        : null;

                    $saleOrderNo = $linkedSubOrder ? $linkedSubOrder->order_no : $prod->subOrder->order_no;

                    // ✅ Get company name from users table via sub order's user_id
                    $subOrderUserId = $linkedSubOrder ? $linkedSubOrder->user_id : $prod->subOrder->user_id;
                    $user = User::find($subOrderUserId);
                    $toCompany = $user->company_name ?? 'N/A';
                }

                $prod->sale_order_no = $saleOrderNo ?? 'N/A';
                $prod->to_company_name = $toCompany ?? 'N/A';
            }
        }

        return view('backend.sales.btr_receipts', compact('invoices', 'filter'));
    }

    public function getOrderNosFromSubOrderIds($subOrderIdString)
    {
        $orderNos = [];

        $subOrderIds = explode(',', $subOrderIdString);
        if (!empty($subOrderIds)) {
            foreach ($subOrderIds as $subOrderId) {
                $subOrder = SubOrder::find(trim($subOrderId));
                if ($subOrder) {
                    if ($subOrder->sub_order_id) {
                        $linkedSub = SubOrder::find($subOrder->sub_order_id);
                        if ($linkedSub && $linkedSub->order_no) {
                            $orderNos[] = $linkedSub->order_no;
                        }
                    } elseif ($subOrder->order_no) {
                        $orderNos[] = $subOrder->order_no;
                    }
                }
            }
        }

        return array_unique($orderNos);
    }

    public function getInvoicePdfURL($id)
    {
        $invoice = InvoiceOrder::with('invoice_products')->findOrFail($id);

        if (is_string($invoice->party_info)) {
            $invoice->party_info = json_decode($invoice->party_info, true);
        }

        $shipping = Address::find($invoice->shipping_address_id);

        $billingDetails = (object) [
            'company_name' => $invoice->party_info['company_name'] ?? 'N/A',
            'address' => $invoice->party_info['address'] ?? 'N/A',
            'gstin' => $invoice->party_info['gstin'] ?? 'N/A',
        ];

        $manager_phone = '9999241558';

        $branchMap = [
            1 => 'KOL',
            2 => 'DEL',
            6 => 'MUM'
        ];

        $branchDetailsAll = [
            'KOL' => [
                'gstin' => '19ABACA4198B1ZS',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => '257B, BIPIN BEHARI GANGULY STREET',
                'address_2' => '2ND FLOOR',
                'address_3' => '',
                'city' => 'KOLKATA',
                'state' => 'WEST BENGAL',
                'postal_code' => '700012',
                'contact_name' => 'Amir Madraswala',
                'phone' => '9709555576',
                'email' => 'acetools505@gmail.com',
            ],
            'MUM' => [
                'gstin' => '27ABACA4198B1ZV',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => 'HARIHAR COMPLEX F-8, HOUSE NO-10607, ANUR DEPODE ROAD',
                'address_2' => 'GODOWN NO.7, GROUND FLOOR',
                'address_3' => 'BHIWANDI',
                'city' => 'MUMBAI',
                'state' => 'MAHARASHTRA',
                'postal_code' => '421302',
                'contact_name' => 'Hussain',
                'phone' => '9930791952',
                'email' => 'acetools505@gmail.com',
            ],
            'DEL' => [
                'gstin' => '07ABACA4198B1ZX',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => 'Khasra No. 58/15',
                'address_2' => 'Pal Colony',
                'address_3' => 'Village Rithala',
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'postal_code' => '110085',
                'contact_name' => 'Mustafa Worliwala',
                'phone' => '9730377752',
                'email' => 'acetools505@gmail.com',
            ],
        ];

        $branchCode = $branchMap[$invoice->warehouse_id] ?? 'DEL';
        $branchDetails = $branchDetailsAll[$branchCode] ?? [];

        $logistic = OrderLogistic::where('invoice_no', $invoice->invoice_no)->orderByDesc('id')->first();

        $pdfContentService = new PdfContentService();
        $pdfContentBlock   = $pdfContentService->buildBlockForType('invoice');

        // Generate PDF
        $pdf = PDF::loadView('backend.sales.invoice_pdf', compact(
            'invoice',
            'billingDetails',
            'manager_phone',
            'branchDetails',
            'shipping',
            'logistic',
            'pdfContentBlock' // ✅ Blade me use hoga
        ));

        // Ensure directory exists
        $pdfDir = public_path('purchase_history_invoice');
        if (!file_exists($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }

        // File name and path
        $fileName = str_replace('/', '_', $invoice->invoice_no) . '.pdf';
        $filePath = $pdfDir . '/' . $fileName;

        // Save the PDF to public/pdfs
        $pdf->save($filePath);

        // Return full public URL
        $publicUrl = url('public/purchase_history_invoice/' . $fileName);

        return $publicUrl;
    }


   public function getChallanPdfURLByInvoice($invoiceId)
{
    // 1) Load invoice + its lines
    $invoice = InvoiceOrder::with(['invoice_products', 'warehouse'])->findOrFail($invoiceId);

    // 2) Branch metadata (by warehouse_id)
    $branchMap  = [1 => 'KOL', 2 => 'DEL', 6 => 'MUM'];
    $branchCode = $branchMap[$invoice->warehouse_id] ?? 'DEL';

    $branchDetailsAll = [
        'KOL' => [
            'gstin' => '19ABACA4198B1ZS',
            'company_name' => 'ACE TOOLS PVT LTD',
            'address_1' => '257B, BIPIN BEHARI GANGULY STREET',
            'address_2' => '2ND FLOOR',
            'address_3' => '',
            'city' => 'KOLKATA',
            'state' => 'WEST BENGAL',
            'postal_code' => '700012',
            'contact_name' => 'Amir Madraswala',
            'phone' => '9709555576',
            'email' => 'acetools505@gmail.com',
        ],
        'MUM' => [
            'gstin' => '27ABACA4198B1ZV',
            'company_name' => 'ACE TOOLS PVT LTD',
            'address_1' => 'HARIHAR COMPLEX F-8, HOUSE NO-10607, ANUR DEPODE ROAD',
            'address_2' => 'GODOWN NO.7, GROUND FLOOR',
            'address_3' => 'BHIWANDI',
            'city' => 'MUMBAI',
            'state' => 'MAHARASHTRA',
            'postal_code' => '421302',
            'contact_name' => 'Hussain',
            'phone' => '9930791952',
            'email' => 'acetools505@gmail.com',
        ],
        'DEL' => [
            'gstin' => '07ABACA4198B1ZX',
            'company_name' => 'ACE TOOLS PVT LTD',
            'address_1' => 'Khasra No. 58/15',
            'address_2' => 'Pal Colony',
            'address_3' => 'Village Rithala',
            'city' => 'New Delhi',
            'state' => 'Delhi',
            'postal_code' => '110085',
            'contact_name' => 'Mustafa Worliwala',
            'phone' => '9730377752',
            'email' => 'acetools505@gmail.com',
        ],
    ];
    $branchDetails = $branchDetailsAll[$branchCode];

    // 3) Party/billing info (if present on invoice)
    $partyInfo = $invoice->party_info;
    if (is_string($partyInfo)) {
        $partyInfo = json_decode($partyInfo, true);
    }
    $billingDetails = (object) [
        'company_name' => $partyInfo['company_name'] ?? 'N/A',
        'address'      => $partyInfo['address'] ?? 'N/A',
        'gstin'        => $partyInfo['gstin'] ?? 'N/A',
    ];

    // 4) Shipping + Logistic (optional)
    $shipping = null;
    if (!empty($invoice->shipping_address_id)) {
        $shipping = Address::find($invoice->shipping_address_id);
    }
    $logistic = OrderLogistic::where('invoice_no', $invoice->invoice_no)->orderByDesc('id')->first();

    // 5) Build products purchase price map by part_no
    $lines = collect($invoice->invoice_products ?? []); // relation or attribute
    $partNos = $lines->map(function ($row) {
        return is_array($row) ? ($row['part_no'] ?? null) : ($row->part_no ?? null);
    })->filter()->unique()->values();

    $products = Product::whereIn('part_no', $partNos)->get(['part_no', 'purchase_price', 'unit_price']);
    $productRates = $products->mapWithKeys(function ($p) {
        $key = strtoupper(trim((string) $p->part_no));
        $purchase = (float) ($p->purchase_price ?? $p->unit_price ?? 0);
        return [$key => $purchase];
    })->toArray();

    // 6) Render (Blade filters the lines to ONLY under-priced ones)
    $pdf = PDF::loadView('backend.sales.challan_invoice_pdf', [
        'invoice'       => $invoice,
        'lines'         => $lines,
        'productRates'  => $productRates,   // PARTNO (UPPER/TRIM) => purchase price
        'branchDetails' => $branchDetails,
        'billingDetails'=> $billingDetails,
        'shipping'      => $shipping,
        'logistic'      => $logistic,
    ]);

    // 7) Save under /public/challan_invoices (keeping your existing path)
    $pdfDir = public_path('challan_invoices');
    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0755, true);
    }

    $safeInvoiceNo = Str::of((string)($invoice->invoice_no ?? 'INV'))
                        ->replace('/', '_')->replace('\\', '_');

    $fileName = 'INVOICE_UNDERPRICED_' . time() . '_' . $safeInvoiceNo . '.pdf';
    $pdf->save($pdfDir . DIRECTORY_SEPARATOR . $fileName);

    // 8) Return public URL
    return url('public/challan_invoices/' . $fileName);
}




    public function getCompanyNameFromWarehouseUser()
    {
        $loggedInUser = auth()->user();

        // Step 1: Get warehouse_id
        $warehouseId = $loggedInUser->warehouse_id;

        // Step 2: Find warehouse and its user_id
        $warehouse = Warehouse::find($warehouseId);
        if (!$warehouse || !$warehouse->user_id) {
            return 'Warehouse or assigned user not found';
        }

        // Step 3: Get user from warehouse's user_id
        $targetUser = User::find($warehouse->user_id);
        if (!$targetUser || !$targetUser->company_name) {
            return 'User or company name not found';
        }

        // ✅ Return company name
        return $targetUser->company_name;
    }


    public function sendBTRWhatsApp($invoice){
       
        $pdfURl=$this->downloadBtrPdf($invoice->id);
        $orderNos = $this->getOrderNosFromSubOrderIds($invoice->sub_order_id);
        $companyName=$this->getCompanyNameFromWarehouseUser();
        $WhatsAppWebService = new WhatsAppWebService();
        // 1. Upload PDF
        $media_id = $WhatsAppWebService->uploadMedia($pdfURl);

        // 2. Prepare Template Payload
        $templateData = [
            'name' => 'btr_received', // ✅ Template name
            'language' => 'en_US',
            'components' => [
                [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'document',
                            'document' => [
                                'filename' => "Invoice Number",
                                'id' => $media_id['media_id'],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $companyName, // {{1}} Customer Name
                        ],
                        [
                            'type' => 'text',
                            'text' => $invoice->invoice_no, // {{2}} Invoice No
                        ],
                        [
                            'type' => 'text',
                            'text' => implode(', ', $orderNos), // {{3}} Order Nos
                        ],
                    ],
                ],
            ],
        ];

        // WhatsApp recipients
        $recipients = ['Kolkata' => '8597228356','Delhi'   => '9763268640','Mumbai'  => '9860433981','burahan'=>'9894753728'];
        // 3. Send to each number
        $responses = [];
        foreach ($recipients as $location => $number) {
            $responses[$location] = $WhatsAppWebService->sendTemplateMessage($number, $templateData);
        }
        //$response = $WhatsAppWebService->sendTemplateMessage($to="7044300330", $templateData);
        return response()->json($responses);
       
    }

    public function downloadBtrPdf($invoiceId)
    {
        $invoice = InvoiceOrder::with(['invoice_products', 'shipping_address', 'warehouse'])->findOrFail($invoiceId);

        // Default display name from first product's To company
        $mainCompanyName = null;

        foreach ($invoice->invoice_products as $key => $product) {
            // Get sub-order
            $subOrder = SubOrder::find($product->sub_order_id);
            $linkedSubOrder = $subOrder && $subOrder->sub_order_id
                ? SubOrder::find($subOrder->sub_order_id)
                : null;

            $orderNo = $linkedSubOrder->order_no ?? $subOrder->order_no ?? 'N/A';
            $userId = $linkedSubOrder->user_id ?? $subOrder->user_id ?? null;
            $user = User::find($userId);

            $toCompany = $user->company_name ?? 'N/A';
            $product->sale_order_no = $orderNo;
            $product->to_company_name = $toCompany;

            // Use first product's TO as main company name (if needed)
            if ($key === 0) {
                $mainCompanyName = $toCompany;
            }
        }

        // echo "<pre>";
        // print_r($invoice);
        // die();

        $pdf = PDF::loadView('backend.sales.btr_pdf', [
            'invoice' => $invoice,
            'mainCompanyName' => $mainCompanyName,
        ]);

        $pdfDir = public_path('purchase_history_invoice');
        if (!file_exists($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }

        $fileName = str_replace('/', '_', $invoice->invoice_no).time() . '.pdf';
        $filePath = $pdfDir . '/' . $fileName;

        $pdf->save($filePath);

        return url('public/purchase_history_invoice/' . $fileName);
    }



    public function generateAndStoreInvoicePdfForMarkAsReceived($invoiceId)
    {
        $invoice = InvoiceOrder::with('invoice_products')->findOrFail($invoiceId);
        $eway = EwayBill::where('invoice_order_id', $invoiceId)->first();

        if (is_string($invoice->party_info)) {
            $invoice->party_info = json_decode($invoice->party_info, true);
        }

        $shipping = Address::find($invoice->shipping_address_id);

        $billingDetails = (object) [
            'company_name' => $invoice->party_info['company_name'] ?? 'N/A',
            'address' => $invoice->party_info['address'] ?? 'N/A',
            'gstin' => $invoice->party_info['gstin'] ?? 'N/A',
        ];

        $manager_phone = '9999241558';

        $branchMap = [
            1 => 'KOL',
            2 => 'DEL',
            6 => 'MUM',
        ];

        $branchDetailsAll = [
            'KOL' => [
                'gstin' => '19ABACA4198B1ZS',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => '257B, BIPIN BEHARI GANGULY STREET',
                'address_2' => '2ND FLOOR',
                'address_3' => '',
                'city' => 'KOLKATA',
                'state' => 'WEST BENGAL',
                'postal_code' => '700012',
                'contact_name' => 'Amir Madraswala',
                'phone' => '9709555576',
                'email' => 'acetools505@gmail.com',
            ],
            'MUM' => [
                'gstin' => '27ABACA4198B1ZV',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => 'HARIHAR COMPLEX F-8, HOUSE NO-10607, ANUR DEPODE ROAD',
                'address_2' => 'GODOWN NO.7, GROUND FLOOR',
                'address_3' => 'BHIWANDI',
                'city' => 'MUMBAI',
                'state' => 'MAHARASHTRA',
                'postal_code' => '421302',
                'contact_name' => 'Hussain',
                'phone' => '9930791952',
                'email' => 'acetools505@gmail.com',
            ],
            'DEL' => [
                'gstin' => '07ABACA4198B1ZX',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => 'Khasra No. 58/15',
                'address_2' => 'Pal Colony',
                'address_3' => 'Village Rithala',
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'postal_code' => '110085',
                'contact_name' => 'Mustafa Worliwala',
                'phone' => '9730377752',
                'email' => 'acetools505@gmail.com',
            ],
        ];

        $branchCode = $branchMap[$invoice->warehouse_id] ?? 'DEL';
        $branchDetails = $branchDetailsAll[$branchCode] ?? [];

        $logistic = OrderLogistic::where('invoice_no', $invoice->invoice_no)->orderByDesc('id')->first();

        $pdfContentService = new PdfContentService();
        $pdfContentBlock   = $pdfContentService->buildBlockForType('invoice');

        $pdf = PDF::loadView('backend.sales.invoice_pdf', compact(
            'invoice',
            'billingDetails',
            'manager_phone',
            'branchDetails',
            'shipping',
            'logistic',
            'eway',
            'pdfContentBlock' // ✅ Blade me use hoga
        ));

        $dir = public_path('purchase_invoice_attachment');
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $fileName = str_replace('/', '_', $invoice->invoice_no) . '.pdf';
        $fullPath = $dir . '/' . $fileName;

        $pdf->save($fullPath);

        // ✅ Return relative path for DB
        return 'purchase_invoice_attachment/' . $fileName;
    }


    public function markAsReceived($invoice_id)
    {
        // $pdfURl=$this->downloadBtrPdf($invoice_id);

        // echo $pdfURl;
        // die();

        $invoice = InvoiceOrder::findOrFail($invoice_id);

        // 🔍 Get seller_id from invoice_no prefix
        $prefix = strtoupper(substr($invoice->invoice_no, 0, 3));
        $sellerMap = [
            'KOL' => 1,
            'DEL' => 2,
            'MUM' => 5,
        ];
        $sellerId = $sellerMap[$prefix] ?? null;

        if (!$sellerId) {
            return back()->with('error', 'Invalid seller prefix in invoice.');
        }

        // ✅ Fetch Seller with related Shop using your model
        $seller = Seller::with('shop')->where('id', $sellerId)->first();

        if (!$seller || !$seller->shop) {
            return back()->with('error', 'Seller or shop information not found.');
        }

        // ✅ Build seller_info JSON
        $sellerInfo = [
            'seller_name'    => $seller->shop->name,
            'seller_address' => $seller->shop->address,
            'seller_gstin'   => $seller->gstin,
            'seller_phone'   => $seller->shop->phone,
        ];

        $lastPurchase = PurchaseInvoice::orderBy('id', 'desc')->first();
        $newPurchaseNumber = $lastPurchase ? intval(substr($lastPurchase->purchase_no, 3)) + 1 : 1;
        $purchaseNo = 'pn-' . str_pad($newPurchaseNumber, 3, '0', STR_PAD_LEFT);
		
		$loggedInUser = auth()->user();

        // Step 1: Get warehouse_id
        $loggedInUserwarehouseId = $loggedInUser->warehouse_id;

        // ✅ Create purchase invoice
        $purchase = new PurchaseInvoice();
        $purchase->purchase_no         = $purchaseNo;
        $purchase->purchase_order_no   = 'BTR Purchase';
        $purchase->seller_invoice_no   = $invoice->invoice_no;
        $purchase->seller_invoice_date = \Carbon\Carbon::now()->format('Y-m-d');
        $purchase->seller_id           = $sellerId;
        $purchase->purchase_invoice_type = 'seller';
        $purchase->seller_info         = json_encode($sellerInfo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // ✅ Add this line
        $purchase->warehouse_id        = $loggedInUserwarehouseId;

        $purchase->save();

        // ✅ Generate and attach invoice PDF for this purchase
        $attachmentPath = $this->generateAndStoreInvoicePdfForMarkAsReceived($invoice->id);
        $purchase->update(['invoice_attachment' => $attachmentPath]);

        // ✅ Get warehouse and shipping state names
        $warehouseState = optional($invoice->warehouse->state)->name;
        $shippingState  = optional(optional(Address::find($invoice->shipping_address_id))->state)->name;

        // ✅ Initialize total tax
        $totalCgst = 0;
        $totalSgst = 0;
        $totalIgst = 0;

        // ✅ Insert purchase invoice details
        $details = InvoiceOrderDetail::where('invoice_order_id', $invoice->id)->get();

        foreach ($details as $detail) {
            $rate = (float) $detail->rate;
            $qty  = (float) $detail->billed_qty;
            $gst  = (float) $detail->gst;

            $price = round($rate / (1 + ($gst / 100)), 2);
            $grossAmt = round($price * $qty, 2);

            $cgst = $sgst = $igst = 0;

            if (strtoupper($warehouseState) === strtoupper($shippingState)) {
                $cgst = round(($grossAmt * ($gst / 2)) / 100, 2);
                $sgst = round(($grossAmt * ($gst / 2)) / 100, 2);
                $totalCgst += $cgst;
                $totalSgst += $sgst;
            } else {
                $igst = round(($grossAmt * $gst) / 100, 2);
                $totalIgst += $igst;
            }

            PurchaseInvoiceDetail::create([
                'purchase_invoice_id' => $purchase->id,
                'purchase_invoice_no' => $purchase->purchase_no,
                'purchase_order_no'   => 'BTR Purchase',
                'part_no'             => $detail->part_no,
                'qty'                 => $qty,
                'order_no'            => $detail->challan_no,
                'hsncode'             => $detail->hsn_no,
                'price'               => $price,
                'gross_amt'           => $grossAmt,
                'cgst'                => $cgst,
                'sgst'                => $sgst,
                'igst'                => $igst,
                'tax'                 => $gst,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // Optional: inventory entry
            $productID = Product::where('part_no', $detail->part_no)->value('id');
            if ($productID) {
                $requestSubmit = new \Illuminate\Http\Request();
                $requestSubmit->merge(['product_id' => $productID]);
                $this->inventoryProductEntry($requestSubmit);
            }
        }

        // ✅ Update total taxes
        $purchase->update([
            'total_cgst' => $totalCgst,
            'total_sgst' => $totalSgst,
            'total_igst' => $totalIgst,
        ]);

        // ✅ Mark invoice as BTR received
        $invoice->update(['btr_received_status' => 1]);
        // send whatsapp
        $res=$this->sendBTRWhatsApp($invoice);

        // ✅ Call Zoho bill creation
        $zoho = new ZohoController();
        $zohoBillResponse = $zoho->createVendorBill($purchase->id);

        return redirect()->back()->with('success_msg', 'Marked as received and products saved with tax calculations.');
    }



public function manager41SplitOrderPdf($id)
{
    // (optional) only allow Manager-41 context
    if (!$this->isActingAs41Manager()) {
        abort(403, 'Only Manager-41 is allowed to view this PDF.');
    }

    /** @var \App\Models\Manager41SubOrder $subOrder */
    $subOrder = \App\Models\Manager41SubOrder::with([
        'order_warehouse',
        'sub_order_details.product_data',
        'shippingAddress',
        'user', // needed for header "Ledger Name"
    ])->findOrFail($id);

    $warehouseName = $subOrder->order_warehouse->name ?? null;

    // annotate each detail
    $subOrder->sub_order_details->each(function ($detail) use ($warehouseName) {
        $partNo = optional($detail->product_data)->part_no;

        // closing stock for current godown (warehouse)
        $detail->closing_qty = 0;
        if ($partNo && $warehouseName) {
            $detail->closing_qty = (int) (
                \App\Models\Manager41ProductStock::where('part_no', $partNo)
                    ->where('godown', $warehouseName)
                    ->value('closing_stock') ?? 0
            );
        }

        // pending (unchallaned) qty
        $approvedQty = (int)($detail->approved_quantity ?? 0);
        $challanQty  = (int)($detail->challan_qty ?? 0);
        $detail->remaining_qty = max($approvedQty - $challanQty, 0);

        // net rate to print
        $detail->net_rate = (float)($detail->approved_rate ?? $detail->price ?? 0);
    });

    // keep only rows where pending qty > 0
    $filtered = $subOrder->sub_order_details->filter(fn ($d) => ($d->remaining_qty ?? 0) > 0);
    $subOrder->setRelation('sub_order_details', $filtered->values());

    // generate PDF (same view as normal flow)
    $pdf = \PDF::loadView('backend.sales.split_order_static', [
        'subOrder' => $subOrder,
    ], [], [
        'format'        => 'letter',
        'margin_top'    => 10,
        'margin_bottom' => 10,
        'margin_left'   => 10,
        'margin_right'  => 10,
    ]);

    return $pdf->stream('Manager41-Split-Order.pdf');
}

public function splitOrderPdf($id)
{
    // Manager-41 override
    if ($this->isActingAs41Manager()) {
        return $this->manager41SplitOrderPdf($id);
    }

    $subOrder = SubOrder::with([
        'order_warehouse',
        'sub_order_details.product_data',
        'shippingAddress',
        'user',
    ])->findOrFail($id);

    $warehouseName = $subOrder->order_warehouse->name ?? null;

    // Annotate each detail with closing_qty, remaining_qty (approved - challan), and net_rate
    $subOrder->sub_order_details->each(function ($detail) use ($warehouseName) {
        $partNo = optional($detail->product_data)->part_no;

        // Closing stock from products_api for current godown
        $detail->closing_qty = 0;
        if ($partNo && $warehouseName) {
            $detail->closing_qty = (int) (DB::table('products_api')
                ->where('part_no', $partNo)
                ->where('godown', $warehouseName)
                ->value('closing_stock') ?? 0);
        }

        $approvedQty = (int)($detail->approved_quantity ?? 0);
        $challanQty  = (int)($detail->challan_qty ?? 0);

        // pending (unchallaned)
        $detail->remaining_qty = max($approvedQty - $challanQty, 0);

        // net rate to print
        $detail->net_rate = (float)($detail->approved_rate ?? $detail->price ?? 0);
    });

    // Keep only rows with pending qty > 0
    $filtered = $subOrder->sub_order_details->filter(fn($d) => ($d->remaining_qty ?? 0) > 0);
    $subOrder->setRelation('sub_order_details', $filtered->values());

    $pdf = PDF::loadView('backend.sales.split_order_static', [
        'subOrder' => $subOrder,
    ], [], [
        'format'        => 'letter',
        'margin_top'    => 10,
        'margin_bottom' => 10,
        'margin_left'   => 10,
        'margin_right'  => 10,
    ]);

    return $pdf->stream('Split-Order.pdf');
}
    public function __splitOrderPdf($id)
    {
        $subOrder = SubOrder::with([
            'order_warehouse',
            'sub_order_details.product_data',
            'shippingAddress'
        ])->findOrFail($id);

        $warehouseName = $subOrder->order_warehouse->name ?? '';

        // Step 1: First filter by closing stock > 0
        $filteredDetails = $subOrder->sub_order_details->filter(function ($detail) use ($warehouseName) {
            $partNo = $detail->product_data->part_no ?? null;

            if (!$partNo || !$warehouseName) return false;

            $closingStock = ProductApi::where('part_no', $partNo)
                ->where('godown', $warehouseName)
                ->value('closing_stock');

            if ($closingStock > 0) {
                $detail->closing_qty = $closingStock;
                return true;
            }

            return false;
        });

        // Step 2: Now filter those having (approved_quantity - challan_qty) > 0
        $filteredDetails = $filteredDetails->filter(function ($detail) {
            $approvedQty = $detail->approved_quantity ?? 0;
            $challanQty = $detail->challan_qty ?? 0;

            return ($approvedQty - $challanQty) > 0;
        });

        // Replace original sub_order_details with final filtered list
        $subOrder->setRelation('sub_order_details', $filteredDetails);

        $pdf = PDF::loadView('backend.sales.split_order_static', [
            'subOrder' => $subOrder
        ], [], [
            'format' => 'letter',
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
            'margin_right' => 10,
        ]);

        return $pdf->stream('Split-Order.pdf');
    }


    public function invoiceProducts($id)
    {
        $invoice = InvoiceOrder::findOrFail($id);
        $products = InvoiceOrderDetail::where('invoice_order_id', $id)->get();
        return view('backend.sales.invoice_products', compact('invoice', 'products'));
    }


    
    public function downloadPdf($id)
    {
        $invoice = InvoiceOrder::with('invoice_products')->findOrFail($id);
        $eway = \App\Models\EwayBill::where('invoice_order_id', $id)->first();

        // Decode JSON if needed
        if (is_string($invoice->party_info)) {
            $invoice->party_info = json_decode($invoice->party_info, true);
        }

        // Get shipping address
        $shipping = Address::find($invoice->shipping_address_id);

        // Billing Info
        $billingDetails = (object) [
            'company_name' => $invoice->party_info['company_name'] ?? 'N/A',
            'address' => $invoice->party_info['address'] ?? 'N/A',
            'gstin' => $invoice->party_info['gstin'] ?? 'N/A',
        ];

        // Manager phone
        $manager_phone = '9999241558';

        // Define branch info
        $branchMap = [
            1 => 'KOL',
            2 => 'DEL',
            6 => 'MUM'
        ];

        $branchDetailsAll = [
            'KOL' => [
                'gstin' => '19ABACA4198B1ZS',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => '257B, BIPIN BEHARI GANGULY STREET',
                'address_2' => '2ND FLOOR',
                'address_3' => '',
                'city' => 'KOLKATA',
                'state' => 'WEST BENGAL',
                'postal_code' => '700012',
                'contact_name' => 'Amir Madraswala',
                'phone' => '9709555576',
                'email' => 'acetools505@gmail.com',
            ],
            'MUM' => [
                'gstin' => '27ABACA4198B1ZV',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => 'HARIHAR COMPLEX F-8, HOUSE NO-10607, ANUR DEPODE ROAD',
                'address_2' => 'GODOWN NO.7, GROUND FLOOR',
                'address_3' => 'BHIWANDI',
                'city' => 'MUMBAI',
                'state' => 'MAHARASHTRA',
                'postal_code' => '421302',
                'contact_name' => 'Hussain',
                'phone' => '9930791952',
                'email' => 'acetools505@gmail.com',
            ],
            'DEL' => [
                'gstin' => '07ABACA4198B1ZX',
                'company_name' => 'ACE TOOLS PVT LTD',
                'address_1' => 'Khasra No. 58/15',
                'address_2' => 'Pal Colony',
                'address_3' => 'Village Rithala',
                'city' => 'New Delhi',
                'state' => 'Delhi',
                'postal_code' => '110085',
                'contact_name' => 'Mustafa Worliwala',
                'phone' => '9730377752',
                'email' => 'acetools505@gmail.com',
            ],
        ];

        $branchCode = $branchMap[$invoice->warehouse_id] ?? 'DEL';
        $branchDetails = $branchDetailsAll[$branchCode] ?? [];

        

        $logistic = OrderLogistic::where('invoice_no', $invoice->invoice_no)->orderByDesc('id')->first();

            // echo "<pre>";
            // print_r($invoice->toArray());
            // die();

         // ✅ Yahan service ka object bana ke call karo
    $pdfContentService = new PdfContentService();
    $pdfContentBlock   = $pdfContentService->buildBlockForType('invoice');

        $pdf = PDF::loadView('backend.sales.invoice_pdf', compact(
            'invoice',
            'billingDetails',
            'manager_phone',
            'branchDetails',
            'shipping',
            'logistic',
            
             'eway', // ✅ NEW
             'pdfContentBlock' // ✅ Blade me use hoga
        ));

        return $pdf->download(str_replace('/', '_', $invoice->invoice_no) . '.pdf');
    }

    public function downloadEwayBillPDF($invoiceId)
    {
        $eway = EwayBill::where('invoice_order_id', $invoiceId)->first();
       
        if (!$eway) {
            return back()->with('error', 'E-Way Bill not found for this invoice.');
        }

        $qrLink = InvoiceOrder::where('id', $invoiceId)->value('qr_link');

        // ✅ Fetch unique HSN codes from invoice order details
        $hsnCodes = InvoiceOrderDetail::where('invoice_order_id', $invoiceId)
                        ->pluck('hsn_no')
                        ->unique()
                        ->filter() // remove null or empty
                        ->implode(', '); // convert to comma-separated string

        $ewayData = [
            'ewaybill_no'        => $eway->ewaybill_number,
            'ewaybill_date'      => \Carbon\Carbon::parse($eway->ewaybill_date)->format('d/m/Y h:i A'),
            'status'             => $eway->ewaybill_status_formatted ?? 'Generated',
            'valid_from'         => 'Not Valid for Movement as Part - B is not entered [' . ($eway->distance ?? '0') . 'Kms]',
            'irn'                => $eway->irn_no ?? '-',
            'supplier_gstin'     => $eway->supplier_gstin.' - ACE TOOLS PRIVATE LIMITED',
            'place_of_dispatch'  => $eway->place_of_dispatch,
            'recipient_gstin'    => $eway->customer_gstin . ' - ' . $eway->customer_name,
            'place_of_delivery'  => $eway->place_of_delivery,
            'doc_no'             => $eway->invoice_no,
            'doc_date'           => \Carbon\Carbon::parse($eway->ewaybill_date)->format('d/m/Y'),
            'transaction_type'   => 'Bill To - Ship To',
            'goods_value'        => number_format($eway->entity_total, 2),
            'hsn_code'           => $hsnCodes, // ✅ dynamically injected,
            'reason'             => "Outward - Supply",
            'transporter'        => $eway->transporter_registration_id . ' - ' . $eway->transporter_name,
        ];

        $pdf = PDF::loadView('backend.sales.eway_bill_pdf', ['eway' => $ewayData,'qrLink' => $qrLink]);
        return $pdf->download("EWAYBILL_{$invoiceId}.pdf");
    }


    public function generateApprovalPDF($orderId )
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

    private function getManagerPhone($managerId)
    {
          $managerData = DB::table('users')
              ->where('id', $managerId)
              ->select('phone')
              ->first();

          return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found

    }

    private function sendUnavaliableProductsNotification($subOrderDetailsId, $pdfUrl)
    {
        try {
            $WhatsAppWebService = new WhatsAppWebService();

            // ✅ Get SubOrderDetail with product + relations
            $subOrderDetail = SubOrderDetail::findOrFail($subOrderDetailsId);

            // ✅ Determine if it's a BTR type
            if ($subOrderDetail->type === 'btr') {

                // ✅ For BTR → get actual sub_order_id from sub_order_details
                $btrSubOrder = SubOrder::findOrFail($subOrderDetail->sub_order_id);

                // ✅ From that row, get original sub_order_id
                $originalSubOrder = SubOrder::findOrFail($btrSubOrder->sub_order_id);
                

                // ✅ Get shipping address and phone from that suborder
                $shippingAddress = Address::find($originalSubOrder->shipping_address_id);
                $companyName = $shippingAddress->company_name ?? 'Valued Customer';
                $customerPhone = $shippingAddress->phone ?? null;

                // ✅ Get related order
                $order = Order::find($originalSubOrder->order_id);
                $orderCode = $order->code;
                $orderDate = \Carbon\Carbon::parse($order->created_at)->format('Y-m-d');

                // ✅ Manager Phone from BTR User
                $btrUser = User::find($btrSubOrder->user_id);
                $managerPhone = $this->getManagerPhone($btrUser->manager_id ?? null);
            } else {
                // ✅ Default flow for regular sub_order
                $subOrder = SubOrder::with(['user', 'order'])->findOrFail($subOrderDetail->sub_order_id);
                $user = $subOrder->user;
                $order = $subOrder->order;

                if (!$order || !$user) {
                    return response()->json(['error' => 'Missing order or user info.'], 404);
                }

                $shippingAddress = Address::find($subOrder->shipping_address_id);
                $companyName = $shippingAddress->company_name ?? ($user->company_name ?? 'Valued Customer');
                $customerPhone = $user->phone;
                $orderCode = $order->code;
                $orderDate = \Carbon\Carbon::parse($order->created_at)->format('Y-m-d');
                $managerPhone = $this->getManagerPhone($user->manager_id ?? null);
            }

            // ✅ Upload PDF and fetch media ID
            $media = $WhatsAppWebService->uploadMedia($pdfUrl);
            if (!isset($media['media_id'])) {
                return response()->json(['error' => 'Failed to upload media to WhatsApp.'], 500);
            }

            // ✅ WhatsApp Template Payload
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

            // ✅ Send WhatsApp to Customer (For testing hardcoded 7044300330)
            $customerResponse = $WhatsAppWebService->sendTemplateMessage('7044300330', $templateData);

            // ✅ (Optional) Send WhatsApp to Manager
            $managerResponse = null;
            if ($managerPhone) {
                //$managerResponse = $WhatsAppWebService->sendTemplateMessage($managerPhone, $templateData);
            }

            return response()->json([
                'customer_response' => $customerResponse,
                'manager_response' => $managerResponse,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function generateUnavailableProductsPDF($sub_order_details_id)
    {
        // single unavailable product pdf generation
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
        $res=$this->sendUnavaliableProductsNotification($sub_order_details_id, $pdfURL);
        echo "<pre>";
        print_r($res);


        return $pdfURL;
    }

    // main button code start

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

            // ✅ NEW: skip if pre_closed is null
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
        // send whatsapp for single unavaliable product
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
            $customerResponse = $WhatsAppWebService->sendTemplateMessage('7044300330', $templateData);

            // Send WhatsApp to Manager (optional)
            $managerResponse = null;
            if ($managerPhone) {
               // $managerResponse = $WhatsAppWebService->sendTemplateMessage($managerPhone, $templateData);
            }

            return response()->json([
                'customer_response' => $customerResponse,
                'manager_response' => $managerResponse,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Debit note start

    public function viewManualDebitNote()
    {
        $warehouses = Warehouse::whereIn('id', [1, 2, 6])->get(); // only specific warehouses

        $all_sellers = Shop::join('sellers', 'shops.seller_id', '=', 'sellers.id')
            ->join('users', 'sellers.user_id', '=', 'users.id')
            ->select(
                'sellers.id as seller_id',
                'shops.name as seller_name',
                'shops.address as seller_address',
                'sellers.gstin',
                'shops.phone as seller_phone',
                'users.state as state_name'
            )
            ->get();

        $states = State::all();
        $orders = collect(); // Empty by default

        $products = DB::table('products')
            ->select('id', 'name', 'part_no', 'purchase_price')
            ->where('current_stock', '1')
            ->get();

        $categoryGroups = CategoryGroup::all();
        $categories = Category::all();
        $brands = Brand::all();

        $all_customers = DB::table('addresses')
            ->leftJoin('states', 'addresses.state_id', '=', 'states.id')
            ->select(
                'addresses.id',
                'addresses.company_name',
                'addresses.address',
                'addresses.phone',
                'addresses.gstin',
                'addresses.acc_code',
                'addresses.city',
                'states.name as state_name'
            )
            ->get();

        return view('backend.po.debit_note_purchase_order', compact(
            'warehouses',
            'all_sellers',
            'orders',
            'products',
            'categoryGroups',
            'categories',
            'brands',
            'states',
            'all_customers'
        ));
    }

    public function saveManualDebitNoteCustomer(Request $request)
    {
        $validatedData = $request->validate([
            'warehouse_id' => 'required|integer',
            'address_id' => 'required|integer',
            'orders.*.part_no' => 'required|string',
            'orders.*.product_name' => 'required|string',
            'orders.*.quantity' => 'required|integer|min:1',
            'orders.*.purchase_price' => 'required|numeric|min:0',
        ]);

        $creditNoteType = $request->input('credit_note_type');
        if ($creditNoteType === 'service') {
            return $this->insertDebitNoteServiceData($request); // Service entry handling
        }

        $address = Address::with('state')->findOrFail($request->input('address_id'));
        $customerState = strtoupper($address->state->name ?? '');
        $warehouseId = $request->input('warehouse_id');
        $companyState = strtoupper(User::where('id', Warehouse::where('id', $warehouseId)->value('user_id'))->value('state'));

        // Generate debit_note_no
        $last = DebitNoteInvoice::orderBy('id', 'desc')->first();
        $newNo = $last ? intval(substr($last->debit_note_no, 3)) + 1 : 1;
        $debitNoteNo = 'dn-' . str_pad($newNo, 3, '0', STR_PAD_LEFT);

        $warehouse = Warehouse::findOrFail($warehouseId);
        $warehouseCode = strtoupper(substr($warehouse->name, 0, 3));

        // Generate debit_note_number
        $lastDN = DebitNoteInvoice::where('debit_note_number', 'LIKE', $warehouseCode . '/DN/%')
            ->orderBy('id', 'desc')
            ->value('debit_note_number');
        $newNumber = $lastDN ? str_pad(intval(substr($lastDN, -3)) + 1, 3, '0', STR_PAD_LEFT) : '001';
        $debitNoteNumber = $warehouseCode . '/DN/' . $newNumber;

        // Insert into DebitNoteInvoice
        $invoiceId = DebitNoteInvoice::insertGetId([
            'debit_note_no' => $debitNoteNo,
            'debit_note_order_no' => 'Goods Return',
            'seller_invoice_no' => $request->input('seller_invoice_no'),
            'seller_invoice_date' => $request->input('seller_invoice_date'),
            'addresses_id' => $request->input('address_id'),
            'debit_note_type' => 'customer',
            'debit_note_number' => $debitNoteNumber,
            'warehouse_id' => $warehouseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $totalCgst = $totalSgst = $totalIgst = 0;

        foreach ($request->input('orders') as $product) {
            $qty = (float) $product['quantity'];
            $rate = (float) $product['purchase_price'];
            $hsncode = $product['hsncode'] ?? '';
            $price = round($rate / 1.18, 2);
            $grossAmt = round($price * $qty, 2);

            $cgst = $sgst = $igst = 0;

            if ($customerState === $companyState) {
                $cgst = $sgst = round($grossAmt * 0.09, 2);
                $totalCgst += $cgst;
                $totalSgst += $sgst;
            } else {
                $igst = round($grossAmt * 0.18, 2);
                $totalIgst += $igst;
            }

            // Insert into DebitNoteInvoiceDetail
            DebitNoteInvoiceDetail::create([
                'debit_note_invoice_id' => $invoiceId,
                'debit_note_no' => $debitNoteNo,
                'debit_note_order_no' => 'Goods Return',
                'part_no' => $product['part_no'],
                'qty' => $qty,
                'order_no' => 'Goods Return',
                'hsncode' => $hsncode,
                'price' => $price,
                'gross_amt' => $grossAmt,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'tax' => 18,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Optional HSN update
            $productID = Product::where('part_no', $product['part_no'])->value('id');
            if ($productID) {
                Product::where('id', $productID)->update(['hsncode' => $hsncode]);

               //  $requestSubmit = new \Illuminate\Http\Request();
               //  $requestSubmit->merge(['product_id' => $productID]);
               // $this->inventoryProductEntry($requestSubmit);
            }
        }

        // Update totals
        DebitNoteInvoice::where('id', $invoiceId)->update([
            'total_cgst' => $totalCgst,
            'total_sgst' => $totalSgst,
            'total_igst' => $totalIgst,
        ]);

        // additional added zoho push
        try {
            $zoho = new ZohoController();
            $zoho->createVendorCreditFromCustomerForGoodsOrService($invoiceId);
        } catch (\Exception $e) {
            echo "<pre>";
            print_r($e->getMessage());
            die();
            \Log::error('Zoho Vendor Credit (Service) failed: ' . $e->getMessage());
        }
        die();

        // return redirect()->route('admin.viewDebitNotePurchaseOrder')->with('status', 'Customer Debit Note created successfully!');
        return redirect()->route('purchase.debit.note.list')->with('status', 'Customer Debit Note created successfully!');

    }


    public function saveManualDebitNoteSeller(Request $request)
    {
        // Step 1: Validate Inputs
        $validatedData = $request->validate([
            'warehouse_id' => 'required|integer',
            'seller_info.seller_name' => 'required|string',
            'seller_info.seller_phone' => 'required|string',
            'orders.*.part_no' => 'required|string',
            'orders.*.product_name' => 'required|string',
            'orders.*.quantity' => 'required|integer|min:1',
            'orders.*.purchase_price' => 'required|numeric|min:0',
        ]);

        $creditNoteType = $request->input('credit_note_type');
        if ($creditNoteType === 'service') {
            return $this->insertDebitNoteServiceDataSeller($request); // Service entry handler
        }

        $warehouseId = $request->input('warehouse_id');
        $sellerInfo = $request->input('seller_info');
        $sellerId = $sellerInfo['seller_id'] ?? null;

        // Step 2: Create New Seller If Needed
        if (empty($sellerId)) {
            try {
                $sellerId = $this->createSellerFromManualEntry($sellerInfo);
                $sellerInfo['seller_id'] = $sellerId;
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Seller creation failed: ' . $e->getMessage());
            }
        }

        // Step 3: State Comparison (Seller vs Warehouse)
        $sellerState = strtoupper($sellerInfo['state_name'] ?? '');
        $companyState = strtoupper(User::where('id', Warehouse::where('id', $warehouseId)->value('user_id'))->value('state'));

        // Step 4: Generate Debit Note No
        $last = DebitNoteInvoice::orderBy('id', 'desc')->first();
        $newNo = $last ? intval(substr($last->debit_note_no, 3)) + 1 : 1;
        $debitNoteNo = 'dn-' . str_pad($newNo, 3, '0', STR_PAD_LEFT);

        // Step 5: Generate Debit Note Number
        $warehouse = Warehouse::findOrFail($warehouseId);
        $warehouseCode = strtoupper(substr($warehouse->name, 0, 3));
        $lastDN = DebitNoteInvoice::where('debit_note_number', 'LIKE', $warehouseCode . '/DN/%')
            ->orderBy('id', 'desc')
            ->value('debit_note_number');
        $newNumber = $lastDN ? str_pad(intval(substr($lastDN, -3)) + 1, 3, '0', STR_PAD_LEFT) : '001';
        $debitNoteNumber = $warehouseCode . '/DN/' . $newNumber;

        // Step 6: Insert Main Debit Note Invoice
        $invoiceId = DebitNoteInvoice::insertGetId([
            'debit_note_no' => $debitNoteNo,
            'debit_note_order_no' => 'Goods Return',
            'seller_invoice_no' => $request->input('seller_invoice_no'),
            'seller_invoice_date' => $request->input('seller_invoice_date'),
            'warehouse_id' => $warehouseId,
            'debit_note_type' => 'seller',
            'seller_id' => $sellerId,
            'seller_info' => json_encode($sellerInfo),
            'debit_note_number' => $debitNoteNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Step 7: Insert Products
        $totalCgst = $totalSgst = $totalIgst = 0;

        foreach ($request->input('orders') as $product) {
            $qty = (float) $product['quantity'];
            $rate = (float) $product['purchase_price'];
            $hsncode = $product['hsncode'] ?? '';
            $price = round($rate / 1.18, 2);
            $grossAmt = round($price * $qty, 2);

            $cgst = $sgst = $igst = 0;
            if ($sellerState === $companyState) {
                $cgst = $sgst = round($grossAmt * 0.09, 2);
                $totalCgst += $cgst;
                $totalSgst += $sgst;
            } else {
                $igst = round($grossAmt * 0.18, 2);
                $totalIgst += $igst;
            }

            DebitNoteInvoiceDetail::create([
                'debit_note_invoice_id' => $invoiceId,
                'debit_note_no' => $debitNoteNo,
                'debit_note_order_no' => 'Goods Return',
                'part_no' => $product['part_no'],
                'qty' => $qty,
                'order_no' => 'Goods Return',
                'hsncode' => $hsncode,
                'price' => $price,
                'gross_amt' => $grossAmt,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'tax' => 18,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productID = Product::where('part_no', $product['part_no'])->value('id');
            if ($productID) {
                Product::where('id', $productID)->update(['hsncode' => $hsncode]);

                if ($productID && $creditNoteType !== 'service') {
                    $requestSubmit = new \Illuminate\Http\Request();
                    $requestSubmit->merge(['product_id' => $productID]);
                    $this->inventoryProductEntry($requestSubmit);
                }
            }
        }

        // Step 8: Update Totals
        DebitNoteInvoice::where('id', $invoiceId)->update([
            'total_cgst' => $totalCgst,
            'total_sgst' => $totalSgst,
            'total_igst' => $totalIgst,
        ]);

        if ($request->input('credit_note_type') !== 'service') {
            try {
                $zoho = new ZohoController();
                $zoho->createVendorCreditFromSellerForGoodsOrService($invoiceId);
            } catch (\Exception $e) {
                \Log::error('Zoho Vendor Credit (Goods) failed: ' . $e->getMessage());
            }
        }

        // Final redirect
        // return redirect()->route('admin.viewDebitNotePurchaseOrder')->with('status', 'Seller Debit Note created successfully!');
        return redirect()->route('purchase.debit.note.list')->with('status', 'Seller Debit Note created successfully!');
    }



    public function saveManualDebitNote(Request $request)
    {
        $partyType = $request->input('party_type'); // 'seller' or 'customer'
        $action = $request->input('action'); // usually 'convert'

        // Handle Customer Debit Note
        if ($partyType === 'customer' && $action === 'convert') {
            return $this->saveManualDebitNoteCustomer($request);
        }

        // Handle Seller Debit Note
        if ($partyType === 'seller' && $action === 'convert') {
            return $this->saveManualDebitNoteSeller($request);
        }

        // If invalid or missing values
        return redirect()->back()->with('error', 'Invalid party type or action for Debit Note.');
    }


    private function insertDebitNoteServiceData(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        $addressId = $request->input('address_id');
        $note = $request->input('note');
        $sacCode = $request->input('sac_code');
        $rate = (float) $request->input('rate');
        $quantity = (int) $request->input('quantity');

        $address = Address::with('state')->findOrFail($addressId);
        $customerState = strtoupper($address->state->name ?? '');

        $warehouse = Warehouse::findOrFail($warehouseId);
        $companyState = strtoupper(User::where('id', $warehouse->user_id)->value('state'));
        $warehouseCode = strtoupper(substr($warehouse->name, 0, 3));

        // Generate Debit Note Number
        $lastDebitNote = DebitNoteInvoice::where('debit_note_number', 'LIKE', $warehouseCode . '/DN/%')
            ->orderBy('id', 'desc')
            ->value('debit_note_number');

        $newNumber = $lastDebitNote
            ? str_pad(intval(substr($lastDebitNote, -3)) + 1, 3, '0', STR_PAD_LEFT)
            : '001';

        $debitNoteNumber = $warehouseCode . '/DN/' . $newNumber;

        // Generate Debit Note No (formerly purchase_no)
        $last = DebitNoteInvoice::orderBy('id', 'desc')->first();
        $newNo = $last ? intval(substr($last->debit_note_no, 3)) + 1 : 1;
        $debitNoteNo = 'dn-' . str_pad($newNo, 3, '0', STR_PAD_LEFT);

        // Insert in DebitNoteInvoice
        $invoiceId = DebitNoteInvoice::insertGetId([
            'debit_note_no' => $debitNoteNo,
            'debit_note_order_no' => 'Service Entry',
            'seller_invoice_no' => $request->input('seller_invoice_no'),
            'seller_invoice_date' => $request->input('seller_invoice_date'),
            'addresses_id' => $addressId,
            'warehouse_id' => $warehouseId,
            'debit_note_type' => 'customer',
            'debit_note_number' => $debitNoteNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Calculate Tax
        $gstRate = 18;
        $priceExcludingGST = round($rate / (1 + ($gstRate / 100)), 2);
        $grossAmount = round($priceExcludingGST * $quantity, 2);
        $cgst = $sgst = $igst = 0;

        if ($customerState === $companyState) {
            $cgst = round($grossAmount * 0.09, 2);
            $sgst = round($grossAmount * 0.09, 2);
        } else {
            $igst = round($grossAmount * 0.18, 2);
        }

        // Insert in DebitNoteInvoiceDetail
        DebitNoteInvoiceDetail::create([
            'debit_note_invoice_id' => $invoiceId,
            'debit_note_no' => $debitNoteNo,
            'debit_note_order_no' => 'Service Entry',
            'part_no' => $note,
            'hsncode' => $sacCode,
            'qty' => $quantity,
            'price' => $priceExcludingGST,
            'gross_amt' => $grossAmount,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => $igst,
            'tax' => 18,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update total taxes
        DebitNoteInvoice::where('id', $invoiceId)->update([
            'total_cgst' => $cgst,
            'total_sgst' => $sgst,
            'total_igst' => $igst,
        ]);

        // return redirect()->route('admin.viewDebitNotePurchaseOrder')->with('status', 'Service Debit Note created successfully: ' . $debitNoteNumber);
        return redirect()->route('purchase.debit.note.list')->with('status', 'Service Debit Note created successfully!');

    }


    private function insertDebitNoteServiceDataSeller(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        $sellerInfo = $request->input('seller_info');
        $sellerId = $sellerInfo['seller_id'] ?? null;

        // Create seller if new
        if (empty($sellerId)) {
            try {
                $sellerId = $this->createSellerFromManualEntry($sellerInfo);
                $sellerInfo['seller_id'] = $sellerId;
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Seller creation failed: ' . $e->getMessage());
            }
        }

        $sellerState = strtoupper($sellerInfo['state_name'] ?? '');
        $companyState = strtoupper(User::where('id', Warehouse::where('id', $warehouseId)->value('user_id'))->value('state'));
        $warehouse = Warehouse::findOrFail($warehouseId);
        $warehouseCode = strtoupper(substr($warehouse->name, 0, 3));

        // Generate debit_note_number
        $lastDebitNote = DebitNoteInvoice::where('debit_note_number', 'LIKE', $warehouseCode . '/DN/%')
            ->orderBy('id', 'desc')
            ->value('debit_note_number');
        $newNumber = $lastDebitNote
            ? str_pad(intval(substr($lastDebitNote, -3)) + 1, 3, '0', STR_PAD_LEFT)
            : '001';
        $debitNoteNumber = $warehouseCode . '/DN/' . $newNumber;

        // Generate debit_note_no
        $last = DebitNoteInvoice::orderBy('id', 'desc')->first();
        $newNo = $last ? intval(substr($last->debit_note_no, 3)) + 1 : 1;
        $debitNoteNo = 'dn-' . str_pad($newNo, 3, '0', STR_PAD_LEFT);

        // Insert main debit note invoice
        $invoiceId = DebitNoteInvoice::insertGetId([
            'debit_note_no' => $debitNoteNo,
            'debit_note_order_no' => 'Service Entry',
            'seller_invoice_no' => $request->input('seller_invoice_no'),
            'seller_invoice_date' => $request->input('seller_invoice_date'),
            'warehouse_id' => $warehouseId,
            'debit_note_type' => 'seller',
            'seller_id' => $sellerId,
            'seller_info' => json_encode($sellerInfo),
            'debit_note_number' => $debitNoteNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tax Calculation
        $note = $request->input('note');
        $sacCode = $request->input('sac_code');
        $rate = (float) $request->input('rate');
        $quantity = (int) $request->input('quantity');
        $gstRate = 18;

        $priceExGST = round($rate / (1 + ($gstRate / 100)), 2);
        $grossAmount = round($priceExGST * $quantity, 2);

        $cgst = $sgst = $igst = 0;
        if ($sellerState === $companyState) {
            $cgst = round($grossAmount * 0.09, 2);
            $sgst = round($grossAmount * 0.09, 2);
        } else {
            $igst = round($grossAmount * 0.18, 2);
        }

        DebitNoteInvoiceDetail::create([
            'debit_note_invoice_id' => $invoiceId,
            'debit_note_no' => $debitNoteNo,
            'debit_note_order_no' => 'Service Entry',
            'part_no' => $note,
            'hsncode' => $sacCode,
            'qty' => $quantity,
            'price' => $priceExGST,
            'gross_amt' => $grossAmount,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => $igst,
            'tax' => 18,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DebitNoteInvoice::where('id', $invoiceId)->update([
            'total_cgst' => $cgst,
            'total_sgst' => $sgst,
            'total_igst' => $igst,
        ]);


        try {
            $zoho = new ZohoController();
            $zoho->createVendorCreditFromSellerForGoodsOrService($invoiceId);
        } catch (\Exception $e) {
            \Log::error('Zoho Vendor Credit (Service) failed: ' . $e->getMessage());
        }

        // return redirect()->route('admin.viewDebitNotePurchaseOrder')->with('status', 'Seller Service Debit Note created successfully!');
        return redirect()->route('purchase.debit.note.list')->with('status', 'Seller Service Debit Note created successfully!');

    }



    public function showPurchaseDebitNoteList(Request $request)
    {
        // Fetch product list for reference
        $products = Product::select('part_no', 'name')->get()->keyBy('part_no');

        // Eager load debit note relationships
        $query = DebitNoteInvoice::with([
            // 'makePurchaseOrder', // ❌ Remove or update if no longer needed
            'debitNoteInvoiceDetails',
            'address.state'
        ])->orderBy('id', 'DESC');

        if ($request->has('search') && !empty($request->search)) {
            $search = trim(strtolower($request->search));

            if ($search === 'unsync') {
                $query->where(function ($q) {
                    $q->whereNull('zoho_debitnote_id')
                      ->orWhere('zoho_debitnote_id', '');
                });
            } else {
                $query->where(function ($q) use ($search) {
                    $q->where('debit_note_no', 'like', '%' . $search . '%') // ✅ was purchase_no
                      ->orWhere('seller_invoice_no', 'like', '%' . $search . '%')
                      ->orWhere('debit_note_number', 'like', '%' . $search . '%')
                      ->orWhereHas('address', function ($q2) use ($search) {
                          $q2->where('company_name', 'like', '%' . $search . '%')
                             ->orWhere('phone', 'like', '%' . $search . '%');
                      })
                      ->orWhereJsonContains('seller_info->seller_name', $search)
                      ->orWhereJsonContains('seller_info->seller_phone', $search);
                });
            }
        }

        $purchases = $query->paginate(50)->withQueryString();

        return view('backend.po.purchase_debit_note', compact('purchases', 'products'));
    }




    public function downloadDebitInvoicePDF($id)
    {
        // Fetch debit note invoice
        $invoice = DebitNoteInvoice::with(['warehouse', 'address'])->findOrFail($id);

        // Handle seller info (array or address)
        $sellerInfo = $invoice->addresses_id ? $invoice->address : $invoice->seller_info;

        // Fetch debit note invoice details
        $details = DebitNoteInvoiceDetail::where('debit_note_invoice_id', $invoice->id)->get();

        // Prepare product-wise info
        $productInfo = $details->map(function ($detail) {
            $product = Product::where('part_no', $detail->part_no)->first();

            $rateWithoutTax = $detail->price ?? 0;
            $taxPercent = $detail->tax ?? 0;

            // ✅ Final rate including tax
            $rate = $rateWithoutTax + ($rateWithoutTax * $taxPercent / 100);

            $qty = $detail->qty ?? 0;
            $subtotal = $qty * $rate;

            return (object)[
                'order_no' => $detail->order_no ?? '-',
                'debit_note_order_no' => $detail->debit_note_order_no ?? '-',
                'part_no' => $detail->part_no ?? '-',
                'product_name' => $product->name ?? 'N/A',
                'slug' => $product->slug ?? '',
                'thumbnail_img' => $product->thumbnail_img ?? null,
                'hsncode' => $detail->hsncode ?? '-',
                'qty' => $qty,
                'rate' => $rate,
                'subtotal' => $subtotal,
                'tax_percent' => $taxPercent,
            ];
        });

        // Calculate total amount
        $totalAmount = $productInfo->sum('subtotal');

        // Generate the PDF
        $pdf = PDF::loadView('backend.po.debit_invoice_pdf', [
            'invoice' => $invoice,
            'sellerInfo' => $sellerInfo,
            'productInfo' => $productInfo,
            'totalAmount' => $totalAmount,
            'direction' => 'ltr',
            'text_align' => 'left',
            'not_text_align' => 'right',
            'font_family' => 'DejaVu Sans',
            'logo' => true
        ]);

        return $pdf->download($invoice->debit_note_no . '_invoice.pdf');
    }



    // Debit Note end


    public function sendPurchaseInvoicePdfOnWhatsApp($id)
    {
        $invoice = PurchaseInvoice::with([
            'purchaseInvoiceDetails.product',
            'address',
            'warehouse',
            'shop'
        ])->findOrFail($id);

        $orderNos = [];

        // Build invoice_products array
        $invoice->invoice_products = $invoice->purchaseInvoiceDetails->map(function ($detail) use (&$orderNos) {
            $rawOrderNo = $detail->order_no;
            $cleanOrderNo = preg_replace('/\s*\(.*?\)/', '', $rawOrderNo);

            $orderNos[] = $cleanOrderNo;

            $subOrder = SubOrder::where('order_no', $cleanOrderNo)->first();
            $companyName = 'Manual Entry';

            if ($subOrder && $subOrder->user_id) {
                $user = User::find($subOrder->user_id);
                $companyName = $user->company_name ?? '';
            }

            return (object)[
                'item_name'        => $detail->product->name ?? 'N/A',
                'part_no'          => $detail->part_no,
                'billed_qty'       => $detail->qty,
                'to_company_name'  => $companyName,
                'sale_order_no'    => $cleanOrderNo,
            ];
        });

        // Generate PDF
        $pdf = PDF::loadView('backend.sales.convert_to_purchase_pdf', compact('invoice'));
        $fileName = 'convert-to-purchase-' . str_replace('/', '_', $invoice->purchase_no ?? $invoice->id) . '.pdf';
        $pdfPath = public_path('purchase_history_invoice/' . $fileName);

        // Save PDF to server
        if (!file_exists(public_path('purchase_history_invoice'))) {
            mkdir(public_path('purchase_history_invoice'), 0755, true);
        }

        $pdf->save($pdfPath);

        // Upload PDF to WhatsApp

        // ✅ New Logic to get Party Name from warehouse → user → company_name
        $warehouseUser = $invoice->warehouse && $invoice->warehouse->user_id
            ? User::find($invoice->warehouse->user_id)
            : null;

        $companyName = $warehouseUser->company_name ?? 'N/A';

        $pdfUrl = url('public/purchase_history_invoice/' . $fileName);
        $WhatsAppWebService = new WhatsAppWebService(); // replace with your actual instance or injected service
        $media_id = $WhatsAppWebService->uploadMedia($pdfUrl);

        // Prepare WhatsApp Template Data
        $templateData = [
            'name' => 'utility_convert_to_purchase',
            'language' => 'en_US',
            'components' => [
                [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'document',
                            'document' => [
                                'filename' => "Purchase_Invoice.pdf",
                                'id' => $media_id['media_id'],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $companyName ?? 'N/A', // {{1}} Party Name
                        ],
                        [
                            'type' => 'text',
                            'text' => $invoice->purchase_no, // {{2}} Invoice Number
                        ],
                        [
                            'type' => 'text',
                            'text' => implode(', ', array_unique($orderNos)), // {{3}} Order Nos (cleaned)
                        ],
                    ],
                ],
            ],
        ];

        // Send WhatsApp message here using your WhatsApp sending function
       // Define warehouse-wise recipients
        $recipients = [
            'Kolkata' => '8597228356',
            'Delhi'   => '9763268640',
            'Mumbai'  => '9860433981',
            'burahan' => '9894753728'
        ];
        // Determine recipient number from warehouse name
        $warehouseName = strtolower(trim($invoice->warehouse->name ?? ''));
        $recipientNumber = $recipients[ucfirst($warehouseName)] ?? $recipients['burahan']; // fallback to Burhan if not matched
        // Send WhatsApp message
        $res = $WhatsAppWebService->sendTemplateMessage($recipientNumber, $templateData);
        return response()->json([
            'status' => 'PDF generated and template ready.',
            'pdf_url' => $pdfUrl,
            'template_data' => $templateData,
            'whatsapp_response'=>$res
        ]);
    }

   // barcode code start
    public function show()
    {
        $barcode = $this->generateEAN13Barcode(); // ✅ Dynamic, valid

         $product = Product::findOrFail(50933); // You can replace 1 with a passed ID
        return view('backend.labels.barcode_label', [
            'mrp' => '1699.00',
            'part_no' => $product->part_no,
            'product_name' => $product->name,
            'qty' => 3,
            'barcode'=>$barcode,
            'marketed_by' => 'ACE TOOLS PVT LTD',
            'market_address_line1' => 'Khasra No. 58/15',
            'market_address_line2' => 'Pal Colony, Village Rithala',
            'market_address_line3' => 'CITY: New Delhi, STATE: Delhi, POSTAL CODE:  Delhi 110085',
        ]);


    }

    public function generateEAN13Barcode()
    {
        $code = '';
        for ($i = 0; $i < 12; $i++) {
            $code .= rand(0, 9); // generate 12-digit number
        }

        // Calculate the 13th check digit
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $code[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $code . $checkDigit;
    }


 public function manager41InventoryEntry(Request $request)
{
    $pending = \App\Models\Manager41ResetInventoryProduct::orderBy('id','ASC')->take(50)->get();
    $msg = "Nothing Sync";

    foreach ($pending as $rp) {
        $warehouses = \App\Models\Warehouse::where('active', '1')->get();

        foreach ($warehouses as $wh) {
            $openingQty  = 0;
            $purchaseQty = 0;
            $saleQty     = 0;

            // Opening
            $opening = \App\Models\Manager41OpeningStock::where('part_no', $rp->part_no)
                ->where(function ($q) use ($wh) {
                    $q->where('godown', $wh->name)->orWhere('warehouse_id', $wh->id);
                })->first();
            if ($opening) $openingQty = (float) $opening->closing_stock;

            // Purchases
            $piDetails = \App\Models\Manager41PurchaseInvoiceDetail::where('part_no', $rp->part_no)
                ->whereDate('created_at', '>=', '2025-04-01')
                ->whereHas('purchaseInvoice', fn($q)=>$q->where('warehouse_id',$wh->id))
                ->get();
            if ($piDetails->isNotEmpty()) $purchaseQty = (int) $piDetails->sum('qty');

            // Sales
            $challanDetails = \App\Models\Manager41ChallanDetail::where('product_id', $rp->product_id)
                ->whereDate('created_at', '>=', '2025-04-01')
                ->whereHas('challan', fn($q)=>$q->where('warehouse_id',$wh->id))
                ->get();
            if ($challanDetails->isNotEmpty()) $saleQty = (int) $challanDetails->sum('quantity');

            // Final stock
            $stock = ($openingQty + $purchaseQty) - $saleQty;

            // Upsert mirror
            $mirror = \App\Models\Manager41ProductStock::where('part_no', $rp->part_no)
                ->where('godown', $wh->name)
                ->first();

            if (!$mirror) {
                $product = \App\Models\Product::where('part_no', $rp->part_no)->first();
                if ($stock > 0) {
                    \App\Models\Manager41ProductStock::create([
                        'part_no'       => $rp->part_no,
                        'name'          => $product->name ?? '',
                        'group'         => '',
                        'category'      => '',
                        'closing_stock' => $stock,
                        'list_price'    => $product->mrp ?? 0,
                        'godown'        => $wh->name,
                    ]);
                }
            } else {
                $mirror->closing_stock = $stock;
                $mirror->save();
            }
        } // <-- foreach ($warehouses) end

        /* ✅ FINAL CHECK  */
        $hasAnyPositive = \App\Models\Manager41ProductStock::where('part_no', $rp->part_no)
            ->where('closing_stock', '>', 0)
            ->exists();

        \App\Models\Product::where('part_no', $rp->part_no)
            ->update(['is_manager_41' => $hasAnyPositive ? 1 : 0]);

        // queue item done
        $rp->delete();
    }

    if ($pending->count() > 0) $msg = $pending->count() . " record sync";
    return $msg;
}



public function org_replacePrecloseItem(Request $request)
{
    // GET-only; no DB::transaction()
    $id   = (int) $request->query('sub_order_details_id', 0);
    $type = (string) $request->query('sub_order_type', ''); // 'sub_order' | 'btr'

    if ($id <= 0) {
        return back()->with('error', 'Invalid sub_order_details_id.');
    }
    if ($type !== 'sub_order' && $type !== 'btr') {
        return back()->with('error', 'Please provide sub_order_type (sub_order or btr).');
    }



    try {


        // ---- Load current row (no lock, per requirement)
        /** @var SubOrderDetail $current */
        $current = SubOrderDetail::findOrFail($id);

        

        // Linked order id (BTR child order id) if any
        $btrOrderId = $request->query('has_btr_order_id'); // may be null/empty

        // Decide defaults for flags if not explicitly passed
        $propagateToMain = $request->has('propagate_to_main')
            ? (bool) $request->boolean('propagate_to_main')
            : ($type === 'btr'); // default ON for btr

        $closeLinkedBtr = $request->has('close_linked_btr')
            ? (bool) $request->boolean('close_linked_btr')
            : ($type === 'sub_order' && !empty($btrOrderId)); // default ON when main has BTR child


        

        // Identify main/btr rows
        $main = null;  // type='sub_order'
        $btr  = null;  // type='btr'

        if ($type === 'sub_order') {
            $main = $current;
            if (!empty($btrOrderId)) {
                $btr = SubOrderDetail::where([
                    'product_id'   => $main->product_id,
                    'sub_order_id' => $btrOrderId,
                    'type'         => 'btr',
                ])->first();
            }
        } else { // btr
            $btr = $current;
            $main = SubOrderDetail::where([
                'product_id'       => $btr->product_id,
                'order_details_id' => $btr->order_details_id,
                'type'             => 'sub_order',
            ])->first();
        }

        // Guards mirroring savePreClose()
        if ($type === 'btr' && $propagateToMain && !$main) {
            return back()->with('error', 'Linked main sub_order not found for this BTR line.');
        }
        if ($type === 'sub_order' && $closeLinkedBtr && !$btr) {
            // We can still close main even if BTR not found; but mirror your previous behavior:
            return back()->with('error', 'Linked BTR sub_order not found for this main line.');
        }

        // Helpers (no closure vars outside)
        $i = function ($v) { return (int) ($v ?? 0); };
        $getNums = function (SubOrderDetail $row) use ($i) {
            return [
                'approved'   => $i($row->approved_quantity),
                'challan'    => $i($row->challan_quantity),
                'in_transit' => $i($row->in_transit),
                'pre_closed' => $i($row->pre_closed),
            ];
        };
        $recomputeStatus = function (SubOrderDetail $row) use ($getNums) {
            ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($row);
            $row->pre_closed_status = (($C + ($P + $T)) >= $A) ? 1 : 0;
        };
        $precloseDelta = function (SubOrderDetail $row, int $add) use ($getNums) : int {
            ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($row);
            $remaining = max(0, $A - $C - $T - $P);
            $delta     = max(0, min($add, $remaining));
            if ($delta > 0) {
                $row->pre_closed    = $P + $delta;
                $row->pre_closed_by = $row->warehouse_id;
            }
            return $delta;
        };
        $moveTransitToPreclose = function (SubOrderDetail $row, int $qty) use ($getNums) : int {
            ['in_transit'=>$T] = $getNums($row);
            $delta = max(0, min($qty, $T));
            if ($delta > 0) {
                $row->in_transit = $T - $delta;
            }
            return $delta;
        };

        // ===== CASES (mirror of savePreClose) =====
        if ($type === 'sub_order' && !$closeLinkedBtr) {
            // CASE 1: Close remaining on MAIN only
            ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($main);
            $remaining = max(0, $A - $C - $T - $P);
            $precloseDelta($main, $remaining);
            $recomputeStatus($main);
            $main->save();
        }
        elseif ($type === 'btr' && !$propagateToMain) {
            // CASE 4: Close BTR only
            ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($btr);
            $remaining = max(0, $A - $C - $T - $P);
            $precloseDelta($btr, $remaining);
            $recomputeStatus($btr);
            $btr->save();
        }
        elseif ($type === 'btr' && $propagateToMain) {
            // CASE 2: Close BTR, then move same newly closed qty from MAIN.in_transit → MAIN.pre_closed
            ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($btr);
            $btrRemaining = max(0, $A - $C - $T - $P);
            $btrClosedNow = $precloseDelta($btr, $btrRemaining);
            $recomputeStatus($btr);
            $btr->save();

            if ($btrClosedNow > 0 && $main) {
                $moved = $moveTransitToPreclose($main, $btrClosedNow);
                if ($moved > 0) {
                    $precloseDelta($main, $moved);
                }
                $recomputeStatus($main);
                $main->save();
            }
        }
        elseif ($type === 'sub_order' && $closeLinkedBtr) {
            // CASE 3: Close MAIN with its remaining qty, then close linked BTR by SAME qty (capped) and force BTR status=1
            ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($main);
            $mainRemaining = max(0, $A - $C - $T - $P);
            $mainClosedNow = $precloseDelta($main, $mainRemaining);
            $recomputeStatus($main);
            $main->save();

            if ($btr && $mainClosedNow > 0) {
                ['approved'=>$BA,'challan'=>$BC,'in_transit'=>$BT,'pre_closed'=>$BP] = $getNums($btr);
                $btrRemaining = max(0, $BA - $BC - $BT - $BP);
                $btrDelta     = min($mainClosedNow, $btrRemaining);

                if ($btrDelta > 0) {
                    $precloseDelta($btr, $btrDelta);
                }
                $recomputeStatus($btr);
                $btr->pre_closed_status = 1; // explicit
                $btr->save();
            }
        }

        // ---- Optional hooks (same triggers as savePreClose)
        try {
            if (($type === 'sub_order') || ($type === 'btr' && $propagateToMain)) {
                $subOrderDetailId = (int) $id;
                $pdfUrl = $this->generateUnavailableProductsPDF($subOrderDetailId);
                $this->sendUnavaliableProductsNotification($subOrderDetailId, $pdfUrl);
            }
        } catch (\Exception $ex) {
            \Log::error('ReplacePreclose: PDF/WhatsApp error: ' . $ex->getMessage());
        }

        // Always jump to Add New Product for same sub_order
        $redirectUrl = route('products.quickorder', ['sub_order_id' => encrypt($current->sub_order_id)]);
        return redirect($redirectUrl)->with('success_msg', 'Item fully pre-closed. You can add a replacement product now.');

    } catch (\Throwable $e) {
        return back()->with('error', $e->getMessage());
    }
}

public function replacePrecloseItem(Request $request)
{
    // GET-only; no DB::transaction()
    $id   = (int) $request->query('sub_order_details_id', 0);
    $type = (string) $request->query('sub_order_type', ''); // 'sub_order' | 'btr'

    if ($id <= 0) {
        return back()->with('error', 'Invalid sub_order_details_id.');
    }
    if ($type !== 'sub_order' && $type !== 'btr') {
        return back()->with('error', 'Please provide sub_order_type (sub_order or btr).');
    }

    try {
        /** @var SubOrderDetail $current */
        $current = SubOrderDetail::findOrFail($id);

        // BTR child sub_order_id if any (controller ko GET se milega)
        $btrOrderId = $request->query('has_btr_order_id'); // may be null/empty

        // Defaults: btr -> propagateToMain ON; main with BTR -> closeLinkedBtr ON
        $propagateToMain = $request->has('propagate_to_main')
            ? (bool) $request->boolean('propagate_to_main')
            : ($type === 'btr');

        $closeLinkedBtr = $request->has('close_linked_btr')
            ? (bool) $request->boolean('close_linked_btr')
            : ($type === 'sub_order' && !empty($btrOrderId));

        // Identify main/btr rows
        $main = null;  // type='sub_order'
        $btr  = null;  // type='btr'

        if ($type === 'sub_order') {
            $main = $current;
            if (!empty($btrOrderId)) {
                $btr = SubOrderDetail::where([
                    'product_id'   => $main->product_id,
                    'sub_order_id' => $btrOrderId,
                    'type'         => 'btr',
                ])->first();
            }
        } else { // current is btr
            $btr = $current;
            $main = SubOrderDetail::where([
                'product_id'       => $btr->product_id,
                'order_details_id' => $btr->order_details_id,
                'type'             => 'sub_order',
            ])->first();
        }

        // If counterpart missing → coupled action OFF, but flow continue (no abort)
        if ($type === 'btr' && $propagateToMain && !$main) {
            $propagateToMain = false;
        }
        if ($type === 'sub_order' && $closeLinkedBtr && !$btr) {
            $closeLinkedBtr = false;
        }

        // Helpers
        $i = fn($v) => (int) ($v ?? 0);

        $getNums = function (SubOrderDetail $row) use ($i) {
            return [
                'approved'   => $i($row->approved_quantity),
                'challan'    => $i($row->challan_quantity), // NOTE: challan_quantity field
                'in_transit' => $i($row->in_transit),
                'pre_closed' => $i($row->pre_closed),
            ];
        };

        $recomputeStatus = function (SubOrderDetail $row) use ($getNums) {
            ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($row);
            $row->pre_closed_status = (($C + ($P + $T)) >= $A) ? 1 : 0;
        };

        $precloseDelta = function (SubOrderDetail $row, int $add) use ($getNums): int {
            ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($row);
            $remaining = max(0, $A - $C - $T - $P);
            $delta     = max(0, min($add, $remaining));
            if ($delta > 0) {
                $row->pre_closed    = $P + $delta;
                $row->pre_closed_by = $row->warehouse_id;
            }
            return $delta;
        };

        $moveTransitToPreclose = function (SubOrderDetail $row, int $qty) use ($getNums): int {
            ['in_transit'=>$T] = $getNums($row);
            $delta = max(0, min($qty, $T));
            if ($delta > 0) {
                $row->in_transit = $T - $delta;
            }
            return $delta;
        };

        // ===== Cases (fully pre-close + optional BTR/main sync) =====
        if ($type === 'sub_order' && !$closeLinkedBtr) {
            // CASE 1: Close MAIN only (full)
            ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($main);
            $remaining = max(0, $A - $C - $T - $P);
            $precloseDelta($main, $remaining);
            $recomputeStatus($main);
            $main->save();
        }
        elseif ($type === 'btr' && !$propagateToMain) {
            // CASE 4: Close BTR only (full)
            ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($btr);
            $remaining = max(0, $A - $C - $T - $P);
            $precloseDelta($btr, $remaining);
            $recomputeStatus($btr);
            $btr->save();
        }
        elseif ($type === 'btr' && $propagateToMain) {
            // CASE 2: Close BTR (full) → SAME qty MAIN.in_transit se nikaal kar MAIN.pre_closed me add
            ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($btr);
            $btrRemaining = max(0, $A - $C - $T - $P);
            $btrClosedNow = $precloseDelta($btr, $btrRemaining);
            $recomputeStatus($btr);
            $btr->save();

            if ($btrClosedNow > 0 && $main) {
                $moved = $moveTransitToPreclose($main, $btrClosedNow);
                if ($moved > 0) {
                    $precloseDelta($main, $moved);
                }
                $recomputeStatus($main);
                $main->save();
            }
        }
        elseif ($type === 'sub_order' && $closeLinkedBtr) {
            // CASE 3: Close MAIN (full) → SAME qty BTR par close (cap) + BTR status force=1
            ['approved'=>$A,'challan'=>$C,'in_transit'=>$T,'pre_closed'=>$P] = $getNums($main);
            $mainRemaining = max(0, $A - $C - $T - $P);
            $mainClosedNow = $precloseDelta($main, $mainRemaining);
            $recomputeStatus($main);
            $main->save();

            if ($btr && $mainClosedNow > 0) {
                ['approved'=>$BA,'challan'=>$BC,'in_transit'=>$BT,'pre_closed'=>$BP] = $getNums($btr);
                $btrRemaining = max(0, $BA - $BC - $BT - $BP);
                $btrDelta     = min($mainClosedNow, $btrRemaining);
                if ($btrDelta > 0) {
                    $precloseDelta($btr, $btrDelta);
                }
                $recomputeStatus($btr);
                $btr->pre_closed_status = 1; // explicit force
                $btr->save();
            }
        }

        // Optional hooks (same as savePreClose) – keep or remove as you like
        try {
            if (($type === 'sub_order') || ($type === 'btr' && $propagateToMain)) {
                $pdfUrl = $this->generateUnavailableProductsPDF($current->id);
                $this->sendUnavaliableProductsNotification($current->id, $pdfUrl);
            }
        } catch (\Exception $ex) {
            \Log::error('ReplacePreclose: PDF/WhatsApp error: ' . $ex->getMessage());
        }

        // Redirect: Add New Product (Quick Order) of the same sub_order
        $redirectUrl = route('products.quickorder', ['sub_order_id' => encrypt($current->sub_order_id)]);
        return redirect($redirectUrl)->with('success_msg', 'Item fully pre-closed. You can add a replacement product now.');

    } catch (\Throwable $e) {
        return back()->with('error', $e->getMessage());
    }
}

    







}
