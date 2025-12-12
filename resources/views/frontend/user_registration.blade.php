@extends('frontend.layouts.app')
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
  <section class="gry-bg py-4">
    <div class="profile">
      <div class="container">
        <div class="row">
          <div class="col-xxl-4 col-xl-5 col-lg-6 col-md-8 mx-auto">
            <div class="card">
              <div class="text-center pt-4">
                <h1 class="h4 fw-600">
                  {{ translate('Create an Account') }}
                </h1>
              </div>
              <div class="px-4 py-3 py-lg-4">
                <div class="">
                  <form id="reg-form" class="form-default" role="form" id="frmRegistration" name="frmRegistration" action="{{ route('register') }}" method="POST">
                    @csrf
                    <div
                      class="form-group {{ old('name') || old('company_name') || old('aadhar_card') || old('email') || old('postal_code') || $errors->has('name') || $errors->has('company_name') || $errors->has('aadhar_card') || $errors->has('email') || $errors->has('postal_code') ? 'd-none' : '' }}"
                      id="divGstin">
                      <input type="text" class="form-control{{ $errors->has('gstin') ? ' is-invalid' : '' }}"
                        value="{{ old('gstin') }}" placeholder="{{ translate('GSTIN') }}" name="gstin"  id="gstin">                      
                      @if ($errors->has('gstin'))
                        <span class="invalid-feedback" role="alert">
                          <strong>{{ $errors->first('gstin') }}</strong>
                        </span>
                      @endif                      
                    </div>
                    <span id="gstin_success" class="text-success"></span>
                    <span id="gstin_err" class="text-danger"></span>
                    <div class="form-group phone-form-group mb-3">
                      <input type="tel" id="phone-code"
                        class="form-control{{ $errors->has('phone') ? ' is-invalid' : '' }}" value="{{ old('phone') }}"
                        placeholder="Phone No." name="phone" autocomplete="off" max=15><br/>                        
                        @if ($errors->has('phone'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('phone') }}</strong>
                          </span>
                        @endif
                    </div>
                    <span id="phone_err" class="text-danger"></span>
                    <div class="form-group email-form-group mb-3">
                      <input type="email" class="form-control {{ $errors->has('email') ? ' is-invalid' : '' }}"
                        value="{{ old('email') }}" placeholder="{{ translate('Email') }}" name="email" id="email"
                        autocomplete="off">
                      <input type="hidden" name="email_err_flag" id="email_err_flag" value = "0">
                      @if ($errors->has('email'))
                        <span class="invalid-feedback" role="alert">
                          <strong>{{ $errors->first('email') }}</strong>
                        </span>
                      @endif
                    </div> 
                    <span id="email_err" class="text-danger"></span>                   
                    <input type="hidden" name="country_code" value="91">
                    <div class="{{ $errors->count() && !old('gstin') && !$errors->has('gstin') ? '' : 'd-none' }}" id="no-gstin">
                      <div class="form-group">
                        <input type="text" class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}"
                          value="{{ old('name') }}" placeholder="{{ translate('Full Name') }}" name="name" id="name">
                        @if ($errors->has('name'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('name') }}</strong>
                          </span>
                        @endif
                      </div>
                      <span id="name_err" class="text-danger"></span>
                      <div class="form-group">
                        <input type="text" class="form-control{{ $errors->has('company_name') ? ' is-invalid' : '' }}"
                          value="{{ old('company_name') }}" placeholder="{{ translate('Company Name') }}"
                          name="company_name" id="company_name">
                        @if ($errors->has('company_name'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('company_name') }}</strong>
                          </span>
                        @endif
                      </div>
                      <span id="company_name_err" class="text-danger"></span>
                      <div class="form-group">
                        <input type="number" class="form-control{{ $errors->has('aadhar_card') ? ' is-invalid' : '' }}"
                          value="{{ old('aadhar_card') }}" placeholder="{{ translate('Aadhar No.') }}"
                          name="aadhar_card" id="aadhar_card">
                        @if ($errors->has('aadhar_card'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('aadhar_card') }}</strong>
                          </span>
                        @endif
                      </div>
                      <span id="aadhar_card_err" class="text-danger"></span>

                      <div class="form-group">
                        <input type="text" min="100000" max="999999"
                          class="form-control{{ $errors->has('address') ? ' is-invalid' : '' }}"
                          value="{{ old('address') }}" placeholder="{{ translate('Address') }}" id="address" name="address">
                        @if ($errors->has('address'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('address') }}</strong>
                          </span>
                        @endif
                      </div>
                      <div class="form-group">
                        <input type="text" min="100000" max="999999"
                          class="form-control{{ $errors->has('address2') ? ' is-invalid' : '' }}"
                          value="{{ old('address2') }}" placeholder="{{ translate('Address 2') }}" id="address2" name="address2">
                        @if ($errors->has('address2'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('address2') }}</strong>
                          </span>
                        @endif
                      </div>
                      <span id="address_err" class="text-danger"></span>
                      <div class="form-group">
                        <input type="text" min="100000" max="999999"
                          class="form-control{{ $errors->has('city') ? ' is-invalid' : '' }}"
                          value="{{ old('City') }}" placeholder="{{ translate('City') }}" id="city" name="city">
                        @if ($errors->has('city'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('city') }}</strong>
                          </span>
                        @endif
                      </div>

                      <div class="form-group">
                      <select class="form-control{{ $errors->has('state') ? ' is-invalid' : '' }}" id="state" name="state">
                          <option value="" disabled selected>{{ translate('Select State') }}</option>
                          @foreach ($states as $state)
                              <option value="{{ $state->id }}" {{ old('state') == $state->name ? 'selected' : '' }}>
                                  {{ $state->name }}
                              </option>
                          @endforeach
                      </select>
                      @if ($errors->has('state'))
                          <span class="invalid-feedback" role="alert">
                              <strong>{{ $errors->first('state') }}</strong>
                          </span>
                      @endif
                    </div>
                    <span id="city_err" class="text-danger"></span>
                    <div class="form-group">
                      <input type="number" min="100000" max="999999"
                        class="form-control{{ $errors->has('postal_code') ? ' is-invalid' : '' }}"
                        value="{{ old('postal_code') }}" placeholder="{{ translate('Pincode') }}" id="postal_code" name="postal_code">
                      @if ($errors->has('postal_code'))
                        <span class="invalid-feedback" role="alert">
                          <strong>{{ $errors->first('postal_code') }}</strong>
                        </span>
                      @endif
                    </div>
                    <span id="postal_code_err" class="text-danger"></span>
                    </div>
                    <input type="hidden" name="gst_data" id="gst_data" value="">
                    <div class="mb-0">
                      <label class="aiz-checkbox">
                        <input type="checkbox" name="no_gstin" id="no_gstin"
                          {{ $errors->count() && !old('gstin') && !$errors->has('gstin') ? 'checked="checked"' : '' }}>
                        <span class=opacity-60>{{ translate('Don\'t have a GSTIN?') }}</span>
                        <span class="aiz-square-check"></span>
                        @if ($errors->has('no_gstin'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('no_gstin') }}</strong>
                          </span>
                        @endif
                      </label>
                    </div>

                    @if (get_setting('google_recaptcha') == 1)
                      <div class="form-group">
                        <div class="g-recaptcha" data-sitekey="{{ env('CAPTCHA_KEY') }}"></div>
                      </div>
                    @endif

                    <div class="mb-3">
                      <label class="aiz-checkbox">
                        <input type="checkbox" name="agree_terms" checked="checked" required>
                        <span
                          class=opacity-60>{{ translate('By signing up you agree to our Terms and Conditions.') }}</span>
                        <span class="aiz-square-check"></span>
                        @if ($errors->has('agree_terms'))
                          <span class="invalid-feedback" role="alert">
                            <strong>{{ $errors->first('agree_terms') }}</strong>
                          </span>
                        @endif
                      </label>
                    </div>
                    <div class="mb-5">
                      <button type="button" class="btn btn-primary btn-block fw-600" id="createAccountBtn">{{ translate('Create Account') }}</button>
                    </div>
                  </form>
                  @if (get_setting('google_login') == 1 ||
                          get_setting('facebook_login') == 1 ||
                          get_setting('twitter_login') == 1 ||
                          get_setting('apple_login') == 1)
                    <div class="separator mb-3">
                      <span class="bg-white px-3 opacity-60">{{ translate('Or Join With') }}</span>
                    </div>
                    <ul class="list-inline social colored text-center mb-5">
                      @if (get_setting('facebook_login') == 1)
                        <li class="list-inline-item">
                          <a href="{{ route('social.login', ['provider' => 'facebook']) }}" class="facebook">
                            <i class="lab la-facebook-f"></i>
                          </a>
                        </li>
                      @endif
                      @if (get_setting('google_login') == 1)
                        <li class="list-inline-item">
                          <a href="{{ route('social.login', ['provider' => 'google']) }}" class="google">
                            <i class="lab la-google"></i>
                          </a>
                        </li>
                      @endif
                      @if (get_setting('twitter_login') == 1)
                        <li class="list-inline-item">
                          <a href="{{ route('social.login', ['provider' => 'twitter']) }}" class="twitter">
                            <i class="lab la-twitter"></i>
                          </a>
                        </li>
                      @endif
                      @if (get_setting('apple_login') == 1)
                        <li class="list-inline-item">
                          <a href="{{ route('social.login', ['provider' => 'apple']) }}" class="apple">
                            <i class="lab la-apple"></i>
                          </a>
                        </li>
                      @endif
                    </ul>
                  @endif
                </div>
                <div class="text-center">
                  <p class="text-muted mb-0">{{ translate('Already have an account?') }}</p>
                  <a href="{{ route('user.login') }}">{{ translate('Log In') }}</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- Vefiry GST Data Modal -->
  <style>
        .form-group {
          display: flex;
          align-items: center;
      }
      .form-group label {
          flex: 0 0 120px; /* Adjust the width as needed */
          margin-bottom: 0; /* Align with the inline content */
      }
      .form-group small {
          flex: 1;
          margin-left: 10px; /* Space between label and small tag */
      }
      .otp-input {
          width: 2em;
          text-align: center;
          margin: 0 0.5em;
          font-size: 2em;
          padding: 0.25em;
      }
  </style>
  <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="exampleModalLabel">Verify GST Data</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                  </button>
              </div>
              <div class="modal-body">
                  <div class="row">
                    <div class="form-group col-md-12">
                      <label for="exampleInputEmail1">GST in</label>
                      <small id="gstinHelp" class="form-text text-muted"></small>
                    </div>
                    <div class="form-group col-md-12">
                      <label for="exampleInputEmail1">Name</label>
                      <small id="namenHelp" class="form-text text-muted"></small>
                    </div>
                    <div class="form-group col-md-12">
                      <label for="exampleInputEmail1">Company Name</label>
                      <small id="companyNameHelp" class="form-text text-muted"></small>
                    </div>
                    <div class="form-group col-md-12">
                      <label for="exampleInputEmail1">Address</label>
                      <small id="addressHelp" class="form-text text-muted"></small>
                    </div>
                    <div class="form-group col-md-12">
                      <label for="exampleInputEmail1">Address2</label>
                      <small id="address2Help" class="form-text text-muted"></small>
                    </div>
                    <div class="form-group col-md-12">
                      <label for="exampleInputEmail1">Postal Code</label>
                      <small id="postalCodeHelp" class="form-text text-muted"></small>
                    </div>
                    <div class="form-check col-md-12">
                      <input type="checkbox" class="form-check-input" id="verifiedCheck">
                      <label class="form-check-label" for="exampleCheck1">I agree to the <a target="_blank" href="{{ route('terms') }}">Terms & Condition</a>.</label>
                    </div>
                    <input type="hidden" name="verify_code" id="verify_code" value="123456">
                  </div>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-primary" id="btnProcced">Procced</button>
              </div>
          </div>
      </div>
  </div>

  <!-- OTP Modal -->
  <div class="modal fade" id="otpModal" tabindex="-1" role="dialog" aria-labelledby="otpModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
          <div class="modal-content" style="width:110%;">
              <div class="modal-header">
                  <h5 class="modal-title" id="otpModalLabel">Enter OTP</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                  </button>
              </div>
              <div class="modal-body">
                  <form id="otpForm">
                      <div class="form-group d-flex justify-content-center">
                          <input type="number" class="form-control otp-input" maxlength="1" id="otp1">
                          <input type="number" class="form-control otp-input" maxlength="1" id="otp2">
                          <input type="number" class="form-control otp-input" maxlength="1" id="otp3">
                          <input type="number" class="form-control otp-input" maxlength="1" id="otp4">
                          <input type="number" class="form-control otp-input" maxlength="1" id="otp5">
                          <input type="number" class="form-control otp-input" maxlength="1" id="otp6">
                      </div>
                      <span id="otp_success" class="text-success"></span>
                      <span id="otp_err" class="text-danger"></span>
                  </form>
              </div>
              <div class="modal-footer">
                  <span style="margin-right:61%; cursor:pointer;" id="resendOtpSpan">Resend OTP</span>
                  <button type="button" class="btn btn-primary" id="submitOtpBtn">Verify OTP</button>
              </div>
          </div>
      </div>
  </div>
