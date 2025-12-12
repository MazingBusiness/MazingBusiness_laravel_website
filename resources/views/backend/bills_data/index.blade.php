@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-3 mb-4">
    <h1 class="h3 text-primary font-weight-bold">{{ translate('Bills Order') }}</h1>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0 font-weight-bold">{{ translate('Search By') }}</h5>
      <form method="GET" action="{{ route('bills.data') }}" class="d-flex align-items-center position-relative" style="width: 70%;">
        <div class="input-group" style="flex: 1;">
          <input 
              type="text" 
              name="search" 
              class="form-control form-control-sm" 
              placeholder="{{ translate('Invoice No, Billing Company, Order Code, Part No, Item Name, Company Name, Warehouse, Invoice Date') }}" 
              value="{{ request('search') }}"
          >
          @if(request('search'))
              <div class="input-group-append">
                  <a href="{{ route('bills.data') }}" class="btn btn-light btn-sm" title="{{ translate('Clear Search') }}">
                      <i class="las la-times"></i>
                  </a>
              </div>
          @endif
        </div>
        <button type="submit" class="btn btn-light btn-sm ml-2">
          <i class="las la-search"></i> {{ translate('Search') }}
        </button>
      </form>
    </div>

    <div class="card-body p-0">
      <table class="table table-bordered table-hover mb-0">
        <thead class="bg-secondary text-white">
          <tr>
            <th>#</th>
            <th>
              <a href="{{ route('bills.data', ['sort' => 'addresses.company_name', 'direction' => $sort === 'addresses.company_name' && $direction === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}">
                {{ translate('Company Name') }}
                @if($sort === 'addresses.company_name')
                    <i class="las la-sort{{ $direction === 'asc' ? '-up' : '-down' }}"></i>
                @endif
              </a>
            </th>
            <th>
              <a href="{{ route('bills.data', ['sort' => 'orders.code', 'direction' => $sort === 'orders.code' && $direction === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}">
                {{ translate('Order Code') }}
                @if($sort === 'orders.code')
                    <i class="las la-sort{{ $direction === 'asc' ? '-up' : '-down' }}"></i>
                @endif
              </a>
            </th>
            <th>
              <a href="{{ route('bills.data', ['sort' => 'bills_data.invoice_date', 'direction' => $sort === 'bills_data.invoice_date' && $direction === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}">
                {{ translate('Invoice Date') }}
                @if($sort === 'bills_data.invoice_date')
                    <i class="las la-sort{{ $direction === 'asc' ? '-up' : '-down' }}"></i>
                @endif
              </a>
            </th>
            <th>
              <a href="{{ route('bills.data', ['sort' => 'warehouse', 'direction' => $sort === 'warehouse' && $direction === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}">
                {{ translate('Place Of Invoice') }}
                @if($sort === 'warehouse')
                    <i class="las la-sort{{ $direction === 'asc' ? '-up' : '-down' }}"></i>
                @endif
              </a>
            </th>
            <th>
              <a href="{{ route('bills.data', ['sort' => 'bills_data.billing_company', 'direction' => $sort === 'bills_data.billing_company' && $direction === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}">
                {{ translate('Billing Company') }}
                @if($sort === 'bills_data.billing_company')
                    <i class="las la-sort{{ $direction === 'asc' ? '-up' : '-down' }}"></i>
                @endif
              </a>
            </th>
            <th>{{ translate('Invoice Amount') }}</th>
            <th>
              <a href="{{ route('bills.data', ['sort' => 'bills_data.invoice_no', 'direction' => $sort === 'bills_data.invoice_no' && $direction === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}">
                {{ translate('Invoice No') }}
                @if($sort === 'bills_data.invoice_no')
                    <i class="las la-sort{{ $direction === 'asc' ? '-up' : '-down' }}"></i>
                @endif
              </a>
            </th>
            <th>{{ translate('Actions') }}</th>
            <th>{{ translate('PDF') }}</th>
            <th>{{ translate('Whatsapp') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($data as $groupKey => $rows)
            <tr class="collapsible-header bg-light" data-target="invoice-{{ $groupKey }}" data-inv_no="invoice-{{ $rows->first()->invoice_no }}">
              <td class="text-center font-weight-bold text-primary">{{ $loop->iteration }}</td>
              <td>{{ $rows->first()->company_name ?? 'N/A' }}</td>
              <td>{{ $rows->first()->order_code }}</td>
              <td>{{ $rows->first()->invoice_date }}</td>
              <td>{{ $rows->first()->warehouse ?? 'Unknown' }}</td>
              <td>{{ $rows->first()->billing_company }}</td>
              <td class="invoice-amount">{{ number_format($rows->first()->invoice_amount, 2) }}</td>
              <td >{{ $rows->first()->invoice_no }}</td>
              <td>
                <button class="btn btn-sm btn-outline-primary toggle-symbol">
                  <i class="las la-plus"></i>
                </button>
              </td>
              <td class="text-center">
                <a href="{{ route('bills.data.pdf', encrypt($rows->first()->invoice_no)) }}" 
                   class="btn btn-sm btn-outline-success" 
                   title="{{ translate('Download PDF') }}">
                  <i class="las la-file-pdf"></i>
                </a>
              </td>


               <td class="text-center">
    <button 
        data-url="{{ route('whatsapp.send.invoice', encrypt($rows->first()->invoice_no)) }}" 
        class="btn btn-sm btn-outline-success send-invoice-pdf" 
        title="{{ translate('Send') }}">
        <i class="lab la-whatsapp"></i>
    </button>
</td>

            </tr>
            <tr class="details-row" data-parent="invoice-{{ $groupKey }}" style="display: none;">
              <td colspan="11" class="p-0">
                <table class="table mb-0">
                  <thead style="background-color: #fcfcfc;">
                    <tr>
                      <th>#</th>
                      <th>{{ translate('Part No') }}</th>
                      <th>{{ translate('Item Name') }}</th>
                      <th>{{ translate('Order Quantity') }}</th>
                      <th>{{ translate('Billed Quantity') }}</th>
                      <th>{{ translate('Rate') }}</th>
                   
                      <th>{{ translate('Sub Total') }}</th>
                      <th>{{ translate('Action') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($rows as $rowKey => $childRow)
               @php
                  $billedQty = is_numeric($childRow->billed_qty) ? $childRow->billed_qty : 0;
                  $rate = is_numeric($childRow->net_rate) ? $childRow->net_rate : 0;
                  $billedAmount = $billedQty * $rate; // Calculate billed amount
                 
                  $subTotal = $billedAmount; // Total amount
              @endphp

                  <tr data-invoice-id="{{ $childRow->invoice_no }}" data-part-no="{{ $childRow->part_no }}">
                      <td>{{ $rowKey + 1 }}</td>
                      <td>{{ $childRow->part_no }}</td>
                      <td>{{ $childRow->item_name }}</td>
                      <td>{{ (int)$childRow->order_qty }}</td>
                      <td>
                          <span class="billed-qty-text">{{ (int)$billedQty }}</span>
                          <input type="number" class="form-control form-control-sm billed-qty-input d-none" 
                                 value="{{ (int)$billedQty }}" style="width: 80px;">
                      </td>
                      <td class="rate-column">{{ number_format($rate, 2) }}</td>
                      
                      <td class="bill-amount-column">{{ number_format($subTotal, 2) }}</td> <!-- Sub Total -->
                      <td class="text-center actions">
                         @if($childRow->manually_cancel_item)
                                <!-- Show Canceled Badge -->
                                <span style="width: 60px;" class="badge badge-danger">{{ translate('Canceled') }}</span>
                          @else
                              <button class="btn btn-soft-success btn-icon btn-circle btn-sm edit-btn" title="{{ translate('Edit Quantity') }}">
                                  <i class="las la-edit"></i>
                              </button>
                              <button class="btn btn-soft-primary btn-icon btn-circle btn-sm save-btn d-none" title="{{ translate('Save Changes') }}">
                                  <i class="las la-save"></i>
                              </button>

                              <!-- Cancel Button -->
                                <a href="javascript:void(0);" 
                                   class="btn btn-soft-danger btn-icon btn-circle cancel-btn" 
                                   data-invoice-id="{{ $childRow->invoice_no }}" 
                                   data-part-no="{{ $childRow->part_no }}"
                                   title="{{ translate('Cancel Changes') }}"
                                   style="padding: 8px 12px; font-size: 16px; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;">
                                    <i class="las la-times" style="font-size: 14px;"></i>
                                </a>
                          @endif
                      </td>
                  </tr>


                    @endforeach
                  </tbody>
                </table>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="12">{{ translate('No data available') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer bg-light d-flex justify-content-between">
      <span>{{ translate('Showing') }} {{ $pagination->firstItem() }} {{ translate('to') }} {{ $pagination->lastItem() }} {{ translate('of') }} {{ $pagination->total() }} {{ translate('entries') }}</span>
      {{ $pagination->links() }}
    </div>
  </div>
@endsection

@section('script')
<script>
  $(document).ready(function () {
    $('.collapsible-header').on('click', function () {
      const target = $(this).data('target');
      const rows = $(`.details-row[data-parent="${target}"]`);
      const toggle = $(this).find('.toggle-symbol i');

      rows.toggle();
      toggle.toggleClass('la-plus la-minus');
    });



   // Edit functionality
    $('.edit-btn').on('click', function () {
        const row = $(this).closest('tr');
        row.find('.billed-qty-text').addClass('d-none'); // Hide static quantity
        row.find('.billed-qty-input').removeClass('d-none'); // Show input field
        $(this).addClass('d-none'); // Hide edit button
        row.find('.save-btn').removeClass('d-none'); // Show save button
    });

    // Save functionality
    $('.save-btn').on('click', function () {
        const row = $(this).closest('tr');
        const invoiceId = row.data('invoice-id'); // Get invoice_id from row data attribute
        const partNo = row.data('part-no'); // Get part_no from row data attribute
        const newBilledQty = parseFloat(row.find('.billed-qty-input').val());
        const rate = parseFloat(row.find('.rate-column').text().replace(/,/g, '')); // Get rate from rate column
        
        const newBillAmount = newBilledQty * rate ; // Calculate new sub total

        // Update fields in DOM
        row.find('.billed-qty-text').text(newBilledQty).removeClass('d-none'); // Update quantity text
        row.find('.billed-qty-input').addClass('d-none'); // Hide input field
        row.find('.bill-amount-column').text(newBillAmount.toFixed(2)); // Update sub total
        $(this).addClass('d-none'); // Hide save button
        row.find('.edit-btn').removeClass('d-none'); // Show edit button

        // Send AJAX request to update the backend
        $.ajax({
            url: '{{ route("bills.data.update") }}', // Replace with the correct route
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                invoice_no: invoiceId,
                part_no: partNo,
                billed_qty: newBilledQty
            },
            success: function (response) {
                if (response.success) {
                if (response.invoice_amount && !isNaN(response.invoice_amount)) {

                       // Ensure the selector targets the correct element
                    const invoiceRow = $(`.collapsible-header[data-inv_no="invoice-${invoiceId}"]`);
                    console.log('Invoice row found:', invoiceRow.length); // Log if the row is found

                   
                    // Update the Invoice Amount in the main table
                    $(`.collapsible-header[data-inv_no="invoice-${invoiceId}"]`)
                        .find('.invoice-amount')
                        .text(parseFloat(response.invoice_amount).toFixed(2)); // Ensure it's parsed as a float
                }

                AIZ.plugins.notify('success', response.message);
            } else {
                AIZ.plugins.notify('danger', response.message);
            }
            },
            error: function (xhr) {
                console.error(xhr.responseText);
                AIZ.plugins.notify('danger', '{{ translate("An error occurred while updating the billed quantity.") }}');
            }
        });
    });



     // Cancel functionality
    $(document).on('click', '.cancel-btn', function (e) {
        e.preventDefault();

        const cancelButton = $(this); // Reference to the cancel button
        const invoiceId = cancelButton.data('invoice-id'); // Fetch invoice ID
        const partNo = cancelButton.data('part-no'); // Fetch part number
        const row = cancelButton.closest('tr'); // Reference to the row
        const actionsTd = row.find('td.actions'); // Get the 'actions' column in the same row

        // Confirm action
        if (!confirm('{{ translate("Are you sure you want to cancel this item?") }}')) {
            return;
        }

        // AJAX request to update the column
        $.ajax({
            url: '{{ route("bills.cancel-item") }}', // Update route
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                invoice_id: invoiceId,
                part_no: partNo
            },
            success: function (response) {
                if (response.success) {
                    AIZ.plugins.notify('success', response.message);

                    // Clear the current content of the td with 'actions' class
                    actionsTd.empty();

                    // Append the "Canceled" badge
                    actionsTd.append(`
                        <span style="width: 60px;" class="badge badge-danger">{{ translate('Canceled') }}</span>
                    `);
                } else {
                    AIZ.plugins.notify('danger', response.message);
                }
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
                AIZ.plugins.notify('danger', '{{ translate("An error occurred while canceling the item.") }}');
            }
        });
    });

   $(document).on('click', '.send-invoice-pdf', function () {
    let button = $(this); // Reference to the button
    let url = button.data('url'); // Get the data-url attribute value

    // Disable button while processing
    button.prop('disabled', true).text('Sending...');

    // AJAX request
    $.ajax({
        url: url,  // Route URL
        method: 'GET', // Use GET method as per route configuration
        success: function (response) {
            if (response.success) {
                // Show success notification
                AIZ.plugins.notify('success', response.message);
            } else if (response.error) {
                // Show error alert
                alert('Error: ' + response.error);
            }
        },
        error: function (xhr, status, error) {
            // Handle unexpected errors
            alert('An error occurred: ' + (xhr.responseJSON?.error || error));
        },
        complete: function () {
            // Re-enable button after processing
            button.prop('disabled', false).html('<i class="lab la-whatsapp"></i>');
        }
    });
});


  });
</script>
@endsection
