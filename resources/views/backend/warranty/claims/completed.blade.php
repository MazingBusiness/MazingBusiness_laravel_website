@extends('backend.layouts.app')

@section('content')
<style>
  .card-pro{background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 10px 32px rgba(2,6,23,.06);overflow:hidden;}
  .card-pro .hd{padding:14px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e2e8f0;background:linear-gradient(180deg,#f8fafc,#ffffff)}
  .title{margin:0;font-weight:800;color:#0f172a;display:flex;gap:10px;align-items:center}
  .searchbar{display:flex;gap:8px}
  .badge-completed{background:#ecfdf5;color:#065f46;font-weight:700;border-radius:999px;padding:.25rem .5rem}
  .pill{display:inline-flex;align-items:center;gap:6px;background:#eff6ff;color:#1e3a8a;border-radius:999px;padding:.25rem .5rem;font-weight:700}
  .pill .dot{width:8px;height:8px;border-radius:999px;background:#3b82f6;display:inline-block}
  .table thead th{background:#fff;border-bottom:1px solid #e2e8f0!important;color:#64748b;font-weight:700}
  .table tbody tr:hover{background:#f8fafc}
</style>

<div class="card-pro mb-3">
  <div class="hd">
    <h5 class="title"><i class="las la-check-circle" style="color:#16a34a"></i> {{ translate('Completed Claims') }}</h5>
    <form method="GET" class="searchbar">
      <input type="text" name="search" class="form-control form-control-sm"
             value="{{ request('search') }}"
             placeholder="{{ translate('Search ticket, name, phone, email') }}">
      <button class="btn btn-primary btn-sm">{{ translate('Search') }}</button>
      @if(request()->has('search'))
        <a href="{{ route('claims.completed') }}" class="btn btn-soft-secondary btn-sm">{{ translate('Reset') }}</a>
      @endif
    </form>
  </div>

  <div class="p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>{{ translate('Ticket') }}</th>
            <th>{{ translate('Customer') }}</th>
            <th>{{ translate('Party Code') }}</th>
            <th class="text-center">{{ translate('Items') }}</th>
            <th>{{ translate('Created At') }}</th>
            <th class="text-right">{{ translate('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($claims as $c)
            @php
              $pending = max(0, ($c->total_items ?? 0) - ($c->approved_items ?? 0) - ($c->rejected_items ?? 0));
            @endphp
            <tr>
              <td>
                @if($c->ticket_id)
                  <span class="pill"><span class="dot"></span>#{{ $c->ticket_id }}</span>
                @else
                  —
                @endif
                <div><span class="badge-completed">{{ translate('Completed') }}</span></div>
              </td>
              <td>
                <div class="font-weight-bold">{{ $c->name ?? optional($c->user)->name ?? '—' }}</div>
                <div class="text-muted small">{{ optional($c->user)->phone ?? $c->phone ?? '—' }}</div>
                <div class="text-muted small">{{ $c->email ?? '—' }}</div>
              </td>
              <td>{{ optional($c->user)->party_code ?? '—' }}</td>
              <td class="text-center">
                <div class="small">
                  {{ translate('Total') }}: <strong>{{ $c->total_items ?? 0 }}</strong> •
                  {{ translate('Approved') }}: <strong>{{ $c->approved_items ?? 0 }}</strong> •
                  {{ translate('Rejected') }}: <strong>{{ $c->rejected_items ?? 0 }}</strong> •
                  {{ translate('Pending') }}: <strong>{{ $pending }}</strong>
                </div>
              </td>
              <td>{{ optional($c->created_at)->format('d M Y, h:i A') }}</td>
              <td class="text-right">
                <a class="btn btn-outline-primary btn-sm"
                   href="{{ route('claims.show', ['id' => $c->id, 'from' => 'completed']) }}">
                  <i class="las la-eye"></i> {{ translate('View') }}
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">{{ translate('No completed claims found.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="px-3 py-2">
    {{ $claims->links() }}
  </div>
</div>
@endsection
