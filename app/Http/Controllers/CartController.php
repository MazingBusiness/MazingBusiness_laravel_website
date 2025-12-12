<?php

namespace App\Http\Controllers;

use App\Http\Resources\V2\ProductDetailCollection;
use App\Models\Cart;
use App\Models\CartSaveForLater;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Address;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Str;
use App\Models\SubOrder;
use App\Models\SubOrderDetail;

use App\Models\OfferProduct;
use App\Models\OfferCombination;
use App\Models\AttributeValue;
use App\Models\Attribute;
use Auth;
use PDF;
use Cookie;
use Illuminate\Http\Request;
use Session;
use Illuminate\Support\Facades\DB;
use App\Services\WhatsAppWebService;
use App\Exports\AbandonedCartExport;
use App\Imports\ExternalPurchaseOrder;
use App\Exports\FinalPurchaseExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\StatementCalculationService;
use Carbon\Carbon;
use App\Services\PdfContentService;


class CartController extends Controller {
    protected $WhatsAppWebService;
    protected $statementCalculationService;

    public function __construct(StatementCalculationService $statementCalculationService)
    {
        $this->statementCalculationService = $statementCalculationService;
    }

    public function index(Request $request) {
        if (auth()->user() != null) {
        $user_id = Auth::user()->id;
        if ($request->session()->get('temp_user_id')) {
            Cart::where('temp_user_id', $request->session()->get('temp_user_id'))
            ->update(
                [
                'user_id'      => $user_id,
                'temp_user_id' => null,
                ]
            );

            Session::forget('temp_user_id');
        }
        $carts = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
        } else {
            $temp_user_id = $request->session()->get('temp_user_id');
            // $carts = Cart::where('temp_user_id', $temp_user_id)->get();
            $carts = ($temp_user_id != null) ? Cart::where('temp_user_id', $temp_user_id)->get() : [];
        }

        return view('frontend.view_cart', compact('carts'));
    }

