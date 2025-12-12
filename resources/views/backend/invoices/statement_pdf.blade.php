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
        @php
            // Fetch the first address for the authenticated user
            $addressData = DB::table('addresses')
                ->where('acc_code', $party_code)
                ->first();

            // Set default values if no address is found
            $address = $addressData ? $addressData->address : 'Address not found';
            $address_2 = $addressData ? $addressData->address_2 : '';
            $postal_code = $addressData ? $addressData->postal_code : '';

            // Get user credit details
            $userCreditDetails = DB::table('users')
                ->where('id',$userData->id)
                ->select('credit_days', 'credit_limit','company_name')
                ->first();

            // Set default values for credit details if not available
            $creditDays = $userCreditDetails ? $userCreditDetails->credit_days : 'N/A';
            $creditLimit = $userCreditDetails ? $userCreditDetails->credit_limit : 0;

            // Calculate total outstanding debit (amount the user owes)
            $totalOutstandingDebit = 0;
            foreach ($statementData as $transaction) {
                $totalOutstandingDebit += isset($transaction['dramount']) ? floatval($transaction['dramount']) : 0;
            }

            // Calculate available credit
            $availableCredit = $creditLimit - $totalOutstandingDebit;
        @endphp

        <td width="50%" style="text-align: right; font-family: Arial, sans-serif; font-size: 14px; color: #174e84;">
            {{ $addressData->company_name }}<br>
            {{ $address }}<br>
            {{ $address_2 }}<br>
            Pincode: {{ $postal_code }}<br>
        </td>
    </tr>
</table>
<!-- Credit Information -->
<table width="100%" border="0" cellpadding="10" cellspacing="0" style="margin-top: 20px;">
    <tr>
        <td width="33%" style="text-align: center; font-family: Arial, sans-serif; font-size: 14px; color: #174e84;">
            <strong>Credit Days</strong><br>
            <span style="font-size: 12px; color: #333;">{{ $creditDays }} days</span> <!-- Dynamic value for credit days -->
        </td>
        <td width="33%" style="text-align: center; font-family: Arial, sans-serif; font-size: 14px; color: #174e84;">
            <strong>Credit Limit</strong><br>
            <span style="font-size: 12px; color: #333;">₹{{ number_format($creditLimit, 2) }}</span> <!-- Dynamic value for credit limit -->
        </td>
        <td width="33%" style="text-align: center; font-family: Arial, sans-serif; font-size: 14px; color: #174e84;">
            <strong>Available Credit</strong><br>
            <span style="font-size: 12px; color: #000;">
                ₹{{ number_format($userData->dueDrOrCr == 'Cr' ? $creditLimit : ($creditLimit - $dueAmount), 2) }}
            </span>
        </td>
    </tr>
</table>
<!-- Overdue Information -->
<table width="100%" border="0" cellpadding="10" cellspacing="0" style="margin-top: 20px;">
    <tr>
        <td width="50%" style="text-align: center; font-family: Arial, sans-serif; font-size: 14px; color: #174e84;">
            <strong>Overdue Balance</strong><br>
            <span style="font-size: 12px; color: {{ $overdueAmount > 0 ? '#ff0707' : '#333' }};">
                ₹{{ number_format($overdueAmount, 2) }} {{ $userData->overdueDrOrCr }} <!-- Overdue Balance -->
            </span>
        </td>
        <td width="50%" style="text-align: center; font-family: Arial, sans-serif; font-size: 14px; color: #174e84;">
            <strong>Due Amount</strong><br>
            <span style="font-size: 12px; color: #333;">₹{{ number_format($dueAmount, 2) }} {{ $userData->dueDrOrCr }}</span> <!-- Replacing 'Overdue From' with Due Amount -->
        </td>
    </tr>
</table>
<!-- Date Range Information -->
<p style="font-size: 14px; text-align: center; margin-top: 20px;">
    <strong>{{ date('d-M-y', strtotime($form_date)) }} to {{ date('d-M-y', strtotime($to_date)) }}</strong>
</p>

