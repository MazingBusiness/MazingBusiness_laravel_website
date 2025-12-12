<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AizUploadController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CustomerPackageController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerProductController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Payment\AamarpayController;
use App\Http\Controllers\Payment\AuthorizenetController;
use App\Http\Controllers\Payment\BkashController;
use App\Http\Controllers\Payment\InstamojoController;
use App\Http\Controllers\Payment\IyzicoController;
use App\Http\Controllers\Payment\MercadopagoController;
use App\Http\Controllers\Payment\NagadController;
use App\Http\Controllers\Payment\NgeniusController;
use App\Http\Controllers\Payment\PayhereController;
use App\Http\Controllers\Payment\PaykuController;
use App\Http\Controllers\Payment\PaypalController;
use App\Http\Controllers\Payment\PaystackController;
use App\Http\Controllers\Payment\RazorpayController;
use App\Http\Controllers\Payment\SslcommerzController;
use App\Http\Controllers\Payment\StripeController;
use App\Http\Controllers\Payment\VoguepayController;
use App\Http\Controllers\ProductQueryController;
use App\Http\Controllers\PurchaseHistoryController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AdminStatementController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\OfferProductController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendWhatsAppMessagesJob;
use App\Http\Controllers\CustomAPIController;
use App\Models\Product;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

// Generate PDF with job quee
Route::get('/download-pdf/{file_name}', [PdfController::class, 'downloadPdf'])->name('download-pdf');
Route::get('/generate-pdf-page', [PdfController::class, 'generatePdfPage'])->name('generatePdfPage');
Route::post('/generate-pdf-filename', [PdfController::class, 'generatePdfFileName'])->name('generatePdfFileName');
Route::post('/generate-pdf', [PdfController::class, 'generatePdf'])->name('generatePdf');
Route::get('/pdf-status/{filename}', [PdfController::class, 'checkPdfStatus']);
Route::get('/pdf/{filename}', function ($filename) {
    if (Storage::exists("public/pdfs/{$filename}.pdf")) {
        return Storage::download("public/pdfs/{$filename}.pdf");
    }
    return abort(404);
});
Route::get('/download-pdf/{file_name}', [PdfController::class, 'downloadPdf'])->name('downloadPdf');
Route::get('/update-download-pdf-status', [PdfController::class, 'updateDownloadPdfStatus'])->name('updateDownloadPdfStatus');

// Payment
Route::post('/payment/webhook', [PaymentController::class, 'webhook'])->name('webhook');

Route::controller(DemoController::class)->group(function () {
  Route::get('/demo/cron_1', 'cron_1');
  Route::get('/demo/cron_2', 'cron_2');
  Route::get('/convert_assets', 'convert_assets');
  Route::get('/convert_category', 'convert_category');
  Route::get('/convert_tax', 'convertTaxes');
  Route::get('/insert_product_variant_forcefully', 'insert_product_variant_forcefully');
  Route::get('/update_seller_id_in_orders/{id_min}/{id_max}', 'update_seller_id_in_orders');
  Route::get('/migrate_attribute_values', 'migrate_attribute_values');
});

Route::get('/refresh-csrf', function () {
  return csrf_token();
});

// AIZ Uploader
Route::controller(AizUploadController::class)->group(function () {
  Route::post('/aiz-uploader', 'show_uploader');
  Route::post('/aiz-uploader/upload', 'upload');
  Route::get('/aiz-uploader/get_uploaded_files', 'get_uploaded_files');
  Route::post('/aiz-uploader/get_file_by_ids', 'get_preview_files');
  Route::get('/aiz-uploader/download/{id}', 'attachment_download')->name('download_attachment');
  
  Route::post('/aiz-uploader/own-brand-upload', 'own_brand_file_upload');
  Route::get('/aiz-uploader/get-own-brand-uploaded-files', 'get_own_brand_uploaded_files');
});

Auth::routes(['verify' => true]);

// Login
Route::controller(LoginController::class)->group(function () {
  Route::get('/logout', 'logout');
  Route::get('/social-login/redirect/{provider}', 'redirectToProvider')->name('social.login');
  Route::get('/social-login/{provider}/callback', 'handleProviderCallback')->name('social.callback');
  //Apple Callback
  Route::post('/apple-callback', 'handleAppleCallback');
  Route::get('/account-deletion', 'account_deletion')->name('account_delete');
});

Route::controller(VerificationController::class)->group(function () {
  Route::get('/email/resend', 'resend')->name('verification.resend');
  Route::get('/verification-confirmation/{code}', 'verification_confirmation')->name('email.verification.confirmation');
});

Route::post('/products/{product}/price', function (Request $r, Product $product) {
    $qty    = (int) $r->input('qty', 1);
    $userId = (int) $r->input('user_id');

    // Unit price for this qty (your helper should return a numeric)
    $price = (float) product_price_with_qty_condition($product, $userId, $qty);
    $product = product_min_qty($product, $userId);

    // Threshold logic
    $target       = (float) env('SPECIAL_DISCOUNT_AMOUNT', 5000);     // e.g. ₹5,000
    $spPercentage = (float) env('SPECIAL_DISCOUNT_PERCENTAGE', 3);    // kept if you use it later

    $subtotal = $price * $qty;

    $increasePriceText = ($subtotal < $target)
        ? "ALERT : Price increase due to quantity {$qty} pices. For regular price buy {$product->min_qty} or more."
        : "";

    return response()->json([
        'price'             => $price,
        'qty'               => $qty,
        'subtotal'          => $subtotal,
        'increasePriceText' => $increasePriceText,
    ]);
})->name('products.price');

