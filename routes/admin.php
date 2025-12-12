<?php

use App\Http\Controllers\AddonController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AizUploadController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\BlogCategoryController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\CarrierController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryGroupController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPackageController;
use App\Http\Controllers\CustomerProductController;
use App\Http\Controllers\DigitalProductController;
use App\Http\Controllers\FlashDealController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PickupPointController;
use App\Http\Controllers\ProductBulkUploadController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductQueryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerWithdrawRequestController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\ZoneController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\DispatchDataController;
use App\Http\Controllers\BillsDataController;
use App\Http\Controllers\OrderLogisticsController;
use App\Http\Controllers\AdminStatementController;
use App\Http\Controllers\PendingDispatchOrder;
use App\Http\Controllers\OfferProductController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\WhatsappTopCategories;
use App\Http\Controllers\LogisticWhatsappAssignController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\BusyExportController;
use App\Http\Controllers\RewardReminderController;
use App\Http\Controllers\ZohoController;
use App\Http\Controllers\EwayController;
use App\Http\Controllers\Manager41StatementController;
use App\Http\Controllers\WarrantyClaimController;
use App\Http\Controllers\WhatsaapCarousel;
use App\Http\Controllers\CloudResponseController;
use App\Http\Controllers\NotificationAndCronJobController;
use App\Http\Controllers\CronJobController;
use App\Http\Controllers\ManagerClientPurchaseNotify;
use App\Http\Controllers\Manager41OrderLogisticsController;
use App\Http\Controllers\ImportCommercialInvoiceController;
use App\Http\Controllers\ImportCartController;
use App\Http\Controllers\PdfContentController;

