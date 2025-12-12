@extends('frontend.layouts.app')

@section('content')
<style type="text/css">
/* Custom carousel arrow styling */
.custom-carousel-control {
    width: 30px;
    height: 30px;
    background-color: rgba(0, 0, 0, 0.3);
    border-radius: 50%;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.3s ease;
}

.custom-carousel-control:hover {
    background-color: rgba(0, 0, 0, 0.5);
}

.custom-carousel-control .carousel-control-prev-icon,
.custom-carousel-control .carousel-control-next-icon {
    background-image: none;
    border: solid white;
    border-width: 0 2px 2px 0;
    display: inline-block;
    padding: 4px;
}

.custom-carousel-control .carousel-control-prev-icon {
    transform: rotate(135deg);
    margin-left: 4px;
    width:10px;
    height:10px;
}

.custom-carousel-control .carousel-control-next-icon {
    transform: rotate(-45deg);
    margin-right: 4px;
    width:10px;
    height:10px;
}

.sticky-category-carousel {
    position: sticky;
    top: 0;
    z-index: 1020;
    background-color: white;
    padding: 10px 0;
    border-bottom: 1px solid #e0e0e0;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
    border-radius:7px;
}
.sticky-category-carousel .nav-link {
    font-weight: bold;
    color: #333;
    padding: 5px 15px;
    transition: color 0.3s, border-bottom 0.3s;
}
.sticky-category-carousel .nav-link.active {
    color: #007bff;
    border-bottom: 2px solid #007bff;
}
.sticky-category-carousel .nav-link:hover {
    color: #0056b3;
}

.card {
    border: 1px solid #e0e0e0;
    transition: transform 0.3s, box-shadow 0.3s;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.1);
}

.combo-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: none;
    border-radius: 10px;
}

.combo-card:hover {
    transform: translateY(-5px);
    box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.1);
}

.combo-card .card-body {
    background-color: #f8f9fa;
    border-radius: 10px;
}