Route::controller(HomeController::class)->group(function () {
  Route::post('/get-managers', 'getManagers')->name('get-managers');
  Route::post('/check-gstin-exists', 'checkGsitnExist')->name('check-gstin-exists');
  Route::post('/check-gstin-exists-on-profile', 'checkGsitnExistOnProfile')->name('checkGsitnExistOnProfile');
  Route::get('/email_change/callback', 'email_change_callback')->name('email_change.callback');
  Route::post('/password/reset/email/submit', 'reset_password_with_code')->name('password.update');
  Route::get('/users/login', 'login')->name('user.login');
  Route::get('/users/registration', 'registration')->name('user.registration');
  Route::post('/submit-register', 'register')->name('register');
  Route::post('/users/login/cart', 'cart_login')->name('cart.login.submit');
  Route::post('/check-phone-number', 'checkPhoneNumber')->name('checkPhoneNumber');
  Route::post('/check-email', 'checkEmail')->name('checkEmail');
  Route::post('/check-aadhar-number', 'checkAadharNumber')->name('checkAadharNumber');
  Route::post('/check-postal_code', 'checkPostalCode')->name('checkPostalCode');
  Route::post('/send-otp', 'sendOtp')->name('sendOtp');
  Route::post('/assign-manager', 'assignManager')->name('assignManager');
  // Route::get('/new-page', 'new_page')->name('new_page');

  //Home Page
  Route::get('/', 'index')->name('home');

  Route::post('/home/section/featured', 'load_featured_section')->name('home.section.featured');
  Route::post('/home/section/best_selling', 'load_best_selling_section')->name('home.section.best_selling');
  Route::post('/home/section/recently_viewed', 'load_recently_viewed_section')->name('home.section.recently_viewed');
  Route::post('/home/section/search_by_profession', 'load_search_by_profession')->name('home.section.search_by_profession');
  Route::post('/home/section/top_10_brands', 'load_top_10_brands_section')->name('home.section.top10brands');
  Route::post('/home/section/home_categories', 'load_home_categories_section')->name('home.section.home_categories');
  Route::post('/home/section/best_sellers', 'load_best_sellers_section')->name('home.section.best_sellers');
  Route::post('/home/section/offer_price', 'load_offer_price_section')->name('home.section.offer_price');
  // Route to display all offer price products
  Route::get('/offer-price/all', 'offerPriceAll')->name('offer_price.all');



  //category dropdown menu ajax call
  Route::post('/category/nav-element-list', 'get_category_items')->name('category.elements');

  //Flash Deal Details Page
  Route::get('/flash-deals', 'all_flash_deals')->name('flash-deals');
  Route::get('/flash-deal/{slug}', 'flash_deal_details')->name('flash-deal-details');

  Route::get('/product/{slug}', 'product')->name('product');
  Route::get('/add-recently-viewed', 'addRecentlyViewed')->name('product.addrecent');
  Route::post('/product/show-quickview-modal', 'showQuickViewModal')->name('product.showquickviewmodal');
  Route::post('/product/variant_price', 'variant_price')->name('products.variant_price');
  Route::get('/shop/{slug}', 'shop')->name('shop.visit');
  Route::get('/shop/{slug}/{type}', 'filter_shop')->name('shop.visit.type');

  Route::get('/customer-packages', 'premium_package_index')->name('customer_packages_list_show');

  Route::get('/brands', 'all_brands')->name('brands.all');
  Route::get('/categories', 'all_categories')->name('categories.all');
  Route::get('/sellers', 'all_seller')->name('sellers');
  Route::get('/coupons', 'all_coupons')->name('coupons.all');
  Route::get('/inhouse', 'inhouse_products')->name('inhouse.all');

  // Policies
  Route::get('/seller-policy', 'sellerpolicy')->name('sellerpolicy');
  Route::get('/return-policy', 'returnpolicy')->name('returnpolicy');
  Route::get('/shipping-policy', 'shippingpolicy')->name('shippingpolicy');
  Route::get('/terms', 'terms')->name('terms');
  Route::get('/privacy-policy', 'privacypolicy')->name('privacypolicy');

  Route::get('/track-your-order', 'trackOrder')->name('orders.track');

  Route::get('/create-success', 'createSuccess')->name('customer.createSuccess');

  // Route::get('/get-graph-data', 'ordersGraphData')->name('orders.graph.data');

  // product variations route
   Route::post('/update-attribute-values', 'updateAttributeValues')->name('attribute.values.update');
   Route::post('/get-product-id',  'getProductId')->name('getProductId');

});

// Language Switch
Route::post('/language', [LanguageController::class, 'changeLanguage'])->name('language.change');

// Currency Switch
Route::post('/currency', [CurrencyController::class, 'changeCurrency'])->name('currency.change');

Route::get('/sitemap.xml', function () {
  return base_path('sitemap.xml');
});

// Route::get('/switch-back', 'CustomerController@switch_back')->name('switch_back');
// Customer
Route::resource('customers', CustomerController::class);

