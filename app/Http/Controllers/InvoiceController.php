<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Language;
use App\Models\Order;
use App\Models\User;
use App\Models\Address;
use Config;
use Hash;
use PDF;
use Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\WhatsAppWebService;
use Illuminate\Support\Facades\Auth;

use App\Models\OwnBrandCart;
use App\Models\OwnBrandProduct;
use App\Models\OwnBrandOrder;
use App\Models\OwnBrandOrderDetail;
use App\Models\OwnBrandOrderApproval;
use App\Models\InvoiceOrder;
use App\Http\Controllers\PurchaseOrderController;
use App\Jobs\GenerateStatementPdf;

use App\Models\Manager41SubOrder;
use App\Models\Manager41SubOrderDetail;
use App\Models\Manager41Order;
use App\Models\Manager41OrderDetail;

use App\Services\PdfContentService;

class InvoiceController extends Controller {
  protected $whatsAppWebService;


   // Helper function to get head manager phone number based on warehouse location
    private function getHeadManagerPhone($warehouseId)
    {
        switch ($warehouseId) {
            case 1: // Kolkata
                return $this->getManagerPhone(180);
            case 2: // Delhi
                return $this->getManagerPhone(25606);
            case 6: // Mumbai
                return $this->getManagerPhone(169);
            default:
                return null; // Default case if warehouse does not match
        }
    }


  private function getManagerPhone($managerId)
  {
      $managerData = DB::table('users')
          ->where('id', $managerId)
          ->select('phone')
          ->first();

      return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
  }

  public function open_invoice_download($hash) {
    $hash = base64_decode($hash);
    parse_str($hash, $params);
    if (Session::has('currency_code')) {
      $currency_code = Session::get('currency_code');
    } else {
      $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
    }
    $language_code = Session::get('locale', Config::get('app.locale'));

    if (Language::where('code', $language_code)->first()->rtl == 1) {
      $direction      = 'rtl';
      $text_align     = 'right';
      $not_text_align = 'left';
    } else {
      $direction      = 'ltr';
      $text_align     = 'left';
      $not_text_align = 'right';
    }

    if ($currency_code == 'BDT' || $language_code == 'bd') {
      // bengali font
      $font_family = "'Hind Siliguri','sans-serif'";
    } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
      // khmer font
      $font_family = "'Hanuman','sans-serif'";
    } elseif ($currency_code == 'AMD') {
      // Armenia font
      $font_family = "'arnamu','sans-serif'";
      // }elseif($currency_code == 'ILS'){
      //     // Israeli font
      //     $font_family = "'Varela Round','sans-serif'";
    } elseif ($currency_code == 'AED' || $currency_code == 'EGP' || $language_code == 'sa' || $currency_code == 'IQD' || $language_code == 'ir' || $language_code == 'om' || $currency_code == 'ROM' || $currency_code == 'SDG' || $currency_code == 'ILS' || $language_code == 'jo') {
      // middle east/arabic/Israeli font
      $font_family = "'Baloo Bhaijaan 2','sans-serif'";
    } elseif ($currency_code == 'THB') {
      // thai font
      $font_family = "'Kanit','sans-serif'";
    } else {
      // general for all
      $font_family = "'Roboto','sans-serif'";
    }

    // $config = ['instanceConfigurator' => function($mpdf) {
    //     $mpdf->showImageErrors = true;
    // }];
    // mpdf config will be used in 4th params of loadview

    $config = [];

    $order = Order::findOrFail($params['id']);

    $pdfContentService = new PdfContentService();
    $pdfContentBlock   = $pdfContentService->buildBlockForType('order');

