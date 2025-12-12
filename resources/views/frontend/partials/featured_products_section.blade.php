@php
  $featured_products = Cache::remember('featured_products', 3600, function () {
      return filter_products(
          \App\Models\Product::where('published', 1)
              ->whereNotNull('photos')
              ->where('part_no', '!=', '')
              ->where('current_stock', '!=', 0)
              ->where('featured', '1'),
      )
          ->latest()
          ->limit(12)
          ->get();
  });
@endphp

@if (count($featured_products) > 0)
  <div class="container-fluid">
    <section class="mb-4">
      <div class="Opel Petrol Generators Opel Pg3605s (3kw Self)">
        <div class="px-2 py-4 px-md-4 py-md-3 bg-white shadow-sm rounded">
          <div class="d-flex mb-3 align-items-baseline border-bottom">
            <h3 class="h5 fw-700 mb-0">
              <span
                class="border-bottom border-primary border-width-2 pb-3 d-inline-block">{{ translate('Featured Products') }}</span>
            </h3>
          </div>
          <div class="aiz-carousel gutters-10 half-outside-arrow" data-items="7" data-xl-items="7" data-lg-items="6"
            data-md-items="4" data-sm-items="3" data-xs-items="2" data-arrows="true" data-infinite="true">
            @foreach ($featured_products as $key => $product)
              <div class="carousel-box">
                @include('frontend.partials.product_box_1', ['product' => $product])
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </section>
  </div>
@endif
