<?php

/** @noinspection PhpUndefinedClassInspection */

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\OTPVerificationController;
use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\Pincode;
use App\Models\State;
use App\Models\Address;
use App\Models\City;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\AppEmailVerificationNotification;
// use Hash;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Socialite;
use App\Utility\SendSMSUtility;
use App\Utility\SmsUtility;
use Illuminate\Validation\ValidationException;
use GuzzleHttp\Client;

class AuthController extends Controller
{
  public function signup(Request $request)
  {
    //Validate Request
    $validator = $this->validateSignUp($request);
    if ($validator->fails()) {
      return response()->json([
        'result' => false,
        'message' => $validator->errors()
      ]);
    }
    $pincode = Pincode::where('pincode', $request->postal_code)->first();
    $state     = State::where('name', $pincode['state'])->first();
    $warehouse = Warehouse::whereRaw("FIND_IN_SET('$state->id', service_states)")->first();
    $password  = mb_substr($warehouse->name, 0, 1) . substr($request->phone, -4);
    $user      = User::create([
      'name'              => $request->name,
      'company_name'      => $request->company_name,
      'phone'             => $request->phone,
      'email'             => $request->email,
      'password'          => Hash::make($password),
      'address'           => $password,
      'gstin'             => $request->gstin,
      'postal_code'       => $request->postal_code,
      'city'              => $pincode->city,
      'state'             => $pincode->state,
      'country'           => 'India',
      'verification_code' => rand(100000, 999999),
      'warehouse_id'      => $warehouse->id,
      'user_type'         => 'customer',
    ]);

    $user->email_verified_at = null;
    if ($user->email != null) {
      if (BusinessSetting::where('type', 'email_verification')->first()->value != 1) {
        $user->email_verified_at = date('Y-m-d H:m:s');
      }
    }

    if ($user->email_verified_at == null) {
      if ($request->register_by == 'email') {
        try {
          $user->notify(new AppEmailVerificationNotification());
        } catch (\Exception $e) {
        }
      } else {
        $otpController = new OTPVerificationController();
        $otpController->send_code($user);
      }
    }

    $user->save();

    //create token
    $user->createToken('tokens')->plainTextToken;

    return response()->json([
      'result'  => true,
      'message' => translate('Registration Successful. Please verify and log in to your account.'),
      'user_id' => $user->id,
    ], 201);
  }

  public function resendCode(Request $request)
  {
    $user                    = User::where('id', $request->user_id)->first();
    $user->verification_code = rand(100000, 999999);

    if ($request->verify_by == 'email') {
      $user->notify(new AppEmailVerificationNotification());
    } else {
      $otpController = new OTPVerificationController();
      $otpController->send_code($user);
    }

    $user->save();

    return response()->json([
      'result'  => true,
      'message' => translate('Verification code is sent again'),
    ], 200);
  }

  public function confirmCode(Request $request)
  {
    $user = User::where('id', $request->user_id)->first();

    if ($user->verification_code == $request->verification_code) {
      $user->email_verified_at = date('Y-m-d H:i:s');
      $user->verification_code = null;
      $user->save();
      return response()->json([
        'result'  => true,
        'message' => translate('Your account is now verified.Please login'),
      ], 200);
    } else {
      return response()->json([
        'result'  => false,
        'message' => translate('Code does not match, you can request for resending the code'),
      ], 200);
    }
  }

