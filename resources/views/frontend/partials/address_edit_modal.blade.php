<form class="form-default" role="form" action="{{ route('addresses.update', $address_data->id) }}" method="POST">
  @csrf
  <div class="p-3">
    <div class="row">
      <div class="col-md-2">
        <label>{{ translate('Company Name') }}</label>
      </div>
      <div class="col-md-10">
        <input type="text" class="form-control mb-3" placeholder="{{ translate('Company Name') }}"
          value="{{ $address_data->company_name }}" name="company_name" value="" required>
      </div>
    </div>
    <div class="row">
      <div class="col-md-2">
        <label>{{ translate('GSTIN') }}</label>
      </div>
      <div class="col-md-10">
        <input type="text" class="form-control mb-3" placeholder="{{ translate('GSTIN') }}"
          value="{{ $address_data->gstin }}" name="gstin" value=""
          @if ($address_data->gstin) disabled="disbled" readonly="readonly" @endif>
      </div>
    </div>
    <div class="row">
      <div class="col-md-2">
        <label>{{ translate('Address') }}</label>
      </div>
      <div class="col-md-10">
        <textarea class="form-control mb-3" placeholder="{{ translate('Your Address') }}" rows="2" name="address"
          required>{{ $address_data->address }}</textarea>
      </div>
    </div>
    <div class="row">
      <div class="col-md-2">
        <label>{{ translate('Address 2') }}</label>
      </div>
      <div class="col-md-10">
        <textarea class="form-control mb-3" placeholder="{{ translate('Your Address 2') }}" rows="2" name="address_2"
          required>{{ $address_data->address_2 }}</textarea>
      </div>
    </div>
    <div class="row">
      <div class="col-md-2">
        <label>{{ translate('Country') }}</label>
      </div>
      <div class="col-md-10">
        <div class="mb-3">
          <select class="form-control aiz-selectpicker" data-live-search="true"
            data-placeholder="{{ translate('Select your country') }}" name="country_id" id="edit_country" required>
            <option value="">{{ translate('Select your country') }}</option>
            @foreach (\App\Models\Country::where('status', 1)->get() as $key => $country)
              <option value="{{ $country->id }}" @if ($address_data->country_id == $country->id) selected @endif>
                {{ $country->name }}
              </option>
            @endforeach
          </select>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-2">
        <label>{{ translate('State') }}</label>
      </div>
      <div class="col-md-10">
        <select class="form-control mb-3 aiz-selectpicker" name="state_id" id="edit_state" data-live-search="true"
          required>
          @foreach ($states as $key => $state)
            <option value="{{ $state->id }}" @if ($address_data->state_id == $state->id) selected @endif>
              {{ $state->name }}
            </option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="row">
      <div class="col-md-2">
        <label>{{ translate('City') }}</label>
      </div>
      <div class="col-md-10">
        <select class="form-control mb-3 aiz-selectpicker" data-live-search="true" name="city_id" required>
          @foreach ($cities as $key => $city)
            <option value="{{ $city->id }}" @if ($address_data->city_id == $city->id) selected @endif>
              {{ $city->name }}
            </option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="row">
      <div class="col-md-2">
        <label>{{ translate('City Name') }}</label>
      </div>
      <div class="col-md-10">
        <input type="text" class="form-control mb-3" placeholder="{{ translate('City Name') }}"
      value="{{ $address_data->city }}" name="city" value="{{ $address_data->city }}" required>
      </div>
    </div>
    @if (get_setting('google_map') == 1)
      <div class="row">
        <input id="edit_searchInput" class="controls" type="text" placeholder="Enter a location">
        <div id="edit_map"></div>
        <ul id="geoData">
          <li style="display: none;">Full Address: <span id="location"></span></li>
          <li style="display: none;">Postal Code: <span id="postal_code"></span></li>
          <li style="display: none;">Country: <span id="country"></span></li>
          <li style="display: none;">Latitude: <span id="lat"></span></li>
          <li style="display: none;">Longitude: <span id="lon"></span></li>
        </ul>
      </div>

      <div class="row">
        <div class="col-md-2" id="">
          <label for="exampleInputuname">Longitude</label>
        </div>
        <div class="col-md-10" id="">
          <input type="text" class="form-control mb-3" id="edit_longitude" name="longitude"
            value="{{ $address_data->longitude }}" readonly="">
        </div>
      </div>
      <div class="row">
        <div class="col-md-2" id="">
          <label for="exampleInputuname">Latitude</label>
        </div>
        <div class="col-md-10" id="">
          <input type="text" class="form-control mb-3" id="edit_latitude" name="latitude"
            value="{{ $address_data->latitude }}" readonly="">
        </div>
      </div>
    @endif

    <div class="row">
      <div class="col-md-2">
        <label>{{ translate('Postal code') }}</label>
      </div>
      <div class="col-md-10">
        <input type="text" class="form-control mb-3" placeholder="{{ translate('Your Postal Code') }}"
          value="{{ $address_data->postal_code }}" name="postal_code" value="" required>
      </div>
    </div>
    <div class="row">
      <div class="col-md-2">
        <label>{{ translate('Phone') }}</label>
      </div>
      <div class="col-md-10">
        <input type="text" class="form-control mb-3" placeholder="{{ translate('+91') }}"
          value="{{ $address_data->phone }}" name="phone" value="" required>
      </div>
    </div>
    Choose Preferred Transporter<br/><br/>
      @php
        $warehouses = \App\Models\Warehouse::orderBy('id', 'asc')
            ->get();
      @endphp
      @foreach ($warehouses as $warehouse)
        <div class="row" style="padding-bottom: 15px;">
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
      <button type="submit" class="btn btn-sm btn-primary">{{ translate('Save') }}</button>
    </div>
  </div>
</form>
