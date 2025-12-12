@extends('backend.layouts.app')
@section('content')
  
  @php
    CoreComponentRepository::instantiateShopRepository();
    CoreComponentRepository::initializeCache();
  @endphp

  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
      <div class="col-auto">
        <h1 class="h3">{{ translate('All products closing stock') }}</h1>
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
    <?php /* <div class="card-header row gutters-5">
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
    </div> */ ?>
    <div class="card-header row gutters-5">
      <div class="col-md-2">
        <a href="{{ route('products.closingStockExport') }}" class="btn btn-circle btn-info">
          <span>{{ translate('Export') }}</span>
        </a>
      </div>
    </div>
    <form class="" id="sort_products_closing_stock" action="" method="GET">
      <div class="card-header row gutters-5">
        <div class="col">
          <h5 class="mb-md-0 h6">{{ translate('All products closing stock') }}</h5>
        </div>
        <div class="col-lg-3 ml-auto">
          <div class="form-group mb-0"> 
            <input type="text" name="search_text" id="search_text" value="{{ $search_text }}" placeholder="Part Number Or Item Name" class="form-control">
          </div>
        </div>
        <?php /*<div class="col-lg-3 ml-auto">Select Godown:
            <select class="form-control aiz-selectpicker" name="godown" id="godown">
                <option value="">--- Select Godown ---</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->name }}">{{ $warehouse->name }}</option>
                @endforeach                    
            </select>
        </div>
        <div class="col-lg-2">From Date: 
          <div class="form-group mb-0">
              <input type="date" name="from_date" id="from_date" value="{{ $from_date }}" class="form-control">
          </div>
        </div>        
        <div class="col-lg-2">To Date:
          <div class="form-group mb-0"> 
            <input type="date" name="to_date" id="to_date" value="{{ $to_date }}" class="form-control">
          </div>
        </div>        
        
        <div class="dropdown mb-2 mb-md-0">
          <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
            {{ translate('Bulk Action') }}
          </button>
          <div class="dropdown-menu dropdown-menu-right">
            <a class="dropdown-item" href="#" onclick="bulk_delete()"> {{ translate('Delete selection') }}</a>
          </div>
        </div>

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
        </div> */ ?>
        <div class="col-auto">
            <div class="form-group mb-0">
                <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
            </div>
        </div>
      </div>

      <div class="card-body">
        <table class="table mb-0">
          <thead>
            <tr>
              <th data-breakpoints="lg">#</th>
              <!-- <th data-breakpoints="sm">Branch Name</th>               -->
              <th data-breakpoints="sm">Part No</th>
              <th data-breakpoints="sm">Brand</th>
              <th data-breakpoints="md">Item Name</th>
              <th data-breakpoints="md">MRP</th>
              <th data-breakpoints="lg">Category Group</th>
              <th data-breakpoints="lg">Category</th>
              <th data-breakpoints="lg">Kolkata</th>
              <th data-breakpoints="lg">Delhi</th>
              <th data-breakpoints="lg">Mumbai</th>
              <th data-breakpoints="lg">Total Stock</th>
              <th data-breakpoints="lg">Action</th>
              <!-- <th data-breakpoints="lg">Purchase Qty</th>
              <th data-breakpoints="lg">Sale Qty</th>
              <th data-breakpoints="lg">Closing Qty</th> -->
              <?php /* <th data-breakpoints="sm" class="text-right">{{ translate('Options') }}</th> */ ?>
            </tr>
          </thead>
          <tbody>
            @foreach ($products as $key => $product)
              @php
                  $collapseId = 'collapse_' . $key;
              @endphp
              <tr>
                <td>{{ $key + 1 + ($products->currentPage() - 1) * $products->perPage() }}</td>
                <!-- <td><strong> {{ $product->godown }}</strong></td> -->
                <td><strong> {{ $product->part_no }}</strong></td>
                <td>{{ $product->productDetails->brand->name }}</td>
                <td>{{ $product->name }}</td>
                <td>
                @if($isManager41) 
                    ₹{{ number_format($product->productDetails->mrp_41_price, 2) }}
                @else
                    ₹{{ number_format($product->productDetails->mrp, 2) }}
                @endif
              </td>
                <td>{{ $product->productDetails->categoryGroup->name }}</td>
                <td>{{ $product->productDetails->category->name }}</td>
                @php
                  $totalStock = 0;
                @endphp
                @foreach($product->warehouse as $wStockKey => $wStockValue)
                  <td>{{ ($wStockValue['opening_stock'] + $wStockValue['purchase_qty']) - ($wStockValue['debit_note_qty'] + $wStockValue['sale_qty']) }}</td>
                  @php                  
                    $totalStock += ($wStockValue['opening_stock'] + $wStockValue['purchase_qty']) - ($wStockValue['debit_note_qty'] + $wStockValue['sale_qty']);
                  @endphp
                @endforeach                
                <td>{{ $totalStock }}</td>
                <td>
                  <a href="javascript:void(0);" class="btn btn-icon btn-sm btn-circle btn-soft-success toggle-row" data-target="#{{ $collapseId }}" style="background-color: #99ff00;"> <i class="las la-chevron-down"></i></a>
                </td>
              </tr>
              <tr>
                <td colspan="10" class="p-0">
                  <div id="{{ $collapseId }}" class="collapse bg-light">
                      <table class="table table-sm table-bordered mb-0">
                          <thead class="text-white" style="background-color: #174ba9;">
                              <tr>
                                  <th>Warehouse Name</th>
                                  <th>Opening Stock</th>
                                  <th>Purchase</th>
                                  <th>Sale</th>
                                  <th>Closing Stock</th>
                              </tr>
                          </thead>
                          <tbody>
                              @foreach($product->warehouse as $wStockKey => $wStockValue)
                                <tr>
                                  <td>
                                    <a href="javascript:void(0);" class="warehouse-name" data-warehouse-id="{{ $wStockValue['warehouse_id'] }}" data-part-number="{{ $product->part_no }}" data-warehouse-name="{{ $wStockValue['warehouse_name'] }}" >
                                      {{ $wStockValue['warehouse_name'] }}
                                    </a>
                                  </td>
                                  <td>{{ $wStockValue['opening_stock'] }}</td>
                                  <td>{{ $wStockValue['purchase_qty'] }}</td>
                                  <td>{{ ($wStockValue['debit_note_qty'] + $wStockValue['sale_qty']) }}</td>
                                  <td>{{ ($wStockValue['opening_stock'] + $wStockValue['purchase_qty']) - ($wStockValue['debit_note_qty'] + $wStockValue['sale_qty']) }}</td>
                                </tr>
                              @endforeach                              
                          </tbody>
                      </table>
                  </div>
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

  <!-- Stock Transaction Modal -->
  <div class="modal fade" id="stockTransactionModal" tabindex="-1" aria-labelledby="stockTransactionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><span id="partNumberSpan"></span> Stock Transaction History of <span id="wareHouseSpan"></span> warehouse</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
            <div class="card-header row gutters-5">
              <div class="col-md-3" id="exportDetailsButton"></div>
            </div> 
            <table class="table table-bordered table-striped">
            <thead class="thead-dark">
              <tr>
                <th>Date</th>
                <th>Voucher Type</th>
                <th>Voucher Number</th>
                <th>Party Name</th>
                <th>Dr Qty</th>
                <th>Cr Qty</th>
                <th>Running Qty</th>
              </tr>
            </thead>
            <tbody id="stockTransactionTableBody">
              <!-- Content will be inserted via JS -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