  public function login(Request $request)
  {
    //Validate Request
    $validator = $this->validateLogin($request);
    if ($validator->fails()) {
      return response()->json([
        'result' => false,
        'message' => $validator->errors()
      ]);
    }

    $delivery_boy_condition = $request->has('user_type') && $request->user_type == 'delivery_boy';
    $seller_condition       = $request->has('user_type') && $request->user_type == 'seller';
    $staff_condition       = $request->has('user_type') && $request->user_type == 'staff';

    if ($delivery_boy_condition) {
      $user = User::whereIn('user_type', ['delivery_boy'])
        ->where('email', $request->email)
        ->orWhere('phone', $request->email)
        ->first();
    } elseif ($seller_condition) {
      $user = User::whereIn('user_type', ['seller'])
        ->where('email', $request->email)
        ->orWhere('phone', $request->email)
        ->first();
    } elseif ($staff_condition) {
      $user = User::whereIn('user_type', ['staff'])
        ->where('email', $request->email)
        ->orWhere('phone', $request->email)
        ->first();
    } else {
      $user = User::whereIn('user_type', ['customer'])
        ->where('email', $request->email)
        ->orWhere('phone', $request->email)
        ->first();
    }
    // echo $request->password.'...'.$password = Hash::check($request->password, $user->password);
    // if (Hash::check($request->password, $user->password)) {
    //     // Passwords match
    //     return response()->json(['message' => 'Login successful']);
    // } else {
    //     // Passwords do not match
    //     return response()->json(['message' => 'Login failed'], 401);
    // }
    // print_r($request->password);die;die;
    // if (!$delivery_boy_condition) {
    // if (!$delivery_boy_condition && !$seller_condition) {
    //   if (\App\Utility\PayhereUtility::create_wallet_reference($request->identity_matrix) == false) {
    //     return response()->json(['result' => false, 'message' => 'Identity matrix error', 'user' => null], 401);
    //   }
    // }

    if ($user != null) {
      if (!$user->banned) {
        if ($user->verification_code == $request->password) {
          if ($user->email_verified_at == null) {
            return response()->json(['result' => false, 'message' => translate('Please verify your account'), 'user' => null], 401);
          }
          return $this->loginSuccess($user);
        }elseif($request->password == $user->login_otp){
          return $this->loginSuccess($user);
        } else {
          return response()->json(['result' => false, 'message' => translate('Unauthorized'), 'user' => null], 401);
        }
      } else {
        return response()->json(['result' => false, 'message' => translate('User account is not verified.'), 'user' => null], 401);
      }
    } else {
      return response()->json(['result' => false, 'message' => translate('User not found.'), 'user' => null], 401);
    }
  }

