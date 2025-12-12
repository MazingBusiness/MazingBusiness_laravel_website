<?php

namespace App\Http\Controllers;

use App\Mail\SecondEmailVerifyMailManager;
use App\Models\AffiliateConfig;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerPackage;
use App\Models\FlashDeal;
use App\Models\Order;
use App\Models\Page;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\ProductQuery;
use App\Models\Shop;
use App\Models\Staff;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Pincode;
use App\Models\Address;
use App\Models\City;
use App\Models\State;
use App\Models\RewardUser;
use App\Models\ProductWarehouse;
use App\Models\VariationProduct;
use App\Models\AttributeValue;
use App\Models\Attribute;

use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\OfferCombination;
use Carbon\Carbon;

use Auth;
use Cache;
use Cookie;
use Exception;
use Hash;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Utility\SendSMSUtility;
use App\Services\WhatsAppWebService;

use App\Http\Controllers\ZohoController;

if (!function_exists('debug_to_console')) {
  function debug_to_console($data) {
      $output = $data;
      if (is_array($output))
          $output = implode(',', $output);
    
      echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
  }
}

class HomeController extends Controller {
  /**
   * Show the application frontend home.
   *
   * @return \Illuminate\Http\Response
   */
  protected $WhatsAppWebService;

  public function index() {
    $featured_categories = Cache::rememberForever('featured_categories', function () {
      return Category::where('featured', 1)->get();
    });

    $todays_deal_products = Cache::rememberForever('todays_deal_products', function () {
      // return filter_products(Product::whereNotNull('photos')->where('published', 1)->where('todays_deal', '1'))->get();
      return filter_products(Product::where('published', 1)->where('todays_deal', '1'))->get();
    });

    // $newest_products = Cache::remember('newest_products', 3600, function () {
    //   // return filter_products(Product::whereNotNull('photos')->latest())->limit(12)->get();
    //   return filter_products(Product::latest()->where('part_no', '!=', '')
    //   ->where('current_stock', '!=', 0))->limit(12)->get();
    // });
    $newest_products = Product::latest()->where('part_no', '!=', '') ->where('current_stock', '!=', 0)->limit(12)->get();
    // print_r($newest_products);die;

    $category_menu = DB::table('products')
              ->leftJoin('category_groups', 'products.group_id', '=','category_groups.id' )
              ->where('category_groups.featured', 1)
              ->where('products.part_no','!=','')->where('products.current_stock','!=','0')
              ->orderBy('category_groups.name', 'asc')
              ->select('category_groups.*')
              ->distinct()
              ->get();
    // $sub_category_menu = DB::table('categories')
    //         ->where('category_group_id', $category->id)
    //         ->orderBy('name', 'asc')
    //         ->get();
    
    // images
    $slider_images = Cache::remember('slider_images', 3600, function () {
      $images = json_decode(get_setting('home_slider_images'), true);
      $links  = json_decode(get_setting('home_slider_links'), true);
      return array_map(function ($image, $link) {
        return [$image, $link];
      }, $images, $links);
    });


    // echo "<pre>";
    // print_r($featured_categories);
    // die();

    return view('frontend.index', compact('featured_categories', 'todays_deal_products', 'newest_products', 'slider_images', 'category_menu'));
  }

  public function login() {
    if (Auth::check()) {
      return redirect()->route('home');
    }
    return view('frontend.user_login');
  }

  public function registration() {
    try{
        // if (Auth::check()) {
        //   return redirect()->route('home');
        // }        
        // if ($request->has('referral_code') && addon_is_activated('affiliate_system')) {
        //   try {
        //     $affiliate_validation_time = AffiliateConfig::where('type', 'validation_time')->first();
        //     $cookie_minute             = 30 * 24;
        //     if ($affiliate_validation_time) {
        //       $cookie_minute = $affiliate_validation_time->value * 60;
        //     }
    
        //     Cookie::queue('referral_code', $request->referral_code, $cookie_minute);
        //     $referred_by_user = User::where('referral_code', $request->referral_code)->first();
    
        //     $affiliateController = new AffiliateController;
        //     $affiliateController->processAffiliateStats($referred_by_user->id, 1, 0, 0, 0);
        //   } catch (\Exception $e) {
        //   }
        // }
        $states = State::where('status', 1)->get();
        return view('frontend.user_registration',compact('states'));
      }catch (\Exception $e) {
        // Log any other exceptions
        \Log::error('An error occurred: ' . $e->getMessage());
        return response()->json([
            'status' => 'Error',
            'message' => 'An unexpected error occurred.',
        ], 500);
    }
    
  }