/*
|--------------------------------------------------------------------------
| Admin Routes 
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */
 
 
Route::controller(PdfContentController::class)->group(function () {
    Route::get('/pdf-contents', 'index')->name('pdf_contents.index');
    Route::post('/pdf-contents', 'store')->name('pdf_contents.store');
    Route::get('/pdf-contents/search-products', 'searchProducts')->name('pdf_contents.search_products');
});
//Update Routes
Route::controller(UpdateController::class)->group(function () {
  Route::post('/update', 'step0')->name('update');
  Route::get('/update/step1', 'step1')->name('update.step1');
  Route::get('/update/step2', 'step2')->name('update.step2');
});
Route::controller(OrderController::class)->group(function () {
  Route::get('/admin/negative-stock-entry', 'negativeStockEntry')->name('order.negativeStockEntry');
  Route::get('/admin/negative-stock-entry-v2', 'negativeStockEntryV2')->name('order.negativeStockEntryV2');
  Route::get('/admin/inventory-product-entry', 'inventoryProductEntry')->name('order.inventoryProductEntry');
  Route::get('/admin/inventory-entry', 'inventoryEntry')->name('order.inventoryEntry');
});
Route::get('/admin', [AdminController::class, 'admin_dashboard'])->name('admin.dashboard')->middleware(['auth', 'admin']);
Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'admin']], function () {

  // category
  Route::resource('categories', CategoryController::class);
  Route::resource('category-groups', CategoryGroupController::class);
  Route::controller(CategoryController::class)->group(function () {
    Route::get('/categories/edit/{id}', 'edit')->name('categories.edit');
    Route::get('/categories/destroy/{id}', 'destroy')->name('categories.destroy');
    Route::post('/categories/featured', 'updateFeatured')->name('categories.featured');
    Route::get('/own-brand-categories', 'ownBrandCategories')->name('categories.ownBrandCategories');
    Route::get('/own-brand-category/create', 'ownBrandCategoryCreate')->name('category.ownBrandCategoryCreate');
    Route::post('/own-brand-category/submit-own-brand-category', 'submmitOwnBrandCategory')->name('category.submmitOwnBrandCategory');
    Route::get('/own-brand-category/edit/{id}', 'ownBrandCategoryEdit')->name('category.ownBrandCategoryEdit');
    Route::post('/own-brand-category/update/{id}', 'ownBrandCategoryUpdate')->name('category.ownBrandCategoryUpdate');
    Route::get('/own-brand-category/delete/{id}', 'ownBrandCategoryDelete')->name('category.ownBrandCategoryDelete');
  });
  Route::controller(CategoryGroupController::class)->group(function () {
    Route::get('/category-groups/edit/{id}', 'edit')->name('category-groups.edit');
    Route::get('/category-groups/destroy/{id}', 'destroy')->name('category-groups.destroy');
    Route::post('/category-groups/featured', 'updateFeatured')->name('category-groups.featured');
    Route::get('/own-brand-category-groups', 'ownBrandCategoryGroups')->name('category-groups.ownBrandCategoryGroups');
    Route::get('/own-brand-category-groups/create', 'ownBrandCategoryGroupsCreate')->name('category-groups.ownBrandCategoryGroupsCreate');
    Route::post('/own-brand-category-groups/submit-own-brand-category-groups', 'submmitOwnBrandCategoryGroups')->name('category-groups.submmitOwnBrandCategoryGroups');
    Route::get('/own-brand-category-groups/edit/{id}', 'ownBrandCategoryGroupsEdit')->name('category-groups.ownBrandCategoryGroupsEdit');
    Route::post('/own-brand-category-groups/update/{id}', 'ownBrandCategoryGroupsUpdate')->name('category-groups.ownBrandCategoryGroupsUpdate');
    Route::get('/own-brand-category-groups/delete/{id}', 'ownBrandCategoryGroupDelete')->name('category-groups.ownBrandCategoryGroupDelete');
  });

  // Brand
  Route::resource('brands', BrandController::class);
  Route::controller(BrandController::class)->group(function () {
    Route::get('/brands/edit/{id}', 'edit')->name('brands.edit');
    Route::get('/brands/destroy/{id}', 'destroy')->name('brands.destroy');
  });

  // Warehouse
  Route::resource('warehouses', WarehouseController::class);
  Route::controller(WarehouseController::class)->group(function () {
    Route::get('/warehouses/create', 'create')->name('warehouses.create');
    Route::get('/warehouses/edit/{id}', 'edit')->name('warehouses.edit');
    Route::get('/warehouses/destroy/{id}', 'destroy')->name('warehouses.destroy');
  });

  // Products
  Route::controller(ProductController::class)->group(function () {
    Route::get('/products/admin', 'admin_products')->name('products.admin');
    Route::get('/products/seller', 'seller_products')->name('products.seller');
    Route::get('/products/all', 'all_products')->name('products.all');
    Route::get('/products/without-images', 'no_images')->name('products.no-images');
    Route::get('/products/create', 'create')->name('products.create');
    Route::post('/products/store/', 'store')->name('products.store');
    Route::get('/products/admin/{id}/edit', 'admin_product_edit')->name('products.admin.edit');
    Route::get('/products/seller/{id}/edit', 'seller_product_edit')->name('products.seller.edit');
    Route::post('/products/update/{product}', 'update')->name('products.update');
    Route::post('/products/todays_deal', 'updateTodaysDeal')->name('products.todays_deal');
    Route::post('/products/featured', 'updateFeatured')->name('products.featured');
    Route::post('/products/published', 'updatePublished')->name('products.published');
    Route::post('/products/approved', 'updateProductApproval')->name('products.approved');
    Route::post('/products/get_products_by_subcategory', 'get_products_by_subcategory')->name('products.get_products_by_subcategory');
    Route::get('/products/duplicate/{id}', 'duplicate')->name('products.duplicate');
    Route::get('/products/destroy/{id}', 'destroy')->name('products.destroy');
    Route::post('/bulk-product-delete', 'bulk_product_delete')->name('bulk-product-delete');
    Route::post('/products/sku_combination', 'sku_combination')->name('products.sku_combination');
    Route::post('/products/sku_combination_edit', 'sku_combination_edit')->name('products.sku_combination_edit');
    Route::post('/products/add-more-choice-option', 'add_more_choice_option')->name('products.add-more-choice-option');
    Route::get('/products/export-data-to-google-sheet', 'exportDataToGoogleSheet')->name('products.exportDataToGoogleSheet');
    Route::get('/products/update-products-from-google-sheet', 'updateProductsFromGoogleSheet')->name('products.updateProductsFromGoogleSheet');
    // Own Brand Product
    Route::get('/products/all-own-brand-products', 'allOwnBrandProducts')->name('products.allOwnBrandProducts');
    Route::get('/products/create-or-update-the-own-brand-products-from-google-sheet', 'createOrUpdateTheOwnBrandProductsFromGoogleSheet')->name('products.createOrUpdateTheOwnBrandProductsFromGoogleSheet');
    Route::post('/products/own-brand-published', 'updateOwnBrandPublished')->name('products.ownBrandPublished');
    Route::post('/products/own-brand-approved', 'updateOwnBrandProductApproval')->name('products.ownBrandApproved');
    Route::get('/products/own-brand-product/{id}/edit', 'own_brand_product_edit')->name('products.admin.ownBrandProductEdit');
    Route::post('/products/own-brand-product-update/{product}', 'ownBrandProductUpdate')->name('products.ownBrandProductUpdate');
    Route::get('/products/ownBrandProductDelete/{id}', 'ownBrandProductDelete')->name('products.ownBrandProductDelete');

    //backend product attribute variation (product Module)
    Route::post('/admin/add-variation',  'addVariation')->name('admin.add.variation');
	  
	  Route::get('/products/delete-file/{part_no}','deleteFile')->name('delete-file');
	  // routes/web.php
    Route::get('/get-categories-by-group', 'getCategoriesByGroup')->name('get.categories.by.group');

    Route::get('/closing-stock', 'closingStock')->name('products.closingStock');
    Route::get('/get-stock-transaction', 'getStockTransaction')->name('products.getStockTransaction');
    Route::get('/closing-stock-export', 'closingStockExport')->name('products.closingStockExport');
    Route::get('/closing-stock-export-details', 'closingStockExportDetails')->name('products.closingStockExportDetails');
    
    Route::get('/print-barcode',  'generateBarcodePdf')->name('print.barcode');
    
    
    Route::get('/preview-static',  'testStaticBarcodePdf')->name('barcode.preview.static');
    
    Route::get('/mark-as-lost',  'markAsLostPage')->name('mark_as_lost.page');
    
    Route::get('/get-product-stock',  'getProductStockByPartNo')->name('mark_as_lost.fetch_stock');
    Route::post('/mark-as-lost/store', 'storeMarkAsLost')->name('mark_as_lost.store');
    
    


  });

  Route::controller(RewardController::class)->group(function () {
    Route::get('/reward/reward-user-list', 'rewardUserList')->name('reward.rewardUserList');
    Route::post('/reward/update_preferance', 'updatePreferance')->name('reward.updatePreferance');
    Route::post('/reward/update_reward', 'updateReward')->name('reward.updateReward');
    Route::get('/reward/pull-party-into-google-sheet', 'pullPartyCodeIntoGoogleSheet')->name('reward.pullPartyCodeIntoGoogleSheet');
    Route::get('/reward/insert-data-from-google-sheet', 'insertDataFromGoogleSheet')->name('reward.insertDataFromGoogleSheet');
    Route::get('/reward/export-data-from-database', 'exportDataFromDatabase')->name('reward.exportDataFromDatabase');

    Route::get('/reward/users_rewards_points', 'usersRewardsPoints')->name('reward.usersRewardsPoints');

    Route::get('/reward/export_rewards', 'exportRewards')->name('reward.exportRewards');
    Route::post('/reward/import_credit_note_rewards', 'importCreditNoteRewards')->name('reward.importCreditNoteRewards');

    Route::get('/manual-reward-points', 'manualRewardPoint')->name('manual.reward.points');
    Route::post('/update-reward-point', 'updateRewardPoint')->name('update.reward.point');

    Route::get('/sync_new_user', 'syncNewUser')->name('reward.syncNewUser');



	//Route::get('/whatsapp-logistic-assign-rewards', 'whatsappRewardAssign')->name('reward.assign.whatsapp');
	Route::get('/early-payment-remainder', 'earlyPaymentRemainderWhatsapp');


   // Route to insert data into the RewardRemainderEarlyPayment table
    Route::get('/insert-early-payment-remainders', 'insertEarlyPaymentRemainders');

  // Route to send WhatsApp reminders for early payments
    Route::get('/send-early-payment-whatsapp', 'sendEarlyPaymentWhatsApp');
	  // Route for getting reward pdf url
    Route::get('/reward-pdf/{party_code}',  'getRewardPdfURL')->name('admin.reward.pdf');
	  
	  Route::get('/get-reminder-dates', 'getReminderDates');
	  Route::get('/notify-early-reward', 'notifyEarlyRewardToManager')->name('notify.early.reward');

  });

  // Digital Product
  Route::resource('digitalproducts', DigitalProductController::class);
  Route::controller(DigitalProductController::class)->group(function () {
    Route::get('/digitalproducts/edit/{id}', 'edit')->name('digitalproducts.edit');
    Route::get('/digitalproducts/destroy/{id}', 'destroy')->name('digitalproducts.destroy');
    Route::get('/digitalproducts/download/{id}', 'download')->name('digitalproducts.download');
  });

  Route::controller(ProductBulkUploadController::class)->group(function () {
    //Product Export
    Route::get('/product-bulk-demo', 'exportDemo')->name('product_bulk_export.demo');
    Route::get('/product-bulk-export', 'export')->name('product_bulk_export.products');
    Route::get('/download-seller-products/{id}', 'sellerProductsExport')->name('download_seller_products.products');
    Route::get('/download-seller-stocks/{id}', 'sellerStocksExport')->name('download_seller_products.stocks');
    Route::get('/download-warehouse-products/{id}', 'warehouseProductsExport')->name('download_warehouse_products.products');
    Route::get('/download-warehouse-stocks/{id}', 'warehouseStocksExport')->name('download_warehouse_products.stocks');

    //Product Bulk Upload
    Route::get('/product-bulk-upload/index', 'index')->name('product_bulk_upload.index');
    Route::post('/bulk-product-upload', 'bulk_upload')->name('bulk_product_upload');
    Route::get('/product-csv-download/{type}', 'import_product')->name('product_csv.download');
    Route::get('/vendor-product-csv-download/{id}', 'import_vendor_product')->name('import_vendor_product.download');
    Route::group(['prefix' => 'bulk-upload/download'], function () {
      Route::get('/category', 'pdf_download_category')->name('pdf.download_category');
      Route::get('/brand', 'pdf_download_brand')->name('pdf.download_brand');
      Route::get('/seller', 'pdf_download_seller')->name('pdf.download_seller');
    });
  });

  // Seller
  Route::resource('sellers', SellerController::class);
  Route::controller(SellerController::class)->group(function () {
    Route::get('sellers_ban/{id}', 'ban')->name('sellers.ban');
    Route::get('/sellers/destroy/{id}', 'destroy')->name('sellers.destroy');
    Route::post('/bulk-seller-delete', 'bulk_seller_delete')->name('bulk-seller-delete');
    Route::get('/sellers/view/{id}/verification', 'show_verification_request')->name('sellers.show_verification_request');
    Route::get('/sellers/approve/{id}', 'approve_seller')->name('sellers.approve');
    Route::get('/sellers/reject/{id}', 'reject_seller')->name('sellers.reject');
    Route::get('/sellers/login/{id}', 'login')->name('sellers.login');
    Route::post('/sellers/payment_modal', 'payment_modal')->name('sellers.payment_modal');
    Route::post('/sellers/profile_modal', 'profile_modal')->name('sellers.profile_modal');
    Route::post('/sellers/approved', 'updateApproved')->name('sellers.approved');
    
    Route::get('/test-signup', [SellerController::class, 'testSignup'])->name('test.signup');
  });

  // Seller Payment
  Route::controller(PaymentController::class)->group(function () {
    Route::get('/seller/payments', 'payment_histories')->name('sellers.payment_histories');
    Route::get('/seller/payments/show/{id}', 'show')->name('sellers.payment_history');
  });

  // Seller Withdraw Request
  Route::resource('/withdraw_requests', SellerWithdrawRequestController::class);
  Route::controller(SellerWithdrawRequestController::class)->group(function () {
    Route::get('/withdraw_requests_all', 'index')->name('withdraw_requests_all');
    Route::post('/withdraw_request/payment_modal', 'payment_modal')->name('withdraw_request.payment_modal');
    Route::post('/withdraw_request/message_modal', 'message_modal')->name('withdraw_request.message_modal');
  });

  // Customer
  Route::resource('customers', CustomerController::class);
  Route::controller(CustomerController::class)->group(function () {
    Route::get('customers_ban/{customer}/{manager_id?}', 'ban')->name('customers.ban');
    Route::get('/customers/login/{id}', 'login')->name('customers.login');
    Route::get('/customers/impex-login-from-admin/{id}', 'impexLoginFromAdmin')->name('customers.impexLoginFromAdmin');
    Route::get('/customers/destroy/{id}', 'destroy')->name('customers.destroy');
    Route::post('/bulk-customer-delete', 'bulk_customer_delete')->name('bulk-customer-delete');
    Route::post('/customers/approveOwnBrand', 'approveOwnBrand')->name('customers.approveOwnBrand');
    Route::get('/own-brand-customers', 'ownBrandCustomer')->name('customers.ownBrandCustomer');
    
    Route::get('/customers/reject/{id}', 'reject')
    ->name('customers.reject');
  });
Route::get('get_manager_by_warehouse', [CustomerController::class, 'get_manager_by_warehouse'])->name('get_manager_by_warehouse');
Route::get('get_cities_by_manager', [CustomerController::class, 'get_cities_by_manager'])->name('get_cities_by_manager');

Route::get('get_cities_by_manager_statement', [CustomerController::class, 'get_cities_by_manager_statement'])->name('get_cities_by_manager_statement');


  // Newsletter
  Route::controller(NewsletterController::class)->group(function () {
    Route::get('/newsletter', 'index')->name('newsletters.index');
    Route::post('/newsletter/send', 'send')->name('newsletters.send');
    Route::post('/newsletter/test/smtp', 'testEmail')->name('test.smtp');
  });

  Route::resource('profile', ProfileController::class);

  // Business Settings
  Route::controller(BusinessSettingsController::class)->group(function () {
    Route::post('/business-settings/update', 'update')->name('business_settings.update');
    Route::post('/business-settings/update/activation', 'updateActivationSettings')->name('business_settings.update.activation');
    Route::get('/general-setting', 'general_setting')->name('general_setting.index');
    Route::get('/activation', 'activation')->name('activation.index');
    Route::get('/payment-method', 'payment_method')->name('payment_method.index');
    Route::get('/file_system', 'file_system')->name('file_system.index');
    Route::get('/social-login', 'social_login')->name('social_login.index');
    Route::get('/smtp-settings', 'smtp_settings')->name('smtp_settings.index');
    Route::get('/google-analytics', 'google_analytics')->name('google_analytics.index');
    Route::get('/google-recaptcha', 'google_recaptcha')->name('google_recaptcha.index');
    Route::get('/google-map', 'google_map')->name('google-map.index');
    Route::get('/google-firebase', 'google_firebase')->name('google-firebase.index');

    //Facebook Settings
    Route::get('/facebook-chat', 'facebook_chat')->name('facebook_chat.index');
    Route::post('/facebook_chat', 'facebook_chat_update')->name('facebook_chat.update');
    Route::get('/facebook-comment', 'facebook_comment')->name('facebook-comment');
    Route::post('/facebook-comment', 'facebook_comment_update')->name('facebook-comment.update');
    Route::post('/facebook_pixel', 'facebook_pixel_update')->name('facebook_pixel.update');

    Route::post('/env_key_update', 'env_key_update')->name('env_key_update.update');
    Route::post('/payment_method_update', 'payment_method_update')->name('payment_method.update');
    Route::post('/google_analytics', 'google_analytics_update')->name('google_analytics.update');
    Route::post('/google_recaptcha', 'google_recaptcha_update')->name('google_recaptcha.update');
    Route::post('/google-map', 'google_map_update')->name('google-map.update');
    Route::post('/google-firebase', 'google_firebase_update')->name('google-firebase.update');

    Route::get('/verification/form', 'seller_verification_form')->name('seller_verification_form.index');
    Route::post('/verification/form', 'seller_verification_form_update')->name('seller_verification_form.update');
    Route::get('/vendor_commission', 'vendor_commission')->name('business_settings.vendor_commission');
    Route::post('/vendor_commission_update', 'vendor_commission_update')->name('business_settings.vendor_commission.update');

    //Shipping Configuration
    Route::get('/shipping_configuration', 'shipping_configuration')->name('shipping_configuration.index');
    Route::post('/shipping_configuration/update', 'shipping_configuration_update')->name('shipping_configuration.update');

    // Order Configuration
    Route::get('/order-configuration', 'order_configuration')->name('order_configuration.index');
  });

  //Currency
  Route::controller(CurrencyController::class)->group(function () {
    Route::get('/currency', 'currency')->name('currency.index');
    Route::post('/currency/update', 'updateCurrency')->name('currency.update');
    Route::post('/your-currency/update', 'updateYourCurrency')->name('your_currency.update');
    Route::get('/currency/create', 'create')->name('currency.create');
    Route::post('/currency/store', 'store')->name('currency.store');
    Route::post('/currency/currency_edit', 'edit')->name('currency.edit');
    Route::post('/currency/update_status', 'update_status')->name('currency.update_status');
  });

  //Tax
  Route::resource('tax', TaxController::class);
  Route::controller(TaxController::class)->group(function () {
    Route::get('/tax/edit/{id}', 'edit')->name('tax.edit');
    Route::get('/tax/destroy/{id}', 'destroy')->name('tax.destroy');
    Route::post('tax-status', 'change_tax_status')->name('taxes.tax-status');
  });

  // Language
  Route::resource('/languages', LanguageController::class);
  Route::controller(LanguageController::class)->group(function () {
    Route::post('/languages/{id}/update', 'update')->name('languages.update');
    Route::get('/languages/destroy/{id}', 'destroy')->name('languages.destroy');
    Route::post('/languages/update_rtl_status', 'update_rtl_status')->name('languages.update_rtl_status');
    Route::post('/languages/update-status', 'update_status')->name('languages.update-status');
    Route::post('/languages/key_value_store', 'key_value_store')->name('languages.key_value_store');

    //App Trasnlation
    Route::post('/languages/app-translations/import', 'importEnglishFile')->name('app-translations.import');
    Route::get('/languages/app-translations/show/{id}', 'showAppTranlsationView')->name('app-translations.show');
    Route::post('/languages/app-translations/key_value_store', 'storeAppTranlsation')->name('app-translations.store');
    Route::get('/languages/app-translations/export/{id}', 'exportARBFile')->name('app-translations.export');
  });

  // website setting
  Route::group(['prefix' => 'website'], function () {
    Route::controller(WebsiteController::class)->group(function () {
      Route::get('/footer', 'footer')->name('website.footer');
      Route::get('/header', 'header')->name('website.header');
      Route::get('/appearance', 'appearance')->name('website.appearance');
      Route::get('/sitemap', 'sitemap')->name('website.sitemap');
      Route::post('/sitemap', 'updateSitemap')->name('website.update-sitemap');
      Route::get('/robots', 'robots')->name('website.robots');
      Route::post('/robots', 'updateRobots')->name('website.update-robots');
      Route::get('/pages', 'pages')->name('website.pages');
    });

    // Custom Page
    Route::resource('custom-pages', PageController::class);
    Route::controller(PageController::class)->group(function () {
      Route::get('/custom-pages/edit/{id}', 'edit')->name('custom-pages.edit');
      Route::get('/custom-pages/destroy/{id}', 'destroy')->name('custom-pages.destroy');
    });
  });

  // Staff Roles
  Route::resource('roles', RoleController::class);
  Route::controller(RoleController::class)->group(function () {
    Route::get('/roles/edit/{id}', 'edit')->name('roles.edit');
    Route::get('/roles/destroy/{id}', 'destroy')->name('roles.destroy');

    // Add Permissiom
    Route::post('/roles/add_permission', 'add_permission')->name('roles.permission');
  });

  // Staff
  Route::resource('staffs', StaffController::class);
  Route::get('/staffs/destroy/{id}', [StaffController::class, 'destroy'])->name('staffs.destroy');

  // Flash Deal
  Route::resource('flash_deals', FlashDealController::class);
  Route::controller(FlashDealController::class)->group(function () {
    Route::get('/flash_deals/edit/{id}', 'edit')->name('flash_deals.edit');
    Route::get('/flash_deals/destroy/{id}', 'destroy')->name('flash_deals.destroy');
    Route::post('/flash_deals/update_status', 'update_status')->name('flash_deals.update_status');
    Route::post('/flash_deals/update_featured', 'update_featured')->name('flash_deals.update_featured');
    Route::post('/flash_deals/product_discount', 'product_discount')->name('flash_deals.product_discount');
    Route::post('/flash_deals/product_discount_edit', 'product_discount_edit')->name('flash_deals.product_discount_edit');
  });

  //Subscribers
  Route::controller(SubscriberController::class)->group(function () {
    Route::get('/subscribers', 'index')->name('subscribers.index');
    Route::get('/subscribers/destroy/{id}', 'destroy')->name('subscriber.destroy');
  });

  Route::post('/saleszing/orders/status', [OrderController::class, 'updateStatus']);

  // Order
  Route::resource('orders', OrderController::class);
  Route::controller(OrderController::class)->group(function () {
    // All Orders
    Route::get('/all_orders', 'all_orders')->name('all_orders.index');
    Route::get('/inhouse-orders', 'all_orders')->name('inhouse_orders.index');
    Route::get('/seller_orders', 'all_orders')->name('seller_orders.index');
    Route::get('orders_by_pickup_point', 'all_orders')->name('pick_up_point.index');

    Route::get('/orders/{id}/show', 'show')->name('all_orders.show');
    Route::get('/inhouse-orders/{id}/show', 'show')->name('inhouse_orders.show');
    Route::get('/seller_orders/{id}/show', 'show')->name('seller_orders.show');
    Route::get('/orders_by_pickup_point/{id}/show', 'show')->name('pick_up_point.order_show');

    Route::post('/bulk-order-status', 'bulk_order_status')->name('bulk-order-status');

    Route::get('/orders/destroy/{id}', 'destroy')->name('orders.destroy');
    Route::post('/bulk-order-delete', 'bulk_order_delete')->name('bulk-order-delete');

    Route::get('/orders/destroy/{id}', 'destroy')->name('orders.destroy');
    Route::post('/orders/details', 'order_details')->name('orders.details');
    Route::post('/orders/update_delivery_status', 'update_delivery_status')->name('orders.update_delivery_status');
    Route::post('/orders/update_payment_status', 'update_payment_status')->name('orders.update_payment_status');
    Route::post('/orders/update_tracking_code', 'update_tracking_code')->name('orders.update_tracking_code');

    //Delivery Boy Assign
    Route::post('/orders/delivery-boy-assign', 'assign_delivery_boy')->name('orders.delivery-boy-assign');

    Route::get('/all-international-pending-orders', 'all_international_orders')->name('all_international_orders');
    Route::get('/all-international-approved-orders', 'all_international_approved_orders')->name('all_international_approved_orders');
    Route::get('/all-international-in-review-orders', 'all_international_in_review_orders')->name('all_international_in_review_orders');
    Route::get('/international-order-details/{id?}', 'international_pending_order_details')->name('international_pending_order_details');
    Route::get('/international-confirm-order-details/{id?}', 'international_confirm_order_details')->name('international_confirm_order_details');
    Route::post('/international-order-update-delivery-status', 'international_order_update_delivery_status')->name('international_order_update_delivery_status');
    Route::post('/international-order-update-payment-status', 'international_order_update_payment_status')->name('international_order_update_payment_status');
    Route::post('/international-order-update-qty', 'international_order_update_qty')->name('international_order_update_qty');
    Route::post('/international-order-update-unit-price', 'international_order_update_unit_price')->name('international_order_update_unit_price');
    Route::post('/international-order-update-brand', 'international_order_update_brand')->name('international_order_update_brand');
    Route::post('/international-order-delete-product', 'international_order_delete_product')->name('international_order_delete_product');
    Route::post('/international-order-reverse-product', 'international_order_reverse_product')->name('international_order_reverse_product');
    Route::get('/products/get-own-brand-product-list', 'getOwnBrandProductList')->name('products.getOwnBrandProductsList');
    Route::post('/international-order-add-product', 'international_order_add_product')->name('international_order_add_product');
    Route::post('/international-order-add-or-update-comment-and-days-of-delivery', 'international_order_add_or_update_comment_and_days_of_delivery')->name('international_order_add_or_update_comment_and_days_of_delivery');
    Route::post('/international-order_update-confirm-status', 'international_order_update_confirm_status')->name('international_order_update_confirm_status');

    // Unpushed Order
    Route::get('/all-unpushed-orders', 'all_unpushed_orders')->name('all_unpushed_orders');
    Route::get('/unpushed-order/{id}/show', 'unpushed_order_show')->name('unpushed_order_show');
    Route::post('/bulk-order-push-to-salezing', 'bulkOrderPushToSalezing')->name('bulkOrderPushToSalezing');


    Route::get('/download-impex-order/{order_code}','impexOrderPdf')->name('impexOrderPdf');
    Route::get('/send-impex-order-whatsapp/{order_code}',  'sendImpexOrderWhatsApp')->name('send.impex.order.whatsapp');


    // Split Order
    Route::get('/all-pending-for-approval-order', 'allPendingForApprovalOrder')->name('order.allPendingForApprovalOrder');
    Route::get('/all-split-order', 'allSplitOrder')->name('order.allSplitOrder');
    Route::get('/split-order/{order_id}/{redirect?}/{data_status?}', 'splitOrder')->name('order.splitOrder');
    Route::post('/change-order-address', 'changeOrderAddress')->name('order.changeOrderAddress');
    Route::post('/save-split-order', 'saveSplitOrder')->name('order.saveSplitOrder');
    Route::post('/add-carriers', 'addCarriers')->name('order.addCarriers'); // ------------- ********* This is pending ********* --------------------
    Route::get('get-all-transport-data', 'getAllTransportData')->name('getAllTransportData');    
    Route::get('/split-order-details/{order_id}', 'splitOrderDetails')->name('order.splitOrderDetails');
    Route::post('/save-pre-close', 'savePreClose')->name('order.savePreClose');
    Route::get('/sub-order-reallocation-split-order/{sub_order_id}', 'subOrderreallocationSplitOrder')->name('order.subOrderreallocationSplitOrder');
    Route::get('/reallocation-split-order/{sub_order_id}', 'reallocationSplitOrder')->name('order.reallocationSplitOrder');
    Route::get('/all-pre-closed-order', 'allPreClosedOrder')->name('order.allPreClosedOrder');
    Route::get('/pre-closed-order-details/{order_id}', 'preClosedOrderDetails')->name('order.preClosedOrderDetails');
    Route::get('/pre-closed-order/{order_id}/{type}/{btr?}', 'preClosedOrder')->name('order.preClosedOrder');
    Route::post('/save-re-allocation-order', 'saveReAllocationOrder')->name('order.saveReAllocationOrder');
    Route::post('/undo-preclose-sub-order', 'undoPreCloseSubOrder')->name('order.undoPreCloseSubOrder');
    Route::post('/undo-preclose-order', 'undoPreCloseOrder')->name('order.undoPreCloseOrder');

    Route::post('/admin/update-hsncode', 'updateHsncode')->name('admin.updateHsncode');
    
    Route::get('/admin/all-pending-order', 'allPendingOrder')->name('order.allPendingOrder');

    Route::post('/add-new-product-by-scan', 'addNewProductByScan')->name('order.addNewProductByScan');
    Route::post('/save-sub-order-re-allocation-order', 'saveSubOrderReAllocationOrder')->name('order.saveSubOrderReAllocationOrder');

    Route::post('/save-challan', 'saveChallan')->name('order.saveChallan');
    Route::get('/all-challan', 'allChallan')->name('order.allChallan');
    Route::get('/challan-details/{id}', 'challanDetails')->name('order.challanDetails');

    // Route::get('/negative-stock-entry', 'negativeStockEntry')->name('order.negativeStockEntry');    
    
    Route::post('/sub-order-detail/barcode', 'storeBarcode')
     ->name('suborderdetail.storeBarcode');
     
    //  Route::get('/orders/{order}/send-additional-whatsapp',  'sendAdditionalWhatsapp')->name('order.sendAdditionalWhatsapp');
    Route::get('/orders/{order}/send-additional-whatsapp-ajax',  'sendAdditionalWhatsappAjax')->name('order.sendAdditionalWhatsapp.ajax');
    
    // NEW: Export route (GET)
    Route::get('/orders/pending/export', [OrderController::class, 'allPendingOrderExport'])
    ->name('order.allPendingOrder.export');


  });

  Route::post('/pay_to_seller', [CommissionController::class, 'pay_to_seller'])->name('commissions.pay_to_seller');

  //Reports
  Route::controller(ReportController::class)->group(function () {
    Route::get('/in_house_sale_report', 'in_house_sale_report')->name('in_house_sale_report.index');
    Route::get('/seller_sale_report', 'seller_sale_report')->name('seller_sale_report.index');
    Route::get('/stock_report', 'stock_report')->name('stock_report.index');
    Route::get('/wish_report', 'wish_report')->name('wish_report.index');
    Route::get('/user_search_report', 'user_search_report')->name('user_search_report.index');
    Route::get('/commission-log', 'commission_history')->name('commission-log.index');
    Route::get('/wallet-history', 'wallet_transaction_history')->name('wallet-history.index');
  });

  //Blog Section
  //Blog cateory
  Route::resource('blog-category', BlogCategoryController::class);
  Route::get('/blog-category/destroy/{id}', [BlogCategoryController::class, 'destroy'])->name('blog-category.destroy');

  // Blog
  Route::resource('blog', BlogController::class);
  Route::controller(BlogController::class)->group(function () {
    Route::get('/blog/destroy/{id}', 'destroy')->name('blog.destroy');
    Route::post('/blog/change-status', 'change_status')->name('blog.change-status');
  });

  //Coupons
  Route::resource('coupon', CouponController::class);
  Route::controller(CouponController::class)->group(function () {
    Route::get('/coupon/destroy/{id}', 'destroy')->name('coupon.destroy');

    //Coupon Form
    Route::post('/coupon/get_form', 'get_coupon_form')->name('coupon.get_coupon_form');
    Route::post('/coupon/get_form_edit', 'get_coupon_form_edit')->name('coupon.get_coupon_form_edit');
  });

  //Reviews
  Route::controller(ReviewController::class)->group(function () {
    Route::get('/reviews', 'index')->name('reviews.index');
    Route::post('/reviews/published', 'updatePublished')->name('reviews.published');
    Route::get('/reviews/demo-download', 'reviewDemoExport')->name('reviews.demo-download');
    Route::post('/reviews/bulk-upload', 'uploadBulk')->name('reviews.bulk-upload');
  });

  //Support_Ticket
  Route::controller(SupportTicketController::class)->group(function () {
    Route::get('support_ticket/', 'admin_index')->name('support_ticket.admin_index');
    Route::get('support_ticket/{id}/show', 'admin_show')->name('support_ticket.admin_show');
    Route::post('support_ticket/reply', 'admin_store')->name('support_ticket.admin_store');
  });

  //Pickup_Points
  Route::resource('pick_up_points', PickupPointController::class);
  Route::controller(PickupPointController::class)->group(function () {
    Route::get('/pick_up_points/edit/{id}', 'edit')->name('pick_up_points.edit');
    Route::get('/pick_up_points/destroy/{id}', 'destroy')->name('pick_up_points.destroy');
  });

  //conversation of seller customer
  Route::controller(ConversationController::class)->group(function () {
    Route::get('conversations', 'admin_index')->name('conversations.admin_index');
    Route::get('conversations/{id}/show', 'admin_show')->name('conversations.admin_show');
  });

  // product Queries show on Admin panel
  Route::controller(ProductQueryController::class)->group(function () {
    Route::get('/product-queries', 'index')->name('product_query.index');
    Route::get('/product-queries/{id}', 'show')->name('product_query.show');
    Route::put('/product-queries/{id}', 'reply')->name('product_query.reply');
  });

  // Product Attribute
  Route::resource('attributes', AttributeController::class);
  Route::controller(AttributeController::class)->group(function () {
    Route::get('/attributes/edit/{id}', 'edit')->name('attributes.edit');
    Route::get('/attributes/destroy/{id}', 'destroy')->name('attributes.destroy');

    //Attribute Value
    Route::post('/store-attribute-value', 'store_attribute_value')->name('store-attribute-value');
    Route::get('/edit-attribute-value/{id}', 'edit_attribute_value')->name('edit-attribute-value');
    Route::post('/update-attribute-value/{id}', 'update_attribute_value')->name('update-attribute-value');
    Route::get('/destroy-attribute-value/{id}', 'destroy_attribute_value')->name('destroy-attribute-value');

    //Colors
    Route::get('/colors', 'colors')->name('colors');
    Route::post('/colors/store', 'store_color')->name('colors.store');
    Route::get('/colors/edit/{id}', 'edit_color')->name('colors.edit');
    Route::post('/colors/update/{id}', 'update_color')->name('colors.update');
    Route::get('/colors/destroy/{id}', 'destroy_color')->name('colors.destroy');
	  
	  
	//import 
	  Route::post('/attributes/import',  'importAttributes')->name('attributes.import');
  });

  // Addon
  Route::resource('addons', AddonController::class);
  Route::post('/addons/activation', [AddonController::class, 'activation'])->name('addons.activation');

  //Customer Package
  Route::resource('customer_packages', CustomerPackageController::class);
  Route::controller(CustomerPackageController::class)->group(function () {
    Route::get('/customer_packages/edit/{id}', 'edit')->name('customer_packages.edit');
    Route::get('/customer_packages/destroy/{id}', 'destroy')->name('customer_packages.destroy');
  });

  //Classified Products
  Route::controller(CustomerProductController::class)->group(function () {
    Route::get('/classified_products', 'customer_product_index')->name('classified_products');
    Route::post('/classified_products/published', 'updatePublished')->name('classified_products.published');
    Route::get('/classified_products/destroy/{id}', 'destroy_by_admin')->name('classified_products.destroy');
  });

  // Countries
  Route::resource('countries', CountryController::class);
  Route::post('/countries/status', [CountryController::class, 'updateStatus'])->name('countries.status');

  // States
  Route::resource('states', StateController::class);
  Route::post('/states/status', [StateController::class, 'updateStatus'])->name('states.status');

  // Carriers
  Route::resource('carriers', CarrierController::class);
  Route::controller(CarrierController::class)->group(function () {
    Route::get('/carriers/destroy/{id}', 'destroy')->name('carriers.destroy');
    Route::post('/carriers/update_status', 'updateStatus')->name('carriers.update_status');
  });

  // Zones
  Route::resource('zones', ZoneController::class);
  Route::get('/zones/destroy/{id}', [ZoneController::class, 'destroy'])->name('zones.destroy');

  Route::resource('cities', CityController::class);
  Route::controller(CityController::class)->group(function () {
    Route::get('/cities/edit/{id}', 'edit')->name('cities.edit');
    Route::get('/cities/destroy/{id}', 'destroy')->name('cities.destroy');
    Route::post('/cities/status', 'updateStatus')->name('cities.status');
  });

  Route::view('/system/update', 'backend.system.update')->name('system_update');
  Route::view('/system/server-status', 'backend.system.server_status')->name('system_server');

  // uploaded files
  Route::resource('/uploaded-files', AizUploadController::class);
  Route::controller(AizUploadController::class)->group(function () {
    Route::any('/uploaded-files/file-info', 'file_info')->name('uploaded-files.info');
    Route::get('/uploaded-files/destroy/{id}', 'destroy')->name('uploaded-files.destroy');
    Route::post('/bulk-uploaded-files-delete', 'bulk_uploaded_files_delete')->name('bulk-uploaded-files-delete');
    Route::get('/all-file', 'all_file');

    // Own Brand uploaded files
    Route::get('/own-brand-all-file', 'own_brand_all_file')->name('uploaded-files.own_brand_all_file');
    Route::get('/own-brand-file-create', 'own_brand_file_create')->name('uploaded-files.own_brand_file_create');
    Route::post('/own-brand-file-upload', 'own_brand_file_upload')->name('uploaded-files.own_brand_file_upload');
  });

  

  Route::get('/all-notification', [NotificationController::class, 'index'])->name('admin.all-notification');

  Route::get('/clear-cache', [AdminController::class, 'clearCache'])->name('cache.clear');

  Route::get('/admin-permissions', [RoleController::class, 'create_admin_permissions']);
});


