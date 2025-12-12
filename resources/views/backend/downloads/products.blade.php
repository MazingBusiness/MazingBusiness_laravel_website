@php echo '<?xml version="1.0"?>' @endphp
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0">
  <title>Mazing Business</title>
  <link rel="self" href="{{ env('APP_URL') }}" />
  <updated>{{ \Carbon\Carbon::now()->toIso8601ZuluString() }}</updated>
  @foreach ($products as $product)
    @php
      $images = get_images_path($product->photos);
      $count = 0;
      $sub_category = $product->category;
      $main_category = $sub_category->load('parentCategory')->parentCategory;
      $category_group = $sub_category->load('categoryGroup')->categoryGroup;
    @endphp
    <entry>
      <g:id>{{ $product->id }}</g:id>
      <g:title>
        <![CDATA[{!! $product->name !!}]]>
      </g:title>
      <g:description>
        <![CDATA[{!! $product->description !!}]]>
      </g:description>
      <g:link>{{ env('APP_URL') . '/product/' . $product->slug }}</g:link>
      @foreach ($images as $image)
        @if ($count == 0)
          <g:image_link>{{ $image }}</g:image_link>
        @else
          <g:additional_image_link>{{ $image }}</g:additional_image_link>
        @endif
        @php $count++; @endphp
      @endforeach
      <g:condition>new</g:condition>
      @if ((count($product->stocks) && $product->stocks->sum('qty') + $product->stocks->sum('seller_stock')) || $product->current_stock)
        <g:availability>in_stock</g:availability>
      @else
        <g:availability>out_of_stock</g:availability>
      @endif
      @if (count($product->taxes) && $product->taxes[0]->tax)
        <g:price>{{ round($product->unit_price + ($product->unit_price * $product->taxes[0]->tax) / 100) }} INR</g:price>
      @else
        <g:price>{{ round($product->unit_price + $product->unit_price * 0.18) }} INR</g:price>
      @endif
      <g:brand>{{ isset($product->brand) ? $product->brand->name : 'Generic' }}</g:brand>
      <g:product_type>{{ $category_group ? $category_group->name . ' > ' : '' }}{{ $main_category ? $main_category->name . ' > ' : '' }}{{ $sub_category->name }}</g:product_type>
      <g:google_product_category>{{ isset($product->category) ? $product->category->google_category_id : 'Generic' }}</g:google_product_category>
    </entry>
  @endforeach
</feed>
