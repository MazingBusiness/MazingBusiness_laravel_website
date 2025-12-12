@extends('backend.layouts.app')

@section('content')
  <!-- Full-page Loader -->
<div id="loader">
    <div class="loader-overlay"></div>
    <div class="loader-content">
        <img src="/public/assets/img/ajax-loader.gif" alt="Loading...">
    </div>
</div>

<style>
  /* Loader full-screen background */
  #loader {
      display: none; /* Hidden by default */
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4); /* Semi-transparent dark overlay */
      backdrop-filter: blur(5px); /* Blur effect */
      z-index: 9999;
  }

  /* Centering the loading icon */
  .loader-content {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
  }

  .loader-content img {
      width: 80px;
      height: 80px;
  }

  .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
  }

  .modal-content {
      background-color: white;
      padding: 20px;
      margin: 15% auto;
      width: 60%;
      border-radius: 8px;
      text-align: center;
  }

  .close {
      float: right;
      font-size: 24px;
      cursor: pointer;
  }
  .table thead th {
      position: sticky;
      top: 0;
      background: white;
      z-index: 9999;
      transition: background-color 0.3s ease;
  }

  .table thead.th-sticky-active th {
      background-color:rgb(207, 224, 247); /* New color when sticky is active */
  }
</style>
<!-- Full-page Loader -->
<div id="loader">
    <div class="loader-overlay"></div>
    <div class="loader-content">
        <img src="/public/assets/img/ajax-loader.gif" alt="Loading...">
    </div>
</div>

<style>
  /* Loader full-screen background */
  #loader {
      display: none; /* Hidden by default */
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4); /* Semi-transparent dark overlay */
      backdrop-filter: blur(5px); /* Blur effect */
      z-index: 9999;
  }

  /* Centering the loading icon */
  .loader-content {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
  }

  .loader-content img {
      width: 80px;
      height: 80px;
  }