<!-- Transaction Table -->
@if (count($statementData) > 0)
<table width="100%" border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse; margin-top: 25px; font-family: Arial, sans-serif; border: 1px solid #ccc;">
    <thead>
        <tr>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 5px; text-align: center;">Date.</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 5px; text-align: center;">Particulars</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 5px; text-align: center;">Txn No</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 5px; text-align: right;">Debit</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 5px; text-align: right;">Credit</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 5px; text-align: right;">Balance</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 5px; text-align: center;">Overdue Status</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 5px; text-align: center;">Dr / Cr</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 5px; text-align: center;">Overdue By Day</th>
        </tr>
    </thead>
   <tbody>
    @php
        $balance = 0;
        $totalDebit = 0;
        $totalCredit = 0;
        $closingBalance = 0;
        $closingDrOrCr = '-';
    @endphp

    @foreach($statementData as $index => $transaction)
        @if($transaction['ledgername'] != 'closing C/f...')
            <tr style="{{ isset($transaction['overdue_status']) && $transaction['overdue_status'] == 'Overdue' ? 'background-color: #ff00006b;' : (isset($transaction['overdue_status']) && $transaction['overdue_status'] == 'Pertial Overdue' ? 'background-color: #ffcccc;' : '') }}">
                <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">{{ date('d-M-y', strtotime($transaction['trn_date'])) }}</td>
                <td style="border: 1px solid #ccc; padding: 12px; text-align: center;">{{ strtoupper($transaction['vouchertypebasename']) }}</td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">
                    @if(!empty($transaction['trn_no']))
                        <a href="{{ route('generate.invoice', ['invoice_no' => encrypt($transaction['trn_no'])]) }}" 
                        target="_blank" 
                        rel="noopener noreferrer" 
                        style="text-decoration: none; color: #074e86;">
                            {{ $transaction['trn_no'] }}
                        </a>
                    @else
                        {{ __('N/A') }}
                    @endif
                </td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">
                    @php
                        $debitAmount = $transaction['dramount'] != "0.00" ? floatval($transaction['dramount']) : 0;
                        $totalDebit += $debitAmount;
                    @endphp
                    ₹{{ number_format($debitAmount, 2) }}
                </td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">
                    @php
                        $creditAmount = $transaction['cramount'] != "0.00" ? floatval($transaction['cramount']) : 0;
                        $totalCredit += $creditAmount;
                    @endphp
                    ₹{{ number_format($creditAmount, 2) }}
                </td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">
                    @php
                        $balance += $debitAmount - $creditAmount;
                        $closingBalance = $balance; // Capturing the last balance for closing balance
                        $closingDrOrCr = ($balance > 0) ? 'Dr' : (($balance < 0) ? 'Cr' : '-');
                    @endphp
                    ₹{{ number_format($balance, 2) }}
                </td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">{{ $transaction['overdue_status'] ?? '-' }}</td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">
                    {{ $closingDrOrCr }}
                </td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">
                    {{ $transaction['overdue_by_day'] ?? '-' }}
                </td>
            </tr>
        @endif
    @endforeach

    <!-- Total Row -->
    <tr>
        <td colspan="3" style="border: 1px solid #ccc; padding: 10px; text-align: right;"><strong>Total</strong></td>
        <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">₹{{ number_format($totalDebit, 2) }}</td>
        <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">₹{{ number_format($totalCredit, 2) }}</td>
        <td colspan="3" style="border: 1px solid #ccc; padding: 10px;"></td>
        <td style="border: 1px solid #ccc;"></td>
    </tr>

    <!-- Closing Balance Row -->
    <tr>
        <td colspan="3" style="border: 1px solid #ccc; text-align: right;"><strong>Closing Balance</strong></td>
        <td style="border: 1px solid #ccc;">@if($closingDrOrCr == 'Cr') ₹{{ number_format(abs($closingBalance), 2) }} @else ₹ 0.00 @endif</td>
        <td style="border: 1px solid #ccc;">
            @if($closingDrOrCr == 'Dr') ₹{{ number_format(abs($closingBalance), 2) }} @else ₹ 0.00 @endif
        </td>
        <td colspan="3" style="border: 1px solid #ccc;"></td>
        <td style="border: 1px solid #ccc; text-align: center;"><strong>{{ $closingDrOrCr }}</strong></td>
    </tr>

    <!-- Grand Total Row -->
    <tr>
        <td colspan="3" style="border: 1px solid #ccc; padding: 10px; text-align: right;"><strong>Grand Total</strong></td>
        <td style="border: 1px solid #ccc; padding: 10px; text-align: right;"><strong>@if($closingDrOrCr == 'Cr') ₹{{ number_format($totalDebit+ abs($closingBalance), 2) }} @else ₹{{ number_format($totalDebit+ abs($closingBalance), 2) }} @endif</strong></td>
        <td style="border: 1px solid #ccc; padding: 10px; text-align: right;"><strong>@if($closingDrOrCr == 'Dr') ₹{{ number_format($totalCredit + abs($closingBalance), 2) }} @else ₹{{ number_format($totalCredit, 2) }} @endif</strong></td>
        <td colspan="3" style="border: 1px solid #ccc;"></td>
        <td style="border: 1px solid #ccc;"></td>
    </tr>
</tbody>

</table>
@endif

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
                ACE TOOLS PVT LTD STATEMENT
            </td>
        </tr>
    </tbody>
</table>
{{-- ✅ PDF CONTENT BLOCK — BOTTOM (placement = last) --}}
     @if(!empty($pdfContentBlock) && ($pdfContentBlock['placement'] ?? 'last') === 'last')
        @include('backend.sales.partials.pdf_content_block', ['block' => $pdfContentBlock])
    @endif