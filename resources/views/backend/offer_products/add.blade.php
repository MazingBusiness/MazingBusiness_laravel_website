@extends('backend.layouts.app')

@section('content')

  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{ translate('Add New Offer Product') }}</h5>
  </div>

  <div class="">

    <!-- Display error messages, if any -->
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form class="form form-horizontal mar-top" action="{{ route('offer-products.save') }}" method="POST" enctype="multipart/form-data" id="offer_product_form">
      @csrf
      <div class="row gutters-5">

        <!-- First Column: Offer Information -->
        <div class="col-lg-6">
          <div class="card mb-4">
            <div class="card-header bg-light">
              <h6 class="mb-0">{{ translate('Offer Information') }}</h6>
            </div>
            <div class="card-body">

              <!-- Offer Name -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Name') }} <span class="text-danger">*</span></label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="offer_name" placeholder="{{ translate('Offer Name') }}" required>
                </div>
              </div>

              <!-- Offer Validity -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Validity') }} <span class="text-danger">*</span></label>
                <div class="col-md-8">
                  <input type="text" class="form-control aiz-date-range" name="offer_validity" placeholder="{{ translate('Select Date Range') }}" data-time-picker="true" data-format="DD-MM-Y HH:mm:ss" data-separator=" to " autocomplete="off" required>
                </div>
              </div>

              <!-- Offer Description -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Description') }}</label>
                <div class="col-md-8">
                  <textarea class="aiz-text-editor form-control" name="offer_description"></textarea>
                </div>
              </div>

              <!-- Banner Upload -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Banner') }}</label>
                <div class="col-md-8">
                  <div class="input-group" data-toggle="aizuploader" data-type="image">
                    <div class="input-group-prepend">
                      <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
                    </div>
                    <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                    <input type="hidden" name="offer_banner" class="selected-files">
                  </div>
                  <div class="file-preview box sm"></div>
                </div>
              </div>

              <!-- Offer Type -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Type') }} <span class="text-danger">*</span></label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" name="offer_type" id="offer_type" required onchange="handleOfferTypeChange()">
                    <option value="">{{ translate('Select  Type') }}</option>
                    <option value="1">{{ translate('Item Wise') }}</option>
                    <option value="2">{{ translate('Total') }}</option>
                    <option value="3">{{ translate('Complementry') }}</option>
                  </select>
                </div>
              </div>

              <!-- Offer Value and Discount Percent - Initially Hidden -->
             <div id="offer_value_section" style="display: none;">
    <div class="form-group row">
        <label class="col-md-4 col-form-label">{{ translate(' Value') }} <span class="text-danger">*</span></label>
        <div class="col-md-8">
            <input type="number" lang="en" min="0" step="0.01" placeholder="{{ translate('Offer Value') }}" 
                   name="offer_value" class="form-control" 
                   oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1')">
        </div>
    </div>

    <div class="form-group row">
        <label id="discount_label" class="col-md-4 col-form-label">{{ translate('Discount Percent') }} <span class="text-danger">*</span></label>
        <div class="col-md-4">
            <input type="number" lang="en" 
                   placeholder="{{ translate('Enter value') }}" 
                   name="discount_percent" class="form-control" id="discount_percent_input"
                   >
            <small  id="validation_message"  class="text-muted">{{ translate('Enter a percentage value between 0 and 100.') }}</small>
        </div>

        <div class="col-md-4">
            <select  class="form-control aiz-selectpicker" name="value_type">
                <option value="">{{ translate('Nothing Selected') }}</option>
                <option value="amount">{{ translate('Flat Value') }}</option>
                <option value="percent">{{ translate('Percentage') }}</option>
            </select>
        </div>
    </div>