    if (Hash::check($params['hash'], Hash::make($order['combined_order_id'] . $order['user_id']))) {
      if (mb_substr($order->code, 0, 3) == 'MZ/') {
        return PDF::loadView('backend.invoices.invoice', [
          'order'          => $order,
          'font_family'    => $font_family,
          'direction'      => $direction,
          'text_align'     => $text_align,
          'not_text_align' => $not_text_align,
          'pdfContentBlock'  => $pdfContentBlock,
        ], [], $config)->download('order-' . $order->code . '.pdf');
      } else {
        return PDF::loadView('backend.invoices.proformainvoice', [
          'order'          => $order,
          'font_family'    => $font_family,
          'direction'      => $direction,
          'text_align'     => $text_align,
          'not_text_align' => $not_text_align,
          'pdfContentBlock'  => $pdfContentBlock,
        ], [], $config)->download('order-' . $order->code . '.pdf');
      }
    } else {}
  }

    private function isActingAs41Manager(): bool
    {

        
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Normalize
        $title = strtolower(trim((string) $user->user_title));
        $type  = strtolower(trim((string) $user->user_type));

        // Exactly check for manager_41 on current login
        if ($title === 'manager_41' || $type === 'manager_41') {
            return true;
        }

        // (Optional) tolerate common variants
        $aliases = ['41_manager'];
        return in_array($title, $aliases, true);
    }

  public function invoice_download_manager41($id)
    {
        // ── currency & language → direction & font ─────────────────────────────
        $currency_code = Session::get(
            'currency_code',
            Currency::findOrFail(get_setting('system_default_currency'))->code
        );
        $language_code = Session::get('locale', Config::get('app.locale'));
        $rtl = optional(Language::where('code', $language_code)->first())->rtl == 1;

        $direction      = $rtl ? 'rtl' : 'ltr';
        $text_align     = $rtl ? 'right' : 'left';
        $not_text_align = $rtl ? 'left'  : 'right';

        $resolveFont = function (string $cur, string $lang): string {
            if ($cur === 'BDT' || $lang === 'bd') return "'Hind Siliguri','sans-serif'";
            if ($cur === 'KHR' || $lang === 'kh') return "'Hanuman','sans-serif'";
            if ($cur === 'AMD')                  return "'arnamu','sans-serif'";
            if (in_array($cur, ['AED','EGP','IQD','ROM','SDG','ILS'], true)
                || in_array($lang, ['sa','ir','om','jo'], true)) {
                return "'Baloo Bhaijaan 2','sans-serif'";
            }
            if ($cur === 'THB') return "'Kanit','sans-serif'";
            return "'Roboto','sans-serif'";
        };
        $font_family = $resolveFont($currency_code, $language_code);

        // ── fetch order with needed relations ───────────────────────────────────
        $order = Manager41Order::with([
            'user:id,company_name',
            'orderDetails.product:id,name',
            'carrier:id,name', 
        ])->findOrFail($id);

        
        $vmUser = (object)[
            'company_name' => $order->user->company_name ?? 'ACE TOOLS PRIVATE LIMITED',
        ];

        
        $toCompany = null;
        if (!empty($order->shipping_address)) {
            $addr = is_string($order->shipping_address)
                ? json_decode($order->shipping_address, true)
                : $order->shipping_address;
            if (json_last_error() === JSON_ERROR_NONE && is_array($addr)) {
                $toCompany = $addr['company_name'] ?? ($addr['name'] ?? null);
            }
        }
        $toCompany = $toCompany ?: ($order->user->company_name ?? '-');

        
        $orderDoc = (object)[
            'order_no'        => $order->code,                      // Order No.
            'created_at'      => $order->created_at,                // Date
            'transport_name'  => $order->carrier->name ?? ($order->shipping_type ?? 'Aps parcel carriers'),
            'to_company_name' => $toCompany,
            'no_of_ctn'       => null,                              
            'mc_name'         => 'MUMBAI',                          
            'user'            => $vmUser,
        ];

        
        $items = collect($order->orderDetails)->map(function ($d) {
            $qty  = (int)   ($d->approved_quantity ?? $d->quantity ?? 0);
            $rate = (float) ($d->approved_rate ?? (($d->quantity ?? 0) ? ($d->price / max(1, $d->quantity)) : ($d->price ?? 0)));
            $amt  = (float) ($d->final_amount ?? ($rate * $qty));

            return (object)[
                'product_data' => (object)[
                    'name' => optional($d->product)->name ?? '-',
                ],
                'variation'    => $d->variation ?? null,
                'quantity'     => $qty,
                'rate'         => $rate,
                'final_amount' => $amt,
            ];
        });

        // echo "<pre>";
        // print_r($items);
        // die();

        // Totals
        $totalQty = (int) $items->sum('quantity');
        $subTotal = (float) $items->sum(fn($x) => (float) $x->final_amount);
        
        // ✅ Use the items sum for proforma so it matches the table
        $grandTotal = $subTotal;
        
        // Amount in words (now matches)
        $amountWords = $this->toIndianWordsSafe($grandTotal);

        // ── Render ORDER template (Manager-41) ──────────────────────────────────
        return PDF::loadView('backend.invoices.manager41.manager_41_performainvoice', [
            'orderDoc'        => $orderDoc,
            'items'           => $items,
            'totalQty'        => $totalQty,
            'subTotal'        => $subTotal,
            'grandTotal'      => $grandTotal,
            'amountWords'     => $amountWords,
            'font_family'     => $font_family,
            'direction'       => $direction,
            'text_align'      => $text_align,
            'not_text_align'  => $not_text_align,
        ])->download('order-' . $order->code . '.pdf');
    }

    private function toIndianWordsSafe(float $amount): string
    {
        try {
            if (class_exists(\NumberFormatter::class)) {
                $fmt = new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT);
                $rupees = (int) floor($amount);
                $paise  = (int) round(($amount - $rupees) * 100);
                $out = ucfirst($fmt->format($rupees)) . ' rupees';
                if ($paise > 0) $out .= ' and ' . $fmt->format($paise) . ' paise';
                return $out;
            }
        } catch (\Throwable $e) {}
        return '';
    }
  //download invoice
  public function invoice_download($id) {
    if (Session::has('currency_code')) {
      $currency_code = Session::get('currency_code');
    } else {
      $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
    }
    $language_code = Session::get('locale', Config::get('app.locale'));

    if (Language::where('code', $language_code)->first()->rtl == 1) {
      $direction      = 'rtl';
      $text_align     = 'right';
      $not_text_align = 'left';
    } else {
      $direction      = 'ltr';
      $text_align     = 'left';
      $not_text_align = 'right';
    }

    if ($currency_code == 'BDT' || $language_code == 'bd') {
      // bengali font
      $font_family = "'Hind Siliguri','sans-serif'";
    } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
      // khmer font
      $font_family = "'Hanuman','sans-serif'";
    } elseif ($currency_code == 'AMD') {
      // Armenia font
      $font_family = "'arnamu','sans-serif'";
      // }elseif($currency_code == 'ILS'){
      //     // Israeli font
      //     $font_family = "'Varela Round','sans-serif'";
    } elseif ($currency_code == 'AED' || $currency_code == 'EGP' || $language_code == 'sa' || $currency_code == 'IQD' || $language_code == 'ir' || $language_code == 'om' || $currency_code == 'ROM' || $currency_code == 'SDG' || $currency_code == 'ILS' || $language_code == 'jo') {
      // middle east/arabic/Israeli font
      $font_family = "'Baloo Bhaijaan 2','sans-serif'";
    } elseif ($currency_code == 'THB') {
      // thai font
      $font_family = "'Kanit','sans-serif'";
    } else {
      // general for all
      $font_family = "'Roboto','sans-serif'";
    }

   

    // $config = ['instanceConfigurator' => function($mpdf) {
    //     $mpdf->showImageErrors = true;
    // }];
    // mpdf config will be used in 4th params of loadview

    $config = [];

    $order = Order::findOrFail($id);
    $pdfContentService = new PdfContentService();
    $pdfContentBlock   = $pdfContentService->buildBlockForType('order');
    if (mb_substr($order->code, 0, 3) == 'MZ/') {
      return PDF::loadView('backend.invoices.invoice', [
        'order'          => $order,
        'font_family'    => $font_family,
        'direction'      => $direction,
        'text_align'     => $text_align,
        'not_text_align' => $not_text_align,
        'pdfContentBlock'  => $pdfContentBlock,
      ], [], $config)->download('order-' . $order->code . '.pdf');
    } else {
      return PDF::loadView('backend.invoices.proformainvoice', [
        'order'          => $order,
        'font_family'    => $font_family,
        'direction'      => $direction,
        'text_align'     => $text_align,
        'not_text_align' => $not_text_align,
        'pdfContentBlock'  => $pdfContentBlock,
      ], [], $config)->download('order-' . $order->code . '.pdf');
    }
  }





  //new invoice _file_path function created on 06 - aug- 2024
  public function invoice_file_path($id) {
    if (Session::has('currency_code')) {
      $currency_code = Session::get('currency_code');
    } else {
        $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
    }
    $language_code = Session::get('locale', Config::get('app.locale'));

    if (Language::where('code', $language_code)->first()->rtl == 1) {
        $direction      = 'rtl';
        $text_align     = 'right';
        $not_text_align = 'left';
    } else {
        $direction      = 'ltr';
        $text_align     = 'left';
        $not_text_align = 'right';
    }

    if ($currency_code == 'BDT' || $language_code == 'bd') {
        $font_family = "'Hind Siliguri','sans-serif'";
    } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
        $font_family = "'Hanuman','sans-serif'";
    } elseif ($currency_code == 'AMD') {
        $font_family = "'arnamu','sans-serif'";
    } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
        $font_family = "'Baloo Bhaijaan 2','sans-serif'";
    } elseif ($currency_code == 'THB') {
        $font_family = "'Kanit','sans-serif'";
    } else {
        $font_family = "'Roboto','sans-serif'";
    }

    $config = [];

    $order = Order::findOrFail($id);

    $client_address = DB::table('addresses')
    ->where('id', $order->address_id)
    ->first();

    $pdfContentService = new PdfContentService();
    $pdfContentBlock   = $pdfContentService->buildBlockForType('order');
    $view = mb_substr($order->code, 0, 3) == 'MZ/' ? 'backend.invoices.invoice' : 'backend.invoices.proformainvoice';
    $pdf = PDF::loadView($view, [
        'order'          => $order,
        'client_address' => $client_address,
        'font_family'    => $font_family,
        'direction'      => $direction,
        'text_align'     => $text_align,
        'not_text_align' => $not_text_align,
        'pdfContentBlock'  => $pdfContentBlock,
    ], [], $config);

    $fileName = 'order-' . $order->code . '.pdf';
    $filePath = public_path('pdfs/' . $fileName);
    $pdf->save($filePath);

    $publicUrl = url('public/pdfs/' . $fileName);

    return $publicUrl;
  }




