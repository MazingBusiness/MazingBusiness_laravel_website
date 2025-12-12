<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Language;
use App\Models\Order;
use App\Models\User;
use App\Models\Address;
use Config;
use Hash;
use PDF;
use Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\WhatsAppWebService;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendWhatsAppMessagesJob;
use App\Http\Controllers\InvoiceController;

class CustomAPIController extends Controller
{
    /**
     * Process order dispatch data and insert into the dispatch_data table.
     */
    protected $whatsAppWebService;

    private function getManagerPhone($managerId)
    {
      $managerData = DB::table('users')
          ->where('id', $managerId)
          ->select('phone')
          ->first();

      return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
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

 




 public function processOrderDispatch()
{
    // Fetch dispatch_id, details, and code from the order_dispatch table
    $dispatchDataList = DB::table('order_dispatch')
        //->where('dispatch_id','D/MUM/1504/24-25')
        ->where('dispatch_id', '!=', '')
        ->whereNotNull('dispatch_id')
        ->whereNotNull('order_no')
        ->select('dispatch_id', 'details', 'code','party_code') // Fetch dispatch_id, details, and code
        ->get();

    // Check if any data is found
    if ($dispatchDataList->isEmpty()) {
        echo "No valid dispatch records found.";
        return;
    }


    $groupId = uniqid('group_', true);
    // Process each dispatch record
    foreach ($dispatchDataList as $dispatchData) {
        $dispatchId = $dispatchData->dispatch_id;
        $dispatchCode = $dispatchData->code;
        $party_code = $dispatchData->party_code;

        // Fetch order_id using the code from the orders table
        $order = DB::table('orders')->where('code', $dispatchCode)->select('id')->first();
        if (!$order) {
            echo "No order found for Dispatch Code: {$dispatchCode}\n";
            continue;
        }
        $orderId = $order->id;

        // Fix and decode the JSON details
        $detailsJson = $this->fixJson($dispatchData->details);
        $details = json_decode($detailsJson, true);

        if (empty($details) || !is_array($details)) {
            echo "Invalid or empty details JSON for Dispatch ID: {$dispatchId}\n";
            continue;
        }

        // Insert or update details into the dispatch_data table
        foreach ($details as $item) {
            // Validate the structure of each item
            if (!isset($item['part_no'], $item['item_name'], $item['gst'], $item['order_qty'], $item['billed_qty'], $item['rate'], $item['bill_amount'])) {
                echo "Invalid item structure for Dispatch ID: {$dispatchId}\n";
                continue;
            }

            // Fetch the product_id from the products table using part_no
            $product = DB::table('products')->where('part_no', $item['part_no'])->select('id')->first();
            if (!$product) {
                echo "No product found for Part No: {$item['part_no']} in Dispatch ID: {$dispatchId}\n";
                continue;
            }
            $productId = $product->id;

            // Calculate the net rate with 18% GST
            $netRate = $item['rate'] * 1.18;

            // Debugging output for net_rate calculation
            echo "Net Rate for Part No: {$item['part_no']} is {$netRate}\n";

            // Check if the record already exists
            $existingRecord = DB::table('dispatch_data')
                ->where('dispatch_id', $dispatchId)
                ->where('part_no', $item['part_no'])
                ->first();

            if ($existingRecord) {
                // Update the existing record
                DB::table('dispatch_data')
                    ->where('dispatch_id', $dispatchId)
                    ->where('part_no', $item['part_no'])
                    ->where('manually_update_item', false) // Add condition to check if manually_update_item is false
                    ->where('manually_cancel_item', false) // Add condition to check if manually_cancel_item is false
                    ->update([
                        'order_id' => $orderId,
                        'product_id' => $productId,
                        'item_name' => $item['item_name'],
                        'gst' => $item['gst'],
                        'hsn' => $item['hsn'] ?? null,
                        'order_qty' => $item['order_qty'],
                        'billed_qty' => $item['billed_qty'],
                        'rate' => $item['rate'],
                        'net_rate' => $netRate, // Insert net rate
                        'disc' => $item['disc'] ?? '0.00',
                        'gross' => $item['gross'] ?? '0.00',
                        'bill_amount' => $item['bill_amount'],
                        'party_code'=>$party_code,
                        //'is_processed'=>false
                    ]);

                echo "Updated item with Part No: {$item['part_no']} for Dispatch ID: {$dispatchId}\n";
            } else {
                // Insert a new record
                DB::table('dispatch_data')->insert([
                    'dispatch_id' => $dispatchId,
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'part_no' => $item['part_no'],
                    'item_name' => $item['item_name'],
                    'gst' => $item['gst'],
                    'hsn' => $item['hsn'] ?? null,
                    'order_qty' => $item['order_qty'],
                    'billed_qty' => $item['billed_qty'],
                    'rate' => $item['rate'],
                    'net_rate' => $netRate, // Insert net rate
                    'disc' => $item['disc'] ?? '0.00',
                    'gross' => $item['gross'] ?? '0.00',
                    'bill_amount' => $item['bill_amount'],
                    'party_code'=>$party_code,
                    'is_processed'=>false
                ]);

                echo "Inserted item with Part No: {$item['part_no']} for Dispatch ID: {$dispatchId} and Order ID: {$orderId}\n";
            }
          
        }

          $this->generateDispatchPDF($orderId, $party_code,$dispatchId,$groupId);
             
    }
 SendWhatsAppMessagesJob::dispatch($groupId);
    echo "Processing of all dispatch records completed.";
}

 public  function trimPartyCode(string $party_code): string
{
    return substr($party_code, 0, 11);
}

public function generateDispatchPDF($orderId, $partyCode,$dispatchId,$groupId)
{

    // Fetch dispatch data from the table
    $dispatchData = DB::table('dispatch_data')
    ->join('products', 'dispatch_data.product_id', '=', 'products.id') // Join with products table
    ->where('dispatch_data.is_processed', false)
    ->where('dispatch_data.order_id', $orderId)
    ->where('dispatch_data.party_code', $partyCode)
    ->where('dispatch_data.dispatch_id', $dispatchId)

    ->select(
        'dispatch_data.dispatch_id',
        'dispatch_data.part_no',
        'dispatch_data.item_name',
        'dispatch_data.order_qty',
        'dispatch_data.billed_qty',
        'dispatch_data.rate',
        'dispatch_data.bill_amount',
        'dispatch_data.part_no', // Needed for update
        'products.slug' // Fetch the slug from products table
    )
    ->get();

    // Check if any dispatch data exists
    if ($dispatchData->isEmpty()) {
        return response()->json(['message' => 'No dispatch data found for the specified order and party.'], 404);
    }

    // Fetch user details using party_code


    $userDetails = DB::table('users')
        ->where('party_code', $this->trimPartyCode($partyCode))
        ->select('company_name','phone','party_code','manager_id')
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

    // Fetch order details
    $order = DB::table('orders')
        ->where('id', $orderId)
        ->select('code', 'created_at')
        ->first();

    // Prepare data for the PDF
    $pdfData = [
        'dispatchData' => $dispatchData,
        'orderId' => $orderId,
        'partyCode' => $partyCode,
        'userDetails' => $userDetails,
        'order' => $order,
        'dispatchId'=>$dispatchId,
    ];

    // Load the PDF view and pass the data
    $pdf = PDF::loadView('backend.invoices.dispatch_product', $pdfData);

    // Define the file name and path
    $fileName = 'dispatch-data-' . $orderId . '-' . uniqid() . '.pdf';
    $filePath = public_path('approved_products_pdf/' . $fileName);

    // Save the PDF to the specified directory
    $pdf->save($filePath);

    // Generate the public URL
    $publicUrl = url('public/approved_products_pdf/' . $fileName);
    $cityName = $this->getCityName($dispatchId);

     // whatsapp sending code start 
          $templateData = [
                    'name' => 'utiltiy_product_dispatch', // Replace with your template name, e.g., 
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
                                ['type' => 'text', 'text' => $cityName],
                                ['type' => 'text', 'text' => $order->created_at],
                                ['type' => 'text', 'text' => $manager_phone],

                            ],
                        ],
                    ],
                ];


                 // Insert message for the user

            DB::table('wa_sales_queue')->insert([
                'group_id' => $groupId,
                'callback_data' => $templateData['name'],
                'recipient_type' => 'individual',
                'to_number' => $userDetails->phone,
                'type' => 'template',
                'file_url' => $publicUrl,
                'content' => json_encode($templateData),
                'status' => 'pending',
                'response' => '',
                'msg_id' => '',
                'msg_status' => '',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            // manager queue
            DB::table('wa_sales_queue')->insert([
                'group_id' => $groupId,
                'callback_data' => $templateData['name'],
                'recipient_type' => 'individual',
                'to_number' => $manager_phone,
                'type' => 'template',
                'file_url' => $publicUrl,
                'content' => json_encode($templateData),
                'status' => 'pending',
                'response' => '',
                'msg_id' => '',
                'msg_status' => '',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update is_processed to true for all dispatched items
            foreach ($dispatchData as $item) {
                DB::table('dispatch_data')
                    ->where('dispatch_id', $item->dispatch_id)
                    ->where('part_no', $item->part_no)
                    ->update(['is_processed' => true]);
            }

                
            //$this->whatsAppWebService = new WhatsAppWebService();
           
          
        // whatsapp sending code end



        return $publicUrl;
}


// bill processing

public function processOrderBill()
{
    // Fetch data from the order_bills table
    $billDataList = DB::table('order_bills')
        //->where('invoice_no', 'DEL/1056/24-25')
        ->whereNotNull('invoice_no')
        ->whereNotNull('dispatch_id')
        ->where('is_processed',false)
        ->select('code', 'invoice_no', 'invoice_date', 'billing_company', 'invoice_amount', 'dispatch_id', 'details')
        ->get();

    if ($billDataList->isEmpty()) {
        echo "No valid bill records found.";
        return;
    }

    // Delete existing records with the same invoice_no
    $invoiceNo = $billDataList[0]->invoice_no;
    DB::table('bills_data')->where('invoice_no', $invoiceNo)->delete();
    echo "Deleted existing records for Invoice No: {$invoiceNo}\n";

    $groupId = uniqid('group_', true);
    foreach ($billDataList as $billData) {
        $dispatchId = $billData->dispatch_id;
        $code = $billData->code;

        $order = DB::table('orders')->where('code', $code)->select('id')->first();
        if (!$order) {
            echo "No order found for Dispatch Code: {$code}\n";
            continue;
        }
        $orderId = $order->id;

        $detailsJson = $this->fixJson($billData->details);
        $details = json_decode($detailsJson, true);

        if (empty($details) || !is_array($details)) {
            echo "Invalid or empty details JSON for Dispatch ID: {$dispatchId}\n";
            continue;
        }

        // Group items by part_no and rate, summing billed_qty
        $groupedDetails = [];
        foreach ($details as $item) {
            if (!isset($item['part_no'], $item['item_name'], $item['rate'], $item['billed_qty'])) {
                echo "Invalid item structure for Dispatch ID: {$dispatchId}\n";
                continue;
            }

            $key = $item['part_no'] . '_' . $item['rate'];
            if (!isset($groupedDetails[$key])) {
                $groupedDetails[$key] = $item;
            } else {
                $groupedDetails[$key]['billed_qty'] += $item['billed_qty'];
            }
        }

        foreach ($groupedDetails as $item) {
            $product = DB::table('products')->where('part_no', $item['part_no'])->select('id')->first();
            if (!$product) {
                echo "No product found for Part No: {$item['part_no']} in Dispatch ID: {$dispatchId}\n";
                continue;
            }

            $productId = $product->id;
            $netRate = $item['rate'] * 1.18;

            // Check if record already exists
            $exists = DB::table('bills_data')
                ->where('dispatch_id', $dispatchId)
                ->where('part_no', $item['part_no'])
                ->where('rate', $item['rate'])
                ->exists();

            if (!$exists) {
                DB::table('bills_data')->insert([
                    'dispatch_id' => $dispatchId,
                    'product_id' => $productId,
                    'order_id' => $orderId,
                    'part_no' => $item['part_no'],
                    'item_name' => $item['item_name'],
                    'gst' => $item['gst'] ?? '0',
                    'hsn' => $item['hsn'] ?? null,
                    'order_qty' => $item['order_qty'] ?? 0,
                    'billed_qty' => $item['billed_qty'],
                    'rate' => $item['rate'],
                    'net_rate' => $netRate,
                    'disc' => $item['disc'] ?? '0.00',
                    'gross' => $item['gross'] ?? '0.00',
                    'bill_amount' => $item['bill_amount'],
                    'invoice_no' => $billData->invoice_no,
                    'invoice_date' => $billData->invoice_date,
                    'billing_company' => $billData->billing_company,
                    'invoice_amount' => $billData->invoice_amount,
                    'is_processed' => false,
                ]);
                echo "Inserted item with Part No: {$item['part_no']} in Dispatch ID: {$dispatchId}\n";
            } else {
                echo "Skipped duplicate item with Part No: {$item['part_no']} in Dispatch ID: {$dispatchId}\n";
            }
        }

        // Generate PDF
        ///$pdfUrl = $this->generateBillInvoicePDF(encrypt($billData->invoice_no), $groupId);
        //echo "PDF URL: {$pdfUrl}\n";
    }

    //SendWhatsAppMessagesJob::dispatch($groupId);
    echo "Processing of all bill records completed.";
}



public function backup_processOrderBill()
{
    // Fetch data from the order_bills table
    $billDataList = DB::table('order_bills')
        ->where('invoice_no', 'DEL/1056/24-25')
        ->whereNotNull('invoice_no')
        ->whereNotNull('dispatch_id')
        ->select('code', 'invoice_no', 'invoice_date', 'billing_company', 'invoice_amount', 'dispatch_id', 'details')
        ->get();

    if ($billDataList->isEmpty()) {
        echo "No valid bill records found.";
        return;
    }

    // Delete existing records with the same invoice_no
    $invoiceNo = $billDataList[0]->invoice_no;
    DB::table('bills_data')->where('invoice_no', $invoiceNo)->delete();
    echo "Deleted existing records for Invoice No: {$invoiceNo}\n";

    $groupId = uniqid('group_', true);
    foreach ($billDataList as $billData) {
        $dispatchId = $billData->dispatch_id;
        $code = $billData->code;

        $order = DB::table('orders')->where('code', $code)->select('id')->first();
        if (!$order) {
            echo "No order found for Dispatch Code: {$code}\n";
            continue;
        }
        $orderId = $order->id;

        $detailsJson = $this->fixJson($billData->details);
        $details = json_decode($detailsJson, true);

        if (empty($details) || !is_array($details)) {
            echo "Invalid or empty details JSON for Dispatch ID: {$dispatchId}\n";
            continue;
        }

        // Group items by part_no and rate, summing billed_qty
        $groupedDetails = [];
        foreach ($details as $item) {
            if (!isset($item['part_no'], $item['item_name'], $item['rate'], $item['billed_qty'])) {
                echo "Invalid item structure for Dispatch ID: {$dispatchId}\n";
                continue;
            }

            $key = $item['part_no'] . '_' . $item['rate'];
            if (!isset($groupedDetails[$key])) {
                $groupedDetails[$key] = $item;
            } else {
                $groupedDetails[$key]['billed_qty'] += $item['billed_qty'];
            }
        }

        foreach ($groupedDetails as $item) {
            $product = DB::table('products')->where('part_no', $item['part_no'])->select('id')->first();
            if (!$product) {
                echo "No product found for Part No: {$item['part_no']} in Dispatch ID: {$dispatchId}\n";
                continue;
            }

            $productId = $product->id;
            $netRate = $item['rate'] * 1.18;

            // Always insert a new record
            DB::table('bills_data')->insert([
                'dispatch_id' => $dispatchId,
                'product_id' => $productId,
                'order_id' => $orderId,
                'part_no' => $item['part_no'],
                'item_name' => $item['item_name'],
                'gst' => $item['gst'] ?? '0',
                'hsn' => $item['hsn'] ?? null,
                'order_qty' => $item['order_qty'] ?? 0,
                'billed_qty' => $item['billed_qty'],
                'rate' => $item['rate'],
                'net_rate' => $netRate,
                'disc' => $item['disc'] ?? '0.00',
                'gross' => $item['gross'] ?? '0.00',
                'bill_amount' => $item['bill_amount'],
                'invoice_no' => $billData->invoice_no,
                'invoice_date' => $billData->invoice_date,
                'billing_company' => $billData->billing_company,
                'invoice_amount' => $billData->invoice_amount,
                'is_processed' => false,
            ]);
            echo "Inserted item with Part No: {$item['part_no']} in Dispatch ID: {$dispatchId}\n";
        }

        // Generate PDF
        $pdfUrl = $this->generateBillInvoicePDF(encrypt($billData->invoice_no), $groupId);
        echo "PDF URL: {$pdfUrl}\n";
    }

    SendWhatsAppMessagesJob::dispatch($groupId);
    echo "Processing of all bill records completed.";
}


public function __org_backup_processOrderBill()
{
    // Fetch data from the order_bills table
    $billDataList = DB::table('order_bills')
        ->whereNotNull('invoice_no')
        ->whereNotNull('dispatch_id')
        ->where('is_processed',false)
        ->select('code', 'invoice_no', 'invoice_date', 'billing_company', 'invoice_amount', 'dispatch_id', 'details')
        ->get();



    // Check if any data is found
    if ($billDataList->isEmpty()) {
        echo "No valid bill records found.";
        return;
    }

    // Process each bill record
    $groupId = uniqid('group_', true);
    foreach ($billDataList as $billData) {

        $dispatchId = $billData->dispatch_id;
        $code = $billData->code;

        // Fetch order_id using the code from the orders table
        $order = DB::table('orders')->where('code', $code)->select('id')->first();
        if (!$order) {
            echo "No order found for Dispatch Code: {$code}\n";
            continue;
        }
        $orderId = $order->id;

        // Decode and fix the JSON details
        $detailsJson = $this->fixJson($billData->details);
        $details = json_decode($detailsJson, true);

        if (empty($details) || !is_array($details)) {
            echo "Invalid or empty details JSON for Dispatch ID: {$dispatchId}\n";
            continue;
        }

        // Insert or update details into the bills_data table
        foreach ($details as $item) {
            
            // Validate the structure of each item
            if (!isset($item['part_no'], $item['item_name'], $item['gst'], $item['order_qty'], $item['billed_qty'], $item['rate'], $item['bill_amount'])) {
                echo "Invalid item structure for Dispatch ID: {$dispatchId}\n";
                continue;
            }

            // Fetch the product_id from the products table using part_no
            $product = DB::table('products')->where('part_no', $item['part_no'])->select('id')->first();
            if (!$product) {
                echo "No product found for Part No: {$item['part_no']} in Dispatch ID: {$dispatchId}\n";
                continue;
            }
            $productId = $product->id;

            // Calculate the net rate with 18% GST
            $netRate = $item['rate'] * 1.18;

            // Check if the record already exists
            $existingRecord = DB::table('bills_data')
                ->where('billing_company', $billData->billing_company)
                ->where('dispatch_id', $dispatchId)
                ->where('part_no', $item['part_no'])
                ->first();

            if ($existingRecord) {
                // Update the existing record
                DB::table('bills_data')
                    ->where('dispatch_id', $dispatchId)
                    ->where('part_no', $item['part_no'])
                     ->where('manually_update_item', false) // Add condition to check if manually_update_item is false
                     ->where('manually_cancel_item', false) // Add condition to check if manually_cancel_item is false
                    ->update([
                        'product_id' => $productId,
                        'order_id' => $orderId,
                        'item_name' => $item['item_name'],
                        'gst' => $item['gst'],
                        'hsn' => $item['hsn'] ?? null,
                        'order_qty' => $item['order_qty'],
                        'billed_qty' => $item['billed_qty'],
                        'rate' => $item['rate'],
                        'net_rate' => $netRate,
                        'disc' => $item['disc'] ?? '0.00',
                        'gross' => $item['gross'] ?? '0.00',
                        'bill_amount' => $item['bill_amount'],
                        'invoice_no' => $billData->invoice_no,
                        'invoice_date' => $billData->invoice_date,
                        'billing_company' => $billData->billing_company,
                        'invoice_amount' => $billData->invoice_amount,
                    ]);

                echo "Updated item with Part No: {$item['part_no']} for Dispatch ID: {$dispatchId}\n";
                
            } else {
                // Insert a new record
                DB::table('bills_data')->insert([
                    'dispatch_id' => $dispatchId,
                    'product_id' => $productId,
                    'order_id' => $orderId,
                    'part_no' => $item['part_no'],
                    'item_name' => $item['item_name'],
                    'gst' => $item['gst'],
                    'hsn' => $item['hsn'] ?? null,
                    'order_qty' => $item['order_qty'],
                    'billed_qty' => $item['billed_qty'],
                    'rate' => $item['rate'],
                    'net_rate' => $netRate,
                    'disc' => $item['disc'] ?? '0.00',
                    'gross' => $item['gross'] ?? '0.00',
                    'bill_amount' => $item['bill_amount'],
                    'invoice_no' => $billData->invoice_no,
                    'invoice_date' => $billData->invoice_date,
                    'billing_company' => $billData->billing_company,
                    'invoice_amount' => $billData->invoice_amount,
                    'is_processed' => false,
                ]);

                echo "Inserted item with Part No: {$item['part_no']} for Dispatch ID: {$dispatchId}\n";
            }
        }

        // Generate the PDF and print the URL
        $pdfUrl = $this->generateBillInvoicePDF(encrypt($billData->invoice_no), $groupId); // Encrypt the invoice_no for the PDF generation method
        echo "PDF URL: {$pdfUrl}\n";
    }


    SendWhatsAppMessagesJob::dispatch($groupId);

    echo "Processing of all bill records completed.";
}


  public function generateBillInvoicePDF($invoice_no,$groupId)
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

      // Define the file name and path
      $fileName = 'invoice-' . str_replace('/', '-', decrypt($invoice_no)) . '-' . uniqid() . '.pdf';
      $filePath = public_path('purchase_history_invoice/' . $fileName);

      // Save the PDF to the public/statements directory
      $pdf->save($filePath);

      // Generate the public URL
      $publicUrl = url('public/purchase_history_invoice/' . $fileName);


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



                 // Insert message for the user

            DB::table('wa_sales_queue')->insert([
                'group_id' => $groupId,
                'callback_data' => $templateData['name'],
                'recipient_type' => 'individual',
                'to_number' => $userDetails->phone,
                'type' => 'template',
                'file_url' => $publicUrl,
                'content' => json_encode($templateData),
                'status' => 'pending',
                'response' => '',
                'msg_id' => '',
                'msg_status' => '',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            // manager queue
            DB::table('wa_sales_queue')->insert([
                'group_id' => $groupId,
                'callback_data' => $templateData['name'],
                'recipient_type' => 'individual',
                'to_number' => $manager_phone,
                'type' => 'template',
                'file_url' => $publicUrl,
                'content' => json_encode($templateData),
                'status' => 'pending',
                'response' => '',
                'msg_id' => '',
                'msg_status' => '',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            //$this->whatsAppWebService = new WhatsAppWebService();

           
           // SendWhatsAppMessagesJob::dispatch($groupId);
          
           


        // whatsapp sending code end

             DB::table('order_bills')
        ->where('invoice_no', decrypt($invoice_no))
        ->update(['is_processed' => true]);

      // Return the public URL as a response
     return $publicUrl;
  }





public function fetchOrderDetailsForDispatch()
{
    // Fetch rows from dispatch_data where order_id = 388
    $dispatchDataList = DB::table('dispatch_data')->where('order_id', 388)->get();

    // Check if any dispatch data is found
    if ($dispatchDataList->isEmpty()) {
        return response()->json(['message' => 'No dispatch data found for Order ID: 388'], 404);
    }

    // Fetch rows from order_details where order_id = 388
    $orderDetailsList = DB::table('order_details')->where('order_id', 388)->get();

    // Check if any order details are found
    if ($orderDetailsList->isEmpty()) {
        return response()->json(['message' => 'No order details found for Order ID: 388'], 404);
    }

    // Output buffer for final result
    $output = '';

    // Iterate through dispatch_data
    foreach ($dispatchDataList as $dispatchData) {
        $dispatchProductId = $dispatchData->product_id;
        $dispatchId = $dispatchData->dispatch_id;
        $dispatchQuantity = $dispatchData->billed_qty; // Assuming billed_qty is dispatch quantity


        // Extract the place of dispatch from the dispatch_id
        $dispatchParts = explode('/', $dispatchId);
        $dispatchPlace = isset($dispatchParts[1]) ? $dispatchParts[1] : "N/A";

        // Search for a matching product_id in order_details
        foreach ($orderDetailsList as $orderDetail) {
            if ($orderDetail->product_id == $dispatchProductId) {
                // Retrieve existing values
                $previousDispatchId = $orderDetail->dispatch_id;
                $previousDispatchQuantity = $orderDetail->dispatch_quantity;
                $previousPlaceOfDispatch = $orderDetail->place_of_dispatch;

                // Log existing values
                $output .= "Previous Dispatch ID: {$previousDispatchId}<br>";
                $output .= "Previous Dispatch Quantity: {$previousDispatchQuantity}<br>";
                $output .= "Previous Place of Dispatch: {$previousPlaceOfDispatch}<br>";

                /*if ($previousDispatchId === $dispatchId) {
                    // Normal update if dispatch_id matches
                    DB::table('order_details')->where('id', $orderDetail->id)->update([
                        'dispatch_id' => $dispatchId,
                        'dispatch_quantity' => $dispatchQuantity,
                        'place_of_dispatch' => $dispatchPlace,
                    ]);

                    $output .= "Normal Update: Updated Order Detail ID: {$orderDetail->id} with product_id: {$dispatchProductId}<br><br>";
                } else {*/

                    // Append new values to previous values
                    $updatedDispatchId = $previousDispatchId ? $previousDispatchId . ',' . $dispatchId : $dispatchId;
                    $updatedDispatchQuantity = $previousDispatchQuantity ? $previousDispatchQuantity . ',' . $dispatchQuantity : $dispatchQuantity;
                    $updatedPlaceOfDispatch = $previousPlaceOfDispatch ? $previousPlaceOfDispatch . ',' . $dispatchPlace : $dispatchPlace;

                    // Update with appended values
                    DB::table('order_details')->where('id', $orderDetail->id)->update([
                        'dispatch_id' => $updatedDispatchId,
                        'dispatch_quantity' => $updatedDispatchQuantity,
                        'place_of_dispatch' => $updatedPlaceOfDispatch,
                    ]);

                    $output .= "Comma-Separated Update: Updated Order Detail ID: {$orderDetail->id} with product_id: {$dispatchProductId}<br><br>";
                    
                //}


                // Break the inner loop after updating the matching record
                break;
            }
        }
    }

    $output .= "Processing completed for Order ID: 388.<br>";

    // Return the output for browser rendering
    return response($output);
}




// approval data

    public function insertApprovedData()
    {
        // Fetch data from the order_approvals table
        $approvalDataList = DB::table('order_approvals')
            ->where('status', 'Approved') // Only process approved orders
            //->where('code', '20241206-16043726') // Add code condition
            //->where('party_code', 'OPEL0200340') // Add party_code condition
            ->where('is_processed', false) // Check for unprocessed orders
            ->select('code', 'status', 'details', 'timestamp','party_code') // Select necessary columns
            ->get();

             //echo "<pre>";
             //print_r($approvalDataList);
             //die();

        // Check if any data is found
        if ($approvalDataList->isEmpty()) {
            echo "No valid approval records found.";
            return;
        }
        $groupId = uniqid('group_', true);
        // Process each approval record
        foreach ($approvalDataList as $approvalData) {
            $code = $approvalData->code;
            $party_code = $approvalData->party_code;

            // Fetch order_id using the code from the orders table
            $order = DB::table('orders')->where('code', $code)->select('id')->first();
            if (!$order) {
                echo "No order found for Approval Code: {$code}\n";
                continue;
            }
            $orderId = $order->id;

            // Decode the JSON details
            $detailsJson = $this->fixJson($approvalData->details);
            $details = json_decode($detailsJson, true);

            if (empty($details)) {
                echo "Invalid or empty details JSON for Order Code: {$code}\n";
                continue;
            }

            // Normalize the details structure
            if (isset($details['part_no'])) {
                $details = [$details]; // Convert single item to an array of one item
            }

            // Insert or update details in the approvals_data table
            foreach ($details as $item) {
                // Validate the structure of each item
                if (!isset($item['part_no'], $item['item_name'], $item['gst'], $item['order_qty'], $item['Rate'], $item['bill_amount'])) {
                    echo "Invalid item structure for Order Code: {$code}\n";
                    continue;
                }

                // Fetch the product_id from the products table using part_no
                $product = DB::table('products')->where('part_no', $item['part_no'])->select('id')->first();
                if (!$product) {
                    echo "No product found for Part No: {$item['part_no']} in Order Code: {$code}\n";
                    continue;
                }
                $productId = $product->id;

                  // Check if the item exists in the order_details table
                $existingItem = DB::table('order_details')
                    ->where('order_id', $orderId)
                    ->where('product_id', $productId)
                    ->exists();

                     // Determine if the item is new
                $isNew = !$existingItem;


                // Calculate the net rate with 18% GST
                $netRate = $item['Rate'] * 1.18;

                // Use updateOrInsert to handle both insert and update scenarios
                DB::table('approvals_data')->updateOrInsert(
                    [
                        // AND condition for matching
                        'order_id' => $orderId,
                        'part_no' => $item['part_no'],
                    ],
                    [
                        'product_id' => $productId,
                        'item_name' => $item['item_name'],
                        'gst' => $item['gst'],
                        'hsn' => $item['hsn'] ?? null,
                        'order_qty' => $item['order_qty'],
                        'rate' => $item['Rate'],
                        'net_rate' => $netRate,
                        'disc' => $item['disc'] ?? '0.00',
                        'gross' => $item['gross'] ?? '0.00',
                        'bill_amount' => $item['bill_amount'],
                        'approval_status' => $approvalData->status,
                        'approval_date' => $approvalData->timestamp,
                        'approved_by' => null, // Add approved_by logic if applicable
                        'party_code'=>$party_code,
                        'is_new' => $isNew // Mark as TRUE if it's a newly added item
                    ]
                );

                echo "Processed item with Part No: {$item['part_no']} for Order Code: {$code}\n";
            }


            // Call the PDF generation function after processing the approval data
            $pdfUrl = $this->generateApprovalPDF($orderId, $party_code,$groupId);
            echo "PDF generated successfully: {$pdfUrl}\n";

            
        }

         SendWhatsAppMessagesJob::dispatch($groupId);

        echo "Insertion or update of all approval records completed.";
    }

   public function generateApprovalPDF($orderId, $partyCode,$groupId)
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


                 // Insert message for the user

            DB::table('wa_sales_queue')->insert([
                'group_id' => $groupId,
                'callback_data' => $templateData['name'],
                'recipient_type' => 'individual',
                'to_number' => $userDetails->phone,
                'type' => 'template',
                'file_url' => $publicUrl,
                'content' => json_encode($templateData),
                'status' => 'pending',
                'response' => '',
                'msg_id' => '',
                'msg_status' => '',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            // manager queue
            DB::table('wa_sales_queue')->insert([
                'group_id' => $groupId,
                'callback_data' => $templateData['name'],
                'recipient_type' => 'individual',
                'to_number' => $manager_phone,
                'type' => 'template',
                'file_url' => $publicUrl,
                'content' => json_encode($templateData),
                'status' => 'pending',
                'response' => '',
                'msg_id' => '',
                'msg_status' => '',
                'created_at' => now(),
                'updated_at' => now()
            ]);

                
            //$this->whatsAppWebService = new WhatsAppWebService();
             // Update is_processed to true after generating the PDF
                DB::table('order_approvals')
                    ->where('code', $order->code)
                    ->where('party_code', $partyCode)
                    ->update(['is_processed' => true]);
           
           
        // whatsapp sending code end


        return $publicUrl;
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
}
