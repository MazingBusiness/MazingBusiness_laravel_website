@extends('backend.layouts.app')

@section('content')
<style>
/* Custom width for the modal */
.custom-modal-width {
    max-width: 80%; /* Set desired width here */
}

/* Optionally, you can set width for larger screens */
@media (min-width: 768px) {
    .custom-modal-width {
        max-width: 70%;
    }
}
</style>
@php
    $allowedUserIds = [1, 180, 169,25606];
@endphp

@if(in_array(auth()->user()->id, $allowedUserIds))
  <div class="card">
    <div class="card-header">
      <h1 class="h2 fs-16 mb-0">{{ translate('Order Details') }}</h1>
    </div>
    <div class="card-body">
      <div class="row">
        <!-- <div class="col-md-2 ml-auto">
            <button name="addProduct" id="addProduct" class="form-control btn-success" data-order-id="{{$order->id}}" onclick="openAddProductModal(this)">Add Product</button>
           
            <a href="{{ route('impexOrderPdf', ['order_code' => $order->order_code]) }}" class="btn btn-primary mt-2">
                Download PDF
            </a>
        </div> -->
        <div class="col-md-4 d-flex gap-6 align-items-center">
            <!-- Add Product Button -->
            <button name="addProduct" id="addProduct" class="btn btn-success" data-order-id="{{$order->id}}" onclick="openAddProductModal(this)">
                Add Product
            </button>

            <!-- Download PDF Button -->
          <!--   <a href="{{ route('impexOrderPdf', ['order_code' => $order->order_code]) }}" class="btn btn-primary ml-3">
                Download PDF
            </a> -->
        </div>

         


        <div class="col-md-5 ml-auto">
          {{-- <div class="row">
            <div class="col-md-4 ml-auto">
              <label for="update_payment_status">{{ translate('Payment Status') }}</label>
            </div>
            <div class="col-md-8 ml-auto">
              <select class="form-control aiz-selectpicker" data-minimum-results-for-search="Infinity"
                id="update_payment_status">
                <option value="unpaid" @if ($order->payment_status == 'unpaid') selected @endif>
                  {{ translate('Unpaid') }}
                </option>
                <option value="paid" @if ($order->payment_status == 'paid') selected @endif>
                  {{ translate('Paid') }}
                </option>
              </select>
            </div>
          </div> --}}
        </div>
        <div class="col-md-5 ml-auto">
          {{-- <div class="row">
            <div class="col-md-4 ml-auto">
              <label for="update_delivery_status">{{ translate('Oredr Status') }}</label>
            </div>
            <div class="col-md-8 ml-auto">
              <select class="form-control aiz-selectpicker" data-minimum-results-for-search="Infinity"
                id="update_delivery_status">
                <option value="pending" @if ($order->delivery_status == 'pending') selected @endif>
                  {{ translate('Pending') }}
                </option>
                <option value="in_review" @if ($order->delivery_status == 'in_review') selected @endif>
                  {{ translate('In Review') }}
                </option>
                <option value="confirm" @if ($order->delivery_status == 'confirm') selected @endif>
                  {{ translate('Confirm') }}
                </option>
              </select>
            </div>
          </div> --}}
        </div>
      </div></br>
      <div class="mb-3">
        <?php
            $removedXML = '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
        {!! str_replace($removedXML, '', QrCode::size(100)->generate($order->order_code)) !!}
      </div>
      <div class="row gutters-5">
        <div class="col text-md-left text-center">
          <address>
            <strong class="text-main">
              {{ $order->user->name }}
            </strong><br>
            {{ $order->user->email }}<br>
            {{ $order->user->phone }}<br>
          </address>
        </div>
        <div class="col-md-4 ml-auto">
          <table>
            <tbody>
              <tr>
                <td class="text-main text-bold">{{ translate('Order #') }}</td>
                <td class="text-info text-bold text-right"> {{ $order->order_code }}</td>
              </tr>
              <tr>
                <td class="text-main text-bold">{{ translate('Order Status') }}</td>
                <td class="text-right">
                  @if ($order->delivery_status == 'delivered')
                    <span class="badge badge-inline badge-success">
                      {{ translate(ucfirst(str_replace('_', ' ', $order->delivery_status))) }}
                    </span>
                  @else
                    <span class="badge badge-inline badge-danger" id="spanDeliveryStatus">
                      {{ translate(ucfirst(str_replace('_', ' ', $order->delivery_status))) }}
                    </span>
                  @endif
                </td>
              </tr>
              <tr>
                <td class="text-main text-bold">{{ translate('Order Date') }} </td>
                <td class="text-right">{{ date('d M, Y h:i A', strtotime($order->created_at)) }}</td>
              </tr>
              <tr>
                <td class="text-main text-bold">
                  {{ translate('Total amount') }}
                </td>
                <td class="text-right">
                  <span id='mainGrandTotal'>{{ $order->currency.' '.$order->grand_total }}</span>
                </td>
              </tr>
              {{-- <tr>
                <td class="text-main text-bold">{{ translate('Payment method') }}</td>
                <td class="text-right">
                  {{ translate(ucfirst(str_replace('_', ' ', $order->payment_type))) }}</td>
              </tr>
              <tr>
                <td class="text-main text-bold">{{ translate('Additional Info') }}</td>
                <td class="text-right">{{ $order->additional_info }}</td>
              </tr> --}}
            </tbody>
          </table>
        </div>
      </div>
      <hr class="new-section-sm bord-no">
      <div class="row">
        <div class="col-lg-12 table-responsive" id="tableOrderProduct">
          <table class="table-bordered aiz-table invoice-summary table">
            <thead>
              <tr class="bg-trans-dark">
                <th data-breakpoints="lg" class="min-col">#</th>
                <th width="10%">{{ translate('Photo') }}</th>
                <th class="text-uppercase">{{ translate('Description') }}</th>
                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                  {{ translate('Brand') }}
                </th>
                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                Comment <br/> and <br/>Days of Delivery
                </th>
                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                  {{ translate('Qty') }}
                </th>
                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                  {{ translate('Unit Price') }}
                </th>
                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                  {{ translate('Total') }}
                </th>
                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                  {{ translate('Action') }}
                </th>
              </tr>
            </thead>
            <tbody>
              @foreach ($order->orderDetails as $key => $orderDetail)
                <tr id="tr_{{$orderDetail->id}}">
                  <td>{{ $key + 1 }}</td>
                  <td>
                    @if ($orderDetail->product != null)
                      {{-- <a href="{{ route('product', $orderDetail->product->slug) }}" target="_blank"> --}}
                      <a href="#" target="_blank">  
                        <img height="50" src="{{ uploaded_asset($orderDetail->product->thumbnail_img) }}">
                      </a>
                    @else
                      <strong>{{ translate('N/A') }}</strong>
                    @endif
                  </td>
                  <td>
                    @if ($orderDetail->name != null)
                      <strong>
                        {{-- <a href="{{ route('product', $orderDetail->product->slug) }}" target="_blank" class="text-muted"> --}}
                        <a href="#" target="_blank" class="text-muted">  
                          {{ $orderDetail->product->name }}
                        </a>
                      </strong>
                      <br>
                      <small id="small_brand_{{$orderDetail->id}}">
                        @if($orderDetail->brand_name != "" OR $orderDetail->brand_name != NULL)
                          {{ $orderDetail->brand.' : '.$orderDetail->brand_name }}
                        @else
                          {{ $orderDetail->brand }}
                        @endif
                      </small>
                      <br>
                      <small>
                        {{ translate('Part No.') }}: {{ $orderDetail->product->part_no }}
                      </small>
                    @else
                      <strong>{{ translate('Product Unavailable') }}</strong>
                    @endif
                  </td>
                  <td class="text-center" style="display: flex; justify-content: center; align-items: center;" id="trBrand_{{$orderDetail->id}}">
                    <i class="las la-highlighter" style="color: green; font-size: 24px; cursor:pointer;" data-brand="{{$orderDetail->brand}}" data-brand-name="{{$orderDetail->brand_name}}" data-orderdetails-id="{{$orderDetail->id}}" onclick="openModal(this)"></i>
                  </td>
                  <td class="text-center" style="display: flex; justify-content: center; align-items: center;" id="trComment_{{$orderDetail->id}}">
                    <i class="las la-comment" style="color: #25bcf1; font-size: 24px; cursor:pointer;" data-comment="{{$orderDetail->comment}}"  data-days-of-delivery="{{$orderDetail->days_of_delivery}}" data-orderdetails-id="{{$orderDetail->id}}" onclick="openCommentModal(this)"></i>
                  </td>                  
                  <td class="text-center" style="display: flex; justify-content: center; align-items: center;">
                      <input type="text" value="{{ $orderDetail->quantity }}" name="quantity_{{$orderDetail->id}}" id="quantity_{{$orderDetail->id}}" class="form-control" style="width: 100px; text-align: center; margin: auto;" oninput="updateQty(this.value,{{$orderDetail->id}})">
                  </td>
                  <td class="text-center">
                      <div style="position: relative; display: inline-flex; align-items: center;">
                          <span style="position: absolute; left: 10px; padding-right: 5px; color: #888;"> {{ $order->currency }} </span>
                          <input type="text" value="{{ $orderDetail->unit_price }}" name="unit_price" id="unit_price" class="form-control" style="padding-left: 30px; width: 100px; text-align: left; margin: auto;" oninput="updateUnitPrice(this.value,{{$orderDetail->id}})">
                      </div>
                  </td>
                  <td class="text-center">
                    <span id="spanTotalPrice_{{$orderDetail->id}}">{{ $order->currency.' '.$orderDetail->total_price }}</span>
                  </td>
                  <td class="text-center">
                  <i class="las la-trash" style="color: red; font-size: 24px; cursor:pointer;" onclick="productDelete({{$orderDetail->id}})"></i>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      <div class="clearfix float-right">
        <table class="table">
          <tbody>
            <tr>
              <td>
                <strong class="text-muted">{{ translate('TOTAL') }} :</strong>
              </td>
              <td class="text-muted h5">
              <span id="spanGrandTotalPrice">{{ $order->currency.' '.$order->grand_total }}</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="no-print text-right">
          {{-- <a href="{{ route('invoice.download', $order->id) }}" type="button" class="btn btn-icon btn-light"><i
              class="las la-print"></i></a> --}}
        </div>
      </div>
      <div class="row">
        <div class="col-md-3" >
          <button name="addProduct" id="addProduct" class="form-control btn-success" onclick="saveInReview()">Save in Review</button>          
        </div>
        <div class="col-md-3">
          <button name="addProduct" id="addProduct" class="form-control btn-info" onclick="openConfirmModal()">Confirm Order</button>
        </div>
      </div><br/>
    </div>
    
  </div>
  
  <div class="card" id="divDeleteProduct" @if($deleteOrderProduct->orderDetails->count() <= 0) style="display:none;" @endif >
    <div class="card-header">
      <h1 class="h2 fs-16 mb-0">{{ translate('Deleted Product Details') }}</h1>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-lg-12 table-responsive" id="tableDeleteProduct">
          <table class="table-bordered aiz-table invoice-summary table">
            <thead>
              <tr class="bg-trans-dark">
                <th data-breakpoints="lg" class="min-col">#</th>
                <th width="10%">{{ translate('Photo') }}</th>
                <th class="text-uppercase">{{ translate('Description') }}</th>
                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                  {{ translate('Qty') }}
                </th>
                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                  {{ translate('Unit Price') }}
                </th>
                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                  {{ translate('Total') }}
                </th>
                <th data-breakpoints="lg" class="min-col text-uppercase text-center">
                  {{ translate('Action') }}
                </th>
              </tr>
            </thead>
            <tbody>
              @foreach ($deleteOrderProduct->orderDetails as $key => $orderDetail)
                <tr id="tr_reverse_{{$orderDetail->id}}">
                  <td>{{ $key + 1 }}</td>
                  <td>
                    @if ($orderDetail->product != null)
                      {{-- <a href="{{ route('product', $orderDetail->product->slug) }}" target="_blank"> --}}
                      <a href="#" target="_blank">  
                        <img height="50" src="{{ uploaded_asset($orderDetail->product->thumbnail_img) }}">
                      </a>
                    @else
                      <strong>{{ translate('N/A') }}</strong>
                    @endif
                  </td>
                  <td>
                    @if ($orderDetail->name != null)
                      <strong>
                        {{-- <a href="{{ route('product', $orderDetail->product->slug) }}" target="_blank" class="text-muted"> --}}
                        <a href="#" target="_blank" class="text-muted">  
                          {{ $orderDetail->product->name }}
                        </a>
                      </strong>
                      <br>
                      <small id="small_brand_{{$orderDetail->id}}">
                        @if($orderDetail->brand_name != "" OR $orderDetail->brand_name != NULL)
                          {{ $orderDetail->brand.' : '.$orderDetail->brand_name }}
                        @else
                          {{ $orderDetail->brand }}
                        @endif
                      </small>
                      <br>
                      <small>
                        {{ translate('Part No.') }}: {{ $orderDetail->product->part_no }}
                      </small>
                    @else
                      <strong>{{ translate('Product Unavailable') }}</strong>
                    @endif
                  </td>
                  <td class="text-center" style="display: flex; justify-content: center; align-items: center;">
                      <input type="text" value="{{ $orderDetail->quantity }}" name="quantity_{{$orderDetail->id}}" id="quantity_{{$orderDetail->id}}" class="form-control" style="width: 100px; text-align: center; margin: auto;" oninput="updateQty(this.value,{{$orderDetail->id}})" readonly>
                  </td>
                  <td class="text-center">
                      <div style="position: relative; display: inline-flex; align-items: center;">
                          <span style="position: absolute; left: 10px; padding-right: 5px; color: #888;"> {{ $order->currency }} </span>
                          <input type="text" value="{{ $orderDetail->unit_price }}" name="unit_price" id="unit_price" class="form-control" style="padding-left: 30px; width: 100px; text-align: left; margin: auto;" oninput="updateUnitPrice(this.value,{{$orderDetail->id}})" readonly>
                      </div>
                  </td>
                  <td class="text-center">
                    <span id="spanTotalPrice_{{$orderDetail->id}}">{{ $order->currency.' '.$orderDetail->total_price }}</span>
                  </td>
                  <td class="text-center">
                    <i class="las la-arrow-circle-up" style="color: green; font-size: 24px; cursor:pointer;" onclick="productReverse({{$orderDetail->id}})"></i>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@else
  <div class="alert alert-danger">
    {{ translate('You do not have permission to view this page.') }}
  </div>
