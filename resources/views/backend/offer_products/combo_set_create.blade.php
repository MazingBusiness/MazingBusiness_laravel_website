@extends('backend.layouts.app')

@section('content')

  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{ translate('Add New Offer Combination Product') }}</h5>
  </div>

  <div class="">
    @if (session('success'))
      <div class="alert alert-success">
        {{ session('success') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form action="{{ route('store.offer.combination') }}" method="POST">
      @csrf
      <div class="row gutters-5 justify-content-center">
        <div class="col-lg-10">
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0 h6">{{ translate('Offer Combination Product Information') }}</h5>
            </div>
            <div class="card-body">
              
              <!-- Offer Selection -->
              <div class="form-group row">
                <label class="col-md-3 col-from-label">{{ translate('Select Offer') }}</label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" name="offer_id" id="offer_id" data-live-search="true" required>
                    <option value="">{{ translate('Select Offer') }}</option>
                    @foreach ($offers as $offer)
                      <option value="{{ $offer->offer_id }}">{{ $offer->offer_name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>

              <!-- Free Product Selection (Populated based on selected offer) -->
              <div class="form-group row" id="free_product_section" style="display: none;">
                <label class="col-md-3 col-from-label">{{ translate('Free Product') }}</label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" id="free_product_select" name="free_product" data-live-search="true">
                    <!-- Options will be populated by AJAX -->
                  </select>
                </div>
              </div>

              <!-- Category Selection -->
              <div class="form-group row">
                <label class="col-md-3 col-from-label">{{ translate('Select Category') }}</label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" name="category_id" id="category_id" data-live-search="true" required>
                    <option value="">{{ translate('Select Category') }}</option>
                    @foreach ($categories as $category)
                      <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>

              <!-- Dynamic Product Selection and Quantity Section -->
              <div id="product_container" style="display: none;">
                <div class="form-group row">
                  <label class="col-md-3 col-from-label">{{ translate('Select Products and Quantity') }}</label>
                  <div class="col-md-8" id="product_rows">
                    <!-- Product rows will be added dynamically with JavaScript -->
                  </div>
                </div>
                <div class="form-group row">
                  <div class="col-md-8 offset-md-3">
                    <button type="button" class="btn btn-primary btn-sm" id="add_product_row" style="padding: 2px 5px; font-size: 12px;">{{ translate('Add Product') }}</button>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- Save Button -->
        <div class="col-12">
          <div class="btn-toolbar float-right mb-3" role="toolbar">
            <button type="submit" class="btn btn-success">{{ translate('Save Combo Set') }}</button>
          </div>
        </div>
      </div>
    </form>
  </div>

@endsection

@section('script')
<script type="text/javascript">
  $(document).ready(function() {
    // Load free products based on selected offer
    $('#offer_id').on('change', function() {
      var offerId = $(this).val();

      if (offerId) {
        $.ajax({
          url: '{{ route("offer-combination-products.get-free-products") }}',
          method: 'POST',
          data: {
            _token: '{{ csrf_token() }}',
            offer_id: offerId
          },
          success: function(response) {
            console.log("Free product response:", response);
            if (Array.isArray(response) && response.length > 0) {
              $('#free_product_section').show();
              $('#free_product_select').empty();

              // Add "Select Free Gift" as the first option
              $('#free_product_select').append('<option value="">{{ translate("Select Free Gift") }}</option>');

              $.each(response, function(index, product) {
                $('#free_product_select').append('<option value="' + product.id + '">' + product.name + ' (Qty: ' + product.free_product_qty + ')</option>');
              });

              $('#free_product_select').selectpicker('refresh');
            } else {
              $('#free_product_section').hide();
            }
          },
          error: function(xhr, status, error) {
            console.error("Error fetching free products:", status, error);
            $('#free_product_section').hide();
          }
        });
      } else {
        $('#free_product_section').hide();
      }
    });

    // Show Product Selection and Quantity section after selecting a category
    $('#category_id').on('change', function() {
      var categoryId = $(this).val();

      if (categoryId) {
        // Show the product container
        $('#product_container').show();

        // Fetch products for the selected category
        $.ajax({
          url: '{{ route("offers.get_products_by_category") }}',
          method: 'POST',
          data: {
            _token: '{{ csrf_token() }}',
            category_id: categoryId
          },
          dataType: 'json',
          success: function(response) {
            console.log("Category product response:", response);
            if (Array.isArray(response) && response.length > 0) {
              addProductRow(response); // Add new rows for the selected category products
            } else {
              // If no products, still show "Nothing selected" in the dropdown
              addProductRow([]); // Empty array to show "Nothing selected"
              console.warn("No products found for this category.");
            }
          },
          error: function(xhr, status, error) {
            console.error("Error fetching category products:", status, error);
          }
        });
      } else {
        $('#product_container').hide();
      }
    });

    // Function to add a new product row
    function addProductRow(products) {
      var productOptions = '<option value="">{{ translate("Nothing selected") }}</option>'; // Default option
      $.each(products, function(index, product) {
        productOptions += `<option value="${product.part_no}">${product.name} (Part No: ${product.part_no})</option>`;
      });

      var newRow = `<div class="product_row mb-2" style="display: flex; align-items: center;">
                      <select class="form-control aiz-selectpicker product_select" name="product_id[]" data-live-search="true" style="width: 60%; margin-right: 10px;" required>
                        ${productOptions}
                      </select>
                      <div style="position: relative; width: auto; flex: 1;">
                        <input type="number" class="form-control product_qty" name="product_qty[]" placeholder="{{ translate('Quantity') }}" style="width: auto; box-sizing: border-box; min-width: 50px;" required min="1" oninput="autoResize(this)">
                      </div>
                      <button type="button" class="btn btn-danger btn-sm remove_product_row" style="flex-shrink: 0; margin-left: 10px; padding: 2px 5px; font-size: 12px;">{{ translate('Remove') }}</button>
                    </div>`;
      $('#product_rows').append(newRow);
      $('.aiz-selectpicker').selectpicker('refresh'); // Reinitialize selectpicker for search functionality
    }

    // Add a new product row on "Add Product" button click
    $('#add_product_row').on('click', function() {
      var categoryId = $('#category_id').val();
      if (categoryId) {
        $.ajax({
          url: '{{ route("offers.get_products_by_category") }}',
          method: 'POST',
          data: {
            _token: '{{ csrf_token() }}',
            category_id: categoryId
          },
          dataType: 'json',
          success: function(response) {
            if (Array.isArray(response) && response.length > 0) {
              addProductRow(response);
            } else {
              addProductRow([]); // Show "Nothing selected" if no products found
            }
          },
          error: function(xhr, status, error) {
            console.error("Error fetching category products:", status, error);
          }
        });
      }
    });

    // Remove product row on button click
    $(document).on('click', '.remove_product_row', function() {
      $(this).closest('.product_row').remove();
    });
  });

  // JavaScript function to automatically resize the quantity input based on content
  function autoResize(input) {
    input.style.width = ((input.value.length + 1) * 10) + 'px';
  }
</script>
@endsection
