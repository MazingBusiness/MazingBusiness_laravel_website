@extends('frontend.layouts.app')

@section('content')
  <section class="pt-3 mb-2">
    <div class="container">
      <div class="row">
        <div class="col-md-10 col-lg-9 col-xl-8 mx-auto">
          <div class="row aiz-steps arrow-divider">
            <div class="col done">
              <div class="text-center text-success">
                <i class="la-2x mb-2 las la-shopping-cart"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('1. My Cart') }}</h3>
              </div>
            </div>
            <div class="col done">
              <div class="text-center text-success">
                <i class="la-2x mb-2 las la-map"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('2. Shipping Company') }}</h3>
              </div>
            </div>
            <!-- <div class="col active">
              <div class="text-center text-primary">
                <i class="la-2x mb-2 las la-truck"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('3. Delivery info') }}</h3>
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
                <h3 class="fs-12 fw-600 d-none d-lg-block opacity-50">{{ translate('4. Confirmation') }}</h3>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="mb-4 gry-bg">
    <div class="container">
      <div class="row">
        <div class="col-xxl-8 col-xl-10 mx-auto">
          <form class="form-default" action="{{ route('checkout.store_delivery_info') }}" role="form" method="POST">
            @csrf
            @php
              $admin_products = [];
              $seller_products = [];
              $address = \App\Models\Address::find($carts->first()->address_id);
              foreach ($carts as $key => $cartItem) {
                  $product = \App\Models\Product::find($cartItem['product_id']);
                  if ($product->added_by == 'admin') {
                      array_push($admin_products, $cartItem['product_id']);
                  } else {
                      $product_ids = [];
                      if (isset($seller_products[$product->user_id])) {
                          $product_ids = $seller_products[$product->user_id];
                      }
                      array_push($product_ids, $cartItem['product_id']);
                      $seller_products[$product->user_id] = $product_ids;
                  }
              }

              $pickup_point_list = [];
              if (get_setting('pickup_point') == 1) {
                  $pickup_point_list = \App\Models\PickupPoint::where('pick_up_status', 1)->get();
              }
            @endphp
            @if (!empty($admin_products))
              <div class="card mb-3 shadow-sm border-0 rounded">
                <div class="card-header p-3">
                  <h5 class="fs-16 fw-600 mb-0">{{ get_setting('site_name') }} {{ translate('Products') }}</h5>
                </div>
                <div class="card-body">
                  <ul class="list-group list-group-flush">
                    @php
                      $physical = false;
                    @endphp
                    @foreach ($admin_products as $key => $cartItem)
                      @php
                        $product = \App\Models\Product::find($cartItem);
                        if ($product->digital == 0) {
                            $physical = true;
                        }
                      @endphp
                      <li class="list-group-item">
                        <div class="d-flex">
                          <span class="mr-2">
                            <img src="{{ uploaded_asset($product->thumbnail_img) }}" class="img-fit size-60px rounded"
                              alt="{{ $product->getTranslation('name') }}">
                          </span>
                          <span class="fs-14 opacity-60">{{ $product->getTranslation('name') }}</span>
                        </div>
                      </li>
                    @endforeach
                  </ul>
                  <!-- Transporter -->
                </div>
              </div>
            @endif
            @if (!empty($seller_products))
              @foreach ($seller_products as $key => $seller_product)
                <div class="card mb-3 shadow-sm border-0 rounded">
                  <div class="card-header p-3">
                    <h5 class="fs-16 fw-600 mb-0">{{ \App\Models\Shop::where('user_id', $key)->first()->name }}
                      {{ translate('Products') }}</h5>
                  </div>
                  <div class="card-body">
                    <ul class="list-group list-group-flush">
                      @php
                        $physical = false;
                      @endphp
                      @foreach ($seller_product as $cartItem)
                        @php
                          $product = \App\Models\Product::find($cartItem);
                          if ($product->digital == 0) {
                              $physical = true;
                          }
                        @endphp
                        <li class="list-group-item">
                          <div class="d-flex">
                            <span class="mr-2">
                              <img src="{{ uploaded_asset($product->thumbnail_img) }}"
                                class="img-fit size-60px rounded" alt="{{ $product->getTranslation('name') }}">
                            </span>
                            <span class="fs-14 opacity-60">{{ $product->getTranslation('name') }}</span>
                          </div>
                        </li>
                      @endforeach
                    </ul>

                    
                  </div>
                </div>
              @endforeach
            @endif

            <div class="pt-4 d-flex justify-content-between align-items-center">
              <a href="{{ route('home') }}">
                <i class="la la-angle-left"></i>
                {{ translate('Return to shop') }}
              </a>
              <button type="submit" class="btn fw-600 btn-primary">{{ translate('Continue to Payment') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>

@endsection

@section('script')
  <script type="text/javascript">
    function display_option(key) {

    }

    $('.choose-other').on('click', function() {
      $('.other_shipper').removeClass('d-none');
      $('.other_shipper input').prop('required', true);
      $('.aiz-selectpicker[name=shipper]').val(372).change();
    });

    $('.aiz-selectpicker').on('change', function() {
      if ($(this).val() == 372) {
        $('.other_shipper').removeClass('d-none');
        $('.other_shipper input').prop('required', true);
      } else {
        $('.other_shipper').addClass('d-none');
        $('.other_shipper input').prop('required', false);
      }
    })

    function show_pickup_point(el, type) {
      var value = $(el).val();
      var target = $(el).data('target');

      // console.log(value);

      if (value == 'home_delivery' || value == 'carrier') {
        if (!$(target).hasClass('d-none')) {
          $(target).addClass('d-none');
        }
        $('.carrier_id_' + type).removeClass('d-none');
      } else {
        $(target).removeClass('d-none');
        $('.carrier_id_' + type).addClass('d-none');
      }
    }
  </script>
@endsection