    public function cart_v02(Request $request) {
        session()->forget('overdueAmount');
        session()->forget('dueAmount');
        $overdueAmount = 0;
        if (auth()->user() != null) {
            $user_id = Auth::user()->id;
            if ($request->session()->get('temp_user_id')) {
                Cart::where('temp_user_id', $request->session()->get('temp_user_id'))
                ->update(
                    [
                    'user_id'      => $user_id,
                    'temp_user_id' => null,
                    ]
                );
                Session::forget('temp_user_id');
            }
            $carts = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
            $cartSaveForLater = CartSaveForLater::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
            $cartSaveForLaterCategory = CartSaveForLater::with('category') ->where('user_id', $user_id) ->orWhere('customer_id', $user_id) ->selectRaw('category_id, COUNT(*) as product_count') ->groupBy('category_id')  ->get()
            ->map(function ($item) {
                return [
                    'category_id' => $item->category->id,
                    'category_name' => $item->category->name,
                    'product_count' => $item->product_count,
                ];
            });
            // --------------------------------- Calculate Due and Overdue amount --------------------------------------
            $currentDate = date('Y-m-d');
            $currentMonth = date('m');
            $currentYear = date('Y');
            $overdueDateFrom="";
            $overdueAmount="0";

            $openingBalance="0";
            $drBalance = 0;
            $crBalance = 0;
            $dueAmount = 0;

            $userData = User::where('id', $user_id)->first();
            $userAddressData = Address::where('acc_code',"!=","")->where('user_id',$userData->id)->groupBy('gstin')->orderBy('acc_code','ASC')->get();
            foreach($userAddressData as $key=>$value){
                $party_code = $value->acc_code;
                if ($currentMonth >= 4) {
                    $fy_form_date = date('Y-04-01'); // Start of financial year
                    $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
                } else {
                    $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
                    $fy_to_date = date('Y-03-31'); // Current year March
                }
                $from_date = $fy_form_date;
                $to_date = $fy_to_date;
                $headers = [
                    'authtoken' => '65d448afc6f6b',
                ];
                $body = [
                    'party_code' => $party_code,
                    'from_date' => $from_date,
                    'to_date' =>  $to_date,
                ];
				//echo "Hello"; die;
                $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
                \Log::info('Received response from Salzing API For Sync Statement Overdue Calculation', [
                    'status' => $response->status(),
                    'party_code' =>  $party_code,
                    'body' => $response->body()
                ]);
				/*$context = stream_context_create([
					'http' => [
						'method' => 'POST',
						'header' => "Content-Type: application/json\r\n" .
									"Authorization: Bearer your_token_here\r\n",
						'content' => json_encode($body),
						'timeout' => 10
					]
				]);
				$result = file_get_contents('https://saleszing.co.in/itaapi/getclientstatement.php', false, $context);
				dd($result);*/
				// echo "<pre>"; print_r($response); die;
                if ($response->successful()) {
                    $getData = $response->json();
                    if(!empty($getData) AND isset($getData['data']) AND !empty($getData['data'])){
                        $getData = $getData['data'];
                        $closingBalanceResult = array_filter($getData, function ($entry) {
                            return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
                        });
                        $closingEntry = reset($closingBalanceResult);
                        $cloasingDrAmount = $closingEntry['dramount'];
                        $cloasingCrAmount = $closingEntry['cramount'];
                        $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
                        if($cloasingCrAmount > 0){
                            $drBalanceBeforeOVDate = 0;
                            $crBalanceBeforeOVDate = 0;
                            $getData = array_reverse($getData);
                            foreach($getData as $ovKey=>$gValue){
                                if($gValue['ledgername'] != 'closing C/f...'){
                                    if(strtotime($gValue['trn_date']) > strtotime($overdueDateFrom)){
                                        // $drBalanceBeforeOVDate += $ovValue['dramount'];
                                        $crBalanceBeforeOVDate += $gValue['cramount'];
                                    }else{
                                        $drBalanceBeforeOVDate += $gValue['dramount'];
                                        $crBalanceBeforeOVDate += $gValue['cramount'];
                                    }
                                }
                                if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                                    $drBalance = $drBalance + $gValue['dramount'];
                                    $dueAmount = $dueAmount + $gValue['dramount'];
                                } 
                                if($gValue['cramount'] != '0.00' AND $gValue['ledgername'] != 'closing C/f...') {
                                    $crBalance = $crBalance + $gValue['cramount'];
                                    $dueAmount = $dueAmount - $gValue['cramount'];
                                }
                            }
                            $overdueAmount = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                        }
                    }
                }
            }
            if($overdueAmount <= 0){
                $overdueAmount = 0;
            }
            // $overdueAmount = ceil($overdueAmount);
            // $dueAmount = ceil($dueAmount);
            $overdueAmount = $overdueAmount;
            $dueAmount = $dueAmount;
            session(['overdueAmount' => $overdueAmount]);
            session(['dueAmount' => $dueAmount]);
            // --------------------------------- Calculate Due and Overdue amount --------------------------------------
            
        } else {
            $temp_user_id = $request->session()->get('temp_user_id');
            // $carts = Cart::where('temp_user_id', $temp_user_id)->get();
            $carts = ($temp_user_id != null) ? Cart::where('temp_user_id', $temp_user_id)->get() : [];
        }
        $cash_and_carry_item_flag_for_later = CartSaveForLater::where('cash_and_carry_item', '1')->where('user_id', $user_id)->orWhere('customer_id',$user_id)->count();
        return view('frontend.view_cart_v02', compact('carts','cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later','overdueAmount','dueAmount'));
    }


    public function manager41ViewCart(Request $request)
{
    // reset session amounts
    session()->forget('overdueAmount');
    session()->forget('dueAmount');

    $overdueAmount = 0;
    $dueAmount     = 0;
    $validOffers   = [];
    $achiveOfferArray = [];

    if (!auth()->check()) {
        return redirect()->route('home');
    }

    $user_id = Auth::id();

    // attach temp cart (if any) to the logged-in user
    if ($request->session()->get('temp_user_id')) {
        Cart::where('temp_user_id', $request->session()->get('temp_user_id'))
            ->update([
                'user_id'      => $user_id,
                'temp_user_id' => null,
            ]);
        Session::forget('temp_user_id');
    }

    // ============ LIVE CART (Manager-41 only) ============
    $carts = Cart::where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)
                  ->orWhere('customer_id', $user_id);
            })
            ->where('is_manager_41', 1)
            ->orderByRaw("CASE WHEN applied_offer_id IS NOT NULL AND applied_offer_id <> '' THEN 0 ELSE 1 END")
            ->get();

    // ============ SAVE FOR LATER (Manager-41 only) ============
    $cartSaveForLater = CartSaveForLater::where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)
                  ->orWhere('customer_id', $user_id);
            })
            ->where('is_manager_41', 1)   // <<< important
            ->get();

    // category chips for Save-for-Later (Manager-41 only)
    $cartSaveForLaterCategory = CartSaveForLater::with('category')
        ->where(function ($q) use ($user_id) {
            $q->where('user_id', $user_id)
              ->orWhere('customer_id', $user_id);
        })
        ->where('is_manager_41', 1)       // <<< important
        ->selectRaw('category_id, COUNT(*) as product_count')
        ->groupBy('category_id')
        ->get()
        ->map(function ($item) {
            return [
                'category_id'   => optional($item->category)->id,
                'category_name' => optional($item->category)->name,
                'product_count' => $item->product_count,
            ];
        });

    // statement calc (due/overdue)
    $calculationResponse = $this->statementCalculationService->calculateForOneCompany($user_id, 'live');
    $calc = $calculationResponse->getData(true);

    $overdueAmount = $calc['overdueAmount'] ?? 0;
    $dueAmount     = $calc['dueAmount'] ?? 0;

    session(['overdueAmount' => $overdueAmount]);
    session(['dueAmount' => $dueAmount]);

    // count of "No Credit" items in Save-for-Later (Manager-41 only)
    $cash_and_carry_item_flag_for_later = CartSaveForLater::where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)
                  ->orWhere('customer_id', $user_id);
            })
            ->where('is_manager_41', 1)   // <<< important
            ->where('cash_and_carry_item', '1')
            ->count();

    // ===== Optional offer section for user id 24185 =====
    // if ($user_id == 24185) {
        $carts = $this->addOfferTag($carts);
        $validOffersTemp  = $this->checkValidOffer();
        $activeOffersTemp = $this->allActiveOffer();

        $validOffers       = $activeOffersTemp['offers'] ?? [];
        $offers            = $this->processProducts($validOffers); // if you use it in view
        $achiveOfferArray  = $validOffersTemp['achiveOfferArray'] ?? [];
    // }

    return view('frontend.view_cart_41', compact(
        'carts',
        'cartSaveForLater',
        'cartSaveForLaterCategory',
        'cash_and_carry_item_flag_for_later',
        'overdueAmount',
        'dueAmount',
        'validOffers',
        'achiveOfferArray'
    ));
}


    public function cart_v03(Request $request) {


       if ($this->isActingAs41Manager()) { 
        return $this->manager41ViewCart($request);
       }
       
        session()->forget('overdueAmount');
        session()->forget('dueAmount');
        $overdueAmount = 0;
        $validOffers = array();
        $achiveOfferArray = array();
        if (auth()->user() != null) {
            $user_id = Auth::user()->id;
            if ($request->session()->get('temp_user_id')) {
                Cart::where('temp_user_id', $request->session()->get('temp_user_id'))
                ->update(
                    [
                    'user_id'      => $user_id,
                    'temp_user_id' => null,
                    ]
                );
                Session::forget('temp_user_id');
            }
            // $carts = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
            $carts = Cart::where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)
                ->orWhere('customer_id', $user_id);
            })
            ->where('is_manager_41', 0)
            ->orderByRaw("CASE WHEN applied_offer_id IS NOT NULL AND applied_offer_id <> '' THEN 0 ELSE 1 END")
            ->get();
            
            //$cartSaveForLater = CartSaveForLater::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();

            $cartSaveForLater = CartSaveForLater::where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)
                  ->orWhere('customer_id', $user_id);
            })
            ->where('is_manager_41', 0)   // <<< only NON-41 items
            ->get();

            // $cartSaveForLaterCategory = CartSaveForLater::with('category') ->where('user_id', $user_id) ->orWhere('customer_id', $user_id) ->selectRaw('category_id, COUNT(*) as product_count') ->groupBy('category_id')  ->get()
            // ->map(function ($item) {
            //     return [
            //         'category_id' => $item->category->id,
            //         'category_name' => $item->category->name,
            //         'product_count' => $item->product_count,
            //     ];
            // });

            $cartSaveForLaterCategory = CartSaveForLater::with('category')
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)
                      ->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)   // <<< only NON-41 items
                ->selectRaw('category_id, COUNT(*) as product_count')
                ->groupBy('category_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'category_id'   => optional($item->category)->id,
                        'category_name' => optional($item->category)->name,
                        'product_count' => $item->product_count,
                    ];
                });


            $calculationResponse = $this->statementCalculationService->calculateForOneCompany(Auth::user()->id, 'live');
			//echo "<pre>"; print_r($calculationResponse);die;
            // Decode the JSON response to an array
            $calculationResponse = $calculationResponse->getData(true);

            $overdueAmount = $calculationResponse['overdueAmount'];
            $dueAmount = $calculationResponse['dueAmount'];
            session(['overdueAmount' => $overdueAmount]);
            session(['dueAmount' => $dueAmount]);
            // --------------------------------- Calculate Due and Overdue amount --------------------------------------

            // $cash_and_carry_item_flag_for_later = CartSaveForLater::where('cash_and_carry_item', '1')->where('user_id', $user_id)->orWhere('customer_id',$user_id)->count();

            $cash_and_carry_item_flag_for_later = CartSaveForLater::where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)
                  ->orWhere('customer_id', $user_id);
            })
            ->where('is_manager_41', 0)     // <<< only NON-41 items
            ->where('cash_and_carry_item', '1')
            ->count();

            
        } else {
            return redirect()->route('home');
            // $temp_user_id = $request->session()->get('temp_user_id');
            // // $carts = Cart::where('temp_user_id', $temp_user_id)->get();
            // $carts = ($temp_user_id != null) ? Cart::where('temp_user_id', $temp_user_id)->get() : [];
        }        

        // Offer Section
        // if(Auth::user()->id == '24185'){
            $carts = $this->addOfferTag($carts);
            $validOffersTemp = $this->checkValidOffer();
            // $validOffers = $validOffersTemp['offers'] ?? [];
            // echo "<pre>"; print_r($validOffers);die;
            $activeOffersTemp = $this->allActiveOffer();
            $validOffers = $activeOffersTemp['offers'] ?? [];
            $offers = $this->processProducts($validOffers); 
            $achiveOfferArray = $validOffersTemp['achiveOfferArray'] ?? [];
        // }
        if(Auth::user()->id == '24185'){
            // $carts = Cart::where(function ($q) use ($user_id) {
            //     $q->where('user_id', $user_id)
            //     ->orWhere('customer_id', $user_id);
            // })
            // ->orderByRaw("CASE WHEN applied_offer_id IS NOT NULL AND applied_offer_id <> '' THEN 0 ELSE 1 END")
            // ->get();
            // echo "<pre>"; print_r($validOffers); die;
        }
        return view('frontend.view_cart_v02', compact('carts','cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later','overdueAmount','dueAmount','validOffers','achiveOfferArray'));
    }

    public function offersFragment()
    {
        // Re-run your latest offer-building logic here:
        // - compute $validOffers (or the new $payload you built earlier)
        // - determine $applied_offer_id
        // Return JUST the HTML partial.

        $total = 0;
        $cash_and_carry_item_flag = 0;
        $cash_and_carry_item_subtotal = 0;
        $normal_item_flag = 0;
        $normal_item_subtotal = 0;
        $applied_offer_id = 0;
        $offer_rewards = 0;
        $temp_carts = Cart::where('user_id', Auth::user()->id)->orWhere('customer_id',Auth::user()->id)->get();
        foreach($temp_carts as $key => $value){
            if($value['cash_and_carry_item'] == 1 && Auth::check() && Auth::user()->credit_days > 0){
                $cash_and_carry_item_flag = 1;
                $cash_and_carry_item_subtotal += $value['price'] * $value['quantity'];
            }else{
                $normal_item_flag = 1;
                $normal_item_subtotal += $value['price'] * $value['quantity'];
                $total += $value['price'] * $value['quantity'];
            }
            if($value['applied_offer_id'] != NULL OR $value['applied_offer_id']!= ""){
                $applied_offer_id = $value['applied_offer_id'];
            }
            $offer_rewards = ($offer_rewards == 0) ? $value['offer_rewards'] : $offer_rewards;
        }

        $activeOffersTemp = $this->allActiveOffer();
        $validOffers = $activeOffersTemp['offers'] ?? [];
        $offers = $this->processProducts($validOffers);

        // [$validOffers, $applied_offer_id] = $this->computeValidOffersForUser(); // <— wrap your existing logic

        return view('frontend.partials.offers', compact('validOffers', 'applied_offer_id'));
    }

    

    public function manager41UpdateCartPrice(Request $request){
        try {
            $user_id      = Auth::id();
            $cart_id      = $request->input('cart_id');
            $update_price = (float) $request->input('update_price', 0);
    
            // Update only if the row belongs to this user and is a Manager-41 item
            $cartItem = Cart::where('id', $cart_id)
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)
                      ->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->firstOrFail();
    
            $cartItem->price = $update_price;
            $cartItem->save();
    
            // Keep using the same session amounts you already store
            $overdueAmount = session('overdueAmount');
            $dueAmount     = session('dueAmount');
    
            // Rebuild ONLY the Manager-41 slice
            $carts = Cart::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)
                      ->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->get();
    
            // If you make a 41-specific view, we'll use it; otherwise fall back to the existing one
            $viewName = view()->exists('frontend.manager41UpdateCartPrice')
                ? 'frontend.manager41UpdateCartPrice'
                : 'frontend.updateCartPrice';
    
            $view = view($viewName, compact('carts', 'dueAmount', 'overdueAmount'))->render();
    
            return response()->json([
                'html'          => $view,
                'nav_cart_view' => view('frontend.partials.cart')->render(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::warning('manager41UpdateCartPrice: item not found / not Manager-41 / not owned by user.');
            return response()->json([
                'status'  => 'Error',
                'message' => 'Item not found.',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('manager41UpdateCartPrice error: '.$e->getMessage());
            return response()->json([
                'status'  => 'Error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateCartPrice(Request $request) {

        if ($this->isActingAs41Manager()) {
            return $this->manager41UpdateCartPrice($request);
        }

        try {
            $user_id = Auth::user()->id;        
            $cart_id = $request->has('cart_id')? $request->cart_id : '';
            $update_price = $request->has('update_price')? $request->update_price : '0';

            $cartItem = Cart::findOrFail($cart_id);        
            $cartItem['price'] = $update_price;
            $cartItem->save();

            $overdueAmount = session('overdueAmount');
            $dueAmount = session('dueAmount');
            
            $carts = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
            $view = view('frontend.updateCartPrice', compact('carts','dueAmount','overdueAmount'))->render();
            return response()->json([
                'html' => $view,
                'nav_cart_view' => view('frontend.partials.cart')->render()
            ]);

        } catch (\Exception $e) {
            // Log any other exceptions
            \Log::error('An error occurred: ' . $e->getMessage());
            return response()->json([
                'status' => 'Error',
                'message' => 'An unexpected error occurred.',
            ], 500);
        }

    }


    public function manager41SaveForLater(Request $request)
    {
        try {
            $validOffers = [];
            $achiveOfferArray = [];
            $user_id = Auth::id();
            $cart_id = $request->has('cart_id') ? $request->cart_id : null;

            if (!$cart_id) {
                return response()->json(['status' => 'Error', 'message' => 'Missing cart_id.'], 422);
            }

            $cartItem = Cart::with('product')
                ->where('id', $cart_id)
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1) // 41-only
                ->first();

            if (!$cartItem) {
                return response()->json(['status' => 'Error', 'message' => 'Cart item not found.'], 404);
            }

            // ----- Remove offer (limit to 41 items) -----
            $offer_id = $cartItem->applied_offer_id;
            if (!empty($offer_id)) {
                $currentDate = now();
                $offers = Offer::with('offerProducts')
                    ->where('status', 1)
                    ->whereDate('offer_validity_start', '<=', $currentDate)
                    ->whereDate('offer_validity_end', '>=', $currentDate)
                    ->where('id', $offer_id)
                    ->first();

                if ($offers) {
                    $offerProducts = $offers->offerProducts ?? collect();
                    foreach ($offerProducts as $opValue) {
                        $cartProduct = Cart::where(function ($query) use ($user_id) {
                                $query->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                            })
                            ->where('is_manager_41', 1) // 41-only
                            ->where('product_id', $opValue->product_id)
                            ->first();

                        if ($cartProduct) {
                            $product = Product::find($opValue->product_id);
                            if ($product) {
                                $discount = Auth::user()->discount ?? 0;
                                $price = $product->mrp * ((100 - $discount) / 100);
                                $cartProduct->applied_offer_id = null;
                                $cartProduct->price = $price;
                                $cartProduct->save();
                            }
                        }
                    }

                    // delete complementary items of this offer (41-only)
                    Cart::where(function ($query) use ($user_id) {
                            $query->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                        })
                        ->where('is_manager_41', 1)
                        ->where('applied_offer_id', $offer_id)
                        ->where('complementary_item', '1')
                        ->delete();
                }
            }

            // ----- Save For Later (41) -----
            $dup = CartSaveForLater::where(function ($q) use ($user_id, $cartItem) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('product_id', $cartItem->product_id)
                    ->where('is_manager_41', 1)
                    ->exists();

            if (!$dup) {
                $payload = $cartItem->toArray();
                // enrich
                $payload['group_id']      = $cartItem->product->group_id    ?? null;
                $payload['category_id']   = $cartItem->product->category_id ?? null;
                $payload['brand_id']      = $cartItem->product->brand_id    ?? null;
                $payload['is_manager_41'] = 1; // ensure 41

                unset($payload['id'], $payload['created_at'], $payload['updated_at']);
                CartSaveForLater::create($payload);
            }

            $cartItem->delete();

            // ----- Rebuild 41 collections for partials -----
            $carts = Cart::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 1)
                    ->get();

            $cartSaveForLater = CartSaveForLater::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 1)
                    ->get();

            $cartSaveForLaterCategory = CartSaveForLater::with('category')
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->selectRaw('category_id, COUNT(*) as product_count')
                ->groupBy('category_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'category_id'   => optional($item->category)->id,
                        'category_name' => optional($item->category)->name,
                        'product_count' => $item->product_count,
                    ];
                });

            $overdueAmount = $request->overdueAmount;
            $dueAmount     = $request->dueAmount;

            $cash_and_carry_item_flag_for_later = CartSaveForLater::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 1)
                    ->where('cash_and_carry_item', '1')
                    ->count();

            // Offer tags for a specific user (kept as-is)
            if (Auth::id() == 24185) {
                $carts = $this->addOfferTag($carts);
                $validOffersTemp = $this->checkValidOffer();
                $validOffers = $validOffersTemp['offers'] ?? [];
                $achiveOfferArray = $validOffersTemp['achiveOfferArray'] ?? [];
            }

            return response()->json([
                'html'             => view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount','validOffers','achiveOfferArray'))->render(),
                'viewSaveForLater' => view('frontend.partials.cart_save_for_laters_41', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
                'nav_cart_view'    => view('frontend.partials.cart', compact('carts','overdueAmount','dueAmount'))->render(),
            ]);
        } catch (\Exception $e) {
            \Log::error('manager41SaveForLater error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'Error',
                'message' => 'An unexpected error occurred. ' . $e->getMessage(),
            ], 500);
        }
    }


public function saveForLater(Request $request)
{
    // If Manager-41, use the 41-specific flow
    if ($this->isActingAs41Manager()) {
        return $this->manager41SaveForLater($request);
    }

    try {
        $validOffers = [];
        $achiveOfferArray = [];
        $user_id = Auth::id();
        $cart_id = $request->has('cart_id') ? $request->cart_id : null;

        if (!$cart_id) {
            return response()->json(['status' => 'Error', 'message' => 'Missing cart_id.'], 422);
        }

        $cartItem = Cart::with('product')
            ->where('id', $cart_id)
            ->where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
            })
            ->where('is_manager_41', 0) // NON-41 only
            ->first();

        if (!$cartItem) {
            return response()->json(['status' => 'Error', 'message' => 'Cart item not found.'], 404);
        }

        // ----- Remove offer (limit to NON-41 items) -----
        $offer_id = $cartItem->applied_offer_id;
        if (!empty($offer_id)) {
            $currentDate = now();
            $offers = Offer::with('offerProducts')
                ->where('status', 1)
                ->whereDate('offer_validity_start', '<=', $currentDate)
                ->whereDate('offer_validity_end', '>=', $currentDate)
                ->where('id', $offer_id)
                ->first();

            if ($offers) {
                $offerProducts = $offers->offerProducts ?? collect();
                foreach ($offerProducts as $opValue) {
                    $cartProduct = Cart::where(function ($query) use ($user_id) {
                            $query->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                        })
                        ->where('is_manager_41', 0) // NON-41 only
                        ->where('product_id', $opValue->product_id)
                        ->first();

                    if ($cartProduct) {
                        $product = Product::find($opValue->product_id);
                        if ($product) {
                            $discount = Auth::user()->discount ?? 0;
                            $price = $product->mrp * ((100 - $discount) / 100);
                            $cartProduct->applied_offer_id = null;
                            $cartProduct->price = $price;
                            $cartProduct->save();
                        }
                    }
                }

                // delete complementary items of this offer (NON-41 only)
                Cart::where(function ($query) use ($user_id) {
                        $query->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 0)
                    ->where('applied_offer_id', $offer_id)
                    ->where('complementary_item', '1')
                    ->delete();
            }
        }

        // ----- Save For Later (NON-41) -----
        $dup = CartSaveForLater::where(function ($q) use ($user_id, $cartItem) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('product_id', $cartItem->product_id)
                ->where('is_manager_41', 0)
                ->exists();

        if (!$dup) {
            $payload = $cartItem->toArray();
            // enrich
            $payload['group_id']      = $cartItem->product->group_id    ?? null;
            $payload['category_id']   = $cartItem->product->category_id ?? null;
            $payload['brand_id']      = $cartItem->product->brand_id    ?? null;
            $payload['is_manager_41'] = 0; // ensure NON-41

            unset($payload['id'], $payload['created_at'], $payload['updated_at']);
            CartSaveForLater::create($payload);
        }

        $cartItem->delete();

        // ----- Rebuild NON-41 collections for partials -----
        $carts = Cart::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)
                ->get();

        $cartSaveForLater = CartSaveForLater::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)
                ->get();

        $cartSaveForLaterCategory = CartSaveForLater::with('category')
            ->where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
            })
            ->where('is_manager_41', 0)
            ->selectRaw('category_id, COUNT(*) as product_count')
            ->groupBy('category_id')
            ->get()
            ->map(function ($item) {
                return [
                    'category_id'   => optional($item->category)->id,
                    'category_name' => optional($item->category)->name,
                    'product_count' => $item->product_count,
                ];
            });

        $overdueAmount = $request->overdueAmount;
        $dueAmount     = $request->dueAmount;

        $cash_and_carry_item_flag_for_later = CartSaveForLater::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)
                ->where('cash_and_carry_item', '1')
                ->count();

        // Offer tags for a specific user (kept as-is)
        if (Auth::id() == 24185) {
            $carts = $this->addOfferTag($carts);
            $validOffersTemp = $this->checkValidOffer();
            $validOffers = $validOffersTemp['offers'] ?? [];
            $achiveOfferArray = $validOffersTemp['achiveOfferArray'] ?? [];
        }

        return response()->json([
            'html'             => view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount','validOffers','achiveOfferArray'))->render(),
            'viewSaveForLater' => view('frontend.partials.cart_save_for_laters', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
            'nav_cart_view'    => view('frontend.partials.cart', compact('carts','overdueAmount','dueAmount'))->render(),
        ]);
    } catch (\Exception $e) {
        \Log::error('saveForLater error: ' . $e->getMessage());
        return response()->json([
            'status'  => 'Error',
            'message' => 'An unexpected error occurred. ' . $e->getMessage(),
        ], 500);
    }
}

    // public function saveForLater(Request $request) {

    //     // If Manager-41, use the 41-specific flow
    //     if ($this->isActingAs41Manager()) {
    //         return $this->manager41SaveForLater($request);
    //     }
    //     try {
    //         $validOffers = array();
    //         $achiveOfferArray = array();
    //         $user_id = Auth::user()->id;        
    //         $cart_id = $request->has('cart_id')? $request->cart_id : '';

    //         $cartItem = Cart::with('product')->where('id', $cart_id)->first();
    //         // Remove offer
    //         $offer_id = $cartItem->applied_offer_id;
    //         $currentDate = now();
    //         if($offer_id != NULL OR $offer_id != ""){
    //             $currentDate = now();
    //             $offers = Offer::with('offerProducts')
    //                 ->where('status', 1)
    //                 ->whereDate('offer_validity_start', '<=', $currentDate)
    //                 ->whereDate('offer_validity_end', '>=', $currentDate)
    //                 ->where('id', $offer_id)->first();
    //             $offerProducts = $offers->offerProducts;            
    //             foreach($offerProducts as $opKey=>$opValue){
    //                 $cartProduct = Cart::where(function ($query) use ($opValue) {
    //                     $query->where('user_id', Auth::user()->id)
    //                             ->orWhere('customer_id', Auth::user()->id);
    //                 })->where('product_id', $opValue->product_id)->first();
                    
    //                 $product = Product::where('id',$opValue->product_id)->first();
    //                 $price = $product->mrp * ((100 - Auth::user()->discount) / 100);
    //                 $cartProduct->applied_offer_id = null;
    //                 $cartProduct->price = $price;
    //                 $cartProduct->save();
    //             }
    //             $cartProduct = Cart::where(function ($query) use ($opValue) {
    //                 $query->where('user_id', Auth::user()->id)
    //                         ->orWhere('customer_id', Auth::user()->id);
    //             })->where('applied_offer_id', $offer_id)->where('complementary_item', '1')->delete();
    //         }
            
    //         $cartSaveForLaterData = CartSaveForLater::where(function($query) use ($user_id, $cartItem) {
    //             $query->where('user_id', $user_id)
    //                   ->where('product_id', $cartItem->product_id);
    //         })->orWhere('customer_id', $user_id)->get();
           
    //         if (empty($cartSaveForLaterData) || $cartSaveForLaterData->isEmpty()) {
    //             $cartItem->group_id = $cartItem->product->group_id;
    //             $cartItem->category_id = $cartItem->product->category_id;
    //             $cartItem->brand_id = $cartItem->product->brand_id;
    //             CartSaveForLater::create($cartItem->toArray());
    //         }           
            
    //         $cartItem->delete();

    //         $carts = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
           
    //         $cartSaveForLater = CartSaveForLater::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
    //         $cartSaveForLaterCategory = CartSaveForLater::with('category') // Eager load the related category
    //         ->where('user_id', $user_id)
    //         ->orWhere('customer_id', $user_id)
    //         ->selectRaw('category_id, COUNT(*) as product_count') // Select category_id and the product count
    //         ->groupBy('category_id')
    //         ->get()
    //         ->map(function ($item) {
    //             return [
    //                 'category_id' => $item->category->id,
    //                 'category_name' => $item->category->name, // Assuming 'name' is the category's name column
    //                 'product_count' => $item->product_count,
    //             ];
    //         });

    //         $overdueAmount = $request->overdueAmount;
    //         $dueAmount = $request->dueAmount;
    //         $cash_and_carry_item_flag_for_later = CartSaveForLater::where('cash_and_carry_item', '1')->where('user_id', $user_id)->orWhere('customer_id',$user_id)->count();


    //         // Offer Section 
    //         if(Auth::user()->id == '24185'){
    //             $carts = $this->addOfferTag($carts);
    //             $validOffersTemp = $this->checkValidOffer();
    //             $validOffers = $validOffersTemp['offers'] ?? [];
    //             $achiveOfferArray = $validOffersTemp['achiveOfferArray'] ?? [];
    //         }
    //         return response()->json([
    //             'html' =>  view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount','validOffers','achiveOfferArray'))->render(),
    //             'viewSaveForLater' => view('frontend.partials.cart_save_for_laters', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
    //             'nav_cart_view' => view('frontend.partials.cart', compact('carts','overdueAmount','dueAmount'))->render(),
    //         ]);
    //     } catch (\Exception $e) {
    //         // Log any other exceptions
    //         \Log::error('An error occurred: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => 'Error',
    //             'message' => 'An unexpected error occurred.' . $e->getMessage(),
    //         ], 500);
    //     }

    // }

    public function saveAllNoCreditItemForLater(Request $request) {
        try {
            $user_id = Auth::user()->id;        
            
            $cartItem = Cart::with('product')->where('cash_and_carry_item', '1')->where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();

            // echo "<pre>";print_r($cartItem);die();
            if(!$cartItem->isEmpty()){
                foreach($cartItem as $cKey=>$cValue){
                    $cartSaveForLaterData = CartSaveForLater::where(function($query) use ($user_id, $cValue) {
                        $query->where('user_id', $user_id)
                              ->where('product_id', $cValue->product_id);
                    })->orWhere('customer_id', $user_id)->get();
                   
                    if (empty($cartSaveForLaterData) || $cartSaveForLaterData->isEmpty()) {
                        $cValue->group_id = $cValue->product->group_id;
                        $cValue->category_id = $cValue->product->category_id;
                        $cValue->brand_id = $cValue->product->brand_id;
                        CartSaveForLater::create($cValue->toArray());
                    }
                    Cart::where('id',$cValue->id)->delete();
                }
            }

            $carts = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
           
            $cartSaveForLater = CartSaveForLater::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
            $cartSaveForLaterCategory = CartSaveForLater::with('category') // Eager load the related category
            ->where('user_id', $user_id)
            ->orWhere('customer_id', $user_id)
            ->selectRaw('category_id, COUNT(*) as product_count') // Select category_id and the product count
            ->groupBy('category_id')
            ->get()
            ->map(function ($item) {
                return [
                    'category_id' => $item->category->id,
                    'category_name' => $item->category->name, // Assuming 'name' is the category's name column
                    'product_count' => $item->product_count,
                ];
            });

            $overdueAmount = $request->overdueAmount;
            $dueAmount = $request->dueAmount;
            $cash_and_carry_item_flag_for_later = CartSaveForLater::where('cash_and_carry_item', '1')->where('user_id', $user_id)->orWhere('customer_id',$user_id)->count();
            return response()->json([
                'html' =>  view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount'))->render(), 
                'viewSaveForLater' => view('frontend.partials.cart_save_for_laters', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
                'nav_cart_view' => view('frontend.partials.cart')->render(),
            ]);

        } catch (\Exception $e) {
            // Log any other exceptions
            \Log::error('An error occurred: ' . $e->getMessage());
            return response()->json([
                'status' => 'Error',
                'message' => 'An unexpected error occurred.' . $e->getMessage(),
            ], 500);
        }

    }

    public function moveAllNoCreditItemToCart(Request $request) {
        try {
            $user_id = Auth::user()->id;        
            // $id = $request->has('id')? $request->id : '';

            $cartItem = CartSaveForLater::where('cash_and_carry_item', '1')->where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
            $cartItemData = $cartItem->toArray();
            if(!$cartItem->isEmpty()){
                foreach($cartItem as $cKey=>$cValue){
                    $cartItemData = $cValue->toArray();
                    unset($cartItemData['group_id']);
                    unset($cartItemData['category_id']);
                    unset($cartItemData['brand_id']);
                    
                    Cart::create($cartItemData);
                    
                    CartSaveForLater::where('id',$cValue->id)->delete();
                }
            }

            $carts = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
           
            $cartSaveForLater = CartSaveForLater::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
            $cartSaveForLaterCategory = CartSaveForLater::with('category') // Eager load the related category
            ->where('user_id', $user_id)
            ->orWhere('customer_id', $user_id)
            ->selectRaw('category_id, COUNT(*) as product_count') // Select category_id and the product count
            ->groupBy('category_id')
            ->get()
            ->map(function ($item) {
                return [
                    'category_id' => $item->category->id,
                    'category_name' => $item->category->name, // Assuming 'name' is the category's name column
                    'product_count' => $item->product_count,
                ];
            });

            $overdueAmount = $request->overdueAmount;
            $dueAmount = $request->dueAmount;
            $cash_and_carry_item_flag_for_later = CartSaveForLater::where('cash_and_carry_item', '1')->where('user_id', $user_id)->orWhere('customer_id',$user_id)->count();
            return response()->json([
                'html' =>  view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount'))->render(), 
                'viewSaveForLater' => view('frontend.partials.cart_save_for_laters', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
                'nav_cart_view' => view('frontend.partials.cart')->render(),
            ]);

        } catch (\Exception $e) {
            // Log any other exceptions
            \Log::error('An error occurred: ' . $e->getMessage());
            return response()->json([
                'status' => 'Error',
                'message' => 'An unexpected error occurred.' . $e->getMessage(),
            ], 500);
        }

    }

    public function manager41SaveAllCheckedItemForLater(Request $request)
    {

        try {
            $user_id = Auth::id();
            $itemIds = (array) ($request->itemIds ?? []);

            foreach ($itemIds as $id) {
                $cartItem = Cart::with('product')
                    ->where('id', $id)
                    ->where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)
                          ->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 1)     // only 41 items
                    ->first();

                if (!$cartItem) continue;

                // check if 41 item already exists in SaveForLater for this user
                $exists = CartSaveForLater::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)
                          ->orWhere('customer_id', $user_id);
                    })
                    ->where('product_id', $cartItem->product_id)
                    ->where('is_manager_41', 1)
                    ->exists();

                if (!$exists) {
                    $payload = $cartItem->toArray();
                    unset($payload['id'], $payload['created_at'], $payload['updated_at']);
                    $payload['group_id']      = $cartItem->product->group_id    ?? null;
                    $payload['category_id']   = $cartItem->product->category_id ?? null;
                    $payload['brand_id']      = $cartItem->product->brand_id    ?? null;
                    $payload['is_manager_41'] = 1; // mark as 41
                    CartSaveForLater::create($payload);
                }

                // remove from live Manager-41 cart
                $cartItem->delete();
            }

            // rebuild views (Manager-41 slices)
            $carts = Cart::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->get();

            $cartSaveForLater = CartSaveForLater::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->get();

            $cartSaveForLaterCategory = CartSaveForLater::with('category')
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->selectRaw('category_id, COUNT(*) as product_count')
                ->groupBy('category_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'category_id'   => $item->category->id   ?? null,
                        'category_name' => $item->category->name ?? '-',
                        'product_count' => $item->product_count,
                    ];
                });

            $overdueAmount = $request->overdueAmount;
            $dueAmount     = $request->dueAmount;

            $cash_and_carry_item_flag_for_later = CartSaveForLater::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->where('cash_and_carry_item', '1')
                ->count();

            return response()->json([
                'html'               => view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount'))->render(),
                'viewSaveForLater'   => view('frontend.partials.cart_save_for_laters_41', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
                'nav_cart_view'      => view('frontend.partials.cart')->render(),
            ]);

        } catch (\Exception $e) {
            \Log::error('manager41SaveAllCheckedItemForLater error: '.$e->getMessage());
            return response()->json([
                'status'  => 'Error',
                'message' => 'An unexpected error occurred. '.$e->getMessage(),
            ], 500);
        }
    }

    public function saveAllCheckedItemForLater(Request $request)
    {
        // If Manager-41, delegate to the 41-specific handler
        if ($this->isActingAs41Manager()) {
            return $this->manager41SaveAllCheckedItemForLater($request);
        }

        try {
            $user_id = Auth::id();
            $itemIds = (array) ($request->itemIds ?? []);

            foreach ($itemIds as $cartRowId) {
                // Fetch ONLY non-41 cart item for this user
                $cartItem = Cart::with('product')
                    ->where('id', $cartRowId)
                    ->where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)
                          ->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 0)
                    ->first();

                if (!$cartItem) {
                    continue;
                }

                // Check if a non-41 Save-For-Later row already exists for same product
                $exists = CartSaveForLater::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)
                          ->orWhere('customer_id', $user_id);
                    })
                    ->where('product_id', $cartItem->product_id)
                    ->where('is_manager_41', 0)
                    ->exists();

                if (!$exists) {
                    // Prepare payload; include group/category/brand if available
                    $payload = $cartItem->toArray();
                    unset($payload['id'], $payload['created_at'], $payload['updated_at']);

                    $payload['group_id']      = optional($cartItem->product)->group_id;
                    $payload['category_id']   = optional($cartItem->product)->category_id;
                    $payload['brand_id']      = optional($cartItem->product)->brand_id;
                    $payload['is_manager_41'] = 0; // force non-41 slice

                    CartSaveForLater::create($payload);
                }

                // Remove from live cart
                $cartItem->delete();
            }

            // Rebuild ONLY non-41 collections
            $carts = Cart::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)
                      ->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)
                ->get();

            $cartSaveForLater = CartSaveForLater::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)
                      ->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)
                ->get();

            $cartSaveForLaterCategory = CartSaveForLater::with('category')
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)
                      ->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)
                ->selectRaw('category_id, COUNT(*) as product_count')
                ->groupBy('category_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'category_id'   => optional($item->category)->id,
                        'category_name' => optional($item->category)->name,
                        'product_count' => $item->product_count,
                    ];
                });

            $overdueAmount = $request->overdueAmount;
            $dueAmount     = $request->dueAmount;

            $cash_and_carry_item_flag_for_later = CartSaveForLater::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)
                      ->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)
                ->where('cash_and_carry_item', '1')
                ->count();

            return response()->json([
                'html'             => view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount'))->render(),
                'viewSaveForLater' => view('frontend.partials.cart_save_for_laters', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
                'nav_cart_view'    => view('frontend.partials.cart')->render(),
            ]);

        } catch (\Exception $e) {
            \Log::error('saveAllCheckedItemForLater (non-41) error: '.$e->getMessage());
            return response()->json([
                'status'  => 'Error',
                'message' => 'An unexpected error occurred. '.$e->getMessage(),
            ], 500);
        }
    }



    // public function saveAllCheckedItemForLater(Request $request) {

    //     // Simple branch: if Manager-41, delegate to the 41-specific handler.
    //     if ($this->isActingAs41Manager()) {
    //         return $this->manager41SaveAllCheckedItemForLater($request);
    //     }

    //     try {
    //         $user_id = Auth::user()->id;        
            
    //         foreach($request->itemIds as $key=>$value){
    //             $cartItem = Cart::with('product')->where('id', $value)->first();
    //             if($cartItem!==NULL){                    
    //                 $cartSaveForLaterData = CartSaveForLater::where(function($query) use ($user_id, $cartItem) {
    //                     $query->where('user_id', $user_id)
    //                         ->where('product_id', $cartItem->product->product_id);
    //                 })->orWhere('customer_id', $user_id)->get();
                
    //                 if (empty($cartSaveForLaterData) || $cartSaveForLaterData->isEmpty()) {
    //                     $cartItem->group_id = $cartItem->product->group_id;
    //                     $cartItem->category_id = $cartItem->product->category_id;
    //                     $cartItem->brand_id = $cartItem->product->brand_id;
    //                     CartSaveForLater::create($cartItem->toArray());
    //                 }
    //                 Cart::where('id',$cartItem->id)->delete();                    
    //             }
    //         }            

    //         $carts = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
           
    //         $cartSaveForLater = CartSaveForLater::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
    //         $cartSaveForLaterCategory = CartSaveForLater::with('category') // Eager load the related category
    //         ->where('user_id', $user_id)
    //         ->orWhere('customer_id', $user_id)
    //         ->selectRaw('category_id, COUNT(*) as product_count') // Select category_id and the product count
    //         ->groupBy('category_id')
    //         ->get()
    //         ->map(function ($item) {
    //             return [
    //                 'category_id' => $item->category->id,
    //                 'category_name' => $item->category->name, // Assuming 'name' is the category's name column
    //                 'product_count' => $item->product_count,
    //             ];
    //         });

    //         $overdueAmount = $request->overdueAmount;
    //         $dueAmount = $request->dueAmount;
    //         $cash_and_carry_item_flag_for_later = CartSaveForLater::where('cash_and_carry_item', '1')->where('user_id', $user_id)->orWhere('customer_id',$user_id)->count();
    //         return response()->json([
    //             'html' =>  view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount'))->render(), 
    //             'viewSaveForLater' => view('frontend.partials.cart_save_for_laters', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
    //             'nav_cart_view' => view('frontend.partials.cart')->render(),
    //         ]);

    //     } catch (\Exception $e) {
    //         // Log any other exceptions
    //         \Log::error('An error occurred: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => 'Error',
    //             'message' => 'An unexpected error occurred.' . $e->getMessage(),
    //         ], 500);
    //     }

    // }

    public function manager41MoveAllCheckedItemToCart(Request $request)
    {
        try {
            $user_id = Auth::id();
            $itemIds = (array) ($request->itemIds ?? []);

            foreach ($itemIds as $value) {
                $cartItem = CartSaveForLater::where('id', $value)
                    ->where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)
                          ->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 1) // 41-only
                    ->first();

                if (!$cartItem) continue;

                $cartItemData = $cartItem->toArray();
                unset($cartItemData['group_id'], $cartItemData['category_id'], $cartItemData['brand_id'], $cartItemData['id'], $cartItemData['created_at'], $cartItemData['updated_at']);

                // keep it explicitly 41
                $cartItemData['is_manager_41'] = 1;

                Cart::create($cartItemData);
                $cartItem->delete();
            }

            // Rebuild 41 views
            $carts = Cart::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 1)
                    ->get();

            $cartSaveForLater = CartSaveForLater::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 1)
                    ->get();

            $cartSaveForLaterCategory = CartSaveForLater::with('category')
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->selectRaw('category_id, COUNT(*) as product_count')
                ->groupBy('category_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'category_id'   => optional($item->category)->id,
                        'category_name' => optional($item->category)->name,
                        'product_count' => $item->product_count,
                    ];
                });

            $overdueAmount = $request->overdueAmount;
            $dueAmount     = $request->dueAmount;

            $cash_and_carry_item_flag_for_later = CartSaveForLater::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 1)
                    ->where('cash_and_carry_item', '1')
                    ->count();

            return response()->json([
                'html'             => view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount'))->render(),
                'viewSaveForLater' => view('frontend.partials.cart_save_for_laters_41', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
                'nav_cart_view'    => view('frontend.partials.cart')->render(),
            ]);

        } catch (\Exception $e) {
            \Log::error('manager41MoveAllCheckedItemToCart error: '.$e->getMessage());
            return response()->json([
                'status'  => 'Error',
                'message' => 'An unexpected error occurred. '.$e->getMessage(),
            ], 500);
        }
    }


    // public function moveAllCheckedItemToCart(Request $request) {

    //      // If Manager-41, use the 41-specific flow
    //     if ($this->isActingAs41Manager()) {
    //         return $this->manager41MoveAllCheckedItemToCart($request);
    //     }
    //     try {
    //         $user_id = Auth::user()->id;
            
    //         foreach($request->itemIds as $key=>$value){
    //             $cartItem = CartSaveForLater::where('id', $value)->first();
    //             if($cartItem!==NULL){                    
    //                 $cartItemData = $cartItem->toArray();
    //                 unset($cartItemData['group_id']);
    //                 unset($cartItemData['category_id']);
    //                 unset($cartItemData['brand_id']);
                    
    //                 Cart::create($cartItemData);
                    
    //                 CartSaveForLater::where('id',$value)->delete();
    //             }
    //         }

    //         $carts = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
           
    //         $cartSaveForLater = CartSaveForLater::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
    //         $cartSaveForLaterCategory = CartSaveForLater::with('category') // Eager load the related category
    //         ->where('user_id', $user_id)
    //         ->orWhere('customer_id', $user_id)
    //         ->selectRaw('category_id, COUNT(*) as product_count') // Select category_id and the product count
    //         ->groupBy('category_id')
    //         ->get()
    //         ->map(function ($item) {
    //             return [
    //                 'category_id' => $item->category->id,
    //                 'category_name' => $item->category->name, // Assuming 'name' is the category's name column
    //                 'product_count' => $item->product_count,
    //             ];
    //         });

    //         $overdueAmount = $request->overdueAmount;
    //         $dueAmount = $request->dueAmount;
    //         $cash_and_carry_item_flag_for_later = CartSaveForLater::where('cash_and_carry_item', '1')->where('user_id', $user_id)->orWhere('customer_id',$user_id)->count();
    //         return response()->json([
    //             'html' =>  view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount'))->render(), 
    //             'viewSaveForLater' => view('frontend.partials.cart_save_for_laters', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
    //             'nav_cart_view' => view('frontend.partials.cart')->render(),
    //         ]);

    //     } catch (\Exception $e) {
    //         // Log any other exceptions
    //         \Log::error('An error occurred: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => 'Error',
    //             'message' => 'An unexpected error occurred.' . $e->getMessage(),
    //         ], 500);
    //     }

    // }

    public function moveAllCheckedItemToCart(Request $request)
    {
        // If Manager-41, use the 41-specific flow
        if ($this->isActingAs41Manager()) {
            return $this->manager41MoveAllCheckedItemToCart($request);
        }

        try {
            $user_id = Auth::id();
            $itemIds = (array) ($request->itemIds ?? []);

            foreach ($itemIds as $value) {
                $cartItem = CartSaveForLater::where('id', $value)
                    ->where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)
                          ->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 0) // NON-41 only
                    ->first();

                if (!$cartItem) continue;

                $cartItemData = $cartItem->toArray();
                unset($cartItemData['group_id'], $cartItemData['category_id'], $cartItemData['brand_id'], $cartItemData['id'], $cartItemData['created_at'], $cartItemData['updated_at']);

                // keep it explicitly NON-41
                $cartItemData['is_manager_41'] = 0;

                Cart::create($cartItemData);
                $cartItem->delete();
            }

            // Rebuild NON-41 views
            $carts = Cart::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 0)
                    ->get();

            $cartSaveForLater = CartSaveForLater::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 0)
                    ->get();

            $cartSaveForLaterCategory = CartSaveForLater::with('category')
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)
                ->selectRaw('category_id, COUNT(*) as product_count')
                ->groupBy('category_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'category_id'   => optional($item->category)->id,
                        'category_name' => optional($item->category)->name,
                        'product_count' => $item->product_count,
                    ];
                });

            $overdueAmount = $request->overdueAmount;
            $dueAmount     = $request->dueAmount;

            $cash_and_carry_item_flag_for_later = CartSaveForLater::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 0)
                    ->where('cash_and_carry_item', '1')
                    ->count();

            return response()->json([
                'html'             => view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount'))->render(),
                'viewSaveForLater' => view('frontend.partials.cart_save_for_laters', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
                'nav_cart_view'    => view('frontend.partials.cart')->render(),
            ]);

        } catch (\Exception $e) {
            \Log::error('moveAllCheckedItemToCart error: '.$e->getMessage());
            return response()->json([
                'status'  => 'Error',
                'message' => 'An unexpected error occurred. '.$e->getMessage(),
            ], 500);
        }
    }


    public function manager41MoveToCart(Request $request)
    {
        try {
            $user_id = Auth::id();
            $id = $request->input('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'Missing id.'], 422);
            }

            // Pick ONLY 41 saved item for this user
            $cartItem = CartSaveForLater::where('id', $id)
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->first();

            if (!$cartItem) {
                return response()->json(['status' => 'Error', 'message' => 'Item not found.'], 404);
            }

            $payload = $cartItem->toArray();
            unset($payload['id'], $payload['group_id'], $payload['category_id'], $payload['brand_id'], $payload['created_at'], $payload['updated_at']);
            $payload['is_manager_41'] = 1; // keep in 41 slice

            Cart::create($payload);
            $cartItem->delete();

            // Rebuild 41 collections
            $carts = Cart::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->get();

            $cartSaveForLater = CartSaveForLater::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->get();

            $cartSaveForLaterCategory = CartSaveForLater::with('category')
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->selectRaw('category_id, COUNT(*) as product_count')
                ->groupBy('category_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'category_id'   => optional($item->category)->id,
                        'category_name' => optional($item->category)->name,
                        'product_count' => $item->product_count,
                    ];
                });

            // Offer (kept as-is for your special user)
            $validOffers = [];
            $achiveOfferArray = [];
            if (Auth::id() == 24185) {
                $carts = $this->addOfferTag($carts);
                $validOffersTemp  = $this->checkValidOffer();
                $validOffers      = $validOffersTemp['offers'] ?? [];
                $achiveOfferArray = $validOffersTemp['achiveOfferArray'] ?? [];
            }

            $overdueAmount = $request->overdueAmount;
            $dueAmount     = $request->dueAmount;
            $cash_and_carry_item_flag_for_later = CartSaveForLater::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 1)
                    ->where('cash_and_carry_item', '1')
                    ->count();

            return response()->json([
                'html'             => view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount','validOffers','achiveOfferArray'))->render(),
                'viewSaveForLater' => view('frontend.partials.cart_save_for_laters_41', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
                'nav_cart_view'    => view('frontend.partials.cart', compact('carts','overdueAmount','dueAmount'))->render(),
            ]);

        } catch (\Exception $e) {
            \Log::error('manager41MoveToCart error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'Error',
                'message' => 'An unexpected error occurred. ' . $e->getMessage(),
            ], 500);
        }
    }


    // public function moveToCart(Request $request) {

    //       // If Manager-41, use the 41-specific flow
    //     if ($this->isActingAs41Manager()) {
    //         return $this->manager41MoveToCart($request);
    //     }
    //     try {
    //         $user_id = Auth::user()->id;        
    //         $id = $request->has('id')? $request->id : '';

    //         $cartItem = CartSaveForLater::where('id', $id)->first();
    //         $cartItemData = $cartItem->toArray();
            
    //         unset($cartItemData['group_id']);
    //         unset($cartItemData['category_id']);
    //         unset($cartItemData['brand_id']);
            
    //         Cart::create($cartItemData);
    //         // Cart::create($cartItem->toArray());         
            
    //         $cartItem->delete();

    //         $carts = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
           
    //         $cartSaveForLater = CartSaveForLater::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
    //         $cartSaveForLaterCategory = CartSaveForLater::with('category') // Eager load the related category
    //         ->where('user_id', $user_id)
    //         ->orWhere('customer_id', $user_id)
    //         ->selectRaw('category_id, COUNT(*) as product_count') // Select category_id and the product count
    //         ->groupBy('category_id')
    //         ->get()
    //         ->map(function ($item) {
    //             return [
    //                 'category_id' => $item->category->id,
    //                 'category_name' => $item->category->name, // Assuming 'name' is the category's name column
    //                 'product_count' => $item->product_count,
    //             ];
    //         });


    //         // Offer Section 
    //         // Offer Section
    //         $validOffers = array();
    //         $achiveOfferArray = array();
    //         if(Auth::user()->id == '24185'){
    //             $carts = $this->addOfferTag($carts);
    //             $validOffersTemp = $this->checkValidOffer();
    //             $validOffers = $validOffersTemp['offers'] ?? [];
    //             $achiveOfferArray = $validOffersTemp['achiveOfferArray'] ?? [];
    //             // echo "<pre>"; print_r($validOffers);die;
    //         }

    //         $overdueAmount = $request->overdueAmount;
    //         $dueAmount = $request->dueAmount;
    //         $cash_and_carry_item_flag_for_later = CartSaveForLater::where('cash_and_carry_item', '1')->where('user_id', $user_id)->orWhere('customer_id',$user_id)->count();
    //         return response()->json([
    //             'html' =>  view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount','validOffers','achiveOfferArray'))->render(), 
    //             'viewSaveForLater' => view('frontend.partials.cart_save_for_laters', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
    //             'nav_cart_view' => view('frontend.partials.cart')->render(),
    //         ]);

    //     } catch (\Exception $e) {
    //         // Log any other exceptions
    //         \Log::error('An error occurred: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => 'Error',
    //             'message' => 'An unexpected error occurred.' . $e->getMessage(),
    //         ], 500);
    //     }

    // }

    public function moveToCart(Request $request)
    {
        // If Manager-41, use the 41-specific flow
        if ($this->isActingAs41Manager()) {
            return $this->manager41MoveToCart($request);
        }

        try {
            $user_id = Auth::id();
            $id = $request->input('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'Missing id.'], 422);
            }

            // Pick ONLY non-41 saved item for this user
            $cartItem = CartSaveForLater::where('id', $id)
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)
                ->first();

            if (!$cartItem) {
                return response()->json(['status' => 'Error', 'message' => 'Item not found.'], 404);
            }

            $payload = $cartItem->toArray();
            unset($payload['id'], $payload['group_id'], $payload['category_id'], $payload['brand_id'], $payload['created_at'], $payload['updated_at']);
            $payload['is_manager_41'] = 0; // keep in non-41 slice

            $product = Product::find($payload['product_id']);
            $qty    = (int) $payload['quantity'];
            $userId = (int) $user_id;
            // Unit price for this qty (your helper should return a numeric)
            echo $price = (float) product_price_with_qty_condition($product, $userId, $qty); die;
            // Threshold logic
            $target       = (float) env('SPECIAL_DISCOUNT_AMOUNT', 5000);     // e.g. ₹5,000
            $spPercentage = (float) env('SPECIAL_DISCOUNT_PERCENTAGE', 3);    // kept if you use it later
            $subtotal = $price * $qty;
            $product = product_min_qty($product, $userId);

            $payload['price'] = $price;
            Cart::create($payload);
            $cartItem->delete();

            // Rebuild NON-41 collections
            $carts = Cart::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)
                ->get();

            $cartSaveForLater = CartSaveForLater::where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)
                ->get();

            $cartSaveForLaterCategory = CartSaveForLater::with('category')
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 0)
                ->selectRaw('category_id, COUNT(*) as product_count')
                ->groupBy('category_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'category_id'   => optional($item->category)->id,
                        'category_name' => optional($item->category)->name,
                        'product_count' => $item->product_count,
                    ];
                });

            // Offer (kept as-is for your special user)
            $validOffers = [];
            $achiveOfferArray = [];
            if (Auth::id() == 24185) {
                $carts = $this->addOfferTag($carts);
                $validOffersTemp  = $this->checkValidOffer();
                $validOffers      = $validOffersTemp['offers'] ?? [];
                $achiveOfferArray = $validOffersTemp['achiveOfferArray'] ?? [];
            }

            $overdueAmount = $request->overdueAmount;
            $dueAmount     = $request->dueAmount;
            $cash_and_carry_item_flag_for_later = CartSaveForLater::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 0)
                    ->where('cash_and_carry_item', '1')
                    ->count();

            return response()->json([
                'html'             => view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount','validOffers','achiveOfferArray'))->render(),
                'viewSaveForLater' => view('frontend.partials.cart_save_for_laters', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
                'nav_cart_view'    => view('frontend.partials.cart', compact('carts','overdueAmount','dueAmount'))->render(),
            ]);

        } catch (\Exception $e) {
            \Log::error('moveToCart error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'Error',
                'message' => 'An unexpected error occurred. ' . $e->getMessage(),
            ], 500);
        }
    }


    public function removeFromSaveForLeterView(Request $request) {
        try {
            $user_id = Auth::user()->id;        
            $id = $request->has('id')? $request->id : '';
            $cartItem = CartSaveForLater::where('id', $id)->first();            
            $cartItem->delete();
           
            $cartSaveForLater = CartSaveForLater::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
            $cartSaveForLaterCategory = CartSaveForLater::with('category') ->where('user_id', $user_id) ->orWhere('customer_id', $user_id) ->selectRaw('category_id, COUNT(*) as product_count') ->groupBy('category_id')  ->get()
            ->map(function ($item) {
                return [
                    'category_id' => $item->category->id,
                    'category_name' => $item->category->name,
                    'product_count' => $item->product_count,
                ];
            });

            $overdueAmount = $request->overdueAmount;
            $dueAmount = $request->dueAmount;
            $cash_and_carry_item_flag_for_later = CartSaveForLater::where('cash_and_carry_item', '1')->where('user_id', $user_id)->orWhere('customer_id',$user_id)->count();
            return response()->json([
                'viewSaveForLater' => view('frontend.partials.cart_save_for_laters', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
                // 'nav_cart_view' => view('frontend.partials.cart')->render(),
            ]);

        } catch (\Exception $e) {
            // Log any other exceptions
            \Log::error('An error occurred: ' . $e->getMessage());
            return response()->json([
                'status' => 'Error',
                'message' => 'An unexpected error occurred.' . $e->getMessage(),
            ], 500);
        }

    }

    public function sortByCategoryIdInSaveForLater(Request $request) {
        try {
            $user_id = Auth::user()->id;        
            $category_id = $request->has('category_id')? $request->category_id : '';
            $cartSaveForLaterCategory = CartSaveForLater::with('category') ->where('user_id', $user_id) ->orWhere('customer_id', $user_id) ->selectRaw('category_id, COUNT(*) as product_count') ->groupBy('category_id')  ->get()
            ->map(function ($item) {
                return [
                    'category_id' => $item->category->id,
                    'category_name' => $item->category->name,
                    'product_count' => $item->product_count,
                ];
            });
            $cartSaveForLater = CartSaveForLater::where('user_id', $user_id)->orWhere('customer_id', $user_id) ->orderByRaw("category_id = ? DESC", [$category_id])->get();

            $overdueAmount = $request->overdueAmount;
            $dueAmount = $request->dueAmount;
            $cash_and_carry_item_flag_for_later = CartSaveForLater::where('cash_and_carry_item', '1')->where('user_id', $user_id)->orWhere('customer_id',$user_id)->count();
            return response()->json([
                'viewSaveForLater' => view('frontend.partials.cart_save_for_laters', compact('cartSaveForLater','cartSaveForLaterCategory','cash_and_carry_item_flag_for_later'))->render(),
                // 'nav_cart_view' => view('frontend.partials.cart')->render(),
            ]);
        } catch (\Exception $e) {
            // Log any other exceptions
            \Log::error('An error occurred: ' . $e->getMessage());
            return response()->json([
                'status' => 'Error',
                'message' => 'An unexpected error occurred.' . $e->getMessage(),
            ], 500);
        }
    }

    public function viewFullStatement(Request $request) {
        try {
            $user_id = $request->userId;
            $userData = User::where('id', $user_id)->first();

            $statementArray = array();
            
            $currentDate = date('Y-m-d');
            $currentMonth = date('m');
            $currentYear = date('Y');

            $totalOverdueAmount = 0;
            $totalDueAmount = 0;

            // Define financial year date range based on the current date
            if ($currentMonth >= 4) {
                $from_date = date('Y-04-01'); // Start of financial year
                $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year
            } else {
                $from_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
                $to_date = date('Y-03-31'); // Current year March
            }

            // Limit the 'to_date' to the current date
            if ($to_date > $currentDate) {
                $to_date = $currentDate;
            }

            $userAddressGSTData = Address::where('acc_code',"!=","")->where('user_id',$userData->id)->groupBy('gstin')->orderBy('acc_code','ASC')->get();
            // echo "<pre>"; print_r($userAddressGSTData); die;
            foreach($userAddressGSTData as $uAkey=>$uAvalue){
                $party_code = $uAvalue->acc_code;

                // ----- New Logic Start -----
                $statementData = array();
                $userAddress = Address::where('acc_code', $party_code)->first();
                $userData = User::where('id', $userAddress->user_id)->first();

                if (!$userData) {
                    return response()->json(['error' => 'User or address not found'], 404);
                }                

                // Retrieve the address information
                $company_name = $userAddress->company_name ?? 'Company Name not found';
                $address = $userAddress->address ?? 'Address not found';
                $address_2 = $userAddress->address_2 ?? '';
                $postal_code = $userAddress->postal_code ?? '';

                if ($userAddress) {
                    $gstin = $userAddress->gstin;
                    $userAddressData = Address::where('user_id', $userData->id)->where('gstin', $gstin)->get();
                } else {
                    $userAddressData = collect(); // Return empty collection if no address found
                }
                
                $overdueAmount = 0;
                $dueAmount = 0;
                // echo "<pre>"; print_r($userAddressData);die;
                foreach ($userAddressData as $uValue) {
                    $decodedData = json_decode($uValue->statement_data, true);        
                    if (is_array($decodedData)) {
                        // Remove "closing C/f......" entries
                        $filteredData = array_filter($decodedData, function ($item) {
                            return !isset($item['ledgername']) || stripos($item['ledgername'], 'closing C/f...') === false;
                        });        
                        $statementData[$uValue->id] = $filteredData;
                        $overdueAmount += $uValue->overdue_amount;
                        $dueAmount += $uValue->due_amount;
                    }
                }
                // echo "<pre>"; print_r($statementData);die;
                $mergedData = [];
                foreach ($statementData as $data) {
                    $mergedData = array_merge($mergedData, $data);
                }
                usort($mergedData, function ($a, $b) {
                    return strtotime($a['trn_date']) - strtotime($b['trn_date']);
                });
                $statementData = array_values($mergedData);
                $balance = 0;
                
                foreach ($statementData as $gKey=>$gValue) {
                    if($gValue['ledgername'] == 'Opening b/f...'){
                        $balance = $gValue['dramount'] != 0.00 ? $gValue['dramount'] : -$gValue['cramount'];
                    }else{
                        $balance += (float)$gValue['dramount'] - (float)$gValue['cramount'];
                    }
                    
                    // single_price(trim($balance,'-'));
                    $statementData[$gKey]['running_balance'] = $balance;
                    // die;
                }
                
                if(isset($balance)){
                    $tempArray = array();
                    $tempArray['trn_no'] = "";
                    $tempArray['trn_date'] = date('Y-m-d');
                    $tempArray['vouchertypebasename'] = "";
                    $tempArray['ledgername'] = "closing C/f...";
                    // $amount = explode('₹',$value[5]);
                    $tempArray['ledgerid'] = "";
                    if($balance >= 0){
                        $tempArray['cramount'] = (float)str_replace(',', '',$balance);
                        $tempArray['dramount'] = (float)0.00;
                    }else{
                        $tempArray['dramount'] = (float)str_replace(',', '',$balance);
                        $tempArray['cramount'] = (float)0.00;
                    }
                    $tempArray['narration'] = "";
                    $statementData[] = $tempArray;
                }
                
                // ----- New Logic End -----

                // Variables to store balances
                $openingBalance = "0";
                $closingBalance = "0";
                $openDrOrCr = "";
                $closeDrOrCr = "";
                $overdueDrOrCr = 'Dr'; // Default value for overdue Dr/Cr

                // Calculate total debit
                $totalDebit = 0;
                foreach ($statementData as $transaction) {
                    if (isset($transaction['dramount']) && $transaction['dramount'] != "0.00") {
                        $totalDebit += floatval($transaction['dramount']);
                    }
                }

                // Get user credit limit and calculate available credit
                $creditLimit = floatval($userData->credit_limit);
                $availableCredit = $creditLimit - $totalDebit;

                $getOverdueData = $statementData;
                
                // Iterate through statement data and process transactions
                foreach ($statementData as $transaction) {
                    if (isset($transaction['ledgername']) && $transaction['ledgername'] == "Opening b/f...") {
                        $openingBalance = ($transaction['dramount'] != "0.00") ? floatval($transaction['dramount']) : floatval($transaction['cramount']);
                        $openDrOrCr = ($transaction['dramount'] != "0.00") ? "Dr" : "Cr";
                    } elseif (isset($transaction['ledgername']) && $transaction['ledgername'] == "closing C/f...") {
                        $closingBalance = ($transaction['dramount'] != "0.00") ? floatval($transaction['dramount']) : floatval($transaction['cramount']);
                        $closeDrOrCr = ($transaction['dramount'] != "0.00") ? "Dr" : "Cr";

                        // Set dueAmount and overdueAmount and also set overdueDrOrCr based on closing balance
                        if ($transaction['dramount'] != "0.00") {
                            $dueAmount = floatval($transaction['dramount']);
                            $overdueDrOrCr = 'Dr';
                        } else {
                            $dueAmount = floatval($transaction['cramount']);
                            $overdueDrOrCr = 'Cr';
                        }

                        $cloasingDrAmount = $transaction['dramount'];
                        $cloasingCrAmount = $transaction['cramount'];
                        $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));

                        if ($cloasingCrAmount > 0) {
                            $drBalanceBeforeOVDate = 0;
                            $crBalanceBeforeOVDate = 0;
                            $getOverdueData = array_reverse($getOverdueData);

                            foreach ($getOverdueData as $ovValue) {
                                if ($ovValue['ledgername'] != 'closing C/f...') {
                                    if (strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)) {
                                        $crBalanceBeforeOVDate += $ovValue['cramount'];
                                    } else {
                                        $drBalanceBeforeOVDate += $ovValue['dramount'];
                                        $crBalanceBeforeOVDate += $ovValue['cramount'];
                                    }
                                }
                            }
                            $overdueAmount = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                        }

                        if ($overdueAmount <= 0) {
                            $overdueDrOrCr = 'Cr';
                            $overdueAmount = 0;
                        } else {
                            $overdueDrOrCr = 'Dr';
                        }
                    }
                }
                
                // --------- Start Calculate the Ovedue date and balance and update the statement for pdf -------
                $overdueAmount = "0";
                $openingBalance="0";
                $openDrOrCr="";
                $closingBalance="0";
                $closeDrOrCr="";
                $dueAmount="0";
                $overdueDateFrom="";
                $overdueDrOrCr="";
                $overDueMark = array();
                $drBalance = 0;
                $crBalance = 0;
                $drBalanceBeforeOVDate = 0;
                $crBalanceBeforeOVDate = 0;
                $getOverdueData = $statementData;
                $getOverdueData = array_reverse($getOverdueData);
                $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
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

                $totalOverdueAmount += $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                $overDueMark = array();
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            // $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }elseif(strtotime($ovValue['trn_date']) <= strtotime($overdueDateFrom) AND $temOverDueBalance > 0 AND $ovValue['dramount'] != '0.00'){
                            $temOverDueBalance -= $ovValue['dramount'];
                            $date1 = $ovValue['trn_date'];
                            $date2 = $overdueDateFrom;        
                            $diff = abs(strtotime($date2) - strtotime($date1));

                            $dateDifference = floor($diff / (60 * 60 * 24)).' days';
                            if($temOverDueBalance >= 0){
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Overdue'
                                ];
                            }else{
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Pertial Overdue'
                                ];
                            }
                        }
                    }
                }

                if($overdueAmount <= 0){
                    $overdueDrOrCr = 'Cr';
                    $overdueAmount = 0;
                }else{
                    $overdueDrOrCr = 'Dr';
                }

                // $getData = $statementData;
                if(count($overDueMark) > 0){
                    $overDueMarkTrnNos = array_column($overDueMark, 'trn_no');
                    $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
                    $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
                }
                // echo "<pre>"; print_r($overDueMark);die;

                foreach($statementData as $gKey=>$gValue){                
                    if($gValue['ledgername'] == "Opening b/f..."){
                        if($gValue['dramount'] != "0.00"){
                            $openingBalance = $gValue['dramount'];
                            $openDrOrCr = "Dr";
                        }else{
                            $openingBalance = $gValue['cramount'];
                            $openDrOrCr = "Cr";
                        }
                    }else if($gValue['ledgername'] == "closing C/f..."){
                        if($gValue['dramount'] != "0.00"){
                            $closingBalance = $gValue['dramount'];
                            // $dueAmount = $gValue['dramount'];
                            // $closeDrOrCr = "Dr";
                        }else{
                            $closingBalance = $gValue['cramount'];
                            // $closeDrOrCr = "Cr";
                            // $dueAmount = $gValue['cramount'];
                        }
                    }
                    if(count($overDueMark) > 0) {
                        $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                        if ($key !== false) {
                            $statementData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                            $statementData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                            // echo $gValue['trn_date'];
                        }else{
                            if(isset($statementData[$gKey]['overdue_status'])){
                                unset($statementData[$gKey]['overdue_status']);
                                unset($statementData[$gKey]['overdue_by_day']);
                            }
                        }
                    }else{
                        if(isset($statementData[$gKey]['overdue_status'])){
                            unset($statementData[$gKey]['overdue_status']);
                            unset($statementData[$gKey]['overdue_by_day']);
                        }
                    }
                    if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                        $drBalance = $drBalance + $gValue['dramount'];
                        $totalDueAmount += $gValue['dramount'];
                    } 
                    if($gValue['cramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                        $crBalance = $crBalance + $gValue['cramount'];
                        $totalDueAmount -= $gValue['cramount'];
                    }
                    
                }

                $statementArray[$uAkey]['cmp_name'] = $uAvalue->company_name;
                $statementArray[$uAkey]['party_code'] = $uAvalue->acc_code;
                $statementArray[$uAkey]['statement'] = $statementData;
            }

            // echo "<pre>";echo $totalDueAmount; print_r($statementArray);die;
            // $overdueAmount = ceil($overdueAmount);
            if ($totalOverdueAmount <= 0) {
                $overdueDrOrCr = 'Cr';
                $totalOverdueAmount = 0;
            } else {
                $overdueDrOrCr = 'Dr';
            }
            // $dueAmount = ceil($dueAmount);
            if ($totalDueAmount <= 0) {
                $dueDrOrCr = 'Cr';
                $totalDueAmount = 0;
            } else {
                $dueDrOrCr = 'Dr';
            }
            // --------------------------------- Calculate Due and Overdue amount --------------------------------------

            // Prepare and generate the PDF
            $randomNumber = rand(1000, 9999);
            $fileName = 'statement-' . $randomNumber . '.pdf';

            $overdueAmount = $totalOverdueAmount;
            $dueAmount = $totalDueAmount;            

            // Load the Blade view for the PDF generation
            $pdf = PDF::loadView('frontend.partials.statement_from_cart_pdf', compact(
                'userData',
                'statementArray',
                'from_date',
                'to_date',
                'overdueAmount',
                'overdueDrOrCr',
                'dueDrOrCr',
                'dueAmount'
            ))->save(public_path('statements/' . $fileName));

            return response()->json(['pdf_url' =>'public/statements/' . $fileName]);
        } catch (\Exception $e) {
            // Log any other exceptions
            \Log::error('An error occurred: ' . $e->getMessage());
            return response()->json([
                'status' => 'Error',
                'message' => 'An unexpected error occurred.' . $e->getMessage(),
            ], 500);
        }
    }

    public function viewFullStatement_backup_09_06_2025(Request $request) {
        try {
            $user_id = $request->userId;        
            // --------------------------------- Calculate Due and Overdue amount --------------------------------------
            $currentDate = date('Y-m-d');
            $currentMonth = date('m');
            $currentYear = date('Y');
            $overdueDateFrom="";
            $overdueAmount="0";

            $openingBalance="0";
            $drBalance = 0;
            $crBalance = 0;
            $dueAmount = 0;
            $openDrOrCr = "";
            $closeDrOrCr = "";
            $closingBalance = 0;
            $dueDrOrCr  = "";
            

            $userData = User::where('id', $user_id)->first();
            $userAddressData = Address::where('acc_code',"!=","")->where('user_id',$userData->id)->groupBy('gstin')->orderBy('acc_code','ASC')->get();
            $statementArray = array();
            foreach($userAddressData as $key=>$value){
                $party_code = $value->acc_code;
                if ($currentMonth >= 4) {
                    $fy_form_date = date('Y-04-01'); // Start of financial year
                    $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
                } else {
                    $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
                    $fy_to_date = date('Y-03-31'); // Current year March
                }
                $from_date = $fy_form_date;
                $to_date = $fy_to_date;
                $headers = [
                    'authtoken' => '65d448afc6f6b',
                ];
                $body = [
                    'party_code' => $party_code,
                    'from_date' => $from_date,
                    'to_date' =>  $to_date,
                ];
                $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
                \Log::info('Received response from Salzing API For Sync Statement Overdue Calculation', [
                    'status' => $response->status(),
                    'party_code' =>  $party_code,
                    'body' => $response->body()
                ]);
                if ($response->successful()) {
                    $getData = $response->json();
                    $statementArray[$key]['cmp_name'] = $value->company_name;
                    $statementArray[$key]['party_code'] = $value->acc_code;
                    $statementArray[$key]['statement'] = array();
                    if(!empty($getData) AND isset($getData['data']) AND !empty($getData['data'])){
                        $getData = $getData['data'];
                        
                        $closingBalanceResult = array_filter($getData, function ($entry) {
                            return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
                        });
                        $closingEntry = reset($closingBalanceResult);
                        $cloasingDrAmount = $closingEntry['dramount'];
                        $cloasingCrAmount = $closingEntry['cramount'];          
                        $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
                        if($cloasingCrAmount > 0){
                            $drBalanceBeforeOVDate = 0;
                            $crBalanceBeforeOVDate = 0;
                            $getData = array_reverse($getData);
                            foreach($getData as $ovKey=>$gValue){
                                if($gValue['ledgername'] != 'closing C/f...'){
                                    if(strtotime($gValue['trn_date']) > strtotime($overdueDateFrom)){
                                        // $drBalanceBeforeOVDate += $ovValue['dramount'];
                                        $crBalanceBeforeOVDate += $gValue['cramount'];
                                    }else{
                                        $drBalanceBeforeOVDate += $gValue['dramount'];
                                        $crBalanceBeforeOVDate += $gValue['cramount'];
                                    }
                                }
                                if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                                    $drBalance = $drBalance + $gValue['dramount'];
                                    $dueAmount = $dueAmount + $gValue['dramount'];
                                } 
                                if($gValue['cramount'] != '0.00' AND $gValue['ledgername'] != 'closing C/f...') {
                                    $crBalance = $crBalance + $gValue['cramount'];
                                    $dueAmount = $dueAmount - $gValue['cramount'];
                                }

                            }
                            $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                            $overDueMark = array();
                            
                            foreach($getData as $ovKey=>$ovValue){
                                if($ovValue['ledgername'] != 'closing C/f...'){
                                    if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                                        // $drBalanceBeforeOVDate += $ovValue['dramount'];
                                        // $crBalanceBeforeOVDate += $ovValue['cramount'];
                                    }elseif(strtotime($ovValue['trn_date']) <= strtotime($overdueDateFrom) AND $temOverDueBalance > 0 AND $ovValue['dramount'] != '0.00'){
                                        $temOverDueBalance -= $ovValue['dramount'];
                                        $date1 = $ovValue['trn_date'];
                                        $date2 = $overdueDateFrom;
            
                                        $diff = abs(strtotime($date2) - strtotime($date1));
            
                                        $dateDifference = floor($diff / (60 * 60 * 24)).' days';
                                        if($temOverDueBalance >= 0){
                                            $getData[$ovKey]['overdue_by_day'] = $dateDifference;
                                            $getData[$ovKey]['overdue_status'] = 'Overdue';
                                        }else{
                                            $getData[$ovKey]['overdue_by_day'] = $dateDifference;
                                            $getData[$ovKey]['overdue_status'] = 'Pertial Overdue';
                                        }
                                    }
                                }
                            }
                        }
                        if(count($getData) > 0){
                            $statementArray[$key]['statement'] = array_reverse($getData);
                        }                        
                    }
                }
            }
            
            // echo "<pre>";print_r($statementArray);die;
            // $overdueAmount = ceil($overdueAmount);
            if ($overdueAmount <= 0) {
                $overdueDrOrCr = 'Cr';
                $overdueAmount = 0;
            } else {
                $overdueDrOrCr = 'Dr';
            }
            // $dueAmount = ceil($dueAmount);
            if ($dueAmount <= 0) {
                $dueDrOrCr = 'Cr';
                $dueAmount = 0;
            } else {
                $dueDrOrCr = 'Dr';
            }
            // --------------------------------- Calculate Due and Overdue amount --------------------------------------

            // Prepare and generate the PDF
            $randomNumber = rand(1000, 9999);
            $fileName = 'statement-' . $randomNumber . '.pdf';

            // Load the Blade view for the PDF generation
            $pdf = PDF::loadView('frontend.partials.statement_from_cart_pdf', compact(
                'userData',
                'statementArray',
                'from_date',
                'to_date',
                'overdueAmount',
                'overdueDrOrCr',
                'dueDrOrCr',
                'dueAmount'
            ))->save(public_path('statements/' . $fileName));

            return response()->json(['pdf_url' =>'public/statements/' . $fileName]);
        } catch (\Exception $e) {
            // Log any other exceptions
            \Log::error('An error occurred: ' . $e->getMessage());
            return response()->json([
                'status' => 'Error',
                'message' => 'An unexpected error occurred.' . $e->getMessage(),
            ], 500);
        }
    }

    public function showCartModalManager41(Request $request)
    {
        $product      = Product::find($request->id);
        $order_id     = $request->order_id;
        $sub_order_id = $request->sub_order_id;
        $user_id      = "";

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Manager-41 fixed flag
        $is41Manager = true;

        // Safe decrypt helper: encrypted ya plain, dono handle
        $safeDecrypt = function ($value) {
            try {
                return decrypt((string) $value);
            } catch (\Throwable $e) {
                return (int) $value;
            }
        };

        // ---- Resolve user via Manager-41 ORDER ----
        if ($request->filled('order_id')) {
            $resolvedOrderId = $safeDecrypt($request->order_id);
            $orderData = \App\Models\Manager41Order::find($resolvedOrderId);

            if ($orderData) {
                if ($orderData->user_id) {
                    $user = User::find($orderData->user_id);
                    if ($user) {
                        $user_warehouse_id = $user->warehouse_id ?? null;
                        $user_id           = $user->id;
                    }
                }
            }
        }

        // ---- Resolve user via Manager-41 SUB-ORDER ----
        if ($request->filled('sub_order_id')) {
            $resolvedSubOrderId = $safeDecrypt($request->sub_order_id);
            $subOrderData = \App\Models\Manager41SubOrder::find($resolvedSubOrderId);

            if ($subOrderData) {
                if ($subOrderData->user_id) {
                    $user = User::find($subOrderData->user_id);
                    if ($user) {
                        $user_warehouse_id = $user->warehouse_id ?? null;
                        $user_id           = $user->id;
                    }
                }
            }
        }

        try {
            // -------- Variations (same logic as your original) --------
            $attributeVariations = [];
            $selectedValues      = [];

            if ((int)$product->variant_product === 1) {
                $parentProducts = Product::where('variation_parent_part_no', $product->variation_parent_part_no)
                    ->where('current_stock', 1)
                    ->pluck('variations')
                    ->toArray();

                $allVariationIds = collect($parentProducts)
                    ->flatMap(function ($variations) {
                        $arr = is_string($variations) ? json_decode($variations, true) : $variations;
                        return is_array($arr) ? $arr : [];
                    })
                    ->unique()
                    ->values()
                    ->all();

                $attributeValues = AttributeValue::whereIn('id', $allVariationIds)->get();

                $attributeVariations = Attribute::whereIn('id', $attributeValues->pluck('attribute_id'))
                    ->where('is_variation', 1)
                    ->get()
                    ->map(function ($attribute) use ($attributeValues) {
                        return [
                            'attribute_id'   => $attribute->id,
                            'attribute_name' => $attribute->name,
                            'values'         => $attributeValues->where('attribute_id', $attribute->id)->pluck('value', 'id'),
                        ];
                    });

                $selectedValues = $attributeValues
                    ->whereIn('id', json_decode($product->variations ?? '[]', true))
                    ->pluck('id', 'attribute_id');
            }

            // -------- Offers (unchanged) --------
            if (Auth::user()->user_type === 'admin' || Auth::user()->user_type === 'staff') {
                $product->offer = "";
            } else {
                $userDetails = User::with(['get_addresses' => function ($q) {
                        $q->orderBy('acc_code', 'asc');
                    }])
                    ->where('id', Auth::user()->id)
                    ->first();

                $state_id    = optional($userDetails->get_addresses[0] ?? null)->state_id;
                $currentDate = Carbon::now();

                $offers = Offer::with('offerProducts')
                    ->where('status', 1)
                    ->where(function ($q) use ($userDetails) {
                        $q->where('manager_id', $userDetails->manager_id)
                          ->orWhereNull('manager_id');
                    })
                    ->where(function ($q) use ($state_id) {
                        $q->where('state_id', $state_id)
                          ->orWhereNull('state_id');
                    })
                    ->whereDate('offer_validity_start', '<=', $currentDate)
                    ->whereDate('offer_validity_end', '>=', $currentDate)
                    ->whereHas('offerProducts', function ($q) use ($product) {
                        $q->where('product_id', $product->id);
                    })
                    ->get();

                $product->offer = $offers->count() > 0 ? $offers : "";
            }

        } catch (\Exception $e) {
            \Log::error('Error in showCartModalManager41: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while preparing product data'], 500);
        }

        return view('frontend.partials.addToCart_manager41', compact(
            'product',
            'attributeVariations',
            'selectedValues',
            'order_id',
            'sub_order_id',
            'user_id',
            'is41Manager'
        ));
    }


    public function showCartModal(Request $request)
    {

        if (method_exists($this, 'isActingAs41Manager') && $this->isActingAs41Manager()) {
            return $this->showCartModalManager41($request);
        }
        $product = Product::find($request->id);
        $order_id = $request->order_id;
        $sub_order_id = $request->sub_order_id;
        $user_id = "";
        if(isset($request->order_id)){ 
            $order_id = $request->order_id;
            $encryptedId = (string) $request->order_id;
            $temp_order_id = decrypt($encryptedId);
            $orderData = Order::where('id',$temp_order_id)->first();
            $user = User::where('id',$orderData->user_id)->first();
            $user_warehouse_id = $user->warehouse_id;
            $user_id = $user->id;
        }
        if(isset($request->sub_order_id)){
            $sub_order_id = $request->sub_order_id;
            $encryptedId = (string) $request->sub_order_id;
            $temp_sub_order_id= decrypt($encryptedId);
            $subOrderData = SubOrder::where('id',$temp_sub_order_id)->first();
            $user = User::where('id',$subOrderData->user_id)->first();
            $user_warehouse_id = $user->warehouse_id;
            $user_id = $user->id;
        }

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        try {
            // Initialize variables
            $attributeVariations = [];
            $selectedValues = [];
            if ($product->variant_product == 1) {
                // Fetch all products with the same variation_parent_part_no
                $parentProducts = Product::where('variation_parent_part_no', $product->variation_parent_part_no)
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

                // Fetch attribute values based on variation IDs
                $attributeValues = AttributeValue::whereIn('id', $allVariationIds)->get();

                // Fetch attributes and their corresponding values
                $attributeVariations = Attribute::whereIn('id', $attributeValues->pluck('attribute_id'))
                    ->where('is_variation', 1)
                    ->get()
                    ->map(function ($attribute) use ($attributeValues) {
                        return [
                            'attribute_id' => $attribute->id,
                            'attribute_name' => $attribute->name,
                            'values' => $attributeValues->where('attribute_id', $attribute->id)->pluck('value', 'id'),
                        ];
                    });

                // Get the selected values for the loaded product
                $selectedValues = $attributeValues->whereIn('id', json_decode($product->variations, true))
                    ->pluck('id', 'attribute_id');
            }

            $productId=$product->id;
            // $userDetails = User::with(['get_addresses' => function ($query) {
            //     $query->where('set_default', 1);
            // }])->where('id', Auth::user()->id)->first();
            // echo "<pre>"; print_r(Auth::user()); die;
            if(Auth::user()->user_type == 'admin' OR Auth::user()->user_type == 'staff'){
                $product->offer = "";
            }else{
                $userDetails = User::with(['get_addresses' => function ($query) {
                    $query->orderBy('acc_code', 'asc');
                }])->where('id', Auth::user()->id)->first();
                // echo "<pre>".Auth::user()->id; print_r($userDetails->get_addresses);die;
                $state_id = $userDetails->get_addresses[0]->state_id;
                $currentDate = Carbon::now(); // Get the current date and time
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
                    $product->offer = $offers;
                }else{
                    $product->offer = "";
                }
            }
            product_min_qty($product, Auth::user()->id);
            
        } catch (\Exception $e) {
            // Log and return error for debugging
            \Log::error('Error fetching product variations: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching product variations'], 500);
        }

       // determine 41-manager once, here:
       $is41Manager = $this->isActingAs41Manager();

        // Return the view with the required data
        return view('frontend.partials.addToCart', compact('product', 'attributeVariations', 'selectedValues', 'order_id', 'sub_order_id','user_id','is41Manager'));
    }


    public function addToCartManager41(Request $request)
    {
        /** @var \App\Models\Product $product */
        $product = Product::find($request->id);
        if (!$product) {
            return [
                'status'        => 0,
                'cart_count'    => 0,
                'modal_view'    => view('frontend.partials.minQtyNotSatisfied', ['min_qty' => 1])->render(),
                'nav_cart_view' => view('frontend.partials.cart')->render(),
            ];
        }

        // --- Identify user / temp user ---
        $carts   = collect();
        $data    = [];
        $userId  = auth()->check() ? (int) auth()->id() : 0;

        if (auth()->check()) {
            $data['user_id'] = $userId;
            $carts = Cart::where('user_id', $userId)->orWhere('customer_id', $userId)->get();
        } else {
            if (!$request->session()->get('temp_user_id')) {
                $request->session()->put('temp_user_id', bin2hex(random_bytes(10)));
            }
            $data['temp_user_id'] = $request->session()->get('temp_user_id');
            $carts = Cart::where('temp_user_id', $data['temp_user_id'])->get();
        }

        // --- Guard: min qty (respect product->min_qty) ---
        $qtyRequested = (int) ($request->quantity ?? 1);
        if ($product->digital != 1 && $qtyRequested < (int) $product->min_qty) {
            return [
                'status'        => 0,
                'cart_count'    => $carts->count(),
                'modal_view'    => view('frontend.partials.minQtyNotSatisfied', ['min_qty' => $product->min_qty])->render(),
                'nav_cart_view' => view('frontend.partials.cart')->render(),
            ];
        }

        // --- Normalized fields for 41 carts ---
        $type                       = (string) ($request->type ?? 'piece'); // 'piece' | 'bulk'
        $isCarton                   = (int) ($type === 'bulk');             // 1 for bulk rows
        $data['product_id']         = $product->id;
        $data['owner_id']           = $product->user_id;
        $data['variation']          = $product->slug;    // your existing variation style
        $data['quantity']           = $qtyRequested;
        $data['tax']                = 0;
        $data['shipping_cost']      = 0;
        $data['product_referral_code'] = null;
        $data['cash_on_delivery']   = $product->cash_on_delivery;
        $data['cash_and_carry_item']= $product->cash_and_carry_item;
        $data['digital']            = $product->digital;
        $data['is_manager_41']      = 1;                 // <- mark as 41
        $data['is_carton']          = $isCarton;

        // Keep request('is_carton') in sync for any downstream usage
        $request->merge(['is_carton' => $isCarton]);

        // --- PRICE (server-side) ---
        // Uses your helpers which now auto-pick mrp_41_price for Manager-41.
        if ($type === 'bulk') {
            $unit = (float) home_bulk_discounted_price($product, false, $userId)['price'];
        } else {
            $unit = (float) home_discounted_price($product, false, $userId)['price'];
        }
        $data['price'] = price_less_than_50($unit, false); // numeric only

        // --- Referral cookie (unchanged) ---
        if (Cookie::has('referred_product_id') && Cookie::get('referred_product_id') == $product->id) {
            $data['product_referral_code'] = Cookie::get('product_referral_code');
        }

        // --- IMPORTANT: do NOT merge with any existing row for Manager-41 ---
        Cart::create($data);

        // --- Re-fetch cart for counters/views ---
        if ($userId) {
            $carts = Cart::where('user_id', $userId)->orWhere('customer_id', $userId)->get();
        } else {
            $carts = Cart::where('temp_user_id', $data['temp_user_id'])->get();
        }

        return [
            'status'        => 1,
            'cart_count'    => $carts->count(),
            'modal_view'    => view('frontend.partials.addedToCart', compact('product', 'data'))->render(),
            'nav_cart_view' => view('frontend.partials.cart')->render(),
        ];
    }



    public function addToCart(Request $request) {


         // If Manager-41 is active, delegate to the dedicated flow.
        if ($this->isActingAs41Manager()) {
            return $this->addToCartManager41($request);
        }

        $product = Product::find($request->id);
        // echo "<pre>"; print_r($product);die;
        $carts   = array();
        $data    = array();

         // 👇 determine Manager-41 (works with impersonation)
        $is41 = $this->isActingAs41Manager();   // returns true/false

        if (auth()->user() != null) {
        $user_id         = Auth::user()->id;
        $data['user_id'] = $user_id;
        $carts = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
        } else {
        if ($request->session()->get('temp_user_id')) {
            $temp_user_id = $request->session()->get('temp_user_id');
        } else {
            $temp_user_id = bin2hex(random_bytes(10));
            $request->session()->put('temp_user_id', $temp_user_id);
        }
        $data['temp_user_id'] = $temp_user_id;
        $carts = Cart::where('temp_user_id', $temp_user_id)->get();
        }

        $data['product_id'] = $product->id;
        $data['owner_id']   = $product->user_id;

        $str     = '';
        $tax     = $ctax     = $price     = $carton_price     = 0;
        $wmarkup = 0;
        if ($product->digital != 1 && $request->quantity < $product->min_qty) {
        return array(
            'status'        => 0,
            'cart_count'    => count($carts),
            'modal_view'    => view('frontend.partials.minQtyNotSatisfied', ['min_qty' => $product->min_qty])->render(),
            'nav_cart_view' => view('frontend.partials.cart')->render(),
        );
        }

        //check the color enabled or disabled for the product
        if ($request->has('color')) {
        $str = $request['color'];
        }

        if ($product->digital != 1) {
        //Gets all the choice values of customer choice option and generate a string like Black-S-Cotton
        if(is_countable(json_decode(Product::find($request->id)->choice_options))){
            foreach (json_decode(Product::find($request->id)->choice_options) as $key => $choice) {
            if ($str != null) {
                $str .= '_' . str_replace(' ', '', strtolower($request['attribute_id_' . $choice->attribute_id]));
            } else {
                $str .= str_replace(' ', '', strtolower($request['attribute_id_' . $choice->attribute_id]));
            }
            }
        }
        
        }
        $str = $product->slug;
        $data['variation'] = $str;

        $product_stock = $product->stocks->where('variant', $str);
        
        //$price=calculate_discounted_price($product->mrp,false)['net_selling_price'];
        
        $user = Auth::user();

        $discount = 0;

        if ($user) {
            $discount = $user->discount;
        }

        if(!is_numeric($discount) || $discount == 0) {
            $discount = 20;
        }

        if($request->type == "piece"){
            if($user_id != '24185'){
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
            }else{
                $qty    = (int) $request['quantity'];
                $userId = (int) $user->id;
                $price  = product_price_with_qty_condition($product, $userId, $qty);
            }
        }elseif($request->type == "bulk"){
            $price = $request->order_by_carton_price;
        }

        
        
        $data['quantity']  = $request['quantity'];
        $data['price']     = price_less_than_50($price,false);
        $data['tax']       = $tax;
        //$data['shipping'] = 0;
        $data['shipping_cost']         = 0;
        $data['product_referral_code'] = null;
        $data['cash_on_delivery']      = $product->cash_on_delivery;
        $data['cash_and_carry_item']      = $product->cash_and_carry_item;
        $data['digital']               = $product->digital;
        
         // 👇 NEW: mark if added by Manager-41
        $data['is_manager_41']       = $is41 ? 1 : 0;

        if ($request['quantity'] == null) {
        $data['quantity'] = 1;
        }

        if (Cookie::has('referred_product_id') && Cookie::get('referred_product_id') == $product->id) {
        $data['product_referral_code'] = Cookie::get('product_referral_code');
        }
        
        if ($carts && count($carts) > 0) {
        $foundInCart = false;
        foreach ($carts as $key => $cartItem) {
            $cart_product = Product::where('id', $cartItem['product_id'])->first();
            if ($cartItem['product_id'] == $request->id) {
            //BEFORE
            // if ($cartItem['is_carton'] != $request['is_carton']) {
            //     $deleteCartRequest = new Request();
            //     $deleteCartRequest->replace(['id' => $cartItem['id']]);
            //     $this->removeFromCart($deleteCartRequest);
            // }
            // AFTER
            if (!$is41 && $cartItem['is_carton'] != $request['is_carton']) { // <<< CHANGE
                $deleteCartRequest = new Request();
                $deleteCartRequest->replace(['id' => $cartItem['id']]);
                $this->removeFromCart($deleteCartRequest);
            }
            $product_stock = $cart_product->stocks->where('variant', $str);

            // if (($str != null && $cartItem['variation'] == $str) || $str == null) {
            //     $foundInCart = true;
            //     $cartItem['quantity'] += $request['quantity'];
            //     $cartItem['price'] = $price;

            //      // 👇 ensure the flag is kept accurate on updates too
            //     $cartItem['is_manager_41'] = $is41 ? 1 : 0;

            //     $cartItem->save();
            // }
            // AFTER
                if (
                    !$is41 && // <<< CHANGE: never merge when Manager-41 is adding
                    (($str != null && $cartItem['variation'] == $str) || $str == null) &&
                    (int)$cartItem['is_manager_41'] === 0 // <<< CHANGE: merge only into non-Manager-41 rows
                ) {
                    $foundInCart = true;
                    $cartItem['quantity'] += $request['quantity'];
                    $cartItem['price'] = $price;
                
                    // DO NOT change manager flag on update // <<< CHANGE (remove the assignment below)
                    // $cartItem['is_manager_41'] = $is41 ? 1 : 0;
                
                    $cartItem->save();
                }
            }
        }
        if (!$foundInCart) {
            Cart::create($data);
        }
        } else {
        Cart::create($data);
        }

        if (auth()->user() != null) {
        $user_id = Auth::user()->id;
        $carts   = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
        } else {
        $temp_user_id = $request->session()->get('temp_user_id');
        $carts        = Cart::where('temp_user_id', $temp_user_id)->get();
        }
    
        return array(
        'status'        => 1,
        'cart_count'    => count($carts),
        'modal_view'    => view('frontend.partials.addedToCart', compact('product', 'data'))->render(),
        'nav_cart_view' => view('frontend.partials.cart')->render(),
        );
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

public function manager41AddProductToSplitOrder(Request $request)
{
    try {
        // Optional guard — only allow when logged in as Manager_41
        if (method_exists($this, 'isActingAs41Manager') && !$this->isActingAs41Manager()) {
            abort(403, 'Access denied. Only manager_41 can add items here.');
        }

        $order_id = '';
        $sub_order_id = '';

        if (!empty($request->order_id)) {
            $encryptedId = (string) $request->order_id;
            $order_id = decrypt($encryptedId);
        }
        if (!empty($request->sub_order_id)) {
            $encryptedId = (string) $request->sub_order_id;
            $sub_order_id = decrypt($encryptedId);
        }

        // Resolve Manager-41 sub order / order
        $subOrderData = null;
        if ($sub_order_id !== '') {
            $subOrderData = \App\Models\Manager41SubOrder::where('id', $sub_order_id)->first();
            if (!$subOrderData) {
                return ['error' => 'Manager41 Sub-Order not found.'];
            }
            $order_id = $subOrderData->order_id;
        }

        $orderData = \App\Models\Manager41Order::where('id', $order_id)->first();
        if (!$orderData) {
            return ['error' => 'Manager41 Order not found.'];
        }

        // Product must be flagged for Manager-41
        $product = \App\Models\Product::find($request->id);
        if (!$product) {
            return ['error' => 'Product not found.'];
        }
        if ((int)($product->is_manager_41 ?? 0) !== 1) {
            return ['error' => 'This product is not allowed for Manager-41.'];
        }

        // Prepare $data for the modal (unchanged shape from your original)
        $carts = [];
        $data  = [];
        $data['order_id']  = $orderData->id;
        $data['seller_id'] = $product->seller_id; // you kept this in data (UI), but details row uses user_id
        $data['product_id']= $product->id;
        $data['owner_id']  = $product->user_id;

        // Variation (your original ends up using slug)
        $str = $product->slug;
        $data['variation'] = $str;

        // Pricing/discount logic (same as your original)
        $tax      = 0;
        $price    = 0;
        $user     = \App\Models\User::where('id', $orderData->user_id)->first();
        $discount = $user ? ($user->discount ?? 0) : 0;
        if (!is_numeric($discount) || $discount == 0) {
            $discount = 20;
        }

        if ($request->type === 'piece') {
            $product_mrp = \App\Models\Product::where('id', $product->id)->value('mrp');
            $mrp = is_numeric($product_mrp) ? (float)$product_mrp : 0;
            $price = $mrp * ((100 - $discount) / 100);
        } elseif ($request->type === 'bulk') {
            $price = (float) ($request->order_by_carton_price ?? 0);
        }

        $qty = (int) ($request['quantity'] ?? 1);
        if ($qty <= 0) $qty = 1;

        $netUnit = price_less_than_50($price, false);
        $data['quantity']            = $qty;
        $data['price']               = $netUnit;
        $data['tax']                 = $tax;
        $data['shipping_cost']       = 0;
        $data['product_referral_code'] = null;
        $data['cash_on_delivery']    = $product->cash_on_delivery;
        $data['cash_and_carry_item'] = $product->cash_and_carry_item;
        $data['digital']             = $product->digital;

        // Check existing Manager-41 order details (by product)
        if ($subOrderData) {
            $existingOrderDetail = \App\Models\Manager41OrderDetail::where('order_id', $subOrderData->order_id)
                ->where('product_id', $product->id)
                ->first();
        } else {
            $existingOrderDetail = \App\Models\Manager41OrderDetail::where('order_id', $order_id)
                ->where('product_id', $product->id)
                ->first();
        }

        if (!$existingOrderDetail) {
            // Create Manager-41 order detail
            $order_detail = new \App\Models\Manager41OrderDetail();
            $order_detail->order_id   = $orderData->id;
            $order_detail->seller_id  = $product->user_id; // matches your original
            $order_detail->product_id = $product->id;
            $order_detail->variation  = $product->slug;
            $order_detail->quantity   = $qty;
            $order_detail->price      = $netUnit * $qty;
            $order_detail->tax        = $tax;
            $order_detail->shipping_cost = 0;
            $order_detail->product_referral_code = null;
            $order_detail->cash_and_carry_item   = $product->cash_and_carry_item;
            $order_detail->save();

            // Update Manager-41 order grand total
            $orderData->grand_total = (float) $orderData->grand_total + ($netUnit * $qty);
            $orderData->save();

            // If adding into a Manager-41 sub-order, also create its detail
            if ($subOrderData) {
                $sub_order_detail = new \App\Models\Manager41SubOrderDetail();
                $sub_order_detail->order_id          = $subOrderData->order_id;
                $sub_order_detail->sub_order_id      = $sub_order_id;
                $sub_order_detail->seller_id         = $product->user_id;
                $sub_order_detail->product_id        = $product->id;
                $sub_order_detail->variation         = $product->slug;
                $sub_order_detail->new_item          = '1';
                $sub_order_detail->type              = $subOrderData->type;
                $sub_order_detail->order_details_id  = $order_detail->id;
                $sub_order_detail->warehouse_id      = $subOrderData->warehouse_id;
                $sub_order_detail->quantity          = $qty;
                $sub_order_detail->approved_quantity = $qty;
                $sub_order_detail->approved_rate     = $netUnit;
                $sub_order_detail->price             = $netUnit * $qty;
                $sub_order_detail->tax               = $tax;
                $sub_order_detail->shipping_cost     = 0;
                $sub_order_detail->cash_and_carry_item = $product->cash_and_carry_item;
                $sub_order_detail->save();

                // Update Manager-41 sub order grand total
                $subOrderData->grand_total = (float) $subOrderData->grand_total + ($netUnit * $qty);
                $subOrderData->save();
            }
        } else {
            // Update / (re)create Manager-41 order detail for this product
            $order_detail = \App\Models\Manager41OrderDetail::where([
                'order_id' => $orderData->id,
                'product_id' => $product->id,
            ])->first();

            if (!$order_detail) {
                $order_detail = new \App\Models\Manager41OrderDetail();
                $order_detail->order_id   = $orderData->id;
                $order_detail->product_id = $product->id;
            }

            $order_detail->seller_id  = $product->user_id;
            $order_detail->variation  = $product->slug;
            $order_detail->quantity   = $qty;
            $order_detail->price      = $netUnit * $qty;
            $order_detail->tax        = $tax;
            $order_detail->shipping_cost = 0;
            $order_detail->product_referral_code = null;
            $order_detail->cash_and_carry_item   = $product->cash_and_carry_item;
            $order_detail->save();

            if ($subOrderData) {
                $sub_order_detail = \App\Models\Manager41SubOrderDetail::where([
                        'sub_order_id' => $sub_order_id,
                        'product_id'   => $product->id,
                    ])->first();

                if (!$sub_order_detail) {
                    $sub_order_detail = new \App\Models\Manager41SubOrderDetail();
                    $sub_order_detail->order_id     = $subOrderData->order_id;
                    $sub_order_detail->sub_order_id = $sub_order_id;
                    $sub_order_detail->product_id   = $product->id;
                }

                $sub_order_detail->seller_id         = $product->user_id;
                $sub_order_detail->variation         = $product->slug;
                $sub_order_detail->new_item          = '1';
                $sub_order_detail->type              = $subOrderData->type;
                $sub_order_detail->order_details_id  = $order_detail->id;
                $sub_order_detail->warehouse_id      = $subOrderData->warehouse_id;
                $sub_order_detail->quantity          = $qty;
                $sub_order_detail->approved_quantity = $qty;
                $sub_order_detail->approved_rate     = $netUnit;
                $sub_order_detail->price             = $netUnit * $qty;
                $sub_order_detail->tax               = $tax;
                $sub_order_detail->shipping_cost     = 0;
                $sub_order_detail->cash_and_carry_item = $product->cash_and_carry_item;
                $sub_order_detail->save();

                $subOrderData->grand_total = (float) $subOrderData->grand_total + ($netUnit * $qty);
                $subOrderData->save();
            }

            // Also bump the order total the same way as in create path
            $orderData->grand_total = (float) $orderData->grand_total + ($netUnit * $qty);
            $orderData->save();
        }

        return [
            'status'     => $sub_order_id,
            'cart_count' => count($carts),
            'modal_view' => view('frontend.partials.addedToOrder', compact('product', 'data'))->render(),
        ];

    } catch (\Throwable $e) {
        return ['error' => $e->getMessage()];
    }
}


    public function _addProductToSplitOrder(Request $request) {

        // Manager-41 login par yahi branch chalegi
        if ($this->isActingAs41Manager()) {
            return $this->manager41AddProductToSplitOrder($request);
        }
        try{
            $order_id = "";
            $sub_order_id = "";
            if($request->order_id != ""){
                $encryptedId = (string) $request->order_id;
                $order_id = decrypt($encryptedId);
                // $order_id = decrypt($request->order_id);
            }            
            if($request->sub_order_id != ""){
                $encryptedId = (string) $request->sub_order_id;
                $sub_order_id= decrypt($encryptedId);
                // $sub_order_id = decrypt($request->sub_order_id); 
            }

            // echo $sub_order_id; die;
            
            $subOrderData = "";
            if($sub_order_id != ""){
                $subOrderData = SubOrder::where('id',$sub_order_id)->first();
                $order_id = $subOrderData->order_id;
            }           
            $orderData = Order::where('id',$order_id)->first();
            $product = Product::find($request->id);
            $carts   = array();
            $data    = array();

            $data['order_id'] = $orderData->id;
            $data['seller_id'] = $product->seller_id;
            $data['product_id'] = $product->id;
            $data['owner_id']   = $product->user_id;

            $str     = '';
            $tax     = $ctax     = $price     = $carton_price     = 0;
            $wmarkup = 0;

            //check the color enabled or disabled for the product
            if ($request->has('color')) {
                $str = $request['color'];
            }

            if ($product->digital != 1) {
                //Gets all the choice values of customer choice option and generate a string like Black-S-Cotton
                if(is_countable(json_decode(Product::find($request->id)->choice_options))){
                    foreach (json_decode(Product::find($request->id)->choice_options) as $key => $choice) {
                        if ($str != null) {
                            $str .= '_' . str_replace(' ', '', strtolower($request['attribute_id_' . $choice->attribute_id]));
                        } else {
                            $str .= str_replace(' ', '', strtolower($request['attribute_id_' . $choice->attribute_id]));
                        }
                    }
                }        
            }
            $str = $product->slug;
            $data['variation'] = $str;

            $product_stock = $product->stocks->where('variant', $str);
            
            //$price=calculate_discounted_price($product->mrp,false)['net_selling_price'];
            
            $user = User::where('id',$orderData->user_id)->first();
            
            $discount = 0;

            if ($user) {
                $discount = $user->discount;
            }

            if(!is_numeric($discount) || $discount == 0) {
                $discount = 20;
            }

            if($request->type == "piece"){
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
            }elseif($request->type == "bulk"){
                $price = $request->order_by_carton_price;
            }            
            $data['quantity']  = $request['quantity'];
            $data['price']     = price_less_than_50($price,false);
            $data['tax']       = $tax;
            //$data['shipping'] = 0;
            $data['shipping_cost']         = 0;
            $data['product_referral_code'] = null;
            $data['cash_on_delivery']      = $product->cash_on_delivery;
            $data['cash_and_carry_item']      = $product->cash_and_carry_item;
            $data['digital']               = $product->digital;       

            if ($request['quantity'] == null) {
                $data['quantity'] = 1;
            }
            
            if($sub_order_id != ""){
                $getOrderDetails = OrderDetail::where('order_id', $subOrderData->order_id)->where('product_id',$product->id)->first();                
            }else{
                $getOrderDetails = OrderDetail::where('order_id', $order_id)->where('product_id',$product->id)->first();
            }
            // echo "<pre>"; print_r($getOrderDetails); die;
            
            if($getOrderDetails == NULL){                
                $order_detail = new OrderDetail;
                $order_detail->order_id = $orderData->id;
                $order_detail->seller_id = $product->user_id;
                $order_detail->product_id = $product->id;
                $order_detail->variation = $product->slug;

                $order_detail->quantity = $request['quantity'];
                $order_detail->price = price_less_than_50($price,false) * $request['quantity'];
                $order_detail->tax = $tax;
                $order_detail->shipping_cost = 0;
                $order_detail->product_referral_code = null;
                $order_detail->cash_and_carry_item = $product->cash_and_carry_item;
                $order_detail->save();
                
                $orderData->grand_total = $orderData->grand_total + (price_less_than_50($price,false) * $data['quantity']);
                $orderData->save();

                if($sub_order_id != ""){
                    
                    $sub_order_detail = new SubOrderDetail;
                    $sub_order_detail->order_id = $subOrderData->order_id;
                    $sub_order_detail->sub_order_id = $sub_order_id;
                    $sub_order_detail->seller_id = $product->user_id;
                    $sub_order_detail->product_id = $product->id;
                    $sub_order_detail->variation = $product->slug;
                    $sub_order_detail->new_item = '1';
                    $sub_order_detail->type = $subOrderData->type;
                    $sub_order_detail->order_details_id = $order_detail->id;
                    $sub_order_detail->warehouse_id = $subOrderData->warehouse_id;
                    $sub_order_detail->quantity = $request['quantity'];
                    $sub_order_detail->approved_quantity = $request['quantity'];
                    $sub_order_detail->approved_rate = price_less_than_50($price,false);
                    $sub_order_detail->price = price_less_than_50($price,false) * $request['quantity'];
                    $sub_order_detail->tax = $tax;
                    $sub_order_detail->shipping_cost = 0;
                    $sub_order_detail->cash_and_carry_item = $product->cash_and_carry_item;
                    $sub_order_detail->save();
                    
                    $subOrderData->grand_total = $subOrderData->grand_total + (price_less_than_50($price,false) * $data['quantity']);
                    $subOrderData->save();
                }
            }else{
                $order_detail = OrderDetail::where([
                    'order_id' => $orderData->id,
                    'product_id' => $product->id
                ])->first();

                if (!$order_detail) {
                    $order_detail = new OrderDetail();
                    $order_detail->order_id = $orderData->id;
                    $order_detail->product_id = $product->id;
                }

                $order_detail->seller_id = $product->user_id;
                $order_detail->variation = $product->slug;
                $order_detail->quantity = $request['quantity'];
                $order_detail->price = price_less_than_50($price,false) * $request['quantity'];
                $order_detail->tax = $tax;
                $order_detail->shipping_cost = 0;
                $order_detail->product_referral_code = null;
                $order_detail->cash_and_carry_item = $product->cash_and_carry_item;

                $order_detail->save();

                if($sub_order_id != ""){
                    
                    if ($sub_order_id != "") {
                        $sub_order_detail = SubOrderDetail::where([
                            'sub_order_id' => $sub_order_id,
                            'product_id'   => $product->id
                        ])->first();

                        if (!$sub_order_detail) {
                            $sub_order_detail = new SubOrderDetail();
                            $sub_order_detail->order_id = $subOrderData->order_id;
                            $sub_order_detail->sub_order_id = $sub_order_id;
                            $sub_order_detail->product_id = $product->id;
                        }

                        $sub_order_detail->seller_id = $product->user_id;
                        $sub_order_detail->variation = $product->slug;
                        $sub_order_detail->new_item = '1';
                        $sub_order_detail->type = $subOrderData->type;
                        $sub_order_detail->order_details_id = $order_detail->id;
                        $sub_order_detail->warehouse_id = $subOrderData->warehouse_id;
                        $sub_order_detail->quantity = $request['quantity'];
                        $sub_order_detail->approved_quantity = $request['quantity'];
                        $sub_order_detail->approved_rate = price_less_than_50($price,false);
                        $sub_order_detail->price = price_less_than_50($price,false) * $request['quantity'];
                        $sub_order_detail->tax = $tax;
                        $sub_order_detail->shipping_cost = 0;
                        $sub_order_detail->cash_and_carry_item = $product->cash_and_carry_item;
                        $sub_order_detail->save();

                        $subOrderData->grand_total = $subOrderData->grand_total + (price_less_than_50($price,false) * $data['quantity']);
                        $subOrderData->save();
                    }
                }
            }
            return array(
                'status'        => $sub_order_id,
                'cart_count'    => count($carts),
                'modal_view'    => view('frontend.partials.addedToOrder', compact('product', 'data'))->render(),
            );

        } catch (\Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
    public function addProductToSplitOrder(Request $request) {

    // Manager-41 login par yahi branch chalegi
    if ($this->isActingAs41Manager()) {
        return $this->manager41AddProductToSplitOrder($request);
    }

    try {
        $order_id = "";
        $sub_order_id = "";

        if ($request->order_id != "") {
            $encryptedId = (string) $request->order_id;
            $order_id = decrypt($encryptedId);
        }
        if ($request->sub_order_id != "") {
            $encryptedId = (string) $request->sub_order_id;
            $sub_order_id = decrypt($encryptedId);
        }

        $subOrderData = "";
        if ($sub_order_id != "") {
            $subOrderData = SubOrder::where('id', $sub_order_id)->first();
            $order_id = $subOrderData->order_id;
        }

        $orderData = Order::where('id', $order_id)->first();
        $product   = Product::find($request->id);

        $carts = [];
        $data  = [];

        $data['order_id']  = $orderData->id;
        $data['seller_id'] = $product->seller_id;
        $data['product_id'] = $product->id;
        $data['owner_id']   = $product->user_id;

        $str     = '';
        $tax     = 0;
        $price   = 0;
        $wmarkup = 0;

        // color / attributes (kept as-is)
        if ($request->has('color')) {
            $str = $request['color'];
        }
        if ($product->digital != 1) {
            if (is_countable(json_decode(Product::find($request->id)->choice_options))) {
                foreach (json_decode(Product::find($request->id)->choice_options) as $key => $choice) {
                    if ($str != null) {
                        $str .= '_' . str_replace(' ', '', strtolower($request['attribute_id_' . $choice->attribute_id]));
                    } else {
                        $str .= str_replace(' ', '', strtolower($request['attribute_id_' . $choice->attribute_id]));
                    }
                }
            }
        }

        // final variation uses slug (kept as-is)
        $str = $product->slug;
        $data['variation'] = $str;

        $product_stock = $product->stocks->where('variant', $str);

        // user discount
        $user = User::where('id', $orderData->user_id)->first();
        $discount = 0;
        if ($user) $discount = $user->discount;
        if (!is_numeric($discount) || $discount == 0) {
            $discount = 20;
        }

        // price calc
        if ($request->type == "piece") {
            $product_mrp = Product::where('id', $product->id)->select('mrp')->first();
            $price = $product_mrp ? $product_mrp->mrp : 0;
            if (!is_numeric($price)) $price = 0;
            $price = $price * ((100 - $discount) / 100);
        } elseif ($request->type == "bulk") {
            $price = $request->order_by_carton_price;
        }

        $qty = (int) ($request['quantity'] ?? 1);
        if ($qty <= 0) $qty = 1;

        $netUnit = price_less_than_50($price, false);

        $data['quantity']  = $qty;
        $data['price']     = $netUnit;
        $data['tax']       = $tax;
        $data['shipping_cost']         = 0;
        $data['product_referral_code'] = null;
        $data['cash_on_delivery']      = $product->cash_on_delivery;
        $data['cash_and_carry_item']   = $product->cash_and_carry_item;
        $data['digital']               = $product->digital;
        if ($request['quantity'] == null) $data['quantity'] = 1;

        // Ensure OrderDetail exists/updated
        if ($sub_order_id != "") {
            $getOrderDetails = OrderDetail::where('order_id', $subOrderData->order_id)
                                ->where('product_id', $product->id)
                                ->first();
        } else {
            $getOrderDetails = OrderDetail::where('order_id', $order_id)
                                ->where('product_id', $product->id)
                                ->first();
        }

        if ($getOrderDetails == NULL) {
            // CREATE order_detail
            $order_detail = new OrderDetail;
            $order_detail->order_id = $orderData->id;
            $order_detail->seller_id = $product->user_id;
            $order_detail->product_id = $product->id;
            $order_detail->variation = $product->slug;
            $order_detail->quantity = $qty;
            $order_detail->price = $netUnit * $qty;
            $order_detail->tax = $tax;
            $order_detail->shipping_cost = 0;
            $order_detail->product_referral_code = null;
            $order_detail->cash_and_carry_item = $product->cash_and_carry_item;
            $order_detail->save();

            // bump main order total
            $orderData->grand_total = $orderData->grand_total + ($netUnit * $qty);
            $orderData->save();

            if ($sub_order_id != "") {
                // CREATE sub_order_detail for current sub_order
                $sub_order_detail = new SubOrderDetail;
                $sub_order_detail->order_id = $subOrderData->order_id;
                $sub_order_detail->sub_order_id = $sub_order_id;
                $sub_order_detail->seller_id = $product->user_id;
                $sub_order_detail->product_id = $product->id;
                $sub_order_detail->variation = $product->slug;
                $sub_order_detail->new_item = '1';
                $sub_order_detail->type = $subOrderData->type; // 'sub_order' | 'btr'
                $sub_order_detail->order_details_id = $order_detail->id;
                $sub_order_detail->warehouse_id = $subOrderData->warehouse_id;
                $sub_order_detail->quantity = $qty;
                $sub_order_detail->approved_quantity = $qty;
                $sub_order_detail->approved_rate = $netUnit;
                $sub_order_detail->price = $netUnit * $qty;
                $sub_order_detail->tax = $tax;
                $sub_order_detail->shipping_cost = 0;
                $sub_order_detail->cash_and_carry_item = $product->cash_and_carry_item;
                $sub_order_detail->save();

                // bump current sub_order total
                $subOrderData->grand_total = $subOrderData->grand_total + ($netUnit * $qty);
                $subOrderData->save();

                /* =========================
                 * NEW: If current sub-order is BTR, mirror into MAIN sub_order
                 * so MAIN shows the replacement with in_transit = qty
                 * ========================= */
                if ($subOrderData->type === 'btr') {
                    $mainSubOrder = SubOrder::where('order_id', $subOrderData->order_id)
                        ->where('type', 'sub_order')
                        ->first();

                    if ($mainSubOrder) {
                        // create/update mirror line on MAIN
                        $mirror = SubOrderDetail::firstOrNew([
                            'sub_order_id'     => $mainSubOrder->id,
                            'order_details_id' => $order_detail->id, // link by order_details_id
                            'product_id'       => $product->id,
                            'type'             => 'sub_order',
                        ]);

                        $mirror->order_id   = $mainSubOrder->order_id;
                        $mirror->seller_id  = $product->user_id;
                        $mirror->variation  = $product->slug;
                        $mirror->new_item   = '1';
                        $mirror->warehouse_id = $mainSubOrder->warehouse_id;

                        // bump numbers (existing + new qty)
                        $mirror->approved_rate     = $netUnit;
                        $mirror->approved_quantity = ((int) ($mirror->approved_quantity ?? 0)) + $qty;
                        $mirror->quantity          = ((int) ($mirror->quantity ?? 0)) + $qty;
                        $mirror->in_transit        = ((int) ($mirror->in_transit ?? 0)) + $qty; // ★ key for BTR pipeline
                        $mirror->price             = $mirror->approved_quantity * $netUnit;
                        $mirror->tax               = $tax;
                        $mirror->shipping_cost     = 0;

                        $mirror->save();

                        // IMPORTANT:
                        // We intentionally DO NOT update $mainSubOrder->grand_total here
                        // to avoid double-counting with the BTR sub_order. If you want totals
                        // visible on MAIN card, uncomment the next two lines:
                        // $mainSubOrder->grand_total = $mainSubOrder->grand_total + ($netUnit * $qty);
                        // $mainSubOrder->save();
                    }
                }
                /* ===== /NEW BTR→MAIN mirror ===== */
            }

        } else {
            // UPDATE or CREATE order_detail then sub_order_detail
            $order_detail = OrderDetail::where([
                'order_id'   => $orderData->id,
                'product_id' => $product->id
            ])->first();

            if (!$order_detail) {
                $order_detail = new OrderDetail();
                $order_detail->order_id = $orderData->id;
                $order_detail->product_id = $product->id;
            }

            $order_detail->seller_id = $product->user_id;
            $order_detail->variation = $product->slug;
            $order_detail->quantity  = $qty;
            $order_detail->price     = $netUnit * $qty;
            $order_detail->tax       = $tax;
            $order_detail->shipping_cost = 0;
            $order_detail->product_referral_code = null;
            $order_detail->cash_and_carry_item   = $product->cash_and_carry_item;
            $order_detail->save();

            if ($sub_order_id != "") {
                $sub_order_detail = SubOrderDetail::where([
                    'sub_order_id' => $sub_order_id,
                    'product_id'   => $product->id
                ])->first();

                if (!$sub_order_detail) {
                    $sub_order_detail = new SubOrderDetail();
                    $sub_order_detail->order_id = $subOrderData->order_id;
                    $sub_order_detail->sub_order_id = $sub_order_id;
                    $sub_order_detail->product_id = $product->id;
                }

                $sub_order_detail->seller_id = $product->user_id;
                $sub_order_detail->variation = $product->slug;
                $sub_order_detail->new_item  = '1';
                $sub_order_detail->type      = $subOrderData->type;
                $sub_order_detail->order_details_id = $order_detail->id;
                $sub_order_detail->warehouse_id = $subOrderData->warehouse_id;
                $sub_order_detail->quantity  = $qty;
                $sub_order_detail->approved_quantity = $qty;
                $sub_order_detail->approved_rate = $netUnit;
                $sub_order_detail->price     = $netUnit * $qty;
                $sub_order_detail->tax       = $tax;
                $sub_order_detail->shipping_cost = 0;
                $sub_order_detail->cash_and_carry_item = $product->cash_and_carry_item;
                $sub_order_detail->save();

                $subOrderData->grand_total = $subOrderData->grand_total + ($netUnit * $data['quantity']);
                $subOrderData->save();

                /* =========================
                 * NEW: If current sub-order is BTR, mirror into MAIN sub_order
                 * ========================= */
                if ($subOrderData->type === 'btr') {
                    $mainSubOrder = SubOrder::where('order_id', $subOrderData->order_id)
                        ->where('type', 'sub_order')
                        ->first();

                    if ($mainSubOrder) {
                        $mirror = SubOrderDetail::firstOrNew([
                            'sub_order_id'     => $mainSubOrder->id,
                            'order_details_id' => $order_detail->id,
                            'product_id'       => $product->id,
                            'type'             => 'sub_order',
                        ]);

                        $mirror->order_id   = $mainSubOrder->order_id;
                        $mirror->seller_id  = $product->user_id;
                        $mirror->variation  = $product->slug;
                        $mirror->new_item   = '1';
                        $mirror->warehouse_id = $mainSubOrder->warehouse_id;

                        $mirror->approved_rate     = $netUnit;
                        $mirror->approved_quantity = ((int) ($mirror->approved_quantity ?? 0)) + $qty;
                        $mirror->quantity          = ((int) ($mirror->quantity ?? 0)) + $qty;
                        $mirror->in_transit        = ((int) ($mirror->in_transit ?? 0)) + $qty;
                        $mirror->price             = $mirror->approved_quantity * $netUnit;
                        $mirror->tax               = $tax;
                        $mirror->shipping_cost     = 0;

                        $mirror->save();

                        // (Optional) main total update – keep disabled to prevent double count
                        // $mainSubOrder->grand_total = $mainSubOrder->grand_total + ($netUnit * $qty);
                        // $mainSubOrder->save();
                    }
                }
                /* ===== /NEW BTR→MAIN mirror ===== */
            }
        }

        return [
            'status'     => $sub_order_id,
            'cart_count' => count($carts),
            'modal_view' => view('frontend.partials.addedToOrder', compact('product', 'data'))->render(),
        ];

    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}


    public function removeProductFromSplitOrder(Request $request) {
        try{
            $getOrderDetails = OrderDetail::where('id',$request->order_details_id)->first();
            if($getOrderDetails != NULL){
                $getOrderDetails->regret_qty = $getOrderDetails->quantity;
                $getOrderDetails->update();
                return response()->json(['msg' => 'Successfully delete this product from order.'], 200);
            }
        } catch (\Exception $e) {
            return array('error' => $e->getMessage());
        }
    }


    public function removeFromCartManager41(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['status' => 'Error', 'message' => 'Login required.'], 401);
            }

            $user_id = (int) $user->id;
            $cartId  = (int) $request->id;

            // Fetch only this user's 41-cart item
            /** @var Cart|null $cartItem */
            $cartItem = Cart::with('product')
                ->where('id', $cartId)
                ->where(function ($q) use ($user_id) {
                    $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                })
                ->where('is_manager_41', 1)
                ->first();

            if (!$cartItem) {
                return response()->json(['status' => 'Error', 'message' => 'Cart item not found.'], 404);
            }

            // ---------- Offer rollback (41-scoped) ----------
            $offer_id = $cartItem->applied_offer_id ?: null;
            if (!empty($offer_id)) {
                $currentDate = now();

                /** @var Offer|null $offers */
                $offers = Offer::with('offerProducts')
                    ->where('status', 1)
                    ->whereDate('offer_validity_start', '<=', $currentDate)
                    ->whereDate('offer_validity_end', '>=', $currentDate)
                    ->where('id', $offer_id)
                    ->first();

                if ($offers) {
                    $offerProducts = $offers->offerProducts ?? collect();

                    foreach ($offerProducts as $op) {
                        // Find corresponding product in THIS user's 41 cart
                        $cartProduct = Cart::where(function ($q) use ($user_id) {
                                $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                            })
                            ->where('is_manager_41', 1)
                            ->where('product_id', $op->product_id)
                            ->first();

                        if ($cartProduct) {
                            // Manager-41 base price: mrp_41_price (no discounts); fallback to mrp
                            $p = Product::find($op->product_id);
                            if ($p) {
                                $base = $p->mrp_41_price ?? Product::where('id', $p->id)->value('mrp_41_price');
                                if (!is_numeric($base) || (float)$base <= 0) {
                                    $base = $p->mrp ?? Product::where('id', $p->id)->value('mrp');
                                }
                                $cartProduct->applied_offer_id = null;
                                $cartProduct->price            = (float) $base;
                                $cartProduct->save();
                            }
                        }
                    }

                    // Delete complementary items created by this offer (41-only)
                    Cart::where(function ($q) use ($user_id) {
                            $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                        })
                        ->where('is_manager_41', 1)
                        ->where('applied_offer_id', $offer_id)
                        ->where('complementary_item', '1')
                        ->delete();
                }
            }

            // Remove the requested cart row (41-only)
            Cart::destroy($cartId);

            // ---------- Rebuild 41-only cart collections ----------
            $carts = Cart::where(function ($q) use ($user_id) {
                        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
                    })
                    ->where('is_manager_41', 1)
                    ->get();

            // -------- Optional: compute Due/Overdue (kept same as your original) --------
            $overdueAmount = 0;
            $dueAmount     = 0;

            try {
                $currentMonth = date('m');
                $currentYear  = date('Y');
                $userData     = User::find($user_id);

                if ($userData) {
                    $userAddressData = Address::where('acc_code', '!=', '')
                        ->where('user_id', $userData->id)
                        ->groupBy('gstin')
                        ->orderBy('acc_code', 'ASC')
                        ->get();

                    foreach ($userAddressData as $value) {
                        $party_code = $value->acc_code;

                        if ($currentMonth >= 4) {
                            $fy_form_date = date('Y-04-01');
                            $fy_to_date   = date('Y-03-31', strtotime('+1 year'));
                        } else {
                            $fy_form_date = date('Y-04-01', strtotime('-1 year'));
                            $fy_to_date   = date('Y-03-31');
                        }

                        $headers = ['authtoken' => '65d448afc6f6b'];
                        $body    = ['party_code' => $party_code, 'from_date' => $fy_form_date, 'to_date' => $fy_to_date];

                        $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
                        \Log::info('Received response from Salzing API For Sync Statement Overdue Calculation (41 remove)', [
                            'status' => $response->status(),
                            'party_code' => $party_code,
                            'body' => $response->body()
                        ]);

                        if ($response->successful()) {
                            $json = $response->json();
                            if (!empty($json['data'])) {
                                $data = $json['data'];
                                $closingBalanceResult = array_filter($data, fn($e) => isset($e['ledgername']) && $e['ledgername'] === 'closing C/f...');
                                $closingEntry = reset($closingBalanceResult);
                                $cloasingCrAmount = $closingEntry['cramount'] ?? 0;

                                $drBalanceBeforeOVDate = 0;
                                $crBalanceBeforeOVDate = 0;
                                $drBalance = 0;
                                $crBalance = 0;

                                $overdueDateFrom = date('Y-m-d', strtotime('-' . (int)($userData->credit_days ?? 0) . ' days'));

                                $data = array_reverse($data);
                                foreach ($data as $gValue) {
                                    if (($gValue['ledgername'] ?? '') !== 'closing C/f...') {
                                        if (strtotime($gValue['trn_date']) > strtotime($overdueDateFrom)) {
                                            $crBalanceBeforeOVDate += $gValue['cramount'];
                                        } else {
                                            $drBalanceBeforeOVDate += $gValue['dramount'];
                                            $crBalanceBeforeOVDate += $gValue['cramount'];
                                        }
                                    }
                                    if ($gValue['ledgername'] !== 'closing C/f...') {
                                        if ($gValue['dramount'] != 0.00) { $drBalance += $gValue['dramount']; $dueAmount += $gValue['dramount']; }
                                        if ($gValue['cramount'] != 0.00) { $crBalance += $gValue['cramount']; $dueAmount -= $gValue['cramount']; }
                                    }
                                }

                                if ($cloasingCrAmount > 0) {
                                    $overdueAmount += ($drBalanceBeforeOVDate - $crBalanceBeforeOVDate);
                                }
                            }
                        }
                    }
                }

                if ($overdueAmount < 0) $overdueAmount = 0;
                session(['overdueAmount' => $overdueAmount, 'dueAmount' => $dueAmount]);
            } catch (\Throwable $te) {
                \Log::warning('Overdue/Due calc failed in removeFromCartManager41: ' . $te->getMessage());
            }

            // ----- Offer tags (kept same conditional as your code) -----
            $validOffers = [];
            $achiveOfferArray = [];
            if ($user_id === 24185 && method_exists($this, 'addOfferTag')) {
                $carts = $this->addOfferTag($carts);
                if (method_exists($this, 'checkValidOffer')) {
                    $tmp = $this->checkValidOffer();
                    $validOffers      = $tmp['offers'] ?? [];
                    $achiveOfferArray = $tmp['achiveOfferArray'] ?? [];
                }
            }

            return [
                'cart_count'    => count($carts),
                'cart_view'     => view('frontend.partials.cart_details', compact('carts'))->render(),
                'nav_cart_view' => view('frontend.partials.cart')->render(),
                'html'          => view('frontend.partials.cartSummary', compact('carts', 'overdueAmount', 'dueAmount', 'validOffers', 'achiveOfferArray'))->render(),
            ];
        } catch (\Exception $e) {
            \Log::error('removeFromCartManager41 error: ' . $e->getMessage());
            return response()->json(['status' => 'Error', 'message' => 'Unexpected error: ' . $e->getMessage()], 500);
        }
    }


    //removes from Cart
    public function removeFromCart(Request $request) {

        if (method_exists($this, 'isActingAs41Manager') && $this->isActingAs41Manager()) {
            return $this->removeFromCartManager41($request);
        }
        $cartItem = Cart::where('id',$request->id)->first();
        $offer_id = NULL;
        if(isset($cartItem->applied_offer_id)){
            $offer_id = $cartItem->applied_offer_id;
        }
        

        if($offer_id != NULL OR $offer_id != ""){
            $currentDate = now();
            $offers = Offer::with('offerProducts')
                ->where('status', 1)
                ->whereDate('offer_validity_start', '<=', $currentDate)
                ->whereDate('offer_validity_end', '>=', $currentDate)
                ->where('id', $offer_id)->first();
            $offerProducts = $offers->offerProducts;            
            foreach($offerProducts as $opKey=>$opValue){
                $cartProduct = Cart::where(function ($query) use ($opValue) {
                    $query->where('user_id', Auth::user()->id)
                            ->orWhere('customer_id', Auth::user()->id);
                })->where('product_id', $opValue->product_id)->first();
                
                $product = Product::where('id',$opValue->product_id)->first();
                $price = $product->mrp * ((100 - Auth::user()->discount) / 100);
                $cartProduct->applied_offer_id = null;
                $cartProduct->price = $price;
                $cartProduct->save();
            }
            $cartProduct = Cart::where(function ($query) use ($opValue) {
                $query->where('user_id', Auth::user()->id)
                        ->orWhere('customer_id', Auth::user()->id);
            })->where('applied_offer_id', $offer_id)->where('complementary_item', '1')->delete();
        }

        Cart::destroy($request->id);

        if (auth()->user() != null) {
        $user_id = Auth::user()->id;
        $carts   = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
        } else {
        $temp_user_id = $request->session()->get('temp_user_id');
        $carts        = Cart::where('temp_user_id', $temp_user_id)->get();
        }

        // --------------------------------- Calculate Due and Overdue amount --------------------------------------
        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        $overdueDateFrom="";
        $overdueAmount="0";

        $openingBalance="0";
        $drBalance = 0;
        $crBalance = 0;
        $dueAmount = 0;

        $userData = User::where('id', $user_id)->first();
        $userAddressData = Address::where('acc_code',"!=","")->where('user_id',$userData->id)->groupBy('gstin')->orderBy('acc_code','ASC')->get();
        foreach($userAddressData as $key=>$value){
            $party_code = $value->acc_code;
            if ($currentMonth >= 4) {
                $fy_form_date = date('Y-04-01'); // Start of financial year
                $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
            } else {
                $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
                $fy_to_date = date('Y-03-31'); // Current year March
            }
            $from_date = $fy_form_date;
            $to_date = $fy_to_date;
            $headers = [
                'authtoken' => '65d448afc6f6b',
            ];
            $body = [
                'party_code' => $party_code,
                'from_date' => $from_date,
                'to_date' =>  $to_date,
            ];
            $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);
            \Log::info('Received response from Salzing API For Sync Statement Overdue Calculation', [
                'status' => $response->status(),
                'party_code' =>  $party_code,
                'body' => $response->body()
            ]);
            if ($response->successful()) {
                $getData = $response->json();
                if(!empty($getData) AND isset($getData['data']) AND !empty($getData['data'])){
                    $getData = $getData['data'];
                    $closingBalanceResult = array_filter($getData, function ($entry) {
                        return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
                    });
                    $closingEntry = reset($closingBalanceResult);
                    $cloasingDrAmount = $closingEntry['dramount'];
                    $cloasingCrAmount = $closingEntry['cramount'];
                    $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
                    if($cloasingCrAmount > 0){
                        $drBalanceBeforeOVDate = 0;
                        $crBalanceBeforeOVDate = 0;
                        $getData = array_reverse($getData);
                        foreach($getData as $ovKey=>$gValue){
                            if($gValue['ledgername'] != 'closing C/f...'){
                                if(strtotime($gValue['trn_date']) > strtotime($overdueDateFrom)){
                                    // $drBalanceBeforeOVDate += $ovValue['dramount'];
                                    $crBalanceBeforeOVDate += $gValue['cramount'];
                                }else{
                                    $drBalanceBeforeOVDate += $gValue['dramount'];
                                    $crBalanceBeforeOVDate += $gValue['cramount'];
                                }
                            }
                            if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                                $drBalance = $drBalance + $gValue['dramount'];
                                $dueAmount = $dueAmount + $gValue['dramount'];
                            } 
                            if($gValue['cramount'] != '0.00' AND $gValue['ledgername'] != 'closing C/f...') {
                                $crBalance = $crBalance + $gValue['cramount'];
                                $dueAmount = $dueAmount - $gValue['cramount'];
                            }
                        }
                        $overdueAmount = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                    }
                }
            }
        }
        if($overdueAmount <= 0){
            $overdueAmount = 0;
        }
        // $overdueAmount = ceil($overdueAmount);
        // $dueAmount = ceil($dueAmount);
        $overdueAmount = $overdueAmount;
        $dueAmount = $dueAmount;
        session(['overdueAmount' => $overdueAmount]);
        session(['dueAmount' => $dueAmount]);
        // --------------------------------- Calculate Due and Overdue amount --------------------------------------

        // Offer Section 
        // if(Auth::user()->id == '24185'){
            $carts = $this->addOfferTag($carts);
            $validOffersTemp = $this->checkValidOffer();
            $validOffers = $validOffersTemp['offers'] ?? [];
            $achiveOfferArray = $validOffersTemp['achiveOfferArray'] ?? [];
        // }

        return array(
        'cart_count'    => count($carts),
        'cart_view'     => view('frontend.partials.cart_details', compact('carts'))->render(),
        'nav_cart_view' => view('frontend.partials.cart')->render(),
        'html' =>  view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount','validOffers','achiveOfferArray'))->render(),
        );
    }

    //updated the quantity for a cart item
    public function updateQuantity(Request $request) {
        $cartItem = Cart::findOrFail($request->id);

        if ($cartItem['id'] == $request->id) {
        $product       = Product::find($cartItem['product_id']);
        $product_stock = $product->stocks->where('variant', $cartItem['variation'])->first();
        $quantity      = $product_stock->qty;
        $price         = $product_stock->price;

        //discount calculation
        $discount_applicable = false;

        if ($product->discount_start_date == null) {
            $discount_applicable = true;
        } elseif (strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
            strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date) {
            $discount_applicable = true;
        }

        if ($discount_applicable) {
            if ($product->discount_type == 'percent') {
            $price -= ($price * $product->discount) / 100;
            } elseif ($product->discount_type == 'amount') {
            $price -= $product->discount;
            }
        }

        if ($quantity >= $request->quantity) {
            if ($request->quantity >= $product->min_qty) {
            $cartItem['quantity'] = $request->quantity;
            }
        }

        if ($product->wholesale_product) {
            $wholesalePrice = $product_stock->wholesalePrices->where('min_qty', '<=', $request->quantity)->where('max_qty', '>=', $request->quantity)->first();
            if ($wholesalePrice) {
            $price = $wholesalePrice->price;
            }
        }

        $cartItem['price'] = $price;
        $cartItem->save();
        }

        if (auth()->user() != null) {
        $user_id = Auth::user()->id;
        $carts   = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
        } else {
        $temp_user_id = $request->session()->get('temp_user_id');
        $carts        = Cart::where('temp_user_id', $temp_user_id)->get();
        }

        return array(
        'cart_count'    => count($carts),
        'cart_view'     => view('frontend.partials.cart_details', compact('carts'))->render(),
        'nav_cart_view' => view('frontend.partials.cart')->render(),
        );
    }
    
    public function updateQuantityV02Manager41(Request $request)
    {
        // Only operate on Manager-41 items belonging to this user
        $user_id  = Auth::id();

        $cartItem = Cart::where('id', $request->id)
            ->where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)
                ->orWhere('customer_id', $user_id);
            })
            ->where('is_manager_41', 1)
            ->firstOrFail();

        $quantity = (int) $request->quantity;
        $product  = Product::findOrFail($cartItem->product_id);

        // Base price calculation (same logic as non-41)
        if ($quantity >= (int)($product->piece_by_carton ?? 0)) {
            $price = home_bulk_discounted_price($product, false)['price'];
        } else {
            $price = home_discounted_price($product, false)['price'];
        }

        // Staff override (allow fixed price for specific staff IDs incl. 27604 for 41 flows)
        if (
            session()->has('staff_id') &&
            in_array((string) session('staff_id'), ['180', '169', '25606', '27604'], true)
        ) {
            $price = $cartItem->price;
        }

        // -------- Offer handling (kept consistent with your non-41 flow) --------
        if (Auth::id() == 24185) {
            if ($cartItem->id == $request->id) {
                // If an offer is applied on this line
                if (!empty($cartItem['applied_offer_id'])) {
                    $currentDate  = now();
                    $appliedOffer = Offer::with('offerProducts')
                        ->where('status', 1)
                        ->whereDate('offer_validity_start', '<=', $currentDate)
                        ->whereDate('offer_validity_end', '>=', $currentDate)
                        ->where('id', $cartItem['applied_offer_id'])
                        ->first();

                    if ($appliedOffer !== null) {
                        $offerProducts = $appliedOffer->offerProducts;

                        foreach ($offerProducts as $opValue) {
                            // Type 2: cart-value based (as in your code)
                            if ($appliedOffer->offer_type == 2) {
                                // Pull only Manager-41 items in the same offer
                                $cartItems = Cart::where(function ($q) use ($user_id) {
                                        $q->where('user_id', $user_id)
                                        ->orWhere('customer_id', $user_id);
                                    })
                                    ->where('is_manager_41', 1)
                                    ->where('applied_offer_id', $cartItem['applied_offer_id'])
                                    ->get(['product_id', 'quantity', 'price'])
                                    ->groupBy('product_id');

                                // Quantities by product
                                $cartQuantities = $cartItems->mapWithKeys(function ($items, $productId) {
                                    return [$productId => $items->sum('quantity')];
                                })->toArray();

                                // Recompute cart total using bulk / retail price depending on qty
                                $c_offer_price_calculate = 0;
                                foreach ($cartQuantities as $pId => $qty) {
                                    $cProduct = Product::find($pId);
                                    if (!$cProduct) continue;

                                    $cPrice =
                                        $qty >= (int)($cProduct->piece_by_carton ?? 0)
                                            ? home_bulk_discounted_price($cProduct, false)['price']
                                            : home_discounted_price($cProduct, false)['price'];

                                    // For the line being updated, use the new requested $quantity
                                    if ($pId == $product->id) {
                                        $c_offer_price_calculate += $cPrice * $quantity;
                                    } else {
                                        $c_offer_price_calculate += $cPrice * $qty;
                                    }
                                }

                                if ($cartItems->isNotEmpty()) {
                                    if ($appliedOffer->offer_value > $c_offer_price_calculate) {
                                        // Break the offer
                                        $this->removeOfferFromTable($cartItem['applied_offer_id']);
                                        $cartItem->applied_offer_id = null;
                                        $cartItem->price     = $price;
                                        $cartItem->quantity  = $quantity;
                                        $cartItem->save();
                                    } else {
                                        // Keep offer, only update qty
                                        $cartItem->quantity = $quantity;
                                        $cartItem->save();
                                    }
                                } else {
                                    // Fallback: no items? keep computed price
                                    $cartItem->price    = $price;
                                    $cartItem->quantity = $quantity;
                                    $cartItem->save();
                                }
                            } else {
                                // Product / quantity based offers
                                if ($quantity < $opValue->min_qty && $opValue->product_id == $product->id) {
                                    $this->removeOfferFromTable($cartItem['applied_offer_id']);
                                    $cartItem->quantity        = $quantity;
                                    $cartItem->applied_offer_id = null;
                                    $cartItem->price           = $price;
                                    $cartItem->save();
                                } else {
                                    $cartItem->quantity = $quantity;
                                    $cartItem->save();
                                }
                            }
                        }
                    } else {
                        // Offer no longer valid: remove offer from manager-41 items using that offer
                        $offerId = $cartItem['applied_offer_id'];

                        Cart::where(function ($q) use ($user_id) {
                                $q->where('user_id', $user_id)
                                ->orWhere('customer_id', $user_id);
                            })
                            ->where('is_manager_41', 1)
                            ->where('applied_offer_id', $offerId)
                            ->update([
                                'applied_offer_id' => null,
                                'price'            => $price,  // revert line price
                                'quantity'         => $quantity,
                            ]);

                        // Drop complementary items tied to that offer
                        Cart::where(function ($q) use ($user_id) {
                                $q->where('user_id', $user_id)
                                ->orWhere('customer_id', $user_id);
                            })
                            ->where('is_manager_41', 1)
                            ->where('applied_offer_id', $offerId)
                            ->where('complementary_item', '1')
                            ->delete();
                    }
                } else {
                    // Different line id (defensive; should not hit)
                    $cartItem->quantity = $quantity;
                    $cartItem->price    = $price;
                    $cartItem->save();
                }
            }
        } else {
            // Regular user (not 24185)
            $cartItem->quantity = $quantity;
            $cartItem->price    = $price;
            $cartItem->save();
        }

        // -------- Rebuild Manager-41-only views/totals --------
        $cartsForSum = Cart::where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
            })
            ->where('is_manager_41', 1)
            ->selectRaw('*, price * quantity as subtotal')
            ->get();

        $totalSubtotal = $cartsForSum->sum('subtotal');

        $overdueAmount = $request->overdueAmount;
        $dueAmount     = $request->dueAmount;

        $carts = Cart::where(function ($q) use ($user_id) {
                $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
            })
            ->where('is_manager_41', 1)
            ->get();

        // Offer ribbons etc. (same behavior you use elsewhere)
        $validOffers      = [];
        $achiveOfferArray = [];
        if (Auth::id() == 24185) {
            $carts             = $this->addOfferTag($carts);
            $validOffersTemp   = $this->checkValidOffer();
            $validOffers       = $validOffersTemp['offers'] ?? [];
            $achiveOfferArray  = $validOffersTemp['achiveOfferArray'] ?? [];
        }

        // Return the same payload keys as your non-41 method,
        // but based strictly on Manager-41 items.
        return [
            'item_sub_total' => format_price_in_rs($cartItem['price'] * $quantity),
            'span_sub_total' => format_price_in_rs($totalSubtotal),
            'price'          => format_price_in_rs($price),
            'update_price'   => $price,

            // Uses your existing summary/bill partials (they work with any $carts)
            'cart_summary'   => view('frontend.partials.cart_bill_amount_v02', compact(
                'overdueAmount', 'dueAmount', 'validOffers', 'achiveOfferArray'
            ))->render(),

            'nav_cart_view'  => view('frontend.partials.cart')->render(),

            // If you have a 41-specific summary partial, swap it here.
            // Keeping the default since that’s what your non-41 version uses.
            'html'           => view('frontend.partials.cartSummary', compact(
                'carts', 'overdueAmount', 'dueAmount', 'validOffers', 'achiveOfferArray'
            ))->render(),
        ];
    }

    // public function updateQuantityV02Manager41(Request $request){
        
    //     // find the item only inside the Manager-41 slice
    //     $cartItem = Cart::where('id', $request->id)
    //         ->where('is_manager_41', 1)
    //         ->firstOrFail();
    
    //     $quantity = (int) $request->quantity;
    //     $product  = Product::find($cartItem->product_id);
    
    //     // price calc (bulk vs regular)
    //     if (!empty($product->piece_by_carton) && $quantity >= (int) $product->piece_by_carton) {
    //         $price = home_bulk_discounted_price($product, false)['price'];
    //     } else {
    //         $price = home_discounted_price($product, false)['price'];
    //     }
    
    //     // staff override
    //     if (session()->has('staff_id') && in_array(session()->get('staff_id'), [180, 169, 25606])) {
    //         $price = $cartItem->price;
    //     }
    
    //     // ----- Offer logic (kept same semantics) -----
    //     if (Auth::user()->id == '24185') {
    //         if (!empty($cartItem['id']) && $cartItem['id'] == $request->id) {
    
    //             // If offer already applied on this row
    //             if (!empty($cartItem['applied_offer_id'])) {
    
    //                 $currentDate = now();
    //                 $appliedOffer = Offer::with('offerProducts')
    //                     ->where('status', 1)
    //                     ->whereDate('offer_validity_start', '<=', $currentDate)
    //                     ->whereDate('offer_validity_end', '>=', $currentDate)
    //                     ->where('id', $cartItem['applied_offer_id'])
    //                     ->first();
    
    //                 if ($appliedOffer) {
    
    //                     $offerProducts = $appliedOffer->offerProducts;
    
    //                     foreach ($offerProducts as $opValue) {
    
    //                         // Combo/Bundle type?
    //                         if ($appliedOffer->offer_type == 2) {
    //                             // Pull only Manager-41 items from this user
    //                             $cartItems = Cart::where(function ($q) {
    //                                     $q->where('user_id', Auth::id())
    //                                       ->orWhere('customer_id', Auth::id());
    //                                 })
    //                                 ->where('is_manager_41', 1) // 41 slice only
    //                                 ->where('applied_offer_id', $cartItem['applied_offer_id'])
    //                                 ->get(['product_id', 'quantity', 'price'])
    //                                 ->groupBy('product_id');
    
    //                             $cartQuantities = $cartItems->mapWithKeys(function ($items, $productId) {
    //                                 return [$productId => $items->sum('quantity')];
    //                             })->toArray();
    
    //                             // recompute ?cart price total? with bulk threshold per product
    //                             $c_offer_price_calculate = 0;
    //                             foreach ($cartQuantities as $prodId => $qtyForProd) {
    //                                 $c_product = Product::find($prodId);
    
    //                                 if (!empty($c_product->piece_by_carton) && $qtyForProd >= (int) $c_product->piece_by_carton) {
    //                                     $c_price = home_bulk_discounted_price($c_product, false)['price'];
    //                                 } else {
    //                                     $c_price = home_discounted_price($c_product, false)['price'];
    //                                 }
    
    //                                 // If this is the row being updated, use requested qty
    //                                 if ($prodId == $cartItem->product_id) {
    //                                     $c_offer_price_calculate += $c_price * $quantity;
    //                                 } else {
    //                                     $c_offer_price_calculate += $c_price * $qtyForProd;
    //                                 }
    //                             }
    
    //                             // If below offer threshold => remove the offer
    //                             if ($cartItems->isNotEmpty() && $appliedOffer->offer_value > $c_offer_price_calculate) {
    //                                 $this->removeOfferFromTable($cartItem['applied_offer_id']);
    //                                 $cartItem->applied_offer_id = null;
    //                                 $cartItem->price = $price;
    //                                 $cartItem->quantity = $quantity;
    //                                 $cartItem->save();
    //                             } else {
    //                                 // Still valid: only update quantity
    //                                 $cartItem->quantity = $quantity;
    //                                 $cartItem->save();
    //                             }
    //                         } else {
    //                             // Non-combo offer types
    //                             if (!empty($opValue->min_qty) && $quantity < $opValue->min_qty && $opValue->product_id == $product->id) {
    //                                 // no longer qualifies -> remove offer
    //                                 $this->removeOfferFromTable($cartItem['applied_offer_id']);
    //                                 $cartItem->quantity = $quantity;
    //                                 $cartItem->applied_offer_id = null;
    //                                 $cartItem->price = $price;
    //                                 $cartItem->save();
    //                             } else {
    //                                 // still qualifies
    //                                 $cartItem->quantity = $quantity;
    //                                 $cartItem->save();
    //                             }
    //                         }
    //                     }
    
    //                 } else {
    //                     // Offer not valid anymore: clean all rows that had this offer (only 41 slice)
    //                     $offerId = $cartItem['applied_offer_id'];
    
    //                     Cart::where(function ($q) {
    //                             $q->where('user_id', Auth::id())
    //                               ->orWhere('customer_id', Auth::id());
    //                         })
    //                         ->where('is_manager_41', 1)
    //                         ->where('applied_offer_id', $offerId)
    //                         ->get()
    //                         ->each(function ($r) use ($price, $quantity, $cartItem) {
    //                             // restore row pricing (use per-row product)
    //                             $rowProduct = Product::find($r->product_id);
    //                             $rowPrice = $price;
    
    //                             if (!empty($rowProduct->piece_by_carton) && $r->quantity >= (int) $rowProduct->piece_by_carton) {
    //                                 $rowPrice = home_bulk_discounted_price($rowProduct, false)['price'];
    //                             } else {
    //                                 $rowPrice = home_discounted_price($rowProduct, false)['price'];
    //                             }
    
    //                             $r->applied_offer_id = null;
    //                             if ($r->id == $cartItem->id) {
    //                                 $r->quantity = $quantity;
    //                             }
    //                             $r->price = $rowPrice;
    //                             $r->save();
    //                         });
    
    //                     // remove complementary items tied to this invalid offer in 41 slice
    //                     Cart::where(function ($q) {
    //                             $q->where('user_id', Auth::id())
    //                               ->orWhere('customer_id', Auth::id());
    //                         })
    //                         ->where('is_manager_41', 1)
    //                         ->where('applied_offer_id', $offerId)
    //                         ->where('complementary_item', '1')
    //                         ->delete();
    //                 }
    //             } else {
    //                 // no offer on this row; just update qty + price
    //                 $cartItem->quantity = $quantity;
    //                 $cartItem->price    = $price;
    //                 $cartItem->save();
    //             }
    //         }
    //     } else {
    //         // regular users: no offer logic change
    //         $cartItem->quantity = $quantity;
    //         $cartItem->price    = $price;
    //         $cartItem->save();
    //     }
    
    //     // ----- rebuild Manager-41 cart slice -----
    //     $user_id = Auth::id();
    
    //     $carts41 = Cart::where(function ($q) use ($user_id) {
    //             $q->where('user_id', $user_id)
    //               ->orWhere('customer_id', $user_id);
    //         })
    //         ->where('is_manager_41', 1)
    //         ->selectRaw('*, price * quantity as subtotal')
    //         ->get();
    
    //     $totalSubtotal = $carts41->sum('subtotal');
    
    //     $overdueAmount = $request->overdueAmount;
    //     $dueAmount     = $request->dueAmount;
    
    //     $validOffers = [];
    //     $achiveOfferArray = [];
    //     if (Auth::user()->id == '24185') {
    //         // decorate only the 41 cart
    //         $carts41 = $this->addOfferTag($carts41);
    //         $validOffersTemp   = $this->checkValidOffer();
    //         $validOffers       = $validOffersTemp['offers'] ?? [];
    //         $achiveOfferArray  = $validOffersTemp['achiveOfferArray'] ?? [];
    //     }
    
    //     return [
    //         'item_sub_total' => format_price_in_rs($cartItem['price'] * $quantity),
    //         'span_sub_total' => format_price_in_rs($totalSubtotal),
    //         'price'          => format_price_in_rs($price),
    //         'update_price'   => $price,
    
    //         // bill amount box (same partial, just fed Manager-41 data)
    //         'cart_summary'   => view('frontend.partials.cart_bill_amount_v02',
    //                             compact('overdueAmount','dueAmount','validOffers','achiveOfferArray')
    //                           )->render(),
    
    //         // mini-cart
    //         'nav_cart_view'  => view('frontend.partials.cart')->render(),
    
    //         // main cart summary table (feed only Manager-41 $carts)
    //         'html'           => view('frontend.partials.cartSummary',
    //                             ['carts' => $carts41, 'overdueAmount' => $overdueAmount, 'dueAmount' => $dueAmount, 'validOffers' => $validOffers, 'achiveOfferArray' => $achiveOfferArray]
    //                           )->render(),
    //     ];
    // }

    public function updateQuantityV02(Request $request) {
        
        if ($this->isActingAs41Manager()) {
            return $this->updateQuantityV02Manager41($request);
        }
        
        $cartItem = Cart::findOrFail($request->id);
        $quantity = $request->quantity;
        $product = Product::find($cartItem->product_id);
        if($quantity >= $product->piece_by_carton ){
            $price = home_bulk_discounted_price($product,false)['price'];
        }else{
            // $price = home_discounted_price($product,false)['price'];
            $price = '0.00';
        }
        if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606)){
        // if(Auth::user()->id == '180' OR Auth::user()->id == '169' OR Auth::user()->id == '25606' OR Auth::user()->id == '1'){
            $price = $cartItem->price;
        }
        // if(Auth::id() == '24185'){
            $qty    = (int) $request->quantity;
            $userId = (int) Auth::id();

            if($price == '0.00'){
                // Unit price for this qty (your helper should return a numeric)
                $price = (float) product_price_with_qty_condition($product, $userId, $qty);
            }
            

            // Threshold logic
            $target       = (float) env('SPECIAL_DISCOUNT_AMOUNT', 5000);     // e.g. ₹5,000
            $spPercentage = (float) env('SPECIAL_DISCOUNT_PERCENTAGE', 3);    // kept if you use it later

            $subtotal = $price * $qty;

            $increasePriceText = ($subtotal < $target)
            ? "ALERT : Price increase due to quantity {$qty} pices. For regular price buy {$product->min_qty} or more."
            : "";
            // $increasePriceText = ($subtotal < $target)
            //     ? "Make product's total value ₹{$target} or more and get normal price."
            //     : "";
        // }
        

        // return response()->json([
        //     'price'             => $price,       // unit price
        //     'qty'               => $qty,
        //     'subtotal'          => $subtotal,
        //     'increasePriceText' => $increasePriceText,
        // ]);


        // echo $price; die;
        // if(Auth::user()->id == '24185'){
            if ($cartItem['id'] == $request->id) {
                // If offer already applied  
                if($cartItem['applied_offer_id'] != "" OR $cartItem['applied_offer_id'] != NULL){
                    $currentDate = now();
                    $appliedOffer = Offer::with('offerProducts')
                        ->where('status', 1)
                        ->whereDate('offer_validity_start', '<=', $currentDate)
                        ->whereDate('offer_validity_end', '>=', $currentDate)
                        ->where('id', $cartItem['applied_offer_id'])->first();
                    // If offer is valid then.
                    if ($appliedOffer !== NULL){                        
                        $offerProducts = $appliedOffer->offerProducts;
                        foreach($offerProducts as $opKey=>$opValue){
                            // If valid offer
                            if($appliedOffer->offer_type == 2){
                                
                                $cartItems = Cart::where(function ($query) {
                                    $query->where('user_id', Auth::user()->id)
                                          ->orWhere('customer_id', Auth::user()->id);
                                })
                                ->where('applied_offer_id', $cartItem['applied_offer_id'])
                                ->get(['product_id', 'quantity', 'price'])
                                ->groupBy('product_id');
                
                                // Prepare product IDs and quantities from the cart
                                $cartProductIds = $cartItems->keys()->toArray();                                
                                // Sum quantities for each product
                                $cartQuantities = $cartItems->mapWithKeys(function ($items, $productId) {
                                    return [$productId => $items->sum('quantity')];
                                })->toArray();

                                // Prepare prices for each product (assuming all prices for a given product are the same)
                                $cartItemPrice = $cartItems->mapWithKeys(function ($items, $productId) {
                                    return [$productId => $items->first()->price * $items->sum('quantity')]; // Use the price of the first item in the group
                                })->toArray();

                                // echo "<pre>";print_r($cartItemPrice);die;

                                $c_offer_price_calculate = 0 ;
                                foreach($cartQuantities as $cqKey=>$cqValue){
                                    $c_product = Product::find($cqKey);
                                    if($cqValue >= $product->piece_by_carton ){
                                        $c_price = home_bulk_discounted_price($c_product,false)['price'];
                                    }else{
                                        $c_price = home_discounted_price($c_product,false)['price'];
                                    }
                                    if($cqKey == $request->id){
                                        $c_offer_price_calculate += $c_price * $quantity;
                                    }else{
                                        $c_offer_price_calculate += $c_price * $cqValue;
                                    }                                    
                                }
                                // echo '@'.$appliedOffer->offer_value.'----'.$c_offer_price_calculate;
                                if(!empty($cartItems)){
                                    if($appliedOffer->offer_value > $c_offer_price_calculate){
                                        $this->removeOfferFromTable($cartItem['applied_offer_id']);                                    
                                        $cartItem->applied_offer_id = null;
                                        $cartItem->price = $price;
                                        $cartItem['quantity'] = $request->quantity;
                                        $cartItem->save(); 
                                    }else{
                                        $cartItem['quantity'] = $request->quantity;
                                        $cartItem->save();
                                    }
                                }else{
                                    $cartItem->price = $c_price;
                                    $cartItem['quantity'] = $request->quantity;
                                    $cartItem->save();
                                }
                            }else{
                                if($quantity < $opValue->min_qty AND $opValue->product_id == $product->id){
                                    $this->removeOfferFromTable($cartItem['applied_offer_id']);
                                    $cartItem['quantity'] = $request->quantity;
                                    $cartItem->applied_offer_id = null;
                                    $cartItem->price = $price;
                                    $cartItem->save();                                
                                }else{
                                    $cartItem['quantity'] = $request->quantity;
                                    // $cartItem->applied_offer_id = null;
                                    // if($opValue->discount_type == 'percent'){
                                    //     $price = $price * ((100 - $opValue->offer_discount_percent) / 100);
                                    // }else{
                                    //     $price = $opValue->offer_price;
                                    // }
                                    $cartItem->save(); 
                                } 
                            }                                    
                        }
                    }else{
                        // Offer Didn't get or not valid then remove the offer from all products if already have.
                        $offerProducts = $appliedOffer->offerProducts;            
                        foreach($offerProducts as $opKey=>$opValue){
                            $cartProduct = Cart::where(function ($query) use ($opValue) {
                                $query->where('user_id', Auth::user()->id)
                                    ->orWhere('customer_id', Auth::user()->id);
                            })->where('product_id', $opValue->product_id)->first();
                            
                            // $product = Product::where('id',$opValue->product_id)->first();
                            // $price = $product->mrp * ((100 - Auth::user()->discount) / 100);
                            $cartProduct->quantity = $request->quantity;
                            $cartProduct->applied_offer_id = null;
                            $cartProduct->price = $price;
                            $cartProduct->save();
                        }
                        $cartProduct = Cart::where(function ($query) use ($opValue) {
                            $query->where('user_id', Auth::user()->id)
                                ->orWhere('customer_id', Auth::user()->id);
                        })->where('applied_offer_id', $offer_id)->where('complementary_item', '1')->delete();
                    }              
                }else{
                    // echo "hello2"; die;
                    $cartItem['quantity'] = $request->quantity;
                    $cartItem['price'] = $price;
                    $cartItem->save();
                }
            }
        // }else{
        //     $cartItem['quantity'] = $request->quantity;
        //     $cartItem['price'] = $price;
        //     $cartItem->save();
        // }


        $user_id = Auth::user()->id;
        $carts = Cart::where('user_id', $user_id)->orWhere('customer_id', $user_id)->selectRaw('*, price * quantity as subtotal')->get();
        $totalSubtotal = $carts->sum('subtotal');
        $overdueAmount = $request->overdueAmount;
        $dueAmount = $request->dueAmount;
        
        $carts = Cart::where('user_id', $user_id)->orWhere('customer_id', $user_id)->get();
        $validOffers = array();
        $achiveOfferArray = array();
        // Offer Section 
        // if(Auth::user()->id == '24185'){
            $carts = $this->addOfferTag($carts);
            $validOffersTemp = $this->checkValidOffer();
            $validOffers = $validOffersTemp['offers'] ?? [];
            $achiveOfferArray = $validOffersTemp['achiveOfferArray'] ?? [];
        // }
        // echo "<pre>"; print_r($carts); die;
        return array(
            'item_sub_total'    => format_price_in_rs($cartItem['price'] * $request->quantity),
            'span_sub_total'    => format_price_in_rs($totalSubtotal),
            'price' => format_price_in_rs($price),
            'update_price' => $price,
            'increasePriceText' => $increasePriceText,
            // 'cart_summary'     => view('frontend.partials.cart_bill_amount_v02', compact('total','overdueAmount','cash_and_carry_item_flag','cash_and_carry_item_subtotal','cash_and_carry_item_subtotal','normal_item_flag','normal_item_subtotal'))->render(),
            'cart_summary'     => view('frontend.partials.cart_bill_amount_v02', compact('overdueAmount','dueAmount','validOffers','achiveOfferArray'))->render(),
            'nav_cart_view' => view('frontend.partials.cart')->render(),
            'html' =>  view('frontend.partials.cartSummary', compact('carts','overdueAmount','dueAmount','validOffers','achiveOfferArray'))->render(),
        );
    }
  // public function productDetails($id)
  // {
  //   $cart = Cart::where('id',$id)->with('product')->first();
  //   return [
  //     'cart' => $cart,
  //     'product' => $cart['product'],
  //     'brand' => $cart['product']['brand'],
  //     'category' => $cart['product']['category'],
  //     'stocks' => $cart['product']['stocks']->first(),
  //   ];
  // }

  public function productDetails($id)
  {
    try {
        $cart = Cart::where('id', $id)->with('product')->first();

        if (!$cart) {
            return response()->json(['error' => 'Cart not found'], 404);
        }

        $product = $cart->product;

        if (!$product) {
            return response()->json(['error' => 'Product not found in cart'], 404);
        }

        $brand = $product->brand;
        $category = $product->category;
        $stocks = $product->stocks->first();

        return [
            'cart' => $cart,
            'product' => $product,
            'brand' => $brand,
            'category' => $category,
            'stocks' => $stocks,
        ];
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
  }


  // ABANDONED CART CODE START


  public function sendBulkWhatsApp(Request $request)
  {

    // send whatsapp button
  
      $userIds = $request->input('selected_carts');
      // Make the IDs distinct
      $userIds = array_unique($userIds);
      // echo "<pre>";
      // print_r($userIds);
      // die();

      if ($userIds) {
          foreach ($userIds as $userId) {
            $user = DB::table('users')
            ->join('carts', 'users.id', '=', 'carts.user_id')
            ->select('users.company_name', 'users.phone','users.manager_id')
            ->where('users.id', $userId)
            ->first();

            if (!$user) {
              return response()->json(['error' => 'User not found'], 404);
            }
             // Retrieve manager's information
             $manager = DB::table('users')
                 ->select('name', 'phone')
                 ->where('id', $user->manager_id)
                 ->first();

              // Example of sending WhatsApp messages
              $cartItems = DB::table('carts')
              ->join('products', 'carts.product_id', '=', 'products.id')
              ->select('products.name as product_name', 'carts.quantity')
              ->where('carts.user_id', $userId)
              ->where('carts.is_manager_41', 0)
              ->get();
  
              $itemCount = $cartItems->count();
            
                  $name = $user->company_name;
                 
                  $invoice=new InvoiceController();
                  $randomNumber = rand(1000, 9999); // Generates a random number between 1000 and 9999

                  $file_url=$invoice->invoice_file_path_abandoned_cart($userId,$randomNumber);
                  $file_name="Abandoned Cart";
                  $multipleItems = [
                    'name' => 'utility_abandoned_cart', // Replace with your template name, e.g., 'abandoned_cart_template'
                    'language' => 'en_US', // Replace with your desired language code
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                ['type' => 'document', 'document' => ['link' => $file_url,'filename' => $file_name,]],
                            ],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $name],
                                ['type' => 'text', 'text' => $itemCount], // Second variable (Total Count)
                            ],
                        ],
                    ],
                ];

                 $this->WhatsAppWebService = new WhatsAppWebService();
                 $response = $this->WhatsAppWebService->sendTemplateMessage($user->phone, $multipleItems);
                  $response1 = $this->WhatsAppWebService->sendTemplateMessage($manager->phone, $multipleItems);
                  //$response2 = $this->WhatsAppWebService->sendTemplateMessage('+919894753728', $multipleItems);
                 return redirect()->back()->with('status', 'WhatsApp messages sent successfully!');
             
          }

          return redirect()->back()->with('status', 'WhatsApp messages sent successfully!')->withInput($request->all());
      }

      return redirect()->back()->with('status', 'No carts selected')->withInput($request->all());
  }


  public function abandoned_cart_send_single_whatsapp($cart_id)
  {



        // $userId = $user_id;
        $user = DB::table('users')
            ->join('carts', 'users.id', '=', 'carts.user_id')
            ->select('users.company_name', 'users.phone','users.manager_id','carts.user_id')
            ->where('carts.id', $cart_id)
            ->first();

           
       
        
        $userId = $user->user_id;
           
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Retrieve manager's information
        $manager = DB::table('users')
        ->select('name', 'phone')
        ->where('id', $user->manager_id)
        ->first();

        $cartItems = DB::table('carts')
        ->join('products', 'carts.product_id', '=', 'products.id')
        ->select('products.name as product_name', 'carts.quantity')
        ->where('carts.user_id', $userId)
        ->where('carts.is_manager_41', 0)   // <--- NON-41 FILTER
        ->get();
       


        $itemCount = $cartItems->count();

            // $itemCount = $cartItems->count();

        // $name = $user->company_name;
        // $item1 = $cartItems->product_name ?? 'N/A';
        // $qty1 = $cartItems->quantity ?? '0';

       

        $name = $user->company_name;
                 
        $invoice=new InvoiceController();
        $randomNumber = rand(1000, 9999); // Generates a random number between 1000 and 9999

        $file_url=$invoice->invoice_file_path_abandoned_cart($userId,$randomNumber);
        
        $file_name="Abandoned Cart";
        $multipleItems = [
          'name' => 'utility_abandoned_cart', // Replace with your template name, e.g., 'abandoned_cart_template'
          'language' => 'en_US', // Replace with your desired language code
          'components' => [
              [
                  'type' => 'header',
                  'parameters' => [
                      ['type' => 'document', 'document' => ['link' => $file_url,'filename' => $file_name,]],
                  ],
              ],
              [
                  'type' => 'body',
                  'parameters' => [
                      ['type' => 'text', 'text' => $name],
                      ['type' => 'text', 'text' => $itemCount], // Second variable (Total Count)
                  ],
              ],
          ],
      ];

        $this->WhatsAppWebService = new WhatsAppWebService();
         $response = $this->WhatsAppWebService->sendTemplateMessage($user->phone, $multipleItems);
         $response1 = $this->WhatsAppWebService->sendTemplateMessage($manager->phone, $multipleItems);
        //$response2 = $this->WhatsAppWebService->sendTemplateMessage('+916289062983', $multipleItems);

        return redirect()->back()->with('status', 'WhatsApp messages sent successfully!');
        
  }


    public function abandoned_cart_send_whatsapp(Request $request)
    {
        // whatsapp all
        set_time_limit(-1);

        // 1) Sirf un users ko jinke cart me SIRF non-41 items hain
        $distinctUser = DB::table('users')
        ->join('carts', 'users.id', '=', 'carts.user_id')
        ->select('users.company_name','users.party_code','users.phone','users.manager_id','carts.user_id')
        // ->where('carts.user_id', 24185) // test
        ->whereExists(function ($q) {
            $q->from('carts as c2')
              ->whereColumn('c2.user_id', 'carts.user_id')
              ->where('c2.is_manager_41', 0); // has at least one non-41
        })
        ->groupBy('carts.user_id','users.company_name','users.party_code','users.phone','users.manager_id')
        ->get();


        $responses = [];

        foreach ($distinctUser as $user) {
            \Log::info('Send abandoned cart whatsapp of party '.$user->party_code.' with cron', [
                'status' => 'Start',
                'party_code' =>  $user->party_code
            ]);

            // 2) Manager info (null safety)
            $manager = DB::table('users')
                ->select('name', 'phone')
                ->where('id', $user->manager_id)
                ->first();

            // 3) Sirf non-41 items (carts par filter)
            $cartItems = DB::table('carts')
                ->join('products', 'carts.product_id', '=', 'products.id')
                ->select('products.name as product_name', 'carts.quantity', 'carts.price')
                ->where('carts.user_id', $user->user_id)
                ->where('carts.is_manager_41', 0)
                ->get();

            if ($cartItems->isEmpty()) {
                \Log::info('Skip party '.$user->party_code.' (no non-41 items after filter)');
                continue;
            }

            $itemCount = $cartItems->count();
            $name = $user->company_name;

            // 4) PDF link for this user's non-41 cart only
            $invoice = new InvoiceController();
            $randomNumber = rand(1000, 9999);
            $file_url = $invoice->invoice_file_path_abandoned_cart($user->user_id, $randomNumber);

            $file_name = "Abandoned Cart";

            // 5) WhatsApp template payload
            $multipleItems = [
                'name' => 'utility_abandoned_cart',
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'document', 'document' => ['link' => $file_url, 'filename' => $file_name]],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $name],
                            ['type' => 'text', 'text' => $itemCount],
                        ],
                    ],
                ],
            ];

            // 6) Send
            $this->WhatsAppWebService = new WhatsAppWebService();
            $response = $this->WhatsAppWebService->sendTemplateMessage($user->phone, $multipleItems);

            // Optional: manager & internal number
            if ($manager && !empty($manager->phone)) {
                $response1 = $this->WhatsAppWebService->sendTemplateMessage($manager->phone, $multipleItems);
            }
            $response2 = $this->WhatsAppWebService->sendTemplateMessage('+919894753728', $multipleItems);

            $responses[] = $response;

            \Log::info('Send abandoned cart whatsapp of party '.$user->party_code.' with cron', [
                'status' => 'End',
                'party_code' =>  $user->party_code
            ]);
        }

        return redirect()->back()->with('status', 'WhatsApp messages sent to all successfully!');
    }

  public function is_abandoned_cart_send_whatsapp(Request $request)
  {

        // whatsapp all
        set_time_limit(-1);
    
        $distinctUser = DB::table('users')
            ->join('carts', 'users.id', '=', 'carts.user_id')
            ->join('products', 'carts.product_id', '=', 'products.id')
            ->select('users.company_name','users.party_code', 'users.phone', 'carts.user_id','users.manager_id')
              // ->where('carts.user_id', 24185)
            ->groupBy('carts.user_id', 'users.name')
            ->get();


  
        $responses = [];

        foreach ($distinctUser as $user) {

            \Log::info('Send abandoned cart whatsapp of party '.$user->party_code.' with cron', [
                'status' => 'Start',
                'party_code' =>  $user->party_code
            ]);

            // Retrieve manager's information
            $manager = DB::table('users')
            ->select('name', 'phone')
            ->where('id', $user->manager_id)
            ->first();

            $cartItems = DB::table('carts')
                ->join('products', 'carts.product_id', '=', 'products.id')
                ->select('products.name as product_name', 'carts.quantity')
                ->where('carts.user_id', $user->user_id)
                ->get();

                $itemCount = $cartItems->count();

                $name = $user->company_name;

                $invoice=new InvoiceController();
                $randomNumber = rand(1000, 9999); // Generates a random number between 1000 and 9999

                $file_url=$invoice->invoice_file_path_abandoned_cart($user->user_id,$randomNumber);
                $file_name="Abandoned Cart";

                $multipleItems = [
                'name' => 'utility_abandoned_cart', // Replace with your template name, e.g., 'abandoned_cart_template'
                'language' => 'en_US', // Replace with your desired language code
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'document', 'document' => ['link' => $file_url,'filename' => $file_name,]],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $name],
                            ['type' => 'text', 'text' => $itemCount], // Second variable (Total Count)
                        ],
                    ],
                ],
            ];

            $this->WhatsAppWebService = new WhatsAppWebService();
            $response = $this->WhatsAppWebService->sendTemplateMessage($user->phone, $multipleItems);

            // echo "<pre>";
            // print_r($response);
            // die();
            $response1 = $this->WhatsAppWebService->sendTemplateMessage($manager->phone, $multipleItems);
            $response2 = $this->WhatsAppWebService->sendTemplateMessage('+919894753728', $multipleItems);
            $responses[] = $response;

            \Log::info('Send abandoned cart whatsapp of party '.$user->party_code.' with cron', [
                'status' => 'End',
                'party_code' =>  $user->party_code
            ]);
            
        }
  
      return redirect()->back()->with('status', 'WhatsApp messages sent to all successfully!');
  }
  public function abandoned_cart_list(Request $request)
{
    // Filters & Sorting
    $searchDate         = $request->input('searchDate');
    $searchManagers     = $request->input('searchManager', []);
    $searchCompanyNames = $request->input('searchCompanyName', []);
    $sortField          = $request->input('sortField', 'carts.created_at');
    $sortDirection      = $request->input('sortDirection', 'desc');

    $currentUserId   = auth()->user()->id;
    $isSuperManager  = in_array($currentUserId, [180, 169, 25606, 1]);

    // Distinct Managers (sirf non-41 carts se)
    $distinctManagersQuery = DB::table('users')
        ->join('carts', 'users.id', '=', 'carts.user_id')
        ->where('carts.is_manager_41', 0)
        ->select('users.manager_id')
        ->groupBy('users.manager_id');

    if (!$isSuperManager) {
        $distinctManagersQuery->where('users.manager_id', '=', $currentUserId);
    }
    $distinctManagers = $distinctManagersQuery->get();

    // Distinct Party Codes (sirf non-41)
    $distinctPartyCodes = DB::table('users')
        ->join('carts', 'users.id', '=', 'carts.user_id')
        ->where('carts.is_manager_41', 0)
        ->select('users.party_code')
        ->groupBy('users.party_code')
        ->get();

    // Distinct Company Names (sirf non-41)
    $distinctCompanyNames = DB::table('users')
        ->join('carts', 'users.id', '=', 'carts.user_id')
        ->where('carts.is_manager_41', 0)
        ->select('users.company_name')
        ->groupBy('users.company_name')
        ->get();

    // Base query
    $query = DB::table('users as u1')
        ->join('carts', 'u1.id', '=', 'carts.user_id')
        ->join('products', 'carts.product_id', '=', 'products.id')
        ->join('users as u2', 'u1.manager_id', '=', 'u2.id')
        ->select(
            'u1.company_name',
            'u1.phone',
            'u1.manager_id',
            'u1.party_code',
            'u2.name as manager_name',
            'products.name as product_name',
            'carts.created_at',
            'carts.quantity',
            'carts.price',
            'carts.user_id',
            'carts.product_id',
            'carts.id as cart_id',
            'carts.is_manager_41',
            DB::raw('carts.quantity * carts.price as total')
        )
        // ⬇️ Non-41 only (agar aapki listing non-41 par hi based hai)
        ->where('carts.is_manager_41', 0);

    // Manager filter
    if ($isSuperManager) {
        if (!empty($searchManagers)) {
            $query->whereIn('u1.manager_id', $searchManagers);
        }
    } else {
        $query->where('u1.manager_id', '=', $currentUserId);
    }

    // Company name filter
    if (!empty($searchCompanyNames)) {
        $query->whereIn('u1.company_name', $searchCompanyNames);
    }

    // Date filter
    if (!empty($searchDate)) {
        $query->whereDate('carts.created_at', '=', $searchDate);
    }

    // ✅ Total sum with the SAME filters (clone the builder)
    $totalSum = (clone $query)->sum(DB::raw('carts.quantity * carts.price'));

    // Sorting (optional: allowlist fields for safety)
    $allowedSorts = [
        'carts.created_at', 'u1.company_name', 'u2.name', 'u1.party_code'
    ];
    if (!in_array($sortField, $allowedSorts)) {
        $sortField = 'carts.created_at';
    }
    $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

    $abandonedCart = $query->orderBy($sortField, $sortDirection)->get();

    return view('backend.abandoned_cart.abandoned_cart_list', compact(
        'abandonedCart',
        'distinctManagers',
        'distinctPartyCodes',
        'distinctCompanyNames',
        'totalSum'
    ));
}

  public function backup_abandoned_cart_list(Request $request)
{

  
    // Retrieve the filters and sorting options from the request
    $searchDate = $request->input('searchDate');
    $searchManagers = $request->input('searchManager', []); // Accept multiple manager IDs
    // $searchPartyCodes = $request->input('searchPartyCode', []); // Accept multiple party codes
    $searchCompanyNames = $request->input('searchCompanyName', []); // Accept multiple company names
    $sortField = $request->input('sortField', 'carts.created_at'); // Default sort field
    $sortDirection = $request->input('sortDirection', 'desc'); // Default sort direction

    $currentUserId = auth()->user()->id;

    // Determine if the current user should see all data or only data related to their manager_id
    $isSuperManager = in_array($currentUserId, [180, 169, 25606, 1]);

    // Get distinct managers based on the current user role
    $distinctManagersQuery = DB::table('users')
        ->join('carts', 'users.id', '=', 'carts.user_id')
        ->select('users.manager_id')
        ->groupBy('users.manager_id');

    if (!$isSuperManager) {
        $distinctManagersQuery->where('users.manager_id', '=', $currentUserId);
    }

    $distinctManagers = $distinctManagersQuery->get();

    // Get distinct party codes for the dropdown
    $distinctPartyCodes = DB::table('users')
        ->join('carts', 'users.id', '=', 'carts.user_id')
        ->select('users.party_code')
        ->groupBy('users.party_code')
        ->get();

    // Get distinct company names for the dropdown
    $distinctCompanyNames = DB::table('users')
        ->join('carts', 'users.id', '=', 'carts.user_id')
        ->select('users.company_name')
        ->groupBy('users.company_name')
        ->get();

    // Start building the main query
    $query = DB::table('users as u1')
        ->join('carts', 'u1.id', '=', 'carts.user_id')
        ->join('products', 'carts.product_id', '=', 'products.id')
        ->join('users as u2', 'u1.manager_id', '=', 'u2.id') // Join to get the manager's name
        ->select(
            'u1.company_name',
            'u1.phone',
            'u1.manager_id',
            'u1.party_code',
            'u2.name as manager_name', // Select manager's name
            'products.name as product_name',
            'carts.created_at',
            'carts.quantity',
            'carts.price',
            'carts.user_id',
            'carts.product_id',
            'carts.id as cart_id',
            'carts.is_manager_41',
            DB::raw('carts.quantity * carts.price as total') // Calculate total per item
        );

    // Apply manager filter
    if ($isSuperManager) {
        if (!empty($searchManagers)) {
            $query->whereIn('u1.manager_id', $searchManagers);
        }
    } else {
        $query->where('u1.manager_id', '=', $currentUserId);
    }

    // Apply party code filter
    // if (!empty($searchPartyCodes)) {
    //     $query->whereIn('u1.party_code', $searchPartyCodes);
    // }

    // Apply company name filter
    if (!empty($searchCompanyNames)) {
        $query->whereIn('u1.company_name', $searchCompanyNames);
    }

    // Apply the date filter if it exists
    if ($searchDate) {
        $query->whereDate('carts.created_at', '=', $searchDate);
    }

    // Get the total sum of all items
    $totalSum = $query->sum(DB::raw('carts.quantity * carts.price'));

    // Apply sorting
    // $abandonedCart = $query->orderBy($sortField, $sortDirection)
    //     ->paginate(50)
    //     ->appends($request->all());

    $abandonedCart = $query->orderBy($sortField, $sortDirection)
        ->get();

    // echo "<pre>";
    // print_r($abandonedCart);
    // die();

    return view('backend.abandoned_cart.abandoned_cart_list', compact('abandonedCart', 'distinctManagers', 'distinctPartyCodes', 'distinctCompanyNames', 'totalSum'));
}

