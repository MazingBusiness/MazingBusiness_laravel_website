
@extends('frontend.layouts.app')

@php
  use Illuminate\Support\Facades\DB;
  if (!session('view')) {
      session(['view' => 'list']);
  }
@endphp
@if (isset($category_id))
  @php
    $meta_title = \App\Models\Category::find($category_id)->meta_title;
    $meta_description = \App\Models\Category::find($category_id)->meta_description;
    $page_description = \App\Models\Category::find($category_id)->page_description;
  @endphp
@elseif (isset($brand_id))
  @php
    $meta_title = \App\Models\Brand::find($brand_id)->meta_title;
    $meta_description = \App\Models\Brand::find($brand_id)->meta_description;
    $page_description = \App\Models\Brand::find($brand_id)->page_description;
  @endphp
@else
  @php
    $meta_title = get_setting('meta_title');
    $meta_description = get_setting('meta_description');
    $page_description = '';
  @endphp
@endif

@section('meta_title'){{ $meta_title }}@stop
@section('meta_description'){{ $meta_description }}@stop

@section('meta')
  <!-- Schema.org markup for Google+ -->
  <meta itemprop="name" content="{{ $meta_title }}">
  <meta itemprop="description" content="{{ $meta_description }}">

  <!-- Twitter Card data -->
  <meta name="twitter:title" content="{{ $meta_title }}">
  <meta name="twitter:description" content="{{ $meta_description }}">

  <!-- Open Graph data -->
  <meta property="og:title" content="{{ $meta_title }}" />
  <meta property="og:description" content="{{ $meta_description }}" />
