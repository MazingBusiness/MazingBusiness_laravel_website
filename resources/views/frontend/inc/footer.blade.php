<section class="bg-white border-top mt-auto">
  <div class="container-fluid">
    <div class="row no-gutters">
      <div class="col-lg-3 col-6">
        <a class="text-reset text-center p-4 d-block" href="{{ route('terms') }}">
          <i class="la la-file-text la-3x text-primary mb-2"></i>
          <h4 class="h6">{{ translate('Terms & conditions') }}</h4>
        </a>
      </div>
      <div class="col-lg-3 col-6">
        <a class="text-reset text-center p-4 d-block" href="{{ route('returnpolicy') }}">
          <i class="la la-mail-reply la-3x text-primary mb-2"></i>
          <h4 class="h6">{{ translate('Return Policy') }}</h4>
        </a>
      </div>
      <div class="col-lg-3 col-6">
        <a class="text-reset text-center p-4 d-block" href="{{ route('shippingpolicy') }}">
          <i class="la la-truck la-3x text-primary mb-2"></i>
          <h4 class="h6">{{ translate('Shipping Policy') }}</h4>
        </a>
      </div>
      <div class="col-lg-3 col-6">
        <a class="text-reset text-center p-4 d-block" href="{{ route('privacypolicy') }}">
          <i class="las la-exclamation-circle la-3x text-primary mb-2"></i>
          <h4 class="h6">{{ translate('Privacy Policy') }}</h4>
        </a>
      </div>
    </div>
  </div>
</section>

