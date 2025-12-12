@extends('backend.layouts.app')

@section('content')
<!-- Select2 Style start -->
<style>
    .filter-container {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
        background: #ffffff;
        margin-left:17px;
        z-index: 0;
/*        padding: 1rem 1.5rem;*/
        border-radius: 8px;
       /* box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.1);
        border: 1px solid #ddd;*/
    }

    .filter-container .filter-group {
        flex: 1;
        min-width: 150px;
    }

    .filter-container .filter-group label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 6px;
        color: #333;
    }

    .filter-container .filter-group select {
        width: 100%;
        height: 40px;
        font-size: 14px;
        border-radius: 4px;
        border: 1px solid #ccc;
        padding: 0 10px;
        background-color: #f9f9f9;
        transition: border-color 0.2s;
    }

    .filter-container .filter-group select:focus {
        border-color: #007bff;
        outline: none;
    }

    .filter-container .filter-button {
        align-self: flex-end;
        height: 40px;
        line-height: 38px;
        padding: 0 20px;
        background-color: #007bff;
        color: white;
        font-weight: bold;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s, box-shadow 0.3s;
    }

    .filter-container .filter-button:hover {
        background-color: #0056b3;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.2);
    }

    .select2-container--default .select2-selection--single,
    .select2-container--default .select2-selection--multiple {
        height: 40px; /* Maintain consistent height */
        border-radius: 4px;
        font-size: 14px;
        border: 1px solid #ccc;
        padding: 6px 10px; /* Add consistent padding */
        background-color: #f9f9f9;
        display: flex;
        align-items: center; /* Center content vertically */
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px; /* Align text vertically */
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        font-size: 12px;
        background-color: #007bff;
        color: white;
        border-radius: 4px;
        margin: 4px 2px;
        padding: 2px 8px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: white;
        margin-right: 6px;
        cursor: pointer;
    }

    .select2-container--default .select2-selection--multiple {
        overflow-y: auto; /* Allow scrolling when many options are selected */
        height: auto; /* Allow height adjustment based on selected items */
        min-height: 40px; /* Ensure minimum height consistency */
    }

    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: flex;
        flex-wrap: wrap;
        gap: 4px; /* Space between selected items */
        align-items: center;
    }

    /* Adjust dropdown z-index to ensure it shows above other content */
    .select2-container {
        z-index: 1050;
    }
</style>

<!-- Select2 Style end -->

  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="align-items-center">
      <h1 class="h3">{{ translate('All Customers') }}</h1>
    </div>
    @if (auth()->user()->can('add_new_customer') || true)
      <div class="col text-right">
        <a href="{{ route('customers.create') }}" class="btn btn-circle btn-info">
          <span>{{ translate('Add New Customer') }}</span>
        </a>
      </div>
    @endif
  </div>
   <!-- Display Validation Errors -->
   @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
  <!-- Displaying success message -->
  @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

<!-- Displaying Error Message -->
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

  <div class="card">
    <form class="" id="sort_customers" action="" method="GET">
      <div class="card-header row gutters-5" style="padding:10px;">
        <!-- <div class="col"> -->
          <!-- <h5 class="mb-0 h6">{{ translate('Customers') }}</h5> -->
        <!-- </div> -->

        <!-- <div class="dropdown mb-2 mb-md-0">
          <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
            {{ translate('Bulk Action') }}
          </button>
          <div class="dropdown-menu dropdown-menu-right">
            <a class="dropdown-item" href="#" onclick="bulk_delete()">{{ translate('Delete selection') }}</a>
          </div>
        </div> -->

            <!-- changes start    -->
