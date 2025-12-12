@php
    $total = 0;
    $cash_and_carry_item_flag = 0;
    $cash_and_carry_item_subtotal = 0;
    $normal_item_flag = 0;
    $normal_item_subtotal = 0;
    $applied_offer_id = 0;
    $offer_rewards = 0;
    $app_offer_name = "";
    $cart_items = \App\Models\Cart::where('user_id', Auth::user()->id)->orWhere('customer_id',Auth::user()->id)->get();
    $cartsArr = $cart_items->toArray();
    foreach ($cartsArr as $row) {
        $line = (float)$row['price'] * (int)$row['quantity'];
        if ((int)($row['cash_and_carry_item'] ?? 0) === 1 && Auth::check() && (int)Auth::user()->credit_days > 0) {
            $cash_and_carry_item_flag = 1;
            $cash_and_carry_item_subtotal += $line;
        } else {
            $normal_item_flag = 1;
            $normal_item_subtotal += $line;
            $total += $line;
        }
        if (!empty($row['applied_offer_id'])) {
            $applied_offer_id = $row['applied_offer_id'];
            $offer = \App\Models\Offer::with('offerProducts')->where('status', 1)->where('id',$row['applied_offer_id'])->first();
            $app_offer_name = $offer->offer_name;
        }
        if (empty($offer_rewards)) {
            $offer_rewards = (float)($row['offer_rewards'] ?? 0);
        }
    }
    // -------------------------------- Conveince Fee ------------------------------
    $conveince_fee = 0;
    // if($normal_item_subtotal <= 10000){
    //     $conveince_fee = ($normal_item_subtotal * 10)/100;
    // }elseif($normal_item_subtotal >= 10000 AND $normal_item_subtotal <= 20000){
    //      $conveince_fee = ($normal_item_subtotal * 7)/100;
    // }elseif($normal_item_subtotal >= 20000 AND $normal_item_subtotal <= 30000){
    //      $conveince_fee = ($normal_item_subtotal * 5)/100;
    // }

    if($normal_item_subtotal >= 20000 AND $normal_item_subtotal <= 30000){
         $conveince_fee = ($normal_item_subtotal * 5)/100;
    }

    $total += $conveince_fee;   

    $credit_limit = Auth::user()->credit_limit; // 600000
    $current_limit = $dueAmount - $overdueAmount; //181486
    $currentAvailableCreditLimit = $credit_limit - $current_limit;
    
    //-------------------------- This is for case 2 ------------------------------
    if($current_limit == 0){        
        if($total > $currentAvailableCreditLimit){
            $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
        }else{
            $exceededAmount = $overdueAmount;
        }

    }else{
        if($total > $currentAvailableCreditLimit)
        {
            $exceededAmount = ($total - $currentAvailableCreditLimit) + $overdueAmount;
        }else{
            $exceededAmount = $overdueAmount;
        }
    }
    //----------------------------------------------------------------------------
    $payableAmount = $exceededAmount + $cash_and_carry_item_subtotal;

    
@endphp
<div id="pagetop" class="card rounded border-0 shadow-sm"></div>
<div id="shifttotop">
<div class="card-header">
    <h3 class="fs-16 fw-600 mb-0">{{ translate('Payable Amount') }}</h3>
</div>
@if($offer_rewards != 0)
    <div class="px-3 py-2 border-top d-flex justify-content-between">
        <span class="opacity-60 fs-15">You will get {{ format_price_in_rs($offer_rewards) }} rewards when you place the order</span>
    </div>
@endif
@if (session('statusErrorMsg'))
    <div class="alert alert-danger">
        {{ session('statusErrorMsg') }}
    </div>
@endif
@if (session('statusSuccessMsg'))
    <div class="alert alert-success">
        {{ session('statusSuccessMsg') }}
    </div>
@endif
<div class="card-body text-center">
    {{-- <span class="display-4 text-primary font-weight-bold pay_total">{{ single_price($total + $overdueAmount) }}</span> --}}
    <span class="display-4 text-primary font-weight-bold pay_total">{{ format_price_in_rs($payableAmount) }}</span>
</div>
@if($cash_and_carry_item_flag ==1)
    <div class="px-3 py-2 border-top d-flex justify-content-between">
        <span class="opacity-60 fs-15">{{ translate('No Credit Item Subtotal') }} : </span>
        <a href="javascript:void(0)"  onclick="saveAllNoCreditItemForLater(event)"
            class="btn btn-icon btn-sm btn-soft-danger btn-circle" title="Remove No Credit Item." style="margin-right: 28%;">
            <i class="las la-times"></i>
        </a>
        <span class="fw-600 fs-17">{{ format_price_in_rs($cash_and_carry_item_subtotal) }}</span>
    </div>
@endif
@if($normal_item_flag ==1)
    <div class="px-3 py-2 border-top d-flex justify-content-between">
        <span class="opacity-60 fs-15">{{ translate('Other Item Subtotal') }} : </span>
        <span class="fw-600 fs-17">{{ format_price_in_rs($normal_item_subtotal) }}</span>
    </div>
