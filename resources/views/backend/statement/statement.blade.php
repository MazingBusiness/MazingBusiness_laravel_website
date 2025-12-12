@extends('backend.layouts.app')

@section('content')
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>

.sync-button {
    background-color: #007bff; /* Blue color for Sync Button */
    color: white;
    border: none;
    padding: 10px 20px;
    font-size: 13px;
    cursor: pointer;
    border-radius: 42px;
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 149px; /* Same size as WhatsApp button */
    height: 45px; /* Set fixed height */
}
.sync-button .loader {
    border: 4px solid rgba(255, 255, 255, 0.3); /* Light white with transparency */
    border-top: 4px solid white; /* Solid white */
    border-radius: 50%;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
    display: none; /* Initially hidden */
    position: absolute;
}

.sync-button .button-text {
    visibility: visible; /* Initially visible */
}

/* Add loader animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
    .d-inline-flex {
        display: inline-flex;
        align-items: center;
    }

    .btn-icon {
        margin-right: 5px;
    }

    .table td {
        vertical-align: middle;
    }

    .whatsapp-button {
        background-color: #25D366;
        color: white;
        border: none;
        padding: 10px 20px;
        font-size: 13px;
        cursor: pointer;
        border-radius: 42px;
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 123px;
        height: 45px;
    }

    .whatsapp-button .loader {
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-top: 4px solid white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        animation: spin 1s linear infinite;
        display: none;
        position: absolute;
    }

    .whatsapp-button .button-text {
        visibility: visible;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .button-group {
        display: flex;
        gap: 10px;
    }

    .small-column {
        width: 100px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    @media (max-width: 767.98px) {
        .whatsapp-button {
            width: 150px;
            font-size: 14px;
        }

        .table th, .table td {
            white-space: nowrap;
        }

        .small-column {
            width: 80px;
        }
    }

.notify-manager-button {
    background-color: #ff9800; /* Orange color for Notify Button */
    color: white;
    border: none;
    padding: 10px 20px;
    font-size: 12px;
    cursor: pointer;
    border-radius: 42px;
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 149px; /* Same size as Sync button */
    height: 45px;
}

.notify-manager-button .loader {
    border: 4px solid rgba(255, 255, 255, 0.3); /* Light white with transparency */
    border-top: 4px solid white; /* Solid white */
    border-radius: 50%;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
    display: none; /* Initially hidden */
    position: absolute;
}

.notify-manager-button .button-text {
    visibility: visible; /* Initially visible */
}

 /* Custom Date Picker Styling */
    .custom-date-picker {
        padding: 10px;
        font-size: 14px;
        border: 2px solid #ced4da;
        border-radius: 8px;
        width: 100%;
        background-color: #fff;
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        color: #495057;
        outline: none;
        appearance: none; /* Remove default date picker arrow */
        position: relative;
    }

    .custom-date-picker::-webkit-calendar-picker-indicator {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        height: 20px;
        width: 20px;
        opacity: 0.5;
    }

    /* On hover effect */
    .custom-date-picker:hover {
        border-color: #80bdff;
        background-color: #f8f9fa;
    }

    /* On focus effect */
    .custom-date-picker:focus {
        border-color: #80bdff;
        background-color: #fff;
        outline: none;
        box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
    }

    /* Adjust date picker icon visibility */
    .custom-date-picker::-webkit-calendar-picker-indicator:hover {
        opacity: 1;
    }
</style>
<div class="aiz-titlebar text-left mt-2 mb-3 d-flex justify-content-between">
    <div class="align-items-center">
        <h1 class="h3">Statement</h1>
    </div>
    <div class="button-group">
        <div class="whatsapp-button-container">
            <button type="button" class="whatsapp-button btn btn-sm" id="whatsapp-all">
                <span class="button-text"><i class="fab fa-whatsapp"></i> WhatsApp</span>
                <div class="loader"></div>
            </button>
            <input type="hidden" name="" value="{{$totalCustomersWithDueOrOverdue}}" id="total_customer_with_due_overdue">
        </div>
        <!-- <div class="whatsapp-button-container">
            <button type="button" class="whatsapp-button" id="whatsapp-checked">
                <span class="button-text"><i class="fab fa-whatsapp"></i> WhatsApp Checked</span>
                <div class="loader"></div>
            </button>
        </div> -->

        <!-- Notify Manager Button -->
        <div class="notify-manager-button-container">
    <button type="button" class="notify-manager-button btn btn-sm" id="notify-manager">
        <span class="button-text"><i class="fas fa-bell"></i> Notify Manager</span>
        <div class="loader" style="display: none;"></div>
    </button>
</div>

        <!-- Sync Checked Button -->
        <div class="sync-button-container">
            <button type="button" class="sync-button btn btn-sm" id="sync-checked">
                <span class="button-text"><i class="fas fa-sync"></i> Sync Checked</span>
                <div class="loader"></div>
            </button>
        </div>

         <!-- Add the Export Button -->
         <div class="export-button-container">
            <form method="GET" action="{{ route('adminExportStatement') }}">
                <input type="hidden" name="warehouse_id" value="{{ request()->warehouse_id }}">
                <input type="hidden" name="manager_id"  value="{{ request()->manager_id }}">
                <input type="hidden" name="city_id"     value="{{ request()->city_id }}">
                <input type="hidden" name="search"      value="{{ request()->search }}">
                <input type="hidden" name="duefilter"   value="{{ request()->duefilter }}">

                {{-- optional: sort bhi bhejna ho to --}}
                <input type="hidden" name="sort_by"     value="{{ request()->sort_by }}">
                <input type="hidden" name="sort_order"  value="{{ request()->sort_order }}">

                {{-- anti-cache token on GET --}}
                <input type="hidden" name="_ts" value="{{ now()->timestamp }}">

                <button type="submit" style="border-radius:42px;background-color:#6A5ACD;" class="btn btn-success">
                    <i class="fas fa-file-export"></i> Export
                </button>
            </form>
        </div>

        
    </div>
