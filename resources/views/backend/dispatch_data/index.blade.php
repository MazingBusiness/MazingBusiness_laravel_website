@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-3 mb-4">
    <h1 class="h3 text-primary font-weight-bold">{{ translate('Dispatch Data') }}</h1>
  </div>

  <div class="card shadow-sm border-0">
    <!-- Card Header -->
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0 font-weight-bold">{{ translate('Search By') }}</h5>
      <form method="GET" action="{{ route('dispatch.data') }}" class="d-flex align-items-center position-relative" style="width: 62%;">
            <div class="input-group" style="flex: 1;">
                <!-- Search Input Field -->
                <input 
                    type="text" 
                    name="search" 
                    class="form-control form-control-sm" 
                    placeholder="{{ translate('Dispatch ID, Company Name, Warehouse, Item Name, Part No., Party Code, or Order Code') }}" 
                    value="{{ request('search') }}"
                >
                <!-- Search Reset Button -->
                @if(request('search'))
                    <div class="input-group-append">
                        <a href="{{ route('dispatch.data') }}" class="btn btn-light btn-sm" title="{{ translate('Clear Search') }}">
                            <i class="las la-times"></i>
                        </a>
                    </div>
                @endif
            </div>
            <!-- Submit Button -->
            <button type="submit" class="btn btn-light btn-sm ml-2">
                <i class="las la-search"></i> {{ translate('Search') }}
            </button>
        </form>

    </div>

    <!-- Card Body -->
    <div class="card-body p-0">
      <table class="table table-bordered table-hover mb-0">
        <thead class="bg-secondary text-white">
          <tr>
            <th class="text-center" style="width: 5%;">#</th>
            <th style="width: 25%;">
              <a href="{{ route('dispatch.data', ['sort' => 'addresses.company_name', 'direction' => $sort === 'addresses.company_name' && $direction === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}" class="text-white">
                {{ translate('Company Name') }}
                <i class="las la-sort{{ $sort === 'addresses.company_name' ? ($direction === 'asc' ? '-up' : '-down') : '' }}"></i>
              </a>
            </th>
            <th style="width: 15%;">
              <a href="{{ route('dispatch.data', ['sort' => 'orders.code', 'direction' => $sort === 'orders.code' && $direction === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}" class="text-white">
                {{ translate('Order Code') }}
                <i class="las la-sort{{ $sort === 'orders.code' ? ($direction === 'asc' ? '-up' : '-down') : '' }}"></i>
              </a>
            </th>
            <th style="width: 12%;">
              <a href="{{ route('dispatch.data', ['sort' => 'orders.created_at', 'direction' => $sort === 'orders.created_at' && $direction === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}" class="text-white">
                {{ translate('Order Date') }}
                <i class="las la-sort{{ $sort === 'orders.created_at' ? ($direction === 'asc' ? '-up' : '-down') : '' }}"></i>
              </a>
            </th>
            <th style="width: 15%;">{{ translate('Place Of Dispatch') }}</th>
            <th style="width: 15%;">
              <a href="{{ route('dispatch.data', ['sort' => 'dispatch_data.party_code', 'direction' => $sort === 'dispatch_data.party_code' && $direction === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}" class="text-white">
                {{ translate('Party Code') }}
                <i class="las la-sort{{ $sort === 'dispatch_data.party_code' ? ($direction === 'asc' ? '-up' : '-down') : '' }}"></i>
              </a>
            </th>
            <th style="width: 20%;">
              <a href="{{ route('dispatch.data', ['sort' => 'dispatch_data.dispatch_id', 'direction' => $sort === 'dispatch_data.dispatch_id' && $direction === 'asc' ? 'desc' : 'asc', 'search' => request('search')]) }}" class="text-white">
                {{ translate('Dispatch ID') }}
                <i class="las la-sort{{ $sort === 'dispatch_data.dispatch_id' ? ($direction === 'asc' ? '-up' : '-down') : '' }}"></i>
              </a>
            </th>
            <th class="text-center" style="width: 6%;">{{ translate('Actions') }}</th>
            <th class="text-center" style="width: 6%;">{{ translate('PDF') }}</th>
            <th class="text-center" style="width: 6%;">{{ translate('Whatsapp') }}</th>

          </tr>
        </thead>

      <tbody>
  @forelse ($data as $groupKey => $rows)
    <!-- Collapsible Header -->
    <tr class="collapsible-header bg-light" data-target="dispatch-{{ $groupKey }}">
      <td class="text-center font-weight-bold text-primary">{{ $loop->iteration }}</td>
      <td>{{ $rows->first()->address_company_name ?? '-' }}</td>
      <td>{{ $rows->first()->order_code ?? '-' }}</td>
      <td>{{ $rows->first()->created_at ? \Carbon\Carbon::parse($rows->first()->created_at)->format('Y-m-d') : '-' }}</td>
      <td>{{ $rows->first()->warehouse ?? '-' }}</td>
      <td>{{ $rows->first()->party_code }}</td>
      <td class="font-weight-bold">{{ $rows->first()->dispatch_id }}</td>
      <td class="text-center">
        <button class="btn btn-sm btn-outline-primary toggle-symbol" title="{{ translate('Toggle Details') }}">
          <i class="las la-plus"></i>
        </button>
      </td>
      <td class="text-center">
        <a href="{{ route('dispatch.data.pdf', ['orderId' => $rows->first()->order_id, 'partyCode' => $rows->first()->party_code, 'dispatchId' => encrypt($rows->first()->dispatch_id)]) }}" 
           class="btn btn-sm btn-outline-success" 
           title="{{ translate('Download PDF') }}">
          <i class="las la-file-pdf"></i>
        </a>
      </td>
      <td class="text-center">
          <button 
              class="btn btn-sm btn-outline-success send-dispatch-pdf" 
              title="{{ translate('Send Dispatch PDF via WhatsApp') }}"
              data-url="{{ route('send.dispatch.pdf', ['orderId' => $rows->first()->order_id, 'partyCode' => $rows->first()->party_code, 'dispatchId' => encrypt($rows->first()->dispatch_id)]) }}"
              >
              <i class="lab la-whatsapp"></i>
          </button>
      </td>

    </tr>

    <!-- Collapsible Content -->
    <tr class="details-row" data-parent="dispatch-{{ $groupKey }}" style="display: none;">
      <td colspan="10" class="p-0">
        <table class="table mb-0">
          <thead class="thead-dark">
            <tr>
              <th class="text-center">#</th>
              <th>{{ translate('Part No') }}</th>
              <th>{{ translate('Item Name') }}</th>
              <th class="text-right">{{ translate('Order Quantity') }}</th>
              <th class="text-right">{{ translate('Billed Quantity') }}</th>
              <th class="text-right">{{ translate('Rate') }}</th>
              <th class="text-right">{{ translate('Total') }}{{ translate(' (Inc. Tax)') }}</th>
              <th class="text-center">{{ translate('Actions') }}</th>

            </tr>
          </thead>
          <tbody>
            @foreach ($rows as $rowKey => $childRow)
              <tr data-dispatch-id="{{ $childRow->dispatch_id }}" data-rate="{{ $childRow->rate }}">
                <td class="text-center">{{ $rowKey + 1 }}</td>
                <td class="part-no-column">{{ $childRow->part_no }}</td>
                <td>{{ $childRow->item_name }}</td>
                <td class="text-right">{{ (int)$childRow->order_qty }}</td>
                <td class="text-right">
                  <span class="billed-qty-text">{{ $childRow->billed_qty }}</span>
                  <input type="number" class="form-control form-control-sm billed-qty-input d-none" value="{{ $childRow->billed_qty }}" style="width: 70px;">
                </td>
                <td class="text-right">{{ number_format($childRow->rate, 2) }}</td>
                <td class="text-right total-column">{{ number_format($childRow->bill_amount, 2) }}</td>
                <td class="text-center actions">
                  @if($childRow->manually_cancel_item)
                    <span class="badge badge-danger">{{ translate('Canceled') }}</span>
                  @else
                    <button class="btn btn-soft-success btn-icon btn-circle btn-sm edit-btn" title="{{ translate('Edit Quantity') }}">
                      <i class="las la-edit"></i>
                    </button>
                    <button class="btn btn-soft-primary btn-icon btn-circle btn-sm save-btn d-none" title="{{ translate('Save Changes') }}">
                      <i class="las la-save"></i>
                    </button>
                    <a href="#" class="btn btn-soft-danger btn-icon btn-circle cancel-btn" 
                       data-dispatch-id="{{ $childRow->dispatch_id }}" 
                       data-part-no="{{ $childRow->part_no }}"
                       title="{{ translate('Cancel Changes') }}">
                      <i class="las la-times"></i>
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
      <td colspan="10" class="text-center text-muted py-3">{{ translate('No data available') }}</td>
    </tr>
  @endforelse
