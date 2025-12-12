@extends('backend.layouts.app')

@section('content')

<style>
    /* Professional Table Styling */
    .table-group-header {
        background-color: #f8f9fa; /* Light gray for group headers */
        font-weight: bold;
        color: #333; /* Dark gray text for readability */
    }

    .table tbody tr:nth-child(odd) {
        background-color: #ffffff; /* White for odd rows */
    }

    .table tbody tr:nth-child(even) {
        background-color: #f7f7f7; /* Light gray for even rows */
    }

    .table tbody tr:hover {
        background-color: #eaf4fc; /* Light blue hover effect */
    }

    .table td, .table th {
        padding: 12px; /* Consistent padding for cells */
        vertical-align: middle;
        text-align: center;
        border: 1px solid #dee2e6; /* Subtle borders for better structure */
    }

    .btn-icon {
        margin: 0 5px; /* Spacing between action buttons */
    }

    .text-primary {
        font-weight: bold;
        color: #007bff; /* Primary color for highlights */
    }
</style>
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="align-items-center">
      <h1 class="h3">{{ translate('Abandoned Carts') }}</h1>
      @if (session('status'))
        {{ session('status') }}
      @endif
    </div>
    
    <!-- <div class="col text-right">
   
      
      <a href="{{route('abandoned-carts.send_whatsapp')}}"  class="btn btn-sm btn-success" title="{{ translate('Send WhatsApp') }}">
        <span>Whatsapp All</span>
      </a>
      <a href="{{ route('abandoned-cart.export', request()->all()) }}" class="btn btn-sm btn-primary ml-2" title="{{ translate('Export to Excel') }}">
        <span>Export to Excel</span>
      </a>
    </div> -->
    <!-- Global Buttons Div with Custom Styling -->
<div class="d-flex justify-content-end mt-3 mb-3 custom-button-container">
    <div class="btn-group">
        <a href="{{ route('abandoned-carts.send_whatsapp') }}" class="btn btn-sm btn-success">
            <i class="lab la-whatsapp"></i> {{ translate('WhatsApp All') }}
        </a>
        <a href="{{ route('abandoned-cart.export', request()->all()) }}" class="btn btn-sm btn-primary ml-2">
            <i class="las la-file-excel"></i> {{ translate('Export to Excel') }}
        </a>
    </div>
</div>

  </div>

  <div class="card">


    <div class="card-header row gutters-5">
      <form method="GET" action="{{ route('abandoned.cartlist') }}" class="d-flex w-100">
        <div class="col-md-3">
            <input type="date" class="form-control" id="searchDate" name="searchDate" value="{{ request('searchDate') }}" placeholder="{{ translate('Search by Date') }}">
        </div>
        <div class="col-md-3">
            <div class="multiselect-dropdown-btn">
                <div class="multiselect-selected">
                    <span>{{ translate('Select Manager') }}</span>
                </div>
            </div>
            <ul class="multiselect-container" style="display: none;">
              @foreach($distinctManagers as $data)
                  @php
                      $managerUser = DB::table('users')
                          ->where('id', $data->manager_id)
                          ->first();
                  @endphp
                  @if(!is_null($managerUser))
                  <li>
                      <input type="checkbox" id="manager-{{ $managerUser->id }}" name="searchManager[]" value="{{ $managerUser->id }}" 
                          {{ in_array($managerUser->id, request('searchManager', [])) ? 'checked' : '' }}>
                      <label for="manager-{{ $managerUser->id }}">{{ $managerUser->name }}</label>
                  </li>
                  @endif
              @endforeach
            </ul>
        </div>
        <div class="col-md-3">
            <div class="multiselect-dropdown-btn">
                <div class="multiselect-selected">
                    <span>{{ translate('Select Company Name') }}</span>
                </div>
            </div>
            <ul class="multiselect-container my_company" style="display: none;">
                @foreach($distinctCompanyNames as $company)
                    <li>
                        <input type="checkbox" id="company-{{ $company->company_name }}" name="searchCompanyName[]" value="{{ $company->company_name }}" 
                            {{ in_array($company->company_name, request('searchCompanyName', [])) ? 'checked' : '' }}>
                        <label for="company-{{ $company->company_name }}">{{ $company->company_name }}</label>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-sm">{{ translate('Filter') }}</button>
        </div>
      </form>
    </div>

    <div class="card-body">
      <form method="POST" action="{{ route('abandoned-carts.send_bulk_whatsapp') }}">
        @csrf
        <div class="mb-3">
            <strong style="font-size: 20px;">{{ translate('Total Cart Value:') }} {{ number_format($totalSum, 2) }}</strong>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-sm btn-success">{{ translate('Send WhatsApp') }}</button>
            <button type="button" class="btn btn-sm btn-danger ml-2" id="clearCartBtn">{{ translate('Clear Cart') }}</button>
        </div>
        <table class="table aiz-table mb-0">
          <thead>
    <tr>
        <th><input type="checkbox" id="select-all"></th>
        <th>
            <a href="{{ route('abandoned.cartlist', ['sortField' => 'u1.company_name', 'sortDirection' => request('sortDirection') == 'asc' ? 'desc' : 'asc']) }}">
                {{ translate('Company Name') }}
                @if (request('sortField') == 'u1.company_name')
                    @if (request('sortDirection') == 'asc')
                        &uarr;
                    @else
                        &darr;
                    @endif
                @endif
            </a>
        </th>
        <th>
            <a href="{{ route('abandoned.cartlist', ['sortField' => 'u2.name', 'sortDirection' => request('sortDirection') == 'asc' ? 'desc' : 'asc']) }}">
                {{ translate('Manager Name') }}
                @if (request('sortField') == 'u2.name')
                    @if (request('sortDirection') == 'asc')
                        &uarr;
                    @else
                        &darr;
                    @endif
                @endif
            </a>
        </th>
        <th>{{ translate('Phone') }}</th>
        <th>
            <a href="{{ route('abandoned.cartlist', ['sortField' => 'u1.party_code', 'sortDirection' => request('sortDirection') == 'asc' ? 'desc' : 'asc']) }}">
                {{ translate('Last Cart Date') }}
                @if (request('sortField') == 'u1.party_code')
                    @if (request('sortDirection') == 'asc')
                        &uarr;
                    @else
                        &darr;
                    @endif
                @endif
            </a>
        </th>
        <th>Total</th>
     
        <th class="text-left">{{ translate('Options') }}</th>
    </tr>
