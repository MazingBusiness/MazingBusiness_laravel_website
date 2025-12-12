@extends('frontend.layouts.app')

@section('content')
  <section class="pt-3 mb-2">
    <div class="container">
      <div class="row">
        <div class="col-md-10 col-lg-9 col-xl-8 mx-auto">
          <div class="row aiz-steps arrow-divider">
            <div class="col done">
              <div class="text-success text-center">
                <i class="la-2x las la-shopping-cart mb-2"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('1. My Cart') }}</h3>
              </div>
            </div>
            <div class="col done">
              <div class="text-success text-center">
                <i class="la-2x las la-map mb-2"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('2. Shipping Company') }}</h3>
              </div>
            </div>
            <!-- <div class="col done">
              <div class="text-success text-center">
                <i class="la-2x las la-truck mb-2"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('3. Delivery info') }}</h3>
              </div>
            </div> -->
            <div class="col active">
              <div class="text-primary text-center">
                <i class="la-2x las la-credit-card mb-2"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('3. Payment') }}</h3>
              </div>
            </div>
            <div class="col">
              <div class="text-center">
                <i class="la-2x las la-check-circle mb-2 opacity-50"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block opacity-50">{{ translate('4. Confirmation') }}
                </h3>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <section class="mb-4">
    <div class="container text-left">
      <div class="row">
        <div class="col-lg-8">
          @php
            $coupons = \App\Models\Coupon::whereNull('customer_id')
                ->orWhere('customer_id', Auth::user()->id)
                ->get();

          @endphp
          @if (count($coupons))
            <div class="card rounded border-0 shadow-sm">
              <div class="card-header bg-warning">
                <h3 class="fs-16 fw-600 mb-0">{{ translate('Available Coupons') }}</h3>
              </div>

              <div class="card-body bg-primary" style="border-top-left-radius: 0;border-top-right-radius: 0;">
                <div class="aiz-carousel gutters-5 couponslider" data-items="3" data-xl-items="2" data-lg-items="2"
                  data-md-items="2" data-sm-items="1" data-xs-items="1" data-infinite="true" data-arrows="true"
                  data-dots="false" data-autoplay="false">
                  @foreach ($coupons as $key => $coupon)
                    <div class="carousel-box d-flex">
                      <div class="coupon-code card p-3 w-100">
                        <div class="card-body p-0">
                          <span
                            class="coupon_code @if ($carts[0]['coupon_code'] == $coupon->code) text-white bg-success @endif">{{ $coupon->code }}</span><span
                            class="selected las la-check-circle align-middle h4 ml-1 mb-0 text-success @unless ($carts[0]['coupon_code'] == $coupon->code) d-none @endunless"></span><span
                            class="apply_code @if ($carts[0]['coupon_applied']) d-none @endif float-right pointer text-secondary small fw-600 text-dark"
                            data-code="{{ $coupon->code }}">Apply Coupon</span><span
                            class="remove_code @unless ($carts[0]['coupon_code'] == $coupon->code) d-none @endunless float-right pointer text-secondary small fw-600 text-dark"
                            data-code="{{ $coupon->code }}">Remove Coupon</span><br>{{ $coupon->description }}
                        </div>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
          @endif
          <form  action="{{ route('payment.checkout') }}" class="form-default" role="form" method="POST"
            id="checkout-form">
            @csrf
            <input type="hidden" name="owner_id" value="{{ $carts[0]['owner_id'] }}">
            <div class="card rounded border-0 shadow-sm">
              <!-- <div class="card-header p-3">
                <h3 class="fs-16 fw-600 mb-0">
                  {{ translate('Any additional info?') }}
                </h3>
              </div>
              <div class="form-group px-3 pt-3">
                @php $shipper_details = explode(',', $custom_shipper) @endphp
                @if ($carts[0]['carrier_id'] == 372)
                  <textarea name="additional_info" rows="4" class="form-control" placeholder="{{ translate('Type your text') }}">Shipper Type: Other Shipper&#13;&#10;Shipper Name: {{ $shipper_details[0] }}&#13;&#10;Shipper GSTIN: {{ $shipper_details[1] }}&#13;&#10;Shipper Phone No.: {{ $shipper_details[2] }}</textarea>
                @else
                  <textarea name="additional_info" rows="2" class="form-control" placeholder="{{ translate('Type your text') }}"></textarea>
                @endif
              </div> -->

              <div class="card-header p-3">
                <h3 class="fs-16 fw-600 mb-0">
                  {{ translate('Select a payment option') }}
                </h3>
              </div>
              <div class="card-body text-center">
              <input value="cash_on_delivery" class="online_payment d-none" type="radio"
                                name="payment_option" checked>

                <!-- <div class="row"> -->
                  <!-- <div class="col-12 mx-auto"> -->
                    <!-- <div class="row gutters-10"> -->

                          <!-- <div class="col-6 col-md-4">
                            <label class="aiz-megabox d-block mb-3">
                              <input value="cash_on_delivery" class="online_payment" type="radio"
                                name="payment_option" checked>
                              <span class="d-block aiz-megabox-elem p-3">
                                <img src="{{ static_asset('assets/img/cards/cod.png') }}" class="img-fluid mb-2">
                                <span class="d-block text-center">
                                  <span class="d-block fw-600 fs-15">{{ translate('Cash on Delivery') }}</span>
                                </span>
                              </span>
                            </label>
                          </div> -->
                      <!-- @if (Auth::check())
                        @if (addon_is_activated('offline_payment'))
                          @foreach (\App\Models\ManualPaymentMethod::all() as $method)
                            <div class="col-6 col-md-4">
                              <label class="aiz-megabox d-block mb-3">
                                <input value="{{ $method->heading }}" type="radio" name="payment_option"
                                  class="offline_payment_option"
                                  onchange="toggleManualPaymentData({{ $method->id }})"
                                  data-id="{{ $method->id }}">
                                <span class="d-block aiz-megabox-elem p-3">
                                  <img src="{{ uploaded_asset($method->photo) }}" class="img-fluid mb-2">
                                  <span class="d-block text-center">
                                    <span class="d-block fw-600 fs-15">{{ $method->heading }}</span>
                                  </span>
                                </span>
                              </label>
                            </div>
                          @endforeach

                          @foreach (\App\Models\ManualPaymentMethod::all() as $method)
                            <div id="manual_payment_info_{{ $method->id }}" class="d-none">
                              @php echo $method->description @endphp
                              @if ($method->bank_info != null)
                                @foreach (json_decode($method->bank_info) as $key => $info)
                                  <div class="row">
                                    <div class="col-sm-6"><b>{{ translate('Bank Name') }}</b> - {{ $info->bank_name }}
                                    </div>
                                    <div class="col-sm-6"><b>{{ translate('Account Name') }}</b> -
                                      {{ $info->account_name }}
                                    </div>
                                    <div class="col-sm-6"><b>{{ translate('Account Number') }}</b> -
                                      {{ $info->account_number }}</div>
                                    <div class="col-sm-6"><b>{{ translate('IFSC Code') }}</b> -
                                      {{ $info->routing_number }}
                                    </div>
                                  </div>
                                @endforeach
                              @endif
                            </div>
                          @endforeach
                        @endif
                      @endif -->
                    <!-- </div> -->
                  <!-- </div> -->
                <!-- </div> -->

                @if (addon_is_activated('offline_payment'))
                  <div class="d-none mb-3 rounded border bg-white p-3 text-left">
                    <div id="manual_payment_description" class="mb-4">

                    </div>
                    <div class="row">
                      <div class="col-md-3">
                        <label>{{ translate('Transaction ID') }} <span class="text-danger">*</span></label>
                      </div>
                      <div class="col-md-9">
                        <input type="text" class="form-control mb-3" name="trx_id" id="trx_id"
                          placeholder="{{ translate('Transaction ID') }}" required>
                      </div>
                    </div>
                    <div class="form-group row">
                      <label class="col-md-3 col-form-label">{{ translate('Upload Transaction Receipt') }}</label>
                      <div class="col-md-9">
                        <div class="input-group" data-toggle="aizuploader" data-type="image">
                          <div class="input-group-prepend">
                            <div class="input-group-text bg-soft-secondary font-weight-medium">
                              {{ translate('Browse') }}</div>
                          </div>
                          <div class="form-control file-amount">{{ translate('Choose image') }}
                          </div>
                          <input type="hidden" name="photo" class="selected-files">
                        </div>
                        <div class="file-preview box sm">
                        </div>
                      </div>
                    </div>
                  </div>
                @endif
                @if (Auth::check() && get_setting('wallet_system') == 1 && false)
                  <div class="separator mb-3">
                    <span class="bg-white px-3">
                      <span class="opacity-60">{{ translate('Or') }}</span>
                    </span>
                  </div>
                  <div class="py-4 text-center">
                    <div class="h6 mb-3">
                      <span class="opacity-80">{{ translate('Your wallet balance :') }}</span>
                      <span class="fw-600">{{ single_price(Auth::user()->balance) }}</span>
                    </div>
                    @if (Auth::user()->balance < $total)
                      <button type="button" class="btn btn-secondary" disabled>
                        {{ translate('Insufficient balance') }}
                      </button>
                    @else
                      <button type="button" onclick="use_wallet()" class="btn btn-primary fw-600">
                        {{ translate('Pay with wallet') }}
                      </button>
                    @endif
                  </div>
                @endif
              </div>

              <div class="card rounded border-0 shadow-sm">
            <div class="card-header">
              <h3 class="fs-16 fw-600 mb-0">{{ translate('Summary') }}</h3>
              <div class="text-right">
                <span class="badge badge-inline badge-primary">
                  {{ translate('Items') }}
                </span>
                @php
                  $coupon_discount = 0;
                @endphp
                @if (Auth::check() && get_setting('coupon_system') == 1)
                  @php
                    $coupon_code = null;
                  @endphp

                  @foreach ($carts as $key => $cartItem)
                    @php
                      $product = \App\Models\Product::find($cartItem['product_id']);
                    @endphp
                    @if ($cartItem->coupon_applied == 1)
                      @php
                        $coupon_code = $cartItem->coupon_code;
                        break;
                      @endphp
                    @endif
                  @endforeach

                  @php
                    $coupon_discount = carts_coupon_discount($coupon_code);
                  @endphp
                @endif

                @php $subtotal_for_min_order_amount = 0; @endphp
                @foreach ($carts as $key => $cartItem)
                  @php
                    if ($cartItem['is_carton']) {
                        $product = \App\Models\Product::find($cartItem['product_id']);
                        $ppc = $product->stocks->first()->piece_per_carton;
                        $subtotal_for_min_order_amount += cart_product_price($cartItem, $cartItem->product, false, false, Auth::user()->id) * $cartItem['quantity'] * $ppc;
                    } else {
                        $subtotal_for_min_order_amount += cart_product_price($cartItem, $cartItem->product, false, false, Auth::user()->id) * $cartItem['quantity'];
                    }
                  @endphp
                @endforeach

                @if (get_setting('minimum_order_amount_check') == 1 &&
                        $subtotal_for_min_order_amount < get_setting('minimum_order_amount'))
                  <span class="badge badge-inline badge-primary">
                    {{ translate('Minimum Order Amount') . ' ' . single_price(get_setting('minimum_order_amount')) }}
                  </span>
                @endif
              </div>
            </div>

            <div class="card-body p-2">
              @if (addon_is_activated('club_point'))
                @php
                  $total_point = 0;
                @endphp
                @foreach ($carts as $key => $cartItem)
                  @php
                    $product = \App\Models\Product::find($cartItem['product_id']);
                    if ($cartItem['is_carton']) {
                        $product = \App\Models\Product::find($cartItem['product_id']);
                        $ppc = $product->stocks->first()->piece_per_carton;
                        $total_point += $product->earn_point * $cartItem['quantity'] * $ppc;
                    } else {
                        $total_point += $product->earn_point * $cartItem['quantity'];
                    }
                  @endphp
                @endforeach

                <div class="bg-soft-primary border-soft-primary mb-2 rounded border px-2">
                  {{ translate('Total Club point') }}:
                  <span class="fw-700 float-right">{{ $total_point }}</span>
                </div>
              @endif
              <table class="table">
                <thead>
                  <tr>
                    <th class="product-name border-0">{{ translate('Product') }}</th>
                    <th class="product-total text-right border-0">{{ translate('Total') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @php
                    $subtotal = 0;
                    $tax = 0;
                    $shipping = 0;
                    $product_shipping_cost = 0;
                    $shipping_region = $shipping_info['city'];
                  @endphp
                  @foreach ($carts as $key => $cartItem)
                    @php
                      $product = \App\Models\Product::find($cartItem['product_id']);
                      $ppc = $product->stocks->first()->piece_per_carton;
                      if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606)){
                        if ($cartItem['is_carton']) {
                            $subtotal += $cartItem['price'] * $cartItem['quantity'] * $ppc;
                            $tax += $cartItem['price'] * $cartItem['quantity'] * $ppc;
                        } else {
                            $subtotal += $cartItem['price'] * $cartItem['quantity'];
                            $tax += $cartItem['price'] * $cartItem['quantity'];
                        }
                      }else{
                        if ($cartItem['is_carton']) {
                            $subtotal += cart_product_price($cartItem, $product, false, false, Auth::user()->id) * $cartItem['quantity'] * $ppc;
                            $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'] * $ppc;
                        } else {
                            $subtotal += cart_product_price($cartItem, $product, false, false, Auth::user()->id) * $cartItem['quantity'];
                            $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                        }
                      }
                      
                      $product_shipping_cost = $cartItem['shipping_cost'];

                      $shipping += $product_shipping_cost;

                      $product_name_with_choice = $product->getTranslation('name');
                      if ($cartItem['is_carton']) {
                          $product_name_with_choice = $product->getTranslation('name');
                      }
                    @endphp
                    <tr class="cart_item">
                      <td class="product-name">
                        {{ $product_name_with_choice }}
                        <strong class="product-quantity">
                          ×
                          {{ $cartItem['quantity'] }}
                          {{ $cartItem['is_carton'] ? Str::plural('Carton', $cartItem['quantity']) : Str::plural('Piece', $cartItem['quantity']) }}
                        </strong>
                      </td>
                      <td class="product-total text-right">
                        <span class="pl-4 pr-0">
                          @if(session()->has('staff_id') AND (session()->get('staff_id')==180 OR session()->get('staff_id')==169 OR session()->get('staff_id')==25606))
                              <?php /* ?>{{ single_price(($cartItem['price'] * $cartItem['quantity']) * $ppc) }} <?php */ ?>
                              {{ $cartItem['is_carton'] ? single_price(($cartItem['price'] * $cartItem['quantity']) * $ppc) : single_price(($cartItem['price'] * $cartItem['quantity'])) }}
                          @else
                            {{ $cartItem['is_carton'] ? single_price(cart_product_price($cartItem, $cartItem->product, false, false, Auth::user()->id) * $cartItem['quantity'] * $ppc) : single_price(cart_product_price($cartItem, $cartItem->product, false, false, Auth::user()->id) * $cartItem['quantity']) }}
                          @endif
                        </span>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
              <input type="hidden" id="sub_total" value="{{ $subtotal }}">
              <table class="table mb-0">

                <tfoot>
                  <!-- <tr class="cart-subtotal">
                    <th>{{ translate('Subtotal') }}</th>
                    <td class="text-right">
                      <span class="fw-600">{{ single_price($subtotal) }}</span>
                    </td>
                  </tr>

                  <tr class="cart-shipping">
                    <th>{{ translate('Tax') }}</th>
                    <td class="text-right">
                      <span class="font-italic">{{ single_price($tax) }}</span>
                    </td>
                  </tr>

                  <tr class="cart-shipping">
                    <th>{{ translate('Shipping Through') }}</th>
                    <td class="text-right">
                      <span class="font-italic">{{ $shipper_details[0] }}</span>
                    </td>
                  </tr>

                  <tr class="cart-shipping">
                    <th>{{ translate('Total Shipping') }}</th>
                    <td class="text-right">
                      <span class="font-italic">{{ single_price($shipping) }}</span>
                    </td>
                  </tr>

                  @if (Session::has('club_point'))
                    <tr class="cart-shipping">
                      <th>{{ translate('Redeem point') }}</th>
                      <td class="text-right">
                        <span class="font-italic">{{ single_price(Session::get('club_point')) }}</span>
                      </td>
                    </tr>
                  @endif

                  @if ($coupon_discount > 0)
                    <tr class="cart-shipping">
                      <th>{{ translate('Coupon Discount') }}</th>
                      <td class="text-right">
                        <span class="font-italic">{{ single_price($coupon_discount) }}</span>
                      </td>
                    </tr>
                  @endif

                  @php
                    $total = $subtotal  + $shipping;
                    if (Session::has('club_point')) {
                        $total -= Session::get('club_point');
                    }
                    if ($coupon_discount > 0) {
                        $total -= $coupon_discount;
                    }
                  @endphp -->

                  <!-- <tr id="payment-discount" data-discount="{{ convert_price(0.02 * $total) }}"
                    data-total="{{ convert_price($total) }}">
                    <th><span class="strong-600">{{ translate('NEFT Discount') }}</span></th>
                    <td class="text-right">
                      <strong><span>{{ single_price(0.02 * $total) }}</span></strong>
                    </td>
                  </tr> -->

                  <tr class="cart-total">
                    <th><span class="strong-600">{{ translate('Total') }}</span></th>
                    <td class="text-right">
                      <strong><span class="pay_total">{{ single_price($total) }}</span></strong>
                    </td>
                  </tr>
                </tfoot>
              </table>


              @if (addon_is_activated('club_point'))
                @if (Session::has('club_point'))
                  <div class="mt-3">
                    <form class="" action="{{ route('checkout.remove_club_point') }}" method="POST"
                      enctype="multipart/form-data">
                      @csrf
                      <div class="input-group">
                        <input type="hidden" name="custom_shipper" value="{{ $custom_shipper }}">
                        <div class="form-control">{{ Session::get('club_point') }}</div>
                        <div class="input-group-append">
                          <button type="submit" class="btn btn-primary">{{ translate('Remove Redeem Point') }}</button>
                        </div>
                      </div>
                    </form>
                  </div>
                @endif
              @endif
              @if (Auth::check() && get_setting('coupon_system') == 1)
                @if ($coupon_discount > 0 && $coupon_code)
                  <form id="remove-coupon-form" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="custom_shipper" value="{{ $custom_shipper }}">
                  </form>
                @else
                  <form id="apply-coupon-form" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="owner_id" value="{{ $carts[0]['owner_id'] }}">
                    <input type="hidden" name="custom_shipper" value="{{ $custom_shipper }}">
                    <input type="hidden" name="code" value="{{ $custom_shipper }}">
                  </form>
                @endif
              @endif
            </div>
          </div>
            </div>
            <div class="pt-3">
              <label class="aiz-checkbox">
                <input type="checkbox" required id="agree_checkbox" checked>
                <span class="aiz-square-check"></span>
                <span>{{ translate('I agree to the') }}</span>
              </label>
              <a href="{{ route('terms') }}">{{ translate('terms and conditions') }}</a>,
              <a href="{{ route('returnpolicy') }}">{{ translate('return policy') }}</a> &
              <a href="{{ route('privacypolicy') }}">{{ translate('privacy policy') }}</a>
            </div>

            <div class="row align-items-center pt-3">
              <div class="col-6">
                <a href="{{ route('home') }}" class="link link--style-3">
                  <i class="las la-arrow-left"></i>
                  {{ translate('Return to shop') }}
                </a>
              </div>
              <!-- <div class="col-6 text-right">
                <button type="button" onclick="submitOrder(this)"
                  class="btn btn-primary fw-600">{{ translate('Complete Order') }}</button>
              </div> -->
            </div>
          </form>
        </div>

        <div class="col-lg-4 mt-lg-0 mt-4" id="cart_summary">
          <?php /* ?> @include('frontend.partials.cart_summary') <?php */ ?>
          <div id="pagetop" class="card rounded border-0 shadow-sm"></div>
          <div id="shifttotop">
            <div class="card-header">
              <h3 class="fs-16 fw-600 mb-0">{{ translate('Payable Amount') }}</h3>
            </div>
            <div class="card-body text-center"><span
                class="display-4 text-primary font-weight-bold pay_total">{{ single_price($total) }}</span>
            </div>
              <button type="button" style="width:100%" onclick="submitOrder(this)"
                class="btn btn-primary fw-600">{{ translate('Complete Order') }}</button>

                @if(session()->has('staff_id'))
                  <a href="javascript:void(0);" id="get-quotations-btn" style="width:100%; margin-top:6px; color:white;" 
                  class="btn btn-success fw-600">{{ translate('GET QUOTATIONS') }}</a>
                  @if (session('status'))
                     <span style="margin-top:5px;color:green;";>{{ session('status') }}</span>
                  @endif
                @endif
          </div>

          <script>
            document.getElementById('pagetop').appendChild(document.getElementById('shifttotop'));
          </script>
        </div>
      </div>
    </div>
  </section>
