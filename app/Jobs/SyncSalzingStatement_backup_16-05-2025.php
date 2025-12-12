<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\File;

use App\Models\User;
use App\Models\Address;
use App\Models\ZohoSetting;
use App\Models\ZohoToken;
use App\Models\UserSalzingStatement;

class SyncSalzingStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
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
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $getData=array();
        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');

        // $userAddressData = Address::where('user_id', '24920')->groupBy('gstin')->orderBy('acc_code','ASC')->get();
        $userAddressData = Address::where('acc_code',"!=","")->where('zoho_customer_id',"!=","")->groupBy('acc_code')->orderBy('acc_code','ASC')->get();
        
        $from_date = '2025-04-01';
        $to_date = date('Y-m-d');

        foreach($userAddressData as $uAkey=>$uAvalue){
            $contactId = $uAvalue->zoho_customer_id;
            
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
                'party_code' =>  $uAvalue->acc_code
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
                            }else{
                                $tempArray['vouchertypebasename'] = $value[1];
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
                            }else if(($value[1] == 'Payment Refund') AND $value[4] != ""){
                                $tempArray['cramount'] = (float)0.00;
                                $tempArray['dramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                            }else if(($value[1] == 'Payment Received') AND $value[4] != ""){
                                $tempArray['cramount'] = (float)str_replace(',', '',$value[4]);
                                $tempArray['dramount'] = (float)0.00;
                            }else if($value[1] == 'Credit Note'){
                                if($value[1] == 'Credit Note' AND $value[3] != ""){
                                    $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[3])));
                                    $tempArray['cramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)$crnValue;
                                    $tempArray['dramount'] = (float)0.00;
                                }elseif($value[1] == 'Credit Note' AND $value[4] != ""){
                                    $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[4])));
                                    $tempArray['dramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)$crnValue;
                                    $tempArray['cramount'] = (float)0.00;
                                }
                            }else{
                                if($value[3] != ""){
                                    $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[3])));
                                    $tempArray['cramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)$crnValue;
                                    $tempArray['dramount'] = (float)0.00;
                                }elseif($value[4] != ""){
                                    $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[4])));
                                    $tempArray['dramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)$crnValue;
                                    $tempArray['cramount'] = (float)0.00;
                                }
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
                        'due_amount'     => $dueAmount,
                        'dueDrOrCr'      => $closeDrOrCr,
                        'overdue_amount' => $overdueAmount,
                        'overdueDrOrCr'  => $overdueDrOrCr,
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
                'party_code' =>  $uAvalue->acc_code
            ]);
            // echo "<pre>Hello"; print_r($data); die;
            sleep(1); // Delay of 1 second between requests
        }
        return response()->json(['status' => 'success']);
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