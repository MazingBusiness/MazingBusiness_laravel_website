<div class="aiz-sidebar-wrap">
  <div class="aiz-sidebar left c-scrollbar">
    <div class="aiz-side-nav-logo-wrap">
      <div class="d-block text-center my-3">
        @if (get_setting('system_logo_white') != null)
          <img class="mw-100 mb-3" src="{{ uploaded_asset(get_setting('system_logo_white')) }}" class="brand-icon"
            alt="{{ get_setting('site_name') }}">
        @else
          <img class="mw-100 mb-3" src="{{ static_asset('assets/img/logo.png') }}" class="brand-icon"
            alt="{{ get_setting('site_name') }}">
        @endif
        <h3 class="fs-16  m-0 text-primary">{{ optional(Auth::user())->name }}</h3>
        <p class="text-primary">{{ Auth::user()->email }}</p>
      </div>
    </div>
    <div class="aiz-side-nav-wrap">
      <ul class="aiz-side-nav-list" id="main-menu" data-toggle="aiz-side-menu">
        <li class="aiz-side-nav-item">
          <a href="{{ route('seller.dashboard') }}" class="aiz-side-nav-link">
            <i class="las la-home aiz-side-nav-icon"></i>
            <span class="aiz-side-nav-text">{{ translate('Dashboard') }}</span>
          </a>
        </li>
        <li class="aiz-side-nav-item">
          <a href="#" class="aiz-side-nav-link">
            <i class="las la-shopping-cart aiz-side-nav-icon"></i>
            <span class="aiz-side-nav-text">{{ translate('Products') }}</span>
            <span class="aiz-side-nav-arrow"></span>
          </a>
          <!--Submenu-->
          <ul class="aiz-side-nav-list level-2">
            <li class="aiz-side-nav-item">
              <a href="{{ route('seller.products') }}"
                class="aiz-side-nav-link {{ areActiveRoutes(['seller.products', 'seller.products.create', 'seller.products.edit']) }}">
                <span class="aiz-side-nav-text">{{ translate('Products') }}</span>
              </a>
            </li>

            <li style="display: none;" class="aiz-side-nav-item">
              <a href="{{ route('seller.product_bulk_upload.index') }}"
                class="aiz-side-nav-link {{ areActiveRoutes(['product_bulk_upload.index']) }}">
                <span class="aiz-side-nav-text">{{ translate('Product Bulk Upload') }}</span>
              </a>
            </li>
          </ul>
        </li>
        <li class="aiz-side-nav-item">
          <a href="{{ route('seller.uploaded-files.index') }}"
            class="aiz-side-nav-link {{ areActiveRoutes(['seller.uploaded-files.index', 'seller.uploads.create']) }}">
            <i class="las la-folder-open aiz-side-nav-icon"></i>
            <span class="aiz-side-nav-text">{{ translate('Uploaded Files') }}</span>
          </a>
        </li>
        <li style="display: none;" class="aiz-side-nav-item">
          <a href="{{ route('seller.orders.index') }}"
            class="aiz-side-nav-link {{ areActiveRoutes(['seller.orders.index', 'seller.orders.show']) }}">
            <i class="las la-money-bill aiz-side-nav-icon"></i>
            <span class="aiz-side-nav-text">{{ translate('Orders') }}</span>
          </a>
        </li>

        <li style="display: none;" class="aiz-side-nav-item">
          <a href="{{ route('seller.payments.index') }}"
            class="aiz-side-nav-link {{ areActiveRoutes(['seller.payments.index']) }}">
            <i class="las la-history aiz-side-nav-icon"></i>
            <span class="aiz-side-nav-text">{{ translate('Credit Notes') }}</span>
          </a>
        </li>

        @php
          $support_ticket = DB::table('tickets')
              ->where('client_viewed', 0)
              ->where('user_id', Auth::user()->id)
              ->count();
        @endphp
        <li style="display: none;" class="aiz-side-nav-item">
          <a href="{{ route('seller.support_ticket.index') }}"
            class="aiz-side-nav-link {{ areActiveRoutes(['seller.support_ticket.index']) }}">
            <i class="las la-atom aiz-side-nav-icon"></i>
            <span class="aiz-side-nav-text">{{ translate('Support Ticket') }}</span>
            @if ($support_ticket > 0)
              <span class="badge badge-inline badge-success">{{ $support_ticket }}</span>
            @endif
          </a>
        </li>

      </ul><!-- .aiz-side-nav -->
    </div><!-- .aiz-side-nav-wrap -->
  </div><!-- .aiz-sidebar -->
  <div class="aiz-sidebar-overlay"></div>
</div><!-- .aiz-sidebar -->