</tbody>

      </table>
    </div>

    <!-- Card Footer -->
    <div class="card-footer d-flex justify-content-between align-items-center bg-light">
      <span class="text-muted">{{ translate('Showing') }} {{ $pagination->firstItem() ?? 0 }} {{ translate('to') }} {{ $pagination->lastItem() ?? 0 }} {{ translate('of') }} {{ $pagination->total() ?? 0 }} {{ translate('entries') }}</span>
      <div>
        {{ $pagination->links('pagination::bootstrap-4') }}
      </div>
    </div>
  </div>
@endsection


@section('script')
<script type="text/javascript">
  $(document).ready(function () {
    // Toggle collapse functionality
    $('.collapsible-header').on('click', function () {
      const targetGroup = $(this).data('target');
      const rows = $(`.details-row[data-parent="${targetGroup}"]`);
      const toggleSymbol = $(this).find('.toggle-symbol i');

      rows.toggle();
      if (toggleSymbol.hasClass('la-plus')) {
        toggleSymbol.removeClass('la-plus').addClass('la-minus');
      } else {
        toggleSymbol.removeClass('la-minus').addClass('la-plus');
      }
    });

    // Edit functionality
    $('.edit-btn').on('click', function () {
      const row = $(this).closest('tr');
      row.find('.billed-qty-text').addClass('d-none');
      row.find('.billed-qty-input').removeClass('d-none');
      $(this).addClass('d-none');
      row.find('.save-btn').removeClass('d-none');
    });

    // Save functionality
    $('.save-btn').on('click', function () {
        const row = $(this).closest('tr');
        const dispatchId = row.data('dispatch-id');
        const partNo = row.find('.part-no-column').text();
        const originalBilledQty = parseFloat(row.find('.billed-qty-text').text()); // Original billed quantity
        const newBilledQty = parseFloat(row.find('.billed-qty-input').val());
        const rate = parseFloat(row.data('rate'));
        const total = newBilledQty * rate;

        // Check if there's a change in the billed quantity
        if (originalBilledQty === newBilledQty) {
            AIZ.plugins.notify('warning', '{{ translate("No changes detected in billed quantity.") }}');
            row.find('.billed-qty-text').removeClass('d-none');
            row.find('.billed-qty-input').addClass('d-none');
            $(this).addClass('d-none');
            row.find('.edit-btn').removeClass('d-none');
            return; // Exit the function as there's no change
        }

        // Update DOM
        row.find('.billed-qty-text').text(newBilledQty).removeClass('d-none');
        row.find('.billed-qty-input').addClass('d-none');
        row.find('.total-column').text(total.toFixed(2));
        $(this).addClass('d-none');
        row.find('.edit-btn').removeClass('d-none');

        // Send AJAX to update backend
        $.ajax({
            url: '{{ route('dispatch.data.update') }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                dispatch_id: dispatchId,
                part_no: partNo,
                billed_qty: newBilledQty
            },
            success: function (response) {
                if (response.success) {
                    AIZ.plugins.notify('success', response.message);
                } else {
                    AIZ.plugins.notify('danger', response.message);
                }
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
                AIZ.plugins.notify('danger', '{{ translate("An error occurred while updating the billed quantity.") }}');
            }
        });
    });


      // Cancel functionality
      $(document).on('click', '.cancel-btn', function (e) {
          e.preventDefault();

          const cancelButton = $(this); // Reference to the cancel button
          const dispatchId = cancelButton.data('dispatch-id'); // Fetch dispatch ID
          const partNo = cancelButton.data('part-no'); // Fetch part number
          const row = cancelButton.closest('tr'); // Reference to the row
          const actionsTd = row.find('td.actions'); // Get the 'actions' column in the same row

          // Confirm action
          if (!confirm('{{ translate("Are you sure you want to cancel this item?") }}')) {
              return;
          }

          // AJAX request to update the column
          $.ajax({
              url: '{{ route('dispatch.data.cancel') }}', // Update route
              method: 'POST',
              data: {
                  _token: '{{ csrf_token() }}',
                  dispatch_id: dispatchId,
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



      $(document).on('click', '.send-dispatch-pdf', function () {
    let button = $(this);
    let url = button.data('url');

    // Disable button while processing
    button.prop('disabled', true).text('Sending...');

    $.ajax({
        url: url,
        method: 'GET', // Use GET or POST as per your route configuration
        success: function (response) {
            if (response.success) {
              AIZ.plugins.notify('success', response.message);
                
            } else if (response.error) {
                alert(response.error);
            }
        },
        error: function (xhr, status, error) {
            alert('An error occurred: ' + error);
        },
        complete: function () {
            // Enable button after processing
            button.prop('disabled', false).html('<i class="lab la-whatsapp"></i>');
        }
    });
});


  });
</script>
@endsection
