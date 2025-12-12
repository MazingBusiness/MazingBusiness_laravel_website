@if (get_setting('home_categories') != null)
  @php $home_categories = json_decode(get_setting('home_categories')); @endphp
  @foreach ($home_categories as $key => $value)
    @php
      $category = \App\Models\CategoryGroup::with([
          'childrenCategories' => function ($query) {
              $query
                  ->whereHas('products')
                  ->orderBy('order_level', 'asc')
                  ->limit(10);
          },
      ])->find($value);
    @endphp
    <section class="mb-4">
      <div class="container-fluid">
        <div class="px-2 py-4 px-md-4 py-md-3 bg-white shadow-sm rounded">
          <div class="d-flex mb-3 align-items-baseline border-bottom">
            <h3 class="h5 fw-700 mb-0">
              <span class="border-bottom border-primary border-width-2 pb-3 d-inline-block">{{ $category->name }}</span>
            </h3>
          </div>
          <div class="aiz-carousel gutters-10 half-outside-arrow" data-items="7" data-xl-items="7" data-lg-items="6"
            data-md-items="4" data-sm-items="3" data-xs-items="2" data-arrows="true" data-infinite="true">
            @if (count($category->childrenCategories) < 6)
              @php
                $products = collect([]);
                foreach ($category->childrenCategories as $cat) {
                    $products = $products->merge(get_cached_products($cat->id));
                }
              @endphp
              @foreach ($products as $product)
                <div class="carousel-box">
                  @include('frontend.partials.product_box_1', ['product' => $product])
                </div>
              @endforeach
            @else
              @foreach ($category->childrenCategories as $key => $ca)

                @php
                  // Fetch base URL for uploads from the .env file
                  $baseUrl = env('UPLOADS_BASE_URL', url('public'));

                  // Fetch file_name for the banner (assuming $ca->banner contains the ID of the upload)
                  $banner_image = \App\Models\Upload::where('id', $ca->banner)->value('file_name');
                  $banner_image_path = $banner_image
                              ? $baseUrl . '/' . $banner_image
                              : url('public/assets/img/placeholder.jpg');
                @endphp


                <div class="carousel-box">
                  <div class="aiz-card-box border border-light hov-shadow-md mt-1 mb-2 has-transition bg-white">
                    <div class="position-relative">
                      <a href="{{ route('products.category', $ca->slug) }}" class="d-block">
                        <!-- <img class="lazyload mx-auto h-140px h-md-200px mt-2"
                          src="{{ static_asset('assets/img/placeholder.jpg') }}"
                          data-src="{{ uploaded_asset($ca->banner) }}" alt="{{ $ca->name }}"
                          onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                          <img class="lazyload mx-auto h-140px h-md-200px mt-2"
                            src="{{ url('public/assets/img/placeholder.jpg') }}"
                            data-src="{{ $banner_image_path }}" 
                            alt="{{ $ca->name }}"
                            onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">

                        <div class="text-truncate fs-12 fw-600 p-2 opacity-70 text-dark text-center">
                          {{ $ca->name }}
                        </div>
                      </a>
                    </div>
                  </div>
                </div>
              @endforeach
            @endif
          </div>
        </div>
      </div>
    </section>
  @endforeach
@endif
