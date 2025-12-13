<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Language;
use App\Models\Order;
use App\Models\User;
use App\Models\Address;
use App\Models\Warehouse;
use App\Models\OrderLogistic;
use App\Models\Manager41Challan;
use App\Models\Manager41PurchaseInvoice;

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
use App\Http\Controllers\PurchaseHistoryController;
use App\Http\Controllers\PurchaseOrderController;
use Maatwebsite\Excel\Facades\Excel; // Assuming you're using Laravel Excel for export
use App\Jobs\SendWhatsAppMessagesJob;
use Illuminate\Support\Facades\File;
use Carbon\Carbon; // Make sure to use Carbon for date manipulation
use App\Models\InvoiceOrderDetail;
use App\Models\InvoiceOrder;

use App\Jobs\GenerateStatementPdf;
use App\Services\PdfContentService;

class AdminStatementController extends Controller
{
    //
    protected $whatsAppWebService;

    public function statementPdfDownload(Request $request)
    {
        try {
            $party_code = ($request->party_code); 
        } catch (\Exception $e) {
            Log::error('Decryption error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to decrypt party code.'], 500);
        }

        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');

        if ($currentMonth >= 4) {
            $form_date = date('Y-04-01'); // Start of financial year
            $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year
        } else {
            $form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
            $to_date = date('Y-03-31'); // Current year March
        }

        if ($request->has('from_date')) {
            $form_date = $request->from_date; // Use provided date
        }

        if ($request->has('to_date')) {
            $to_date = $request->to_date; // Use provided date
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
            'to_date' => $to_date,
        ];

        Log::info('Sending request to API', ['body' => $body]);

        $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);      

        Log::info('Received response from API', ['status' => $response->status(), 'body' => $response->body()]);

        if ($response->successful()) {

            $getData = $response->json();
            $getData = $getData['data'];

            $openingBalance = "0";
            $closingBalance = "0";
            $openDrOrCr = "";
            $closeDrOrCr = "";
            $dueAmount = 0;  // Initialize dueAmount
            $overdueAmount = 0;
            $overdueDrOrCr = 'Dr';
            $userData = User::where('party_code', $party_code)->first();
            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));

            // Process the API data to get opening, closing balances, and calculate due amount
            foreach ($getData as $gKey => $gValue) {
                if ($gValue['ledgername'] == "Opening b/f...") {
                    $openingBalance = ($gValue['dramount'] != "0.00") ? $gValue['dramount'] : $gValue['cramount'];
                    $openDrOrCr = ($gValue['dramount'] != "0.00") ? "Dr" : "Cr";
                } elseif ($gValue['ledgername'] == "closing C/f...") {
                    $closingBalance = ($gValue['dramount'] != "0.00") ? $gValue['dramount'] : $gValue['cramount'];
                    $closeDrOrCr = ($gValue['dramount'] != "0.00") ? "Dr" : "Cr";
                    $dueAmount = $gValue['dramount'] != "0.00" ? $gValue['dramount'] : $gValue['cramount'];  // Calculate due amount
                    $overdueAmount = $closingBalance;
                }
            }

            // Overdue calculation logic
            $getOverdueData = array_reverse($getData);
            $drBalanceBeforeOVDate = 0;
            $crBalanceBeforeOVDate = 0;
            $overDueMark = [];

            foreach($getOverdueData as $ovKey => $ovValue){
                if($ovValue['ledgername'] != 'closing C/f...'){
                    if(strtotime($ovValue['trn_date']) <= strtotime($overdueDateFrom)){
                        $drBalanceBeforeOVDate += $ovValue['dramount'];
                        $crBalanceBeforeOVDate += $ovValue['cramount'];
                    }
                }
            }

            $overdueAmount = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;

            // Marking overdue transactions
            foreach($getOverdueData as $ovKey => $ovValue){
                if($ovValue['ledgername'] != 'closing C/f...'){
                    if(strtotime($ovValue['trn_date']) < strtotime($overdueDateFrom) && $ovValue['dramount'] > 0){
                        $overDueMark[] = [
                            'trn_no' => $ovValue['trn_no'],
                            'trn_date' => $ovValue['trn_date'],
                            'overdue_status' => ($overdueAmount > 0) ? 'Overdue' : 'Partial Overdue'
                        ];
                    }
                }
            }

            // Adding overdue status to each transaction in getData
            $overDueMarkTrnNos = array_column($overDueMark, 'trn_no');
            $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_status');

            if (count($overDueMark) > 0) {
                foreach ($getData as $gKey => $gValue) {
                    $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                    if ($key !== false) {
                        $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                    }
                }
            }

            // Generating PDF with overdue data and due amount
            $randomNumber = str_replace('.', '', microtime(true));
            $fileName = 'statement-' . $party_code . '-' . $randomNumber . '.pdf';
            $userId = Auth::id();

            $pdfContentService = new PdfContentService();
            $pdfContentBlock   = $pdfContentService->buildBlockForType('statement');

            $pdf = PDF::loadView('backend.invoices.statement_pdf', compact(
                'party_code',
                'getData',
                'openingBalance',
                'openDrOrCr',
                'closingBalance',
                'closeDrOrCr',
                'form_date',
                'to_date',
                'overdueAmount',
                'overdueDrOrCr',
                'overdueDateFrom',
                'overDueMark',
                'dueAmount' ,// Pass dueAmount to the view
                'userId',
                'pdfContentBlock' // ✅ Blade me use hoga
            ))->save(public_path('statements/' . $fileName));

            $publicUrl = url('public/statements/' . $fileName);
            return response()->json(['status' => 'success', 'message' => $publicUrl], $response->status());
        } else {
            return response()->json(['status' => 'failure'], $response->status());
        }
    }




    // public function statementPdfDownload(Request $request)
    // {
        
    //     // return response()->json([
    //     //     'status' => 'failure', 
    //     //     'error' => "Sorry for the inconvenience, but we're working on it."
    //     // ], 500);
       
    //     try {
    //         $party_code = ($request->party_code); 
    //     } catch (\Exception $e) {
    //         Log::error('Decryption error: ' . $e->getMessage());
    //         return response()->json(['error' => 'Failed to decrypt party code.'], 500);
    //     }
        
    //     $currentDate = date('Y-m-d');
    //     $currentMonth = date('m');
    //     $currentYear = date('Y');

    //     if ($currentMonth >= 4) {
    //         $form_date = date('Y-04-01'); // Start of financial year
    //         $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year
    //     } else {
    //         $form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
    //         $to_date = date('Y-03-31'); // Current year March
    //     }

    //     if ($request->has('from_date')) {
    //         $form_date = $request->from_date; // Use provided date
    //     }

    //     if ($request->has('to_date')) {
    //         $to_date = $request->to_date; // Use provided date
    //     }

    //     if ($to_date > $currentDate) {
    //         $to_date = $currentDate;
    //     }
        
    //     $headers = [
    //         'authtoken' => '65d448afc6f6b',
    //     ];

    //     $body = [
    //         'party_code' => $party_code,
    //         'from_date' => $form_date,
    //         'to_date' => $to_date,
    //     ];

        

    //     Log::info('Sending request to API', ['body' => $body]);

        
        
    //     // Send request to external API
    //     // $response = Http::withHeaders($headers)->post('https://sz.saleszing.co.in/itaapi/getclientstatement.php', $body);
    //     $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);      
        
    //     Log::info('Received response from API', ['status' => $response->status(), 'body' => $response->body()]);

    //     if ($response->successful()) {
    //         $getData = $response->json();
    //         $getData = $getData['data'];

    //         $openingBalance = "0";
    //         $closingBalance = "0";
    //         $openDrOrCr = "";
    //         $closeDrOrCr = "";

    //         // Process the API data to get opening and closing balances
    //         foreach ($getData as $gValue) {
    //             if ($gValue['ledgername'] == "Opening b/f...") {
    //                 $openingBalance = ($gValue['dramount'] != "0.00") ? $gValue['dramount'] : $gValue['cramount'];
    //                 $openDrOrCr = ($gValue['dramount'] != "0.00") ? "Dr" : "Cr";
    //             } elseif ($gValue['ledgername'] == "closing C/f...") {
    //                 $closingBalance = ($gValue['dramount'] != "0.00") ? $gValue['dramount'] : $gValue['cramount'];
    //                 $closeDrOrCr = ($gValue['dramount'] != "0.00") ? "Dr" : "Cr";
    //             }
    //         }

    //         $randomNumber = rand(1000, 9999);
    //     $fileName = 'statement-' . $randomNumber . '.pdf';

    //         // Generate the PDF using the collected data
    //         $pdf = PDF::loadView('backend.invoices.statement_pdf', compact(
    //             'party_code',
    //             'getData',
    //             'openingBalance',
    //             'openDrOrCr',
    //             'closingBalance',
    //             'closeDrOrCr',
    //             'form_date',
    //             'to_date'
    //         ))->save(public_path('statements/' . $fileName));

    //         $publicUrl = url('public/statements/' . $fileName);
    //         return response()->json(['status' => 'success', 'message' => $publicUrl], $response->status());
    //         $message="statement";

    //          $to="6289062983"; 
    //          $phone = Auth::user()->phone;
             
             
    //         $templateData = [
    //             'name' => 'utility_statement_document', // Replace with your template name, 
    //             'language' => 'en_US', // Replace with your desired language code
    //             'components' => [
    //                 // [
    //                 //     'type' => 'header',
    //                 //     'parameters' => [
    //                 //         ['type' => 'document', 'document' => ['link' => $publicUrl,'filename' => $fileName,]],
    //                 //     ],
    //                 // ],
    //                 [
    //                     'type' => 'body',
    //                     'parameters' => [
    //                         ['type' => 'text', 'text' => Auth::user()->company_name],
    //                         ['type' => 'text', 'text' => $message],
    //                     ],
    //                 ],
    //                 [
    //                     'type' => 'button',
    //                     'sub_type' => 'url',
    //                     'index' => '0',
    //                     'parameters' => [
    //                         [
    //                             "type" => "text",
    //                             "text" => $fileName // Replace $button_text with the actual Parameter for the button.
    //                         ],
    //                     ],
    //                 ],
    //             ],
    //         ];

    //         // Send the template message using the WhatsApp web service
    //         $this->whatsAppWebService = new WhatsAppWebService();
    //         $jsonResponse  = $this->whatsAppWebService->sendTemplateMessage($to, $templateData);
    //         // $responseData = json_decode($jsonResponse , true);
           
            
    //         // return $publicUrl;
    //         return response()->json(['status' => 'success', 'message' =>"Statement sent to whatsapp"], $response->status());

    //     } else {
          
    //         return response()->json(['status' => 'faliure'], $response->status());
    //     }
    // }

    
  
    public function getManagersByWarehouse(Request $request)
    {
        // Fetch managers based on the selected warehouse
        $managers = User::join('staff', 'users.id', '=', 'staff.user_id')
        ->where('staff.role_id', 5)
        ->where('users.warehouse_id', $request->warehouse_id)  // Apply condition on users table
        ->select('users.*')
        ->get();

    
        return response()->json($managers); // Return managers as JSON response
    }





 public function getManager($manager_id)
{
    
    $manager = DB::table('users')
          ->where('id', $manager_id)
          ->select('phone','name')
          ->first();

    return $manager;
}

public function assignManager(Request $request)
{
    $request->validate([
        'user_id'    => 'required|exists:users,id',
        'manager_id' => 'required|exists:users,id',
    ]);

    try {
        $user = User::findOrFail($request->user_id);
        $user->manager_id  = $request->manager_id;
        $user->credit_days = $request->credit_days;
        $user->credit_limit= $request->credit_limit;
        if ($request->filled('discount')) {
            $user->discount = $request->discount; // (optional) you are sending it from the modal
        }
        // If "approve" means unban:
        // $user->banned = 0;

        $user->save();

        // If this ever gets called via AJAX:
        if ($request->ajax()) {
            $manager = $this->getManager($user->manager_id);
            return response()->json([
                'success' => true,
                'message' => 'Manager assigned successfully.',
                'manager' => $manager
            ]);
        }

        // Normal form post: go back to the same filtered list
        $backUrl = $request->input('redirect_url', url()->previous());
        return redirect()->to($backUrl)->with('success', 'Manager assigned successfully.');
    } catch (\Throwable $e) {
        \Log::error($e->getMessage());

        if ($request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign manager. Please try again.'
            ], 500);
        }

        $backUrl = $request->input('redirect_url', url()->previous());
        return redirect()->to($backUrl)->with('error', 'Failed to assign manager. Please try again.');
    }
}

