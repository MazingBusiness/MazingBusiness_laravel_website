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
                <h5 class="mb-md-0 h6">{{ translate('All Approved Orders') }}</h5>
            </div>

            {{-- <div class="dropdown mb-2 mb-md-0">
                <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">
                    {{translate('Bulk Action')}}
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#" onclick="bulk_delete()"> {{translate('Delete selection')}}</a>
                </div>
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
            </div> --}}
        </div>

        <div class="card-body">
            <table class="table aiz-table mb-0">
                <thead>
                    <tr>
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
                        <th>{{ translate('Order Code') }}</th>
                        <th>{{ translate('Num. of Products') }}</th>
                        <th>{{ translate('Customer') }}</th>
                        <th>{{ translate('Amount') }}</th>
                        <th>{{ translate('Order Date') }}</th> <!-- New column for Order Date -->
                        <th>{{translate('Order Status')}}</th> <!-- Column moved here -->
                        <th class="text-center" width="15%">{{translate('Options')}}</th>
                        <th class="text-right" width="1%">{{translate('Send')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $key => $order)
                    <tr>
                        <td>
                            <div class="form-group">
                                <div class="aiz-checkbox-inline">
                                    <label class="aiz-checkbox">
                                        <input type="checkbox" class="check-one" name="id[]" value="{{$order->id}}">
                                        <span class="aiz-square-check"></span>
                                    </label>
                                </div>
                            </div>
                        </td>
                        <td>
                            {{ $order->order_code }}
                        </td>
                        <td>
                            {{ count($order->orderDetails) }}
                        </td>
                        <td>
                            {{ $order->customer_company_name }}
                        </td>
                        <td>
                            {{ $order->currency.' '.$order->grand_total }}
                        </td>
                        <td>
                            <!-- Displaying the order creation date -->
                            {{ $order->created_at->format('d M, Y') }}
                        </td>

                        <td>
                            <!-- Displaying the order creation date -->
                            {{ ucfirst($order->delivery_status) }}
                        </td>
                        <td class="text-center">
                            @php
                                $order_detail_route = route('orders.show', encrypt($order->id));
                                if(Route::currentRouteName() == 'seller_orders.index') {
                                    $order_detail_route = route('seller_orders.show', encrypt($order->id));
                                }
                                else if(Route::currentRouteName() == 'pick_up_point.index') {
                                    $order_detail_route = route('pick_up_point.order_show', encrypt($order->id));
                                }
                                if(Route::currentRouteName() == 'inhouse_orders.index') {
                                    $order_detail_route = route('inhouse_orders.show', encrypt($order->id));
                                }
                            @endphp
                            <a class="btn btn-soft-primary btn-icon btn-circle btn-sm" href="{{ route('international_confirm_order_details',['id'=>encrypt($order->id)]) }}" title="{{ translate('View') }}">
                                <i class="las la-eye"></i>
                            </a>
                            {{-- <a class="btn btn-soft-info btn-icon btn-circle btn-sm" href="#" title="{{ translate('Download Invoice') }}">
                                <i class="las la-download"></i>
                            </a>
                            <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete" data-href="{{route('orders.destroy', $order->id)}}" title="{{ translate('Delete') }}">
                                <i class="las la-trash"></i>
                            </a> --}}
                        </td>
                        <td>
                            <a href="#" class="btn btn-success btn-icon btn-circle btn-sm send-whatsapp" data-order-id="{{ $order->order_code }}" title="{{ translate('Send WhatsApp') }}">
                                <i class="lab la-whatsapp"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="aiz-pagination">
                {{ $orders->appends(request()->input())->links() }}
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
        var orderCode = $(this).data('order-id');
        var $button = $(this);

        // Add loader and disable button
        $button.addClass('loading');
        $button.html('<div class="loader"></div>');

        $.ajax({
            url: "{{ url('admin/send-impex-order-whatsapp') }}/" + orderCode, // Append order ID to the URL
            type: "GET",
            success: function(response) {
                // Revert button back to original state
                $button.removeClass('loading');
                $button.html('<i class="lab la-whatsapp"></i>');
                AIZ.plugins.notify('success', 'WhatsApp messages sent successfully.');
               // alert('WhatsApp message sent successfully');
            },
            error: function(response) {
                // Revert button back to original state
                $button.removeClass('loading');
                $button.html('<i class="lab la-whatsapp"></i>');
                AIZ.plugins.notify('danger', 'WhatsApp messages sent successfully.');
                //alert('Failed to send WhatsApp message');
            }
        });
    });
</script>

@endsection
