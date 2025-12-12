<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use App\Models\User;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderApproval;
use App\Models\Upload;
use App\Models\Product;
use App\Models\RewardPointsOfUser;
use App\Models\ZohoSetting;
use App\Models\ZohoToken;
use App\Models\UserSalzingStatement;

use PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Jobs\SyncSalzingStatement;
use App\Jobs\SyncSalzingStatementForOpeningBalance;
use App\Services\WhatsAppWebService;
use App\Http\Controllers\AdminStatementController;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\File;


class PurchaseHistoryController extends Controller
{

    private $clientId, $clientSecret, $redirectUri, $orgId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $settings = ZohoSetting::where('status','0')->first();

        $this->clientId = $settings->client_id;
        $this->clientSecret = $settings->client_secret;
        $this->redirectUri = $settings->redirect_uri;
        $this->orgId = $settings->organization_id;
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index()
    {
        $orders = Order::where('user_id', Auth::user()->id)->orderBy('code', 'desc')->paginate(9);
        foreach ($orders as $key => $value) {            
            $ordersApprovalCount = OrderApproval::where('code', $value->code)->count();
            if ($ordersApprovalCount > 0) {
                $ordersApproval = OrderApproval::where('code', $value->code)->first();
                $value->approved = true;
                $details = $ordersApproval->details;
                if (substr($details, 0, 1) !== '[') {
                    $details = '[' . $details;
                }
                if (substr($details, -1) !== ']') {
                    $details = $details . ']';
                }                
                $detailsData = json_decode($details, true);
                $bill_amount = 0.0;
                // foreach($detailsData as $odKey=>$odValue){
                //     $bill_amount = $bill_amount + (float)$odValue['bill_amount'];
                // }                
                $value->bill_amount = $bill_amount;
                $value->order_details = $detailsData;
            } else {
                $value->approved = false;
                $value->bill_amount = "";
                $value->order_details = "";
            }
        }
        // echo "<pre>";print_r($orders);die;
        return view('frontend.user.purchase_history', compact('orders'));
    }

    public function digital_index()
    {
        $orders = DB::table('orders')
                        ->orderBy('code', 'desc')
                        ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                        ->join('products', 'order_details.product_id', '=', 'products.id')
                        ->where('orders.user_id', Auth::user()->id)
                        ->where('products.digital', '1')
                        ->where('order_details.payment_status', 'paid')
                        ->select('order_details.id')
                        ->paginate(15);
        return view('frontend.user.digital_purchase_history', compact('orders'));
    }

    function correct_json($json) {
        // 1. Escape double quotes inside strings
        $json = preg_replace('/"([^"]*?)"/', '"$1\""$2"', $json);
        
        // 2. Remove unescaped double quotes at the end of a string
        $json = preg_replace('/\"([^-]+?)\"/', '\"$1\"', $json);
        
        // 3. Ensure that there are no trailing commas
        $json = preg_replace('/,\s*([\]}])/', '$1', $json);
        
        return $json;
    }

    public function backup_purchase_history_details($id)
    {


        $order = Order::findOrFail(decrypt($id));
        $order->delivery_viewed = 1;
        $order->payment_status_viewed = 1;
        $order->save();


    
        // Fetch the count of order approvals with the same code
        $ordersApprovalCount = OrderApproval::where('code', $order->code)->count();
    
        if ($ordersApprovalCount > 0) {
            // Get the order approval record
            $ordersApproval = OrderApproval::where('code', $order->code)->first();
            // Search in order_dispatch table by code
            $orderDispatch = DB::table('order_dispatch')->where('code', $order->code)->first();

            // echo "<pre>";
            // print_r($orderDispatch);
            // die();

            $details = $ordersApproval->details;
    
            // Preprocess the details JSON string to escape inner quotes inside item_name
            $details = preg_replace_callback(
                '/"item_name":"(.*?)"/',
                function ($matches) {
                    // Escape double quotes inside item_name
                    $cleaned_value = addslashes($matches[1]); 
                    return '"item_name":"' . $cleaned_value . '"';
                },
                $details
            );
    
            // Debugging: Print the fixed JSON string to verify correctness
            // echo "<pre>Fixed JSON string (with properly escaped quotes inside item_name):\n";
            // print_r($details);
            // echo "</pre>";
    
            // Now decode the fixed JSON data
            $detailsData = json_decode($details, true);
    
            // Debugging: Check if json_decode is successful
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "<pre>JSON decode error: " . json_last_error_msg() . "</pre>";
               die();
            }
    
            // Check if JSON is an array after decoding
            if (is_array($detailsData)) {
                $bill_amount = 0.0;
    
                // Iterate through the details data
                foreach ($detailsData as $odKey => $odValue) {
                    $bill_amount += (float)$odValue['bill_amount'];
    
                    // Fetch the product details based on the part number
                    $productDetails = Product::where('part_no', $odValue['part_no'])->first();
    
                    // Ensure the product details exist before assigning slug and product_id
                    if ($productDetails) {
                        $detailsData[$odKey]['slug'] = $productDetails->slug;
                        $detailsData[$odKey]['product_id'] = $productDetails->id;
                    }
                }
    
                // Assign the computed bill amount and the modified details data to the order
                $order->bill_amount = $bill_amount;
                $order->order_details = $detailsData;
            } else {
                // Handle invalid JSON or an empty array
                \Log::error('Invalid JSON in order approval details: ' . json_last_error_msg());
                $order->bill_amount = "";
                $order->order_details = array();
            }
        } else {
            // No approval found, set default values
            $order->bill_amount = "";
            $order->order_details = array();
        }
    
        // Return the view with the order data
        return view('frontend.user.order_details_customer', compact('order'));
    }
    

public function debugJsonDecodeError($json, $context = "JSON Decode Error") {
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<pre>";
        echo "Error in: $context\n";
        echo "JSON Error: " . json_last_error_msg() . "\n";
        echo "Original JSON: \n" . print_r($json, true) . "\n";
        echo "</pre>";
        die(); // Stop execution for debugging
    }
}

function sanitizeJsonWithRegex($jsonString) {
    // Define the regex to escape problematic double quotes
    $regex = '/(?<![ ,\\\\])"(?![:,\\}])/';

    // Apply the regex to escape problematic quotes
    $sanitizedJson = preg_replace($regex, '\"', $jsonString);

    // Validate the fixed JSON
    $decoded = json_decode($sanitizedJson, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 'JSON Error: ' . json_last_error_msg() . "\n";
        die("Problematic JSON: " . $sanitizedJson);
    }

    return json_encode($decoded, JSON_PRETTY_PRINT);
}


public function purchase_history_details($id)
{
    $order = Order::findOrFail(decrypt($id));

    // Fetch order details
    $orderDetails = DB::table('order_details')
        ->join('products', 'order_details.product_id', '=', 'products.id')
        ->leftJoin('approvals_data', function ($join) use ($order) {
            $join->on(
                DB::raw('approvals_data.part_no COLLATE utf8mb3_unicode_ci'),
                '=',
                DB::raw('products.part_no COLLATE utf8mb3_unicode_ci')
            )
            ->where('approvals_data.order_id', '=', $order->id);
        })
        ->where('order_details.order_id', $order->id)
        ->select(
            'order_details.*',
            'products.name as product_name',
            'products.part_no as part_number',
            'approvals_data.order_qty as approved_quantity',
            'approvals_data.net_rate as approval_net_rate',
            'approvals_data.manually_cancel_item'
        )
        ->get();

    // Fetch dispatch data
    $dispatchData = DB::table('dispatch_data')
        ->where('order_id', $order->id)
        ->select('dispatch_id', 'part_no', 'billed_qty', 'bill_amount', 'manually_cancel_item')
        ->get();

    // Fetch bills data
    $billsData = DB::table('bills_data')
        ->where('order_id', $order->id)
        ->select('dispatch_id', 'part_no', 'invoice_no', 'billed_qty', 'rate', 'invoice_amount', 'invoice_date','manually_cancel_item')
        ->get();

    // Process details and attach dispatch/invoice data
    $finalDetails = [];
    foreach ($orderDetails as $detail) {


        //edited on 26 dec 2024 start
         // Check if the approval is manually canceled
        if ($detail->manually_cancel_item ?? false) { // Add manually_cancel_item from approvals_data
            $finalDetails[] = (object) [
                'product_name' => $detail->product_name,
                'part_number' => $detail->part_number,
                'quantity' => $detail->quantity,
                'approved_quantity' => $detail->approved_quantity,
                'billed_qty' => null,
                'rate' => $detail->approval_net_rate,
                'price' => null,
                'invoice_no' => 'N/A',
                'invoice_date' => '',
                'dispatch_id' => 'N/A',
                'status' => 'Canceled',
            ];
            continue; // Skip further processing for canceled items
        }
        //edited on 26 dec 2024 end


        $dispatches = $dispatchData->where('part_no', $detail->part_number);
        $bills = $billsData->where('part_no', $detail->part_number);

        // Check if invoice exists in bills_data
        $hasInvoice = $bills->isNotEmpty();

        if ($hasInvoice) {
            foreach ($bills as $bill) {
                $status = 'Completed'; // Set status as completed for invoiced items
                 if($bill->manually_cancel_item){
                    $status = 'Canceled';
                }

                $finalDetails[] = (object) [
                    'product_name' => $detail->product_name,
                    'part_number' => $detail->part_number,
                    'quantity' => $detail->quantity,
                    'approved_quantity' => $detail->approved_quantity,
                    'billed_qty' => $bill->billed_qty, // Use billed qty from bills_data
                    'rate' => $bill->rate, // Use rate from bills_data
                    'price' => $bill->invoice_amount, // Use invoice amount
                    'invoice_no' => $bill->invoice_no,
                    'invoice_date' => $bill->invoice_date,
                    'dispatch_id' => $bill->dispatch_id,
                    'status' => $status,
                ];
            }
        } else {
            foreach ($dispatches as $dispatch) {

                $status = 'Material in transit';
                if($dispatch->manually_cancel_item){
                    $status = 'Canceled';
                }

                $finalDetails[] = (object) [
                    'product_name' => $detail->product_name,
                    'part_number' => $detail->part_number,
                    'quantity' => $detail->quantity,
                    'approved_quantity' => $detail->approved_quantity,
                    'billed_qty' => $dispatch->billed_qty, // Use billed qty from dispatch_data
                    'rate' => $detail->approval_net_rate,
                    'price' => $dispatch->bill_amount,
                    'invoice_no' => 'N/A',
                    'invoice_date' => '',
                    'dispatch_id' => $dispatch->dispatch_id,
                    'status' => $status,
                ];
            }

            // If no dispatch exists but approved quantity exists
            if ($dispatches->isEmpty()) {
                $status = $detail->approved_quantity > 0 ? 'Pending for Dispatch' : 'Material unavailable';

                $finalDetails[] = (object) [
                    'product_name' => $detail->product_name,
                    'part_number' => $detail->part_number,
                    'quantity' => $detail->quantity,
                    'approved_quantity' => $detail->approved_quantity,
                    'billed_qty' => null,
                    'rate' => $detail->approval_net_rate,
                    'price' => null,
                    'invoice_no' => 'N/A',
                    'invoice_date' => '',
                    'status' => $status,
                ];
            }
        }
    }

    // Calculate total invoiced amount
    $totalInvoicedAmount = collect($finalDetails)->filter(function ($detail) {
        return $detail->status === 'Completed';
    })->sum('price');

    // Set order status
    $statuses = collect($finalDetails)->pluck('status');
    $orderStatus = 'Material unavailable';
    if ($statuses->every(fn($status) => $status === 'Completed')) {
        $orderStatus = 'Completed';
    } elseif ($statuses->contains('Material in transit')) {
        $orderStatus = 'Material in transit';
    } elseif ($statuses->contains('Pending for Dispatch')) {
        $orderStatus = 'Pending for Dispatch';
    }
    // echo "<pre>"; print_r($orderStatus); exit;
    return view('frontend.user.order_details_customer', compact('order', 'finalDetails', 'totalInvoicedAmount', 'orderStatus'));
}