Route::controller(DispatchDataController::class)->group(function () {
  Route::get('/dispatch-data',  'index')->name('dispatch.data');

 Route::post('/dispatch-data/update', 'updateBilledQuantity')->name('dispatch.data.update');
Route::get('/dispatch-data/pdf/{orderId}/{partyCode}/{dispatchId}', 
   'generateDispatchPDF')
    ->name('dispatch.data.pdf');

    Route::get('/send-dispatch-pdf/{orderId}/{partyCode}/{dispatchId}',  'senddispatchpdf')->name('send.dispatch.pdf');

  Route::post('/dispatch-data/cancel', 'cancelItem')->name('dispatch.data.cancel');



});

Route::controller(BillsDataController::class)->group(function () {
 Route::get('/bills-data',  'index')->name('bills.data');
  Route::post('/bills-data/update',  'updateBilledQty')->name('bills.data.update');

  Route::get('/bills-data/pdf/{invoice_no}',  'generateBillInvoicePDF')->name('bills.data.pdf');

  Route::post('/bills/cancel-item', 'cancelItem')->name('bills.cancel-item');


  // WhatsApp invoice sending route
  Route::get('/whatsapp/send-invoice/{invoice_no}', 'sendInvoiceViaWhatsApp')
      ->name('whatsapp.send.invoice');


  });

