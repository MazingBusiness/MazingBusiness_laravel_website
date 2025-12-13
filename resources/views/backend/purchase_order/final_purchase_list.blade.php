@extends('backend.layouts.app')

@section('content')
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <h1 class="h3">Final Purchase Orders</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <!-- Display Success Message -->
            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <table class="table aiz-table mb-0">
                <thead>
                    <tr>
                        <th>#</th> <!-- Serial Number Column -->
                        <th>Purchase No</th>
                        <th>Purchase Order No</th>
                        <th>Seller Name</th>
                        <th>Seller Invoice No</th>
                        <th>Seller Invoice Date</th>
                        <th>Product Info</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchases as $index => $purchase)
                        <tr>
                            <td>{{ $index + 1 }}</td> <!-- Serial Number -->
                            <td>{{ $purchase->purchase_no }}</td>
                            <td>{{ $purchase->purchase_order_no }}</td>
                            <td>{{ $purchase->seller_name }}</td>
                            <td>{{ $purchase->seller_invoice_no }}</td>
                            <td>{{ $purchase->seller_invoice_date }}</td>
                            <td>
                                <!-- Button to view product info -->
                                <a href="{{ route('purchase-order.view', $purchase->purchase_no) }}" class="btn btn-sm btn-primary">
                                    View Products
                                </a>
                            </td>
                            <td>
                                <!-- Actions like edit or delete -->
                               
                                <a href="{{ route('final-purchases', $purchase->purchase_no) }}" class="btn btn-sm btn-warning"><i class="fas fa-download"></i> Download</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
