@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{ translate('Offer Products List') }} Of {{ $offerName ?? '' }}</h5>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <!-- Display Success Message -->
        @if (session('status'))
            <div class="alert alert-success text-center">
                {{ session('status') }}
            </div>
        @endif

        <!-- Tabs for Offer Products and Complementary Items -->
        <ul class="nav nav-tabs custom-tabs" id="offerTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="offer-products-tab" data-toggle="tab" href="#offer-products" role="tab" aria-controls="offer-products" aria-selected="true">{{ translate('Offer Products') }}</a>
            </li>
            @if(isset($offer_type) && $offer_type != 1)
            <li class="nav-item">
                <a class="nav-link" id="complementary-items-tab" data-toggle="tab" href="#complementary-items" role="tab" aria-controls="complementary-items" aria-selected="false">{{ translate('Complementary Items') }}</a>
            </li>
            @endif
        </ul>

        <div class="tab-content custom-tab-content" id="offerTabContent">
            <!-- Offer Products Tab Content -->
            <div style="padding-top:22px;" class="tab-pane fade show active" id="offer-products" role="tabpanel" aria-labelledby="offer-products-tab">
                <button type="button" class="btn btn-outline-primary mb-3" data-toggle="modal" data-target="#bulkUpdateModal">
                    <i class="las la-edit"></i> {{ translate('Bulk Update Offer Percent & Min Quantity') }}
                </button>
                <!-- Add Button to Trigger Modal -->
                <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#selectionModal">
                    <i class="las la-plus"></i> {{ translate('Add Products') }}
                </button>
                   
                <form action="{{ route('offer-products.bulk-update') }}" method="POST">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover small-text-table">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th class="text-center">#</th>
                                    <th class="text-center">{{ translate('Offer ID') }}</th>
                                    <th class="text-center">{{ translate('Part No') }}</th>
                                    <th>{{ translate('Name') }}</th>
                                    <th class="text-center">{{ translate('Marked Price') }}</th>
                                    <th class="text-center">{{ translate('Offer Price') }}</th>
                                    <th class="text-center">{{ translate('Min Qty') }}</th>
                                     <!-- Only show Discount Type and Offer Percent headers if offer_type is not 2 -->
            @if($offers_products->contains(fn($product) => $product->offer_type != 2))
                <th width="140px" class="text-center">{{ translate('Discount Type') }}</th>
                <th class="text-center">{{ translate('Offer Percent') }}</th>
            @endif
                                    <th>{{ translate('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($offers_products as $index => $product)

                                    <tr id="offerrow-{{ $product->id }}">
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td class="text-center">{{ $product->offer_id }}</td>
                                        <td class="text-center">{{ $product->part_no }}</td>
                                        <td>{{ $product->name }}
                                            <input type="hidden" name="offers[{{ $product->id }}][product_id]" value="{{ $product->part_no }}">
                                        </td>
                                        <td class="text-center">{{ $product->mrp }}</td>
                                        <td class="text-center">
                                            <input type="number" name="offers[{{ $product->id }}][offer_price]" value="{{ $product->offer_price }}" step="0.01" class="form-control offer_price_input" {{ $product->discount_type == 'percent' ? 'readonly' : '' }}>
                                        </td>
                                        <td class="text-center">
                                            <input type="number" name="offers[{{ $product->id }}][min_qty]" value="{{ $product->min_qty }}" min="1" class="form-control min_qty_input">
                                        </td>
                                         @if($product->offer_type != 2)
                                        <td class="text-center">
                                            <select name="offers[{{ $product->id }}][discount_type]" class="form-control discount_type_select">
                                                <option value="amount" {{ $product->discount_type == 'amount' ? 'selected' : '' }}>{{ translate('Amount') }}</option>
                                                <option value="percent" {{ $product->discount_type == 'percent' ? 'selected' : '' }}>{{ translate('Percent') }}</option>
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            <input type="number" name="offers[{{ $product->id }}][offer_discount_percent]" value="{{ $product->offer_discount_percent }}" min="0" max="100" step="0.01" class="form-control discount_percent_input" {{ $product->discount_type == 'amount' ? 'readonly' : '' }}>
                                        </td>
                                        @endif
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm delete-offerproduct-item" data-id="{{ $product->id }}">
                                                <i class="las la-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 text-right">
                        <button type="submit" class="btn btn-success" id="save-button-offer-product">
                            <i class="las la-save"></i> {{ translate('Save Changes') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Complementary Items Tab Content -->

            <div style="margin-top:20px;" class="tab-pane fade" id="complementary-items" role="tabpanel" aria-labelledby="complementary-items-tab">
               @if($complementary_products->isNotEmpty())
                    <form action="{{ route('offer-products.save-complementary-items') }}" method="POST">
                        @csrf
                        <input type="hidden" name="offer_id" value="{{ $offer_id }}">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover small-text-table">
                                <thead class="bg-primary text-white">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>{{ translate('Complementary Part No') }}</th>
                                        <th>{{ translate('Name') }}</th>
                                        <th class="text-center">{{ translate('MRP') }}</th>
                                        <th class="text-center">{{ translate('Quantity') }}</th>
                                        <th class="text-center">{{ translate('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($complementary_products as $index => $compProduct)
                                        <tr id="row-{{ $compProduct->id }}">
                                            <td class="text-center">{{ $index + 1 }}</td>
                                            <td>{{ $compProduct->part_no }}</td>
                                            <td>{{ $compProduct->name }}</td>
                                            <td class="text-center">
                                                <input readonly type="number" name="complementary_items[{{ $compProduct->part_no }}][mrp]" value="{{ $compProduct->mrp }}" step="0.01" class="form-control">
                                            </td>
                                            <td class="text-center">
                                                <input type="number" name="complementary_items[{{ $compProduct->part_no }}][quantity]" value="{{ $compProduct->min_qty }}" min="1" class="form-control">
                                            </td>
                                              <td class="text-center">
                                            <button type="button" class="btn btn-danger btn-sm delete-complementary-item" data-id="{{ $compProduct->id }}">
                                                <i class="las la-trash"></i>
                                            </button>
                                        </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 text-right">
                            <button type="submit" class="btn btn-success">
                                <i class="las la-save"></i> {{ translate('Save Changes') }}
                            </button>
                                <button type="button" class="btn btn-primary " data-toggle="modal" data-target="#addComplementaryItemModal">
                                    <i class="las la-plus"></i> {{ translate('Add New') }}
                                </button>
                        </div>
                    </form>

                    @else
                    <button type="button" class="btn btn-primary " data-toggle="modal" data-target="#addComplementaryItemModal">
                                    <i class="las la-plus"></i> {{ translate('Add New') }}
                   </button>
                 @endif
                
            </div>
        </div>
    </div>
</div>

<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1" role="dialog" aria-labelledby="bulkUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="bulkUpdateModalLabel">
                    <i class="las la-bullhorn"></i> {{ translate('Bulk Update Offer Percent & Min Quantity') }}
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="bulkUpdateForm">
                    <div class="form-group">
                        <label for="bulkOfferPercent" class="font-weight-bold">{{ translate('Offer Percent') }}</label>
                        <input type="number" id="bulkOfferPercent" class="form-control" min="0" max="100" step="0.01" placeholder="{{ translate('Enter Offer Percent') }}">
                    </div>
                    <div class="form-group">
                        <label for="bulkMinQty" class="font-weight-bold">{{ translate('Min Quantity') }}</label>
                        <input type="number" id="bulkMinQty" class="form-control" min="1" placeholder="{{ translate('Enter Min Quantity') }}">
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                <button type="button" class="btn btn-primary" id="applyBulkUpdate">
                    <i class="las la-check"></i> {{ translate('Apply to All') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Complementry items Modal -->
<div class="modal fade" id="addComplementaryItemModal" tabindex="-1" role="dialog" aria-labelledby="addComplementaryItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addComplementaryItemModalLabel">{{ translate('Add New Complementary Item') }}</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('complementary-items.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="offer_id" value="{{ $offer_id }}">
                   <div class="form-group">
                        <label for="productSelect">{{ translate('Select Product') }}</label>
                        <select name="part_no" id="productSelect" class="form-control aiz-selectpicker" data-live-search="true" required>
                            <option value="">{{ translate('Select a product') }}</option>
                            @foreach($products as $product)
                                <option value="{{ $product->part_no }}">{{ $product->name }} ({{ $product->part_no }})</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">{{ translate('Quantity') }}</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    <button type="submit" class="btn btn-success">
                        <i class="las la-save"></i> {{ translate('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- offer product Modal -->

<div class="modal fade" id="selectionModal" tabindex="-1" role="dialog" aria-labelledby="selectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="selectionModalLabel">{{ translate('Add Products') }}</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('offer-products.save-selections') }}" method="POST">
                @csrf

                  <input type="hidden" name="offer_id" value="{{ $offer_id }}">
                <div class="modal-body">
                  

                    <!-- Category Multi-Select Dropdown -->
                    <div class="form-group">
                        <label>{{ translate('Category') }}</label>
                        <select  class="form-control aiz-selectpicker" name="category_ids[]" multiple data-live-search="true" id="category-select">
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->getTranslation('name') }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Brand Multi-Select Dropdown -->
                    <!-- <div class="form-group">
                        <label>{{ translate('Brand') }}</label>
                        <select required class="form-control aiz-selectpicker" name="brand_ids[]" multiple data-live-search="true" id="brand-select">
                            <option value="">{{ translate('Select Brand') }}</option>
                        </select>
                    </div> -->
                    <div class="form-group">
                        <label>{{ translate('Brand') }}</label>
                        <select  class="form-control aiz-selectpicker" name="brand_ids[]" multiple data-live-search="true" id="brand-select">
                            <option value="">{{ translate('Select Brand') }}</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Product Multi-Select Dropdown -->
                    <!-- <div class="form-group">
                        <label>{{ translate('Product') }}</label>
                        <select required class="form-control aiz-selectpicker" name="product_ids[]" multiple data-live-search="true" id="product-select">
                            <option value="">{{ translate('Select Product') }}</option>
                        </select>
                    </div> -->
                    <div class="form-group">
                        <label>{{ translate('Product') }}</label>
                        <select required class="form-control aiz-selectpicker" name="product_ids[]" multiple data-live-search="true" id="product-select">
                            <option value="">{{ translate('Select Product') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->part_no }}">{{ $product->name }} ({{ $product->part_no }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    <button type="submit" class="btn btn-success">
                        <i class="las la-save"></i> {{ translate('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>




@endsection

@section('script')
<script>
    $(document).ready(function() {


        // Restore active tab from localStorage
        var activeTab = localStorage.getItem('activeTab');
        if (activeTab) {
            $('#offerTab a[href="' + activeTab + '"]').tab('show');
        }

        // Save the active tab to localStorage on change
        $('#offerTab a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            localStorage.setItem('activeTab', $(e.target).attr('href'));
        });

        $('#applyBulkUpdate').on('click', function() {
            let bulkOfferPercent = $('#bulkOfferPercent').val();
            let bulkMinQty = $('#bulkMinQty').val();

            $('.discount_type_select').each(function() {
                $(this).val('percent').trigger('change'); 
            });

            if (bulkOfferPercent) {
                $('.discount_percent_input').val(bulkOfferPercent);
            }
            if (bulkMinQty) {
                $('.min_qty_input').val(bulkMinQty);
            }

            $('#bulkUpdateModal').modal('hide');
        });

        $(document).on('change', '.discount_type_select', function() {
            let row = $(this).closest('tr');
            let discountType = $(this).val();
            let discountPercentInput = row.find('.discount_percent_input');
            let offerPriceInput = row.find('.offer_price_input');

            if (discountType === 'amount') {
                discountPercentInput.prop('readonly', true).val(''); 
                offerPriceInput.prop('readonly', false);
            } else if (discountType === 'percent') {
                discountPercentInput.prop('readonly', false); 
                offerPriceInput.prop('readonly', true).val(''); 
            }
        });

        $('.discount_type_select').each(function() {
            $(this).trigger('change');
        });

          // Bind click event to the delete button
        $('.delete-complementary-item').on('click', function () {
            const itemId = $(this).data('id'); // Get the item ID from data-id attribute

            const confirmation = confirm('{{ translate('Are you sure you want to delete this item?') }}'); // Show confirmation dialog

            if (confirmation) {
                $.ajax({
                    url: `{{ route('complementary-items.destroy', ':id') }}`.replace(':id', itemId), // Dynamic route
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}', // CSRF token
                        _method: 'DELETE' // Method override for DELETE
                    },
                    success: function (response) {
                        
                         $(`#row-${itemId}`).remove(); // Remove row immediately
                         AIZ.plugins.notify('danger', "Product deleted successfully!");
                    },
                    error: function (xhr) {
                        // Show error message if any
                        alert(xhr.responseJSON?.message || '{{ translate('Something went wrong!') }}');
                    }
                });
            }
        });

        // edit start
         // Handle Category Change
      
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


        //edit end

        // Save Button Click
        $('#saveSelection').on('click', function () {
            var formData = $('#selectionForm').serialize();
            $.ajax({
                url: '{{ route("offer-products.save-selections") }}', // Replace with your save route
                method: 'POST',
                data: formData,
                success: function (response) {
                    
                    alert('{{ translate("Selections saved successfully!") }}');
                    $('#selectionModal').modal('hide'); // Close modal
                },
                error: function (xhr) {
                    alert('{{ translate("Something went wrong!") }}');
                }
            });
        });


       $(document).on('click', '.delete-offerproduct-item', function () {
            const productId = $(this).data('id'); // Get product ID from data-id attribute
            const confirmation = confirm('{{ translate("Are you sure you want to delete this product?") }}'); // Confirmation dialog

            if (confirmation) {
                $.ajax({
                    url: '{{ route("offer-products.delete", ":id") }}'.replace(':id', productId), // Dynamic route
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}', // CSRF token for security
                    },
                    success: function (response) {
                        if (response.success) {
                            // Remove the row from the table
                            $('#offerrow-' + productId).remove();
                            AIZ.plugins.notify('success', "Product deleted successfully!");
                            
                        } else {
                            AIZ.plugins.notify('warning', "Unable to delete the product!");
                           
                        }
                    },
                    error: function () {
                         AIZ.plugins.notify('danger', "Something went wrong!");
                        
                    }
                });
            }
        });


       $(document).on('input', '.offer_price_input', function () {
            const $row = $(this).closest('tr'); // Get the current row
            const $cell = $(this).closest('td'); // Get the cell containing the input
            const markedPrice = parseFloat($row.find('td:nth-child(5)').text().trim()); // Get Marked Price
            const enteredOfferPrice = parseFloat($(this).val()); // Get the entered Offer Price

            // Calculate 24% of the marked price
            const minimumOfferPrice = markedPrice - (markedPrice * 0.24);

            // Message element
            const $message = $row.find('.validation-message');

            // Check if entered price exceeds the minimum offer price
            if (enteredOfferPrice > minimumOfferPrice) {
               // $cell.addClass('border-danger'); // Add red border to the cell
                if ($message.length === 0) {
                    $cell.append('<span class="validation-message text-danger">Offer price greater then product net price.</span>');
                }
                $('#save-button-offer-product').prop('disabled', true); // Disable Save button
            } else {
               // $cell.removeClass('border-danger'); // Remove red border if valid
                $message.remove(); // Remove the validation message

                // Check if all inputs are valid before enabling Save button
                let allValid = true;
                $('.offer_price_input').each(function () {
                    const $inputRow = $(this).closest('tr');
                    const markedPrice = parseFloat($inputRow.find('td:nth-child(5)').text().trim());
                    const inputOfferPrice = parseFloat($(this).val());
                    const minimumOfferPrice = markedPrice - (markedPrice * 0.24);

                    if (inputOfferPrice > minimumOfferPrice) {
                        allValid = false;
                    }
                });

                $('#save-button-offer-product').prop('disabled', !allValid); // Enable or disable Save button based on validity
            }
        });

    });

    
</script>
@endsection

@section('style')
<style>
    .custom-tabs .nav-link {
        font-weight: bold;
    }
    .custom-tabs .nav-link.active {
        color: #007bff;
        border-color: #007bff;
    }
    .small-text-table td, .small-text-table th {
        font-size: 0.9rem;
        padding: 0.5rem;
    }
    .table-responsive {
        overflow-x: auto;
    }
    .btn-outline-primary {
        font-size: 0.85rem;
        padding: 0.4rem 1rem;
    }
    @media (max-width: 768px) {
        .small-text-table td, .small-text-table th {
            font-size: 0.8rem;
            padding: 0.4rem;
        }
        .btn-outline-primary {
            font-size: 0.75rem;
            padding: 0.35rem 0.8rem;
        }
    }
</style>
@endsection
