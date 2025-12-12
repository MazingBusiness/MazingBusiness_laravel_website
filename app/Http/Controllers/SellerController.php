<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\State;
use App\Models\User;
use App\Http\Controllers\ZohoController;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SellerController extends Controller {
  public function __construct() {
    // Staff Permission Check
    $this->middleware(['permission:view_all_seller'])->only('index');
    $this->middleware(['permission:view_seller_profile'])->only('profile_modal');
    $this->middleware(['permission:login_as_seller'])->only('login');
    $this->middleware(['permission:pay_to_seller'])->only('payment_modal');
    $this->middleware(['permission:edit_seller'])->only('edit');
    $this->middleware(['permission:delete_seller'])->only('destroy');
    $this->middleware(['permission:ban_seller'])->only('ban');
  }

  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index(Request $request) {
    $sort_search = null;
    $approved    = null;
    $sellers     = Seller::with('user','warehouseProducts')->latest();
    if ($request->has('search')) {
      $sort_search = $request->search;
      $user_ids    = User::where(function ($user) use ($sort_search) {
        $user->where('name', 'like', '%' . $sort_search . '%')->orWhere('email', 'like', '%' . $sort_search . '%');
      })->pluck('id')->toArray();
      $sellers = $sellers->where(function ($sellers) use ($user_ids) {
        $sellers->whereIn('user_id', $user_ids);
      });
    }
    if ($request->approved_status != null) {
      $approved = $request->approved_status;
      $sellers  = $sellers->where('verification_status', $approved);
    }
    $sellers = $sellers->paginate(15);
    return view('backend.sellers.index', compact('sellers', 'sort_search', 'approved'));
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create() {
    return view('backend.sellers.create');
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
  {
      // ❗ seller ke duplicate check ko GSTIN par shift karo (email dummy hoga)
      if (Seller::where('gstin', $request->gstin)->exists()) {
          flash(translate('Seller already exists!'))->error();
          return back();
      }

      // real email (signup API ke liye)
      $realEmail  = $request->email;
      // seller ke liye dummy email (DB me save hoga)
      $dummyEmail = $this->makeSellerDummyEmail($request->name, $request->gstin);

      $user                    = new \App\Models\User;
      $user->name              = $request->name;
      $user->warehouse_id      = $request->warehouse_id;
      $user->email             = $dummyEmail; // 👈 DB me dummy
      $user->address           = $request->address;
      $user->country           = optional(\App\Models\Country::find($request->country_id))->name;
      $user->state             = optional(\App\Models\State::find($request->state_id))->name;
      $user->city              = optional(\App\Models\City::find($request->city_id))->name;
      $user->postal_code       = $request->postal_code;
      $user->phone             = $request->phone;
      $user->user_type         = "seller";
      $user->password          = \Hash::make($request->password);
      $user->email_verified_at = now();

      if ($user->save()) {
          $seller                      = new \App\Models\Seller;
          $seller->user_id             = $user->id;
          $seller->verification_status = 1;
          $seller->gstin               = $request->gstin;
          $seller->bank_name           = $request->bank_name;
          $seller->bank_acc_name       = $request->bank_acc_name;
          $seller->bank_acc_no         = $request->bank_acc_no;
          $seller->bank_ifsc_code      = $request->bank_ifsc_code;
          if ($request->has('bank_acc_no')) {
              $seller->bank_payment_status = 1;
          }
          if ($seller->save()) {
              $shop            = new \App\Models\Shop;
              $shop->seller_id = $seller->id;
              $shop->name      = $request->shop_name;
              $shop->phone     = $request->phone;
              $shop->address   = $request->address . ', ' . $user->city . ' - ' . $user->postal_code . ', ' . $user->state . ', ' . $user->country;
              $shop->save();

              // ✅ GST verify + sign-up (real email ke saath)
              $partyCode = $this->signupWithGst($user, $request, $realEmail);

              // Seller Creation In Zoho (agar chahiye to enable)
               $zoho = new ZohoController();
               $res  = $zoho->createNewSellerInZoho($user->id);

               // ✅ Customer Creation In Zoho: party_code pass karo
              if ($partyCode) {
                  $custRes = $zoho->createNewCustomerInZoho($partyCode);
                  // 1-liner (array form):
                  $zohoCustomerId = $custRes->getData(true)['zoho_customer_id'] ?? null;
                  // DB se existing user id nikalna jo party_code match kare
                  $customerUserId = User::where('party_code', $partyCode)->value('id');
                  
                  $seller->customer_user_id = $customerUserId;
                  $seller->save();
                  
              }

              flash(translate('Seller has been inserted successfully'))->success();
              return redirect()->route('sellers.index');
          }
      }

      flash(translate('Something went wrong'))->error();
      return back();
  }


  private function signupWithGst(User $user, Request $request, string $realEmail): ?string
  {
      try {
          // 1) Verify GST -> gst_data
          $verifyUrl = 'http://mazingbusiness.com/mazing_business_react/api/user/verify-gst-for-registration';
          $verifyRes = Http::asForm()->post($verifyUrl, [
              'gst_number' => $request->gstin,
          ]);

          if (!$verifyRes->ok() || $verifyRes->json('res') !== true) {
              \Log::warning('GST verify failed', ['status'=>$verifyRes->status(),'body'=>$verifyRes->body()]);
              return null;
          }

          $gstDataJson = json_encode($verifyRes->json('gst_data'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          $phone10     = substr(preg_replace('/\D/', '', (string) $request->phone), -10);

          // 2) Sign-up payload
          $payload = [
              'gstin'        => $request->gstin,
              'gst_data'     => $gstDataJson,
              'phone'        => $phone10,
              'name'         => $request->name,
              'aadhar_card'  => $request->aadhar_card,
              'address'      => $request->address,
              'address2'     => $request->address2 ?? $request->address,
              'city'         => $user->city,
              'company_name' => $request->shop_name ?? $request->company_name,
              'postal_code'  => $request->postal_code,
              'state'        => $request->state_id ?? $user->state,
              'email'        => $realEmail,
          ];

          // 3) Sign-up call
          $signupUrl = 'http://mazingbusiness.com/mazing_business_react/api/user/sign-up';
          $res = Http::asForm()->post($signupUrl, $payload);

          // ✅ party_code nikaal ke return karo
          if ($res->successful()) {
              // preferred way
              $party = $res->json('data.party_code');

              // fallback (in case json() path fail ho)
              if (!$party) {
                  $decoded = json_decode($res->body(), true);
                  $party = data_get($decoded, 'data.party_code');
              }

              \Log::info('Sign-up API OK', ['party_code' => $party, 'status' => $res->status()]);
              return $party ?: null;
          }

          \Log::warning('Sign-up API not successful', ['status' => $res->status(), 'body' => $res->body()]);
          return null;

      } catch (\Throwable $e) {
          \Log::error('Sign-up flow failed: '.$e->getMessage());
          return null;
      }
  }



  private function makeSellerDummyEmail(?string $name, ?string $gstin): string
  {
      $slug  = Str::slug($name ?: 'seller', '_');
      $token = $gstin ? substr(preg_replace('/\W/', '', $gstin), -6) : substr(uniqid(), -6);
      $base  = "seller_{$slug}_{$token}@dummy.com";

      $email = $base; $i = 1;
      while (User::where('email', $email)->exists()) {
          $email = "seller_{$slug}_{$token}_{$i}@dummy.com";
          $i++;
      }
      return $email;
  }






  // testing code start

    public function testSignup()
  {
      // NOTE: Endpoint me hyphen hai: sign-up (signup nahi)
      $endpoint = 'http://mazingbusiness.com/mazing_business_react/api/user/sign-up';

      // Static gst_data string (exact string bhejna hota hai)
      $gstData = '{"taxpayerInfo":{"stjCd":"GA004","dty":"Regular","lgnm":"GP PARSIK SAHAKARI BANK LIMITED","stj":"Margao","adadr":[{"addr":{"bnm":"","loc":"MAPUSA","st":"KHORLIM","bno":"KAVLEKAR TOWER","dst":"South Goa","lt":"","locality":"","pncd":"403507","landMark":"","stcd":"Goa","geocodelvl":"NA","flno":"","lg":""},"ntr":"Service Provision"}],"cxdt":"","gstin":"30AAAAP0267H1Z1","nba":["Service Provision","Supplier of Services"],"lstupdt":"11/10/2023","rgdt":"01/07/2017","ctb":"Society/ Club/ Trust/ AOP","pradr":{"addr":{"bnm":"Costa Towers","loc":"Margao","st":"Valaulikar Road","bno":"Shop No. SH-20","dst":"South Goa","lt":"15.2754590000001","locality":"Pajifond","pncd":"403601","landMark":"Pajifond","stcd":"Goa","geocodelvl":"Building","flno":"Ground Floor","lg":"73.9592200000001"},"ntr":"Service Provision, Supplier of Services"},"tradeNam":"GP PARSIK SAHAKARI BANK LIMITED.","sts":"Active","ctjCd":"UF0501","ctj":"RANGE-I-MADGAON","einvoiceStatus":"Yes","panNo":"AAAAP0267H"},"compliance":{"filingFrequency":null},"filing":[]}';

      // Static payload (Postman screenshot ke format me)
      // $payload = [
      //     'gstin'        => '30AAAAP0267H1Z1',
      //     'gst_data'     => $gstData,                    // string JSON
      //     'phone'        => '7044300330',                // 10-digit
      //     'name'         => 'GP PARSIK SAHAKARI BANK LIMITED',
      //     'aadhar_card'  => '',                          // optional
      //     'address'      => '75, Arera Hills, , Near Central School No 1',
      //     'address2'     => 'Bhopal',
      //     'city'         => 'Bhopal',
      //     'company_name' => 'GP PARSIK SAHAKARI BANK LIMITED',
      //     'postal_code'  => '462011',
      //     'state'        => '3',                         // tumhare API me yeh ID lag raha hai
      //     'email'        => 'dipak@gmail.com',
      // ];

      $payload = [
        'gstin'        => '30AAAAP0267H1Z1',
        'gst_data'     => '{"taxpayerInfo":{"stjCd":"GA004","dty":"Regular","stj":"Margao","lgnm":"GP PARSIK SAHAKARI BANK LIMITED","adadr":[{"addr":{"bnm":"","loc":"MAPUSA","st":"KHORLIM","bno":"KAVLEKAR TOWER","dst":"South Goa","lt":"","locality":"","pncd":"403507","landMark":"","stcd":"Goa","geocodelvl":"NA","flno":"","lg":""},"ntr":"Service Provision"}],"cxdt":"","gstin":"30AAAAP0267H1Z1","nba":["Service Provision","Supplier of Services"],"lstupdt":"11/10/2023","rgdt":"01/07/2017","ctb":"Society/ Club/ Trust/ AOP","pradr":{"addr":{"bnm":"Costa Towers","loc":"Margao","st":"Valaulikar Road","bno":"Shop No. SH-20","dst":"South Goa","lt":"15.2754590000001","locality":"Pajifond","pncd":"403601","landMark":"Pajifond","stcd":"Goa","geocodelvl":"Building","flno":"Ground Floor","lg":"73.9592200000001"},"ntr":"Service Provision, Supplier of Services"},"tradeNam":"GP PARSIK SAHAKARI BANK LIMITED.","ctjCd":"UF0501","sts":"Active","ctj":"RANGE-I-MADGAON","einvoiceStatus":"Yes","panNo":"AAAAP0267H"},"compliance":{"filingFrequency":null},"filing":[]}',
        'phone'        => '7044300330',
        'name'         => 'Dipak Jaiswal',
        'aadhar_card'  => '',
        'address'      => 'test',
        'address2'     => 'test',
        'city'         => 'Adoni',
        'company_name' => 'My test compnay',
        'postal_code'  => '711203',
        'state'        => '2',
        'email'        => 'jaiswal.dipak30@gmail.com',
    ];



      // x-www-form-urlencoded ke saath POST (Postman jaisa)
      $res = Http::asForm()->post($endpoint, $payload);

      // Debug logs (optional)
      \Log::info('Test Sign-up API', [
          'endpoint' => $endpoint,
          'status'   => $res->status(),
          'body'     => $res->body(),
      ]);

      // Browser me clean response dikha do
      return response()->json([
          'endpoint' => $endpoint,
          'status'   => $res->status(),
          'body'     => $this->safeJsonDecode($res->body()) ?? $res->body(),
      ], $res->status());
  }

  // Optional helper: JSON ho to decode karke dikhane ke liye
  private function safeJsonDecode($str) {
      $decoded = json_decode($str, true);
      return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
  }

  // testing code end

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
    $seller = Seller::findOrFail(decrypt($id));
    return view('backend.sellers.edit', compact('seller'));
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, $id) {
    $seller             = Seller::findOrFail($id);
    $user               = $seller->user;
    $user->name         = $request->name;
    $user->warehouse_id = $request->warehouse_id;
    $user->email        = $request->email;
    $user->address      = $request->address;
    $user->country      = Country::find($request->country_id)->name;
    $user->state        = State::find($request->state_id)->name;
    $user->city         = City::find($request->city_id)->name;
    $user->postal_code  = $request->postal_code;
    $user->phone        = $request->phone;
    $user->password     = Hash::make($request->password);
    if ($user->save()) {
      $seller->gstin          = $request->gstin;
      $seller->bank_name      = $request->bank_name;
      $seller->bank_acc_name  = $request->bank_acc_name;
      $seller->bank_acc_no    = $request->bank_acc_no;
      $seller->bank_ifsc_code = $request->bank_ifsc_code;
      if ($request->has('bank_acc_no')) {
        $seller->bank_payment_status = 1;
      } else {
        $seller->bank_payment_status = 0;
      }
      if ($seller->save()) {
        $shop          = $seller->shop;
        $shop->name    = $request->shop_name;
        $shop->phone   = $request->phone;
        $shop->address = $request->address . ', ' . $user->city . ' - ' . $user->postal_code . ', ' . $user->state . ', ' . $user->country;
        $shop->save();

        // ✅ Call Zoho Update after successful update
        if ($seller->zoho_seller_id) {
            try {
                $zoho = new ZohoController();
                $zoho->updateZohoSellerContact($seller->zoho_seller_id);
            } catch (\Exception $e) {
                \Log::error('Zoho Update Error: ' . $e->getMessage());
            }
        }
        flash(translate('Seller has been updated successfully'))->success();
        return redirect()->route('sellers.index');
      }
    }
    flash(translate('Something went wrong'))->error();
    return back();
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id) {
    $seller = Seller::findOrFail($id);
   
    Product::where('user_id', $seller->user_id)->delete();
    $orders = Order::where('user_id', $seller->user_id)->get();

    foreach ($orders as $key => $order) {
      OrderDetail::where('order_id', $order->id)->delete();
    }
    Order::where('user_id', $seller->user_id)->delete();
    Shop::where('seller_id', $id)->delete();
    User::destroy($seller->user->id);

    if (Seller::destroy($id)) {
      // Seller Deletion In Zoho 
       $zoho = new ZohoController();
       $res= $zoho->deleteZohoSellerContact($seller->zoho_seller_id);

       flash(translate('Seller has been deleted successfully'))->success();

      return redirect()->route('sellers.index');
    } else {
      flash(translate('Something went wrong'))->error();
      return back();
    }
  }

  public function bulk_seller_delete(Request $request) {
    if ($request->id) {
      foreach ($request->id as $seller_id) {

         // Fetch Zoho Seller ID from Seller Table
          $seller = Seller::find($seller_id);

          if ($seller && $seller->zoho_seller_id) {
              // Delete Zoho Seller Contact
              $zohoController = new ZohoController();
              $zohoController->deleteZohoSellerContact($seller->zoho_seller_id);
          }
        // Delete Seller and Related Data in Database  
        $this->destroy($seller_id);
      }
    }
    return 1;
  }

  public function show_verification_request($id) {
    $shop = Shop::findOrFail($id);
    return view('backend.sellers.verification', compact('shop'));
  }

  public function approve_seller($id) {
    $shop                      = Shop::findOrFail($id);
    $shop->verification_status = 1;
    if ($shop->save()) {
      Cache::forget('verified_sellers_id');
      flash(translate('Seller has been approved successfully'))->success();
      return redirect()->route('sellers.index');
    }
    flash(translate('Something went wrong'))->error();
    return back();
  }

  public function reject_seller($id) {
    $shop                      = Shop::findOrFail($id);
    $shop->verification_status = 0;
    $shop->verification_info   = null;
    if ($shop->save()) {
      Cache::forget('verified_sellers_id');
      flash(translate('Seller verification request has been rejected successfully'))->success();
      return redirect()->route('sellers.index');
    }
    flash(translate('Something went wrong'))->error();
    return back();
  }

  public function payment_modal(Request $request) {
    $shop = shop::findOrFail($request->id);
    return view('backend.sellers.payment_modal', compact('shop'));
  }

  public function profile_modal(Request $request) {
    $seller = Seller::findOrFail($request->id);
    return view('backend.sellers.profile_modal', compact('seller'));
  }

  public function updateApproved(Request $request) {
    $seller                      = Seller::findOrFail($request->id);
    $seller->verification_status = $request->status;
    if ($seller->save()) {
      Cache::forget('verified_sellers_id');
      return 1;
    }
    return 0;
  }

  public function login($id) {
    $seller = Seller::findOrFail(decrypt($id));
    $user   = $seller->user;
    auth()->login($user, true);
    return redirect()->route('seller.dashboard');
  }

  public function ban($id) {
    $seller = Seller::findOrFail($id);

    if ($seller->user->banned == 1) {
      $seller->user->banned = 0;
      flash(translate('Seller has been unbanned successfully'))->success();
    } else {
      $seller->user->banned = 1;
      flash(translate('Seller has been banned successfully'))->success();
    }

    $seller->user->save();
    return back();
  }
}
