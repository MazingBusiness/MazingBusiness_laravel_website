@if (get_setting('topbar_banner') != null)
  <div class="position-relative top-banner removable-session z-1035 d-none" data-key="top-banner" data-value="removed">
    <a href="{{ get_setting('topbar_banner_link') }}" class="d-block text-reset">
      @php
        // Fetch the file_name for the topbar banner from the settings (assuming get_setting('topbar_banner') returns an ID)
        $topbar_banner_id = get_setting('topbar_banner');
        $topbar_banner_file = \App\Models\Upload::where('id', $topbar_banner_id)->value('file_name');
        $topbar_banner_path = $topbar_banner_file
                    ? url('public/' . $topbar_banner_file)
                    : url('public/assets/img/placeholder.jpg');
      @endphp

      <!-- <img src="{{ uploaded_asset(get_setting('topbar_banner')) }}" class="w-100 mw-100 h-50px h-lg-auto img-fit"> -->
      <img src="{{ $topbar_banner_path }}" class="w-100 mw-100 h-50px h-lg-auto img-fit">

    </a>
    <button class="btn text-white absolute-top-right set-session" data-key="top-banner" data-value="removed"
      data-toggle="remove-parent" data-parent=".top-banner">
      <i class="la la-close la-2x"></i>
    </button>
  </div>
