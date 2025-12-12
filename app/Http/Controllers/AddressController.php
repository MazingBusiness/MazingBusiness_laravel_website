<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Pincode;
use App\Models\City;
use App\Models\State;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\ZohoController;

class AddressController extends Controller {
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index() {
    //
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create() {
    //
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request) {


    $address = new Address;
    if ($request->has('customer_id')) {
      $address->user_id = $request->customer_id;
    } else {
      $address->user_id = Auth::user()->id;
    }
    $user = User::where('id',$address->user_id)->first();
    $addressDetails = Address::where('user_id',$address->user_id)->orderBy('id','DESC')->first();
    $count = 10;
    if($addressDetails !== null){
      $party_code = $user->party_code;
      $lastAccCode = $addressDetails->acc_code;
      if($party_code == $lastAccCode){
        $address->acc_code = $lastAccCode.$count;
      }else{
        $prefix = "OPEL";
        $numericPart = str_replace($prefix, "", $lastAccCode);
        $incrementedNumericPart = (int)$numericPart + 1;
        $paddedIncrementedNumericPart = str_pad($incrementedNumericPart, strlen($numericPart), "0", STR_PAD_LEFT);
        $newAccCode = $prefix . $paddedIncrementedNumericPart;

        $address->acc_code = $newAccCode;
      }
    }else{
      $address->acc_code      = $user->party_code;
    }
    $address->address      = $request->address;
    $address->address_2      = $request->address_2;
    $address->company_name = $request->company_name;
    if ($request->gstin) {
      // try {
      //   $response = Http::post('https://appyflow.in/api/verifyGST', [
      //     'key_secret' => env('APPYFLOW_KEYSECRET'),
      //     'gstNo'      => $request->gstin,
      //   ]);
      // } catch (\Exception $e) {
      //   flash(translate('GSTIN could not be verified. Please try again.'))->error();
      //   return back();
      // }
      // if ($response->successful()) {
        // $data = json_decode($response->body(), true);
        // if ($data['error']) {
        //   flash(translate($data['message']))->error();
        //   return back();
        // } else {
        //   $address->gstin = $request->gstin;
        // }
      // }
      $pincode = Pincode::where('pincode', $request->postal_code)->first();
      $city = City::where('name', $pincode->city)->first();
      $state = State::where('name', $pincode->state)->first();
      if(!isset($city->id)){
        $city = City::create([
          'name'                   => $pincode->city,
          'state_id'           => $state->id
        ]);
      }else{
        $city = $city->id;
      }
      
      $country_id = 101;
      $city_id = $city ;

      $state_id = $state->id;
    }else{
      $country_id = $request->country_id;
      $city_id = $request->city_id;
      $state_id = $request->state_id;
    }
    $address->gstin       = $request->gstin;
    $address->country_id  = $country_id;
    $address->state_id    = $state_id;
    $address->city_id     = $city_id;
    $address->city        = $request->city;
    $address->longitude   = $request->longitude;
    $address->latitude    = $request->latitude;
    $address->postal_code = $request->postal_code;
    $address->phone       = $user->phone;
    $address->save();

    

    // ✅ Call Zoho function directly
    $zoho = new ZohoController();
    $res= $zoho->createNewCustomerInZoho($address->acc_code); // pass the party_code
    
    
    if ($request->has('customer_id')) {

     

      // Push User data to Salezing
      $result=array();
      $result['party_code']= $user->party_code;
      $response = Http::withHeaders([
          'Content-Type' => 'application/json',
      ])->post('https://mazingbusiness.com/api/v2/client-push', $result);



    }

    return back();
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function show($id) {
    //
  }

  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function edit($id) {
    $data['address_data'] = Address::findOrFail($id);
    $data['states']       = State::where('status', 1)->where('country_id', $data['address_data']->country_id)->get();
    $data['cities']       = City::where('status', 1)->where('state_id', $data['address_data']->state_id)->get();

    $returnHTML = view('frontend.partials.address_edit_modal', $data)->render();
    return response()->json(array('data' => $data, 'html' => $returnHTML));
//        return ;
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, $id) {
    $address = Address::findOrFail($id);

    $address->address      = $request->address;
    $address->address_2      = $request->address_2;
    $address->company_name = $request->company_name;
    if (!$address->gstin && $request->gstin) {
      try {
        $response = Http::post('https://appyflow.in/api/verifyGST', [
          'key_secret' => env('APPYFLOW_KEYSECRET'),
          'gstNo'      => $request->gstin,
        ]);
      } catch (\Exception $e) {
        flash(translate('GSTIN could not be verified. Please try again.'))->error();
        return back();
      }
      if ($response->successful()) {
        $data = json_decode($response->body(), true);
        if (isset($data['error'])) {
          flash(translate($data['message']))->error();
          return back();
        } else {
          $address->gstin = $request->gstin;
        }
      }
    }
    $address->country_id  = $request->country_id;
    $address->state_id    = $request->state_id;
    $address->city_id     = $request->city_id;
    $address->city        = $request->city;
    $address->longitude   = $request->longitude;
    $address->latitude    = $request->latitude;
    $address->postal_code = $request->postal_code;
    $address->phone       = $request->phone;
    $address->save();
    
    $user = User::where('id',$address->user_id)->first();
    // Push User data to Salezing
    $result=array();
    $result['party_code']= $user->party_code;
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->post('https://mazingbusiness.com/api/v2/client-push', $result);


    flash(translate('Address info updated successfully'))->success();
    return back();
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id) {
    $address = Address::findOrFail($id);
    if (!$address->set_default) {
      $address->delete();
      return back();
    }
    flash(translate('Default address can not be deleted'))->warning();
    return back();
  }

  public function getStates(Request $request) {
    $states = State::where('status', 1)->where('country_id', $request->country_id)->get();
    $html   = '<option value="">' . translate("Select State") . '</option>';

    foreach ($states as $state) {
      $html .= '<option value="' . $state->id . '">' . $state->name . '</option>';
    }

    echo json_encode($html);
  }

  public function getCities(Request $request) {
    $cities = City::where('status', 1)->where('state_id', $request->state_id)->get();
    $html   = '<option value="">' . translate("Select City") . '</option>';

    foreach ($cities as $row) {
      $html .= '<option value="' . $row->id . '">' . $row->getTranslation('name') . '</option>';
    }

    echo json_encode($html);
  }

  public function set_default($id) {
    foreach (Auth::user()->addresses as $key => $address) {
      $address->set_default = 0;
      $address->save();
    }
    $address              = Address::findOrFail($id);
    $address->set_default = 1;
    $address->save();

    return back();
  }
}
