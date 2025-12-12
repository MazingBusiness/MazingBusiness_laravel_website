@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{ translate('Edit Offer') }}</h5>
  </div>

  <div class="">
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form class="form form-horizontal mar-top" action="{{ route('offer-products.update', $offer->offer_id) }}" method="POST" enctype="multipart/form-data" id="offer_product_form">
      @csrf
      @method('POST')

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
                  <input type="text" class="form-control" name="offer_name" value="{{ $offer->offer_name }}" placeholder="{{ translate('Offer Name') }}" required>
                </div>
              </div>

              <!-- Offer Validity -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Validity') }} <span class="text-danger">*</span></label>
                <div class="col-md-8">
                  <input type="text" class="form-control aiz-date-range" name="offer_validity" value="{{ date('d-m-Y H:i:s', strtotime($offer->offer_validity_start)) . ' to ' . date('d-m-Y H:i:s', strtotime($offer->offer_validity_end)) }}" placeholder="{{ translate('Select Date Range') }}" data-time-picker="true" data-format="DD-MM-Y HH:mm:ss" data-separator=" to " autocomplete="off" required>
                </div>
              </div>

              <!-- Offer Description -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Description') }}</label>
                <div class="col-md-8">
                  <textarea class="aiz-text-editor form-control" name="offer_description">{{ $offer->offer_description }}</textarea>
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
                    <input type="hidden" name="offer_banner" value="{{ $offer->offer_banner }}" class="selected-files">
                  </div>
                  <div class="file-preview box sm"></div>
                </div>
              </div>

              <!-- Offer Type -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Type') }} <span class="text-danger">*</span></label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" name="offer_type" id="offer_type" onchange="handleOfferTypeChange()" required>
                    <option value="">{{ translate('Select Offer Type') }}</option>
                    <option value="1" {{ $offer->offer_type == '1' ? 'selected' : '' }}>{{ translate('Item Wise') }}</option>
                    <option value="2" {{ $offer->offer_type == '2' ? 'selected' : '' }}>{{ translate('Total') }}</option>
                    <option value="3" {{ $offer->offer_type == '3' ? 'selected' : '' }}>{{ translate('Complementry') }}</option>
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
                   value="{{ $offer->offer_value }}" 
                   oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1')">
        </div>
    </div>

    <div class="form-group row">
        <label id="discount_label" class="col-md-4 col-form-label">{{ translate('Discount Percent') }} <span class="text-danger">*</span></label>
        <div class="col-md-4">
            <input type="number" lang="en" 
                   placeholder="{{ translate('Enter value') }}" 
                   name="discount_percent" class="form-control" 
                   id="discount_percent_input" 
                   value="{{ $offer->discount_percent }}" 
                   >
            <small id="validation_message" class="text-muted"></small>
        </div>

        <div class="col-md-4">
            <select class="form-control aiz-selectpicker" name="value_type">
                <option value="">{{ translate('Nothing Selected') }}</option>
                <option value="amount" {{ $offer->value_type == 'amount' ? 'selected' : '' }}>{{ translate('Flat Value') }}</option>
                <option value="percent" {{ $offer->value_type == 'percent' ? 'selected' : '' }}>{{ translate('Percentage') }}</option>
            </select>
        </div>
    </div>
