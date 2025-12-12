<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\CartCollection;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\ApiLog;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Session;

class CartController extends Controller
{
    public function summary(Request $request)
    {
        if($request->header('x-customer-id') !== null){
            $user_id = $request->header('x-customer-id');
        }else{
            $user_id = auth()->user()->id;
        }
        //$user = User::where('id', auth()->user()->id)->first();
        // $items = auth()->user()->carts;
        //echo $user_id;
        $items = Cart::where('customer_id',$user_id)->orWhere('user_id',$user_id)->get();
        //print_r($items);
        //die;      
        if ($items->isEmpty()) {
            return response()->json([
                'sub_total' => format_price(0.00),
                'tax' => format_price(0.00),
                'shipping_cost' => format_price(0.00),
                'discount' => format_price(0.00),
                'grand_total' => format_price(0.00),
                'grand_total_value' => 0.00,
                'coupon_code' => "",
                'coupon_applied' => false,
            ]);
        }

        $sum = 0.00;
        $subtotal = 0.00;
        $tax = 0.00;
        foreach ($items as $cartItem) {
            $item_sum = 0.00;
            // $item_sum += ($cartItem->price + $cartItem->tax) * $cartItem->quantity;
            $item_sum += ($cartItem->price) * $cartItem->quantity;
            $item_sum += $cartItem->shipping_cost - $cartItem->discount;
            $sum +=  $item_sum;   //// 'grand_total' => $request->g

            $subtotal += $cartItem->price * $cartItem->quantity;
            $tax += $cartItem->tax * $cartItem->quantity;
        }

        return response()->json([
            'sub_total' => format_price($subtotal),
            'tax' => format_price($tax),
            'shipping_cost' => format_price($items->sum('shipping_cost')),
            'discount' => format_price($items->sum('discount')),
            'grand_total' => format_price($sum),
            'grand_total_value' => price_less_than_50($sum,false),
            'coupon_code' => $items[0]->coupon_code,
            'coupon_applied' => $items[0]->coupon_applied == 1,
        ]);
    }


    public function count(Request $request)
    {
        $user=auth()->user();
        if($request->header('x-customer-id') !== null){
            // $items = $user->carts()->where('customer_id', $request->header('x-customer-id'))->get();
            $items = Cart::where('customer_id',$request->header('x-customer-id'))->orWhere('user_id',$request->header('x-customer-id'))->get();
        }else{
            // $items = auth()->user()->carts;
            $items = Cart::where('user_id',$user->id)->orWhere('customer_id',$user->id)->get();
            if(sizeof($items) <= 0){
                $items = Cart::where('user_id',$user->id)->orWhere('customer_id',$user->id)->get();
            }
        }
        // $api_log = new ApiLog;
        // $api_log->log = json_encode($items);
        // $api_log->api_name = 'count';
        // $api_log->request = $request->headers->all();
        // $api_log->save();
        return response()->json([
            'count' => sizeof($items),
            'status' => true,
        ]);
    }

