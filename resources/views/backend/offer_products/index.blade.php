@extends('backend.layouts.app')

@section('content')
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <h5 class="mb-0 h6">{{ translate('Offers List') }}</h5>
       <div class="aiz-titlebar d-flex justify-content-between align-items-center mt-2 mb-3">
    <div></div> <!-- Empty div for alignment purposes -->
    <a href="{{ route('offer-products.create') }}" class="btn btn-primary">
        <i class="las la-plus"></i> {{ translate('Add Offer') }}
    </a>
</div>

    </div>

    <div class="card">
        <div class="card-body">
            <!-- Display Success Message -->
            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Search Form -->
            <form method="GET" action="{{ route('offers.index') }}" class="mb-4">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="{{ translate('Search by Offer Name or Product Part No') }}" value="{{ request('search') }}">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary">{{ translate('Search') }}</button>
                    </div>
                </div>
            </form>

            <table class="table aiz-table mb-0">
                <thead>
                    <tr>
                        <th>#</th> <!-- Serial Number Column -->
                        <th>{{ translate('Offer ID') }}</th>
                        <th>{{ translate('Offer Name') }}</th>
                        <th>{{ translate('Offer Value') }}</th>
                        <th>{{ translate('Value Type') }}</th>
                        <th>{{ translate('Offer Type') }}</th>
                        <th>{{ translate('Product Count') }}</th> <!-- New Column for Product Count -->
                        <th>{{ translate('Validity') }}</th> <!-- Combined Validity Column -->
                        <th>{{ translate('Status') }}</th> <!-- New Column for Days Remaining -->
                        <th>{{ translate('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($offers as $index => $offer)
                        <tr>
                            <td>{{ $index + 1 }}</td> <!-- Serial Number -->
                            <td>{{ $offer->offer_id }}</td>
                            <td>{{ $offer->offer_name }}</td>
                            <td>{{ $offer->offer_value }}</td>
                            <td>{{ ucfirst($offer->value_type) }}</td>
                            <td>
                                @if ($offer->offer_type == 1)
                                    Item Wise
                                @elseif ($offer->offer_type == 2)
                                    Total
                                @elseif ($offer->offer_type == 3)
                                    Complementary
                                @else
                                    {{ ucfirst($offer->offer_type) }}
                                @endif
                            </td>
                            <td>{{ $offer->product_count }}</td> <!-- Display Product Count -->
                            <!-- Combined Validity Column -->
                            <td>{{ date('d-m-Y', strtotime($offer->offer_validity_start)) }} to {{ date('d-m-Y', strtotime($offer->offer_validity_end)) }}</td>
                            <td>
                                <label class="aiz-switch aiz-switch-success mb-0">
                                    <input type="checkbox" class="status-toggle" data-id="{{ $offer->id }}" {{ $offer->status == 1 ? 'checked' : '' }}>
                                    <span></span>
                                </label>
                            </td> <!-- Display Days Remaining -->
                            <td>
                                <!-- Actions for view, edit, and delete -->
                                <a href="{{ route('offer-products.view', ['offer_id' => $offer->offer_id]) }}" class="btn btn-soft-info btn-icon btn-circle btn-sm" title="{{ translate('View') }}">
                                    <i class="las la-eye"></i>
                                </a>
                                <a href="{{ route('offer-products.edit', ['offer_id' => $offer->offer_id]) }}" class="btn btn-soft-primary btn-icon btn-circle btn-sm" title="{{ translate('Edit') }}">
                                    <i class="las la-edit"></i>
                                </a>

                                 <!-- Delete Button -->
                                <form action="{{ route('offer.delete', ['offer_id' => $offer->offer_id]) }}" method="POST" style="display:inline;" class="delete-offer-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-soft-danger btn-icon btn-circle btn-sm delete-offer-btn" title="{{ translate('Delete') }}">
                                        <i class="las la-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('script')
<script>


    $(document).on('change', '.status-toggle', function() {
        var offerId = $(this).data('id');
        var status = $(this).is(':checked') ? 1 : 0; // Get the new status
       

        $.ajax({
            url: '{{ route("offer.update.status") }}', // Route to handle the update
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}', // CSRF token for security
                offer_id: offerId,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    AIZ.plugins.notify('success', 'Status updated successfully.');
                } else {
                    AIZ.plugins.notify('danger', 'Failed to update status.');
                    
                }
            },
            error: function(xhr) {
                alert('An error occurred while updating status');
            }
        });
    });

    $(document).on('click', '.delete-offer-btn', function(e) {
        e.preventDefault();
        var form = $(this).closest('form');

        if (confirm('{{ translate("Are you sure you want to delete this offer? This action cannot be undone.") }}')) {
            form.submit();
        }
    });
</script>

@endsection
