@extends('frontend.layouts.app')

@section('content')

  {{-- Categories , Sliders . Today's deal --}}
  <div class="home-banner-area pt-3">
    <div class="container-fluid">
      <div class="row gutters-10 position-relative">
        <div class="col-lg-3 position-static d-none d-lg-block">
          @include('frontend.partials.category_menu')
        </div>

       @php
            $num_todays_deal = count($todays_deal_products);
            // Get slider images and slider links from settings
            $slider_images = json_decode(get_setting('home_slider_images'), true);
            $slider_links = json_decode(get_setting('home_slider_links'), true);

            // Get the file names from the uploads table where ID matches the image ID in the slider
            $upload_files = DB::table('uploads')
                ->whereIn('id', $slider_images) // Fetch all file names based on image IDs
                ->pluck('file_name', 'id'); // Use pluck to get the file names and map them by ID
      @endphp

      <div class="col-lg-7">
          @if ($slider_images != null)
              <div class="aiz-carousel dots-inside-bottom mobile-img-auto-height" data-infinite="true" data-arrows="true"
                  data-dots="true" data-autoplay="true">
                  @foreach ($slider_images as $key => $image_id)
                      @php
                          // Fetch the file name for each image or fallback to a placeholder
                          $file_path = isset($upload_files[$image_id]) 
                              ? env('UPLOADS_BASE_URL', url('public')) . '/' . $upload_files[$image_id]
                              : static_asset('assets/img/placeholder-rect.jpg');
                      @endphp

                      <div class="carousel-box">
                          <a href="{{ $slider_links[$key] }}">
                              <img class="d-block mw-100 img-fit rounded shadow-sm overflow-hidden"
                                  src="{{ $file_path }}" alt="{{ env('APP_NAME') }} promo" height="452"
                                  onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder-rect.jpg') }}';">
                          </a>
                      </div>
                  @endforeach
              </div>
          @endif
      </div>


        <div class="col-12 d-lg-none mt-2">
          <a href="{{ route('products.quickorder') }}">
            <!-- <img class="img-fit rounded"
              src="{{ env('GOOGLE_CLOUD_STORAGE_API_URI') . '/uploads/all/mazing-quick-order-hor.gif' }}"
              alt="{{ env('APP_NAME') }} promo"> -->
              
              <img class="img-fit rounded"
                src="{{ env('UPLOADS_BASE_URL', url('public')) . '/uploads/all/mazing-quick-order-hor.gif' }}"
                alt="{{ env('APP_NAME') }} promo">

          </a>
        </div>

        <div
          class="col-lg-2 rounded shadow-sm overflow-hidden d-none d-lg-flex justify-content-center align-items-center gifbg">
          <a href="{{ route('products.quickorder') }}">
            <!-- <img class="w-100 img-fit" src="{{ env('GOOGLE_CLOUD_STORAGE_API_URI') . '/uploads/all/quick-order.gif' }}"
              alt="{{ env('APP_NAME') }} promo"> -->
              <!-- <img class="w-100 img-fit" src="{{ url('public/uploads/all/quick-order.gif') }}"
              alt="{{ env('APP_NAME') }} promo"> -->
              <img class="w-100 img-fit" src="{{ env('UPLOADS_BASE_URL', url('public')) . '/uploads/all/quick-order.gif' }}" alt="{{ env('APP_NAME') }} promo">

          </a>
        </div>

        @if ($num_todays_deal > 0)
          <div class="col-lg-2 order-3 mt-3 mt-lg-0">
            <div class="bg-white rounded shadow-sm">
              <div class="bg-soft-primary rounded-top p-3 d-flex align-items-center justify-content-center">
                <span class="fw-600 fs-16 mr-2 text-truncate">
                  {{ translate('Todays Deal') }}
                </span>
                <span class="badge badge-primary badge-inline">{{ translate('Hot') }}</span>
              </div>
              <div class="c-scrollbar-light overflow-auto h-lg-400px p-2 bg-primary rounded-bottom">
                <div class="gutters-5 lg-no-gutters row row-cols-2 row-cols-lg-1">
                  @foreach ($todays_deal_products as $key => $product)
                    @if ($product != null)
                      <div class="col mb-2">
                        <a href="{{ route('product', $product->slug) }}"
                          class="d-block p-2 text-reset bg-white h-100 rounded">
                          <div class="row gutters-5 align-items-center">
                            <div class="col-xxl">
                              <div class="img">
                              @php
                                // Fetch file_name for the thumbnail image (assuming $product->thumbnail_img contains the ID of the upload)
                                $thumbnail_image = \App\Models\Upload::where('id', $product->thumbnail_img)->value('file_name');
                                $thumbnail_image_path = $thumbnail_image
                                            ? url('public/' . $thumbnail_image)
                                            : url('public/assets/img/placeholder.jpg');
                              @endphp

                                <!-- <img class="lazyload img-fit h-140px h-lg-80px"
                                  src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                  data-src="{{ uploaded_asset($product->thumbnail_img) }}"
                                  alt="{{ $product->getTranslation('name') }}"
                                  onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                                  <img class="lazyload img-fit h-140px h-lg-80px"
                                    src="{{ url('public/assets/img/placeholder.jpg') }}"
                                    data-src="{{ $thumbnail_image_path }}" 
                                    alt="{{ $product->getTranslation('name') }}"
                                    onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">

                              </div>
                            </div>
                            <div class="col-xxl">
                              <div class="fs-16">
                                @if (Auth::check())
                                  @if (Auth::user()->warehouse_id)
                                    @if (home_base_price($related_product) != home_discounted_base_price($related_product))
                                      <del class="fw-600 opacity-50 mr-1">{{ home_base_price($related_product) }}</del>
                                    @endif
                                    <span
                                      class="fw-700 text-primary">{{ home_discounted_base_price($related_product) }}</span>
                                  @else
                                    <small><a href="{{ route('user.registration') }}">Complete profile to check
                                        prices.</a></small>
                                  @endif
                                @else
                                  <small><a href="{{ route('user.registration') }}"
                                      class="btn btn-sm btn-primary btn-block">Register to check prices</a></small>
                                @endif
                              </div>
                            </div>
                          </div>
                        </a>
                      </div>
                    @endif
                  @endforeach
                </div>
              </div>
            </div>
          </div>
        @endif

      </div>
    </div>
  </div>

  <div class="home-banner-area mb-4">
    <div class="container-fluid">
      <div class="row gutters-10 position-relative">
        <div class="col-lg-12">
          @if (count($featured_categories) > 0)
            <ul class="list-unstyled mb-0 row gutters-5">
              @foreach ($featured_categories as $key => $category)
                @php
                  // Fetch file_name for the banner (assuming $category->banner contains the ID of the upload)
                  $category_banner = \App\Models\Upload::where('id', $category->banner)->value('file_name');
                  $category_banner_path = $category_banner
                              ? url('public/' . $category_banner)
                              : url('public/assets/img/placeholder.jpg');
                @endphp

                <li class="minw-0 col-4 col-sm-3 col-lg-2 col-xl-1 mt-3">
                  <a href="{{ route('products.category', $category->slug) }}"
                    class="d-block rounded bg-white p-2 text-reset shadow-sm">
                    <!-- <img src="{{ static_asset('assets/img/placeholder.jpg') }}"
                      data-src="{{ uploaded_asset($category->banner) }}" alt="{{ $category->getTranslation('name') }}"
                      class="lazyload img-fit"
                      onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder-rect.jpg') }}';"> -->
                      <img src="{{ url('public/assets/img/placeholder.jpg') }}"
                        data-src="{{ $category_banner_path }}" 
                        alt="{{ $category->getTranslation('name') }}"
                        class="lazyload img-fit"
                        onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder-rect.jpg') }}';">

                    <div class="text-truncate fs-12 fw-600 mt-2 opacity-70 text-center">
                      {{ $category->getTranslation('name') }}</div>
                  </a>
                </li>
              @endforeach

            </ul>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Banner section 1 --}}
  @if (get_setting('home_banner1_images') != null)
    <div class="mb-4">
      <div class="container-fluid">
        <div class="row gutters-10">
          @php $banner_1_imags = json_decode(get_setting('home_banner1_images')); 
          
          @endphp
          @foreach ($banner_1_imags as $key => $value)
              @php
                // Fetch file_name for the banner image (assuming $banner_1_imags[$key] contains the ID of the upload)
                $banner_image = \App\Models\Upload::where('id', $banner_1_imags[$key])->value('file_name');

                // Directly use the base URL from the .env file in the image path
                $banner_image_path = $banner_image
                            ? env('UPLOADS_BASE_URL', url('public')) . '/' . $banner_image
                            : url('public/assets/img/placeholder-rect.jpg');
              @endphp
            <div class="col-xl col-6">
              <div class="mb-3 mb-lg-0">
                <a href="{{ json_decode(get_setting('home_banner1_links'), true)[$key] }}" class="d-block text-reset">
                  <!-- <img src="{{ static_asset('assets/img/placeholder-rect.jpg') }}"
                    data-src="{{ uploaded_asset($banner_1_imags[$key]) }}" alt="{{ env('APP_NAME') }} promo"
                    class="img-fluid lazyload w-100"> -->
                    <img src="{{ url('public/assets/img/placeholder-rect.jpg') }}"
                      data-src="{{ $banner_image_path }}" 
                      alt="{{ env('APP_NAME') }} promo"
                      class="img-fluid lazyload w-100">

                </a>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  <!-- {{-- Banner section 1 --}}
