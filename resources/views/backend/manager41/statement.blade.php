@extends('backend.layouts.app')

@section('content')
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
.sync-button{background:#007bff;color:#fff;border:none;padding:10px 20px;font-size:13px;cursor:pointer;border-radius:42px;position:relative;display:inline-flex;align-items:center;justify-content:center;width:149px;height:45px}
.sync-button .loader{border:4px solid rgba(255,255,255,.3);border-top:4px solid #fff;border-radius:50%;width:20px;height:20px;animation:spin 1s linear infinite;display:none;position:absolute}
.sync-button .button-text{visibility:visible}
@keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}
.d-inline-flex{display:inline-flex;align-items:center}.btn-icon{margin-right:5px}.table td{vertical-align:middle}
.whatsapp-button{background:#25D366;color:#fff;border:none;padding:10px 20px;font-size:13px;cursor:pointer;border-radius:42px;position:relative;display:inline-flex;align-items:center;justify-content:center;width:123px;height:45px}
.whatsapp-button .loader{border:4px solid rgba(255,255,255,.3);border-top:4px solid #fff;border-radius:50%;width:20px;height:20px;animation:spin 1s linear infinite;display:none;position:absolute}
.whatsapp-button .button-text{visibility:visible}
.button-group{display:flex;gap:10px}.small-column{width:100px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@media(max-width:767.98px){.whatsapp-button{width:150px;font-size:14px}.table th,.table td{white-space:nowrap}.small-column{width:80px}}
.notify-manager-button{background:#ff9800;color:#fff;border:none;padding:10px 20px;font-size:12px;cursor:pointer;border-radius:42px;position:relative;display:inline-flex;align-items:center;justify-content:center;width:149px;height:45px}
.notify-manager-button .loader{border:4px solid rgba(255,255,255,.3);border-top:4px solid #fff;border-radius:50%;width:20px;height:20px;animation:spin 1s linear infinite;display:none;position:absolute}
.notify-manager-button .button-text{visibility:visible}
.custom-date-picker{padding:10px;font-size:14px;border:2px solid #ced4da;border-radius:8px;width:100%;background:#fff;box-shadow:0 4px 6px rgba(0,0,0,.1);color:#495057;outline:none;appearance:none;position:relative}
.custom-date-picker::-webkit-calendar-picker-indicator{position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;height:20px;width:20px;opacity:.5}
.custom-date-picker:hover{border-color:#80bdff;background:#f8f9fa}
.custom-date-picker:focus{border-color:#80bdff;background:#fff;outline:none;box-shadow:0 0 5px rgba(0,123,255,.5)}
.custom-date-picker::-webkit-calendar-picker-indicator:hover{opacity:1}
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
      <input type="hidden" id="total_customer_with_due_overdue" value="{{ $totalCustomersWithDueOrOverdue }}">
    </div>

    <div class="notify-manager-button-container">
      <button type="button" class="notify-manager-button btn btn-sm" id="notify-manager">
        <span class="button-text"><i class="fas fa-bell"></i> Notify Manager</span>
        <div class="loader" style="display:none;"></div>
      </button>
    </div>

    <div class="sync-button-container">
      <button type="button" class="sync-button btn btn-sm" id="sync-checked">
        <span class="button-text"><i class="fas fa-sync"></i> Sync Checked</span>
        <div class="loader"></div>
      </button>
    </div>

    <div class="export-button-container">
      <form method="GET" action="{{ route('adminExportStatement') }}">
        <input type="hidden" name="warehouse_id" value="{{ request()->warehouse_id }}">
        <input type="hidden" name="manager_id"  value="{{ request()->manager_id }}">
        <input type="hidden" name="city_id"     value="{{ request()->city_id }}">
        <input type="hidden" name="search"      value="{{ request()->search }}">
        <input type="hidden" name="duefilter"   value="{{ request()->duefilter }}">
        <input type="hidden" name="sort_by"     value="{{ request()->sort_by }}">
        <input type="hidden" name="sort_order"  value="{{ request()->sort_order }}">
        <input type="hidden" name="_ts"         value="{{ now()->timestamp }}">
        <button type="submit" style="border-radius:42px;background-color:#6A5ACD;" class="btn btn-success">
          <i class="fas fa-file-export"></i> Export
        </button>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    {{-- Filters --}}
    <form method="GET" action="{{ route('manager41.statement') }}">
      <div class="row gutters-5 mb-4">
        <div class="col-md-3 position-relative">
          <label class="form-label font-weight-bold">Search</label>
          <div class="input-group">
            <input type="text" name="search" id="search-input" class="form-control"
                   placeholder="Party Code, Name, or City" value="{{ request()->search }}">
            @if(request()->has('search') && request()->search != '')
              <button type="submit" name="clear" value="1" class="btn btn-outline-secondary">&times;</button>
            @endif
          </div>
        </div>

        @php
          $loggedInUserWarehouseId = auth()->user()->warehouse_id;
        @endphp

        {{-- replace the disabled select + hidden input with this --}}
        <div class="col-md-2">
          <label class="form-label font-weight-bold">Branch</label>
          <select class="form-control" name="warehouse_id" id="branch-select">
            <option value="">Select Branch</option>
            @foreach($warehouses as $warehouse)
              <option value="{{ $warehouse->id }}"
                {{ (int)request('warehouse_id') === (int)$warehouse->id ? 'selected' : '' }}>
                {{ $warehouse->name }}
              </option>
            @endforeach
          </select>
        </div>

        <input type="hidden" name="manager_id" id="manager-select" value="{{ auth()->id() }}">

        <div class="col-md-2">
          <label class="form-label font-weight-bold">City</label>
          <select class="form-control" name="city_id" id="city-select">
            <option value="">Select City</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label font-weight-bold">Due Filter</label>
          <select class="form-control" name="duefilter" id="duefilter-select">
            <option value="">Select Due Filter</option>
            <option value="due"         {{ request()->duefilter == 'due' ? 'selected' : '' }}>Due</option>
            <option value="overdue"     {{ request()->duefilter == 'overdue' ? 'selected' : '' }}>Overdue</option>
            <option value="overdue_60"  {{ request()->duefilter == 'overdue_60' ? 'selected' : '' }}>60+ days</option>
            <option value="overdue_90"  {{ request()->duefilter == 'overdue_90' ? 'selected' : '' }}>90+ days</option>
            <option value="overdue_120" {{ request()->duefilter == 'overdue_120' ? 'selected' : '' }}>120+ days</option>
          </select>
        </div>

        <div class="col-md-1">
          <button type="submit" class="btn btn-primary btn-sm mt-4">Filters</button>
        </div>
      </div>
    </form>

    {{-- Tiles --}}
    <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
      <div style="flex:1 1 260px; background:#fff; border:1px solid #eef1f5; border-radius:12px; padding:14px 16px; box-shadow:0 2px 6px rgba(7,78,134,.06); display:flex; align-items:center;">
        <span style="display:inline-flex; width:40px; height:40px; border-radius:50%; align-items:center; justify-content:center; margin-right:12px; background:#e8f2ff; color:#2b6cb0;">
          <i class="fas fa-wallet"></i>
        </span>
        <div>
          <div style="font-size:12px; color:#6c757d; letter-spacing:.3px;">Total Due Amount</div>
          <div style="font-size:18px; font-weight:700;">₹{{ number_format($totalDueAmount, 2) }}</div>
        </div>
      </div>

      <div style="flex:1 1 260px; background:#fff; border:1px solid #feecec; border-radius:12px; padding:14px 16px; box-shadow:0 2px 6px rgba(197,48,48,.08); display:flex; align-items:center;">
        <span style="display:inline-flex; width:40px; height:40px; border-radius:50%; align-items:center; justify-content:center; margin-right:12px; background:#ffe8e8; color:#c53030;">
          <i class="fas fa-exclamation-circle"></i>
        </span>
        <div>
          <div style="font-size:12px; color:#a94442; letter-spacing:.3px;">Total Overdue Amount</div>
          <div style="font-size:18px; font-weight:700;">₹{{ number_format($totalOverdueAmount, 2) }}</div>
        </div>
      </div>

      @if(!empty($overdueBucketThreshold))
        <div style="flex:1 1 260px; background:#fff; border:1px solid #efe9ff; border-radius:12px; padding:14px 16px; box-shadow:0 2px 6px rgba(111,66,193,.08); display:flex; align-items:center;">
          <span style="display:inline-flex; width:40px; height:40px; border-radius:50%; align-items:center; justify-content:center; margin-right:12px; background:#efe9ff; color:#6f42c1;">
            <i class="fas fa-hourglass-half"></i>
          </span>
          <div>
            <div style="font-size:12px; color:#6f42c1; letter-spacing:.3px;">Overdue {{ $overdueBucketThreshold }}+ Amount</div>
            <div style="font-size:18px; font-weight:700;">₹{{ number_format($totalOverdueBucketAmount ?? 0, 2) }}</div>
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
              <th>Phone</th>
              <th>
                <a href="{{ request()->fullUrlWithQuery(['sort_by'=>'credit_days','sort_order'=> request('sort_order')=='asc'?'desc':'asc']) }}">
                  Credit Days
                  @if(request('sort_by')=='credit_days') <i class="fas fa-sort-{{ request('sort_order')=='asc'?'up':'down' }}"></i>
                  @else <i class="fas fa-sort"></i>@endif
                </a>
              </th>
              <th>
                <a href="{{ request()->fullUrlWithQuery(['sort_by'=>'credit_limit','sort_order'=> request('sort_order')=='asc'?'desc':'asc']) }}">
                  Credit Limit
                  @if(request('sort_by')=='credit_limit') <i class="fas fa-sort-{{ request('sort_order')=='asc'?'up':'down' }}"></i>
                  @else <i class="fas fa-sort"></i>@endif
                </a>
              </th>
              <th>Manager</th>
              <th>Warehouse</th>
              <th>
                <a href="{{ request()->fullUrlWithQuery(['sort_by'=>'city','sort_order'=> request('sort_order')=='asc'?'desc':'asc']) }}">
                  City
                  @if(request('sort_by')=='city') <i class="fas fa-sort-{{ request('sort_order')=='asc'?'up':'down' }}"></i>
                  @else <i class="fas fa-sort"></i>@endif
                </a>
              </th>
              <th>
                <a href="{{ request()->fullUrlWithQuery(['sort_by'=>'due_amount_numeric','sort_order'=> request('sort_order')=='asc'?'desc':'asc']) }}">
                  Due Amount
                  @if(request('sort_by')=='due_amount_numeric') <i class="fas fa-sort-{{ request('sort_order')=='asc'?'up':'down' }}"></i>
                  @else <i class="fas fa-sort"></i>@endif
                </a>
              </th>
              <th>
                <a href="{{ request()->fullUrlWithQuery(['sort_by'=>'overdue_amount_numeric','sort_order'=> request('sort_order')=='asc'?'desc':'asc']) }}">
                  Overdue Amount
                  @if(request('sort_by')=='overdue_amount_numeric') <i class="fas fa-sort-{{ request('sort_order')=='asc'?'up':'down' }}"></i>
                  @else <i class="fas fa-sort"></i>@endif
                </a>
              </th>

              {{-- NEW: Snapshot from statement_41_data --}}
              <th>Last Txn</th>
              <th>C/F</th>

              <th>Action</th>
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
                  <div class="text-primary" style="font-size:11px;font-weight:600;">
                    {{ $customer->acc_code }}
                  </div>

                  {{-- Optional tiny line showing snapshot info under name --}}
                  @if(!empty($customer->statement_41))
                    <div style="font-size:10px;color:#6c757d;">
                      Snap: {{ $customer->statement_41_last_date ? \Carbon\Carbon::parse($customer->statement_41_last_date)->format('d-m-Y') : '-' }}
                      • ₹{{ $customer->statement_41_closing_balance !== null ? number_format((float)$customer->statement_41_closing_balance, 2) : '0.00' }}
                    </div>
                  @endif
                </td>
                <td>{{ $customer->phone }}</td>
                <td class="credit-days">{{ $customer->credit_days }}</td>
                <td class="credit-limit">{{ $customer->credit_limit }}</td>
                <td class="manager-name">{{ $customer->manager_name ?? 'N/A' }}</td>
                <td>{{ $customer->warehouse_name ?? 'N/A' }}</td>
                <td>{{ $customer->city }}</td>
                <td>₹{{ number_format($customer->due_amount_numeric, 2) }}</td>
                <td>₹{{ number_format($customer->overdue_amount_numeric, 2) }}</td>

                {{-- NEW: Snapshot columns --}}
                <td>
                  {{ $customer->statement_41_last_date
                      ? \Carbon\Carbon::parse($customer->statement_41_last_date)->format('d-m-Y')
                      : '-' }}
                </td>
                <td>
                  ₹{{ $customer->statement_41_closing_balance !== null
                         ? number_format((float)$customer->statement_41_closing_balance, 2)
                         : '0.00' }}
                </td>

                <td style="white-space: nowrap;">
                  <a href="#"
                     class="btn btn-primary btn-sm"
                     data-toggle="modal"
                     data-target="#commentModal"
                     data-party-code="{{ $customer->acc_code }}"
                     data-party-name="{{ $customer->company_name }}"
                     data-user-id="{{ $customer->id }}"
                     style="margin-right:5px;padding:6px 8px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                    <i class="fas fa-comment" style="font-size:16px;"></i>
                  </a>

                  <a href="#"
                     class="btn btn-primary btn-sm my_pdf"
                     data-party-code="{{ $customer->acc_code }}"
                     data-party-name="{{ $customer->company_name }}"
                     data-user-id="{{ $customer->id }}"
                     style="padding:6px 8px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                    <i class="fas fa-file-pdf" style="font-size:16px;"></i>
                  </a>
                  <br>
                  <a href="{{ route('customers.login', encrypt($customer->id)) }}"
                     class="btn btn-soft-primary btn-icon btn-circle btn-sm"
                     title="{{ translate('Log in as this Customer') }}">
                    <i class="las la-sign-in-alt"></i>
                  </a>

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
                          style="padding:6px 8px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                    <i class="fas fa-user-plus" style="font-size:14px;margin-right:0;"></i>
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
                          style="padding:6px 8px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                    <i class="las la-rupee-sign" style="font-size:14px;margin-right:0;"></i>
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
      <div class="alert alert-warning">No records found.</div>
    @endif
  </div>
</div>

{{-- Comment Modal --}}
<div class="modal fade" id="commentModal" tabindex="-1" role="dialog" aria-labelledby="commentModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="commentModalLabel">Add Comment</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="commentForm">
          @csrf
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

{{-- PDF Modal --}}
<div class="modal fade" id="pdfModal" tabindex="-1" role="dialog" aria-labelledby="pdfModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content" style="height: 90vh;">
      <div class="modal-header">
        <h5 class="modal-title" id="pdfModalLabel">View PDF</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body" style="padding:0;height:100%;">
        <iframe id="pdfViewer" src="" frameborder="0" width="100%" height="100%" style="height:100%;"></iframe>
      </div>
    </div>
  </div>
</div>

{{-- Assign Manager Modal --}}
<div class="modal fade" id="assignModel" tabindex="-1" role="dialog" aria-labelledby="assignModelLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assignModelLabel">Assign Manager</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="assignManagerForm">
          @csrf
          <input type="hidden" name="user_id" id="user-id-assign">
          <div class="form-group">
            <label for="party-name">Party Name</label>
            <input type="text" class="form-control" id="party-name-assign" readonly>
          </div>
          <div class="form-group">
            <label for="assignmanager-select">Select Manager</label>
            <select class="form-control" name="manager_id" id="assignmanager-select">
              <option value="{{ auth()->id() }}" selected>{{ auth()->user()->name }}</option>
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

{{-- Payment Link Modal --}}
<div class="modal fade" id="paymentLinkModel" tabindex="-1" role="dialog" aria-labelledby="assignModelLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assignModelLabel">Send Payment Link</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="paymentLinkSendForm">
          @csrf
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
$(function() {
  // Preload cities for locked manager
  var selectedCity = "{{ request('city_id') }}";
  var managerId    = $('#manager-select').val();
  if (managerId) loadCities(managerId, selectedCity);

  function loadCities(managerId, selectedCity) {
    $.ajax({
      url: "{{ route('manager41.get_cities_by_manager_statement') }}",
      type: "GET",
      data: { manager_id: managerId },
      success: function(response) {
        $.each(response, function(_, city) {
          $('#city-select').append('<option value="'+city+'">'+city+'</option>');
        });
        if (selectedCity) $('#city-select').val(selectedCity);
      },
      error: function(_, __, error) { console.error('Error fetching cities:', error); }
    });
  }

  // Select all
  $('#select-all').on('click', function() {
    $('.select-person').prop('checked', $(this).prop('checked'));
  });

  // PDF viewer
  $(document).on('click', '.my_pdf', function(e) {
    e.preventDefault();
    let partyCode = $(this).data('party-code');

    $.ajax({
      url: `/manager41/create-pdf/${partyCode}`,
      type: 'GET',
      success: function(res) {
        if (res.pdf_url) {
          $('#pdfViewer').attr('src', res.pdf_url);
          $('#pdfModal').modal('show');
        } else { alert("Failed to generate PDF. Please try again."); }
      },
      error: function(xhr) {
        console.error("Response Text: ", xhr.responseText);
        alert("An error occurred while generating the PDF.");
      }
    });
  });

  // Assign Manager
  $(document).on('click', '.assign-manager-btn', function () {
    var userId = $(this).data('user-id');
    var partyName = $(this).data('party-name');
    var creditDays = $(this).data('user-creditdays');
    var creditLimit = $(this).data('user-creditlimit');
    var currentManagerId = $(this).data('current-manager-id');

    $('#credit-days-assign').val(creditDays);
    $('#credit-limit-assign').val(creditLimit);
    $('#user-id-assign').val(userId);
    $('#party-name-assign').val(partyName);
    $('#assignmanager-select').val(currentManagerId || '{{ auth()->id() }}');
    $('#assignModel').modal('show');
  });

  // Payment link modal
  $(document).on('click', '.payment-link-btn', function () {
    var userId = $(this).data('user-id');
    var partyName = $(this).data('party-name');
    var partyPhoneNumber = $(this).data('party-phone-number');
    var dueAmount = $(this).data('party-due-amount');
    var overdueAmount = $(this).data('party-over-due-amount');

    $('#pay-user-id-assign').val(userId);
    $('#pay-party-name-assign').val(partyName);
    $('#party-phone-number').val(partyPhoneNumber);

    $('#payable_amount').val(dueAmount).prop('readonly', true);
    $('#payment_for').val('Due_Amount');
    $('#dueAmount').val(dueAmount);
    $('#overdueAmount').val(overdueAmount);

    $('#paymentLinkModel').data('due-amount', dueAmount).data('overdue-amount', overdueAmount);
    $('#paymentLinkModel').modal('show');
  });

  $(document).on('change', '#payment_for', function () {
    var sel = $(this).val();
    var modal = $('#paymentLinkModel');
    var dueAmount = modal.data('due-amount');
    var overdueAmount = modal.data('overdue-amount');

    if (sel === 'Due_Amount') $('#payable_amount').val(dueAmount).prop('readonly', true);
    else if (sel === 'Over_Due_Amount') $('#payable_amount').val(overdueAmount).prop('readonly', true);
    else if (sel === 'Custom_Amount') $('#payable_amount').val('').prop('readonly', false).focus();
  });

  $('#sendPaymentLink').on('click', function () {
    var userId     = $('#pay-user-id-assign').val();
    var paymentFor = $('#payment_for').val();
    var amount     = $('#payable_amount').val();
    var dueAmount  = $('#dueAmount').val();
    var overdueAmount = $('#overdueAmount').val();

    $.ajax({
      url: '{{ route("zoho.payment.createPaymentLink") }}',
      method: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        user_id: userId, description: paymentFor, amount: amount,
        dueAmount: dueAmount, overdueAmount: overdueAmount, notify_user: '1'
      },
      success: function (res) {
        if (res.success) { AIZ.plugins.notify('success', 'Payment link sent successfully.'); $('#paymentLinkModel').modal('hide'); }
        else if (res.auth_required && res.auth_url) {
          var win = window.open(res.auth_url, '_blank');
          if (!win || win.closed || typeof win.closed === 'undefined') { window.location.href = res.auth_url; return; }
          AIZ.plugins.notify('warning', 'Please authorize Zoho in the new tab, then return here.');
        } else { alert(res.message || 'Error creating payment link. Please try again.'); }
      },
      error: function (xhr) {
        try {
          const r = JSON.parse(xhr.responseText);
          if (r.auth_required && r.auth_url) {
            var win = window.open(r.auth_url, '_blank');
            if (!win || win.closed || typeof win.closed === 'undefined') { window.location.href = r.auth_url; return; }
            AIZ.plugins.notify('warning', 'Please authorize Zoho in the new tab, then return here.');
          } else { alert((r && r.message) ? r.message : 'An error occurred. Please try again.'); }
        } catch { alert('An error occurred. Please try again.'); }
      }
    });
  });

  // Assign manager submit
  $('#assignManagerForUser').on('click', function () {
    var userId = $('#user-id-assign').val();
    var managerId = $('#assignmanager-select').val();
    var creditDays = $('#credit-days-assign').val();
    var creditLimit = $('#credit-limit-assign').val();

    if (!managerId) { alert('Please select a manager.'); return; }

    $.ajax({
      url: '{{ route("assign.manager") }}',
      method: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        user_id: userId, manager_id: managerId,
        credit_days: creditDays, credit_limit: creditLimit
      },
      success: function (res) {
        if (res.success) {
          var btn = $(`button[data-user-id="${userId}"]`);
          btn.data('current-manager-id', managerId);
          btn.data('user-creditdays', creditDays);
          btn.data('user-creditlimit', creditLimit);
          $(`tr[data-user-id="${userId}"] td.manager-name`).text(`${res.manager.name}`);
          $(`tr[data-user-id="${userId}"] td.credit-days`).text(creditDays);
          $(`tr[data-user-id="${userId}"] td.credit-limit`).text(creditLimit);
          AIZ.plugins.notify('success', 'Manager Assigned Successfully.');
          $('#assignModel').modal('hide');
        } else { alert('Error assigning manager. Please try again.'); }
      },
      error: function (xhr) { console.error(xhr.responseText); alert('An error occurred. Please try again.'); }
    });
  });

  // Sync Checked
  $('#sync-checked').click(function() {
    var button = $(this), loader = button.find('.loader'), buttonText = button.find('.button-text');
    buttonText.css('visibility','hidden'); loader.show(); button.prop('disabled',true);

    var selectedData = [];
    $('.select-person:checked').each(function() {
      selectedData.push({ party_code: $(this).data('party-code'), user_id: $(this).data('user-id') });
    });

    if (!selectedData.length) {
      AIZ.plugins.notify('warning', 'Please select at least one record.');
      buttonText.css('visibility','visible'); loader.hide(); button.prop('disabled',false);
      return;
    }

    $.ajax({
      url: "{{ route('manager41.sync.statement') }}",
      type: "POST",
      data: { _token: "{{ csrf_token() }}", selected_data: selectedData },
      success: function(res) {
        loader.hide(); buttonText.css('visibility','visible'); button.prop('disabled',false);
        if (res.success) { AIZ.plugins.notify('success', 'Statements synced successfully.'); location.reload(); }
        else { alert('Failed to sync statements. Please try again.'); }
      },
      error: function() {
        loader.hide(); buttonText.css('visibility','visible'); button.prop('disabled',false);
        alert('An error occurred. Please try again.');
      }
    });
  });

  // Comment modal populate
  $('#commentModal').on('show.bs.modal', function(e) {
    var button = $(e.relatedTarget);
    $('#party-name').val(button.data('party-name'));
    $('#party-code').val(button.data('party-code'));
    $('#user-id').val(button.data('user-id'));
  });

  // Save comment
  $('#saveComment').on('click', function() {
    var data = {
      user_id: $('#user-id').val(),
      party_code: $('#party-code').val(),
      statement_comment: $('#comment').val(),
      statement_comment_date: $('#due-date-picker').val(),
      _token: $('input[name="_token"]').val()
    };

    $.ajax({
      type: "POST",
      url: "{{ route('submitComment') }}",
      data: data,
      success: function(res) {
        if(res.success) { alert('Comment added successfully'); $('#commentModal').modal('hide'); location.reload(); }
        else { alert('Failed to add comment'); }
      },
      error: function() { alert('Something went wrong'); }
    });
  });

  // Notify manager
  $('#notify-manager').click(function() {
    var button = $(this), loader = button.find('.loader'), buttonText = button.find('.button-text');
    buttonText.css('visibility','hidden'); loader.show(); button.prop('disabled',true);

    var managerId = $('#manager-select').val();
    var warehouseId = $('input[name="warehouse_id"]').val();

    var uniq = new Set();
    $('.select-person:checked').each(function(){ uniq.add($(this).data('manager-id')); });
    var managerIds = Array.from(uniq);

    if (managerIds.length > 0) sendNotificationRequest(managerIds, warehouseId);
    else if (managerId)        sendNotificationRequest([managerId], warehouseId);
    else if (warehouseId) {
      $.ajax({
        url: "{{ route('getManagersByWarehouse') }}",
        type: "GET",
        data: { warehouse_id: warehouseId },
        success: function(r) { sendNotificationRequest(r.map(x=>x.id), warehouseId); },
        error: function() {
          loader.hide(); buttonText.css('visibility','visible'); button.prop('disabled',false);
          alert('Failed to fetch managers for the branch.');
        }
      });
    } else {
      AIZ.plugins.notify('warning', 'Please select a manager, branch, or checkboxes.');
      loader.hide(); buttonText.css('visibility','visible'); button.prop('disabled',false);
    }
  });

  function sendNotificationRequest(managerIds, warehouseId) {
    $.ajax({
      url: "{{ route('notify.manager') }}",
      type: "POST",
      data: { _token: "{{ csrf_token() }}", manager_ids: managerIds, warehouse_id: warehouseId },
      success: function(res) {
        $('#notify-manager .loader').hide();
        $('#notify-manager .button-text').css('visibility','visible');
        $('#notify-manager').prop('disabled',false);
        if (res.status) AIZ.plugins.notify('success', 'Notifications sent successfully.');
        else            AIZ.plugins.notify('warning', 'Failed to notify manager(s). Please try again.');
      },
      error: function() {
        $('#notify-manager .loader').hide();
        $('#notify-manager .button-text').css('visibility','visible');
        $('#notify-manager').prop('disabled',false);
        alert('An error occurred. Please try again.');
      }
    });
  }

  // WhatsApp all / checked
  $('#whatsapp-all').click(function() {
    var button = $(this), loader = button.find('.loader'), buttonText = button.find('.button-text');
    buttonText.css('visibility','hidden'); loader.show(); button.prop('disabled',true);

    var warehouseId = $('input[name="warehouse_id"]').val();
    var managerId   = $('#manager-select').val();
    var duefilter   = $('#duefilter-select').val();

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
    var totalUsers = parseInt($('#total_customer_with_due_overdue').val());

    if (!selectedCount) {
      loader.hide(); buttonText.css('visibility','visible'); button.prop('disabled',false);
      if (confirm("No checkboxes selected. Do you want to send WhatsApp to all " + totalUsers + " users?")) {
        loader.show(); buttonText.css('visibility','hidden'); button.prop('disabled',true);
        $.ajax({
          url: "{{ route('manager41.get.all.users.data') }}",
          type: "POST",
          data: { _token: "{{ csrf_token() }}", warehouse_id: warehouseId, manager_id: managerId, duefilter: duefilter },
          timeout: -1,
          success: function(r) {
            loader.hide(); buttonText.css('visibility','visible'); button.prop('disabled',false);
            if (r.success) {
              AIZ.plugins.notify('success', 'WhatsApp messages queued.');
              $.ajax({ url: "{{ route('manager41.processWhatsapp') }}", type: "POST", data: { _token: "{{ csrf_token() }}", group_id: r.group_id }});
            } else { alert('Failed to send WhatsApp messages.'); }
          },
          error: function(_, __, error) {
            loader.hide(); buttonText.css('visibility','visible'); button.prop('disabled',false);
            alert('Error: ' + error);
          }
        });
      }
      return;
    }

    if (confirm("You are about to send WhatsApp messages to " + selectedCount + " users. Do you want to proceed?")) {
      $.ajax({
        url: "{{ route('manager41.generate.statement.pdf.bulk.checked') }}",
        type: "POST",
        data: { _token: "{{ csrf_token() }}", all_data: selectedData },
        success: function(r) {
          loader.hide(); buttonText.css('visibility','visible'); button.prop('disabled',false);
          if (r.success) AIZ.plugins.notify('success', 'WhatsApp messages sent successfully.');
          else alert('Failed to send WhatsApp messages.');
        },
        error: function() {
          loader.hide(); buttonText.css('visibility','visible'); button.prop('disabled',false);
          alert('An error occurred. Please try again.');
        }
      });
    } else {
      loader.hide(); buttonText.css('visibility','visible'); button.prop('disabled',false);
    }
  });
});
</script>
@endsection