</style>

  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{ translate('Challan') }}</h5>
  </div>
    <!-- Display error messages, if any -->
    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif
    @if (session('success_msg'))
        <div class="alert alert-success">
            {{ session('success_msg') }}
        </div>
    @endif
  <form class="form form-horizontal mar-top" action="{{ route('order.saveChallan') }}" method="POST" enctype="multipart/form-data" id="challan_order_form">
    @csrf
    <input type="hidden" class="form-control" name="user_id" value="{{ $userDetails->id }}">
    <input type="hidden" class="form-control" name="sub_order_id" id="sub_order_id" value="{{ $orderData->id }}">
    <input type="hidden" class="form-control" name="warehouse_id" value="{{ $userDetails->user_warehouse->id }}">

    <div class="row gutters-5">
      <div class="col-lg-12">
        <div class="card mb-4">
          <div class="card-header text-white" style="background-color: #024285 !important;">
              <h5 class="mb-0">User Details</h5>
          </div>
          <div class="card-body">
            <div class="form-group row">
                <div class="col-md-4" style="font-size: 15px;">
                  <label class="col-form-label"><strong>Code : </strong></label> {{$orderData->sub_order_user_name}}
                </div>
                <div class="col-md-4" style="font-size: 15px;">
                  <label class="col-form-label"><strong>GST : </strong></label> {{$userDetails->gstin}}
                </div>
                <div class="col-md-4" style="font-size: 15px;">
                  <label class="col-form-label"><strong>Party : </strong></label> {{$userDetails->company_name}}
                </div>
            </div>
            <div class="form-group row">                
                <div class="col-md-4" style="font-size: 15px;">
                  <label class="col-form-label"><strong>Credit Limit : </strong></label> {{$userDetails->credit_limit}}
                </div>
                <div class="col-md-4" style="font-size: 15px;">
                  <label class="col-form-label"><strong>User's Warehouse : </strong></label> {{$userDetails->user_warehouse->name}}
                </div>
                <div class="col-md-4" style="font-size: 15px;">
                  <label class="col-form-label"><strong>Discount % : </strong></label> {{$userDetails->discount}}
                </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-12">
        <div class="card mb-4">
          <div class="card-header text-white" style="background-color: #024285 !important;">
              <h5 class="mb-0">Order Details</h5>
          </div>
          <div class="card-body row">
            <div class="col-md-6">
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Branch:</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="warehouse" placeholder="{{ translate('Branch') }}" value="{{ $orderData->order_warehouse->name }}" readonly>
                </div>
              </div>
              
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Challan Date:</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="order_date" placeholder="{{ translate('Offer Date') }}" value="{{ date('d-m-Y', strtotime($orderData->created_at)) }}" readonly>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Transport Name:</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="transport_name" placeholder="{{ translate('Transport Name') }}" value="{{ $orderData->transport_name }}" readonly>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Transport Id:</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="transport_id" placeholder="{{ translate('Transport Id.') }}" value="{{ $orderData->transport_id }}" readonly>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Transport Mobile:</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="transport_phone" placeholder="{{ translate('Transport Mobile.') }}" value="{{ $orderData->transport_phone }}" readonly>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Transport Remarks:</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="transport_remarks" placeholder="{{ translate('Transport Remarks.') }}" value="{{ $orderData->transport_remarks }}" readonly>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Ship To:</label>
                <div class="col-md-8">
                  @php
                      if($orderData->type == 'btr'){
                        $shipping_address = $branchDetails[$orderData->sub_order_user_name];
                      }else{
                        $shipping_address = json_decode($orderData->shipping_address, true);
                      }
                  @endphp
                  @if($shipping_address)
                      <strong>Company:</strong> {{ $shipping_address['company_name'] ?? 'N/A' }} <br>
                      <strong>GSTIN:</strong> {{ $shipping_address['gstin'] ?? 'N/A' }} <br>
                      <strong>Email:</strong> {{ $shipping_address['email'] ?? 'N/A' }} <br>
                      <strong>Address:</strong> {{ $shipping_address['address'] ?? 'N/A' }} <br>
                      <strong>City:</strong> {{ $shipping_address['city'] ?? 'N/A' }}, 
                      <strong>State:</strong> {{ $shipping_address['state'] ?? 'N/A' }} <br>
                      <strong>Country:</strong> {{ $shipping_address['country'] ?? 'N/A' }} <br>
                      <strong>Postal Code:</strong> {{ $shipping_address['postal_code'] ?? 'N/A' }} <br>
                      <strong>Phone:</strong> {{ $shipping_address['phone'] ?? 'N/A' }}
                  @else
                      <p>No billing address available</p>
                  @endif
                </div>
              </div>
            </div>
            <?php /*<div class="col-md-12">
              <div class="form-group row">
                <label class="col-md-2 col-form-label">Multi Scan:</label>
                <div class="col-md-10">
                  <input type="text" class="form-control" name="barcode" placeholder="{{ translate('Multi Scan') }}" value="">
                </div>
              </div>
            </div> */ ?>
          </div>
        </div>
      </div>
      <div class="col-lg-12">
        <div class="card mb-4">
          <div class="card-header text-white" style="background-color: #024285 !important;">
              <h5 class="mb-0">Item Table</h5>
          </div>
          <div class="card-body row">
            <table class="table mb-0 footable footable-1 breakpoint-xl" id="orderTable">
              <thead>
                  <tr class="footable-header">
                    <th style="display: table-cell;">Part No</th>
                    <th style="display: table-cell;">Item Name</th>
                    <th style="display: table-cell;">Hsn No</th>
                    <th style="display: table-cell;">GST</th>
                    <th style="display: table-cell;">Billed Qty</th>
                    <th style="display: table-cell;">Rate</th>
                    <th style="display: table-cell;">Billed Amount</th>
                  </tr>
              </thead>
              <tbody>
                @php
                  $count=1; 
                  $part_number = "";
                  $subOrderDetailId = "";
                @endphp              
                  @foreach($orderDetails as $subOrderDetail)
                    @foreach($subOrderDetail->product as $orderDetail)
                      @php
                        $closingStock = optional($orderDetail->stocks->where('warehouse_id', $subOrderDetail->warehouse_id)->first())->qty;
                        
                        if($closingStock == ""){
                          $closingStock = 0;
                        }
                        if($closingStock == "0"){
                          $style = "style='color:#f00;'";
                        }else{
                          $style = "";
                        } 
                        $part_number = $part_number.','.$orderDetail->part_no;
                        $subOrderDetailId = $subOrderDetailId.','.$subOrderDetail->id;
                      @endphp
                      <?php /* @if($subOrderDetail->reallocated < $subOrderDetail->approved_quantity) */ ?>
                        <tr id="row_{{$count}}">      
                          <td style="display: table-cell;">
                            {{ $orderDetail->part_no }}
                          </td>
                          <td style="display: table-cell;">
                            {{ $orderDetail->name }}
                            <div><strong>Seller Name : </strong>{{ $orderDetail->sellerDetails->user->name }}</div>
                            <div><strong>Seller Location : </strong>{{ $orderDetail->sellerDetails->user->user_warehouse->name }}</div>
                          </td>
                          <td style="display: table-cell;"><span style="{{ strlen($orderDetail->hsncode) < 8 ? 'color:#f00;' : '' }}" >{{ $orderDetail->hsncode }}</span></td>
                          <td style="display: table-cell;">{{ $orderDetail->tax }}%</td>
                          <td style="display: table-cell;">
                            {{ $subOrderDetail->quantity }}
                          </td>
                          <td style="display: table-cell;">
                            {{ single_price($subOrderDetail->rate) }}
                          </td>
                          <td style="display: table-cell;">
                            {{ single_price($subOrderDetail->final_amount) }}
                          </td>
                        </tr>
                      @php                      
                        $count++;
                      @endphp
                    @endforeach
                  @endforeach
              </tbody>              
            </table>
          </div>
        </div>
      </div>
    </div>
    <div>
      <a href="{{ route('order.allChallan')}}" class="btn btn-info" id="saveChalan" style="float:right;">Back</a>         
    </div>
  </form>
  <!-- Pre Closed Modal -->
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
            <input type="hidden" class="form-control" name="sub_order_id" id="sub_order_id" value="" required>
            <input type="hidden" class="form-control" name="sub_order_qty" id="sub_order_qty" value="" required>
            <input type="hidden" class="form-control" name="sub_order_type" id="sub_order_type" value="" required>
            
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

  <!-- Confirmation Modal -->
  <div class="modal fade" id="confirmCloseModal" tabindex="-1" aria-labelledby="confirmCloseModalLabel" aria-hidden="true">
      <div class="modal-dialog">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title">Confirm Close Order</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                  </button>
              </div>
              <div class="modal-body">
                  <p>Do you want to close the main order?</p>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" id="btnNoClose" data-dismiss="modal">No</button>
                  <button type="button" class="btn btn-primary" id="btnYesClose">Yes</button>
              </div>
          </div>
      </div>
  </div>

  <!-- New product add thrugh barcode reader modal -->
   <!-- Product Details Modal -->
  <div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productDetailsModalLabel">Product Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="table">
                    <tr>
                        <th>Part Number</th>
                        <td id="modal_part_no"></td>
                    </tr>
                    <tr>
                        <th>Name</th>
                        <td id="modal_name"></td>
                    </tr>
                    <tr>
                        <th>Seller Name</th>
                        <td id="modal_seller_name"></td>
                    </tr>
                    <tr>
                        <th>Seller Location</th>
                        <td id="modal_seller_location"></td>
                    </tr>
                    <tr>
                        <th>Order For</th>
                        <td id="modal_order_for"></td>
                    </tr>
                    <tr>
                        <th>HSN Code</th>
                        <td id="modal_hsncode"></td>
                    </tr>
                    <tr>
                        <th>Tax</th>
                        <td id="modal_tax"></td>
                    </tr>
                    <tr>
                        <th>Closing Stock</th>
                        <td id="modal_closing_stock"></td>
                    </tr>
                    <tr>
                        <th>Quantity</th>
                        <td id="modal_quantity"></td>
                    </tr>
                    <tr>
                        <th>Price</th>
                        <td id="modal_price"></td>
                    </tr>
                    <tr>
                        <th>Billed Amount</th>
                        <td id="modal_billed_amount"></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
  </div>
