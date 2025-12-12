@extends('backend.layouts.app')

@section('content')
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

    .warranty-badge{
      display:inline-block;
      padding:2px 8px;
      border-radius:12px;
      background:#ff9800;
      color:#fff;
      font-weight:600;
      font-size:12px;
      line-height:1.3;
      margin-left:6px;
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
    <input type="hidden" name="force_create" id="forceCreate" value="0"> {{-- ★ NEW --}}
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
                <label class="col-md-4 col-form-label">Party:</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="party_name" value="{{ $orderData->sub_order_user_name }}" readonly>
                </div>
              </div>
              
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Bill To:</label>
                <div class="col-md-8">
                  @php
                    $billingAddress = json_decode($orderData->billing_address, true);
                  @endphp
                  @if($billingAddress)
                      <strong>Company:</strong> {{ $billingAddress['company_name'] ?? 'N/A' }} <br>
                      <strong>GSTIN:</strong> {{ $billingAddress['gstin'] ?? 'N/A' }} <br>
                      <strong>Email:</strong> {{ $billingAddress['email'] ?? 'N/A' }} <br>
                      <strong>Address:</strong> {{ $billingAddress['address'] ?? 'N/A' }} <br>
                      <strong>City:</strong> {{ $billingAddress['city'] ?? 'N/A' }}, <br>
                      <strong>State:</strong> {{ $billingAddress['state'] ?? 'N/A' }} <br>
                      <strong>Country:</strong> {{ $billingAddress['country'] ?? 'N/A' }} <br>
                      <strong>Postal Code:</strong> {{ $billingAddress['postal_code'] ?? 'N/A' }} <br>
                      <strong>Phone:</strong> {{ $billingAddress['phone'] ?? 'N/A' }}
                  @else
                      <p>No billing address available</p>
                  @endif
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Doc Date:</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="order_date" placeholder="{{ translate('Offer Date') }}" value="{{ date('d-m-Y', strtotime($orderData->created_at)) }}" readonly>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Transport Name:</label>
                <div class="col-md-8">
                  <?php /* <input type="text" class="form-control" name="transport_name" placeholder="{{ translate('Transport Name') }}" value="{{ $orderData->transport_name }}" readonly> */ ?> 
                  <div class="input-group">
                    <select class="form-control transport-select" name="transport_name" id="transport_name" data-warehouse="{{ $userDetails->user_warehouse->id }}">
                        <option value="">---- Select Transport Name ----</option>
                        @foreach($allTransportData as $transport)
                          <option value="{{ $transport->name }}" data-transport_table_id="{{ $transport->id }}" data-gst="{{ $transport->gstin }}" data-mobile="{{ $transport->mobile_no }}" @if(isset($selectedTransportData->transport_id) AND $selectedTransportData->transport_id == $transport->id) selected  @endif>{{ $transport->name }}</option>
                        @endforeach
                    </select>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addTransporterModal">
                            <i class="las la-plus"></i>
                        </button>
                    </div>
                  </div>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Multi Scan:</label>
                <div class="col-md-8">
                  <textarea class="form-control" name="barcode" id="barcode" placeholder="{{ translate('Multi Scan') }}" col="5" autofocus></textarea>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Branch:</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="warehouse" placeholder="{{ translate('Branch') }}" value="{{ $orderData->order_warehouse->name }}" readonly>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Ship To:</label>
                <div class="col-md-8">
                  @php
                    $shipping_address = json_decode($orderData->shipping_address, true);
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
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Transport Id:</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="transport_id" id="transport_id" placeholder="{{ translate('Transport Id.') }}" value="{{ old('transport_id', data_get($selectedTransportData ?? null, 'transport_id', '')) }}" readonly>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Transport Mobile:</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="transport_phone" id="transport_phone" placeholder="{{ translate('Transport Mobile.') }}" value="{{ old('transport_phone', data_get($selectedTransportData ?? null, 'transport_phone', '')) }}" readonly>
                </div>
              </div>
              <div class="form-group row">
                <label class="col-md-4 col-form-label">Transport Remarks:</label>
                <div class="col-md-8">
                  <input type="text" class="form-control" name="transport_remarks" id="transport_remarks" placeholder="{{ translate('Transport Remarks.') }}" value="{{ old('transport_remarks', data_get($selectedTransportData ?? null, 'transport_remarks', '')) }}">
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
      <div class="col-lg-12" style="float:right; margin-bottom: 22px;">

        <a href="{{ route('order.preClosedOrder', [$orderData->id, $orderData->type] )}}" class="btn btn-danger pre-close-btn" data-sub_order_id="{{ $orderData->id }}" data-sub_order_type="{{ $orderData->type }}" data-has_btr="{{ $hasBTRId }}"> Pre Closed This Order </a>

        <a href="{{ route('products.quickorder', ['sub_order_id' => encrypt($orderData->id)] )}}" class="btn btn-success">+ Add New Product </a>

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
                    <th style="display: table-cell;">Clg Stock</th>
                    <th style="display: table-cell;">Order Qty</th>
                    <th style="display: table-cell;">Billed Qty</th>
                    <th style="display: table-cell;">Rate</th>
                    <th style="display: table-cell;">Billed Amount</th>
                    <th style="display: table-cell;">Action</th>
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
                      /* $closingStock = optional($orderDetail->stocks->where('warehouse_id', $subOrderDetail->warehouse_id)->first())->qty; */

                      // Detect 41 Manager using ONLY user_title (or use controller-passed $is41Manager)
                      $is41Manager = ($is41Manager ?? false)
                          ?: in_array(strtolower(trim((string) optional(Auth::user())->user_title)), ['manager_41','41_manager'], true);

                      // Pick stock table
                      $stockTable = $is41Manager ? 'manager_41_product_stocks' : 'products_api';

                      // Get part no from either structure
                      $partNo = $orderDetail->part_no ?? optional($orderDetail->product)->part_no;

                      $kolStocks = 0;
                      $delStocks = 0;
                      $mumStocks = 0;

                      if ($partNo) {
                          $kolkataStocks = DB::table($stockTable)->where('part_no', $partNo)->where('godown', 'Kolkata')->first();
                          $delhiStocks   = DB::table($stockTable)->where('part_no', $partNo)->where('godown', 'Delhi')->first();
                          $mumbaiStocks  = DB::table($stockTable)->where('part_no', $partNo)->where('godown', 'Mumbai')->first();

                          /********** Closing Stock show as per warehouse order **************/
                          if ($subOrderDetail->warehouse_id == 1) {
                              $kolStocks = $kolkataStocks ? (int) $kolkataStocks->closing_stock : 0;
                          } elseif ($subOrderDetail->warehouse_id == 2) {
                              $delStocks = $delhiStocks ? (int) $delhiStocks->closing_stock : 0;
                          } elseif ($subOrderDetail->warehouse_id == 6) {
                              $mumStocks = $mumbaiStocks ? (int) $mumbaiStocks->closing_stock : 0;
                          }
                      }

                      $closingStock = ($kolStocks + $delStocks + $mumStocks) ?: 0;

                      $style = $closingStock === 0 ? "style='color:#f00;'" : "";

                      $part_number      = isset($part_number) ? $part_number . ',' . $partNo : (string) $partNo;
                      $subOrderDetailId = isset($subOrderDetailId) ? $subOrderDetailId . ',' . $subOrderDetail->id : (string) $subOrderDetail->id;
                  @endphp
                      <?php /* @if($subOrderDetail->reallocated < $subOrderDetail->approved_quantity) */ ?>
                      @if(($subOrderDetail->pre_closed + $subOrderDetail->reallocated + $subOrderDetail->challan_qty) < $subOrderDetail->approved_quantity)
                    @php
                      // ★ use the RAW key the controller used
                      $__pn = (string) ($orderDetail->part_no ?? '');
                      $__rate = (float) ($subOrderDetail->approved_rate ?? 0);
                      $__buy  = (float) ($allProductRates[$__pn] ?? ($orderDetail->purchase_price ?? $orderDetail->unit_price ?? 0));
                    @endphp
                    <tr id="row_{{$count}}"
                        class="product-row"                                         
                        data-partno="{{ $__pn }}"                                  
                        data-rate="{{ $__rate }}"                                   
                        data-purchase-price="{{ $__buy }}">                            
                          <td style="display: table-cell;">
                            {{ $orderDetail->part_no }}
                          </td>
                          <td style="display: table-cell;">
                            {{ $orderDetail->name }}
                             {{-- [WARRANTY BADGE] add yahin --}}
                            @if(!empty($subOrderDetail->is_warranty) && (int)$subOrderDetail->is_warranty === 1)
                                <span class="warranty-badge">Warranty</span>
                                {{-- ya: <span class="badge badge-warning">Warranty</span> --}}
                            @endif
                            <div><strong>Seller Name : </strong>{{ $orderDetail->sellerDetails->user->name }}</div>
                            <div><strong>Seller Location : </strong>{{ $orderDetail->sellerDetails->user->user_warehouse->name }}</div>
                            <div><strong>Order For : </strong>{{ $orderData->sub_order_user_name }}</div>
                            @if($subOrderDetail->in_transit != NULL AND $subOrderDetail->in_transit != 0)
                              @php
                                $btrStatus = "<span style='color:#f00;'>Pending<span>";

                                $getChallanDetailsData = \App\Models\ChallanDetail::where('sub_order_details_id', $subOrderDetail->id)->where('product_id', $subOrderDetail->product_id)->first();

                                $getInvoiveDetailsData = \App\Models\InvoiceOrderDetail::where('sub_order_details_id', $subOrderDetail->id)->where('part_no', $orderDetail->part_no)->first();

                                if($getInvoiveDetailsData != NULL){
                                  $btrStatus = "<span style='color:#15bf22'>Deliverd<span>";
                                }elseif($getChallanDetailsData != NULL){
                                  $btrStatus = "<span style='color:#3215bf'>In Transit<span>";
                                }
                              @endphp
                              <div><strong>BTR Quantity : </strong>{{ $subOrderDetail->in_transit }}</div>
                              <div><strong>BTR Status : </strong>{!! $btrStatus !!}</div>
                            @endif<div>
                            <div><input type="text" class="form-control" name="remark_{{ $orderDetail->id }}" id="remark_{{ $orderDetail->id }}" value="{{ $subOrderDetail->remarks }}" placeholder="Remarks">
                          </td>
                          <td style="display: table-cell;">
                            @if(strlen($orderDetail->hsncode) < 8)
                              <input type="text" id="hsncode_{{ $orderDetail->part_no }}" value="{{ $orderDetail->hsncode }}" style="color:#f00;" data-part_no="{{ $orderDetail->part_no }}" class="hsncode-input">
                            @else
                              <span style="{{ strlen($orderDetail->hsncode) < 8 ? 'color:#f00;' : '' }}" id="span_hsncode">{{ $orderDetail->hsncode }}</span>
                              <input type="hidden" id="hsncode_{{ $orderDetail->part_no }}" value="{{ $orderDetail->hsncode }}">
                            @endif
                          </td>
                          <td style="display: table-cell;">{{ $orderDetail->tax }}%</td>
                          <td style="display: table-cell; {{ ($closingStock == '0') ? 'color:#f00;' : (($closingStock < $subOrderDetail->approved_quantity) ? 'color:#ff8100;' : '') }}">
                            {{ $closingStock < 0 ? 0 : $closingStock }}
                            <input type="hidden" id="closing_stock_{{ $orderDetail->part_no }}" value="{{ $closingStock }}">
                          </td>
                          <td style="display: table-cell; {{ ($closingStock == '0') ? 'color:#f00;' : (($closingStock < $subOrderDetail->approved_quantity) ? 'color:#ff8100;' : '') }}">
                            {{ $subOrderDetail->approved_quantity - ($subOrderDetail->pre_closed + $subOrderDetail->reallocated + $subOrderDetail->challan_qty) }}
                            <input type="hidden" id="{{ $orderDetail->part_no }}" value="{{ $subOrderDetail->approved_quantity - ($subOrderDetail->pre_closed + $subOrderDetail->reallocated + $subOrderDetail->challan_qty) }}" style="color:#f00;" data-sub_order_detail_id="{{ $subOrderDetail->id }}">
                          </td>
                          <td style="display: table-cell;">
                            <?php /* <input type="number" name="billed_qty_{{ $orderDetail->part_no }}" id="billed_qty_{{ $orderDetail->part_no }}" value="" class="form-control" @if($closingStock <= 0 ) readonly @endif /> */ ?>
                            <input type="number" name="billed_qty_{{ $orderDetail->part_no }}" id="billed_qty_{{ $orderDetail->part_no }}" value="" class="form-control"/>
                          </td>
                          <td style="display: table-cell;">
                            {{ $subOrderDetail->approved_rate }}
                            <input type="hidden" name="rate_{{ $orderDetail->part_no }}" id="rate_{{ $orderDetail->part_no }}" value="{{ $subOrderDetail->approved_rate }}">
                          </td>
                          <td style="display: table-cell;">
                            <span id="span_billed_amount_{{ $orderDetail->part_no }}"></span>
                            <input type="hidden" name="billed_amount_{{ $orderDetail->part_no }}" id="billed_amount_{{ $orderDetail->part_no }}" value="">
                          </td>
                          <td style="display: table-cell;">

                         {{-- PRINT (BARCODE) – sirf warranty rows par --}}
                            @if(!empty($subOrderDetail->is_warranty) && (int)$subOrderDetail->is_warranty === 1)
                              @php
                                $remainingQty = $subOrderDetail->approved_quantity - ($subOrderDetail->pre_closed + $subOrderDetail->reallocated + $subOrderDetail->challan_qty);
                              @endphp
                              <button
                                type="button"
                                class="btn btn-outline-primary btn-sm rounded-pill"
                                data-toggle="modal"
                                data-target="#barcodeModal"
                                data-part_no="{{ $orderDetail->part_no }}"
                                data-max_qty="{{ max(0, $remainingQty) }}"
                                title="Print Warranty Barcode"
                              >
                                <i class="las la-barcode"></i>
                              </button>
                            @endif
                            @php
                                $hasBTROrderId = $order->btrSubOrder->id ?? '';
                            @endphp
                            @if($subOrderDetail->pre_closed_status == 0)
                              <i class="las la-handshake preclose" style="font-size: 30px; color:#f00; cursor:pointer;" title="Pre Close" data-id="{{ $subOrderDetail->id }}" data-sub_order_id="{{ $subOrderDetail->sub_order_id }}" data-sub_order_type="{{ $subOrderDetail->type }}" data-sub_order_qty="{{ $subOrderDetail->approved_quantity - $subOrderDetail->pre_closed }}" data-closing_stock="{{ $closingStock }}" data-item_name="{{ $orderDetail->name }}"  data-has_btr="{{ $hasBTRId }}"  data-has_btr_order_id="{{ $hasBTROrderId }}"  data-btr_qty="{{ $subOrderDetail->in_transit }}"></i>
                            @endif
                            <?php /* @if($subOrderDetail->pre_closed_status == 0 AND $userDetails->user_warehouse->id == $subOrderDetail->warehouse_id AND $subOrderDetail->type != 'btr')
                            @if($subOrderDetail->pre_closed_status == 0 AND $subOrderDetail->type != 'btr')
                              <a class="btn btn-soft-success btn-icon btn-circle btn-sm" href="{{ route('order.reallocationSplitOrder', $subOrderDetail->id) }}" title="{{ translate('Reallocation Order') }}" style="background-color: #00ffe7;">
                                  <i class="las la-project-diagram"></i>
                              </a>
                            @endif */ ?>

                            {{-- REPLACE: full pre-close + redirect to Quick Order --}}
                            @if($subOrderDetail->pre_closed_status == 0)
                              <a
                                href="{{ route('order.replacePrecloseItem', [
                                    'sub_order_details_id' => $subOrderDetail->id,
                                    'sub_order_type'       => $subOrderDetail->type,          // 'sub_order' | 'btr'
                                    'has_btr_order_id'     => $hasBTROrderId ?? '',
                                    // Agar defaults override karne ho to ye params pass kar sakte ho:
                                    // 'close_linked_btr'   => 1,   // only for 'sub_order' line
                                    // 'propagate_to_main'  => 1,   // only for 'btr' line
                                ]) }}"
                                class="btn btn-icon btn-circle btn-sm"
                                style="font-size: x-large;"
                                title="Replace (Pre-close & Add New)"
                                onclick="return confirm('This will pre-close this item completely and open Add New Product. Continue?')"
                              >
                                <i style="color: #ffc107;" class="las la-exchange-alt "></i>
                              </a>
                            @endif
                          </td>
                        </tr>
                      @endif
                      @php                      
                        $count++;
                      @endphp
                    @endforeach
                  @endforeach
                  <input type="hidden" class="form-control" name="part_number" id="part_number" value="{{ trim($part_number,',') }}">
                  <input type="hidden" class="form-control" name="sub_order_detail_id" id="sub_order_detail_id" value="{{ trim($subOrderDetailId,',') }}">
              </tbody>              
            </table>
          </div>
        </div>
      </div>
    </div>
    <div>
      <a href="javascript:void(0)" class="btn btn-info" id="saveChalan" style="float:right;">Submit Challan</a>         
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
            <input type="hidden" class="form-control" name="sub_order_id" id="sub_order_id_pre_close" value="" required>
            <input type="hidden" class="form-control" name="sub_order_qty" id="sub_order_qty" value="" required>
            <input type="hidden" class="form-control" name="sub_order_type" id="sub_order_type" value="" required>
            <input type="hidden" class="form-control" name="has_btr_order_id" id="has_btr_order_id" value="{{ $hasBTROrderId }}">
            <input type="hidden" class="form-control" name="btr_qty" id="btr_qty" value="">
            
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
  <!-- Add Transporter Modal -->
  <div class="modal fade" id="addTransporterModal" tabindex="-1" role="dialog" aria-labelledby="addTransporterModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <form id="addTransporterForm">
          <div class="modal-content" style="padding:0px;">
              <div class="modal-header bg-success text-white">
                  <h5 class="modal-title" id="addTransporterModalLabel">Add New Transporter</h5>
                  <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                      &times;
                  </button>
              </div>
              <div class="modal-body">
                  <div class="alert alert-danger d-none" id="transporterError"></div>
                  <div class="form-group" style="text-align:left;">
                      <label for="newTransporterName">Transporter Name</label>
                      <input type="text" class="form-control" id="newTransporterName" required>
                  </div>
                  <div class="form-group" style="text-align:left;">
                      <label for="newTransporterRegId">GST Registration ID</label>
                      <input type="text" class="form-control" id="newTransporterRegId" required>
                  </div>
              </div>
              <div class="modal-footer">
                  <button type="submit" class="btn btn-success">
                      <span class="spinner-border spinner-border-sm d-none" id="transporterSpinner"></span>
                      <span id="transporterBtnText">Add Transporter</span>
                  </button>
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
              </div>
          </div>
      </form>
    </div>
  </div>




  <!-- Barcode Modal -->
