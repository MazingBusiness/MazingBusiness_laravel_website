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

class BillsDataController extends Controller
{
    //

     // this function will return invoice pdf
   public function generateBillInvoicePdfURL($invoice_no)
   {

      // Fetch bills data using the invoice number
      $billsData = DB::table('bills_data')
            
          ->where('invoice_no', decrypt($invoice_no))
          ->select(
              'dispatch_id',
              'part_no',
              'item_name',
              'hsn',
              'billed_qty',
              'rate',
              'bill_amount',
              'invoice_no',
              'invoice_amount',
              'invoice_date',
              'billing_company',
              'product_id',
              'order_id',

              DB::raw('SUM(invoice_amount) OVER () as total_invoice_amount')
          )
          ->get();

      $totalInvoiceAmount = $billsData->first()->invoice_amount ?? 0;
                    

      // Check if the invoice exists
      if ($billsData->isEmpty()) {
          return response()->json(['info' => 'Invoice not found'], 404);
      }

      // Fetch the billing company details from the addresses table
      $billingCompany = $billsData->first()->billing_company;

      

      $orderId = $billsData->first()->order_id;
      $billingDetails = DB::table('addresses')
          ->where('acc_code', $billingCompany)
          ->select('company_name', 'address', 'address_2', 'city', 'postal_code', 'gstin', 'due_amount','dueDrOrCr','overdue_amount','overdueDrOrCr','user_id')
          ->first();


      if (!$billingDetails) {
          return response()->json(['info' => 'Billing details not found'], 404);
      }


        // Fetch manager_id from the users table based on user_id
      $managerId = DB::table('users')
          ->where('id', $billingDetails->user_id)
          ->value('manager_id');
      $manager_phone= $this->getManagerPhone($managerId);


      // Fetch the logistic data from the order_logistics table
      
      $logisticsDetails = DB::table('order_logistics')
      ->where('invoice_no', decrypt($invoice_no))
      ->select('lr_no', 'lr_date', 'no_of_boxes', 'attachment')
      ->first();

      if (!$logisticsDetails) {
          // Set default values
          $logisticsDetails = (object) [
              'lr_no' => '',
              'lr_date' => '',
              'no_of_boxes' => '',
              'attachment' => '#', // Default link when no attachment is available
          ];
      }

      // Extract place of supply from invoice number
      $placePrefix = strtoupper(substr(decrypt($invoice_no), 0, 3));

      // Example data from the Excel
      $branchDetails = [
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

      $branchData = $branchDetails[$placePrefix] ?? null;
      if (!$branchData) {
          return response()->json(['error' => 'Branch details not found for the given invoice'], 404);
      }

      // Get the invoice date from the first record
      $invoiceDate = $billsData->first()->invoice_date;

      // Configuration for PDF (optional)
      $config = [
          'format' => 'A4',
          'orientation' => 'portrait',
          'margin_top' => 10,
          'margin_bottom' => 0,
      ];

      // Generate the PDF
      $pdf = PDF::loadView('backend.invoices.product_invoiced', [
          'billsData' => $billsData,
          'invoice_no' => $invoice_no,
          'totalInvoiceAmount' => $totalInvoiceAmount,
          'placeOfSupply' => $placePrefix,
          'invoiceDate' => $invoiceDate,
          'billingDetails' => $billingDetails,
          'logisticsDetails' => $logisticsDetails,
          'branchDetails' => $branchData, // Pass the branch details to the view
          'manager_phone'=>$manager_phone,
      ], [], $config);

      // Define the file name 
      $fileName = 'invoice-' . str_replace('/', '-', decrypt($invoice_no)) . '-' . uniqid() . '.pdf';


      $filePath = public_path('purchase_history_invoice/' . $fileName);

      // Save the PDF to the public/statements directory
      $pdf->save($filePath);

      // Generate the public URL
      $publicUrl = url('public/purchase_history_invoice/' . $fileName);
    
        // Return the PDF as a download
        return $publicUrl;
      
   }

    private function getCityName($dispatchId) {
        // Extract the city code from the dispatch ID
        preg_match('/[A-Z]{3}/', $dispatchId, $matches);

        // Check if a match was found
        if (!empty($matches)) {
            $cityCode = $matches[0];

            // Map city codes to full city names
            $cityMap = [
                'MUM' => 'Mumbai',
                'DEL' => 'Delhi',
                'KOL' => 'Kolkata',
                // Add more city codes and names here if needed
            ];

            // Return the corresponding city name or a default message
            return $cityMap[$cityCode] ?? 'Unknown City';
        }

        return 'Invalid Dispatch ID';
    }

   public function getWarehouseFromInvoiceNo($invoiceNo)
   {
        // Extract the warehouse code from the invoice_no
        if (strpos($invoiceNo, 'KOL') !== false) {
            return 'Kolkata';
        } elseif (strpos($invoiceNo, 'MUM') !== false) {
            return 'Mumbai';
        } elseif (strpos($invoiceNo, 'DEL') !== false) {
            return 'Delhi';
        } else {
            return 'Unknown';
        }
    }


    public function index(Request $request)
    {

    
      // Access control: Allow only specific users
        $allowedUserIds = [1,180, 169, 25606]; // List of allowed user IDs
        if (!in_array(auth()->user()->id, $allowedUserIds)) {
            // Redirect or abort if the user is not allowed
            return abort(403, 'Unauthorized action.'); // You can customize the response
        }
        // Sorting parameters
        $sort = $request->input('sort', 'bills_data.id'); // Default sort column is bills_data.id
        $direction = $request->input('direction', 'desc'); // Default sort direction

        // Validate sort columns
        $validSortColumns = ['bills_data.id', 'bills_data.invoice_no', 'bills_data.billing_company', 'bills_data.invoice_date', 'orders.code', 'addresses.company_name', 'warehouse'];
        if (!in_array($sort, $validSortColumns)) {
            $sort = 'bills_data.id'; // Fallback to default valid column
        }

        // Query the bills_data table
        $query = DB::table('bills_data')
            ->leftJoin('orders', 'bills_data.order_id', '=', 'orders.id') // Join orders for order code
            ->leftJoin(
                  'addresses',
                  'bills_data.billing_company',
                  '=',
                  'addresses.acc_code'
              ) // Join addresses using billing_company as acc_code
            ->select(
                'bills_data.id', // Include bills_data.id
                'bills_data.invoice_no',
                'bills_data.billing_company',
                'bills_data.invoice_date',
                'orders.code as order_code',
                'bills_data.part_no',
                'bills_data.item_name',
                'bills_data.order_qty',
                'bills_data.billed_qty',
                'bills_data.rate',
                'bills_data.bill_amount',
                'bills_data.invoice_amount',
                'bills_data.net_rate',
                'bills_data.manually_cancel_item',
                'addresses.company_name', // Fetch company_name
            );

        // If sorting by warehouse, handle manually after fetching data
        if ($sort === 'warehouse') {
            $data = $query->get(); // Fetch all rows for sorting
            foreach ($data as $row) {
                $row->warehouse = $this->getWarehouseFromInvoiceNo($row->invoice_no);
            }
            $data = $data->sortBy(function ($row) {
                return $row->warehouse;
            }, SORT_REGULAR, $direction === 'desc');
        } else {
            // Apply sorting to query
            $query->orderBy($sort, $direction);
            $data = $query->get(); // Fetch sorted data
        }

        // Add warehouse information for display
        foreach ($data as $row) {
            $row->warehouse = $this->getWarehouseFromInvoiceNo($row->invoice_no);
        }

        // Search functionality
        if ($request->has('search') && $request->search != '') {
            $searchTerm = strtolower($request->search);

            // Search across fields including invoice_date
            $data = $data->filter(function ($row) use ($searchTerm) {
                $invoiceDateFormatted = $row->invoice_date ? date('Y-m-d', strtotime($row->invoice_date)) : ''; // Format invoice_date for comparison

                return str_contains(strtolower($row->invoice_no), $searchTerm)
                    || str_contains(strtolower($row->billing_company), $searchTerm)
                    || str_contains(strtolower($row->order_code), $searchTerm)
                    || str_contains(strtolower($row->part_no), $searchTerm)
                    || str_contains(strtolower($row->item_name), $searchTerm)
                    || str_contains(strtolower($row->company_name), $searchTerm) // Include company_name in search
                    || str_contains(strtolower($row->warehouse), $searchTerm) // Include warehouse in search
                    || str_contains(strtolower($invoiceDateFormatted), $searchTerm); // Include invoice_date in search
            });
        }

        // Group data by invoice_no
        $groupedData = $data->groupBy('invoice_no');

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
        return view('backend.bills_data.index', [
            'data' => $pagedData,
            'pagination' => $pagination,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }





    private function getManagerPhone($managerId)
    {
      $managerData = DB::table('users')
          ->where('id', $managerId)
          ->select('phone')
          ->first();

      return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
    }

    public function generateBillInvoicePDF($invoice_no)
   {


      // Fetch bills data using the invoice number
      $billsData = DB::table('bills_data')
            
          ->where('invoice_no', decrypt($invoice_no))
          ->select(
              'dispatch_id',
              'part_no',
              'item_name',
              'hsn',
              'billed_qty',
              'rate',
              'bill_amount',
              'invoice_no',
              'invoice_amount',
              'invoice_date',
              'billing_company',
              'product_id',
              'order_id',

              DB::raw('SUM(invoice_amount) OVER () as total_invoice_amount')
          )
          ->get();

      $totalInvoiceAmount = $billsData->first()->invoice_amount ?? 0;
                    

      // Check if the invoice exists
      if ($billsData->isEmpty()) {
          return response()->json(['info' => 'Invoice not found'], 404);
      }

      // Fetch the billing company details from the addresses table
      $billingCompany = $billsData->first()->billing_company;

      

      $orderId = $billsData->first()->order_id;
      $billingDetails = DB::table('addresses')
          ->where('acc_code', $billingCompany)
          ->select('company_name', 'address', 'address_2', 'city', 'postal_code', 'gstin', 'due_amount','dueDrOrCr','overdue_amount','overdueDrOrCr','user_id')
          ->first();


      if (!$billingDetails) {
          return response()->json(['info' => 'Billing details not found'], 404);
      }


        // Fetch manager_id from the users table based on user_id
      $managerId = DB::table('users')
          ->where('id', $billingDetails->user_id)
          ->value('manager_id');
      $manager_phone= $this->getManagerPhone($managerId);


      // Fetch the logistic data from the order_logistics table
      
      $logisticsDetails = DB::table('order_logistics')
      ->where('invoice_no', decrypt($invoice_no))
      ->select('lr_no', 'lr_date', 'no_of_boxes', 'attachment')
      ->first();

      if (!$logisticsDetails) {
          // Set default values
          $logisticsDetails = (object) [
              'lr_no' => '',
              'lr_date' => '',
              'no_of_boxes' => '',
              'attachment' => '#', // Default link when no attachment is available
          ];
      }

      // Extract place of supply from invoice number
      $placePrefix = strtoupper(substr(decrypt($invoice_no), 0, 3));

      // Example data from the Excel
      $branchDetails = [
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
              'phone' => '011-47032910',
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
              'phone' => '011-47032910',
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
              'phone' => '011-47032910',
              'email' => 'acetools505@gmail.com',
          ],
      ];

      $branchData = $branchDetails[$placePrefix] ?? null;
      if (!$branchData) {
          return response()->json(['error' => 'Branch details not found for the given invoice'], 404);
      }

      // Get the invoice date from the first record
      $invoiceDate = $billsData->first()->invoice_date;

      // Configuration for PDF (optional)
      $config = [
          'format' => 'A4',
          'orientation' => 'portrait',
          'margin_top' => 10,
          'margin_bottom' => 0,
      ];

      // Generate the PDF
      $pdf = PDF::loadView('backend.invoices.product_invoiced', [
          'billsData' => $billsData,
          'invoice_no' => $invoice_no,
          'totalInvoiceAmount' => $totalInvoiceAmount,
          'placeOfSupply' => $placePrefix,
          'invoiceDate' => $invoiceDate,
          'billingDetails' => $billingDetails,
          'logisticsDetails' => $logisticsDetails,
          'branchDetails' => $branchData, // Pass the branch details to the view
          'manager_phone'=>$manager_phone,
      ], [], $config);

      // Define the file name 
      $fileName = 'invoice-' . str_replace('/', '-', decrypt($invoice_no)) . '-' . uniqid() . '.pdf';
    
        // Return the PDF as a download
        return $pdf->download($fileName);
      
  }



 public function updateBilledQty(Request $request)
{
    // Validate request
    $request->validate([
        'invoice_no' => 'required|string',
        'part_no' => 'required|string',
        'billed_qty' => 'required|numeric|min:0',
    ]);

    // Get input data
    $invoiceNo = $request->input('invoice_no');
    $partNo = $request->input('part_no');
    $billedQty = $request->input('billed_qty');

    // Update billed_qty and recalculate bill_amount for the specific part
    $row = DB::table('bills_data')
        ->where('invoice_no', $invoiceNo)
        ->where('part_no', $partNo)
        ->first();

    if ($row) {
        $rate = $row->net_rate; // Use net_rate for calculations
        $billAmount = $billedQty * $rate; // Calculate new bill amount

        // Update billed_qty and bill_amount
        DB::table('bills_data')
            ->where('invoice_no', $invoiceNo)
            ->where('part_no', $partNo)
            ->update([
                'billed_qty' => $billedQty,
                'bill_amount' => $billAmount,
                'manually_update_item'=>true
            ]);

        // Recalculate the total invoice amount for all rows in the same invoice
        $totalInvoiceAmount = DB::table('bills_data')
            ->where('invoice_no', $invoiceNo)
            ->sum(DB::raw('billed_qty * net_rate')); // Calculate total

        // Update the invoice_amount in the main invoice record
        DB::table('bills_data')
            ->where('invoice_no', $invoiceNo)
            ->update(['invoice_amount' => $totalInvoiceAmount]);

        return response()->json(['success' => true, 'message' => 'Billed quantity and invoice amount updated successfully!', 'invoice_amount' => (float)$totalInvoiceAmount]);
    }

    return response()->json(['success' => false, 'message' => 'Data not found.']);
}



public function cancelItem(Request $request)
{
    // Validate the request
    $request->validate([
        'invoice_id' => 'required|string',
        'part_no' => 'required|string',
    ]);

    // Fetch the item to cancel
    $item = DB::table('bills_data')
        ->where('invoice_no', $request->invoice_id)
        ->where('part_no', $request->part_no)
        ->first();

    if (!$item) {
        return response()->json(['success' => false, 'message' => 'Item not found.']);
    }

    // Update the 'manually_cancel_item' column
    $updated = DB::table('bills_data')
        ->where('invoice_no', $request->invoice_id)
        ->where('part_no', $request->part_no)
        ->update(['manually_cancel_item' => 1]); // Set to 1 to mark as canceled

    if ($updated) {
        return response()->json(['success' => true, 'message' => 'Item successfully canceled.']);
    }

    return response()->json(['success' => false, 'message' => 'Failed to cancel the item.']);
}


//whatsapp sending invoice

    public function sendInvoiceViaWhatsApp($invoice_no)
    {
      // Fetch bills data using the invoice number

      $billsData = DB::table('bills_data')
            
          ->where('invoice_no', decrypt($invoice_no))
          ->select(
              'dispatch_id',
              'part_no',
              'item_name',
              'hsn',
              'billed_qty',
              'rate',
              'bill_amount',
              'invoice_no',
              'invoice_amount',
              'invoice_date',
              'billing_company',
              'product_id',
              'order_id',

              DB::raw('SUM(invoice_amount) OVER () as total_invoice_amount')
          )
          ->get();

      $totalInvoiceAmount = $billsData->first()->invoice_amount ?? 0;
      // Check if the invoice exists
      if ($billsData->isEmpty()) {
          return response()->json(['success' => false,'message'=>'Invoice not found']);
      }
      // Fetch the billing company details from the addresses table
      $billingCompany = $billsData->first()->billing_company;
      $orderId = $billsData->first()->order_id;
      $billingDetails = DB::table('addresses')
          ->where('acc_code', $billingCompany)
          ->select('company_name', 'address', 'address_2', 'city', 'postal_code', 'gstin', 'due_amount','dueDrOrCr','overdue_amount','overdueDrOrCr','user_id')
          ->first();

      if (!$billingDetails) {
         return response()->json(['success' => false,'message'=>'Billing details not found']);
         
      }
        // Fetch manager_id from the users table based on user_id
      $managerId = DB::table('users')
          ->where('id', $billingDetails->user_id)
          ->value('manager_id');
      $manager_phone= $this->getManagerPhone($managerId);
      // Get the invoice date from the first record
      $invoiceDate = $billsData->first()->invoice_date;
      // Generate the public URL
      //$publicUrl = url('public/purchase_history_invoice/' . $fileName);
      $publicUrl=$this->generateBillInvoicePdfURL($invoice_no);

       $userDetails = DB::table('addresses')->where('acc_code', $billingCompany)->select('company_name','phone')->first();

     $order = DB::table('orders')->where('id', $orderId)->select('id','code','created_at')->first();
     $place_of_dispatch=$this->getCityName($billsData->first()->dispatch_id);


     // whatsapp sending code start 
      $templateData = [
                'name' => 'utility_bills_invoice_template', // Replace with your template name, e.g., 
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
                            ['type' => 'text', 'text' => decrypt($invoice_no)],
                            ['type' => 'text', 'text' => $invoiceDate],
                            ['type' => 'text', 'text' => $totalInvoiceAmount],
                            ['type' => 'text', 'text' => $place_of_dispatch],
                            ['type' => 'text', 'text' => $manager_phone],

                        ],
                    ],
                ],
            ];

          DB::table('order_bills')
                ->where('invoice_no', decrypt($invoice_no))
                ->update(['is_processed' => true]);

        $to = [$userDetails->phone,$manager_phone];
        //$to = ['7044300330'];
        $this->whatsAppWebService = new WhatsAppWebService();

           foreach ($to as $recipient) {
            $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($recipient, $templateData);

            // Log the response for debugging
            Log::info('WhatsApp Response', ['recipient' => $recipient, 'response' => $jsonResponse]);

            // Optionally check for errors in the API response
            if (isset($jsonResponse['messages'][0]['message_status']) && $jsonResponse['messages'][0]['message_status'] !== 'accepted') {
                return response()->json([
                    'success' => false,
                    'error' => 'Message was not accepted by WhatsApp API.',
                    'details' => $jsonResponse
                ], 500);
            }
        }
        return response()->json(['success' => true,'message'=>'PDF sent successfully via WhatsApp.']);
        // whatsapp sending code end

    }



}
