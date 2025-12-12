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
                <h5 class="mb-md-0 h6">{{ translate('All Pending Orders') }}</h5>
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
            </div> */ ?>
            <div class="col-lg-5">
                <div class="form-group mb-0">
                    <input type="text" class="form-control" id="search" name="search"@isset($sort_search) value="{{ $sort_search }}" @endisset placeholder="{{ translate('Type Order number Or Part no Or Customer Name Or Item Name & hit Enter') }}">
                </div>


            </div>
            <div class="col-auto">
                <div class="form-group mb-0">
                    <button type="submit" class="btn btn-primary">{{ translate('Filter') }}</button>
                    {{-- Export: pure anchor, no jQuery --}}
                    <a
                            class="btn btn-soft-success"
                            href="{{ route('order.allPendingOrder.export', [
                                'search' => request('search'),
                                // extra param just to make URL unique on each page load
                                't'      => microtime(true),
                            ]) }}"
                        >
                            {{ translate('Export') }}
                     </a>
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
                        <th>{{ translate('#') }}</th>
                        <th>{{ translate('Order Date') }}</th>
                        <th>{{ translate('Order No') }}</th>
                        <th>{{ translate('Warehouse Name') }}</th>
                        <th>{{ translate('Customer') }}</th>
                        <th>{{ translate('Part Number') }}</th>
                        <th>{{ translate('Item Name') }}</th>
                        <th>{{ translate('Approved Rate') }}</th>
                        <th>{{ translate('Pending Quqntity') }}</th>
                        <th class="text-right">{{translate('Options')}}</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $hasBTROrderId = '';
                    @endphp
                    @if(count($orderData))
                        @foreach ($orderData as $key => $order)
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
                                    {{ ($orderData->currentPage() - 1) * $orderData->perPage() + $key + 1 }} 
                                </td>
                                <td>
                                    {{ $order->created_at->format('d-m-Y h:m A') }}
                                </td>
                                <td>
                                    {{ $order->sub_order_record->order_no }}
                                </td>
                                <td>
                                    {{ $order->sub_order_record->order_warehouse->name }}
                                    
                                </td>
                                <td>
                                    {{ $order->sub_order_record->user->company_name }}
                                </td>
                                <td>
                                    {{ $order->product_data->part_no }}
                                </td>
                                <td>
                                    {{ $order->product_data->name }}
                                </td>
                                <td>
                                    ₹ {{ $order->approved_rate }}
                                </td>
                                <td>
                                    {{ $order->pending_qty }}
                                </td>
                                <td>
                                    @php
                                        $hasBTRId = $order->parentSubOrder->id ?? '';
                                        
                                        $hasBTROrderId = $order->btrSubOrder->id ?? '';
                                    @endphp
                                    @if($order->pre_closed_status == 0)
                                        <!-- <i class="las la-handshake preclose" style="font-size: 30px; color:#f00; cursor:pointer;" title="Pre Close" data-id="{{ $order->id }}" data-sub_order_id="{{ $order->sub_order_id }}" data-sub_order_type="{{ $order->type }}" data-sub_order_qty="{{ $order->approved_quantity - $order->pre_closed }}" data-closing_stock="{{ $order->pending_qty }}" data-item_name="{{ $order->product_data->name }}"></i> -->

                                        <i class="las la-handshake preclose" style="font-size: 30px; color:#f00; cursor:pointer;" title="Pre Close" data-id="{{ $order->id }}" data-sub_order_id="{{ $order->sub_order_id }}" data-sub_order_type="{{ $order->type }}" data-sub_order_qty="{{ $order->approved_quantity - $order->pre_closed }}" data-closing_stock="{{ $order->pending_qty }}" data-item_name="{{ $order->product_data->name }}"  data-has_btr="{{ $hasBTRId }}"  data-has_btr_order_id="{{ $hasBTROrderId }}"  data-btr_qty="{{ $order->in_transit }}"></i>
                                        
                                    @endif
                                </td>
                                <?php /* <td class="text-right">
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

                                    <a href="{{ route('splitOrderPdf', $order->id) }}" 
                                       class="btn btn-icon btn-sm btn-circle btn-soft-danger" 
                                       title="Download PDF" 
                                       target="_blank">
                                        <i class="las la-file-pdf"></i>
                                    </a>
                                </td> */ ?>
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
<!-- Pre Closed Modal -->
<!-- <div class="modal fade" id="preCloseModal" tabindex="-1" aria-labelledby="addCarriersModal" aria-hidden="true">
    <div class="modal-dialog"> 
      <div class="modal-content p-3">
        <div class="modal-header">
          <h5 class="modal-title" id="myLargeModalLabel">Pre Close</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form class="form form-horizontal mar-top" action="{{ route('order.savePreClose') }}" method="POST" enctype="multipart/form-data" id="preclosed_form">
          <div class="modal-body">          
            @csrf
            <input type="hidden" class="form-control" name="sub_order_details_id" id="sub_order_details_id" value="" required>
            <input type="hidden" class="form-control" name="sub_order_id" id="sub_order_id_pre_close" value="" required>
            <input type="hidden" class="form-control" name="sub_order_qty" id="sub_order_qty" value="" required>
            <input type="hidden" class="form-control" name="sub_order_type" id="sub_order_type" value="" required>
            <input type="hidden" class="form-control" name="has_btr_order_id" id="has_btr_order_id" value="{{ $hasBTROrderId }}">
            <input type="hidden" class="form-control" name="btr_qty" id="btr_qty" value="">
            <input type="hidden" class="form-control" name="redirect" id="redirect" value="order.allPendingOrder" required>
            <div class="col-md-12" style="text-align:left;">
                <div class="form-group row">
                  <label class="col-md-5 col-form-label">Item Name :</label>
                  <div class="col-md-7">
                    <span id="spanItemName"></span>
                  </div>
                </div>
            </div>
            <div class="col-md-12" style="text-align:left;">
                <div class="form-group row">
                  <label class="col-md-5 col-form-label">Max Order Qty :</label>
                  <div class="col-md-7">
                    <span id="spanOrderQty"></span>
                  </div>
                </div>
            </div>
            <div class="col-md-12" style="text-align:left;">
              <div class="form-group row">
                <label class="col-md-5 col-form-label">Pre Close Quantity:</label>
                <div class="col-md-7">
                  <input type="number" min='0' max="5" class="form-control" name="pre_closed" id="pre_closed" placeholder="Pre Close Quantity" value="" required>
                </div>
              </div>
            </div>
            <div class="col-md-12" style="text-align:left; display:none;" id="mainBranchBtrDiv">
              <div class="form-group row">
                <label class="col-md-5 col-form-label">BTR Pre Close Quantity:</label>
                <div class="col-md-7">
                  <input type="number" min='0' class="form-control" name="main_branch_pre_closed" id="main_branch_pre_closed" placeholder="Btr Pre Close Quantity" value="">
                </div>
              </div>
            </div>         
          </div>
          <p>Are you sure you want to pre-close this order?</p>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Confirm Pre Close</button>
          </div>
        </form>
      </div>
    </div>
