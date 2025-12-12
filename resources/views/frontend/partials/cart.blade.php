@php
// === Manager-41 flag (impersonation aware) =========================
$is41Manager = false;
$impersonated = session()->has('staff_id') ? \App\Models\User::find((int) session('staff_id')) : null;
$currentUser = $impersonated ?: Auth::user();

if ($currentUser) {
    $title = strtolower(trim((string) $currentUser->user_title));
    $type  = strtolower(trim((string) $currentUser->user_type));
    $is41Manager = ($type === 'manager_41') || in_array($title, ['manager_41'], true);   // <<< 41
}

// === Fetch carts (filter by is_manager_41) =========================
$cart = collect(); 
$saveForLatercart = collect();

if (Auth::check()) {
    $user_id = Auth::id();

    // Base where-clause (user_id OR customer_id)
    $baseUserFilter = function($q) use ($user_id) {
        $q->where('user_id', $user_id)->orWhere('customer_id', $user_id);
    };

    // Cart
    $cart = \App\Models\Cart::where($baseUserFilter)
        ->when($is41Manager, function($q) {
            // Acting as Manager-41 ⇒ show ONLY 41 items
            $q->where('is_manager_41', 1);                                         // <<< 41
        }, function($q) {
            // Normal user ⇒ hide Manager-41 rows
            $q->where(function($qq){
                $qq->whereNull('is_manager_41')->orWhere('is_manager_41', 0);      // <<< 41
            });
        })
        ->get();

    // Save For Later (same filter)
    $saveForLatercart = \App\Models\CartSaveForLater::where($baseUserFilter)
        ->when($is41Manager, function($q) {
            $q->where('is_manager_41', 1);                                         // <<< 41
        }, function($q) {
            $q->where(function($qq){
                $qq->whereNull('is_manager_41')->orWhere('is_manager_41', 0);      // <<< 41
            });
        })
        ->get();

} else {
    // Guest flow (no Manager-41 for guests)
    $temp_user_id = session()->get('temp_user_id');
    if ($temp_user_id) {
        $cart = \App\Models\Cart::where('temp_user_id', $temp_user_id)->get();
    }
}
@endphp

<a href="javascript:void(0)" class="d-flex align-items-center text-reset h-100"  >
  <i class="la la-shopping-cart la-2x text-white"></i>
  <span class="flex-grow-1 ml-1">
    @if (isset($cart) && count($cart) > 0)
      <span class="badge badge-primary badge-inline badge-pill text-dark mz-highlight cart-count">
        {{ count($cart) }}
      </span>
    @else
      <span  class=" badge badge-primary badge-inline badge-pill text-dark mz-highlight cart-count">0</span>
    @endif
    <span class="nav-box-text d-none d-xl-block text-white">{{ translate('Cart') }}</span>
  </span>
</a>