public function fetchCompanies(Request $request)
{
    $managerId = $request->input('manager_id');
    
    // Fetch distinct company names based on the selected manager
    $companies = DB::table('users')
        ->select('company_name')
        ->where('manager_id', $managerId)
        ->distinct()
        ->get();

    return response()->json($companies);
}

public function clearCart(Request $request)
{
    $userIds = $request->input('user_ids');

    if (empty($userIds)) {
        return response()->json(['success' => false, 'message' => 'No carts selected.']);
    }

    // Use query builder to delete all cart items for the selected user_ids
    DB::table('carts')->whereIn('user_id', $userIds)->where('is_manager_41', 0)->delete();

    return response()->json(['success' => true, 'message' => 'All items for the selected users have been cleared successfully.']);
}


public function deleteCartItem(Request $request)
{
    // Get the user ID and product ID from the request
    $userId = $request->input('user_id');
    $productId = $request->input('product_id');

    // Validate that both user ID and product ID are provided
    if (!$userId || !$productId) {
        return response()->json([
            'success' => false,
            'message' => 'User ID and Product ID are required.'
        ]);
    }

    // Find the specific cart item for the user and product
    $cartItem = Cart::where('user_id', $userId)
                    ->where('product_id', $productId)
                    ->first();

    // Check if the cart item exists
    if ($cartItem) {
        // Delete the specific cart item
        $cartItem->delete();

        // Return success response
        return response()->json([
            'success' => true,
            'message' => 'Cart item has been removed successfully.'
        ]);
    }

    // If the specific cart item is not found
    return response()->json([
        'success' => false,
        'message' => 'No cart item found for the specified user and product.'
    ]);
}





