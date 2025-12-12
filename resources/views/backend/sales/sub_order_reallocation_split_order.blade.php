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
      z-index: 99;
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
    <h5 class="mb-0 h6">{{ translate('Re Allocation Order') }}</h5>
  </div>
  <!-- Display error messages, if any -->
  @if (session('error'))
      <div class="alert alert-danger">
          {{ session('error') }}
      </div>
  @endif

  <form class="form form-horizontal mar-top" action="{{ route('order.saveSubOrderReAllocationOrder') }}" method="POST" enctype="multipart/form-data" id="split_order_form">
    @csrf
    <input type="hidden" class="form-control" name="user_id" value="{{ $userDetails->id }}">
    <input type="hidden" class="form-control" name="order_id" value="{{ $subOrderData->order_id }}">
    <input type="hidden" class="form-control" name="combined_order_id" value="{{ $subOrderData->combined_order_id }}">
    <input type="hidden" class="form-control" name="warehouse_id" value="{{ $userDetails->user_warehouse->id }}">
    <input type="hidden" class="form-control" name="sub_order_id" value="{{ $subOrderData->id }}">
    <div class="row gutters-5">
      <div class="col-lg-12">
        <div class="card mb-4">
          <div class="card-header text-white" style="background-color: #024285 !important;">
              <h5 class="mb-0">User Details</h5>
          </div>
          <div class="card-body">
            <div class="form-group row">
                <div class="col-md-4" style="font-size: 20px;">
                  <label class="col-form-label"><strong>Code : </strong></label> {{$userDetails->party_code}}
                </div>
                <div class="col-md-4" style="font-size: 20px;">
                  <label class="col-form-label"><strong>GST Number : </strong></label> {{$shippingAddress->gstin}}
                </div>
                <div class="col-md-4" style="font-size: 20px;">
                  <label class="col-form-label"><strong>Due : </strong></label> {{$shippingAddress->due_amount .' '.$shippingAddress->dueDrOrCr}}
                </div>
            </div>
            <div class="form-group row">
                <div class="col-md-4" style="font-size: 20px;">
                  <label class="col-form-label"><strong>Over Due : </strong></label> {{$shippingAddress->overdue_amount .' '.$shippingAddress->overdueDrOrCr}}
                </div>
                <div class="col-md-4" style="font-size: 20px;">
                  <label class="col-form-label"><strong>Credit Limit : </strong></label> {{$userDetails->credit_limit}}
                </div>
                <div class="col-md-4" style="font-size: 20px;">
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
                <label class="col-md-3 col-form-label">Party:</label>
                <div class="col-md-9">
                  <input type="text" class="form-control" name="party_name" value="{{ $userDetails->company_name }}" readonly>
                </div>
              </div>
              <?php /*
              <div class="form-group row">
                <label class="col-md-3 col-form-label">A/C Name:</label>
                <div class="col-md-9">
                  <select name="acc_id" id="acc_id" class="form-control select2">
                      <option value="">Party</option>
                      @foreach ($allAddressesForThisUser as $address)
                          <option value="{{ $address->id }}" 
                              @if($subOrderData->address_id == $address->id) 
                                  selected 
                              @endif>
                              {{ $address->company_name.' ('.$address->acc_code.')' }}
                          </option>
                      @endforeach
                  </select>
                </div>
              </div> */ ?>
              <div class="form-group row">
                <label class="col-md-3 col-form-label">Branch:</label>
                <div class="col-md-9">
                  <input type="text" class="form-control" name="warehouse" placeholder="{{ translate('Branch') }}" value="{{ $userDetails->user_warehouse->name }}" readonly>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-3 col-form-label">Bill To:</label>
                <div class="col-md-9">
                  @php
                      $subOrderShippingId = optional($subOrderDetailsData->first())->billing_address_id;
                      $selectedAddress = $subOrderShippingId ?? old('ship_to', $subOrderData->billing_address_id);
                      $billAddress = '';
                  @endphp
                <select name="bill_to" id="bill_to" class="form-control select2 billToSelect">
                      
                      @foreach ($allAddressesForThisUser as $address)
                          @php
                              $isSelected = $selectedAddress == $address->id ? 'selected' : '';
                              if ($selectedAddress == $address->id) {
                                  $billAddress = $address->address;
                              }
                          @endphp
                          <option value="{{ $address->id }}" {{ $isSelected }} data-address="{{ $address->address }}">
                              {{ $address->company_name.' - '.$address->city.' - '.$address->postal_code }}
                          </option>
                      @endforeach
                  </select>
                  <p id="pBillToAddress"><strong>Address : </strong>{{ $billAddress }}</p>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group row">
                <label class="col-md-3 col-form-label">Date:</label>
                <div class="col-md-9">
                  <input type="text" class="form-control" name="order_date" placeholder="{{ translate('Offer Date') }}" value="{{ date('d-m-Y', strtotime($subOrderData->created_at)) }}" readonly>
                </div>
              </div>
              <div class="form-group row" style="display:none;">
                <label class="col-md-3 col-form-label">Status:</label>
                <div class="col-md-9">
                  <select name="order_status" id="order_status" class="form-control select2">
                      <option value="draft">Draft</option>
                      <option value="completed">Completed</option>
                  </select>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-3 col-form-label">Order No:</label>
                <div class="col-md-9">
                  <input type="text" class="form-control" name="code" placeholder="{{ translate('Offer No.') }}" value="{{ $subOrderData->code }}" readonly>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-3 col-form-label">Ship To:</label>
                <div class="col-md-9">
                  <select name="ship_to" id="ship_to" class="form-control select2 shipToSelect">
                      @php
                          $subOrderShippingId = optional($subOrderDetailsData->first())->shipping_address_id;
                          $selectedAddress = $subOrderShippingId ?? old('ship_to', $subOrderData->shipping_address_id);
                          $shipAddress = '';
                      @endphp
                      @foreach ($allAddressesForThisUser as $address)
                          @php
                              $isSelected = $selectedAddress == $address->id ? 'selected' : '';
                              if ($selectedAddress == $address->id) {
                                  $shipAddress = $address->address;
                              }
                          @endphp
                          <option value="{{ $address->id }}" {{ $isSelected }} data-address="{{ $address->address }}">
                              {{ $address->company_name.' - '.$address->city.' - '.$address->postal_code }}
                          </option>
                      @endforeach
                  </select>
                  <p id="pShipToAddress"><strong>Address : </strong>{{ $shipAddress }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-12">
        <div class="card mb-4">
          <div class="card-header text-white" style="background-color: #024285 !important;">
              <h5 class="mb-0">Item Table</h5>
          </div>
          <div class="card-body row">
            <table class="table mb-0 footable footable-1 breakpoint-xl">
              <thead>
                  <tr class="footable-header">
                    <th style="display: table-cell;">Item Details</th>
                    <th style="display: table-cell;">Quantity</th>
                    <th style="display: table-cell;">Kolkata</th>
                    <th style="display: table-cell;">Delhi</th>
                    <th style="display: table-cell;">Mumbai</th>
                    <th style="display: table-cell;">Allocate Qty</th>
                    <th style="display: table-cell;">Rate</th>
                    <th style="display: table-cell;">Amount</th>
                  </tr>
              </thead>
              <tbody>
                @php
                  $count=1;
                @endphp
                @foreach($subOrderDetailsData as $orderDetail)
                  @php
                      // Retrieve old allocated values or set to 0 if not present
                      $kolkataAllocated = old('Kolkata_allocate_qty_' . $orderDetail->order_details_id, 0);
                      $delhiAllocated = old('Delhi_allocate_qty_' . $orderDetail->order_details_id, 0);
                      $mumbaiAllocated = old('Mumbai_allocate_qty_' . $orderDetail->order_details_id, 0);
                      $rate = old('rate_' . $orderDetail->order_details_id, 0);

                      // Calculate total allocated quantity
                      $totalAllocated = $kolkataAllocated + $delhiAllocated + $mumbaiAllocated;

                      // Calculate subtotal
                      $subTotal = $totalAllocated * $rate;                      

                      // Retrieve order quantity
                      $orderQty = $orderDetail->approved_quantity;                      

                      // Retrieve submited value
                      $btrQty = 0;
                      $splitOrder = App\Models\SubOrder::where('order_id',$subOrderData->id)->where('warehouse_id','1')->first();
                      $subOrderProductQty=NULL;
                      if(isset($subOrderData->sub_order) AND $subOrderData->sub_order != NULL){
                        $splitOrder = App\Models\SubOrder::where('order_id',$subOrderData->id)->where('warehouse_id','1')->first();
                        $splitOrderDetails = App\Models\SubOrderDetail::where('order_id',$subOrderData->id)->where('product_id',$orderDetail->product_data->id)->where('warehouse_id','1')->first();
                        if($splitOrderDetails != NULL){
                          $subOrderProductQty = $splitOrderDetails->approved_quantity;
                          if(isset($splitOrder->type) AND $splitOrder->type == 'btr'){
                            $btrQty += $subOrderProductQty;
                          }
                        }
                        
                      } 
                      $kolQty = $subOrderProductQty ?? old('Kolkata_allocate_qty_' . $orderDetail->order_details_id);

                      $subOrderProductQty=NULL;
                      if(isset($subOrderData->sub_order) AND $subOrderData->sub_order != NULL){
                        $splitOrder = App\Models\SubOrder::where('order_id',$subOrderData->id)->where('warehouse_id','2')->first();
                        $splitOrderDetails = App\Models\SubOrderDetail::where('order_id',$subOrderData->id)->where('product_id',$orderDetail->product_data->id)->where('warehouse_id','2')->first();
                        if($splitOrderDetails != NULL){
                          $subOrderProductQty = $splitOrderDetails->approved_quantity;
                          if(isset($splitOrder->type) AND $splitOrder->type == 'btr'){
                            $btrQty += $subOrderProductQty;
                          }
                        }                              
                      } 
                      $delhiQty = $subOrderProductQty ?? old('Delhi_allocate_qty_' . $orderDetail->order_details_id);

                      $subOrderProductQty=NULL;
                      if(isset($subOrderData->sub_order) AND $subOrderData->sub_order != NULL){
                        $splitOrder = App\Models\SubOrder::where('order_id',$subOrderData->id)->where('warehouse_id','6')->first();
                        $splitOrderDetails = App\Models\SubOrderDetail::where('order_id',$subOrderData->id)->where('product_id',$orderDetail->product_data->id)->where('warehouse_id','6')->first();
                        if($splitOrderDetails != NULL){
                          $subOrderProductQty = $splitOrderDetails->approved_quantity;
                          if(isset($splitOrder->type) AND $splitOrder->type == 'btr'){
                            $btrQty += $subOrderProductQty;
                          }
                        }
                        
                      } 
                      $mumbaiQty = $subOrderProductQty ?? old('Delhi_allocate_qty_' . $orderDetail->order_details_id);

                      if($subOrderData->user->warehouse_id == 1){
                        $kolQty = $kolQty - $btrQty;
                      }elseif($subOrderData->user->warehouse_id == 2){
                        $delhiQty = $delhiQty - $btrQty;
                      }elseif($subOrderData->user->warehouse_id == 6){
                        $mumbaiQty = $mumbaiQty - $btrQty;
                      }

                      $totalAllocated = $kolQty + $delhiQty + $mumbaiQty;

                      // Determine color coding
                      $color = '';
                      if ($totalAllocated < $orderQty) {
                          $color = 'color:red;';
                      } elseif ($totalAllocated == $orderQty) {
                          $color = 'color:green;';
                      } else {
                          $color = 'color:darkblue;';
                      }
                      // Calculate subtotal
                      $subTotal = $totalAllocated * ceil($orderDetail->price/$orderDetail->approved_quantity);

                      $kolkataStocks = DB::table('products_api')->where('part_no', $orderDetail->product_data->part_no)->where('godown', 'Kolkata')->first();
                      $delhiStocks = DB::table('products_api')->where('part_no', $orderDetail->product_data->part_no)->where('godown', 'Delhi')->first();
                      $mumbaiStocks = DB::table('products_api')->where('part_no', $orderDetail->product_data->part_no)->where('godown', 'Mumbai')->first();

                      $kolStocks = $kolkataStocks ? (int)$kolkataStocks->closing_stock : 0;
                      $delStocks = $delhiStocks ? (int)$delhiStocks->closing_stock : 0;
                      $mumStocks = $mumbaiStocks ? (int)$mumbaiStocks->closing_stock : 0;

                  @endphp
                  @if(($orderDetail->pre_closed + $orderDetail->reallocated + $orderDetail->challan_qty + $orderDetail->in_transit) < $orderDetail->approved_quantity)
                    <tr id="row_{{$count}}">      
                        <td style="display: table-cell;">
                          <p><strong>{{ $orderDetail->product_data->name }}</strong></p>
                          <input type="hidden" class="form-control" name="product_id_{{ $orderDetail->order_details_id }}" value="{{ $orderDetail->product_data->id }}">
                          <input type="hidden" name="order_details_id[]" id="order_details_id_{{$count}}" value="{{ $orderDetail->order_details_id }}" >
                          <input type="hidden" name="sub_order_details_id[]" id="sub_order_details_id{{$count}}" value="{{ $orderDetail->id }}" >
                          <div><strong>Part Number : <span style="color: #024285;" >{{ $orderDetail->product_data->part_no }} - {{ $orderDetail->product_data->id }}</span></strong></div>
                          <div><strong>HSN : </strong><span style="{{ strlen($orderDetail->product_data->hsncode) < 8 ? 'color:#f00;' : '' }}" >{{ $orderDetail->product_data->hsncode.' - '.$orderDetail->product_data->tax.'%' }}</span></div>
                          <div><strong>Seller Name : </strong>{{ $orderDetail->product_data->sellerDetails->user->name }}</div>
                          <div><strong>Seller Location : </strong>{{ $orderDetail->product_data->sellerDetails->user->user_warehouse->name }}</div>
                          <input type="text" class="form-control" name="remark_{{ $orderDetail->order_details_id }}" value="{{ old('remark_' . $orderDetail->order_details_id) }}" placeholder="Remark">
                        </td>
                        <td style="display: table-cell;">
                          <input type="text" class="form-control" name="order_qty_{{ $orderDetail->order_details_id }}" id="order_qty_{{ $orderDetail->order_details_id }}" value="{{ $orderDetail->approved_quantity - ($orderDetail->reallocated + $orderDetail->in_transit) }}" readonly>
                          <div class="col-md-12" style="text-align: center;">
                            <?php /* <strong>{{ optional($orderDetail->product_data->stocks->where('warehouse_id', 1)->first())->qty + optional($orderDetail->product_data->stocks->where('warehouse_id', 2)->first())->qty + optional($orderDetail->product_data->stocks->where('warehouse_id', 6)->first())->qty }}</strong> */ ?>
                            <strong>{{ $kolStocks + $delStocks + $mumStocks }}</strong>
                          </div>
                        </td>
                        <td style="display: table-cell;">
                          <input type="number" class="form-control allocate-qty-input" name="Kolkata_allocate_qty_{{ $orderDetail->order_details_id }}" value="{{ $kolQty != '0' ? $kolQty : '' }}" @if($subOrderData->warehouse_id == 1) readonly @endif> 
                          
                          <div class="form-group row">
                            <div class="col-md-12" style="text-align: center;">
                              <strong>
                                <?php /* {{ optional($orderDetail->product_data->stocks->where('warehouse_id', 1)->first())->qty }} */ ?>
                                {{ $kolStocks }}
                              </strong>
                            </div>
                          </div>                          
                        </td>
                        <td style="display: table-cell;">
                          <input type="number" class="form-control allocate-qty-input" name="Delhi_allocate_qty_{{ $orderDetail->order_details_id }}" value="{{ $delhiQty != '0' ? $delhiQty : '' }}" @if($subOrderData->warehouse_id == 2) readonly @endif>
                          <div class="form-group row">
                            <div class="col-md-12" style="text-align: center;">
                              <strong>
                                <?php /* {{ optional($orderDetail->product_data->stocks->where('warehouse_id', 2)->first())->qty }} */ ?>
                                {{ $delStocks }}
                              </strong>
                            </div>
                          </div>
                        </td>
                        <td style="display: table-cell;">
                          <input type="number" class="form-control allocate-qty-input" name="Mumbai_allocate_qty_{{ $orderDetail->order_details_id }}" value="{{ $mumbaiQty != '0' ? $mumbaiQty : '' }}" @if($subOrderData->warehouse_id == 6) readonly @endif>
                          <div class="form-group row">
                            <div class="col-md-12" style="text-align: center;">
                              <strong>
                                <?php /* {{ optional($orderDetail->product_data->stocks->where('warehouse_id', 6)->first())->qty }} */ ?>
                                {{ $mumStocks }}
                              </strong>
                            </div>
                          </div>
                        </td>
                        <td style="display: table-cell;">
                          <div class="col-md-12" style="text-align: center;">
                            <strong id="allocateQtyS_{{ $orderDetail->order_details_id }}" style="{{ $color }}">{{ $totalAllocated }}</strong>
                            <input type="hidden" name="regrate_qty_{{ $orderDetail->order_details_id }}" id="regrate_qty_{{ $orderDetail->order_details_id }}" value="0">
                          </div>
                        </td>
                        <td style="display: table-cell;">
                          <input type="number" class="form-control rate-input" name="rate_{{ $orderDetail->order_details_id }}" id="rate_{{ $orderDetail->order_details_id }}" value="{{ ceil($orderDetail->price/$orderDetail->approved_quantity) }}">
                        </td>
                        <td style="display: table-cell; text-align:center;">
                          <p><strong><span id="spanSubTotal_{{ $orderDetail->order_details_id }}">{{ $subTotal }}</span></strong></p>
                          <input type="hidden" name="subTotal_{{ $orderDetail->order_details_id }}" id="subTotal_{{ $orderDetail->order_details_id }}" value="{{ old('subTotal_' . $orderDetail->order_details_id) }}">
                          <!-- <i class="las la-trash delete-row" data-rowid="{{$count}}" style="font-size: 30px; color:#f00; cursor:pointer;"></i> -->
                        </td>
                    </tr>
                  @endif
                  @php
                    $count++;
                  @endphp
                @endforeach                
            </table>
          </div>
        </div>
        <div>
          <!-- <button type="button" class="btn add-table-row btn-primary">+ Add New Row</button> -->
          <?php /* <a href="{{ route('products.quickorder', ['order_id' => encrypt($subOrderData->id)]) }}" class="btn" style="background-color: #024285 !important; color:#fff;">+ Add New Product</a> */ ?>
          <a href="javascript:void(0)" class="btn btn-success" id="confButton" style="float:right; margin-left:10px;">Confirm</a>
          <?php /* <a href="javascript:void(0)" class="btn btn-info" id="saveDraftButton" style="float:right;">Save Draft</a> */ ?>
          <!-- <span style="float: right;margin-right: 10px;padding-top: 13px;font-size: 15px;"><input type="checkbox" name="btr_verification" id="btr_verification" value="1"> BTR Verified</span> -->
          @php
            $checked = (old('btr_verification')) ? 'checked' : '';
            $checked = '';
            $detail = $subOrderDetailsData->first(); // a SubOrderDetail model (or null)
            $earlyPaymentChecked = ((optional($detail->sub_order_record)->early_payment_check ?? 0) == 1) ? 'checked' : '';
          @endphp
          <a href="javascript:void(0)" class="btn" id="configBTR" style="float:right; margin-left:10px; margin-right:10px; background-color: #fe9535 !important; color:#fff;">BTR Config</a>
          <span style="float: right; margin-right: 10px; padding-top: 13px; font-size: 15px; display:none;" id="span_btr_verification"><input type="checkbox" name="btr_verification" id="btr_verification" value="1" {{ $checked }}> BTR Verified</span>

          <span style="float: right; margin-right: 90px; padding-top: 13px; font-size: 15px; font-weight: bold;"><input type="checkbox" name="early_payment_check" id="early_payment_check" value="1" {{ $earlyPaymentChecked }}> Early Payment</span>
          
          <span style="float: right; margin-right: 90px; padding-top: 13px; font-size: 15px; font-weight: bold;"><input type="checkbox" name="conveince_fee_payment_check" id="conveince_fee_payment_check" value="1" @if($subOrderData->conveince_fee_payment_check == 1)checked @endif> Conveince Fees</span>
          
        </div>
        <br/>
      </div>
    </div>
    <div class="modal fade" id="btrConfigModal" tabindex="-1" aria-labelledby="btrConfigModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl"> 
        <div class="modal-content p-3">
          <div class="modal-header">
            <h5 class="modal-title" id="myLargeModalLabel">BTR Config</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <table class="table table-bordered">
              <thead style="background-color: #024285 !important;">
                <tr style="color:#fff;">
                  <th>Name</th>
                  <th>Transporter ID</th>
                  <th>Mobile</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                @foreach($allWareHouse as $keyWarehouse)
                  @php
                    if(isset($btrOrderDetails) AND count($btrOrderDetails) > 0){
                      $btrOrderWarehouseWise = $btrOrderDetails->where('warehouse_id', $keyWarehouse->id);
                      foreach($btrOrderWarehouseWise as $order){
                        $btrOrderWarehouseWise = $order;
                      }
                    }
                    if (!$btrOrderWarehouseWise) {
                        $btrOrderWarehouseWise = $btrOrderDetails
                            ->where('warehouse_id', $keyWarehouse->id)
                            ->where('type', 'sub_order')
                            ->first();
                    }
                  @endphp
                  <tr id="tr_{{ $keyWarehouse->id }}">
                      <td>
                        @if($subOrderData->user->warehouse_id != $keyWarehouse->id)
                          <input type="checkbox" class="warehouse-checkbox" id="btr_warehouse_{{$keyWarehouse->id}}" name="btr_warehouse_{{$keyWarehouse->id}}" value="{{$keyWarehouse->id}}" data-name="{{$keyWarehouse->name}}" @if(isset($btrOrderWarehouseWise->type) AND $btrOrderWarehouseWise->type == 'btr') checked @endif>
                        @endif
                        <span class="warehouse-name" id="warehouse_name_{{ $keyWarehouse->id }}">{{$keyWarehouse->name}}</span>
                        <?php /* @if($subOrderData->user->warehouse_id != $keyWarehouse->id)
                          @if(!isset($btrOrderWarehouseWise) OR (isset($btrOrderWarehouseWise->type) AND $btrOrderWarehouseWise->type != 'btr')) */ ?>
                            <div class="d-flex align-items-center transport-dropdown" id="div_transport_drop_down_{{ $keyWarehouse->id }}">
                                <select class="form-control w-75 transport-select" name="btr_transport_name_{{ $keyWarehouse->id }}" id="btr_transport_name_{{ $keyWarehouse->id }}" data-warehouse="{{ $keyWarehouse->id }}">
                                    <option value="">---- Select Transport Name ----</option>
                                    @foreach($allTransportData as $transport)
                                      <option value="{{ $transport->name }}" data-transport_table_id="{{ $transport->id }}" data-gst="{{ $transport->gstin }}" data-mobile="{{ $transport->mobile_no }}" @if(isset($btrOrderWarehouseWise->transport_name) AND $btrOrderWarehouseWise->transport_name == $transport->name) selected @endif>{{ $transport->name }}</option>
                                    @endforeach
                                </select>
                                <a class="btn btn-sm ms-2 btnAddCarriers" style="height:38px; background-color: #024285 !important; color: #fff;"><b>+</b></a>
                            </div>
                          <?php /* @endif
                          @endif */ ?>
                      </td>
                      <td id="td_transport_id_{{ $keyWarehouse->id }}">
                      <?php /* @if($subOrderData->user->warehouse_id != $keyWarehouse->id)
                          @if(!isset($btrOrderWarehouseWise) OR (isset($btrOrderWarehouseWise->type) AND $btrOrderWarehouseWise->type != 'btr')) */ ?>
                            <input type="text" id="btr_transport_id_{{ $keyWarehouse->id }}" name="btr_transport_id_{{ $keyWarehouse->id }}" class="form-control transport-id" placeholder="ID" value="@if(isset($btrOrderWarehouseWise->transport_id) AND $btrOrderWarehouseWise->transport_id != NULL) {{ $btrOrderWarehouseWise->transport_id }} @endif">
                            <input type="hidden" id="btr_transport_table_id_{{ $keyWarehouse->id }}" name="btr_transport_table_id_{{ $keyWarehouse->id }}" class="form-control transport_table_id" value="@if(isset($btrOrderWarehouseWise->transport_id) AND $btrOrderWarehouseWise->transport_id != NULL) {{ $btrOrderWarehouseWise->transport_id }} @endif">
                          <?php /* @else
                            <b>Not Applicable</b>
                          @endif
                          @else
                          <b>Home Warehouse</b>
                        @endif */ ?>
                      </td>
                      <td id="td_transport_mobile_{{ $keyWarehouse->id }}">
                      <?php /* @if($subOrderData->user->warehouse_id != $keyWarehouse->id) */ ?>
                          <input type="text" class="form-control transport-mobile" id="btr_transport_mobile_{{ $keyWarehouse->id }}" name="btr_transport_mobile_{{ $keyWarehouse->id }}" placeholder="Mobile Number" value="@if(isset($btrOrderWarehouseWise->transport_phone) AND $btrOrderWarehouseWise->transport_phone != NULL) {{ $btrOrderWarehouseWise->transport_phone }} @endif" @if((isset($btrOrderWarehouseWise->type) AND $btrOrderWarehouseWise->type == 'btr')) readonly @endif>
                          <?php /* @endif */ ?>
                      </td>
                      <td><input type="text" class="form-control" name="btr_transport_remarks_{{ $keyWarehouse->id }}" id="btr_transport_remarks_{{ $keyWarehouse->id }}" placeholder="Remarks" value="@if(isset($btrOrderWarehouseWise->transport_remarks) AND $btrOrderWarehouseWise->transport_remarks != NULL) {{ $btrOrderWarehouseWise->transport_remarks }} @endif"></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </form>
  
  <div class="modal fade" id="addCarriersModal" tabindex="-1" aria-labelledby="addCarriersModal" aria-hidden="true">
    <div class="modal-dialog modal-xl"> 
      <div class="modal-content p-3">
        <div class="modal-header">
          <h5 class="modal-title" id="myLargeModalLabel">Add Carriers</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form class="form form-horizontal mar-top" action="{{ route('order.addCarriers') }}" method="POST" enctype="multipart/form-data" id="add_carrier_form">
            @csrf
            <div class="col-lg-12">
              <div class="card mb-4">
                <div class="card-body row">
                  <div class="col-md-6">
                    <div class="form-group row">
                      <label class="col-md-3 col-form-label">Name:</label>
                      <div class="col-md-9">
                        <input type="text" class="form-control" name="name" placeholder="Name" value="" required>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group row">
                      <label class="col-md-3 col-form-label">Mobile No:</label>
                      <div class="col-md-9">
                        <input type="number" class="form-control" name="mobile_no" id="mobile_no" placeholder="Mobile No" value="" required>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group row">
                      <label class="col-md-3 col-form-label">Phone No:</label>
                      <div class="col-md-9">
                        <input type="number" class="form-control" name="phone_no" id="phone_no" placeholder="Phone No" value="">
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group row">
                      <label class="col-md-3 col-form-label">GST No:</label>
                      <div class="col-md-9">
                        <input type="text" class="form-control" name="gstin" id="gstin" placeholder="GST No" value="" required>
                      </div>
                    </div>
                  </div>
                </div>              
              </div>
            </div>
            <div class="col-lg-12">
              <button type="submit" class="btn btn-primary btnSubmitAddCarrier">Save</button>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
    let rowCount = {{ count($subOrderDetailsData) }}; // Start count from existing rows

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

    $('#configBTR').on('click', function(){
        $('#span_btr_verification').show(); // You can use `.toggle()` if you want to show/hide on every click
    });

    // $("#btr_verification").change(function () {
    //     if ($(this).is(":checked")) {
    //         $("#btrConfigModal").modal("show"); // Open modal
    //     } else {
    //         $("#btrConfigModal").modal("hide"); // Hide modal if unchecked
    //     }
    // });

    $("#configBTR").on('click',function () {
      $("#btrConfigModal").modal("show"); // Open modal
    });

    $('.btnAddCarriers').on('click', function() {
      $("#addCarriersModal").modal("show"); // Open modal
    });

    // When modal is closed, uncheck the checkbox
    // $("#btrConfigModal").on("hidden.bs.modal", function () {
    //     $("#btr_verification").prop("checked", false);
    // });

    $(".warehouse-checkbox").change(function(){
        let warehouseId = $(this).val();
        let isChecked = $(this).prop("checked");

        if (isChecked) {
            // Change warehouse name and transport ID to 'Not Applicable'
            // $("#warehouse_name_" + warehouseId).text("Not Applicable");
            $("#td_transport_id_" + warehouseId).html('<b>Not Applicable</b>');

            // Remove transport dropdown div
            $("#div_transport_drop_down_" + warehouseId).remove();

            // Clear and disable mobile input
            $("#td_transport_mobile_" + warehouseId).find("input").val("").prop("disabled", true);
        } else {
            let originalName = $(this).data("name");
            // $("#warehouse_name_" + warehouseId).text(originalName);
            $("#td_transport_id_" + warehouseId).html('<input type="text" id="btr_transport_id_' + warehouseId + '" name="btr_transport_id_' + warehouseId + '" class="form-control transport-id" placeholder="ID">');
            $("#td_transport_mobile_" + warehouseId).find("input").prop("disabled", false);

            // Re-add transport dropdown dynamically
            let transportDropdownHtml = `
                <div class="d-flex align-items-center transport-dropdown" id="div_transport_drop_down_${warehouseId}">
                    <select class="form-control w-75 transport-select" name="btr_transport_name_${warehouseId}" id="btr_transport_name_${warehouseId}" data-warehouse="${warehouseId}">
                        <option value="">---- Select Transport Name ----</option>
                        @foreach($allTransportData as $transport)
                          <option value="{{ $transport->id }}" data-gst="{{ $transport->gstin }}" data-mobile="{{ $transport->mobile_no }}">{{ $transport->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm ms-2" style="height:38px; background-color: #024285 !important; color: #fff;"><b>+</b></button>
                </div>
            `;
            $("#warehouse_name_" + warehouseId).after(transportDropdownHtml);
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

        let kolkataGivenQty = row.find("[name='Kolkata_allocate_qty_" + orderDetailId + "']");
        let delhiGivenQty = row.find("[name='Delhi_allocate_qty_" + orderDetailId + "']");
        let mumbaiGivenQty = row.find("[name='Mumbai_allocate_qty_" + orderDetailId + "']");

        // Calculate total allocated quantity
        let allocatedQty = kolkataStock + delhiStock + mumbaiStock;
        let orderQty = parseFloat(row.find("[name='order_qty_" + orderDetailId + "']").val()) || 0;

        // Update the allocated quantity display
        let allocateQtyS = row.find("#allocateQtyS_" + orderDetailId);
        

        let regrateQty = row.find("#regrate_qty_" + orderDetailId);
        // Change color based on condition
        if (allocatedQty < orderQty) {
            allocateQtyS.css("color", "red");
            regrateQty.val(orderQty - allocatedQty); 
        } else if (allocatedQty == orderQty) {
            allocateQtyS.css("color", "green");
            regrateQty.val('0');
        } else {
            alert("You are tring to allocate more than the given order quantity.");
            // let conf = confirm("You are tring to allocate more than the given order quantity. Do you want to continoue?");
            // if (!conf) {
            //     // Reset the input field that triggered the event
            //     $(this).val(""); // Clears the current input field
            //     // Fetch input values for stocks
            //     let kolkataStock = parseFloat(row.find("[name='Kolkata_allocate_qty_" + orderDetailId + "']").val()) || 0;
            //     let delhiStock = parseFloat(row.find("[name='Delhi_allocate_qty_" + orderDetailId + "']").val()) || 0;
            //     let mumbaiStock = parseFloat(row.find("[name='Mumbai_allocate_qty_" + orderDetailId + "']").val()) || 0;
            //     allocatedQty = kolkataStock + delhiStock + mumbaiStock; // Recalculate
                
            //     allocateQtyS.text(allocatedQty).css("color", "red"); // Update again
            // } else {
            //     allocateQtyS.css("color", "darkblue");
            // }
            regrateQty.val('0'); 
            $(this).val('0');
        }
        
        if (allocatedQty <= orderQty) {
          allocateQtyS.text(allocatedQty);
          // Update the total price
          let spanSubTotal = row.find("#spanSubTotal_" + orderDetailId);
          let rate = parseFloat(row.find("#rate_" + orderDetailId).val()) || 0;
          spanSubTotal.text(allocatedQty*rate);

          let subTotal = row.find("#subTotal_" + orderDetailId);
          subTotal.text(allocatedQty*rate);
        }

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

    $('#confButton').on('click', function() {
      $('#order_status').val('completed');
      $('#split_order_form').submit();
    });

    $('#saveDraftButton').on('click', function() {
      $('#order_status').val('draft');
      $('#split_order_form').submit();
    });

    $('#btnSubmitAddCarrier').on('click', function() {
      $('#add_carrier_form').submit();
    });

    // Add Transport
    $('#add_carrier_form').on('submit', function(event) {
      event.preventDefault(); // Prevent the default form submission

      // Get the form data
      var formData = new FormData(this);

      // Make the AJAX request
      $.ajax({
        url: '{{ route('order.addCarriers') }}', // URL for the form submission route
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
          // Check if the response is successful
          if (response.success) {
            // Close the Add Carriers Modal
            $('#addCarriersModal').modal('hide');

            // Refresh the transport dropdown in the first modal
            refreshTransportDropdown(response.new_transport.name);
          } else {
            // Display any error message if needed
            alert('Error: ' + response.message);
          }
        },
        error: function() {
          alert('An error occurred. Please try again.');
        }
      });
    });

    // Function to refresh the transport dropdown in the first modal
    function refreshTransportDropdown(selectedValue) {
      $.ajax({
        url: '{{ route('getAllTransportData') }}', // A route to fetch all transporters
        type: 'GET',
        success: function(response) {
          if (response.success) {
            // Loop through each warehouse and update the transport dropdown
            @foreach($allWareHouse as $keyWarehouse)
              var transportDropdown = $('#btr_transport_name_{{ $keyWarehouse->id }}');
              transportDropdown.empty(); // Clear existing options
              transportDropdown.append('<option value="">---- Select Transport Name ----</option>');
              // Loop through the transport data and add new options
              response.data.forEach(function(transport) {
                // Check if the transport is selected
                var selected = (transport.name === selectedValue) ? 'selected' : '';
                transportDropdown.append(
                  '<option value="' + transport.name + '" data-transport_table_id="' + transport.id + '" data-gst="' + transport.gstin + '" data-mobile="' + transport.mobile_no + '" ' + selected + '>' + transport.name + '</option>'
                );
              });
            @endforeach
          } else {
            alert('Failed to fetch transport data.');
          }
        },
        error: function() {
          alert('Error fetching transport data.');
        }
      });
    }

  });
</script>

@endsection
