@extends('backend.layouts.app')

@section('content')

<div class="card">
   <div class="card-header d-flex justify-content-between align-items-center">
    <h5>Invoiced Orders</h5>
    <button 
        class="btn btn-success btn-sm" 
        id="bulkSyncBtn"
    >
        <i class="las la-sync-alt"></i> Sync to Zoho
    </button>
</div>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

    <div class="card-body">
           <div class="mb-3">
              <form method="GET" action="{{ route('invoice.orders') }}" style="position: relative; max-width: 400px;">
                    <!-- Search Input -->
                    <input 
                        type="text" 
                        name="search" 
                        id="searchInput" 
                        value="{{ request('search') }}" 
                        placeholder="Search By Invoice No, Challan No, Party Name, Party Code, Warehouse, City " 
                        class="form-control" 
                        style="padding-right: 45px; width: 595px;"
                    >

                    <!-- Clear Button -->
                    @if(request('search'))
                        <a href="{{ route('invoice.orders') }}" 
                           title="Clear Search"
                           style="
                                position: absolute;
                                left: 512px;
                                top: 50%;
                                transform: translateY(-50%);
                                width: 35px;
                                height: 35px;
                                background-color: #ccc; /* light grey */
                                color: white;
                                border-radius: 3px;
                                font-size: 22px;
                                text-align: center;
                                line-height: 35px;
                                text-decoration: none;
                                font-weight: bold;
                                cursor: pointer;
                            ">
                            <i class="las la-sync-alt" id="refreshIcon"></i>
                        </a>
                    @endif
                </form>
          </div>
        <table class="table table-bordered">
        <thead class="bg-primary text-white">
            <tr>
                <th>#</th>
                <th>Invoice No</th>
                <th>Party</th>
                <th>Warehouse</th>
                <th>Challan Nos</th>
                <th>Date</th>
                <th>Products</th>
                <th>PDF</th>
                <th>Eway Bill</th>
                <th>E-Invoice</th>
                <th>Logistics</th> {{-- ✅ New column --}}
            </tr>
        </thead>
        <tbody>
            @foreach ($invoices as $key => $inv)
            
            <tr @if(empty($inv->zoho_invoice_id)) style="background-color: #fff9db;" @endif>
                    <td>{{ $key + 1 }}</td>
                    <td>{{ $inv->invoice_no }}</td>
                    <td>
                        {{ $inv->address ? $inv->address->company_name . ' - ' . $inv->address->city . ' (' . $inv->address->acc_code . ')' : 'N/A' }}
                    </td>
                    <td>{{ $inv->warehouse->name ?? 'N/A' }}</td>
                    <td>{{ $inv->challan_no }}</td>
                    <td>{{ \Carbon\Carbon::parse($inv->created_at)->format('d-m-Y') }}</td>

                    {{-- Products --}}
                    <td>
                        @if ($inv->invoice_cancel_status != 1)
                            <a  href="{{ route('invoice.products', $inv->id) }}" class="btn btn-info btn-sm py-0 px-1 text-xs font-weight-bold">Products</a>
                        @endif
                    </td>

                    {{-- PDF --}}
                    <td>
                        @if ($inv->invoice_cancel_status != 1)
                            <a href="{{ route('invoice.downloadPdf', $inv->id) }}" class="btn btn-danger btn-sm py-0 px-1 text-xs font-weight-bold" target="_blank">PDF</a>
                        @endif
                    </td>

                    {{-- E-Way Bill --}}
                    <td>
                        @if ($inv->invoice_cancel_status != 1)
                            @if ($inv->ewaybill && $inv->ewaybill->ewaybill_number)
                                <a href="{{ route('zoho.ewaybill.cancel', $inv->ewaybill->ewaybill_id) }}"
                                class="btn btn-danger py-0 px-1 text-xs font-weight-bold"
                                style="font-size: 11px; line-height: 1.1;"
                                onclick="return confirm('Are you sure you want to cancel this e-Way Bill?')">
                                    Cancel e-Way Bill
                                </a>
                            @else
                                <a href="javascript:void(0)" 
                                class="btn btn-primary py-0 px-1 text-xs font-weight-bold openEwayModal"
                                style="font-size: 11px; line-height: 1.1;"
                                data-invoice-id="{{ $inv->zoho_invoice_id }}"
                                data-invoice="{{ $inv->invoice_no }}"
                                data-party="{{ $inv->address->company_name ?? 'N/A' }}"
                                data-dispatch-address="{{ $inv->warehouse->eway_address_id }}"
                                data-pincode="{{ $inv->address->postal_code ?? 'N/A' }}"
                                data-dispatchpincode="{{ $inv->warehouse->pincode }}"
                                data-shipstate="{{ $inv->address->state->state_code ?? 'N/A' }}">
                                    Generate e-Way Bill
                                </a>
                            @endif
                        @endif
                    </td>

                    {{-- E-Invoice & Cancel Invoice --}}
                    <td>
                        @if ($inv->invoice_cancel_status == 1)
                            <span style="width: auto;" class="badge badge-secondary py-1 px-2" style="font-size: 11px;">Invoice Cancelled</span>
                        @else
                            {{-- E-Invoice Actions --}}
                            @if ($inv->einvoice_status === 1)
                                @if (!empty($inv->zoho_invoice_id))
                                    <a href="{{ route('zoho.einvoice.cancel', $inv->zoho_invoice_id) }}?reason=Order Cancelled&reason_type=order_cancelled" 
                                    class="btn btn-danger py-0 px-1 text-xs font-weight-bold"
                                    style="font-size: 11px; line-height: 1.1;"
                                    onclick="return confirm('Are you sure you want to cancel this IRN?')">
                                        Cancel E-Invoice
                                    </a>
                                
                                @endif
                            @elseif ($inv->einvoice_status === 0)
                                @if (!empty($inv->zoho_invoice_id))
                                    <a href="{{ route('zoho.einvoice.push', $inv->zoho_invoice_id) }}" 
                                    class="btn btn-success py-0 px-1 text-xs font-weight-bold"
                                    style="font-size: 11px; line-height: 1.1;"
                                    onclick="return confirm('Are you sure you want to push this invoice to IRP and generate IRN?')">
                                    Generate E-Invoice
                                    </a>
                                
                                @endif
                            @elseif ($inv->einvoice_status === 2)
                                <span style="width: auto;" class="badge badge-secondary py-1 px-2" style="font-size: 20px;">IRN Cancelled</span>
                            @endif



                            {{-- Cancel Invoice --}}
                            @if ($inv->challan_id && !$inv->irn_no)
                                <a href="{{ route('invoice.cancel', ['challans' => $inv->challan_id, 'invoice_id' => $inv->id]) }}"
                                class="btn btn-warning py-0 px-1 text-xs font-weight-bold"
                                style="font-size: 11px; line-height: 1.1;"
                                onclick="return confirm('Are you sure you want to cancel this Invoice and mark all related Challans?')">
                                    Cancel Invoice
                                </a>
                            @endif
                        @endif
                    </td>
                    <td>
                        @if ($inv->invoice_cancel_status != 1)
                            @if ($inv->add_status == 1)
                                <a href="{{ route('order.logistics.edit', encrypt($inv->invoice_no)) }}" 
                                class="btn btn-warning py-0 px-1 text-xs font-weight-bold"
                                style="font-size: 11px; line-height: 1.1;">
                                Edit Logistics
                                </a>
                            @else
                                <a href="{{ route('order.logistics.add', encrypt($inv->invoice_no)) }}" 
                                class="btn btn-primary py-0 px-1 text-xs font-weight-bold"
                                style="background-color: #6610f2; color: white; font-size: 11px; line-height: 1.1;">
                                Add Logistics
                                </a>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

            <div class="aiz-pagination mt-3">
                {{ $invoices->links() }}
            </div>
        </div>
    </div>