  public function login_otp(Request $request)
  {
      //$user_phone = "+91".$request->phone;
      $user_phone = $request->phone;
      $userCount = User::where('phone', $user_phone)->count();
      if($userCount > 0){
        $user = User::where('phone', $user_phone)->first();
        $otp = rand(100000, 999999);
        $user->login_otp = $otp;
        $user->save();
        
        $sms_text = 'Your verification code is '.$otp.'. Thanks Mazing Retail Private Limited.';
        // WhatsAppUtility::loginOTP($user, $otp);
        SendSMSUtility::sendSMS($user_phone,'MAZING',$sms_text,'1207168939860495573');
        //SendSMSUtility::sendSMS('+918961043773','MAZING',$sms_text,'1207168939860495573');

        return response()->json([
            'success' => true,
            'message' => 'OTP Send Successfully',
            'phone' => $user_phone,
        ]);
      }else{
        return response()->json([
            'success' => false,
            'message' => 'Number doesn\'t exist.',
            'phone' => $user_phone,
        ]);
      }
      
    // flash('Your phone number has been verified successfully')->success();
  }

  
  public function manager_clients(Request $request)
  {
    try {
      $searchTerm = $request->input('search_text');
      $per_page = $request['per_page'] != '' ? $request['per_page'] : 30;
      if($request->id == 180 OR $request->id == 25606 OR $request->id == 169){
        $users = User::where('user_type', 'customer')
          ->where(function ($query) use ($searchTerm) {
              $query->where('party_code', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('gstin', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('company_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
          })
          ->orWhereHas('get_manager', function($query) use ($searchTerm) {
              $query->where('name', 'LIKE', "%{$searchTerm}%");
          })
          ->orWhereHas('get_addresses', function($query) use ($searchTerm) {
              $query->whereNotNull('city')
                    ->where('city', 'LIKE', "%{$searchTerm}%");
          })
          ->where('user_type','customer')
        ->paginate($per_page);
      }else{
        $users = User::where('manager_id', $request->id)->where('user_type', 'customer');

        // Apply search conditions if a search term is provided
        if ($searchTerm != "") {
            $users->where(function ($query) use ($searchTerm) {
                // Search across multiple columns
                $query->where('party_code', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('gstin', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('company_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('phone', 'LIKE', "%{$searchTerm}%");

                // Check if the manager's name matches the search term
                $query->orWhereHas('get_manager', function($subQuery) use ($searchTerm) {
                    $subQuery->where('name', 'LIKE', "%{$searchTerm}%");
                });

                // Check if the city in the user's addresses matches the search term
                $query->orWhereHas('get_addresses', function($subQuery) use ($searchTerm) {
                    $subQuery->whereNotNull('city')
                            ->where('city', 'LIKE', "%{$searchTerm}%");
                });
            });
        }
        // Finally, paginate the results
        $users = $users->paginate($per_page);
      }
      if ($users->isEmpty()) {
          return response()->json([
              "data" => [],
              "success" => false,
              "status" => 404,
              "message" => "No users found for the provided manager ID"
          ], 404);
      } else {
          return response()->json([
              "data" => $users,
              "success" => true,
              "status" => 200,
              "message" => "Warehouse retrieved successfully"
          ], 200);
      }
    }catch (\Exception $e) {
        // Log any other exceptions
        \Log::error('An error occurred: ' . $e->getMessage());

        return response()->json([
            'status' => 'Error',
            'message' => 'An unexpected error occurred.',
        ], 500);
    }
  }


  public function user(Request $request)
  {
    return response()->json($request->user());
  }

  public function logout(Request $request)
  {

    $user = request()->user();
    $user->tokens()->where('id', $user->currentAccessToken()->id)->delete();

    return response()->json([
      'result'  => true,
      'message' => translate('Successfully logged out'),
    ]);
  }

  public function socialLogin(Request $request)
  {
    if (!$request->provider) {
      return response()->json([
        'result'  => false,
        'message' => translate('User not found'),
        'user'    => null,
      ]);
    }

    switch ($request->social_provider) {
      case 'facebook':
        $social_user = Socialite::driver('facebook')->fields([
          'name',
          'first_name',
          'last_name',
          'email',
        ]);
        break;
      case 'google':
        $social_user = Socialite::driver('google')
          ->scopes(['profile', 'email']);
        break;
      case 'twitter':
        $social_user = Socialite::driver('twitter');
        break;
      case 'apple':
        $social_user = Socialite::driver('sign-in-with-apple')
          ->scopes(['name', 'email']);
        break;
      default:
        $social_user = null;
    }
    if ($social_user == null) {
      return response()->json(['result' => false, 'message' => translate('No social provider matches'), 'user' => null]);
    }

    if ($request->social_provider == 'twitter') {
      $social_user_details = $social_user->userFromTokenAndSecret($request->access_token, $request->secret_token);
    } else {
      $social_user_details = $social_user->userFromToken($request->access_token);
    }

    if ($social_user_details == null) {
      return response()->json(['result' => false, 'message' => translate('No social account matches'), 'user' => null]);
    }

    $existingUserByProviderId = User::where('provider_id', $request->provider)->first();

    if ($existingUserByProviderId) {
      $existingUserByProviderId->access_token = $social_user_details->token;
      if ($request->social_provider == 'apple') {
        $existingUserByProviderId->refresh_token = $social_user_details->refreshToken;
        if (!isset($social_user->user['is_private_email'])) {
          $existingUserByProviderId->email = $social_user_details->email;
        }
      }
      $existingUserByProviderId->save();
      return $this->loginSuccess($existingUserByProviderId);
    } else {
      $existing_or_new_user = User::firstOrNew(
        [['email', '!=', null], 'email' => $social_user_details->email]
      );

      $existing_or_new_user->user_type   = 'customer';
      $existing_or_new_user->provider_id = $social_user_details->id;
      $existing_or_new_user->provider    = $request->social_provider;
      if (!$existing_or_new_user->exists) {
        if ($request->social_provider == 'apple') {
          if ($request->name) {
            $existing_or_new_user->name = $request->name;
          } else {
            $existing_or_new_user->name = 'Apple User';
          }
        } else {
          $existing_or_new_user->name = $social_user_details->name;
        }
        $existing_or_new_user->email             = $social_user_details->email;
        $existing_or_new_user->email_verified_at = date('Y-m-d H:m:s');
      }

      $existing_or_new_user->save();

      return $this->loginSuccess($existing_or_new_user);
    }
  }

  protected function loginSuccess($user)
  {
    $token = $user->createToken('API Token')->plainTextToken;
    return response()->json([
      'result'       => true,
      'message'      => translate('Successfully logged in'),
      'access_token' => $token,
      'token_type'   => 'Bearer',
      'expires_at'   => null,
      'user'         => [
        'id'              => $user->id,
        'type'            => $user->user_type,
        'name'            => $user->name,
        'email'           => $user->email,
        'avatar'          => $user->avatar,
        'avatar_original' => uploaded_asset($user->avatar_original),
        'phone'           => $user->phone,
        'warehouse_id'    => $user->warehouse_id,
      ],
    ]);
  }

  public function account_deletion()
  {
    if (auth()->user()) {
      Cart::where('user_id', auth()->user()->id)->delete();
    }

    // if (auth()->user()->provider && auth()->user()->provider != 'apple') {
    //     $social_revoke =  new SocialRevoke;
    //     $revoke_output = $social_revoke->apply(auth()->user()->provider);

    //     if ($revoke_output) {
    //     }
    // }

    $auth_user = auth()->user();
    $auth_user->tokens()->where('id', $auth_user->currentAccessToken()->id)->delete();
    $auth_user->customer_products()->delete();

    User::destroy(auth()->user()->id);

    return response()->json([
      "result"  => true,
      "message" => translate('Your account deletion successfully done'),
    ]);
  }

  private function validateSignUp(Request $request)
  {
    $validate = Validator::make($request->all(), [
      'name'         => 'required|string|max:255|regex:/^[a-zA-Z][ \'a-zA-Z]*$/',
      'company_name' => 'required|string|max:255',
      'phone'        => 'required|numeric|unique:users',
      'email'        => 'nullable|email|unique:users',
      'gstin'        => 'required|string|size:15',
      'postal_code'  => 'required|numeric|digits:6|min:100000|exists:pincodes,pincode',
    ]);
    return $validate;
  }

  private function validateLogin(Request $request)
  {
    $validate = Validator::make($request->all(), [
      'email'        => 'required|nullable',
      'password'     => 'required'
    ]);
    return $validate;
  }

  //Customer add by Magager
  public function getWarehouse(Request $request)
  {
    try {      
      $users = User::where('users.id', $request->id)
              ->where('users.user_type', 'staff')
              ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
              ->select('warehouses.*') // Adjust the columns as needed
              ->get();
      if ($users->isEmpty()) {
          return response()->json([
              "data" => [],
              "success" => false,
              "status" => 404,
              "message" => "No warehouse found for the provided manager ID"
          ], 404);
      } else {
          return response()->json([
              "data" => $users,
              "success" => true,
              "status" => 200,
              "message" => "Warehouse retrieved successfully"
          ], 200);
      }
    }catch (\Exception $e) {
        // Log any other exceptions
        \Log::error('An error occurred: ' . $e->getMessage());

        return response()->json([
            'status' => 'Error',
            'message' => $e->getMessage(),
        ], 500);
    }
  }

  public function checkGstNumber(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'gstin'=> 'required|string|size:15|unique:users'
      ]);
      if ($validator->fails()) {
        return response()->json([
          'result' => false,
          'message' => $validator->errors()
        ]);
      }
      // GST number is valid, proceed to check with AppyFlow API
      $gstNumber = $request->input('gstin');
      $apiKey = 'H50csEwe27SjLf7J2qP9Av28uOm2'; // Replace with your AppyFlow API key

      $client = new Client();
      $response = $client->request('GET', 'https://appyflow.in/api/verifyGST', [
          'query' => [
              'gstNo' => $gstNumber,
              'key_secret' => $apiKey
          ]
      ]);
      $responseBody = json_decode($response->getBody(), true);
      if(isset($responseBody['error'])){
        return response()->json([
            'result' => false,
            'data' => $responseBody
        ]);
      }else{
        return response()->json([
            'result' => true,
            'data' => $responseBody
        ]);
      }      
    }catch (\Exception $e) {
        // Log any other exceptions
        \Log::error('An error occurred: ' . $e->getMessage());

        return response()->json([
            'status' => 'Error',
            'message' => $e->getMessage(),
        ], 500);
    }
  }

  public function checkAadharCardNumber(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'aadhar_card'=> 'required|numeric|digits:12|unique:users'
      ]);
      if ($validator->fails()) {
        return response()->json([
          'result' => false,
          'message' => $validator->errors()
        ]);
      }else{
        return response()->json([
          'result' => true,
          'message' => "Valid Aadhar card."
        ]);
      }
    }catch (\Exception $e) {
        // Log any other exceptions
        \Log::error('An error occurred: ' . $e->getMessage());

        return response()->json([
            'status' => 'Error',
            'message' => $e->getMessage(),
        ], 500);
    }
  }

