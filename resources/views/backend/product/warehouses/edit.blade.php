@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{ translate('Warehouse Information') }}</h5>
  </div>

  <div class="col mx-auto">
    <div class="card">
      <div class="card-body p-0">
        <form class="p-4" action="{{ route('warehouses.update', $warehouse->id) }}" method="POST">
          <input name="_method" type="hidden" value="PATCH">
          @csrf
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="name">{{ translate('Name') }} *</label>
            <div class="col-sm-9">
              <input type="text" placeholder="{{ translate('Name') }}" id="name" name="name"
                value="{{ $warehouse->name }}" class="form-control" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="address">{{ translate('Address') }} *</label>
            <div class="col-sm-9">
              <textarea name="address" rows="3" class="form-control" required>{{ $warehouse->address }}</textarea>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="state_id">{{ translate('State') }} *</label>
            <div class="col-sm-9">
              <select class="form-control aiz-selectpicker" data-live-search="true" name="state_id" required>
                @foreach ($states as $key => $state)
                  <option value="{{ $state->id }}" @if ($warehouse->state_id == $state->id) selected @endif>
                    {{ $state->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="city_id">{{ translate('City') }} *</label>
            <div class="col-sm-9">
              <select class="form-control aiz-selectpicker" data-live-search="true" name="city_id" required>
                @foreach ($cities as $key => $city)
                  <option value="{{ $city->id }}" @if ($warehouse->city_id == $city->id) selected @endif>
                    {{ $city->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="pincode">{{ translate('Pincode') }} *</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" name="pincode" value="{{ $warehouse->pincode }}"
                placeholder="{{ translate('Pincode') }}" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="phone">{{ translate('Default Phone') }} *</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" name="phone" value="{{ $warehouse->phone }}"
                placeholder="{{ translate('Default Phone') }}" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-md-3 col-form-label">{{ translate('Service States') }}</label>
            <div class="col-md-9">
              <select class="select2 form-control aiz-selectpicker" name="service_states[]" data-toggle="select2"
                data-placeholder="Choose ..."data-live-search="true" data-selected="[{{ $warehouse->service_states }}]"
                multiple>
                @foreach ($states as $mstate)
                  <option value="{{ $mstate->id }}">{{ $mstate->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label" for="markups">{{ translate('Markups per Warehouse') }} *</label>
            <div class="col-sm-9 table-responsive">
              @php
                $whs = \App\Models\Warehouse::where('id', '!=', $warehouse->id)->get();
              @endphp
              <table class="table table-bordered">
                <thead>
                  <tr>
                    @php $count = 0; @endphp
                    @foreach ($whs as $wh)
                      <th>
                        <select class="form-control aiz-selectpicker" data-live-search="true"
                          data-placeholder="{{ translate('Select the Warehouse') }}" name="warehouses[]" required>
                          <option value="">{{ translate('Select the Warehouse') }}</option>
                          @foreach ($whs as $swh)
                            <option value="{{ $swh->id }}" @if (isset($warehouse->markup[$count]) && $warehouse->markup[$count]['warehouse_id'] == $swh->id) selected @endif>
                              {{ $swh->name }}</option>
                          @endforeach
                        </select>
                      </th>
                      @php $count++; @endphp
                    @endforeach
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    @foreach ($whs as $wh)
                      <td>
                        <input type="text" class="form-control" name="markups[]"
                          value="{{ isset($warehouse->markup[$loop->index]['markup']) ? $warehouse->markup[$loop->index]['markup'] : '' }}"
                          placeholder="Markup Percentage" required>
                      </td>
                    @endforeach
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label"
              for="seller_saleszing_id">{{ translate('Seller Saleszing Warehouse Id') }}
              *</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" name="seller_saleszing_id"
                value="{{ $warehouse->seller_saleszing_id }}"
                placeholder="{{ translate('Seller Saleszing Warehouse Id') }}" required>
            </div>
          </div>
          <div class="form-group row">
            <label class="col-sm-3 col-from-label"
              for="inhouse_saleszing_id">{{ translate('Inhouse Saleszing Warehouse Id') }}
              *</label>
            <div class="col-sm-9">
              <input type="text" class="form-control" name="inhouse_saleszing_id"
                value="{{ $warehouse->inhouse_saleszing_id }}"
                placeholder="{{ translate('Inhouse Saleszing Warehouse Id') }}" required>
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