<div class="row align-items-center mb-3">
    <!-- Search Bar -->
    <div class="col-md-6">
        <input
            style="width: 100%;"
            type="text"
            class="form-control"
            id="search"
            name="search"
            @isset($sort_search) value="{{ $sort_search }}" @endisset
            placeholder="{{ translate('Type Party Code, Phone no. or name & Enter') }}"
        >
    </div>

    <!-- All User Dropdown -->
    <div class="col-md-6">
        <select name="filter" id="filter" class="form-control">
            <option value="all">All User</option>
            <option value="approved" @if(isset($filter) && $filter == "approved") selected @endif>Approved</option>
            <option value="un_approved" @if(isset($filter) && $filter == "un_approved") selected @endif>Un Approved</option>
        </select>
    </div>
</div>


<div class="row">

        <div class="filter-container">

            <!-- Warehouse Dropdown -->
                <div class="filter-group">
                    
                    <select name="warehouse[]" id="warehouse" class="form-control select2" multiple>
                        <!-- <option value="">{{ translate('Select Warehouse') }}</option> -->
                        @foreach (\DB::table('warehouses')->whereIn('id', [1, 2, 6])->get() as $warehouse)
                            <option value="{{ $warehouse->id }}" @if(is_array(request('warehouse')) && in_array($warehouse->id, request('warehouse'))) selected @endif>
                                {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Manager Dropdown -->
                <div class="filter-group">
                   
                    <select name="manager[]" id="manager" class="form-control select2" multiple>
                        <!-- <option value="">{{ translate('Manager') }}</option> -->
                        @if (request('warehouse'))
                            @foreach ($staffUsers as $manager)
                                <option value="{{ $manager->id }}" @if(is_array(request('manager')) && in_array($manager->id, request('manager'))) selected @endif>
                                    {{ $manager->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- City Dropdown -->
                <div class="filter-group">
                   
                    <select name="city" id="city" class="form-control select2">
                        <option value="">{{ translate('City') }}</option>
                        @if (request('city'))
                            @foreach ($cities as $city)
                                <option value="{{ $city }}" @if(request('city') == $city) selected @endif>
                                    {{ $city }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Discount Dropdown -->
                <div class="filter-group">
                    
                    <select name="discount" id="discount" class="form-control select2">
                        <option value="">{{ translate('Discount') }}</option>
                        @if ($discounts)
                            @foreach ($discounts as $discount)
                                <option value="{{ $discount }}" @if(request('discount') == $discount) selected @endif>
                                    {{ $discount }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Filter Button -->
                <div class="filter-group" >
                    <button type="submit" class="btn filter-button ">
                        <i class="fas fa-filter"></i> {{ translate('Filter') }}
                    </button>
                </div>
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
              <!-- <th>{{ translate('Name') }}</th> -->
              <th>
                <a href="{{ route('customers.index', ['sort_by' => 'company_name', 'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc']) }}">
                    {{ translate('Company Name') }}
                    @if (request('sort_by') === 'company_name')
                        <i class="las la-sort-{{ request('sort_order') === 'asc' ? 'up' : 'down' }}"></i>
                    @endif
                </a>
            </th>
              <th>{{ translate('Party Code') }}</th>
              @if (request('filter') === 'un_approved')
                    <th>
                        <a href="{{ route('customers.index', array_merge(request()->all(), [
                            'sort_by' => 'created_at',
                            'sort_order' => request('sort_order') === 'asc' ? 'desc' : 'asc'
                        ])) }}">
                            {{ translate('Created At') }}
                            @if (request('sort_by') === 'created_at')
                                <i class="las la-sort-{{ request('sort_order') === 'asc' ? 'up' : 'down' }}"></i>
                            @endif
                        </a>
                    </th>
                @endif
              <th data-breakpoints="lg">{{ translate('Due amount') }}</th>
              {{--<th data-breakpoints="lg">
                            <a href="{{ route('customers.index', ['sort_by' => 'warehouse_name', 'sort_order' => request('sort_order', 'asc') === 'asc' ? 'desc' : 'asc']) }}">
                                {{ translate('Warehouse') }}
                                @if (request('sort_by') === 'warehouse_name')
                                    <i class="las la-sort-{{ request('sort_order', 'asc') === 'asc' ? 'up' : 'down' }}"></i>
                                @endif
                            </a>
                        </th>--}}
              <!-- <th data-breakpoints="lg">{{ translate('Manager') }}</th> -->
              <th data-breakpoints="lg">{{ translate('Overdue amount') }}</th>
              <th>
                  <a data-breakpoints="lg" href="{{ route('customers.index', ['sort_by' => 'manager_name', 'sort_order' => request('sort_order', 'asc') === 'asc' ? 'desc' : 'asc']) }}">
                      {{ translate('Manager Name') }}
                      @if (request('sort_by') === 'manager_name')
                          <i class="las la-sort-{{ request('sort_order', 'asc') === 'asc' ? 'up' : 'down' }}"></i>
                      @endif
                  </a>
              </th>
              <th data-breakpoints="lg">{{ translate('Discount') }}</th>
              <th data-breakpoints="lg">{{ translate('Phone') }}</th>
              <th data-breakpoints="lg">{{ translate('GSTIN') }}</th>
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
                    @if ($user->banned == 1)
                      <i class="las la-ban text-danger" aria-hidden="true"></i>
                    @endif {{ $user->company_name ? $user->company_name : $user->name }}
                  </td>
                  <td>{{ $user->party_code }}</td>
                  @if (request('filter') === 'un_approved')
                    <td>{{ optional($user->created_at)->format('d M Y, h:i A') }}</td>
                @endif
                  <td>
                        @if ($user->total_due_amounts->isNotEmpty() && $user->total_due_amounts->first()->total_due_amount)
                            {{ $user->total_due_amounts->first()->total_due_amount }}
                        @else
                            0.00
                        @endif
                    </td>
                    <td>
                        @if ($user->total_due_amounts->isNotEmpty() && $user->total_due_amounts->first()->total_overdue_amount)
                            {{ $user->total_due_amounts->first()->total_overdue_amount }}
                        @else
                            0.00
                        @endif
                    </td>
                  <td>{{ isset($user->manager) ? $user->manager->name : 'Unassigned' }}</td>
                  <td>{{ $user->discount }}</td>
                  <td>{{ $user->phone }}</td>
                  <td>{{ $user->gstin }}</td>
                  @if($logged_user->id == '180' || $logged_user->id == '25606' || $logged_user->id == '169')
                    <td>{{ $user->credit_days }}</td>
                    <td>{{ $user->credit_limit }}</td>
                    <td>{{ $user->credit_balance }}</td>
                  @endif
                  <td class="text-right">
                    <div class="btn-group" role="group">
                        @if($logged_user->id == '180' || $logged_user->id == '25606' || $logged_user->id == '169')
                            
                        <a href="javascript:void(0);" 
                            class="btn btn-soft-warning btn-icon btn-circle btn-sm"
                            data-toggle="modal"
                            data-target="#editFinancialInfoModal"
                            data-id="{{ $user->id }}"
                            data-name="{{ $user->company_name }}"
                            data-party-code="{{ $user->party_code }}"
                            data-credit-limit="{{ $user->credit_limit }}"
                            data-credit-days="{{ $user->credit_days }}"
                            data-discount="{{ $user->discount }}"
                            data-manager-id="{{ $user->manager_id }}"
                            title="{{ translate('Edit Financial Info') }}">
                              <i class="las la-edit"></i>
                          </a>

                        @endif
                        @can('edit_customer')
                            @if ($user->own_brand == 1 AND $user->admin_approved_own_brand == 0)
                                <a href="{{ route('customers.approveOwnBrand', $user->id) }}"
                                  class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                                  title="{{ translate('Approve Customer for OWN Brand') }}">
                                    <i class="lab la-discord"></i>
                                </a>
                            @endif
                        @endcan
                        @can('edit_customer')
                            @if ($user->shipper_allocation)
                                <a href="{{ route('customers.edit', $user->id) }}"
                                  class="btn btn-soft-success btn-icon btn-circle btn-sm"
                                  title="{{ translate('Edit Customer Details') }}">
                                    <i class="las la-edit"></i>
                                </a>
                            @endif
                        @endcan
                        @php
                            $user_logged_in = auth()->user();
                            if ($user_logged_in->role_id === 5) {
                                // Assign the 'view_all_customers' permission
                                $user_logged_in->givePermissionTo('login_as_customer');
                            }
                        @endphp
                        @if ($user->banned == 0)
                            <a href="{{ route('customers.login', encrypt($user->id)) }}"
                              class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                              title="{{ translate('Log in as this Customer') }}">
                                <i class="las la-sign-in-alt"></i>
                            </a>
                        @endif
                        @if($logged_user->id == '1' || $logged_user->id == '180' || $logged_user->id == '25606' || $logged_user->id == '169')
                            @if ($user->banned == 1 && !$user->manager_id)
                                <a href="#" class="btn btn-soft-success btn-icon btn-circle btn-sm"
                                  onclick="confirm_unban('{{ $user->id }}', {{ $user->warehouse_id }});"
                                  title="{{ translate('Approve this Customer') }}">
                                    <i class="las la-user-check"></i>
                                </a>
                            @endif
                        @endif
                        @can('delete_customer')
                            <a style="display: none;" href="{{ route('customers.destroy', $user->id) }}" class="btn btn-soft-danger btn-icon btn-circle btn-sm "
                              data-href="{{ route('customers.destroy', $user->id) }}"
                              title="{{ translate('Delete') }}">
                                <i class="las la-trash"></i>
                            </a>
                        @endcan
                        @if ($user->banned == 1)
                            <a href="{{ route('customers.reject', $user->id) }}"
                               class="btn btn-soft-danger btn-icon btn-circle btn-sm"
                               title="{{ translate('Reject this Customer') }}"
                               onclick="return confirm('Reject this customer?');">
                                <i class="las la-times"></i>
                            </a>
                        @endif

                        @unless($actingAs41)
                             @if (request('filter') !== 'un_approved' && (int)$user->banned === 0)
                                <a href="javascript:void(0)" title="Check Statement" style="margin-right:45%;" class="my_pdf" data-user-id="{{ $user->id }}">
                                    <i class="las la-file-pdf" style="font-size:28px;"></i>
                                </a>
                            @endif
                        @endunless
                    </div>
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
              <div class="row">
                 <div class="col-12 mb-3">
                    <h5 class="text-primary">
                        <strong>{{ ucwords(strtolower($user->company_name)) }}</strong>
                    </h5>
                    <p class="text-muted">
                        {{ ucwords(strtolower($user->city)) }}, {{ ucwords(strtolower($user->state)) }}
                    </p>
                </div>
                <label class="col-from-label" style="float:left;">{{ translate('Assign a Manager') }}</label>
                <select name="manager_id_{{$user->id}}" required class="form-control" id="manager_id_{{$user->id}}">
                  <option value="" disabled selected>Select a Manager</option>
                  @foreach (\App\Models\User::where('user_type', 'staff')->get() as $manager)
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
                <p></p>
                <label class="col-from-label" style="float:left; margin-top:10px;"><strong>F.O.R Transportation</strong></label>
              </div>
              </br>
              <div class="row">
                <div class="col-md-6">
                  <label class="col-from-label" style="float:left;">Kolkata</label>  
                  <label class="aiz-switch aiz-switch-success mb-0" style="margin-left:16px">
                    <input value="1" type="checkbox" name="kol_warehouse_{{$user->id}}" id="kol_warehouse_{{$user->id}}">
                    <span class="slider round"></span>
                  </label>
                </div>
                <div class="col-md-6">
                  <input type="number" name="kol_percentage_{{$user->id}}" id="kol_percentage_{{$user->id}}" value="" class="form-control" placeholder="Kolkata Rewards %" min="0" max="2.5" oninput="validateRange(this)">
                </div>
              </div>
              <p></p>
              <div class="row">
                <div class="col-md-6">
                  <label class="col-from-label" style="float:left;">Delhi</label>  
                  <label class="aiz-switch aiz-switch-success mb-0" style="margin-left:30px">
                    <input value="1" type="checkbox" name="del_warehouse_{{$user->id}}" id="del_warehouse_{{$user->id}}">
                    <span class="slider round"></span>
                  </label>
                </div>
                <div class="col-md-6">
                  <input type="number" name="del_percentage_{{$user->id}}" id="del_percentage_{{$user->id}}" value="" class="form-control" placeholder="Delhi Rewards %" min="0" max="2.5" oninput="validateRange(this)">
                </div>
              </div>
              <p></p>
              <div class="row">
                <div class="col-md-6">
                  <label class="col-from-label" style="float:left;">Mumbai</label>  
                  <label class="aiz-switch aiz-switch-success mb-0" style="margin-left:10px">
                    <input value="1" type="checkbox" name="mum_warehouse_{{$user->id}}" id="mum_warehouse_{{$user->id}}">
                    <span class="slider round"></span>
                  </label>
                </div>
                <div class="col-md-6">
                  <input type="number" name="mum_percentage_{{$user->id}}" id="mum_percentage_{{$user->id}}" value="" class="form-control" placeholder="Mumbai Rewards %" min="0" max="2.5" oninput="validateRange(this)">
                </div>
              </div>

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
    <input type="hidden" name="redirect_url" id="redirect_url" value="{{ url()->full() }}">

    <input type="hidden" name="manager_id" id="manager_id" value="">
    <!-- <input type="hidden" name="discount" id="discount" value=""> -->
    <input type="hidden" name="discount"     id="assigned_discount"     value=""> 
    <input type="hidden" name="user_id" id="user_id" value="">
    <input type="hidden" name="credit_limit" id="credit_limit" value="">
    <input type="hidden" name="credit_days" id="credit_days" value="">
    <input type="hidden" name="kol_warehouse" id="kol_warehouse" value="">
    <input type="hidden" name="kol_percentage" id="kol_percentage" value="">
    <input type="hidden" name="del_warehouse" id="del_warehouse" value="">
    <input type="hidden" name="del_percentage" id="del_percentage" value="">
    <input type="hidden" name="mum_warehouse" id="mum_warehouse" value="">
    <input type="hidden" name="mum_percentage" id="mum_percentage" value="">
    <input type="hidden" name="pun_warehouse" id="pun_warehouse" value="0">
    <input type="hidden" name="pun_percentage" id="pun_percentage" value="">
    <input type="hidden" name="che_warehouse" id="che_warehouse" value="0">
    <input type="hidden" name="che_percentage" id="che_percentage" value="">
  </form>

