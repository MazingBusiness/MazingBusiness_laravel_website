<?php

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\ClubPointController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\AdminStatementController;
use App\Http\Resources\V2\CarrierCollection;
use App\Models\Addon;
use App\Models\Address;
use App\Models\BusinessSetting;
use App\Models\Carrier;
use App\Models\Cart;
use App\Models\City;
use App\Models\CombinedOrder;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Currency;
use App\Models\CustomerPackage;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\SellerPackage;
use App\Models\SellerPackagePayment;
use App\Models\Shop;
use App\Models\Translation;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Warehouse;
use App\Utility\CategoryUtility;
use App\Utility\NotificationUtility;
use App\Utility\SendSMSUtility;
use App\Utility\SendWhatsAppUtility;
use Carbon\Carbon;
use Intervention\Image\Facades\Image;
use App\Models\Upload;


if (! function_exists('pdf_opt_image')) {
    /**
     * Upload ID se PDF ke liye optimized image ka URL.
     *
     * @param  int|string|null  $uploadId
     * @param  int              $maxWidth
     * @param  int              $quality
     * @return string|null
     */
    function pdf_opt_image($uploadId, int $maxWidth = 600, int $quality = 70): ?string
    {
        if (empty($uploadId)) {
            return null;
        }

        /** @var Upload|null $upload */
        $upload = Upload::find($uploadId);
        if (! $upload) {
            return null;
        }

        // original file
        $originalPath = public_path('uploads/' . $upload->file_name);
        if (! file_exists($originalPath)) {
            // agar file nahi mili to normal uploaded_asset use kar lo
            return uploaded_asset($uploadId);
        }

        // compressed folder
        $thumbDir = public_path('uploads/pdf_thumbs');
        if (! is_dir($thumbDir)) {
            @mkdir($thumbDir, 0755, true);
        }

        // naya naam
        $ext       = pathinfo($originalPath, PATHINFO_EXTENSION) ?: 'jpg';
        $thumbName = 'pdf_' . md5($upload->file_name) . '.' . $ext;
        $thumbPath = $thumbDir . '/' . $thumbName;
        
        // agar pehle se bana hua hai to fir se process mat karo
        if (! file_exists($thumbPath)) {
            try {
                $img = Image::make($originalPath);

                // width limit, height auto
                $img->resize($maxWidth, null, function ($c) {
                    $c->aspectRatio();
                    $c->upsize();
                });

                // compress
                $img->save($thumbPath, $quality);
            } catch (\Throwable $e) {
                // koi error aaye to original URL de do
                return uploaded_asset($uploadId);
            }
        }

        return asset('uploads/pdf_thumbs/' . $thumbName);
    }
}

if (!function_exists('getFirstOverdueDays')) {
    function getFirstOverdueDays($partyCode) {
        return app(AdminStatementController::class)->getFirstOverdueDays($partyCode);
    }
}

//sensSMS function
if (!function_exists('sendSMS')) {
  function sendSMS($to, $from, $text, $template_id) {
    return SendSMSUtility::sendSMS($to, $from, $text, $template_id);
  }
}

//sensWhatsApp function
if (!function_exists('sendWhatsApp')) {
  function sendWhatsApp($customer, $params, $media, $campaignName) {
    return SendWhatsAppUtility::sendWhatsApp($customer, $params, $media, $campaignName);
  }
}

//highlights the selected navigation on admin panel
if (!function_exists('areActiveRoutes')) {
  function areActiveRoutes(array $routes, $output = "active") {
    foreach ($routes as $route) {
      if (Route::currentRouteName() == $route) {
        return $output;
      }
    }
  }
}

//highlights the selected navigation on frontend
if (!function_exists('areActiveRoutesHome')) {
  function areActiveRoutesHome(array $routes, $output = "active") {
    foreach ($routes as $route) {
      if (Route::currentRouteName() == $route) {
        return $output;
      }
    }
  }
}

//highlights the selected navigation on frontend
if (!function_exists('default_language')) {
  function default_language() {
    return env("DEFAULT_LANGUAGE");
  }
}

/**
 * Save JSON File
 * @return Response
 */
if (!function_exists('convert_to_usd')) {
  function convert_to_usd($amount) {
    $currency = Currency::find(get_setting('system_default_currency'));
    return (floatval($amount) / floatval($currency->exchange_rate)) * Currency::where('code', 'USD')->first()->exchange_rate;
  }
}

if (!function_exists('convert_to_kes')) {
  function convert_to_kes($amount) {
    $currency = Currency::find(get_setting('system_default_currency'));
    return (floatval($amount) / floatval($currency->exchange_rate)) * Currency::where('code', 'KES')->first()->exchange_rate;
  }
}

//filter products based on vendor activation system
if (!function_exists('filter_products')) {
  function filter_products($products) {
    $verified_sellers = verified_sellers_id();
    if (get_setting('vendor_system_activation') == 1) {
      return $products->where('approved', '1')
        ->where('published', '1')
        ->where(function ($p) use ($verified_sellers) {
          $p->where('added_by', 'admin')->orWhere(function ($q) use ($verified_sellers) {
            $q->whereIn('user_id', $verified_sellers);
          });
        });
    } else {
      return $products->where('published', '1')->where('added_by', 'admin');
    }
  }
}

//cache products based on category
if (!function_exists('get_cached_products')) {
  function get_cached_products($category_id = null) {
    // $products         = \App\Models\Product::where('published', 1)->where('approved', '1')->whereNotNull('photos');
    $products         = \App\Models\Product::where('published', 1)->where('approved', '1');
    $verified_sellers = verified_sellers_id();
    if (get_setting('vendor_system_activation') == 1) {
      $products = $products->where(function ($p) use ($verified_sellers) {
        $p->where('added_by', 'admin')->orWhere(function ($q) use ($verified_sellers) {
          $q->whereIn('user_id', $verified_sellers);
        });
      });
    } else {
      $products = $products->where('added_by', 'admin');
    }

    if ($category_id != null) {
      return Cache::remember('products-category-' . $category_id, 86400, function () use ($category_id, $products) {
        $category_ids   = CategoryUtility::children_ids($category_id);
        $category_ids[] = $category_id;
        return $products->whereIn('category_id', $category_ids)->latest()->take(20)->get();
      });
    } else {
      return Cache::remember('products', 86400, function () use ($products) {
        return $products->latest()->take(20)->get();
      });
    }
  }
}

if (!function_exists('verified_sellers_id')) {
  function verified_sellers_id() {
    return Cache::rememberForever('verified_sellers_id', function () {
      return App\Models\Seller::where('verification_status', 1)->pluck('user_id')->toArray();
    });
  }
}

if (!function_exists('get_system_default_currency')) {
  function get_system_default_currency() {
    return Cache::remember('system_default_currency', 86400, function () {
      return Currency::findOrFail(get_setting('system_default_currency'));
    });
  }
}

