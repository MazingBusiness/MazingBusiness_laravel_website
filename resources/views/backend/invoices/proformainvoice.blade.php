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
      border-collapse: collapse; /* ✅ safer for mPDF */
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
      padding: 2px; /* Reduced padding */
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
      padding: 5px; /* Reduced padding */
      border: 1px solid #ddd; /* Light border */
      border-radius: 10px;
      margin-top: 5px;
      font-size: 0.875rem;
      line-height: 1.6;
      color: #444;
    }

    .terms-conditions-section h4 {
      margin-bottom: 10px;
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
      margin-bottom: 5px; /* Reduced margin for a more compact list */
    }
  </style>
</head>

<body>
  {{-- ✅ PDF CONTENT BLOCK — TOP (placement = first) --}}
  @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'first')
      @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
  @endif

  <div>

    @php
      $logo = get_setting('header_logo');
    @endphp

    <div style="font-size: 1.5rem; padding: 15px; text-align:center; font-weight: bold;">
      {{ translate('PROFORMA INVOICE') }}
    </div>

    <div style="background: #074E86; padding: 1rem; color: white !important;">
      <table style="width: 100%;">
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
              <img src="https://mazingbusiness.com/public/uploads/all/PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png"
                   height="30"
                   style="display:inline-block; margin-bottom: 20px;">
            @else
              <img src="https://mazingbusiness.com/public/uploads/all/PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png"
                   height="30"
                   style="display:inline-block; margin-bottom: 20px;">
            @endif

            <div style="margin-top: 20px; text-align: center;">
              <img src="https://admin.mazingbusiness.com/assets/pdf/mazing_business_qr.jpg"
                   alt="Scan QR Code"
                   style="width: 100px; height: 100px; display: block; margin: 0 auto;">
            </div>
          </td>
        </tr>
      </table>
    </div>

    <div style="padding: 1rem; padding-bottom: 30px">
      <table>
        <tr>
          @php
              // Try DB address row
              $addrRow  = DB::table('addresses')->where('user_id', $order->user_id)->first();

              // Fallback to JSON stored in order
              $addrJson = json_decode($order->shipping_address ?: '{}', false);

              // Use whichever is available
              $addr     = $addrRow ?: $addrJson;

              // DB-only values (used in the right table)
              $dueAmount     = $addrRow->due_amount     ?? null;
              $overdueAmount = $addrRow->overdue_amount ?? null;
              $accCode       = $addrRow->acc_code       ?? null;
          @endphp

          <td class="strong small gry-color">
            <div style="width: 100%">
              <div style="font-weight: bold;">{{ translate('Customer Info:') }}<br><br></div>

              {{ $addr->company_name ?? '' }}<br>
              {{ $addr->address ?? '' }}<br>
              {{ $addr->address_2 ?? '' }}<br>
              {{ $addr->city ?? '' }} {{ $addr->postal_code ?? '' }}<br>
              GSTIN: {{ $addr->gstin ?? '' }}<br>
              {{ translate('Phone') }}: {{ $addr->phone ?? '' }}
            </div>
          </td>

          <td style="background: #eceff4; padding: 1rem;">
            <table>
              <tr>
                <td colspan="2" style="padding-bottom: 15px;text-align: center;">
                  <span style="font-weight: bold">{{ translate('Proforma Invoice No.') }}:</span>
                  {{ $order->code }}
                </td>
              </tr>
              <tr>
                <td>{{ translate('Order Date') }}</td>
                <td class="text-right">{{ date('d/m/Y', $order->date) }}</td>
              </tr>
              <tr>
                <td>{{ translate('Amount') }}</td>
                <td class="text-right">₹ {{ $order->grand_total }}</td>
              </tr>

              @if(!is_null($dueAmount))
                  <tr>
                    <td>{{ translate('Due Amount') }}</td>
                    <td class="text-right">₹ {{ $dueAmount }}</td>
                  </tr>
              @endif

              @if(!is_null($overdueAmount))
                  <tr>
                    <td>{{ translate('Overdue Amount') }}</td>
                    <td class="text-right">
                      ₹ {{ $overdueAmount }}
                      @if(!is_null($accCode))
                        ({{ optional(getFirstOverdueDays(encrypt($accCode)))->getData()->overdue_days ?? 'No overdue days' }})
                        <a href="{{ route('downloadStatementForOrder', ['party_code' => encrypt($accCode)]) }}">
                          Download Statement
                        </a>
                      @endif
                    </td>
                  </tr>
              @endif
            </table>
          </td>
        </tr>   {{-- ✅ MISSING </tr> AB ADD KIYA --}}
      </table>
    </div>

    <div style="padding: 1rem;">
      <table class="padding text-left small border-bottom">
        <thead>
          <tr class="gry-color" style="background: #eceff4;">
            <th width="5%" class="text-left">{{ translate('No.') }}</th>
            <th width="12%" class="text-left">{{ translate('Part NO.') }}</th>
            <th width="12%" class="text-left">{{ translate('Photo.') }}</th>
            <th width="25%" class="text-left">{{ translate('Product Name') }}</th>
            <th width="10%" class="text-left">{{ translate('Quantity') }}</th>
            <th width="15%" class="text-left">{{ translate('Rate') }}</th>
            <th width="25%" class="text-right">{{ translate('Sub Total') }}</th>
          </tr>
        </thead>
        <tbody class="strong">
          @foreach ($order->orderDetails as $key => $orderDetail)
            @if ($orderDetail->product != null)
              <tr>
                <td class="text-left">{{ $key + 1 }}</td>
                <td class="text-left">
                  @php
                    $product_stock = json_decode($orderDetail->product->stocks->first(), true);
                  @endphp
                  {{ $product_stock['part_no'] }}
                </td>
                <td>
                  @php
                    // Retrieve the thumbnail image ID from the product
                    $thumbnailId = $orderDetail->product->photos;

                    if($thumbnailId != null){
                        // Fetch the file_name from the uploads table
                        $item = DB::table('uploads')->where('id', $thumbnailId)->first();

                        // Fetch base URL from the .env file
                        $baseUrl = env('UPLOADS_BASE_URL', url('public'));

                        // Construct the thumbnail path
                        $thumbnailPath = $item && $item->file_name
                                        ? $baseUrl . '/' . $item->file_name
                                        : asset('assets/img/placeholder.jpg');
                    }else{
                        $thumbnailPath = asset('assets/img/placeholder.jpg');
                    }
                  @endphp

                  <img style="width:50px;height:50px;" class="img-fit lazyload mx-auto"
                       src="{{ $thumbnailPath }}"
                       data-src=""
                       alt=""
                       onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';">
                </td>
                <td>
                  {{ $orderDetail->product->name }}
                  <br>
                  <small>
                    {{ translate('SKU') }}: {{ $product_stock['part_no'] }}
                  </small>
                  @if($orderDetail->product->cash_and_carry_item == 1 && Auth::check() && optional(Auth::user())->credit_days > 0)
                      <br>
                      <span style="display: inline-block; margin-top: 5px; padding: 2px 5px; background-color: #dc3545; color: #fff; font-size: 10px; border-radius: 3px;">
                          {{ translate('No Credit Item') }}
                      </span>
                  @endif
                </td>
                <td class="">{{ $orderDetail->quantity }}</td>
                <td class="currency">{{ format_price_in_rs($orderDetail->price / $orderDetail->quantity) }}</td>
                <td class="text-right currency">{{ format_price_in_rs($orderDetail->quantity * ($orderDetail->price / $orderDetail->quantity)) }}</td>
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
              <?php
                $removedXML = '<?xml version="1.0" encoding="UTF-8"?>';
              ?>
              <!-- QR code commented -->
            </td>
            <td>
              <table class="text-right sm-padding small strong">
                <tbody>
                  <tr class="border-bottom">
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

    <div style="padding: 0 0.8rem;">
      <table class="bank-details-section" style="width: 100%; margin-top: 5px;">
        <tr>
          <td style="width: 50%; padding: 2px;">
            <strong>Bank Details:</strong><br>
            <span>A/C Name: ACE TOOLS PRIVATE LIMITED</span><br>
            <span>A/C No: 235605001202</span><br>
            <span>IFSC Code: ICIC0002356</span><br>
            <span>Bank Name: ICICI Bank</span>
          </td>
          <td style="width: 50%; text-align: right; padding: 10px;">
            <img src="https://mazingbusiness.com/public/assets/img/barcode.png"
                 alt="Scan QR Code"
                 style="width: 100px; height: 100px;">
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