@endsection

@section('modal')
  @include('modals.delete_modal')
@endsection


@section('script')
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
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

    $(document).on("click", ".toggle-row", function() {
        const targetId = this.getAttribute('data-target');
        const target = document.querySelector(targetId);
        const icon = this.querySelector('i');
        if (target.classList.contains('show')) {
            target.classList.remove('show');
            icon.classList.remove('la-chevron-up');
            icon.classList.add('la-chevron-down');
        } else {
            target.classList.add('show');
            icon.classList.remove('la-chevron-down');
            icon.classList.add('la-chevron-up');
        }
    });

    $(document).on("click", ".warehouse-name", function () {
        const warehouseId = $(this).data('warehouse-id');
        const warehouseName = $(this).data('warehouse-name');
        const part_no = $(this).data('part-number');
        document.getElementById('wareHouseSpan').innerHTML = warehouseName;
        document.getElementById('partNumberSpan').innerHTML = part_no;
        $.ajax({
            url: "{{ route('products.getStockTransaction') }}",
            type: "GET",
            data: {
                warehouseId: warehouseId,
                warehouseName: warehouseName,
                part_no: part_no
            },
            success: function (response) {
                let data = [];

                // Corrected handling
                if (Array.isArray(response)) {
                    data = response;
                } else if (response.data && Array.isArray(response.data)) {
                    data = response.data;
                } else if (typeof response === 'object') {
                    data = Object.values(response);
                }

                let tableBody = '';
                let closingStock = 0;

                if (data.length > 0) {
                    data.forEach(item => {
                        const date = new Date(item.created_at).toLocaleDateString('en-GB');
                        const type = (item.type ?? '-').replace('_', ' ').toUpperCase();
                        const voucher = item.voucher_number ?? '-';
                        const party_name = item.party_name ?? '-';
                        const qty = parseFloat(item.quantity ?? 0);
                        if (type === 'OPENING STOCK') {
                            closingStock = qty;
                        } else if (type === 'PURCHASE' || type === 'CREDIT NOTE') {
                            closingStock += qty;
                        } else {
                            closingStock -= qty;
                        }
                        tableBody += `
                            <tr>
                                <td>${date}</td>
                                <td>${type}</td>
                                <td>${voucher}</td>
                                <td>${party_name}</td>`;
                        if (type === 'OPENING STOCK') {
                          tableBody += `<td></td>
                                        <td>${qty}</td>`;
                        } else if (type === 'PURCHASE') {
                            tableBody += `<td></td>
                                        <td>${qty}</td>`;
                        } else if (type === 'CREDIT NOTE') {
                            tableBody += `<td></td>
                                        <td>${qty}</td>`;
                        } else {
                            tableBody += `<td>${qty}</td>
                                        <td></td>`;
                        }
                                
                        tableBody += `
                                <td>${closingStock}</td>
                              </tr>`;

                        
                    });
                    if(closingStock >= 0){
                      tableBody += `
                          <tr>
                              <td>-</td>
                              <td><strong>CLOSING STOCK</strong></td>
                              <td>-</td>
                              <td><strong></strong></td>
                              <td><strong>${closingStock}</strong></td>
                              <td><strong></strong></td>
                              <td><strong></strong></td>
                          </tr>`;
                    }else{
                        tableBody += `
                            <tr>
                                <td>-</td>
                                <td><strong>CLOSING STOCK</strong></td>
                                <td>-</td>
                                <td><strong></strong></td>
                                <td><strong></strong></td>                                
                                <td><strong>${closingStock}</strong></td>
                                <td><strong></strong></td>
                            </tr>`;
                    }
                } else {
                    tableBody = `<tr><td colspan="6" class="text-center">No data available</td></tr>`;
                }
                let exportDetailsButton = '';
                exportDetailsButton = `<a href="javascript:void(0);" class="btn btn-primary export-details" data-warehouse-id="${warehouseId}" data-part-number="${part_no}" data-warehouse-name="${warehouseName}" >
                  <span>Export Stock</span>
                </a>`;
                
                $('#exportDetailsButton').html(exportDetailsButton);
                $('#stockTransactionTableBody').html(tableBody);
                $('#stockTransactionModal').modal('show');
            },
            error: function (xhr) {
                alert('Failed to fetch stock data.');
                console.log(xhr.responseText);
            }
        });
    });
    
    // $(document).on("click", ".export-details", function () {
        // const warehouseId = $(this).data('warehouse-id');
        // const warehouseName = $(this).data('warehouse-name');
        // const part_no = $(this).data('part-number');
        // $.ajax({
        //     url: "{{ route('products.closingStockExportDetails') }}",
        //     type: "GET",
        //     data: {
        //         warehouseId: warehouseId,
        //         warehouseName: warehouseName,
        //         part_no: part_no
        //     },
        //     success: function (response) {
                
        //     },
        //     error: function (xhr) {
        //         alert('Failed to fetch stock data.');
        //         console.log(xhr.responseText);
        //     }
        // });
    // });

    $(document).on("click", ".export-details", function () {
        const warehouseId = $(this).data('warehouse-id');
        const warehouseName = $(this).data('warehouse-name');
        const part_no = $(this).data('part-number');

        // Build query string
        const url = `{{ route('products.closingStockExportDetails') }}?warehouseId=${warehouseId}&warehouseName=${encodeURIComponent(warehouseName)}&part_no=${part_no}`;

        // Trigger download by navigating to the URL
        window.location.href = url;
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

  </script>
@endsection