//converts currency to home default currency
// Converts currency to home default currency and rounds up
if (!function_exists('convert_price')) {
  function convert_price($price) {
    if (Session::has('currency_code') && (Session::get('currency_code') != get_system_default_currency()->code)) {
      // Convert to base currency
      $price = floatval($price) / floatval(get_system_default_currency()->exchange_rate);
      // Apply session exchange rate
      $price = floatval($price) * floatval(Session::get('currency_exchange_rate'));
    }

    // Ensure price is rounded up to the next highest whole number or to the desired decimal
    if($price <= 50){
      $multiplier = pow(10, get_setting('no_of_decimals'));
      $price = ceil($price * $multiplier) / $multiplier;
    }    
    return $price;
  }
}

//gets currency symbol
if (!function_exists('currency_symbol')) {
  function currency_symbol() {
    if (Session::has('currency_symbol')) {
      return Session::get('currency_symbol');
    }
    return get_system_default_currency()->symbol;
  }
}

//formats currency
if (!function_exists('format_price')) {
  function format_price($price, $isMinimize = false) {
    
    if (get_setting('decimal_separator') == 1) {
      $fomated_price = number_format($price, get_setting('no_of_decimals'));
    } else {
      $fomated_price = number_format($price, get_setting('no_of_decimals'), ',', '.');
    }
    
    

    // Minimize the price
    if ($isMinimize) {
      $temp = number_format($price / 1000000000, get_setting('no_of_decimals'), ".", "");

      if ($temp >= 1) {
        $fomated_price = $temp . "B";
      } else {
        $temp = number_format($price / 1000000, get_setting('no_of_decimals'), ".", "");
        if ($temp >= 1) {
          $fomated_price = $temp . "M";
        }
      }
    }

    if (get_setting('symbol_format') == 1) {
      return currency_symbol() . $fomated_price;
    } else if (get_setting('symbol_format') == 3) {
      return currency_symbol() . ' ' . $fomated_price;
    } else if (get_setting('symbol_format') == 4) {
      return $fomated_price . ' ' . currency_symbol();
    }
    return $fomated_price . currency_symbol();
  }
}

//formats price to home default price with convertion
if (!function_exists('single_price')) {
  function single_price($price) {
    // if($price <= 50){
    //   // Truncate the number to two decimal places without rounding
    //   $truncated = floor($price * 100) / 100;
    //   // Format the truncated number to two decimal places
    //   $price = number_format($truncated, 2, '.', '');
    //   return currency_symbol() . $price;
    // }else{
    //   return price_less_than_50($price);
    // }
    return price_less_than_50($price);
  }
}

if (!function_exists('price_less_than_50')) {
  function price_less_than_50($price, $formatted = true) {
    if ($price <= 50) {
        // Round the price based on the custom rule
        $integer_part = floor($price); // Get the integer part
        $decimal_part = $price - $integer_part; // Get the decimal part

        if ($decimal_part < 0.05) {
            $rounded_price = $integer_part; // Round down to the integer
        } else {
            $rounded_price = round($price, 1); // Round to the nearest tenth
        }

        // Format the rounded price to one decimal place
        $price = number_format($rounded_price, 1, '.', '');

        if ($formatted == true) {
            return currency_symbol() . $price;
        } else {
            return $price;
        }
    } else {
      if ($formatted == true) {
          return currency_symbol() . ceil($price);
      } else {
          return $price;
      }
    }
  }
}

if (!function_exists('product_min_qty')) {
  function product_min_qty($product, $user_id="") {
    $price        = (float) (home_discounted_price($product, false, $user_id)['price']);
    $target       = (float) env('SPECIAL_DISCOUNT_AMOUNT', 5000);
    $spPercentage = (float) env('SPECIAL_DISCOUNT_PERCENTAGE', 3);

    if ($price > 0) {
        $spDQty = (int) ceil($target / $price);
        if (($spDQty * $price) <= $target) {
            $spDQty++;
        }
        // if env is 10 for 10%, use /100:
        $spDPrice = $price - ($price * ($spPercentage / 100));
    } else {
        $spDQty   = 1;
        $spDPrice = 0;
    }
    $product->min_qty = $spDQty;
    return $product;
  }
}

if (!function_exists('product_price_with_qty_condition')) {
  function product_price_with_qty_condition($product, $user_id="", $qty) {
    $price        = (float) (home_discounted_price($product, false, $user_id)['price']);
    $target       = (float) env('SPECIAL_DISCOUNT_AMOUNT', 5000);
    $spPercentage = (float) env('SPECIAL_DISCOUNT_PERCENTAGE', 3);

    if (($price * $qty) < $target) {
        // if env is 10 for 10%, use /100:
        $spDPrice = $price + ($price * ($spPercentage / 100));
    } else {
        $spDPrice = $price;
    }
    return $spDPrice;
  }
}


if (!function_exists('discount_in_percentage')) {
  function discount_in_percentage($product,$user_id="") {
    // echo $user_id;
    $base     = home_base_price($product, false, $user_id="");
    $reduced  = home_discounted_base_price($product, false, $user_id);
    $discount = $base - $reduced;
    $dp       = ($discount * 100) / ($base > 0 ? $base : 1);
    return round($dp);
  }
}

//Shows Price on page based on carts
if (!function_exists('cart_product_price')) {
  function cart_product_price($cart_product, $product, $formatted = true, $tax = true,$user_id="") {
    $str = '';
    if ($cart_product['variation'] != null) {
      $str = $cart_product['variation'];
    }
    $price         = 0;
    $product_stock = $product->stocks->where('variant', $str);
    $user = User::find($user_id);

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
    $price = ceil_price($price);

    if($cart_product['quantity'] >= $product->piece_by_carton )
    {
      $price = $price * 0.98;
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

    //calculation of taxes
    if ($tax) {
      $taxAmount = 0;
      foreach ($product->taxes as $product_tax) {
        if ($product_tax->tax_type == 'percent') {
          $taxAmount += ($price * $product_tax->tax) / 100;
        } elseif ($product_tax->tax_type == 'amount') {
          $taxAmount += $product_tax->tax;
        }
      }
      // $price += $taxAmount;
    }

    if ($formatted && false) {
      return price_less_than_50($price);
    } else {
      return $price;
    }
  }
}

if (!function_exists('cart_product_tax')) {
  function cart_product_tax($cart_product, $product, $formatted = true, $user_id="") {
    $str = '';
    if ($cart_product['variation'] != null) {
      $str = $cart_product['variation'];
    }
    $price         = 0;
    $product_stock = $product->stocks->where('variant', $str);
    // $user = Auth::user();
    $user = User::find($user_id);
    
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
    $price = ceil_price($price);

    if($cart_product['quantity'] >= $product->piece_by_carton )
    {
      $price = $price * 0.98;
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

    //calculation of taxes
    $tax = 0;
    foreach ($product->taxes as $product_tax) {
      if ($product_tax->tax_type == 'percent') {
        $tax += (($price / 1.18) * $product_tax->tax) / 100;
      } elseif ($product_tax->tax_type == 'amount') {
        $tax += $product_tax->tax;
      }
    }

    //calculation of taxes
    // foreach ($product->taxes as $product_tax) {
    //     if ($product_tax->tax_type == 'percent') {
    //         $tax += ($price * $product_tax->tax) / 100;
    //     } elseif ($product_tax->tax_type == 'amount') {
    //         $tax += $product_tax->tax;
    //     }
    // }

    // echo $tax;die;
    if ($formatted) {
      return format_price(convert_price($tax));
    } else {
      return $tax;
    }
  }
}

if (!function_exists('cart_product_discount')) {
  function cart_product_discount($cart_product, $product, $formatted = false) {
    $str = '';
    if ($cart_product['variation'] != null) {
      $str = $cart_product['variation'];
    }
    $product_stock = $product->stocks->where('variant', $str)->first();
    $price         = $product_stock->price;

    //discount calculation
    $discount_applicable = false;
    $discount            = 0;

    // if ($product->discount_start_date == null) {
    //   $discount_applicable = true;
    // } elseif (
    //   strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
    //   strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date
    // ) {
    //   $discount_applicable = true;
    // }

    // if ($discount_applicable) {
    //   if ($product->discount_type == 'percent') {
    //     $discount = ($price * $product->discount) / 100;
    //   } elseif ($product->discount_type == 'amount') {
    //     $discount = $product->discount;
    //   }
    // }

    if ($formatted) {
      return format_price(convert_price($discount));
    } else {
      return $discount;
    }
  }
}

// all discount
if (!function_exists('carts_product_discount')) {
  function carts_product_discount($cart_products, $formatted = false) {
    $discount = 0;
    foreach ($cart_products as $key => $cart_product) {
      $str     = '';
      $product = \App\Models\Product::find($cart_product['product_id']);
      if ($cart_product['variation'] != null) {
        $str = $cart_product['variation'];
      }
      $product_stock = $product->stocks->where('variant', $str)->first();
      $price         = $product_stock->price;

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
          $discount += ($price * $product->discount) / 100;
        } elseif ($product->discount_type == 'amount') {
          $discount += $product->discount;
        }
      }
    }

    if ($formatted) {
      return format_price(convert_price($discount));
    } else {
      return $discount;
    }
  }
}