.combo-card .img-thumbnail {
    border-radius: 50%;
    width: 80px;
    height: 80px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.combo-card .font-weight-bold {
    color: #007bff;
}
</style>

<div class="container-fluid">
    <!-- Banner Section with Carousel and Timer Overlay -->
    <div class="row mt-4 position-relative">
        <div class="col-12">
            <div class="position-absolute p-2 rounded shadow-sm bg-white text-dark d-flex flex-column align-items-center"
                 style="top: 7px; right: 25px; z-index: 10; opacity: 0.9; font-size: 12px;">
                <span class="text-primary font-weight-bold">Limited Time Offer</span>
                <div id="offer-timer" class="d-flex mt-1">
                    <div class="text-center text-dark mx-1">
                        <div id="days" class="font-weight-bold">00</div>
                        <small>Days</small>
                    </div>
                    <div class="text-center text-dark mx-1">
                        <div id="hours" class="font-weight-bold">00</div>
                        <small>Hrs</small>
                    </div>
                    <div class="text-center text-dark mx-1">
                        <div id="minutes" class="font-weight-bold">00</div>
                        <small>Min</small>
                    </div>
                    <div class="text-center text-dark mx-1">
                        <div id="seconds" class="font-weight-bold">00</div>
                        <small>Sec</small>
                    </div>
                </div>
            </div>

            <div id="bannerCarousel" class="carousel slide rounded shadow-sm overflow-hidden" data-ride="carousel" data-interval="3000">
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <img src="https://rukminim2.flixcart.com/fk-p-flap/1600/270/image/b8134f12060c7b7e.jpg?q=20" class="img-fluid w-100" alt="Offer Banner 1">
                    </div>
                    <div class="carousel-item">
                        <img src="https://rukminim2.flixcart.com/fk-p-flap/1600/270/image/8acfb721c7bef89a.jpg?q=20" class="img-fluid w-100" alt="Offer Banner 2">
                    </div>
                </div>
                <a class="carousel-control-prev custom-carousel-control" href="#bannerCarousel" role="button" data-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="sr-only">Previous</span>
                </a>
                <a class="carousel-control-next custom-carousel-control" href="#bannerCarousel" role="button" data-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="sr-only">Next</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Sticky Category Navigation Carousel with Chunked Categories -->
    <div class="sticky-category-carousel mt-4">
        <div id="categoryCarousel" class="carousel slide" data-ride="carousel" data-interval="false">
            <div class="carousel-inner">
                @foreach($categories->chunk(6) as $index => $chunk)
                    <div class="carousel-item {{ $index == 0 ? 'active' : '' }}">
                        <ul class="nav nav-pills justify-content-center flex-nowrap">
                            @foreach($chunk as $category)
                                <li class="nav-item mx-2">
                                    <a class="nav-link font-weight-bold text-dark" data-category-id="{{ $category->id }}" href="#">{{ $category->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <a class="carousel-control-prev custom-carousel-control" href="#categoryCarousel" role="button" data-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="sr-only">Previous</span>
            </a>
            <a class="carousel-control-next custom-carousel-control" href="#categoryCarousel" role="button" data-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="sr-only">Next</span>
            </a>
        </div>
    </div>

    <!-- Combo Offers Section as Slider -->
    <div class="mt-5">
        <h4 class="text-primary font-weight-bold mb-3">Combo Offers</h4>

        @if($comboOffers->isEmpty())
            <div class="text-center text-muted">No combo offers available yet</div>
        @else
            <div id="comboOfferCarousel" class="carousel slide" data-ride="carousel">
                <div class="carousel-inner">
                    @foreach($comboOffers->chunk(3) as $index => $chunk)
                        <div class="carousel-item {{ $index == 0 ? 'active' : '' }}">
                            <div class="row">
                                @foreach($chunk as $comboId => $products)
                                    <div class="col-md-4 mb-4">
                                        <div class="card h-100 shadow combo-card">
                                            <div class="card-body p-4">
                                                <div class="text-center mb-3">
                                                    <img src="{{ uploaded_asset($products->first()->free_product_photo) }}" 
                                                         alt="{{ $products->first()->free_product_name }}" 
                                                         class="img-thumbnail mb-2">
                                                    <h6 class="font-weight-bold text-primary mb-0">
                                                        Free Product: {{ $products->first()->free_product_name }}
                                                    </h6>
                                                    <small class="text-muted">({{ $products->first()->free_product_part_no }})</small>
                                                    <p class="text-secondary mt-1">Quantity: {{ $products->first()->free_product_qty }}</p>
                                                </div>

                                                <hr>

                                                <h6 class="text-muted mb-3">Combo Products:</h6>
                                                <ul class="list-unstyled">
                                                    @foreach($products as $product)
                                                        <li class="d-flex align-items-start mb-3">
                                                            <img src="{{ uploaded_asset($product->product_photo) }}" 
                                                                 alt="{{ $product->product_name }}" 
                                                                 class="rounded shadow-sm mr-3"
                                                                 style="width: 60px; height: 60px; object-fit: cover;">
                                                            <div>
                                                                <a href="{{ route('product', ['slug' => $product->product_slug]) }}" 
                                                                   class="text-reset font-weight-bold">{{ $product->product_name }}</a>
                                                                <p class="mb-1">Qty: {{ $product->required_qty }}</p>
                                                                <p class="text-primary font-weight-bold">₹{{ number_format($product->product_mrp, 2) }}</p>
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>

                                                <div class="text-center mt-3">
                                                    <a href="{{ route('offer.combination', ['id' => $comboId]) }}" 
                                                       class="btn btn-primary btn-block font-weight-bold">View Combo Offer</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Carousel Controls -->
                <a class="carousel-control-prev custom-carousel-control" href="#comboOfferCarousel" role="button" data-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="sr-only">Previous</span>
                </a>
                <a class="carousel-control-next custom-carousel-control" href="#comboOfferCarousel" role="button" data-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="sr-only">Next</span>
                </a>
            </div>
        @endif
    </div>

    <!-- Product Listing Section -->
    <div class="row mt-4" id="product-listing">
        @if($allProducts->isEmpty())
            <div class="col-12 text-center">
                <p class="text-muted">No offers available yet</p>
            </div>
        @else
            @foreach($allProducts as $product)
                <div class="col-md-2 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <img src="{{ uploaded_asset($product->photos) }}" class="mx-auto h-140px h-md-188px mt-2 lazyloaded" alt="Product Image">
                        <div class="card-body text-center">
                            <h3 class="fw-600 fs-13 text-truncate-2 lh-1-4 mb-0 h-35px">
                                <a href="{{ route('product', ['slug' => $product->slug]) }}" class="d-block text-reset">
                                    {{ $product->name }}
                                </a>
                            </h3>
                            <div class="my-2">
                                {{ renderStarRating($product->rating) }}
                            </div>
                            <p class="card-text">
                                @php
                                    $userDiscountPercent = 20;
                                    $mrp = $product->mrp;
                                    $finalPrice = $mrp - ($mrp * $userDiscountPercent / 100);

                                    if(isset($product->offer_discount_percent) && $product->offer_discount_percent > 0) {
                                        $finalPrice -= $finalPrice * $product->offer_discount_percent / 100;
                                    } elseif(isset($product->offer_price) && $product->offer_price > 0) {
                                        $finalPrice = $product->offer_price;
                                    }
                                @endphp

                                <del class="text-muted">₹{{ number_format($mrp, 2) }}</del>
                                <span class="text-primary font-weight-bold">₹{{ number_format($finalPrice, 2) }}</span>
                            </p>
                            @if(isset($product->offer_discount_percent) && $product->offer_discount_percent > 0)
                                <p class="text-success">{{ $product->offer_discount_percent }}% Off</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection

@section('script')
<script>
    function initializeTimer() {
        const countdownDate = new Date().getTime() + (5 * 24 * 60 * 60 * 1000);

        const interval = setInterval(function() {
            const now = new Date().getTime();
            const distance = countdownDate - now;

            if (distance < 0) {
                clearInterval(interval);
                document.getElementById("offer-timer").innerHTML = "EXPIRED";
            } else {
                document.getElementById("days").innerText = Math.floor(distance / (1000 * 60 * 60 * 24));
                document.getElementById("hours").innerText = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                document.getElementById("minutes").innerText = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                document.getElementById("seconds").innerText = Math.floor((distance % (1000 * 60)) / 1000);
            }
        }, 1000);
    }

    $(document).ready(function() {
        initializeTimer();

        $('.sticky-category-carousel .nav-link').on('click', function(e) {
            e.preventDefault();
            let categoryId = $(this).data('category-id');

            $('.sticky-category-carousel .nav-link').removeClass('active');
            $(this).addClass('active');

            $.ajax({
                url: "{{ route('offers.category_products') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    category_id: categoryId
                },
                success: function(data) {
                    $('#product-listing').html(data);
                }
            });
        });
    });
</script>
@endsection