public function back_assignManager(Request $request)
{
    // Validate the request
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'manager_id' => 'required|exists:users,id',
    ]);

    try {
        // Find the user by user_id
        $user = User::findOrFail($request->user_id);

        // Update the manager_id
        $user->manager_id = $request->manager_id;
        $user->credit_days = $request->credit_days;
        $user->credit_limit = $request->credit_limit;

        $user->save();

        // Get manager details
        $manager = $this->getManager($user->manager_id);

        // Return success response with manager details
        return response()->json([
            'success' => true,
            'message' => 'Manager assigned successfully.',
            'manager' => $manager
        ]);
    } catch (\Exception $e) {
        // Log the error for debugging
        \Log::error($e->getMessage());

        // Return error response
        return response()->json([
            'success' => false,
            'message' => 'Failed to assign manager. Please try again.'
        ]);
    }
}


    
    public function generateStatementPdf($party_code, $dueAmount, $overdueAmount, $userData)
    {
        // Set date range (financial year or custom range)
        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');

        if ($currentMonth >= 4) {
            $form_date = date('Y-04-01'); // Start of financial year
            $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year
        } else {
            $form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
            $to_date = date('Y-03-31'); // Current year March
        }

        // Override with request date if present
        if (request()->has('from_date')) {
            $form_date = request()->from_date;
        }
        if (request()->has('to_date')) {
            $to_date = request()->to_date;
        }

        // Ensure 'to_date' doesn't exceed current date
        if ($to_date > $currentDate) {
            $to_date = $currentDate;
        }

        // Fetch data from address and user tables
        // $userData = DB::table('users')
        //     ->join('addresses', 'users.id', '=', 'addresses.user_id')
        //     ->where('addresses.acc_code', $party_code)
        //     ->select('users.*', 'addresses.statement_data', 'addresses.overdue_amount', 'addresses.due_amount', 'addresses.address', 'addresses.address_2', 'addresses.postal_code','addresses.dueDrOrCr','addresses.overdueDrOrCr')
        //     ->first();

        // if (!$userData) {
        //     return response()->json(['error' => 'User or address not found'], 404);
        // }

        // // Parse the statement data from the address table
        // $statementData = json_decode($userData->statement_data, true) ?? [];

        // // Address details
        // $address = $userData->address ?? 'Address not found';
        // $address_2 = $userData->address_2 ?? '';
        // $postal_code = $userData->postal_code ?? '';

        // // Variables to store balances
        // $openingBalance = "0";
        // $closingBalance = "0";
        // $openDrOrCr = "";
        // $closeDrOrCr = "";
        // $overdueDrOrCr = 'Dr'; // Default to Dr

        // // Calculate overdue and closing balances using Atanu's logic
        // $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
        // $drBalanceBeforeOVDate = 0;
        // $crBalanceBeforeOVDate = 0;

        // foreach (array_reverse($statementData) as $transaction) {
        //     if ($transaction['ledgername'] != 'closing C/f...') {
        //         if (strtotime($transaction['trn_date']) > strtotime($overdueDateFrom)) {
        //             $crBalanceBeforeOVDate += floatval($transaction['cramount']);
        //         } else {
        //             $drBalanceBeforeOVDate += floatval($transaction['dramount']);
        //             $crBalanceBeforeOVDate += floatval($transaction['cramount']);
        //         }
        //     }

        //     if (isset($transaction['ledgername']) && $transaction['ledgername'] == "Opening b/f...") {
        //         $openingBalance = ($transaction['dramount'] != "0.00") ? floatval($transaction['dramount']) : floatval($transaction['cramount']);
        //         $openDrOrCr = ($transaction['dramount'] != "0.00") ? "Dr" : "Cr";
        //     } elseif (isset($transaction['ledgername']) && $transaction['ledgername'] == "closing C/f...") {
        //         $closingBalance = ($transaction['dramount'] != "0.00") ? floatval($transaction['dramount']) : floatval($transaction['cramount']);
        //         $closeDrOrCr = ($transaction['dramount'] != "0.00") ? "Dr" : "Cr";
        //     }
        // }

        // // Calculate overdue amount
        // $overdueAmount = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
        // if ($overdueAmount <= 0) {
        //     $overdueDrOrCr = 'Cr';
        //     $overdueAmount = 0;
        // } else {
        //     $overdueDrOrCr = 'Dr';
        // }
        $userAddress = Address::where('acc_code', $party_code)->first();
        $userData = User::where('id', $userAddress->user_id)->first();
        

        // Address details
        $address = $userAddress->address ?? 'Address not found';
        $address_2 = $userAddress->address_2 ?? '';
        $postal_code = $userAddress->postal_code ?? '';

        // $userAddressData = Address::where('user_id', $userId)->select('gstin')->whereNotNull('gstin')->distinct()->orderBy('gstin')->get();
        $dueAmount = '0.00';
        $overdueAmount = '0.00';

        $statement_data = array();
        if ($userAddress) {
            $gstin = $userAddress->gstin;
            $userAddressDatas = Address::where('user_id', $userData->id)->where('gstin', $gstin)->get();
        } else {
            $userAddressDatas = collect(); // Return empty collection if no address found
        }
        foreach ($userAddressDatas as $uValue) {
            $decodedData = json_decode($uValue->statement_data, true);
            // echo "<pre>"; print_r($decodedData); die;
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
            $statement_data[$gKey]['running_balance'] = $balance;
        }
        
        if(isset($balance)){
            $tempArray = array();
            $tempArray['trn_no'] = "";
            $tempArray['trn_date'] = date('Y-m-d');
            $tempArray['vouchertypebasename'] = "";
            $tempArray['ledgername'] = "closing C/f...";
            // $amount = explode('₹',$value[5]);
            $tempArray['ledgerid'] = "";
            if($balance >= 0){
                $tempArray['cramount'] = (float)str_replace(',', '',$balance);
                $tempArray['dramount'] = (float)0.00;
            }else{
                $tempArray['dramount'] = (float)str_replace(',', '',$balance);
                $tempArray['cramount'] = (float)0.00;
            }
            $tempArray['narration'] = "";
            $statement_data[] = $tempArray;
        }

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
            // echo "<pre>"; print_r($statement_data); die();
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
                }else{
                    if(isset($getData[$gKey]['overdue_status'])){ 
                        $getData[$gKey]['overdue_status']=""; 
                    }
                    if(isset($getData[$gKey]['overdue_by_day'])){ 
                        $getData[$gKey]['overdue_by_day'] = ""; 
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
        
        $statementData = $getData;
        
        // echo "<pre>"; print_r($statementData); die;

        // Prepare and generate the PDF
        $randomNumber = str_replace('.', '', microtime(true));
        $fileName = 'statement-' . $party_code . '-' . $randomNumber . '.pdf';
        // echo "<pre>";print_r($statement_data); die;

        $jobData = [
            'userData' => $userData,
            'party_code' => $party_code,
            'statementData' => $statementData,
            'openingBalance' => $openingBalance,
            'openDrOrCr' => $openDrOrCr,
            'closingBalance' => $closingBalance,
            'closeDrOrCr' => $closeDrOrCr,
            'form_date' => $form_date,
            'to_date' => $to_date,
            'overdueAmount' => $overdueAmount,
            'overdueDrOrCr' => $overdueDrOrCr,
            'dueAmount' => $dueAmount,
            'address' => $address,
            'address_2' => $address_2,
            'postal_code' => $postal_code,
        ];

        GenerateStatementPdf::dispatch($jobData, $fileName);
        return url('public/statements/' . $fileName);
        // return response()->json([
        //     'message' => 'PDF generation started. You will be able to download it shortly.',
        //     'file_url' => url('statements/' . $fileName)
        // ]);
        


        // Load the Blade view for the PDF generation
        // $pdf = PDF::loadView('backend.invoices.statement_pdf', compact(
        //     'userData',
        //     'party_code',
        //     'statementData',
        //     'openingBalance',
        //     'openDrOrCr',
        //     'closingBalance',
        //     'closeDrOrCr',
        //     'form_date',
        //     'to_date',
        //     'overdueAmount',
        //     'overdueDrOrCr',
        //     'dueAmount',
        //     'address',     // Pass address details to the view
        //     'address_2',
        //     'postal_code'
        // ))->save(public_path('statements/' . $fileName));
        // // echo "Hello";die;

        // // Return the public URL of the PDF
        // return url('public/statements/' . $fileName);
    }

    

    

    /**
     * Function to calculate due and overdue amounts for a party code
     */
    private function calculateDueAndOverdueAmounts($party_code, $userData)
{
    $currentDate = date('Y-m-d');
    $currentMonth = date('m');
    $currentYear = date('Y');

    if ($currentMonth >= 4) {
        $form_date = date('Y-04-01'); // Start of financial year
        $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year
    } else {
        $form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
        $to_date = date('Y-03-31'); // Current year March
    }

    // Adjust the date range if provided
    if (request()->has('from_date')) {
        $form_date = request()->from_date; 
    }

    if (request()->has('to_date')) {
        $to_date = request()->to_date; 
    }

    if ($to_date > $currentDate) {
        $to_date = $currentDate;
    }

    $headers = [
        'authtoken' => '65d448afc6f6b',  // API auth token
    ];

    $body = [
        'party_code' => $party_code,
        'from_date' => $form_date,
        'to_date' => $to_date,
    ];

    // Make API request (or replace this with your data fetching logic)
    $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);      

    if ($response->successful()) {
        // Check if 'data' key exists in the response
        if (isset($response->json()['data'])) {
            $getData = $response->json()['data'];

            $dueAmount = 0;
            $overdueAmount = 0;

            // Calculate due and overdue amounts
            foreach ($getData as $gValue) {
                if ($gValue['ledgername'] == "closing C/f...") {
                    $dueAmount = $gValue['dramount'] != "0.00" ? $gValue['dramount'] : $gValue['cramount'];
                }
            }

            // Overdue calculation logic based on user's credit days
            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
            $drBalanceBeforeOVDate = 0;
            $crBalanceBeforeOVDate = 0;

            foreach (array_reverse($getData) as $ovValue) {
                if ($ovValue['ledgername'] != 'closing C/f...') {
                    if (strtotime($ovValue['trn_date']) <= strtotime($overdueDateFrom)) {
                        $drBalanceBeforeOVDate += $ovValue['dramount'];
                        $crBalanceBeforeOVDate += $ovValue['cramount'];
                    }
                }
            }

            $overdueAmount = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;

            // Calculate overdue days
            foreach ($getData as &$gValue) {
                if (strtotime($gValue['trn_date']) < strtotime($overdueDateFrom) && $gValue['dramount'] > 0) {
                    $dateDifference = abs(strtotime($overdueDateFrom) - strtotime($gValue['trn_date']));
                    $daysOverdue = floor($dateDifference / (60 * 60 * 24)); // Convert to days

                    // Assign overdue status and days
                    $gValue['overdue_status'] = $overdueAmount > 0 ? 'Overdue' : 'Partial Overdue';
                    $gValue['overdue_by_day'] = $daysOverdue . ' days';
                }
            }

            return [
                'dueAmount' => $dueAmount,
                'overdueAmount' => $overdueAmount,
            ];
        } else {
            // Log or handle the missing 'data' key case
            return [
                'dueAmount' => 0,
                'overdueAmount' => 0,
            ];
        }
    }

    // Default to zero if the API call fails
    return [
        'dueAmount' => 0,
        'overdueAmount' => 0,
    ];
}
    

    public function createStatementPdf(Request $request)
    {
        $party_code = $request->input('party_code');
        $dueAmount = $request->input('due_amount');
        $overdueAmount = $request->input('overdue_amount');
        $userId = $request->input('user_id');

        $user=User::find($userId);

        // return response()->json([
        //     'success' => true,
        //     'pdf_url' => $overdueAmount
        // ]);

        // Call the method to generate the PDF
        $pdfUrl = $this->generateStatementPdf($party_code, $dueAmount, $overdueAmount, User::find($userId));
        $fileName = basename($pdfUrl);

        $message="statement";
       // $to="7044300330"; 
        $to = $user->phone;
        $templateData = [
            'name' => 'utility_statement_document', // Replace with your template name, 
            'language' => 'en_US', // Replace with your desired language code
            'components' => [
                [
                    'type' => 'header',
                    'parameters' => [
                        ['type' => 'document', 'document' => ['link' => $pdfUrl,'filename' => $fileName,]],
                    ],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $user->company_name],
                        ['type' => 'text', 'text' => $message],
                    ],
                ],
                // [
                //     'type' => 'button',
                //     'sub_type' => 'url',
                //     'index' => '0',
                //     'parameters' => [
                //         [
                //             "type" => "text","text" => $fileName // Replace $button_text with the actual Parameter for the button.
                //         ],
                //     ],
                // ],
            ],
        ];

        // Send the template message using the WhatsApp web service
        $this->whatsAppWebService = new WhatsAppWebService();
        $jsonResponse  = $this->whatsAppWebService->sendTemplateMessage($to, $templateData);
               
        if ($pdfUrl) {
            return response()->json([
                'success' => true,
                'pdf_url' => "Statement Sent to whatsapp"
            ]);
        } else {
            return response()->json(['success' => false]);
        }
    }


    public function generateBulkStatements(Request $request)
    {
        $allData = $request->input('all_data');
        $user = User::find($allData[1]['user_id']);

       
        
        // Loop through the data and generate PDFs for each entry
        foreach ($allData as $data) {
            $party_code = $data['party_code'];
            $due_amount = $data['due_amount'];
            $overdue_amount = $data['overdue_amount'];
            $user_id = $data['user_id'];
            
            // Find the user
            $user = User::find($user_id);

            // Assuming you have a method to generate the PDF for each entry
            $pdfUrl = $this->generateStatementPdf($party_code, $due_amount, $overdue_amount, $user);
            $fileName = basename($pdfUrl);
            // Prepare message content
            $message = "Statement " ;
            
            // WhatsApp message sending code
            //$to = "7044300330"; // Replace with recipient's phone number
            $to=$user->phone;
            $templateData = [
                'name' => 'utility_statement_document', // Replace with your template name
                'language' => 'en_US', // Replace with your desired language code
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'document', 'document' => ['link' => $pdfUrl,'filename' => $fileName,]],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $user->company_name],
                            ['type' => 'text', 'text' => $message],
                        ],
                    ],
                    // [
                    //     'type' => 'button',
                    //     'sub_type' => 'url',
                    //     'index' => '0',
                    //     'parameters' => [
                    //         [
                    //             'type' => 'text',
                    //             'text' => $fileName // Add PDF download link as button parameter
                    //         ],
                    //     ],
                    // ],
                ],
            ];

            // Send the template message using the WhatsApp web service
            $this->whatsAppWebService = new WhatsAppWebService();
            $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($to, $templateData);
            break;
            // Handle the response or logging if needed
        }
         // Return response as needed
         return response()->json([
            'success' => true,
            'pdf_url' => $user->phone
        ]);
    }


    public function generateStatementPdfChecked(Request $request)
    {
        // Get the selected data from the request
        $selectedData = $request->input('selected_data');

   


        // Check if any data is selected
        if (empty($selectedData)) {
            return response()->json(['success' => false, 'message' => 'No users selected.']);
        }

        // Loop through each selected person and process the WhatsApp message
        foreach ($selectedData as $data) {
            $partyCode = $data['party_code'];
            $dueAmount = $data['due_amount'];
            $overdueAmount = $data['overdue_amount'];
            $userId = $data['user_id'];

            $user = User::find($userId);

            // Implement your logic here to send WhatsApp message
           // Assuming you have a method to generate the PDF for each entry
           $pdfUrl = $this->generateStatementPdf($partyCode, $dueAmount, $overdueAmount, $user);
           $fileName = basename($pdfUrl);
           // Prepare message content
           $message = "Statement " ;
           
           // WhatsApp message sending code
           //$to = "7044300330"; // Replace with recipient's phone number
           $to=$user->phone;

           $templateData = [
               'name' => 'utility_statement_document', // Replace with your template name
               'language' => 'en_US', // Replace with your desired language code
               'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'document', 'document' => ['link' => $pdfUrl,'filename' => $fileName,]],
                        ],
                    ],
                   [
                       'type' => 'body',
                       'parameters' => [
                           ['type' => 'text', 'text' => $user->company_name],
                           ['type' => 'text', 'text' => $message],
                       ],
                   ],
                //    [
                //        'type' => 'button',
                //        'sub_type' => 'url',
                //        'index' => '0',
                //        'parameters' => [
                //            [
                //                'type' => 'text',
                //                'text' => $fileName // Add PDF download link as button parameter
                //            ],
                //        ],
                //    ],
               ],
           ];

           // Send the template message using the WhatsApp web service
           $this->whatsAppWebService = new WhatsAppWebService();
           $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($to, $templateData);
           break;
            
            // For demonstration, you can log the data
            \Log::info("Sending WhatsApp to Party Code: $partyCode, Due: $dueAmount, Overdue: $overdueAmount, User ID: $userId");
        }

        // Return a success response
        return response()->json(['success' => true, 'message' => 'WhatsApp messages sent to selected persons.']);
    }


    public function syncStatement(Request $request)
    {
        $selectedData = $request->input('selected_data');
        
        // Create an instance of PurchaseHistoryController
        $purchaseHistoryController = new PurchaseHistoryController();
    
        foreach ($selectedData as $data) {
            $party_code = $data['party_code'];    
            // Call the refreshStatementDetails function from PurchaseHistoryController
            $purchaseHistoryController->refreshStatementDetails(new Request(['party_code' => encrypt($party_code)]));
        }
    
        return response()->json(['success' => true]);
    }

    public function statementExport(Request $request)
    {
        // ----- same user restrictions as Statement() -----
        $allowedUserIds = [1, 180, 169, 25606];
        $loggedInUser   = auth()->user();
        $loggedInUserId = $loggedInUser ? $loggedInUser->id : null;

        // Determine active N+ bucket (if any)
        $df = $request->input('duefilter');
        $bucketThreshold = null;          // 60 | 90 | 120
        $bucketHeading   = null;          // e.g. "Overdue 60+ Amount"
        if (in_array($df, ['overdue_60','overdue_90','overdue_120'], true)) {
            $map             = ['overdue_60'=>60, 'overdue_90'=>90, 'overdue_120'=>120];
            $bucketThreshold = $map[$df];
            $bucketHeading   = "Overdue {$bucketThreshold}+ Amount";
        }

        // ---------------- ROWS (clean, dedup) ----------------
        $rowsQ = Address::join('users', 'addresses.user_id', '=', 'users.id')
            ->leftJoin('users as managers', 'users.manager_id', '=', 'managers.id')
            ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
            ->where('users.user_type', 'customer')
            // match: exclude ace tools
            ->whereRaw('LOWER(users.name) NOT LIKE ?', ['%ace tools%'])
            // match: only customers having some due/overdue
            ->where(function($q){
                $q->whereRaw("CAST(NULLIF(addresses.due_amount, '') AS DECIMAL(18,2)) > 0")
                  ->orWhereRaw("CAST(NULLIF(addresses.overdue_amount, '') AS DECIMAL(18,2)) > 0");
            });

        // allowed-user manager restriction
        if (!in_array($loggedInUserId, $allowedUserIds, true)) {
            if ($loggedInUserId == 178) {
                $rowsQ->whereIn('users.manager_id', [178, 26786]);
            } else {
                $rowsQ->where('users.manager_id', $loggedInUserId);
            }
        }

        // Common filters
        if ($request->filled('search')) {
            $s = $request->search;
            $rowsQ->where(function ($q) use ($s) {
                $q->where('users.id', $s)
                  ->orWhere('users.party_code', 'LIKE', "%{$s}%")
                  ->orWhere('users.name', 'LIKE', "%{$s}%")
                  ->orWhere('addresses.company_name', 'LIKE', "%{$s}%")
                  ->orWhere('addresses.city', 'LIKE', "%{$s}%");
            });
        }
        if ($request->filled('manager_id'))   $rowsQ->where('users.manager_id',   $request->manager_id);
        if ($request->filled('warehouse_id')) $rowsQ->where('users.warehouse_id', $request->warehouse_id);
        if ($request->filled('city_id'))      $rowsQ->where('addresses.city', 'LIKE', "%{$request->city_id}%");

        // Due filter (rows)
        if ($df === 'due') {
            $rowsQ->whereRaw("CAST(NULLIF(addresses.due_amount, '') AS DECIMAL(18,2)) > 0");
        } elseif ($df === 'overdue') {
            $rowsQ->whereRaw("CAST(NULLIF(addresses.overdue_amount, '') AS DECIMAL(18,2)) > 0");
        } elseif (in_array($df, ['overdue_60','overdue_90','overdue_120'], true)) {
            $rowsQ->whereRaw("CAST(NULLIF(addresses.overdue_amount, '') AS DECIMAL(18,2)) > 0");
            $map = ['overdue_60'=>60, 'overdue_90'=>90, 'overdue_120'=>120];
            $thr = $map[$df];

            // candidates (acc_code + credit_days)
            $cand = (clone $rowsQ)->select('addresses.acc_code','users.credit_days')->get();
            $accList = [];
            foreach ($cand as $r) {
                $od = $this->computeFirstOverdueDays($r->acc_code, (int)$r->credit_days); // days past credit
                if ($od === null) continue;
                if ((int)$r->credit_days + (int)$od >= $thr) $accList[] = $r->acc_code;
            }
            $accList = array_values(array_unique($accList));
            if (empty($accList)) $rowsQ->whereRaw('1=0'); else $rowsQ->whereIn('addresses.acc_code', $accList);
        }

        // Clean row set (unique acc_code)
        $rows = (clone $rowsQ)
            ->select(
                'addresses.acc_code',
                'addresses.company_name',
                'addresses.city',
                'users.phone',
                'users.credit_days', // <-- needed for dynamic bucket calc
                'managers.name as manager_name',
                'warehouses.name as warehouse_name',
                DB::raw("CAST(NULLIF(addresses.due_amount, '') AS DECIMAL(18,2))  AS due_amount_numeric"),
                DB::raw("CAST(NULLIF(addresses.overdue_amount, '') AS DECIMAL(18,2)) AS overdue_amount_numeric")
            )
            ->groupBy('addresses.acc_code')
            ->orderBy('warehouses.name','asc')
            ->orderBy('managers.name','asc')
            ->orderBy('addresses.city','asc')
            ->get();

        // Build export rows — RECOMPUTE per row (matches UI)
        $data = [];
        $bucketTotal = 0.0; // for the dynamic N+ column total

        foreach ($rows as $r) {
            $due  = (float)$r->due_amount_numeric;
            $over = (float)$r->overdue_amount_numeric;

            // refresh via existing calculator (same as Statement())
            try {
                $resp = $this->getDueAndOverDueAmount(new Request(['party_code' => $r->acc_code]));
                $d    = json_decode($resp->getContent(), true);
                if (isset($d['dueAmount']))     $due  = (float)$d['dueAmount'];
                if (isset($d['overdueAmount'])) $over = (float)$d['overdueAmount'];
            } catch (\Throwable $e) {
                // keep DB values if calculator fails
            }

            // Skip rows with no amounts (parity with page)
            if ($due <= 0 && $over <= 0) continue;

            // Build base row
            $row = [
                'Party Name'     => $r->company_name,
                'Party Code'     => $r->acc_code,
                'Phone'          => $r->phone,
                'Manager'        => $r->manager_name ?? 'N/A',
                'Warehouse'      => $r->warehouse_name ?? 'N/A',
                'City'           => $r->city,
                'Due Amount'     => round($due, 2),
                'Overdue Amount' => round($over, 2),
            ];

            // If N+ bucket is active, add dynamic column
            if ($bucketThreshold !== null) {
                $creditDays = (int) $r->credit_days;
                // Convert global N+ to "overdue-beyond-credit" days
                $dynOverdueThreshold = max(0, $bucketThreshold - $creditDays);

                $bucketAmt = 0.0;
                try {
                    $bucketAmt = $this->computeOverdueAmountByAgeThreshold($r->acc_code, $creditDays, $dynOverdueThreshold);
                } catch (\Throwable $e) {
                    $bucketAmt = 0.0;
                }

                $row[$bucketHeading] = round($bucketAmt, 2);
                $bucketTotal += $bucketAmt;
            }

            $data[] = $row;
        }

        // ---------------- PAGE TOTALS (mirror Statement banner) ----------------
        $pageQ = User::join('addresses', function ($join) {
                $join->on(DB::raw("LEFT(users.party_code, 11)"), '=', DB::raw("LEFT(addresses.acc_code, 11)"));
            })
            ->leftJoin('users as managers', 'users.manager_id', '=', 'managers.id')
            ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
            ->where('users.user_type','customer')
            ->whereRaw('LOWER(users.name) NOT LIKE ?', ['%ace tools%'])
            ->where(function($q){
                $q->whereRaw("CAST(NULLIF(addresses.due_amount, '') AS DECIMAL(18,2)) > 0")
                  ->orWhereRaw("CAST(NULLIF(addresses.overdue_amount, '') AS DECIMAL(18,2)) > 0");
            });

        if (!in_array($loggedInUserId, $allowedUserIds, true)) {
            if ($loggedInUserId == 178) {
                $pageQ->whereIn('users.manager_id', [178, 26786]);
            } else {
                $pageQ->where('users.manager_id', $loggedInUserId);
            }
        }

        // same filters as above
        if ($request->filled('search')) {
            $s = $request->search;
            $pageQ->where(function ($q) use ($s) {
                $q->where('users.id', $s)
                  ->orWhere('users.party_code', 'LIKE', "%{$s}%")
                  ->orWhere('users.name', 'LIKE', "%{$s}%")
                  ->orWhere('addresses.company_name', 'LIKE', "%{$s}%")
                  ->orWhere('addresses.city', 'LIKE', "%{$s}%");
            });
        }
        if ($request->filled('manager_id'))   $pageQ->where('users.manager_id',   $request->manager_id);
        if ($request->filled('warehouse_id')) $pageQ->where('users.warehouse_id', $request->warehouse_id);
        if ($request->filled('city_id'))      $pageQ->where('addresses.city', 'LIKE', "%{$request->city_id}%");

        if ($df === 'due') {
            $pageQ->whereRaw("CAST(NULLIF(addresses.due_amount, '') AS DECIMAL(18,2)) > 0");
        } elseif ($df === 'overdue') {
            $pageQ->whereRaw("CAST(NULLIF(addresses.overdue_amount, '') AS DECIMAL(18,2)) > 0");
        } elseif (in_array($df, ['overdue_60','overdue_90','overdue_120'], true)) {
            $pageQ->whereRaw("CAST(NULLIF(addresses.overdue_amount, '') AS DECIMAL(18,2)) > 0");
            $map = ['overdue_60'=>60, 'overdue_90'=>90, 'overdue_120'=>120];
            $thr = $map[$df];
            $cand = (clone $pageQ)->select('addresses.acc_code','users.credit_days')->get();
            $accList = [];
            foreach ($cand as $r) {
                $od = $this->computeFirstOverdueDays($r->acc_code, (int)$r->credit_days);
                if ($od === null) continue;
                if ((int)$r->credit_days + (int)$od >= $thr) $accList[] = $r->acc_code;
            }
            $accList = array_values(array_unique($accList));
            if (empty($accList)) $pageQ->whereRaw('1=0'); else $pageQ->whereIn('addresses.acc_code', $accList);
        }

        $totals = (clone $pageQ)
            ->selectRaw("SUM(CAST(NULLIF(addresses.due_amount, '') AS DECIMAL(18,2)))  AS t_due,
                         SUM(CAST(NULLIF(addresses.overdue_amount, '') AS DECIMAL(18,2))) AS t_over")
            ->first();

        $pageDue  = (float)($totals->t_due  ?? 0);
        $pageOver = (float)($totals->t_over ?? 0);

        // Build headings (dynamic if N+ active)
        $headings = [
            'Party Name',
            'Party Code',
            'Phone',
            'Manager',
            'Warehouse',
            'City',
            'Due Amount',
            'Overdue Amount',
        ];
        if ($bucketHeading !== null) {
            $headings[] = $bucketHeading;
        }

        // Append TOTAL row (matches page banner exactly; N+ column total is from $bucketTotal)
        if (!empty($data)) {
            $totalRow = [
                'Party Name'     => 'TOTAL',
                'Party Code'     => '',
                'Phone'          => '',
                'Manager'        => '',
                'Warehouse'      => '',
                'City'           => '',
                'Due Amount'     => round($pageDue, 2),
                'Overdue Amount' => round($pageOver, 2),
            ];
            if ($bucketHeading !== null) {
                $totalRow[$bucketHeading] = round($bucketTotal, 2);
            }
            $data[] = $totalRow;
        }

        // --------------- download ---------------
        $filename = 'statements-' . now()->format('Ymd-His') . '.xlsx';
        $resp = \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\StatementExport($data, $headings),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
        $resp->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $resp->headers->set('Pragma', 'no-cache');
        $resp->headers->set('Expires', '0');
        return $resp;
    }



    public function generateBulkOrCheckedStatements(Request $request)
    {
        $allData = $request->input('all_data'); 

        // Ensure allData is not empty
        if (empty($allData)) {
            return response()->json(['success' => false, 'message' => 'No data available to send.']);
        }

        // Generate a unique group ID for this batch of messages
        $groupId = uniqid('group_', true);

        // Loop through the data and generate PDFs/WhatsApp messages
        foreach ($allData as $data) {
            $party_code = $data['party_code'];
            $due_amount = $data['due_amount'];
            $overdue_amount = $data['overdue_amount'];
            $user_id = $data['user_id'];

            // Find the user
            $user = User::find($user_id);
            if (!$user) {
                continue; // Skip if the user does not exist
            }

            // Get user, manager, and head manager phone numbers
            $phone = $user->phone;
            $managerPhone = $this->getManagerPhone($user->manager_id);
            $headManagerPhone = $this->getHeadManagerPhone($user->warehouse_id);

            // Generate the PDF statement
            $pdfUrl = $this->generateStatementPdf($party_code, $due_amount, $overdue_amount, $user);
            $fileName = basename($pdfUrl);

            // Generate the payment URL
            $payment_url = $this->generatePaymentUrl($party_code, $payment_for = "custom_amount");
            $payNowBtn = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
            $button_variable_encode_part = $payNowBtn;

            // Prepare the template data
            $templateData = [
                'name' => 'utility_statement_document',
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'document', 'document' => ['link' => $pdfUrl, 'filename' => $fileName]],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $user->company_name ?? 'No Company Name'],
                            ['type' => 'text', 'text' => $due_amount],
                            ['type' => 'text', 'text' => $overdue_amount],
                            ['type' => 'text', 'text' => $managerPhone],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            ["type" => "text", "text" => $button_variable_encode_part],
                        ],
                    ],
                ],
            ];

            // Insert message for the user
            DB::table('wa_sales_queue')->insert([
                'group_id' => $groupId,
                'callback_data' => $templateData['name'],
                'recipient_type' => 'individual',
                'to_number' => $phone,
                'type' => 'template',
                'file_url' => $pdfUrl,
                'file_name' => $party_code,
                'content' => json_encode($templateData),
                'status' => 'pending',
                'response' => '',
                'msg_id' => '',
                'msg_status' => '',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Insert message for the manager
            // if ($managerPhone) {
            //     DB::table('wa_sales_queue')->insert([
            //         'group_id' => $groupId,
            //         'callback_data' => $templateData['name'],
            //         'recipient_type' => 'individual',
            //         'to_number' => $managerPhone,
            //         'type' => 'template',
            //         'file_url' => $pdfUrl,
            //         'content' => json_encode($templateData),
            //         'status' => 'pending',
            //         'response' => '',
            //         'msg_id' => '',
            //         'msg_status' => '',
            //         'created_at' => now(),
            //         'updated_at' => now()
            //     ]);
            // }

            // Insert message for the head manager
            // if ($headManagerPhone) {
            //     DB::table('wa_sales_queue')->insert([
            //         'group_id' => $groupId,
            //         'callback_data' => $templateData['name'],
            //         'recipient_type' => 'individual',
            //         'to_number' => $headManagerPhone,
            //         'type' => 'template',
            //         'file_url' => $pdfUrl,
            //         'content' => json_encode($templateData),
            //         'status' => 'pending',
            //         'response' => '',
            //         'msg_id' => '',
            //         'msg_status' => '',
            //         'created_at' => now(),
            //         'updated_at' => now()
            //     ]);
            // }
        }

        // Dispatch the job to process WhatsApp messages asynchronously
        SendWhatsAppMessagesJob::dispatch($groupId);

        return response()->json(['success' => true, 'message' => 'WhatsApp messages are queued successfully.']);
    }
    public function ____generateBulkOrCheckedStatements(Request $request)
    {
        $allData = $request->input('all_data');
    
        // Ensure allData is not empty
        if (empty($allData)) {
            return response()->json(['success' => false, 'message' => 'No data available to send.']);
        }
    
        // Loop through the data and generate PDFs/WhatsApp messages
        foreach ($allData as $data) {
            $party_code = $data['party_code'];
            $due_amount = $data['due_amount'];
            $overdue_amount = $data['overdue_amount'];
            $user_id = $data['user_id'];
    
            // Find the user
            $user = User::find($user_id);
            $phone=$user->phone;
    
            // Assuming you have a method to generate the PDF for each entry
            $pdfUrl = $this->generateStatementPdf($party_code, $due_amount, $overdue_amount, $user);
            $fileName = basename($pdfUrl);
    
            // Prepare message content
            $message = "Statement for " . $user->company_name;
            $managerId = $user->manager_id;

            // Check if manager_id exists
              if ($managerId) {
                  // Perform query to fetch manager's phone number from users table
                  $managerData = DB::table('users')
                                  ->where('id', $managerId)
                                  ->select('phone')
                                  ->first();
                  
                  // Check if manager data is found
                  if ($managerData && $managerData->phone) {
                      $managerPhone = $managerData->phone;
                  } else {
                      // Fallback if no manager found or no phone number available
                      $managerPhone = null;
                  }
              }
    
            // WhatsApp message sending code
			$payment_url=$this->generatePaymentUrl($party_code,$payment_for="custom_amount");
              $payNowBtn = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
          $button_variable_encode_part=$payNowBtn;
			
            $to = $phone; // Replace with recipient's phone number
            $templateData = [
                'name' => 'utility_statement_document',
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'document', 'document' => ['link' => $pdfUrl, 'filename' => $fileName]],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $user->company_name],
                             ['type' => 'text', 'text' => $due_amount],
                            ['type' => 'text', 'text' => $overdue_amount],
                            ['type' => 'text', 'text' => $managerPhone],
                        ],
                    ],
					 [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            [
                                "type" => "text",
                                "text" => $button_variable_encode_part // Replace $button_text with the actual Parameter for the button.
                            ],
                        ],
                   ],
                ],
            ];
    
            // Send the template message using the WhatsApp web service
            $this->whatsAppWebService = new WhatsAppWebService();
            $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($to, $templateData);
        }
    
        return response()->json(['success' => true, 'message' => 'WhatsApp messages sent successfully.']);
    }


    public function notifyManager(Request $request)
    {
        $managerIds = $request->input('manager_ids', []);
        $warehouseId = $request->input('warehouse_id');

        // Special case mapping: key = manager who gets extra access, value = array of other managers' IDs whose customers they can see
        $specialAccess = [
            178 => [26786], // Hatim sees Hussain's customers
            // Add more mappings here if needed
        ];

        // Fetch managers from selected manager IDs
        $managers = User::whereIn('id', $managerIds)->get();

        if ($managers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No managers found for the selected options.'
            ]);
        }

        $groupId = uniqid('group_', true);

        foreach ($managers as $manager) {

            // Build customer query with special case handling
            $customersQuery = User::where('user_type', 'customer')
                ->orderBy('company_name', 'asc');

            if (isset($specialAccess[$manager->id])) {
                $allowedManagerIds = array_merge([$manager->id], $specialAccess[$manager->id]);
                $customersQuery->whereIn('manager_id', $allowedManagerIds);
            } else {
                $customersQuery->where('manager_id', $manager->id);
            }

            $customers = $customersQuery->get();

            $processedData = [];
            $totalDueAmount = 0;
            $totalOverdueAmount = 0;

            foreach ($customers as $userData) {
                $userAddressData = Address::where('user_id', $userData->id)
                    ->where(function ($q) {
                        $q->where('due_amount', '>', 0)
                          ->orWhere('overdue_amount', '>', 0);
                    })
                    ->orderBy('updated_at', 'desc')
                    ->get();

                foreach ($userAddressData as $address) {
                    if ($address->due_amount > 0 || $address->overdue_amount > 0) {
                        $totalDueAmount += $address->due_amount;
                        $totalOverdueAmount += $address->overdue_amount;

                        $processedData[] = [
                            'customer' => ['company_name' => $address->company_name],
                            'address'  => $address,
                            'phone'    => $userData->phone,
                            'city'     => $address->city
                        ];
                    }
                }
            }

            // Sort processed data
            usort($processedData, function ($a, $b) {
                $cityComparison = strcmp($a['city'], $b['city']);
                if ($cityComparison !== 0) {
                    return $cityComparison;
                }
                $companyNameComparison = strcmp($a['customer']['company_name'], $b['customer']['company_name']);
                if ($companyNameComparison !== 0) {
                    return $companyNameComparison;
                }
                return $b['address']->overdue_amount <=> $a['address']->overdue_amount;
            });

            Log::info("Manager: {$manager->name}", [
                'processedData'   => $processedData,
                'totalDue'        => $totalDueAmount,
                'totalOverdue'    => $totalOverdueAmount
            ]);

            if (!empty($processedData)) {
                $fileName = 'Manager_Report_' . $manager->name . '_' . rand(1000, 9999) . '.pdf';
                $pdf = PDF::loadView('backend.statement.manager-report', [
                    'manager_name'       => $manager->name,
                    'processedData'      => $processedData,
                    'totalDueAmount'     => $totalDueAmount,
                    'totalOverdueAmount' => $totalOverdueAmount
                ])->save(public_path('statements/' . $fileName));

                $publicUrl = url('public/statements/' . $fileName);

                $templateData = [
                    'name'      => 'utility_statement_manager_notify',
                    'language'  => 'en_US',
                    'components'=> [
                        [
                            'type'       => 'header',
                            'parameters' => [
                                [
                                    'type'     => 'document',
                                    'document' => [
                                        'link'     => $publicUrl,
                                        'filename' => $fileName
                                    ]
                                ]
                            ],
                        ],
                        [
                            'type'       => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $manager->name],
                                ['type' => 'text', 'text' => $totalDueAmount],
                                ['type' => 'text', 'text' => $totalOverdueAmount],
                            ],
                        ],
                    ],
                ];

                // Send via WhatsApp
                $this->whatsAppWebService = new WhatsAppWebService();
                $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($manager->phone, $templateData);
            }
        }

        return response()->json([
            'status'  => true,
            'message' => 'Report generated and notification sent successfully.',
        ], 200);
    }

    



    
    public function submitComment(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'party_code' => 'required|string|exists:addresses,acc_code', // Validate that the party_code exists in the addresses table
            'statement_comment' => 'required|string',
            'statement_comment_date' => 'required|date',
        ]);

        // Update all records matching the acc_code
        $updated = Address::where('acc_code', $request->party_code)
                          ->update([
                              'statement_comment' => $request->statement_comment,
                              'statement_comment_date' => $request->statement_comment_date
                          ]);

        if ($updated) {
            return response()->json(['success' => true, 'message' => 'Comment and date updated successfully']);
        } else {
            return response()->json(['success' => false, 'message' => 'No addresses were updated for this party code']);
        }
    }



   


   /*public function getAllUsersData(Request $request)
    {

        // Increase memory limit and execution time to avoid timeouts
        set_time_limit(-1);  // Set max execution time to 3000 seconds (50 minutes)
        header('Keep-Alive: timeout=86400, max=100');
        header('Cache-Control: no-cache');
        header('Connection: Keep-Alive');
        ini_set('memory_limit', '512M');
        

        $groupId = uniqid('group_', true);  // Generate the same group_id for all users in this "run"
        
        try {
            // Start processing users in the background using chunking
            User::join('addresses', 'users.id', '=', 'addresses.user_id')
                ->select('users.party_code', 'addresses.due_amount', 'addresses.overdue_amount', 'users.id as user_id')
                ->where(function($query) {
                    $query->where('addresses.due_amount', '>', 0)
                          ->orWhere('addresses.overdue_amount', '>', 0);
                })
                ->chunk(300, function ($users) use ($groupId) {
                    try {
                        // Log the start of chunk processing
                        Log::info("Processing a chunk of users", ['group_id' => $groupId, 'user_count' => count($users)]);

                        // Dispatch the job to send WhatsApp messages
                        SendWhatsAppMessagesJob::dispatch($users->toArray(), $groupId);

                        // Log successful dispatch
                        Log::info("Successfully dispatched WhatsApp messages", ['group_id' => $groupId]);

                    } catch (\Exception $e) {
                        // Log the exception if something goes wrong
                        Log::error("Error while processing chunk of users", ['error' => $e->getMessage(), 'group_id' => $groupId]);
                    }
                });
            
            // Log overall success
            Log::info("All chunks have been processed for group", ['group_id' => $groupId]);
        } catch (\Exception $e) {
            // Log the exception if the whole process fails
            Log::error("Error in getAllUsersData function", ['error' => $e->getMessage()]);
        }

        // Return success response immediately after dispatching the job
        return response()->json(['success' => true, 'message' => 'WhatsApp messages are being processed in the background.']);
    }*/
	