if (!function_exists('carts_coupon_discount')) {
  function carts_coupon_discount($code, $formatted = false) {
    $coupon          = Coupon::where('code', $code)->first();
    $coupon_discount = 0;
    if ($coupon != null) {
      if (!$coupon->customer_id || ($coupon->customer_id && ($coupon->customer_id == Auth::user()->id))) {
        if (strtotime(date('d-m-Y')) >= $coupon->start_date && strtotime(date('d-m-Y')) <= $coupon->end_date) {
          $coupon_usage_count = CouponUsage::where('user_id', Auth::user()->id)->where('coupon_id', $coupon->id)->count();
          if (($coupon->new_user_only && !Auth::user()->load(['orders' => function ($query) {
            $query->where('payment_status', '!=', 'unpaid');
          }])->orders->count()) || !$coupon->new_user_only) {
            if ($coupon_usage_count == null) {
              $coupon_details = json_decode($coupon->details);

              $carts = Cart::where('user_id', Auth::user()->id)
                ->where('owner_id', $coupon->user_id)
                ->get();

              if ($coupon->type == 'cart_base') {
                $subtotal = 0;
                $tax      = 0;
                $shipping = 0;
                foreach ($carts as $key => $cartItem) {
                  $product = Product::find($cartItem['product_id']);
                  if ($cartItem['is_carton']) {
                    $ppc = $product->stocks->first()->piece_per_carton;
                    $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'] * $ppc;
                    $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'] * $cartItem['quantity'] * $ppc;
                  } else {
                    $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
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
                          $ppc = $product->stocks->first()->piece_per_carton;
                          $coupon_discount += (cart_product_price($cartItem, $product, false, false) * $coupon->discount / 100) * $cartItem['quantity'] * $ppc;
                        } else {
                          $coupon_discount += (cart_product_price($cartItem, $product, false, false) * $coupon->discount / 100) * $cartItem['quantity'];
                        }
                      } elseif ($coupon->discount_type == 'amount') {
                        if ($cartItem['is_carton']) {
                          $ppc = $product->stocks->first()->piece_per_carton;
                          $coupon_discount += $coupon->discount * $cartItem['quantity'] * $ppc;
                        } else {
                          $coupon_discount += $coupon->discount * $cartItem['quantity'];
                        }
                      }
                    }
                  }
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
              'discount' => $coupon_discount / count($carts),
            ]
          );
      } else {
        Cart::where('user_id', Auth::user()->id)
          ->where('owner_id', $coupon->user_id)
          ->update(
            [
              'discount'    => 0,
              'coupon_code' => null,
            ]
          );
      }
    }

    if ($formatted) {
      return format_price(convert_price($coupon_discount));
    } else {
      return $coupon_discount;
    }
  }
}

if (!function_exists('get_estimated_shipping_days')) {
  function get_estimated_shipping_days($product) {
    $wmarkup           = $p_stock           = $s_stock           = 0;
    $user_warehouse_id = (Auth::check() && Auth::user()->user_type == 'customer') ? Auth::user()->warehouse_id : null;
    if ($user_warehouse_id) {
      $same_warehouse_stock = $product->stocks->where('warehouse_id', $user_warehouse_id)->first();
      if ($same_warehouse_stock) {
        $p_stock = $same_warehouse_stock->qty;
        if ($p_stock > 0) {
          return ['days' => $product->est_shipping_days, 'immediate' => 1];
        }
        $s_stock = $same_warehouse_stock->seller_stock;
        if ($s_stock > 0) {
          return ['days' => $product->est_shipping_days + 1, 'immediate' => 0];
        }
      }
      $warehouse = Warehouse::find($user_warehouse_id);
      foreach ($warehouse->markup as $wamarkup) {
        $stock = $product->stocks->where('warehouse_id', $wamarkup['warehouse_id'])->first();
        $wmarkup += 2;
        if ($stock) {
          $p_stock = $stock->qty;
          if ($p_stock > 0) {
            return ['days' => $product->est_shipping_days + $wmarkup, 'immediate' => 0];
          }
          $s_stock = $stock->seller_stock;
          if ($s_stock > 0) {
            return ['days' => $product->est_shipping_days + 1 + $wmarkup, 'immediate' => 0];
          }
        }
      }
      return ['days' => 0, 'immediate' => 0];
    } else {
      return ['days' => $product->est_shipping_days, 'immediate' => 0];
    }
  }
}

