<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderApproval;
use App\Models\Upload;
use App\Models\Product;
use App\Models\RewardPointsOfUser;
use App\Models\ZohoSetting;
use App\Models\ZohoToken;
use App\Models\UserSalzingStatement;
use App\Models\Seller;
use GuzzleHttp\Client;


use App\Models\SubOrder;
use App\Models\SubOrderDetail;
use App\Models\Challan;
use App\Models\ChallanDetail;
use App\Models\InvoiceOrder;
use App\Models\InvoiceOrderDetail;
use App\Models\Barcode;
use App\Models\Warehouse;
use App\Models\Pincode;
use App\Models\City;
use App\Models\State;
use App\Models\WarrantyUser;
use App\Models\WarrantyClaim;
use App\Models\WarrantyClaimDetail;

use Illuminate\Support\Str;

use PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Jobs\SyncSalzingStatement;
use App\Jobs\SyncSalzingStatementForOpeningBalance;
use App\Services\WhatsAppWebService;
use App\Http\Controllers\AdminStatementController;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

use App\Jobs\UpdateProductStockJob;
use App\Services\PdfContentService;

class PurchaseHistoryController extends Controller
{

    private $clientId, $clientSecret, $redirectUri, $orgId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $settings = ZohoSetting::where('status','0')->first();

