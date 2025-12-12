<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OTPVerificationController;
use App\Models\Cart;
use App\Models\Pincode;
use App\Models\State;
use App\Models\User;
use App\Models\Warehouse;
use App\Utility\WhatsAppUtility;
use Auth;
use Cookie;
use Hash;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Session;

class RegisterController extends Controller {
  /*
  |--------------------------------------------------------------------------
  | Register Controller
  |--------------------------------------------------------------------------
  |
  | This controller handles the registration of new users as well as their
  | validation and creation. By default this controller uses a trait to
  | provide this functionality without requiring any additional code.
  |
   */

  use RegistersUsers;

  /**
   * Where to redirect users after registration.
   *
   * @var string
   */
  protected $redirectTo = '/';

  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct() {
    $this->middleware('guest');
  }

  /**
   * Get a validator for an incoming registration request.
   *
   * @param  array  $data
   * @return \Illuminate\Contracts\Validation\Validator
   */
  protected function validator(array $data) {
    if (isset($data['no_gstin'])) {
      return Validator::make($data, [
        'phone'        => 'required|numeric',
        'aadhar_card'  => 'required|string|size:12',
        'name'         => 'required|string|max:255|regex:/^[a-zA-Z][ \'a-zA-Z]*$/',
        'company_name' => 'required|string|max:255',
        'email'        => 'required|email|unique:users',
        'postal_code'  => 'required|numeric|digits:6|min:100000|exists:pincodes,pincode',
        'agree_terms'  => 'accepted_if:modal_login,null',
      ]);
    } else {
      return Validator::make($data, [
        'phone'       => 'required|numeric',
        'gstin'       => 'required|string|size:15|unique:users',
        'agree_terms' => 'accepted',
      ]);
    }
    $data['phone'] = '+' . $data['country_code'] . $data['phone'];
  }

  /**
   * Create a new user instance after a valid registration.
   *
   * @param  array  $data
   * @return \App\Models\User
   */
  protected function create(array $data) {
    if ($data['gstin']) {
      $pincode = Pincode::where('pincode', $data['gst_data']['taxpayerInfo']['pradr']['addr']['pncd'])->first();
    } else {
      $pincode = Pincode::where('pincode', $data['postal_code'])->first();
    }
    if ($pincode) {
      if (User::where('phone', '+' . $data['country_code'] . $data['phone'])->exists() && $data['modal_login']) {
        $user = User::where('phone', '+' . $data['country_code'] . $data['phone'])->first();
        $user->update(['verification_code' => rand(100000, 999999)]);
        $otpController = new OTPVerificationController;
        $otpController->send_code($user);
      } else {
        $state        = State::where('name', $pincode['state'])->first();
        $warehouse    = Warehouse::whereRaw("FIND_IN_SET('$state->id', service_states)")->first();
        $lastcustomer = User::where('user_type', 'customer')->where('warehouse_id', $warehouse->id)->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        if ($lastcustomer) {
          $party_code = 'OPEL0' . $warehouse->id . str_pad(((int) substr($lastcustomer->party_code, -5) + 1), 5, '0', STR_PAD_LEFT);
        } else {
          $party_code = 'OPEL0' . $warehouse->id . '00001';
        }
        $password = mb_substr($warehouse->name, 0, 1) . substr($data['phone'], -4);
        if ($data['gstin']) {
          $user = User::create([
            'name'                   => $data['gst_data']['taxpayerInfo']['lgnm'],
            'company_name'           => $data['gst_data']['taxpayerInfo']['tradeNam'],
            'phone'                  => '+' . $data['country_code'] . $data['phone'],
            'email'                  => null,
            'password'               => Hash::make($password),
            'address'                => $password,
            'gstin'                  => $data['gstin'],
            'aadhar_card'            => null,
            'postal_code'            => $pincode->pincode,
            'city'                   => $pincode->city,
            'state'                  => $pincode->state,
            'country'                => 'India',
            'verification_code'      => rand(100000, 999999),
            'warehouse_id'           => $warehouse->id,
            'party_code'             => $party_code,
            'virtual_account_number' => $party_code,
            'user_type'              => 'customer',
            'banned'                 => true,
          ]);
        } else {
          $user = User::create([
            'name'                   => $data['modal_login'] ? 'Directly Registered User' : $data['name'],
            'company_name'           => $data['modal_login'] ? 'No Company' : $data['company_name'],
            'phone'                  => '+' . $data['country_code'] . $data['phone'],
            'email'                  => $data['modal_login'] ? null : $data['email'],
            'password'               => Hash::make($password),
            'address'                => $password,
            'aadhar_card'            => $data['modal_login'] ? '' : $data['aadhar_card'],
            'postal_code'            => $data['postal_code'],
            'city'                   => $pincode->city,
            'state'                  => $pincode->state,
            'country'                => 'India',
            'verification_code'      => rand(100000, 999999),
            'warehouse_id'           => $warehouse->id,
            'party_code'             => $party_code,
            'virtual_account_number' => $party_code,
            'user_type'              => 'customer',
            'banned'                 => true,
          ]);
        }
        $otpController = new OTPVerificationController;
        $otpController->send_code($user);
        if (session('temp_user_id') != null) {
          Cart::where('temp_user_id', session('temp_user_id'))
            ->update([
              'user_id'      => $user->id,
              'temp_user_id' => null,
            ]);
          Session::forget('temp_user_id');
        }
        if (Cookie::has('referral_code')) {
          $referral_code    = Cookie::get('referral_code');
          $referred_by_user = User::where('referral_code', $referral_code)->first();
          if ($referred_by_user != null) {
            $user->referred_by = $referred_by_user->id;
            $user->save();
          }
        }
      }
      return $user;
    } else {
      return false;
    }
  }

