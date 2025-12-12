<?php

/** @noinspection PhpUndefinedClassInspection */

namespace App\Http\Controllers\Api\V2;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Address;
use App\Models\City;
use App\Models\State;
use App\Models\ProductWarehouse;
use App\Models\Product;
use App\Models\Upload;
use App\Models\Seller;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\SalezingLog;

use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Socialite;
use App\Utility\SendSMSUtility;
use App\Utility\SmsUtility;
use Illuminate\Validation\ValidationException;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SaleszingController extends Controller
{

    public function clientPush(Request $request){
        $party_code = $request->party_code;

        // Fetch User details
        $userData = User::where('party_code', $party_code)->first();
        if (!$userData) {
            $data = [
                'api_name' => 'client-push',
                'code' => $party_code,
                'response' => 'User not found.',
                'status_code' => '400',
                'status' => 'falied'
            ];
            SalezingLog::create($data);
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }
        $addressData = Address::where('user_id', $userData->id)->first();
        $stateData = State::where('id',$addressData->state_id )->first();
        $state = $stateData->name;
        $id = $userData->id;
        $warehouse_id = $userData->warehouse_id;

        // Fetch Warehouse details
        $wareHouseData = Warehouse::where('id', $warehouse_id)->first();

        $result = array();
        $shiptoaddress['transport'] = array();
        $output = array();
        
        $output['id'] = "$userData->id";
        $output['party_code'] = "$userData->party_code";
        $output['ledgergroup'] = str_replace(' ','_',$userData->name).$userData->party_code;
        $output['virtual_account_number'] = $userData->party_code;
        $output['name'] = $userData->company_name.' - '.$addressData->city;
        $output['company_name'] = $userData->company_name.' - '.$addressData->city;
        $output['address_1'] = $addressData->address;
        $output['address_2'] = $addressData->address_2;
        $output['city'] = $addressData->city;
        $output['state'] = $state;
        $output['pincode'] = $addressData->postal_code;
        $output['gstin'] = strtoupper($userData->gstin);
        $output['phone'] = $userData->phone;
        $output['email'] = $userData->email;
        $output['aadhar_card'] = $userData->aadhar_card;
        $output['billbybill'] = 'YES';
        $output['warehouse']['id'] = "$userData->warehouse_id";
        $output['warehouse']['name'] = $wareHouseData->name;
        $output['manager'] = "$userData->manager_id";
        $output['credit_limit'] = $userData->credit_limit;
        $output['credit_days'] = $userData->credit_days;
        $output['discounts'] = $userData->discount;
        $output['shiptoaddress'] = array();
        

        $transport = array();
        $transport['transportername'] = "Bhagirathi carrying coporation";
        $transport['paymentmode'] = "";
        $transport['warehousename'] = 'kolkata';

        $output['transport'][] = $transport;
        // $shiptoaddress['transport'][] = $transport;

        $transport = array();
        $transport['transportername'] = "Bhagirathi carrying coporation";
        $transport['paymentmode'] = "";
        $transport['warehousename'] = 'kolkata';

        $output['transport'][] = $transport;
        // $shiptoaddress['transport'][] = $transport;

        $transport = array();
        $transport['transportername'] = "Bhagirathi carrying coporation";
        $transport['paymentmode'] = "";
        $transport['warehousename'] = 'kolkata';

        $output['transport'][] = $transport;
        // $shiptoaddress['transport'][] = $transport;

        $transport = array();
        $transport['transportername'] = "Bhagirathi carrying coporation";
        $transport['paymentmode'] = "";
        $transport['warehousename'] = 'kolkata';

        $output['transport'][] = $transport;
        // $shiptoaddress['transport'][] = $transport;

        $transport = array();
        $transport['transportername'] = "Bhagirathi carrying coporation";
        $transport['paymentmode'] = "";
        $transport['warehousename'] = 'kolkata';

        $output['transport'] = array();
        // $shiptoaddress['transport'][] = $transport;
        // $output['shiptoaddress'][] = $shiptoaddress;

        $result['data'][] = $output;

        $count = 10;
        // Fetch Warehouse details
        $addressData = Address::where('user_id', $id)->get();
        // echo "<pre>"; print_r($addressData);die;
        foreach($addressData as $aKey=>$aValue){
            // $party_code = $userData->party_code.$count;
            $output = array();
            $shiptoaddress = array();

            $city_id = $aValue->city_id;
            $state_id = $aValue->state_id;

            $cityData = City::where('id',$city_id )->first();            
            if(($cityData)){
                $city = $cityData->name;
            }else{
                $city = "";
            }

            $stateData = State::where('id',$state_id )->first();
            $state = $stateData->name;

            $output['id'] = "$aValue->id";
            $output['party_code'] = $aValue->acc_code;
            $output['ledgergroup'] = str_replace(' ','_',$userData->name).$userData->party_code;
            $output['virtual_account_number'] = $party_code;
            $output['name'] = $aValue->company_name.' - '.$city;
            $output['company_name'] = $aValue->company_name.' - '.$city;
            $output['address_1'] = $aValue->address;
            $output['address_2'] = $aValue->address_2;
            $output['city'] = $aValue->city;
            $output['state'] = $state;
            $output['pincode'] = $aValue->postal_code;
            $output['gstin'] = strtoupper($aValue->gstin);
            $output['phone'] = $aValue->phone;
            $output['email'] = '';
            $output['aadhar_card'] = null;
            $output['billbybill'] = 'YES';
            $output['warehouse']['id'] = "$userData->warehouse_id";
            $output['warehouse']['name'] = $wareHouseData->name;
            $output['manager'] = "$userData->manager_id";
            $output['credit_limit'] = '0';
            $output['credit_days'] = '0';
            $output['discounts'] = '22';
            $output['shiptoaddress'] = array();


            $shiptoaddress['acccode'] = $aValue->acc_code;
            $shiptoaddress['acname'] = $aValue->company_name.' - '.$aValue->city;
            $shiptoaddress['address_1'] = $aValue->address;
            $shiptoaddress['address_2'] = $aValue->address;
            $shiptoaddress['city'] = $aValue->city;
            $shiptoaddress['state'] = $state;
            $shiptoaddress['pincode'] = $aValue->postal_code;

            $transport = array();
            $transport['transportername'] = "Bhagirathi carrying coporation";
            $transport['paymentmode'] = "";
            $transport['warehousename'] = 'kolkata';

            $output['transport'][] = $transport;
            $shiptoaddress['transport'][] = $transport;

            $transport = array();
            $transport['transportername'] = "Bhagirathi carrying coporation";
            $transport['paymentmode'] = "";
            $transport['warehousename'] = 'kolkata';

            $output['transport'][] = $transport;
            $shiptoaddress['transport'][] = $transport;

            $transport = array();
            $transport['transportername'] = "Bhagirathi carrying coporation";
            $transport['paymentmode'] = "";
            $transport['warehousename'] = 'kolkata';

            $output['transport'][] = $transport;
            $shiptoaddress['transport'][] = $transport;

            $transport = array();
            $transport['transportername'] = "Bhagirathi carrying coporation";
            $transport['paymentmode'] = "";


            $transport['warehousename'] = 'kolkata';

            $output['transport'][] = $transport;
            $shiptoaddress['transport'][] = $transport;

            $transport = array();
            $transport['transportername'] = "Bhagirathi carrying coporation";
            $transport['paymentmode'] = "";

            $transport['warehousename'] = 'kolkata';

            $output['transport'] = array();
            $shiptoaddress['transport'][] = $transport;
            
            $output['shiptoaddress'][] = $shiptoaddress;            
            
            // Check if the GSTIN already exists in the main array
            $gstinExists = false;
            foreach ($result['data'] as $key => $value) {
                if ($value['gstin'] === $output['gstin']) {
                    $gstinExists = true;
                    $result['data'][$key]['shiptoaddress'][] = $shiptoaddress; // Add the address under shiptoaddress
                    break;
                }else{
                    // $result['data'][$key]['shiptoaddress'][] = $shiptoaddress;
                }
            }

            if (!$gstinExists) {
                $result['data'][] = $output; // Add a new item in the array
            }
            $count++;
        }

        $response = Http::withHeaders([
        'authtoken' => '65d448afc6f6b',
        'Content-Type' => 'application/json',
        ])->post('https://saleszing.co.in/itaapi/szparty.php', $result);

        \Log::info('Salzing Client Push Status: ' . json_encode($response->json(), JSON_PRETTY_PRINT));
        $responseData = $response->json();
        if ($response->failed() || ((isset($responseData['totalcount']) && $responseData['totalcount'] == '0') AND (isset($responseData['totalinsert']) && $responseData['totalinsert'] == '0') AND (isset($responseData['totalupdate']) && $responseData['totalupdate'] == '0'))) {
            $data = [
                'api_name' => 'client-push',
                'code' => $party_code,
                'response' => 'Client didn\'t push',
                'status_code' => '400',
                'status' => 'falied'
            ];
            SalezingLog::create($data);

            return response()->json([
                'success' => false,
                'message' => 'Failed to push customer to external API.',
                'error' => $response->body()
            ], $response->status());
        }
        if((isset($responseData['totalcount']) && $responseData['totalcount'] == '0') AND (isset($responseData['totalinsert']) && $responseData['totalinsert'] == '0') AND (isset($responseData['totalupdate']) && $responseData['totalupdate'] == '0')) {
            $data = [
                'api_name' => 'client-push',
                'code' => $party_code,
                'response' => 'Client didn\'t push',
                'status_code' => '400',
                'status' => 'falied'
            ];
            SalezingLog::create($data);
        }else{
            if((isset($responseData['totalcount']) && $responseData['totalcount'] != '0') AND (isset($responseData['totalinsert']) && $responseData['totalinsert'] != '0') AND (isset($responseData['totalupdate']) && $responseData['totalupdate'] == '0')) {
                $data = [
                    'api_name' => 'client-push',
                    'code' => $party_code,
                    'response' => 'Client inserted successfully',
                    'status_code' => '200',
                    'status' => 'success'
                ];
                SalezingLog::create($data);
            }elseif((isset($responseData['totalcount']) && $responseData['totalcount'] != '0') AND (isset($responseData['totalinsert']) && $responseData['totalinsert'] == '0') AND (isset($responseData['totalupdate']) && $responseData['totalupdate'] != '0')){
                $data = [
                    'api_name' => 'client-push',
                    'code' => $party_code,
                    'response' => 'Client updated successfully',
                    'status_code' => '200',
                    'status' => 'success'
                ];
                SalezingLog::create($data);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'api_response' => $responseData
        ]);

    }

    public function orderPush(Request $request){
        $code = $request->code;

        // Fetch order details
        $order = Order::join('users', 'orders.user_id', '=', 'users.id')
            ->join('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
            ->select('orders.id as order_table_id', 'orders.*', 'users.*', 'warehouses.name as warehouse_name')
            ->where('orders.code', $code)
            ->first();
        $addressData = Address::where('id',$order->address_id)->first();
        if($addressData !== null){
            $acc_code = $addressData->acc_code;
            $gstin = $addressData->gstin;
            $name = $addressData->company_name;
        }else{
            $acc_code = $order->party_code;
            $gstin = $order->gstin;
            $name = $order->name;
        }
        if (!$order) {
            $data = [
                'api_name' => 'order-push',
                'code' => $order->code,
                'response' => 'No order found.',
                'status_code' => '400',
                'status' => 'falied'
            ];
            SalezingLog::create($data);
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }
        $getUserDetails = User::where('id',$order->user_id)->first();
        $getManagerDetails = User::where('id',$getUserDetails->manager_id)->first();

        // Prepare output array
        $output = [
            'order_number' => $order->code,
            'order_date' => date('Y-m-d', $order->date),
            'salesperson' => $getManagerDetails->name,
            'customer_code' => $acc_code,
            'customer_name' => $name,
            'gstin' => $gstin,
            'acc_code' => $acc_code,
            'branch_id' => $order->warehouse_id,
            'branch_name' => $order->warehouse_name,
            'remarks' => $order->additional_info,
            'order_items' => []
        ];

        // Fetch order details (items)
        $orderDetails = OrderDetail::join('products', 'order_details.product_id', '=', 'products.id')
            ->select('order_details.*', 'products.name as product_name', 'products.part_no')
            ->where('order_details.order_id', $order->order_table_id)
            ->get();

        if ($orderDetails->isEmpty()) {
            $data = [
                'api_name' => 'order-push',
                'code' => $order->code,
                'response' => 'No order details found.',
                'status_code' => '400',
                'status' => 'falied'
            ];
            SalezingLog::create($data);
            return response()->json([
                'success' => false,
                'message' => 'No order details found.'
            ], 404);
        }

        $tax = 0;
        $subTotal = 0;

        foreach ($orderDetails as $value) {
            $tax += $value->tax;
            $subTotal += $value->price;

            $item = [
                'slno' => $value->id,
                'item_name' => $value->product_name,
                'part_no' => $value->part_no,
                'quantity' => $value->quantity,
                'price' => $value->price / $value->quantity,
                'discount_per' => 0, // Assuming no discount for simplicity
                'tax_per' => 18, // Example tax percentage
                'tax' => $value->tax / $value->quantity,
                'sub_total' => $value->price - $value->tax,
                'total' => $value->price
            ];
            $output['order_items'][] = $item;
        }

        $output['order_sub_total'] = $subTotal - $tax;
        $output['additional_discount'] = $order->coupon_discount + $order->payment_discount;
        $output['packing_charges'] = 0;
        $output['transport_charges'] = 0;
        $output['order_tax'] = $tax;
        $output['order_grand_total'] = $order->grand_total;

        $response = Http::withHeaders([
            'authtoken' => '65d448afc6f6b',
            'Content-Type' => 'application/json',
        ])->post('https://saleszing.co.in/itaapi/szorders.php', $output);
        \Log::info('Salzing Order Push Status: ' . json_encode($response->json(), JSON_PRETTY_PRINT));

        // Check if the request failed or if the API returned an error in the response
        $responseData = $response->json();
        if ($response->failed() || (isset($responseData[0]['statusmessage']) && $responseData[0]['statusmessage'] == 'falied.')) {
            $data = [
                'api_name' => 'order-push',
                'code' => $order->code,
                'response' => $responseData[0]['statusmessage'],
                'status_code' => $responseData[0]['statuscode'],
                'status' => 'falied'
            ];
            SalezingLog::create($data);

            return response()->json([
                'success' => false,
                'message' => 'Failed to push order to external API.',
                'error' => $responseData
            ], $response->status());
        }
        $data = [
            'api_name' => 'order-push',
            'code' => $order->code,
            'response' => $responseData[0]['statusmessage'],
            'status_code' => '200',
            'status' => 'success'
        ];
        SalezingLog::create($data);
        return response()->json([
            'success' => true,
            'data' => $output,
            'api_response' => $responseData
        ]);
    }

    public function itemPush(Request $request){
        $part_no = $request->part_no;
        $start = isset($request->start) ? $request->start : 0;

        // Fetch Product details
        $productWarehouse = ProductWarehouse::where('part_no', $part_no)->get();
        // echo "<pre>"; print_r($productWarehouse);die;
        $salezingSuccessResponse = array();
        $salezingErrorResponse = array();
        foreach ($productWarehouse as $value) {
            $part_no = $value->part_no;
            $product = Product::join('product_warehouses', 'products.id', '=', 'product_warehouses.product_id')
                        ->select('products.*', 'product_warehouses.*', 'products.seller_id as s_id', 'products.hsncode as hsncode')
                        ->where('product_warehouses.part_no', $part_no)
                        ->first();
            
            $photo_id = $product->photos;
            $url = '';
            if($photo_id != ''){
                $photo = Upload::where('id', $photo_id)->first();
                // $url = "https://storage.googleapis.com/mazing/".$photo->file_name;
                $url = env('UPLOADS_BASE_URL') . '/' . $photo->file_name;

            }

            $seller_id = $value->s_id;
            if($seller_id == ''){
                $seller_id = $value->seller_id;
            }

            $seller = Seller::where('id', $seller_id)->first();
            $user_id = $seller->user_id;

            $user = User::where('id', $user_id)->first();
            $category = Category::where('id', $product->category_id)->first();
            $categoryGroup = CategoryGroup::where('id', $product->group_id)->first();

            $seller_stock = ($value->seller_stock > 0) ? true : false;

            $seller_item = 'YES'; // if seller id 1 then NO else YES from product table
            $seller_name = $user->name;
            
            if($seller_id == '1'){
                $seller_item = 'NO';
                $seller_name = "";
            }
            $output = array();
            $output['product_id'] = $product->id;
            $output['part_no'] = $product->part_no;
            $output['item_name'] = $product->name;
            $output['alias_name'] = $product->alias_name;
            $output['billing_name'] = $product->billing_name;
            $output['hsn_code'] = $product->hsncode;
            $output['weight'] = $product->weight;
            $output['cbm'] = "$product->cbm";
            $output['SZ_Manual_Price_list'] = $product->sz_manual_price;
            $output['SZ_Group'] = $categoryGroup->name;
            $output['SZ_Category'] = $category->name;
            $output['piece_per_carton'] = $product->piece_per_carton;
            $output['import_duty'] = $product->import_duty;
            $output['rate_of_gst'] = $product->tax;
            $output['seller_stock'] = $seller_stock;
            $output['seller_item'] = $seller_item;
            $output['seller_name'] = $seller_name;
            $output['image'] = $url;
            $output['unit_of_measurement'] = "Pcs";
            $output['mrp'] = $product->mrp;
            $result['data'][] = $output;
        }
        // print_r($result); die;         
        $response = Http::withHeaders([
            'authtoken' => '65d448afc6f6b',
            'Content-Type' => 'application/json',
        ])->post('https://saleszing.co.in/itaapi/szitemstock.php?warehouseid='.$value->warehouse_id, $result);
        \Log::info('Salzing Item Push Status: ' . json_encode($response->json(), JSON_PRETTY_PRINT));
        $responseData = $response->json();
        if ($response->failed() || ((isset($responseData['totalcount']) && $responseData['totalcount'] == '0') AND (isset($responseData['totalinsert']) && $responseData['totalinsert'] == '0') AND (isset($responseData['totalupdate']) && $responseData['totalupdate'] == '0'))) {
            $data = [
                'api_name' => 'item-push',
                'code' => $product->part_no,
                'response' => 'Item didn\'t push',
                'status_code' => '400',
                'status' => 'falied'
            ];
            SalezingLog::create($data);

            return response()->json([
                'success' => false,
                'message' => 'Failed to push customer to external API.',
                'error' => $response->body()
            ], $response->status());
        }
        if((isset($responseData['totalcount']) && $responseData['totalcount'] == '0') AND (isset($responseData['totalinsert']) && $responseData['totalinsert'] == '0') AND (isset($responseData['totalupdate']) && $responseData['totalupdate'] == '0')) {
            $data = [
                'api_name' => 'item-push',
                'code' => $product->part_no,
                'response' => 'Item didn\'t push',
                'status_code' => '400',
                'status' => 'falied'
            ];
            SalezingLog::create($data);
        }else{
            if((isset($responseData['totalcount']) && $responseData['totalcount'] != '0') AND (isset($responseData['totalinsert']) && $responseData['totalinsert'] != '0') AND (isset($responseData['totalupdate']) && $responseData['totalupdate'] == '0')) {
                $data = [
                    'api_name' => 'item-push',
                    'code' => $product->part_no,
                    'response' => 'Item inserted successfully',
                    'status_code' => '200',
                    'status' => 'success'
                ];
                SalezingLog::create($data);
            }elseif((isset($responseData['totalcount']) && $responseData['totalcount'] != '0') AND (isset($responseData['totalinsert']) && $responseData['totalinsert'] == '0') AND (isset($responseData['totalupdate']) && $responseData['totalupdate'] != '0')){
                $data = [
                    'api_name' => 'item-push',
                    'code' => $product->part_no,
                    'response' => 'Item updated successfully',
                    'status_code' => '200',
                    'status' => 'success'
                ];
                SalezingLog::create($data);
            }
        }
        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to push order to external API.',
                'error' => $response->body()
            ], $response->status());
        }
        return response()->json([
            'success' => true,
            'data' => $output,
            'api_response' => $response->json()
        ]);
    }
    
}