</thead>

<tbody>
        @php
    // Sirf wahi rows jinke cart table me is_manager_41 = 0
    $groupedCarts = $abandonedCart->where('is_manager_41', 0)->groupBy('user_id');  
@endphp

        @foreach ($groupedCarts as $party_code => $carts)
            @php
                // Calculate the total cart value for the current party_code
                $totalCartValue = $carts->sum(function ($cart) {
                    return $cart->quantity * $cart->price;
                });
            @endphp

            <!-- Party Code Header -->
            <tr class="table-group-header">
                <td><input type="checkbox" name="selected_carts[]" value="{{ $carts->first()->user_id }}"></td>
                <td>{{ $carts->first()->company_name }}</td>
                <td>{{ $carts->first()->manager_name ?? 'N/A' }}</td>
                <td>{{ $carts->first()->phone }}</td>
                <td>{{ $carts->first()->created_at }}</td>
                <td class="text-primary">{{ '₹ ' . number_format($totalCartValue, 2) }}</td>
                <td>
                    <a href="{{ route('customers.login', encrypt($carts->first()->user_id)) }}"
                       class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                       title="{{ translate('Log in as this Customer') }}">
                        <i class="las la-sign-in-alt"></i>
                    </a>
                    <button type="button" class="btn btn-soft-primary btn-icon btn-circle btn-sm" data-toggle="collapse" data-target="#group-{{ $loop->index }}" aria-expanded="false">
                        +
                    </button>
                </td>
            </tr>

            <!-- Collapsible Rows for Each Party Code -->
            <tr class="collapse" id="group-{{ $loop->index }}">
                <td colspan="7">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><input type="checkbox"></th>
                                <th>{{ translate('Date') }}</th>
                                <th>{{ translate('Item Name') }}</th>
                                <th>{{ translate('Quantity') }}</th>
                                <th>{{ translate('Price') }}</th>
                                <th>{{ translate('Total') }}</th>
                                <th class="text-right">{{ translate('Options') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($carts as $cart)
                                <tr>
                                    <td><input type="checkbox" name="selected_carts[]" value="{{ $cart->user_id }}"></td>
                                    <td>{{ $cart->created_at }}</td>
                                    <td>{{ $cart->product_name }}</td>
                                    <td>{{ $cart->quantity }}</td>
                                    <td>{{ '₹ ' . $cart->price }}</td>
                                    <td>{{ '₹ ' . ($cart->quantity * $cart->price) }}</td>
                                    <td class="text-right">
                                        <div class="d-flex align-items-center justify-content-end">
                                            <a href="{{ route('abandoned-carts-single.send_single_whatsapp', $cart->cart_id) }}" class="btn btn-success btn-icon btn-circle btn-sm" title="{{ translate('Send WhatsApp') }}">
                                                <i class="lab la-whatsapp"></i>
                                            </a>
                                            <a href="#" class="btn btn-soft-primary btn-icon btn-circle btn-sm" title="{{ translate('Remark') }}" data-toggle="modal" data-target="#remarkModal" data-id="{{ $cart->cart_id }}" data-user-id="{{ $cart->user_id }}">
                                                <i class="las la-comment"></i>
                                            </a>
                                            <a href="#" class="btn btn-soft-primary btn-icon btn-circle btn-sm ml-2" title="{{ translate('View Remarks') }}" data-toggle="modal" data-target="#viewRemarksModal" data-id="{{ $cart->cart_id }}">
                                                <i class="las la-eye"></i>
                                            </a>
                                            <a href="javascript:void(0);" class="btn btn-soft-danger btn-icon btn-circle btn-sm delete-cart" data-product-id="{{ $cart->product_id }}" data-user-id="{{ $cart->user_id }}" title="{{ translate('Delete Cart') }}">
                                                <i class="las la-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        @endforeach
    </tbody>


        </table>
        
      </form>
    </div>

    <div class="aiz-pagination">
     {{-- {{ $abandonedCart->links('pagination::bootstrap-4') }} --}}
    </div>
  </div>

  <!-- Remark Modal -->
  <div class="modal fade" id="remarkModal" tabindex="-1" role="dialog" aria-labelledby="remarkModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="remarkModalLabel">{{ translate('Add Remark') }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form id="remarkForm">
            @csrf
            <div class="form-group">
              <label for="remark">{{ translate('Remark') }}</label>
              <textarea class="form-control" id="remark" name="remark" rows="4"></textarea>
              <span class="text-danger" id="remark-error"></span>
            </div>
            <input type="hidden" id="cart_id" name="cart_id" value="">
            <input type="hidden" id="user_id" name="user_id" value="">
            <button type="button" class="btn btn-primary" id="saveRemark">{{ translate('Save Remark') }}</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- View Remarks Modal -->
  <div class="modal fade" id="viewRemarksModal" tabindex="-1" role="dialog" aria-labelledby="viewRemarksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewRemarksModalLabel">{{ translate('View Remarks') }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div id="remarksTableBody">
            <!-- Remarks will be loaded here dynamically -->
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@section('script')
<script type="text/javascript">
  $(document).ready(function() {
    // Toggle the dropdown when the button is clicked
    $('.multiselect-dropdown-btn').click(function() {
        $(this).toggleClass('open');
        $(this).next('.multiselect-container').slideToggle(200);
    });

    // Update the selected items when checkboxes are clicked
    $('.multiselect-container input[type="checkbox"]').change(function() {
        updateSelectedItems($(this).closest('.multiselect-container'));
    });

    function updateSelectedItems(container) {
        let selectedItems = [];
        container.find('input[type="checkbox"]:checked').each(function() {
            selectedItems.push($(this).next('label').text());
        });

        let displayArea = container.prev('.multiselect-dropdown-btn').find('.multiselect-selected');
        displayArea.empty();

        if (selectedItems.length > 0) {
            selectedItems.forEach(item => {
                displayArea.append(`<span>${item}</span>`);
            });
        } else {
            displayArea.append('<span>Select items</span>');
        }
    }

    $('#remarkModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); // Button that triggered the modal
        var cartId = button.data('id'); // Extract info from data-* attributes
        var userId = button.data('user-id'); // Extract the user ID
        var modal = $(this);
        modal.find('.modal-body #cart_id').val(cartId);
        modal.find('.modal-body #user_id').val(userId);
    });

    $('#saveRemark').on('click', function() {
        var form = $('#remarkForm');
        var data = form.serialize();
        $('#remark-error').text('');
        // Make an AJAX request to save the remark
        $.ajax({
          url: '{{ route("abandoned-carts.save_remark") }}',
          type: 'POST',
          data: data,
          success: function(response) {
            if (response.success) {
              form.trigger("reset");
              $('#remarkModal').modal('hide');
              alert(response.message);
            }
          },
          error: function(response) {
            if (response.status === 422) {
                var errors = response.responseJSON.errors;
                if (errors.remark) {
                    $('#remark-error').text(errors.remark[0]);
                }
            } else {
                alert('Error saving remark');
            }
          }
        });
    });

    $('#viewRemarksModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var cartId = button.data('id');
        var modal = $(this);

        // Clear previous data
        $('#remarksTableBody').empty();

        // Make an AJAX request to get the remarks
        $.ajax({
            url: '{{ route("abandoned-carts.get_remarks") }}',
            type: 'GET',
            data: { cart_id: cartId },
            success: function(response) {
                if (response.success) {
                    var remarks = response.remarks;
                    if (remarks.length > 0) {
                        remarks.forEach(function(remark) {
                            var row = '<div class="remark-item">' +
                                      '<div class="remark-description">' + remark.remark_description + '</div>' +
                                      '<div class="remark-timestamp">' + remark.created_at + '</div>' +
                                      '</div>';
                            $('#remarksTableBody').append(row);
                        });
                    } else {
                        $('#remarksTableBody').append('<div class="text-center">No remarks found.</div>');
                    }
                } else {
                    $('#remarksTableBody').append('<div class="text-center">' + response.message + '</div>');
                }
            },
            error: function(response) {
                alert('Error loading remarks');
            }
        });
    });

    // Select all checkboxes
    document.getElementById('select-all').onclick = function() {
        var checkboxes = document.getElementsByName('selected_carts[]');
        for (var checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
    }
  });


  //clear cart coding
  $('#clearCartBtn').on('click', function() {
    var selectedCarts = [];
    $('input[name="selected_carts[]"]:checked').each(function() {
        selectedCarts.push($(this).val());
    });

    if (selectedCarts.length === 0) {
        alert("Please select at least one cart to clear.");
        return;
    }

    if (confirm("Are you sure you want to clear the selected carts?")) {
        $.ajax({
            url: '{{ route("abandoned-carts.clear_cart") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                user_ids: selectedCarts
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload(); // Refresh the page to reflect the changes
                } else {
                    alert('Error clearing cart');
                }
            },
            error: function(xhr) {
                alert('Error occurred while clearing the cart');
            }
        });
    }
});


