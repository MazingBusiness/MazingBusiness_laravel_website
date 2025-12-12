
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
            Plot No 220/219 & 220 Kh No 58/2,<br>
            Rithala Road, Rithala,<br>
            New Delhi - 110085
        </td>
        @php
            // Fetch the first address for the authenticated user
            $addressData = DB::table('addresses')
                ->where('user_id',$userData->id)
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
            <span style="font-size: 12px; color: #000;">₹{{ number_format($availableCredit, 2) }}</span> <!-- Changed color to black -->
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
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: center;">Date</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: center;">Particulars</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: center;">Txn No</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: right;">Debit</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: right;">Credit</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: right;">Balance</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: center;">Overdue Status</th>
            <th style="border: 1px solid #ccc; background-color: #f1f1f1; padding: 10px; text-align: center;">Dr / Cr</th>
        </tr>
    </thead>
    <tbody>
        @php
            $balance = 0;
            $totalDebit = 0;
            $totalCredit = 0;
            $closingDrBalance = 0;
            $closingCrBalance = 0;
        @endphp
        @foreach($statementData as $transaction)
            @if($transaction['ledgername'] != 'closing C/f...')
            <tr>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">{{ date('d-M-y', strtotime($transaction['trn_date'])) }}</td>
                <td style="border: 1px solid #ccc; padding: 12px; text-align: center;">{{ strtoupper($transaction['vouchertypebasename']) }}</td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">{{ $transaction['trn_no'] }}</td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">
                    @php
                        $debitAmount = $transaction['dramount'] != "0.00" ? number_format($transaction['dramount'], 2) : '';
                        $totalDebit += isset($transaction['dramount']) ? floatval($transaction['dramount']) : 0;
                    @endphp
                    ₹{{ $debitAmount }}
                </td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">
                    @php
                        $creditAmount = $transaction['cramount'] != "0.00" ? number_format($transaction['cramount'], 2) : '';
                        $totalCredit += isset($transaction['cramount']) ? floatval($transaction['cramount']) : 0;
                    @endphp
                    ₹{{ $creditAmount }}
                </td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">
                    @php
                        $balance += $transaction['dramount'] - $transaction['cramount'];
                    @endphp
                    ₹{{ number_format($balance, 2) }}
                </td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">
                    {{ isset($transaction['overdue_status']) ? $transaction['overdue_status'] : '-' }}
                </td>
                <td style="border: 1px solid #ccc; padding: 10px; text-align: center;">
                    {{ $transaction['dramount'] != "0.00" ? 'Dr' : 'Cr' }}
                </td>
            </tr>
            @else
                @php
                    $closingDrBalance = isset($transaction['dramount']) ? floatval($transaction['dramount']) : 0;
                    $closingCrBalance = isset($transaction['cramount']) ? floatval($transaction['cramount']) : 0;
                @endphp
            @endif
        @endforeach

        <!-- Total Row -->
        <tr>
            <td colspan="3" style="border: 1px solid #ccc; padding: 10px; text-align: right;"><strong>Total</strong></td>
            <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">₹{{ number_format($totalDebit, 2) }}</td>
            <td style="border: 1px solid #ccc; padding: 10px; text-align: right;">₹{{ number_format($totalCredit, 2) }}</td>
            <td colspan="3" style="border: 1px solid #ccc; padding: 10px;"></td>
        </tr>

        <!-- Closing Balance Row -->
        <tr>
            <td colspan="3" style="border: 1px solid #ccc; text-align: right;"><strong>Closing Balance</strong></td>
            <td style="border: 1px solid #ccc;">
                {{ ($closingDrBalance != "0.00") ? '₹' . number_format($closingDrBalance, 2) : '' }}
            </td>
            <td style="border: 1px solid #ccc;">
                {{ ($closingCrBalance != "0.00") ? '₹' . number_format($closingCrBalance, 2) : '' }}
            </td>
            <td style="border: 1px solid #ccc;"></td>
            <td style="border: 1px solid #ccc;"></td>
            <td style="border: 1px solid #ccc;"></td>
        </tr>

        <!-- Grand Total Row -->
        <tr>
            <td colspan="3" style="border: 1px solid #ccc; padding: 10px; text-align: right;"><strong>Grand Total</strong></td>
            <td style="border: 1px solid #ccc; padding: 10px; text-align: right;"><strong>₹{{ number_format($totalDebit + $closingDrBalance, 2) }}</strong></td>
            <td style="border: 1px solid #ccc; padding: 10px; text-align: right;"><strong>₹{{ number_format($totalCredit + $closingCrBalance, 2) }}</strong></td>
            <td colspan="3" style="border: 1px solid #ccc; padding: 10px;"></td>
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
                Mazing Business Statement
            </td>
        </tr>
    </tbody>
</table>