<div class="modal fade" id="barcodeModal" tabindex="-1" role="dialog" aria-labelledby="barcodeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document" style="max-width:720px;">
    <form id="barcodeForm" action="https://mazingbusiness.com/admin/print-barcode" method="GET" target="_blank" style="width:100%;">
      <input type="hidden" name="_token" value="O2tP8t1XrixleKS93CeK90N6G8hnH8WqMOlqulHa">

      <div class="modal-content" style="border:0; border-radius:14px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,.2);">
        <!-- Header -->
        <div class="modal-header text-white" style="background:#024285; border-bottom:0;">
          <h5 class="modal-title d-flex align-items-center" id="barcodeModalLabel" style="font-weight:700; letter-spacing:.3px;">
            <i class="las la-barcode" style="font-size:22px; margin-right:8px;"></i>
            Generate Barcode
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity:.9;">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <!-- Body -->
        <div class="modal-body" style="padding:18px 20px 8px;">
          <!-- Part No -->
          <div class="form-group mb-3">
            <label class="mb-1" style="font-weight:600;">Part No</label>
            <input
              type="text"
              class="form-control"
              name="part_no"
              id="modal_part_no"
              readonly
              style="color:#074e86; font-weight:700; background:#f6f9ff;"
            >
          </div>

          <!-- Qty & Copies -->
          <div class="form-row">
            <div class="form-group col-md-6 mb-3">
              <label class="mb-1" style="font-weight:600;">Quantity per Barcode</label>
              <input
                type="number"
                class="form-control"
                name="qty"
                id="modal_qty"
                required
                min="1"
                inputmode="numeric"
                placeholder="e.g. 5"
              >
              <small class="form-text text-muted" style="margin-top:4px;">
                Default: remaining order qty (auto-filled on open)
              </small>
            </div>
            <div class="form-group col-md-6 mb-3">
              <label class="mb-1" style="font-weight:600;">Number of Copies</label>
              <input
                type="number"
                class="form-control"
                name="copies"
                required
                min="1"
                inputmode="numeric"
                placeholder="e.g. 2"
              >
              <small class="form-text text-muted" style="margin-top:4px;">
                How many barcodes to print
              </small>
            </div>
          </div>

          <!-- Imported By (optional) -->
          <div class="form-check mt-2 mb-2">
            <input class="form-check-input" type="checkbox" id="customImporterCheckbox" name="imported_by" value="1">
            <label class="form-check-label" for="customImporterCheckbox" style="font-weight:600;">
              Imported By
            </label>
          </div>

          <div id="customImporterFields" style="display:none; border:1px dashed #ced4da; border-radius:10px; padding:12px; margin-top:10px;">
            <div class="form-group mb-2">
              <label class="mb-1" style="font-weight:600;">Name Of Importer</label>
              <input type="text" class="form-control" name="custom_company" placeholder="Company / Importer name">
            </div>

            <div class="form-group mb-2">
              <label class="mb-1" style="font-weight:600;">Address</label>
              <textarea class="form-control" name="custom_address" rows="2" placeholder="Address"></textarea>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6 mb-2">
                <label class="mb-1" style="font-weight:600;">Customer Care Number</label>
                <input type="text" class="form-control" name="custom_phone" placeholder="+91-XXXXXXXXXX">
              </div>
              <div class="form-group col-md-6 mb-2">
                <label class="mb-1" style="font-weight:600;">Email Id</label>
                <input type="email" class="form-control" name="custom_email" placeholder="care@example.com">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6 mb-2">
                <label class="mb-1" style="font-weight:600;">Country of Origin</label>
                <input type="text" class="form-control" name="country_of_origin" placeholder="e.g. India">
              </div>
              <div class="form-group col-md-6 mb-2">
                <label class="mb-1" style="font-weight:600;">Month and Year of Mfg</label>
                <input type="month" class="form-control" name="mfg_month_year">
                <small class="form-text text-muted">Format: YYYY-MM (e.g., 2025-02)</small>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer" style="border-top:0; padding:12px 20px 18px;">
          <button type="button" class="btn btn-light" data-dismiss="modal" style="border:1px solid #e9ecef;">
            Cancel
          </button>
          <button type="submit" id="generateBtn" class="btn btn-primary" style="padding:.45rem 1rem;">
            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            <span class="btn-text">Generate</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>


