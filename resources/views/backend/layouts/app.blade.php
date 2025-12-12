<!doctype html>
@if (\App\Models\Language::where('code', Session::get('locale', Config::get('app.locale')))->first()->rtl == 1)
  <html dir="rtl" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@else
  <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@endif

<head>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="app-url" content="{{ getBaseURL() }}">
  <!-- <meta name="file-base-url" content="{{ getFileBaseURL() }}"> -->
  <meta name="file-base-url" content="{{ env('UPLOADS_BASE_URL', url('public')).'/' }}">

  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <!-- Favicon -->
  <link rel="icon" href="{{ uploaded_asset(get_setting('site_icon')) }}">
  <title>{{ get_setting('website_name') . ' | ' . get_setting('site_motto') }}</title>

  <!-- google font -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700">

  <!-- aiz core css -->
  <link rel="stylesheet" href="{{ static_asset('assets/css/vendors.css') }}">
  @if (\App\Models\Language::where('code', Session::get('locale', Config::get('app.locale')))->first()->rtl == 1)
    <link rel="stylesheet" href="{{ static_asset('assets/css/bootstrap-rtl.min.css') }}">
  @endif
  <link rel="stylesheet" href="{{ static_asset('assets/css/aiz-core.css') }}">


 <!-- Select 2 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.4/css/select2.min.css" rel="stylesheet" />
   <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.8/js/select2.min.js" defer></script>

   
  <style>
    body {
      font-size: 12px;
    }
  </style>

  <script>
    var AIZ = AIZ || {};
    AIZ.local = {
      nothing_selected: '{!! translate('Nothing selected', null, true) !!}',
      nothing_found: '{!! translate('Nothing found', null, true) !!}',
      choose_file: '{{ translate('Choose file') }}',
      file_selected: '{{ translate('File selected') }}',
      files_selected: '{{ translate('Files selected') }}',
      add_more_files: '{{ translate('Add more files') }}',
      adding_more_files: '{{ translate('Adding more files') }}',
      drop_files_here_paste_or: '{{ translate('Drop files here, paste or') }}',
      browse: '{{ translate('Browse') }}',
      upload_complete: '{{ translate('Upload complete') }}',
      upload_paused: '{{ translate('Upload paused') }}',
      resume_upload: '{{ translate('Resume upload') }}',
      pause_upload: '{{ translate('Pause upload') }}',
      retry_upload: '{{ translate('Retry upload') }}',
      cancel_upload: '{{ translate('Cancel upload') }}',
      uploading: '{{ translate('Uploading') }}',
      processing: '{{ translate('Processing') }}',
      complete: '{{ translate('Complete') }}',
      file: '{{ translate('File') }}',
      files: '{{ translate('Files') }}',
    }
  </script>

</head>

