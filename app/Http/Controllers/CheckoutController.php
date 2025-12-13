<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Address;
use App\Models\Carrier;
use App\Models\Cart;
use App\Models\Category;
use App\Models\CombinedOrder;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\PaymentHistory;
use App\Models\PaymentUrl;

use App\Models\Manager41Order;
use App\Models\Manager41CombinedOrder;



use App\Models\WebhookLog;
use App\Utility\NotificationUtility;
use App\Utility\WhatsAppUtility;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Session;
use App\Services\WhatsAppWebService;

use Illuminate\Support\Facades\DB;
use App\Jobs\SyncPaymentTranscationHistory;
use App\Http\Controllers\AdminStatementController;
use App\Services\StatementCalculationService;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Crypt;
class CheckoutController extends Controller {

    protected $WhatsAppWebService;  
    protected $statementCalculationService;

    public function __construct(StatementCalculationService $statementCalculationService)
    {
        $this->statementCalculationService = $statementCalculationService;
    }


     private function isActingAs41Manager(): bool
    {
        // 1) Agar impersonation chal raha hai to staff user ko check karo
        $user = null;
        if (session()->has('staff_id')) {
            $user = User::find((int) session('staff_id'));
        }

        // 2) Warna current logged-in user
        if (!$user) {
            $user = Auth::user();
        }
        if (!$user) {
            return false;
        }

        // 3) Normalize and match
        $title = strtolower(trim((string) $user->user_title));
        $type  = strtolower(trim((string) $user->user_type));

        if ($type === 'manager_41') {
            return true;
        }

        $aliases = ['manager_41'];
        return in_array($title, $aliases, true);
    }

  //check the selected payment gateway and redirect to that controller accordingly
  public function checkout(Request $request) {
    // Minumum order amount check    
    if (get_setting('minimum_order_amount_check') == 1) {
      $subtotal = 0;
      foreach (Cart::where('user_id', Auth::user()->id)->get() as $key => $cartItem) {
        $product = Product::find($cartItem['product_id']);
        if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606)){
          if ($cartItem['quantity'] >= $product->piece_by_carton) {
            $subtotal += $cartItem['price'] * 0.98 * $cartItem['quantity'];
          } else {
            $subtotal += $cartItem['price'] * $cartItem['quantity'];
          }
        }else{
          if ($cartItem['quantity'] >= $product->piece_by_carton) {
            $subtotal += cart_product_price($cartItem, $product, false, false,Auth::user()->id) * 0.98 * $cartItem['quantity'];
          } else {
            $subtotal += cart_product_price($cartItem, $product, false, false,Auth::user()->id) * $cartItem['quantity'];
          }
        }       
      }
      
