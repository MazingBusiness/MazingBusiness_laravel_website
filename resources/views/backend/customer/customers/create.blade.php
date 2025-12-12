@extends('backend.layouts.app')
  
@section('content')
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
  </style>
  <div class="ajax-loader">
    <img src="{{ url('https://mazingbusiness.com/public/assets/img/ajax-loader.gif') }}" class="img-responsive" />
  </div>
  <div class="row">
    <div class="col-lg-8 mx-auto">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0 h6">{{ translate('Add Customer') }}</h5>
        </div>
        <!-- Display the success message -->
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <!-- Display the error message -->
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        <form class="form-horizontal" action="{{ route('store') }}" method="POST" enctype="multipart/form-data" name="frmCreateCustomer" id="frmCreateCustomer">
          @csrf
          <div class="card-body">
            <div class="form-group row">
              <div class="col-md-3">
                <label class="col-from-label">{{ translate('Warehouse') }} <span class="text-danger">*</span></label>
              </div>
              <div class="col-md-9">
                <select class="form-control aiz-selectpicker" data-live-search="true" data-placeholder="{{ translate('Select the Warehouse') }}" id="warehouse_id" disabled>
                    <option value="">{{ translate('Select the Warehouse') }}</option>
                    @foreach (\App\Models\Warehouse::get() as $key => $warehouse)
                        <option value="{{ $warehouse->id }}" @if($user->warehouse_id == $warehouse->id) selected @endif>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="warehouse_id" value="{{ $user->warehouse_id }}">
                @if(Auth::user()->user_type == 'staff')
                  <input type="hidden" id="staff_user" value="{{Auth::user()->id}}">
                @endif
              </div>
            </div>
            <div class="form-group">
              <div class="row">
                <div class="col-sm-3 control-label">
                  <label>{{ translate('Manager') }} <span class="text-danger">*</span></label>
                </div>
                <div class="col-sm-9">                 
                  <select class="form-control aiz-selectpicker" data-live-search="true" id="manager_id" required disabled>
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                  </select>
                  <input type="hidden" name="manager_id" value="{{ $user->id }}">
                </div>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="gstin">{{ translate('GSTIN') }}</label>
              <div class="col-sm-9">
                <input type="text" placeholder="{{ translate('GSTIN') }}" id="gstin" name="gstin"
                  class="form-control">
                  <span id="gstin_err" class="text-danger"></span>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="aadhar_card">{{ translate('Aadhar Card No.') }}</label>
              <div class="col-sm-9">
                <input type="number" placeholder="{{ translate('Aadhar Card No.') }}" id="aadhar_card"
                  name="aadhar_card" class="form-control" min="10">
                <span id="aadhar_err" class="text-danger"></span>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="name">{{ translate('Party Name') }} <span
                  class="text-danger">*</span></label>
              <div class="col-sm-9">
                <input type="text" placeholder="{{ translate('Party Name') }}" id="name" name="name"
                  class="form-control" required>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="company_name">{{ translate('Company Name') }} <span
                  class="text-danger">*</span></label>
              <div class="col-sm-9">
                <input type="text" placeholder="{{ translate('Company Name') }}" id="company_name" name="company_name"
                  class="form-control" required>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="email">{{ translate('Email') }}<span
                  class="text-danger">*</span></label>
              <div class="col-sm-9">
                <input type="email" placeholder="{{ translate('Email') }}" id="email" name="email"
                  class="form-control">
                  <span id="email_err" class="text-danger"></span> 
              </div>
            </div>
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="mobile">{{ translate('Phone') }} <span
                  class="text-danger">*</span></label>
              <div class="col-sm-9">
                <input type="number" placeholder="{{ translate('Phone') }}" id="mobile" name="mobile"
                  class="form-control" required>
                <span id="phone_err" class="text-danger"></span>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="address">{{ translate('Address') }}</label>
              <div class="col-sm-9">
                <textarea placeholder="{{ translate('Address') }}" id="address" name="address" class="form-control"></textarea>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="address2">{{ translate('Address 2') }}</label>
              <div class="col-sm-9">
                <textarea placeholder="{{ translate('Address 2') }}" id="address2" name="address2" class="form-control"></textarea>
              </div>
            </div>
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="pincode">{{ translate('City') }}<span
              class="text-danger">*</span></label>
              <div class="col-sm-9">
                <input type="text" placeholder="{{ translate('City') }}" id="city" name="city"
                  class="form-control" required>
                <span id="city_err" class="text-danger"></span>
              </div>
            </div>

            <div class="form-group row">
              <label class="col-sm-3 col-form-label" for="state">{{ translate('State') }}<span class="text-danger">*</span></label>
              <div class="col-sm-9">
                  <select class="form-control{{ $errors->has('state') ? ' is-invalid' : '' }}" id="state" name="state" required>
                      <option value="" disabled selected>{{ translate('Select State') }}</option>
                      @foreach ($states as $state)
                          <option value="{{ $state->id }}" {{ old('state') == $state->name ? 'selected' : '' }}>
                              {{ $state->name }}
                          </option>
                      @endforeach
                  </select>
                  <span id="state_err" class="text-danger"></span>
                  @if ($errors->has('state'))
                      <span class="invalid-feedback" role="alert">
                          <strong>{{ $errors->first('state') }}</strong>
                      </span>
                  @endif
              </div>
          </div>

            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="pincode">{{ translate('Pincode') }}</label>
              <div class="col-sm-9">
                <input type="text" placeholder="{{ translate('Pincode') }}" id="pincode" name="pincode"
                  class="form-control">
                <span id="postal_code_err" class="text-danger"></span>
              </div>
            </div>
            
            <div class="form-group">
              <div class=" row">
                <label class="col-sm-3 control-label" for="credit_days">{{ translate('Credit Days') }}</label>
                <div class="col-sm-9">
                  <input type="number" min="0" value="0" placeholder="{{ translate('Credit Days') }}"
                    id="credit_days" name="credit_days" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="form-group">
              <div class=" row">
                <label class="col-sm-3 control-label" for="credit_limit">{{ translate('Credit Limit') }}</label>
                <div class="col-sm-9">
                  <input type="number" min="0" value="0" placeholder="{{ translate('Credit Limit') }}"
                    id="credit_limit" name="credit_limit" class="form-control" required>
                </div>
              </div>
            </div>
            <!-- <div class="form-group">
              <div class=" row">
                <label class="col-sm-3 control-label" for="credit_balance">{{ translate('Credit Balance') }}</label>
                <div class="col-sm-9">
                  <input type="number" min="0" value="0"
                    placeholder="{{ translate('Credit Balance') }}" id="credit_balance" name="credit_balance"
                    class="form-control" required>
                </div>
              </div>
            </div> -->
            <div class="form-group row">
              <label class="col-sm-3 col-from-label" for="discount">{{ translate('Discount') }} <span
                  class="text-danger">*</span></label>
              <div class="col-sm-9">
                <input type="number" placeholder="{{ translate('Discount') }}" id="discount" name="discount"
                  class="form-control"  required>
                  <span id="discount_err" class="text-danger"></span>
              </div>
            </div>
            <input type="hidden" id="gst_data" name="gst_data" class="form-control" value="">
            <div class="form-group mb-0 text-right">
              <button type="button" id="saveBtn" class="btn btn-sm btn-primary">{{ translate('Save') }}</button>
            </div>
          </div>
        </form>

      </div>
    </div>
  </div>