Route::controller(OrderLogisticsController::class)->group(function () {
  Route::get('/order-logistics',  'index')->name('order.logistics');
  Route::get('/order-logistics/edit/{invoice_no}',  'edit')->name('order.logistics.edit');
  Route::post('/order-logistics/update/{invoice_no}/{id}', 'update')->name('order.logistics.update');
  Route::get('/logistics/send-whatsapp/{invoice_no}/{id}',  'sendLogisticWhatsapp')->name('logistics.send-whatsapp');
  Route::get('/temp-all-orders',  'tempAllOrders')->name('temp.all.orders');
	
  Route::get('/order-logistics/add/{encrypted_invoice_no}',  'create')->name('order.logistics.add');
  Route::post('/order-logistics/store/{encrypted_invoice_no}',  'store')->name('order.logistics.store');
  
  Route::get('order-logistics/{invoice_no}/push-zoho',  'pushZohoAttachment')
    ->name('order.logistics.push-zoho');
});

Route::controller(Manager41OrderLogisticsController::class)->group(function () {
   Route::get('create/{challan}', 'create')->name('manager41.order.logistics.create');   // challan id encrypted

    Route::post('store/{challan}',  'store')->name('manager41.order.logistics.store');    // challan id encrypted
    
    Route::get('edit/{logistic}',  'edit')->name('manager41.order.logistics.edit');     // logistic id (encrypted)
    Route::post('update/{logistic}', 'update')->name('manager41.order.logistics.update');   // logistic id (encrypted)
});