    public function getList(Request $request)
    {
        if($request->header('x-customer-id') !== null){
            // $owner_ids = Cart::where('user_id', auth()->user()->id)->where('customer_id', $request->customer_id)->select('owner_id')->groupBy('owner_id')->pluck('owner_id')->toArray();
            $owner_ids = Cart::where('customer_id', $request->header('x-customer-id'))->orWhere('user_id',$request->header('x-customer-id'))->select('owner_id')->groupBy('owner_id')->pluck('owner_id')->toArray();
            $user_id = $request->header('x-customer-id');

            // $api_log = new ApiLog;
            // $api_log->log = json_encode($owner_ids);
            // $api_log->api_name = 'carts';
            // $api_log->request = $request;
            // $api_log->save();
        }else{
            $owner_ids = Cart::where('user_id', auth()->user()->id)->orWhere('customer_id',auth()->user()->id)->select('owner_id')->groupBy('owner_id')->pluck('owner_id')->toArray();
            $user_id = auth()->user()->id;
            // if(count($owner_ids) <= 0){
            //     $owner_ids = Cart::where('user_id', auth()->user()->id)->orWhere('customer_id',auth()->user()->id)->select('owner_id')->groupBy('owner_id')->pluck('owner_id')->toArray();
            // }
            // $api_log = new ApiLog;
            // $api_log->log = json_encode($owner_ids);
            // $api_log->api_name = 'carts';
            // $api_log->request = $request;
            // $api_log->save();
        }
        
        $currency_symbol = currency_symbol();
        $shops = [];
        $sub_total = 0.00;
        $grand_total = 0.00;
        if (!empty($owner_ids)) {
            foreach ($owner_ids as $owner_id) {
                $shop = array();
                if($request->header('x-customer-id') !== null){
                    $shop_items_raw_data =  Cart::where('customer_id', $request->header('x-customer-id'))->orWhere('user_id',$request->header('x-customer-id'))->where('owner_id', $owner_id)->get()->toArray();
                }else{
                    $shop_items_raw_data = Cart::where('user_id', auth()->user()->id)->orWhere('customer_id',auth()->user()->id)->where('owner_id', $owner_id)->get()->toArray();
                    // if(count($shop_items_raw_data) <= 0){
                    //     $shop_items_raw_data = Cart::where('user_id', auth()->user()->id)->orWhere('customer_id', auth()->user()->id)->where('owner_id', $owner_id)->get()->toArray();
                    // }
                }
                
                $shop_items_data = array();
                if (!empty($shop_items_raw_data)) {
                    foreach ($shop_items_raw_data as $shop_items_raw_data_item) {
                        // echo $user_id; die;
                        $product = Product::where('id', $shop_items_raw_data_item["product_id"])->first();
                        $price = cart_product_price($shop_items_raw_data_item, $product, false, false,$user_id) * intval($shop_items_raw_data_item["quantity"]);
                        $tax = cart_product_tax($shop_items_raw_data_item, $product, false,$user_id);
                        $shop_items_data_item["id"] = intval($shop_items_raw_data_item["id"]);
                        $shop_items_data_item["owner_id"] = intval($shop_items_raw_data_item["owner_id"]);
                        $shop_items_data_item["user_id"] = intval($shop_items_raw_data_item["user_id"]);
                        $shop_items_data_item["product_id"] = intval($shop_items_raw_data_item["product_id"]);
                        $shop_items_data_item["product_name"] = $product->getTranslation('name');
                        $shop_items_data_item["auction_product"] = $product->auction_product;
                        $shop_items_data_item["product_thumbnail_image"] = uploaded_asset($product->thumbnail_img);
                        $shop_items_data_item["variation"] = $shop_items_raw_data_item["variation"];
                        $shop_items_data_item["price"] = (float) cart_product_price($shop_items_raw_data_item, $product, false, false,$user_id);
                        $shop_items_data_item["currency_symbol"] = $currency_symbol;
                        $shop_items_data_item["tax"] = (float) cart_product_tax($shop_items_raw_data_item, $product, false,$user_id);
                        $shop_items_data_item["price"] = single_price($price);
                        $shop_items_data_item["currency_symbol"] = $currency_symbol;
                        $shop_items_data_item["tax"] = single_price($tax);
                        // $shop_items_data_item["tax"] = (float) cart_product_tax($shop_items_raw_data_item, $product, false);
                        $shop_items_data_item["shipping_cost"] = (float) $shop_items_raw_data_item["shipping_cost"];
                        $shop_items_data_item["quantity"] = intval($shop_items_raw_data_item["quantity"]);
                        $shop_items_data_item["lower_limit"] = intval($product->min_qty);
                        // $shop_items_data_item["upper_limit"] = intval($product->stocks->where('variant', $shop_items_raw_data_item['variation'])->first()->qty);

                        // $sub_total += $price + $tax;
                        $sub_total += $price;
                        $shop_items_data[] = $shop_items_data_item;
                    }
                }

                $grand_total += $sub_total;
                $shop_data = Shop::where('seller_id', $owner_id)->first();
                if ($shop_data) {
                    //$shop['name'] = translate($shop_data->name);
                    //$shop['owner_id'] = (int) $owner_id;
                    $shop['sub_total'] = single_price($sub_total);
                    $shop['cart_items'] = $shop_items_data;
                } else {
                    $shop['name'] = translate("Inhouse");
                    //$shop['owner_id'] = (int) $owner_id;
                    $shop['sub_total'] = single_price($sub_total);
                    $shop['cart_items'] = $shop_items_data;
                }
                $shops[] = $shop;
                $sub_total = 0.00;
            }
        }

        //dd($shops);

        return response()->json([
            "grand_total" => single_price($grand_total),
            "data" =>$shops
        ]);
    }