  public function register(Request $request) {
    $validator = $this->validator($request->all());
    if ($validator->fails()) {
      $failedRules = $validator->failed();
      if (isset($failedRules['email']['Unique']) || isset($failedRules['phone']['Unique'])) {
        $user      = User::where('email', $request->email)->orWhere('phone', 'like', '%' . $request->phone)->first();
        $warehouse = Warehouse::find($user->warehouse_id);
        $password  = mb_substr($warehouse->name, 0, 1) . substr($request->phone, -4);
        WhatsAppUtility::accountExists($user, $password);
      } else {
        WhatsAppUtility::contactSupport($request);
      }
      return $validator->validate();
    }
    if (User::where('phone', '+' . $request['country_code'] . $request['phone'])->exists() && !$request->has('modal_login')) {
      return redirect()->route('user.registration')->withErrors(['phone' => 'The phone has already been taken.'])->withInput();
    }
    if ($request->has('gstin') && $request->gstin != '') {
      try {
        $response = Http::post('https://appyflow.in/api/verifyGST', [
          'key_secret' => env('APPYFLOW_KEYSECRET'),
          'gstNo'      => $request->gstin,
        ]);
      } catch (\Exception $e) {
        return redirect()->route('user.registration')->withErrors(['gstin' => 'Some error occurred getting your GST Details.'])->withInput();
      }
      if ($response->successful()) {
        $data = json_decode($response->body(), true);
        if (false) {
          // if ($data['error']) {
          return redirect()->route('user.registration')->withErrors(['gstin' => $data['message']])->withInput();
        } else {
          $user = $this->create($request->all() + ['modal_login' => null] + ['gst_data' => $data]);
          if (!$user) {
            return redirect()->route('user.registration')->withErrors(['gstin' => 'Some error occurred getting your GST Details.'])->withInput();
          }
        }
      }
    } else {
      $user = $this->create($request->all() + ['modal_login' => null]);
      if (!$user) {
        return redirect()->route('user.registration')->withErrors(['postal_code' => 'The given Pincode does not exist!'])->withInput();
      }
    }
    $this->guard()->login($user);
    return $this->registered($request, $user)
    ?: redirect($this->redirectPath());
  }

  protected function registered(Request $request, $user) {
    if (Auth::check() && $user->phone) {
      return redirect()->route('verification');
    } elseif ($user->verification_code != null) {
      return redirect()->route('verification')->with('modal_verification', 1);
    } elseif (session('link') != null) {
      return redirect(session('link'));
    } else {
      return redirect()->intended('/');
    }
  }
}
