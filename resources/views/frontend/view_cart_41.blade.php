@php
  $total = 0;
  $cash_and_carry_item_flag = 0;
  $cash_and_carry_item_subtotal = 0;
  $normal_item_flag = 0;
  $normal_item_subtotal = 0;
  $applied_offer_id = 0;
  $offer_rewards = 0;
  $temp_carts = \App\Models\Cart::where('user_id', Auth::user()->id)->orWhere('customer_id',Auth::user()->id)->get();
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
@endphp
@extends('frontend.layouts.app')

@section('content')
<style>
  .ajax-loader {
    visibility: hidden;
    background-color: rgba(255, 255, 255, 0.7);
    position: fixed; /* Changed from absolute to fixed */
    z-index: 10000; /* Adjust to ensure it's on top of all other elements */
    width: 100%;
    height: 100%;
    top: 0; /* Ensure it covers the entire viewport */
    left: 0; /* Ensure it covers the entire viewport */
  }

  .ajax-loader img {
    position: absolute;
    top: 50%; /* Center vertically */
    left: 50%; /* Center horizontally */
    transform: translate(-50%, -50%); /* Adjust for image dimensions */
  }
  #cart_split_bill {
      position: fixed;
      right: 0;
      z-index: 10;
  }

  .parent-container {
      position: relative;
      height: auto;
      overflow-y: auto;
  }
  .slick-slide {
    width: auto !important;
    padding :0.6rem .2rem !important;
  }

  :root{
    --bg:#f6f7f9;
    --card:#ffffff;
    --text:#1e293b;
    --muted:#64748b;
    --brand:#0ea5e9;
    --divider:#e5e7eb;
    --shadow: 0 6px 18px rgba(2,6,23,.06), 0 1px 2px rgba(2,6,23,.06);
    --ribbon-orange: #f97316;
    --ribbon-green: #0fa63f;
    --ribbon-grey: #53565a;
    --apply:#fd7e14;
    --corner:18px;
  }

  *{box-sizing:border-box}
  body{ margin:0; background:var(--bg); color:var(--text);
        font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,'Helvetica Neue',Arial,'Noto Sans',sans-serif;}

  .wrap{ max-width:900px; margin:32px auto; padding:0 16px 80px; display:grid; gap:18px; }

  .coupon{
    display:flex; gap:16px; background:var(--card); border-radius:var(--corner);
    box-shadow:var(--shadow); position:relative; overflow:hidden; min-height:120px;
  }
  .coupon::before{
    content:""; position:absolute; inset:0;
    background: radial-gradient(#e2e8f0 4px, transparent 5px) 8px 20px / 16px 28px repeat-y;
    width:20px; pointer-events:none;
  }
  .ribbon{ width:92px; display:flex; align-items:center; justify-content:center; color:#fff;
           font-weight:800; letter-spacing:.06em; writing-mode:vertical-rl; transform:rotate(180deg);
           text-transform:uppercase; font-size:.86rem; }
  .ribbon--cashback{ background:var(--ribbon-orange); }
  .ribbon--cashback--class{ background:var(--ribbon-green); }
  .ribbon--off{ background:var(--ribbon-grey); }

  .body{
    flex:1; padding:16px 16px 10px;
    display:grid; grid-template-columns:auto 1fr auto; grid-template-rows:auto auto auto; gap:12px;
    align-items:center; width:100%;
  }
  .brand{ width:38px;height:38px;border-radius:10px; display:grid; place-items:center;
          background:#e6f6ff; border:1px solid #bae6fd; color:var(--brand); font-size:.72rem; font-weight:800; text-transform:uppercase; grid-row:1 / span 2;}
  .title{ font-size:1.35rem; font-weight:800; letter-spacing:.02em; }
  .apply{ grid-column:3; grid-row:1; font-weight:800; color:var(--apply); text-decoration:none; padding:6px 0 6px 12px; white-space:nowrap; }
  .desc{ grid-column:2 / 4; grid-row:2; font-size:1.025rem; color:#1f6f3f; line-height:1.35; font-weight:600; }
  .divider{ grid-column:2 / 4; grid-row:3; height:1px; background:var(--divider); margin:2px 0 6px; }

  .meta{ grid-column:2 / 4; display:flex; flex-wrap:wrap; gap:10px; align-items:center; color:var(--muted); font-size:.95rem; }
  .more{
    margin-left:10px; color:#0f766e; text-decoration:none; font-weight:700; cursor:pointer;
    border:0; background:transparent; padding:0;
  }
  .coupon:hover{ transform:translateY(-2px); transition:transform .15s ease; }

  /* Expandable products panel */
  .details{
    grid-column:1 / -1; /* full width inside card */
    margin-left:108px;   /* align under content (ribbon 92 + left dots ~16) */
    margin-right:16px;
    overflow:hidden;
    max-height:0;
    transition:max-height .3s ease;
    border-top:1px dashed var(--divider);
  }
  .details.open{ max-height:900px; } /* big enough to show list; adjust if needed */

  .prod-list{ display:flex; flex-direction:column; gap:10px; padding:12px 0 14px; }
  .prod{
    display:flex; gap:12px; align-items:center; background:#f9fafb; border:1px solid #eef2f7;
    border-radius:12px; padding:10px; box-shadow:0 1px 0 rgba(2,6,23,.02) inset;
  }
  .thumb{ width:48px; height:48px; border-radius:10px; background:#e2e8f0; flex:0 0 48px; overflow:hidden; display:grid; place-items:center; font-weight:700; color:#475569; font-size:.8rem; }
  .pinfo{ flex:1; min-width:0; }
  .pname{ font-weight:700; font-size:.98rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .pmeta{ font-size:.9rem; color:var(--muted); }
  .price{ font-weight:800; }

  .meta{
    display:flex;
    justify-content:space-between; /* pushes left and right sides apart */
    align-items:center;
    font-size:.95rem;
    color:#64748b;
  }
  .meta .remove{
    color:#e11d48; /* red tone */
    font-weight:700;
    text-decoration:none;
  }

  .d-none {
    display: none !important;
  }
  .meta .remove:hover{ color:#b91c1c; }
.meta .remove i{ margin-right:4px; }

  /* Responsive tweaks */
  @media (max-width:640px){
    .ribbon{ width:72px; font-size:.78rem; }
    .title{ font-size:1.1rem; }
    .desc{ font-size:.98rem; }
    .details{ margin-left:88px; }
  }
  @media (max-width:420px){
    .apply{ font-size:.9rem; }
    .brand{ width:34px;height:34px; }
    .details{ margin-left:80px; }
  }
</style>
<div class="ajax-loader">
  <img src="{{ url('https://mazingbusiness.com/public/assets/img/ajax-loader.gif') }}" class="img-responsive" />
</div>
  <section class="pt-3 mb-2">
    <div class="container">
      <div class="row">
        <div class="col-md-10 col-lg-9 col-xl-8 mx-auto">
          <div class="row aiz-steps arrow-divider">
            <div class="col active">
              <div class="text-center text-primary">
                <i class="la-2x mb-2 las la-shopping-cart"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('1. My Cart') }}</h3>
              </div>
            </div>
            <div class="col">
              <div class="text-center">
                <i class="la-2x mb-2 opacity-50 las la-map"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block opacity-50">{{ translate('2. Shipping Company') }}
                </h3>
              </div>
            </div>
            <!-- <div class="col">
              <div class="text-center">
                <i class="la-2x mb-2 opacity-50 las la-truck"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block opacity-50">{{ translate('3. Delivery info') }}
                </h3>
              </div>
            </div> -->
            <div class="col">
              <div class="text-center">
                <i class="la-2x mb-2 opacity-50 las la-credit-card"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block opacity-50">{{ translate('3. Payment') }}</h3>
              </div>
            </div>
            <div class="col">
              <div class="text-center">
                <i class="la-2x mb-2 opacity-50 las la-check-circle"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block opacity-50">{{ translate('4. Confirmation') }}
                </h3>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <div class="parent-container">
    <section class="mb-4" id="cart-summary">
      <div class="container">
        <div class="row">
          <div @if ($carts && count($carts) > 0) class="col-lg-8" @else class="col-lg-12" @endif>
            @if ($carts && count($carts) > 0)
              <div id="divCart">
                <div class="mx-auto">
                  <div class="shadow-sm bg-white p-3 p-lg-4 rounded text-left">
                    <div class="mb-4">
                      <div class="row gutters-5 d-none d-lg-flex border-bottom mb-3 pb-3">
                        <div class="col-md-1 fw-600 text-center"><input type="checkbox" id="select-all"></div>
                        <div class="col-md-3 fw-600 text-center">{{ translate('Product') }}</div>
                        <div class="col-md-2 fw-600 text-center">{{ translate('Price') }}</div>
                        <div class="col-md-2 fw-600 text-center">{{ translate('Quantity') }}</div>
                        <div class="col-md-2 fw-600 text-center">{{ translate('Total') }}</div>
                        <div class="col-md-2 fw-600 text-center">{{ translate('Action') }}</div>
                      </div>
                      <ul class="list-group list-group-flush">
                        @php
                          $total = 0;
                          $cash_and_carry_item_flag = 0;
                          $cash_and_carry_item_subtotal = 0;
                          $normal_item_flag = 0;
                          $normal_item_subtotal = 0;
                        @endphp
                        @foreach ($carts as $key => $cartItem)                    
                          @if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606))
                              @php
                                $product = \App\Models\Product::find($cartItem['product_id']);
                                $product_stock = $product->stocks->where('variant', $cartItem['variation'])->first();
                                $total = $total + ($cartItem['price'] * ($cartItem['is_carton'] ? $cartItem['quantity'] * $product_stock->piece_per_carton : $cartItem['quantity']));
                                $product_name_with_choice = $product->getTranslation('name');
                              @endphp
                          @else
                              @php
                                $product = \App\Models\Product::find($cartItem['product_id']);
                                $product_stock = $product->stocks->where('variant', $cartItem['variation'])->first();
                                /* $total = $total + cart_product_price($cartItem, $product, false,true, Auth::user()->id) * ($cartItem['is_carton'] ? $cartItem['quantity'] * $product_stock->piece_per_carton : $cartItem['quantity']); */
                                $total = $total + price_less_than_50(($cartItem['price'] * $cartItem['quantity']),false);
                                $product_name_with_choice = $product->getTranslation('name');
                              @endphp
                          @endif
                          <li class="list-group-item px-0 px-lg-3" id="cartRow_{{ $cartItem['id'] }}">
                            <div class="row gutters-5">
                              <div class="col-lg-1 d-flex">
                                <span class="mr-2 ml-0">
                                  @if($cartItem['complementary_item'] == 0 OR $cartItem['complementary_item'] == NULL)
                                    <input type="checkbox" id="{{ $cartItem['id'] }}" name="{{ $cartItem['id'] }}" value="{{ $cartItem['id'] }}" class="form-control save-for-later-checkbox">
                                  @endif
                                </span>
                              </div>
                              <div class="col-lg-3 d-flex">

                                <span class="mr-2 ml-0">
                                @php
                                  // Fetch the base URL for uploads from the .env file
                                  $uploads_base_url = env('UPLOADS_BASE_URL', url('public'));

                                  // Fetch file_name for the product thumbnail image (assuming $product->thumbnail_img contains the ID of the upload)
                                  $product_thumbnail = \App\Models\Upload::where('id', $product->thumbnail_img)->value('file_name');
                                  $product_thumbnail_path = $product_thumbnail
                                              ? $uploads_base_url . '/' . $product_thumbnail
                                              : url('public/assets/img/placeholder.jpg');
                                @endphp
                                <img src="{{ $product_thumbnail_path }}" class="img-fit size-60px rounded" alt="{{ $product->getTranslation('name') }}">
                                  <!-- <img src="{{ uploaded_asset($product->thumbnail_img) }}" class="img-fit size-60px rounded"
                                    alt="{{ $product->getTranslation('name') }}"> -->
                                </span>

                                <div>
                                    <span class="fs-14 opacity-60">{{ $product_name_with_choice }}</span>
                                    {{-- {!! ($cartItem['cash_and_carry_item'] == 1 && Auth::check() && Auth::user()->credit_days > 0) ? '<div><span class="badge badge-inline badge-danger">No Credit Item</span></div>:'' !!} --}}
                                    @if($cartItem['cash_and_carry_item'] == 1 && Auth::check() && Auth::user()->credit_days > 0)
                                      <div><span class="badge badge-inline badge-danger">No Credit Item</span></div>
                                      @php
                                        $cash_and_carry_item_flag = 1;
                                      @endphp
                                    @endif

                                    @if(DB::table('products_api')->where('part_no', $product->part_no)->where('closing_stock', '>', 0)->exists())
                                      <img src="{{ asset('public/uploads/fast_dispatch.jpg') }}" alt="Fast Delivery" 
                                            style="width: 73px; height: 20px;  border-radius: 3px;">
                                    @endif
                                    @if(Auth::user()->id == '24185')
                                        <div>
                                            {!! $cartItem['offer'] != "" 
                                                ? $cartItem['applied_offer_id'] == "" ? '<span class="badge badge-inline badge-success view-offer" style="cursor: pointer;" data-toggle="modal" data-target="#offerModal" data-product-id="' . $product->id . '">View Offer</span>' : ''
                                                : ''
                                            !!}
                                            @if($cartItem['applied_offer_id'] != "")
                                              <span class="badge badge-inline badge-primary">Offer Applied</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                              </div>
                              <div class="col-lg-2 col-4 order-1 order-lg-0 my-3 my-lg-0 text-center">
                                @if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606 OR session()->get('staff_id')==27604))
                                  @if($cartItem['complementary_item'] == 0 OR $cartItem['complementary_item'] == NULL)
                                    <p>
                                      <input type="number" names="updatePrice_{{ $cartItem['id'] }}" id="updatePrice_{{ $cartItem['id'] }}" value="{{ $cartItem['price'] }}" class="col border flex-grow-1 fs-16 input-number" autofocu style="width: 75px;">
                                      <a href="#" class="btn btn-primary fw-600" style="height: 29px; padding: 4px 8px 16px 12px; margin-bottom: 3px;" onclick="updateCartPrice({{ $cartItem['id'] }})"> Update </a>
                                    </p>
                                  @endif
                                @else
                                  <?php /*<span class="fw-600 fs-16"  id="spanPrice_{{ $cartItem['id'] }}">{{ single_price(cart_product_price($cartItem, $product, true, false, Auth::user()->id)) }}</span> */ ?>
                                  <span class="fw-600 fs-16"  id="spanPrice_{{ $cartItem['id'] }}">{{ single_price($cartItem['price']) }}</span>
                                @endif
                              </div>

                              <div class="col-lg-2 col-6 order-4 order-lg-0 text-center">
                                @if ($cartItem['digital'] != 1)
                                  @if($cartItem['complementary_item'] == 0 OR $cartItem['complementary_item'] == NULL)
                                    <div class="row no-gutters align-items-center aiz-plus-minus mr-2 ml-0">
                                      <button class="btn col-auto btn-icon btn-sm btn-circle btn-light" type="button" data-type="minus" data-field="quantity[{{ $cartItem['id'] }}]"  data-cart-id="{{ $cartItem['id'] }}"> <i class="las la-minus"></i> </button>

                                      {{-- <input type="number" name="quantity[{{ $cartItem['id'] }}]" class="col border-0 text-center flex-grow-1 fs-16 input-number" placeholder="1" value="{{ $cartItem['quantity'] }}" min="{{ $product->min_qty }}" max="{{ $product_stock->qty }}"> --}}
                                      <input type="number" name="quantity[{{ $cartItem['id'] }}]" id="{{ $cartItem['id'] }}" class="col border-0 text-center flex-grow-1 fs-16 input-number" placeholder="1" value="{{ $cartItem['quantity'] }}">

                                      <button class="btn col-auto btn-icon btn-sm btn-circle btn-light" type="button" data-type="plus" data-field="quantity[{{ $cartItem['id'] }}]" data-cart-id="{{ $cartItem['id'] }}"> <i class="las la-plus"></i> </button>
                                    </div>
                                  @else
                                    {{ $cartItem['quantity'] }}
                                  @endif
                                  
                                  {{-- {{ $cartItem['quantity'] }}
                                  {!! ' ' .
                                      ($cartItem['is_carton']
                                          ? Str::plural('Carton', $cartItem['quantity']) .
                                              '<br>[' .
                                              $cartItem['quantity'] * $product_stock->piece_per_carton .
                                              ' ' .
                                              Str::plural('Piece', $cartItem['quantity'] * $product_stock->piece_per_carton) .
                                              ']'
                                          : Str::plural('Piece', $cartItem['quantity'])) !!} --}}
                                @endif
                              </div>
                              <div class="col-lg-2 col-4 order-3 order-lg-0 my-3 my-lg-0 text-center">
                                <span class="opacity-60 fs-12 d-block d-lg-none">{{ translate('Total') }}</span>
                                <span class="fw-600 fs-16 text-primary" id="item_sub_total_span_{{ $cartItem['id'] }}">
                                @if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606))
                                  {{ format_price_in_rs($cartItem['price'] * ($cartItem['is_carton'] ? $cartItem['quantity'] * $product_stock->piece_per_carton : $cartItem['quantity'])) }}
                                @else
                                  <?php /* {{ format_price_in_rs(cart_product_price($cartItem, $product, false,true, Auth::user()->id) * ($cartItem['is_carton'] ? $cartItem['quantity'] * $product_stock->piece_per_carton : $cartItem['quantity'])) }} */ ?>
                                  {{single_price($cartItem['price'] * $cartItem['quantity'])}}
                                @endif

                                </span>
                              </div>
                              <div class="col-lg-2 col-6 order-5 order-lg-0  text-center">
                                @if($cartItem['complementary_item'] == 0 OR $cartItem['complementary_item'] == NULL)
                                  <a href="javascript:void(0)" onclick="removeFromCartView(event, {{ $cartItem['id'] }})"
                                    class="btn btn-icon btn-sm btn-soft-primary btn-circle">
                                    <i class="las la-trash"></i>
                                  </a>
                                  <a href="javascript:void(0)" onclick="saveForLater(event, {{ $cartItem['id'] }})"
                                    class="btn btn-icon btn-sm btn-soft-primary btn-circle" title="Save for later.">
                                    <i class="las la-bookmark"></i>
                                  </a>
                                @endif 
                                {{-- <button class="btn btn-info fw-600" onclick="saveForLater(event, {{ $cartItem['id'] }})">Save for later</button>--}}
                              </div>
                            </div>
                          </li>
                        @endforeach
                      </ul>
                    </div>
                    
                    <div class="px-3 py-2 mb-4 border-top d-flex justify-content-between">
                      <span class="opacity-60 fs-15">{{ translate('Subtotal') }}</span>
                      <span class="fw-600 fs-17" id="span_sub_total">{{ format_price_in_rs($total) }}</span>
                    </div>
                    
                    <div class="row align-items-center">
                      <div class="col-md-3 text-center text-md-left order-1 order-md-0">
                        <a href="{{ route('home') }}" class="btn btn-link">
                          <i class="las la-arrow-left"></i>
                          {{ translate('Return to shop') }}
                        </a>
                      </div>
                      <div class="col-md-9 text-center text-md-right">
                        <button class="btn btn-success fw-600" onclick="saveAllCheckedItemForLater(event)">Save All Checked Item for later</button>
                        @if($cash_and_carry_item_flag == 1)
                          <button class="btn btn-info fw-600" onclick="saveAllNoCreditItemForLater(event)">Save All No Credit Item for later</button>
                        @endif
                        @if (Auth::check())
                          <a href="{{ route('checkout.shipping_info') }}" class="btn btn-primary fw-600">
                            {{ translate('Next') }}
                          </a>
                        @else
                          <button class="btn btn-primary fw-600"
                            onclick="showCheckoutModal()">{{ translate('Next') }}</button>
                        @endif
                      </div>
                    </div>                  
                  </div>
                </div>
              </div>
            @else
              <div class="row">
                <div class="col-xl-8 mx-auto">
                  <div class="shadow-sm bg-white p-4 rounded">
                    <div class="text-center p-3">
                      <i class="las la-frown la-3x opacity-60 mb-3"></i>
                      <h3 class="h4 fw-700">{{ translate('Your Cart is empty') }}</h3>
                    </div>
                  </div>
                </div>
              </div>
            @endif
          </div>
          @if ($carts && count($carts) > 0)
            <div class="col-lg-4 mt-lg-0 mt-4" id="cart_split_bill">
              @include('frontend.partials.cart_bill_amount_v02')
            </div>
          @endif
        </div>
      </div>
    </section>
    <section class="mb-4" id="cartSaveForLater">
      <div class="container">
        <div class="row">
          <div class="col-lg-8">
            <h2 class="shadow-sm bg-white p-4 rounded">Saved For Later ({{ count($cartSaveForLater) }})</h2>
            @if(count($cartSaveForLater) > 0)
              <div class="row align-items-center">
                <div class="col-md-3 text-center text-md-left order-1 order-md-0">
                  <a href="{{ route('home') }}" class="btn btn-link">
                    <i class="las la-arrow-left"></i>
                    {{ translate('Return to shop') }}
                  </a>
                </div>
                <div class="col-md-9 text-center text-md-right">
                  <button class="btn btn-success fw-600" onclick="moveAllCheckedItemToCart(event)">Move All Checked Item To Cart</button>
                  @if($cash_and_carry_item_flag_for_later == 1)
                    <button class="btn btn-info fw-600" onclick="moveAllNoCreditItemToCart(event)">Move All No Credit Item To Cart</button>
                  @endif
                  @if (Auth::check())
                    <a href="{{ route('checkout.shipping_info') }}" class="btn btn-primary fw-600">
                      {{ translate('Next') }}
                    </a>
                  @else
                    <button class="btn btn-primary fw-600"
                      onclick="showCheckoutModal()">{{ translate('Next') }}</button>
                  @endif
                </div>
              </div>
            @endif
            <div style="margin-top: 16px; margin-bottom: 16px;">
              {{-- @if(count($cartSaveForLaterCategory) > 0)
                  @foreach($cartSaveForLaterCategory as $catKey=>$catValue)
                      <span class="shadow-sm p-2 bg-white rounded" style="cursor:pointer;" onclick="sortByCategoryIdInSaveForLater(event, {{ $catValue['category_id'] }})">{{ $catValue['category_name'] }} ({{ $catValue['product_count'] }})</span>
                  @endforeach
              @endif --}}
              <section class="mb-4">
                  <div class="px-2 py-4 px-md-4 py-md-3 bg-white shadow-sm rounded">
                    <div class="d-flex mb-3 align-items-baseline border-bottom">
                      <h3 class="h5 fw-700 mb-0">
                        <span class="border-bottom border-primary border-width-2 pb-3 d-inline-block">
                          {{ translate('Selected Categories') }}
                        </span>
                      </h3>
                    </div>
                    <div class="aiz-carousel gutters-10 half-outside-arrow" data-items="5" data-xl-items="5" data-lg-items="6" data-md-items="4" data-sm-items="3" data-xs-items="2" data-arrows="true" data-infinite="true">
                      @if(count($cartSaveForLaterCategory) > 0)
                          @foreach($cartSaveForLaterCategory as $catKey=>$catValue)
                              <span class="shadow-sm p-2 bg-white rounded" style="cursor:pointer;" onclick="sortByCategoryIdInSaveForLater(event, {{ $catValue['category_id'] }})">{{ $catValue['category_name'] }} ({{ $catValue['product_count'] }})</span>
                          @endforeach
                      @endif
                    </div>
                  </div>
              </section>
            </div>
            @if ($cartSaveForLater && count($cartSaveForLater) > 0)
              <div class="mx-auto">
                <div class="shadow-sm bg-white p-3 p-lg-4 rounded text-left">
                  <div class="mb-4">
                    <div class="row gutters-5 d-none d-lg-flex border-bottom mb-3 pb-3">
                      <div class="col-md-1 fw-600 text-center"><input type="checkbox" id="select-all-from-save-for-later"></div>
                      <div class="col-md-3 fw-600 text-center">{{ translate('Product') }}</div>
                      <div class="col-md-2 fw-600 text-center">{{ translate('Price') }}</div>
                      <div class="col-md-2 fw-600 text-center">{{ translate('Added Quantity') }}</div>
                      <div class="col-md-2 fw-600 text-center">{{ translate('Total') }}</div>
                      <div class="col-md-2 fw-600 text-center">{{ translate('Action') }}</div>
                    </div>
                    <ul class="list-group list-group-flush">
                      @php
                        $total = 0;
                        $cash_and_carry_item_flag = 0;
                        $cash_and_carry_item_subtotal = 0;
                        $normal_item_flag = 0;
                        $normal_item_subtotal = 0;
                      @endphp
                      @foreach ($cartSaveForLater as $key => $cartItem)                    
                        @if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606))
                            @php
                              $product = \App\Models\Product::find($cartItem['product_id']);
                              $product_stock = $product->stocks->where('variant', $cartItem['variation'])->first();
                              $total = $total + ($cartItem['price'] * ($cartItem['is_carton'] ? $cartItem['quantity'] * $product_stock->piece_per_carton : $cartItem['quantity']));
                              $product_name_with_choice = $product->getTranslation('name');
                            @endphp
                        @else
                            @php
                              $product = \App\Models\Product::find($cartItem['product_id']);
                              $product_stock = $product->stocks->where('variant', $cartItem['variation'])->first();
                              $total = $total + cart_product_price($cartItem, $product, false,true, Auth::user()->id) * ($cartItem['is_carton'] ? $cartItem['quantity'] * $product_stock->piece_per_carton : $cartItem['quantity']);
                              $product_name_with_choice = $product->getTranslation('name');
                            @endphp
                        @endif
                        <li class="list-group-item px-0 px-lg-3" id="cartRow_{{ $cartItem['id'] }}">
                          <div class="row gutters-5">
                            <div class="col-lg-1 d-flex">
                              <span class="mr-2 ml-0">
                                <input type="checkbox" id="{{ $cartItem['id'] }}" name="{{ $cartItem['id'] }}" value="{{ $cartItem['id'] }}" class="form-control move-to-cart-checkbox">
                              </span>
                            </div>
                            <div class="col-lg-3 d-flex">
                              <span class="mr-2 ml-0">
                              @php
                                // Fetch the base URL for uploads from the .env file
                                $uploads_base_url = env('UPLOADS_BASE_URL', url('public'));

                                // Fetch file_name for the product thumbnail image (assuming $product->thumbnail_img contains the ID of the upload)
                                $product_thumbnail = \App\Models\Upload::where('id', $product->thumbnail_img)->value('file_name');
                                $product_thumbnail_path = $product_thumbnail
                                            ? $uploads_base_url . '/' . $product_thumbnail
                                            : url('public/assets/img/placeholder.jpg');
                              @endphp
                              <img src="{{ $product_thumbnail_path }}" class="img-fit size-60px rounded" alt="{{ $product->getTranslation('name') }}">
                                <!-- <img src="{{ uploaded_asset($product->thumbnail_img) }}" class="img-fit size-60px rounded"
                                  alt="{{ $product->getTranslation('name') }}"> -->
                              </span>

                              <div>
                                  <span class="fs-14 opacity-60">{{ $product_name_with_choice }}</span>
                                  {{-- {!! ($cartItem['cash_and_carry_item'] == 1 && Auth::check() && Auth::user()->credit_days > 0) ? '<div><span class="badge badge-inline badge-danger">No Credit Item</span></div>:'' !!} --}}
                                  @if($cartItem['cash_and_carry_item'] == 1 && Auth::check() && Auth::user()->credit_days > 0)
                                    <div><span class="badge badge-inline badge-danger">No Credit Item</span></div>
                                    @php
                                      $cash_and_carry_item_flag = 1;
                                    @endphp
                                  @endif

                                  @if(DB::table('products_api')->where('part_no', $product->part_no)->where('closing_stock', '>', 0)->exists())

                                        <img src="{{ asset('public/uploads/fast_dispatch.jpg') }}" alt="Fast Delivery" 
                                             style="width: 73px; height: 20px;  border-radius: 3px;">
                                    @endif
                              </div>
                            </div>

                            <div class="col-lg-2 col-4 order-1 order-lg-0 my-3 my-lg-0 text-center">
                              {{-- @if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606 OR session()->get('staff_id')==27604))
                                <p>
                                  <input type="number" names="updatePrice_{{ $cartItem['id'] }}" id="updatePrice_{{ $cartItem['price'] }}" value="{{ $cartItem['price'] }}" class="col border flex-grow-1 fs-16 input-number" autofocu style="width: 75px;">
                                  <a href="#" class="btn btn-primary fw-600" style="height: 29px; padding: 4px 8px 16px 12px; margin-bottom: 3px;" onclick="updateCartPrice({{ $cartItem['id'] }})"> Update </a>
                                </p>
                              @else
                                <span class="fw-600 fs-16">{{ single_price(cart_product_price($cartItem, $product, true, false, Auth::user()->id)) }}</span>
                              @endif --}}
                              <span class="fw-600 fs-16">{{ single_price(cart_product_price($cartItem, $product, true, false, Auth::user()->id)) }}</span>
                            </div>

                            <div class="col-lg-2 col-6 order-4 order-lg-0 text-center">
                              @if ($cartItem['digital'] != 1)
                                {{ $cartItem['quantity'] }}
                                {{-- {!! ' ' .
                                    ($cartItem['is_carton']
                                        ? Str::plural('Carton', $cartItem['quantity']) .
                                            '<br>[' .
                                            $cartItem['quantity'] * $product_stock->piece_per_carton .
                                            ' ' .
                                            Str::plural('Piece', $cartItem['quantity'] * $product_stock->piece_per_carton) .
                                            ']'
                                        : Str::plural('Piece', $cartItem['quantity'])) !!} --}}
                              @endif
                            </div>
                            <div class="col-lg-2 col-4 order-3 order-lg-0 my-3 my-lg-0 text-center">
                              <span class="opacity-60 fs-12 d-block d-lg-none">{{ translate('Total') }}</span>
                              <span class="fw-600 fs-16 text-primary" id="item_sub_total_span_{{ $cartItem['id'] }}">
                                @if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606))
                                  {{ single_price($cartItem['price'] * ($cartItem['is_carton'] ? $cartItem['quantity'] * $product_stock->piece_per_carton : $cartItem['quantity'])) }}
                                @else
                                  {{ single_price(cart_product_price($cartItem, $product, false,true, Auth::user()->id) * ($cartItem['is_carton'] ? $cartItem['quantity'] * $product_stock->piece_per_carton : $cartItem['quantity'])) }}
                                @endif
                              </span>
                            </div>
                            <div class="col-lg-2 col-6 order-5 order-lg-0  text-center">
                              <a href="javascript:void(0)" onclick="removeFromSaveForLeterView(event, {{ $cartItem['id'] }})"
                                class="btn btn-icon btn-sm btn-soft-primary btn-circle">
                                <i class="las la-trash"></i>
                              </a>
                              <a href="javascript:void(0)" onclick="moveToCart(event, {{ $cartItem['id'] }})"
                                class="btn btn-icon btn-sm btn-soft-primary btn-circle" title="Move to cart.">
                                <i class="las  la-angle-double-up"></i>
                              </a>
                            </div>
                          </div>
                        </li>
                      @endforeach
                    </ul>
                  </div>
                  <hr>
                  @if(count($cartSaveForLater) > 0)
                    <div class="row align-items-center">
                      <div class="col-md-3 text-center text-md-left order-1 order-md-0">
                        <a href="{{ route('home') }}" class="btn btn-link">
                          <i class="las la-arrow-left"></i>
                          {{ translate('Return to shop') }}
                        </a>
                      </div>        
                      <div class="col-md-9 text-center text-md-right">
                        <button class="btn btn-success fw-600" onclick="moveAllCheckedItemToCart(event)">Move All Checked Item To Cart</button>
                        @if($cash_and_carry_item_flag_for_later == 1)
                          <button class="btn btn-info fw-600" onclick="moveAllNoCreditItemToCart(event)">Move All No Credit Item To Cart</button>
                        @endif
                        @if (Auth::check())
                          <a href="{{ route('checkout.shipping_info') }}" class="btn btn-primary fw-600">
                            {{ translate('Next') }}
                          </a>
                        @else
                          <button class="btn btn-primary fw-600"
                            onclick="showCheckoutModal()">{{ translate('Next') }}</button>
                        @endif
                      </div>
                    </div>
                  @endif
                  {{-- <div class="px-3 py-2 mb-4 border-top d-flex justify-content-between">
                    <span class="opacity-60 fs-15">{{ translate('Subtotal') }}</span>
                    <span class="fw-600 fs-17" id="span_sub_total">{{ single_price($total) }}</span>
                  </div> --}}                 
                </div>
              </div>
            @else
              <div class="row">
                <div class="col-xl-12 mx-auto">
                  <div class="shadow-sm bg-white p-4 rounded">
                    <div class="text-center p-3">
                      <i class="las la-cart-arrow-down la-3x opacity-60 mb-3"></i>
                      <h3 class="h4 fw-700">No Item in save for later.</h3>
                    </div>
                  </div>
                </div>
              </div>
            @endif
          </div>
        </div>
      </div>
    </section>
  </div>
  
