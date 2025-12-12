<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <title>Barcode Label</title>

    <style>

      .u-bold{

        text-decoration: underline;

      }

    </style>

</head>

<body style="background: white; font-family: Arial, sans-serif; font-size: 10px; margin: 10px; padding: 0;">



@for ($i = 0; $i < count($barcodes); $i += 2)

    <div style="display: flex; justify-content: center; gap: 20px; margin-bottom: 10px; page-break-inside: avoid;">



        {{-- ============== LABEL A ============== --}}

        <div style="width: 230px; height: 355px; border: 1px solid black; padding: 5px; box-sizing: border-box; display: flex; flex-direction: column; page-break-inside: avoid;">

            <table style="width: 100%; font-size: 10px; font-weight: bold;">

                <tr>

                    <td align="left">

                        <span style="font-size: 8px; font-weight: normal;">
  MRP: ₹{{ number_format($mrp * max(1, (int)$qty), 0) }}
</span><br>

                        <span style="font-size: 8px; font-weight: normal;">(Incl. of all taxes)</span>

                    </td>

                    <td align="right" style="white-space: nowrap;">

                        <span style="font-size: 10px; font-weight: bold;">QTY: {{ $qty }} N</span>

                    </td>

                </tr>

            </table>



            <hr style="margin: 2px 0;">

            <div style="font-size: 8px; margin-top: 2px; line-height: 1.2;">

              <span class="u-bold"><strong>ITEM NAME: {{ $display_name }} ({{ $part_no }})</strong></span>

            </div>

            <hr style="margin: 2px 0;">



            {{-- Barcode + QR (QR visible when NO custom importer company) --}}

            <div style="text-align: center; margin: 4px 0;">

                <img src="data:image/png;base64,{{ $barcodes[$i]['image'] }}" alt="Barcode" style="width: 150px; height: 20px;">

                <div style="font-size: 10px; letter-spacing: 1px; margin-top: 2px;">{{ $barcodes[$i]['code'] }}</div>



                @if (empty($customImporter['company']))

                    <div style="margin-top: 4px;">

                        <img src="https://mazingbusiness.com/public/images/qr.png" width="40" height="40"><br>

                        <div style="font-size: 9px; text-align: center; margin-top: 2px;">Download Our App</div>

                        <div style="font-size: 9px; text-align: center; font-weight: bold;">Visit Our Website</div>

                        <div style="font-size: 9px; text-align: center;">www.mazingbusiness.com</div>

                    </div>

                @endif

            </div>



            <hr style="margin: 2px 0;">



            {{-- Optional: Marketed By (CENTER ALIGNED) --}}

            @if ($marketed_by_checked)

                <div style="text-align: center; font-size: 8px; margin-top: 2px; text-transform: uppercase;">

                    <strong>Marketed By:</strong>

                </div>

                <div style="text-align: center; font-size: 8px; line-height: 1.3; margin-top: 2px;">

                    @if (!empty($marketedBy['company']))

                        <div style="font-size: 9px;">{{ $marketedBy['company'] }}</div>

                    @endif

                    @if (!empty($marketedBy['address']))

                        <div>{{ $marketedBy['address'] }}</div>

                    @endif

                    @if (!empty($marketedBy['phone']))

                        <div>Ph: {{ $marketedBy['phone'] }}</div>

                    @endif

                    @if (!empty($marketedBy['email']))

                        <div>Email: {{ $marketedBy['email'] }}</div>

                    @endif

                </div>

                <hr style="margin: 2px 0;">

            @endif



            {{-- Optional: Imported By --}}

            @if ($imported_by_checked)

                <div style="text-align: center; font-size: 8px; margin-top: 2px; text-transform: uppercase;">

                    <strong>Imported By:</strong>

                </div>



                <div style="text-align: left; font-size: 8px; line-height: 1.3; margin-top: 2px;">

                   @if (!empty($customImporter['company']))
                      <div style="font-size:8px; padding:0; margin:0; line-height:1.05;">
                        <span class="u-bold" style="margin-right:2px;"><strong>Name Of Importer:</strong></span>
                        {{ $customImporter['company'] }} 
                      </div>
                    @endif

                    @if (!empty($customImporter['address']))
                      <table style="width:100%; font-size:8px; line-height:1.25; border-collapse:collapse; margin:0;">
                        <tr>
                          <td style="vertical-align:top; white-space:nowrap; padding:0 2px 0 0;">
                            <span style="font-size:7px;" class="u-bold"><strong>Address:</strong></span>
                          </td>
                          <td style="vertical-align:top; padding:0; font-size:7px;">
                            {{ $customImporter['address'] }}
                          </td>
                        </tr>
                      </table>
                    @endif

                    @if (!empty($customImporter['phone']))
                      <div>
                        <span class="u-bold" style="font-size:7px;"><strong>Customer Care Number:</strong></span>
                        {{ $customImporter['phone'] }}
                      </div>
                    @endif

                    @if (!empty($customImporter['email']))

                        <div><span style="font-size:7px;" class="u-bold"><strong>Email Id:</strong></span> {{ $customImporter['email'] }}</div>

                    @endif

                    @if (!empty($country_of_origin))

                        <div><span style="font-size:7px;" class="u-bold"><strong>Country Of Origin:</strong></span> {{ $country_of_origin }}</div>

                    @endif

                    <div><span class="u-bold"><strong>Quantity:</strong></span> {{ $qty }} PCS.</div>

                    @if (!empty($mfg_month_year))

                        <div><span class="u-bold"><strong>Month & Year Of Mfg.:</strong></span> {{ $mfg_month_year }}</div>

                    @endif

                </div>

                <hr style="margin: 2px 0;">

            @endif



            {{-- ⬇️ Fixed single bottom barcode (always) --}}

            <div style="text-align: center; margin-top: auto; padding-top: 2px;">

                <img src="data:image/png;base64,{{ $barcodes[$i]['image'] }}" alt="Barcode" style="width: 150px; height: 20px;">

                <div style="font-size: 10px; letter-spacing: 1px; margin-top: 2px;">{{ $barcodes[$i]['code'] }}</div>

            </div>

            <div style="text-align: center; margin-top: auto; padding-top: 2px;">

                <img src="data:image/png;base64,{{ $barcodes[$i]['image'] }}" alt="Barcode" style="width: 150px; height: 20px;">

                <div style="font-size: 10px; letter-spacing: 1px; margin-top: 2px;">{{ $barcodes[$i]['code'] }}</div>

            </div>

        </div>



        {{-- ============== LABEL B ============== --}}

        @if ($i + 1 < count($barcodes))

        <div style="width: 230px; height: 355px; border: 1px solid black; padding: 5px; box-sizing: border-box; display: flex; flex-direction: column; page-break-inside: avoid;">

            <table style="width: 100%; font-size: 10px; font-weight: bold;">

                <tr>

                    <td align="left">

                        <span style="font-size: 8px; font-weight: normal;">
  MRP: ₹{{ number_format($mrp * max(1, (int)$qty), 0) }}