public function invoice_file_path_cart_quotations_manager41($id, $newQuotationId)
{
    if (Session::has('currency_code')) {
        $currency_code = Session::get('currency_code');
    } else {
        $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
    }
    $language_code = Session::get('locale', Config::get('app.locale'));

    if (Language::where('code', $language_code)->first()->rtl == 1) {
        $direction      = 'rtl';
        $text_align     = 'right';
        $not_text_align = 'left';
    } else {
        $direction      = 'ltr';
        $text_align     = 'left';
        $not_text_align = 'right';
    }

    if ($currency_code == 'BDT' || $language_code == 'bd') {
        $font_family = "'Hind Siliguri','sans-serif'";
    } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
        $font_family = "'Hanuman','sans-serif'";
    } elseif ($currency_code == 'AMD') {
        $font_family = "'arnamu','sans-serif'";
    } elseif (in_array($currency_code, ['AED','EGP','IQD','ROM','SDG','ILS']) || in_array($language_code, ['sa','ir','om','jo'])) {
        $font_family = "'Baloo Bhaijaan 2','sans-serif'";
    } elseif ($currency_code == 'THB') {
        $font_family = "'Kanit','sans-serif'";
    } else {
        $font_family = "'Roboto','sans-serif'";
    }

    $config = [];

    // ---- Manager-41 filtered cart items ----
    $cartItems = DB::table('users')
        ->leftJoin('carts', 'users.id', '=', 'carts.user_id')
        ->leftJoin('products', 'carts.product_id', '=', 'products.id')
        ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
        ->leftJoin('addresses', 'carts.address_id', '=', 'addresses.id')
        ->leftJoin('uploads', 'products.photos', '=', 'uploads.id')
        ->select(
            'users.company_name',
            'users.phone',
            'users.manager_id',
            'users.party_code',
            'users.credit_days',
            'users.credit_limit',
            'products.part_no',
            'products.name as product_name',
            'warehouses.name as warehouse_name',
            'carts.created_at',
            'carts.quantity',
            'carts.address_id',
            'carts.price',
            'carts.user_id',
            'carts.product_id',
            'addresses.address',
            'addresses.address_2',
            'addresses.city',
            'carts.id as cart_id',
            DB::raw('carts.quantity * carts.price as total'),
            'uploads.file_name as thumbnail'
        )
        ->where('carts.user_id', $id)
        ->where('carts.is_manager_41', 1)   // <<< Manager-41 filter
        ->get();

    // Address (Manager-41 filter respected)
    $cartItemAddress = DB::table('users')
        ->join('carts', 'users.id', '=', 'carts.user_id')
        ->join('addresses', 'carts.address_id', '=', 'addresses.id')
        ->where('carts.user_id', $id)
        ->where('carts.is_manager_41', 1)   // <<< Manager-41 filter
        ->select('carts.address_id', 'addresses.address', 'addresses.address_2')
        ->first();

    if ($cartItems->isEmpty()) {
        // kuch bhi nahi mila to null de do; caller handle karega
        return null;
    }

    // Logo
    $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';

    $pdfContentService = new PdfContentService();
    $pdfContentBlock   = $pdfContentService->buildBlockForType('quotation');

    $view = 'backend.invoices.manager_41_cart_quotation';
    $pdf = PDF::loadView($view, [
        'cart_items'     => $cartItems,
        'client_address' => $cartItemAddress,
        'quotation_id'   => $newQuotationId,
        'logo'           => $logo,
        'font_family'    => $font_family,
        'direction'      => $direction,
        'text_align'     => $text_align,
        'not_text_align' => $not_text_align,
        'pdfContentBlock'  => $pdfContentBlock,
    ], [], $config);

    // Ensure folder exists
    $folder = public_path('pdfs');
    if (!\Illuminate\Support\Facades\File::exists($folder)) {
        \Illuminate\Support\Facades\File::makeDirectory($folder, 0775, true);
    }

    $fileName = 'quotations-' . $newQuotationId . '.pdf';
    $filePath = $folder . DIRECTORY_SEPARATOR . $fileName;
    $pdf->save($filePath);

    // Public URL
    $publicUrl = url('public/pdfs/' . $fileName);

    return $publicUrl;
}

  //new invoice _file_path for cart_quotations function created on 14 - aug- 2024
  public function invoice_file_path_cart_quotations($id,$newQuotationId) {
    
    if (Session::has('currency_code')) {
      $currency_code = Session::get('currency_code');
    } else {
        $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
    }
    $language_code = Session::get('locale', Config::get('app.locale'));
   

    if (Language::where('code', $language_code)->first()->rtl == 1) {
        $direction      = 'rtl';
        $text_align     = 'right';
        $not_text_align = 'left';
    } else {
        $direction      = 'ltr';
        $text_align     = 'left';
        $not_text_align = 'right';
    }
    

    if ($currency_code == 'BDT' || $language_code == 'bd') {
        $font_family = "'Hind Siliguri','sans-serif'";
    } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
        $font_family = "'Hanuman','sans-serif'";
    } elseif ($currency_code == 'AMD') {
        $font_family = "'arnamu','sans-serif'";
    } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
        $font_family = "'Baloo Bhaijaan 2','sans-serif'";
    } elseif ($currency_code == 'THB') {
        $font_family = "'Kanit','sans-serif'";
    } else {
        $font_family = "'Roboto','sans-serif'";
    }

    $config = [];
    

    $cartItems = DB::table('users')
    ->leftJoin('carts', 'users.id', '=', 'carts.user_id')
    ->leftJoin('products', 'carts.product_id', '=', 'products.id')
    ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
    ->leftJoin('addresses', 'carts.address_id', '=', 'addresses.id')
    ->leftJoin('uploads', 'products.photos', '=', 'uploads.id') // Join uploads with products based on thumbnail_img
    ->select(
        'users.company_name',
        'users.phone',
        'users.manager_id',
        'users.party_code',
        'users.credit_days',
        'users.credit_limit',
        'products.part_no',
        'products.name as product_name',
        'warehouses.name as warehouse_name',
        'carts.created_at',
        'carts.quantity',
        'carts.address_id',
        'carts.price',
        'carts.user_id',
        'carts.product_id',
        'addresses.address',
        'addresses.address_2',
        'addresses.city',
        'carts.id as cart_id',
        DB::raw('carts.quantity * carts.price as total'),
        'uploads.file_name as thumbnail' // Select the file_name from uploads
    )
    ->where('carts.user_id', $id)
    ->get();

    $cartItemAddress = DB::table('users')
        ->join('carts', 'users.id', '=', 'carts.user_id')
        ->join('addresses', 'carts.address_id', '=', 'addresses.id') // Join with the addresses table
        ->where('carts.user_id', $id)  // Add the where condition
        ->select('carts.address_id', 'addresses.address', 'addresses.address_2') // Select specific columns
        ->first(); // Retrieve the first matching record

   // $logo="https://storage.googleapis.com/mazing/uploads/all/PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png";   
   $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';

   $pdfContentService = new PdfContentService();
   $pdfContentBlock   = $pdfContentService->buildBlockForType('quotation');
      
    $view = 'backend.invoices.cart_quotations';
    $pdf = PDF::loadView($view, [
        'cart_items'          => $cartItems,
        'client_address' => $cartItemAddress,
        'quotation_id'=>$newQuotationId,
        'logo'=>$logo,
        'font_family'    => $font_family,
        'direction'      => $direction,
        'text_align'     => $text_align,
        'not_text_align' => $not_text_align,
        'pdfContentBlock'  => $pdfContentBlock,
    ], [], $config);

    $fileName = 'quotations-' . $newQuotationId . '.pdf';
    $filePath = public_path('pdfs/' . $fileName);
    $pdf->save($filePath);

    $publicUrl = url('public/pdfs/' . $fileName);

    return $publicUrl;
  }

    //new invoice _file_path for cart_quotations function created on 14 - aug- 2024
   public function invoice_file_path_abandoned_cart($id, $random_number)
    {
        if (Session::has('currency_code')) {
            $currency_code = Session::get('currency_code');
        } else {
            $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
        }
        $language_code = Session::get('locale', Config::get('app.locale'));

        if (Language::where('code', $language_code)->first()->rtl == 1) {
            $direction      = 'rtl';
            $text_align     = 'right';
            $not_text_align = 'left';
        } else {
            $direction      = 'ltr';
            $text_align     = 'left';
            $not_text_align = 'right';
        }

        if ($currency_code == 'BDT' || $language_code == 'bd') {
            $font_family = "'Hind Siliguri','sans-serif'";
        } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
            $font_family = "'Hanuman','sans-serif'";
        } elseif ($currency_code == 'AMD') {
            $font_family = "'arnamu','sans-serif'";
        } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
            $font_family = "'Baloo Bhaijaan 2','sans-serif'";
        } elseif ($currency_code == 'THB') {
            $font_family = "'Kanit','sans-serif'";
        } else {
            $font_family = "'Roboto','sans-serif'";
        }

        $config = [];

        // 🔴 Sirf non-41 cart items
        $cartItems = DB::table('users')
            ->leftJoin('carts', 'users.id', '=', 'carts.user_id')
            ->leftJoin('products', 'carts.product_id', '=', 'products.id')
            ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('addresses', 'carts.address_id', '=', 'addresses.id')
            ->leftJoin('uploads', 'products.thumbnail_img', '=', 'uploads.id')
            ->select(
                'users.company_name',
                'users.phone',
                'users.manager_id',
                'users.party_code',
                'products.name as product_name',
                'products.part_no as part_no',
                'warehouses.name as warehouse_name',
                'carts.created_at',
                'carts.quantity',
                'carts.address_id',
                'carts.price',
                'carts.user_id',
                'carts.product_id',
                'addresses.address',
                'addresses.address_2',
                'addresses.city',
                'carts.id as cart_id',
                'carts.is_manager_41',
                DB::raw('carts.quantity * carts.price as total')
            )
            ->where('carts.user_id', $id)
            ->where('carts.is_manager_41', 0)  // 🔴 filter
            ->get();

        // Graceful guard
        if ($cartItems->isEmpty()) {
            // aap chaho to empty PDF return kar do ya exception throw karo
            // For now, simple empty PDF with header
            $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';
            $view = 'backend.invoices.unfilled_order';
            $pdfContentService = new PdfContentService();
            $pdfContentBlock   = $pdfContentService->buildBlockForType('abandon_cart');
            $pdf = PDF::loadView($view, [
                'cart_items'     => $cartItems,
                'client_address' => null,
                'logo'           => $logo,
                'font_family'    => $font_family,
                'direction'      => $direction,
                'text_align'     => $text_align,
                'not_text_align' => $not_text_align,
                'pdfContentBlock'  => $pdfContentBlock,
            ], [], $config);

            $hashCode = substr(md5(uniqid($random_number, true)), 0, 8);
            $fileName = 'abandoned_cart-empty-' . $hashCode . '.pdf';
            $filePath = public_path('abandoned_cart_pdf/' . $fileName);
            $pdf->save($filePath);
            return url('public/abandoned_cart_pdf/' . $fileName);
        }

        $cartItemAddress = DB::table('users')
            ->join('carts', 'users.id', '=', 'carts.user_id')
            ->join('addresses', 'carts.address_id', '=', 'addresses.id')
            ->where('carts.user_id', $id)
            ->where('carts.is_manager_41', 0) // 🔴 filter
            ->select('carts.address_id', 'addresses.address', 'addresses.address_2')
            ->first();

        $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';

        $view = 'backend.invoices.unfilled_order';
        $pdfContentService = new PdfContentService();
        $pdfContentBlock   = $pdfContentService->buildBlockForType('abandon_cart');
        $pdf = PDF::loadView($view, [
            'cart_items'     => $cartItems,
            'client_address' => $cartItemAddress,
            'logo'           => $logo,
            'font_family'    => $font_family,
            'direction'      => $direction,
            'text_align'     => $text_align,
            'not_text_align' => $not_text_align,
            'pdfContentBlock'  => $pdfContentBlock,
        ], [], $config);

        $hashCode = substr(md5(uniqid($random_number, true)), 0, 8);
        $fileName = 'abandoned_cart-' . $hashCode . '.pdf';
        $filePath = public_path('abandoned_cart_pdf/' . $fileName);
        $pdf->save($filePath);

        $publicUrl = url('public/abandoned_cart_pdf/' . $fileName);
        return $publicUrl;
    }

    public function is_invoice_file_path_abandoned_cart($id,$random_number) {
    
      if (Session::has('currency_code')) {
        $currency_code = Session::get('currency_code');
      } else {
          $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
      }
      $language_code = Session::get('locale', Config::get('app.locale'));
     
  
      if (Language::where('code', $language_code)->first()->rtl == 1) {
          $direction      = 'rtl';
          $text_align     = 'right';
          $not_text_align = 'left';
      } else {
          $direction      = 'ltr';
          $text_align     = 'left';
          $not_text_align = 'right';
      }
      
  
      if ($currency_code == 'BDT' || $language_code == 'bd') {
          $font_family = "'Hind Siliguri','sans-serif'";
      } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
          $font_family = "'Hanuman','sans-serif'";
      } elseif ($currency_code == 'AMD') {
          $font_family = "'arnamu','sans-serif'";
      } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
          $font_family = "'Baloo Bhaijaan 2','sans-serif'";
      } elseif ($currency_code == 'THB') {
          $font_family = "'Kanit','sans-serif'";
      } else {
          $font_family = "'Roboto','sans-serif'";
      }
  
      $config = [];
      
      $cartItems = DB::table('users')
        ->leftJoin('carts', 'users.id', '=', 'carts.user_id')
        ->leftJoin('products', 'carts.product_id', '=', 'products.id')
        ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
        ->leftJoin('addresses', 'carts.address_id', '=', 'addresses.id')
    	->leftJoin('uploads', 'products.thumbnail_img', '=', 'uploads.id')
        ->select(
            'users.company_name',
            'users.phone',
            'users.manager_id',
            'users.party_code',
            'products.name as product_name',
            'products.part_no as part_no',
            'warehouses.name as warehouse_name',
            'carts.created_at',
            'carts.quantity',
            'carts.address_id',
            'carts.price',
            'carts.user_id',
            'carts.product_id',
            'addresses.address',
            'addresses.address_2',
            'addresses.city',
            'carts.id as cart_id',
            'carts.is_manager_41',
            DB::raw('carts.quantity * carts.price as total')
        )
        ->where('carts.user_id', $id)
        ->get();

              // echo $cartItems->count();
              // die();
  
      $cartItemAddress = DB::table('users')
          ->join('carts', 'users.id', '=', 'carts.user_id')
          ->join('addresses', 'carts.address_id', '=', 'addresses.id') // Join with the addresses table
          ->where('carts.user_id', $id)  // Add the where condition
          ->select('carts.address_id', 'addresses.address', 'addresses.address_2') // Select specific columns
          ->first(); // Retrieve the first matching record
  
     // $logo="https://storage.googleapis.com/mazing/uploads/all/PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png";       
     $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';
  
      $view = 'backend.invoices.unfilled_order';

      $pdfContentService = new PdfContentService();
      $pdfContentBlock   = $pdfContentService->buildBlockForType('abandon_cart');

      $pdf = PDF::loadView($view, [
          'cart_items'          => $cartItems,
          'client_address' => $cartItemAddress,
          
          'logo'=>$logo,
          'font_family'    => $font_family,
          'direction'      => $direction,
          'text_align'     => $text_align,
          'not_text_align' => $not_text_align,
          'pdfContentBlock'  => $pdfContentBlock,
      ], [], $config);
      
      $hashCode = substr(md5(uniqid($random_number, true)), 0, 8);
      $fileName = 'abandoned_cart-' . '-' . $hashCode . '.pdf';
      // $fileName = 'quotations-' . $newQuotationId . '.pdf';
      $filePath = public_path('abandoned_cart_pdf/' . $fileName);
      $pdf->save($filePath);
  
      $publicUrl = url('public/abandoned_cart_pdf/' . $fileName);

      // echo $publicUrl;
      // die();
  
      return $publicUrl;
    }


    //purchase order for download only
    public function download_purchase_order_pdf_invoice($purchase_order_no) {
      if (Session::has('currency_code')) {
          $currency_code = Session::get('currency_code');
      } else {
          $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
      }
      $language_code = Session::get('locale', Config::get('app.locale'));
  
      if (Language::where('code', $language_code)->first()->rtl == 1) {
          $direction      = 'rtl';
          $text_align     = 'right';
          $not_text_align = 'left';
      } else {
          $direction      = 'ltr';
          $text_align     = 'left';
          $not_text_align = 'right';
      }
  
      if ($currency_code == 'BDT' || $language_code == 'bd') {
          $font_family = "'Hind Siliguri','sans-serif'";
      } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
          $font_family = "'Hanuman','sans-serif'";
      } elseif ($currency_code == 'AMD') {
          $font_family = "'arnamu','sans-serif'";
      } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
          $font_family = "'Baloo Bhaijaan 2','sans-serif'";
      } elseif ($currency_code == 'THB') {
          $font_family = "'Kanit','sans-serif'";
      } else {
          $font_family = "'Roboto','sans-serif'";
      }
  
      // Fetch the purchase order details
      $order = DB::table('final_purchase_order')
          ->where('purchase_order_no', $purchase_order_no)
          ->select('final_purchase_order.*')
          ->first();
  
      // Decode the seller_info JSON
      $sellerInfo = json_decode($order->seller_info, true);
  
      // Decode the product_info JSON
      $productInfo = json_decode($order->product_invoice, true);
  
      // Fetch product details (purchase_price, hsncode, product_name) for each part number
      foreach ($productInfo as &$product) {
          $productDetails = DB::table('products')
              ->where('part_no', $product['part_no'])
              ->select('name as product_name', 'purchase_price', 'hsncode', 'part_no')
              ->first();
  
          $product['product_name'] = $productDetails->product_name ?? 'Unknown';
          $product['purchase_price'] = $productDetails->purchase_price ?? 0;
          $product['hsncode'] = $productDetails->hsncode ?? 'N/A';
          $product['subtotal'] = $product['qty'] * $product['purchase_price'];
      }
  
      // $logo = "https://storage.googleapis.com/mazing/uploads/all/PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png";
      $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';

      $view = 'backend.invoices.purchase_order_pdf';
  
      $randomNumber = rand(1000, 9999);
      $fileName = 'purchase_order-' . $randomNumber . '.pdf';
  
      $pdf = PDF::loadView($view, [
          'logo' => $logo,
          'font_family' => $font_family,
          'direction' => $direction,
          'text_align' => $text_align,
          'not_text_align' => $not_text_align,
          'order' => $order,
          'sellerInfo' => $sellerInfo,  // Pass the decoded seller information
          'productInfo' => $productInfo
      ], [], []);
  
      $filePath = public_path('purchase_order_pdf/' . $fileName);
      $pdf->save($filePath);
  
      // $publicUrl = url('public/purchase_order_pdf/' . $fileName);
      return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
     // return $publicUrl;
  }
    


    //purchase order pdf generation (save & continuee) for whatsapp
    public function purchase_order_pdf_invoice($purchase_order_no) {
      if (Session::has('currency_code')) {
          $currency_code = Session::get('currency_code');
      } else {
          $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
      }
      $language_code = Session::get('locale', Config::get('app.locale'));
  
      if (Language::where('code', $language_code)->first()->rtl == 1) {
          $direction      = 'rtl';
          $text_align     = 'right';
          $not_text_align = 'left';
      } else {
          $direction      = 'ltr';
          $text_align     = 'left';
          $not_text_align = 'right';
      }
  
      if ($currency_code == 'BDT' || $language_code == 'bd') {
          $font_family = "'Hind Siliguri','sans-serif'";
      } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
          $font_family = "'Hanuman','sans-serif'";
      } elseif ($currency_code == 'AMD') {
          $font_family = "'arnamu','sans-serif'";
      } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
          $font_family = "'Baloo Bhaijaan 2','sans-serif'";
      } elseif ($currency_code == 'THB') {
          $font_family = "'Kanit','sans-serif'";
      } else {
          $font_family = "'Roboto','sans-serif'";
      }
  
      // Fetch the purchase order details
      $order = DB::table('final_purchase_order')
          ->where('purchase_order_no', $purchase_order_no)
          ->select('final_purchase_order.*')
          ->first();
  
      // Decode the seller_info JSON
      $sellerInfo = json_decode($order->seller_info, true);
  
      // Decode the product_info JSON
      $productInfo = json_decode($order->product_invoice, true);
  
      // Fetch product details (purchase_price, hsncode, product_name) for each part number
      foreach ($productInfo as &$product) {
          $productDetails = DB::table('products')
              ->where('part_no', $product['part_no'])
              ->select('name as product_name', 'purchase_price', 'hsncode', 'part_no')
              ->first();
  
          $product['product_name'] = $productDetails->product_name ?? 'Unknown';
          $product['purchase_price'] = $productDetails->purchase_price ?? 0;
          $product['hsncode'] = $productDetails->hsncode ?? 'N/A';
          $product['subtotal'] = $product['qty'] * $product['purchase_price'];
      }
  
      // $logo = "https://storage.googleapis.com/mazing/uploads/all/PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png";
      $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';

      $view = 'backend.invoices.purchase_order_pdf';
  
      $randomNumber = rand(1000, 9999);
      $fileName = 'purchase_order-' . $randomNumber . '.pdf';
  
      $pdf = PDF::loadView($view, [
          'logo' => $logo,
          'font_family' => $font_family,
          'direction' => $direction,
          'text_align' => $text_align,
          'not_text_align' => $not_text_align,
          'order' => $order,
          'sellerInfo' => $sellerInfo,  // Pass the decoded seller information
          'productInfo' => $productInfo
      ], [], []);
  
      $filePath = public_path('purchase_order_pdf/' . $fileName);
      $pdf->save($filePath);
  
      $publicUrl = url('public/purchase_order_pdf/' . $fileName);
      return $publicUrl;
  }
  

      //purchase order pdf generation (save & continuee) for whatsapp
      public function packing_list_pdf_invoice($purchase_order_no) {
        if (Session::has('currency_code')) {
            $currency_code = Session::get('currency_code');
        } else {
            $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
        }
        $language_code = Session::get('locale', Config::get('app.locale'));

        if (Language::where('code', $language_code)->first()->rtl == 1) {
            $direction      = 'rtl';
            $text_align     = 'right';
            $not_text_align = 'left';
        } else {
            $direction      = 'ltr';
            $text_align     = 'left';
            $not_text_align = 'right';
        }

        if ($currency_code == 'BDT' || $language_code == 'bd') {
            $font_family = "'Hind Siliguri','sans-serif'";
        } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
            $font_family = "'Hanuman','sans-serif'";
        } elseif ($currency_code == 'AMD') {
            $font_family = "'arnamu','sans-serif'";
        } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
            $font_family = "'Baloo Bhaijaan 2','sans-serif'";
        } elseif ($currency_code == 'THB') {
            $font_family = "'Kanit','sans-serif'";
        } else {
            $font_family = "'Roboto','sans-serif'";
        }

        // Fetch the purchase order details
        $order = DB::table('final_purchase_order')
            ->join('sellers', 'final_purchase_order.seller_id', '=', 'sellers.id')
            ->join('shops', 'sellers.id', '=', 'shops.seller_id')
            ->where('final_purchase_order.purchase_order_no', $purchase_order_no)
            ->select(
                'final_purchase_order.*',
                'shops.name as seller_company_name',
                'shops.address as seller_address',
                'sellers.gstin as seller_gstin',
                'shops.phone as seller_phone'
            )
            ->first();
          
        // Decode the product_info JSON
        $productInfo = json_decode($order->product_invoice, true);

        // Fetch product details (purchase_price, hsncode, product_name) for each part number
        foreach ($productInfo as &$product) {
            $productDetails = DB::table('products')
                ->where('part_no', $product['part_no'])
                ->select('name as product_name', 'purchase_price', 'hsncode', 'part_no')
                ->first();

            $product['product_name'] = $productDetails->product_name ?? 'Unknown';
            $product['purchase_price'] = $productDetails->purchase_price ?? 0;
            $product['hsncode'] = $productDetails->hsncode ?? 'N/A';
            $product['subtotal'] = $product['qty'] * $product['purchase_price'];
        }
        $sellerName = DB::table('final_purchase_order')
              ->where('purchase_order_no', $purchase_order_no)
              ->value(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_name'))"));

        // $logo = "https://storage.googleapis.com/mazing/uploads/all/PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png";   
        $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';
      
        $view = 'backend.invoices.packing_list';


        $randomNumber = rand(1000, 9999);
        $fileName = 'packing_list-' . $randomNumber . '.pdf';

        $pdf = PDF::loadView($view, [
            'logo' => $logo,
            'font_family' => $font_family,
            'direction' => $direction,
            'text_align' => $text_align,
            'not_text_align' => $not_text_align,
            'order' => $order,
            'seller_name'=>$sellerName,
            'productInfo' => $productInfo
        ])->save(public_path('packing_list_pdf/' . $fileName));

        $publicUrl = url('public/packing_list_pdf/' . $fileName);
        return $publicUrl;
    }

    // download packing list only
     public function download_packing_list_pdf_invoice($purchase_order_no) {
        if (Session::has('currency_code')) {
            $currency_code = Session::get('currency_code');
        } else {
            $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
        }
        $language_code = Session::get('locale', Config::get('app.locale'));

        if (Language::where('code', $language_code)->first()->rtl == 1) {
            $direction      = 'rtl';
            $text_align     = 'right';
            $not_text_align = 'left';
        } else {
            $direction      = 'ltr';
            $text_align     = 'left';
            $not_text_align = 'right';
        }

        if ($currency_code == 'BDT' || $language_code == 'bd') {
            $font_family = "'Hind Siliguri','sans-serif'";
        } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
            $font_family = "'Hanuman','sans-serif'";
        } elseif ($currency_code == 'AMD') {
            $font_family = "'arnamu','sans-serif'";
        } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
            $font_family = "'Baloo Bhaijaan 2','sans-serif'";
        } elseif ($currency_code == 'THB') {
            $font_family = "'Kanit','sans-serif'";
        } else {
            $font_family = "'Roboto','sans-serif'";
        }

        // Fetch the purchase order details
        $order = DB::table('final_purchase_order')
            ->join('sellers', 'final_purchase_order.seller_id', '=', 'sellers.id')
            ->join('shops', 'sellers.id', '=', 'shops.seller_id')
            ->where('final_purchase_order.purchase_order_no', $purchase_order_no)
            ->select(
                'final_purchase_order.*',
                'shops.name as seller_company_name',
                'shops.address as seller_address',
                'sellers.gstin as seller_gstin',
                'shops.phone as seller_phone'
            )
            ->first();
          
        // Decode the product_info JSON
        $productInfo = json_decode($order->product_invoice, true);

        // Fetch product details (purchase_price, hsncode, product_name) for each part number
        foreach ($productInfo as &$product) {
            $productDetails = DB::table('products')
                ->where('part_no', $product['part_no'])
                ->select('name as product_name', 'purchase_price', 'hsncode', 'part_no')
                ->first();

            $product['product_name'] = $productDetails->product_name ?? 'Unknown';
            $product['purchase_price'] = $productDetails->purchase_price ?? 0;
            $product['hsncode'] = $productDetails->hsncode ?? 'N/A';
            $product['subtotal'] = $product['qty'] * $product['purchase_price'];
        }
        $sellerName = DB::table('final_purchase_order')
              ->where('purchase_order_no', $purchase_order_no)
              ->value(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(seller_info, '$.seller_name'))"));

        // $logo = "https://storage.googleapis.com/mazing/uploads/all/PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png";   
        $logo = env('UPLOADS_BASE_URL') . '/' . 'PH8lWzw1Qs3Z2QxgCOTYpdQe2DmP0GQHCSZaxXAk.png';
      
        $view = 'backend.invoices.packing_list';


        $randomNumber = rand(1000, 9999);
        $fileName = 'packing_list-' . $randomNumber . '.pdf';

        $filePath=public_path('packing_list_pdf/' . $fileName);

        $pdf = PDF::loadView($view, [
            'logo' => $logo,
            'font_family' => $font_family,
            'direction' => $direction,
            'text_align' => $text_align,
            'not_text_align' => $not_text_align,
            'order' => $order,
            'seller_name'=>$sellerName,
            'productInfo' => $productInfo
        ])->save($filePath);

        //$publicUrl = url('public/packing_list_pdf/' . $fileName);
        //return $publicUrl;

        return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
    }


    private function generatePaymentUrl($party_code, $payment_for)
    {
      $client = new \GuzzleHttp\Client();
      $response = $client->post('https://mazingbusiness.com/api/v2/payment/generate-url', [
          'json' => [
              'party_code' => $party_code,
              'payment_for' => $payment_for
          ]
      ]);

      $data = json_decode($response->getBody(), true);
      return $data['url'] ?? '';  // Return the generated URL or an empty string if it fails
    }



