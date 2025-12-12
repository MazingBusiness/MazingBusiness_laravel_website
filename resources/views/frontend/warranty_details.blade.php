@extends('frontend.layouts.app')

@section('content')
<style>
  .card { border-radius: 8px; }
  .badge-status{ padding:.35rem .6rem; border-radius:9999px; font-size:12px; }
  .badge-status.pending{ background:#fff3cd; color:#856404; border:1px solid #ffeeba; }
  .badge-status.approved{ background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
  .badge-status.rejected{ background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
  .badge-status.draft{ background:#e2e3e5; color:#383d41; border:1px solid #d6d8db; }

  .twocol { width:100%; border-collapse:collapse; table-layout:fixed; }
  .twocol td { width:50%; vertical-align:top; padding:0 6px; }

  .small { font-size: 12px; color:#555; }
  .table th, .table td { vertical-align: middle; }
</style>

<div class="container my-3">

  {{-- Header --}}
  <div class="card shadow-sm mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
      <div class="mb-2">
        <a href="{{ route('warrantyClaimDetails') }}"
            class="btn btn-sm btn-outline-primary mr-2">
            << BACK
        </a>
        <div class="h5 mb-1">Warranty Claim — <span class="text-primary">{{ $claim->ticket_id }}</span></div>
        <div class="small">
          Created: {{ \Carbon\Carbon::parse($claim->created_at)->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}
        </div>
      </div>
      <div class="mb-2 d-flex align-items-center">
        @php $status = strtolower($claim->status ?? 'pending'); @endphp
        <span class="badge-status {{ $status }} text-uppercase mr-2">{{ $claim->status }}</span>

        {{-- Download Shipping Label (bridge auto-download handled elsewhere) --}}
        @if(!empty($claim->pdf_link))
          <a href="{{ route('warrantyShipPdfDownload', ['ticket' => $claim->ticket_id]) }}"
             class="btn btn-sm btn-outline-primary mr-2">
             <i class="las la-file-pdf"></i> Shipping Label
          </a>
        @endif

        {{-- Upload Courier Info --}}
        @if($claim->corrier_info === NULL)
            <a href="javascript:void(0)" class="btn btn-soft-danger btn-icon btn-circle btn-sm" title="Upload Courier Information" data-toggle="modal" data-target="#courierUploadModal" data-claim-id="{{ $wcKey->id }}" data-ticket="{{ $wcKey->ticket_id }}">
                <i class="las la-cloud-upload-alt"></i>
            </a>
        @endif
      </div>
    </div>
  </div>

  {{-- Parties --}}
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <table class="twocol">
        <thead>
          <tr>
            <th class="py-2">Ship From</th>
            <th class="py-2">Ship To (Warehouse)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td class="pb-2">
              <div class="small">
                <div><strong>{{ $claim->name }}</strong></div>
                <div>{!! nl2br(e(trim(($claim->address ?? '')."\n".($claim->address_2 ?? '')))) !!}</div>
                <div>{{ $claim->city }} {{ $claim->postal_code }}</div>
                @if($claim->gstin) <div>GSTIN: {{ $claim->gstin }}</div> @endif
                @if($claim->aadhar_card) <div>Aadhar: {{ $claim->aadhar_card }}</div> @endif
                @if($claim->email || $claim->phone)
                  <div class="mt-1">{{ $claim->email }} @if($claim->email && $claim->phone)|@endif {{ $claim->phone }}</div>
                @endif
              </div>
            </td>
            <td class="pb-2">
              <div class="small">
                @if($warehouse)
                  <div><strong>{{ $warehouse->name }}</strong></div>
                  <div>{!! nl2br(e($claim->warehouse_address ?: ($warehouse->address ?? ''))) !!}</div>
                @else
                  <div>{!! nl2br(e($claim->warehouse_address)) !!}</div>
                @endif
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      {{-- Courier file (if already uploaded) --}}
      @if(!empty($claim->corrier_info))
        <div class="mt-3">
          <span class="small mr-2">Courier File:</span>
          <a href="{{ $claim->corrier_info }}" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="las la-external-link-alt"></i> View / Download
          </a>
          <span class="small text-muted ml-2">(stored)</span>
        </div>
      @endif
    </div>
  </div>

  {{-- Products --}}
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Products</h6>
        <span class="small text-muted">Total items: {{ $details->count() }}</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead class="thead-light">
            <tr>
              <th>#</th>
              <th>Barcode</th>
              <th>Main Part No.</th>
              <th>Invoice</th>
              <th>Purchase Date</th>
              <th>Warranty Part No.</th>
              <th>Warranty Product Name</th>
              <th>Invoice File</th>
              <th>Warranty Card</th>
              <th>Approval</th>
            </tr>
          </thead>
          <tbody>
            @foreach($details as $i => $d)
              <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $d->barcode }}</td>
                <td>{{ $d->part_number }}</td>
                <td>{{ $d->invoice_no }}</td>
                <td>
                  @if(!empty($d->purchase_date))
                    {{ \Carbon\Carbon::parse($d->purchase_date)->format('d-m-Y') }}
                  @endif
                </td>
                <td>{{ $d->warranty_product_part_number ?: '-' }}</td>
                <td>{{ $d->warrantyProduct->name ?: '-' }}</td>
                <td>
                  @if(!empty($d->attachment_invoice))
                    <a href="{{ str_starts_with($d->attachment_invoice,'http') ? $d->attachment_invoice : url('public/'.$d->attachment_invoice) }}"
                       target="_blank" class="btn btn-xs btn-outline-secondary">
                      <i class="las la-file"></i> View
                    </a>
                  @else
                    <span class="small text-muted">-</span>
                  @endif
                </td>
                <td>
                  @if(!empty($d->attatchment_warranty_card))
                    <a href="{{ str_starts_with($d->attatchment_warranty_card,'http') ? $d->attatchment_warranty_card : url('public/'.$d->attatchment_warranty_card) }}"
                       target="_blank" class="btn btn-xs btn-outline-secondary">
                      <i class="las la-id-card"></i> View
                    </a>
                  @else
                    <span class="small text-muted">-</span>
                  @endif
                </td>
                <td>
                  @php
                    $ap = strtolower($d->approval_status ?? 'pending');
                    $map = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'];
                  @endphp
                  <span class="badge badge-{{ $map[$ap] ?? 'secondary' }}">{{ ucfirst($d->approval_status ?? 'pending') }}</span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

{{-- Modal: Upload Courier Info --}}
<?php /* <div class="modal fade" id="courierUploadModal" tabindex="-1" role="dialog" aria-labelledby="courierUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <form action="{{ route('warranty.courier.upload') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="courierUploadModalLabel">Upload Courier Info</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="claim_id" id="courier_claim_id">
          <input type="hidden" name="ticket_id" id="courier_ticket_id">

          <div class="form-group mb-2">
            <label class="mb-1">File (PDF / Image)</label>
            <input type="file" name="courier_file" class="form-control" required
                   accept=".pdf,.jpg,.jpeg,.png,.webp">
            <small class="text-muted d-block mt-1">Max 5 MB</small>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Upload</button>
        </div>
      </form>
    </div>
  </div>
</div> */?>

@endsection

@push('scripts')
<script>
  // Fill hidden fields when opening modal (Bootstrap 4)
  $('#courierUploadModal').on('show.bs.modal', function (e) {
    var btn = $(e.relatedTarget);
    $('#courier_claim_id').val(btn.data('claim-id'));
    $('#courier_ticket_id').val(btn.data('ticket'));
  });
</script>
@endpush
