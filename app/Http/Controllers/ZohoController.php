<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ZohoSetting;
use App\Models\ZohoToken;
use App\Models\ZohoPaymentToken;
use App\Models\Address;
use App\Models\Product;
use App\Models\State;
use App\Models\Shop;
use App\Models\Seller;
use App\Models\User;
use App\Models\InvoiceOrder;
use App\Models\InvoiceOrderDetail;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceDetail;
use App\Models\ZohoChartOfAccount;
use App\Models\Carrier;
use App\Models\DebitNoteInvoice;
use App\Models\DebitNoteInvoiceDetail;
use App\Models\MarkAsLostItem;
use App\Models\ZohoPayment;
use App\Models\OrderLogistic;

use App\Models\Warehouse;
use App\Models\EwayBill;
use App\Models\ZohoTax;
use App\Models\Challan;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\File;
use App\Services\WhatsAppWebService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use Illuminate\Http\Client\RequestException;
use App\Jobs\BulkUploadInvoiceAttachmentsJob;

class ZohoController extends Controller
{
    private $clientId, $clientSecret, $redirectUri, $orgId;

    public function __construct()
    {
        $settings = ZohoSetting::where('status','0')->first();

        $this->clientId = $settings->client_id;
        $this->clientSecret = $settings->client_secret;
        $this->redirectUri = $settings->redirect_uri;
        $this->orgId = $settings->organization_id;
    }

    private function getManagerPhone($managerId)
    {
      $managerData = User::where('id', $managerId)
          ->select('name')
          ->first();

      return $managerData->name ?? 'No Manager Phone';
    }