public function abandonedCartExportList(Request $request){
    $searchDate = $request->input('searchDate');
    $searchManagers = $request->input('searchManager', []);
    $searchCompanyNames = $request->input('searchCompanyName', []);

    $currentUserId = auth()->user()->id;
    $isSuperManager = in_array($currentUserId, [180, 169, 25606, 1]);

    $query = DB::table('users as u1')
        ->join('carts', 'u1.id', '=', 'carts.user_id')
        ->join('products', 'carts.product_id', '=', 'products.id')
        ->join('users as u2', 'u1.manager_id', '=', 'u2.id')
        ->select(
            'u1.company_name',
            'u1.phone',
            'u1.manager_id',
            'u1.party_code',
            'u2.name as manager_name',
            'products.name as product_name',
            'carts.created_at',
            'carts.quantity',
            'carts.price',
            DB::raw('carts.quantity * carts.price as total')
        )
        // ⬇️ Yahin par non-41 filter add karein
        ->where('carts.is_manager_41', 0);

    // Filters
    if ($isSuperManager) {
        if (!empty($searchManagers)) {
            $query->whereIn('u1.manager_id', $searchManagers);
        }
    } else {
        $query->where('u1.manager_id', '=', $currentUserId);
    }

    if (!empty($searchCompanyNames)) {
        $query->whereIn('u1.company_name', $searchCompanyNames);
    }

    if (!empty($searchDate)) {
        $query->whereDate('carts.created_at', $searchDate);
    }

    $abandonedCartData = $query->get();

    return Excel::download(new AbandonedCartExport($abandonedCartData), 'abandoned_carts.xlsx');
}