@endsection

@section('script')
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- jQuery & Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    $(document).ready(function () {

      
      function applyScan(latestBarcode) {
      const part_number = latestBarcode.substring(0, 7);

      // Parse quantity (+ optional trailing "B<stock>")
      const bIndex = latestBarcode.indexOf('B');
      let quantity = 0, stock = null;
      if (bIndex !== -1) {
        quantity = parseInt(latestBarcode.substring(7, bIndex), 10) || 0;
        stock = parseInt(latestBarcode.substring(bIndex + 1), 10) || null;
      } else {
        quantity = parseInt(latestBarcode.substring(7), 10) || 0;
      }
      if (!quantity) return; // invalid scan qty

      const closing_stock = parseInt($("#closing_stock_" + part_number).val() || "0", 10);
      const hsncode = String($("#hsncode_" + part_number).val() || "");
      const $mapInput = $("#" + part_number); // hidden input: remaining/order qty

      if (closing_stock <= 0) {
        alert("Scanned item's didn't have closing stock.");
        return;
      }

      if (hsncode.length < 8) {
        alert("PLease Update HSN Code of " + part_number + ".");
        return;
      }

      // If product row not present → NEW PRODUCT FLOW (aapka purana code)
      if ($mapInput.length === 0) {
        if (!confirm("This product is new. Do you want to add?")) return;

        const sub_order_id = $("#sub_order_id").val();
        const user_id = $("#user_id").val();

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
          success: function (response) {
            if (response.success) {
              alert("Product added successfully!");

              // HSN style
              const hsnStyle = (response.data.hsncode || "").length < 8 ? 'color:#f00;' : '';
              const closingStockStyle = (parseInt(response.data.closing_stock, 10) <= 0) ? 'color:#f00;' : '';

              var reallocationRoute = "{{ route('order.reallocationSplitOrder', ':id') }}";
              let reallocationUrl = reallocationRoute.replace(':id', response.data.id);

              const newRow = `
                <tr id="row_${response.data.id}">
                  <td style="display: table-cell;">${response.data.part_no}</td>
                  <td style="display: table-cell;">
                    ${response.data.name}
                    <div><strong>Seller Name : </strong>${response.data.seller_name}</div>
                    <div><strong>Seller Location : </strong>${response.data.seller_location}</div>
                    <div><strong>Order For : </strong>${response.data.order_for}</div>
                    <div><input type="text" class="form-control" name="remark_${response.data.id}" id="remark_${response.data.id}" value="" placeholder="Remarks"></div>
                  </td>
                  <td style="display: table-cell;"><span style="${hsnStyle}">${response.data.hsncode}</span></td>
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
                    <i class="las la-handshake preclose" style="font-size:30px; color:#f00; cursor:pointer;"
                      title="Pre Close"
                      data-id="${response.data.id}"
                      data-sub_order_id="${response.data.sub_order_id}"
                      data-sub_order_type="${response.data.type}"
                      data-sub_order_qty="${response.data.quantity}"
                      data-closing_stock="${response.data.closing_stock}"
                      data-item_name="${response.data.name}"
                      data-has_btr="${response.data.has_btr}">
                    </i>
                    <a class="btn btn-soft-success btn-icon btn-circle btn-sm"
                       href="${reallocationUrl}"
                       title="{{ translate('Reallocation Order') }}"
                       style="background-color:#00ffe7;">
                       <i class="las la-project-diagram"></i>
                    </a>
                  </td>
                </tr>
              `;
              $("#orderTable tbody").append(newRow);

              // Details modal (optional)
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
          error: function (xhr) {
            console.error(xhr.responseText);
            alert("Error sending data!");
          }
        });

        return; // end new-product flow
      }

      // Existing row → update billed qty + amount
      const availableQty = parseInt($mapInput.val(), 10) || 0;
      const $qtyInput = $("#billed_qty_" + part_number);
      const oldQty = parseInt($qtyInput.val(), 10) || 0;
      let billed_qty = oldQty + quantity;

      // If exceeds available, ask before applying
      if (billed_qty > availableQty) {
        if (!confirm("Scan quantity is greater than order quantity.")) return;
      }

      $qtyInput.val(billed_qty);

      const rate = parseFloat($("#rate_" + part_number).val() || "0");
      const billed_amount = Math.round(billed_qty * rate); // round to nearest
      
      $("#span_billed_amount_" + part_number).text(billed_amount);
      $("#billed_amount_" + part_number).val(billed_amount);
    }


        
      function saveScannedBarcode(partNo, fullBarcode, onOk) {
        var $mapEl = $("#" + partNo);
        if ($mapEl.length === 0) return;
        var sodId = $mapEl.data("sub_order_detail_id");
        if (!sodId) return;

        $.ajax({
          url: "{{ route('suborderdetail.storeBarcode') }}",
          type: "POST",
          data: {
            _token: "{{ csrf_token() }}",
            sub_order_detail_id: sodId,
            barcode: fullBarcode
          },
          success: function(res) {
            if (res && res.success && res.duplicate === false) {
              // ✅ non-duplicate => ab billed qty badhao
              if (typeof onOk === "function") onOk();
              if (window.AIZ?.plugins?.notify) AIZ.plugins.notify('success', 'Barcode saved');
            } else {
              // 🔁 duplicate => UI qty MAT badhao
              const msg = (res && res.message) ? res.message : 'This barcode is already scanned.';
              if (window.AIZ?.plugins?.notify) AIZ.plugins.notify('warning', msg);
              else alert(msg);
            }
          },
          error: function(xhr) {
            console.error(xhr.responseText || xhr.statusText);
            alert('Error saving barcode.');
          }
        });
      }



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
      

      // // Restrict input to max sub_order_qty
      // $(document).on("input", "#main_branch_pre_closed", function() {
      //     var maxQty = parseInt($("#sub_order_qty").val(), 10); // Get max quantity
      //     var enteredQty = parseInt($(this).val(), 10); // Get entered value

      //     if (enteredQty > maxQty) {
      //         $(this).val(maxQty); // Reset to maxQty if user enters more
      //     }
      // });

      $(document).on("change", ".transport-select", function(){   
        let warehouseId = $(this).data("warehouse");      
        let selectedOption = $(this).find("option:selected");
        let gstNumber = selectedOption.data("gst") || "";
        let transport_table_id = selectedOption.data("transport_table_id") || "";
        let mobile = selectedOption.data("mobile") || "";
        $("#transport_id").val(gstNumber);
        $("#transport_table_id").val(transport_table_id);
        $("#transport_phone").val(mobile);
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

      // ★ REPLACE EXISTING
      $('#saveChalan').on('click', function (e) {
          e.preventDefault();

          const underPricedPartNos = [];

          $('.product-row').each(function () {
            const rate = parseFloat($(this).data('rate')) || 0;
            const purchasePrice = parseFloat($(this).data('purchase-price')) || 0;

            if (rate < purchasePrice) {
              const pn = String($(this).data('partno') || '').trim();
              if (pn) underPricedPartNos.push(pn);
            }
          });

          if (underPricedPartNos.length > 0) {
            const uniqueList = Array.from(new Set(underPricedPartNos)); // de-dupe
            alert(
              "Warning! These products are being billed below purchase price:\n" +
              uniqueList.join(", ")
            );
            $('#forceCreate').val('1');   // force flag
          } else {
            $('#forceCreate').val('0');   // normal
          }

          $('#challan_order_form').submit(); // always submit
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
                    // let quantity = parseInt(latestBarcode.substring(7), 10);
                    
                  
                  /* Warranty part start  **/
                    // ⬇️ NEW: warranty row detect + early-return path
                      let isWarranty = false;
                      let $mapEl = $("#" + part_number); // id=part_no wala hidden input
                      if ($mapEl.length) {
                        isWarranty = $mapEl.closest("tr").find(".warranty-badge").length > 0;
                      }
                      if (isWarranty) {
                        // backend non-duplicate par hi callback call kare; duplicate par NHI
                        saveScannedBarcode(part_number, latestBarcode, function () {
                          applyScan(latestBarcode);  // yahi billed qty update karega (sirf non-duplicate)
                        });
                        barcodeInput.val('');   // input clear
                        return;       // ⬅️ IMPORTANT: yahin ruk jao; niche ka "normal flow" skip
                      }

                    /* Warranty part End  **/
                    
                    let bIndex = latestBarcode.indexOf('B');
                    let quantity, stock;

                    if (bIndex !== -1) {
                        // B is present
                        quantity = parseInt(latestBarcode.substring(7, bIndex), 10);
                        stock = parseInt(latestBarcode.substring(bIndex + 1), 10);
                    } else {
                        // B is not present
                        quantity = parseInt(latestBarcode.substring(7), 10);
                        stock = null; // or 0 or undefined as needed
                    }
                    // alert(stock);
                    let closing_stock = $("#closing_stock_" + part_number).val();
                    let hsncode = $("#hsncode_" + part_number).val();
                    let hiddenInput = $("#" + part_number);
                    if(closing_stock > 0){
                      if(hsncode.length < 8){
                        alert("PLease Update HSN Code of " + part_number + ".");
                      }else{
                        if (hiddenInput.length === 0) {
                            if (confirm("This product is new. Do you want to add?")) {
                                let sub_order_id = $("#sub_order_id").val();
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
                                                      <i class="las la-handshake preclose" style="font-size: 30px; color:#f00; cursor:pointer;" title="Pre Close" data-id="${response.data.id}" data-sub_order_id="${response.data.sub_order_id}" data-sub_order_type="${response.data.type}" data-sub_order_qty="${response.data.quantity}" data-closing_stock="${response.data.closing_stock}" data-item_name="${response.data.name} data-has_btr="${response.data.has_btr}""></i>

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
                    }else{
                      alert("Scaned Item's didn't have closing stock.");
                    }
                }
                barcodeInput.val('');
            }
        }
      });

      // Reset old handlers for this group
    $('input[id^="billed_qty_"]').off('.bq');
    
    // Focus: block if no stock
    $('input[id^="billed_qty_"]').on('focus.bq', function () {
      const $inp = $(this);
      const partNo = this.id.replace('billed_qty_', '');
      const closingStock = parseFloat($('#closing_stock_' + partNo).val()) || 0;
    
      $inp.removeAttr('max'); // ensure no browser cap
    
      if (closingStock <= 0) {
        alert("Scanned item's closing stock is 0. You can't bill this item.");
        $inp.val('').blur();
      }
    });
    
    // Live warnings (no compute here)
    $('input[id^="billed_qty_"]').on('input.bq', function () {
      const $inp = $(this);
      const partNo = this.id.replace('billed_qty_', '');
      const closingStock = parseFloat($('#closing_stock_' + partNo).val()) || 0;
      const orderQty     = parseFloat($('#' + partNo).val()) || 0; // hidden input holds order qty
      const raw = $inp.val();
      const qty = parseFloat(raw);
    
      if (closingStock <= 0) return;
    
      if (!isNaN(qty) && qty > closingStock) {
        const k1 = 'warn_cs_' + raw;
        if ($inp.data('lastWarnCS') !== k1) {
          alert('Quantity (' + qty + ') is greater than closing stock (' + closingStock + ').');
          $inp.val('').trigger('input'); // clear after OK; trigger if you need recalcs
          $inp.focus();
          // $inp.data('lastWarnCS', k1);
        }
      } else {
        $inp.removeData('lastWarnCS');
      }
    
      if (!isNaN(qty) && orderQty > 0 && qty > orderQty) {
        const k2 = 'warn_oq_' + raw;
        if ($inp.data('lastWarnOQ') !== k2) {
          alert('Trying to scan more than order quantity (' + orderQty + ').');
          $inp.data('lastWarnOQ', k2);
        }
      } else {
        $inp.removeData('lastWarnOQ');
      }
    });
    
    // Enter: stop compute if > closing stock; warn-only if > order qty
    $('input[id^="billed_qty_"]').on('keypress.bq', function (e) {
      if (e.which !== 13) return; // only Enter
      e.preventDefault();
    
      const $inp = $(this);
      const partNo = this.id.replace('billed_qty_', '');
      const closingStock = parseFloat($('#closing_stock_' + partNo).val()) || 0;
      const orderQty     = parseFloat($('#' + partNo).val()) || 0; // hidden input with order qty
    
      if (closingStock <= 0) {
        alert("Scanned item's closing stock is 0. You can't bill this item.");
        $inp.val('').blur();
        return;
      }
    
      const qty  = parseFloat($inp.val());
      if (isNaN(qty) || qty <= 0) {
        alert('Please enter a valid quantity greater than 0.');
        return;
      }
    
      // Hard stop for closing stock
      if (qty > closingStock) {
        alert('Quantity (' + qty + ') is greater than closing stock (' + closingStock + ').');
        $inp.val('').trigger('input'); // clear after OK; trigger if you need recalcs
        $inp.focus();
        return; // 🚫 do NOT compute
      }
    
      // Soft warning for order qty (continue to compute)
      if (orderQty > 0 && qty > orderQty) {
        alert('Trying to scan more than order quantity (' + orderQty + ').');
      }
    
      const rate = parseFloat($('#rate_' + partNo).val()) || 0;
      // const roundedAmount = Math.round(qty * rate);
      const roundedAmount = (qty * rate);
    
      $('#span_billed_amount_' + partNo).text(roundedAmount);
      $('#billed_amount_' + partNo).val(roundedAmount);
    });

      $('.hsncode-input').on('keydown', function(e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          let part_no = $(this).data('part_no');
          let hsncode = $(this).val();
          if(hsncode.length < 8){
            alert('HSN Code must be 8 digits.');
            return false;
          }
          $("#loader").show();
          $.ajax({            
            url: "{{ route('admin.updateHsncode') }}",
            method: "POST",
            data: {
                part_no: part_no,
                hsncode: hsncode,
                _token: "{{ csrf_token() }}"
            },
            success: function(response) {
                if (response.success) {
                    alert('HSN Code updated successfully!');
                } else {
                    alert('Update failed: ' + (response.message ?? 'Unknown error'));
                }
            },
            error: function(xhr) {
                alert('Server error: ' + xhr.statusText);
            }
          });
          $("#loader").hide();
        }
      });

      document.querySelectorAll('.pre-close-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();

            const subOrderType = this.getAttribute('data-sub_order_type');
            const baseUrl = this.getAttribute('href');
            // alert(baseUrl);
            if (subOrderType === 'btr') {
                Swal.fire({
                      title: 'Confirm Pre-Close',
                      text: 'Are you sure you want to pre-close this order?',
                      icon: 'warning',
                      showCancelButton: true,
                      confirmButtonText: 'Yes',
                      cancelButtonText: 'No'
                  }).then((result) => {
                    if (result.isConfirmed) {
                      Swal.fire({
                          title: 'Close BTR?',
                          text: 'Do you want to close BTR to main branch also?',
                          icon: 'question',
                          showCancelButton: true,
                          confirmButtonText: 'Yes',
                          cancelButtonText: 'No'
                      }).then((result) => {
                          if (result.isConfirmed) {
                              window.location.href = baseUrl + '/yes';
                          } else {
                              window.location.href = baseUrl + '/no';
                          }
                      });
                    }
                  });
            } else {
              Swal.fire({
                  title: 'Confirm Pre-Close',
                  text: 'Are you sure you want to pre-close this order?',
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonText: 'Yes',
                  cancelButtonText: 'No'
              }).then((result) => {
                  if (result.isConfirmed) {
                      window.location.href = baseUrl;
                  }
              });
            }
        });
      });

    });

    // Handle Add Transporter Form
    $('#addTransporterForm').on('submit', function (e) {
        e.preventDefault();

        const name = $('#newTransporterName').val().trim();
        const regId = $('#newTransporterRegId').val().trim();
        const $errorBox = $('#transporterError');

        if (!name || !regId) {
            $errorBox.removeClass('d-none').text('Please fill in both fields.');
            return;
        }

        $('#transporterSpinner').removeClass('d-none');
        $('#transporterBtnText').text('Adding...');
        $errorBox.addClass('d-none').text(''); // clear old errors

        $.ajax({
            url: "{{ url('/zoho/create-eway-transporter') }}",
            type: 'GET',
            data: {
                transporter_name: name,
                transporter_registration_id: regId
            },
            success: function (res) {
                $('#transporterSpinner').addClass('d-none');
                $('#transporterBtnText').text('Add Transporter');

                if (res.success) {
                    alert('Transporter added successfully!');
                    $('#addTransporterModal').modal('hide');
                    $('#newTransporterName').val('');
                    $('#newTransporterRegId').val('');
                    $errorBox.addClass('d-none').text('');
                    refreshTransporterList();
                } else {
                    const errorMessage = res.zoho_response?.message || 'Failed to add transporter.';
                    $errorBox.removeClass('d-none').text(errorMessage);
                }
            },
            error: function (xhr) {
                $('#transporterSpinner').addClass('d-none');
                $('#transporterBtnText').text('Add Transporter');

                let errorMsg = 'Something went wrong.';
                try {
                    const json = JSON.parse(xhr.responseText);
                    errorMsg = json?.zoho_response?.message || json?.message || errorMsg;
                } catch (e) {}

                $errorBox.removeClass('d-none').text(errorMsg);
            }
        });
    });

    function refreshTransporterList() {
        $.get('{{ route('zoho.getTransporters') }}', function (res) {
            let options = `<option value="">-- Select Transporter Name --</option>`;
            if (res.data && res.data.transporters) {
                res.data.transporters.forEach(item => {
                    // options += `<option value="${item.transporter_id}">${item.transporter_name}</option>`;
                    options += `<option value="${item.name}" data-transport_table_id="${item.id}" data-gst="${item.gstin}" data-mobile="" selected="">${item.name}</option>`;
                });
            }
            const $dropdown = $('#transport_name');
            $dropdown.html(options);

            // ✅ Re-initialize AIZ selectpicker to apply search
            AIZ.plugins.bootstrapSelect('refresh');
        });
    }


    

     

  </script>


  <script>