Route::controller(CustomerController::class)->group(function () {
  Route::get('/customer/create', 'create')->name('customer.create');
  Route::get('/switch_back', 'switch_back')->name('switch_back');
  Route::get('/switch_back_from_impex/{staff_id?}', 'switch_back_from_impex')->name('switch_back_from_impex');
  Route::post('customer-store', 'store')->name('store');
  Route::get('/customer/impex-login/{id?}', 'impexLogin')->name('impexLogin');
});

// Classified Product
Route::controller(CustomerProductController::class)->group(function () {
  Route::get('/customer-products', 'customer_products_listing')->name('customer.products');
  Route::get('/customer-products?category={category_slug}', 'search')->name('customer_products.category');
  Route::get('/customer-products?city={city_id}', 'search')->name('customer_products.city');
  Route::get('/customer-products?q={search}', 'search')->name('customer_products.search');
  Route::get('/customer-product/{slug}', 'customer_product')->name('customer.product');
});
Route::post('/customers/update-financial-info/{customer_id}', [CustomerController::class, 'updateFinancialInfo'])->name('customers.update-financial-info');
// Search
Route::controller(SearchController::class)->group(function () {
  Route::get('/search', 'index')->name('search');
  Route::get('/search?keyword={search}', 'index')->name('suggestion.search');
  Route::post('/ajax-search', 'ajax_search')->name('search.ajax');
  Route::get('/category/{category_slug}', 'listingByCategory')->name('products.category');
  Route::get('/brand/{brand_slug}', 'listingByBrand')->name('products.brand');
  Route::get('/quick-order/{category_group_id?}', 'quickOrderList')->name('products.quickorder');
  Route::get('/get-categories', 'getCategoriesByGroup')->name('getcategories');
  Route::get('/get-brands', 'getBrandByCategoriesGroupAndCategory')->name('getbrands');
  Route::get('/quick-order-search-list', 'quickOrderSearchList')->name('quickOrderSearchList');
  Route::get('/group-category-products/{category_group__id?}', 'showGroupCategoryProducts')->name('group.category.products');

  Route::get('/get-brands-from-admin', 'getBrandsFromAdmin')->name('getBrandsFromAdmin');
  Route::get('/get-cat-group-by-seller-wise', 'getCatGroupBySellerWise')->name('getCatGroupBySellerWise');
  Route::get('/get-categories-from-admin', 'getCategoriesFromAdmin')->name('getCategoriesFromAdmin');
  Route::get('/get-own-brand-categories-from-admin', 'getOwnBrandCategoriesFromAdmin')->name('getOwnBrandCategoriesFromAdmin');
  
 
});

