<?php

namespace App\Utility;

class sendWhatsAppUtility {
  public static function sendWhatsApp($customer, $params, $media, $campaignName) {
    if (env('WHATSAPP_SERVICE_ON')) {
      // $data = json_encode(['apiKey' => env('WHATSAPP_API_KEY'),
      //   'campaignName'                => $campaignName,
      //   'destination'                 => $customer->phone,
      //   'userName'                    => $customer->name,
      //   'templateParams'              => $params,
      //   'media'                       => $media,
      // ]);
      // $ch = curl_init('https://backend.aisensy.com/campaign/t1/api');
      // curl_setopt($ch, CURLOPT_POST, true);
      // curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
      // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      // curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
      // $response = curl_exec($ch);
      // curl_close($ch);

      $content = array();
      $content['messaging_product'] = "whatsapp";
      $content['to'] = $customer->phone;
      // $content['to'] = '+918961043773';
      $content['type'] = 'template';
      $content['biz_opaque_callback_data'] = 'testing_mazing';
      $content['template'] = $params;

      $token = env('WHATSAPP_API_TOKEN');

      $curl = curl_init();

      curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://graph.facebook.com/v18.0/147572895113819/messages',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($content),
        CURLOPT_HTTPHEADER => array(
          'Content-Type: application/json',
          'Authorization: Bearer '.$token
        ),
      ));

      $response = curl_exec($curl);
      curl_close($curl);

    }
    return true;
  }
}
