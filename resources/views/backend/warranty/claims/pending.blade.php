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
    --warn:#f59e0b;
  }
  .page-head h1{ color:var(--ink); margin-bottom:.25rem; font-weight:700; }
  .page-head .meta{ color:var(--muted); font-size:.95rem; }
  .card{ border:1px solid var(--border); }
  .table-responsive{ overflow:auto; }
  .table thead th{ position:sticky; top:0; z-index:1; background:var(--bg); border-bottom:1px solid var(--border)!important; }
  .table tbody tr:hover{ background:var(--row-hover); }
  .table td,.table th{ vertical-align:middle; }
  .chip{ display:inline-flex; align-items:center; gap:8px; padding:.25rem .6rem; border-radius:999px; font-weight:600; font-size:.85rem; }
  .chip .dot{ width:8px; height:8px; border-radius:999px; background:var(--warn); display:inline-block; }
  .aiz-pagination{ margin-top:1rem; }
  /* Big search */
  .search-wrap .input-group.input-group-lg>.form-control{
    height:56px;border-radius:999px 0 0 999px;padding-left:18px;font-size:16px;box-shadow:inset 0 0 0 1px var(--border);
  }
  .search-wrap .input-group.input-group-lg>.input-group-append>.btn{
    height:56px;border-radius:0 999px 999px 0;display:inline-flex;align-items:center;gap:8px;padding:0 18px;
  }
  .search-wrap .btn-clear{ border-radius:999px;height:56px;margin-left:8px; }
</style>

<div class="aiz-titlebar text-left mt-2 mb-3 page-head">
  <h1 class="h3">{{ translate('Warranty — Pending Claims') }}</h1>
  <div class="meta">
    {{ translate('Total') }}: <strong>{{ number_format($claims->total()) }}</strong>
    @if(request('search')) • {{ translate('Search') }}: “{{ request('search') }}” @endif
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
@if (session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
@endif

<div class="card">
  {{-- SEARCH FORM (GET) — separate, NOT wrapping the table --}}
  <div class="card-header search-wrap" style="padding:12px;">
    <form action="{{ route('claims.pending') }}" method="GET" class="w-100">
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
          <a href="{{ route('claims.pending') }}" class="btn btn-outline-secondary btn-clear">
            <i class="las la-times-circle"></i> {{ translate('Clear') }}
          </a>
        @endif
      </div>
    </form>
  </div>

  {{-- TABLE (no outer form here) --}}
  <div class="card-body">
    <div class="table-responsive">
      <table class="table aiz-table mb-0">
        <thead>
          <tr>
            <th width="44">
              <label class="aiz-checkbox mb-0">
                <input type="checkbox" class="check-all">
                <span class="aiz-square-check"></span>
              </label>
            </th>
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
              <td>
                <label class="aiz-checkbox mb-0">
                  <input type="checkbox" class="check-one" name="ids[]" value="{{ $claim->id }}">
                  <span class="aiz-square-check"></span>
                </label>
              </td>

              <td><span class="font-weight-600">{{ $claim->ticket_id ?? '-' }}</span></td>
              <td>{{ $claim->name ?? '-' }}</td>
              <td>{{ $claim->phone ?? '-' }}</td>
              <td>{{ $claim->email ?? '-' }}</td>

              <td>
                <span class="chip badge-warning">
                  <span class="dot"></span> {{ translate('Pending') }}
                </span>
              </td>

              <td>{{ optional($claim->created_at)->format('d M Y, h:i A') }}</td>

              <td class="text-right">
                <div class="btn-group" role="group" aria-label="Row actions">
                  {{-- VIEW (placeholder) --}}
                  <a href="{{ route('claims.show', ['id' => $claim->id, 'from' => 'pending']) }}"
                     class="btn btn-soft-secondary btn-icon btn-circle btn-sm"
                     title="{{ translate('View') }}">
                    <i class="las la-eye"></i>
                  </a>

                  {{-- APPROVE (POST) --}}
                  <form action="{{ route('claims.approve', $claim->id) }}" method="POST" style="display:inline-block;">
                    @csrf
                    <button type="submit"
                            class="btn btn-soft-success btn-icon btn-circle btn-sm"
                            data-toggle="tooltip"
                            title="{{ translate('Approve') }}"
                            onclick="return confirm('{{ translate('Approve this claim?') }}')">
                      <i class="las la-check-circle"></i>
                    </button>
                  </form>

                  {{-- REJECT (POST) --}}
                  <form action="{{ route('claims.reject', $claim->id) }}" method="POST" style="display:inline-block;">
                    @csrf
                    <button type="submit"
                            class="btn btn-soft-danger btn-icon btn-circle btn-sm"
                            data-toggle="tooltip"
                            title="{{ translate('Reject') }}"
                            onclick="return confirm('{{ translate('Reject this claim?') }}')">
                      <i class="las la-times-circle"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-5">
                <i class="las la-inbox" style="font-size:28px;"></i><br>
                {{ translate('No pending claims found') }}
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
</div>
@endsection

@section('script')
<script>
  // Select all
  $(document).on("change", ".check-all", function() {
    $('.check-one:checkbox').prop('checked', this.checked);
  });
  // Tooltips
  $(function () { $('[data-toggle="tooltip"]').tooltip(); });
</script>
@endsection