@endsection
@section('script')
  @if (get_setting('google_recaptcha') == 1)
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  @endif  
  <script type="text/javascript">

    // Function to send OTP length
    function sendOtp(phone) {
        $.ajax({
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: '{{ route("sendOtp") }}',
            type: 'POST',
            beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
            data: { phone: phone },
            dataType: 'json',
            success: function (response) {              
              var base64Otp = response.otp;                    
              // Decode the base64 OTP
              var decodedOtp = atob(base64Otp) - 10111984;              
              // Set the decoded OTP into the hidden field
              $('#verify_code').val(decodedOtp);
              $('#otpModal').modal('show');
              $('.otp-input').val(''); // Clear OTP fields              
              $('#otp_success').html('OTP sent successfully.');
              $('#otp_err').html('');
              $('#otp1').focus(); // Focus on the first OTP field
            },
            complete: function(){
              $('.ajax-loader').css("visibility", "hidden");
            }
        });
      }

    // GST
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
          $('#createAccountBtn').prop('disabled', false);
          if(response){
            if (response.hasOwnProperty('error')) {
              // $('#gstin_err').html(response.message);
              $('#gstin_err').html('Invalid GST');
              $('#phone-code').val('');
              $('#phone_err').html('');
              $('#createAccountBtn').prop('disabled', true);
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
                    $('#gstin_success').html('');
                    $('#phone-code').val('');
                    $('#phone_err').html('');
                    $('#address_err').html('');
                    $('#city_err').html('');
                    $('#createAccountBtn').prop('disabled', true);
                  }else{
                    $('#gstin_success').html('Valid GST');
                    $('#company_name').val(response.taxpayerInfo.tradeNam);
                    $('#name').val(response.taxpayerInfo.lgnm);
                    $('#gst_data').val(JSON.stringify(response));
                    var address = (response.taxpayerInfo.pradr.addr.bnm + ', ' +response.taxpayerInfo.pradr.addr.st + ', ' + response.taxpayerInfo.pradr.addr.loc).replace(/^[, ]+|[, ]+$/g, '');
                    var address2 = (response.taxpayerInfo.pradr.addr.bno + ', ' +  response.taxpayerInfo.pradr.addr.dst).replace(/^[, ]+|[, ]+$/g, '');
                    $('#address').val(address);
                    $('#address2').val(address2);
                    $('#postal_code').val(response.taxpayerInfo.pradr.addr.pncd);

                    $('#gstinHelp').html(gstin);
                    $('#companyNameHelp').html(response.taxpayerInfo.tradeNam);
                    $('#namenHelp').html(response.taxpayerInfo.lgnm);
                    $('#addressHelp').html(address);
                    $('#address2Help').html(address2);
                    $('#postalCodeHelp').html(response.taxpayerInfo.pradr.addr.pncd);
                    $('#phone-code').val('');
                    $('#phone_err').html('');
                    $('#address_err').html('');
                    $('#city_err').html('');
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

    // Phone
    $(document).on('keyup', '[name=phone]', function() {
      var phone = $(this).val();
      if (phone.length == 10) {
        var valid = phoneValidation(phone);
        $('#createAccountBtn').prop('disabled', false);
        if(valid == true){
          $('#phone_err').html('');
          check_phone_number(phone);          
        }else{
          $('#phone_err').html('Phone number is not valid');
          $('#createAccountBtn').prop('disabled', true);
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
    
    function check_phone_number(phone) {
      $.ajax({
            headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: '{{ route("checkPhoneNumber") }}',
            type: 'POST',
            beforeSend: function(){
              $('.ajax-loader').css("visibility", "visible");
            },
            data: { mobile: phone },
            dataType: 'json',
            success: function (res) {
              if (res.hasOwnProperty('error')) {
                $('#phone_err').html(res.message);
                $('#createAccountBtn').prop('disabled', true);
              }else{
                $('#createAccountBtn').prop('disabled', false);
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
              var noGstinChecked = $('#no_gstin').is(':checked');
              var gstinValue = $('#gstin').val().trim();
              var phoneValue = $('#phone-code').val().trim();
              var emailValue = $('#email').val().trim();
              var nameValue = $('#name').val().trim();
              var companyNameValue = $('#company_name').val().trim();
              var aadharCardValue = $('#aadhar_card').val().trim();
              var postalCodeValue = $('#postal_code').val().trim();
              var addressValue = $('#address').val().trim();
              $('#gstin_err').html('');
              $('#phone_err').html('');
              $('#name_err').html('');
              $('#company_name_err').html('');
              $('#aadhar_card_err').html('');
              $('#postal_code_err').html('');
              $('#email_err').html('');
              $('#address_err').html('');
              if (!noGstinChecked && gstinValue !== '' && phoneValue !== '' && emailValue !== '') {
                  $('#myModal').modal('show');
                  $('#email_err').html('');
                  $('#gstin_err').html('');
                  $('#phone_err').html('');
              } else if(!noGstinChecked && gstinValue == ''){
                  $('#gstin_err').html('Please provide a valid GSTIN or check the No GSTIN box.');
              } else if(!noGstinChecked && phoneValue == ''){
                  $('#phone_err').html('Please provide a valid phone number.');
              } else if(noGstinChecked && gstinValue == ''){
                if(phoneValue == ""){
                  $('#phone_err').html('Please enter valid phone number.');
                }else if(nameValue == ""){
                  $('#name_err').html('Please enter full name.');
                }else if(companyNameValue == ""){
                  $('#company_name_err').html('Please enter company.');
                }else if(aadharCardValue == ""){
                  $('#aadhar_card_err').html('Please enter valid aadhar number.');
                }else if(addressValue == ""){
                  $('#address_err').html('Please enter address.');
                }else if(postalCodeValue == ""){
                  $('#postal_code_err').html('Please enter valid pin code.');
                }else{
                  $('#myModal').modal('hide');
                  var phone = $('#phone-code').val().trim();
                  sendOtp(phone);
                }
                
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

    //Aadhar Card
    $(document).on('keyup', '[name=aadhar_card]', function() {
      var aadhar_card = $(this).val();
      if (aadhar_card.length == 12) {
        var valid = aadharValidation(aadhar_card);
        $('#createAccountBtn').prop('disabled', false);
        if(valid == true){
          $('#aadhar_card_err').html('');
          check_aadhar_card_number(aadhar_card);          
        }else{
          $('#aadhar_card_err').html('Aadhar card number is not valid');
          $('#createAccountBtn').prop('disabled', true);
        }
        
      }
    });

    function aadharValidation(phoneNumber) {
      var regex = /^[0-9]{12}$/;
      if (regex.test(phoneNumber)) {
        return true;
      } else {
        return false;
      }
    }

    function check_aadhar_card_number(aadhar_card) {
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
                $('#aadhar_card_err').html(res.message);
                $('#createAccountBtn').prop('disabled', true);
              }else{
                $('#createAccountBtn').prop('disabled', false);
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

    //Pincode Validation
    $(document).on('keyup', '[name=postal_code]', function() {
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
        
      }
      else {
        $('#postal_code_err').html('Pincode must be 6 digits'); // Show error for length
        $('#createAccountBtn').prop('disabled', true); // Disable the button if not 6 digits
      }
    });

    function postalCodeValidation(postal_code) {
      var regex = /^[0-9]{6}$/;
      if (regex.test(postal_code)) {
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
                $('#createAccountBtn').prop('disabled', true);
              }else{
                $('#createAccountBtn').prop('disabled', false);
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


    @if (get_setting('google_recaptcha') == 1)
      // making the CAPTCHA  a required field for form submission
      $(document).ready(function() {
        $("#reg-form").on("submit", function(evt) {
          var response = grecaptcha.getResponse();
          if (response.length == 0) {
            //reCaptcha not verified
            alert("please verify you are human!");
            evt.preventDefault();
            return false;
          }
          //captcha verified
          //do the rest of your validations here
          $("#reg-form").submit();
        });
      });
    @endif

    $(document).ready(function() {
      $("#no_gstin").on('change', function() {
        $('#email_err').html('');
        $('#gstin_err').html('');
        $('#phone_err').html('');
        $('#gstin_success').html('');
        if ($(this).prop('checked') == true) {
          $('#no-gstin').removeClass('d-none');
          $('#gstin').addClass('d-none');
          $('#gstin').val('');
          $('#gst_data').val('');
          $('#address').val('');
        } else {
          $('#gstin').removeClass('d-none');
          $('#no-gstin').addClass('d-none');
        }
      });

      $('#createAccountBtn').click(function() {
          var noGstinChecked = $('#no_gstin').is(':checked');
          var gstinValue = $('#gstin').val().trim();
          var phoneValue = $('#phone-code').val().trim();
          var emailValue = $('#email').val().trim();
          // Email validation regex
          var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (emailRegex.test(emailValue)) {              
            check_email(emailValue);
          } else {
              emailValue = "";
              $('#email_err').html('Please enter a valid email address.');
          }
          // if (!noGstinChecked && gstinValue !== '' && phoneValue !== '' && emailValue !== '') {
          //     $('#myModal').modal('show');
          //     $('#email_err').html('');
          //     $('#gstin_err').html('');
          //     $('#phone_err').html('');
          // } else if(!noGstinChecked && gstinValue == ''){
          //     $('#gstin_err').html('Please provide a valid GSTIN or check the No GSTIN box.');
          // } else if(!noGstinChecked && phoneValue == ''){
          //     $('#phone_err').html('Please provide a valid phone number.');
          // }
      });

      $('#verifiedCheck').change(function() {
          if ($(this).is(':checked')) {
              $('#btnProcced').prop('disabled', false);
          } else {
              $('#btnProcced').prop('disabled', true);
          }
      });

      $('#btnProcced').prop('disabled', true);
      $('#submitOtpBtn').prop('disabled', true);

      $('#phone-code').on('input', function() {
          var value = $(this).val();
          if (value.length > 10) {
              $(this).val(value.slice(0, 10));
          }
      });

      $('#gstin').on('input', function() {
          var value = $(this).val();
          if (value.length > 15) {
              $(this).val(value.slice(0, 15));
          }
      });

      $('#aadhar_card').on('input', function() {
          var value = $(this).val();
          if (value.length > 12) {
              $(this).val(value.slice(0, 12));
          }
      });

      $('#btnProcced').click(function() {
        $('#myModal').modal('hide');
        var phone = $('#phone-code').val().trim();
        sendOtp(phone);        
      });

      $('#resendOtpSpan').click(function() {
        var phone = $('#phone-code').val().trim();
        sendOtp(phone);        
      });

      // Function to check OTP length
      function checkOtpLength() {
          var otp = '';
          $('.otp-input').each(function() {
              otp += $(this).val();
          });
          if (otp.length === 6) {
              $('#submitOtpBtn').prop('disabled', false);
          } else {
              $('#submitOtpBtn').prop('disabled', true);
          }
      }

      // Automatically focus on the next input field and check OTP length
      $('.otp-input').on('keyup', function(e) {
          if (e.key >= 0 && e.key <= 9) {
              $(this).next('.otp-input').focus();
          } else if (e.key === 'Backspace') {
              $(this).prev('.otp-input').focus();
          }
          checkOtpLength();
      });

      // Submit OTP
      $('#submitOtpBtn').click(function() {
          var verify_code = $('#verify_code').val();
          var otp = '';
          $('.otp-input').each(function() {
              otp += $(this).val();
          });
          if(verify_code != otp){
            $('#otp_success').html('');
            $('#otp_err').html('Please enter correct OTP.');
            $('.otp-input').val(''); // Clear OTP fields
            $('#otp1').focus(); // Focus on the first OTP field
            $('#otpModal').modal('show');
          }else{
            frmRegistration.submit();
          }
      });
      
    });

    {{-- var isPhoneShown = true,
      countryData = window.intlTelInputGlobals.getCountryData(),
      input = document.querySelector("#phone-code");

    for (var i = 0; i < countryData.length; i++) {
      var country = countryData[i];
      if (country.iso2 == 'in') {
        country.dialCode = '91';
      }
    }

    var iti = intlTelInput(input, {
      separateDialCode: true,
      utilsScript: "{{ static_asset('assets/js/intlTelutils.js') }}?1590403638580",
      onlyCountries: @php
        echo json_encode(
            \App\Models\Country::where('status', 1)
                ->pluck('code')
                ->toArray(),
        );
      @endphp,
      customPlaceholder: function(selectedCountryPlaceholder, selectedCountryData) {
        if (selectedCountryData.iso2 == 'in') {
          return "01xxxxxxxxx";
        }
        return selectedCountryPlaceholder;
      }
    });

    var country = iti.getSelectedCountryData();
    $('input[name=country_code]').val(country.dialCode);

    input.addEventListener("countrychange", function(e) {
      // var currentMask = e.currentTarget.placeholder;

      var country = iti.getSelectedCountryData();
      $('input[name=country_code]').val(country.dialCode);

    });
    --}}

    function toggleEmailPhone(el) {
      if (isPhoneShown) {
        $('.phone-form-group').addClass('d-none');
        $('.email-form-group').removeClass('d-none');
        isPhoneShown = false;
        $(el).html('{{ translate('Use Phone Instead') }}');
      } else {
        $('.phone-form-group').removeClass('d-none');
        $('.email-form-group').addClass('d-none');
        isPhoneShown = true;
        $(el).html('{{ translate('Use Email Instead') }}');
      }
    }
  </script>
@endsection
