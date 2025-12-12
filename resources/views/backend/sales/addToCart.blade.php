<style>
   @keyframes blink { 50% { opacity: 0; } } .blink { animation: blink 1s
   steps(5, start) infinite; color: red; }
</style>
<div class="modal-body p-4 c-scrollbar-light">
   <div class="row pr-2">
      <div class="col-lg-6">
         <div class="row">
            @php 
               use App\Models\User;
               $photos = explode(',', $product->photos);
               $price = 0;
               $unitPrice = 0;
               $user = User::where('id',$user_id)->first();
               $price = $product->dollar_purchase_price;
               $userPhone = $user->phone;
               $firstTwoChars = substr($userPhone, 0, 3);
               if($firstTwoChars == "+91"){
                  if($user->profile_type == 'Bronze'){
                     $markup = $product->inr_bronze;
                  }elseif($user->profile_type == 'Silver'){
                     $markup = $product->inr_silver;
                  }elseif($user->profile_type == 'Gold'){
                     $markup = $product->inr_gold;
                  }
                  $unitPrice = round($price*$markup);
                  $price = '₹'.round($price*$markup);
               }else{
                  if($user->profile_type == 'Bronze'){
                     $markup = $product->doller_bronze;
                  }elseif($user->profile_type == 'Silver'){
                     $markup = $product->doller_silver;
                  }elseif($user->profile_type == 'Gold'){
                     $markup = $product->doller_gold;
                  }
                  $unitPrice = number_format(($price+($price*$markup)/100),2);
                  $price = '$'.number_format(($price+(($price*$markup)/100)),2);
               }
            @endphp
            <div class="col">
               <div class="aiz-carousel product-gallery" data-nav-for='.product-gallery-thumb'
               data-fade='true' data-auto-height='true'>
                  @foreach ($photos as $key => $photo)
                  <div class="carousel-box img-zoom rounded">
                     <img class="img-fluid lazyload" src="{{ static_asset('assets/img/placeholder.jpg') }}"
                     data-src="{{ uploaded_asset($photo) }}" onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';">
                  </div>
                  @endforeach
               </div>
            </div>
            <div class="col-12 mt-3 mt-md-0">
               <div class="aiz-carousel product-gallery-thumb" data-items='5' data-nav-for='.product-gallery'
               data-focus-select='true' data-arrows='true'>
                  @foreach ($photos as $key => $photo)
                    <div class="carousel-box c-pointer border p-1 rounded">
                      <img class="lazyload mw-100 size-50px h-auto mx-auto" src="{{ static_asset('assets/img/placeholder.jpg') }}"
                      data-src="{{ uploaded_asset($photo) }}" onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';">
                    </div>
                  @endforeach
               </div>
            </div>
         </div>
      </div>
      <div class="col-lg-6">
         <div class="text-left">
            <h2 class="mb-2 fs-20 fw-600">
               {{ $product->getTranslation('name') }}
            </h2>
            <form name="frmAddToCart" id="frmAddToCart">
               @csrf
               <input type="hidden" name="product_id" id="product_id" value="{{ $product->id }}">
               <input type="hidden" name="product_name" id="product_name" value="{{ $product->getTranslation('name') }}">
               <input type="hidden" name="group_id" id="group_id" value="{{ $product->group_id }}">
               <input type="hidden" name="category_id" id="category_id" value="{{ $product->category_id }}">
               <input type="hidden" name="unit_price" id="unit_price" value="{{ $unitPrice }}">
               <!-- + Add to cart -->
               <div class="bg-white mb-3 rounded" id="orderby">
                  @if (Auth::check())
                    <div class="row no-gutters my-3">
                        <div class="col-6 col-md-4">
                            <div class="opacity-50 my-2">
                              {{ translate('Price') }}:
                            </div>
                        </div>
                        <div class="col-6 col-md-8">
                            <div>
                              <strong class="h2 fw-600 text-primary">{{ $price }}</strong>
                              <span class="opacity-70">/Pcs</span>
                            </div>
                        </div>
                    </div> 
                  @endif
                  <? /*<div class="row no-gutters">
                     <span class="blink">
                        Bulk Quantity Discount :
                     </span>
                     Purchase {{ home_bulk_qty($product)['bulk_qty'] }} or more and get each
                     for
                     <strong style="color: black">
                        {{ home_bulk_discounted_price($product)['price'] }}
                     </strong>
                     instead of
                     <strong style="color: black">
                        {{ home_discounted_price($product)['price'] }}
                     </strong>
                     <a onclick="buy_now({{ home_bulk_qty($product)['bulk_qty'] }})" style="padding-left:10px; color:var(--primary);; font-weight: 600; cursor: pointer;">
                        Get Discount
                     </a>
               </div>
               <input type="hidden" value="{{ home_bulk_discounted_price($product)['price'] }}"
               id="get_bulk_discount_price">
               */ ?>
               <!-- Quantity -->
               <div class="row no-gutters">
                  <div class="col-6 col-md-4">
                     <div class="opacity-50 my-2">
                        {{ translate('Select Brand') }}:
                     </div>
                  </div>
                  <div class="col-6 col-md-8">
                      <select name="brand" id="brand" class="form-control" onchange="showDivOwnBrand(this.value)">
                        <option>---- Select Brand ----</option>
                        <option value="Our Brand - OPEL">Our Brand - OPEL</option>
                        <option value="Your Brand">Your Brand</option>                        
                      </select>
                  </div>
               </div>
               <p></p>
               <div class="row no-gutters" style="display: none" id="divOwnBrand">
                  <div class="col-6 col-md-4">
                     <div class="opacity-50 my-2">
                        {{ translate('Enter Brand Name') }}:
                     </div>
                  </div>
                  <div class="col-6 col-md-8">
                    <input type="text" name="own_brand_name" id="own_brand_name" class="form-control col border mx-2 text-center fs-16" placeholder="Enter Brand Name" lang="en">
                  </div>
               </div>
               <span id="qtyError" style="color:#F00;"></span>
               <div class="row no-gutters" style="display: none" id="divQty">                  
                  <div class="col-6 col-md-4">
                     <div class="opacity-50 my-2">
                        {{ translate('Quantity') }}:
                     </div>
                  </div>
                  <div class="col-6 col-md-8">
                     <!-- <div class="product-quantity d-flex align-items-center">
                        <div class="row no-gutters align-items-center aiz-plus-minus">
                           <div class="row no-gutters"> -->
                           <input type="number" name="quantity" id="quantity" class="form-control" value="" placeholder="Enter Qty" lang="en" onkeyup="handleQuantityChange(this.value)">
                           </div>
                        <!-- </div>
                        <div class="avialable-amount opacity-60">
                          <span id="available-quantity">{{ translate('In Stock') }}</span>
                        </div>
                     </div> -->
                  </div>
               </div>
               <div class="row no-gutters pb-3 d-none my-3" id="chosen_price_div" style="display: block !important;">
                  <div class="col-6 col-md-4">
                     <div class="opacity-50 mt-1">
                        {{ translate('Total Price') }}:
                     </div>
                  </div>
                  <div class="col-6 col-md-8">
                     <div class="product-price">
                        <strong id="chosen_price" class="h4 fw-600 text-primary">{{ $price }}</strong>
                     </div>
                  </div>
               </div>
              </div>
              <input type="hidden" name="currency" id="currency" value="{{ $currency }}">
            </form>
            <input type="hidden" name="min_order_qty_1" id="min_order_qty_1" value="{{ $product->getTranslation('min_order_qty_1') }}">
            <input type="hidden" name="min_order_qty_2" id="min_order_qty_2" value="{{ $product->getTranslation('min_order_qty_2') }}">
            
            <div class="mt-3">
                <button type="button" class="btn btn-primary" id="btnAddToCart" onclick="addToCart()" disabled><i class="la la-shopping-cart"></i>
                  <span class="d-none d-md-inline-block">{{ translate('Add to cart') }}</span>
                </button>
            </div>
          </div>
      </div>
    </div>
