<table width="100%" border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse; margin-top: 25px; font-family: Arial, sans-serif; border: 2px solid #000;">
    <thead>
        <tr>
            <th style="border: 2px solid #000; padding: 5px; text-align: center; width: 10%;">SN</th>
            <th style="border: 2px solid #000; padding: 5px; text-align: center; width: 10%;">PART NO</th>
            <th style="border: 2px solid #000; padding: 5px; text-align: center; width: 10%;">IMAGE</th>
            <th style="border: 2px solid #000; padding: 5px; text-align: center; width: 32%;">ITEM NAME</th>
            <th style="border: 2px solid #000; padding: 5px; text-align: center; width: 12%;">ITEM GROUP</th>
            <th style="border: 2px solid #000; padding: 5px; text-align: center; width: 12%;">CATEGORY</th>
            <th style="border: 2px solid #000; padding: 5px; text-align: center; width: 12%;">NET PRICE</th>
        </tr>
    </thead>
    <tbody>
        @php $serialNumber = $serialNumberStart ?? 1; @endphp
        @if (!empty($products) && count($products) > 0)
            @foreach($products as $key => $product)
                @php
                    $cashAndCarryItem = DB::table('products')->where('part_no', $product->part_no)->value('cash_and_carry_item');
                    $isNoCreditItem = ($cashAndCarryItem == 1 && Auth::check() && Auth::user()->credit_days > 0);
                    $isFastDispatch = DB::table('products_api')->where('part_no', $product->part_no)->where('closing_stock', '>', 0)->exists();

                @endphp
                <tr>
                    <td style="border: 2px solid #000; padding: 10px; text-align: center; font-size: 12px;">{{ $serialNumber++ }} </td>
                    <td style="border: 2px solid #000; padding: 10px; text-align: center; font-size: 12px;">
                        {{ $product->part_no }}
                        @if($isNoCreditItem)
                            <br/><span style="display: inline-block; margin-top: 5px; padding: 2px 5px; background-color: #dc3545; color: #fff; font-size: 10px; border-radius: 3px;">No Credit Item</span>
                        @endif
                    </td>
                    <td style="border: 2px solid #000; padding: 10px; text-align: center; font-size: 12px;">
                        <img src="{{ $product->photo_url }}" alt="Product Image" width="80" height="80" style="border-radius: 5px;">
                    </td>
                    <td style="border: 2px solid #000; padding: 10px; text-align: left; width: 32%; font-size: 12px;">
                        <a href="{{ route('product', ['slug' => $product->slug]) }}" target="_blank" rel="noopener noreferrer" style="text-decoration: none; color: inherit;">
                            {{ $product->name }}
                        </a>
                        @if($isFastDispatch)
                            <div style="margin-top: 5px;">
                                <img src="{{ public_path('uploads/fast_dispatch.jpg') }}" alt="Fast Delivery" style="width: 68px; height: 17px; border-radius: 3px;">
                            </div>
                        @endif
                    </td>
                    <td style="border: 2px solid #000; padding: 10px; text-align: center; font-size: 12px;">{{ $product->group_name }}</td>
                    <td style="border: 2px solid #000; padding: 10px; text-align: center; font-size: 12px;">{{ $product->category_name }}</td>
                    <td style="border: 2px solid #000; padding: 10px; text-align: center; font-size: 12px;">{{ $product->price }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="7" style="text-align: center; border: 2px solid #000;">No Products Found</td>
            </tr>
        @endif
    </tbody>
</table>