public function is_abandonedCartExportList(Request $request){
  $searchDate = $request->input('searchDate');
  $searchManagers = $request->input('searchManager', []);
  $searchCompanyNames = $request->input('searchCompanyName', []);

  $currentUserId = auth()->user()->id;
  $isSuperManager = in_array($currentUserId, [180, 169, 25606, 1]);

  $query = DB::table('users as u1')
      ->join('carts', 'u1.id', '=', 'carts.user_id')
      ->join('products', 'carts.product_id', '=', 'products.id')
      ->join('users as u2', 'u1.manager_id', '=', 'u2.id')
      ->select(
          'u1.company_name',
          'u1.phone',
          'u1.manager_id',
          'u1.party_code',
          'u2.name as manager_name',
          'products.name as product_name',
          'carts.created_at',
          'carts.quantity',
          'carts.price',
          DB::raw('carts.quantity * carts.price as total')
      );

  // Apply filters based on the request
  if ($isSuperManager) {
      if (!empty($searchManagers)) {
          $query->whereIn('u1.manager_id', $searchManagers);
      }
  } else {
      $query->where('u1.manager_id', '=', $currentUserId);
  }

  if (!empty($searchCompanyNames)) {
      $query->whereIn('u1.company_name', $searchCompanyNames);
  }

  if ($searchDate) {
      $query->whereDate('carts.created_at', '=', $searchDate);
  }

  $abandonedCartData = $query->get();

  // Export the data to Excel
  return Excel::download(new AbandonedCartExport($abandonedCartData), 'abandoned_carts.xlsx');
}
    


  



  public function abandoned_cart_save_remark(Request $request){
    // return response()->json(['success' => true, 'message' => 'Remark saved successfully!']);
    // Validate the incoming request data
        $request->validate([
          'remark' => 'required'
          
      ]);

      // Insert the data into the remarks table
      DB::table('remarks')->insert([
          'remark_description' => $request->input('remark'),
          'created_at' => now(),
          'updated_at' => now(),
          'user_id' => $request->input('user_id'),
          'cart_id' => $request->input('cart_id'),
      ]);

      // Return a JSON response indicating success
      return response()->json([
          'success' => true,
          'message' => 'Remark saved successfully!',
      ]);
  }

  public function viewRemark($cart_id)
    {
        // Fetch the remark based on the cart_id
      
        $remarks = DB::table('remarks')
        ->join('users', 'remarks.user_id', '=', 'users.id')
        ->join('carts', 'remarks.cart_id', '=', 'carts.id')
        ->join('products', 'carts.product_id', '=', 'products.id')
        ->select(
            'remarks.remark_description',
            'remarks.created_at',
            'users.name as user_name',
            'carts.quantity',
            'carts.price',
            'products.name as product_name'
        )
        ->where('remarks.cart_id', $cart_id)
        ->get();

        // Pass the remark data to the view
        return view('backend.abandoned_cart.view_remark', compact('remarks'));
    }

    public function getRemarks(Request $request)
    {
        $cart_id = $request->input('cart_id');
    
        $remarks = DB::table('remarks')
            ->join('users', 'remarks.user_id', '=', 'users.id')
            ->select('remarks.remark_description', 'remarks.created_at', 'users.name as user_name')
            ->where('remarks.cart_id', $cart_id)
            ->orderBy('remarks.created_at', 'desc')
            ->get();
    
        if ($remarks->isNotEmpty()) {
            return response()->json([
                'success' => true,
                'remarks' => $remarks
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No remarks found for the selected cart.'
            ]);
        }
    }


