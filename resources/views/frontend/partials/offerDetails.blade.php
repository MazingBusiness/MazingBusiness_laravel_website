@foreach($offers as $offer)
    <strong>Offer Name:</strong> {{ $offer->offer_name }} <br>
    <strong>Validity:</strong> {{ date('d-m-Y', strtotime($offer->offer_validity_start)) }} to {{ date('d-m-Y', strtotime($offer->offer_validity_end)) }}
    <div class="mx-auto">
        <div class="shadow-sm bg-white p-3 p-lg-4 rounded text-left">
            <div class="mb-4">
                <div class="row gutters-5 d-none d-lg-flex border-bottom mb-3 pb-3">
                    <div class="col-md-1 fw-600 text-center">{{ translate('Select') }}</div>
                    <div class="col-md-3 fw-600 text-center">{{ translate('Product') }}</div>
                    <div class="col-md-2 fw-600 text-center">{{ translate('Min. Quantity') }}</div>
                    <div class="col-md-3 fw-600 text-center">{{ translate('Offer Price') }}</div>
                    <div class="col-md-3 fw-600 text-center">{{ translate('Action') }}</div>
                </div>
                <ul class="list-group list-group-flush">
                    @foreach ($offer->offerProducts as $oKey => $oValue)
                        @php
                        if($oValue->discount_type == 'percent'){                           
                            $discountedPrice = ($oValue->mrp * ((100 - Auth::user()->discount) / 100))*((100 - $oValue->offer_discount_percent) / 100);
                        }else{
                            $discountedPrice = $oValue->offer_price;
                        }                           
                        $discountedPrice = $discountedPrice == "" ? 0 : $discountedPrice;                     
                        @endphp
                        <li class="list-group-item px-0 px-lg-3">
                            <div class="row gutters-5">
                                <div class="col-lg-1" style="text-align:center;">
                                    <input type="checkbox" class="select-product" value="{{ $oValue->product_id }}">
                                </div>
                                <div class="col-lg-3">
                                    <span class="mr-2 ml-0">
                                        <!-- Image Placeholder -->
                                    </span>
                                    <div>
                                        <span class="fs-14 opacity-60">{{ $oValue->name }}</span>
                                    </div>
                                </div>
                                <div class="col-lg-2" style="text-align:center;">
                                    {{ $oValue->min_qty }}
                                </div>
                                <div class="col-lg-3" style="text-align:center;">
                                    {{ single_price($discountedPrice) }}
                                </div>
                                <div class="col-lg-3" style="text-align:center;">
                                    <button type="button" class="btn btn-success fw-600" 
                                        onclick="addOfferProductToCart('{{ $oValue->product_id }}')">
                                        {{ translate('Add To Cart') }}
                                    </button>
                                </div>                                
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
            <hr/>
            <button type="button" class="btn btn-warning fw-600 mt-3" onclick="addSelectedItemsToCart({{ $offer->id }})">
                {{ translate('Add Selected Items') }}
            </button>
            <button type="button" class="btn btn-success fw-600 mt-3" onclick="addAllOfferProductToCart('{{ $offer->offerProducts->pluck('product_id')->join(',') }}',{{ $offer->id }},'1')">
                {{ translate('Apply offer') }}
            </button>
        </div>
    </div>
    <form id="addOfferProductToCartFrm_{{ $offer->id }}" name="addOfferProductToCartFrm_{{ $offer->id }}" action="{{ route('cart.addOfferProductToCart') }}" method="post">
        @csrf
        <input type="hidden" name="product_id" id="product_id" value="" >
        <input type="hidden" name="offer_id" id="offer_id" value="{{ $offer->id }}" >
        <input type="hidden" name="addAllItem" id="addAllItem" value="0" >
    </form>
@endforeach