<!-- e-Way Bill Modal -->
<!-- E-Way Bill Modal -->
<div class="modal fade" id="ewayModal" tabindex="-1" role="dialog" aria-labelledby="ewayModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <form id="ewayForm" method="GET" action="{{ route('zoho.generate.ewaybill') }}">
       
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="ewayModalLabel">Generate e-Way Bill</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="modal-body row">
                {{-- Hidden inputs --}}
                   <!-- <input type="hidden" name="entity_id" id="ewayInvoiceId"> -->

                    <input type="hidden" name="entity_id" id="ewayInvoiceId">
                    <input type="hidden" name="party_name" id="ewayPartyName">


                {{-- Invoice Number --}}
                <div class="form-group col-md-6">
                    <label for="ewayInvoiceNoDisplay">Invoice Number</label>
                    <input type="text" id="ewayInvoiceNoDisplay" class="form-control font-weight-bold text-primary" readonly>
                </div>

                {{-- Party Name --}}
                <div class="form-group col-md-6">
                    <label for="ewayPartyNameDisplay">Party Name</label>
                    <input type="text" id="ewayPartyNameDisplay" class="form-control font-weight-bold text-dark" readonly>
                </div>

                
                <div class="form-group col-md-6">
                    <label for="shipToPincode">Ship To Pincode</label>
                    <input type="text" id="shipToPincode" class="form-control font-weight-bold text-dark" readonly>
                </div>

                <div class="form-group col-md-6">
                    <label for="dispatchFromPincode">Dispatch From Pincode</label>
                    <input type="text" id="dispatchFromPincode" class="form-control font-weight-bold text-dark" readonly>
                </div>
                <div class="form-group col-md-6">
                    <label>Vehicle Number</label>
                    <input type="text" class="form-control" name="vehicle_number" >
                </div>

                <div class="form-group col-md-6">
                    <label>Transportation Mode</label>
                    <select name="transportation_mode" class="form-control" required>
                        <option value="">-- Select Mode --</option>
                        <option selected value="road">Road</option>
                        <option value="rail">Rail</option>
                        <option value="air">Air</option>
                        <option value="ship">Ship</option>
                    </select>
                </div>

                <div class="form-group col-md-6">
                    <label>Distance (km)</label>
                    <input type="number" class="form-control" name="distance" required>

                    <a href="https://ewaybillgst.gov.in/Others/P2PDistance.aspx" target="_blank" class="">
                            Check Distance Online
                        </a>
                </div>

                <div class="form-group col-md-6">
                    <label>Dispatch Address</label>
                    <select name="dispatch_from_address_id" class="form-control" required>
                        <option value="">-- Select Dispatch Address --</option>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->eway_address_id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group col-md-6">
                    <label>Ship To State Code</label>
                   <select name="ship_to_state_code" id="shipToStateCodeDropdown" class="form-control" required>
                        <option value="">-- Select State Code --</option>
                        @foreach($states as $state)
                            <option value="{{ $state->state_code }}">{{ $state->name }} ({{ $state->state_code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group col-md-6">
                    <label>Select Transporter</label>
                    <div class="input-group">
                        <select name="transporter_id" id="transporterList" class="form-control aiz-selectpicker" data-live-search="true" required>
                            <option value="">-- Select Transporter --</option>
                        </select>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addTransporterModal">
                                <i class="las la-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>


            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary" id="ewayGenerateBtn">
                     <span class="spinner-border spinner-border-sm d-none mr-2" role="status" id="ewaySpinner"></span>
                        <span id="ewayBtnText">Generate</span>
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </form>
  </div>
</div>


<!-- Add Transporter Modal -->
<div class="modal fade" id="addTransporterModal" tabindex="-1" role="dialog" aria-labelledby="addTransporterModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form id="addTransporterForm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addTransporterModalLabel">Add New Transporter</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="transporterError"></div>
                <div class="form-group">
                    <label for="newTransporterName">Transporter Name</label>
                    <input type="text" class="form-control" id="newTransporterName" required>
                </div>
                <div class="form-group">
                    <label for="newTransporterRegId">GST Registration ID</label>
                    <input type="text" class="form-control" id="newTransporterRegId" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">
                    <span class="spinner-border spinner-border-sm d-none" id="transporterSpinner"></span>
                    <span id="transporterBtnText">Add Transporter</span>
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </form>
  </div>
</div>

<!-- Bulk Sync Result Modal -->
<div class="modal fade" id="bulkSyncModal" tabindex="-1" role="dialog" aria-labelledby="bulkSyncModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success text-white py-2">
        <h6 class="modal-title" id="bulkSyncModalLabel">Zoho Bulk Sync</h6>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body text-center">
        <div class="spinner-border text-success d-none" id="bulkSyncSpinner" role="status"></div>
        <div id="bulkSyncMessage" class="mt-2"></div>
      </div>
      <div class="modal-footer justify-content-center py-2">
        <button type="button" class="btn btn-primary btn-sm" id="bulkSyncOkBtn" data-dismiss="modal" style="display: none;">OK</button>
      </div>
    </div>
  </div>
</div>


@endsection

@section('script')


<script>
$(document).ready(function () {

    // serach part

    // Show or hide clear button
    function toggleClearButton() {
        if ($('#searchInput').val().length > 0) {
            $('#clearBtn').show();
        } else {
            $('#clearBtn').hide();
        }
    }

    // On typing in search input
    $('#searchInput').on('input', function() {
        toggleClearButton();
    });

    // On clear button click
    $('#clearBtn').click(function() {
        $('#searchInput').val('');
        toggleClearButton();
        $('#searchInput').focus();
    });

    // Initialize on page load
    toggleClearButton();

    //serach part end
    $('.openEwayModal').click(function () {
        const invoiceId = $(this).data('invoice-id');
        const invoiceNo = $(this).data('invoice');
        const partyName = $(this).data('party');
        const dispatchAddressId = $(this).data('dispatch-address');
        const pincode = $(this).data('pincode');
        const dispatchPincode = $(this).data('dispatchpincode');
        const stateCode = $(this).data('shipstate');

        $('#ewayInvoiceId').val(invoiceId);
        $('#ewayInvoiceNoDisplay').val(invoiceNo);
        $('#ewayPartyName').val(partyName);
        $('#ewayPartyNameDisplay').val(partyName);
        $('#shipToPincode').val(pincode);
        $('#dispatchFromPincode').val(dispatchPincode);
        $('#shipToStateCodeDropdown').val(stateCode);

        $('select[name="dispatch_from_address_id"]').val(dispatchAddressId);

        // Open modal
        $('#ewayModal').modal('show');
        refreshTransporterList(); // 🔁 load latest transporters

        
       
    });


    $('#ewayModal').on('hidden.bs.modal', function () {
        // Reset all fields
        $('#ewayInvoiceId').val('');
        $('#ewayInvoiceNoDisplay').val('');
        $('#ewayPartyName').val('');
        $('#ewayPartyNameDisplay').val('');
        $('#shipToPincode').val('');
        $('#dispatchFromPincode').val('');
        $('#shipToStateCodeDropdown').val('');
        $('select[name="dispatch_from_address_id"]').val('');
        $('select[name="transporter_id"]').html('<option value="">-- Loading... --</option>');
        $('input[name="vehicle_number"]').val('');
        $('input[name="distance"]').val('');
    });

     $('#ewayForm').on('submit', function () {
        $('#ewayGenerateBtn').prop('disabled', true);
        $('#ewaySpinner').removeClass('d-none'); // Show spinner
        $('#ewayBtnText').text('Generating...');
    });

    function refreshTransporterList() {
        $.get('{{ route('zoho.ewaybill.transporters') }}', function (res) {
            let options = `<option value="">-- Select Transporter --</option>`;
            if (res.data && res.data.transporters) {
                res.data.transporters.forEach(item => {
                    options += `<option value="${item.transporter_id}">${item.transporter_name}</option>`;
                });
            }

            const $dropdown = $('#transporterList');
            $dropdown.html(options);

            // ✅ Re-initialize AIZ selectpicker to apply search
            AIZ.plugins.bootstrapSelect('refresh');
        });
    }
    // Handle Add Transporter Form
    $('#addTransporterForm').on('submit', function (e) {
        e.preventDefault();

        const name = $('#newTransporterName').val().trim();
        const regId = $('#newTransporterRegId').val().trim();
        const $errorBox = $('#transporterError');

        if (!name || !regId) {
            $errorBox.removeClass('d-none').text('Please fill in both fields.');
            return;
        }

        $('#transporterSpinner').removeClass('d-none');
        $('#transporterBtnText').text('Adding...');
        $errorBox.addClass('d-none').text(''); // clear old errors

        $.ajax({
            url: "{{ url('/zoho/create-eway-transporter') }}",
            type: 'GET',
            data: {
                transporter_name: name,
                transporter_registration_id: regId
            },
            success: function (res) {
                $('#transporterSpinner').addClass('d-none');
                $('#transporterBtnText').text('Add Transporter');

                if (res.success) {
                    alert('Transporter added successfully!');
                    $('#addTransporterModal').modal('hide');
                    $('#newTransporterName').val('');
                    $('#newTransporterRegId').val('');
                    $errorBox.addClass('d-none').text('');
                    refreshTransporterList();
                } else {
                    const errorMessage = res.zoho_response?.message || 'Failed to add transporter.';
                    $errorBox.removeClass('d-none').text(errorMessage);
                }
            },
            error: function (xhr) {
                $('#transporterSpinner').addClass('d-none');
                $('#transporterBtnText').text('Add Transporter');

                let errorMsg = 'Something went wrong.';
                try {
                    const json = JSON.parse(xhr.responseText);
                    errorMsg = json?.zoho_response?.message || json?.message || errorMsg;
                } catch (e) {}

                $errorBox.removeClass('d-none').text(errorMsg);
            }
        });
    });


    $('#bulkSyncBtn').on('click', function () {
        if (!confirm('Are you sure you want to sync invoices in bulk to Zoho?')) {
            return;
        }

        $('#bulkSyncModal').modal('show');
        $('#bulkSyncMessage').text('');
        $('#bulkSyncSpinner').removeClass('d-none');
        $('#bulkSyncOkBtn').hide();

        $.ajax({
            url: "{{ route('zoho.invoice.sync') }}", // ✅ Correct route for pending invoice sync
            method: "GET",
            success: function (response) {
                let success = 0, fail = 0;

                if (response.results && Array.isArray(response.results)) {
                    response.results.forEach(r => {
                        if (r.status === 'success') success++;
                        else fail++;
                    });
                }

                $('#bulkSyncMessage').html(`
                    ✅ Synced Successfully: <strong>${success}</strong><br>
                    ❌ Failed: <strong>${fail}</strong>
                `);
            },
            error: function () {
                $('#bulkSyncMessage').html(`<span class="text-danger">❌ Sync failed. Please try again.</span>`);
            },
            complete: function () {
                $('#bulkSyncSpinner').addClass('d-none');
                $('#bulkSyncOkBtn').show();

                // Optional auto reload after a delay
                setTimeout(() => location.reload(), 2500);
            }
        });
    });



});
</script>

@endsection
