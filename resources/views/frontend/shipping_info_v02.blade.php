@extends('frontend.layouts.app')

@section('content')
  <style>
    .ajax-loader {
      visibility: hidden;
      background-color: rgba(255,255,255,0.7);
      position: absolute;
      z-index: 999999 !important;
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
                <h3 class="fs-12 fw-600 d-none d-lg-block ">{{ translate('1. My Cart') }}</h3>
              </div>
            </div>
            <div class="col active">
              <div class="text-center text-primary">
                <i class="la-2x mb-2 las la-map"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block ">{{ translate('2. Shipping Company') }}</h3>
              </div>
            </div>
            <!-- <div class="col">
              <div class="text-center">
                <i class="la-2x mb-2 opacity-50 las la-truck"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block opacity-50 ">{{ translate('3. Delivery info') }}</h3>
              </div>
            </div> -->
            <div class="col">
              <div class="text-center">
                <i class="la-2x mb-2 opacity-50 las la-credit-card"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block opacity-50 ">{{ translate('3.. Payment') }}</h3>
              </div>
            </div>
            <div class="col">
              <div class="text-center">
                <i class="la-2x mb-2 opacity-50 las la-check-circle"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block opacity-50 ">{{ translate('4. Confirmation') }}</h3>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="mb-4 gry-bg">
    <div class="container">
      <div class="row cols-xs-space cols-sm-space cols-md-space">
        <div class="col-xxl-8 col-xl-10 mx-auto">
          <form class="form-default" data-toggle="validator" action="{{ route('checkout.store_shipping_info_v02') }}"
            role="form" method="POST">
            @csrf
            @if (Auth::check())
              <div class="shadow-sm bg-white p-4 rounded mb-4">
                <div class="row gutters-5">
                  @foreach (Auth::user()->addresses as $key => $address)
                    <div class="col-lg-6 mb-3">
                      <label class="aiz-megabox d-block bg-white mb-0">
                        <input type="radio" name="address_id" value="{{ $address->id }}"
                          @if ($address->set_default) checked @endif required>
                        <span class="d-flex p-3 aiz-megabox-elem">
                          <span class="aiz-rounded-check flex-shrink-0 mt-1"></span>
                          <span class="flex-grow-1 pl-3 text-left">
                            <div>
                              <span class="opacity-60">{{ translate('GST In') }}:</span>
                              <span class="fw-600 ml-2">{{ $address->gstin }}</span>
                            </div>
                            <div>
                              <span class="opacity-60">{{ translate('Company Name') }}:</span>
                              <span class="fw-600 ml-2">{{ $address->company_name }}</span>
                            </div>
                            <div>
                              <span class="opacity-60">{{ translate('Address') }}:</span>
                              <span class="fw-600 ml-2">{{ $address->address }}</span>
                            </div>
                            
                            <div>
                              <span class="opacity-60">{{ translate('Address 2') }}:</span>
                              <span class="fw-600 ml-2">{{ $address->address_2 }}</span>
                            </div>
                            <div>
                              <span class="opacity-60">{{ translate('Postal Code') }}:</span>
                              <span class="fw-600 ml-2">{{ $address->postal_code }}</span>
                            </div>
                            <div>
                              <span class="opacity-60">{{ translate('City') }}:</span>
                              <span class="fw-600 ml-2">{{ $address->city }}</span>
                            </div>

                            <div>
                              <span class="opacity-60">{{ translate('State') }}:</span>
                              <span class="fw-600 ml-2">{{ optional($address->state)->name }}</span>
                            </div>
                            <div>
                              <span class="opacity-60">{{ translate('Country') }}:</span>
                              <span class="fw-600 ml-2">{{ optional($address->country)->name }}</span>
                            </div>
                            <div>
                              <span class="opacity-60">{{ translate('Phone') }}:</span>
                              <span class="fw-600 ml-2">{{ $address->phone }}</span>
                            </div>
                          </span>
                        </span>
                      </label>
                      <div class="position-absolute right-0 top-0 px-3 py-2">
                        <a class="btn btn-primary btn-xs text-white" onclick="edit_address('{{ $address->id }}')">
                          {{ translate('Edit') }}
                        </a>
                      </div>
                    </div>
                  @endforeach
                  <input type="hidden" name="checkout_type" value="logged">
                  <div class="col-md-6 mx-auto mb-3">
                    <div
                      class="border p-3 rounded mb-3 c-pointer text-center bg-white h-100 d-flex flex-column justify-content-center"
                      onclick="add_new_address()">
                      <i class="las la-plus la-2x mb-3"></i>
                      <div class="alpha-7">{{ translate('Add New Address') }}</div>
                    </div>
                  </div>
                </div>
              </div>
            @endif
            <div class="row align-items-center">
              <div class="col-md-6 text-center text-md-left order-1 order-md-0">
                <a href="{{ route('home') }}" class="btn btn-link">
                  <i class="las la-arrow-left"></i>
                  {{ translate('Return to shop') }}
                </a>
              </div>
              <div class="col-md-6 text-center text-md-right">
                <button type="submit" class="btn btn-primary fw-600">{{ translate('Next') }}</a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
@endsection

@section('modal')
  @include('frontend.partials.address_modal')
@endsection
