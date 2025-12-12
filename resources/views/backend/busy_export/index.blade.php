@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar d-flex justify-content-between align-items-center mt-2 mb-3">
    <h1 class="h3 text-dark mb-0">🧾 Busy Export Format</h1>
    <a href="{{ route('busy.export.download') }}" style="border-radius:42px;background-color:#6A5ACD;" href="#" class="btn btn-success">
        <i class="las la-file-export"></i> Export to Excel
    </a>
</div>

<div class="card">
    <div class="card-body">

        <table class="table table-bordered text-center">
            <thead style="background: #247BA0; color: #fff;">
                <tr>
                    <th>VCH Series</th>
                    <th>VCH Bill Date</th>
                    <th>VCH Type</th>
                    <th>VCH Bill No</th>
                    <th>Party Code</th>
                    <th>Party Name</th>
                    <th>MC Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data as $index => $d)
                    <tr>
                        <td>{{ $d['vch_series'] }}</td>
                        <td>{{ $d['vch_bill_date'] }}</td>
                        <td>{{ $d['vch_type'] }}</td>
                        <td>{{ $d['vch_bill_no'] }}</td>
                        <td>{{ $d['party_code'] }}</td>
                        <td>{{ $d['party_name'] }}</td>
                        <td>{{ $d['mc_name'] }}</td>
                        <td>
                            <button onclick="toggleDetails({{ $index }})" class="btn btn-sm btn-outline-primary">
                                <i class="las la-plus-circle" id="icon-{{ $index }}"></i>
                            </button>
                        </td>
                    </tr>
                    <tr id="details-{{ $index }}" style="display:none;" class="bg-light">
                        <td colspan="8">
                            <table class="table table-sm table-bordered mt-2 mb-0">
                                <thead class="bg-secondary text-white">
                                    <tr>
                                        <th>Part No</th>
                                        <th>Qty</th>
                                        <th>Unit</th>
                                        <th>List Price</th>
                                        <th>Discount</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($d['items'] as $item)
                                        <tr>
                                            <td>{{ $item['part_no'] }}</td>
                                            <td>{{ $item['qty'] }}</td>
                                            <td>{{ $item['unit'] }}</td>
                                            <td>{{ number_format($item['list_price'], 2) }}</td>
                                            <td>{{ $item['discount'] }}</td>
                                            <td>{{ number_format($item['amount'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                @endforeach
            </tbody>

        </table>
        <div class="mt-4 p-3 bg-white rounded shadow-sm border d-flex justify-content-between align-items-center flex-wrap">
            <div class="text-dark mb-2">
                <p class="mb-1">
                    <i class="las la-database text-primary"></i>
                    <strong>Total Records:</strong> {{ $data->total() }}
                </p>
                <p class="mb-1">
                    <i class="las la-list-ol text-success"></i>
                    <strong>Page:</strong> {{ $data->currentPage() }} of {{ $data->lastPage() }}
                </p>
                <p class="mb-1">
                    <i class="las la-copy text-warning"></i>
                    <strong>Per Page:</strong> {{ $data->perPage() }}
                </p>
            </div>

            <div class="mb-2">
                {{ $data->links() }}
            </div>
        </div>

    </div>
</div>
@endsection

@section('script')
<script>
function toggleDetails(index) {
    const row = document.getElementById('details-' + index);
    const icon = document.getElementById('icon-' + index);
    const isOpen = row.style.display === 'table-row';
    row.style.display = isOpen ? 'none' : 'table-row';
    icon.classList.toggle('la-plus-circle', isOpen);
    icon.classList.toggle('la-minus-circle', !isOpen);
}
</script>
@endsection