        $this->clientId = $settings->client_id;
        $this->clientSecret = $settings->client_secret;
        $this->redirectUri = $settings->redirect_uri;
        $this->orgId = $settings->organization_id;
    }

    public function warrantyClaim(Request $request)
    {
        $warrantyUserId = (int) $request->cookie('warranty_user');
        if ($warrantyUserId) {
            return redirect()->route('warrantyClaimDetails');
        }
        return view('frontend.warrantyClaim');
    }
    
    public function warrantyClaimPost(Request $request)
    {
        // If otpVerified isn't present, default to 0
        $otpVerified = (int) $request->input('otpVerified', 0);
        $phone = '+91'.$request->input('phone');
        if ($otpVerified === 0) {
            // Redirect back to the GET page
            return redirect()
                ->route('warrantyClaim')
                ->with('error', 'Please verify OTP first.'); // optional flash msg
        }

        $getUserData = User::where('phone',$phone)->first();
        // echo "<pre>";print_r($getUserData->address_by_party_code);
        // echo "...".$getUserData->address_by_party_code->gstin; die;

        // Optional: basic guard in case phone not provided
        if (strlen($phone) < 10) {
            return redirect()
                ->route('warrantyClaim')
                ->with('error', 'Please enter a valid phone number.');
        }

        // Either find by phone or create a new row
        $wu = WarrantyUser::firstOrNew(['phone' => $phone]);
        if($getUserData != NULL){
            $wu->user_id = $getUserData->id;
            $wu->party_code = $getUserData->party_code;
            $wu->user_type = $getUserData->user_type;
            $wu->name = $getUserData->company_name;
            $wu->gst = $getUserData->address_by_party_code->gstin;
        }
        $wu->last_login = now();
        $wu->save();

        // Set sliding cookie for 60 minutes with the WarrantyUser id
        Cookie::queue(cookie(
            'warranty_user',            // cookie name (renamed)
            (string) $wu->id,           // cookie value: warranty_users.id
            60,                         // minutes
            null,                       // path
            null,                       // domain
            config('session.secure'),   // secure
            true,                       // httpOnly
            false,                      // raw
            'lax'                       // sameSite
        ));
        return redirect()->route('warrantyClaimDetails');        
    }

    public function warrantyClaimDetails(Request $request)
    {
        $warehouse = Warehouse::find(1);
        $addr = $warehouse->getAddress; // single Address (highest acc_code)
        // If the helper returns a redirect, return it immediately
        if ($resp = $this->checkWarrantyCookie($request)) {
            return $resp; // redirect to warrantyClaim
        }
        // Get cookie value (decrypted automatically)
        $warrantyUserId = (int) Cookie::get('warranty_user');
        $warrantyUser = WarrantyUser::where('id' , $warrantyUserId)->first();
        $warrantyClaim = WarrantyClaim::where('warranty_user_id', $warrantyUserId)->get();
        return view('frontend.warrantyClaimDetails', compact('warrantyClaim','warrantyUserId','warrantyUser'));
    }

    public function warrantyDetails(Request $request)
    {
        // If the helper returns a redirect, return it immediately
        if ($resp = $this->checkWarrantyCookie($request)) {
            return $resp; // redirect to warrantyClaim
        }
        // Load claim by ticket id + eager-load related detail info for the view
        $claim = WarrantyClaim::query()
            ->where('ticket_id', $request->ticket_id)
            ->with([
                // load relations you actually show in the table:
                'details.product:id,name',            // Product (main)
                'details.warrantyProduct:id,name',    // Warranty/Spare product
            ])
            ->firstOrFail();

        // You’re already storing warehouse_id on claim
        $warehouse = $claim->warehouse_id ? Warehouse::find($claim->warehouse_id) : null;

        // The Blade expects $details
        $details = $claim->details;

        return view('frontend.warranty_details', compact('claim', 'details', 'warehouse'));
    }

    public function warrantyUserTypePost(Request $request)
    {
        try{
            // If you already use cookie gating, keep it:
            if ($resp = $this->checkWarrantyCookie($request)) {
                return $resp;
            }
            // Validation rules based on the presence of GSTIN
            if ($request->gstin) {
                $request->validate([
                    'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
                    'company_name' => 'required|string|max:255',
                    'address'      => 'required|string',
                    'address2'     => 'nullable|string',
                    'postal_code'  => 'required',
                    'email'        => 'required|email|unique:users|email',
                    'phone'       => 'required|unique:users,phone',
                    'gstin'        => 'required|string|size:15|unique:users',
                ]);
            } else {
                $request->validate([
                    'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
                    'company_name' => 'required|string|max:255',
                    'email'        => 'required|email|unique:users|email',
                    'phone'       => 'required|unique:users,phone',
                    'aadhar_card'  => 'required|string|size:12',
                    'address'     => 'required|string',
                    'address2'     => 'nullable|string',
                    'postal_code'  => 'required|numeric|digits:6',
                ]);
            }
            // Debug user data before creation
            $user = $request->all();

            $pincode = Pincode::where('pincode', $request->postal_code)->first();
            if (!$pincode) {
                // Retrieve the state by its ID from the form
                $state = State::find($request->state); // Assuming `state` in the request contains the state ID
                // Check if the state exists, if not, return an error (optional, based on your logic)
                if (!$state) {
                    return redirect()->back()->withErrors(['state' => 'State not found']);
                }           
                // Create a new city if it doesn't exist, using default cost and status            
                $city = City::firstOrCreate(
                    ['name' => $request->city],
                    ['state_id' => $state->id, 'cost' => 0.00, 'status' => 1]
                );          
                // Create a new pincode entry
                $pincode = Pincode::create([
                    'pincode' => $request->postal_code,
                    'city' => $city->name,
                    'state' => $state->name,
                ]);           
            } else {
                // Retrieve the state based on the existing pincode's state
                $state = State::where('name', $pincode->state)->first();
            }
            $warehouse = Warehouse::whereRaw('FIND_IN_SET(?, service_states)', [$state->id])->first();       
            if($warehouse->id == 3 OR $warehouse->id == 5){
                $warehouse->id = 6;
            }
            $lastcustomer = User::where('user_type', 'customer')->where('warehouse_id', $warehouse->id)->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
            if ($lastcustomer) {
                $party_code = 'OPEL0' . $warehouse->id . str_pad(((int) substr($lastcustomer->party_code, -5) + 1), 5, '0', STR_PAD_LEFT);
            } else {
                $party_code = 'OPEL0' . $warehouse->id . '00001';
            }
            $user['party_code'] = $party_code;
            $user['warehouse_id'] = $warehouse->id; 
            $user['email_verified_at'] = date("Y-m-d H:i:s");
            // echo "<pre>"; print_r($user); die;
            
            // Create user
            $user = $this->createUser($user);
            // echo "<pre>"; print_r($user); die;
            // Resolve the WarrantyUser id: hidden field OR cookie
            $warrantyUserId = (int) $request->cookie('warranty_user');
            $wu = WarrantyUser::findOrFail($warrantyUserId);
            $wu->user_type = $user->user_type;
            $wu->user_id = $user->id;
            $wu->gst = $user->gstin;
            $wu->party_code = $user->party_code;
            $wu->save();
            // Redirect to details entry page
            // return redirect()->route('warrantyAddProductDetails');

            // ✅ Return JSON with redirect URL for AJAX
            return response()->json([
                'success' => true,
                'redirect' => route('warrantyAddProductDetails'),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(), // first error message
            ], 422);
        }
    }

    public function warrantyAddProductDetails(Request $request){
        // If the helper returns a redirect, return it immediately
        if ($resp = $this->checkWarrantyCookie($request)) {
            return $resp; // redirect to warrantyClaim
        }

        // Get cookie value (decrypted automatically)
        $warrantyUserId = (int) Cookie::get('warranty_user');
        $warrantyUser = WarrantyUser::where('id' , $warrantyUserId)->first();
        // echo $warrantyUser->user_id; die;
        $userData = User::where('id',$warrantyUser->user_id)->first();
        $addresses = $userData->get_addresses()->orderBy('id', 'asc')->groupBy('gstin')->get()->toArray();
        // echo "<pre>"; print_r($addresses);die;

        $warehouses = Warehouse::orderBy('name')->where('active','1')->get(['id','name','address','city_id','state_id','pincode','phone']);

        return view('frontend.warrantyAddProductDetails', compact('warrantyUserId','warrantyUser','userData','addresses','warehouses'));
    }

    public function warrantyBarcodeCheck(Request $request){
        $request->validate([
            'barcode' => 'required|string|max:255',
        ]);

        $part_number = substr($request->barcode, 0, 7);
        $getProductDetails = Product::leftJoin('uploads', 'uploads.id', '=', 'products.thumbnail_img')->where('part_no', $part_number)->first();
        // Prepare product meta for the header
        $productMeta = null;
        if ($getProductDetails) {
            $productMeta = [
                'part_no' => $getProductDetails->part_no,
                'name'    => $getProductDetails->name,
                'description'     => $getProductDetails->description,
                'warranty_duration'=> $getProductDetails->warranty_duration,
                'product_image' => "https://mazingbusiness.com/public/".$getProductDetails->file_name
            ];
        }

        $suitableSpareParts = collect();
        if ($getProductDetails && !empty($getProductDetails->suitable_spare_parts)) {
            $parts = array_filter(array_map('trim', explode(',', $getProductDetails->suitable_spare_parts)));
            $BASE = config('app.url') . '/public/'; // e.g. https://mazingbusiness.com/public/

            $suitableSpareParts = Product::leftJoin('uploads', 'uploads.id', '=', 'products.thumbnail_img')
                ->whereIn('products.part_no', $parts)
                ->orderBy('products.name')
                ->get([
                    'products.part_no as part_number',
                    'products.name as product_name',
                    'products.mrp',
                    'uploads.file_name as thumb_file',
                ])
                ->map(function ($row) use ($BASE) {
                    // build absolute URL (or null if no image)
                    $row->spareImage = $row->thumb_file ? $BASE . $row->thumb_file : null;
                    unset($row->thumb_file);
                    return $row;
                })
                ->values();
        }

        $row = Barcode::where('barcode', $request->barcode)->first();
        if (!$row) {
            return response()->json([
                'found' => false,
                'message' => 'Barcode not found.',
            ]);
        }
        $isWarranty = (int)($row->is_warranty ?? 0) === 1;

        return response()->json([
            'found'               => true,
            'is_warranty'         => $isWarranty,
            'productDetails'      => $productMeta,         // <-- clean, simple meta
            'suitableSpareParts'  => $suitableSpareParts,
            'message'             => $isWarranty ? 'Valid Barcode' : 'Not in Warranty',
        ]);
    }

    public function warrantyInvoiceCheck(Request $request)
    {
        $request->validate([
            'invoice_no' => 'required|string|max:255',
            'barcode' => 'required|string|max:255',
        ]);
        // Find invoice by number (only need created_at)
        $inv = InvoiceOrder::where('invoice_no', $request->invoice_no)->first();
        if (!$inv) {
            $partNo = null;
            if (!empty($request->barcode)) {
                $partNo = substr($request->barcode, 0, 7);
            }

            $getProductDetails = Product::leftJoin('uploads', 'uploads.id', '=', 'products.thumbnail_img')->where('part_no', $partNo)->first();
            // Prepare product meta for the header
            $productMeta = null;
            if ($getProductDetails) {
                $productMeta = [
                    'part_no' => $getProductDetails->part_no,
                    'name'    => $getProductDetails->name,
                    'description'     => $getProductDetails->description,
                    'warranty_duration'=> $getProductDetails->warranty_duration,
                    'product_image' => "https://mazingbusiness.com/public/".$getProductDetails->file_name
                ];
            }
            $suitableSpareParts = collect();
            if ($getProductDetails && !empty($getProductDetails->suitable_spare_parts)) {
                $parts = array_unique(array_filter(array_map('trim', explode(',', $getProductDetails->suitable_spare_parts))));
                $BASE  = config('app.url') . '/public/'; // e.g. https://mazingbusiness.com/public/

                // 1) Fetch the spare products + their image + (IMPORTANT) product_id
                $productRows = Product::leftJoin('uploads', 'uploads.id', '=', 'products.thumbnail_img')
                    ->whereIn('products.part_no', $parts)
                    ->orderBy('products.name')
                    ->get([
                        'products.id as product_id',              // <-- needed to compare with warranty_claim_details.warranty_product_id
                        'products.part_no as part_number',
                        'products.name as product_name',
                        'products.mrp',
                        'uploads.file_name as thumb_file',
                    ]);

                // 2) Gather IDs for a single IN() query
                $productIds   = $productRows->pluck('product_id')->all();
                $invoiceNo    = $request->input('invoice_no');      // <- send this from JS (same row)
                $fullBarcode  = $request->input('barcode');         // full barcode (already in this request)

                // 3) Find which of these products are already claimed for THIS invoice + barcode
                $alreadyIds = [];
                if ($invoiceNo && $fullBarcode && !empty($productIds)) {
                    $alreadyIds = WarrantyClaimDetail::where('invoice_no', $invoiceNo)
                        ->where('barcode', $fullBarcode)
                        ->whereIn('warranty_product_id', $productIds)
                        ->pluck('warranty_product_id')
                        ->all();
                }

                // 4) Build the final list with image URL + already_applied flag
                $suitableSpareParts = $productRows->map(function ($row) use ($BASE, $alreadyIds) {
                        $row->spareImage       = $row->thumb_file ? $BASE . $row->thumb_file : null;
                        $row->already_applied  = in_array($row->product_id, $alreadyIds) ? 1 : 0;
                        unset($row->thumb_file);
                        // If you don’t want to expose product_id to the frontend:
                        // unset($row->product_id);
                        return $row;
                    })
                    ->values();
            }
            // echo "<pre>"; print_r($suitableSpareParts); die;
            return response()->json([
                'found'   => false,
                'message' => '',
                'productDetails'      => $productMeta,
                'suitableSpareParts'  => $suitableSpareParts,
            ]);
        }else{
            $partNo = null;
            if (!empty($request->barcode)) {
                $partNo = substr($request->barcode, 0, 7);
            }

            // Check product is in warranty or not.
            $months = 0;
            if ($partNo) {
                $product = Product::where('part_no', $partNo)->select('part_no', 'warranty_duration')->first();
                if ($product && is_numeric($product->warranty_duration)) {
                    $months = (int) $product->warranty_duration;  // e.g., "6" => 6 months
                }
            }
            $tz         = 'Asia/Kolkata';
            $purchaseAt = Carbon::parse($inv->created_at)->timezone($tz);
            $expiryAt   = $purchaseAt->copy()->addMonthsNoOverflow($months)->endOfDay();
            $now        = Carbon::now($tz);

            $expired = $now->gt($expiryAt);
            // ✅ If expired, treat as NOT found (per your requirement)
            if ($expired) {
                return response()->json([
                    'found'               => false,
                    'message'             => 'Warranty expired on ' . $expiryAt->format('d-m-Y') . '.',
                    'warranty_expired'    => true,
                    'warranty_expires_on' => $expiryAt->toDateString(),
                    'productDetails'      => '',
                    'suitableSpareParts'  => '',
                ]);
            }else{

                $getProductDetails = Product::leftJoin('uploads', 'uploads.id', '=', 'products.thumbnail_img')->where('part_no', $partNo)->first();
                // Prepare product meta for the header
                $productMeta = null;
                if ($getProductDetails) {
                    $productMeta = [
                        'part_no' => $getProductDetails->part_no,
                        'name'    => $getProductDetails->name,
                        'description'     => $getProductDetails->description,
                        'warranty_duration'=> $getProductDetails->warranty_duration,
                        'product_image' => "https://mazingbusiness.com/public/".$getProductDetails->file_name
                    ];
                }

                $suitableSpareParts = collect();
                if ($getProductDetails && !empty($getProductDetails->suitable_spare_parts)) {
                    $parts = array_unique(array_filter(array_map('trim', explode(',', $getProductDetails->suitable_spare_parts))));
                    $BASE  = config('app.url') . '/public/'; // e.g. https://mazingbusiness.com/public/

                    // 1) Fetch the spare products + their image + (IMPORTANT) product_id
                    $productRows = Product::leftJoin('uploads', 'uploads.id', '=', 'products.thumbnail_img')
                        ->whereIn('products.part_no', $parts)
                        ->orderBy('products.name')
                        ->get([
                            'products.id as product_id',              // <-- needed to compare with warranty_claim_details.warranty_product_id
                            'products.part_no as part_number',
                            'products.name as product_name',
                            'products.mrp',
                            'uploads.file_name as thumb_file',
                        ]);

                    // 2) Gather IDs for a single IN() query
                    $productIds   = $productRows->pluck('product_id')->all();
                    $invoiceNo    = $request->input('invoice_no');      // <- send this from JS (same row)
                    $fullBarcode  = $request->input('barcode');         // full barcode (already in this request)

                    // 3) Find which of these products are already claimed for THIS invoice + barcode
                    $alreadyIds = [];
                    if ($invoiceNo && $fullBarcode && !empty($productIds)) {
                        $alreadyIds = WarrantyClaimDetail::where('invoice_no', $invoiceNo)
                            ->where('barcode', $fullBarcode)
                            ->whereIn('warranty_product_id', $productIds)
                            ->pluck('warranty_product_id')
                            ->all();
                    }

                    // 4) Build the final list with image URL + already_applied flag
                    $suitableSpareParts = $productRows->map(function ($row) use ($BASE, $alreadyIds) {
                            $row->spareImage       = $row->thumb_file ? $BASE . $row->thumb_file : null;
                            $row->already_applied  = in_array($row->product_id, $alreadyIds) ? 1 : 0;
                            unset($row->thumb_file);
                            // If you don’t want to expose product_id to the frontend:
                            // unset($row->product_id);
                            return $row;
                        })
                        ->values();
                }

                return response()->json([
                    'found'               => true,
                    'message'             => 'Invoice found. Under warranty.',
                    'purchase_date'       => $purchaseAt->toDateString(),
                    'warranty_months'     => $months,
                    'warranty_expires_on' => $expiryAt->toDateString(),
                    'is_warranty'         => true,
                    'warranty_expired'    => false,
                    'productDetails'      => $productMeta,
                    'suitableSpareParts'  => $suitableSpareParts,
                ]);
            }            
        }

        return response()->json([
            'found'         => true,
            'purchase_date' => $dt->format('Y-m-d'), // suitable for <input type="date">
            'message'       => 'Invoice found.',
        ]);
    }

    public function warrantyDateCheck(Request $request)
    {
        $request->validate([
            'date'    => 'required|date',                 // ensure valid date
            'barcode' => 'required|string|max:255',
            'invoice' => 'required|string|max:255',       // make this 'nullable|string|max:255' if you want it optional
        ]);

        // product by part_no from barcode
        $part_number = substr($request->barcode, 0, 7);
        $product = Product::where('part_no', $part_number)->select('warranty_duration')->first();

        if (!$product) {
            return response()->json([
                'found'   => false,
                'message' => 'Product not found for this barcode.',
            ]);
        }

        $months = (int) ($product->warranty_duration ?? 0);
        $tz         = 'Asia/Kolkata';
        $purchaseAt = Carbon::parse($request->date, $tz);
        $expiryAt   = $purchaseAt->copy()->addMonthsNoOverflow($months)->endOfDay();
        $now        = Carbon::now($tz);
        $expired    = $now->gt($expiryAt);

        if ($expired) {
            return response()->json([
                'found'               => false,
                'message'             => 'Warranty expired on ' . $expiryAt->format('d-m-Y') . '.',
                'warranty_expired'    => true,
                'warranty_expires_on' => $expiryAt->toDateString(),
            ]);
        }

        return response()->json([
            'found'               => true,
            'message'             => '',
            'warranty_expired'    => false,
            'warranty_expires_on' => $expiryAt->toDateString(),
            'warranty_months'     => $months,
        ]);
    }

    public function warrantyLogout(Request $request)
    {
        // Delete the cookie (matches the one you set earlier)
        Cookie::queue(Cookie::forget('warranty_user')); // default path '/' & domain null

        // Redirect to the claim page
        return redirect()->route('warrantyClaim');
    }

    public function warrantySubmit(Request $request)
    {
        try {
                // ---------- Build validation rules (per-row) ----------
                $baseRules = [
                    'company_name'        => 'required|string|max:255',
                    'name'                => 'required|string|max:255', // (phone) as per your form
                    'email'               => 'required|email',
                    'address'             => 'required|string',
                    'postal_code'         => 'required|string|max:20',
                    'rows'                => 'required|array|min:1',
                    'warehouse_id'        => 'required',
                    'terms_and_condition' => 'required',
                ];

                $rows     = $request->input('rows', []);
                $rules    = $baseRules;
                $messages = [];

                foreach ($rows as $i => $row) {
                    $rules["rows.$i.barcode"]        = 'required|string|max:255';
                    $rules["rows.$i.invoice"]        = 'required|string|max:255';
                    $rules["rows.$i.purchase_date"]  = 'required|date|before_or_equal:today';

                    // At least ONE of the two files is required for this row:
                    $rules["rows.$i.upload_invoice"] = 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,webp'
                        . "|required_without:rows.$i.warranty_card";
                    $rules["rows.$i.warranty_card"]  = 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,webp'
                        . "|required_without:rows.$i.upload_invoice";

                    $rules["rows.$i.suitable_spares"]    = 'required|array';
                    $rules["rows.$i.suitable_spares.*"]  = 'string|max:50';

                    $rowNum = $i + 1;
                    $messages["rows.$i.upload_invoice.required_without"] = "Row #$rowNum: Upload either the Invoice or the Warranty Card.";
                    $messages["rows.$i.warranty_card.required_without"]  = "Row #$rowNum: Upload either the Invoice or the Warranty Card.";
                }

                $validator = Validator::make($request->all(), $rules, $messages);

                // Composite duplicate check: (barcode + invoice) must be unique within submission
                $validator->after(function ($v) use ($rows) {
                    $seen = []; // key: "barcode|invoice" -> first row index

                    foreach ($rows as $i => $row) {
                        $barcode = strtolower(trim((string)($row['barcode'] ?? '')));
                        $invoice = strtolower(trim((string)($row['invoice'] ?? '')));

                        // only check when both present
                        if ($barcode === '' || $invoice === '') {
                            continue;
                        }

                        $key = $barcode . '|' . $invoice;

                        if (isset($seen[$key])) {
                            $firstRow = $seen[$key] + 1;
                            $currRow  = $i + 1;
                            $msg = "Row #$currRow duplicates the same Barcode + Invoice as Row #$firstRow.";

                            // attach once (to barcode and invoice) for clearer UX
                            $v->errors()->add("rows.$i.barcode", $msg);
                            $v->errors()->add("rows.$i.invoice", $msg);
                        } else {
                            $seen[$key] = $i;
                        }
                    }
                });

                $validator->validate();


                // who is filing...
                $warrantyUserId = $request->cookie('warranty_user') ?: Auth::id();
                $wu = WarrantyUser::findOrFail($warrantyUserId);
                $user_id = $wu->user_id;
                $gstin  = $request->input('gst');
                $aadhar = $request->input('aadhar');

                // --- do inserts inside a transaction; return claim + ticketId back out ---
                $payload = DB::transaction(function () use ($request, $warrantyUserId, $gstin, $aadhar, $user_id) {

                    $ticketId = $this->generateNextTicketId();

                    $claim = WarrantyClaim::create([
                        'warranty_user_id' => $warrantyUserId,
                        'ticket_id'        => $ticketId,
                        'user_id'          => $user_id,
                        'address_id'       => $request->input('address_id'),
                        'status'           => 'Pending for courier details upload',
                        'name'             => $request->input('company_name'),
                        'phone'            => $request->input('name'),
                        'email'            => $request->input('email'),
                        'gstin'            => $gstin,
                        'aadhar_card'      => $aadhar,
                        'address'          => $request->input('address'),
                        'address_2'        => $request->input('address_2'),
                        'city'             => $request->input('city'),
                        'postal_code'      => $request->input('postal_code'),
                        'warehouse_id'     => $request->input('warehouse_id'),
                        'warehouse_address'=> $request->input('warehouse_address'),
                    ]);

                    foreach ($request->input('rows', []) as $i => $row) {
                        $barcode      = trim((string)($row['barcode'] ?? ''));
                        $invoiceNo    = trim((string)($row['invoice'] ?? ''));
                        $purchaseDate = (string)($row['purchase_date'] ?? '');
                        $spares       = $row['suitable_spares'] ?? [];

                        $partNo  = substr($barcode, 0, 7);
                        $product = Product::where('part_no', $partNo)
                            ->select('id','part_no','warranty_duration')
                            ->first();

                        $productId        = optional($product)->id;
                        $productPartNo    = optional($product)->part_no;
                        $warrantyDuration = (int) (optional($product)->warranty_duration ?? 0);

                        $fileInv  = $request->file("rows.$i.upload_invoice");
                        $fileCard = $request->file("rows.$i.warranty_card");

                        // Save to public/ the same way as your images:
                        $invPath  = $this->saveToPublic($fileInv,  "warranty/{$ticketId}/invoices");
                        $cardPath = $this->saveToPublic($fileCard, "warranty/{$ticketId}/cards");

                        if (!empty($spares)) {
                            foreach ($spares as $spPartNo) {
                                $spProduct = Product::where('part_no', $spPartNo)->select('id','part_no')->first();
                                WarrantyClaimDetail::create([
                                    'warranty_claim_id'            => $claim->id,
                                    'warranty_user_id'             => $warrantyUserId,
                                    'ticket_id'                    => $ticketId,
                                    'barcode'                      => $barcode,
                                    'product_id'                   => $productId,
                                    'part_number'                  => $productPartNo,
                                    'invoice_no'                   => $invoiceNo,
                                    'purchase_date'                => $purchaseDate,
                                    'warranty_product_part_number' => optional($spProduct)->part_no,
                                    'warranty_product_id'          => optional($spProduct)->id,
                                    'warranty_duration'            => $warrantyDuration,
                                    'attachment_invoice'           => $invPath,
                                    'attatchment_warranty_card'    => $cardPath,
                                    'approval_status'              => 'pending',
                                ]);
                            }
                        } else {
                            WarrantyClaimDetail::create([
                                'warranty_claim_id'            => $claim->id,
                                'warranty_user_id'             => $warrantyUserId,
                                'ticket_id'                    => $ticketId,
                                'barcode'                      => $barcode,
                                'product_id'                   => $productId,
                                'part_number'                  => $productPartNo,
                                'invoice_no'                   => $invoiceNo,
                                'purchase_date'                => $purchaseDate,
                                'warranty_product_part_number' => null,
                                'warranty_product_id'          => null,
                                'warranty_duration'            => $warrantyDuration,
                                'attachment_invoice'           => $invPath,
                                'attatchment_warranty_card'    => $cardPath,
                                'approval_status'              => 'pending',
                            ]);
                        }
                    }

                    return ['claim' => $claim, 'ticketId' => $ticketId];
                });

                /** @var \App\Models\WarrantyClaim $claim */
                $claim    = $payload['claim'];
                $ticketId = $payload['ticketId'];

                // ---- Generate the PDF OUTSIDE the transaction ----
                $warehouse = Warehouse::find($claim->warehouse_id);
                // eager load detail lines
                $claim->load(['details' => function($q){ $q->orderBy('id'); }]);

                // Build your PDF data context
                $pdfData = [
                    'claim'     => $claim,
                    'details'   => $claim->details,
                    'warehouse' => $warehouse,
                ];

                // Render
                $pdf = PDF::loadView('frontend.shipping_label', $pdfData, [], [
                    'format'      => 'A5',   // e.g. A4, A5, Letter, etc.
                    'orientation' => 'P',    // 'P' = portrait, 'L' = landscape
                    'margin_top'    => 6,
                    'margin_right'  => 6,
                    'margin_bottom' => 6,
                    'margin_left'   => 6,
                ]);

                // Save to public/warranty/{ticketId}/label/{ticketId}.pdf
                $relativeDir  = "warranty/{$ticketId}/label";
                $filename     = "{$ticketId}.pdf";
                $this->ensurePublicDir($relativeDir);
                file_put_contents(public_path("$relativeDir/$filename"), $pdf->output());

                // store link on claim
                $claim->pdf_link = "https://mazingbusiness.com/public/"."$relativeDir/$filename"; // e.g. 'warranty/MZW00000001/label/MZW00000001.pdf'
                $claim->save();

                // ⬇️ Show a bridge page that auto-downloads, then redirects
                $downloadUrl = route('warrantyShipPdfDownload', ['ticket' => $ticketId]);
                $redirectUrl = route('warrantyClaimDetails');
                return response()->view('frontend.autodownload', compact('downloadUrl','redirectUrl','ticketId'));
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Important: do NOT pass $request->all()
                return back()->withErrors($e->validator)->withInput();
            }
    }

    public function warrantyShipPdfDownload(Request $request, ?string $ticket = null)
    {
        // Resolve ticket from route or query as fallback (defensive)
        $ticket = $ticket ?? $request->route('ticket') ?? $request->query('ticket');
        abort_if(empty($ticket), 400, 'Ticket is required');

        $claim = WarrantyClaim::where('ticket_id', $ticket)->firstOrFail();
        $url   = $claim->pdf_link;

        // Map full URL → local public path
        $path = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');     // e.g. public/warranty/.../TICKET.pdf
        if (Str::startsWith($path, 'public/')) {
            $path = substr($path, 7);                                // remove leading "public/"
        }

        $absolute = public_path($path);
        abort_unless(is_file($absolute), 404, 'File not found');

        $filename = basename($absolute);

        // ✅ Queue a short-lived cookie so the bridge page knows the download started
        Cookie::queue(cookie('warranty_dl_'.$ticket, '1', 5, '/', null, false, true, false, 'Lax'));

        // Return the download response (no cookie chaining)
        return response()->download($absolute, $filename, [
            'Content-Type'  => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    public function warrantyCorrierInfoUpload (Request $request)
    {
        $request->validate([
            'claim_id'     => 'required|integer|exists:warranty_claims,id',
            'ticket_id'    => 'required|string',
            'courier_file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png,webp',
            'courier_name'     => 'required',
            'tracking_no'     => 'required',
        ]);

        $claim  = WarrantyClaim::findOrFail($request->claim_id);
        $ticket = preg_replace('/[^A-Za-z0-9]/', '', (string)$request->ticket_id); // sanitize

        // Build destination: public/warranty/{ticket}/courier/
        $dirRelative = "warranty/{$ticket}/courier";
        $dirAbsolute = public_path($dirRelative);
        if (!is_dir($dirAbsolute)) {
            @mkdir($dirAbsolute, 0775, true);
        }

        // Create a safe filename
        $ext      = $request->file('courier_file')->getClientOriginalExtension();
        $basename = 'courier_' . date('Ymd_His') . '_' . Str::random(6) . '.' . $ext;

        // Move file
        $request->file('courier_file')->move($dirAbsolute, $basename);

        // Save FULL URL (or relative path—whichever you prefer)
        $publicUrl = url("public/{$dirRelative}/{$basename}"); // e.g. https://.../public/warranty/TICKET/courier/...
        $claim->corrier_info = $publicUrl;                     // ← using your field name as requested
        $claim->courier_name = $request->courier_name;
        $claim->tracking_no = $request->tracking_no;
        $claim->status = 'Pending';
        $claim->save();

        return back()->with('success', 'Courier info uploaded successfully.');
    }

    protected function resolvePublicAbsolutePath(string $stored): string
    {
        // Full URL? -> extract the path part
        if (preg_match('#^https?://#i', $stored)) {
            $path = parse_url($stored, PHP_URL_PATH) ?: '';
            // If your URL includes "/public/...", strip it because public_path() already points to /public
            $path = ltrim(preg_replace('#^/public/#', '', $path), '/');
            return public_path($path); // /var/www/app/public/{path}
        }

        // Absolute filesystem path? return as-is
        if (Str::startsWith($stored, ['/']) || preg_match('#^[A-Za-z]:[/\\\\]#', $stored)) {
            return $stored;
        }

        // Otherwise treat as relative to public/
        return public_path(ltrim($stored, '/'));
    }

    /** Ensure public/{subdir} exists */
    protected function ensurePublicDir(string $subdir): void
    {
        $dir = public_path($subdir);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
    }

    protected function generateNextTicketId(): string
    {
        // Lock to avoid race conditions
        $last = WarrantyClaim::where('ticket_id', 'like', 'MZW%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('ticket_id');

        $n = $last && strlen($last) > 3 ? (int)substr($last, 3) : 0;
        return 'MZW' . str_pad((string)($n + 1), 8, '0', STR_PAD_LEFT);
    }

    protected function saveToPublic(?UploadedFile $file, string $subdir): ?string
    {
        if (!$file) return null;

        $destDir = public_path($subdir);               // e.g. public/warranty/MZW00000001/invoices
        if (!File::isDirectory($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $ext  = $file->getClientOriginalExtension() ?: 'bin';
        $name = uniqid().'.'.$ext;                     // or use Str::uuid().'.'.$ext
        $file->move($destDir, $name);

        // Return path relative to public/ so asset() works
        return rtrim('https://mazingbusiness.com/public/'.$subdir, '/').'/'.$name;         // e.g. warranty/MZW00000001/invoices/64ff....jpg
    }

    protected function createUser(array $data) {
        $pincode      = Pincode::where('pincode', $data['postal_code'])->first();        
        // debug_to_console($party_code);
        // debug_to_console(json_encode($pincode));
        // debug_to_console(json_encode($data));        
        if ($data['gstin']) {
            // echo "<pre>"; print_r($data); die; 
            try {
                $user = User::create([
                    'name'                   => $data['name'],
                    'company_name'           => $data['company_name'],
                    'phone'                  => $data['phone'],
                    'email'                  => $data['email'],
                    //'password'               => Hash::make($data['password']),
                    'address'                => $data['address'],
                    'gstin'                  => $data['gstin'],
                    'aadhar_card'            => $data['aadhar_card'],
                    'postal_code'            => $data['postal_code'],
                    'city'                   => $pincode->city,
                    'state'                  => $pincode->state,
                    'country'                => 'India',
                    'warehouse_id'           => $data['warehouse_id'],
                    //'manager_id'             => $getManager->id,
                    'party_code'             => $data['party_code'],
                    'ledgergroup'            => str_replace(' ','_',$data['name']).$data['party_code'],
                    'virtual_account_number' => $data['party_code'],
                    //'discount'               => $data['discount'],
                    'user_type'              => $data['user_type'],
                    'banned'                 => true,
                    'gst_data'               => $data['gst_data'],
                    'email_verified_at'      => date("Y-m-d H:i:s"),
                    'banned'      =>'1',
                    'unapproved'      =>'0',
                ]);

                // Convert JSON to array          
                $gstDataArray = json_decode($data['gst_data'], true);          
                $gstDataArray = $gstDataArray['taxpayerInfo'];
                if(isset($gstDataArray['adadr']) AND count($gstDataArray['adadr']) > 0){
                    $count = 10;
                    foreach($gstDataArray['adadr'] as $key=>$value){
                        $party_code =$data['party_code'].$count;
                        $address = $value['addr'];
                        $pincode = Pincode::where('pincode', $address['pncd'])->first();
                        $state = State::where('name', $pincode->state)->first();
                        $city = City::where('name', $pincode->city)->first();
                        if(!isset($city->id)){
                            $city = City::create([
                            'name'                   => $pincode->city,
                            'state_id'           => $state->id
                            ]);
                        }else{
                            $city = $city->id;
                        }
                        
                        // $cmp_address = $address['bnm']. ', '.$address['st'] . ', ' .$address['loc'] . ', ' .$address['bno'] . ', ' .$address['dst'];
                        $cmp_address = $address['bnm']. ', '.$address['st'] . ', ' .$address['loc'];
                        $cmp_address2 = $address['bno'] . ', ' .$address['dst'];
                        Address::create([
                            'user_id'=>$user->id,
                            'acc_code'=>$party_code,
                            'company_name'=> $gstDataArray['tradeNam'],
                            'address' => trim($cmp_address,' ,'),
                            'address_2' => trim($cmp_address2,' ,'),
                            'gstin'=> $gstDataArray['gstin'],
                            'country_id' => '101',
                            'state_id'=>$state->id,
                            'city_id'=> $city,
                            'city'=>$address['dst'],
                            'longitude'=> $address['lt'],
                            'latitude'=> $address['lg'],
                            'postal_code'=> $address['pncd'],
                            'phone'=> $data['phone'],
                            'set_default'=> 0
                        ]);
                        $count++;
                    }
                }
                $pincode = Pincode::where('pincode', $gstDataArray['pradr']['addr']['pncd'])->first();
                $city = City::where('name', $pincode->city)->first();
                if(!isset($city->id)){
                    $city= 0;
                }else{
                    $city = $city->id;
                }
                $state = State::where('name', $pincode->state)->first();
                // $cmp_address = $gstDataArray['pradr']['addr']['bnm']. ', '.$gstDataArray['pradr']['addr']['st'] . ', ' .$gstDataArray['pradr']['addr']['loc'] . ', ' .$gstDataArray['pradr']['addr']['bno'] . ', ' .$gstDataArray['pradr']['addr']['dst'];
                $cmp_address = $gstDataArray['pradr']['addr']['bnm']. ', '.$gstDataArray['pradr']['addr']['st'] . ', ' .$gstDataArray['pradr']['addr']['loc'];
                $cmp_address2 = $gstDataArray['pradr']['addr']['bno'] . ', ' .$gstDataArray['pradr']['addr']['dst'];
                $address = Address::create([
                    'user_id'=>$user->id,
                    'acc_code'=>$data['party_code'],
                    'company_name'=> $gstDataArray['tradeNam'],
                    'address' => trim($cmp_address,' ,'),
                    'address_2' => trim($cmp_address2,' ,'),
                    'gstin'=> $gstDataArray['gstin'],
                    'country_id' => '101',
                    'state_id'=>$state->id,
                    'city_id'=> $city,
                    'city'=>$gstDataArray['pradr']['addr']['dst'],
                    'longitude'=> $gstDataArray['pradr']['addr']['lt'],
                    'latitude'=> $gstDataArray['pradr']['addr']['lg'],
                    'postal_code'=> $gstDataArray['pradr']['addr']['pncd'],
                    'phone'=> $data['phone'],
                    'set_default'=> 1
                ]);

            } catch (\Exception $e) {
                debug_to_console($e->getMessage());
                // Log::error($e->getMessage());
                // You can also log the stack trace
                // Log::error($e->getTraceAsString());
            }
        } else {      
            $user = User::create([
                'name'                   => $data['name'],
                'company_name'           => $data['company_name'],
                'phone'                  => $data['phone'],
                'email'                  => $data['email'],
                //'password'               => Hash::make($data['password']),
                'address'                => $data['address'],
                'gstin'                  => null,
                'aadhar_card'            => $data['aadhar_card'],
                'postal_code'            => $data['postal_code'],
                'city'                   => $pincode->city,
                'state'                  => $pincode->state,
                'country'                => 'India',
                'warehouse_id'           => $data['warehouse_id'],
                'party_code'             => $data['party_code'],
                'ledgergroup'            => str_replace(' ','_',$data['name']).$data['party_code'],
                'virtual_account_number' => $data['party_code'],
                'user_type'              => $data['user_type'],
                'banned'                 => true,
                'unapproved'      =>'0',
                'email_verified_at'      => date("Y-m-d H:i:s")
            ]);
            $pincode = Pincode::where('pincode', $data['postal_code'])->first();
            $state = State::where('name', $pincode->state)->first();
            $city = City::where('name', $pincode->city)->first();
            if(!isset($city->id)){
                $city = City::create([
                'name'                   => $pincode->city,
                'state_id'           => $state->id
                ]);
            }else{
                $city = $city->id;
            }
            
            $cmp_address = $data['address'];
            $address = Address::create([
                'user_id'=>$user->id,
                'acc_code'=>$data['party_code'],
                'company_name'=> $data['company_name'],
                'address' => $cmp_address,
                'address_2' => $data['address2'],
                'gstin'=> null,
                'country_id' => '101',
                'state_id'=>$state->id,
                'city_id'=> $city,
                'city'=> $data['city'],
                'longitude'=> null,
                'latitude'=> null,
                'postal_code'=> $data['postal_code'],
                'phone'=> $data['phone'],
                'set_default'=> 1
            ]);
        }


        // ✅ Call Zoho function directly
        // $zoho = new ZohoController();
        // $res= $zoho->createNewCustomerInZoho($user->party_code); // pass the party_code
        
        // // Push User data to Salezing
        // $result=array();
        // $result['party_code']= $user->party_code;
        // $response = Http::withHeaders([
        //     'Content-Type' => 'application/json',
        // ])->post('https://mazingbusiness.com/api/v2/client-push', $result);
        return $user;
    }

    public function checkGsitnExistForWarranty(Request $request) {
        $checkgsitn = User::where('gstin', $request->gstin)->first();
        if(!empty($checkgsitn))  {
            //************WHATSAPP  CODE START**************//
            $templateData = [
                    'name' => 'old_already_registered', // Replace with your template name
                    'language' => 'en', // Replace with your desired language code
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text','text' => $checkgsitn->phone],
                                ['type' => 'text','text' => $checkgsitn->verification_code]
                            ],
                        ]
                    ],
                ];
                $this->WhatsAppWebService=new WhatsAppWebService();
                $whatsappResponse = $this->WhatsAppWebService->sendTemplateMessage($checkgsitn->phone, $templateData);
            //************WHATSAPP  CODE END**************//

            $response['error']  = true;
            $phone = (string) ($checkgsitn->phone ?? '');
            // $maskPhoneNumber = preg_replace('/.(?=.{4}$)/', '*', $phone);
            $maskPhoneNumber = preg_replace('/^(.{4}).{4}(.*)$/', '$1****$2', $phone);
            $response['message'] = 'GSTIN already exists. <a href="'.route('warrantyLogout').'" style="font-size: 18px;font-weight: bold;">Click Here</a> to login with ' . $maskPhoneNumber;
        }
        else {
            $response['status']  = true;
            $response['message'] = 'GSITN not exists!';
        }
        return json_encode($response);
    }


    /**
     * Returns RedirectResponse if cookie missing/invalid, otherwise null.
     */
    private function checkWarrantyCookie(Request $request)
    {
        // Get the id stored in the cookie
        $warrantyUserId = (int) $request->cookie('warranty_user');

        if (! $warrantyUserId) {
            return redirect()->route('warrantyClaim')
                ->with('error', 'Please verify OTP to view this page.');
        }

        // (Optional) sanity check: ensure the row still exists
        if (! WarrantyUser::whereKey($warrantyUserId)->exists()) {
            // Clear invalid cookie and bounce
            Cookie::queue(Cookie::forget('warranty_user'));
            return redirect()->route('warrantyClaim')
                ->with('error', 'Please verify OTP to view this page.');
        }

        // Slide/refresh cookie TTL by another 60 minutes (keep same id)
        Cookie::queue(cookie(
            'warranty_user',
            (string) $warrantyUserId,
            60,
            null,
            null,
            config('session.secure'),
            true,
            false,
            'lax'
        ));

        return null;
    }

    // -----------------------------------------------------------------------------------------------------------------------------------


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index()
    {
        $orders = Order::where('user_id', Auth::user()->id)->orderBy('code', 'desc')->paginate(9);

        foreach ($orders as $key => $value) {
            $value->approved = false;
            $value->bill_amount = "";
            $value->order_details = "";

            // ✅ Fetch related suborders
            $subOrders = SubOrder::where('order_id', $value->id)
                ->where('type', 'sub_order')->get();

            $subOrderIds = $subOrders->pluck('id');

            // ✅ Fetch challans & challan details
            $challans = Challan::whereIn('sub_order_id', $subOrderIds)->get();
            $challanIds = $challans->pluck('id');

            $invoiceDetails = InvoiceOrderDetail::whereIn('challan_id', $challanIds)->get();
            $challanDetails = ChallanDetail::whereIn('challan_id', $challanIds)->get();

            // ✅ Set delivery status
            if ($invoiceDetails->count() > 0) {
                $value->delivery_status = 'Completed';
            } elseif ($challanDetails->count() > 0) {
                $value->delivery_status = 'Dispatched';
            } elseif ($subOrders->count() > 0) {
                $value->delivery_status = 'Approved';
            } else {
                $value->delivery_status = 'Pending';
            }
        }

        return view('frontend.user.purchase_history', compact('orders'));
    }


    public function __index()
    {
        $orders = Order::where('user_id', Auth::user()->id)->orderBy('code', 'desc')->paginate(9);
        foreach ($orders as $key => $value) {            
            $ordersApprovalCount = OrderApproval::where('code', $value->code)->count();
            if ($ordersApprovalCount > 0) {
                $ordersApproval = OrderApproval::where('code', $value->code)->first();
                $value->approved = true;
                $details = $ordersApproval->details;
                if (substr($details, 0, 1) !== '[') {
                    $details = '[' . $details;
                }
                if (substr($details, -1) !== ']') {
                    $details = $details . ']';
                }                
                $detailsData = json_decode($details, true);
                $bill_amount = 0.0;
                // foreach($detailsData as $odKey=>$odValue){
                //     $bill_amount = $bill_amount + (float)$odValue['bill_amount'];
                // }                
                $value->bill_amount = $bill_amount;
                $value->order_details = $detailsData;
            } else {
                $value->approved = false;
                $value->bill_amount = "";
                $value->order_details = "";
            }
        }
        // echo "<pre>";print_r($orders);die;
        return view('frontend.user.purchase_history', compact('orders'));
    }

    public function digital_index()
    {
        $orders = DB::table('orders')
                        ->orderBy('code', 'desc')
                        ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                        ->join('products', 'order_details.product_id', '=', 'products.id')
                        ->where('orders.user_id', Auth::user()->id)
                        ->where('products.digital', '1')
                        ->where('order_details.payment_status', 'paid')
                        ->select('order_details.id')
                        ->paginate(15);
        return view('frontend.user.digital_purchase_history', compact('orders'));
    }

    function correct_json($json) {
        // 1. Escape double quotes inside strings
        $json = preg_replace('/"([^"]*?)"/', '"$1\""$2"', $json);
        
        // 2. Remove unescaped double quotes at the end of a string
        $json = preg_replace('/\"([^-]+?)\"/', '\"$1\"', $json);
        
        // 3. Ensure that there are no trailing commas
        $json = preg_replace('/,\s*([\]}])/', '$1', $json);
        
        return $json;
    }

    
    

public function debugJsonDecodeError($json, $context = "JSON Decode Error") {
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<pre>";
        echo "Error in: $context\n";
        echo "JSON Error: " . json_last_error_msg() . "\n";
        echo "Original JSON: \n" . print_r($json, true) . "\n";
        echo "</pre>";
        die(); // Stop execution for debugging
    }
}

public function sanitizeJsonWithRegex($jsonString) {
    // Define the regex to escape problematic double quotes
    $regex = '/(?<![ ,\\\\])"(?![:,\\}])/';

    // Apply the regex to escape problematic quotes
    $sanitizedJson = preg_replace($regex, '\"', $jsonString);

    // Validate the fixed JSON
    $decoded = json_decode($sanitizedJson, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo 'JSON Error: ' . json_last_error_msg() . "\n";
        die("Problematic JSON: " . $sanitizedJson);
    }

    return json_encode($decoded, JSON_PRETTY_PRINT);
}
public function purchase_history_details($id)
{
    $orderId = decrypt($id);
    $order   = Order::findOrFail($orderId);

    // 1) Load graph
    list(
        $subOrders,
        $challans,
        $challanDetails,
        $invoiceDetails,
        $invoices,
        $btrSubOrders,
        $btrChallans,
        $btrChallanDetails
    ) = $this->loadOrderGraph($order);

    // 2) Build indexes (fast lookups)
    $idx = $this->buildIndexes(
        $subOrders,
        $challans,
        $challanDetails,
        $invoiceDetails,
        $btrSubOrders,
        $btrChallans,
        $btrChallanDetails
    );

    // 3) Build display rows using modular status resolvers
    $finalDetails = [];

    foreach ($subOrders as $subOrder) {
        $warehouseName = isset($subOrder->order_warehouse) && isset($subOrder->order_warehouse->name)
            ? $subOrder->order_warehouse->name
            : 'N/A';

        $filteredChallanIds = isset($idx['challanIdsBySubOrder'][$subOrder->id])
            ? $idx['challanIdsBySubOrder'][$subOrder->id]
            : collect();

        foreach ($subOrder->sub_order_details as $detail) {
            $row = $this->resolveLineStatus(
                $subOrder,
                $detail,
                $filteredChallanIds,
                $idx,
                $invoices,
                $btrChallans,
                $challans,
                $warehouseName
            );

            $finalDetails[] = (object) $row;
        }
    }

    /**
     * 🔁 FALLBACK: if graph-based $finalDetails is empty,
     * build it from OrderDetail + product directly.
     */
    // echo "<pre>"; print_r($finalDetails); die;
    // if (empty($finalDetails)) {
        $orderDetails = OrderDetail::with('product')
            ->where('order_id', $orderId)
            ->get();

        // $finalDetails = [];
        $existingPartNos = [];

        foreach ($finalDetails as $row) {
            if (!empty($row->part_number)) {
                $existingPartNos[$row->part_number] = true;   // use array as a set
            }
        }
        // echo "<pre>"; print_r($existingPartNos); die;
        foreach ($orderDetails as $od) {
            $product  = $od->product;
            $partNo   = $product ? $product->part_no : null;
            $prodName = $product ? $product->name : '-';

            // If no part number, skip
            if (empty($partNo)) {
                continue;
            }

            // If this part number already exists in $finalDetails, skip
            if (isset($existingPartNos[$partNo])) {
                continue;
            }

            $qty      = (int) $od->quantity;
            $approved = (int) ($od->approved_quantity ?? 0);
            $rate     = $od->approved_rate ?? $od->price;
            if($od->regret_qty != NULL){
                $status = "Un Approved";
            }else{
                $status = "Pending";
            }

            $row = (object) [
                'product_name'           => $prodName,
                'part_number'            => $partNo,
                'quantity'               => $qty,
                'approved_quantity'      => $approved,
                'billed_qty'             => null,
                'rate'                   => $rate,
                'price'                  => null,          // no invoice yet
                'invoice_no'             => 'N/A',
                'invoice_date'           => '',
                'dispatch_id'            => 'N/A',         // ya yahan warehouse laga sakte ho
                'status'                 => $status,     // initial status
                'cancelled_qty'          => 0,
                'total_approved_qty'     => 0,
                'total_not_approved_qty' => 0,
            ];

            $finalDetails[] = $row;

            // Mark this part_no as now present, so duplicate order_details won't add again
            $existingPartNos[$partNo] = true;
        }
        // echo "<pre>"; print_r($finalDetails); die;
    // }
    
    // From here down, treat $finalDetails the same way as before
    $details = collect($finalDetails);

    // 4) Totals per part number
    $approvedQtyByPart = $details
        ->groupBy('part_number')
        ->map(function ($items) {
            return $items->sum('approved_quantity');
        });

    $notApprovedQtyByPart = $details
        ->groupBy('part_number')
        ->map(function ($items) {
            return $items->sum(function ($item) {
                $ordered  = (int) $item->quantity;
                $approved = (int) $item->approved_quantity;
                return max($ordered - $approved, 0);
            });
        });

    foreach ($details as $item) {
        if (empty($item->part_number)) {
            $item->total_approved_qty     = 0;
            $item->total_not_approved_qty = 0;
            continue;
        }

        $item->total_approved_qty = $approvedQtyByPart->get(
            $item->part_number,
            (int) $item->approved_quantity
        );

        $item->total_not_approved_qty = $notApprovedQtyByPart->get(
            $item->part_number,
            max((int) $item->quantity - (int) $item->approved_quantity, 0)
        );
    }

    // 5) Group for UI + totals/status
    $groupedDetails = $details->groupBy('part_number');
    $totalInvoicedAmount = $details->where('status', 'Completed')->sum('price');
    $orderStatus = $this->computeOverallStatus($details->all());
    // echo "<pre>"; print_r($groupedDetails); die;
    return view('frontend.user.order_details_customer', compact(
        'order',
        'groupedDetails',
        'totalInvoicedAmount',
        'orderStatus'
    ));
}

// public function purchase_history_details($id)
// {
//     $order = Order::findOrFail(decrypt($id));

//     // 1) Load graph
//     list($subOrders, $challans, $challanDetails, $invoiceDetails, $invoices, $btrSubOrders, $btrChallans, $btrChallanDetails)
//         = $this->loadOrderGraph($order);

//     // 2) Build indexes (fast lookups)
//     $idx = $this->buildIndexes($subOrders, $challans, $challanDetails, $invoiceDetails, $btrSubOrders, $btrChallans, $btrChallanDetails);

//     // 3) Build display rows using modular status resolvers
//     $finalDetails = array();

//     foreach ($subOrders as $subOrder) {
//         $warehouseName = isset($subOrder->order_warehouse) && isset($subOrder->order_warehouse->name)
//             ? $subOrder->order_warehouse->name
//             : 'N/A';

//         $filteredChallanIds = isset($idx['challanIdsBySubOrder'][$subOrder->id])
//             ? $idx['challanIdsBySubOrder'][$subOrder->id]
//             : collect();

//         foreach ($subOrder->sub_order_details as $detail) {
//             $row = $this->resolveLineStatus(
//                 $subOrder,
//                 $detail,
//                 $filteredChallanIds,
//                 $idx,
//                 $invoices,
//                 $btrChallans,
//                 $challans,
//                 $warehouseName
//             );

//             $finalDetails[] = (object) $row;
//         }
//     }
    
    
//     // 4) Totals per part number
//     $approvedQtyByPart = collect($finalDetails)->groupBy('part_number')->map(function ($items) {
//         return $items->sum('approved_quantity');
//     });

//     // Total NOT approved (ordered - approved) per part number
//     $notApprovedQtyByPart = collect($finalDetails)->groupBy('part_number')->map(function ($items) {
//         return $items->sum(function ($item) {
//             $ordered  = (int) $item->quantity;
//             $approved = (int) $item->approved_quantity;

//             return max($ordered - $approved, 0); // avoid negative
//         });
//     });

//     foreach ($finalDetails as $item) {
//         $item->total_approved_qty = isset($approvedQtyByPart[$item->part_number])
//             ? $approvedQtyByPart[$item->part_number]
//             : $item->approved_quantity;
        
//         $item->total_not_approved_qty = isset($notApprovedQtyByPart[$item->part_number])
//             ? $notApprovedQtyByPart[$item->part_number]
//             : max((int)$item->quantity - (int)$item->approved_quantity, 0);
//     }

    

//     // 5) Group for UI + totals/status
//     $groupedDetails = collect($finalDetails)->groupBy('part_number');
//     if($groupedDetails->isEmpty()){
//         $getOrderDetails = OrderDetail::with('product')->where('order_id',decrypt($id))->get();
//         foreach($getOrderDetails as $orderDetails){
            
//         }
//         echo "<pre>"; print_r($getOrderDetails); die;
//     }
//     $totalInvoicedAmount = collect($finalDetails)->where('status', 'Completed')->sum('price');
//     $orderStatus = $this->computeOverallStatus($finalDetails);
//     echo "<pre>"; print_r($groupedDetails); die;
//     return view('frontend.user.order_details_customer', compact(
//         'order',
//         'groupedDetails',
//         'totalInvoicedAmount',
//         'orderStatus'
//     ));
// }

/* ========================= HELPERS ========================= */

private function loadOrderGraph($order)
{
    $subOrders = SubOrder::where('order_id', $order->id)
        ->where('type', 'sub_order')
        ->with(array(
            'sub_order_details.product_data',
            'sub_order_details.btrSubOrder', // detail of type 'btr' for the same order+product
            'order_warehouse',
        ))->get();

    $challans = Challan::whereIn('sub_order_id', $subOrders->pluck('id'))->get();

    $challanDetails = $challans->isEmpty()
        ? collect()
        : ChallanDetail::whereIn('challan_id', $challans->pluck('id'))->get();

    $invoiceDetails = $challans->isEmpty()
        ? collect()
        : InvoiceOrderDetail::whereIn('challan_id', $challans->pluck('id'))->get();

    $invoices = $invoiceDetails->isEmpty()
        ? collect()
        : InvoiceOrder::whereIn('id', $invoiceDetails->pluck('invoice_order_id')->unique())->get();

    // CHANGED: eager-load origin warehouse for BTR sub-orders
    $btrSubOrders = SubOrder::where('order_id', $order->id)
        ->where('type', 'btr')
        ->with(array('order_warehouse'))
        ->get();

    $btrChallans = $btrSubOrders->isEmpty()
        ? collect()
        : Challan::whereIn('sub_order_id', $btrSubOrders->pluck('id'))->get();

    $btrChallanDetails = $btrChallans->isEmpty()
        ? collect()
        : ChallanDetail::whereIn('challan_id', $btrChallans->pluck('id'))->get();

    return array($subOrders, $challans, $challanDetails, $invoiceDetails, $invoices, $btrSubOrders, $btrChallans, $btrChallanDetails);
}

private function buildIndexes($subOrders, $challans, $challanDetails, $invoiceDetails, $btrSubOrders, $btrChallans, $btrChallanDetails)
{
    // Regular challan IDs per sub order
    $challanIdsBySubOrder = $challans->groupBy('sub_order_id')->map(function ($rows) {
        return $rows->pluck('id');
    });

    // (challan_id|part_no) => invoice detail
    $invoiceByChallanPart = array();
    foreach ($invoiceDetails as $inv) {
        $key = ((isset($inv->challan_id) ? $inv->challan_id : '0') . '|' . (isset($inv->part_no) ? $inv->part_no : ''));
        $invoiceByChallanPart[$key] = $inv;
    }

    // Regular (sub_order_id|product_id) => challan detail
    $regChallanDetailBySubAndProduct = array();
    foreach ($challanDetails as $cd) {
        $key = ((isset($cd->sub_order_id) ? $cd->sub_order_id : '0') . '|' . (isset($cd->product_id) ? $cd->product_id : '0'));
        $regChallanDetailBySubAndProduct[$key] = $cd;
    }

    // BTR (sub_order_id|product_id) => challan detail
    $btrChallanDetailBySubAndProduct = array();
    foreach ($btrChallanDetails as $cd) {
        $key = ((isset($cd->sub_order_id) ? $cd->sub_order_id : '0') . '|' . (isset($cd->product_id) ? $cd->product_id : '0'));
        $btrChallanDetailBySubAndProduct[$key] = $cd;
    }

    // NEW: sub_order_id -> warehouse name (for both regular & BTR sub-orders)
    $warehouseNameBySubOrderId = array();
    foreach ($subOrders as $so) {
        $warehouseNameBySubOrderId[$so->id] =
            (isset($so->order_warehouse) && isset($so->order_warehouse->name))
                ? $so->order_warehouse->name
                : 'N/A';
    }
    foreach ($btrSubOrders as $so) {
        $warehouseNameBySubOrderId[$so->id] =
            (isset($so->order_warehouse) && isset($so->order_warehouse->name))
                ? $so->order_warehouse->name
                : 'N/A';
    }

    // NEW: parent main sub_order_id -> btr sub_order_id
    $btrSubOrderIdByParent = array();
    foreach ($btrSubOrders as $so) {
        if (isset($so->sub_order_id)) {
            $btrSubOrderIdByParent[$so->sub_order_id] = $so->id;
        }
    }

    return array(
        'challanIdsBySubOrder'            => $challanIdsBySubOrder,             // Collection<int, Collection<int>>
        'invoiceByChallanPart'            => $invoiceByChallanPart,             // array
        'regChallanDetailBySubAndProduct' => $regChallanDetailBySubAndProduct,  // array
        'btrChallanDetailBySubAndProduct' => $btrChallanDetailBySubAndProduct,  // array
        // NEW:
        'warehouseNameBySubOrderId'       => $warehouseNameBySubOrderId,        // array
        'btrSubOrderIdByParent'           => $btrSubOrderIdByParent,            // array
    );
}

/**
 * Decide final display row for one line by composing individual status resolvers.
 */
private function resolveLineStatus($subOrder, $detail, $filteredChallanIds, $idx, $invoices, $btrChallans, $challans, $warehouseName)
{
    $partNo    = isset($detail->product_data) ? $detail->product_data->part_no : null;
    $productId = $detail->product_id;

    // Base row (will be merged with whichever status resolver matches)
    $row = array(
        'product_name'      => isset($detail->product_data) ? ($detail->product_data->name ?: '-') : '-',
        'part_number'       => $partNo,
        'quantity'          => (int) $detail->quantity,
        'approved_quantity' => (int) $detail->approved_quantity,
        'billed_qty'        => null,
        'rate'              => $detail->approved_rate,
        'price'             => null,
        'invoice_no'        => 'N/A',
        'invoice_date'      => '',
        'dispatch_id'       => $warehouseName, // source warehouse (regular sub-order)
        'status'            => '',
        'cancelled_qty'     => 0,              // for partial pre-close display (filled by fallback if needed)
    );

    // 1) BTR gate (updated): unblock when arrival inferred via dispatch/invoice/BTR child flag
    $btrResult = $this->resolveBtrStatus(
        $detail,                // main sub_order_detail (type=sub_order)
        $subOrder->id,          // main sub_order id
        $productId,             // product id for dispatch lookup
        $partNo,                // part no for invoice lookup
        $filteredChallanIds,    // challan ids for this main sub_order
        $idx,                   // indexes map
        $invoices,              // invoice headers collection
        $btrChallans            // BTR challans collection
    );

    if ($btrResult['blocked']) {
        return array_merge($row, $btrResult['presentation']);
    }

    // 2) Completed? (invoice present for this part on any challan of this main sub-order)
    $invoiceResult = $this->resolveInvoiceStatus($partNo, $filteredChallanIds, $idx, $invoices);
    if ($invoiceResult['matched']) {
        // prefer invoiced rate if provided
        if (isset($invoiceResult['presentation']['rate']) && $invoiceResult['presentation']['rate'] !== null) {
            $row['rate'] = $invoiceResult['presentation']['rate'];
        }
        return array_merge($row, $invoiceResult['presentation']);
    }

    // 3) Dispatched? (regular challan present for main sub_order + product)
    $dispatchResult = $this->resolveDispatchStatus($subOrder->id, $productId, $idx, $challans);
    if ($dispatchResult['matched']) {
        return array_merge($row, $dispatchResult['presentation']);
    }

    // 4) Fallback (Pending / Cancelled / Material unavailable) with partial pre-close
    $fallback = $this->resolveFallbackStatus($detail);
    return array_merge($row, $fallback);
}

/* ---------- individual resolvers ---------- */

/**
 * BTR gate:
 * - Block if MAIN detail.in_transit > 0
 * - If related BTR detail challan_quantity > 0 => "BTR In Transit" with that qty
 * - Else => "Awaiting BTR Stock"
 * - When MAIN detail.in_transit == 0 => not blocked (normal flow resumes)
 */
private function resolveBtrStatus($detail, $subOrderId, $productId, $partNo, $filteredChallanIds, $idx, $invoices, $btrChallans)
{
    $blocked = false;
    $presentation = array(
        'status'       => '',
        'billed_qty'   => null,
        'price'        => null,
        'invoice_no'   => 'N/A',
        'invoice_date' => '',
    );

    // If main line not flagged as in_transit, do not block
    $mainInTransit = isset($detail->in_transit) ? (int) $detail->in_transit : 0;
    if ($mainInTransit <= 0) {
        return array('blocked' => false, 'presentation' => $presentation);
    }

    // ---------- ARRIVAL SIGNALS (any one unblocks) ----------
    $arrivalViaDispatch = false;
    $arrivalViaInvoice  = false;
    $arrivalViaBtrFlag  = false;

    // A) Regular challan on MAIN sub order for this product => BTR has arrived at source
    $regKey = $subOrderId . '|' . $productId;
    if (isset($idx['regChallanDetailBySubAndProduct'][$regKey])) {
        $arrivalViaDispatch = true;
    }

    // B) Invoice present for this part on any challan of MAIN sub order
    if ($partNo) {
        foreach ($filteredChallanIds as $cid) {
            $k = $cid . '|' . $partNo;
            if (isset($idx['invoiceByChallanPart'][$k])) {
                $arrivalViaInvoice = true;
                break;
            }
        }
    }

    // C) BTR child detail explicitly says not in transit anymore
    $btrSubDetail  = $detail->btrSubOrder; // SubOrderDetail of type 'btr' for same order+product
    if ($btrSubDetail && isset($btrSubDetail->in_transit) && ((int) $btrSubDetail->in_transit) === 0) {
        $arrivalViaBtrFlag = true;
    }

    // If any arrival signal true → UNBLOCK and let normal flow (invoice/dispatch/pending) take over
    if ($arrivalViaDispatch || $arrivalViaInvoice || $arrivalViaBtrFlag) {
        return array('blocked' => false, 'presentation' => $presentation);
    }

    // ---------- Still BTR-blocked: show Awaiting / In Transit with origin ----------
    $blocked = true;

    // Resolve origin warehouse name (from BTR sub_order_id)
    $btrSubOrderId = ($btrSubDetail && isset($btrSubDetail->sub_order_id)) ? $btrSubDetail->sub_order_id : null;
    $originName = null;
    if ($btrSubOrderId && isset($idx['warehouseNameBySubOrderId'][$btrSubOrderId])) {
        $originName = $idx['warehouseNameBySubOrderId'][$btrSubOrderId];
    } else {
        // fallback via parent→BTR mapping
        $parentId = isset($detail->sub_order_id) ? $detail->sub_order_id : null;
        if ($parentId && isset($idx['btrSubOrderIdByParent'][$parentId])) {
            $possibleBtrId = $idx['btrSubOrderIdByParent'][$parentId];
            if (isset($idx['warehouseNameBySubOrderId'][$possibleBtrId])) {
                $originName = $idx['warehouseNameBySubOrderId'][$possibleBtrId];
            }
            if (!$btrSubOrderId) {
                $btrSubOrderId = $possibleBtrId;
            }
        }
    }

    // If you store BTR challaned qty on the child detail, prefer that for "in transit" quantity
    // NOTE: change to 'challan_qty' if that's your column name
    $btrChallanQty = ($btrSubDetail && isset($btrSubDetail->challan_quantity)) ? (int) $btrSubDetail->challan_quantity : 0;

    if ($btrChallanQty > 0) {
        $presentation['status']     = 'BTR In Transit';
        $presentation['billed_qty'] = $btrChallanQty ?: null;

        if ($btrSubOrderId) {
            $key   = $btrSubOrderId . '|' . $productId;
            $btrCD = isset($idx['btrChallanDetailBySubAndProduct'][$key]) ? $idx['btrChallanDetailBySubAndProduct'][$key] : null;

            if ($btrCD) {
                $presentation['price'] = isset($btrCD->final_amount) ? $btrCD->final_amount : null;
                $btrChallan = $btrChallans->firstWhere('id', $btrCD->challan_id);
                $presentation['invoice_no'] = $btrChallan ? ($btrChallan->challan_no ?: 'N/A') : 'N/A';
            }
        }
        return array('blocked' => $blocked, 'presentation' => $presentation);
    }

    // Awaiting BTR (no challan qty yet) — include origin if known
    if ($originName && $originName !== '') {
        $presentation['status'] = 'Awaiting BTR Stock (from ' . $originName . ')';
    } else {
        $presentation['status'] = 'Awaiting BTR Stock';
    }

    return array('blocked' => $blocked, 'presentation' => $presentation);
}

private function resolveInvoiceStatus($partNo, $filteredChallanIds, $idx, $invoices)
{
    $matched = false;
    $presentation = array(
        'status'       => '',
        'billed_qty'   => null,
        'price'        => null,
        'invoice_no'   => 'N/A',
        'invoice_date' => '',
        'rate'         => null,
    );

    if (!$partNo) {
        return array('matched' => $matched, 'presentation' => $presentation);
    }

    $matchedInvoice = null;
    foreach ($filteredChallanIds as $cid) {
        $key = $cid . '|' . $partNo;
        if (isset($idx['invoiceByChallanPart'][$key])) {
            $matchedInvoice = $idx['invoiceByChallanPart'][$key];
            break;
        }
    }

    if ($matchedInvoice) {
        $invoice = $invoices->firstWhere('id', $matchedInvoice->invoice_order_id);
        $matched = true;
        $presentation['status']       = 'Completed';
        $presentation['billed_qty']   = isset($matchedInvoice->billed_qty) ? (int) $matchedInvoice->billed_qty : null;
        $presentation['price']        = isset($matchedInvoice->billed_amt) ? $matchedInvoice->billed_amt : null;
        $presentation['invoice_no']   = isset($matchedInvoice->invoice_no) && $matchedInvoice->invoice_no
            ? $matchedInvoice->invoice_no
            : ($invoice ? ($invoice->invoice_no ?: 'N/A') : 'N/A');
        $presentation['invoice_date'] = $invoice ? optional($invoice->created_at)->format('d-m-Y') : '';
        $presentation['rate']         = isset($matchedInvoice->rate) ? $matchedInvoice->rate : null;
    }

    return array('matched' => $matched, 'presentation' => $presentation);
}

private function resolveDispatchStatus($subOrderId, $productId, $idx, $challans)
{
    $matched = false;
    $presentation = array(
        'status'       => '',
        'billed_qty'   => null,
        'price'        => null,
        'invoice_no'   => 'N/A',
        'invoice_date' => '',
    );

    $key = $subOrderId . '|' . $productId;
    $cd = isset($idx['regChallanDetailBySubAndProduct'][$key]) ? $idx['regChallanDetailBySubAndProduct'][$key] : null;

    if ($cd) {
        $matched = true;
        $presentation['status']     = 'Material in transit';
        $presentation['billed_qty'] = isset($cd->quantity) ? (int) $cd->quantity : null;
        $presentation['price']      = isset($cd->final_amount) ? $cd->final_amount : null;

        $matchedChallan = $challans->firstWhere('id', $cd->challan_id);
        $presentation['invoice_no'] = $matchedChallan ? ($matchedChallan->challan_no ?: 'N/A') : 'N/A';
    }

    return array('matched' => $matched, 'presentation' => $presentation);
}

/**
 * Fallback with partial pre-closed handling:
 * - cancelled_qty = pre_closed when pre_closed_status==1
 * - If remaining approved qty > 0 => Pending For Dispatch
 * - Else => Cancelled
 */
private function resolveFallbackStatus($detail)
{
    $cancelledQty = ((int) $detail->pre_closed_status === 1) ? (int) $detail->pre_closed : 0;
    $approvedQty  = (int) $detail->approved_quantity;
    $remaining    = max(0, $approvedQty - $cancelledQty);

    if ($remaining > 0) {
        return array('status' => 'Pending For Dispatch', 'cancelled_qty' => $cancelledQty);
    }

    if ($approvedQty > 0 && $remaining == 0 && $cancelledQty > 0) {
        return array('status' => 'Cancelled', 'cancelled_qty' => $cancelledQty);
    }

    return array('status' => 'Material unavailable', 'cancelled_qty' => $cancelledQty);
}

/* ---------- status aggregation ---------- */

private function computeOverallStatus($finalDetails)
{
    $statuses = collect($finalDetails)->pluck('status');

    $hasCompleted   = $statuses->contains('Completed');
    $hasBtrTransit  = $statuses->contains('BTR In Transit');
    $hasAwaitingBtr = $statuses->contains(function ($s) {
        return strpos($s, 'Awaiting BTR Stock') === 0;
    });
    $hasInTransit   = $statuses->contains('Material in transit');
    $hasPending     = $statuses->contains('Pending For Dispatch');
    $allCancelled   = $statuses->every(function ($s) { return $s === 'Cancelled'; });

    if ($hasCompleted)  return 'Completed';
    if ($hasBtrTransit) return 'BTR In Transit';
    if ($hasAwaitingBtr) return 'Awaiting BTR';
    if ($hasInTransit)  return 'Dispatched';
    if ($hasPending)    return 'Approved';
    if ($allCancelled)  return 'Material unavailable';
    return 'Material unavailable';
}

private function determineStatus($detail, $dispatch, $bill)
{
    if ($dispatch && $dispatch->manually_cancel_item) {
        return 'Canceled';
    } 
    // Check if the bill has a manually canceled item
    elseif ($bill && $bill->manually_cancel_item) {
        return 'Canceled';
    } 
    elseif ($bill) {
        return 'Completed';
    } elseif ($dispatch) {
        return 'Material in transit';
    } elseif ($detail->approved_quantity > 0) {
        return 'Pending for Dispatch';
    } else {
        return 'Material unavailable';
    }
}


// Helper function to determine the status
// private function determineStatus($detail, $dispatch, $bill)
// {
//     if ($dispatch && $dispatch->manually_cancel_item) {
//         return 'Canceled';
//     } 
//     elseif ($bill) {
//         return 'Completed';
//     } elseif ($dispatch) {
//         return 'Material in transit';
//     } elseif ($detail->approved_quantity > 0) {
//         return 'Pending for Dispatch';
//     } else {
//         return 'Material unavailable';
//     }
// }







/**
 * Fix JSON dynamically by blanking problematic `item_name` but retaining other fields.
 *
 * @param string $jsonString
 * @return string|null
 */
private function fixJson($jsonString)
{
    $jsonString = preg_replace('/[\r\n\t]+/', ' ', $jsonString);
    $decodedArray = json_decode($jsonString, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->processAndFixJson($jsonString);
    }

    foreach ($decodedArray as &$object) {
        if (isset($object['item_name'])) {
            $testJson = json_encode(['item_name' => $object['item_name']]);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $object['item_name'] = ""; // Blank invalid `item_name`
            }
        }
    }

    return json_encode($decodedArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Process and fix invalid JSON string by handling each object separately.
 *
 * @param string $jsonString
 * @return string|null
 */
private function processAndFixJson($jsonString)
{
    preg_match_all('/\{.*?\}/', $jsonString, $matches);
    $fixedArray = [];

    foreach ($matches[0] as $index => $jsonPart) {
        $decoded = json_decode($jsonPart, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $decoded = $this->createValidObject($jsonPart);
        }

        $fixedArray[] = $decoded ?: new \stdClass();
    }

    return json_encode($fixedArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Create a valid object from an invalid JSON string.
 *
 * @param string $jsonPart
 * @return array
 */
private function createValidObject($jsonPart)
{
    preg_match_all('/"([^"]+)"\s*:\s*"([^"]*?)"/', $jsonPart, $matches);
    $validObject = [];

    foreach ($matches[1] as $index => $key) {
        $value = $matches[2][$index];

        if ($key === 'item_name') {
            $testJson = json_encode(['item_name' => $value]);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $value = ""; // Blank problematic `item_name`
            }
        }

        $validObject[$key] = $value;
    }

    return $validObject;
}











    public function download(Request $request)
    {
        $product = Product::findOrFail(decrypt($request->id));
        $downloadable = false;
        foreach (Auth::user()->orders as $key => $order) {
            foreach ($order->orderDetails as $key => $orderDetail) {
                if ($orderDetail->product_id == $product->id && $orderDetail->payment_status == 'paid') {
                    $downloadable = true;
                    break;
                }
            }
        }
        if ($downloadable) {
            $upload = Upload::findOrFail($product->file_name);
            if (env('FILESYSTEM_DRIVER') == "s3") {
                return \Storage::disk('s3')->download($upload->file_name, $upload->file_original_name . "." . $upload->extension);
            } else {
                if (file_exists(base_path('public/' . $upload->file_name))) {
                    return response()->download(base_path('public/' . $upload->file_name));
                }
            }
        } else {
            flash(translate('You cannot download this product at this product.'))->success();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function order_cancel($id)
    {
        $order = Order::where('id', $id)->where('user_id', auth()->user()->id)->first();
        if($order && ($order->delivery_status == 'pending' && $order->payment_status == 'unpaid')) {
            $order->delivery_status = 'cancelled';
            $order->save();

            foreach ($order->orderDetails as $key => $orderDetail) {
                $orderDetail->delivery_status = 'cancelled';
                $orderDetail->save();
                product_restock($orderDetail);
            }

            flash(translate('Order has been canceled successfully'))->success();
        } else {
            flash(translate('Something went wrong'))->error();
        }

        return back();
    }

    // Statement

    public function statement()
    {
        try{
            $userData = User::where('id', Auth::user()->id)->first();
            $userAddressData = Address::where('user_id', Auth::user()->id)->groupBy('gstin')->orderBy('acc_code','ASC')->get();
            $dueAmount = '0.00';
            $overdueAmount = '0.00';

            $statement_data = array();
            $userData = User::where('id', Auth::user()->id)->first();
            $userAddress = Address::where('user_id', Auth::user()->id)->first();
            if ($userAddress) {
                $gstin = $userAddress->gstin;
                $userAddressDatas = Address::where('user_id', $userData->id)->where('gstin', $gstin)->get();
            } else {
                $userAddressDatas = collect(); // Return empty collection if no address found
            }
            foreach ($userAddressDatas as $uValue) {
                $decodedData = json_decode($uValue->statement_data, true);        
                if (is_array($decodedData)) {
                    // Remove "closing C/f......" entries
                    $filteredData = array_filter($decodedData, function ($item) {
                        return !isset($item['ledgername']) || stripos($item['ledgername'], 'closing C/f...') === false;
                    });        
                    $statement_data[$uValue->id] = $filteredData;
                }
            }

            $mergedData = [];
            foreach ($statement_data as $data) {
                $mergedData = array_merge($mergedData, $data);
            }
            usort($mergedData, function ($a, $b) {
                return strtotime($a['trn_date']) - strtotime($b['trn_date']);
            });
            $statement_data = array_values($mergedData);
            $balance = 0;
            foreach ($statement_data as $gKey=>$gValue) {
                if($gValue['ledgername'] == 'Opening b/f...'){
                    $balance = $gValue['dramount'] != 0.00 ? $gValue['dramount'] : -$gValue['cramount'];
                }else{
                    $balance += (float)$gValue['dramount'] - (float)$gValue['cramount'];
                }
                $statement_data[$gKey]['running_balance'] = $balance;
            }
            
            if(isset($balance)){
                $tempArray = array();
                $tempArray['trn_no'] = "";
                $tempArray['trn_date'] = date('Y-m-d');
                $tempArray['vouchertypebasename'] = "";
                $tempArray['ledgername'] = "closing C/f...";
                // $amount = explode('₹',$value[5]);
                $tempArray['ledgerid'] = "";
                if($balance <= 0){
                    $tempArray['cramount'] = (float)str_replace(',', '',$balance);
                    $tempArray['dramount'] = (float)0.00;
                }else{
                    $tempArray['dramount'] = (float)str_replace(',', '',$balance);
                    $tempArray['cramount'] = (float)0.00;
                }
                $tempArray['narration'] = "";
                $statement_data[] = $tempArray;
            }

            $overdueAmount = "0";
            $openingBalance="0";
            $openDrOrCr="";
            $closingBalance="0";
            $closeDrOrCr="";
            $dueAmount="0";
            $overdueDateFrom="";
            $overdueAmount="0";
            $overdueDrOrCr="";
            $getData=array();
            $overDueMark = array();
            $getOverdueData = $statement_data;
            if($statement_data !== NULL){
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
                                        'overdue_staus' => 'Partial Overdue'
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

                // Overdue Calculation End
            }        

            if ($statement_data !== NULL) {
                $getData = $statement_data;            
                $openingBalance = "0";
                $closingBalance = "0";
                $openDrOrCr = "";
                $drBalance = "0";
                $crBalance = "0";
                $dueAmount = "0";
                $overDueMarkTrnNos = array_column($overDueMark, 'trn_no'); 
                $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
                $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
                // echo "<pre>"; print_r($overDueMark); die();
                foreach($getData as $gKey=>$gValue){
                    //echo "<pre>";print_r($gValue);
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
                            // $closeDrOrCr = "Cr";
                        }else{
                            $closingBalance = $gValue['cramount'];
                            // $dueAmount = $gValue['cramount'];
                            // $closeDrOrCr = "Cr";
                            // $closeDrOrCr = "Dr";
                        }
                    }
                    if(count($overDueMark) > 0) {
                        $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                        if ($key !== false) {
                            $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                            $getData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                        }
                    }else{
                        if(isset($getData[$gKey]['overdue_status'])){ $getData[$gKey]['overdue_status']=""; }
                        if(isset($getData[$gKey]['overdue_by_day'])){ $getData[$gKey]['overdue_by_day'] = ""; }
                    }
                    if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                        $drBalance = $drBalance + $gValue['dramount'];
                        $dueAmount = $dueAmount + $gValue['dramount'];
                    } 
                    if($gValue['cramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                        $crBalance = $crBalance + $gValue['cramount'];
                        $dueAmount = $dueAmount - $gValue['cramount'];
                    }
                }
                $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
            }

            return view('frontend.user.statement', compact('userData','userAddressData','dueAmount','overdueAmount'));
        } catch (\Exception $e) {
            \Log::error('Error in sendPayNowLink:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

    }

    public function __statement_details($party_code = "", $form_date = "", $to_date = "")
    {
        $party_code = decrypt($party_code);        
        $userAddressData = Address::where('acc_code', $party_code)->first();
        $userData = User::where('id', $userAddressData->user_id)->first();
        // echo "<pre>"; print_r(json_decode($userData->statement_data));die;
        // Overdue Calculation Start
        $overdueAmount = "0";
        $openingBalance="0";
        $openDrOrCr="";
        $closingBalance="0";
        $closeDrOrCr="";
        $dueAmount="0";
        $overdueDateFrom="";
        $overdueAmount="0";
        $overdueDrOrCr="";
        $getData=array();

        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        if ($currentMonth >= 4) {
            $fy_form_date = date('Y-04-01'); // Start of financial year
            $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        } else {
            $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
            $fy_to_date = date('Y-03-31'); // Current year March
        }
        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];        
        $body = [
            'party_code' => $party_code,
            'from_date' => $fy_form_date,
            'to_date' =>  $fy_to_date,
        ];
        \Log::info('Sending request to API For Overdue', [
            'url' => 'https://saleszing.co.in/itaapi/getclientstatement.php',
            'headers' => $headers,
            'body' => $body
        ]);        
        $overdue_response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);        
        \Log::info('Received response from API For Overdue', [
            'status' => $overdue_response->status(),
            'body' => $overdue_response->body()
        ]);
        $getOverdueData = $overdue_response->json();
        $getOverdueData = $getOverdueData['data'];
        if(!empty($getOverdueData)){
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
                        }elseif(strtotime($ovValue['trn_date']) < strtotime($overdueDateFrom) AND $temOverDueBalance > 0 AND $ovValue['dramount'] != '0.00'){
                            $temOverDueBalance -= $ovValue['dramount'];
                            $date1 = $ovValue['trn_date'];
                            $date2 = $overdueDateFrom;

                            $diff = abs(strtotime($date2) - strtotime($date1));

                            // $years = floor($diff / (365*60*60*24));
                            // $months = floor(($diff - $years * 365*60*60*24) / (30*60*60*24));
                            // $days = floor(($diff - $years * 365*60*60*24 - $months*30*60*60*24) / (60*60*24));

                            // // Initialize an empty array to store non-zero date parts
                            // $dateParts = [];

                            // if ($years > 0) {
                            //     $dateParts[] = "$years years";
                            // }
                            // if ($months > 0) {
                            //     $dateParts[] = "$months months";
                            // }
                            // if ($days > 0) {
                            //     $dateParts[] = "$days days";
                            // }
                            // // Combine the date parts into a string
                            // $dateDifference = implode(', ', $dateParts);
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
                                    'overdue_staus' => 'Partial Overdue'
                                ];
                            }
                            
                            
                        }
                    }
                }
            }
            if($overdueAmount <= 0){
                $overdueDrOrCr = 'Dr';
                $overdueAmount = 0;
            }else{
                $overdueDrOrCr = 'Cr';
            }
        }      

        // Overdue Calculation End
        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        if ($currentMonth >= 4) {
            $form_date = date('Y-04-01'); // Start of financial year
            $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        } else {
            $form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
            $to_date = date('Y-03-31'); // Current year March
        }
        if ($to_date > $currentDate) {
            $to_date = $currentDate;
        }
        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];        
        $body = [
            'party_code' => $party_code,
            'from_date' => $form_date,
            'to_date' =>  $to_date,
        ];
        \Log::info('Sending request to API', [
            'url' => 'https://saleszing.co.in/itaapi/getclientstatement.php',
            'headers' => $headers,
            'body' => $body
        ]);        
        $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);        
        \Log::info('Received response from API', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        // echo "<pre>"; print_r($response->json());die;

        if ($response->successful()) {
            $getData = $response->json();
            $getData = $getData['data'];
            if(!empty($getData)){
                $openingBalance = "0";
                $closingBalance = "0";
                $openDrOrCr = "";
                $drBalance = "0";
                $crBalance = "0";
                $dueAmount = "0";
                $overDueMarkTrnNos = array_column($overDueMark, 'trn_no'); 
                $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
                $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
                // echo "<pre>"; print_r($overDueMarkTrnNos); die();
                foreach($getData as $gKey=>$gValue){
                    //echo "<pre>";print_r($gValue);
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
                            $dueAmount = $gValue['dramount'];
                            $closeDrOrCr = "Dr";
                        }else{
                            $closingBalance = $gValue['cramount'];
                            $closeDrOrCr = "Cr";
                            $dueAmount = $gValue['cramount'];
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
                    if($gValue['cramount'] != '0.00' AND $gValue['ledgername'] != 'closing C/f...') {
                        $crBalance = $crBalance + $gValue['cramount'];
                        $dueAmount = $dueAmount - $gValue['cramount'];
                    }
                }
                $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
            }            
            // echo "<pre>"; print_r($getData);die;
            // return view('frontend.user.statement_details', compact('party_code','getData','openingBalance','openDrOrCr','closingBalance','closeDrOrCr','dueAmount','overdueDateFrom','overdueAmount','overdueDrOrCr'));
        }
        return view('frontend.user.statement_details', compact('party_code','getData','openingBalance','openDrOrCr','closingBalance','closeDrOrCr','dueAmount','overdueDateFrom','overdueAmount','overdueDrOrCr'));
    }

    public function statement_details($party_code = "", $from_date = "", $to_date = "")
    {
        $party_code = decrypt($party_code);

        // Previous Logic
        // $userAddressData = Address::where('acc_code', $party_code)->first();
        // $userData = User::where('id', $userAddressData->user_id)->first();
        // $statement_data = json_decode($userAddressData->statement_data, true);

        // ----- New Logic Start -----
        $statement_data = array();
        $userAddress = Address::where('acc_code', $party_code)->first();
        $userData = User::where('id', $userAddress->user_id)->first();
        
        // if ($userAddress) {
        //     $gstin = $userAddress->gstin;
        //     $userAddressData = Address::where('user_id', $userData->id)->where('gstin', $gstin)->get();
        // } else {
        //     $userAddressData = collect(); // Return empty collection if no address found
        // }
        // // echo "<pre>"; print_r($userAddressData[0]->statement_data);die;
        // foreach ($userAddressData as $uValue) {
        //     $decodedData = json_decode($uValue->statement_data, true);        
        //     if (is_array($decodedData)) {
        //         // Remove "closing C/f......" entries
        //         $filteredData = array_filter($decodedData, function ($item) {
        //             return !isset($item['ledgername']) || stripos($item['ledgername'], 'closing C/f...') === false;
        //         });        
        //         $statement_data[$uValue->id] = $filteredData;
        //     }
        // }

        // $mergedData = [];
        // foreach ($statement_data as $data) {
        //     $mergedData = array_merge($mergedData, $data);
        // }
        // usort($mergedData, function ($a, $b) {
        //     return strtotime($a['trn_date']) - strtotime($b['trn_date']);
        // });
        // $statement_data = array_values($mergedData);
        // // echo "<pre>"; print_r($statement_data);die;
        // $balance = 0;
        // foreach ($statement_data as $gKey=>$gValue) {
        //     if($gValue['ledgername'] == 'Opening b/f...'){
        //         $balance = $gValue['dramount'] != 0.00 ? $gValue['dramount'] : -$gValue['cramount'];
        //     }else{
        //         $balance += (float)$gValue['dramount'] - (float)$gValue['cramount'];
        //     }
        //     // single_price(trim($balance,'-'));
        //     $statement_data[$gKey]['running_balance'] = $balance;
        //     // die;
        // }
        
        // if(isset($balance)){
        //     $tempArray = array();
        //     $tempArray['trn_no'] = "";
        //     $tempArray['trn_date'] = date('Y-m-d');
        //     $tempArray['vouchertypebasename'] = "";
        //     $tempArray['ledgername'] = "closing C/f...";
        //     // $amount = explode('₹',$value[5]);
        //     $tempArray['ledgerid'] = "";
        //     if($balance <= 0){
        //         $tempArray['cramount'] = (float)str_replace(',', '',$balance);
        //         $tempArray['dramount'] = (float)0.00;
        //     }else{
        //         $tempArray['dramount'] = (float)str_replace(',', '',$balance);
        //         $tempArray['cramount'] = (float)0.00;
        //     }
        //     $tempArray['narration'] = "";
        //     $statement_data[] = $tempArray;
        // }

        $statement_data = json_decode($userAddress->statement_data, true);
        // ----- New Logic End -----
        // echo "<pre>"; print_r($statement_data); die;

        $overdueAmount = "0";
        $openingBalance="0";
        $openDrOrCr="";
        $closingBalance="0";
        $closeDrOrCr="";
        $dueAmount="0";
        $overdueDateFrom="";
        $overdueDrOrCr="";
        $getData=array();
        $overDueMark = array();
        $getOverdueData = $statement_data;
        if($statement_data !== NULL){
            $closingBalanceResult = array_filter($getOverdueData, function ($entry) {
                return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
            });
            $closingEntry = reset($closingBalanceResult);
            $cloasingDrAmount = $closingEntry['dramount'];
            $cloasingCrAmount = $closingEntry['cramount'];          
            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
            if($cloasingDrAmount > 0){
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
                                    'overdue_staus' => 'Partial Overdue'
                                ];
                            }
                        }
                    }
                }
            }
            // echo $overdueAmount; die;
            if($overdueAmount <= 0){
                $overdueDrOrCr = 'Cr';
                $overdueAmount = 0;
            }else{
                $overdueDrOrCr = 'Dr';
            }

            // Overdue Calculation End
        }        

        if ($statement_data !== NULL) {
            $getData = $statement_data;            
            $openingBalance = "0";
            $closingBalance = "0";
            $openDrOrCr = "";
            $drBalance = "0";
            $crBalance = "0";
            $dueAmount = "0";
            $overDueMarkTrnNos = array_column($overDueMark, 'trn_no'); 
            $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
            $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
            // echo "<pre>"; print_r($overDueMark); die();
            foreach($getData as $gKey=>$gValue){
                //echo "<pre>";print_r($gValue);
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
                        // $closeDrOrCr = "Cr";
                    }else{
                        $closingBalance = $gValue['cramount'];
                        // $dueAmount = $gValue['cramount'];
                        // $closeDrOrCr = "Cr";
                        // $closeDrOrCr = "Dr";
                    }
                }
                if(count($overDueMark) > 0) {
                    $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                    if ($key !== false) {
                        $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                        $getData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                    }
                }else{
                    if(isset($getData[$gKey]['overdue_status'])){ $getData[$gKey]['overdue_status']=""; }
                    if(isset($getData[$gKey]['overdue_by_day'])){ $getData[$gKey]['overdue_by_day'] = ""; }
                }

                if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                    $drBalance = $drBalance + $gValue['dramount'];
                    $dueAmount = $dueAmount + $gValue['dramount'];
                } 
                if($gValue['cramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                    $crBalance = $crBalance + $gValue['cramount'];
                    $dueAmount = $dueAmount - $gValue['cramount'];
                }
            }
            $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
        }

        // echo "<pre>.$temOverDueBalance"; print_r($overDueMark); die;

        // Make API calls for payment URLs Edited by dipak start
        $overduePaymentUrl=0;
        $duePaymentUrl=0;
        $customPaymentUrl="";


        
        // if ($dueAmount > 0) {
        //     $duePaymentUrl = $this->generatePaymentUrl($party_code, 'due_amount');
        // }

        // if ($overdueAmount > 0) {
        //     $overduePaymentUrl = $this->generatePaymentUrl($party_code, 'overdue_amount');
        // }

        // $customPaymentUrl = $this->generatePaymentUrl($party_code, 'custom_amount');

        // $from_date = '2024-10-01';
        // $to_date = '2024-10-21';
        if($from_date != "" AND $to_date != ""){
            $opening_balance = 0.00;
            $filtered_transactions = [];
            $closing_balance = 0.00;

            // Step 1: Loop through all data
            foreach ($getData as $entry) {
                $entry_date = $entry['trn_date'];

                if ($entry_date < $from_date) {
                    // Calculate opening balance: add Dr, subtract Cr
                    $opening_balance += floatval($entry['dramount']) - floatval($entry['cramount']);
                } elseif ($entry_date >= $from_date && $entry_date <= $to_date) {
                    // Keep only transactions within date range
                    $filtered_transactions[] = $entry;
                }
            }

            // Step 2: Prepare opening balance entry
            $opening_entry = [
                'trn_no' => '',
                'trn_date' => $from_date,
                'vouchertypebasename' => '',
                'ledgername' => 'Opening b/f...',
                'ledgerid' => '',
                'dramount' => $opening_balance > 0 ? number_format($opening_balance, 2, '.', '') : "0.00",
                'cramount' => $opening_balance < 0 ? number_format(abs($opening_balance), 2, '.', '') : "0.00",
                'narration' => '',
                'running_balance' => number_format($opening_balance, 2, '.', ''),
                'overdue_status' => '',
                'overdue_by_day' => ''
            ];

            // Step 3: Calculate running balance for filtered items
            $running_balance = $opening_balance;
            foreach ($filtered_transactions as &$entry) {
                $running_balance += floatval($entry['dramount']) - floatval($entry['cramount']);
                $entry['running_balance'] = number_format($running_balance, 2, '.', '');
            }

            // Step 4: Prepare closing balance
            $closing_entry = [
                'trn_no' => '',
                'trn_date' => $to_date,
                'vouchertypebasename' => '',
                'ledgername' => 'closing C/f...',
                'ledgerid' => '',
                'dramount' => '',
                'cramount' => '',
                'narration' => '',
                'running_balance' => number_format($running_balance, 2, '.', ''),
                'overdue_status' => '',
                'overdue_by_day' => ''
            ];

            // Step 5: Combine all into final result
            $final_statement = [];
            $final_statement[] = $opening_entry;
            $final_statement = array_merge($final_statement, $filtered_transactions);
            $final_statement[] = $closing_entry;
            $getData = $final_statement;
        }
        

        // echo "<pre>"; print_r($getData); die;


        return view('frontend.user.statement_details', compact('party_code','getData','openingBalance','openDrOrCr','closingBalance','closeDrOrCr','dueAmount','overdueDateFrom','overdueAmount','overdueDrOrCr','duePaymentUrl','overduePaymentUrl','customPaymentUrl'));
    }

    public function rewards()
    {
        $partyCodeArray = Address::where('acc_code',"!=","")->where('user_id',Auth::user()->id)->pluck('acc_code');
        $getData = RewardPointsOfUser::whereIn('party_code', $partyCodeArray)->whereNull('cancel_reason')->get();
        return view('frontend.user.rewards_details', compact('getData'));
    }

    public function rewardsDownload()
    {
        // Fetch rewards data
        $getData = RewardPointsOfUser::where('party_code', Auth::user()->party_code)
            ->whereNull('cancel_reason')
            ->get();

        // Process rewards data to add narration
        foreach ($getData as $reward) {
            if ($reward->rewards_from === 'Logistic' && !empty($reward->invoice_no)) {
                $billData = DB::table('bills_data')->where('invoice_no', $reward->invoice_no)->first();
                $reward->narration = $billData ? $billData->invoice_amount : 'N/A';
            } elseif ($reward->rewards_from === 'manual') {
                $reward->narration = !empty($reward->notes) ? $reward->notes : '-';
            } else {
                $reward->narration = '-';
            }
        }

        // Exclude 'Total' and 'Closing Balance' rows from calculations
        $rewardRows = $getData->filter(function ($reward) {
            return !in_array($reward->rewards_from, ['Total', 'Closing Balance']);
        });

        // Calculate reward amount
        $rewardAmount = $rewardRows->sum('rewards');

        // Get the last valid row before totals for closing balance
        $lastRow = $rewardRows->last();
        $closing_balance = $lastRow ? $lastRow->rewards : 0;
        $last_dr_or_cr = $lastRow ? strtolower($lastRow->dr_or_cr) : null;

        // Adjust the closing balance based on Dr/Cr logic
        if ($last_dr_or_cr === 'dr') {
            $closing_balance = $rewardAmount;
        } else {
            $closing_balance = -$rewardAmount;
        }

        // User data
        $userData = Auth::user();
        $party_code = $userData->party_code;

        $pdfContentService = new PdfContentService();
        $pdfContentBlock   = $pdfContentService->buildBlockForType('reward_statement');

        // Generate PDF
        $pdf = PDF::loadView('backend.invoices.rewards_pdf', compact(
            'userData',
            'party_code',
            'getData',
            'rewardAmount', // Ensure this is passed
            'closing_balance',
            'last_dr_or_cr',
            'pdfContentBlock' // ✅ Blade me use hoga
        ));

        // Return the PDF for download
        return $pdf->download('rewards_statement.pdf');
    }

    public function getRewardPdfURL($party_code)
    {
        // Fetch rewards data
        $getData = RewardPointsOfUser::where('party_code', $party_code)
            ->whereNull('cancel_reason')
            ->get();

        // Process rewards data to add narration
        foreach ($getData as $reward) {
            if ($reward->rewards_from === 'Logistic' && !empty($reward->invoice_no)) {
                $billData = DB::table('bills_data')->where('invoice_no', $reward->invoice_no)->first();
                $reward->narration = $billData ? $billData->invoice_amount : 'N/A';
            } elseif ($reward->rewards_from === 'manual') {
                $reward->narration = !empty($reward->notes) ? $reward->notes : '-';
            } else {
                $reward->narration = '-';
            }
        }

        // Exclude 'Total' and 'Closing Balance' rows from calculations
        $rewardRows = $getData->filter(function ($reward) {
            return !in_array($reward->rewards_from, ['Total', 'Closing Balance']);
        });

        // Calculate reward amount
        $rewardAmount = $rewardRows->sum('rewards');

        // Get the last valid row before totals for closing balance
        $lastRow = $rewardRows->last();
        $closing_balance = $lastRow ? $lastRow->rewards : 0;
        $last_dr_or_cr = $lastRow ? strtolower($lastRow->dr_or_cr) : null;

        // Adjust the closing balance based on Dr/Cr logic
        if ($last_dr_or_cr === 'dr') {
            $closing_balance = $rewardAmount;
        } else {
            $closing_balance = -$rewardAmount;
        }

        // User data
        $userData = Auth::user();

        // File name and path
        $fileName = 'reward_statement_' . $party_code . '_' . time() . '.pdf';
        $filePath = public_path('reward_pdf/' . $fileName);
        $publicUrl = url('public/reward_pdf/' . $fileName);

        $pdfContentService = new PdfContentService();
        $pdfContentBlock   = $pdfContentService->buildBlockForType('reward_statement');

        // Generate and save the PDF
        PDF::loadView('backend.invoices.rewards_pdf', compact(
            'userData',
            'party_code',
            'getData',
            'rewardAmount',
            'closing_balance',
            'last_dr_or_cr',
            'pdfContentBlock' // ✅ Blade me use hoga
        ))->save($filePath);

        // Return the public URL
        return $publicUrl;
    }


    public function sendRewardWhatsapp(Request $request)
    {
       // $party_code = $request->input('party_code');
        $party_code = $request->query('party_code'); // Get the party_code from the query string

        // Fetch user data
        
        $userData = User::where('party_code', $party_code)->first();

        if (!$userData) {
            return response()->json(['error' => 'Party code is invalid or user not found.'], 400);
        }

        // Generate PDF dynamically (replace with your reward generation logic)
        
       // $publicUrl = 'https://mazingbusiness.com/public/reward_pdf/rewards_statement.pdf';
       $publicUrl= $this->getRewardPdfURL($party_code);
       $fileName = basename($publicUrl);

        // Static example data
       
        $phone = $userData->phone; // Replace with the user's phone number
        //$ref="7044300330";
        $imageUrl="https://mazingbusiness.com/public/reward_pdf/reward_image.jpg";
        // Prepare WhatsApp template data
        $templateData = [
            'name' => 'utility_rewards', // Replace with your template name
            'language' => 'en_US', // Replace with your desired language code
            'components' => [
                // [
                //     'type' => 'header',
                //     'parameters' => [
                //         [
                //             'type' => 'document',
                //             'document' => [
                //                 'link' => $publicUrl,
                //                 'filename' => $fileName,
                //             ],
                //         ],
                //     ],
                // ],

                [
                    'type' => 'header',
                   'parameters' => [
                        ['type' => 'image', 'image' => ['link' => $imageUrl]],
                    ],
                 ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $userData->company_name],
                    ],
                ],
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $fileName, // Button text
                        ],
                    ],
                ],
            ],
        ];

        // WhatsApp Numbers to send the template to
        $whatsappNumbers = [
           
            $phone,
        ];

        // Simulated WhatsApp Web Service Call (replace this with your actual WhatsApp API integration)
        $this->whatsAppWebService = new WhatsAppWebService();
        foreach ($whatsappNumbers as $number) {
            if (!empty($number)) {
                $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($number, $templateData);

                // Parse WhatsApp API response
                if (isset($jsonResponse['messages'][0]['message_status']) && $jsonResponse['messages'][0]['message_status'] === 'accepted') {
                    return response()->json(['message' => 'Reward statement sent successfully via WhatsApp.']);
                } else {
                    $error = $jsonResponse['messages'][0]['message_status'] ?? 'unknown';
                    return response()->json(['error' => "Failed to send reward statement. Status: $error"], 400);
                }
            }
        }

        return response()->json(['error' => 'No valid phone number provided.'], 400);

        
    }

    private function generatePaymentUrl($party_code, $payment_for)
    {
        
        $client = new \GuzzleHttp\Client();
        // echo $party_code; die;
        $response = $client->post('https://mazingbusiness.com/api/v2/payment/generate-url', [
            'json' => [
                'party_code' => $party_code,
                'payment_for' => $payment_for
            ],
            'debug' => true
        ]);
        $data = json_decode($response->getBody(), true);
        return $data['url'] ?? '';  // Return the generated URL or an empty string if it fails
    }

    public function searchStatementDetails(Request $request){
        $party_code = decrypt($request->input('party_code'));
        $userAddressData = Address::where('acc_code', $party_code)->first();
        $userData = User::where('id', $userAddressData->user_id)->first();
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        

        $statement_data = json_decode($userAddressData->statement_data, true);
        // echo "<pre>"; print_r($statement_data); die;
        // ----- New Logic End -----
        
        if($from_date != "" AND $to_date != ""){
            $opening_balance = 0.00;
            $filtered_transactions = [];
            $closing_balance = 0.00;

            // Step 1: Loop through all data
            foreach ($statement_data as $entry) {
                // Skip closing C/f...
                if (isset($entry['ledgername']) && strtolower(trim($entry['ledgername'])) === 'closing c/f...') {
                    continue;
                }

                $entry_date = $entry['trn_date'];

                if ($entry_date < $from_date) {
                    // Add to opening balance
                    $opening_balance += floatval($entry['dramount']) - floatval($entry['cramount']);
                } elseif ($entry_date >= $from_date && $entry_date <= $to_date) {
                    // Include in filtered
                    $filtered_transactions[] = $entry;
                }
            }

            // Step 2: Prepare opening balance entry
            $opening_entry = [
                'trn_no' => '',
                'trn_date' => $from_date,
                'vouchertypebasename' => '',
                'ledgername' => 'Opening b/f...',
                'ledgerid' => '',
                'dramount' => $opening_balance > 0 ? number_format($opening_balance, 2, '.', '') : "0.00",
                'cramount' => $opening_balance < 0 ? number_format(abs($opening_balance), 2, '.', '') : "0.00",
                'narration' => '',
                'running_balance' => number_format($opening_balance, 2, '.', '')
            ];

            // Step 3: Calculate running balance for filtered items
            $running_balance = $opening_balance;
            foreach ($filtered_transactions as &$entry) {
                $running_balance += floatval($entry['dramount']) - floatval($entry['cramount']);
                $entry['running_balance'] = number_format($running_balance, 2, '.', '');
            }

            // Step 4: Prepare closing balance
            $closing_entry = [
                'trn_no' => '',
                'trn_date' => $to_date,
                'vouchertypebasename' => '',
                'ledgername' => 'closing C/f...',
                'ledgerid' => '',
                'dramount' => $running_balance > 0 ? number_format($running_balance, 2, '.', '') : "0.00",
                'cramount' => $running_balance < 0 ? number_format(abs($running_balance), 2, '.', '') : "0.00",
                'narration' => '',
                'running_balance' => number_format($running_balance, 2, '.', '')
            ];

            // Step 5: Combine all into final result
            $final_statement = [];
            $final_statement[] = $opening_entry;
            $final_statement = array_merge($final_statement, $filtered_transactions);
            $final_statement[] = $closing_entry;
            $statement_data = $final_statement;
        }

        $overdueAmount = "0";
        $openingBalance="0";
        $openDrOrCr="";
        $closingBalance="0";
        $closeDrOrCr="";
        $dueAmount="0";
        $overdueDateFrom="";
        $overdueDrOrCr="";
        $getData=array();
        $overDueMark = array();
        $getOverdueData = $statement_data;
        if($statement_data !== NULL){
            $closingBalanceResult = array_filter($getOverdueData, function ($entry) {
                return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
            });
            $closingEntry = reset($closingBalanceResult);
            $cloasingDrAmount = $closingEntry['dramount'];
            $cloasingCrAmount = $closingEntry['cramount'];          
            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
            if($cloasingDrAmount > 0){
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
            // echo $overdueAmount; die;
            if($overdueAmount <= 0){
                $overdueDrOrCr = 'Cr';
                $overdueAmount = 0;
            }else{
                $overdueDrOrCr = 'Dr';
            }

            // Overdue Calculation End
        }        
        
        if ($statement_data !== NULL) {
            $getData = $statement_data;            
            $openingBalance = "0";
            $closingBalance = "0";
            $openDrOrCr = "";
            $drBalance = "0";
            $crBalance = "0";
            $dueAmount = "0";
            $overDueMarkTrnNos = array_column($overDueMark, 'trn_no'); 
            $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
            $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
            // echo "<pre>"; print_r($overDueMark); die();
            foreach($getData as $gKey=>$gValue){
                //echo "<pre>";print_r($gValue);
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
                        // $closeDrOrCr = "Cr";
                    }else{
                        $closingBalance = $gValue['cramount'];
                        // $dueAmount = $gValue['cramount'];
                        // $closeDrOrCr = "Cr";
                        // $closeDrOrCr = "Dr";
                    }
                }
                if(count($overDueMark) > 0 AND $gValue['ledgername'] != "closing C/f...") {
                    $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                    if ($key !== false) {
                        $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                        $getData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                    }
                }else{
                    if(isset($getData[$gKey]['overdue_status'])){ $getData[$gKey]['overdue_status']=""; }
                    if(isset($getData[$gKey]['overdue_by_day'])){ $getData[$gKey]['overdue_by_day'] = ""; }
                }

                if ($gValue['dramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                    $drBalance = $drBalance + $gValue['dramount'];
                    $dueAmount = $dueAmount + $gValue['dramount'];
                } 
                if($gValue['cramount'] != 0.00 AND $gValue['ledgername'] != 'closing C/f...') {
                    $crBalance = $crBalance + $gValue['cramount'];
                    $dueAmount = $dueAmount - $gValue['cramount'];
                }
            }
            $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
        }

        $overduePaymentUrl=0;
        $duePaymentUrl=0;
        $customPaymentUrl="";
        
        $html = view('frontend.partials._table_rows', [
            'data' => $getData,
            'openingBalance' => $openingBalance,
            'closeDrOrCr' => $closeDrOrCr,
            'closingBalance' => $closingBalance
        ])->render();

        return response()->json(['html' => $html, 'openingBalance' => $openingBalance, 'closingBalance'=>$closingBalance]);

    }

    public function ___searchStatementDetails(Request $request){
        $party_code = decrypt($request->input('party_code'));
        $userAddressData = Address::where('acc_code', $party_code)->first();
        $userData = User::where('id', $userAddressData->user_id)->first();
        // Overdue Calculation Start
        $overdueAmount = "0";
        $openingBalance="0";
        $openDrOrCr="";
        $closingBalance="0";
        $closeDrOrCr="";
        $dueAmount="0";
        $overdueDateFrom="";
        $overdueAmount="0";
        $overdueDrOrCr="";
        $getData=array();
        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        $overDueMark = array();
        if ($currentMonth >= 4) {
            $fy_form_date = date('Y-04-01'); // Start of financial year
            $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        } else {
            $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
            $fy_to_date = date('Y-03-31'); // Current year March
        }
        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];        
        $body = [
            'party_code' => $party_code,
            'from_date' => $fy_form_date,
            'to_date' =>  $fy_to_date,
        ];
        \Log::info('Sending request to API For Overdue', [
            'url' => 'https://saleszing.co.in/itaapi/getclientstatement.php',
            'headers' => $headers,
            'body' => $body
        ]);        
        $overdue_response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);        
        \Log::info('Received response from API For Overdue', [
            'status' => $overdue_response->status(),
            'body' => $overdue_response->body()
        ]);
        $getOverdueData = $overdue_response->json();
        $getOverdueData = $getOverdueData['data'];
        if(!empty($getOverdueData)){
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
                
                foreach($getOverdueData as $ovKey=>$ovValue){
                    if($ovValue['ledgername'] != 'closing C/f...'){
                        if(strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)){
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            // $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }elseif(strtotime($ovValue['trn_date']) < strtotime($overdueDateFrom) AND $temOverDueBalance > 0 AND $ovValue['dramount'] != '0.00'){
                            $temOverDueBalance -= $ovValue['dramount'];
                            $date1 = $ovValue['trn_date'];
                            $date2 = $overdueDateFrom;

                            $diff = abs(strtotime($date2) - strtotime($date1));

                            $years = floor($diff / (365*60*60*24));
                            $months = floor(($diff - $years * 365*60*60*24) / (30*60*60*24));
                            $days = floor(($diff - $years * 365*60*60*24 - $months*30*60*60*24) / (60*60*24));

                            // Initialize an empty array to store non-zero date parts
                            $dateParts = [];

                            if ($years > 0) {
                                $dateParts[] = "$years years";
                            }
                            if ($months > 0) {
                                $dateParts[] = "$months months";
                            }
                            if ($days > 0) {
                                $dateParts[] = "$days days";
                            }
                            // Combine the date parts into a string
                            // $dateDifference = implode(', ', $dateParts);
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
                                    'overdue_staus' => 'Partial Overdue'
                                ];
                            }
                            
                            
                        }
                    }
                }
            }
            if($overdueAmount <= 0){
                $overdueDrOrCr = 'Dr';
                $overdueAmount = 0;
            }else{
                $overdueDrOrCr = 'Cr';
            }
        }
        // Overdue Calculation End


        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');

        // $currentDate = date('Y-m-d');
        // $currentMonth = date('m');
        // $currentYear = date('Y');
        // if ($currentMonth >= 4) {
        //     $from_date = date('Y-04-01'); // Start of financial year
        //     $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        // } else {
        //     $from_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
        //     $to_date = date('Y-03-31'); // Current year March
        // }
        // if ($to_date > $currentDate) {
        //     $to_date = $currentDate;
        // }
        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];        
        $body = [
            'party_code' => $party_code,
            'from_date' => $from_date,
            'to_date' =>  $to_date,
        ];
        \Log::info('Sending request to API', [
            'url' => 'https://saleszing.co.in/itaapi/getclientstatement.php',
            'headers' => $headers,
            'body' => $body
        ]);        
        $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);        
        \Log::info('Received response from API', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);
        if ($response->successful()) {
            $getData = $response->json();
            $getData = $getData['data'];
            if(!empty($getData)){
                $openingBalance = 0;
                $closingBalance = 0;
                $openDrOrCr = "";
                $drBalance = 0;
                $crBalance = 0;
                $closeDrOrCr="";
                $overDueMarkTrnNos = array_column($overDueMark, 'trn_no'); 
                $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
                $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
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
                            $closeDrOrCr = "Dr";
                        }else{
                            $closingBalance = $gValue['cramount'];
                            $closeDrOrCr = "Cr";
                        }
                    }
                    if(count($overDueMark) > 0) {
                        $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                        if ($key !== false) {
                            $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                            $getData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                        }
                    }
                }
            }
            // return response()->json(['data' => $getData, 'openingBalance' => $openingBalance, 'closingBalance'=>$closingBalance]);
        }
        // echo "<pre>"; print_r($getData);die;
        $html = view('frontend.partials._table_rows', [
            'data' => $getData,
            'openingBalance' => $openingBalance,
            'closeDrOrCr' => $closeDrOrCr,
            'closingBalance' => $closingBalance
        ])->render();

        return response()->json(['html' => $html, 'openingBalance' => $openingBalance, 'closingBalance'=>$closingBalance]);

    }

    public function refreshStatementDetails(Request $request){
        $party_code = decrypt($request->input('party_code'));
        // $party_code = 'OPEL0100224';
        $userAddress = Address::where('acc_code', $party_code)->first();
        $userData = User::where('id', $userAddress->user_id)->first();

        // -----------------------------------------------------------------------------------

        $from_date = '2025-04-01';
        $to_date = date('Y-m-d');
        $orgId = $this->orgId;
        $statement_data = array();
        $cleanedStatement = array();

        // Get multiple address with same GST number.
        if ($userAddress) {
            $gstin = $userAddress->gstin;
            $usersAllAddress = Address::where('user_id', $userData->id)->where('gstin', $gstin)->get();
        } else {
            $usersAllAddress = collect(); // Return empty collection if no address found
        }
        // Get Zoho Data
        foreach($usersAllAddress as $userAddressData){
            $contactId = $userAddressData->zoho_customer_id;
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
                            // $tempArray['trn_date'] = $value[0];
                            // change the date format for zoho tran date.
                            $raw = trim((string) $value[0]);
                            $formats = [
                                'd/m/Y H:i:s.u', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
                                'd-m-Y H:i:s.u', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
                                'Y-m-d H:i:s.u', 'Y-m-d H:i:s', 'Y-m-d',
                            ];
                            $dt = null;
                            foreach ($formats as $f) {
                                $dt = \DateTime::createFromFormat($f, $raw);
                                if ($dt) break;
                            }
                            $tempArray['trn_date'] = $dt ? $dt->format('Y-m-d') : e($raw);
                            // -----------------------------------------------------------
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
                            }elseif($value[1] == 'Journal' AND $value[3] != ""){
                                // clean formatting
                                $amount = str_replace([',', '(', ')'], '', $value[3]);
                                if (strpos($value[3], '(') !== false || (float)$amount < 0) {
                                    // Credit if bracketed or negative
                                    $tempArray['cramount'] = (float)abs($amount);
                                    $tempArray['dramount'] = 0.00;

                                    // $tempArray['dramount'] = (float)abs($amount);
                                    // $tempArray['cramount'] = 0.00;
                                    
                                } else {
                                    // Otherwise Debit
                                    $tempArray['dramount'] = (float)$amount;
                                    $tempArray['cramount'] = 0.00;

                                    // $tempArray['cramount'] = (float)$amount;
                                    // $tempArray['dramount'] = 0.00;
                                }
                            }elseif($value[1] == 'Journal' AND $value[4] != ""){
                                // clean formatting
                                $amount = str_replace([',', '(', ')'], '', $value[3]);
                                if (strpos($value[3], '(') !== false || (float)$amount < 0) {
                                    // Credit if bracketed or negative
                                    $tempArray['dramount'] = (float)abs($amount);
                                    $tempArray['cramount'] = 0.00;
                                    // $tempArray['cramount'] = (float)abs($amount);
                                    // $tempArray['dramount'] = 0.00;
                                    
                                } else {
                                    // Otherwise Debit
                                    $tempArray['cramount'] = (float)$amount;
                                    $tempArray['dramount'] = 0.00;
                                    // $tempArray['dramount'] = (float)$amount;
                                    // $tempArray['cramount'] = 0.00;
                                }
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

                if (File::exists($fullPath)) {
                    File::delete($fullPath);
                }
            }            
            $statement_data[]=$arrayBeautifier;
        }
        // echo "<pre>"; print_r($statement_data);die;

        // Get salezing Data
        foreach($usersAllAddress as $userAddressData){
            $contactId = $userAddressData->zoho_customer_id;
            // Get Salezing Statement from Database
            $salezingData = UserSalzingStatement::where('zoho_customer_id',$contactId)->first();
            $cleanedStatement = array();
            if($salezingData != NULL){                
                // Step 1: Decode the statement
                $salezingStatement = json_decode($salezingData->statement_data, true);
                // Step 2: Clean 'x1' from each item
                $cleanedStatement = array_map(function ($item) {
                    unset($item['x1']);
                    if (isset($item['overdue_status'])) unset($item['overdue_status']);
                    if (isset($item['overdue_by_day'])) unset($item['overdue_by_day']);
                    return $item;
                }, $salezingStatement);
                // Step 3: Remove the last item
                array_pop($cleanedStatement);
            }        
            if (count($cleanedStatement) > 0) {                
                // Remove "closing C/f......" entries
                $filteredData = array_filter($cleanedStatement, function ($item) {
                    return !isset($item['ledgername']) || stripos($item['ledgername'], 'closing C/f...') === false;
                });       
                $statement_data[] = $filteredData;
            }
        }
        
        // Get Seller Statement
        // $getSellerData = Seller::where('customer_user_id', $userData->id)->first();
        $getSellerData = Seller::where('customer_user_id', $userData->id)
            ->whereNotNull('gstin')
            ->where('gstin', $userAddress->gstin)
            ->first();
        $sellerArrayBeautifier = array();
        // if($getSellerData != null){
        if($getSellerData != null && $userAddress && !empty($userAddress->gstin) && strcasecmp(($getSellerData->gstin ?? ''), $userAddress->gstin) === 0){    
            // $vendorContactId = '2435622000001680418'; // CLIF
            $vendorContactId = $getSellerData->zoho_seller_id;
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
                            $trn_no_array = explode('<div>',trim($tempVarArray[0]));

                            $tempArray['trn_no'] = $trn_no_array[0];
                            // $tempArray['trn_date'] = $value[0];
                            // change the date format for zoho tran date.
                            $raw = trim((string) $value[0]);
                            $formats = [
                                'd/m/Y H:i:s.u', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
                                'd-m-Y H:i:s.u', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
                                'Y-m-d H:i:s.u', 'Y-m-d H:i:s', 'Y-m-d',
                            ];
                            $dt = null;
                            foreach ($formats as $f) {
                                $dt = \DateTime::createFromFormat($f, $raw);
                                if ($dt) break;
                            }
                            $tempArray['trn_date'] = $dt ? $dt->format('Y-m-d') : e($raw);
                            // -----------------------------------------------------------
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

                            if($value[1] == 'Payment Made'){
                                $tempArray['trn_no'] = 'BANK ENTRY';
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
                // if(count($cleanedStatement) > 0){
                //     $sellerArrayBeautifier = array_merge($cleanedStatement, $sellerArrayBeautifier);
                // }
                // echo "<pre>"; print_r($sellerArrayBeautifier); die;
                if (File::exists($fullPath)) {
                    File::delete($fullPath);
                }
                
                // echo "<pre>"; print_r($sellerArrayBeautifier); die;
                
                $statement_data[]=$sellerArrayBeautifier;
            }
        }
        
        $mergedData = [];
        foreach ($statement_data as $data) {
            $mergedData = array_merge($mergedData, $data);
        }        
        $statement_data = array_values($mergedData);
        
        usort($statement_data, function ($a, $b) {
            return strtotime($a['trn_date']) - strtotime($b['trn_date']);
        });
        
        // calculate the running ballance
        $balance = 0.00;
        foreach($statement_data as $gKey=>$gValue){
            $balance += number_format(((double)$gValue['dramount'] - (double)$gValue['cramount']),2,'.','');
            $balance = number_format($balance,2,'.','');
            $statement_data[$gKey]['running_balance'] = $balance;
        }
        // echo $balance; print_r($statement_data);die;
        // Insert closing balance into array
        $tempArray['trn_no'] = "";
        $tempArray['trn_date'] = date('Y-m-d');
        $tempArray['vouchertypebasename'] = "";
        $tempArray['ledgername'] = "closing C/f...";
        $tempArray['ledgerid'] = "";
        if ($balance <= 0) {
            $tempArray['cramount'] = $balance == 0.00 ? number_format(0, 2, '.', '') : number_format((float)str_replace('-', '', str_replace(',', '', $balance)), 2, '.', '');
            $tempArray['dramount'] = number_format(0, 2, '.', '');
        } else {
            $tempArray['dramount'] = number_format((float)str_replace('-', '', str_replace(',', '', $balance)), 2, '.', '');
            $tempArray['cramount'] = number_format(0, 2, '.', '');
        }
        $tempArray['narration'] = "";
        $statement_data[] = $tempArray;

        // echo "<pre>"; print_r($statement_data);die;

        $finalStatementArray = $statement_data;

        

        // Start the statement data as like salzing
        $overdueAmount = "0";
        $openingBalance="0";
        $openDrOrCr="";
        $closingBalance="0";
        $closeDrOrCr="";
        $dueAmount="0";
        $overdueDateFrom="";
        $overdueDrOrCr="";
        $overDueMark = array();
        $drBalance = 0;
        $crBalance = 0;
        $getUserData = Address::with('user')->where('zoho_customer_id',$contactId)->first();
        $userData = $getUserData->user;
        $getOverdueData = $finalStatementArray;
        $closingBalanceResult = array_filter($getOverdueData, function ($entry) {
            return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
        });
        $closingEntry = reset($closingBalanceResult);
        $cloasingDrAmount = $closingEntry['dramount'];
        $cloasingCrAmount = $closingEntry['cramount'];

        $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));
        if($cloasingDrAmount > 0){
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
        // echo "<pre>"; print_r($userData);die;
        $getData = $finalStatementArray;
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
                    // echo $gValue['trn_date'];
                }else{
                    if(isset($getData[$gKey]['overdue_status'])){
                        unset($getData[$gKey]['overdue_status']);
                        unset($getData[$gKey]['overdue_by_day']);
                    }
                }
            }else{
                if(isset($getData[$gKey]['overdue_status'])){
                    unset($getData[$gKey]['overdue_status']);
                    unset($getData[$gKey]['overdue_by_day']);
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

        // Update value with blank
        foreach($usersAllAddress as $userAddressData){
            if ($userAddressData && $userAddressData->acc_code) {
                Address::where('acc_code', $userAddressData->acc_code)->update([
                    'due_amount'      => "0.00",
                    'dueDrOrCr'       => null,
                    'overdue_amount'  => "0.00",
                    'overdueDrOrCr'   => null,
                    'statement_data'  => null
                ]);
            }
        }
        Address::where('acc_code', $party_code)
        ->update(
            [
                'due_amount'      => $dueAmount,
                'dueDrOrCr'      => $closeDrOrCr,
                'overdue_amount' => $overdueAmount,
                'overdueDrOrCr' => $overdueDrOrCr,
                'statement_data' => json_encode($getData)
            ]
        );
        
        \Log::info('Update Statement of party '.$contactId.' with cron', [
            'status' => 'End',
            'party_code' =>  $userAddressData->acc_code
        ]);

        $userAddressData = Address::where('acc_code', $party_code)->select('addresses.*')->get();
        $rewardCount = 1;
        foreach($userAddressData as $key=>$value){            
            $userData = User::where('id', $value->user_id)->first();
            $url = 'https://mazingbusiness.com/mazing_business_react/api/saleszing/saleszing-statement-get';
            $response = Http::get($url, [
                'address_id' => $value->id,
                'data_from' => 'database',
            ]);
            \Log::info($rewardCount.'. Early Rewards Point calculate of '.$value->acc_code." - ".$value->company_name);
            $rewardCount++;
        }

        $html = view('frontend.partials._table_rows', [
            'data' => $getData,
            'openingBalance' => $openingBalance,
            'closeDrOrCr' => $closeDrOrCr,
            'closingBalance' => $closingBalance
        ])->render();

        return response()->json(['html' => $html, 'openingBalance' => $openingBalance, 'closingBalance'=>$closingBalance, 'due_amount'=>single_price($dueAmount).' '.$closeDrOrCr, 'overdue_amount'=>single_price($overdueAmount).' '.$overdueDrOrCr]);

    }

    public function refreshStatementDetailsBackup15052015(Request $request){
        $party_code = decrypt($request->input('party_code'));
        $userAddressData = Address::where('acc_code', $party_code)->first();
        $userData = User::where('id', $userAddressData->user_id)->first();

        $from_date = '2025-04-01';
        $to_date = date('Y-m-d');

        $contactId = $userAddressData->zoho_customer_id;

        \Log::info('Update Statement of party '.$contactId.' with cron', [
            'status' => 'Start',
            'party_code' =>  $userAddressData->acc_code
        ]);

        $orgId = $this->orgId;

        // Get Salezing Statement from Database
        $salezingData = UserSalzingStatement::where('zoho_customer_id',$contactId)->first();
        $cleanedStatement = array();
        if($salezingData != NULL){
            // Step 1: Decode the statement
            $salezingStatement = json_decode($salezingData->statement_data, true);
            // Step 2: Clean 'x1' from each item
            $cleanedStatement = array_map(function ($item) {
                unset($item['x1']);
                if (isset($item['overdue_status'])) unset($item['overdue_status']);
                if (isset($item['overdue_by_day'])) unset($item['overdue_by_day']);
                return $item;
            }, $salezingStatement);
            // Step 3: Remove the last item
            array_pop($cleanedStatement);
        }
        
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
                        // $tempArray['trn_date'] = $value[0];
                        // change the date format for zoho tran date.
                        $raw = trim((string) $value[0]);
                        $formats = [
                            'd/m/Y H:i:s.u', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
                            'd-m-Y H:i:s.u', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
                            'Y-m-d H:i:s.u', 'Y-m-d H:i:s', 'Y-m-d',
                        ];
                        $dt = null;
                        foreach ($formats as $f) {
                            $dt = \DateTime::createFromFormat($f, $raw);
                            if ($dt) break;
                        }
                        $tempArray['trn_date'] = $dt ? $dt->format('Y-m-d') : e($raw);
                        // -----------------------------------------------------------
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

            // calculate the running ballance
            $balance = 0.00;
            foreach($arrayBeautifier as $gKey=>$gValue){
                $balance += (float)$gValue['dramount'] - (float)$gValue['cramount'];
                $arrayBeautifier[$gKey]['running_balance'] = $balance;
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
            $arrayBeautifier[] = $tempArray;

            // Start the statement data as like salzing
            $overdueAmount = "0";
            $openingBalance="0";
            $openDrOrCr="";
            $closingBalance="0";
            $closeDrOrCr="";
            $dueAmount="0";
            $overdueDateFrom="";
            $overdueDrOrCr="";
            $overDueMark = array();
            $drBalance = 0;
            $crBalance = 0;
            $getUserData = Address::with('user')->where('zoho_customer_id',$contactId)->first();
            $userData = $getUserData->user;
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
                        // echo $gValue['trn_date'];
                    }else{
                        if(isset($getData[$gKey]['overdue_status'])){
                            unset($getData[$gKey]['overdue_status']);
                            unset($getData[$gKey]['overdue_by_day']);
                        }
                    }
                }else{
                    if(isset($getData[$gKey]['overdue_status'])){
                        unset($getData[$gKey]['overdue_status']);
                        unset($getData[$gKey]['overdue_by_day']);
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
            // echo "<pre>"; print_r($overDueMark); die;
            $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
            // echo $overdueAmount; die;
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
        } else {
            return response()->json([
                'error' => 'Failed to download Excel.',
                'details' => $response->body(),
                'status' => $response->status()
            ], 500);
        }
        
        \Log::info('Update Statement of party '.$contactId.' with cron', [
            'status' => 'End',
            'party_code' =>  $userAddressData->acc_code
        ]);
        $userAddressData = Address::where('zoho_customer_id',$contactId)->select('addresses.*')->get();
        // echo "<pre>"; print_r($userAddressData);die;
        $rewardCount = 1;
        foreach($userAddressData as $key=>$value){
            // echo "<pre>"; print_r($value);die;
            // $userData = User::where('id', $value->user_id)->first();
            $url = 'https://mazingbusiness.com/mazing_business_react/api/saleszing/saleszing-statement-get';
            $response = Http::get($url, [
                'address_id' => $value->id,
                'data_from' => 'live',
            ]);
            // if ($response->successful()) {
            //     // Process and return the response data
            //     $data = $response->json(); // Converts response to array
            //     return response()->json(['status' => 'success', 'data' => $data]);
            // }
            \Log::info($rewardCount.'. Early Rewards Point calculate of '.$userAddressData->acc_code." - ".$userAddressData->company_name);
            $rewardCount++;
        }

        $html = view('frontend.partials._table_rows', [
            'data' => $getData,
            'openingBalance' => $openingBalance,
            'closeDrOrCr' => $closeDrOrCr,
            'closingBalance' => $closingBalance
        ])->render();

        return response()->json(['html' => $html, 'openingBalance' => $openingBalance, 'closingBalance'=>$closingBalance, 'due_amount'=>single_price($dueAmount).' '.$closeDrOrCr, 'overdue_amount'=>single_price($overdueAmount).' '.$overdueDrOrCr]);

    }

    public function refreshStatementDetails_backup(Request $request){
        $party_code = decrypt($request->input('party_code'));
        $userAddressData = Address::where('acc_code', $party_code)->first();
        $userData = User::where('id', $userAddressData->user_id)->first();
        // Overdue Calculation Start
        $overdueAmount = "0";
        $openingBalance="0";
        $openDrOrCr="";
        $closingBalance="0";
        $closeDrOrCr="";
        $dueAmount="0";
        $overdueDateFrom="";
        $overdueAmount="0";
        $overdueDrOrCr="";
        $getData=array();
        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');
        $overDueMark = array();
        // if ($currentMonth >= 4) {
        //     $fy_form_date = date('Y-04-01'); // Start of financial year
        //     $fy_to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        // } else {
        //     $fy_form_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
        //     $fy_to_date = date('Y-03-31'); // Current year March
        // }

        $fy_form_date='2024-04-01';
        $fy_to_date=date('Y-m-d');

        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];        
        $body = [
            'party_code' => $party_code,
            'from_date' => $fy_form_date,
            'to_date' =>  $fy_to_date,
        ];
        \Log::info('Sending request to API For Overdue', [
            'url' => 'https://saleszing.co.in/itaapi/getclientstatement.php',
            'headers' => $headers,
            'body' => $body
        ]);        
        $overdue_response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);        
        \Log::info('Received response from API For Overdue', [
            'status' => $overdue_response->status(),
            'body' => $overdue_response->body()
        ]);
        $getOverdueData = $overdue_response->json();
        $getOverdueData = $getOverdueData['data'];        
        if(!empty($getOverdueData)){
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
                // $overDueMark = array();
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

                            // $years = floor($diff / (365*60*60*24));
                            // $months = floor(($diff - $years * 365*60*60*24) / (30*60*60*24));
                            // $days = floor(($diff - $years * 365*60*60*24 - $months*30*60*60*24) / (60*60*24));

                            // // Initialize an empty array to store non-zero date parts
                            // $dateParts = [];

                            // if ($years > 0) {
                            //     $dateParts[] = "$years years";
                            // }
                            // if ($months > 0) {
                            //     $dateParts[] = "$months months";
                            // }
                            // if ($days > 0) {
                            //     $dateParts[] = "$days days";
                            // }
                            // Combine the date parts into a string
                            // $dateDifference = implode(', ', $dateParts);
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
                                    'overdue_staus' => 'Partial Overdue'
                                ];
                            }
                            
                            
                        }
                    }
                }
            }
            if($overdueAmount <= 0){
                // $overdueDrOrCr = 'Dr';
                $overdueDrOrCr = 'Cr';
                $overdueAmount = 0;
            }else{
                // $overdueDrOrCr = 'Cr';
                $overdueDrOrCr = 'Dr';
            }
            // Overdue Calculation End
        }

        $from_date = $fy_form_date;
        $to_date = $fy_to_date;

        // $currentDate = date('Y-m-d');
        // $currentMonth = date('m');
        // $currentYear = date('Y');
        // if ($currentMonth >= 4) {
        //     $from_date = date('Y-04-01'); // Start of financial year
        //     $to_date = date('Y-03-31', strtotime('+1 year')); // End of financial year (next year)
        // } else {
        //     $from_date = date('Y-04-01', strtotime('-1 year')); // Previous year April
        //     $to_date = date('Y-03-31'); // Current year March
        // }
        // if ($to_date > $currentDate) {
        //     $to_date = $currentDate;
        // }
        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];        
        $body = [
            'party_code' => $party_code,
            'from_date' => $from_date,
            'to_date' =>  $to_date,
        ];
        \Log::info('Sending request to API', [
            'url' => 'https://saleszing.co.in/itaapi/getclientstatement.php',
            'headers' => $headers,
            'body' => $body
        ]);        
        $response = Http::withHeaders($headers)->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);        
        \Log::info('Received response from API', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);
        
        if ($response->successful()) {
            $getData = $response->json();
            $getData = $getData['data'];
            // echo "<pre>"; print_r($getData); die;
            if(!empty($getData)){
                $openingBalance = 0;
                $closingBalance = 0;
                $openDrOrCr = "";
                $drBalance = 0;
                $crBalance = 0;
                $closeDrOrCr="";
                $dueAmount = 0;
                $overDueMarkTrnNos = array_column($overDueMark, 'trn_no'); 
                $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
                $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
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
                    if($gValue['cramount'] != '0.00' AND $gValue['ledgername'] != 'closing C/f...') {
                        $crBalance = $crBalance + $gValue['cramount'];
                        $dueAmount = $dueAmount - $gValue['cramount'];
                    }
                }
                $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';
                Address::where('acc_code', $party_code)
                ->update(
                    [
                        'due_amount'      => $dueAmount,
                        'dueDrOrCr'      => $closeDrOrCr,
                        'overdue_amount' => $overdueAmount,
                        'overdueDrOrCr' => $overdueDrOrCr,
                        'statement_data' => json_encode($getData)
                    ]
                );
            }
        }

        $html = view('frontend.partials._table_rows', [
            'data' => $getData,
            'openingBalance' => $openingBalance,
            'closeDrOrCr' => $closeDrOrCr,
            'closingBalance' => $closingBalance
        ])->render();

        return response()->json(['html' => $html, 'openingBalance' => $openingBalance, 'closingBalance'=>$closingBalance, 'due_amount'=>single_price($dueAmount).' '.$closeDrOrCr, 'overdue_amount'=>single_price($overdueAmount).' '.$overdueDrOrCr]);

    }


    public function syncStatementFromSalezing(){
        SyncSalzingStatement::dispatch();
        return response()->json([
            'message' => 'Successfully sync the statement.'
        ]); 
    }

    public function syncSalzingStatementForOpeningBalance(){
        
        SyncSalzingStatementForOpeningBalance::dispatch();
        return response()->json([
            'message' => 'Successfully sync the statement.'
        ]); 
    }

    public function downloadStatementPdf(Request $request)
    {
        $invoiceController = new InvoiceController();
    
        // Capture the JSON response from the statementPdfDownload method
        $response = $invoiceController->statementPdfDownload($request);
        return $response;
    
        // Decode the JSON response (response()->json returns an instance of Illuminate\Http\JsonResponse)
        // $responseData = json_decode($response->getContent(), true); 

        // if ($responseData['status'] === 'success') {
          
        //     $pdf_url = $responseData['message'];
        //     return response()->json(['status' => 'success', 'message' => $pdf_url]);
        // } else {
        //     return response()->json(['status' => 'error', 'message' => 'PDF generation failed'], 500);
        // }
    
        
    }

    public function sendPayNowLink(Request $request)
    {
        try {
            // Step 1: Retrieve the data from the request
            $party_code = $request->input('party_code');
            $paymentUrl = $request->input('payment_url');  // Get the dynamic payment URL
            $paymentAmount = $request->input('payment_amount'); // Get the payment amount
            $paymentFor = $request->input('payment_for'); // Get the payment_for value (due_amount, custom_amount, overdue_amount)

            // Step 2: Get the corresponding party details using `acc_code` (party_code)
            $partyAddress = Address::where('acc_code', $party_code)->first();
            if (!$partyAddress) {
                throw new \Exception("Party not found for the given party_code: $party_code");
            }

            // Step 3: Get the due and overdue amounts from the `addresses` table
            $dueAmount = $partyAddress->due_amount ?? 0;
            $overdueAmount = $partyAddress->overdue_amount ?? 0;

            // Step 4: Prepare the payment amount string based on payment_for type
            if ($paymentFor === 'custom_amount') {
                // If it's a custom amount, show both due and overdue amounts
                $paymentAmount = "Due: ₹{$dueAmount}, Overdue: ₹{$overdueAmount}";
            }

            // Step 5: Get the manager details (Assuming authenticated user has manager_id)
            $user = Auth::user();
            $manager = User::where('id', $user->manager_id)->first();
            if (!$manager) {
                throw new \Exception("Manager not found for the user: " . $user->id);
            }

            // Step 6: Set the customer name, manager phone, and recipient phone
            $customer_name = $partyAddress->company_name;  // Get customer name from addresses table
            $manager_phone = $manager->phone;  // Get the manager's phone number
            $to = $user->phone;  // Use the party's phone or fallback number

             // Extract the part after 'pay-amount/'
              $fileName = substr($paymentUrl, strpos($paymentUrl, "pay-amount/") + strlen("pay-amount/"));
              $button_variable_encode_part=$fileName;

              $adminStatementController = new AdminStatementController();
          $pdf_url=$adminStatementController->generateStatementPdf($party_code, $partyAddress->due_amount, $partyAddress->overdue_amount, $user);
          $fileName1 = basename($pdf_url);
          $button_variable_pdf_filename=$fileName1;

            // Step 7: WhatsApp template data
            $templateData = [
                'name' => 'utility_initial_payment',  // Fixed template name
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $customer_name],
                            ['type' => 'text', 'text' => $paymentAmount],  // Correctly set payment amount
                            ['type' => 'text', 'text' => $manager_phone],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $button_variable_encode_part,  // File name used as button text
                            ],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '1',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $button_variable_pdf_filename,  // File name used as button text
                            ],
                        ],
                    ],
                ],
            ];

            // Convert template data to JSON for logging
            $jsonTemplateData = json_encode($templateData, JSON_PRETTY_PRINT);

            // Step 8: Send the WhatsApp message
            $this->whatsAppWebService = new WhatsAppWebService();
            $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($to, $templateData);

            // Log the JSON request for debugging purposes
            \Log::info('WhatsApp message sent:', ['request' => $jsonResponse]);

            // Return a successful response
            return response()->json(['success' => true, 'message' => ucfirst($paymentFor) . ' Pay Now link processed successfully.']);
        } catch (\Exception $e) {
            \Log::error('Error in sendPayNowLink:', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function cronForStockUpdate()
    {
        UpdateProductStockJob::dispatch();

        return response()->json([
            'status'  => 'ok',
            'message' => 'Stock update job dispatched to queue.',
        ]);
    }

    private function getAuthHeaders()
    {
        $token = ZohoToken::first();
        if (!$token) {
            abort(403, 'Zoho token not found.');
        }
        // Refresh token if expired
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

}
