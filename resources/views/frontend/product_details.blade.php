@extends('frontend.layouts.app')

@section('meta_title'){{ $detailedProduct->meta_title }}@stop

@section('meta_description'){{ $detailedProduct->meta_description }}@stop

@section('meta_keywords'){{ $detailedProduct->tags }}@stop

@section('meta')
  <!-- Schema.org markup for Google+ -->
  <meta itemprop="name" content="{{ $detailedProduct->meta_title }}">
  <meta itemprop="description" content="{{ $detailedProduct->meta_description }}">
  <meta itemprop="image" content="{{ uploaded_asset($detailedProduct->meta_img) }}">

  <!-- Twitter Card data -->
  <meta name="twitter:card" content="product">
  <meta name="twitter:site" content="@publisher_handle">
  <meta name="twitter:title" content="{{ $detailedProduct->meta_title }}">
  <meta name="twitter:description" content="{{ $detailedProduct->meta_description }}">
  <meta name="twitter:creator"
    content="@author_handle">
    <meta name="twitter:image" content="{{ uploaded_asset($detailedProduct->meta_img) }}">
    <meta name="twitter:data1" content="{{ single_price($detailedProduct->unit_price) }}">
    <meta name="twitter:label1" content="Price">

    <!-- Open Graph data -->
    <meta property="og:title" content="{{ $detailedProduct->meta_title }}" />
    <meta property="og:type" content="og:product" />
    <meta property="og:url" content="{{ route('product', $detailedProduct->slug) }}" />
    <meta property="og:image" content="{{ uploaded_asset($detailedProduct->meta_img) }}" />
    <meta property="og:description" content="{{ $detailedProduct->meta_description }}" />
    <meta property="og:site_name" content="{{ get_setting('meta_title') }}" />
    <meta property="og:price:amount" content="{{ single_price($detailedProduct->unit_price) }}" />
    <meta property="product:price:currency"
        content="{{ \App\Models\Currency::findOrFail(get_setting('system_default_currency'))->code }}" />
    <meta property="fb:app_id" content="{{ env('FACEBOOK_PIXEL_ID') }}">
@endsection

@section('content')
<style>
  .view-offer {
      transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
  }

  .view-offer:hover {
      transform: scale(1.1);
      opacity: 0.8;
  }