  public function register(Request $request) {
    try {
      // echo "<pre>"; print_r($request->all());die;
      // Append country code to the mobile number     

      $request->merge(['phone' => '+91' . $request->phone]);

      // Logging the mobile number for debugging
      \Log::info('Mobile: ' . $request->phone);

      // Validation rules based on the presence of GSTIN
      if ($request->gstin) {
          $request->validate([
              'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
              'company_name' => 'required|string|max:255',
              'address'      => 'required|string',
              'address2'     => 'required|string',
              'postal_code'  => 'required',
              'email'        => 'unique:users|email',
              'phone'       => 'required|unique:users,phone',
              'gstin'        => 'required|string|size:15|unique:users',
          ]);
      } else {
          $request->validate([
              // 'warehouse_id' => 'required|exists:warehouses,id',
              // 'manager_id'   => 'required|exists:staff,user_id',
              'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
              'company_name' => 'required|string|max:255',
              'email'        => 'required|unique:users|email',
              'phone'       => 'required|unique:users,phone',
              'aadhar_card'  => 'required|string|size:12',
              'address'     => 'required|string',
              'address2'     => 'required|string',
              // 'country'      => 'required|exists:countries,id',
              // 'state'        => 'required|exists:states,id',
              // 'city'         => 'required|exists:cities,id',
              'postal_code'  => 'required|numeric|digits:6',
              // 'postal_code'  => 'required|numeric|digits:6|exists:pincodes,pincode',
          ]);
      }
      // Debug user data before creation
      $user = $request->all();     
      
      // \Log::info('User Data: ' . json_encode($user));
      // \Log::info('Postal Code: ' . json_encode($request->postal_code));
      // \Log::info('address: ' . json_encode($request->address));
      // \Log::info('address2: ' . json_encode($request->address2));
      //if ($request->gstin) {        
       
        // $print_r($state);die;
        // \Log::info('User Data: ' . json_encode($state));

         $pincode = Pincode::where('pincode', $request->postal_code)->first();  
             
        // $state = State::where('name', $pincode->state)->first();
        if (!$pincode) {
          
         
            // Create a new state if it doesn't exist
            // $state = State::firstOrCreate(
            //     ['name' => $request->state],
            //     ['country_id' => 101, 'status' => 1] // Assuming country_id = 101 for India
            // );
            // Retrieve the state by its ID from the form
            $state = State::find($request->state); // Assuming `state` in the request contains the state ID

            // Check if the state exists, if not, return an error (optional, based on your logic)
            if (!$state) {
                return redirect()->back()->withErrors(['state' => 'State not found']);
            }
           
            // Create a new city if it doesn't exist, using default cost and status
            
            $city = City::firstOrCreate(
                ['name' => $request->city],
                ['state_id' => $state->id, 'cost' => 0.00, 'status' => 1]
            );
          
            // Create a new pincode entry
            $pincode = Pincode::create([
                'pincode' => $request->postal_code,
                'city' => $city->name,
                'state' => $state->name,
            ]);
           

            // $state = State::where('name', $pincode->state)->first();
           
        } else {
          
            // Retrieve the state based on the existing pincode's state
            $state = State::where('name', $pincode->state)->first();
        }
       
        $warehouse = Warehouse::whereRaw('FIND_IN_SET(?, service_states)', [$state->id])->first();

        // echo "<pre>";
        // print_r($warehouse);
        // die();
       
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
      //}     
      $user['email_verified_at'] = date("Y-m-d H:i:s");
      
      // Create user
      $user = $this->createUser($user);

      // Transport data insert
      $data = [
          'user_id' => $user->id,
          'party_code' => $user->party_code,
          'assigned_warehouse' => $warehouse->name,
          'city' => $user->city,
          'warehouse_id' => '1',
          'warehouse_name' => 'Kolkata',
          'preference' => '0',
          'rewards_percentage' => ''
      ];
      RewardUser::create($data);

      $data = [
          'user_id' => $user->id,
          'party_code' => $user->party_code,
          'assigned_warehouse' => $warehouse->name,
          'city' => $user->city,
          'warehouse_id' => '2',
          'warehouse_name' => 'Delhi',
          'preference' => '0',
          'rewards_percentage' => ''
      ];
      RewardUser::create($data);
      $data = [
          'user_id' => $user->id,
          'party_code' => $user->party_code,
          'assigned_warehouse' => $warehouse->name,
          'city' => $user->city,
          'warehouse_id' => '3',
          'warehouse_name' => 'Chennai',
          'preference' => '0',
          'rewards_percentage' => ''
      ];
      RewardUser::create($data);

      $data = [
          'user_id' => $user->id,
          'party_code' => $user->party_code,
          'assigned_warehouse' => $warehouse->name,
          'city' => $user->city,
          'warehouse_id' => '5',
          'warehouse_name' => 'Pune',
          'preference' => '0',
          'rewards_percentage' => ''
      ];
      RewardUser::create($data);

      $data = [
          'user_id' => $user->id,
          'party_code' => $user->party_code,
          'assigned_warehouse' => $warehouse->name,
          'city' => $user->city,
          'warehouse_id' => '6',
          'warehouse_name' => 'Mumbai',
          'preference' => '0',
          'rewards_percentage' => ''
      ];
      RewardUser::create($data);
     
      

      // If user creation is successful, modify the response status
      if ($user) {
        //whatsapp processing start
        $apiUrl =  "https://mazingbusiness.com/api/v2/get-head-manager-details-using-pincode";//getting headmanager phonenumber
        $res = Http::post($apiUrl, [
            'postal_code' => $user->postal_code
        ]);
        $res=json_decode($res);
        $manager_phone=$res->data->phone;

        $to = $user->phone;
        $company_name=$user->company_name;
        $messageContent = "Your account has been successfully created. Please wait for approval from the manager, which will be completed within 24 hours.";

        $userTemplateData = [
            'name' => 'utility_user_signup_temp', // Replace with your template name
            'language' => 'en_US', // Replace with your desired language code
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $company_name
                        ],
                        [
                            'type' => 'text',
                            'text' => $messageContent
                        ]
                    ],
                ],
            ],
        ];

        $this->WhatsAppWebService=new WhatsAppWebService();
        $response = $this->WhatsAppWebService->sendTemplateMessage($to, $userTemplateData);

        //whatsapp message to manager
        $to = $manager_phone;
        $company_name=$user->company_name;
        $user_phone=$user->phone;

        $managerTemplateData = [
          'name' => 'utility_head_manager_approval_template', // Replace with your template name
          'language' => 'en_US', // Replace with your desired language code
          'components' => [
              [
                  'type' => 'body',
                  'parameters' => [
                      [
                          'type' => 'text',
                          'text' => $company_name
                      ],
                      [
                          'type' => 'text',
                          'text' => $user_phone
                      ]
                  ],
              ]
              
          ],
      ];
      $this->WhatsAppWebService=new WhatsAppWebService();
      $response = $this->WhatsAppWebService->sendTemplateMessage($to, $managerTemplateData);
      //whatsapp processing end       
          return redirect()->route('customer.createSuccess')->with('success', 'Successfully create your account. Please wait for approval.');
      } else {
          return redirect()->route('customer.createSuccess')->with('error', 'There was a problem creating the user.');
      }
    } catch (ValidationException $e) {
      // Log the validation error messages
      \Log::error('Validation Errors: ', $e->errors());

      // Return validation errors as JSON
      return response()->json([
          'status' => 'Error',
          'errors' => $e->errors(),
      ], 422);
    } catch (\Exception $e) {
      // Log any other exceptions
      \Log::error('An error occurred: ' . $e->getMessage());

      return response()->json([
          'status' => 'Error',
          'message' => 'An unexpected error occurred.',
      ], 500);
    }
    
  }

  public function assignManager(Request $request) {
    try {
      $data = array();
      $getUser = User::where('id',$request->user_id)->first();
      $getManager = User::where('id',$request->manager_id)->first();
      
      $user = User::findOrFail($request->user_id);
      $user->password = Hash::make(substr($getManager->name, 0, 1).substr($getUser->phone, -4));
      $user->verification_code = substr($getManager->name, 0, 1).substr($getUser->phone, -4);
      $user->manager_id = $request->manager_id;
      $user->discount = $request->discount;
      $user->credit_limit = $request->credit_limit;
      $user->credit_days = $request->credit_days;
      $user->banned = 0;
      $user->save();
      $party_code = $getUser->party_code;

      // Transport data insert
      $rewardsData = RewardUser::where('party_code', $party_code)->where('warehouse_id', '1')->first();
      if ($rewardsData) {                
          $rewardsData->update([
              'preference' => $request->kol_warehouse,
              'rewards_percentage' => $request->kol_percentage
          ]);
      }else{
          $data = [
              'user_id' => $getUser->id,
              'party_code' => $getUser->party_code,
              'warehouse_id' => '1',
              'warehouse_name' => 'Kolkata',
              'preference' => $request->kol_warehouse,
              'rewards_percentage' => $request->kol_percentage
          ];
          RewardUser::create($data);
      }

      $rewardsData = RewardUser::where('party_code', $party_code)->where('warehouse_id', '2')->first();
      if ($rewardsData) {                
          $rewardsData->update([
              'preference' => $request->del_warehouse,
              'rewards_percentage' => $request->del_percentage
          ]);
      }else{
          $data = [
              'user_id' => $getUser->id,
              'party_code' => $getUser->party_code,
              'warehouse_id' => '2',
              'warehouse_name' => 'Delhi',
              'preference' => $request->del_warehouse,
              'rewards_percentage' => $request->del_percentage
          ];
          RewardUser::create($data);
      }
      $rewardsData = RewardUser::where('party_code', $party_code)->where('warehouse_id', '3')->first();
      if ($rewardsData) {                
          $rewardsData->update([
              'preference' => $request->che_warehouse,
              'rewards_percentage' => $request->che_percentage
          ]);
      }else{
          $data = [
              'user_id' => $getUser->id,
              'party_code' => $getUser->party_code,
              'warehouse_id' => '3',
              'warehouse_name' => 'Chennai',
              'preference' => $request->che_warehouse,
              'rewards_percentage' => $request->che_percentage
          ];
          RewardUser::create($data);
      }

      $rewardsData = RewardUser::where('party_code', $party_code)->where('warehouse_id', '5')->first();
      if ($rewardsData) {                
          $rewardsData->update([
              'preference' => $request->pun_warehouse,
              'rewards_percentage' => $request->pun_percentage
          ]);
      }else{
          $data = [
              'user_id' => $getUser->id,
              'party_code' => $getUser->party_code,
              'warehouse_id' => '5',
              'warehouse_name' => 'Pune',
              'preference' => $request->pun_warehouse,
              'rewards_percentage' => $request->pun_percentage
          ];
          RewardUser::create($data);
      }

      $rewardsData = RewardUser::where('party_code', $party_code)->where('warehouse_id', '6')->first();
      if ($rewardsData) {                
          $rewardsData->update([
              'preference' => $request->mum_warehouse,
              'rewards_percentage' => $request->mum_percentage
          ]);
      }else{
          $data = [
              'user_id' => $getUser->id,
              'party_code' => $getUser->party_code,
              'warehouse_id' => '6',
              'warehouse_name' => 'Mumbai',
              'preference' => $request->mum_warehouse,
              'rewards_percentage' => $request->mum_percentage
          ];
          RewardUser::create($data);
      }

      //SENDING WHATSAPP MESSAGE CODE START
      // **********************Message sending to Client************************ //
      $to = $user->phone;
      $templateData = [
        'name' => 'utility_registration_template', // Replace with your template name
        'language' => 'en_US', // Replace with your desired language code
        'components' => [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text','text' => $user->company_name],
                    ['type' => 'text','text' => str_replace("+91", "", $user->phone)],
                    ['type' => 'text','text' => $user->verification_code]
                ],
            ]
        ],
      ];

      $this->WhatsAppWebService=new WhatsAppWebService();
      $response = $this->WhatsAppWebService->sendTemplateMessage($to, $templateData);

      // **********************Message sending to sub Manager ************************ //
      $user_city = DB::table('users')
      ->join('addresses', 'users.id', '=', 'addresses.user_id')
      ->where('users.id', $user->id)
      ->value('addresses.city');

      $to=$getManager->phone;
      $subManagerTemplate = [
        'name' => 'utility_manager_notification', // Replace with your template name
        'language' => 'en_US', // Replace with your desired language code
        'components' => [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => !empty($getManager->name)     ? (string)$getManager->name     : '-'],
                    ['type' => 'text', 'text' => !empty($user->company_name)   ? (string)$user->company_name   : '-'],
                    ['type' => 'text', 'text' => !empty($user->phone)          ? (string)preg_replace('/^\+91/', '', $user->phone) : '-'],
                    ['type' => 'text', 'text' => !empty($user->gstin)          ? (string)$user->gstin          : $user->aadhar_card],
                    ['type' => 'text', 'text' => !empty($user->state)          ? (string)$user->state          : '-'],
                    ['type' => 'text', 'text' => !empty($user_city)            ? (string)$user_city            : '-'],
                ],
            ]
            
        ],
      ];

      $this->WhatsAppWebService=new WhatsAppWebService();
      $response = $this->WhatsAppWebService->sendTemplateMessage($to, $subManagerTemplate);
        


      // SENDING WHATSAPP MESSAGE CODE END

      $zoho = new ZohoController();
      $res= $zoho->createNewCustomerInZoho($getUser->party_code); // pass the party_code

      $redirect_url=$request->input('redirect_url');

      return redirect()->to($redirect_url)->with('success', 'Manager assigned successfully.');

      //return redirect()->route('customers.index');
      
    } catch (ValidationException $e) {
      // Log the validation error messages
      \Log::error('Validation Errors: ', $e->errors());

      // Return validation errors as JSON
      return response()->json([
          'status' => 'Error',
          'errors' => $e->errors(),
      ], 422);
    } catch (\Exception $e) {
      // Log any other exceptions
      \Log::error('An error occurred: ' . $e->getMessage());

      return response()->json([
          'status' => 'Error',
          'message' => $e->getMessage(),
      ], 500);
    }
    
  }

  public function createSuccess(Request $request) {
    // return view('frontend.user_registration');
    // return view('frontend.success_registration');
    if ($request->session()->has('success')) {
        return view('frontend.success_registration');
    } else {
        return redirect()->route('user.login')->with('error', 'Please log in to continue.');
    }
  }

  protected function createUser(array $data) {
   
    
    
    $pincode      = Pincode::where('pincode', $data['postal_code'])->first();
    
    // debug_to_console($party_code);
    // debug_to_console(json_encode($pincode));
    // debug_to_console(json_encode($data));
    
    if ($data['gstin']) {
      // echo "<pre>"; print_r($data); die; 
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
              $party_code =$data['party_code'].$count;
              $address = $value['addr'];
              $pincode = Pincode::where('pincode', $address['pncd'])->first();
              $state = State::where('name', $pincode->state)->first();
              $city = City::where('name', $pincode->city)->first();
              if(!isset($city->id)){
                $city = City::create([
                  'name'                   => $pincode->city,
                  'state_id'           => $state->id
                ]);
              }else{
                $city = $city->id;
              }
              
              // $cmp_address = $address['bnm']. ', '.$address['st'] . ', ' .$address['loc'] . ', ' .$address['bno'] . ', ' .$address['dst'];
              $cmp_address = $address['bnm']. ', '.$address['st'] . ', ' .$address['loc'];
              $cmp_address2 = $address['bno'] . ', ' .$address['dst'];
              Address::create([
                  'user_id'=>$user->id,
                  'acc_code'=>$party_code,
                  'company_name'=> $gstDataArray['tradeNam'],
                  'address' => trim($cmp_address,' ,'),
                  'address_2' => trim($cmp_address2,' ,'),
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
          if(!isset($city->id)){
            $city= 0;
          }else{
            $city = $city->id;
          }
          $state = State::where('name', $pincode->state)->first();
          // $cmp_address = $gstDataArray['pradr']['addr']['bnm']. ', '.$gstDataArray['pradr']['addr']['st'] . ', ' .$gstDataArray['pradr']['addr']['loc'] . ', ' .$gstDataArray['pradr']['addr']['bno'] . ', ' .$gstDataArray['pradr']['addr']['dst'];
          $cmp_address = $gstDataArray['pradr']['addr']['bnm']. ', '.$gstDataArray['pradr']['addr']['st'] . ', ' .$gstDataArray['pradr']['addr']['loc'];
          $cmp_address2 = $gstDataArray['pradr']['addr']['bno'] . ', ' .$gstDataArray['pradr']['addr']['dst'];
          $address = Address::create([
              'user_id'=>$user->id,
              'acc_code'=>$data['party_code'],
              'company_name'=> $gstDataArray['tradeNam'],
              'address' => trim($cmp_address,' ,'),
              'address_2' => trim($cmp_address2,' ,'),
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
          debug_to_console($e->getMessage());
          // Log::error($e->getMessage());
          // You can also log the stack trace
          // Log::error($e->getTraceAsString());
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
      $pincode = Pincode::where('pincode', $data['postal_code'])->first();
      $state = State::where('name', $pincode->state)->first();
      $city = City::where('name', $pincode->city)->first();
      if(!isset($city->id)){
        $city = City::create([
          'name'                   => $pincode->city,
          'state_id'           => $state->id
        ]);
      }else{
        $city = $city->id;
      }
      
      $cmp_address = $data['address'];
      $address = Address::create([
          'user_id'=>$user->id,
          'acc_code'=>$data['party_code'],
          'company_name'=> $data['company_name'],
          'address' => $cmp_address,
          'address_2' => $data['address2'],
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


    // ✅ Call Zoho function directly
    $zoho = new ZohoController();
    $res= $zoho->createNewCustomerInZoho($user->party_code); // pass the party_code
    
    // Push User data to Salezing
    $result=array();
    $result['party_code']= $user->party_code;
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->post('https://mazingbusiness.com/api/v2/client-push', $result);



    return $user;
  }

  public function sendOtp(Request $request){
    if ($request->phone != null) {
      $user_phone = "+91".$request->phone;
      $otp = rand(100000, 999999);            
      $sms_text = 'Your verification code is '.$otp.'. Thanks Mazing Retail Private Limited.';
      // WhatsAppUtility::loginOTP($user, $otp);
      SendSMSUtility::sendSMS($user_phone,'MAZING',$sms_text,'1207168939860495573');
      // SendSMSUtility::sendSMS('+918961043773','MAZING',$sms_text,'1207168939860495573');

      // Encode the OTP in base64
      $base64Otp = base64_encode($otp+10111984);

      // Return the base64 encoded OTP
      return response()->json(['otp' => $base64Otp]);
      //return base64_encode($otp);
    }
  }
  
  public function cart_login(Request $request) {
    $user = null;
    if ($request->get('phone') != null) {
      $user = User::whereIn('user_type', ['customer', 'seller'])->where('phone', "+{$request['country_code']}{$request['phone']}")->first();
    } elseif ($request->get('email') != null) {
      $user = User::whereIn('user_type', ['customer', 'seller'])->where('email', $request->email)->first();
    }

    if ($user != null) {
      if (Hash::check($request->password, $user->password)) {
        if ($request->has('remember')) {
          auth()->login($user, true);
        } else {
          auth()->login($user, false);
        }
      } else {
        flash(translate('Invalid email or password!!'))->warning();
      }
    } else {
      flash(translate('Invalid email or password!'))->warning();
    }
    return back();
  }

  /**
   * Create a new controller instance.
   *
   * @return void
   */
  public function __construct() {
    //$this->middleware('auth');
  }

  /**
   * Show the customer/seller dashboard.
   *
   * @return \Illuminate\Http\Response
   */
  
  public function dashboard() {
    if (Auth::user()->user_type == 'seller') {
      
      return redirect()->route('seller.dashboard');
    } elseif (Auth::user()->user_type == 'customer') {

      $monthlyOrders = Order::selectRaw('MONTH(created_at) as month, COUNT(*) as total')
                              ->groupBy('month')
                              ->get();

        $weeklyOrders = Order::selectRaw('WEEK(created_at) as week, COUNT(*) as total')
                          ->groupBy('week')
                          ->get();

        $yearlyOrders = Order::selectRaw('YEAR(created_at) as year, COUNT(*) as total')
                              ->groupBy('year')
                              ->get();

        // Prepare data for the chart
        $labels = [];
        $monthlyData = [];
        $weeklyData = [];
        $yearlyData = [];

        foreach ($monthlyOrders as $order) {
          $monthName = date('M', mktime(0, 0, 0, $order->interval, 1));
          $labels['monthly'][] = $monthName;
          $monthlyData[] = $order->total;
        }

        foreach ($weeklyOrders as $order) {
          $labels['weekly'][] = 'Week ' . $order->interval;
          $weeklyData[] = $order->total;
       }

      foreach ($yearlyOrders as $order) {
          $labels['yearly'][] = $order->interval;
          $yearlyData[] = $order->total;
      }

      //    echo "<pre>";
      //  print_r($labels);
      //  die();
      return view('frontend.user.customer.dashboard',compact('monthlyData','weeklyData','yearlyData'));
      // return view('frontend.quickorder');
    } elseif (Auth::user()->user_type == 'delivery_boy') {
      return view('delivery_boys.frontend.dashboard');
    } else {
      abort(404);
    }
  }

  public function profile(Request $request) {
    if (Auth::user()->user_type == 'seller') {
      return redirect()->route('seller.profile.index');
    } elseif (Auth::user()->user_type == 'delivery_boy') {
      return view('delivery_boys.frontend.profile');
    } else {
      return view('frontend.user.profile');
    }
  }
  public function ownBrandRequestSubmit(Request $request) {
    $user              = Auth::user();
    $user->own_brand        = $request->own_brand;
    $user->admin_approved_own_brand     = 0;
    $user->save();  
    if($request->own_brand == 1){
      return response()->json(['html' => '<div class="alert alert-success" role="alert">Thank you. Your request had been sent. Please wait for admin approved.</div>']);
    }else{
      return response()->json(['html' => '<div class="alert alert-danger" role="alert">Your profile is now de active from Own Brand.</div>']);
    }
    
  }
  public function userProfileUpdate(Request $request) {
    if (env('DEMO_MODE') == 'On') {
      flash(translate('Sorry! the action is not permitted in demo '))->error();
      return back();
    }
    // echo "test";
    // die();
    $user              = Auth::user();
    $user->name        = $request->name;
    $user->address     = $request->address;
    $user->aadhar_card = $request->aadhar_card;
    $user->country     = $request->country;
    $user->city        = $request->city;
    if (!$user->gstin && $request->gstin) {
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
        if ($data['error']) {
          flash(translate($data['message']))->error();
          return back();
        } else {
          $user->gstin = $request->gstin;
        }
      }
    }
    $user->postal_code  = $request->postal_code;
    // $user->company_name = $request->company_name;
    if ($request->new_password != null && ($request->new_password == $request->confirm_password)) {
      $user->password = Hash::make($request->new_password);
    }

    $user->avatar_original = $request->photo;
    $user->save();
    
    // Push User data to Salezing
    $result=array();
    $result['party_code']= $user->party_code;
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->post('https://mazingbusiness.com/api/v2/client-push', $result);

    flash(translate('Your Profile has been updated successfully!'))->success();
    return back();
  }

  public function flash_deal_details($slug) {
    $flash_deal = FlashDeal::where('slug', $slug)->first();
    if ($flash_deal != null) {
      return view('frontend.flash_deal_details', compact('flash_deal'));
    } else {
      abort(404);
    }
  }

  public function load_featured_section() {
    return view('frontend.partials.featured_products_section');
  }

  public function load_best_selling_section() {
    return view('frontend.partials.best_selling_section');
  }

  public function load_offer_price_section() {

    // Return the view for offer price section
    return view('frontend.partials.offer_price_section');
  }

  public function offerPriceAll(Request $request, $category_id = null, $brand_id = null)
  {
      // Maintain session view
      if ($request->view && (session('view') != $request->view)) {
          session(['view' => $request->view]);
      }
  
      $query = $request->keyword;
      $generic_name = $request->generic_name;
      $type = $request->type;
      $sort_by = $request->sort_by;
      $min_price = $request->min_price;
      $max_price = $request->max_price;
      $seller_id = $request->seller_id;
      $brands = $request->has('brands') ? array_filter($request->brands) : [];
      $attributes = [];
      $selected_attribute_values = [];
      $selected_color = null;
  
      // Get distinct part numbers from products_api table
      $distinct_part_nos = DB::table('products_api')->select('part_no')->distinct()->pluck('part_no')->toArray();
  
      // Query to fetch products where part_no exists in the products table based on distinct part_no
      $products = Product::whereIn('part_no', $distinct_part_nos)
          ->where('part_no', '!=', '')
          ->where('current_stock', '>', 0);
  
      // Get minimum and maximum price for price range slider
      $min_total = floor($products->min('unit_price') ?? 0);
      $max_total = ceil($products->max('unit_price') ?? 0);
  
      // Apply filters if price range is set
      if ($request->has('min_price') && $request->has('max_price')) {
          $products->whereBetween('unit_price', [$request->min_price, $request->max_price]);
      }
  
      // Apply generic name filter
      if ($generic_name != null) {
          $products = $products->where('generic_name', 'like', '%' . $generic_name . '%');
      }
  
      // Apply brand filter
      if ($brand_id != null) {
          $products = $products->where('brand_id', $brand_id);
      } elseif ($brands) {
          $brand_ids = Brand::whereIn('slug', $brands)->pluck('id');
          $products = $products->whereIn('brand_id', $brand_ids);
      }
  
      // Apply category filter based on type or category_id
      if ($type) {
          $category_ids = explode(',', Category::find($request->category_id)->linked_categories);
          $category_ids = Category::whereIn('id', $category_ids)
              ->where('category_group_id', $type === 'accessories' ? 1 : 5)
              ->pluck('id');
          $products = $products->whereIn('category_id', $category_ids);
      } elseif ($category_id != null) {
          $category_ids = CategoryUtility::children_ids($category_id);
          $category_ids[] = $category_id;
          $products->whereIn('category_id', $category_ids);
          $attribute_ids = AttributeCategory::whereIn('category_id', $category_ids)->pluck('attribute_id')->toArray();
          $attributes = Attribute::whereIn('id', $attribute_ids)->where('type', '!=', 'data')->get();
      }
  
      // Apply keyword search filter
      if ($query != null) {
          $products->where(function ($q) use ($query) {
              foreach (explode(' ', trim($query)) as $word) {
                  $q->where('name', 'like', '%' . $word . '%')
                      ->orWhere('tags', 'like', '%' . $word . '%')
                      ->orWhereHas('product_translations', function ($q) use ($word) {
                          $q->where('name', 'like', '%' . $word . '%');
                      })
                      ->orWhereHas('stocks', function ($q) use ($word) {
                          $q->where('variant', 'like', '%' . $word . '%');
                      });
              }
          });
      }
  
      // Apply color filter
      if ($request->has('color')) {
          $products->where('colors', 'like', '%' . json_encode($request->color) . '%');
          $selected_color = $request->color;
      }
  
      // Apply attribute filter
      if ($request->has('selected_attribute_values')) {
          $selected_attribute_values = $request->selected_attribute_values;
          $products->where(function ($query) use ($selected_attribute_values) {
              foreach ($selected_attribute_values as $value) {
                  $query->orWhere('choice_options', 'like', '%' . json_encode($value) . '%');
              }
          });
      }
  
      // Sorting logic with Opel products displayed first within each category
      switch ($sort_by) {
          case 'newest':
              $products->orderBy('created_at', 'desc');
              break;
          case 'oldest':
              $products->orderBy('created_at', 'asc');
              break;
          case 'price-asc':
              $products->orderBy('mrp', 'asc');
              break;
          case 'price-desc':
              $products->orderBy('mrp', 'desc');
              break;
          default:
              // Prioritize Opel products within each category, followed by others in each category
              $products->join('categories', 'products.category_id', '=', 'categories.id')
                  ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
                  ->select('products.*', 'category_groups.name as group_name', 'categories.name as category_name')
                  ->orderBy('categories.id') // Ensure products are grouped by category
                  ->orderByRaw("CASE 
                      WHEN products.name LIKE '%opel%' THEN 0 
                      ELSE 1 END")  // Opel products first in each category
                  ->orderBy('products.name', 'ASC')  // After Opel, other products are ordered by name
                  ->orderBy('products.mrp', 'ASC');
              break;
      }
  
      // Apply eager loading of taxes and pagination
      $products = $products->with('taxes')->paginate(42)->appends(request()->query());
  
      // Fetch category groups
      $categoryGroup = DB::table('category_groups')
          ->select('category_groups.*')
          ->leftJoin('products', 'products.group_id', '=', 'category_groups.id')
          ->where('products.part_no', '!=', '')
          ->where('products.current_stock', '>', 0)
          ->distinct()
          ->orderByRaw("CASE 
              WHEN category_groups.id = 1 THEN 0 
              WHEN category_groups.id = 8 THEN 1 
              ELSE 2 END")
          ->orderBy('category_groups.name', 'asc')
          ->get();
  
      // Fetch brand IDs
      $catProducts = Product::where('category_id', $category_id)->where('part_no', '!=', '')->where('current_stock', '>', 0)->get();
      $id_brand = $catProducts->pluck('brand_id')->unique()->toArray();
  
      // Pass min_total and max_total to the view
      return view('frontend.offer_price_all', compact('categoryGroup', 'products', 'query', 'category_id', 'brand_id', 'brands', 'sort_by', 'seller_id', 'min_price', 'max_price', 'attributes', 'selected_attribute_values', 'selected_color', 'id_brand', 'min_total', 'max_total'));
  }
  
  

  public function offerPriceAll_back(Request $request, $category_id = null, $brand_id = null)
  {
   
    
      // Maintain session view
      if ($request->view && (session('view') != $request->view)) {
          session(['view' => $request->view]);
      }
  
      $query = $request->keyword;
      $generic_name = $request->generic_name;
      $type = $request->type;
      $sort_by = $request->sort_by;
      $min_price = $request->min_price;
      $max_price = $request->max_price;
      $seller_id = $request->seller_id;
      $brands = $request->has('brands') ? array_filter($request->brands) : [];
      $attributes = [];
      $selected_attribute_values = [];
      $selected_color = null;
  
      // Get distinct part numbers from products_api table
      $distinct_part_nos = DB::table('products_api')->select('part_no')->distinct()->pluck('part_no')->toArray();
  
      // Query to fetch products where part_no exists in the products table based on distinct part_no
      $products = Product::whereIn('part_no', $distinct_part_nos)
          ->where('part_no', '!=', '')
          ->where('current_stock', '>', 0);
  
      // Get minimum and maximum price for price range slider
      $min_total = floor($products->min('unit_price') ?? 0);
      $max_total = ceil($products->max('unit_price') ?? 0);
  
      // Apply filters if price range is set
      if ($request->has('min_price') && $request->has('max_price')) {
          $products->whereBetween('unit_price', [$request->min_price, $request->max_price]);
      }
  
      // Apply generic name filter
      if ($generic_name != null) {
          $products = $products->where('generic_name', 'like', '%' . $generic_name . '%');
      }
  
      // Apply brand filter
      if ($brand_id != null) {
          $products = $products->where('brand_id', $brand_id);
      } elseif ($brands) {
          $brand_ids = Brand::whereIn('slug', $brands)->pluck('id');
          $products = $products->whereIn('brand_id', $brand_ids);
      }
  
      // Apply category filter based on type or category_id
      if ($type) {
          $category_ids = explode(',', Category::find($request->category_id)->linked_categories);
          $category_ids = Category::whereIn('id', $category_ids)
              ->where('category_group_id', $type === 'accessories' ? 1 : 5)
              ->pluck('id');
          $products = $products->whereIn('category_id', $category_ids);
      } elseif ($category_id != null) {
          $category_ids = CategoryUtility::children_ids($category_id);
          $category_ids[] = $category_id;
          $products->whereIn('category_id', $category_ids);
          $attribute_ids = AttributeCategory::whereIn('category_id', $category_ids)->pluck('attribute_id')->toArray();
          $attributes = Attribute::whereIn('id', $attribute_ids)->where('type', '!=', 'data')->get();
      }
  
      // Apply keyword search filter
      if ($query != null) {
          $products->where(function ($q) use ($query) {
              foreach (explode(' ', trim($query)) as $word) {
                  $q->where('name', 'like', '%' . $word . '%')
                      ->orWhere('tags', 'like', '%' . $word . '%')
                      ->orWhereHas('product_translations', function ($q) use ($word) {
                          $q->where('name', 'like', '%' . $word . '%');
                      })
                      ->orWhereHas('stocks', function ($q) use ($word) {
                          $q->where('variant', 'like', '%' . $word . '%');
                      });
              }
          });
      }
  
      // Apply color filter
      if ($request->has('color')) {
          $products->where('colors', 'like', '%' . json_encode($request->color) . '%');
          $selected_color = $request->color;
      }
  
      // Apply attribute filter
      if ($request->has('selected_attribute_values')) {
          $selected_attribute_values = $request->selected_attribute_values;
          $products->where(function ($query) use ($selected_attribute_values) {
              foreach ($selected_attribute_values as $value) {
                  $query->orWhere('choice_options', 'like', '%' . json_encode($value) . '%');
              }
          });
      }
  
      // Sorting logic
     //$products->orderByRaw("CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END"); // Opel products first
      switch ($sort_by) {
          case 'newest':
              $products->orderBy('created_at', 'desc');
              break;
          case 'oldest':
              $products->orderBy('created_at', 'asc');
              break;
          case 'price-asc':
              $products->orderBy('mrp', 'asc');
              break;
          case 'price-desc':
              $products->orderBy('mrp', 'desc');
              break;
          default:
              $products->orderBy('category_groups.name', 'ASC');
              $products->orderBy('categories.name', 'ASC');
              $products->orderBy('products.name', 'ASC');
              $products->orderBy('products.mrp', 'ASC');
              break;
      }
  
      // Join categories and category groups
      $productsQuery = $products->join('categories', 'products.category_id', '=', 'categories.id')
          ->join('category_groups', 'categories.category_group_id', '=', 'category_groups.id')
          ->select('products.*', 'category_groups.name as group_name', 'categories.name as category_name');
  
      // Add eager loading of taxes and apply pagination
      $products = $productsQuery->with('taxes')->paginate(42)->appends(request()->query());
  
      // Fetch category groups
      $categoryGroup = DB::table('products')
          ->leftJoin('category_groups', 'products.group_id', '=', 'category_groups.id')
          ->where('category_groups.featured', 1)
          ->where('products.part_no', '!=', '')
          ->where('products.current_stock', '>', 0)
          ->distinct()
          ->orderBy('category_groups.name', 'asc')
          ->select('category_groups.*')
          ->get();
  
      // Fetch brand IDs
      $catProducts = Product::where('category_id', $category_id)->where('part_no', '!=', '')->where('current_stock', '>', 0)->get();
      $id_brand = $catProducts->pluck('brand_id')->unique()->toArray();
  
      // Pass min_total and max_total to the view
      return view('frontend.offer_price_all', compact('categoryGroup', 'products', 'query', 'category_id', 'brand_id', 'brands', 'sort_by', 'seller_id', 'min_price', 'max_price', 'attributes', 'selected_attribute_values', 'selected_color', 'id_brand', 'min_total', 'max_total'));
  }
  

  public function load_recently_viewed_section() {
    return view('frontend.partials.recently_viewed_section');
  }

  public function load_top_10_brands_section(Request $request) {
    $compare     = $request->session()->get('compare');
    $top10brands = Cache::remember('brands', 3600, function () {
      $br           = [];
      $top10_brands = json_decode(get_setting('top10_brands'));
      foreach ($top10_brands as $key => $value) {
        $br[$key] = Brand::where('id', $value)->select('slug', 'logo', 'name')->first();
      }
      return $br;
    });
    return view('frontend.partials.top10brands', compact('top10brands', 'compare'));
  }

  public function load_search_by_profession(Request $request) {
    $professions = json_decode(get_setting('home_professions'));
    return view('frontend.partials.profession', compact('professions'));
  }

  public function load_home_categories_section() {
    return view('frontend.partials.home_categories_section');
  }

  public function load_best_sellers_section() {
    return view('frontend.partials.best_sellers_section');
  }

  public function trackOrder(Request $request) {
    if ($request->has('order_code')) {
      $order = Order::where('code', $request->order_code)->first();
      if ($order != null) {
        return view('frontend.track_order', compact('order'));
      }
    }
    return view('frontend.track_order');
  }

  public function showQuickViewModal(Request $request) {
    $product = Product::find($request->id);
    return view('frontend.partials.quickview', compact('product'));
  }

public function updateAttributeValues(Request $request)
{
    // Retrieve the selected attribute_values table IDs (values only)
    $selectedValues = $request->input('selected_values');
    $variation_parent_part_no = $request->input('variation_parent_part_no');

    // Extract only the values (e.g., [639, 641]) and convert them to integers
    $selectedIds = array_map('intval', array_values($selectedValues));

    // Sort the selected IDs to ensure they match the JSON array order in the DB
    sort($selectedIds);

    // Query the products table to find a row where variations exactly match the selected IDs
     $product = Product::whereRaw('JSON_LENGTH(variations) = ?', [count($selectedIds)]) // Match array length
        ->whereRaw('JSON_CONTAINS(variations, ?)', [json_encode($selectedIds)]) // Match all IDs
        ->where('variation_parent_part_no',$variation_parent_part_no)
        ->first(); // Get the first matching row

    if ($product) {
        return response()->json([
            'success' => true,
            'message' => 'Matching product slug retrieved successfully!',
            'data' => $product->slug
        ]);
    } else {
        return response()->json([
            'success' => false,
            'message' => 'No matching product found!',
            'data' => null
        ]);
    }
}

public function getProductId(Request $request)
{
    $selectedValues = $request->input('selected_values', []);
    $variationParentPartNo = $request->input('variation_parent_part_no');

    // Convert selected values to integers and sort them
    $selectedIds = array_map('intval', array_values($selectedValues));
    sort($selectedIds);

    // Find the product that matches the selected attributes
    $product = Product::whereRaw('JSON_LENGTH(variations) = ?', [count($selectedIds)])
        ->whereRaw('JSON_CONTAINS(variations, ?)', [json_encode($selectedIds)])
        ->where('variation_parent_part_no', $variationParentPartNo)
        ->first();

    if ($product) {
        return response()->json([
            'success' => true,
            'message' => 'Matching product found!',
            'data' => $product->id // Return the product ID
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'No matching product found!',
        'data' => null
    ]);
}
public function product(Request $request, $slug) {


    try {
      $detailedProduct = Product::with(['reviews' => function ($query) {
        $query->orderBy('created_at', 'desc');
      }, 'brand', 'stocks', 'user', 'stocks.seller', 'category', 'category.categoryGroup'])->where('slug', $slug)->where('approved', 1)->firstOrFail();
      $product_queries = ProductQuery::where('product_id', $detailedProduct->id)->where('customer_id', '!=', Auth::id())->latest('id')->paginate(10);
      $total_query     = ProductQuery::where('product_id', $detailedProduct->id)->count();

      // Pagination using Ajax
      if (request()->ajax()) {
        return Response::json(View::make('frontend.partials.product_query_pagination', array('product_queries' => $product_queries))->render());
      }
      // End of Pagination using Ajax

       // Initialize variant details and attributes
        $variantDetails = [];
        $attributeVariations = [];
        $selectedValues = []; // To store the selected values for the loaded product

        if ($detailedProduct->variant_product == 1) {

              // Fetch all products with the same variation_parent_part_no
            $parentProducts = Product::where('variation_parent_part_no', $detailedProduct->variation_parent_part_no)
                ->where('current_stock','1')
                ->pluck('variations')
                ->toArray();
            // Combine and get distinct variation IDs
            $allVariationIds = collect($parentProducts)
                ->flatMap(function ($variations) {
                    return json_decode($variations, true);
                })
                ->unique()
                ->toArray();
            // Fetch attribute values with attribute_id
            $attributeValues = AttributeValue::whereIn('id', $allVariationIds)->get();
            // Fetch attributes and their corresponding values
            $attributeVariations = Attribute::whereIn('id', $attributeValues->pluck('attribute_id'))
            ->where('is_variation', 1) //  attributes where is_variation is true
                ->get()
                ->map(function ($attribute) use ($attributeValues) {
                    return [
                        'attribute_id' => $attribute->id, // Include attribute_id for proper mapping
                        'attribute_name' => $attribute->name,
                        'values' => $attributeValues->where('attribute_id', $attribute->id)->pluck('value', 'id'),
                    ];
                });

            // Get the selected values for the loaded product
            $selectedValues = $attributeValues->whereIn('id', json_decode($detailedProduct->variations, true))
                ->pluck('id', 'attribute_id');

        }

      $acc_spa = ['acc' => [], 'spa' => []];
      if ($detailedProduct != null && $detailedProduct->published) {
        $categories = [];
        if ($detailedProduct->category->linked_categories) {
          $linked_categories = explode(',', $detailedProduct->category->linked_categories);
          $categories        = Category::with('categoryGroup:id')->select('id', 'category_group_id', 'name')->whereIn('id',
            $linked_categories)->get();
          $acc_spa = ['acc' => [], 'spa' => []];
          foreach ($categories as $cat) {
            if ($cat->categoryGroup->id == 1) {
              $acc_spa['acc'] = array_merge($acc_spa['acc'], get_cached_products($cat->id)->all());
            }
            if ($cat->categoryGroup->id == 5) {
              $acc_spa['spa'] = array_merge($acc_spa['spa'], get_cached_products($cat->id)->all());
            }
          }
        }


        if (Auth::check()) {

            // Referral processing (only if code present + addon active)
            if ($request->filled('product_referral_code') && addon_is_activated('affiliate_system')) {
                $affMinutes = AffiliateConfig::where('type', 'validation_time')->value('value');
                $cookieMin  = $affMinutes ? ((int)$affMinutes * 60) : (30 * 24);

                // (Optional) set cookies even for logged-in; guest par hum skip kar rahe hain by design
                Cookie::queue('product_referral_code', $request->product_referral_code, $cookieMin);
                Cookie::queue('referred_product_id', $detailedProduct->id, $cookieMin);

                // Safe referral (no self-referral)
                $referredBy = User::where('referral_code', $request->product_referral_code)->first();
                if ($referredBy && $referredBy->id !== Auth::id()) {
                    try {
                        (new \App\Http\Controllers\AffiliateController)
                            ->processAffiliateStats($referredBy->id, 1, 0, 0, 0);
                    } catch (\Throwable $e) {
                        logger()->warning('Affiliate referral skipped', ['msg' => $e->getMessage()]);
                    }
                }
            }

            // Offers only for logged-in user
            $detailedProduct = $this->addOfferTag($detailedProduct);

            // Min qty only for logged-in user
            product_min_qty($detailedProduct, Auth::id());
        }


        if ($detailedProduct->digital == 1) {
         
          return view('frontend.digital_product_details', compact('detailedProduct', 'product_queries', 'total_query', 'attributeVariations','selectedValues'));
        } else {
          if (Auth::check()) {
              product_min_qty($detailedProduct, Auth::id());
          }
          // echo "<pre>";
          // print_r($detailedProduct);
          // die();
          return view('frontend.product_details', compact('detailedProduct', 'product_queries', 'total_query', 'acc_spa',  'attributeVariations','selectedValues'));
        }
      }
    } catch (Exception $e) {
      abort(404);
    }
  }

  public function addOfferTag($carts){
    
    $userDetails = User::with(['get_addresses' => function ($query) {
        $query->where('set_default', 1);
    }])->where('id', Auth::user()->id)->first();
    // echo "<pre>"; print_r($userDetails);die;
    $state_id = $userDetails->get_addresses[0]->state_id;
    $currentDate = Carbon::now(); // Get the current date and time
    $offerCount = 0;
    $productId=$carts->id;
    $offers = Offer::with('offerProducts')
    ->where('status', 1) // Check for offer status
    ->where(function ($query) use ($userDetails) {
        $query->where('manager_id', $userDetails->manager_id)
            ->orWhereNull('manager_id');
    })            
    ->where(function ($query) use ($state_id) {
        $query->where('state_id', $state_id)
            ->orWhereNull('state_id');
    })
    ->whereDate('offer_validity_start', '<=', $currentDate) // Start date condition
    ->whereDate('offer_validity_end', '>=', $currentDate) // End date condition
    ->whereHas('offerProducts', function ($query) use ($productId) {
        $query->where('product_id', $productId);
    })->get();
    $offerCount = $offers->count();
    if($offerCount > 0){
        $carts->offer = $offers;
    }else{
        $carts->offer = "";
    }
    return $carts;
  }

  public function shop($slug) {
    $shop = Shop::where('slug', $slug)->first();
    if ($shop != null) {
      if ($shop->verification_status != 0) {
        return view('frontend.seller_shop', compact('shop'));
      } else {
        return view('frontend.seller_shop_without_verification', compact('shop'));
      }
    }
    abort(404);
  }

  public function filter_shop($slug, $type) {
    $shop = Shop::where('slug', $slug)->first();
    if ($shop != null && $type != null) {
      return view('frontend.seller_shop', compact('shop', 'type'));
    }
    abort(404);
  }

  public function all_categories(Request $request) {

     // $categories = DB::table('category_groups')
    //             ->orderByRaw("CASE WHEN name = 'Power Tools' THEN 0 ELSE 1 END")
    //             ->orderBy('name', 'ASC')
    //             ->get();

    // $categories = DB::table('products')
    //             ->leftJoin('category_groups', 'products.group_id', '=', 'category_groups.id')
    //             // ->where('category_groups.featured', 1)
    //             ->where('products.part_no', '!=', '')
    //             ->where('products.current_stock', '>', 0)
    //             ->orderByRaw("CASE WHEN category_groups.name = 'Power Tools' THEN 0 ELSE 1 END")
    //             ->orderBy('category_groups.name', 'asc')
    //             ->select('category_groups.*')
    //             ->distinct()
    //             ->get();

    $categories = DB::table('products')
                ->leftJoin('category_groups', 'products.group_id', '=', 'category_groups.id')
                ->where('products.part_no', '!=', '')
                ->where('products.current_stock', '>', 0)
                ->orderByRaw("CASE 
                    WHEN category_groups.id = 1 THEN 0  -- Power Tools
                    WHEN category_groups.id = 8 THEN 1  -- Cordless Tools
                    ELSE 2 
                END")
                ->orderBy('category_groups.name', 'asc')
                ->select('category_groups.*')
                ->distinct()
                ->get();

    // Loop through categories and get sub-categories
    foreach ($categories as $category) {
        $sub_categories = DB::table('categories')
                            ->where('category_group_id', $category->id)
                            ->orderBy('name', 'asc')
                            ->get();
        // Assign sub-categories to the 'sub' property of each category object
        $category->sub = $sub_categories;
    }

    // Return the view with the updated categories
    return view('frontend.all_category', compact('categories'));
}


  public function all_brands(Request $request) {
    
    $brands = Brand::whereHas('products', function ($query) {
      $query->where('published', 1);
      $query->where('current_stock', 1);
      // $query->where('approved', 1);
      
    })->get();
   
    
    return view('frontend.all_brand', compact('brands'));
  }

  public function home_settings(Request $request) {
    return view('home_settings.index');
  }

  public function top_10_settings(Request $request) {
    foreach (Category::all() as $key => $category) {
      if (is_array($request->top_categories) && in_array($category->id, $request->top_categories)) {
        $category->top = 1;
        $category->save();
      } else {
        $category->top = 0;
        $category->save();
      }
    }

    foreach (Brand::all() as $key => $brand) {
      if (is_array($request->top_brands) && in_array($brand->id, $request->top_brands)) {
        $brand->top = 1;
        $brand->save();
      } else {
        $brand->top = 0;
        $brand->save();
      }
    }

    flash(translate('Top 10 categories and brands have been updated successfully'))->success();
    return redirect()->route('home_settings.index');
  }

  public function getManagers(Request $request) {

    // $logged_in_user = Auth::user();
    // $role_id = $logged_in_user->role_id;

      $managers = Staff::with(['user' => function ($query) {
                    $query->select('id', 'name');
                  }])->whereHas('user', function ($query) use ($request) {
                    if($request->staff_user_id && $request->staff_user_id != 0) {
                      $query->where('user_id', $request->staff_user_id);
                    }
                    else{
                      $query->where('warehouse_id', $request->warehouse_id);
                    }
                 })->where('role_id', 5)->get();
               

    $html = ($request->staff_user_id!= 0) ? '' :'<option value="">' . translate("Select Manager") . '</option>';
    foreach ($managers as $manager) {
      $html .= '<option value="' . $manager->user->id . '">' . $manager->user->name . '</option>';
    }
    echo json_encode($html);
  }

  public function checkGsitnExist(Request $request) {
    $checkgsitn = User::where('gstin', $request->gstin)->first();
    if(!empty($checkgsitn))  {
      //************WHATSAPP  CODE START**************//
          $templateData = [
                'name' => 'old_already_registered', // Replace with your template name
                'language' => 'en', // Replace with your desired language code
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text','text' => $checkgsitn->phone],
                            ['type' => 'text','text' => $checkgsitn->verification_code]
                        ],
                    ]
                ],
            ];
            $this->WhatsAppWebService=new WhatsAppWebService();
            $whatsappResponse = $this->WhatsAppWebService->sendTemplateMessage($checkgsitn->phone, $templateData);

      //************WHATSAPP  CODE END**************//

      $response['error']  = true;
      $response['message'] = 'GSITN already exists!';
    }
    else {
      $response['status']  = true;
      $response['message'] = 'GSITN not exists!';
    }
    return json_encode($response);
  }

  public function checkGsitnExistOnProfile(Request $request) {
    $user = Auth::user();
    $checkgsitn = User::where('id', '!=', $user->id)->where('gstin', $request->gstin)->first();
    if(!empty($checkgsitn))  {
      $response['error']  = true;
      $response['message'] = 'GSITN already exists!';
    }
    else {
      $response['status']  = true;
      $response['message'] = 'GSITN not exists!';
    }
    return json_encode($response);
  }

  public function checkPhoneNumber(Request $request) {
    $checkphone = User::where('phone', 'like', '%' . $request->mobile . '%')->first();
    if(!empty($checkphone))  {
      $response['error']  = true;
      $response['message'] = 'Phone number already exists!';
    }
    else {
      $response['status']  = true;
      $response['message'] = 'Phone number not exists!';
    }
    return json_encode($response);
  }

  public function checkEmail(Request $request) {
    $checkphone = User::where('email', 'like', '%' . $request->email . '%')->first();
    if(!empty($checkphone))  {
      $response['error']  = true;
      $response['message'] = 'Email already exists!';
    }
    else {
      $response['status']  = true;
      $response['message'] = 'Email not exists!';
    }
    return json_encode($response);
  }

  public function checkAadharNumber(Request $request) {
    $check_aadhar_card = User::where('aadhar_card', 'like', '%' . $request->aadhar_card . '%')->first();
    if(!empty($check_aadhar_card))  {
      $response['error']  = true;
      $response['message'] = 'Aadhar number already exists!';
    }else {
      $response['status']  = true;
      $response['message'] = 'Aadhar number not exists!';
    }
    return json_encode($response);
  }

  public function checkPostalCode(Request $request) {
    $check_postal_code = Pincode::where('pincode', $request->postal_code)->first();
    if(empty($check_postal_code))  {
      $response['error']  = true;
      $response['message'] = 'Pincode is not valid!';
    }else {
      $response['status']  = true;
      $response['message'] = 'Valid pincode!';
    }
    return json_encode($response);
  }

  public function variant_price(Request $request) {
    return;
    $product  = Product::find($request->id);
    $str      = '';
    $quantity = $tax = $ctax = $max_limit = $markup = $wmarkup = $price = $carton_price = 0;

    if ($request->has('color')) {
      $str = $request['color'];
    }
    
   
    if (json_decode($product->choice_options) != null) {
      foreach (json_decode($product->choice_options) as $key => $choice) {
        if ($str != null) {
          $str .= '_' . str_replace(' ', '', strtolower($request['attribute_id_' . $choice->attribute_id]));
        } else {
          $str .= str_replace(' ', '', strtolower($request['attribute_id_' . $choice->attribute_id]));
        }
      }
    }
    $product_stocks = $product->stocks->where('variant', $str);

    $user = Auth::user();

    $discount = 0;

    if ($user) {
        $discount = $user->discount;
    } else {
        // echo "<script>console.log('User not logged in');</script>";
    }

    if(!is_numeric($discount) || $discount == 0) {
      $discount = 20;
    }

    $product_mrp = Product::where('id', $product->id)->select('mrp')->first();
    if ($product_mrp) {
        $price = $product_mrp->mrp;
    } else {
      $price = 0;
    }
    
    if (!is_numeric($price)) {
      $price = 0;
    }

    $price = $price * ((100 - $discount) / 100);
    $price = ceil($price);
    
    // $carton_price += $carton_price * ($markup + $wmarkup) / 100;
    // $max_limit        = $quantity        = $product_stocks->sum('qty') + $product_stocks->sum('seller_stock');
    // $max_carton_limit = $carton_quantity = floor($quantity / $product_stocks->first()->piece_per_carton);
    // if ($product->wholesale_product) {
    //   $wholesalePrice = $product_stock->wholesalePrices->where('min_qty', '<=', $request->quantity)->where('max_qty', '>=', $request->quantity)->first();
    //   if ($wholesalePrice) {
    //     $price = $wholesalePrice->price;
    //   }
    // }

    if ($quantity >= 1 && $product->min_qty <= $quantity) {
      $in_stock = 1;
    } else {
      $in_stock = 0;
    }

    //Product Stock Visibility
    if ($product->stock_visibility_state == 'text') {
      if ($quantity >= 1 && $product->min_qty < $quantity) {
        $quantity = translate('In Stock');
      } else {
        $quantity = translate('Out Of Stock');
      }
    }

    //discount calculation
    $discount_applicable = false;

    if ($product->discount_start_date == null) {
      $discount_applicable = true;
    } elseif (
      strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
      strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date
    ) {
      $discount_applicable = true;
    }

    if ($discount_applicable) {
      if ($product->discount_type == 'percent') {
        $price -= ($price * $product->discount) / 100;
      } elseif ($product->discount_type == 'amount') {
        $price -= $product->discount;
      }
    }

    // taxes
    foreach ($product->taxes as $product_tax) {
      if ($product_tax->tax_type == 'percent') {
        $tax += ($price * $product_tax->tax) / 100;
      } elseif ($product_tax->tax_type == 'amount') {
        $tax += $product_tax->tax;
      }
    }
    // $price += $tax;
    $carton_price += $ctax;

    $product_bulk_qty = Product::where('id', $product->id)->select('piece_by_carton')->first();
    if ($product_bulk_qty) {
        $bulk_qty = $product_bulk_qty->piece_by_carton;
    } else {
      $bulk_qty = 0;
    }

    if($request->quantity >= $bulk_qty)
    {
      $price = $price * 0.98;
    }

    return array(
      'single_price'     => single_price($price),
      'price'            => single_price(round($price) * $request->quantity),
      'carton_price'     => single_price(round($price) * $product_bulk_qty),
      'piece_per_carton' => $bulk_qty,
      'quantity'         => $quantity,
      'carton_quantity'  => $bulk_qty,
      'variation'        => $str,
      'max_limit'        => $max_limit,
      'in_stock'         => $in_stock,
    );
  }

  public function sellerpolicy() {
    $page = Page::where('type', 'seller_policy_page')->first();
    return view("frontend.policies.sellerpolicy", compact('page'));
  }

  public function returnpolicy() {
    $page = Page::where('type', 'return_policy_page')->first();
    return view("frontend.policies.returnpolicy", compact('page'));
  }

  public function shippingpolicy() {
    $page = Page::where('type', 'shipping_policy_page')->first();
    return view("frontend.policies.shippingpolicy", compact('page'));
  }

  public function terms() {
    $page = Page::where('type', 'terms_conditions_page')->first();
    return view("frontend.policies.terms", compact('page'));
  }

  public function privacypolicy() {
    $page = Page::where('type', 'privacy_policy_page')->first();
    return view("frontend.policies.privacypolicy", compact('page'));
  }

  public function get_pick_up_points(Request $request) {
    $pick_up_points = PickupPoint::all();
    return view('frontend.partials.pick_up_points', compact('pick_up_points'));
  }

  public function get_category_items(Request $request) {
    $category = Cache::remember('category_menu_items_' . $request->id, 3600, function () use ($request) {
      $category = Category::with('childrenCategoriesMini:id,parent_id,name,slug')->findOrFail($request->id);
      foreach ($category->childrenCategoriesMini as $key1 => $cat1) {
        $add1 = Product::where('published', 1)
          ->where('category_id', $cat1->id)
          ->count();
        $sum = $add1;
        foreach ($cat1->categories as $key2 => $cat2) {
          $add2 = Product::where('published', 1)
            ->where('category_id', $cat2->id)
            ->count();
          $category['childrenCategoriesMini'][$key1]['categories'][$key2]['products_count'] = $add2;
          $sum += $add2;
        }
        $category['childrenCategoriesMini'][$key1]['products_count'] = $sum;
      }
      return $category;
    });
    return view('frontend.partials.category_elements', compact('category'));
  }

  public function premium_package_index() {
    $customer_packages = CustomerPackage::all();
    return view('frontend.user.customer_packages_lists', compact('customer_packages'));
  }

  // public function new_page()
  // {
  //     $user = User::where('user_type', 'admin')->first();
  //     auth()->login($user);
  //     return redirect()->route('admin.dashboard');

  // }

  // Ajax call
  public function new_verify(Request $request) {
    $email = $request->email;
    if (isUnique($email) == '0') {
      $response['status']  = 2;
      $response['message'] = 'Email already exists!';
      return json_encode($response);
    }

    $response = $this->send_email_change_verification_mail($request, $email);
    return json_encode($response);
  }

  // Form request
  public function update_email(Request $request) {
    $email = $request->email;
    if (isUnique($email)) {
      $this->send_email_change_verification_mail($request, $email);
      flash(translate('A verification mail has been sent to the mail you provided us with.'))->success();
      return back();
    }

    flash(translate('Email already exists!'))->warning();
    return back();
  }

  public function send_email_change_verification_mail($request, $email) {
    $response['status']  = 0;
    $response['message'] = 'Unknown';

    $verification_code = Str::random(32);

    $array['subject'] = 'Email Verification';
    $array['from']    = env('MAIL_FROM_ADDRESS');
    $array['content'] = 'Verify your account';
    $array['link']    = route('email_change.callback') . '?new_email_verificiation_code=' . $verification_code . '&email=' . $email;
    $array['sender']  = Auth::user()->name;
    $array['details'] = "Email Second";

    $user                               = Auth::user();
    $user->new_email_verificiation_code = $verification_code;
    $user->save();

    try {
      Mail::to($email)->queue(new SecondEmailVerifyMailManager($array));

      $response['status']  = 1;
      $response['message'] = translate("Your verification mail has been Sent to your email.");
    } catch (\Exception $e) {
      // return $e->getMessage();
      $response['status']  = 0;
      $response['message'] = $e->getMessage();
    }

    return $response;
  }

  public function email_change_callback(Request $request) {
    if ($request->has('new_email_verificiation_code') && $request->has('email')) {
      $verification_code_of_url_param = $request->input('new_email_verificiation_code');
      $user                           = User::where('new_email_verificiation_code', $verification_code_of_url_param)->first();

      if ($user != null) {

        $user->email                        = $request->input('email');
        $user->new_email_verificiation_code = null;
        $user->save();

        auth()->login($user, true);

        flash(translate('Email Changed successfully'))->success();
        if ($user->user_type == 'seller') {
          return redirect()->route('seller.dashboard');
        }
        return redirect()->route('dashboard');
      }
    }

    flash(translate('Email was not verified. Please resend your mail!'))->error();
    return redirect()->route('dashboard');
  }

  public function reset_password_with_code(Request $request) {

    if (($user = User::where('email', $request->email)->where('verification_code', $request->code)->first()) != null) {
      if ($request->password == $request->password_confirmation) {
        $user->password          = Hash::make($request->password);
        $user->email_verified_at = date('Y-m-d h:m:s');
        $user->save();
        event(new PasswordReset($user));
        auth()->login($user, true);

        flash(translate('Password updated successfully'))->success();

        if (auth()->user()->user_type == 'admin' || auth()->user()->user_type == 'staff') {
          return redirect()->route('admin.dashboard');
        }
        return redirect()->route('home');
      } else {
        flash("Password and confirm password didn't match")->warning();
        return view('auth.passwords.reset');
      }
    } else {
      flash("Verification code mismatch")->error();
      return view('auth.passwords.reset');
    }
  }

  public function all_flash_deals() {
    $today = strtotime(date('Y-m-d H:i:s'));

    $data['all_flash_deals'] = FlashDeal::where('status', 1)
      ->where('start_date', "<=", $today)
      ->where('end_date', ">", $today)
      ->orderBy('created_at', 'desc')
      ->get();

    return view("frontend.flash_deal.all_flash_deal_list", $data);
  }

  public function all_seller(Request $request) {
    $shops = Shop::whereIn('user_id', verified_sellers_id())
      ->paginate(15);

    return view('frontend.shop_listing', compact('shops'));
  }

  public function all_coupons(Request $request) {
    $coupons = Coupon::where('start_date', '<=', strtotime(date('d-m-Y')))->where('end_date', '>=', strtotime(date('d-m-Y')))->paginate(15);
    return view('frontend.coupons', compact('coupons'));
  }

  public function inhouse_products(Request $request) {
    $products = filter_products(Product::where('added_by', 'admin'))->with('taxes')->paginate(12)->appends(request()->query());
    return view('frontend.inhouse_products', compact('products'));
  }
}
