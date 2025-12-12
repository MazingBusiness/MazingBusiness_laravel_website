<div class="modal-body p-4 c-scrollbar-light">
  <div class="row pr-2">
    <div class="col-lg-6">
      <div class="row">
        @php
          $photos = explode(',', $product->photos);
        @endphp
        <div class="col">
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
                  test
                  <img class="img-fluid lazyload"
                    src="{{ url('public/assets/img/placeholder.jpg') }}" 
                    data-src="{{ $stock_image_path }}"
                    onerror="this.onerror=null;this.src='{{ url('public/assets/img/placeholder.jpg') }}';">
                  <!-- <img class="img-fluid lazyload" src="{{ static_asset('assets/img/placeholder.jpg') }}"
                    data-src="{{ uploaded_asset($stock->image) }}"
                    onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"> -->
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
            @if ($est['immediate'])
              <br><br><span class="bg-danger text-white rounded mt-2 px-4 py-1">Dispatch in 24 hours</span>
            @endif
          @endif
        @endif
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
        <hr>
        <form id="option-choice-form">
          @csrf
          <input type="hidden" name="id" value="{{ $product->id }}">
          @if ($product->choice_options != null)
            @foreach (json_decode($product->choice_options) as $key => $choice)
              @php $attribute = \App\Models\Attribute::find($choice->attribute_id) @endphp
              @if (count($choice->values))
                <div class="row no-gutters">
                  <div class="col-6 col-sm-6">
                    <div class="opacity-50 my-2">
                      {{ $attribute->getTranslation('name') }}:
                    </div>
                  </div>
                  <div class="col-6 col-sm-6 d-flex align-items-center">
                    <div class="aiz-radio-inline">
                      <input type="hidden" name="attribute_id_{{ $choice->attribute_id }}"
                        value="{{ $choice->values[0] }}">
                      <div class="my-2">
                        @foreach ($choice->values as $key => $value)
                          {{ $value }}
                        @endforeach
                      </div>
                    </div>
                  </div>
                </div>
              @else
                <input type="hidden" name="attribute_id_{{ $choice->attribute_id }}" value="">
              @endif
            @endforeach
            <hr>
          @endif
          @if (count(json_decode($product->colors)) > 0)
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
            <hr>
          @endif
        </form>
      </div>
    </div>
    <div class="col-12">
      <div class="bg-white mb-3 mt-3 shadow-sm rounded">
        <div class="nav border-bottom aiz-nav-tabs">
          <a href="#tab_default_1" data-toggle="tab"
            class="p-3 fs-16 fw-600 text-reset active show">{{ translate('Description') }}</a>
          @if ($product->video_link != null)
            <a href="#tab_default_2" data-toggle="tab" class="p-3 fs-16 fw-600 text-reset">{{ translate('Video') }}</a>
          @endif
          @if ($product->pdf != null)
            <a href="#tab_default_3" data-toggle="tab"
              class="p-3 fs-16 fw-600 text-reset">{{ translate('Downloads') }}</a>
          @endif
          <a href="#tab_default_4" data-toggle="tab" class="p-3 fs-16 fw-600 text-reset">{{ translate('Reviews') }}</a>
        </div>

        <div class="tab-content pt-0">
          <div class="tab-pane fade active show" id="tab_default_1">
            <div class="p-4">
              <div class="mw-100 overflow-auto text-left aiz-editor-data">
                <?php echo $product->getTranslation('description'); ?>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="tab_default_2">
            <div class="p-4">
              <div class="embed-responsive embed-responsive-16by9">
                @if ($product->video_provider == 'youtube' && isset(explode('=', $product->video_link)[1]))
                  <iframe class="embed-responsive-item"
                    src="https://www.youtube.com/embed/{{ get_url_params($product->video_link, 'v') }}"></iframe>
                @elseif ($product->video_provider == 'dailymotion' && isset(explode('video/', $product->video_link)[1]))
                  <iframe class="embed-responsive-item"
                    src="https://www.dailymotion.com/embed/video/{{ explode('video/', $product->video_link)[1] }}"></iframe>
                @elseif ($product->video_provider == 'vimeo' && isset(explode('vimeo.com/', $product->video_link)[1]))
                  <iframe src="https://player.vimeo.com/video/{{ explode('vimeo.com/', $product->video_link)[1] }}"
                    width="500" height="281" frameborder="0" webkitallowfullscreen mozallowfullscreen
                    allowfullscreen></iframe>
                @endif
              </div>
            </div>
          </div>
          <div class="tab-pane fade" id="tab_default_3">
            <div class="p-4 text-center ">
              <a href="{{ uploaded_asset($product->pdf) }}" class="btn btn-primary">{{ translate('Download') }}</a>
            </div>
          </div>
          <div class="tab-pane fade" id="tab_default_4">
            <div class="p-4">
              <ul class="list-group list-group-flush">
                @foreach ($product->reviews as $key => $review)
                  <li class="media list-group-item d-flex">
                    <span class="avatar avatar-md mr-3">
                      <img class="lazyload" src="{{ static_asset('assets/img/placeholder.jpg') }}"
                        onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"
                        @if ($review->user && $review->user->avatar_original != null) data-src="{{ uploaded_asset($review->user->avatar_original) }}"
                                            @else
                                                data-src="{{ static_asset('assets/img/placeholder.jpg') }}" @endif>
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

              @if (count($product->reviews) <= 0)
                <div class="text-center fs-18 opacity-70">
                  {{ translate('There have been no reviews for this product yet.') }}
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
