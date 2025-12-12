<style>

  .view-offer {

      transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;

  }



  .view-offer:hover {

      transform: scale(1.1);

      opacity: 0.8;

  }

</style>

@if(count($products) > 0)

  @foreach ($products->filter(function($product) { return $product->current_stock == 1; }) as $key => $product)

    <div class="aiz-card-box border border-light rounded hov-shadow-md mt-1 mb-2 has-transition bg-white">

      @if (discount_in_percentage($product) > 0)

        {{-- <span class="badge-custom">{{ translate('OFF') }}<span

            class="box ml-1 mr-0">&nbsp;{{ discount_in_percentage($product) }}%</span></span> --}}

      @endif

      <div class="row m-0">

        <div class="col-2 col-lg-1">

          @php

            $product_url = route('product', $product->slug);

          @endphp

          <a onclick="showAddToCartModal({{ $product->id }})" href="javascript:void(0)" class="d-block">

          @php

            // Fetch the base URL for uploads from the .env file

            $uploads_base_url = env('UPLOADS_BASE_URL', url('public'));



            // Fetch file_name for the product thumbnail image (assuming $product->thumbnail_img contains the ID of the upload)

            $product_thumbnail = \App\Models\Upload::where('id', $product->thumbnail_img)->value('file_name');

            $product_thumbnail_path = $product_thumbnail

                        ? $uploads_base_url . '/' . $product_thumbnail

                        : url('public/assets/img/placeholder.jpg');

          @endphp



            <!-- <img class="img-fit lazyload mx-auto" src="{{ static_asset('assets/img/placeholder.jpg') }}"

              data-src="{{ uploaded_asset($product->thumbnail_img) }}" alt="{{ $product->getTranslation('name') }}"

              onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->

              <img class="img-fit lazyload mx-auto"

                src="{{ url('public/assets/img/placeholder.jpg') }}"

                data-src="{{ $product_thumbnail_path }}"

                alt="{{ $product->getTranslation('name') }}"

                onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">



          </a>

        </div>

        @if (Auth::check())

        <div class="d-flex flex-column align-items-start col-3 col-md-3 col-lg-3">

            <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mt-4">

                <a href="javascript:void(0)" onclick="showAddToCartModal({{ $product->id }})" class="d-block text-reset">

                    {{ $product->getTranslation('name') }} 

                </a>

            </h3>
            <div class="row">
              <div class="col-md-3">
                  @php
                      $part_no = \DB::table('products')->where('id', $product->id)->value('part_no');
                      $is41 = $is41Manager ?? false;  // controller should pass $is41Manager
                    @endphp
                    @if($is41 ? \App\Models\Manager41ProductStock::where('part_no', $part_no)->where('closing_stock', '>', 0)->exists() : \DB::table('products_api')->where('part_no', $part_no)->where('closing_stock', '>', 0)->exists())
                      <img src="{{ asset('public/uploads/fast_dispatch.jpg') }}" alt="Fast Delivery" style="width: 75px; height: 21px; border-radius: 3px;">
                    @endif
              </div>
              <div class="col-md-9">
                @if($product->is_warranty == 1)
                  <img src="{{ asset('public/uploads/warranty.jpg') }}" alt="Fast Delivery" style="width: 75px; border-radius: 3px;"> <strong>{{ $product->warranty_duration }} Months </strong>
                @endif
              </div>
            </div>
        </div>



          <div class="d-flex align-items-center col-3 col-md-3 col-lg-3">

            <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0">

              <a href="javascript:void(0)" onclick="showAddToCartModal({{ $product->id }})"

                class="d-block text-reset">{{ $product->getTranslation('group_name') }} -> {{ $product->getTranslation('category_name') }}</a>

            </h3>

          </div>

          <div class="d-flex align-items-center col-2 col-md-2">

            <div class="fs-15">

              @if (convert_price($product->home_base_price) != convert_price($product->home_discounted_base_price))

                <del class="fw-600 opacity-50 mr-1">{{ single_price($product->home_base_price) }}</del>

              @endif

              <span class="fw-700 text-primary">{{ single_price($product->home_discounted_base_price) }}</span>

              {!! ($product->cash_and_carry_item == 1 && Auth::check() && Auth::user()->credit_days > 0) ? '<br/><span class="badge badge-inline badge-danger">No Credit Item</span>' : '' !!}

            </div>

          </div>

          <div class="col-2 col-md-2 col-lg-2 text-center d-flex align-items-center justify-content-center">

            <div class="w-100">

              @if(isset($product->offer) AND $product->offer != "")

              <img src="{{ asset('public/uploads/offers-icon.png') }}" alt="Special Offer" class="view-offer" style="width: 92px; height: auto;  border-radius: 3px; cursor: pointer; "data-toggle="modal" data-target="#offerModal" data-product-id="{{ $product->id }}">

              @endif

              <a href="javascript:void(0)" onclick="showAddToCartModal({{ $product->id }})"

                class="btn btn-primary btn-block py-1 my-1">

                <i class="las la-shopping-cart"></i><span class="d-none d-md-inline-block ml-2">{{ empty($order_id) && empty($sub_order_id) ? 'Add to Cart' : 'Add Product' }}</span>

              </a>

            </div>

          </div>

        @else

          <div class="d-flex align-items-center col-8 col-md-6 col-lg-7">

            <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0">

              <a href="javascript:void(0)" onclick="showQuickViewModal({{ $product->id }})"

                class="d-block text-reset">{{ $product->getTranslation('name') }}</a>

            </h3>

          </div>

          <div class="col-2 col-md-4 d-flex align-items-center justify-content-end">

            <a href="{{ route('user.registration') }}" class="btn btn-primary py-1 my-1">

              <i class="las la-sign-in-alt"></i><span class="d-none d-md-inline-block ml-2">Register to view

                prices</span>

            </a>

          </div>

        @endif

      </div>

    </div>

  @endforeach

@else

  <label style="margin-left: 4px; margin-top: 10px;">No Record Found</lable>

@endif

