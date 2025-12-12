@if(isset($validOffers) AND count($validOffers) > 0)
  @php
      $count = 0;
  @endphp
  @foreach($validOffers as $voKey=>$voValue)
      @if($applied_offer_id == 0 OR $applied_offer_id != $voValue->id)
          <!-- <a href="{{ route('cart.applyOffer',['offer_id'=> encrypt($voValue->id)]) }}" style="width:100%"  class="btn {{ $count % 2 == 0 ? 'btn-success' : 'btn-warning' }}  fw-600">Apply {{ $voValue->offer_name }} Offer</a> -->
          <div class="coupon">
            <div class="ribbon ribbon--cashback--class"><small>
              @if($voValue->offer_type == 1)
                  Item Wise
              @elseif($voValue->offer_type == 2)
                  Total
              @elseif($voValue->offer_type == 3)
                  Complementary
              @else
                  -
              @endif
            </small></div>

            <div class="body">
              <div class="brand">Mazing</div>
              <div class="title">{{ $voValue->offer_name }}</div>
              <a class="apply" href="javascript:void(0)" onclick="addAllOfferProductToCart('{{ $voValue->offerProducts->pluck('product_id')->join(',') }}',{{ $voValue->id }},'1')" aria-label="Apply">APPLY</a>
              
              <div class="desc">Offer Description : {!! $voValue->offer_description !!}</div>
              <div class="divider"></div>

              <div class="meta">
                <span>Click More to get details</span>
                <button class="more" type="button" aria-expanded="false" aria-controls="d{{$voValue->id}}">+ MORE</button>
              </div>
              <!-- Expandable product list -->
              <div id="d{{$voValue->id}}" class="details" hidden>
                <div class="prod-list">
                  @foreach ($voValue->offerProducts as $oKey => $oValue)
                    @php
                      if($oValue->discount_type == 'percent'){                           
                          $discountedPrice = ($oValue->mrp * ((100 - Auth::user()->discount) / 100))*((100 - $oValue->offer_discount_percent) / 100);
                      }else{
                          $discountedPrice = $oValue->offer_price;
                      }                           
                      $discountedPrice = $discountedPrice == "" ? 0 : $discountedPrice;
                    @endphp
                    <div class="prod">
                      <div class="thumb">{{$oKey+1}}</div>
                      <div class="pinfo">
                        <div class="pname">{{ $oValue->name }}</div>
                        <div class="pmeta">Minimum Quantity: {{ $oValue->min_qty }}</div>
                        @if($oValue->achive_offer != "")
                          <div class="pmeta" style="color:#e22c19f0;">{!! $oValue->achive_offer !!}</div>
                        @endif
                      </div>
                      <div class="price">{{ single_price($discountedPrice) }}</div>
                    </div>
                  @endforeach
                </div>
              </div>
              <!-- /Expandable -->
            </div>
          </div>
      @else
          <div class="coupon">
            <div class="ribbon ribbon--cashback">
              <small>
                @if($voValue->offer_type == 1)
                    Item Wise
                @elseif($voValue->offer_type == 2)
                    Total
                @elseif($voValue->offer_type == 3)
                    Complementary
                @else
                    -
                @endif
              </small>
            </div>

            <div class="body">
              <div class="brand">Mazing</div>
              <div class="title">{{ $voValue->offer_name }}</div>
              <a class="apply" href="{{ route('cart.removeOffer',['offer_id'=> encrypt($voValue->id)]) }}">APPLIED</a>
              <div class="desc">Offer Description : {{ $voValue->offer_description }}</div>
              <div class="divider"></div>

              <div class="meta">
                <div>
                  <span>Applicable on Paytm wallet transaction above ₹99</span>
                  <button class="more" type="button" aria-expanded="false" aria-controls="d{{$voValue->id}}">+ MORE</button>
                </div>
                <a class="remove" href="{{ route('cart.removeOffer',['offer_id'=> encrypt($voValue->id)]) }}">
                  <i class="fa fa-trash"></i> REMOVE
                </a>
              </div>

              <!-- Expandable product list -->
              <div id="d{{$voValue->id}}" class="details" hidden>
                <div class="prod-list">
                  @foreach ($voValue->offerProducts as $oKey => $oValue)
                    @php
                      if($oValue->discount_type == 'percent'){                           
                          $discountedPrice = ($oValue->mrp * ((100 - Auth::user()->discount) / 100))*((100 - $oValue->offer_discount_percent) / 100);
                      }else{
                          $discountedPrice = $oValue->offer_price;
                      }                           
                      $discountedPrice = $discountedPrice == "" ? 0 : $discountedPrice;
                    @endphp
                    <div class="prod">
                      <div class="thumb">A</div>
                      <div class="pinfo">
                        <div class="pname">{{ $oValue->name }}</div>
                        <div class="pmeta">Minimum Quantity: {{ $oValue->min_qty }}</div>
                      </div>
                      <div class="price">{{ single_price($discountedPrice) }}</div>
                    </div>
                  @endforeach
                </div>
              </div>
              <!-- /Expandable -->
            </div>
          </div>
          <!-- <a href="{{ route('cart.removeOffer',['offer_id'=> encrypt($voValue->id)]) }}" style="width:100%"  class="btn btn-danger fw-600">Remove {{ $voValue->offer_name }} Offer</a> -->
      @endif
      @php
          $count ++;
      @endphp
      <form id="addOfferProductToCartFrm_{{ $voValue->id }}" name="addOfferProductToCartFrm_{{ $voValue->id }}" action="{{ route('cart.addOfferProductToCart') }}" method="post">
          @csrf
          <input type="hidden" name="product_id" id="product_id" value="" >
          <input type="hidden" name="offer_id" id="offer_id" value="{{ $voValue->id }}" >
          <input type="hidden" name="addAllItem" id="addAllItem" value="1" >
      </form>
  @endforeach
@else
  <div class="p-4 text-center text-muted">No offers found.</div>
@endif