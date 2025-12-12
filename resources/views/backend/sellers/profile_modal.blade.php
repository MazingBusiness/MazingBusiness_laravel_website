<div class="modal-body">

  <div class="text-center">
    <span class="avatar avatar-xxl mb-3">
      <img src="{{ uploaded_asset($seller->user->avatar_original) }}"
        onerror="this.onerror=null;this.src='{{ static_asset('assets/img/avatar-place.png') }}';">
    </span>
    <h1 class="h5 mb-1">{{ $seller->user->name }}</h1>
    <p class="text-sm text-muted">{{ $seller->shop->name }}</p>
  </div>
  <hr>

  <!-- Profile Details -->
  <h6 class="mb-4">{{ translate('About') }} {{ $seller->user->name }}</h6>
  <p><i class="demo-pli-map-marker-2 icon-lg icon-fw mr-1"></i>Warehouse: {{ $seller->user->warehouse->name }}</p>
  <p><i class="demo-pli-map-marker-2 icon-lg icon-fw mr-1"></i>Address: {{ $seller->shop->address }}</p>
  <p><i class="demo-pli-old-telephone icon-lg icon-fw mr-1"></i>Phone No: {{ $seller->user->phone }}</p>

  <h6 class="mb-4">{{ translate('Payout Info') }}</h6>
  <p>{{ translate('Bank Name') }} : {{ $seller->bank_name }}</p>
  <p>{{ translate('Bank Acc Name') }} : {{ $seller->bank_acc_name }}</p>
  <p>{{ translate('Bank Acc Number') }} : {{ $seller->bank_acc_no }}</p>
  <p>{{ translate('Bank IFSC Code') }} : {{ $seller->bank_ifsc_code }}</p>

  <br>

  <div class="table-responsive">
    <table class="table table-striped mar-no">
      <tbody>
        <tr>
          <td>{{ translate('Total Products') }}</td>
          <td>{{ App\Models\Product::where('user_id', $seller->user->id)->get()->count() }}</td>
        </tr>
        <tr>
          <td>{{ translate('Total Orders') }}</td>
          <td>{{ App\Models\OrderDetail::where('seller_id', $seller->user->id)->get()->count() }}</td>
        </tr>
        <tr>
          <td>{{ translate('Total Sold Amount') }}</td>
          @php
            $orderDetails = \App\Models\OrderDetail::where('seller_id', $seller->user->id)->get();
            $total = 0;
            foreach ($orderDetails as $key => $orderDetail) {
                if ($orderDetail->order != null && $orderDetail->order->payment_status == 'paid') {
                    $total += $orderDetail->price;
                }
            }
          @endphp
          <td>{{ single_price($total) }}</td>
        </tr>
        <tr>
          <td>{{ translate('Wallet Balance') }}</td>
          <td>{{ single_price($seller->user->balance) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
