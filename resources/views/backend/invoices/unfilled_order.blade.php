<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ translate('PROFORMA INVOICE') }}</title>
  <meta http-equiv="Content-Type" content="text/html;" />
  <meta charset="UTF-8">
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
  {{-- ✅ PDF CONTENT BLOCK — TOP (placement = first) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'first')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif
  <div>
    <div style="font-size: 1.5rem; padding: 15px; text-align:center; font-weight: bold;">
      {{ translate('Waiting For Purchase') }}
    </div>
    <div style="background: #074E86; padding: 1rem; color: white !important;">
      <table style="width: 100%; border-collapse: collapse;">
        <tr>
          <td style="width: 50%; vertical-align: top; color: white;">
            <div style="font-size: 1.5rem; margin-bottom: 20px; font-weight: bold;">
              ACE TOOLS PRIVATE LIMITED
            </div>
            <br>
            <div style="font-size: 0.875rem; line-height: 1.5;">
              PLOT NO. 220/219 KH NO. 85/2,<br>
              RITHALA ROAD,<br>
              New Delhi – 110085
            </div>
            <div style="font-size: 0.875rem; margin-top: 10px;">
              {{ translate('GSTIN') }}: 07ABACA4198B1ZX
            </div>
          </td>
          <td style="width: 50%; text-align: center; vertical-align: top; color: white;">
            @if (isset($logo))
              <img src="{{ $logo }}" height="30" style="display:inline-block; margin-bottom: 20px;">
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

    <div style="padding: 1rem;padding-bottom: 30px">
      <table>
        <tr>
          @php
            $client_address = $cart_items->first();
          @endphp
          <td class="strong small gry-color">
            <div style="width: 100%">
              <div style="font-weight: bold;">
                {{ translate('Customer Info:') }}<br><br>
              </div>
              {{ $client_address->company_name }}<br>
              {{ $client_address->address }},<br>
              {{ $client_address->address_2 }}<br>
              {{ $client_address->city }}<br>
              {{ translate('Phone') }}: {{ $client_address->phone }}
            </div>
          </td>
          
        </tr>
      </table>
    </div>

    <div style="padding: 1rem;">
      <table class="padding text-left small border-bottom">
        <thead>
          <tr class="gry-color" style="background: #eceff4;">
            <th width="5%" class="text-left">{{ translate('No.') }}</th>
            <th width="12%" class="text-left">{{ translate('Product Name') }}</th>
            <th width="10%" class="text-left">{{ translate('Quantity') }}</th>
            <th width="15%" class="text-left">{{ translate('Rate') }}</th>
            <th width="25%" class="text-right">{{ translate('Sub Total') }}</th>
          </tr>
        </thead>
        <tbody class="strong">
          @foreach ($cart_items as $key => $item)
              <tr>
                <td class="text-left">{{ $key + 1 }}</td>
               <td class="text-left">
                  <div style="display: block; line-height: 1.2;">
                      {{ $item->product_name }}
                  </div>
                  @if(DB::table('products_api')->where('part_no', $item->part_no)->where('closing_stock', '>', 0)->exists())
                      <div style="margin-top: 5px;">
                          <img src="{{ asset('public/uploads/fast_dispatch.jpg') }}" 
                               alt="Fast Delivery" 
                               style="width: 70px; height: 18px; display: block;">
                      </div>
                  @endif
               </td>

                <td>{{ $item->quantity }}</td>
                <td class="text-left">{{ '₹ ' .number_format($item->price, 2) }}</td>
                <td class="text-right">{{ '₹ ' .number_format($item->total, 2) }}</td>
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
                <td>{{ '₹ ' .number_format($cart_items->sum('total'), 2) }}</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </div>

    <div style="padding: 0 1.5rem;">
      <table class="bank-details-section" style="width: 100%; margin-top: 20px;">
        <tr>
          <td style="width: 50%; padding: 15px;">
            <strong>Bank Details:</strong><br>
            <span>A/C Name: ACE TOOLS PRIVATE LIMITED</span><br>
            <span>A/C No: 235605001202</span><br>
            <span>IFSC Code: ICIC0002356</span><br>
            <span>Bank Name: ICIC Bank</span>
          </td>
          <td style="width: 50%; text-align: right; padding: 15px;">
            <img src="https://mazingbusiness.com/public/assets/img/barcode.png" alt="Scan QR Code" style="width: 100px; height: 100px;">
            <br><span style="font-size: 12px;">Scan the barcode with any UPI app to pay.</span>
          </td>
        </tr>
      </table>
    </div>

    <div class="terms-conditions-section">
      <h4>Terms and Conditions</h4>
      <ul>
        <li>Payment must be made within the credit periods mentioned above , if the credit periods is zero the payment has to made before dispatch.</li>
        <li>Goods once sold will not be taken back or exchanged.</li>
        <li>Any disputes arising will be subject to New Delhi jurisdiction only.</li>
       
      </ul>

      {{-- ✅ PDF CONTENT BLOCK — BOTTOM (placement = last) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'last')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif
    </div>

  </div>
  
</body>
</html>