<div class="dropdown-menu dropdown-menu-right dropdown-menu-lg p-0 stop-propagation" id="list_menu" >

  @if (isset($cart) && count($cart) > 0)
    <div class="p-3 fs-15 fw-600 p-3 border-bottom">
      {{ translate('Cart Items') }}
    </div>
    <ul class="h-250px overflow-auto c-scrollbar-light list-group list-group-flush">
      @php
        $total = 0;
      @endphp
      @foreach ($cart as $key => $cartItem)
        @php
          $product = \App\Models\Product::find($cartItem['product_id']);
          if ($cartItem['is_carton']) {
            $ppc = $product->stocks->first()->piece_per_carton;
          }else{
            $ppc = 1;
          }
          $product_stock = $product->stocks->where('variant', $cartItem['variation'])->first();
          $total = $total + ($cartItem['price'] * $cartItem['quantity'] * $ppc);
        @endphp
        @if ($product != null)
         @php
            // Fetch the base URL for uploads from the .env file
            $uploads_base_url = env('UPLOADS_BASE_URL', url('public'));

            // Fetch file_name for the product thumbnail image (assuming $product->thumbnail_img contains the ID of the upload)
            $product_thumbnail = \App\Models\Upload::where('id', $product->thumbnail_img)->value('file_name');
            $product_thumbnail_path = $product_thumbnail
                        ? $uploads_base_url . '/' . $product_thumbnail
                        : url('public/assets/img/placeholder.jpg');
          @endphp
          <li class="list-group-item">
            <span class="d-flex align-items-center">
              <a href="{{ route('product', $product->slug) }}" class="text-reset d-flex align-items-center flex-grow-1" >
              <img src="{{ url('public/assets/img/placeholder.jpg') }}"
                data-src="{{ $product_thumbnail_path }}" 
                class="img-fit lazyload size-60px rounded"
                alt="{{ $product->getTranslation('name') }}">
                <!-- <img src="{{ static_asset('assets/img/placeholder.jpg') }}"
                  data-src="{{ uploaded_asset($product->thumbnail_img) }}" class="img-fit lazyload size-60px rounded"
                  alt="{{ $product->getTranslation('name') }}"> -->
                <span class="minw-0 pl-2 flex-grow-1">
                  <span class="fw-600 mb-1 text-truncate-2">
                    {{ $product->getTranslation('names')}}
                  </span>
                  <span  class="">{{ $cartItem['quantity'] }}
                    {{ $cartItem['is_carton'] ? Str::plural('Carton', $cartItem['quantity']) : Str::plural('Piece', $cartItem['quantity']) }}
                    x</span>
                  <span class="">{{ format_price_in_rs($cartItem['price'] * $ppc) }}</span>                  
                    {!! ($cartItem['cash_and_carry_item'] == 1 && Auth::check() && Auth::user()->credit_days > 0) ? '<span class="badge badge-inline badge-danger">No Credit Item</span>' : '' !!}                  
                </span>
              </a>
              @if(Auth::user()->id == '24185')
                <div>
                    {!! $cartItem['offer'] != "" 
                        ? $cartItem['applied_offer_id'] == "" ? '<span class="badge badge-inline badge-success view-offer" style="cursor: pointer;" data-toggle="modal" data-target="#offerModal" data-product-id="' . $product->id . '">View Offer</span>' : '<span class="badge badge-inline badge-primary view-offer" style="cursor: pointer;" data-toggle="modal" data-target="#offerModal" data-product-id="' . $product->id . '">Offer Applied</span>'
                        : '' 
                    !!}
                </div>
              @endif
              <span class="">
                <button  onclick="removeFromCart({{ $cartItem['id'] }})" class="btn btn-sm btn-icon stop-propagation" id="dell">
                  <i class="la la-close"></i>
                </button>
              </span>
            </span>
          </li>
        @endif
      @endforeach
    </ul>
    <div class="px-3 py-2 fs-15 border-top d-flex justify-content-between">
      <span class="opacity-60">{{ translate('Subtotal') }}</span>
      <span class="fw-600">{{ format_price_in_rs($total) }}</span>
    </div>
    <div class="px-3 py-2 text-center border-top">
      <ul class="list-inline mb-0">
        <li class="list-inline-item">
          <a href="{{ route('cart') }}" class="btn btn-soft-primary btn-sm">
            {{ translate('View cart') }}
          </a>
        </li>
        @if (Auth::check())
          <li class="list-inline-item">
            <a href="{{ route('checkout.shipping_info') }}" class="btn btn-primary btn-sm">
              {{ translate('Checkout') }}
            </a>
          </li>
        @endif
      </ul>
    </div>
  @else
    <div class="text-center p-3">
      <i class="las la-frown la-3x opacity-60 mb-3"></i>
      <h3 class="h6 fw-700">{{ translate('Your Cart is empty') }}</h3>
    </div>
    @if (isset($saveForLatercart) && count($saveForLatercart) > 0)
      <div class="px-3 py-2 text-center border-top">
        <ul class="list-inline mb-0">
          <li class="list-inline-item">
            <a href="{{ route('cart') }}" class="btn btn-soft-primary btn-sm">
              {{ translate('View Save For Later') }}
            </a>
          </li>
        </ul>
      </div>
    @endif
  @endif

</div>


{{-- <script>
$(document).ready(function() {
    $('body').on('click', '#cart_items', function() {
        alert("test");
        $(this).toggleClass('show');
        $('#cart_items div').toggleClass('show');
    });
});
</script> --}}