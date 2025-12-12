@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="align-items-center">
      <h1 class="h3">{{ translate('All OWN Brand Customers') }}</h1>
    </div>
    <!-- @if (auth()->user()->can('add_new_customer') || true)
      <div class="col text-right">
        <a href="{{ route('customers.create') }}" class="btn btn-circle btn-info">
          <span>{{ translate('Add New Customer') }}</span>
        </a>
      </div>
    @endif -->
  </div>


  <div class="card">
    <form class="" id="sort_customers" action="" method="GET">
      <div class="card-header row gutters-5">
        <div class="col">
          <h5 class="mb-0 h6">{{ translate('OWN Brand Customers') }}</h5>
        </div>

        <!-- <div class="dropdown mb-2 mb-md-0">
          <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
            {{ translate('Bulk Action') }}
          </button>
          <div class="dropdown-menu dropdown-menu-right">
            <a class="dropdown-item" href="#" onclick="bulk_delete()">{{ translate('Delete selection') }}</a>
          </div>
        </div> -->
        <div class="col-md-3">
          <div class="form-group mb-0">
            <select name="filter" id="filter" class="form-control">
              <option value="all">All Users</option>
              <option value="approved" @if(isset($filter) && $filter == "approved") selected @endif >Approved</option>
              <option value="un_approved" @if(isset($filter) && $filter == "un_approved") selected @endif>Un Approved</option>
            </select>
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group mb-0">
            <input type="text" class="form-control" id="search"
              name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset
              placeholder="{{ translate('Type Party Code, Phone no. or name & Enter') }}">
          </div>
        </div>
        
      </div>

      <div class="card-body">
        <table class="table aiz-table mb-0">
          <thead>
            <tr>
              <!--<th data-breakpoints="lg">#</th>-->
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
              <th>{{ translate('Name') }}</th>
              <th>{{ translate('Party Code') }}</th>
              <th data-breakpoints="lg">{{ translate('Warehouse') }}</th>
              <th data-breakpoints="lg">{{ translate('Manager') }}</th>
              <th data-breakpoints="lg">{{ translate('Discount') }}</th>
              <th data-breakpoints="lg">{{ translate('Phone') }}</th>
              <th data-breakpoints="lg">{{ translate('Profile Type') }}</th>
              @php $logged_user = Auth::user(); @endphp
              @if($logged_user->id == '180' || $logged_user->id == '25606' || $logged_user->id == '169')

                <th data-breakpoints="lg">{{ translate('Credit Days') }}</th>
                <th data-breakpoints="lg">{{ translate('Credit Limit') }}</th>
                <th data-breakpoints="lg">{{ translate('Credit Balance') }}</th>
                @endif

              <th class="text-right">{{ translate('Options') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($users as $key => $user)
              @if ($user != null)
                <tr>
                  <!--<td>{{ $key + 1 + ($users->currentPage() - 1) * $users->perPage() }}</td>-->
                  <td>
                    <div class="form-group">
                      <div class="aiz-checkbox-inline">
                        <label class="aiz-checkbox">
                          <input type="checkbox" class="check-one" name="id[]" value="{{ $user->id }}">
                          <span class="aiz-square-check"></span>
                        </label>
                      </div>
                    </div>
                  </td>
                  <td>
                    @if ($user->admin_approved_own_brand == 0)
                      <i class="las la-ban text-danger" aria-hidden="true"></i>
                    @endif 
                    {{ $user->company_name ? $user->company_name : $user->name }}
                  </td>
                  <td>{{ $user->party_code }}</td>
                  <td>@if ($user->warehouse)
                      {{ $user->warehouse->name }}
                    @endif
                  </td>
                  <td>{{ isset($user->manager) ? $user->manager->name : 'Unassigned' }}</td>
                  <td>{{ $user->discount }}</td>
                  <td>{{ $user->phone }}</td>
                  <td>{{ $user->profile_type }}</td>
                  @if($logged_user->id == '180' || $logged_user->id == '25606' || $logged_user->id == '169')
                    <td>{{ $user->credit_days }}</td>
                    <td>{{ $user->credit_limit }}</td>
                    <td>{{ $user->credit_balance }}</td>
                  @endif
                  <td class="text-right">
                    @if($logged_user->id == '1' || $logged_user->id == '180' || $logged_user->id == '25606' || $logged_user->id == '169')
                      @if ($user->own_brand == 1 AND $user->admin_approved_own_brand == 0)
                        <!-- <a href="{{ route('customers.approveOwnBrand', $user->id) }}" class="btn btn-soft-success btn-icon btn-circle btn-sm" title="{{ translate('Approve Customer for OWN Brand') }}">
                          <i class="lab la-discord"></i>
                        </a> -->
                        <a href="javascript:void(0)" class="btn btn-soft-success btn-icon btn-circle btn-sm" title="{{ translate('Approve Customer for OWN Brand') }}" data-toggle="modal" data-target="#approveCustomer" data-user-id="{{ $user->id }}" id="approveOwnBrandLink_{{ $user->id }}"><i class="lab la-discord"></i></a>
                      @endif
                    @endif
                    <!-- @can('edit_customer')
                      @if ($user->shipper_allocation)
                        <a href="{{ route('customers.edit', $user->id) }}"
                          class="btn btn-soft-success btn-icon btn-circle btn-sm"
                          title="{{ translate('Edit Customer Details') }}">
                          <i class="las la-edit"></i>
                        </a>
                      @endif
                    @endcan -->
                    @php
                      $user_logged_in = auth()->user();
                      if ($user_logged_in->role_id === 5) {
                          // Assign the 'view_all_customers' permission
                          $user_logged_in->givePermissionTo('login_as_customer');
                      }
                    @endphp
                    <?php /*@if ($user->banned == 0)
                      <a href="{{ route('customers.impexLoginFromAdmin', encrypt($user->id)) }}"class="btn btn-soft-primary btn-icon btn-circle btn-sm"title="{{ translate('Log in as this Customer') }}"><i class="las la-sign-in-alt"></i></a>
                    @endif*/ ?>
                    <a href="{{ route('customers.impexLoginFromAdmin', encrypt($user->id)) }}"class="btn btn-soft-primary btn-icon btn-circle btn-sm"title="{{ translate('Log in as this Customer') }}"><i class="las la-sign-in-alt"></i></a>
                    <!-- @if($logged_user->id == '1' || $logged_user->id == '180' || $logged_user->id == '25606' || $logged_user->id == '169')
                      @if ($user->banned == 1 && !$user->manager_id)
                        <a href="#" class="btn btn-soft-success btn-icon btn-circle btn-sm"
                          onclick="confirm_unban('{{ $user->id }}', {{ $user->warehouse_id }});"
                          title="{{ translate('Approve this Customer') }}">
                          <i class="las la-user-check"></i>
                        </a>
                      @endif
                    @endif -->
                    <!-- @can('delete_customer')
                      <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete"
                        data-href="{{ route('customers.destroy', $user->id) }}" title="{{ translate('Delete') }}">
                        <i class="las la-trash"></i>
                      </a>
                    @endcan -->
                  </td>
                </tr>
              @endif
            @endforeach
          </tbody>
        </table>
        <div class="aiz-pagination">
          {{ $users->appends(request()->input())->links() }}
        </div>
      </div>
    </form>
  </div>
  <div class="modal fade" id="confirm-ban">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title h6">{{ translate('Confirmation') }}</h5>
          <button type="button" class="close" data-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>{{ translate('Do you really want to ban this Customer?') }}</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">{{ translate('Cancel') }}</button>
          <a type="button" id="confirmation" class="btn btn-primary">{{ translate('Proceed!') }}</a>
        </div>
      </div>
    </div>
  </div>
  @foreach ($users as $key => $user)
    @if ($user->banned == 1)
      <div class="modal fade" id="assign_manager_{{ $user->id }}">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title h6">{{ translate('Approve this Customer?') }}</h5>
              <button type="button" class="close" data-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <label class="col-from-label" style="float:left;">{{ translate('Assign a Manager') }}</label>
              <select name="manager_id_{{$user->id}}" required class="form-control" id="manager_id_{{$user->id}}">
                <option value="" disabled selected>Select a Manager</option>
                @foreach (\App\Models\User::where('user_type', 'staff')->where('warehouse_id', $user->warehouse_id)->get() as $manager)
                  <option value="{{$manager->id}}">{{$manager->name}}</option>
                @endforeach
              </select>
              <span id="manager_err_{{$user->id}}" class="text-danger"></span>
              <p></p>
              <label class="col-from-label" style="float:left;">{{ translate('Credit Days') }}</label>
              <input type="number" name="credit_days_{{$user->id}}" id="credit_days_{{$user->id}}"  min="0" value="0" class="form-control">
              <span id="credit_days_err" class="text-danger"></span>
              <p></p>
              <label class="col-from-label" style="float:left;">{{ translate('Credit Limit') }}</label>
              <input type="number" name="credit_limit_{{$user->id}}" id="credit_limit_{{$user->id}}"  min="0" value="0" class="form-control">
              <span id="credit_limit_err_{{$user->id}}" class="text-danger"></span> 
              <p></p>
              <label class="col-from-label" style="float:left;">{{ translate('Discount') }}</label>
              <input type="number" name="discount_{{$user->id}}" id="discount_{{$user->id}}" value="" class="form-control">
              <span id="discount_err_{{$user->id}}" class="text-danger"></span> 
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-dismiss="modal">{{ translate('Cancel') }}</button>
              <a href="#" type="button" id="proceedBtn" class="btn btn-primary" onclick="getTheAssignValue({{$user->id}})">{{ translate('Proceed!') }}</a>
            </div>
          </div>
        </div>
      </div>
    @endif
  @endforeach
  <form name="frmAssigned" id="frmAssigned" method="POST" action="{{route('assignManager')}}">
    @csrf
    <input type="hidden" name="manager_id" id="manager_id" value="">
    <input type="hidden" name="discount" id="discount" value="">
    <input type="hidden" name="user_id" id="user_id" value="">
    <input type="hidden" name="credit_limit" id="credit_limit" value="">
    <input type="hidden" name="credit_days" id="credit_days" value="">
  </form>
  
  <div class="modal fade" id="approveCustomer" tabindex="-1" role="dialog" aria-labelledby="approveCustomerLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="approveCustomerLabel">Approve Customer</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form id="approveCustomerForm" method="POST">
            @csrf
            <div class="form-group">
              <label for="profile_type">{{ translate('Profile Type') }}</label>
              <select name="profile_type" id="profile_type" class="form-control">
                <option value="Bronze">Bronze</option>
                <option value="Silver">Silver</option>
                <option value="Gold">Gold</option>
              </select>
            </div>
            <input type="hidden" name="user_id" id="userId" value=""> <!-- Hidden input -->
            <!-- Other form fields -->
            <button type="button" id="submitApprove" class="btn btn-success">Approve</button> <!-- Trigger AJAX -->
          </form>
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
    function getTheAssignValue(userId) {
      $('#manager_err_' + userId).html('');
      $('#discount_err_' + userId).html('');
      $('#credit_limit_err_' + userId).html('');
      $('#credit_days_err_' + userId).html('');
      var selectedManagerId = $('#manager_id_' + userId).val();
      var discount = $('#discount_' + userId).val();
      var credit_limit = $('#credit_limit_' + userId).val();
      var credit_days = $('#credit_days_' + userId).val();
      var submitFlag = 1;
      if (selectedManagerId) {
        submitFlag = 1;
      } else {
          $('#manager_err_' + userId).html('Please select Manager!');
          submitFlag = 0;
      }
      if (discount) {
        if(discount > 24){
          $('#discount_err_' + userId).html('Discount no more than 24!');
          submitFlag = 0;
        }else{
          submitFlag = 1;
        }        
      } else {
          $('#discount_err').html('Please enter discount!');
          submitFlag = 0;
      }
      if (credit_limit) {
        submitFlag = 1;
      } else {
          $('#credit_limit_err_' + userId).html('Please enter credit limit at least 0.');
          submitFlag = 0;
      }
      if (credit_days) {
        submitFlag = 1;
      } else {
          $('#credit_days_err_' + userId).html('Please enter credit days at least 0.');
          submitFlag = 0;
      }
      if(submitFlag == 1){
        $('#manager_id').val(selectedManagerId);
        $('#discount').val(discount);
        $('#user_id').val(userId);
        $('#credit_limit').val(credit_limit);
        $('#credit_days').val(credit_days);
        
        console.log('Ready to submit');
        $('#frmAssigned').submit();
      }
    }
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

    $('#filter').change(function() {
      var value = $(this).val();
      if(value != ""){
        $('#sort_customers').submit();
      }
    });
    function sort_customers(el) {
      $('#sort_customers').submit();
    }

    function confirm_ban(url) {
      $('#confirm-ban').modal('show', {
        backdrop: 'static'
      });
      document.getElementById('confirmation').setAttribute('href', url);
    }

    function confirm_unban(user_id, warehouse_id) {
      $('#assign_manager_'+user_id).modal('show');
    }

    function updateHref() {
      document.getElementById('confirmationunban').setAttribute('href', '{{ url('admin/customers_ban') }}/' +
        $('#manager_assign').attr('data-user') + '/' + $('#manager_assign').val());
    }

    function bulk_delete() {
      var data = new FormData($('#sort_customers')[0]);
      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: "{{ route('bulk-customer-delete') }}",
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
    $(document).ready(function() {
      // When the modal is about to open
      $('#approveCustomer').on('show.bs.modal', function (event) {
          var button = $(event.relatedTarget); 
          var userId = button.data('user-id'); 
          var modal = $(this);
          modal.find('#userId').val(userId); // Set the user ID in the hidden input
      });

      // On click of the Approve button
      $('#submitApprove').on('click', function(e) {
          e.preventDefault();

          // Get form data
          var formData = {
              _token: "{{ csrf_token() }}", // Include CSRF token
              user_id: $('#userId').val(),  // Pass the user ID
              profile_type: $('#profile_type').val() // Pass the profile type
          };

          // Send AJAX POST request
          $.ajax({
              url: "{{ route('customers.approveOwnBrand') }}", // The post route
              method: 'POST',
              data: formData, // Send the form data
              success: function(response) {
                  // Show success message
                  AIZ.plugins.notify('success', 'Customer\'s OWN brand has been approved.');
                  
                  // Hide the modal
                  $('#approveCustomer').modal('hide');

                  // Dynamically hide the specific link based on user_id
                  var userId = $('#userId').val();
                  $('#approveOwnBrandLink_' + userId).hide(); // Use dynamic ID to target the link
              },
              error: function(xhr, status, error) {
                  // Handle error response
                  AIZ.plugins.notify('danger', 'Something went wrong. Please try again.');
              }
          });
      });
  });
  </script>
@endsection
