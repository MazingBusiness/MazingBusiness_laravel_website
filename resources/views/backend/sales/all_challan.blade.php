@extends('backend.layouts.app')

@section('content')
<style>
    /* Centering the loader within the button */
    .loader {
        border: 3px solid rgba(255, 255, 255, 0.3); /* Semi-transparent border */
        border-top: 3px solid #ffffff; /* Solid white border at the top */
        border-radius: 50%;
        width: 20px;
        height: 20px;
        animation: spin 1s linear infinite;
        display: inline-block;
        vertical-align: middle;
    }

    /* Animation for the loader */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Styling for the button when it's in loading state */
    .loading {
        pointer-events: none;
        opacity: 0.7;
        position: relative;
    }

    /* Aligning loader within the button */
    .loading .loader {
        position: absolute;
        top: 20%;
        left: 20%;
        transform: translate(-50%, -50%);
    }
</style>

<div class="card">
    <form class="" action="" id="sort_orders" method="GET">
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        <div class="card-header row gutters-5">
            <div class="col">
                <h5 class="mb-md-0 h6">{{ translate('All Challans') }}</h5>
            </div>

            <div class="col-lg-4">
                <div class="form-group mb-0">
                    <input type="text" class="form-control" id="search" name="search"
                        value="{{ request('search') }}" placeholder="Challan No or Customer Name or Sub Order No">
                </div>
            </div>
            <div class="col-auto">
                <div class="form-group mb-0">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
            <?php /* <div class="dropdown mb-2 mb-md-0">
                <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
                    {{translate('Bulk Action')}}
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#" onclick="bulk_delete()"> {{translate('Delete selection')}}</a>
                </div>
            </div>
            <!-- Dropdown for Salzing Order Punch Status -->
            <div class="col-lg-2 ml-auto">
                <select class="form-control aiz-selectpicker" name="salzing_status" id="salzing_status">
                    <option value="">{{translate('Filter by Salzing Order Punch Status')}}</option>
                    @foreach ($salzing_statuses as $status)
                        <option value="{{ $status }}" @if (request('salzing_status') == $status) selected @endif>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="display:none;" class="col-lg-2 ml-auto">
                <select class="form-control aiz-selectpicker" name="delivery_status" id="delivery_status">
                    <option value="">{{translate('Filter by Delivery Status')}}</option>
                    <option value="pending" @if ($delivery_status == 'pending') selected @endif>{{translate('Pending')}}</option>
                    <option value="confirmed" @if ($delivery_status == 'confirmed') selected @endif>{{translate('Confirmed')}}</option>
                    <option value="picked_up" @if ($delivery_status == 'picked_up') selected @endif>{{translate('Picked Up')}}</option>
                    <option value="on_the_way" @if ($delivery_status == 'on_the_way') selected @endif>{{translate('On The Way')}}</option>
                    <option value="delivered" @if ($delivery_status == 'delivered') selected @endif>{{translate('Delivered')}}</option>
                    <option value="cancelled" @if ($delivery_status == 'cancelled') selected @endif>{{translate('Cancel')}}</option>
                </select>
            </div>
            <div style="display:none;"; class="col-lg-2 ml-auto">
                <select class="form-control aiz-selectpicker" name="payment_status" id="payment_status">
                    <option value="">{{translate('Filter by Payment Status')}}</option>
                    <option value="paid"  @isset($payment_status) @if($payment_status == 'paid') selected @endif @endisset>{{translate('Paid')}}</option>
                    <option value="unpaid"  @isset($payment_status) @if($payment_status == 'unpaid') selected @endif @endisset>{{translate('Un-Paid')}}</option>
                </select>
              </div>
            <div class="col-lg-2">
                <div class="form-group mb-0">
                    <input type="text" class="aiz-date-range form-control" value="{{ $date }}" name="date" placeholder="{{ translate('Filter by date') }}" data-format="DD-MM-Y" data-separator=" to " data-advanced-range="true" autocomplete="off">
                </div>
            </div>
            <div class="col-lg-2">
                <div class="form-group mb-0">
                    <input type="text" class="form-control" id="search" name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset placeholder="{{ translate('Type Order code & hit Enter') }}">
                </div>
            </div>
            <div class="col-auto">
                <div class="form-group mb-0">
                    <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
                </div>
            </div> */ ?>
        </div>

        <div class="card-body">
            <div id="continue-button-wrapper" style="display:none; margin-top: 20px;">
                <button type="button" id="continue-button" class="btn btn-success">Continue</button>
            </div>
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <?php /*<th>
                            <div class="form-group">
                                <div class="aiz-checkbox-inline">
                                    <label class="aiz-checkbox">
                                        <input type="checkbox" class="check-all">
                                        <span class="aiz-square-check"></span>
                                    </label>
                                </div>
                            </div>
                        </th>*/?>
                        <th>{{ translate('Challan No') }}</th>
                        <th>{{ translate('Sub Order No') }}</th>
                        <th>{{ translate('Customer') }}</th>
                        <th>{{ translate('Amount') }}</th>
                        <th>{{ translate('Manager Name') }}</th> <!-- New column for Manager Name -->
                        <th>{{ translate('Warehouse Name') }}</th> <!-- New column for Warehouse Name -->
                        <th>Created Date And Time</th>
                        <th class="text-right">{{translate('Options')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orderData as $key => $order)
                        <tr>
                            <td>
                                <input type="checkbox" class="challan-checkbox" value="{{ $order->id }}">
                            </td>
                            <?php /*<td>
                                <div class="form-group">
                                    <div class="aiz-checkbox-inline">
                                        <label class="aiz-checkbox">
                                            <input type="checkbox" class="check-one" name="id[]" value="{{$order->id}}">
                                            <span class="aiz-square-check"></span>
                                        </label>
                                    </div>
                                </div>
                            </td>*/ ?>
                            <td>
                                {{ $order->challan_no }}
                            </td>
                            <td>
                                {{ $order->sub_order->order_no??''; }}
                            </td>
                            <td>
                                {{ $order->user->company_name }}
                            </td>
                            <td>
                                {{ single_price($order->challan_details->sum('final_amount')) }}
                            </td>
                            <td>
                                {{ $order->user->getManager->name }}
                            </td>
                            <td>
                                {{ $order->order_warehouse->name }}
                            </td>
                            <td>
                                {{ $order->created_at->format('d-m-Y h:m A') }}
                            </td>
                            <td class="text-right">
                                @php
                                    $order_detail_route = '';
                                @endphp
                                <!-- <a class="btn btn-soft-primary btn-icon btn-circle btn-sm" href="{{ $order_detail_route }}" title="{{ translate('View') }}">
                                    <i class="las la-eye"></i>
                                </a> -->
                                <a class="btn btn-soft-primary btn-icon btn-circle btn-sm" href="{{ route('order.challanDetails', $order->id) }}" title="{{ translate('View') }}">
                                    <i class="las la-eye"></i>
                                </a>
                                <?php /* <a class="btn btn-soft-info btn-icon btn-circle btn-sm" href="{{ route('invoice.download', $order->id) }}" title="{{ translate('Download Invoice') }}">
                                    <i class="las la-download"></i>
                                </a>
                                <?php /* <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete" data-href="{{route('orders.destroy', $order->id)}}" title="{{ translate('Delete') }}">
                                    <i class="las la-trash"></i> */ ?>
                                </a>

                                <a href="{{ route('challan.cancel', $order->id) }}"
                                   class="btn btn-soft-danger btn-icon btn-circle btn-sm"
                                   onclick="return confirm('Are you sure you want to cancel this challan?')"
                                   title="Cancel Challan">
                                    <i class="las la-times"></i>
                                </a>

                                @php
                                    $logistic41 = \App\Models\Manager41OrderLogistic::where('challan_id', $order->id)
                                        ->orderByDesc('id')
                                        ->first();
                                    $isAdded = $logistic41 && (int)($logistic41->add_status ?? 0) === 1;
                                @endphp

                                @if($is41Manager ?? false)
                                    <a class="btn btn-soft-info btn-icon btn-circle btn-sm"  target="_blank"
                                       href="{{ route('manager41.challan.pdf', $order->id) }}"
                                       title="Download PDF">
                                        <i class="las la-file-pdf"></i>
                                    </a>

                                    {{-- NEW: Add Logistics (Manager-41) --}}
                                    @if($isAdded)
                                        {{-- EDIT Logistics --}}
                                        <a class="btn btn-soft-warning btn-icon btn-circle btn-sm"
                                           href="{{ route('manager41.order.logistics.edit', encrypt($logistic41->id)) }}"
                                           title="Edit Logistics">
                                            <i class="las la-edit"></i>
                                        </a>
                                    @else
                                        {{-- ADD Logistics --}}
                                        <a class="btn btn-soft-success btn-icon btn-circle btn-sm"
                                           href="{{ route('manager41.order.logistics.create', encrypt($order->id)) }}"
                                           title="Add Logistics">
                                            <i class="las la-truck"></i>
                                        </a>
                                    @endif
                                 @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <form id="challanForm">
                <div id="continue-button-wrapper" style="display:none; margin-top: 20px;">
                    <button type="button" id="continue-button" class="btn btn-success">Continue</button>
                </div>
            </form>

            <div class="aiz-pagination">
                {{ $orderData->appends(request()->input())->links() }}
            </div>

        </div>
    </form>
</div>

@endsection

@section('modal')
    @include('modals.delete_modal')
@endsection

@section('script')
    <script type="text/javascript">
        $(document).on("change", ".check-all", function() {
            if(this.checked) {
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

        function bulk_delete() {
            var data = new FormData($('#sort_orders')[0]);
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{route('bulk-order-delete')}}",
                type: 'POST',
                data: data,
                cache: false,
                contentType: false,
                processData: false,
                success: function (response) {
                    if(response == 1) {
                        location.reload();
                    }
                }
            });
        }
    </script>

<script>
    $(document).on('click', '.send-whatsapp', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');
        var $button = $(this);

        // Add loader and disable button
        $button.addClass('loading');
        $button.html('<div class="loader"></div>');

        $.ajax({
            url: "{{ url('send-whatsapp-message') }}/" + orderId, // Append order ID to the URL
            type: "GET",
            success: function(response) {
                // Revert button back to original state
                $button.removeClass('loading');
                $button.html('<i class="lab la-whatsapp"></i>');
                alert('WhatsApp message sent successfully');
            },
            error: function(response) {
                // Revert button back to original state
                $button.removeClass('loading');
                $button.html('<i class="lab la-whatsapp"></i>');
                alert('Failed to send WhatsApp message');
            }
        });
    });
</script>

<script type="text/javascript">
   $('#select-all').on('change', function () {
        $('.challan-checkbox').prop('checked', this.checked);
        toggleContinueButton();
    });

    $('.challan-checkbox').on('change', function () {
        toggleContinueButton();
    });

    function toggleContinueButton() {
        let checkedCount = $('.challan-checkbox:checked').length;
        $('#continue-button-wrapper').toggle(checkedCount > 0);
    }

    $('#continue-button').on('click', function () {
        let selectedIds = [];
        $('.challan-checkbox:checked').each(function () {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            alert('Please select at least one challan.');
            return;
        }

        // Construct redirect URL manually
        let url = "{{ route('challans.view.products') }}?challan_ids=" + selectedIds.join(',');
        window.location.href = url;
    });
</script>

@endsection
