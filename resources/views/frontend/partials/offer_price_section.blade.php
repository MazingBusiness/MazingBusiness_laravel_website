@php
// Get all distinct part_no from the products_api table
$part_numbers = \DB::table('products_api')
    ->where('closing_stock', '>', 0) // Add any necessary conditions
    ->distinct() // Ensure distinct part_no values
    ->pluck('part_no');

// Get products from the products table using the distinct part_no from products_api

    use Carbon\Carbon;
    $today = Carbon::today()->toDateString();
    $offer_price_products = \App\Models\OfferProduct::selectRaw('
        product_id,
        MAX(id) as id,
        MAX(offer_id) as offer_id,
        MAX(discount_type) as discount_type,
        MAX(offer_price) as offer_price,
        MAX(offer_discount_percent) as offer_discount_percent
    ')
    ->where('discount_type', 'percent')
    ->whereHas('productDetails', function ($query) {
        $query->where('current_stock', '>', 0);
    })
    ->whereHas('offer', function ($q) use ($today) {
        $q->whereDate('offer_validity_start', '<=', $today)
        ->whereDate('offer_validity_end', '>=', $today);
    })
    ->with([
        'offer',
        'productDetails.thumb_img:id,file_name',
        'reviews:id,product_id,rating,comment',
    ])
    ->groupBy('product_id')
    ->take(20)
    ->get();
@endphp

<section class="mb-4">
    <div class="container-fluid">
      <div class="px-2 py-4 px-md-4 py-md-3 bg-white shadow-sm rounded">
        <!-- Section header with OFFER PRICE ITEMS title and View All button -->
        <div class="d-flex mb-3 align-items-baseline justify-content-between border-bottom">
          <h3 class="h5 fw-700 mb-0">
            <span class="border-bottom border-primary border-width-2 pb-3 d-inline-block">
              {{ translate('OFFER PRICE ITEMS') }}
            </span>
          </h3>
          <!-- View All button aligned to the right -->
          <a href="{{ route('products.quickorder') }}" class="btn-sm btn btn-primary">
            {{ translate('View All') }}
          </a>
        </div>

        <!-- Carousel of offer price products -->
        <div class="aiz-carousel gutters-10 half-outside-arrow" data-items="7" data-xl-items="7" data-lg-items="6"
          data-md-items="4" data-sm-items="3" data-xs-items="2" data-arrows="true" data-infinite="true">
          @foreach ($offer_price_products as $offerProduct)
            {{-- Product model related to this offer --}}
            @php $product = $offerProduct->productDetails; @endphp
            <div class="carousel-box">
              @include('frontend.partials.product_box_1', ['product' => $product])
            </div>
          @endforeach
        </div>
      </div>
    </div>
</section>