@endsection

@section('script')
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- jQuery & Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    $(document).ready(function () {
      // Function to remove a row when clicking the delete icon
      $(document).on("click", ".delete-row", function () {
        $("#loader").show(); // Show loader when updating values
        let rowId = $(this).data("rowid");
        let order_details_id = $("#order_details_id_" + rowId).val();
        var conf = confirm("Are you sure for delete this row?")
        if(conf == true){
          $.ajax({
              url: "{{ route('cart.removeProductFromSplitOrder') }}", // Laravel route
              type: "POST",
              data: {
                  order_details_id: order_details_id,
                  _token: "{{ csrf_token() }}" // CSRF token for security
              },
              success: function(response) {
                  // alert(response.msg);                    
                  $("#row_" + rowId).remove();
                  AIZ.plugins.notify('success', response.msg);
              },
              error: function(xhr) {
                  alert("Error: " + xhr.responseJSON.error);
              }
          });
          // $("#row_" + rowId).remove();
        }
        // Use setTimeout to ensure the loader is visible for at least a short time
        setTimeout(function(){
              $("#loader").hide(); // Hide loader after a delay
          }, 500); // 100ms delay, adjust as needed
      });

      $("#btr_verification").change(function () {
          if ($(this).is(":checked")) {
              $("#btrConfigModal").modal("show"); // Open modal
          } else {
              $("#btrConfigModal").modal("hide"); // Hide modal if unchecked
          }
      });

      var form = $("#add_carrier_form");
      $(document).on("click", ".preclose", function () {
          var subOrderDetailsId = $(this).data("id");
          var subOrderId = $(this).data("sub_order_id");
          var subOrderQty = $(this).data("sub_order_qty");
          var subOrderType = $(this).data("sub_order_type");
          var closingStock = $(this).data("closing_stock");
          var itemName = $(this).data("item_name");

          $("#sub_order_details_id").val(subOrderDetailsId);
          $("#sub_order_id").val(subOrderId);
          $("#sub_order_qty").val(subOrderQty);
          $("#sub_order_type").val(subOrderType);
          $("#spanOrderQty").html(subOrderQty);
          $("#spanItemName").html(itemName);
          $("#pre_closed").attr("max", subOrderQty); // Set max limit

          $("#preCloseModal").modal("show");
      });

      // When the form is submitted
      form.on("submit", function (e) {
          var subOrderType = $("#sub_order_type").val();

          if (subOrderType === "btr") {
              e.preventDefault(); // Prevent form from submitting immediately

              // Show confirmation modal
              $("#confirmCloseModal").modal("show");
          }
      });

      // Handle Yes button in confirmation modal
      $("#btnYesClose").on("click", function () {
          $("<input>").attr({
              type: "hidden",
              name: "main_order_close",
              value: "1"
          }).appendTo(form); // Add hidden field
          
          form.off("submit").submit(); // Submit the form with main_order_close = 1
      });

      // Handle No button in confirmation modal
      $("#btnNoClose").on("click", function () {
          form.off("submit").submit(); // Submit the form normally
      });

      // Restrict input to max sub_order_qty
      $(document).on("input", "#pre_closed", function() {
          var maxQty = parseInt($("#sub_order_qty").val(), 10); // Get max quantity
          var enteredQty = parseInt($(this).val(), 10); // Get entered value

          if (enteredQty > maxQty) {
              $(this).val(maxQty); // Reset to maxQty if user enters more
          }
      });

      $(document).on("change", ".transport-select", function(){      
        let warehouseId = $(this).data("warehouse");      
        let selectedOption = $(this).find("option:selected");
        let gstNumber = selectedOption.data("gst") || "";
        let transport_table_id = selectedOption.data("transport_table_id") || "";
        let mobile = selectedOption.data("mobile") || "";
        $("#btr_transport_id_" + warehouseId).val(gstNumber);
        $("#btr_transport_table_id_" + warehouseId).val(transport_table_id);
        $("#btr_transport_mobile_" + warehouseId).val(mobile);
      });

      $(document).on("change", ".billToSelect", function(){
        let selectedOption = $(this).find("option:selected");
        let address = selectedOption.data("address") || "";
        $("#pBillToAddress").html('<strong>Address:</strong> '+address);
      });

      $(document).on("change", ".shipToSelect", function(){
        let selectedOption = $(this).find("option:selected");
        let address = selectedOption.data("address") || "";
        $("#pShipToAddress").html('<strong>Address:</strong> '+address);
      });

      $(document).on("input", ".allocate-qty-input", function() {
          $("#loader").show(); // Show loader when updating values
          let row = $(this).closest("tr");
          let orderDetailId = row.find("[name^='order_qty_']").attr("id").split("_")[2]; // Extract orderDetailId

          // Fetch input values for stocks
          let kolkataStock = parseFloat(row.find("[name='Kolkata_allocate_qty_" + orderDetailId + "']").val()) || 0;
          let delhiStock = parseFloat(row.find("[name='Delhi_allocate_qty_" + orderDetailId + "']").val()) || 0;
          let mumbaiStock = parseFloat(row.find("[name='Mumbai_allocate_qty_" + orderDetailId + "']").val()) || 0;

          // Calculate total allocated quantity
          let allocatedQty = kolkataStock + delhiStock + mumbaiStock;
          let orderQty = parseFloat(row.find("[name='order_qty_" + orderDetailId + "']").val()) || 0;

          // Update the allocated quantity display
          let allocateQtyS = row.find("#allocateQtyS_" + orderDetailId);
          allocateQtyS.text(allocatedQty);

          let regrateQty = row.find("#regrate_qty_" + orderDetailId);
          // Change color based on condition
          if (allocatedQty < orderQty) {
              allocateQtyS.css("color", "red");
              regrateQty.val(orderQty - allocatedQty); 
          } else if (allocatedQty == orderQty) {
              allocateQtyS.css("color", "green");
              regrateQty.val('0'); 
          } else {
              let conf = confirm("You are allocating more than the purchase quantity. Do you want to continue?");
              if (!conf) {
                  // Reset the input field that triggered the event
                  $(this).val(""); // Clears the current input field
                  // Fetch input values for stocks
                  let kolkataStock = parseFloat(row.find("[name='Kolkata_allocate_qty_" + orderDetailId + "']").val()) || 0;
                  let delhiStock = parseFloat(row.find("[name='Delhi_allocate_qty_" + orderDetailId + "']").val()) || 0;
                  let mumbaiStock = parseFloat(row.find("[name='Mumbai_allocate_qty_" + orderDetailId + "']").val()) || 0;
                  allocatedQty = kolkataStock + delhiStock + mumbaiStock; // Recalculate
                  
                  allocateQtyS.text(allocatedQty).css("color", "red"); // Update again
              } else {
                  allocateQtyS.css("color", "darkblue");
              }
              regrateQty.val('0'); 
          }

          // Update the total price
          let spanSubTotal = row.find("#spanSubTotal_" + orderDetailId);
          let rate = parseFloat(row.find("#rate_" + orderDetailId).val()) || 0;
          spanSubTotal.text(allocatedQty*rate);

          let subTotal = row.find("#subTotal_" + orderDetailId);
          subTotal.text(allocatedQty*rate);

          // Use setTimeout to ensure the loader is visible for at least a short time
          setTimeout(function(){
              $("#loader").hide(); // Hide loader after a delay
          }, 500); // 100ms delay, adjust as needed
      });

      $(document).on("input", ".rate-input", function() {
        $("#loader").show(); // Show loader when updating values
        let row = $(this).closest("tr");
        let orderDetailId = row.find("[name^='order_qty_']").attr("id").split("_")[2]; // Extract orderDetailId

        // Fetch input values for stocks
        let kolkataStock = parseFloat(row.find("[name='Kolkata_allocate_qty_" + orderDetailId + "']").val()) || 0;
        let delhiStock = parseFloat(row.find("[name='Delhi_allocate_qty_" + orderDetailId + "']").val()) || 0;
        let mumbaiStock = parseFloat(row.find("[name='Mumbai_allocate_qty_" + orderDetailId + "']").val()) || 0;

        // Calculate total allocated quantity
        let allocatedQty = kolkataStock + delhiStock + mumbaiStock;

        // Update the total price
        let spanSubTotal = row.find("#spanSubTotal_" + orderDetailId);
        let rate = parseFloat(row.find("#rate_" + orderDetailId).val()) || 0;
        spanSubTotal.text(allocatedQty*rate);

        let subTotal = row.find("#subTotal_" + orderDetailId);
        subTotal.text(allocatedQty*rate);

        // Use setTimeout to ensure the loader is visible for at least a short time
        setTimeout(function(){
              $("#loader").hide(); // Hide loader after a delay
          }, 500); // 100ms delay, adjust as needed
      });

      $('#saveChalan').on('click', function() {
        // $('#order_status').val('completed');
        $('#challan_order_form').submit();
      });

      $('#btnSubmitAddCarrier').on('click', function() {
        $('#add_carrier_form').submit();
      });

      // Barcode Scan
      let scannedBarcodes = new Set(); // Store scanned barcodes to check for duplicates

      $("#barcode").keypress(function(event) {
          if (event.which === 13) { // Check if Enter key is pressed
              event.preventDefault(); // Prevent default behavior (new line in textarea)            
              let barcodeInput = $("#barcode");
              let barcodeText = barcodeInput.val().trim(); // Get all barcodes in the textarea            
              if (barcodeText !== "") {
                  let barcodes = barcodeText.split("\n"); // Split by new line
                  let latestBarcode = barcodes[barcodes.length - 1].trim(); // Get the last scanned barcode

                  if (latestBarcode.length > 7) { // Ensure barcode is valid
                      // if (scannedBarcodes.has(latestBarcode)) {
                      //     alert("This barcode has already been scanned!");
                      //     barcodeInput.val(''); 
                      //     return; 
                      // }                       
                      // scannedBarcodes.add(latestBarcode);
                      let part_number = latestBarcode.substring(0, 7);
                      let quantity = parseInt(latestBarcode.substring(7), 10);

                      let hiddenInput = $("#" + part_number);
                      if (hiddenInput.length === 0) {
                          if (confirm("This product is new. Do you want to add?")) {
                              let sub_order_id = $("#order_id").val();
                              let user_id = $("#user_id").val();
                              $.ajax({
                                  url: "{{ route('order.addNewProductByScan') }}",
                                  method: "POST",
                                  data: {
                                      _token: $('meta[name="csrf-token"]').attr('content'),
                                      part_number: part_number,
                                      quantity: quantity,
                                      sub_order_id: sub_order_id,
                                      user_id: user_id
                                  },
                                  success: function(response) {
                                      if (response.success) {
                                          alert("Product added successfully!");

                                          // Create new row
                                          let hsncode = response.data.hsncode;
                                          let hsnStyle = hsncode.length < 8 ? 'color:#f00;' : '';

                                          let closingStockStyle = response.data.closing_stock <= 0 ? 'color:#f00;' : '';
                                          
                                          var reallocationRoute = "{{ route('order.reallocationSplitOrder', ':id') }}";
                                          let reallocationUrl = reallocationRoute.replace(':id', response.data.id);

                                          let newRow = `
                                              <tr id="row_${response.data.id}">
                                                  <td style="display: table-cell;">${response.data.part_no}</td>
                                                  <td style="display: table-cell;">
                                                      ${response.data.name}
                                                      <div><strong>Seller Name : </strong>${response.data.seller_name}</div>
                                                      <div><strong>Seller Location : </strong>${response.data.seller_location}</div>
                                                      <div><strong>Order For : </strong>${response.data.order_for}</div>
                                                      <div><input type="text" class="form-control" name="remark_${response.data.id}" id="remark_${response.data.id}" value="" placeholder="Remarks"></div>
                                                  </td>
                                                  <td style="display: table-cell;"><span style="${hsnStyle}">${hsncode}</span></td>
                                                  <td style="display: table-cell;">${response.data.tax}%</td>
                                                  <td style="display: table-cell;"><span style="${closingStockStyle}">${response.data.closing_stock}</span></td>
                                                  <td style="display: table-cell;">
                                                  <span style="${closingStockStyle}">${response.data.quantity}</span>
                                                  <input type="hidden" id="${response.data.part_no}" value="${response.data.quantity}">
                                                  </td>
                                                  <td style="display: table-cell;">
                                                      <input type="text" name="billed_qty" id="billed_qty_${response.data.part_no}" value="${response.data.quantity}" class="form-control" />
                                                  </td>
                                                  <td style="display: table-cell;">${response.data.price}</td>
                                                  <td style="display: table-cell;">
                                                      <span id="span_billed_amount_${response.data.part_no}">${response.data.billed_amount}</span>
                                                      <input type="hidden" name="billed_amount_${response.data.part_no}" id="billed_amount_${response.data.part_no}" value="${response.data.billed_amount}">
                                                  </td>
                                                  <td style="display: table-cell;">
                                                    <i class="las la-handshake preclose" style="font-size: 30px; color:#f00; cursor:pointer;" title="Pre Close" data-id="${response.data.id}" data-sub_order_id="${response.data.sub_order_id}" data-sub_order_type="${response.data.type}" data-sub_order_qty="${response.data.quantity}" data-closing_stock="${response.data.closing_stock}" data-item_name="${response.data.name}"></i>

                                                    <a class="btn btn-soft-success btn-icon btn-circle btn-sm" href="${reallocationUrl}" title="{{ translate('Reallocation Order') }}" style="background-color: #00ffe7;"><i class="las la-project-diagram"></i></a>

                                                  </td>
                                              </tr>
                                          `;
                                          $("#orderTable tbody").append(newRow);

                                          // Show product details in modal
                                    $("#modal_part_no").text(response.data.part_no);
                                    $("#modal_name").text(response.data.name);
                                    $("#modal_seller_name").text(response.data.seller_name);
                                    $("#modal_seller_location").text(response.data.seller_location);
                                    $("#modal_order_for").text(response.data.order_for);
                                    $("#modal_hsncode").text(response.data.hsncode);
                                    $("#modal_tax").text(response.data.tax + "%");
                                    $("#modal_closing_stock").text(response.data.closing_stock);
                                    $("#modal_quantity").text(response.data.quantity);
                                    $("#modal_price").text(response.data.price);
                                    $("#modal_billed_amount").text(response.data.billed_amount);


                                          $("#productDetailsModal").modal("show");
                                      } else {
                                          alert("Error adding product.");
                                      }
                                  },
                                  error: function(xhr) {
                                      console.error(xhr.responseText);
                                      alert("Error sending data!");
                                  }
                              });
                          }
                      } else {
                          let availableQty = parseInt(hiddenInput.val(), 10) || 0;
                          let temp_billed_qty = $("#billed_qty_" + part_number).val();
                          let rate = $("#rate_" + part_number).val();
                          let billed_qty = (parseInt(temp_billed_qty, 10) || 0) + quantity;

                          if (billed_qty > availableQty) {
                              if (confirm("Scan quantity is greater than order quantity.")) {
                                $("#billed_qty_" + part_number).val(billed_qty);
                                $("#span_billed_amount_" + part_number).html(rate * billed_qty);
                                $("#billed_amount_" + part_number).val(rate * billed_qty);
                              }
                          }else{
                            $("#billed_qty_" + part_number).val(billed_qty);
                            $("#span_billed_amount_" + part_number).html(rate * billed_qty);
                            $("#billed_amount_" + part_number).val(rate * billed_qty);
                          }
                          
                      }
                  }
                  barcodeInput.val('');
              }
          }
      });

    });

  </script>

@endsection
