<div class="modal fade" id="new-address-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">{{ translate('New Address') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form class="form-default" role="form" action="{{ route('addresses.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="p-3">
            <div class="row">
              <div class="col-md-3">
                <label>{{ translate('Company Name') }}</label>
              </div>
              <div class="col-md-9">
                <input type="text" class="form-control mb-3" placeholder="{{ translate('Company Name') }}"
                  name="company_name" id="company_name" value="" required>
              </div>
            </div>

            <div class="row">
              <div class="col-md-3">
                <label>{{ translate('GSTIN') }}</label>
              </div>
              <div class="col-md-9">
                <input type="text" class="form-control mb-3" placeholder="{{ translate('GSTIN') }}" name="gstin"
                  value="">
                <span id="gstin_success" class="text-success"></span>
                <span id="gstin_err" class="text-danger"></span>
              </div>
            </div>

            <div class="row">
              <div class="col-md-3">
                <label>{{ translate('Address') }}</label>
              </div>
              <div class="col-md-9">
                <textarea class="form-control mb-3" placeholder="{{ translate('Your Address') }}" rows="2" name="address" id="address"
                  required></textarea>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3">
                <label>{{ translate('Address 2') }}</label>
              </div>
              <div class="col-md-9">
                <textarea class="form-control mb-3" placeholder="{{ translate('Your Address 2') }}" rows="2" name="address_2" id="address_2"
                  required></textarea>
              </div>
            </div>

            <div class="row" id="divCountry">
              <div class="col-md-3">
                <label>{{ translate('Country') }}</label>
              </div>
              <div class="col-md-9">
                <div class="mb-3">
                  <select class="form-control aiz-selectpicker" data-live-search="true"
                    data-placeholder="{{ translate('Select your country') }}" name="country_id" id="country" required>
                    <option value="">{{ translate('Select your country') }}</option>
                    @foreach (\App\Models\Country::where('status', 1)->get() as $key => $country)
                      <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>

            <div class="row" id="divState">
              <div class="col-md-3">
                <label>{{ translate('State') }}</label>
              </div>
              <div class="col-md-9">
                <select class="form-control mb-3 aiz-selectpicker" data-live-search="true" name="state_id" id="state" required>

                </select>
              </div>
            </div>

            <div class="row" id="divCity">
              <div class="col-md-3">
                <label>{{ translate('City') }}</label>
              </div>
              <div class="col-md-9">
                <select class="form-control mb-3 aiz-selectpicker" data-live-search="true" name="city_id" id="city_field_id" required>

                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3">
                <label>{{ translate('City Name') }}</label>
              </div>
              <div class="col-md-9">
                <input type="text" class="form-control mb-3" placeholder="{{ translate('City Name') }}" value="" name="city"  id="city" value="" required>
              </div>
            </div>

            @if (get_setting('google_map') == 1)
              <div class="row">
                <input id="searchInput" class="controls" type="text"
                  placeholder="{{ translate('Enter a location') }}">
                <div id="map"></div>
                <ul id="geoData">
                  <li style="display: none;">Full Address: <span id="location"></span></li>
                  <li style="display: none;">Postal Code: <span id="postal_code"></span></li>
                  <li style="display: none;">Country: <span id="country"></span></li>
                  <li style="display: none;">Latitude: <span id="lat"></span></li>
                  <li style="display: none;">Longitude: <span id="lon"></span></li>
                </ul>
              </div>

              <div class="row">
                <div class="col-md-3" id="">
                  <label for="exampleInputuname">Longitude</label>
                </div>
                <div class="col-md-9" id="">
                  <input type="text" class="form-control mb-3" id="longitude" name="longitude" readonly="">
                </div>
              </div>
              <div class="row">
                <div class="col-md-3" id="">
                  <label for="exampleInputuname">Latitude</label>
                </div>
                <div class="col-md-9" id="">
                  <input type="text" class="form-control mb-3" id="latitude" name="latitude" readonly="">
                </div>
              </div>
            @endif

            <div class="row">
              <div class="col-md-3">
                <label>{{ translate('Pincode') }}</label>
              </div>
              <div class="col-md-9">
                <input type="text" class="form-control mb-3" placeholder="{{ translate('Pincode') }}"
                  name="postal_code" id="postal_code" value="" required>
              </div>
            </div>

            <div class="row" id="divPhone" style="display:none;">
              <div class="col-md-3">
                <label>{{ translate('Phone') }}</label>
              </div>
              <div class="col-md-9">
                <input type="text" class="form-control mb-3" placeholder="{{ translate('+91') }}" name="phone" id="phone"
                  value="">
              </div>
            </div>
            Choose Preferred Transporter<br/><br/>
              @php
                $warehouses = \App\Models\Warehouse::orderBy('id', 'asc')
                    ->get();
              @endphp
              @foreach ($warehouses as $warehouse)
                <div class="row"  style="padding-bottom: 15px;">
                  <div class="col-md-3">
                    <label>{{ translate($warehouse->name) }}</label>
                  </div>
                  <div class="col-md-9">
                  <select class="form-control form-control-sm aiz-selectpicker" name="shipper_{{ translate($warehouse->id) }}"
                      data-live-search="true"
                      required>
                      <option value="">--Choose your preferred Shipping Carrier--</option>
                      @php
                        $address_shippers = \App\Models\Carrier::where('status', true)
                            ->where('all_india', false)
                            ->orderBy('name', 'asc')
                            ->get();
                      @endphp
                      @foreach ($address_shippers as $carrier)
                        <option value="{{ $carrier->id }}">{{ $carrier->name }}</option>
                      @endforeach
                      @foreach (\App\Models\Carrier::where('status', true)->where('all_india', true)->orderBy('name', 'asc')->get() as $carrier)
                        <option value="{{ $carrier->id }}">{{ $carrier->name }}</option>
                      @endforeach
                      @php
                        $other_shippers = \App\Models\Carrier::where('status', true)->where('all_india', false)->whereNotIn('id', $address_shippers->pluck('id'))->orderBy('name', 'asc')->get();
                      @endphp
                      @foreach ($other_shippers as $carrier)
                        <option value="{{ $carrier->id }}">{{ $carrier->name }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
              @endforeach
            <div class="form-group text-right">
              <button type="submit" class="btn btn-sm btn-primary" id="saveButton">{{ translate('Save') }}</button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="edit-address-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">{{ translate('Edit Address') }}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body" id="edit_modal_body">

      </div>
    </div>
  </div>
</div>

@section('script')
  <script type="text/javascript">
    function add_new_address() {
      $('#new-address-modal').modal('show');
    }

    function edit_address(address) {
      var url = '{{ route('addresses.edit', ':id') }}';
      url = url.replace(':id', address);

      $.ajax({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: 'GET',
        success: function(response) {
          $('#edit_modal_body').html(response.html);
          $('#edit-address-modal').modal('show');
          AIZ.plugins.bootstrapSelect('refresh');

          @if (get_setting('google_map') == 1)
            var lat = -33.8688;
            var long = 151.2195;

            if (response.data.address_data.latitude && response.data.address_data.longitude) {
              lat = parseFloat(response.data.address_data.latitude);
              long = parseFloat(response.data.address_data.longitude);
            }

            initialize(lat, long, 'edit_');
          @endif
        }
      });
    }

    $(document).on('change', '[name=country_id]', function() {
      var country_id = $(this).val();
      get_states(country_id);
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

    $(document).on('keyup', '[name=gstin]', function() {
      var gstin = $(this).val();
      if (gstin.length >= 15) {
        get_gstin_data(gstin);
      }else{
        $('#gstin_err').html('');
        $('#saveButton').prop('disabled', false);
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
          $('#saveButton').prop('disabled', false);
          if(response){
            if (response.hasOwnProperty('error')) {
              // $('#gstin_err').html(response.message);
              $('#gstin_err').html('Invalid GST');
              $('#gstin_success').html('');
              $('#phone-code').val('');
              $('#phone_err').html('');
              $('#saveButton').prop('disabled', true);
            } else {
              $('#gstin_err').html('');
              $.ajax({
                headers: {
                  'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '{{ route("checkGsitnExistOnProfile") }}',
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
                    $('#saveButton').prop('disabled', true);
                    $('#address').removeAttr('readonly');
                    $('#postal_code').removeAttr('readonly');
                    $('#divCity').show();                    
                    $('#city').attr('required', 'required');
                    $('#divCountry').show();
                    $('#country').attr('required', 'required');
                    $('#divState').show();
                    $('#state').attr('required', 'required');
                    // $('#divPhone').show();
                    // $('#phone').attr('required', 'required');
                  }else{
                    $('#gstin_success').html('Valid GST');
                    $('#company_name').val(response.taxpayerInfo.tradeNam);
                    $('#name').val(response.taxpayerInfo.lgnm);
                    // $('#gst_data').val(JSON.stringify(response));
                    var address = (response.taxpayerInfo.pradr.addr.bnm + ', ' + response.taxpayerInfo.pradr.addr.st + ', ' + response.taxpayerInfo.pradr.addr.loc).replace(/^[, ]+|[, ]+$/g, '');                              
                    $('#address').val(address);
                    var address_2 = (response.taxpayerInfo.pradr.addr.bno + ', ' + response.taxpayerInfo.pradr.addr.dst).replace(/^[, ]+|[, ]+$/g, '');                              
                    $('#address_2').val(address_2);
                    
                    $('#postal_code').val(response.taxpayerInfo.pradr.addr.pncd);

                    $('#gstinHelp').html(gstin);
                    $('#companyNameHelp').html(response.taxpayerInfo.tradeNam);
                    $('#namenHelp').html(response.taxpayerInfo.lgnm);
                    $('#addressHelp').html(address);
                    $('#postalCodeHelp').html(response.taxpayerInfo.pradr.addr.pncd);
                    $('#phone-code').val('');
                    $('#phone_err').html('');
                    $('#address_err').html('');
                    // $('#address').attr('readonly', 'readonly');
                    // $('#address_2').attr('readonly', 'readonly');
                    // $('#postal_code').attr('readonly', 'readonly');
                    $('#city').val(response.taxpayerInfo.pradr.addr.loc);
                    $('#divCity').hide();
                    $('#city_field_id').removeAttr('required');
                    $('#divCountry').hide();
                    $('#country').removeAttr('required');
                    $('#divState').hide();
                    $('#state').removeAttr('required');
                    // $('#divPhone').hide();
                    // $('#phone').removeAttr('required');
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
    
  </script>


  @if (get_setting('google_map') == 1)
    @include('frontend.partials.google_map')
  @endif
@endsection