public function getAllUsersData(Request $request)
{
    // Increase memory limit and execution time to avoid timeouts
    set_time_limit(-1);  // No timeout
    header('Keep-Alive: timeout=86400, max=100');
    header('Cache-Control: no-cache');
    header('Connection: Keep-Alive');
    ini_set('memory_limit', '512M');
    
    $groupId = uniqid('group_', true);  // Generate a unique group_id

    // Retrieve filter parameters
    $warehouseId = $request->warehouse_id;
    $managerId = $request->manager_id;
    $duefilter = $request->duefilter;

    try {
        $query = User::join('addresses', 'users.id', '=', 'addresses.user_id')
            ->select(
                'users.party_code', 
                'addresses.due_amount', 
                'addresses.overdue_amount', 
                'users.id as user_id', 
                'users.company_name', 
                'users.phone',   // Ensure that phone is selected
                'users.manager_id',  // Ensure manager_id is selected to fetch manager phone later
                'users.warehouse_id'
            )
            ->where(function($query) use ($duefilter) {
                if ($duefilter === 'due') {
                    $query->where('addresses.due_amount', '>', 0);
                } elseif ($duefilter === 'overdue') {
                    $query->where('addresses.overdue_amount', '>', 0);
                } else {
                    $query->where(function ($q) {
                        $q->where('addresses.due_amount', '>', 0)
                          ->orWhere('addresses.overdue_amount', '>', 0);
                    });
                }
            });

        // Apply warehouse and manager filters if provided
        if ($warehouseId) {
            $query->where('users.warehouse_id', $warehouseId);
        }
        if ($managerId) {
            $query->where('users.manager_id', $managerId);
        }

        $query->chunk(300, function ($users) use ($groupId) {
            foreach ($users as $user) {
                try {
                    // Get the manager's phone number
                    $managerPhone = $this->getManagerPhone($user->manager_id);
                    $headManagerPhone = $this->getHeadManagerPhone($user->warehouse_id);

                    // Generate the PDF and get the file URL
                    $pdfUrl = $this->generateStatementPdf($user->party_code, $user->due_amount, $user->overdue_amount, $user);
                    $fileName = basename($pdfUrl);
                    
                    $payment_url = $this->generatePaymentUrl($user->party_code, $payment_for = "custom_amount");
                    $payNowBtn = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
                    $button_variable_encode_part = $payNowBtn;
                    
                    // Prepare the template data for insertion
                    $templateData = [
                        'name' => 'utility_statement_document',
                        'language' => 'en_US',
                        'components' => [
                            [
                                'type' => 'header',
                                'parameters' => [
                                    ['type' => 'document', 'document' => ['link' => $pdfUrl, 'filename' => $fileName]],
                                ],
                            ],
                            [
                                'type' => 'body',
                                'parameters' => [               
                                    ['type' => 'text', 'text' => $user->company_name ?? 'No Company Name'],
                                    ['type' => 'text', 'text' => $user->due_amount],
                                    ['type' => 'text', 'text' => $user->overdue_amount],
                                    ['type' => 'text', 'text' => $managerPhone]
                                ],
                            ],
                            [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => '0',
                                'parameters' => [
                                    [
                                        "type" => "text",
                                        "text" => $button_variable_encode_part
                                    ],
                                ],
                            ],
                        ],
                    ];

                    // Insert data into wa_sales_queue table
                    DB::table('wa_sales_queue')->insert([
                        'group_id' => $groupId,
                        'callback_data' => $templateData['name'],
                        'recipient_type' => 'individual',
                        'to_number' => $user->phone,
                        'type' => 'template',
                        'file_url' => $pdfUrl,
                        'file_name'=>$user->party_code,
                        'content' => json_encode($templateData),
                        'status' => 'pending',
                        'response' => '',
                        'msg_id' => '',
                        'msg_status' => '',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Insert message for the manager
                        // DB::table('wa_sales_queue')->insert([
                        //     'group_id' => $groupId,
                        //     'callback_data' => $templateData['name'],
                        //     'recipient_type' => 'individual',
                        //     'to_number' => $managerPhone,
                        //     'type' => 'template',
                        //     'file_url' => $pdfUrl,
                        //     'content' => json_encode($templateData),
                        //     'status' => 'pending',
                        //     'response' => '',
                        //     'msg_id' => '',
                        //     'msg_status' => '',
                        //     'created_at' => now(),
                        //     'updated_at' => now()
                        // ]);

                        // Insert message for the head manager
                        // DB::table('wa_sales_queue')->insert([
                        //     'group_id' => $groupId,
                        //     'callback_data' => $templateData['name'],
                        //     'recipient_type' => 'individual',
                        //     'to_number' => $headManagerPhone,
                        //     'type' => 'template',
                        //     'file_url' => $pdfUrl,
                        //     'content' => json_encode($templateData),
                        //     'status' => 'pending',
                        //     'response' => '',
                        //     'msg_id' => '',
                        //     'msg_status' => '',
                        //     'created_at' => now(),
                        //     'updated_at' => now()
                        // ]);

                } catch (\Exception $e) {
                    Log::error("Error processing user ID {$user->user_id}: {$e->getMessage()}");
                    continue;
                }
            }
        });

        // Return the group_id as part of the response
        return response()->json(['success' => true, 'group_id' => $groupId]);

    } catch (\Exception $e) {
        Log::error("Error in getAllUsersData function", ['error' => $e->getMessage()]);
        return response()->json(['success' => false, 'message' => 'Error processing data']);
    }
}






public function processWhatsapp(Request $request)
{
    // Validate the request to ensure 'group_id' is present
    // $request->validate([
    //     'group_id' => 'required'
    // ]);

    $groupId = $request->input('group_id');
    // $groupId = 124;

    

    // Dispatch the job to process WhatsApp messages asynchronously
//    SendWhatsAppMessagesJob::dispatch($groupId);

    // Return a response immediately while the job processes in the background
    return response()->json([
        'success' => true,
        'message' => 'WhatsApp messages are being processed',
        'group_id' => $groupId
    ]);
}
public function __processWhatsapp(Request $request)
{
    // Validate the request to ensure 'group_id' is present
    $request->validate([
        'group_id' => 'required'
    ]);

    $groupId = $request->input('group_id');

    // Retrieve users from the wa_sales_queue where group_id matches and status is 'pending'
    $messages = DB::table('wa_sales_queue')
        ->where('group_id', $groupId)
        ->where('status', 'pending')  // Only process 'pending' messages
        ->get();

    foreach ($messages as $message) {
        try {
           

            // Prepare WhatsApp message content from the message record
            $templateData = json_decode($message->content, true);
			  // Return a success response after processing
				
          


            // Send the WhatsApp message using your WhatsApp API service
            $whatsAppWebService = new WhatsAppWebService();
            $jsonResponse = $whatsAppWebService->sendTemplateMessage($message->to_number, $templateData);

            // Extract response details from the WhatsApp API response
            $messageId = $jsonResponse['messages'][0]['id'] ?? '';
		
            $messageStatus = $jsonResponse['messages'][0]['message_status'] ?? 'unknown';

            // Update the wa_sales_queue with the response details
            DB::table('wa_sales_queue')
                ->where('id', $message->id)  // Update by the primary key 'id'
                ->update([
                    'status' => 'sent',  // Mark the message as 'sent'
                    'response' => json_encode($jsonResponse),  // Store the API response
                    'msg_id' => $messageId,  // Store the message ID
                    'msg_status' => $messageStatus,  // Store the message status
                    'updated_at' => now()  // Update the timestamp
                ]);

        } catch (Exception $e) {
            // Log the error and continue with the next message
            Log::error('Error sending WhatsApp message for ID ' . $message->id . ': ' . $e->getMessage());
            continue;  // Continue to the next message even if an error occurs
        }
    }

    // Return a success response after processing
    return response()->json([
        'success' => true,
        'message' => 'WhatsApp messages processed successfully',
        'group_id' => $groupId
    ]);
}


/**
 * Get the manager's phone number.
 */
private function getManagerPhone($managerId)
{
    $managerData = DB::table('users')
        ->where('id', $managerId)
        ->select('phone')
        ->first();

    return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
}

public function __getAllUsersData(Request $request)
{
    // Set the maximum execution time to 300 seconds (5 minutes)
     set_time_limit(3000000);
    
    // Fetch users who have either a due amount or an overdue amount greater than 0
    $users = User::join('addresses', 'users.id', '=', 'addresses.user_id')
                 ->select('users.party_code', 'addresses.due_amount', 'addresses.overdue_amount', 'users.id as user_id')
                 ->where(function($query) {
                     $query->where('addresses.due_amount', '>', 0)
                           ->orWhere('addresses.overdue_amount', '>', 0);
                 })
              
                 ->get();

    // Ensure we have users to send WhatsApp messages to
    if ($users->isEmpty()) {
        return response()->json(['success' => false, 'message' => 'No users with due or overdue amounts found.']);
    }

    // Generate the same group_id for all users in this "run"
    $groupId = uniqid('group_', true);

    // Loop through the users and send WhatsApp messages
    foreach ($users as $user) {
        try {
            $party_code = $user->party_code;
            $due_amount = $user->due_amount;
            $overdue_amount = $user->overdue_amount;
            $user_id = $user->user_id;

            // Find the user by ID
            $userData = User::find($user_id);
            if (!$userData) {
                throw new Exception("User not found");
            }

            // Generate the PDF for each user within a try-catch block to handle errors
            try {
                $pdfUrl = $this->generateStatementPdf($party_code, $due_amount, $overdue_amount, $userData);
                $fileName = basename($pdfUrl);
            } catch (Exception $pdfException) {
                \Log::error('Error generating PDF for user ID ' . $user_id . ': ' . $pdfException->getMessage());
                continue; // Skip to the next user if PDF generation fails
            }

            // Prepare message content
            $message = "Statement for " . $userData->company_name;

            $managerId = $userData->manager_id;
              // Check if manager_id exists
              if ($managerId) {
                  // Perform query to fetch manager's phone number from users table
                  $managerData = DB::table('users')
                                  ->where('id', $managerId)
                                  ->select('phone')
                                  ->first();
                  
                  // Check if manager data is found
                  if ($managerData && $managerData->phone) {
                      $managerPhone = $managerData->phone;
                  } else {
                      // Fallback if no manager found or no phone number available
                      $managerPhone = null;
                  }
              }

            // WhatsApp message sending code
            $to = 7044300330; // Replace with the user's phone number
            $templateData = [
                'name' => 'utility_statement_document',
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'document', 'document' => ['link' => $pdfUrl, 'filename' => $fileName]],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $userData->company_name],
                             ['type' => 'text', 'text' => $due_amount],
                            ['type' => 'text', 'text' => $overdue_amount],
                            ['type' => 'text', 'text' => $managerPhone],
                        ],
                    ],
                ],
            ];

            // Send the template message using the WhatsApp web service
            $this->whatsAppWebService = new WhatsAppWebService();
            $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($to, $templateData);

            // Check if response is already an array or a string
            $responseData = is_array($jsonResponse) ? $jsonResponse : json_decode($jsonResponse, true);

            if (!is_array($responseData)) {
                throw new Exception("Invalid response format");
            }

            // Extract message ID and message status from the response
            $messageId = $responseData['messages'][0]['id'] ?? null; // Extract message ID
            $messageStatus = $responseData['messages'][0]['message_status'] ?? 'unknown'; // Extract message status

            // Insert into wa_sales_queue table
            DB::table('wa_sales_queue')->insert([
                'group_id' => $groupId, // Use the same group_id for all users
                'callback_data' => $templateData['name'], // Set the callback_data to the template name
                'recipient_type' => 'individual', // Change as needed
                'to_number' => $to,
                'type' => 'template',
                'file_url' => $pdfUrl,
                'content' => json_encode($templateData),
                'status' => 'sent', // Change status based on the response
                'response' => json_encode($jsonResponse), // Log response
                'msg_id' => $messageId, // Insert extracted message ID
                'msg_status' => $messageStatus, // Insert extracted message status
                'created_at' => now(),
                'updated_at' => now()
            ]);

            break;

        } catch (Exception $e) {
            // Log the error and skip this user
            \Log::error('Error processing user ID ' . $user_id . ': ' . $e->getMessage());
            continue; // Skip to the next user in case of error
        }
    }

    return response()->json(['success' => true, 'message' => 'WhatsApp messages sent and data inserted into queue.']);
}





    public function sendRemainderWhatsAppForStatements()
    {
        // Get current date
        $currentDate = date('Y-m-d');
        
        // Fetch users from the address table where statement_comment and statement_comment_date are not blank and match the current date
        $users = DB::table('addresses')
                    ->whereNotNull('statement_comment')
                    ->whereNotNull('statement_comment_date')
                    ->whereDate('statement_comment_date', $currentDate)
                    ->get();

        // Check if any users exist
        if ($users->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No users with comments to send WhatsApp message today.']);
        }

        foreach ($users as $user) {
            $party_code = $user->acc_code;
            $statement_comment = $user->statement_comment;
            $statement_comment_date = $user->statement_comment_date;
            $due_amount = $user->due_amount; // Assuming you have a due_amount field
            $overdue_amount = $user->overdue_amount; // Assuming you have an overdue_amount field
            
            // Generate the PDF using the generateStatementPdf function
            $pdfUrl = $this->generateStatementPdf($party_code, $due_amount, $overdue_amount, $user);
            $fileName = basename($pdfUrl);

            // Generate the message
            $message = "Statement";
			$managerId = DB::table('users')
				->where('id', $user->user_id)
				->value('manager_id');
			
			

          // Check if manager_id exists
          if ($managerId) {
              // Perform query to fetch manager's phone number from users table
              $managerData = DB::table('users')
                              ->where('id', $managerId)
                              ->select('phone')
                              ->first();
              
              // Check if manager data is found
              if ($managerData && $managerData->phone) {
                  $managerPhone = $managerData->phone;
              } else {
                  // Fallback if no manager found or no phone number available
                  $managerPhone = null;
              }
          }

            // WhatsApp message sending code
            $to = $user->phone; // Replace with recipient's phone number
            $templateData = [
                'name' => 'utility_remainder_statement',
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'document', 'document' => ['link' => $pdfUrl, 'filename' => $fileName]],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $user->company_name],
                            ['type' => 'text', 'text' => $due_amount],
                            ['type' => 'text', 'text' => $overdue_amount],
                            ['type' => 'text', 'text' => $managerPhone],
                        ],
                    ],
                ],
            ];

            // Send the template message using the WhatsApp web service
            $this->whatsAppWebService = new WhatsAppWebService();
            $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($to, $templateData);
            // echo "<pre>";
            // print_r($jsonResponse);
            // die(); 
        }

        return response()->json(['success' => true, 'message' => 'WhatsApp messages sent successfully with PDFs.']);
    }


    public function __sendRemainderWhatsAppForStatements()
    {
        // Get current date
        $currentDate = date('Y-m-d');
        
        // Fetch users from the address table where statement_comment and statement_comment_date are not blank and match the current date
        $users = DB::table('addresses')
                    ->whereNotNull('statement_comment')
                    ->whereNotNull('statement_comment_date')
                    ->whereDate('statement_comment_date', $currentDate)
                    ->get();

        // Check if any users exist
        if ($users->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No users with comments to save WhatsApp data today.']);
        }

        foreach ($users as $user) {
            $party_code = $user->acc_code;
            $statement_comment = $user->statement_comment;
            $statement_comment_date = $user->statement_comment_date;
            $due_amount = $user->due_amount; // Assuming you have a due_amount field
            $overdue_amount = $user->overdue_amount; // Assuming you have an overdue_amount field
            
            // Generate the PDF using the generateStatementPdf function
            $pdfUrl = $this->generateStatementPdf($party_code, $due_amount, $overdue_amount, $user);
            $fileName = basename($pdfUrl);

            // Prepare the template data
            $templateData = [
                'name' => 'utility_remainder_statement',
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'document', 'document' => ['link' => $pdfUrl, 'filename' => $fileName]],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $user->company_name],
                            ['type' => 'text', 'text' => $due_amount],
                            ['type' => 'text', 'text' => $overdue_amount],
                        ],
                    ],
                ],
            ];

            // Convert the templateData to JSON format
            $templateDataJson = json_encode($templateData);

            // Get the template name dynamically from templateData
            $templateName = $templateData['name'];

            // Prepare the data for wa_sales_queue table
            $whatsappData = [
                'group_id' => '', // Set to empty string or a valid ID if available
                'callback_data' => $templateName, // Set the template name dynamically in callback_data
                'recipient_type' => 'individual', // Adjust as necessary
                'to_number' => '7044300330', // Replace with recipient's phone number
                'type' => 'template', // Set the type to template
                'file_url' => $pdfUrl, // File URL for the document
                'content' => $templateDataJson, // Save the JSON formatted template data
                'status' => 'pending', // Set the initial status to pending
                'response' => '', // Set an empty string for response instead of null
                'msg_id' => '', // Set an empty string for msg_id instead of null
                'msg_status' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert into the wa_sales_queue table
            DB::table('wa_sales_queue')->insert($whatsappData);
        }

        return response()->json(['success' => true, 'message' => 'WhatsApp data saved successfully in wa_sales_queue.']);
    }


     public function deletePdfFiles()
    {
        //THis function is for cron
        // Define an array of folder names
        $folders = ['statements', 'pdfs']; // Add more folder names if needed

        $pdfDeleted = false;
        $resultMessages = [];

        // Loop through each folder in the array
        foreach ($folders as $folderName) {
            // Dynamic path based on folder name
            $path = public_path($folderName);

            // Check if the folder exists
            if (File::exists($path)) {
                // Get all files in the folder
                $pdfFiles = File::files($path);

                $folderHasPdfToDelete = false;

                foreach ($pdfFiles as $file) {
                    // Check if the file has a .pdf extension
                    if (File::extension($file) == 'pdf') {
                        // Get the last modified time of the file
                        $lastModified = Carbon::createFromTimestamp(File::lastModified($file));

                        // Delete files older than 2 days, excluding today's files
                        if ($lastModified->lt(Carbon::now()->subDays(2))) {
                            File::delete($file);
                            $pdfDeleted = true;
                            $folderHasPdfToDelete = true;
                        }
                    }
                }

                // Add result messages based on whether files were deleted or not
                if ($folderHasPdfToDelete) {
                    $resultMessages[] = "Great! PDF files older than 2 days have been deleted from the {$folderName} folder.";
                } else {
                    $resultMessages[] = "No PDF files older than 2 days found to delete in the {$folderName} folder.";
                }
            } else {
                $resultMessages[] = "The {$folderName} folder does not exist.";
            }
        }

        // Return all the result messages as a JSON response
        return response()->json(['messages' => $resultMessages], 200, [], JSON_UNESCAPED_UNICODE);
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


    public function __paymentPendingRemainder() {
        
        $pendingPayments = DB::table('payment_histories')
                            ->join('addresses', DB::raw('payment_histories.party_code COLLATE utf8mb3_unicode_ci'), '=', DB::raw('addresses.acc_code COLLATE utf8mb3_unicode_ci'))
                            ->where('payment_histories.status', 'PENDING')
                            ->whereNotNull('payment_histories.payment_for') // Ensure payment_for is not NULL
                            ->where('payment_histories.payment_for', '!=', '') // Ensure payment_for is not empty
                            ->select('payment_histories.*', 'addresses.*') // Select all columns from both tables
                            ->first();

        // Return the result or do further processing
    
       $customer_name=$pendingPayments->company_name;
       $payment_url=$this->generatePaymentUrl($pendingPayments->party_code, $pendingPayments->payment_for);
       $payment_amt=$pendingPayments->amount;
       // Extract the part after 'pay-amount/'
       $fileName = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
       $button_variable_encode_part=$fileName;

       $user = DB::table('users')->where('id', $pendingPayments->user_id)->first();
       $manager = DB::table('users')->where('id', $user->manager_id)->first();
       $manager_phone = $manager->phone;
       $pdf_url=$this->generateStatementPdf($pendingPayments->party_code, $pendingPayments->due_amount, $pendingPayments->overdue_amount, $user);
       $fileName1 = basename($pdf_url);
       $button_variable_pdf_filename=$fileName1;
          
       $templateData = [
                'name' => 'utility_payment_pending_remainder', // Don't change this template name
                'language' => 'en_US', 
                'components' => [
                    
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $customer_name],
                            ['type' => 'text', 'text' => $payment_amt],
                            ['type' => 'text', 'text' => $manager_phone],
                            
                            
                        ],
                    ],

                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            [
                                "type" => "text",
                                "text" => $button_variable_encode_part // Replace $button_text with the actual Parameter for the button.
                            ],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '1',
                        'parameters' => [
                            [
                                "type" => "text",
                                "text" => $button_variable_pdf_filename // Replace $button_text with the actual Parameter for the button.
                            ],
                        ],
                    ],
                ],
            ];


            // Convert template data to JSON for logging
            $jsonTemplateData = json_encode($templateData, JSON_PRETTY_PRINT);

            // Step 8: Send the WhatsApp message
            $this->whatsAppWebService = new WhatsAppWebService();
           // $jsonResponse = $this->whatsAppWebService->sendTemplateMessage(7044300330, $templateData);

            // echo "<pre>";
            // print_r($jsonResponse);
            // die();

            // Log the JSON request for debugging purposes
            \Log::info('WhatsApp message sent:', ['request' => $jsonResponse]);
    }


    public function paymentPendingRemainder() {
        
        $pendingPayments = DB::table('payment_histories')
                            ->join('addresses', DB::raw('payment_histories.party_code COLLATE utf8mb3_unicode_ci'), '=', DB::raw('addresses.acc_code COLLATE utf8mb3_unicode_ci'))
                            ->where('payment_histories.status', 'PENDING')
                            ->whereNotNull('payment_histories.payment_for') // Ensure payment_for is not NULL
                            ->where('payment_histories.payment_for', '!=', '') // Ensure payment_for is not empty
                            ->select('payment_histories.*', 'addresses.*') // Select all columns from both tables
                            ->get(); // Use get() to fetch all pending payments instead of first()

        foreach ($pendingPayments as $pendingPayment) {
            $customer_name = $pendingPayment->company_name;
            $payment_url = $this->generatePaymentUrl($pendingPayment->party_code, $pendingPayment->payment_for);
            $payment_amt = $pendingPayment->amount;

            // Extract the part after 'pay-amount/'
            $fileName = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
            $button_variable_encode_part = $fileName;

            $user = DB::table('users')->where('id', $pendingPayment->user_id)->first();
            $manager = DB::table('users')->where('id', $user->manager_id)->first();
            $manager_phone = $manager->phone;

            $pdf_url = $this->generateStatementPdf($pendingPayment->party_code, $pendingPayment->due_amount, $pendingPayment->overdue_amount, $user);
            $fileName1 = basename($pdf_url);
            $button_variable_pdf_filename = $fileName1;

            $templateData = [
                'name' => 'utility_payment_pending_remainder', // Don't change this template name
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $customer_name],
                            ['type' => 'text', 'text' => $payment_amt],
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
                                'text' => $button_variable_encode_part, // Replace $button_text with the actual Parameter for the button.
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
                                'text' => $button_variable_pdf_filename, // Replace $button_text with the actual Parameter for the button.
                            ],
                        ],
                    ],
                ],
            ];

            // Convert template data to JSON for logging
            // $jsonTemplateData = json_encode($templateData, JSON_PRETTY_PRINT);

            // Step 8: Send the WhatsApp message
            $this->whatsAppWebService = new WhatsAppWebService();
            $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($user->phone, $templateData);

            // Log the JSON request for debugging purposes
            \Log::info('WhatsApp message sent:', ['request' => $jsonResponse]);
        }
    }

    public function sendOverdueStatements(Request $request)
    {
        // Retrieve only 20 users with overdue amounts
        $customersQuery = User::where('user_type', 'customer')
            ->orderBy('company_name', 'asc');
            //->take(20);

        if ($request->has('manager_id') && !empty($request->manager_id)) {
            $customersQuery->where('manager_id', $request->manager_id);
        }

        if ($request->has('warehouse_id') && !empty($request->warehouse_id)) {
            $customersQuery->where('warehouse_id', $request->warehouse_id);
        }

        $customers = $customersQuery->get();
        $groupId = uniqid('group_', true); // Generate a unique group ID for this batch

        foreach ($customers as $userData) {
            $userAddressData = Address::where('user_id', $userData->id)
                ->select('gstin', 'acc_code', 'due_amount', 'overdue_amount')
                ->where('overdue_amount', '>', 3000)
                ->groupBy('gstin')
                ->orderBy('acc_code', 'ASC')
                ->get();

            foreach ($userAddressData as $address) {
                // Only proceed if overdue amount is greater than zero
                if ($address->overdue_amount > 3000) {
                    // Generate PDF statement for the user
                    $pdfUrl = $this->generateStatementPdf($address->acc_code, $address->due_amount, $address->overdue_amount, $userData);
                    $fileName = basename($pdfUrl);

                    // Get phone numbers for user, manager, and head manager
                    $userPhone = $userData->phone;
                    $managerPhone = $this->getManagerPhone($userData->manager_id);
                    $headManagerPhone = $this->getHeadManagerPhone($userData->warehouse_id);

                    // Generate the payment URL
                    $payment_url = $this->generatePaymentUrl($address->acc_code, $payment_for = "overdue_amount");
                    $payNowBtn = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
                    $button_variable_encode_part = $payNowBtn;

                    // Prepare the template data
                    $templateData = [
                        'name' => 'utility_statement_document',
                        'language' => 'en_US',
                        'components' => [
                            [
                                'type' => 'header',
                                'parameters' => [
                                    ['type' => 'document', 'document' => ['link' => $pdfUrl, 'filename' => $fileName]],
                                ],
                            ],
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $userData->company_name],
                                    ['type' => 'text', 'text' => $address->due_amount],
                                    ['type' => 'text', 'text' => $address->overdue_amount],
                                    ['type' => 'text', 'text' => $managerPhone],
                                ],
                            ],
                            [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => '0',
                                'parameters' => [
                                    ["type" => "text", "text" => $button_variable_encode_part],
                                ],
                            ],
                        ],
                    ];

                    // Insert message for the user (this should be open)
                    
                    DB::table('wa_sales_queue')->insert([
                        'group_id' => $groupId,
                        'callback_data' => $templateData['name'],
                        'recipient_type' => 'individual',
                        'to_number' => $userPhone,
                       // 'to_number' => '7044300330',
                        'type' => 'template',
                        'file_url' => $pdfUrl,
                        'file_name'=>$address->acc_code,
                        'content' => json_encode($templateData),
                        'status' => 'pending',
                        'response' => '',
                        'msg_id' => '',
                        'msg_status' => '',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]); 

                    // Insert message for the manager
                    // if ($managerPhone) {
                    //     DB::table('wa_sales_queue')->insert([
                    //         'group_id' => $groupId,
                    //         'callback_data' => $templateData['name'],
                    //         'recipient_type' => 'individual',
                    //         'to_number' => $managerPhone,
                    //         'type' => 'template',
                    //         'file_url' => $pdfUrl,
                    //         'content' => json_encode($templateData),
                    //         'status' => 'pending',
                    //         'response' => '',
                    //         'msg_id' => '',
                    //         'msg_status' => '',
                    //         'created_at' => now(),
                    //         'updated_at' => now()
                    //     ]);
                    // }

                    // Insert message for the head manager
                    // if ($headManagerPhone) {
                    //     DB::table('wa_sales_queue')->insert([
                    //         'group_id' => $groupId,
                    //         'callback_data' => $templateData['name'],
                    //         'recipient_type' => 'individual',
                    //         'to_number' => $headManagerPhone,
                    //         'type' => 'template',
                    //         'file_url' => $pdfUrl,
                    //         'content' => json_encode($templateData),
                    //         'status' => 'pending',
                    //         'response' => '',
                    //         'msg_id' => '',
                    //         'msg_status' => '',
                    //         'created_at' => now(),
                    //         'updated_at' => now()
                    //     ]);
                    // }
                }
            }
        }

        // Dispatch the job to process WhatsApp messages asynchronously
        SendWhatsAppMessagesJob::dispatch($groupId);

        return response()->json(['success' => true, 'message' => 'Overdue WhatsApp notifications queued successfully.']);
    }


    public function ___sendOverdueStatements(Request $request)
    {
        // Retrieve only 5 users with overdue amounts
        $customersQuery = User::where('user_type', 'customer')
            ->orderBy('company_name', 'asc')
            ->take(20); // Limit to 5 records

        if ($request->has('manager_id') && !empty($request->manager_id)) {
            $customersQuery->where('manager_id', $request->manager_id);
        }

        if ($request->has('warehouse_id') && !empty($request->warehouse_id)) {
            $customersQuery->where('warehouse_id', $request->warehouse_id);
        }

        $customers = $customersQuery->get();

        foreach ($customers as $userData) {
            $userAddressData = Address::where('user_id', $userData->id)
                ->select('gstin', 'acc_code', 'due_amount', 'overdue_amount')
                ->groupBy('gstin')
                ->orderBy('acc_code', 'ASC')
                ->get();
               

               
            foreach ($userAddressData as $address) {
                
                // Only proceed if overdue amount is greater than zero
                if ($address->overdue_amount > 0) {
                    // Generate PDF statement for the user
                    $pdfUrl = $this->generateStatementPdf($address->acc_code, $address->due_amount, $address->overdue_amount, $userData);
                    $fileName = basename($pdfUrl);

                    // Prepare WhatsApp message content using template
                    $to = "7044300330";
                     //$to = $userData->phone; // Dynamically set the customer's phone number

                    // Get manager's phone number if available
                    $managerPhone = null;
                    if ($userData->manager_id) {
                        $managerData = DB::table('users')
                            ->where('id', $userData->manager_id)
                            ->select('phone')
                            ->first();
                        
                        $managerPhone = $managerData->phone ?? null;
                    }
                    $headManagerPhone = $this->getHeadManagerPhone($userData->warehouse_id);
                    echo $headManagerPhone;
                    die();


                    $payment_url = $this->generatePaymentUrl($address->acc_code, $payment_for = "custom_amount");
                    $payNowBtn = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
                    $button_variable_encode_part = $payNowBtn;

                    $templateData = [
                        'name' => 'utility_statement_document',
                        'language' => 'en_US',
                        'components' => [
                            [
                                'type' => 'header',
                                'parameters' => [
                                    ['type' => 'document', 'document' => ['link' => $pdfUrl, 'filename' => $fileName]],
                                ],
                            ],
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $userData->company_name],
                                    ['type' => 'text', 'text' => $address->due_amount],
                                    ['type' => 'text', 'text' => $address->overdue_amount],
                                    ['type' => 'text', 'text' => $managerPhone ?? 'N/A'],
                                ],
                            ],
                            [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => '0',
                                'parameters' => [
                                    [
                                        "type" => "text",
                                        "text" => $button_variable_encode_part
                                    ],
                                ],
                            ],
                        ],
                    ];

                    // Send the message using WhatsApp web service
                    $this->whatsAppWebService = new WhatsAppWebService();
                    $resp = $this->whatsAppWebService->sendTemplateMessage($to, $templateData);
                    // echo "<pre>";
                    // print_r($resp);
                   
                }
               
            }
           
        }

        return response()->json(['success' => true, 'message' => 'Overdue WhatsApp notifications sent successfully.']);
    }





    //statement sendwhatsapp all using cron
  


    public function apiStatementSendWhatsappAll(Request $request)
    {
        // Increase memory limit and execution time to avoid timeouts
        set_time_limit(-1);
        header('Keep-Alive: timeout=86400, max=100');
        header('Cache-Control: no-cache');
        header('Connection: Keep-Alive');
        ini_set('memory_limit', '512M');
        
        // Generate a unique group_id
        $groupId = uniqid('group_', true);

        try {
            // Query the addresses table for records with due or overdue amounts
            $addresses = Address::where('due_amount', '>', 2000)
                // ->orWhere('overdue_amount', '>', 0)
                ->select('user_id', 'due_amount', 'overdue_amount', 'gstin', 'acc_code')
                ->groupBy('user_id')
                ->get();

            foreach ($addresses as $address) {
                $user = User::where('id', $address->user_id)
                    ->select('party_code', 'company_name', 'phone', 'manager_id', 'warehouse_id')
                    ->first();

                if (!$user) {
                    continue; // Skip if the user does not exist
                }

                try {
                    // Get manager and head manager phone numbers
                    $managerPhone = $this->getManagerPhone($user->manager_id);
                    $headManagerPhone = $this->getHeadManagerPhone($user->warehouse_id);

                    // Generate the PDF statement
                    $pdfUrl = $this->generateStatementPdf($user->party_code, $address->due_amount, $address->overdue_amount, $user);
                    $fileName = basename($pdfUrl);

                    // Generate the payment URL
                    $payment_url = $this->generatePaymentUrl($user->party_code, $payment_for = "custom_amount");
                    $payNowBtn = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
                    $button_variable_encode_part = $payNowBtn;

                    // Prepare the template data
                    $templateData = [
                        'name' => 'utility_statement_document',
                        'language' => 'en_US',
                        'components' => [
                            [
                                'type' => 'header',
                                'parameters' => [
                                    ['type' => 'document', 'document' => ['link' => $pdfUrl, 'filename' => $fileName]],
                                ],
                            ],
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $user->company_name ?? 'No Company Name'],
                                    ['type' => 'text', 'text' => $address->due_amount],
                                    ['type' => 'text', 'text' => $address->overdue_amount],
                                    ['type' => 'text', 'text' => $managerPhone]
                                ],
                            ],
                            [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => '0',
                                'parameters' => [
                                    ["type" => "text", "text" => $button_variable_encode_part],
                                ],
                            ],
                        ],
                    ];

                    // Insert message for the user
                    DB::table('wa_sales_queue')->insert([
                        'group_id' => $groupId,
                        'callback_data' => $templateData['name'],
                        'recipient_type' => 'individual',
                        'to_number' => $user->phone,
                        'type' => 'template',
                        'file_url' => $pdfUrl,
                        'file_name'=>$user->party_code,
                        'content' => json_encode($templateData),
                        'status' => 'pending',
                        'response' => '',
                        'msg_id' => '',
                        'msg_status' => '',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Insert message for the manager
                    // DB::table('wa_sales_queue')->insert([
                    //     'group_id' => $groupId,
                    //     'callback_data' => $templateData['name'],
                    //     'recipient_type' => 'individual',
                    //     'to_number' => $managerPhone,
                    //     'type' => 'template',
                    //     'file_url' => $pdfUrl,
                    //     'content' => json_encode($templateData),
                    //     'status' => 'pending',
                    //     'response' => '',
                    //     'msg_id' => '',
                    //     'msg_status' => '',
                    //     'created_at' => now(),
                    //     'updated_at' => now()
                    // ]);

                    // Insert message for the head manager
                    // DB::table('wa_sales_queue')->insert([
                    //     'group_id' => $groupId,
                    //     'callback_data' => $templateData['name'],
                    //     'recipient_type' => 'individual',
                    //     'to_number' => $headManagerPhone,
                    //     'type' => 'template',
                    //     'file_url' => $pdfUrl,
                    //     'content' => json_encode($templateData),
                    //     'status' => 'pending',
                    //     'response' => '',
                    //     'msg_id' => '',
                    //     'msg_status' => '',
                    //     'created_at' => now(),
                    //     'updated_at' => now()
                    // ]);

                } catch (\Exception $e) {
                    Log::error("Error processing user ID {$user->id}: {$e->getMessage()}");
                    continue;
                }
            }

            // Dispatch the job to process WhatsApp messages asynchronously
            SendWhatsAppMessagesJob::dispatch($groupId);

            // Return the group_id as part of the response
            return response()->json([
                'success' => true,
                'message' => 'WhatsApp messages are being processed',
                'group_id' => $groupId
            ]);

        } catch (\Exception $e) {
            Log::error("Error in apiStatementSendWhatsappAll function", ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error processing data']);
        }
    }

    // Helper function to get head manager phone number based on warehouse location
    private function getHeadManagerPhone($warehouseId)
    {
        switch ($warehouseId) {
            case 1: // Kolkata
                return $this->getManagerPhone(180);
            case 2: // Delhi
                return $this->getManagerPhone(25606);
            case 6: // Mumbai
                return $this->getManagerPhone(169);
            default:
                return null; // Default case if warehouse does not match
        }
    }

  

    public function generateUserStatementPDF($user_id)
    {
        // Fetch the user data
        $user = DB::table('addresses')
			->where('acc_code', $user_id)
			->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Fetch the due amount and overdue amount from the address table
        $addressData = DB::table('addresses')
            ->where('acc_code', $user_id)
            ->select('due_amount', 'overdue_amount', 'acc_code')
            ->first();

        if (!$addressData) {
            return response()->json(['error' => 'No address data found for the user'], 404);
        }
        // Generate PDF and get the URL
        $pdfUrl = $this->generateStatementPdf(
            $addressData->acc_code, 
            $addressData->due_amount, 
            $addressData->overdue_amount, 
            $user
        );

        // Return the PDF URL as JSON response
        return response()->json(['pdf_url' =>$pdfUrl]);
    }


    public function generateInvoice($invoice_no)
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
                  'phone' => '9730377752',
                  'email' => 'acetools505@gmail.com',
              ],
              'DEL' => [
                  'gstin' => '07ABACA4198B1ZX',
                  'company_name' => 'ACE TOOLS PVT LTD',
                  'address_1' => 'Ground Floor, Plot No 220/219 & 220 Kh No 58/2',
                  'address_2' => 'Rithala Road',
                  'address_3' => 'Rithala',
                  'city' => 'New Delhi',
                  'state' => 'Delhi',
                  'postal_code' => '110085',
                  'contact_name' => 'Mustafa Worliwala',
                  'phone' => '9871165253',
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

          return $publicUrl;
         
      }


   public function getOrderLogisticsDetails()
{
    // Generate a unique group ID for this batch of messages
    $groupId = uniqid('group_', true);
    $this->whatsAppWebService = new WhatsAppWebService();
    // Fetch and join `order_logistics` and `addresses` tables
    $results = DB::table('order_logistics')
        ->join('addresses', 'order_logistics.party_code', '=', 'addresses.acc_code')
		->leftJoin('invoice_orders', DB::raw("BINARY order_logistics.invoice_no"), '=', DB::raw("BINARY invoice_orders.invoice_no"))
        ->select(
            'order_logistics.id as order_logistics_id',
            'order_logistics.party_code',
            'order_logistics.order_no',
            'order_logistics.invoice_no',
            'order_logistics.lr_no',
            'order_logistics.lr_date',
            'order_logistics.no_of_boxes',
            'order_logistics.payment_type',
            'order_logistics.lr_amount',
            'order_logistics.attachment',
            'addresses.company_name',
            'addresses.address',
            'addresses.address_2',
            'addresses.city',
            'addresses.state_id',
            'addresses.country_id',
            'addresses.gstin',
            'addresses.phone',
            'addresses.user_id',
			'invoice_orders.created_at as invoice_date'
        )
        ->whereNotNull('order_logistics.lr_no') // Ensure `lr_no` is not null
          ->where('order_logistics.invoice_no', 'DEL/0040/25-26')
        ->where('order_logistics.lr_no', '!=', '') // Ensure `lr_no` is not blank
        ->where('order_logistics.wa_is_processed', false) // Only unprocessed records
        ->groupBy('order_logistics.id') // Group by order_logistics to remove duplicates
        ->get();

        

    // Process and send WhatsApp messages
    $results->each(function ($item) use ($groupId) {
        // Extract `transport_name` and refine `lr_no`
        if (!empty($item->lr_no)) {
			//$lrParts = explode('-', trim($item->lr_no));
			$item->transport_name = $item->lr_no; // Extract and trim transport name
			//$item->lr_no = isset($lrParts[1]) ? trim($lrParts[1]) : $item->lr_no; // Extract or retain trimmed `lr_no`
		} else {
			$item->transport_name = null;
			$item->lr_no = null;
		}

        //get first attatchment addition added code start date 18/12/2024
         $attachments = explode(',', $item->attachment);
         $item->attachment = $attachments[0] ?? null;
        //additional added code end

        // Generate file name using `lr_no` and `invoice_no`
        $lrNo = preg_replace('/[^A-Za-z0-9]/', '_', $item->lr_no); // Replace invalid characters
        $invoiceNo = preg_replace('/[^A-Za-z0-9]/', '_', $item->invoice_no); // Replace invalid characters
        $fileName = "{$lrNo}_{$invoiceNo}." . pathinfo($item->attachment, PATHINFO_EXTENSION);

        // Define the storage path
        $storagePath = 'uploads/cw_acetools';
        $publicFilePath = public_path($storagePath . '/' . $fileName);

        try {
            // Ensure the directory exists
            if (!file_exists(public_path($storagePath))) {
                mkdir(public_path($storagePath), 0777, true);
            }

            // Check if the attachment is valid before fetching
            if (!empty($item->attachment)) {
                // Use Laravel's Http client to fetch the file
                $response = Http::get($item->attachment);

                if ($response->successful()) {
                    // Save the file to the public folder
                    file_put_contents($publicFilePath, $response->body());

                    // Assign the public URL for WhatsApp message
                    $item->attachment_url = url('public/' . $storagePath . '/' . $fileName);
                } else {
                    $item->attachment_url = asset('public/uploads/cw_acetools/default_image.jpg'); // Fallback URL
                }
            } else {
                // Log a warning if attachment is missing
                Log::warning("Attachment is null for order logistics ID: {$item->order_logistics_id}");
                $item->attachment_url = asset('public/uploads/cw_acetools/default_image.jpg'); // Fallback URL
            }
        } catch (\Exception $e) {
            // Handle errors during file download
            Log::error('Error downloading file: ' . $e->getMessage());
            $item->attachment_url = asset('public/uploads/cw_acetools/default_image.jpg'); // Fallback URL
        }
       
       
       // $invoice_url=$this->generateInvoice(encrypt($item->invoice_no));

      
        $invoice_url=$this->getInvoicePdfURL($item->invoice_no);
       
       
        $buttonVariable=basename($invoice_url);
        //for pdf start
        $isPdf = preg_match('/\.pdf$/i', $item->attachment_url);
        $media_id = $isPdf ? $this->whatsAppWebService->uploadMedia($item->attachment_url) : null;
        $templateName = $isPdf ? 'utility_logistic_fresh_pdfs' : 'utility_logistic_fresh';
        $whatsappMessage = [
            'name' => $templateName,
            'language' => 'en_US',
            'components' => [
                 [
                    'type' => 'header',
                    'parameters' => $isPdf 
                        ? [[
                            'type' => 'document',
                            'document' => [
                                'id' => $media_id['media_id'],
                                'filename' => 'Order Logistic Notification' // Adding filename for PDFs
                            ]
                        ]]
                        : [[
                            'type' => 'image',
                            'image' => ['link' => $item->attachment_url]
                        ]], // Image handling
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $item->company_name ?: 'N/A'], // Customer name
                        ['type' => 'text', 'text' => $item->invoice_no ?: 'N/A'], // Invoice 
                        ['type' => 'text', 'text' => $item->invoice_date ?: 'N/A'], // Invoice date
                        ['type' => 'text', 'text' => $item->transport_name ?: 'N/A'], // Transport name - lr number                           
                        ['type' => 'text', 'text' => $item->lr_date ?: 'N/A'], // LR date
                        ['type' => 'text', 'text' => $item->no_of_boxes ?: '0.00'], // No. of boxes
                    ],
                ],
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [
                        ['type' => 'text', 'text' => $buttonVariable],
                    ],
                ],
            ],
        ];

        //for pdf end

        $phone = $item->phone ?? 'N/A'; // Placeholder recipient phone number

        $user = DB::table('users')->where('id', $item->user_id)->first();
        $managerPhone = $this->getManagerPhone($user->manager_id);

        // Insert message for the user
        DB::table('wa_sales_queue')->insert([
            'group_id' => $groupId,
            'callback_data' => $whatsappMessage['name'],
            'recipient_type' => 'individual',
             // 'to_number' => $user->phone,
            'to_number' => '7044300330',
            'type' => 'template',
            'file_url' => $item->attachment_url ?: 'N/A',
            'content' => json_encode($whatsappMessage),
            'status' => 'pending',
            'response' => '',
            'msg_id' => '',
            'msg_status' => '',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // DB::table('wa_sales_queue')->insert([
        //     'group_id' => $groupId,
        //     'callback_data' => $whatsappMessage['name'],
        //     'recipient_type' => 'individual',
        //     'to_number' => $managerPhone,
        //     'type' => 'template',
        //     'file_url' => $item->attachment_url ?: 'N/A',
        //     'content' => json_encode($whatsappMessage),
        //     'status' => 'pending',
        //     'response' => '',
        //     'msg_id' => '',
        //     'msg_status' => '',
        //     'created_at' => now(),
        //     'updated_at' => now()
        // ]);
		
		// DB::table('wa_sales_queue')->insert([
        //     'group_id' => $groupId,
        //     'callback_data' => $whatsappMessage['name'],
        //     'recipient_type' => 'individual',
        //     'to_number' => '9894753728',
        //     'type' => 'template',
        //     'file_url' => $item->attachment_url ?: 'N/A',
        //     'content' => json_encode($whatsappMessage),
        //     'status' => 'pending',
        //     'response' => '',
        //     'msg_id' => '',
        //     'msg_status' => '',
        //     'created_at' => now(),
        //     'updated_at' => now()
        // ]);

        // Mark the record as processed
        DB::table('order_logistics')
            ->where('id', $item->order_logistics_id)
            ->update(['wa_is_processed' => true]);
    });

  
        // No data was retrieved
       SendWhatsAppMessagesJob::dispatch($groupId);
   

    // Return the processed results
    return response()->json($results);
}
public function getInvoicePdfURL($id)
{
    $invoice = InvoiceOrder::with('invoice_products')->where('invoice_no', $id)->firstOrFail();

    if (is_string($invoice->party_info)) {
        $invoice->party_info = json_decode($invoice->party_info, true);
    }

    $shipping = Address::find($invoice->shipping_address_id);

    // ✅ Fetch transport info from order_logistics
    $logistic = OrderLogistic::where('invoice_no', $id)->orderByDesc('id')->first();


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

    // ✅ Pass $logistic to the view

    $pdfContentService = new PdfContentService();
    $pdfContentBlock   = $pdfContentService->buildBlockForType('invoice');
    
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

    $fileName = str_replace('/', '_', $invoice->invoice_no) . '.pdf';
    $filePath = $pdfDir . '/' . $fileName;
    $pdf->save($filePath);

    return url('public/purchase_history_invoice/' . $fileName);
}



  public function getOrderDetailsByApprovalCode()
{
    // Fetch the specific code from the order_approvals table
    $approvalCodes = DB::table('order_approvals')
       //  ->where('code', '20241119-15110371') // Add where condition for specific code
        ->select('code')
        ->get();

    // Check if any codes are found
    if ($approvalCodes->isEmpty()) {
        echo "No codes found in the order_approvals table matching the condition.";
        die();
    }

    foreach ($approvalCodes as $approval) {
        $code = $approval->code;

        // Fetch data from the orders table for the current code
        $orderData = DB::table('orders')
            ->select('orders.*')
            ->where('orders.code', $code)
            ->first();

        // Skip if no order data exists for the current code
        if (!$orderData) {
            echo "No order found for the code: $code\n";
            continue;
        }

        // Fetch the details JSON data from order_approvals table for the current code
        $approvalData = DB::table('order_approvals')
            ->select('details')
            ->where('code', $code)
            ->first();

        if (!$approvalData) {
            echo "No approval data found for the code: $code\n";
            continue;
        }

        // Fix and decode the JSON details
        $detailsJson = $this->fixJson($approvalData->details);
        $details = json_decode($detailsJson, true);

        if (empty($details) || !is_array($details)) {
            echo "No valid details found or invalid JSON for code: $code\n";
            continue;
        }

        // Fetch data from the order_details table joined with the products table
        $orderDetailsData = DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->select('order_details.*', 'products.part_no') // Include part_no from products
            ->where('order_details.order_id', $orderData->id)
            ->get();

        if ($orderDetailsData->isEmpty()) {
            echo "No order details found for Order ID: " . $orderData->id . "\n";
            continue;
        }

        // Match the part_no and update the approved_quantity and approved_rate
        foreach ($orderDetailsData as $detail) {
            foreach ($details as $item) {
                // Validate item structure and part_no key
                if (!is_array($item) || !isset($item['part_no'])) {
                    echo "Invalid structure or missing 'part_no' for item in code: $code\n";
                    continue;
                }

                if ($detail->part_no === $item['part_no']) {
                    // Safely retrieve other fields
                    $baseRate = isset($item['Rate']) && is_numeric($item['Rate']) ? floatval($item['Rate']) : 0;
                    $approvedQuantity = isset($item['order_qty']) && is_numeric($item['order_qty']) ? floatval($item['order_qty']) : 0;

                    // Calculate rates and amounts
                    $approvedRateWithGst = $baseRate + ($baseRate * 0.18); // Add 18% GST
                    $approvedRateFormatted = $approvedRateWithGst <= 50 ? number_format($approvedRateWithGst, 2) : round($approvedRateWithGst);
                    $finalAmount = $approvedQuantity * $approvedRateFormatted;

                    // Update the database
                    DB::table('order_details')
                        ->where('id', $detail->id)
                        ->update([
                            'approved_quantity' => $approvedQuantity,
                            'approved_rate' => $approvedRateFormatted,
                            'final_amount' => $finalAmount,
                        ]);

                    echo "Updated approved_quantity to {$approvedQuantity} and approved_rate to {$approvedRateFormatted} for Part No: {$item['part_no']} (Code: $code)\n";
                }
            }
        }
    }

    echo "Processing of the codes completed.";
}




 public function processOrderBills()
{
    // Fetch all records from the order_bills table
    $orderBillsData = DB::table('order_bills')
->where('code', '20241119-15110371') // Add where condition for specific code
    ->get();

    // Check if any data is found
    if ($orderBillsData->isEmpty()) {
        echo "No data found in the order_bills table matching the condition.";
        die();
    }

    // Process each bill record
    foreach ($orderBillsData as $billData) {
        // Fix and decode the JSON details from the details column
        $detailsJson = $this->fixJson($billData->details);
        $details = json_decode($detailsJson, true);

        // Ensure $details is a valid array
        if (!is_array($details)) {
            echo "No valid details found or invalid JSON for code: {$billData->code}\n";
            continue;
        }

        // Extract the place of dispatch from invoice_no
        $invoiceNoParts = explode('/', $billData->invoice_no); // Split the invoice_no by '/'
        $placeOfDispatch = $invoiceNoParts[0] ?? 'N/A'; // Default to 'N/A'

        // Process each part_no in the bill details
        foreach ($details as $item) {
            // Validate item structure and required keys
            if (!is_array($item) || !isset($item['part_no'], $item['billed_qty'])) {
                echo "Invalid structure or missing keys in bill details for code: {$billData->code}\n";
                continue;
            }

            // Fetch product ID using part_no
            $productData = DB::table('products')
                ->where('part_no', $item['part_no'])
                ->first();

            if (!$productData) {
                echo "No product found for Part No: {$item['part_no']}\n";
                continue;
            }

            // Fetch the corresponding order detail record from the order_details table
            $orderDetail = DB::table('order_details')
                ->where('product_id', $productData->id)
                ->where('dispatch_id', $billData->dispatch_id)
                ->first();

            if ($orderDetail) {
                // Calculate the final amount (billed_quantity * approved_rate)
                $billedQuantity = is_numeric($item['billed_qty']) ? (float)$item['billed_qty'] : 0;
                $approvedRate = is_numeric($orderDetail->approved_rate) ? (float)$orderDetail->approved_rate : 0;
                $finalAmount = $billedQuantity * $approvedRate;

                // Update the existing record with billed_quantity, billed_invoice_no, and place_of_dispatch
                DB::table('order_details')
                    ->where('id', $orderDetail->id)
                    ->update([
                        'billed_quantity' => $billedQuantity,
                        'billed_invoice_no' => $billData->invoice_no,
                        'place_of_dispatch' => $placeOfDispatch,
                        'final_amount' => $finalAmount,
                    ]);

                echo "Updated row for Part No: {$item['part_no']}, Order Detail ID: {$orderDetail->id}, Place of Dispatch: {$placeOfDispatch}\n";
            } else {
                echo "No matching order detail found for Product ID: {$productData->id}, Part No: {$item['part_no']}, Dispatch ID: {$billData->dispatch_id}\n";
            }
        }
    }

    echo "Processing of all bill records completed.";
}
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
 * Create a valid JSON object from invalid JSON part.
 *
 * @param string $jsonPart
 * @return array
 */