public function manager41DownloadQuotation(Request $request)
{
    $user_id = Auth::id();

    // 1) Only Manager-41 cart items for this user
    $cartItems = DB::table('users')
        ->leftJoin('carts', 'users.id', '=', 'carts.user_id')
        ->leftJoin('products', 'carts.product_id', '=', 'products.id')
        ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
        ->leftJoin('addresses', 'carts.address_id', '=', 'addresses.id')
        ->select(
            'users.company_name',
            'users.phone',
            'users.manager_id',
            'users.party_code',
            'products.name as product_name',
            'warehouses.name as warehouse_name',
            'carts.created_at',
            'carts.quantity',
            'carts.address_id',
            'carts.price',
            'carts.user_id',
            'carts.product_id',
            'carts.id as cart_id',
            DB::raw('carts.quantity * carts.price as total')
        )
        ->where('carts.user_id', $user_id)
        ->where('carts.is_manager_41', 1)
        ->get();

    if ($cartItems->isEmpty()) {
        return back()->with('status', 'No Manager-41 cart items found.');
    }

    // 2) Build prefix like "KOL41-"
    $whName         = $cartItems->first()->warehouse_name ?? 'GEN';
    $warehouse_name = strtoupper(substr($whName, 0, 3)) ?: 'GEN';
    $prefix         = $warehouse_name . '41-';

    // 3) Find last number for this prefix and increment (0001 -> 0002 ...)
    $maxQuotationId = DB::table('manager41_quotation')
        ->where('quotation_id', 'LIKE', $prefix . '%')
        ->orderByRaw("CAST(SUBSTRING_INDEX(quotation_id,'-',-1) AS UNSIGNED) DESC")
        ->value('quotation_id');

    $newNumber = $maxQuotationId
        ? ((int) substr($maxQuotationId, strlen($prefix))) + 1
        : 1;

    $newQuotationId = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT); // e.g. KOL41-0002

    // 4) Insert quotation rows
    foreach ($cartItems as $item) {
        DB::table('manager41_quotation')->insert([
            'cart_id'      => $item->cart_id,
            'user_id'      => $user_id,
            'quotation_id' => $newQuotationId,
            'status'       => '0',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    // 5) Generate PDF for Manager-41 items and return to user
    $invoice      = new InvoiceController();
    $fileUrlOrPath = $invoice->invoice_file_path_cart_quotations_manager41($user_id, $newQuotationId);

    if (empty($fileUrlOrPath)) {
        return back()->with('status', 'Unable to generate quotation file.');
    }

    // If absolute URL, just open/redirect to it
    if (Str::startsWith($fileUrlOrPath, ['http://', 'https://'])) {
        return redirect()->away($fileUrlOrPath);
    }

    // Otherwise treat as a local path under public/ (or storage fallback)
    $filePath = $fileUrlOrPath;
    if (!Str::startsWith($filePath, ['/','\\']) && !preg_match('/^[A-Za-z]:[\/\\\\]/', $filePath)) {
        $filePath = public_path(ltrim(parse_url($fileUrlOrPath, PHP_URL_PATH) ?? $fileUrlOrPath, '/'));
    }

    if (!file_exists($filePath)) {
        $altPath = storage_path('app/' . ltrim($fileUrlOrPath, '/'));
        if (file_exists($altPath)) {
            $filePath = $altPath;
        } else {
            return back()->with('status', 'Quotation file not found.');
        }
    }

    return response()->download($filePath, $newQuotationId . '.pdf', [
        'Content-Type' => 'application/pdf',
    ]);
}




    public function send_quotations(Request $request) {

         // If logged in as Manager-41, hand off to the special flow
        if ($this->isActingAs41Manager()) {                    // <<< NEW
            return $this->manager41DownloadQuotation($request); // <<< NEW
        }   
      $user_id = Auth::user()->id;

      // Retrieve manager's information
      $manager = DB::table('users')
      ->select('name', 'phone')
      ->where('id', Auth::user()->manager_id)
      ->first();

      // Retrieve all cart items related to the user, including the warehouse name
      $cartItems = DB::table('users')
          ->leftJoin('carts', 'users.id', '=', 'carts.user_id')
          ->leftJoin('products', 'carts.product_id', '=', 'products.id')
          ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id') // Join with warehouses table via users table
          ->leftJoin('addresses', 'carts.address_id', '=', 'addresses.id')
          ->select(
              'users.company_name',
              'users.phone',
              'users.manager_id',
              'users.party_code',
              'products.name as product_name',
              'warehouses.name as warehouse_name', // Get the warehouse name with alias
              'carts.created_at',
              'carts.quantity',
              'carts.address_id',
              'carts.price',
              'carts.user_id',
              'carts.product_id',
              'carts.id as cart_id',
              DB::raw('carts.quantity * carts.price as total') // Calculate total per item
          )
          ->where('carts.user_id', $user_id)  // Add the where condition
          ->get();
  
      // Get the warehouse name of the user
      $warehouse_name = strtoupper(substr($cartItems->first()->warehouse_name, 0, 3)); // Extract the first 3 letters and convert to uppercase
  
      // Generate the new quotation_id
      $maxQuotationId = DB::table('quotations')
          ->where('quotation_id', 'LIKE', $warehouse_name . '-%')
          ->orderBy('quotation_id', 'desc')
          ->value('quotation_id');
  
      if ($maxQuotationId) {
          $lastNumber = (int)substr($maxQuotationId, strlen($warehouse_name) + 1);
          $newNumber = $lastNumber + 1;
      } else {
          $newNumber = 1;
      }
  
      $newQuotationId = $warehouse_name . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
      
      foreach ($cartItems as $item) {
          DB::table('quotations')->insert([
              'cart_id' => $item->cart_id,
              'user_id' => $user_id,
              'quotation_id' => $newQuotationId, // Use the new quotation ID
              'status' => '0', // Set the initial status
              'created_at' => now(),
              'updated_at' => now(),
          ]);
      }
      
      $invoice=new InvoiceController();
      $file_url=$invoice->invoice_file_path_cart_quotations($user_id,$newQuotationId);
     
      $file_name="Quotations";
     // $to=['+916289062983'];
      $to=[Auth::user()->phone,$manager->phone,'+919894753728'];

      $templateData = [
        'name' => 'utility_quotation',
        'language' => 'en', 
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
                    ['type' => 'text', 'text' => $newQuotationId],
                ],
            ],
        ],
    ];
    

      $this->WhatsAppWebService=new WhatsAppWebService();
      foreach($to as $person_to_send){
        $response = $this->WhatsAppWebService->sendTemplateMessage($person_to_send, $templateData);
      }

      return response()->json(['status' => 'Quotation Sent to whatsapp!']);
  }
  
  

  // ABANDONED CART CODE END


      //purchase order code start
      //negative stock inventory
      public function purchase_order(Request $request) {
       
        // Retrieve the list of sellers for the dropdown
        $sellers = DB::table('users')
            ->join('sellers', 'users.id', '=', 'sellers.user_id')
            ->select('users.id', 'users.name')
            ->orderBy('users.name', 'asc')
            ->get();
    
       // Start query for purchase orders
        $query = DB::table('purchase_order')
        ->leftJoin('products', 'purchase_order.part_no', '=', 'products.part_no')
        ->leftJoin('sellers', 'products.seller_id', '=', 'sellers.id')
        ->leftJoin('users', 'sellers.user_id', '=', 'users.id')
        ->leftJoin('shops', 'sellers.id', '=', 'shops.seller_id') // Join sellers with shops
        ->select(
            'purchase_order.*',
            'products.seller_id',
            'sellers.user_id',
            'users.name as seller_name',
            'shops.name as seller_company_name' // Retrieve name as seller_company_name
        )
        // Add the condition to exclude records where delete_status is 1
        ->where('purchase_order.delete_status', '!=', 1);
    
        // Apply seller name filter if provided
        if ($request->filled('sellerName')) {
            $query->where('users.id', '=', $request->sellerName);
        }
    
        // Apply sorting if provided
        if ($request->filled('sort') && $request->filled('direction')) {
            $query->orderBy($request->sort, $request->direction);
        } else {
            $query->orderBy('purchase_order.id', 'asc'); // Default sorting
        }
    
        // Get paginated results
        $purchaseOrders = $query->paginate(100)->appends($request->all());
        // echo "<pre>";
        // print_r($purchaseOrders);
        // die();
    
        return view('backend.purchase_order.purchase_order', compact('purchaseOrders', 'sellers'));
    }

    public function purchaseOrderDeleteItems($id, Request $request)
    {
        // Validate the ID exists
        $order = DB::table('purchase_order')->where('id', $id)->first();
       
        
        if (!$order) {
            return redirect()->back()->with('status', 'Purchase order not found!');
        }

        // Get the order_id from the $order object
        $orderId = $order->order_no; // Assuming 'order_id' is the correct field name

        // Delete the purchase order
        // DB::table('purchase_order')->where('id', $id)->delete();
         // Update the delete_status to 1 instead of deleting the purchase order
        DB::table('purchase_order')
        ->where('id', $id)
        ->update(['delete_status' => 1]);

        // Redirect back with a success message, including the order_id
        return redirect()->back()->with('status', "Purchase order with Order ID $orderId deleted successfully!")->withInput($request->all());
    }

    


    public function showSelected(Request $request)
    {

      
        // Get the selected orders' IDs
        $selectedOrders = $request->input('selectedOrders', []);
        
        // Fetch the selected orders from the database, grouping by part_no and combining order_no and quantities
        $orders = DB::table('purchase_order')
            ->whereIn('purchase_order.id', $selectedOrders)
            ->leftJoin('products', 'purchase_order.part_no', '=', 'products.part_no')
            ->leftJoin('sellers', 'products.seller_id', '=', 'sellers.id')
            ->leftJoin('shops', 'sellers.id', '=', 'shops.seller_id')
            ->select(
                'purchase_order.part_no',
                'purchase_order.item',
                DB::raw('GROUP_CONCAT(DISTINCT purchase_order.order_no ORDER BY purchase_order.order_no ASC SEPARATOR ", ") as order_no'),
                DB::raw('GROUP_CONCAT(DISTINCT purchase_order.age ORDER BY purchase_order.age ASC SEPARATOR ", ") as age'),
                DB::raw('GROUP_CONCAT(DISTINCT DATE_FORMAT(purchase_order.order_date, "%d/%m/%y") ORDER BY purchase_order.order_date ASC SEPARATOR ", ") as order_date'),
                DB::raw('SUM(purchase_order.to_be_ordered) as total_quantity'),
                'products.seller_id',
                'products.purchase_price',
                'shops.name as seller_company_name',
                'shops.address as seller_address',
                'sellers.gstin as seller_gstin',
                'shops.phone as seller_phone'
            )
            ->groupBy('purchase_order.part_no', 'purchase_order.item', 'products.seller_id', 'products.purchase_price', 'shops.name', 'shops.address', 'sellers.gstin', 'shops.phone')
            ->get();

            // Fetch warehouses with id 1, 2, and 6
          $warehouses = DB::table('warehouses')
              ->whereIn('id', [1, 2, 6])
              ->select('id', 'name')
              ->get();

               // Fetch all sellers for the dropdown
          // $all_sellers = DB::table('sellers')
          //     ->join('shops', 'sellers.id', '=', 'shops.seller_id')
          //     ->select('sellers.id', 'shops.name as seller_company_name')
          //     ->get();

               // Fetch all sellers from final_purchase_order (JSON column 'seller_info')
    $all_sellers = DB::table('final_purchase_order')
        ->select(DB::raw('JSON_UNQUOTE(JSON_EXTRACT(seller_info, "$.seller_name")) as seller_name'))
        ->distinct()
        ->get();

              // Get the current seller ID from the first order in the collection (if any orders exist)
          // $current_seller_id = $orders->isNotEmpty() ? $orders->first()->seller_id : null;
        $current_seller_name = $orders->isNotEmpty() ? $orders->first()->seller_company_name : null;
          // echo $current_seller_id;
          // die();

        // Check if $orders is empty
        // Ensure $orders is not null
        if ($orders->isEmpty()) {
          $orders = collect(); // This will make $orders an empty collection
      }
      
      return view('backend.purchase_order.selected_orders', compact('orders', 'warehouses','all_sellers','current_seller_name'));
    }

     public function getSellerInfo($seller_name)
    {
        // Fetch the seller information based on the seller name from final_purchase_order
        $seller = DB::table('final_purchase_order')
            ->select(DB::raw('JSON_UNQUOTE(JSON_EXTRACT(seller_info, "$.seller_name")) as seller_name'),
                     DB::raw('JSON_UNQUOTE(JSON_EXTRACT(seller_info, "$.seller_address")) as seller_address'),
                     DB::raw('JSON_UNQUOTE(JSON_EXTRACT(seller_info, "$.seller_gstin")) as seller_gstin'),
                     DB::raw('JSON_UNQUOTE(JSON_EXTRACT(seller_info, "$.seller_phone")) as seller_phone'))
            ->where(DB::raw('JSON_UNQUOTE(JSON_EXTRACT(seller_info, "$.seller_name"))'), $seller_name)
            ->first();

        // Return the seller information as a JSON response
        return response()->json($seller);
    }

   // Function to get the message status by ID
    public function getMessageStatusById($messageId)
    {
        // Set up the database connection dynamically within the function
        config([
            'database.connections.dynamic_mazingbusiness' => [
                'driver' => 'mysql',
                'host' => 'localhost',  // Replace with your cloud database host
                'port' => '3306',  // Replace with your cloud database port
                'database' => 'mazingbusiness',  // The database name is 'mazingbusiness'
                'username' => 'mazingbusiness',   // Replace with your database username
                'password' => 'Gd6de243%',   // Replace with your database password
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ]
        ]);

        // Fetch the message data from the cloud_response database
        $cloudResponseData = DB::connection('dynamic_mazingbusiness')
            ->table('cloud_response')
            ->where('msg_id', $messageId)
            ->first();

        return $cloudResponseData->status;
    }

    

    public function saveSelected(Request $request)
    {

        // Validate the input data
        $validatedData = $request->validate([
            'orders.*.quantity' => 'required|integer|min:0',
            'orders.*.purchase_price' => 'required|numeric|min:0',
            'orders.*.order_no' => 'required|string',
            'seller_info.seller_name' => 'required|string|max:255',
            'seller_info.seller_phone' => 'required|string|max:15',
            'warehouse_id' => 'required'
        ], [
            'orders.*.quantity.required' => 'Quantity is required for each item.',
            'orders.*.quantity.integer' => 'Quantity must be a valid number.',
            'orders.*.purchase_price.required' => 'Purchase price is required for each item.',
            'orders.*.purchase_price.numeric' => 'Purchase price must be a valid number.',
            'orders.*.order_no.required' => 'Order number is required.',
            'seller_info.seller_name.required' => 'Seller name is required.',
            'seller_info.seller_phone.required' => 'Seller phone is required.',
            'warehouse_id.required' => 'Warehouse selection is required.', 
        ]);

        $sellerId = null;
        $productInfo = [];
        $orderNumbers = [];
        $productInfoWithOutZeroQty = [];

        foreach ($request->input('orders') as $orderId => $orderData) {
            $partNo = $orderData['part_no'];
            $quantity = $orderData['quantity'];
            $purchasePrice = $orderData['purchase_price'];
            $currentSellerId = $orderData['seller_id'];
            $orderNo = $orderData['order_no'];
            $orderDate =  $orderData['order_date'];
            $age =  $orderData['age'];

            if (!$sellerId) {
                $sellerId = $currentSellerId;
            }

            DB::table('products')
                ->where('part_no', $partNo)
                ->update(['purchase_price' => $purchasePrice]);

            $orderNoWithDate = $orderNo . " ($orderDate)";

            $productInfo[] = [
                'part_no' => $partNo,
                'qty' => $quantity,
                'order_no' => $orderNoWithDate,
                'age' => $age
            ];

            if ($quantity != 0) {
                $productInfoWithOutZeroQty[] = [
                    'part_no' => $partNo,
                    'qty' => $quantity,
                    'order_no' => $orderNoWithDate,
                    'age' => $age,
                ];

                DB::table('purchase_order')
                ->where('part_no', $partNo)
                ->where('order_no', $orderNo)
                ->update(['delete_status' => 1]);
            }

            $orderNumbers[] = $orderNoWithDate;
        }

        $lastOrder = DB::table('final_purchase_order')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastOrder) {
            $lastOrderNumber = intval(substr($lastOrder->purchase_order_no, 3));
            $newOrderNumber = $lastOrderNumber + 1;
        } else {
            $newOrderNumber = 1;
        }

        $purchaseOrderNo = 'po-' . str_pad($newOrderNumber, 3, '0', STR_PAD_LEFT);

        $orderNumbersString = implode(',', array_unique($orderNumbers));

        $sellerInfo = [
            'seller_name' => $request->input('seller_info.seller_name'),
            'seller_address' => $request->input('seller_info.seller_address'),
            'seller_gstin' => $request->input('seller_info.seller_gstin'),
            'seller_phone' => $request->input('seller_info.seller_phone'),
        ];

        $data = [
            'purchase_order_no' => $purchaseOrderNo,
            'order_no' => $orderNumbersString,
            'date' => now()->format('Y-m-d'),
            'seller_id' => $sellerId,
            'product_info' => json_encode($productInfoWithOutZeroQty),
            'product_invoice' => json_encode($productInfoWithOutZeroQty),
            'seller_info' => json_encode($sellerInfo),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('final_purchase_order')->insert($data);

        $seller_warehouse = DB::table('final_purchase_order as fpo')
        ->join('sellers as s', 'fpo.seller_id', '=', 's.id')
        ->join('users as u', 's.user_id', '=', 'u.id')
        ->where('fpo.purchase_order_no', $purchaseOrderNo)
        ->value('u.warehouse_id');

        $invoiceController = new InvoiceController();
        $fileUrls = [
            $invoiceController->purchase_order_pdf_invoice($purchaseOrderNo),
            $invoiceController->packing_list_pdf_invoice($purchaseOrderNo)
        ];

        $fileNames = ["Purchase Order", "Packing List"];

        $sellerPhone = DB::table('final_purchase_order')
            ->where('purchase_order_no', $purchaseOrderNo)
            ->value(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_phone'))"));

        // List of phone numbers to send the message to
        $toNumbers = [
            // $sellerPhone,  // Original seller phone number from the database
            '+919930791952' , // Additional phone number 1
            // '9730377752'   // Additional phone number 2
            '+919894753728'
            
        ];


        // Manager phone numbers based on seller_warehouse condition
        // Get the selected warehouse from the request
         $warehouseId = $request->warehouse_id;
        if ($warehouseId == 2) {
            $toNumbers[] = '+919763268640';  // Manager 1 (m1) //delhi
        } elseif ($warehouseId == 6) {
            $toNumbers[] = '+919860433981';  // Manager 2 (m2) mumbai
        }

        // Loop through each phone number and send the WhatsApp message with retry logic
        foreach ($toNumbers as $to) {
            foreach ($fileUrls as $index => $fileUrl) {
                $templateData = [
                    'name' => 'utility_purchase_order',
                    'language' => 'en_US',
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                [
                                    'type' => 'document',
                                    'document' => [
                                        'link' => $fileUrl,
                                        'filename' => $fileNames[$index],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' =>$purchaseOrderNo],
                            ],
                        ],
                    ],
                ];

                // Retry mechanism
                $retryCount = 0;
                $maxRetries = 1;
                $messageSent = false;

                while ($retryCount < $maxRetries && !$messageSent) {
                    try {
                        $this->WhatsAppWebService = new WhatsAppWebService();
                        $response1 = $this->WhatsAppWebService->sendTemplateMessage($to, $templateData);

                        if (isset($response1['messages'][0]['id'])) {
                            $messageId = $response1['messages'][0]['id'];

                            sleep(2); // Delay for 1 second before checking the status
                            // Call the function to get the message status
                            $messageStatus = $this->getMessageStatusById($messageId);

                            if ($messageStatus === 'sent') {
                                $messageSent = true;  // Mark as sent
                                break;  // Break out of the retry loop
                            } else {
                                throw new Exception("Message sending failed");
                            }
                        } else {
                            throw new Exception("Message ID not found in the response");
                        }
                    } catch (Exception $e) {
                        $retryCount++;
                        if ($retryCount >= $maxRetries) {
                            // Log or handle failure after 3 retries
                            Log::error("Failed to send message to $to after $maxRetries attempts. Error: " . $e->getMessage());
                            // Optionally, you can notify admin via email or other methods
                        } else {
                            // You may introduce a short delay before retrying (optional)
                            sleep(2); // Delay for 2 seconds before retrying
                        }
                    }
                }
            }
        }

        return redirect()->route('admin.purchase_order')->with('status', 'Purchase order saved successfully!');
    }

    
