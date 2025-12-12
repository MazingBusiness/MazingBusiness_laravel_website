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

                <h5 class="mb-md-0 h6">{{ translate('All Pending For Approval Orders') }}</h5>

            </div>



            <div class="dropdown mb-2 mb-md-0">

                <button class="btn border dropdown-toggle" type="button" data-toggle="dropdown">

                    {{translate('Bulk Action')}}

                </button>

                <div class="dropdown-menu dropdown-menu-right">

                    <a class="dropdown-item" href="#" onclick="bulk_delete()"> {{translate('Delete selection')}}</a>

                </div>

            </div>

            <!-- Dropdown for Salzing Order Punch Status -->

            <?php /* <div class="col-lg-2 ml-auto"  style="display:none;">

                <select class="form-control aiz-selectpicker" name="salzing_status" id="salzing_status">

                    <option value="">{{translate('Filter by Salzing Order Punch Status')}}</option>

                    @foreach ($salzing_statuses as $status)

                        <option value="{{ $status }}" @if (request('salzing_status') == $status) selected @endif>

                            {{ ucfirst($status) }}

                        </option>

                    @endforeach

                </select>

            </div> */ ?>

            <!-- <div class="col-lg-3 ml-auto">

                <select class="form-control aiz-selectpicker" name="manager" id="manager">

                    <option value="">--- Select Manager ---</option>

                    @foreach($managerList as $key=>$value)

                        <option value="{{ $key }}">{{ $value }}</option>

                    @endforeach                    

                </select>

            </div> -->

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

            </div>

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

                        <th>{{ translate('Manager Name') }}</th> <!-- New column for Manager Name -->

                        <th>{{ translate('Warehouse Name') }}</th> <!-- New column for Warehouse Name -->

                        <th>{{ translate('Order Date') }}</th> <!-- New column for Order Date -->

                        <!-- <th class="text-right">{{translate('Salzing Order Punch Status')}}</th>  -->

                        <th>{{ translate('Due Amount') }}</th>

                        <th>{{ translate('Overdue Amount') }}</th>

                        <th class="text-right">{{translate('Delivery Status')}}</th> <!-- Column moved here -->

                        @if (addon_is_activated('refund_request'))

                        <th>{{ translate('Refund') }}</th>

                        @endif

                        <th class="text-right" width="15%">{{translate('Options')}}</th>

                        <th class="text-right" width="1%">{{translate('Send')}}</th>

                        <!-- <th class="text-right" width="1%">{{translate('Salzing Order')}}</th> -->

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

                            {{ $order->code }}@if($order->viewed == 0) <span class="badge badge-inline badge-info">{{translate('New')}}</span>@endif

                        </td>

                        <td>

                            {{ count($order->orderDetails) }}

                        </td>

                        <td>

                            {{ $order->company_name }}

                        </td>

                        <td>

                            {{ single_price($order->grand_total) }}

                        </td>

                        <td>

                            <!-- Manager name column -->

                            {{ $order->manager_name }}

                        </td>

                        <td>

                            <!-- Warehouse name column -->

                            {{ $order->warehouse_name }}

                        </td>

                        <td>

                            <!-- Displaying the order creation date -->

                            {{ $order->created_at->format('d-m-Y') }}

                        </td>

                        <!-- <td class="text-right">

                           

                             @if (strcasecmp($order->response, 'success.') == 0)

                                    <span class="badge badge-inline badge-success">{{ $order->response }}</span>

                                @else

                                    <span class="badge badge-inline badge-danger">{{ $order->response }}</span>

                                @endif

                        </td> -->

                        <td>

                            @php

                                $dueAmount = $order->due_amount ?? 0;

                                $dueType = $order->dueDrOrCr ?? 'Cr';

                            @endphp

                            <span class="{{ $dueType == 'Dr' && $dueAmount > 0 ? 'text-warning' : 'text-success' }}">

                                {{ number_format($dueAmount, 2) }} ({{ $dueType }})

                            </span>

                        </td>



                        <td>

                            @php

                                $overdueAmount = $order->overdue_amount ?? 0;

                                $overdueType = $order->overdueDrOrCr ?? 'Cr';

                            @endphp

                            <span class="{{ $overdueType == 'Dr' && $overdueAmount > 0 ? 'text-danger' : 'text-success' }}">

                                {{ number_format($overdueAmount, 2) }} ({{ $overdueType }})

                            </span>

                        </td>





                        <td>

                            <!-- Displaying the order creation date -->

                            {{ $order->delivery_status }}

                        </td>

                        @if (addon_is_activated('refund_request'))

                        <td>

                            @if (count($order->refund_requests) > 0)

                                {{ count($order->refund_requests) }} {{ translate('Refund') }}

                            @else

                                {{ translate('No Refund') }}

                            @endif

                        </td>

                        @endif

                        <td class="text-right">

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

                            <a class="btn btn-soft-primary btn-icon btn-circle btn-sm" href="{{ $order_detail_route }}" title="{{ translate('View') }}">

                                <i class="las la-eye"></i>

                            </a>

                            @php

                                $getSplitOrder = App\Models\SubOrder::where('code',$order->code)->first();

                                $data_status=0;

                                if($getSplitOrder != NULL){

                                    $data_status=1;

                                }

                            @endphp

                            @if($getSplitOrder == NULL OR $getSplitOrder->status == 'draft')

                                <a class="btn btn-soft-success btn-icon btn-circle btn-sm" href="{{ route('order.splitOrder', [$order->id, 'order.allPendingForApprovalOrder', $data_status]) }}" title="{{ translate('Split Order') }}">

                                    <i class="las la-project-diagram"></i>

                                </a>

                            @endif

                            <a class="btn btn-soft-info btn-icon btn-circle btn-sm" href="{{ route('invoice.download', $order->id) }}" title="{{ translate('Download Invoice') }}">

                                <i class="las la-download"></i>

                            </a>

                            <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete" data-href="{{route('orders.destroy', $order->id)}}" title="{{ translate('Delete') }}">

                                <i class="las la-trash"></i>

                            </a>

                        </td>

                        <td>

                            <a href="{{route('send.whatsapp.message', $order->id)}}" class="btn btn-success btn-icon btn-circle btn-sm send-whatsapp" data-order-id="{{ $order->id }}" title="{{ translate('Send WhatsApp') }}">

                                <i class="lab la-whatsapp"></i>

                            </a>

                        </td>

                        <!-- <td class="text-right">

                            @if($order->status == 0)

                                <a href="{{route('push.order', $order->combined_order_id)}}" class="btn btn-info btn-icon btn-circle btn-sm push-order" title="{{ translate('Push Order') }}">

                                    <i class="las la-paper-plane"></i>

                                </a>

                            @endif

                        </td> -->

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



@endsection