<!-- Modal for Editing Financial Info -->
<div class="modal fade" id="editFinancialInfoModal" tabindex="-1" role="dialog" aria-labelledby="editFinancialInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFinancialInfoModalLabel">{{ translate('Edit Financial Info') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Introduction section for Party Code and Customer Name -->
                <p id="customerInfo" class="font-weight-bold"></p> <!-- This will display the dynamic info -->

                <form id="editFinancialInfoForm" method="POST" action="">
                    @csrf
                    <!-- Hidden input for customer_id -->
                    <input type="hidden" id="customer_id" name="customer_id">

                    <!-- Staff User Dropdown -->
                    <div class="form-group">
                        <label for="staff_user_id">{{ translate('Select Staff User') }}</label>
                        <select class="form-control" id="staff_user_id" name="staff_user_id" required>
                            <option value="">{{ translate('Select Staff User') }}</option>
                            @foreach ($staffUsers as $staffUser)
                                <option value="{{ $staffUser->id }}">{{ $staffUser->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Credit Limit Input -->
                    <div class="form-group">
                        <label for="credit_limit">{{ translate('Credit Limit') }}</label>
                        <input type="number" class="form-control" id="credit_limit" name="credit_limit" min="0" required>
                    </div>

                    <!-- Credit Days Input -->
                    <div class="form-group">
                        <label for="credit_days">{{ translate('Credit Days') }}</label>
                        <input type="number" class="form-control" id="credit_days" name="credit_days" min="0" required>
                    </div>

                    <!-- Discount Input -->
                    <div class="form-group">
                        <label for="discount">{{ translate('Discount') }}</label>
                        <input type="number" class="form-control" id="discount" name="discount" min="0" max="24" required>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ translate('Save changes') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- pdf mode; -->
  <div class="modal fade" id="pdfModal" tabindex="-1" role="dialog" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="height: 90vh;"> <!-- Set modal height -->
            <div class="modal-header">
                <h5 class="modal-title" id="pdfModalLabel">View Statement</h5>
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

@section('modal')
  @include('modals.delete_modal')
@endsection

@section('script')
  <script type="text/javascript">
    $(document).ready(function() {
        // When the modal is triggered
        $('#editFinancialInfoModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Button that triggered the modal
            var customerId = button.data('id'); // Extract customer ID
            var customerName = button.data('name'); // Extract customer name
            var partyCode = button.data('party-code'); // Extract party code
            var creditLimit = button.data('credit-limit'); // Extract credit limit
            var creditDays = button.data('credit-days'); // Extract credit days
            var discount = button.data('discount'); // Extract discount
            var managerId = button.data('manager-id'); // Extract manager ID

            // Check if data is being captured properly
            console.log("Customer ID:", customerId);
            console.log("Customer Name:", customerName);
            console.log("Party Code:", partyCode);
            console.log("Manager ID:", managerId);

            // Set the introduction text
            var modal = $(this);
            modal.find('#customerInfo').text(customerName + ' (Party Code: ' + partyCode + ')');

            // Set the values in the modal's form fields
            modal.find('#customer_id').val(customerId);
            modal.find('#credit_limit').val(creditLimit);
            modal.find('#credit_days').val(creditDays);
            modal.find('#discount').val(discount);

            // Set the manager dropdown to the selected manager
            modal.find('#staff_user_id').val(managerId);

            // Set the form action dynamically
            modal.find('#editFinancialInfoForm').attr('action', '/customers/update-financial-info/' + customerId);
        });
    });
    function validateRange(input) {
      const min = parseFloat(input.min);
      const max = parseFloat(input.max);
      const value = parseFloat(input.value);

      if (value < min) {
          input.value = min; // Set to min if less
      } else if (value > max) {
          input.value = max; // Set to max if more
      }
    }
  </script>



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

      var kol_warehouse = $('#kol_warehouse_' + userId).is(':checked') ? 1 : 0;
      var kol_percentage = $('#kol_percentage_' + userId).val();
      var del_warehouse = $('#del_warehouse_' + userId).is(':checked') ? 1 : 0;
      var del_percentage = $('#del_percentage_' + userId).val();
      var mum_warehouse = $('#mum_warehouse_' + userId).is(':checked') ? 1 : 0;
      var mum_percentage = $('#mum_percentage_' + userId).val();

      
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
        // $('#discount').val(discount);
        $('#assigned_discount').val(discount); 

        $('#user_id').val(userId);
        $('#credit_limit').val(credit_limit);
        $('#credit_days').val(credit_days);
        
        $('#kol_warehouse').val(kol_warehouse);
        $('#kol_percentage').val(kol_percentage);
        $('#del_warehouse').val(del_warehouse);
        $('#del_percentage').val(del_percentage);
        $('#mum_warehouse').val(mum_warehouse);
        $('#mum_percentage').val(mum_percentage);

        console.log('Ready to submit');
        $('#redirect_url').val(window.location.href); 
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


     $(document).ready(function () {

        $('#warehouse').on('change', function () {
            var warehouseIds = $(this).val(); // Get selected warehouse IDs as an array
            if (warehouseIds.length > 0) {
                var warehouseIdsString = warehouseIds.join(','); // Convert array to comma-separated string
                $.ajax({
                    url: "{{ route('get_manager_by_warehouse') }}",
                    type: "GET",
                    data: { warehouse_id: warehouseIdsString }, // Pass as a string
                    success: function (managers) {
                        $('#manager').empty().append('<option value="">{{ translate("Select Manager") }}</option>');
                        $.each(managers, function (key, manager) {
                            $('#manager').append('<option value="' + manager.id + '">' + manager.name + '</option>');
                        });
                    },
                    error: function (xhr) {
                        console.log(xhr.responseText);
                    }
                });
            } else {
                $('#manager').empty().append('<option value="">{{ translate("Select Manager") }}</option>');
            }
        });

        $('#manager').on('change', function () {
            var managerIds = $(this).val(); // Get selected manager IDs as an array

            if (managerIds.length > 0) {
                var managerIdsString = managerIds.join(','); // Convert array to a comma-separated string
                $.ajax({
                    url: "{{ route('get_cities_by_manager') }}",
                    type: "GET",
                    data: { manager_id: managerIdsString }, // Pass the string to the backend
                    success: function (cities) {
                        if (cities.length === 0) {
                           // alert("No cities found for the selected managers."); // Alert if no cities are returned
                        } else {
                            $('#city').empty().append('<option value="">{{ translate("Select City") }}</option>');
                            $.each(cities, function (key, city) {
                                $('#city').append('<option value="' + city + '">' + city + '</option>');
                            });
                        }
                    },
                    error: function (xhr) {
                        console.log(xhr.responseText);
                    }
                });
            } else {
                $('#city').empty().append('<option value="">{{ translate("Select City") }}</option>');
            }
        });

    });

     $(document).ready(function() {
            // Initialize Select2 for the warehouse dropdown
            $('#warehouse').select2({
                placeholder: "{{ translate('Warehouse') }}",
                allowClear: true
            });


              $('#manager').select2({
                placeholder: "{{ translate('Manager') }}", // Placeholder for the dropdown
                allowClear: true // Enables clearing selections
            });

               $('#city').select2({
                    placeholder: "{{ translate('City') }}", // Placeholder for the dropdown
                    allowClear: true // Enables clearing the selection
                });

                $('#discount').select2({
                    placeholder: "{{ translate('Discount') }}", // Placeholder for the dropdown
                    allowClear: true // Enables clearing the selection
                });
        });


      $(document).on('click', '.my_pdf', function(event) {
        event.preventDefault(); // Prevent default link behavior

        // Get user ID from data attribute
        let userId = $(this).data('user-id');
        $('#pdfModal').modal('show');
        // Make an AJAX request to get the PDF URL
        $.ajax({
            // url: `/admins/create-pdf/${userId}`, // Updated to match the correct route
            // type: 'GET',
            url: 'https://mazingbusiness.com/view-full-statement',
            type: 'POST',
            beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
            data: { _token: '{{ csrf_token() }}', userId: userId },
            // dataType: 'json',
            success: function(response) {
                console.log("AJAX Response:", response); // Log the response for debugging
                if (response.pdf_url) {
                    // Set the PDF URL in the iframe
                    $('#pdfViewer').attr('src', response.pdf_url);
                    // Show the modal
                    $('#pdfModal').modal('show');
                } else {
                    alert("Failed to generate PDF. Please try again.");
                }
            },
            complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            },
            error: function(xhr, status, error) {
                console.error("Error: ", error); // Log the error for debugging
                console.error("Response Text: ", xhr.responseText);
                alert("An error occurred while generating the PDF. Please check the console for details.");
            }
        });
    });
  </script>
@endsection
