@extends('backend.layouts.app')

@section('content')
<div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{ translate('Combined Product List') }}</h5>
</div>

<div class="card">
    <div class="card-header">
        <h6>{{ translate('All Offer Combinations') }}</h6>
    </div>
    <div class="card-body">
        <!-- Make table responsive on mobile -->
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="thead-light">
                    <tr>
                        <th>{{ translate('Offer ID') }}</th>
                        <th>{{ translate('Free Product (Complementary Items)') }}</th>
                        <th>{{ translate('Free Product Qty') }}</th>
                        <th>{{ translate('Products in Combination') }}</th>
                        <th>{{ translate('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($offerCombinations as $combinationId => $products)
                        <tr>
                            <!-- Display offer combination details -->
                            <td>{{ $products->first()->offer_id }}</td>
                            <td>
                                <div class="d-inline-block">
                                    <strong>{{ $products->first()->free_product_name }}</strong><br>
                                    <small>{{ $products->first()->free_product_part_no }}</small>
                                </div>
                            </td>
                            <td>{{ $products->first()->free_product_qty }}</td>
                            
                            <!-- Display associated products with part numbers and quantities -->
                            <td>
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>{{ translate('Product Name') }}</th>
                                                <th>{{ translate('Product Part No') }}</th>
                                                <th>{{ translate('Required Quantity') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($products as $product)
                                                <tr>
                                                    <td>{{ $product->product_name }}</td>
                                                    <td>{{ $product->product_part_no }}</td>
                                                    <td>{{ $product->required_qty }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                            <!-- Delete button -->
                            <td>
                                <form action="{{ route('offer_combination_products.delete', $combinationId) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('{{ translate('Are you sure you want to delete this combination?') }}');">
                                        {{ translate('Delete') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">{{ translate('No offer combinations found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
