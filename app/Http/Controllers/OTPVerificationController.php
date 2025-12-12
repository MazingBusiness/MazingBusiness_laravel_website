<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Utility\SendSMSUtility;
use App\Utility\SmsUtility;
use App\Utility\WhatsAppUtility;
use Auth;
use Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Mail;

class OTPVerificationController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function verification(Request $request)
  {
    if (Auth::check() && Auth::user()->email_verified_at == null) {
      return view('otp_systems.frontend.user_verification');
    } elseif ($request->session('modal_verification') &&  Auth::user()->email_verified_at != null) {
      return redirect()->route('home')->with(['modal_otp' => 1]);
    } else {
      flash('You have already verified your number')->warning();
      return redirect()->route('home');
    }
  }

  /**
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */

  public function verify_phone(Request $request)
  {
    $user = Auth::user();
    if ($user->verification_code == $request->verification_code) {
      $user->email_verified_at = date('Y-m-d h:m:s');
      $user->verification_code = null;
      $user->save();
      if ($request->has('modal_verification')) {
        return redirect()->intended('/');
      }
      WhatsAppUtility::accountRegistered($user, $user->address);
      // Send email
      $user = Auth::user()->toArray();
      Mail::send('emails.user_registered', $user, function ($message) {
        $message->to('kburhanuddin12@gmail.com', 'Mazing Business')->subject('New user registered on Mazing Business');
        $message->from(env('MAIL_FROM_ADDRESS'), 'Mazing Business');
      });
      flash('Your phone number has been verified successfully')->success();
      return redirect()->intended('/');
    } elseif ($request->has('modal_verification')) {
      flash('Invalid Code')->error();
      return redirect()->route('home')->with(['modal_otp' => 1]);
    } else {
      flash('Invalid Code')->error();
      return back();
    }
  }

  public function login_otp(Request $request)
  {

      $user_phone = "+91".$request->phone;

      $user = User::where('phone', $user_phone)->first();

      $otp = rand(100000, 999999);

      $user->login_otp = $otp;
      $user->save();

      $sms_text = 'Your verification code is '.$otp.'. Thanks Mazing Retail Private Limited.';
      // WhatsAppUtility::loginOTP($user, $otp);
      SendSMSUtility::sendSMS($user_phone,'MAZING',$sms_text,'1207168939860495573');
      // SendSMSUtility::sendSMS('+918961043773','MAZING',$sms_text,'1207168939860495573');

      return response()->json([
        'message' => 'OTP verified successfully',
        'phone' => $user_phone,
    ]);

      // flash('Your phone number has been verified successfully')->success();
  }

  /**
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */

  public function resend_verificcation_code(Request $request)
  {
    $user                    = Auth::user();
    $user->verification_code = rand(100000, 999999);
    $user->save();
    SmsUtility::phone_number_verification($user);
    if ($request->has('modal_verification')) {
      flash('Verification code has been sent.')->success();
      return redirect()->route('home')->with(['modal_otp' => 1]);
    }
    return back();
  }

  /**
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */

  public function reset_password_with_code(Request $request)
  {
    $phone = "+{$request['country_code']}{$request['phone']}";
    if (($user = User::where('phone', $phone)->where('verification_code', $request->code)->first()) != null) {
      if ($request->password == $request->password_confirmation) {
        $user->password          = Hash::make($request->password);
        $user->email_verified_at = date('Y-m-d h:m:s');
        $user->save();
        event(new PasswordReset($user));
        auth()->login($user, true);

        if (auth()->user()->user_type == 'admin' || auth()->user()->user_type == 'staff') {
          flash("Password has been reset successfully")->success();
          return redirect()->route('admin.dashboard');
        }
        flash("Password has been reset successfully")->success();
        return redirect()->route('home');
      } else {
        flash("Password and confirm password didn't match")->warning();
        return view('otp_systems.frontend.auth.passwords.reset_with_phone');
      }
    } else {
      flash("Verification code mismatch")->error();
      return view('otp_systems.frontend.auth.passwords.reset_with_phone');
    }
  }

  /**
   * @param  User $user
   * @return void
   */

  public function send_code($user)
  {
    SmsUtility::phone_number_verification($user);
  }

  /**
   * @param  Order $order
   * @return void
   */
  public function send_order_code($order)
  {
    $phone = json_decode($order->shipping_address)->phone;
    if ($phone != null) {
      SmsUtility::order_placement($phone, $order);
    }
  }

  /**
   * @param  Order $order
   * @return void
   */
  public function send_delivery_status($order)
  {
    $phone = json_decode($order->shipping_address)->phone;
    if ($phone != null) {
      SmsUtility::delivery_status_change($phone, $order);
    }
  }

  /**
   * @param  Order $order
   * @return void
   */
  public function send_payment_status($order)
  {
    $phone = json_decode($order->shipping_address)->phone;
    if ($phone != null) {
      SmsUtility::payment_status_change($phone, $order);
    }
  }
}