// Cart
Route::controller(CartController::class)->group(function () {
  // Route::get('/cart', 'index')->name('cart');
  Route::get('/cart', 'cart_v03')->name('cart');
  Route::get('/cart-v02', 'cart_v02')->name('cart_v02');
  Route::get('/cart-v03', 'cart_v03')->name('cart_v03');
  Route::post('/cart/show-cart-modal', 'showCartModal')->name('cart.showCartModal');
  Route::post('/cart/addtocart', 'addToCart')->name('cart.addToCart');
  Route::post('/cart/addProductToSplitOrder', 'addProductToSplitOrder')->name('cart.addProductToSplitOrder');
  Route::post('/cart/removeProductFromSplitOrder', 'removeProductFromSplitOrder')->name('cart.removeProductFromSplitOrder');
  Route::post('/cart/removeFromCart', 'removeFromCart')->name('cart.removeFromCart');
  Route::post('/cart/updateQuantity', 'updateQuantity')->name('cart.updateQuantity');
  Route::post('/cart/updateQuantityV02', 'updateQuantityV02')->name('cart.updateQuantityV02');
  Route::get('/cart/productDetails/{id}', 'productDetails')->name('cart.productDetails');
  Route::get('/update-cart-price', 'updateCartPrice')->name('updateCartPrice');
  Route::get('/save-for-later', 'saveForLater')->name('saveForLater');
  Route::get('/move-to-cart', 'moveToCart')->name('moveToCart');
  Route::get('/remove-from-save-for-leter-view', 'removeFromSaveForLeterView')->name('removeFromSaveForLeterView');
  Route::get('/save-all-no-credit-item-for-later', 'saveAllNoCreditItemForLater')->name('saveAllNoCreditItemForLater');
  Route::get('/move-all-no-credit-item-to-cart', 'moveAllNoCreditItemToCart')->name('moveAllNoCreditItemToCart');
  Route::post('/save-all-checked-item-for-later', 'saveAllCheckedItemForLater')->name('saveAllCheckedItemForLater');
  Route::post('/move-all-checked-item-to-cart', 'moveAllCheckedItemToCart')->name('moveAllCheckedItemToCart');
  Route::post('/sort-by-categoryId-in-save-for-later', 'sortByCategoryIdInSaveForLater')->name('sortByCategoryIdInSaveForLater');
  Route::post('/view-full-statement', 'viewFullStatement')->name('viewFullStatement');
  Route::get('/cart/offers/fragment', 'offersFragment')->name('cart.offers.fragment'); // GET returns HTML partial
  Route::get('/cart/apply-offer/{offer_id}', 'applyOffer')->name('cart.applyOffer');
  Route::get('/cart/remove-offer/{offer_id}', 'removeOffer')->name('cart.removeOffer');

  //abandoned cart list routing abandoned_cart_send_single_whatsapp
  Route::get('/abandoned-cart-list', 'abandoned_cart_list')->name('abandoned.cartlist');
  Route::post('abandoned-carts/save-remark', 'abandoned_cart_save_remark')->name('abandoned-carts.save_remark');
  Route::get('abandoned-carts/view-remark/{cart_id}', 'viewRemark')->name('abandoned-carts.view_remark');
  Route::get('abandoned-carts/get-remarks', 'getRemarks')->name('abandoned-carts.get_remarks');
  Route::get('abandoned-carts/send-whatsapp', 'abandoned_cart_send_whatsapp')->name('abandoned-carts.send_whatsapp');
  Route::get('abandoned-single-carts/send-single-whatsapp/{user_id}', 'abandoned_cart_send_single_whatsapp')->name('abandoned-carts-single.send_single_whatsapp');
  Route::post('abandoned-carts/send_bulk_whatsapp', 'sendBulkWhatsApp')->name('abandoned-carts.send_bulk_whatsapp');
  Route::get('abandoned-cart/export',  'abandonedCartExportList')->name('abandoned-cart.export');
  Route::post('/abandoned-carts/clear-cart',  'clearCart')->name('abandoned-carts.clear_cart');
  Route::post('/abandoned-carts/delete-cart-item', 'deleteCartItem')->name('abandoned-carts.delete_cart_item');

  Route::get('/fetch-companies',  'fetchCompanies')->name('fetch.companies');

  Route::get('send-quotations', 'send_quotations')->name('cart.send-quotations');

  //Purchase Order (admin part start)
  Route::get('admin/purchase-order', 'purchase_order')->name('admin.purchase_order');
  Route::get('/import-excel', 'showImportForm')->name('import.excel.form');
  Route::post('/import-excel','importExcel')->name('import.excel');

  Route::post('/purchase-orders/make',  'makePurchaseOrder')->name('purchase-order.make');
  Route::get('/purchase-orders/show-selected',  'showSelected')->name('purchase-order.showSelected');
  Route::post('/purchase-order/save', 'saveSelected')->name('purchase-order.save');
  Route::get('/finalized-purchase-orders','showFinalizedOrders')->name('finalized.purchase.orders');

  Route::get('purchase-order/product-info/{id}','showProductInfo')->name('purchase-order.product-info');
  Route::post('purchase-order/convert/{id}', 'convertToPurchase')->name('purchase-order.convert');
  Route::get('/final-purchase-orders',  'showFinalizedPurchaseOrders')->name('final.purchase.orders');
  Route::get('/purchase-order/view/{purchase_order_no}', 'viewProducts')->name('purchase-order.view');

  Route::get('/final-export/export/{purchase_no}',  'export')->name('final-purchases');
  Route::get('/purchase-order/download/{purchase_order_no}','downloadPdf')->name('purchase-order.download-pdf');
  Route::get('/send-whatsapp-message/{combined_order}', 'sendWhatsAppMessage')->name('send.whatsapp.message');

  Route::get('/download-file/{file_name}',  'downloadPDF');
  Route::get('/update-slugs',  'updateSlugs');

  Route::get('/admin/purchase-order/{id}',  'purchaseOrderDeleteItems')->name('purchase-order-delete');
  Route::get('/purchase-order/force-close/{id}', 'forceClose')->name('purchase-order-force-close');;

  //salzing puch order
  Route::get('/admin/push-order/{id}',  'pushOrder')->name('push.order');
  
  Route::get('/get-seller-info/{seller_id}','getSellerInfo')->name('seller.info');
  //Purchase Order (admin part end)

  // Offer Section
  Route::get('/get-offers','getOffers')->name('getOffers');
  Route::post('/cart/addOfferProductToCart', 'addOfferProductToCart')->name('cart.addOfferProductToCart');
  
  
  Route::get('/cart/manager41/quotation/download',
         'manager41DownloadQuotation'
    )->name('cart.manager41.download-quotation');
  

});

//Paypal START
Route::controller(PaypalController::class)->group(function () {
  Route::get('/paypal/payment/done', 'getDone')->name('payment.done');
  Route::get('/paypal/payment/cancel', 'getCancel')->name('payment.cancel');
});
//Mercadopago START
Route::controller(MercadopagoController::class)->group(function () {
  Route::any('/mercadopago/payment/done', 'paymentstatus')->name('mercadopago.done');
  Route::any('/mercadopago/payment/cancel', 'callback')->name('mercadopago.cancel');
});
//Mercadopago

// SSLCOMMERZ Start
Route::controller(SslcommerzController::class)->group(function () {
  Route::get('/sslcommerz/pay', 'index');
  Route::POST('/sslcommerz/success', 'success');
  Route::POST('/sslcommerz/fail', 'fail');
  Route::POST('/sslcommerz/cancel', 'cancel');
  Route::POST('/sslcommerz/ipn', 'ipn');
});
//SSLCOMMERZ END

//Stipe Start
Route::controller(StripeController::class)->group(function () {
  Route::get('stripe', 'stripe');
  Route::post('/stripe/create-checkout-session', 'create_checkout_session')->name('stripe.get_token');
  Route::any('/stripe/payment/callback', 'callback')->name('stripe.callback');
  Route::get('/stripe/success', 'success')->name('stripe.success');
  Route::get('/stripe/cancel', 'cancel')->name('stripe.cancel');
});
//Stripe END

