@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="h3">Review Purchase Order</h1>
    @if (session('status'))
       <span style="color:green;">{{ session('status') }}</span> 
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
  </div>

  <div class="card">
    <div class="card-body">
      @if($orders->isEmpty())
        <div class="alert alert-warning">
          No orders found for the selected IDs.
        </div>
      @else
        <form method="POST" action="{{ route('purchase-order.save') }}">
          @csrf
<!-- Warehouse Selection Dropdown -->
<div class="form-group row">
    <label for="warehouse" class="col-md-2 col-form-label">
        Select Material Warehouse <span style="color: red;">*</span>
    </label>
    <div class="col-md-4">
        <select id="warehouse" name="warehouse_id" class="form-control" required>
            <option value="" disabled selected>Select a Warehouse</option> <!-- Placeholder option -->
            @foreach($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
            @endforeach
        </select>
    </div>
     <label for="seller" class="col-md-2 col-form-label">
        Select Seller
         <!-- <span style="color: red;">*</span> -->
    </label>
   <div class="col-md-4">
    <select id="seller" name="seller_name" class="form-control">
        <option value="" disabled>Select a Seller</option> <!-- Placeholder option -->
        @foreach($all_sellers as $seller)
            <option value="{{ $seller->seller_name }}"
                @if(isset($current_seller_name) && $seller->seller_name == $current_seller_name) selected @endif>
                {{ $seller->seller_name }}
            </option>
        @endforeach
    </select>
</div>
</div>
          <!-- Seller Information Fields -->
          <!-- <div class="form-group row">
            <div class="col-md-6">
              <label for="seller_name" class="col-form-label">Seller Name</label>
              <input type="text" id="seller_name" name="seller_info[seller_name]" value="{{ $orders->first()->seller_company_name }}" class="form-control">
            </div>

            <div class="col-md-6">
              <label for="seller_address" class="col-form-label">Seller Address</label>
              <input type="text" id="seller_address" name="seller_info[seller_address]" value="{{ $orders->first()->seller_address }}" class="form-control">
            </div>
          </div>

          <div class="form-group row">
            <div class="col-md-6">
              <label for="seller_gstin" class="col-form-label">Seller GSTIN</label>
              <input type="text" id="seller_gstin" name="seller_info[seller_gstin]" value="{{ $orders->first()->seller_gstin }}" class="form-control">
            </div>
            <div class="col-md-6">
              <label for="seller_phone" class="col-form-label">Seller Phone</label>
              <input type="text" id="seller_phone" name="seller_info[seller_phone]" value="{{ $orders->first()->seller_phone }}" class="form-control">
            </div>
          </div> -->
          <!-- Seller Information Fields -->
          <div class="form-group row">
              <div class="col-md-6">
                  <label for="seller_name" class="col-form-label">Seller Name</label>
                  <input type="text" id="seller_name" name="seller_info[seller_name]" value="{{ $orders->first()->seller_company_name }}" class="form-control">
              </div>
              <div class="col-md-6">
                  <label for="seller_address" class="col-form-label">Seller Address</label>
                  <input type="text" id="seller_address" name="seller_info[seller_address]" value="{{ $orders->first()->seller_address }}" class="form-control">
              </div>
          </div>

          <div class="form-group row">
              <div class="col-md-6">
                  <label for="seller_gstin" class="col-form-label">Seller GSTIN</label>
                  <input type="text" id="seller_gstin" name="seller_info[seller_gstin]" value="{{ $orders->first()->seller_gstin }}" class="form-control">
              </div>
              <div class="col-md-6">
                  <label for="seller_phone" class="col-form-label">Seller Phone</label>
                  <input type="text" id="seller_phone" name="seller_info[seller_phone]" value="{{ $orders->first()->seller_phone }}" class="form-control">
              </div>
          </div>


          <table class="table aiz-table mb-0">
            <thead>
              <tr>
                <th>Part No.</th>
                <th>Order No(s)</th>
                <th>Order Date</th> <!-- Added column for Order Date -->
                <th>Product Name</th>
                <th>Purchase Price</th>
                <th>To Be Ordered</th>
                <th>Quantity</th>
              </tr>
            </thead>
            <tbody>
            @foreach($orders as $order)
              <tr>
                <td>{{ $order->part_no }}</td>
                <td>{{ $order->order_no }}</td> <!-- Display combined order numbers -->
                <td>{{ $order->order_date }}</td> <!-- Display order dates -->
                <td>{{ $order->item }}</td>
                <td>
                  <!-- Editable input for Purchase Price -->
                  <input type="number" name="orders[{{ $order->part_no }}][purchase_price]" value="{{ $order->purchase_price }}" class="form-control">
                </td>

                <td>{{ $order->total_quantity }}</td>
                <td>
                  <!-- Combined quantity for the same part_no -->
                  <input type="number" name="orders[{{ $order->part_no }}][quantity]" value="{{ $order->total_quantity }}" class="form-control">
                </td>
              </tr>
              <!-- Include hidden inputs for part_no, seller_id, and order_no -->
              <input type="hidden" name="orders[{{ $order->part_no }}][seller_id]" value="{{ $order->seller_id }}">
              <input type="hidden" name="orders[{{ $order->part_no }}][part_no]" value="{{ $order->part_no }}">
              <input type="hidden" name="orders[{{ $order->part_no }}][order_no]" value="{{ $order->order_no }}">
              <input type="hidden" name="orders[{{ $order->part_no }}][order_date]" value="{{ $order->order_date }}">
              <input type="hidden" name="orders[{{ $order->part_no }}][age]" value="{{ $order->age }}">
            @endforeach
            </tbody>
          </table>

          <div class="mt-3">
            <button type="submit" class="btn btn-primary">Save and Continue</button>
          </div>
        </form>
      @endif
    </div>
  </div>
@endsection

@section('script')
<script type="text/javascript">
    $(document).ready(function() {
        $('#seller').change(function() {
            var sellerId = $(this).val();

            if(sellerId) {
                $.ajax({
                    url: '/get-seller-info/' + sellerId,
                    type: "GET",
                    dataType: "json",
                    success: function(data) {
                        if(data) {
                            // Populate the fields with the returned data
                            $('#seller_name').val(data.seller_name);
                            $('#seller_address').val(data.seller_address);
                            $('#seller_gstin').val(data.gstin);
                            $('#seller_phone').val(data.seller_phone);
                        }
                    }
                });
            } else {
                // Clear the fields if no seller is selected
                $('#seller_name').val('');
                $('#seller_address').val('');
                $('#seller_gstin').val('');
                $('#seller_phone').val('');
            }
        });
    });
</script>

@endsection

