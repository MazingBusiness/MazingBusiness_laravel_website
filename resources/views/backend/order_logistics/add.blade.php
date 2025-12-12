@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-3 mb-4">
    <h1 class="h3 text-primary fw-bold">Add Order Logistic</h1>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="POST" action="{{ route('order.logistics.store', encrypt($invoiceNo)) }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-md-12">
                    <label for="transport_name" class="form-label fw-semibold">{{ translate('Transporter Name') }}</label>
                    <input type="text" id="transport_name" name="transport_name" class="form-control" placeholder="Enter Transporter Name" value="{{ $logistic->transport_name ?? old('transport_name') }}" required>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="lr_date" class="form-label fw-semibold">LR Date</label>
                    <input type="date" id="lr_date" name="lr_date" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label for="lr_no" class="form-label fw-semibold">LR Number</label>
                    <input type="text" id="lr_no" name="lr_no" class="form-control" placeholder="Enter LR Number" required>
                </div>
                
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <label for="no_of_boxes" class="form-label fw-semibold">No. of Boxes</label>
                    <input type="number" id="no_of_boxes" name="no_of_boxes" class="form-control" placeholder="Enter Number of Boxes" required>
                </div>
                <div class="col-md-6">
                    <label for="lr_amount" class="form-label fw-semibold">LR Amount</label>
                    <input type="text" id="lr_amount" name="lr_amount" class="form-control" placeholder="Enter LR Amount" required>
                </div>
            </div>

            <div class="mt-3">
                <label for="attachments" class="form-label fw-semibold">Attachments</label>
                <div class="input-group">
                    <input type="file" id="attachments" name="attachments[]" class="form-control" accept=".jpeg,.jpg,.png" multiple>
                    <label class="input-group-text" for="attachments">Upload</label>
                </div>
            </div>

            <div class="text-end mt-4">
                <button type="submit" class="btn btn-success px-4">Submit</button>
                <a href="{{ route('order.logistics') }}" class="btn btn-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
