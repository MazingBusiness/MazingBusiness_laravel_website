@extends('backend.layouts.app')

@section('content')
<style>
  :root{
    --brand:#0d6efd;
    --ink:#1f2937;
    --muted:#6b7280;
    --bg:#ffffff;
    --row-hover:#f8fafc;
    --border:#e5e7eb;
    --danger:#ef4444;
  }

  /* Page head */
  .page-head h1{
    color:var(--ink);
    margin-bottom:.25rem;
    font-weight:700;
  }
  .page-head .meta{
    color:var(--muted);
    font-size:.95rem;
  }

  /* Big search */
  .search-wrap .input-group.input-group-lg > .form-control{
    height:56px;
    border-radius:999px 0 0 999px;
    padding-left:18px;
    font-size:16px;
    box-shadow: inset 0 0 0 1px var(--border);
  }
  .search-wrap .input-group.input-group-lg > .input-group-append > .btn{
    height:56px;
    border-radius:0 999px 999px 0;
    display:inline-flex; align-items:center; gap:8px;
    padding:0 18px;
  }
  .search-wrap .btn-clear{
    border-radius:999px;
    height:56px;
    margin-left:8px;
  }

  /* Card + table polish */
  .card{ border:1px solid var(--border); }
  .table-responsive{ overflow:auto; }
  .table thead th{
    position: sticky; top: 0; z-index: 1;
    background: var(--bg);
    border-bottom: 1px solid var(--border) !important;
  }
  .table tbody tr:hover{ background: var(--row-hover); }
  .table td, .table th{ vertical-align: middle; }

  /* Status chip */
  .chip{
    display:inline-flex; align-items:center; gap:8px;
    padding:.25rem .6rem; border-radius:999px; font-weight:600;
    font-size:.85rem;
  }
  .chip.danger{ background:rgba(239,68,68,.12); color:#7f1d1d; }
  .chip .dot{ width:8px; height:8px; border-radius:999px; display:inline-block; }
  .chip.danger .dot{ background:var(--danger); }

  /* Pagination spacing */
  .aiz-pagination{ margin-top: 1rem; }
</style>

<div class="aiz-titlebar text-left mt-2 mb-3 page-head">
  <h1 class="h3">{{ translate('Warranty — Rejected Claims') }}</h1>
  <div class="meta">
    {{ translate('Total') }}: <strong>{{ number_format($claims->total()) }}</strong>
    @if(request('search'))
      • {{ translate('Search') }}: “{{ request('search') }}”
    @endif
  </div>
</div>

{{-- Alerts --}}
@if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif
@if (session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
@endif

<div class="card">
  <form action="{{ route('claims.rejected') }}" method="GET">
    {{-- Search toolbar --}}
    <div class="card-header search-wrap" style="padding:12px;">
      <div class="d-flex align-items-center">
        <div class="flex-grow-1">
          <div class="input-group input-group-lg">
            <input type="text"
                   class="form-control"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="{{ translate('Search by Ticket ID, Name, Phone or Email & Enter') }}">
            <div class="input-group-append">
              <button type="submit" class="btn btn-primary">
                <i class="las la-search"></i> {{ translate('Search') }}
              </button>
            </div>
          </div>
        </div>
        @if(request('search'))
          <a href="{{ route('claims.rejected') }}" class="btn btn-outline-secondary btn-clear">
            <i class="las la-times-circle"></i> {{ translate('Clear') }}
          </a>
        @endif
      </div>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table class="table aiz-table mb-0">
          <thead>
            <tr>
              <th>{{ translate('Ticket ID') }}</th>
              <th>{{ translate('Customer Name') }}</th>
              <th>{{ translate('Phone') }}</th>
              <th data-breakpoints="md">{{ translate('Email') }}</th>
              <th>{{ translate('Status') }}</th>
              <th data-breakpoints="md">{{ translate('Created At') }}</th>
              <th class="text-right" data-breakpoints="md">{{ translate('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($claims as $claim)
              <tr>
                <td><span class="font-weight-600">{{ $claim->ticket_id ?? '-' }}</span></td>
                <td>{{ $claim->name ?? '-' }}</td>
                <td>{{ $claim->phone ?? '-' }}</td>
                <td>{{ $claim->email ?? '-' }}</td>
                <td>
                  <span class="chip danger">
                    <span class="dot"></span> {{ translate('Rejected') }}
                  </span>
                </td>
                <td>{{ optional($claim->created_at)->format('d M Y, h:i A') }}</td>
                <td class="text-right">
                  <div class="btn-group" role="group" aria-label="Row actions">
                    {{-- VIEW (placeholder for now) --}}
                    <a href="{{ route('claims.show', ['id' => $claim->id, 'from' => 'rejected']) }}"
                       class="btn btn-soft-secondary btn-icon btn-circle btn-sm"
                       title="{{ translate('View') }}">
                      <i class="las la-eye"></i>
                    </a>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted py-5">
                  <i class="las la-inbox" style="font-size:28px;"></i><br>
                  {{ translate('No rejected claims found') }}
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="aiz-pagination">
        {{ $claims->appends(request()->query())->links() }}
      </div>
    </div>
  </form>
</div>
@endsection

@section('script')
<script>
  // Bootstrap tooltips
  $(function () {
    $('[data-toggle="tooltip"]').tooltip();
  });
</script>
@endsection
