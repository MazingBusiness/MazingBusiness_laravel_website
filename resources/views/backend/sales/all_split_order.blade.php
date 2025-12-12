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
                <h5 class="mb-md-0 h6">{{ translate('All Split Orders') }}</h5>
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
            </div> */ ?>
            <div class="col-lg-3 ml-auto">
                <select class="form-control aiz-selectpicker" name="manager" id="manager">
                    <option value="">--- Select Manager ---</option>
                    @foreach($managerList as $key=>$value)
                        <option value="{{ $key }}">{{ $value }}</option>
                    @endforeach                    
                </select>
            </div>
            <div class="col-lg-5">
                <div class="form-group mb-0">
                    <input type="text" class="form-control" id="search" name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset placeholder="{{ translate('Type Customer Name Or Part no Or Customer Name Or Item Name Or Amount Or Manager & hit Enter') }}">
                </div>
            </div>
            <div class="col-auto">
                <div class="form-group mb-0">
                    <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
                </div>
            </div>
        </div>

        <div class="card-body">
            <table class="table mb-0">
                <thead>
                    <tr>
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
                        <th>{{ translate('Order No') }}</th>
                        <th>{{ translate('Customer') }}</th>
                        <th>{{ translate('Order For') }}</th>
                        <th>{{ translate('Amount') }}</th>
                        <th>{{ translate('Manager Name') }}</th> <!-- New column for Manager Name -->
                        <th>{{ translate('Warehouse Name') }}</th> <!-- New column for Warehouse Name -->
                        <th>Created Date And Time</th>
                        <th class="text-right">{{translate('Options')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @if(count($orderData))
                        @foreach ($orderData as $key => $order)
                            @php
                                $collapseId = 'collapse_' . $key;
                            @endphp
                            <tr>
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
                                    {{ $order->order_no }}
                                </td>
                                <td>
                                    {{ $order->user->company_name }}
                                </td>
                                <td>
                                    {{ $order->sub_order_user_name }}
                                    <p><strong>Order Type : </strong>{{ $order->type }}</p>
                                </td>
                                <td>
                                    {{ single_price($order->sub_total) }}
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
                                    <a class="btn btn-soft-primary btn-icon btn-circle btn-sm" href="{{ route('order.splitOrderDetails', $order->id) }}" title="{{ translate('View') }}">
                                        <i class="las la-eye"></i>
                                    </a>

                                    <a class="btn btn-soft-success btn-icon btn-circle btn-sm" href="{{ route('order.subOrderreallocationSplitOrder', $order->id) }}" title="{{ translate('Reallocation Order') }}" style="background-color: #00ffe7;">
                                        <i class="las la-project-diagram"></i>
                                    </a>
                                    <?php /* <a class="btn btn-soft-info btn-icon btn-circle btn-sm" href="{{ route('invoice.download', $order->id) }}" title="{{ translate('Download Invoice') }}">
                                        <i class="las la-download"></i>
                                    </a>
                                    <?php /* <a href="#" class="btn btn-soft-danger btn-icon btn-circle btn-sm confirm-delete" data-href="{{route('orders.destroy', $order->id)}}" title="{{ translate('Delete') }}">
                                        <i class="las la-trash"></i> */ ?>
                                    </a>

                                    <a href="{{ route('splitOrderPdf', $order->id) }}" 
                                       class="btn btn-icon btn-sm btn-circle btn-soft-danger" 
                                       title="Download PDF" 
                                       target="_blank">
                                        <i class="las la-file-pdf"></i>
                                    </a>
                                    <a href="javascript:void(0);" class="btn btn-icon btn-sm btn-circle btn-soft-success toggle-row" data-target="#{{ $collapseId }}" style="background-color: #99ff00;"> <i class="las la-chevron-down"></i></a>
                                </td>
                            </tr>
                            <tr id="{{ $collapseId }}" class="collapse bg-light">
                                <td colspan="9">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="text-white" style="background-color: #174ba9;">
                                            <tr>
                                                <th>Part No</th>
                                                <th>Item Name</th>
                                                <th>HSN</th>
                                                <th>GST</th>
                                                <th>Clg Stock</th>
                                                <th>Order Qty</th>
                                                <th>Pre Closed Qty</th>        <!-- ⬅️ new -->
                                                 <th>Dispatch Qty</th>   
                                                <th>Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $billingAddress = json_decode($order->billing_address, true);
                                            @endphp
                                            @foreach ($order->sub_order_details as $prod)
                                                @php
                                                    $kolStocks = 0;
                                                    $delStocks = 0;
                                                    $mumStocks = 0;
                                                    $kolkataStocks = DB::table('products_api')->where('part_no', $prod->product_data->part_no)->where('godown', 'Kolkata')->first();
                                                    $delhiStocks = DB::table('products_api')->where('part_no', $prod->product_data->part_no)->where('godown', 'Delhi')->first();
                                                    $mumbaiStocks = DB::table('products_api')->where('part_no', $prod->product_data->part_no)->where('godown', 'Mumbai')->first();
                                                    /********** Closing Stock show as per warehouse order **************/
                                                    if($prod->warehouse_id == 1){
                                                        $kolStocks = $kolkataStocks ? (int)$kolkataStocks->closing_stock : 0;
                                                    }else if($prod->warehouse_id == 2){
                                                        $delStocks = $delhiStocks ? (int)$delhiStocks->closing_stock : 0;
                                                    }else if($prod->warehouse_id == 6){
                                                        $mumStocks = $mumbaiStocks ? (int)$mumbaiStocks->closing_stock : 0;
                                                    }
                                                    $closingStock = $kolStocks + $delStocks + $mumStocks;                        
                                                    if($closingStock == ""){
                                                        $closingStock = 0;
                                                    }
                                                    if($closingStock == "0"){
                                                        $style = "style='color:#f00;'";
                                                    }else{
                                                        $style = "";
                                                    }
                                                @endphp
                                                <tr>
                                                    <td>{{ $prod->product_data->part_no }}</td>
                                                    <td>{{ $prod->product_data->name }}</td>
                                                    <td>{{ $prod->product_data->hsncode }}</td>
                                                    <td>{{ $prod->product_data->tax }}%</td>
                                                    <td>{{ $closingStock }} </td>
                                                    <td>{{ $prod->approved_quantity }}</td>
                                                    <td>{{ (int)($prod->pre_closed ?? 0) }}</td>                             <!-- ⬅️ new -->
                                                     <td>{{ (int)($prod->challan_qty ?? $prod->challan_quantity ?? 0) }}</td> <!-- ⬅️ new -->
                                                    <td>{{ number_format($prod->approved_rate, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        @endforeach
                        
                    @else
                        <tr><td colspan="8">No Record Found</tr></td>
                    @endif
                </tbody>
            </table>

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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
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

        document.querySelectorAll('.toggle-row').forEach(function(btn) {
            btn.addEventListener('click', function () {
                const targetId = this.getAttribute('data-target');
                const target = document.querySelector(targetId);
                const icon = this.querySelector('i');
                if (target.classList.contains('show')) {
                    // Collapse
                    target.classList.remove('show');
                    icon.classList.remove('la-chevron-up');
                    icon.classList.add('la-chevron-down');
                } else {
                    // Expand
                    target.classList.add('show');
                    icon.classList.remove('la-chevron-down');
                    icon.classList.add('la-chevron-up');
                }
            });
        });
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