if (!function_exists('isActingAs41Manager')) {
    function isActingAs41Manager(): bool
    {
        // 1) Impersonation (staff_id) → check that user first
        $user = null;
        try {
            if (session()->has('staff_id')) {
                $user = \App\Models\User::find((int) session('staff_id'));
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 2) Otherwise current logged-in user
        if (!$user) {
            $user = Auth::user();
        }
        if (!$user) {
            return false;
        }

        // 3) Compare normalized user_type / user_title
        $title = strtolower(trim((string) $user->user_title));
        $type  = strtolower(trim((string) $user->user_type));

        if ($type === 'manager_41') {
            return true;
        }

        $aliases = ['manager_41'];
        return in_array($title, $aliases, true);
    }
}

// Shows Price on Product Details Page
if (!function_exists('home_price')) {
  function home_price($product, $formatted = true) {
    // --- Manager-41: use mrp_41_price verbatim, no discounts/markups ---
    $is41 = function_exists('isActingAs41Manager') ? isActingAs41Manager() : false;
    if ($is41) {
      $base = isset($product->mrp_41_price) && $product->mrp_41_price !== '' ? $product->mrp_41_price
             : \App\Models\Product::where('id', $product->id)->value('mrp_41_price');
      if (!is_numeric($base) || (float)$base <= 0) {
        $base = isset($product->mrp) && $product->mrp !== '' ? $product->mrp
               : \App\Models\Product::where('id', $product->id)->value('mrp');
      }
      $price = (float) $base;
      return $formatted
        ? ['price' => price_less_than_50($price), 'carton_price' => price_less_than_50(0)]
        : ['price' => $price, 'carton_price' => 0];
    }

    // --- Non-41 (original behavior) ---
    $price = $cprice = 0;
    $discount = 20;
    if ($user = Auth::user()) { $discount = $user->discount; }
    if (!is_numeric($discount) || $discount == 0) { $discount = 20; }

    $base = isset($product->mrp) && $product->mrp !== '' ? $product->mrp
           : \App\Models\Product::where('id', $product->id)->value('mrp');
    $price = (float) ($base ?: 0);
    if (!is_numeric($price)) { $price = 0; }

    $price = $price * ((100 - $discount) / 100);
    $price = ceil_price($price);
    $price = $price * 131.6 / 100;
    $price = ceil_price($price);

    return $formatted
      ? ['price' => price_less_than_50($price), 'carton_price' => price_less_than_50($cprice)]
      : ['price' => $price, 'carton_price' => $cprice];
  }
}

// Shows Price on page based on low to high with discount
if (!function_exists('home_discounted_price')) {
  function home_discounted_price($product, $formatted = true, $user_id = "") {
    // --- Manager-41: use mrp_41_price verbatim, no user/product discounts ---
    $is41 = function_exists('isActingAs41Manager') ? isActingAs41Manager() : false;
    if ($is41) {
      $base = isset($product->mrp_41_price) && $product->mrp_41_price !== '' ? $product->mrp_41_price
             : \App\Models\Product::where('id', $product->id)->value('mrp_41_price');
      if (!is_numeric($base) || (float)$base <= 0) {
        $base = isset($product->mrp) && $product->mrp !== '' ? $product->mrp
               : \App\Models\Product::where('id', $product->id)->value('mrp');
      }
      $price = (float) $base;
      return $formatted
        ? ['price' => price_less_than_50($price), 'carton_price' => price_less_than_50(0)]
        : ['price' => $price, 'carton_price' => 0];
    }

    // --- Non-41 (original behavior) ---
    $price = $cprice = 0;
    $discount = 20;

    if ($user_id != "") {
      $u = \App\Models\User::find($user_id);
      if ($u && is_numeric($u->discount)) { $discount = $u->discount; }
    } elseif ($u = Auth::user()) {
      if (is_numeric($u->discount)) { $discount = $u->discount; }
    }
    if (!is_numeric($discount) || $discount == 0) { $discount = 20; }

    $base = isset($product->mrp) && $product->mrp !== '' ? $product->mrp
           : \App\Models\Product::where('id', $product->id)->value('mrp');

    $price = (float) ($base ?: 0);
    if (!is_numeric($price)) { $price = 0; }

    $price  = ceil_price($price * ((100 - $discount) / 100));
    $cprice = $price;

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
        $cprice -= ($cprice * $product->discount) / 100;
        $price  -= ($price  * $product->discount)  / 100;
      } elseif ($product->discount_type == 'amount') {
        $cprice -= $product->discount;
        $price  -= $product->discount;
      }
    }

    return $formatted
      ? ['price' => price_less_than_50($price), 'carton_price' => price_less_than_50($cprice)]
      : ['price' => $price, 'carton_price' => $cprice];
  }
}


if (!function_exists('home_bulk_discounted_price')) {
  function home_bulk_discounted_price($product, $formatted = true, $user_id = "") {
    // --- Manager-41: use mrp_41_price verbatim, NO extra 2%, NO product/user discount ---
    $is41 = function_exists('isActingAs41Manager') ? isActingAs41Manager() : false;
    if ($is41) {
      $base = isset($product->mrp_41_price) && $product->mrp_41_price !== '' ? $product->mrp_41_price
             : \App\Models\Product::where('id', $product->id)->value('mrp_41_price');
      if (!is_numeric($base) || (float)$base <= 0) {
        $base = isset($product->mrp) && $product->mrp !== '' ? $product->mrp
               : \App\Models\Product::where('id', $product->id)->value('mrp');
      }
      $price = (float) $base;
      return $formatted
        ? ['price' => price_less_than_50($price), 'carton_price' => price_less_than_50(0)]
        : ['price' => price_less_than_50($price, false), 'carton_price' => price_less_than_50(0, false)];
    }

    // --- Non-41 (original behavior) ---
    $price = $cprice = 0;
    $discount = 20;

    if ($user_id == "" && Auth::check()) {
      $u = Auth::user();
      if ($u && is_numeric($u->discount)) { $discount = $u->discount; }
    } elseif ($user_id != "") {
      $u = \App\Models\User::find($user_id);
      if ($u && is_numeric($u->discount)) { $discount = $u->discount; }
    }
    if (!is_numeric($discount) || $discount == 0) { $discount = 20; }

    $base = isset($product->mrp) && $product->mrp !== '' ? $product->mrp
           : \App\Models\Product::where('id', $product->id)->value('mrp');

    $price = (float) ($base ?: 0);
    if (!is_numeric($price)) { $price = 0; }

    $price  = ceil_price($price * ((100 - $discount) / 100));
    $cprice = $price;

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
        $cprice -= ($cprice * $product->discount) / 100;
        $price  -= ($price  * $product->discount)  / 100;
      } elseif ($product->discount_type == 'amount') {
        $cprice -= $product->discount;
        $price  -= $product->discount;
      }
    }

    // original bulk extra 2%
    $price = $price * 0.98;

    return $formatted
      ? ['price' => price_less_than_50($price), 'carton_price' => price_less_than_50($cprice)]
      : ['price' => price_less_than_50($price, false), 'carton_price' => price_less_than_50($cprice, false)];
  }
}


if (!function_exists('ceil_price')) {
  function ceil_price($price) {
    if($price > 50){
      return ceil($price);
    }else{
      return $price;
    }
  }
}

if (!function_exists('home_bulk_qty')) {
  function home_bulk_qty($product, $formatted = true) {
    
    $product_bulk_qty = Product::where('id', $product->id)->select('piece_by_carton')->first();
    if ($product_bulk_qty) {
        $bulk_qty = $product_bulk_qty->piece_by_carton;
    } else {
      $bulk_qty = 0;
    }

    return ['bulk_qty' => $bulk_qty];
  }
}

//Shows Base Price
if (!function_exists('home_base_price_by_stock_id')) {
  function home_base_price_by_stock_id($id) {
    $product_stock = ProductWarehouse::findOrFail($id);
    $product       = $product_stock->load('product:id,category_id,brand_id', 'product.category:id,markup', 'product.brand:id,markup');
     
    $user = Auth::user();

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
    $price = $price * 131.6 / 100;
    $price = ceil_price($price);

    return price_less_than_50($price);
  }
}

