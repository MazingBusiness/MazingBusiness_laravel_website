<html>

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ translate('INVOICE') }}</title>
  <meta http-equiv="Content-Type" content="text/html;" />
  <meta charset="UTF-8">
  <style media="all">
    @page {
      margin: 0;
      padding: 0;
    }

    body {
      font-size: 0.875rem;
      font-family: '<?php echo $font_family; ?>';
      direction: <?php echo $direction; ?>;
      text-align: <?php echo $text_align; ?>;
      padding: 0;
      margin: 0;
    }

    .text-white *,
    .text-white {
      color: white;
    }

    table {
      width: 100%;
    }

    table.padding th {
      padding: .25rem .7rem;
    }

    table.padding td {
      padding: .25rem .7rem;
    }

    table.sm-padding td {
      padding: .1rem .7rem;
    }

    .border-bottom td,
    .border-bottom th {
      border-bottom: 1px solid #eceff4;
    }

    .text-left {
      text-align: <?php echo $text_align; ?>;
    }

    .text-right {
      text-align: <?php echo $not_text_align; ?>;
    }
  </style>
</head>

<body>
  <div>

    @php
      $logo = get_setting('header_logo');
    @endphp
    <div style="font-size: 1.5rem; padding: 15px; text-align:center; font-weight: bold;">
      {{ translate('INVOICE') }}
    </div>
    <div style="background: #074E86; padding: 1rem;">
      <table>
        <tr>
          <td width="50%">
            <div style="font-size: 1.5rem; margin-bottom: 20px; color: white; font-weight: bold;">MAZING RETAIL PRIVATE
              LIMITED<br><br></div>
            <div class="text-white small">71/6 A, Ground Floor,<br>Rama Road Industrial Area,<br>New Delhi – 110015
            </div>
            <div class="text-white small">{{ translate('GSTIN') }}: 07AAOCM7588A1Z3</div>
          </td>
          <td width="50%" class="text-right">
            @if ($logo != null)
              <img src="{{ uploaded_asset($logo) }}" height="30" style="display:inline-block;">
            @else
              <img src="{{ static_asset('assets/img/logo.png') }}" height="30" style="display:inline-block;">
            @endif
          </td>
        </tr>
      </table>
    </div>

    <div style="padding: 1rem;padding-bottom: 30px">
      <table>
        <tr>
          @php
            $shipping_address = json_decode($order->shipping_address);
          @endphp
          <td class="strong small gry-color">
            <div style="width: 100%">
              <div style="font-weight: bold;">{{ translate('Customer Info:') }}<br><br></div>
              {{ $shipping_address->name }}<br>
              {{ $order->user->company_name }}<br>
              {{ $shipping_address->address }}, {{ $shipping_address->city }}, @if (isset(json_decode($order->shipping_address)->state))
                {{ json_decode($order->shipping_address)->state }} -
              @endif {{ $shipping_address->postal_code }}, {{ $shipping_address->country }}<br>
              GSTIN: {{ $order->user->gstin }}<br>
              {{ translate('Email') }}: {{ $shipping_address->email }}<br>
              {{ translate('Phone') }}: {{ $shipping_address->phone }}
            </div>
          </td>
          <td style="background: #eceff4; padding: 1rem;">
            <table>
              <tr>
                <td colspan="2" style="padding-bottom: 15px;text-align: center;"><span
                    style="font-weight: bold">{{ translate('Invoice No.') }}:</span>
                  MZ/{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}/{{ date('m') > 3 ? date('y') . '-' . ((int) date('y') + 1) : (int) date('y') - 1 . '-' . date('y') }}
                </td>
              </tr>
              <tr>
                <td>{{ translate('Order ID') }}</td>
                <td class="text-right">{{ $order->id }}</td>
              </tr>
              <tr>
                <td>{{ translate('Order Date') }}</td>
                <td class="text-right">{{ date('d/m/Y', $order->date) }}</td>
              </tr>
              <tr>
                <td>{{ translate('Amount') }}</td>
                <td class="text-right">₹ {{ $order->grand_total }}</td>
              </tr>
            </table>
          </td>
      </table>
    </div>

    <div style="padding: 1rem;">
      <table class="padding text-left small border-bottom">
        <thead>
          <tr class="gry-color" style="background: #eceff4;">
            <th width="35%" class="text-left">{{ translate('Product Name') }}</th>
            <th width="10%" class="text-left">{{ translate('HSN Code') }}</th>
            <th width="10%" class="text-left">{{ translate('Quantity') }}</th>
            <th width="15%" class="text-left">{{ translate('Unit Price') }}</th>
            @if (mb_substr($order->user->gstin, 0, 2) == '07')
              <th width="10%" class="text-left">{{ translate('CGST') }}</th>
              <th width="10%" class="text-left">{{ translate('SGST') }}</th>
            @else
              <th width="10%" class="text-left">{{ translate('IGST') }}</th>
            @endif
            <th width="15%" class="text-right">{{ translate('Total') }}</th>
          </tr>
        </thead>
        <tbody class="strong">
          @foreach ($order->orderDetails as $key => $orderDetail)
            @if ($orderDetail->product != null)
              <tr class="">
                <td>
                  {{ $orderDetail->product->name }}
                  <br>
                  <small>
                    @php
                      $product_stock = json_decode($orderDetail->product->stocks->first(), true);
                    @endphp
                    {{ translate('SKU') }}: {{ $product_stock['part_no'] }}
                  </small>
                </td>
                <td class="">{{ $product_stock['hsncode'] }}</td>
                <td class="">{{ $orderDetail->quantity }}</td>
                <td class="currency">{{ format_price_in_rs($orderDetail->price / $orderDetail->quantity) }}</td>
                <td class="currency">{{ format_price_in_rs($orderDetail->tax / $orderDetail->quantity) }}</td>
                @if (mb_substr($order->user->gstin, 0, 2) == '07')
                  <td class="text-right currency">{{ format_price_in_rs(($orderDetail->price + $orderDetail->tax) / 2) }}
                  </td>
                  <td class="text-right currency">{{ format_price_in_rs(($orderDetail->price + $orderDetail->tax) / 2) }}
                  </td>
                @else
                  <td class="text-right currency">{{ format_price_in_rs($orderDetail->price + $orderDetail->tax) }}</td>
                @endif
              </tr>
            @endif
          @endforeach
        </tbody>
      </table>
    </div>

    <div style="padding:0 1.5rem;">
      <table class="text-right sm-padding small strong">
        <thead>
          <tr>
            <th width="60%"></th>
            <th width="40%"></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="text-left">
              @php
                $removedXML = '<?xml version="1.0" encoding="UTF-8"?>';
              @endphp
              {!! str_replace($removedXML, '', QrCode::size(100)->generate($order->code)) !!}
            </td>
            <td>
              <table class="text-right sm-padding small strong">
                <tbody>
                  <tr>
                    <th class="gry-color text-left">{{ translate('Sub Total') }}</th>
                    <td class="currency">{{ format_price_in_rs($order->orderDetails->sum('price')) }}</td>
                  </tr>
                  <tr>
                    <th class="gry-color text-left">{{ translate('Shipping Cost') }}</th>
                    <td class="currency">{{ format_price_in_rs($order->orderDetails->sum('shipping_cost')) }}</td>
                  </tr>
                  <tr class="border-bottom">
                    <th class="gry-color text-left">{{ translate('Total Tax') }}</th>
                    <td class="currency">{{ format_price_in_rs($order->orderDetails->sum('tax')) }}</td>
                  </tr>
                  <tr class="border-bottom">
                    <th class="gry-color text-left">{{ translate('Payment Discount') }}</th>
                    <td class="currency">{{ format_price_in_rs($order->payment_discount) }}</td>
                  </tr>
                  <tr class="border-bottom">
                    <th class="gry-color text-left">{{ translate('Coupon Discount') }}</th>
                    <td class="currency">{{ format_price_in_rs($order->coupon_discount) }}</td>
                  </tr>
                  <tr>
                    <th class="text-left strong">{{ translate('Grand Total') }}</th>
                    <td class="currency">{{ format_price_in_rs($order->grand_total) }}</td>
                  </tr>
                </tbody>
              </table>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</body>

</html>