public function showFinalizedOrders(Request $request)
{
    // Get search and sorting parameters
    $search = $request->input('search');
    $sortColumn = $request->input('sort_column', 'date'); // Default sorting column is 'date'
    $sortOrder = $request->input('sort_order', 'desc');   // Default sorting order is 'desc'

    // Base query
    $query = DB::table('final_purchase_order')
        ->join('sellers', 'final_purchase_order.seller_id', '=', 'sellers.id')
        ->select(
            'final_purchase_order.*',
            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_name')) as seller_name"),
            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_phone')) as seller_phone"),
            'final_purchase_order.force_closed'
        )
        ->where('is_closed', '=', 0); // Check if is_closed is 0

    // Apply search filter if search query exists
    if ($search) {
        $query->where(function ($query) use ($search) {
            $query->where('final_purchase_order.purchase_order_no', 'LIKE', '%' . $search . '%')
                  ->orWhere(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_name'))"), 'LIKE', '%' . $search . '%');
        });
    }

    // Apply sorting
    $query->orderBy($sortColumn, $sortOrder);

    // Paginate results and keep the query string
    $orders = $query->paginate(50)->withQueryString();

    // Process orders
    foreach ($orders as $order) {
        // Decode the product_info JSON
        $productInfo = json_decode($order->product_info, true);

        // Check the convert_to_purchase_status and filter accordingly
        if ($order->convert_to_purchase_status == 1) {
            // Filter only products with quantity zero for converted orders
            $filteredProducts = array_filter($productInfo, function ($product) {
                return $product['qty'] == 0;
            });
        } else {
            // For orders not yet converted, show all products
            $filteredProducts = $productInfo;
        }

        // Fetch product names and attach them to the products
        foreach ($filteredProducts as &$product) {
            $productName = DB::table('products')
                ->where('part_no', $product['part_no'])
                ->value('name');
            $product['product_name'] = $productName;
        }

        // Assign the filtered product info back to the order
        $order->product_info = $filteredProducts;
    }

    // Return the view with the orders
    return view('backend.purchase_order.finalized_purchase_order_listing', compact('orders', 'search', 'sortColumn', 'sortOrder'));
}



    public function forceClose($id)
{
    // Fetch the purchase order by ID
    $purchaseOrder = DB::table('final_purchase_order')->where('id', $id)->first();

    if (!$purchaseOrder) {
        // Redirect with an error message if the purchase order doesn't exist
        return redirect()->back()->with('status', 'Purchase order not found.');
    }

    // Decode the product_info field
    $productInfo = json_decode($purchaseOrder->product_info);

    foreach ($productInfo as $product) {
        $partNo = $product->part_no; // Use object notation
        $orderNosWithDates = explode(',', $product->order_no); // Split in case of multiple order numbers
        $qty = $product->qty; // Quantity for the entire entry

        foreach ($orderNosWithDates as $orderNoWithDate) {
            // Extract the order number without the date part
            if (preg_match('/(SO\/[^ ]+)/', trim($orderNoWithDate), $matches)) {
                $orderNo = trim($matches[1]);

                // Update the purchase_order table where part_no and order_no match
                DB::table('purchase_order')
                    ->where('part_no', $partNo)
                    ->where('order_no', $orderNo)
                    ->update(['delete_status' => 1]);
            }
        }
    }

    // Mark the purchase order as closed by updating the 'force_closed' field
    DB::table('final_purchase_order')
        ->where('id', $id)
        ->update([
            'force_closed' => 1,
            'updated_at' => now(), // Update the updated_at timestamp
        ]);

    // Redirect with a success message after closing the order
    return redirect()->back()->with('status', 'Purchase order has been force closed successfully.');
}






    public function showProductInfo($id)
    {
        // Fetch the purchase order by ID
        $order = DB::table('final_purchase_order')
            ->where('id', $id)
            ->first();

        // Decode the product info JSON
        $productInfo = json_decode($order->product_info, true);

        // Decode the seller info JSON
        $sellerInfo = json_decode($order->seller_info, true);

        $purchaseNo = "";
        $finalPurchase = null; // Initialize $finalPurchase to null

        // Check the convert_to_purchase_status
        if ($order->convert_to_purchase_status == 1) {
            // If status is 1, filter only products with quantity zero
            $finalPurchase = DB::table('final_purchase')
                ->where('purchase_order_no', $order->purchase_order_no)
                ->first();

            if ($finalPurchase) {
                $purchaseNo = $finalPurchase->purchase_no;
            }

            $productInfo = array_filter($productInfo, function ($product) {
                return $product['qty'] == 0;
            });
        }

        // Fetch the product details for each part number
        foreach ($productInfo as &$product) {
            $productDetails = DB::table('products')
                ->where('part_no', $product['part_no'])
                ->select('name', 'purchase_price', 'hsncode')
                ->first();

            $product['product_name'] = $productDetails->name ?? 'Unknown';
            $product['purchase_price'] = $productDetails->purchase_price ?? 'N/A';
            $product['hsncode'] = $productDetails->hsncode ?? 'N/A';
        }
        

        // Pass the seller information and product information to the view
        return view('backend.purchase_order.product_info', compact('order', 'productInfo', 'sellerInfo', 'purchaseNo', 'finalPurchase'));
    }

    
    public function viewProducts($purchaseOrderNo)
    {
        // Retrieve the purchase order based on the purchase_order_no
        $order = DB::table('final_purchase')
            ->where('purchase_no', $purchaseOrderNo)
            ->first();
            // echo "<pre>";
            // print_r($order);
            // die();
    
        // Decode the product info JSON
        $productInfo = json_decode($order->product_info, true);
    
        // Filter the product info to include only those products with qty != 0
        $filteredProductInfo = array_filter($productInfo, function($product) {
            return $product['qty'] != 0;
        });
    
        // Fetch additional product details if needed
        foreach ($filteredProductInfo as &$product) {
            $productDetails = DB::table('products')
                ->where('part_no', $product['part_no'])
                ->select('name', 'purchase_price', 'hsncode') // Adjust fields as needed
                ->first();
    
            $product['product_name'] = $productDetails->name ?? 'Unknown';
            $product['purchase_price'] = $productDetails->purchase_price ?? 'N/A';
            $product['hsncode'] = $productDetails->hsncode ?? 'N/A';
        }
    
        return view('backend.purchase_order.final_product_info', compact('order', 'filteredProductInfo'));
    }
    

    

   public function convertToPurchase(Request $request, $id)
   {
      

      $validatedData = $request->validate([
        'seller_invoice_no' => 'required|string|max:255',
        'seller_invoice_date' => 'required|date',
        'products.*.hsncode' => 'required|string|max:255',
        'products.*.qty' => 'required|integer|min:0',
        'products.*.purchase_price' => 'required|numeric|min:0',
    ], [
        'seller_invoice_no.required' => "Seller Invoice Number is required.",
        'seller_invoice_date.required' => "Seller Invoice Date is required.",
        'products.*.hsncode.required' => "HSN Code is required for each product.",
        'products.*.qty.required' => "Quantity is required for each product.",
        'products.*.qty.integer' => "Quantity must be an integer.",
        'products.*.purchase_price.required' => "Purchase Price is required for each product.",
        'products.*.purchase_price.numeric' => "Purchase Price must be a valid number.",
    ]);
    


      
      // Get the product_info from the final_purchase_order table
      $order = DB::table('final_purchase_order')->where('id', $id)->first();
      $productInfo = json_decode($order->product_info, true);

      $isClosed = 1; // Assume the purchase order will be closed unless a product has zero qty

      

      // Initialize an array to collect updated product information
      $updatedProductInfo = [];

      // Iterate over the products from the request to update the product table and collect updated information
      foreach ($request->input('products') as $productData) {
          $partNo = $productData['part_no'];
          $qty = $productData['qty'];
          if($qty == 0){
            $isClosed=0;
          }
          $hsncode = $productData['hsncode'];
          $purchasePrice = $productData['purchase_price'];
          $orderNo = $productData['order_no']; // Get the order number
          $age = $productData['age']; // Get the age

          $sellerId = $request->input('seller_id');
          $purchaseOrderNo = $request->input('purchase_order_no');

          // Update the product table with the new HSN code and purchase price
          DB::table('products')
              ->where('part_no', $partNo)
              ->update([
                  'hsncode' => $hsncode,
                  'purchase_price' => $purchasePrice,
              ]);

          // Update the existing product info in the array
          foreach ($productInfo as &$existingProduct) {
              if ($existingProduct['part_no'] === $partNo) {
                  $existingProduct['qty'] = $qty;
                  $existingProduct['hsncode'] = $hsncode;
                  $existingProduct['order_no'] = $orderNo;
                  $existingProduct['age'] = $age;
                  break;
              }
          }

          // Collect updated product information
          $updatedProductInfo[] = [
              'part_no' => $partNo,
              'qty' => $qty,
              'order_no' => $orderNo, // Include the order number in the product info
              'age' => $age // Include the age in the product info
          ];

          // Push only the part_no to Salezing API if the quantity is not zero
        // if ($qty != 0) {
            //   $result = [
            //       'part_no' => $partNo
            //   ];
            $result=array();
            $result['part_no']= $partNo;

            $salzingResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://mazingbusiness.com/api/v2/item-push', $result);

            \Log::info('Salezing Item Push Status: ' . json_encode($salzingResponse->json(), JSON_PRETTY_PRINT));
        //   }
       
      }
     

      
      // Check if purchase_no is provided; if not, generate a new one
  
      $lastPurchase = DB::table('final_purchase')
      ->orderBy('id', 'desc')
      ->first();

      if ($lastPurchase) {
          // Extract the number from the last purchase_no
          $lastPurchaseNumber = intval(substr($lastPurchase->purchase_no, 3));
          $newPurchaseNumber = $lastPurchaseNumber + 1;
      } else {
          // Start from 1 if no purchase records exist
          $newPurchaseNumber = 1;
      }
      // Format the new purchase_no with leading zeros (e.g., pn-001)
      $purchaseNo = 'pn-' . str_pad($newPurchaseNumber, 3, '0', STR_PAD_LEFT);
    
      
      // $sellerInfo = json_decode($order->seller_info, true);
      // Prepare the seller_info array
      $sellerInfo = [
        'seller_name' => $request->input('seller_info.seller_name'),
        'seller_address' => $request->input('seller_info.seller_address'),
        'seller_gstin' => $request->input('seller_info.seller_gstin'),
        'seller_phone' => $request->input('seller_info.seller_phone'),
    ];

    

      // Prepare data to be inserted into the final_purchase table
      $data = [
          'purchase_no' => $purchaseNo,
          'purchase_order_no' => $purchaseOrderNo,
          'seller_id' => $sellerId,
          'seller_invoice_no' => $request->input('seller_invoice_no'),  // Use the provided seller invoice number
          'seller_invoice_date' => $request->input('seller_invoice_date'), // Use the provided seller invoice date
          'seller_info' => json_encode($sellerInfo), // Insert seller info
          'product_info' => json_encode($updatedProductInfo), // Insert updated product info
          'created_at' => now(),
          'updated_at' => now(),
      ];

      // Use updateOrInsert to update the final_purchase table
      DB::table('final_purchase')->insert(
        
          $data // Data to update or insert
      );

      // Update the final_purchase_order table with the updated product information and convert_to_purchase_status
      DB::table('final_purchase_order')
          ->where('id', $id)
          ->update([
              'convert_to_purchase_status' => 1, // Mark as closed only if no qty is zero
              'seller_info' => json_encode($sellerInfo),
              'product_info' => json_encode($productInfo), // Update product info in final_purchase_order table as well
              'is_closed' => $isClosed, // Update the is_closed column based on the status
          ]);
      
      // Iterate through productInfo to delete items from purchase_order table
    //   foreach ($productInfo as &$product) {
    //       $partNo = $product['part_no'];
    //       $orderNosWithDates = explode(',', $product['order_no']); // Split in case of multiple order numbers
    //       $qty = $product['qty']; // Quantity for the entire entry

    //       // If any product has qty 0, do not close the purchase order
    //       if ($qty == 0) {
    //           $isClosed = 0;
    //           continue; // Skip deletion for this product
    //       }

    //       foreach ($orderNosWithDates as $orderNoWithDate) {
    //           // Extract the order number without the date part
    //           if (preg_match('/(SO\/[^ ]+)/', trim($orderNoWithDate), $matches)) {
    //               $orderNo = trim($matches[1]);

    //               // Perform the deletion for the specific order_no and part_no
    //               DB::table('purchase_order')
    //                   ->where('order_no', $orderNo)
    //                   ->where('part_no', $partNo)
    //                   ->delete();

               
    //           }
    //       }
    //   }

      // Return success message
      return redirect()->route('finalized.purchase.orders')->with('status', 'Purchase order converted successfully!');
  }



    public function showFinalizedPurchaseOrders()
    {
      
      $purchases = DB::table('final_purchase')
      ->join('final_purchase_order', 'final_purchase.purchase_order_no', '=', 'final_purchase_order.purchase_order_no')
      ->join('sellers', 'final_purchase.seller_id', '=', 'sellers.id')
      ->join('users', 'sellers.user_id', '=', 'users.id') // Join with users table
      ->select('final_purchase.*', 'final_purchase_order.product_info', 'users.name as seller_name') // Get seller name from users table
      ->get();

            // echo "<pre>";
            // print_r($purchases);
            // die();

        return view('backend.purchase_order.final_purchase_list', compact('purchases'));
    }


    public function showImportForm()
    {
        return view('backend.purchase_order.purchase_order_excel_import');
    }

    public function importExcel(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'excel_file' => 'required|file|mimes:xls,xlsx'
        ], ['excel_file' => "File is required"]);

        // Get the real path of the uploaded file
        $filePath = $request->file('excel_file');

        $tableName = 'purchase_order';

        try {
            // Attempt to import the file
            Excel::import(new ExternalPurchaseOrder($tableName), $filePath);

            // If no exception occurs, consider it a success
            return redirect()->back()->with('success', 'Data imported successfully!');
        } catch (Exception $e) {
            // If an exception occurs, handle it and return an error message
            return redirect()->back()->with('error', 'Data import failed: ' . $e->getMessage());
        }
    }

    public function export($purchase_no)
    {
      $data = DB::table('final_purchase')
     
      ->join('final_purchase_order', 'final_purchase.purchase_order_no', '=', 'final_purchase_order.purchase_order_no')
      ->where('final_purchase.purchase_no', $purchase_no)
      ->select(
          'final_purchase.purchase_no',
          'final_purchase.purchase_order_no',
          'final_purchase.seller_info',  // Fetch seller_info JSON field
          'final_purchase.seller_invoice_no',
          'final_purchase.seller_invoice_date',
          'final_purchase.product_info'
      )
      ->orderBy('final_purchase.created_at', 'desc')
      ->get();

      // Process the data to filter out products with qty == 0
      $filteredData = $data->map(function ($item) {
          $productInfo = json_decode($item->product_info, true);

          // Filter out products where qty is 0
          $filteredProductInfo = array_filter($productInfo, function ($product) {
              return $product['qty'] != 0;
          });

          // Encode the filtered product info back to JSON
          $item->product_info = json_encode(array_values($filteredProductInfo));

          return $item;
      });

      
        return Excel::download(new FinalPurchaseExport($purchase_no), 'final_purchases.xlsx');
    }
	
	

  //purchase order code end
	
	// sales (nav menu) backend dashboard whatsaap send
	public function sendWhatsAppMessage($order_id)
	{
		
		// Fetching the order directly from the orders table
		$first_order = DB::table('orders')
			->where('id', $order_id)
			->first();

		// Fetching the user who placed the order
		$user = DB::table('users')
				->where('id', $first_order->user_id)
				->first();

		// Fetching the manager's phone number
		$manager_phone_number = DB::table('users')
			->where('id', $user->manager_id)
			->pluck('phone')
			->first();

		// Setting the recipients for the WhatsApp message
		$to = [
			json_decode($first_order->shipping_address)->phone,
			'+919709555576', // Replace with an actual number if needed
			$manager_phone_number
     
		];

		// Fetching the client's address information
		$client_address = DB::table('addresses')
			->where('id', $first_order->address_id)
			->first();

		// Order details for the WhatsApp message
		$company_name = $client_address->company_name;
		$order_code = $first_order->code;
		$date = date('d-m-Y H:i A', $first_order->date);
		$total_amount = $first_order->grand_total;

		// Generating the invoice file path
		$invoiceController = new InvoiceController();
		$file_url = $invoiceController->invoice_file_path($first_order->id);

		// Setting the file name for the invoice
		$file_name = "Order-" . $order_code;

		// Preparing the template data for WhatsApp message
		$templateData = [
			'name' => 'utility_order_template',
			'language' => 'en_US', 
			'components' => [
				[
					'type' => 'header',
					'parameters' => [
						[
							'type' => 'document', 
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
						['type' => 'text', 'text' => $company_name],
						['type' => 'text', 'text' => $order_code],
						['type' => 'text', 'text' => $date],
						['type' => 'text', 'text' => $total_amount]
					],
				],
			],
		];

		// Sending the WhatsApp message to each recipient
		$this->WhatsAppWebService = new WhatsAppWebService();
		foreach ($to as $person_to_send) {
			$response = $this->WhatsAppWebService->sendTemplateMessage($person_to_send, $templateData);
		}
    return response()->json(['message' => 'WhatsApp sent successfully']);
	// return redirect()->back()->with('message', 'whatsapp sent successfully');
	// 	return $response; 
	}

  public function downloadPDF($file_name)
  {
      // Construct the full path to the file in the public directory
      $filePath = public_path('abandoned_cart_pdf/' . $file_name);

      // Check if the file exists
      if (file_exists($filePath)) {
          // Return the file as a download response
          return Response::download($filePath, $file_name);
      } else {
          // Return a 404 error if the file does not exist
          return view('errors.link_expire');
      }
  }

  public function updateSlugs()
  {
    $messageId="wamid.HBgINjI4OTA2MjkVAgARGBI3MDk1RUMwQjUyMzM1RTlENkQA";
    // Set up the database connection dynamically within the function
    config([
        'database.connections.dynamic_mazingbusiness' => [
            'driver' => 'mysql',
            'host' => 'localhost',  // Replace with your cloud database host
            'port' => '3306',  // Replace with your cloud database port
            'database' => 'mazingbusiness',  // The database name is 'mazingbusiness'
            'username' => 'mazingbusiness',   // Replace with your database username
            'password' => 'Gd6de243%',   // Replace with your database password
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]
    ]);

    // Fetch the message data from the cloud_response database
    $cloudResponseData = DB::connection('dynamic_mazingbusiness')
        ->table('cloud_response')  // Assume this is the table storing the message details
        ->where('msg_id', $messageId)
        ->first();

    return $cloudResponseData->status;

      // Get all products where the slug contains a slash
    //   $products = DB::table('products')
    //       ->where('slug', 'LIKE', '%/%')
    //       ->get();

    //   foreach ($products as $product) {
    //       // Replace slashes with hyphens
    //       $newSlug = str_replace('/', '-', $product->slug);

    //       // Update the product slug in the database
    //       DB::table('products')
    //           ->where('id', $product->id)
    //           ->update(['slug' => $newSlug]);
    //   }

    //   return response()->json(['message' => 'Slugs updated successfully.']);
  }

//   public function pushOrder($id)
//     {

       
      
//       // Push order data to Salezing
//       $orderData = DB::table('orders')->where('combined_order_id', $id)->first();
//       $result=array();
//       $result['code']= $orderData->code;
//       $response = Http::withHeaders([
//           'Content-Type' => 'application/json',
//       ])->post('https://mazingbusiness.com/api/v2/order-push', $result);

      
       
//         return redirect()->back();
//     }

    public function pushOrder($id)
    {
        // Fetch order data based on combined_order_id
        $orderData = DB::table('orders')->where('combined_order_id', $id)->first();

        if ($orderData) {
            // Check if a record exists in salezing_logs with the same order code
            $existingLog = DB::table('salezing_logs')->where('code', $orderData->code)->first();

            // If a record exists, delete it
            if ($existingLog) {
                DB::table('salezing_logs')->where('code', $orderData->code)->delete();
            }

            // Prepare the data to be pushed to the Salezing API
            $result = array();
            $result['code'] = $orderData->code;

            // Push order data to Salezing API
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://mazingbusiness.com/api/v2/order-push', $result);

            // Handle the response from the API
            if ($response->successful()) {
                // Set a success message
                session()->flash('success', 'Order pushed to Salezing successfully!');
            } else {
                // Set a failure message
                session()->flash('error', 'Failed to push order to Salezing.');
            }

           
        }else {
            // If no order data found, set an error message
            session()->flash('error', 'Order not found.');
        }
     
        // Redirect back to the previous page
        return redirect()->back();
    }

    // ------------------------- Offer Section ------------------------
    public function getOffers(Request $request){
        $currentDate = now();
        $productId = $request->input('product_id');
        $offers = Offer::with('offerProducts')
                ->where('status', 1)
                ->whereDate('offer_validity_start', '<=', $currentDate)
                ->whereDate('offer_validity_end', '>=', $currentDate)
                ->whereHas('offerProducts', function ($query) use ($productId) {
                    $query->where('product_id', $productId);
                })->get();

        $offers = $this->processProducts($offers);       

        $html = view('frontend.partials.offerDetails', compact('offers'))->render();
        return response()->json(['html' => $html]);
    }

    private function processProducts($offers) {
        $user_warehouse_id="";
        $user_id="";
        foreach ($offers as $offer) {
            $offerProducts = $offer->offerProducts;
            foreach($offerProducts as $offerProduct){
                if($offerProduct->offer_price == '' OR $offerProduct->offer_price == '0'){
                    $price = $markup = $wmarkup = 0;
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

                    $product_mrp = Product::where('id', $offerProduct->product_id)->select('mrp')->first();
                    
                    $price = $product_mrp->mrp;
                    
                    if (!is_numeric($price)) {
                        $price = 0;
                    }

                    $price = $price * ((100 - $discount) / 100);
                    $offerProduct->offer_price = $price;
                    
                    // $price = $price * 131.6 / 100;
                    // $product->home_base_price = $price;
                }
            }            
        }
        // die;
        return $offers;
    }

    public function applyOffer($offer_id){
        $offer_id = decrypt($offer_id);
        $currentDate = now();
        $offers = Offer::with('offerProducts','offerComplementoryProducts.offerComplementoryProductDetails')
                ->where('status', 1)
                ->whereDate('offer_validity_start', '<=', $currentDate)
                ->whereDate('offer_validity_end', '>=', $currentDate)
                ->where('id', $offer_id)->first();

        if($offers == NULL){
            return redirect()->route('cart')->with('statusErrorMsg', '* Offer is no loanger valid now.');
        }else{
            // Check offer had applied previously or not for single offer applied at a time.
            $appliedOffer = Cart::where('user_id', Auth::user()->id) ->orWhere('customer_id', Auth::user()->id)->where('applied_offer_id', '!=', NULL )->get();
            if (!$appliedOffer->isEmpty()){
                foreach($appliedOffer as $aoKey=>$aoValue){
                    $cartProduct = Cart::where('id',$aoValue->id)->first();
                    if($aoValue->complementary_item == 1){
                        $cartProduct->delete();
                    }else{
                        $product = Product::where('id',$aoValue->product_id)->first();
                        if($aoValue->quantity >= $product->piece_by_carton ){
                            $price = home_bulk_discounted_price($product,false)['price'];
                        }else{
                            $price = home_discounted_price($product,false)['price'];
                        }
                        // $price = $product->mrp * ((100 - Auth::user()->discount) / 100);
                        
                        $cartProduct->applied_offer_id = null;
                        $cartProduct->price = $price;
                        $cartProduct->save();
                    }                    
                }
            }
            // Offer Products discount options
            $offerProducts = $offers->offerProducts;
            $rewards = 0;  
            // echo "<pre>"; print_r($offerProducts); die;
            if (!$offerProducts->isEmpty()) {
                foreach ($offerProducts as $opKey => $opValue) {
                    // Fetch the cart product for the current user or customer
                    $cartProduct = Cart::where(function ($query) use ($opValue) {
                        $query->where('user_id', Auth::user()->id)
                            ->orWhere('customer_id', Auth::user()->id);
                    })->where('product_id', $opValue->product_id)->first();
                    
                    // Fetch product details
                    $product = Product::find($opValue->product_id); // Use find for simplicity
                    
                    if (!$product) {
                        continue; // Skip if product doesn't exist
                    }

                    // Calculate the base price with user-specific discount
                    // $price = $product->mrp * ((100 - Auth::user()->discount) / 100);
                    

                    if ($offers->offer_type == 2) { // Type 2 offer logic
                        if ($offers->value_type == 'percent') {
                            $rewards = ($offers->offer_value * ($offers->discount_percent)) / 100;
                        } else {
                            $rewards = $offers->discount_percent;
                        }
                    } else { // Other offer types
                        if ($opValue->discount_type == 'percent') {
                            $price = $price * ((100 - $opValue->offer_discount_percent) / 100);
                        } else {
                            $price = $opValue->offer_price;
                        }
                    }
                    if($price == 0){
                        if($aoValue->quantity >= $product->piece_by_carton ){
                            $price = home_bulk_discounted_price($product,false)['price'];
                        }else{
                            $price = home_discounted_price($product,false)['price'];
                        }
                    }
                    // Update cart product if it exists
                    if ($cartProduct != null) {
                        if ($offers->offer_type == 2) {
                            if($cartProduct->quantity >= $product->piece_by_carton ){
                                $price = home_bulk_discounted_price($product,false)['price'];
                            }else{
                                $price = home_discounted_price($product,false)['price'];
                            }
                        }
                        // If Offer type is Item Wise
                        // if ($offers->offer_type == 2 AND $opValue->offer_price != "") {
                        //     $price = $opValue->offer_price;
                        // }
                        // echo "<pre>"; print_r($price); die;
                        $cartProduct->applied_offer_id = $offers->id;
                        $cartProduct->offer_rewards = $rewards;
                        $cartProduct->price = $price;
                        $cartProduct->save();
                    }
                }
            } else { // Handle cases when no specific offer products are provided
                if ($offers->value_type == 'percent') {
                    $rewards = ($offers->offer_value * ($offers->discount_percent)) / 100;
                } else {
                    $rewards = $offers->discount_percent;
                }
                Cart::where('user_id', Auth::user()->id)->orWhere('customer_id', Auth::user()->id)->update(
                    [
                        'applied_offer_id'      => $offers->id,
                        'offer_rewards' => $rewards,
                    ]
                );
            }
            // Offer Complementory Products
            $offerComplementoryProducts = $offers->offerComplementoryProducts;
            if($offerComplementoryProducts != NULL){
                foreach($offerComplementoryProducts as $ocpKey=>$ocpValue){
                    $data = array();
                    $data['user_id'] = Auth::user()->id;
                    $data['owner_id']   = Auth::user()->id;
                    $data['product_id'] = $ocpValue->offerComplementoryProductDetails->id;
                    $data['owner_id']   = $ocpValue->offerComplementoryProductDetails->user_id;
                    $data['variation']   = $ocpValue->offerComplementoryProductDetails->slug;
                    // if($request['type'] == "piece"){
                    //     $price = $product->mrp * ((100 - $discount) / 100);
                    // }elseif($request['type'] == "bulk"){
                    //     $price = $request['order_by_carton_price'];
                    // }
                    $data['price']= '1';
                    $data['quantity']= $ocpValue->free_product_qty;
                    $data['tax']= 0;
                    $data['shipping_cost']= 0;
                    $data['product_referral_code'] = null;
                    $data['cash_on_delivery']= $ocpValue->offerComplementoryProductDetails->cash_on_delivery;
                    $data['cash_and_carry_item']= $ocpValue->offerComplementoryProductDetails->cash_and_carry_item;
                    $data['digital']= $ocpValue->offerComplementoryProductDetails->digital;
                    $data['complementary_item'] = '1';
                    $data['applied_offer_id'] = $offers->id;
                    Cart::create($data);
                    // echo "<pre>"; print_r($data); die;
                }
            }
            return redirect()->route('cart')->with('statusSuccessMsg', $offers->offer_name.' offer applied.');
        }
    }

    public function removeOffer($offer_id){
        $offer_id = decrypt($offer_id);
        $offers = $this->removeOfferFromTable($offer_id);
        return redirect()->route('cart')->with('statusSuccessMsg', $offers->offer_name.' offer removed.');
    }

    public function removeOfferFromTable($offer_id){
        $currentDate = now();
        $offers = Offer::with('offerProducts')
                ->where('status', 1)
                ->whereDate('offer_validity_start', '<=', $currentDate)
                ->whereDate('offer_validity_end', '>=', $currentDate)
                ->where('id', $offer_id)->first();
        if($offers == NULL){
            $cartProduct = Cart::where(function ($query) {
                $query->where('user_id', Auth::user()->id)
                      ->orWhere('customer_id', Auth::user()->id);
            })->where('applied_offer_id', $offer_id)->get();
            if(!$cartProduct->isEmpty()){
                foreach($cartProduct as $cpKey=>$cpValue){
                    $updateCart = Cart::where('id',$cpValue->id)->first();
                    $product = Product::where('id',$cpValue->product_id)->first();
                    // $price = $product->mrp * ((100 - Auth::user()->discount) / 100);
                    if($cpValue->quantity >= $product->piece_by_carton ){
                        $price = home_bulk_discounted_price($product,false)['price'];
                    }else{
                        $price = home_discounted_price($product,false)['price'];
                    }
                    $updateCart->applied_offer_id = null;
                    $updateCart->offer_rewards = null;
                    $updateCart->price = $price;
                    $updateCart->save();
                }
            }
            return redirect()->route('cart')->with('statusErrorMsg', '* Offer is no loanger valid now.');
        }else{
            $offerProducts = $offers->offerProducts;
            if (!$offerProducts->isEmpty()) {
                foreach($offerProducts as $opKey=>$opValue){
                    $cartProduct = Cart::where(function ($query) use ($opValue) {
                        $query->where('user_id', Auth::user()->id)
                            ->orWhere('customer_id', Auth::user()->id);
                    })->where('product_id', $opValue->product_id)->first();
                    
                    $product = Product::where('id',$opValue->product_id)->first();
                    // $price = $product->mrp * ((100 - Auth::user()->discount) / 100);
                    if($cartProduct != NULL){
                        if($cartProduct->quantity >= $product->piece_by_carton ){
                            $price = home_bulk_discounted_price($product,false)['price'];
                        }else{
                            $price = home_discounted_price($product,false)['price'];
                        }
                        $cartProduct->applied_offer_id = null;
                        $cartProduct->price = $price;
                        $cartProduct->offer_rewards = null;
                        $cartProduct->save();
                    }                
                }
                $cartProduct = Cart::where('applied_offer_id', $offer_id)
                ->where('complementary_item', '1')
                ->where(function ($query) {
                    $query->where('user_id', Auth::id())
                        ->orWhere('customer_id', Auth::id());
                })
                ->delete();
            }else{
                if ($offers->value_type === 'percent') {
                    $rewards = ($offers->offer_value * ($offers->discount_percent)) / 100;
                } else {
                    $rewards = $offers->discount_percent;
                }

                $cartProduct = Cart::where('applied_offer_id', $offer_id)
                ->where('complementary_item', '1')
                ->where(function ($query) {
                    $query->where('user_id', Auth::id())
                        ->orWhere('customer_id', Auth::id());
                })
                ->delete();
                Cart::where('user_id', Auth::user()->id)->orWhere('customer_id', Auth::user()->id)->update(
                    [
                        'applied_offer_id' => NULL,
                        'offer_rewards' => NULL,
                    ]
                );
            }    
            return $offers;
        }
    }

    public function addOfferTag($carts){
        $userDetails = User::with(['get_addresses' => function ($query) {
            $query->where('set_default', 1);
        }])->where('id', Auth::user()->id)->first();
        // echo "<pre>"; print_r($userDetails);die;
        $state_id = optional($userDetails->get_addresses->first())->state_id
         ?? ($userDetails->state_id ?? null);
        $currentDate = Carbon::now(); // Get the current date and time
        foreach($carts as $cKey=>$cValue){
            $offerCount = 0;
            $productId=$cValue->product_id;
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
                $cValue->offer = $offers;
            }else{
                $cValue->offer = "";
            }
        }
        return $carts;
    }

    public function checkValidOffer(){
        $currentDate = now();

        $userDetails = User::with('get_addresses')->where('id',Auth::user()->id)->first();        
        $state_id = $userDetails->get_addresses[0]->state_id;

        $cartItems = Cart::where('user_id', Auth::user()->id)
        ->orWhere('customer_id', Auth::user()->id)
        ->get(['product_id', 'quantity', 'price'])
        ->groupBy('product_id');

        $cartProductIds = $cartItems->keys()->toArray();
        // Sum quantities for each product
        $cartQuantities = $cartItems->mapWithKeys(function ($items, $productId) {
            return [$productId => $items->sum('quantity')];
        })->toArray();
        // Prepare prices for each product (assuming all prices for a given product are the same)
        $cartItemPrice = $cartItems->mapWithKeys(function ($items, $productId) {
            return [$productId => $items->first()->price * $items->sum('quantity')]; // Use the price of the first item in the group
        })->toArray();

        $achiveOfferArray = array();

        $offers = Offer::with('offerProducts')
            ->where('status', 1)
            ->where(function ($query) use ($userDetails) {
                $query->where('manager_id', $userDetails->manager_id)
                    ->orWhereNull('manager_id');
            })
            ->where(function ($query) use ($state_id) {
                $query->where('state_id', $state_id)
                    ->orWhereNull('state_id');
            })
            ->whereDate('offer_validity_start', '<=', $currentDate)
            ->whereDate('offer_validity_end', '>=', $currentDate)
            ->get()
            ->filter(function ($offer) use ($cartProductIds, $cartQuantities, $cartItemPrice, &$achiveOfferArray) {

                $getLogedUserAppliedOfferCount = Order::where('user_id', Auth::user()->id)
                    ->where('applied_offer_id', $offer->id)->count();

                $getAppliedOfferCount = Order::where('applied_offer_id', $offer->id)->count();

                $validOfferFlag = ($getLogedUserAppliedOfferCount < $offer->per_user)
                                && ($getAppliedOfferCount < $offer->max_uses);

                // store per-product messages
                $perProductAchieve = [];

                $offerProductsArray = $offer->offerProducts->pluck('product_id')->toArray();

                if ($validOfferFlag) {
                    if ($offer->offer_type == 1) {
                        // ITEM WISE
                        $offerProductNames = $offer->offerProducts->pluck('name', 'product_id')->toArray();
                        $minQtyByProduct   = $offer->offerProducts->pluck('min_qty', 'product_id')->toArray();

                        if (!empty(array_intersect(array_keys($minQtyByProduct), $cartProductIds))) {
                            foreach ($minQtyByProduct as $productId => $minQty) {
                                if (in_array($productId, $cartProductIds)) {
                                    $productName = $offerProductNames[$productId] ?? 'Product';
                                    $needQty     = isset($cartQuantities[$productId])
                                                    ? max(0, $minQty - $cartQuantities[$productId])
                                                    : $minQty;

                                    if ($needQty > 0) {
                                        $msg = "Add <b>$needQty</b> more of $productName to get OFFER <b>{$offer->offer_name}</b>";
                                        $achiveOfferArray[]       = $msg;
                                        $perProductAchieve[$productId] = $msg;
                                    }
                                }
                            }
                        }

                    } elseif ($offer->offer_type == 2) {
                        // TOTAL
                        $offerProducts     = $offer->offerProducts->pluck('min_qty', 'product_id')->toArray();
                        $offerProductNames = $offer->offerProducts->pluck('name')->toArray();
                        $offerValue        = $offer->offer_value;
                        $offerSubTotal     = 0;

                        if (count($offerProducts) > 0) {
                            foreach ($offerProducts as $productId => $minQty) {
                                if (isset($cartQuantities[$productId])) {
                                    $offerSubTotal += $cartItemPrice[$productId] ?? 0;
                                }
                            }
                            if ($offerSubTotal < $offerValue) {
                                $msg = "Add more <b>₹" . ($offerValue - $offerSubTotal) . "</b> on "
                                    . implode(', OR ', $offerProductNames)
                                    . " to get OFFER <b>{$offer->offer_name}</b>";
                                $achiveOfferArray[] = $msg;
                                foreach (array_keys($offerProducts) as $pid) {
                                    $perProductAchieve[$pid] = $msg;
                                }
                            }
                        } else {
                            // no specific products, subtotal check
                            $cartTotal = array_sum($cartItemPrice);
                            if ($cartTotal < $offerValue) {
                                $msg = "Add more <b>₹" . ($offerValue - $cartTotal) . "</b> to get OFFER <b>{$offer->offer_name}</b>";
                                $achiveOfferArray[] = $msg;
                            }
                        }

                    } elseif ($offer->offer_type == 3) {
                        // COMPLEMENTARY
                        $offerProducts     = $offer->offerProducts->pluck('min_qty', 'product_id')->toArray();
                        $offerProductNames = $offer->offerProducts->pluck('name', 'product_id')->toArray();

                        foreach ($offerProducts as $productId => $minQty) {
                            if (!isset($cartQuantities[$productId]) || $cartQuantities[$productId] < $minQty) {
                                $needQty     = isset($cartQuantities[$productId])
                                                ? max(0, $minQty - $cartQuantities[$productId])
                                                : $minQty;
                                $productName = $offerProductNames[$productId] ?? 'Product';

                                if ($needQty > 0) {
                                    $msg = "Add <b>$needQty</b> more of $productName to get OFFER <b>{$offer->offer_name}</b>";
                                    $achiveOfferArray[]       = $msg;
                                    $perProductAchieve[$productId] = $msg;
                                }
                            }
                        }
                    }
                }

                // inject achive_offer into each related product
                $offer->setRelation('offerProducts', $offer->offerProducts->map(function ($prod) use ($perProductAchieve) {
                    $prod->forceFill(['achive_offer' => $perProductAchieve[$prod->product_id] ?? null]);
                    $prod->makeVisible('achive_offer');
                    return $prod;
                }));
                return $validOfferFlag;
            });


            // echo "<pre>"; print_r($offers); die;
        return ["offers" => $offers, "achiveOfferArray" => $achiveOfferArray];
    }

    public function allActiveOffer(){
        $currentDate = now();

        $userDetails = User::with('get_addresses')->where('id',Auth::user()->id)->first();        
        $state_id = $userDetails->get_addresses[0]->state_id;

        $cartItems = Cart::where('user_id', Auth::user()->id)
        ->orWhere('customer_id', Auth::user()->id)
        ->get(['product_id', 'quantity', 'price'])
        ->groupBy('product_id');

        $cartProductIds = $cartItems->keys()->toArray();
        // Sum quantities for each product
        $cartQuantities = $cartItems->mapWithKeys(function ($items, $productId) {
            return [$productId => $items->sum('quantity')];
        })->toArray();
        // Prepare prices for each product (assuming all prices for a given product are the same)
        $cartItemPrice = $cartItems->mapWithKeys(function ($items, $productId) {
            return [$productId => $items->first()->price * $items->sum('quantity')]; // Use the price of the first item in the group
        })->toArray();

        $achiveOfferArray = array();

        $offers = Offer::with('offerProducts')
    ->where('status', 1)
    ->where(function ($query) use ($userDetails) {
        $query->where('manager_id', $userDetails->manager_id)
              ->orWhereNull('manager_id');
    })
    ->where(function ($query) use ($state_id) {
        $query->where('state_id', $state_id)
              ->orWhereNull('state_id');
    })
    ->whereDate('offer_validity_start', '<=', $currentDate)
    ->whereDate('offer_validity_end', '>=', $currentDate)
    ->get()
    ->map(function ($offer) use ($cartProductIds, $cartQuantities, $cartItemPrice, &$achiveOfferArray) {

        // Build per-product messages but DO NOT filter the offer out
        $perProductAchieve = [];

        // validity gates only affect messaging, not inclusion
        $userUsed  = Order::where('user_id', Auth::id())->where('applied_offer_id', $offer->id)->count();
        $offerUsed = Order::where('applied_offer_id', $offer->id)->count();
        $validOfferFlag = ($userUsed < ($offer->per_user ?? PHP_INT_MAX))
                       && ($offerUsed < ($offer->max_uses ?? PHP_INT_MAX));

        $offerProductNames = $offer->offerProducts->pluck('name', 'product_id')->toArray();
        $minQtyByProduct   = $offer->offerProducts->pluck('min_qty', 'product_id')->toArray();

        if ($validOfferFlag) {
            if ($offer->offer_type == 1) {
                // ITEM WISE
                if (!empty(array_intersect(array_keys($minQtyByProduct), $cartProductIds))) {
                    foreach ($minQtyByProduct as $productId => $minQty) {
                        if (in_array($productId, $cartProductIds)) {
                            $needQty = isset($cartQuantities[$productId])
                                ? max(0, $minQty - $cartQuantities[$productId])
                                : $minQty;

                            if ($needQty > 0) {
                                $name = $offerProductNames[$productId] ?? 'Product';
                                $msg  = "Add <b>{$needQty}</b> more of {$name} to get OFFER <b>{$offer->offer_name}</b>";
                                $achiveOfferArray[] = $msg;
                                $perProductAchieve[$productId] = $msg;
                            }
                        }
                    }
                }

            } elseif ($offer->offer_type == 2) {
                // TOTAL
                $offerValue    = $offer->offer_value ?? 0;
                $offerProducts = $minQtyByProduct; // same keys

                if (count($offerProducts) > 0) {
                    $offerSubTotal = 0;
                    foreach ($offerProducts as $productId => $_min) {
                        if (isset($cartQuantities[$productId])) {
                            $offerSubTotal += $cartItemPrice[$productId] ?? 0;
                        }
                    }
                    if ($offerSubTotal < $offerValue) {
                        $msg = "Add more <b>₹" . ($offerValue - $offerSubTotal) . "</b> on "
                             . implode(', OR ', array_values($offerProductNames))
                             . " to get OFFER <b>{$offer->offer_name}</b>";
                        $achiveOfferArray[] = $msg;
                        foreach (array_keys($offerProducts) as $pid) {
                            $perProductAchieve[$pid] = $msg;
                        }
                    }
                } else {
                    // Global subtotal offer (no specific products)
                    $cartTotal = array_sum($cartItemPrice);
                    if ($cartTotal < $offerValue) {
                        $msg = "Add more <b>₹" . ($offerValue - $cartTotal) . "</b> to get OFFER <b>{$offer->offer_name}</b>";
                        $achiveOfferArray[] = $msg;
                        // no per-product messages in this case
                    }
                }

            } elseif ($offer->offer_type == 3) {
                // COMPLEMENTARY
                foreach ($minQtyByProduct as $productId => $minQty) {
                    $have = $cartQuantities[$productId] ?? 0;
                    if ($have < $minQty) {
                        $need = max(0, $minQty - $have);
                        if ($need > 0) {
                            $name = $offerProductNames[$productId] ?? 'Product';
                            $msg  = "Add <b>{$need}</b> more of {$name} to get OFFER <b>{$offer->offer_name}</b>";
                            $achiveOfferArray[] = $msg;
                            $perProductAchieve[$productId] = $msg;
                        }
                    }
                }
            }
        }

        // Attach achive_offer per product (do not remove any product)
        $offer->setRelation('offerProducts', $offer->offerProducts->map(function ($prod) use ($perProductAchieve) {
            $prod->forceFill(['achive_offer' => $perProductAchieve[$prod->product_id] ?? null]);
            $prod->makeVisible('achive_offer'); // ensures it appears in toArray()/JSON
            return $prod;
        }));

        return $offer; // keep the offer
    });

            // echo "<pre>"; print_r($offers);
        return ["offers" => $offers, "achiveOfferArray" => $achiveOfferArray];
    }

    public function addOfferProductToCart(Request $request) {
        $offer_id = $request->offer_id;
        $product_id_array = explode(',',$request->product_id);
        $addAllItem = $request->addAllItem;
        $currentDate = now();
        foreach($product_id_array as $pKey => $pValue){
            $product = Product::find($pValue);
            $carts   = array();
            $data    = array();
            $user_id         = Auth::user()->id;
            $data['user_id'] = $user_id;
            $carts = Cart::where(function ($query) use ($user_id) {
                $query->where('user_id', $user_id)
                      ->orWhere('customer_id', $user_id);
            })->where('product_id', $pValue)->get();
            
            $data['product_id'] = $product->id;
            $data['owner_id']   = $product->user_id;

            $str     = '';
            $tax     = $ctax     = $price     = $carton_price     = 0;
            $wmarkup = 0;

            $str = $product->slug;
            $data['variation'] = $str;

            $product_stock = $product->stocks->where('variant', $str);
            
            //$price=calculate_discounted_price($product->mrp,false)['net_selling_price'];
            
            $user = Auth::user();
            $discount = 0;
            if ($user) {
                $discount = $user->discount;
            }
            if(!is_numeric($discount) || $discount == 0) {
                $discount = 20;
            }
            
            $price = $product->mrp * ((100 - $discount) / 100);

            $data['quantity']  = $request['quantity'];
            $data['price']     = price_less_than_50($price,false);
            $data['tax']       = $tax;
            $data['shipping_cost']         = 0;
            $data['product_referral_code'] = null;
            $data['cash_on_delivery']      = $product->cash_on_delivery;
            $data['cash_and_carry_item']      = $product->cash_and_carry_item;
            $data['digital']               = $product->digital;

            // Get Offer Qty
            $getOfferDetails = Offer::with(['offerProducts' => function ($query) use ($pValue) {
                $query->where('product_id', $pValue);
            }])
            ->where('status', 1)
            ->whereDate('offer_validity_start', '<=', $currentDate)
            ->whereDate('offer_validity_end', '>=', $currentDate)
            ->where('id', $offer_id)
            ->first();
            // echo "<pre>"; print_r($getOfferDetails->offerProducts); die;
            if($getOfferDetails->offerProducts[0]->min_qty != "" AND $getOfferDetails->offerProducts[0]->min_qty > 0){
                $data['quantity'] = $getOfferDetails->offerProducts[0]->min_qty;
            }else{
                $data['quantity'] = 1;
            }
            
            if ($carts && count($carts) <= 0) {
                Cart::create($data);
            }elseif($carts[0]->quantity < $getOfferDetails->offerProducts[0]->min_qty){
                Cart::where('id',$carts[0]->id)->update(['quantity'=> $getOfferDetails->offerProducts[0]->min_qty]);
            }
        }
        if($addAllItem == 1){

            $currentDate = now();
            $offers = Offer::with('offerProducts','offerComplementoryProducts.offerComplementoryProductDetails')
                    ->where('status', 1)
                    ->whereDate('offer_validity_start', '<=', $currentDate)
                    ->whereDate('offer_validity_end', '>=', $currentDate)
                    ->where('id', $offer_id)->first();
                    
            // Check offer had applied previously or not for single offer applied at a time.
            $appliedOffer = Cart::where('user_id', Auth::user()->id) ->orWhere('customer_id', Auth::user()->id)->where('applied_offer_id', '!=', NULL )->get();
            if (!$appliedOffer->isEmpty()){
                foreach($appliedOffer as $aoKey=>$aoValue){
                    $cartProduct = Cart::where('id',$aoValue->id)->first();
                    if($aoValue->complementary_item == 1){
                        $cartProduct->delete();
                    }else{
                        $product = Product::where('id',$aoValue->product_id)->first();
                        if($aoValue->quantity >= $product->piece_by_carton ){
                            $price = home_bulk_discounted_price($product,false)['price'];
                        }else{
                            $price = home_discounted_price($product,false)['price'];
                        }
                        // $price = $product->mrp * ((100 - Auth::user()->discount) / 100);
                        
                        $cartProduct->applied_offer_id = null;
                        $cartProduct->price = $price;
                        $cartProduct->save();
                    }                    
                }
            }
            // Offer Products discount options
            $offerProducts = $offers->offerProducts;
            $rewards = 0;  
            // echo "<pre>"; print_r($offerProducts); die;
            if (!$offerProducts->isEmpty()) {
                foreach ($offerProducts as $opKey => $opValue) {
                    // Fetch the cart product for the current user or customer
                    $cartProduct = Cart::where(function ($query) use ($opValue) {
                        $query->where('user_id', Auth::user()->id)
                            ->orWhere('customer_id', Auth::user()->id);
                    })->where('product_id', $opValue->product_id)->first();
                    
                    // Fetch product details
                    $product = Product::find($opValue->product_id); // Use find for simplicity
                    
                    if (!$product) {
                        continue; // Skip if product doesn't exist
                    }

                    // Calculate the base price with user-specific discount
                    // $price = $product->mrp * ((100 - Auth::user()->discount) / 100);
                    

                    if ($offers->offer_type == 2) { // Type 2 offer logic
                        if ($offers->value_type == 'percent') {
                            $rewards = ($offers->offer_value * ($offers->discount_percent)) / 100;
                        } else {
                            $rewards = $offers->discount_percent;
                        }
                    } else { // Other offer types
                        if ($opValue->discount_type == 'percent') {
                            $price = $price * ((100 - $opValue->offer_discount_percent) / 100);
                        } else {
                            $price = $opValue->offer_price;
                        }
                    }
                    if($price == 0){
                        if($aoValue->quantity >= $product->piece_by_carton ){
                            $price = home_bulk_discounted_price($product,false)['price'];
                        }else{
                            $price = home_discounted_price($product,false)['price'];
                        }
                    }
                    // Update cart product if it exists
                    if ($cartProduct != null) {
                        if ($offers->offer_type == 2) {
                            if($cartProduct->quantity >= $product->piece_by_carton ){
                                $price = home_bulk_discounted_price($product,false)['price'];
                            }else{
                                $price = home_discounted_price($product,false)['price'];
                            }
                        }
                        // If Offer type is Item Wise
                        // if ($offers->offer_type == 2 AND $opValue->offer_price != "") {
                        //     $price = $opValue->offer_price;
                        // }
                        // echo "<pre>"; print_r($price); die;
                        $cartProduct->applied_offer_id = $offers->id;
                        $cartProduct->offer_rewards = $rewards;
                        $cartProduct->price = $price;
                        $cartProduct->save();
                    }
                }
            } else { // Handle cases when no specific offer products are provided
                if ($offers->value_type == 'percent') {
                    $rewards = ($offers->offer_value * ($offers->discount_percent)) / 100;
                } else {
                    $rewards = $offers->discount_percent;
                }
                Cart::where('user_id', Auth::user()->id)->orWhere('customer_id', Auth::user()->id)->update(
                    [
                        'applied_offer_id'      => $offers->id,
                        'offer_rewards' => $rewards,
                    ]
                );
            }
            // Offer Complementory Products
            $offerComplementoryProducts = $offers->offerComplementoryProducts;
            if($offerComplementoryProducts != NULL){
                foreach($offerComplementoryProducts as $ocpKey=>$ocpValue){
                    $data = array();
                    $data['user_id'] = Auth::user()->id;
                    $data['owner_id']   = Auth::user()->id;
                    $data['product_id'] = $ocpValue->offerComplementoryProductDetails->id;
                    $data['owner_id']   = $ocpValue->offerComplementoryProductDetails->user_id;
                    $data['variation']   = $ocpValue->offerComplementoryProductDetails->slug;
                    // if($request['type'] == "piece"){
                    //     $price = $product->mrp * ((100 - $discount) / 100);
                    // }elseif($request['type'] == "bulk"){
                    //     $price = $request['order_by_carton_price'];
                    // }
                    $data['price']= '1';
                    $data['quantity']= $ocpValue->free_product_qty;
                    $data['tax']= 0;
                    $data['shipping_cost']= 0;
                    $data['product_referral_code'] = null;
                    $data['cash_on_delivery']= $ocpValue->offerComplementoryProductDetails->cash_on_delivery;
                    $data['cash_and_carry_item']= $ocpValue->offerComplementoryProductDetails->cash_and_carry_item;
                    $data['digital']= $ocpValue->offerComplementoryProductDetails->digital;
                    $data['complementary_item'] = '1';
                    $data['applied_offer_id'] = $offers->id;
                    Cart::create($data);
                }
            }

        }
        $carts   = Cart::where('user_id', $user_id)->orWhere('customer_id',$user_id)->get();
        return redirect()->route('cart');
    }
}