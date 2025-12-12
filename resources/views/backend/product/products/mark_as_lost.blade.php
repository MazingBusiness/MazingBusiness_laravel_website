@extends('backend.layouts.app')

@section('content')
<div class="card p-4">
    <h4 class="text-danger mb-3">Search by Part No.</h4>

    <!-- <div class="mb-3">
        <input type="text" id="search_part_no" class="form-control" placeholder="Enter Part No">
        <button type="button" class="btn btn-primary mt-2" id="searchBtn">Search</button>
    </div> -->

    <div class="row mb-3">
        <div class="col-md-3">
            <label>Search By</label>
            <select id="search_type" class="form-control">
                <option value="part_no">Part No</option>
                <option value="name">Product Name</option>
            </select>
        </div>
        <div class="col-md-6">
            <label>Search Value</label>
            <input type="text" id="search_value" class="form-control" placeholder="Enter value">
        </div>
        <div class="col-md-3">
            <label>&nbsp;</label>
            <button type="button" class="btn btn-primary w-100 mt-1" id="searchBtn">Search</button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

    <div id="responseMsg" class="mb-2"></div>

    <form action="{{ route('mark_as_lost.store') }}" method="POST">
        @csrf
        <div id="resultBox" style="display: none;">
            <div class="border p-3 mb-2">
                <strong>Item Name:</strong> <span id="item_name_label"></span><br>
                <strong>Total Stock:</strong> <span id="total_stock_label"></span>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label>Total Stock - Kolkata</label>
                    <input type="text" id="stock_kol" class="form-control" readonly>
                </div>
                <div class="col">
                    <label>Total Stock - Mumbai</label>
                    <input type="text" id="stock_mum" class="form-control" readonly>
                </div>
                <div class="col">
                    <label>Total Stock - Delhi</label>
                    <input type="text" id="stock_del" class="form-control" readonly>
                </div>
            </div>

            <div class="row mb-2">
                <div class="col">
                    <label>Lost Qty (Kolkata)</label>
                    <input type="number" name="lost_stock[1]" class="form-control" min="0">
                </div>
                <div class="col">
                    <label>Lost Qty (Mumbai)</label>
                    <input type="number" name="lost_stock[6]" class="form-control" min="0">
                </div>
                <div class="col">
                    <label>Lost Qty (Delhi)</label>
                    <input type="number" name="lost_stock[2]" class="form-control" min="0">
                </div>
            </div>

            <div class="row mb-2">
                <div class="col">
                    <label>Reason</label>
                    <select name="reason" class="form-control" required>
                        <option value="">Select Reason</option>
                        <option value="Stock on fire">Stock on fire</option>
                        <option value="Stolen goods">Stolen goods</option>
                        <option value="Damaged goods">Damaged goods</option>
                        <option value="Stock Written off">Stock Written off</option>
                        <option value="Stocktaking results">Stocktaking results</option>
                        <option value="Inventory Revaluation">Inventory Revaluation</option>
                        <option value="Stock Damage">Stock Damage</option>
                         <option value="Test Reson">Test Reson</option>
                    </select>
                </div>
            </div>

            <input type="hidden" name="part_no" id="part_no_hidden">
            <input type="hidden" name="product_id" id="product_id_hidden">
            <input type="hidden" name="item_name" id="item_name_hidden">

            <button type="submit" class="btn btn-success mt-3 float-end">Save</button>
        </div>
    </form>
</div>
@endsection

@section('script')
<script>
$(document).ready(function () {
    $('#searchBtn').on('click', function () {
    let searchType = $('#search_type').val();     // part_no or name
    let searchValue = $('#search_value').val();

    if (!searchValue) {
        alert("Please enter a value.");
        return;
    }

    $.ajax({
        url: '{{ route("mark_as_lost.fetch_stock") }}',
        method: 'GET',
        data: {
            type: searchType,
            value: searchValue
        },
        success: function (data) {
            if (data.status === 'success') {
                $('#resultBox').show();
                $('#item_name_label').text(data.item_name);
                $('#part_no_hidden').val(data.part_no);
                $('#product_id_hidden').val(data.product_id || '');
                $('#item_name_hidden').val(data.item_name);

                $('#stock_kol').val(data.stock.kolkata ?? 0);
                $('#stock_mum').val(data.stock.mumbai ?? 0);
                $('#stock_del').val(data.stock.delhi ?? 0);

                let total = 0;
                for (const key in data.stock) {
                    total += parseInt(data.stock[key]) || 0;
                }
                $('#total_stock_label').text(total);
            } else {
                $('#resultBox').hide();
                $('#responseMsg').html(`<div class="alert alert-danger">${data.message}</div>`);
            }
        },
        error: function () {
            $('#responseMsg').html(`<div class="alert alert-danger">Something went wrong.</div>`);
        }
    });
});

});
</script>
@endsection