    // Step 1: Redirect to Zoho OAuth page
    public function redirectToZoho()
    {
        $authUrl = "https://accounts.zoho.in/oauth/v2/auth?" . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            // 'client_id' => '1005.RIME8BTAKD43II43KYFLM81DGQI2BT',
            
            'scope' => implode(" ", [
                "ZohoBooks.invoices.CREATE",
                "ZohoBooks.invoices.READ",
                "ZohoBooks.contacts.READ",
                "ZohoBooks.contacts.DELETE",
                "ZohoBooks.settings.READ",
                "ZohoBooks.items.READ",
                "ZohoBooks.contacts.CREATE",                
                "ZohoBooks.settings.CREATE",
                "ZohoBooks.settings.UPDATE",
                "ZohoBooks.bills.CREATE",
                "ZohoBooks.bills.READ",
                "ZohoBooks.bills.UPDATE",
                "ZohoBooks.bills.DELETE",
                "ZohoBooks.inventoryadjustments.CREATE",
                "ZohoBooks.fullaccess.all"
            ]),
            'redirect_uri' => $this->redirectUri,
            // 'redirect_uri' => 'https://mazingbusiness.com/zoho/payment-callback',
            'access_type' => 'offline',
            'prompt' => 'consent' // 🔥 forces permission screen to show again
        ]);
        // print_r($authUrl); die;
        return redirect()->away($authUrl);
    }

    // Step 2: Handle Zoho redirect and get tokens
    public function handleZohoCallback(Request $request)
    {
        if (!$request->has('code')) {
            return response()->json(['error' => 'Authorization code is missing']);
        }

        $code = $request->input('code');
        $response = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ])->json();

        if (isset($response['access_token'])) {
            ZohoToken::truncate();

            ZohoToken::create([
                'access_token' => $response['access_token'],
                'refresh_token' => $response['refresh_token'],
                'expires_at' => now()->addSeconds($response['expires_in']),
            ]);

            return response()->json(['message' => 'Token stored successfully.']);
        }

        return response()->json(['error' => 'Failed to get token.', 'response' => $response]);
    }

    private function getAuthHeaders()
    {
        $token = ZohoToken::first();
        if (!$token) {
            abort(403, 'Zoho token not found.');
        }
        // 🔁 Refresh token if expired
        if (now()->greaterThanOrEqualTo($token->expires_at)) {
            $settings = ZohoSetting::first();
            $refresh = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'refresh_token' => $token->refresh_token,
            ])->json();

            if (isset($refresh['access_token'])) {
                $token->update([
                    'access_token' => $refresh['access_token'],
                    'expires_at' => now()->addSeconds($refresh['expires_in']),
                ]);
            } else {
                abort(403, 'Failed to refresh Zoho token.');
            }
        }
        return [
            'Authorization' => 'Zoho-oauthtoken ' . $token->access_token,
            'Content-Type' => 'application/json',
        ];
    }


    public function sendZohoSyncFailureNotification($referenceId, $errorMsg, $functionName = null)
    {
        try {
            $recipientNumber = '919894753728'; // ✅ Must include country code

            // ✅ Automatically detect calling function + class if not passed
            if (!$functionName) {
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
                $caller = $trace[1] ?? [];
                $class = $caller['class'] ?? 'UnknownClass';
                $function = $caller['function'] ?? 'UnknownFunction';
                $functionName = $class . '::' . $function;
            }

            $components = [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $functionName],  // {{1}} - Auto function
                        ['type' => 'text', 'text' => $referenceId],   // {{2}} - Ref ID
                        ['type' => 'text', 'text' => $errorMsg],      // {{3}} - Error
                    ]
                ]
            ];

            $templateData = [
                'name' => 'zoho_api_failure_alert',
                'language' => 'en_US',
                'components' => $components,
            ];

            $WhatsAppWebService = new WhatsAppWebService();
            $res = $WhatsAppWebService->sendTemplateMessage($recipientNumber, $templateData);

            return response()->json(['success' => true, 'response' => $res]);

         } catch (\Exception $e) {
            \Log::error('WhatsApp send error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
         }
   }

   public function testZohoFailureNotification()
   {

    // how to call failure notification
    $referenceId = 'TEST-REF-001';
    $errorMsg = 'This is a test error for WhatsApp Zoho failure alert.';

    return $this->sendZohoSyncFailureNotification($referenceId, $errorMsg);
   }

  public function generatePaymentUrl(
    string $invoiceNo,
    string $payment_for,
    float $amount,
    string $party_code = '',
    int $user_id = 0,
    string $customExpiresDate = '',
    bool $notify_user = false
): string
{
    try {
        if ($amount <= 0) {
            \Log::warning('[ZohoPayment] Invalid amount for ' . $invoiceNo . ': ' . $amount);
            return '';
        }

        $tz         = 'Asia/Kolkata';
        $today      = \Carbon\Carbon::now($tz)->toDateString();
        $expires_at = $customExpiresDate !== '' ? $customExpiresDate : \Carbon\Carbon::now($tz)->addDay()->toDateString();
        $accountId  = env('ZOHO_PAYMENT_ACCOUNT_ID');

        // Identify mode
        $isConsolidated = (strtoupper($invoiceNo) === 'MULTI') || (stripos($payment_for, 'party_total') === 0);

        // 1) Resolve user/address
        $user = null;
        if ($user_id > 0) {
            $user = \App\Models\User::find($user_id);
        } elseif ($party_code !== '') {
            $user = \App\Models\User::where('party_code', $party_code)->first();
        }

        $addressRow = null;
        if ($party_code !== '') {
            $addressRow = \App\Models\Address::where('acc_code', $party_code)->first();
        }

        // Email
        $email = 'admin@mazing.store';
        if ($user && is_string($user->email) && trim($user->email) !== '') {
            $email = trim($user->email);
        } elseif ($addressRow && is_string($addressRow->email) && trim($addressRow->email) !== '') {
            $email = trim($addressRow->email);
        }

        // Phone (normalize)
        $phoneSource = '';
        if ($user && is_string($user->phone)) {
            $phoneSource = $user->phone;
        } elseif ($addressRow && is_string($addressRow->phone)) {
            $phoneSource = $addressRow->phone;
        }
        $phone = preg_replace('/\s+|-/', '', (string)$phoneSource);

        // 2) Reuse logic
        $canUseInvoiceNoCol = \Illuminate\Support\Facades\Schema::hasColumn('zoho_payments', 'invoice_no');

        $reuseQuery = \App\Models\ZohoPayment::query();
        if ($user && $user->id) {
            $reuseQuery->where('user_id', $user->id);
        } elseif ($party_code !== '') {
            $reuseQuery->where('party_code', $party_code);
        }

        if ($isConsolidated) {
            // Consolidated: match by party + amount + unexpired; DO NOT bind to a single invoice
            // (Optionally, you can also add a marker match if you want stricter reuse)
            // $reuseQuery->where('description', 'like', '%[scope=party_total]%');
        } else {
            // Single-invoice: match by invoice_no column if present, otherwise description LIKE
            if ($canUseInvoiceNoCol) {
                $reuseQuery->where('invoice_no', $invoiceNo);
            } else {
                $reuseQuery->where('description', 'like', '%' . $invoiceNo . '%');
            }
        }

        $reuse = $reuseQuery
            ->where('payment_status', '0')
            ->whereDate('expires_at', '>=', $today)
            ->where('payable_amount', $amount)
            ->orderByDesc('id')
            ->first();

        if ($reuse && is_string($reuse->payment_link_url) && $reuse->payment_link_url !== '') {
            \Log::info('[ZohoPayment] Reusing link for ' . ($isConsolidated ? ('party '.$party_code) : ('invoice '.$invoiceNo)) . ': ' . $reuse->payment_link_url);
            return $reuse->payment_link_url;
        }

        // 3) Access token
        $tok = $this->getZohoPaymentAccessTokenOrAuthResponse();
        if (is_array($tok) && !empty($tok['auth_required'])) {
            if (!empty($tok['auth_url']) && is_string($tok['auth_url'])) {
                \Log::warning('[ZohoPayment] Auth required, returning auth URL for ' . ($isConsolidated ? $party_code : $invoiceNo));
                return $tok['auth_url'];
            }
            \Log::error('[ZohoPayment] Auth required but auth_url missing.');
            return '';
        }
        if (!is_array($tok) || empty($tok['access_token']) || !is_string($tok['access_token'])) {
            \Log::error('[ZohoPayment] Could not obtain access token.');
            return '';
        }
        $accessToken = $tok['access_token'];

        // 4) Description
        if ($isConsolidated) {
            // Consolidated (party total) — include a scope tag for clarity
            $desc = 'Payment for party ' . $party_code . ' — total outstanding as on ' . $today . ' (multiple invoices) [scope=party_total]';
        } else {
            $desc = 'Payment for invoice ' . $invoiceNo . ' (' . $payment_for . ')';
        }

        // 5) Payload
        $referenceId = '';
        if ($party_code !== '') {
            $referenceId = $party_code;
        } elseif ($user && is_string($user->party_code)) {
            $referenceId = $user->party_code;
        }

        $payload = [
            'amount'       => (float) $amount,
            'currency'     => 'INR',
            'email'        => $email,
            'phone'        => $phone,
            'reference_id' => $referenceId !== '' ? $referenceId : null,
            'description'  => $desc,
            'expires_at'   => $expires_at, // Y-m-d
            'return_url'   => 'https://mazingbusiness.com/zoho/after-payment-redirect',
            'notify_user'  => (bool) $notify_user,
        ];

        // 6) API call
        $resp = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
            'Content-Type'  => 'application/json',
        ])->post('https://payments.zoho.in/api/v1/paymentlinks?account_id=' . $accountId, $payload);

        if ($resp->failed()) {
            \Log::error('[ZohoPayment] Create link failed: ' . $resp->body());
            return '';
        }

        $data = $resp->json();
        $paymentUrl    = '';
        $paymentLinkId = '';

        // 7) Parse response robustly
        if (is_array($data)) {
            if (isset($data['payment_links']) && is_array($data['payment_links'])) {
                $pl = $data['payment_links'];
                if (isset($pl['url']) && is_string($pl['url'])) {
                    $paymentUrl = $pl['url'];
                }
                if (isset($pl['payment_link_id']) && is_string($pl['payment_link_id'])) {
                    $paymentLinkId = $pl['payment_link_id'];
                }
            } elseif (isset($data['payment_link']) && is_array($data['payment_link'])) {
                $pl = $data['payment_link'];
                if (isset($pl['url']) && is_string($pl['url'])) {
                    $paymentUrl = $pl['url'];
                }
                if (isset($pl['payment_link_id']) && is_string($pl['payment_link_id'])) {
                    $paymentLinkId = $pl['payment_link_id'];
                }
            } elseif (isset($data['url'])) {
                $paymentUrl = (string)$data['url'];
                if (isset($data['payment_link_id'])) {
                    $paymentLinkId = (string)$data['payment_link_id'];
                }
            }
        }

        if ($paymentUrl === '') {
            \Log::error('[ZohoPayment] API success but URL missing for ' . ($isConsolidated ? ('party '.$party_code) : ('invoice '.$invoiceNo)) . '. Payload: ' . json_encode($data));
            return '';
        }

        // 8) Persist
        $store = [
            'payment_link_url' => $paymentUrl,
            'payment_link_id'  => $paymentLinkId,
            'expires_at'       => $expires_at,
            'payable_amount'   => (float) $amount,
            'description'      => $desc,
            'user_id'          => null,
            'party_code'       => $referenceId !== '' ? $referenceId : null,
            'email'            => $email,
            'phone'            => $phone !== '' ? $phone : null,
            'send_by_id'       => \Illuminate\Support\Facades\Auth::id(),
            'payment_status'   => '0',
        ];

        if ($user && $user->id) {
            $store['user_id'] = $user->id;
        }

        // Save invoice no only for single-invoice mode (if column exists)
        if (\Illuminate\Support\Facades\Schema::hasColumn('zoho_payments', 'invoice_no')) {
            $store['invoice_no'] = $isConsolidated ? null : $invoiceNo;
        }

        \App\Models\ZohoPayment::create($store);

        \Log::info('[ZohoPayment] New link created for ' . ($isConsolidated ? ('party '.$party_code) : ('invoice '.$invoiceNo)) . ': ' . $paymentUrl . ' (id: ' . $paymentLinkId . ')');
        return $paymentUrl;

    } catch (\Throwable $e) {
        \Log::error('[ZohoPayment] Exception in generatePaymentUrl: ' . $e->getMessage());
        return '';
    }
}



    // Step 3: Use the token to call API
    public function callZohoApi(Request $request)
    {
        $token = ZohoToken::first();

        if (!$token) {
            return response()->json(['error' => 'No token available']);
        }

        // 🔄 Refresh token if expired
        if (now()->greaterThanOrEqualTo($token->expires_at)) {
            $refresh = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $token->refresh_token,
            ])->json();

            if (isset($refresh['access_token'])) {
                $token->update([
                    'access_token' => $refresh['access_token'],
                    'expires_at' => now()->addSeconds($refresh['expires_in']),
                ]);
            } else {
                return response()->json(['error' => 'Token refresh failed', 'response' => $refresh]);
            }
        }

        // ✅ Get endpoint from request (default to contacts)
        $endpoint = $request->input('endpoint', 'contacts');

        // 🧭 Build URL with orgId
        $url = "https://www.zohoapis.in/books/v3/{$endpoint}?organization_id={$this->orgId}";

        // 🔥 Make the call
        $response = Http::withHeaders($this->getAuthHeaders())
            ->get($url);

        return $response->json();
    }

    // ✅ Custom function to test any Zoho API
    public function testZohoApi(Request $request)
    {
        $token = ZohoToken::first();

        if (!$token) {
            return response()->json(['error' => 'No token found']);
        }

        // Auto-refresh if expired
        if (now()->greaterThanOrEqualTo($token->expires_at)) {
            $refresh = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $token->refresh_token,
            ])->json();

            if (isset($refresh['access_token'])) {
                $token->update([
                    'access_token' => $refresh['access_token'],
                    'expires_at' => now()->addSeconds($refresh['expires_in']),
                ]);
            } else {
                return response()->json(['error' => 'Token refresh failed', 'response' => $refresh]);
            }
        }

        // Use dynamic endpoint from URL (optional)
        $endpoint = $request->input('endpoint', "contacts");
        $url = "https://www.zohoapis.in/books/v3/{$endpoint}?organization_id={$this->orgId}";

        $response = Http::withHeaders($this->getAuthHeaders())
          ->get($url);

        return $response->json();
    }

    
   //Invoice Start
   public function createInvoice($invoiceId)
   {
        // --- 0) Basic auth/bootstrap ---
        $token = ZohoToken::first();
        $orgId = $this->orgId; // ensure this is set on your controller/service

        if (!$token) {
            return response()->json(['error' => 'Token not found'], 401);
        }

        // --- 1) Load invoice + user (for manager), and ensure we have a Zoho customer ---
        $invoice = InvoiceOrder::with('user')->findOrFail($invoiceId);

        $user = User::where('party_code', $invoice->party_code)->first();
        $manager_name = $this->getManagerPhone($user->manager_id ?? null); // your helper

        // Must have a shipping address with Zoho customer id
        $address = Address::where('id', $invoice->shipping_address_id)
            ->whereNotNull('zoho_customer_id')
            ->first();

        if (!$address) {
            $errorMessage = 'Customer address with Zoho ID not found. Address ID: ' . $invoice->shipping_address_id;
            $this->sendZohoSyncFailureNotification($invoice->invoice_no, $errorMessage);
            return response()->json(['error' => $errorMessage], 404);
        }

        // --- 2) Collect lines + convenience fee total (INCLUSIVE values stored in DB) ---
        $products = InvoiceOrderDetail::where('invoice_order_id', $invoice->id)->get();

        // If your DB stores fee INCLUSIVE of GST (as per your latest flow), we just sum it
        $convenienceFeeTotalIncl = (float) InvoiceOrderDetail::where('invoice_order_id', $invoice->id)
            ->sum('conveince_fees');

        // This is EXACTLY how you asked to pass it to Zoho (as base):
        $shippingChargeBase = $convenienceFeeTotalIncl > 0
            ? round($convenienceFeeTotalIncl, 2)
            : 0.00;

        // --- 3) Branch (warehouse) → Zoho Books branch_id mapping ---
        $warehouseId = $invoice->warehouse_id;
        $branchMap = [
            6 => "2435622000000031379", // Mumbai
            1 => "2435622000000031330", // Kolkata
            2 => "2435622000000031254", // Delhi (default)
        ];
        $branchId = $branchMap[$warehouseId] ?? "2435622000000031254";

        // --- 4) Build Zoho line items, resolve tax per line using your ZohoTax map ---
        $lineItems = [];

        // Determine intra vs inter for shipping tax as well
        $shippingAddr = Address::find($invoice->shipping_address_id);
        $shippingStateId = $shippingAddr->state_id ?? null;
        $shippingStateName = $shippingStateId ? optional(State::find($shippingStateId))->name : null;

        $warehouse = Warehouse::with('state')->find($warehouseId);
        $warehouseStateName = $warehouse->state->name ?? null;

        foreach ($products as $product) {
            // Local copy of the product master (for item_id)
            $item = Product::where('part_no', $product->part_no)->first();

            $taxPercent = (float) ($product->gst ?? 0);

            // Resolve Zoho tax (line level)
            if ($product->igst > 0) {
                // Inter-state → IGST
                $zohoTax = ZohoTax::where('tax_percentage', $taxPercent)
                    ->where('tax_specific_type', 'igst')
                    ->first();
            } else {
                // Intra-state → CGST+SGST
                $zohoTax = ZohoTax::where('tax_percentage', $taxPercent)
                    ->where('tax_specification', 'intra')
                    ->first();
            }

            if (!$zohoTax || !$zohoTax->tax_id) {
                $errorMessage = 'Zoho tax_id not found for part no: ' . ($product->part_no ?? 'N/A');
                $this->sendZohoSyncFailureNotification($invoice->invoice_no, $errorMessage);

                return response()->json([
                    'error' => $errorMessage,
                    'tax_percent' => $taxPercent
                ], 422);
            }

            // Human-friendly name + barcode in description
            $lineName = trim(($product->item_name ?? '')) . ' (' . $product->part_no . ')';
            $desc = !empty($product->barcode) ? (string)$product->barcode : '';

            $lineItems[] = [
                "item_id"         => $item->zoho_item_id ?? null, // ensure your products have zoho_item_id set
                "product_type"    => "goods",
                "name"            => $lineName,
                "hsn_or_sac"      => $product->hsn_no,
                "rate"            => (float) $product->rate,       // inclusive rate
                "quantity"        => (float) $product->billed_qty,
                "unit"            => "pcs",
                "description"     => $desc,
                "tax_id"          => $zohoTax->tax_id,
                "tax_percentage"  => $taxPercent,
            ];
        }

        // --- 5) Resolve tax ID for SHIPPING (always 18%; intra → CGST/SGST, inter → IGST) ---
        if ($shippingStateName && $warehouseStateName && $shippingStateName === $warehouseStateName) {
            // INTRA
            $shippingTax = ZohoTax::where('tax_percentage', 18)
                ->where('tax_specification', 'intra')
                ->first();
        } else {
            // INTER
            $shippingTax = ZohoTax::where('tax_percentage', 18)
                ->where('tax_specific_type', 'igst')
                ->first();
        }

        if (!$shippingTax || !$shippingTax->tax_id) {
            $errorMessage = 'Zoho shipping_charge_tax_id (18%) not found (intra/inter).';
            $this->sendZohoSyncFailureNotification($invoice->invoice_no, $errorMessage);
            return response()->json(['error' => $errorMessage], 422);
        }

        // --- 6) Assemble payload for Zoho (inclusive tax at line level) ---
        $discount = (float) ($invoice->rewards_discount ?? 0);

        $payload = [
            "customer_id"              => $address->zoho_customer_id,
            "gst_no"                   => $address->gstin ?? "",
            "branch_id"                => $branchId,
            "date"                     => $invoice->created_at->format('Y-m-d'),
            "invoice_number"           => $invoice->invoice_no,

            // entity-level discount (your rewards), applied before tax because your items are inclusive
            "discount"                 => $discount,
            "discount_type"            => "entity_level",

            // VERY IMPORTANT: your line "rate" is inclusive → must be true
            "is_inclusive_tax"         => true,
            "is_discount_before_tax"   => true,

            "exchange_rate"            => 1,
            "salesperson_name"         => $manager_name ?? "Ammar Master",
            "line_items"               => $lineItems,
            "notes"                    => "Thank you for your business!",

            // Convenience fee as SHIPPING (base), with tax id @18%
            "shipping_charge"          => $shippingChargeBase,
            "shipping_charge_tax_id"   => $shippingTax->tax_id,
        ];

        // --- 7) Send to Zoho Books ---
        $url = "https://www.zohoapis.in/books/v3/invoices?organization_id={$orgId}";
        $response = Http::withHeaders($this->getAuthHeaders())->post($url, $payload);

        // --- 8) Handle response: persist zoho_invoice_id + mark sent / notify on failure ---
        if ($response->successful() && isset($response['invoice']['invoice_id'])) {
            $zohoInvoiceId = $response['invoice']['invoice_id'];

            InvoiceOrder::where('id', $invoiceId)->update([
                'zoho_invoice_id' => $zohoInvoiceId
            ]);

            // optional but recommended
            $this->markAsSent($zohoInvoiceId);
        } else {
            $errorMessage = json_encode($response->json(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $this->sendZohoSyncFailureNotification($invoice->invoice_no, $errorMessage);
        }

        // --- 9) Return Zoho raw response for logging/inspection ---
        return $response->json();
   }






   

    public function updateInvoice(Request $request, $invoiceId)
    {
        $orgId = $this->orgId;
        $data = $request->all();

        $response = Http::withHeaders($this->getAuthHeaders())
            ->put("https://www.zohoapis.in/books/v3/invoices/{$invoiceId}?organization_id={$orgId}", $data);

        return $response->json();
    }

    public function listInvoices()
    {
        $orgId = $this->orgId;

        $response = Http::withHeaders($this->getAuthHeaders())
            ->get("https://www.zohoapis.in/books/v3/invoices?organization_id={$orgId}");

        return $response->json();
    }

    public function getInvoice($invoiceId)
    {
        $orgId = $this->orgId;

        $response = Http::withHeaders($this->getAuthHeaders())
            ->get("https://www.zohoapis.in/books/v3/invoices/{$invoiceId}?organization_id={$orgId}");

        return $response->json();
    }

    public function deleteInvoice($invoiceId)
    {
        //use zoho invoice_id 
        $orgId = $this->orgId;

        $response = Http::withHeaders($this->getAuthHeaders())
            ->delete("https://www.zohoapis.in/books/v3/invoices/{$invoiceId}?organization_id={$orgId}");

        return $response->json();
    }

    public function markAsSent($invoiceId)
    {
        //use zoho invoice_id 
        $orgId = $this->orgId;

        $response = Http::withHeaders($this->getAuthHeaders())
            ->post("https://www.zohoapis.in/books/v3/invoices/{$invoiceId}/status/sent?organization_id={$orgId}");

        return $response->json();
    }

    public function updateAllZohoInvoicesIdInInvoiceOrders()
    {
        $orgId = $this->orgId;
        $page = 1;
        $perPage = 200;
        $totalUpdated = 0;

        do {
            $response = Http::withHeaders($this->getAuthHeaders())
                ->get("https://www.zohoapis.in/books/v3/invoices", [
                    'organization_id' => $orgId,
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

            $invoices = $response->json('invoices') ?? [];

            foreach ($invoices as $invoice) {
                $zohoInvoiceId = $invoice['invoice_id'];
                $invoiceNumber = $invoice['invoice_number'];

                if ($invoiceNumber) {
                    $localInvoice = InvoiceOrder::where('invoice_no', $invoiceNumber)->first();

                    if ($localInvoice && $localInvoice->zoho_invoice_id !== $zohoInvoiceId) {
                        $localInvoice->zoho_invoice_id = $zohoInvoiceId;
                        $localInvoice->save();
                        $totalUpdated++;
                    }
                }
            }

            $morePages = count($invoices) === $perPage;
            $page++;
        } while ($morePages);

        return response()->json([
            'message' => 'Zoho invoice sync completed.',
            'total_updated' => $totalUpdated
        ]);
    }


    public function syncPendingInvoicesToZoho()
    {
        $pendingInvoices = InvoiceOrder::whereNull('zoho_invoice_id')->get();
        //  echo "<pre>";
        // print_r($pendingInvoices->toArray());
        // die();
        $results = [];
        foreach ($pendingInvoices as $invoice) {
            // Call your createInvoice function
            $response = $this->createInvoice($invoice->id);
            // echo "<pre>";
            // print_r($response);
            // die();
            $results[] = [
                'invoice_id' => $invoice->id,
                'zoho_invoice_id' => $response['invoice']['invoice_id'] ?? null,
                'status' => $response['code'] == 0 ? 'synced' : 'failed',
                'message' => $response['message'] ?? 'unknown',
            ];
        }

        return response()->json([
            'synced_invoices' => $results,
            'total' => count($results),
        ]);
    }

    private function cancelZohoInvoice($zohoInvoiceId)
    {
        $headers = $this->getAuthHeaders();
        $orgId = $this->orgId; // ✅ Dynamic Organization ID

        $url = "https://www.zohoapis.in/books/v3/invoices/{$zohoInvoiceId}/status/void?organization_id={$orgId}";

        $response = Http::withHeaders($headers)->post($url);

        if ($response->ok()) {
            $result = $response->json();
            if ($result['code'] === 0) {
                return [
                    'status' => 'success',
                    'message' => 'Invoice status has been changed to Void in Zoho.',
                ];
            }
        }

        return [
            'status' => 'error',
            'message' => 'Failed to void the invoice in Zoho: ' . $response->body(),
        ];
    }

    //Invoice End

    // seller start
    public function createNewSellerInZoho($user_id)
    {
        $orgId = $this->orgId;
        // ✅ Fetch seller GSTIN
        $seller = Seller::where('user_id', $user_id)->first();
        // ✅ Fetch shop details
        $shop = Shop::where('seller_id', $seller->id)->first();
        // ✅ Fetch user details
        $user = User::where('id', $user_id)->where('user_type', 'seller')->firstOrFail();
        //$seller = Seller::where('user_id', $seller_id)->first();
        $gstin = $seller ? ($seller->gstin ?? '') : '';

        // ✅ State lookup
        $state = State::where('name', $user->state)->first();
        $stateCode = $state->state_code ?? 'DL';
        $stateName = $state->name ?? 'Delhi';

        // ✅ Prepare payload
        $payload = [
            "contact_name" => $shop->name . ' - ' . $shop->address,
            "company_name" => $shop->name,
            "contact_type" => "vendor",
            "customer_sub_type" => "business",
            "email" => $user->email ?? '',
            "phone" => $shop->phone,
            "credit_limit" => 0,
            "is_portal_enabled" => false,
            "payment_terms" => 15,
            "payment_terms_label" => "Net 15",
            "place_of_contact" => $stateCode,
            // "gst_no" => $gstin,
            "gst_treatment" => $gstin ? "business_gst" : "business_none",
            "contact_persons" => [
                [
                    "first_name" => $shop->name,
                    "email" => $user->email ?? '',
                    "phone" => $shop->phone,
                    "mobile" => $shop->phone,
                ]
            ],
            "billing_address" => [
                "attention" => $shop->name,
                "address" => $shop->address,
                "city" => $user->city ?? "",
                "state" => $stateName,
                "state_code" => $stateCode,
                "zip" => $user->postal_code ?? "-",
                "country" => $user->country ?? "India",
                "phone" => $shop->phone,
            ],
            "shipping_address" => [
                "attention" => $shop->name,
                "address" => $shop->address,
                "city" => $user->city ?? "",
                "state" => $stateName,
                "state_code" => $stateCode,
                "zip" => $user->postal_code ?? "-",
                "country" => $user->country ?? "India",
                "phone" => $shop->phone,
            ]
            
        ];

        if (!empty($gstin)) {
            $payload['gst_no'] = $gstin;
        }

        // ✅ API Call to Zoho
        $response = Http::withHeaders($this->getAuthHeaders())
                    ->post("https://www.zohoapis.in/books/v3/contacts?organization_id={$orgId}", $payload);
                 
        // ✅ Save Zoho Vendor ID in seller table
        if ($response->successful() && isset($response['contact']['contact_id'])) {
            $zohoContactId = $response['contact']['contact_id'];

            if ($seller) {
                $seller->zoho_seller_id = $zohoContactId;
                $seller->save();
            }

            return response()->json([
                'message' => 'Seller contact created successfully in Zoho.',
                'zoho_seller_id' => $zohoContactId
            ]);
        }

        // ❌ Error Response
        return response()->json([
            'error' => 'Failed to create seller in Zoho.',
            'response' => $response->json()
        ], 500);
    }

   public function deleteZohoSellerContact($zoho_seller_id)
   {
        
        $url = "https://www.zohoapis.in/books/v3/contacts/{$zoho_seller_id}?organization_id={$this->orgId}";
        $headers = $this->getAuthHeaders();

        // Execute DELETE Request
        $response = Http::withHeaders($headers)->delete($url);

        if ($response->successful()) {
            $responseData = $response->json();

            if (isset($responseData['code']) && $responseData['code'] === 0) {
                

                return response()->json([
                    'message' => 'Zoho seller contact deleted successfully.',
                    'data'    => $responseData,
                ]);
            }

            return response()->json([
                'error'   => 'Unexpected response from Zoho.',
                'details' => $responseData
            ], 500);
        }

        return response()->json([
            'error'   => 'Failed to delete seller contact in Zoho.',
            'details' => $response->json()
        ], $response->status());
   }

   public function updateZohoSellerContact($zoho_seller_id)
   {
            $orgId = $this->orgId;

            // ✅ Fetch seller data
            $seller = Seller::where('zoho_seller_id', $zoho_seller_id)->first();

            if (!$seller) {
                return response()->json(['error' => 'Seller not found with Zoho ID: ' . $zoho_seller_id], 404);
            }

            // ✅ Fetch shop details
            $shop = Shop::where('seller_id', $seller->id)->first();
            if (!$shop) {
                return response()->json(['error' => 'Shop not found for seller.'], 404);
            }

            // ✅ Fetch user details
            $user = User::where('id', $seller->user_id)->first();
            if (!$user) {
                return response()->json(['error' => 'User not found for seller.'], 404);
            }

            $gstin = $seller->gstin ?? '';

            // ✅ State lookup
            $state = State::where('name', $user->state)->first();
            $stateCode = $state->state_code ?? 'DL';
            $stateName = $state->name ?? 'Delhi';

            // ✅ Prepare payload for update
            $payload = [
                "contact_name" => $shop->name . ' - ' . $shop->address,
                "company_name" => $shop->name,
                "contact_type" => "vendor",
                "customer_sub_type" => "business",
                "email" => $user->email ?? '',
                "phone" => $shop->phone,
                "credit_limit" => 0,
                "is_portal_enabled" => false,
                "payment_terms" => 15,
                "payment_terms_label" => "Net 15",
                "place_of_contact" => $stateCode,
                "gst_treatment" => $gstin ? "business_gst" : "business_none",
                "contact_persons" => [
                    [
                        "first_name" => $shop->name,
                        "email" => $user->email ?? '',
                        "phone" => $shop->phone,
                        "mobile" => $shop->phone,
                    ]
                ],
                "billing_address" => [
                    "attention" => $shop->name,
                    "address"   => $shop->address,
                    "city"      => $user->city ?? 'Not Available',
                    "state"     => $stateName,
                    "state_code"=> $stateCode,
                    "zip"       => $user->postal_code ?? '000000',
                    "country"   => $user->country ?? 'India',
                    "phone"     => $shop->phone,
                ],
                "shipping_address" => [
                    "attention" => $shop->name,
                    "address"   => $shop->address,
                    "city"      => $user->city ?? 'Not Available',
                    "state"     => $stateName,
                    "state_code"=> $stateCode,
                    "zip"       => $user->postal_code ?? '000000',
                    "country"   => $user->country ?? 'India',
                    "phone"     => $shop->phone,
                ],
                "notes" => "Updated via API",
            ];

            if (!empty($gstin)) {
                $payload['gst_no'] = $gstin;
            }

            // ✅ API URL
            $url = "https://www.zohoapis.in/books/v3/contacts/{$zoho_seller_id}?organization_id={$orgId}";

            // ✅ API Call
            $response = Http::withHeaders($this->getAuthHeaders())->put($url, $payload);

            if ($response->successful()) {
                return response()->json([
                    'message' => 'Seller contact updated successfully in Zoho.',
                    'data'    => $response->json()
                ]);
            }

            // ❌ Error Handling
            return response()->json([
                'error'   => 'Failed to update seller in Zoho.',
                'details' => $response->json()
            ], $response->status());
    }

    public function bulkCreateSellersInZoho()
    {
        $orgId = $this->orgId;

        // ✅ Get all sellers where zoho_seller_id is NULL (not yet synced)
        // $sellers = Seller::whereNull('zoho_seller_id')
        //             ->pluck('user_id')
        //             ->toArray();
        // ✅ Get all sellers where zoho_seller_id is NULL (not yet synced)
        $sellers = Seller::whereNull('zoho_seller_id')
                ->limit(10)
                ->pluck('user_id')
                ->toArray();

        if (empty($sellers)) {
            return response()->json([
                'message' => 'No sellers found to create in Zoho.',
            ]);
        }

        $success = [];
        $failed = [];

        foreach ($sellers as $user_id) {
            try {
                // Call your existing function
                $result = $this->createNewSellerInZoho($user_id);

                // Check response
                if ($result->status() == 200) {
                    $success[] = $user_id;
                } else {
                    $failed[] = $user_id;
                }

                // Optional: Delay between API hits to avoid hitting rate limits
                usleep(300000); // 300 milliseconds (0.3 second)

            } catch (\Exception $e) {
                $failed[] = $user_id;
            }
        }

        return response()->json([
            'message' => 'Bulk upload completed.',
            'total' => count($sellers),
            'success' => $success,
            'failed' => $failed
        ]);
    }


    // seller end

   // Customer Start
    public function createNewCustomerInZoho($party_code)
    {
        $orgId = $this->orgId;

        $address = Address::where('acc_code', $party_code)->firstOrFail();

        // Get state code and name from `states` table
        $state = State::where('id', $address->state_id)->first();
        $stateCode = $state->state_code ?? 'TN';
        $stateName = $state->name ?? 'Tamil Nadu';

        $gstTreatment = !empty($address->gstin) ? 'business_gst' : 'business_none';
        $gstNo = $address->gstin ?? '';

        $payload = [
            "contact_name" => $address->company_name . ' - ' . $address->city . ' (' . $address->acc_code . ')',

            "company_name" => $address->company_name,
            "contact_type" => "customer",
            "customer_sub_type" => "business",
            "email" => $address->email ?? "",
            "phone" => $address->phone,
            "credit_limit" => 0,
            "is_portal_enabled" => false,
            "payment_terms" => 15,
            "payment_terms_label" => "Net 15",
            "place_of_contact" => $stateCode,
            "gst_no" => $gstNo,
            "gst_treatment" => $gstTreatment,

            "contact_persons" => [
                [
                    "first_name" => $address->company_name,
                    "email" => $address->email ?? "",
                    "phone" => $address->phone,
                    "mobile" => $address->phone
                ]
            ],

            "billing_address" => [
                "attention" => $address->company_name,
                "address" => $address->address,
                "street2" => $address->address_2 ?? '',
                "city" => $address->city,
                "state" => $stateName,
                "state_code" => $stateCode,
                "zip" => $address->postal_code,
                "country" => "India",
                "phone" => $address->phone
            ],

            "shipping_address" => [
                "attention" => $address->company_name,
                "address" => $address->address,
                "street2" => $address->address_2 ?? '',
                "city" => $address->city,
                "state" => $stateName,
                "state_code" => $stateCode,
                "zip" => $address->postal_code,
                "country" => "India",
                "phone" => $address->phone
            ],

            "custom_fields" => [
                [
                    "api_name" => "cf_party_code",
                    "value" => $address->acc_code
                ]
            ]
        ];

        $response = Http::withHeaders($this->getAuthHeaders())
            ->post("https://www.zohoapis.in/books/v3/contacts?organization_id={$orgId}", $payload);

        if ($response->successful() && isset($response['contact']['contact_id'])) {
            $zohoContactId = $response['contact']['contact_id'];

            // ✅ Save to database
            $address->zoho_customer_id = $zohoContactId;
            $address->save();

            return response()->json([
                'message' => 'Contact created and synced with Zoho.',
                'zoho_customer_id' => $zohoContactId
            ]);
        }

        return response()->json([
            'error' => 'Failed to create contact',
            'response' => $response->json()
        ], 500);
    }

    public function updateCustomerInZoho($zoho_contact_id)
    {
        $orgId = $this->orgId;

        // Fetch address based on Zoho contact ID
        $address = Address::where('zoho_customer_id', $zoho_contact_id)->firstOrFail();
        $state = State::where('id', $address->state_id)->first();
        $stateCode = $state->state_code ?? 'TN';
        $stateName = $state->name ?? 'Tamil Nadu';

        // Default values
        $gst_no = null;
        $gst_treatment = 'business_none';

        // ✅ Check GST from address table
       if (!empty($address->gstin)) {

            // Verify GST using API
            $gstResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://appyflow.in/api/verifyGST', [
                'key_secret' => env('APPYFLOW_KEYSECRET'),
                'gstNo' => $address->gstin,
            ]);

            if ($gstResponse->successful()) {
                $gst_data = $gstResponse->json(); // ✅ use json() instead of json_decode()

                // Print once to check structure (optional)
                // echo "<pre>"; print_r($gst_data); die();

                if (
                    isset($gst_data['taxpayerInfo']['gstin']) &&
                    $gst_data['taxpayerInfo']['sts'] === 'Active'
                ) {
                    // Valid GSTIN
                    $gst_no = $gst_data['taxpayerInfo']['gstin'];
                    $gst_treatment = 'business_gst';

                }
            }
        }


        // Build the payload for PUT request
        $payload = [
            
            "gst_no" => $gst_no,
            "gst_treatment" => $gst_treatment,
            
        ];

        // Make the PUT request to Zoho
        $response = Http::withHeaders($this->getAuthHeaders())
            ->put("https://www.zohoapis.in/books/v3/contacts/{$zoho_contact_id}?organization_id={$orgId}", $payload);

        if ($response->successful()) {
            return response()->json([
                'message' => 'Contact updated successfully in Zoho.',
                'zoho_customer_id' => $zoho_contact_id
            ]);
        }

        return response()->json([
            'error' => 'Failed to update contact',
            'response' => $response->json()
        ], 500);
    }

    public function bulkUpdateMissingGstinCustomers()
    {
        // Step 1: Get addresses that have Zoho ID but no GSTIN
        $addresses = Address::whereNotNull('zoho_customer_id')
            ->whereNull('gstin')
            ->where('id',5928)
            ->orderBy('id', 'DESC')
            ->get();

        // echo "<pre>";
        // print_r($addresses->toArray());
        // die();

        $updated = 0;
        $failed = [];

        // Step 2: Loop and update each one
        foreach ($addresses as $address) {
            try {
                 $this->updateCustomerInZoho($address->zoho_customer_id);
                $updated++;
            } catch (\Exception $e) {
                $failed[] = [
                    'id' => $address->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Bulk update completed.',
            'updated_count' => $updated,
            'failed' => $failed,
        ]);
    }


    public function updateZohoRegisteredUnregisteredAddressesOnly()
    {
        $addresses = Address::whereNotNull('zoho_customer_id')->get();

        die();

        foreach ($addresses as $address) {
            try {
                exit;
                echo "🔄 Updating Zoho customer: {$address->zoho_customer_id} ({$address->company_name})\n";

                $this->updateCustomerInZoho($address->zoho_customer_id);

                echo "✅ Successfully updated {$address->company_name}\n";
            } catch (\Exception $e) {
                echo "❌ Error updating {$address->company_name}: {$e->getMessage()}\n";
            }
            exit;
        }

        echo "🎯 Done updating all registered Zoho contacts.\n";
    }


    public function bulkCreateZohoCustomers()
    {
        $addresses = Address::whereNull('zoho_customer_id')->get();
        $created = 0;
        $failed = [];

        foreach ($addresses as $address) {
            try {
                $this->createNewCustomerInZoho($address->acc_code);
                $created++;
            } catch (\Exception $e) {
                $failed[] = [
                    'party_code' => $address->acc_code,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'message' => 'Zoho Customer Sync Complete.',
            'created_count' => $created,
            'failed' => $failed
        ]);
    }

    public function updateAllZohoCustomersInAddresses()
    {
        $orgId = $this->orgId;
        $page = 1;
        $perPage = 200;
        $totalUpdated = 0;

        do {
            // Call Zoho API with pagination
            $response = Http::withHeaders($this->getAuthHeaders())
                ->get("https://www.zohoapis.in/books/v3/contacts", [
                    'organization_id' => $orgId,
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

            $contacts = $response->json('contacts') ?? [];

            foreach ($contacts as $contact) {
                $contactId = $contact['contact_id'];
                $partyCode = $contact['cf_party_code'] ?? null;

                if ($partyCode) {
                    $address = Address::where('acc_code', $partyCode)->first();
                    if ($address && $address->zoho_customer_id !== $contactId) {
                        $address->zoho_customer_id = $contactId;
                        $address->save();
                        $totalUpdated++;
                    }
                }
            }

            $morePages = count($contacts) === $perPage;
            $page++;

        } while ($morePages);

        return response()->json([
            'message' => 'All Zoho contacts processed.',
            'total_updated' => $totalUpdated
        ]);
    }
   // Customer End

   // Item Start

    public function newItemPushInZoho($part_no)
    {
        $orgId = $this->orgId;

        // ✅ Fetch product from DB using part_no
        $product = Product::where('part_no', $part_no)->firstOrFail();

        $payload = [
             "name" => ($product->name ?? '') . ' (' . $product->part_no . ')', // ✅ Proper format
            "rate" => $product->mrp ?? 0,
            "description" => $product->generic_name ?? $product->name,
            "sku" => $product->part_no, // ✅ SKU = part_no
            "product_type" => "goods",
            "unit" => "pcs",
            "hsn_or_sac" => $product->hsncode ?? '',
            "item_type" => "sales_and_purchases",
            "purchase_description" => $product->name ?? 'Purchase',
            "purchase_rate" => $product->purchase_price ?? 0,
            "track_inventory" => true,  // ✅ Enable inventory tracking

            // Optional - Custom fields
            // "custom_fields" => [
            //     [
            //         "api_name" => "cf_category",
            //         "value" => "Storage Devices"
            //     ]
            // ]
        ];

        $response = Http::withHeaders($this->getAuthHeaders())
            ->post("https://www.zohoapis.in/books/v3/items?organization_id={$orgId}", $payload);

        if ($response->successful() && isset($response['item']['item_id'])) {
            $product->zoho_item_id = $response['item']['item_id'];
            $product->save();

            return response()->json(['message' => 'Item created in Zoho', 'item_id' => $product->zoho_item_id]);
        }

        return response()->json(['error' => 'Failed to create item', 'response' => $response->json()], 500);
    }

    public function pushBulkItem()
    {
        // Fetch products where zoho_item_id is NULL
        $products = Product::whereNull('zoho_item_id')->get();

        if ($products->isEmpty()) {
            return response()->json(['message' => 'No products to push.']);
        }

        $results = [];

        foreach ($products as $product) {
            // Call existing single item push logic
            $response = $this->newItemPushInZoho($product->part_no);

            // Store each result for review
            $results[] = [
                'part_no' => $product->part_no,
                'status' => $response->status() === 200 ? 'success' : 'failed',
                'response' => $response->original ?? $response->json()
            ];
        }

        return response()->json([
            'message' => 'Bulk item sync complete.',
            'results' => $results
        ]);
    }


    public function updateItemInZoho($part_no="MZ33356")
    {
        $orgId = $this->orgId;

        // 🔍 Fetch product
        $product = Product::where('part_no', $part_no)->whereNotNull('zoho_item_id')->firstOrFail();

        // 🔐 Get Zoho Item ID
        $zohoItemId = $product->zoho_item_id;

        // 🛠️ Build Payload
        $payload = [
            "name" => ($product->name ?? '') . ' (' . $product->part_no . ')',
            "rate" => $product->mrp ?? 0,
            "description" => $product->generic_name ?? $product->name,
            "sku" => $product->part_no,
            "product_type" => "goods",
            "unit" => "pcs",
            "hsn_or_sac" => $product->hsncode ?? '',
            "item_type" => "sales_and_purchases",
            "track_inventory" => true,
            "purchase_description" => $product->name ?? 'Purchase',
            "purchase_rate" => $product->purchase_price ?? 0,
        ];

        // 📤 Send PUT Request to Zoho
        $response = Http::withHeaders($this->getAuthHeaders())
            ->put("https://www.zohoapis.in/books/v3/items/{$zohoItemId}?organization_id={$orgId}", $payload);

        if ($response->successful()) {
            return response()->json([
                'message' => 'Zoho item updated successfully',
                'item_id' => $zohoItemId
            ]);
        }

        return response()->json([
            'error' => 'Failed to update item',
            'response' => $response->json()
        ], 500);
    }

    public function updateAllZohoItemsInProducts()
    {
        $orgId = $this->orgId;
        $page = 1;
        $perPage = 200;
        $totalUpdated = 0;

        do {
            $response = Http::withHeaders($this->getAuthHeaders())
                ->get("https://www.zohoapis.in/books/v3/items", [
                    'organization_id' => $orgId,
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

            $items = $response->json('items') ?? [];

            foreach ($items as $item) {
                $itemId = $item['item_id'];
                $sku = $item['sku'] ?? null;

                if ($sku) {
                    $product = Product::where('part_no', $sku)->first();

                    if ($product && $product->zoho_item_id !== $itemId) {
                        $product->zoho_item_id = $itemId;
                        $product->save();
                        $totalUpdated++;
                    }
                }
            }

            $morePages = count($items) === $perPage;
            $page++;

        } while ($morePages);

        return response()->json([
            'message' => 'Zoho item sync completed.',
            'total_updated' => $totalUpdated
        ]);
    }
    // Item End

    // Ewaybill Start

    public function generateZohoEWayBill(Request $request)
    {
        $invoiceId = $request->entity_id;

        // 1. Fetch Invoice Order + related data
        $invoice = InvoiceOrder::with(['address'])->where('zoho_invoice_id', $invoiceId)->firstOrFail();

        // 2. Prepare payload from form and model
        $payload = [
            "transaction_type" => "billto_shipto",
            "transportation_mode" => $request->transportation_mode,
            "distance" => (int) $request->distance,
            "vehicle_number" => !empty($request->vehicle_number) ? $request->vehicle_number : '',
            "sub_supply_type" => "supply",
            "vehicle_type" => "regular",
            "entity_id" => $invoiceId,
            "entity_type" => "invoice",
            // "dispatch_from_address_id" => $request->dispatch_from_address_id,
            "ship_to_state_code" => $request->ship_to_state_code,
            "transporter_id" => $request->transporter_id,
            "action" => "save_generate"
        ];

        // echo "<pre>";
        // print_r($payload);
        // die();


        // 3. Prepare headers
        $headers = $this->getAuthHeaders();
        $headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';

        // 4. Send request to Zoho
        $response = Http::withHeaders($headers)
            ->asForm()
            ->post("https://www.zohoapis.in/books/v3/ewaybills?organization_id={$this->orgId}", [
                'JSONString' => json_encode($payload)
            ]);

         $result = $response->json();


        // 5. Return response
        // ✅ 5. If success, store in eway_bills table
        if ($response->successful() && isset($result['ewaybill']['ewaybill_id'])) {

            $eway = $result['ewaybill'];

            // 🔁 Fetch complete invoice details to get IRN-related data
            $invoiceData = $this->getInvoice($invoice->zoho_invoice_id);
            $einvoice = $invoiceData['invoice']['einvoice_details'] ?? null;

            EwayBill::create([
                'invoice_order_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'irn_no' => $einvoice['inv_ref_num'] ?? null,
                'party_code' => $invoice->party_code,
                'zoho_invoice_id' => $invoice->zoho_invoice_id,
                'ewaybill_id' => $eway['ewaybill_id'],
                'ewaybill_number' => $eway['ewaybill_number'],
                'entity_id' => $eway['entity_id'],
                'entity_type' => $eway['entity_type'],
                'entity_number' => $eway['entity_number'],
                'entity_date' => Carbon::createFromFormat('d/m/Y', $eway['entity_date_formatted'])->format('Y-m-d'), // ✅ FIXED HERE
                'supplier_gstin' => $eway['supplier_gstin'],
                'customer_name' => $eway['customer_name'],
                'customer_gstin' => $eway['customer_gstin'],
                'ewaybill_status' => $eway['ewaybill_status'],
                'ewaybill_status_formatted' => $eway['ewaybill_status_formatted'],
                'transporter_id' => $eway['transporter_id'],
                'transporter_name' => $eway['transporter_name'],
                'transporter_registration_id' => $eway['transporter_registration_id'],
                'sub_supply_type' => $eway['sub_supply_type'],
                'distance' => $eway['distance'],
                'vehicle_number' => $eway['vehicle_number'],
                'ship_to_state_code' => $eway['ship_to_state_code'],
                'entity_total' => $eway['entity_total'],
                'ewaybill_date' => $eway['ewaybill_date'],
                'ewaybill_start_date' => $eway['ewaybill_start_date'] ?? null,
                'ewaybill_expiry_date' => $eway['ewaybill_expiry_date'] ?? null,

                // ✅ Newly added fields
                'place_of_dispatch' => $eway['place_of_dispatch'] ?? null,
                'place_of_delivery' => $eway['place_of_delivery'] ?? null,
            ]);
            return redirect()->back()->with('success', 'e-Way Bill generated successfully.');
        }

        // return response()->json($result, $response->status());
        return redirect()->back()->with('error', $result['message'] ?? 'Failed to generate e-Way Bill.');
    }


    public function cancelEWayBill($ewaybillId)
    {
        $payload = [
            "reason" => "duplicate",
            "remarks" => "Cancelled due to duplication"
        ];

        $headers = $this->getAuthHeaders();
        $headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';

        $response = Http::withHeaders($headers)
            ->asForm()
            ->post("https://www.zohoapis.in/books/v3/ewaybills/{$ewaybillId}/cancel?organization_id={$this->orgId}", [
                'JSONString' => json_encode($payload)
            ]);

            if ($response->successful()) {
                    // ✅ DELETE the eway bill record from DB
                    EwayBill::where('ewaybill_id', $ewaybillId)->delete();

                    return redirect()->back()->with('success', 'E-Way Bill cancelled.');
                }

            return redirect()->back()->with('error', 'Failed to cancel E-Way Bill.');

        //return $response->json();
    }

    public function deleteEWayBill($ewaybillId)
    {
        $response = Http::withHeaders($this->getAuthHeaders())
            ->delete("https://www.zohoapis.in/books/v3/ewaybills/{$ewaybillId}?organization_id={$this->orgId}");

        return $response->json();
    }

    public function getZohoTransporters()
    {
        $orgId = $this->orgId;
        $page = 1;
        $perPage = 200;
        $allTransporters = [];
    
        do {
            $response = Http::withHeaders($this->getAuthHeaders())->get(
                'https://www.zohoapis.in/books/v3/ewaybills/transporters',
                [
                    'organization_id' => $orgId,
                    'page' => $page,
                    'per_page' => $perPage
                ]
            );
    
            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Failed to fetch transporters.',
                    'response' => $response->json()
                ], 500);
            }
    
            $data = $response->json();
            $transporters = $data['transporters'] ?? [];
            $allTransporters = array_merge($allTransporters, $transporters);
    
            $hasMore = $data['page_context']['has_more_page'] ?? false;
            $page++;
    
        } while ($hasMore);
    
        return response()->json([
            'message' => 'Transporters fetched successfully.',
            'data' => [
                'code' => 0,
                'message' => 'success',
                'transporters' => $allTransporters
            ]
        ]);
    }

    public function getTransporters()
    {
        $allTransporters = Carrier::orderBy('name','ASC')->get();
    
        return response()->json([
            'message' => 'Transporters fetched successfully.',
            'data' => [
                'code' => 0,
                'message' => 'success',
                'transporters' => $allTransporters
            ]
        ]);
    }

    public function createEwayBillTransporter(Request $request)
    {
        $headers = $this->getAuthHeaders();

        $transporterName = $request->query('transporter_name', 'DELHIVERY LIMITED');
        $transporterRegId = $request->query('transporter_registration_id', '06AAPCS9575E1ZR');

        $payload = [
            'transporter_name' => $transporterName,
            'transporter_registration_id' => $transporterRegId,
        ];

        $url = "https://www.zohoapis.in/books/v3/ewaybills/transporters?organization_id={$this->orgId}";

        $response = Http::withHeaders($headers)->post($url, $payload);

        if ($response->successful()) {
            $zohoData = $response->json();
            $transporter = $zohoData['transporter'] ?? null;

            if ($transporter && isset($transporter['transporter_id'], $transporter['transporter_name'], $transporter['transporter_registration_id'])) {
                // Create carrier entry
                Carrier::create([
                    'name' => $transporter['transporter_name'],
                    'gstin' => $transporter['transporter_registration_id'],
                    'zoho_transporter_id' => $transporter['transporter_id'],
                    'status' => 1,
                    'free_shipping' => 0,
                    'all_india' => 1,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transporter created successfully in Zoho and added to carriers.',
                'zoho_response' => $zohoData
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to create transporter.',
            'zoho_response' => $response->json()
        ], $response->status());
    }

    public function addVehicleToEWayBill(Request $request, $ewaybillId)
    {
        $orgId = $this->orgId;

        $vehicleData = [
            "transportation_mode" => "road",                  // required
            "vehicle_number" => "TN47BY1234",                 // required
            "vehicle_type" => "regular",                      // required
            "from_place" => "Karur",                          // required
            "from_state" => "TN",                             // required
            "reason" => "break_down",                         // required
            "remarks" => "",                                  // optional
            "transporter_document_date" => "2025-04-12",      // required
            "transporter_document_number" => "12345"          // required
        ];

        $headers = $this->getAuthHeaders();
        $headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';

        $url = "https://www.zohoapis.in/books/v3/ewaybills/{$ewaybillId}/vehicles";

        $response = Http::withHeaders($headers)
            ->asForm()
            ->post($url, [
                'organization_id' => $orgId,
                'JSONString' => json_encode($vehicleData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);

        if ($response->successful()) {
            return response()->json([
                'message' => 'Vehicle details updated successfully.',
                'data' => $response->json()
            ]);
        }

        return response()->json([
            'error' => 'Failed to update vehicle details.',
            'response' => $response->json()
        ], 500);
    }

     public function getDispatchAddresses()
    {
        $response = Http::withHeaders($this->getAuthHeaders())
            ->get("https://www.zohoapis.in/books/v3/organization/address/dispatch?organization_id={$this->orgId}");

        return $response->json();
    }

    public function addDispatchAddressToZoho(Request $request)
    {
        $branchCode = strtoupper($request->branch_code ?? 'DEL');

        $branches = [
            'KOL' => [
                'contact_name' => 'Amir Madraswala',
                'address' => '257B, BIPIN BEHARI GANGULY STREET',
                'street2' => '2ND FLOOR',
                'city' => 'KOLKATA',
                'state_code' => 'WB',
                'zip' => '700012',
            ],
            'MUM' => [
                'contact_name' => 'Hussain',
                'address' => 'HARIHAR COMPLEX F-8, HOUSE NO-10607, ANUR DEPODE ROAD',
                'street2' => 'GODOWN NO.7, GROUND FLOOR',
                'city' => 'MUMBAI',
                'state_code' => 'MH',
                'zip' => '421302',
            ],
            'DEL' => [
                'contact_name' => 'Mustafa Worliwala',
                'address' => 'Khasra No. 58/15',
                'street2' => 'Pal Colony, Village Rithala',
                'city' => 'New Delhi',
                'state_code' => 'DL',
                'zip' => '110085',
            ]
        ];

        if (!isset($branches[$branchCode])) {
            return response()->json(['error' => 'Invalid branch code. Use KOL, MUM, or DEL.'], 422);
        }

        $data = $branches[$branchCode];

        $dispatchData = [
            "attention" => $data['contact_name'],
            "address" => $data['address'],
            "street2" => $data['street2'],
            "city" => $data['city'],
            "state_code" => $data['state_code'],
            "zip" => $data['zip'],
            "country" => "India"
        ];

        $response = Http::withHeaders($this->getAuthHeaders())
            ->asMultipart()
            ->post("https://www.zohoapis.in/books/v3/organization/address/dispatch", [
                [ 'name' => 'organization_id', 'contents' => $this->orgId ],
                [ 'name' => 'JSONString', 'contents' => json_encode($dispatchData) ]
            ]);

        return $response->json();
    }

    public function deleteDispatchAddressFromZoho($dispatchId)
    {
        $response = Http::withHeaders($this->getAuthHeaders())
            ->delete("https://www.zohoapis.in/books/v3/organization/address/dispatch/{$dispatchId}?organization_id={$this->orgId}");

        return $response->json();
    }


    // Ewaybill End

    // E-invoice Start

     public function pushEInvoiceToIRP($zoho_invoice_id)
    {
        //Push Invoice to IRP (Generate IRN)
        $orgId = $this->orgId;
        

        // 1. Fetch invoice with related shipping address
        $invoice = InvoiceOrder::with('shipping_address')
            ->where('zoho_invoice_id', $zoho_invoice_id)
            ->firstOrFail();

         // 2. GST Check
        if (empty($invoice->shipping_address->gstin)) {
            return response()->json(['error' => 'Customer does not have a GSTIN. Cannot generate IRN.'], 403);
        }
        // 3. Push to IRP
        $url = "https://www.zohoapis.in/books/v3/invoices/{$zoho_invoice_id}/einvoice/push?organization_id={$orgId}";
        $response = Http::withHeaders($this->getAuthHeaders())->post($url);
        $result = $response->json();

         // 4. If success, mark status = 1 and fetch IRN details
        if ($response->successful() && isset($result['code']) && $result['code'] == 0) {
            
            // 🔁 Fetch complete invoice details to get IRN-related data
            $invoiceData = $this->getInvoice($zoho_invoice_id);
            $einvoice = $invoiceData['invoice']['einvoice_details'] ?? null;

            if ($einvoice) {
                $invoice->update([
                    'irn_no'     => $einvoice['inv_ref_num'] ?? null,
                    'ack_number' => $einvoice['ack_number'] ?? null,
                    'ack_date' => !empty($einvoice['ack_date'])  ? Carbon::parse($einvoice['ack_date'])->format('Y-m-d H:i:s')  : null,
                    'qr_link'    => $einvoice['qr_link'] ?? null,
                    'einvoice_status' => 1,
                ]);

            }
        }

        return response()->json($result, $response->status());

        // return $response->json();
    }

    public function cancelIRNWithin24Hrs(Request $request, $zoho_invoice_id)
    {
        $orgId = $this->orgId;

        $invoice = InvoiceOrder::with('shipping_address')
            ->where('zoho_invoice_id', $zoho_invoice_id)
            ->firstOrFail();

        if (empty($invoice->shipping_address->gstin)) {
            return response()->json(['error' => 'Customer does not have a GSTIN. Cannot cancel IRN.'], 403);
        }

        $reason = $request->query('reason');
        $reasonType = $request->query('reason_type', 'data_entry_mistake');

        if (empty($reason)) {
            return response()->json(['error' => 'Cancellation reason is required.'], 422);
        }

        $url = "https://www.zohoapis.in/books/v3/invoices/{$zoho_invoice_id}/einvoice/cancel?organization_id={$orgId}";

        $payload = [
            'reason' => $reason,
            'reason_type' => $reasonType
        ];

        $response = Http::withHeaders($this->getAuthHeaders())->post($url, $payload);
        $result = $response->json();

        if ($response->successful() && isset($result['code']) && $result['code'] == 0) {
            $invoice->update([
                'einvoice_status' => 2,
                'irn_no' => null,
                'ack_number' => null,
                'ack_date' => null,
                'qr_link' => null
            ]);
        }

        return response()->json($result, $response->status());
    }

    public function cancelIRNAfter24Hours(Request $request, $zoho_invoice_id)
    {
        $orgId = $this->orgId;

        $invoice = InvoiceOrder::with('shipping_address')
            ->where('zoho_invoice_id', $zoho_invoice_id)
            ->firstOrFail();

        if (empty($invoice->shipping_address->gstin)) {
            return response()->json(['error' => 'Customer does not have a GSTIN. Cannot cancel IRN.'], 403);
        }

        $reason = $request->query('reason');

        if (empty($reason)) {
            return response()->json(['error' => 'Cancellation reason is required.'], 422);
        }

        $url = "https://www.zohoapis.in/books/v3/invoices/{$zoho_invoice_id}/einvoice/status/cancel?organization_id={$orgId}";

        $payload = [
            'reason' => $reason
        ];

        $response = Http::withHeaders($this->getAuthHeaders())->post($url, $payload);

        return $response->json();
    }


    public function syncEinvoiceAndEwayFromZoho()
    {
        $orgId = $this->orgId;

        // Step 1: Get all InvoiceOrders where IRN not generated
        $invoices = InvoiceOrder::whereNull('irn_no')->get();

        foreach ($invoices as $invoice) {
            $zohoInvoiceId = $invoice->zoho_invoice_id;

            // Step 2: Get invoice data from Zoho
            $res = $this->getInvoice($zohoInvoiceId);

            if (!isset($res['invoice'])) {
                continue;
            }

            $data = $res['invoice'];

            // Step 3: If e-invoice info available, update InvoiceOrder
            if (isset($data['einvoice_details'])) {
                $invoice->update([
                    'irn_no' => $data['einvoice_details']['inv_ref_num'] ?? null,
                    'ack_number' => $data['einvoice_details']['ack_number'] ?? null,
                    'ack_date' => $data['einvoice_details']['ack_date'] ?? null,
                    'qr_link' => $data['einvoice_details']['qr_link'] ?? null,
                    'einvoice_status' => 1,
                ]);
            }

            // Step 4: If e-way bill info available, insert in `eway_bills`
            if (isset($data['ewaybill_id']) && !EwayBill::where('ewaybill_id', $data['ewaybill_id'])->exists()) {
                EwayBill::create([
                    'invoice_order_id' => $invoice->id,
                    'invoice_no' => $data['invoice_number'] ?? '',
                    'party_code' => $data['cf_party_code'] ?? '',
                    'zoho_invoice_id' => $data['invoice_id'] ?? '',
                    'irn_no' => $data['einvoice_details']['inv_ref_num'] ?? '',
                    'ack_number' => $data['einvoice_details']['ack_number'] ?? '',
                    'ack_date' => $data['einvoice_details']['ack_date'] ?? null,
                    'ewaybill_id' => $data['ewaybill_id'] ?? '',
                    'ewaybill_number' => $data['ewaybill_number'] ?? '',
                    'entity_id' => $data['invoice_id'],
                    'entity_type' => 'invoice',
                    'entity_number' => $data['invoice_number'],
                    'entity_date' => $data['date'] ?? now(),
                    'supplier_gstin' => $data['gst_no'] ?? '',
                    'customer_name' => $data['customer_name'] ?? '',
                    'customer_gstin' => $data['tax_reg_no'] ?? '',
                    'ewaybill_status' => $data['ewaybill_status'] ?? '',
                    'ewaybill_status_formatted' => $data['ewaybill_status_formatted'] ?? '',
                    'transporter_id' => $data['transporter_id'] ?? '',
                    'transporter_name' => $data['transporter_name'] ?? '',
                    'transporter_registration_id' => $data['transporter_registration_id'] ?? '',
                    'sub_supply_type' => $data['sub_supply_type'] ?? 'supply',
                    'distance' => 0,
                    'place_of_dispatch' => $data['branch_name'] ?? '',
                    'place_of_delivery' => $data['shipping_address']['city'] ?? '',
                    'vehicle_number' => $data['eway_bill_details']['vehicle_number'] ?? '',
                    'ship_to_state_code' => $data['place_of_supply'] ?? '',
                    'entity_total' => $data['total'] ?? 0,
                    'ewaybill_date' => $data['ewaybill_date'] ?? null,
                    'ewaybill_start_date' => null,
                    'ewaybill_expiry_date' => null,
                ]);
            }
        }

        return response()->json(['message' => 'Sync completed.']);
    }


    public function cancelInvoiceChallans(Request $request)
    {
        $challanIds = explode(',', $request->challans);
        $invoiceId = $request->invoice_id;

         // 1. Fetch the invoice
         $invoice = InvoiceOrder::find($invoiceId);

         if (!$invoice) {
            return back()->with('error', 'Invoice not found.');
         }

        // Check if the invoice has an IRN number
         if ($invoice->irn_no) {
            return back()->with('error', 'Cannot cancel an invoice with IRN.');
         }
       

        if (!empty($challanIds)) {
            Challan::whereIn('id', $challanIds)
                ->update(['invoice_status' => 0]);
        }

        // 3. Cancel the invoice in Zoho
        $zohoResponse = $this->cancelZohoInvoice($invoice->zoho_invoice_id);

        if ($zohoResponse['status'] !== 'success') {
            return back()->with('error', $zohoResponse['message']);
        }

         // 4. Mark invoice cancel status
        InvoiceOrder::where('id', $invoiceId)
            ->update(['invoice_cancel_status' => 1]);

        return back()->with('success', 'Challan(s) invoice status has been reset successfully.');
    }



    // tax start
    public function getZohoTaxes()
    {
        $orgId = $this->orgId; // your org ID
        $headers = $this->getAuthHeaders(); // must return Authorization header

        $url = "https://www.zohoapis.in/books/v3/settings/taxes?organization_id={$orgId}";

        $response = Http::withHeaders($headers)->get($url);

        if ($response->successful()) {
            return response()->json([
                'message' => 'Taxes fetched successfully.',
                'taxes' => $response->json()['taxes']
            ]);
        } else {
            return response()->json([
                'error' => 'Failed to fetch Zoho taxes.',
                'details' => $response->json()
            ], 500);
        }
    }

    public function syncZohoTaxes()
    {
        $orgId = $this->orgId;
        $headers = $this->getAuthHeaders();

        $url = "https://www.zohoapis.in/books/v3/settings/taxes?organization_id={$orgId}";

        $response = Http::withHeaders($headers)->get($url);

        if (!$response->successful()) {
            return response()->json([
                'error' => 'Failed to fetch taxes from Zoho.',
                'details' => $response->json()
            ], 500);
        }

        $taxes = $response->json('taxes') ?? [];

        foreach ($taxes as $tax) {
            ZohoTax::updateOrCreate(
                ['tax_id' => $tax['tax_id']],
                [
                    'tax_name' => $tax['tax_name'] ?? null,
                    'tax_percentage' => $tax['tax_percentage'] ?? 0,
                    'tax_type' => $tax['tax_type'] ?? null,
                    'tax_specific_type' => $tax['tax_specific_type'] ?? null,
                    'tax_authority_id' => $tax['tax_authority_id'] ?? null,
                    'tax_authority_name' => $tax['tax_authority_name'] ?? null,
                    'output_tax_account_name' => $tax['output_tax_account_name'] ?? null,
                    'tax_account_id' => $tax['tax_account_id'] ?? null,
                    'tax_specification' => $tax['tax_specification'] ?? null,
                    'is_inactive' => $tax['is_inactive'] ?? false,
                    'is_default_tax' => $tax['is_default_tax'] ?? false,
                    'is_editable' => $tax['is_editable'] ?? false,
                    'status' => $tax['status'] ?? null,
                    'start_date' => $tax['start_date'] ?: null,
                    'end_date' => $tax['end_date'] ?: null,
                    'last_modified_time' => $tax['last_modified_time'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Zoho taxes synced successfully.',
            'total_synced' => count($taxes)
        ]);
    }
    // tax end

    // Bills Start

    public function createVendorBill($purchase_invoice_id)
    {
        $orgId = $this->orgId;
        $headers = $this->getAuthHeaders();
        $url = "https://www.zohoapis.in/books/v3/bills?organization_id={$orgId}";

        $purchaseInvoiceId = $purchase_invoice_id; // ✅ Purchase Invoice ID dynamically
        if (!$purchaseInvoiceId) {

            return response()->json(['error' => 'Purchase Invoice ID is missing.']);
        }

        // 1. Fetch purchase invoice
        $purchaseInvoice = PurchaseInvoice::find($purchaseInvoiceId);
        

        // 2. Seller Details
        $seller = Seller::where('id', $purchaseInvoice->seller_id)->first();

      
        if (!$seller || !$seller->zoho_seller_id) {
            $this->sendZohoSyncFailureNotification($purchaseInvoice->purchase_no ?? 'N/A', 'Seller or Zoho Vendor ID missing.');
            return response()->json(['error' => 'Seller or Zoho Vendor ID missing.']);
        }
        $vendorId = $seller->zoho_seller_id;

        // 3. Seller User State
        $user = User::where('id', $seller->user_id)->first();
        if (!$user || !$user->state) {
            $this->sendZohoSyncFailureNotification($purchaseInvoice->purchase_no ?? 'N/A', 'Seller User or State missing.');

            return response()->json(['error' => 'Seller User or State missing.']);
        }

        $state = State::where('name', $user->state)->first();
        
        if (!$state || !$state->state_code) {
            $this->sendZohoSyncFailureNotification($purchaseInvoice->purchase_no ?? 'N/A', 'State or State Code missing.');

            return response()->json(['error' => 'State or State Code missing.']);
        }
        $sourceOfSupply = $state->state_code;

        // 4. Warehouse Details
        $warehouse = Warehouse::find($purchaseInvoice->warehouse_id);
        if (!$warehouse || !$warehouse->zoho_branch_id) {
            $this->sendZohoSyncFailureNotification($purchaseInvoice->purchase_no ?? 'N/A', 'Warehouse or Zoho Branch ID missing.');

            return response()->json(['error' => 'Warehouse or Zoho Branch ID missing.']);
        }
        $locationId = $warehouse->zoho_branch_id;

        $warehouseState = \App\Models\State::where('id', $warehouse->state_id)->first();
        if (!$warehouseState || !$warehouseState->state_code) {
            $this->sendZohoSyncFailureNotification($purchaseInvoice->purchase_no ?? 'N/A', 'Warehouse State Code missing.');

            return response()->json(['error' => 'Warehouse State Code missing.']);
        }
        $destinationOfSupply = $warehouseState->state_code;

        // 5. Fetch Invoice Products
        $invoiceDetails = PurchaseInvoiceDetail::where('purchase_invoice_id', $purchaseInvoiceId)->get();
        if ($invoiceDetails->isEmpty()) {
            $this->sendZohoSyncFailureNotification($purchaseInvoice->purchase_no ?? 'N/A', 'No products found in Purchase Invoice.');

            return response()->json(['error' => 'No products found in Purchase Invoice.']);
        }

        $lineItems = [];
        foreach ($invoiceDetails as $detail) {
            $rate = $detail->price;
            $taxPercent = $detail->tax;

            // 🔍 Check tax type
            if ($detail->igst > 0) {
                // IGST tax
                $zohoTax = \App\Models\ZohoTax::where('tax_percentage', $taxPercent)
                    ->where('tax_specific_type', 'igst')
                    ->first();
            } else {
                // CGST+SGST tax
                $zohoTax = \App\Models\ZohoTax::where('tax_percentage', $taxPercent)
                    ->where('tax_specification', 'intra')
                    ->first();
            }

            if (!$zohoTax || !$zohoTax->tax_id) {
                $this->sendZohoSyncFailureNotification($purchaseInvoice->purchase_no ?? 'N/A', 'Zoho tax_id not found for part no: ' . $detail->part_no);

                return response()->json(['error' => 'Zoho tax_id not found for part no: ' . $detail->part_no]);
            }

            $product = Product::where('part_no',$detail->part_no)->first();
           
            // ✅ Increase rate based on tax percentage
                $finalRate = $rate * (1 + ($taxPercent / 100));

            $lineItems[] = [
                "item_id" => $product->zoho_item_id ?? 'Item',
                "rate" => round($finalRate, 2), // ✅ Tax-added rate
                "quantity" => $detail->qty,
                "hsn_or_sac" => $detail->hsncode,
                "description" => $detail->order_no ?? 'Purchase Item',
                "unit" => "pcs",
                // "tax_exemption_code" => "gst", // Without GST
                "tax_id" => $zohoTax->tax_id, // ✅ Pass 18% GST tax_id
            ];
        }

       

        // 6. Create Payload
        $body = [
            "vendor_id" => $vendorId,
            "currency_id" => "243562200000000007", // INR
            "bill_number" => $purchaseInvoice->purchase_no,
            // "date" => now()->format('Y-m-d'),
            "date" => $purchaseInvoice->created_at->format('Y-m-d'),
            "due_date" => now()->addDays(15)->format('Y-m-d'),
            "gst_treatment" => "business_gst", // Without GST
            "source_of_supply" => $sourceOfSupply,
            "destination_of_supply" => $destinationOfSupply,
            "reference_number" => $purchaseInvoice->seller_invoice_no,
            "is_item_level_tax_calc" => true,
            "is_inclusive_tax" => true,
            "location_id" => $locationId,
            "line_items" => $lineItems,
            "notes" => "Bill created via API",
            "terms" => "Payable within 15 days"
        ];

        // 7. Push to Zoho
        $response = Http::withHeaders($headers)->post($url, $body);

         if ($response->successful()) {
        $responseData = $response->json();

        if (isset($responseData['bill']['bill_id'])) {
            $zohoBillId = $responseData['bill']['bill_id'];

                // ✅ 8. Update PurchaseInvoice table
                $purchaseInvoice->update([
                    'zoho_bill_id' => $zohoBillId
                ]);

                // ✅ 9. Attach PDF if available adding start
                if ($purchaseInvoice->invoice_attachment) {
                    $filePath = public_path($purchaseInvoice->invoice_attachment);
                    $fileName = basename($filePath);
                    
                    if (file_exists($filePath)) {
                        // Call the file attach method
                        $this->attachFileToBill($zohoBillId, $filePath, $fileName);
                    }
                }

                // adding end
            }

            return response()->json([
                'message' => 'Bill created successfully!',
                'zoho_bill_id' => $zohoBillId ?? null,
                'data' => $responseData
            ]);
        } else {
            $errorMessage = json_encode($response->json(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $this->sendZohoSyncFailureNotification($purchaseInvoice->purchase_no ?? 'N/A', $errorMessage);

            return response()->json([
                'error' => 'Failed to create bill',
                'details' => $response->json()
            ], 500);
        }
    }

    

    public function attachFileToBill($billId, $filePath, $fileName = null)
    {
        try {
            $headers = $this->getAuthHeaders(); // ✅ Using existing token handler

            if (!file_exists($filePath)) {
                return response()->json(['error' => 'File not found: ' . $filePath], 404);
            }

            $fileName = $fileName ?? basename($filePath); // fallback to file name from path

            $response = Http::withHeaders([
                'Authorization' => $headers['Authorization'],
            ])->attach(
                'attachment',
                file_get_contents($filePath),
                $fileName
            )->post("https://www.zohoapis.in/books/v3/bills/{$billId}/attachment", [
                'organization_id' => $this->orgId,
            ]);

            $responseBody = $response->json();

            if ($response->successful() && isset($responseBody['code']) && $responseBody['code'] == 0) {
                return response()->json(['success' => true, 'message' => $responseBody['message']]);
            } else {
                return response()->json(['success' => false, 'response' => $responseBody], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function __attachFileToBill($billId)
    {
        try {
            $headers = $this->getAuthHeaders(); // ✅ Using existing token handler

            $filePath = public_path('images/qr.png'); // ✅ From public/images/
            $fileName = 'qr.png';

            if (!file_exists($filePath)) {
                return response()->json(['error' => 'File not found in public/images/'], 404);
            }

            $response = Http::withHeaders([
                'Authorization' => $headers['Authorization'], // ✅ Only pass Authorization, not Content-Type
            ])->attach(
                'attachment',
                file_get_contents($filePath),
                $fileName
            )->post("https://www.zohoapis.in/books/v3/bills/{$billId}/attachment", [
                'organization_id' => $this->orgId,
            ]);

            $responseBody = $response->json();

            if ($response->successful() && isset($responseBody['code']) && $responseBody['code'] == 0) {
                return response()->json(['success' => true, 'message' => $responseBody['message']]);
            } else {
                return response()->json(['success' => false, 'response' => $responseBody], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function bulkCreateVendorBills()
    {
        // ✅ Get only those purchase invoice IDs where seller_id IS NOT NULL and zoho_bill_id IS NULL
        $purchaseInvoiceIds = PurchaseInvoice::whereNotNull('seller_id')
            ->whereNull('zoho_bill_id')
            ->pluck('id')
            ->toArray();
        // $purchaseInvoiceIds = PurchaseInvoice::whereNotNull('seller_id')
        //     ->whereNull('zoho_bill_id')
        //     ->limit(30) // ✅ Limit to 2 records only
        //     ->pluck('id')
        //     ->toArray();
            // echo "<pre>";
            // print_r($purchaseInvoiceIds);
            // die();

        if (empty($purchaseInvoiceIds)) {
            return response()->json(['error' => 'No pending Purchase Invoices found to create bills.']);
        }

        $results = [];

        foreach ($purchaseInvoiceIds as $purchaseInvoiceId) {
            // ✅ Internally call your createVendorBill($purchase_invoice_id)
            $response = $this->createVendorBill($purchaseInvoiceId);

            // ✅ Convert Laravel JsonResponse to array
            $results[] = $response->getData(true);
        }

        return response()->json([
            'message' => 'Bulk Vendor Bills creation completed.',
            'results' => $results
        ]);
    }


    public function updateVendorBill($purchase_invoice_id)
    {
        $orgId = $this->orgId;
        $headers = $this->getAuthHeaders();

        // Step 1: Fetch purchase invoice
        $purchaseInvoice = PurchaseInvoice::find($purchase_invoice_id);
        if (!$purchaseInvoice) {
            return response()->json(['error' => 'Purchase Invoice not found.']);
        }

        if (!$purchaseInvoice->zoho_bill_id) {
            return response()->json(['error' => 'Zoho Bill ID missing.']);
        }

        $billId = $purchaseInvoice->zoho_bill_id;
        $url = "https://www.zohoapis.in/books/v3/bills/{$billId}?organization_id={$orgId}";

        // Step 2: Get Seller & Vendor Details
        $seller = Seller::find($purchaseInvoice->seller_id);
        if (!$seller || !$seller->zoho_seller_id) {
            return response()->json(['error' => 'Seller or Zoho Vendor ID missing.']);
        }
        $vendorId = $seller->zoho_seller_id;

        // Step 3: Get Seller State (source_of_supply)
        $user = User::find($seller->user_id);
        $sourceState = State::where('name', $user->state ?? '')->first();
        if (!$sourceState || !$sourceState->state_code) {
            return response()->json(['error' => 'Seller state or code missing.']);
        }

        // Step 4: Get Warehouse Info (destination_of_supply + location_id)
        $warehouse = Warehouse::find($purchaseInvoice->warehouse_id);
        $warehouseState = State::find($warehouse->state_id);
        if (!$warehouse || !$warehouse->zoho_branch_id || !$warehouseState) {
            return response()->json(['error' => 'Warehouse details incomplete.']);
        }

        // Step 5: Build line items
        $invoiceDetails = PurchaseInvoiceDetail::where('purchase_invoice_id', $purchase_invoice_id)->get();
        if ($invoiceDetails->isEmpty()) {
            return response()->json(['error' => 'No product lines found.']);
        }

        $lineItems = [];
        foreach ($invoiceDetails as $detail) {
            $product = Product::where('part_no', $detail->part_no)->first();

            $taxPercent = $detail->tax;
            $zohoTax = ($detail->igst > 0)
                ? ZohoTax::where('tax_percentage', $taxPercent)->where('tax_specific_type', 'igst')->first()
                : ZohoTax::where('tax_percentage', $taxPercent)->where('tax_specification', 'intra')->first();

            if (!$zohoTax || !$product || !$product->zoho_item_id) {
                return response()->json(['error' => "Missing product or tax for part_no: {$detail->part_no}"]);
            }

            $finalRate = $detail->price * (1 + ($taxPercent / 100));

            $lineItems[] = [
                
                "item_id" => $product->zoho_item_id,
                "rate" => round($finalRate, 2),
                "quantity" => $detail->qty,
                "hsn_or_sac" => $detail->hsncode,
                "description" => $detail->order_no ?? 'Updated item',
                "unit" => "pcs",
                "tax_id" => $zohoTax->tax_id,
            ];
        }

        // Step 6: Build PUT body
        $body = [
            "vendor_id" => $vendorId,
            "currency_id" => "243562200000000007", // INR
            "bill_number" => $purchaseInvoice->purchase_no,
            "reference_number" => $purchaseInvoice->seller_invoice_no,
            "gst_treatment" => "business_gst",
            "source_of_supply" => $sourceState->state_code,
            "destination_of_supply" => $warehouseState->state_code,
            "location_id" => $warehouse->zoho_branch_id,
            "date" => $purchaseInvoice->created_at->format('Y-m-d'),
            "due_date" => now()->addDays(15)->format('Y-m-d'),
            "is_item_level_tax_calc" => true,
            "is_inclusive_tax" => true,
            "line_items" => $lineItems,
            "notes" => "Updated bill via API",
            "terms" => "Payable within 15 days"
        ];

        // Step 7: Call Zoho PUT API
        $response = Http::withHeaders($headers)->put($url, $body);

        if ($response->successful()) {
            return response()->json([
                'message' => 'Vendor bill updated successfully.',
                'data' => $response->json()
            ]);
        } else {
            return response()->json([
                'error' => 'Failed to update vendor bill.',
                'details' => $response->json()
            ], 500);
        }
    }

    public function listZohoBills(Request $request)
    {
        $headers = $this->getAuthHeaders(); // ✅ Your header method
        $orgId = $this->orgId; // ✅ Your organization ID

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20); // You can control from frontend if needed

        $queryParams = [
            'organization_id' => $orgId,
            'page' => $page,
            'per_page' => $perPage,
        ];

        // Optional search filters
        if ($request->filled('search_text')) {
            $queryParams['search_text'] = $request->search_text;
        }
        if ($request->filled('vendor_name')) {
            $queryParams['vendor_name_contains'] = $request->vendor_name;
        }
        if ($request->filled('bill_number')) {
            $queryParams['bill_number_contains'] = $request->bill_number;
        }
        if ($request->filled('status')) {
            $queryParams['filter_by'] = 'Status.' . ucfirst($request->status);
        }
        if ($request->filled('date_start')) {
            $queryParams['date_start'] = $request->date_start;
        }
        if ($request->filled('date_end')) {
            $queryParams['date_end'] = $request->date_end;
        }

        $url = 'https://www.zohoapis.in/books/v3/bills?' . http_build_query($queryParams);

        try {
            $response = Http::withHeaders($headers)->get($url);

            $data = $response->json();

            if (isset($data['bills'])) {
                return response()->json([
                    'success' => true,
                    'bills' => $data['bills'],
                    'page_context' => $data['page_context'] ?? [],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No bills found.',
                    'response' => $data,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching bills from Zoho.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Bills End

    // Credit Note start

    public function pushCreditNoteToIRP($zoho_creditnote_id, $returnJsonOnly = true)
    {
        $orgId = $this->orgId;

        $creditNote = \App\Models\PurchaseInvoice::with('address')
            ->where('zoho_creditnote_id', $zoho_creditnote_id)
            ->firstOrFail();

        if (empty($creditNote->address->gstin)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer does not have a GSTIN. Cannot generate IRN for credit note.'
            ], 403);
        }

        $url = "https://www.zohoapis.in/books/v3/creditnotes/{$zoho_creditnote_id}/einvoice/push?organization_id={$orgId}";
        $response = Http::withHeaders($this->getAuthHeaders())->post($url);
        $result = $response->json();

        if ($response->successful() && isset($result['code']) && $result['code'] == 0) {
            if (isset($result['data']['errors'][0]['code']) && $result['data']['errors'][0]['code'] == 41000) {
                return response()->json([
                    'status' => 'info',
                    'message' => 'IRN already exists. You cannot push again.'
                ]);
            }

            // ✅ Mark IRP as done
            $creditNote->update(['credit_note_irp_status' => 1]);

            return response()->json([
                'status' => 'success',
                'message' => 'IRP pushed and IRN generated successfully.'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['message'] ?? 'Failed to push IRP.',
            'data' => $result
        ], $response->status());
    }

    public function cancelCreditNoteIRNWithin24Hrs($creditnote_id)
{
    $orgId = $this->orgId;
    $headers = $this->getAuthHeaders();

    // Static reason and reason_type for now
    $reason = 'Wrong entry';
    $reasonType = 'data_entry_mistake'; // ✅ Use one of the valid types

    $url = "https://www.zohoapis.in/books/v3/creditnotes/{$creditnote_id}/einvoice/cancel?organization_id={$orgId}";

    $payload = [
        'reason' => $reason,
        'reason_type' => $reasonType
    ];

    $response = Http::withHeaders($headers)->post($url, $payload);
    $json = $response->json();

    if ($response->successful() && isset($json['code']) && $json['code'] == 0) {
        // Optional: update IRP status to 0
        \App\Models\PurchaseInvoice::where('zoho_creditnote_id', $creditnote_id)->update([
            'credit_note_irp_status' => 0
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'IRN cancelled successfully.'
        ]);
    }

    return response()->json([
        'status' => 'error',
        'message' => $json['message'] ?? 'Failed to cancel IRN',
        'data' => $json
    ], $response->status());
}




    public function createZohoCreditNote($invoiceId)
    {
        // credit note for goods
        $orgId = $this->orgId;
        $headers = $this->getAuthHeaders();
        $url = "https://www.zohoapis.in/books/v3/creditnotes?organization_id={$orgId}";

        // Load invoice with product & address
        $invoice = PurchaseInvoice::with(['purchaseInvoiceDetails', 'address'])->findOrFail($invoiceId);
        
        $address = $invoice->address;

        if (!$address || !$address->zoho_customer_id) {
            return response()->json([
                'error' => 'Customer Zoho ID or GSTIN missing.',
            ], 422);
        }

        // Build line items
       $lineItems = [];

        foreach ($invoice->purchaseInvoiceDetails as $detail) {
            $product = Product::where('part_no', $detail->part_no)->first();

            if (!$product || !$product->zoho_item_id) {
                return response()->json([
                    'error' => "Missing Zoho Item ID for part no: {$detail->part_no}"
                ]);
            }

            $taxPercent = number_format((float) $detail->tax, 2, '.', '');


            if ($detail->igst > 0) {
                // IGST
                $zohoTax = ZohoTax::where('tax_percentage', $taxPercent)
                    ->where('tax_specific_type', 'igst')
                    ->first();
            } else {
                // CGST + SGST (intra-state)
                $zohoTax =ZohoTax::where('tax_percentage', $taxPercent)
                    ->where('tax_specification', 'intra')
                    ->first();
            }

            if (!$zohoTax || !$zohoTax->tax_id) {
                return response()->json([
                    'error' => "Zoho tax_id not found for part no: {$detail->part_no}"
                ]);
            }

            // ✅ Calculate the tax amount
            $taxAmount = $detail->price * ($zohoTax->tax_percentage / 100);
            // ✅ Calculate the total price including tax and apply `ceil()`
            $priceWithTax = $detail->price + $taxAmount;
            

            $lineItems[] = [
                "item_id"     => $product->zoho_item_id,
                "description" => $product->name,
                "name"        => $product->name,
                "rate" => number_format($priceWithTax, 2, '.', ''),
                "quantity"    => (int) $detail->qty,
                "hsn_or_sac"  => $product->hsncode,
                "product_type"=> "goods",
                "tax_id"      => $zohoTax->tax_id, // ✅ Dynamic tax ID
            ];
        }

        if (empty($lineItems)) {
            return response()->json([
                'error' => 'No valid line items found.',
            ], 422);
        }

        // Step 1: Fetch warehouse Zoho branch ID
        $warehouse = Warehouse::where('id', $invoice->warehouse_id)->first();

        if (!$warehouse || !$warehouse->zoho_branch_id) {
            return response()->json([
                'error' => 'Zoho Branch ID not found for warehouse ID: ' . $invoice->warehouse_id
            ]);
        }

        // Step 2: Use it as location_id
        $locationId = $warehouse->zoho_branch_id;

        $state = State::where('id', $address->state_id)->first();

        if (!$state || !$state->state_code) {
            return response()->json([
                'error' => 'State code not found for customer address state_id: ' . $address->state_id
            ]);
        }

        $placeOfSupply = $state->state_code;

        // Prepare payload
        $body = [
            "customer_id" => $address->zoho_customer_id,
            'date' => $invoice->created_at->toDateString(),
            "line_items" => $lineItems,
            "location_id" => $locationId,
            "creditnote_number" => $invoice->credit_note_number,
            "gst_treatment" => $address->gstin ? "business_gst" : "business_none",
            "is_reverse_charge_applied" => false,
            "invoice_type" => "others", // ✅ Required if no invoice_id
            // "gst_no" => $address->gstin,
            "reference_invoice_type" => $address->gstin ? "registered" : "b2c_others",
            "place_of_supply" => $placeOfSupply, // Or map from $address->state_id if needed
            "reference_number" => $invoice->seller_invoice_no,
            "notes" => "Credit Note against Purchase Invoice: " . $invoice->purchase_no,
            "is_inclusive_tax" => true
        ];


        // Add GSTIN only if it exists
        if ($address->gstin) {
            $body["gst_no"] = $address->gstin;
        }

        $response = Http::withHeaders(array_merge($headers, [
            'Content-Type' => 'application/json'
        ]))->post($url, $body);

       if ($response->successful()) {
            $responseData = $response->json();

            if (isset($responseData['creditnote']['creditnote_id'])) {
                $creditNoteId = $responseData['creditnote']['creditnote_id'];

                // ✅ Update the purchase invoice with the creditnote_id
                $invoice->update([
                    'zoho_creditnote_id' => $creditNoteId,
                ]);

                return response()->json([
                    'message' => 'Credit Note created successfully.',
                    'data' => $responseData
                ]);
            }
        }

        // echo "<pre>";
        // print_r(response()->json([
        //     'error' => 'Zoho Credit Note creation failed',
        //     'details' => $response->json()
        // ], 500));
        // die();
        $this->sendZohoSyncFailureNotification('Credit Note', $invoice->purchase_no, $response->json());
        return response()->json([
            'error' => 'Failed to create credit note',
            'details' => $response->json()
        ], 500);
    }

    public function createZohoServiceCreditNote($invoiceId)
    {
        // services
        $orgId = $this->orgId;
        $headers = $this->getAuthHeaders();
        $url = "https://www.zohoapis.in/books/v3/creditnotes?organization_id={$orgId}";

        $invoice = PurchaseInvoice::with(['purchaseInvoiceDetails', 'address'])->findOrFail($invoiceId);
        $address = $invoice->address;
        // echo "<pre>";
        // print_r($address->toArray());
        // die();

        if (!$address || !$address->zoho_customer_id ) {
            $this->sendZohoSyncFailureNotification('Service Credit Note', $invoice->purchase_no, ['msg' => 'Customer Zoho ID or GSTIN missing.']);
            return response()->json(['error' => 'Customer Zoho ID or GSTIN missing.'], 422);
        }

        $detail = $invoice->purchaseInvoiceDetails->first(); // One line item only
        if (!$detail) {
            return response()->json(['error' => 'Service entry detail missing.'], 422);
        }

        $taxPercent = number_format((float) $detail->tax, 2, '.', '');

        // Tax lookup
        if ($detail->igst > 0) {
            $zohoTax = ZohoTax::where('tax_percentage', $taxPercent)
                ->where('tax_specific_type', 'igst')
                ->first();
        } else {
            $zohoTax = ZohoTax::where('tax_percentage', $taxPercent)
                ->where('tax_specification', 'intra')
                ->first();
        }

        if (!$zohoTax || !$zohoTax->tax_id) {
            $this->sendZohoSyncFailureNotification('Service Credit Note', $invoice->purchase_no, ['msg' => 'Zoho tax_id not found for service item.']);
            return response()->json(['error' => 'Zoho tax_id not found for service item.']);
        }

        $warehouse = Warehouse::find($invoice->warehouse_id);
        if (!$warehouse || !$warehouse->zoho_branch_id) {
              $this->sendZohoSyncFailureNotification('Service Credit Note', $invoice->purchase_no, ['msg' => 'Zoho Branch ID not found for warehouse.']);
            return response()->json(['error' => 'Zoho Branch ID not found for warehouse.']);
        }

        $state = State::find($address->state_id);
        if (!$state || !$state->state_code) {
            $this->sendZohoSyncFailureNotification('Service Credit Note', $invoice->purchase_no, ['msg' => 'State code missing.']);
            return response()->json(['error' => 'State code missing.']);
        }

        // ✅ Construct line item for service
        $taxAmount = $detail->price * ($zohoTax->tax_percentage / 100);
        $priceWithTax = $detail->price + $taxAmount;

        $lineItems = [[
            "description" => $detail->part_no,
           "rate" => number_format($priceWithTax, 2, '.', ''),
            "quantity"    => (int) $detail->qty,
            "hsn_or_sac"  => $detail->hsncode,
            "product_type"=> "service",
            "tax_id"      => $zohoTax->tax_id,
        ]];

        $body = [
            "customer_id" => $address->zoho_customer_id,
            'date' => $invoice->created_at->toDateString(),
            "line_items" => $lineItems,
            "location_id" => $warehouse->zoho_branch_id,
            "creditnote_number" => $invoice->credit_note_number,
            "gst_treatment" => $address->gstin ? "business_gst" : "business_none",
            "is_reverse_charge_applied" => false,
            "invoice_type" => "others",
            "reference_invoice_type" => $address->gstin ? "registered" : "b2c_others",
            // "gst_no" => $address->gstin,
            "place_of_supply" => $state->state_code,
            "reference_number" => $invoice->seller_invoice_no,
            "notes" => "Credit Note for Service Entry: " . $invoice->purchase_no,
            "is_inclusive_tax" => true
        ];

        // echo "<pre>";
        // print_r($body);
        // die();

        // ✅ Conditionally add GSTIN
        if ($address->gstin) {
            $body["gst_no"] = $address->gstin;
        }

        $response = Http::withHeaders(array_merge($headers, [
            'Content-Type' => 'application/json'
        ]))->post($url, $body);

        if ($response->successful()) {
            $responseData = $response->json();
            if (isset($responseData['creditnote']['creditnote_id'])) {
                $invoice->update(['zoho_creditnote_id' => $responseData['creditnote']['creditnote_id']]);
                return response()->json(['message' => 'Zoho Service Credit Note Created', 'data' => $responseData]);
            }
        }
        $this->sendZohoSyncFailureNotification('Service Credit Note', $invoice->purchase_no, $response->json());
        return response()->json([
            'error' => 'Zoho Credit Note creation failed',
            'details' => $response->json()
        ], 500);
    }



    public function processPendingCreditNotes()
    {
        // Fetch all purchase invoices with null `zoho_creditnote_id`
        $invoices = \App\Models\PurchaseInvoice::whereNull('zoho_bill_id')
            
            ->get();
           
        if ($invoices->isEmpty()) {
            return response()->json([
                'message' => 'No pending credit notes to process.'
            ]);
        }

        $successCount = 0;
        $failedCount = 0;
        $failedInvoices = [];

        foreach ($invoices as $invoice) {
            try {
                $this->createZohoCreditNote($invoice->id);
                $successCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $failedInvoices[] = [
                    'purchase_no' => $invoice->purchase_no,
                    'error'       => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'message'       => "Processed {$successCount} credit notes successfully.",
            'failed_count'  => $failedCount,
            'failed_invoices' => $failedInvoices,
        ]);
    }

    public function getZohoCreditNote($creditNoteId)
    {
        $orgId = $this->orgId; // Replace with your dynamic org ID logic
        $headers = $this->getAuthHeaders();

        $url = "https://www.zohoapis.in/books/v3/creditnotes/{$creditNoteId}?organization_id={$orgId}";

        $response = Http::withHeaders(array_merge($headers, [
            'Content-Type' => 'application/json'
        ]))->get($url);

        if ($response->successful()) {
            return response()->json([
                'message' => 'Credit Note fetched successfully.',
                'data' => $response->json()
            ]);
        }

        return response()->json([
            'error' => 'Failed to fetch credit note',
            'details' => $response->json()
        ], 500);
    }

    // Credit Note End

    // vender Creditnote start
    public function createVendorCreditFullPayload()
    {
        $orgId = $this->orgId;
        $headers = $this->getAuthHeaders();

        $url = "https://www.zohoapis.in/books/v3/vendorcredits?organization_id={$orgId}";

        $payload = [
            "vendor_id" => "2435622000001699441",
            // "currency_id" => "3000000000083",
            // "vat_treatment" => "string", // 🇬🇧 or GCC only
            "vendor_credit_number" => "DN-006",
            "gst_treatment" => "business_gst",
            // "tax_treatment" => "vat_registered",
            "gst_no" => "19ABACA4198B1ZS",
            "source_of_supply" => "WB",
            "destination_of_supply" => "WB",
            // "place_of_supply" => "WB", // warehouse ka
            // "pricebook_id" => "string", // optional unless used
            "reference_number" => "KOL/DN/004",
            // "is_update_customer" => false,
            "date" => "2025-05-25",
            // "exchange_rate" => 1,
            "is_inclusive_tax" => true,
            "location_id" => "2435622000000031330",
            "reference_invoice_type"=>"registered",
            
            "notes" => "Debit Note Push",

            "line_items" => [
                [
                    "item_id" => "2435622000000052954",
                    // "line_item_id" => "460000000020077", // optional for create
                    // "account_id" => "460000000020097",
                    // "name" => "Premium Plan - Web hosting",
                    
                    "hsn_or_sac" => "996511",
                    // "reverse_charge_tax_id" => 460000000057089,
                    // "location_id" => "2435622000000031330",
                    "description" => "Test description",
                    "item_order" => 0,
                    "quantity" => 1,
                    "unit" => "Nos",
                    "rate" => 30,
                    "tax_id" => "2435622000000031213",
                    // "tds_tax_id" => "string",
                    // "tax_treatment_code" => "uae_others",
                    // "tags" => [
                    //     [
                    //         "tag_id" => 0,
                    //         "tag_option_id" => 0
                    //     ]
                    // ],
                    // "item_custom_fields" => [
                    //     [
                    //         "custom_field_id" => 0,
                    //         "label" => "string",
                    //         "value" => "string",
                    //         "index" => 0
                    //     ]
                    // ],
                    // "serial_numbers" => ["string"],
                    // "project_id" => 90300000087378
                ]
            ],

            // "documents" => [
            //     [
            //         "document_id" => 0,
            //         "file_name" => "string"
            //     ]
            // ],

            // "custom_fields" => [
            //     [
            //         "custom_field_id" => 0,
            //         "label" => "string",
            //         "value" => "string",
            //         "index" => 0
            //     ]
            // ]
        ];



        $response = Http::withHeaders($headers)->post($url, $payload);
        $json = $response->json();

        if ($response->successful()) {
            return response()->json([
                'message' => 'Vendor Credit created successfully.',
                'vendor_credit_id' => $json['vendor_credit']['vendor_credit_id'] ?? null,
                'data' => $json
            ]);
        }

        return response()->json([
            'error' => $json['message'] ?? 'Failed to create vendor credit.',
            'details' => $json
        ], $response->status());
    }

  


    public function createVendorCreditFromSellerForGoodsOrService($debitNoteId)
    {
        $orgId = $this->orgId;
        $headers = $this->getAuthHeaders();

        $debitNote = DebitNoteInvoice::with(['debitNoteInvoiceDetails', 'warehouse'])->findOrFail($debitNoteId);
        $sellerId = $debitNote->seller_id;

        $seller = Seller::where('id', $sellerId)->first();
        if (!$seller || !$seller->zoho_seller_id) {
            $this->sendZohoSyncFailureNotification('Vendor Credit', $debitNote->debit_note_number, ['msg' => 'Missing Zoho Vendor ID in sellers table.']);
            return response()->json(['error' => 'Missing Zoho Vendor ID in sellers table.']);
        }

        $hasGstin = !empty($seller->gstin);
        $referenceType = $hasGstin ? "registered" : "b2c_others";

        $sourceStateCode = State::where('id', $debitNote->warehouse->state_id)->value('state_code');
       // Get seller user ID
        $userId = $seller->user_id;
        // Step 1: Get the state name from users table
        $stateName = User::where('id', $userId)->value('state');
        // Step 2: Match state name in `states` table to get state_code
        $destinationStateCode = State::where('name', $stateName)->value('state_code') ?? 'WB'; // fallback

        $lineItems = [];

        foreach ($debitNote->debitNoteInvoiceDetails as $detail) {
            $product = Product::where('part_no', $detail->part_no)->first();

            $taxPercent = number_format((float) $detail->tax, 2, '.', '');

            if ((float) $detail->igst > 0) {
                $zohoTax = ZohoTax::where('tax_percentage', $taxPercent)
                    ->where('tax_specific_type', 'igst')
                    ->first();
            } else {
                $zohoTax = ZohoTax::where('tax_percentage', $taxPercent)
                    ->where('tax_specification', 'intra')
                    ->first();
            }

            if (!$zohoTax || !$zohoTax->tax_id) {
                $this->sendZohoSyncFailureNotification('Vendor Credit', $debitNote->debit_note_number, [
                    'msg' => "Zoho tax_id not found for part no: {$detail->part_no} with tax {$taxPercent}%"
                ]);
                return response()->json([
                    'error' => "Zoho tax_id not found for part no: {$detail->part_no} with tax {$taxPercent}%"
                ], 422);
            }

            $taxAmount = $detail->price * ($zohoTax->tax_percentage / 100);
            $priceWithTax = $detail->price + $taxAmount;

            // ✅ If product does not exist or is a service entry
            if (!$product || !$product->zoho_item_id) {
                // Assume it's a service entry
                $lineItems[] = [
                    "account_id"=>"2435622000000000504",
                    "description"   => $detail->part_no ?? 'Service Item',
                    "rate"          => number_format($priceWithTax, 2, '.', ''),
                    "quantity"      => (int) $detail->qty,
                    "hsn_or_sac"    => $detail->hsncode,
                    // "product_type"  => "service", // important
                    "tax_id"        => $zohoTax->tax_id,
                ];
            } else {
                // Normal product item (goods entry)
                $lineItems[] = [
                    "item_id"      => $product->zoho_item_id,
                    "description"  => $product->name,
                    "name"         => $product->name,
                    "rate"         => number_format($priceWithTax, 2, '.', ''),
                    "quantity"     => (int) $detail->qty,
                    "hsn_or_sac"   => $product->hsncode,
                    "tax_id"       => $zohoTax->tax_id,
                ];
            }
        }

        if (empty($lineItems)) {
            $this->sendZohoSyncFailureNotification('Vendor Credit', $debitNote->debit_note_number, ['msg' => 'No valid line items to push.']);
            return response()->json(['error' => 'No valid line items to push.'], 422);
        }

        $payload = [
            "vendor_id"             => $seller->zoho_seller_id,
            "vendor_credit_number"  => $debitNote->debit_note_number,
            "gst_treatment"         => $hasGstin ? "business_gst" : "business_none",
            "source_of_supply"      => $sourceStateCode,
            "destination_of_supply" => $destinationStateCode,
            "date"                  => $debitNote->seller_invoice_date,

            "is_inclusive_tax"      => true,
            "location_id"           => $debitNote->warehouse->zoho_branch_id ?? null,
            "reference_invoice_type"=> $referenceType,
            "notes"                 => "Auto-generated from Debit Note #{$debitNote->id}",
            "line_items"            => $lineItems
        ];

        if ($hasGstin) {
            $payload['gst_no'] = $seller->gstin;
        }

        $url = "https://www.zohoapis.in/books/v3/vendorcredits?organization_id={$orgId}";
        $response = Http::withHeaders($headers)->post($url, $payload);
        $json = $response->json();

        if ($response->successful()) {
            $zohoId = $json['vendor_credit']['vendor_credit_id'] ?? null;
            $debitNote->zoho_debitnote_id = $zohoId;
            $debitNote->save();

            return response()->json([
                'message' => 'Vendor Credit pushed successfully.',
                'vendor_credit_id' => $zohoId,
                'payload' => $payload
            ]);
        }

        $this->sendZohoSyncFailureNotification('Vendor Credit', $debitNote->debit_note_number, $json);
        return response()->json([
            'error' => $json['message'] ?? 'Failed to create vendor credit.',
            'details' => $json
        ], $response->status());
    }




public function createVendorCreditFromCustomerForGoodsOrService($debitNoteId)
{
    $orgId = $this->orgId;
    $headers = $this->getAuthHeaders();

    $debitNote = \App\Models\DebitNoteInvoice::with(['debitNoteInvoiceDetails', 'address', 'warehouse'])->findOrFail($debitNoteId);
    $address = $debitNote->address;
    $warehouse = $debitNote->warehouse;

    if (!$address || !$address->zoho_customer_id) {
        $this->sendZohoSyncFailureNotification('Customer Debit Note', $debitNote->debit_note_number, [
            'msg' => 'Missing Zoho Customer ID in addresses table.'
        ]);
        return response()->json(['error' => 'Missing Zoho Customer ID in addresses table.']);
    }

    $hasGstin = !empty($address->gstin);
    $referenceType = $hasGstin ? "registered" : "b2c_others";
    $customerStateCode = strtoupper(optional($address->state)->state_code);
    $sourceStateCode = strtoupper($warehouse->state_code ?? '');
    $isInterState = $customerStateCode !== $sourceStateCode;

    $lineItems = [];

    foreach ($debitNote->debitNoteInvoiceDetails as $index => $detail) {
        $product = \App\Models\Product::where('part_no', $detail->part_no)->first();

        $taxPercent = number_format((float) $detail->tax, 2, '.', '');
        $zohoTax = ($detail->igst > 0)
            ? ZohoTax::where('tax_percentage', $taxPercent)->where('tax_specific_type', 'igst')->first()
            : ZohoTax::where('tax_percentage', $taxPercent)->where('tax_specification', 'intra')->first();

        if (!$zohoTax || !$zohoTax->tax_id) {

             $this->sendZohoSyncFailureNotification('Customer Debit Note', $debitNote->debit_note_number, [
                'msg' => "Zoho tax_id not found for part no: {$detail->part_no} with tax {$taxPercent}%"
            ]);
            return response()->json([
                'error' => "Zoho tax_id not found for part no: {$detail->part_no} with tax {$taxPercent}%"
            ]);
        }

        $taxAmount = $detail->price * ($zohoTax->tax_percentage / 100);
        $priceWithTax = $detail->price + $taxAmount;

        // 🔍 Identify if it's a service
        if (!$product || !$product->zoho_item_id) {
            // 📦 Service entry
            $lineItems[] = [
                "item_order"    => $index + 1,
                "rate"          => number_format($priceWithTax, 2, '.', ''),
                "name"          => $detail->part_no ?? "Service Entry",
                "description"   => "Service entry for {$detail->part_no}",
                "quantity"      => (string) $detail->qty,
                "discount"      => "0%",
                "tax_id"        => $zohoTax->tax_id,
                "account_id"    => "2435622000000000504", // ✅ default service account ID
                "hsn_or_sac"    => $detail->hsncode,
            ];
        } else {
            // 🛒 Goods entry
            $lineItems[] = [
                "item_order"    => $index + 1,
                "item_id"       => $product->zoho_item_id,
                "name"          => $product->name,
                "description"   => $product->name,
                "rate"          => number_format($priceWithTax, 2, '.', ''),
                "quantity"      => (string) $detail->qty,
                "discount"      => "0%",
                "tax_id"        => $zohoTax->tax_id,
                "hsn_or_sac"    => $product->hsncode,
            ];
        }
    }

    if (empty($lineItems)) {

        $this->sendZohoSyncFailureNotification('Customer Debit Note', $debitNote->debit_note_number, [
            'msg' => 'No valid line items to push.'
        ]);
        return response()->json(['error' => 'No valid line items to push.'], 422);
    }

    $payload = [
        "location_id"               => $warehouse->zoho_branch_id,
        "customer_id"               => $address->zoho_customer_id,
        "date"                      => $debitNote->seller_invoice_date,
        "due_date"                  => now()->addDays(15)->toDateString(),
        "notes"                     => "Auto-generated from Customer Debit Note #{$debitNote->id}",
        "terms"                     => "Terms and conditions apply.",
        "is_inclusive_tax"          => true,
        "line_items"                => $lineItems,
        "discount_type"             => "item_level",
        "adjustment"                => 0,
        "adjustment_description"    => "Adjustment",
        "type"                      => "debit_note", // ✅ important
        "gst_treatment"             => $hasGstin ? "business_gst" : "business_none",
        "gst_no"                    => $address->gstin,
        "place_of_supply"           => $customerStateCode,
        "is_reverse_charge_applied" => false,
        "reference_invoice_type"    => $referenceType,
        "allow_partial_payments"    => false,
        "ignore_auto_number_generation" => true,
        "invoice_number"            => $debitNote->debit_note_number, // ✅ Correctly passed
    ];

    $url = "https://www.zohoapis.in/books/v3/invoices?organization_id={$orgId}";
    $response = Http::withHeaders($headers)->post($url, $payload);
    $json = $response->json();

    if ($response->successful()) {
        $zohoId = $json['invoice']['invoice_id'] ?? null;
        $debitNote->zoho_debitnote_id = $zohoId;
        $debitNote->save();

        return response()->json([
            'message' => 'Customer Debit Note pushed successfully to Zoho.',
            'zoho_invoice_id' => $zohoId,
            'payload' => $payload
        ]);
    }
    $this->sendZohoSyncFailureNotification('Customer Debit Note', $debitNote->debit_note_number, $json);
    return response()->json([
        'error' => $json['message'] ?? 'Failed to create customer debit note in Zoho.',
        'details' => $json
    ], $response->status());
}




public function uploadInvoiceAttachmentFromUrlToZoho($zohoInvoiceId, string $url)
{
    if (empty($zohoInvoiceId)) {
        \Log::warning('Zoho invoice ID missing while trying to upload attachment (URL mode).');
        return false;
    }

    if (empty($url)) {
        \Log::warning('Attachment URL missing while trying to upload to Zoho.');
        return false;
    }

    // Yahan hum URL hi expect kar rahe hain
    // isliye file_exists() nahi karenge, direct file_get_contents() se fetch karenge
    $fileContents = @file_get_contents($url);

    if ($fileContents === false) {
        \Log::error('Failed to read attachment from URL for Zoho upload.', [
            'invoice' => $zohoInvoiceId,
            'url'     => $url,
        ]);
        return false;
    }

    // Base headers (includes Authorization + token refresh logic)
    $headers = $this->getAuthHeaders();

    // multipart ke liye Content-Type manually set nahi karna
    if (isset($headers['Content-Type'])) {
        unset($headers['Content-Type']);
    }

    // Zoho Books endpoint (India DC)
    $zohoUrl = "https://www.zohoapis.in/books/v3/invoices/{$zohoInvoiceId}/attachment";

    // Filename URL se nikaal lo
    $path     = parse_url($url, PHP_URL_PATH) ?: '';
    $filename = basename($path) ?: 'attachment.jpg';

    try {
        $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
            ->attach(
                'attachment',
                $fileContents,
                $filename
            )
            ->post($zohoUrl, [
                'organization_id' => $this->orgId,
            ]);

        if (! $response->successful()) {
            \Log::error('Zoho attachment upload failed (URL mode)', [
                'status'   => $response->status(),
                'body'     => $response->body(),
                'invoice'  => $zohoInvoiceId,
                'url'      => $url,
            ]);
            return false;
        }

        $json = $response->json();

        \Log::info('Zoho attachment uploaded successfully (URL mode)', [
            'invoice'  => $zohoInvoiceId,
            'url'      => $url,
            'response' => $json,
        ]);

        return $json;
    } catch (\Throwable $e) {
        \Log::error('Exception while uploading Zoho attachment (URL mode): ' . $e->getMessage(), [
            'invoice' => $zohoInvoiceId,
            'url'     => $url,
        ]);
        return false;
    }
}

    public function uploadInvoiceAttachmentToZoho($zohoInvoiceId, string $filePath)
    {
        if (empty($zohoInvoiceId)) {
            \Log::warning('Zoho invoice ID missing while trying to upload attachment.');
            return false;
        }

        // if (!file_exists($filePath)) {
        //     \Log::error('Attachment file not found for Zoho upload: ' . $filePath);
        //     return false;
        // }

        // Base headers (includes Authorization + token refresh logic)
        $headers = $this->getAuthHeaders();

        // For multipart/form-data, Content-Type ko manually set nahi karna chahiye
        if (isset($headers['Content-Type'])) {
            unset($headers['Content-Type']);
        }

        $url = "https://www.zohoapis.in/books/v3/invoices/{$zohoInvoiceId}/attachment";

        try {
            $response =Http::withHeaders($headers)
                ->attach(
                    'attachment',
                    file_get_contents($filePath),
                    basename($filePath)
                )
                ->post($url, [
                    'organization_id' => $this->orgId,
                ]);

            if (! $response->successful()) {
                \Log::error('Zoho attachment upload failed', [
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                    'invoice'  => $zohoInvoiceId,
                    'filePath' => $filePath,
                ]);
                return false;
            }

            $json = $response->json();

            \Log::info('Zoho attachment uploaded successfully', [
                'invoice'  => $zohoInvoiceId,
                'file'     => $filePath,
                'response' => $json,
            ]);

            return $json;
        } catch (\Throwable $e) {
            \Log::error('Exception while uploading Zoho attachment: ' . $e->getMessage(), [
                'invoice'  => $zohoInvoiceId,
                'filePath' => $filePath,
            ]);
            return false;
        }
    }

    

// public function testUpdateInvoiceAttachmentOnZoho()
// {
//     // 🔹 Static Zoho invoice ID (tumne diya hua)
//     $zohoInvoiceId = '2435622000013709150';

//     // can_send_in_mail = true
//     $result = $this->updateInvoiceAttachmentOnZoho($zohoInvoiceId, true);

//     if ($result === false) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Zoho attachment update failed.',
//         ], 500);
//     }

//     return response()->json([
//         'success' => true,
//         'message' => 'Zoho attachment update API called successfully.',
//         'zoho_response' => $result,
//     ]);
// }


public function deleteInvoiceAttachmentFromZoho(string $zohoInvoiceId)
{
    //order logistics document
    if (empty($zohoInvoiceId)) {
        \Log::warning('Zoho invoice ID missing while trying to DELETE attachment.');
        return false;
    }

    $headers = $this->getAuthHeaders();

    // Tum jo DC use kar rahe ho wahi rakho (.in ya .com)
    $baseUrl = 'https://www.zohoapis.in'; // agar tum .com use karte ho to yahan .com kar do

    $url = $baseUrl
        . "/books/v3/invoices/{$zohoInvoiceId}/attachment"
        . '?organization_id=' . urlencode($this->orgId);

    try {
        $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
            ->delete($url);

        if (! $response->successful()) {
            \Log::error('Zoho attachment DELETE failed', [
                'status'  => $response->status(),
                'body'    => $response->body(),
                'invoice' => $zohoInvoiceId,
            ]);
            return false;
        }

        $json = $response->json();

        \Log::info('Zoho attachment DELETE success', [
            'invoice'  => $zohoInvoiceId,
            'response' => $json,
        ]);

        return $json;
    } catch (\Throwable $e) {
        \Log::error('Exception while DELETING Zoho attachment: ' . $e->getMessage(), [
            'invoice' => $zohoInvoiceId,
        ]);
        return false;
    }
}



    public function updateInvoiceAttachmentOnZoho(string $zohoInvoiceId, bool $canSendInMail = true)
{
    if (empty($zohoInvoiceId)) {
        \Log::warning('Zoho invoice ID missing while trying to UPDATE attachment flags.');
        return false;
    }

    $headers = $this->getAuthHeaders();

    // Same DC use karo jo baaki calls me use kar rahe ho (.in ya .com)
    $baseUrl = 'https://www.zohoapis.in'; // agar tum .com use karte ho to yaha .com kar do

    // 👇 CURL jaisa hi URL banao – query params ke saath
    $url = $baseUrl
        . "/books/v3/invoices/{$zohoInvoiceId}/attachment"
        . '?organization_id=' . urlencode($this->orgId)
        . '&can_send_in_mail=' . ($canSendInMail ? 'true' : 'false');

    try {
        // ❗ Body empty, bas headers + PUT
        $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
            ->put($url);

        // Debug ke liye (abhi ke liye rakho, baad me hata sakte ho)
        \Log::info('Zoho UPDATE raw response', [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        if (! $response->successful()) {
            \Log::error('Zoho attachment UPDATE failed', [
                'status'  => $response->status(),
                'body'    => $response->body(),
                'invoice' => $zohoInvoiceId,
            ]);
            return false;
        }

        $json = $response->json();

        \Log::info('Zoho attachment UPDATE success', [
            'invoice'  => $zohoInvoiceId,
            'response' => $json,
        ]);

        return $json;
    } catch (\Throwable $e) {
        \Log::error('Exception while UPDATING Zoho attachment: ' . $e->getMessage(), [
            'invoice' => $zohoInvoiceId,
        ]);
        return false;
    }
}



    // public function bulkUploadInvoiceAttachmentsFromLogistics()
    // {
    //     // Sirf active / non-cancelled invoices
    //     $invoices = InvoiceOrder::where('invoice_cancel_status', 0)
    //         ->whereNotNull('zoho_invoice_id')
    //         ->get();

    //     $summary = [
    //         'total_invoices' => $invoices->count(),
    //         'processed'      => 0,
    //         'success'        => 0,
    //         'failed'         => 0,
    //         'skipped'        => 0,
    //         'details'        => [],
    //     ];

    //     foreach ($invoices as $invoice) {
    //         $invoiceNo    = $invoice->invoice_no;
    //         $zohoInvoiceId = $invoice->zoho_invoice_id;   // <-- tumhara Zoho ID field

    //         // Latest logistic record with attachment for this invoice
    //         $logistic = OrderLogistic::where('invoice_no', $invoiceNo)
    //             ->whereNotNull('attachment')
    //             ->where('attachment', '!=', '')
    //             ->orderByDesc('id')
    //             ->first();

    //         if (!$logistic) {
    //             $summary['skipped']++;
    //             $summary['details'][] = [
    //                 'invoice_no' => $invoiceNo,
    //                 'status'     => 'skipped',
    //                 'reason'     => 'No logistic record with attachment found',
    //             ];
    //             continue;
    //         }

    //         // Comma-separated attachment URLs -> first one use karenge
    //         $attachments = explode(',', $logistic->attachment);
    //         $firstUrl    = trim($attachments[0] ?? '');

    //         if (empty($firstUrl)) {
    //             $summary['skipped']++;
    //             $summary['details'][] = [
    //                 'invoice_no' => $invoiceNo,
    //                 'status'     => 'skipped',
    //                 'reason'     => 'Empty attachment URL',
    //             ];
    //             continue;
    //         }

    //         // URL se filename nikal ke local path banana
    //         $pathPart = parse_url($firstUrl, PHP_URL_PATH); // e.g. /public/uploads/cw_acetools/17647...jpg
    //         $fileName = basename($pathPart);

    //         // Tumhara local path structure:
    //         $localPath = public_path('uploads/cw_acetools/' . $fileName);

    //         $summary['processed']++;

    //         // Existing helper function ko call
    //         $result = $this->uploadInvoiceAttachmentToZoho($zohoInvoiceId, $localPath);

    //         if ($result === false) {
    //             $summary['failed']++;
    //             $summary['details'][] = [
    //                 'invoice_no'    => $invoiceNo,
    //                 'zoho_invoice_id' => $zohoInvoiceId,
    //                 'file'          => $localPath,
    //                 'status'        => 'failed',
    //             ];
    //         } else {
    //             $summary['success']++;
    //             $summary['details'][] = [
    //                 'invoice_no'    => $invoiceNo,
    //                 'zoho_invoice_id' => $zohoInvoiceId,
    //                 'file'          => $localPath,
    //                 'status'        => 'success',
    //             ];
    //         }
    //     }

    //     return response()->json($summary);
    // }

    public function startBulkZohoAttachmentJob()
    {
        dispatch(new BulkUploadInvoiceAttachmentsJob());

        return response()->json([
            'success' => true,
            'message' => 'Bulk Zoho attachment job dispatched. It will process 50 logistics at a time with 1-minute gaps.',
        ]);
    }


    public function testStaticAttachmentUpload()
{
    // 🔐 Static Zoho invoice ID
    $zohoInvoiceId = '2435622000013528441';

    // 🌐 Static file URL
    $fileUrl = 'https://mazingbusiness.com/public/uploads/cw_acetools/1764737068_2935.jpg';

    try {
        // Zoho auth headers (with auto refresh)
        $headers = $this->getAuthHeaders();

        // multipart ke liye Content-Type manually set mat karo
        if (isset($headers['Content-Type'])) {
            unset($headers['Content-Type']);
        }

        // File content URL se uthao
        $fileContents = file_get_contents($fileUrl);
        if ($fileContents === false) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to read file from URL.',
                'file_url' => $fileUrl,
            ], 500);
        }

        $url = "https://www.zohoapis.in/books/v3/invoices/{$zohoInvoiceId}/attachment";

        $response = Http::withHeaders($headers)
            ->attach(
                'attachment',
                $fileContents,
                basename($fileUrl)
            )
            ->post($url, [
                'organization_id' => $this->orgId,
            ]);

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ], 500);
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Attachment uploaded to Zoho successfully.',
            'zoho_id'  => $zohoInvoiceId,
            'file_url' => $fileUrl,
            'response' => $response->json(),
        ]);
    } catch (\Throwable $e) {
        \Log::error('Zoho static attachment upload failed: '.$e->getMessage(), [
            'zoho_invoice_id' => $zohoInvoiceId,
            'file_url'        => $fileUrl,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Exception: '.$e->getMessage(),
        ], 500);
    }
}





    public function getVendorCredits()
    {
        $orgId = $this->orgId; // your stored organization ID
        $headers = $this->getAuthHeaders(); // should return Authorization + Content-Type headers

        $url = "https://www.zohoapis.in/books/v3/vendorcredits?organization_id={$orgId}";

        $response = Http::withHeaders($headers)->get($url);
        $json = $response->json();

        if ($response->successful()) {
            return response()->json([
                'message' => 'Vendor Credits fetched successfully.',
                'data' => $json
            ]);
        }

        return response()->json([
            'error' => 'Failed to fetch vendor credits.',
            'details' => $json
        ], $response->status());
    }




    // vendor creditnote end

    // chart of account start
    public function getChartOfAccounts()
    {
        $orgId = $this->orgId; // your stored organization ID
        $headers = $this->getAuthHeaders(); // must return Authorization and Content-Type

        $url = "https://www.zohoapis.in/books/v3/chartofaccounts?organization_id={$orgId}";

        $response = Http::withHeaders($headers)->get($url);
        $json = $response->json();

        if ($response->successful()) {
            return response()->json([
                'message' => 'Chart of Accounts fetched successfully.',
                'data' => $json
            ]);
        }

        return response()->json([
            'error' => 'Failed to fetch chart of accounts.',
            'details' => $json
        ], $response->status());
    }

    public function syncChartOfAccounts()
    {
        $orgId = $this->orgId;
        $headers = $this->getAuthHeaders();

        $url = "https://www.zohoapis.in/books/v3/chartofaccounts?organization_id={$orgId}";
        $response = Http::withHeaders($headers)->get($url);
        $json = $response->json();

        if ($response->successful() && isset($json['chartofaccounts'])) {
            foreach ($json['chartofaccounts'] as $account) {
                ZohoChartOfAccount::updateOrCreate(
                    ['account_id' => $account['account_id']],
                    [
                        'account_name'          => $account['account_name'] ?? null,
                        'account_code'          => $account['account_code'] ?? null,
                        'account_type'          => $account['account_type'] ?? null,
                        'description'           => $account['description'] ?? null,
                        'is_user_created'       => $account['is_user_created'] ?? null,
                        'is_system_account'     => $account['is_system_account'] ?? null,
                        'is_active'             => $account['is_active'] ?? null,
                        'can_show_in_ze'        => $account['can_show_in_ze'] ?? null,
                        'parent_account_id'     => $account['parent_account_id'] ?? null,
                        'parent_account_name'   => $account['parent_account_name'] ?? null,
                        'depth'                 => $account['depth'] ?? null,
                        'has_attachment'        => $account['has_attachment'] ?? null,
                        'is_child_present'      => $account['is_child_present'] ?? null,
                        'child_count'           => $account['child_count'] ?? null,
                        'created_time'          => $account['created_time'] ?? null,
                        'last_modified_time'    => $account['last_modified_time'] ?? null,
                        'is_standalone_account' => $account['is_standalone_account'] ?? null,
                        'documents'             => $account['documents'] ?? null,
                    ]
                );
            }

            return response()->json(['message' => 'Chart of Accounts synced successfully.']);
        }

        return response()->json([
            'error' => 'Failed to fetch chart of accounts.',
            'details' => $json
        ], $response->status());
    }
    // chat of account end


    // mark as lost start

    public function zoho_mark_as_lost($id)
    {
        $record = MarkAsLostItem::find($id);

        if (!$record) {
            return response()->json(['error' => 'Mark as lost record not found.']);
        }

        $product = Product::find($record->product_id);

        if (!$product || !$product->zoho_item_id) {
            return response()->json(['error' => 'Product or Zoho item ID not found.']);
        }

        $headers = $this->getAuthHeaders();

        $payload = [
            'reason' => $record->reason ?? 'Stock Damage',
            'date' => now()->toDateString(),
            'warehouse_id' => '2435622000000031254', // use your logic
            'line_items' => [
                [
                    'item_id' => $product->zoho_item_id,
                    'quantity_adjusted' => -(int)$record->mark_as_lost_qty,
                    'rate' => $product->purchase_price ?? 0
                ]
            ]
        ];

        $response = Http::withHeaders($headers)->post(
            "https://www.zohoapis.in/books/v3/inventoryadjustments?organization_id={$this->orgId}",
            $payload
        );

        return $response->json();
    }
    // mark as lost end




    // public function getStatement(Request $request){
    //     echo $orgId = $this->orgId; die;
    //     $response = Http::withHeaders($this->getAuthHeaders())
    //         ->get("https://www.zohoapis.com/books/v3/contacts/2435622000000642903/statements/email?organization_id={$orgId}");

    //     return $response->json();
    // }

    public function getStatement(Request $request)
    {
        $contactId = '2435622000000562905';
        // $contactId = '2435622000001680418';
        $orgId = $this->orgId;
        
        $url = "https://www.zohoapis.in/books/v3/customers/{$contactId}/statements";
        // $url = "https://www.zohoapis.in/books/v3/vendors/{$contactId}/statements";
        $response = Http::withHeaders($this->getAuthHeaders(), ['Accept' => 'application/xls'])
        ->get($url, [
            'from_date' => '2025-04-01',
            'to_date' => date('Y-m-d'),
            'filter_by' => 'Status.All',
            'accept' => 'xls',
            'organization_id' => $orgId,
        ]);
        $data = $response->json();
        if ($response->successful()) {
            // Save the response body as a file
            $fileName = 'zoho_statement_' . now()->format('Ymd_His') . '.xls';
            $folderPath = public_path('zoho_statements');
            // Create the folder if it doesn't exist
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
            $fullPath = $folderPath . '/' . $fileName;
            file_put_contents($fullPath, $response->body());

            $getStatementData = json_decode(json_encode($this->readStatementData($fileName)), true);
            $getStatementData = $getStatementData['original']['data'][0];
            $arrayBeautifier = array();
            // echo "<pre>"; print_r($getStatementData); die;

            foreach($getStatementData as $key=>$value){
                $tempArray = array();
                // if($key >= 2 AND $key < 6){                    
                //     $tempArray['trn_no'] = "";
                //     $tempArray['trn_date'] = date('Y-m-d');
                //     $tempArray['vouchertypebasename'] = "";
                //     $tempArray['ledgername'] = $value[0];
                //     $amount = explode('₹',$value[1]);
                //     $tempArray['ledgerid'] = "";
                //     $amount[1] >= 0 ? $tempArray['dramount'] = str_replace(',', '',$amount[1]) : $tempArray['cramount'] = str_replace(',', '',$amount[1]);
                //     $tempArray['narration'] = "";
                //     $arrayBeautifier[] = $tempArray;
                // }else
                if($key >= 9){
                    if($value[1] != ""){
                        $tempVarArray = array();
                        if($value[1] == 'Invoice' OR $value[1] == 'Debit Note' OR $value[1] == 'Credit Note'){
                            $tempVarArray = explode(' - ',$value[2]);
                        }else{
                            $tempVarArray = explode('₹',$value[2]);
                        }
                        $tempArray['trn_no'] = trim($tempVarArray[0]);
                        $tempArray['trn_date'] = $value[0];
                        if($value[1] == 'Invoice'){
                            $tempArray['vouchertypebasename'] = "Sales";
                        }elseif($value[1] == 'Debit Note' OR  $value[1] == 'Credit Note'){
                            $tempArray['vouchertypebasename'] = $value[1] ;
                        }elseif($value[1] == 'Payment Received'){
                            $tempArray['vouchertypebasename'] = "Receipt";
                        }elseif($value[1] == 'Payment Received'){
                            $tempArray['vouchertypebasename'] = "Receipt";
                        }
                        
                        $tempArray['ledgername'] = '';
                        // $amount = explode('₹',$value[4]);
                        $tempArray['ledgerid'] = "";
                        
                        if(($value[1] == 'Invoice' OR $value[1] == 'Debit Note') AND $value[3] != ""){
                            if($value[3] >= '0'){
                                $tempArray['dramount'] = str_replace(',', '',$value[3]);
                                $tempArray['cramount'] = 0.00;
                                
                            }else{
                                $tempArray['cramount'] = str_replace(',', '',$value[3]);
                                $tempArray['dramount'] = 0.00;
                            }
                        }
                        
                        if($value[1] == 'Opening Balance'){
                            if($value[3] >= 0){
                                $tempArray['dramount'] = str_replace(',', '',$value[3]);
                                $tempArray['cramount'] = 0.00;                                
                            }else{
                                $tempArray['cramount'] = str_replace(',', '',$value[3]);
                                $tempArray['dramount'] = 0.00;
                            }
                            $tempArray['trn_date'] = '2025-04-01';
                            $tempArray['vouchertypebasename'] = "Opening Balance";
                            $tempArray['ledgername'] = 'Opening b/f...';
                        }
                        if(($value[1] == 'Payment Received') AND $value[4] != ""){
                            $tempArray['cramount'] = str_replace(',', '',$value[4]);
                            $tempArray['dramount'] = 0.00;
                        }
                        if($value[1] == 'Credit Note' AND $value[3] != ""){
                            $tempArray['cramount'] = str_replace(',', '',$value[4]);
                            $tempArray['dramount'] = 0.00;
                        }
                        $tempArray['narration'] = $value[2];
                        $tempArray['running_balance'] = $value[5];
                        $arrayBeautifier[] = $tempArray;
                    }
                    if($value[4] == "Balance Due"){
                        $tempArray['trn_no'] = "";
                        $tempArray['trn_date'] = date('Y-m-d');
                        $tempArray['vouchertypebasename'] = "";
                        $tempArray['ledgername'] = "closing C/f...";
                        $amount = explode('₹',$value[5]);
                        $tempArray['ledgerid'] = "";
                        if($amount[1] >= 0){
                            $tempArray['cramount'] = str_replace(',', '',$amount[1]);
                            $tempArray['dramount'] = 0.00;
                        }else{
                            $tempArray['dramount'] = str_replace(',', '',$amount[1]);
                            $tempArray['cramount'] = 0.00;
                        }
                        $tempArray['narration'] = "";
                        $arrayBeautifier[] = $tempArray;
                    }
                }
            }
            
            // Start the statement data as like salzing
            $overdueAmount = "0";
            $openingBalance="0";
            $openDrOrCr="";
            $closingBalance="0";
            $closeDrOrCr="";
            $dueAmount="0";
            $overdueDateFrom="";
            $overdueAmount="0";
            $overdueDrOrCr="";
            $overDueMark = array();
            $drBalance = 0;
            $crBalance = 0;
            $getUserData = Address::with('user')->where('zoho_customer_id',$contactId)->first();
            $userData = $getUserData->user;
            // echo "<pre>"; print_r($userData);print_r($arrayBeautifier);
            $getOverdueData = $arrayBeautifier;
            $closingBalanceResult = array_filter($getOverdueData, function ($entry) {
                return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
            });
            $closingEntry = reset($closingBalanceResult);
            $cloasingDrAmount = $closingEntry['dramount'];
            $cloasingCrAmount = $closingEntry['cramount'];          
            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
            if($cloasingCrAmount > 0){
                $drBalanceBeforeOVDate = 0;
                $crBalanceBeforeOVDate = 0;
                $getOverdueData = array_reverse($getOverdueData);
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }else{
                            $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }
                    }
                }
                $overdueAmount = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                $overDueMark = array();
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            // $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }elseif(strtotime($ovValue['trn_date']) <= strtotime($overdueDateFrom) AND $temOverDueBalance > 0 AND $ovValue['dramount'] != '0.00'){
                            $temOverDueBalance -= $ovValue['dramount'];
                            $date1 = $ovValue['trn_date'];
                            $date2 = $overdueDateFrom;        
                            $diff = abs(strtotime($date2) - strtotime($date1));

                            $dateDifference = floor($diff / (60 * 60 * 24)).' days';
                            if($temOverDueBalance >= 0){
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Overdue'
                                ];
                            }else{
                                $overDueMark[] = [
                                    'trn_no' => $ovValue['trn_no'],
                                    'trn_date' => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus' => 'Pertial Overdue'
                                ];
                            }
                        }
                    }
                }
            }
            if($overdueAmount <= 0){
                $overdueDrOrCr = 'Cr';
                $overdueAmount = 0;
            }else{
                $overdueDrOrCr = 'Dr';
            }

            $getData = $arrayBeautifier;
            if(count($overDueMark) > 0){
                $overDueMarkTrnNos = array_column($overDueMark, 'trn_no');
                $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
                $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
            }
            foreach($getData as $gKey=>$gValue){                
                if($gValue['ledgername'] == "Opening b/f..."){
                    if($gValue['dramount'] != "0.00"){
                        $openingBalance = $gValue['dramount'];
                        $openDrOrCr = "Dr";
                    }else{
                        $openingBalance = $gValue['cramount'];
                        $openDrOrCr = "Cr";
                    }
                }else if($gValue['ledgername'] == "closing C/f..."){
                    if($gValue['dramount'] != "0.00"){
                        $closingBalance = $gValue['dramount'];
                        // $dueAmount = $gValue['dramount'];
                        // $closeDrOrCr = "Dr";
                    }else{
                        $closingBalance = $gValue['cramount'];
                        // $closeDrOrCr = "Cr";
                        // $dueAmount = $gValue['cramount'];
                    }
                }
                if(count($overDueMark) > 0) {
                    $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                    if ($key !== false) {
                        $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                        $getData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                    }
                }
                if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                    $drBalance = $drBalance + $gValue['dramount'];
                    $dueAmount = $dueAmount + $gValue['dramount'];
                } 
                if($gValue['cramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                    $crBalance = $crBalance + $gValue['cramount'];
                    $dueAmount = $dueAmount - $gValue['cramount'];
                }
                // echo "<pre>"; print_r($gValue);die;
            }
            $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
            // echo $overdueAmount;
            Address::where('zoho_customer_id',$contactId)
            ->update(
                [
                    'due_amount'      => $dueAmount,
                    'dueDrOrCr'      => $closeDrOrCr,
                    'overdue_amount' => $overdueAmount,
                    'overdueDrOrCr' => $overdueDrOrCr,
                    'statement_data' => json_encode($getData)
                ]
            );

            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
            // echo $overdueDrOrCr; print_r($overDueMark); die;
            // $jsonData = json_encode($arrayBeautifier);
            // print_r($jsonData); die;
            // Return file URL
            return response()->json([
                'message' => 'Saved successfully.'
            ]);
		} else {
			return response()->json([
				'error' => 'Failed to download PDF.',
				'details' => $response->body(),
				'status' => $response->status()
			], 500);
		}
    }

    private function readStatementData($filename){
        $filePath = public_path('zoho_statements/' . $filename);
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found.'], 404);
        }
        // Read the file as a Collection
        $data = Excel::toCollection(null, $filePath);
        return response()->json([
            'data' => $data // usually the first sheet
        ]);
    }

    public function getMergeSellerAndCustomerStatement(Request $request)
    {
        $contactId = '2435622000000562905';
        // $contactId = '2435622000001680418';
        $orgId = $this->orgId;
        $from_date = '2025-04-01';
        $to_date = date('Y-m-d');
        $cleanedStatement = array();
        
        $arrayBeautifier = array();
        // Get Statement from Zoho
        $url = "https://www.zohoapis.in/books/v3/customers/{$contactId}/statements";
        $response = Http::withHeaders($this->getAuthHeaders(), ['Accept' => 'application/xls'])
            ->get($url, [
                'from_date' => $from_date,
                'to_date' => $to_date,
                'filter_by' => 'Status.All',
                'accept' => 'xls',
                'organization_id' => $orgId,
            ]);
        $data = $response->json();

        if ($response->successful()) {
            // Save the response body as a file
            $fileName = 'zoho_statement_' . now()->format('Ymd_His') . '.xls';
            $folderPath = public_path('zoho_statements');
            // Create the folder if it doesn't exist
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
            $fullPath = $folderPath . '/' . $fileName;
            file_put_contents($fullPath, $response->body());
            
            $getStatementData = json_decode(json_encode($this->readStatementData($fileName)), true);
            $getStatementData = $getStatementData['original']['data'][0];
            
            $arrayBeautifier = array();
            foreach($getStatementData as $key=>$value){
                $tempArray = array();
                if($key > 9){
                    if($value[1] != "" AND  $value[1] != 'Customer Opening Balance'){
                        $tempVarArray = array();
                        if($value[1] == 'Invoice' OR $value[1] == 'Debit Note' OR $value[1] == 'Credit Note'){
                            $tempVarArray = explode(' - ',$value[2]);
                        }else{
                            $tempVarArray = explode('₹',$value[2]);
                        }
                        $tempArray['trn_no'] = trim($tempVarArray[0]);
                        $tempArray['trn_date'] = $value[0];
                        if($value[1] == 'Invoice'){
                            $tempArray['vouchertypebasename'] = "Sales";
                        }elseif($value[1] == 'Debit Note' OR  $value[1] == 'Credit Note'){
                            $tempArray['vouchertypebasename'] = $value[1] ;
                        }elseif($value[1] == 'Payment Received'){
                            $tempArray['vouchertypebasename'] = "Receipt";
                        }elseif($value[1] == 'Payment Refund'){
                            $tempArray['vouchertypebasename'] = "Payment";
                        }elseif($value[1] == 'Customer Opening Balance'){
                            $tempArray['vouchertypebasename'] = "Opening Balance";
                        }else{
                            $tempArray['vouchertypebasename'] = $value[1];
                        }
                        
                        $tempArray['ledgername'] = '';
                        $tempArray['ledgerid'] = "";
                        
                        if(($value[1] == 'Invoice' OR $value[1] == 'Debit Note') AND $value[3] != ""){
                            if($value[3] >= '0'){
                                $tempArray['dramount'] = (float)str_replace('-', '',str_replace(',', '',$value[3]));
                                $tempArray['cramount'] = (float)0.00;                                
                            }else{
                                $tempArray['cramount'] = (float)str_replace('-', '',str_replace(',', '',$value[3]));
                                $tempArray['dramount'] = (float)0.00;
                            }
                        }else if(($value[1] == 'Payment Refund') AND $value[4] != ""){
                            if($value[4] <= '0'){
                                $tempArray['dramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                                $tempArray['cramount'] = (float)0.00;                                
                            }else{
                                $tempArray['cramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                                $tempArray['dramount'] = (float)0.00;
                            }
                        }elseif(($value[1] == 'Payment Received') AND $value[4] != ""){
                            if($value[4] <= '0'){
                                $tempArray['dramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                                $tempArray['cramount'] = (float)0.00;                                
                            }else{
                                $tempArray['cramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                                $tempArray['dramount'] = (float)0.00;
                            }
                        }elseif($value[1] == 'Credit Note' AND $value[3] != ""){
                            $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[3])));
                            $tempArray['cramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)$crnValue;
                            $tempArray['dramount'] = (float)0.00;
                        }elseif($value[1] == 'Credit Note' AND $value[4] != ""){
                            $drnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[4])));
                            $tempArray['dramount'] = $drnValue < 0 ? (float)str_replace('-', '',$drnValue) : (float)$drnValue;
                            $tempArray['cramount'] = (float)0.00;
                        }else{
                            if($value[3] != ""){
                                $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[3])));
                                $tempArray['cramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)$crnValue;
                                $tempArray['dramount'] = (float)0.00;
                            }elseif($value[4] != ""){
                                $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[4])));
                                $tempArray['dramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)$crnValue;
                                $tempArray['cramount'] = (float)0.00;
                            }
                        }
                        $tempArray['narration'] = $value[2];
                        $arrayBeautifier[] = $tempArray;
                    }
                }
            }

            // Merge salzing and zoho statement data in an array.
            if(count($cleanedStatement) > 0){
                $arrayBeautifier = array_merge($cleanedStatement, $arrayBeautifier);
            }

            // if (File::exists($fullPath)) {
            //     File::delete($fullPath);
            // }
        }            
        // $statement_data[]=$arrayBeautifier;

        // Seller Statement
        $vendorContactId = '2435622000001680418';
        $sellerArrayBeautifier = array();
        // Get Statement from Zoho
        $url = "https://www.zohoapis.in/books/v3/vendors/{$vendorContactId}/statements";
        $response = Http::withHeaders($this->getAuthHeaders(), ['Accept' => 'application/xls'])
            ->get($url, [
                'from_date' => $from_date,
                'to_date' => $to_date,
                'filter_by' => 'Status.All',
                'accept' => 'xls',
                'organization_id' => $orgId,
            ]);
        if ($response->successful()) {
            // Save the response body as a file
            $fileName = 'zoho_statement_' . now()->format('Ymd_His') . '.xls';
            $folderPath = public_path('zoho_statements');
            // Create the folder if it doesn't exist
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0777, true);
            }
            $fullPath = $folderPath . '/' . $fileName;
            file_put_contents($fullPath, $response->body());
            
            $getStatementData = json_decode(json_encode($this->readStatementData($fileName)), true);
            $getStatementData = $getStatementData['original']['data'][0];
            // echo "<pre>"; print_r($getStatementData); die;
            $sellerArrayBeautifier = array();
            foreach($getStatementData as $key=>$value){
                $tempArray = array();
                if($key > 8){
                    if($value[1] != "" AND  $value[1] != 'Customer Opening Balance'){
                        $tempVarArray = array();
                        if($value[1] == 'Invoice' OR $value[1] == 'Debit Note' OR $value[1] == 'Credit Note'){
                            $tempVarArray = explode(' - ',$value[2]);
                        }else{
                            $tempVarArray = explode('₹',$value[2]);
                        }
                        $tempArray['trn_no'] = trim($tempVarArray[0]);
                        $tempArray['trn_date'] = $value[0];
                        if($value[1] == 'Invoice'){
                            $tempArray['vouchertypebasename'] = "Sales";
                        }elseif($value[1] == 'Debit Note' OR  $value[1] == 'Credit Note'){
                            $tempArray['vouchertypebasename'] = $value[1] ;
                        }elseif($value[1] == 'Payment Received'){
                            $tempArray['vouchertypebasename'] = "Receipt";
                        }elseif($value[1] == 'Payment Refund'){
                            $tempArray['vouchertypebasename'] = "Payment";
                        }elseif($value[1] == 'Customer Opening Balance'){
                            $tempArray['vouchertypebasename'] = "Opening Balance";
                        }else{
                            $tempArray['vouchertypebasename'] = $value[1];
                        }
                        
                        $tempArray['ledgername'] = '';
                        $tempArray['ledgerid'] = "";
                        
                        if(($value[1] == 'Invoice' OR $value[1] == 'Debit Note') AND $value[3] != ""){
                            if($value[3] >= '0'){
                                $tempArray['dramount'] = (float)str_replace('-', '',str_replace(',', '',$value[3]));
                                $tempArray['cramount'] = (float)0.00;                                
                            }else{
                                $tempArray['cramount'] = (float)str_replace('-', '',str_replace(',', '',$value[3]));
                                $tempArray['dramount'] = (float)0.00;
                            }
                        }else if(($value[1] == 'Payment Refund') AND $value[4] != ""){
                            if($value[4] <= '0'){
                                $tempArray['dramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                                $tempArray['cramount'] = (float)0.00;                                
                            }else{
                                $tempArray['cramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                                $tempArray['dramount'] = (float)0.00;
                            }
                        }elseif(($value[1] == 'Payment Received') AND $value[4] != ""){
                            if($value[4] <= '0'){
                                $tempArray['dramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                                $tempArray['cramount'] = (float)0.00;                                
                            }else{
                                $tempArray['cramount'] = (float)str_replace('-', '',str_replace(',', '',$value[4]));
                                $tempArray['dramount'] = (float)0.00;
                            }
                        }elseif($value[1] == 'Credit Note' AND $value[3] != ""){
                            $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[3])));
                            $tempArray['cramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)$crnValue;
                            $tempArray['dramount'] = (float)0.00;
                        }elseif($value[1] == 'Credit Note' AND $value[4] != ""){
                            $drnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[4])));
                            $tempArray['dramount'] = $drnValue < 0 ? (float)str_replace('-', '',$drnValue) : (float)$drnValue;
                            $tempArray['cramount'] = (float)0.00;
                        }else{
                            if($value[3] != ""){
                                $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[3])));
                                $tempArray['cramount'] = $crnValue < 0 ? (float)0.00 : (float)$crnValue;
                                $tempArray['dramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)0.00;
                            }elseif($value[4] != ""){
                                $crnValue = str_replace('(','',str_replace(')','',str_replace(',', '',$value[4])));
                                $tempArray['dramount'] = $crnValue < 0 ? (float)str_replace('-', '',$crnValue) : (float)$crnValue;
                                $tempArray['cramount'] = (float)0.00;
                            }
                        }
                        $tempArray['narration'] = $value[2];
                        $sellerArrayBeautifier[] = $tempArray;
                    }
                }
            }            

            // Merge salzing and zoho statement data in an array.
            if(count($cleanedStatement) > 0){
                $sellerArrayBeautifier = array_merge($cleanedStatement, $sellerArrayBeautifier);
            }

            // if (File::exists($fullPath)) {
            //     File::delete($fullPath);
            // }
            // $statement_data[]=$sellerArrayBeautifier;
        }

        $statement_data = array_merge($arrayBeautifier, $sellerArrayBeautifier);

        usort($statement_data, function ($a, $b) {
            return strtotime($a['trn_date']) - strtotime($b['trn_date']);
        });
        

        // calculate the running ballance
        $balance = 0.00;
        foreach($statement_data as $gKey=>$gValue){
            $balance += (float)$gValue['dramount'] - (float)$gValue['cramount'];
            $statement_data[$gKey]['running_balance'] = $balance;
        }

        // Insert closing balance into array
        $tempArray['trn_no'] = "";
        $tempArray['trn_date'] = date('Y-m-d');
        $tempArray['vouchertypebasename'] = "";
        $tempArray['ledgername'] = "closing C/f...";
        $tempArray['ledgerid'] = "";
        if($balance <= 0){
            $tempArray['cramount'] = (float)str_replace('-','',str_replace(',', '',$balance));
            $tempArray['dramount'] = (float)0.00;
        }else{
            $tempArray['dramount'] = (float)str_replace('-','',str_replace(',', '',$balance));
            $tempArray['cramount'] = (float)0.00;
        }
        $tempArray['narration'] = "";
        $statement_data[] = $tempArray;

        $finalStatementArray = $statement_data;
        
        echo "<pre>"; print_r($finalStatementArray); die;
        // return response()->json([
		// 		'error' => 'Failed to download PDF.',
		// 		'details' => $response->body(),
		// 		'status' => $response->status()
		// 	], 500);
    }


    private function getZohoAccessToken()
    {
        $token = ZohoToken::latest()->first();
        // echo $token->refresh_token; die;
        if (!$token) {
            throw new \Exception('No Zoho access token found.');
        }

        // Check if token is expired
        if (now()->gte($token->expires_at)) {
            return $this->refreshZohoAccessToken($token->refresh_token);
        }

        return $token->access_token;
    }


    private function refreshZohoAccessToken($refreshToken)
    {
        $response = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
        ])->json();

        if (isset($response['access_token'])) {
            ZohoToken::truncate(); // clear old tokens
            ZohoToken::create([
                'access_token' => $response['access_token'],
                'refresh_token' => $refreshToken, // Zoho may not always return it again
                'expires_at' => now()->addSeconds($response['expires_in']),
            ]);
            return $response['access_token'];
        }

        throw new \Exception('Failed to refresh Zoho access token.');
    }

    public function handlePaymentCallback(Request $request)
    {
        // Log or handle the callback
        \Log::info('Zoho Payment Callback:', $request->all());

        return response()->json(['message' => 'Callback received'], 200);
    }

    //-------------------------------- Zoho Payment--------------------------

    // ---- For create the access token for payment open this url on browser to create new token ----

    // public function createPaymentLink(Request $request)
    // {
    //     try {
    //         $access_token = "";
    //         $settings = ZohoSetting::where('status','2')->first();
    //         $token = ZohoToken::latest()->first();
    //         $response = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
    //             'grant_type' => 'authorization_code',
    //             'client_id' => $settings->client_id,
    //             'client_secret' => $settings->client_secret,
    //             'redirect_uri' => $settings->redirect_uri, // same as in step 1
    //             'code' => $request->code, // from the query string after redirect
    //         ])->json();
    //         print_r($response); die;
    //         // Save these in DB
    //         $accessToken = $response['access_token'];
    //         $refreshToken = $response['refresh_token'];
    //         $expiresIn = $response['expires_in'];
            
    //         echo "access_token : ".$access_token; die;

    //         // ✅ Validate the request
    //         $validated = $request->validate([
    //             'amount' => 'required|numeric|min:1',
    //             'currency' => 'required|string|max:3',
    //             'email' => 'required|email',
    //             'phone' => 'required|string|max:20',
    //             'reference_id' => 'nullable|string|max:100',
    //             'description' => 'required|string|max:255',
    //             'expires_at' => 'nullable|date_format:Y-m-d',
    //             'return_url' => 'nullable|url',
    //             'notify_user' => 'nullable|boolean',
    //         ]);

    //         // ✅ Get access token and account ID
    //         $accountId = env('ZOHO_PAYMENT_ACCOUNT_ID');
    //         $clientId = env('ZOHO_PAYMENT_CLIENT_ID');
    //         $clientSecret = env('ZOHO_PAYMENT_CLIENT_SECRET');
            
    //         // print_r($validated); die;

    //         // ✅ Prepare payload
    //         $payload = [
    //             'amount' => (float) $validated['amount'],
    //             'currency' => $validated['currency'],
    //             'email' => $validated['email'],
    //             'phone' => $validated['phone'],
    //             'reference_id' => $validated['reference_id'],
    //             'description' => $validated['description'],
    //             'expires_at' => $validated['expires_at'],
    //             'return_url' => $validated['return_url'],
    //             'notify_user' => $validated['notify_user'],
    //         ];

    //         // ✅ Send to Zoho
    //         // $response = Http::withToken($accessToken)
    //         //     ->withHeaders(['Content-Type' => 'application/json'])
    //         //     ->post("https://payments.zoho.in/api/v1/paymentlinks?account_id={$accountId}", $payload);

    //         $response = Http::withHeaders([
    //             'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
    //             'Content-Type' => 'application/json',
    //         ])
    //         ->post("https://payments.zoho.in/api/v1/paymentlinks?account_id={$accountId}", $payload);

    //         if ($response->failed()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Failed to create Zoho Payment Link.',
    //                 'error' => $response->json(),
    //             ], $response->status());
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'payment_link' => $response->json(),
    //         ]);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation failed.',
    //             'errors' => $e->errors()
    //         ], 422);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'An unexpected error occurred.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    
    // public function createPaymentLinkV01(Request $request)
    // {
    //     try {
    //         $validated = $request->validate([
    //             'amount' => 'required|numeric|min:1',
    //             'currency' => 'required|string|max:3',
    //             'email' => 'required|email',
    //             'phone' => 'required|string|max:20',
    //             'reference_id' => 'nullable|string|max:100',
    //             'description' => 'required|string|max:255',
    //             'expires_at' => 'nullable|date_format:Y-m-d',
    //             'return_url' => 'nullable|url',
    //             'notify_user' => 'nullable|boolean',
    //         ]);

    //         $accessToken = $this->getZohoPaymentAccessToken();
    //         $accountId = env('ZOHO_PAYMENT_ACCOUNT_ID');
            
    //         $payload = [
    //             'amount' => (float) $validated['amount'],
    //             'currency' => $validated['currency'],
    //             'email' => $validated['email'],
    //             'phone' => $validated['phone'],
    //             'reference_id' => $validated['reference_id'] ?? null,
    //             'description' => $validated['description'],
    //             'expires_at' => $validated['expires_at'] ?? null,
    //             'return_url' => $validated['return_url'] ?? null,
    //             'notify_user' => filter_var($validated['notify_user'] ?? false, FILTER_VALIDATE_BOOLEAN),
    //         ];
            
    //         $response = Http::withHeaders([
    //             'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
    //             'Content-Type' => 'application/json',
    //         ])->post("https://payments.zoho.in/api/v1/paymentlinks?account_id={$accountId}", $payload);
            
    //         if ($response->failed()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Failed to create Zoho Payment Link.',
    //                 'error' => $response->json(),
    //             ], $response->status());
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'payment_link' => $response->json(),
    //         ]);

    //         // $accessToken = $this->getZohoPaymentAccessToken();
    //         // try {
    //         //     // $accessToken = cache('zoho_pay_access_token'); // wherever you store it
    //         //     $accessToken = $this->getZohoPaymentAccessToken();
    //         //     // echo $accessToken; die;
    //         //     $data = [
    //         //         'amount'       => (int) $request->amount, // in paise if Zoho expects that; else exact units
    //         //         'currency'     => 'INR',
    //         //         'purpose'      => 'Order Payment',
    //         //         'redirect_url' => 'https://mazingbusiness.com/zoho/payment-callback',
    //         //         'reference_id' => 'ORDER_' . now()->timestamp,
    //         //         'buyer'        => [
    //         //             'name'    => 'Atanu',
    //         //             'email'   => 'atanu.mazing@com',
    //         //             'contact' => '+919804722029',
    //         //         ],
    //         //     ];

    //         //     $json = $this->zohoPaymentsRequest(
    //         //         'post',
    //         //         'https://payments.zoho.in/api/v1/payment-intents',
    //         //         $data,
    //         //         $accessToken
    //         //     );

    //         //     return response()->json([
    //         //         'payment_url' => data_get($json, 'data.payment_url'),
    //         //         'intent_id'   => data_get($json, 'data.intent_id'),
    //         //     ]);
    //         // } catch (\Throwable $e) {
    //         //     // Show *something* useful to frontend while keeping full details in logs
    //         //     return response()->json([
    //         //         'error' => 'Zoho create intent failed',
    //         //         'hint'  => 'Check server logs for X-Request-Id and response body',
    //         //         'msg'   => $e->getMessage(),
    //         //     ], 500);
    //         // }

    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation failed.',
    //             'errors' => $e->errors()
    //         ], 422);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'An unexpected error occurred.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    // public function zohoPaymentsRequest(string $method, string $url, array $payload = [], string $token = null)
    // {
    //     $req = Http::withHeaders([
    //         'Authorization' => 'Zoho-oauthtoken ' . $token,
    //         'Accept'        => 'application/json',
    //         'Content-Type'  => 'application/json',
    //     ]);

    //     $resp = $req->$method($url, $payload);

    //     // Log everything useful
    //     logger()->error('Zoho Payments API', [
    //         'url'         => $url,
    //         'status'      => $resp->status(),
    //         'x-requestid' => $resp->header('X-Request-Id'),
    //         'body'        => $resp->body(),
    //         'payload'     => $payload,
    //     ]);

    //     if ($resp->serverError()) {
    //         // Bubble up with details we just logged
    //         throw new RequestException($resp);
    //     }

    //     print_r($resp->json()); die;

    //     return $resp->json();
    // }
    
    public function startZohoPaymentAuth()
    {
        $accountId = env('ZOHO_PAYMENT_ACCOUNT_ID');
        $settings = ZohoSetting::where('status', '2')->first();

        $authUrl = 'https://accounts.zoho.in/oauth/v2/org/auth?' . http_build_query([
            'scope'         => 'ZohoPay.payments.CREATE,ZohoPay.payments.READ',
            'client_id'     => $settings->client_id,
            'soid'          => 'zohopay.' . $accountId,
            'response_type' => 'code',
            'redirect_uri'  => 'https://mazingbusiness.com/zoho/payment-callback',
            'access_type'   => 'offline',
        ]);
        return redirect()->away($authUrl);
    }

    private function ensureZohoPaymentAuthorized(string $accessToken, string $expectedAccountId): void
    {
        $resp = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
            'Content-Type'  => 'application/json',
        ])->get('https://payments.zoho.in/api/v1/accounts');

        if ($resp->failed()) {
            throw new \Exception('Unable to read Zoho Payments accounts: ' . $resp->body());
        }

        $accounts = $resp->json()['accounts'] ?? [];
        $found = collect($accounts)->firstWhere('account_id', $expectedAccountId);

        if (!$found) {
            // Token isn’t authorized for this account_id
            throw new \Exception(
                "Current token isn’t authorized for account {$expectedAccountId}. " .
                "Please re-authorize using /zoho/payment-auth with the owner/admin Zoho login."
            );
        }
    }

    public function paymentCallback(Request $request)
    {
        $settings = ZohoSetting::where('status', '2')->first();

        $response = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
            'grant_type'    => 'authorization_code',
            'client_id'     => $settings->client_id,
            'client_secret' => $settings->client_secret,
            'redirect_uri'  => 'https://mazingbusiness.com/zoho/payment-callback',
            'code'          => $request->code,
        ])->json();

        if (!empty($response['access_token'])) {
            ZohoPaymentToken::truncate();
            ZohoPaymentToken::create([
                'access_token'  => $response['access_token'],
                'refresh_token' => $response['refresh_token'],
                'expires_at'    => now()->addSeconds($response['expires_in_sec']),
            ]);
            return "Token stored successfully!";
        }
        return response()->json($response);
    }

    private function getZohoPaymentAccessTokenOrAuthResponse()
    {
        $token    = ZohoPaymentToken::first();
        $settings = ZohoSetting::where('status', '2')->first();
        

        if (!$token) {
            return ['auth_required' => true, 'auth_url' => $this->buildZohoPaymentAuthUrl()];
        }

        if (now()->greaterThanOrEqualTo($token->expires_at)) {
                        
            $refresh = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
                'grant_type'    => 'refresh_token',
                'client_id'     => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'refresh_token' => $token->refresh_token,
            ]);
            
            $data = $refresh->json();
            if (!$refresh->successful() || empty($data['access_token'])) {
                return ['auth_required' => true, 'auth_url' => $this->buildZohoPaymentAuthUrl()];
            }

            $token->update([
                'access_token' => $data['access_token'],
                'expires_at'   => now()->addSeconds($data['expires_in_sec']),
            ]);
        }

        return ['auth_required' => false, 'access_token' => $token->access_token];
    }

    private function buildZohoPaymentAuthUrl(): string
    {
        $accountId = env('ZOHO_PAYMENT_ACCOUNT_ID');
        $settings  = ZohoSetting::where('status', '2')->firstOrFail();

        return 'https://accounts.zoho.in/oauth/v2/org/auth?' . http_build_query([
            'scope'         => 'ZohoPay.payments.CREATE,ZohoPay.payments.READ',
            'client_id'     => $settings->client_id,
            'soid'          => 'zohopay.' . $accountId,
            'response_type' => 'code',
            'redirect_uri'  => 'https://mazingbusiness.com/zoho/payment-callback',
            'access_type'   => 'offline',
            'state'         => 'payments_auth',
        ]);
    }

    private function tokenHasAccessToAccount(string $accessToken, string $expectedAccountId): bool
    {
        $resp = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
            'Content-Type'  => 'application/json',
        ])->get('https://payments.zoho.in/api/v1/accounts');

        if ($resp->failed()) {
            // Not authorized or token missing Payments access
            return false;
        }

        $accounts = $resp->json()['accounts'] ?? [];
        return collect($accounts)->contains(fn ($a) => ($a['account_id'] ?? null) === $expectedAccountId);
    }

    public function createPaymentLink(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|numeric|min:1',
                'amount' => 'required|numeric|min:1',
                'dueAmount' => 'required|numeric',
                'overdueAmount' => 'required|numeric',
                // 'currency' => 'required|string|max:3',
                // 'email' => 'required|email',
                // 'phone' => 'required|string|max:20',
                // 'reference_id' => 'nullable|string|max:100',
                'description' => 'required|string|max:255',
                // 'expires_at' => 'nullable|date_format:Y-m-d',
                // 'return_url' => 'nullable|url',
                'notify_user' => 'nullable|boolean',
            ]);

            // // 1) Get/refresh token or return an auth URL
            $tok = $this->getZohoPaymentAccessTokenOrAuthResponse();
            // print_r($tok); die;
            if ($tok['auth_required'] ?? false) {
                // If browser request: redirect silently
                if (!$request->expectsJson()) {
                    return redirect()->away($tok['auth_url']);
                }
                // If API request: return JSON with next step
                return response()->json([
                    'success'     => false,
                    'auth_required' => true,
                    'message'     => 'Zoho Payments needs authorization.',
                    'auth_url'    => $tok['auth_url'],
                ], 401);
            }

            $accessToken = $tok['access_token'];
            $accountId   = env('ZOHO_PAYMENT_ACCOUNT_ID');
            $userData = User::where('id', $validated['user_id'])->first();
            $expires_at = date('Y-m-d', strtotime('+1 day'));
            $payload = [
                'amount' => (float) $validated['amount'],
                'currency' => 'INR',
                'email' => trim($userData->email) !== '' ? $userData->email : 'admin@mazing.store',
                'phone' => preg_replace('/\s+|-/', '', $userData->phone),
                'reference_id' => $userData->party_code ?? null,
                'description' => $validated['description'],
                'expires_at' => $expires_at ?? null,
                'return_url' => "https://mazingbusiness.com/zoho/after-payment-redirect" ?? null,
                'notify_user' => filter_var($validated['notify_user'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://payments.zoho.in/api/v1/paymentlinks?account_id={$accountId}", $payload);

            // echo "<pre>..."; print_r($response->json()); die;
            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create Zoho Payment Link.',
                    'error' => $response->json(),
                ], $response->status());
            }
            
            $data = $response->json();
            $paymentUrl = $data['payment_links']['url'] ?? null;
            $payment_link_id = $data['payment_links']['payment_link_id'] ?? null;

            ZohoPayment::create([
                'payment_link_url' => $paymentUrl,
                'payment_link_id' => $payment_link_id,
                'expires_at' => $expires_at ?? null,
                'payable_amount' => (float) $validated['amount'],
                'description' => $validated['description'],
                'user_id' => $userData->id,
                'party_code' => $userData->party_code ?? null,
                'email' => trim($userData->email) !== '' ? $userData->email : 'admin@mazing.store',
                'phone' => $userData->phone ?? null,
                'send_by_id' => Auth::id(),
                'payment_status' => '0'
            ]);
            // die;
            $this->sendStatementPaymentReminder($paymentUrl,(float) $validated['amount'], $validated['user_id'], $validated['dueAmount'], $validated['overdueAmount'] );
            return response()->json([
                'success' => true,
                'payment_link' => $response->json(),
            ]); 

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function sendStatementPaymentReminder($zohoPaymentUrl, $totalPayable, $user_id, $dueAmount, $overdueAmount)
    {
        try {
            $userData = User::where('id', $user_id)->first();
            // ✅ Inputs and defaults
            $to = preg_replace('/^\+91/', '', $userData->phone);
            // $to = '9804722029'; // Recipient phone number (your number)
            $customerName = $userData->company_name;            
            $managerPhone = preg_replace('/^\+91/', '', $userData->get_manager->phone);
            // $dueAmount = '5000.00';
            // $overdueAmount = '3000.00';
            // $totalPayable = '8000.00';            

            // ✅ Extract payment ID from link
            $paymentHash = basename($zohoPaymentUrl); // Only the last part

            // ✅ WhatsApp template name must match approved template
            $templateData = [
                'name' => 'utility_statement_paynow', // Replace with your actual template name
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $customerName],   // {{1}}
                            ['type' => 'text', 'text' => number_format($dueAmount,2)],       // {{2}}
                            ['type' => 'text', 'text' => number_format($overdueAmount,2)],   // {{3}}
                            ['type' => 'text', 'text' => number_format($totalPayable,2)],    // {{4}}
                            ['type' => 'text', 'text' => $managerPhone],    // {{5}}
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            ['type' => 'text', 'text' => $paymentHash],     // Pass only the hash
                        ],
                    ],
                ],
            ];

            $WhatsAppWebService = new WhatsAppWebService();
            // ✅ Send using WhatsApp service
            $response = $WhatsAppWebService->sendTemplateMessage($to, $templateData);

            return response()->json([
                'status' => 'success',
                'sent_to' => $to,
                'payment_hash' => $paymentHash,
                'response' => $response
            ]);
        } catch (\Exception $e) {
            \Log::error('WhatsApp template send failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function afterPaymentRedirect(Request $request){
        // print_r($request);
        $getZohoPaymentRecord = ZohoPayment::where('payment_link_id',$request->payment_link_id)->first();
        $getZohoPaymentRecord->payment_status = $request->status;
        $getZohoPaymentRecord->save();

        $paymentStatus = $request->status;
        $paumentId = $request->payment_id;
        $paidAmount = $request->amount;
        // echo "Successfully Payment";

        return view('frontend.zoho_payment.success', compact('paymentStatus', 'paumentId', 'paidAmount'));
    }

    
}