Route::controller(AdminStatementController::class)->group(function () {

  Route::get('/admin/statement', 'statement')->name('adminStatement');
  Route::get('/admin/get-managers-by-warehouse', 'getManagersByWarehouse')->name('getManagersByWarehouse');
  Route::post('/generate-statement-pdf', 'createStatementPdf')->name('generate.statement.pdf');
  Route::post('/admin/statement/generate-pdf-bulk',  'generateBulkStatements')->name('generate.statement.pdf.bulk');
  Route::post('/generate-statement-pdf-checked', 'generateStatementPdfChecked')->name('generate.statement.pdf.checked');
  Route::get('/admin/statement/export',  'statementExport')->name('adminExportStatement');
  Route::post('/sync-statement', 'syncStatement')->name('sync.statement');
  Route::post('/generate-statement-pdf-bulk-checked','generateBulkOrCheckedStatements')->name('generate.statement.pdf.bulk.checked');
  Route::post('/notify-manager', 'notifyManager')->name('notify.manager');
  Route::post('/submit-comment',  'submitComment')->name('submitComment');
  Route::post('/get-all-users-data',  'getAllUsersData')->name('get.all.users.data');
  Route::get('admin/send-whatsapp-statements',  'sendRemainderWhatsAppForStatements');
  Route::post('/send-whatsapp-messages',  'sendWhatsAppMessages')->name('send.whatsapp.messages');
  Route::post('admin/whatsapp-bombing',  'processWhatsapp')->name('processWhatsapp');
  Route::get('admin/delete-statements-pdf-files',  'deletePdfFiles');
  Route::get('admin/send-overdue-statements',  'sendOverdueStatements')->name('send.overdue.statements');
  Route::get('admin/send-whatsapp-statements-all','apiStatementSendWhatsappAll');
  Route::get('admins/create-pdf/{user_id}', 'generateUserStatementPDF')->name('generatePDF');
  Route::get('admin/order-logistics-details', 'getOrderLogisticsDetails')->name('order-logistics.details');
  Route::get('admin/api-order-details',  'getOrderDetailsByApprovalCode')->name('api-order-details');
  Route::get('api/process-order-dispatch',  'processOrderDispatch');
  Route::get('api/process-order-bills',  'processOrderBills');

  Route::get('admin/notify-statement-manager-api',  'notifyManagerAPI')->name('notify-manager-api');
  Route::post('admin/assign-manager',  'assignManager')->name('assign.manager');
  Route::post('admin/get-manager', 'getManager')->name('get.manager');
	Route::get('/download-statement-order', 'downloadStatementForOrder')->name('downloadStatementForOrder');

  Route::get('/get-due-and-overdue-amount', 'getDueAndOverDueAmount')->name('getDueAndOverDueAmount');;
  
  
  // routes/web.php
Route::get('/first-overdue-days','getFirstOverdueDays')->name('first.overdue.days');


});


  
Route::middleware(['auth'])                 // add your own guards if needed
    ->prefix('manager41')                   // URL like /manager41/...
    ->as('manager41.')                      // NAME prefix: manager41.*
    ->controller(Manager41StatementController::class)
    ->group(function () {

        // Page
        Route::get('/statement', 'index')->name('statement');

        // JSON for a single user
        Route::get('/statements/{userId}', 'statementData')->name('statements.data');

        // Cities for filter
        Route::get('/get-cities-by-manager-statement', 'getCitiesByManager')->name('get_cities_by_manager_statement');

        // WhatsApp + PDF helpers
        Route::post('/whatsapp/get-all-users-data', 'getAllUsersData')->name('get.all.users.data');
        Route::post('/whatsapp/process', 'processWhatsapp')->name('processWhatsapp');
        Route::post('/generate-statement-pdf-bulk-checked', 'generateStatementPdfBulkChecked')->name('generate.statement.pdf.bulk.checked');
        Route::post('/sync-statement', 'sync')->name('sync.statement');
        
        // PDF creator alias so the modal call works
        Route::get('/create-pdf/{partyCode}', 'createPdf')->name('create_pdf');
        
    });



Route::controller(OfferProductController::class)->group(function () {
  Route::get('/offer-products/create',  'showAddOfferProductPage')->name('offer-products.create');
  Route::get('/offer-products/get-products/{category_id}','getProductsByCategory')->name('offer-products.get-products');

  Route::post('/offer-products/save', 'saveOfferProduct')->name('offer-products.save');
  Route::get('/offers-product/list',  'listOffersProduct')->name('offers_product.list');
  Route::get('/get-brands-by-category/{categoryId}', 'getBrandsByCategory')->name('getBrandsByCategory');
  Route::get('admin/offers', 'offerLising')->name('offers.index');

  Route::get('/offer-products/view/{offer_id}','offerView')->name('offer-products.view');

  Route::get('/offer-products/edit/{offer_id}',  'offerEdit')->name('offer-products.edit');
  Route::post('/offer-products/update/{offer_id}', 'offerUpdate')->name('offer-products.update');
  Route::post('/offer-products/bulk-update', 'bulkUpdate')->name('offer-products.bulk-update');


  Route::get('/offer/all', 'showOfferPage')->name('offers.page');
  Route::post('/offers/category-products',  'getCategoryProducts')->name('offers.category_products');

  Route::post('/offer-products/save-complementary-items', 'saveComplementaryItems')->name('offer-products.save-complementary-items');
  Route::get('/offer-combination-products/combo-set-create', 'showComboSetCreateForm')->name('offer-combination-products.combo-set-create');

  Route::post('/offer-combination-products/get-free-product',  'getFreeProduct')->name('offer-combination-products.get-free-products');

  Route::post('/get-products-by-category', 'getAllProductsByCategory')->name('offers.get_products_by_category');
  Route::post('/store-offer-combination', 'storeOfferCombination')->name('store.offer.combination');

  Route::get('/admin/offer-combinations', 'combinedProductList')->name('offer.combinations.list');
  Route::get('/offer-combination/{id}',  'showComboOffer')->name('offer.combination');
  
  Route::post('/get-products-by-category-and-brand','getProductsByCategoryAndBrand')->name('get-products-by-category-and-brand');
  Route::get('/get-products-by-brand/{brandId}',  'getProductsByBrand')->name('get-products-by-brand');

  Route::get('/get-brand-list',  'getBrandList')->name('get-brand-list');
  Route::get('/get-category-list',  'getCategoryList')->name('get-category-list');

  Route::post('/complementary-items/store',  'addComplementryProduct')->name('complementary-items.store');

  Route::delete('/complementary-items/{id}',  'destroy')->name('complementary-items.destroy');

  Route::post('/offer-products/save-selections', 'saveSelections')->name('offer-products.save-selections');
  Route::delete('/offer-products/{id}', 'delete')->name('offer-products.delete');

  Route::post('/offer/update-status', 'offerUpdateStatus')->name('offer.update.status');

  Route::delete('/offers/{offer_id}/delete',  'deleteOffer')->name('offer.delete');



});



Route::controller(PendingDispatchOrder::class)->group(function () {
 
 // Routes for Pending Dispatch Orders
  Route::get('pending-dispatch-orders',  'index')->name('pending.dispatch.orders');
  Route::get('pending-dispatch-orders/send-pdf/{invoiceNo}', [PendingDispatchOrder::class, 'sendPDF'])->name('pending.dispatch.orders.send.pdf');
  Route::post('pending-dispatch-orders/update', [PendingDispatchOrder::class, 'updateBilledQuantity'])->name('pending.dispatch.orders.update');
  Route::post('pending-dispatch-orders/cancel-item', [PendingDispatchOrder::class, 'cancelItem'])->name('pending.dispatch.orders.cancel');
  Route::get('/pending-dispatch-orders/send-whatsapp/{order_id}/{party_code}', [PendingDispatchOrder::class, 'sendWhatsAppMessage'])->name('pending.dispatch.orders.send.whatsapp');
    
  Route::get('/download-approval-pdf/{orderId}/{partyCode}', 'downloadApprovalPdfURL')->name('download.approval.pdf');


});

Route::get('/download-purchase-order/{purchase_order_no}', [InvoiceController::class, 'download_purchase_order_pdf_invoice'])
    ->name('download.purchase_order');
Route::get('/download-packing-list/{purchase_order_no}', [InvoiceController::class, 'download_packing_list_pdf_invoice'])
    ->name('download.packing_list');



Route::controller(WhatsappTopCategories::class)->group(function () {
  

  Route::get('/insert/top-five-categories', 'insertTopFiveCategory')->name('insertTopFiveCategory');

  Route::get('/top-selling-categories', 'topSellingCategories')->name('topSellingCategories');
	
  Route::get('/download-top-selling-category', 'downloadTopSellingCategory');
	
  Route::get('/whatsapp-top-selling-category', 'whatsappTopFiveCategory');
	
  Route::get('/new-arrival',  'newArrivalApi');
	
  Route::get('/export-top-selling-categories', 'exportTopSellingCategories')->name('exportTopSellingCategories');

  Route::get('/generate-category-pdf', 'generateCategoryPdf');

});

Route::controller(LogisticWhatsappAssignController::class)->group(function () {
  Route::get('/whatsapp-logistic-assign-rewards', 'whatsappRewardAssign')->name('reward.assign.whatsapp');

});


