@extends('backend.layouts.app')
@section('content')
  
  @php
    CoreComponentRepository::instantiateShopRepository();
    CoreComponentRepository::initializeCache();

     // detect 41 manager
    $title = strtolower(trim((string) auth()->user()->user_title));
    $is41Manager = in_array($title, ['manager_41'], true);
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
        @endif */ ?>
        <a href="{{ route('products.no-images') }}" class="btn btn-circle btn-danger">
          <span>{{ translate('Products without Images') }}</span>
        </a>
      </div>
       {{-- Zoho Sync: hide for 41_manager --}}
       @if (! $is41Manager)
        <div class="col-md-2">
           
            <a href="javascript:void(0);" class="btn btn-circle btn-warning" id="zohoSyncBtn">
              <span><i class="las la-sync-alt"></i> Zoho Sync</span>
          </a>
        </div>
      @endif
    </div>
  </div>
  <br>
  <div class="card">
    <div class="card-header row gutters-5">
      <div class="col-md-2 ml-auto">
        <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="seller[]" id="seller_drop" multiple="multiple" title="{{ translate('Select Seller') }}">
        <option disabled value="">{{ translate('Select Seller') }}</option>
          @foreach($seller as $key=>$value)
            <option value="{{$value->id}}">{{$value->name}}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="cat_groups[]" id="cat_group_drop" multiple="multiple" title="{{ translate('Select Category Group') }}">
          <option disabled value="">{{ translate('Select Category Group') }}</option>
          @foreach($category_group as $key=>$value)
            <option value="{{$value->id}}">{{$value->name}}</option>
          @endforeach 
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="categories[]" id="categories_drop" multiple="multiple" title="{{ translate('Select Category') }}" disabled>
          <option disabled value="">{{ translate('Select Category') }}</option>
        </select>
      </div>
      <div class="col-md-2 ml-auto">
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
      </div>
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
        @if (! $is41Manager && auth()->user()->can('product_delete'))
          <div class="dropdown mb-2 mb-md-0">
            <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
              {{ translate('Bulk Action') }}
            </button>
            <div class="dropdown-menu dropdown-menu-right">
              <a class="dropdown-item" href="#" onclick="bulk_delete()"> {{ translate('Delete selection') }}</a>
            </div>
          </div>
        @endif

        <div class="col-md-2 ml-auto">
          <select class="form-control form-control-sm aiz-selectpicker mb-2 mb-md-0" name="type" id="type"
            onchange="sort_products()">
            <option value="">{{ translate('Sort By') }}</option>
            <option value="rating,desc"
              @isset($col_name, $query) @if ($col_name == 'rating' && $query == 'desc') selected @endif @endisset>
              {{ translate('Rating (High > Low)') }}</option>
            <option value="rating,asc"
              @isset($col_name, $query) @if ($col_name == 'rating' && $query == 'asc') selected @endif @endisset>
              {{ translate('Rating (Low > High)') }}</option>
            <option
              value="num_of_sale,desc"@isset($col_name, $query) @if ($col_name == 'num_of_sale' && $query == 'desc') selected @endif @endisset>
              {{ translate('Num of Sale (High > Low)') }}</option>
            <option
              value="num_of_sale,asc"@isset($col_name, $query) @if ($col_name == 'num_of_sale' && $query == 'asc') selected @endif @endisset>
              {{ translate('Num of Sale (Low > High)') }}</option>
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
              <th data-breakpoints="sm">{{ translate('Info') }}</th>
              <th data-breakpoints="md">{{ translate('Total Stock') }}</th>
              <th data-breakpoints="lg">{{ translate('Published') }}</th>
              <th data-breakpoints="lg">{{ translate('Featured') }}</th>
              <th data-breakpoints="sm" class="text-right">{{ translate('Options') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($products as $key => $product)
            @php
            $file_path = $product->thumbnail_image 
              ? url('public/' . $product->thumbnail_image) 
              : url('public/assets/img/placeholder.jpg');
            @endphp
              <tr  @if (is_null($product->zoho_item_id)) style="background-color: #fff8dc;" @endif>
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
                      <img src="{{ $file_path }}" alt="Image" class="size-50px img-fit">
                      
                    </div>
                    <div class="col">
                      <span class="text-muted text-truncate-2">{{ $product->getTranslation('name') }}</span>
                      <small class="text-primary">Part No: {{ $product->part_no }}</small>
                    </div>
                  </div>
                </td>
                <td>
                  <strong>{{ translate('Num of Sale') }}:</strong> {{ $product->num_of_sale }} {{ translate('times') }}
                  </br>
                  <strong>{{ translate('Base Price') }}:</strong> {{ single_price($product->unit_price) }} </br>
                  <strong>{{ translate('Rating') }}:</strong> {{ $product->rating }} </br>
                  <strong>{{ translate('Inhouse Stock') }}:</strong> 
                  {{ DB::table('products_api')->where('part_no', $product->part_no)->where('closing_stock', '>', 0)->exists() ? translate('Yes') : translate('No') }}

                </td>

                <td>
                  @php
                    // [NEW] Fallback detection if controller didn’t pass $is41Manager
                    if (!isset($is41Manager)) {
                        $title = strtolower(trim((string) (Auth::user()->user_title ?? '')));
                        $is41Manager = in_array($title, ['manager_41'], true);
                    }
                
                    $is41 = $is41Manager ?? false;
                
                    // [UPDATED] One source of truth: ProductWarehouse with role-based filter
                    $qty = \App\Models\ProductWarehouse::query()
                        ->where('product_id', $product->id)
                        ->when($is41, fn($q) => $q->where('is_manager_41', 1))
                        ->when(!$is41, function ($q) {
                            $q->where(function($sub){
                                $sub->whereNull('is_manager_41')->orWhere('is_manager_41', 0);
                            });
                        })
                        ->sum('qty');
                
                    echo (int) $qty;
                  @endphp
                
                  @if ((int)$qty <= (int)($product->low_stock_quantity ?? 0))
                    <span class="badge badge-inline badge-danger">Low</span>
                  @endif
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
                    <input onchange="update_featured(this)" value="{{ $product->id }}" type="checkbox"
                      <?php if ($product->featured == 1) {
                          echo 'checked';
                      } ?>>
                    <span class="slider round"></span>
                  </label>
                </td>
                <td class="text-right">
                  <a class="btn btn-soft-success btn-icon btn-circle btn-sm"
                    href="{{ route('product', $product->slug) }}" target="_blank" title="{{ translate('View') }}">
                    <i class="las la-eye"></i>
                  </a>
                  {{-- Hide EDIT buttons for 41 manager --}}
                  @if (! $is41Manager)
                    @can('product_edit')
                      @if ($type == 'Seller')
                        <a class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                           href="{{ route('products.seller.edit', ['id' => $product->id, 'lang' => env('DEFAULT_LANGUAGE')]) }}"
                           title="{{ translate('Edit') }}">
                          <i class="las la-edit"></i>
                        </a>
                      @else
                        <a class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                           href="{{ route('products.admin.edit', ['id' => $product->id, 'lang' => env('DEFAULT_LANGUAGE')]) }}"
                           title="{{ translate('Edit') }}">
                          <i class="las la-edit"></i>
                        </a>
                      @endif
                    @endcan
                  @endif
                  @can('product_duplicate')
                    <a class="btn btn-soft-warning btn-icon btn-circle btn-sm"
                      href="{{ route('products.duplicate', ['id' => $product->id, 'type' => $type]) }}"
                      title="{{ translate('Duplicate') }}">
                      <i class="las la-copy"></i>
                    </a>
                  @endcan
                  @if (! $is41Manager && auth()->user()->can('product_delete'))
                    <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                      data-href="{{ route('products.destroy', $product->id) }}" title="{{ translate('Delete') }}">
                      <i class="las la-trash"></i>
                    </a>
                 @endif

                  <button type="button"
                    class="btn btn-soft-info btn-icon btn-circle btn-sm"
                    data-toggle="modal"
                    data-target="#barcodeModal"
                    data-part_no="{{ $product->part_no }}"
                    data-product_name="{{ $product->name }}"
                    data-barcode_name="{{ $product->barcode_name ?? '' }}"
                    data-mrp="{{ (float)($product->mrp ?? 0) }}"
                    data-unit_price="{{ (float)($product->unit_price ?? 0) }}">
                    <i class="las la-barcode"></i>
                  </button>
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


<!-- Barcode Modal -->
<!-- Barcode Modal -->
<div class="modal fade" id="barcodeModal" tabindex="-1" role="dialog" aria-labelledby="barcodeModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="barcodeForm" action="{{ route('print.barcode') }}" method="GET" target="_blank">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="barcodeModalLabel">Generate Barcode</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>Part No</label>
            <input style="color: #074e86; font-weight: bold;" type="text" class="form-control" name="part_no" id="modal_part_no" readonly>
          </div>

          {{-- ✅ Name block --}}
          <div class="form-group">
            <label class="mb-1 d-block">Name</label>
            <select class="form-control" id="name_mode" name="name_mode">
              <option value="item">Item Name</option>
              <option value="barcode">Barcode Name</option>
            </select>
          </div>

          <div class="form-group" id="itemNameWrap">
            <label>Item Name</label>
            <input type="text" class="form-control" id="item_name" name="item_name" readonly>
          </div>

          <div class="form-group d-none" id="barcodeNameWrap">
            <label>Barcode Name</label>
            <input type="text" class="form-control" id="barcode_name" name="barcode_name" placeholder="Enter barcode name (optional)">
            <small class="text-muted">If provided, this will be saved to the product.</small>
          </div>

          {{-- ✅ MRP block (editable & used for PDF @ +20%) --}}
          <div class="form-group">
            <label>MRP (₹)</label>
            <input type="number" step="0.01" class="form-control" id="mrp" name="mrp" placeholder="0.00">
            <small class="text-muted">This MRP will be printed in the PDF (automatically +20% for label display).</small>
          </div>

          <div class="form-group">
            <label>Quantity per Barcode</label>
            <input type="number" class="form-control" name="qty" required>
          </div>

          <div class="form-group">
            <label>Number of Copies</label>
            <input type="number" class="form-control" name="copies" required>
          </div>

          <!-- Imported By -->
          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="customImporterCheckbox" name="imported_by" value="1">
            <label class="form-check-label" for="customImporterCheckbox">Imported By</label>
          </div>

          <div id="customImporterFields" style="display: none; margin-top: 15px;">
            <div class="form-group">
              <label>Name Of Importer</label>
              <input type="text" class="form-control" name="custom_company">
            </div>

            <div class="form-group">
              <label>Address</label>
              <textarea class="form-control" name="custom_address" rows="2"></textarea>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Customer Care Number</label>
                <input type="text" class="form-control" name="custom_phone" value="+91 6287859750">
              </div>
              <div class="form-group col-md-6">
                <label>Email Id</label>
                <input type="email" class="form-control" name="custom_email" value="acetools505@gmail.com">
              </div>
            </div>

            <div class="form-group mt-3">
              <label>Country of Origin</label>
              <input type="text" class="form-control" name="country_of_origin" value="People’s Republic China.">
            </div>

            <div class="form-group">
              <label>Month and Year of Mfg</label>
              <input type="month" class="form-control" name="mfg_month_year">
              <small class="form-text text-muted">Format: YYYY-MM (e.g., 2025-02)</small>
            </div>
          </div>

          <!-- Marketed By (optional like Imported By) -->
          <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" id="marketedByCheckbox" name="marketed_by" value="1">
            <label class="form-check-label" for="marketedByCheckbox">Marketed By</label>
          </div>

          <div id="marketedByFields" style="display: none; margin-top: 15px;">
            <div class="form-group">
              <label>Name Of Marketer</label>
              <input type="text" class="form-control" name="marketed_company" placeholder="ACE TOOLS PRIVATE LIMITED">
            </div>

            <div class="form-group">
              <label>Address</label>
              <textarea class="form-control" name="marketed_address" rows="2" placeholder="Pal Colony, Village Rithala, NEW DELHI - 110085"></textarea>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Customer Care Number</label>
                <input  type="text" class="form-control" name="marketed_phone" placeholder="9730377752">
              </div>
              <div class="form-group col-md-6">
                <label>Email Id</label>
                <input type="email" class="form-control" name="marketed_email" placeholder="support@example.com">
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" id="generateBtn" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            <span class="btn-text">Generate</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>






<!-- ✅  Alert Zoho Sync Modal -->
<div class="modal fade" id="syncResultModal" tabindex="-1" role="dialog" aria-labelledby="syncResultModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow rounded text-center">

      <!-- Header Border Strip -->
      <div style="height: 5px; background: linear-gradient(to right, #28a745, #218838); border-top-left-radius: .3rem; border-top-right-radius: .3rem;"></div>

      <div class="modal-body py-4 px-3">

        <h5 class="text-success font-weight-bold mb-2">
          <i class="las la-thumbs-up"></i> Success: <span id="syncSuccessCount">0</span>
        </h5>

        <h6 class="text-danger font-weight-bold mb-3">
          <i class="las la-exclamation-triangle"></i> Failed: <span id="syncFailedCount">0</span>
        </h6>

        <p class="text-muted small mb-4">
          Products have been pushed to Zoho.<br>Click OK to reload and update the status.
        </p>

        <button type="button" class="btn btn-primary btn-sm px-4" id="reloadAfterSync">
          <i class="las la-redo-alt mr-1"></i> OK, Reload
        </button>
      </div>

    </div>
  </div>
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
      //$('#container').removeClass('mainnav-lg').addClass('mainnav-sm');
      $('#seller_drop').change(function () {
          var seller_drop = $('#seller_drop').val();
          if (seller_drop.length != 0) {
              // AJAX call to get brand
              $.ajax({
                  url: '{{ route("getBrandsFromAdmin") }}',
                  type: 'GET',
                  data: { seller_id: seller_drop, category_group_id: 0, category_id: 0 },
                  dataType: 'json',
                  success: function (response) {
                      $('#brand_drop').empty();
                      $('#brand_drop').prop('disabled', false);
                      // Append options
                      $.each(response, function (key, value) {
                          var option = $('<option></option>')
                              .attr('value', value.id)
                              .text(value.name);
                          $('#brand_drop').append(option);
                      });
                      // Refresh the aiz-selectpicker
                      $('#brand_drop').selectpicker('refresh');
                  },
                  error: function (xhr, status, error) {
                      console.error(xhr.responseText);
                  }
              });
              // AJAX call to get cat group
              $.ajax({
                  url: '{{ route("getCatGroupBySellerWise") }}',
                  type: 'GET',
                  data: { seller_id: seller_drop },
                  dataType: 'json',
                  success: function (response) {
                      $('#cat_group_drop').empty();
                      $('#cat_group_drop').prop('disabled', false);
                      // Append options
                      $.each(response, function (key, value) {
                          var option = $('<option></option>')
                              .attr('value', value.id)
                              .text(value.name);
                          $('#cat_group_drop').append(option);
                      });
                      // Refresh the aiz-selectpicker
                      $('#cat_group_drop').selectpicker('refresh');
                  },
                  error: function (xhr, status, error) {
                      console.error(xhr.responseText);
                  }
              });
              $('#categories_drop').empty();
              $('#categories_drop').prop('disabled', true);
              $('#categories_drop').selectpicker('refresh');
          } else {
              // AJAX call to get brand
              $.ajax({
                  url: '{{ route("getBrandsFromAdmin") }}',
                  type: 'GET',
                  data: { seller_id: 0, category_group_id: 0, category_id: 0 },
                  dataType: 'json',
                  success: function (response) {
                      $('#brand_drop').empty();
                      $('#brand_drop').prop('disabled', false);
                      // Append options
                      $.each(response, function (key, value) {
                          var option = $('<option></option>')
                              .attr('value', value.id)
                              .text(value.name);
                          $('#brand_drop').append(option);
                      });
                      // Refresh the aiz-selectpicker
                      $('#brand_drop').selectpicker('refresh');
                  },
                  error: function (xhr, status, error) {
                      console.error(xhr.responseText);
                  }
              });
              // AJAX call to get cat group
              $.ajax({
                  url: '{{ route("getCatGroupBySellerWise") }}',
                  type: 'GET',
                  data: { seller_id: 0 },
                  dataType: 'json',
                  success: function (response) {
                      $('#cat_group_drop').empty();
                      $('#cat_group_drop').prop('disabled', false);
                      // Append options
                      $.each(response, function (key, value) {
                          var option = $('<option></option>')
                              .attr('value', value.id)
                              .text(value.name);
                          $('#cat_group_drop').append(option);
                      });
                      // Refresh the aiz-selectpicker
                      $('#cat_group_drop').selectpicker('refresh');
                  },
                  error: function (xhr, status, error) {
                      console.error(xhr.responseText);
                  }
              });
              $('#categories_drop').empty();
              $('#categories_drop').prop('disabled', true);
              $('#categories_drop').selectpicker('refresh');
          }
      });

      $('#cat_group_drop').change(function () {
          var category_group_id = $('#cat_group_drop').val();
          
          if (category_group_id.length == 0) {
            var category_group_id = 0;
          }
          var seller_id = $('#seller_drop').val();
          if(seller_id.length == 0){
            var seller_id = 0;
          }
          var category_id = $('#categories_drop').val();
          if(category_id.length == 0){
            var category_id = 0;
          }
          if (category_group_id != 0) {
              // AJAX call to fetch child options
              $.ajax({
                  url: '{{ route("getCategoriesFromAdmin") }}',
                  type: 'GET',
                  data: { seller_id: seller_id, category_group_id: category_group_id },
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
          $.ajax({
              url: '{{ route("getBrandsFromAdmin") }}',
              type: 'GET',
              data: { seller_id: seller_id, category_group_id: category_group_id, category_id: category_id },
              dataType: 'json',
              success: function (response) {
                  $('#brand_drop').empty();
                  $('#brand_drop').prop('disabled', false);
                  $.each(response, function (key, value) {
                      var option = $('<option></option>')
                          .attr('value', value.id)
                          .text(value.name);
                      $('#brand_drop').append(option);
                  });
                  // Refresh the aiz-selectpicker
                  $('#brand_drop').selectpicker('refresh');
              },
              error: function (xhr, status, error) {
                  console.error(xhr.responseText);
              }
          });
      });

      $('#categories_drop').change(function () {
        var seller_drop = $('#seller_drop').val();
        if(seller_drop.length == 0){
          seller_drop = 0;
        }
        var cat_group_drop = $('#cat_group_drop').val();
        if(cat_group_drop.length == 0){
          cat_group_drop = 0;
        }
        var categories_drop = $('#categories_drop').val();
        if(categories_drop.length == 0){
          categories_drop = 0;
        }
        $.ajax({
            url: '{{ route("getBrandsFromAdmin") }}',
            type: 'GET',
            data: { seller_id: seller_drop, category_group_id: cat_group_drop, category_id: categories_drop },
            dataType: 'json',
            success: function (response) {
                $('#brand_drop').empty();
                $('#brand_drop').prop('disabled', false);
                // Append options
                $.each(response, function (key, value) {
                    var option = $('<option></option>')
                        .attr('value', value.id)
                        .text(value.name);
                    $('#brand_drop').append(option);
                });
                // Refresh the aiz-selectpicker
                $('#brand_drop').selectpicker('refresh');
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
      });

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
        var seller_drop = $('#seller_drop').val() || 0;
        var cat_group_drop = $('#cat_group_drop').val() || 0;
        var categories_drop = $('#categories_drop').val() || 0;
        var stock = $('#stock').val() || 2;
        $.ajax({
            url: '{{ route("products.updateProductsFromGoogleSheet") }}',
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

    function update_todays_deal(el) {
      if (el.checked) {
        var status = 1;
      } else {
        var status = 0;
      }
      $.post('{{ route('products.todays_deal') }}', {
        _token: '{{ csrf_token() }}',
        id: el.value,
        status: status
      }, function(data) {
        if (data == 1) {
          AIZ.plugins.notify('success', '{{ translate('Todays Deal updated successfully') }}');
        } else {
          AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
        }
      });
    }

    function update_published(el) {
      if (el.checked) {
        var status = 1;
      } else {
        var status = 0;
      }
      $.post('{{ route('products.published') }}', {
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
      $.post('{{ route('products.approved') }}', {
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

    function update_featured(el) {
      if (el.checked) {
        var status = 1;
      } else {
        var status = 0;
      }
      $.post('{{ route('products.featured') }}', {
        _token: '{{ csrf_token() }}',
        id: el.value,
        status: status
      }, function(data) {
        if (data == 1) {
          AIZ.plugins.notify('success', '{{ translate('Featured products updated successfully') }}');
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

    // When opening the modal: clear old values, then set the part no and hide importer fields
    // OPEN modal: reset, prefill, decide name mode & fields
$('#barcodeModal').on('show.bs.modal', function (event) {
  const $modal = $(this);
  const $form  = $('#barcodeForm');
  if ($form.length && $form[0]) $form[0].reset();

  const button       = $(event.relatedTarget);
  const partNo       = button.data('part_no') || '';
  const prodName     = button.data('product_name') || '';
  const barcodeName  = button.data('barcode_name') || '';
  const mrpRaw       = parseFloat(button.data('mrp')) || 0;
  const unitPriceRaw = parseFloat(button.data('unit_price')) || 0;

  // Base for +20%: prefer MRP, fallback to unit_price
  const base = mrpRaw > 0 ? mrpRaw : (unitPriceRaw > 0 ? unitPriceRaw : 0);
  // Show +20% in the textbox (rounded to integer label style; change to .toFixed(2) if you want 2dp)
  const mrpPlus20 = base > 0 ? Math.round(base * 1.20) : '';

  $modal.find('#modal_part_no').val(partNo);
  $('#item_name').val(prodName);
  $('#barcode_name').val(barcodeName);
  $('#mrp').val(mrpPlus20);

  // default name mode
  if (barcodeName && barcodeName.trim() !== '') {
    $('#name_mode').val('barcode');
    $('#barcodeNameWrap').removeClass('d-none');
    $('#itemNameWrap').addClass('d-none');
  } else {
    $('#name_mode').val('item');
    $('#itemNameWrap').removeClass('d-none');
    $('#barcodeNameWrap').addClass('d-none');
  }

  const $btn = $('#generateBtn');
  $btn.prop('disabled', false);
  $btn.find('.spinner-border').addClass('d-none');
  $btn.find('.btn-text').text('Generate');
});

    // CLOSE modal: hard reset everything (so old values stick na rahen)
$('#barcodeModal').on('hidden.bs.modal', function () {
  const $form = $('#barcodeForm');
  if ($form.length && $form[0]) $form[0].reset();
  $('#modal_part_no').val('');
  $('#customImporterFields').hide();
  $('#customImporterCheckbox').prop('checked', false);
  $('#marketedByFields').hide();
  $('#marketedByCheckbox').prop('checked', false);
  $('#itemNameWrap').removeClass('d-none');
  $('#barcodeNameWrap').addClass('d-none');
  const $btn = $('#generateBtn');
  $btn.prop('disabled', false);
  $btn.find('.spinner-border').addClass('d-none');
  $btn.find('.btn-text').text('Generate');
});

// Name mode switcher
$('#name_mode').on('change', function () {
  if ($(this).val() === 'barcode') {
    $('#barcodeNameWrap').removeClass('d-none');
    $('#itemNameWrap').addClass('d-none');
  } else {
    $('#itemNameWrap').removeClass('d-none');
    $('#barcodeNameWrap').addClass('d-none');
  }
});

     // Imported By toggle
$('#customImporterCheckbox').on('change', function () {
  if ($(this).is(':checked')) {
    $('#customImporterFields').slideDown();
  } else {
    $('#customImporterFields').slideUp();
    $('#customImporterFields').find('input, textarea').val('');
  }
});


    // Marketed By toggle
$('#marketedByCheckbox').on('change', function () {
  if ($(this).is(':checked')) {
    $('#marketedByFields').slideDown();
  } else {
    $('#marketedByFields').slideUp();
    $('#marketedByFields').find('input, textarea').val('');
  }
});

  </script>


<script>
$(document).ready(function () {
    $('#barcodeForm').submit(function (e) {
  e.preventDefault();
  const form = $(this);
  const submitBtn = $('#generateBtn');
  const spinner = submitBtn.find('.spinner-border');
  const btnText = submitBtn.find('.btn-text');

  submitBtn.prop('disabled', true);
  spinner.removeClass('d-none');
  btnText.text('Generating...');

  const baseUrl = "{{ route('print.barcode') }}";
  const query = form.serialize();
  const fullUrl = `${baseUrl}?${query}`;

  $.ajax({
    url: fullUrl,
    method: 'GET',
    success: function (response) {
      if (response.success && response.url) {
        const link = document.createElement('a');
        link.href = response.url;
        link.download = 'barcode-labels.pdf';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      } else {
        alert('❌ Not hit');
      }
    },
    error: function () {
      alert('❌ AJAX error');
    },
    complete: function () {
      submitBtn.prop('disabled', false);
      spinner.addClass('d-none');
      btnText.text('Generate');
    }
  });
});

    function resetButton(btn, spinner, textSpan) {
        btn.prop('disabled', false);
        spinner.addClass('d-none');
        textSpan.text('Generate');
    }

    async function rotateAndDownloadPDF(url, btn, spinner, textSpan) {
        try {
            const response = await fetch(url);
            const pdfBytes = await response.arrayBuffer();

            const pdfDoc = await PDFLib.PDFDocument.load(pdfBytes);
            const pages = pdfDoc.getPages();

            pages.forEach(page => {
                const rotation = page.getRotation().angle;
                page.setRotation(PDFLib.degrees((rotation + 90) % 360));
            });

            const rotatedBytes = await pdfDoc.save();
            const blob = new Blob([rotatedBytes], { type: 'application/pdf' });

            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'rotated-barcode.pdf';
            link.click();
        } catch (err) {
            console.error('PDF rotation failed:', err);
            alert('PDF rotation failed');
        } finally {
            resetButton(btn, spinner, textSpan);
        }
    }


   $('#zohoSyncBtn').on('click', function () {
        if (!confirm('Are you sure you want to sync all unsynced products to Zoho?')) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Syncing...');

        $.ajax({
            url: "{{ route('zoho.items.push.bulk') }}",
            method: "GET",
            success: function (response) {
                console.log(response);

                if (response.message) {
                    AIZ.plugins.notify('success', response.message);
                }

                let successCount = 0;
                let failedCount = 0;
                (response.results || []).forEach(r => {
                    r.status === 'success' ? successCount++ : failedCount++;
                });

                // Show custom modal with counts
                $('#syncSuccessCount').text(successCount);
                $('#syncFailedCount').text(failedCount);
                $('#syncResultModal').modal('show');
            },
            error: function (xhr) {
                console.error(xhr);
                AIZ.plugins.notify('danger', '❌ Zoho sync failed. Please try again.');
            },
            complete: function () {
                btn.prop('disabled', false).html('<span><i class="las la-sync-alt"></i> Zoho Sync</span>');
            }
        });
    });

    // 🔁 Page reload on modal "OK - ZohoSync Model Part"
    $('#reloadAfterSync').on('click', function () {
        location.reload();
    });


});
</script>
@endsection