@endif
<!-- Top Bar -->
<div class="top-navbar z-1045 h-35px h-sm-auto">
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-7 col">
        <ul class="list-inline d-flex justify-content-between justify-content-lg-start mb-0">
          @if (get_setting('show_language_switcher') == 'on')
            <li class="list-inline-item dropdown mr-3" id="lang-change">
              @php
                if (Session::has('locale')) {
                    $locale = Session::get('locale', Config::get('app.locale'));
                } else {
                    $locale = 'en';
                }
              @endphp
              <a href="javascript:void(0)" class="dropdown-toggle text-reset py-2" data-toggle="dropdown"
                data-display="static">
                <img src="{{ static_asset('assets/img/placeholder.jpg') }}"
                  data-src="{{ static_asset('assets/img/flags/' . $locale . '.png') }}" class="mr-2 lazyload"
                  alt="{{ \App\Models\Language::where('code', $locale)->first()->name }}" height="11">
                <span class="opacity-60">{{ \App\Models\Language::where('code', $locale)->first()->name }}</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-left">
                @foreach (\App\Models\Language::where('status', 1)->get() as $key => $language)
                  <li>
                    <a href="javascript:void(0)" data-flag="{{ $language->code }}"
                      class="dropdown-item @if ($locale == $language) active @endif">
                      <img src="{{ static_asset('assets/img/placeholder.jpg') }}"
                        data-src="{{ static_asset('assets/img/flags/' . $language->code . '.png') }}"
                        class="mr-1 lazyload" alt="{{ $language->name }}" height="11">
                      <span class="language">{{ $language->name }}</span>
                    </a>
                  </li>
                @endforeach
              </ul>
            </li>
          @endif

          @if (get_setting('show_currency_switcher') == 'on')
            <li class="list-inline-item dropdown ml-auto ml-lg-0 mr-0" id="currency-change">
              @php
                if (Session::has('currency_code')) {
                    $currency_code = Session::get('currency_code');
                } else {
                    $currency_code = \App\Models\Currency::findOrFail(get_setting('system_default_currency'))->code;
                }
              @endphp
              <a href="javascript:void(0)" class="dropdown-toggle text-reset py-2 opacity-60" data-toggle="dropdown"
                data-display="static">
                {{ \App\Models\Currency::where('code', $currency_code)->first()->name }}
                {{ \App\Models\Currency::where('code', $currency_code)->first()->symbol }}
              </a>
              <ul class="dropdown-menu dropdown-menu-right dropdown-menu-lg-left">
                @foreach (\App\Models\Currency::where('status', 1)->get() as $key => $currency)
                  <li>
                    <a class="dropdown-item @if ($currency_code == $currency->code) active @endif" href="javascript:void(0)"
                      data-currency="{{ $currency->code }}">{{ $currency->name }} ({{ $currency->symbol }})</a>
                  </li>
                @endforeach
              </ul>
            </li>
          @endif
        </ul>
      </div>
      @if (!request()->routeIs('warranty*'))
        <div class="col-5 text-right">
          {{-- d-none d-lg-block --}}
          <ul class="list-inline mb-0 h-100 d-flex justify-content-end align-items-center">
            @if(Route::currentRouteName() == 'products.quickorder' AND !empty($order_id))
              <li class="list-inline-item mr-3 border-right border-left-0 pr-3 pl-0">
                <a href="{{ route('order.splitOrder', ['order_id' => decrypt($order_id),'redirect'=>$redirect_url]) }}">Switch Back to Split Order</a>
              </li>
            @elseif(Route::currentRouteName() == 'products.quickorder' AND !empty($sub_order_id))
              <li class="list-inline-item mr-3 border-right border-left-0 pr-3 pl-0">
                <a href="{{ route('order.splitOrderDetails', ['order_id' => decrypt($sub_order_id)]) }}">Switch Back to Challan</a>
              </li>
            @else
              @if(session()->has('staff_id'))
                <li class="list-inline-item mr-3 border-right border-left-0 pr-3 pl-0">
                  <small>Logged in as {{ session()->get('cl_name') }} - </small><a href="{{ route('switch_back') }}">Switch Back</a>
                </li>
              @endif
              @if (get_setting('helpline_number'))
                <li class="list-inline-item mr-3 border-right border-left-0 pr-3 pl-0">
                  <a href="tel:{{ get_setting('helpline_number') }}" class="text-reset d-inline-block opacity-60 py-2">
                    <i class="la la-phone"></i>
                    <span>{{ translate('Help line') }}</span>
                    <span>{{ get_setting('helpline_number') }}</span>
                  </a>
                </li>
              @endif
              @auth
                @if (isAdmin())
                  <li class="list-inline-item mr-3 border-right border-left-0 pr-3 pl-0">
                    <a href="{{ route('admin.dashboard') }}"
                      class="text-reset d-inline-block opacity-60 py-2">{{ translate('My Panel') }}</a>
                  </li>
                  <li class="list-inline-item mr-3 border-left-0 pr-3 pl-0">
                    <a href="{{ route('logout') }}"
                      class="text-reset d-inline-block opacity-60 py-2">{{ translate('Logout') }}</a>
                  </li>
                @else
                  <li class="list-inline-item border-left-0 pl-0 dropdown">
                    <a class="dropdown-toggle no-arrow text-reset" data-toggle="dropdown" href="javascript:void(0);"
                      role="button" aria-haspopup="false" aria-expanded="false">
                      <span class="">
                        <span class="position-relative d-inline-block">
                          <i class="las la-bell fs-18"></i>
                          @if (count(Auth::user()->unreadNotifications) > 0)
                            <span
                              class="badge badge-sm badge-dot badge-circle badge-primary position-absolute absolute-top-right"></span>
                          @endif
                        </span>
                      </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right dropdown-menu-lg py-0">
                      <div class="p-3 bg-light border-bottom">
                        <h6 class="mb-0">{{ translate('Notifications') }}</h6>
                      </div>
                      <div class="px-3 c-scrollbar-light overflow-auto " style="max-height:300px;">
                        <ul class="list-group list-group-flush">
                          @forelse(Auth::user()->unreadNotifications as $notification)
                            <li class="list-group-item">
                              @if ($notification->type == 'App\Notifications\OrderNotification')
                                @if (Auth::user()->user_type == 'customer')
                                  <a href="{{ route('purchase_history.details', encrypt($notification->data['order_id'])) }}"
                                    class="text-reset">
                                    <span class="ml-2">
                                      {{ translate('Order code: ') }} {{ $notification->data['order_code'] }}
                                      {{ translate('has been ' . ucfirst(str_replace('_', ' ', $notification->data['status']))) }}
                                    </span>
                                  </a>
                                @elseif (Auth::user()->user_type == 'seller')
                                  <a href="{{ route('seller.orders.show', encrypt($notification->data['order_id'])) }}"
                                    class="text-reset">
                                    <span class="ml-2">
                                      {{ translate('Order code: ') }} {{ $notification->data['order_code'] }}
                                      {{ translate('has been ' . ucfirst(str_replace('_', ' ', $notification->data['status']))) }}
                                    </span>
                                  </a>
                                @endif
                              @endif
                            </li>
                          @empty
                            <li class="list-group-item">
                              <div class="py-4 text-center fs-16">
                                {{ translate('No notification found') }}
                              </div>
                            </li>
                          @endforelse
                        </ul>
                      </div>
                      <div class="text-center border-top">
                        <a href="{{ route('all-notifications') }}" class="text-reset d-block py-2">
                          {{ translate('View All Notifications') }}
                        </a>
                      </div>
                    </div>
                  </li>
                  @if (Auth::user()->user_type == 'seller')
                    <li class="list-inline-item mr-3 border-right border-left-0 pr-3 pl-0">
                      <a href="{{ route('seller.dashboard') }}"
                        class="text-reset d-inline-block opacity-60 py-2">{{ translate('My Panel') }}</a>
                    </li>
                  @endif
                @endif
              @else
                <li class="list-inline-item mr-3 border-right border-left-0 pr-3 pl-0">
                  <a href="{{ route('user.login') }}"
                    class="text-reset d-inline-block opacity-60 py-2">{{ translate('Login') }}</a>
                </li>
                <li class="list-inline-item">
                  <a href="{{ route('user.registration') }}"
                    class="text-reset d-inline-block opacity-60 py-2">{{ translate('Registration') }}</a>
                </li>
              @endauth
            @endif
          </ul>
        </div>
      @endif
    </div>
  </div>