// purchase order
Route::controller(PurchaseOrderController::class)->group(function () {
  Route::get('/transfer-purchase-orders',  'transferPurchaseOrderData');
	Route::get('/purchase-order',  'purchaseOrder')->name('admin.purchasebag');
	Route::get('/make-purchase-order',  'makePurchaseOrder')->name('admin.makePurchaseOrder');
	Route::post('/save-make-purchase-order',  'saveMakePurchaseOrder')->name('admin.saveMakePurchaseOrder');
	Route::get('/delete-purchasebag-item/{id}',  'deletePurchaseBagItem')->name('admin.deletePurchaseBagItem');
	
	Route::get('/purchase-orders/get-product-rows', 'getProductRows')->name('purchase-orders.getProductRows');

	
	Route::get('/supply-order-listing',  'supplyOrderLising')->name('admin.supplyOrderLising');
	Route::get('/purchase-orders/{id}/product-info', 'showProductInfo')->name('purchase-orders.product-info');
  Route::post('/purchase-orders/{id}/convert', 'convertToPurchase')->name('purchase-orders.convert');


  Route::get('/product-information', 'productInformation')->name('productInformation');
  Route::post('/update-preclose-stock', 'updatePreCloseAndStock')->name('update.preclose.stock');
	Route::post('/purchase-orders/add', 'addToPurchaseOrderDetails')->name('purchase-orders.addToPurchaseOrderDetails');
  Route::get('/purchase-invoices', 'showPurchaseInvoiceList')->name('purchase.invoices.list');

  Route::get('purchase-order/view/{id}', 'viewPurchaseInvoiceProducts')->name('purchase-order.view');
	Route::get('purchase-invoice/export/{id}',  'export')->name('purchase.invoice.export');
  Route::get('purchase-invoice/pdf/{id}',  'downloadInvoicePDF')->name('purchase.invoice.pdf');
	
  Route::get('/find-categories-by-group/{groupId}', 'getCategoriesByGroup');
  Route::post('/find-products-by-category-and-brand','getProductsByCategoryAndBrand')->name('find-products-by-category-and-brand');
  Route::get('/find-brands-by-category/{categoryId}', 'getBrandsByCategory')->name('findBrandsByCategory');
  //Route::get('/purchase-order/pdf/{purchase_order_no}', 'download_purchase_order_pdf_invoice')->name('purchase_order.download_pdf');
  Route::get('/purchase-order/download-pdf/{purchase_order_no}','purchase_order_pdf_invoice_download')->name('purchase_order.download_pdf');

  Route::get('/packing-list/download/{purchase_order_no}',  'packing_list_pdf_invoice_download')
    ->name('packing_list.download_pdf');
	
	Route::get('/po/force-close/{id}', 'forceClose')->name('po-force-close');
	Route::get('/admin/purchase-credit-notes', 'showPurchaseCreditNoteList')->name('purchase.credit.note.list');


    Route::get('/manual-make-purchase-order',  'viewManualMakePurchaseOrder')->name('admin.viewManualMakePurchaseOrder');
    
    Route::get('/manual-make-purchase-order-41',
     'viewManualMakePurchaseOrder41')
    ->name('admin.manualMakePurchaseOrder41');
    
    Route::post('/admin/fetch-product-pos', 'getProductPOs')->name('fetch.product.pos');

   // Route::post('/manual-make-purchase-order/save', 'saveManualMakePurchaseOrder')->name('admin.saveMakePurchaseOrder');
    Route::post('/admin/manual-purchase-order/save',  'saveManualPurchaseOrder')->name('admin.saveManualPurchaseOrder');
    Route::get('/get-sellers-info/{seller_id}','getSellerInfo')->name('sellers.info');
    Route::post('/admin/search-product-by-part-no',  'searchByPartNo')->name('admin.searchProductByPartNo');
    // temprory function start
    Route::post('/save-challan', 'saveChallan')->name('order.saveChallan');
    Route::get('/all-challan', 'allChallan')->name('order.allChallan');
    Route::get('/challan-details/{id}', 'challanDetails')->name('order.challanDetails');
	  Route::get('/challan/cancel/{id}', 'cancelChallan')->name('challan.cancel');

    // temprory function end
    Route::get('/view-selected-challans-products', 'viewSelectedChallansProducts')->name('challans.view.products');
    Route::post('invoice/save-from-challans', 'saveFromChallans')->name('invoice.saveFromChallans');
    Route::get('/invoiced-orders', 'invoicedOrders')->name('invoice.orders');
    Route::get('/invoiced-orders/{id}/products',  'invoiceProducts')->name('invoice.products');
    Route::get('/invoice/download/{id}', 'downloadPdf')->name('invoice.downloadPdf');
    Route::get('/btr-receipts',  'btrReceipts')->name('btr.receipts');
    Route::get('/btr-receive/{invoice_id}',  'markAsReceived')->name('btr.receive');
    Route::get('/split-order-pdf/{id}', 'splitOrderPdf')->name('splitOrderPdf');
	
	  Route::get('/download/ewaybill/{invoiceId}', 'downloadEwayBillPDF')->name('admin.download.ewaybill');
	
    Route::get('/generate-approval-pdf/{orderId}',  'generateApprovalPDF')->name('generate.approval.pdf');
	  Route::get('/generate-dispatch-pdf/{challanId}', 'generateDispatchPDF')->name('generate.dispatch.pdf');
	
	Route::get('/unavailable-products/{id}', 'generateUnavailableProductsPDF')->name('unavalibal.produts');
	Route::get('/generate-all-unavailable-products/{id}',  'generateAllUnavailableProductsPDF')
     ->name('generate.all.unavailable.products');
	
	Route::get('/invoice/download-by-no/{encrypted_invoice}',  'downloadPdfByInvoiceNo')
    ->name('invoice.downloadByNo');

    Route::get('/zoho/creditnote/generate-irp/{zoho_creditnote_id}',  'generateIRP')->name('zoho.creditnote.generate_irp');

	Route::get('/admin/debit-note-purchase-order',  'viewManualDebitNote')
    ->name('admin.viewDebitNotePurchaseOrder');
    Route::post('/admin/manual-debit-note/convert',  'saveManualDebitNoteCustomer')
    ->name('admin.saveManualDebitNoteCustomer');
    Route::get('/admin/purchase-debit-note', 'showPurchaseDebitNoteList')
    ->name('purchase.debit.note.list');
    Route::post('/admin/manual-debit-note/save', 'saveManualDebitNote')
    ->name('admin.saveManualDebitNote');
    Route::get('debit-invoice/pdf/{id}',  'downloadDebitInvoicePDF')->name('debit.invoice.pdf');
    Route::get('/export-credit-note-invoice/{id}',  'exportCreditNoteInvoice')->name('admin.export.credit_note_invoice');

    
    Route::get('/export/debit-note-invoice/{id}', 'exportDebitNoteInvoice')->name('debit.note.export');
    Route::get('/credit-note/pdf/{id}', 'downloadCreditNoteInvoicePDF')->name('admin.credit_note.download_pdf');
    
    Route::get('/admin/btr-pdf/{invoiceId}', 'downloadBtrPdf')->name('admin.download.btrpdf');
    Route::get('/supply-order-export/{id}',  'exportSupplyOrder')->name('admin.supplyOrder.export');
    
    Route::get('/admin/export-all-supply-orders',  'exportAllSupplyOrders')->name('admin.supplyOrder.export.all');

    //Route::get('/purchase-invoice/download-pdf/{id}', 'downloadPurchaseInvoicePdf')->name('purchase.invoice.download.pdf');
    Route::get('/purchase-invoice/send-pdf-whatsapp/{id}', 'sendPurchaseInvoicePdfOnWhatsApp')
    ->name('purchase.invoice.send.whatsapp');
    
    Route::get('/admin/invoice/update-from-challans/{invoiceId}',  'updateInvoiceFromChallans')->name('invoice.updateFromChallans');

    Route::get('/admin/send-reward-whatsapp-test/{invoiceId}', 'sendLogisticRewardWhatsAppNotification')->name('admin.reward.whatsapp.test');

    
    Route::get('/sales/invoiceorder/{id}', 'getInvoiceOrderPdfUrl')->name('getInvoiceOrderPdfUrl');

    Route::get('/admin/edit-manual-purchase/{id}', 'editManualPurchaseOrder')->name('admin.editManualPurchaseOrder');
    
    Route::post('/admin/save-edited-manual-po',  'saveManualPurchaseOrderEdit')->name('admin.saveEditedManualPO');

    Route::post('/admin/inventory/remove-product',  'removeProductFromInventory')->name('admin.inventory.remove.product');
    
    Route::get('/print-label', 'show')->name('label.print');
    //Route::get('/print-barcode',  'generateBarcodePdf')->name('print.barcode');

    Route::get('/test/force-invoice-wa',  'testForcefullyCreatedInvoiceWhatsApp');
    
    Route::post('/purchase-orders/{invoiceId}/force-invoice-notice', 'sendForcefullyCreatedInvoiceNotification')->name('purchase_orders.force_invoice_notice');
    
    Route::get('/invoices/{invoice}/force-notify', 'sendForcefullyCreatedInvoiceNotification')
    ->name('invoices.force_notify');
    
    Route::get('/invoice/update-from-challan/{invoiceId}',  'updateFromChallans')->name('invoice.update.from.challan');
    
    
    // GET route (manual trigger/button/cron-style)
    Route::get('/manager41/inventory/sync', 'manager41InventoryEntry')
        ->name('manager41.inventory.sync');
        
        Route::get('/manager-41/challan/{id}/pdf',  'manager41ChallanPdf')
        ->name('manager41.challan.pdf');


    Route::get('/invoices/{invoice}/challan-pdf-url', 'getChallanPdfURLByInvoice')
         ->name('invoices.challan.pdf.url'); // no middleware
         
         Route::get('/forced-challan-pdf/{challanId}', 'getForcedChallanPdfURL')
    ->name('challan.forced.pdf');
    
    Route::get('/force-challan-notify/{challanId}',  'sendForcefullyCreatedChallanNotification')
    ->name('force.challan.notify');
    
   Route::get('/orders/replace-preclose-item', 'replacePrecloseItem')
     ->name('order.replacePrecloseItem');


});

Route::controller(BusyExportController::class)->group(function () {
   
    Route::get('/busy-export', 'index')->name('busy.export');
    Route::get('/busy-export-download','exportBusyFormat')->name('busy.export.download');

});

Route::controller(ImportCommercialInvoiceController::class)->group(function () {
   
     // Import Companies Ã¢â‚¬â€œ list + add
  // Import Companies - List
    Route::get('/import-companies',  'importCompaniesIndex')
        ->name('import_companies.index');

    // Import Companies - Add New
    Route::get('/import-companies/create', 'importCompanyCreate')
        ->name('import_companies.create');

    // Import Companies - Store
    Route::post('/import-companies',  'importCompanyStore')
        ->name('import_companies.store');
        
        
        Route::get('/import-companies/create', [ImportCommercialInvoiceController::class, 'importCompanyCreate'])
    ->name('import_companies.create');

    Route::get('/import/ajax/states', [ImportCommercialInvoiceController::class, 'getStatesByCountry'])
        ->name('import.ajax.states');
    
    Route::get('/import/ajax/cities', [ImportCommercialInvoiceController::class, 'getCitiesByState'])
        ->name('import.ajax.cities');
        
        
        Route::get('/import-companies/{company}/edit', [ImportCommercialInvoiceController::class, 'importCompanyEdit'])
    ->name('import_companies.edit');
    
    Route::put('/import-companies/{company}', [ImportCommercialInvoiceController::class, 'importCompanyUpdate'])
        ->name('import_companies.update');
        
    Route::delete('/import-companies/{company}', [ImportCommercialInvoiceController::class, 'importCompanyDestroy'])
    ->name('import_companies.destroy');
    
    Route::get('import/companies/check-gstin', [ImportCommercialInvoiceController::class, 'checkGstin'])
    ->name('import.ajax.check_gstin');
    
    Route::get('import/suppliers', [ImportCommercialInvoiceController::class, 'suppliersIndex'])
    ->name('import_suppliers.index');
    
    
    Route::get('import/suppliers/create', [ImportCommercialInvoiceController::class, 'supplierCreate'])
        ->name('import_suppliers.create');
    
    Route::post('import/suppliers', [ImportCommercialInvoiceController::class, 'supplierStore'])
        ->name('import_suppliers.store');
        
   Route::post('import/bl-details', 'storeBlDetail')
    ->name('import_bl_details.store');
    
    Route::get('/import/bl-details', [ImportCommercialInvoiceController::class, 'blDetailsIndex'])
    ->name('import_bl_details.index');
    
    // ðŸ”¹ Pending BL listing
    Route::get('/import/bl-details/pending', [ImportCommercialInvoiceController::class, 'pendingBlIndex'])
        ->name('import_bl_details.pending');
        
        // âœ… NEW: Pending BL show page
    Route::get('/import/bl-details/pending/{bl}', 
        [ImportCommercialInvoiceController::class, 'pendingBlShow']
    )->name('import_bl_details.pending.show');
    
    // âœ… NEW: Bill of Entry upload
    Route::post('/import/bl-details/pending/{bl}/bill-of-entry', 
        [ImportCommercialInvoiceController::class, 'uploadBillOfEntry']
    )->name('import_bl_details.pending.bill_of_entry.upload');
    
    // COMPLETED BL LIST + SHOW
    Route::get('import/bl-details/completed', [
        ImportCommercialInvoiceController::class,
        'completedBlIndex'
    ])->name('import_bl_details.completed');
    
    Route::get('import/bl-details/completed/{bl}', [
        ImportCommercialInvoiceController::class,
        'completedBlShow'
    ])->name('import_bl_details.completed.show');
    
    
    // âœ… NEW: Separate downloads
    Route::get('/import/bl/pending/{blId}/download-ci-zip', [ImportCartController::class, 'downloadCiZipForBl'])
        ->name('import_bl_details.pending.download_ci_zip');
    
    Route::get('/import/bl/pending/{blId}/download-pl-zip', [ImportCartController::class, 'downloadPlZipForBl'])
        ->name('import_bl_details.pending.download_pl_zip');
    
    // Import BL Ã¢â€ â€™ Product listing
    Route::get('/import/bl/{bl}/products', [ImportCommercialInvoiceController::class, 'importProductsList'])
        ->name('import_bl.products.list');
    
    // (Optional) Add to cart for selected products (you can fill logic later)
    Route::post('/import/bl/{bl}/products/add-to-cart', [ImportCommercialInvoiceController::class, 'importProductsAddToCart'])
        ->name('import_bl.products.add_to_cart');
        
        Route::get('import/products/search', [ImportCommercialInvoiceController::class, 'ajaxSearchProducts'])
    ->name('import.products.search');
    
    Route::get('import/ajax/products-search',
        [ImportCommercialInvoiceController::class, 'importProductsAjaxSearch']
    )->name('import.ajax.products_search');
    
    Route::get('import/ci', 'ciIndex')
            ->name('import_ci.index');
            
            Route::get('/import/ci/{ci}', [ImportCommercialInvoiceController::class, 'ciShow'])
    ->name('import_ci.show');
    
    // 🔹 NEW: edit + update
    Route::get('/suppliers/{supplier}/edit',    [ImportCommercialInvoiceController::class, 'supplierEdit'])->name('import_suppliers.edit');
    Route::put('/suppliers/{supplier}',         [ImportCommercialInvoiceController::class, 'supplierUpdate'])->name('import_suppliers.update');
    
    Route::get('import/bl-details/{bl}/items-poster-pdf', [ImportCommercialInvoiceController::class, 'downloadBlItemsPosterPdf'])
    ->name('import.bl-details.items_poster_pdf');
    
    Route::get('import/bl-details/{bl}/items-poster-doc', [ImportCommercialInvoiceController::class, 'downloadBlItemsPosterWord'])
    ->name('import.bl-details.items_poster_doc');
});


