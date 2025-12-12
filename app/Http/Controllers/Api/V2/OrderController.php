<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\Address;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Product;
use App\Models\OrderDetail;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Models\City;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use DB;
use \App\Utility\NotificationUtility;
use App\Models\CombinedOrder;
use App\Http\Controllers\AffiliateController;
use App\Services\WhatsAppWebService;
use App\Http\Controllers\InvoiceController;


class OrderController extends Controller
{
    protected $WhatsAppWebService;

      private function generatePaymentUrl($party_code, $payment_for)
      {
          $client = new \GuzzleHttp\Client();
          $response = $client->post('https://mazingbusiness.com/api/v2/payment/generate-url', [
              'json' => [
                  'party_code' => $party_code,
                  'payment_for' => $payment_for
              ]
          ]);

          $data = json_decode($response->getBody(), true);
          return $data['url'] ?? '';  // Return the generated URL or an empty string if it fails
      }

    public function store(Request $request, $set_paid = false)
    {
        // if($request->header('x-customer-id') !== null){
        //     $customerId = $request->header('x-customer-id');
        // }else{
        //     $customerId = auth()->user()->id;
        // }
        if($request->header('x-customer-id') !== null){
            $user_id = $request->header('x-customer-id');
        }else{
            $user_id = $request->user_id;
        }
        
        if(get_setting('minimum_order_amount_check') == 1){
            $subtotal = 0;
            foreach (Cart::where('customer_id',$user_id)->orWhere('user_id',$user_id)->get() as $key => $cartItem){ 
                $product = Product::find($cartItem['product_id']);
                $subtotal += cart_product_price($cartItem, $product, false, false, $user_id) * $cartItem['quantity'];
            }
            if ($subtotal < get_setting('minimum_order_amount')) {
                return $this->failed("You order amount is less then the minimum order amount");
            }
        }
        
        $cartItems = Cart::where('customer_id',$user_id)->orWhere('user_id',$user_id)->get();
        
        if ($cartItems->isEmpty()) {
            return response()->json([
                'combined_order_id' => 0,
                'result' => false,
                'message' => translate('Cart is Empty')
            ]);
        }

        // $user = User::find(auth()->user()->id);
        $user = User::find($user_id);
        

        $address = Address::where('id', $cartItems->first()->address_id)->first();
        $city=City::where('id',$address->city_id)->first();
        $address_id = null;
        $shippingAddress = [];
        if ($address != null) {
            $address_id = $address->id;
            $shippingAddress['name']        = $user->name;
            $shippingAddress['company_name'] = $user->company_name;
            $shippingAddress['gstin']        = $user->gstin;
            $shippingAddress['email']       = $user->email;
            $shippingAddress['address']     = $address->address;
            $shippingAddress['country']     = $address->country->name;
            $shippingAddress['state']       = $address->state->name;
            $shippingAddress['city']        = $address->city;
            $shippingAddress['postal_code'] = $address->postal_code;
            $shippingAddress['phone']       = $address->phone;
            if ($address->latitude || $address->longitude) {
                $shippingAddress['lat_lang'] = $address->latitude . ',' . $address->longitude;
            }
        }
        // print_r($shippingAddress);die;
        $combined_order = new CombinedOrder;
        // $combined_order->user_id = $user->id;
        $combined_order->user_id = $user_id;
        $combined_order->shipping_address = json_encode($shippingAddress);
        $combined_order->save();

        $seller_products = array();
        foreach ($cartItems as $cartItem) {
            $product_ids = array();
            $product = Product::find($cartItem['product_id']);
            if (isset($seller_products[$product->user_id])) {
                $product_ids = $seller_products[$product->user_id];
            }
            array_push($product_ids, $cartItem);
            $seller_products[$product->user_id] = $product_ids;
        }

        foreach ($seller_products as $seller_product) {
            $order = new Order;
            $order->combined_order_id = $combined_order->id;
            // $order->user_id = $user->id;
            $order->user_id = $user_id;
            $order->shipping_address = $combined_order->shipping_address;
            $order->address_id = $address_id;
            // $order->shipping_type = $cartItems->first()->shipping_type;
            // if ($cartItems->first()->shipping_type == 'pickup_point') {
            //     $order->pickup_point_id = $cartItems->first()->pickup_point;
            // }

            $order->payment_type = $request->payment_type;
            $order->delivery_viewed = '0';
            $order->payment_status_viewed = '0';
            $order->payment_status_viewed = '0';
            $order->code = date('Ymd-His') . rand(10, 99);
            $order->date = strtotime('now');
            if($set_paid){
                $order->payment_status = 'paid';
            }else{
                $order->payment_status = 'unpaid';
            }
            $order->payment_gateway_status = '0';
            $order->order_from = 'app';
            
            $order->save();

            $subtotal = 0;
            $tax = 0;
            $shipping = 0;
            $coupon_discount = 0;

            //Order Details Storing
            foreach ($seller_product as $cartItem) {
                $product = Product::find($cartItem['product_id']);

                $subtotal += cart_product_price($cartItem, $product, false, false,$user_id ) * $cartItem['quantity'];
                $tax += cart_product_tax($cartItem, $product,false,$user_id) * $cartItem['quantity'];
                $coupon_discount += $cartItem['discount'];

                $product_variation = $cartItem['variation'];

                // $product_stock = $product->stocks->where('variant', $product_variation)->first();
                // if ($product->digital != 1 && $cartItem['quantity'] > $product_stock->qty) {
                //     $order->delete();
                //     $combined_order->delete();
                //     return response()->json([
                //         'combined_order_id' => 0,
                //         'result' => false,
                //         'message' => translate('The requested quantity is not available for ') . $product->name
                //     ]);
                // } elseif ($product->digital != 1) {
                //     $product_stock->qty -= $cartItem['quantity'];
                //     $product_stock->save();
                // }

                $order_detail = new OrderDetail;
                $order_detail->order_id = $order->id;
                $order_detail->seller_id = $product->user_id;
                $order_detail->product_id = $product->id;
                $order_detail->variation = $product_variation;
                $order_detail->price = cart_product_price($cartItem, $product, false, false,$user_id) * $cartItem['quantity'];
                $order_detail->tax = cart_product_tax($cartItem, $product,false,$user_id) * $cartItem['quantity'];
                $order_detail->shipping_type = $cartItem['shipping_type'];
                $order_detail->product_referral_code = $cartItem['product_referral_code'];
                $order_detail->shipping_cost = $cartItem['shipping_cost'];

                $shipping += $order_detail->shipping_cost;

                // if ($cartItem['shipping_type'] == 'pickup_point') {
                //     $order_detail->pickup_point_id = $cartItem['pickup_point'];
                // }
                //End of storing shipping cost
                if (addon_is_activated('club_point')) {
                    $order_detail->earn_point = $product->earn_point;
                }

                $order_detail->quantity = $cartItem['quantity'];
                $order_detail->save();

                $product->num_of_sale = $product->num_of_sale + $cartItem['quantity'];
                $product->save();

                $order->seller_id = $product->user_id;
                //======== Added By Kiron ==========
                $order->shipping_type = $cartItem['shipping_type'];
                if ($cartItem['shipping_type'] == 'pickup_point') {
                    $order->pickup_point_id = $cartItem['pickup_point'];
                }
                if ($cartItem['shipping_type'] == 'carrier') {
                    $order->carrier_id = $cartItem['carrier_id'];
                }

                if ($product->added_by == 'seller' && $product->user->seller != null){
                    $seller = $product->user->seller;
                    $seller->num_of_sale += $cartItem['quantity'];
                    $seller->save();
                }



                if (addon_is_activated('affiliate_system')) {
                    if ($order_detail->product_referral_code) {
                        $referred_by_user = User::where('referral_code', $order_detail->product_referral_code)->first();

                        $affiliateController = new AffiliateController;
                        $affiliateController->processAffiliateStats($referred_by_user->id, 0, $order_detail->quantity, 0, 0);
                    }
                }
            }

            // $order->grand_total = $subtotal + $tax + $shipping;
            $order->grand_total = $subtotal + $shipping;

            if ($seller_product[0]->coupon_code != null) {
                // if (Session::has('club_point')) {
                //     $order->club_point = Session::get('club_point');
                // }
                $order->coupon_discount = $coupon_discount;
                $order->grand_total -= $coupon_discount;

                $coupon_usage = new CouponUsage;
                // $coupon_usage->user_id = $user->id;
                $coupon_usage->user_id = $user_id;
                $coupon_usage->coupon_id = Coupon::where('code', $seller_product[0]->coupon_code)->first()->id;
                $coupon_usage->save();
            }

            $combined_order->grand_total += $order->grand_total;

            if (strpos($request->payment_type, "manual_payment_") !== false) { // if payment type like  manual_payment_1 or  manual_payment_25 etc)

                $order->manual_payment = 1;
                $order->save();

            }

            $order->save();
        }
        $combined_order->save();

        Cart::where('customer_id',$user_id)->orWhere('user_id',$user_id)->delete();

        if (
            $request->payment_type == 'cash_on_delivery'
            || $request->payment_type == 'wallet'
            || strpos($request->payment_type, "manual_payment_") !== false // if payment type like  manual_payment_1 or  manual_payment_25 etc
        ) {
            NotificationUtility::sendOrderPlacedNotification($order);
        }

        //Whatsapp Code Implementation
        $combined_order = CombinedOrder::findOrFail($combined_order->id);
         $first_order=$combined_order->orders->first();
        $user = DB::table('users')
                ->where('id',  $first_order->user_id)
                ->first();

        
          // edited by dipak start
          $party_code=$user->party_code;
          $payment_url=$this->generatePaymentUrl($party_code, $payment_for="custom_amount");
          // Extract the part after 'pay-amount/'
          $fileName = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
          $button_variable_encode_part=$fileName;

          // edited by dipak end
                
        $manager_phone_number = DB::table('users')
        ->where('id', $user->manager_id)
        ->pluck('phone')
        ->first();

        $to =[json_decode($first_order->shipping_address)->phone,'+919709555576',$manager_phone_number];
        $company_name=json_decode($first_order->shipping_address)->company_name;
        $order_id=$first_order->code;
        $date=date('d-m-Y H:i A', $first_order->date);
        $total_amount=$first_order->grand_total;

        // // echo "To: ".$to." </br> ";
        // // echo "Company Name: ".$company_name." </br> ";
        // // echo "Order Id: ".$order_id." </br> ";
        // // echo "Date: ".$date." </br> ";
        // // echo "Total: ".$total_amount." </br> ";
        // // die();
        $invoiceController=new InvoiceController();
        $file_url=$invoiceController->invoice_file_path($first_order->id);
        
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
              //   [
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

        // Push order data to Salezing
        $result=array();
        $result['code']= $order->code;
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://mazingbusiness.com/api/v2/order-push', $result);
        \Log::info('Salzing Order Push From Mobile App Status: '  . json_encode($response->json(), JSON_PRETTY_PRINT));

        
        return response()->json([
            'combined_order_id' => $combined_order->id,
            'result' => true,
            'message' => translate('Your order has been placed successfully')
        ]);
    }

    public function orderStatusSaleszing(Request $request){
        return json_encode($request);
    }
}
