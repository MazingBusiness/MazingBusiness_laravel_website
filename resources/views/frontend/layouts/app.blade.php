<!DOCTYPE html>
@if (\App\Models\Language::where('code', Session::get('locale', Config::get('app.locale')))->first()->rtl == 1)
  <html dir="rtl" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@else
  <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@endif

<head>

  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="app-url" content="{{ getBaseURL() }}">
  <!-- <meta name="file-base-url" content="{{ getFileBaseURL() }}"> -->
  <meta name="file-base-url" content="{{ env('UPLOADS_BASE_URL', url('public')).'/' }}">

  <meta name="facebook-domain-verification" content="k417v9v9jsneemmwemupdh8l0bl3g9" />

  <title>@yield('meta_title', get_setting('website_name') . ' | ' . get_setting('site_motto'))</title>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="index, follow">
  <meta name="description" content="@yield('meta_description', get_setting('meta_description'))" />
  <meta name="keywords" content="@yield('meta_keywords', get_setting('meta_keywords'))">

  @yield('meta')

  @if (!isset($detailedProduct) && !isset($customer_product) && !isset($shop) && !isset($page) && !isset($blog))
    <!-- Schema.org markup for Google+ -->
    @php
        // Fetch file_name for the meta image from settings
        $meta_image_id = get_setting('meta_image');
        $meta_image_file = \App\Models\Upload::where('id', $meta_image_id)->value('file_name');
        $meta_image_path = $meta_image_file
                    ? url('public/' . $meta_image_file)
                    : url('public/assets/img/placeholder.jpg');
    @endphp
    <meta itemprop="name" content="{{ get_setting('meta_title') }}">
    <meta itemprop="description" content="{{ get_setting('meta_description') }}">
    <!-- <meta itemprop="image" content="{{ uploaded_asset(get_setting('meta_image')) }}"> -->
    <meta itemprop="image" content="{{ $meta_image_path }}">

    <!-- Twitter Card data -->
    <meta name="twitter:card" content="product">
    <meta name="twitter:site" content="@publisher_handle">
    <meta name="twitter:title" content="{{ get_setting('meta_title') }}">
    <meta name="twitter:description" content="{{ get_setting('meta_description') }}">
    <meta name="twitter:creator"
      content="@author_handle">
        <!-- <meta name="twitter:image" content="{{ uploaded_asset(get_setting('meta_image')) }}"> -->
        <meta name="twitter:image" content="{{ $meta_image_path }}">

        <!-- Open Graph data -->
        <meta property="og:title" content="{{ get_setting('meta_title') }}" />
        <meta property="og:type" content="website" />
        <meta property="og:url" content="{{ route('home') }}" />
        <!-- <meta property="og:image" content="{{ uploaded_asset(get_setting('meta_image')) }}" /> -->
        <meta property="og:image" content="{{ $meta_image_path }}">
        <meta property="og:description" content="{{ get_setting('meta_description') }}" />
        <meta property="og:site_name" content="{{ env('APP_NAME') }}" />
        <meta property="fb:app_id" content="{{ env('FACEBOOK_PIXEL_ID') }}">
    @endif

    <!-- Favicon -->
    @php
        // Fetch file_name for the site icon from settings
        $site_icon_id = get_setting('site_icon');
        $site_icon_file = \App\Models\Upload::where('id', $site_icon_id)->value('file_name');
        $site_icon_path = $site_icon_file
                    ? url('public/' . $site_icon_file)
                    : url('public/assets/img/placeholder.jpg');
    @endphp
    <!-- <link rel="icon" href="{{ uploaded_asset(get_setting('site_icon')) }}"> -->
    <link rel="icon" href="{{ $site_icon_path }}">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:ital,wght@0,300;0,400;0,600;0,700;0,800;1,300;1,400;1,600;1,700;1,800&display=swap" rel="stylesheet">

    <!-- CSS Files -->
    <link rel="stylesheet" href="{{ static_asset('assets/css/vendors.css') }}">
    @if (\App\Models\Language::where('code', Session::get('locale', Config::get('app.locale')))->first()->rtl == 1)
    <link rel="stylesheet" href="{{ static_asset('assets/css/bootstrap-rtl.min.css') }}">
    @endif
    <link rel="stylesheet" href="{{ static_asset('assets/css/aiz-core.css') }}">
    <link rel="stylesheet" href="{{ static_asset('assets/css/custom-style.css') }}">

    @if(Route::currentRouteName() == 'products.quickorder')
        <!-- SCRIPTS -->

        <script src="{{ static_asset('assets/js/vendors.js') }}"></script>
        <script src="{{ static_asset('assets/js/aiz-core.js') }}"></script>
    @endif


    <!-- Select 2 dropdown Start -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous"> -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.4/css/select2.min.css" rel="stylesheet" />
    {{-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script> --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.4/js/select2.min.js"></script>
    <style>
        /* body {
            font-family: 'Ubuntu', sans-serif;
            font-weight: bold;
        } */
        .select2-container {
            min-width: 400px;
        }

        .select2-results__option {
            padding-right: 20px;
            vertical-align: middle;
        }
        .select2-results__option:before {
            content: "";
            display: inline-block;
            position: relative;
            height: 20px;
            width: 20px;
            border: 2px solid #e9e9e9;
            border-radius: 4px;
            background-color: #fff;
            margin-right: 20px;
            vertical-align: middle;
        }
        .select2-results__option[aria-selected=true]:before {
            font-family:fontAwesome;
            content: "\f00c";
            color: #fff;
            background-color: #074e86;
            border: 0;
            display: inline-block;
            padding-left: 3px;
        }
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: #fff;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #eaeaeb;
            color: #272727;
        }
        .select2-container--default .select2-selection--multiple {
            margin-bottom: 10px;
        }
        .select2-container--default.select2-container--open.select2-container--below .select2-selection--multiple {
            border-radius: 4px;
        }
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #074e86;
            border-width: 2px;
        }
        .select2-container--default .select2-selection--multiple {
            border-width: 2px;
        }
        .select2-container--open .select2-dropdown--below {

            border-radius: 6px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);

        }
        .select2-selection .select2-selection--multiple:after {
            content: 'hhghgh';
        }
        /* select with icons badges single*/
        .select-icon .select2-selection__placeholder .badge {
            display: none;
        }
        .select-icon .placeholder {
            display: none;
        }
        .select-icon .select2-results__option:before,
        .select-icon .select2-results__option[aria-selected=true]:before {
            display: none !important;
            /* content: "" !important; */
        }
        .select-icon  .select2-search--dropdown {
            display: none;
        }
        /* .select2-selection__clear {
            display:none;
        } */
    </style>
    <!-- Select 2 dropdown End -->
    <script>
        var AIZ = AIZ || {};
        AIZ.local = {
            nothing_selected: '{!! translate('Nothing selected', null, true) !!}',
            nothing_found: '{!! translate('Nothing found', null, true) !!}',
            choose_file: '{{ translate('Choose file') }}',
            file_selected: '{{ translate('File selected') }}',
            files_selected: '{{ translate('Files selected') }}',
            add_more_files: '{{ translate('Add more files') }}',
            adding_more_files: '{{ translate('Adding more files') }}',
            drop_files_here_paste_or: '{{ translate('Drop files here, paste or') }}',
            browse: '{{ translate('Browse') }}',
            upload_complete: '{{ translate('Upload complete') }}',
            upload_paused: '{{ translate('Upload paused') }}',
            resume_upload: '{{ translate('Resume upload') }}',
            pause_upload: '{{ translate('Pause upload') }}',
            retry_upload: '{{ translate('Retry upload') }}',
            cancel_upload: '{{ translate('Cancel upload') }}',
            uploading: '{{ translate('Uploading') }}',
            processing: '{{ translate('Processing') }}',
            complete: '{{ translate('Complete') }}',
            file: '{{ translate('File') }}',
            files: '{{ translate('Files') }}',
        }
    </script>

    <style>
        body{
            font-family: 'Jost', sans-serif;
            font-weight: 400;
        }
        :root{
            --primary: {{ get_setting('base_color', '#e62d04') }};
            --hov-primary: {{ get_setting('base_hov_color', '#c52907') }};
            --soft-primary: {{ hex2rgba(get_setting('base_color', '#e62d04'), 0.15) }};
        }

        #map{
            width: 100%;
            height: 250px;
        }
        #edit_map{
            width: 100%;
            height: 250px;
        }

        .pac-container { z-index: 100000; }
    </style>

