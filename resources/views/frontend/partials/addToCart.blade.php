<style>
    @keyframes blink {
        50% {
            opacity: 0;
        }
    }

    .blink {
        animation: blink 1s steps(5, start) infinite;
        color: red;
    }
</style>

<div  class="modal-body p-4 c-scrollbar-light">
  <div class="row pr-2">
    <div class="col-lg-6">
      <div class="row">
        @php
          $photos = explode(',', $product->photos);
        @endphp
        <div class="col">
          {!! ($product->cash_and_carry_item == 1 && Auth::check() && Auth::user()->credit_days > 0) ? '<span class="badge-custom">No Credit Item<span class="box ml-1 mr-0">&nbsp;</span></span>' : '' !!}
          <div class="aiz-carousel product-gallery" data-nav-for='.product-gallery-thumb' data-fade='true'
            data-auto-height='true'>
            @foreach ($photos as $key => $photo)
            @php
              // Fetch the base URL for uploads from the .env file
              $uploads_base_url = env('UPLOADS_BASE_URL', url('public'));

              // Fetch file_name for the photo (assuming $photo contains the ID of the upload)
              $photo_file = \App\Models\Upload::where('id', $photo)->value('file_name');
              $photo_file_path = $photo_file
                          ? $uploads_base_url . '/' . $photo_file
                          : url('public/assets/img/placeholder.jpg');
            @endphp

              <div class="carousel-box img-zoom rounded">
              <img class="img-fluid lazyload"
                src="{{ url('public/assets/img/placeholder.jpg') }}"
                data-src="{{ $photo_file_path }}"
                onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">

                <!-- <img class="img-fluid lazyload" src="{{ static_asset('assets/img/placeholder.jpg') }}"
                  data-src="{{ uploaded_asset($photo) }}"
                  onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
              </div>
            @endforeach

            @foreach ($product->stocks as $key => $stock)
              @if ($stock->image != null)
              @php
                  // Fetch the base URL for uploads from the .env file
                  $uploads_base_url = env('UPLOADS_BASE_URL', url('public'));

                  // Fetch file_name for the stock image (assuming $stock->image contains the ID of the upload)
                  $stock_image = \App\Models\Upload::where('id', $stock->image)->value('file_name');
                  $stock_image_path = $stock_image
                            ? $uploads_base_url . '/' . $stock_image
                            : url('public/assets/img/placeholder.jpg');
              @endphp
                <div class="carousel-box img-zoom rounded">
                  <!-- <img class="img-fluid lazyload" src="{{ static_asset('assets/img/placeholder.jpg') }}"
                    data-src="{{ uploaded_asset($stock->image) }}"
                    onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                    <img class="img-fluid lazyload" 
                        src="{{ url('public/assets/img/placeholder.jpg') }}" 
                        data-src="{{ $stock_image_path }}"
                        onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">
                </div>
              @endif
            @endforeach


          </div>


        </div>
        <div class="col-12 mt-3 mt-md-0">
          <div class="aiz-carousel product-gallery-thumb" data-items='5' data-nav-for='.product-gallery'
            data-focus-select='true' data-arrows='true'>
            @foreach ($photos as $key => $photo)
            @php
              // Fetch the base URL for uploads from the .env file
              $uploads_base_url = env('UPLOADS_BASE_URL', url('public'));

              // Fetch file_name for the photo (assuming $photo contains the ID of the upload)
              $photo_file = \App\Models\Upload::where('id', $photo)->value('file_name');
              $photo_file_path = $photo_file
                          ? $uploads_base_url . '/' . $photo_file
                          : url('public/assets/img/placeholder.jpg');
            @endphp

              <div class="carousel-box c-pointer border p-1 rounded">
              <img class="lazyload mw-100 size-50px h-auto mx-auto"
                src="{{ url('public/assets/img/placeholder.jpg') }}"
                data-src="{{ $photo_file_path }}"
                onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">

                <!-- <img class="lazyload mw-100 size-50px h-auto mx-auto"
                  src="{{ static_asset('assets/img/placeholder.jpg') }}" data-src="{{ uploaded_asset($photo) }}"
                  onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
              </div>
            @endforeach
            @foreach ($product->stocks as $key => $stock)
              @if ($stock->image != null)
                @php
                    // Fetch the base URL for uploads from the .env file
                    $uploads_base_url = env('UPLOADS_BASE_URL', url('public'));

                    // Fetch file_name for the stock image (assuming $stock->image contains the ID of the upload)
                    $stock_image = \App\Models\Upload::where('id', $stock->image)->value('file_name');
                    $stock_image_path = $stock_image
                              ? $uploads_base_url . '/' . $stock_image
                              : url('public/assets/img/placeholder.jpg');
                @endphp
                <div class="carousel-box c-pointer border p-1 rounded" data-variation="{{ $stock->variant }}">
                <img class="lazyload mw-100 size-50px mx-auto" 
                  src="{{ url('public/assets/img/placeholder.jpg') }}" 
                  data-src="{{ $stock_image_path }}"
                  onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">
                  <!-- <img class="lazyload mw-100 size-50px mx-auto" src="{{ static_asset('assets/img/placeholder.jpg') }}"
                    data-src="{{ uploaded_asset($stock->image) }}"
                    onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                </div>
              @endif
            @endforeach
          </div>
       <!-- Attributes Section -->
          <div class="mt-4">
              <div class="table-responsive">
                  <table class="table table-borderless" style="margin-bottom: 0;">
                      <tbody>
                          <!-- Dynamic Attributes Row -->
                          @if ($product->attributes && count(json_decode($product->attributes, true)) > 0)
                              @php
                                  $attributeIds = json_decode($product->attributes, true);
                                  $attributes = DB::table('attribute_values')
                                      ->join('attributes', 'attribute_values.attribute_id', '=', 'attributes.id')
                                      ->whereIn('attribute_values.id', $attributeIds)
                                      ->select('attributes.name as attribute_name', 'attribute_values.value as attribute_value')
                                      ->get();
                              @endphp

                              @foreach ($attributes as $attribute)
                               @if (!empty($attribute->attribute_value)) <!-- Check if attribute value is not empty -->
                                  <tr style="padding: 2px 0;">
                                      <th  style="color: #6c757d; font-size: 14px; padding: 4px; font-weight: 501;">{{ $attribute->attribute_name }}</th>
                                      <td style="font-size: 14px; padding: 4px; color: #555;">{{ Str::title($attribute->attribute_value) }}</td>
                                  </tr>
                                @endif
                              @endforeach
                          
                          @endif
                      </tbody>
                  </table>
              </div>
          </div>
          <!-- End Attributes Section -->
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="text-left">
        <h2 class="mb-2 fs-20 fw-600">
          {{ $product->getTranslation('name') }}
        </h2>
        @if ($product->est_shipping_days)
          @php $est = get_estimated_shipping_days($product); @endphp
          @if ($est['days'])
            <small class="mr-2 opacity-50">{{ translate('Estimated Shipping Time') }}:
            </small>{{ $est['days'] . ' - ' . ($est['days'] + 1) }} {{ translate('Days') }}

          @endif
        @endif
        <div class="row no-gutters mt-4 d-flex justify-content-between align-items-center">
          @if($product->offer != "")
            <img src="public/uploads/offers-icon.png" alt="Special Offer" class="view-offer" style="width: 92px; height: auto;  border-radius: 3px; cursor: pointer; " data-toggle="modal" data-target="#offerModal" data-product-id="{{$product->id}}">
          @endif
          @php $is41 = $is41Manager ?? false; @endphp
          @if(
              $is41
                  ? \App\Models\Manager41ProductStock::where('part_no', $product->part_no)->where('closing_stock', '>', 0)->exists()
                  : \DB::table('products_api')->where('part_no', $product->part_no)->where('closing_stock', '>', 0)->exists()
          )
            <img src="{{ asset('public/uploads/fast_dispatch.jpg') }}" alt="Fast Delivery"
                 style="width: 75px; height: 21px; border-radius: 3px;">
          @endif

          @if ($product->is_warranty == 1)
              <img src="{{ asset('public/uploads/warranty.jpg') }}" alt="Fast Delivery" style="width: 75px; border-radius: 3px;"> <strong>{{ $product->warranty_duration }} Months </strong>
          @endif

        </div>

        @if (addon_is_activated('club_point') && $product->earn_point > 0)
          <div class="row no-gutters mt-4">
            <div class="col-2">
              <div class="opacity-50">{{ translate('Club Point') }}:</div>
            </div>
            <div class="col-10">
              <div class="d-inline-block club-point bg-soft-primary px-3 py-1 border">
                <span class="strong-700">{{ $product->earn_point }}</span>
              </div>
            </div>
          </div>
        @endif

        <form id="option-choice-form">
          @csrf
          <input type="hidden" name="id" id="product_id" value="{{ $product->id }}">
          <input type="hidden" name="order_id" value="{{ $order_id }}">
          <input type="hidden" name="sub_order_id" value="{{ $sub_order_id }}">
          <input type="hidden" name="product_name" value="{{ $product->getTranslation('name') }}">
          <input type="hidden" name="brand_name" value="{{ $product->brand->getTranslation('name') }}">
          <input type="hidden" name="category_name" value="{{ $product->category->getTranslation('name') }}">
          <input type="hidden" name="variant" value="{{ $product->stocks->first()->variant }}">
          <input type="hidden" id="order_by_piece_price" name="order_by_piece_price" value="{{ home_discounted_price($product, false, $user_id)['price'] }}">
          <input type="hidden" name="order_by_carton_price"  value="{{ home_bulk_discounted_price($product, false)['price'] }}">
          <!-- <input type="hidden" name="order_by_carton_price"  value="{{ round(home_discounted_price($product, false)['carton_price']) }}"> -->
          <input type="hidden" id="piece_per_carton" name="piece_per_carton" value="">
          @if ($product->choice_options != null)
            @foreach (json_decode($product->choice_options) as $key => $choice)
              @php $attribute = \App\Models\Attribute::find($choice->attribute_id) @endphp
              @if (is_countable($choice->values) && count($choice->values))
                <div class="row no-gutters">
                  <div class="col-6 col-sm-5">
                    <div class="opacity-50 my-2">
                      {{ $attribute->getTranslation('name') }}:
                    </div>
                  </div>
                  <div class="col-6 col-sm-7 d-flex align-items-center">
                    <div class="aiz-radio-inline">
                      @if (
                          $product->category->categoryGroup->name == 'Accessories' ||
                              $product->category->categoryGroup->name == 'Spare Parts')
                        @if ($attribute->type == 'variant')
                          @foreach ($choice->values as $key => $value)
                            <label class="aiz-megabox pl-0 mr-2 my-2">
                              <input type="radio" name="attribute_id_{{ $choice->attribute_id }}"
                                value="{{ $value }}" @if ($key == 0) checked @endif>
                              <span
                                class="aiz-megabox-elem rounded d-flex align-items-center justify-content-center py-2 px-3">
                                {{ $value }}
                              </span>
                            </label>
                          @endforeach
                        @else
                          <input type="hidden" name="attribute_id_{{ $choice->attribute_id }}"
                            value="{{ $choice->values[0] }}">
                          <div class="my-2">
                            @foreach ($choice->values as $key => $value)
                              {{ $value }}
                            @endforeach
                          </div>
                        @endif
                      @else
                        @if ($attribute->type == 'variant')
                          @php
                            $attrs = \App\Models\Product::select('choice_options')
                                ->where('category_id', $product->category->id)
                                ->get();
                            $list = [];
                            foreach ($attrs as $attr) {
                                $list = array_merge(
                                    $list,
                                    collect(json_decode($attr->choice_options))
                                        ->where('attribute_id', $attribute->id)
                                        ->first()->values,
                                );
                                $list = array_unique($list);
                                sort($list);
                            }
                          @endphp
                          @if (is_countable($list) && count($list) > 1)
                            <input type="hidden" name="attribute_id_{{ $choice->attribute_id }}"
                              value="{{ $choice->values[0] }}">
                            <div class="dropdown d-inline-block">
                              <button class="btn btn-primary btn-xs dropdown-toggle" type="button"
                                data-toggle="dropdown">{{ $choice->values[0] }}
                                @if (is_countable($list) && count($list) > 1)
                                  <span class="caret"></span>
                              </button>
                              <ul class="dropdown-menu">
                                @foreach ($list as $value)
                                  <li><a
                                      href="{{ url('/category/' . $product->category->slug . '?selected_attribute_values%5B%5D=' . $value) }}"
                                      class="ml-3">{{ $value }}</a></li>
                                @endforeach
                              </ul>
                          @endif
                    </div>
                  @else
                    <input type="hidden" name="attribute_id_{{ $choice->attribute_id }}"
                      value="{{ $choice->values[0] }}">
                    <div class="my-2">
                      @foreach ($choice->values as $key => $value)
                        {{ $value }}
                      @endforeach
                    </div>
              @endif
            @else
              <input type="hidden" name="attribute_id_{{ $choice->attribute_id }}"
                value="{{ $choice->values[0] }}">
              <div class="my-2">
                @foreach ($choice->values as $key => $value)
                  {{ $value }}
                @endforeach
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

  @if (is_countable(json_decode($product->colors)) && count(json_decode($product->colors)) > 0)
    <div class="row no-gutters">
      <div class="col-sm-2">
        <div class="opacity-50 my-2">{{ translate('Color') }}:</div>
      </div>
      <div class="col-sm-10">
        <div class="aiz-radio-inline">
          @foreach (json_decode($product->colors) as $key => $color)
            <label class="aiz-megabox pl-0 mr-2" data-toggle="tooltip"
              data-title="{{ \App\Models\Color::where('code', $color)->first()->name }}">
              <input type="radio" name="color"
                value="{{ \App\Models\Color::where('code', $color)->first()->name }}"
                @if ($key == 0) checked @endif>
              <span class="aiz-megabox-elem rounded d-flex align-items-center justify-content-center p-1 mb-2">
                <span class="size-30px d-inline-block rounded" style="background: {{ $color }};"></span>
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

          <input type="hidden" name="type" id="type" value="piece">
          @if ($product->wholesale_product)
            <table class="table mb-0">
              <thead>
                <tr>
                  <th>{{ translate('Min Qty') }}</th>
                  <th>{{ translate('Max Qty') }}</th>
                  <th>{{ translate('Unit Price') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($product->stocks->first()->wholesalePrices as $wholesalePrice)
                  <tr>
                    <td>{{ $wholesalePrice->min_qty }}</td>
                    <td>{{ $wholesalePrice->max_qty }}</td>
                    <td>{{ single_price($wholesalePrice->price) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @else
            @if (home_price($product, 0)['price'] != home_discounted_price($product, 0, $user_id)['price'])
              <div class="row no-gutters my-3 without_discount">
                <div class="col-6 col-md-4">
                  <div class="opacity-50 my-2">{{ translate('Price') }}:</div>
                </div>
                <div class="col-6 col-md-8">
                  <div class="fs-20 opacity-60">
                    <del>
                      {{ home_price($product)['price'] }}
                      @if ($product->unit != null)
                        <span>/{{ $product->getTranslation('unit') }}</span>
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
                    <strong class="h2 fw-600 text-primary" id="offer">
                      {{ home_discounted_price($product, true, $user_id)['price'] }}
                    </strong>
                    @if ($product->unit != null)
                      <span class="opacity-70">/{{ $product->getTranslation('unit') }}</span>
                    @endif
                    {!! ($product->cash_and_carry_item == 1 && Auth::check() && Auth::user()->credit_days > 0) ? '<span class="badge badge-inline badge-danger">No Credit Item</span>' : '' !!}
                  </div>
                </div>
              </div>
              <div class="row no-gutters my-3">
                  <div class="col-md-12">
                    <div class="opacity-70" style="color:#f00;" id="increasePriceText"></div>
                  </div>
              </div>
            @else
              <div class="row no-gutters my-3">
                <div class="col-6 col-md-4">
                  <div class="opacity-50 my-2">{{ translate('Price') }}:</div>
                </div>
                <div class="col-6 col-md-8">
                  <div>
                    <strong class="h2 fw-600 text-primary">{{ home_discounted_price($product, true, $user_id)['price'] }}</strong>
                    @if ($product->unit != null)
                      <span class="opacity-70">/{{ $product->getTranslation('unit') }}</span>
                    @endif
                  </div>
                </div>
              </div>
            @endif
          @endif
          @php
              $price        = (float) (home_discounted_price($product, false, $user_id)['price']);
              $target       = (float) env('SPECIAL_DISCOUNT_AMOUNT', 5000);
              $spPercentage = (float) env('SPECIAL_DISCOUNT_PERCENTAGE', 3);

              if ($price > 0) {
                  $spDQty = (int) ceil($target / $price);
                  if (($spDQty * $price) <= $target) {
                      $spDQty++;
                  }
                  // if env is 10 for 10%, use /100:
                  $spDPrice = $price - ($price * ($spPercentage / 100));
              } else {
                  $spDQty   = 0;
                  $spDPrice = 0;
              }
          @endphp
         <table class="table table-bordered">
          @php
            $is41 = $is41Manager ?? false; // [UNCHANGED/CONFIRM]
            // [UPDATED] Build a single query and branch the filter
            $stocksQuery = \App\Models\ProductWarehouse::query()
                ->where('product_id', $product->id);
        
            if ($is41) { // Manager-41 sees only their stock
                $stocksQuery->where('is_manager_41', 1);          // [NEW]
            } else {     // Non-Manager-41 sees only non-41 stock
                $stocksQuery->where(function($q){                  // [NEW]
                    $q->whereNull('is_manager_41')->orWhere('is_manager_41', 0);
                });
            }
        
            $stocks = $stocksQuery->get();                         // [NEW]
        
            // [NEW] Prefetch warehouse names to avoid N+1
            $warehouseNames = \App\Models\Warehouse::whereIn('id', $stocks->pluck('warehouse_id'))
                              ->pluck('name','id');
          @endphp
        
          @forelse ($stocks as $stock)
            @php $wName = $warehouseNames[$stock->warehouse_id] ?? '—'; @endphp  <!-- [NEW] -->
            <tr>
              <td style="padding:0.25em">{{ $wName }}</td>                                <!-- [UPDATED] -->
              <td style="padding:0.25em">{{ (int) $stock->qty }}</td>                     <!-- [UNCHANGED/SAFE] -->
            </tr>
          @empty
            <tr>
              <td colspan="2" class="text-center text-muted" style="padding:0.5em">No stock</td> <!-- [NEW] -->
            </tr>
          @endforelse
        </table>

          <div class="row no-gutters">
            @php /*
            @if(Auth::user()->id == '24185')
            <div>
              <span class="blink">Special Product Discount :</span>Purchase {{ $spDQty }} or more and get each for <strong style="color: black">  ₹{{ $spDPrice }} </strong> instead of <strong  style="color: black"> {{ home_discounted_price($product, true, $user_id)['price'] }} </strong> <br/>
              <!-- <a onclick="buy_now({{ home_bulk_qty($product)['bulk_qty'] }})" style="padding-left:10px; color:var(--primary);; font-weight: 600; cursor: pointer;">Get Discount</a> -->
            </div>
            @endif
            */ @endphp
            <div>
              <span class="blink">Bulk Quantity Discount :</span>Purchase {{ home_bulk_qty($product)['bulk_qty'] }} or more and get each for <strong style="color: black"> {{ home_bulk_discounted_price($product,true,$user_id)['price'] }} </strong> instead of <strong  style="color: black"> {{ home_discounted_price($product, true, $user_id)['price'] }} </strong> <a onclick="buy_now({{ home_bulk_qty($product)['bulk_qty'] }})" style="padding-left:10px; color:var(--primary);; font-weight: 600; cursor: pointer;">Get Discount</a>
            </div>
          </div>
          <input type="hidden" value="{{ home_bulk_discounted_price($product,true,$user_id)['price'] }}" id="get_bulk_discount_price">
          <!-- Quantity -->
          <div class="row no-gutters">
            <div class="col-6 col-md-4">
              <div class="opacity-50 my-2">{{ translate('Quantity') }}:</div>
            </div>
            <div class="col-6 col-md-8">
              <div class="product-quantity d-flex align-items-center">
                <div class="row no-gutters align-items-center aiz-plus-minus mr-3" style="width: 130px;">
                  <!-- <button class="btn col-auto btn-icon btn-sm btn-circle btn-light" type="button" data-type="minus"
                    data-count="1" data-field="quantity" disabled="">
                    <i class="las la-minus"></i>
                  </button> -->
                  {{-- <input type="number" name="quantity" id="quantity"
                    class="col border mx-2 text-center flex-grow-1 fs-16 input-number" placeholder="Enter Qty"
                    value="{{ $product->min_qty }}" min="{{ $product->min_qty }}" max="" lang="en"> --}}

                    <div class="row no-gutters">
                        <!-- <input type="number"
                               name="quantity"
                               id="quantity"
                               class="form-control col border mx-2 text-center fs-16"
                               placeholder="Enter Qty"
                               value="{{ $product->min_qty }}"
                               {{-- min="{{ $product->min_qty }}" --}}
                               lang="en" onkeyup="handleQuantityChange()"> -->

                        <input type="number"
                          name="quantity"
                          id="quantity"
                          class="form-control col border mx-2 text-center fs-16"
                          placeholder="Enter Qty"
                          value="{{ $product->min_qty }}"
                          lang="en"
                          onkeyup="handleQuantityChange()"
                          data-discount-qty="{{ home_bulk_qty($product)['bulk_qty'] }}"
                          data-bulk-unit="{{ (float) home_bulk_discounted_price($product, false, $user_id)['price'] }}"
                          data-retail-default="{{ (float) home_discounted_price($product, false, $user_id)['price'] }}"
                          data-price-url="{{ route('products.price', $product->id) }}"
                          data-user-id="{{ (int) $user_id }}">
                    </div>
                  <!-- <button class="btn  col-auto btn-icon btn-sm btn-circle btn-light" type="button" data-type="plus"
                    data-count="1" data-field="quantity">
                    <i class="las la-plus"></i>
                  </button> -->
                </div>
                @php
                    // default: non-41 logic (sum Eloquent product->stocks)
                    $qty = 0;
                
                    // detect if Manager-41 is active (controller should pass $is41Manager)
                    $is41 = $is41Manager ?? false;
                
                    if ($is41) {
                        // For Manager-41: read stock from manager_41_product_stocks
                        // Try to use already-loaded part_no; fallback to a quick lookup.
                        $partNo = $product->part_no ?? \DB::table('products')->where('id', $product->id)->value('part_no');
                
                        // Sum closing_stock for this part number
                        $qty = (int) \App\Models\ProductWarehouse::where('part_no', $partNo)->sum('qty');
                    } else {
                        // Original behavior
                        foreach ($product->stocks as $stock) {
                            $qty += (int) $stock->qty;
                        }
                    }
                @endphp
                <div class="avialable-amount opacity-60">
                  @if ($product->stock_visibility_state == 'quantity')
                    (<span id="available-quantity">{{ $qty }}</span>
                    {{ translate('available') }})
                  @elseif($product->stock_visibility_state == 'text' && $qty >= 1)
                    (<span id="available-quantity">{{ translate('In Stock') }}</span>)
                  @endif
                </div>
              </div>
            </div>
            <!--  product Attribute variend part start -->
            @if (!empty($attributeVariations))
                <div class="row no-gutters pb-3 my-3" id="variant_listing_div" data-product-id="{{ $product->id }}" >
                  <input type="hidden" id="variation_parent_part_no" value="{{ $product->variation_parent_part_no }}" name="variation_parent_part_no">
                    <div class="col-12">
                        @foreach ($attributeVariations as $attribute)
                            <div class="variant-dropdown mb-3">
                                <label style="font-size: 14px;" class="fw-400 opacity-50"> {{ $attribute['attribute_name'] }}</label>
                                <select
                                    style="width:350px;"
                                    class="form-control attribute-select"
                                    data-attribute-id="{{ $attribute['attribute_id'] }}"
                                    data-attribute-name="{{ $attribute['attribute_name'] }}">
                                    <option value=""> {{ $attribute['attribute_name'] }}</option>
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
                    </div>
                </div>
            @endif
            <!-- product attribute varient part end -->
          </div>

          <div class="row no-gutters pb-3 d-none my-3" id="chosen_price_div" style="display: block !important;">
            <div class="col-6 col-md-4">
              <div class="opacity-50 mt-1">{{ translate('Total Price') }}:</div>
            </div>
            <div class="col-6 col-md-8">
              <div class="product-price">
                <strong id="chosen_price" class="h4 fw-600 text-primary">
                    @php
                      $product_price = home_discounted_price($product, false, $user_id)['price'];
                      $show_total = $product_price * $product->min_qty;
                    @endphp
                    {{ price_less_than_50($show_total, true) }}
                </strong>
              </div>
              <!-- Added the text "Inclusive of all taxes" below the price -->
              <div class="mt-1">
                  <span style="font-size: 14px; color: #555;">Inclusive of all taxes</span>
              </div>
            </div>
          </div>
    @else
      <a class="my-2 d-inline-block btn btn-sm btn-primary" href="{{ route('user.registration') }}">Register to check
        prices</a>
    @endif
  </div>

  </form>
  <div class="mt-3">
    @if ($product->digital == 1)
      @if(empty($order_id))
        <button data-type="is_piece" type="button" class="btn btn-primary buy-now fw-600 add-to-cart"
          id="add-to-cart" onclick="addToCart()">
          <i class="la la-shopping-cart"></i>
          <span class="d-none d-md-inline-block">{{ translate('Add to cart') }}</span>
        </button>
      @else
        <button data-type="is_piece" type="button" class="btn btn-primary buy-now fw-600 add-product"
          id="add-product" onclick="addProduct()">
          <i class="la la-shopping-cart"></i>
          <span class="d-none d-md-inline-block">{{ translate('Add Product') }}</span>
        </button>
      @endif
    @elseif($qty > 0)
      @if ($product->external_link != null)
        <a type="button" class="btn btn-soft-primary mr-2 add-to-cart fw-600" href="{{ $product->external_link }}">
          <i class="las la-share"></i>
          <span class="d-none d-md-inline-block">{{ translate($product->external_link_btn) }}</span>
        </a>
      @else
        @if(empty($order_id) AND empty($sub_order_id))
          <button data-type="is_piece" type="button" class="btn btn-primary buy-now fw-600 add-to-cart"
            id="add-to-cart" onclick="addToCart()">
            <i class="la la-shopping-cart"></i>
            <span class="d-none d-md-inline-block">{{ translate('Add to cart') }}</span>
          </button>
        @else
          <button data-type="is_piece" type="button" class="btn btn-primary buy-now fw-600 add-product"
            id="add-product" onclick="addProduct()">
            <i class="la la-shopping-cart"></i>
            <span class="d-none d-md-inline-block">{{ translate('Add Product') }}</span>
          </button>
        @endif
      @endif
    @endif
    <button type="button" class="btn btn-secondary out-of-stock fw-600 d-none" disabled>
      <i class="la la-cart-arrow-down"></i>{{ translate('Out of Stock') }}
    </button>
  </div>

</div>
</div>
</div>
</div>

<script type="text/javascript">
  $('#option-choice-form input').on('change', function() {
    getVariantPrice();
    getVariantCartonPrice();
  });
  $('.orderdivision').on('shown.bs.tab', function(e) {
    var target = $(e.target).attr("data-type"); // activated tab
    $('#add-to-cart').attr('data-type', target);
  });

  function buy_now(value){
    let qty=value;
    $('#quantity').val(value);
    $('#type').val('bulk');
    $('.without_discount').hide();
        let priceExpression = "{{ home_bulk_discounted_price($product,true,$user_id)['price'] }}";
        let bulk_discount_price = priceExpression.replace(/[^\d.]/g, '');
        $('#offer').text("₹ " +bulk_discount_price);
        let totalprice=bulk_discount_price * qty;
        $('#chosen_price').text("₹ " +totalprice.toLocaleString());

  }



  // function inr(n){ return '₹ ' + Number(n).toLocaleString('en-IN'); }

  // const DISCOUNT_QTY   = @json(home_bulk_qty($product)['bulk_qty']);
  // const BULK_UNIT      = @json((float) home_bulk_discounted_price($product, true, $user_id)['price']);
  // const RETAIL_DEFAULT = @json((float) home_discounted_price($product, true, $user_id)['price']); // fallback
  // const PRICE_URL      = "{{ route('products.price', $product->id) }}";  // make this route
  // const USER_ID        = @json((int) $user_id);

  // function fetchUnitPrice(qty){
  //   return $.ajax({
  //     url: PRICE_URL,
  //     method: "POST",
  //     data: {
  //       _token: document.querySelector('meta[name="csrf-token"]').content,
  //       qty: qty,
  //       user_id: USER_ID
  //     }
  //   });
  // }

  // function handleQuantityChange(){
  //   let discount_qty={{ home_bulk_qty($product)['bulk_qty'] }};
  //   let qty= $('#quantity').val();
  //   if(qty >= discount_qty){
  //       $('.without_discount').hide();
  //       let priceExpression = "{{ home_bulk_discounted_price($product,true,$user_id)['price'] }}";
  //       let bulk_discount_price = priceExpression.replace(/[^\d.]/g, '');
  //       $('#offer').text("₹ " +bulk_discount_price);
  //       let totalprice=bulk_discount_price * qty;
  //       $('#chosen_price').text("₹ " +totalprice.toLocaleString());
  //       $('#type').val('bulk');
  //   }else{
  //       // alert("without discount");
  //       $('.without_discount').show();
  //       let priceExpression = "{{ home_discounted_price($product, true, $user_id)['price'] }}";
  //       let qty= $('#quantity').val();
  //       // let actual_price = priceExpression.replace(/[^\d.]/g, '');
  //       const res = await fetchUnitPrice(qty);                 // << sends JS qty to PHP
  //       const actual_price = parseFloat(res.price) || 0;       // unit price for this qty
  //       $('#offer').text("₹ " +actual_price);
        
  //       let totalprice=actual_price * qty;
  //       $('#chosen_price').text("₹ " +totalprice.toLocaleString());
  //       $('#type').val('piece');
  //   }
  // }
//  @if(Auth::user()->id == '24185')
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
      var $offer       = $form.find('#offer');
      var $chosen      = $form.find('#chosen_price');
      var $increase    = $form.find('#increasePriceText');
      var $piecePrice  = $form.find('#order_by_piece_price');
      var $type        = $form.find('#type');

      if (qty >= DISCOUNT_QTY && DISCOUNT_QTY > 0) {
        $withoutDisc.hide();
        $offer.text(inr(BULK_UNIT));
        $chosen.text(inr(BULK_UNIT * qty));
        $type.val('bulk');
        $increase.text('');
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
      return false;
    }

    // prevent stacked listeners when modal HTML is reloaded
    $(document)
      .off('input.pricing change.pricing', '#quantity')
      .on('input.pricing change.pricing', '#quantity', handleQuantityChange);
  // @else
  //   function handleQuantityChange(){
  //     let discount_qty={{ home_bulk_qty($product)['bulk_qty'] }};
  //     let qty= $('#quantity').val();
  //     if(qty >= discount_qty){
  //         $('.without_discount').hide();
  //         let priceExpression = "{{ home_bulk_discounted_price($product,true,$user_id)['price'] }}";
  //         let bulk_discount_price = priceExpression.replace(/[^\d.]/g, '');
  //         $('#offer').text("₹ " +bulk_discount_price);
  //         let totalprice=bulk_discount_price * qty;
  //         $('#chosen_price').text("₹ " +totalprice.toLocaleString());
  //         $('#type').val('bulk');
  //     }else{
  //         // alert("without discount");
  //         $('.without_discount').show();
  //         let priceExpression = "{{ home_discounted_price($product, true, $user_id)['price'] }}";
  //         let qty= $('#quantity').val();
  //         let actual_price = priceExpression.replace(/[^\d.]/g, '');
  //         // const res = await fetchUnitPrice(qty);                 // << sends JS qty to PHP
  //         // const actual_price = parseFloat(res.price) || 0;       // unit price for this qty
  //         $('#offer').text("₹ " +actual_price);
          
  //         let totalprice=actual_price * qty;
  //         $('#chosen_price').text("₹ " +totalprice.toLocaleString());
  //         $('#type').val('piece');
  //     }
  //   }
  // @endif

// Global variable to store the last selected values
if (!window.lastSelectedValues) {
    window.lastSelectedValues = {};
}

// Put this once, near the top of your script
function inr(value) {
  const num = Number(value) || 0;
  // Use Intl if available
  if (typeof Intl !== 'undefined' && Intl.NumberFormat) {
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      maximumFractionDigits: 2
    }).format(num);
  }
  // Fallback (simple): ₹ 12,34,567.89
  return '₹ ' + num.toFixed(2);
}

// Function to reset subsequent dropdowns
function resetSubsequentDropdowns(currentDropdown) {
    let foundCurrent = false;

    $('.attribute-select').each(function () {
        if (foundCurrent) {
            $(this).val('');
        }

        if (this === currentDropdown) {
            foundCurrent = true;
        }
    });
}

// Event listener for attribute dropdown changes
$(document).on('change', '.attribute-select', function () {
    // Reset subsequent dropdowns
    resetSubsequentDropdowns(this);

    // Collect selected attribute values
    let selectedValues = {};
    let allSelected = true;

    $('.attribute-select').each(function () {
        let attributeId = $(this).data('attribute-id');
        let selectedValue = $(this).val();

        if (selectedValue) {
            selectedValues[attributeId] = selectedValue;
        } else {
            allSelected = false;
        }
    });

    // Trigger only if all attributes are selected and there's a change
    if (allSelected && JSON.stringify(selectedValues) !== JSON.stringify(lastSelectedValues)) {
        // Update global lastSelectedValues
        lastSelectedValues = { ...selectedValues };

        // Retrieve variation parent part number
        let variationParentPartNo = $('#variation_parent_part_no').val();

        // AJAX call to get the product ID
        $.ajax({
            url: '{{ route("getProductId") }}',
            type: 'POST',
            data: {
                _token: AIZ.data.csrf,
                selected_values: selectedValues,
                variation_parent_part_no: variationParentPartNo
            },
            success: function (response) {
                if (response.success) {
                    // Call modal with the retrieved product ID
                    showAddToCartModal(response.data);
                } else {
                    alert(response.message || 'No matching product found!');
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching product ID:', error);
                AIZ.plugins.notify('danger', 'An error occurred while fetching the product.');
            }
        });
    }
});

</script>
