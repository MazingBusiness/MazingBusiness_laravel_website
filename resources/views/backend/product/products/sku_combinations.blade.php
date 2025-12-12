@if (count($combinations[0]) > 0)
  <table class="table table-bordered aiz-table">
    <thead>
      <tr>
        <td class="text-center">
          {{ translate('Product') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('Part No.') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('Model No.') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('Seller SKU') }}
        </td>
        <td class="text-center">
          {{ translate('Base Price') }}
        </td>
        <td class="text-center">
          {{ translate('Carton Price') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('Pieces per Carton') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('Warehouse Quantity') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('Seller Quantity') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('CBM') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('Carton CBM') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('Length') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('Breadth') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('Height') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('Weight') }}
        </td>
        <td class="text-center" data-breakpoints="lg">
          {{ translate('Photo') }}
        </td>
      </tr>
    </thead>
    <tbody>
      @foreach ($combinations as $key => $combination)
        @php
          $sku = '';
          foreach (explode(' ', $product_name) as $key => $value) {
              $sku .= substr($value, 0, 1);
          }
          
          $str = '';
          foreach ($combination as $key => $item) {
              if ($key > 0) {
                  $str .= '-' . str_replace(' ', '', $item);
                  $sku .= '-' . str_replace(' ', '', $item);
              } else {
                  if ($colors_active == 1) {
                      $color_name = \App\Models\Color::where('code', $item)->first()->name;
                      $str .= $color_name;
                      $sku .= '-' . $color_name;
                  } else {
                      $str .= str_replace(' ', '', $item);
                      $sku .= '-' . str_replace(' ', '', $item);
                  }
              }
          }
        @endphp
        @if (strlen($str) > 0)
          <tr class="variant">
            <td>
              <label for="" class="control-label">{{ $str }}</label>
            </td>
            <td>
              <input type="text" name="part_no_{{ $str }}" value="" class="form-control">
            </td>
            <td>
              <input type="text" name="model_no_{{ $str }}" value="" class="form-control">
            </td>
            <td>
              <input type="text" name="seller_sku_{{ $str }}" value="" class="form-control">
            </td>
            <td>
              <input type="number" lang="en" name="price_{{ $str }}" value="{{ $unit_price }}"
                min="0" step="0.01" class="form-control" required>
            </td>
            <td>
              <input type="number" lang="en" name="carton_price_{{ $str }}" value="{{ $unit_price }}"
                min="0" step="0.01" class="form-control" required>
            </td>
            <td>
              <input type="number" lang="en" name="piece_per_carton_{{ $str }}"
                value="{{ $unit_price }}" min="0" step="1" class="form-control" required>
            </td>
            <td>
              <input type="number" lang="en" name="qty_{{ $str }}" value="10" min="0"
                step="1" class="form-control" required>
            </td>
            <td>
              <input type="number" lang="en" name="seller_stock_{{ $str }}" value="10"
                min="0" step="1" class="form-control" required>
            </td>
            <td>
              <input type="number" lang="en" name="cbm_{{ $str }}" value="" min="0"
                step="0.000001" class="form-control" required>
            </td>
            <td>
              <input type="number" lang="en" name="carton_cbm_{{ $str }}" value="" min="0"
                step="0.000001" class="form-control" required>
            </td>
            <td>
              <input type="number" lang="en" name="length_{{ $str }}" value="" min="0"
                step="0.01" class="form-control" required>
            </td>
            <td>
              <input type="number" lang="en" name="breadth_{{ $str }}" value=""
                min="0" step="0.01" class="form-control" required>
            </td>
            <td>
              <input type="number" lang="en" name="height_{{ $str }}" value="" min="0"
                step="0.01" class="form-control" required>
            </td>
            <td>
              <input type="number" lang="en" name="weight_{{ $str }}" value="" min="0"
                step="0.01" class="form-control" required>
            </td>
            <td>
              <div class=" input-group " data-toggle="aizuploader" data-type="image">
                <div class="input-group-prepend">
                  <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
                </div>
                <div class="form-control file-amount text-truncate">{{ translate('Choose File') }}</div>
                <input type="hidden" name="img_{{ $str }}" class="selected-files">
              </div>
              <div class="file-preview box sm"></div>
            </td>
          </tr>
        @endif
      @endforeach
    </tbody>
  </table>
@endif
