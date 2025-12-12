@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="align-items-center">
      <h1 class="h3">{{ translate('Edit Financial Info') }}</h1>
    </div>
  </div>

  <!-- Display success message -->
  @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
    </div>
  @endif

  <div class="card">
    <div class="card-body">
      <form action="{{ route('customers.update-financial-info', $customer->id) }}" method="POST">
        @csrf
        @method('POST')

        <div class="form-group">
          <label for="credit_limit">{{ translate('Credit Limit') }}</label>
          <input type="number" class="form-control" id="credit_limit" name="credit_limit" value="{{ $customer->credit_limit }}" min="0" required>
        </div>

        <div class="form-group">
          <label for="credit_days">{{ translate('Credit Days') }}</label>
          <input type="number" class="form-control" id="credit_days" name="credit_days" value="{{ $customer->credit_days }}" min="0" required>
        </div>

        <div class="form-group">
          <label for="discount">{{ translate('Discount') }}</label>
          <input type="number" class="form-control" id="discount" name="discount" value="{{ $customer->discount }}" min="0" max="100" required>
        </div>

        <div class="text-right">
          <button type="submit" class="btn btn-primary">{{ translate('Update Financial Info') }}</button>
        </div>
      </form>
    </div>
  </div>
@endsection
