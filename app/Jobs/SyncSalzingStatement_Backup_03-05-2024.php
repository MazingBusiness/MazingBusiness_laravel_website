<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Address;

class SyncSalzingStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        $userAddressData = Address::where('acc_code',"!=","")->groupBy('acc_code')->orderBy('acc_code','ASC')->get();
        // echo "<pre>";print_r($userAddressData);die;
        // if ($currentMonth >= 4) {
        //     $fy_form_date = date('Y-04-01'); // Start of financial year
        //     $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        // } else {
        //     $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
        //     $fy_to_date = date('Y-03-31'); // Current year March
        // }
        // $from_date = $fy_form_date;
        // $to_date = $fy_to_date;
        
        $from_date = '2024-04-01';
        $to_date = date('Y-m-d');

        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];

        foreach($userAddressData as $key=>$value){
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
            $userData = User::where('id', $value->user_id)->first();
            $body = [
                'party_code' => $value->acc_code,
                'from_date' => $from_date,
                'to_date' =>  $to_date,
            ];
            $overdue_response = Http::withHeaders($headers)
            ->retry(3, 100) // Retry 3 times with a 100ms delay between attempts
            ->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
            \Log::info('Received response from Salzing API For Sync Statement Overdue Calculation', [
                'status' => $overdue_response->status(),
                'party_code' =>  $value->acc_code,
                'body' => $overdue_response->body()
            ]);
            if ($overdue_response->successful()) {
                $getOverdueData = $overdue_response->json();
                
                if(!empty($getOverdueData) AND isset($getOverdueData['data']) AND !empty($getOverdueData['data'])){
                    $getOverdueData = $getOverdueData['data'];
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
                    // Overdue Calculation End
                }

                $body = [
                    'party_code' => $value->acc_code,
                    'from_date' => $from_date,
                    'to_date' =>  $to_date,
                ];
                $response = Http::withHeaders($headers)
                ->retry(3, 100) // Retry 3 times with a 100ms delay between attempts
                ->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
                \Log::info('Received response from Salzing API For Sync Statement', [
                    'status' => $response->status(),
                    'party_code' =>  $value->acc_code,
                    'body' => $response->body()
                ]);
                $getData = $response->json();                
                if(!empty($getData) AND isset($getData['data']) AND !empty($getData['data'])){
                    $getData = $getData['data'];
                    $openingBalance = 0;
                    $closingBalance = 0;
                    $openDrOrCr = "";
                    $drBalance = 0;
                    $crBalance = 0;
                    $closeDrOrCr="";
                    $dueAmount = 0;
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
                    Address::where('acc_code', $value->acc_code)
                    ->update(
                        [
                            'due_amount'      => $dueAmount,
                            'dueDrOrCr'      => $closeDrOrCr,
                            'overdue_amount' => $overdueAmount,
                            'overdueDrOrCr' => $overdueDrOrCr,
                            'statement_data' => json_encode($getData)
                        ]
                    );
                    // $url = 'https://mazingbusiness.com/mazing_business_react/api/saleszing/saleszing-statement-get';
                    // $response = Http::get($url, [
                    //     'user_id' => $userData->id,
                    //     'data_from' => 'live',
                    // ]);
                    // if ($response->successful()) {
                    //     // Process and return the response data
                    //     $data = $response->json(); // Converts response to array
                    //     return response()->json(['status' => 'success', 'data' => $data]);
                    // }
                }
            }
            sleep(1); // Delay of 1 second between requests
        }
        
        $userAddressData = Address::where('acc_code',"!=","")->groupBy('acc_code')->orderBy('acc_code','ASC')->get();
        foreach($userAddressData as $key=>$value){
            // echo "<pre>"; print_r($value);die;
            $userData = User::where('id', $value->user_id)->first();
            $url = 'https://mazingbusiness.com/mazing_business_react/api/saleszing/saleszing-statement-get';
            $response = Http::get($url, [
                'address_id' => $value->id,
                'data_from' => 'live',
            ]);
            // if ($response->successful()) {
            //     // Process and return the response data
            //     $data = $response->json(); // Converts response to array
            //     return response()->json(['status' => 'success', 'data' => $data]);
            // }
        }
        return response()->json(['status' => 'success']);
    }
}