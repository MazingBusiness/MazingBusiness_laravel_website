@php
  // Fetch base URL for uploads from the .env file
  $baseUrl = env('UPLOADS_BASE_URL', url('public'));

  // Fetch file_name for the brand logo
  $brand_logo = \App\Models\Upload::where('id', $brand->logo)->value('file_name');
  $brand_logo_path = $brand_logo
              ? $baseUrl . '/' . $brand_logo
              : url('public/assets/img/placeholder.jpg');
@endphp


<div class="aiz-card-box border border-light hov-shadow-md mt-1 mb-2 has-transition bg-white">
  <div class="position-relative">
    <a href="{{ route('products.brand', $brand->slug) }}" class="d-block">
      <!-- <img class="img-contain lazyload mx-auto h-140px h-md-210px" src="{{ static_asset('assets/img/placeholder.jpg') }}"
        data-src="{{ uploaded_asset($brand->logo) }}" alt="{{ $brand->getTranslation('name') }}"
        onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
        <img class="img-contain lazyload mx-auto h-140px h-md-210px" 
          src="{{ url('public/assets/img/placeholder.jpg') }}"
          data-src="{{ $brand_logo_path }}" 
          alt="{{ $brand->getTranslation('name') }}"
          onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">

    </a>
  </div>
</div>