<section class="mz-secondary py-5 text-light footer-widget" id="footerstart">
  <div class="container-fluid">
    <div class="row">
      <div class="col-lg-5 col-xl-4 text-center text-md-left">
        <div class="mt-4">
          <a href="{{ route('home') }}" class="d-block">
          @php
            // Fetch file_name for the footer logo (if `get_setting('footer_logo')` is not null)
            $footer_logo = get_setting('footer_logo') != null 
                          ? \App\Models\Upload::where('id', get_setting('footer_logo'))->value('file_name') 
                          : null;
            $footer_logo_path = $footer_logo 
                                ? url('public/' . $footer_logo) 
                                : url('public/assets/img/logo.png');
          @endphp

            <!-- @if (get_setting('footer_logo') != null)
              <img class="lazyload" src="{{ static_asset('assets/img/placeholder-rect.jpg') }}"
                data-src="{{ uploaded_asset(get_setting('footer_logo')) }}" alt="{{ env('APP_NAME') }}"
                height="25">
            @else
              <img class="lazyload" src="{{ static_asset('assets/img/placeholder-rect.jpg') }}"
                data-src="{{ static_asset('assets/img/logo.png') }}" alt="{{ env('APP_NAME') }}" height="25">
            @endif -->
            @if ($footer_logo != null)
              <img class="lazyload" src="{{ url('public/assets/img/placeholder-rect.jpg') }}"
                data-src="{{ $footer_logo_path }}" alt="{{ env('APP_NAME') }}" height="25">
            @else
              <img class="lazyload" src="{{ url('public/assets/img/placeholder-rect.jpg') }}"
                data-src="{{ url('public/assets/img/logo.png') }}" alt="{{ env('APP_NAME') }}" height="25">
            @endif

          </a>
          <div class="my-3">
            {!! get_setting('about_us_description', null, App::getLocale()) !!}
          </div>
          <div class="d-inline-block d-md-block mb-4">
            <form class="form-inline" method="POST" action="{{ route('subscribers.store') }}">
              @csrf
              <div class="form-group mb-0">
                <input type="email" class="form-control" placeholder="{{ translate('Your Email Address') }}"
                  name="email" required>
              </div>
              <button type="submit" class="btn btn-primary">
                {{ translate('Subscribe') }}
              </button>
            </form>
          </div>
          <div class="w-300px mw-100 mx-auto mx-md-0">
            @if (get_setting('play_store_link') != null)
              <a href="{{ get_setting('play_store_link') }}" target="_blank" class="d-inline-block mr-3 ml-0">
                <img src="{{ static_asset('assets/img/play.png') }}" class="mx-100 h-40px">
              </a>
            @endif
            @if (get_setting('app_store_link') != null)
              <a href="{{ get_setting('app_store_link') }}" target="_blank" class="d-inline-block">
                <img src="{{ static_asset('assets/img/app.png') }}" class="mx-100 h-40px">
              </a>
            @endif
          </div>
        </div>
      </div>
      <div class="col-lg-3 ml-xl-auto col-md-4 mr-0">
        <div class="text-center text-md-left mt-4">
          <h4 class="fs-13 text-uppercase fw-600 border-bottom border-white pb-2 mb-4">
            {{ translate('Contact Info') }}
          </h4>
          <ul class="list-unstyled">
            <li class="mb-2">
              <span class="d-block opacity-60">{{ translate('Address') }}:</span>
              <span class="d-block opacity-90">{{ get_setting('contact_address', null, App::getLocale()) }}</span>
            </li>
            <li class="mb-2">
              <span class="d-block opacity-60">{{ translate('Phone') }}:</span>
              <span class="d-block opacity-90">{{ get_setting('contact_phone') }}</span>
            </li>
            <li class="mb-2">
              <span class="d-block opacity-60">{{ translate('Email') }}:</span>
              <span class="d-block opacity-90">
                <a href="mailto:{{ get_setting('contact_email') }}"
                  class="text-reset">{{ get_setting('contact_email') }}</a>
              </span>
            </li>
          </ul>
        </div>
      </div>
      <div class="col-lg-2 col-md-4">
        <div class="text-center text-md-left mt-4">
          <h4 class="fs-13 text-uppercase fw-600 border-bottom border-white pb-2 mb-4">
            {{ get_setting('widget_one', null, App::getLocale()) }}
          </h4>
          <ul class="list-unstyled">
            @if (get_setting('widget_one_labels', null, App::getLocale()) != null)
              @foreach (json_decode(get_setting('widget_one_labels', null, App::getLocale()), true) as $key => $value)
                <li class="mb-2">
                  <a href="{{ json_decode(get_setting('widget_one_links'), true)[$key] }}"
                    class="opacity-70 hov-opacity-100 text-reset">
                    {{ $value }}
                  </a>
                </li>
              @endforeach
            @endif
          </ul>
        </div>
      </div>

      <div class="col-md-4 col-lg-2">
        <div class="text-center text-md-left mt-4">
          <h4 class="fs-13 text-uppercase fw-600 border-bottom border-white pb-2 mb-4">
            {{ translate('My Account') }}
          </h4>
          <ul class="list-unstyled">
            @if (Auth::check())
              <li class="mb-2">
                <a class="opacity-70 hov-opacity-100 text-reset" href="{{ route('logout') }}">
                  {{ translate('Logout') }}
                </a>
              </li>
            @else
              <li class="mb-2">
                <a class="opacity-70 hov-opacity-100 text-reset" href="{{ route('user.login') }}">
                  {{ translate('Login') }}
                </a>
              </li>
            @endif
            <li class="mb-2">
              <a class="opacity-70 hov-opacity-100 text-reset" href="{{ route('purchase_history.index') }}">
                {{ translate('Order History') }}
              </a>
            </li>
            <li class="mb-2">
              <a class="opacity-70 hov-opacity-100 text-reset" href="{{ route('wishlists.index') }}">
                {{ translate('My Wishlist') }}
              </a>
            </li>
            <li class="mb-2">
              <a class="opacity-70 hov-opacity-100 text-reset" href="{{ route('orders.track') }}">
                {{ translate('Track Order') }}
              </a>
            </li>
            @if (addon_is_activated('affiliate_system'))
              <li class="mb-2">
                <a class="opacity-70 hov-opacity-100 text-light"
                  href="{{ route('affiliate.apply') }}">{{ translate('Be an affiliate partner') }}</a>
              </li>
            @endif
          </ul>
        </div>
        @if (get_setting('vendor_system_activation') == 1)
          <div class="text-center text-md-left mt-4">
            <h4 class="fs-13 text-uppercase fw-600 border-bottom border-white pb-2 mb-4">
              {{ translate('Be a Seller') }}
            </h4>
            <a href="{{ route('shops.create') }}" class="btn btn-primary btn-sm shadow-md">
              {{ translate('Apply Now') }}
            </a>
          </div>
        @endif
      </div>
    </div>
    <div class="row mt-4">
      <div class="col-12 pb-3">
        <b>Mazing Store: The One-Stop Shopping Destination</b>
      </div>
      <div class="col-12 col-md-6 text-justify small pb-3">
        E-commerce is revolutionizing the way we all hardware stores in India. Why do you want to hop from one store to
        another in search of the latest welding machine when you can find it on the Internet with a single click? Not
        only welding machines and agricultural equipment. Mazing Store houses everything you can possibly imagine, from
        Power Tools like marble cutters, jigsaw machines, and die grinders to basic industrial staples and agricultural
        equipment’s such as screwdrivers, wrenches, and pliers to safety equipment such as shoes, slings, helmets, etc;
        from modern construction equipment to agricultural; from water pumps to generators; from cranes to chainsaws,
        including all sorts of accessories and spare parts, toolboxes that the industry has to offer, we’ve got them all
        covered. You name it, and you can be assured of finding it all in an e-commerce hardware stores site.
      </div>
      <div class="col-12 col-md-6 text-justify small pb-3">
        For those of you with erratic working hours, Mazing is your best bet. Shop in your PJs, at night or in the wee
        hours of the morning. This e-commerce site never shuts down. Users may easily traverse our site and obtain
        information about any industrial tools and power tools they are interested in. We’ve formed partnerships with
        premier logistics organizations to ensure that industrial, agricultural, construction and power tools and
        equipment supplies arrive at our clients’ doorsteps on time, even in the most remote parts of India. We are
        offering more than 5000+ products in over 15+ categories, all set up with the vision to provide the best buying
        experience in an online hardware store. With offers and discounts running all year round, Mazing Store has
        rapidly expanded its reach even in the rural areas of India, empowering businesses countrywide and creating
        innumerable opportunities by providing a curated buying experience.
      </div>
      <div class="col-12 col-md-6 text-justify small">
        A one-stop solution for retail as well as B2B customers, Mazing Store is bringing together small and
        medium-sized sellers across India. Powered by our product and technical expertise, and our heritage in the
        industrial tools, agricultural tools and power tools and equipment market, unmatched product catalogue, and
        digital expertise, it’s our unwavering commitment to create opportunities and bring value to customers and
        communities around the world.
      </div>
      <div class="col-12 col-md-6 text-justify small">
        We pride ourselves on providing the best online customer buying experience and seek to democratize the
        industrial, agricultural and power tools and equipment market by building a community of sellers and buyers
        across the globe and bringing a revolutionary change in the industrial supply operations on a national and
        global scale, providing economic opportunities to our sellers across India to create a sustainable power tools
        and industrial goods supply chain.
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="pt-3 pb-7 pb-xl-3 bg-primary text-light">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-lg-4">
        <div class="text-center text-md-left" current-verison="{{ get_setting('current_version') }}">
          {!! get_setting('frontend_copyright_text', null, App::getLocale()) !!}
        </div>
      </div>
      <div class="col-lg-4">
        @if (get_setting('show_social_links'))
          <ul class="list-inline my-3 my-md-0 social colored text-center">
            @if (get_setting('facebook_link') != null)
              <li class="list-inline-item">
                <a href="{{ get_setting('facebook_link') }}" target="_blank" class="facebook bg-white"><i
                    class="lab la-facebook-f bg-white"></i></a>
              </li>
            @endif
            @if (get_setting('twitter_link') != null)
              <li class="list-inline-item">
                <a href="{{ get_setting('twitter_link') }}" target="_blank" class="twitter bg-white"><i
                    class="lab la-twitter bg-white"></i></a>
              </li>
            @endif
            @if (get_setting('instagram_link') != null)
              <li class="list-inline-item">
                <a href="{{ get_setting('instagram_link') }}" target="_blank" class="instagram bg-white"><i
                    class="lab la-instagram bg-white"></i></a>
              </li>
            @endif
            @if (get_setting('youtube_link') != null)
              <li class="list-inline-item">
                <a href="{{ get_setting('youtube_link') }}" target="_blank" class="youtube bg-white"><i
                    class="lab la-youtube bg-white"></i></a>
              </li>
            @endif
            @if (get_setting('linkedin_link') != null)
              <li class="list-inline-item">
                <a href="{{ get_setting('linkedin_link') }}" target="_blank" class="linkedin bg-white"><i
                    class="lab la-linkedin-in bg-white"></i></a>
              </li>
            @endif
          </ul>
        @endif
      </div>
      <div class="col-lg-4">
        <div class="text-center text-md-right">
          <ul class="list-inline mb-0">
            @if (get_setting('payment_method_images') != null)
              @foreach (explode(',', get_setting('payment_method_images')) as $key => $value)
                @php
                  // Fetch file_name for the image (assuming $value contains the ID of the upload)
                  $image_file = \App\Models\Upload::where('id', $value)->value('file_name');
                  $image_path = $image_file
                              ? url('public/' . $image_file)
                              : url('public/assets/img/placeholder.jpg');
                @endphp
                <li class="list-inline-item">
                  <!-- <img src="{{ uploaded_asset($value) }}" height="30" class="mw-100 h-auto"
                    style="max-height: 30px"> -->
                    <img src="{{ $image_path }}" height="30" class="mw-100 h-auto" style="max-height: 30px">

                </li>
              @endforeach
            @endif
          </ul>
        </div>
      </div>
    </div>
  </div>