private function determineStatus($detail, $dispatch, $bill)
{
    if ($dispatch && $dispatch->manually_cancel_item) {
        return 'Canceled';
    } 
    // Check if the bill has a manually canceled item
    elseif ($bill && $bill->manually_cancel_item) {
        return 'Canceled';
    } 
    elseif ($bill) {
        return 'Completed';
    } elseif ($dispatch) {
        return 'Material in transit';
    } elseif ($detail->approved_quantity > 0) {
        return 'Pending for Dispatch';
    } else {
        return 'Material unavailable';
    }
}


// Helper function to determine the status
// private function determineStatus($detail, $dispatch, $bill)
// {
//     if ($dispatch && $dispatch->manually_cancel_item) {
//         return 'Canceled';
//     } 
//     elseif ($bill) {
//         return 'Completed';
//     } elseif ($dispatch) {
//         return 'Material in transit';
//     } elseif ($detail->approved_quantity > 0) {
//         return 'Pending for Dispatch';
//     } else {
//         return 'Material unavailable';
//     }
// }





public function t1_purchase_history_details($id)
{
    $order = Order::findOrFail(decrypt($id));
    $order->delivery_viewed = 1;
    $order->payment_status_viewed = 1;
    $order->save();

    // Fetch the count of order approvals with the same code
    $ordersApprovalCount = OrderApproval::where('code', $order->code)->count();

    if ($ordersApprovalCount > 0) {
        // Get the order approval record
        $ordersApproval = OrderApproval::where('code', $order->code)->first();
        $details = $ordersApproval->details;

        // Fetch the dispatch record
        $orderDispatch = DB::table('order_dispatch')->where('code', $order->code)->first();
        $dispatchDetails = [];
        $dispatchLocation = null;

        if ($orderDispatch && isset($orderDispatch->details)) {
            $dispatchDetails = json_decode($orderDispatch->details, true);
        }

        

        if ($orderDispatch && isset($orderDispatch->dispatch_id)) {
            $dispatchParts = explode('/', $orderDispatch->dispatch_id);
            $dispatchCode = $dispatchParts[1] ?? null;
           
        }

        // Decode and preprocess the details from order approvals
       // Fix JSON dynamically before processing
        $fixedDetails = $this->fixJson($details);

        // Decode the fixed details
        $detailsData = $fixedDetails ? json_decode($fixedDetails, true) : [];

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "<pre>JSON decode error: " . json_last_error_msg() . "</pre>";
            die();
        }

        if (is_array($detailsData)) {
            $bill_amount = 0.0;

            // Fetch the `order_bills` JSON details for dynamic billed quantity
            $orderBillsDetails = DB::table('order_bills')
                ->where('code', $order->code)
                ->value('details'); // Fetch JSON from `order_bills`
            $orderBillsData = $orderBillsDetails ? json_decode($orderBillsDetails, true) : [];
            // echo "<pre>";
            // print_r($orderBillsData);
            // die();

            foreach ($detailsData as $key => $detail) {
                $bill_amount += (float) $detail['bill_amount'];

                // Fetch the approved quantity
                $approvedQty = isset($detail['approved_qty']) ? (int) $detail['approved_qty'] : 0;

                // Fetch the dispatched quantity for the part number
                $dispatchedItem = collect($dispatchDetails)->firstWhere('part_no', $detail['part_no']);
                $dispatchedQty = $dispatchedItem['billed_qty'] ?? 0;

                // Fetch billed quantity from `order_bills` if available
                $billedFromOrderBills = collect($orderBillsData)
                    ->firstWhere('part_no', $detail['part_no'])['billed_qty'] ?? $dispatchedQty;

                $detailsData[$key]['approved_qty'] = $approvedQty;
                $detailsData[$key]['billed_qty'] = $billedFromOrderBills > 0 ? $billedFromOrderBills : $dispatchedQty;

                // Fetch product details
                $productDetails = Product::where('part_no', $detail['part_no'])->first();

                if ($productDetails) {
                    $detailsData[$key]['slug'] = $productDetails->slug;
                    $detailsData[$key]['product_id'] = $productDetails->id;
                }
            }

            // Check the overall delivery status
            // Retrieve the invoice details JSON from `order_bills`
            // $orderBillsDetails = DB::table('order_bills')->where('code', $order->code)->value('details');
            // $orderBillsData = $orderBillsDetails ? json_decode($orderBillsDetails, true) : [];

            // Ensure $allInvoiced checks the presence of `part_no` in invoice details
            $allInvoiced = collect($detailsData)->every(function ($item) use ($orderBillsData) {
                $matchingBill = collect($orderBillsData)->firstWhere('part_no', $item['part_no']);
                return $matchingBill !== null; // Ensure `part_no` exists in invoice details
            });

            // Retrieve the dispatch details JSON from `order_dispatch`
            // $orderDispatch = DB::table('order_dispatch')->where('code', $order->code)->first();
            // $dispatchDetails = $orderDispatch ? json_decode($orderDispatch->details, true) : [];
                        // Ensure $allDispatched checks the presence of `part_no` in dispatch details
            $allDispatched = collect($detailsData)->every(function ($item) use ($dispatchDetails) {
                $dispatchedItem = collect($dispatchDetails)->firstWhere('part_no', $item['part_no']);
                return $dispatchedItem !== null; // Ensure `part_no` exists in dispatch details
            });
            if ($allInvoiced) {
                $order->delivery_status = 'invoiced';
            } elseif ($allDispatched) {
                $order->delivery_status = 'dispatched';
            }

            $order->bill_amount = $bill_amount;
            $order->order_details = $detailsData;
        } else {
            $order->bill_amount = "";
            $order->order_details = [];
        }
    } else {
        $order->bill_amount = "";
        $order->order_details = [];
    }

    return view('frontend.user.order_details_customer', compact('order'));
}

/**
 * Fix JSON dynamically by blanking problematic `item_name` but retaining other fields.
 *
 * @param string $jsonString
 * @return string|null
 */