@endif
<div id="brandModal" class="modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Brand Order</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="brandForm">
          <div class="form-group">
            <label for="brand">Brand</label>
            <select name="brand" id="brand" class="form-control" onchange="showDivOwnBrand(this.value)">
              <option value="Your Brand">Your Brand</option>
              <option value="Our Brand - OPEL">Our Brand - OPEL</option>                                    
            </select>
            <input type="hidden" id="order_detail_id" name="order_detail_id" class="form-control" readonly>
          </div>
          <div class="form-group" id="divOwnBrand" style="display:none;">
            <label for="orderdetails_id">Enter Brand Name</label>
            <input type="text" id="brand_name" name="brand_name" class="form-control">
          </div>
          <!-- Add other form inputs as needed -->
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="" onclick="submitBrand()">Save changes</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div id="addProductModal" class="modal" role="dialog">
  <div class="modal-dialog custom-modal-width" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Product</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        // Some text here
      </div>
      <div class="modal-footer">
        <!-- <button type="button" class="btn btn-primary" id="" onclick="submitBrand()">Save changes</button> -->
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div id="commentModal" class="modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add or Edit Comment and Days Of Delivery of Order</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="commentForm">
          <div class="form-group">
            <label for="brand">Days Of Delivery</label>
            <input type="text" id="days_of_delivery" name="days_of_delivery" class="form-control">
            <input type="hidden" id="order_detail_id" name="order_detail_id" class="form-control" readonly>
          </div>
          <div class="form-group" id="divOwnBrand">
            <label for="orderdetails_id">Comment</label>
            <textarea id="comment" name="comment" class="form-control"></textarea>
          </div>
          <!-- Add other form inputs as needed -->
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="" onclick="submitComment()">Save changes</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div id="confirmModal" class="modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Enter advance amount and confirm the order</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="commentForm">
          <div class="form-group">
            <label for="brand">Enter advance amount</label>
            <input type="number" id="advance_amount" name="advance_amount" class="form-control">
          </div>
          <!-- Add other form inputs as needed -->
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="" onclick="submitConfirmOrder()">Confirm Order</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('script')
  <script type="text/javascript">
    $('#update_delivery_status').on('change', function() {
      var order_id = {{ $order->id }};
      var status = $('#update_delivery_status').val();
      $.post('{{ route('international_order_update_delivery_status') }}', {
        _token: '{{ @csrf_token() }}',
        order_id: order_id,
        status: status
      }, function(data) {
        if (data == 0) {
          AIZ.plugins.notify('danger', '{{ translate('Could not change status.') }}');
        } else {
          $('#spanDeliveryStatus').html(status.charAt(0).toUpperCase() + status.slice(1));
          AIZ.plugins.notify('success', '{{ translate('Order status has been updated.') }}');
        }
      });
    });

    function saveInReview(){
      var status = 'in_review';
      var order_id = {{ $order->id }};
      $.post('{{ route('international_order_update_delivery_status') }}', {
        _token: '{{ @csrf_token() }}',
        order_id: order_id,
        status: status
      }, function(data) {
        if (data == 0) {
          AIZ.plugins.notify('danger', '{{ translate('Could not change status.') }}');
        } else {
          AIZ.plugins.notify('success', '{{ translate('Save the order in review.') }}');
          // Redirect to the all_international_orders route
          setTimeout(function() {
            window.location.href = '{{ route('all_international_in_review_orders') }}';
          }, 3000); 
        }
      });
    }

    function submitConfirmOrder(){
      var advance_amount =  $('#advance_amount').val();
      var order_id = {{ $order->id }};
      $.post('{{ route('international_order_update_confirm_status') }}', {
        _token: '{{ @csrf_token() }}',
        order_id: order_id,
        advance_amount: advance_amount
      }, function(data) {
        if (data == 0) {
          AIZ.plugins.notify('danger', '{{ translate('Could not change status.') }}');
        } else {
          AIZ.plugins.notify('success', '{{ translate('Order had confirmed.') }}');
          // Redirect to the all_international_orders route
          setTimeout(function() {
            window.location.href = '{{ route('all_international_approved_orders') }}';
          }, 3000); 
        }
      });
    }

    $('#update_payment_status').on('change', function() {
      var order_id = {{ $order->id }};
      var status = $('#update_payment_status').val();
      $.post('{{ route('international_order_update_payment_status') }}', {
        _token: '{{ @csrf_token() }}',
        order_id: order_id,
        status: status
      }, function(data) {
        AIZ.plugins.notify('success', '{{ translate('Payment status has been updated') }}');
      });
    });

    function updateQty(qty, orderdetailId){
      $.post('{{ route('international_order_update_qty') }}', {
        _token: '{{ @csrf_token() }}',
        order_detail_id: orderdetailId,
        qty: qty
      }, function(data) {
        $('#spanTotalPrice_'+orderdetailId).html(data.currency+' '+data.subtotal);
        $('#spanGrandTotalPrice').html(data.currency+' '+data.grandTotal);
        $('#mainGrandTotal').html(data.currency+' '+data.grandTotal);
        AIZ.plugins.notify('success', '{{ translate('Quantity has been updated') }}');
      });
    }

    function updateUnitPrice(unit_price, orderdetailId){
      $.post('{{ route('international_order_update_unit_price') }}', {
        _token: '{{ @csrf_token() }}',
        order_detail_id: orderdetailId,
        unit_price: unit_price
      }, function(data) {
        $('#spanTotalPrice_'+orderdetailId).html(data.currency+' '+data.subtotal);
        $('#spanGrandTotalPrice').html(data.currency+' '+data.grandTotal);
        $('#mainGrandTotal').html(data.currency+' '+data.grandTotal);
        AIZ.plugins.notify('success', '{{ translate('Unit Price has been updated') }}');
      });
    }

    function openModal(element) {
        // Get data attributes from the clicked icon
        const brand = $(element).data('brand');
        const order_detail_id = $(element).data('orderdetails-id');
        const brand_name = $(element).data('brand-name');
        
        // Set the values in the modal's form fields
        $('#brand').val(brand);
        $('#order_detail_id').val(order_detail_id);
        $('#brand_name').val(brand_name);
        // Show or hide the 'divOwnBrand' based on the brand selection
        if (brand === "Your Brand") {
            $('#divOwnBrand').css('display', 'block');
        } else {
            $('#divOwnBrand').css('display', 'none');
        }
        // Show the modal
        $('#brandModal').modal('show');
    }

    function openCommentModal(element) {
        // Get data attributes from the clicked icon
        const comment = $(element).data('comment');
        const order_detail_id = $(element).data('orderdetails-id');
        const days_of_delivery = $(element).data('days-of-delivery');
        
        // Set the values in the modal's form fields
        $('#comment').val(comment);
        $('#order_detail_id').val(order_detail_id);
        $('#days_of_delivery').val(days_of_delivery);

        // Show the modal
        $('#commentModal').modal('show');
    }

    function openConfirmModal() {
        // Get data attributes from the clicked icon
        // const comment = $(element).data('comment');
        // const order_detail_id = $(element).data('orderdetails-id');
        // const days_of_delivery = $(element).data('days-of-delivery');
        
        // // Set the values in the modal's form fields
        // $('#comment').val(comment);
        // $('#order_detail_id').val(order_detail_id);
        // $('#days_of_delivery').val(days_of_delivery);

        // Show the modal
        $('#confirmModal').modal('show');
    }

    function openAddProductModal(element) {
        const order_id = $(element).data('order-id');

        // Fetch the product list using AJAX, sending order_detail_id in the data object
        $.ajax({
            url: "{{ route('products.getOwnBrandProductsList') }}",
            method: "GET",
            data: { order_id: order_id },
            success: function(response) {
                // Insert the response (the HTML from the Blade view) into the modal body
                $('#addProductModal .modal-body').html(response);

                // Show the modal
                $('#addProductModal').modal('show');
            },
            error: function() {
                alert("Failed to load product list.");
            }
        });
    }
    function showDivOwnBrand(selectedValue) {
      if (selectedValue == 'Your Brand') {
         $('#divOwnBrand').show();
      } else if (selectedValue == 'Our Brand - OPEL') {
         $('#divOwnBrand').hide();
         $('#brand_name').val('');
      }
    }

    function submitBrand() {
      var brand = $('#brand').val();
      var brand_name = $('#brand_name').val();
      var order_detail_id = $('#order_detail_id').val();
      $.post('{{ route('international_order_update_brand') }}', {
        _token: '{{ @csrf_token() }}',
        order_detail_id: order_detail_id,
        brand: brand,
        brand_name: brand_name
      }, function(data) {
        if(data.brand_name){
          $('#small_brand_'+order_detail_id).html(data.brand+' : '+data.brand_name);
          const brand_name = data.brand_name;
        }else{
          $('#small_brand_'+order_detail_id).html(data.brand);
          const brand_name = '';
        }
        const trBrandHTML = '<i class="las la-highlighter" style="color: green; font-size: 24px; cursor:pointer;" data-brand="'+data.brand+'" data-brand-name="'+brand_name+'" data-orderdetails-id="'+data.order_detail_id+'" onclick="openModal(this)"></i>';
        $('#trBrand_'+order_detail_id).html(trBrandHTML);
        $('#brandModal').modal('hide');
        AIZ.plugins.notify('success', '{{ translate('Brand has been updated.') }}');
      });
    }
    function submitComment() {
      var comment = $('#comment').val();
      var days_of_delivery = $('#days_of_delivery').val();
      var order_detail_id = $('#order_detail_id').val();
      $.post('{{ route('international_order_add_or_update_comment_and_days_of_delivery') }}', {
        _token: '{{ @csrf_token() }}',
        order_detail_id: order_detail_id,
        comment: comment,
        days_of_delivery: days_of_delivery
      }, function(data) {
        const trBrandHTML = '<i class="las la-comment" style="color: #25bcf1; font-size: 24px; cursor:pointer;" data-comment="'+data.comment+'"  data-days-of-delivery="'+data.days_of_delivery+'" data-orderdetails-id="'+order_detail_id+'" onclick="openCommentModal(this)"></i>';
        $('#trComment_'+order_detail_id).html(trBrandHTML);
        $('#commentModal').modal('hide');
        AIZ.plugins.notify('success', '{{ translate('Comment and days of delivery has been updated.') }}');
      });
    }


    function productDelete(order_detail_id) {
      var confirmMsg = confirm('Are you sure want to delete?');
      if(confirmMsg == true){
        $.post('{{ route('international_order_delete_product') }}', {
          _token: '{{ @csrf_token() }}',
          order_detail_id: order_detail_id
        }, function(data) {
          $('#spanGrandTotalPrice').html(data.currency+' '+data.grandTotal);
          $('#mainGrandTotal').html(data.currency+' '+data.grandTotal);
          AIZ.plugins.notify('success', '{{ translate('Product delete success.') }}');
          $('#tr_'+order_detail_id).html('');
          $('#divDeleteProduct').css('display', 'block');
          $('#tableDeleteProduct').html(data.html);
        });        
      }else{
        return false
      }      
    }

    function productReverse(order_detail_id) {
      var confirmMsg = confirm('Are you sure want to reverse this product?');
      if(confirmMsg == true){
        $.post('{{ route('international_order_reverse_product') }}', {
          _token: '{{ @csrf_token() }}',
          order_detail_id: order_detail_id
        }, function(data) {
          $('#spanGrandTotalPrice').html(data.currency+' '+data.grandTotal);
          $('#mainGrandTotal').html(data.currency+' '+data.grandTotal);
          AIZ.plugins.notify('success', '{{ translate('Product delete success.') }}');
          $('#tr_reverse_'+order_detail_id).html('');
          // $('#divDeleteProduct').css('display', 'block');
          $('#tableOrderProduct').html(data.html);
        });        
      }else{
        return false
      }      
    }

    function addToOrder(id){ 
      var confirmMsg = confirm('Are you sure want to add this product?');
      if(confirmMsg == true){
        $.post('{{ route('international_order_add_product') }}', {
          _token: '{{ @csrf_token() }}',
          product_id: id,
          order_id:{{$order->id}}
        }, function(data) {
          $('#spanGrandTotalPrice').html(data.currency+' '+data.grandTotal);
          $('#mainGrandTotal').html(data.currency+' '+data.grandTotal);
          if(data.addFlag == 1){
            $('#tableOrderProduct').html(data.html);
            AIZ.plugins.notify('success', '{{ translate('Product added success.') }}');
          }else if(data.addFlag == 0){
            AIZ.plugins.notify('warning', '{{ translate('Product already in order.') }}');
          }
        });        
      }else{
        return false
      }          
    }

  </script>
@endsection
