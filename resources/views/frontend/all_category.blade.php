@extends('frontend.layouts.app')

@section('content')
<section class="pt-4 mb-4">
  <div class="container-fluid text-center">
    <div class="row">
      <div class="col-lg-6 text-center text-lg-left">
        <h1 class="fw-600 h4">{{ translate('All Categories') }}</h1>
      </div>
      <div class="col-lg-6">
        <ul class="breadcrumb bg-transparent p-0 justify-content-center justify-content-lg-end">
          <li class="breadcrumb-item opacity-50">
            <a class="text-reset" href="{{ route('home') }}">{{ translate('Home') }}</a>
          </li>
          <li class="text-dark fw-600 breadcrumb-item">
            <a class="text-reset" href="{{ route('categories.all') }}">{{ translate('All Categories') }}</a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="container-fluid">
    <div id="accordion">
      @foreach ($categories as $category)
      <div class="card mb-3">
        <div class="card-header" id="heading{{ $category->id }}">
          <h5 style="font-weight: 600; text-decoration: none;" class="mb-0">
            <button class="btn btn-link" data-toggle="collapse" data-target="#collapse{{ $category->id }}" aria-expanded="true" aria-controls="collapse{{ $category->id }}">
              {{ $category->name }}
            </button>
          </h5>
        </div>
        <div id="collapse{{ $category->id }}" class="collapse" aria-labelledby="heading{{ $category->id }}" data-parent="#accordion">
          <div class="card-body">
            <div class="row">
              @foreach ($category->sub as $sub_cat)
              <div class="col-lg-3 col-6">
                <ul class="list-unstyled">
                  <li>
                   @if($sub_cat && $sub_cat->slug)
                      <a href="{{ route('products.category', ['category_slug' => $sub_cat->slug]) }}" class="text-reset">{{ $sub_cat->name }}</a>
                  @else
                      <p>Category not available</p>
                      <!-- This will show the data in $sub_cat -->
                  @endif                                                                            

                  </li>
                </ul>
              </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</section>

@endsection
