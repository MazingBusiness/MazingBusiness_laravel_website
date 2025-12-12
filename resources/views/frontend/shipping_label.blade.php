<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Shipping Label - {{ $claim->ticket_id }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
    .box { border:1px solid #ccc; padding:12px; border-radius:6px; }
    .flex { display:flex; justify-content:space-between; }
    .mt-1{ margin-top:6px;} .mt-2{ margin-top:12px;}
    .mb-1{ margin-bottom:6px;} .mb-2{ margin-bottom:12px;}
    .small{ font-size:11px; color:#555; }
    table { width:100%; border-collapse: collapse; }
    th, td { border:1px solid #ddd; padding:6px; }
    th { background:#f5f5f5; text-align:left; }
    /* add with your other styles */
    .twocol { width:100%; border-collapse:collapse; table-layout:fixed; }
    .twocol td { width:50%; vertical-align:top; padding:0 6px; }
    .notice{
      text-align:center;
      margin-top:12px;
      padding:8px 10px;
      border:1px solid #ddd;
      border-radius:4px;
      background:#fafafa;
      font-weight:700;
      letter-spacing:.5px;
    }
    .notice .title{ font-size:13px; }
    .notice .sub{ text-align:left; font-size:10px; color:#666; margin-top:2px; }
  </style>
</head>
<body>
  <div class="box">
    <div class="notice">
      <div class="title">Warranty Claim Report</div>
    </div>
    <div class="flex mb-2">
      <div>
        <div><strong>Ticket:</strong> {{ $claim->ticket_id }}</div>
        <div><strong>Date:</strong> {{ \Carbon\Carbon::parse($claim->created_at)->timezone('Asia/Kolkata')->format('d-m-Y') }}</div>
      </div>
      <div style="text-align:right">
        <div><strong>Customer:</strong> {{ $claim->name }}</div>
        <div class="small">{{ $claim->email }} | {{ $claim->phone }}</div>
      </div>
    </div>
    <div class="flex">
      <table class="twocol">
        <thead>
            <tr>
              <th>Ship From</th>
              <th>Ship To (Warehouse)</th>
            </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <div class="small">
                <div>{{ $claim->name }}</div>
                <div>{!! nl2br(e(trim($claim->address."\n".$claim->address_2))) !!}</div>
                <div>{{ $claim->city }} {{ $claim->postal_code }}</div>
                @if($claim->gstin)<div>GSTIN: {{ $claim->gstin }}</div>@endif
              </div>
            </td>
            <td>
              <div class="small">
                @if($warehouse)
                  <div>{{ $warehouse->getAddress->company_name }}</div>
                  <div>{{ $warehouse->getAddress->gstin }}</div>
                  <div>{!! nl2br(e($claim->warehouse_address ?: ($warehouse->address ?? ''))) !!}</div>
                @else
                  <div>{!! nl2br(e($claim->warehouse_address)) !!}</div>
                @endif
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="mt-2">
      <div class="mb-1"><strong>Products</strong></div>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Part Number</th>
            <!-- <th>Invoice</th> -->
            <th>Product Name</th>
            <th>Purchase Date</th>
          </tr>
        </thead>
        <tbody>
          @foreach($details as $idx => $d)
          <tr>
            <td>{{ $idx+1 }}</td>
            <td>{{ $d->warranty_product_part_number ?: '-' }}</td>
            <!-- <td>{{ $d->invoice_no }}</td> -->
            <td>{{ $d->warrantyProduct->name }}</td>
            <td>{{ \Carbon\Carbon::parse($d->purchase_date)->format('d-m-Y') }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="notice">
      <!-- Optional helper line; remove if you only want the title -->
      <div class="sub"><strong>Note :</strong></div>
      <div class="sub">Products are of no cost as they are rejected products.</div>
      <div class="sub">Damaged goods.</div>
      <div class="sub">The claim mention in the document are subject to approval by ACE TOOLS PVT. LTD.</div>
    </div>
  </div>
</body>
</html>