</footer>


<div class="aiz-mobile-bottom-nav d-xl-none fixed-bottom bg-white shadow-lg border-top rounded-top"
  style="box-shadow: 0px -1px 10px rgb(0 0 0 / 15%)!important; ">
  <div class="row align-items-center gutters-5">
    <div class="col">
      <a href="{{ route('home') }}" class="text-reset d-block text-center pb-2 pt-3">
        <i class="las la-home fs-20 opacity-60 {{ areActiveRoutes(['home'], 'opacity-100 text-primary') }}"></i>
        <span
          class="d-block fs-10 fw-600 opacity-60 {{ areActiveRoutes(['home'], 'opacity-100 fw-600') }}">{{ translate('Home') }}</span>
      </a>
    </div>
    <div class="col">
      <a href="{{ route('products.quickorder') }}" class="text-reset d-block text-center pb-2 pt-3">
        <i
          class="las la-cart-arrow-down fs-20 opacity-60 {{ areActiveRoutes(['products.quickorder'], 'opacity-100 text-primary') }}"></i>
        <span
          class="d-block fs-10 fw-600 opacity-60 {{ areActiveRoutes(['products.quickorder'], 'opacity-100 fw-600') }}">{{ translate('Quick Order') }}</span>
      </a>
    </div>
    @php
      if (auth()->user() != null) {
          $user_id = Auth::user()->id;
          $cart = \App\Models\Cart::where('user_id', $user_id)->get();
      } else {
          $temp_user_id = Session()->get('temp_user_id');
          if ($temp_user_id) {
              $cart = \App\Models\Cart::where('temp_user_id', $temp_user_id)->get();
          }
      }
    @endphp
    <div class="col-auto">
      <a href="{{ route('cart') }}" class="text-reset d-block text-center pb-2 pt-3">
        <span
          class="align-items-center bg-primary border border-white border-width-4 d-flex justify-content-center position-relative rounded-circle size-50px"
          style="margin-top: -33px;box-shadow: 0px -5px 10px rgb(0 0 0 / 15%);border-color: #fff !important;">
          <i class="las la-shopping-bag la-2x text-white"></i>
        </span>
        <span class="d-block mt-1 fs-10 fw-600 opacity-60 {{ areActiveRoutes(['cart'], 'opacity-100 fw-600') }}">
          {{ translate('Cart') }}
          @php
            $count = isset($cart) && count($cart) ? count($cart) : 0;
          @endphp
          (<span class="cart-count">{{ $count }}</span>)
        </span>
      </a>
    </div>
    <div class="col">
      <a href="{{ route('categories.all') }}" class="text-reset d-block text-center pb-2 pt-3">
        <i
          class="las la-list-ul fs-20 opacity-60 {{ areActiveRoutes(['categories.all'], 'opacity-100 text-primary') }}"></i>
        <span
          class="d-block fs-10 fw-600 opacity-60 {{ areActiveRoutes(['categories.all'], 'opacity-100 fw-600') }}">{{ translate('Categories') }}</span>
      </a>
    </div>
    <div class="col">
      @if (Auth::check())
        @if (isAdmin())
          <a href="{{ route('admin.dashboard') }}" class="text-reset d-block text-center pb-2 pt-3">
            <span class="d-block mx-auto">
              @if (Auth::user()->photo != null)
                <img src="{{ custom_asset(Auth::user()->avatar_original) }}" class="rounded-circle size-20px">
              @else
                <img src="{{ static_asset('assets/img/avatar-place.png') }}" class="rounded-circle size-20px">
              @endif
            </span>
            <span class="d-block fs-10 fw-600 opacity-60">{{ translate('Account') }}</span>
          </a>
        @else

          <a href="javascript:void(0)" class="text-reset d-block text-center pb-2 pt-3 mobile-side-nav-thumb "
            data-toggle="class-toggle" data-backdrop="static" data-target=".aiz-mobile-side-nav">
            <span class="d-block mx-auto">
              @if (Auth::user()->photo != null)
                <img src="{{ custom_asset(Auth::user()->avatar_original) }}" class="rounded-circle size-20px">
              @else
                <img src="{{ static_asset('assets/img/avatar-place.png') }}" class="rounded-circle size-20px">
              @endif
            </span>
            <span class="d-block fs-10 fw-600 opacity-60">{{ translate('Account') }}</span>
          </a>

        @endif
      @else
        <a href="{{ route('user.login') }}" class="text-reset d-block text-center pb-2 pt-3">
          <span class="d-block mx-auto">
            <img src="{{ static_asset('assets/img/avatar-place.png') }}" class="rounded-circle size-20px">
          </span>
          <span class="d-block fs-10 fw-600 opacity-60">{{ translate('Account') }}</span>
        </a>
      @endif
    </div>
  </div>