if (!function_exists('home_base_price')) {
  function home_base_price($product, $formatted = true, $user_id="") {
    $product        = $product->load('category:id,markup', 'brand:id,markup');
    $price          = $discount   = 0;
    // echo $user_id."....";
    if($user_id!=""){
      $user = User::where('id',$user_id)->first();
    }elseif(Auth::check()){
      $user = Auth::user();
    }else{
      $user = null;
    }


    if ($user !== null) {
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
    // echo $product_mrp->mrp.".....".$price; die;

    $price = $price * 131.6 / 100;
    $price = ceil_price($price);

    $tax = 0;
    foreach ($product->taxes as $product_tax) {
      if ($product_tax->tax_type == 'percent') {
        $tax += ($price * $product_tax->tax) / 100;
      } elseif ($product_tax->tax_type == 'amount') {
        $tax += $product_tax->tax;
      }
    }
    // $price += $tax;
    return $formatted ? price_less_than_50($price) : $price;
  }
}

if (!function_exists('home_base_carton_price')) {
  function home_base_carton_price($product, $formatted = true) {
    $product_stocks = ProductWarehouse::where('product_id', $product->id)->get();
    $product        = $product->load('category:id,markup', 'brand:id,markup');
    // $price          = $markup          = $wmarkup          = 0;
    // if ($product->brand->markup) {
    //   $markup = $product->brand->markup;
    // } else if ($product->category->markup) {
    //   $markup = $product->category->markup;
    // }
    // $user_warehouse_id = (Auth::check() && Auth::user()->user_type == 'customer') ? Auth::user()->warehouse_id : null;
    // if ($user_warehouse_id) {
    //   $same_warehouse_stock = $product_stocks->where('warehouse_id', $user_warehouse_id)->first();
    //   if ($same_warehouse_stock) {
    //     $price = $same_warehouse_stock->carton_price;
    //   } else {
    //     $warehouse = Warehouse::find($user_warehouse_id);
    //     foreach ($warehouse->markup as $wamarkup) {
    //       $p_stock = $product_stocks->where('warehouse_id', $wamarkup['warehouse_id'])->first();
    //       $wmarkup += $wamarkup['markup'];
    //       if ($p_stock) {
    //         $price = $p_stock->carton_price;
    //         break;
    //       }
    //     }
    //   }
    // } else {
    //   $price = $product_stocks->min('carton_price');
    // }
    // $price += $price * ($markup + $wmarkup) / 100;
    // $tax = 0;
    // foreach ($product->taxes as $product_tax) {
    //   if ($product_tax->tax_type == 'percent') {
    //     $tax += ($price * $product_tax->tax) / 100;
    //   } elseif ($product_tax->tax_type == 'amount') {
    //     $tax += $product_tax->tax;
    //   }
    // }
    // $price += $tax;

    $price          = $discount   = 0;

    $user = Auth::user();

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

    $price = $price * 131.6 / 100;
    $price = ceil_price($price);

    return $formatted ? price_less_than_50($price) : $price;
  }
}

if (!function_exists('qty_per_carton')) {
  function qty_per_carton($product, $formatted = true) {
    $product_carton_qty = ProductWarehouse::where('product_id', $product->id)->value('piece_per_carton');
    return $product_carton_qty;
  }
}

//Shows Base Price with discount
if (!function_exists('home_discounted_base_price_by_stock_id')) {
  function home_discounted_base_price_by_stock_id($id) {
    $product_stock = ProductWarehouse::findOrFail($id);
    $product       = $product_stock->load('product:id,category_id,brand_id', 'product.category:id,markup', 'product.brand:id,markup');
    
    $user = Auth::user();

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
    $price = $price * 131.6 / 100;
    $price = ceil_price($price);

    $tax                 = 0;
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
    // $price += $tax;
    return price_less_than_50($price);
  }
}

//Shows Base Price with discount

if (!function_exists('home_discounted_base_price')) {
  function home_discounted_base_price($product, $formatted = true, $user_id="") {
    // echo $user_id;die;
    $product_stocks = ProductWarehouse::where('product_id', $product->id)->get();
    $product        = $product->load('category:id,markup', 'brand:id,markup');
    $price          = $discount   = 0;

    // if ($product->brand->markup) {
    //   $markup = $product->brand->markup;
    // } else if ($product->category->markup) {
    //   $markup = $product->category->markup;
    // }
    // $user_warehouse_id = (Auth::check() && Auth::user()->user_type == 'customer') ? Auth::user()->warehouse_id : null;
    // if ($user_warehouse_id) {
    //   $same_warehouse_stock = $product_stocks->where('warehouse_id', $user_warehouse_id)->first();
    //   if ($same_warehouse_stock) {
    //     $price = $same_warehouse_stock->price;
    //   } else {
    //     $warehouse = Warehouse::find($user_warehouse_id);
    //     foreach ($warehouse->markup as $wamarkup) {
    //       $p_stock = $product_stocks->where('warehouse_id', $wamarkup['warehouse_id'])->first();
    //       $wmarkup += $wamarkup['markup'];
    //       if ($p_stock) {
    //         $price = $p_stock->price;
    //         break;
    //       }
    //     }
    //   }
    // } else {
    //   $price = $product_stocks->min('price');
    // }
    // $price += $price * ($markup + $wmarkup) / 100;
    if($user_id!=""){
      $user = User::where('id',$user_id)->first();
    }elseif(Auth::check()){
      $user = Auth::user();
    }else{
      $user = null;
    }

    if ($user) {
        $discount = $user->discount;
    } else {
        // echo
         "<script>console.log('User not logged in');</script>";
    }
    
    if(!is_numeric($discount) || $discount == 0) {
      $discount = 20;
    }
    // echo $discount;die;
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
    $price = ceil_price($price);
    // echo $product->id;
    // echo $product_mrp->mrp."....".$price; die;

    $tax                 = 0;
    // $discount_applicable = false;
    // if ($product->discount_start_date == null) {
    //   $discount_applicable = true;
    // } elseif (
    //   strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
    //   strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date
    // ) {
    //   $discount_applicable = true;
    // }
    // if ($discount_applicable) {
    //   if ($product->discount_type == 'percent') {
    //     $price -= ($price * $product->discount) / 100;
    //   } elseif ($product->discount_type == 'amount') {
    //     $price -= $product->discount;
    //   }
    // }
    foreach ($product->taxes as $product_tax) {
      if ($product_tax->tax_type == 'percent') {
        $tax += ($price * $product_tax->tax) / 100;
      } elseif ($product_tax->tax_type == 'amount') {
        $tax += $product_tax->tax;
      }
    }
    // $price += $tax;
    
    return $formatted ? price_less_than_50($price) : $price;
  }
}

//Shows Base Price with discount per carton
if (!function_exists('home_discounted_base_carton_price')) {
  function home_discounted_base_carton_price($product, $formatted = true) {
    $product_stocks = ProductWarehouse::where('product_id', $product->id)->get();
    $product        = $product->load('category:id,markup', 'brand:id,markup');
    $price          = $markup          = $wmarkup          = 0;
    if ($product->brand->markup) {
      $markup = $product->brand->markup;
    } else if ($product->category->markup) {
      $markup = $product->category->markup;
    }
    $user_warehouse_id = (Auth::check() && Auth::user()->user_type == 'customer') ? Auth::user()->warehouse_id : null;
    if ($user_warehouse_id) {
      $same_warehouse_stock = $product_stocks->where('warehouse_id', $user_warehouse_id)->first();
      if ($same_warehouse_stock) {
        $price = $same_warehouse_stock->carton_price;
      } else {
        $warehouse = Warehouse::find($user_warehouse_id);
        foreach ($warehouse->markup as $wamarkup) {
          $p_stock = $product_stocks->where('warehouse_id', $wamarkup['warehouse_id'])->first();
          $wmarkup += $wamarkup['markup'];
          if ($p_stock) {
            $price = $p_stock->carton_price;
            break;
          }
        }
      }
    } else {
      $price = $product_stocks->min('carton_price');
    }
    $price += $price * ($markup + $wmarkup) / 100;
    $tax                 = 0;
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
    foreach ($product->taxes as $product_tax) {
      if ($product_tax->tax_type == 'percent') {
        $tax += ($price * $product_tax->tax) / 100;
      } elseif ($product_tax->tax_type == 'amount') {
        $tax += $product_tax->tax;
      }
    }
    $price += $tax;
    return $formatted ? price_less_than_50($price) : $price;
  }
}

if (!function_exists('renderStarRating')) {
  function renderStarRating($rating, $maxRating = 5) {
    $fullStar  = "<i class = 'las la-star active'></i>";
    $halfStar  = "<i class = 'las la-star half'></i>";
    $emptyStar = "<i class = 'las la-star'></i>";
    $rating    = $rating <= $maxRating ? $rating : $maxRating;

    $fullStarCount  = (int) $rating;
    $halfStarCount  = ceil($rating) - $fullStarCount;
    $emptyStarCount = $maxRating - $fullStarCount - $halfStarCount;

    $html = str_repeat($fullStar, $fullStarCount);
    $html .= str_repeat($halfStar, $halfStarCount);
    $html .= str_repeat($emptyStar, $emptyStarCount);
    echo $html;
  }
}

function translate($key, $lang = null, $addslashes = false) {
  if ($lang == null) {
    $lang = App::getLocale();
  }

  $lang_key = preg_replace('/[^A-Za-z0-9\_]/', '', str_replace(' ', '_', strtolower($key)));

  $translations_en = Cache::rememberForever('translations-en', function () {
    return Translation::where('lang', 'en')->pluck('lang_value', 'lang_key')->toArray();
  });

  if (!isset($translations_en[$lang_key])) {
    $translation_def             = new Translation;
    $translation_def->lang       = 'en';
    $translation_def->lang_key   = $lang_key;
    $translation_def->lang_value = str_replace(array("\r", "\n", "\r\n"), "", $key);
    $translation_def->save();
    Cache::forget('translations-en');
  }

  // return user session lang
  $translation_locale = Cache::rememberForever("translations-{$lang}", function () use ($lang) {
    return Translation::where('lang', $lang)->pluck('lang_value', 'lang_key')->toArray();
  });
  if (isset($translation_locale[$lang_key])) {
    return $addslashes ? addslashes(trim($translation_locale[$lang_key])) : trim($translation_locale[$lang_key]);
  }

  // return default lang if session lang not found
  $translations_default = Cache::rememberForever('translations-' . env('DEFAULT_LANGUAGE', 'en'), function () {
    return Translation::where('lang', env('DEFAULT_LANGUAGE', 'en'))->pluck('lang_value', 'lang_key')->toArray();
  });
  if (isset($translations_default[$lang_key])) {
    return $addslashes ? addslashes(trim($translations_default[$lang_key])) : trim($translations_default[$lang_key]);
  }

  // fallback to en lang
  if (!isset($translations_en[$lang_key])) {
    return trim($key);
  }
  return $addslashes ? addslashes(trim($translations_en[$lang_key])) : trim($translations_en[$lang_key]);
}

function remove_invalid_charcaters($str) {
  $str = str_ireplace(array("\\"), '', $str);
  return str_ireplace(array('"'), '\"', $str);
}

function getShippingCost($carts, $index, $carrier = '') {
  $shipping_type               = get_setting('shipping_type');
  $admin_products              = array();
  $seller_products             = array();
  $admin_product_total_weight  = 0;
  $admin_product_total_price   = 0;
  $seller_product_total_weight = array();
  $seller_product_total_price  = array();

  $cartItem = $carts[$index];
  $product  = Product::find($cartItem['product_id']);

  if ($product->digital == 1) {
    return 0;
  }

  foreach ($carts as $key => $cart_item) {
    $item_product = Product::find($cart_item['product_id']);
    if ($item_product->added_by == 'admin') {
      array_push($admin_products, $cart_item['product_id']);

      // For carrier wise shipping
      if ($shipping_type == 'carrier_wise_shipping') {
        $admin_product_total_weight += ($item_product->weight * $cart_item['quantity']);
        $admin_product_total_price += (cart_product_price($cart_item, $item_product, false, false) * $cart_item['quantity']);
      }
    } else {
      $product_ids = array();
      $weight      = 0;
      $price       = 0;
      if (isset($seller_products[$item_product->user_id])) {
        $product_ids = $seller_products[$item_product->user_id];

        // For carrier wise shipping
        if ($shipping_type == 'carrier_wise_shipping') {
          $weight += $seller_product_total_weight[$item_product->user_id];
          $price += $seller_product_total_price[$item_product->user_id];
        }
      }

      array_push($product_ids, $cart_item['product_id']);
      $seller_products[$item_product->user_id] = $product_ids;

      // For carrier wise shipping
      if ($shipping_type == 'carrier_wise_shipping') {
        $weight += ($item_product->weight * $cart_item['quantity']);
        $seller_product_total_weight[$item_product->user_id] = $weight;

        $price += (cart_product_price($cart_item, $item_product, false, false) * $cart_item['quantity']);
        $seller_product_total_price[$item_product->user_id] = $price;
      }
    }
  }

  if ($shipping_type == 'flat_rate') {
    return get_setting('flat_rate_shipping_cost') / count($carts);
  } elseif ($shipping_type == 'seller_wise_shipping') {
    if ($product->added_by == 'admin') {
      return get_setting('shipping_cost_admin') / count($admin_products);
    } else {
      return Shop::where('user_id', $product->user_id)->first()->shipping_cost / count($seller_products[$product->user_id]);
    }
  } elseif ($shipping_type == 'area_wise_shipping') {
    $shipping_info = Address::where('id', $carts[0]['address_id'])->first();
    $city          = City::where('id', $shipping_info->city_id)->first();
    if ($city != null) {
      if ($product->added_by == 'admin') {
        return $city->cost / count($admin_products);
      } else {
        return $city->cost / count($seller_products[$product->user_id]);
      }
    }
    return 0;
  } elseif ($shipping_type == 'carrier_wise_shipping') {
    // carrier wise shipping
    $user_zone = Address::where('id', $carts[0]['address_id'])->first()->country->zone_id;
    if ($carrier == null || $user_zone == 0) {
      return 0;
    }

    $carrier = Carrier::find($carrier);
    if ($carrier->carrier_ranges->first()) {
      $carrier_billing_type = $carrier->carrier_ranges->first()->billing_type;
      if ($product->added_by == 'admin') {
        $itemsWeightOrPrice = $carrier_billing_type == 'weight_based' ? $admin_product_total_weight : $admin_product_total_price;
      } else {
        $itemsWeightOrPrice = $carrier_billing_type == 'weight_based' ? $seller_product_total_weight[$product->user_id] : $seller_product_total_price[$product->user_id];
      }
    }

    foreach ($carrier->carrier_ranges as $carrier_range) {
      if ($itemsWeightOrPrice >= $carrier_range->delimiter1 && $itemsWeightOrPrice < $carrier_range->delimiter2) {
        $carrier_price = $carrier_range->carrier_range_prices->where('zone_id', $user_zone)->first()->price;
        return $product->added_by == 'admin' ? ($carrier_price / count($admin_products)) : ($carrier_price / count($seller_products[$product->user_id]));
      }
    }
    return 0;
  } else {
    if ($product->is_quantity_multiplied && ($shipping_type == 'product_wise_shipping')) {
      return $product->shipping_cost * $cartItem['quantity'];
    }
    return $product->shipping_cost;
  }
}

//return carrier wise shipping cost against seller
if (!function_exists('carrier_base_price')) {
  function carrier_base_price($carts, $carrier_id, $owner_id) {
    $shipping = 0;
    foreach ($carts as $key => $cartItem) {
      if ($cartItem->owner_id == $owner_id) {
        $shipping_cost = getShippingCost($carts, $key, $carrier_id);
        $shipping += $shipping_cost;
      }
    }
    return $shipping;
  }
}

//return seller wise carrier list
if (!function_exists('seller_base_carrier_list')) {
  function seller_base_carrier_list($owner_id) {
    $carrier_list = array();
    $carts        = Cart::where('user_id', auth()->user()->id)->get();
    if (count($carts) > 0) {
      $zone          = $carts[0]['address'] ? Country::where('id', $carts[0]['address']['country_id'])->first()->zone_id : null;
      $carrier_query = Carrier::query();
      $carrier_query->whereIn('id', function ($query) use ($zone) {
        $query->select('carrier_id')->from('carrier_range_prices')
          ->where('zone_id', $zone);
      })->orWhere('free_shipping', 1);
      $carrier_list = $carrier_query->active()->get();
    }
    return (new CarrierCollection($carrier_list))->extra($owner_id);
  }
}

function timezones() {
  return Timezones::timezonesToArray();
}

if (!function_exists('app_timezone')) {
  function app_timezone() {
    return config('app.timezone');
  }
}

//return file uploaded via uploader
if (!function_exists('uploaded_asset')) {
  function uploaded_asset($id) {
    if (($asset = \App\Models\Upload::find($id)) != null) {
      return $asset->external_link == null ? my_asset($asset->file_name) : $asset->external_link;
    }
    return static_asset('assets/img/placeholder.jpg');
  }
}
/*
if (!function_exists('my_asset')) {
  function my_asset($path, $secure = null) {
    if (env('FILESYSTEM_DRIVER') == 's3') {
      return Storage::disk('s3')->url($path);
    } else {
      return app('url')->asset('public/' . $path, $secure);
    }
  }
}
*/
if (!function_exists('my_asset')) {
  function my_asset($path, $secure = null) {
    // Use the environment variable 'UPLOADS_BASE_URL' to set the base URL
    $baseUrl = env('UPLOADS_BASE_URL', url('public'));
    
    // Return the full URL by appending the path to the base URL
    return $baseUrl . '/' . $path;
  }
}


if (!function_exists('static_asset')) {
  function static_asset($path, $secure = null) {
    return app('url')->asset('public/' . $path, $secure);
  }
}

if (!function_exists('getBaseURL')) {
  function getBaseURL() {
    $root = '//' . $_SERVER['HTTP_HOST'];
    $root .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);

    return $root;
  }
}