private function fixJson($jsonString)
{
    $jsonString = preg_replace('/[\r\n\t]+/', ' ', $jsonString);
    $decodedArray = json_decode($jsonString, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->processAndFixJson($jsonString);
    }

    foreach ($decodedArray as &$object) {
        if (isset($object['item_name'])) {
            $testJson = json_encode(['item_name' => $object['item_name']]);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $object['item_name'] = ""; // Blank invalid `item_name`
            }
        }
    }

    return json_encode($decodedArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Process and fix invalid JSON string by handling each object separately.
 *
 * @param string $jsonString
 * @return string|null
 */
private function processAndFixJson($jsonString)
{
    preg_match_all('/\{.*?\}/', $jsonString, $matches);
    $fixedArray = [];

    foreach ($matches[0] as $index => $jsonPart) {
        $decoded = json_decode($jsonPart, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = $this->createValidObject($jsonPart);
        }

        $fixedArray[] = $decoded ?: new \stdClass();
    }

    return json_encode($fixedArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Create a valid object from an invalid JSON string.
 *
 * @param string $jsonPart
 * @return array
 */
private function createValidObject($jsonPart)
{
    preg_match_all('/"([^"]+)"\s*:\s*"([^"]*?)"/', $jsonPart, $matches);
    $validObject = [];

    foreach ($matches[1] as $index => $key) {
        $value = $matches[2][$index];

        if ($key === 'item_name') {
            $testJson = json_encode(['item_name' => $value]);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $value = ""; // Blank problematic `item_name`
            }
        }

        $validObject[$key] = $value;
    }

    return $validObject;
}











    public function download(Request $request)
    {
        $product = Product::findOrFail(decrypt($request->id));
        $downloadable = false;
        foreach (Auth::user()->orders as $key => $order) {
            foreach ($order->orderDetails as $key => $orderDetail) {
                if ($orderDetail->product_id == $product->id && $orderDetail->payment_status == 'paid') {
                    $downloadable = true;
                    break;
                }
            }
        }
        if ($downloadable) {
            $upload = Upload::findOrFail($product->file_name);
            if (env('FILESYSTEM_DRIVER') == "s3") {
                return \Storage::disk('s3')->download($upload->file_name, $upload->file_original_name . "." . $upload->extension);
            } else {
                if (file_exists(base_path('public/' . $upload->file_name))) {
                    return response()->download(base_path('public/' . $upload->file_name));
                }
            }
        } else {
            flash(translate('You cannot download this product at this product.'))->success();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function order_cancel($id)
    {
        $order = Order::where('id', $id)->where('user_id', auth()->user()->id)->first();
        if($order && ($order->delivery_status == 'pending' && $order->payment_status == 'unpaid')) {
            $order->delivery_status = 'cancelled';
            $order->save();

            foreach ($order->orderDetails as $key => $orderDetail) {
                $orderDetail->delivery_status = 'cancelled';
                $orderDetail->save();
                product_restock($orderDetail);
            }

            flash(translate('Order has been canceled successfully'))->success();
        } else {
            flash(translate('Something went wrong'))->error();
        }

        return back();
    }

    // Statement

    public function statement()
    {
        $userData = User::where('id', Auth::user()->id)->first();
        $userAddressData = Address::where('user_id', Auth::user()->id)->groupBy('gstin')->orderBy('acc_code','ASC')->get();
        $dueAmount = '0.00';
        $overdueAmount = '0.00';
        foreach($userAddressData as $key=>$value){
            $dueAmount +=$value->due_amount;
            $overdueAmount +=$value->overdue_amount;
        }
        return view('frontend.user.statement', compact('userData','userAddressData','dueAmount','overdueAmount'));
    }

    public function __statement_details($party_code = "", $form_date = "", $to_date = "")
    {
        $party_code = decrypt($party_code);        
        $userAddressData = Address::where('acc_code', $party_code)->first();
        $userData = User::where('id', $userAddressData->user_id)->first();
        // echo "<pre>"; print_r(json_decode($userData->statement_data));die;
        // Overdue Calculation Start
        $overdueAmount = "0";
        $openingBalance="0";
        $openDrOrCr="";
        $closingBalance="0";
        $closeDrOrCr="";
        $dueAmount="0";
        $overdueDateFrom="";
        $overdueAmount="0";
        $overdueDrOrCr="";
        $getData=array();

        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        if ($currentMonth >= 4) {
            $fy_form_date = date('Y-04-01'); // Start of financial year
            $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        } else {
            $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
            $fy_to_date = date('Y-03-31'); // Current year March
        }
        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];        
        $body = [
            'party_code' => $party_code,
            'from_date' => $fy_form_date,
            'to_date' =>  $fy_to_date,
        ];
        \Log::info('Sending request to API For Overdue', [
            'url' => 'https://saleszing.co.in/itaapi/getclientstatement.php',
            'headers' => $headers,
            'body' => $body
        ]);        
        $overdue_response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);        
        \Log::info('Received response from API For Overdue', [
            'status' => $overdue_response->status(),
            'body' => $overdue_response->body()
        ]);
        $getOverdueData = $overdue_response->json();
        $getOverdueData = $getOverdueData['data'];
        if(!empty($getOverdueData)){
            $closingBalanceResult = array_filter($getOverdueData, function ($entry) {
                return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
            });
            $closingEntry = reset($closingBalanceResult);
            $cloasingDrAmount = $closingEntry['dramount'];
            $cloasingCrAmount = $closingEntry['cramount'];          
            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
            if($cloasingCrAmount > 0){
                $drBalanceBeforeOVDate = 0;
                $crBalanceBeforeOVDate = 0;
                $getOverdueData = array_reverse($getOverdueData);
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }else{
                            $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }
                    }
                }
                $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                $overDueMark = array();
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            // $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }elseif(strtotime($ovValue['trn_date']) < strtotime($overdueDateFrom) AND $temOverDueBalance > 0 AND $ovValue['dramount'] != '0.00'){
                            $temOverDueBalance -= $ovValue['dramount'];
                            $date1 = $ovValue['trn_date'];
                            $date2 = $overdueDateFrom;

                            $diff = abs(strtotime($date2) - strtotime($date1));

                            // $years = floor($diff / (365*60*60*24));
                            // $months = floor(($diff - $years * 365*60*60*24) / (30*60*60*24));
                            // $days = floor(($diff - $years * 365*60*60*24 - $months*30*60*60*24) / (60*60*24));

                            // // Initialize an empty array to store non-zero date parts
                            // $dateParts = [];

                            // if ($years > 0) {
                            //     $dateParts[] = "$years years";
                            // }
                            // if ($months > 0) {
                            //     $dateParts[] = "$months months";
                            // }
                            // if ($days > 0) {
                            //     $dateParts[] = "$days days";
                            // }
                            // // Combine the date parts into a string
                            // $dateDifference = implode(', ', $dateParts);
                            $dateDifference = floor($diff / (60 * 60 * 24)).' days';
                            if($temOverDueBalance >= 0){
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Overdue'
                                ];
                            }else{
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Partial Overdue'
                                ];
                            }
                            
                            
                        }
                    }
                }
            }
            if($overdueAmount <= 0){
                $overdueDrOrCr = 'Dr';
                $overdueAmount = 0;
            }else{
                $overdueDrOrCr = 'Cr';
            }
        }      

        // Overdue Calculation End
        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        if ($currentMonth >= 4) {
            $form_date = date('Y-04-01'); // Start of financial year
            $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        } else {
            $form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
            $to_date = date('Y-03-31'); // Current year March
        }
        if ($to_date > $currentDate) {
            $to_date = $currentDate;
        }
        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];        
        $body = [
            'party_code' => $party_code,
            'from_date' => $form_date,
            'to_date' =>  $to_date,
        ];
        \Log::info('Sending request to API', [
            'url' => 'https://saleszing.co.in/itaapi/getclientstatement.php',
            'headers' => $headers,
            'body' => $body
        ]);        
        $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);        
        \Log::info('Received response from API', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        // echo "<pre>"; print_r($response->json());die;

        if ($response->successful()) {
            $getData = $response->json();
            $getData = $getData['data'];
            if(!empty($getData)){
                $openingBalance = "0";
                $closingBalance = "0";
                $openDrOrCr = "";
                $drBalance = "0";
                $crBalance = "0";
                $dueAmount = "0";
                $overDueMarkTrnNos = array_column($overDueMark, 'trn_no'); 
                $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
                $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
                // echo "<pre>"; print_r($overDueMarkTrnNos); die();
                foreach($getData as $gKey=>$gValue){
                    //echo "<pre>";print_r($gValue);
                    if($gValue['ledgername'] == "Opening b/f..."){
                        if($gValue['dramount'] != "0.00"){
                            $openingBalance = $gValue['dramount'];
                            $openDrOrCr = "Dr";
                        }else{
                            $openingBalance = $gValue['cramount'];
                            $openDrOrCr = "Cr";
                        }
                    }else if($gValue['ledgername'] == "closing C/f..."){
                        if($gValue['dramount'] != "0.00"){
                            $closingBalance = $gValue['dramount'];
                            $dueAmount = $gValue['dramount'];
                            $closeDrOrCr = "Dr";
                        }else{
                            $closingBalance = $gValue['cramount'];
                            $closeDrOrCr = "Cr";
                            $dueAmount = $gValue['cramount'];
                        }
                    }
                    if(count($overDueMark) > 0) {
                        $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                        if ($key !== false) {
                            $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                            $getData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                        }
                    }
                    if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                        $drBalance = $drBalance + $gValue['dramount'];
                        $dueAmount = $dueAmount + $gValue['dramount'];
                    } 
                    if($gValue['cramount'] != '0.00' AND $gValue['ledgername'] != 'closing C/f...') {
                        $crBalance = $crBalance + $gValue['cramount'];
                        $dueAmount = $dueAmount - $gValue['cramount'];
                    }
                }
                $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
            }            
            // echo "<pre>"; print_r($getData);die;
            // return view('frontend.user.statement_details', compact('party_code','getData','openingBalance','openDrOrCr','closingBalance','closeDrOrCr','dueAmount','overdueDateFrom','overdueAmount','overdueDrOrCr'));
        }
        return view('frontend.user.statement_details', compact('party_code','getData','openingBalance','openDrOrCr','closingBalance','closeDrOrCr','dueAmount','overdueDateFrom','overdueAmount','overdueDrOrCr'));
    }

    public function statement_details($party_code = "", $form_date = "", $to_date = "")
    {
        $party_code = decrypt($party_code);
        


        // Previous Logic
        // $userAddressData = Address::where('acc_code', $party_code)->first();
        // $userData = User::where('id', $userAddressData->user_id)->first();
        // $statement_data = json_decode($userAddressData->statement_data, true);

        // ----- New Logic Start -----
        $statement_data = array();
        $userData = User::where('party_code', $party_code)->first();
        $userAddress = Address::where('acc_code', $party_code)->first();
        if ($userAddress) {
            $gstin = $userAddress->gstin;
            $userAddressData = Address::where('gstin', $gstin)->get();
        } else {
            $userAddressData = collect(); // Return empty collection if no address found
        }
        foreach ($userAddressData as $uValue) {
            $decodedData = json_decode($uValue->statement_data, true);        
            if (is_array($decodedData)) {
                // Remove "closing C/f......" entries
                $filteredData = array_filter($decodedData, function ($item) {
                    return !isset($item['ledgername']) || stripos($item['ledgername'], 'closing C/f...') === false;
                });        
                $statement_data[$uValue->id] = $filteredData;
            }
        }

        $mergedData = [];
        foreach ($statement_data as $data) {
            $mergedData = array_merge($mergedData, $data);
        }
        usort($mergedData, function ($a, $b) {
            return strtotime($a['trn_date']) - strtotime($b['trn_date']);
        });
        $statement_data = array_values($mergedData);
        $balance = 0;
        foreach ($statement_data as $gKey=>$gValue) {
            if($gValue['ledgername'] == 'Opening b/f...'){
                $balance = $gValue['dramount'] != 0.00 ? $gValue['dramount'] : -$gValue['cramount'];
            }else{
                $balance += (float)$gValue['dramount'] - (float)$gValue['cramount'];
            }
            single_price(trim($balance,'-'));
            $statement_data[$gKey]['running_balance'] = $balance;
            // die;
        }        
        // ----- New Logic End -----
        echo "<pre>"; print_r($statement_data); die;

        $overdueAmount = "0";
        $openingBalance="0";
        $openDrOrCr="";
        $closingBalance="0";
        $closeDrOrCr="";
        $dueAmount="0";
        $overdueDateFrom="";
        $overdueAmount="0";
        $overdueDrOrCr="";
        $getData=array();
        $overDueMark = array();
        $getOverdueData = $statement_data;
        if($statement_data !== NULL){
            $closingBalanceResult = array_filter($getOverdueData, function ($entry) {
                return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
            });
            $closingEntry = reset($closingBalanceResult);
            $cloasingDrAmount = $closingEntry['dramount'];
            $cloasingCrAmount = $closingEntry['cramount'];          
            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
            if($cloasingCrAmount > 0){
                $drBalanceBeforeOVDate = 0;
                $crBalanceBeforeOVDate = 0;
                $getOverdueData = array_reverse($getOverdueData);
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }else{
                            $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }
                    }
                }
                $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){                        
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            // $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }elseif(strtotime($ovValue['trn_date']) <= strtotime($overdueDateFrom) AND $temOverDueBalance > 0 AND $ovValue['dramount'] != '0.00'){
                            $temOverDueBalance -= $ovValue['dramount'];
                            $date1 = $ovValue['trn_date'];
                            $date2 = $overdueDateFrom;
                            $diff = abs(strtotime($date2) - strtotime($date1));
                            $dateDifference = floor($diff / (60 * 60 * 24)).' days';
                            if($temOverDueBalance >= 0){
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Overdue'
                                ];
                            }else{
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Partial Overdue'
                                ];
                            }
                            
                            
                        }
                    }
                }
            }
            if($overdueAmount <= 0){
                $overdueDrOrCr = 'Cr';
                $overdueAmount = 0;
            }else{
                $overdueDrOrCr = 'Dr';
            }

            // Overdue Calculation End
        }        

        if ($statement_data !== NULL) {
            $getData = $statement_data;            
            $openingBalance = "0";
            $closingBalance = "0";
            $openDrOrCr = "";
            $drBalance = "0";
            $crBalance = "0";
            $dueAmount = "0";
            $overDueMarkTrnNos = array_column($overDueMark, 'trn_no'); 
            $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
            $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
            // echo "<pre>"; print_r($overDueMarkTrnNos); die();
            foreach($getData as $gKey=>$gValue){
                //echo "<pre>";print_r($gValue);
                if($gValue['ledgername'] == "Opening b/f..."){
                    if($gValue['dramount'] != "0.00"){
                        $openingBalance = $gValue['dramount'];
                        $openDrOrCr = "Dr";
                    }else{
                        $openingBalance = $gValue['cramount'];
                        $openDrOrCr = "Cr";
                    }
                }else if($gValue['ledgername'] == "closing C/f..."){
                    if($gValue['dramount'] != "0.00"){
                        $closingBalance = $gValue['dramount'];
                        // $dueAmount = $gValue['dramount'];                        
                        // $closeDrOrCr = "Dr";
                        // $closeDrOrCr = "Cr";
                    }else{
                        $closingBalance = $gValue['cramount'];
                        // $dueAmount = $gValue['cramount'];
                        // $closeDrOrCr = "Cr";
                        // $closeDrOrCr = "Dr";
                    }
                }
                if(count($overDueMark) > 0) {
                    $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                    if ($key !== false) {
                        $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                        $getData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                    }
                }
                if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                    $drBalance = $drBalance + $gValue['dramount'];
                    $dueAmount = $dueAmount + $gValue['dramount'];
                } 
                if($gValue['cramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                    $crBalance = $crBalance + $gValue['cramount'];
                    $dueAmount = $dueAmount - $gValue['cramount'];
                }
            }
            $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
        } 
        // Make API calls for payment URLs Edited by dipak start
        $overduePaymentUrl=0;
        $duePaymentUrl=0;
        if ($dueAmount > 0) {
            $duePaymentUrl = $this->generatePaymentUrl($party_code, 'due_amount');
        }

        if ($overdueAmount > 0) {
            $overduePaymentUrl = $this->generatePaymentUrl($party_code, 'overdue_amount');
        }

        $customPaymentUrl = $this->generatePaymentUrl($party_code, 'custom_amount');

       //Edited by dipak end

        return view('frontend.user.statement_details', compact('party_code','getData','openingBalance','openDrOrCr','closingBalance','closeDrOrCr','dueAmount','overdueDateFrom','overdueAmount','overdueDrOrCr','duePaymentUrl','overduePaymentUrl','customPaymentUrl'));
    }

    public function rewards()
    {
        $partyCodeArray = Address::where('acc_code',"!=","")->where('user_id',Auth::user()->id)->pluck('acc_code');
        $getData = RewardPointsOfUser::whereIn('party_code', $partyCodeArray)->whereNull('cancel_reason')->get();
        return view('frontend.user.rewards_details', compact('getData'));
    }

    public function rewardsDownload()
    {
        // Fetch rewards data
        $getData = RewardPointsOfUser::where('party_code', Auth::user()->party_code)
            ->whereNull('cancel_reason')
            ->get();

        // Process rewards data to add narration
        foreach ($getData as $reward) {
            if ($reward->rewards_from === 'Logistic' && !empty($reward->invoice_no)) {
                $billData = DB::table('bills_data')->where('invoice_no', $reward->invoice_no)->first();
                $reward->narration = $billData ? $billData->invoice_amount : 'N/A';
            } elseif ($reward->rewards_from === 'manual') {
                $reward->narration = !empty($reward->notes) ? $reward->notes : '-';
            } else {
                $reward->narration = '-';
            }
        }

        // Exclude 'Total' and 'Closing Balance' rows from calculations
        $rewardRows = $getData->filter(function ($reward) {
            return !in_array($reward->rewards_from, ['Total', 'Closing Balance']);
        });

        // Calculate reward amount
        $rewardAmount = $rewardRows->sum('rewards');

        // Get the last valid row before totals for closing balance
        $lastRow = $rewardRows->last();
        $closing_balance = $lastRow ? $lastRow->rewards : 0;
        $last_dr_or_cr = $lastRow ? strtolower($lastRow->dr_or_cr) : null;

        // Adjust the closing balance based on Dr/Cr logic
        if ($last_dr_or_cr === 'dr') {
            $closing_balance = $rewardAmount;
        } else {
            $closing_balance = -$rewardAmount;
        }

        // User data
        $userData = Auth::user();
        $party_code = $userData->party_code;

        // Generate PDF
        $pdf = PDF::loadView('backend.invoices.rewards_pdf', compact(
            'userData',
            'party_code',
            'getData',
            'rewardAmount', // Ensure this is passed
            'closing_balance',
            'last_dr_or_cr'
        ));

        // Return the PDF for download
        return $pdf->download('rewards_statement.pdf');
    }

    public function getRewardPdfURL($party_code)
    {
        // Fetch rewards data
        $getData = RewardPointsOfUser::where('party_code', $party_code)
            ->whereNull('cancel_reason')
            ->get();

        // Process rewards data to add narration
        foreach ($getData as $reward) {
            if ($reward->rewards_from === 'Logistic' && !empty($reward->invoice_no)) {
                $billData = DB::table('bills_data')->where('invoice_no', $reward->invoice_no)->first();
                $reward->narration = $billData ? $billData->invoice_amount : 'N/A';
            } elseif ($reward->rewards_from === 'manual') {
                $reward->narration = !empty($reward->notes) ? $reward->notes : '-';
            } else {
                $reward->narration = '-';
            }
        }

        // Exclude 'Total' and 'Closing Balance' rows from calculations
        $rewardRows = $getData->filter(function ($reward) {
            return !in_array($reward->rewards_from, ['Total', 'Closing Balance']);
        });

        // Calculate reward amount
        $rewardAmount = $rewardRows->sum('rewards');

        // Get the last valid row before totals for closing balance
        $lastRow = $rewardRows->last();
        $closing_balance = $lastRow ? $lastRow->rewards : 0;
        $last_dr_or_cr = $lastRow ? strtolower($lastRow->dr_or_cr) : null;

        // Adjust the closing balance based on Dr/Cr logic
        if ($last_dr_or_cr === 'dr') {
            $closing_balance = $rewardAmount;
        } else {
            $closing_balance = -$rewardAmount;
        }

        // User data
        $userData = Auth::user();

        // File name and path
        $fileName = 'reward_statement_' . $party_code . '_' . time() . '.pdf';
        $filePath = public_path('reward_pdf/' . $fileName);
        $publicUrl = url('public/reward_pdf/' . $fileName);

        // Generate and save the PDF
        PDF::loadView('backend.invoices.rewards_pdf', compact(
            'userData',
            'party_code',
            'getData',
            'rewardAmount',
            'closing_balance',
            'last_dr_or_cr'
        ))->save($filePath);

        // Return the public URL
        return $publicUrl;
    }


    public function sendRewardWhatsapp(Request $request)
    {
       // $party_code = $request->input('party_code');
        $party_code = $request->query('party_code'); // Get the party_code from the query string

        // Fetch user data
        
        $userData = User::where('party_code', $party_code)->first();

        if (!$userData) {
            return response()->json(['error' => 'Party code is invalid or user not found.'], 400);
        }

        // Generate PDF dynamically (replace with your reward generation logic)
        
       // $publicUrl = 'https://mazingbusiness.com/public/reward_pdf/rewards_statement.pdf';
       $publicUrl= $this->getRewardPdfURL($party_code);
       $fileName = basename($publicUrl);

        // Static example data
       
        $phone = $userData->phone; // Replace with the user's phone number
        //$ref="7044300330";
        $imageUrl="https://mazingbusiness.com/public/reward_pdf/reward_image.jpg";
        // Prepare WhatsApp template data
        $templateData = [
            'name' => 'utility_rewards', // Replace with your template name
            'language' => 'en_US', // Replace with your desired language code
            'components' => [
                // [
                //     'type' => 'header',
                //     'parameters' => [
                //         [
                //             'type' => 'document',
                //             'document' => [
                //                 'link' => $publicUrl,
                //                 'filename' => $fileName,
                //             ],
                //         ],
                //     ],
                // ],

                [
                    'type' => 'header',
                   'parameters' => [
                        ['type' => 'image', 'image' => ['link' => $imageUrl]],
                    ],
                 ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $userData->company_name],
                    ],
                ],
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $fileName, // Button text
                        ],
                    ],
                ],
            ],
        ];

        // WhatsApp Numbers to send the template to
        $whatsappNumbers = [
           
            $phone,
        ];

        // Simulated WhatsApp Web Service Call (replace this with your actual WhatsApp API integration)
        $this->whatsAppWebService = new WhatsAppWebService();
        foreach ($whatsappNumbers as $number) {
            if (!empty($number)) {
                $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($number, $templateData);

                // Parse WhatsApp API response
                if (isset($jsonResponse['messages'][0]['message_status']) && $jsonResponse['messages'][0]['message_status'] === 'accepted') {
                    return response()->json(['message' => 'Reward statement sent successfully via WhatsApp.']);
                } else {
                    $error = $jsonResponse['messages'][0]['message_status'] ?? 'unknown';
                    return response()->json(['error' => "Failed to send reward statement. Status: $error"], 400);
                }
            }
        }

        return response()->json(['error' => 'No valid phone number provided.'], 400);

        
    }






    private function generatePaymentUrl($party_code, $payment_for)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->post('https://mazingbusiness.com/api/v2/payment/generate-url', [
            'json' => [
                'party_code' => $party_code,
                'payment_for' => $payment_for
            ]
        ]);
        $data = json_decode($response->getBody(), true);
        return $data['url'] ?? '';  // Return the generated URL or an empty string if it fails
    }

    public function searchStatementDetails(Request $request){
        $party_code = decrypt($request->input('party_code'));
        $userAddressData = Address::where('acc_code', $party_code)->first();
        $userData = User::where('id', $userAddressData->user_id)->first();
        // Overdue Calculation Start
        $overdueAmount = "0";
        $openingBalance="0";
        $openDrOrCr="";
        $closingBalance="0";
        $closeDrOrCr="";
        $dueAmount="0";
        $overdueDateFrom="";
        $overdueAmount="0";
        $overdueDrOrCr="";
        $getData=array();
        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        $overDueMark = array();
        if ($currentMonth >= 4) {
            $fy_form_date = date('Y-04-01'); // Start of financial year
            $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        } else {
            $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
            $fy_to_date = date('Y-03-31'); // Current year March
        }
        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];        
        $body = [
            'party_code' => $party_code,
            'from_date' => $fy_form_date,
            'to_date' =>  $fy_to_date,
        ];
        \Log::info('Sending request to API For Overdue', [
            'url' => 'https://saleszing.co.in/itaapi/getclientstatement.php',
            'headers' => $headers,
            'body' => $body
        ]);        
        $overdue_response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);        
        \Log::info('Received response from API For Overdue', [
            'status' => $overdue_response->status(),
            'body' => $overdue_response->body()
        ]);
        $getOverdueData = $overdue_response->json();
        $getOverdueData = $getOverdueData['data'];
        if(!empty($getOverdueData)){
            $closingBalanceResult = array_filter($getOverdueData, function ($entry) {
                return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
            });
            $closingEntry = reset($closingBalanceResult);
            $cloasingDrAmount = $closingEntry['dramount'];
            $cloasingCrAmount = $closingEntry['cramount'];          
            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
            if($cloasingCrAmount > 0){
                $drBalanceBeforeOVDate = 0;
                $crBalanceBeforeOVDate = 0;
                $getOverdueData = array_reverse($getOverdueData);
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }else{
                            $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }
                    }
                }
                $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            // $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }elseif(strtotime($ovValue['trn_date']) < strtotime($overdueDateFrom) AND $temOverDueBalance > 0 AND $ovValue['dramount'] != '0.00'){
                            $temOverDueBalance -= $ovValue['dramount'];
                            $date1 = $ovValue['trn_date'];
                            $date2 = $overdueDateFrom;

                            $diff = abs(strtotime($date2) - strtotime($date1));

                            $years = floor($diff / (365*60*60*24));
                            $months = floor(($diff - $years * 365*60*60*24) / (30*60*60*24));
                            $days = floor(($diff - $years * 365*60*60*24 - $months*30*60*60*24) / (60*60*24));

                            // Initialize an empty array to store non-zero date parts
                            $dateParts = [];

                            if ($years > 0) {
                                $dateParts[] = "$years years";
                            }
                            if ($months > 0) {
                                $dateParts[] = "$months months";
                            }
                            if ($days > 0) {
                                $dateParts[] = "$days days";
                            }
                            // Combine the date parts into a string
                            // $dateDifference = implode(', ', $dateParts);
                            $dateDifference = floor($diff / (60 * 60 * 24)).' days';
                            if($temOverDueBalance >= 0){
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Overdue'
                                ];
                            }else{
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Partial Overdue'
                                ];
                            }
                            
                            
                        }
                    }
                }
            }
            if($overdueAmount <= 0){
                $overdueDrOrCr = 'Dr';
                $overdueAmount = 0;
            }else{
                $overdueDrOrCr = 'Cr';
            }
        }
        // Overdue Calculation End


        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        // $currentDate = date('Y-m-d');
        // $currentMonth = date('m');
        // $currentYear = date('Y');
        // if ($currentMonth >= 4) {
        //     $from_date = date('Y-04-01'); // Start of financial year
        //     $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        // } else {
        //     $from_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
        //     $to_date = date('Y-03-31'); // Current year March
        // }
        // if ($to_date > $currentDate) {
        //     $to_date = $currentDate;
        // }
        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];        
        $body = [
            'party_code' => $party_code,
            'from_date' => $from_date,
            'to_date' =>  $to_date,
        ];
        \Log::info('Sending request to API', [
            'url' => 'https://saleszing.co.in/itaapi/getclientstatement.php',
            'headers' => $headers,
            'body' => $body
        ]);        
        $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);        
        \Log::info('Received response from API', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);
        if ($response->successful()) {
            $getData = $response->json();
            $getData = $getData['data'];
            if(!empty($getData)){
                $openingBalance = 0;
                $closingBalance = 0;
                $openDrOrCr = "";
                $drBalance = 0;
                $crBalance = 0;
                $closeDrOrCr="";
                $overDueMarkTrnNos = array_column($overDueMark, 'trn_no'); 
                $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
                $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
                foreach($getData as $gKey=>$gValue){
                    if($gValue['ledgername'] == "Opening b/f..."){
                        if($gValue['dramount'] != "0.00"){
                            $openingBalance = $gValue['dramount'];
                            $openDrOrCr = "Dr";
                        }else{
                            $openingBalance = $gValue['cramount'];
                            $openDrOrCr = "Cr";
                        }
                    }else if($gValue['ledgername'] == "closing C/f..."){
                        if($gValue['dramount'] != "0.00"){
                            $closingBalance = $gValue['dramount'];
                            $closeDrOrCr = "Dr";
                        }else{
                            $closingBalance = $gValue['cramount'];
                            $closeDrOrCr = "Cr";
                        }
                    }
                    if(count($overDueMark) > 0) {
                        $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                        if ($key !== false) {
                            $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                            $getData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                        }
                    }
                }
            }
            // return response()->json(['data' => $getData, 'openingBalance' => $openingBalance, 'closingBalance'=>$closingBalance]);
        }
        $html = view('frontend.partials._table_rows', [
            'data' => $getData,
            'openingBalance' => $openingBalance,
            'closeDrOrCr' => $closeDrOrCr,
            'closingBalance' => $closingBalance
        ])->render();

        return response()->json(['html' => $html, 'openingBalance' => $openingBalance, 'closingBalance'=>$closingBalance]);

    }

    public function refreshStatementDetails(Request $request){
        $party_code = decrypt($request->input('party_code'));
        $userAddressData = Address::where('acc_code', $party_code)->first();
        $userData = User::where('id', $userAddressData->user_id)->first();

        $from_date = '2025-04-01';
        $to_date = date('Y-m-d');

        $contactId = $userAddressData->zoho_customer_id;
            
        // $contactId = '2435622000000562905'; // KOLKATA SUJAUDDIN AND CO
        // $contactId = '2435622000000561958'; // Paul hardware
        // $contactId = '2435622000000545491'; // Suryansh Enterprise
        // $contactId = '2435622000000563217'; // M/S Z.A.MACHINERY
        // $contactId = '2435622000000903541'; // NOBLE MACHINE TOOLS
        // $contactId = '2435622000000629699'; // SHARP INDUSTRIAL AGENCIES
        // $contactId = '2435622000000546376'; // SHIV TOOLS AND MACHINERY
        // $contactId = '2435622000000553931'; // SAI ENTERPRISES
        // $contactId = '2435622000000565516'; // BHAVANA ENTERPRISES            
        // $contactId = '2435622000000562031'; // M/s.Hindustan Traders
        // $contactId = '2435622000000558482';die;

        \Log::info('Update Statement of party '.$contactId.' with cron', [
            'status' => 'Start',
            'party_code' =>  $userAddressData->acc_code
        ]);
        $orgId = $this->orgId;
        // Get Salezing Statement from Database
        $salezingData = UserSalzingStatement::where('zoho_customer_id',$contactId)->first();

        $cleanedStatement = array();
        if($salezingData != NULL){
            // Step 1: Decode the statement
            $salezingStatement = json_decode($salezingData->statement_data, true);
            // Step 2: Clean 'x1' from each item
            $cleanedStatement = array_map(function ($item) {
                unset($item['x1']);
                return $item;
            }, $salezingStatement);
            // Step 3: Remove the last item
            array_pop($cleanedStatement);
            // echo "<pre>"; print_r($cleanedStatement); die;
            $balance = 0.00;
            foreach($cleanedStatement as $gKey=>$gValue){
                $balance += (float)$gValue['dramount'] - (float)$gValue['cramount'];
                $cleanedStatement[$gKey]['running_balance'] = $balance;
            }
        }
        
        // Get Statement from Zoho
        $url = "https://www.zohoapis.in/books/v3/customers/{$contactId}/statements";        
        $response = Http::withHeaders($this->getAuthHeaders(), ['Accept' => 'application/xls'])
        ->get($url, [
            'from_date' => $from_date,
            'to_date' => $to_date,
            'filter_by' => 'Status.All',
            'accept' => 'xls',
            'organization_id' => $orgId,
        ]);
        $data = $response->json();
        if ($response->successful()) {
            // Save the response body as a file
            $fileName = 'zoho_statement_' . now()->format('Ymd_His') . '.xls';
            $folderPath = public_path('zoho_statements');
            // Create the folder if it doesn't exist
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
            $fullPath = $folderPath . '/' . $fileName;
            file_put_contents($fullPath, $response->body());
            
            $getStatementData = json_decode(json_encode($this->readStatementData($fileName)), true);
            $getStatementData = $getStatementData['original']['data'][0];
            $arrayBeautifier = array();
            foreach($getStatementData as $key=>$value){
                $tempArray = array();
                // if($key >= 2 AND $key < 6){                    
                //     $tempArray['trn_no'] = "";
                //     $tempArray['trn_date'] = date('Y-m-d');
                //     $tempArray['vouchertypebasename'] = "";
                //     $tempArray['ledgername'] = $value[0];
                //     $amount = explode('₹',$value[1]);
                //     $tempArray['ledgerid'] = "";
                //     $amount[1] >= 0 ? $tempArray['dramount'] = str_replace(',', '',$amount[1]) : $tempArray['cramount'] = str_replace(',', '',$amount[1]);
                //     $tempArray['narration'] = "";
                //     $arrayBeautifier[] = $tempArray;
                // }else
                if($key > 9){
                    if($value[1] != "" AND  $value[1] != 'Customer Opening Balance'){
                        $tempVarArray = array();
                        if($value[1] == 'Invoice' OR $value[1] == 'Debit Note' OR $value[1] == 'Credit Note'){
                            $tempVarArray = explode(' - ',$value[2]);
                        }else{
                            $tempVarArray = explode('₹',$value[2]);
                        }
                        $tempArray['trn_no'] = trim($tempVarArray[0]);
                        $tempArray['trn_date'] = $value[0];
                        if($value[1] == 'Invoice'){
                            $tempArray['vouchertypebasename'] = "Sales";
                        }elseif($value[1] == 'Debit Note' OR  $value[1] == 'Credit Note'){
                            $tempArray['vouchertypebasename'] = $value[1] ;
                        }elseif($value[1] == 'Payment Received'){
                            $tempArray['vouchertypebasename'] = "Receipt";
                        }elseif($value[1] == 'Payment Refund'){
                            $tempArray['vouchertypebasename'] = "Payment";
                        }elseif($value[1] == 'Customer Opening Balance'){
                            $tempArray['vouchertypebasename'] = "Opening Balance";
                        }
                        
                        $tempArray['ledgername'] = '';
                        // $amount = explode('₹',$value[4]);
                        $tempArray['ledgerid'] = "";
                        
                        if(($value[1] == 'Invoice' OR $value[1] == 'Debit Note') AND $value[3] != ""){
                            if($value[3] >= '0'){
                                $tempArray['dramount'] = (float)str_replace(',', '',$value[3]);
                                $tempArray['cramount'] = (float)0.00;
                                
                            }else{
                                $tempArray['cramount'] = (float)str_replace(',', '',$value[3]);
                                $tempArray['dramount'] = (float)0.00;
                            }
                        }
                        
                        // if($value[1] == 'Customer Opening Balance'){
                        //     if($value[3] != ""){
                        //         $tempArray['dramount'] = str_replace(',', '',$value[3]);
                        //         $tempArray['cramount'] = 0.00;                                
                        //     }else{
                        //         $tempArray['cramount'] = str_replace(',', '',$value[4]);
                        //         $tempArray['dramount'] = 0.00;
                        //     }
                        //     $tempArray['trn_date'] = $value[0];
                        //     $tempArray['ledgername'] = 'Opening b/f...';
                        // }
                        if(($value[1] == 'Payment Refund') AND $value[4] != ""){
                            $tempArray['cramount'] = (float)0.00;
                            $tempArray['dramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                        }

                        if(($value[1] == 'Payment Received') AND $value[4] != ""){
                            $tempArray['cramount'] = (float)str_replace(',', '',$value[4]);
                            $tempArray['dramount'] = (float)0.00;
                        }
                        if($value[1] == 'Credit Note' AND $value[3] != ""){
                            $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[3])));
                            $tempArray['cramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)$crnValue;
                            $tempArray['dramount'] = (float)0.00;
                        }elseif($value[1] == 'Credit Note' AND $value[4] != ""){
                            $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[4])));
                            $tempArray['dramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)$crnValue;
                            $tempArray['cramount'] = (float)0.00;
                        }
                        $tempArray['narration'] = $value[2];
                        $tempArray['running_balance'] = $value[5];
                        $arrayBeautifier[] = $tempArray;
                    }
                    if($value[4] == "Balance Due"){
                        $tempArray['trn_no'] = "";
                        $tempArray['trn_date'] = date('Y-m-d');
                        $tempArray['vouchertypebasename'] = "";
                        $tempArray['ledgername'] = "closing C/f...";
                        $amount = explode('₹',$value[5]);
                        $tempArray['ledgerid'] = "";
                        if($amount[1] >= 0){
                            $tempArray['cramount'] = (float)str_replace(',', '',$amount[1]);
                            $tempArray['dramount'] = (float)0.00;
                        }else{
                            $tempArray['dramount'] = (float)str_replace(',', '',$amount[1]);
                            $tempArray['cramount'] = (float)0.00;
                        }
                        $tempArray['narration'] = "";
                        $arrayBeautifier[] = $tempArray;
                    }
                }
            }

            if(count($cleanedStatement) > 0){
                $arrayBeautifier = array_merge($cleanedStatement, $arrayBeautifier);
            }
            // echo "<pre>"; print_r($arrayBeautifier); die;
            // Start the statement data as like salzing
            $overdueAmount = "0";
            $openingBalance="0";
            $openDrOrCr="";
            $closingBalance="0";
            $closeDrOrCr="";
            $dueAmount="0";
            $overdueDateFrom="";
            $overdueAmount="0";
            $overdueDrOrCr="";
            $overDueMark = array();
            $drBalance = 0;
            $crBalance = 0;
            $getUserData = Address::with('user')->where('zoho_customer_id',$contactId)->first();
            $userData = $getUserData->user;
            // echo "<pre>"; print_r($arrayBeautifier);
            $getOverdueData = $arrayBeautifier;
            $closingBalanceResult = array_filter($getOverdueData, function ($entry) {
                return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
            });
            $closingEntry = reset($closingBalanceResult);
            $cloasingDrAmount = $closingEntry['dramount'];
            $cloasingCrAmount = $closingEntry['cramount'];          
            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
            if($cloasingCrAmount > 0){
                $drBalanceBeforeOVDate = 0;
                $crBalanceBeforeOVDate = 0;
                $getOverdueData = array_reverse($getOverdueData);
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }else{
                            $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }
                    }
                }
                $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                $overDueMark = array();
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            // $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }elseif(strtotime($ovValue['trn_date']) <= strtotime($overdueDateFrom) AND $temOverDueBalance > 0 AND $ovValue['dramount'] != '0.00'){
                            $temOverDueBalance -= $ovValue['dramount'];
                            $date1 = $ovValue['trn_date'];
                            $date2 = $overdueDateFrom;        
                            $diff = abs(strtotime($date2) - strtotime($date1));

                            $dateDifference = floor($diff / (60 * 60 * 24)).' days';
                            if($temOverDueBalance >= 0){
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Overdue'
                                ];
                            }else{
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Pertial Overdue'
                                ];
                            }
                        }
                    }
                }
            }
            if($overdueAmount <= 0){
                $overdueDrOrCr = 'Cr';
                $overdueAmount = 0;
            }else{
                $overdueDrOrCr = 'Dr';
            }

            $getData = $arrayBeautifier;
            if(count($overDueMark) > 0){
                $overDueMarkTrnNos = array_column($overDueMark, 'trn_no');
                $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
                $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
            }
            foreach($getData as $gKey=>$gValue){                
                if($gValue['ledgername'] == "Opening b/f..."){
                    if($gValue['dramount'] != "0.00"){
                        $openingBalance = $gValue['dramount'];
                        $openDrOrCr = "Dr";
                    }else{
                        $openingBalance = $gValue['cramount'];
                        $openDrOrCr = "Cr";
                    }
                }else if($gValue['ledgername'] == "closing C/f..."){
                    if($gValue['dramount'] != "0.00"){
                        $closingBalance = $gValue['dramount'];
                        // $dueAmount = $gValue['dramount'];
                        // $closeDrOrCr = "Dr";
                    }else{
                        $closingBalance = $gValue['cramount'];
                        // $closeDrOrCr = "Cr";
                        // $dueAmount = $gValue['cramount'];
                    }
                }
                if(count($overDueMark) > 0) {
                    $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                    if ($key !== false) {
                        $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                        $getData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                        // echo $gValue['trn_date'];
                    }else{
                        if(isset($getData[$gKey]['overdue_status'])){
                            unset($getData[$gKey]['overdue_status']);
                            unset($getData[$gKey]['overdue_by_day']);
                        }
                    }
                }
                if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                    $drBalance = $drBalance + $gValue['dramount'];
                    $dueAmount = $dueAmount + $gValue['dramount'];
                } 
                if($gValue['cramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                    $crBalance = $crBalance + $gValue['cramount'];
                    $dueAmount = $dueAmount - $gValue['cramount'];
                }
                // echo "<pre>"; print_r($gValue);die;
            }
            // echo "<pre>"; print_r($getData); die;
            $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
            // echo $overdueAmount;
            Address::where('zoho_customer_id',$contactId)
            ->update(
                [
                    'due_amount'      => $dueAmount,
                    'dueDrOrCr'      => $closeDrOrCr,
                    'overdue_amount' => $overdueAmount,
                    'overdueDrOrCr' => $overdueDrOrCr,
                    'statement_data' => json_encode($getData)
                ]
            );
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        } else {
            return response()->json([
                'error' => 'Failed to download Excel.',
                'details' => $response->body(),
                'status' => $response->status()
            ], 500);
        }
        
        \Log::info('Update Statement of party '.$contactId.' with cron', [
            'status' => 'End',
            'party_code' =>  $userAddressData->acc_code
        ]);
        // echo "<pre>Hello"; print_r($data); die;



        $html = view('frontend.partials._table_rows', [
            'data' => $getData,
            'openingBalance' => $openingBalance,
            'closeDrOrCr' => $closeDrOrCr,
            'closingBalance' => $closingBalance
        ])->render();

        return response()->json(['html' => $html, 'openingBalance' => $openingBalance, 'closingBalance'=>$closingBalance, 'due_amount'=>single_price($dueAmount).' '.$closeDrOrCr, 'overdue_amount'=>single_price($overdueAmount).' '.$overdueDrOrCr]);

    }

    public function refreshStatementDetails_backup(Request $request){
        $party_code = decrypt($request->input('party_code'));
        $userAddressData = Address::where('acc_code', $party_code)->first();
        $userData = User::where('id', $userAddressData->user_id)->first();
        // Overdue Calculation Start
        $overdueAmount = "0";
        $openingBalance="0";
        $openDrOrCr="";
        $closingBalance="0";
        $closeDrOrCr="";
        $dueAmount="0";
        $overdueDateFrom="";
        $overdueAmount="0";
        $overdueDrOrCr="";
        $getData=array();
        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        $overDueMark = array();
        // if ($currentMonth >= 4) {
        //     $fy_form_date = date('Y-04-01'); // Start of financial year
        //     $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        // } else {
        //     $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
        //     $fy_to_date = date('Y-03-31'); // Current year March
        // }

        $fy_form_date='2024-04-01';
        $fy_to_date=date('Y-m-d');

        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];        
        $body = [
            'party_code' => $party_code,
            'from_date' => $fy_form_date,
            'to_date' =>  $fy_to_date,
        ];
        \Log::info('Sending request to API For Overdue', [
            'url' => 'https://saleszing.co.in/itaapi/getclientstatement.php',
            'headers' => $headers,
            'body' => $body
        ]);        
        $overdue_response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);        
        \Log::info('Received response from API For Overdue', [
            'status' => $overdue_response->status(),
            'body' => $overdue_response->body()
        ]);
        $getOverdueData = $overdue_response->json();
        $getOverdueData = $getOverdueData['data'];        
        if(!empty($getOverdueData)){
            $closingBalanceResult = array_filter($getOverdueData, function ($entry) {
                return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
            });
            $closingEntry = reset($closingBalanceResult);
            $cloasingDrAmount = $closingEntry['dramount'];
            $cloasingCrAmount = $closingEntry['cramount'];          
            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
            if($cloasingCrAmount > 0){
                $drBalanceBeforeOVDate = 0;
                $crBalanceBeforeOVDate = 0;
                $getOverdueData = array_reverse($getOverdueData);
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }else{
                            $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }
                    }
                }
                $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                // $overDueMark = array();
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            // $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }elseif(strtotime($ovValue['trn_date']) <= strtotime($overdueDateFrom) AND $temOverDueBalance > 0 AND $ovValue['dramount'] != '0.00'){
                            $temOverDueBalance -= $ovValue['dramount'];
                            $date1 = $ovValue['trn_date'];
                            $date2 = $overdueDateFrom;

                            $diff = abs(strtotime($date2) - strtotime($date1));

                            // $years = floor($diff / (365*60*60*24));
                            // $months = floor(($diff - $years * 365*60*60*24) / (30*60*60*24));
                            // $days = floor(($diff - $years * 365*60*60*24 - $months*30*60*60*24) / (60*60*24));

                            // // Initialize an empty array to store non-zero date parts
                            // $dateParts = [];

                            // if ($years > 0) {
                            //     $dateParts[] = "$years years";
                            // }
                            // if ($months > 0) {
                            //     $dateParts[] = "$months months";
                            // }
                            // if ($days > 0) {
                            //     $dateParts[] = "$days days";
                            // }
                            // Combine the date parts into a string
                            // $dateDifference = implode(', ', $dateParts);
                            $dateDifference = floor($diff / (60 * 60 * 24)).' days';
                            if($temOverDueBalance >= 0){
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Overdue'
                                ];
                            }else{
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Partial Overdue'
                                ];
                            }
                            
                            
                        }
                    }
                }
            }
            if($overdueAmount <= 0){
                // $overdueDrOrCr = 'Dr';
                $overdueDrOrCr = 'Cr';
                $overdueAmount = 0;
            }else{
                // $overdueDrOrCr = 'Cr';
                $overdueDrOrCr = 'Dr';
            }
            // Overdue Calculation End
        }

        $from_date = $fy_form_date;
        $to_date = $fy_to_date;

        // $currentDate = date('Y-m-d');
        // $currentMonth = date('m');
        // $currentYear = date('Y');
        // if ($currentMonth >= 4) {
        //     $from_date = date('Y-04-01'); // Start of financial year
        //     $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        // } else {
        //     $from_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
        //     $to_date = date('Y-03-31'); // Current year March
        // }
        // if ($to_date > $currentDate) {
        //     $to_date = $currentDate;
        // }
        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];        
        $body = [
            'party_code' => $party_code,
            'from_date' => $from_date,
            'to_date' =>  $to_date,
        ];
        \Log::info('Sending request to API', [
            'url' => 'https://saleszing.co.in/itaapi/getclientstatement.php',
            'headers' => $headers,
            'body' => $body
        ]);        
        $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);        
        \Log::info('Received response from API', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);
        
        if ($response->successful()) {
            $getData = $response->json();
            $getData = $getData['data'];
            // echo "<pre>"; print_r($getData); die;
            if(!empty($getData)){
                $openingBalance = 0;
                $closingBalance = 0;
                $openDrOrCr = "";
                $drBalance = 0;
                $crBalance = 0;
                $closeDrOrCr="";
                $dueAmount = 0;
                $overDueMarkTrnNos = array_column($overDueMark, 'trn_no'); 
                $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
                $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
                foreach($getData as $gKey=>$gValue){
                    if($gValue['ledgername'] == "Opening b/f..."){
                        if($gValue['dramount'] != "0.00"){
                            $openingBalance = $gValue['dramount'];
                            $openDrOrCr = "Dr";
                        }else{
                            $openingBalance = $gValue['cramount'];
                            $openDrOrCr = "Cr";
                        }
                    }else if($gValue['ledgername'] == "closing C/f..."){
                        if($gValue['dramount'] != "0.00"){
                            $closingBalance = $gValue['dramount'];
                            // $dueAmount = $gValue['dramount'];
                            // $closeDrOrCr = "Dr";
                        }else{
                            $closingBalance = $gValue['cramount'];
                            // $closeDrOrCr = "Cr";
                            // $dueAmount = $gValue['cramount'];
                        }
                    }
                    if(count($overDueMark) > 0) {
                        $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                        if ($key !== false) {
                            $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                            $getData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                        }
                    }
                    if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                        $drBalance = $drBalance + $gValue['dramount'];
                        $dueAmount = $dueAmount + $gValue['dramount'];
                    } 
                    if($gValue['cramount'] != '0.00' AND $gValue['ledgername'] != 'closing C/f...') {
                        $crBalance = $crBalance + $gValue['cramount'];
                        $dueAmount = $dueAmount - $gValue['cramount'];
                    }
                }
                $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
                Address::where('acc_code', $party_code)
                ->update(
                    [
                        'due_amount'      => $dueAmount,
                        'dueDrOrCr'      => $closeDrOrCr,
                        'overdue_amount' => $overdueAmount,
                        'overdueDrOrCr' => $overdueDrOrCr,
                        'statement_data' => json_encode($getData)
                    ]
                );
            }
        }
        $html = view('frontend.partials._table_rows', [
            'data' => $getData,
            'openingBalance' => $openingBalance,
            'closeDrOrCr' => $closeDrOrCr,
            'closingBalance' => $closingBalance
        ])->render();

        return response()->json(['html' => $html, 'openingBalance' => $openingBalance, 'closingBalance'=>$closingBalance, 'due_amount'=>single_price($dueAmount).' '.$closeDrOrCr, 'overdue_amount'=>single_price($overdueAmount).' '.$overdueDrOrCr]);

    }


    public function syncStatementFromSalezing(){
        SyncSalzingStatement::dispatch();
        return response()->json([
            'message' => 'Successfully sync the statement.'
        ]); 
    }

    public function syncSalzingStatementForOpeningBalance(){
        
        SyncSalzingStatementForOpeningBalance::dispatch();
        return response()->json([
            'message' => 'Successfully sync the statement.'
        ]); 
    }

    public function downloadStatementPdf(Request $request)
    {
        $invoiceController = new InvoiceController();
    
        // Capture the JSON response from the statementPdfDownload method
        $response = $invoiceController->statementPdfDownload($request);
        return $response;
    
        // Decode the JSON response (response()->json returns an instance of Illuminate\Http\JsonResponse)
        // $responseData = json_decode($response->getContent(), true); 

        // if ($responseData['status'] === 'success') {
          
        //     $pdf_url = $responseData['message'];
        //     return response()->json(['status' => 'success', 'message' => $pdf_url]);
        // } else {
        //     return response()->json(['status' => 'error', 'message' => 'PDF generation failed'], 500);
        // }
    
        
    }

    public function sendPayNowLink(Request $request)
    {
        try {
            // Step 1: Retrieve the data from the request
            $party_code = $request->input('party_code');
            $paymentUrl = $request->input('payment_url');  // Get the dynamic payment URL
            $paymentAmount = $request->input('payment_amount'); // Get the payment amount
            $paymentFor = $request->input('payment_for'); // Get the payment_for value (due_amount, custom_amount, overdue_amount)

            // Step 2: Get the corresponding party details using `acc_code` (party_code)
            $partyAddress = Address::where('acc_code', $party_code)->first();
            if (!$partyAddress) {
                throw new \Exception("Party not found for the given party_code: $party_code");
            }

            // Step 3: Get the due and overdue amounts from the `addresses` table
            $dueAmount = $partyAddress->due_amount ?? 0;
            $overdueAmount = $partyAddress->overdue_amount ?? 0;

            // Step 4: Prepare the payment amount string based on payment_for type
            if ($paymentFor === 'custom_amount') {
                // If it's a custom amount, show both due and overdue amounts
                $paymentAmount = "Due: ₹{$dueAmount}, Overdue: ₹{$overdueAmount}";
            }

            // Step 5: Get the manager details (Assuming authenticated user has manager_id)
            $user = Auth::user();
            $manager = User::where('id', $user->manager_id)->first();
            if (!$manager) {
                throw new \Exception("Manager not found for the user: " . $user->id);
            }

            // Step 6: Set the customer name, manager phone, and recipient phone
            $customer_name = $partyAddress->company_name;  // Get customer name from addresses table
            $manager_phone = $manager->phone;  // Get the manager's phone number
            $to = $user->phone;  // Use the party's phone or fallback number

             // Extract the part after 'pay-amount/'
              $fileName = substr($paymentUrl, strpos($paymentUrl, "pay-amount/") + strlen("pay-amount/"));
              $button_variable_encode_part=$fileName;

              $adminStatementController = new AdminStatementController();
          $pdf_url=$adminStatementController->generateStatementPdf($party_code, $partyAddress->due_amount, $partyAddress->overdue_amount, $user);
          $fileName1 = basename($pdf_url);
          $button_variable_pdf_filename=$fileName1;

            // Step 7: WhatsApp template data
            $templateData = [
                'name' => 'utility_initial_payment',  // Fixed template name
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $customer_name],
                            ['type' => 'text', 'text' => $paymentAmount],  // Correctly set payment amount
                            ['type' => 'text', 'text' => $manager_phone],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $button_variable_encode_part,  // File name used as button text
                            ],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '1',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $button_variable_pdf_filename,  // File name used as button text
                            ],
                        ],
                    ],
                ],
            ];

            // Convert template data to JSON for logging
            $jsonTemplateData = json_encode($templateData, JSON_PRETTY_PRINT);

            // Step 8: Send the WhatsApp message
            $this->whatsAppWebService = new WhatsAppWebService();
            $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($to, $templateData);

            // Log the JSON request for debugging purposes
            \Log::info('WhatsApp message sent:', ['request' => $jsonResponse]);

            // Return a successful response
            return response()->json(['success' => true, 'message' => ucfirst($paymentFor) . ' Pay Now link processed successfully.']);
        } catch (\Exception $e) {
            \Log::error('Error in sendPayNowLink:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function getAuthHeaders()
    {
        $token = ZohoToken::first();
        if (!$token) {
            abort(403, 'Zoho token not found.');
        }
        // Refresh token if expired
        if (now()->greaterThanOrEqualTo($token->expires_at)) {
            $settings = ZohoSetting::first();
            $refresh = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'refresh_token' => $token->refresh_token,
            ])->json();

            if (isset($refresh['access_token'])) {
                $token->update([
                    'access_token' => $refresh['access_token'],
                    'expires_at' => now()->addSeconds($refresh['expires_in']),
                ]);
            } else {
                abort(403, 'Failed to refresh Zoho token.');
            }
        }
        return [
            'Authorization' => 'Zoho-oauthtoken ' . $token->access_token,
            'Content-Type' => 'application/json',
        ];
    }

    private function readStatementData($filename){
        $filePath = public_path('zoho_statements/' . $filename);
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found.'], 404);
        }
        // Read the file as a Collection
        $data = Excel::toCollection(null, $filePath);
        return response()->json([
            'data' => $data // usually the first sheet
        ]);
    }

}
