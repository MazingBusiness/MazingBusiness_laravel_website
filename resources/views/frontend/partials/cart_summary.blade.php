<div id="pagetop" class="card rounded border-0 shadow-sm"></div>

<!-- <div class="card rounded border-0 shadow-sm">
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
              $subtotal_for_min_order_amount += cart_product_price($cartItem, $cartItem->product, false, false) * $cartItem['quantity'] * $ppc;
          } else {
              $subtotal_for_min_order_amount += cart_product_price($cartItem, $cartItem->product, false, false) * $cartItem['quantity'];
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
            if ($cartItem['is_carton']) {
                $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'] * $ppc;
                $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'] * $ppc;
            } else {
                $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
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
              <span
                class="pl-4 pr-0">{{ $cartItem['is_carton'] ? single_price(cart_product_price($cartItem, $cartItem->product, false, false) * $cartItem['quantity'] * $ppc) : single_price(cart_product_price($cartItem, $cartItem->product, false, false) * $cartItem['quantity']) }}</span>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <input type="hidden" id="sub_total" value="{{ $subtotal }}">
    <table class="table mb-0">

      <tfoot>
        <tr class="cart-subtotal">
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
          $total = $subtotal + $tax + $shipping;
          if (Session::has('club_point')) {
              $total -= Session::get('club_point');
          }
          if ($coupon_discount > 0) {
              $total -= $coupon_discount;
          }
        @endphp

        <tr id="payment-discount" data-discount="{{ convert_price(0.02 * $total) }}"
          data-total="{{ convert_price($total) }}">
          <th><span class="strong-600">{{ translate('NEFT Discount') }}</span></th>
          <td class="text-right">
            <strong><span>{{ single_price(0.02 * $total) }}</span></strong>
          </td>
        </tr>

        <tr class="cart-total">
          <th><span class="strong-600">{{ translate('Total') }}</span></th>
          <td class="text-right">
            <strong><span class="pay_total">{{ single_price($total - 0.02 * $total) }}</span></strong>
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
</div> -->

<!-- <div class="card">
  <div class="card-header">
    <h3 class="fs-16 fw-600 mb-0">{{ translate('Payable Amount') }}</h3>
  </div>
  <div class="card-body text-center"><span
      class="display-4 text-primary font-weight-bold pay_total">{{ single_price($total - 0.02 * $total) }}</span>
  </div>
  
</div> -->

<div id="shifttotop">
  <div class="card-header">
    <h3 class="fs-16 fw-600 mb-0">{{ translate('Payable Amount') }}</h3>
  </div>
  <div class="card-body text-center"><span
      class="display-4 text-primary font-weight-bold pay_total">{{ single_price($total) }}</span>
  </div>
    <button type="button" style="width:100%" onclick="submitOrder(this)"
      class="btn btn-primary fw-600">{{ translate('Complete Order') }}</button>
</div>

<script>
  document.getElementById('pagetop').appendChild(document.getElementById('shifttotop'));
</script>