      if ($subtotal < get_setting('minimum_order_amount')) {
       
        flash(translate('You order amount is less then the minimum order amount'))->warning();
        return redirect()->route('home');
      }
    }

    // echo "<pre>"; print_r($request->session()->get('combined_order_id'));die;
    // echo "<pre>"; print_r($request->all());die;
    // Minumum order amount check end

    if ($request->payment_option != null) {
      
   
      // if(Auth::user()->id == '24185'){
      //  echo "<pre>"; print_r($request->all()); die;
        (new OrderController)->store($request);
        $carts = Cart::where('user_id', Auth::user()->id)->get();
        
        $request->session()->put('payment_type', 'cart_payment');

        $data['combined_order_id'] = $request->session()->get('combined_order_id');
        $request->session()->put('payment_data', $data);
        
        if ($request->session()->get('combined_order_id') != null) {          
          // If block for Online payment, wallet and cash on delivery. Else block for Offline payment
          $decorator = __NAMESPACE__ . '\\Payment\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $request->payment_option))) . "Controller";
          if (class_exists($decorator)) {
            return (new $decorator)->pay($request);
          } else {
            $combined_order      = CombinedOrder::findOrFail($request->session()->get('combined_order_id'));
            $manual_payment_data = array(
              'name'   => $request->payment_option,
              'amount' => $combined_order->grand_total,
              'trx_id' => $request->trx_id,
              'photo'  => $request->photo,
            );
            foreach ($combined_order->orders as $order) {
              $order->manual_payment      = 1;
              $order->manual_payment_data = json_encode($manual_payment_data);
              $order->payment_discount    = 0;
              $order->grand_total         = $order->grand_total - $order->payment_discount;
              // WhatsAppUtility::orderDetail(Auth::user(), $order);
              $order->save();
            }
            // $combined_order->grand_total = $combined_order->grand_total - 0.02 * $combined_order->grand_total;
            $combined_order->save();
            $orderData = Order::where('combined_order_id', $combined_order->id)->first();
            $total = $orderData->grand_total;
            // if(Auth::user()->id != '24185'){
            //   // Push order data to Salezing
            //   $result=array();
            //   $result['code']= $orderData->code;
            //   $response = Http::withHeaders([
            //       'Content-Type' => 'application/json',
            //   ])->post('https://mazingbusiness.com/api/v2/order-push', $result);
            //   \Log::info('Salzing Order Push From Website Status: '  . json_encode($response->json(), JSON_PRETTY_PRINT));
            // }else{
            //   //------------ Calculation for pass the order to salzing or not start 
            //   $calculationResponse = $this->statementCalculationService->calculateForOneCompany(Auth::user()->id, 'live');
            //   // Decode the JSON response to an array
            //   $calculationResponse = $calculationResponse->getData(true);
      
            //   $overdueAmount = $calculationResponse['overdueAmount'];
            //   $dueAmount = $calculationResponse['dueAmount'];
      
            //   $credit_limit = Auth::user()->credit_limit;
            //   // $credit_days = Auth::user()->credit_days;
            //   $current_limit = $dueAmount - $overdueAmount;
            //   $currentAvailableCreditLimit = $credit_limit - $current_limit;
            //   $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
            //   //-------------------------- This is for case 2 ------------------------------
            //   if($current_limit == 0){        
            //       if($total > $currentAvailableCreditLimit){
            //           $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
            //       }else{
            //           $exceededAmount = $overdueAmount;
            //       }
      
            //   }else{
            //       if($total > $currentAvailableCreditLimit)
            //       {
            //           $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
            //       }else{
            //           $exceededAmount = $overdueAmount;
            //       }
            //   }
            //   //----------------------------------------------------------------------------
            //   $payableAmount = $exceededAmount + $cash_and_carry_item_subtotal;
            //   if($payableAmount == 0){
            //     $result=array();
            //     $result['code']= $orderData->code;
            //     $response = Http::withHeaders([
            //         'Content-Type' => 'application/json',
            //     ])->post('https://mazingbusiness.com/api/v2/order-push', $result);
            //     \Log::info('Salzing Order Push From Website Status: '  . json_encode($response->json(), JSON_PRETTY_PRINT));
            //   }
            // }
            flash(translate('Your order has been placed successfully. Please submit payment information from purchase history'))->success();
            return redirect()->route('order_confirmed');
          }
        }
    } else {
      flash(translate('Select Payment Option.'))->warning();
      return back();
    }
    // }else{
    //   echo "Site is maintanance mode. We will back shortly.";
    // }
  }

  //redirects to this method after a successfull checkout
  public function checkout_done($combined_order_id, $payment) {
    $combined_order = CombinedOrder::findOrFail($combined_order_id);

    foreach ($combined_order->orders as $key => $order) {
      $order                  = Order::findOrFail($order->id);
      $order->payment_status  = 'paid';
      $order->payment_details = $payment;
      if (addon_is_activated('otp_system') && $order->payment_status == 'paid') {
        WhatsAppUtility::orderDetail(Auth::user(), $order);
      }
      $order->save();

      calculateCommissionAffilationClubPoint($order);
    }
    Session::put('combined_order_id', $combined_order_id);
    return redirect()->route('order_confirmed');
  }

  public function get_shipping_info(Request $request) {
    if (Auth::user()->name === 'User' || Auth::user()->company_name === 'My company') {
      flash(translate('Please complete your profile first'))->success();
      return redirect()->route('profile');
    }
    $carts = Cart::where('user_id', Auth::user()->id)->get();
    // if (Session::has('cart') && count(Session::get('cart')) > 0) {
    if ($carts && count($carts) > 0) {
      $categories = Category::all();
      return view('frontend.shipping_info', compact('categories', 'carts'));
    }
    flash(translate('Your cart is empty'))->success();
    return back();
  }

  public function store_shipping_info(Request $request) {


  
    if ($request->address_id == null) {
      flash(translate("Please add shipping address"))->warning();
      return back();
    }

    $carts = Cart::where('user_id', Auth::user()->id)->get();
   
    if ($carts->isEmpty()) {
      flash(translate('Your cart is empty'))->warning();
      return redirect()->route('home');
    }

    foreach ($carts as $key => $cartItem) {
      $cartItem->address_id = $request->address_id;
      $cartItem->save();
    }

  
    $carrier_list = array();
    if (get_setting('shipping_type') == 'carrier_wise_shipping') {
      $zone = \App\Models\Country::where('id', $carts[0]['address']['country_id'])->first()->zone_id;

      $carrier_query = Carrier::query();
      $carrier_query->whereIn('id', function ($query) use ($zone) {
        $query->select('carrier_id')->from('carrier_range_prices')
          ->where('zone_id', $zone);
      })->orWhere('free_shipping', 1);
      $carrier_list = $carrier_query->get();
    }
   

    $shipping_info = Address::where('id', $carts[0]['address_id'])->first();

 

    $total         = 0;
    $tax           = 0;
    $shipping      = 0;
    $subtotal      = 0;

   

    if ($carts && count($carts) > 0) {
      foreach ($carts as $key => $cartItem) {
        $product = Product::find($cartItem['product_id']);
        $tax += cart_product_tax($cartItem, $product, false, Auth::user()->id) * $cartItem['quantity'];
        if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606)){
          $subtotal += $cartItem['price'] * $cartItem['quantity'];
        }else{
          $subtotal += cart_product_price($cartItem, $product, false, false, Auth::user()->id) * $cartItem['quantity'];
        }
        

        if (get_setting('shipping_type') != 'carrier_wise_shipping' || $request['shipping_type_' . $product->user_id] == 'pickup_point') {
          if ($request['shipping_type_' . $product->user_id] == 'pickup_point') {
            $cartItem['shipping_type'] = 'pickup_point';
            $cartItem['pickup_point']  = $request['pickup_point_id_' . $product->user_id];
          } else {
            $cartItem['shipping_type'] = 'carrier';
            $cartItem['carrier_id']    = $request['shipper'];
            $carrier                   = Carrier::find($request['shipper']);
            if ($request['shipper'] == 372) {
              $custom_shipper = $request->custom_shipper_name . ',' . $request->custom_shipper_gstin . ',' . $request->custom_shipper_phone;
            } else {
              // $custom_shipper = $carrier->name . ',' . $carrier->gstin . ',' . $carrier->phone;
              $custom_shipper = "Testing";
            }
            if (!Auth::user()->shipper_allocation) {
              $user                     = Auth::user();
              $user->shipper_allocation = [['warehouse_id' => 1, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 2, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 3, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 4, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 5, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null]];
              $user->save();
            }
          }
          $cartItem['shipping_cost'] = 0;
          if ($cartItem['shipping_type'] == 'carrier') {
            $cartItem['shipping_cost'] = getShippingCost($carts, $key);
          }
        } else {
          $cartItem['shipping_type'] = 'carrier';
          $cartItem['carrier_id']    = $request['carrier_id_' . $product->user_id];
          $cartItem['shipping_cost'] = getShippingCost($carts, $key, $cartItem['carrier_id']);
        }

        $shipping += $cartItem['shipping_cost'];
        $cartItem->save();
      }
      // $total = $subtotal + $tax + $shipping;
      $total = $subtotal + $shipping;
      // echo $subtotal; die;
     
      return view('frontend.payment_select', compact('carts', 'shipping_info', 'total', 'custom_shipper'));
    } else {
      flash(translate('Your Cart was empty'))->warning();
      return redirect()->route('home');
    }
    // return view('frontend.delivery_info', compact('carts', 'carrier_list'));
    // return view('frontend.payment_select', compact('carts', 'shipping_info', 'total', 'custom_shipper'));

  }

  public function get_shipping_info_v02(Request $request) {
    if (Auth::user()->name === 'User' || Auth::user()->company_name === 'My company') {
      flash(translate('Please complete your profile first'))->success();
      return redirect()->route('profile');
    }
    $carts = Cart::where('user_id', Auth::user()->id)->get();
    // if (Session::has('cart') && count(Session::get('cart')) > 0) {
    if ($carts && count($carts) > 0) {
      $categories = Category::all();
      return view('frontend.shipping_info_v02', compact('categories', 'carts'));
    }
    flash(translate('Your cart is empty'))->success();
    return back();
  }
  
  public function store_shipping_info_v02(Request $request) {
  
    if ($request->address_id == null) {
      flash(translate("Please add shipping address"))->warning();
      return back();
    }

    if ($this->isActingAs41Manager()) {
        // Manager-41 flow -> sirf flagged items
       $carts = Cart::where('user_id', Auth::user()->id)->where('is_manager_41', 1)->get();
    } else{
      $carts = Cart::where('user_id', Auth::user()->id)->get();
    }
   

    
   
    if ($carts->isEmpty()) {
      flash(translate('Your cart is empty'))->warning();
      return redirect()->route('home');
    }

    foreach ($carts as $key => $cartItem) {
      $cartItem->address_id = $request->address_id;
      $cartItem->save();
    }

  
    $carrier_list = array();
    if (get_setting('shipping_type') == 'carrier_wise_shipping') {
      $zone = \App\Models\Country::where('id', $carts[0]['address']['country_id'])->first()->zone_id;

      $carrier_query = Carrier::query();
      $carrier_query->whereIn('id', function ($query) use ($zone) {
        $query->select('carrier_id')->from('carrier_range_prices')
          ->where('zone_id', $zone);
      })->orWhere('free_shipping', 1);
      $carrier_list = $carrier_query->get();
    }
   

    $shipping_info = Address::where('id', $carts[0]['address_id'])->first();

 

    $total         = 0;
    $tax           = 0;
    $shipping      = 0;
    $subtotal      = 0;

   

    if ($carts && count($carts) > 0) {
      foreach ($carts as $key => $cartItem) {
        $product = Product::find($cartItem['product_id']);
        $tax += cart_product_tax($cartItem, $product, false, Auth::user()->id) * $cartItem['quantity'];
        if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606)){
          $subtotal += $cartItem['price'] * $cartItem['quantity'];
        }else{
          $subtotal += cart_product_price($cartItem, $product, false, false, Auth::user()->id) * $cartItem['quantity'];
        }
        

        if (get_setting('shipping_type') != 'carrier_wise_shipping' || $request['shipping_type_' . $product->user_id] == 'pickup_point') {
          if ($request['shipping_type_' . $product->user_id] == 'pickup_point') {
            $cartItem['shipping_type'] = 'pickup_point';
            $cartItem['pickup_point']  = $request['pickup_point_id_' . $product->user_id];
          } else {
            $cartItem['shipping_type'] = 'carrier';
            $cartItem['carrier_id']    = $request['shipper'];
            $carrier                   = Carrier::find($request['shipper']);
            if ($request['shipper'] == 372) {
              $custom_shipper = $request->custom_shipper_name . ',' . $request->custom_shipper_gstin . ',' . $request->custom_shipper_phone;
            } else {
              // $custom_shipper = $carrier->name . ',' . $carrier->gstin . ',' . $carrier->phone;
              $custom_shipper = "Testing";
            }
            if (!Auth::user()->shipper_allocation) {
              $user                     = Auth::user();
              $user->shipper_allocation = [['warehouse_id' => 1, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 2, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 3, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 4, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 5, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null]];
              $user->save();
            }
          }
          $cartItem['shipping_cost'] = 0;
          if ($cartItem['shipping_type'] == 'carrier') {
            $cartItem['shipping_cost'] = getShippingCost($carts, $key);
          }
        } else {
          $cartItem['shipping_type'] = 'carrier';
          $cartItem['carrier_id']    = $request['carrier_id_' . $product->user_id];
          $cartItem['shipping_cost'] = getShippingCost($carts, $key, $cartItem['carrier_id']);
        }

        $shipping += $cartItem['shipping_cost'];
        $cartItem->save();
      }
      // $total = $subtotal + $tax + $shipping;
      $total = $subtotal + $shipping;
      // echo $subtotal; die;

      // --------------------------------- Calculate Due and Overdue amount --------------------------------------
      // $currentDate = date('Y-m-d');
      // $currentMonth = date('m');
      // $currentYear = date('Y');
      // $overdueDateFrom="";
      // $overdueAmount="0";

      // $openingBalance="0";
      // $drBalance = 0;
      // $crBalance = 0;
      // $dueAmount = 0;

      // $userData = User::where('id', Auth::user()->id)->first();
      // $userAddressData = Address::where('acc_code',"!=","")->where('user_id',$userData->id)->groupBy('gstin')->orderBy('acc_code','ASC')->get();
      // foreach($userAddressData as $key=>$value){
      //     $party_code = $value->acc_code;
      //     if ($currentMonth >= 4) {
      //         $fy_form_date = date('Y-04-01'); // Start of financial year
      //         $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
      //     } else {
      //         $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
      //         $fy_to_date = date('Y-03-31'); // Current year March
      //     }
      //     $from_date = $fy_form_date;
      //     $to_date = $fy_to_date;
      //     $headers = [
      //         'authtoken' => '65d448afc6f6b',
      //     ];
      //     $body = [
      //         'party_code' => $party_code,
      //         'from_date' => $from_date,
      //         'to_date' =>  $to_date,
      //     ];
      //     $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
      //     \Log::info('Received response from Salzing API For Sync Statement Overdue Calculation', [
      //         'status' => $response->status(),
      //         'party_code' =>  $party_code,
      //         'body' => $response->body()
      //     ]);
      //     if ($response->successful()) {
      //         $getData = $response->json();
      //         if(!empty($getData) AND isset($getData['data']) AND !empty($getData['data'])){
      //             $getData = $getData['data'];
      //             $closingBalanceResult = array_filter($getData, function ($entry) {
      //                 return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
      //             });
      //             $closingEntry = reset($closingBalanceResult);
      //             $cloasingDrAmount = $closingEntry['dramount'];
      //             $cloasingCrAmount = $closingEntry['cramount'];          
      //             $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
      //             if($cloasingCrAmount > 0){
      //                 $drBalanceBeforeOVDate = 0;
      //                 $crBalanceBeforeOVDate = 0;
      //                 $getData = array_reverse($getData);
      //                 foreach($getData as $ovKey=>$gValue){
      //                     if($gValue['ledgername'] != 'closing C/f...'){
      //                         if(strtotime($gValue['trn_date']) > strtotime($overdueDateFrom)){
      //                             // $drBalanceBeforeOVDate += $ovValue['dramount'];
      //                             $crBalanceBeforeOVDate += $gValue['cramount'];
      //                         }else{
      //                             $drBalanceBeforeOVDate += $gValue['dramount'];
      //                             $crBalanceBeforeOVDate += $gValue['cramount'];
      //                         }
      //                     }
      //                     if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
      //                         $drBalance = $drBalance + $gValue['dramount'];
      //                         $dueAmount = $dueAmount + $gValue['dramount'];
      //                     } 
      //                     if($gValue['cramount'] != '0.00' AND $gValue['ledgername'] != 'closing C/f...') {
      //                         $crBalance = $crBalance + $gValue['cramount'];
      //                         $dueAmount = $dueAmount - $gValue['cramount'];
      //                     }

      //                 }
      //                 $overdueAmount = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
      //             }
      //         }
      //     }
      // }
      $calculationResponse = $this->statementCalculationService->calculateForOneCompany(Auth::user()->id, 'live');
      // Decode the JSON response to an array
      $calculationResponse = $calculationResponse->getData(true);

      $overdueAmount = $calculationResponse['overdueAmount'];
      $dueAmount = $calculationResponse['dueAmount'];
      // $overdueAmount = ceil($overdueAmount);
      // $dueAmount = ceil($dueAmount);
      // --------------------------------- Calculate Due and Overdue amount --------------------------------------

      $is41Manager=$this->isActingAs41Manager();
     
      return view('frontend.payment_select_v02', compact(
        'carts',
       'shipping_info',
       'total', 
       'custom_shipper',
       'overdueAmount',
       'dueAmount',
       'is41Manager'));
    } else {
      flash(translate('Your Cart was empty'))->warning();
      return redirect()->route('home');
    }
    // return view('frontend.delivery_info', compact('carts', 'carrier_list'));
    // return view('frontend.payment_select', compact('carts', 'shipping_info', 'total', 'custom_shipper'));

  }

  public function store_delivery_info(Request $request) {
    $carts          = Cart::where('user_id', Auth::user()->id)->get();
    $custom_shipper = '';

    if ($carts->isEmpty()) {
      flash(translate('Your cart is empty'))->warning();
      return redirect()->route('home');
    }

    $shipping_info = Address::where('id', $carts[0]['address_id'])->first();
    $total         = 0;
    $tax           = 0;
    $shipping      = 0;
    $subtotal      = 0;

    if ($carts && count($carts) > 0) {
      foreach ($carts as $key => $cartItem) {
        $product = Product::find($cartItem['product_id']);
        $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
        $subtotal += cart_product_price($cartItem, $product, false, false, Auth::user()->id) * $cartItem['quantity'];

        if (get_setting('shipping_type') != 'carrier_wise_shipping' || $request['shipping_type_' . $product->user_id] == 'pickup_point') {
          if ($request['shipping_type_' . $product->user_id] == 'pickup_point') {
            $cartItem['shipping_type'] = 'pickup_point';
            $cartItem['pickup_point']  = $request['pickup_point_id_' . $product->user_id];
          } else {
            $cartItem['shipping_type'] = 'carrier';
            $cartItem['carrier_id']    = $request['shipper'];
            $carrier                   = Carrier::find($request['shipper']);
            if ($request['shipper'] == 372) {
              $custom_shipper = $request->custom_shipper_name . ',' . $request->custom_shipper_gstin . ',' . $request->custom_shipper_phone;
            } else {
              // $custom_shipper = $carrier->name . ',' . $carrier->gstin . ',' . $carrier->phone;
              $custom_shipper = "Testing";
            }
            if (!Auth::user()->shipper_allocation) {
              $user                     = Auth::user();
              $user->shipper_allocation = [['warehouse_id' => 1, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 2, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 3, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 4, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 5, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null]];
              $user->save();
            }
          }
          $cartItem['shipping_cost'] = 0;
          if ($cartItem['shipping_type'] == 'carrier') {
            $cartItem['shipping_cost'] = getShippingCost($carts, $key);
          }
        } else {
          $cartItem['shipping_type'] = 'carrier';
          $cartItem['carrier_id']    = $request['carrier_id_' . $product->user_id];
          $cartItem['shipping_cost'] = getShippingCost($carts, $key, $cartItem['carrier_id']);
        }

        $shipping += $cartItem['shipping_cost'];
        $cartItem->save();
      }
      $total = $subtotal + $tax + $shipping;
      return view('frontend.payment_select', compact('carts', 'shipping_info', 'total', 'custom_shipper'));
    } else {
      flash(translate('Your Cart was empty'))->warning();
      return redirect()->route('home');
    }
  }

  public function apply_coupon_code(Request $request) {
    $coupon           = Coupon::where('code', $request->code)->first();
    $response_message = array();
    $custom_shipper   = $request->custom_shipper;
    $shipper_details  = explode(',', $request->custom_shipper);

    if ($coupon != null) {
      if (!$coupon->customer_id || ($coupon->customer_id && ($coupon->customer_id == Auth::user()->id))) {
        if (strtotime(date('d-m-Y')) >= $coupon->start_date && strtotime(date('d-m-Y')) <= $coupon->end_date) {
          $coupon_usage_count = CouponUsage::where('user_id', Auth::user()->id)->where('coupon_id', $coupon->id)->count();
          if (($coupon->new_user_only && !Auth::user()->load(['orders' => function ($query) {
            $query->where('payment_status', '!=', 'unpaid');
          }])->orders->count()) || !$coupon->new_user_only) {
            if ($coupon_usage_count <= $coupon->max_usage_count) {
              $coupon_details = json_decode($coupon->details);

              $carts = Cart::where('user_id', Auth::user()->id)
                ->where('owner_id', $coupon->user_id)
                ->get();

              $coupon_discount = 0;

              if ($coupon->type == 'cart_base') {
                $subtotal = 0;
                $tax      = 0;
                $shipping = 0;
                foreach ($carts as $key => $cartItem) {
                  $product = Product::find($cartItem['product_id']);
                  if ($cartItem['is_carton']) {
                    $subtotal += cart_product_price($cartItem, $product, false, false, Auth::user()->id) * $cartItem['quantity'] * $product->stocks->first()->piece_per_carton;
                    $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'] * $product->stocks->first()->piece_per_carton;
                  } else {
                    $subtotal += cart_product_price($cartItem, $product, false, false, Auth::user()->id) * $cartItem['quantity'];
                    $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                  }
                  $shipping += $cartItem['shipping_cost'];
                }
                $sum = $subtotal + $tax + $shipping;
                if ($sum >= $coupon_details->min_buy) {
                  if ($coupon->discount_type == 'percent') {
                    $coupon_discount = ($sum * $coupon->discount) / 100;
                    if ($coupon_discount > $coupon_details->max_discount) {
                      $coupon_discount = $coupon_details->max_discount;
                    }
                  } elseif ($coupon->discount_type == 'amount') {
                    $coupon_discount = $coupon->discount;
                  }

                }
              } elseif ($coupon->type == 'product_base') {
                foreach ($carts as $key => $cartItem) {
                  $product = Product::find($cartItem['product_id']);
                  foreach ($coupon_details as $key => $coupon_detail) {
                    if ($coupon_detail->product_id == $cartItem['product_id']) {
                      if ($coupon->discount_type == 'percent') {
                        if ($cartItem['is_carton']) {
                          $coupon_discount += (cart_product_price($cartItem, $product, false, false, Auth::user()->id) * $coupon->discount / 100) * $cartItem['quantity'] * $product->stocks->first()->piece_per_carton;
                        } else {
                          $coupon_discount += (cart_product_price($cartItem, $product, false, false, Auth::user()->id) * $coupon->discount / 100) * $cartItem['quantity'];
                        }
                      } elseif ($coupon->discount_type == 'amount') {
                        if ($cartItem['is_carton']) {
                          $coupon_discount += $coupon->discount * $cartItem['quantity'] * $product->stocks->first()->piece_per_carton;
                        } else {
                          $coupon_discount += $coupon->discount * $cartItem['quantity'];
                        }
                      }
                    }
                  }
                }
              }

              if ($coupon_discount > 0) {
                Cart::where('user_id', Auth::user()->id)
                  ->where('owner_id', $coupon->user_id)
                  ->update(
                    [
                      'discount'       => $coupon_discount / count($carts),
                      'coupon_code'    => $request->code,
                      'coupon_applied' => 1,
                    ]
                  );
                $response_message['response'] = 'success';
                $response_message['message']  = translate('Coupon has been applied');
              } else {
                $response_message['response'] = 'warning';
                $response_message['message']  = translate('This coupon is not applicable to your cart products!');
              }

            } else {
              $response_message['response'] = 'warning';
              $response_message['message']  = translate('You already used this coupon!');
            }
          } else {
            $response_message['response'] = 'warning';
            $response_message['message']  = translate('This coupon is only available for new users!');
          }
        } else {
          $response_message['response'] = 'warning';
          $response_message['message']  = translate('Coupon expired!');
        }
      } else {
        $response_message['response'] = 'danger';
        $response_message['message']  = translate('Invalid coupon!');
      }
    } else {
      $response_message['response'] = 'danger';
      $response_message['message']  = translate('Invalid coupon!');
    }

    $carts         = Cart::where('user_id', Auth::user()->id)->get();
    $shipping_info = Address::where('id', $carts[0]['address_id'])->first();

    $returnHTML = view('frontend.partials.cart_summary', compact('coupon', 'carts', 'shipping_info', 'shipper_details', 'custom_shipper'))->render();
    return response()->json(array('response_message' => $response_message, 'html' => $returnHTML));
  }

  public function remove_coupon_code(Request $request) {
    Cart::where('user_id', Auth::user()->id)
      ->update(
        [
          'discount'       => 0.00,
          'coupon_code'    => '',
          'coupon_applied' => 0,
        ]
      );
    $custom_shipper  = $request->custom_shipper;
    $shipper_details = explode(',', $request->custom_shipper);

    $coupon = Coupon::where('code', $request->code)->first();
    $carts  = Cart::where('user_id', Auth::user()->id)
      ->get();

    $shipping_info = Address::where('id', $carts[0]['address_id'])->first();

    return view('frontend.partials.cart_summary', compact('coupon', 'carts', 'shipping_info', 'shipper_details', 'custom_shipper'));
  }

  public function apply_club_point(Request $request) {
    if (addon_is_activated('club_point')) {

      $point = $request->point;

      if (Auth::user()->point_balance >= $point) {
        $request->session()->put('club_point', $point);
        flash(translate('Point has been redeemed'))->success();
      } else {
        flash(translate('Invalid point!'))->warning();
      }
    }
    return back();
  }

  public function remove_club_point(Request $request) {
    $request->session()->forget('club_point');
    return back();
  }

  // private function generatePaymentUrl($party_code, $payment_for)
  //   {
  //       $client = new \GuzzleHttp\Client();
  //       $response = $client->post('https://mazingbusiness.com/api/v2/payment/generate-url', [
  //           'json' => [
  //               'party_code' => $party_code,
  //               'payment_for' => $payment_for
  //           ]
  //       ]);

  //       $data = json_decode($response->getBody(), true);
  //       return $data['url'] ?? '';  // Return the generated URL or an empty string if it fails
  //   }

  public function order_confirmed() {
    $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));

    Cart::where('user_id', $combined_order->user_id)->delete();

    //Session::forget('club_point');
    //Session::forget('combined_order_id');

    foreach ($combined_order->orders as $order) {
      NotificationUtility::sendOrderPlacedNotification($order);
    }
    if(Auth::user()->id != '24185'){
      //Whatsapp Code Implementation
      $first_order=$combined_order->orders->first();
      $user = DB::table('users')
              ->where('id',  $first_order->user_id)
              ->first();
              
      $manager_phone_number = DB::table('users')
      ->where('id', $user->manager_id)
      ->pluck('phone')
      ->first();   
      $to =[json_decode($first_order->shipping_address)->phone,"+919709555576",$manager_phone_number];
      // $to =[""];
      $client_address = DB::table('addresses')
      ->where('id', $first_order->address_id)
      ->first();    
      $company_name=$client_address->company_name;//json_decode($first_order->shipping_address)->company_name;
      $order_id=$first_order->code;
      $date=date('d-m-Y H:i A', $first_order->date);
      $total_amount=$first_order->grand_total;
      $invoiceController=new InvoiceController();
      $file_url=$invoiceController->invoice_file_path($first_order->id);

      // edited by dipak start


      $party_code=Auth::user()->party_code;
      //$payment_url=$this->generatePaymentUrl($party_code, $payment_for="custom_amount");
      $getOrder = Order::with('orderDetails')->where('combined_order_id',$combined_order->id)->first();
      $payment_url=$this->generatePaymentUrl($getOrder->code, $payment_for="payable_amount");
      
      // Extract the part after 'pay-amount/'
      $fileName = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
      $button_variable_encode_part=$fileName;

      // edited by dipak end

      $file_name="Order-".$order_id;
      $templateData = [
          'name' => 'utility_order_template',
          'language' => 'en_US', 
          'components' => [
            [
              'type' => 'header',
                  'parameters' => [
                      [
                          'type' => 'document', // Use 'image', 'video', etc. as needed
                          'document' => [
                              'link' => $file_url,
                              'filename' => $file_name,
                          ]
                      ]
                  ]
              ],
              [
                  'type' => 'body',
                  'parameters' => [
                      ['type' => 'text','text' => $company_name],
                      ['type' => 'text','text' => $order_id],
                      ['type' => 'text','text' => $date],
                      ['type' => 'text','text' => $total_amount ]
                  ],
              ],
              [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            [
                                "type" => "text",
                                "text" => $button_variable_encode_part // Replace $button_text with the actual Parameter for the button.
                            ],
                        ],
              ],
            
          ],
      ];

      $this->WhatsAppWebService=new WhatsAppWebService();
      foreach($to as $person_to_send){
        $response = $this->WhatsAppWebService->sendTemplateMessage($person_to_send, $templateData);
      }    
      //whatsapp code end
    }

    // Generate Payment QR Code
    $qrCodeUrl = "";
    $merchantTranId = "";
    $qrmerchantTranId = "";
    $orderCode = "";
    $payment_amount = 0;
    $paymentAmount = 0;
    $overdueFlag = 0;
    $cart_grand_total = 0;
    // if(Auth::user()->id == '24185'){
      $getOrder = Order::where('combined_order_id',$combined_order->id)->first();
      $combined_order_data=$combined_order->orders->first();
      $payment_amount = $combined_order_data->grand_total;
      $cart_grand_total = $combined_order_data->grand_total;
      
      //Payment amount calculation start
      $userAddressData = Address::where('user_id', Auth::user()->id)->where('id',$getOrder->address_id)->first();
      // $userAddressData = Address::where('user_id', '26326')->first();
      $getstatementData = json_decode($userAddressData->statement_data);
      $balance = 0;
      $totalDueAmount = 0;
      $totalOverdueAmount = 0;
      if(!empty($getstatementData)){       
        foreach ($getstatementData as $address) {
          if($address->ledgername != 'closing C/f...'){
            if($address->ledgername == 'Opening b/f...'){
                $balance = $address->dramount != "0.00" ? $address->dramount : -$address->cramount;
            }else{
                $balance += $address->dramount - $address->cramount;
            }  
          }                      
        }
        $payment_amount = $balance + $combined_order_data->grand_total;
      }

      $overdueFlag = $balance > 0 ? 1 : 0;
      $balance = $balance < 0 ? '-₹' . number_format(abs($balance),2) : '₹' . number_format(abs($balance),2);
      $paymentAmount = '₹' . number_format(abs($payment_amount),2);
      $cart_grand_total = '₹' . number_format(abs($cart_grand_total),2);
      
      //Due Overdue calculation start
      $response = $this->iciciPaymentQrCodeGenerater($payment_amount,$getOrder->code);
      // $response = $this->iciciPaymentQrCodeGenerater($payableAmount,$getOrder->code);
      $responseData = json_decode($response->getContent(), true);
      $qrCodeUrl = $responseData['qrCodeUrl'];
      $qrmerchantTranId = $responseData['merchantTranId'];
      $orderCode = $getOrder->code;
    // }
    return view('frontend.order_confirmed', compact('combined_order','qrCodeUrl','qrmerchantTranId','orderCode','balance','paymentAmount','cart_grand_total','overdueFlag'));
  }


