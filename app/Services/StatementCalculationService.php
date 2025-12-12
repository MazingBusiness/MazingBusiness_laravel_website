<?php
    namespace App\Services;
    use App\Models\User;
    use App\Models\Address;
    use App\Models\ZohoSetting;
    use App\Models\ZohoToken;
    use App\Models\UserSalzingStatement;

    use Illuminate\Http\Request;
    use Illuminate\Bus\Queueable;
    use Illuminate\Contracts\Queue\ShouldBeUnique;
    use Illuminate\Contracts\Queue\ShouldQueue;
    use Illuminate\Foundation\Bus\Dispatchable;
    use Illuminate\Queue\InteractsWithQueue;
    use Illuminate\Queue\SerializesModels;
    use Illuminate\Support\Facades\Http;

    use Maatwebsite\Excel\Facades\Excel;
    use Illuminate\Support\Facades\File;

    class StatementCalculationService
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
                // $userData = User::where('id', $user_id)->first();
                // $userAddressData = Address::where('acc_code',"!=","")->where('user_id',$user_id)->first();
                
                $userAddress = Address::where('user_id', $user_id)->first();
                $userData = User::where('id', $userAddress->user_id)->first();
                

                $from_date = '2025-04-01';
                $to_date = date('Y-m-d');
                $orgId = $this->orgId;
                $statement_data = array();
                $cleanedStatement = array();
                // echo $user_id; die;
                // Get multiple address with same GST number.
                if ($userAddress) {
                    $gstin = $userAddress->gstin;
                    $usersAllAddress = Address::where('user_id', $userData->id)->where('gstin', $gstin)->get();
                } else {
                    $usersAllAddress = collect(); // Return empty collection if no address found
                }
                
                // Get Zoho Data
                foreach($usersAllAddress as $userAddressData){
                    // echo "<pre>"; print_r($userAddressData); die;
                    $contactId = $userAddressData->zoho_customer_id;
                    $arrayBeautifier = array();
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
                            if($key > 9){
                                if($value[1] != "" AND  $value[1] != 'Customer Opening Balance'){
                                    $tempVarArray = array();
                                    if($value[1] == 'Invoice' OR $value[1] == 'Debit Note' OR $value[1] == 'Credit Note'){
                                        $tempVarArray = explode(' - ',$value[2]);
                                    }else{
                                        $tempVarArray = explode('₹',$value[2]);
                                    }
                                    $tempArray['trn_no'] = trim($tempVarArray[0]);
                                    // $tempArray['trn_date'] = $value[0];
                                    // change the date format for zoho tran date.
                                    $raw = trim((string) $value[0]);
                                    $formats = [
                                        'd/m/Y H:i:s.u', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
                                        'd-m-Y H:i:s.u', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
                                        'Y-m-d H:i:s.u', 'Y-m-d H:i:s', 'Y-m-d',
                                    ];
                                    $dt = null;
                                    foreach ($formats as $f) {
                                        $dt = \DateTime::createFromFormat($f, $raw);
                                        if ($dt) break;
                                    }
                                    $tempArray['trn_date'] = $dt ? $dt->format('Y-m-d') : e($raw);
                                // -----------------------------------------------------------
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
                                    $tempArray['ledgerid'] = "";
                                    
                                    if(($value[1] == 'Invoice' OR $value[1] == 'Debit Note') AND $value[3] != ""){
                                        if($value[3] >= '0'){
                                            $tempArray['dramount'] = (float)str_replace('-', '',str_replace(',', '',$value[3]));
                                            $tempArray['cramount'] = (float)0.00;                                
                                        }else{
                                            $tempArray['cramount'] = (float)str_replace('-', '',str_replace(',', '',$value[3]));
                                            $tempArray['dramount'] = (float)0.00;
                                        }
                                    }else if(($value[1] == 'Payment Refund') AND $value[4] != ""){
                                        if($value[4] <= '0'){
                                            $tempArray['dramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                                            $tempArray['cramount'] = (float)0.00;                                
                                        }else{
                                            $tempArray['cramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                                            $tempArray['dramount'] = (float)0.00;
                                        }
                                    }elseif(($value[1] == 'Payment Received') AND $value[4] != ""){
                                        if($value[4] <= '0'){
                                            $tempArray['dramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                                            $tempArray['cramount'] = (float)0.00;                                
                                        }else{
                                            $tempArray['cramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                                            $tempArray['dramount'] = (float)0.00;
                                        }
                                    }elseif($value[1] == 'Credit Note' AND $value[3] != ""){
                                        $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[3])));
                                        $tempArray['cramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)$crnValue;
                                        $tempArray['dramount'] = (float)0.00;
                                    }elseif($value[1] == 'Credit Note' AND $value[4] != ""){
                                        $drnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[4])));
                                        $tempArray['dramount'] = $drnValue < 0 ? (float)str_replace('-', '',$drnValue) : (float)$drnValue;
                                        $tempArray['cramount'] = (float)0.00;
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
                                    $arrayBeautifier[] = $tempArray;
                                }
                            }
                        }

                        // Merge salzing and zoho statement data in an array.
                        if(count($cleanedStatement) > 0){
                            $arrayBeautifier = array_merge($cleanedStatement, $arrayBeautifier);
                        }

                        if (File::exists($fullPath)) {
                            File::delete($fullPath);
                        }
                    }            
                    $statement_data[]=$arrayBeautifier;
                }

                // Get salezing Data
                foreach($usersAllAddress as $userAddressData){
                    $contactId = $userAddressData->zoho_customer_id;
                    // Get Salezing Statement from Database
                    $salezingData = UserSalzingStatement::where('zoho_customer_id',$contactId)->first();
                    $cleanedStatement = array();
                    if($salezingData != NULL){                
                        // Step 1: Decode the statement
                        $salezingStatement = json_decode($salezingData->statement_data, true);
                        // Step 2: Clean 'x1' from each item
                        $cleanedStatement = array_map(function ($item) {
                            unset($item['x1']);
                            if (isset($item['overdue_status'])) unset($item['overdue_status']);
                            if (isset($item['overdue_by_day'])) unset($item['overdue_by_day']);
                            return $item;
                        }, $salezingStatement);
                        // Step 3: Remove the last item
                        array_pop($cleanedStatement);
                    }        
                    if (count($cleanedStatement) > 0) {                
                        // Remove "closing C/f......" entries
                        $filteredData = array_filter($cleanedStatement, function ($item) {
                            return !isset($item['ledgername']) || stripos($item['ledgername'], 'closing C/f...') === false;
                        });       
                        $statement_data[] = $filteredData;
                    }
                }
                $statement_data = array_filter($statement_data, function ($item) {
                    return !empty($item);
                });
                // Optional: reindex the array keys (0, 1, 2, ...) after filtering
                $statement_data = array_values($statement_data);

                // echo "<pre>"; print_r($statement_data); die;
                $mergedData = [];
                foreach ($statement_data as $data) {
                    $mergedData = array_merge($mergedData, $data);
                }        
                $statement_data = array_values($mergedData);
                
                usort($statement_data, function ($a, $b) {
                    return strtotime($a['trn_date']) - strtotime($b['trn_date']);
                });
                // echo "<pre>"; print_r($statement_data); die;
                // calculate the running ballance
                $balance = 0.00;
                foreach($statement_data as $gKey=>$gValue){
                    $balance += (float)$gValue['dramount'] - (float)$gValue['cramount'];
                    $statement_data[$gKey]['running_balance'] = $balance;
                }

                // Insert closing balance into array
                $tempArray['trn_no'] = "";
                $tempArray['trn_date'] = date('Y-m-d');
                $tempArray['vouchertypebasename'] = "";
                $tempArray['ledgername'] = "closing C/f...";
                $tempArray['ledgerid'] = "";
                if($balance <= 0){
                    $tempArray['cramount'] = (float)str_replace('-','',str_replace(',', '',$balance));
                    $tempArray['dramount'] = (float)0.00;
                }else{
                    $tempArray['dramount'] = (float)str_replace('-','',str_replace(',', '',$balance));
                    $tempArray['cramount'] = (float)0.00;
                }
                $tempArray['narration'] = "";
                $statement_data[] = $tempArray;

                // echo "<pre>"; print_r($statement_data);die;

                $getData['data'] = $statement_data;

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
                    if($cloasingDrAmount > 0){
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
                            // change the date format for zoho tran date.
                            $raw = trim((string) $ovValue['trn_date']);
                            $formats = [
                                'd/m/Y H:i:s.u', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
                                'd-m-Y H:i:s.u', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
                                'Y-m-d H:i:s.u', 'Y-m-d H:i:s', 'Y-m-d',
                            ];
                            $dt = null;
                            foreach ($formats as $f) {
                                $dt = \DateTime::createFromFormat($f, $raw);
                                if ($dt) break;
                            }
                            $ovValue['trn_date'] = $dt ? $dt->format('Y-m-d') : e($raw);
                            // -----------------------------------------------------------
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