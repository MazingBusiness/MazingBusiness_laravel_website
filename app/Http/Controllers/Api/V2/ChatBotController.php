<?php

namespace App\Http\Controllers\Api\V2;

use Cache;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Hash;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Pincode;
use App\Models\Address;
use App\Models\City;
use App\Models\State;


class ChatBotController extends Controller {
  public function userStatus(Request $request) {
    try {
        // Define validation rules
        $rules = [
            // 'phone_number' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
            'phone_number' => 'required',
        ];
        $validatedData = $request->validate($rules);
        $phoneNumber = $validatedData['phone_number'];
        $getUserDetails = User::select('name', 'phone', 'email', 'user_type')
          ->where('phone', $phoneNumber)
          ->where(function ($query) {
              $query->where('user_type', 'customer')
                    ->orWhere('user_type', 'staff');
          })
          ->first();
        if($getUserDetails !== null){          
          return response()->json(['data' => $getUserDetails, 'success' => 1, 'message' => 'Valid user.']);
        }else{
          return response()->json(['data' => [], 'success' => 0, 'message' => 'Not a valid customer.']);
        }
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'message' => 'Invalid phone number.', 'errors' => $e->errors()], 422);
    }
  }

  public function userCredential(Request $request) {
    try {
      // Define validation rules
        $rules = [
            // 'phone_number' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
            'phone_number' => 'required',
        ];
        $validatedData = $request->validate($rules);
        $phoneNumber = $validatedData['phone_number'];
        $getUserDetails = User::select('name','phone','email','verification_code')->where('phone',$phoneNumber)->where('user_type','customer')->first();
        if($getUserDetails !== null){
          $getUserDetails->makeHidden('verification_code');
          $getUserDetails->login_password = $getUserDetails->verification_code;
          return response()->json(['data' => $getUserDetails, 'success' => 1, 'message' => 'Valid user.']);
        }else{
          return response()->json(['data' => [], 'success' => 0, 'message' => 'No a valid user.']);
        }
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'message' => 'Invalid phone number.', 'errors' => $e->errors()], 422);
    }
  }

  public function userAadhaarCard(Request $request) {
    try {
      // Define validation rules
        $rules = [
            'aadhar_card' => 'required|numeric|digits:12',
        ];
        $validatedData = $request->validate($rules);
        $aadhar_card = $validatedData['aadhar_card'];
        $getUserDetails = User::select('name','phone','email')->where('aadhar_card',$aadhar_card)->where('user_type','customer')->first();
        if($getUserDetails !== null){
          return response()->json(['data' => $getUserDetails, 'success' => 1, 'message' => 'Valid user.']);
        }else{
          return response()->json(['data' => [], 'success' => 0, 'message' => 'No a valid user.']);
        }
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'message' => 'Invalid aadhar card number.', 'errors' => $e->errors()], 422);
    }
  }
  
  public function userRegistrationUsingAadhaarCard(Request $request) {
    try {
      // Define validation rules
      $request->validate([
            'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
            'company_name' => 'required|string|max:255',
            'email'        => 'required|unique:users|email',
            'phone'        => 'required|unique:users,phone',
            'aadhar_card'  => 'required|string|digits:12|unique:users,aadhar_card',
            'address'      => 'required|string',
            'address_2'      => 'required|string',
            'city'      => 'required|string',
            // 'country'      => 'required|exists:countries,name',
            // 'state'        => 'required|exists:states,id',
            // 'city'         => 'required|exists:cities,id',
            'postal_code'  => 'required|numeric|digits:6|exists:pincodes,pincode',
        ]);
        // Debug user data before creation
        $user = $request->all();      
        // \Log::info('User Data: ' . json_encode($user));     
        $pincode = Pincode::where('pincode', $request->postal_code)->first();
        
        $state = State::where('name', $pincode->state)->first();
        $warehouse = Warehouse::whereRaw('FIND_IN_SET(?, service_states)', [$state->id])->first();
        if($warehouse->id == 3 OR $warehouse->id == 5){
          $warehouse->id = 6;
        }
        $lastcustomer = User::where('user_type', 'customer')->where('warehouse_id', $warehouse->id)->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        if ($lastcustomer) {
          $party_code = 'OPEL0' . $warehouse->id . str_pad(((int) substr($lastcustomer->party_code, -5) + 1), 5, '0', STR_PAD_LEFT);
        } else {
          $party_code = 'OPEL0' . $warehouse->id . '00001';
        }
        $user['party_code'] = $party_code;
        $user['warehouse_id'] = $warehouse->id;     
        $user['email_verified_at'] = date("Y-m-d H:i:s");

        // Create user
        $user = $this->createUser($user);

        // If user creation is successful, modify the response status
        if ($user) {
          return response()->json(['success' => 1, 'message' => $user]);
        } else {
          return response()->json(['success' => 0, 'message' => 'Didn\'t create user.']);
        }
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'errors' => $e->errors()], 422);
    }
  }

  public function getHeadManagerDetailsUsingPincode(Request $request) {
    try {
      // Define validation rules
        $rules = [
            'postal_code' => 'required|numeric|digits:6',
        ];
        $validatedData = $request->validate($rules);
        $pincode = Pincode::where('pincode', $validatedData['postal_code'])->first();
        if($pincode != null){
          $state = State::where('name', $pincode->state)->first();
          $warehouse = Warehouse::whereRaw('FIND_IN_SET(?, service_states)', [$state->id])->first();
          if($warehouse != null){
            $getUserDetails = User::select('name', DB::raw("REPLACE(phone, '+91', '') as phone"), 'email', 'user_type')
              ->where('warehouse_id', $warehouse->id)
              ->where(function ($query) {
                  $query->where('id', '180')
                        ->orWhere('id', '25606')
                        ->orWhere('id', '169');
              })
              ->first();
            if($getUserDetails !== null){
              return response()->json(['data' => $getUserDetails, 'success' => 1, 'message' => 'Valid head manager.']);
            }else{
              $getUserDetails = User::select('name', DB::raw("REPLACE(phone, '+91', '') as phone"), 'email', 'user_type')
              // ->where('warehouse_id', '1')
              ->where(function ($query) {
                  $query->where('id', '180')
                        ->orWhere('id', '25606')
                        ->orWhere('id', '169');
              })
              ->first();
              return response()->json(['data' => $getUserDetails, 'success' => 1, 'message' => 'Valid head manager.']);
              // return response()->json(['data' => [], 'success' => 0, 'message' => 'Not a valid head manager.']);
            }
          }else{
            return response()->json(['data' => [], 'success' => 0, 'message' => 'Warehouse didn\'t get.']);
          }
        }else{
          return response()->json(['data' => [], 'success' => 0, 'message' => 'Invalid postal code for our syatem.']);
        }       
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'message' => 'Invalid Postal code number.', 'errors' => $e->errors()], 422);
    }
  }

  public function getUnderHeadManagerDetailsUsingPhone(Request $request) {
    try {
      // Define validation rules
        // Define validation rules
        $rules = [
          // 'phone_number' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
          'phone_number' => 'required',
      ];
      $validatedData = $request->validate($rules);
      $phoneNumber = $validatedData['phone_number'];

      // $getUserDetails = User::select('name', DB::raw("REPLACE(phone, '+91', '') as phone"), 'email', 'user_type', 'warehouse_id')
      $getUserDetails = User::select('name', 'phone', 'email', 'user_type', 'warehouse_id')
          ->where('phone', $phoneNumber)
          ->where('user_type', 'staff')
          ->where(function ($query) {
              $query->where('id', '180')
                      ->orWhere('id', '25606')
                      ->orWhere('id', '169');
          })->first();
      if($getUserDetails !== null){
        $getManagerDetails = User::select('id', 'name', 'phone', 'email', 'user_type', 'warehouse_id')
          // ->where('warehouse_id', $getUserDetails->warehouse_id)
          ->where('user_type', 'staff')
          ->whereNotIn('id', [180, 25606, 169])
          ->get();  
          
        // Initialize the content string
        $content = '';
        $snArray = array();
        // Loop through each user and format the string
        foreach ($getManagerDetails as $index => $user) {
            $sn = $index + 1;
            $snArray[]=$user->phone;
            $content .= "SN: {$sn}\n Name: {$user->name} \n  Phone: {$user->phone} \n\n ";
        }

        // Extract the ids from the collection
        // $ids = $getManagerDetails->pluck('id')->toArray();

        // Prepare the response array
        $response = [
            "content" => trim($content), // Trim any trailing space
            "json" => $snArray,
            "success" => 1
        ];

        // Return the response as JSON
        return response()->json($response);

        // return response()->json(['data' => $getManagerDetails, 'success' => 1, 'message' => 'Manager List.']);
      }else{
        return response()->json(['data' => [], 'success' => 0, 'message' => 'No Manager Found.']);
      }
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'message' => 'Invalid Phone number.', 'errors' => $e->errors()], 422);
    }
  }

  public function userDetailsUsingphoneNumber(Request $request) {
    try {
        // Define validation rules
        $rules = [
            // 'phone_number' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
            'phone_number' => 'required'
        ];
        $validatedData = $request->validate($rules);
        $phoneNumber = $validatedData['phone_number'];
        $getUserDetails = User::where('phone', $phoneNumber)
          ->where(function ($query) {
              $query->where('user_type', 'customer')
                    ->orWhere('user_type', 'staff');
          })
          ->first();
        if($getUserDetails !== null){          
          return response()->json(['data' => $getUserDetails, 'success' => 1, 'message' => 'Valid user.']);
        }else{
          return response()->json(['data' => [], 'success' => 0, 'message' => 'Not a valid customer.']);
        }
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'message' => 'Invalid phone number.', 'errors' => $e->errors()], 422);
    }
  }

  public function gatValidation(Request $request) {
    try {
      $gstin = $request->input('gstin');
      $key_secret = "H50csEwe27SjLf7J2qP9Av28uOm2";

      // Call the external GST verification API
      $response = Http::withHeaders([
          'Content-Type' => 'application/json'
      ])->post('https://appyflow.in/api/verifyGST', [
          'key_secret' => $key_secret,
          'gstNo' => $gstin
      ]);

      // Check if the response is successful
      if ($response->successful()) {
          $data = $response->json();
          // print_r($data['taxpayerInfo']['pradr']['addr']); 
          $address =  $data['taxpayerInfo']['pradr']['addr']['bno'].', '.$data['taxpayerInfo']['pradr']['addr']['st'].', '.$data['taxpayerInfo']['pradr']['addr']['dst'].', '.$data['taxpayerInfo']['pradr']['addr']['locality'].', '.$data['taxpayerInfo']['pradr']['addr']['stcd'].', '.$data['taxpayerInfo']['pradr']['addr']['pncd'];
          //$data['address'] = $address;
          if (isset($data['error'])) {
              return response()->json(['success' => 0, 'message' => 'Invalid GST'], 400);
          } 
          return response()->json(['success' => 1, 'message' => 'Valid GST','data'=>$data,'address'=>$address], 200);
      }

      // Handle errors
      return response()->json(['error' => 'Unable to verify GST at this time'], 500);
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'message' => 'Invalid GST.', 'errors' => $e->errors()], 422);
    }
  }

  public function userRegistrationUsingGST(Request $request) {
    try {
      // Define validation rules
      $request->validate([
            'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
            'company_name' => 'required|string|max:255',
            'address'      => 'required|string',
            'city'      => 'required|string',
            'postal_code'  => 'required',
            'email'        => 'unique:users|email',
            'phone'       => 'required|unique:users,phone',
            // 'aadhar_card'  => 'required|string|digits:12|unique:users,aadhar_card',
            'gstin'        => 'required|string|size:15',
            'gst_data'        => 'required'
        ]);
        // Debug user data before creation
        $user = $request->all();      
        // \Log::info('User Data: ' . json_encode($user));     
        $pincode = Pincode::where('pincode', $request->postal_code)->first();
        
        $state = State::where('name', $pincode->state)->first();
        $warehouse = Warehouse::whereRaw('FIND_IN_SET(?, service_states)', [$state->id])->first();
        if($warehouse->id == 3 OR $warehouse->id == 5){
          $warehouse->id = 6;
        }
        $lastcustomer = User::where('user_type', 'customer')->where('warehouse_id', $warehouse->id)->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        if ($lastcustomer) {
          $party_code = 'OPEL0' . $warehouse->id . str_pad(((int) substr($lastcustomer->party_code, -5) + 1), 5, '0', STR_PAD_LEFT);
        } else {
          $party_code = 'OPEL0' . $warehouse->id . '00001';
        }
        $user['aadhar_card'] = '';
        $user['party_code'] = $party_code;
        $user['warehouse_id'] = $warehouse->id;     
        $user['email_verified_at'] = date("Y-m-d H:i:s");

        // Create user
        $user = $this->createUser($user);

        // If user creation is successful, modify the response status
        if ($user) {
          return response()->json(['success' => 1, 'message' => $user]);
        } else {
          return response()->json(['success' => 0, 'message' => 'Didn\'t create user.']);
        }
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'errors' => $e->errors()], 422);
    }
  }

  public function managerApproval(Request $request) {
    try {
        // Define validation rules
        $rules = [
            'discount' => 'required|numeric|max:24',
            'manager_phone_number' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
            'user_phone_number' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
        ];
        $validatedData = $request->validate($rules);
        $managerPhoneNumber = $validatedData['manager_phone_number'];
        $userPhoneMumber = $validatedData['user_phone_number'];

        $data = array();
        $getUser = User::where('phone',$userPhoneMumber)->where('user_type','customer')->first();
        $getManager = User::where('phone',$managerPhoneNumber)->where('user_type','staff')->first();
        
        if($getUser === null){
          return response()->json(['success' => 0, 'message' => 'Didn\'t get user.']);
        }elseif($getManager === null){
          return response()->json(['success' => 0, 'message' => 'Didn\'t get manager.']);
        }else{
          $user = User::findOrFail($getUser->id);
          $user->password = Hash::make(substr($getManager->name, 0, 1).substr($getUser->phone, -4));
          $user->verification_code = substr($getManager->name, 0, 1).substr($getUser->phone, -4);
          $user->manager_id = $getManager->manager_id;
          $user->discount = $validatedData['discount'];
          $user->banned = 0;
          $user->save();          
          return response()->json(['success' => 1, 'message' => 'Successfully approved.']);
        }
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'message' => 'Invalid Input.', 'errors' => $e->errors()], 422);
    }
  }

  protected function createUser(array $data) {
  
    $pincode = Pincode::where('pincode', $data['postal_code'])->first();
    
    if (isset($data['gstin']) AND $data['gstin']!="") {
      try {
          $user = User::create([
              'name'                   => $data['name'],
              'company_name'           => $data['company_name'],
              'phone'                  => $data['phone'],
              'email'                  => $data['email'],
              //'password'               => Hash::make($data['password']),
              'address'                => $data['address'],
              'gstin'                  => $data['gstin'],
              'aadhar_card'            => $data['aadhar_card'],
              'postal_code'            => $data['postal_code'],
              'city'                   => $pincode->city,
              'state'                  => $pincode->state,
              'country'                => 'India',
              'warehouse_id'           => $data['warehouse_id'],
              //'manager_id'             => $getManager->id,
              'party_code'             => $data['party_code'],
              'ledgergroup'            => str_replace(' ','_',$data['name']).$data['party_code'],
              'virtual_account_number' => $data['party_code'],
              //'discount'               => $data['discount'],
              'user_type'              => 'customer',
              'banned'                 => true,
              'gst_data'               => $data['gst_data'],
              'email_verified_at'      => date("Y-m-d H:i:s"),
              'banned'      =>'1',
              'unapproved'      =>'0',
          ]);

          // Convert JSON to array          
          $gstDataArray = json_decode($data['gst_data'], true);          
          $gstDataArray = $gstDataArray['taxpayerInfo'];

          if(isset($gstDataArray['adadr']) AND count($gstDataArray['adadr']) > 0){
            $count = 10;
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
              $cmp_address2 = $address['bno'] . ', ' .$address['dst'];
              $party_code =$data['party_code'].$count;
              Address::create([
                  'user_id'=>$user->id,
                  'acc_code'=>$party_code,
                  'company_name'=> $gstDataArray['tradeNam'],
                  'address' => $cmp_address,
                  'address_2' => $cmp_address2,
                  'gstin'=> $gstDataArray['gstin'],
                  'country_id' => '101',
                  'state_id'=>$state->id,
                  'city_id'=> $city,
                  'city'=> $address['dst'],
                  'longitude'=> $address['lt'],
                  'latitude'=> $address['lg'],
                  'postal_code'=> $address['pncd'],
                  'phone'=> $data['phone'],
                  'set_default'=> 0
              ]);
              $count++;
            }
          }
          $pincode = Pincode::where('pincode', $gstDataArray['pradr']['addr']['pncd'])->first();
          $city = City::where('name', $pincode->city)->first();
          // if(!isset($city->id)){
          //   $city= 0;
          // }else{
          //   $city = $city->id;
          // }
          if(!isset($city->id)){
            $city = City::create([
              'name'                   => $pincode->city,
              'state_id'           => $state->id
            ]);
            $city = $city->id;
          }else{
            $city = $city->id;
          }
          $state = State::where('name', $pincode->state)->first();
          $cmp_address = $gstDataArray['pradr']['addr']['bnm']. ', '.$gstDataArray['pradr']['addr']['st'] . ', ' .$gstDataArray['pradr']['addr']['loc'] . ', ' .$gstDataArray['pradr']['addr']['bno'] . ', ' .$gstDataArray['pradr']['addr']['dst'];
          $cmp_address2 = $gstDataArray['pradr']['addr']['bno'] . ', ' .$gstDataArray['pradr']['addr']['dst'];
          $address = Address::create([
              'user_id'=>$user->id,
              'acc_code'=>$data['party_code'],
              'company_name'=> $gstDataArray['tradeNam'],
              'address' => $cmp_address,
              'address_2' => $cmp_address2,
              'gstin'=> $gstDataArray['gstin'],
              'country_id' => '101',
              'state_id'=>$state->id,
              'city_id'=> $city,
              'city'=> $gstDataArray['pradr']['addr']['dst'],
              'longitude'=> $gstDataArray['pradr']['addr']['lt'],
              'latitude'=> $gstDataArray['pradr']['addr']['lg'],
              'postal_code'=> $gstDataArray['pradr']['addr']['pncd'],
              'phone'=> $data['phone'],
              'set_default'=> 1
          ]);

      } catch (\Exception $e) {
          return response()->json(['success' => 0, 'errors' => $e->getMessage()], 422);
      }
    } else {      
      $user = User::create([
        'name'                   => $data['name'],
        'company_name'           => $data['company_name'],
        'phone'                  => $data['phone'],
        'email'                  => $data['email'],
        //'password'               => Hash::make($data['password']),
        'address'                => $data['address'],
        'gstin'                  => null,
        'aadhar_card'            => $data['aadhar_card'],
        'postal_code'            => $data['postal_code'],
        'city'                   => $pincode->city,
        'state'                  => $pincode->state,
        'country'                => 'India',
        'warehouse_id'           => $data['warehouse_id'],
        'party_code'             => $data['party_code'],
        'ledgergroup'            => str_replace(' ','_',$data['name']).$data['party_code'],
        'virtual_account_number' => $data['party_code'],
        'user_type'              => 'customer',
        'banned'                 => true,
        'unapproved'      =>'0',
        'email_verified_at'      => date("Y-m-d H:i:s")
      ]);

      
      $state = State::where('name', $pincode->state)->first();

      $city = City::where('name', $pincode->city)->first();
      if(!isset($city->id)){
        $city = City::create([
          'name'                   => $pincode->city,
          'state_id'           => $state->id
        ]);
        $city = $city->id;
      }else{
        $city = $city->id;
      }

      $cmp_address = $data['address'];
      $cmp_address2 = $data['address_2'];
      $address = Address::create([
          'user_id'=>$user->id,
          'acc_code'=>$data['party_code'],
          'company_name'=> $data['company_name'],
          'address' => $cmp_address,
          'address_2' => $cmp_address2,
          'gstin'=> null,
          'country_id' => '101',
          'state_id'=>$state->id,
          'city_id'=> $city,
          'city'=> $data['city'],
          'longitude'=> null,
          'latitude'=> null,
          'postal_code'=> $data['postal_code'],
          'phone'=> $data['phone'],
          'set_default'=> 1
      ]);
    }
    
    // Push User data to Salezing
    // $result=array();
    // $result['party_code']= $user->party_code;
    // $response = Http::withHeaders([
    //     'Content-Type' => 'application/json',
    // ])->post('https://mazingbusiness.com/api/v2/client-push', $result);

    return $user;
  }

  public function updateUnapprovedStatus(Request $request) {
    try {
        // Define validation rules
        // $rules = [
        //     'user_phone_number' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
        // ];
        // $validatedData = $request->validate($rules);
        // $userPhoneMumber = $validatedData['user_phone_number'];
        $userPhoneMumber = $request->user_phone_number;

        $data = array();
        $getUser = User::where('phone',$userPhoneMumber)->where('user_type','customer')->first();
        
        if($getUser === null){
          return response()->json(['success' => 0, 'message' => 'Didn\'t get user.']);
        }{
          $user = User::findOrFail($getUser->id);
          $user->unapproved = 1;
          $user->save();          
          return response()->json(['success' => 1, 'message' => 'Successfully Update.']);
        }
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'message' => 'Invalid Input.', 'errors' => $e->errors()], 422);
    }
  }

  public function userRegistrationByManagerUsingAadharCard(Request $request) {
    try {
      // Define validation rules
      $request->validate([
            'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
            'company_name' => 'required|string|max:255',
            'email'        => 'required|unique:users|email',
            'phone'        => 'required|unique:users,phone',
            'aadhar_card'  => 'required|string|digits:12|unique:users,aadhar_card',
            'address'      => 'required|string',
            'address_2'      => 'required|string',
            'city'      => 'required|string',
            'postal_code'  => 'required|numeric|digits:6|exists:pincodes,pincode',
            'managerPhoneNumber' => 'required',
            'discount' => 'required|numeric|max:24',
        ]);
        // Debug user data before creation
        $user = $request->all();  
        
        $managerDetails = User::where('phone',  $request->managerPhoneNumber)->where('user_type', 'staff')->first();

        // \Log::info('User Data: ' . json_encode($user));     
        $pincode = Pincode::where('pincode', $request->postal_code)->first();
        
        $state = State::where('name', $pincode->state)->first();
        $warehouse = Warehouse::whereRaw('FIND_IN_SET(?, service_states)', [$state->id])->first();
        if($warehouse->id == 3 OR $warehouse->id == 5){
          $warehouse->id = 6;
        }
        $lastcustomer = User::where('user_type', 'customer')->where('warehouse_id', $warehouse->id)->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        if ($lastcustomer) {
          $party_code = 'OPEL0' . $warehouse->id . str_pad(((int) substr($lastcustomer->party_code, -5) + 1), 5, '0', STR_PAD_LEFT);
        } else {
          $party_code = 'OPEL0' . $warehouse->id . '00001';
        }
        $user['party_code'] = $party_code;
        $user['password'] = Hash::make(substr($managerDetails->name, 0, 1).substr($request->phone, -4));
        $user['manager_id'] = $managerDetails->id;
        $user['warehouse_id'] = $warehouse->id;     
        $user['email_verified_at'] = date("Y-m-d H:i:s");

        // Create user
        $user = $this->createUserByManager($user);

        // If user creation is successful, modify the response status
        if ($user) {
          return response()->json(['success' => 1, 'message' => $user]);
        } else {
          return response()->json(['success' => 0, 'message' => 'Didn\'t create user.']);
        }
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'errors' => $e->errors()], 422);
    }
  }
  public function userRegistrationByManagerUsingGST(Request $request) {
    try {
      // Define validation rules
      $request->validate([
            'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
            'company_name' => 'required|string|max:255',
            'address'      => 'required|string',
            'postal_code'  => 'required',
            'city'      => 'required|string',
            'email'        => 'unique:users|email',
            'phone'       => 'required|unique:users,phone',
            'managerPhoneNumber' => 'required',
            'discount' => 'required|numeric|max:24',
            // 'aadhar_card'  => 'required|string|digits:12|unique:users,aadhar_card',
            'gstin'        => 'required|string|size:15',
            'gst_data'        => 'required'
        ]);
        $managerDetails = User::where('phone',  $request->managerPhoneNumber)->where('user_type', 'staff')->first();
        // Debug user data before creation
        $user = $request->all();      
        // \Log::info('User Data: ' . json_encode($user));     
        $pincode = Pincode::where('pincode', $request->postal_code)->first();
        
        $state = State::where('name', $pincode->state)->first();
        $warehouse = Warehouse::whereRaw('FIND_IN_SET(?, service_states)', [$state->id])->first();
        if($warehouse->id == 3 OR $warehouse->id == 5){
          $warehouse->id = 6;
        }
        $lastcustomer = User::where('user_type', 'customer')->where('warehouse_id', $warehouse->id)->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        if ($lastcustomer) {
          $party_code = 'OPEL0' . $warehouse->id . str_pad(((int) substr($lastcustomer->party_code, -5) + 1), 5, '0', STR_PAD_LEFT);
        } else {
          $party_code = 'OPEL0' . $warehouse->id . '00001';
        }
        $user['password'] = Hash::make(substr($managerDetails->name, 0, 1).substr($request->phone, -4));
        $user['aadhar_card'] = '';
        $user['manager_id'] = $managerDetails->id;
        $user['party_code'] = $party_code;
        $user['warehouse_id'] = $warehouse->id;     
        $user['email_verified_at'] = date("Y-m-d H:i:s");

        // Create user
        $user = $this->createUserByManager($user);

        // If user creation is successful, modify the response status
        if ($user) {
          return response()->json(['success' => 1, 'message' => $user]);
        } else {
          return response()->json(['success' => 0, 'message' => 'Didn\'t create user.']);
        }
    } catch (ValidationException $e) {
        // Return a custom response if validation fails
        return response()->json(['success' => 0, 'errors' => $e->errors()], 422);
    }
  }

  protected function createUserByManager(array $data) {
  
    $pincode = Pincode::where('pincode', $data['postal_code'])->first();
    
    if (isset($data['gstin']) AND $data['gstin']!="") {
      try {
          $user = User::create([
              'name'                   => $data['name'],
              'company_name'           => $data['company_name'],
              'phone'                  => $data['phone'],
              'email'                  => $data['email'],
              'password'               => $data['password'],
              'address'                => $data['address'],
              'gstin'                  => $data['gstin'],
              'aadhar_card'            => $data['aadhar_card'],
              'postal_code'            => $data['postal_code'],
              'city'                   => $pincode->city,
              'state'                  => $pincode->state,
              'country'                => 'India',
              'warehouse_id'           => $data['warehouse_id'],
              'manager_id'           => $data['manager_id'],
              'party_code'             => $data['party_code'],
              'ledgergroup'            => str_replace(' ','_',$data['name']).$data['party_code'],
              'virtual_account_number' => $data['party_code'],
              'discount'               => $data['discount'],
              'user_type'              => 'customer',
              'banned'                 => true,
              'gst_data'               => $data['gst_data'],
              'email_verified_at'      => date("Y-m-d H:i:s"),
              'banned'      =>'0',
              'unapproved'      =>'1',
          ]);

          // Convert JSON to array          
          $gstDataArray = json_decode($data['gst_data'], true);          
          $gstDataArray = $gstDataArray['taxpayerInfo'];
          if(isset($gstDataArray['adadr']) AND count($gstDataArray['adadr']) > 0){
            $count = 10;
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
              $cmp_address2 = $address['bno'] . ', ' .$address['dst'];
              $party_code =$data['party_code'].$count;
              Address::create([
                  'user_id'=>$user->id,
                  'acc_code'=>$party_code,
                  'company_name'=> $gstDataArray['tradeNam'],
                  'address' => $cmp_address,
                  'address_2' => $cmp_address2,
                  'gstin'=> $gstDataArray['gstin'],
                  'country_id' => '101',
                  'state_id'=>$state->id,
                  'city_id'=> $city,
                  'city'=>$address['dst'],
                  'longitude'=> $address['lt'],
                  'latitude'=> $address['lg'],
                  'postal_code'=> $address['pncd'],
                  'phone'=> $data['phone'],
                  'set_default'=> 0
              ]);
              $count++;
            }
          }
          $pincode = Pincode::where('pincode', $gstDataArray['pradr']['addr']['pncd'])->first();
          $city = City::where('name', $pincode->city)->first();
          // if(!isset($city->id)){
          //   $city= 0;
          // }else{
          //   $city = $city->id;
          // }
          if(!isset($city->id)){
            $city = City::create([
              'name'                   => $pincode->city,
              'state_id'           => $state->id
            ]);
            $city = $city->id;
          }else{
            $city = $city->id;
          }
          $state = State::where('name', $pincode->state)->first();
          $cmp_address = $gstDataArray['pradr']['addr']['bnm']. ', '.$gstDataArray['pradr']['addr']['st'] . ', ' .$gstDataArray['pradr']['addr']['loc'] . ', ' .$gstDataArray['pradr']['addr']['bno'] . ', ' .$gstDataArray['pradr']['addr']['dst'];
          $cmp_address2 = $gstDataArray['pradr']['addr']['bno'] . ', ' .$gstDataArray['pradr']['addr']['dst'];
          $address = Address::create([
              'user_id'=>$user->id,
              'acc_code'=>$data['party_code'],
              'company_name'=> $gstDataArray['tradeNam'],
              'address' => $cmp_address,
              'address_2' => $cmp_address2,
              'gstin'=> $gstDataArray['gstin'],
              'country_id' => '101',
              'state_id'=>$state->id,
              'city_id'=> $city,
              'city'=>$gstDataArray['pradr']['addr']['dst'],
              'longitude'=> $gstDataArray['pradr']['addr']['lt'],
              'latitude'=> $gstDataArray['pradr']['addr']['lg'],
              'postal_code'=> $gstDataArray['pradr']['addr']['pncd'],
              'phone'=> $data['phone'],
              'set_default'=> 1
          ]);

      } catch (\Exception $e) {
          return response()->json(['success' => 0, 'errors' => $e->getMessage()], 422);
      }
    } else {      
      $user = User::create([
        'name'                   => $data['name'],
        'company_name'           => $data['company_name'],
        'phone'                  => $data['phone'],
        'email'                  => $data['email'],
        'password'               => $data['password'],
        'address'                => $data['address'],
        'gstin'                  => null,
        'aadhar_card'            => $data['aadhar_card'],
        'postal_code'            => $data['postal_code'],
        'manager_id'             => $data['manager_id'],
        'city'                   => $pincode->city,
        'state'                  => $pincode->state,
        'country'                => 'India',
        'warehouse_id'           => $data['warehouse_id'],
        'party_code'             => $data['party_code'],
        'discount'               => $data['discount'],
        'ledgergroup'            => str_replace(' ','_',$data['name']).$data['party_code'],
        'virtual_account_number' => $data['party_code'],
        'user_type'              => 'customer',
        'banned'                 => true,
        'unapproved'      =>'0',
        'email_verified_at'      => date("Y-m-d H:i:s")
      ]);

      
      $state = State::where('name', $pincode->state)->first();
      $city = City::where('name', $pincode->city)->first();
      if(!isset($city->id)){
        $city = City::create([
          'name'                   => $pincode->city,
          'state_id'           => $state->id
        ]);
        $city = $city->id;
      }else{
        $city = $city->id;
      }

      $cmp_address = $data['address'];
      $address = Address::create([
          'user_id'=>$user->id,
          'acc_code'=>$data['party_code'],
          'company_name'=> $data['company_name'],
          'address' => $cmp_address,
          'gstin'=> null,
          'country_id' => '101',
          'state_id'=>$state->id,
          'city_id'=> $city,
          'longitude'=> null,
          'latitude'=> null,
          'postal_code'=> $data['postal_code'],
          'phone'=> $data['phone'],
          'set_default'=> 1
      ]);
    }
    
    // Push User data to Salezing
    // $result=array();
    // $result['party_code']= $user->party_code;
    // $response = Http::withHeaders([
    //     'Content-Type' => 'application/json',
    // ])->post('https://mazingbusiness.com/api/v2/client-push', $result);

    return $user;
  }
  
}