Route::prefix('admin/import')->group(function () {
    // Cart listing (per BL)
    Route::get('cart/{bl}', [ImportCartController::class, 'index'])
        ->name('import.cart.index');

    // Add to cart (product listing se)
    Route::post('cart/add', [ImportCartController::class, 'add'])
        ->name('import.cart.add');

    // Update quantities
    Route::post('cart/update', [ImportCartController::class, 'update'])
        ->name('import.cart.update');

    // Remove single item
    Route::post('cart/remove', [ImportCartController::class, 'remove'])
        ->name('import.cart.remove');

    // Clear cart for BL
    Route::post('cart/clear', [ImportCartController::class, 'clear'])
        ->name('import.cart.clear');
    Route::post('import/cart/update-row/{cart}', [ImportCartController::class, 'updateRow'])
    ->name('import.cart.update_row');
    
    // Ã°Å¸â€˜â€° NEW: proceed to BL Items + CI Details + CI Item Details
    Route::post('cart/proceed', [ImportCartController::class, 'proceed'])
        ->name('import.cart.proceed');
        
    Route::get('bl-details/{bl}/ci-summary', [ImportCartController::class, 'blCiSupplierSummary'])
    ->name('import_bl.ci_supplier_summary');
        
    Route::post('import/bl-details/{bl}/ci-summary/update', [ImportCartController::class, 'updateCiSupplierSummary'])
    ->name('import.bl.ci_summary.update');

    Route::get('import/bl-details/{bl}/ci-summary/complete', [ImportCartController::class, 'completeCiForBl'])
    ->name('import.bl.ci_summary.complete');
});



Route::controller(WarrantyClaimController::class)->group(function () {

    // Lists
    Route::get('claims/pending',  'pendingList')->name('claims.pending');
    Route::get('claims/approved', 'approvedList')->name('claims.approved');
    Route::get('claims/rejected', 'rejectedList')->name('claims.rejected');

    // NEW: Draft list
    Route::get('claims/draft',    'draftListing')->name('claims.draft');

    // Details page (fixed paths ke baad hi rakho)
    Route::get('claims/{id}', 'show')->whereNumber('id')->name('claims.show');

    // Claim-level actions
    Route::post('claims/{claim}/approve', 'approve')->whereNumber('claim')->name('claims.approve');
    Route::post('claims/{claim}/reject',  'reject')->whereNumber('claim')->name('claims.reject');
    Route::post('claims/{claim}/draft',   'draft')->whereNumber('claim')->name('claims.draft.update');

    // Item-level actions
    Route::post('claims/details/{detail}/approve', 'approveDetail')->whereNumber('detail')->name('claims.details.approve');
    Route::post('claims/details/{detail}/reject',  'rejectDetail')->whereNumber('detail')->name('claims.details.reject');
    
    Route::post('claims/{claim}/save', 'approveFromDraft')->name('claims.save');
    
    Route::get('claims/{claim}/complete-to-invoice', 'completeToInvoice')
     ->whereNumber('claim')
     ->name('claims.complete_invoice');
     
     Route::get('claims/{claim}/credit-note/service',
         'warrantyCreditNoteService'
    )->whereNumber('claim')->name('claims.credit_note.service');
    
    Route::get('claims/completed', 'completedList')->name('claims.completed');
    
    Route::get('/send-warranty',  'sendWarrantyClaimCreatedWA');
    
    
    Route::get('/claims/{claim}/to-suborder', [WarrantyClaimController::class, 'warrantyClaimToSubOrder'])
    ->name('claims.to_suborder');
});



Route::controller(ZohoController::class)->group(function () {
    Route::get('/zoho/connect', 'redirectToZoho');
    Route::get('/zoho/callback', 'handleZohoCallback');

    //testing api start
    Route::get('/zoho/test','callZohoApi');
    Route::get('/zoho/test-api',  'testZohoApi');
    //testing api end

  // zoho create invoice api
    Route::get('/create-zoho-invoice/{invoiceId}','createInvoice')->name('zoho.invoice.create');// create Invoice
    Route::put('/zoho/invoices/{invoiceId}','updateInvoice');
    Route::get('/zoho/list-invoices',  'listInvoices');
    Route::get('/zoho/invoices/{invoiceId}',  'getInvoice');
    Route::delete('/zoho/invoices/{invoiceId}',  'deleteInvoice');
    Route::post('/zoho/invoices/{invoiceId}/status/sent',  'markAsSent');
    //create invoice in zoho whose zoho_invoice_id is null
    Route::get('/zoho-pending-invoice-sync',  'syncPendingInvoicesToZoho')->name('zoho.invoice.sync');

  // Bulk Update Address table and Product Table 
     Route::get('/zoho/update-all-addresses',  'updateAllZohoCustomersInAddresses');
     
  // customer API
    Route::get('/zoho-create-contact/{party_code}',  'createNewCustomerInZoho');
    Route::get('/zoho-create-bulk-customers',  'bulkCreateZohoCustomers')->name('zoho.create.bulk.customers');
    Route::get('/zoho/customer/update/{zoho_contact_id}', [ZohoController::class, 'updateCustomerInZoho'])->name('zoho.customer.update');
    
    Route::get('/zoho/update-missing-gstin', [ZohoController::class, 'bulkUpdateMissingGstinCustomers']);


   // seller createion api
	
	Route::get('/create-zoho-seller/{user_id}',  'createNewSellerInZoho')->name('zoho.create_seller');
	Route::get('/bulk-create-zoho-sellers','bulkCreateSellersInZoho')->name('zoho.bulk_create_sellers');
	

  // Item API 
  	//Route::get('/zoho/item-push', 'newItemPushInZoho')->name('zoho.item.push');
  	Route::get('/zoho/new-item-push/{part_no}',  'newItemPushInZoho')->name('zoho.item.push');
    //bulk item push shift when zoho id is null
  	Route::get('/zoho/items/push-bulk', 'pushBulkItem')->name('zoho.items.push.bulk'); 
  	Route::get('/zoho/items/update/{part_no}',  'updateItemInZoho')->name('zoho.items.update');
    //Route::get('/zoho/update-items', 'updateAllZohoItemsInProducts');// bulk update item_id

  // E-way api
   
    // Fetch list of transporters
    Route::get('/zoho/ewaybill/transporters', 'getZohoTransporters')->name('zoho.ewaybill.transporters');
    Route::get('/zoho/transporters', 'getTransporters')->name('zoho.getTransporters');
    // Add new vehicle to e-Way Bill
    Route::get('/zoho/ewaybill/{ewaybillId}/add-vehicle',  'addVehicleToEWayBill')->name('zoho.ewaybill.addvehicle');
    //  e-Way Bill Generation
    Route::get('/zoho-generate-ewaybill',  'generateZohoEWayBill')->name('zoho.generate.ewaybill');
    //  Cancel e-Way Bill
    Route::get('/zoho/ewaybill/{ewaybillId}/cancel',  'cancelEWayBill')->name('zoho.ewaybill.cancel');
    //  Delete e-Way Bill
    Route::get('/zoho/ewaybill/{ewaybillId}', 'deleteEWayBill')->name('zoho.ewaybill.delete');
    //  Get All Dispatch Addresses
    Route::get('/zoho-dispatch-addresses', 'getDispatchAddresses')->name('zoho.dispatch.addresses');
    //  Add a New Dispatch Address
    Route::get('/zoho-dispatch-address-add', 'addDispatchAddressToZoho')->name('zoho.dispatch.address.add');
    //  Delete a Dispatch Address
    Route::get('/zoho-dispatch-address-delete/{dispatchId}', 'deleteDispatchAddressFromZoho')->name('zoho.dispatch.address.delete');
	
	// bill api
	Route::get('/zoho/bills','listZohoBills')->name('zoho.bills.list');
	Route::get('/zoho/bill/create',  'createVendorBill')->name('zoho.create.bill');
	Route::get('/zoho/bill/create/{purchase_invoice_id}', 'createVendorBill')->name('zoho.create.bill'); // this is for testing
	Route::get('/zoho/bulk-create-bills',  'bulkCreateVendorBills')->name('zoho.bills.bulk.create');
	Route::get('/zoho/bill/{billId}/attach', [ZohoController::class, 'attachFileToBill']);
	
	// tax api
	Route::get('/zoho/taxes',  'getZohoTaxes')->name('zoho.getTaxes');
	Route::get('/zoho/sync-taxes',  'syncZohoTaxes')->name('zoho.syncTaxes');


  // E-Invoice api
    // Ã¢Å“â€¦ Push e-Invoice to IRP (Generate IRN)
    Route::get('/zoho/einvoice/push/{zoho_invoice_id}', 'pushEInvoiceToIRP')->name('zoho.einvoice.push');
    // Ã¢Å“â€¦ Cancel IRN within 24 hours
    Route::get('/zoho/einvoice/cancel/{zoho_invoice_id}', 'cancelIRNWithin24Hrs')->name('zoho.einvoice.cancel');
    // Ã¢Å“â€¦ Cancel IRN after 24 hours
    Route::get('/zoho/einvoice/cancel-manual/{zoho_invoice_id}', 'cancelIRNManuallyAfter24Hrs')->name('zoho.einvoice.cancel.manual');
	
    Route::get('/zoho/invoice/einvoice-eway-sync', 'syncEinvoiceAndEwayFromZoho')->name('zoho.invoice.sync.einvoice_eway');
    
    Route::get('/invoice/cancel', 'cancelInvoiceChallans')->name('invoice.cancel');
	
	// Credit note api
	//Route::get('/zoho/credit-note',  'createZohoCreditNote')->name('zoho.credit_note.create');
	Route::get('/zoho/creditnote/{id}', 'getZohoCreditNote');
	Route::get('/process-credit-notes', 'processPendingCreditNotes');
	Route::get('/zoho/credit-note/{invoiceId}', [ZohoController::class, 'createZohoCreditNote'])->name('zoho.credit_note.create');
	Route::get('/zoho/service-credit-note/{invoiceId}', [ZohoController::class, 'createZohoServiceCreditNote'])->name('zoho.service_credit_note.create');
	Route::get('/zoho/creditnote/push-irp/{zoho_creditnote_id}',  'pushCreditNoteToIRP')->name('zoho.creditnote.push_irp');
	Route::get('/zoho/creditnote/cancel-irp/{creditnote_id}', [ZohoController::class, 'cancelCreditNoteIRNWithin24Hrs']);

    //vendor credit api
    Route::get('/zoho/vendor-credit/create', [ZohoController::class, 'createVendorCreditFullPayload'])->name('zoho.vendor_credit.create'); // static function
    Route::get('/zoho/vendor-credit/seller/{debitNoteId}', 'createVendorCreditFromSellerForGoodsOrService')
    ->name('zoho.vendor.credit.seller');
    Route::get('/zoho/vendor-credits',  'getVendorCredits')->name('zoho.vendor.credits');
    //debit note
    Route::get('/zoho/customer-debit-note/{debitNoteId}',  'createVendorCreditFromCustomerForGoodsOrService')
    ->name('zoho.customer.debitnote.create');
    
    // chart of account api
    Route::get('/zoho/chart-of-accounts',  'getChartOfAccounts')
    ->name('zoho.chart.of.accounts');
    Route::get('/zoho/chart-of-accounts/sync', 'syncChartOfAccounts')->name('zoho.chartofaccounts.sync');
    
    Route::get('/zoho/create-eway-transporter',  'createEwayBillTransporter');

    Route::get('/admin/zoho/update-vendor-bill/{purchase_invoice_id}', 'updateVendorBill')
     ->name('admin.zoho.updateVendorBill');

    Route::get('/zoho/get-statement', 'getStatement')->name('getStatement');

    Route::get('/zoho/get-merge-seller-and-customer-statement', 'getMergeSellerAndCustomerStatement')->name('getMergeSellerAndCustomerStatement');
    
    Route::get('/test-zoho-mark-as-lost/{id}',  'zoho_mark_as_lost')->name('zoho.mark_as_lost.test');
    
    // If it's for web
    Route::get('/zoho/send-failure-alert',  'sendZohoSyncFailureNotification')->name('zoho.send.failure.alert');
    
    
    Route::get('/test/zoho-failure-alert', [App\Http\Controllers\ZohoController::class, 'testZohoFailureNotification']);   


	  Route::get('/zoho/update-registered-contacts', [ZohoController::class, 'updateZohoRegisteredUnregisteredAddressesOnly']);

    Route::get('/zoho/payment-auth', [ZohoController::class, 'startZohoPaymentAuth']);
    Route::post('/zoho/payment-link', [ZohoController::class, 'createPaymentLink'])->name('zoho.payment.createPaymentLink');
    Route::get('/zoho/payment-callback', [ZohoController::class, 'paymentCallback'])->name('zoho.payment.paymentCallback');
    Route::get('/zoho/after-payment-redirect', [ZohoController::class, 'afterPaymentRedirect'])->name('zoho.payment.afterPaymentRedirect');
    
    Route::get('/zoho/test-attachment-upload', [ZohoController::class, 'testStaticAttachmentUpload'])
    ->name('zoho.test_attachment_upload');
    
   
    
    Route::get('/zoho/invoices-without-attachments', [ZohoController::class, 'listInvoicesWithoutZohoAttachment'])
    ->name('zoho.invoices_without_attachments');
    
    Route::get('/zoho/bulk-upload-attachments', [ZohoController::class, 'startBulkZohoAttachmentJob'])
    ->name('zoho.bulk_upload_attachments');

    
});

