<div class="aiz-card-box border border-light rounded hov-shadow-md mt-1 mb-2 has-transition bg-white">
  @if (discount_in_percentage($product) > 0)
    <span class="badge-custom">{{ translate('OFF') }}<span
        class="box ml-1 mr-0">&nbsp;{{ discount_in_percentage($product) }}%</span></span>
  @endif
  <div class="row m-0">
    <div class="@if (Auth::check() && Auth::user()->warehouse_id) col-4 col-md-3 col-lg-2 @else  col-4 col-md-5 col-lg-4 @endif">
      @php
        $product_url = route('product', $product->slug);
      @endphp
      <a href="{{ $product_url }}" class="d-block">
         

        <!-- <img class="img-fit lazyload mx-auto" src="{{ static_asset('assets/img/placeholder.jpg') }}"
          data-src="{{ uploaded_asset($product->thumbnail_img) }}" alt="{{ $product->getTranslation('name') }}"
          onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
          @php
            // Fetch base URL for uploads from the .env file
            $baseUrl = env('UPLOADS_BASE_URL', url('public'));

            // Fetch file_name for the product thumbnail image (assuming $product->thumbnail_img contains the ID of the upload)
            $product_thumbnail = \App\Models\Upload::where('id', $product->thumbnail_img)->value('file_name');
            $product_thumbnail_path = $product_thumbnail
                        ? $baseUrl . '/' . $product_thumbnail
                        : url('public/assets/img/placeholder.jpg');
          @endphp

          <img class="img-fit lazyload mx-auto" 
            src="{{ url('public/assets/img/placeholder.jpg') }}"
            data-src="{{ $product_thumbnail_path }}" 
            alt="{{ $product->getTranslation('name') }}"
            onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">

      </a>
    </div>
    <div
      class="py-md-3 py-2 @if (Auth::check() && Auth::user()->warehouse_id) col-8 col-md-4 col-lg-5 @else  col-8 col-md-7 col-lg-8 @endif">
      <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0 h-35px">
        <a href="{{ $product_url }}" class="d-block text-reset">{{ $product->getTranslation('name') }}</a>
      </h3>
       @if(DB::table('products_api')->where('part_no', $product->part_no)->where('closing_stock', '>', 0)->exists())
                <img src="{{ asset('public/uploads/fast_dispatch.jpg') }}" alt="Fast Delivery" 
                     style="width: 75px; height: 21px;  border-radius: 3px;">
            @endif
      @if($product->is_warranty == 1)
        <img src="{{ asset('public/uploads/warranty.jpg') }}" alt="Fast Delivery" style="width: 75px; border-radius: 3px;"> <strong>{{ $product->warranty_duration }} Months </strong>
      @endif
      <div class="rating rating-sm mt-1">
        {{ renderStarRating($product->rating) }}
      </div>
      <div class="fs-15">
        @if (Auth::check())
          @if (Auth::user()->warehouse_id)
            @if (home_base_price($product) != home_discounted_base_price($product))
              <del class="fw-600 opacity-50 mr-1">{{ home_base_price($product) }}</del>
            @endif
            <span class="fw-700 text-primary">{{ home_discounted_base_price($product) }}</span>
            {!! ($product->cash_and_carry_item == 1 && Auth::check() && Auth::user()->credit_days > 0) ? '<br/><span class="badge badge-inline badge-danger">No Credit Item</span>' : '' !!}
          @else
            <small><a href="{{ route('user.registration') }}">Complete profile to check prices.</a></small>
          @endif
        @else
          <small><a href="{{ route('user.registration') }}" class="btn btn-sm btn-primary btn-block mt-2">Register to
              check prices</a></small>
        @endif
      </div>
      @if (addon_is_activated('club_point'))
        <div class="rounded px-2 mt-2 bg-soft-primary border-soft-primary border">
          {{ translate('Club Point') }}:
          <span class="fw-700 float-right">{{ $product->earn_point }}</span>
        </div>
      @endif
    </div>
    @if (Auth::check() && Auth::user()->warehouse_id)
      <div class="py-md-3 py-2 col-12 col-md-5 col-lg-5 text-center d-flex align-items-center justify-content-center">
        <div class="w-100">
          <a href="javascript:void(0)" onclick="showAddToCartModal({{ $product->id }})"
            class="btn btn-primary btn-block mb-3">
            <i class="las la-shopping-cart"></i> Add to Cart
          </a>
          <a href="javascript:void(0)" onclick="addToWishList({{ $product->id }})"
            class="d-md-block d-lg-inline mb-md-3 mr-md-0 mr-3 mr-lg-3">
            <i class="la la-heart-o"></i> Add to Wishlist
          </a>
          <a href="javascript:void(0)" onclick="addToCompare({{ $product->id }})">
            <i class="las la-sync"></i> Add to Compare
          </a>
        </div>
      </div>
    @endif
  </div>
</div>
