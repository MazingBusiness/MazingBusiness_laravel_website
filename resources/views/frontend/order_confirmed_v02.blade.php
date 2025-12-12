@extends('frontend.layouts.app')

@section('content')

  @php
      $is_41 = $is_41 ?? false;
      $total = 0;
      $cash_and_carry_item_flag = 0;
      $cash_and_carry_item_subtotal = 0;
      $normal_item_flag = 0;
      $normal_item_subtotal = 0;
      $item_subtotal = 0;
      $dueAmount = session('dueAmount');
      $overdueAmount = session('overdueAmount');
      foreach($getOrder->orderDetails as $key => $value){
          if($value['cash_and_carry_item'] == 1 && Auth::check() && Auth::user()->credit_days > 0){
              $cash_and_carry_item_flag = 1;
              $cash_and_carry_item_subtotal += $value['price'];
          }else{
              $normal_item_flag = 1;
              $normal_item_subtotal += $value['price'];
          }
          $total += $value['price'];
          $item_subtotal += $value['price'] * $value['quantity'];
      }


      // -------------------------------- Conveince Fee ------------------------------
      $conveince_fee = 0;
      $conveince_fee_percentage = 0;
      // if($item_subtotal <= 10000){
      //     $conveince_fee = ($item_subtotal * 10)/100;
      //     $conveince_fee_percentage = 10;
      // }elseif($item_subtotal >= 10000 AND $item_subtotal <= 20000){
      //     $conveince_fee = ($item_subtotal * 7)/100;
      //     $conveince_fee_percentage = 7;
      // }elseif($item_subtotal >= 20000 AND $item_subtotal <= 30000){
      //     $conveince_fee = ($item_subtotal * 5)/100;
      //     $conveince_fee_percentage = 5;
      // }
      if($item_subtotal <= 20000){
          $conveince_fee = ($item_subtotal * 5)/100;
          $conveince_fee_percentage = 5;
      }
      $total += $conveince_fee;

      $credit_limit = Auth::user()->credit_limit;
      $current_limit = $dueAmount - $overdueAmount;
      $currentAvailableCreditLimit = $credit_limit - $current_limit;
      $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
      //-------------------------- This is for case 2 ------------------------------
      if($current_limit == 0){        
          if($total > $currentAvailableCreditLimit){
              $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
          }else{
              $exceededAmount = $overdueAmount;
          }
      }else{
          if($total > $currentAvailableCreditLimit)
          {
              $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
          }else{
              $exceededAmount = $overdueAmount;
          }
      }
    //----------------------------------------------------------------------------
    $payableAmount = $exceededAmount + $cash_and_carry_item_subtotal;
  @endphp
  <style>
    .ajax-loader {
      visibility: hidden;
      background-color: rgba(255,255,255,0.7);
      position: absolute;
      z-index: +100 !important;
      width: 100%;
      height:100%;
    }

    .ajax-loader img {
      position: relative;
      top:50%;
      left:50%;
    }
    .custom-close {
        font-size: 1.8rem; /* Larger close button */
        color: #333; /* Cross color */
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 5px;
        transition: color 0.3s ease;
    }
    
    .custom-close:hover {
        color: #ff0000; /* Change color on hover */
    }
    
    .modal-lg {
        max-width: 80%; /* Adjust modal width to 80% of screen */
    }
    
    .modal-header, .modal-body {
        padding: 20px; /* More padding for a cleaner layout */
    }
    
    .modal-body img {
        max-width: 100%; /* Ensure image is responsive */
        height: auto;
    }

    .modal-footer {
        justify-content: center; /* Center align footer buttons */
    }
  </style>
  <div class="ajax-loader">
    <img src="https://mazingbusiness.com/public/assets/img/ajax-loader.gif" class="img-responsive" />
  </div>
  <section class="pt-3 mb-2">
    <div class="container">
      <div class="row">
        <div class="col-md-10 col-lg-9 col-xl-8 mx-auto">
          <div class="row aiz-steps arrow-divider">
            <div class="col done">
              <div class="text-center text-success">
                <i class="la-2x mb-2 las la-shopping-cart"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('1. My Cart') }}</h3>
              </div>
            </div>
            <div class="col done">
              <div class="text-center text-success">
                <i class="la-2x mb-2 las la-map"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('2. Shipping Company') }}</h3>
              </div>
            </div>
            <!-- <div class="col done">
              <div class="text-center text-success">
                <i class="la-2x mb-2 las la-truck"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('3. Delivery info') }}</h3>
              </div>
            </div> -->
            <div class="col done">
              <div class="text-center text-success">
                <i class="la-2x mb-2 las la-credit-card"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('3. Payment') }}</h3>
              </div>
            </div>
            <div class="col active">
              <div class="text-center text-primary">
                <i class="la-2x mb-2 las la-check-circle"></i>
                <h3 class="fs-12 fw-600 d-none d-lg-block">{{ translate('4. Confirmation') }}</h3>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <section class="py-4">
    <div class="container text-left">
      <div class="row">
        <div class="col-xl-8 mx-auto">
          @php
            $first_order = $combined_order->orders->first();
          @endphp
          <div class="text-center py-4 mb-4">
            <i class="la la-check-circle la-3x text-success mb-3"></i>
            <h1 class="h3 mb-3 fw-600">{{ translate('Thank You for Your Order!') }}</h1>
            <p class="opacity-70 font-italic">{{ translate('A copy or your order summary has been sent to') }}
              {{ json_decode($first_order->shipping_address)->email }}</p>
            <!-- @if(Auth::user()->id == 24185 AND $qrCodeUrl!= "")
              <div id="divPayment">
                <p>Pay your amount by scan the QR CODE</p>
                <img src="{{$qrCodeUrl}}" alt="UPI QR Code" /></br>
                <p>OR</p>
                <p>Pay your amount by enter your @upi ID</p>
                <p id="upiError" style="color: red; font-size: 20px;"></p>
                <div style="display: flex; justify-content: center; align-items: center;">
                  <form class="form-inline" method="POST" action="">
                    @csrf
                    <div class="form-group mb-0">
                      <input type="text" class="form-control" placeholder="@uipId" name="upi_id" id="upi_id" required="" style="width:300px;">
                      <input type="hidden" class="form-control" name="bill_number" id="bill_number" required="" value="{{encrypt($orderCode)}}">
                    </div>
                    <button type="button" class="btn btn-primary" id="btnVerifyAndPay">Verify and Pay</button>
                  </form>
                </div>
              </div>
              <p id="upiSuccess" style="color: #2aa705;"></p>
              <p id="upiProgress" style="color: #e89507;"></p>
            @endif -->
          </div>
          <div class="mb-4 bg-white p-4 rounded shadow-sm">
            <h5 class="fw-600 mb-3 fs-17 pb-2">{{ translate('Order Summary') }}</h5>
            <div class="row">
              <div class="col-md-6">
                <table class="table">
                  <tr>
                    <td class="w-50 fw-600">{{ translate('Order date') }}:</td>
                    <td>{{ date('d-m-Y H:i A', $first_order->date) }}</td>
                  </tr>
                  <tr>
                    <td class="w-50 fw-600">{{ translate('Name') }}:</td>
                    <td>{{ json_decode($first_order->shipping_address)->name }}</td>
                  </tr>
                  <tr>
                    <td class="w-50 fw-600">{{ translate('Email') }}:</td>
                    <td>{{ json_decode($first_order->shipping_address)->email }}</td>
                  </tr>
                  <tr>
                    <td class="w-50 fw-600">{{ translate('Shipping address') }}:</td>
                    <td>{{ json_decode($first_order->shipping_address)->address }},
                      {{ json_decode($first_order->shipping_address)->city }},
                      {{ json_decode($first_order->shipping_address)->country }}</td>
                  </tr>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table">
                  <tr>
                    <td class="w-50 fw-600">{{ translate('Order status') }}:</td>
                    <td>{{ translate(ucfirst(str_replace('_', ' ', $first_order->delivery_status))) }}</td>
                  </tr>
                  <tr>
                    <td class="w-50 fw-600">{{ translate('Total order amount') }}:</td>
                    <td>{{ single_price($total)}}</td>
                  </tr>
                  <tr>
                    <td class="w-50 fw-600">{{ translate('Shipping') }}:</td>
                    <td>{{ translate('Flat shipping rate') }}</td>
                  </tr>
                  <tr>
                    <?php /* <td class="w-50 fw-600">{{ translate('Payment method') }}:</td>
                    <td>{{ translate(ucfirst(str_replace('_', ' ', $first_order->payment_type))) }}</td> */ ?>
                  </tr>
                </table>
              </div>
            </div>
          </div>
          @foreach ($combined_order->orders as $order)
            <div class="card shadow-sm border-0 rounded">
              <div class="card-body">
                <div class="text-center py-4 mb-4">
                  <h2 class="h5">{{ translate('Order Code:') }} <span class="fw-700 text-primary">{{ $order->code }}</span></h2>
                </div>
                <div>
                  <h5 class="fw-600 mb-3 fs-17 pb-2">{{ translate('Order Details') }}</h5>
                  <div>
                    <table class="table table-responsive-md">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th width="30%">{{ translate('Product') }}</th>
                          <th>{{ translate('Variation') }}</th>
                          <th>{{ translate('Quantity') }}</th>
                          <th>{{ translate('Delivery Type') }}</th>
                          <th class="text-right">{{ translate('Price') }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach ($order->orderDetails as $key => $orderDetail)
                          <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>
                              @if ($orderDetail->product != null)
                                <a href="{{ route('product', $orderDetail->product->slug) }}" target="_blank"
                                  class="text-reset">
                                  {{ $orderDetail->product->getTranslation('name') }}
                                  @php
                                    if ($orderDetail->combo_id != null) {
                                        $combo = \App\ComboProduct::findOrFail($orderDetail->combo_id);

                                        echo '(' . $combo->combo_title . ')';
                                    }
                                  @endphp
                                </a>
                                @if ($orderDetail->cash_and_carry_item != '0')
                                  <div><span class="badge badge-inline badge-danger">No Credit Item</span></div>
                                @endif
                              @else
                                <strong>{{ translate('Product Unavailable') }}</strong>
                              @endif
                            </td>
                            <td>
                              {{ $orderDetail->variation }}
                            </td>
                            <td>
                              {{ $orderDetail->quantity }}
                            </td>
                            <td>
                              @if ($order->shipping_type != null && $order->shipping_type == 'home_delivery')
                                {{ translate('Home Delivery') }}
                              @elseif ($order->shipping_type != null && $order->shipping_type == 'carrier')
                                {{ translate('Carrier') }}
                              @elseif ($order->shipping_type == 'pickup_point')
                                @if ($order->pickup_point != null)
                                  {{ $order->pickup_point->getTranslation('name') }} ({{ translate('Pickip Point') }})
                                @endif
                              @endif
                            </td>
                            <td class="text-right">{{ single_price($orderDetail->price) }}</td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                  <div class="row">
                    <div class="col-xl-5 col-md-6 ml-auto mr-0">
                      <table class="table ">
                        <tbody>
                          <tr>
                            <th>{{ translate('Subtotal') }}</th>
                            <td class="text-right">
                              <span class="fw-600">{{ single_price($order->orderDetails->sum('price')) }}</span>
                            </td>
                          </tr>
                          <tr>
                            <th>{{ translate('Shipping') }}</th>
                            <td class="text-right">
                              <span
                                class="font-italic">{{ single_price($order->orderDetails->sum('shipping_cost')) }}</span>
                            </td>
                          </tr>
                          <tr>
                            <th>{{ translate('Tax') }}</th>
                            <td class="text-right">
                              <span class="font-italic">{{ single_price($order->orderDetails->sum('tax')) }}</span>
                            </td>
                          </tr>
                          <tr>
                            <th>{{ translate('Coupon Discount') }}</th>
                            <td class="text-right">
                              <span class="font-italic">{{ single_price($order->coupon_discount) }}</span>
                            </td>
                          </tr>
                          <tr>
                            <th>{{ translate('Packing and forwarding') }}</th>
                            <td class="text-right">
                              <span class="font-italic">{{ single_price($conveince_fee) }}</span>
                            </td>
                          </tr>
                          <tr>
                            <th><span class="fw-600">{{ translate('Total') }}</span></th>
                            <td class="text-right">
                              <strong><span>{{ single_price($total) }}</span></strong>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
    @if(!$is_41 && $qrCodeUrl!= "")
      <!-- Modal -->
      <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true" style="z-index:99999;">
        <div class="modal-dialog modal-lg"> <!-- Added modal-lg for wide modal -->
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="paymentModalLabel">Payment Information</h5>
              <button type="button" class="btn-close custom-close" data-bs-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div id="divPayment" class="text-center">
                <div class="row align-items-center">                  
                  <div class="col-md-4">                    
                    <p>Pay your amount by scanning the QR CODE</p>
                    <img src="{{ $qrCodeUrl }}" alt="UPI QR Code" class="img-fluid" style="margin-top: -20px;" /><br> <!-- img-fluid for responsive image -->
                    <p style="font-size: 14px; color: #afabab;">(* This QR Code is valid for next 24 hrs.)</p>
                    <p>OR</p>
                    <p>Pay your amount by entering your @upi ID</p>
                    <p id="upiError" style="color: red; font-size: 20px;"></p>
                    <div class="d-flex justify-content-center align-items-center">
                      <form class="form-inline" method="POST" action="">
                        @csrf
                        <div class="form-group mb-0">
                          <input type="text" class="form-control" placeholder="@upiId" name="upi_id" id="upi_id" required="" style="width:239px;">
                          <input type="hidden" class="form-control" name="bill_number" id="bill_number" required="" value="{{ encrypt($orderCode) }}">
                        </div>
                        <button type="button" class="btn btn-primary mt-2" id="btnVerifyAndPay" style="margin-bottom:10px;">Verify and Pay</button> <!-- Added mt-2 for margin-top -->
                      </form>
                    </div>
                  </div>
                  <div class="col-md-4">
                  <table style="border-collapse: collapse; width: 100%;" border="0">
                    <tr>
                      <td style="text-align: left;"><strong>Bank Name:</strong></td>
                      <td style="text-align: left; padding-left: 5px;">ICICI BANK</td>
                    </tr>
                    <tr>
                      <td style="text-align: left;"><strong>ACCOUNT NAME:</strong></td>
                      <td style="text-align: left; padding-left: 5px;">ACE TOOLS PVT LTD</td>
                    </tr>
                    <tr style="text-align: left;">
                      <td><strong>A/C NO:</strong></td>
                      <td style="text-align: left; padding-left: 5px;">235605001202</td>
                    </tr>
                    <tr>
                      <td style="text-align: left;"><strong>IFSC CODE:</strong></td>
                      <td style="text-align: left; padding-left: 5px;">ICIC0002356</td>
                    </tr>
                  </table>                    
                  </div>
                  <div class="col-md-4 text-right">
                    <table style="border-collapse: collapse; width: 100%;" border="0">
                      <tr>
                        <td><strong>Payment Summary :</strong></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td class="text-left"><strong>Payable Amount:</strong></td>
                        <td class="text-right">{{'₹'.$payableAmount}}</td>
                      </tr>
                      @if($cash_and_carry_item_flag == 1)
                        <tr>
                          <td class="text-left"><strong>No Credit Item Subtotal:</strong></td>
                          <td class="text-right">{{'₹'.$cash_and_carry_item_subtotal}}</td>
                        </tr>
                      @endif
                      @if($normal_item_flag == 1)
                        <tr>
                          <td class="text-left"><strong>Others Item Subtotal:</strong></td>
                          <td class="text-right">{{'₹'.$normal_item_subtotal}}</td>
                        </tr>
                      @endif
                      @if($conveince_fee > 0)
                        <tr>
                          <td class="text-left"><strong>Conveince fee:</strong></td>
                          <td class="text-right">{{ format_price_in_rs($conveince_fee) }}</td>
                        </tr>
                      @endif
                      @if($overdueAmount > 0)
                        <tr>
                          <td class="text-left"><strong>Overdue Amount:</strong></td>
                          <td class="text-right">{{'₹'.$overdueAmount}}</td>
                        </tr>
                      @endif
                      @if($exceededAmount > 0)
                        @if($dueAmount - $overdueAmount != 0)
                          <tr>
                            <td class="text-left"><strong>Credit limit Exceeded Amount:</strong></td>
                            <td class="text-right">{{'₹'.$exceededAmount}}</td>
                          </tr>
                        @endif
                      @endif
                      <tr>
                        <td colspan="2"><hr></td>
                      </tr>
                      {{-- <tr>
                        <td class="text-left"><strong>Total:</strong></td>
                        <td class="text-right">{{$paymentAmount}}</td>
                      </tr> --}}
                    </table>
                  </div>
                </div>
              </div>
              <p id="upiSuccess" style="color: #2aa705;"></p>
              <p id="upiProgress" style="color: #e89507;"></p>
            </div>
            <div class="modal-footer text-left">
              <!-- Optional footer content -->
              <p style="font-size: 14px; color: #afabab;">*NOTE : Statement will update once in 24 hrs.</p>
            </div>
          </div>
        </div>
      </div>

    @endif
  </section>
  @if(!$is_41 && $qrCodeUrl!= "")
    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JS (required for modals) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      $(document).ready(function() {
        // Check if the modal exists in the DOM
        if ($('#paymentModal').length) {
            $('#paymentModal').modal('show'); // Show the modal on page load
        }
        // Define merchantTranId from your backend or assign it dynamically
        var merchantTranId = '{{ $qrmerchantTranId }}';
        // Call the function immediately on page load
        getQrPaymentSuccessStatus(merchantTranId);
        // Start polling every 5 seconds
        var intervalId = setInterval(function() {
            getQrPaymentSuccessStatus(merchantTranId, intervalId); // Pass intervalId to stop polling later
        }, 5000); // 5000 milliseconds = 5 seconds

        $('#btnVerifyAndPay').click(function() {
            var upi_id = $('#upi_id').val(); // Get the UPI ID input value
            var bill_number = $('#bill_number').val(); // Get the bill number
            $('#upiError').empty(); // Clear any previous errors
            clearInterval(intervalId);
            if (upi_id != "") {
                $.ajax({
                    url: '{{ route("verifyAndPay") }}',
                    type: 'POST',
                    beforeSend: function() {
                        $('#loaderDiv').css("visibility", "visible");
                    },
                    data: { 
                        upi_id: upi_id, 
                        bill_number: bill_number,
                        _token: '{{ csrf_token() }}'
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log(response); 
                        if (response.status === 'Error') {
                            $('#upiError').append(response.message);
                        } else if (response.status === 'Success') {
                            // alert('Payment successful!');
                            $('#upiProgress').empty();
                            $('#upiSuccess').empty();
                            $('#upiProgress').append('<h1 class="h3 mb-3 fw-600">'+response.message+'</h1>');
                            if(response.merchantTranId != 0){
                              // Start polling the server every 5 seconds
                              var intervalId = setInterval(function() {
                                  getPaymentSuccessStatus(response.merchantTranId, intervalId); // Pass intervalId to stop polling
                              }, 5000); // 5000 milliseconds = 5 seconds 
                            }                   
                        } else {
                            $('#upiError').append('Unexpected response from server. Please try again.');
                        }
                    },
                    complete: function() {
                        $('#loaderDiv').css("visibility", "hidden");
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                        $('#upiError').append('An error occurred while processing your request. Please try again.');
                    }
                });
            } else {
                alert('Please enter your UPI ID.');
            }
        });
      });

      function getQrPaymentSuccessStatus(merchantTranId,intervalId){
        $.ajax({
            url: '{{ route("checkPaymentStatus") }}', // Replace with your actual payment status endpoint
            type: 'POST',
            data: { 
              merchantTranId: merchantTranId,
              _token: '{{ csrf_token() }}'
            },
            dataType: 'json',
            success: function(response) {
                console.log('Checking payment status:', response);                      
                // Check if payment is successful (adjust this logic based on the actual response)
                if (response.status === 'Success') {
                    $('#upiProgress').empty();
                    $('#upiSuccess').empty();
                    $('#upiSuccess').append('<i class="la la-check-circle la-3x text-success mb-3"></i><h1 class="h3 mb-3 fw-600">Payment Success!</h1>');
                    // Stop the polling by clearing the interval
                    clearInterval(intervalId);
                    $('#divPayment').hide();

                    // Hide the paymentModal after 5 seconds
                    setTimeout(function() {
                        $('#paymentModal').modal('hide');
                    }, 5000); // 5000 ms = 5 seconds
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
      }
      
      function getPaymentSuccessStatus(merchantTranId,intervalId){
        $.ajax({
            url: '{{ route("checkPaymentStatus") }}', // Replace with your actual payment status endpoint
            type: 'POST',
            data: { 
              merchantTranId: merchantTranId,
              _token: '{{ csrf_token() }}'
            },
            dataType: 'json',
            success: function(response) {
                console.log('Checking payment status:', response);                      
                // Check if payment is successful (adjust this logic based on the actual response)
                if (response.status === 'Success') {
                    $('#upiProgress').empty();
                    $('#upiSuccess').empty();
                    $('#upiSuccess').append('<i class="la la-check-circle la-3x text-success mb-3"></i><h1 class="h3 mb-3 fw-600">Payment Success!</h1>');
                    // Stop the polling by clearing the interval
                    clearInterval(intervalId);
                    $('#divPayment').hide();
                }
                if (response.status === 'FAILURE') {
                    $('#upiProgress').empty();
                    $('#upiSuccess').empty();
                    $('#upiProgress').append('<i class="fa fa-exclamation-triangle fa-3x text-warning mb-3" aria-hidden="true"></i><h1 class="h3 mb-3 fw-600" style="color: #e89507; font-size: 30px; text-align: center;">Payment Failure!</h1>');
                    // Stop the polling by clearing the interval
                    clearInterval(intervalId);
                    $('#divPayment').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
            }
        });
      }
    </script>
  @endif
@endsection