@endsection

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
  <section class="mb-4 pt-3">
    <div class="container-fluid sm-px-0">
      <form class="" id="search-form" action="" method="GET">
        <div class="row">
          <div class="col-xl-4">
            <div class="aiz-filter-sidebar collapse-sidebar-wrap sidebar-xl sidebar-right z-1035">
              <div class="overlay overlay-fixed dark c-pointer" data-toggle="class-toggle"
                data-target=".aiz-filter-sidebar" data-same=".filter-sidebar-thumb"></div>
              <div class="collapse-sidebar c-scrollbar-light text-left">
                <div class="d-flex d-xl-none justify-content-between align-items-center pl-3 border-bottom">
                  <h3 class="h6 mb-0 fw-600">{{ translate('Filters') }}</h3>
                  <button type="button" class="btn btn-sm p-2 filter-sidebar-thumb" data-toggle="class-toggle"
                    data-target=".aiz-filter-sidebar">
                    <i class="las la-times la-2x"></i>
                  </button>
                </div>
                <div class="bg-white shadow-sm rounded mb-3">
                  <div class="fs-15 fw-600 p-3 border-bottom">
                    <a href="#collapse_1" class="dropdown-toggle filter-section text-dark" data-toggle="collapse">
                      {{ translate('Categories') }}
                    </a>
                  </div>

                  <div class="collapse show" id="collapse_1" style="max-height: calc(100vh - 400px); overflow:scroll;">
                    <ul class="p-0 list-unstyled">
                      @if (!isset($category_id))
                        {{-- @foreach (\App\Models\Category::where('level', 0)->get() as $category) --}}
                        <ul class="p-2 list-unstyled">
                            @foreach ($categoryGroup as $category)
                              @php
                                // Fetch base URL for uploads from the .env file
                                $baseUrl = env('UPLOADS_BASE_URL', url('public'));

                                // Fetch file_name for the category icon (assuming $category->icon contains the ID of the upload)
                                $category_icon = \App\Models\Upload::where('id', $category->icon)->value('file_name');
                                $category_icon_path = $category_icon
                                            ? $baseUrl . '/' . $category_icon
                                            : url('public/assets/img/placeholder.jpg');
                                             $isExpanded = isset($category_group_id) && $category->id == $category_group_id;
                              @endphp


                                <li style="" class="mb-2 ml-1 fs-13 fw-600">
                                     <a style="color:#666666; text-decoration: none;" 
                                         href="{{ route('group.category.products', ['category_group__id' => $category->id]) }}">
                                          <img class="cat-image lazyload mr-2 opacity-70" 
                                               src="{{ url('public/assets/img/placeholder.jpg') }}" 
                                               data-src="{{ $category_icon_path }}" 
                                               width="16" 
                                               alt="{{ $category->name }}" 
                                               onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">
                                          {{ $category->name }}
                                      </a>
                                      <!-- Collapsible Arrow -->
                                      <span class="arrow category-toggle {{ $isExpanded ? '' : 'collapsed' }}" 
                                            data-toggle="collapse" 
                                            data-target="#category_{{ $category->id }}" 
                                            aria-expanded="{{ $isExpanded ? 'true' : 'false' }}">
                                      </span>
                                    <ul id="category_{{ $category->id }}" class="collapse sub-categories ml-3"> <!-- Collapse sub-categories -->
                                        @php
                                          /*$categories = \App\Models\Category::where('level', 0)->where('category_group_id',$category->id)->get()*/
                                          $categories = DB::table('products')
                                              ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                                              ->where('products.part_no','!=','')
                                              ->where('products.current_stock','>','0')
                                              ->where('categories.category_group_id',$category->id)
                                              ->where('categories.level','0')
                                              ->orderBy('categories.name', 'asc')
                                              ->select('categories.id', 'categories.name', 'categories.slug', 'categories.icon')
                                              ->distinct()->get();
                                        @endphp
                                        @foreach($categories as $subcategory)
                                          @php
                                            // Fetch file_name for the subcategory icon (assuming $subcategory->icon contains the ID of the upload)
                                            $subcategory_icon = \App\Models\Upload::where('id', $subcategory->icon)->value('file_name');
                                            $subcategory_icon_path = $subcategory_icon
                                                        ? url('public/' . $subcategory_icon)
                                                        : url('public/assets/img/placeholder.jpg');
                                          @endphp

                                          
                                            <li>
                                            <img class="cat-image lazyload mr-2 opacity-70" 
                                              src="{{ url('public/assets/img/placeholder.jpg') }}" 
                                              data-src="{{ $subcategory_icon_path }}" 
                                              width="16" 
                                              alt="{{ $subcategory->name }}" 
                                              onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">

                                                <!-- <img class="cat-image lazyload mr-2 opacity-70" src="{{ static_asset('assets/img/placeholder.jpg') }}" data-src="{{ uploaded_asset($subcategory->icon) }}" width="16" alt="{{ $subcategory->name }}" onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                                                @if (!empty($subcategory->slug))
													<a class="text-reset fs-12 fw-400" href="{{ route('products.category', ['category_slug' => $subcategory->slug]) }}">
														{{ $subcategory->name }}
													</a>
												@else
													<span class="text-muted fs-12 fw-400">Category Unavailable</span>
												@endif

                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endforeach
                        </ul>

                        <style>
                            .arrow {
                                float: right;
                                margin-top: 3px; /* Adjust vertical alignment */
                                border: solid #666666;
                                border-width: 0 1px 1px 0;
                                display: inline-block;
                                padding: 3px;
                                transform: rotate(-45deg);
                                transition: transform 0.4s;
                            }

                            .collapsed .arrow {
                                transform: rotate(135deg);
                            }
                        </style>


                      @else
                        <li class="mb-2">
                          <a class="text-reset fs-14 fw-600" href="{{ route('search') }}">
                            <i class="las la-angle-left"></i>
                            {{ translate('All Categories') }}
                          </a>
                        </li>
                        @if (\App\Models\Category::find($category_id)->parent_id != 0)
                          <li class="mb-2">
                            <a class="text-reset fs-14 fw-600"
                              href="{{ route('products.category', \App\Models\Category::find(\App\Models\Category::find($category_id)->parent_id)->slug) }}">
                              <i class="las la-angle-left"></i>
                              {{ \App\Models\Category::find(\App\Models\Category::find($category_id)->parent_id)->getTranslation('name') }}
                            </a>
                          </li>
                        @endif

                        <ul class="list-unstyled pl-3">
                          <li class="mb-2">
                            <a class="text-reset fs-14 fw-600"
                              href="{{ route('products.category', \App\Models\Category::find($category_id)->slug) }}">
                              <i class="las la-angle-left"></i>                              
                              @php
                                $group_id=\App\Models\Category::find($category_id)->category_group_id;
                                $cat_group = \App\Models\CategoryGroup::find($group_id);
                              @endphp
                              @php
                                // Fetch file_name for the category group icon (assuming $cat_group->icon contains the ID of the upload)
                                $cat_group_icon = \App\Models\Upload::where('id', $cat_group->icon)->value('file_name');
                                $cat_group_icon_path = $cat_group_icon
                                            ? url('public/' . $cat_group_icon)
                                            : url('public/assets/img/placeholder.jpg');
                              @endphp
                              <img class="cat-image lazyload mr-2 opacity-70" 
                                src="{{ url('public/assets/img/placeholder.jpg') }}" 
                                data-src="{{ $cat_group_icon_path }}" 
                                width="20" 
                                alt="{{ $cat_group->name }}" 
                                onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">

                              <!-- <img class="cat-image lazyload mr-2 opacity-70" src="{{ static_asset('assets/img/placeholder.jpg') }}" data-src="{{ uploaded_asset($cat_group->icon) }}" width="20" alt="{{ $cat_group->name }}" onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->

                              {{ ucwords(Str::lower($cat_group->name)) }}
                            </a>
                          </li>

                          {{-- create at 28-06-2024 --}}
                              <ul class="list-unstyled pl-3">
                                @php
                                    $group_id=\App\Models\Category::find($category_id)->category_group_id;

                                    /*$subCat = \App\Models\Category::where('category_group_id', $group_id)
                                        ->orderBy('name', 'asc')
                                        ->get();*/
                                    $subCat = DB::table('products')
                                              ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                                              ->where('products.part_no','!=','')
                                              ->where('products.current_stock','>','0')
                                              ->where('categories.category_group_id',$group_id)
                                              ->orderBy('categories.name', 'asc')
                                              ->select('categories.id', 'categories.name', 'categories.slug', 'categories.icon')
                                              ->distinct()->get();

                                    $currentRoute = \Request::route()->getName(); // Get current route name
                                @endphp

                                @foreach ($subCat as $cat)
                                    <li class="mb-2">
                                        <a style="color:@if ($currentRoute === 'products.category' && \Route::current()->parameter('slug') === $cat->slug) #007bff !important; @endif" class="text-reset fs-14 fw-400  "
                                          href="{{ route('products.category', $cat->slug) }}">
                                          @php
                                            // Fetch file_name for the category icon (assuming $cat->icon contains the ID of the upload)
                                            $category_icon = \App\Models\Upload::where('id', $cat->icon)->value('file_name');
                                            $category_icon_path = $category_icon
                                                        ? url('public/' . $category_icon)
                                                        : url('public/assets/img/placeholder.jpg');
                                          @endphp
                                          <img class="cat-image lazyload mr-2 opacity-70" 
                                            src="{{ url('public/assets/img/placeholder.jpg') }}" 
                                            data-src="{{ $category_icon_path }}" 
                                            width="16" 
                                            alt="{{ $cat->name }}" 
                                            onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">

                                          <!-- <img class="cat-image lazyload mr-2 opacity-70" src="{{ static_asset('assets/img/placeholder.jpg') }}" data-src="{{ uploaded_asset($cat->icon) }}" width="16" alt="{{ $cat->name }}" onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
                                            {{ $cat->name }} 
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </ul>
                        {{-- create at 28-06-2024 --}}



                        @foreach (\App\Utility\CategoryUtility::get_immediate_children($category_id) as $key => $cat)
                          @if ($cat->loadCount('products')->products_count)
                            <li class="ml-4 mb-2">
                              <a class="text-reset fs-14"
                                href="{{ route('products.category', \App\Models\Category::find($cat->id)->slug) }}">{{ \App\Models\Category::find($cat->id)->getTranslation('name') }}</a>
                            </li>
                          @endif
                        @endforeach
                      @endif
                    </ul>
                  </div>
                </div>


                @unless ($query)
                  <div class="bg-white shadow-sm rounded mb-3">
                    <div class="fs-15 fw-600 p-3 border-bottom">
                      {{ translate('Price Range') }}
                    </div>
                    @php
                      $min_price = $min_price ? $min_price : $min_total;
                      $max_price = $max_price ? $max_price : $max_total;
                    @endphp
                    <div class="p-3">
                      <div class="aiz-range-slider">
                        <div id="input-slider-range" data-range-value-min="{{ $min_total }}"
                          data-range-value-max="{{ $max_total }}">
                        </div>
                        <div class="row mt-2">
                          <div class="col-6">
                            <span class="range-slider-value value-low fs-14 fw-600 opacity-70"
                              data-range-value-low="{{ $min_price }}" id="input-slider-range-value-low"></span>
                          </div>
                          <div class="col-6 text-right">
                            <span class="range-slider-value value-high fs-14 fw-600 opacity-70"
                              data-range-value-high="{{ $max_price }}" id="input-slider-range-value-high"></span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                @endunless

                @foreach ($attributes as $attribute)
                  @php
                    if (isset($category_id)) {
                        $attrs = \App\Models\Product::select('choice_options')
                            ->where('category_id', $category_id)
                            ->get();


                    } elseif (isset($brand_id)) {
                        $attrs = \App\Models\Product::select('choice_options')
                            ->where('brand_id', $brand_id)
                            ->get();

                    } else {
                        $attrs = [];

                    }
                    // $list = [];
                    // foreach ($attrs as $attr) {
                    //     $list = array_merge(
                    //         $list,
                    //         collect(json_decode($attr->choice_options))
                    //             ->where('attribute_id', $attribute->id)
                    //             ->first()->values,
                    //     );
                    //     $list = array_unique($list);
                    // }

                    $list = [];

                    foreach ($attrs as $attr) {
                        // Decode the JSON attribute options, handle potential errors gracefully
                        $options = json_decode($attr->choice_options);

                        if ($options !== null) {
                            // Filter options based on attribute_id
                            $filteredOptions = collect($options)
                                ->where('attribute_id', $attribute->id)
                                ->first();

                            // Check if $filteredOptions is not null before accessing ->values
                            if ($filteredOptions !== null && isset($filteredOptions->values)) {
                                // Merge the values into $list
                                $list = array_merge($list, $filteredOptions->values);
                            }
                        }
                    }

                    // Remove duplicates from $list
                    $list = array_unique($list);


                  @endphp


                  @if ((!$attrs->isEmpty() && $attrs) || (count($attribute->attribute_values) && count($attribute->attribute_values) > 1))
                    <div class="bg-white shadow-sm rounded mb-3">
                      <div class="fs-15 fw-600 p-3 border-bottom">
                        <a href="#" class="dropdown-toggle text-dark filter-section collapsed"
                          data-toggle="collapse"
                          data-target="#collapse_{{ preg_replace('/[^a-zA-Z0-9_]/s', '', strtolower($attribute->name)) }}"
                          style="overflow: hidden;white-space: nowrap;text-overflow: ellipsis; display: inherit">
                          {{ translate('Filter by') }} {{ $attribute->getTranslation('name') }}
                        </a>
                      </div>
                      <div class="collapse"
                        id="collapse_{{ preg_replace('/[^a-zA-Z0-9_]/s', '', strtolower($attribute->name)) }}">
                        <div class="p-3  aiz-checkbox-list">
                          @if ($attrs->isEmpty() || !$attrs)
                            @foreach ($attribute->attribute_values as $attribute_value)
                              <label class="aiz-checkbox">
                                <input type="checkbox" name="selected_attribute_values[]"
                                  value="{{ $attribute_value->value }}" @if (in_array($attribute_value->value, $selected_attribute_values)) checked @endif
                                  onchange="filter()">
                                <span class="aiz-square-check"></span>
                                <span>{{ $attribute_value->value }}</span>
                              </label>
                            @endforeach
                          @else
                            @foreach ($list as $attribute_value)
                              <label class="aiz-checkbox">
                                <input type="checkbox" name="selected_attribute_values[]" value="{{ $attribute_value }}"
                                  @if (in_array($attribute_value, $selected_attribute_values)) checked @endif onchange="filter()">
                                <span class="aiz-square-check"></span>
                                <span>{{ $attribute_value }}</span>
                              </label>
                            @endforeach
                          @endif
                        </div>
                      </div>
                    </div>
                  @endif
                @endforeach

                @if (get_setting('color_filter_activation'))
                  <div class="bg-white shadow-sm rounded mb-3">
                    <div class="fs-15 fw-600 p-3 border-bottom">
                      <a href="#" class="dropdown-toggle text-dark filter-section collapsed"
                        data-toggle="collapse" data-target="#collapse_color">
                        {{ translate('Filter by color') }}
                      </a>
                    </div>
                    <div class="collapse" id="collapse_color">
                      <div class="p-3 aiz-radio-inline">
                        @foreach ($colors as $key => $color)
                          <label class="aiz-megabox pl-0 mr-2" data-toggle="tooltip" data-title="{{ $color->name }}">
                            <input type="radio" name="color" value="{{ $color->code }}" onchange="filter()"
                              @if (isset($selected_color) && $selected_color == $color->code) checked @endif>
                            <span
                              class="aiz-megabox-elem rounded d-flex align-items-center justify-content-center p-1 mb-2">
                              <span class="size-30px d-inline-block rounded"
                                style="background: {{ $color->code }};"></span>
                            </span>
                          </label>
                        @endforeach
                      </div>
                    </div>
                  </div>
                @endif

                {{-- <button type="submit" class="btn btn-styled btn-block btn-base-4">Apply filter</button> --}}
              </div>
            </div>
          </div>
          <div class="col-xl-8">



            <ul class="breadcrumb bg-transparent p-0">
              <li class="breadcrumb-item opacity-50">
                <a class="text-reset" href="{{ route('home') }}">{{ translate('Home') }}</a>
              </li>
              @if (!isset($category_id))
                <li class="breadcrumb-item fw-600  text-dark">
                  <a class="text-reset" href="{{ route('search') }}">"{{ translate('All Categories') }}"</a>
                </li>
              @else
                <li class="breadcrumb-item opacity-50">
                  <a class="text-reset" href="{{ route('search') }}">{{ translate('All Categories') }}</a>
                </li>
                <li class="breadcrumb-item opacity-50">
                  @php
                    $group_id=\App\Models\Category::find($category_id)->category_group_id;


                  @endphp
                  <a class="text-reset" href="{{ route('group.category.products', ['category_group__id' => $group_id]) }}">{{  \App\Models\CategoryGroup::find($group_id)->name }}</a>
                </li>
              @endif

              @if (isset($category_id))
                <li class="text-dark fw-600 breadcrumb-item">

                  <a class="text-reset"
                    href="{{ route('products.category', \App\Models\Category::find($category_id)->slug) }}">"{{ \App\Models\Category::find($category_id)->name }}"</a>
                </li>
              @endif
            </ul>

            <div class="text-left">
              <div class="row gutters-5 flex-wrap align-items-center">
                <div class="col-md-10 col-10 col-lg-auto flex-fill">
                  <h1 class="h4 mb-md-0 fw-600 text-body">
                    @if (isset($category_id))
                      {{-- {{ \App\Models\Category::find($category_id)->getTranslation('name') }} --}}
                      {{ \App\Models\Category::find($category_id)->name }}
                    @elseif(isset($query))
                      {{ translate('Search result for ') }}"{{ $query }}"
                    @else
                      {{ translate('All Products') }}
                    @endif
                  </h1>
                  <input type="hidden" name="keyword" value="{{ $query }}">
                </div>
                <div class="col-2 col-md-1 col-lg-auto d-xl-none text-right">
                  <button type="button" class="btn btn-icon p-0" data-toggle="class-toggle"
                    data-target=".aiz-filter-sidebar">
                    <i class="la la-filter la-2x"></i>
                  </button>
                </div>
                <div class="col-md-1 col-lg-auto text-right d-none d-md-block">
                  <button type="button" class="btn btn-icon p-0" id="switch-view"
                    data-current-view="{{ session('view') }}">
                    @if (session('view') == 'list')
                      <i class="la la-border-all la-2x"></i>
                    @else
                      <i class="la la-bars la-2x"></i>
                    @endif
                  </button>
                </div>
                <div class="col-6 col-lg-auto mb-3 w-lg-200px">
                  @if (Route::currentRouteName() != 'products.brand')
                    <label class="mb-0 opacity-50">{{ translate('Brands') }}</label>
                    <select class="form-control form-control-sm aiz-selectpicker" data-live-search="true"
                      name="brands[]" onchange="filter()" multiple data-selected='["{{ implode('","', $brands) }}"]'>
                      <option value="">{{ translate('All Brands') }}</option>
                      @foreach (\App\Models\Brand::whereHas('products')->whereIn('id', $id_brand)->get() as $brand)
                        <option value="{{ $brand->slug }}"
                          @isset($brand_id) @if ($brand_id == $brand->id) selected @endif @endisset>
                          {{ $brand->getTranslation('name') }}</option>
                      @endforeach
                    </select>
                  @endif
                </div>
                <div class="col-6 col-lg-auto mb-3 w-lg-200px">
                  <label class="mb-0 opacity-50">{{ translate('Sort by') }}</label>
                  <select class="form-control form-control-sm aiz-selectpicker" name="sort_by" onchange="filter()">
                    <option value="newest"
                      @isset($sort_by) @if ($sort_by == 'newest') selected @endif @endisset>
                      {{ translate('Newest') }}</option>
                    <option value="oldest"
                      @isset($sort_by) @if ($sort_by == 'oldest') selected @endif @endisset>
                      {{ translate('Oldest') }}</option>
                    @unless ($query)
                      <option value="price-asc"
                        @isset($sort_by) @if ($sort_by == 'price-asc') selected @endif @endisset>
                        {{ translate('Price low to high') }}</option>
                      <option value="price-desc"
                        @isset($sort_by) @if ($sort_by == 'price-desc') selected @endif @endisset>
                        {{ translate('Price high to low') }}</option>
                    @endunless
                  </select>
                </div>

                <div class="col-6 col-lg-auto mb-3 w-lg-200px">
                  <label class="mb-0 opacity-50">{{ translate('Delivery option') }}</label>
                  <select class="form-control form-control-sm aiz-selectpicker"  id="inhouseDropdown" name="inhouse"onchange="filter()">
                     <option  disabled selected>Select Delivery Time</option>
                      <option value="1">Delivery in  3 to 4 days</option>
                      <option value="2">Delivery in 6 to 7 days</option>
                  </select>
                </div>
              </div>
            </div>
            <input type="hidden" name="min_price" value="{{ $min_price }}">
            <input type="hidden" name="max_price" value="{{ $max_price }}">
            @if (session('view') == 'list')
              <div
               class="row gutters-5 @if (Auth::check() && Auth::user()->warehouse_id) row-cols-1 @else  row-cols-xxl-2 row-cols-md-2 row-cols-1 @endif">
                @foreach ($products as $key => $product)
                  <div class="col">
                    @include('frontend.partials.product_list_box', ['product' => $product])
                  </div>
                @endforeach
              </div>
            @else
              <div class="row gutters-5 row-cols-xxl-4 row-cols-xl-4 row-cols-lg-4 row-cols-md-3 row-cols-2">
                @foreach ($products as $key => $product)
                  <div class="col">
                    @include('frontend.partials.product_box_1', ['product' => $product])
                  </div>
                @endforeach
              </div>
            @endif
            <div class="aiz-pagination aiz-pagination-center mt-4">
              {{ $products->appends(request()->input())->links() }}
            </div>
          </div>
        </div>
      </form>
      @if ($page_description)
        <div class="row">
          <div class="col-12 category-description">
            {!! $page_description !!}
          </div>
        </div>
      @endif
    </div>
  </section>

@endsection

@section('script')
  <script type="text/javascript">
    function filter() {
      $('.ajax-loader').css("visibility", "visible");
      $('#search-form').submit();
    }

    $('#switch-view').on('click', function() {
      var btn = $(this);
      let url = window.location.href;
      if (btn.attr('data-current-view') == 'list') {
        if (url.indexOf('?') > -1) {
          url += '&view=grid'
        } else {
          url += '?view=grid'
        }
      } else {
        if (url.indexOf('?') > -1) {
          url += '&view=list'
        } else {
          url += '?view=list'
        }
      }
      window.location.href = url;
    });

    function rangefilter(arg) {
      $('input[name=min_price]').val(arg[0]);
      $('input[name=max_price]').val(arg[1]);
      filter();
    }
  </script>
@endsection