// Compare
Route::controller(CompareController::class)->group(function () {
  Route::get('/compare', 'index')->name('compare');
  Route::get('/compare/reset', 'reset')->name('compare.reset');
  Route::post('/compare/addToCompare', 'addToCompare')->name('compare.addToCompare');
});

// Subscribe
Route::resource('subscribers', SubscriberController::class);

Route::group(['middleware' => ['user', 'verified', 'unbanned']], function () {

  Route::controller(HomeController::class)->group(function () {
    Route::get('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/profile', 'profile')->name('profile');
    Route::post('/new-user-verification', 'new_verify')->name('user.new.verify');
    Route::post('/new-user-email', 'update_email')->name('user.change.email');
    Route::post('/user/update-profile', 'userProfileUpdate')->name('user.profile.update');
    Route::post('/own-brand-request-submit', 'ownBrandRequestSubmit')->name('ownBrandRequestSubmit');
    
  });

  Route::get('/all-notifications', [NotificationController::class, 'index'])->name('all-notifications');

});

Route::get('/order-confirmed-test', [CheckoutController::class,'order_confirmed_test'])->name('order_confirmed_test');
Route::post('/verify-and-pay', [CheckoutController::class,'verifyAndPay'])->name('verifyAndPay');
Route::post('/check-payment-status', [CheckoutController::class,'checkPaymentStatus'])->name('checkPaymentStatus');

Route::get('/pay-amount/{payment_for}/{party_code}/{id}', [CheckoutController::class,'payAmount'])->name('payAmount');
Route::post('/verify-and-pay-from-payment-page', [CheckoutController::class,'verifyAndPayFromPaymentPage'])->name('verifyAndPayFromPaymentPage');
Route::post('/get-amount', [CheckoutController::class,'getAmount'])->name('getAmount');
Route::post('/generate-QrCode-For-Custom-Amount', [CheckoutController::class,'generateQrCodeForCustomAmount'])->name('generateQrCodeForCustomAmount');


// --------------------------- Warranty Part ---------------------------------
Route::get('/warranty-claim', [PurchaseHistoryController::class,'warrantyClaim'])->name('warrantyClaim');
Route::post('/warranty-claim-post', [PurchaseHistoryController::class,'warrantyClaimPost'])->name('warrantyClaimPost');
Route::get('/warranty-claim-details', [PurchaseHistoryController::class,'warrantyClaimDetails'])->name('warrantyClaimDetails');
Route::post('/warranty-user-type-post', [PurchaseHistoryController::class,'warrantyUserTypePost'])->name('warrantyUserTypePost');
Route::get('/warranty-add-product-details', [PurchaseHistoryController::class,'warrantyAddProductDetails'])->name('warrantyAddProductDetails');
Route::post('/warranty-barcode-check', [PurchaseHistoryController::class,'warrantyBarcodeCheck'])->name('warrantyBarcodeCheck');
Route::post('/warranty-invoice-check', [PurchaseHistoryController::class,'warrantyInvoiceCheck'])->name('warrantyInvoiceCheck');
Route::post('/warranty-date-check', [PurchaseHistoryController::class,'warrantyDateCheck'])->name('warrantyDateCheck');
Route::post('/check-gstin-exists-for-warranty', [PurchaseHistoryController::class,'checkGsitnExistForWarranty'])->name('checkGsitnExistForWarranty');
Route::get('/warranty-logout', [PurchaseHistoryController::class,'warrantyLogout'])->name('warrantyLogout');
Route::post('/warranty-submit', [PurchaseHistoryController::class,'warrantySubmit'])->name('warrantySubmit');
Route::get('/warranty-ship-pdf-download', [PurchaseHistoryController::class, 'warrantyShipPdfDownload'])->name('warrantyShipPdfDownload');
Route::post('/warranty-corrier-info-upload', [PurchaseHistoryController::class, 'warrantyCorrierInfoUpload'])->name('warrantyCorrierInfoUpload');
Route::get('/warranty-details', [PurchaseHistoryController::class, 'warrantyDetails'])->name('warrantyDetails');