if (!function_exists('getFileBaseURL')) {
  function getFileBaseURL() {
    if (env('FILESYSTEM_DRIVER') == 's3') {
      return env('AWS_URL') . '/';
    } else {
      return getBaseURL() . 'public/';
    }
  }
}

if (!function_exists('isUnique')) {
  function isUnique($email) {
    $user = \App\Models\User::where('email', $email)->first();
    if ($user == null) {
      return '1'; // $user = null means we did not get any match with the email provided by the user inside the database
    } else {
      return '0';
    }
  }
}

if (!function_exists('get_setting')) {
  function get_setting($key, $default = null, $lang = false) {
    $settings = Cache::remember('business_settings', 86400, function () {
      return BusinessSetting::all();
    });
    if ($lang == false) {
      $setting = $settings->where('type', $key)->first();
    } else {
      $setting = $settings->where('type', $key)->where('lang', $lang)->first();
      $setting = !$setting ? $settings->where('type', $key)->first() : $setting;
    }
    return $setting == null ? $default : $setting->value;
  }
}

function hex2rgba($color, $opacity = false) {
  return Colorcodeconverter::convertHexToRgba($color, $opacity);
}

if (!function_exists('isAdmin')) {
  function isAdmin() {
    if (Auth::check() && (Auth::user()->user_type == 'admin' || Auth::user()->user_type == 'staff')) {
      return true;
    }
    return false;
  }
}