    // public function getList()
    // {
    //     $owner_ids = Cart::where('user_id', auth()->user()->id)->select('owner_id')->groupBy('owner_id')->pluck('owner_id')->toArray();
    //     $currency_symbol = currency_symbol();
    //     $shops = [];
    //     if (!empty($owner_ids)) {
    //         foreach ($owner_ids as $owner_id) {
    //             $shop = array();
    //             $shop_items_raw_data = Cart::where('user_id', auth()->user()->id)->where('owner_id', $owner_id)->get()->toArray();
    //             $shop_items_data = array();
    //             if (!empty($shop_items_raw_data)) {
    //                 foreach ($shop_items_raw_data as $shop_items_raw_data_item) {
    //                     $product = Product::where('id', $shop_items_raw_data_item["product_id"])->first();
    //                     $shop_items_data_item["id"] = intval($shop_items_raw_data_item["id"]);
    //                     $shop_items_data_item["owner_id"] = intval($shop_items_raw_data_item["owner_id"]);
    //                     $shop_items_data_item["user_id"] = intval($shop_items_raw_data_item["user_id"]);
    //                     $shop_items_data_item["product_id"] = intval($shop_items_raw_data_item["product_id"]);
    //                     $shop_items_data_item["product_name"] = $product->getTranslation('name');
    //                     $shop_items_data_item["product_thumbnail_image"] = uploaded_asset($product->thumbnail_img);
    //                     $shop_items_data_item["variation"] = $shop_items_raw_data_item["variation"];
    //                     $shop_items_data_item["price"] = $shop_items_raw_data_item["is_carton"] == 1 ? (float) cart_product_price($shop_items_raw_data_item, $product, false, false) * qty_per_carton($product) : (float) cart_product_price($shop_items_raw_data_item, $product, false, false);
    //                     $shop_items_data_item["currency_symbol"] = $currency_symbol;
    //                     $shop_items_data_item["tax"] = $shop_items_raw_data_item["is_carton"] == 1 ? (cart_product_tax($shop_items_raw_data_item, $product, false) * qty_per_carton($product)) : (float) cart_product_tax($shop_items_raw_data_item, $product, false);
    //                     $shop_items_data_item["shipping_cost"] = (float) $shop_items_raw_data_item["shipping_cost"];
    //                     $shop_items_data_item["quantity"] = intval($shop_items_raw_data_item["quantity"]);
    //                     $shop_items_data_item["lower_limit"] = intval($product->min_qty);
    //                     // $shop_items_data_item["upper_limit"] = intval($product->stocks->where('variant', $shop_items_raw_data_item['variation'])->first()->quantity);
    //                     $shop_items_data_item["piece_per_carton"] = $shop_items_raw_data_item["is_carton"] == 1 ? qty_per_carton($product) : 0;

    //                     $shop_items_data[] = $shop_items_data_item;

    //                 }
    //             }


    //             $shop_data = Shop::where('seller_id', $owner_id)->first();
    //             if ($shop_data) {
    //                 $shop['name'] = $shop_data->name;
    //                 $shop['owner_id'] =(int) $owner_id;
    //                 $shop['cart_items'] = $shop_items_data;
    //             } else {
    //                 $shop['name'] = "Inhouse";
    //                 $shop['owner_id'] =(int) $owner_id;
    //                 $shop['cart_items'] = $shop_items_data;
    //             }
    //             $shops[] = $shop;
    //         }
    //     }

    //     //dd($shops);

    //     return response()->json($shops);
    // }


