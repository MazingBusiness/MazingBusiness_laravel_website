<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $direction }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ translate('Purchase Order') }}</title>
    <style media="all">
    @page {
      margin: 0;
      padding: 0;
    }

    body {
      font-size: 0.875rem;
      font-family: '{{ $font_family }}';
      direction: {{ $direction }};
      text-align: {{ $text_align }};
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
      text-align: {{ $text_align }};
    }

    .text-right {
      text-align: {{ $not_text_align }};
    }

    .bank-details-section {
      background-color: #f2f2f2; /* Light grey background */
      padding: 15px;
      border: 1px solid #eceff4; /* Light border */
      border-radius: 5px;
    }

    .bank-details-section td {
      vertical-align: top;
    }

    .bank-details-section strong {
      font-size: 1rem;
      color: #333;
    }

    .bank-details-section span {
      display: block;
      color: #333;
    }

    .terms-conditions-section {
      background-color: #f7f7f7; /* Soft light grey background */
      padding: 20px;
      border: 1px solid #ddd; /* Light border */
      border-radius: 10px;
      margin-top: 30px;
      font-size: 0.875rem;
      line-height: 1.6;
      color: #444;
    }

    .terms-conditions-section h4 {
      margin-bottom: 15px;
      font-size: 1.2rem;
      color: #222;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      border-bottom: 2px solid #074E86;
      padding-bottom: 5px;
    }

    .terms-conditions-section ul {
      padding-left: 20px;
    }

    .terms-conditions-section li {
      margin-bottom: 10px;
    }
  </style>
</head>

<body>
    <div>
        <div style="font-size: 1.5rem; padding: 15px; text-align:center; font-weight: bold;">
            {{ translate('Purchase Order') }}
        </div>
        <div style="background: #074E86; padding: 1rem; color: white !important;">
            <table style="width: 100%; border-collapse: collapse;" class="text-white">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <div style="font-size: 1.5rem; margin-bottom: 20px; font-weight: bold;">
                            ACE TOOLS PRIVATE LIMITED
                        </div>
                        <div style="font-size: 0.875rem; line-height: 1.5;">
                            PLOT NO. 220/219 KH NO. 85/2,<br>
                            RITHALA ROAD,<br>
                            New Delhi – 110085
                        </div>
                        <div style="font-size: 0.875rem; margin-top: 10px;">
                            {{ translate('GSTIN') }}: 07ABACA4198B1ZX
                        </div>
                    </td>
                    <td style="width: 50%; text-align: center; vertical-align: top;">
                        @if (isset($logo))
                            <img src="https://mazingbusiness.com/public/uploads/all/PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png" height="30" style="display:inline-block; margin-bottom: 20px;">
                        @else
                            <img src="https://mazingbusiness.com/public/uploads/all/PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png" height="30" style="display:inline-block; margin-bottom: 20px;">
                        @endif
                        <div style="margin-top: 20px; text-align: center;">
                            <img src="https://admin.mazingbusiness.com/assets/pdf/mazing_business_qr.jpg" alt="Scan QR Code" style="width: 100px; height: 100px; display: block; margin: 0 auto;">
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div style="padding: 1rem; padding-bottom: 30px;">
            <table>
                <tr>
                    <td class="strong small gry-color">
                        <div style="width: 100%">
                            <div style="font-weight: bold;">
                                {{ translate('Seller Info:') }}<br><br>
                            </div>
                            {{ $sellerInfo['seller_name'] }}<br>
                            {{ $sellerInfo['seller_address'] }}<br>
                            {{ translate('Phone') }}: {{ $sellerInfo['seller_phone'] }}<br>
                            {{ translate('GSTIN') }}: {{ $sellerInfo['seller_gstin'] }}
                        </div>
                    </td>
                    <td style="background: #eceff4; padding: 1rem;">
                        <table>
                            <tr>
                                <td colspan="2" style="padding-bottom: 15px;text-align: center;"><span
                                    style="font-weight: bold">{{ translate('Purchase Order No.') }}:</span>
                                    {{ $order->purchase_order_no }}
                                </td>
                            </tr>
                            <tr>
                                <td>{{ translate('Order Date') }}</td>
                                <td class="text-right">{{ date('d/m/Y', strtotime($order->date)) }}</td>
                            </tr>
                            <tr>
                                <td>{{ translate('Amount') }}</td>
                                <td class="text-right">₹ {{ number_format(collect($productInfo)->sum('subtotal'), 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <div style="padding: 1rem;">
            <table class="padding text-left small border-bottom">
                <thead>
                    <tr class="gry-color" style="background: #eceff4;">
                        <th width="5%" class="text-left">{{ translate('No.') }}</th>
                        <th width="10%" class="text-left">{{ translate('Part No.') }}</th>
                        <th width="12%" class="text-left">{{ translate('Product Name') }}</th>
                        <th width="12%" class="text-left">{{ translate('Image') }}</th>
                        <th width="10%" class="text-left">{{ translate('Quantity') }}</th>
                        {{-- <th width="15%" class="text-left">{{ translate('Rate') }}</th> --}}
                        <th width="25%" class="text-right">{{ translate('Sub Total') }}</th>
                    </tr>
                </thead>
                  <tbody>
                    @foreach ($productInfo as $key => $item)
                        @php
                            $product = \DB::table('products')->where('part_no', $item->part_no)->first();
                        @endphp

                        <tr>
                            <td class="text-left">{{ $key + 1 }}</td>
                            <td class="text-left">{{ $item->part_no }}</td>
                            <td class="text-left">{{ $item->product_name }}</td>
                            <td>
                                <img style="width:50px;height:50px;" src="{{ uploaded_asset($product->thumbnail_img) }}" 
                                     onerror="this.onerror=null;this.src='{{ static_asset("assets/img/placeholder.jpg") }}';">
                            </td>
                            <td>{{ $item->qty }}</td>
                            <td class="text-right">{{ number_format($item->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="padding: 1rem;">
            <table class="padding text-right small">
                <tr>
                    <td style="width: 70%;"></td>
                    <td style="width: 30%; padding: 15px 0;">
                        <table style="width: 100%;">
                            <tr class="border-bottom">
                                <th style="text-align: left;">{{ translate('Grand Total') }}</th>
                                <td>{{ number_format(collect($productInfo)->sum('subtotal'), 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