@endsection

@section('modal')
  <div class="modal fade" id="login-modal">
    <div class="modal-dialog modal-dialog-zoom">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title fw-600">{{ translate('Login') }}</h6>
          <button type="button" class="close" data-dismiss="modal">
            <span aria-hidden="true"></span>
          </button>
        </div>
        <div class="modal-body">
          <div class="p-3">
            <form class="form-default" role="form" action="{{ route('cart.login.submit') }}" method="POST">
              @csrf
              @if (addon_is_activated('otp_system') && env('DEMO_MODE') != 'On')
                <div class="form-group phone-form-group mb-1">
                  <input type="tel" id="phone-code"
                    class="form-control{{ $errors->has('phone') ? ' is-invalid' : '' }}" value="{{ old('phone') }}"
                    placeholder="" name="phone" autocomplete="off">
                </div>

                <input type="hidden" name="country_code" value="">

                <div class="form-group email-form-group mb-1 d-none">
                  <input type="email" class="form-control {{ $errors->has('email') ? ' is-invalid' : '' }}"
                    value="{{ old('email') }}" placeholder="{{ translate('Email') }}" name="email" id="email"
                    autocomplete="off">
                  @if ($errors->has('email'))
                    <span class="invalid-feedback" role="alert">
                      <strong>{{ $errors->first('email') }}</strong>
                    </span>
                  @endif
                </div>

                <div class="form-group text-right">
                  <button class="btn btn-link p-0 opacity-50 text-reset" type="button"
                    onclick="toggleEmailPhone(this)">{{ translate('Use Email Instead') }}</button>
                </div>
              @else
                <div class="form-group">
                  <input type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}"
                    value="{{ old('email') }}" placeholder="{{ translate('Email') }}" name="email" id="email"
                    autocomplete="off">
                  @if ($errors->has('email'))
                    <span class="invalid-feedback" role="alert">
                      <strong>{{ $errors->first('email') }}</strong>
                    </span>
                  @endif
                </div>
              @endif

              <div class="form-group">
                <input type="password" class="form-control {{ $errors->has('password') ? ' is-invalid' : '' }}"
                  placeholder="{{ translate('Password') }}" name="password" id="password">
              </div>

              <div class="row mb-2">
                <div class="col-6">
                  <label class="aiz-checkbox">
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                    <span class=opacity-60>{{ translate('Remember Me') }}</span>
                    <span class="aiz-square-check"></span>
                  </label>
                </div>
                <div class="col-6 text-right">
                  <a href="{{ route('password.request') }}"
                    class="text-reset opacity-60 fs-14">{{ translate('Forgot password?') }}</a>
                </div>
              </div>

              <div class="mb-5">
                <button type="submit" class="btn btn-primary btn-block fw-600">{{ translate('Login') }}</button>
              </div>
            </form>

          </div>
          <div class="text-center mb-3">
            <p class="text-muted mb-0">{{ translate('Dont have an account?') }}</p>
            <a href="{{ route('user.registration') }}">{{ translate('Register Now') }}</a>
          </div>
          @if (get_setting('google_login') == 1 ||
                  get_setting('facebook_login') == 1 ||
                  get_setting('twitter_login') == 1 ||
                  get_setting('apple_login') == 1)
            <div class="separator mb-3">
              <span class="bg-white px-3 opacity-60">{{ translate('Or Login With') }}</span>
            </div>
            <ul class="list-inline social colored text-center mb-3">
              @if (get_setting('facebook_login') == 1)
                <li class="list-inline-item">
                  <a href="{{ route('social.login', ['provider' => 'facebook']) }}" class="facebook">
                    <i class="lab la-facebook-f"></i>
                  </a>
                </li>
              @endif
              @if (get_setting('google_login') == 1)
                <li class="list-inline-item">
                  <a href="{{ route('social.login', ['provider' => 'google']) }}" class="google">
                    <i class="lab la-google"></i>
                  </a>
                </li>
              @endif
              @if (get_setting('twitter_login') == 1)
                <li class="list-inline-item">
                  <a href="{{ route('social.login', ['provider' => 'twitter']) }}" class="twitter">
                    <i class="lab la-twitter"></i>
                  </a>
                </li>
              @endif
              @if (get_setting('apple_login') == 1)
                <li class="list-inline-item">
                  <a href="{{ route('social.login', ['provider' => 'apple']) }}" class="apple">
                    <i class="lab la-apple"></i>
                  </a>
                </li>
              @endif
            </ul>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="pdfModal" tabindex="-1" role="dialog" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="height: 90vh;"> <!-- Set modal height -->
            <div class="modal-header">
                <h5 class="modal-title" id="pdfModalLabel">View Statement</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 0; height: 100%;">
                <!-- Embed PDF here -->
                <iframe id="pdfViewer" src="" frameborder="0" width="100%" height="100%" style="height: 100%;"></iframe>
            </div>
        </div>
    </div>
  </div>

  <?php /*<div class="modal fade" id="allOfferModal" tabindex="-1" role="dialog" aria-labelledby="offerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="height: 90vh;"> <!-- Set modal height -->
            <div class="modal-header">
                <h5 class="modal-title" id="pdfModalLabel">Offer List</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 0; height: 100%;">
                <div class="wrap">
                  @if(isset($validOffers) AND count($validOffers) > 0)
                    @php
                        $count = 0;
                    @endphp
                    @foreach($validOffers as $voKey=>$voValue)
                        @if($applied_offer_id == 0 OR $applied_offer_id != $voValue->id)
                            <!-- <a href="{{ route('cart.applyOffer',['offer_id'=> encrypt($voValue->id)]) }}" style="width:100%"  class="btn {{ $count % 2 == 0 ? 'btn-success' : 'btn-warning' }}  fw-600">Apply {{ $voValue->offer_name }} Offer</a> -->
                            <div class="coupon">
                              <div class="ribbon ribbon--cashback--class"><small>
                                @if($voValue->offer_type == 1)
                                    Item Wise
                                @elseif($voValue->offer_type == 2)
                                    Total
                                @elseif($voValue->offer_type == 3)
                                    Complementary
                                @else
                                    -
                                @endif
                              </small></div>

                              <div class="body">
                                <div class="brand">Mazing</div>
                                <div class="title">{{ $voValue->offer_name }}</div>
                                <a class="apply" href="{{ route('cart.applyOffer',['offer_id'=> encrypt($voValue->id)]) }}" aria-label="Apply">APPLY</a>
                                <div class="desc">Offer Description : {{ $voValue->offer_description }}</div>
                                <div class="divider"></div>

                                <div class="meta">
                                  <span>Click More to get details</span>
                                  <button class="more" type="button" aria-expanded="false" aria-controls="d{{$voValue->id}}">+ MORE</button>
                                </div>
                                <!-- Expandable product list -->
                                <div id="d{{$voValue->id}}" class="details" hidden>
                                  <div class="prod-list">
                                    @foreach ($voValue->offerProducts as $oKey => $oValue)
                                      @php
                                        if($oValue->discount_type == 'percent'){                           
                                            $discountedPrice = ($oValue->mrp * ((100 - Auth::user()->discount) / 100))*((100 - $oValue->offer_discount_percent) / 100);
                                        }else{
                                            $discountedPrice = $oValue->offer_price;
                                        }                           
                                        $discountedPrice = $discountedPrice == "" ? 0 : $discountedPrice;
                                      @endphp
                                      <div class="prod">
                                        <div class="thumb">{{$oKey+1}}</div>
                                        <div class="pinfo">
                                          <div class="pname">{{ $oValue->name }}</div>
                                          <div class="pmeta">Minium Quantity: {{ $oValue->min_qty }}</div>
                                        </div>
                                        <div class="price">{{ single_price($discountedPrice) }}</div>
                                      </div>
                                    @endforeach
                                  </div>
                                </div>
                                <!-- /Expandable -->
                              </div>
                            </div>
                        @else
                            <div class="coupon">
                              <div class="ribbon ribbon--cashback"><small>CASHBACK</small></div>

                              <div class="body">
                                <div class="brand">Mazing</div>
                                <div class="title">{{ $voValue->offer_name }}</div>
                                <a class="apply" href="{{ route('cart.removeOffer',['offer_id'=> encrypt($voValue->id)]) }}">APPLIED</a>
                                <div class="desc">Offer Description : {{ $voValue->offer_description }}</div>
                                <div class="divider"></div>

                                <div class="meta">
                                  <div>
                                    <span>Applicable on Paytm wallet transaction above ₹99</span>
                                    <button class="more" type="button" aria-expanded="false" aria-controls="d{{$voValue->id}}">+ MORE</button>
                                  </div>
                                  <a class="remove" href="{{ route('cart.removeOffer',['offer_id'=> encrypt($voValue->id)]) }}">
                                    <i class="fa fa-trash"></i> REMOVE
                                  </a>
                                </div>

                                <!-- Expandable product list -->
                                <div id="d{{$voValue->id}}" class="details" hidden>
                                  <div class="prod-list">
                                    @foreach ($voValue->offerProducts as $oKey => $oValue)
                                      @php
                                        if($oValue->discount_type == 'percent'){                           
                                            $discountedPrice = ($oValue->mrp * ((100 - Auth::user()->discount) / 100))*((100 - $oValue->offer_discount_percent) / 100);
                                        }else{
                                            $discountedPrice = $oValue->offer_price;
                                        }                           
                                        $discountedPrice = $discountedPrice == "" ? 0 : $discountedPrice;
                                      @endphp
                                      <div class="prod">
                                        <div class="thumb">A</div>
                                        <div class="pinfo">
                                          <div class="pname">{{ $oValue->name }}</div>
                                          <div class="pmeta">Minium Quantity: {{ $oValue->min_qty }}</div>
                                        </div>
                                        <div class="price">{{ single_price($discountedPrice) }}</div>
                                      </div>
                                    @endforeach

                                    <!-- <div class="prod">
                                      <div class="thumb">B</div>
                                      <div class="pinfo">
                                        <div class="pname">Brownie Sundae with Hot Fudge</div>
                                        <div class="pmeta">Dessert</div>
                                      </div>
                                      <div class="price">₹149</div>
                                    </div>

                                    <div class="prod">
                                      <div class="thumb">C</div>
                                      <div class="pinfo">
                                        <div class="pname">Cold Coffee (Tall)</div>
                                        <div class="pmeta">Beverage</div>
                                      </div>
                                      <div class="price">₹99</div>
                                    </div> -->

                                  </div>
                                </div>
                                <!-- /Expandable -->
                              </div>
                            </div>
                            <!-- <a href="{{ route('cart.removeOffer',['offer_id'=> encrypt($voValue->id)]) }}" style="width:100%"  class="btn btn-danger fw-600">Remove {{ $voValue->offer_name }} Offer</a> -->
                        @endif
                        @php
                            $count ++;
                        @endphp
                    @endforeach
                  @endif
                </div>
            </div>
        </div>
    </div>
  </div> */ ?>
  <div class="modal fade" id="allOfferModal" tabindex="-1" role="dialog" aria-labelledby="offerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
      <div class="modal-content" style="height: 90vh;">
        <div class="modal-header">
          <h5 class="modal-title">Offer List</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
        </div>

        <div class="modal-body p-0 h-100">
          <!-- loading state -->
          <div id="offerModalLoading" class="d-flex align-items-center justify-content-center h-100">
            <div class="text-center p-4">
              <div class="spinner-border" role="status"></div>
              <div class="mt-2">Loading offers…</div>
            </div>
          </div>
          <!-- dynamic content goes here -->
          <div id="offerModalContent" class="wrap d-none"></div>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('script')
  <script type="text/javascript">

    $('#allOfferModal').on('show.bs.modal', function () {
      const $loading = $('#offerModalLoading');
      const $content = $('#offerModalContent');
      $content.addClass('d-none').empty();
      $loading.removeClass('d-none');

      $.get("{{ route('cart.offers.fragment') }}", { _t: Date.now() }) // cache-bust
        .always(function() {
          $loading.addClass('d-none');
        })
        .done(function(html) {
          $content.html(html).removeClass('d-none');
        })
        .fail(function() {
          $content.html('<div class="p-4 text-center text-danger">Failed to load offers.</div>').removeClass('d-none');
        });
        
    });

    $(document).on('click', '.aiz-plus-minus button', function (e) {

        e.preventDefault();

       
        var cartId = $(this).attr('data-cart-id');
        var fieldName = $(this).attr('data-field');        
        var type = $(this).attr('data-type');
        var input = $("input[name='" + fieldName + "']");
        var currentVal = parseInt(input.val());
        if (!isNaN(currentVal)) {
            if (type === 'minus') {
                if (currentVal > 1) { // Prevent going below 1
                    input.val(currentVal - 1).change(); // Decrease value
                }
            } else if (type === 'plus') {
                input.val(currentVal + 1).change(); // Increase value
            }
        } else {
            input.val(1); // Set default value to 1 if no valid number
        }
        updateQuantity(cartId, input.val());
    });

    $(document).on('change', '#select-all', function () {
        var isChecked = $(this).is(':checked');
        $('.save-for-later-checkbox').prop('checked', isChecked);
    });
    $(document).on('change', '.save-for-later-checkbox', function () {
        var allChecked = $('.save-for-later-checkbox').length === $('.save-for-later-checkbox:checked').length;
        $('#select-all').prop('checked', allChecked);
    });

    $(document).on('change', '#select-all-from-save-for-later', function () {
        var isChecked = $(this).is(':checked');
        $('.move-to-cart-checkbox').prop('checked', isChecked);
    });
    $(document).on('change', '.move-to-cart-checkbox', function () {
        var allChecked = $('.move-to-cart-checkbox').length === $('.move-to-cart-checkbox:checked').length;
        $('#select-all-from-save-for-later').prop('checked', allChecked);
    });

    document.addEventListener("scroll", function () {
        const cartSplitBill = document.getElementById("cart_split_bill");
        const parentContainer = document.querySelector(".parent-container");

        const parentRect = parentContainer.getBoundingClientRect();
        const cartHeight = cartSplitBill.offsetHeight;

        // If the cart should stop scrolling at the bottom of the parent container
        if (parentRect.bottom - cartHeight <= 0) {
            cartSplitBill.style.position = "absolute";
            cartSplitBill.style.top = `${parentContainer.offsetHeight - cartHeight}px`;
            cartSplitBill.style.right = "0"; // Ensure it remains aligned
        } else {
            // Reset back to fixed when scrolling upward
            cartSplitBill.style.position = "fixed";
            cartSplitBill.style.top = "auto";
            cartSplitBill.style.right = "0"; // Ensure alignment remains
        }
    });


    $(document).on('click', '.my_pdf', function(event) {
        event.preventDefault(); // Prevent default link behavior

        // Get user ID from data attribute
        let userId = $(this).data('user-id');
        $('#pdfModal').modal('show');
        // Make an AJAX request to get the PDF URL
        $.ajax({
            // url: `/admins/create-pdf/${userId}`, // Updated to match the correct route
            // type: 'GET',
            url: '{{ route("viewFullStatement") }}',
            type: 'POST',
            beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
            data: { _token: '{{ csrf_token() }}', userId: userId },
            // dataType: 'json',
            success: function(response) {
                console.log("AJAX Response:", response); // Log the response for debugging
                if (response.pdf_url) {
                    // Set the PDF URL in the iframe
                    $('#pdfViewer').attr('src', response.pdf_url);
                    // Show the modal
                    $('#pdfModal').modal('show');
                } else {
                    alert("Failed to generate PDF. Please try again.");
                }
            },
            complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            },
            error: function(xhr, status, error) {
                console.error("Error: ", error); // Log the error for debugging
                console.error("Response Text: ", xhr.responseText);
                alert("An error occurred while generating the PDF. Please check the console for details.");
            }
        });
    });

    $(document).on('keypress', '.input-number', function(event) {
        // Check if Enter key is pressed
        if (event.which === 13) {
            event.preventDefault(); // Prevent the form from submitting if in a form

            // Get cart_id and quantity
            var cartId = $(this).attr('id');
            var quantity = $(this).val();
            if(quantity <= 0){
              quantity = 1;
              $(this).val('1');
            }
            updateQuantity(cartId, quantity);
        }
    });

    function removeFromCartView(e, key) {
      e.preventDefault();
      removeFromCart(key);
    }
    
    function saveForLater(e, id) {
      e.preventDefault();
      // $('#cartRow_'+id).html('');
      const overdueAmount = {{ $overdueAmount }};
      const dueAmount = {{ $dueAmount }};
      $.ajax({
          url: '{{ route("saveForLater") }}',
          type: 'GET',
          beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
          data: { cart_id: id, overdueAmount: overdueAmount, dueAmount: dueAmount },
          dataType: 'json',
          success: function (response) {
            updateNavCart(response.nav_cart_view, response.cart_count);
            $('#cart-summary').empty(); // Clear the div before appending new data
            $('#cart-summary').append(response.html); // Append the response data
            $('#cartSaveForLater').empty(); // Clear the div before appending new data
            $('#cartSaveForLater').append(response.viewSaveForLater); // Append the response data
            // Call the initialization function
            initAizCarousel();
          },
          complete: function(){
            $('.ajax-loader').css("visibility", "hidden");
          },
          error: function (xhr, status, error) {
              console.error(xhr.responseText);
          }
      });
    }

    function moveToCart(e, id) {
      e.preventDefault();
      // $('#cartRow_'+id).html('');
      const overdueAmount = {{ $overdueAmount }};
      const dueAmount = {{ $dueAmount }};
      $.ajax({
          url: '{{ route("moveToCart") }}',
          type: 'GET',
          beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
          data: { id: id, overdueAmount: overdueAmount, dueAmount: dueAmount },
          dataType: 'json',
          success: function (response) {
            updateNavCart(response.nav_cart_view, response.cart_count);
            $('#cart-summary').empty(); // Clear the div before appending new data
            $('#cart-summary').append(response.html); // Append the response data
            $('#cartSaveForLater').empty(); // Clear the div before appending new data
            $('#cartSaveForLater').append(response.viewSaveForLater); // Append the response data
            // Call the initialization function
            initAizCarousel();
          },
          complete: function(){
            $('.ajax-loader').css("visibility", "hidden");
          },
          error: function (xhr, status, error) {
              console.error(xhr.responseText);
          }
      });
    }

    function removeFromSaveForLeterView(e, id) {
      e.preventDefault();
      // $('#cartRow_'+id).html('');
      var conf = confirm('Are you sure want delete this item from save for later?');
      if(conf == true){
        const overdueAmount = {{ $overdueAmount }};
        const dueAmount = {{ $dueAmount }};
        $.ajax({
            url: '{{ route("removeFromSaveForLeterView") }}',
            type: 'GET',
            beforeSend: function(){
                $('.ajax-loader').css("visibility", "visible");
              },
            data: { id: id, overdueAmount: overdueAmount, dueAmount: dueAmount },
            dataType: 'json',
            success: function (response) {
              $('#cartSaveForLater').empty(); // Clear the div before appending new data
              $('#cartSaveForLater').append(response.viewSaveForLater); // Append the response data
              // Call the initialization function
              initAizCarousel();
            },
            complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
      }else{
        return false;
      }
    }

    function saveAllNoCreditItemForLater(e) {
      e.preventDefault();
      // $('#cartRow_'+id).html('');
      const overdueAmount = {{ $overdueAmount }};
      const dueAmount = {{ $dueAmount }};
      $.ajax({
          url: '{{ route("saveAllNoCreditItemForLater") }}',
          type: 'GET',
          beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
          data: { overdueAmount: overdueAmount, dueAmount: dueAmount },
          dataType: 'json',
          success: function (response) {
            updateNavCart(response.nav_cart_view, response.cart_count);
            $('#cart-summary').empty(); // Clear the div before appending new data
            $('#cart-summary').append(response.html); // Append the response data
            $('#cartSaveForLater').empty(); // Clear the div before appending new data
            $('#cartSaveForLater').append(response.viewSaveForLater); // Append the response data
            // Call the initialization function
            initAizCarousel();
          },
          complete: function(){
            $('.ajax-loader').css("visibility", "hidden");
          },
          error: function (xhr, status, error) {
              console.error(xhr.responseText);
          }
      });
    }

    function moveAllNoCreditItemToCart(e) {
      e.preventDefault();
      // $('#cartRow_'+id).html('');
      const overdueAmount = {{ $overdueAmount }};
      const dueAmount = {{ $dueAmount }};
      $.ajax({
          url: '{{ route("moveAllNoCreditItemToCart") }}',
          type: 'GET',
          beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
          data: { overdueAmount: overdueAmount, dueAmount: dueAmount },
          dataType: 'json',
          success: function (response) {
            updateNavCart(response.nav_cart_view, response.cart_count);
            $('#cart-summary').empty(); // Clear the div before appending new data
            $('#cart-summary').append(response.html); // Append the response data
            $('#cartSaveForLater').empty(); // Clear the div before appending new data
            $('#cartSaveForLater').append(response.viewSaveForLater); // Append the response data
            // Call the initialization function
            initAizCarousel();
          },
          complete: function(){
            $('.ajax-loader').css("visibility", "hidden");
          },
          error: function (xhr, status, error) {
              console.error(xhr.responseText);
          }
      });
    }

    function saveAllCheckedItemForLater(event) {
      event.preventDefault(); 
      const checkedItems = document.querySelectorAll('.save-for-later-checkbox:checked');
      const itemIds = Array.from(checkedItems).map(checkbox => checkbox.value);
      const overdueAmount = {{ $overdueAmount }};
      const dueAmount = {{ $dueAmount }};
      if (itemIds.length === 0) {
          alert('Please select at least one item from cart.');
          return;
      }

      $.ajax({
          url: '{{ route("saveAllCheckedItemForLater") }}',
          type: 'POST',
          beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
          data: { _token: '{{ csrf_token() }}', itemIds: itemIds, overdueAmount: overdueAmount, dueAmount: dueAmount },
          dataType: 'json',
          success: function (response) {
            updateNavCart(response.nav_cart_view, response.cart_count);
            $('#cart-summary').empty(); // Clear the div before appending new data
            $('#cart-summary').append(response.html); // Append the response data
            $('#cartSaveForLater').empty(); // Clear the div before appending new data
            $('#cartSaveForLater').append(response.viewSaveForLater); // Append the response data
            // Call the initialization function
            initAizCarousel();
          },
          complete: function(){
            $('.ajax-loader').css("visibility", "hidden");
          },
          error: function (xhr, status, error) {
              console.error(xhr.responseText);
          }
      });
    }

    function moveAllCheckedItemToCart(event) {
      event.preventDefault(); 
      const checkedItems = document.querySelectorAll('.move-to-cart-checkbox:checked');
      const itemIds = Array.from(checkedItems).map(checkbox => checkbox.value);
      const overdueAmount = {{ $overdueAmount }};
      const dueAmount = {{ $dueAmount }};
      if (itemIds.length === 0) {
          alert('Please select at least one item fron save for later.');
          return;
      }

      $.ajax({
          url: '{{ route("moveAllCheckedItemToCart") }}',
          type: 'POST',
          beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
          data: { _token: '{{ csrf_token() }}', itemIds: itemIds, overdueAmount: overdueAmount, dueAmount: dueAmount },
          dataType: 'json',
          success: function (response) {
            updateNavCart(response.nav_cart_view, response.cart_count);
            $('#cart-summary').empty(); // Clear the div before appending new data
            $('#cart-summary').append(response.html); // Append the response data
            $('#cartSaveForLater').empty(); // Clear the div before appending new data
            $('#cartSaveForLater').append(response.viewSaveForLater); // Append the response data
            // Call the initialization function
            initAizCarousel();
          },
          complete: function(){
            $('.ajax-loader').css("visibility", "hidden");
          },
          error: function (xhr, status, error) {
              console.error(xhr.responseText);
          }
      });
    }

    function sortByCategoryIdInSaveForLater(event,category_id){
      const overdueAmount = {{ $overdueAmount }};
      const dueAmount = {{ $dueAmount }};
      $.ajax({
          url: '{{ route("sortByCategoryIdInSaveForLater") }}',
          type: 'POST',
          beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
          data: { _token: '{{ csrf_token() }}', category_id: category_id, overdueAmount: overdueAmount, dueAmount: dueAmount },
          dataType: 'json',
          success: function (response) {
            $('#cartSaveForLater').empty(); // Clear the div before appending new data
            $('#cartSaveForLater').append(response.viewSaveForLater); // Append the response data
            // Call the initialization function
            initAizCarousel();
          },
          complete: function(){
            $('.ajax-loader').css("visibility", "hidden");
          },
          error: function (xhr, status, error) {
              console.error(xhr.responseText);
          }
      });
    }

    function initAizCarousel() {
        // Reinitialize the carousel for elements with the 'aiz-carousel' class
        if (typeof AIZ !== 'undefined' && AIZ.plugins && typeof AIZ.plugins.slickCarousel === 'function') {
            AIZ.plugins.slickCarousel(); // Reinitialize the carousel
        } else {
            console.error("AIZ library or slickCarousel plugin is not loaded.");
        }
    }
    
    function updateQuantity(cart_id, quantity) {
      $('.ajax-loader').css("visibility", "visible");
      $.post('{{ route('cart.updateQuantityV02') }}', {
          _token: '{{ csrf_token() }}',
          id: cart_id,
          quantity: quantity,
          overdueAmount: {{ $overdueAmount }},
          dueAmount: {{ $dueAmount }}
      }, function(data) {
          updateNavCart(data.nav_cart_view, data.cart_count);
          $('#cart_split_bill').html(data.cart_summary);
          $('#item_sub_total_span_' + cart_id).html(data.item_sub_total);
          $('#span_sub_total').html(data.span_sub_total);
          if ($('#spanPrice_' + cart_id).length) {
              $('#spanPrice_' + cart_id).html(data.price);
          }

          if ($('#updatePrice_' + cart_id).length) {
              $('#updatePrice_' + cart_id).html(data.update_price);
          }
          $('#cart-summary').empty(); // Clear the div before appending new data
          $('#cart-summary').append(data.html); // Append the response data
          // Call the initialization function
          initAizCarousel();
      });
      $('.ajax-loader').css("visibility", "hidden");
    }
    
    function showCheckoutModal() {
      $('#login-modal').modal();
    }

    // Country Code
    var isPhoneShown = true,
      countryData = window.intlTelInputGlobals.getCountryData(),
      input = document.querySelector("#phone-code");

    for (var i = 0; i < countryData.length; i++) {
      var country = countryData[i];
      if (country.iso2 == 'bd') {
        country.dialCode = '88';
      }
    }

    var iti = intlTelInput(input, {
      separateDialCode: true,
      utilsScript: "{{ static_asset('assets/js/intlTelutils.js') }}?1590403638580",
      onlyCountries: @php
        echo json_encode(\App\Models\Country::where('status', 1)->pluck('code')->toArray());
      @endphp,
      customPlaceholder: function(selectedCountryPlaceholder, selectedCountryData) {
        if (selectedCountryData.iso2 == 'bd') {
          return "01xxxxxxxxx";
        }
        return selectedCountryPlaceholder;
      }
    });

    var country = iti.getSelectedCountryData();
    $('input[name=country_code]').val(country.dialCode);

    input.addEventListener("countrychange", function(e) {
      // var currentMask = e.currentTarget.placeholder;

      var country = iti.getSelectedCountryData();
      $('input[name=country_code]').val(country.dialCode);

    });

    function toggleEmailPhone(el) {
      if (isPhoneShown) {
        $('.phone-form-group').addClass('d-none');
        $('.email-form-group').removeClass('d-none');
        $('input[name=phone]').val(null);
        isPhoneShown = false;
        $(el).html('{{ translate('Use Phone Instead') }}');
      } else {
        $('.phone-form-group').removeClass('d-none');
        $('.email-form-group').addClass('d-none');
        $('input[name=email]').val(null);
        isPhoneShown = true;
        $(el).html('{{ translate('Use Email Instead') }}');
      }
    }

    function updateCartPrice(cart_id){
      var update_price = $('#updatePrice_'+cart_id).val();
      const overdueAmount = {{ $overdueAmount }};
      const dueAmount = {{ $dueAmount }};
      $.ajax({
          url: '{{ route("updateCartPrice") }}',
          type: 'GET',
          beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
          data: { cart_id: cart_id, update_price: update_price },
          dataType: 'json',
          success: function (response) {
            $('#cart-summary').empty(); // Clear the div before appending new data
            $('#cart-summary').append(response.html); // Append the response data
          },
          complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            },
          error: function (xhr, status, error) {
              console.error(xhr.responseText);
          }
      });
    }

    // Expand/Collapse logic for "+ MORE"
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.more');
    if (!btn) return;

    const panelId = btn.getAttribute('aria-controls');
    const panel = document.getElementById(panelId);
    if (!panel) return;

    // Toggle state
    const isOpen = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', String(!isOpen));
    btn.textContent = isOpen ? '+ MORE' : '− LESS';

    // Animate with max-height + hidden attribute for a11y
    if (isOpen) {
      panel.classList.remove('open');
      // wait for transition to finish before hiding from a11y tree
      setTimeout(() => panel.hidden = true, 300);
    } else {
      panel.hidden = false;
      // ensure transition triggers
      requestAnimationFrame(() => panel.classList.add('open'));
    }

    e.preventDefault();
  });

  </script>
@endsection
