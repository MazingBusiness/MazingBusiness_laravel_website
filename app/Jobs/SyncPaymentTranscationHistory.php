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
use App\Models\PaymentHistory;

class SyncPaymentTranscationHistory implements ShouldQueue
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
        $transactionDetailsData = PaymentHistory::orderBy('id','DESC')->get();
        foreach($transactionDetailsData as $key=>$value){
            // Set MID, VPA, and other variables
            $mid = env('MERCHANT_ID'); //'610853';
            $vpa = env('VPA'); //'aceuat@icici';
            $merchantName = env('MARCHANT_NAME'); //'Ace Tools Pvt. Ltd'; // Merchant name can be dynamic
            $api_url = env('API_URL_TRANSCATION_DETAILS'). $mid; // 'https://apibankingonesandbox.icicibank.com/api/MerchantAPI/UPI/v0/QR3/' . $mid;
            // Payload to be encrypted
            $payload = json_encode([
                'merchantId' => $mid,
                'terminalId' => env('TERMINAL_ID'), // '5411',
                'merchantTranId' => $value->merchantTranId,
                'subMerchantId' => $value->subMerchantId,
            ]);

            // Encrypt the payload
            $encrypted_payload = $this->encrypt_payload($payload, storage_path('pay/public/key/rsa_apikey.txt'));

            // Send API request
            $response = $this->send_api_request($api_url, $encrypted_payload);

            // Decrypt the response
            $decrypted_response = $this->decrypt_response($response, storage_path('pay/private/key/private_cer.pem'));

            // Handle response and generate UPI URL and QR code
            $response_data = json_decode($decrypted_response, true);
            
            $paymentHistory = PaymentHistory::where('id', $value->id)->first();
            $paymentHistory->status = $response_data['status']; // data will come :- PENDING, SUCCESS, FAILURE
            $paymentHistory->success = $response_data['success'];
            $paymentHistory->message = $response_data['message'];
            $paymentHistory->save();
        }
    }
    
    private function decrypt_response($encrypted_response, $private_key_path){
        // Load the private key from the file
        $private_key = file_get_contents($private_key_path);
  
        // Decode the base64-encoded encrypted response
        $decoded_response = base64_decode($encrypted_response);
  
        // Variable to hold the decrypted response
        $decrypted = '';
  
        // Decrypt the response using the private key and PKCS1 padding
        $decryption_successful = openssl_private_decrypt($decoded_response, $decrypted, $private_key, OPENSSL_PKCS1_PADDING);
  
        // Check if decryption was successful
        if ($decryption_successful) {
            return $decrypted;  // Return the decrypted response
        } else {
            return 'Decryption failed';  // Handle decryption failure
        }
    }
  
    private function encrypt_payload($payload, $public_key_path){
        // Load the public key from the file
        $public_key = file_get_contents($public_key_path);
  
        // Variable to hold the encrypted result
        $encrypted = '';
  
        // Encrypt the payload using the public key and PKCS1 padding
        $encryption_successful = openssl_public_encrypt($payload, $encrypted, $public_key, OPENSSL_PKCS1_PADDING);
  
        // Check if encryption was successful
        if ($encryption_successful) {
            // Base64 encode the encrypted payload
            return base64_encode($encrypted);
        } else {
            return 'Encryption failed';  // Handle encryption failure
        }
    }

    private function send_api_request($url, $encrypted_payload) {
        // Initialize cURL
        $ch = curl_init();
  
        // Set the cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encrypted_payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  
        // Set headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: */*',
            'accept-encoding: *',
            'accept-language: en-US,en;q=0.8,hi;q=0.6',
            'cache-control: no-cache',
            'connection: keep-alive',
            'content-length: ' . strlen($encrypted_payload),
            'content-type: text/plain;charset=UTF-8',
        ]);
  
        // Execute the cURL request and fetch response
        $response = curl_exec($ch);
  
        // Check for errors
        if ($response === false) {
            $response = curl_error($ch);
        }
  
        // Close cURL
        curl_close($ch);
  
        // Return the response
        return $response;
    }
}
