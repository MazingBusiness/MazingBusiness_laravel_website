<div class="modal fade" id="otp_modal" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header text-center">
                <h1 class="h4 fw-600 modal-title w-100">
                    {{ translate('Verify OTP.')}}
                </h1>
                <a href="{{ route('logout',['modal_logout' => 1])}}">
                    <button type="button" class="close"></button>
                </a>
            </div>
            <div class="modal-body">
                <form class="form-default" role="form"
                    action="{{ route('verification.submit',['modal_verification' => 1]) }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <div class="input-group input-group--style-1">
                            <input type="text" class="form-control" name="verification_code">
                        </div>
                    </div>
                    <p>Verification code has been sent. Please wait a few minutes.</p>
                    <button type="submit" class="btn btn-primary btn-block">{{ translate('Verify') }}</button>
                </form>
                <center>
                    <a href="{{ route('verification.phone.resend',['modal_verification' => 1])}}"
                        class="btn btn-link">{{translate('Resend Code')}}</a>
                </center>
            </div>
        </div>
    </div>
</div>