@extends('frontend.layouts.app')

@section('content')
    <section class="gry-bg py-5">
        <div class="profile">
            <div class="container">
                <div class="row">
                    <div class="col-xxl-4 col-xl-5 col-lg-6 col-md-8 mx-auto">
                        <div class="card">
                            <div class="text-center pt-4">
                                <h1 class="h4 fw-600">
                                    {{ translate('Login to your account.')}}
                                </h1>
                            </div>

                            <div class="px-4 py-3 py-lg-4">
                                <div class="">
                                    <form class="form-default" role="form" action="{{ route('login') }}" method="POST">
                                        @csrf
                                        @if (addon_is_activated('otp_system') && env("DEMO_MODE") != "On")
                                            <div class="form-group phone-form-group mb-1">
                                                <input type="tel"  pattern="[0-9]{10}" maxlength="10" id="phone-code" class="form-control{{ $errors->has('phone') ? ' is-invalid' : '' }}" value="{{ old('phone') }}" placeholder="" name="phone" autocomplete="off">
                                            </div>

                                            <input type="hidden" name="country_code" value="">

                                            <div class="form-group email-form-group mb-1 d-none">
                                                <input type="email" class="form-control {{ $errors->has('email') ? ' is-invalid' : '' }}" value="{{ old('email') }}" placeholder="{{  translate('Email') }}" name="email" id="email" autocomplete="off">
                                                @if ($errors->has('email'))
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $errors->first('email') }}</strong>
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="form-group text-right">
                                                <button class="btn btn-link p-0 opacity-50 text-reset" style="float:left;" type="button" onclick="toggleEmailPhone(this)">{{ translate('Use Email Instead') }}</button>
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
                                            <input type="password" class="form-control {{ $errors->has('password') ? ' is-invalid' : '' }}" placeholder="{{ translate('Password')}}" name="password" id="password">
                                            <div id="otp-inputs" class="d-none">
                                                <input autofocus type="text" inputmode="numeric" id="otp-1" maxlength="1" class="otp-input" oninput="moveToNext(this, 'otp-2')" />
                                                <input type="text" inputmode="numeric" id="otp-2" maxlength="1" class="otp-input" oninput="moveToNext(this, 'otp-3')" />
                                                <input type="text" inputmode="numeric" id="otp-3" maxlength="1" class="otp-input" oninput="moveToNext(this, 'otp-4')" />
                                                <input type="text" inputmode="numeric" id="otp-4" maxlength="1" class="otp-input" oninput="moveToNext(this, 'otp-5')" />
                                                <input type="text" inputmode="numeric" id="otp-5" maxlength="1" class="otp-input" oninput="moveToNext(this, 'otp-6')" />
                                                <input type="text" inputmode="numeric" id="otp-6" maxlength="1" class="otp-input" />
                                            </div>
                                        </div>

                                        <div class="row mb-2 remember_me_forget_password">
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
                                        </div>

                                        <div  class="mb-5 submit_div">
                                            <button type="submit" class="btn btn-primary btn-block fw-600" id="submitButton">{{  translate('Login') }}</button>
                                        </div>
                                    </form>

                                    @if (env("DEMO_MODE") == "On")
                                        <div class="mb-5">
                                            <table class="table table-bordered mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td>{{ translate('Seller Account')}}</td>
                                                        <td>
                                                            <button class="btn btn-info btn-sm" onclick="autoFillSeller()">{{ translate('Copy credentials') }}</button>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>{{ translate('Customer Account')}}</td>
                                                        <td>
                                                            <button class="btn btn-info btn-sm" onclick="autoFillCustomer()">{{ translate('Copy credentials') }}</button>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>{{ translate('Delivery Boy Account')}}</td>
                                                        <td>
                                                            <button class="btn btn-info btn-sm" onclick="autoFillDeliveryBoy()">{{ translate('Copy credentials') }}</button>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    @if(get_setting('google_login') == 1 || get_setting('facebook_login') == 1 || get_setting('twitter_login') == 1 || get_setting('apple_login') == 1)
                                        <div class="separator mb-3">
                                            <span class="bg-white px-3 opacity-60">{{ translate('Or Login With')}}</span>
                                        </div>
                                        <ul class="list-inline social colored text-center mb-5">
                                            @if (get_setting('facebook_login') == 1)
                                                <li class="list-inline-item">
                                                    <a href="{{ route('social.login', ['provider' => 'facebook']) }}" class="facebook">
                                                        <i class="lab la-facebook-f"></i>
                                                    </a>
                                                </li>
                                            @endif
                                            @if(get_setting('google_login') == 1)
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
                                                    <a href="{{ route('social.login', ['provider' => 'apple']) }}"
                                                        class="apple">
                                                        <i class="lab la-apple"></i>
                                                    </a>
                                                </li>
                                            @endif
                                        </ul>
                                    @endif
                                </div>
                                <div class="text-center">
                                    <p class="text-muted mb-0">{{ translate('Dont have an account?')}}</p>
                                    <a href="{{ route('user.registration') }}">{{ translate('Register Now')}}</a>
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

        function moveToNext(current, nextFieldID) {
            if (current.value.length >= 1) {
                document.getElementById(nextFieldID).focus();
            }
        }

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

                    $(el).html('{{ translate('Resend OTP') }}');
                    // alert('OTP has been sent');
                    AIZ.plugins.notify('info', 'OTP has been sent');
                    document.getElementById('password').placeholder = 'Enter OTP';
                    document.getElementById('password').classList.add('d-none');
                    document.getElementById('otp-inputs').classList.remove('d-none');
                    $('.otp-input').first().focus();
                    $('.remember_me_forget_password').hide();

                    var user_phone = $('input[name=phone]').val();
                    console.log(user_phone);

                    // Define the parameters to be sent
                    const params = {
                        phone: user_phone,
                        _token: "{{ csrf_token() }}"
                    };

                    // Construct the request options object
                    const requestOptions = {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(params)
                    };

                    // Make the fetch request with the specified options
                    fetch('https://mazingbusiness.com/login_otp', requestOptions)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            // Handle the response here if needed
                            console.log('Route triggered successfully');
                        })
                        .catch(error => {
                            console.error('There was a problem triggering the route:', error.message);
                        });
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
