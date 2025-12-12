<?php
    namespace App\Services;
    use App\Models\User;
    use App\Models\Address;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Http;

    class StatementCalculationService
    {
        public function calculateForOneCompany($user_id, $fromLiveOrDatabase)
        {
            $getData=array();
            $currentDate = date('Y-m-d');
            $currentMonth = date('m');
            $currentYear = date('Y');
            $statement_data = "";
            $drBalance = 0;
            $crBalance = 0;
            $overdueAmount = 0;
            $dueAmount = 0;
            $drBalanceBeforeOVDate = 0;
            $crBalanceBeforeOVDate = 0;
            $overdueDrOrCr="";
            $dueDrOrCr="";
            $from_date = '';
            $to_date = '';
            if($fromLiveOrDatabase == 'live'){
                $userData = User::where('id', $user_id)->first();
                $userAddressData = Address::where('acc_code',"!=","")->where('user_id',$user_id)->first();
                if ($currentMonth >= 4) {
                    $fy_form_date = date('Y-04-01'); // Start of financial year
                    $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
                } else {
                    $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
                    $fy_to_date = date('Y-03-31'); // Current year March
                }
                $from_date = $fy_form_date;
                $to_date = $fy_to_date;

                $headers = [
                    'authtoken' => '65d448afc6f6b',
                ];

                $body = [
                    'party_code' => $userData->party_code,
                    'from_date' => $from_date,
                    'to_date' =>  $to_date,
                ];
				
                $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
				//echo "<pre>"; print_r($response->successful()); die;
                \Log::info('Received response from Salzing API', [
                    'status' => $response->status(),
                    'party_code' =>  $userData->party_code,
                    'body' => $response->body()
                ]);
				
                if ($response->successful()) {
                    $getData = $response->json();
                    if(isset($getData['data'])){
                        $statement_data = $getData['data'];
                        $closingBalanceResult = array_filter($statement_data, function ($entry) {
                            return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
                        });
                        $closingEntry = reset($closingBalanceResult);
                        if ($closingEntry !== false) {
                                $cloasingDrAmount = $closingEntry['dramount'];
                                $cloasingCrAmount = $closingEntry['cramount'];
                            } else {
                                $cloasingDrAmount = 0;
                                $cloasingCrAmount = 0;
                            }
                        //$cloasingDrAmount = $closingEntry['dramount'];
                        //$cloasingCrAmount = $closingEntry['cramount'];          
                        $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
                        if($cloasingCrAmount > 0){
                            $drBalanceBeforeOVDate = 0;
                            $crBalanceBeforeOVDate = 0;
                            $statement_data = array_reverse($statement_data);
                            foreach($statement_data as $ovKey=>$ovValue){
                                if($ovValue['ledgername'] != 'closing C/f...'){
                                    if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                                        // $drBalanceBeforeOVDate += $ovValue['dramount'];
                                        $crBalanceBeforeOVDate += $ovValue['cramount'];
                                    }else{
                                        $drBalanceBeforeOVDate += $ovValue['dramount'];
                                        $crBalanceBeforeOVDate += $ovValue['cramount'];
                                    }
                                }
                                if ($ovValue['dramount'] != 0.00 AND $ovValue['ledgername'] != 'closing C/f...') {
                                    $drBalance = $drBalance + $ovValue['dramount'];
                                    $dueAmount = $dueAmount + $ovValue['dramount'];
                                } 
                                if($ovValue['cramount'] != '0.00' AND $ovValue['ledgername'] != 'closing C/f...') {
                                    $crBalance = $crBalance + $ovValue['cramount'];
                                    $dueAmount = $dueAmount - $ovValue['cramount'];
                                }
                            }
                            $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                            $overDueMark = array();
                            foreach($statement_data as $ovKey=>$ovValue){
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
                                            $statement_data[$ovKey]['overdue_status'] = 'Overdue';
                                        }else{
                                            $overDueMark[] = [
                                                'trn_no' => $ovValue['trn_no'],
                                                'trn_date' => $ovValue['trn_date'],
                                                'overdue_by_day' => $dateDifference,
                                                'overdue_staus' => 'Pertial Overdue'
                                            ];
                                            $statement_data[$ovKey]['overdue_status'] = 'Pertial Overdue';
                                        }
                                        $statement_data[$ovKey]['overdue_by_day'] = $dateDifference;
                                    }
                                }
                            }
                        }
                        $statement_data = array_reverse($statement_data);
                        if($overdueAmount <= 0){
                            $overdueAmount = 0;
                            $overdueDrOrCr = 'Cr';
                        }else{
                            $overdueDrOrCr = 'Dr';
                        }
                        $dueDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
                    }
                }
            }elseif($fromLiveOrDatabase == 'database'){
                $userAddressData = Address::where('acc_code',"!=","")->where('user_id',$user_id)->first();
                $userData = User::where('id', $userAddressData->user_id)->first();
                $statement_data = json_decode($userAddressData->statement_data, true);
                if($userAddressData->statement_data != NULL){
                    $closingBalanceResult = array_filter($statement_data, function ($entry) {
                        return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
                    });
                    $closingEntry = reset($closingBalanceResult);
                    $cloasingDrAmount = $closingEntry['dramount'];
                    $cloasingCrAmount = $closingEntry['cramount'];          
                    $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
                    foreach($statement_data as $ovKey=>$ovValue){
                        if($ovValue['ledgername'] != 'closing C/f...'){
                            if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                                // $drBalanceBeforeOVDate += $ovValue['dramount'];
                                $crBalanceBeforeOVDate += $ovValue['cramount'];
                            }else{
                                $drBalanceBeforeOVDate += $ovValue['dramount'];
                                $crBalanceBeforeOVDate += $ovValue['cramount'];
                            }
                        }
                        if ($ovValue['dramount'] != 0.00 AND $ovValue['ledgername'] != 'closing C/f...') {
                            $drBalance = $drBalance + $ovValue['dramount'];
                            $dueAmount = $dueAmount + $ovValue['dramount'];
                        } 
                        if($ovValue['cramount'] != '0.00' AND $ovValue['ledgername'] != 'closing C/f...') {
                            $crBalance = $crBalance + $ovValue['cramount'];
                            $dueAmount = $dueAmount - $ovValue['cramount'];
                        }
                    }
                    $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                    if($overdueAmount <= 0){
                        $overdueAmount = 0;
                        $overdueDrOrCr = 'Cr';
                    }else{
                        $overdueDrOrCr = 'Dr';
                    }
                    $dueDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
                }
            }
            return response()->json([
                'from_date' => $from_date,
                'to_date' => $to_date,
                'party_code' => $userData->party_code,
                'company_name' => $userAddressData?$userAddressData->company_name:'',
                'statement_data' => $statement_data,
                'dueAmount' => $dueAmount,
                'dueDrOrCr' => $dueDrOrCr,
                'overdueAmount' => $overdueAmount,
                'overdueDrOrCr' => $overdueDrOrCr,
            ]);
        }

        public function calculateForAllCompany($user_id, $fromLiveOrDatabase)
        {
            $getData=array();
            $currentDate = date('Y-m-d');
            $currentMonth = date('m');
            $currentYear = date('Y');
            $statement_data = "";
            $statement_data_array = array();
            $drBalance = 0;
            $crBalance = 0;
            $overdueAmount = 0;
            $dueAmount = 0;
            $drBalanceBeforeOVDate = 0;
            $crBalanceBeforeOVDate = 0;
            $overdueDrOrCr="";
            $dueDrOrCr="";
            $from_date = "";
            $to_date = "";
            if($fromLiveOrDatabase == 'live'){
                $userAddressData = Address::where('acc_code',"!=","")->where('user_id',$user_id)->groupBy('gstin')->orderBy('acc_code','ASC')->get();
                foreach($userAddressData as $key=>$value){
                    $party_code = $value->acc_code;
                    $userData = User::where('id', $user_id)->first();
                    if ($currentMonth >= 4) {
                        $fy_form_date = date('Y-04-01'); // Start of financial year
                        $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
                    } else {
                        $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
                        $fy_to_date = date('Y-03-31'); // Current year March
                    }
                    $from_date = $fy_form_date;
                    $to_date = $fy_to_date;

                    $headers = [
                        'authtoken' => '65d448afc6f6b',
                    ];

                    $body = [
                        'party_code' => $party_code,
                        'from_date' => $from_date,
                        'to_date' =>  $to_date,
                    ];
                    $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
                    \Log::info('Received response from Salzing API', [
                        'status' => $response->status(),
                        'party_code' =>  $party_code,
                        'body' => $response->body()
                    ]);

                    if ($response->successful()) {
                        $getData = $response->json();
                        $statement_data_array[$key]['party_code'] = $party_code;
                        $statement_data_array[$key]['company_name'] = $value->company_name;
                        
                        $statement_data = $getData['data'];
                        
                        $closingBalanceResult = array_filter($statement_data, function ($entry) {
                            return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
                        });
                        if(!empty($closingBalanceResult)){
                            $closingEntry = reset($closingBalanceResult);
                            $cloasingDrAmount = $closingEntry['dramount'];
                            $cloasingCrAmount = $closingEntry['cramount'];          
                            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
                            if($cloasingCrAmount > 0){
                                $drBalanceBeforeOVDate = 0;
                                $crBalanceBeforeOVDate = 0;
                                $statement_data = array_reverse($statement_data);
                                foreach($statement_data as $ovKey=>$ovValue){
                                    if($ovValue['ledgername'] != 'closing C/f...'){
                                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                                        }else{
                                            $drBalanceBeforeOVDate += $ovValue['dramount'];
                                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                                        }
                                    }
                                    if ($ovValue['dramount'] != 0.00 AND $ovValue['ledgername'] != 'closing C/f...') {
                                        $drBalance = $drBalance + $ovValue['dramount'];
                                        $dueAmount = $dueAmount + $ovValue['dramount'];
                                    } 
                                    if($ovValue['cramount'] != '0.00' AND $ovValue['ledgername'] != 'closing C/f...') {
                                        $crBalance = $crBalance + $ovValue['cramount'];
                                        $dueAmount = $dueAmount - $ovValue['cramount'];
                                    }
                                }
                                $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                                $overDueMark = array();
                                foreach($statement_data as $ovKey=>$ovValue){
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
                                                $statement_data[$ovKey]['overdue_status'] = 'Overdue';
                                            }else{
                                                $overDueMark[] = [
                                                    'trn_no' => $ovValue['trn_no'],
                                                    'trn_date' => $ovValue['trn_date'],
                                                    'overdue_by_day' => $dateDifference,
                                                    'overdue_staus' => 'Pertial Overdue'
                                                ];
                                                $statement_data[$ovKey]['overdue_status'] = 'Pertial Overdue';
                                            }
                                            $statement_data[$ovKey]['overdue_by_day'] = $dateDifference;
                                        }
                                    }
                                }
                            }
                            $statement_data = array_reverse($statement_data);
                            if($overdueAmount <= 0){
                                $overdueAmount = 0;
                                $overdueDrOrCr = 'Cr';
                            }else{
                                $overdueDrOrCr = 'Dr';
                            }
                            $dueDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
                        }
                        $statement_data_array[$key]['statement_data'] = $statement_data;
                    }
                }

            }elseif($fromLiveOrDatabase == 'database'){

                $userAddressData = Address::where('acc_code',"!=","")->where('user_id',$user_id)->groupBy('gstin')->orderBy('acc_code','ASC')->get();
                foreach($userAddressData as $key=>$value){
                    $party_code = $value->acc_code;

                    // $userAddressData = Address::where('acc_code', $userData->party_code)->first();
                    $userData = User::where('id', $value->user_id)->first();
                    $statement_data_array[$key]['party_code'] = $party_code;
                    $statement_data_array[$key]['company_name'] = $value->company_name;
                    $statement_data_array[$key]['statement_data'] = !empty($value->statement_data) ? json_decode($value->statement_data, true) : [];
                    $statement_data = json_decode($value->statement_data, true);
                    if($value->statement_data != NULL){
                        $closingBalanceResult = array_filter($statement_data, function ($entry) {
                            return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
                        });
                        $closingEntry = reset($closingBalanceResult);
                        $cloasingDrAmount = $closingEntry['dramount'];
                        $cloasingCrAmount = $closingEntry['cramount'];          
                        $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
                        foreach($statement_data as $ovKey=>$ovValue){
                            if($ovValue['ledgername'] != 'closing C/f...'){
                                if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                                    // $drBalanceBeforeOVDate += $ovValue['dramount'];
                                    $crBalanceBeforeOVDate += $ovValue['cramount'];
                                }else{
                                    $drBalanceBeforeOVDate += $ovValue['dramount'];
                                    $crBalanceBeforeOVDate += $ovValue['cramount'];
                                }
                            }
                            if ($ovValue['dramount'] != 0.00 AND $ovValue['ledgername'] != 'closing C/f...') {
                                $drBalance = $drBalance + $ovValue['dramount'];
                                $dueAmount = $dueAmount + $ovValue['dramount'];
                            } 
                            if($ovValue['cramount'] != '0.00' AND $ovValue['ledgername'] != 'closing C/f...') {
                                $crBalance = $crBalance + $ovValue['cramount'];
                                $dueAmount = $dueAmount - $ovValue['cramount'];
                            }
                        }
                        $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                        if($overdueAmount <= 0){
                            $overdueAmount = 0;
                            $overdueDrOrCr = 'Cr';
                        }else{
                            $overdueDrOrCr = 'Dr';
                        }
                        $dueDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
                    }
                }
            }
            return response()->json([
                'from_date' => $from_date,
                'to_date' => $to_date,
                'statement_data' => $statement_data_array,
                'dueAmount' => $dueAmount,
                'dueDrOrCr' => $dueDrOrCr,
                'overdueAmount' => $overdueAmount,
                'overdueDrOrCr' => $overdueDrOrCr,
            ]);
        }


    }