    public function add(Request $request)
    {
        if($request->header('x-customer-id') !== null){
            $user_id = $request->header('x-customer-id');
        }else{
            $user_id = auth()->user()->id;
        }

        $product = Product::findOrFail($request->id);
        $carts   = array();

        if ($user_id != null) {
            // $user_id         = Auth::user()->id;
            $data['user_id'] = $user_id;
            $carts           = Cart::where('user_id', $user_id)->get();
        } else {
            if ($request->session()->get('temp_user_id')) {
                $temp_user_id = $request->session()->get('temp_user_id');
            } else {
                $temp_user_id = bin2hex(random_bytes(10));
                $request->session()->put('temp_user_id', $temp_user_id);
            }
            $data['temp_user_id'] = $temp_user_id;
            $carts                = Cart::where('temp_user_id', $temp_user_id)->get();
        }
        // Product variant
        $variant = '';
        if(is_countable(json_decode(Product::find($request->id)->choice_options))){
            foreach (json_decode(Product::find($request->id)->choice_options) as $key => $choice) {
                $variant .= '_' . str_replace(' ', '', strtolower(implode($choice->values)));
                $variant = ltrim($variant, '_');
            }
        }
        $tax = $ctax = 0;
        $product_stock = $product->stocks->where('variant', $variant);

        // $user = Auth::user();
        $user = User::find($user_id);

        $discount = 0;

        if ($user) {
            $discount = $user->discount;
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
        // $price = ceil($price);
        //discount calculation based on flash deal and regular discount
        //calculation of taxes
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

        //calculation of taxes
        foreach ($product->taxes as $product_tax) {
            if ($product_tax->tax_type == 'percent') {
                $tax += ($price * $product_tax->tax) / 100;
            } elseif ($product_tax->tax_type == 'amount') {
                $tax += $product_tax->tax;
            }
        }

        if ($product->min_qty > $request->quantity) {
            return response()->json(['message' => translate("Minimum") . " {$product->min_qty} " . translate("item(s) should be ordered")], 200);
        }

        // $stock = $product->stocks->where('variant', $variant)->first()->qty;
        // $variant_string = $variant != null && $variant != "" ? translate("for") . " ($variant)" : "";
        // if ($stock < $request->quantity && $product->digital == 0) {
        //     if ($stock == 0) {
        //         return response()->json(['result' => false, 'message' => "Stock out"], 200);
        //     } else {
        //         return response()->json(['result' => false, 'message' => translate("Only") . " {$stock} " . translate("item(s) are available") . " {$variant_string}"], 200);
        //     }
        // }

        if ($carts && count($carts) > 0) {
            $foundInCart = false;
            foreach ($carts as $key => $cartItem) {
                $cart_product = Product::where('id', $cartItem['product_id'])->first();
                if ($cartItem['product_id'] == $request->id) {
                    if ($cartItem['is_carton'] != $request['is_carton']) {
                        $deleteCartRequest = new Request();
                        $deleteCartRequest->replace(['id' => $cartItem['id']]);
                        $this->removeFromCart($deleteCartRequest);
                    }
                    $product_stock = $cart_product->stocks->where('variant', $variant);
                    
                }
            }
        }
        Cart::updateOrCreate([
            'user_id' => $user_id,
            'owner_id' => $product->user_id,
            // 'customer_id' => $request->user_id,
            'customer_id' => $user_id,
            'product_id' => $product->id,
            'variation' => $variant,
        ], [
            'price' =>  $price,
            'tax' => $tax,
            'shipping_cost' => 0,
            'quantity' => DB::raw("quantity + $request->quantity")
        ]);


        // if(\App\Utility\NagadUtility::create_balance_reference($request->cost_matrix) == false){
        //     return response()->json(['result' => false, 'message' => 'Cost matrix error' ]);
        // }

        return response()->json([
            'message' => translate('Product added to cart successfully')
        ], 200);
    }

    //removes from Cart
    public function removeFromCart(Request $request)
    {
        Cart::destroy($request->id);
        if (auth()->user() != null) {
            $user_id = Auth::user()->id;
            $carts   = Cart::where('user_id', $user_id)->get();
        } else {
            $temp_user_id = $request->session()->get('temp_user_id');
            $carts        = Cart::where('temp_user_id', $temp_user_id)->get();
        }
    }
    public function changeQuantity(Request $request)
    {
        $cart = Cart::find($request->id);
        if ($cart != null) {

            if ($cart->product->stocks->where('variant', $cart->variation)->first()->qty >= $request->quantity) {
                $cart->update([
                    'quantity' => $request->quantity
                ]);

                return response()->json(['result' => true, 'message' => translate('Cart updated')], 200);
            } else {
                return response()->json(['result' => false, 'message' => translate('Maximum available quantity reached')], 200);
            }
        }

        return response()->json(['result' => false, 'message' => translate('Something went wrong')], 200);
    }

    public function process(Request $request)
    {
        $cart_ids = explode(",", $request->cart_ids);
        $cart_quantities = explode(",", $request->cart_quantities);

        if (!empty($cart_ids)) {
            $i = 0;
            foreach ($cart_ids as $cart_id) {
                $cart_item = Cart::where('id', $cart_id)->first();
                $product = Product::where('id', $cart_item->product_id)->first();

                // if ($product->min_qty > $cart_quantities[$i]) {
                //     return response()->json(['result' => false, 'message' => translate("Minimum") . " {$product->min_qty} " . translate("item(s) should be ordered for") . " {$product->name}"], 200);
                // }

                // $stock = $cart_item->product->stocks->where('variant', $cart_item->variation)->first()->qty;
                // $variant_string = $cart_item->variation != null && $cart_item->variation != "" ? " ($cart_item->variation)" : "";
                // if ($stock >= $cart_quantities[$i] || $product->digital == 1) {
                    $cart_item->update([
                        'quantity' => $cart_quantities[$i]
                    ]);
                // } else {
                //     if ($stock == 0) {
                //         return response()->json(['result' => false, 'message' => translate("No item is available for") . " {$product->name}{$variant_string}," . translate("remove this from cart")], 200);
                //     } else {
                //         return response()->json(['result' => false, 'message' => translate("Only") . " {$stock} " . translate("item(s) are available for") . " {$product->name}{$variant_string}"], 200);
                //     }
                // }

                $i++;
            }

            return response()->json(['result' => true, 'message' => translate('Cart updated')], 200);
        } else {
            return response()->json(['result' => false, 'message' => translate('Cart is empty')], 200);
        }
    }

    public function destroy($id)
    {
        Cart::destroy($id);
        return response()->json(['result' => true, 'message' => translate('Product is successfully removed from your cart')], 200);
    }
}
