@extends('frontend.layouts.app')

@section('content')
  <style>
    .ajax-loader {
      visibility: hidden;
      background-color: rgba(255,255,255,0.7);
      position: absolute;
      z-index: +100 !important;
      width: 100%;
      height:100%;
    }

    .ajax-loader img {
      position: relative;
      top:50%;
      left:50%;
    }
  </style>
  <div class="ajax-loader">
    <img src="{{ url('https://mazingbusiness.com/public/assets/img/ajax-loader.gif') }}" class="img-responsive" />
  </div>
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
            <!-- <div class="col done">
              <div class="text-center text-success">
                <i class="la-2x mb-2 las la-truck"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('3. Delivery info') }}</h3>
              </div>
            </div> -->
            <div class="col done">
              <div class="text-center text-success">
                <i class="la-2x mb-2 las la-credit-card"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('3. Payment') }}</h3>
              </div>
            </div>
            <div class="col active">
              <div class="text-center text-primary">
                <i class="la-2x mb-2 las la-check-circle"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('4. Confirmation') }}</h3>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <section class="py-4">
    <div class="container text-left">
      <div class="row">
        <div class="col-xl-8 mx-auto">
          <div class="text-center py-4 mb-4">
            <img src="{{$qrCodeUrl}}" alt="UPI QR Code" />
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
