<div class="modal fade" id="login">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-center">
        <h1 class="h4 fw-600 modal-title w-100">
          {{ translate('Login to your account.')}}
        </h1>
        <button type="button" class="close" data-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form class="form-default" role="form" action="{{ route('register') }}" method="POST">
          @csrf
          <div class="form-group phone-form-group mb-3">
            <input type="tel" class="form-control{{ $errors->has('phone') ? ' is-invalid' : '' }}"
              value="{{ old('phone') }}" placeholder="Phone No." name="phone" autocomplete="off">
            @if ($errors->has('phone'))
            <span class="invalid-feedback" role="alert">
              <strong>{{ $errors->first('phone') }}</strong>
            </span>
            @endif
          </div>

          <input type="hidden" name="country_code" value="91">
          <input type="hidden" name="modal_login" value="modal_login">

          <div class="form-group phone-form-group mb-3">
            <input type="number" min="100000" max="999999"
              class="form-control{{ $errors->has('postal_code') ? ' is-invalid' : '' }}"
              value="{{ old('postal_code') }}" placeholder="{{ translate('Pincode') }}" name="postal_code">
            @if ($errors->has('postal_code'))
            <span class="invalid-feedback" role="alert">
              <strong>{{ $errors->first('postal_code') }}</strong>
            </span>
            @endif
          </div>
          <div class="">
            <button type="submit" class="btn btn-primary btn-block fw-600">{{ translate('Login') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>