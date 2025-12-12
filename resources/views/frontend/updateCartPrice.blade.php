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
                          $total = $total + cart_product_price($cartItem, $product, false,true, Auth::user()->id) * ($cartItem['is_carton'] ? $cartItem['quantity'] * $product_stock->piece_per_carton : $cartItem['quantity']);
                          $product_name_with_choice = $product->getTranslation('name');
                        @endphp
                    @endif
                    <li class="list-group-item px-0 px-lg-3" id="cartRow_{{ $cartItem['id'] }}">
                      <div class="row gutters-5">
                        <div class="col-lg-1 d-flex">
                          <span class="mr-2 ml-0">
                            <input type="checkbox" id="{{ $cartItem['id'] }}" name="{{ $cartItem['id'] }}" value="{{ $cartItem['id'] }}" class="form-control save-for-later-checkbox">
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
                          @if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606))
                            <p>
                              <input type="number" names="updatePrice_{{ $cartItem['id'] }}" id="updatePrice_{{ $cartItem['id'] }}" value="{{ $cartItem['price'] }}" class="col border flex-grow-1 fs-16 input-number" autofocu style="width: 75px;">
                              <a href="javascript:void(0)" class="btn btn-primary fw-600" style="height: 29px; padding: 4px 8px 16px 12px; margin-bottom: 3px;" onclick="updateCartPrice({{ $cartItem['id'] }})"> Update </a>
                            </p>
                          @else
                            <span class="fw-600 fs-16">{{ single_price(cart_product_price($cartItem, $product, true, false, Auth::user()->id)) }}</span>
                          @endif
                          
                        </div>

                        <div class="col-lg-2 col-6 order-4 order-lg-0 text-center">
                          @if ($cartItem['digital'] != 1)
                            <div class="row no-gutters align-items-center aiz-plus-minus mr-2 ml-0">
                              <button class="btn col-auto btn-icon btn-sm btn-circle btn-light" type="button" data-type="minus" data-field="quantity[{{ $cartItem['id'] }}]"  data-cart-id="{{ $cartItem['id'] }}"> <i class="las la-minus"></i> </button>

                              {{-- <input type="number" name="quantity[{{ $cartItem['id'] }}]" class="col border-0 text-center flex-grow-1 fs-16 input-number" placeholder="1" value="{{ $cartItem['quantity'] }}" min="{{ $product->min_qty }}" max="{{ $product_stock->qty }}"> --}}
                              <input type="number" name="quantity[{{ $cartItem['id'] }}]" id="{{ $cartItem['id'] }}" class="col border-0 text-center flex-grow-1 fs-16 input-number" placeholder="1" value="{{ $cartItem['quantity'] }}">

                              <button class="btn col-auto btn-icon btn-sm btn-circle btn-light" type="button" data-type="plus" data-field="quantity[{{ $cartItem['id'] }}]" data-cart-id="{{ $cartItem['id'] }}"> <i class="las la-plus"></i> </button>
                            </div>
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
                            {{ single_price($cartItem['price'] * ($cartItem['is_carton'] ? $cartItem['quantity'] * $product_stock->piece_per_carton : $cartItem['quantity'])) }}
                          @else
                            {{ single_price(cart_product_price($cartItem, $product, false,true, Auth::user()->id) * ($cartItem['is_carton'] ? $cartItem['quantity'] * $product_stock->piece_per_carton : $cartItem['quantity'])) }}
                          @endif

                          </span>
                        </div>
                        <div class="col-lg-2 col-6 order-5 order-lg-0  text-center">
                          <a href="javascript:void(0)" onclick="removeFromCartView(event, {{ $cartItem['id'] }})"
                            class="btn btn-icon btn-sm btn-soft-primary btn-circle">
                            <i class="las la-trash"></i>
                          </a>
                          <a href="javascript:void(0)" onclick="saveForLater(event, {{ $cartItem['id'] }})"
                            class="btn btn-icon btn-sm btn-soft-primary btn-circle" title="Save for later.">
                            <i class="las la-bookmark"></i>
                          </a> 
                          {{-- <button class="btn btn-info fw-600" onclick="saveForLater(event, {{ $cartItem['id'] }})">Save for later</button>--}}
                        </div>
                      </div>
                    </li>
                  @endforeach
                </ul>
              </div>
              
              <div class="px-3 py-2 mb-4 border-top d-flex justify-content-between">
                <span class="opacity-60 fs-15">{{ translate('Subtotal') }}</span>
                <span class="fw-600 fs-17" id="span_sub_total">{{ single_price($total) }}</span>
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
      <div class="col-lg-4 mt-lg-0 mt-4 position-fixed" id="cart_split_bill">
        @include('frontend.partials.cart_bill_amount_v02')
      </div>
    @endif
  </div>
</div>