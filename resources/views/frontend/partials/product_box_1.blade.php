<div class="aiz-card-box border border-light rounded hov-shadow-md mt-1 mb-2 has-transition bg-white">
  {{-- @if (discount_in_percentage($product) > 0)
    <span class="badge-custom">{{ translate('OFF') }}<span
        class="box ml-1 mr-0">&nbsp;{{ discount_in_percentage($product) }}%</span></span>
  @endif --}}
 
  {!! ($product->cash_and_carry_item == 1 && Auth::check() && Auth::user()->credit_days > 0) ? '<span class="badge-custom">No Credit Item<span class="box ml-1 mr-0">&nbsp;</span></span>' : '' !!}
  <div class="position-relative">
      @php
        // Generate product URL
        $product_url = route('product', $product->slug);

        // Fetch the base URL for uploads from the .env file
        $baseUrl = env('UPLOADS_BASE_URL', url('public'));

        // Fetch file_name for thumbnail image
        $thumbnail_image = \App\Models\Upload::where('id', $product->thumbnail_img)->value('file_name');
        $thumbnail_image_path = $thumbnail_image
                    ? $baseUrl . '/' . $thumbnail_image
                    : url('public/assets/img/placeholder.jpg');

        // Fetch file_name for photos (since it's a single ID now)
        $photo_image = \App\Models\Upload::where('id', $product->photos)->value('file_name');
        $photo_image_path = $photo_image
                    ? $baseUrl . '/' . $photo_image
                    : url('public/assets/img/placeholder.jpg');
    @endphp

    <a href="{{ $product_url }}" class="d-block">
      <!-- <img class="img-fit lazyload mx-auto" src="{{ static_asset('assets/img/placeholder.jpg') }}"
        data-src="{{ uploaded_asset($product->thumbnail_img) }}" alt="{{ $product->getTranslation('name') }}"
        onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
        <img class="img-fit lazyload mx-auto" src="{{ static_asset('assets/img/placeholder.jpg') }}"
        data-src="{{ $thumbnail_image_path }}" alt="{{ $product->getTranslation('name') }}"
        onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';">
    </a>
    @if ($product->wholesale_product)
      <span class="absolute-bottom-left fs-11 text-white fw-600 px-2 lh-1-8" style="background-color: #455a64">
        {{ translate('Wholesale') }}
      </span>
    @endif
    <div class="absolute-top-right aiz-p-hov-icon">
      <a href="javascript:void(0)" onclick="addToWishList({{ $product->id }})" data-toggle="tooltip"
        data-title="{{ translate('Add to wishlist') }}" data-placement="left">
        <i class="la la-heart-o"></i>
      </a>
      <a href="javascript:void(0)" onclick="addToCompare({{ $product->id }})" data-toggle="tooltip"
        data-title="{{ translate('Add to compare') }}" data-placement="left">
        <i class="las la-sync"></i>
      </a>
      <a href="javascript:void(0)" onclick="showAddToCartModal({{ $product->id }})" data-toggle="tooltip"
        data-title="{{ translate('Add to cart') }}" data-placement="left">
        <i class="las la-shopping-cart"></i>
      </a>
    </div>
  </div>
  <div class="p-md-3 p-2 text-left">
    <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0 h-35px">
      <a href="{{ $product_url }}" class="d-block text-reset">{{ Str::upper($product->getTranslation('name')) }}</a>
    </h3>
    @if(DB::table('products_api')->where('part_no', $product->part_no)->where('closing_stock', '>', 0)->exists())
        <img src="{{ asset('public/uploads/fast_dispatch.jpg') }}" alt="Fast Delivery" style="width: 75px; height: 21px;  border-radius: 3px;">
    @endif
    @if ($product->is_warranty == 1)
        <img src="{{ asset('public/uploads/warranty.jpg') }}" alt="Fast Delivery" style="width: 75px; border-radius: 3px;"> 
        <!-- <strong>{{ $product->warranty_duration }} Months </strong> -->
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
          <span class="fw-700 text-primary">{{ home_discounted_base_price($product) }}</span>@if (discount_in_percentage($product) > 0) ({{ translate('OFF') }} {{ discount_in_percentage($product) }}%) @endif
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
</div>