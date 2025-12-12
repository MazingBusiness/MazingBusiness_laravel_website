@php
  // Fetch base URL for uploads from the .env file
  $baseUrl = env('UPLOADS_BASE_URL', url('public'));

  // Fetch file_name for the asset (assuming $asset contains the ID of the upload)
  $asset_image = \App\Models\Upload::where('id', $asset)->value('file_name');
  $asset_image_path = $asset_image
              ? $baseUrl . '/' . $asset_image
              : url('public/assets/img/placeholder.jpg');
@endphp


<div class="aiz-card-box border border-light hov-shadow-md mt-1 mb-2 has-transition bg-white rounded">
  <div class="position-relative">
    <a href="{{ route('suggestion.search', strtolower($name)) }}" class="d-block">
      <!-- <img class="img-fit lazyload mx-auto h-200px h-md-300px" src="{{ static_asset('assets/img/placeholder.jpg') }}"
        data-src="{{ uploaded_asset($asset) }}" alt="{{ __($name) }}"
        onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
        <img class="img-fit lazyload mx-auto h-200px h-md-300px" 
          src="{{ url('public/assets/img/placeholder.jpg') }}"
          data-src="{{ $asset_image_path }}" 
          alt="{{ __($name) }}"
          onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">

      <div class="text-center p-2 fw-600 fs-13 text-gray">{{ Str::upper($name) }}</div>
    </a>
  </div>
</div>