private function createValidObject($jsonPart)
{
    $jsonPart = preg_replace('/[^a-zA-Z0-9:{},"\[\]\s]+/', '', $jsonPart);
    $decoded = json_decode($jsonPart, true);

    return $decoded ?: ['invalid_data' => true];
}





    public function notifyManagerAPI(Request $request)
    {
        // Special access: Hatim (178) can see Hussain's (26786) customers
        $specialAccess = [
            178 => [26786],
        ];

        // Fetch all managers (role_id = 5)
        $managers = User::join('staff', 'users.id', '=', 'staff.user_id')
            ->where('staff.role_id', 5)
            ->select('users.*')
            ->get();

        if ($managers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No managers found for the selected options.'
            ]);
        }

        $groupId = uniqid('group_', true);

        foreach ($managers as $manager) {
            // Apply Hatim–Hussain logic
            $customersQuery = User::where('user_type', 'customer')->orderBy('company_name', 'asc');

            if (isset($specialAccess[$manager->id])) {
                $allowedManagerIds = array_merge([$manager->id], $specialAccess[$manager->id]);
                $customersQuery->whereIn('manager_id', $allowedManagerIds);
            } else {
                $customersQuery->where('manager_id', $manager->id);
            }

            $customers = $customersQuery->get();

            $processedData = [];
            $totalDueAmount = 0;
            $totalOverdueAmount = 0;

            foreach ($customers as $userData) {
                $userAddressData = Address::where('user_id', $userData->id)
                    ->select('city', 'company_name', 'gstin', 'acc_code', 'due_amount', 'overdue_amount')
                    ->groupBy('gstin')
                    ->orderBy('acc_code', 'ASC')
                    ->get();

                foreach ($userAddressData as $address) {
                    if ($address->due_amount > 0 || $address->overdue_amount > 0) {
                        $totalDueAmount += $address->due_amount;
                        $totalOverdueAmount += $address->overdue_amount;

                        $processedData[] = [
                            'customer' => ['company_name' => $address->company_name],
                            'address' => $address,
                            'phone' => $userData->phone,
                            'city' => $address->city
                        ];
                    }
                }
            }

            // Sort processed data
            usort($processedData, function ($a, $b) {
                $cityComparison = strcmp($a['city'], $b['city']);
                if ($cityComparison !== 0) return $cityComparison;
                $companyNameComparison = strcmp($a['customer']['company_name'], $b['customer']['company_name']);
                return $companyNameComparison !== 0
                    ? $companyNameComparison
                    : $b['address']->overdue_amount <=> $a['address']->overdue_amount;
            });

            // Generate PDF
            if (!empty($processedData)) {
                $fileName = 'Manager_Report_' . $manager->name . '_' . rand(1000, 9999) . '.pdf';
                $pdf = PDF::loadView('backend.statement.manager-report', [
                    'manager_name' => $manager->name,
                    'processedData' => $processedData,
                    'totalDueAmount' => $totalDueAmount,
                    'totalOverdueAmount' => $totalOverdueAmount
                ])->save(public_path('statements/' . $fileName));

                $publicUrl = url('public/statements/' . $fileName);

                // WhatsApp template payload
                $templateData = [
                    'name' => 'utility_statement_manager_notify',
                    'language' => 'en_US',
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [[
                                'type' => 'document',
                                'document' => [
                                    'link' => $publicUrl,
                                    'filename' => $fileName
                                ]
                            ]],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $manager->name],
                                ['type' => 'text', 'text' => $totalDueAmount],
                                ['type' => 'text', 'text' => $totalOverdueAmount],
                            ],
                        ],
                    ],
                ];

                DB::table('wa_sales_queue')->insert([
                    'group_id' => $groupId,
                    'callback_data' => $templateData['name'],
                    'recipient_type' => 'individual',
                    'to_number' => $manager->phone,
                    'type' => 'template',
                    'file_url' => $publicUrl,
                    'file_name' => $manager->name,
                    'content' => json_encode($templateData),
                    'status' => 'pending',
                    'response' => '',
                    'msg_id' => '',
                    'msg_status' => '',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // Dispatch WhatsApp queue job
        SendWhatsAppMessagesJob::dispatch($groupId);

        return response()->json([
            'status' => true,
            'message' => 'Reports generated and notifications sent successfully.',
        ], 200);
    }

    public function orgs_notifyManagerAPI(Request $request)
    {


        // Fetch managers using the statementWhatsappManagers logic
        $managers = User::join('staff', 'users.id', '=', 'staff.user_id')
            ->where('staff.role_id', 5) // Ensure it fetches managers only
            // ->where('users.id', 180) // Ensure it fetches managers only
            ->select('users.*')
            ->get();

           

        if ($managers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No managers found for the selected options.'
            ]);
        }


         $groupId = uniqid('group_', true);
        foreach ($managers as $manager) {
            $customers = User::where('user_type', 'customer')
                              ->where('manager_id', $manager->id)
                              ->orderBy('company_name', 'asc')
                              ->get();

            $processedData = [];
            $totalDueAmount = 0;
            $totalOverdueAmount = 0;

            foreach ($customers as $userData) {
                $userAddressData = Address::where('user_id', $userData->id)
                    ->select('city', 'company_name', 'gstin', 'acc_code', 'due_amount', 'overdue_amount') 
                    ->groupBy('gstin')
                    ->orderBy('acc_code', 'ASC')
                    ->get();

                foreach ($userAddressData as $address) {
                    if ($address->due_amount > 0 || $address->overdue_amount > 0) {
                        $totalDueAmount += $address->due_amount;
                        $totalOverdueAmount += $address->overdue_amount;

                        $processedData[] = [
                            'customer' => ['company_name' => $address->company_name],
                            'address' => $address,
                            'phone' => $userData->phone,
                            'city' => $address->city
                        ];
                    }
                }
            }

            // Sort processed data
            usort($processedData, function ($a, $b) {
                $cityComparison = strcmp($a['city'], $b['city']);
                if ($cityComparison !== 0) {
                    return $cityComparison;
                }
                $companyNameComparison = strcmp($a['customer']['company_name'], $b['customer']['company_name']);
                return $companyNameComparison !== 0 ? $companyNameComparison : $b['address']->overdue_amount <=> $a['address']->overdue_amount;
            });

          

            // Generate PDF if data is available
            if (!empty($processedData)) {
                $fileName = 'Manager_Report_' . $manager->name . '_' . rand(1000, 9999) . '.pdf';
                $pdf = PDF::loadView('backend.statement.manager-report', [
                    'manager_name' => $manager->name,
                    'processedData' => $processedData,
                    'totalDueAmount' => $totalDueAmount,
                    'totalOverdueAmount' => $totalOverdueAmount
                ])->save(public_path('statements/' . $fileName));

                $publicUrl = url('public/statements/' . $fileName);

                // WhatsApp template message
                $templateData = [
                    'name' => 'utility_statement_manager_notify',
                    'language' => 'en_US',
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [['type' => 'document', 'document' => ['link' => $publicUrl, 'filename' => $fileName]]],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $manager->name],
                                ['type' => 'text', 'text' => $totalDueAmount],
                                ['type' => 'text', 'text' => $totalOverdueAmount],
                            ],
                        ],
                    ],
                ];

                 DB::table('wa_sales_queue')->insert([
                        'group_id' => $groupId,
                        'callback_data' => $templateData['name'],
                        'recipient_type' => 'individual',
                        'to_number' => $manager->phone,
                        'type' => 'template',
                        'file_url' => $publicUrl,
                        'file_name'=>$manager->name,
                        'content' => json_encode($templateData),
                        'status' => 'pending',
                        'response' => '',
                        'msg_id' => '',
                        'msg_status' => '',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);


                // $this->whatsAppWebService = new WhatsAppWebService();
                //$this->whatsAppWebService->sendTemplateMessage($manager->phone, $templateData);
                // $this->whatsAppWebService->sendTemplateMessage('7044300330', $templateData);
            }
        }

        SendWhatsAppMessagesJob::dispatch($groupId);

        return response()->json([
            'status' => true,
            'message' => 'Reports generated and notifications sent successfully.',
        ], 200);
    }




    public function v2_statementExport(Request $request)
    {

        // Set execution time limit
        set_time_limit(-1);

        // ✅ **Base Query for Customers**
        $customersQuery = DB::table('users')
            ->join('addresses', function ($join) {
                $join->on(DB::raw("LEFT(users.party_code, 11)"), '=', DB::raw("LEFT(addresses.acc_code, 11)"));
            })
            ->leftJoin('users as managers', 'users.manager_id', '=', 'managers.id')
            ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
            ->where('users.user_type', 'customer');

        // ✅ **Filter customers who have due or overdue amounts**
        $customersQuery->where(function ($query) {
            $query->whereRaw('CAST(NULLIF(addresses.due_amount, "") AS DECIMAL(10,2)) > 0')
                  ->orWhereRaw('CAST(NULLIF(addresses.overdue_amount, "") AS DECIMAL(10,2)) > 0');
        });

        // ✅ **Apply Filters**
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $customersQuery->where(function ($query) use ($searchTerm) {
                $query->where('users.party_code', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('users.name', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('addresses.company_name', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('addresses.city', 'LIKE', '%' . $searchTerm . '%');
            });
        }
        if ($request->has('manager_id') && !empty($request->manager_id)) {
            $customersQuery->where('users.manager_id', $request->manager_id);
        }
        if ($request->has('warehouse_id') && !empty($request->warehouse_id)) {
            $customersQuery->where('users.warehouse_id', $request->warehouse_id);
        }
        if ($request->has('duefilter') && !empty($request->duefilter)) {
            if ($request->duefilter === 'due') {
                $customersQuery->whereRaw('CAST(NULLIF(addresses.due_amount, "") AS DECIMAL(10,2)) > 0');
            } elseif ($request->duefilter === 'overdue') {
                $customersQuery->whereRaw('CAST(NULLIF(addresses.overdue_amount, "") AS DECIMAL(10,2)) > 0');
            }
        }

        // ✅ **Calculate Total Due and Overdue Amount with Filters Applied**
        $filteredTotalQuery = clone $customersQuery;
        $totalDueAmount = $filteredTotalQuery->sum(DB::raw('CAST(NULLIF(addresses.due_amount, "") AS DECIMAL(10,2))'));
        $totalOverdueAmount = $filteredTotalQuery->sum(DB::raw('CAST(NULLIF(addresses.overdue_amount, "") AS DECIMAL(10,2))'));

        // ✅ **Apply Sorting**
        $customersQuery->orderBy('warehouses.name', 'asc')
            ->orderBy('managers.name', 'asc')
            ->orderByRaw('LOWER(addresses.city) ASC');

        // ✅ **Fetch Data**
        $customers = $customersQuery
            ->select(
                'addresses.company_name as party_name',
                'addresses.acc_code as party_code',
                'users.phone',
                'managers.name as manager_name',
                'warehouses.name as warehouse_name',
                'addresses.city',
                DB::raw('CAST(NULLIF(addresses.due_amount, "") AS DECIMAL(10,2)) as due_amount_numeric'),
                DB::raw('CAST(NULLIF(addresses.overdue_amount, "") AS DECIMAL(10,2)) as overdue_amount_numeric')
            )
            ->get();

        // ✅ **Prepare Data for Export**
        $exportData = [];

        foreach ($customers as $customer) {
            $exportData[] = [
                'Party Name' => $customer->party_name,
                'Party Code' => $customer->party_code,
                'Phone' => $customer->phone,
                'Manager' => $customer->manager_name ?? 'N/A',
                'Warehouse' => $customer->warehouse_name ?? 'N/A',
                'City' => $customer->city,
                'Due Amount' => $customer->due_amount_numeric,
                'Overdue Amount' => $customer->overdue_amount_numeric,
            ];
        }

        // ✅ **Add Total Row**
        if (count($exportData) > 0) {
            $exportData[] = [
                'Party Name' => 'TOTAL',
                'Party Code' => '',
                'Phone' => '',
                'Manager' => '',
                'Warehouse' => '',
                'City' => '',
                'Due Amount' => $totalDueAmount,
                'Overdue Amount' => $totalOverdueAmount,
            ];
        }

        // ✅ **Export to Excel**
        return Excel::download(new \App\Exports\ExportStatement($exportData), 'statements.xlsx');
    }

    function computeFirstOverdueDays(string $partyCode, int $creditDays): ?int
    {
        $address = Address::where('acc_code', $partyCode)->first();
        if (!$address || empty($address->statement_data)) return null;

        $statement = json_decode($address->statement_data, true);
        if (!$statement || !is_array($statement)) return null;

        // Find closing C/f...
        $closing = null;
        foreach ($statement as $row) {
            if (($row['ledgername'] ?? '') === 'closing C/f...') { $closing = $row; break; }
        }
        if (!$closing) return null;

        $clDr = (float)($closing['dramount'] ?? 0);
        $clCr = (float)($closing['cramount'] ?? 0);
        if ($clDr <= $clCr) return null; // no net debit => no overdue

        $overdueDateFrom = date('Y-m-d', strtotime('-' . max(0, (int)$creditDays) . ' days'));

        // FIFO (oldest first)
        $rows = array_reverse($statement);

        // Overdue base: DR up to cutoff minus all CR
        $drBefore = 0.0; $crBefore = 0.0;
        foreach ($rows as $e) {
            if (($e['ledgername'] ?? '') === 'closing C/f...') continue;
            $d = $e['trn_date'] ?? null; if (!$d) continue;
            if (strtotime($d) > strtotime($overdueDateFrom)) {
                $crBefore += (float)($e['cramount'] ?? 0);
            } else {
                $drBefore += (float)($e['dramount'] ?? 0);
                $crBefore += (float)($e['cramount'] ?? 0);
            }
        }

        $overdueAmount = $drBefore - $crBefore;
        if ($overdueAmount <= 0) return null;

        // Find first Partial/Overdue age
        $temp = $overdueAmount;
        $firstPartialDays = null;
        $firstOverdueDays = null;

        foreach ($rows as $e) {
            if (($e['ledgername'] ?? '') === 'closing C/f...') continue;
            $date  = $e['trn_date'] ?? null; if (!$date) continue;
            $drAmt = (float)($e['dramount'] ?? 0);

            if (strtotime($date) > strtotime($overdueDateFrom)) continue;
            if ($temp > 0 && $drAmt > 0) {
                $temp -= $drAmt;
                $diffDays = (int) floor(abs(strtotime($overdueDateFrom) - strtotime($date)) / 86400);

                if ($temp >= 0) {
                    if ($firstOverdueDays === null)  $firstOverdueDays  = $diffDays; // "Overdue"
                } else {
                    if ($firstPartialDays === null) $firstPartialDays = $diffDays;   // "Partial Overdue"
                }
            }
        }

        return $firstPartialDays ?? $firstOverdueDays;
    }



    function computeOverdueAmountByAgeThreshold(string $partyCode, int $creditDays, int $thresholdDays): float
    {
        $address = Address::where('acc_code', $partyCode)->first();
        if (!$address || empty($address->statement_data)) return 0.0;

        $statement = json_decode($address->statement_data, true);
        if (!$statement || !is_array($statement)) return 0.0;

        // Closing C/f...
        $closing = null;
        foreach ($statement as $row) {
            if (($row['ledgername'] ?? '') === 'closing C/f...') { $closing = $row; break; }
        }
        if (!$closing) return 0.0;

        $clDr = (float)($closing['dramount'] ?? 0);
        $clCr = (float)($closing['cramount'] ?? 0);
        if ($clDr <= $clCr) return 0.0;

        $overdueDateFrom = date('Y-m-d', strtotime('-' . max(0, (int)$creditDays) . ' days'));
        $rows = array_reverse($statement); // FIFO

        // Compute outstanding overdue base
        $drBefore = 0.0; $crBefore = 0.0;
        foreach ($rows as $e) {
            if (($e['ledgername'] ?? '') === 'closing C/f...') continue;
            $d = $e['trn_date'] ?? null; if (!$d) continue;
            $crBefore += (float)($e['cramount'] ?? 0); // credits always reduce
            if (strtotime($d) <= strtotime($overdueDateFrom)) {
                $drBefore += (float)($e['dramount'] ?? 0);
            }
        }

        $overdueAmount = $drBefore - $crBefore;
        if ($overdueAmount <= 0) return 0.0;

        // Allocate overdue across DR entries up to cutoff; sum those with age >= thresholdDays
        $remaining = $overdueAmount;
        $sumThreshold = 0.0;

        foreach ($rows as $e) {
            if ($remaining <= 0) break;
            if (($e['ledgername'] ?? '') === 'closing C/f...') continue;

            $date  = $e['trn_date'] ?? null; if (!$date) continue;
            $drAmt = (float)($e['dramount'] ?? 0);
            if ($drAmt <= 0) continue;
            if (strtotime($date) > strtotime($overdueDateFrom)) continue;

            $alloc = min($drAmt, $remaining);
            if ($alloc <= 0) continue;

            $diffDays = (int) floor(abs(strtotime($overdueDateFrom) - strtotime($date)) / 86400);
            if ($diffDays >= $thresholdDays) {
                $sumThreshold += $alloc;
            }

            $remaining -= $alloc;
        }

        return round($sumThreshold, 2);
    }

    function computeOverdueSummary(string $partyCode, int $creditDays, array $thresholds = [60,90,120]): array
    {
        // Reuse the two primitives above:
        $firstDays = $this->computeFirstOverdueDays($partyCode, $creditDays);

        // To compute total overdue amount, reuse the threshold engine by asking for 0+,
        // which effectively returns the full outstanding overdue (>=0 days).
        $totalOverdue = $this->computeOverdueAmountByAgeThreshold($partyCode, $creditDays, 0);

        $buckets = [];
        foreach ($thresholds as $t) {
            $buckets[(int)$t] = $this->computeOverdueAmountByAgeThreshold($partyCode, $creditDays, (int)$t);
        }

        return [
            'first_overdue_days'  => $firstDays,
            'buckets'             => $buckets,           // e.g., [60 => 1234.56, 90 => 789.00, 120 => 0.00]
            'total_overdue_amount'=> $totalOverdue,      // full overdue (for reference)
        ];
    }
    public function Statement(Request $request)
    {
        if (!auth()->check()) {
            return redirect()->to(url('/login'));
        }

        if ($request->has('clear')) {
            return redirect()->route('adminStatement');
        }

        set_time_limit(-1);
        
        // Allowed user IDs
        $allowedUserIds = [1, 180, 169, 25606];
        $loggedInUser   = auth()->user();
        $loggedInUserId = $loggedInUser->id;

        // Warehouses list
        if (in_array($loggedInUserId, $allowedUserIds)) {
            $warehouses = Warehouse::get();
        } else {
            $warehouses = Warehouse::where('id', $loggedInUser->warehouse_id)->get();
        }

        // Managers list
        $managersQuery = User::join('staff', 'users.id', '=', 'staff.user_id')
            ->where('staff.role_id', 5)
            ->select('users.*');

        if (!in_array($loggedInUserId, $allowedUserIds)) {
            if ($loggedInUserId == 178) {
                $managersQuery->whereIn('users.id', [178, 26786]); // Hatim + Hussain
            } else {
                $managersQuery->where('users.id', $loggedInUserId);
            }
        } elseif ($request->filled('warehouse_id')) {
            $managersQuery->where('users.warehouse_id', $request->warehouse_id);
        }
        $managers = $managersQuery->get();

        // Sorting config
        $sortBy    = $request->input('sort_by');
        $sortOrder = $request->input('sort_order', 'asc');
        $validSortColumns = ['credit_days', 'credit_limit', 'city', 'due_amount_numeric', 'overdue_amount_numeric'];

        // Base query
        $baseQuery = User::join('addresses', function ($join) {
                $join->on(DB::raw("LEFT(users.party_code, 11)"), '=', DB::raw("LEFT(addresses.acc_code, 11)"));
            })
            ->leftJoin('users as managers', 'users.manager_id', '=', 'managers.id')
            ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
            ->where(function ($query) {
                $query->whereRaw('LOWER(users.name) NOT LIKE ?', ['%ace tools%']);
            })
            ->where('users.user_type', 'customer');

        // Totals (pre-filter)
        $totalCustomerCount = (clone $baseQuery)->count();

        // Customers with any due/overdue
        $customersQuery = (clone $baseQuery)->where(function ($query) {
            $query->whereRaw('CAST(NULLIF(addresses.due_amount, "") AS DECIMAL(10,2)) > 0')
                  ->orWhereRaw('CAST(NULLIF(addresses.overdue_amount, "") AS DECIMAL(10,2)) > 0');
        });

        $totalCustomersWithDueOrOverdue = (clone $customersQuery)->count();

        // Filters
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $customersQuery->where(function ($query) use ($searchTerm) {
                $query->where('users.id', $searchTerm) // Exact ID match
                    ->orWhere('users.party_code', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('users.name', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('addresses.company_name', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('addresses.city', 'LIKE', '%' . $searchTerm . '%');
            });
        }

        if (!in_array($loggedInUserId, $allowedUserIds)) {
            if ($loggedInUserId == 178) {
                $customersQuery->whereIn('users.manager_id', [178, 26786]);
            } else {
                $customersQuery->where('users.manager_id', $loggedInUserId);
            }
        }

        if ($request->filled('manager_id')) {
            $customersQuery->where('users.manager_id', $request->manager_id);
        }

        if ($request->filled('warehouse_id')) {
            $customersQuery->where('users.warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('city_id')) {
            $customersQuery->where('addresses.city', 'LIKE', "%{$request->city_id}%");
        }

        // Due/Overdue + Age buckets (60/90/120)
        $df = $request->input('duefilter');
        if (!empty($df)) {
            if ($df === 'due') {
                $customersQuery->whereRaw("CAST(NULLIF(addresses.due_amount, '') AS DECIMAL(10,2)) > 0");
            } elseif ($df === 'overdue') {
                $customersQuery->whereRaw("CAST(NULLIF(addresses.overdue_amount, '') AS DECIMAL(10,2)) > 0");
            } elseif (in_array($df, ['overdue_60', 'overdue_90', 'overdue_120'], true)) {
                // Only those who have some overdue
                $customersQuery->whereRaw("CAST(NULLIF(addresses.overdue_amount, '') AS DECIMAL(10,2)) > 0");

                // Map filter to threshold
                $thresholdMap = ['overdue_60' => 60, 'overdue_90' => 90, 'overdue_120' => 120];
                $threshold    = $thresholdMap[$df];

                // Compute first overdue days for candidates, then filter list
                $candidates = (clone $customersQuery)
                    ->select('addresses.acc_code', 'users.credit_days')
                    ->get();

                $matchingAccCodes = [];
                foreach ($candidates as $row) {
                    $creditDays  = (int) $row->credit_days;
                    $overdueDays = $this->computeFirstOverdueDays($row->acc_code, $creditDays); // days past credit
                    if ($overdueDays === null) continue;

                    $combinedDays = $creditDays + $overdueDays;
                    if ($combinedDays >= $threshold) {
                        $matchingAccCodes[] = $row->acc_code;
                    }
                }
                $matchingAccCodes = array_unique($matchingAccCodes);

                if (empty($matchingAccCodes)) {
                    $customersQuery->whereRaw('1=0');
                } else {
                    $customersQuery->whereIn('addresses.acc_code', $matchingAccCodes);
                }
            }
        }

        // Regular sorting (skip here if user wants the dynamic bucket column)
        if ($sortBy && in_array($sortBy, $validSortColumns) && $sortBy !== 'overdue_bucket_amount') {
            switch ($sortBy) {
                case 'credit_days':
                case 'credit_limit':
                    $customersQuery->orderBy("users.$sortBy", $sortOrder);
                    break;
                case 'city':
                    $customersQuery->orderByRaw("LOWER(addresses.city) $sortOrder");
                    break;
                case 'due_amount_numeric':
                    $customersQuery->orderByRaw("CAST(NULLIF(addresses.due_amount, '') AS DECIMAL(10,2)) $sortOrder");
                    break;
                case 'overdue_amount_numeric':
                    $customersQuery->orderByRaw("CAST(NULLIF(addresses.overdue_amount, '') AS DECIMAL(10,2)) $sortOrder");
                    break;
            }
        }

        // Totals (after all filters, before pagination)
        $filteredCustomers = (clone $customersQuery)
            ->select('users.id', 'addresses.due_amount', 'addresses.overdue_amount')
            ->get();

        $totalDueAmount = $filteredCustomers->sum(function ($item) {
            return is_numeric($item->due_amount) ? (float)$item->due_amount : 0;
        });

        $totalOverdueAmount = $filteredCustomers->sum(function ($item) {
            return is_numeric($item->overdue_amount) ? (float)$item->overdue_amount : 0;
        });

        // Bucket computation (for N+ filter) — per row + total
        $overdueBucketThreshold    = null;  // e.g., 60 / 90 / 120
        $totalOverdueBucketAmount  = 0.0;   // sum of N+ only
        $bucketByAcc               = [];    // acc_code => N+ amount

        // Convenience totals (if you plan to show dedicated tiles)
        $totalOverdue60Amount  = 0.0;
        $totalOverdue90Amount  = 0.0;
        $totalOverdue120Amount = 0.0;

        if (in_array($df, ['overdue_60', 'overdue_90', 'overdue_120'], true)) {
            $thresholdMap = ['overdue_60' => 60, 'overdue_90' => 90, 'overdue_120' => 120];
            $overdueBucketThreshold = $thresholdMap[$df];

            $candidatesForBucket = (clone $customersQuery)
                ->select('addresses.acc_code', 'users.credit_days')
                ->get();

            foreach ($candidatesForBucket as $row) {
                $creditDays = (int) $row->credit_days;
                // N threshold beyond credit days
                $dynOverdueThreshold = max(0, $overdueBucketThreshold - $creditDays);

                $amt = $this->computeOverdueAmountByAgeThreshold($row->acc_code, $creditDays, $dynOverdueThreshold);
                $bucketByAcc[$row->acc_code] = $amt;
                $totalOverdueBucketAmount   += $amt;
            }

            if ($overdueBucketThreshold === 60)  $totalOverdue60Amount  = $totalOverdueBucketAmount;
            if ($overdueBucketThreshold === 90)  $totalOverdue90Amount  = $totalOverdueBucketAmount;
            if ($overdueBucketThreshold === 120) $totalOverdue120Amount = $totalOverdueBucketAmount;

            // ✅ Sorting by the dynamic bucket column
            if ($sortBy === 'overdue_bucket_amount' && !empty($bucketByAcc)) {
                // Order acc_codes by amount asc/desc
                $accOrder = $bucketByAcc;
                if ($sortOrder === 'asc') {
                    asort($accOrder, SORT_NUMERIC);
                } else {
                    arsort($accOrder, SORT_NUMERIC);
                }
                $orderedAccCodes = array_keys($accOrder);

                if (!empty($orderedAccCodes)) {
                    $pdo = DB::getPdo();
                    $quoted = array_map(function($v) use ($pdo) {
                        return $pdo->quote($v);
                    }, $orderedAccCodes);

                    // Use FIELD() to enforce the custom order (MySQL/MariaDB)
                    $customersQuery->orderByRaw("FIELD(addresses.acc_code, " . implode(',', $quoted) . ")");
                }
            }
        }

        // Final data + pagination
        $customers = $customersQuery
            ->select(
                'users.id',
                'users.phone',
                'users.manager_id',
                'users.credit_days',
                'users.credit_limit',
                'addresses.company_name',
                'addresses.acc_code',
                'addresses.city',
                DB::raw('CAST(NULLIF(addresses.due_amount, "") AS DECIMAL(10,2)) as due_amount_numeric'),
                DB::raw('CAST(NULLIF(addresses.overdue_amount, "") AS DECIMAL(10,2)) as overdue_amount_numeric'),
                'managers.name as manager_name',
                'warehouses.name as warehouse_name'
            )
            ->paginate(50)
            ->appends($request->query());
        // Refresh due/overdue via your existing function and attach bucket amounts
        foreach ($customers as $cusKey) {
            $tmpReq   = new Request(['party_code' => $cusKey->acc_code]);
            $response = $this->getDueAndOverDueAmount($tmpReq);
            $data     = json_decode($response->getContent(), true);

            $cusKey->due_amount_numeric     = $data['dueAmount'];
            $cusKey->overdue_amount_numeric = $data['overdueAmount'];

            // Attach bucket amount when N+ filter is active (map by acc_code)
            if ($overdueBucketThreshold !== null) {
                $amount = $bucketByAcc[$cusKey->acc_code] ?? 0.0;
                $cusKey->overdue_bucket_amount = $amount;

                if ($overdueBucketThreshold === 60)  $cusKey->overdue_60_amount  = $amount;
                if ($overdueBucketThreshold === 90)  $cusKey->overdue_90_amount  = $amount;
                if ($overdueBucketThreshold === 120) $cusKey->overdue_120_amount = $amount;
            }
        }
        return view('backend.statement.statement', compact(
            'managers',
            'warehouses',
            'customers',
            'totalDueAmount',
            'totalOverdueAmount',
            'totalCustomerCount',
            'totalCustomersWithDueOrOverdue',
            // Bucket-specific context for the active N+
            'overdueBucketThreshold',
            'totalOverdueBucketAmount',
            // Convenience totals
            'totalOverdue60Amount',
            'totalOverdue90Amount',
            'totalOverdue120Amount'
        ));
    }

    public function Statement_backup_10_07_2025(Request $request)
    {
        
        if (!auth()->check()) {
            return redirect()->to(url('/login')); // Redirect to login if not authenticated
        }

        if ($request->has('clear')) {
            return redirect()->route('adminStatement'); // Redirect to the same route without search
        }

        set_time_limit(-1);

        // Allowed user IDs
        $allowedUserIds = [1, 180, 169, 25606];
        $loggedInUser = auth()->user();
        $loggedInUserId = $loggedInUser->id;

        // Fetch warehouses and managers
        $warehouses = Warehouse::get();
        $managers = User::join('staff', 'users.id', '=', 'staff.user_id')
            ->where('staff.role_id', 5)
            ->select('users.*');

        if ($request->has('warehouse_id') && !empty($request->warehouse_id)) {
            $managers->where('warehouse_id', $request->warehouse_id);
        }
        $managers = $managers->get();

        // Sorting options
        $sortBy = $request->input('sort_by');
        $sortOrder = $request->input('sort_order', 'asc');
        $validSortColumns = ['credit_days', 'credit_limit', 'city', 'due_amount_numeric', 'overdue_amount_numeric'];

        // ✅ Base Query
        $baseQuery = User::join('addresses', function ($join) {
                $join->on(DB::raw("LEFT(users.party_code, 11)"), '=', DB::raw("LEFT(addresses.acc_code, 11)"));
            })
            ->leftJoin('users as managers', 'users.manager_id', '=', 'managers.id')
            ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
            ->where('users.user_type', 'customer');

        // ✅ Total Customers Count (before filtering due/overdue amounts)
        $totalCustomerCount = (clone $baseQuery)->count();

        // ✅ Filtering customers who have due or overdue amounts
        $customersQuery = (clone $baseQuery)
            ->where(function ($query) {
                $query->whereRaw('CAST(NULLIF(addresses.due_amount, "") AS DECIMAL(10,2)) > 0')
                    ->orWhereRaw('CAST(NULLIF(addresses.overdue_amount, "") AS DECIMAL(10,2)) > 0');
            });

        // ✅ Total Customers With Due or Overdue
        $totalCustomersWithDueOrOverdue = (clone $customersQuery)->count();

        // ✅ Apply Filters
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $customersQuery->where(function ($query) use ($searchTerm) {
                $query->where('users.party_code', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('users.name', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('addresses.company_name', 'LIKE', '%' . $searchTerm . '%')
                    ->orWhere('addresses.city', 'LIKE', '%' . $searchTerm . '%');
            });
        }

        if (!in_array($loggedInUserId, $allowedUserIds)) {
            $customersQuery->where('users.manager_id', $loggedInUserId);
        }

        if ($request->has('manager_id') && !empty($request->manager_id)) {
            $customersQuery->where('users.manager_id', $request->manager_id);
        }

        if ($request->has('warehouse_id') && !empty($request->warehouse_id)) {
            $customersQuery->where('users.warehouse_id', $request->warehouse_id);
        }

        if ($request->has('city_id') && !empty($request->city_id)) {
            $customersQuery->where('addresses.city', 'LIKE', '%' . $request->city_id . '%');
        }

        if ($request->has('duefilter') && !empty($request->duefilter)) {
            if ($request->duefilter === 'due') {
                $customersQuery->whereRaw('CAST(NULLIF(addresses.due_amount, "") AS DECIMAL(10,2)) > 0');
            } elseif ($request->duefilter === 'overdue') {
                $customersQuery->whereRaw('CAST(NULLIF(addresses.overdue_amount, "") AS DECIMAL(10,2)) > 0');
            }
        }

        // ✅ Apply Sorting
        if ($sortBy && in_array($sortBy, $validSortColumns)) {
            if ($sortBy == 'city') {
                $customersQuery->orderByRaw("LOWER(addresses.city) $sortOrder");
            } else {
                $customersQuery->orderBy($sortBy, $sortOrder);
            }
        } else {
            $customersQuery->orderBy('warehouses.name', 'asc')
                ->orderBy('managers.name', 'asc')
                ->orderByRaw('LOWER(addresses.city) ASC');
        }

        // ✅ Calculate Accurate Total Due and Overdue using PHP collection (before pagination)
        $totalDueAmount = 0.0;
        $totalOverdueAmount = 0;
        // $filteredCustomers = (clone $customersQuery)
        //     ->select('users.id','addresses.due_amount', 'addresses.overdue_amount')
        //     ->distinct('users.id')
        //     ->get()->toArray();
        $filteredCustomers = (clone $customersQuery)
        ->select('users.id','addresses.due_amount', 'addresses.overdue_amount')
        ->get()->toArray();

        $amounts = array_column($filteredCustomers, 'due_amount');
        $total = array_sum($amounts);
        // echo "<pre>"; print_r($filteredCustomers); die;
        // foreach ($filteredCustomers as $faKey) {
        //     $totalDueAmount = $totalDueAmount + (float)$faKey->due_amount;
        // }
        echo $total; die;
        // $totalDueAmount = $filteredCustomers->sum(function ($item) {
        //     return is_numeric($item->due_amount) ? (float) $item->due_amount : 0;
        // });

        // $totalOverdueAmount = $filteredCustomers->sum(function ($item) {
        //     return is_numeric($item->overdue_amount) ? (float) $item->overdue_amount : 0;
        // });

        // ✅ Fetch Paginated Data
        $customers = $customersQuery
        ->select(
            'users.id',
            'users.phone',
            'users.manager_id',
            'users.credit_days',
            'users.credit_limit',
            'addresses.company_name',
            'addresses.acc_code',
            'addresses.city',
            DB::raw('CAST(NULLIF(addresses.due_amount, "") AS DECIMAL(10,2)) as due_amount_numeric'),
            DB::raw('CAST(NULLIF(addresses.overdue_amount, "") AS DECIMAL(10,2)) as overdue_amount_numeric'),
            'managers.name as manager_name',
            'warehouses.name as warehouse_name'
        )        
        ->paginate(50)
        ->appends($request->query());
            
        // $totalDueAmount = 0;
        // $totalOverdueAmount = 0;
        foreach($customers as $cusKey){
            // echo "<br>".$cusKey->acc_code;
            $request = new Request([
                'party_code' => $cusKey->acc_code
            ]);
            $response = $this->getDueAndOverDueAmount($request);
            $data = json_decode($response->getContent(), true);
            
            $cusKey->due_amount_numeric = $data['dueAmount'];
            $cusKey->overdue_amount_numeric = $data['overdueAmount'];
            // $totalDueAmount += $cusKey->due_amount_numeric;
            // $totalOverdueAmount += $cusKey->overdue_amount_numeric;            
        }
            
        // Get content as JSON and decode to array   
        

        return view('backend.statement.statement', compact(
            'managers', 'warehouses', 'customers', 'totalDueAmount', 'totalOverdueAmount',
            'totalCustomerCount', 'totalCustomersWithDueOrOverdue'
        ));
    }


    public function getFirstOverdueDays($partyCode)
    {
        // FOR TESTING ONLY — override encrypted value
        //$partyCode = encrypt('OPEL0100087');

        // Step 1: Decrypt
        try {
            $party_code = decrypt($partyCode);
        } catch (\Exception $e) {
            Log::error('Decryption error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to decrypt party code.'], 500);
        }

        // Step 2: Fetch Address
        $userAddressData = Address::where('acc_code', $party_code)->first();
        if (!$userAddressData) {
            return response()->json(['error' => 'User address not found'], 404);
        }

        // Step 3: Fetch User
        $userData = User::where('id', $userAddressData->user_id)->first();
        if (!$userData) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Step 4: Decode Statement
        $statement_data = json_decode($userAddressData->statement_data, true);
        if (!$statement_data) {
            return response()->json(['error' => 'No statement data found'], 404);
        }

        // Step 5: Get Closing Balance
        $closingBalanceResult = array_filter($statement_data, function ($entry) {
            return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
        });

        $closingEntry = reset($closingBalanceResult);
        $cloasingDrAmount = (float) ($closingEntry['dramount'] ?? 0);
        $cloasingCrAmount = (float) ($closingEntry['cramount'] ?? 0);

        // Step 6: Set Overdue Date From (today - credit days)
        $overdueDateFrom = date('Y-m-d', strtotime('-' . ($userData->credit_days ?? 0) . ' days'));

        $overDueMark = [];

        // Step 7: Only proceed if debit balance exists
        if ($cloasingDrAmount > $cloasingCrAmount) {

            $drBalanceBeforeOVDate = 0;
            $crBalanceBeforeOVDate = 0;

            $statement_data = array_reverse($statement_data); // reverse for FIFO

            foreach ($statement_data as $entry) {
                if (($entry['ledgername'] ?? '') !== 'closing C/f...') {
                    $entryDate = $entry['trn_date'];
                    if (strtotime($entryDate) > strtotime($overdueDateFrom)) {
                        $crBalanceBeforeOVDate += (float) $entry['cramount'];
                    } else {
                        $drBalanceBeforeOVDate += (float) $entry['dramount'];
                        $crBalanceBeforeOVDate += (float) $entry['cramount'];
                    }
                }
            }

            $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;

            foreach ($statement_data as $entry) {
                if (($entry['ledgername'] ?? '') !== 'closing C/f...') {
                    $entryDate = $entry['trn_date'];
                    $drAmt = (float) $entry['dramount'];
                    if (strtotime($entryDate) > strtotime($overdueDateFrom)) {
                        continue;
                    }

                    if (strtotime($entryDate) <= strtotime($overdueDateFrom) && $temOverDueBalance > 0 && $drAmt > 0) {
                        $temOverDueBalance -= $drAmt;
                        $diffDays = floor(abs(strtotime($overdueDateFrom) - strtotime($entryDate)) / (60 * 60 * 24));

                        $overDueMark[] = [
                            'trn_no' => $entry['trn_no'] ?? '',
                            'trn_date' => $entryDate,
                            'overdue_by_day' => $diffDays . ' days',
                            'overdue_status' => ($temOverDueBalance >= 0) ? 'Overdue' : 'Partial Overdue',
                        ];
                    }
                }
            }
        }

        // Step 8: Find the first Partial Overdue
        foreach ($overDueMark as $overdueEntry) {
            if ($overdueEntry['overdue_status'] === 'Partial Overdue') {
                return response()->json([
                    'status' => 'success',
                    'overdue_status' => 'Partial Overdue',
                    'overdue_days' => $overdueEntry['overdue_by_day']
                ]);
            }
        }

        // Step 9: Otherwise return first Overdue
        foreach ($overDueMark as $overdueEntry) {
            if ($overdueEntry['overdue_status'] === 'Overdue') {
                return response()->json([
                    'status' => 'success',
                    'overdue_status' => 'Overdue',
                    'overdue_days' => $overdueEntry['overdue_by_day']
                ]);
            }
        }

        // Step 10: Default response if no overdue found
        return response()->json([
            'status' => 'success',
            'message' => 'No overdue transactions found'
        ]);
    }



    public function backup_8_august_getFirstOverdueDays($partyCode=null)
    {

          $partyCode=encrypt('OPEL0100087');
        // helper function
        try {
            $party_code = decrypt($partyCode);
        } catch (\Exception $e) {
            Log::error('Decryption error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to decrypt party code.'], 500);
        }

        // Fetch user address and user data
        $userAddressData = Address::where('acc_code', $party_code)->first();
        if (!$userAddressData) {
            return response()->json(['error' => 'User address not found'], 404);
        }

        $userData = User::where('id', $userAddressData->user_id)->first();
        if (!$userData) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Decode statement data
        $statement_data = json_decode($userAddressData->statement_data, true);
        if (!$statement_data) {
            return response()->json(['error' => 'No statement data found'], 404);
        }
        // echo "<pre>";
        // print_r($statement_data);
        // die();

        // Initialize variables
        $overdueAmount = "0";
        $overdueDateFrom = "";
        $overDueMark = [];

        // Get closing balance and overdue start date
        $closingBalanceResult = array_filter($statement_data, function ($entry) {
            return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
        });
        
        $closingEntry = reset($closingBalanceResult);
        $cloasingDrAmount = $closingEntry['dramount'];
        $cloasingCrAmount = $closingEntry['cramount'];
        $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));

        if ($cloasingCrAmount > 0) {
            $drBalanceBeforeOVDate = 0;
            $crBalanceBeforeOVDate = 0;
            $statement_data = array_reverse($statement_data); // Reverse data order

            foreach ($statement_data as $ovValue) {
                if ($ovValue['ledgername'] != 'closing C/f...') {
                    if (strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)) {
                        $crBalanceBeforeOVDate += $ovValue['cramount'];
                    } else {
                        $drBalanceBeforeOVDate += $ovValue['dramount'];
                        $crBalanceBeforeOVDate += $ovValue['cramount'];
                    }
                }
            }

            $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;

            foreach ($statement_data as $ovValue) {
                if ($ovValue['ledgername'] != 'closing C/f...') {
                    if (strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)) {
                        continue;
                    } elseif (strtotime($ovValue['trn_date']) <= strtotime($overdueDateFrom) && $temOverDueBalance > 0 && $ovValue['dramount'] != '0.00') {
                        $temOverDueBalance -= $ovValue['dramount'];
                        $date1 = $ovValue['trn_date'];
                        $date2 = $overdueDateFrom;
                        $diff = abs(strtotime($date2) - strtotime($date1));
                        $dateDifference = floor($diff / (60 * 60 * 24)) . ' days';

                        if ($temOverDueBalance >= 0) {
                            $overDueMark[] = [
                                'trn_no' => $ovValue['trn_no'],
                                'trn_date' => $ovValue['trn_date'],
                                'overdue_by_day' => $dateDifference,
                                'overdue_status' => 'Overdue'
                            ];
                        } else {
                            $overDueMark[] = [
                                'trn_no' => $ovValue['trn_no'],
                                'trn_date' => $ovValue['trn_date'],
                                'overdue_by_day' => $dateDifference,
                                'overdue_status' => 'Partial Overdue'
                            ];
                        }
                    }
                }
            }
        }

        // Find first "Partial Overdue" or "Overdue" transaction
        foreach ($overDueMark as $overdueEntry) {
            if ($overdueEntry['overdue_status'] === 'Partial Overdue') {
                return response()->json([
                    'status' => 'success',
                    'overdue_status' => 'Partial Overdue',
                    'overdue_days' => $overdueEntry['overdue_by_day']
                ], 200);
            }
        }

        // If no "Partial Overdue" found, return first "Overdue"
        foreach ($overDueMark as $overdueEntry) {
            if ($overdueEntry['overdue_status'] === 'Overdue') {
                return response()->json([
                    'status' => 'success',
                    'overdue_status' => 'Overdue',
                    'overdue_days' => $overdueEntry['overdue_by_day']
                ], 200);
            }
        }

        return response()->json(['status' => 'success', 'message' => 'No overdue transactions found'], 200);
    }


     public function downloadStatementForOrder(Request $request)
    {
        try {
            $party_code = decrypt($request->query('party_code'));
        } catch (\Exception $e) {
            Log::error('Decryption error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to decrypt party code.'], 500);
        }

        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');

        // Define financial year date range based on the current date
        if ($currentMonth >= 4) {
            $form_date = date('Y-04-01'); // Start of financial year
            $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year
        } else {
            $form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
            $to_date = date('Y-03-31'); // Current year March
        }

        // Use custom date range if provided
       if ($request->query('from_date')) {
            $form_date = $request->query('from_date');
        }

        if ($request->query('to_date')) {
            $to_date = $request->query('to_date');
        }

        // Limit the 'to_date' to the current date
        if ($to_date > $currentDate) {
            $to_date = $currentDate;
        }

        // Perform INNER JOIN between users and addresses tables based on user_id
        $userData = DB::table('users')
            ->join('addresses', 'users.id', '=', 'addresses.user_id')
            ->where('addresses.acc_code', $party_code)
            ->select('users.*', 'addresses.company_name', 'addresses.statement_data', 'addresses.overdue_amount', 'addresses.due_amount', 'addresses.address', 'addresses.address_2', 'addresses.postal_code', 'addresses.dueDrOrCr', 'addresses.overdueDrOrCr')
            ->first();

        if (!$userData) {
            return response()->json(['error' => 'User or address not found'], 404);
        }

        // Get statement_data, overdue_amount, and due_amount from the address table
        $statementData = json_decode($userData->statement_data, true);
        $overdueAmount = floatval($userData->overdue_amount);
        $dueAmount = floatval($userData->due_amount);

        // Retrieve the address information
        $company_name = $userData->company_name ?? 'Company Name not found';
        $address = $userData->address ?? 'Address not found';
        $address_2 = $userData->address_2 ?? '';
        $postal_code = $userData->postal_code ?? '';

        // Variables to store balances
        $openingBalance = "0";
        $closingBalance = "0";
        $openDrOrCr = "";
        $closeDrOrCr = "";
        $overdueDrOrCr = 'Dr'; // Default value for overdue Dr/Cr

        // Calculate total debit
        $totalDebit = 0;
        foreach ($statementData as $transaction) {
            if (isset($transaction['dramount']) && $transaction['dramount'] != "0.00") {
                $totalDebit += floatval($transaction['dramount']);
            }
        }

        // Get user credit limit and calculate available credit
        $creditLimit = floatval($userData->credit_limit);
        $availableCredit = $creditLimit - $totalDebit;

        $getOverdueData = $statementData;

        // Iterate through statement data and process transactions
        foreach ($statementData as $transaction) {
            if (isset($transaction['ledgername']) && $transaction['ledgername'] == "Opening b/f...") {
                $openingBalance = ($transaction['dramount'] != "0.00") ? floatval($transaction['dramount']) : floatval($transaction['cramount']);
                $openDrOrCr = ($transaction['dramount'] != "0.00") ? "Dr" : "Cr";
            } elseif (isset($transaction['ledgername']) && $transaction['ledgername'] == "closing C/f...") {
                $closingBalance = ($transaction['dramount'] != "0.00") ? floatval($transaction['dramount']) : floatval($transaction['cramount']);
                $closeDrOrCr = ($transaction['dramount'] != "0.00") ? "Dr" : "Cr";

                // Set dueAmount and overdueAmount and also set overdueDrOrCr based on closing balance
                if ($transaction['dramount'] != "0.00") {
                    $dueAmount = floatval($transaction['dramount']);
                    $overdueDrOrCr = 'Dr';
                } else {
                    $dueAmount = floatval($transaction['cramount']);
                    $overdueDrOrCr = 'Cr';
                }

                $cloasingDrAmount = $transaction['dramount'];
                $cloasingCrAmount = $transaction['cramount'];
                $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));

                if ($cloasingCrAmount > 0) {
                    $drBalanceBeforeOVDate = 0;
                    $crBalanceBeforeOVDate = 0;
                    $getOverdueData = array_reverse($getOverdueData);

                    foreach ($getOverdueData as $ovValue) {
                        if ($ovValue['ledgername'] != 'closing C/f...') {
                            if (strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)) {
                                $crBalanceBeforeOVDate += $ovValue['cramount'];
                            } else {
                                $drBalanceBeforeOVDate += $ovValue['dramount'];
                                $crBalanceBeforeOVDate += $ovValue['cramount'];
                            }
                        }
                    }
                    $overdueAmount = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                }

                if ($overdueAmount <= 0) {
                    $overdueDrOrCr = 'Cr';
                    $overdueAmount = 0;
                } else {
                    $overdueDrOrCr = 'Dr';
                }
            }
        }

        // Add overdue days calculation to each transaction
        foreach ($statementData as &$transaction) {
            if (isset($transaction['trn_date']) && strtotime($transaction['trn_date']) < strtotime($overdueDateFrom)) {
                $dateDiff = (strtotime($overdueDateFrom) - strtotime($transaction['trn_date'])) / (60 * 60 * 24);
                $transaction['overdue_days'] = floor($dateDiff) . ' days';
            } else {
                $transaction['overdue_days'] = '-';
            }
        }

        // Generating PDF with transaction data
        $randomNumber = str_replace('.', '', microtime(true));
        $fileName = 'statement-' . $party_code . '-' . $randomNumber . '.pdf';
        $filePath = public_path('statements/' . $fileName); // Store in public/statements
         //New add code start
        
       // Filter statement data based on date range while keeping the closing balance entry
        $statementData = array_filter($statementData, function ($transaction) use ($form_date, $to_date) {
            // Always include the "closing C/f..." entry
            if (isset($transaction['ledgername']) && $transaction['ledgername'] == "closing C/f...") {
                return true;
            }

            // Filter based on the date range
            $transactionDate = strtotime($transaction['trn_date']);
            return ($transactionDate >= strtotime($form_date) && $transactionDate <= strtotime($to_date));
        });

        // Re-index the array to avoid gaps in keys
        $statementData = array_values($statementData);

        $pdfContentService = new PdfContentService();
        $pdfContentBlock   = $pdfContentService->buildBlockForType('statement');

            // New added code end
            // Prepare PDF content using Blade template
            $pdf = PDF::loadView('backend.invoices.statement_pdf', compact(
                'userData',
                'party_code',
                'statementData',
                'openingBalance',
                'openDrOrCr',
                'closingBalance',
                'closeDrOrCr',
                'form_date',
                'to_date',
                'overdueAmount',
                'overdueDrOrCr',
                'dueAmount',
                'availableCredit',
                'address',
                'address_2',
                'postal_code',
                'pdfContentBlock'
            ))->save($filePath);


                // ✅ Ensure the file exists before attempting to download
        if (!File::exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        // ✅ Force direct file download and delete after sending
        return response()->download($filePath)->deleteFileAfterSend(true);


        // $publicUrl = url('public/statements/' . $fileName);

        // return response()->json(['status' => 'success', 'message' => "Statement sent to WhatsApp", 'url' => $publicUrl], 200);
    }

    public function getDueAndOverDueAmount(Request $request){
        $userAddress = Address::where('acc_code', $request->party_code)->first();

        $userData = User::where('id', $userAddress->user_id)->first();
        // $userAddressData = Address::where('user_id', $userAddress->user_id)->groupBy('gstin')->orderBy('acc_code','ASC')->get();
        
        $dueAmount = '0.00';
        $overdueAmount = '0.00';

        $statement_data = array();
        $userData = User::where('id', $userData->id)->first();
        // $userAddress = Address::where('user_id', $userData->id)->first();
        if ($userAddress) {
            $gstin = $userAddress->gstin;
            $userAddressDatas = Address::where('user_id', $userData->id)->where('gstin', $gstin)->get();
        } else {
            $userAddressDatas = collect(); // Return empty collection if no address found
        }
        foreach ($userAddressDatas as $uValue) {
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
            $statement_data[$gKey]['running_balance'] = $balance;
        }
        
        if(isset($balance)){
            $tempArray = array();
            $tempArray['trn_no'] = "";
            $tempArray['trn_date'] = date('Y-m-d');
            $tempArray['vouchertypebasename'] = "";
            $tempArray['ledgername'] = "closing C/f...";
            // $amount = explode('₹',$value[5]);
            $tempArray['ledgerid'] = "";
            if($balance >= 0){
                $tempArray['cramount'] = (float)str_replace(',', '',$balance);
                $tempArray['dramount'] = (float)0.00;
            }else{
                $tempArray['dramount'] = (float)str_replace(',', '',$balance);
                $tempArray['cramount'] = (float)0.00;
            }
            $tempArray['narration'] = "";
            $statement_data[] = $tempArray;
        }

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
            // echo "<pre>"; print_r($overDueMark); die();
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
                }else{
                    if(isset($getData[$gKey]['overdue_status'])){ $getData[$gKey]['overdue_status']=""; }
                    if(isset($getData[$gKey]['overdue_by_day'])){ $getData[$gKey]['overdue_by_day'] = ""; }
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

        // echo $dueAmount.'.......'.$overdueAmount; die;
        return response()->json(['dueAmount' => $dueAmount, 'overdueAmount' => $overdueAmount]);
    }
}
