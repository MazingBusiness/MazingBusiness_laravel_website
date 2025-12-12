<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sub Order Print Format</title>
    <style>
        @page { margin: 20px 30px; }
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .center { text-align: center; font-weight: bold; text-transform: uppercase; margin-bottom: 10px; }
        .header-table td { padding: 3px; vertical-align: top; }
        .data-table, .data-table th, .data-table td { border: 1px solid #000; padding: 4px; }
        .data-table th { text-align: center; }
        .footer-table td { padding: 8px; vertical-align: top; }
        .remarks { margin-top: 10px; border-top: 1px solid #000; padding-top: 10px; }
        .signature { text-align: right; padding-top: 40px; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>

    <div class="center">
        {{ $subOrder->shippingAddress->company_name ?? '' }}
        @if(!empty($subOrder->shippingAddress->city))
            - ({{ $subOrder->shippingAddress->city }})
        @endif
    </div>

    <table class="header-table" width="100%">
        <tr>
            <td><strong>Ledger Name:</strong> {{ $subOrder->user->company_name ?? '' }}</td>
            <td><strong>Voucher No:</strong> {{ $subOrder->order_no }}</td>
            <td><strong>Date:</strong> {{ \Carbon\Carbon::parse($subOrder->created_at)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Send To:</strong></td>
            <td><strong>Phone No:</strong></td>
            <td><strong>Marka:</strong></td>
        </tr>
        <tr>
            <td><strong>Dispatch To:</strong></td>
            <td><strong>Godown:</strong> {{ $subOrder->order_warehouse->name ?? '' }}</td>
            <td></td>
        </tr>
        <tr>
            <td><strong>Transport Name:</strong> {{ $subOrder->transport_name ?? '-' }}</td>
            <td><strong>Transport ID:</strong> {{ $subOrder->transport_id ?? '-' }}</td>
            <td><strong>Transport Remarks:</strong> {{ $subOrder->transport_remarks ?? '-' }}</td>
        </tr>
    </table>

    <br>

    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Item Name</th>
                <th>Part No</th>
                <th>Closing QTY</th>
                <th>Pending QTY</th>     <!-- approved - challan -->
                <th>Delivered Qty</th>   <!-- left blank for store -->
                <th>Net Rate</th>
                <th>Amount</th>
                <th>Remarks 2</th>       <!-- fixed header -->
            </tr>
        </thead>
        <tbody>
        @php
            $rowIndex = 0;
            $total_pending_qty = 0;
            $total_amount = 0.0;
        @endphp

        @foreach ($subOrder->sub_order_details as $detail)
            @php
                $pendingQty = (int)($detail->remaining_qty ?? 0);
                if ($pendingQty <= 0) continue; // safety

                $rate       = (float)($detail->net_rate ?? 0);
                $lineAmount = $pendingQty * $rate;

                $closingQty = (int)($detail->closing_qty ?? 0);
                $remarks2   = $detail->remarks_2 ?? $detail->remarks ?? '';

                $rowIndex++;
                $total_pending_qty += $pendingQty;
                $total_amount      += $lineAmount;
            @endphp
            <tr>
                <td class="text-center">{{ $rowIndex }}</td>
                <td>{{ $detail->product_data->name ?? '' }}</td>
                <td class="text-center">{{ $detail->product_data->part_no ?? '' }}</td>
                <td class="text-center">{{ $closingQty }}</td>
                <td class="text-center">{{ $pendingQty }}</td>
                <td class="text-center"></td>
                <td class="text-center">{{ number_format($rate, 3) }}</td>
                <td class="text-right">{{ number_format($lineAmount, 3) }}</td>
                <td>{{ $remarks2 }}</td> <!-- show Remarks 2 with fallback -->
            </tr>
        @endforeach

        <tr>
            <td colspan="3" class="text-right"><strong>Total :</strong></td>
            <td class="text-center"><strong><!-- closing total optional --></strong></td>
            <td class="text-center"><strong>{{ $total_pending_qty }}</strong></td>
            <td class="text-center"><strong></strong></td>
            <td></td>
            <td class="text-right"><strong>{{ number_format($total_amount, 3) }}</strong></td>
            <td></td>
        </tr>
        </tbody>
    </table>

    <div class="remarks">
        <p><strong>Remarks:</strong></p>
        <table class="footer-table" width="100%">
            <tr>
                <td>
                    <p>No Of Boxes&nbsp;&nbsp;&nbsp;</p>
                    <input type="text" style="width: 50px; border: 1px solid #000; position: relative; left: 30px;">
                    <p>No Of Bundles&nbsp;</p>
                    <input type="text" style="width: 50px; border: 1px solid #000;">
                </td>
                <td class="signature">
                    Godown Incharge<br>
                    Signature
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
