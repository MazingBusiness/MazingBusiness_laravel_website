
{{-- ✅ PDF CONTENT BLOCK — TOP (placement = first) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'first')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif
<!-- Header -->
<table width="100%" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td style="text-align: right; position: relative;">
            <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" width="100%" alt="Header Image" style="display: block;" />
        </td>
    </tr>
</table>

<!-- Static Address and User Details -->
<table width="100%" border="0" cellpadding="10" cellspacing="0" style="margin-top: 20px;">
    <tr>
        <td width="50%" style="text-align: left; font-family: Arial, sans-serif; font-size: 14px; font-weight: bold; color: #174e84;">
            ACE TOOLS PRIVATE LIMITED<br>
            Building No./Flat No.: Khasra No. 58/15,<br>
            Pal Colony, Village Rithala,Delhi<br>
            New Delhi - 110085
        </td>
        <td width="50%" style="text-align: right; font-family: Arial, sans-serif; font-size: 14px; color: #174e84;">
            @php
                 $addressData = DB::table('addresses')
                ->where('acc_code', $party_code)
                ->first();
            @endphp
            {{ $addressData->company_name }}<br>
            {{ $addressData->address }}<br>
            {{ $addressData->address_2 }}<br>
            Pincode: {{ $addressData->postal_code }}<br>
        </td>
    </tr>
</table>


<!-- Show Reward Amount Above the Table -->
<p style="font-family: Arial, sans-serif; font-size: 14px; text-align: left; margin-bottom: 20px;">
    <strong>Reward Balance: </strong> ₹ {{ number_format($rewardAmount, 2) }}
      <span style="font-family: Arial, sans-serif; font-size: 14px; color: #174e84;">
        ({{ $last_dr_or_cr }})
    </span>
</p>
<!-- Rewards Table -->
<table width="100%" border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse; margin-top: 25px; font-family: Arial, sans-serif; border: 1px solid #ccc;">
    <thead>
        <tr>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: center;">Txn No</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: center;">Rewards From</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: right;">Debit</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: right;">Credit</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: right;">Balance</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: center;">Dr / Cr</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: left;">Narration</th>
        </tr>
    </thead>
    <tbody>
        @if (count($getData) > 0)
            @php
                $grand_total = 0;
                $total_debit = 0;
                $total_credit = 0;
            @endphp
            @foreach($getData as $gValue)
                @php
                    if ($gValue->dr_or_cr === 'dr') {
                        $grand_total += $gValue->rewards;
                        $total_debit += $gValue->rewards;
                    } else {
                        $grand_total -= $gValue->rewards;
                        $total_credit += $gValue->rewards;
                    }
                @endphp
                <tr>
                    <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">
                        <a 
                            href="{{ route('generate.invoice', ['invoice_no' => encrypt($gValue->invoice_no)]) }}" 
                            target="_blank"
                            style="text-decoration: none;">
                            {{ strtoupper($gValue->invoice_no) }}
                        </a>
                    </td>

                    <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">{{ strtoupper($gValue->rewards_from) }}</td>
                    <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">
                        {{ $gValue->dr_or_cr === 'dr' ? '₹ ' . number_format($gValue->rewards, 2) : '' }}
                    </td>
                    <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">
                        {{ $gValue->dr_or_cr === 'cr' ? '₹ ' . number_format($gValue->rewards, 2) : '' }}
                    </td>
                    <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">
                        ₹ {{ number_format(abs($grand_total), 2) }}
                    </td>
                    <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">{{ strtoupper($gValue->dr_or_cr) }}</td>
                    <td style="border: 1px solid #ccc; padding: 10px; text-align: left;">
                        {{ !empty($gValue->narration) ? $gValue->narration : '' }}
                    </td>
                </tr>
            @endforeach
            <tr>
                <td style="border: 1px solid #ccc;"></td>
                <td style="border: 1px solid #ccc; text-align: center;"><strong>Total</strong></td>
                <td style="border: 1px solid #ccc; text-align: right;">
                    ₹ {{ $total_debit > 0 ? number_format($total_debit, 2) : '0.0' }}
                </td>
                <td style="border: 1px solid #ccc; text-align: right;">
                    ₹ {{ $total_credit > 0 ? number_format($total_credit, 2) : '0.0' }}
                </td>
                <td style="border: 1px solid #ccc;"></td>
                <td style="border: 1px solid #ccc;"></td>
                <td style="border: 1px solid #ccc;"></td>
            </tr>
           <tr>
            <td style="border: 1px solid #ccc;"></td>
            <td style="border: 1px solid #ccc; text-align: center;"><strong>Closing Balance</strong></td>

            {{-- Debit column me tab dikhayen jab balance DR ho --}}
            <td style="border: 1px solid #ccc; text-align: right;">
                {{ $last_dr_or_cr === 'dr' ? '₹ ' . number_format(abs($closing_balance), 2) : '' }}
            </td>

            {{-- Credit column me tab dikhayen jab balance CR ho --}}
            <td style="border: 1px solid #ccc; text-align: right;">
                {{ $last_dr_or_cr === 'cr' ? '₹ ' . number_format(abs($closing_balance), 2) : '' }}
            </td>

            <td style="border: 1px solid #ccc;"></td>
            <td style="border: 1px solid #ccc;">{{ strtoupper($last_dr_or_cr) }}</td>
            <td style="border: 1px solid #ccc;"></td>
        </tr>
        @else
            <tr>
                <td colspan="7" style="text-align: center; border: 1px solid #ccc;">No Transaction Found</td>
            </tr>
        @endif
    </tbody>
</table>

<!-- Bank Details and QR Code -->
<table width="100%" border="0" cellpadding="10" cellspacing="0" style="margin-top: 20px;">
    <tr>
        <td width="50%" style="font-family: Arial, sans-serif; font-size: 14px; color: #174e84;">
            <strong>Bank Details:</strong><br>
            A/C Name: ACE TOOLS PRIVATE LIMITED<br>
            Branch : NAJAFGARH ROAD, NEW DELHI<br>
            A/C No: 235605001202<br>
            IFSC Code: ICIC0002356<br>
            Bank Name: ICICI Bank<br>
        </td>
        <td style="width: 50%; text-align: right; padding: 10px;">
            <img src="https://mazingbusiness.com/public/assets/img/barcode.png" alt="Scan QR Code" style="width: 100px; height: 100px;">
            <br><span style="font-size: 12px;">Scan the barcode with any UPI app to pay.</span>
        </td>
    </tr>
</table>
<!-- Footer -->
<table width="100%" border="0" cellpadding="0" cellspacing="0">
    <tbody>
        <tr bgcolor="#174e84">
            <td style="height: 40px; text-align: center; color: #fff; font-family: Arial; font-weight: bold;">
                ACE TOOLS PVT LTD - REWARDS 
            </td>
        </tr>
    </tbody>
</table>
 {{-- ✅ PDF CONTENT BLOCK — BOTTOM (placement = last) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'last')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif