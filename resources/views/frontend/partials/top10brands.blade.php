@if ($top10brands)
  <section class="mb-4 py-4 mz-highlight sliderbox">
    <div class="container-fluid">
      <div class="row gutters-10">
        <div class="col-lg-12">
          <div class="d-flex mb-3 align-items-baseline border-bottom border-dark">
            <h3 class="h5 fw-700 mb-0">
              <span
                class="border-bottom border-primary border-width-2 pb-3 d-inline-block">{{ translate('Search by Brands') }}</span>
            </h3>
            <a href="{{ route('brands.all') }}"
              class="ml-auto mr-0 btn btn-primary btn-sm shadow-md">{{ translate('View All Brands') }}</a>
          </div>
          <div class="row gutters-5">
            <div class="aiz-carousel gutters-10 half-outside-arrow" data-items="7" data-xl-items="7" data-lg-items="6"
              data-md-items="4" data-sm-items="3" data-xs-items="2" data-arrows="true" data-infinite="true">
              @foreach ($top10brands as $brand)
                <div class="carousel-box">
                  @include('frontend.partials.brandsliderbox', ['brand' => $brand])
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endif
