<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\PickupPointResource;
use App\Models\Address;
use App\Models\Carrier;
use App\Models\Cart;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\Shop;
use Auth;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function pickup_list()
    {
        $pickup_point_list = PickupPoint::where('pick_up_status', '=', 1)->get();

        return PickupPointResource::collection($pickup_point_list);
        // return response()->json(['result' => true, 'pickup_points' => $pickup_point_list], 200);
    }

    public function shipping_cost(Request $request)
    {
        if($request->header('x-customer-id') !== null){
            $user_id = $request->header('x-customer-id');
        }else{
            $user_id = auth()->user()->id;
        }
        $carts = Cart::where('user_id', $user_id )
            ->get();
        $custom_shipper = '';
        $shipping_info = Address::where('id', $carts[0]['address_id'])->first();
        $total         = 0;
        $tax           = 0;
        $shipping      = 0;
        $subtotal      = 0;
        if ($carts && count($carts) > 0) {
            foreach ($carts as $key => $cartItem) {
                $product = Product::find($cartItem['product_id']);
                $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];

                // if (get_setting('shipping_type') != 'carrier_wise_shipping') {
                //     $cartItem['shipping_type']  = 'carrier';
                //     $cartItem['carrier_id']    = $request['shipper'];
                //     $carrier                   = Carrier::find($request['shipper']);
                //     if ($request['shipper'] == 372) {
                //         $custom_shipper = $request->custom_shipper_name . ',' . $request->custom_shipper_gstin . ',' . $request->custom_shipper_phone;
                //     } else {
                //         $custom_shipper = $carrier->name . ',' . $carrier->gstin . ',' . $carrier->phone;
                //     }
                //     if (!Auth::user()->shipper_allocation) {
                //         $user                     = Auth::user();
                //         $user->shipper_allocation = [['warehouse_id' => 1, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 2, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 3, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 4, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null], ['warehouse_id' => 5, 'carrier_id' => $request['shipper'], 'carrier_name' => ($request['shipper'] == 372) ? $request->custom_shipper_name : null]];
                //         $user->save();
                //     }
                //     $cartItem['shipping_cost'] = 0;
                //     if ($cartItem['shipping_type'] == 'carrier') {
                //         $cartItem['shipping_cost'] = getShippingCost($carts, $key);
                //     }
                // } else {
                //     $cartItem['shipping_type'] = 'carrier';
                //     $cartItem['carrier_id']    = $request['carrier_id_' . $product->user_id];
                //     $cartItem['shipping_cost'] = getShippingCost($carts, $key, $cartItem['carrier_id']);
                // }

                $shipping += $cartItem['shipping_cost'];
                $cartItem->save();
            }
            $total_shipping_cost = Cart::where('user_id', $user_id )->sum('shipping_cost');
            return response()->json(['result' => true, 'shipping_type' => get_setting('shipping_type'), 'value' => convert_price($total_shipping_cost), 'value_string' => format_price($total_shipping_cost)], 200);
        } else {
            return response()->json([
                'message' => 'Your Cart was empty'
            ]);
        }
    }


//     public function getDeliveryInfo()
//     {
//         $owner_ids = Cart::where('user_id', auth()->user()->id)->select('owner_id')->groupBy('owner_id')->pluck('owner_id')->toArray();
//         $carts = Cart::where('user_id', auth()->user()->id)->first();
//         $address = Address::find($carts->address_id);
//         $currency_symbol = currency_symbol();
//         $shops = [];
//         if (!empty($owner_ids)) {
//             foreach ($owner_ids as $owner_id) {
//                 $shop = array();
//                 $shop_items_raw_data = Cart::where('user_id', auth()->user()->id)->where('owner_id', $owner_id)->get()->toArray();
//                 $shop_items_data = array();
//                 if (!empty($shop_items_raw_data)) {
//                     foreach ($shop_items_raw_data as $shop_items_raw_data_item) {
//                         $product = Product::where('id', $shop_items_raw_data_item["product_id"])->first();
//                         $shop_items_data_item["id"] = intval($shop_items_raw_data_item["id"]);
//                         $shop_items_data_item["owner_id"] = intval($shop_items_raw_data_item["owner_id"]);
//                         $shop_items_data_item["user_id"] = intval($shop_items_raw_data_item["user_id"]);
//                         $shop_items_data_item["product_id"] = intval($shop_items_raw_data_item["product_id"]);
//                         $shop_items_data_item["product_name"] = $product->getTranslation('name');
//                         $shop_items_data_item["product_thumbnail_image"] = uploaded_asset($product->thumbnail_img);
//                         /*
//                         $shop_items_data_item["variation"] = $shop_items_raw_data_item["variation"];
//                         $shop_items_data_item["price"] =(double) cart_product_price($shop_items_raw_data_item, $product, false, false);
//                         $shop_items_data_item["currency_symbol"] = $currency_symbol;
//                         $shop_items_data_item["tax"] =(double) cart_product_tax($shop_items_raw_data_item, $product,false);
//                         $shop_items_data_item["shipping_cost"] =(double) $shop_items_raw_data_item["shipping_cost"];
//                         $shop_items_data_item["quantity"] =intval($shop_items_raw_data_item["quantity"]) ;
//                         $shop_items_data_item["lower_limit"] = intval($product->min_qty) ;
//                         $shop_items_data_item["upper_limit"] = intval($product->stocks->where('variant', $shop_items_raw_data_item['variation'])->first()->qty) ;
// */
//                         $shop_items_data[] = $shop_items_data_item;
//                     }
//                 }