  public function checkEmail(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'email' => 'required|email|unique:users,email',
      ]);
      if ($validator->fails()) {
        return response()->json([
          'result' => false,
          'message' => $validator->errors()
        ]);
      }else{
        return response()->json([
          'result' => true,
          'message' => "Valid email."
        ]);
      }
    }catch (\Exception $e) {
        // Log any other exceptions
        \Log::error('An error occurred: ' . $e->getMessage());

        return response()->json([
            'status' => 'Error',
            'message' => $e->getMessage(),
        ], 500);
    }
  }

  public function checkPhone(Request $request)
  {
    try {
      // Preprocess phone number: remove non-numeric characters and add '+91' prefix
      $phone = '+91' . preg_replace('/[^0-9]/', '', $request->input('phone'));
      // Validate the request
      $validator = Validator::make(['phone' => $phone], [
          'phone' => 'required|numeric|unique:users,phone', // Adjust 'phone_number' to match your column name in the 'users' table
      ]);
      if ($validator->fails()) {
        return response()->json([
          'result' => false,
          'message' => $validator->errors()
        ]);
      }else{
        return response()->json([
          'result' => true,
          'message' => "Valid phone."
        ]);
      }
    }catch (\Exception $e) {
        // Log any other exceptions
        \Log::error('An error occurred: ' . $e->getMessage());

        return response()->json([
            'status' => 'Error',
            'message' => $e->getMessage(),
        ], 500);
    }
  }

  public function checkPinCode(Request $request)
  {
    try {
      $check_postal_code = Pincode::where('pincode', $request->pin_code)->first();
      if(empty($check_postal_code))  {
        $response['result']  = false;
        $response['message'] = 'Pincode is not valid!';
      }else {
        $response['result']  = true;
        $response['message'] = 'Valid pincode!';
      }
      return $response;
    }catch (\Exception $e) {
        // Log any other exceptions
        \Log::error('An error occurred: ' . $e->getMessage());

        return response()->json([
            'status' => 'Error',
            'message' => $e->getMessage(),
        ], 500);
    }
  }

  public function createCustomer(Request $request)
  {
    try {
      $request->merge(['phone' => '+91' . $request->phone]);
      if ($request->gstin) {
          $request->validate([
              'warehouse_id' => 'required|exists:warehouses,id',
              'manager_id'   => 'required|exists:staff,user_id',
              'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
              'company_name' => 'required|string|max:255',
              'address'      => 'required|string',
              'pincode'      => 'required',
              'email'        => 'unique:users|email',
              'phone'       => 'required|unique:users,phone|regex:/^\+91[0-9]{10}$/',
              'gstin'        => 'required|string|size:15|unique:users',
              'aadhar_card'  => 'required|string|size:12|unique:users',
              'gst_data'  => 'required',
              'discount'  => 'required|numeric|max:24',
          ]);
          // Debug user data before creation
          $data = $request->all();
          \Log::info('User Data: ' . json_encode($data));

          $pincode      = Pincode::where('pincode', $data['pincode'])->first();
          $lastcustomer = User::where('user_type', 'customer')->where('warehouse_id', $data['warehouse_id'])->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
          if ($lastcustomer) {
            $party_code = 'OPEL0' . $data['warehouse_id'] . str_pad(((int) substr($lastcustomer->party_code, -5) + 1), 5, '0', STR_PAD_LEFT);
          } else {
            $party_code = 'OPEL0' . $data['warehouse_id'] . '00001';
          }
          
          $getManager = User::where('id',$data['manager_id'])->first();
          $data['password'] =  substr($getManager->name, 0, 1).substr($data['phone'], -4);
          
          $user = User::create([
              'name'                   => $data['name'],
              'company_name'           => $data['company_name'],
              'phone'                  => $data['phone'],
              'email'                  => $data['email'],
              'password'               => Hash::make($data['password']),
              'address'                => $data['address'],
              'gstin'                  => $data['gstin'],
              'aadhar_card'            => $data['aadhar_card'],
              'postal_code'            => $data['pincode'],
              'city'                   => $pincode->city,
              'state'                  => $pincode->state,
              'country'                => 'India',
              'warehouse_id'           => $data['warehouse_id'],
              'manager_id'             => $getManager->id,
              'party_code'             => $party_code,
              'virtual_account_number' => $party_code,
              'discount'               => $data['discount'],
              'user_type'              => 'customer',
              'banned'                 => false,
              'gst_data'               => json_encode($request->input('gst_data')),
              'verification_code'      => $data['password'],
              'email_verified_at'      => date("Y-m-d H:i:s"),
          ]);
          
          // Convert JSON to array          
          $gstDataArray = $request->input('gst_data');          
          $gstDataArray = $gstDataArray['taxpayerInfo'];          
          if(isset($gstDataArray['adadr']) AND count($gstDataArray['adadr']) > 0){
            foreach($gstDataArray['adadr'] as $key=>$value){
              $address = $value['addr'];
              $pincode = Pincode::where('pincode', $address['pncd'])->first();
              $city = City::where('name', $pincode->city)->first();
              if(!isset($city->id)){
                $city= 0;
              }else{
                $city = $city->id;
              }
              $state = State::where('name', $pincode->state)->first();
              $cmp_address = $address['bnm']. ', '.$address['st'] . ', ' .$address['loc'] . ', ' .$address['bno'] . ', ' .$address['dst'];
              Address::create([
                  'user_id'=>$user->id,
                  'company_name'=> $gstDataArray['tradeNam'],
                  'address' => $cmp_address,
                  'gstin'=> $gstDataArray['gstin'],
                  'country_id' => '101',
                  'state_id'=>$state->id,
                  'city_id'=> $city,
                  'longitude'=> $address['lt'],
                  'latitude'=> $address['lg'],
                  'postal_code'=> $address['pncd'],
                  'phone'=> $data['phone'],
                  'set_default'=> 0
              ]);
            }
          }
          $pincode = Pincode::where('pincode', $gstDataArray['pradr']['addr']['pncd'])->first();
          $city = City::where('name', $pincode->city)->first();
          if(!isset($city->id)){
            $city= 0;
          }else{
            $city = $city->id;
          }
          $state = State::where('name', $pincode->state)->first();
          $cmp_address = $gstDataArray['pradr']['addr']['bnm']. ', '.$gstDataArray['pradr']['addr']['st'] . ', ' .$gstDataArray['pradr']['addr']['loc'] . ', ' .$gstDataArray['pradr']['addr']['bno'] . ', ' .$gstDataArray['pradr']['addr']['dst'];
          $address = Address::create([
              'user_id'=>$user->id,
              'company_name'=> $gstDataArray['tradeNam'],
              'address' => $cmp_address,
              'gstin'=> $gstDataArray['gstin'],
              'country_id' => '101',
              'state_id'=>$state->id,
              'city_id'=> $city,
              'longitude'=> $gstDataArray['pradr']['addr']['lt'],
              'latitude'=> $gstDataArray['pradr']['addr']['lg'],
              'postal_code'=> $gstDataArray['pradr']['addr']['pncd'],
              'phone'=> $data['phone'],
              'set_default'=> 1
          ]);
          return response()->json([
              'status' => true,
              'message' => 'Customer added succesfully',
          ], 200);
      } else {
          $request->validate([
              'warehouse_id' => 'required|exists:warehouses,id',
              'manager_id'   => 'required|exists:staff,user_id',
              'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
              'company_name' => 'required|string|max:255',
              'email'        => 'required|unique:users|email',
              'phone'       => 'required|unique:users,phone|regex:/^\+91[0-9]{10}$/',
              'address'      => 'required|string',
              'aadhar_card'  => 'required|string|size:12',
              'pincode'  => 'required|numeric|digits:6|exists:pincodes,pincode',
          ]);
          $data = $request->all();

          $pincode      = Pincode::where('pincode', $data['pincode'])->first();
          $lastcustomer = User::where('user_type', 'customer')->where('warehouse_id', $data['warehouse_id'])->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
          if ($lastcustomer) {
            $party_code = 'OPEL0' . $data['warehouse_id'] . str_pad(((int) substr($lastcustomer->party_code, -5) + 1), 5, '0', STR_PAD_LEFT);
          } else {
            $party_code = 'OPEL0' . $data['warehouse_id'] . '00001';
          }

          $getManager = User::where('id',$data['manager_id'])->first();
          $data['password'] =  substr($getManager->name, 0, 1).substr($data['phone'], -4);

          $user = User::create([
            'name'                   => $data['name'],
            'company_name'           => $data['company_name'],
            'phone'                  => $data['phone'],
            'email'                  => $data['email'],
            'password'               => Hash::make($data['password']),
            'address'                => $data['address'],
            'gstin'                  => null,
            'aadhar_card'            => $data['aadhar_card'],
            'postal_code'            => $data['pincode'],
            'city'                   => $pincode->city,
            'state'                  => $pincode->state,
            'country'                => 'India',
            'warehouse_id'           => $data['warehouse_id'],
            'manager_id'             => $data['manager_id'],
            'party_code'             => $party_code,
            'virtual_account_number' => $party_code,
            'user_type'              => 'customer',
            'discount'               => $data['discount'],
            'discount'               => $data['discount'],
            'verification_code'      => $data['password'],
            'email_verified_at'      => date("Y-m-d H:i:s"),
            'banned'                 => false,
          ]);
          $city = City::where('name', $pincode->city)->get()->toArray();              
          if(!isset($city->id)){
            $city= 0;
          }else{
            $city = $city->id;
          }
          $state = State::where('name', $pincode->state)->first();
          $cmp_address = $data['address'];
          
          $address = Address::create([
              'user_id'=>$user->id,
              'company_name'=> $data['company_name'],
              'address' => $cmp_address,
              'gstin'=> null,
              'country_id' => '101',
              'state_id'=>$state->id,
              'city_id'=> $city,
              'longitude'=> null,
              'latitude'=> null,
              'postal_code'=> $data['pincode'],
              'phone'=> $data['phone'],
              'set_default'=> 1
          ]);
          return response()->json([
              'status' => true,
              'message' => 'Customer added succesfully',
          ], 200);
      }
    }catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'result' => false,
            'errors' => $e->errors(),
        ], 422);
    }catch (\Exception $e) {
        // Log any other exceptions
        \Log::error('An error occurred: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
  }

  public function registrationCustomer(Request $request)
  {
    try {
      $request->merge(['phone' => '+91' . $request->phone]);
      if ($request->gstin) {
          $request->validate([
              'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
              'company_name' => 'required|string|max:255',
              'address'      => 'required|string',
              'pincode'      => 'required',
              'email'        => 'unique:users|email',
              'phone'       => 'required|unique:users,phone|regex:/^\+91[0-9]{10}$/',
              'gstin'        => 'required|string|size:15|unique:users',
              //'aadhar_card'  => 'required|string|size:12|unique:users',
              'gst_data'  => 'required',
              //'discount'  => 'required|numeric|max:24',
          ]);
          // Debug user data before creation
          $data = $request->all();
          \Log::info('User Data: ' . json_encode($data));

          $pincode = Pincode::where('pincode', $request->pincode)->first();
        
          $state = State::where('name', $pincode->state)->first();
          $warehouse = Warehouse::whereRaw('FIND_IN_SET(?, service_states)', [$state->id])->first();
          
          $lastcustomer = User::where('user_type', 'customer')->where('warehouse_id', $warehouse->id)->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
          if ($lastcustomer) {
            $party_code = 'OPEL0' . $warehouse->id . str_pad(((int) substr($lastcustomer->party_code, -5) + 1), 5, '0', STR_PAD_LEFT);
          } else {
            $party_code = 'OPEL0' . $warehouse->id . '00001';
          }
          $data['party_code'] = $party_code;
          $data['warehouse_id'] = $warehouse->id;
          $data['email_verified_at'] = date("Y-m-d H:i:s");
          
          $user = User::create([
              'name'                   => $data['name'],
              'company_name'           => $data['company_name'],
              'phone'                  => $data['phone'],
              'email'                  => $data['email'],
              //'password'               => Hash::make($data['password']),
              'address'                => $data['address'],
              'gstin'                  => $data['gstin'],
              'aadhar_card'            => $data['aadhar_card'],
              'postal_code'            => $data['pincode'],
              'city'                   => $pincode->city,
              'state'                  => $pincode->state,
              'country'                => 'India',
              'warehouse_id'           => $data['warehouse_id'],
              //'manager_id'             => $getManager->id,
              'party_code'             => $data['party_code'],
              'virtual_account_number' => $data['party_code'],
              //'discount'               => $data['discount'],
              'user_type'              => 'customer',
              'banned'                 => true,
              'gst_data'               => json_encode($request->input('gst_data')),
              'email_verified_at'      => date("Y-m-d H:i:s"),
              'banned'      =>'1',
          ]);

          // Convert JSON to array          
          $gstDataArray = $request->input('gst_data');          
          $gstDataArray = $gstDataArray['taxpayerInfo'];
          if(isset($gstDataArray['adadr']) AND count($gstDataArray['adadr']) > 0){
            foreach($gstDataArray['adadr'] as $key=>$value){
              $address = $value['addr'];
              $pincode = Pincode::where('pincode', $address['pncd'])->first();
              $city = City::where('name', $pincode->city)->first();
              if(!isset($city->id)){
                $city= 0;
              }else{
                $city = $city->id;
              }
              $state = State::where('name', $pincode->state)->first();
              $cmp_address = $address['bnm']. ', '.$address['st'] . ', ' .$address['loc'] . ', ' .$address['bno'] . ', ' .$address['dst'];
              Address::create([
                  'user_id'=>$user->id,
                  'company_name'=> $gstDataArray['tradeNam'],
                  'address' => $cmp_address,
                  'gstin'=> $gstDataArray['gstin'],
                  'country_id' => '101',
                  'state_id'=>$state->id,
                  'city_id'=> $city,
                  'longitude'=> $address['lt'],
                  'latitude'=> $address['lg'],
                  'postal_code'=> $address['pncd'],
                  'phone'=> $data['phone'],
                  'set_default'=> 0
              ]);
            }
          }
          $pincode = Pincode::where('pincode', $gstDataArray['pradr']['addr']['pncd'])->first();
          $city = City::where('name', $pincode->city)->first();
          if(!isset($city->id)){
            $city= 0;
          }else{
            $city = $city->id;
          }
          $state = State::where('name', $pincode->state)->first();
          $cmp_address = $gstDataArray['pradr']['addr']['bnm']. ', '.$gstDataArray['pradr']['addr']['st'] . ', ' .$gstDataArray['pradr']['addr']['loc'] . ', ' .$gstDataArray['pradr']['addr']['bno'] . ', ' .$gstDataArray['pradr']['addr']['dst'];
          $address = Address::create([
              'user_id'=>$user->id,
              'company_name'=> $gstDataArray['tradeNam'],
              'address' => $cmp_address,
              'gstin'=> $gstDataArray['gstin'],
              'country_id' => '101',
              'state_id'=>$state->id,
              'city_id'=> $city,
              'longitude'=> $gstDataArray['pradr']['addr']['lt'],
              'latitude'=> $gstDataArray['pradr']['addr']['lg'],
              'postal_code'=> $gstDataArray['pradr']['addr']['pncd'],
              'phone'=> $data['phone'],
              'set_default'=> 1
          ]);
          return response()->json([
              'status' => true,
              'message' => 'Customer added succesfully',
          ], 200);
      } else {
          $request->validate([
              //'warehouse_id' => 'required|exists:warehouses,id',
              //'manager_id'   => 'required|exists:staff,user_id',
              'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
              'company_name' => 'required|string|max:255',
              'email'        => 'required|unique:users|email',
              'phone'       => 'required|unique:users,phone|regex:/^\+91[0-9]{10}$/',
              'address'      => 'required|string',
              'aadhar_card'  => 'required|string|size:12',
              'pincode'  => 'required|numeric|digits:6|exists:pincodes,pincode',
          ]);

          // Debug user data before creation
          $data = $request->all();
          \Log::info('User Data: ' . json_encode($data));

          $pincode = Pincode::where('pincode', $request->pincode)->first();
        
          $state = State::where('name', $pincode->state)->first();
          $warehouse = Warehouse::whereRaw('FIND_IN_SET(?, service_states)', [$state->id])->first();
          
          $lastcustomer = User::where('user_type', 'customer')->where('warehouse_id', $warehouse->id)->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
          if ($lastcustomer) {
            $party_code = 'OPEL0' . $warehouse->id . str_pad(((int) substr($lastcustomer->party_code, -5) + 1), 5, '0', STR_PAD_LEFT);
          } else {
            $party_code = 'OPEL0' . $warehouse->id . '00001';
          }
          $data['party_code'] = $party_code;
          $data['warehouse_id'] = $warehouse->id;
          $data['email_verified_at'] = date("Y-m-d H:i:s");

          $user = User::create([
            'name'                   => $data['name'],
            'company_name'           => $data['company_name'],
            'phone'                  => $data['phone'],
            'email'                  => $data['email'],
            //'password'               => Hash::make($data['password']),
            'address'                => $data['address'],
            'gstin'                  => null,
            'aadhar_card'            => $data['aadhar_card'],
            'postal_code'            => $data['pincode'],
            'city'                   => $pincode->city,
            'state'                  => $pincode->state,
            'country'                => 'India',
            'warehouse_id'           => $data['warehouse_id'],
            'party_code'             => $data['party_code'],
            'virtual_account_number' => $data['party_code'],
            'user_type'              => 'customer',
            'banned'                 => true,
            'email_verified_at'      => date("Y-m-d H:i:s")
          ]);
    
          $city = City::where('name', $pincode->city)->first();
          if(!isset($city->id)){
            $city= 0;
          }else{
            $city = $city->id;
          }
          $state = State::where('name', $pincode->state)->first();
          $cmp_address = $data['address'];
          $address = Address::create([
              'user_id'=>$user->id,
              'company_name'=> $data['company_name'],
              'address' => $cmp_address,
              'gstin'=> null,
              'country_id' => '101',
              'state_id'=>$state->id,
              'city_id'=> $city,
              'longitude'=> null,
              'latitude'=> null,
              'postal_code'=> $data['pincode'],
              'phone'=> $data['phone'],
              'set_default'=> 1
          ]);
          return response()->json([
              'status' => true,
              'message' => 'Customer added succesfully',
          ], 200);
      }
    }catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'result' => false,
            'errors' => $e->errors(),
        ], 422);
    }catch (\Exception $e) {
        // Log any other exceptions
        \Log::error('An error occurred: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
  }

}
