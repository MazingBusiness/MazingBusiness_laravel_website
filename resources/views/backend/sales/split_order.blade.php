@extends('backend.layouts.app')

@section('content')
  <!-- Full-page Loader -->
  <!-- <div id="loader">
      <div class="loader-overlay"></div>
      <div class="loader-content">
          <img src="/public/assets/img/ajax-loader.gif" alt="Loading...">
      </div>
  </div> -->

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
      z-index: 999999 !important;
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

  <div class="aiz-titlebar text-left mt-2 mb-3 d-flex justify-content-between align-items-center">
      <h5 class="mb-0 h6">{{ translate('Split Order') }}</h5>

      {{-- WhatsApp Button (AJAX) --}}
      <button type="button"
              id="btnSendDueOverdueWhatsapp"
              class="btn btn-success btn-sm">
          <i class="las la-whatsapp"></i>  Due/Overdue WhatsApp
      </button>
  </div>

    {{-- Display success message, if any --}}
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- Display error messages, if any --}}
    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

  <form class="form form-horizontal mar-top" action="{{ route('order.saveSplitOrder') }}" method="POST" enctype="multipart/form-data" id="split_order_form">
    @csrf
    <input type="hidden" class="form-control" name="user_id" value="{{ $userDetails->id }}">
    <input type="hidden" class="form-control" name="order_id" value="{{ $orderData->id }}">
    <input type="hidden" class="form-control" name="combined_order_id" value="{{ $orderData->combined_order_id }}">
    <input type="hidden" class="form-control" name="warehouse_id" value="{{ $userDetails->user_warehouse->id }}">
    <input type="hidden" class="form-control" name="redirect_url" value="{{ $redirect }}">
    <div class="row gutters-5">
      <div class="col-lg-12">
        <div class="card mb-4">
          <div class="card-header text-white" style=" @if($address->overdue_amount > 0) background-color:rgb(250, 72, 72) !important; @else background-color: #024285 !important; @endif">
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
                <div class="col-md-4" style="font-size: 20px;">
                  <label class="col-form-label"><strong>Order Payment Status : </strong></label> {!! ($paymentHistory == 0) ? '<span class="badge badge-inline badge-danger" style="height:26px;">Unpaid</span>' : '<span class="badge badge-inline badge-success" style="height:26px;">Paid</span>' !!}
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
                  <input type="text" class="form-control" name="party_name" value="{{ $userDetails->name }}" readonly>
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
                              @if($orderData->address_id == $address->id) 
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
                      <div class="d-flex align-items-center">
                          <select name="bill_to" id="bill_to" class="form-control w-85 select2 billToSelect">
                              @php
                                  $subOrderShippingId = optional($orderData->sub_order->first())->billing_address_id;
                                  $selectedAddress = $subOrderShippingId ?? old('ship_to', $orderData->address_id);
                                  $billAddress = '';
                              @endphp
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
                          <a class="btn btn-sm ms-2" onclick="add_new_address('bill_to')" style="height:38px; background-color: #024285 !important; color: #fff;"><b>+</b></a>
                      </div>
                      {{-- Address line on the next row --}}
                      <div class="mt-2">
                          <p id="pBillToAddress"><strong>Address: </strong>{{ $billAddress }}</p>
                      </div>
                  </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group row">
                <label class="col-md-3 col-form-label">Date:</label>
                <div class="col-md-9">
                  <input type="text" class="form-control" name="order_date" placeholder="{{ translate('Offer Date') }}" value="{{ date('d-m-Y', strtotime($orderData->created_at)) }}" readonly>
                </div>
              </div>
              <div class="form-group row" style="display:none;">
                <label class="col-md-3 col-form-label">Status:</label>
                <div class="col-md-9">
                  <select name="order_status" id="order_status" class="form-control select2">
                      <option value="draft">Draft</option>
                      <option value="completed">Completed</option>
                      <option value="add_new_product">Add New Product</option>
                  </select>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-3 col-form-label">Order No:</label>
                <div class="col-md-9">
                  <input type="text" class="form-control" name="code" placeholder="{{ translate('Offer No.') }}" value="{{ $orderData->code }}" readonly>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-3 col-form-label">Ship To:</label>
                <div class="col-md-9">
                  <div class="d-flex align-items-center">
                    <select name="ship_to" id="ship_to" class="form-control select2 shipToSelect">
                        @php
                            $subOrderShippingId = optional($orderData->sub_order->first())->shipping_address_id;
                            $selectedAddress = $subOrderShippingId ?? old('ship_to', $orderData->address_id);
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
                    <a class="btn btn-sm ms-2" onclick="add_new_address('ship_to')" style="height:38px; background-color: #024285 !important; color: #fff;"><b>+</b></a>
                  </div>
                  {{-- Address line on the next row --}}
                  <div class="mt-2">
                    <p id="pShipToAddress"><strong>Address : </strong>{{ $shipAddress }}</p>
                  </div>
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
            <table class="table mb-0 footable footable-1 breakpoint-xl" style="">
              <thead>
                  <tr class="footable-header">
                    <th style="display: table-cell;">#</th>
                    <th style="display: table-cell;">Warranty</th>
                    <th style="display: table-cell;">Item Details</th>
                    <th style="display: table-cell;">Quantity</th>
                    <th style="display: table-cell;">Kolkata</th>
                    <th style="display: table-cell;">Delhi</th>
                    <th style="display: table-cell;">Mumbai</th>
                    <th style="display: table-cell;">Allocate Qty</th>
                    <th style="display: table-cell;">Rate</th>
                    <th style="display: table-cell;">Conv. Fee (%)</th>   <!-- ADD THIS -->
                    <th style="display: table-cell;">Amount</th>
                  </tr>
              </thead>
              <tbody>
                @php
                  $count=1;
                  $order_details_id = array();
                @endphp
                @foreach($orderDetails as $orderDetail)
                  @php
                      $order_details_id[]=$orderDetail->id;
                      $stocks = $orderDetail->product->stocks->map(function ($stock) {
                          return [
                              'warehouse_id' => $stock->warehouse_id,
                              'qty' => $stock->qty,
                          ];
                      });

                      // Retrieve old allocated values or set to 0 if not present
                      $kolkataAllocated = old('Kolkata_allocate_qty_' . $orderDetail->id, 0);
                      $delhiAllocated = old('Delhi_allocate_qty_' . $orderDetail->id, 0);
                      $mumbaiAllocated = old('Mumbai_allocate_qty_' . $orderDetail->id, 0);
                      $rate = old('rate_' . $orderDetail->id, 0);

                      // Calculate total allocated quantity
                      $totalAllocated = $kolkataAllocated + $delhiAllocated + $mumbaiAllocated;

                      // Calculate subtotal
                      $subTotal = $totalAllocated * $rate;                      

                      // Retrieve order quantity
                      $orderQty = $orderDetail->quantity;                      

                      // Retrieve submited value
                      $btrQty = 0;
                      $splitOrder = App\Models\SubOrder::where('order_id',$orderData->id)->where('warehouse_id','1')->first();
                      $subOrderProductQty=NULL;
                      if(isset($orderData->sub_order) AND $orderData->sub_order != NULL){
                        $splitOrder = App\Models\SubOrder::where('order_id',$orderData->id)->where('warehouse_id','1')->first();
                        $splitOrderDetails = App\Models\SubOrderDetail::where('order_id',$orderData->id)->where('product_id',$orderDetail->product->id)->where('warehouse_id','1')->first();
                        if($splitOrderDetails != NULL){
                          $subOrderProductQty = $splitOrderDetails->approved_quantity;
                          if(isset($splitOrder->type) AND $splitOrder->type == 'btr'){
                            $btrQty += $subOrderProductQty;
                          }
                        }
                        
                      } 
                      $kolQty = $subOrderProductQty ?? old('Kolkata_allocate_qty_' . $orderDetail->id);

                      $subOrderProductQty=NULL;
                      if(isset($orderData->sub_order) AND $orderData->sub_order != NULL){
                        $splitOrder = App\Models\SubOrder::where('order_id',$orderData->id)->where('warehouse_id','2')->first();
                        $splitOrderDetails = App\Models\SubOrderDetail::where('order_id',$orderData->id)->where('product_id',$orderDetail->product->id)->where('warehouse_id','2')->first();
                        if($splitOrderDetails != NULL){
                          $subOrderProductQty = $splitOrderDetails->approved_quantity;
                          if(isset($splitOrder->type) AND $splitOrder->type == 'btr'){
                            $btrQty += $subOrderProductQty;
                          }
                        }                              
                      } 
                      $delhiQty = $subOrderProductQty ?? old('Delhi_allocate_qty_' . $orderDetail->id);

                      $subOrderProductQty=NULL;
                      if(isset($orderData->sub_order) AND $orderData->sub_order != NULL){
                        $splitOrder = App\Models\SubOrder::where('order_id',$orderData->id)->where('warehouse_id','6')->first();
                        $splitOrderDetails = App\Models\SubOrderDetail::where('order_id',$orderData->id)->where('product_id',$orderDetail->product->id)->where('warehouse_id','6')->first();
                        if($splitOrderDetails != NULL){
                          $subOrderProductQty = $splitOrderDetails->approved_quantity;
                          if(isset($splitOrder->type) AND $splitOrder->type == 'btr'){
                            $btrQty += $subOrderProductQty;
                          }
                        }
                        
                      } 
                      $mumbaiQty = $subOrderProductQty ?? old('Mumbai_allocate_qty_' . $orderDetail->id);

                      if($orderData->user->warehouse_id == 1){
                        $kolQty = $kolQty - $btrQty;
                      }elseif($orderData->user->warehouse_id == 2){
                        $delhiQty = $delhiQty - $btrQty;
                      }elseif($orderData->user->warehouse_id == 6){
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
                      $subTotal = $totalAllocated * ceil($orderDetail->price/$orderDetail->quantity);
                      
                       // detect 41 manager (uses the same logic you already have)
                    $is41Manager = ($is41Manager ?? false)
                        ?: (strtolower(trim((string) optional(Auth::user())->user_title)) === 'manager_41'
                            || strtolower(trim((string) optional(Auth::user())->user_type)) === 'manager_41');

                    $stockTable = $is41Manager ? 'manager_41_product_stocks' : 'products_api';

                    $kolkataStocks = DB::table($stockTable)
                        ->where('part_no', $orderDetail->product->part_no)
                        ->where('godown', 'Kolkata')
                        ->first();

                    $delhiStocks = DB::table($stockTable)
                        ->where('part_no', $orderDetail->product->part_no)
                        ->where('godown', 'Delhi')
                        ->first();

                    $mumbaiStocks = DB::table($stockTable)
                        ->where('part_no', $orderDetail->product->part_no)
                        ->where('godown', 'Mumbai')
                        ->first();

                    $kolStocks = $kolkataStocks ? (int) $kolkataStocks->closing_stock : 0;
                    $delStocks = $delhiStocks ? (int) $delhiStocks->closing_stock : 0;
                    $mumStocks = $mumbaiStocks ? (int) $mumbaiStocks->closing_stock : 0;
                  @endphp
                  <tr id="row_{{$count}}">
                      <td style="display: table-cell;">{{ $count }} . </td>  

                      <td style="display: table-cell; text-align:center;">
                        <!-- Hidden ensures 0 is posted when unchecked -->
                        <!-- <input type="hidden" name="warranty_{{ $orderDetail->id }}" value="0"> -->
                        {{-- Warranty checkbox --}}
                        <input type="checkbox"
                              name="warranty_{{ $orderDetail->id }}"
                              value="1"
                              {{ old('warranty_'.$orderDetail->id, $orderDetail->product->is_warranty) == 1 ? 'checked' : '' }}>

                        {{-- If product already has warranty, show fixed duration text --}}
                        @if($orderDetail->product->is_warranty == 1)
                            <p id="warranty_duration_{{ $orderDetail->id }}"
                              style="{{ old('warranty_'.$orderDetail->id, $orderDetail->product->is_warranty) == 1 ? '' : 'display:none;' }}">
                                {{ $orderDetail->product->warranty_duration }} months
                            </p>

                        {{-- If product has no warranty, show an input box when checkbox is checked --}}
                        @else
                            <input type="number" min="0" step="1" class="form-control mt-1" id="warranty_duration_input_{{ $orderDetail->id }}" name="warranty_duration_{{ $orderDetail->id }}" placeholder="Warranty Duration (months)" value="{{ old('warranty_duration_'.$orderDetail->id) }}" style="{{ old('warranty_'.$orderDetail->id) == 1 ? '' : 'display:none;' }}">
                        @endif
                      </td>
                      <td style="display: table-cell;">
                      <p><strong>{{ $orderDetail->product->name }}</strong></p>
                      {!! ($orderDetail->cash_and_carry_item == 1) ? '<p><span class="badge badge-inline badge-danger">No Credit Item</span></p>' : '' !!}
                      <input type="hidden" class="form-control" name="product_id_{{ $orderDetail->id }}" value="{{ $orderDetail->product->id }}">
                      <input type="hidden" name="order_details_id[]" id="order_details_id_{{$count}}" value="{{ $orderDetail->id }}" >
                      <div><strong>Part Number : <span style="color: #024285;" >{{ $orderDetail->product->part_no }}</span></strong></div>
                        <div><strong>HSN : </strong><span style="{{ strlen($orderDetail->product->hsncode) < 8 ? 'color:#f00;' : '' }}" >{{ $orderDetail->product->hsncode.' - '.$orderDetail->product->tax.'%' }}</span></div>
                        <div><strong>Seller Name : </strong>{{ $orderDetail->product->sellerDetails->user->name }}</div>
                        <div><strong>Seller Location : </strong>{{ $orderDetail->product->sellerDetails->user->user_warehouse->name }}</div>
                        <input type="text" class="form-control" name="remark_{{ $orderDetail->id }}" value="{{ old('remark_' . $orderDetail->id) }}" placeholder="Remark">
                      </td>
                      <td style="display: table-cell;">
                        <input type="text" class="form-control" name="order_qty_{{ $orderDetail->id }}" id="order_qty_{{ $orderDetail->id }}" value="{{ $orderDetail->quantity }}" readonly>
                        <div class="col-md-12" style="text-align: center;">
                          <strong>{{ $kolStocks + $delStocks + $mumStocks }}</strong>
                        </div>
                      </td>
                      <td style="display: table-cell;">
                      <input type="number" class="form-control allocate-qty-input"
                            name="Kolkata_allocate_qty_{{ $orderDetail->id }}"
                            id="Kolkata_allocate_qty_{{ $orderDetail->id }}"
                            value="{{ old('Kolkata_allocate_qty_' . $orderDetail->id, $kolQty != '0' ? $kolQty : '') }}"> 
                        
                        <div class="form-group row">
                          <div class="col-md-12" style="text-align: center;">
                            <strong>
                              <?php /* {{ optional($orderDetail->product->stocks->where('warehouse_id', 1)->first())->qty }} */ ?>
                              {{ $kolStocks }}
                            </strong>
                          </div>
                        </div>                          
                      </td>
                      <td style="display: table-cell;">
                      <input type="number" class="form-control allocate-qty-input"
                        name="Delhi_allocate_qty_{{ $orderDetail->id }}"
                        id="Delhi_allocate_qty_{{ $orderDetail->id }}"
                        value="{{ old('Delhi_allocate_qty_' . $orderDetail->id, $delhiQty != '0' ? $delhiQty : '') }}">
                        <div class="form-group row">
                          <div class="col-md-12" style="text-align: center;">
                            <strong>
                              <?php /* {{ optional($orderDetail->product->stocks->where('warehouse_id', 2)->first())->qty }} */ ?>
                              {{ $delStocks }}
                            </strong>
                          </div>
                        </div>
                      </td>
                      <td style="display: table-cell;">
                      <input type="number" class="form-control allocate-qty-input"
                        name="Mumbai_allocate_qty_{{ $orderDetail->id }}"
                        id="Mumbai_allocate_qty_{{ $orderDetail->id }}"
                        value="{{ old('Mumbai_allocate_qty_' . $orderDetail->id, $mumbaiQty != '0' ? $mumbaiQty : '') }}">
                        <div class="form-group row">
                          <div class="col-md-12" style="text-align: center;">
                            <strong>
                              <?php /* {{ optional($orderDetail->product->stocks->where('warehouse_id', 6)->first())->qty }} */ ?>
                              {{ $mumStocks }}

                            </strong>
                          </div>
                        </div>
                      </td>
                      <td style="display: table-cell;">
                        <div class="col-md-12" style="text-align: center;">
                          <strong id="allocateQtyS_{{ $orderDetail->id }}" style="{{ $color }}">{{ $totalAllocated }}</strong>
                          <input type="hidden" name="regrate_qty_{{ $orderDetail->id }}" id="regrate_qty_{{ $orderDetail->id }}" value="0">
                        </div>
                      </td>
                      <td style="display: table-cell;">
                        <input type="number" class="form-control rate-input" name="rate_{{ $orderDetail->id }}" id="rate_{{ $orderDetail->id }}" value="{{ ($orderDetail->price/$orderDetail->quantity) }}">
                      </td>
                      <td style="display: table-cell;">
                          <input type="number"
                                 step="0.01"
                                 min="0"
                                 class="form-control conv-fee-input"
                                 name="conveince_fee_percentage_{{ $orderDetail->id }}"
                                 id="conveince_fee_percentage_{{ $orderDetail->id }}"
                                 value="{{ old('conveince_fee_percentage_'.$orderDetail->id, $orderDetail->conveince_fee_percentage) }}"
                                 placeholder="%">
                      </td>
                      <td style="display: table-cell; text-align:center;">
                        <p><strong><span id="spanSubTotal_{{ $orderDetail->id }}">{{ $subTotal }}</span></strong></p>
                        <input type="hidden" name="subTotal_{{ $orderDetail->id }}" id="subTotal_{{ $orderDetail->id }}" value="{{ old('subTotal_' . $orderDetail->id) }}">
                        <i class="las la-trash delete-row" data-rowid="{{$count}}" style="font-size: 30px; color:#f00; cursor:pointer;"></i>
                      </td>
                  </tr>
                  @php
                    $count++;
                  @endphp
                @endforeach
                <input type="hidden" name="order_details_id" id="order_details_id" value="{{ implode(',', $order_details_id) }}" />
            </table>
          </div>
        </div>
        <div>
          <!-- <button type="button" class="btn add-table-row btn-primary">+ Add New Row</button> -->
          <?php /* <a href="{{ route('products.quickorder', ['order_id' => encrypt($orderData->id)]) }}" class="btn" style="background-color: #024285 !important; color:#fff;" id="addNewProduct">+ Add New Product</a> */ ?>
          <a href="javascript:void(0)" class="btn" style="background-color: #024285 !important; color:#fff;" id="addNewProduct">+ Add New Product</a>            
          <a href="javascript:void(0)" class="btn btn-success" id="confButton" style="float:right; margin-left:10px;">Confirm</a>

          <a href="javascript:void(0)" class="btn btn-info" id="saveDraftButton" style="float:right;">Save Draft</a>
          <!-- <span style="float: right;margin-right: 10px;padding-top: 13px;font-size: 15px;"><input type="checkbox" name="btr_verification" id="btr_verification" value="1"> BTR Verified</span> -->
          @php
            $checked = '';
            $checked = (count($btrOrderDetails) > 0 || old('btr_verification')) ? 'checked' : '';            
          @endphp
          <a href="javascript:void(0)" class="btn" id="configBTR" style="float:right; margin-left:10px; margin-right:10px; background-color: #fe9535 !important; color:#fff;">BTR Config</a>
          <span style="float: right; margin-right: 10px; padding-top: 13px; font-size: 15px; @if($checked == '') display:none; @endif" id="span_btr_verification"><input type="checkbox" name="btr_verification" id="btr_verification" value="1" {{ $checked }}> BTR Verified</span>
          <span style="float: right; margin-right: 90px; padding-top: 13px; font-size: 15px; font-weight: bold;"><input type="checkbox" name="early_payment_check" id="early_payment_check" value="1" checked> Early Payment</span>
          <span style="float: right; margin-right: 90px; padding-top: 13px; font-size: 15px; font-weight: bold;"><input type="checkbox" name="conveince_fee_payment_check" id="conveince_fee_payment_check" value="1" checked> Conveince Fees</span>

           <label style="font-size: 10px;" for="global_conveince_fee_percentage" class="mb-0 font-weight-bold">
            Conveince Fee
          </label>
          <input type="number"
             step="0.01"
             min="0"
             id="global_conveince_fee_percentage"
             name="global_conveince_fee_percentage"
             class="form-control d-inline-block ml-2"
             style="width: 130px; vertical-align: middle;"
             placeholder="% (global)"
             value="{{ old('global_conveince_fee_percentage', $orderData->conveince_fee_percentage) }}">
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
            <span style="color:#f00;" id="btrErrorMsg"></span>
            <table class="table table-bordered">
              <thead style="background-color: #024285 !important;">
                <tr style="color:#000F;">
                  <th>Name</th>
                  <th>Transporter ID</th>
                  <th>Mobile Number</th>
                  <th>Remarks</th>
                  <th>Logistic Rewards</th>
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
                    $warehouseRewards = $getRewardsOfUser->where('warehouse_id', $keyWarehouse->id);
                    if($warehouseRewards->isNotEmpty()){
                      foreach($warehouseRewards as $rewardKey){
                        $warehouseRewards = $rewardKey->rewards_percentage;
                      }
                    }else{
                      $warehouseRewards = 0;
                    }
                  @endphp
                  <tr id="tr_{{ $keyWarehouse->id }}">
                      <td>
                        @if($orderData->user->warehouse_id != $keyWarehouse->id)
                          <input type="checkbox" class="warehouse-checkbox" id="btr_warehouse_{{$keyWarehouse->id}}" name="btr_warehouse_{{$keyWarehouse->id}}" value="{{$keyWarehouse->id}}" data-name="{{$keyWarehouse->name}}" @if(!empty($lastBtrWarehouseIds) && in_array($keyWarehouse->id, $lastBtrWarehouseIds)) checked @elseif(isset($btrOrderWarehouseWise->type) AND $btrOrderWarehouseWise->type == 'btr') checked @endif>
                        @endif
                        <span class="warehouse-name" id="warehouse_name_{{ $keyWarehouse->id }}">{{$keyWarehouse->name}}</span>
                        @if(!empty($lastBtrWarehouseIds) && in_array($keyWarehouse->id, $lastBtrWarehouseIds))
                        @else
                          <?php /* @if($orderData->user->warehouse_id != $keyWarehouse->id)
                            @if(!isset($btrOrderWarehouseWise) OR (isset($btrOrderWarehouseWise->type) AND $btrOrderWarehouseWise->type != 'btr')) */ ?>
                              <div class="d-flex align-items-center transport-dropdown" id="div_transport_drop_down_{{ $keyWarehouse->id }}">
                                  <select class="form-control w-75 transport-select" name="btr_transport_name_{{ $keyWarehouse->id }}" id="btr_transport_name_{{ $keyWarehouse->id }}" data-warehouse="{{ $keyWarehouse->id }}">
                                      <option value="">---- Select Transport Name ----</option>
                                      @foreach($allTransportData as $transport)
                                        <option value="{{ $transport->name }}" data-transport_table_id="{{ $transport->id }}" data-gst="{{ $transport->gstin }}" data-mobile="{{ $transport->mobile_no }}" @if(old("btr_transport_name_{$keyWarehouse->id}") == $transport->name) selected @elseif(isset($btrOrderWarehouseWise->transport_name) AND $btrOrderWarehouseWise->transport_name == $transport->name) selected @endif>{{ $transport->name }}</option>
                                      @endforeach
                                  </select>
                                  <a class="btn btn-sm ms-2 btnAddCarriers" style="height:38px; background-color: #024285 !important; color: #fff;"><b>+</b></a>
                              </div>
                          <?php /*  @endif
                          @endif */ ?>
                        @endif
                      </td>
                      <td id="td_transport_id_{{ $keyWarehouse->id }}">
                        @if(!empty($lastBtrWarehouseIds) && in_array($keyWarehouse->id, $lastBtrWarehouseIds))
                          <b>Not Applicable</b>
                        @else
                        <?php /* @if($orderData->user->warehouse_id != $keyWarehouse->id)
                          @if(!isset($btrOrderWarehouseWise) OR (isset($btrOrderWarehouseWise->type) AND $btrOrderWarehouseWise->type != 'btr')) */ ?>
                            <input type="text" id="btr_transport_id_{{ $keyWarehouse->id }}" name="btr_transport_id_{{ $keyWarehouse->id }}" class="form-control transport-id" placeholder="ID" value="@if(isset($btrOrderWarehouseWise->transport_id) AND $btrOrderWarehouseWise->transport_id != NULL) {{ $btrOrderWarehouseWise->transport_id }} @endif">
                            <input type="hidden" id="btr_transport_table_id_{{ $keyWarehouse->id }}" name="btr_transport_table_id_{{ $keyWarehouse->id }}" class="form-control transport_table_id" value="@if(isset($btrOrderWarehouseWise->transport_id) AND $btrOrderWarehouseWise->transport_id != NULL) {{ $btrOrderWarehouseWise->transport_id }} @endif">
                          <?php /* @else
                            <b>Not Applicable</b>
                          @endif
                        @else
                          <b>Home Warehouse</b>
                        @endif */ ?>
                        @endif
                      </td>
                      <td id="td_transport_mobile_{{ $keyWarehouse->id }}">
                        <?php /* @if($orderData->user->warehouse_id != $keyWarehouse->id) */ ?>
                          <input type="text" class="form-control transport-mobile" id="btr_transport_mobile_{{ $keyWarehouse->id }}" name="btr_transport_mobile_{{ $keyWarehouse->id }}" placeholder="Mobile Number" value="@if(isset($btrOrderWarehouseWise->transport_phone) AND $btrOrderWarehouseWise->transport_phone != NULL AND (empty($lastBtrWarehouseIds) OR !in_array($keyWarehouse->id, $lastBtrWarehouseIds))) {{ $btrOrderWarehouseWise->transport_phone }} @endif" @if(!empty($lastBtrWarehouseIds) && in_array($keyWarehouse->id, $lastBtrWarehouseIds)) readonly @endif>
                        <?php /* @endif */ ?>
                      </td>
                      <td><input type="text" class="form-control" name="btr_transport_remarks_{{ $keyWarehouse->id }}" id="btr_transport_remarks_{{ $keyWarehouse->id }}" placeholder="Remarks" value="@if(isset($btrOrderWarehouseWise->transport_remarks) AND $btrOrderWarehouseWise->transport_remarks != NULL) {{ $btrOrderWarehouseWise->transport_remarks }} @endif"></td>

                      <td><input type="text" class="form-control" name="rewards_{{ $keyWarehouse->id }}" id="rewards_{{ $keyWarehouse->id }}" placeholder="Logistic Rewards" value="{{ $warehouseRewards }}"></td>
                      <input type="hidden" name="selected_transport_{{ $keyWarehouse->id }}" id="selected_transport_{{ $keyWarehouse->id }}" value="{{ isset($btrOrderWarehouseWise->transport_name) ? $btrOrderWarehouseWise->transport_name : '' }}">
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="saveBtrConfig">Save</button>
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

  <div class="modal fade" id="new-address-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">{{ translate('Add New Address') }}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <!-- <form class="form-default" role="form" action="{{ route('order.changeOrderAddress') }}" method="POST"> -->
        <form id="new-address-form" class="form-default" role="form">
          @csrf
          <input type="hidden" name="party_code" id="party_code" value="{{ $userDetails->party_code }}" />
          <input type="hidden" name="user_id" id="user_id" value="{{ $userDetails->id }}" />
          <input type="hidden" name="order_id" id="order_id" value="{{ $orderData->id }}" />
          <input type="hidden" name="bill_to_or_ship_to" id="bill_to_or_ship_to" value="" />
          <div class="modal-body">
            <div class="p-3">
              <div class="row">
                <div class="col-md-3">
                  <label>{{ translate('Company Name') }}</label>
                </div>
                <div class="col-md-9">
                  <input type="text" class="form-control mb-3" placeholder="{{ translate('Company Name') }}"
                    name="company_name" id="company_name" value="" required>
                </div>
              </div>

              <div class="row">
                <div class="col-md-3">
                  <label>{{ translate('GSTIN') }}</label>
                </div>
                <div class="col-md-9">
                  <input type="text" class="form-control mb-3" placeholder="{{ translate('GSTIN') }}" name="gstin"
                    value="">
                  <span id="gstin_success" class="text-success"></span>
                  <span id="gstin_err" class="text-danger"></span>
                </div>
              </div>

              <div class="row">
                <div class="col-md-3">
                  <label>{{ translate('Address') }}</label>
                </div>
                <div class="col-md-9">
                  <textarea class="form-control mb-3" placeholder="{{ translate('Your Address') }}" rows="2" name="address" id="address"
                    required></textarea>
                </div>
              </div>
              <div class="row">
                <div class="col-md-3">
                  <label>{{ translate('Address 2') }}</label>
                </div>
                <div class="col-md-9">
                  <textarea class="form-control mb-3" placeholder="{{ translate('Your Address 2') }}" rows="2" name="address_2" id="address_2"
                    required></textarea>
                </div>
              </div>
              <div class="row" id="divCountry">
                <div class="col-md-3">
                  <label>{{ translate('Country') }}</label>
                </div>
                <div class="col-md-9">
                  <div class="mb-3">
                    <select class="form-control" data-live-search="true" data-placeholder="{{ translate('Select your country') }}" name="country_id" id="country" required>
                      <option value="">{{ translate('Select your country') }}</option>
                      @foreach (\App\Models\Country::where('status', 1)->get() as $key => $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>

              <div class="row" id="divState">
                <div class="col-md-3">
                  <label>{{ translate('State') }}</label>
                </div>
                <div class="col-md-9">
                  <select class="form-control mb-3" data-live-search="true" name="state_id" id="state" required>
                    <option value="">Nothing Selected</option>
                  </select>
                </div>
              </div>

              <div class="row" id="divCity">
                <div class="col-md-3">
                  <label>{{ translate('City') }}</label>
                </div>
                <div class="col-md-9">
                  <select class="form-control mb-3" data-live-search="true" name="city_id" id="city_field_id" required>
                    <option value="">Nothing Selected</option>
                  </select>
                </div>
              </div>
              <div class="row">
                <div class="col-md-3">
                  <label>{{ translate('City Name') }}</label>
                </div>
                <div class="col-md-9">
                  <input type="text" class="form-control mb-3" placeholder="{{ translate('City Name') }}" value="" name="city"  id="city" value="" required>
                </div>
              </div>

              @if (get_setting('google_map') == 1)
                <div class="row">
                  <input id="searchInput" class="controls" type="text"
                    placeholder="{{ translate('Enter a location') }}">
                  <div id="map"></div>
                  <ul id="geoData">
                    <li style="display: none;">Full Address: <span id="location"></span></li>
                    <li style="display: none;">Postal Code: <span id="postal_code"></span></li>
                    <li style="display: none;">Country: <span id="country"></span></li>
                    <li style="display: none;">Latitude: <span id="lat"></span></li>
                    <li style="display: none;">Longitude: <span id="lon"></span></li>
                  </ul>
                </div>

                <div class="row">
                  <div class="col-md-3" id="">
                    <label for="exampleInputuname">Longitude</label>
                  </div>
                  <div class="col-md-9" id="">
                    <input type="text" class="form-control mb-3" id="longitude" name="longitude" readonly="">
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-3" id="">
                    <label for="exampleInputuname">Latitude</label>
                  </div>
                  <div class="col-md-9" id="">
                    <input type="text" class="form-control mb-3" id="latitude" name="latitude" readonly="">
                  </div>
                </div>
              @endif
              <div class="row">
                <div class="col-md-3">
                  <label>{{ translate('Pincode') }}</label>
                </div>
                <div class="col-md-9">
                  <input type="text" class="form-control mb-3" placeholder="{{ translate('Pincode') }}"
                    name="postal_code" id="postal_code" value="" required>
                </div>
              </div>
              <div class="row" id="divPhone" style="display:none;">
                <div class="col-md-3">
                  <label>{{ translate('Phone') }}</label>
                </div>
                <div class="col-md-9">
                  <input type="text" class="form-control mb-3" placeholder="{{ translate('+91') }}" name="phone" id="phone"
                    value="">
                </div>
              </div>
              <div class="form-group text-right">
                <button type="submit" class="btn btn-sm btn-primary" id="saveButton">{{ translate('Save') }}</button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- jQuery & Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>


<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta2/css/bootstrap-select.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta2/js/bootstrap-select.min.js"></script>

<script>
    $(document).ready(function () {


      $('#conveince_fee_payment_check').on('change', function(){
        const on = $(this).is(':checked');
        $('#global_conveince_fee_percentage').prop('disabled', !on);
      }).trigger('change');

      function backfillLineConvFromGlobal(){
        const globalPct = parseFloat($('#global_conveince_fee_percentage').val());
        if (isNaN(globalPct)) return;

        const odCsv = $('#order_details_id').val() || '';
        const ids = odCsv.split(',').map(s => s.trim()).filter(Boolean);
        ids.forEach(function(odId){
          const $inp = $('#conveince_fee_percentage_' + odId);
          if ($inp.length && ($inp.val() === '' || $inp.val() === null)) {
            $inp.val(globalPct);
          }
        });
      }

      $('.aiz-selectpicker').selectpicker();
      // $('.aiz-selectpicker').selectpicker('refresh');

      let rowCount = {{ count($orderDetails) }}; // Start count from existing rows

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

      $('#saveBtrConfig').on('click', function () {
        let isValid = true;
        let errorMessage = "";
        let warehouseName = "Kolkata";

        let kol_order_value_flag = 0;
        let del_order_value_flag = 0;
        let mum_order_value_flag = 0;


        let order_details_id = $('#order_details_id').val();
        // Split the value by comma
        let orderDetailsArray = order_details_id.split(',');
        // Loop through each value and print it
        for (let i = 0; i < orderDetailsArray.length; i++) {
            let kolAccocationValue = $('#Kolkata_allocate_qty_' + orderDetailsArray[i]).val();
            if(kolAccocationValue != "" && kol_order_value_flag == 0){
              kol_order_value_flag = 1
            }
            
            let delAllocationValue = $('#Delhi_allocate_qty_' + orderDetailsArray[i]).val();
            if(delAllocationValue != "" && del_order_value_flag == 0){
              del_order_value_flag = 1
            }
            
            let mumAllicationValue = $('#Mumbai_allocate_qty_' + orderDetailsArray[i]).val();
            if(mumAllicationValue != "" && mum_order_value_flag == 0){
              mum_order_value_flag = 1
            }
        }
        
        // Validate each warehouse row in the modal
        $('.warehouse-checkbox').each(function () {            
            let warehouseId = $(this).val();
            let valueFlag = 0;
            if(warehouseId == 1){
              warehouseName = "Kolkata";
              valueFlag = kol_order_value_flag;
            }else if(warehouseId == 2){
              warehouseName = "Delhi";
              valueFlag = del_order_value_flag;
            }else if(warehouseId == 6){
              warehouseName = "Mumbai";
              valueFlag = mum_order_value_flag;
            }
            let isChecked = $(this).is(':checked');                    
            // If checkbox is checked, ensure transport ID & mobile are filled
            if (isChecked == false && valueFlag == 1) {
                let transportId = $('#btr_transport_id_' + warehouseId).val().trim();
                let transportMobile = $('#btr_transport_mobile_' + warehouseId).val().trim();
                if (transportId == "" || transportId === null) {
                    isValid = false;
                    errorMessage += "Transport ID is required for warehouse " + warehouseName + ".\n";
                    $('#btr_transport_name_' + warehouseId).addClass('is-invalid');
                } else {
                    $('#btr_transport_name_' + warehouseId).removeClass('is-invalid');
                    
                }
              }
          });
          if (!isValid) {
              // alert(errorMessage); // Show errors
              $('#btrErrorMsg').html(errorMessage);
          } else {
              $('#btrConfigModal').modal('hide'); // Close modal if validation passes
              $('#btrErrorMsg').html('');
          }
        });

      // Prevent main form submission if modal data is invalid
      $('#split_order_form').on('submit', function (e) {
          if ($('#btrConfigModal').is(':visible')) {
              e.preventDefault(); // Stop form submission if modal is still open
              alert('Please save BTR Config before submitting the form.');
          }
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
          let transport = $("#selected_transport_" + warehouseId).val();
          if (isChecked === false) {
                $('#btr_transport_mobile_' + warehouseId).prop('readonly', false);
            } else {
                $('#btr_transport_mobile_' + warehouseId).prop('readonly', true);
            } 
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
                            <option value="{{ $transport->name }}" data-transport_table_id="{{ $transport->id }}" data-gst="{{ $transport->gstin }}" data-mobile="{{ $transport->mobile_no }}">{{ $transport->name }}</option>
                          @endforeach
                      </select>
                      <button class="btn btn-sm ms-2" style="height:38px; background-color: #024285 !important; color: #fff;"><b>+</b></button>
                  </div>
              `;
              $("#warehouse_name_" + warehouseId).after(transportDropdownHtml);
              // Now set the selected option from the JS variable
              $("#btr_transport_name_" + warehouseId).val(transport || "");
              let gstNumber = $("#btr_transport_name_" + warehouseId).find("option:selected").data("gst");
              $("#btr_transport_id_" + warehouseId).val(gstNumber || "");
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

      function submitSplitOrder(status) {
          backfillLineConvFromGlobal();
          $('#order_status').val(status);

          const form = document.getElementById('split_order_form');

          // Run HTML5 validation
          if (form.checkValidity()) {
              form.submit();            // OK → actually submit
          } else {
              form.reportValidity();    // Show which fields are invalid
          }
      }

      $('#confButton').on('click', function (e) {
          e.preventDefault();
          submitSplitOrder('completed');
      });

      $('#saveDraftButton').on('click', function (e) {
          e.preventDefault();
          submitSplitOrder('draft');
      });

      $('#addNewProduct').on('click', function (e) {
          e.preventDefault();
          // If you DON'T want validation here, just do this:
          backfillLineConvFromGlobal();
          $('#order_status').val('add_new_product');
          document.getElementById('split_order_form').submit();
          // Or if you DO want validation, call submitSplitOrder('add_new_product');
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

      // Add address
      $('#new-address-form').on('submit', function (e) {
        e.preventDefault();

        var form = $(this);
        var formData = form.serialize();

        $.ajax({
            url: "{{ route('order.changeOrderAddress') }}",
            type: "POST",
            data: formData,
            beforeSend: function () {
                $('#saveButton').prop('disabled', true).text('Saving...');
            },
            success: function (res) {
                if (res.res) {
                    let address_id = res.address_id;
                    let company = $('#company_name').val();
                    let city = $('#city').val();
                    let pincode = $('#postal_code').val();
                    let fullAddress = $('#address').val();

                    // Create new option
                    let newOption = new Option(`${company} - ${city} - ${pincode}`, address_id, true, true);
                    newOption.setAttribute('data-address', fullAddress);

                    // Determine which dropdown to update
                    let target = $('#bill_to_or_ship_to').val();

                    // if (target === 'bill_to') {
                    //     $('#bill_to').append(newOption).val(address_id).trigger('change');
                    // } else if (target === 'ship_to') {
                    //     $('#ship_to').append(newOption).val(address_id).trigger('change');
                    // }

                    $('#bill_to').append(newOption).val(address_id).trigger('change');
                    $('#ship_to').append(newOption).val(address_id).trigger('change');

                    // Also append to the other dropdown if needed (optional)
                    if (target === 'bill_to') {
                        $('#ship_to').append(new Option(`${company} - ${city} - ${pincode}`, address_id, false, false))
                    } else {
                        $('#bill_to').append(new Option(`${company} - ${city} - ${pincode}`, address_id, false, false))
                    }

                    // Reset form and hide modal
                    $('#new-address-form')[0].reset();
                    $('#new-address-modal').modal('hide');
                } else {
                    alert("Something went wrong: " + res.msg);
                }
            },
            error: function (xhr) {
                console.error(xhr.responseText);
                alert('Server error. Please try again.');
            },
            complete: function () {
                $('#saveButton').prop('disabled', false).text('Save');
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

    function add_new_address(bill_to_or_ship_to) {

      // Reset all form fields
      $('#new-address-modal form')[0].reset(); // Reset form inputs
      $('#gstin_err, #gstin_success, #phone_err, #address_err').html(''); // Clear error/success messages
      $('#companyNameHelp, #namenHelp, #addressHelp, #postalCodeHelp, #gstinHelp').html('');
      $('#divCity, #divState, #divCountry').show();
      $('#city, #state, #country').attr('required', true);
      $('#saveButton').prop('disabled', false);

      $('#new-address-modal').modal('show');
      $('#bill_to_or_ship_to').val(bill_to_or_ship_to);
    }

    function edit_address(address) {
      var url = '{{ route('addresses.edit', ':id') }}';
      url = url.replace(':id', address);

      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: 'GET',
        success: function(response) {
          $('#edit_modal_body').html(response.html);
          $('#edit-address-modal').modal('show');
          AIZ.plugins.bootstrapSelect('refresh');

          @if (get_setting('google_map') == 1)
            var lat = -33.8688;
            var long = 151.2195;

            if (response.data.address_data.latitude && response.data.address_data.longitude) {
              lat = parseFloat(response.data.address_data.latitude);
              long = parseFloat(response.data.address_data.longitude);
            }

            initialize(lat, long, 'edit_');
          @endif
        }
      });
    }

    $(document).on('change', '[name=country_id]', function() {
      var country_id = $(this).val();
      get_states(country_id);
    });

    $(document).on('change', '[name=state_id]', function() {
      var state_id = $(this).val();
      get_city(state_id);
    });

    function get_states(country_id) {
      $('[name="state"]').html("");
      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: "{{ route('get-state') }}",
        type: 'POST',
        data: {
          country_id: country_id
        },
        success: function(response) {
          var obj = JSON.parse(response);
          if (obj != '') {
            $('[name="state_id"]').html(obj);
            AIZ.plugins.bootstrapSelect('refresh');
          }
        }
      });
    }

    function get_city(state_id) {
      $('[name="city"]').html("");
      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: "{{ route('get-city') }}",
        type: 'POST',
        data: {
          state_id: state_id
        },
        success: function(response) {
          var obj = JSON.parse(response);
          if (obj != '') {
            $('[name="city_id"]').html(obj);
            AIZ.plugins.bootstrapSelect('refresh');
          }
        }
      });
    }

    $(document).on('keyup', '[name=gstin]', function() {
      var gstin = $(this).val();
      if (gstin.length >= 15) {
        get_gstin_data(gstin);
      }else{
        var gstinValue = gstin; // Save current GSTIN value

        // Reset the form
        $('#new-address-modal form')[0].reset();

        // Restore GSTIN field value
        $('[name=gstin]').val(gstinValue);

        // Clear all help/error messages and reset visible fields
        $('#gstin_err, #gstin_success, #phone_err, #address_err').html('');
        $('#companyNameHelp, #namenHelp, #addressHelp, #postalCodeHelp, #gstinHelp').html('');
        $('#divCity, #divState, #divCountry').show();
        $('#city, #state, #country').attr('required', true);
        $('#saveButton').prop('disabled', false);
        $('#gstin_err').html('');
        $('#saveButton').prop('disabled', false);
      }
    });

    function get_gstin_data(gstin) {
      $.ajax({
        url: "https://appyflow.in/api/verifyGST",
        type: 'POST',
        beforeSend: function(){
          $('.ajax-loader').css("visibility", "visible");
        },
        headers: {
            "Content-Type": "application/json" // Specify the content type header
        },
        data: JSON.stringify({ // Convert data to JSON format
            key_secret: "H50csEwe27SjLf7J2qP9Av28uOm2",
            gstNo: gstin
        }),
        success: function(response) {
          $('#gstin_err').html('');
          $('#saveButton').prop('disabled', false);
          if(response){
            if (response.hasOwnProperty('error')) {
              // $('#gstin_err').html(response.message);
              $('#gstin_err').html('Invalid GST');
              $('#gstin_success').html('');
              $('#phone-code').val('');
              $('#phone_err').html('');
              $('#saveButton').prop('disabled', true);
            } else {
              $('#gstin_err').html('');
              $.ajax({
                headers: {
                  'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '{{ route("checkGsitnExistOnProfile") }}',
                type: 'POST',
                data: { gstin: gstin },
                dataType: 'json',
                success: function (res) {
                  if (res.hasOwnProperty('error')) {
                    $('#gstin_err').html(res.message);
                    $('#gstin_success').html('');
                    $('#phone-code').val('');
                    $('#phone_err').html('');
                    $('#address_err').html('');
                    $('#saveButton').prop('disabled', true);
                    $('#address').removeAttr('readonly');
                    $('#postal_code').removeAttr('readonly');
                    $('#divCity').show();                    
                    $('#city').attr('required', 'required');
                    $('#divCountry').show();
                    $('#country').attr('required', 'required');
                    $('#divState').show();
                    $('#state').attr('required', 'required');
                    // $('#divPhone').show();
                    // $('#phone').attr('required', 'required');
                  }else{
                    $('#gstin_success').html('Valid GST');
                    $('#company_name').val(response.taxpayerInfo.tradeNam);
                    $('#name').val(response.taxpayerInfo.lgnm);
                    // $('#gst_data').val(JSON.stringify(response));
                    var address = (response.taxpayerInfo.pradr.addr.bnm + ', ' + response.taxpayerInfo.pradr.addr.st + ', ' + response.taxpayerInfo.pradr.addr.loc).replace(/^[, ]+|[, ]+$/g, '');                              
                    $('#address').val(address);
                    var address_2 = (response.taxpayerInfo.pradr.addr.bno + ', ' + response.taxpayerInfo.pradr.addr.dst).replace(/^[, ]+|[, ]+$/g, '');                              
                    $('#address_2').val(address_2);
                    
                    $('#postal_code').val(response.taxpayerInfo.pradr.addr.pncd);

                    $('#gstinHelp').html(gstin);
                    $('#companyNameHelp').html(response.taxpayerInfo.tradeNam);
                    $('#namenHelp').html(response.taxpayerInfo.lgnm);
                    $('#addressHelp').html(address);
                    $('#postalCodeHelp').html(response.taxpayerInfo.pradr.addr.pncd);
                    $('#phone-code').val('');
                    $('#phone_err').html('');
                    $('#address_err').html('');
                    // $('#address').attr('readonly', 'readonly');
                    // $('#address_2').attr('readonly', 'readonly');
                    // $('#postal_code').attr('readonly', 'readonly');
                    $('#city').val(response.taxpayerInfo.pradr.addr.loc);
                    $('#divCity').hide();
                    $('#city_field_id').removeAttr('required');
                    $('#divCountry').hide();
                    $('#country').removeAttr('required');
                    $('#divState').hide();
                    $('#state').removeAttr('required');
                    // $('#divPhone').hide();
                    // $('#phone').removeAttr('required');
                  }
                },
                error: function (xhr, status, error) {
                    console.error(xhr.responseText);
                    // Optionally handle errors
                }
            });
              // var obj = JSON.parse(JSON.stringify(response));
              // if (obj != '') {
              //   console.log(obj);
              //   $('[name="name"]').val(obj.gst_data.taxpayerInfo.lgnm);
              // }
            }
          }
        },
        complete: function(){
          $('.ajax-loader').css("visibility", "hidden");
        },
        failure: function(error) {
        }
      });
    }


     // ---- WhatsApp Reminder AJAX Button ----
      $('#btnSendDueOverdueWhatsapp').on('click', function () {
          if (!confirm('Send WhatsApp due/overdue reminder now?')) {
              return;
          }

          var url = "{{ route('order.sendAdditionalWhatsapp.ajax', $orderData->id) }}";

          $('#loader').show();

          $.ajax({
              url: url,
              type: 'GET',
              dataType: 'json',
              success: function (res) {
                  if (res.success) {
                      // Your requested message:
                      AIZ.plugins.notify('success', 'WhatsApp Sent Successfully.');
                  } else {
                      AIZ.plugins.notify('danger', res.message || 'Something went wrong.');
                  }
              },
              error: function (xhr) {
                  AIZ.plugins.notify('danger', 'Server error. Please try again.');
              },
              complete: function () {
                  $('#loader').hide();
              }
          });
      });

      document.addEventListener('DOMContentLoaded', function () {
          // Attach change listener to all warranty checkboxes
          document.querySelectorAll('input[type="checkbox"][name^="warranty_"]').forEach(function (checkbox) {
              const id = checkbox.name.replace('warranty_', '');

              const durationText  = document.getElementById('warranty_duration_' + id);        // for is_warranty == 1
              const durationInput = document.getElementById('warranty_duration_input_' + id);   // for is_warranty != 1

              // On change toggle visibility, value, and required
              checkbox.addEventListener('change', function () {
                  if (durationText) {
                      durationText.style.display = this.checked ? 'block' : 'none';
                  }

                  if (durationInput) {
                      if (this.checked) {
                          durationInput.style.display = 'block';
                          durationInput.required = true;      // make required when checked
                      } else {
                          durationInput.style.display = 'none';
                          durationInput.value = '';           // clear value when unchecked
                          durationInput.required = false;     // remove required when unchecked
                      }
                  }
              });

              // Initial state on page load (in case of old() / validation errors)
              if (durationInput) {
                  if (checkbox.checked) {
                      durationInput.style.display = 'block';
                      durationInput.required = true;
                  } else {
                      durationInput.style.display = 'none';
                      durationInput.required = false;
                  }
              }
          });
      });
</script>

@endsection
