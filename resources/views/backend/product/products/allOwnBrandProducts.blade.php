@extends('backend.layouts.app')
@section('content')
  
  @php
    CoreComponentRepository::instantiateShopRepository();
    CoreComponentRepository::initializeCache();
  @endphp

  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
      <div class="col-auto">
        <h1 class="h3">{{ translate('All products') }}</h1>
      </div>
      <div class="col text-right">
        <?php /*@if ($type != 'Seller' &&
                auth()->user()->can('add_new_product'))
          <a href="{{ route('products.create') }}" class="btn btn-circle btn-info">
            <span>{{ translate('Add New Product') }}</span>
          </a>
        @endif
        <a href="{{ route('products.no-images') }}" class="btn btn-circle btn-danger">
          <span>{{ translate('Products without Images') }}</span>
        </a> */ ?>
      </div>
    </div>
  </div>
  <br>
  <div class="card">
    <div class="card-header row gutters-5">
      <?php /*<div class="col-md-2 ml-auto">
        <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="seller[]" id="seller_drop" multiple="multiple" title="{{ translate('Select Seller') }}">
        <option disabled value="">{{ translate('Select Seller') }}</option>
          @foreach($seller as $key=>$value)
            <option value="{{$value->id}}">{{$value->name}}</option>
          @endforeach
        </select>
      </div> */ ?>
      <div class="col-md-4">
        <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="cat_groups[]" id="cat_group_drop" multiple="multiple" title="{{ translate('Select Category Group') }}">
          <option disabled value="">{{ translate('Select Category Group') }}</option>
          @foreach($category_group as $key=>$value)
            <option value="{{$value->id}}">{{$value->name}}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="categories[]" id="categories_drop" multiple="multiple" title="{{ translate('Select Category') }}" disabled>
          <option disabled value="">{{ translate('Select Category') }}</option>
        </select>
      </div>
      <?php /*<div class="col-md-2 ml-auto">
        <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="brands[]" id="brand_drop" multiple="multiple" title="{{ translate('Select Brand') }}">
          <option disabled value="">{{ translate('Select Brand') }}</option>
          @foreach($brands as $key=>$value)
            <option value="{{$value->id}}">{{$value->name}}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="stock" id="stock">
          <option disabled selected value="">{{ translate('Select Stock') }}</option>
          <option value="2">{{ translate('All') }}</option>
          <option value="1">{{ translate('In Stock') }}</option>
          <option value="0">{{ translate('Out of Stock') }}</option>
        </select>
      </div> */ ?>
      <div class="col-md-2">
        <a href="javascript:void(0)" class="btn btn-circle btn-info" id="exportBtn">
          <span>{{ translate('Export') }}</span>
        </a>
        <a href="javascript:void(0)" class="btn btn-circle btn-success" id="pullBtn">
          <span>{{ translate('Pull') }}</span>
        </a>
      </div>
    </div>
    <form class="" id="sort_products" action="" method="GET">
      <div class="card-header row gutters-5">
        <div class="col">
          <h5 class="mb-md-0 h6">{{ translate('All Product') }}</h5>
        </div>
        <div class="col-md-4 ml-auto">
          <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="type" id="type"
            onchange="sort_products()">
            <option value="">{{ translate('Sort By') }}</option>
            <option value="mrp,desc"
              @isset($col_name, $query) @if ($col_name == 'mrp' && $query == 'desc') selected @endif @endisset>
              {{ translate('Price (High > Low)') }}</option>
            <option value="mrp,asc"
              @isset($col_name, $query) @if ($col_name == 'mrp' && $query == 'asc') selected @endif @endisset>
              {{ translate('Price (Low > High)') }}</option>
            <!-- <option
              value="num_of_sale,desc"@isset($col_name, $query) @if ($col_name == 'num_of_sale' && $query == 'desc') selected @endif @endisset>
              {{ translate('Num of Sale (High > Low)') }}</option>
            <option
              value="num_of_sale,asc"@isset($col_name, $query) @if ($col_name == 'num_of_sale' && $query == 'asc') selected @endif @endisset>
              {{ translate('Num of Sale (Low > High)') }}</option> -->
          </select>
        </div>
        <div class="col-md-2">
          <div class="form-group mb-0">
            <input type="text" class="form-control form-control-sm" id="search"
              name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
              placeholder="{{ translate('Type & Enter') }}">
          </div>
        </div>
      </div>

      <div class="card-body">
        <table class="table aiz-table mb-0">
          <thead>
            <tr>
              @if (auth()->user()->can('product_delete'))
                <th>
                  <div class="form-group">
                    <div class="aiz-checkbox-inline">
                      <label class="aiz-checkbox">
                        <input type="checkbox" class="check-all">
                        <span class="aiz-square-check"></span>
                      </label>
                    </div>
                  </div>
                </th>
              @else
                <th data-breakpoints="lg">#</th>
              @endif
              <th>{{ translate('Name') }}</th>
              <th data-breakpoints="lg">{{ translate('Published') }}</th>
              <th data-breakpoints="lg">{{ translate('Approved') }}</th>
              <th data-breakpoints="sm" class="text-right">{{ translate('Options') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($products as $key => $product)
              <tr>
                @if (auth()->user()->can('product_delete'))
                  <td>
                    <div class="form-group d-inline-block">
                      <label class="aiz-checkbox">
                        <input type="checkbox" class="check-one" name="id[]" value="{{ $product->id }}">
                        <span class="aiz-square-check"></span>
                      </label>
                    </div>
                  </td>
                @else
                  <td>{{ $key + 1 + ($products->currentPage() - 1) * $products->perPage() }}</td>
                @endif
                <td>
                  <div class="row gutters-5 w-200px w-md-300px mw-100">
                    <div class="col-auto">
                      <img src="{{ uploaded_asset($product->thumbnail_img) }}" alt="Image" class="size-50px img-fit">
                    </div>
                    <div class="col">
                      <span class="text-muted text-truncate-2">{{ $product->getTranslation('name') }}</span>
                    </div>
                  </div>
                </td>
                <td>
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input onchange="update_published(this)" value="{{ $product->id }}" type="checkbox"
                      <?php if ($product->published == 1) {
                          echo 'checked';
                      } ?>>
                    <span class="slider round"></span>
                  </label>
                </td>
                <td>
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input onchange="update_approved(this)" value="{{ $product->id }}" type="checkbox"
                      <?php if ($product->approved == 1) {
                          echo 'checked';
                      } ?>>
                    <span class="slider round"></span>
                  </label>
                </td>
                <td class="text-right">
                  <!-- <a class="btn btn-soft-success btn-icon btn-circle btn-sm"
                    href="{{ route('product', $product->slug) }}" target="_blank" title="{{ translate('View') }}">
                    <i class="las la-eye"></i>
                  </a> -->
                  @can('product_edit')
                      <a class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                        href="{{ route('products.admin.ownBrandProductEdit', ['id' => $product->id, 'lang' => env('DEFAULT_LANGUAGE')]) }}"
                        title="{{ translate('Edit') }}">
                        <i class="las la-edit"></i>
                      </a>
                  @endcan
                  @can('product_delete')
                    <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                      data-href="{{ route('products.ownBrandProductDelete', $product->id) }}" title="{{ translate('Delete') }}">
                      <i class="las la-trash"></i>
                    </a>
                  @endcan
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <div class="aiz-pagination">
          {{ $products->appends(request()->input())->links() }}
        </div>
      </div>
    </form>
  </div>
@endsection

@section('modal')
  @include('modals.delete_modal')
@endsection


@section('script')
  <script type="text/javascript">
    $(document).on("change", ".check-all", function() {
      if (this.checked) {
        // Iterate each checkbox
        $('.check-one:checkbox').each(function() {
          this.checked = true;
        });
      } else {
        $('.check-one:checkbox').each(function() {
          this.checked = false;
        });
      }

    });

    $(document).ready(function() {

      $('#cat_group_drop').change(function () {
          var category_group_id = $('#cat_group_drop').val();
          
          if (category_group_id.length == 0) {
            var category_group_id = 0;
          }
          
          if (category_group_id != 0) {
              // AJAX call to fetch child options
              $.ajax({
                  url: '{{ route("getOwnBrandCategoriesFromAdmin") }}',
                  type: 'GET',
                  data: { category_group_id: category_group_id },
                  dataType: 'json',
                  success: function (response) {
                      $('#categories_drop').empty();
                      $('#categories_drop').prop('disabled', false);

                      // Append options
                      $.each(response, function (key, value) {
                          var option = $('<option></option>')
                              .attr('value', value.id)
                              .text(value.name);
                          $('#categories_drop').append(option);
                      });
                      // Refresh the aiz-selectpicker
                      $('#categories_drop').selectpicker('refresh');
                  },
                  error: function (xhr, status, error) {
                      console.error(xhr.responseText);
                  }
              });
          } else {
              $('#categories_drop').empty().prop('disabled', true);
              $('#categories_drop').selectpicker('refresh');
          }
          // $.ajax({
          //     url: '{{ route("getBrandsFromAdmin") }}',
          //     type: 'GET',
          //     data: { seller_id: seller_id, category_group_id: category_group_id, category_id: category_id },
          //     dataType: 'json',
          //     success: function (response) {
          //         $('#brand_drop').empty();
          //         $('#brand_drop').prop('disabled', false);
          //         $.each(response, function (key, value) {
          //             var option = $('<option></option>')
          //                 .attr('value', value.id)
          //                 .text(value.name);
          //             $('#brand_drop').append(option);
          //         });
          //         // Refresh the aiz-selectpicker
          //         $('#brand_drop').selectpicker('refresh');
          //     },
          //     error: function (xhr, status, error) {
          //         console.error(xhr.responseText);
          //     }
          // });
      });

      // $('#categories_drop').change(function () {
      //   var seller_drop = $('#seller_drop').val();
      //   if(seller_drop.length == 0){
      //     seller_drop = 0;
      //   }
      //   var cat_group_drop = $('#cat_group_drop').val();
      //   if(cat_group_drop.length == 0){
      //     cat_group_drop = 0;
      //   }
      //   var categories_drop = $('#categories_drop').val();
      //   if(categories_drop.length == 0){
      //     categories_drop = 0;
      //   }
      //   $.ajax({
      //       url: '{{ route("getBrandsFromAdmin") }}',
      //       type: 'GET',
      //       data: { seller_id: seller_drop, category_group_id: cat_group_drop, category_id: categories_drop },
      //       dataType: 'json',
      //       success: function (response) {
      //           $('#brand_drop').empty();
      //           $('#brand_drop').prop('disabled', false);
      //           // Append options
      //           $.each(response, function (key, value) {
      //               var option = $('<option></option>')
      //                   .attr('value', value.id)
      //                   .text(value.name);
      //               $('#brand_drop').append(option);
      //           });
      //           // Refresh the aiz-selectpicker
      //           $('#brand_drop').selectpicker('refresh');
      //       },
      //       error: function (xhr, status, error) {
      //           console.error(xhr.responseText);
      //       }
      //   });
      // });

      $('#exportBtn').on('click', function () {
        var seller_drop = $('#seller_drop').val() || 0;
        var cat_group_drop = $('#cat_group_drop').val() || 0;
        var categories_drop = $('#categories_drop').val() || 0;
        var brand_drop = $('#brand_drop').val() || 0;
        var stock = $('#stock').val() || 2;
        $.ajax({
            url: '{{ route("products.exportDataToGoogleSheet") }}',
            type: 'GET',
            beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
            data: { 
                seller_id: seller_drop, 
                category_group_id: cat_group_drop, 
                category_id: categories_drop, 
                brand_id: brand_drop, 
                stock: stock
            },
            dataType: 'json',
            success: function (response) {
                // alert("Successfully Exported to Google Sheet.");
                AIZ.plugins.notify('success', '{{ translate('Successfully Exported to Google Sheet.') }}');
            },
            complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
      });

      $('#pullBtn').on('click', function () {
        var cat_group_drop = $('#cat_group_drop').val() || 0;
        var categories_drop = $('#categories_drop').val() || 0;
        var stock = $('#stock').val() || 2;
        $.ajax({
            url: '{{ route("products.createOrUpdateTheOwnBrandProductsFromGoogleSheet") }}',
            type: 'GET',
            beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
            // data: { 
            //     seller_id: seller_drop, 
            //     category_group_id: cat_group_drop, 
            //     category_id: categories_drop, 
            //     stock: stock
            // },
            dataType: 'json',
            success: function (response) {
                AIZ.plugins.notify('success', '{{ translate("Successfully Updated From Google sheet.") }}');
            },
            complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
      });

    });

    function getUrlParameterArray(name) {
        var results = [];
        var regex = new RegExp('[\\?&]' + name + '%5B%5D=([^&#]*)', 'g');  // Updated regex to handle [] in parameter names
        var result;
        while ((result = regex.exec(location.search)) !== null) {
            results.push(decodeURIComponent(result[1].replace(/\+/g, ' ')));
        }
        return results;
    }


    function update_published(el) {
      if (el.checked) {
        var status = 1;
      } else {
        var status = 0;
      }
      $.post('{{ route('products.ownBrandPublished') }}', {
        _token: '{{ csrf_token() }}',
        id: el.value,
        status: status
      }, function(data) {
        if (data == 1) {
          AIZ.plugins.notify('success', '{{ translate('Published products updated successfully') }}');
        } else {
          AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
        }
      });
    }

    function update_approved(el) {
      if (el.checked) {
        var approved = 1;
      } else {
        var approved = 0;
      }
      $.post('{{ route('products.ownBrandApproved') }}', {
        _token: '{{ csrf_token() }}',
        id: el.value,
        approved: approved
      }, function(data) {
        if (data == 1) {
          AIZ.plugins.notify('success', '{{ translate('Product approval update successfully') }}');
        } else {
          AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
        }
      });
    }

    function sort_products(el) {
      $('#sort_products').submit();
    }

    function bulk_delete() {
      var data = new FormData($('#sort_products')[0]);
      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: "{{ route('bulk-product-delete') }}",
        type: 'POST',
        data: data,
        cache: false,
        contentType: false,
        processData: false,
        success: function(response) {
          if (response == 1) {
            location.reload();
          }
        }
      });
    }
  </script>
@endsection
