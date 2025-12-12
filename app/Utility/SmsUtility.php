<?php
namespace App\Utility;

use Exception;

class SmsUtility {
  public static function phone_number_verification($user = '') {
    try {
      sendSMS($user->phone, 'MAZING', 'Your verification code is ' . $user->verification_code . '. Thanks Mazing Retail Private Limited.', null);
    } catch (Exception $e) {}
  }

  public static function password_reset($user = '') {
    try {
      sendSMS($user->phone, 'MAZING', 'Your password reset code is ' . $user->verification_code . '. Thanks Mazing Retail Private Limited.', null);
    } catch (Exception $e) {}
  }

}