</div>
<div class="card">
   <!--  <div class="card-header">
        <h3 class="h3">Statement</h3>
    </div> -->

    <div class="card-body">
        <!-- Search and Filters -->
        <form method="GET" action="{{ route('adminStatement') }}">
            <div class="row gutters-5 mb-4">
                <!-- Search -->
                <div class="col-md-3 position-relative">
                    <label class="form-label font-weight-bold">Search</label>
                    <div class="input-group">
                        <input type="text" name="search" id="search-input" class="form-control" placeholder="Party Code, Name, or City" value="{{ request()->search }}">
                        @if(request()->has('search') && request()->search != '')
                            <button type="submit" name="clear" value="1" class="btn btn-outline-secondary">&times;</button>
                        @endif
                    </div>
                </div>

                @php
                    $allowedUserIds = [1, 180, 169, 25606];
                    $loggedInUserId = auth()->user()->id;
                    $loggedInUserWarehouseId = auth()->user()->warehouse_id;
                @endphp

                <!-- Branch Selection -->
                <div class="col-md-2">
                    <label class="form-label font-weight-bold">Branch</label>
                    <select class="form-control" name="warehouse_id" id="branch-select">
                        @if(in_array($loggedInUserId, $allowedUserIds))
                            <option value="">Select Branch</option>
                            @foreach($warehouses as $warehouse)
                                @if($warehouse->id != 3 && $warehouse->id != 5)
                                    <option value="{{ $warehouse->id }}"
                                        {{ request()->warehouse_id == $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->name }}
                                    </option>
                                @endif
                            @endforeach
                        @else
                            @foreach($warehouses as $warehouse)
                                @if($warehouse->id == $loggedInUserWarehouseId)
                                    <option value="{{ $warehouse->id }}" selected>
                                        {{ $warehouse->name }}
                                    </option>
                                @endif
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Manager Selection -->
                <div class="col-md-2">
                    <label class="form-label font-weight-bold">Manager</label>
                    <select class="form-control" name="manager_id" id="manager-select">
                        @if(in_array(auth()->user()->id, [1, 180, 169, 25606]))
                            <option value="">Select Manager</option>
                        @endif
                
                        @foreach($managers as $manager)
                            <option value="{{ $manager->id }}"
                                {{ request()->manager_id
                                    ? (request()->manager_id == $manager->id ? 'selected' : '')
                                    : (!in_array(auth()->user()->id, [1, 180, 169, 25606]) && auth()->user()->id == $manager->id ? 'selected' : '') }}>
                                {{ $manager->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- City Selection -->
                <div class="col-md-2">
                    <label class="form-label font-weight-bold">City</label>
                    <select class="form-control" name="city_id" id="city-select">
                        <option value="">Select City</option>
                        <!-- Cities will be populated dynamically -->
                    </select>
                </div>

                <!-- Due Filter -->
                <div class="col-md-2">
                    <label class="form-label font-weight-bold">Due Filter</label>
                    <select class="form-control" name="duefilter">
                        <option value="">Select Due Filter</option>
                        <option value="due" {{ request()->duefilter == 'due' ? 'selected' : '' }}>Due</option>
                        <option value="overdue" {{ request()->duefilter == 'overdue' ? 'selected' : '' }}>Overdue</option>

                        <option value="overdue_60" {{ request()->duefilter == 'overdue_60' ? 'selected' : '' }}>60+ days</option>

                        <option value="overdue_90" {{ request()->duefilter == 'overdue_90' ? 'selected' : '' }}>90+ days</option>
                         <option value="overdue_120" {{ request()->duefilter == 'overdue_120' ? 'selected' : '' }}>120+ days</option>
                    </select>
                </div>

                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm mt-4">Filters</button>
                </div>
            </div>
        </form>

        <!-- Total Due and Overdue Amount -->
        <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:12px;">

          {{-- Total Due --}}
          <div style="flex:1 1 260px; background:#fff; border:1px solid #eef1f5; border-radius:12px; padding:14px 16px; box-shadow:0 2px 6px rgba(7,78,134,.06); display:flex; align-items:center;">
            <span style="display:inline-flex; width:40px; height:40px; border-radius:50%; align-items:center; justify-content:center; margin-right:12px; background:#e8f2ff; color:#2b6cb0;">
              <i class="fas fa-wallet"></i>
            </span>
            <div>
              <div style="font-size:12px; color:#6c757d; letter-spacing:.3px;">Total Due Amount</div>
              <div style="font-size:18px; font-weight:700;">₹{{ number_format($totalDueAmount, 2) }}</div>
            </div>
          </div>

          {{-- Overall Overdue --}}
          <div style="flex:1 1 260px; background:#fff; border:1px solid #feecec; border-radius:12px; padding:14px 16px; box-shadow:0 2px 6px rgba(197,48,48,.08); display:flex; align-items:center;">
            <span style="display:inline-flex; width:40px; height:40px; border-radius:50%; align-items:center; justify-content:center; margin-right:12px; background:#ffe8e8; color:#c53030;">
              <i class="fas fa-exclamation-circle"></i>
            </span>
            <div>
              <div style="font-size:12px; color:#a94442; letter-spacing:.3px;">Total Overdue Amount</div>
              <div style="font-size:18px; font-weight:700;">₹{{ number_format($totalOverdueAmount, 2) }}</div>
            </div>
          </div>

          {{-- Threshold Overdue (60+/90+/120+) – only when active --}}
          @if(!empty($overdueBucketThreshold))
            <div style="flex:1 1 260px; background:#fff; border:1px solid #efe9ff; border-radius:12px; padding:14px 16px; box-shadow:0 2px 6px rgba(111,66,193,.08); display:flex; align-items:center;">
              <span style="display:inline-flex; width:40px; height:40px; border-radius:50%; align-items:center; justify-content:center; margin-right:12px; background:#efe9ff; color:#6f42c1;">
                <i class="fas fa-hourglass-half"></i>
              </span>
              <div>
                <div style="font-size:12px; color:#6f42c1; letter-spacing:.3px;">
                  Overdue {{ $overdueBucketThreshold }}+ Amount
                </div>
                <div style="font-size:18px; font-weight:700;">
                  ₹{{ number_format($totalOverdueBucketAmount ?? 0, 2) }}
                </div>
              </div>
            </div>
          @endif

        </div>



        @if($customers->count() > 0)
            <div class="table-responsive">
                <table class="table aiz-table mb-0" style="font-size: 11px;">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>#</th>
                            <th>Party Name</th>
                            <!-- <th>Party Code</th> -->
                            @if(isset($overdueBucketThreshold) && $overdueBucketThreshold)
                                <th>
                                    <a href="{{ request()->fullUrlWithQuery([
                                            'sort_by'    => 'overdue_bucket_amount',
                                            'sort_order' => request('sort_by') === 'overdue_bucket_amount' && request('sort_order') === 'asc' ? 'desc' : 'asc'
                                    ]) }}">
                                        Overdue {{ $overdueBucketThreshold }}+ Amount
                                        @if(request('sort_by') === 'overdue_bucket_amount')
                                            <i class="fas fa-sort-{{ request('sort_order') === 'asc' ? 'up' : 'down' }}"></i>
                                        @else
                                            <i class="fas fa-sort"></i>
                                        @endif
                                    </a>
                                </th>
                            @endif
                            <th>Phone</th>
                           <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'credit_days', 'sort_order' => request('sort_order') == 'asc' ? 'desc' : 'asc']) }}">
                                    Credit Days
                                    @if(request('sort_by') == 'credit_days')
                                        <i class="fas fa-sort-{{ request('sort_order') == 'asc' ? 'up' : 'down' }}"></i>
                                    @else
                                        <i class="fas fa-sort"></i>
                                    @endif
                                </a>
                            </th>

                            <!-- Sorting for Credit Limit -->
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'credit_limit', 'sort_order' => request('sort_order') == 'asc' ? 'desc' : 'asc']) }}">
                                    Credit Limit
                                    @if(request('sort_by') == 'credit_limit')
                                        <i class="fas fa-sort-{{ request('sort_order') == 'asc' ? 'up' : 'down' }}"></i>
                                    @else
                                        <i class="fas fa-sort"></i>
                                    @endif
                                </a>
                            </th>
                            <th>Manager</th>
                            <th>Warehouse</th>
                             <!-- Sorting for City -->
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'city', 'sort_order' => request('sort_order') == 'asc' ? 'desc' : 'asc']) }}">
                                    City
                                    @if(request('sort_by') == 'city')
                                        <i class="fas fa-sort-{{ request('sort_order') == 'asc' ? 'up' : 'down' }}"></i>
                                    @else
                                        <i class="fas fa-sort"></i>
                                    @endif
                                </a>
                            </th>

                            <!-- Sorting for Due Amount -->
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'due_amount_numeric', 'sort_order' => request('sort_order') == 'asc' ? 'desc' : 'asc']) }}">
                                    Due Amount
                                    @if(request('sort_by') == 'due_amount_numeric')
                                        <i class="fas fa-sort-{{ request('sort_order') == 'asc' ? 'up' : 'down' }}"></i>
                                    @else
                                        <i class="fas fa-sort"></i>
                                    @endif
                                </a>
                            </th>

                            <!-- Sorting for Overdue Amount -->
                            <th>
                                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'overdue_amount_numeric', 'sort_order' => request('sort_order') == 'asc' ? 'desc' : 'asc']) }}">
                                    Overdue Amount
                                    @if(request('sort_by') == 'overdue_amount_numeric')
                                        <i class="fas fa-sort-{{ request('sort_order') == 'asc' ? 'up' : 'down' }}"></i>
                                    @else
                                        <i class="fas fa-sort"></i>
                                    @endif
                                </a>
                            </th>
                             <th>Action</th> <!-- New Column -->
                        </tr>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $index => $customer)
                            <tr data-party-code="{{ $customer->acc_code }}" 
                            data-due-amount="{{ $customer->due_amount_numeric }}" 
                            data-overdue-amount="{{ $customer->overdue_amount_numeric }}" 
                            data-user-id="{{ $customer->id }}">
                                <td>
                                    <input type="checkbox" class="select-person" 
                                     data-manager-id="{{ $customer->manager_id }}" 
                                        data-party-code="{{ $customer->acc_code }}" 
                                        data-due-amount="{{ $customer->due_amount_numeric }}" 
                                        data-overdue-amount="{{ $customer->overdue_amount_numeric }}" 
                                        data-user-id="{{ $customer->id }}">
                                </td>
                                <td>{{ $customers->firstItem() + $index }}</td>
                               <td>
                                    <div>{{ $customer->company_name }}</div>
                                    <div class="text-primary" style="font-size:11px; font-weight:600;">
                                        {{ $customer->acc_code }}
                                    </div>
                                </td>
                                <!-- <td>{{ $customer->acc_code }}</td> -->
                                @if(isset($overdueBucketThreshold) && $overdueBucketThreshold)
                                    <td>₹{{ number_format($customer->overdue_bucket_amount ?? 0, 2) }}</td>
                                @endif
                                <td>{{ $customer->phone }}</td>
                                <td class="credit-days">{{ $customer->credit_days }}</td>
                                <td class="credit-limit">{{ $customer->credit_limit }}</td>
                                <td class="manager-name">{{ $customer->manager_name ?? 'N/A' }}</td>
                                <td>{{ $customer->warehouse_name ?? 'N/A' }}</td>
                                <td>{{ $customer->city }}</td>
                                <td>₹{{ number_format($customer->due_amount_numeric, 2) }}</td>
                                <td>₹{{ number_format($customer->overdue_amount_numeric, 2) }}</td>

                                <td style="white-space: nowrap;">
                                <a href="#" 
                                   class="btn btn-primary btn-sm" 
                                   data-toggle="modal" 
                                   data-target="#commentModal" 
                                   data-party-code="{{ $customer->acc_code }}" 
                                   data-party-name="{{ $customer->company_name }}"
                                   data-user-id="{{ $customer->id }}"
                                   style="margin-right: 5px; padding: 6px 8px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-comment" style="font-size: 16px;"></i>
                                </a>
                                <a href="#" 
                                   class="btn btn-primary btn-sm my_pdf" 
                                   data-party-code="{{ $customer->acc_code }}" 
                                   data-party-name="{{ $customer->company_name }}"
                                   data-user-id="{{ $customer->id }}"
                                   style="padding: 6px 8px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-file-pdf" style="font-size: 16px;"></i>
                                </a>
                                <br>
                                <a href="{{ route('customers.login', encrypt($customer->id)) }}"
                                    class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                                    title="{{ translate('Log in as this Customer') }}">
                                    <i class="las la-sign-in-alt"></i>
                                </a>

                                   
                                <!-- Assign Manager Button -->
                                <button class="btn btn-success btn-sm assign-manager-btn" 
                                        data-user-id="{{ $customer->id }}" 
                                        data-party-code="{{ $customer->acc_code }}" 
                                        data-party-name="{{ $customer->company_name }}" 
                                        data-user-creditDays="{{ $customer->credit_days }}"
                                        data-user-creditLimit="{{ $customer->credit_limit }}"
                                        data-current-manager-id="{{ $customer->manager_id }}" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#assignModel" 
                                        title="Assign Manager"
                                        style="padding: 6px 8px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user-plus" style="font-size: 14px; margin-right: 0;"></i>
                                </button>
                                <br>
                                <button class="btn btn-warning btn-sm payment-link-btn" 
                                        data-user-id="{{ $customer->id }}" 
                                        data-party-code="{{ $customer->acc_code }}" 
                                        data-party-name="{{ $customer->company_name }}" 
                                        data-party-phone-number="{{ $customer->phone }}"
                                        data-party-due-amount="{{ number_format($customer->due_amount_numeric, 2, '.', '') }}"
                                        data-party-over-due-amount="{{ number_format($customer->overdue_amount_numeric, 2, '.', '') }}"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#paymentLinkModel" 
                                        title="Send payment link"
                                        style="padding: 6px 8px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                                    <i class="las la-rupee-sign" style="font-size: 14px; margin-right: 0;"></i>
                                </button>

                            </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-3">
                {{ $customers->links('pagination::bootstrap-4') }}
            </div>
        @else
            <div class="alert alert-warning">
                No records found.
            </div>
        @endif
    </div>