</span><br>

                        <span style="font-size: 8px; font-weight: normal;">(Incl. of all taxes)</span>

                    </td>

                    <td align="right" style="white-space: nowrap;">

                        <span style="font-size: 10px; font-weight: bold;">QTY: {{ $qty }} N</span>

                    </td>

                </tr>

            </table>



            <hr style="margin: 2px 0;">

            <div style="font-size: 8px; margin-top: 2px; line-height: 1.2;">

              <span class="u-bold"><strong>ITEM NAME: {{ $display_name }} ({{ $part_no }})</strong></span>

            </div>

            <hr style="margin: 2px 0;">



            <div style="text-align: center; margin: 4px 0;">

                <img src="data:image/png;base64,{{ $barcodes[$i+1]['image'] }}" alt="Barcode" style="width: 150px; height: 20px;">

                <div style="font-size: 10px; letter-spacing: 1px; margin-top: 2px;">{{ $barcodes[$i+1]['code'] }}</div>



                @if (empty($customImporter['company']))

                    <div style="margin-top: 4px;">

                        <img src="https://mazingbusiness.com/public/images/qr.png" width="40" height="40"><br>

                        <div style="font-size: 9px; text-align: center; margin-top: 2px;">Download Our App</div>

                        <div style="font-size: 9px; text-align: center; font-weight: bold;">Visit Our Website</div>

                        <div style="font-size: 9px; text-align: center;">www.mazingbusiness.com</div>

                    </div>

                @endif

            </div>



            <hr style="margin: 2px 0;">



            {{-- Optional: Marketed By (CENTER ALIGNED) --}}

            @if ($marketed_by_checked)

                <div style="text-align: center; font-size: 8px; margin-top: 2px; text-transform: uppercase;">

                    <strong>Marketed By:</strong>

                </div>

                <div style="text-align: center; font-size: 8px; line-height: 1.3; margin-top: 2px;">

                    @if (!empty($marketedBy['company']))

                        <div style="font-size: 9px;">{{ $marketedBy['company'] }}</div>

                    @endif

                    @if (!empty($marketedBy['address']))

                        <div>{{ $marketedBy['address'] }}</div>

                    @endif

                    @if (!empty($marketedBy['phone']))

                        <div>Ph: {{ $marketedBy['phone'] }}</div>

                    @endif

                    @if (!empty($marketedBy['email']))

                        <div>Email: {{ $marketedBy['email'] }}</div>

                    @endif

                </div>

                <hr style="margin: 2px 0;">

            @endif



            {{-- Optional: Imported By --}}

            @if ($imported_by_checked)

                <div style="text-align: center; font-size: 8px; margin-top: 2px; text-transform: uppercase;">

                    <strong>Imported By:</strong>

                </div>



                <div style="text-align: left; font-size: 8px; line-height: 1.3; margin-top: 2px;">

                   @if (!empty($customImporter['company']))
                      <div style="font-size:8px; padding:0; margin:0; line-height:1.05;">
                        <span class="u-bold" style="margin-right:2px;"><strong>Name Of Importer:</strong></span>
                        {{ $customImporter['company'] }} 
                      </div>
                    @endif

                    @if (!empty($customImporter['address']))
                      <table style="width:100%; font-size:8px; line-height:1.25; border-collapse:collapse; margin:0;">
                        <tr>
                          <td style="vertical-align:top; white-space:nowrap; padding:0 2px 0 0;">
                            <span style="font-size:7px;" class="u-bold"><strong>Address:</strong></span>
                          </td>
                          <td style="vertical-align:top; padding:0; font-size:7px;">
                            {{ $customImporter['address'] }}
                          </td>
                        </tr>
                      </table>
                    @endif

                    @if (!empty($customImporter['phone']))
                      <div>
                        <span class="u-bold" style="font-size:7px;"><strong>Customer Care Number:</strong></span>
                        {{ $customImporter['phone'] }}
                      </div>
                    @endif

                    @if (!empty($customImporter['email']))

                        <div><span style="font-size:7px;" class="u-bold"><strong>Email Id:</strong></span> {{ $customImporter['email'] }}</div>

                    @endif

                    @if (!empty($country_of_origin))

                        <div><span style="font-size:7px;" class="u-bold"><strong>Country Of Origin:</strong></span> {{ $country_of_origin }}</div>

                    @endif

                    <div><span class="u-bold"><strong>Quantity:</strong></span> {{ $qty }} PCS.</div>

                    @if (!empty($mfg_month_year))

                        <div><span class="u-bold"><strong>Month & Year Of Mfg.:</strong></span> {{ $mfg_month_year }}</div>

                    @endif

                </div>

                <hr style="margin: 2px 0;">

            @endif



            {{-- ⬇️ Fixed single bottom barcode (always) --}}

            <div style="text-align: center; margin-top: auto; padding-top: 2px;">

                <img src="data:image/png;base64,{{ $barcodes[$i+1]['image'] }}" alt="Barcode" style="width: 150px; height: 20px;">

                <div style="font-size: 10px; letter-spacing: 1px; margin-top: 2px;">{{ $barcodes[$i+1]['code'] }}</div>

            </div>

            <div style="text-align: center; margin-top: auto; padding-top: 2px;">

                <img src="data:image/png;base64,{{ $barcodes[$i+1]['image'] }}" alt="Barcode" style="width: 150px; height: 20px;">

                <div style="font-size: 10px; letter-spacing: 1px; margin-top: 2px;">{{ $barcodes[$i+1]['code'] }}</div>

            </div>

        </div>

        @endif

    </div>

@endfor



</body>

</html>