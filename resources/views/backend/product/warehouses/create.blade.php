@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{ translate('Add New Warehouse') }}</h5>
  </div>

  <div class="col mx-auto">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0 h6">{{ translate('Warehouse Details') }}</h5>
      </div>
      <div class="card-body">
        <form action="{{ route('warehouses.store') }}" method="POST">
          @csrf
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="name">{{ translate('Name') }} *</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('Name') }}" id="name" name="name"
                class="form-control" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="address">{{ translate('Address') }} *</label>
            <div class="col-sm-9">
              <textarea placeholder="{{ translate('Address') }}" id="address" name="address" class="form-control" required></textarea>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="state_id">{{ translate('State') }} *</label>
            <div class="col-sm-9">
              <select class="form-control aiz-selectpicker" data-live-search="true"
                data-placeholder="{{ translate('Select the State') }}" name="state_id" required>
                <option value="">{{ translate('Select the State') }}</option>
                @foreach ($states as $key => $state)
                  <option value="{{ $state->id }}">{{ $state->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="city_id">{{ translate('City') }} *</label>
            <div class="col-sm-9">
              <select class="form-control aiz-selectpicker" data-live-search="true" name="city_id" required></select>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="pincode">{{ translate('Pincode') }} *</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('Pincode') }}" id="pincode" name="pincode"
                class="form-control" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-md-3 col-form-label">{{ translate('Service States') }}</label>
            <div class="col-md-9">
              <select class="select2 form-control aiz-selectpicker" name="service_states[]" data-toggle="select2"
                data-placeholder="Choose ..."data-live-search="true" multiple>
                @foreach ($states as $mstate)
                  <option value="{{ $mstate->id }}">{{ $mstate->name }}</option>
                @endforeach
              </select>
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
            <label class="col-sm-3 col-from-label"
              for="seller_saleszing_id">{{ translate('Seller Saleszing Warehouse Id') }} *</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('Seller Saleszing Warehouse Id') }}"
                id="seller_saleszing_id" name="seller_saleszing_id" class="form-control" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label"
              for="inhouse_saleszing_id">{{ translate('Inhouse Saleszing Warehouse Id') }} *</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('Inhouse Saleszing Warehouse Id') }}"
                id="inhouse_saleszing_id" name="inhouse_saleszing_id" class="form-control" required>
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
    $(document).on('change', '[name=state_id]', function() {
      var state_id = $(this).val();
      get_city(state_id);
    });

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
