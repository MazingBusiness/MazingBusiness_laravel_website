<?php

namespace App\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Import DB facade
use Illuminate\Support\Facades\Http;
use App\Models\PaymentUrl;
use App\Models\Address;
use App\Models\PaymentHistory;
use App\Models\User;
// WebhookLog

class PaymentController extends Controller
{
    public function cashOnDelivery(Request $request)
    {
        $order = new OrderController;
        return $order->store($request);
    }

    public function manualPayment(Request $request)
    {
        $order = new OrderController;
        return $order->store($request);
    }

    public function webhook(Request $request)
	{
        // print_r($request->getContent()); die;
		// Check if the request method is POST
		if ($request->isMethod('post')) {
			// Check if the content type is 'text/plain'
			$contentType = $request->header('Content-Type');

			if ($contentType === 'text/plain') {
				// Get the raw POST data
				$response = $request->getContent();

				// Decrypt the response
				$decrypted_response = $this->decrypt_response($response, storage_path('pay/private/key/private_cer.pem'));

				$jsonDecode = json_decode($decrypted_response, true);
				
				\File::append(storage_path('logs/callback_logs.txt'), $response . PHP_EOL);
				
				// Log the raw POST data into the webhook_logs table
				DB::table('webhook_logs')->insert(['payload' => $decrypted_response, 'merchantTranId' => $jsonDecode['merchantTranId'] ]);

				DB::table('payment_histories')
				->where('merchantTranId', $jsonDecode['merchantTranId'])
				->update([
					'status' => $jsonDecode['TxnStatus']
				]);
				if($jsonDecode['TxnStatus']=='SUCCESS'){
					DB::table('payment_urls')->update(['status' => '1'])->where('merchantTranId', $jsonDecode['merchantTranId']);
				}
				
				return response()->json(['message' => 'Callback received successfully.']);
			} else {
				return response('Invalid content type. Only text/plain is supported.', 400)
					   ->header('Content-Type', 'text/plain');
			}
		} else {
			return response('Only POST requests are allowed.', 405)
				   ->header('Content-Type', 'text/plain');
		}
	}

	private function decrypt_response($encrypted_response, $private_key_path){
		$private_key = file_get_contents($private_key_path);
		$decoded_response = base64_decode($encrypted_response);
		$decrypted = '';
  
		$decryption_successful = openssl_private_decrypt($decoded_response, $decrypted, $private_key, OPENSSL_PKCS1_PADDING);
  
		if ($decryption_successful) {
			return $decrypted;
		} else {
			return 'Decryption failed';
		}
	}