$(document).ready(function () {
    $('#barcodeForm').submit(function (e) {
        e.preventDefault();

        const form = $(this);
        const submitBtn = $('#generateBtn');
        const spinner = submitBtn.find('.spinner-border');
        const btnText = submitBtn.find('.btn-text');

        // Disable button and show loader
        submitBtn.prop('disabled', true);
        spinner.removeClass('d-none');
        btnText.text('Generating...');

        const baseUrl = "{{ route('print.barcode') }}";
        const query = form.serialize();
        const fullUrl = `${baseUrl}?${query}`;

        $.ajax({
            url: fullUrl,
            method: 'GET',
            success: function (response) {
                if (response.success && response.url) {
                  const link = document.createElement('a');
                  link.href = response.url;
                  link.download = 'rotated-barcode.pdf';
                  link.click();

                  submitBtn.prop('disabled', false);
                  spinner.removeClass('spinner-border');
                  btnText.text('Generate');

                    //rotateAndDownloadPDF(response.url, submitBtn, spinner, btnText);
                } else {
                    alert('❌ Not hit');
                    resetButton(submitBtn, spinner, btnText);
                }
            },
            error: function () {
                alert('❌ AJAX error');
                resetButton(submitBtn, spinner, btnText);
            }
        });
    });

    function resetButton(btn, spinner, textSpan) {
        btn.prop('disabled', false);
        spinner.addClass('d-none');
        textSpan.text('Generate');
    }

    async function rotateAndDownloadPDF(url, btn, spinner, textSpan) {
        try {
            const response = await fetch(url);
            const pdfBytes = await response.arrayBuffer();

            const pdfDoc = await PDFLib.PDFDocument.load(pdfBytes);
            const pages = pdfDoc.getPages();

            pages.forEach(page => {
                const rotation = page.getRotation().angle;
                page.setRotation(PDFLib.degrees((rotation + 90) % 360));
            });

            const rotatedBytes = await pdfDoc.save();
            const blob = new Blob([rotatedBytes], { type: 'application/pdf' });

            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'rotated-barcode.pdf';
            link.click();
        } catch (err) {
            console.error('PDF rotation failed:', err);
            alert('PDF rotation failed');
        } finally {
            resetButton(btn, spinner, textSpan);
        }
    }



    $('#barcodeModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var partNo = button.data('part_no');
        var maxQty = button.data('max_qty') || ''; // yahi tum button me bhej rahe ho

        var modal = $(this);
        modal.find('#modal_part_no').val(partNo);

        // ✅ Default "Quantity per Barcode" = remaining Order Qty
        modal.find('#modal_qty')
             .val(maxQty)            // prefill
             .attr('min', 1);        // optional safety
        if (maxQty) {
            modal.find('#modal_qty').attr('max', maxQty);
        } else {
            modal.find('#modal_qty').removeAttr('max');
        }
    });

     $('#customImporterCheckbox').on('change', function () {
        if ($(this).is(':checked')) {
            $('#customImporterFields').slideDown();
        } else {
            $('#customImporterFields').slideUp();

            // Optional: clear all fields when hidden
            $('#customImporterFields').find('input, textarea').val('');
        }
    });
    
    // $("#barcode").on("change", function() {
    //   var t = $(this).val().trim();
    //   if (!t) return;
    //   var lines = t.split("\n");
    //   var last = (lines[lines.length - 1] || "").trim();
    //   if (last.length > 7) {
    //     var partNo = last.substring(0, 7);
    //     saveScannedBarcode(partNo, last);
    //   }
    // });
});
</script>

@endsection