public function statementPdfDownload(Request $request)
{
    try {
        // 1) Trigger your existing generator to get the PDF URL
        //    (it will run sync if queue.default=sync; otherwise it queues and still returns the URL)
        $genResponse = $this->downloadStatementOnly($request);

        // If generator failed, bubble up its error JSON
        if (method_exists($genResponse, 'getStatusCode') && $genResponse->getStatusCode() >= 400) {
            return $genResponse;
        }

        $genPayload = $genResponse->getData(true); // array from JsonResponse
        $publicUrl  = $genPayload['url'] ?? null;

        

        if (!$publicUrl) {
            return response()->json(['error' => 'Failed to receive statement URL from generator.'], 500);
        }

        // derive filename from URL (needed for WhatsApp document header)
        $fileName = basename(parse_url($publicUrl, PHP_URL_PATH));

        // 2) Decrypt party code (we still need it for payment URL & phones)
        $party_code = decrypt($request->party_code);

        // 3) Fetch user/address info for placeholders (company name, due/overdue, phones)
        $userData = DB::table('users')
            ->join('addresses', 'users.id', '=', 'addresses.user_id')
            ->where('addresses.acc_code', $party_code)
            ->select(
                'users.id',
                'users.phone',
                'users.manager_id',
                'users.warehouse_id',
                'addresses.company_name',
                'addresses.overdue_amount',
                'addresses.due_amount'
            )
            ->first();

        if (!$userData) {
            return response()->json(['error' => 'User or address not found'], 404);
        }

        $companyName   = $userData->company_name ?? 'Customer';
        $dueAmount     = (float)($userData->due_amount ?? 0);
        $overdueAmount = (float)($userData->overdue_amount ?? 0);

        // 4) Payment link + URL button variable
        $payment_url = $this->generatePaymentUrl($party_code, $payment_for = "due_amount");
        // If you don't want to use Str helper:
        $button_variable_encode_part = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
        // $button_variable_encode_part = Str::after($payment_url, 'pay-amount/'); // alternative

        // 5) Manager / Head Manager phones
        $managerPhone = null;
        if (!empty($userData->manager_id)) {
            $managerRow   = DB::table('users')->where('id', $userData->manager_id)->select('phone')->first();
            $managerPhone = $managerRow->phone ?? null;
        }
        $headManagerPhone = $this->getHeadManagerPhone($userData->warehouse_id);

        // 6) Build WhatsApp template payload (document in header + variables in body + URL button)
        $templateData = [
            'name'      => 'utility_statement_document', // your approved template name
            'language'  => 'en_US',
            'components'=> [
                [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type'     => 'document',
                            'document' => [
                                'link'     => $publicUrl,
                                'filename' => $fileName,
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $companyName],
                        ['type' => 'text', 'text' => number_format($dueAmount, 2)],
                        ['type' => 'text', 'text' => number_format($overdueAmount, 2)],
                        ['type' => 'text', 'text' => (string) $managerPhone],
                    ],
                ],
                [
                    'type'    => 'button',
                    'sub_type'=> 'url',
                    'index'   => '0',
                    'parameters' => [
                        ['type' => 'text', 'text' => $button_variable_encode_part],
                    ],
                ],
            ],
        ];

        // 7) Fire WhatsApp sends
        $this->whatsAppWebService = new WhatsAppWebService();
        $whatsappNumbers = array_filter([
            $headManagerPhone,
            $managerPhone,
            $userData->phone,
        ]);

        foreach ($whatsappNumbers as $number) {
            $this->whatsAppWebService->sendTemplateMessage($number, $templateData);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Statement sent to WhatsApp',
            'url'     => $publicUrl,
        ], 200);

    } catch (\Exception $e) {
        Log::error('statementPdfDownload error: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
}



    public function downloadStatementOnly(Request $request)
  {
      try {
          $party_code = decrypt($request->party_code);
      } catch (\Exception $e) {
          \Log::error('Decryption error: ' . $e->getMessage());
          return response()->json(['error' => 'Failed to decrypt party code.'], 500);
      }

      try {
          $currentDate  = date('Y-m-d');
          $currentMonth = date('m');

          // FY range defaults
          if ($currentMonth >= 4) {
              $form_date = date('Y-04-01');
              $to_date   = date('Y-03-31', strtotime('+1 year'));
          } else {
              $form_date = date('Y-04-01', strtotime('-1 year'));
              $to_date   = date('Y-03-31');
          }

          // override by request
          if ($request->has('from_date')) $form_date = $request->from_date;
          if ($request->has('to_date'))   $to_date   = $request->to_date;
          if ($to_date > $currentDate)    $to_date   = $currentDate;

          // -------- get base data --------
          $userAddress = Address::where('acc_code', $party_code)->first();
          if (!$userAddress) {
              return response()->json(['error' => 'Address not found'], 404);
          }
          $userData = User::where('id', $userAddress->user_id)->first();
          if (!$userData) {
              return response()->json(['error' => 'User not found'], 404);
          }

          // pull all addresses of same user + GSTIN
          $company_name = $userAddress->company_name ?? 'Company Name not found';
          $address      = $userAddress->address ?? 'Address not found';
          $address_2    = $userAddress->address_2 ?? '';
          $postal_code  = $userAddress->postal_code ?? '';
          $gstin        = $userAddress->gstin;

          $userAddressData = Address::where('user_id', $userData->id)
              ->when($gstin, fn($q) => $q->where('gstin', $gstin))
              ->get();

          // merge statement_data across those addresses
          $statementData = [];
          $overdueAmount = 0.0;
          $dueAmount     = 0.0;

          foreach ($userAddressData as $uValue) {
              $decoded = json_decode($uValue->statement_data, true);
              if (!is_array($decoded)) continue;

              // strip closing rows
              $filtered = array_filter($decoded, function ($row) {
                  return !isset($row['ledgername']) || stripos($row['ledgername'], 'closing C/f...') === false;
              });

              $statementData[$uValue->id] = $filtered;
              $overdueAmount += (float)$uValue->overdue_amount;
              $dueAmount     += (float)$uValue->due_amount;
          }

          // flatten + sort by date
          $merged = [];
          foreach ($statementData as $data) $merged = array_merge($merged, $data);
          usort($merged, fn($a, $b) => strtotime($a['trn_date']) <=> strtotime($b['trn_date']));
          $statementData = array_values($merged);

          // running balance + synthetic closing
          $balance = 0.0;
          foreach ($statementData as $i => $row) {
              if (($row['ledgername'] ?? '') === 'Opening b/f...') {
                  $balance = ($row['dramount'] != 0.00) ? (float)$row['dramount'] : -(float)$row['cramount'];
              } else {
                  $balance += (float)$row['dramount'] - (float)$row['cramount'];
              }
              $statementData[$i]['running_balance'] = $balance;
          }
          $statementData[] = [
              'trn_no'               => '',
              'trn_date'             => date('Y-m-d'),
              'vouchertypebasename'  => '',
              'ledgername'           => 'closing C/f...',
              'ledgerid'             => '',
              'dramount'             => ($balance < 0) ? number_format($balance, 2, '.', '') : "0.00",
              'cramount'             => ($balance >= 0) ? number_format($balance, 2, '.', '') : "0.00",
              'narration'            => '',
              'running_balance'      => number_format($balance, 2, '.', ''),
          ];

          // optional: when a custom range provided, rebuild opening/closing with running balances
          if ($request->has(['from_date','to_date'])) {
              $from_date        = $request->from_date;
              $to_date_override = $request->to_date;

              $opening_balance = 0.00;
              $filtered        = [];

              foreach ($statementData as $entry) {
                  $d = $entry['trn_date'];
                  if ($d < $from_date) {
                      $opening_balance += (float)$entry['dramount'] - (float)$entry['cramount'];
                  } elseif ($d >= $from_date && $d <= $to_date_override) {
                      $filtered[] = $entry;
                  }
              }

              $opening_entry = [
                  'trn_no'               => '',
                  'trn_date'             => $from_date,
                  'vouchertypebasename'  => 'Opening Balance',
                  'ledgername'           => 'Opening b/f...',
                  'ledgerid'             => '',
                  'dramount'             => $opening_balance > 0 ? number_format($opening_balance, 2, '.', '') : "0.00",
                  'cramount'             => $opening_balance < 0 ? number_format(abs($opening_balance), 2, '.', '') : "0.00",
                  'narration'            => '',
                  'running_balance'      => number_format($opening_balance, 2, '.', ''),
              ];

              $run = $opening_balance;
              foreach ($filtered as &$entry) {
                  $run += (float)$entry['dramount'] - (float)$entry['cramount'];
                  $entry['running_balance'] = number_format($run, 2, '.', '');
              }

              $closing_entry = [
                  'trn_no'               => '',
                  'trn_date'             => $to_date_override,
                  'vouchertypebasename'  => '',
                  'ledgername'           => 'closing C/f...',
                  'ledgerid'             => '',
                  'dramount'             => '',
                  'cramount'             => '',
                  'narration'            => '',
                  'running_balance'      => number_format($run, 2, '.', ''),
              ];

              $statementData = array_merge([$opening_entry], $filtered, [$closing_entry]);
          }

          // ---- prepare payload for JOB (no changes in job) ----
          // job expects $data['userData'] but also reads dueDrOrCr/overdueDrOrCr; attach from address:
          $userData->dueDrOrCr     = $userAddress->dueDrOrCr ?? null;
          $userData->overdueDrOrCr = $userAddress->overdueDrOrCr ?? null;

          $randomNumber = str_replace('.', '', microtime(true));
          $fileName     = 'statement-' . $party_code . '-' . $randomNumber . '.pdf';

          $data = [
              'userData'      => $userData,
              'party_code'    => $party_code,
              'statementData' => $statementData,
              'overdueAmount' => (float)$overdueAmount,
              'dueAmount'     => (float)$dueAmount,
              'form_date'     => $form_date,
              'to_date'       => $to_date,
              // (address block is looked up inside the job via party_code)
          ];

          // If QUEUE_CONNECTION=sync -> generate now; else queue and return 202
          $publicUrl = url('public/statements/' . $fileName);

          if (config('queue.default') === 'sync') {
              // run immediately
              GenerateStatementPdf::dispatchSync($data, $fileName);
              return response()->json([
                  'status'  => 'success',
                  'message' => 'Statement generated',
                  'url'     => $publicUrl,
              ], 200);
          }

          // queue async (use your preferred queue name)
          GenerateStatementPdf::dispatch($data, $fileName)->onQueue('pdf');

          return response()->json([
              'status'  => 'queued',
              'message' => 'Statement generation started',
              'url'     => $publicUrl, // file will appear here when job finishes
          ], 202);

      } catch (\Exception $e) {
          \Log::error('downloadStatementOnly error: ' . $e->getMessage());
          return response()->json(['error' => $e->getMessage()], 500);
      }
  }




  public function generateInvoice($invoice_no)
  {

      try {
      // Decrypt the invoice number
          $decryptedInvoiceNo = decrypt($invoice_no);
      } catch (\Exception $e) {
          return response()->json(['error' => 'Invalid invoice number.'], 404);
      }
       /**
     * Check if the Invoice Exists in `invoice_orders` Table
     * If found, redirect to the `downloadPdf` method in `PurchaseOrderController`.
     */
      $invoiceOrder = InvoiceOrder::where('invoice_no', $decryptedInvoiceNo)->first();
      if ($invoiceOrder) {
          // ✅ Create an instance of the PurchaseOrderController
          $purchaseOrderController = new PurchaseOrderController();
          // ✅ Call the downloadPdf method with the invoice ID
          return $purchaseOrderController->downloadPdf($invoiceOrder->id);
      }


      // Proceed with the remaining logic if not found in `invoice_orders`
      // Fetch bills data using the invoice number
      $billsData = DB::table('bills_data')
          ->where('invoice_no', decrypt($invoice_no))
          ->select(
              'dispatch_id',
              'part_no',
              'item_name',
              'hsn',
              'billed_qty',
              'rate',
              'bill_amount',
              'invoice_no',
              'invoice_amount',
              'invoice_date',
              'billing_company',
              'product_id',

              DB::raw('SUM(invoice_amount) OVER () as total_invoice_amount')
          )
          ->get();

      $totalInvoiceAmount = $billsData->first()->invoice_amount ?? 0;
    

      // Check if the invoice exists
      if ($billsData->isEmpty()) {
          return response()->json(['info' => 'Invoice not found'], 404);
      }

      // Fetch the billing company details from the addresses table
      $billingCompany = $billsData->first()->billing_company;
      $billingDetails = DB::table('addresses')
          ->where('acc_code', $billingCompany)
          ->select('company_name', 'address', 'address_2', 'city', 'postal_code', 'gstin', 'due_amount','dueDrOrCr','overdue_amount','overdueDrOrCr','user_id')
          ->first();

      if (!$billingDetails) {
          return response()->json(['info' => 'Billing details not found'], 404);
      }

        // Fetch manager_id from the users table based on user_id
      $managerId = DB::table('users')
          ->where('id', $billingDetails->user_id)
          ->value('manager_id');
      $manager_phone= $this->getManagerPhone($managerId);


      // Fetch the logistic data from the order_logistics table
      
      $logisticsDetails = DB::table('order_logistics')
      ->where('invoice_no', decrypt($invoice_no))
      ->select('lr_no', 'lr_date', 'no_of_boxes', 'attachment')
      ->first();

      if (!$logisticsDetails) {
          // Set default values
          $logisticsDetails = (object) [
              'lr_no' => '',
              'lr_date' => '',
              'no_of_boxes' => '',
              'attachment' => '#', // Default link when no attachment is available
          ];
      }



      // Extract place of supply from invoice number
      $placePrefix = strtoupper(substr(decrypt($invoice_no), 0, 3));

      

      // Example data from the Excel
      $branchDetails = [
          'KOL' => [
              'gstin' => '19ABACA4198B1ZS',
              'company_name' => 'ACE TOOLS PVT LTD',
              'address_1' => '257B, BIPIN BEHARI GANGULY STREET',
              'address_2' => '2ND FLOOR',
              'address_3' => '',
              'city' => 'KOLKATA',
              'state' => 'WEST BENGAL',
              'postal_code' => '700012',
              'contact_name' => 'Amir Madraswala',
              'phone' => '9709555576',
              'email' => 'acetools505@gmail.com',
          ],
          'MUM' => [
              'gstin' => '27ABACA4198B1ZV',
              'company_name' => 'ACE TOOLS PVT LTD',
              'address_1' => 'HARIHAR COMPLEX F-8, HOUSE NO-10607, ANUR DEPODE ROAD',
              'address_2' => 'GODOWN NO.7, GROUND FLOOR',
              'address_3' => 'BHIWANDI',
              'city' => 'MUMBAI',
              'state' => 'MAHARASHTRA',
              'postal_code' => '421302',
              'contact_name' => 'Hussain',
              'phone' => '9930791952',
              'email' => 'acetools505@gmail.com',
          ],
          'DEL' => [
              'gstin' => '07ABACA4198B1ZX',
              'company_name' => 'ACE TOOLS PVT LTD',
              'address_1' => 'Khasra No. 58/15',
              'address_2' => 'Pal Colony',
              'address_3' => 'Village Rithala',
              'city' => 'New Delhi',
              'state' => 'Delhi',
              'postal_code' => '110085',
              'contact_name' => 'Mustafa Worliwala',
              'phone' => '9730377752',
              'email' => 'acetools505@gmail.com',
          ],
      ];

      $branchData = $branchDetails[$placePrefix] ?? null;
      if (!$branchData) {
          return response()->json(['error' => 'Branch details not found for the given invoice'], 404);
      }

      // Get the invoice date from the first record
      $invoiceDate = $billsData->first()->invoice_date;

      // Configuration for PDF (optional)
      $config = [
          'format' => 'A4',
          'orientation' => 'portrait',
          'margin_top' => 10,
          'margin_bottom' => 0,
      ];

      $pdfContentService = new PdfContentService();
      $pdfContentBlock   = $pdfContentService->buildBlockForType('invoice');

      // Generate the PDF
      $pdf = PDF::loadView('backend.invoices.product_invoiced', [
          'billsData' => $billsData,
          'invoice_no' => $invoice_no,
          'totalInvoiceAmount' => $totalInvoiceAmount,
          'placeOfSupply' => $placePrefix,
          'invoiceDate' => $invoiceDate,
          'billingDetails' => $billingDetails,
          'logisticsDetails' => $logisticsDetails,
          'branchDetails' => $branchData, // Pass the branch details to the view
          'manager_phone'=>$manager_phone,
          'pdfContentBlock' // ✅ Blade me use hoga
      ], [], $config);

      // Define the file name and path
      $fileName = 'invoice-' . str_replace('/', '-', decrypt($invoice_no)) . '-' . uniqid() . '.pdf';
      $filePath = public_path('purchase_history_invoice/' . $fileName);

      // Save the PDF to the public/statements directory
      $pdf->save($filePath);

      // Generate the public URL
      $publicUrl = url('public/purchase_history_invoice/' . $fileName);

      // Return the public URL as a response
      return response()->file($filePath, [
          'Content-Type' => 'application/pdf',
          'Content-Disposition' => 'inline; filename="' . $fileName . '"',
      ]);
  }

public function getStatementPdfURL(Request $request)
{
    try {
        $party_code = decrypt($request->party_code);
    } catch (\Exception $e) {
        Log::error('Decryption error: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to decrypt party code.'], 500);
    }

    $currentDate = date('Y-m-d');
    $currentMonth = date('m');
    $currentYear = date('Y');

    // Define financial year date range based on the current date
    if ($currentMonth >= 4) {
        $form_date = date('Y-04-01'); // Start of financial year
        $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year
    } else {
        $form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
        $to_date = date('Y-03-31'); // Current year March
    }

    // Use custom date range if provided
    if ($request->has('from_date')) {
        $form_date = $request->from_date;
    }

    if ($request->has('to_date')) {
        $to_date = $request->to_date;
    }

    // Limit the 'to_date' to the current date
    if ($to_date > $currentDate) {
        $to_date = $currentDate;
    }

    // Perform INNER JOIN between users and addresses tables based on user_id
    $userData = DB::table('users')
        ->join('addresses', 'users.id', '=', 'addresses.user_id')
        ->where('addresses.acc_code', $party_code)
        ->select('users.*', 'addresses.company_name', 'addresses.statement_data', 'addresses.overdue_amount', 'addresses.due_amount', 'addresses.address', 'addresses.address_2', 'addresses.postal_code', 'addresses.dueDrOrCr', 'addresses.overdueDrOrCr')
        ->first();

    if (!$userData) {
        return response()->json(['error' => 'User or address not found'], 404);
    }

    // Get statement_data, overdue_amount, and due_amount from the address table
    $statementData = json_decode($userData->statement_data, true);
    $overdueAmount = floatval($userData->overdue_amount);
    $dueAmount = floatval($userData->due_amount);

    // Retrieve the address information
    $company_name = $userData->company_name ?? 'Company Name not found';
    $address = $userData->address ?? 'Address not found';
    $address_2 = $userData->address_2 ?? '';
    $postal_code = $userData->postal_code ?? '';

    // Variables to store balances
    $openingBalance = "0";
    $closingBalance = "0";
    $openDrOrCr = "";
    $closeDrOrCr = "";
    $overdueDrOrCr = 'Dr'; // Default value for overdue Dr/Cr

    // Calculate total debit
    $totalDebit = 0;
    foreach ($statementData as $transaction) {
        if (isset($transaction['dramount']) && $transaction['dramount'] != "0.00") {
            $totalDebit += floatval($transaction['dramount']);
        }
    }

    // Get user credit limit and calculate available credit
    $creditLimit = floatval($userData->credit_limit);
    $availableCredit = $creditLimit - $totalDebit;

    $getOverdueData = $statementData;

    // Iterate through statement data and process transactions
    foreach ($statementData as $transaction) {
        if (isset($transaction['ledgername']) && $transaction['ledgername'] == "Opening b/f...") {
            $openingBalance = ($transaction['dramount'] != "0.00") ? floatval($transaction['dramount']) : floatval($transaction['cramount']);
            $openDrOrCr = ($transaction['dramount'] != "0.00") ? "Dr" : "Cr";
        } elseif (isset($transaction['ledgername']) && $transaction['ledgername'] == "closing C/f...") {
            $closingBalance = ($transaction['dramount'] != "0.00") ? floatval($transaction['dramount']) : floatval($transaction['cramount']);
            $closeDrOrCr = ($transaction['dramount'] != "0.00") ? "Dr" : "Cr";

            // Set dueAmount and overdueAmount and also set overdueDrOrCr based on closing balance
            if ($transaction['dramount'] != "0.00") {
                $dueAmount = floatval($transaction['dramount']);
                $overdueDrOrCr = 'Dr';
            } else {
                $dueAmount = floatval($transaction['cramount']);
                $overdueDrOrCr = 'Cr';
            }

            $cloasingDrAmount = $transaction['dramount'];
            $cloasingCrAmount = $transaction['cramount'];
            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));

            if ($cloasingCrAmount > 0) {
                $drBalanceBeforeOVDate = 0;
                $crBalanceBeforeOVDate = 0;
                $getOverdueData = array_reverse($getOverdueData);

                foreach ($getOverdueData as $ovValue) {
                    if ($ovValue['ledgername'] != 'closing C/f...') {
                        if (strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)) {
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        } else {
                            $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }
                    }
                }
                $overdueAmount = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
            }

            if ($overdueAmount <= 0) {
                $overdueDrOrCr = 'Cr';
                $overdueAmount = 0;
            } else {
                $overdueDrOrCr = 'Dr';
            }
        }
    }

    // Add overdue days calculation to each transaction
    foreach ($statementData as &$transaction) {
        if (isset($transaction['trn_date']) && strtotime($transaction['trn_date']) < strtotime($overdueDateFrom)) {
            $dateDiff = (strtotime($overdueDateFrom) - strtotime($transaction['trn_date'])) / (60 * 60 * 24);
            $transaction['overdue_days'] = floor($dateDiff) . ' days';
        } else {
            $transaction['overdue_days'] = '-';
        }
    }

    // Generating PDF with transaction data
    $randomNumber = str_replace('.', '', microtime(true));
    $fileName = 'statement-' . $party_code . '-' . $randomNumber . '.pdf';

    // Prepare PDF content using Blade template
    $pdf = PDF::loadView('backend.invoices.statement_pdf', compact(
        'userData',
        'party_code',
        'statementData',
        'openingBalance',
        'openDrOrCr',
        'closingBalance',
        'closeDrOrCr',
        'form_date',
        'to_date',
        'overdueAmount',
        'overdueDrOrCr',
        'dueAmount',
        'availableCredit',
        'address',
        'address_2',
        'postal_code'
    ))->save(public_path('statements/' . $fileName));

    $publicUrl = url('public/statements/' . $fileName);
    return $publicUrl;

   
}



  public function invoice_combined_order($order_code) {

      // Determine currency code
      if (Session::has('currency_code')) {
          $currency_code = Session::get('currency_code');
      } else {
          $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
      }

      // Determine language code and text direction
      $language_code = Session::get('locale', Config::get('app.locale'));

      if (Language::where('code', $language_code)->first()->rtl == 1) {
          $direction      = 'rtl';
          $text_align     = 'right';
          $not_text_align = 'left';
      } else {
          $direction      = 'ltr';
          $text_align     = 'left';
          $not_text_align = 'right';
      }

      // Inline font family logic
      if ($currency_code == 'BDT' || $language_code == 'bd') {
          $font_family = "'Hind Siliguri','sans-serif'";
      } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
          $font_family = "'Hanuman','sans-serif'";
      } elseif ($currency_code == 'AMD') {
          $font_family = "'arnamu','sans-serif'";
      } elseif (in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) || in_array($language_code, ['sa', 'ir', 'om', 'jo'])) {
          $font_family = "'Baloo Bhaijaan 2','sans-serif'";
      } elseif ($currency_code == 'THB') {
          $font_family = "'Kanit','sans-serif'";
      } else {
          $font_family = "'Roboto','sans-serif'";
      }

      // Get the order data
      $order = OwnBrandOrder::where('order_code', $order_code)->where('delivery_status','confirm')->firstOrFail();
      // echo "<pre>";
      // print_r($order);
      // die();

      // Get the client address by joining the addresses and users tables based on customer_id
  

      $client_address = User::select('company_name', 'address', 'city', 'postal_code', 'gstin', 'phone', 'party_code')
    ->where('id', $order->customer_id)
    ->first();
      // echo "<pre>";
      // print_r($client_address);
      // die();

      if (!$client_address) {
          // Set a static address if no client address is found
          $client_address = (object) [
              'company_name' => 'Static Company Name',
              'address' => 'Static Address Line 1',
              'address_2' => 'Static Address Line 2',
              'city' => 'Static City',
              'postal_code' => '123456',
              'gstin' => '00XXXXX0000XZX',
              'phone' => '1234567890'
          ];
      }

      // Get the order details for the products
      // Get the order details and join the `products` table to fetch product-related data
     $order_details = OwnBrandOrderDetail::select('own_brand_order_details.*', 'own_brand_products.photos', 'own_brand_products.part_no')
      ->join('own_brand_products', 'own_brand_products.id', '=', 'own_brand_order_details.product_id')
      ->where('own_brand_order_details.order_code', $order_code)
      ->get();

      // Select view based on order code
      $view = mb_substr($order->order_code, 0, 3) == 'MZ/' ? 'backend.invoices.invoice' : 'backend.invoices.own_brand_order';

      // Pass the data to the PDF view
      $pdf = PDF::loadView($view, [
          'order'          => $order,
          'order_details'  => $order_details,
          'client_address' => $client_address,
          'font_family'    => $font_family,
          'direction'      => $direction,
          'text_align'     => $text_align,
          'not_text_align' => $not_text_align,
      ]);

      // If you need paper size or orientation
      // $pdf->setPaper('A4', 'portrait');  // Example for setting paper size

      $fileName = 'order-' . $order->order_code . '.pdf';
      $filePath = public_path('pdfs/' . $fileName);
      $pdf->save($filePath);

      $publicUrl = url('public/pdfs/' . $fileName);

      return $publicUrl;
  }




  
}