Route::group(['middleware' => ['customer', 'verified', 'unbanned']], function () {

  // Checkout Routs
  Route::group(['prefix' => 'checkout'], function () {
    Route::controller(CheckoutController::class)->group(function () {
      // Route::get('/', 'get_shipping_info')->name('checkout.shipping_info');
      // Route::any('/delivery_info', 'store_shipping_info')->name('checkout.store_shipping_infostore');

      Route::get('/', 'get_shipping_info_v02')->name('checkout.shipping_info');
      // Route::any('/delivery-info', 'store_shipping_info_v02')->name('checkout.store_shipping_info');

      Route::get('/checkout-v02', 'get_shipping_info_v02')->name('checkout.get_shipping_info_v02');
      Route::any('/delivery-info', 'store_shipping_info_v02')->name('checkout.store_shipping_info_v02');

      Route::post('/payment_select', 'store_delivery_info')->name('checkout.store_delivery_info');
      // Route::get('/order-confirmed', 'order_confirmed')->name('order_confirmed');

      Route::get('/order-confirmed', 'order_confirmed_v02')->name('order_confirmed');

      // Route::get('/order-confirmed-v02', 'order_confirmed_v02')->name('order_confirmed_v02');

      Route::post('/payment', 'checkout')->name('payment.checkout');
      Route::post('/get_pick_up_points', 'get_pick_up_points')->name('shipping_info.get_pick_up_points');
      Route::get('/payment-select', 'get_payment_info')->name('checkout.payment_info');
      Route::post('/apply_coupon_code', 'apply_coupon_code')->name('checkout.apply_coupon_code');
      Route::post('/remove_coupon_code', 'remove_coupon_code')->name('checkout.remove_coupon_code');
      //Club point
      Route::post('/apply-club-point', 'apply_club_point')->name('checkout.apply_club_point');
      Route::post('/remove-club-point', 'remove_club_point')->name('checkout.remove_club_point');
      Route::get('/payment-with-virtual-address', 'paymentWithVirtualAddress')->name('checkout.paymentWithVirtualAddress');

    });
  });

  // Purchase History

  
  Route::resource('purchase_history', PurchaseHistoryController::class);
  Route::controller(PurchaseHistoryController::class)->group(function () {
    Route::get('/purchase_history/details/{id}', 'purchase_history_details')->name('purchase_history.details');
    Route::get('/purchase_history/destroy/{id}', 'order_cancel')->name('purchase_history.destroy');
    Route::get('digital-purchase-history', 'digital_index')->name('digital_purchase_history.index');
    Route::get('/digital-products/download/{id}', 'download')->name('digital-products.download');
    Route::get('statement', 'statement')->name('statement');
    Route::get('/statement-details/{party_code}', 'statement_details')->name('statementDetails');
    Route::post('/serarch-statement-details', 'searchStatementDetails')->name('searchStatementDetails');
    Route::post('/refresh-statement-details', 'refreshStatementDetails')->name('refreshStatementDetails');
    Route::post('/download-statement-pdf', 'downloadStatementPdf')->name('downloadStatementPdf'); // send only whatsapp

    Route::post('/send-pay-now-link',  'sendPayNowLink')->name('send.paynow.link');
    Route::get('/rewards', 'rewards')->name('rewards');

    Route::get('/rewards/download', 'rewardsDownload')->name('rewards.download');
    Route::get('/send-reward-whatsapp', 'sendRewardWhatsapp')->name('sendRewardWhatsapp');
  });
  Route::get('/transaction-details', [CheckoutController::class, 'transactionDetails'])->name('transactionDetails');
  

  // Wishlist
  Route::resource('wishlists', WishlistController::class);
  Route::post('/wishlists/remove', [WishlistController::class, 'remove'])->name('wishlists.remove');

  // Wallet
  Route::controller(WalletController::class)->group(function () {
    Route::get('/wallet', 'index')->name('wallet.index');
    Route::post('/recharge', 'recharge')->name('wallet.recharge');
  });

  // Support Ticket
  Route::resource('support_ticket', SupportTicketController::class);
  Route::post('support_ticket/reply', [SupportTicketController::class, 'seller_store'])->name('support_ticket.seller_store');

  // Customer Package
  Route::post('/customer_packages/purchase', [CustomerPackageController::class, 'purchase_package'])->name('customer_packages.purchase');

  // Customer Product
  Route::resource('customer_products', CustomerProductController::class);
  Route::controller(CustomerProductController::class)->group(function () {
    Route::get('/customer_products/{id}/edit', 'edit')->name('customer_products.edit');
    Route::post('/customer_products/published', 'updatePublished')->name('customer_products.published');
    Route::post('/customer_products/status', 'updateStatus')->name('customer_products.update.status');
    Route::get('/customer_products/destroy/{id}', 'destroy')->name('customer_products.destroy');
  });

  // Product Review
  Route::post('/product_review_modal', [ReviewController::class, 'product_review_modal'])->name('product_review_modal');
});

// Route::get('/sync-statement-from-salezing',  [PurchaseHistoryController::class, 'syncStatementFromSalezing'])->name('syncStatementFromSalezing');
Route::get('/cron-statement-from-salezing',  [PurchaseHistoryController::class, 'syncStatementFromSalezing'])->name('syncStatementFromSalezing');
Route::get('/sync-salzing-statement-for-opening-balance',  [PurchaseHistoryController::class, 'syncSalzingStatementForOpeningBalance'])->name('syncSalzingStatementForOpeningBalance');

Route::get('/cron-for-stock-update',  [PurchaseHistoryController::class, 'cronForStockUpdate'])->name('cronForStockUpdate');



Route::post('/download-statement', [InvoiceController::class, 'downloadStatementOnly'])->name('downloadStatementOnly');
Route::get('iv/{hash}', [InvoiceController::class, 'open_invoice_download'])->name('invoice.opendownload');

Route::group(['middleware' => ['auth']], function () {

  Route::get('invoice/{order_id}', [InvoiceController::class, 'invoice_download'])->name('invoice.download');

  // Reviews
  Route::resource('/reviews', ReviewController::class);

  // Product Conversation
  Route::resource('conversations', ConversationController::class);
  Route::controller(ConversationController::class)->group(function () {
    Route::get('/conversations/destroy/{id}', 'destroy')->name('conversations.destroy');
    Route::post('conversations/refresh', 'refresh')->name('conversations.refresh');
  });

  // Product Query
  Route::resource('product-queries', ProductQueryController::class);

  Route::resource('messages', MessageController::class);

  //Address
  Route::resource('addresses', AddressController::class);
  Route::controller(AddressController::class)->group(function () {
    Route::post('/get-states', 'getStates')->name('get-state');
    Route::post('/get-cities', 'getCities')->name('get-city');
    Route::post('/addresses/update/{id}', 'update')->name('addresses.update');
    Route::get('/addresses/destroy/{id}', 'destroy')->name('addresses.destroy');
    Route::get('/addresses/set_default/{id}', 'set_default')->name('addresses.set_default');
  });
});

