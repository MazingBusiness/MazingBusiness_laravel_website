<html>

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PROFORMA INVOICE</title>
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
      table-layout: fixed; /* Ensures consistent table column width */
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
      background-color: #f2f2f2;
      padding: 2px;
      border: 1px solid #eceff4;
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
      background-color: #f7f7f7;
      padding: 5px;
      border: 1px solid #ddd;
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
      margin-bottom: 5px;
    }
  </style>
</head>

<body>
  <div>

    <div style="font-size: 1.25rem; padding: 10px; text-align:center; font-weight: bold;">
  PROFORMA INVOICE
</div>
<div style="background: #074E86; padding: 0.5rem; color: white !important;">
  <table style="width: 100%; border-collapse: collapse;">
    <tr>
      <td style="width: 50%; vertical-align: top; color: white;">
        <div style="font-size: 1.25rem; margin-bottom: 10px; font-weight: bold;">
          ACE TOOLS PRIVATE LIMITED
        </div>
        <br>
        <div style="font-size: 0.75rem; line-height: 1.2;">
          PLOT NO. 220/219 KH NO. 85/2,<br>
          RITHALA ROAD,<br>
          New Delhi – 110085
        </div>
        <div style="font-size: 0.75rem; margin-top: 5px;">
          GSTIN: 07ABACA4198B1ZX
        </div>
      </td>
      <td style="width: 50%; text-align: center; vertical-align: top; color: white;">
        <img src="https://mazingbusiness.com/public/uploads/all/PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png" height="25" style="display:inline-block; margin-bottom: 10px;">
        <div style="margin-top: 10px; text-align: center;">
          <img src="https://admin.mazingbusiness.com/assets/pdf/mazing_business_qr.jpg" alt="Scan QR Code" style="width: 80px; height: 80px; display: block; margin: 0 auto;">
        </div>
      </td>
    </tr>
  </table>
