@extends('frontend.layouts.app')
@section('content')
    <section class="gry-bg py-5">
        <div class="profile">
            <div class="container">
                <div class="row">
                    <div class="col-xxl-4 col-xl-5 col-lg-6 col-md-8 mx-auto">
                        <div class="card">
                            <div class="text-center pt-4">
                              <img src="{{ url('public/assets/img/warranty-claims.jpg') }}" style="width: 40%;" />
                                <h1 class="h4 fw-600">
                                    {{ translate('Warranty Claim Form.')}}
                                </h1>
                            </div>

                            <div class="px-4 py-3 py-lg-4">
                                <div class="">
                                    @if(session('error'))
                                      <div class="alert alert-danger">{{ session('error') }}</div>
                                    @endif
                                    <form class="form-default" role="form" action="{{ route('warrantyClaimPost') }}" method="POST">
                                    <!-- <form class="form-default" role="form" action="{{ route('login') }}" method="POST">   -->
                                        @csrf
                                        @if (addon_is_activated('otp_system') && env("DEMO_MODE") != "On")
                                            <div class="form-group phone-form-group mb-1">
                                                <input type="tel"  pattern="[0-9]{10}" maxlength="10" id="phone-code" class="form-control{{ $errors->has('phone') ? ' is-invalid' : '' }}" value="{{ old('phone') }}" placeholder="" name="phone" autocomplete="off">
                                            </div>

                                            <input type="hidden" name="country_code" value="">

                                            <!-- <div class="form-group email-form-group mb-1 d-none">
                                                <input type="email" class="form-control {{ $errors->has('email') ? ' is-invalid' : '' }}" value="{{ old('email') }}" placeholder="{{  translate('Email') }}" name="email" id="email" autocomplete="off">
                                                @if ($errors->has('email'))
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $errors->first('email') }}</strong>
                                                    </span>
                                                @endif
                                            </div> -->

                                            <div class="form-group text-right">
                                                <button class="btn btn-link p-0 opacity-50 text-reset phone-otp" type="button" onclick="sendPhoneOTP(this)">{{ translate('Send OTP') }}</button>
                                            </div>
                                        @else
                                            <div class="form-group">
                                                <input type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" value="{{ old('email') }}" placeholder="{{  translate('Email') }}" name="email" id="email" autocomplete="off">
                                                @if ($errors->has('email'))
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $errors->first('email') }}</strong>
                                                    </span>
                                                @endif
                                            </div>
                                        @endif

                                        <style>
                                            #otp-inputs {
                                                display: flex;
                                                justify-content: space-between;
                                                max-width: 300px;
                                                margin: auto;
                                            }

                                            .otp-input {
                                                width: 40px;
                                                height: 40px;
                                                text-align: center;
                                                font-size: 20px;
                                                border: 1px solid #ccc;
                                                border-radius: 5px;
                                                margin: 5px;
                                            }
                                        </style>

                                        <div class="form-group">
                                            <!-- <input type="password" class="form-control {{ $errors->has('password') ? ' is-invalid' : '' }}" placeholder="{{ translate('Password')}}" name="password" id="password"> -->
                                            <div id="otp-inputs" class="d-none">
                                                <input autofocus type="text" inputmode="numeric" id="otp-1" maxlength="1" class="otp-input" oninput="moveToNext(this, 'otp-2')" />
                                                <input type="text" inputmode="numeric" id="otp-2" maxlength="1" class="otp-input" oninput="moveToNext(this, 'otp-3')" />
                                                <input type="text" inputmode="numeric" id="otp-3" maxlength="1" class="otp-input" oninput="moveToNext(this, 'otp-4')" />
                                                <input type="text" inputmode="numeric" id="otp-4" maxlength="1" class="otp-input" oninput="moveToNext(this, 'otp-5')" />
                                                <input type="text" inputmode="numeric" id="otp-5" maxlength="1" class="otp-input" oninput="moveToNext(this, 'otp-6')" />
                                                <input type="text" inputmode="numeric" id="otp-6" maxlength="1" class="otp-input" />
                                            </div>
                                        </div>

                                        <!-- <div class="row mb-2 remember_me_forget_password">
                                            <div class="col-6 ">
                                                <label class="aiz-checkbox">
                                                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                                                    <span class=opacity-60>{{  translate('Remember Me') }}</span>
                                                    <span class="aiz-square-check"></span>
                                                </label>
                                            </div>
                                            <div class="col-6 text-right">
                                                <a href="{{ route('password.request') }}" class="text-reset opacity-60 fs-14">{{ translate('Forgot password?')}}</a>
                                            </div>
                                        </div> -->
                                        <input type="hidden" name="otpVerified" id="otpVerified" value="0">
                                        <div  class="mb-5 submit_div" style="display:none;">
                                            <button type="submit" class="btn btn-primary btn-block fw-600" id="submitButton">{{  translate('Procced') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script type="text/javascript">

        document.addEventListener('DOMContentLoaded', function() {
            var phoneCode = document.getElementById('phone-code');

            phoneCode.addEventListener('input', function() {
                // Replace any non-numeric characters with an empty string
                this.value = this.value.replace(/\D/g, '');
            });
        });

        $('#phone-code').on('keydown', function(e) {
            if (e.key === "Enter") {
                e.preventDefault(); // stop default form submission
                sendPhoneOTP(this);
            }
        });

        function moveToNext(current, nextFieldID) {
            if (current.value.length >= 1) {
                document.getElementById(nextFieldID).focus();
            }
        }

        // Auto-advance & verify OTP when typing
        $('.otp-input').on('input', function () {
            var $input = $(this);

            // only 1 digit, digits only
            $input.val($input.val().replace(/\D/g, '').slice(0, 1));

            // auto move to next input
            if ($input.val() !== '' && $input.next('.otp-input').length) {
                $input.next('.otp-input').focus();
            }

            // if last input (#otp-6) filled, verify
            if ($input.is('#otp-6') && $input.val() !== '') {
                const enteredOtp = getOTP();
                console.log("Entered OTP:", enteredOtp);

                if (issuedOtp && enteredOtp === issuedOtp) {
                    AIZ.plugins.notify('success', 'OTP Verified ✅');
                    $('#otpVerified').val('1');   // if needed for your form
                    $('#submitButton').click();       // auto submit


                } else {
                    AIZ.plugins.notify('danger', 'Invalid OTP ❌');
                    // clear all and refocus
                    $('.otp-input').val('');
                    $('#otp-1').focus();
                }
            }
        });

        // Your existing backspace handler
        $('.otp-input').on('keydown', function (e) {
            if (e.key === "Backspace") {
                var $input = $(this);
                var prevInput = $input.prev('.otp-input');

                if ($input.val() === '') {
                    if (prevInput.length) {
                        prevInput.val('');
                        prevInput.focus();
                    }
                } else {
                    $input.val('');
                }
            }
        });

        // You can keep your existing getOTP() function:
        function getOTP() {
            let otp = '';
            for (let i = 1; i <= 6; i++) {
                otp += document.getElementById('otp-' + i).value;
            }
            return otp;
        }

        // Example button click handler
        function sendOTP(el) {
            $(el).html('{{ translate('Resend OTP') }}');
            alert('OTP has been sent');

            // Clear the existing input if necessary
            $('#otp-inputs input').val('');

            // Show the OTP input boxes
            document.getElementById('otp-inputs').style.display = 'flex';

            var user_phone = $('input[name=phone]').val();
            console.log(user_phone);
        }

        // Example: Collect OTP when the last input field is filled
        document.getElementById('otp-6').addEventListener('input', function() {
            const otp = getOTP();
            document.getElementById('password').value = otp;
            document.getElementById('submitButton').click();
            console.log('OTP:', otp); // Do something with the OTP
        });

        var isPhoneShown = true,
            countryData = window.intlTelInputGlobals.getCountryData(),
            input = document.querySelector("#phone-code");

        for (var i = 0; i < countryData.length; i++) {
            var country = countryData[i];
            if(country.iso2 == 'bd'){
                country.dialCode = '88';
            }
        }

        var iti = intlTelInput(input, {
            separateDialCode: true,
            utilsScript: "{{ static_asset('assets/js/intlTelutils.js') }}?1590403638580",
            onlyCountries: @php echo json_encode(\App\Models\Country::where('status', 1)->pluck('code')->toArray()) @endphp ,
            customPlaceholder: function(selectedCountryPlaceholder, selectedCountryData) {
                if(selectedCountryData.iso2 == 'bd'){
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

        function toggleEmailPhone(el){
            if(isPhoneShown){
                // $('.submit_div').show();
                $('.phone-form-group').addClass('d-none');
                $('.email-form-group').removeClass('d-none');
                $('.phone-otp').addClass('d-none');
                $('input[name=phone]').val(null);
                isPhoneShown = false;
                $(el).html('{{ translate('Use Phone Instead') }}');
            }
            else{
                $('.phone-form-group').removeClass('d-none');
                $('.email-form-group').addClass('d-none');
                $('input[name=email]').val(null);
                $('.phone-otp').removeClass('d-none');

                isPhoneShown = true;
                $(el).html('{{ translate('Use Email Instead') }}');
            }
        }

        function sendPhoneOTP(el){

            let phoneNO=$('#phone-code').val();
            $('.submit_div').hide();

            if(phoneNO != ''){
                $(el).html('Resend OTP');
                AIZ.plugins.notify('info', 'OTP has been sent');
                document.getElementById('otp-inputs').classList.remove('d-none');
                $('.otp-input').val('');
                $('.otp-input').first().focus();

                const params = { phone: phoneNO, _token: "{{ csrf_token() }}" };

                fetch('https://mazingbusiness.com/send-otp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(params)
                })
                .then(response => response.json())
                .then(data => {
                    if(data.otp){
                        // decode base64 OTP (remember you added +10111984 in backend)
                        const decoded = atob(data.otp);
                        issuedOtp = String(parseInt(decoded) - 10111984);
                        console.log("Issued OTP:", issuedOtp); // for testing only
                    }
                })
                .catch(err => console.error(err));
            }else{
                AIZ.plugins.notify('warning', 'Enter Your Mobile Number.');
            }

        }

        function autoFillSeller(){
            $('#email').val('seller@example.com');
            $('#password').val('123456');
        }
        function autoFillCustomer(){
            $('#email').val('customer@example.com');
            $('#password').val('123456');
        }
        function autoFillDeliveryBoy(){
            $('#email').val('deliveryboy@example.com');
            $('#password').val('123456');
        }
    </script>
@endsection