Route::resource('shops', ShopController::class);

Route::get('/instamojo/payment/pay-success', [InstamojoController::class, 'success'])->name('instamojo.success');

Route::post('rozer/payment/pay-success', [RazorpayController::class, 'payment'])->name('payment.rozer');

Route::get('/paystack/payment/callback', [PaystackController::class, 'handleGatewayCallback']);
Route::get('/paystack/new-callback', [PaystackController::class, 'paystackNewCallback']);

Route::controller(VoguepayController::class)->group(function () {
  Route::get('/vogue-pay', 'showForm');
  Route::get('/vogue-pay/success/{id}', 'paymentSuccess');
  Route::get('/vogue-pay/failure/{id}', 'paymentFailure');
});

//Iyzico
Route::any('/iyzico/payment/callback/{payment_type}/{amount?}/{payment_method?}/{combined_order_id?}/{customer_package_id?}/{seller_package_id?}', [IyzicoController::class, 'callback'])->name('iyzico.callback');

Route::get('/customer-products/admin', [IyzicoController::class, 'initPayment'])->name('profile.edit');

//payhere below
Route::controller(PayhereController::class)->group(function () {
  Route::get('/payhere/checkout/testing', 'checkout_testing')->name('payhere.checkout.testing');
  Route::get('/payhere/wallet/testing', 'wallet_testing')->name('payhere.checkout.testing');
  Route::get('/payhere/customer_package/testing', 'customer_package_testing')->name('payhere.customer_package.testing');

  Route::any('/payhere/checkout/notify', 'checkout_notify')->name('payhere.checkout.notify');
  Route::any('/payhere/checkout/return', 'checkout_return')->name('payhere.checkout.return');
  Route::any('/payhere/checkout/cancel', 'chekout_cancel')->name('payhere.checkout.cancel');

  Route::any('/payhere/wallet/notify', 'wallet_notify')->name('payhere.wallet.notify');
  Route::any('/payhere/wallet/return', 'wallet_return')->name('payhere.wallet.return');
  Route::any('/payhere/wallet/cancel', 'wallet_cancel')->name('payhere.wallet.cancel');

  Route::any('/payhere/seller_package_payment/notify', 'seller_package_notify')->name('payhere.seller_package_payment.notify');
  Route::any('/payhere/seller_package_payment/return', 'seller_package_payment_return')->name('payhere.seller_package_payment.return');
  Route::any('/payhere/seller_package_payment/cancel', 'seller_package_payment_cancel')->name('payhere.seller_package_payment.cancel');

  Route::any('/payhere/customer_package_payment/notify', 'customer_package_notify')->name('payhere.customer_package_payment.notify');
  Route::any('/payhere/customer_package_payment/return', 'customer_package_return')->name('payhere.customer_package_payment.return');
  Route::any('/payhere/customer_package_payment/cancel', 'customer_package_cancel')->name('payhere.customer_package_payment.cancel');
});

//N-genius
Route::controller(NgeniusController::class)->group(function () {
  Route::any('ngenius/cart_payment_callback', 'cart_payment_callback')->name('ngenius.cart_payment_callback');
  Route::any('ngenius/wallet_payment_callback', 'wallet_payment_callback')->name('ngenius.wallet_payment_callback');
  Route::any('ngenius/customer_package_payment_callback', 'customer_package_payment_callback')->name('ngenius.customer_package_payment_callback');
  Route::any('ngenius/seller_package_payment_callback', 'seller_package_payment_callback')->name('ngenius.seller_package_payment_callback');
});

//bKash
Route::controller(BkashController::class)->group(function () {
  Route::post('/bkash/createpayment', 'checkout')->name('bkash.checkout');
  Route::post('/bkash/executepayment', 'excecute')->name('bkash.excecute');
  Route::get('/bkash/success', 'success')->name('bkash.success');
});

Route::get('/checkout-payment-detail', [StripeController::class, 'checkout_payment_detail']);

//Nagad
Route::get('/nagad/callback', [NagadController::class, 'verify'])->name('nagad.callback');

//aamarpay
Route::controller(AamarpayController::class)->group(function () {
  Route::post('/aamarpay/success', 'success')->name('aamarpay.success');
  Route::post('/aamarpay/fail', 'fail')->name('aamarpay.fail');
});

//Authorize-Net-Payment
Route::post('/dopay/online', [AuthorizenetController::class, 'handleonlinepay'])->name('dopay.online');

//payku
Route::get('/payku/callback/{id}', [PaykuController::class, 'callback'])->name('payku.result');

//Blog Section
Route::controller(BlogController::class)->group(function () {
  Route::get('/blog', 'all_blog')->name('blog');
  Route::get('/blog/{slug}', 'blog_details')->name('blog.details');
});

Route::controller(PageController::class)->group(function () {
  //mobile app balnk page for webview
  Route::get('/mobile-page/{slug}', 'mobile_custom_page')->name('mobile.custom-pages');

  //Custom page
  Route::get('/{slug}', 'show_custom_page')->name('custom-pages.show_custom_page');
});