@endsection

@section('script')
  <script type="text/javascript">
    var products = null;

    $('#saveBtn').click(function() {
        var discount = $('#discount').val().trim();
        var mobile = $('#mobile').val().trim();
        var emailValue = $('#email').val().trim();
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (emailValue == "") {              
          $('#email_err').html('Please enter a valid email address.');
        }else if(!emailRegex.test(emailValue)) {
            emailValue = "";
            $('#email_err').html('Please enter a email address.');
        }else if(emailRegex.test(emailValue)) {
          check_email(emailValue);
        }else if(mobile == ""){
          $('#phone_err').html('Please enter the valid phone number!');
        }else if(discount == ""){
          $('#discount_err').html('Please enter the discount!');
        }else if(discount > 24){
          $('#discount_err').html('Discount no more than 24!');
        }else{
          $('#frmCreateCustomer').submit();
        } 
    });

    $(document).on('change', '[name=warehouse_id]', function() {
      var warehouse_id = $(this).val();
      var staff_user_id = ($('#staff_user').val()) ? $('#staff_user').val() : 0;
      get_managers(warehouse_id, staff_user_id);
    });

    function get_managers(warehouse_id, staff_user_id) {
      $('[name="manager"]').html("");
      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: "{{ route('get-managers') }}",
        type: 'POST',
        data: {
          warehouse_id: warehouse_id,
          staff_user_id:staff_user_id
        },
        success: function(response) {
          var obj = JSON.parse(response);
          if (obj != '') {
            $('[name="manager_id"]').html(obj);
            AIZ.plugins.bootstrapSelect('refresh');
          }
        }
      });
    }

    $(document).on('keyup', '[name=gstin]', function() {
      var gstin = $(this).val();
      if (gstin.length >= 15) {
        get_gstin_data(gstin);
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
          if(response){
            if (response.hasOwnProperty('error')) {
              // $('#gstin_err').html(response.message);
              $('#gstin_err').html('Invalid GST');
              $('#company_name').val('');
              $('#name').val('');
              $('#address').val('');
              $('#address2').val('');
              $('#pincode').val('');
              $('#saveBtn').prop('disabled', true);
            } else {
              $('#gstin_err').html('');
              $.ajax({
                headers: {
                  'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '{{ route("check-gstin-exists") }}',
                type: 'POST',
                data: { gstin: gstin },
                dataType: 'json',
                success: function (res) {
                  if (res.hasOwnProperty('error')) {
                    $('#gstin_err').html(res.message);
                    //return;
                  }else{
                    $('#company_name').val(response.taxpayerInfo.tradeNam);
                    $('#name').val(response.taxpayerInfo.lgnm);
                    $('#gst_data').val(JSON.stringify(response));
                    var address = (response.taxpayerInfo.pradr.addr.bnm + ', ' +response.taxpayerInfo.pradr.addr.st + ', ' + response.taxpayerInfo.pradr.addr.loc).replace(/^[, ]+|[, ]+$/g, '');
                    var address2 = (response.taxpayerInfo.pradr.addr.bno + ', ' +  response.taxpayerInfo.pradr.addr.dst).replace(/^[, ]+|[, ]+$/g, '');
                    $('#address').val(address);
                    $('#address2').val(address2);
                    $('#pincode').val(response.taxpayerInfo.pradr.addr.pncd);
                    $('#saveBtn').prop('disabled', false);
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

    //Pincode Validation
    $(document).on('keyup', '[name=pincode]', function() {
      var postal_code = $(this).val();
      if (postal_code.length == 6) {
        var valid = postalCodeValidation(postal_code);
        $('#createAccountBtn').prop('disabled', false);
        if(valid == true){
          $('#postal_code_err').html('');
          // check_postal_code(postal_code);          
        }else{
          $('#postal_code_err').html('Pincode is not valid');
          $('#createAccountBtn').prop('disabled', true);
        }
        
      }else {
        $('#postal_code_err').html('Pincode must be 6 digits'); // Show error for length
        $('#createAccountBtn').prop('disabled', true); // Disable the button if not 6 digits
      }
    });

    function postalCodeValidation(pincode) {
      var regex = /^[0-9]{6}$/;
      if (regex.test(pincode)) {
        return true;
      } else {
        return false;
      }
    }

    function check_postal_code(postal_code) {
      $.ajax({
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: '{{ route("checkPostalCode") }}',
            type: 'POST',
            beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
            data: { postal_code: postal_code },
            dataType: 'json',
            success: function (res) {
              if (res.hasOwnProperty('error')) {
                $('#postal_code_err').html(res.message);
                $('#saveBtn').prop('disabled', true);
              }else{
                $('#saveBtn').prop('disabled', false);
              }
            },
            complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
                // Optionally handle errors
            }
        });
    }

    // Email
    $(document).on('keyup', '[name=email]', function() {
      $('#email_err').html('');
    });

    function check_email(email) {
      $('#email_err_flag').val(0);
      $.ajax({
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          },
          url: '{{ route("checkEmail") }}',
          type: 'POST',
          beforeSend: function(){
            $('.ajax-loader').css("visibility", "visible");
          },
          data: { email: email },
          dataType: 'json',
          success: function (res) {
            if (res.hasOwnProperty('error')) {
              $('#email_err').html(res.message);
            }else{
              var discount = $('#discount').val().trim();
              var mobile = $('#mobile').val().trim();
              if(mobile == ""){
                $('#phone_err').html('Please enter the valid phone number!');
              }else if(discount == ""){
                $('#discount_err').html('Please enter the discount!');
              }else if(discount > 24){
                $('#discount_err').html('Discount no more than 24!');
              }else{
                $('#frmCreateCustomer').submit();
              } 
            }
          },
          complete: function(){
            $('.ajax-loader').css("visibility", "hidden");
          },
          error: function (xhr, status, error) {
              console.error(xhr.responseText);
              // Optionally handle errors
          }
      });
    }

    //Phone
    $(document).on('keyup', '[name=mobile]', function() {
      var mobile = $(this).val();
      if (mobile.length >= 10) {
        var valid = phoneValidation(mobile);
        if(valid == true){
          $('#phone_err').html('');
          check_phone_number(mobile);          
        }else{
          $('#phone_err').html('Phone number is not valid');
        }
        
      }
    });
    function phoneValidation(phoneNumber) {
      var regex = /^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/;
      if (regex.test(phoneNumber)) {
        return true;
      } else {
        return false;
      }
    }
    function check_phone_number(mobile) {
      $.ajax({
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: '{{ route("checkPhoneNumber") }}',
            type: 'POST',
            beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
            data: { mobile: mobile },
            dataType: 'json',
            success: function (res) {
              if (res.hasOwnProperty('error')) {
                $('#phone_err').html(res.message);
                //return;
              }
            },
            complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
                // Optionally handle errors
            }
        });
    }

    //Aadhar
    $(document).on('keyup', '[name=aadhar_card]', function() {
      var aadhar_card = $(this).val();
      if (aadhar_card.length == 12) {
        check_aadhar_number(aadhar_card);
      }
    });

    function check_aadhar_number(aadhar_card) {
          $.ajax({
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: '{{ route("checkAadharNumber") }}',
            type: 'POST',
            beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
            data: { aadhar_card: aadhar_card },
            dataType: 'json',
            success: function (res) {
              if (res.hasOwnProperty('error')) {
                $('#aadhar_err').html(res.message);
                //return;
              }else{
                $('#aadhar_err').html('');
              }
            },
            complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            },
            error: function (xhr, status, error) {
                console.error(xhr.responseText);
                // Optionally handle errors
            }
        });
    }

    $(document).ready(function() {
        $('#mobile').on('input', function() {
            var value = $(this).val();
            if (value.length > 10) {
                $(this).val(value.slice(0, 10));
            }
        });
        $('#aadhar_card').on('input', function() {
            var value = $(this).val();
            if (value.length > 12) {
                $(this).val(value.slice(0, 12));
            }
        });
    });


    
  </script>
@endsection