</div>
<!-- END Top Bar -->
<header class="@if (get_setting('header_stikcy') == 'on') sticky-top @endif z-1020 bg-primary shadow-md">
  <div class="position-relative logo-bar-area z-1">
    <div class="container-fluid position-relative">
      <img src="{{ static_asset('assets/img/businessz.png') }}" alt="Business Z" id="businessz"
        class="position-absolute z-1025">
      <div class="d-flex align-items-center">
        <div class="pl-0 pr-3 d-flex align-items-center">
          <a class="d-block py-3 mr-5 ml-0 z-1035" href="{{ route('home') }}">
          @php
            // Fetch the file_name for the header logo from the settings (assuming get_setting('header_logo') returns an ID)
            $header_logo_id = get_setting('header_logo');
            $header_logo_file = \App\Models\Upload::where('id', $header_logo_id)->value('file_name');
            $header_logo_path = $header_logo_file
                        ? url('public/' . $header_logo_file)
                        : url('public/assets/img/logo.png');
          @endphp

            <!-- @php
              $header_logo = get_setting('header_logo');
            @endphp
            @if ($header_logo != null)
              <img class="logo" src="{{ uploaded_asset($header_logo) }}" alt="{{ env('APP_NAME') }}">
            @else
              <img class="logo" src="{{ static_asset('assets/img/logo.png') }}" alt="{{ env('APP_NAME') }}">
            @endif -->
            <img class="logo" src="{{ $header_logo_path }}" alt="{{ env('APP_NAME') }}">
          </a>
          @if (!request()->routeIs('warranty*'))
            @if (!in_array(Route::currentRouteName(), ['home', 'categories.all', 'login', 'user.login', 'user.registration','products.quickorder']))
              <div class="d-none d-xl-block align-self-stretch category-menu-icon-box ml-4 mr-0 z-1045">
                <div class="h-100 d-flex align-items-center" id="category-menu-icon">
                  <div
                    class="dropdown-toggle navbar-light bg-light h-40px w-50px justify-content-center rounded border c-pointer">
                    <span class="navbar-toggler-icon"></span>
                  </div>
                </div>
              </div>
            @endif
          @endif
        </div>
        @if (!request()->routeIs('warranty*'))
          <div class="d-lg-none ml-auto mr-0">
            <a class="p-2 d-block text-reset" href="javascript:void(0);" data-toggle="class-toggle"
              data-target=".front-header-search">
              <i class="las la-search la-flip-horizontal la-2x text-white"></i>
            </a>
          </div>
          <div class="flex-grow-1 front-header-search d-flex align-items-center bg-white z-1045">
            @if (!in_array(Route::currentRouteName(), ['products.quickorder']))
            <div class="position-relative flex-grow-1">
              <form action="{{ route('search') }}" method="GET" class="stop-propagation">
                <div class="d-flex position-relative align-items-center">
                  <div class="d-lg-none" data-toggle="class-toggle" data-target=".front-header-search">
                    <button class="btn px-2" type="button"><i class="la la-2x la-long-arrow-left"></i></button>
                  </div>
                  <div class="input-group bg-lg-primary">
                    <input type="text" class="border-0 border-lg form-control" id="search" name="keyword"
                      @isset($query)
                          value="{{ $query }}"
                      @endisset
                      placeholder="{{ translate('Search by Product / Category / Brand...') }}" autocomplete="off">
                    <div class="input-group-append d-none d-lg-block">
                      <button class="btn btn-primary mz-secondary" type="submit">
                        <i class="la la-search la-flip-horizontal fs-18"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </form>
              <div
                class="typed-search-box stop-propagation document-click-d-none d-none bg-white rounded shadow-lg position-absolute left-0 top-200 w-100"
                style="min-height: 50px">
                <div class="search-preloader absolute-top-center">
                  <div class="dot-loader" style="margin-top: 10px">
                    <div></div>
                    <div></div>
                    <div></div>
                  </div>
                </div>
                <div class="search-nothing d-none p-3 text-center fs-16">

                </div>
                <div id="search-content" class="text-left" style="max-height: 600px; overflow-y: auto;">
                </div>
              </div>
            </div>
            @endif
          </div>

          <div class="d-none d-lg-none ml-3 mr-0">
            <div class="nav-search-box">
              <a href="#" class="nav-box-link">
                <i class="la la-search la-flip-horizontal d-inline-block nav-box-icon"></i>
              </a>
            </div>
          </div>
          @if(Route::currentRouteName() == 'products.quickorder' AND (!empty($order_id) OR !empty($sub_order_id)))
          @else
            <div class="d-none d-lg-block ml-3 mr-0">
              <div class="" id="compare">
                @include('frontend.partials.compare')
              </div>
            </div>

            <div class="d-none d-lg-block ml-3 mr-0">
              <div class="" id="wishlist">
                @include('frontend.partials.wishlist')
              </div>
            </div>

            <div class="d-none d-lg-block  align-self-stretch ml-3 mr-0" data-hover="dropdown">
              <div class="nav-cart-box dropdown h-100" id="cart_items" >
                @include('frontend.partials.cart')
              </div>
            </div>
            
            <div class="d-none d-lg-block  align-self-stretch ml-3 mr-0" data-hover="dropdown">
              <div class="nav-cart-box dropdown h-100">
                <a href="{{ route('profile') }}" class="d-flex align-items-center text-reset h-100"
                  data-display="static">
                  <i class="la la-user la-2x text-white"></i>
                  <span class="flex-grow-1 ml-1">
                    <span class="nav-box-text d-none d-xl-block text-white">{{ translate('Profile') }}</span>
                  </span>
                </a>
              </div>
            </div>
          @endif
        @endif
      </div>
    </div>
    @if (Route::currentRouteName() != 'home')
      <div class="hover-category-menu position-absolute w-100 top-100 right-0 d-none z-3" style="left: 100px !important;" id="hover-category-menu">
        <div class="container-fluid">
          <div class="row gutters-10 position-relative">
            <div class="col-lg-3 position-static">
              @include('frontend.partials.category_menu')
            </div>
          </div>
        </div>
      </div>
    @endif
  </div>
  @if (get_setting('header_menu_labels') != null)
    <div class="bg-white border-top border-gray-200 py-1">
      <div class="container-fluid">
        <ul class="list-inline mb-0 pl-0 mobile-hor-swipe text-center">
          @foreach (json_decode(get_setting('header_menu_labels'), true) as $key => $value)
            <li class="list-inline-item mr-0">
              <a href="{{ json_decode(get_setting('header_menu_links'), true)[$key] }}"
                class="opacity-60 fs-14 px-3 py-2 d-inline-block fw-600 hov-opacity-100 text-reset">
                {{ translate($value) }}
              </a>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif
</header>

<div class="modal fade" id="order_details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
    <div class="modal-content">
      <div id="order-details-modal-body">

      </div>
    </div>
  </div>
</div>

@section('script')
  <script type="text/javascript">
    function show_order_details(order_id) {
      $('#order-details-modal-body').html(null);

      if (!$('#modal-size').hasClass('modal-lg')) {
        $('#modal-size').addClass('modal-lg');
      }

      $.post('{{ route('orders.details') }}', {
        _token: AIZ.data.csrf,
        order_id: order_id
      }, function(data) {
        $('#order-details-modal-body').html(data);
        $('#order_details').modal();
        $('.c-preloader').hide();
        AIZ.plugins.bootstrapSelect('refresh');
      });
    }



  </script>


@endsection
