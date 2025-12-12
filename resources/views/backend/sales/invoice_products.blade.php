@extends('backend.layouts.app')

@section('content')
<div class="card">
    <div class="card-header">
        <h5>Invoice No: {{ $invoice->invoice_no }}</h5>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <thead class="bg-secondary text-white">
                <tr>
                    <th>Part No</th>
                    <th>Item Name</th>
                    <th>HSN</th>
                    <th>GST</th>
                    <th>Qty</th>
                    <th>Rate</th>
                    <th>Total</th>
                    <th>Challan No</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($products as $prod)
                    <tr>
                        <td>{{ $prod->part_no }}</td>
                        <td>{{ $prod->item_name }}</td>
                        <td>{{ $prod->hsn_no }}</td>
                        <td>{{ $prod->gst }}%</td>
                        <td>{{ $prod->billed_qty }}</td>
                        <td>{{ number_format($prod->rate, 2) }}</td>
                        <td>{{ number_format($prod->billed_amt, 2) }}</td>
                        <td>{{ $prod->challan_no }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <a href="{{ route('invoice.orders') }}" class="btn btn-primary mt-3">Back to Invoices</a>
    </div>
</div>
@endsection