</div> -->

<div class="modal fade" id="preCloseModal" tabindex="-1" aria-labelledby="addCarriersModal" aria-hidden="true">
    <div class="modal-dialog"> 
      <div class="modal-content p-3">
        <div class="modal-header">
          <h5 class="modal-title" id="myLargeModalLabel">Pre Close</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form class="form form-horizontal mar-top" action="{{ route('order.savePreClose') }}" method="POST" enctype="multipart/form-data" id="add_carrier_form">
          <div class="modal-body">          
            @csrf
            <input type="hidden" class="form-control" name="sub_order_details_id" id="sub_order_details_id" value="" required>
            <input type="hidden" class="form-control" name="sub_order_id" id="sub_order_id_pre_close" value="" required>
            <input type="hidden" class="form-control" name="sub_order_qty" id="sub_order_qty" value="" required>
            <input type="hidden" class="form-control" name="sub_order_type" id="sub_order_type" value="" required>
            <input type="hidden" class="form-control" name="has_btr_order_id" id="has_btr_order_id" value="{{ $hasBTROrderId }}">
            <input type="hidden" class="form-control" name="btr_qty" id="btr_qty" value="">
            <input type="hidden" class="form-control" name="redirect" id="redirect" value="order.allPendingOrder" required>
            
            <div class="col-md-12" style="text-align:left;">
                <div class="form-group row">
                  <label class="col-md-5 col-form-label">Item Name :</label>
                  <div class="col-md-7">
                    <span id="spanItemName"></span>
                  </div>
                </div>
            </div>
            <div class="col-md-12" style="text-align:left;">
                <div class="form-group row">
                  <label class="col-md-5 col-form-label">Max Order Qty :</label>
                  <div class="col-md-7">
                    <span id="spanOrderQty"></span>
                  </div>
                </div>
            </div>
            <div class="col-md-12" style="text-align:left;">
              <div class="form-group row">
                <label class="col-md-5 col-form-label">Pre Close Quantity:</label>
                <div class="col-md-7">
                  <input type="number" min='0' class="form-control" name="pre_closed" id="pre_closed" placeholder="Pre Close Quantity" value="">
                </div>
              </div>
            </div>
            <div class="col-md-12" style="text-align:left; display:none;" id="mainBranchBtrDiv">
              <div class="form-group row">
                <label class="col-md-5 col-form-label">BTR Pre Close Quantity:</label>
                <div class="col-md-7">
                  <input type="number" min='0' class="form-control" name="main_branch_pre_closed" id="main_branch_pre_closed" placeholder="Btr Pre Close Quantity" value="">
                </div>
              </div>
            </div>
            <!-- <div class="col-lg-12">
              <button type="button" class="btn btn-primary btnSubmitAddCarrier">Save</button>
            </div> -->          
          </div>
          <p>Are you sure you want to pre-close this order?</p>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Confirm Pre Close</button>
          </div>
        </form>
      </div>
    </div>
  </div>

