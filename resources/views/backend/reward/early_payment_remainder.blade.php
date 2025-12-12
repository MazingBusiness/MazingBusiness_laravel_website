@extends('backend.layouts.app')



@section('content')
<style>
  .row-processed   { background-color: #e8f5e9 !important; } /* light green */
  .row-unprocessed { background-color: #ffebee !important; } /* light red */
  .row-processed td, .row-unprocessed td { color: #212529; }
</style>

<div class="aiz-titlebar text-left mt-2 mb-4">
  <h1 class="h3 text-primary">{{ translate('Early Payment Reward Reminder') }}</h1>
</div>

{{-- Flash messages --}}
@if (session('status'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('status') }}
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
@endif
@if (session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
@endif

<!-- Filters -->
<div class="card mb-4 shadow-sm">
  <div class="card-header bg-primary text-white">
    <h5 class="mb-0">{{ translate('Filter Options') }}</h5>
  </div>
  <div class="card-body">
    <form id="search_form" action="" method="GET">
      <div class="row gy-3 align-items-end">
        <div class="col-md-6">
          <label for="processed" class="form-label">{{ translate('WhatsApp Status') }}</label>
          <select name="processed" id="processed" class="form-control">
            <option value="">{{ translate('All') }}</option>
            <option value="1" {{ (request('processed') === '1') ? 'selected' : '' }}>
              {{ translate('WhatsApp Sent') }}
            </option>
            <option value="0" {{ (request('processed') === '0') ? 'selected' : '' }}>
              {{ translate('Not Sent') }}
            </option>
          </select>
        </div>
        <div class="col-md-6">
          <label for="search" class="form-label">{{ translate('Search') }}</label>
          <input
            type="text"
            name="search"
            id="search"
            class="form-control"
            placeholder="{{ translate('Search by Party Code, Name, or Phone') }}"
            value="{{ $sort_search ?? '' }}"
          >
        </div>
        <div class="col-md-6 text-end">
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="fas fa-filter"></i> {{ translate('Apply Filter') }}
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead class="table-primary">
          <tr class="text-center">
            <th>#</th>
            <th>{{ translate('Party Code') }}</th>
            <th>{{ translate('Party Name') }}</th>
            <th>{{ translate('Party Phone') }}</th>
            <th>{{ translate('Due Amount') }}</th>
            <th>{{ translate('Overdue Amount') }}</th>
            <th>{{ translate('WA Status') }}</th>
            <th>{{ translate('Actions') }}</th>
          </tr>
        </thead>

        <tbody>
        @forelse ($rows as $idx => $row)
          @php
           
            $collapseId = 'inv_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$row->party_code);
          @endphp

          <tr class="text-center {{ (int)($row->is_processed ?? 0) === 1 ? 'row-processed' : 'row-unprocessed' }}">
            <td>{{ $rows->firstItem() + $idx }}</td>
            <td class="fw-600">{{ $row->party_code }}</td>
            <td>{{ $row->party_name ?: '-' }}</td>
            <td>{{ $row->party_phone ?: '-' }}</td>
            <td>₹ {{ number_format((float) $row->due_amount, 2) }}</td>
            <td>₹ {{ number_format((float) $row->overdue_amount, 2) }}</td>

                <td class="text-center">
              @php
                $st  = strtolower(trim((string) ($row->wa_status ?? '')));
                $cls = $st === 'read' ? 'success'
                     : ($st === 'delivered' ? 'info'
                     : ($st === 'sent' ? 'secondary'
                     : ($st === 'failed' ? 'danger' : 'light')));
              @endphp

              @if($st !== '')
                <span style="width:auto;" class="badge badge-{{ $cls }}">{{ ucfirst($st) }}</span>
                @if(!empty($row->wa_status_at))
                  <small class="text-muted d-block">
                    {{ \Carbon\Carbon::parse($row->wa_status_at)->format('d M Y H:i') }}
                  </small>
                @endif
              @else
                <span class="text-muted">&mdash;</span>
              @endif
            </td>

            <td>
              <a href="{{ route('admin.early_payment.pdf_download', ['party_code' => $row->party_code]) }}"
                 class="btn btn-sm btn-primary">
                {{ translate('Download PDF') }}
              </a>

              <a href="#" class="btn btn-primary btn-sm my_pdf"
                 data-party-code="{{ $row->party_code }}"
                 style="padding: 6px 8px; display: inline-flex; align-items: center; justify-content: center;">
                {{ translate('Statement') }}
              </a>

              <a href="{{ route('sendEarlyPaymentWhatsAppOnButtonClick', ['party_code' => $row->party_code]) }}"
                 class="btn btn-sm btn-success ms-1"
                 title="{{ translate('Send WhatsApp Reminder') }}">
                 <i class="lab la-whatsapp"></i> {{ translate('WhatsApp') }}
              </a>

              <button class="btn btn-sm btn-outline-secondary ms-1"
                      type="button"
                      data-toggle="collapse"
                      data-target="#{{ $collapseId }}"
                      aria-expanded="false"
                      aria-controls="{{ $collapseId }}">
                {{ translate('Invoices') }}
              </button>
            </td>
          </tr>

          {{-- Invoices collapse --}}
          <tr class="bg-light">
            <td colspan="8" class="p-0"><!-- MUST be 8 to match header -->
              <div id="{{ $collapseId }}" class="collapse">
                <div class="p-3">
                  <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                      <thead class="thead-light">
                        <tr class="text-center">
                          <th>{{ translate('Invoice No') }}</th>
                          <th>{{ translate('Invoice Date') }}</th>
                          <th>{{ translate('Invoice Amount') }}</th>
                          <th>{{ translate('Remaining Amount') }}</th>
                          <th>{{ translate('Payment Status') }}</th>
                          <th>{{ translate('Reminder Sent') }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        @forelse(($row->invoices ?? []) as $it)
                          <tr class="text-center {{ (int)($it['is_processed'] ?? 0) === 1 ? 'row-processed' : 'row-unprocessed' }}">
                            <td>
                              @if(!empty($it['invoice_id']))
                                <a href="{{ route('invoice.downloadPdf', ['id' => $it['invoice_id']]) }}"
                                   target="_blank" rel="noopener"
                                   title="{{ translate('Download Invoice PDF') }}">
                                  {{ strtoupper($it['invoice_no']) }}
                                </a>
                              @else
                                {{ strtoupper($it['invoice_no']) }}
                              @endif
                            </td>
                            <td>{{ $it['invoice_date'] }}</td>
                            <td>₹ {{ number_format((float)$it['invoice_amount'], 2) }}</td>
                            <td>₹ {{ number_format((float)$it['remaining_amount'], 2) }}</td>
                            <td>{{ $it['payment_status'] }}</td>
                            <td>{{ $it['reminder_sent'] }}</td>
                          </tr>
                        @empty
                          <tr>
                            <td colspan="6" class="text-center text-muted">
                              {{ translate('No invoices for this party.') }}
                            </td>
                          </tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </td>
          </tr>

        @empty
          <tr>
            <td colspan="8" class="text-center text-muted py-4">
              {{ translate('No data found.') }}
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-4">
      {{ $rows->appends(request()->input())->links() }}
    </div>
  </div>
</div>

<!-- PDF Modal -->
<div class="modal fade" id="pdfModal" tabindex="-1" role="dialog" aria-labelledby="pdfModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content" style="height: 90vh;">
      <div class="modal-header">
        <h5 class="modal-title" id="pdfModalLabel">View PDF</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" style="padding: 0; height: 100%;">
        <iframe id="pdfViewer" src="" frameborder="0" width="100%" height="100%" style="height: 100%;"></iframe>
      </div>
    </div>
  </div>
</div>
@endsection

@section('script')
<script>
$(document).ready(function () {
  // Open PDF in modal
  $(document).on('click', '.my_pdf', function(event) {
    event.preventDefault();
    let partyCode = $(this).data('party-code');

    $.ajax({
      url: `/admins/create-pdf/${partyCode}`,
      type: 'GET',
      success: function(res) {
        if (res && res.pdf_url) {
          $('#pdfViewer').attr('src', res.pdf_url);
          $('#pdfModal').modal('show');
        } else {
          alert("Failed to generate PDF. Please try again.");
        }
      },
      error: function(xhr) {
        console.error("PDF error:", xhr.responseText);
        alert("An error occurred while generating the PDF.");
      }
    });
  });
});
</script>
@endsection