$(document).on('click', '.delete-cart', function() {
    var userId = $(this).data('user-id'); // Get the user ID from the data attribute

    var productId = $(this).data('product-id');
   
   
    if (confirm("Are you sure you want to delete the cart items for this user?")) {
        $.ajax({
            url: '{{ route("abandoned-carts.delete_cart_item") }}', // Make sure this route points to your new method
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                user_id: userId,
                product_id: productId // Pass the product ID
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload(); // Refresh the page to reflect changes
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                alert('Error occurred while deleting the cart');
            }
        });
    }
});

  $(document).ready(function () {
        $('.toggle-btn').on('click', function () {
            const isExpanded = $(this).attr('aria-expanded') === 'true';
            $(this).text(isExpanded ? '+' : '-');
        });
    });


  $(document).ready(function () {
    // When a manager checkbox is changed
    $("input[name='searchManager[]']").on("change", function () {
      

        const selectedManagerId = $(this).val(); // Get the selected manager ID
        const companyContainer = $(".my_company");



        // Make an AJAX request to fetch companies
        $.ajax({
            url: "/fetch-companies",
            method: "GET",
            data: { manager_id: selectedManagerId },
            success: function (response) {
                // Clear the existing company options
                companyContainer.empty();

                // Add the new options to the company dropdown
                response.forEach(function (company) {
                    const companyOption = `
                        <li>
                            <input type="checkbox" id="company-${company.company_name}" name="searchCompanyName[]" value="${company.company_name}">
                            <label for="company-${company.company_name}">${company.company_name}</label>
                        </li>`;

                    companyContainer.append(companyOption);
                });
            },
            error: function () {
                alert("Failed to fetch company names. Please try again.");
            }
        });
    });
});