Route::controller(RewardReminderController::class)->group(function () {
    
    
    // GET: build/update the unpaid/partially-paid worklist
        Route::get('/insertEarlyPaymentRemainders', 'insertEarlyPaymentRemainders')
            ->name('insertEarlyPaymentRemainders');

        // GET: queue WhatsApp reminders for todayÃ¢â‚¬â„¢s due gates
        Route::get('/sendEarlyPaymentWhatsApp', 'sendEarlyPaymentWhatsApp')
            ->name('sendEarlyPaymentWhatsApp');
            
        Route::get('/sendEarlyPaymentWhatsAppOnButtonClick/{party_code}', 'sendEarlyPaymentWhatsAppOnButtonClick')
            ->name('sendEarlyPaymentWhatsAppOnButtonClick');
            
            
    Route::get('/rewards/claim/{partyCode}', 'claimReward')
         ->name('rewards.claim');
         
    Route::get('/rewards/send-claim-whatsapp', 'sendClaimRewardWhatsAppBulk')
             ->name('rewards.sendClaimRewardWhatsAppBulk');
    Route::get('/early-payment-pdf/{party_code}', 'generatePartyEarlyPaymentPdf')->name('admin.early_payment.pdf');
    Route::get('/early-payment-reminders', 'earlyPaymentReminderIndex')
    ->name('admin.early_payment.reminders');
    
    
    
    // DOWNLOAD (forces file download)
    Route::get('/admin/early-payment/pdf-download/{party_code}',
        [RewardReminderController::class, 'onlyEarlyPaymentPdfDownload']
    )->name('admin.early_payment.pdf_download');
    
    
    Route::get('/notifyCustomersEarlyRewardToAllManagers', 'notifyCustomersEarlyRewardToAllManagers')
    ->name('admin.notifyCustomersEarlyRewardToAllManagers');
    
    
    Route::get('/head-manager-warehouse-report', 'generateAndSendHeadManagersWarehouseEarlyPayment')
     ->name('manager.warehouse.report');
   
   Route::get('/admin/reward/claim-wa/{partyCode}', 'sendClaimRewardWhatsAppForParty')
     ->name('reward.claim.whatsapp.single');
             
             

});


Route::controller(ManagerClientPurchaseNotify::class)->group(function () {
    
    Route::get('/manager-client-purchase-notify', 'generateAll')
        ->name('manager.client.purchase.notify');

});

// routes/web.php
Route::get('/manager41/orders/{id}/proforma', [InvoiceController::class, 'invoice_download_manager41'])
    ->name('manager41.invoice.download');
    

Route::controller(CloudResponseController::class)->group(function () {
    
   Route::get('/admin/cloud-responses/purge-old', 'purgePreviousQuarterCloudResponses')->name('cloudResponses.purgeOld');
   
  Route::post('/archive-previous-quarter-carts',  'archivePreviousQuarterCartsToSaveForLater')
     ->name('carts.archive.previous_quarter');

});

Route::controller(WhatsaapCarousel::class)->group(function () {
    // Show form
    Route::get('wa/carousel/create-template-form', 'createTemplateForm')
        ->name('wa.carousel.create-template.form');

    // Submit form Ã¢â€ â€™ create & submit template
    Route::post('wa/carousel/create-template', 'createTemplate')
        ->name('wa.carousel.create-template');
        
        Route::get('wa/carousel/templates',  'listPage')
    ->name('wa.carousel.templates');
    
    Route::get('wa/carousel/send-form',  [WhatsaapCarousel::class, 'sendForm'])->name('wa.carousel.send.form');
    Route::post('wa/carousel/send', [WhatsaapCarousel::class, 'sendFromForm'])->name('wa.carousel.send');
    
    Route::get('wa/get-managers-by-warehouse',  'getManagersByWarehouse')
    ->name('wa.getManagersByWarehouse');
    
     Route::get('wa/get-states-by-manager', 'getStatesByManager')->name('wa.getStatesByManager');
     
     
     // AJAX #1: insert rows only (returns group_id)
    Route::post('wa/carousel/send-ajax', 'sendFromFormAjax')->name('wa.carousel.send.ajax');

    // AJAX #2: dispatch job for a given group
    Route::post('wa/carousel/dispatch-group', 'dispatchCarouselGroup')->name('wa.carousel.dispatch');
    
    Route::get('wa/find-product-by-partno', 'findProductByPartNo')->name('wa.find.product.by.partno');
    
    
    Route::get('/mm-lite/es', function () {
        return view('mm_lite_es'); // resources/views/mm_lite_es.blade.php testing 
    })->name('mm.lite.es');


    Route::post('/wa/carousel/template/delete',  'deleteTemplate')
    ->name('wa.carousel.template.delete');  
    
    
    Route::get('wa/carousel/edit-template', 'editTemplateForm')
    ->name('wa.carousel.edit-template.form');

    // EDIT: submit changes
    Route::post('wa/carousel/edit-template', 'updateTemplate')
        ->name('wa.carousel.edit-template.update');
    
    

   
});


Route::controller(EwayController::class)->group(function () {

	
	


  // Fetch list of transporters
  //Route::get('/zoho/ewaybill/transporters', 'getZohoTransporters')->name('zoho.ewaybill.transporters');

  // Add new vehicle to e-Way Bill
  //Route::get('/zoho/ewaybill/{ewaybillId}/add-vehicle',  'addVehicleToEWayBill')->name('zoho.ewaybill.addvehicle');

  // Ã¢Å“â€¦ e-Way Bill Generation
  //Route::get('/zoho-generate-ewaybill',  'generateZohoEWayBill')->name('zoho.generate.ewaybill');

  // Ã¢Å“â€¦ Cancel e-Way Bill
  //Route::get('/zoho/ewaybill/{ewaybillId}/cancel',  'cancelEWayBill')->name('zoho.ewaybill.cancel');

  // Ã¢Å“â€¦ Delete e-Way Bill
  //Route::get('/zoho/ewaybill/{ewaybillId}', 'deleteEWayBill')->name('zoho.ewaybill.delete');

  // Ã¢Å“â€¦ Get All Dispatch Addresses
  //Route::get('/zoho-dispatch-addresses', 'getDispatchAddresses')->name('zoho.dispatch.addresses');

  // Ã¢Å“â€¦ Add a New Dispatch Address
  //Route::get('/zoho-dispatch-address-add', 'addDispatchAddressToZoho')->name('zoho.dispatch.address.add');

  // Ã¢Å“â€¦ Delete a Dispatch Address
  //Route::get('/zoho-dispatch-address-delete/{dispatchId}', 'deleteDispatchAddressFromZoho')->name('zoho.dispatch.address.delete');

  // Ã¢Å“â€¦ Calculate Distance using Google API
  //Route::get('/calculate-distance', 'calculateDistance')->name('calculate.distance');




});

Route::controller(NotificationAndCronJobController::class)->group(function () {
  Route::get('/notifications',  'getNotifications')->name('getNotifications');
  Route::get('/add-notification',  'addNotifications')->name('addNotifications');
  Route::get('/add-notification',  'addNotifications')->name('addNotifications');
  Route::post('/submit-notification',  'submitNotifications')->name('submitNotifications');
  Route::get('/delete-notification',  'deleteNotification')->name('deleteNotification');
  Route::get('managers-list-by-warehouse', 'getManagersByWarehouse')->name('ajax.managersListByWarehouse');
  Route::get('ajax-categories', 'categories')->name('ajax.categories');
  Route::get('ajax-brands', 'brands')->name('ajax.brands');
  Route::get('ajax-product-by-partno', 'findByPartNo')->name('ajax.product.by.partno');

  Route::get('/cron-jobs',  'getCronJobs')->name('getCronJobs');


});

Route::controller(CronJobController::class)->group(function () {
  Route::get('/auto-work',  'startCronJob')->name('startCronJob');
});