// Route::controller(PdfController::class)->group(function () {
//   Route::get('/generate-pdf-page', 'generatePdfPage')->name('generatePdfPage');
//   Route::post('/generate-pdf', 'generate-pdf')->name('generate-pdf');
//   //Apple Callback
//   Route::post('/apple-callback', 'handleAppleCallback');
//   Route::get('/account-deletion', 'account_deletion')->name('account_delete');
// });


// Route::post('/upload-to-tempserver', function (Request $request) {
//   if ($request->hasFile('file')) {
//       $file = $request->file('file');
//       $filename = $file->getClientOriginalName();

//       try {
//           // Ensure the directory exists
//           $destinationPath = public_path('tempserver');
//           if (!file_exists($destinationPath)) {
//               mkdir($destinationPath, 0777, true); // Create the directory if it doesn't exist
//           }

//           // Save the file to the tempserver directory
//           $file->move($destinationPath, $filename);

//           Log::info("File uploaded: " . $filename);

//           return response()->json(['message' => 'File uploaded successfully'], 200);
//       } catch (\Exception $e) {
//           Log::error("Failed to upload file: " . $e->getMessage());
//           return response()->json(['message' => 'Failed to upload file', 'error' => $e->getMessage()], 500);
//       }
//   }

//   Log::warning("No file uploaded in request.");
//   return response()->json(['message' => 'No file uploaded'], 400);
// });



Route::post('/upload-to-tempserver', function (Request $request) {
    if ($request->hasFile('file')) {
        $file = $request->file('file');
        $filename = $file->getClientOriginalName();

        try {
            // Set the destination path to public/uploads/all
            $destinationPath = public_path('uploads/all');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true); // Create the directory if it doesn't exist
            }

            // Save the file to the uploads/all directory
            $file->move($destinationPath, $filename);

            Log::info("File uploaded: " . $filename);

            return response()->json(['message' => 'File uploaded successfully'], 200);
        } catch (\Exception $e) {
            Log::error("Failed to upload file: " . $e->getMessage());
            return response()->json(['message' => 'Failed to upload file', 'error' => $e->getMessage()], 500);
        }
    }

    Log::warning("No file uploaded in request.");
    return response()->json(['message' => 'No file uploaded'], 400);
});

// Group routes for WhatsAppWebhookController
Route::controller(WhatsAppWebhookController::class)->group(function () {
  Route::get('/whatsapp/webhook', 'webhook');  // This should only handle POST requests
 
});

// Route::controller(AdminStatementController::class)->group(function () {
//   Route::get('/admin/statement', 'statement')->name('adminStatement');
//   Route::get('/admin/get-managers-by-warehouse', 'getManagersByWarehouse')->name('getManagersByWarehouse');
//   Route::post('/generate-statement-pdf', 'createStatementPdf')->name('generate.statement.pdf');
//   Route::post('/admin/statement/generate-pdf-bulk',  'generateBulkStatements')->name('generate.statement.pdf.bulk');
//   Route::post('/generate-statement-pdf-checked', 'generateStatementPdfChecked')->name('generate.statement.pdf.checked');
//   Route::get('/admin/statement/export',  'statementExport')->name('adminStatementExport');
//   Route::post('/sync-statement', 'syncStatement')->name('sync.statement');
//   Route::post('/generate-statement-pdf-bulk-checked','generateBulkOrCheckedStatements')->name('generate.statement.pdf.bulk.checked');
//   Route::post('/notify-manager', 'notifyManager')->name('notify.manager');
//   Route::post('/submit-comment',  'submitComment')->name('submitComment');
//   Route::post('/get-all-users-data',  'getAllUsersData')->name('get.all.users.data');
//   Route::get('admin/send-whatsapp-statements',  'sendRemainderWhatsAppForStatements');
//   Route::post('/send-whatsapp-messages',  'sendWhatsAppMessages')->name('send.whatsapp.messages');
//   Route::post('admin/whatsapp-bombing',  'processWhatsapp')->name('processWhatsapp');
//   Route::get('admin/delete-statements-pdf-files',  'deletePdfFiles');
// 	Route::get('admin/send-overdue-statements',  'sendOverdueStatements')->name('send.overdue.statements');
// 	Route::get('admin/send-whatsapp-statements-all','apiStatementSendWhatsappAll');
// 	Route::get('admins/create-pdf/{user_id}', 'generateUserStatementPDF')->name('generatePDF');
//   Route::get('admin/order-logistics-details', 'getOrderLogisticsDetails')->name('order-logistics.details');
//   Route::get('admin/api-order-details',  'getOrderDetailsByApprovalCode')->name('api-order-details');

//   Route::get('api/process-order-dispatch',  'processOrderDispatch');
//   Route::get('api/process-order-bills',  'processOrderBills');
// });







Route::controller(CustomAPIController::class)->group(function () {
 Route::get('api/fetch-order-details',  'fetchOrderDetailsForDispatch');

  Route::get('api/process-dispatch', 'processOrderDispatch');
  Route::get('api/process-order-bill',  'processOrderBill');
  Route::get('api/process-approvals', 'insertApprovedData');
});

Route::get('/generate-invoice/{invoice_no}', [InvoiceController::class, 'generateInvoice'])
    ->name('generate.invoice');