//                 $shop_data = Shop::where('seller_id', $owner_id)->first();


//                 if ($shop_data) {
//                     $shop['name'] = $shop_data->name;
//                     $shop['owner_id'] = (int) $owner_id;
//                     $shop['cart_items'] = $shop_items_data;
//                 } else {
//                     $shop['name'] = "Inhouse";
//                     $shop['owner_id'] = (int) $owner_id;
//                     $shop['cart_items'] = $shop_items_data;
//                 }
//                 // Carriers list
//                 $address_shippers = Carrier::where('status', true)
//                     ->where('all_india', false)
//                     ->whereRaw("FIND_IN_SET('$address->state_id', delivery_states)")
//                     ->where('warehouse_id', Auth::user()->warehouse_id)
//                     ->orderBy('name', 'asc')
//                     ->get();
//                 $all_india = Carrier::where('status', true)->where('all_india', true)->orderBy('name', 'asc')->get();
//                 $other_shippers = Carrier::where('status', true)
//                     ->where('all_india', false)
//                     ->whereNotIn('id', $address_shippers->pluck('id'))
//                     ->orderBy('name', 'asc')
//                     ->get();
//                 $shop['carriers'] = $address_shippers->merge($all_india)->merge($other_shippers);
//                 $shops[] = $shop;
//             }
//         }
//         return response()->json($shops);
//     }

public function getDeliveryInfo()
    {
        if($request->header('x-customer-id') !== null){
            $user_id = $request->header('x-customer-id');
        }else{
            $user_id = auth()->user()->id;
        }
        $owner_ids = Cart::where('user_id', $user_id )->select('owner_id')->groupBy('owner_id')->pluck('owner_id')->toArray();
        $currency_symbol = currency_symbol();
        $shops = [];
        if (!empty($owner_ids)) {
            foreach ($owner_ids as $owner_id) {
                $shop = array();
                $shop_items_raw_data = Cart::where('user_id', $user_id )->where('owner_id', $owner_id)->get()->toArray();
                $shop_items_data = array();
                if (!empty($shop_items_raw_data)) {
                    foreach ($shop_items_raw_data as $shop_items_raw_data_item) {
                        $product = Product::where('id', $shop_items_raw_data_item["product_id"])->first();
                        $shop_items_data_item["id"] = intval($shop_items_raw_data_item["id"]);
                        $shop_items_data_item["owner_id"] = intval($shop_items_raw_data_item["owner_id"]);
                        $shop_items_data_item["user_id"] = intval($shop_items_raw_data_item["user_id"]);
                        $shop_items_data_item["product_id"] = intval($shop_items_raw_data_item["product_id"]);
                        $shop_items_data_item["product_name"] = $product->getTranslation('name');
                        $shop_items_data_item["product_thumbnail_image"] = uploaded_asset($product->thumbnail_img);
                        $shop_items_data_item["product_is_digital"] = $product->digital == 1;
                        /*
                        $shop_items_data_item["variation"] = $shop_items_raw_data_item["variation"];
                        $shop_items_data_item["price"] =(double) cart_product_price($shop_items_raw_data_item, $product, false, false);
                        $shop_items_data_item["currency_symbol"] = $currency_symbol;
                        $shop_items_data_item["tax"] =(double) cart_product_tax($shop_items_raw_data_item, $product,false);
                        $shop_items_data_item["shipping_cost"] =(double) $shop_items_raw_data_item["shipping_cost"];
                        $shop_items_data_item["quantity"] =intval($shop_items_raw_data_item["quantity"]) ;
                        $shop_items_data_item["lower_limit"] = intval($product->min_qty) ;
                        $shop_items_data_item["upper_limit"] = intval($product->stocks->where('variant', $shop_items_raw_data_item['variation'])->first()->qty) ;
*/
                        $shop_items_data[] = $shop_items_data_item;
                    }
                }


                $shop_data = Shop::where('seller_id', $owner_id)->first();


                if ($shop_data) {
                    $shop['name'] = $shop_data->name;
                    $shop['owner_id'] = (int) $owner_id;
                    $shop['cart_items'] = $shop_items_data;
                } else {
                    $shop['name'] = "Inhouse";
                    $shop['owner_id'] = (int) $owner_id;
                    $shop['cart_items'] = $shop_items_data;
                }
                $shop['carriers'] = seller_base_carrier_list($owner_id);
                $shop['pickup_points'] = [];
                if (get_setting('pickup_point') == 1) {
                    $pickup_point_list = PickupPoint::where('pick_up_status', '=', 1)->get();
                    $shop['pickup_points']  = PickupPointResource::collection($pickup_point_list);
                }
                $shops[] = $shop;
            }
        }

        //dd($shops);

        return response()->json($shops);
    }
}
