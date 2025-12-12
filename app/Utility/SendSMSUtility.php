<?php

namespace App\Utility;

class SendSMSUtility {
  public static function sendSMS($to, $from, $text, $template_id) {
    if (env('SMS_SERVICE_ON')) {
      $curl = curl_init();
      curl_setopt_array($curl, array(
        CURLOPT_URL            => 'https://www.smsalert.co.in/api/push.json',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => array('apikey' => env('SMS_API_KEY'), 'sender' => $from, 'mobileno' => $to, 'text' => $text),
      ));
      $response = curl_exec($curl);
      curl_close($curl);
    }
    return true;
  }
}
