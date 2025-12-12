@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <h1 class="mb-0 h6">{{ translate('Edit Customer Details') }}</h5>
  </div>
  <div class="">
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif
    <form class="form form-horizontal mar-top" action="{{ route('customers.update', $user->id) }}" method="POST"
      enctype="multipart/form-data" id="choice_form">
      <div class="row gutters-5">
        <div class="col-12">
          <input type="hidden" name="id" value="{{ $user->id }}">
          <input name="_method" type="hidden" value="PATCH">
          @csrf
          <div class="card">
            <div class="card-body">
              <div class="form-group row">
                <label class="col-sm-3 col-from-label" for="markups">{{ translate('Shippers per Warehouse') }} *</label>
                <div class="col-sm-9 table-responsive">
                  @php
                    $whs = \App\Models\Warehouse::select('id', 'name')->get();
                    $carriers = \App\Models\Carrier::select('id', 'name')->get();
                  @endphp
                  <table class="table table-bordered">
                    <thead>
                      <tr>
                        @foreach ($user->shipper_allocation as $sa)
                          <th>
                            {{ $whs->where('id', $sa['warehouse_id'])->first()->name }}
                          </th>
                        @endforeach
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        @foreach ($user->shipper_allocation as $sa)
                          <td>
                            <select class="form-control aiz-selectpicker" data-live-search="true"
                              data-placeholder="{{ translate('Select Carrier') }}" name="carrier_id[]"
                              data-selected="[{{ $sa['carrier_id'] }}]" required>
                              <option value="">{{ translate('Select Carrier') }}</option>
                              @foreach ($carriers as $carrier)
                                <option value="{{ $carrier->id }}">
                                  {{ $carrier->name }}</option>
                              @endforeach
                            </select>
                          </td>
                        @endforeach
                      </tr>
                      <tr>
                        @foreach ($user->shipper_allocation as $sa)
                          <td>
                            <input type="text" class="form-control" name="carrier_name[]"
                              value="{{ $sa['carrier_name'] }}" placeholder="Other Carrier Name"
                              style="min-width: 200px;">
                          </td>
                        @endforeach
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <button type="submit" name="button"
                class="btn btn-info float-right">{{ translate('Update Customer Details') }}</button>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>

@endsection

@section('script')
  <script type="text/javascript">
    $(document).ready(function() {
      show_hide_shipping_div();
    });

    $("[name=shipping_type]").on("change", function() {
      show_hide_shipping_div();
    });

    function show_hide_shipping_div() {
      var shipping_val = $("[name=shipping_type]:checked").val();

      $(".flat_rate_shipping_div").hide();

      if (shipping_val == 'flat_rate') {
        $(".flat_rate_shipping_div").show();
      }
    }

    $('input[name="colors_active"]').on('change', function() {
      if (!$('input[name="colors_active"]').is(':checked')) {
        $('#colors').prop('disabled', true);
        AIZ.plugins.bootstrapSelect('refresh');
      } else {
        $('#colors').prop('disabled', false);
        AIZ.plugins.bootstrapSelect('refresh');
      }
      update_sku();
    });

    $(document).on("change", ".attribute_choice", function() {
      update_sku();
    });

    $('#colors').on('change', function() {
      update_sku();
    });

    function delete_row(em) {
      $(em).closest('.form-group').remove();
      update_sku();
    }

    function delete_variant(em) {
      $(em).closest('.variant').remove();
    }

    AIZ.plugins.tagify();

    $(document).ready(function() {
      update_sku();

      $('.remove-files').on('click', function() {
        $(this).parents(".col-md-4").remove();
      });
    });
  </script>
@endsection