@endsection

@section('script')
  <script type="text/javascript">
    $(document).ready(function() {
      $(".online_payment").click(function() {
        $('#manual_payment_description').parent().addClass('d-none');
        var neft_discount = $('#payment-discount');
        var discount = neft_discount.attr('data-discount');
        var total = neft_discount.attr('data-total');
        neft_discount.addClass('d-none');
        $('.pay_total').html('₹ ' + new Intl.NumberFormat().format(Math.round(total)));

      });
      toggleManualPaymentData($('input[name=payment_option]:checked').data('id'));

      $(".offline_payment_option").click(function() {
        var neft_discount = $('#payment-discount');
        var discount = neft_discount.attr('data-discount');
        var total = neft_discount.attr('data-total');
        neft_discount.removeClass('d-none');
        $('.pay_total').html('₹ ' + new Intl.NumberFormat().format(Math.round(total - discount)));
      });
    });



    var minimum_order_amount_check = {{ get_setting('minimum_order_amount_check') == 1 ? 1 : 0 }};
    var minimum_order_amount =
      {{ get_setting('minimum_order_amount_check') == 1 ? get_setting('minimum_order_amount') : 0 }};

    function use_wallet() {
      $('input[name=payment_option]').val('wallet');
      if ($('#agree_checkbox').is(":checked")) {
        ;
        if (minimum_order_amount_check && $('#sub_total').val() < minimum_order_amount) {
          AIZ.plugins.notify('danger',
            '{{ translate('You order amount is less then the minimum order amount') }}');
        } else {
          $('#checkout-form').submit();
        }
      } else {
        AIZ.plugins.notify('danger', '{{ translate('You need to agree with our policies') }}');
      }
    }

    function submitOrder(el) {
      $(el).prop('disabled', true);
      if ($('#agree_checkbox').is(":checked")) {
        if (minimum_order_amount_check && $('#sub_total').val() < minimum_order_amount) {
          AIZ.plugins.notify('danger',
            '{{ translate('You order amount is less then the minimum order amount') }}');
        } else {
          var offline_payment_active = '{{ addon_is_activated('offline_payment') }}';
          if ((offline_payment_active == 'true' || offline_payment_active == 1) && $('.offline_payment_option').is(
              ":checked") && $('#trx_id').val() == '') {
            AIZ.plugins.notify('danger',
              '{{ translate('You need to put Transaction id') }}');
            $(el).prop('disabled', false);
          } else {

            $('#checkout-form').submit();
          }
        }

      } else {
        AIZ.plugins.notify('danger', '{{ translate('You need to agree with our policies') }}');
        $(el).prop('disabled', false);
      }
    }

    function toggleManualPaymentData(id) {
      if (typeof id != 'undefined') {
        $('#manual_payment_description').parent().removeClass('d-none');
        $('#manual_payment_description').html($('#manual_payment_info_' + id).html());
      }
    }

    $(document).on("click", ".apply_code", function() {
      var current = $(this);
      $('#apply-coupon-form input[name=code]').val(current.attr('data-code'));
      var data = new FormData($('#apply-coupon-form')[0]);
      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        method: "POST",
        url: "{{ route('checkout.apply_coupon_code') }}",
        data: data,
        cache: false,
        contentType: false,
        processData: false,
        success: function(data, textStatus, jqXHR) {
          AIZ.plugins.notify(data.response_message.response, data.response_message.message);
          $("#cart_summary").html(data.html);
          if (data.response_message.response == 'success') {
            $('.apply_code').addClass('d-none');
            current.siblings('.coupon_code').addClass('bg-success text-white');
            current.siblings('.selected, .remove_code').removeClass('d-none');
          }
        }
      })
    });

    $(document).on("click", ".remove_code", function() {
      var current = $(this);
      var data = new FormData($('#remove-coupon-form')[0]);
      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        method: "POST",
        url: "{{ route('checkout.remove_coupon_code') }}",
        data: data,
        cache: false,
        contentType: false,
        processData: false,
        success: function(data, textStatus, jqXHR) {
          $("#cart_summary").html(data);
          $('.coupon_code').removeClass('bg-success text-white');
          $('.remove_code, .selected').addClass('d-none');
          $('.apply_code').removeClass('d-none');
          current.parents('.coupon-code').removeClass('border-primary');
        }
      })
    })
  </script>