</div>

<!-- Offer Section start -->
  <div class="modal fade bd-example-modal-lg" id="offerModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">Offer List</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <ul id="offer-details">
              <!-- Offer details will be populated here -->
          </ul>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <script>
    $(document).on('click', '.view-offer', function () {
        let productId = $(this).data('product-id');
        // alert(productId)
        $.ajax({
            url: '{{route("getOffers")}}', // Your route to fetch offer details
            method: 'GET',
            data: { product_id: productId },
            success: function (response) {
              if (response.html) {
                $('#offer-details').html(response.html); // Load the rendered HTML
                $('#offerModal').modal('show'); // Show the modal
              } else {
                  alert('No offers available for this product.');
              }
            },
            error: function () {
                alert('Failed to fetch offer details. Please try again.');
            }
        });
    });
  </script>
<!-- Offer Section End -->

@if (Auth::check() && !isAdmin())
  <div class="aiz-mobile-side-nav collapse-sidebar-wrap sidebar-xl d-xl-none z-1035">
    <div class="overlay dark c-pointer overlay-fixed" data-toggle="class-toggle" data-backdrop="static"
      data-target=".aiz-mobile-side-nav" data-same=".mobile-side-nav-thumb"></div>
    <div class="collapse-sidebar bg-white">
      @include('frontend.inc.user_side_nav')
    </div>
  </div>

  <script>
      $('.mobile-side-nav-thumb').click(function(){
          $('.aiz-mobile-side-nav').addClass('active');
      });

      $('.cl').click(function(){
          $('.aiz-mobile-side-nav').removeClass('active');
      });

  </script>
