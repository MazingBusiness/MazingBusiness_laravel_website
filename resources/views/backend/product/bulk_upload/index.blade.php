@extends('backend.layouts.app')

@section('content')
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0 h6">{{ translate('Product Bulk Upload') }}</h5>
    </div>
    <div class="card-body">
      <div class="alert"
        style="color: #004085;background-color: #cce5ff;border-color: #b8daff;margin-bottom:0;margin-top:10px;">
        <p>1. {{ translate('Download the demo file and fill it with proper data') }}.</p>
        <p>2. {{ translate('You can download the example file to understand how the data must be filled') }}.</p>
        <p>3.
          {{ translate('Once you have downloaded and filled the demo file, upload it in the form below and submit') }}.
        </p>
        <p>4. {{ translate('After uploading products you need to add the images for the products.') }}.</p>
      </div>
      <br>
      <div class="">
        <a href="{{ route('product_bulk_export.demo') }}" download><button
            class="btn btn-info">{{ translate('Download Product Demo Excel') }}</button></a>
        <a href="{{ route('pdf.download_brand') }}"><button
            class="btn btn-info">{{ translate('Download Brands List') }}</button></a>
      </div>
      <br>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0 h6"><strong>{{ translate('Upload Product File') }}</strong></h5>
    </div>
    <div class="card-body">
      <form class="form-horizontal" action="{{ route('bulk_product_upload') }}" method="POST"
        enctype="multipart/form-data">
        @csrf
        <div class="form-group row">
          <div class="col-sm-9">
            <div class="custom-file">
              <label class="custom-file-label">
                <input type="file" name="bulk_file" class="custom-file-input" required>
                <span class="custom-file-name">{{ translate('Choose File') }}</span>
              </label>
            </div>
          </div>
        </div>
        <div class="form-group mb-0">
          <button type="submit" class="btn btn-info">{{ translate('Upload CSV') }}</button>
        </div>
      </form>
    </div>
  </div>
@endsection