<!-- <script>
   document.getElementById('get-quotations-btn').addEventListener('click', function() {
      $.ajax({
         url: "{{ route('cart.send-quotations') }}", // Your route here
         type: "GET", // or "POST" depending on your route's HTTP method
         success: function(response) {
            // If the response contains a status message, display it
            if (response.status) {
              AIZ.plugins.notify('success', response.status);
               $('#status-message').text(response.status);
            }
            // Optionally, redirect the user after the request is successful
            // window.location.href = "some-other-page";
         },
         error: function(xhr, status, error) {
            // Handle any errors that occurred during the AJAX request
            console.error("AJAX request failed: ", status, error);
         }
      });
   });
</script> -->
<script>
   document.getElementById('get-quotations-btn').addEventListener('click', function() {
      var button = this;
      var originalText = button.textContent;

      // Disable the button and change its text to "Please wait..."
      button.textContent = 'Please wait...';
      button.disabled = true;

      $.ajax({
         url: "{{ route('cart.send-quotations') }}", // Your route here
         type: "GET", // or "POST" depending on your route's HTTP method
         success: function(response) {
            // If the response contains a status message, display it
            if (response.status) {
              AIZ.plugins.notify('success', response.status);
               $('#status-message').text(response.status);
            }
         },
         error: function(xhr, status, error) {
            // Handle any errors that occurred during the AJAX request
            console.error("AJAX request failed: ", status, error);
         },
         complete: function() {
            // Re-enable the button and revert its text to the original after the request completes
            button.textContent = originalText;
            button.disabled = false;
         }
      });
   });
</script>

@endsection