</div>



               <!-- Uses Per User -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Uses Per User') }}</label>
                <div class="col-md-8">
                  <input type="number" lang="en" min="0" step="1" placeholder="{{ translate('Uses Per User') }}" name="uses_per_user" class="form-control" value="{{ $offer->per_user }}">
                </div>
              </div>

              <!-- Max Uses -->
              <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Max Uses') }}</label>
                <div class="col-md-8">
                  <input type="number" lang="en" min="0" step="1" placeholder="{{ translate('Max Uses') }}" name="max_uses" class="form-control"  value="{{ $offer->max_uses }}">
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
                      <option value="{{ $state->id }}" {{ $offer->state_id == $state->id ? 'selected' : '' }}>{{ $state->name }}</option>
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
                      <option value="{{ $manager->id }}" {{ $offer->manager_id == $manager->id ? 'selected' : '' }}>{{ $manager->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>

              <!-- Product Listing by Category -->
             <!--  <div class="form-group row" id="category">
                <label class="col-md-4 col-form-label">{{ translate(' Category') }} <span class="text-danger">*</span></label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" name="category_id[]" multiple data-live-search="true" id="category-select">
                    @foreach ($categories as $category)
                      <option value="{{ $category->id }}" {{ in_array($category->id, json_decode($offer->category_id, true) ?? []) ? 'selected' : '' }}>
                        {{ $category->getTranslation('name') }}
                      </option>
                      @foreach ($category->childrenCategories as $childCategory)
                        @include('categories.child_category', ['child_category' => $childCategory, 'selectedCategories' => json_decode($offer->category_id, true) ?? []])
                      @endforeach
                    @endforeach
                  </select>
                </div>
              </div> -->

              <!-- Brand Selection -->
             <!--  <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate(' Brand') }}</label>
                <div class="col-md-8">
                  <select class="form-control aiz-selectpicker" name="brand_id[]" multiple data-live-search="true" id="brand-select">
                    @foreach ($brands as $brand)
                      <option value="{{ $brand->id }}" {{ in_array($brand->id, json_decode($offer->brand_id, true) ?? []) ? 'selected' : '' }}>
                        {{ $brand->name }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div> -->

              <!-- Product Codes -->
             <!--  <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate('Product Codes') }}</label>
                <div class="col-md-8">
                  <textarea class="form-control" name="product_codes" id="product-codes">{{ implode(',', json_decode($offer->product_code, true) ?? []) }}</textarea>
                  <small class="text-muted">{{ translate('Enter product codes separated by commas.') }}</small>
                </div>
              </div> -->

              <!-- Complementary Items Table -->
             <!--  <div class="form-group row">
                <label class="col-md-4 col-form-label">{{ translate('Complementary Items') }}</label>
                <div class="col-md-8">
                  <table class="table table-bordered" id="complementary-items-table">
                    <thead>
                      <tr>
                        <th>{{ translate('Part No') }}</th>
                        <th>{{ translate('Quantity') }}</th>
                        <th>{{ translate('Actions') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($complementary_items as $index => $item)
                        <tr>
                          <td><input type="text" name="complementary_items[{{ $index }}][part_no]" value="{{ $item->free_product_part_no }}" class="form-control" readonly></td>
                          <td><input type="number" name="complementary_items[{{ $index }}][quantity]" value="{{ $item->free_product_qty }}" class="form-control" min="1"></td>
                          <td><button type="button" class="btn btn-danger btn-sm remove-item">{{ translate('Remove') }}</button></td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                  <button type="button" class="btn btn-primary" id="add-complementary-item">{{ translate('Add Item') }}</button>
                </div>
              </div> -->

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
                    <input type="checkbox" name="featured" value="1" {{ $offer->status == 1 ? 'checked' : '' }}>
                    <span></span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Save Button -->
        <div class="col-12 text-right">
          <button type="submit" class="btn btn-success">{{ translate('Update Offer') }}</button>
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

    // Reference to value and discount fields
    const offerValueInput = document.querySelector("input[name='offer_value']");
    const valueTypeSelect = document.querySelector("select[name='value_type']");
    const discountPercentInput = document.querySelector("input[name='discount_percent']");

    if (offerType === "3") { // Complementary
        // Hide the section and clear fields
        offerValueSection.style.display = "none";
        offerValueInput.value = "";
        valueTypeSelect.value = "";
        discountPercentInput.value = "";
        $(valueTypeSelect).selectpicker('refresh'); // Refresh for Bootstrap select
    } else if (offerType === "2") { // Total
        // Show the section
        offerValueSection.style.display = "block";
    } else {
        // Hide the section and clear fields
        offerValueSection.style.display = "none";
        offerValueInput.value = "";
        valueTypeSelect.value = "";
        discountPercentInput.value = "";
        $(valueTypeSelect).selectpicker('refresh'); // Refresh for Bootstrap select
    }
}

document.addEventListener("DOMContentLoaded", function() {
    handleOfferTypeChange();
});

$('select[name="value_type"]').on('change', function () {
    const selectedType = $(this).val();
    const discountInput = $('#discount_percent_input');
    const discountLabel = $('#discount_label');
    const validationMessage = $('#validation_message');

    // Reset the input value only if it is empty
    if (!discountInput.val()) {
        discountInput.val('');
    }

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

// Avoid resetting the input value on page load if it already has a value
document.addEventListener("DOMContentLoaded", function () {
    if (!$('select[name="value_type"]').val()) {
        $('select[name="value_type"]').trigger('change');
    }
});

  $(document).ready(function() {
    let itemIndex = $('#complementary-items-table tr').length;

    $('#add-complementary-item').click(function() {
      $('#complementary-items-table').append(`
        <tr>
          <td><input type="text" name="complementary_items[${itemIndex}][part_no]" class="form-control" required></td>
          <td><input type="number" name="complementary_items[${itemIndex}][quantity]" class="form-control" min="1" required></td>
          <td><button type="button" class="btn btn-danger btn-sm remove-item">{{ translate('Remove') }}</button></td>
        </tr>
      `);
      itemIndex++;
    });

    $(document).on('click', '.remove-item', function() {
      $(this).closest('tr').remove();
    });
  });
</script>
@endsection
