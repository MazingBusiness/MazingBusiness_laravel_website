@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-3 mb-4">
    <h1 class="h3 text-primary font-weight-bold">{{ translate('Pending Dispatch Orders') }}</h1>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 font-weight-bold">{{ translate('Search By') }}</h5>
        <form method="GET" action="{{ route('pending.dispatch.orders') }}" class="d-flex align-items-center position-relative" style="width: 70%;">
            <div class="input-group" style="flex: 1;">
                <input 
                    type="text" 
                    name="search" 
                    class="form-control form-control-sm" 
                    placeholder="{{ translate('Order Code, Item Name, Part No, Party code, Party Name') }}" 
                    value="{{ request('search') }}"
                >
                @if(request('search'))
                    <div class="input-group-append">
                        <a href="{{ route('pending.dispatch.orders') }}" class="btn btn-light btn-sm" title="{{ translate('Clear Search') }}">
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
                        <a href="{{ route('pending.dispatch.orders', array_merge(request()->all(), ['sort' => 'orders.code', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">
                            {{ translate('Order Code') }}
                            @if(request('sort') == 'orders.code')
                                <i class="las la-sort-{{ request('direction') == 'asc' ? 'up' : 'down' }}"></i>
                            @endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('pending.dispatch.orders', array_merge(request()->all(), ['sort' => 'approvals_data.party_code', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">
                            {{ translate('Party Code') }}
                            @if(request('sort') == 'approvals_data.party_code')
                                <i class="las la-sort-{{ request('direction') == 'asc' ? 'up' : 'down' }}"></i>
                            @endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('pending.dispatch.orders', array_merge(request()->all(), ['sort' => 'addresses.company_name', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">
                            {{ translate('Company Name') }}
                            @if(request('sort') == 'addresses.company_name')
                                <i class="las la-sort-{{ request('direction') == 'asc' ? 'up' : 'down' }}"></i>
                            @endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('pending.dispatch.orders', array_merge(request()->all(), ['sort' => 'orders.created_at', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">
                            {{ translate('Order Date') }}
                            @if(request('sort') == 'orders.created_at')
                                <i class="las la-sort-{{ request('direction') == 'asc' ? 'up' : 'down' }}"></i>
                            @endif
                        </a>
                    </th>
                    <th>
                        <a href="{{ route('pending.dispatch.orders', array_merge(request()->all(), ['sort' => 'approvals_data.approval_date', 'direction' => request('direction') == 'asc' ? 'desc' : 'asc'])) }}">
                            {{ translate('Approval Date') }}
                            @if(request('sort') == 'approvals_data.approval_date')
                                <i class="las la-sort-{{ request('direction') == 'asc' ? 'up' : 'down' }}"></i>
                            @endif
                        </a>
                    </th>
                    <th>{{ translate('Total Bill Amount') }}</th>
                    <th>{{ translate('Actions') }}</th>
                    <th>{{ translate('PDF')}}</th>
                    <th>{{ translate('WhatsApp') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data as $groupKey => $rows)
                    <tr class="collapsible-header bg-light" data-target="order-{{ $groupKey }}">
                        <td class="text-center font-weight-bold text-primary">{{ $loop->iteration }}</td>
                        <td>{{ $rows->first()->order_code ?? 'N/A' }}</td>
                        <td>{{ $rows->first()->party_code }}</td>
                        <td>{{ $rows->first()->company_name }}</td>
                        <td>{{ $rows->first()->order_date }}</td>
                        <td>{{ $rows->first()->approval_date }}</td>
                        <td>{{ number_format($rows->sum('bill_amount'), 2) }}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary toggle-symbol">
                                <i class="las la-plus"></i>
                            </button>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('download.approval.pdf', ['orderId' => $rows->first()->order_id, 'partyCode' => $rows->first()->party_code]) }}" 
                               class="btn btn-sm btn-outline-success" 
                               title="Download PDF">
                                <i class="las la-file-pdf"></i>
                            </a>
                        </td>
                        <td class="text-center">
                            <button 
                                class="btn btn-sm btn-outline-success send-whatsapp" 
                                data-url="{{ route('pending.dispatch.orders.send.whatsapp', ['order_id' => $rows->first()->order_id, 'party_code' => $rows->first()->party_code]) }}" 
                                title="{{ translate('Send via WhatsApp') }}">
                                <i class="lab la-whatsapp"></i>
                            </button>
                        </td>
                    </tr>
                    <tr class="details-row" data-parent="order-{{ $groupKey }}" style="display: none;">
                        <td colspan="10" class="p-0">
                            <table class="table mb-0">
                                <thead style="background-color: #fcfcfc;">
                                    <tr>
                                        <th>#</th>
                                        <th>{{ translate('Part No') }}</th>
                                        <th>{{ translate('Item Name') }}</th>
                                        <th>{{ translate('Approved Quantity') }}</th>
                                        <th>{{ translate('Rate') }}</th>
                                        <th>{{ translate('Bill Amt.') }}</th>
                                         <th>{{ translate('Status') }}</th>
                                        <th>{{ translate('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $rowKey => $childRow)
                                        <tr>
                                            <td>{{ $rowKey + 1 }}</td>
                                            <td>{{ $childRow->part_no }}</td>
                                            <td>{{ $childRow->item_name }}</td>
                                            <td>
                                                <span class="order-qty-text">{{ (int)$childRow->order_qty }}</span>
                                                <input type="number" class="form-control form-control-sm order-qty-input d-none" value="{{ $childRow->order_qty }}" style="width: 80px;">
                                            </td>
                                            <td>{{ number_format($childRow->rate, 2) }}</td>
                                            <td>{{ number_format($childRow->bill_amount, 2) }}</td>
                                            <td>
                                                <span style="width: 120px;" class="badge {{ $childRow->status === 'Completed' ? 'badge-success' : ($childRow->status === 'Material in transit' ? 'badge-warning' : ($childRow->status === 'Pending for Dispatch' ? 'badge-info' : 'badge-danger')) }}">
                                                    {{ $childRow->status }}
                                                </span>
                                            </td>
                                            <td class="actions">
                                                 @if($childRow->manually_cancel_item)
                                                        <!-- Show Canceled Badge -->
                                                        <span style="width: 60px;" class="badge badge-danger">{{ translate('Canceled') }}</span>
                                                @elseif($childRow->status === 'Material in transit' || $childRow->status === 'Completed')
                                                        <!-- Do not show edit or cancel buttons for certain statuses -->
                                                        <span style="width: 80px;" class="badge badge-secondary">{{ translate('No Action') }}</span>

                                                  @else
                                                <button class="btn btn-sm btn-icon btn-circle btn-soft-warning edit-item" data-id="{{ $childRow->id }}" title="{{ translate('Edit') }}">
                                                    <i class="las la-edit" style="font-size:14px;"></i>
                                                </button>
                                                <button class="btn btn-sm btn-icon btn-circle btn-soft-success save-item d-none" data-id="{{ $childRow->id }}" title="{{ translate('Save') }}">
                                                    <i class="las la-save" style="font-size:14px;"></i>
                                                </button>

                                                 <!-- Cancel Button -->
                                                <button 
                                                    class="btn btn-sm btn-soft-danger btn-icon btn-circle cancel-btn" 
                                                    data-id="{{ $childRow->id }}" 
                                                    data-url="{{ route('pending.dispatch.orders.cancel') }}" 
                                                    title="{{ translate('Cancel') }}">
                                                    <i class="las la-times" style="font-size: 14px;"></i>
                                                </button>
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
                        <td colspan="8">{{ translate('No data available') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($pagination->hasPages())
    <div class="card-footer bg-light d-flex justify-content-between">
        <span>{{ translate('Showing') }} {{ $pagination->firstItem() }} {{ translate('to') }} {{ $pagination->lastItem() }} {{ translate('of') }} {{ $pagination->total() }} {{ translate('entries') }}</span>
        {{ $pagination->links() }}
    </div>
@endif
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

         $('.send-whatsapp').on('click', function () {
            const button = $(this); // Reference the clicked button
            const url = button.data('url'); // Fetch the URL with order_id and party_code

            // Disable the button while processing
            button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');
             //window.open(url, '_blank'); // Opens the URL in a new tab

            // Send the AJAX request
            $.ajax({
                url: url,
                method: 'GET', // Use GET as defined in your route
                success: function (response) {
                    if (response.success) {
                        // Notify the user of success
                        AIZ.plugins.notify('success', response.message);
                    } else {
                        // Notify the user of failure
                        AIZ.plugins.notify('danger', response.message || 'An unexpected error occurred.');
                    }
                },
                error: function (xhr) {
                    let errorMessage = '{{ translate("An error occurred while sending the message.") }}';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    // Notify the user of the error
                    AIZ.plugins.notify('danger', errorMessage);
                    console.error('Error:', xhr.responseText);
                },
                complete: function () {
                    // Re-enable the button and reset its text
                    button.prop('disabled', false).html('<i class="lab la-whatsapp"></i>');
                }
            });
        });

        $('.edit-item').on('click', function () {
            const row = $(this).closest('tr');
            row.find('.order-qty-text').addClass('d-none'); // Hide static quantity
            row.find('.order-qty-input').removeClass('d-none'); // Show input field
            $(this).addClass('d-none'); // Hide edit button
            row.find('.save-item').removeClass('d-none'); // Show save button
        });

        $('.save-item').on('click', function () {
            const row = $(this).closest('tr');
            const id = $(this).data('id');
            const newQty = row.find('.order-qty-input').val();

            // AJAX request to save the new quantity
            $.ajax({
                url: '{{ route("pending.dispatch.orders.update") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    id: id,
                    order_qty: newQty
                },
                success: function (response) {
                    if (response.success) {
                        AIZ.plugins.notify('success', response.message);

                        // Update the quantity display
                        row.find('.order-qty-text').text(newQty).removeClass('d-none');
                        row.find('.order-qty-input').addClass('d-none');
                        row.find('.save-item').addClass('d-none');
                        row.find('.edit-item').removeClass('d-none');
                    } else {
                        AIZ.plugins.notify('danger', response.message);
                    }
                },
                error: function (xhr) {
                    AIZ.plugins.notify('danger', '{{ translate("An error occurred while updating the quantity.") }}');
                }
            });
        });

        // $('.cancel-btn').on('click', function () {
        //     const id = $(this).data('id');
        //     if (confirm('{{ translate("Are you sure you want to delete this item?") }}')) {
        //         // Add your delete functionality here
        //         alert('Delete functionality for ID: ' + id);
        //     }
        // });

            // Cancel functionality
        $(document).on('click', '.cancel-btn', function (e) {
            e.preventDefault();

            const cancelButton = $(this); // Reference to the cancel button
            const itemId = cancelButton.data('id'); // Fetch item ID from the data attribute
            const row = cancelButton.closest('tr'); // Reference to the row
            const actionsTd = row.find('td.actions'); // Locate the actions column

            // Confirm action
            if (!confirm('Are you sure you want to cancel this item?')) {
                return;
            }

            // AJAX request to cancel the item
            $.ajax({
                url: '{{ route("pending.dispatch.orders.cancel") }}', // Use the named route for the cancel endpoint
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}', // Include CSRF token for security
                    id: itemId // Pass the item ID
                },
                success: function (response) {
                    if (response.success) {
                        // Notify the user of success
                        AIZ.plugins.notify('success', response.message);

                        // Clear the actions column and add a "Canceled" badge
                        actionsTd.empty();
                        actionsTd.append(`
                            <span style="width: 60px;" class="badge badge-danger">Canceled</span>
                        `);
                    } else {
                        // Notify the user of failure
                        AIZ.plugins.notify('danger', response.message);
                    }
                },
                error: function (xhr, status, error) {
                    // Log the error for debugging
                    console.error(xhr.responseText);

                    // Notify the user of an error
                    AIZ.plugins.notify('danger', 'An error occurred while canceling the item.');
                }
            });
        });
    });
</script>
@endsection