@if (get_setting('home_banner1_images') != null)
    <div class="mb-4">
        <div class="container-fluid">
            <div class="row gutters-10">
                @php 
                    // Decode banner images and links
                    $banner_1_images = json_decode(get_setting('home_banner1_images'), true);
                    $banner_1_links = json_decode(get_setting('home_banner1_links'), true);

                    // Fetch file names from uploads table based on the image IDs
                    $upload_files = DB::table('uploads')
                        ->whereIn('id', $banner_1_images)
                        ->pluck('file_name', 'id'); // Map file names with their corresponding IDs
                @endphp
                @foreach ($banner_1_images as $key => $image_id)
                    @php
                        // Fetch the file name for each image ID, or use a placeholder if not found
                        $file_ban_path = isset($upload_files[$image_id]) 
                            ? asset('public/' . $upload_files[$image_id]) 
                            : static_asset('assets/img/placeholder-rect.jpg');
                    @endphp
                    <div class="col-xl col-6">
                        <div class="mb-3 mb-lg-0">
                            <a href="{{ $banner_1_links[$key] }}" class="d-block text-reset">
                                <img src="{{ static_asset('assets/img/placeholder-rect.jpg') }}"
                                    data-src="{{ $file_ban_path }}" alt="{{ env('APP_NAME') }} promo"
                                    class="img-fluid lazyload w-100">
                                    
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif -->


  {{-- Flash Deal --}}
  @php
    $flash_deal = \App\Models\FlashDeal::where('status', 1)
        ->where('featured', 1)
        ->first();
  @endphp
  @if (
      $flash_deal != null &&
          strtotime(date('Y-m-d H:i:s')) >= $flash_deal->start_date &&
          strtotime(date('Y-m-d H:i:s')) <= $flash_deal->end_date)
          <section class="mb-4">
            <div class="container-fluid">
              <div class="px-2 py-4 px-md-4 py-md-3 bg-white shadow-sm rounded">

                <div class="d-flex flex-wrap mb-3 align-items-baseline border-bottom">
                  <h3 class="h5 fw-700 mb-0">
                    <span
                      class="border-bottom border-primary border-width-2 pb-3 d-inline-block">{{ translate('Flash Sale') }}</span>
                  </h3>
                  <div class="aiz-count-down ml-auto ml-lg-3 align-items-center"
                    data-date="{{ date('Y/m/d H:i:s', $flash_deal->end_date) }}"></div>
                  <a href="{{ route('flash-deal-details', $flash_deal->slug) }}"
                    class="ml-auto mr-0 btn btn-primary btn-sm shadow-md w-100 w-md-auto">{{ translate('View More') }}</a>
                </div>

                <div class="aiz-carousel gutters-10 half-outside-arrow" data-items="6" data-xl-items="5" data-lg-items="4"
                  data-md-items="3" data-sm-items="2" data-xs-items="2" data-arrows='true'>
                  @foreach ($flash_deal->flash_deal_products->take(20) as $key => $flash_deal_product)
                    @php
                      $product = \App\Models\Product::find($flash_deal_product->product_id);
                    @endphp
                    @if ($product != null && $product->published != 0)
                      <div class="carousel-box">
                        @include('frontend.partials.product_box_1', ['product' => $product])
                      </div>
                    @endif
                  @endforeach
                </div>
              </div>
            </div>
          </section>
  @endif

  {{-- Offer Price --}}
  <div id="section_offer_price">
  <!-- Dynamic content will be loaded here -->
  </div>
  {{-- Recently Viewed --}}
  <div id="section_recently_viewed">

  </div>
 


  {{-- Best Selling --}}
  <div id="section_best_selling">

  </div>

  <div id="section_brands">

  </div>

  {{-- Banner Section 2 --}}
  @if (get_setting('home_banner2_images') != null)
    <div class="mb-4">
      <div class="container-fluid">
        <div class="row gutters-10">
          @php $banner_2_imags = json_decode(get_setting('home_banner2_images')); @endphp
          @foreach ($banner_2_imags as $key => $value)

            @php
              // Fetch base URL for uploads from the .env file
              $baseUrl = env('UPLOADS_BASE_URL', url('public'));

              // Fetch file_name for the banner image (assuming $banner_2_imags[$key] contains the ID of the upload)
              $banner_image = \App\Models\Upload::where('id', $banner_2_imags[$key])->value('file_name');
              $banner_image_path = $banner_image
                          ? $baseUrl . '/' . $banner_image
                          : url('public/assets/img/placeholder-rect.jpg');
            @endphp


            <div class="col-xl col-6">
              <div class="mb-3 mb-lg-0">
                <a href="{{ json_decode(get_setting('home_banner2_links'), true)[$key] }}" class="d-block text-reset">
                  <!-- <img src="{{ static_asset('assets/img/placeholder-rect.jpg') }}"
                    data-src="{{ uploaded_asset($banner_2_imags[$key]) }}" alt="{{ env('APP_NAME') }} promo"
                    class="img-fluid lazyload w-100"> -->
                    <img src="{{ url('public/assets/img/placeholder-rect.jpg') }}"
                      data-src="{{ $banner_image_path }}" 
                      alt="{{ env('APP_NAME') }} promo"
                      class="img-fluid lazyload w-100">

                </a>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- Category wise Products --}}
  <div id="section_home_categories" class="d-none">

  </div>

  {{-- Featured Section --}}
  <div id="section_featured">

  </div>

  {{-- Classified Product --}}
  @if (get_setting('classified_product') == 1)
    @php
      $classified_products = \App\Models\CustomerProduct::where('status', '1')
          ->where('published', '1')
          ->take(10)
          ->get();
    @endphp
    @if (count($classified_products) > 0)
      <section  class="mb-4">
        <div class="container-fluid">
          <div class="px-2 py-4 px-md-4 py-md-3 bg-white shadow-sm rounded">
            <div class="d-flex mb-3 align-items-baseline border-bottom">
              <h3 class="h5 fw-700 mb-0">
                <span
                  class="border-bottom border-primary border-width-2 pb-3 d-inline-block">{{ translate('Classified Ads') }}</span>
              </h3>
              <a href="{{ route('customer.products') }}"
                class="ml-auto mr-0 btn btn-primary btn-sm shadow-md">{{ translate('View More') }}</a>
            </div>
            <div class="aiz-carousel gutters-10 half-outside-arrow" data-items="6" data-xl-items="5" data-lg-items="4"
              data-md-items="3" data-sm-items="2" data-xs-items="2" data-arrows='true'>
              @foreach ($classified_products as $key => $classified_product)

                @php
                  // Fetch file_name for the thumbnail image (assuming $classified_product->thumbnail_img contains the ID of the upload)
                  $classified_thumbnail_image = \App\Models\Upload::where('id', $classified_product->thumbnail_img)->value('file_name');
                  $classified_thumbnail_image_path = $classified_thumbnail_image
                              ? url('public/' . $classified_thumbnail_image)
                              : url('public/assets/img/placeholder.jpg');
                @endphp

                <div class="carousel-box">
                  <div class="aiz-card-box border border-light rounded hov-shadow-md my-2 has-transition">
                    <div class="position-relative">
                      <a href="{{ route('customer.product', $classified_product->slug) }}" class="d-block">
                        <!-- <img class="img-fit lazyload mx-auto h-140px h-md-210px"
                          src="{{ static_asset('assets/img/placeholder.jpg') }}"
                          data-src="{{ uploaded_asset($classified_product->thumbnail_img) }}"
                          alt="{{ $classified_product->getTranslation('name') }}"
                          onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                          <img class="img-fit lazyload mx-auto h-140px h-md-210px"
                            src="{{ url('public/assets/img/placeholder.jpg') }}"
                            data-src="{{ $classified_thumbnail_image_path }}" 
                            alt="{{ $classified_product->getTranslation('name') }}"
                            onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">

                      </a>
                      <div class="absolute-top-left pt-2 pl-2">
                        @if ($classified_product->conditon == 'new')
                          <span class="badge badge-inline badge-success">{{ translate('new') }}</span>
                        @elseif($classified_product->conditon == 'used')
                          <span class="badge badge-inline badge-danger">{{ translate('Used') }}</span>
                        @endif
                      </div>
                    </div>
                    <div class="p-md-3 p-2 text-left">
                      <div class="fs-15 mb-1">
                        <span class="fw-700 text-primary">{{ single_price($classified_product->unit_price) }}</span>
                      </div>
                      <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0 h-35px">
                        <a href="{{ route('customer.product', $classified_product->slug) }}"
                          class="d-block text-reset">{{ $classified_product->getTranslation('name') }}</a>
                      </h3>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </section>
    @endif
  @endif

  <div id="section_profession">

  </div>

  <div id="section_newest">
    @if (count($newest_products) > 0)
      <section class="mb-4">
        <div class="container-fluid">
          <div class="px-2 py-4 px-md-4 py-md-3 bg-white shadow-sm rounded">
            <div class="d-flex mb-3 align-items-baseline border-bottom">
              <h3 class="h5 fw-700 mb-0">
                <span class="border-bottom border-primary border-width-2 pb-3 d-inline-block">
                  {{ translate('New Products') }}
                </span>
              </h3>
            </div>
            <div class="aiz-carousel gutters-10 half-outside-arrow" data-items="7" data-xl-items="7" data-lg-items="6"
              data-md-items="4" data-sm-items="3" data-xs-items="2" data-arrows="true" data-infinite="true">
              @foreach ($newest_products as $key => $new_product)
                <div class="carousel-box">
                  @include('frontend.partials.product_box_1', ['product' => $new_product])
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </section>
    @endif
  </div>

  {{-- Banner Section 3 --}}
  @if (get_setting('home_banner3_images') != null)
    <div class="mb-4">
      <div class="container-fluid">
        <div class="row gutters-10">
          @php $banner_3_imags = json_decode(get_setting('home_banner3_images')); @endphp
          @foreach ($banner_3_imags as $key => $value)
            @php
              // Fetch base URL for uploads from the .env file
              $baseUrl = env('UPLOADS_BASE_URL', url('public'));

              // Fetch file_name for the banner image (assuming $banner_3_imags[$key] contains the ID of the upload)
              $banner_image = \App\Models\Upload::where('id', $banner_3_imags[$key])->value('file_name');
              $banner_image_path = $banner_image
                          ? $baseUrl . '/' . $banner_image
                          : url('public/assets/img/placeholder-rect.jpg');
            @endphp


            <div class="col-xl col-6">
              <div class="mb-3 mb-lg-0">
                <a href="{{ json_decode(get_setting('home_banner3_links'), true)[$key] }}" class="d-block text-reset">
                  <!-- <img src="{{ static_asset('assets/img/placeholder-rect.jpg') }}"
                    data-src="{{ uploaded_asset($banner_3_imags[$key]) }}" alt="{{ env('APP_NAME') }} promo"
                    class="img-fluid lazyload w-100"> -->
                    <img src="{{ url('public/assets/img/placeholder-rect.jpg') }}"
                      data-src="{{ $banner_image_path }}" 
                      alt="{{ env('APP_NAME') }} promo"
                      class="img-fluid lazyload w-100">

                </a>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- OTP Modal --}}
  @if (Session::has('modal_otp'))
    @include('modals.otp_verification_modal')
  @endif