</div>


    <div style="padding: 1rem;padding-bottom: 30px">
      <table>
        <tr>
          <td class="strong small gry-color">
            <div style="width: 100%">
              <div style="font-weight: bold;">Customer Info:<br><br></div>
              {{ $client_address->company_name }}<br>
              @if (isset($client_address->address))
               {{ $client_address->address }}<br> 
              @endif
              
              @if (isset($client_address->city))
                {{ $client_address->city }}
              @endif
              {{ $client_address->postal_code }}<br>
              GSTIN: {{ $client_address->gstin }}<br>
              Phone: {{ $client_address->phone }}
            </div>
          </td>
          <td style="background: #eceff4; padding: 1rem;">
            <table>
              <tr>
                <td colspan="2" style="padding-bottom: 15px;text-align: center;"><span style="font-weight: bold">Proforma Invoice No.:</span>
                  {{ $order->order_code }}
                </td>
              </tr>
              <tr>
                <td>Order Date</td>
                <td class="text-right">{{ date('d/m/Y', strtotime($order->created_at)) }}</td>
              </tr>
              <tr>
                <td>Total Amount</td>
                <td class="text-right">
                  {{ $order->currency.' '.$order->grand_total }}
                 
                </td>
              </tr>
             
            </table>
          </td>
      </table>
    </div>

    <div style="padding: 1rem;">
      <table class="padding text-left small border-bottom">
        <thead>
          <tr class="gry-color" style="background: #eceff4;">
            <th width="5%" class="text-left">No.</th>
            <th width="12%" class="text-left">Part NO.</th>
            <th width="12%" class="text-left">Photo</th>
            <th width="25%" class="text-left">Product Name</th>
            <th width="10%" class="text-left">Quantity</th>
            <th width="15%" class="text-left">Rate</th>
            <th width="25%" class="text-right">Sub Total</th>
          </tr>
        </thead>
        <tbody class="strong">
          @foreach ($order_details as $key => $orderDetail)
            <tr>
              <td class="text-left">{{ $key + 1 }}</td>
              <td class="text-left">{{ $orderDetail->part_no }}</td>
              <td class="text-left">
                @php
                  $thumbnailId = $orderDetail->photos;

                  if ($thumbnailId == null) {
                     $thumbnailPath = asset('assets/img/placeholder.jpg');
                  } 
                @endphp
                <img style="width:50px;height:50px;" class="img-fit lazyload mx-auto" src="{{ uploaded_asset($thumbnailId) }}" alt="Product Image">
              </td>
              <td class="text-left">{{ $orderDetail->name }}</td>
              <td class="text-left">{{ $orderDetail->quantity }}</td>
              <td class="text-left">
               {{ $order->currency.' '.$orderDetail->unit_price }}
                
              </td>
              <td class="text-right">
               
                {{ $order->currency.' '.$orderDetail->total_price }}
               
              </td>
            </tr>
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
            <td class="text-left"></td>
            <td>
              <table class="text-right sm-padding small strong">
                <tbody>
                  <tr class="border-bottom">
                    <th class="text-left strong">Grand Total</th>
                    <td class="currency">
                      {{ $order->currency.' '.$order->grand_total }}
                    </td>
                  </tr>

                  <tr class="border-bottom">
                    <th class="text-left strong">Advance</th>
                    <td class="currency">
                      {{ $order->currency.' '.$order->advance_amount }}
                    </td>
                  </tr>

                  <tr class="border-bottom">
                    <th class="text-left strong">Balance</th>
                    <td class="currency">
                      {{ $order->currency.' '.($order->grand_total - $order->advance_amount) }}
                    </td>
                  </tr>

                  
                </tbody>
              </table>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- <div style="padding: 1.5rem; margin-top: 20px; border: 1px solid #eceff4; background-color: #f9f9f9; border-radius: 10px;">
      <table style="width: 100%; border-collapse: collapse;">
        <tr>
          <td style="width: 50%; vertical-align: top; padding: 15px;">
            <div style="font-size: 1.25rem; font-weight: bold; color: #074E86; margin-bottom: 10px;">
              Bank Details
            </div>
            <div style="font-size: 1rem; line-height: 1.8; color: #333;">
              <span><strong>Account Name:</strong> ACE TOOLS PRIVATE LIMITED</span><br>
              <span><strong>Account No:</strong> 235605001202</span><br>
              <span><strong>IFSC Code:</strong> ICIC0002356</span><br>
              <span><strong>Bank Name:</strong> ICICI Bank</span><br>
              <span><strong>Branch:</strong> New Delhi</span>
            </div>
          </td>
          <td style="width: 50%; vertical-align: top; text-align: center; padding: 15px;">
            <div style="margin-bottom: 10px;">
              <img src="https://mazingbusiness.com/public/assets/img/barcode.png" alt="Scan QR Code" style="width: 100px; height: 100px;">
            </div>
            <div style="font-size: 0.875rem; color: #555;">
              <strong>Scan the QR code with any UPI app to pay.</strong><br>
              <span style="display: block; margin-top: 5px; color: #074E86;">
                Fast & Secure Payment
              </span>
            </div>
          </td>
        </tr>
      </table>
    </div> -->
    <div style="padding: 1.5rem; margin-top: 20px; border: 1px solid #eceff4; background-color: #f9f9f9; border-radius: 10px;">
  <table style="width: 100%; border-collapse: collapse;">
    <tr>
      <td style="width: 50%; vertical-align: top; padding: 5px;">
        <div style="font-size: 1rem; font-weight: bold; color: #074E86; margin-bottom: 5px;">
          Bank Details
        </div>
        <div style="font-size: 0.875rem; line-height: 1.5; color: #333;">
          <span><strong>Account Name:</strong> ACE TOOLS PRIVATE LIMITED</span><br>
          <span><strong>Account No:</strong> 235605001202</span><br>
          <span><strong>IFSC Code:</strong> ICIC0002356</span><br>
          <span><strong>Bank Name:</strong> ICICI Bank</span><br>
          <span><strong>Branch:</strong> New Delhi</span>
        </div>
      </td>
      <td style="width: 50%; vertical-align: top; text-align: center; padding: 5px;">
        <div style="margin-bottom: 5px;">
          <img src="https://mazingbusiness.com/public/assets/img/barcode.png" alt="Scan QR Code" style="width: 80px; height: 80px;">
        </div>
        <div style="font-size: 0.75rem; color: #555;">
          <strong>Scan the QR code with any UPI app to pay.</strong><br>
          <span style="display: block; margin-top: 3px; color: #074E86;">
            Fast & Secure Payment
          </span>
        </div>
      </td>
    </tr>
  </table>
</div>


    <div class="terms-conditions-section" style="padding: 5px; margin-top: 10px; border: 1px solid #ddd; background-color: #f7f7f7; border-radius: 5px;">
  <h4 style="margin-bottom: 5px; font-size: 1rem; color: #222; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #074E86; padding-bottom: 3px;">
    Terms and Conditions
  </h4>
  <ul style="padding-left: 15px; font-size: 0.75rem; line-height: 1.4; color: #444;">
    <li style="margin-bottom: 3px;">RATES VALID ON CURRENT DOLLAR RATES/ IF DOLLAR FLUCTUATES PRICE MAY VARY
</li>
    <li style="margin-bottom: 3px;">Goods once sold will not be taken back or exchanged.</li>
    <li>Any disputes arising will be subject to New Delhi jurisdiction only.</li>
  </ul>
</div>

  </div>
</body>

</html>