</div>
<script type="text/javascript">
    $('.orderdivision').on('shown.bs.tab', function(e) {
      var target = $(e.target).attr("data-type"); // activated tab
      $('#add-to-cart').attr('data-type', target);
    });

    function buy_now(value) {
      let qty = value;
      $('#quantity').val(value);
      $('.without_discount').hide();
      let priceExpression = "{{ home_bulk_discounted_price($product)['price'] }}";
      let bulk_discount_price = priceExpression.replace(/\D/g, '');
      $('#offer').text("₹ " + bulk_discount_price);
      let totalprice = bulk_discount_price * qty;
      $('#chosen_price').text("₹ " + totalprice.toLocaleString());
    }

   //  function showDivOwnBrand(selectedValue){
   //    if(selectedValue == 'Your Brand'){
   //      $('#divOwnBrand').show();
   //      $('#quantity').val($('#min_order_qty_2').val());        
   //      $('#quantity').attr('min', $('#min_order_qty_2').val()); // Sets the minimum value
   //      $('#chosen_price').text($('#currency').val() + ($('#min_order_qty_2').val()*$('#unit_price').val()))
   //    }else if(selectedValue == 'Our Brand - OPEL'){
   //      $('#divOwnBrand').hide();
   //      $('#quantity').val($('#min_order_qty_1').val());
   //      $('#quantity').attr('min', $('#min_order_qty_1').val()); // Sets the minimum value
   //      $('#chosen_price').text($('#currency').val() + ($('#min_order_qty_1').val()*$('#unit_price').val()))
   //    }
   //    $('#divQty').show();
   //    $('#btnAddToCart').prop('disabled', false);
   //  }

   function showDivOwnBrand(selectedValue) {
      if (selectedValue == 'Your Brand') {
         $('#divOwnBrand').show();
         $('#quantity').val($('#min_order_qty_2').val());
         $('#quantity').attr('min', $('#min_order_qty_2').val());
         
         // Calculate the price and format it to two decimal places
         let price = ($('#min_order_qty_2').val() * $('#unit_price').val()).toFixed(2);
         $('#chosen_price').text($('#currency').val() + price);
         
      } else if (selectedValue == 'Our Brand - OPEL') {
         $('#divOwnBrand').hide();
         $('#quantity').val($('#min_order_qty_1').val());
         $('#quantity').attr('min', $('#min_order_qty_1').val());
         
         // Calculate the price and format it to two decimal places
         let price = ($('#min_order_qty_1').val() * $('#unit_price').val()).toFixed(2);
         $('#chosen_price').text($('#currency').val() + price);
      }
      
      $('#divQty').show();
      $('#btnAddToCart').prop('disabled', false);
   }

    function handleQuantityChange(enterValue){         
      var brandName = $('#brand').val();
      var min_order_qty_1 = parseInt($('#min_order_qty_1').val());
      var min_order_qty_2 = parseInt($('#min_order_qty_2').val());
      var unit_price = $('#unit_price').val();
      if(brandName == 'Our Brand - OPEL'){
        if( parseInt(enterValue) <  parseInt(min_order_qty_1)){
          $('#qtyError').html('<strong>*</strong>Minimun order qty must be '+min_order_qty_1);
          $('#btnAddToCart').prop('disabled', true);
        }else{
          $('#qtyError').html('');
          $('#btnAddToCart').prop('disabled', false);
        }
      }else if(brandName == 'Your Brand'){
        if(parseInt(enterValue) < parseInt(min_order_qty_2)){
          $('#qtyError').html('<strong>*</strong>Minimun order qty must be '+min_order_qty_2);
          $('#btnAddToCart').prop('disabled', true);
        }else{
          $('#qtyError').html('');
          $('#btnAddToCart').prop('disabled', false);
        }
      } 
      var currency = $('#currency').val();
      var unitPrice = $('#unit_price').val();
      var totalPrice = enterValue * unitPrice;
      if (currency === '$') {
          totalPrice = totalPrice.toFixed(2); 
      } else {
          totalPrice = Math.round(totalPrice);
      }
      $('#chosen_price').text(currency + totalPrice);
    }

    function addToCart(is_carton){
      @if (!Auth::check())
            alert("Please Login as a IMPEX customer to add products to the Cart.");
            // AIZ.plugins.notify('warning', "{{ translate('Please Login as a customer to add products to the Cart.') }}");
            return false;
      @endif
      var inputData = $('#frmAddToCart').serializeArray();
      $('#addToCart').modal();
      $('.c-preloader').show();
      // var product_id = $("input[name=product_id]").val();
      // var product_name = $("input[name=product_name]").val();
      // var brand = $("input[name=brand]").val();
      // var category_name = $("input[name=category_name]").val();
      // var variant = $("input[name=variant]").val();
      // var price = $("input[name=order_by_piece_price]").val();
      // var quantity = $("input[name=quantity]").val();
      // var total   = price * quantity;

      // // Google analytics
      // gtag("event", "add_to_cart", {
      //       currency: "INR",
      //       value: {total},
      //       items: [
      //          {
      //          item_id: {product_id},
      //          item_name: {product_name},
      //          index: 0,
      //          item_brand: {brand},
      //          item_category: {category_name},
      //          item_variant: {variant},
      //          price: {price},
      //          quantity: {quantity},
      //          }
      //       ]
      // });
      $.ajax({
            type:"POST",
            url: '{{ route('cart.addToCart') }}',
            data: inputData,
            success: function(data){
               $('#addToCart-modal-body').html(null);
               $('.c-preloader').hide();
               $('#modal-size').removeClass('modal-lg');
               $('#addToCart-modal-body').html(data.modal_view);
               // AIZ.extra.plusMinus();
               // AIZ.plugins.slickCarousel();
               updateNavCart(data.nav_cart_view,data.cart_count);
            }
      });
   }   
</script>