</div>


<!-- Modal -->
<div class="modal fade" id="commentModal" tabindex="-1" role="dialog" aria-labelledby="commentModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="commentModalLabel">Add Comment</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="commentForm">
          @csrf <!-- CSRF token for security -->
          <input type="hidden" name="user_id" id="user-id">
          <input type="hidden" name="party_code" id="party-code">
          
          <div class="form-group">
            <label for="party-name">Party Name</label>
            <input type="text" class="form-control" id="party-name" readonly>
          </div>
          <div class="form-group">
            <label for="comment">Comment</label>
            <textarea class="form-control" id="comment" name="statement_comment" rows="3"></textarea>
          </div>
          <div class="form-group">
            <label for="due_date">Date</label>
            <input type="date" class="form-control custom-date-picker" id="due-date-picker" name="statement_comment_date">
          </div>
          <button type="button" class="btn btn-primary" id="saveComment">Save Comment</button>
        </form>
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



<!-- Assign Manager Modal -->
<div class="modal fade" id="assignModel" tabindex="-1" role="dialog" aria-labelledby="assignModelLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assignModelLabel">Assign Manager</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="assignManagerForm">
          @csrf <!-- CSRF token for security -->
          <input type="hidden" name="user_id" id="user-id-assign">
          <div class="form-group">
            <label for="party-name">Party Name</label>
            <input type="text" class="form-control" id="party-name-assign" readonly>
          </div>
          <div class="form-group">
            <label for="assignmanager-select">Select Manager</label>
            <select class="form-control" name="manager_id" id="assignmanager-select">
              <option value="">Select Manager</option>
              @foreach($managers as $manager)
                  <option value="{{ $manager->id }}">{{ $manager->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label for="credit-days-assign">Credit Days</label>
            <input type="number" class="form-control" name="credit_days" id="credit-days-assign">
          </div>
          <div class="form-group">
            <label for="credit-limit-assign">Credit Limit</label>
            <input type="number" class="form-control" name="credit_limit" id="credit-limit-assign">
          </div>
          <button type="button" class="btn btn-primary" id="assignManagerForUser">Assign</button>
        </form>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="paymentLinkModel" tabindex="-1" role="dialog" aria-labelledby="assignModelLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assignModelLabel">Send Payment Link</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="paymentLinkSendForm">
          @csrf <!-- CSRF token for security -->
          <input type="hidden" name="user_id" id="pay-user-id-assign">
          <div class="form-group">
            <label for="party-name">Party Name</label>
            <input type="text" class="form-control" id="pay-party-name-assign" readonly>
          </div>
          <div class="form-group">
            <label for="party-name">Party Phone Number</label>
            <input type="text" class="form-control" id="party-phone-number" readonly>
          </div>
          <div class="form-group">
            <label for="assignmanager-select">Payment For</label>
            <select class="form-control" name="payment_for" id="payment_for">
              <option value="Due_Amount">Due Amount</option>
              <option value="Over_Due_Amount">Over Due Amount</option>
              <option value="Custom_Amount">Custom Amount</option>
            </select>
          </div>
          <div class="form-group">
            <label for="credit-days-assign">Payable Amount</label>
            <input type="number" class="form-control" name="payable_amount" id="payable_amount" readonly="true">
            <input type="hidden" class="form-control" name="due_amount" id="due_amount">
            <input type="hidden" class="form-control" name="ovedue_amount" id="ovedue_amount">

          </div>
          <input type="hidden" id="dueAmount" value="" />
          <input type="hidden" id="overdueAmount" value="" />
          <button type="button" class="btn btn-primary" id="sendPaymentLink">Send Payment Link</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@section('script')
<script>
    $(document).ready(function() {


         // ✅ Function to load cities and retain selected city
        var selectedCity = "{{ request('city_id') }}"; // Get selected city from request
        // If a manager is already selected on page load, fetch cities
        var managerId = $('#manager-select').val();
        if (managerId) {
            loadCities(managerId, selectedCity);
        }

        function loadCities(managerId, selectedCity) {
            $.ajax({
                url: "{{ route('get_cities_by_manager_statement') }}",
                type: "GET",
                data: { manager_id: managerId },
                success: function(response) {
                    $.each(response, function(index, city) {
                        $('#city-select').append('<option value="' + city + '">' + city + '</option>');
                    });

                    // ✅ Apply the selected city after cities are loaded
                    if (selectedCity) {
                        $('#city-select').val(selectedCity);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching cities:', error);
                }
            });
        }

         $(document).on('click', '#select-all', function() {
            var isChecked = $(this).prop('checked');
            $('.select-person').prop('checked', isChecked);
        });
        // Update Manager List when Branch is Selected
        $('#branch-select').on('change', function() {
            var warehouseId = $(this).val();
            $('#manager-select').empty().append('<option value="">Select Manager</option>');
            
            if (warehouseId) {
                $.ajax({
                    url: "{{ route('getManagersByWarehouse') }}",
                    type: "GET",
                    data: { warehouse_id: warehouseId },
                    success: function(response) {
                        $.each(response, function(key, manager) {
                            $('#manager-select').append('<option value="' + manager.id + '">' + manager.name + '</option>');
                        });
                    }
                });
            }
        });

   

        // Update City List when Manager is Selected
        $('#manager-select').on('change', function() {
            var managerId = $(this).val();
            $('#city-select').empty().append('<option value="">Select City</option>');

            if (managerId) {
                $.ajax({
                    url: "{{ route('get_cities_by_manager_statement') }}",
                    type: "GET",
                    data: { manager_id: managerId },
                    success: function(response) {
                        $.each(response, function(index, city) {
                            $('#city-select').append('<option value="' + city + '">' + city + '</option>');

                        });
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching cities:', error);
                    }
                });
            }
        });


         $(document).on('click', '.my_pdf', function(event) {
            event.preventDefault(); // Prevent default link behavior

            // Get user ID from data attribute
            let userId = $(this).data('party-code');

            // Make an AJAX request to get the PDF URL
            $.ajax({
                url: `/admins/create-pdf/${userId}`, // Updated to match the correct route
                type: 'GET',
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
                error: function(xhr, status, error) {
                    console.error("Error: ", error); // Log the error for debugging
                    console.error("Response Text: ", xhr.responseText);
                    alert("An error occurred while generating the PDF. Please check the console for details.");
                }
            });
        });


         // When Assign Manager button is clicked
        $(document).on('click', '.assign-manager-btn', function () {
            var userId = $(this).data('user-id');
            var partyName = $(this).data('party-name');
            var partyCode = $(this).data('party-code');
            var creditDays = $(this).data('user-creditdays');
            var creditLimit = $(this).data('user-creditlimit');
            var currentManagerId = $(this).data('current-manager-id'); // Add the current manager 

             $('#credit-days-assign').val(creditDays);
            $('#credit-limit-assign').val(creditLimit);
            // Populate modal fields
            $('#user-id-assign').val(userId);
            $('#party-name-assign').val(partyName);
            // Reset the manager dropdown
            $('#assignmanager-select').val('');
              // Set the current manager as selected in the dropdown
            $('#assignmanager-select').val(currentManagerId);
            
            // Open the modal
            $('#assignModel').modal('show');
        });

        // When Assign Manager button is clicked
        $(document).on('click', '.payment-link-btn', function () {
            var userId = $(this).data('user-id');
            var partyName = $(this).data('party-name');
            var partyPhoneNumber = $(this).data('party-phone-number');
            var dueAmount = $(this).data('party-due-amount');
            var overdueAmount = $(this).data('party-over-due-amount');

            // Populate fields
            $('#pay-user-id-assign').val(userId);
            $('#pay-party-name-assign').val(partyName);
            $('#party-phone-number').val(partyPhoneNumber);

            // Set default to due amount
            $('#payable_amount').val(dueAmount).prop('readonly', true);
            $('#payment_for').val('Due_Amount'); // reset dropdown to default

            $('#dueAmount').val(dueAmount);
            $('#overdueAmount').val(overdueAmount);

            // Store for reuse
            $('#paymentLinkModel')
                .data('due-amount', dueAmount)
                .data('overdue-amount', overdueAmount);

            $('#paymentLinkModel').modal('show');
        });

        $(document).on('change', '#payment_for', function () {
            var selected = $(this).val();
            var modal = $('#paymentLinkModel');
            var dueAmount = modal.data('due-amount');
            var overdueAmount = modal.data('overdue-amount');
            if (selected === 'Due_Amount') {
                $('#payable_amount').val(dueAmount).prop('readonly', true);
            } else if (selected === 'Over_Due_Amount') {
                $('#payable_amount').val(overdueAmount).prop('readonly', true);
            } else if (selected === 'Custom_Amount') {
                $('#payable_amount').val('').prop('readonly', false).focus();;
            }
        });

        // Handle Save button click
        $('#sendPaymentLink').on('click', function () {
            var userId     = $('#pay-user-id-assign').val();
            var paymentFor = $('#payment_for').val();
            var amount     = $('#payable_amount').val();
            var dueAmount     = $('#dueAmount').val();
            var overdueAmount     = $('#overdueAmount').val();

            $.ajax({
                url: '{{ route("zoho.payment.createPaymentLink") }}',
                method: 'POST',
                data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                user_id: userId,
                description: paymentFor,
                amount: amount,
                dueAmount: dueAmount,
                overdueAmount: overdueAmount,
                // Send boolean-ish; your backend already casts to boolean
                notify_user: '1'
                },
                success: function (response) {
                if (response.success) {
                    AIZ.plugins.notify('success', 'Payment link sent successfully.');
                    $('#paymentLinkModel').modal('hide');
                } else if (response.auth_required && response.auth_url) {
                    // Open Zoho consent in a new tab
                    var win = window.open(response.auth_url, '_blank');

                    // If popup blocked, fall back to full redirect
                    if (!win || win.closed || typeof win.closed === 'undefined') {
                        window.location.href = response.auth_url;
                        return;
                    }

                    AIZ.plugins.notify('warning', 'Please authorize Zoho in the new tab, then return here.');

                    // Optional: retry automatically after user finishes (simple manual retry button below)
                    // You can also add polling to check when token exists.
                } else {
                    alert(response.message || 'Error creating payment link. Please try again.');
                }
                },
                error: function (xhr) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.auth_required && res.auth_url) {
                        var win = window.open(res.auth_url, '_blank');
                        if (!win || win.closed || typeof win.closed === 'undefined') {
                            window.location.href = res.auth_url;
                            return;
                        }
                        AIZ.plugins.notify('warning', 'Please authorize Zoho in the new tab, then return here.');
                    } else {
                        alert((res && res.message) ? res.message : 'An error occurred. Please try again.');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
                }
            });
        });



        // Handle Save button click
        $('#assignManagerForUser').on('click', function () {
            var userId = $('#user-id-assign').val();
            var managerId = $('#assignmanager-select').val();
            var creditDays = $('#credit-days-assign').val();
            var creditLimit = $('#credit-limit-assign').val();


            if (!managerId) {
                alert('Please select a manager.');
                return;
            }

            $.ajax({
                url: '{{ route("assign.manager") }}', // Replace with your actual route
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    user_id: userId,
                    manager_id: managerId,
                    credit_days: creditDays,
                    credit_limit: creditLimit
                },
                success: function (response) {
                    if (response.success) {

                     // Update the corresponding button's data attributes
                      var button = $(`button[data-user-id="${userId}"]`);
                      button.data('current-manager-id', managerId);
                      button.data('user-creditdays', creditDays);
                      button.data('user-creditlimit', creditLimit);

                      $(`tr[data-user-id="${userId}"] td.manager-name`).text(`${response.manager.name}`);
                      $(`tr[data-user-id="${userId}"] td.credit-days`).text(creditDays);
                      $(`tr[data-user-id="${userId}"] td.credit-limit`).text(creditLimit);
                      AIZ.plugins.notify('success', 'Manager Assigned Successfully.');
                      $('#assignModel').modal('hide'); // Close the modal
                        // Reset modal fields
              
                       // location.reload(); // Reload the page to reflect changes
                    } else {
                        alert('Error assigning manager. Please try again.');
                    }
                },
                error: function (xhr, status, error) {
                    console.error(xhr.responseText);
                    alert('An error occurred. Please try again.');
                }
            });
        });


         // Sync Checked Button Handler
        $('#sync-checked').click(function() {
        
            var button = $(this);
            var loader = button.find('.loader');
            var buttonText = button.find('.button-text');

            

            buttonText.css('visibility', 'hidden');
            loader.show();
            button.prop('disabled', true);

            var selectedData = [];
            $('.select-person:checked').each(function() {
                selectedData.push({
                    party_code: $(this).data('party-code'),
                    user_id: $(this).data('user-id')
                });
            });

            if (selectedData.length === 0) {
                AIZ.plugins.notify('warning', 'Please select at least one record.');
                buttonText.css('visibility', 'visible');
                loader.hide();
                button.prop('disabled', false);
                return;
            }

            $.ajax({
                url: "{{ route('sync.statement') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    selected_data: selectedData
                },
                success: function(response) {
                    loader.hide();
                    buttonText.css('visibility', 'visible');
                    button.prop('disabled', false);
                    if (response.success) {
                        AIZ.plugins.notify('success', 'Statements synced successfully.');
                        location.reload();
                    } else {
                        alert('Failed to sync statements. Please try again.');
                    }
                },
                error: function() {
                    loader.hide();
                    buttonText.css('visibility', 'visible');
                    button.prop('disabled', false);
                    alert('An error occurred. Please try again.');
                }
            });
        });


         // When the modal is shown, populate the party name and ids
        $('#commentModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget); // Button that triggered the modal
            var partyCode = button.data('party-code');
            var partyName = button.data('party-name');
            var userId = button.data('user-id'); // You need to pass the user ID in the button as well
            
            // Populate the modal fields
            $('#party-name').val(partyName);
            $('#party-code').val(partyCode);
            $('#user-id').val(userId);
        });

        // Handle the form submission
        $('#saveComment').on('click', function() {
            var formData = {
                user_id: $('#user-id').val(),
                party_code: $('#party-code').val(),
                statement_comment: $('#comment').val(),
                statement_comment_date: $('#due-date-picker').val(),
                _token: $('input[name="_token"]').val() // CSRF token
            };

            // AJAX request to save the comment
            $.ajax({
                type: "POST",
                url: "{{ route('submitComment') }}", // Make sure this route is correct
                data: formData,
                success: function(response) {
                    if(response.success) {
                        alert(response.message);
                        alert('Comment added successfully');
                        $('#commentModal').modal('hide');
                        location.reload(); // Optionally reload the page
                    } else {
                        alert('Failed to add comment');
                    }
                },
                error: function() {
                    alert('Something went wrong');
                }
            });
        });


        $('#notify-manager').click(function() {
        var button = $(this);
        var loader = button.find('.loader');
        var buttonText = button.find('.button-text');

        buttonText.css('visibility', 'hidden');
        loader.show();
        button.prop('disabled', true);

        var managerId = $('#manager-select').val();
        var warehouseId = $('#branch-select').val();

        // Collect unique manager IDs from selected checkboxes
        var uniqueManagers = new Set();
        $('.select-person:checked').each(function() {
            uniqueManagers.add($(this).data('manager-id'));
        });

        var managerIds = Array.from(uniqueManagers);

        // Determine the condition to send notifications
        if (managerIds.length > 0) {
            // Case 1: Notify only selected checkboxes' managers (unique manager IDs from selected checkboxes)
            sendNotificationRequest(managerIds, warehouseId);
        } else if (managerId) {
            // Case 2: Notify only the specific manager selected in the dropdown
            sendNotificationRequest([managerId], warehouseId);
        } else if (warehouseId) {
            // Case 3: Notify all managers in the selected branch
            $.ajax({
                url: "{{ route('getManagersByWarehouse') }}",
                type: "GET",
                data: { warehouse_id: warehouseId },
                success: function(response) {
                    var allManagerIds = response.map(manager => manager.id);
                    sendNotificationRequest(allManagerIds, warehouseId);
                },
                error: function() {
                    loader.hide();
                    buttonText.css('visibility', 'visible');
                    button.prop('disabled', false);
                    alert('Failed to fetch managers for the branch.');
                }
            });
        } else {
            AIZ.plugins.notify('warning', 'Please select a manager, branch, or checkboxes.');
            loader.hide();
            buttonText.css('visibility', 'visible');
            button.prop('disabled', false);
        }
    });

    // Function to send the AJAX request for notifications
    function sendNotificationRequest(managerIds, warehouseId) {
        $.ajax({
            url: "{{ route('notify.manager') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                manager_ids: managerIds,
                warehouse_id: warehouseId
            },
            success: function(response) {
                $('#notify-manager .loader').hide();
                $('#notify-manager .button-text').css('visibility', 'visible');
                $('#notify-manager').prop('disabled', false);

                if (response.status) {
                    AIZ.plugins.notify('success', 'Notifications sent successfully.');
                } else {
                    AIZ.plugins.notify('warning', 'Failed to notify manager(s). Please try again.');
                }
            },
            error: function() {
                $('#notify-manager .loader').hide();
                $('#notify-manager .button-text').css('visibility', 'visible');
                $('#notify-manager').prop('disabled', false);
                alert('An error occurred. Please try again.');
            }
        });
    }


     $('#whatsapp-all').click(function() {
            var button = $(this);
            var loader = button.find('.loader');
            var buttonText = button.find('.button-text');

            buttonText.css('visibility', 'hidden');
            loader.show();
            button.prop('disabled', true);

             // Gather filter parameters
            var warehouseId = $('#branch-select').val();
            var managerId = $('#manager-select').val();
            var duefilter = $('#duefilter-select').val();



            // Get selected checkboxes (WhatsApp Checked)
            var selectedData = [];
            $('.select-person:checked').each(function() {
                selectedData.push({
                    party_code: $(this).data('party-code'),
                    due_amount: $(this).data('due-amount'),
                    overdue_amount: $(this).data('overdue-amount'),
                    user_id: $(this).data('user-id')
                });
            });

            var selectedCount = selectedData.length;
            // Show selected data in an alert


            // Retrieve the total number of customers with due/overdue from the hidden input field
           var totalCustomerWithDueOverdue = parseInt($('#total_customer_with_due_overdue').val()) ;


            // Ensure that there are records to process
            if (selectedCount === 0) {
                // No checkboxes are selected, ask if the user wants to send to all
                loader.hide();
                buttonText.css('visibility', 'visible');
                button.prop('disabled', false);

                
                if (confirm("No checkboxes selected. Do you want to send WhatsApp to all " + totalCustomerWithDueOverdue + " users?")) {
                    loader.show();
                    buttonText.css('visibility', 'hidden');
                    button.prop('disabled', true);
                    
                    $.ajax({
                        url: "{{ route('get.all.users.data') }}", // The unified route
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            warehouse_id: warehouseId,
                            manager_id: managerId,
                            duefilter: duefilter
                        },
                        timeout: -1,  // Timeout set to 60 seconds
                        success: function(response) {
                            
                            if (response.success) {
                                loader.hide();
                                buttonText.css('visibility', 'visible');
                                button.prop('disabled', false);
                                 AIZ.plugins.notify('success', 'WhatsApp messages sent successfully.');


                                
                               
                                var groupId = response.group_id;

                                // New AJAX request to pass the groupId to processWhatsapp route
                                $.ajax({
                                    url: "{{ route('processWhatsapp') }}", // Replace this with the correct route
                                    type: "POST",
                                    data: {
                                        _token: "{{ csrf_token() }}",
                                        group_id: groupId
                                    },
                                    success: function(processResponse) {
                                        
                                        // alert('Group ID passed: ' + processResponse.group_id);
                                        AIZ.plugins.notify('success', 'WhatsApp messages sent successfully.');
                                    },
                                    error: function() {
                                       // alert('Failed to process the WhatsApp request.');
                                    }
                                });

                            

                                // AIZ.plugins.notify('success', response.groupId);
                            } else {
                                alert('Failed to send WhatsApp messages. Please try again.');
                            }
                        },
                        error: function(xhr, status, error) {
                            loader.hide();
                            buttonText.css('visibility', 'visible');
                            button.prop('disabled', false);
                            var errorMessage = `An error occurred: \n` +
                                               `Status Code: ${xhr.status}\n` +
                                               `Status Text: ${xhr.statusText}\n` +
                                               `Error Thrown: ${error}\n` +
                                               `Response: ${xhr.responseText}`;
                            alert(errorMessage);
                        }
                    });

                    return; // Stop further execution to prevent running the else part
                } else {
                    // User canceled the action
                    loader.hide();
                    buttonText.css('visibility', 'visible');
                    button.prop('disabled', false);
                    return;
                }
            } else {
                // Show confirmation message with selected count
                if (confirm("You are about to send WhatsApp messages to " + selectedCount + " users. Do you want to proceed?")) {
                    // Send AJAX request if checkboxes are selected
                    $.ajax({
                        url: "{{ route('generate.statement.pdf.bulk.checked') }}", // The unified route
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            all_data: selectedData
                        },
                        success: function(response) {
                            loader.hide();
                            buttonText.css('visibility', 'visible');
                            button.prop('disabled', false);
                            if (response.success) {
                                AIZ.plugins.notify('success', 'WhatsApp messages sent successfully.');
                            } else {
                                alert('Failed to send WhatsApp messages. Please try again.');
                            }
                        },
                        error: function() {
                            loader.hide();
                            buttonText.css('visibility', 'visible');
                            button.prop('disabled', false);
                            alert('An error occurred. Please try again.');
                        }
                    });
                } else {
                    // User canceled the action
                    loader.hide();
                    buttonText.css('visibility', 'visible');
                    button.prop('disabled', false);
                }
            }
        });


    });
</script>
@endsection