</script>

<style>
/* Custom style for multiselect dropdown */


.multiselect-container {
    position: absolute;
    background-color: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    padding: 10px;
}

.multiselect-container li {
    list-style: none;
    padding: 5px 10px;
    cursor: pointer;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.multiselect-container li:hover {
    background-color: #f0f0f0;
}

.multiselect-container input[type="checkbox"] {
    margin-right: 10px;
}

.multiselect-container .multiselect-header {
    font-weight: bold;
    padding-bottom: 5px;
    border-bottom: 1px solid #ddd;
    margin-bottom: 5px;
}

.multiselect-dropdown-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px 12px;
    cursor: pointer;
}

.multiselect-dropdown-btn:after {
    content: '\25BC';
    font-size: 12px;
    margin-left: 10px;
    transition: transform 0.3s;
}

.multiselect-dropdown-btn.open:after {
    transform: rotate(180deg);
}

.multiselect-selected {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.multiselect-selected span {
    background-color: #007bff;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
}

/* Container for the remarks */
#remarksTableBody {
    padding: 15px;
    background-color: #f0f2f5;
    border-radius: 10px;
    max-height: 300px;
    overflow-y: auto;
    font-size: 14px;
}

/* Individual Remark Item */
.remark-item {
    background-color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    padding: 15px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

/* Hover Effect for Remark Item */
.remark-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

/* Remark Description Styling */
.remark-description {
    font-size: 16px;
    font-weight: 700;
    color: #222;
    margin-bottom: 10px;
    line-height: 1.4;
}

/* Remark Timestamp Styling */
.remark-timestamp {
    font-size: 13px;
    color: #666;
    font-style: italic;
}
</style>
@endsection
