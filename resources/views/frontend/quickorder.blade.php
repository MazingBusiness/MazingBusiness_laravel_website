
@extends('frontend.layouts.app')

@section('content')
  <style>
    .ajax-loader {
        visibility: hidden;
        background-color: rgba(255, 255, 255, 0.7);
        position: fixed;
        z-index: 100;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        text-align: center;
    }

    .ajax-loader img {
        width: 150px; /* Adjust size as needed */
        margin-bottom: 20px;
    }

    .ajax-loader p {
      color: #074e86;
      font-family: Arial, sans-serif;
      font-size: 21px;
      font-weight: bold;
    }
    
  </style>
  <div id="pdfAjaxLoader" class="ajax-loader">
    <!-- <img src="{{ url('https://mazingbusiness.com/public/assets/img/ajax-loader.gif') }}" class="img-responsive" /> -->
    <p>PDF is being generated. Please wait for some time or you can continoue your browsing (Don't close your browser)....</p>
  </div>
  <div id="searchAjaxLoader" class="ajax-loader">
    <img src="{{ url('https://mazingbusiness.com/public/assets/img/ajax-loader.gif') }}" class="img-responsive" />
    <p>Search is processing. Please wait for some time ....</p>
  </div>
  <section class="mb-4">
    <div class="container-fluid sm-px-0">
      <form class="" id="search-form" action="" method="GET">
        <div class="row">
        <div class="col-12 mb-4 mb-lg-0">
          <div class="row gutters-10 position-relative mb-4 d-flex align-items-center">
              <div class="col-xl-2 col-md-3">
                  <input type="text" value="{{$srch_prod_name}}" id="prod_name" name="prod_name" class="form-control" placeholder="Product Name or Part No">
              </div>
              <div class="col-xl-3 col-md-3">
                <input type="hidden" name="order_id" id="order_id" value="{{ $order_id }}">
                <input type="hidden" name="sub_order_id" id="sub_order_id" value="{{ $sub_order_id }}">
                <select class="js-select-cat-group abc cats_grp_drop form-control" multiple="multiple" id="cat_group_drop" name="cat_groups[]">
                @php
                    // priority 1 & 8 first, then alpha
                    $category_groups = $category_groups->sortBy(fn($g)=>$g->id==1?0:($g->id==8?1:2))->values();
                    $category_groups = $category_groups->sortBy(fn($g)=>($g->id==1||$g->id==8)?'':$g->name)->values();
                    $is41 = !empty($is41Manager);
                @endphp

                @foreach ($category_groups as $group)
                    @php
                        $q = \App\Models\Product::where('group_id', $group->id);

                        if ($is41) {
                            // Manager-41 flow → only is_manager_41
                            $q->where('is_manager_41', 1);
                        } else {
                            // Normal flow → only current_stock
                            $q->where('current_stock', 1);
                        }

                        $count = $q->count();
                    @endphp

                    @if ($count > 0)
                        <option value="{{ $group->id }}">{{ strtoupper($group->name) }} ({{ $count }})</option>
                    @endif
                @endforeach
            </select>



              </div>
              <div class="col-xl-3 col-md-3">
                  <select class="js-select-category category_drop form-control" multiple="multiple" id="cat_drop">
                      <option value="">{{ translate('All Categories') }}</option>
                      @php
                          $categories = $categories->sortBy('name');
                      @endphp
                      @foreach ($categories as $category)
                          @if ($count > 0)
                              <option value="{{ $category->id }}">{{ strtoupper($category->name) }}</option>
                          @endif
                      @endforeach
                  </select>
              </div>
              <div class="col-xl-3 col-md-3">
                  <select class="js-select-all-brand b_drop form-control" multiple="multiple" id="brand_drop">
                      <option value="">{{ translate('All Brands') }}</option>
                      @foreach ($brands as $brand)
                          <option value="{{ $brand->id }}">{{ $brand->getTranslation('name') }}</option>
                      @endforeach
                  </select>
              </div>

              <!-- Inhouse Dropdown -->
            <div style="" class="col-xl-2 col-md-3 mb-3 d-flex align-items-center">
                <select id="inhouseDropdown" name="inhouse" class="form-control select2" style="width: 100%; height: 30px; font-size: 14px; padding: 2px;">
                   <option  disabled selected>Select Delivery Time</option>
                    <option value="1">Delivery in  3 to 4 days</option>
                    <option value="2">Delivery in 6 to 7 days</option>
                </select>
            </div>

              <div style="position: relative;left: 50px;" class="col-xl-1 col-md-3">
                  <input type="button" id="btnSearch" value="Search" class="d-block rounded btn-primary btn-block text-light p-2 shadow-sm">
              </div>
          </div>
      </div>
      <style>
          .custom-margin-top {
              margin-top: 10px; /* Adds margin-top to select boxes */
          }

          .select2-container {
              margin-top: 10px; /* Apply margin-top to Select2 components */
          }
          .select2-container--default .select2-selection--multiple,
          .select2-container--default .select2-selection--single {
              min-height: 40px !important; /* Ensure Select2 components match the custom height */
          }
      </style>
      <script type="text/javascript">
          $(document).ready(function() {
            $(".js-select-cat-group").select2({
                closeOnSelect : false,
                placeholder : "Category Group",
                allowHtml: true,
                allowClear: true,
                tags: true
            });
            $(".js-select-category").select2({
                closeOnSelect : false,
                placeholder : "All Categories",
                allowHtml: true,
                allowClear: true,
                tags: true
            });
            $(".js-select-all-brand").select2({
                closeOnSelect : false,
                placeholder : "All Brands",
                allowHtml: true,
                allowClear: true,
                tags: true
            });
          });
      </script>
          <!-- <div class="col-12 mb-4 mb-lg-0">
            <div class="row gutters-10 position-relative mb-4">
              <div class="col-xl-2 d-flex align-items-center">
               <input type="text" value="{{$srch_prod_name}}" name="prod_name" class="form-control" placeholder="Product Name or Part No" >
              </div>
              <div class="col-xl-2">
                  <select class="form-control form-control-sm aiz-selectpicker" title="Category Group" id="cat_group_drop" data-live-search="true" multiple="multiple" name="cat_groups[]" data-selected='["{{ implode('","', $selected_cat_groups) }}"]'>
                      @php
                          $category_groups = $category_groups->sortBy('name');
                      @endphp
                      @foreach ($category_groups as $group)
                        @php
                          $count = \App\Models\Product::where('group_id', $group->id)->where('current_stock', 1)->count();
                        @endphp
                        @if ($count > 0)
                          <option value="{{ $group->id}}">{{ strtoupper($group->name)}} (  {{ $count }} )</option>
                        @endif
                      @endforeach
                  </select>
              </div>
              <div class="col-xl-2">
                <select class="form-control form-control-sm aiz-selectpicker" id="cat_drop"  title="Categories" data-live-search="true"
                multiple="multiple" name="categories[]"
                  data-selected='["{{ implode('","', $selected_categories) }}"]'>
                  <option value="">{{ translate('All Categories') }}</option>
                    @php
                        $categories = $categories->sortBy('name');
                    @endphp
                  @foreach ($categories as $category)

                  @if ($count > 0)
                    <option value="{{ $category->id }}">{{ strtoupper($category->name) }}</option>
                  @endif
                  @endforeach
                </select>
              </div>
              <div class="col-xl-2">
                <select class="form-control form-control-sm aiz-selectpicker" data-live-search="true" name="brands[]"
                multiple="multiple" data-selected='["{{ implode('","', $selected_brands) }}"]'>
                  <option value="">{{ translate('All Brands') }}</option>
                  @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}">
                      {{ $brand->getTranslation('name') }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-xl-2 align-items-center">
               <input type="submit" class="d-block rounded btn-primary btn-block text-light p-2 shadow-sm">
              </div>
            </div>
          </div> -->
          <div class="col-12" style="margin-bottom:10px;">
            <div class="text-left">
              <div class="row gutters-5 flex-wrap align-items-center">
                <div class="col-md-8 col-8 col-lg-auto flex-fill">
                  <h1 class="h4 mb-lg-0 fw-600 text-body">
                    @if ($category_group)
                      {{ translate('All ' . Str::ucfirst($category_group->name)) }}
                    @else
                      {{ translate('All Products') }}
                    @endif
                  </h1>
                </div>
                @php
                    $user = Auth::user();
                @endphp

                @if ($user)
                  @php
                      $userId = Auth::user()->id;
                  @endphp
                  <input type="hidden" id="userId" value="{{ $userId }}">
                  @if(empty($order_id) AND empty($sub_order_id))
                    <div class="col-2 align-items-center">
                      <!-- <a target="_blank" href="{{ url('https://mazingbusiness.com/api/products_pdf.php?' . $queyparam . '&user_id=' . $userId) }}"
                        class="d-block rounded btn-primary btn-block text-light p-2 shadow-sm">
                        <div class="text-truncate fs-12 fw-700 text-center">
                          DOWNLOAD PDF</div>
                      </a> -->
                      <a target="_blank" id="downloadPDFLink" class="d-block rounded btn-primary btn-block text-light p-2 shadow-sm" style="cursor: pointer;">
                        <div class="text-truncate fs-12 fw-700 text-center">
                          Download Net Price (PDF)</div>
                      </a>
                    </div>
                    <div class="col-2 align-items-center">
                      <!-- <a target="_blank" href="{{ url('https://mazingbusiness.com/api/products_excel.php?' . $queyparam . '&user_id=' . $userId) }}" class="d-block rounded btn-success btn-block text-light p-2 shadow-sm">
                        <div class="text-truncate fs-12 fw-700 text-center">
                          DOWNLOAD EXCEL</div>
                      </a> -->
                      <a target="_blank" id="downloadExcelLink" class="d-block rounded btn-success btn-block text-light p-2 shadow-sm" style="cursor: pointer;">
                        <div class="text-truncate fs-12 fw-700 text-center">
                        Download Net Price (EXCEL)</div>
                      </a>
                    </div>
                  @endif
                @endif
              </div>
            </div>
          </div>
          <div class="col-12">
            <div class="row gutters-5 row-cols-1">
              <div id="postsContainer">
                <div class="col" id="post-data">
                 @if(!($is41Manager ?? false))
                    @include('frontend.partials.quickorder_list_box')
                @else
                    @include('frontend.partials.manager41_quickorder_list_box')
                @endif
                </div>
                <div class="text-center p-3 d-none" id="loading">Loading...</div>
              </div>
              <input type="hidden" id="searchActive" value="0">
            </div>
          </div>
        </div>
      </form>
    </div>
  </section>
@endsection


<div class="modal fade" id="downloadPDFModal" tabindex="-1" role="dialog" aria-labelledby="downloadPDFModal" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">PDF Download Status</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        Your price list download might take few minutes. you can continue browsing. Your file will be downloaded in the download folder.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="downloadPDFErrorModal" tabindex="-1" role="dialog" aria-labelledby="downloadPDFErrorModal" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Message for you</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        Please fill any one of the fields before proceeding with the price list download.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@section('script')
  <script type="text/javascript">
    $(document).ready(function() {

      // Initialize Select2
     $('#inhouseDropdown').select2({
        placeholder: "Select Delivery Time",
        allowClear: true,
        minimumResultsForSearch: Infinity,
        dropdownParent: $('#inhouseDropdown').parent() // Ensures proper positioning
    });

      // Adjust Select2 dropdown and container size
    $('#inhouseDropdown').next('.select2-container').css({
        'height': '30px',
        'font-size': '14px'
    });

    $('.select2-selection--single').css({
        'height': '30px',
        'line-height': '30px',
        'font-size': '14px',
        'width':'225px'
    });
      function getUrlParameter(name) {
          name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
          var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
          var results = regex.exec(location.search);
          return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
      }

      function getUrlParameterArray(name) {
          var results = [];
          var regex = new RegExp('[\\?&]' + name + '%5B%5D=([^&#]*)', 'g');  // Updated regex to handle [] in parameter names
          var result;
          while ((result = regex.exec(location.search)) !== null) {
              results.push(decodeURIComponent(result[1].replace(/\+/g, ' ')));
          }
          return results;
      }

      //JS Pagination
      var page = 1;
      $(window).scroll(bindScroll);

      function bindScroll() {
        if (($(window).scrollTop() + $(window).height()) >= ($(document).height() - $('#footerstart').height() - 300)) {
          $(window).unbind('scroll');
          page++;
          var searchActive = $('#searchActive').val();

          if(searchActive == 0){
            loadMoreData(page);
          }
        }
      }

      function loadMoreData(page) {
        $("#loading").removeClass('d-none');
        $.ajax({
          data: {
            'page': page
          },
          type: "get",
        })
        .done(function(data) {
          $("#loading").addClass('d-none');
          if (data.html) {
            $("#post-data").append(data.html);
            $(window).bind('scroll', bindScroll);
          }
        });
      }

      function filter() {
        $('#search-form').submit();
      }

      $('#cat_drop').on('blur', function(){
          $('#search-form').submit();
      });

      $('#cat_group_drop').change(function () {
        var category_group_id = $(this).val();        
        // Check if "Select All" is selected
        // if (category_group_id.includes('select_all')) {
        //     // Select or deselect all other options
        //     var isSelectAll = $('#option_select_all').is(':selected');
        //     alert(isSelectAll);
        //     $('#cat_group_drop option').prop('selected', isSelectAll);           
        //     if (isSelectAll) {
        //         category_group_id = Array.from($('#cat_group_drop option'))
        //             .map(option => option.value)
        //             .filter(value => value !== 'select_all');
        //     } else if(isSelectAll == false) {
        //       category_group_id = []; 
        //     }
        // }

        if (category_group_id.length !== 0) {
            // AJAX call to fetch child options
            $.ajax({
                url: '{{ route("getcategories") }}',
                type: 'GET',
                data: { category_group_id: category_group_id },
                dataType: 'json',
                success: function (response) {
                    $('#cat_drop').empty();
                    // Enable child selectpicker
                    $('#cat_drop').prop('disabled', false);
                    // Get selected categories from URL parameters
                    var selectedCategories = getUrlParameterArray('categories');
                    // Append new options and mark selected ones
                    $.each(response, function (key, value) {
                        var option = $('<option></option>')
                            .attr('value', value.id)
                            .text(value.name);
                        if (selectedCategories.includes(value.id.toString())) {
                            option.prop('selected', true);
                        }
                        $('#cat_drop').append(option);
                    });
                },
                error: function (xhr, status, error) {
                    console.error(xhr.responseText);
                }
            });
            $('#brand_drop').empty().prop('disabled', true);
        } else {
            // If no parent selected, disable and reset child selectpicker
            $('#cat_drop').empty().prop('disabled', true);
            $.ajax({
                url: '{{ route("getcategories") }}',
                type: 'GET',
                data: { category_group_id: 0 },
                dataType: 'json',
                success: function (response) {
                    $('#cat_drop').empty();
                    // Enable child selectpicker
                    $('#cat_drop').prop('disabled', true);
                },
                error: function (xhr, status, error) {
                    console.error(xhr.responseText);
                }
            });
            $.ajax({
                url: '{{ route("getbrands") }}',
                type: 'GET',
                data: { category_group_id: 0, category_id: 0 },
                dataType: 'json',
                success: function (response) {
                    $('#brand_drop').empty();
                    // Enable child selectpicker
                    $('#brand_drop').prop('disabled', false);
                    // Append new options and mark selected ones
                    $.each(response, function (key, value) {
                        var option = $('<option></option>')
                            .attr('value', value.id)
                            .text(value.name);
                        $('#brand_drop').append(option);
                    });
                },
                error: function (xhr, status, error) {
                    console.error(xhr.responseText);
                }
            });
            // location.reload();
        }
      });

      $('#cat_drop').change(function () {
          var category_id = $(this).val();
          var category_group_id = $("#cat_group_drop").val();
          if (category_group_id != "" && category_id != "") {
              // AJAX call to fetch child options
              $.ajax({
                  url: '{{ route("getbrands") }}',
                  type: 'GET',
                  data: { category_group_id: category_group_id, category_id: category_id },
                  dataType: 'json',
                  success: function (response) {
                      $('#brand_drop').empty();
                      // Enable child selectpicker
                      $('#brand_drop').prop('disabled', false);
                      // Get selected categories from URL parameters
                      var selectedCategories = getUrlParameterArray('categories');

                      // Append new options and mark selected ones
                      $.each(response, function (key, value) {
                          var option = $('<option></option>')
                              .attr('value', value.id)
                              .text(value.name);
                          if (selectedCategories.includes(value.id.toString())) {
                              option.prop('selected', true);
                          }
                          $('#brand_drop').append(option);
                      });
                  },
                  error: function (xhr, status, error) {
                      console.error(xhr.responseText);
                  }
              });
          }else{
            $.ajax({
                  url: '{{ route("getbrands") }}',
                  type: 'GET',
                  data: { category_group_id: 0, category_id: 0 },
                  dataType: 'json',
                  success: function (response) {
                      $('#brand_drop').empty();
                      // Enable child selectpicker
                      $('#brand_drop').prop('disabled', true);
                  },
                  error: function (xhr, status, error) {
                      console.error(xhr.responseText);
                  }
              });
          }
      });

      $('#btnSearch').click(function(){
        var prod_name = $('#prod_name').val();
        var cat_groups = $('#cat_group_drop').val();
        var categories = $('#cat_drop').val();
        var brands = $('#brand_drop').val();
        var inhouse = $('#inhouseDropdown').val(); // Get the inhouse checkbox value
        var order_id = $('#order_id').val();

        if(prod_name == "" && cat_groups =="" && categories=="" && brands==""){
            // alert("Please select alteast one or type any product name.");
            location.reload();
        }else{
          $('#searchActive').val('1');

          $.ajax({
              url: '{{ route("quickOrderSearchList") }}',
              type: 'GET',
              beforeSend: function(){
                $('#searchAjaxLoader').css("visibility", "visible");
              },
              data: { prod_name: prod_name, cat_groups: cat_groups, categories: categories, brands: brands , inhouse: inhouse, order_id: order_id},
              dataType: 'json',
              success: function (response) {
                  console.log(response); // Log the response for debugging
                  $('#postsContainer').empty(); // Clear the div before appending new data
                  $('#postsContainer').append(response.html); // Append the response data
              },
              complete: function(){
                $('#searchAjaxLoader').css("visibility", "hidden");
              },
              error: function (xhr, status, error) {
                  console.error(xhr.responseText);
              }
          });
        }
      });


      //created by dipak start

     // Trigger search on Inhouse checkbox toggle
        $('#inhouseDropdown').change(function () {
             var inhouse = $(this).val(); // Get the selected value
            var prod_name = $('#prod_name').val();
            var cat_groups = $('#cat_group_drop').val();
            var categories = $('#cat_drop').val();
            var brands = $('#brand_drop').val();



            // Check if the value is null or empty
         
        if (!inhouse) {
            console.log('No selection, no search triggered');
            return; // Do nothing if no valid selection
        }

        // if(prod_name == "" && cat_groups =="" && categories=="" && brands==""){
        //     // alert("Please select alteast one or type any product name.");
        //     location.reload();
        // }

            // Call the search function
            $.ajax({
                url: '{{ route("quickOrderSearchList") }}',
                type: 'GET',
                beforeSend: function () {
                    $('#searchAjaxLoader').css("visibility", "visible");
                },
                data: {
                    prod_name: prod_name,
                    cat_groups: cat_groups,
                    categories: categories,
                    brands: brands,
                    inhouse: inhouse
                },
                dataType: 'json',
                success: function (response) {
                    console.log(response); // Debug response
                    $('#postsContainer').empty(); // Clear existing data
                    $('#postsContainer').append(response.html); // Append new data
                },
                complete: function () {
                    $('#searchAjaxLoader').css("visibility", "hidden");
                },
                error: function (xhr, status, error) {
                    console.error(xhr.responseText);
                }
            });
        });
      //created by diapk end

      $('#prod_name').keypress(function(e) {
          if (e.which == 13) { // Enter key corresponds to key code 13
              e.preventDefault(); // Prevent the default form submission behavior
              $('#btnSearch').click(); // Trigger the click event on the search button
          }
      });
      
      $('.b_drop').change(function(){

        var brands = $('.b_drop').val();
        let cat_groups = $('.cats_grp_drop').val();
        var category_drop = $('.category_drop').val();

          // if(brands.length == 0){
          //   location.reload();
          // }

      });


      $('#downloadExcelLink').click(function(){
        var prod_name = $('#prod_name').val();
        var cat_groups = $('#cat_group_drop').val();
        var categories = $('#cat_drop').val();
        var brands = $('#brand_drop').val();
        var userId = $('#userId').val();

        // Create a form
        var form = $('<form>', {
              action: 'https://mazingbusiness.com/api/products_excel.php',
              method: 'GET',
              target: '_blank'
          });

          // Append the parameters as hidden fields
          form.append($('<input>', { type: 'hidden', name: 'prod_name', value: prod_name }));
          form.append($('<input>', { type: 'hidden', name: 'cat_groups', value: cat_groups }));
          form.append($('<input>', { type: 'hidden', name: 'categories', value: categories }));
          form.append($('<input>', { type: 'hidden', name: 'brands', value: brands }));
          form.append($('<input>', { type: 'hidden', name: 'user_id', value: userId }));
          form.append($('<input>', { type: 'hidden', name: 'type', value: 'net' }));

          // Append the form to the body
          $('body').append(form);

          // Submit the form
          form.submit();
      })

      document.getElementById('downloadPDFLink').addEventListener('click', function(event) {
          event.preventDefault();
          // $('#pdfAjaxLoader').css("visibility", "visible");
          
          // Collect additional data
          var prod_name = $('#prod_name').val();
          var cat_groups = $('#cat_group_drop').val();
          var categories = $('#cat_drop').val();
          var brands = $('#brand_drop').val();
          var userId =  $('#userId').val();

          var inhouseDropdown =  $('#inhouseDropdown').val();
          // alert(cat_groups);
          // Check if any of the required values are empty
          if (prod_name == "" && cat_groups == "" && categories == "" && brands == "" && inhouseDropdown === "") {
              $('#downloadPDFErrorModal').modal('show');
              return;
          }
          // Show the modal when PDF generation starts
          $('#downloadPDFModal').modal('show');
          $.ajax({
              url: '{{ route("generatePdfFileName") }}',
              type: 'POST',
              data: { 
                  user_id: userId, 
                  _token: '{{ csrf_token() }}' // Include CSRF token
              },
              dataType: 'json',
              success: function (response) {
                
                if(response.status == true){
                  var formData = new FormData();
                  formData.append('prod_name', prod_name);
                  formData.append('cat_groups', cat_groups);
                  formData.append('categories', categories);
                  formData.append('brands', brands);
                  formData.append('userId', userId);
                  formData.append('type', 'net');
                  formData.append('inhouse', inhouseDropdown);

                  // Add CSRF token
                  formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                  // alert(formData.get('_token'));
                  // Fetch options
                  var options = {
                      method: 'POST',
                      body: formData,
                      headers: {
                          'X-CSRF-TOKEN': formData.get('_token')
                      }
                  };
                  // Send request to the server
                  fetch('/generate-pdf', options)
                    .then(response => response.json())
                    .then(data => {
                        if (data.filename) {
                            checkPdfAvailability(data.filename);
                        }
                    })
                  .catch(error => console.error('Error:', error));
                }else{
                  console.log(response); // Log the response for debugging
                }                
              },
              error: function (xhr, status, error) {
                  console.error(xhr.responseText);
              }
          });
      });

      //---------------- Below fnction is now on footer.blade.php ----------------------
      // function checkPdfAvailability(filename) {
      //     fetch(`/pdf-status/${filename}`)
      //         .then(response => response.json())
      //         .then(data => {
      //             if (data.ready) {
      //                 downloadFile(`public/pdfs/${filename}`);
      //                 $('.ajax-loader').css("visibility", "hidden");
      //             } else {
      //                 setTimeout(() => checkPdfAvailability(filename), 2000);
      //             }
      //         })
      //         .catch(error => console.error('Error:', error));
      // }
      // function downloadFile(url) {
      //     const link = document.createElement('a');
      //     link.href = url;
      //     link.download = url.substring(url.lastIndexOf('/') + 1);
      //     document.body.appendChild(link);
      //     link.click();
      //     document.body.removeChild(link);
      // }
      //--------------------------------------------------------------------------------

      function selectCategories() {
          var selectedCategories = getUrlParameterArray('categories[]');
          if (selectedCategories.length) {
              $('#cat_drop').val(selectedCategories).selectpicker('refresh');
          }
      }

    });
  </script>
  
  <script>
        document.getElementById('generatePdfForm').addEventListener('submit', function(event) {
            event.preventDefault();
            var form = event.target;
            var formData = new FormData(form);
            fetch(form.action, {
                method: form.method,
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': formData.get('_token')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.filename) {
                    checkPdfAvailability(data.filename);
                }
            })
        });
  </script>
  
@endsection
