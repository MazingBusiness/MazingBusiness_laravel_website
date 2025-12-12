<?php

namespace App\Utility;

use App\Models\Ccavenue;

class CCAvenueUtility {

  public static function ccEncrypt($plainText, $key) {
    $key           = self::hextobin(md5($key));
    $initVector    = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
    $openMode      = openssl_encrypt($plainText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
    $encryptedText = bin2hex($openMode);
    return $encryptedText;
  }

  public static function ccDecrypt($encryptedText, $key) {
    $key           = self::hextobin(md5($key));
    $initVector    = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
    $encryptedText = self::hextobin($encryptedText);
    $decryptedText = openssl_decrypt($encryptedText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
    return $decryptedText;
  }

  public static function hexToBin($hexString) {
    $length    = strlen($hexString);
    $binString = "";
    $count     = 0;
    while ($count < $length) {
      $subString    = substr($hexString, $count, 2);
      $packedString = pack("H*", $subString);
      if ($count == 0) {
        $binString = $packedString;
      } else {
        $binString .= $packedString;
      }
      $count += 2;
    }
    return $binString;
  }

  public static function createCCAvenueEntry($response) {
    $entry                  = new Ccavenue();
    $entry->order_id        = isset($response['order_id']) ? $response['order_id'] : '';
    $entry->tracking_id     = isset($response['tracking_id']) ? $response['tracking_id'] : '';
    $entry->bank_ref_no     = isset($response['bank_ref_no']) ? $response['bank_ref_no'] : '';
    $entry->order_status    = isset($response['order_status']) ? $response['order_status'] : '';
    $entry->failure_message = isset($response['failure_message']) ? $response['failure_message'] : '';
    $entry->payment_mode    = isset($response['payment_mode']) ? $response['payment_mode'] : '';
    $entry->card_name       = isset($response['card_name']) ? $response['card_name'] : '';
    $entry->status_code     = isset($response['status_code']) ? $response['status_code'] : '';
    $entry->status_message  = isset($response['status_message']) ? $response['status_message'] : '';
    $entry->currency        = isset($response['currency']) ? $response['currency'] : '';
    $entry->amount          = isset($response['amount']) ? $response['amount'] : null;
    $entry->billing_name    = isset($response['billing_name']) ? $response['billing_name'] : '';
    $entry->billing_address = isset($response['billing_address']) ? $response['billing_address'] : '';
    $entry->billing_city    = isset($response['billing_city']) ? $response['billing_city'] : '';
    $entry->billing_state   = isset($response['billing_state']) ? $response['billing_state'] : '';
    $entry->billing_zip     = isset($response['billing_zip']) ? $response['billing_zip'] : '';
    $entry->billing_country = isset($response['billing_country']) ? $response['billing_country'] : '';
    $entry->billing_tel     = isset($response['billing_tel']) ? $response['billing_tel'] : '';
    $entry->billing_email   = isset($response['billing_email']) ? $response['billing_email'] : '';
    $entry->offer_type      = isset($response['offer_type']) ? $response['offer_type'] : '';
    $entry->offer_code      = isset($response['offer_code']) ? $response['offer_code'] : '';
    $entry->discount_value  = isset($response['discount_value']) ? $response['discount_value'] : null;
    $entry->response_code   = isset($response['response_code']) ? $response['response_code'] : '';
    $entry->mer_amount      = isset($response['mer_amount']) ? $response['mer_amount'] : null;
    $entry->save();
  }

}
