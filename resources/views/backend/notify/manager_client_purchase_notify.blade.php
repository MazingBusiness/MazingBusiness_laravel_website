{{-- resources/views/backend/notify/manager_client_purchase_notify.blade.php --}}
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Manager Client Purchase Notify — {{ $manager->name }}</title>
  <style>
    @page { margin: 20px 20px 40px 20px; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#174e84; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; color:#174e84; }
    th { background:#f1f1f1; }
    .no-border td, .no-border th { border: 0 !important; }
    .right { text-align:right; }
    .muted { color:#333; }
    .client-header { font-size: 14px; font-weight: bold; color:#174e84; }
    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }
    .top-pad { margin-top: 8px; }
    .small { font-size: 11px; color:#174e84; }
    .tag { display:inline-block; font-size: 11px; background:#eef2f7; padding:2px 6px; border-radius: 12px; margin-left: 6px; }
  </style>
</head>
<body>

@foreach($clientSections as $index => $section)
  <div class="page">

    {{-- Header image --}}
    <table width="100%" class="no-border">
      <tr>
        <td style="text-align: right;">
          <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png"
               width="100%" alt="Header Image" style="display:block;" />
        </td>
      </tr>
    </table>

    {{-- Static ACE address (left) + Client address (right) --}}
    <table width="100%" cellpadding="10" class="no-border" style="margin-top: 12px;">
      <tr>
        <td width="60%" style="text-align: left; font-size: 14px; font-weight: bold;">
          {{ $aceAddress['line1'] ?? '' }}<br>
          {{ $aceAddress['line2'] ?? '' }}<br>
          {{ $aceAddress['line3'] ?? '' }}<br>
          {{ $aceAddress['line4'] ?? '' }}
        </td>
        <td width="40%" style="text-align: right; font-size: 13px;">
          {{ $section['client']['address']['company_name'] ?? '' }}<br>
          {{ $section['client']['address']['address'] ?? '' }}<br>
          {{ $section['client']['address']['address_2'] ?? '' }}<br>
          Pincode: {{ $section['client']['address']['postal_code'] ?? '' }}
        </td>
      </tr>
    </table>

    {{-- Manager / Client block --}}
    <table class="no-border top-pad">
      <tr>
        <td class="no-border">
          <div class="client-header">
            Manager: {{ $manager->name }}
            <span class="tag">ID: {{ $manager->id }}</span>
          </div>
          <div class="small" style="margin-top:4px;">
            Client: {{ $section['client']['company_name'] ?: $section['client']['name'] }}
            <span class="tag">Party: {{ $section['client']['party_code'] }}</span>
            <span class="tag">Phone: {{ $section['client']['phone'] }}</span>
            <span class="tag">State: {{ $section['client']['state'] }}</span>
          </div>
        </td>
        <td class="no-border right small" style="vertical-align: bottom;">
          Date: {{ \Carbon\Carbon::now()->format('d-m-Y') }}
        </td>
      </tr>
    </table>

    {{-- Data table --}}
    <table width="100%" cellspacing="0" cellpadding="5" style="margin-top: 10px;">
      <thead>
        <tr>
          <th style="text-align:center;width:6%;">SN</th>
          <th style="text-align:center;width:14%;">Part No</th>
          <th style="text-align:center;">Name (Product)</th>
          <th style="text-align:center;width:10%;">Stock</th>
          <th style="text-align:center;width:16%;">Last Purchased</th>
          <th style="text-align:center;width:16%;">Qty Purchased in One Year</th>
          <th style="text-align:center;width:14%;">Quantity Sold</th>
          <th style="text-align:center;width:14%;">Quantity Preclosed</th>
        </tr>
      </thead>
      <tbody>
        @forelse($section['rows'] as $row)
          <tr>
            <td style="text-align:center;">{{ $row['sn'] }}</td>
            <td style="text-align:center;">{{ $row['part_no'] }}</td>
            <td>{{ $row['name'] }}</td>
            <td style="text-align:center;">{{ number_format($row['stock'], 0) }}</td>
            <td style="text-align:center;">{{ $row['last_purchased'] }}</td>
            <td style="text-align:center;">{{ number_format($row['qty_year'], 2) }}</td>
            <td style="text-align:center;">{{ number_format($row['qty_sold_year'], 2) }}</td>
            <td style="text-align:center;">{{ number_format($row['qty_preclosed_year'], 2) }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="8" style="text-align:center;">No matching products.</td>
          </tr>
        @endforelse
      </tbody>
    </table>

    {{-- Footer bar --}}
    <table width="100%" class="no-border" style="margin-top: 10px;">
      <tr bgcolor="#174e84">
        <td style="height: 34px; text-align: center; color: #fff; font-family: Arial; font-weight: bold;">
          ACE TOOLS PVT LTD
        </td>
      </tr>
    </table>

  </div>
@endforeach

</body>
</html>
