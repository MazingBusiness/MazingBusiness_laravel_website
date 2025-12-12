@extends('frontend.layouts.app')

@section('content')

  <section class="pt-4 mb-4">
    <div class="container text-center">
      <div class="row">
        <div class="col-lg-6 text-center text-lg-left">
          <h1 class="fw-600 h4">{{ translate('Compare') }}</h1>
        </div>
        <div class="col-lg-6">
          <ul class="breadcrumb bg-transparent p-0 justify-content-center justify-content-lg-end">
            <li class="breadcrumb-item opacity-50">
              <a class="text-reset" href="{{ route('home') }}">{{ translate('Home') }}</a>
            </li>
            <li class="text-dark fw-600 breadcrumb-item">
              <a class="text-reset" href="{{ route('compare.reset') }}">"{{ translate('Compare') }}"</a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <section class="mb-4">
    <div class="container text-left">
      <div class="bg-white shadow-sm rounded">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
          <div class="fs-15 fw-600">{{ translate('Comparison') }}</div>
          <a href="{{ route('compare.reset') }}" style="text-decoration: none;"
            class="btn btn-soft-primary btn-sm fw-600">{{ translate('Reset Compare List') }}</a>
        </div>
        <div class="pb-4">
          @if (Session::has('compare'))
            @if (count(Session::get('compare')) > 0)
              @php
                $categoried_products = \App\Models\Product::select('id', 'name', 'thumbnail_img', 'brand_id', 'category_id', 'slug', 'choice_options')
                    ->with('brand:id,name', 'category:id,name', 'category.attributes')
                    ->whereIn('id', Session::get('compare'))
                    ->get()
                    ->groupBy('category.name');
              @endphp
              @foreach ($categoried_products as $key => $category)
                <h4 class="mt-5 ml-4">{{ $key }}</h4>
                <div class="ml-4 mr-4 overflow-auto">
                  <table class="table table-bordered mb-0">
                    <thead>
                      <tr>
                        <th scope="col" style="min-width:150px" class="font-weight-bold">
                          {{ translate('Name') }}
                        </th>
                        @foreach ($category as $product)
                          <th scope="col" style="min-width:240px" class="font-weight-bold">
                            <a class="text-reset fs-15"
                              href="{{ route('product', $product->slug) }}">{{ $product->name }}</a>
                          </th>
                        @endforeach
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <th scope="row">{{ translate('Image') }}</th>
                        @foreach ($category as $product)
                          <td>
                          @php
                            // Fetch the base URL for uploads from the .env file
                            $uploads_base_url = env('UPLOADS_BASE_URL', url('public'));

                            // Fetch file_name for the product thumbnail image (assuming $product->thumbnail_img contains the ID of the upload)
                            $product_thumbnail = \App\Models\Upload::where('id', $product->thumbnail_img)->value('file_name');
                            $product_thumbnail_path = $product_thumbnail
                                        ? $uploads_base_url . '/' . $product_thumbnail
                                        : url('public/assets/img/placeholder.jpg');
                          @endphp
                          <img loading="lazy" src="{{ $product_thumbnail_path }}"
                            alt="{{ translate('Product Image') }}" class="img-fluid py-4">

                            <!-- <img loading="lazy" src="{{ uploaded_asset($product->thumbnail_img) }}"
                              alt="{{ translate('Product Image') }}" class="img-fluid py-4"> -->
                          </td>
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="row">{{ translate('Price') }}</th>
                        @foreach ($category as $product)
                          <td>
                            @if (Auth::check())
                              @if (Auth::user()->warehouse_id)
                                @if (home_base_price($product) != home_discounted_base_price($product))
                                  <del class="fw-600 opacity-50 mr-1">{{ home_base_price($product) }}</del>
                                @endif
                                <span class="fw-700 text-primary">{{ home_discounted_base_price($product) }}</span>
                              @else
                                <small><a href="{{ route('user.registration') }}">Complete profile to check
                                    prices.</a></small>
                              @endif
                            @else
                              <small><a href="{{ route('user.registration') }}"
                                  class="btn btn-sm btn-primary btn-block">Register to check prices</a></small>
                            @endif
                          </td>
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="row">{{ translate('Brand') }}</th>
                        @foreach ($category as $product)
                          <td>
                            @if ($product->brand != null)
                              {{ $product->brand->name }}
                            @endif
                          </td>
                        @endforeach
                      </tr>
                      <tr>
                        <th scope="row">{{ translate('Category') }}</th>
                        @foreach ($category as $product)
                          <td>
                            @if ($product->category != null)
                              {{ $product->category->name }}
                            @endif
                          </td>
                        @endforeach
                      </tr>
                      @php
                        $attributes = $category->first()->category->attributes;
                      @endphp
                      @foreach ($attributes as $attribute)
                        <tr>
                          <th scope="row">{{ $attribute->name }}</th>
                          @foreach ($category as $product)
                            @php $options = collect(json_decode($product->choice_options)); @endphp
                            <td>{{ implode(',', $options->where('attribute_id', $attribute->id)->first()->values) }}</td>
                          @endforeach
                        </tr>
                      @endforeach
                      <tr>
                        <th scope="row"></th>
                        @foreach ($category as $product)
                          <td class="text-center py-4">
                            <button type="button" class="btn btn-primary fw-600"
                              onclick="showAddToCartModal({{ $product }})">
                              {{ translate('Add to cart') }}
                            </button>
                          </td>
                        @endforeach
                      </tr>
                    </tbody>
                  </table>
                </div>
              @endforeach
            @endif
          @else
            <div class="text-center p-4">
              <p class="fs-17">{{ translate('Your comparison list is empty') }}</p>
            </div>
          @endif
        </div>
      </div>
    </div>
  </section>

@endsection