if (!function_exists('isSeller')) {
  function isSeller() {
    if (Auth::check() && Auth::user()->user_type == 'seller') {
      return true;
    }
    return false;
  }
}

if (!function_exists('isCustomer')) {
  function isCustomer() {
    if (Auth::check() && Auth::user()->user_type == 'customer') {
      return true;
    }
    return false;
  }
}

if (!function_exists('formatBytes')) {
  function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow   = min($pow, count($units) - 1);
    // Uncomment one of the following alternatives
    $bytes /= pow(1024, $pow);
    // $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
  }
}

// duplicates m$ excel's ceiling function
if (!function_exists('ceiling')) {
  function ceiling($number, $significance = 1) {
    return (is_numeric($number) && is_numeric($significance)) ? (ceil($number / $significance) * $significance) : false;
  }
}

//for api
if (!function_exists('get_images_path')) {
  function get_images_path($given_ids, $with_trashed = false) {
    $paths = [];
    foreach (explode(',', $given_ids) as $id) {
      $paths[] = uploaded_asset($id);
    }
    return $paths;
  }
}

//for api
if (!function_exists('checkout_done')) {
  function checkout_done($combined_order_id, $payment) {
    $combined_order = CombinedOrder::find($combined_order_id);
    foreach ($combined_order->orders as $key => $order) {
      $order->payment_status  = 'paid';
      $order->payment_details = $payment;
      $order->save();
      try {
        NotificationUtility::sendOrderPlacedNotification($order);
        calculateCommissionAffilationClubPoint($order);
      } catch (\Exception $e) {
      }
    }
  }
}

