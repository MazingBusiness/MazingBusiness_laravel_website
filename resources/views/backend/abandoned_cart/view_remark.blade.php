@extends('backend.layouts.app')

@section('content')
  <div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="align-items-center">
      <h1 class="h3">{{ translate('View Remarks') }}</h1>
    </div>
  </div>

  <div class="card">
    <div class="card-header row gutters-5">
      <div class="col">
        <h5 class="mb-0 h6">{{ translate('Remarks Details') }}</h5>
      </div>
    </div>

    <div class="card-body">
      @if($remarks->isNotEmpty())
        <table class="table aiz-table mb-0">
          <thead>
            <tr>
              <!-- <th>{{ translate('User Name') }}</th>
              <th>{{ translate('Product Name') }}</th>
              <th>{{ translate('Quantity') }}</th>
              <th>{{ translate('Price') }}</th>
              <th>{{ translate('Total Price') }}</th> -->
              <th>{{ translate('Remark Description') }}</th>
              <th>{{ translate('Created At') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($remarks as $remark)
              <tr>
                <!-- <td>{{ $remark->user_name }}</td>
                <td>{{ $remark->product_name }}</td>
                <td>{{ $remark->quantity }}</td>
                <td>{{ $remark->price }}</td>
                <td>{{ $remark->quantity * $remark->price }}</td> -->
                <td>{{ $remark->remark_description }}</td>
                <td>{{ $remark->created_at }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
      <div class="alert alert-warning" role="alert">
        {{ translate('No remarks found for the selected cart.') }}
      </div>
      @endif
    </div>

    <div class="card-footer text-right">
      <button type="button" class="btn btn-secondary" onclick="history.back()">{{ translate('Back') }}</button>
    </div>
  </div>
@endsection
