@extends('backend.layouts.app')

@section('content')
@php
  // --------- View helpers / data ----------
  $wu      = $claim->user ?? null;            // WarrantyUser
  $rows    = $claim->details ?? collect();    // WarrantyClaimDetail collection

  // Counts come from controller: $total, $approvedCnt, $rejectedCnt, $pendingCnt

  // Avatar initial (from customer/warranty user name)
  $cust    = $wu->name ?? $claim->name ?? null;
  $initial = $cust ? mb_strtoupper(mb_substr($cust, 0, 1)) : 'U';

  // Small helpers
  $isImage = function($mimeOrUrl) {
    $str = strtolower((string)$mimeOrUrl);
    if (strpos($str, 'image/') === 0) return true;
    $ext = strtolower(pathinfo(parse_url((string)$mimeOrUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    return in_array($ext, ['jpg','jpeg','png','gif','webp']);
  };
  $isPdf = function($mimeOrUrl) {
    $str = strtolower((string)$mimeOrUrl);
    if ($str === 'application/pdf' || strpos($str, 'pdf') !== false) return true;
    $ext = strtolower(pathinfo(parse_url((string)$mimeOrUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    return $ext === 'pdf';
  };

  // Draft mode? (either status is draft, or opened with from=draft)
  $fromDraft = (strtolower($claim->status ?? '') === 'draft') || (request()->get('from') === 'draft');

  // Status helpers (used everywhere)
  $statusRibbon = strtolower($claim->status ?? 'pending');
  $isCompleted  = ($statusRibbon === 'completed');
@endphp

<style>
  :root{
    --ink:#0f172a;          /* headings */
    --text:#334155;         /* body */
    --muted:#64748b;        /* meta */
    --border:#e2e8f0;       /* lines */
    --bg:#ffffff;           /* cards */
    --soft:#f8fafc;         /* table hover */
    --brand:#3b82f6;        /* blue */
    --brand-2:#6366f1;      /* indigo */
    --ok:#10b981;           /* green */
    --warn:#f59e0b;         /* amber */
    --err:#ef4444;          /* red */
    --draft:#475569;        /* slate */
  }

  .hero{
    position:relative;
    border:1px solid var(--border);
    border-radius:18px;
    padding:18px 16px;
    margin-bottom:18px;
    background:
      radial-gradient(75% 120% at 10% -20%, rgba(59,130,246,.10), transparent 60%),
      radial-gradient(75% 120% at 110% 10%, rgba(99,102,241,.12), transparent 60%),
      #fff;
    overflow:hidden;
  }
  .hero:after{
    content:"";
    position:absolute; inset:0;
    background:linear-gradient(180deg, rgba(99,102,241,.05), rgba(59,130,246,.03));
    pointer-events:none;
  }
  .hero h1{ color:var(--ink); font-weight:800; letter-spacing:.2px; margin-bottom:4px; }
  .hero .meta{ color:var(--muted); font-size:.95rem; }

  .status-ribbon{
    position:absolute; top:18px; right:-34px;
    transform:rotate(45deg);
    color:#fff; font-weight:800; font-size:.78rem; letter-spacing:.5px;
    padding:6px 48px;
    text-transform:uppercase;
    box-shadow:0 8px 24px rgba(2,6,23,.12);
  }
  .status-ribbon.pending{  background:linear-gradient(90deg, #f59e0b, #fbbf24); }
  .status-ribbon.approved{ background:linear-gradient(90deg, #10b981, #22c55e); }
  .status-ribbon.rejected{ background:linear-gradient(90deg, #ef4444, #f87171); }
  .status-ribbon.draft{    background:linear-gradient(90deg, #475569, #64748b); }

  .pill{
    display:inline-flex; align-items:center; gap:8px;
    padding:.35rem .7rem; border-radius:999px; font-weight:700; font-size:.8rem;
    background:#eef2ff; color:#3730a3;
  }
  .pill .dot{ width:8px; height:8px; border-radius:999px; background:#6366f1; display:inline-block; }
  .pill.time{ background:#eff6ff; color:#1e40af; }
  .pill.time .dot{ background:#3b82f6; }

  .card-pro{
    background:var(--bg);
    border:1px solid var(--border);
    border-radius:16px;
    box-shadow:0 10px 32px rgba(2,6,23,.06);
    overflow:hidden;
  }
  .card-pro .hd{
    padding:14px 16px;
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    border-bottom:1px solid var(--border);
    background:linear-gradient(180deg,#f8fafc,#ffffff);
  }
  .card-pro .title{
    display:flex; align-items:center; gap:10px; margin:0; color:var(--ink); font-weight:800;
  }
  .card-pro .title i{ font-size:20px; color:var(--brand-2); }
  .card-pro .bd{ padding:14px 16px; }

  .avatar{
    width:44px; height:44px; border-radius:999px;
    background:linear-gradient(145deg, #3b82f6, #6366f1);
    color:#fff; display:flex; align-items:center; justify-content:center;
    font-weight:900; letter-spacing:.5px;
    box-shadow:0 8px 22px rgba(99,102,241,.25);
  }

  .kv{ display:flex; gap:12px; padding:9px 0; border-bottom:1px dashed var(--border); }
  .kv:last-child{ border-bottom:0; }
  .k{ width:160px; color:var(--muted); font-weight:600; }
  .v{ color:var(--text); font-weight:700; }

  .file-mini{ display:flex; align-items:center; gap:10px; }
  .file-mini .thumb{
    width:42px; height:42px; border-radius:8px; background:#f1f5f9;
    display:flex; align-items:center; justify-content:center; overflow:hidden;
  }
  .file-mini .thumb img{ width:100%; height:100%; object-fit:cover; }
  .file-mini .meta{ min-width:0; }
  .file-mini .name{
    max-width:210px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    font-weight:700; color:var(--ink);
  }

  .btn-approve-sm{
    background:linear-gradient(90deg, #10b981, #22c55e);
    border:none; color:#fff; font-weight:800; border-radius:8px; padding:.35rem .6rem;
  }
  .btn-reject-sm{
    background:linear-gradient(90deg, #ef4444, #f87171);
    border:none; color:#fff; font-weight:800; border-radius:8px; padding:.35rem .6rem;
  }

  .table-frame{
    border:1px solid var(--border);
    border-radius:16px;
    overflow:hidden;
    box-shadow:0 10px 32px rgba(2,6,23,.06);
  }
  .table-title{
    display:flex; align-items:center; justify-content:space-between; gap:8px;
    padding:12px 16px; border-bottom:1px solid var(--border);
    background:linear-gradient(180deg,#f8fafc,#ffffff);
    color:var(--ink); font-weight:800;
  }
  .table-title .sub{ color:var(--muted); font-weight:600; font-size:.9rem; }

  .table thead th{
    background:#fff; border-bottom:1px solid var(--border) !important;
    color:var(--muted); font-weight:700;
  }
  .table td, .table th{ vertical-align:middle; }
  .table tbody tr:hover{ background:var(--soft); transition:background .2s ease; }

  .action-bar{
    position:sticky; bottom:0; z-index:10;
    background:#fff; border:1px solid var(--border); border-radius:14px;
    padding:12px; display:flex; justify-content:flex-end; gap:10px;
    box-shadow:0 10px 32px rgba(2,6,23,.06);
    margin-top:16px;
  }
  .btn-draft{
    background:linear-gradient(90deg, #475569, #64748b);
    border:none; color:#fff; font-weight:800; border-radius:10px; padding:.6rem 1rem;
  }
  .btn-invoice{
    background:linear-gradient(90deg, #3b82f6, #6366f1);
    border:none; color:#fff; font-weight:800; border-radius:10px; padding:.6rem 1rem;
  }
  .btn-credit{
    background:linear-gradient(90deg, #f59e0b, #f97316);
    border:none; color:#fff; font-weight:800; border-radius:10px; padding:.6rem 1rem;
  }
  a.disabled, .btn.disabled { pointer-events: none; opacity: .65; }
</style>

{{-- HERO / HEADER --}}
<div class="hero">
  <div class="d-flex align-items-center justify-content-between flex-wrap">
    <div class="d-flex align-items-center" style="gap:12px;">
      <div class="avatar">{{ $initial }}</div>
      <div>
        <h1 class="h4 mb-1">
          {{ translate('Warranty Claim') }}
          @if($claim->ticket_id)
            <span class="pill ml-2"><span class="dot"></span>#{{ $claim->ticket_id }}</span>
          @endif
        </h1>
        <div class="meta d-flex align-items-center" style="gap:10px; flex-wrap:wrap;">
          <span class="pill time"><span class="dot"></span>{{ translate('Created') }}: {{ optional($claim->created_at)->format('d M Y, h:i A') }}</span>
          <span class="text-muted">•</span>
          <span class="text-muted">
            {{ translate('Total') }}: <strong>{{ $total }}</strong>
            • {{ translate('Approved') }}: <strong>{{ $approvedCnt }}</strong>
            • {{ translate('Rejected') }}: <strong>{{ $rejectedCnt }}</strong>
            • {{ translate('Pending') }}: <strong>{{ $pendingCnt }}</strong>
          </span>
        </div>
      </div>
    </div>

    {{-- Ribbon (from claim status) --}}
    <div class="status-ribbon {{ $statusRibbon }}">
      {{ strtoupper($statusRibbon) }}
    </div>
  </div>
</div>

{{-- FLASHES --}}
@if (session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
@endif
@if (session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
@endif

<div class="row">
  {{-- CUSTOMER DETAILS --}}
  <div class="col-lg-6 mb-3">
    <div class="card-pro h-100">
      <div class="hd">
        <h5 class="title mb-0"><i class="las la-user-circle"></i> {{ translate('Customer Details') }}</h5>
      </div>
      <div class="bd">
        <div class="kv"><div class="k">{{ translate('Name') }}</div>       <div class="v">{{ $wu->name ?? $claim->name ?? '—' }}</div></div>
        <div class="kv"><div class="k">{{ translate('Phone') }}</div>      <div class="v">{{ $wu->phone ?? $claim->phone ?? '—' }}</div></div>
        <div class="kv"><div class="k">{{ translate('Email') }}</div>      <div class="v">{{ $claim->email ?? '—' }}</div></div>
        <div class="kv"><div class="k">{{ translate('Party Code') }}</div> <div class="v">{{ $wu->party_code ?? '—' }}</div></div>
        <div class="kv"><div class="k">{{ translate('GST') }}</div>        <div class="v">{{ $wu->gst ?? $claim->gstin ?? '—' }}</div></div>
      </div>
    </div>
  </div>

  {{-- TICKET INFO --}}
  <div class="col-lg-6 mb-3">
    <div class="card-pro h-100">
      <div class="hd">
        <h5 class="title mb-0"><i class="las la-ticket-alt"></i> {{ translate('Ticket Info') }}</h5>
      </div>
      <div class="bd">
        <div class="kv"><div class="k">{{ translate('Ticket ID') }}</div>  <div class="v">{{ $claim->ticket_id ?? '—' }}</div></div>
        <div class="kv"><div class="k">{{ translate('Status') }}</div>
          <div class="v">
            @switch($statusRibbon)
              @case('approved')
                <span class="pill" style="background:#ecfdf5;color:#065f46;"><span class="dot" style="background:var(--ok)"></span>{{ translate('Approved') }}</span>
                @break
              @case('rejected')
                <span class="pill" style="background:#fef2f2;color:#7f1d1d;"><span class="dot" style="background:var(--err)"></span>{{ translate('Rejected') }}</span>
                @break
              @case('draft')
                <span class="pill" style="background:#e2e8f0;color:#0f172a;"><span class="dot" style="background:var(--draft)"></span>{{ translate('Draft') }}</span>
                @break
              @case('completed')
                <span class="pill" style="background:#ecfdf5;color:#065f46;"><span class="dot" style="background:var(--draft)"></span>{{ translate('completed') }}</span>
                @break
              @default
                <span class="pill" style="background:#fffbeb;color:#92400e;"><span class="dot" style="background:var(--warn)"></span>{{ translate('Pending') }}</span>
            @endswitch
          </div>
        </div>
        <div class="kv"><div class="k">{{ translate('Created At') }}</div> <div class="v">{{ optional($claim->created_at)->format('d M Y, h:i A') }}</div></div>
        <div class="kv"><div class="k">{{ translate('Address') }}</div>
          <div class="v">
            {{ $claim->address ?? '' }}
            {{ $claim->address_2 ? ', '.$claim->address_2 : '' }}
            {{ $claim->city ? ', '.$claim->city : '' }}
            {{ $claim->postal_code ? ' - '.$claim->postal_code : '' }}
            @if(empty($claim->address) && empty($claim->city)) — @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- PRODUCTS TABLE --}}
<div class="table-frame mb-4">
  <div class="table-title">
    <span><i class="las la-boxes mr-1" style="color:var(--brand)"></i> {{ translate('Products in this Claim') }}</span>
    <span class="sub">
      {{ translate('Total') }}: <strong>{{ $total }}</strong>
      • {{ translate('Approved') }}: <strong>{{ $approvedCnt }}</strong>
      • {{ translate('Rejected') }}: <strong>{{ $rejectedCnt }}</strong>
      • {{ translate('Pending') }}: <strong>{{ $pendingCnt }}</strong>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>{{ translate('Barcode') }}</th>
          <th>{{ translate('Part No') }}</th>
          <th>{{ translate('Product Name') }}</th>
          <th>{{ translate('Invoice No') }}</th>
          <th>{{ translate('Purchase Date') }}</th>
          <th>{{ translate('Warranty') }}</th>
          <th>{{ translate('Invoice Attachment') }}</th>
          <th>{{ translate('Warranty Card Attachment') }}</th>
          <th>{{ translate('Item Status') }}</th>
          <th class="text-right">{{ translate('Item Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
          @php
            $invoiceUrl = $row->attachment_invoice ?? null;
            $cardUrl    = $row->attatchment_warranty_card ?? null; // note: attatchment*
            $st         = (int)($row->approval_status ?? 0); // 0 pending, 1 approved, 2 rejected, 3 completed

            // Disable links if claim is completed (as per requirement)
            $openHrefInv      = ($invoiceUrl && !$isCompleted) ? $invoiceUrl : '#';
            $downloadHrefInv  = ($invoiceUrl && !$isCompleted) ? $invoiceUrl : '#';
            $openHrefCard     = ($cardUrl   && !$isCompleted) ? $cardUrl   : '#';
            $downloadHrefCard = ($cardUrl   && !$isCompleted) ? $cardUrl   : '#';

            $disableAttr = $isCompleted ? 'aria-disabled=true onclick="return false;"' : '';
            $btnOpenCls  = 'btn btn-outline-primary btn-sm'.($isCompleted ? ' disabled' : '');
            $btnDlCls    = 'btn btn-soft-secondary btn-sm'.($isCompleted ? ' disabled' : '');
          @endphp
          <tr>
            <td>{{ $row->barcode ?? '—' }}</td>
            <td>{{ $row->warranty_product_part_number ?? $row->part_number ?? '—' }}</td>
            <td>
              {{ optional($row->warrantyProduct)->name
                 ?? optional($row->product)->name
                 ?? '—' }}
            </td>
            <td>{{ $row->invoice_no ?? '—' }}</td>
            <td>
              @if($row->purchase_date)
                {{ \Carbon\Carbon::parse($row->purchase_date)->format('d M Y') }}
              @else
                —
              @endif
            </td>
            <td>{{ $row->warranty_duration ? $row->warranty_duration.' '.translate('months') : '—' }}</td>

            {{-- Invoice Attachment --}}
            <td>
  @if($invoiceUrl)
    <div class="file-mini">
      <div class="thumb">
        @if($isImage($invoiceUrl))
          <img src="{{ $invoiceUrl }}" alt="invoice">
        @elseif($isPdf($invoiceUrl))
          <i class="las la-file-pdf" style="font-size:24px;color:#ef4444"></i>
        @else
          <i class="las la-file" style="font-size:24px;color:#3b82f6"></i>
        @endif
      </div>
      <div class="meta">
        <div class="name" title="{{ basename(parse_url($invoiceUrl, PHP_URL_PATH) ?? '') }}">
          {{ basename(parse_url($invoiceUrl, PHP_URL_PATH) ?? '') ?: translate('Attachment') }}
        </div>
        <div class="act mt-1">
          <a class="btn btn-outline-primary btn-sm" target="_blank" href="{{ $invoiceUrl }}">
            <i class="las la-external-link-alt"></i> {{ translate('Open') }}
          </a>
          <a class="btn btn-soft-secondary btn-sm" href="{{ $invoiceUrl }}" download>
            <i class="las la-download"></i> {{ translate('Download') }}
          </a>
        </div>
      </div>
    </div>
  @else
    <span class="text-muted">—</span>
  @endif
</td>


            {{-- Warranty Card Attachment --}}
            <td>
  @if($cardUrl)
    <div class="file-mini">
      <div class="thumb">
        @if($isImage($cardUrl))
          <img src="{{ $cardUrl }}" alt="warranty-card">
        @elseif($isPdf($cardUrl))
          <i class="las la-file-pdf" style="font-size:24px;color:#ef4444"></i>
        @else
          <i class="las la-file" style="font-size:24px;color:#3b82f6"></i>
        @endif
      </div>
      <div class="meta">
        <div class="name" title="{{ basename(parse_url($cardUrl, PHP_URL_PATH) ?? '') }}">
          {{ basename(parse_url($cardUrl, PHP_URL_PATH) ?? '') ?: translate('Attachment') }}
        </div>
        <div class="act mt-1">
          <a class="btn btn-outline-primary btn-sm" target="_blank" href="{{ $cardUrl }}">
            <i class="las la-external-link-alt"></i> {{ translate('Open') }}
          </a>
          <a class="btn btn-soft-secondary btn-sm" href="{{ $cardUrl }}" download>
            <i class="las la-download"></i> {{ translate('Download') }}
          </a>
        </div>
      </div>
    </div>
  @else
    <span class="text-muted">—</span>
  @endif
</td>


            {{-- Item Status --}}
            <td>
              @if($st === 1)
                <span style="width: auto;" class="badge badge-success">{{ translate('Approved') }}</span>
              @elseif($st === 2)
                <span style="width: auto;" class="badge badge-danger">{{ translate('Rejected') }}</span>
              @elseif($st === 3)
                <span style="width: auto;" class="badge badge-primary">{{ translate('Completed') }}</span>
              @else
                <span style="width: auto;" class="badge badge-warning">{{ translate('Pending') }}</span>
              @endif
            </td>

            {{-- Per-item Approve / Reject --}}
            <td class="text-right">
              @if(!$isCompleted)
                <div class="btn-group" role="group" aria-label="Item actions">
                  @if($st !== 1)
                    <form action="{{ route('claims.details.approve', $row->id) }}" method="POST" class="d-inline">
                      @csrf
                      <button type="submit"
                              class="btn-approve-sm"
                              onclick="return confirm('{{ translate('Approve this item?') }}')">
                        <i class="las la-check-circle"></i>
                      </button>
                    </form>
                  @endif

                  @if($st !== 2)
                    <form action="{{ route('claims.details.reject', $row->id) }}" method="POST" class="d-inline">
                      @csrf
                      <button type="submit"
                              class="btn-reject-sm"
                              onclick="return confirm('{{ translate('Reject this item?') }}')">
                        <i class="las la-times-circle"></i>
                      </button>
                    </form>
                  @endif
                </div>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="10" class="text-center text-muted py-4">{{ translate('No products added to this claim.') }}</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- CLAIM-LEVEL ACTIONS --}}
<div class="action-bar">
  @if($statusRibbon === 'completed')
    {{-- Completed => show single Download button, preferring Invoice > Credit Note --}}
    @php
      $downloadUrl   = null;
      $downloadLabel = null;

      if (!empty($claim->invoice_order_id)) {
          // Prefer invoice when present
          $downloadUrl   = route('invoice.downloadPdf', $claim->invoice_order_id);
          $downloadLabel = __('Download Invoice PDF');
      } elseif (!empty($claim->purchase_invoice_id)) {
          // Else fallback to Credit Note PDF
          $downloadUrl   = route('admin.credit_note.download_pdf', $claim->purchase_invoice_id);
          $downloadLabel = __('Download Credit Note PDF');
      }
    @endphp

    @if($downloadUrl)
      <a href="{{ $downloadUrl }}" class="btn-invoice">
        <i class="las la-download"></i> {{ $downloadLabel }}
      </a>
    @else
      <span class="text-muted">{{ translate('No document available') }}</span>
    @endif

  @elseif($statusRibbon === 'approved' || $statusRibbon === 'draft')
    {{-- Approved & Draft => show BOTH buttons --}}
    <a href="{{ route('claims.to_suborder', $claim->id) }}" class="btn-invoice">
      <i class="las la-file-invoice"></i> {{ translate('Save Order') }}
    </a>
    <a href="{{ route('claims.credit_note.service', ['claim' => $claim->id]) }}" class="btn-credit">
      <i class="las la-file-invoice-dollar"></i> {{ translate('Assign Credit Note') }}
    </a>

    @if($statusRibbon === 'draft')
      {{-- Optional: keep draft->approved action hidden --}}
      <form style="display:none" action="{{ route('claims.save', $claim->id) }}" method="POST" class="m-0">
        @csrf
        <input type="hidden" name="from" value="{{ request('from') }}">
        <button type="submit" class="btn btn-success"
                onclick="return confirm('{{ translate('Save this draft as Approved?') }}')">
          <i class="las la-save"></i> {{ translate('Save') }}
        </button>
      </form>
    @endif

  @else
    {{-- Pending / Rejected => Save to Draft --}}
    <form action="{{ route('claims.draft.update', $claim->id) }}" method="POST" class="m-0">
      @csrf
      <input type="hidden" name="from" value="{{ request('from') }}">
      <button type="submit" class="btn btn-draft"
              onclick="return confirm('{{ translate('Move this claim to Draft?') }}')">
        <i class="las la-file"></i> {{ translate('Save to Draft') }}
      </button>
    </form>
  @endif
</div>

@endsection

@section('script')
<script>
  $(function () { $('[data-toggle="tooltip"]').tooltip(); });
</script>
@endsection