//for api
if (!function_exists('wallet_payment_done')) {
  function wallet_payment_done($user_id, $amount, $payment_method, $payment_details) {
    $user          = \App\Models\User::find($user_id);
    $user->balance = $user->balance + $amount;
    $user->save();
    $wallet                  = new Wallet;
    $wallet->user_id         = $user->id;
    $wallet->amount          = $amount;
    $wallet->payment_method  = $payment_method;
    $wallet->payment_details = $payment_details;
    $wallet->save();
  }
}

if (!function_exists('purchase_payment_done')) {
  function purchase_payment_done($user_id, $package_id) {
    $user                      = User::findOrFail($user_id);
    $user->customer_package_id = $package_id;
    $customer_package          = CustomerPackage::findOrFail($package_id);
    $user->remaining_uploads += $customer_package->product_upload;
    $user->save();
    return 'success';
  }
}

if (!function_exists('seller_purchase_payment_done')) {
  function seller_purchase_payment_done($user_id, $seller_package_id, $amount, $payment_method, $payment_details) {
    $seller                       = Shop::where('user_id', $user_id)->first();
    $seller->seller_package_id    = $seller_package_id;
    $seller_package               = SellerPackage::findOrFail($seller_package_id);
    $seller->product_upload_limit = $seller_package->product_upload_limit;
    $seller->package_invalid_at   = date('Y-m-d', strtotime($seller->package_invalid_at . ' +' . $seller_package->duration . 'days'));
    $seller->save();
    $seller_package                    = new SellerPackagePayment();
    $seller_package->user_id           = $user_id;
    $seller_package->seller_package_id = $seller_package_id;
    $seller_package->payment_method    = $payment_method;
    $seller_package->payment_details   = $payment_details;
    $seller_package->approval          = 1;
    $seller_package->offline_payment   = 2;
    $seller_package->save();
  }
}

if (!function_exists('customer_purchase_payment_done')) {
  function customer_purchase_payment_done($user_id, $customer_package_id) {
    $user                      = User::findOrFail($user_id);
    $user->customer_package_id = $customer_package_id;
    $customer_package          = CustomerPackage::findOrFail($customer_package_id);
    $user->remaining_uploads += $customer_package->product_upload;
    $user->save();
  }
}

if (!function_exists('product_restock')) {
  function product_restock($orderDetail) {
    $variant = $orderDetail->variation;
    if ($orderDetail->variation == null) {
      $variant = '';
    }
    $product_stock = ProductWarehouse::where('product_id', $orderDetail->product_id)
      ->where('variant', $variant)
      ->first();
    if ($product_stock != null) {
      $product_stock->qty += $orderDetail->quantity;
      $product_stock->save();
    }
  }
}

//Commission Calculation
if (!function_exists('calculateCommissionAffilationClubPoint')) {
  function calculateCommissionAffilationClubPoint($order) {
    (new CommissionController)->calculateCommission($order);
    if (addon_is_activated('affiliate_system')) {
      (new AffiliateController)->processAffiliatePoints($order);
    }
    if (addon_is_activated('club_point')) {
      if ($order->user != null) {
        (new ClubPointController)->processClubPoints($order);
      }
    }
    $order->commission_calculated = 1;
    $order->save();
  }
}

// Addon Activation Check
if (!function_exists('addon_is_activated')) {
  function addon_is_activated($identifier, $default = null) {
    $addons = Cache::remember('addons', 86400, function () {
      return Addon::all();
    });
    $activation = $addons->where('unique_identifier', $identifier)->where('activated', 1)->first();
    return $activation == null ? false : true;
  }
}

// Addon Activation Check
if (!function_exists('seller_package_validity_check')) {
  function seller_package_validity_check($user_id = null) {
    $user               = $user_id == null ? \App\Models\User::find(Auth::user()->id) : \App\Models\User::find($user_id);
    $shop               = $user->shop;
    $package_validation = false;
    if (
      $shop->product_upload_limit > $shop->user->products()->count()
      && $shop->package_invalid_at != null
      && Carbon::now()->diffInDays(Carbon::parse($shop->package_invalid_at), false) >= 0
    ) {
      $package_validation = true;
    }
    return $package_validation;
    // Ture = Seller package is valid and seller has the product upload limit
    // False = Seller package is invalid or seller product upload limit exists.
  }
}

// Get URL params
if (!function_exists('get_url_params')) {
  function get_url_params($url, $key) {
    $query_str = parse_url($url, PHP_URL_QUERY);
    parse_str($query_str, $query_params);
    return $query_params[$key] ?? '';
  }
}

if (!function_exists('format_price_in_rs')) {
  function format_price_in_rs($price, $isMinimize = false) {
      $price = is_numeric($price) ? (float)$price : 0.0;
      $formatted_price = $price;
      // Minimize the price if required
      if ($isMinimize) {
          $temp = number_format($price / 1000000000, 2, ".", "");

          if ($temp >= 1) {
              $formatted_price = $temp . "B";
          } else {
              $temp = number_format($price / 1000000, 2, ".", "");
              if ($temp >= 1) {
                  $formatted_price = $temp . "M";
              }
          }
      }
      // $truncated = floor($formatted_price * 100) / 100;
      // $decimal_part = $truncated - floor($truncated);
      // if ($decimal_part > 0 && $decimal_part < 0.1) {
      //     $truncated = floor($truncated); // Set decimal part to 0
      // }
      // $formatted_price = number_format($truncated, 2, '.', '');


      // Round the price based on the custom rule
      $integer_part = floor($formatted_price); // Get the integer part
      $decimal_part = $formatted_price - $integer_part; // Get the decimal part

      if ($decimal_part < 0.05) {
          $rounded_price = $integer_part; // Round down to the integer
      } else {
          $rounded_price = round($formatted_price, 1); // Round to the nearest tenth
      }

      // Format the rounded price to one decimal place
      $formatted_price = number_format($rounded_price, 1, '.', '');

      return '₹ ' . $formatted_price;
  }
}



if (!function_exists('numberToWords')) {
    /**
     * Convert a number to words
     *
     * @param int $number
     * @return string
     */
    function numberToWords($number)
    {
        $words = [
            0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
            5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
            14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
            18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
            30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
            70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
        ];

        $digits = ['', 'Hundred', 'Thousand', 'Lakh', 'Crore'];

        if (!is_numeric($number)) {
            return 'Invalid Input';
        }

        $number = (int) $number;

        if ($number == 0) {
            return 'Zero Only';
        }

        $str = [];
        $i = 0;

        while ($number > 0) {
            $divider = ($i == 1) ? 10 : 100;
            $modulo = $number % $divider;
            $number = (int)($number / $divider);

            if ($modulo) {
                $strText = '';
                if ($modulo < 21) {
                    $strText = $words[$modulo];
                } else {
                    $strText = $words[(int)($modulo / 10) * 10] . ' ' . $words[$modulo % 10];
                }

                $str[] = $strText . ' ' . $digits[$i];
            }

            $i++;
        }

        return trim(implode(' ', array_reverse($str)));
    }
}




