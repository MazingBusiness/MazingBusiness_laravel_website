@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-2 mb-4">
    <h1 class="h3 text-primary">{{ translate('Manual Reward Points') }}</h1>
</div>

<!-- Filters Card -->
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">{{ translate('Filter Options') }}</h5>
    </div>
    <div class="card-body">
        <form id="sort_customers" action="" method="GET">
            <div class="row gy-3">
                <!-- Search Input -->
                <div class="col-md-3">
                    <label for="search" class="form-label">{{ translate('Search') }}</label>
                    <input type="text" name="search" id="search" class="form-control" 
                        placeholder="{{ translate('Search by Party Code, Phone or Name') }}" 
                        value="{{ $sort_search }}">
                </div>

                <!-- Warehouse Dropdown -->
                <div class="col-md-3">
                    <label for="warehouse" class="form-label">{{ translate('Select Warehouse') }}</label>
                    <select name="warehouse[]" id="warehouse" class="form-control select2" multiple>
                        <option value="">{{ translate('Select Warehouse') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" 
                                @if(is_array(request('warehouse')) && in_array($warehouse->id, request('warehouse'))) 
                                    selected 
                                @endif>
                                {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Manager Dropdown -->
                <div class="col-md-3">
                    <label for="manager" class="form-label">{{ translate('Select Manager') }}</label>
                    <select name="manager[]" id="manager" class="form-control select2" multiple>
                        <option value="">{{ translate('Select Manager') }}</option>
                        @if (request('warehouse'))
                            @foreach ($staffUsers as $manager)
                                <option value="{{ $manager->id }}" 
                                    @if(is_array(request('manager')) && in_array($manager->id, request('manager'))) 
                                        selected 
                                    @endif>
                                    {{ $manager->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Filter Button -->
                <div class="col-md-3 mt-4 text-end">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-filter"></i> {{ translate('Apply Filter') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Table Card -->
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-primary">
                    <tr class="text-center">
                        <th>#</th>
                        <th>{{ translate('Company Name') }}</th>
                        <th>{{ translate('Party Code') }}</th>
                        <th>{{ translate('Due Amount') }}</th>
                        <th>{{ translate('Overdue Amount') }}</th>
                        <th>{{ translate('Manager') }}</th>
                        <th>{{ translate('Credit Days') }}</th>
                        <th>{{ translate('Credit Limit') }}</th>
                        <th>{{ translate('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $key => $user)
                        <tr class="text-center">
                            <td>{{ $key + 1 + ($users->currentPage() - 1) * $users->perPage() }}</td>
                            <td>{{ $user->company_name }}</td>
                            <td>{{ $user->party_code }}</td>
                            <td>
                               @if ($user->address_by_party_code)
                                 {{ $user->address_by_party_code->due_amount }}
                                @else
                                    0.00
                                @endif
                            </td>
                            <td>
                                 @if ($user->address_by_party_code)
                                    {{ $user->address_by_party_code->overdue_amount }}

                                @else
                                    0.00
                                @endif
                            </td>
                            <td>{{ optional($user->manager)->name ?? translate('Unassigned') }}</td>
                            <td>{{ $user->credit_days ?? '0' }}</td>
                            <td>{{ $user->credit_limit  }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning text-white edit-button"
                                    data-toggle="modal"
                                    data-target="#editModal"
                                    data-id="{{ $user->id }}"
                                    data-company-name="{{ $user->company_name }}">
                                    <i class="las la-edit"></i> {{ translate('Edit') }}
                                </button>

                                <button  href="#" 
                                   class="mt-2 btn btn-primary btn-sm my_pdf" 
                                   data-party-code="{{ $user->party_code }}" 
                                   
                                   data-user-id="{{ $user->id }}"
                                   style="padding: 6px 8px; display: inline-flex; align-items: center; justify-content: center;">
                                    View Pdf
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-center mt-4">
            {{ $users->appends(request()->input())->links() }}
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editModalLabel">{{ translate('Edit Reward') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <form id="editForm" method="POST" action="{{ route('update.reward.point') }}">
                    @csrf
                    <input type="hidden" name="user_id" id="userId">

                    <div class="mb-3">
                        <label for="companyName" class="form-label">{{ translate('Company Name') }}</label>
                        <input type="text" class="form-control" id="companyName" name="company_name" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="reward_input" class="form-label">{{ translate('Reward') }}</label>
                        <input type="number" class="form-control" id="reward_input" name="reward_points" placeholder="{{ translate('Enter Manual Reward') }}" required>
                    </div>
                     <div class="mb-3">
                        <label for="note_input" class="form-label">{{ translate('Note') }}</label>
                        <textarea required class="form-control" id="note_input" name="note" placeholder="{{ translate('Enter a Note') }}"></textarea>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                <button id="model_save_button"; type="submit" class="btn btn-primary" form="editForm">{{ translate('Save Changes') }}</button>
            </div>
        </div>
    </div>
</div>



<div class="modal fade" id="pdfModal" tabindex="-1" role="dialog" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="height: 90vh;"> <!-- Set modal height -->
            <div class="modal-header">
                <h5 class="modal-title" id="pdfModalLabel">View PDF</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 0; height: 100%;">
                <!-- Embed PDF here -->
                <iframe id="pdfViewer" src="" frameborder="0" width="100%" height="100%" style="height: 100%;"></iframe>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
    $(document).ready(function() {


         // Initialize Select2 for the dropdowns
        $('#warehouse').select2({
            placeholder: "{{ translate('Select Warehouse') }}",
            allowClear: true,
            width: '100%'
        });

        $('#manager').select2({
            placeholder: "{{ translate('Select Manager') }}",
            allowClear: true,
            width: '100%'
        });

        // Handle Warehouse Change Event
        $('#warehouse').on('change', function () {
            var warehouseIds = $(this).val(); // Get selected warehouse IDs as an array
            if (warehouseIds.length > 0) {
                var warehouseIdsString = warehouseIds.join(','); // Convert array to comma-separated string
                $.ajax({
                    url: "{{ route('get_manager_by_warehouse') }}",
                    type: "GET",
                    data: { warehouse_id: warehouseIdsString }, // Pass as a string
                    success: function (managers) {
                        // Populate manager dropdown with results
                        $('#manager').empty().append('<option value="">{{ translate("Select Manager") }}</option>');
                        $.each(managers, function (key, manager) {
                            $('#manager').append('<option value="' + manager.id + '">' + manager.name + '</option>');
                        });
                    },
                    error: function (xhr) {
                        console.log(xhr.responseText); // Log any errors
                    }
                });
            } else {
                // Reset manager dropdown if no warehouses are selected
                $('#manager').empty().append('<option value="">{{ translate("Select Manager") }}</option>');
            }
        });
       

        // Handle "Edit" button click
        $(document).on('click', '.edit-button', function () {
            $('#userId').val($(this).data('id'));
            $('#companyName').val($(this).data('company-name'));
            $('#note_input').val($(this).data('note') || ''); // Set note or leave blank if not available
            $('#editModal').modal('show');
        });

        // Reset modal fields when closed
        $('#editModal').on('hidden.bs.modal', function () {
            $('#userId, #companyName, #reward_input, #note_input').val(''); // Clear all inputs
        });

        // Handle form submission via AJAX
        $('#editForm').on('submit', function (e) {
            e.preventDefault(); // Prevent default form submission
           $('#model_save_button').prop('disabled', true);
            
            // Collect form data
            let formData = {
                _token: '{{ csrf_token() }}', // CSRF token
                user_id: $('#userId').val(),
                reward_points: $('#reward_input').val(),
                note: $('#note_input').val(),
            };

            // Send AJAX request
            $.ajax({
                url: "{{ route('update.reward.point') }}", // Laravel route for handling submission
                method: 'POST',
                data: formData,
                success: function (response) {
                    $('#editModal').modal('hide');
                    // Handle success response
                    AIZ.plugins.notify('success', 'Reward Point updated Successfully.');
                     // Close the modal
                    //location.reload(); // Reload the page to reflect changes (optional)
                },
                error: function (xhr) {
                    // Handle error response
                    AIZ.plugins.notify('success', 'Failed to update reward points. Please try again.');
                    
                    console.log(xhr.responseText); // Log error details for debugging
                }
            });
            $('#model_save_button').prop('disabled', false);
        });



        $(document).on('click', '.my_pdf', function(event) {
            event.preventDefault(); // Prevent default link behavior
    
            // Get user ID from data attribute
            let party_code = $(this).data('party-code');

            // alert("some thing went wrong!");
            // return;

            // Make an AJAX request to get the PDF URL
            $.ajax({
                url: `/admin/reward-pdf/${party_code}`, // Updated to match the correct route
                type: 'GET',
                success: function(response) {
                    console.log("AJAX Response:", response); // Log the response for debugging
                    if (response) {
                        // Set the PDF URL in the iframe
                        $('#pdfViewer').attr('src', response);

                        // Show the modal
                        $('#pdfModal').modal('show');
                    } else {
                        alert("Failed to generate PDF. Please try again.");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error: ", error); // Log the error for debugging
                    console.error("Response Text: ", xhr.responseText);
                    alert("An error occurred while generating the PDF. Please check the console for details.");
                }
            });
        });
    });
</script>
@endsection