@if (get_setting('google_analytics') == 1)
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ env('TRACKING_ID') }}"></script>

    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ env('TRACKING_ID') }}');
    </script>
@endif

@if (get_setting('facebook_pixel') == 1)
    <!-- Facebook Pixel Code -->
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '{{ env('FACEBOOK_PIXEL_ID') }}');
        fbq('track', 'PageView');
    </script>
    <noscript>
        <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ env('FACEBOOK_PIXEL_ID') }}&ev=PageView&noscript=1"/>
    </noscript>
    <!-- End Facebook Pixel Code -->
@endif

@php
    echo get_setting('header_script');
@endphp

</head>
<body>
    <!-- aiz-main-wrapper -->
    <div class="aiz-main-wrapper d-flex flex-column">

        <!-- Header -->
        @include('frontend.inc.nav')

        <section class="mt-3">
        @yield('content')
        </section>

        @include('frontend.inc.footer')

    </div>
    

    @if (get_setting('show_cookies_agreement') == 'on')
        <div class="aiz-cookie-alert shadow-xl">
            <div class="p-3 bg-dark rounded">
                <div class="text-white mb-3">
                    @php
                        echo get_setting('cookies_agreement_text');
                    @endphp
                </div>
                <button class="btn btn-primary aiz-cookie-accept">
                    {{ translate('Ok. I Understood') }}
                </button>
            </div>
        </div>
    @endif

    @if (get_setting('show_website_popup') == 'on')
        <div class="modal website-popup removable-session d-none" data-key="website-popup" data-value="removed">
            <div class="absolute-full bg-black opacity-60"></div>
            <div class="modal-dialog modal-dialog-centered modal-dialog-zoom modal-md">
                <div class="modal-content position-relative border-0 rounded-0">
                    <div class="aiz-editor-data">
                        {!! get_setting('website_popup_content') !!}
                    </div>
                    @if (get_setting('show_subscribe_form') == 'on')
                        <div class="pb-5 pt-4 px-5">
                            <form class="" method="POST" action="{{ route('subscribers.store') }}">
                                @csrf
                                <div class="form-group mb-0">
                                    <input type="email" class="form-control" placeholder="{{ translate('Your Email Address') }}" name="email" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block mt-3">
                                    {{ translate('Subscribe Now') }}
                                </button>
                            </form>
                        </div>
                    @endif
                    <button class="absolute-top-right bg-white shadow-lg btn btn-circle btn-icon mr-n3 mt-n3 set-session" data-key="website-popup" data-value="removed" data-toggle="remove-parent" data-parent=".website-popup">
                        <i class="la la-close fs-20"></i>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @include('frontend.partials.modal')

    @include('frontend.partials.account_delete_modal')

    <div class="modal fade" id="addToCart">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-zoom product-modal" id="modal-size" role="document">
            <div class="modal-content position-relative">
                <div class="c-preloader text-center p-3">
                    <i class="las la-spinner la-spin la-3x"></i>
                </div>
                <button type="button" class="close absolute-top-right btn-icon close z-1" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" class="la-2x">&times;</span>
                </button>
                <div id="addToCart-modal-body">

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="quickView">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-zoom product-modal" id="modal-qv-size" role="document">
            <div class="modal-content position-relative">
                <div class="c-preloader text-center p-3">
                    <i class="las la-spinner la-spin la-3x"></i>
                </div>
                <button type="button" class="close absolute-top-right btn-icon close z-1" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" class="la-2x">&times;</span>
                </button>
                <div id="quickview-modal-body">

                </div>
            </div>
        </div>
    </div>
    <script src="{{ static_asset('assets/js/aiz-core.js') }}"></script>
    @yield('modal')

    @if(Route::currentRouteName() != 'products.quickorder')
        <!-- SCRIPTS -->
        <script src="{{ static_asset('assets/js/vendors.js') }}"></script>
        <script src="{{ static_asset('assets/js/aiz-core.js') }}"></script>
    @endif

    @if (get_setting('facebook_chat') == 1)
        <script type="text/javascript">
            window.fbAsyncInit = function() {
                FB.init({
                  xfbml            : true,
                  version          : 'v3.3'
                });
              };

              (function(d, s, id) {
              var js, fjs = d.getElementsByTagName(s)[0];
              if (d.getElementById(id)) return;
              js = d.createElement(s); js.id = id;
              js.src = 'https://connect.facebook.net/en_US/sdk/xfbml.customerchat.js';
              fjs.parentNode.insertBefore(js, fjs);
            }(document, 'script', 'facebook-jssdk'));
        </script>
        <div id="fb-root"></div>
        <!-- Your customer chat code -->
        <div class="fb-customerchat"
          attribution=setup_tool
          page_id="{{ env('FACEBOOK_PAGE_ID') }}">
        </div>
    @endif

    <script>
        @foreach (session('flash_notification', collect())->toArray() as $message)
            AIZ.plugins.notify('{{ $message['level'] }}', '{{ $message['message'] }}');
        @endforeach
    </script>

    @if(Route::currentRouteName() == 'products.quickorder')
        @if($order_id == "" AND $sub_order_id == "")
            <script type="text/javascript" src="https://d3mkw6s8thqya7.cloudfront.net/integration-plugin.js" id="aisensy-wa-widget" widget-id="qIDKbO"></script>
        @endif
    @else
        <script type="text/javascript" src="https://d3mkw6s8thqya7.cloudfront.net/integration-plugin.js" id="aisensy-wa-widget" widget-id="qIDKbO"></script>
    @endif
    <script type="text/javascript" src="https://d3mkw6s8thqya7.cloudfront.net/integration-plugin.js" id="aisensy-wa-widget" widget-id="qIDKbO"></script>
    @if (!Request::is('checkout/*') AND Route::currentRouteName() !== 'products.quickorder')            
        <a href="{{ route('products.quickorder') }}" class="btn btn-circle btn-primary position-fixed d-none d-xl-block z-index-1" style="bottom:20px;right:15px;z-index:10"><span class="las la-cart-arrow-down"></span> Quick Order</a>
    @endif

    <script>

        $(document).ready(function() {

            $(window).scroll(function() {
                if ($(this).scrollTop() > 40) {
                    $('#businessz').fadeOut(200);
                } else {
                    $('#businessz').fadeIn(200);
                }
            });

            $('.category-nav-element').each(function(i, el) {
                $(el).on('mouseover', function(){
                    if(!$(el).find('.sub-cat-menu').hasClass('loaded')){
                        $.post('{{ route('category.elements') }}', {_token: AIZ.data.csrf, id:$(el).data('id')}, function(data){
                            $(el).find('.sub-cat-menu').addClass('loaded').html(data);
                        });
                    }
                });
            });
            if ($('#lang-change').length > 0) {
                $('#lang-change .dropdown-menu a').each(function() {
                    $(this).on('click', function(e){
                        e.preventDefault();
                        var $this = $(this);
                        var locale = $this.data('flag');
                        $.post('{{ route('language.change') }}',{_token: AIZ.data.csrf, locale:locale}, function(data){
                            location.reload();
                        });

                    });
                });
            }

            if ($('#currency-change').length > 0) {
                $('#currency-change .dropdown-menu a').each(function() {
                    $(this).on('click', function(e){
                        e.preventDefault();
                        var $this = $(this);
                        var currency_code = $this.data('currency');
                        $.post('{{ route('currency.change') }}',{_token: AIZ.data.csrf, currency_code:currency_code}, function(data){
                            location.reload();
                        });

                    });
                });
            }
        });

        var typingTimer;

        $('#search').on('keyup', function(){
            clearTimeout(typingTimer);
            typingTimer = setTimeout(search, 500);
            $('#search-content').html('');
        });

        $('#search').on('focus', function(){
            if ($('#search-content').html().trim() !== '') {
                $('.typed-search-box').removeClass('d-none');
                $('body').addClass("typed-search-box-shown");
            }
            $('#search-content').show();
        });

        function search(){
            var searchKey = $('#search').val();
            if(searchKey.length >= 3){
                $('body').addClass("typed-search-box-shown");
                $('.typed-search-box').removeClass('d-none');
                $('.search-preloader').removeClass('d-none');
                $('.typed-search-box .search-nothing').removeClass('d-none').html('');
                
                $.post('{{ route('search.ajax') }}', { _token: AIZ.data.csrf, search: searchKey }, function(data){
                    if(data == '0'){
                        $('#search-content').html(null);
                        $('.typed-search-box .search-nothing').removeClass('d-none').html('{{ translate('Sorry, nothing found for') }} <strong>"'+searchKey+'"</strong>');
                        $('.search-preloader').addClass('d-none');
                    } else {
                        $('.typed-search-box .search-nothing').addClass('d-none').html(null);
                        $('#search-content').html(data);
                        $('.search-preloader').addClass('d-none');
                    }
                });
            } else {
                $('.typed-search-box').addClass('d-none');
                $('body').removeClass("typed-search-box-shown");
            }
        }

        function updateNavCart(view,count){
            $('.cart-count').html(count);
            $('#cart_items').html(view);
        }

        function removeFromCart(key){

            $.post('{{ route('cart.removeFromCart') }}', {
                _token  : AIZ.data.csrf,
                id      :  key
            }, function(data){
                updateNavCart(data.nav_cart_view, data.cart_count);
                $('#cart-summary').html(data.html);
                document.getElementById('list_menu').classList.add('show');
                AIZ.plugins.notify('success', "{{ translate('Item has been removed from cart') }}");
                $('#cart_items_sidenav').html(parseInt($('#cart_items_sidenav').html())-1);
            });
            var url = '/cart/productDetails/' + key;
            $.ajax({
                type:"GET",
                url: url,
                success: function(data){
                //     var element=document.querySelector('.dropdown-menu');
                //    stopPropagation(element);

                    if (data.cart.is_carton == 1) {
                        var total = (data.cart.quantity * data.stocks.piece_per_carton) * Math.round(data.cart.price + data.cart.tax);
                    } else {
                        var total = data.cart.quantity * Math.round(data.cart.price + data.cart.tax);
                    }
                    
                    // Google analytic
                    gtag("event", "remove_from_cart", {
                    currency: "INR",
                    value: data.cart.quantity * Math.round(data.cart.price + data.cart.tax),
                    items: [
                        {
                        item_id: data.product.id,
                        item_name: data.product.name,
                        affiliation: "Mazing Business",
                        index: 0,
                        item_brand: data.brand.name,
                        item_category: data.category.category_group.name,
                        item_variant : data.cart.variation,
                        price: Math.round(data.cart.price + data.cart.tax),
                        quantity: data.cart.quantity
                        }
                    ]
                    });
                }
            });
        }

        function addToCompare(id){
            $.post('{{ route('compare.addToCompare') }}', {_token: AIZ.data.csrf, id:id}, function(data){
                $('#compare').html(data);
                AIZ.plugins.notify('success', "{{ translate('Item has been added to compare list') }}");
                $('#compare_items_sidenav').html(parseInt($('#compare_items_sidenav').html())+1);
            });
        }

        function addToWishList(id){
            @if (Auth::check() && Auth::user()->user_type == 'customer')
                $.post('{{ route('wishlists.store') }}', {_token: AIZ.data.csrf, id:id}, function(data){
                    if(data != 0){
                        $('#wishlist').html(data);
                        AIZ.plugins.notify('success', "{{ translate('Item has been added to wishlist') }}");
                    }
                    else{
                        AIZ.plugins.notify('warning', "{{ translate('Please login first') }}");
                    }
                });
            @elseif(Auth::check() && Auth::user()->user_type != 'customer')
                AIZ.plugins.notify('warning', "{{ translate('Please Login as a customer to add products to the WishList.') }}");
            @else
                AIZ.plugins.notify('warning', "{{ translate('Please login first') }}");
            @endif
        }

        function showAddToCartModal(id){
            
            
            if(!$('#modal-size').hasClass('modal-lg')){
                $('#modal-size').addClass('modal-lg');
            }
            $('#addToCart-modal-body').html(null);
            $('#addToCart').modal();
            $('.c-preloader').show();
            var order_id = $("#order_id").val();
            var sub_order_id = $("#sub_order_id").val();
             // Collect selected attribute values edit by dipak start 
                 // Collect selected attribute values
                    let selectedValues = {};
                    $('.attribute-select').each(function () {
                        let attributeId = $(this).data('attribute-id');
                        let selectedValue = $(this).val();
                        if (selectedValue) {
                            selectedValues[attributeId] = selectedValue;
                        }
                    });
                // Collect selected attribute values edit by dipak end 

            $.post('{{ route('cart.showCartModal') }}', {_token: AIZ.data.csrf, id:id, selected_values: selectedValues, order_id: order_id, sub_order_id: sub_order_id}, function(data){
               
               
                $('.c-preloader').hide();
                $('#addToCart-modal-body').html(data);

                // AIZ.plugins.slickCarousel();
                // AIZ.plugins.zoom();
                // AIZ.extra.plusMinus();
                // getVariantPrice();
                $(window).trigger('resize');
            });
        }

        function showQuickViewModal(id){
            if(!$('#modal-qv-size').hasClass('modal-lg')){
                $('#modal-qv-size').addClass('modal-lg');
            }
            $('#quickview-modal-body').html(null);
            $('#quickView').modal();
            $('.c-preloader').show();
            $.post('{{ route('product.showquickviewmodal') }}', {_token: AIZ.data.csrf, id:id}, function(data){
                $('.c-preloader').hide();
                $('#quickview-modal-body').html(data);
                AIZ.plugins.slickCarousel();
                AIZ.plugins.zoom();
                AIZ.extra.plusMinus();
                $(window).trigger('resize');
            });
        }

        $('#option-choice-form input').on('change', function(){
            getVariantPrice();
            getVariantCartonPrice();
        });

        function getVariantPrice(){
            if($('#option-choice-form input[name=quantity]').val() > 0 && checkAddToCartValidity()){

                $.ajax({
                   type:"POST",
                   url: '{{ route('products.variant_price') }}',
                   data: $('#option-choice-form').serializeArray(),
                   success: function(data){

                        $('.product-gallery-thumb .carousel-box').each(function (i) {
                            if($(this).data('variation') && data.variation == $(this).data('variation')){
                                $('.product-gallery-thumb').slick('slickGoTo', i);
                            }
                        })

                        $('#option-choice-form #chosen_price_div').removeClass('d-none');
                        $('#discounted_price').html(data.single_price);
                        $('#option-choice-form #chosen_price_div #chosen_price').html(data.price);
                        $('#available-quantity').html(data.quantity);
                        $('.input-number').prop('max', data.max_limit);
                        if(parseInt(data.in_stock) == 0){
                           $('.buy-now, #add-to-cart, #orderby').addClass('d-none');
                           $('.out-of-stock').removeClass('d-none');
                        }
                        else{
                           $('.buy-now, #add-to-cart, #orderby').removeClass('d-none');
                           $('.out-of-stock').addClass('d-none');
                        }

                        AIZ.extra.plusMinus();
                   }
               });
            }
        }

        function getVariantCartonPrice(){
            if($('#option-choice-form input[name=quantity]').val() > 0 && checkAddToCartValidity()){
                $.ajax({
                   type:"POST",
                   url: '{{ route('products.variant_price') }}',
                   data: $('#option-choice-form').serializeArray(),
                   success: function(data){

                        $('.product-gallery-thumb .carousel-box').each(function (i) {
                            if($(this).data('variation') && data.variation == $(this).data('variation')){
                                $('.product-gallery-thumb').slick('slickGoTo', i);
                            }
                        })

                        $('#option-choice-form #chosen_carton_price_div').removeClass('d-none');
                        $('#option-choice-form #chosen_carton_price_div #chosen_carton_price').html(data.carton_price);
                        $('#available-quantity').html(data.carton_quantity);
                        $('#ppc').html(data.piece_per_carton);
                        $('#piece_per_carton').val(data.piece_per_carton);
                        $('.input-carton-number').prop('max', data.max_carton_limit);
                        if(parseInt(data.in_stock) == 0){
                           $('.buy-now, #add-to-cart, #orderby').addClass('d-none');
                           $('.out-of-stock').removeClass('d-none');
                        }
                        else{
                           $('.buy-now, #add-to-cart, #orderby').removeClass('d-none');
                           $('.out-of-stock').addClass('d-none');
                        }

                        AIZ.extra.plusMinus();
                   }
               });
            }
        }

        function checkAddToCartValidity(){
            var names = {};
            $('#option-choice-form input:radio').each(function() { // find unique names
                names[$(this).attr('name')] = true;
            });
            var count = 0;
            $.each(names, function() { // then count them
                count++;
            });

            if($('#option-choice-form input:radio:checked').length == count){
                return true;
            }

            return false;
        }

        function addToCart(is_carton){

            
            @if (Auth::check() && Auth::user()->user_type != 'customer')
                alert("Please Login as a customer to add products to the Cart.");
            // AIZ.plugins.notify('warning', "{{ translate('Please Login as a customer to add products to the Cart.') }}");
                return false;
            @endif
            var inputData = $('#option-choice-form').serializeArray();

            $('#addToCart').modal();
            $('.c-preloader').show();
            var product_id = $("input[name=id]").val();
            var product_name = $("input[name=product_name]").val();
            var brand_name = $("input[name=brand_name]").val();
            var category_name = $("input[name=category_name]").val();
            var variant = $("input[name=variant]").val();
            if($('#add-to-cart').attr('data-type') == 'is_carton') {


                // Carton based calculation
                inputData.push({'name':'is_carton', 'value':1});
                var quantity = $("input[name=carton_quantity]").val();
                var price = $("input[name=order_by_carton_price]").val();
                var piece_per_carton = $("input[name=piece_per_carton]").val();
                var carton_quantity = $("input[name=carton_quantity]").val();
                var total = price * (piece_per_carton * carton_quantity);
            }else{

                // Piece based calculation
                var price = $("input[name=order_by_piece_price]").val();
                var quantity = $("input[name=quantity]").val();
                var total   = price * quantity;
            }

            // Google analytics
            gtag("event", "add_to_cart", {
                currency: "INR",
                value: {total},
                items: [
                    {
                    item_id: {product_id},
                    item_name: {product_name},
                    affiliation: "Mazing Business",
                    index: 0,
                    item_brand: {brand_name},
                    item_category: {category_name},
                    item_variant: {variant},
                    price: {price},
                    quantity: {quantity},
                    }
                ]
            });
            $.ajax({
                type:"POST",
                url: '{{ route('cart.addToCart') }}',
                data: inputData,
                success: function(data){          

                    $('#addToCart-modal-body').html(null);
                    $('.c-preloader').hide();
                    $('#modal-size').removeClass('modal-lg');
                    $('#addToCart-modal-body').html(data.modal_view);
                    // AIZ.extra.plusMinus();
                    // AIZ.plugins.slickCarousel();
                    updateNavCart(data.nav_cart_view,data.cart_count);
                }
            });
        }

        function addProduct(is_carton){            
            // @if (Auth::check() && Auth::user()->user_type != 'customer')
            //     alert("Please Login as a customer to add products to the Cart.");
            // // AIZ.plugins.notify('warning', "{{ translate('Please Login as a customer to add products to the Cart.') }}");
            //     return false;
            // @endif

            var inputData = $('#option-choice-form').serializeArray();
            
            $('#addToCart').modal();
            $('.c-preloader').show();
            var product_id = $("input[name=id]").val();
            var order_id = $("input[name=order_id]").val();
            var sub_order_id = $("input[name=sub_order_id]").val();
            var product_name = $("input[name=product_name]").val();
            var brand_name = $("input[name=brand_name]").val();
            var category_name = $("input[name=category_name]").val();
            var variant = $("input[name=variant]").val();
            if($('#add-to-cart').attr('data-type') == 'is_carton') {
                // Carton based calculation
                inputData.push({'name':'is_carton', 'value':1});
                var quantity = $("input[name=carton_quantity]").val();
                var price = $("input[name=order_by_carton_price]").val();
                var piece_per_carton = $("input[name=piece_per_carton]").val();
                var carton_quantity = $("input[name=carton_quantity]").val();
                var total = price * (piece_per_carton * carton_quantity);
            }else{
                // Piece based calculation
                var price = $("input[name=order_by_piece_price]").val();
                var quantity = $("input[name=quantity]").val();
                var total   = price * quantity;
            }

            // Google analytics
            gtag("event", "add_to_cart", {
                currency: "INR",
                value: {total},
                items: [
                    {
                        item_id: {product_id},
                        item_name: {product_name},
                        affiliation: "Mazing Business",
                        index: 0,
                        item_brand: {brand_name},
                        item_category: {category_name},
                        item_variant: {variant},
                        price: {price},
                        quantity: {quantity},
                        order_id: {order_id},
                        sub_order_id: {sub_order_id},
                    }
                ]
            });
            // Add this once globally
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.ajax({
                type:"POST",
                url: '{{ route('cart.addProductToSplitOrder') }}',
                data: inputData,
                success: function(data){
                    $('#addToCart-modal-body').html(null);
                    $('.c-preloader').hide();
                    $('#modal-size').removeClass('modal-lg');
                    $('#addToCart-modal-body').html(data.modal_view);
                    // AIZ.extra.plusMinus();
                    // AIZ.plugins.slickCarousel();
                    updateNavCart(data.nav_cart_view,data.cart_count);
                }
            });
        }

        function buyNow(){
            @if (Auth::check() && Auth::user()->user_type != 'customer')
                AIZ.plugins.notify('warning', "{{ translate('Please Login as a customer to add products to the Cart.') }}");
                return false; @endif
            var inputData = $('#option-choice-form').serializeArray();
            if($('#add-to-cart').attr('data-type') == 'is_carton') {
                inputData.push({'name':'is_carton', 'value':1});
            }
            if(checkAddToCartValidity()) {
                $('#addToCart-modal-body').html(null);
                $('#addToCart').modal();
                $('.c-preloader').show();
                $.ajax({
                    type:"POST",
                    url: '{{ route('cart.addToCart') }}' , data: inputData, success: function(data) { if(data.status==1) {
                    $('#addToCart-modal-body').html(data.modal_view); updateNavCart(data.nav_cart_view,data.cart_count);
                    window.location.replace("{{ route('cart') }}"); } else { $('#addToCart-modal-body').html(null);
                    $('.c-preloader').hide(); $('#modal-size').removeClass('modal-lg');
                    $('#addToCart-modal-body').html(data.modal_view); } } }); } else {
                    AIZ.plugins.notify('warning', "{{ translate('Please choose all the options') }}" ); 
                } 
        } 
    </script>

    @yield('script')
        <script>
            $(document).ready(function() {
                function stopPropagation(event) {
                    event.stopPropagation();
                }
                $('body').on('click','.dropdown-menu', function(event) {
                    event.stopPropagation();
                });

                $('body').on('click', '#dell', function(e) {

                    document.getElementById('list_menu').classList.add('show');

                });

                $('body').on('click', '#cart_items', function() {

                    $(this).toggleClass('show');
                    $('#cart_items div').toggleClass('show');
                });
            });
        </script>   
        @php
            echo get_setting('footer_script');
        @endphp        
    </body>
</html>
@if(Route::currentRouteName() == 'products.quickorder')
    @if($order_id != "" OR $sub_order_id != "")
            @php dd('Stop'); @endphp
    @endif
@endif
