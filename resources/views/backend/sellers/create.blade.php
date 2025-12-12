@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{ translate('Add New Seller') }}</h5>
  </div>

  <div class="col mx-auto">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0 h6">{{ translate('Seller Information') }}</h5>
      </div>
      <div class="card-body">
        <form action="{{ route('sellers.store') }}" method="POST">
          @csrf
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="name">{{ translate('Name') }} *</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('Name') }}" id="name" name="name"
                class="form-control" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="email">{{ translate('Email Address') }} *</label>
            <div class="col-sm-9">
              <input type="email" placeholder="{{ translate('Email Address') }}" id="email" name="email"
                class="form-control" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="phone">{{ translate('Phone No.') }} *</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('Phone No.') }}" id="phone" name="phone"
                class="form-control" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="password">{{ translate('Password') }} *</label>
            <div class="col-sm-9">
              <input type="password" placeholder="{{ translate('Password') }}" id="password" name="password"
                class="form-control" required>
            </div>
          </div>
          <h6 class="mt-4">{{ translate('Company Details') }}</h6>
          <hr class="mt-2" />
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="shop_name">{{ translate('Company Name') }}</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('Company Name') }}" id="shop_name" name="shop_name"
                class="form-control">
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="warehouse_id">{{ translate('Warehouse') }} *</label>
            <div class="col-sm-9">
              <select class="form-control aiz-selectpicker" data-live-search="true"
                data-placeholder="{{ translate('Select the Warehouse') }}" name="warehouse_id" required>
                <option value="">{{ translate('Select the Warehouse') }}</option>
                @foreach (\App\Models\Warehouse::get() as $key => $warehouse)
                  <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="gstin">{{ translate('GSTIN') }}</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('GSTIN') }}" id="gstin" name="gstin"
                class="form-control">
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="address">{{ translate('Address') }} *</label>
            <div class="col-sm-9">
              <textarea placeholder="{{ translate('Address') }}" id="address" name="address" class="form-control" required></textarea>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="country">{{ translate('Country') }} *</label>
            <div class="col-sm-9">
              <select class="form-control aiz-selectpicker" data-live-search="true"
                data-placeholder="{{ translate('Select the country') }}" name="country_id" required>
                <option value="">{{ translate('Select the country') }}</option>
                @foreach (\App\Models\Country::where('status', 1)->get() as $key => $country)
                  <option value="{{ $country->id }}">{{ $country->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="state_id">{{ translate('State') }} *</label>
            <div class="col-sm-9">
              <select class="form-control aiz-selectpicker" data-live-search="true" name="state_id" required></select>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="city_id">{{ translate('City') }} *</label>
            <div class="col-sm-9">
              <select class="form-control aiz-selectpicker" data-live-search="true" name="city_id" required></select>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="postal_code">{{ translate('Postal Code') }} *</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('Postal Code') }}" id="postal_code" name="postal_code"
                class="form-control" required>
            </div>
          </div>
          <h6 class="mt-4">{{ translate('Account Details') }}</h6>
          <hr class="mt-2" />
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="bank_name">{{ translate('Bank Name') }}</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('Bank Name') }}" id="bank_name" name="bank_name"
                class="form-control">
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="bank_acc_name">{{ translate('Account Name') }}</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('Account Name') }}" id="bank_acc_name"
                name="bank_acc_name" class="form-control">
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="bank_acc_no">{{ translate('Account No.') }}</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('Account No.') }}" id="bank_acc_no" name="bank_acc_no"
                class="form-control">
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="bank_ifsc_code">{{ translate('IFSC Code') }}</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('IFSC Code') }}" id="bank_ifsc_code"
                name="bank_ifsc_code" class="form-control">
            </div>
          </div>
          <div class="form-group mb-0 text-right">
            <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
@section('script')
  <script type="text/javascript">
    //address
    $(document).on('change', '[name=country_id]', function() {
      var country = $(this).val();
      get_states(country);
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
  </script>
@endsection
