
<div class="row mt-4" id="product-listing">
    @if($products->isEmpty())
        <div class="col-12 text-center">
            <p class="text-muted">No offers yet</p>
        </div>
    @else
        @foreach($products as $product)
            <div class="col-md-2 mb-4">
                <div class="card h-100 shadow-sm border-0">
                    <img src="{{ uploaded_asset($product->photos) }}" class="mx-auto h-140px h-md-188px mt-2 lazyloaded" alt="Product Image">
                    <div class="card-body text-center">
                        <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0 h-35px">
                            <a href="{{ route('product', ['slug' => $product->slug]) }}" class="d-block text-reset">{{ $product->name }}</a>
                        </h3>

                        <p class="card-text">
                            @if(isset($product->offer_price) && $product->offer_price > 0)
                                <del class="text-muted">₹{{ $product->mrp }}</del>
                                <span class="text-primary font-weight-bold">₹{{ $product->offer_price }}</span>
                            @elseif(isset($product->offer_discount_percent) && $product->offer_discount_percent > 0)
                                @php
                                    $userDiscountPercent = 20; // Static user discount
                                    $mrp = $product->mrp;

                                    // Calculate price after user discount
                                    $priceAfterUserDiscount = $mrp - ($mrp * $userDiscountPercent / 100);

                                    // Calculate additional discount from offer
                                    $finalPrice = $priceAfterUserDiscount - ($priceAfterUserDiscount * $product->offer_discount_percent / 100);
                                @endphp
                                <del class="text-muted">₹{{ $mrp }}</del>
                                <span class="text-primary font-weight-bold">₹{{ number_format($finalPrice, 2) }}</span>
                            @else
                                <span class="text-primary font-weight-bold">₹{{ $product->mrp }}</span>
                            @endif
                        </p>

                        @if(isset($product->offer_discount_percent) && $product->offer_discount_percent > 0)
                            <p class="text-success">{{ $product->offer_discount_percent }}% Off</p>
                        @endif

                        @if(isset($product->rating))
                            <div class="mb-2">
                                {{ renderStarRating($product->rating) }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