<body class="">
    <style>
        .ajax-loader {
        visibility: hidden;
        background-color: rgba(255,255,255,0.7);
        position: absolute;
        z-index: +100 !important;
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
  <div class="aiz-main-wrapper">
    @include('backend.inc.admin_sidenav')
    <div class="aiz-content-wrapper">
      @include('backend.inc.admin_nav')
      <div class="aiz-main-content">
        <div class="px-15px px-lg-25px">
      
        @if (session('pdf_download_url'))
    <script type="text/javascript">
        window.onload = function() {
            window.open("{{ session('pdf_download_url') }}", '_blank');
        };
    </script>
@endif


          @yield('content')
        </div>
        <div class="bg-white text-center py-3 px-15px px-lg-25px mt-auto">
          <p class="mb-0">&copy; {{ get_setting('site_name') }}</p>
        </div>
      </div><!-- .aiz-main-content -->
    </div><!-- .aiz-content-wrapper -->
  </div><!-- .aiz-main-wrapper -->

  @yield('modal')


  <script src="{{ static_asset('assets/js/vendors.js') }}"></script>
  @if (Route::currentRouteName() != 'uploaded-files.own_brand_file_create')
    <script src="{{ static_asset('assets/js/aiz-core.js') }}"></script>
  @else
    <script src="{{ static_asset('assets/js/aiz-core-own-brand.js') }}"></script>
  @endif
  @yield('script')

  <script type="text/javascript">
    @foreach (session('flash_notification', collect())->toArray() as $message)
      AIZ.plugins.notify('{{ $message['level'] }}', '{{ $message['message'] }}');
    @endforeach


    if ($('#lang-change').length > 0) {
      $('#lang-change .dropdown-menu a').each(function() {
        $(this).on('click', function(e) {
          e.preventDefault();
          var $this = $(this);
          var locale = $this.data('flag');
          $.post('{{ route('language.change') }}', {
            _token: '{{ csrf_token() }}',
            locale: locale
          }, function(data) {
            location.reload();
          });

        });
      });
    }

    

    function menuSearch() {
      var filter, item;
      filter = $("#menu-search").val().toUpperCase();
      items = $("#main-menu").find("a");
      items = items.filter(function(i, item) {
        if ($(item).find(".aiz-side-nav-text")[0].innerText.toUpperCase().indexOf(filter) > -1 && $(item).attr(
            'href') !== '#') {
          return item;
        }
      });

      if (filter !== '') {
        $("#main-menu").addClass('d-none');
        $("#search-menu").html('')
        if (items.length > 0) {
          for (i = 0; i < items.length; i++) {
            const text = $(items[i]).find(".aiz-side-nav-text")[0].innerText;
            const link = $(items[i]).attr('href');
            $("#search-menu").append(
              `<li class="aiz-side-nav-item"><a href="${link}" class="aiz-side-nav-link"><i class="las la-ellipsis-h aiz-side-nav-icon"></i><span>${text}</span></a></li`
            );
          }
        } else {
          $("#search-menu").html(
            `<li class="aiz-side-nav-item"><span	class="text-center text-muted d-block">{{ translate('Nothing Found') }}</span></li>`
          );
        }
      } else {
        $("#main-menu").removeClass('d-none');
        $("#search-menu").html('')
      }
    }



  </script>

<script>
  

  $(document).ready(function() {
    // Check/Uncheck all checkboxes
    $('#checkAll').click(function() {
        $('input[name="selectedOrders[]"]').prop('checked', this.checked);
    });

    // Handle Make Purchase Order button click
    $('#makePurchaseOrder').click(function() {
      
        $('#purchase_order_form').submit(); // Submit the form when button is clicked
    });
  });

  
</script>

<!-- <script>
    function recalculateSubtotal(index) {
        const qty = parseFloat(document.querySelector(`input.qty-input[data-index="${index}"]`).value);
        const price = parseFloat(document.querySelector(`input.price-input[data-index="${index}"]`).value);
        const subtotal = qty * price;
        document.querySelector(`.subtotal[data-index="${index}"]`).textContent = subtotal.toFixed(2);
        recalculateGrandTotal();
    }

    function recalculateGrandTotal() {
        let grandTotal = 0;
        document.querySelectorAll('.subtotal').forEach(function(subtotalElement) {
            grandTotal += parseFloat(subtotalElement.textContent);
        });
        document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
    }
</script> -->

<script>
    function recalculateSubtotal(index) {
        const qty = parseFloat(document.querySelector(`input.qty-input[data-index="${index}"]`).value) || 0;
        const price = parseFloat(document.querySelector(`input.price-input[data-index="${index}"]`).value) || 0;
        const subtotal = qty * price;
        document.querySelector(`.subtotal[data-index="${index}"]`).textContent = subtotal.toFixed(2);
        recalculateGrandTotal();
    }

    function recalculateGrandTotal() {
        let grandTotal = 0;
        document.querySelectorAll('.subtotal').forEach(function(subtotalElement) {
            grandTotal += parseFloat(subtotalElement.textContent) || 0;
        });
        document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
    }

    // Initial calculation for the page load
    recalculateGrandTotal();
</script>

 <script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>
</body>

</html>
