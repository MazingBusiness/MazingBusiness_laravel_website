@extends('seller.layouts.app')

@section('panel_content')
  <div class="aiz-titlebar mt-2 mb-4">
    <div class="row align-items-center">
      <div class="col-md-6">
        <h1 class="h3">{{ translate('Shop Settings') }}</h1>
      </div>
    </div>
  </div>

  {{-- Basic Info --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0 h6">{{ translate('Basic Info') }}</h5>
    </div>
    <div class="card-body">
      <form class="" action="{{ route('seller.shop.update') }}" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="shop_id" value="{{ $shop->id }}">
        @csrf
        <div class="row">
          <label class="col-md-2 col-form-label">{{ translate('Shop Name') }}<span
              class="text-danger text-danger">*</span></label>
          <div class="col-md-10">
            <input type="text" class="form-control mb-3" placeholder="{{ translate('Shop Name') }}" name="name"
              value="{{ $shop->name }}" required>
          </div>
        </div>
        <div class="row">
          <label class="col-md-2 col-form-label">
            {{ translate('Shop Phone') }}
          </label>
          <div class="col-md-10">
            <input type="text" class="form-control mb-3" placeholder="{{ translate('Phone') }}" name="phone"
              value="{{ $shop->phone }}" required>
          </div>
        </div>
        <div class="row">
          <label class="col-md-2 col-form-label">{{ translate('Shop Address') }} <span
              class="text-danger text-danger">*</span></label>
          <div class="col-md-10">
            <input type="text" class="form-control mb-3" placeholder="{{ translate('Address') }}" name="address"
              value="{{ $shop->address }}" required>
          </div>
        </div>
        <div class="form-group mb-0 text-right">
          <button type="submit" class="btn btn-sm btn-primary">{{ translate('Save') }}</button>
        </div>
      </form>
    </div>
  </div>
@endsection