public function order_confirmed_v02_manager41() {
    // echo Session::get('combined_order_id'); die;
    $combined_order = Manager41CombinedOrder::findOrFail(Session::get('combined_order_id'));

    Cart::where('user_id', $combined_order->user_id)
     ->where('is_manager_41', 1)
     ->delete();

    //Session::forget('club_point');
    //Session::forget('combined_order_id');

    foreach ($combined_order->orders as $order) {
      NotificationUtility::sendOrderPlacedNotification($order);
    }

    // if(Auth::user()->id != '24185'){
      //Whatsapp Code Implementation
      $first_order=$combined_order->orders->first();
      $user = DB::table('users')
              ->where('id',  $first_order->user_id)
              ->first();
              
      $manager_phone_number = DB::table('users')
      ->where('id', $user->manager_id)
      ->pluck('phone')
      ->first();   
      $to =[json_decode($first_order->shipping_address)->phone,"+919709555576",$manager_phone_number,'+919730377752','+919930791952'];
      // $to =[""];
      $client_address = DB::table('addresses')
      ->where('id', $first_order->address_id)
      ->first();    
      $company_name=$client_address->company_name;//json_decode($first_order->shipping_address)->company_name;
      $order_id=$first_order->code;
      $date=date('d-m-Y H:i A', $first_order->date);
      $total_amount=$first_order->grand_total;
      $invoiceController=new InvoiceController();
      $file_url=$invoiceController->invoice_file_path($first_order->id);

      // edited by dipak start


      // $party_code=Auth::user()->party_code;
      // $payment_url=$this->generatePaymentUrl($party_code, $payment_for="custom_amount");
      // $getOrder = Order::with('orderDetails')->where('combined_order_id',$combined_order->id)->first();
      // echo $getOrder->code; exit;
      // $payment_url=$this->generatePaymentUrl($getOrder->code, $payment_for="payable_amount");
      
    // }

    // Generate Payment QR Code
    $qrCodeUrl = "";
    $merchantTranId = "";
    $qrmerchantTranId = "";
    $orderCode = "";
    $payment_amount = 0;
    $paymentAmount = 0;
    $overdueFlag = 0;
    $cart_grand_total = 0;
    // if(Auth::user()->id == '24185'){
      $getOrder = Manager41Order::with('orderDetails')->where('combined_order_id',$combined_order->id)->first();
      $combined_order_data=$combined_order->orders->first();
      
      $payment_amount = $combined_order_data->grand_total;
      $cart_grand_total = $combined_order_data->grand_total;
      
      //Payment amount calculation start
      $userAddressData = Address::where('user_id', Auth::user()->id)->where('id',$getOrder->address_id)->first();
      // $userAddressData = Address::where('user_id', '26326')->first();
      $getstatementData = json_decode($userAddressData->statement_data);
      $balance = 0;
      $totalDueAmount = 0;
      $totalOverdueAmount = 0;
      if(!empty($getstatementData)){       
        foreach ($getstatementData as $address) {
          if($address->ledgername != 'closing C/f...'){
            if($address->ledgername == 'Opening b/f...'){
                $balance = $address->dramount != "0.00" ? $address->dramount : -$address->cramount;
            }else{
                $balance += $address->dramount - $address->cramount;
            }  
          }                      
        }
        $payment_amount = $balance + $combined_order_data->grand_total;
      }


      $total = 0;
      $cash_and_carry_item_flag = 0;
      $cash_and_carry_item_subtotal = 0;
      $normal_item_flag = 0;
      $normal_item_subtotal = 0;
      $dueAmount = session('dueAmount');
      $overdueAmount = session('overdueAmount');
      // $carts = Cart::where('user_id', Auth::user()->id)->orWhere('customer_id',Auth::user()->id)->get();
      foreach($getOrder->orderDetails as $key => $value){
          if($value['cash_and_carry_item'] == 1 && Auth::check() && Auth::user()->credit_days > 0){
              $cash_and_carry_item_flag = 1;
              // $cash_and_carry_item_subtotal += $value['price'] * $value['quantity'];
              $cash_and_carry_item_subtotal += $value['price'];
          }else{
              $normal_item_flag = 1;
              // $normal_item_subtotal += $value['price'] * $value['quantity'];
              $normal_item_subtotal += $value['price'];
              $total += $value['price'];
          }
          // $total += $value['price'] * $value['quantity'];          
      }

      $credit_limit = Auth::user()->credit_limit;
      $current_limit = $dueAmount - $overdueAmount;
      $currentAvailableCreditLimit = $credit_limit - $current_limit;
      $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
     

      //-------------------------- This is for case 2 ------------------------------
      if($current_limit == 0){        
          if($total > $currentAvailableCreditLimit){
              $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
          }else{
              $exceededAmount = $overdueAmount;
          }
      }else{
          if($total > $currentAvailableCreditLimit)
          {
              $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
          }else{
              $exceededAmount = $overdueAmount;
          }
      }
    //----------------------------------------------------------------------------
      $payableAmount = $exceededAmount + $cash_and_carry_item_subtotal;

      $overdueFlag = $balance > 0 ? 1 : 0;
      $balance = $balance < 0 ? '-₹' . number_format(abs($balance),2) : '₹' . number_format(abs($balance),2);
      $paymentAmount = '₹' . number_format(abs($payment_amount),2);
      $cart_grand_total = '₹' . number_format(abs($cart_grand_total),2);
      
      //Due Overdue calculation start
      // $response = $this->iciciPaymentQrCodeGenerater($payment_amount,$getOrder->code);
      if(Auth::user()->id == '24185'){
        $payableAmount = 10;
      }
      
      // For payment start
      $response = $this->iciciPaymentQrCodeGenerater($payableAmount,$getOrder->code);
      $responseData = json_decode($response->getContent(), true);
      $qrCodeUrl = $responseData['qrCodeUrl'];
      $qrmerchantTranId = $responseData['merchantTranId'];

      // For payment end
      
      $orderCode = $getOrder->code;

      // edited by dipak end

      $file_name="Order-".$order_id;
      $templateData = [
          'name' => 'utility_order_template',
          'language' => 'en_US', 
          'components' => [
            [
              'type' => 'header',
                  'parameters' => [
                      [
                          'type' => 'document', // Use 'image', 'video', etc. as needed
                          'document' => [
                              'link' => $file_url,
                              'filename' => $file_name,
                          ]
                      ]
                  ]
              ],
              [
                  'type' => 'body',
                  'parameters' => [
                      ['type' => 'text','text' => $company_name],
                      ['type' => 'text','text' => $order_id],
                      ['type' => 'text','text' => $date],
                      ['type' => 'text','text' => number_format($total_amount, 2) ]
                  ],
              ],
              // [
              //           'type' => 'button',
              //           'sub_type' => 'url',
              //           'index' => '0',
              //           'parameters' => [
              //               [
              //                   "type" => "text",
              //                   "text" => $button_variable_encode_part // Replace $button_text with the actual Parameter for the button.
              //               ],
              //           ],
              // ],
            
          ],
      ];

      $this->WhatsAppWebService=new WhatsAppWebService();
      foreach($to as $person_to_send){
         // $response = $this->WhatsAppWebService->sendTemplateMessage($person_to_send, $templateData);
      }


      //whatsapp code end
      if ((isset($dueAmount) && $dueAmount > 0) ||(isset($overdueAmount) && $overdueAmount > 0)) {

            //$this->additionalWhatsapp($user, $dueAmount, $overdueAmount, $first_order);
            // $this->additionalWhatsapp($user, $first_order);
            // $this->sendHeadManagerAlert($first_order,$user);
      }
      
     // Manager-41 flag
    $is_41 = true;

    return view('frontend.order_confirmed_v02', compact('combined_order','getOrder','qrCodeUrl','qrmerchantTranId','orderCode','payableAmount','balance','paymentAmount','cart_grand_total','overdueFlag','is_41'));
  }

  public function order_confirmed_v02() {


     // 🔐 If this order was placed via “Login as Customer” by a Manager_41,
    // route to the Manager 41 version.
    $staffId = session('staff_id');
    if (!empty($staffId)) {
        $is41 = User::where('id', $staffId)
            ->where(function ($q) {
                $q->where('user_type', 'manager_41')
                  ->orWhereRaw('LOWER(user_title) = ?', ['manager_41']);
            })
            ->exists();

        if ($is41) {
            return $this->order_confirmed_v02_manager41();
        }
    }


    // echo Session::get('combined_order_id'); die;
    $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));

    Cart::where('user_id', $combined_order->user_id)->delete();

    //Session::forget('club_point');
    //Session::forget('combined_order_id');

    foreach ($combined_order->orders as $order) {
      NotificationUtility::sendOrderPlacedNotification($order);
    }

    // if(Auth::user()->id != '24185'){
      //Whatsapp Code Implementation
      $first_order=$combined_order->orders->first();
      $user = DB::table('users')
              ->where('id',  $first_order->user_id)
              ->first();
              
      $manager_phone_number = DB::table('users')
      ->where('id', $user->manager_id)
      ->pluck('phone')
      ->first();   
      $to =[json_decode($first_order->shipping_address)->phone,"+919709555576",$manager_phone_number,'+919730377752','+919930791952','+919894753728'];
      // $to =[""];
      $client_address = DB::table('addresses')
      ->where('id', $first_order->address_id)
      ->first();    
      $company_name=$client_address->company_name;//json_decode($first_order->shipping_address)->company_name;
      $order_id=$first_order->code;
      $date=date('d-m-Y H:i A', $first_order->date);
      $total_amount=$first_order->grand_total;
      $invoiceController=new InvoiceController();
      $file_url=$invoiceController->invoice_file_path($first_order->id);

      // edited by dipak start


      // $party_code=Auth::user()->party_code;
      // $payment_url=$this->generatePaymentUrl($party_code, $payment_for="custom_amount");
      // $getOrder = Order::with('orderDetails')->where('combined_order_id',$combined_order->id)->first();
      // echo $getOrder->code; exit;
      // $payment_url=$this->generatePaymentUrl($getOrder->code, $payment_for="payable_amount");
      
    // }

    // Generate Payment QR Code
    $qrCodeUrl = "";
    $merchantTranId = "";
    $qrmerchantTranId = "";
    $orderCode = "";
    $payment_amount = 0;
    $paymentAmount = 0;
    $overdueFlag = 0;
    $cart_grand_total = 0;
    // if(Auth::user()->id == '24185'){
      $getOrder = Order::with('orderDetails')->where('combined_order_id',$combined_order->id)->first();
      $combined_order_data=$combined_order->orders->first();
      $grandTotal = $getOrder->grand_total + (($getOrder->grand_total * $getOrder->conveince_fee_percentage)/100);
      $conveince_fee = ($getOrder->grand_total * $getOrder->conveince_fee_percentage)/100;
      $payment_amount = $grandTotal;
      $cart_grand_total = $getOrder->grand_total;
      
      //Payment amount calculation start
      $userAddressData = Address::where('user_id', Auth::user()->id)->where('id',$getOrder->address_id)->first();
      // $userAddressData = Address::where('user_id', '26326')->first();
      $getstatementData = json_decode($userAddressData->statement_data);
      $balance = 0;
      $totalDueAmount = 0;
      $totalOverdueAmount = 0;
      if(!empty($getstatementData)){       
        foreach ($getstatementData as $address) {
          if($address->ledgername != 'closing C/f...'){
            if($address->ledgername == 'Opening b/f...'){
                $balance = $address->dramount != "0.00" ? $address->dramount : -$address->cramount;
            }else{
                $balance += $address->dramount - $address->cramount;
            }  
          }                      
        }
        $payment_amount = $balance + $grandTotal;
      }


      $total = 0;
      $cash_and_carry_item_flag = 0;
      $cash_and_carry_item_subtotal = 0;
      $normal_item_flag = 0;
      $normal_item_subtotal = 0;
      $dueAmount = session('dueAmount');
      $overdueAmount = session('overdueAmount');
      // $carts = Cart::where('user_id', Auth::user()->id)->orWhere('customer_id',Auth::user()->id)->get();
      foreach($getOrder->orderDetails as $key => $value){
          if($value['cash_and_carry_item'] == 1 && Auth::check() && Auth::user()->credit_days > 0){
              $cash_and_carry_item_flag = 1;
              // $cash_and_carry_item_subtotal += $value['price'] * $value['quantity'];
              $cash_and_carry_item_subtotal += $value['price'];
          }else{
              $normal_item_flag = 1;
              // $normal_item_subtotal += $value['price'] * $value['quantity'];
              $normal_item_subtotal += $value['price'];
              $total += $value['price'];
          }
          // $total += $value['price'] * $value['quantity'];          
      }

      $credit_limit = Auth::user()->credit_limit;
      $current_limit = $dueAmount - $overdueAmount;
      $currentAvailableCreditLimit = $credit_limit - $current_limit;
      $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
      //-------------------------- This is for case 2 ------------------------------
      // if($current_limit == 0){
      //     $exceededAmount = ($total - $currentAvailableCreditLimit);
      //     if($exceededAmount == 0){
      //         $exceededAmount = $overdueAmount;
      //     }elseif($exceededAmount < 0){
      //         $exceededAmount = 0;
      //     }else{
      //         $exceededAmount += $overdueAmount;
      //     }
      // }
      //----------------------------------------------------------------------------

      //-------------------------- This is for case 2 ------------------------------
      if($current_limit == 0){        
          if($total > $currentAvailableCreditLimit){
              $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
          }else{
              $exceededAmount = $overdueAmount;
          }
      }else{
          if($total > $currentAvailableCreditLimit)
          {
              $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
          }else{
              $exceededAmount = $overdueAmount;
          }
      }
    //----------------------------------------------------------------------------
      $payableAmount = $exceededAmount + $cash_and_carry_item_subtotal;

      $overdueFlag = $balance > 0 ? 1 : 0;
      $balance = $balance < 0 ? '-₹' . number_format(abs($balance),2) : '₹' . number_format(abs($balance),2);
      $paymentAmount = '₹' . number_format(abs($payment_amount),2);
      $cart_grand_total = '₹' . number_format(abs($cart_grand_total),2);
      
      //Due Overdue calculation start
      // $response = $this->iciciPaymentQrCodeGenerater($payment_amount,$getOrder->code);
      if(Auth::user()->id == '24185'){
        $payableAmount = 10;
      }
      
      // For payment start
      $response = $this->iciciPaymentQrCodeGenerater($payableAmount,$getOrder->code);
      $responseData = json_decode($response->getContent(), true);
      $qrCodeUrl = $responseData['qrCodeUrl'];
      $qrmerchantTranId = $responseData['merchantTranId'];

      // if($qrCodeUrl != ""){
      //   $payment_url=$this->generatePaymentUrl($getOrder->code, "payable_amount");
      //   // Extract the part after 'pay-amount/'
      //   $fileName = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
      //   $button_variable_encode_part=$fileName;
      // }

      // For payment end
      
      $orderCode = $getOrder->code;

      // edited by dipak end

      $file_name="Order-".$order_id;
      $templateData = [
          'name' => 'utility_order_template',
          'language' => 'en_US', 
          'components' => [
            [
              'type' => 'header',
                  'parameters' => [
                      [
                          'type' => 'document', // Use 'image', 'video', etc. as needed
                          'document' => [
                              'link' => $file_url,
                              'filename' => $file_name,
                          ]
                      ]
                  ]
              ],
              [
                  'type' => 'body',
                  'parameters' => [
                      ['type' => 'text','text' => $company_name],
                      ['type' => 'text','text' => $order_id],
                      ['type' => 'text','text' => $date],
                      ['type' => 'text','text' => number_format($total_amount, 2) ]
                  ],
              ],
              // [
              //           'type' => 'button',
              //           'sub_type' => 'url',
              //           'index' => '0',
              //           'parameters' => [
              //               [
              //                   "type" => "text",
              //                   "text" => $button_variable_encode_part // Replace $button_text with the actual Parameter for the button.
              //               ],
              //           ],
              // ],
            
          ],
      ];

      $this->WhatsAppWebService=new WhatsAppWebService();
      foreach($to as $person_to_send){
          $response = $this->WhatsAppWebService->sendTemplateMessage($person_to_send, $templateData);
      }


      //whatsapp code end
      if ((isset($dueAmount) && $dueAmount > 0) ||(isset($overdueAmount) && $overdueAmount > 0)) {

            //$this->additionalWhatsapp($user, $dueAmount, $overdueAmount, $first_order);
            $this->additionalWhatsapp($user, $first_order);
            $this->sendHeadManagerAlert($first_order,$user);
      }


      if(Auth::user()->id == '24185'){
        if ((isset($dueAmount) && $dueAmount > 0) ||(isset($overdueAmount) && $overdueAmount > 0)) {
          $this->sendHeadManagerAlert($first_order,$user);
          $this->additionalWhatsapp($user, $dueAmount, $overdueAmount, $first_order);
        }
      }
     

      // dd($getOrder);
    // }

      $is_41 = false;

    return view('frontend.order_confirmed_v02', compact('combined_order','getOrder','qrCodeUrl','qrmerchantTranId','orderCode','payableAmount','balance','paymentAmount','cart_grand_total','overdueFlag','conveince_fee','is_41'));
  }


  public function sendHeadManagerAlert($first_order, $user)
  {
      // Step 1: Get order with address info
      $order = Order::select(
              'orders.id', 
              'orders.created_at as date', 
              'orders.code', 
              'addresses.acc_code', 
              'addresses.company_name'
          )
          ->join('addresses', 'orders.address_id', '=', 'addresses.id')
          ->where('orders.id', $first_order->id)
          ->first();

      if (!$order) {
          return; // If order or address not found, exit safely
      }

      $acc_code = $order->acc_code;
      $company_name = $order->company_name;
      $order_date = date('d-m-Y h:i A', strtotime($order->date));

      // Step 2: Get total due and overdue from all addresses for this user
      $dueData = DB::table('addresses')
          ->where('user_id', $user->id)
          ->selectRaw('
              SUM(CAST(NULLIF(due_amount, "") AS DECIMAL(10,2))) as total_due,
              SUM(CAST(NULLIF(overdue_amount, "") AS DECIMAL(10,2))) as total_overdue
          ')
          ->first();

      $dueAmount = $dueData->total_due ?? 0;
      $overdueAmount = $dueData->total_overdue ?? 0;

      // Step 3: Get overdue days from helper
      $overdueDays = getFirstOverdueDays(encrypt($acc_code))->getData()->overdue_days ?? 'N/A';

      // Step 4: Generate statement PDF using AdminStatementController
      $adminStatementController = new AdminStatementController();
      $pdf_url = $adminStatementController->generateStatementPdf($acc_code, $dueAmount, $overdueAmount, $user);

      $pdfUrl = $pdf_url ?? '';
      $statement_button = basename($pdfUrl);

      // Step 5: Build WhatsApp template data
      $templateData = [
          'name' => 'utility_overdue_alert_headmanager',
          'language' => 'en_US',
          'components' => [
              [
                  'type' => 'body',
                  'parameters' => [
                      ['type' => 'text', 'text' => $company_name],                      // {{1}} Customer Name
                      ['type' => 'text', 'text' => $order->code],                       // {{2}} Order Code
                      ['type' => 'text', 'text' => $order_date],                        // {{3}} Order Date
                      ['type' => 'text', 'text' => number_format($dueAmount, 2)],      // {{4}} Total Due
                      ['type' => 'text', 'text' => number_format($overdueAmount, 2)],  // {{5}} Total Overdue
                      ['type' => 'text', 'text' => preg_replace('/\s*days$/', '', $overdueDays)], // {{6}} Overdue Days
                  ],
              ],
              [
                  'type' => 'button',
                  'sub_type' => 'url',
                  'index' => '0',
                  'parameters' => [
                      ['type' => 'text', 'text' => $statement_button],
                  ],
              ],
          ],
      ];

      // Step 6: Send to all head managers
      $this->WhatsAppWebService = new WhatsAppWebService();

      $headManagers = [
          '+919709555576', // Kolkata
          '+919730377752', // Delhi
          '+919930791952', // Mumbai
      ];

      foreach ($headManagers as $number) {
          $this->WhatsAppWebService->sendTemplateMessage($number, $templateData);
      }
  }

  public function _back_sendHeadManagerAlert($first_order,$user)
  {
      // Step 1: Get order with address info
      $order = Order::select(
              'orders.id', 
              'orders.created_at as date', 
              'orders.code', 
              'addresses.acc_code', 
              'addresses.company_name'
          )
          ->join('addresses', 'orders.address_id', '=', 'addresses.id')
          ->where('orders.id', $first_order->id)
          ->first();


      if (!$order) {
          return; // If order or address not found, exit safely
      }

      $acc_code = $order->acc_code;
      $company_name = $order->company_name;
      $order_date = date('d-m-Y h:i A', strtotime($order->date));

      // Step 2: Fetch due and overdue amount from address
      $address = Address::where('acc_code', $acc_code)->first();
      $dueAmount = $address->due_amount ?? 0;
      $overdueAmount = $address->overdue_amount ?? 0;

      // Step 3: Get overdue days using helper
      $overdueDays = getFirstOverdueDays(encrypt($acc_code))->getData()->overdue_days ?? 'N/A';

      $adminStatementController = new AdminStatementController();
      $party_code = $order->acc_code; // Ensure this is available (from earlier $user DB call)
      $pdf_url=$adminStatementController->generateStatementPdf($party_code, $address->due_amount, $address->overdue_amount, $user);
    
      if (isset($pdf_url)) {
      $pdfUrl = $pdf_url;
      $statement_button = basename($pdfUrl); // Use this as button variable
      } else {
          $pdfUrl = '';
          $statement_button = '';
      }
      // Step 4: Build template payload
      $templateData = [
          'name' => 'utility_overdue_alert_headmanager',
          'language' => 'en_US',
          'components' => [
              [
                  'type' => 'body',
                  'parameters' => [
                      ['type' => 'text', 'text' => $company_name],                        // {{1}} Customer Name
                      ['type' => 'text', 'text' => $order->code],                            // {{2}} Code
                      ['type' => 'text', 'text' => $order_date],                          // {{3}} Order Date
                      ['type' => 'text', 'text' => number_format($dueAmount, 2)],        // {{4}} Due
                      ['type' => 'text', 'text' => number_format($overdueAmount, 2)],    // {{5}} Overdue
                      ['type' => 'text', 'text' => preg_replace('/\s*days$/', '', $overdueDays)],                         // {{6}} Days
                  ],
              ],
              [
                      'type' => 'button',
                      'sub_type' => 'url',
                      'index' => '0',
                      'parameters' => [
                          [
                              'type' => 'text',
                              'text' => $statement_button, // e.g. Statement-26326.pdf
                          ],
                      ],
              ],
          ],
      ];

      // Step 6: Send Message
      $this->WhatsAppWebService = new WhatsAppWebService();

      $headManagers = [
          '+919709555576', // Kolkata
          '+919730377752', // Delhi
          '+919930791952', // Mumbai
       
      ];

      foreach ($headManagers as $number) {
          $this->WhatsAppWebService->sendTemplateMessage($number, $templateData);
      }
      //$this->WhatsAppWebService->sendTemplateMessage('7044300330', $templateData);
  }

  private function getManagerPhone($managerId)
  {
      $managerData = User::where('id', $managerId)
          ->select('phone')
          ->first();

      return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
  }

  public function additionalWhatsapp($user, $first_order)
  {
      // Step 1: Get Order Address Info
      $orderWithAddress = Order::select('orders.id', 'addresses.acc_code', 'addresses.company_name')
          ->join('addresses', 'orders.address_id', '=', 'addresses.id')
          ->where('orders.id', $first_order->id)
          ->first();

      if (!$orderWithAddress) {
          return; // Exit if no address found
      }

      $party_code = $orderWithAddress->acc_code;
      $company_name = $orderWithAddress->company_name ?? 'Customer';
      $managerPhone = $this->getManagerPhone($user->manager_id);

      // Step 2: Calculate due and overdue from addresses table
      $dueData = DB::table('addresses')
          ->where('user_id', $user->id)
          ->selectRaw('
              SUM(CAST(NULLIF(due_amount, "") AS DECIMAL(10,2))) as total_due,
              SUM(CAST(NULLIF(overdue_amount, "") AS DECIMAL(10,2))) as total_overdue
          ')
          ->first();

      $dueAmount = $dueData->total_due ?? 0;
      $overdueAmount = $dueData->total_overdue ?? 0;

      // Step 3: Generate PDF if needed
      $adminStatementController = new AdminStatementController();
      $pdf_url = $adminStatementController->generateStatementPdf($party_code, $dueAmount, $overdueAmount, $user);

      $pdfUrl = $pdf_url ?? '';
      $statement_button = basename($pdfUrl);

      // Step 4: Only send if due or overdue exists
      if ($dueAmount > 0 || $overdueAmount > 0) {
          // Prepare the message
          if ($dueAmount > 0 && $overdueAmount > 0) {
              $messageLine = "You have a due of *₹" . number_format($dueAmount, 2) . "* and an overdue of *₹" . number_format($overdueAmount, 2) . "*.";
          } elseif ($dueAmount > 0) {
              $messageLine = "You have a due amount of *₹" . number_format($dueAmount, 2) . "*.";
          } else {
              $messageLine = "You have an overdue amount of *₹" . number_format($overdueAmount, 2) . "*.";
          }

          // WhatsApp Template
          $reminder_template_data = [
              'name' => 'utility_due_overdue_template',
              'language' => 'en_US',
              'components' => [
                  [
                      'type' => 'body',
                      'parameters' => [
                          ['type' => 'text', 'text' => $company_name],
                          ['type' => 'text', 'text' => $messageLine],
                      ],
                  ],
                  [
                      'type' => 'button',
                      'sub_type' => 'url',
                      'index' => '0',
                      'parameters' => [
                          ['type' => 'text', 'text' => $statement_button],
                      ],
                  ],
              ],
          ];

          // Step 5: Send WhatsApp to customer and manager
          $customer_phone = json_decode($first_order->shipping_address)->phone ?? null;

          $this->WhatsAppWebService = new WhatsAppWebService();
          if ($customer_phone) {
              $this->WhatsAppWebService->sendTemplateMessage($customer_phone, $reminder_template_data);
          }

          if ($managerPhone) {
              $this->WhatsAppWebService->sendTemplateMessage($managerPhone, $reminder_template_data);
          }
      }
  }

  public function _back_additionalWhatsapp($user,$dueAmount,$overdueAmount,$first_order){
   
      $orderWithAddress = Order::select('orders.id', 'addresses.acc_code', 'addresses.company_name')
        ->join('addresses', 'orders.address_id', '=', 'addresses.id')
        ->where('orders.id', $first_order->id)
        ->first();
      $managerPhone=$this->getManagerPhone($user->manager_id);

      if ($orderWithAddress) {
          $acc_code = $orderWithAddress->acc_code;
          $company_name = $orderWithAddress->company_name;

          // You can now use:
          $party_code = $acc_code;
      }
    // Additional Message start
        $adminStatementController = new AdminStatementController();
        $party_code = $orderWithAddress->acc_code; // Ensure this is available (from earlier $user DB call)
        $pdf_url=$adminStatementController->generateStatementPdf($party_code, $dueAmount, $overdueAmount, $user);
      
        if (isset($pdf_url)) {
        $pdfUrl = $pdf_url;
        $statement_button = basename($pdfUrl); // Use this as button variable
        } else {
            $pdfUrl = '';
            $statement_button = '';
        }
        
        // STEP 2: Send reminder message if due or overdue exists

         if ($dueAmount > 0 || $overdueAmount > 0) {
      

          // Format the message line with bold amounts
          if ($dueAmount > 0 && $overdueAmount > 0) {
              $messageLine = "You have a due of *₹" . number_format($dueAmount, 2) . "* and an overdue of *₹" . number_format($overdueAmount, 2) . "*.";
          } elseif ($dueAmount > 0) {
              $messageLine = "You have a due amount of *₹" . number_format($dueAmount, 2) . "*.";
          } elseif ($overdueAmount > 0) {
              $messageLine = "You have an overdue amount of *₹" . number_format($overdueAmount, 2) . "*.";
          }

          // WhatsApp template data
          $reminder_template_data = [
              'name' => 'utility_due_overdue_template',
              'language' => 'en_US',
              'components' => [
                  [
                      'type' => 'body',
                      'parameters' => [
                          ['type' => 'text', 'text' => $user->company_name], // {{1}}
                          ['type' => 'text', 'text' => $messageLine],   // {{2}}
                      ],
                  ],
                  [
                      'type' => 'button',
                      'sub_type' => 'url',
                      'index' => '0',
                      'parameters' => [
                          [
                              'type' => 'text',
                              'text' => $statement_button, // e.g. Statement-26326.pdf
                          ],
                      ],
                  ],
              ],
          ];

          // echo "<pre>";
          // print_r($reminder_template_data);
          // die();

         
          // Send to customer only (not manager or internal team)
          $customer_phone = json_decode($first_order->shipping_address)->phone;

          $this->WhatsAppWebService = new WhatsAppWebService();
          // $this->WhatsAppWebService->sendTemplateMessage('7044300330', $reminder_template_data);
          $response= $this->WhatsAppWebService->sendTemplateMessage($customer_phone, $reminder_template_data);
         
           $this->WhatsAppWebService->sendTemplateMessage($managerPhone, $reminder_template_data);
      }   

      //additional Message End 
  }

  // public function order_confirmed_test() {    
  //   return view('frontend.order_confirmed_test');
  // }

  public function verifyAndPay(Request $request) {
    $upi_id = $request->upi_id;
    $bill_number = decrypt($request->bill_number);
    $getOrderData = Order::where('code',$bill_number)->first();
    // return response()->json(['status' => 'Error', 'message' => 'Didn\'t get any order.']);
    if($getOrderData !== NULL){
      //Payment amount calculation start
      $payment_amount = 0;
      $userAddressData = Address::where('user_id', Auth::user()->id)
          ->select('due_amount', 'overdue_amount')
          ->get();
      $totalDueAmount = 0;
      $totalOverdueAmount = 0;
      foreach ($userAddressData as $address) {
        // Add due and overdue amounts to totals
        $totalDueAmount += $address->due_amount;
        $totalOverdueAmount += $address->overdue_amount;
      }
      $payment_amount = $totalOverdueAmount + $getOrderData->grand_total;
      //Due Overdue calculation start
      $response = $this->paymentWithVirtualAddress($payment_amount,$bill_number,$upi_id);
      // echo "<pre>"; print_r($response); die;
      $responseData = json_decode($response->getContent(), true);
      return response()->json(['status' => 'Success', 'merchantTranId' => $responseData['merchantTranId'], 'message' => $responseData['message']]);
    }else{
      return response()->json(['status' => 'Error', 'merchantTranId' => '0', 'message' => 'Didn\'t get any order.']);
    }
  }

  public function verifyAndPayFromPaymentPage(Request $request) {
    $upi_id = $request->upi_id;
    $bill_number = decrypt($request->bill_number);
    $payment_amount = decrypt($request->amount);
    $response = $this->paymentWithVirtualAddress($payment_amount,$bill_number,$upi_id);
    // echo "<pre>"; print_r($response); die;
    $responseData = json_decode($response->getContent(), true);
    return response()->json(['status' => 'Success', 'merchantTranId' => $responseData['merchantTranId'], 'message' => $responseData['message']]);
  }

  private function iciciPaymentQrCodeGenerater($amount="",$billNumber=""){
      // $getPaymentHistoryData = PaymentHistory::where('bill_number',$billNumber)->where('api_name','QR_CODE')->first();
      
      // if(empty($getPaymentHistoryData) AND $amount >= 1){
      if($amount >= 1){   
        // Set MID, VPA, and other variables
        $mid = env('MERCHANT_ID'); //'610853';
        $vpa = env('VPA'); //'aceuat@icici';
        $merchantName = env('MARCHANT_NAME'); //'Ace Tools Pvt. Ltd'; // Merchant name can be dynamic
        $api_url = env('API_URL_QR'). $mid; // 'https://apibankingonesandbox.icicibank.com/api/MerchantAPI/UPI/v0/QR3/' . $mid;
        $merchantTranId = uniqid();
        // $amount ='1.00';
        // Payload to be encrypted
        $payload = json_encode([
            'merchantId' => $mid,
            'terminalId' => env('TERMINAL_ID'), // '5411',
            'amount' => number_format($amount, 2, '.', ''),
            'merchantTranId' => $merchantTranId,
            'billNumber' => $billNumber,
            'validatePayerAccFlag' => 'N'
        ]);

        // Encrypt the payload
        $encrypted_payload = $this->encrypt_payload($payload, storage_path('pay/public/key/rsa_apikey.txt'));
        
        // Send API request
        $response = $this->send_api_request($api_url, $encrypted_payload);

        // Decrypt the response
        $decrypted_response = $this->decrypt_response($response, storage_path('pay/private/key/private_cer.pem'));
		  
        // Handle response and generate UPI URL and QR code
        $response_data = json_decode($decrypted_response, true);
        // echo "<pre>"; print_r($response_data);die;
        $qrCodeUrl = "";
        if ($response_data['success'] == 'true') {
            $refId = $response_data['refId'];
            $currency = 'INR';
            $mccCode = $response_data['terminalId'];

            // Generate UPI URL
            $upiUrl = "upi://pay?pa=$vpa&pn=$merchantName&tr=$refId&am=$amount&cu=$currency&mc=$mccCode";
            $encodedUpiUrl = urlencode($upiUrl);

            // Generate QR code URL
            $qrCodeUrl = "https://quickchart.io/qr?text=" . $encodedUpiUrl . "&size=250";
            
            $paymentHistoryData = array();
            $paymentHistoryData['qrCodeUrl']=$qrCodeUrl;
            $paymentHistoryData['user_id']=Auth::user()->id;
            $paymentHistoryData['party_code']=Auth::user()->party_code;
            $paymentHistoryData['bill_number']=$billNumber;
            $paymentHistoryData['merchantId']=$mid;
            $paymentHistoryData['subMerchantId']=$mid;
            $paymentHistoryData['terminalId']=$response_data['terminalId'];
            $paymentHistoryData['merchantTranId']=$merchantTranId;
            $paymentHistoryData['refId']=$refId;
            $paymentHistoryData['merchantName']=$merchantName;
            $paymentHistoryData['vpa']=$vpa;
            $paymentHistoryData['amount']=$amount;
            $paymentHistoryData['api_name']='QR_CODE';
            PaymentHistory::create($paymentHistoryData);


        }
      // }else if($getPaymentHistoryData->status == 'PENDING' AND $amount >= 1){
      //   $qrCodeUrl = $getPaymentHistoryData->qrCodeUrl;
      //   $merchantTranId = $getPaymentHistoryData->merchantTranId;
      }else{
        $qrCodeUrl ='';
        $merchantTranId = '';
      }
      $data = [
        'qrCodeUrl'=> $qrCodeUrl,
        'merchantTranId'=> $merchantTranId
      ];
      return response()->json($data);
  }

  public function transactionDetails(){
    SyncPaymentTranscationHistory::dispatch();
    return response()->json([
        'message' => 'Successfully sync the statement.'
    ]);
  }

  private function paymentWithVirtualAddress($amount="", $billNumber="", $upi_id=""){
    
    // Set MID, VPA, and other variables
    $mid = env('MERCHANT_ID');
    $vpa = env('VPA');
    $merchantName = 'Ace Tools Pvt Ltd';
    $terminalId = env('TERMINAL_ID');
    $api_url =  env('API_URL_VIRTUAL_ADDRESS_PAYMENT'). $mid;
    $merchantTranId = uniqid();
    // $upi_id = 'burhanuddinimani5@okicici';
    // $billNumber = "12345";
    // $amount = "0.01";
    // Payload to be encrypted
    // echo date('d/m/Y H:i A');
    $payload = json_encode([
      "payerVa" => trim($upi_id), // "burhanuddinimani5@okicici",
      "amount" => number_format($amount, 2, '.', ''),
      "note" => "collect pay request",
      "collectByDate" => date('d/m/Y H:i A'), // date('d/m/Y H:I A', strtotime('+1 day')),
      "merchantId" => $mid,
      "merchantName" => $merchantName,
      "subMerchantId" => '8702385',
      "subMerchantName" => $merchantName,
      "terminalId" => $terminalId,
      "merchantTranId" => $merchantTranId,
      "billNumber" => $billNumber
    ]);

    // Encrypt the payload
    $encrypted_payload = $this->encrypt_payload($payload, storage_path('pay/public/key/rsa_apikey.txt'));
    //  echo $encrypted_payload; die;
    // Send API request
    $response = $this->send_api_request($api_url, $encrypted_payload);
    // Decrypt the response
    $decrypted_response = $this->decrypt_response($response, storage_path('pay/private/key/private_cer.pem'));

    // Handle response and generate UPI URL and QR code
    $response_data = json_decode($decrypted_response, true);
    // print_r($response_data);die; 
    if ($response_data['success'] == 'true') {
      $paymentHistoryData = array();
      $paymentHistoryData['user_id']=Auth::user()->id;
      $paymentHistoryData['party_code']=Auth::user()->party_code;
      $paymentHistoryData['bill_number']=$billNumber;
      $paymentHistoryData['merchantId']=$mid;
      $paymentHistoryData['subMerchantId']=$mid;
      $paymentHistoryData['terminalId']=$response_data['terminalId'];
      $paymentHistoryData['merchantTranId']=$merchantTranId;
      $paymentHistoryData['merchantName']=$merchantName;
      $paymentHistoryData['vpa']=$vpa;
      $paymentHistoryData['amount']=$amount;
      $paymentHistoryData['api_name']='UPI_ID';
      PaymentHistory::create($paymentHistoryData);
      $data = [
        'message'=> $response_data['message'],
        'merchantTranId'=> $merchantTranId
      ];
      return response()->json($data);
    }else{
      $msgArray = explode('|',$response_data['message']);
      if(isset($msgArray[1])){
        $data = [
          'message'=> $msgArray[1],
          'merchantTranId'=> 0
        ];
      }else{
        $data = [
          'message'=> $response_data['message'],
          'merchantTranId'=> 0
        ];
      }
      return response()->json($data);     
    }
  }

  private function generatePaymentUrl($order_code, $payment_for)
  {
      $client = new \GuzzleHttp\Client();
      $response = $client->post('https://mazingbusiness.com/api/v2/payment/generate-url', [
          'json' => [
              'party_code' => $order_code,
              'payment_for' => $payment_for
          ]
      ]);

      $data = json_decode($response->getBody(), true);
      return $data['url'] ?? '';  // Return the generated URL or an empty string if it fails
  }
  
  public function checkPaymentStatus(Request $request){
    $getWebhookLogStatus = WebhookLog::where('merchantTranId',$request->merchantTranId)->first();
    if($getWebhookLogStatus!==NULL){
      $getPayloadData = json_decode($getWebhookLogStatus->payload,true);
      if($getPayloadData['TxnStatus'] == "FAILURE"){

        //WHATSAPP CODE when failed - edited by dipak start 
          $transaction_id=$getPayloadData['merchantTranId'];

          $paymentHistory = DB::table('payment_histories')->where('merchantTranId', $transaction_id)->first();
          $payment_for =  $paymentHistory->payment_for;
          // $acc_code=$paymentHistory->bill_number; // this is wrong
          $acc_code=$paymentHistory->party_code; // Edit By Atanu


          $address = DB::table('addresses')->where('acc_code', $acc_code)->first();
          $customer_name = $address->company_name;
          $due_amount = $address->due_amount;
          $overdue_amount = $address->overdue_amount;

          $user_id = $address->user_id;
          // Step 1: Search the users table to get the manager_id
          $user = DB::table('users')->where('id', $user_id)->first();
          $manager = DB::table('users')->where('id', $user->manager_id)->first();
          $manager_phone = $manager->phone;

          $payment_amt=$getPayloadData['PayerAmount'];
          // $customer_name=$getPayloadData['PayerName'];
        
          $payment_url=$this->generatePaymentUrl($acc_code, $payment_for);
          $adminStatementController = new AdminStatementController();
          $pdf_url=$adminStatementController->generateStatementPdf($acc_code, $due_amount, $overdue_amount, $user);

          // Extract the part after 'pay-amount/'
          $fileName = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
          $button_variable_encode_part=$fileName;
          // $file_url="https://mazingbusiness.com/public/statements/statement-2109.pdf";
          $fileName1 = basename($pdf_url);
          $button_variable_pdf_filename=$fileName1;

          $templateData = [
                'name' => 'utility_failed_payment', // Don't change this template name
                'language' => 'en_US', 
                'components' => [
                    
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $customer_name],
                            ['type' => 'text', 'text' => $payment_amt],
                            ['type' => 'text', 'text' => $manager_phone],
                            ['type' => 'text', 'text' => $transaction_id],
                            
                        ],
                    ],

                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            [
                                "type" => "text",
                                "text" => $button_variable_encode_part // Replace $button_text with the actual Parameter for the button.
                            ],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '1',
                        'parameters' => [
                            [
                                "type" => "text",
                                "text" => $button_variable_pdf_filename // Replace $button_text with the actual Parameter for the button.
                            ],
                        ],
                    ],
                ],
            ];

             // Convert template data to JSON for logging
            $jsonTemplateData = json_encode($templateData, JSON_PRETTY_PRINT);

            // Step 8: Send the WhatsApp message
            $this->whatsAppWebService = new WhatsAppWebService();
            $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($user->phone, $templateData);

            // Log the JSON request for debugging purposes
            \Log::info('WhatsApp message sent:', ['request' => $jsonResponse]);

         //whatapp faliure code edited by dipak end 


        return response()->json([
            'status' => 'FAILURE'
        ]);
      }else{

        //whatapp success code edited by dipak start 
        $transaction_id=$getPayloadData['merchantTranId'];

        $paymentHistory = DB::table('payment_histories')->where('merchantTranId', $transaction_id)->first();
        // $payment_for =  $paymentHistory->payment_for;
        // $acc_code=$paymentHistory->bill_number; // this is wrong
        $acc_code=$paymentHistory->party_code; // Edit ny Atanu

        $address = DB::table('addresses')->where('acc_code', $acc_code)->first();
        $customer_name = $address->company_name;
        $due_amount = $address->due_amount;
        $overdue_amount = $address->overdue_amount;

        $user_id = $address->user_id;
        // Step 1: Search the users table to get the manager_id
        $user = DB::table('users')->where('id', $user_id)->first();
        $manager = DB::table('users')->where('id', $user->manager_id)->first();
        $manager_phone = $manager->phone;
        $adminStatementController = new AdminStatementController();
        $pdf_url=$adminStatementController->generateStatementPdf($acc_code, $due_amount, $overdue_amount, $user);
        
        $payment_amt=$getPayloadData['PayerAmount'];
        
        $fileName1 = basename($pdf_url);
        $button_variable_pdf_filename=$fileName1;
        $templateData = [
                'name' => 'utility_success_payment', // Don't change this template name
                'language' => 'en_US', 
                'components' => [
                    
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $customer_name],
                            ['type' => 'text', 'text' => $payment_amt],
                            ['type' => 'text', 'text' => $transaction_id],
                            ['type' => 'text', 'text' => $manager_phone],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '1',
                        'parameters' => [
                            [
                                "type" => "text",
                                "text" => $button_variable_pdf_filename // Replace $button_text with the actual Parameter for the button.
                            ],
                        ],
                    ],
                ],
            ];

             // Convert template data to JSON for logging
            $jsonTemplateData = json_encode($templateData, JSON_PRETTY_PRINT);

            // Step 8: Send the WhatsApp message
            $this->whatsAppWebService = new WhatsAppWebService();
            $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($user->phone, $templateData);

            // Log the JSON request for debugging purposes
            \Log::info('WhatsApp message sent:', ['request' => $jsonResponse]);

         //whatapp success code edited by dipak end 
         if(Auth::user()->id == 24185){
            // Update payment Status and send the value into Salzing
            $getOrder = Order::where('code',$paymentHistory->bill_number)->first();
            $getOrder->payment_gateway_status = 1;
            $getOrder->save();
            // Push order data to Salezing
            $result=array();
            $result['code']= $getOrder->code;
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://mazingbusiness.com/api/v2/order-push', $result);
            \Log::info('Salzing Order Push From Website Status: '  . json_encode($response->json(), JSON_PRETTY_PRINT));
         }
          
         return response()->json([
            'status' => 'Success'
        ]);
      }      
    }else{
      return response()->json([
          'status' => 'Pending'
      ]);
    }
  }

  private function decrypt_response($encrypted_response, $private_key_path){
      // Load the private key from the file
      $private_key = file_get_contents($private_key_path);

      // Decode the base64-encoded encrypted response
      $decoded_response = base64_decode($encrypted_response);

      // Variable to hold the decrypted response
      $decrypted = '';

      // Decrypt the response using the private key and PKCS1 padding
      $decryption_successful = openssl_private_decrypt($decoded_response, $decrypted, $private_key, OPENSSL_PKCS1_PADDING);

      // Check if decryption was successful
      if ($decryption_successful) {
          return $decrypted;  // Return the decrypted response
      } else {
          return 'Decryption failed';  // Handle decryption failure
      }
  }

  private function encrypt_payload($payload, $public_key_path){
      // Load the public key from the file
      $public_key = file_get_contents($public_key_path);

      // Variable to hold the encrypted result
      $encrypted = '';

      // Encrypt the payload using the public key and PKCS1 padding
      $encryption_successful = openssl_public_encrypt($payload, $encrypted, $public_key, OPENSSL_PKCS1_PADDING);

      // Check if encryption was successful
      if ($encryption_successful) {
          // Base64 encode the encrypted payload
          return base64_encode($encrypted);
      } else {
          return 'Encryption failed';  // Handle encryption failure
      }
  }

  private function send_api_request($url, $encrypted_payload) {
      // Initialize cURL
      $ch = curl_init();

      // Set the cURL options
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $encrypted_payload);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      // Set headers
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'accept: */*',
          'accept-encoding: *',
          'accept-language: en-US,en;q=0.8,hi;q=0.6',
          'cache-control: no-cache',
          'connection: keep-alive',
          'content-length: ' . strlen($encrypted_payload),
          'content-type: text/plain;charset=UTF-8',
          'apikey:MA0n7fMDpGA3PlM5yGsn4uxATlu7yWfA'

      ]);

      // Execute the cURL request and fetch response
      $response = curl_exec($ch);

      // Check for errors
      if ($response === false) {
          $response = curl_error($ch);
      }

      // Close cURL
      curl_close($ch);

      // Return the response
      return $response;
  }

  public function payAmount($payment_for="", $party_code="", $id=""){
    try{
      $payment_for = decrypt($payment_for);
      $party_code = decrypt($party_code);
      $billnumber = $party_code;
      $id = decrypt($id);

      $getPaymentValue = PaymentUrl::where('id',$id)->first();
      
      if($getPaymentValue == NULL){
        return redirect()->route('home');
      }else{
        if($payment_for == 'due_amount' OR $payment_for == 'overdue_amount'){   
          if($getPaymentValue !== NULL AND $getPaymentValue->qrCodeUrl !== NULL){
            $qrCodeUrl = $getPaymentValue->qrCodeUrl;
            $amount = $getPaymentValue->amount;
            $merchantTranId = $getPaymentValue->merchantTranId;
            $billNumber = $party_code;
          }else{
            $billNumber = $party_code;
            $amount = $getPaymentValue->amount;
            // Set MID, VPA, and other variables
            $mid = env('MERCHANT_ID'); //'610853';
            $vpa = env('VPA'); //'aceuat@icici';
            $merchantName = env('MARCHANT_NAME'); //'Ace Tools Pvt. Ltd'; // Merchant name can be dynamic
            $api_url = env('API_URL_QR'). $mid; // 'https://apibankingonesandbox.icicibank.com/api/MerchantAPI/UPI/v0/QR3/' . $mid;
            $merchantTranId = uniqid();
            // $amount ='1.00';
            // Payload to be encrypted
            $payload = json_encode([
                'merchantId' => $mid,
                'terminalId' => env('TERMINAL_ID'), // '5411',
                'amount' => number_format($amount, 2, '.', ''),
                'merchantTranId' => $merchantTranId,
                'billNumber' => $billNumber,
                'validatePayerAccFlag' => 'N'
            ]);

            // Encrypt the payload
            $encrypted_payload = $this->encrypt_payload($payload, storage_path('pay/public/key/rsa_apikey.txt'));
            
            // Send API request
            $response = $this->send_api_request($api_url, $encrypted_payload);

            // Decrypt the response
            $decrypted_response = $this->decrypt_response($response, storage_path('pay/private/key/private_cer.pem'));

            // Handle response and generate UPI URL and QR code
            $response_data = json_decode($decrypted_response, true);
            $qrCodeUrl = "";

            if ($response_data['success'] == 'true') {
                $refId = $response_data['refId'];
                $currency = 'INR';
                $mccCode = $response_data['terminalId'];

                // Generate UPI URL
                $upiUrl = "upi://pay?pa=$vpa&pn=$merchantName&tr=$refId&am=$amount&cu=$currency&mc=$mccCode";
                $encodedUpiUrl = urlencode($upiUrl);

                // Generate QR code URL
                $qrCodeUrl = "https://quickchart.io/qr?text=" . $encodedUpiUrl . "&size=250";

                $userAddressData = Address::where('acc_code',$party_code)->first();
                $userData = User::where('id', $userAddressData->user_id)->first();
                $paymentHistoryData = array();
                $paymentHistoryData['qrCodeUrl']=$qrCodeUrl;
                $paymentHistoryData['user_id']=$userData->id;
                $paymentHistoryData['party_code']=$userData->party_code;
                $paymentHistoryData['bill_number']=$billNumber;
                $paymentHistoryData['merchantId']=$mid;
                $paymentHistoryData['subMerchantId']=$mid;
                $paymentHistoryData['terminalId']=$response_data['terminalId'];
                $paymentHistoryData['merchantTranId']=$merchantTranId;
                $paymentHistoryData['refId']=$refId;
                $paymentHistoryData['merchantName']=$merchantName;
                $paymentHistoryData['vpa']=$vpa;
                $paymentHistoryData['amount']=$amount;
                $paymentHistoryData['payment_for']=$payment_for;
                $paymentHistoryData['api_name']='QR_CODE';
                PaymentHistory::create($paymentHistoryData);

                $getPaymentValue->qrCodeUrl = $qrCodeUrl;
                $getPaymentValue->merchantTranId = $merchantTranId;
                $getPaymentValue->save();

            }
          }
          return view('frontend.pay-amount', compact('qrCodeUrl','payment_for','party_code','amount','merchantTranId','billNumber'));
        }else if($payment_for == 'custom_amount'){
          $billNumber = $party_code;
          $currentMonth = date('m');
          $currentYear = date('Y');
          if ($currentMonth >= 4) {
              $fy_form_date = date('Y-04-01'); // Start of financial year
              $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
          } else {
              $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
              $fy_to_date = date('Y-03-31'); // Current year March
          }
          $from_date = $fy_form_date;
          $to_date = $fy_to_date;
          $dueAmount = 0;
          // Due amount Calculation.
          $headers = [
            'authtoken' => '65d448afc6f6b',
          ];
          $body = [
                    'party_code' => $billNumber,
                    'from_date' => $from_date,
                    'to_date' =>  $to_date,
                ];
                
          $due_response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
          \Log::info('Received response from Salzing API For payment Url Due Calculation', [
              'status' => $due_response->status(),
              'party_code' =>  $billNumber,
              'body' => $due_response->body()
          ]);
          $getDueData = $due_response->json();
          if(!empty($getDueData) AND isset($getDueData['data']) AND !empty($getDueData['data'])){				
            $getDueData = $getDueData['data'];				
            foreach($getDueData as $gKey=>$gValue){
              if($gValue['ledgername'] == "Opening b/f..."){
              }else if($gValue['ledgername'] == "closing C/f..."){
                if($gValue['dramount'] != "0.00"){
                  $dueAmount = $gValue['dramount'];
                }else{
                  $dueAmount = $gValue['cramount'];
                }
              }
            }
          }

          // Overdue calculation 
          $overdueAmount = 0;
          $headers = [
            'authtoken' => '65d448afc6f6b',
          ];
          $body = [
                    'party_code' => $billNumber,
                    'from_date' => $from_date,
                    'to_date' =>  $to_date,
                ];
                
          $overdue_response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
          \Log::info('Received response from Salzing API For Paymeny Url Overdue Calculation', [
              'status' => $overdue_response->status(),
              'party_code' =>  $billNumber,
              'body' => $overdue_response->body()
          ]);
          $getOverdueData = $overdue_response->json();
          if(!empty($getOverdueData) AND isset($getOverdueData['data']) AND !empty($getOverdueData['data'])){
            $userAddressData = Address::where('acc_code',$billNumber)->first();
            $userData = User::where('id', $userAddressData->user_id)->first();		
            $getOverdueData = $getOverdueData['data'];
            $closingBalanceResult = array_filter($getOverdueData, function ($entry) {
              return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
            });
            $closingEntry = reset($closingBalanceResult);
            $cloasingDrAmount = $closingEntry['dramount'];
            $cloasingCrAmount = $closingEntry['cramount'];          
            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
            if($cloasingCrAmount > 0){
              $drBalanceBeforeOVDate = 0;
              $crBalanceBeforeOVDate = 0;
              $getOverdueData = array_reverse($getOverdueData);
              foreach($getOverdueData as $ovKey=>$ovValue){
                if($ovValue['ledgername'] != 'closing C/f...'){
                  if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                    // $drBalanceBeforeOVDate += $ovValue['dramount'];
                    $crBalanceBeforeOVDate += $ovValue['cramount'];
                  }else{
                    $drBalanceBeforeOVDate += $ovValue['dramount'];
                    $crBalanceBeforeOVDate += $ovValue['cramount'];
                  }
                }
              }
              $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
            }
          }          
          $merchantTranId="";
          
          return view('frontend.pay-amount', compact('dueAmount','overdueAmount','payment_for','billNumber','merchantTranId'));
        }else if($payment_for == 'payable_amount'){
          //$getPaymentHistory = PaymentHistory::where('bill_number',$party_code)->first();
			    $getPaymentHistory = PaymentHistory::where('bill_number',$party_code)
                        ->orderBy('id', 'desc')
                        ->first();
			    $getPaymenyUrl = PaymentUrl::where('party_code',$party_code)->where('payment_for',$payment_for)->get();
          $merchantTranId = $getPaymentHistory->merchantTranId;
          $billNumber = $getPaymentHistory->bill_number;
          $amount = $getPaymentHistory->amount;
          $party_code = $getPaymentHistory->party_code;
          $qrCodeUrl = $getPaymentHistory->qrCodeUrl;
          return view('frontend.pay-amount', compact('qrCodeUrl','payment_for','party_code','amount','merchantTranId','billNumber'));
        }        
      }
    } catch (Exception $e) {
        echo "Caught exception: " . $e->getMessage();
    }    
  }

  public function getAmount(Request $request){
    $billnumber = decrypt($request->billnumber); 
    $payment_for = $request->payment_for;
    $dueAmount = 0;
    $overdueAmount = 0;
    $amount = 0;
    $qrCodeUrl = "";
    $merchantTranId = "";
    $currentMonth = date('m');
    $currentYear = date('Y');
		if ($currentMonth >= 4) {
        $fy_form_date = date('Y-04-01'); // Start of financial year
        $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
    } else {
        $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
        $fy_to_date = date('Y-03-31'); // Current year March
    }
    $from_date = $fy_form_date;
    $to_date = $fy_to_date;
    // Calculate Due amount
    if($request->payment_for == 'due_amount' OR $request->payment_for == 'custom_amount'){
			$dueAmount = 0;
			$headers = [
				'authtoken' => '65d448afc6f6b',
			];
			$body = [
                'party_code' => $billnumber,
                'from_date' => $from_date,
                'to_date' =>  $to_date,
            ];
            
      $due_response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
      \Log::info('Received response from Salzing API For payment Url Due Calculation', [
          'status' => $due_response->status(),
          'party_code' =>  $billnumber,
          'body' => $due_response->body()
      ]);
			$getDueData = $due_response->json();
			if(!empty($getDueData) AND isset($getDueData['data']) AND !empty($getDueData['data'])){				
				$getDueData = $getDueData['data'];				
				foreach($getDueData as $gKey=>$gValue){
					if($gValue['ledgername'] == "Opening b/f..."){
					}else if($gValue['ledgername'] == "closing C/f..."){
						if($gValue['dramount'] != "0.00"){
							$dueAmount = $gValue['dramount'];
						}else{
							$dueAmount = $gValue['cramount'];
						}
					}
				}
			}
			if($dueAmount < 0){
				$dueAmount = 0;
        $amount = 0;
			}else{
        $amount = $dueAmount;
      }
		}
    // Calculate Overdue amount
    if($request->payment_for == 'overdue_amount' OR $request->payment_for == 'custom_amount'){
			$overdueAmount = 0;
			$headers = [
				'authtoken' => '65d448afc6f6b',
			];
			$body = [
                'party_code' => $billnumber,
                'from_date' => $from_date,
                'to_date' =>  $to_date,
            ];
            
      $overdue_response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
      \Log::info('Received response from Salzing API For Paymeny Url Overdue Calculation', [
          'status' => $overdue_response->status(),
          'party_code' =>  $billnumber,
          'body' => $overdue_response->body()
      ]);
			$getOverdueData = $overdue_response->json();
			if(!empty($getOverdueData) AND isset($getOverdueData['data']) AND !empty($getOverdueData['data'])){
        $userAddressData = Address::where('acc_code',$billnumber)->first();
		    $userData = User::where('id', $userAddressData->user_id)->first();		
				$getOverdueData = $getOverdueData['data'];
				$closingBalanceResult = array_filter($getOverdueData, function ($entry) {
					return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
				});
				$closingEntry = reset($closingBalanceResult);
				$cloasingDrAmount = $closingEntry['dramount'];
				$cloasingCrAmount = $closingEntry['cramount'];          
				$overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
				if($cloasingCrAmount > 0){
					$drBalanceBeforeOVDate = 0;
					$crBalanceBeforeOVDate = 0;
					$getOverdueData = array_reverse($getOverdueData);
					foreach($getOverdueData as $ovKey=>$ovValue){
						if($ovValue['ledgername'] != 'closing C/f...'){
							if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
								// $drBalanceBeforeOVDate += $ovValue['dramount'];
								$crBalanceBeforeOVDate += $ovValue['cramount'];
							}else{
								$drBalanceBeforeOVDate += $ovValue['dramount'];
								$crBalanceBeforeOVDate += $ovValue['cramount'];
							}
						}
					}
					$overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
				}
			}
      if($overdueAmount <= 0){
        $amount = 0;
			}else{
        $amount = $overdueAmount;
      }
		}

    $getPaymentHistoryData = PaymentHistory::where('payment_for',$payment_for)->where('amount',$amount)->where('bill_number',$billnumber)->where('status','PENDING')->first();
    if($getPaymentHistoryData !== NULL){
      if($payment_for == 'due_amount' OR $payment_for == 'overdue_amount'){
        $amount = $getPaymentHistoryData->amount;
        $payment_for = $getPaymentHistoryData->bill_number;
        $qrCodeUrl = $getPaymentHistoryData->qrCodeUrl;
        $merchantTranId = $getPaymentHistoryData->merchantTranId;
        $returnHTML = view('frontend.partials.generatePayAmount', compact('amount','payment_for','billnumber','qrCodeUrl'))->render();
        return response()->json([
            'html' => $returnHTML,
            'merchantTranId'=>$merchantTranId
        ]);
      }elseif($payment_for == 'custom_amount'){
        // echo $dueAmount.'...'.$overdueAmount.'...'; die;
        $returnHTML = view('frontend.partials.generateCustomPayAmount', compact('dueAmount','overdueAmount','billnumber','payment_for'))->render();
        return response()->json([
            'html' => $returnHTML,
            'merchantTranId'=>$merchantTranId
        ]);
      }
    }else{
      //---------------------------- Generate Qr Code --------------------------------------
      if($payment_for == 'due_amount' OR $payment_for == 'overdue_amount'){
        $billNumber = $billnumber;
        $amount = $amount;
        // Set MID, VPA, and other variables
        $mid = env('MERCHANT_ID'); //'610853';
        $vpa = env('VPA'); //'aceuat@icici';
        $merchantName = env('MARCHANT_NAME'); //'Ace Tools Pvt. Ltd'; // Merchant name can be dynamic
        $api_url = env('API_URL_QR'). $mid; // 'https://apibankingonesandbox.icicibank.com/api/MerchantAPI/UPI/v0/QR3/' . $mid;
        $merchantTranId = uniqid();
        // $amount ='1.00';
        // Payload to be encrypted
        $payload = json_encode([
            'merchantId' => $mid,
            'terminalId' => env('TERMINAL_ID'), // '5411',
            'amount' => number_format($amount, 2, '.', ''),
            'merchantTranId' => $merchantTranId,
            'billNumber' => $billNumber,
            'validatePayerAccFlag' => 'N'
        ]);

        // Encrypt the payload
        $encrypted_payload = $this->encrypt_payload($payload, storage_path('pay/public/key/rsa_apikey.txt'));      
        // Send API request
        $response = $this->send_api_request($api_url, $encrypted_payload);
        // Decrypt the response
        $decrypted_response = $this->decrypt_response($response, storage_path('pay/private/key/private_cer.pem'));
        // Handle response and generate UPI URL and QR code
        $response_data = json_decode($decrypted_response, true);
        
        if ($response_data['success'] == 'true') {
            $refId = $response_data['refId'];
            $currency = 'INR';
            $mccCode = $response_data['terminalId'];

            // Generate UPI URL
            $upiUrl = "upi://pay?pa=$vpa&pn=$merchantName&tr=$refId&am=$amount&cu=$currency&mc=$mccCode";
            $encodedUpiUrl = urlencode($upiUrl);

            // Generate QR code URL
            $qrCodeUrl = "https://quickchart.io/qr?text=" . $encodedUpiUrl . "&size=250";

            $userAddressData = Address::where('acc_code',$billnumber)->first();
            $userData = User::where('id', $userAddressData->user_id)->first();
            $paymentHistoryData = array();
            $paymentHistoryData['qrCodeUrl']=$qrCodeUrl;
            $paymentHistoryData['user_id']=$userData->id;
            $paymentHistoryData['party_code']=$userData->party_code;            
            $paymentHistoryData['bill_number']=$billNumber;
            $paymentHistoryData['merchantId']=$mid;
            $paymentHistoryData['subMerchantId']=$mid;
            $paymentHistoryData['terminalId']=$response_data['terminalId'];
            $paymentHistoryData['merchantTranId']=$merchantTranId;
            $paymentHistoryData['refId']=$refId;
            $paymentHistoryData['merchantName']=$merchantName;
            $paymentHistoryData['vpa']=$vpa;
            $paymentHistoryData['amount']=$amount;
            $paymentHistoryData['payment_for']=$payment_for;
            $paymentHistoryData['api_name']='QR_CODE';
            PaymentHistory::create($paymentHistoryData);
        }
        $returnHTML = view('frontend.partials.generatePayAmount', compact('amount','payment_for','billnumber','qrCodeUrl'))->render();
        return response()->json([
            'html' => $returnHTML,
            'merchantTranId'=>$merchantTranId
        ]);
      }
      if($request->payment_for == 'custom_amount'){
        $returnHTML = view('frontend.partials.generateCustomPayAmount', compact('dueAmount','overdueAmount','billnumber','payment_for'))->render();
        return response()->json([
            'html' => $returnHTML,
            'merchantTranId'=>$merchantTranId
        ]);
      }
    }
  }

  public function generateQrCodeForCustomAmount(Request $request){
    $amount = $request->amount;
    $billnumber = decrypt($request->billnumber);
    $billNumber = $billnumber;
    $payment_for = $request->payment_for;

    // Set MID, VPA, and other variables
    $mid = env('MERCHANT_ID'); //'610853';
    $vpa = env('VPA'); //'aceuat@icici';
    $merchantName = env('MARCHANT_NAME'); //'Ace Tools Pvt. Ltd'; // Merchant name can be dynamic
    $api_url = env('API_URL_QR'). $mid; // 'https://apibankingonesandbox.icicibank.com/api/MerchantAPI/UPI/v0/QR3/' . $mid;
    $merchantTranId = uniqid();
    // $amount ='1.00';
    // Payload to be encrypted
    $payload = json_encode([
        'merchantId' => $mid,
        'terminalId' => env('TERMINAL_ID'), // '5411',
        'amount' => number_format($amount, 2, '.', ''),
        'merchantTranId' => $merchantTranId,
        'billNumber' => $billNumber,
        'validatePayerAccFlag' => 'N'
    ]);

    // Encrypt the payload
    $encrypted_payload = $this->encrypt_payload($payload, storage_path('pay/public/key/rsa_apikey.txt'));
    
    // Send API request
    $response = $this->send_api_request($api_url, $encrypted_payload);

    // Decrypt the response
    $decrypted_response = $this->decrypt_response($response, storage_path('pay/private/key/private_cer.pem'));

    // Handle response and generate UPI URL and QR code
    $response_data = json_decode($decrypted_response, true);
    $qrCodeUrl = "";
    if ($response_data['success'] == 'true') {
        $refId = $response_data['refId'];
        $currency = 'INR';
        $mccCode = $response_data['terminalId'];

        // Generate UPI URL
        $upiUrl = "upi://pay?pa=$vpa&pn=$merchantName&tr=$refId&am=$amount&cu=$currency&mc=$mccCode";
        $encodedUpiUrl = urlencode($upiUrl);

        // Generate QR code URL
        $qrCodeUrl = "https://quickchart.io/qr?text=" . $encodedUpiUrl . "&size=250";
        
        $userAddressData = Address::where('acc_code',$billnumber)->first();
        $userData = User::where('id', $userAddressData->user_id)->first();
        $paymentHistoryData = array();
        $paymentHistoryData['qrCodeUrl']=$qrCodeUrl;
        $paymentHistoryData['user_id']=$userData->id;
        $paymentHistoryData['party_code']=$userData->party_code;
        $paymentHistoryData['bill_number']=$billNumber;
        $paymentHistoryData['merchantId']=$mid;
        $paymentHistoryData['subMerchantId']=$mid;
        $paymentHistoryData['terminalId']=$response_data['terminalId'];
        $paymentHistoryData['merchantTranId']=$merchantTranId;
        $paymentHistoryData['refId']=$refId;
        $paymentHistoryData['merchantName']=$merchantName;
        $paymentHistoryData['vpa']=$vpa;
        $paymentHistoryData['amount']=$amount;
        $paymentHistoryData['payment_for']=$payment_for;
        $paymentHistoryData['api_name']='QR_CODE';
        PaymentHistory::create($paymentHistoryData);
    }
    $party_code = $billnumber;
    $returnHTML = view('frontend.partials.payForCostomAmount', compact('qrCodeUrl','payment_for','party_code','amount','merchantTranId','billNumber'))->render();
    // $returnHTML = view('frontend.partials.generateCustomPayAmount', compact('dueAmount','overdueAmount','billnumber','payment_for'))->render();
    return response()->json([
        'status' => 'Success',
        'html' => $returnHTML,
        'merchantTranId'=>$merchantTranId
    ]);
  }

}
