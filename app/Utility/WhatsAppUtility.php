<?php

namespace App\Utility;

use Exception;
use Log;

class WhatsAppUtility {
  public static function accountCreated($user = null) {
    try {
      sendWhatsApp($user, [(string) $user->name], null, 'Account Under Verification');
    } catch (Exception $e) {
      Log::debug($e->getMessage());
    }
  }

  public static function accountRegistered($user = null, $password) {
    try {
      sendWhatsApp($user, [(string) $user->name, (string) $user->phone, (string) $password], null, 'User Successfully Registered on System');
    } catch (Exception $e) {
      Log::debug($e->getMessage());
    }
  }

  public static function accountExists($user = null, $password) {
    try {
      sendWhatsApp($user, [(string) $user->name, (string) $user->phone, (string) $password], null, 'Account already registered');
    } catch (Exception $e) {
      Log::debug($e->getMessage());
    }
  }

  public static function loginOTP($user = null, $otp) {
    $login_otp = $otp;
    $value = '{"name":"mazing_otp","language":{"code":"en"},"components":[{"type":"body","parameters":[{"type":"text","text":"'.$login_otp.'"}]}]}';

    try {
      sendWhatsApp($user, $value, null, 'Contact Support');
    } catch (Exception $e) {
      Log::debug($e->getMessage());
    }
  }

  public static function contactSupport($user = null) {
    $user->name = 'New User';
    try {
      sendWhatsApp($user, [], null, 'Contact Support');
    } catch (Exception $e) {
      Log::debug($e->getMessage());
    }
  }

  public static function paymentConfirmation($user, $order) {
    try {
      sendWhatsApp($user, [(string) $user->name, (string) $order->code, (string) ucwords($order->payment_type), (string) single_price($order->grand_total)], null, 'Payment Confirmation');
    } catch (Exception $e) {
      Log::debug($e->getMessage());
    }
  }

  public static function requestPaymentConfirmation($user, $order) {
    try {
      sendWhatsApp($user, [(string) $user->name, (string) $order->code], null, 'Request Payment Confirmation');
    } catch (Exception $e) {
      Log::debug($e->getMessage());
    }
  }

  public static function orderDetail($user, $order) {
    try {
      $hash             = base64_encode('id=' . $order->id . '&hash=' . $order->combined_order_id . $user->id);
      $shipping_address = json_decode($order->shipping_address);
      sendWhatsApp($user, [(string) $user->name, (string) $order->code, (string) single_price($order->grand_total), $shipping_address->name . ', ' . $order->user->company_name . ', ' . $shipping_address->address . ', ' . $shipping_address->city . ', ' . isset(json_decode($order->shipping_address)->state) ? json_decode($order->shipping_address)->state : '' . ' - ' . $shipping_address->postal_code . ', ' . $shipping_address->country . ', GSTIN: ' . $user->gstin, (string) $user->email, (string) $user->phone], ['url' => env('APP_URL') . '/iv/' . $hash, 'filename' => 'Proforma Invoice'], 'Send Proforma Invoice');
    } catch (Exception $e) {
      Log::debug($e->getMessage());
    }
  }

  public static function orderConfirmed($user, $order) {
    try {
      sendWhatsApp($user, [(string) $user->name, (string) $order->code], null, 'Order Confirmed');
    } catch (Exception $e) {
      Log::debug($e->getMessage());
    }
  }

  public static function orderCancelled($user, $order) {
    try {
      sendWhatsApp($user, [(string) $user->name, (string) $order->code], null, 'Order Cancelled');
    } catch (Exception $e) {
      Log::debug($e->getMessage());
    }
  }

  public static function orderShipped($user, $order) {
    try {
      $hash         = base64_encode('id=' . $order->id . '&hash=' . $order->combined_order_id . $user->id);
      $carrier_name = '';
      if ($order->carrier_id == 372) {
        // $carrier_name = self::get_string_between($order->additional_info, 'Shipper Name: ', 'Shipper GSTIN:');
      } else {
        $carrier_name = $order->carrier->name;
      }
      sendWhatsApp($user, [(string) $user->name, (string) $order->code, (string) $order->tracking_code, (string) $carrier_name], ['url' => env('APP_URL') . '/iv/' . $hash, 'filename' => 'Invoice'], 'Order Shipped');
    } catch (Exception $e) {
      Log::debug($e->getMessage());
    }
  }

  public static function abandonCart($user, $cart) {
    try {
      sendWhatsApp($user, [(string) $user->name], null, 'Request Payment Confirmation');
    } catch (Exception $e) {
      Log::debug($e->getMessage());
    }
  }

  private function get_string_between($string, $start, $end) {
    $string = ' ' . $string;
    $ini    = strpos($string, $start);
    if ($ini == 0) {
      return '';
    }
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
  }
}