@endif
@if($conveince_fee > 0)
    <div class="px-3 py-2 border-top d-flex justify-content-between">
        <span class="opacity-60 fs-15">{{ translate('Packing and forwarding') }} : </span>
        <span class="fw-600 fs-17">{{ format_price_in_rs($conveince_fee) }}</span>
    </div>
    <div class="px-3 py-2 border-top d-flex justify-content-between">
        <span class="opacity-60 fs-15">{{ translate('Cart Subtotal') }} : </span>
        <span class="fw-600 fs-17">{{ format_price_in_rs($conveince_fee + $normal_item_subtotal) }}</span>
    </div>
@endif
@if($overdueAmount > 0)
    <div class="px-3 py-2 border-top d-flex justify-content-between">
    <span class="opacity-60 fs-15">{{ translate('Overdue Amount') }} : </span>
    <a href="javascript:void(0)" title="Check Statement" style="margin-right: 45%;" class="my_pdf"  data-user-id="{{Auth::user()->id}}">
        <i class="las la-file-pdf" style="font-size: 28px;"></i>
    </a>
    <!-- <a href="#" class="btn btn-primary btn-sm my_pdf" data-party-code="OPEL0100087" data-party-name="The Mazing Retail PVT Limited" data-user-id="24185" style="padding: 6px 8px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
        <i class="fas fa-file-pdf" style="font-size: 16px;"></i>
    </a> -->
    <span class="fw-600 fs-17">{{ format_price_in_rs($overdueAmount) }}</span>
    </div>
@endif
@if($exceededAmount > 0)
    @if($dueAmount - $overdueAmount != 0)
        <div class="px-3 py-2 border-top d-flex justify-content-between">
            <span class="opacity-60 fs-15">{{ translate('Credit limit Exceeded Amount') }} : </span>
            <span class="fw-600 fs-17">{{ format_price_in_rs($exceededAmount) }}</span>
        </div>
    @endif
@endif
<!-- <button type="button" style="width:100%" onclick="submitOrder(this)" class="btn btn-primary fw-600">{{ translate('Pay Now') }}</button> 
{{-- <button type="button" style="width:100%" class="btn btn-primary fw-600">Pay Now {{ single_price($total + $overdueAmount) }}</button> --}}
{{-- <button type="button" style="width:100%" class="btn btn-primary fw-600" onclick="showCheckoutModal()">Pay Now {{ single_price($payableAmount) }}</button> --}}-->

@if(isset($validOffers) AND count($validOffers) > 0)
    @php
        $count = 0;
    @endphp
    <!-- @foreach($validOffers as $voKey=>$voValue)
        @if($applied_offer_id == 0 OR $applied_offer_id != $voValue->id)
            <a href="{{ route('cart.applyOffer',['offer_id'=> encrypt($voValue->id)]) }}" style="width:100%"  class="btn {{ $count % 2 == 0 ? 'btn-success' : 'btn-warning' }}  fw-600">Apply {{ $voValue->offer_name }} Offer</a>
        @else
            <a href="{{ route('cart.removeOffer',['offer_id'=> encrypt($voValue->id)]) }}" style="width:100%"  class="btn btn-danger fw-600">Remove {{ $voValue->offer_name }} Offer</a>
        @endif
        @php
            $count ++;
        @endphp
    @endforeach -->
    @if($applied_offer_id == 0)
        <a href="javascript.void(0)" style="width:100%"  class="btn btn-success fw-600" data-toggle="modal" data-target="#allOfferModal">Apply Offer</a>
    @else
        <a href="{{ route('cart.removeOffer',['offer_id'=> encrypt($applied_offer_id)]) }}" style="width:100%"  class="btn btn-danger fw-600">Remove Offer {{ $app_offer_name }}</a>
    @endif
@endif

<a href="{{ route('checkout.shipping_info') }}" style="width:100%"  class="btn btn-primary fw-600">Pay Now {{ format_price_in_rs($payableAmount) }}</a>
@if(isset($achiveOfferArray) AND count($achiveOfferArray) > 0)
    @foreach($achiveOfferArray as $aoKey=>$aoValue)
        @if($aoKey % 2 == 0)
            <div class="alert alert-primary" role="alert">
                {!! $aoValue !!}
            </div>
        @else
            <div class="alert alert-success" role="alert">
                {!! $aoValue !!}
            </div>
        @endif
    @endforeach
@endif
{{-- @if(session()->has('staff_id'))
    <a href="javascript:void(0);" id="get-quotations-btn" style="width:100%; margin-top:6px; color:white;" 
    class="btn btn-success fw-600">{{ translate('GET QUOTATIONS') }}</a>
    @if (session('status'))
        <span style="margin-top:5px;color:green;";>{{ session('status') }}</span>
    @endif
@endif --}}
</div>
<script>
    document.getElementById('pagetop').appendChild(document.getElementById('shifttotop'));    
</script>
    
    