</div>



              <!-- Uses Per User -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Uses Per User') }}</label>
                <div class="col-md-8">
                  <input type="number" lang="en" min="0" step="1" placeholder="{{ translate('Uses Per User') }}" name="uses_per_user" class="form-control">
                </div>
              </div>

              <!-- Max Uses -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Max Uses') }}</label>
                <div class="col-md-8">
                  <input type="number" lang="en" min="0" step="1" placeholder="{{ translate('Max Uses') }}" name="max_uses" class="form-control">
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- Second Column: Product Information -->
        <div class="col-lg-6">
          <div class="card mb-4">
            <div class="card-header bg-light">
              <h6 class="mb-0">{{ translate('Product Section') }}</h6>
            </div>
            <div class="card-body">

              <!-- State Selection -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' State') }}</label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" name="state_id" data-live-search="true">
                    <option value="">{{ translate('Select State') }}</option>
                    @foreach ($states as $state)
                      <option value="{{ $state->id }}">{{ $state->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>

              <!-- Manager Selection -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Manager') }}</label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" name="manager_id" data-live-search="true">
                    <option value="">{{ translate('Select Manager') }}</option>
                    @foreach ($managers as $manager)
                      <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>

              <!-- Product Listing by Category -->
             <!-- Category Multi-Select Dropdown -->
            <div class="form-group row">
              <label class="col-md-4 col-form-label">{{ translate('Category') }}</label>
              <div class="col-md-8">
                <select class="form-control aiz-selectpicker" name="category_ids[]" multiple data-live-search="true" id="category-select">
                  @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->getTranslation('name') }}</option>
                  @endforeach
                </select>
              </div>
            </div>

         

            <div class="form-group row">
    <label class="col-md-4 col-form-label">{{ translate('Brand') }}</label>
    <div class="col-md-8">
        <select class="form-control aiz-selectpicker" name="brand_ids[]" multiple data-live-search="true" id="brand-select">
            <option value="">{{ translate('Select Brand') }}</option>
            @foreach($brands as $brand)
                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
            @endforeach
        </select>
    </div>
</div>

              

           <!-- Product Multi-Select Dropdown -->
            <div class="form-group row">
              <label class="col-md-4 col-form-label">{{ translate('Product') }}</label>
              <div class="col-md-8">
                <select class="form-control aiz-selectpicker" name="product_ids[]" multiple data-live-search="true" id="product-select">
                  <option value="">{{ translate('Select Product') }}</option>
                  @foreach ($products as $product)
                    <option value="{{ $product->part_no }}">{{ $product->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

              

              <div id="complementary-items-section" class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate('Complementary Items') }}</label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" name="complementary_items[]" multiple data-live-search="true" id="complementary-items-select">
                    <option value="">{{ translate('Select Complementary Items') }}</option>
                    @foreach ($products as $product)
                      <option value="{{ $product->part_no }}">{{ $product->name }}</option>
                    @endforeach
                  </select>
                  <small class="text-muted">{{ translate('Select complementary items for combined offers.') }}</small>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- Featured Status and Save Button -->
        <div class="col-lg-12">
          <div class="card mb-4">
            <div class="card-body">
              <!-- Featured Status -->
              <div class="form-group row">
                <label class="col-md-3 col-form-label">{{ translate('Status') }}</label>
                <div class="col-md-9">
                  <label class="aiz-switch aiz-switch-success mb-0">
                    <input type="checkbox" name="featured" value="1" checke>
                    <span></span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Save Button -->
        <div class="col-12 text-right">
          <button type="submit" class="btn btn-success">{{ translate('Save Offer Product') }}</button>
        </div>
      </div>
    </form>
  </div>

@endsection

@section('script')
  <script type="text/javascript">
   function handleOfferTypeChange() {
    const offerType = document.getElementById("offer_type").value;
    const offerValueSection = document.getElementById("offer_value_section");
    const complementaryItemsSection = document.getElementById("complementary-items-section");

    // Reference to value and discount fields
    const offerValueInput = document.querySelector("input[name='offer_value']");
    const valueTypeSelect = document.querySelector("select[name='value_type']");
    const discountPercentInput = document.querySelector("input[name='discount_percent']");

    if (offerType === "3") { // Complementary
        // Hide the value section and clear fields
        offerValueSection.style.display = "none";
        offerValueInput.value = "";
        valueTypeSelect.value = "";
        discountPercentInput.value = "";
        $(valueTypeSelect).selectpicker('refresh'); // Refresh for Bootstrap select

        // Show the complementary items section
        complementaryItemsSection.style.display = "block";
    } else if (offerType === "2") { // Total
        // Show both the value section and complementary items section
        offerValueSection.style.display = "block";
        complementaryItemsSection.style.display = "block";
    } else {
        // Hide both sections and clear fields
        offerValueSection.style.display = "none";
        offerValueInput.value = "";
        valueTypeSelect.value = "";
        discountPercentInput.value = "";
        $(valueTypeSelect).selectpicker('refresh');

        complementaryItemsSection.style.display = "none";
    }
}

document.addEventListener("DOMContentLoaded", function() {
    handleOfferTypeChange();
});