@endsection

@section('script')
  <script>
    $(document).ready(function() {
      // Popup modal
      var loggedIn = {{ auth()->check() ? 'true' : 'false' }};
      if (loggedIn) {
        $('#otp_modal').modal('show');
      } else {
        $('#login').modal('show');
      }
      $.post('{{ route('home.section.featured') }}', {
        _token: '{{ csrf_token() }}'
      }, function(data) {
        $('#section_featured').html(data);
        AIZ.plugins.slickCarousel();
      });
      $.post('{{ route('home.section.recently_viewed') }}', {
        _token: '{{ csrf_token() }}'
      }, function(data) {
        $('#section_recently_viewed').html(data);
        AIZ.plugins.slickCarousel();
      });

      $.post('{{ route('home.section.best_selling') }}', {
        _token: '{{ csrf_token() }}'
      }, function(data) {
        $('#section_best_selling').html(data);
        AIZ.plugins.slickCarousel();
      });

      // New AJAX request to load the Offer Price section dynamically
    $.post('{{ route('home.section.offer_price') }}', {
      _token: '{{ csrf_token() }}'
    }, function(data) {
      $('#section_offer_price').html(data);
      AIZ.plugins.slickCarousel();
    });
    // end code for section_offer_price

      $.post('{{ route('home.section.top10brands') }}', {
        _token: '{{ csrf_token() }}'
      }, function(data) {
        $('#section_brands').html(data);
        AIZ.plugins.slickCarousel();
      });
      $.post('{{ route('home.section.search_by_profession') }}', {
        _token: '{{ csrf_token() }}'
      }, function(data) {
        $('#section_profession').html(data);
        AIZ.plugins.slickCarousel();
      });
      $.post('{{ route('home.section.home_categories') }}', {
        _token: '{{ csrf_token() }}'
      }, function(data) {
        $('#section_home_categories').html(data);
        $('#section_home_categories section').each(function(index, elem) {

          if (index == 0) {
            $('#section_offer_price').after(elem);
          }
          if (index == 1) {
            $('#section_recently_viewed').after(elem);
          }
          if (index == 2) {
            $('#section_best_selling').after(elem);
          }
          if (index == 3) {
            $('#section_brands').after(elem);
          }
          if (index == 4) {
            $('#section_featured').after(elem);
          }
          if (index == 5) {
            $('#section_profession').after(elem);
          }
          if (index == 6) {
            $('#section_newest').after(elem);
          }
        });
        $('#section_home_categories').remove();
        AIZ.plugins.slickCarousel();
      });
      // To prevent multiple request
      $('form').submit(function() {
        $(this).find(':input[type=submit]').prop('disabled', true);
      })
    });
  </script>
@endsection