</style>
    <section  class="mb-4 pt-3">
        <div class="container-fluid">
            <div class="bg-white shadow-sm rounded p-3">
                <div class="row">
                    <div class="col-xl-5 col-lg-6 mb-4">
                        <div class="sticky-top z-3 row gutters-10">
                            @php
                                $photos = explode(',', $detailedProduct->photos);
                            @endphp
                            
                            <div class="col order-1 order-md-2">
                                {!! ($detailedProduct->cash_and_carry_item == 1 && Auth::check() && Auth::user()->credit_days > 0) ? '<span class="badge-custom">No Credit Item<span class="box ml-1 mr-0">&nbsp;</span></span>' : '' !!}
                                <div class="aiz-carousel product-gallery" data-nav-for='.product-gallery-thumb'
                                    data-fade='true' data-auto-height='true'>
                                    @foreach ($photos as $key => $photo)
                                        @php
                                            // Fetch base URL for uploads from the .env file
                                            $baseUrl = env('UPLOADS_BASE_URL', url('public'));

                                            // Fetch file_name for the photo (assuming $photo contains the ID of the upload)
                                            $photo_file = \App\Models\Upload::where('id', $photo)->value('file_name');
                                            $photo_file_path = $photo_file
                                                        ? $baseUrl . '/' . $photo_file
                                                        : url('public/assets/img/placeholder.jpg');
                                        @endphp
                                        <div class="carousel-box img-zoom rounded">
                                            <!-- <img class="img-fluid lazyload"
                                                src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                                data-src="{{ uploaded_asset($photo) }}"
                                                onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                                                <img class="img-fluid lazyload" 
                                                    src="{{ url('public/assets/img/placeholder.jpg') }}"
                                                    data-src="{{ $photo_file_path }}" 
                                                    onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">
                                                    
                                        </div>
                                    @endforeach
                                    @foreach ($detailedProduct->stocks as $key => $stock)
                                        @if ($stock->image != null)
                                            @php
                                                // Fetch base URL for uploads from the .env file
                                                $baseUrl = env('UPLOADS_BASE_URL', url('public'));

                                                // Fetch file_name for the stock image (assuming $stock->image contains the ID of the upload)
                                                $stock_image = \App\Models\Upload::where('id', $stock->image)->value('file_name');
                                                $stock_image_path = $stock_image
                                                            ? $baseUrl . '/' . $stock_image
                                                            : url('public/assets/img/placeholder.jpg');
                                            @endphp
                                            
                                            <div class="carousel-box img-zoom rounded">
                                                <img class="img-fluid lazyload"
                                                src="{{ url('public/assets/img/placeholder.jpg') }}"
                                                data-src="{{ $stock_image_path }}"
                                                onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">
                                                <!-- <img class="img-fluid lazyload"
                                                    src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                                    data-src="{{ uploaded_asset($stock->image) }}"
                                                    onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12 col-md-auto w-md-80px order-2 order-md-1 mt-3 mt-md-0">
                                <div class="aiz-carousel product-gallery-thumb" data-items='5'
                                    data-nav-for='.product-gallery' data-vertical='true' data-vertical-sm='false'
                                    data-focus-select='true' data-arrows='true'>
                                    @foreach ($photos as $key => $photo)
                                        <div class="carousel-box c-pointer border p-1 rounded">
                                            @php
                                                // Fetch base URL for uploads from the .env file
                                                $baseUrl = env('UPLOADS_BASE_URL', url('public'));

                                                // Fetch file_name for the photo (assuming $photo contains the ID of the upload)
                                                $photo_file = \App\Models\Upload::where('id', $photo)->value('file_name');
                                                $photo_file_path = $photo_file
                                                            ? $baseUrl . '/' . $photo_file
                                                            : url('public/assets/img/placeholder.jpg');
                                            @endphp

                                        <img class="lazyload mw-100 size-50px h-auto mx-auto" 
                                            src="{{ url('public/assets/img/placeholder.jpg') }}" 
                                            data-src="{{ $photo_file_path }}" 
                                            onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">
                                            <!-- <img class="lazyload mw-100 size-50px h-auto mx-auto"
                                                src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                                data-src="{{ uploaded_asset($photo) }}"
                                                onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                                        </div>
                                    @endforeach
                                    @foreach ($detailedProduct->stocks as $key => $stock)
                                        @if ($stock->image != null)
                                            @php
                                                // Fetch base URL for uploads from the .env file
                                                $baseUrl = env('UPLOADS_BASE_URL', url('public'));

                                                // Fetch file_name for the stock image (assuming $stock->image contains the ID of the upload)
                                                $stock_image = \App\Models\Upload::where('id', $stock->image)->value('file_name');
                                                $stock_image_path = $stock_image
                                                            ? $baseUrl . '/' . $stock_image
                                                            : url('public/assets/img/placeholder.jpg');
                                            @endphp
                                            <div class="carousel-box c-pointer border p-1 rounded"
                                                data-variation="{{ $stock->variant }}">
                                                <img class="lazyload mw-100 size-50px mx-auto"
                                                    src="{{ url('public/assets/img/placeholder.jpg') }}"
                                                    data-src="{{ $stock_image_path }}"
                                                    onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">
                                                <!-- <img class="lazyload mw-100 size-50px mx-auto"
                                                    src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                                    data-src="{{ uploaded_asset($stock->image) }}"
                                                    onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-7 col-lg-6">
                        <div class="text-left">
                            <p class="mb-3 small">
                            </p>
                            <h1 class="mb-2 fs-20 fw-600">
                                @if ($detailedProduct->brand != null)
                                <a href="{{ route('products.brand', $detailedProduct->brand->slug) }}">
                                @php
                                    // Fetch file_name for the brand logo (assuming $detailedProduct->brand->logo contains the ID of the upload)
                                    $brand_logo = \App\Models\Upload::where('id', $detailedProduct->brand->logo)->value('file_name');
                                    $brand_logo_path = $brand_logo
                                                ? url('public/' . $brand_logo)
                                                : url('public/assets/img/placeholder.jpg');
                                @endphp

                                    <!-- <img src="{{ uploaded_asset($detailedProduct->brand->logo) }}"
                                        alt="{{ $detailedProduct->brand->getTranslation('name') }}"
                                        height="40"> -->
                                        <img src="{{ $brand_logo_path }}"
                                            alt="{{ $detailedProduct->brand->getTranslation('name') }}"
                                            height="40">

                                </a>
                                @endif
                                {{ $detailedProduct->getTranslation('name') }}  
                            </h1>

                            <div class="row align-items-center">
                                <div class="col-12">
                                    @php
                                        $total = 0;
                                        $total += $detailedProduct->reviews->count();
                                    @endphp
                                    <span class="rating">
                                        {{ renderStarRating($detailedProduct->rating) }}
                                    </span>
                                    <span class="ml-1 opacity-50">({{ $total }}
                                        {{ translate('reviews') }})</span>

                                         @if(DB::table('products_api')->where('part_no', $detailedProduct->part_no)->where('closing_stock', '>', 0)->exists())
                                            <img src="{{ asset('public/uploads/fast_dispatch.jpg') }}" alt="Fast Delivery" style="width: 84px; height: 26px;  border-radius: 3px;">
                                        @endif
                                        @if ($detailedProduct->is_warranty == 1)
                                            <img src="{{ asset('public/uploads/warranty.jpg') }}" alt="Fast Delivery" style="width: 75px; border-radius: 3px;"> <strong>{{ $detailedProduct->warranty_duration }} Months </strong>
                                        @endif
                                        @if (Auth::check() && Auth::user()->warehouse_id)
                                            @if(isset($detailedProduct->offer) AND $detailedProduct->offer != "")
                                                <img src="{{ asset('public/uploads/offers-icon.png') }}" alt="Special Offer" class="view-offer" style="width: 115px; height: auto;  border-radius: 3px; cursor: pointer; "data-toggle="modal" data-target="#offerModal" data-product-id="{{ $detailedProduct->id }}">
                                            @endif
                                        @endif

                                </div>

                                @if ($detailedProduct->est_shipping_days)
                                @php $est = get_estimated_shipping_days($detailedProduct); @endphp
                                    @if ($est['days'])
                                        <div class="col-auto ml">
                                            <small class="mr-2 opacity-50">{{ translate('Estimated Shipping Time') }}:
                                            </small>{{ $est['days'] . ' - ' . ($est['days'] + 1) }} {{ translate('Days') }}
                                        </div>
                                        {{-- 
                                        @if ($est['immediate'])<span class="bg-danger text-white rounded px-4 py-1">Dispatch in 24 hours</span>@endif 
                                        --}}
                                    @endif
                                @endif

                                
                            </div>

                            <hr>

                            @if (addon_is_activated('club_point') && $detailedProduct->earn_point > 0)
                                <div class="row no-gutters mt-4">
                                    <div class="col-sm-2">
                                        <div class="opacity-50 my-2">{{ translate('Club Point') }}:</div>
                                    </div>
                                    <div class="col-sm-10">
                                        <div
                                            class="d-inline-block rounded px-2 bg-soft-primary border-soft-primary border">
                                            <span class="strong-700">{{ $detailedProduct->earn_point }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <form id="option-choice-form">
                                @csrf
                                <input type="hidden" name="id" value="{{ $detailedProduct->id }}">
                                <input type="hidden" name="type" id="type" value="piece">
                                <input type="hidden" name="order_by_carton_price"  value="{{ home_bulk_discounted_price($detailedProduct, false)['price'] }}">
                                @if (is_countable(json_decode($detailedProduct->choice_options)))
                                    @foreach (json_decode($detailedProduct->choice_options) as $key => $choice)
                                    @php $attribute = \App\Models\Attribute::find($choice->attribute_id) @endphp
                                        @if (count($choice->values))
                                        <div class="row no-gutters">
                                            <div class="col-6 col-sm-5">
                                                <div class="opacity-50 my-2">
                                                    {{ $attribute->getTranslation('name') }}:
                                                </div>
                                            </div>
                                            <div class="col-6 col-sm-7 d-flex align-items-center">
                                                <div class="aiz-radio-inline">
                                                    @if (
                                                        $detailedProduct->category->categoryGroup->name == 'Accessories' ||
                                                            $detailedProduct->category->categoryGroup->name == 'Spare Parts')
                                                    @if ($attribute->type == 'variant')
                                                        @foreach ($choice->values as $key => $value)
                                                        <label class="aiz-megabox pl-0 mr-2 my-2">
                                                            <input type="radio"
                                                                name="attribute_id_{{ $choice->attribute_id }}"
                                                                value="{{ $value }}"
                                                                @if ($key == 0) checked @endif>
                                                            <span
                                                                class="aiz-megabox-elem rounded d-flex align-items-center justify-content-center py-2 px-3">
                                                                {{ $value }}
                                                            </span>
                                                        </label>
                                                        @endforeach
                                                    @else
                                                    <input type="hidden" name="attribute_id_{{ $choice->attribute_id }}" value="{{ $choice->values[0] }}">
                                                    <div class="my-2">
                                                        @foreach ($choice->values as $key => $value) {{ $value }} @endforeach
                                                    </div>
                                                    @endif
                                                    @else
                                                    @if ($attribute->type == 'variant')
                                                    @php
                                                        $attrs = \App\Models\Product::select('choice_options')->where('category_id', $detailedProduct->category->id)->get();
                                                        $list = [];
                                                        foreach($attrs as $attr) {
                                                            $list = array_merge($list, collect(json_decode($attr->choice_options))->where('attribute_id', $attribute->id)->first()->values);
                                                            $list = array_unique($list);
                                                            sort($list);
                                                        }
                                                    @endphp
                                                    @if (count($list) > 1)
                                                    <input type="hidden" name="attribute_id_{{ $choice->attribute_id }}" value="{{ $choice->values[0] }}">
                                                    <div class="dropdown d-inline-block">
                                                        <button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">{{ $choice->values[0] }}
                                                        @if (count($list) > 1)
                                                            <span class="caret"></span></button>
                                                            <ul class="dropdown-menu">
                                                                @foreach ($list as $value)
                                                            <li><a href="{{ url('/category/' . $detailedProduct->category->slug . '?selected_attribute_values%5B%5D=' . $value) }}" class="ml-3">{{ $value }}</a></li>
                                                            @endforeach
                                                            </ul>
                                                        @endif
                                                      </div>
                                                    @else
                                                    <input type="hidden" name="attribute_id_{{ $choice->attribute_id }}" value="{{ $choice->values[0] }}">
                                                    <div class="my-2">
                                                        @foreach ($choice->values as $key => $value) {{ $value }} @endforeach
                                                    </div>
                                                    @endif
                                                    @else
                                                    <input type="hidden" name="attribute_id_{{ $choice->attribute_id }}" value="{{ $choice->values[0] }}">
                                                    <div class="my-2">
                                                        @foreach ($choice->values as $key => $value) {{ $value }} @endforeach
                                                    </div>
                                                    @endif
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @else
                                        <input type="hidden" name="attribute_id_{{ $choice->attribute_id }}" value="">
                                        @endif
                                    @endforeach
                                    <hr>
                                @endif

                                @if (is_countable(json_decode($detailedProduct->colors)) AND count(json_decode($detailedProduct->colors)) > 0)
                                    <div class="row no-gutters">
                                        <div class="col-sm-2">
                                            <div class="opacity-50 my-2">{{ translate('Color') }}:</div>
                                        </div>
                                        <div class="col-sm-10">
                                            <div class="aiz-radio-inline">
                                                @foreach (json_decode($detailedProduct->colors) as $key => $color)
                                                    <label class="aiz-megabox pl-0 mr-2" data-toggle="tooltip"
                                                        data-title="{{ \App\Models\Color::where('code', $color)->first()->name }}">
                                                        <input type="radio" name="color"
                                                            value="{{ \App\Models\Color::where('code', $color)->first()->name }}"
                                                            @if ($key == 0) checked @endif>
                                                        <span
                                                            class="aiz-megabox-elem rounded d-flex align-items-center justify-content-center p-1 mb-2">
                                                            <span class="size-30px d-inline-block rounded"
                                                                style="background: {{ $color }};"></span>
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <!--     + Add to cart -->
                                <div class="bg-white mb-3 rounded" id="orderby">
                                    @if (Auth::check() && Auth::user()->warehouse_id)
                                            @if ($detailedProduct->wholesale_product)
                                                <table class="table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>{{ translate('Min Qty') }}</th>
                                                            <th>{{ translate('Max Qty') }}</th>
                                                            <th>{{ translate('Unit Price') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($detailedProduct->stocks->first()->wholesalePrices as $wholesalePrice)
                                                            <tr>
                                                                <td>{{ $wholesalePrice->min_qty }}</td>
                                                                <td>{{ $wholesalePrice->max_qty }}</td>
                                                                <td>{{ single_price($wholesalePrice->price) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            @else

                                                @if (home_price($detailedProduct, 0)['price'] != home_discounted_price($detailedProduct, 0)['price'])
                                                    <div class="row no-gutters my-3 without_discount">
                                                        <div class="col-6 col-md-4">
                                                            <div class="opacity-50 my-2">{{ translate('Price') }}:</div>
                                                        </div>
                                                        <div class="col-6 col-md-8 ">
                                                            <div class="fs-20 opacity-60">
                                                                <del>
                                                                    {{ home_price($detailedProduct)['price'] }}
                                                                    @if ($detailedProduct->unit != null)
                                                                        <span>/{{ $detailedProduct->getTranslation('unit') }}</span>
                                                                    @endif
                                                                </del>
                                                            </div>
                                                        </div>
                                                    </div>



                                                    <div class="row no-gutters my-3">
                                                        <div class="col-6 col-md-4">
                                                            <div class="opacity-50">{{ translate('Discount Price') }}:</div>

                                                        </div>
                                                        <div class="col-6 col-md-8">
                                                            <div>
                                                                <strong class="h2 fw-600 text-primary" id="discounted_price">
                                                                    {{ home_discounted_price($detailedProduct)['price'] }}
                                                                </strong>
                                                                @if ($detailedProduct->unit != null)
                                                                    <span class="opacity-70">/{{ $detailedProduct->getTranslation('unit') }}</span>
                                                                @endif
                                                                {!! ($detailedProduct->cash_and_carry_item == 1 && Auth::check() && Auth::user()->credit_days > 0) ? '<span class="badge badge-inline badge-danger">No Credit Item</span>' : '' !!}
                                                            </div>
                                                            <!-- Added the text "Inclusive of all taxes" below the discount price -->
                                                            <div class="mt-1">
                                                                <span style="font-size: 14px; color: #555; opacity:0.8;">Inclusive of all taxes</span>
                                                            </div>

                                                        </div>
                                                    </div>
                                                    <div class="row no-gutters my-3">
                                                        <div class="col-md-12">
                                                            <div class="opacity-70" style="color:#f00; font-weight: bold; font-size: 20px;" id="increasePriceText"></div>
                                                        </div>
                                                    </div>

                                                    <div  class="row no-gutters my-3">
                                                        <div class="col-6 col-md-6">
                                                            {{-- <div class="opacity-50">{{ translate('Discount Price') }}:</div> --}}
                                                            @php
                                                                // $stocks = App\Models\ProductWarehouse::where('product_id', $detailedProduct->id)->get();
                                                            @endphp
                                                            <table class="table table-bordered">
                                                              @php
                                                                // [NEW] Fallback detection if controller didn’t pass $is41Manager
                                                                if (!isset($is41Manager)) {
                                                                    $title = strtolower(trim((string) (Auth::user()->user_title ?? '')));
                                                                    $is41Manager = in_array($title, ['manager_41'], true);
                                                                }
                                                            
                                                                $is41 = $is41Manager ?? false; // confirm flag
                                                            
                                                                // [NEW] Build one query and branch by role
                                                                $stocksQuery = \App\Models\ProductWarehouse::query()
                                                                    ->where('product_id', $detailedProduct->id);
                                                            
                                                                if ($is41) {
                                                                    // Manager-41: only show Manager-41 stock
                                                                    $stocksQuery->where('is_manager_41', 1);
                                                                } else {
                                                                    // Non-Manager-41: show only non-41 stock
                                                                    $stocksQuery->where(function($q){
                                                                        $q->whereNull('is_manager_41')->orWhere('is_manager_41', 0);
                                                                    });
                                                                }
                                                            
                                                                $stocks = $stocksQuery->get();
                                                            
                                                                // [NEW] Prefetch warehouse names to avoid N+1
                                                                $warehouseNames = \App\Models\Warehouse::whereIn('id', $stocks->pluck('warehouse_id'))
                                                                                  ->pluck('name', 'id');
                                                              @endphp
                                                            
                                                              <tbody>
                                                                @forelse ($stocks as $stock)
                                                                  @php $pwName = $warehouseNames[$stock->warehouse_id] ?? '—'; @endphp
                                                                  <tr style="color:#666666;">
                                                                    <td class="medium-col">{{ $pwName }}</td>
                                                                    <td class="medium-col">{{ (int) $stock->qty }}</td>
                                                                  </tr>
                                                                @empty
                                                                  <tr>
                                                                    <td class="text-center text-muted" colspan="2">No stock</td>
                                                                  </tr>
                                                                @endforelse
                                                              </tbody>
                                                            </table>
                                                        </div>

                                                    </div>




                                                    {{-- <div class="row no-gutters">
                                                        Bulk Discount: Purchase {{ home_bulk_qty($detailedProduct)['bulk_qty'] }} or more and get each for {{ home_bulk_discounted_price($detailedProduct)['price'] }} instead of {{ home_discounted_price($detailedProduct)['price'] }}!<br/>
                                                    </div> --}}
                                                    <input type="hidden" name="actual_price" id="actual_price" value="{{ home_discounted_price($detailedProduct)['price'] }}">
                                                    <div class="row no-gutters">
                                                        <input type="hidden" value="{{ home_bulk_discounted_price($detailedProduct)['price'] }}" id="offer">
                                                        <span class="blink">Bulk Quantity Discount :</span>
                                                        Purchase {{ home_bulk_qty($detailedProduct)['bulk_qty'] }} or more and get each for&nbsp;
                                                        <strong style="color: black"> {{ home_bulk_discounted_price($detailedProduct)['price'] }} </strong> &nbsp;instead of &nbsp;<strong  style="color: black"> {{ home_discounted_price($detailedProduct)['price'] }} </strong> <a onclick="buy_now({{ home_bulk_qty($detailedProduct)['bulk_qty'] }})" style="padding-left:10px; color:var(--primary);; font-weight: 600; cursor: pointer;">Get Discount</a>
                                                      </div>
                                                      {{-- <input type="hidden" value="{{ home_bulk_qty($detailedProduct)['bulk_qty'] }}" id="limted_qty"> --}}
                                                @else
                                                    <div class="row no-gutters my-3">
                                                        <div class="col-6 col-md-4">
                                                            <div class="opacity-50 my-2">{{ translate('Price') }}:</div>
                                                        </div>
                                                        <div class="col-6 col-md-8">
                                                            <div>
                                                                <strong class="h2 fw-600 text-primary">{{ home_discounted_price($detailedProduct)['price'] }}</strong>
                                                                @if ($detailedProduct->unit != null)
                                                                    <span class="opacity-70">/{{ $detailedProduct->getTranslation('unit') }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endif
                                            <div class="row no-gutters">
                                                <div class="col-6 col-md-4">
                                                    <div class="opacity-50 my-2">{{ translate('Quantity') }}:</div>
                                                </div>
                                                <div class="col-6 col-md-8">
                                                    <div class="product-quantity d-flex align-items-center">
                                                        <div class="row no-gutters align-items-center aiz-plus-minus mr-3"
                                                            style="width: 130px;">
                                                            {{-- <button class="btn col-auto btn-icon btn-sm btn-circle btn-light"
                                                                type="button" data-type="minus" data-count="1"  data-field="quantity"
                                                                disabled="">
                                                                <i class="las la-minus"></i>
                                                            </button> --}}
                                                            {{-- <input type="number" name="quantity"
                                                                class="col border mx-2 text-center flex-grow-1 fs-16 input-number"
                                                                placeholder="1" value="{{ $detailedProduct->min_qty }}"
                                                                min="{{ $detailedProduct->min_qty }}" max=""
                                                                lang="en" id="quantity"> --}}

                                                                <!-- <input type="number"
                                                                    name="quantity"
                                                                    id="quantity"
                                                                    class="form-control col border mx-2 text-center fs-16"
                                                                    placeholder="Enter quantity"
                                                                    {{-- value="{{ $detailedProduct->min_qty }}"
                                                                    min="{{ $detailedProduct->min_qty }}"
                                                                    max="{{ $detailedProduct->max_qty }}" --}}
                                                                    lang="en" onkeyup="handleQuantityChange()"> -->

                                                                <input type="number"
                                                                    name="quantity"
                                                                    id="quantity"
                                                                    class="form-control col border mx-2 text-center fs-16"
                                                                    placeholder="Enter Qty"
                                                                    value="{{ $detailedProduct->min_qty }}"
                                                                    lang="en"
                                                                    onkeyup="handleQuantityChange()"
                                                                    data-discount-qty="{{ home_bulk_qty($detailedProduct)['bulk_qty'] }}"
                                                                    data-bulk-unit="{{ (float) home_bulk_discounted_price($detailedProduct, true, Auth::user()->id)['price'] }}"
                                                                    data-retail-default="{{ (float) home_discounted_price($detailedProduct, true, Auth::user()->id)['price'] }}"
                                                                    data-price-url="{{ route('products.price', $detailedProduct->id) }}"
                                                                    data-user-id="{{ (int) Auth::user()->id }}">

                                                            {{-- <button class="btn  col-auto btn-icon btn-sm btn-circle btn-light"
                                                                type="button" data-type="plus" data-count="1" data-field="quantity">
                                                                <i class="las la-plus"></i>
                                                            </button> --}}


                                                        </div>
                                                        {{-- @php
                                                            $qty = 0;
                                                            foreach ($detailedProduct->stocks as $key => $stock) {
                                                                $qty += $stock->qty;
                                                            }

                                                        @endphp
                                                        <div  class="avialable-amount opacity-60">
                                                            @if ($detailedProduct->stock_visibility_state == 'quantity')
                                                                (<span id="available-quantity">{{ $qty }}</span>
                                                                {{ translate('available') }})
                                                            @elseif($detailedProduct->stock_visibility_state == 'text' && $qty >= 1)
                                                                (<span id="available-quantity">{{ translate('In Stock') }}</span>)
                                                            @endif
                                                        </div> --}}
                                                         @php
                                                            $qty = 0;
                                                            foreach ($detailedProduct->stocks as $key => $stock) {
                                                                $qty += $stock->qty;
                                                            }
                                                            // Logging the stock visibility state for debugging
                                                            Log::info('Stock Visibility State:', ['state' => $detailedProduct->stock_visibility_state]);
                                                            Log::info('Total Quantity:', ['quantity' => $qty]);
                                                        @endphp
                                                        <?php /*<div  class="available-amount opacity-60">
                                                            @if ($detailedProduct->stock_visibility_state == 'quantity')
                                                                (<span id="available-quantity">{{ $qty }}</span> {{ translate('available') }})
                                                            @elseif($detailedProduct->stock_visibility_state == 'text' && $qty >= 1)
                                                                (<span id="available-quantity">{{ translate('In Stock') }}</span>)
                                                            @else
                                                                <span id="available-quantity">{{ translate('Out of Stock') }}</span>
                                                            @endif
                                                        </div> */ ?>
                                                    </div>
                                                </div>
                                            </div>

                                        <!--   <div class="row no-gutters pb-3 my-3" id="variant_listing_div">
                                <div class="col-12">
                                    <h5 style="font-size: 16px;"class="fw-600 opacity-50">{{ translate('Available Variants') }}</h5>
                                    <div class="variant-dropdown">
                                        <select style="width:350px;" class="form-control" id="variant_select">
                                            <option value="">{{ translate('Select a Variant') }}</option>
                                            @if (!empty($variants))
                                                @foreach ($variants as $variant)
                                                    <option value="{{ route('product', ['slug' => $variant['product_slug']]) }}"
                                                        @if ($selectedVariant && $variant['product_slug'] === $selectedVariant['product_slug']) selected @endif>
                                                        @if (!empty($variant['variation_1']) && !empty($variant['value_1']))
                                                            {{ $variant['variation_1'] }}: {{ $variant['value_1'] }}
                                                        @endif
                                                        @if (!empty($variant['variation_2']) && !empty($variant['value_2']))
                                                            , {{ $variant['variation_2'] }}: {{ $variant['value_2'] }}
                                                        @endif
                                                        @if (!empty($variant['variation_3']) && !empty($variant['value_3']))
                                                            , {{ $variant['variation_3'] }}: {{ $variant['value_3'] }}
                                                        @endif
                                                        @if (!empty($variant['variation_4']) && !empty($variant['value_4']))
                                                            , {{ $variant['variation_4'] }}: {{ $variant['value_4'] }}
                                                        @endif
                                                    </option>
                                                @endforeach
                                            @else
                                                <option value="">{{ translate('No variants available') }}</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>
                            </div> -->

@if (!empty($attributeVariations))
<input type="hidden" value="{{$detailedProduct->variation_parent_part_no}}"name="variation_parent_part_no" id="variation_parent_part_no">
<div class="row no-gutters pb-3 my-3" id="variant_listing_div">
    <div class="col-12">
        @if (!empty($attributeVariations))
            @foreach ($attributeVariations as $attribute)
                <div class="variant-dropdown mb-3">
                    <label class="fw-400 opacity-50">{{ translate('Select') }} {{ $attribute['attribute_name'] }}</label>
                    <select
                        style="width:350px;"
                        class="form-control attribute-select"
                        data-attribute-id="{{ $attribute['attribute_id'] }}"
                        data-attribute-name="{{ $attribute['attribute_name'] }}">
                        <option value="">{{ translate('Select') }} {{ $attribute['attribute_name'] }}</option>
                        @foreach ($attribute['values'] as $id => $value)
                            <option value="{{ $id }}"
                                @if (!empty($selectedValues[$attribute['attribute_id']]) && $selectedValues[$attribute['attribute_id']] == $id)
                                    selected
                                @endif>
                                {{ $value }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        @else
            <p>{{ translate('No attributes available') }}</p>
        @endif
    </div>
</div>
 @endif
                                     

                                            <div class="row no-gutters pb-3 d-none my-3" id="chosen_price_div">
                                                <div class="col-6 col-md-4">
                                                    <div class="opacity-50 mt-1">{{ translate('Total Price') }}:</div>
                                                </div>
                                                <div class="col-6 col-md-8">
                                                    <div class="product-price">
                                                        <strong id="chosen_price" class="h4 fw-600 text-primary">
                                                            @php /* {{ home_discounted_price($detailedProduct)['price'] }} */ @endphp
                                                            @php
                                                                $product_price = home_discounted_price($detailedProduct, false, Auth::user()->id)['price'];
                                                                $show_total = $product_price * $detailedProduct->min_qty;
                                                            @endphp
                                                            {{ price_less_than_50($show_total, true) }}
                                                        </strong>
                                                    </div>
                                                </div>
                                            </div>
                                    </div>
                                    @else
                                    <a class="my-2 d-inline-block btn btn-sm btn-primary" href="{{ route('user.registration') }}">Register to check prices</a>
                                    @endif
                                </div>

                            </form>

                            <div class="mt-3">
                                @if ($detailedProduct->external_link != null)
                                    <a type="button" class="btn btn-primary buy-now fw-600"
                                        href="{{ $detailedProduct->external_link }}">
                                        <i class="la la-share"></i> {{ translate($detailedProduct->external_link_btn) }}
                                    </a>
                                @else
                                    @if (Auth::check() && Auth::user()->warehouse_id)
                                    <button type="button" data-type="is_piece" class="btn btn-soft-primary mr-2 add-to-cart fw-600" id="add-to-cart"
                                        onclick="addToCart()">
                                        <i class="las la-shopping-bag"></i> {{ translate('Add to cart') }}
                                    </button>
                                    <button type="button" id="buyInstant" class="btn btn-primary buy-now fw-600 mr-2" onclick="buyNow()">
                                        <i class="la la-shopping-cart"></i> {{ translate('Buy Now') }}
                                    </button>
                                    @endif
                                    @if ($detailedProduct->generic_name)
                                    @if ($detailedProduct->category->categoryGroup->name == 'Spare Parts')
                                    <a href="{{ route('search', ['generic_name' => $detailedProduct->generic_name, 'pid' => $detailedProduct->id]) }}" class="btn btn-success fw-600 mt-2 mt-md-0 mt-lg-2 mt-xl-0">
                                        <i class="la la-plus"></i> {{ translate('Buy Machinery for this Product') }}
                                    </a>
                                    @else
                                    <a href="{{ route('search', ['generic_name' => $detailedProduct->generic_name, 'pid' => $detailedProduct->id]) }}" class="btn btn-success fw-600 mt-2 mt-md-0 mt-lg-2 mt-xl-0">
                                        <i class="la la-plus"></i> {{ translate('Buy Spares for this Product') }}
                                    </a>
                                    @endif
                                    @endif
                                @endif
                                <button type="button" class="btn btn-secondary out-of-stock fw-600 d-none" disabled>
                                    <i class="la la-cart-arrow-down"></i> {{ translate('Out of Stock') }}
                                </button>
                            </div>
                            <div class="d-table width-100 mt-3">
                                <div class="d-table-cell">
                                    <!-- Add to wishlist button -->
                                    {{-- <button type="button" class="btn pl-0 btn-link fw-600"
                                        onclick="addToWishList({{ $detailedProduct->id }})">
                                        {{ translate('Add to wishlist') }}
                                    </button> --}}
                                    <!-- Add to compare button -->
                                    {{-- <button type="button" class="btn btn-link btn-icon-left fw-600"
                                        onclick="addToCompare({{ $detailedProduct->id }})">
                                        {{ translate('Add to compare') }}
                                    </button> --}}
                                    @if (Auth::check() &&
                                            addon_is_activated('affiliate_system') &&
                                            (\App\Models\AffiliateOption::where('type', 'product_sharing')->first()->status ||
                                                \App\Models\AffiliateOption::where('type', 'category_wise_affiliate')->first()->status) &&
                                            Auth::user()->affiliate_user != null &&
                                            Auth::user()->affiliate_user->status)
                                        @php
                                            if (Auth::check()) {
                                                if (Auth::user()->referral_code == null) {
                                                    Auth::user()->referral_code = substr(Auth::user()->id . Str::random(10), 0, 10);
                                                    Auth::user()->save();
                                                }
                                                $referral_code = Auth::user()->referral_code;
                                                $referral_code_url = URL::to('/product') . '/' . $detailedProduct->slug . "?product_referral_code=$referral_code";
                                            }
                                        @endphp
                                        <div>
                                            <button type=button id="ref-cpurl-btn" class="btn btn-sm btn-secondary"
                                                data-attrcpy="{{ translate('Copied') }}"
                                                onclick="CopyToClipboard(this)"
                                                data-url="{{ $referral_code_url }}">{{ translate('Copy the Promote Link') }}</button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <a style="display:none;" aria-label="Chat on WhatsApp" class="whatsapp-inquiry btn btn-success mt-3" href="https://wa.me/+917439765853?text={{ urlencode('I have an inquiry for the product ' . $detailedProduct->getTranslation('name') . ' ' . route('product', $detailedProduct->slug)) }}">
                                <i class="lab la-whatsapp"></i>
                                <div>Sales Executive<br><h5 class="mb-0">Need Help? Chat via WhatsApp</h5></div>
                            </a>

                            @php
                                $refund_sticker = get_setting('refund_sticker');
                            @endphp
                            @if (addon_is_activated('refund_request'))
                                <div class="row no-gutters mt-3">
                                    <div class="col-2">
                                        <div class="opacity-50 mt-2">{{ translate('Refund') }}:</div>
                                    </div>
                                    <div class="col-10">
                                        <a href="{{ route('returnpolicy') }}" target="_blank">
                                        @php
                                            // Fetch file_name for the refund sticker (assuming $refund_sticker contains the ID of the upload)
                                            $refund_sticker_file = \App\Models\Upload::where('id', $refund_sticker)->value('file_name');
                                            $refund_sticker_path = $refund_sticker_file
                                                        ? url('public/' . $refund_sticker_file)
                                                        : url('public/assets/img/refund-sticker.jpg');
                                        @endphp
                                        <img src="{{ $refund_sticker_path }}" height="36">


                                            <!-- @if ($refund_sticker != null)
                                                <img src="{{ uploaded_asset($refund_sticker) }}" height="36">
                                            @else
                                                <img src="{{ static_asset('assets/img/refund-sticker.jpg') }}"
                                                    height="36">
                                            @endif -->
                                        </a>
                                        <a href="{{ route('returnpolicy') }}" class="ml-2"
                                            target="_blank">{{ translate('View Policy') }}</a>
                                    </div>
                                </div>
                            @endif
                            <div class="row no-gutters mt-4">
                                <div class="col-sm-2">
                                    <div class="opacity-50 my-2">{{ translate('Share') }}:</div>
                                </div>
                                <div class="col-sm-10">
                                    <div class="aiz-share"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-4">
        <div class="container-fluid">
            <div class="row gutters-10">
                <div class="col-xl-3 order-1 order-xl-0">
                    <div class="bg-white rounded shadow-sm mb-3">
                        <div class="p-3 border-bottom fs-16 fw-600">
                            {{ translate('Top Selling Products') }}
                        </div>
                        <div class="p-3">
                            <ul class="list-group list-group-flush">
                                @foreach (filter_products(
        \App\Models\Product::where('user_id', $detailedProduct->user_id)->orderBy('num_of_sale', 'desc'),
    )->limit(6)->get() as $key => $top_product)
                                    <li class="py-3 px-0 list-group-item border-light">
                                        <div class="row gutters-10 align-items-center">
                                            <div class="col-5">
                                                <a href="{{ route('product', $top_product->slug) }}"
                                                    class="d-block text-reset">
                                                    <!-- <img class="img-fit lazyload h-xxl-110px h-xl-80px h-120px"
                                                        src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                                        data-src="{{ uploaded_asset($top_product->thumbnail_img) }}"
                                                        alt="{{ $top_product->getTranslation('name') }}"
                                                        onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                                                        @php
                                                        // Fetch file_name for the top product thumbnail image (assuming $top_product->thumbnail_img contains the ID of the upload)
                                                        $top_product_thumbnail = \App\Models\Upload::where('id', $top_product->thumbnail_img)->value('file_name');
                                                        $top_product_thumbnail_path = $top_product_thumbnail
                                                                    ? url('public/' . $top_product_thumbnail)
                                                                    : url('public/assets/img/placeholder.jpg');
                                                        @endphp

                                                        <img class="img-fit lazyload h-xxl-110px h-xl-80px h-120px"
                                                            src="{{ url('public/assets/img/placeholder.jpg') }}"
                                                            data-src="{{ $top_product_thumbnail_path }}"
                                                            alt="{{ $top_product->getTranslation('name') }}"
                                                            onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">


                                                </a>
                                            </div>
                                            <div class="col-7 text-left">
                                                <h4 class="fs-13 text-truncate-2">
                                                    <a href="{{ route('product', $top_product->slug) }}"
                                                        class="d-block text-reset">{{ $top_product->getTranslation('name') }}</a>
                                                </h4>
                                                 @if(DB::table('products_api')->where('part_no', $top_product->part_no)->where('closing_stock', '>', 0)->exists())
                                                    <img src="{{ asset('public/uploads/fast_dispatch.jpg') }}" alt="Fast Delivery" 
                                                         style="width: 73px; height: 21px;  border-radius: 3px; ">
                                                    @endif
                                                <div class="rating rating-sm mt-1">
                                                    {{ renderStarRating($top_product->rating) }}
                                                </div>
                                                <div class="mt-2">
                                                    @if (Auth::check())
                                                        @if (Auth::user()->warehouse_id)
                                                        <span class="fw-700 text-primary">{{ home_discounted_base_price($top_product) }}</span>
                                                        @else
                                                        <small><a href="{{ route('user.registration') }}">Complete profile to check prices.</a></small>
                                                        @endif
                                                    @else
                                                        <small><a href="{{ route('user.registration') }}" class="btn btn-sm btn-primary">Register to check prices</a></small>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xl-9 order-0 order-xl-1">
                    @if (
                        ($detailedProduct->category->categoryGroup->name != 'Spare Parts' ||
                            $detailedProduct->category->categoryGroup->name != 'Accessories') &&
                            (count($acc_spa['acc']) || count($acc_spa['spa'])))
                    <div class="bg-white mb-3 shadow-sm rounded">
                        <div class="nav border-bottom aiz-nav-tabs">
                            @if (count($acc_spa['acc']))
                            <a href="#cat-accessories" data-toggle="tab" class="p-3 fs-16 fw-600 moreparts text-reset active show">Accessories</a>
                            @endif
                            @if (count($acc_spa['spa']))
                            <a href="#cat-spares" data-toggle="tab" class="p-3 fs-16 fw-600 moreparts text-reset @unless (count($acc_spa['acc'])) active show @endunless">Spares</a>
                            @endif
                        </div>
                        <div class="tab-content pt-0">
                            @if (count($acc_spa['acc']))
                            <div class="tab-pane fade active show text-center" id="cat-accessories">
                                <div class="px-3 pt-3 pb-2">
                                    <div class="aiz-carousel half-outside-arrow" data-items="5" data-xl-items="3"
                                data-lg-items="4" data-md-items="3" data-sm-items="2" data-xs-items="2"
                                data-arrows='true' data-infinite='true'>
                                    @foreach ($acc_spa['acc'] as $key => $cat_prod)
                                        <div class="carousel-box">
                                            <div class="aiz-card-box border border-light rounded hov-shadow-md my-2 has-transition">
                                                <div class="">
                                                    <a href="{{ route('product', $cat_prod->slug) }}"
                                                        class="d-block">
                                                        @php
                                                        // Fetch file_name for the category product thumbnail image (assuming $cat_prod->thumbnail_img contains the ID of the upload)
                                                        $cat_prod_thumbnail = \App\Models\Upload::where('id', $cat_prod->thumbnail_img)->value('file_name');
                                                        $cat_prod_thumbnail_path = $cat_prod_thumbnail
                                                                    ? url('public/' . $cat_prod_thumbnail)
                                                                    : url('public/assets/img/placeholder.jpg');
                                                        @endphp
                                                        <img class="img-fit lazyload mx-auto h-140px h-md-210px"
                                                            src="{{ url('public/assets/img/placeholder.jpg') }}"
                                                            data-src="{{ $cat_prod_thumbnail_path }}"
                                                            alt="{{ $cat_prod->getTranslation('name') }}"
                                                            onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">


                                                        <!-- <img class="img-fit lazyload mx-auto h-140px h-md-210px"
                                                            src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                                            data-src="{{ uploaded_asset($cat_prod->thumbnail_img) }}"
                                                            alt="{{ $cat_prod->getTranslation('name') }}"
                                                            onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                                                    </a>
                                                </div>
                                                <div class="p-md-3 p-2 text-left">
                                                    <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0 h-35px">
                                                        <a href="{{ route('product', $cat_prod->slug) }}"
                                                            class="d-block text-reset">{{ $cat_prod->getTranslation('name') }}</a>
                                                    </h3>
                                                    <div class="rating rating-sm mt-1">
                                                        {{ renderStarRating($cat_prod->rating) }}
                                                    </div>
                                                    <div class="fs-15">
                                                        @if (Auth::check())
                                                            @if (Auth::user()->warehouse_id)
                                                            @if (home_base_price($cat_prod) != home_discounted_base_price($cat_prod))
                                                                <del class="fw-600 opacity-50 mr-1">{{ home_base_price($cat_prod) }}</del>
                                                            @endif
                                                            <span class="fw-700 text-primary">{{ home_discounted_base_price($cat_prod) }}</span>
                                                            @else
                                                            <small><a href="{{ route('user.registration') }}">Complete profile to check prices.</a></small>
                                                            @endif
                                                        @else
                                                            <small><a href="{{ route('user.registration') }}" class="btn btn-sm btn-primary">Register to check prices</a></small>
                                                        @endif
                                                    </div>
                                                    @if (addon_is_activated('club_point'))
                                                        <div
                                                            class="rounded px-2 mt-2 bg-soft-primary border-soft-primary border">
                                                            {{ translate('Club Point') }}:
                                                            <span
                                                                class="fw-700 float-right">{{ $cat_prod->earn_point }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                    </div>
                                </div>
                                <a href="{{ route('search', ['category_id' => $detailedProduct->category_id, 'type' => 'accessories']) }}" class="btn btn-sm btn-primary mb-3">View All Accessories</a>
                            </div>
                            @endif
                            @if (count($acc_spa['spa']))
                            <div class="tab-pane fade @unless (count($acc_spa['acc'])) active show @endunless text-center" id="cat-spares">
                                <div class="px-3 pt-3 pb-2">
                                    <div class="aiz-carousel half-outside-arrow" data-items="5" data-xl-items="3"
                                data-lg-items="4" data-md-items="3" data-sm-items="2" data-xs-items="2"
                                data-arrows='true' data-infinite='true'>
                                    @foreach ($acc_spa['spa'] as $key => $cat_prod)
                                        <div class="carousel-box">
                                            <div
                                                class="aiz-card-box border border-light rounded hov-shadow-md my-2 has-transition">
                                                <div class="">
                                                    <a href="{{ route('product', $cat_prod->slug) }}"
                                                        class="d-block">
                                                        @php
                                                        // Fetch file_name for the category product thumbnail image (assuming $cat_prod->thumbnail_img contains the ID of the upload)
                                                        $cat_prod_thumbnail = \App\Models\Upload::where('id', $cat_prod->thumbnail_img)->value('file_name');
                                                        $cat_prod_thumbnail_path = $cat_prod_thumbnail
                                                                    ? url('public/' . $cat_prod_thumbnail)
                                                                    : url('public/assets/img/placeholder.jpg');
                                                        @endphp
                                                        
                                                        <img class="img-fit lazyload mx-auto h-140px h-md-210px"
                                                            src="{{ url('public/assets/img/placeholder.jpg') }}"
                                                            data-src="{{ $cat_prod_thumbnail_path }}"
                                                            alt="{{ $cat_prod->getTranslation('name') }}"
                                                            onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">

                                                        <!-- <img class="img-fit lazyload mx-auto h-140px h-md-210px"
                                                            src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                                            data-src="{{ uploaded_asset($cat_prod->thumbnail_img) }}"
                                                            alt="{{ $cat_prod->getTranslation('name') }}"
                                                            onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                                                    </a>
                                                </div>
                                                <div class="p-md-3 p-2 text-left">
                                                    <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0 h-35px">
                                                        <a href="{{ route('product', $cat_prod->slug) }}"
                                                            class="d-block text-reset">{{ $cat_prod->getTranslation('name') }}</a>
                                                    </h3>
                                                    <div class="rating rating-sm mt-1">
                                                        {{ renderStarRating($cat_prod->rating) }}
                                                    </div>
                                                    <div class="fs-15">
                                                        @if (Auth::check())
                                                            @if (Auth::user()->warehouse_id)
                                                            @if (home_base_price($cat_prod) != home_discounted_base_price($cat_prod))
                                                                <del class="fw-600 opacity-50 mr-1">{{ home_base_price($cat_prod) }}</del>
                                                            @endif
                                                            <span class="fw-700 text-primary">{{ home_discounted_base_price($cat_prod) }}</span>
                                                            @else
                                                            <small><a href="{{ route('user.registration') }}">Complete profile to check prices.</a></small>
                                                            @endif
                                                        @else
                                                            <small><a href="{{ route('user.registration') }}" class="btn btn-sm btn-primary">Register to check prices</a></small>
                                                        @endif
                                                    </div>
                                                    @if (addon_is_activated('club_point'))
                                                        <div
                                                            class="rounded px-2 mt-2 bg-soft-primary border-soft-primary border">
                                                            {{ translate('Club Point') }}:
                                                            <span
                                                                class="fw-700 float-right">{{ $cat_prod->earn_point }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                    </div>
                                </div>
                                <a href="{{ route('search', ['category_id' => $detailedProduct->category_id, 'type' => 'spare parts']) }}" class="btn btn-sm btn-primary mb-3">View All Spare Parts</a>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                    <div class="bg-white mb-3 shadow-sm rounded">
                        <div class="nav border-bottom aiz-nav-tabs">
                            <a href="#tab_default_1" data-toggle="tab"
                                class="p-3 fs-16 fw-600 text-reset active show">{{ translate('Description') }}</a>
                            @if ($detailedProduct->video_link != null)
                                <a href="#tab_default_2" data-toggle="tab"
                                    class="p-3 fs-16 fw-600 text-reset">{{ translate('Video') }}</a>
                            @endif
                            @if ($detailedProduct->pdf != null)
                                <a href="#tab_default_3" data-toggle="tab"
                                    class="p-3 fs-16 fw-600 text-reset">{{ translate('Downloads') }}</a>
                            @endif
                            <a href="#tab_default_4" data-toggle="tab"
                                class="p-3 fs-16 fw-600 text-reset">{{ translate('Reviews') }}</a>

                                 <a href="#tab_default_6" data-toggle="tab"
                                class="p-3 fs-16 fw-600 text-reset">{{ translate('Specifications') }}</a>

                            <a href="#tab_default_5" data-toggle="tab"
                                class="p-3 fs-16 fw-600 text-reset">{{ translate('Others') }}</a>
                        </div>

                        <div class="tab-content pt-0">
                            <div class="tab-pane fade active show" id="tab_default_1">
                                <div class="p-4">
                                    <div class="mw-100 overflow-auto text-left aiz-editor-data">
                                        <?php echo $detailedProduct->getTranslation('description'); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="tab_default_2">
                                <div class="p-4">
                                    <div class="embed-responsive embed-responsive-16by9">
                                        @if ($detailedProduct->video_provider == 'youtube' && isset(explode('=', $detailedProduct->video_link)[1]))
                                            <iframe class="embed-responsive-item"
                                                src="https://www.youtube.com/embed/{{ get_url_params($detailedProduct->video_link, 'v') }}"></iframe>
                                        @elseif ($detailedProduct->video_provider == 'dailymotion' && isset(explode('video/', $detailedProduct->video_link)[1]))
                                            <iframe class="embed-responsive-item"
                                                src="https://www.dailymotion.com/embed/video/{{ explode('video/', $detailedProduct->video_link)[1] }}"></iframe>
                                        @elseif ($detailedProduct->video_provider == 'vimeo' && isset(explode('vimeo.com/', $detailedProduct->video_link)[1]))
                                            <iframe
                                                src="https://player.vimeo.com/video/{{ explode('vimeo.com/', $detailedProduct->video_link)[1] }}"
                                                width="500" height="281" frameborder="0" webkitallowfullscreen
                                                mozallowfullscreen allowfullscreen></iframe>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tab_default_3">
                                <div class="p-4 text-center ">
                                @php
                                // Fetch file_name for the product PDF (assuming $detailedProduct->pdf contains the ID of the upload)
                                $product_pdf = \App\Models\Upload::where('id', $detailedProduct->pdf)->value('file_name');
                                $product_pdf_path = $product_pdf
                                            ? url('public/' . $product_pdf)
                                            : '#';  // If no PDF is available, use a fallback link (e.g., '#')
                                @endphp

                                    <!-- <a href="{{ uploaded_asset($detailedProduct->pdf) }}"
                                        class="btn btn-primary">{{ translate('Download') }}</a> -->
                                        <a href="{{ $product_pdf_path }}" class="btn btn-primary">{{ translate('Download') }}</a>

                                </div>
                            </div>
                            <div class="tab-pane fade" id="tab_default_4">
                                <div class="p-4">
                                    <ul class="list-group list-group-flush">
                                        @foreach ($detailedProduct->reviews as $key => $review)
                                            <li class="media list-group-item d-flex">
                                                <span class="avatar avatar-md mr-3">
                                                @php
                                                // Fetch file_name for the user avatar (assuming $review->user->avatar_original contains the ID of the upload)
                                                $user_avatar = $review->user && $review->user->avatar_original != null 
                                                                ? \App\Models\Upload::where('id', $review->user->avatar_original)->value('file_name')
                                                                : null;

                                                $user_avatar_path = $user_avatar
                                                            ? url('public/' . $user_avatar)
                                                            : url('public/assets/img/placeholder.jpg');
                                                @endphp
                                                <img class="lazyload"
                                                    src="{{ url('public/assets/img/placeholder.jpg') }}"
                                                    onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';"
                                                    data-src="{{ $user_avatar_path }}">

                                                    <!-- <img class="lazyload"
                                                        src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                                        onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"
                                                        @if ($review->user && $review->user->avatar_original != null) data-src="{{ uploaded_asset($review->user->avatar_original) }}"
                                                    @else
                                                        data-src="{{ static_asset('assets/img/placeholder.jpg') }}" @endif> -->
                                                </span>
                                                <div class="media-body text-left">
                                                    <div class="d-flex justify-content-between">
                                                        <h3 class="fs-15 fw-600 mb-0">{{ $review->user ? $review->user->name : 'Anonymous User' }}
                                                        </h3>
                                                        <span class="rating rating-sm">
                                                            @for ($i = 0; $i < $review->rating; $i++)
                                                                <i class="las la-star active"></i>
                                                            @endfor
                                                            @for ($i = 0; $i < 5 - $review->rating; $i++)
                                                                <i class="las la-star"></i>
                                                            @endfor
                                                        </span>
                                                    </div>
                                                    <div class="opacity-60 mb-2">
                                                        {{ date('d-m-Y', strtotime($review->created_at)) }}</div>
                                                    <p class="comment-text">
                                                        {{ $review->comment }}
                                                    </p>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>

                                    @if (count($detailedProduct->reviews) <= 0)
                                        <div class="text-center fs-18 opacity-70">
                                            {{ translate('There have been no reviews for this product yet.') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <!-- others tab -->
                            <div class="tab-pane fade" id="tab_default_5">
                                <div class="p-4">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tbody>
                                                <!-- HSN Code Row -->
                                                <tr>
                                                    <th style="color: #6c757d;">HSN Code</th>
                                                    <td>{{ $detailedProduct->hsncode ?? 'Not Available' }}</td>
                                                </tr>

                                                <!-- GST Rate Row -->
                                                <tr>
                                                    <th style="color: #6c757d;">GST Rate</th>
                                                    <td>{{ $detailedProduct->tax ?? 'Not Available' }}%</td>
                                                </tr>

                                                <!-- Group Row -->
                                                <tr>
                                                    <th style="color: #6c757d;">Group</th>
                                                    <td>{{ $detailedProduct->category->categoryGroup->name ?? 'Not Available' }}</td>
                                                </tr>

                                                <!-- Category Row -->
                                                <tr>
                                                    <th style="color: #6c757d;">Category</th>
                                                    <td>{{ $detailedProduct->category->name ?? 'Not Available' }}</td>
                                                </tr>

                                                <!-- Imported By Row -->
                                                <tr>
                                                    <th style="color: #6c757d;">Imported By</th>
                                                    <td>{{ $detailedProduct->imported_by ?? 'Not Available' }}</td>
                                                </tr>

                                                <!-- Contact By Row -->
                                                <tr>
                                                    <th style="color: #6c757d;">Contact By</th>
                                                    <td>{{ $detailedProduct->imported_by ?? 'Not Available' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>


                            <!-- other tab end -->


                              <!-- specifications tab start-->
                            <div class="tab-pane fade" id="tab_default_6">
                                <div class="">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <tbody>
                                                
                                                <!-- Dynamic Attributes Row -->
                                                @if ($detailedProduct->attributes && count(json_decode($detailedProduct->attributes, true)) > 0)
                                                    @php
                                                        $attributeIds = json_decode($detailedProduct->attributes, true);
                                                        $attributes = DB::table('attribute_values')
                                                            ->join('attributes', 'attribute_values.attribute_id', '=', 'attributes.id')
                                                            ->whereIn('attribute_values.id', $attributeIds)
                                                            ->select('attributes.name as attribute_name', 'attribute_values.value as attribute_value')
                                                            ->get();
                                                    @endphp

                                                    @foreach ($attributes as $attribute)
                                                     @if (!empty($attribute->attribute_value))
                                                        <tr>
                                                            <th style="color: #6c757d;">{{ $attribute->attribute_name }}</th>
                                                            <td style="color: #555; opacity:0.8;">{{ Str::title($attribute->attribute_value) }}</td>
                                                        </tr>
                                                     @endif
                                                    @endforeach
                                                @else
                                                    <!-- No Attributes Available -->
                                                    <tr>
                                                        <td  colspan="2" class="text-center p-4">
                                                            <div class="text-center fs-18 opacity-40">
                                                                {{ translate('There are no specification available for this product.') }}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endif

                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- specifications tab end -->



                        </div>
                    </div>
                    @php $related_prods = filter_products(\App\Models\Product::where('category_id', $detailedProduct->category_id)->where('id', '!=', $detailedProduct->id))->limit(10)->get(); @endphp
                    @if (count($related_prods))
                    <div class="bg-white rounded shadow-sm mb-3">
                        <div class="border-bottom p-3">
                            <h3 class="fs-16 fw-600 mb-0">
                                <span class="mr-4">{{ translate('Related products') }}</span>
                            </h3>
                        </div>
                        <div class="p-3">
                            <div class="aiz-carousel half-outside-arrow" data-items="5" data-xl-items="3"
                                data-lg-items="4" data-md-items="3" data-sm-items="2" data-xs-items="2"
                                data-arrows='true' data-infinite='true'>
                                @foreach ($related_prods as $key => $related_product)
                                    <div class="carousel-box">
                                        <div
                                            class="aiz-card-box border border-light rounded hov-shadow-md my-2 has-transition">
                                            <div class="">
                                                <a href="{{ route('product', $related_product->slug) }}"
                                                    class="d-block">
                                                    @php
                                                    // Fetch file_name for the related product thumbnail image (assuming $related_product->thumbnail_img contains the ID of the upload)
                                                    $related_product_thumbnail = \App\Models\Upload::where('id', $related_product->thumbnail_img)->value('file_name');
                                                    $related_product_thumbnail_path = $related_product_thumbnail
                                                                ? url('public/' . $related_product_thumbnail)
                                                                : url('public/assets/img/placeholder.jpg');
                                                    @endphp

                                                    <img class="img-fit lazyload mx-auto h-140px h-md-210px"
                                                        src="{{ url('public/assets/img/placeholder.jpg') }}"
                                                        data-src="{{ $related_product_thumbnail_path }}"
                                                        alt="{{ $related_product->getTranslation('name') }}"
                                                        onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">


                                                    <!-- <img class="img-fit lazyload mx-auto h-140px h-md-210px"
                                                        src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                                        data-src="{{ uploaded_asset($related_product->thumbnail_img) }}"
                                                        alt="{{ $related_product->getTranslation('name') }}"
                                                        onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                                                </a>
                                            </div>
                                            <div class="p-md-3 p-2 text-left">
                                                <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0 h-35px">
                                                    <a href="{{ route('product', $related_product->slug) }}"
                                                        class="d-block text-reset">{{ $related_product->getTranslation('name') }}</a>
                                                </h3>
                                                @if(DB::table('products_api')->where('part_no', $related_product->part_no)->where('closing_stock', '>', 0)->exists())
                                                    <img src="{{ asset('public/uploads/fast_dispatch.jpg') }}" alt="Fast Delivery" 
                                                         style="width: 73px; height: 21px;  border-radius: 3px; margin-top:6px;">
                                                    @endif
                                                <div class="rating rating-sm mt-1">
                                                    {{ renderStarRating($related_product->rating) }}
                                                </div>
                                                <div class="fs-15">
                                                    @if (Auth::check())
                                                        @if (Auth::user()->warehouse_id)
                                                        @if (home_base_price($related_product) != home_discounted_base_price($related_product))
                                                            <del class="fw-600 opacity-50 mr-1">{{ home_base_price($related_product) }}</del>
                                                        @endif
                                                        <span class="fw-700 text-primary">{{ home_discounted_base_price($related_product) }}</span>
                                                        @else
                                                        <small><a href="{{ route('user.registration') }}">Complete profile to check prices.</a></small>
                                                        @endif
                                                    @else
                                                        <small><a href="{{ route('user.registration') }}" class="btn btn-sm btn-primary">Register to check prices</a></small>
                                                    @endif
                                                </div>
                                                @if (addon_is_activated('club_point'))
                                                    <div
                                                        class="rounded px-2 mt-2 bg-soft-primary border-soft-primary border">
                                                        {{ translate('Club Point') }}:
                                                        <span
                                                            class="fw-700 float-right">{{ $related_product->earn_point }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                    @if (session('recently_viewed_prods') && count(session('recently_viewed_prods')) > 4)
                    <div class="bg-white rounded shadow-sm mt-3">
                        <div class="border-bottom p-3">
                            <h3 class="fs-16 fw-600 mb-0">
                                <span class="mr-4">{{ translate('Recently Viewed') }}</span>
                            </h3>
                        </div>
                        <div class="p-3">
                            <div class="aiz-carousel half-outside-arrow" data-items="5" data-xl-items="3"
                                data-lg-items="4" data-md-items="3" data-sm-items="2" data-xs-items="2"
                                data-arrows='true' data-infinite='true'>
                                @foreach (array_reverse(session('recently_viewed_prods')) as $key => $product)
                                    <div class="carousel-box">
                                        <div
                                            class="aiz-card-box border border-light rounded hov-shadow-md my-2 has-transition">
                                            <div class="">
                                                <a href="{{ route('product', $product->slug) }}"
                                                    class="d-block">
                                                    @php
                                                    // Fetch file_name for the product thumbnail image (assuming $product->thumbnail_img contains the ID of the upload)
                                                    $product_thumbnail = \App\Models\Upload::where('id', $product->thumbnail_img)->value('file_name');
                                                    $product_thumbnail_path = $product_thumbnail
                                                                ? url('public/' . $product_thumbnail)
                                                                : url('public/assets/img/placeholder.jpg');
                                                    @endphp
                                                    <img class="img-fit lazyload mx-auto h-140px h-md-210px"
                                                        src="{{ url('public/assets/img/placeholder.jpg') }}"
                                                        data-src="{{ $product_thumbnail_path }}"
                                                        alt="{{ $product->getTranslation('name') }}"
                                                        onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">


                                                    <!-- <img class="img-fit lazyload mx-auto h-140px h-md-210px"
                                                        src="{{ static_asset('assets/img/placeholder.jpg') }}"
                                                        data-src="{{ uploaded_asset($product->thumbnail_img) }}"
                                                        alt="{{ $product->getTranslation('name') }}"
                                                        onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                                                </a>
                                            </div>
                                            <div class="p-md-3 p-2 text-left">
                                                <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0 h-35px">
                                                    <a href="{{ route('product', $product->slug) }}"
                                                        class="d-block text-reset">{{ $product->getTranslation('name') }}</a>
                                                </h3>

                                                @if(DB::table('products_api')->where('part_no', $product->part_no)->where('closing_stock', '>', 0)->exists())
                                                    <img src="{{ asset('public/uploads/fast_dispatch.jpg') }}" alt="Fast Delivery" 
                                                         style="width: 73px; height: 21px;  border-radius: 3px; margin-top:6px;">
                                                    @endif
                                                <div class="rating rating-sm mt-1">
                                                    {{ renderStarRating($product->rating) }}
                                                </div>
                                                <div class="fs-15">
                                                    @if (Auth::check())
                                                        @if (Auth::user()->warehouse_id)
                                                        @if (home_base_price($product) != home_discounted_base_price($product))
                                                            <del class="fw-600 opacity-50 mr-1">{{ home_base_price($product) }}</del>
                                                        @endif
                                                        <span class="fw-700 text-primary">{{ home_discounted_base_price($product) }}</span>
                                                        @else
                                                        <small><a href="{{ route('user.registration') }}">Complete profile to check prices.</a></small>
                                                        @endif
                                                    @else
                                                        <small><a href="{{ route('user.registration') }}" class="btn btn-sm btn-primary">Register to check prices</a></small>
                                                    @endif
                                                </div>
                                                @if (addon_is_activated('club_point'))
                                                    <div
                                                        class="rounded px-2 mt-2 bg-soft-primary border-soft-primary border">
                                                        {{ translate('Club Point') }}:
                                                        <span
                                                            class="fw-700 float-right">{{ $product->earn_point }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                    {{-- Product Query --}}
                    @if (get_setting('product_query_activation') == 1)
                        <div class="bg-white rounded shadow-sm mt-3">
                            <div class="border-bottom p-3">
                                <h3 class="fs-18 fw-600 mb-0">
                                    <span>{{ translate(' Product Queries ') }} ({{ $total_query }})</span>
                                </h3>
                            </div>
                            @guest
                                <p class="fs-14 fw-400 mb-0 ml-3 mt-2"><a
                                        href="{{ route('user.login') }}">{{ translate('Login') }}</a> or <a class="mr-1"
                                        href="{{ route('user.registration') }}">{{ translate('Register ') }}</a>{{ translate(' to submit your questions to seller') }}
                                </p>
                            @endguest
                            @auth
                                <div class="query form p-3">
                                    @if ($errors->any())
                                        <div class="alert alert-danger">
                                            <ul>
                                                @foreach ($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                    <form action="{{ route('product-queries.store') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="product" value="{{ $detailedProduct->id }}">
                                        <div class="form-group">
                                            <textarea class="form-control" rows="3" cols="40" name="question"
                                                placeholder="{{ translate('Write your question here...') }}" style="resize: none;"></textarea>

                                        </div>
                                        <button type="submit" class="btn btn-primary">{{ translate('Submit') }}</button>
                                    </form>
                                </div>
                                @php
                                    $own_product_queries = Auth::user()->product_queries->where('product_id',$detailedProduct->id);
                                @endphp
                                @if ($own_product_queries->count() > 0)

                                    <div class="question-area my-4   mb-0 ml-3">

                                        <div class="border-bottom py-3">
                                            <h3 class="fs-18 fw-600 mb-0">
                                                <span class="mr-4">{{ translate('My Questions') }}</span>
                                            </h3>
                                        </div>
                                        @foreach ($own_product_queries as $product_query)
                                            <div class="produc-queries border-bottom">
                                                <div class="query d-flex my-4">
                                                    <span class="mt-1"><svg xmlns="http://www.w3.org/2000/svg" width="24.994"
                                                            height="24.981" viewBox="0 0 24.994 24.981">
                                                            <g id="Group_23909" data-name="Group 23909"
                                                                transform="translate(18392.496 11044.037)">
                                                                <path id="Subtraction_90" data-name="Subtraction 90"
                                                                    d="M1830.569-117.742a.4.4,0,0,1-.158-.035.423.423,0,0,1-.252-.446c0-.84,0-1.692,0-2.516v-2.2a5.481,5.481,0,0,1-2.391-.745,5.331,5.331,0,0,1-2.749-4.711c-.034-2.365-.018-4.769,0-7.094l0-.649a5.539,5.539,0,0,1,4.694-5.513,5.842,5.842,0,0,1,.921-.065q3.865,0,7.73,0l5.035,0a5.539,5.539,0,0,1,5.591,5.57c.01,2.577.01,5.166,0,7.693a5.54,5.54,0,0,1-4.842,5.506,6.5,6.5,0,0,1-.823.046l-3.225,0c-1.454,0-2.753,0-3.97,0a.555.555,0,0,0-.435.182c-1.205,1.214-2.435,2.445-3.623,3.636l-.062.062-1.005,1.007-.037.037-.069.069A.464.464,0,0,1,1830.569-117.742Zm7.37-11.235h0l1.914,1.521.817-.754-1.621-1.273a3.517,3.517,0,0,0,1.172-1.487,5.633,5.633,0,0,0,.418-2.267v-.58a5.629,5.629,0,0,0-.448-2.323,3.443,3.443,0,0,0-1.282-1.525,3.538,3.538,0,0,0-1.93-.53,3.473,3.473,0,0,0-1.905.534,3.482,3.482,0,0,0-1.288,1.537,5.582,5.582,0,0,0-.454,2.314v.654a5.405,5.405,0,0,0,.471,2.261,3.492,3.492,0,0,0,1.287,1.5,3.492,3.492,0,0,0,1.9.527,3.911,3.911,0,0,0,.947-.112Zm-.948-.9a2.122,2.122,0,0,1-1.812-.9,4.125,4.125,0,0,1-.652-2.457v-.667a4.008,4.008,0,0,1,.671-2.4,2.118,2.118,0,0,1,1.78-.863,2.138,2.138,0,0,1,1.824.869,4.145,4.145,0,0,1,.639,2.473v.673a4.07,4.07,0,0,1-.655,2.423A2.125,2.125,0,0,1,1836.991-129.881Z"
                                                                    transform="translate(-20217 -10901.814)" fill="#e62e04"
                                                                    stroke="rgba(0,0,0,0)" stroke-miterlimit="10"
                                                                    stroke-width="1" />
                                                            </g>
                                                        </svg></span>

                                                    <div class="ml-3">
                                                        <div class="fs-14">{{ strip_tags($product_query->question) }}</div>
                                                        <span class="text-secondary">{{ $product_query->user->name }} </span>
                                                    </div>
                                                </div>
                                                <div class="answer d-flex my-4">
                                                    <span class="mt-1"> <svg xmlns="http://www.w3.org/2000/svg" width="24.99"
                                                            height="24.98" viewBox="0 0 24.99 24.98">
                                                            <g id="Group_23908" data-name="Group 23908"
                                                                transform="translate(17952.169 11072.5)">
                                                                <path id="Subtraction_89" data-name="Subtraction 89"
                                                                    d="M2162.9-146.2a.4.4,0,0,1-.159-.035.423.423,0,0,1-.251-.446q0-.979,0-1.958V-151.4a5.478,5.478,0,0,1-2.39-.744,5.335,5.335,0,0,1-2.75-4.712c-.034-2.355-.018-4.75,0-7.065l0-.678a5.54,5.54,0,0,1,4.7-5.513,5.639,5.639,0,0,1,.92-.064c2.527,0,5.029,0,7.437,0l5.329,0a5.538,5.538,0,0,1,5.591,5.57c.01,2.708.01,5.224,0,7.692a5.539,5.539,0,0,1-4.843,5.506,6,6,0,0,1-.822.046l-3.234,0c-1.358,0-2.691,0-3.96,0a.556.556,0,0,0-.436.182c-1.173,1.182-2.357,2.367-3.5,3.514l-1.189,1.192-.047.048-.058.059A.462.462,0,0,1,2162.9-146.2Zm5.115-12.835h3.559l.812,2.223h1.149l-3.25-8.494h-.98l-3.244,8.494h1.155l.8-2.222Zm3.226-.915h-2.888l1.441-3.974,1.447,3.972Z"
                                                                    transform="translate(-20109 -10901.815)" fill="#f7941d"
                                                                    stroke="rgba(0,0,0,0)" stroke-miterlimit="10"
                                                                    stroke-width="1" />
                                                            </g>
                                                        </svg></span>

                                                    <div class="ml-3">
                                                        <div class="fs-14">
                                                            {{ strip_tags($product_query->reply ? $product_query->reply : translate('Seller did not respond yet')) }}
                                                        </div>
                                                        <span class=" text-secondary">
                                                            {{ $product_query->product->user->name }} </span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                @endif @endauth

                            <div class="pagination-area
    my-4 mb-0 ml-3">
  @include('frontend.partials.product_query_pagination')
  </div>
  </div>
  @endif
  {{-- End of Product Query --}}
  </div>
  </div>
  </div>
  </section>

@endsection

@section('modal')
  <div class="modal fade" id="chat_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-zoom product-modal" id="modal-size" role="document">
      <div class="modal-content position-relative">
        <div class="modal-header">
          <h5 class="modal-title fw-600 h5">{{ translate('Any query about this product') }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form class="" action="{{ route('conversations.store') }}" method="POST" enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="product_id" value="{{ $detailedProduct->id }}">
          <div class="modal-body gry-bg px-3 pt-3">
            <div class="form-group">
              <input type="text" class="form-control mb-3" name="title" value="{{ $detailedProduct->name }}"
                placeholder="{{ translate('Product Name') }}" required>
            </div>
            <div class="form-group">
              <textarea class="form-control" rows="8" name="message" required placeholder="{{ translate('Your Question') }}">{{ route('product', $detailedProduct->slug) }}</textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-primary fw-600"
              data-dismiss="modal">{{ translate('Cancel') }}</button>
            <button type="submit" class="btn btn-primary fw-600">{{ translate('Send') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="login_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-zoom" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title fw-600">{{ translate('Login') }}</h6>
          <button type="button" class="close" data-dismiss="modal">
            <span aria-hidden="true"></span>
          </button>
        </div>
        <div class="modal-body">
          <div class="p-3">
            <form class="form-default" role="form" action="{{ route('cart.login.submit') }}" method="POST">
              @csrf
              <div class="form-group">
                @if (addon_is_activated('otp_system'))
                  <input type="text"
                    class="form-control h-auto form-control-lg {{ $errors->has('email') ? ' is-invalid' : '' }}"
                    value="{{ old('email') }}" placeholder="{{ translate('Email Or Phone') }}" name="email"
                    id="email">
                @else
                  <input type="email"
                    class="form-control h-auto form-control-lg {{ $errors->has('email') ? ' is-invalid' : '' }}"
                    value="{{ old('email') }}" placeholder="{{ translate('Email') }}" name="email">
                @endif
                @if (addon_is_activated('otp_system'))
                  <span class="opacity-60">{{ translate('Use country code before number') }}</span>
                @endif
              </div>

              <div class="form-group">
                <input type="password" name="password" class="form-control h-auto form-control-lg"
                  placeholder="{{ translate('Password') }}">
              </div>

              <div class="row mb-2">
                <div class="col-6">
                  <label class="aiz-checkbox">
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                    <span class=opacity-60>{{ translate('Remember Me') }}</span>
                    <span class="aiz-square-check"></span>
                  </label>
                </div>
                <div class="col-6 text-right">
                  <a href="{{ route('password.request') }}"
                    class="text-reset opacity-60 fs-14">{{ translate('Forgot password?') }}</a>
                </div>
              </div>

              <div class="mb-5">
                <button type="submit" class="btn btn-primary btn-block fw-600">{{ translate('Login') }}</button>
              </div>
            </form>

            <div class="text-center mb-3">
              <p class="text-muted mb-0">{{ translate('Dont have an account?') }}</p>
              <a href="{{ route('user.registration') }}">{{ translate('Register Now') }}</a>
            </div>
            @if (get_setting('google_login') == 1 ||
                    get_setting('facebook_login') == 1 ||
                    get_setting('twitter_login') == 1 ||
                    get_setting('apple_login') == 1)
              <div class="separator mb-3">
                <span class="bg-white px-3 opacity-60">{{ translate('Or Login With') }}</span>
              </div>
              <ul class="list-inline social colored text-center mb-5">
                @if (get_setting('facebook_login') == 1)
                  <li class="list-inline-item">
                    <a href="{{ route('social.login', ['provider' => 'facebook']) }}" class="facebook">
                      <i class="lab la-facebook-f"></i>
                    </a>
                  </li>
                @endif
                @if (get_setting('google_login') == 1)
                  <li class="list-inline-item">
                    <a href="{{ route('social.login', ['provider' => 'google']) }}" class="google">
                      <i class="lab la-google"></i>
                    </a>
                  </li>
                @endif
                @if (get_setting('twitter_login') == 1)
                  <li class="list-inline-item">
                    <a href="{{ route('social.login', ['provider' => 'twitter']) }}" class="twitter">
                      <i class="lab la-twitter"></i>
                    </a>
                  </li>
                @endif
                @if (get_setting('apple_login') == 1)
                  <li class="list-inline-item">
                    <a href="{{ route('social.login', ['provider' => 'apple']) }}" class="apple">
                      <i class="lab la-apple"></i>
                    </a>
                  </li>
                @endif
              </ul>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
  @php
    $recent_products = array_unique(session('recently_viewed_prods') ? session('recently_viewed_prods') : []);
    if (count($recent_products) > 11) {
        array_shift($recent_products);
    }
    $recent_products['p' . $detailedProduct->id] = $detailedProduct;
    session()->put('recently_viewed_prods', $recent_products);
  @endphp
@endsection

@section('script')
<script>
    $(document).ready(function () {
		// Function to reset subsequent dropdowns
		function resetSubsequentDropdowns(currentDropdown) {
			let foundCurrent = false;

			// Iterate over all select elements
			$('.attribute-select').each(function () {
				if (foundCurrent) {
					// Reset the value of subsequent dropdowns
					$(this).val('');
				}

				if (this === currentDropdown) {
					foundCurrent = true; // Mark current dropdown as found
				}
			});
		}

        // Function to collect all selected attribute values
        function getSelectedAttributes() {
            let selectedAttributes = {};
            $('.attribute-select').each(function () {
                const attributeId = $(this).data('attribute-id');
                const selectedValue = $(this).val();
                if (selectedValue) {
                    selectedAttributes[attributeId] = selectedValue; // Collect all selected values
                }
            });
            return selectedAttributes;
        }

        // Attach change event to dropdowns
        $('.attribute-select').on('change', function () {
			 // Reset all dropdowns below the current one
              resetSubsequentDropdowns(this);
            const selectedAttributes = getSelectedAttributes(); // Get all selected attributes

            // Check if all attributes are selected
            const allSelected = $('.attribute-select').length === Object.keys(selectedAttributes).length;

            // Log for debugging
            console.log("Selected Attributes:", selectedAttributes);
            console.log("All Selected:", allSelected);

            // Only hit AJAX if the last attribute is changed and all are selected
            const isLastAttribute = $(this).is($('.attribute-select').last());

            if (allSelected && isLastAttribute) {
                let variation_parent_part_no=$('#variation_parent_part_no').val();
               
                // Send data via AJAX
                $.ajax({
                    url: "{{ route('attribute.values.update') }}", // Replace with your actual route
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}", // Include CSRF token
                        selected_values: selectedAttributes,
                        variation_parent_part_no:variation_parent_part_no
                    },
                    success: function (response) {
                        console.log("Response from server for variation parent partno "+variation_parent_part_no+":", response);
                        if (response.success && response.data) {
                            // Redirect to the product page with the slug
                            window.location.href = "{{ url('/product') }}/" + response.data;
                        } else {
                            alert(response.message || "No matching product found.");
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("AJAX error:", error);
                        alert("An error occurred. Please try again.");
                    }
                });
            }
        });
    });
</script>


<!-- <script type="text/javascript">
    
    // Add an event listener to the dropdown
    document.getElementById('variant_select').addEventListener('change', function () {
        const selectedUrl = this.value; // Get the selected option's value (URL)
        if (selectedUrl) {
            window.location.href = selectedUrl; // Redirect to the selected URL
        }
    });
</script> -->
  <script type="text/javascript">
    $(document).ready(function() {
      getVariantPrice();
      getVariantCartonPrice();
    });


    

    function CopyToClipboard(e) {
      var url = $(e).data('url');
      var $temp = $("<input>");
      $("body").append($temp);
      $temp.val(url).select();
      try {
        document.execCommand("copy");
        AIZ.plugins.notify('success', '{{ translate('Link copied to clipboard') }}');
      } catch (err) {
        AIZ.plugins.notify('danger', '{{ translate('Oops, unable to copy') }}');
      }
      $temp.remove();
      // if (document.selection) {
      //     var range = document.body.createTextRange();
      //     range.moveToElementText(document.getElementById(containerid));
      //     range.select().createTextRange();
      //     document.execCommand("Copy");

      // } else if (window.getSelection) {
      //     var range = document.createRange();
      //     document.getElementById(containerid).style.display = "block";
      //     range.selectNode(document.getElementById(containerid));
      //     window.getSelection().addRange(range);
      //     document.execCommand("Copy");
      //     document.getElementById(containerid).style.display = "none";

      // }
      // AIZ.plugins.notify('success', 'Copied');
    }

    function show_chat_modal() {
      @if (Auth::check())
        $('#chat_modal').modal('show');
      @else
        $('#login_modal').modal('show');
      @endif
    }

    // Pagination using ajax
    $(window).on('hashchange', function() {
      if (window.location.hash) {
        var page = window.location.hash.replace('#', '');
        if (page == Number.NaN || page <= 0) {
          return false;
        } else {
          getQuestions(page);
        }
      }
    });

    $(document).ready(function() {
      $(document).on('click', '.pagination a', function(e) {
        getQuestions($(this).attr('href').split('page=')[1]);
        e.preventDefault();
      });

      $('.moreparts').on('shown.bs.tab', function(e) {
        var target = $(e.target).attr("href"); // activated tab
        $(target).find('.aiz-carousel').slick('refresh');
      });

      $('.orderdivision').on('shown.bs.tab', function(e) {
        var target = $(e.target).attr("data-type"); // activated tab
        console.log(target);
        $('#add-to-cart').attr('data-type', target);
      });
    });

    function getQuestions(page) {
      $.ajax({
        url: '?page=' + page,
        dataType: 'json',
      }).done(function(data) {
        $('.pagination-area').html(data);
        location.hash = page;
      }).fail(function() {
        alert('Something went worng! Questions could not be loaded.');
      });
    }
    // Pagination end
  </script>

  <script type="text/javascript">

    function buy_now(value) {
        $('.without_discount').hide(); // Hide non-bulk discount
        $('#quantity').val(value); // Set quantity to bulk discount quantity
        let bulk_discount_price = $('#offer').val(); // Get bulk discount price
        $('#discounted_price').text(bulk_discount_price);

        // Calculate total price
        let totalprice = (parseInt(bulk_discount_price.replace(/[^\d]/g, ''), 10) * parseInt(value)).toLocaleString();

        // Update chosen price
        $('#chosen_price').text("₹ " + totalprice);
        $('#type').val('bulk');

        // Show total price and activate buttons
        $('#chosen_price_div').removeClass('d-none'); // Show price div
        $('#add-to-cart, #buyInstant').prop('disabled', false); // Enable buttons
    }

    @if(Auth::check() && Auth::user()->id == '24185')
        function inr(n){ return '₹ ' + Number(n || 0).toLocaleString('en-IN'); }

        function fetchUnitPrice(priceUrl, qty, userId){
        return $.ajax({
            url: priceUrl,
            method: "POST",
            cache: false,
            data: {
            _token: document.querySelector('meta[name="csrf-token"]').content,
            qty: qty,
            user_id: userId
            }
        });
        }

        // keep the name exactly so your inline onkeyup works
        async function handleQuantityChange(elOrEvent){
            // figure out the input that triggered this
            var $input;
            if (elOrEvent && elOrEvent.target) { $input = $(elOrEvent.target); }
            else if (elOrEvent && elOrEvent.nodeType === 1) { $input = $(elOrEvent); }
            else { $input = $(document.activeElement); }

            if (!$input.length) return false;

            // scope to this product’s form/modal
            var $form = $input.closest('#option-choice-form');
            if (!$form.length) $form = $input.closest('.modal-body');

            // read config fresh from data-* each time (NO globals)
            var qty            = parseInt($input.val() || 0, 10);
            var DISCOUNT_QTY   = Number($input.data('discountQty')) || 0;
            var BULK_UNIT      = Number($input.data('bulkUnit')) || 0;
            var RETAIL_DEFAULT = Number($input.data('retailDefault')) || 0;
            var PRICE_URL      = String($input.data('priceUrl') || '');
            var USER_ID        = Number($input.data('userId')) || 0;
            // targets INSIDE this form/modal only
            var $withoutDisc = $form.find('.without_discount');
            // var $offer       = $form.find('#offer');
            var $offer       = $form.find('#discounted_price');
            var $chosen      = $form.find('#chosen_price');
            var $increase    = $form.find('#increasePriceText');
            var $piecePrice  = $form.find('#order_by_piece_price');
            var $type        = $form.find('#type');

            if (qty >= DISCOUNT_QTY && DISCOUNT_QTY > 0) {
                $withoutDisc.hide();
                $offer.text(inr(BULK_UNIT));
                $chosen.text(inr(BULK_UNIT * qty));
                $type.val('bulk');
                return false;
            }

            $withoutDisc.show();
            try {
                var res  = await fetchUnitPrice(PRICE_URL, qty, USER_ID);
                var unit = Number(res && res.price) || 0;
                var note = (res && res.increasePriceText) || '';
                $offer.text(inr(unit));
                $increase.text(note);
                $chosen.text(inr(unit * qty));
                $piecePrice.val(unit);
                $type.val('piece');
            } catch (err) {
                console.error(err);
                $offer.text(inr(RETAIL_DEFAULT));
                $chosen.text(inr(RETAIL_DEFAULT * qty));
                $type.val('piece');
            }
            if (isNaN(qty) || qty <= 0) {
                 $('#add-to-cart, #buyInstant').prop('disabled', true); // Disable buttons
            }else{
                $('#add-to-cart, #buyInstant').prop('disabled', false);
            }
            return false;
        }

        // prevent stacked listeners when modal HTML is reloaded
        $(document)
        .off('input.pricing change.pricing', '#quantity')
        .on('input.pricing change.pricing', '#quantity', handleQuantityChange);
    @else
        function handleQuantityChange() {
            let totalprice = 0;
            const qtyStr = $('#quantity').val().trim(); // Get and trim the quantity value
            const qty = parseInt(qtyStr, 10); // Convert to integer

            // If quantity is invalid
            if (isNaN(qty) || qty <= 0) {
                $('#chosen_price_div').addClass('d-none'); // Hide price div
                $('#add-to-cart, #buyInstant').prop('disabled', true); // Disable buttons
                $('#chosen_price').text("₹ " + totalprice); // Reset price to 0
                return;
            }

            // Valid quantity: Show price div and enable buttons
            $('#chosen_price_div').removeClass('d-none');
            $('#add-to-cart, #buyInstant').prop('disabled', false);

            // Get discount quantity and compare
            const discount_qty = {{ home_bulk_qty($detailedProduct)['bulk_qty'] }};
            if (qty >= discount_qty) {
                // Bulk discount applies
                $('.without_discount').hide();
                const bulk_discount_price = $('#offer').val();
                totalprice = (parseInt(bulk_discount_price.replace(/[^\d]/g, ''), 10) * qty).toLocaleString();
                $('#discounted_price').text(bulk_discount_price);
                $('#type').val('bulk');
            } else {
                // Regular price applies
                $('.without_discount').show();
                const actual_price = parseInt($('#actual_price').val().replace(/[^\d]/g, ''), 10);
                totalprice = actual_price * qty;
                $('#type').val('piece');
            }

            // Update chosen price
            $('#chosen_price').text("₹ " + totalprice.toLocaleString());
        }
    @endif

  </script>
@endsection