// Ensure proper UI update on dropdown change
document.getElementById("offer_type").addEventListener("change", handleOfferTypeChange);


   // Change field label, placeholder, and validation message dynamically
    // Change label, placeholder, and validation message dynamically
  $('select[name="value_type"]').on('change', function () {
    const selectedType = $(this).val();
    const discountInput = $('#discount_percent_input');
    const discountLabel = $('#discount_label');
    const validationMessage = $('#validation_message');

    // Reset the input value whenever selection changes
    discountInput.val('');

    if (selectedType === 'amount') {
        // Update label and validation message for Flat Value
        discountLabel.html('{{ translate("Discount Amount") }} <span class="text-danger">*</span>');
        validationMessage.text('{{ translate("Enter a valid amount.") }}');

        // Remove percentage validation
        discountInput.off('input'); // Remove validation logic
    } else if (selectedType === 'percent') {
        // Update label and validation message for Percentage
        discountLabel.html('{{ translate("Discount Percent") }} <span class="text-danger">*</span>');
        discountInput.attr('placeholder', '{{ translate("Enter value") }}');
        validationMessage.text('{{ translate("Enter a percentage value between 0 and 100.") }}');

        // Add percentage validation
        discountInput.off('input').on('input', function () {
            let value = parseFloat($(this).val());
            if (value > 100) {
                alert('{{ translate("Value cannot be greater than 100.") }}');
                $(this).val(100); // Reset value to 100 if it exceeds
            } else if (value < 0) {
                alert('{{ translate("Value cannot be less than 0.") }}');
                $(this).val(0); // Ensure the value is not less than 0
            }
        });
    }
});

// Trigger change event on page load to set the initial state
$('select[name="value_type"]').trigger('change');


    // When Category is selected
 
  $('#category-select').on('change', function () {
      var categoryIds = $(this).val(); // Multi-select returns an array
      var brandIds = $('#brand-select').val(); // Fetch selected brands

      $('#brand-select').html('<option value="">{{ translate("Select Brand") }}</option>');
      $('#product-select').html('<option value="">{{ translate("Select Product") }}</option>');

      if (categoryIds && categoryIds.length > 0) {
          // Fetch Brands filtered by Category
          $.ajax({
              url: '{{ url("/get-brands-by-category") }}/' + categoryIds.join(','),
              method: 'GET',
              success: function (response) {
                  $.each(response, function (index, brand) {
                      $('#brand-select').append('<option value="' + brand.id + '">' + brand.name + '</option>');
                  });
                  $('#brand-select').selectpicker('refresh');
              }
          });

          // Fetch Products filtered by Category (and Brand if selected)
          $.ajax({
              url: '{{ route("get-products-by-category-and-brand") }}',
              method: 'POST',
              data: {
                  _token: '{{ csrf_token() }}',
                  category_ids: categoryIds,
                  brand_ids: brandIds || [] // Empty or selected brands
              },
              success: function (response) {
                  $.each(response, function (index, product) {
                      $('#product-select').append('<option value="' + product.part_no + '">' + product.name + '</option>');
                  });
                  $('#product-select').selectpicker('refresh');
              }
          });
        } else {
            // If no category is selected, fetch all products
            $.ajax({
                url: '{{ route("get-products-by-category-and-brand") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    category_ids: [],
                    brand_ids: brandIds || []
                },
                success: function (response) {
                    $.each(response, function (index, product) {
                        $('#product-select').append('<option value="' + product.part_no + '">' + product.name + '</option>');
                    });
                    $('#product-select').selectpicker('refresh');
                }
            });
        }
    });

    // When Brand is selected
    $('#brand-select').on('change', function () {
        var brandIds = $(this).val(); // Multi-select returns an array
        var categoryIds = $('#category-select').val(); // Fetch selected categories

        $('#product-select').html('<option value="">{{ translate("Select Product") }}</option>');

        if (brandIds && brandIds.length > 0) {
            // Fetch Products filtered by Brand (and Category if selected)
            $.ajax({
                url: '{{ route("get-products-by-category-and-brand") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    category_ids: categoryIds || [], // Empty or selected categories
                    brand_ids: brandIds
                },
                success: function (response) {
                    $.each(response, function (index, product) {
                        $('#product-select').append('<option value="' + product.part_no + '">' + product.name + '</option>');
                    });
                    $('#product-select').selectpicker('refresh');
                }
            });
        } else {
            // If no brand is selected, fetch all products
            $.ajax({
                url: '{{ route("get-products-by-category-and-brand") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    category_ids: categoryIds || [],
                    brand_ids: []
                },
                success: function (response) {
                    $.each(response, function (index, product) {
                        $('#product-select').append('<option value="' + product.part_no + '">' + product.name + '</option>');
                    });
                    $('#product-select').selectpicker('refresh');
                }
            });
        }
    });
  </script>
@endsection
