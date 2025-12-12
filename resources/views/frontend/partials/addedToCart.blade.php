<div class="modal-body p-4 added-to-cart">
  <div class="text-center text-success mb-4">
    <i class="las la-check-circle la-3x"></i>
    <h3>{{ translate('Item added to your cart!') }}</h3>
  </div>
  <div class="media mb-4">
  @php
  // Fetch the base URL for uploads from the .env file
  $uploads_base_url = env('UPLOADS_BASE_URL', url('public'));

  // Fetch file_name for the product thumbnail image (assuming $product->thumbnail_img contains the ID of the upload)
  $product_thumbnail = \App\Models\Upload::where('id', $product->thumbnail_img)->value('file_name');
  $product_thumbnail_path = $product_thumbnail
              ? $uploads_base_url . '/' . $product_thumbnail
              : url('public/assets/img/placeholder.jpg');
@endphp
<img src="{{ url('public/assets/img/placeholder.jpg') }}"
     data-src="{{ $product_thumbnail_path }}"
     class="mr-3 lazyload size-100px img-fit rounded" 
     alt="Product Image">

    <!-- <img src="{{ static_asset('assets/img/placeholder.jpg') }}" data-src="{{ uploaded_asset($product->thumbnail_img) }}"
      class="mr-3 lazyload size-100px img-fit rounded" alt="Product Image"> -->
    <div class="media-body pt-3 text-left">
      <h6 class="fw-600">
        {{ $product->getTranslation('name') }}
      </h6>
      <div class="row mt-3">
        <div class="col-sm-2 opacity-60">
          <div>{{ translate('Price') }}:</div>
        </div>
        <div class="col-sm-10">
          <div class="h6 text-primary">
          <?php /* @if ($data['quantity'] >= $product->piece_by_carton)
              <!-- Code to execute if the condition is true -->
              @php
                $data['price'] = ceil($data['price'] * 0.98);
              @endphp
          @endif */ ?>
            <strong>
              {{ format_price_in_rs(($data['price']) * $data['quantity']) }}
            </strong>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="text-center">
    <button class="btn btn-outline-primary mb-3 mb-sm-0"
      data-dismiss="modal">{{ translate('Back to shopping') }}</button>
    <a href="{{ route('cart') }}" class="btn btn-primary mb-3 mb-sm-0">{{ translate('Proceed to Checkout') }}</a>
  </div>
</div>