@endsection

@section('modal')
    @include('modals.delete_modal')
@endsection

@section('script')
    <script type="text/javascript">
        $(document).ready(function () {
            $(document).on("click", ".preclose", function () {
                var subOrderDetailsId = $(this).data("id");
                var subOrderId = $(this).data("sub_order_id");
                var subOrderQty = $(this).data("sub_order_qty");
                var subOrderType = $(this).data("sub_order_type");
                var closingStock = $(this).data("closing_stock");
                var has_btr = $(this).data("has_btr");
                var has_btr_order_id = $(this).data("has_btr_order_id");
                var btr_qty = $(this).data("btr_qty");
                var itemName = $(this).data("item_name");

                $("#sub_order_details_id").val(subOrderDetailsId);
                $("#sub_order_id_pre_close").val(subOrderId);
                $("#sub_order_qty").val(subOrderQty);
                $("#sub_order_type").val(subOrderType);
                $("#spanOrderQty").html(subOrderQty);
                $("#spanItemName").html(itemName);
                $("#pre_closed").attr("max", subOrderQty); // Set max limit
                if (has_btr_order_id != "") {
                    if(btr_qty > 0){
                        $("#mainBranchBtrDiv").show(); // Show the div
                    }              
                    $("#pre_closed").attr("max", subOrderQty); // Set max limit
                    $("#main_branch_pre_closed").attr("max", btr_qty); // Set max limit
                    $("#has_btr_order_id").val(has_btr_order_id);
                    $("#btr_qty").val(btr_qty);
                } else {
                    $("#mainBranchBtrDiv").hide(); // Hide if not btr
                    $("#pre_closed").val('');
                    $("#main_branch_pre_closed").val('');
                    $("#has_btr_order_id").val('');
                    $("#btr_qty").val('');
                }
                $("#preCloseModal").modal("show");
            });

            // Restrict input to max sub_order_qty
            $(document).on("input", "#pre_closed", function() {
                var maxQty = parseInt($("#sub_order_qty").val(), 10); // Get max quantity
                var enteredQty = parseInt($(this).val(), 10); // Get entered value

                if (enteredQty > maxQty) {
                    $(this).val(maxQty); // Reset to maxQty if user enters more
                }
                $('#main_branch_pre_closed').val('');
            });

            // Restrict input to max sub_order_qty
            $(document).on("input", "#main_branch_pre_closed", function() {
                // var preClosedQty = parseInt($("#pre_closed").val(), 10);
                var preClosedQty = parseInt($("#pre_closed").val(), 10) || 0;
                var subOrderQty = parseInt($("#sub_order_qty").val(), 10);
                var btr_qty = parseInt($("#btr_qty").val(), 10);
                // alert(preClosedQty);
                if(preClosedQty != ""){
                    var maxQty = subOrderQty - preClosedQty;
                    if(maxQty > btr_qty){
                    var maxQty = btr_qty;
                    }
                }else{
                    var maxQty = parseInt($("#btr_qty").val(), 10);            
                }
                var enteredQty = parseInt($(this).val(), 10);

                if (enteredQty > maxQty) {
                    $(this).val(maxQty); // Reset to maxQty if user enters more
                }
            });

            $('#pre_closed').on('input', function() {
                let maxQty = parseInt($('#sub_order_qty').val());
                let entered = parseInt($(this).val());

                if (entered > maxQty) {
                    alert('Pre Closed Quantity cannot be more than Max Order Quantity (' + maxQty + ').');
                    $(this).val(maxQty); // Reset to max value
                }
            });
        });

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