	public function generateUrl(Request $request){
		$party_code = encrypt($request->party_code); // Partycode should ne address tables acc_code
		$payment_for = encrypt($request->payment_for); // due_amount->1, overdue_amount->2, custom_amount->3
		$url=env('SITE_URL').'/pay-amount/'.$payment_for.'/'.$party_code;
        $currentMonth = date('m');
        $currentYear = date('Y');
		if ($currentMonth >= 4) {
            $fy_form_date = date('Y-04-01'); // Start of financial year
            $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        } else {
            $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
            $fy_to_date = date('Y-03-31'); // Current year March
        }
        $from_date = $fy_form_date;
        $to_date = $fy_to_date;
		if($request->payment_for != 'payable_amount'){
			$userAddressData = Address::where('acc_code',$request->party_code)->first();
			$userData = User::where('id', $userAddressData->user_id)->first();
		}
		if($request->payment_for == 'due_amount'){
			$dueAmount = 0;
			$headers = [
				'authtoken' => '65d448afc6f6b',
			];
			$body = [
                'party_code' => $request->party_code,
                'from_date' => $from_date,
                'to_date' =>  $to_date,
            ];
            
            $due_response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
            \Log::info('Received response from Salzing API For payment Url Due Calculation', [
                'status' => $due_response->status(),
                'party_code' =>  $request->party_code,
                'body' => $due_response->body()
            ]);
			$getDueData = $due_response->json();
			if(!empty($getDueData) AND isset($getDueData['data']) AND !empty($getDueData['data'])){				
				$getDueData = $getDueData['data'];				
				foreach($getDueData as $gKey=>$gValue){
					if($gValue['ledgername'] == "Opening b/f..."){
					}else if($gValue['ledgername'] == "closing C/f..."){
						if($gValue['dramount'] != "0.00"){
							$dueAmount = $gValue['dramount'];
						}else{
							$dueAmount = $gValue['cramount'];
						}
					}
				}
			}
			if($dueAmount < 0){
				$dueAmount = 0;
			}
			$getPaymenyUrl = PaymentUrl::where('party_code',$request->party_code)->where('payment_for',$request->payment_for)->get();
			if(!empty($getPaymenyUrl)){
				PaymentUrl::where('party_code',$request->party_code)->where('payment_for',$request->payment_for)->delete();
			}
			$insertArray = array();
			$insertArray['party_code'] = $request->party_code;
			$insertArray['payment_for'] = $request->payment_for;
			$insertArray['amount'] = $dueAmount;
			$insertValue = PaymentUrl::create($insertArray);

			// Update data with the generated URL
			$insertValue->url = $url =  env('SITE_URL') . '/pay-amount/' . $payment_for . '/' . $party_code . '/' . encrypt($insertValue->id);
			$insertValue->save();
		}

		if($request->payment_for == 'overdue_amount'){
			$overdueAmount = 0;
			$headers = [
				'authtoken' => '65d448afc6f6b',
			];
			$body = [
                'party_code' => $request->party_code,
                'from_date' => $from_date,
                'to_date' =>  $to_date,
            ];
            
            $overdue_response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
            \Log::info('Received response from Salzing API For Paymeny Url Overdue Calculation', [
                'status' => $overdue_response->status(),
                'party_code' =>  $request->party_code,
                'body' => $overdue_response->body()
            ]);
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
				}
			}
			$getPaymenyUrl = PaymentUrl::where('party_code',$request->party_code)->where('payment_for',$request->payment_for)->get();
			if(!empty($getPaymenyUrl)){
				PaymentUrl::where('party_code',$request->party_code)->where('payment_for',$request->payment_for)->delete();
			}
			$insertArray = array();
			$insertArray['party_code'] = $request->party_code;
			$insertArray['payment_for'] = $request->payment_for;
			$insertArray['amount'] = $overdueAmount;
			$insertValue = PaymentUrl::create($insertArray);
			// Update data with the generated URL
			$insertValue->url = $url =  env('SITE_URL') . '/pay-amount/' . $payment_for . '/' . $party_code . '/' . encrypt($insertValue->id);
			$insertValue->save();
		}
		
		if($request->payment_for == 'custom_amount'){
			$overdueAmount = 0;
			
			$insertArray = array();
			$insertArray['party_code'] = $request->party_code;
			$insertArray['payment_for'] = $request->payment_for;
			$insertArray['amount'] = "";
			$insertValue = PaymentUrl::create($insertArray);
			// Update data with the generated URL
			$insertValue->url = $url =  env('SITE_URL') . '/pay-amount/' . $payment_for . '/' . $party_code . '/' . encrypt($insertValue->id);
			$insertValue->save();
		}

		if($request->payment_for == 'payable_amount'){
			
			// $getPaymentHistory = PaymentHistory::where('bill_number',$request->party_code)->first();
			$getPaymentHistory = PaymentHistory::where('bill_number', $request->party_code)
                        ->orderBy('id', 'desc')
                        ->first();   // edited by dipak this line only

			$getPaymenyUrl = PaymentUrl::where('party_code',$request->party_code)->where('payment_for',$request->payment_for)->get();
			if(!empty($getPaymenyUrl)){
				PaymentUrl::where('party_code',$request->party_code)->where('payment_for',$request->payment_for)->delete();
			}
			// dd($getPaymentHistory);// echo "hello";die;
			$insertArray = array();
			$insertArray['party_code'] = $request->party_code;
			$insertArray['payment_for'] = $request->payment_for;
			$insertArray['amount'] = $getPaymentHistory->amount;
			$insertArray['qrCodeUrl'] = $getPaymentHistory->qrCodeUrl;
			$insertValue = PaymentUrl::create($insertArray);
			
			// Update data with the generated URL
			$insertValue->url = $url =  env('SITE_URL') . '/pay-amount/' . $payment_for . '/' . $party_code . '/' . encrypt($insertValue->id);
			 
			$insertValue->save();
		}
		
		return response()->json(['url' => $url]);
	}

}