@endif

<input type="hidden" name="pdfDownloadStatus" id="pdfDownloadStatus" value="0">
<script>
    function checkPdfAvailability(filename) {
        fetch(`/pdf-status/${filename}`)
            .then(response => response.json())
            .then(data => {
                if (data.ready) {
                    downloadFile(`public/pdfs/${filename}`,`${filename}`);
                    $('.ajax-loader').css("visibility", "hidden");
                } else {
                    setTimeout(() => checkPdfAvailability(filename), 2000);
                }
            })
            .catch(error => console.error('Error:', error));
    }
    function downloadFile(url,filename) {
        $.ajax({
            url: '{{ route("updateDownloadPdfStatus") }}',
            type: 'GET',
            // beforeSend: function(){
            //   $('.ajax-loader').css("visibility", "visible");
            // },
            data: { filename: filename },
            dataType: 'json',
            success: function (response) {
                console.log(response); // Log the response for debugging
                document.getElementById('pdfDownloadStatus').value = 1;
            },
            // complete: function(){
            //   $('.ajax-loader').css("visibility", "hidden");
            // },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
        const link = document.createElement('a');
        link.href = url;
        link.download = url.substring(url.lastIndexOf('/') + 1);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Add Offer Product
    function addOfferProductToCart(productId) {
        console.log("Adding single product to cart with ID:", productId);
        const form = document.getElementById('addOfferProductToCartFrm');
        if (form) {
            const input = form.querySelector('#product_id');
            if (input) {
                input.value = productId;
                form.submit();
            } else {
                console.error("Hidden input field #product_id not found.");
            }
        } else {
            console.error("Form with ID #addOfferProductToCart not found.");
        }
    }

    function addAllOfferProductToCart(productIds,offerId,addAllItem) {
        console.log("Adding all products to cart with IDs:", productIds);
        const form = document.getElementById('addOfferProductToCartFrm_'+offerId);

        if (form) {
            const input = form.querySelector('#product_id');
            const addAllItemInput = form.querySelector('#addAllItem');
            if (input) {
                input.value = productIds;
                addAllItemInput.value = addAllItem;
                form.submit();
            } else {
                console.error("Hidden input field #product_id not found.");
            }
        } else {
            console.error("Form with ID #addOfferProductToCart not found.");
        }
    }



    function addSelectedItemsToCart(offerId) {
        const selectedProducts = Array.from(document.querySelectorAll('.select-product:checked'))
            .map(checkbox => checkbox.value);

        if (selectedProducts.length === 0) {
            alert("No products selected!");
            return;
        }

        console.log("Adding selected products to cart with IDs:", selectedProducts.join(','));
        const form = document.getElementById('addOfferProductToCartFrm_'+offerId);
        if (form) {
            const input = form.querySelector('#product_id');
            if (input) {
                input.value = selectedProducts.join(',');
                form.submit();
            }
        }
    }

</script>

@php
    if (session()->has('pdfFileName')) {
      $pdfFileName = session('pdfFileName');
      $pdfReportData = \App\Models\PdfReport::where('filename',$pdfFileName)->first();
      $downloadStatus = $pdfReportData->download_status;
    }else{
      $pdfFileName = "";
      $downloadStatus = 1;
    }
@endphp
@if($downloadStatus == "0")
  <script>
      // function checkPdfStatus(fileName) {
      //     fetch(`/pdf-status/${fileName}`)
      //         .then(response => response.json())
      //         .then(data => {
      //             if (data.ready) {
      //                 var pdfDownloadStatus = document.getElementById('pdfDownloadStatus').value;
      //                 if(pdfDownloadStatus == '0'){
      //                     document.getElementById('pdfDownloadStatus').value = '1';
      //                     window.location.href = `/download-pdf/${fileName}`;                        
      //                 }                      
      //             } else {
      //                 setTimeout(() => checkPdfStatus(fileName), 3000); // Check again in 3 seconds
      //             }
      //         });
      // }

      // Start polling as soon as the page loads
      document.addEventListener('DOMContentLoaded', function() {
          const fileName = '{{ $pdfFileName }}';
          var pdfDownloadStatus = document.getElementById('pdfDownloadStatus').value;
          if(pdfDownloadStatus == '0'){
            checkPdfAvailability(fileName);
          }
      });
  </script>
@endif