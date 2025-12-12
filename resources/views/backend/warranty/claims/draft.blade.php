@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3">
  <h1 class="h3">{{ translate('Warranty - Draft Claims') }}</h1>
</div>

@if (session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
  </div>
@endif

<div class="card">
  <form action="{{ route('claims.draft') }}" method="GET">
    <div class="card-header" style="padding:10px;">
      <div class="row align-items-center">
        <div class="col-md-8 mb-2 mb-md-0">
          <input type="text" class="form-control" name="search" value="{{ request('search') }}"
                 placeholder="{{ translate('Search by Ticket ID, Name, Phone or Email & Enter') }}">
        </div>
        <div class="col-md-4 text-md-right">
          <button type="submit" class="btn btn-primary">
            <i class="las la-search"></i> {{ translate('Search') }}
          </button>
        </div>
      </div>
    </div>

    <div class="card-body">
      <table class="table aiz-table mb-0">
        <thead>
          <tr>
            <th>{{ translate('Ticket ID') }}</th>
            <th>{{ translate('Customer Name') }}</th>
            <th>{{ translate('Phone') }}</th>
            <th data-breakpoints="md">{{ translate('Email') }}</th>
            <th>{{ translate('Status') }}</th>
            <th data-breakpoints="md">{{ translate('Created At') }}</th>
            <th class="text-right" data-breakpoints="md">{{ translate('Options') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($claims as $claim)
            <tr>
              <td>{{ $claim->ticket_id ?? '-' }}</td>
              <td>{{ $claim->name ?? '-' }}</td>
              <td>{{ $claim->phone ?? '-' }}</td>
              <td>{{ $claim->email ?? '-' }}</td>
              <td><span style="width: auto;"  class="badge badge-secondary">{{ translate('Draft') }}</span></td>
              <td>{{ optional($claim->created_at)->format('d-M-Y H:i') }}</td>
              <td class="text-right">
               <a href="{{ route('claims.show', ['id' => $claim->id, 'from' => 'draft']) }}"
                   class="btn btn-soft-secondary btn-icon btn-circle btn-sm"
                   title="{{ translate('View') }}">
                  <i class="las la-eye"></i>
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">{{ translate('No draft claims found') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>

      <div class="aiz-pagination">
        {{ $claims->links() }}
      </div>
    </div>
  </form>
</div>
@endsection
