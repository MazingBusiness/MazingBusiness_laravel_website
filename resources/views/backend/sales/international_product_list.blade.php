@if(count($products) > 0)
  @foreach ($products as $key => $product)
    <div class="aiz-card-box border border-light rounded hov-shadow-md mt-1 mb-2 has-transition bg-white">
      <? /*@if (discount_in_percentage($product) > 0)
        {{-- <span class="badge-custom">{{ translate('OFF') }}<span
            class="box ml-1 mr-0">&nbsp;{{ discount_in_percentage($product) }}%</span></span> --}}
      @endif*/ ?>
      <div class="row m-0">
        <div class="col-2 col-lg-1">
          <? /* @php
            $product_url = route('product', $product->slug);
          @endphp */ ?>
          <a onclick="addToOrder({{ $product->id }})" href="javascript:void(0)" class="d-block">
            <img class="img-fit lazyload mx-auto" src="{{ static_asset('assets/img/placeholder.jpg') }}"
              data-src="{{ uploaded_asset($product->thumbnail_img) }}" alt="{{ $product->getTranslation('name') }}"
              onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';">
          </a>
        </div>
        @if (Auth::check())
          <div class="d-flex align-items-center col-3 col-md-3 col-lg-3">
            <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0">
              <a href="javascript:void(0)" onclick="addToOrder({{ $product->id }})"
                class="d-block text-reset">{{ $product->getTranslation('name') }}</a>
            </h3>
          </div>
          <div class="d-flex align-items-center col-3 col-md-3 col-lg-3">
            <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0">
              <a href="javascript:void(0)" onclick="addToOrder({{ $product->id }})"
                class="d-block text-reset">{{ $product->getTranslation('group_name') }} -> {{ $product->getTranslation('category_name') }}</a>
            </h3>
          </div>
          <div class="d-flex align-items-center col-2 col-md-2">
            <div class="fs-15">
              <span class="fw-700 text-primary">{{ $product->convertPrice }}</span>
            </div>
          </div>
          <div class="col-2 col-md-2 col-lg-3 text-center d-flex align-items-center justify-content-center">
            <div class="w-100">
              <a href="javascript:void(0)" onclick="addToOrder({{ $product->id }})"
                class="btn btn-primary btn-block py-1 my-1">
                <i class="las la-shopping-cart"></i><span class="d-none d-md-inline-block ml-2">Add to Order</span>
              </a>
            </div>
          </div>
        @else
          <div class="d-flex align-items-center col-3 col-md-3 col-lg-4">
            <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0">
              <a href="javascript:void(0)" class="d-block text-reset">{{ $product->getTranslation('name') }}</a>
            </h3>
          </div>
          <div class="d-flex align-items-center col-3 col-md-3 col-lg-4">
            <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0">
              <a href="javascript:void(0)" class="d-block text-reset">{{ $product->getTranslation('group_name') }} -> {{ $product->getTranslation('category_name') }}</a>
            </h3>
          </div>
          <div class="col-2 col-md-2 col-lg-2 text-center d-flex align-items-center justify-content-center">
            <div class="w-100">
              <a href="{{ route('user.registration') }}" class="btn btn-primary py-1 my-1">
                <i class="las la-sign-in-alt"></i><span class="d-none d-md-inline-block ml-2">Register to view
                  prices</span>
              </a>
            </div>
          </div>
        @endif
      </div>
    </div>
  @endforeach
@else
  <label style="margin-left: 4px; margin-top: 10px;">No More Record.</lable>
@endif