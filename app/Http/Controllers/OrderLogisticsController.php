<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Order;
use App\Models\User;
use App\Models\Address;
use App\Models\Staff;
use App\Models\InvoiceOrderDetail;
use App\Models\InvoiceOrder;
use App\Models\OrderLogistic;
use Config;
use Hash;
use PDF;
use Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\WhatsAppWebService;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendWhatsAppMessagesJob;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\BillsDataController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use CoreComponentRepository;

use App\Services\PdfContentService;
use App\Http\Controllers\ZohoController;

class OrderLogisticsController extends Controller
{

    private function getManagerPhone($managerId)
    {
        $managerData = DB::table('users')
            ->where('id', $managerId)
            ->select('phone')
            ->first();

        return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
    }
    
    public function index(Request $request)
{
    // ✅ Auth + role check
    $allowedUserIds = [1, 180, 169, 25606];
    $user           = auth()->user();

    $staffRoleId = \App\Models\Staff::where('user_id', $user->id)->value('role_id');

    if (! in_array($user->id, $allowedUserIds) && (int) $staffRoleId !== 4) {
        abort(403, 'Unauthorized action.');
    }

    // ✅ Inputs
    $search        = trim((string) $request->input('search', ''));
    $sortField     = $request->input('sort', 'id');
    $sortDirection = $request->input('direction', 'desc');

    $isDateSearch = $search && strtotime($search) !== false;

    $cityMap = [
        'MUM' => 'Mumbai',
        'DEL' => 'Delhi',
        'KOL' => 'Kolkata',
    ];

    $normalizedSearch = ucfirst(strtolower($search));
    $cityCode         = array_search($normalizedSearch, $cityMap, true) ?: null;

    // ✅ Sort whitelist
    $sortableColumns = [
        'id',
        'invoice_no',
        'lr_date',
        'no_of_boxes',
        'lr_amount',
        'invoice_date',
    ];

    if (! in_array($sortField, $sortableColumns, true)) {
        $sortField = 'id';
    }

    $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

    // Yeh flag decide karega ki invoice_orders join karna hai ya nahi
    $needsInvoiceJoinForSort = ($sortField === 'invoice_date');

    // ✅ Subquery: har invoice_no ka latest logistics row (MAX id)
    $latestLogisticsSub = DB::table('order_logistics')
        ->selectRaw('MAX(id) as id')
        ->whereNotNull('invoice_no')
        ->where('invoice_no', '!=', '')
        ->groupBy('invoice_no');

    // ✅ Base query (sirf order_logistics + address)
    $logisticsQuery = DB::table('order_logistics as ol')
        ->joinSub($latestLogisticsSub, 'latest', function ($join) {
            $join->on('ol.id', '=', 'latest.id');
        })
        ->join('addresses as addr', 'ol.party_code', '=', 'addr.acc_code')
        ->whereNotNull('ol.invoice_no')
        ->where('ol.invoice_no', '!=', '')
        ->select(
            'ol.id',
            'ol.invoice_no',
            'ol.lr_date',
            'ol.lr_no',                 // transport_name PHP mein nikaalenge
            'ol.no_of_boxes',
            'ol.lr_amount',
            'ol.attachment',
            'ol.zoho_attachment_upload',
            'addr.company_name'
            // invoice_date / order_date yahan abhi add nahi kar rahe
        );

    // ✅ Agar invoice_date pe sort karna hai tabhi invoice_orders join karein
    if ($needsInvoiceJoinForSort) {
        $invoiceOrdersSub = DB::table('invoice_orders')
            ->select('id', 'invoice_no', 'created_at');

        $logisticsQuery
            ->leftJoinSub($invoiceOrdersSub, 'io', function ($join) {
                $join->on('ol.invoice_no', '=', 'io.invoice_no');
            })
            ->addSelect(
                'io.created_at as invoice_date',
                'io.created_at as order_date'
            );
    }

    // ✅ Search filters
    $logisticsQuery->when($search === '.blank', function ($query) {
        $query->where(function ($sub) {
            $sub->whereNull('ol.attachment')
                ->orWhere('ol.attachment', '');
        });
    }, function ($query) use ($search, $isDateSearch, $cityCode) {
        if (! $search) {
            return;
        }

        if ($isDateSearch) {
            $query->whereDate('ol.lr_date', $search);
            return;
        }

        if ($cityCode) {
            $query->where('ol.invoice_no', 'LIKE', "%{$cityCode}%");
            return;
        }

        if (stripos($search, '.pdf') !== false) {
            $query->where('ol.attachment', 'LIKE', '%.pdf%');
            return;
        }

        if (stripos($search, '.html') !== false) {
            $query->where(function ($sub) {
                $sub->where('ol.zoho_attachment_upload', '=', 0)
                    ->orWhere('ol.attachment', 'LIKE', '%.html%');
            });
            return;
        }

        // Default: invoice_no / company_name
        $query->where(function ($sub) use ($search) {
            $sub->where('ol.invoice_no', 'LIKE', "%{$search}%")
                ->orWhere('addr.company_name', 'LIKE', "%{$search}%");
        });
    });

    // ✅ Sorting
    if ($sortField === 'invoice_date') {
        $logisticsQuery->orderBy('io.created_at', $sortDirection);
    } else {
        $logisticsQuery->orderBy("ol.{$sortField}", $sortDirection);
    }

    // ✅ Pagination
    $logisticsData = $logisticsQuery
        ->paginate(30)
        ->appends([
            'search'    => $search,
            'sort'      => $sortField,
            'direction' => $sortDirection,
        ]);

    $collection = $logisticsData->getCollection();

    // ✅ Agar invoice_date pe sort nahi ho raha,
    // to ab chhote query se 30 invoice_no ke invoice date laa lo
    $invoiceMap = collect();

    if (! $needsInvoiceJoinForSort && $collection->isNotEmpty()) {
        $invoiceNos = $collection->pluck('invoice_no')->filter()->unique();

        if ($invoiceNos->isNotEmpty()) {
            $invoiceMap = DB::table('invoice_orders')
                ->whereIn('invoice_no', $invoiceNos)
                ->pluck('created_at', 'invoice_no');
        }
    }

    // ✅ Transform: place_of_dispatch, transport_name, invoice_date (agar required)
    $logisticsData->setCollection(
        $collection->map(function ($item) use ($invoiceMap, $needsInvoiceJoinForSort) {

            // transport_name (lr_no se)
            if (! empty($item->lr_no)) {
                $parts               = explode(' - ', $item->lr_no);
                $item->transport_name = $parts[0] ?? '';
            } else {
                $item->transport_name = '';
            }

            // place_of_dispatch: agar aapka getCityName DB hit karta hai
            // to uski jagah yeh lightweight version use kar sakte ho.
            $prefixMap = [
                'MUM' => 'Mumbai',
                'DEL' => 'Delhi',
                'KOL' => 'Kolkata',
            ];
            $prefix = strtoupper(substr($item->invoice_no, 0, 3));
            $item->place_of_dispatch = $prefixMap[$prefix] ?? ($this->getCityName($item->invoice_no) ?? '');

            // invoice_date fill karo jab join nahi kiya
            if (! $needsInvoiceJoinForSort) {
                $item->invoice_date = $invoiceMap[$item->invoice_no] ?? null;
                $item->order_date   = $item->invoice_date;
            }

            return $item;
        })
    );

    return view('backend.order_logistics.index', compact('logisticsData', 'search', 'sortField', 'sortDirection'));
}

    public function pushZohoAttachment($encrypted_invoice_no)
    {
        $invoiceNo = decrypt($encrypted_invoice_no);

        // 1) Invoice + Zoho ID
        $invoice = InvoiceOrder::where('invoice_no', $invoiceNo)
            ->whereNotNull('zoho_invoice_id')
            ->first();

        if (! $invoice) {
            return redirect()->route('order.logistics')
                ->with('error', 'Invoice or Zoho invoice ID not found for: '.$invoiceNo);
        }

        $zohoInvoiceId = $invoice->zoho_invoice_id;

        // 2) Latest logistic record with attachment
        $logistic = OrderLogistic::where('invoice_no', $invoiceNo)
            ->whereNotNull('attachment')
            ->where('attachment', '!=', '')
            ->orderByDesc('id')
            ->first();

        if (! $logistic) {
            return redirect()->route('order.logistics')
                ->with('error', 'No logistic record with attachment found for this invoice.');
        }

        // 3) First attachment URL → local path
        $attachments = explode(',', $logistic->attachment);
        $firstUrl    = trim($attachments[0] ?? '');

        if (empty($firstUrl)) {
            return redirect()->route('order.logistics')
                ->with('error', 'Attachment URL is empty.');
        }

        $pathPart = parse_url($firstUrl, PHP_URL_PATH); // e.g. /public/uploads/cw_acetools/xxxx.jpg
        $fileName = basename($pathPart);
        $localPath = public_path('uploads/cw_acetools/' . $fileName);

        if (! file_exists($localPath)) {
            return redirect()->route('order.logistics')
                ->with('error', 'Local attachment file not found: '.$fileName);
        }

        // 4) ZohoController helper call
        try {
            /** @var \App\Http\Controllers\ZohoController $zoho */
            $zoho = app(\App\Http\Controllers\ZohoController::class);

            // Agar tumhare uploadInvoiceAttachmentToZoho ke andar delete-and-reupload logic already hai,
            // to yahi call kaafi hai:
            $result = $zoho->uploadInvoiceAttachmentToZoho($zohoInvoiceId, $localPath);

            $uploaded = 0;
            if (is_array($result) && isset($result['code']) && (int)$result['code'] === 0) {
                $uploaded = 1;
            }

            // 5) order_logistics table me flag update
            $logistic->zoho_attachment_upload = $uploaded;
            $logistic->save();

            if ($uploaded) {
                return redirect()->route('order.logistics')
                    ->with('success', 'Attachment pushed to Zoho successfully for '.$invoiceNo);
            }

            return redirect()->route('order.logistics')
                ->with('error', 'Zoho upload failed, please check logs.');

        } catch (\Throwable $e) {
            \Log::error('Error in pushZohoAttachment: '.$e->getMessage(), [
                'invoice_no'      => $invoiceNo,
                'zoho_invoice_id' => $zohoInvoiceId ?? null,
            ]);

            return redirect()->route('order.logistics')
                ->with('error', 'Exception while pushing attachment to Zoho. Check logs.');
        }
    }

    private function getCityName($invoice_id) {
        // Extract the city code from the invoice ID
        preg_match('/[A-Z]{3}/', $invoice_id, $matches);

        // Check if a match was found
        if (!empty($matches)) {
            $cityCode = $matches[0];

            // Map city codes to full city names
            $cityMap = [
                'MUM' => 'Mumbai',
                'DEL' => 'Delhi',
                'KOL' => 'Kolkata',
                // Add more city codes and names here if needed
            ];

            // Return the corresponding city name or a default message
            return $cityMap[$cityCode] ?? 'Unknown City';
        }

        return 'Invalid Dispatch ID';
    }

 public function getInvoicePdfURL($id)
    {
        $invoice = InvoiceOrder::with('invoice_products')->where('invoice_no', $id)->firstOrFail();

        if (is_string($invoice->party_info)) {
            $invoice->party_info = json_decode($invoice->party_info, true);
        }

        $shipping = Address::find($invoice->shipping_address_id);

        // ✅ Fetch transport info from order_logistics
        $logistic = OrderLogistic::where('invoice_no', $id)->orderByDesc('id')->first();


        $billingDetails = (object) [
            'company_name' => $invoice->party_info['company_name'] ?? 'N/A',
            'address' => $invoice->party_info['address'] ?? 'N/A',
            'gstin' => $invoice->party_info['gstin'] ?? 'N/A',
        ];

        $manager_phone = '9999241558';

        $branchMap = [
            1 => 'KOL',
            2 => 'DEL',
            6 => 'MUM'
        ];

        $branchDetailsAll = [
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

        $branchCode = $branchMap[$invoice->warehouse_id] ?? 'DEL';
        $branchDetails = $branchDetailsAll[$branchCode] ?? [];

        // ✅ Pass $logistic to the view

        $pdfContentService = new PdfContentService();
        $pdfContentBlock   = $pdfContentService->buildBlockForType('invoice');
        
        $pdf = PDF::loadView('backend.sales.invoice_pdf', compact(
            'invoice',
            'billingDetails',
            'manager_phone',
            'branchDetails',
            'shipping',
            'logistic',
            'pdfContentBlock' // ✅ Blade me use hoga
        ));

        // Ensure directory exists
        $pdfDir = public_path('purchase_history_invoice');
        if (!file_exists($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }

        $fileName = str_replace('/', '_', $invoice->invoice_no) . '.pdf';
        $filePath = $pdfDir . '/' . $fileName;
        $pdf->save($filePath);

        return url('public/purchase_history_invoice/' . $fileName);
    }
    public function create($encrypted_invoice_no)
    {
        // Create Order Logistic view page
        $invoiceNo = Crypt::decrypt($encrypted_invoice_no);
        return view('backend.order_logistics.add', compact('invoiceNo'));
    }

    public function store(Request $request, $encrypted_invoice_no)
    {
        $invoiceNo = Crypt::decrypt($encrypted_invoice_no);

        // 🔍 Get invoice record from DB
        $invoice = DB::table('invoice_orders')->where('invoice_no', $invoiceNo)->first();
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found.');
        }

        $partyCode = $invoice->party_code ?? null;

        // 📦 Upload and save attachment locally
        $attachments = [];
        $localAttachmentPath = null;

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = public_path('uploads/cw_acetools');

                if (!file_exists($filePath)) {
                    mkdir($filePath, 0755, true);
                }

                $file->move($filePath, $fileName);
                $fileUrl = 'https://mazingbusiness.com/public/uploads/cw_acetools/' . $fileName;
                $attachments[] = $fileUrl;

                // Save path for Zoho (only first one)
                if (!$localAttachmentPath) {
                    $localAttachmentPath = $filePath . '/' . $fileName;
                }
            }
        }

        // 💾 Save to order_logistics table (pehle row banao, default zoho_attachment_upload = 0)
        $logisticId = DB::table('order_logistics')->insertGetId([
            'invoice_no'            => $invoiceNo,
            'party_code'            => $partyCode,
            'order_no'              => $invoiceNo,
            'lr_date'               => $request->input('lr_date'),
            'lr_no'                 => $request->input('lr_no'),
            'transport_name'        => $request->input('transport_name'),
            'no_of_boxes'           => $request->input('no_of_boxes'),
            'lr_amount'             => $request->input('lr_amount'),
            'attachment'            => implode(',', $attachments),
            'wa_is_processed'       => 1,
            'add_status'            => 1,
            'zoho_attachment_upload'=> 0,           // ⬅️ default 0
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        // 👉 Zoho part start:
        $zohoInvoiceId = $invoice->zoho_invoice_id ?? null;   // <-- tumhara actual column
        $zohoUploaded  = 0;                                   // assume failed

        if ($localAttachmentPath && $zohoInvoiceId) {
            try {
                /** @var \App\Http\Controllers\ZohoController $zoho */
                $zoho = app(\App\Http\Controllers\ZohoController::class);
                $result = $zoho->uploadInvoiceAttachmentToZoho($zohoInvoiceId, $localAttachmentPath);

                // Expected success:
                // {
                //   "code": 0,
                //   "message": "Your file has been successfully attached to the invoice."
                // }
                if (is_array($result) && isset($result['code']) && (int)$result['code'] === 0) {
                    $zohoUploaded = 1;
                }
            } catch (\Throwable $e) {
                \Log::error('Zoho upload from OrderLogisticsController failed: ' . $e->getMessage(), [
                    'invoice_no'       => $invoiceNo,
                    'zoho_invoice_id'  => $zohoInvoiceId,
                    'file'             => $localAttachmentPath,
                ]);
                // Error pe flow break nahi kar rahe, sirf log kar rahe hain
            }
        }

        // ✅ Zoho upload result ke basis pe column update karo
        DB::table('order_logistics')
            ->where('id', $logisticId)
            ->update([
                'zoho_attachment_upload' => $zohoUploaded,
                'updated_at'             => now(),
            ]);
        // 👉 Zoho part End

        // 🔽 WhatsApp Notification Logic (same as before)
        $pdfURL         = $this->getInvoicePdfURL($invoiceNo);
        $buttonVariable = basename($pdfURL);
        $attachmentURL  = $attachments[0] ?? null;
        $isPdf          = preg_match('/\.pdf$/i', $attachmentURL);

        $whatsAppWebService = new whatsAppWebService();
        $media_id = $isPdf ? $whatsAppWebService->uploadMedia($attachmentURL) : null;
        $templateName = $isPdf ? 'utility_logistic_fresh_pdfs' : 'utility_logistic_fresh';

        $user = User::where('id', $invoice->user_id)->first();
        $managerPhone = $this->getManagerPhone($user->manager_id);

        $whatsappMessage = [
            'name' => $templateName,
            'language' => 'en_US',
            'components' => [
                [
                    'type' => 'header',
                    'parameters' => $isPdf
                        ? [[
                            'type' => 'document',
                            'document' => [
                                'id' => $media_id['media_id'],
                                'filename' => 'Order Logistic Notification'
                            ]
                        ]]
                        : [[
                            'type' => 'image',
                            'image' => ['link' => $attachmentURL]
                        ]],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $user->name ?? 'Customer'],
                        ['type' => 'text', 'text' => $invoiceNo],
                        ['type' => 'text', 'text' => \Carbon\Carbon::parse($invoice->created_at)->format('d-m-Y')],
                        ['type' => 'text', 'text' => $request->transport_name . ' (LR No: ' . $request->lr_no . ')'],
                        ['type' => 'text', 'text' => $request->lr_date],
                        ['type' => 'text', 'text' => $request->no_of_boxes],
                    ],
                ],
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [
                        ['type' => 'text', 'text' => $buttonVariable],
                    ],
                ],
            ],
        ];

        foreach ([$user->phone, $managerPhone] as $recipient) {
            $whatsAppWebService->sendTemplateMessage($recipient, $whatsappMessage);
        }

        return redirect()->route('order.logistics')->with('success', 'Logistic record added successfully!');
    }


    public function __store(Request $request, $encrypted_invoice_no)
    {
        $invoiceNo = Crypt::decrypt($encrypted_invoice_no);
    
        // 🔍 Get invoice record from DB
        $invoice = DB::table('invoice_orders')->where('invoice_no', $invoiceNo)->first();
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found.');
        }
    
        $partyCode = $invoice->party_code ?? null;
    
        // 📦 Upload and save attachment locally
        $attachments = [];
        $localAttachmentPath = null;
    
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = public_path('uploads/cw_acetools');
    
                if (!file_exists($filePath)) {
                    mkdir($filePath, 0755, true);
                }
    
                $file->move($filePath, $fileName);
                $fileUrl = 'https://mazingbusiness.com/public/uploads/cw_acetools/' . $fileName;
                $attachments[] = $fileUrl;
    
                // Save path for Zoho (only first one)
                if (!$localAttachmentPath) {
                    $localAttachmentPath = $filePath . '/' . $fileName;
                }
            }
        }
    
        // 💾 Save to order_logistics table
        DB::table('order_logistics')->insert([
            'invoice_no'       => $invoiceNo,
            'party_code'       => $partyCode,
            'order_no'         => $invoiceNo,
            'lr_date'          => $request->input('lr_date'),
            'lr_no'            => $request->input('lr_no'),
            'transport_name'   => $request->input('transport_name'),
            'no_of_boxes'      => $request->input('no_of_boxes'),
            'lr_amount'        => $request->input('lr_amount'),
            'attachment'       => implode(',', $attachments),
            'wa_is_processed'  => 1,
            'add_status'       => 1,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

         // 👉 Zoho part start:
            $zohoInvoiceId = $invoice->zoho_invoice_id ?? null;   // <-- apka actual column
            if ($localAttachmentPath && $zohoInvoiceId) {
                try {
                    /** @var \App\Http\Controllers\ZohoController $zoho */
                    $zoho = app(ZohoController::class);
                    $zoho->uploadInvoiceAttachmentToZoho($zohoInvoiceId, $localAttachmentPath);
                } catch (\Throwable $e) {
                    \Log::error('Zoho upload from OrderLogisticsController failed: '.$e->getMessage(), [
                        'invoice_no' => $invoiceNo,
                        'zoho_invoice_id' => $zohoInvoiceId,
                        'file' => $localAttachmentPath,
                    ]);
                    // Error pe flow break nahi kar rahe, sirf log kar rahe hain
                }
            }
            // 👉 Zoho part End
    
        // 🔽 WhatsApp Notification Logic
        $pdfURL         = $this->getInvoicePdfURL($invoiceNo);
        $buttonVariable = basename($pdfURL);
        $attachmentURL  = $attachments[0] ?? null;
        $isPdf          = preg_match('/\.pdf$/i', $attachmentURL);
    
        $whatsAppWebService = new whatsAppWebService();
        $media_id = $isPdf ? $whatsAppWebService->uploadMedia($attachmentURL) : null;
        $templateName = $isPdf ? 'utility_logistic_fresh_pdfs' : 'utility_logistic_fresh';
    
        $user = User::where('id', $invoice->user_id)->first();
        $managerPhone = $this->getManagerPhone($user->manager_id);
    
        $whatsappMessage = [
            'name' => $templateName,
            'language' => 'en_US',
            'components' => [
                [
                    'type' => 'header',
                    'parameters' => $isPdf
                        ? [[
                            'type' => 'document',
                            'document' => [
                                'id' => $media_id['media_id'],
                                'filename' => 'Order Logistic Notification'
                            ]
                        ]]
                        : [[
                            'type' => 'image',
                            'image' => ['link' => $attachmentURL]
                        ]],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $user->name ?? 'Customer'],
                        ['type' => 'text', 'text' => $invoiceNo],
                        ['type' => 'text', 'text' => \Carbon\Carbon::parse($invoice->created_at)->format('d-m-Y')],
                        ['type' => 'text', 'text' => $request->transport_name . ' (LR No: ' . $request->lr_no . ')'],
                        ['type' => 'text', 'text' => $request->lr_date],
                        ['type' => 'text', 'text' => $request->no_of_boxes],
                    ],
                ],
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [
                        ['type' => 'text', 'text' => $buttonVariable],
                    ],
                ],
            ],
        ];
    
        foreach ([$user->phone, $managerPhone] as $recipient) {
            $whatsAppWebService->sendTemplateMessage($recipient, $whatsappMessage);
        }
    
        return redirect()->route('order.logistics')->with('success', 'Logistic record added successfully!');
    }

    public function edit($invoice_id)
    {
        $logistic = DB::table('order_logistics')->where('invoice_no',decrypt($invoice_id))->first();


        if (!$logistic) {
            return redirect()->route('order.logistics')->with('error', 'Record not found.');
        }
        // echo "<pre>";
        // print_r($logistic->attachment);
        // die();
        

        return view('backend.order_logistics.edit', compact('logistic'));
    }

    public function update(Request $request, $invoice_no, $id)
    {
        $plainInvoiceNo = decrypt($invoice_no);

        $logistic = DB::table('order_logistics')
            ->where('invoice_no', $plainInvoiceNo)
            ->where('id', $id)
            ->first();

        if (!$logistic) {
            return redirect()->route('order.logistics')->with('error', 'Logistic record not found.');
        }

        // Existing attachments
        $currentAttachments = $logistic->attachment ? explode(',', $logistic->attachment) : [];

        // Remove selected attachments
        if ($request->filled('remove_indexes')) {
            $removeIndexes = explode(',', $request->input('remove_indexes'));
            foreach ($removeIndexes as $index) {
                unset($currentAttachments[$index]);
            }
            $currentAttachments = array_values($currentAttachments);
        }

        // ✅ Always init
        $newAttachments = [];

        // New uploads
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = public_path('uploads/cw_acetools');

                if (!file_exists($filePath)) {
                    mkdir($filePath, 0755, true);
                }

                $file->move($filePath, $fileName);
                $newAttachments[] = 'https://mazingbusiness.com/public/uploads/cw_acetools/' . $fileName;
            }
        }

        // Final attachment list
        $updatedAttachments = array_merge($newAttachments, $currentAttachments);
        $attachmentString   = implode(',', $updatedAttachments);

        // Local DB update
        DB::table('order_logistics')
            ->where('invoice_no', $plainInvoiceNo)
            ->where('id', $id)
            ->update([
                'lr_date'     => $request->input('lr_date'),
                'lr_no'       => $request->input('lr_no'),
                'no_of_boxes' => $request->input('no_of_boxes'),
                'lr_amount'   => $request->input('lr_amount'),
                'attachment'  => $attachmentString,
            ]);

        // 🔗 Invoice + Zoho ID
        $invoice = DB::table('invoice_orders')
            ->where('invoice_no', $plainInvoiceNo)
            ->first();

        $zohoInvoiceId = $invoice->zoho_invoice_id ?? null;

        if ($zohoInvoiceId && !empty($newAttachments)) {
            try {
                /** @var \App\Http\Controllers\ZohoController $zoho */
                $zoho = app(\App\Http\Controllers\ZohoController::class);

                // 1️⃣ Pehle Zoho se purana attachment delete karo
                $deleteResult = $zoho->deleteInvoiceAttachmentFromZoho($zohoInvoiceId);
                \Log::info('Zoho delete result for logistic update', [
                    'invoice_no'      => $plainInvoiceNo,
                    'zoho_invoice_id' => $zohoInvoiceId,
                    'result'          => $deleteResult,
                ]);

                // 2️⃣ Ab naya attachment upload kar do (sirf first new attachment use kar rahe)
                $firstNewUrl = $newAttachments[0];

                $pathPart = parse_url($firstNewUrl, PHP_URL_PATH);
                $fileName = basename($pathPart);
                $localPath = public_path('uploads/cw_acetools/' . $fileName);

                $uploadResult = $zoho->uploadInvoiceAttachmentToZoho($zohoInvoiceId, $localPath);

                $zohoUploaded = 0;
                if (is_array($uploadResult) && isset($uploadResult['code']) && (int) $uploadResult['code'] === 0) {
                    $zohoUploaded = 1;
                }

                DB::table('order_logistics')
                    ->where('id', $id)
                    ->update([
                        'zoho_attachment_upload' => $zohoUploaded,
                        'updated_at'             => now(),
                    ]);

            } catch (\Throwable $e) {
                \Log::error('Zoho DELETE/UPLOAD from OrderLogisticsController@update failed: ' . $e->getMessage(), [
                    'invoice_no'      => $plainInvoiceNo,
                    'zoho_invoice_id' => $zohoInvoiceId,
                ]);
            }
        }

        return redirect()->route('order.logistics')->with('success', 'Logistic record updated successfully!');
    }

    
    public function sendLogisticWhatsapp($invoice_no,$id){

        // echo decrypt($invoice_no)." ". $id;
        // die();
       $this->whatsAppWebService = new WhatsAppWebService();
        $logistics = DB::table('order_logistics')
            ->join('addresses', 'order_logistics.party_code', '=', 'addresses.acc_code')
            ->Join('order_bills', 'order_logistics.invoice_no', '=', 'order_bills.invoice_no') // Join with order_bills table
            ->select(
                'order_logistics.id as order_logistics_id',
                'order_logistics.party_code',
                'order_logistics.order_no',
                'order_logistics.invoice_no',
                'order_logistics.lr_no',
                'order_logistics.lr_date',
                'order_logistics.no_of_boxes',
                'order_logistics.payment_type',
                'order_logistics.lr_amount',
                'order_logistics.attachment',
                DB::raw('SUBSTRING_INDEX(order_logistics.lr_no, " - ", 1) as transport_name'),
                'addresses.company_name',
                'addresses.address',
                'addresses.address_2',
                'addresses.city',
                'addresses.state_id',
                'addresses.country_id',
                'addresses.gstin',
                'addresses.phone',
                'addresses.user_id',
                'order_bills.invoice_date' // Include invoice_date
            )
            ->where('order_logistics.id', $id) // Add table prefix here
            ->where('order_logistics.invoice_no', decrypt($invoice_no)) // Add table prefix here
            ->whereNotNull('order_logistics.lr_no') // Ensure `lr_no` is not null
            ->where('order_logistics.lr_no', '!=', '') // Ensure `lr_no` is not blank
            ->first();

        $billDataController=new BillsDataController();
        $invoice_url= $billDataController->generateBillInvoicePdfURL($invoice_no);
        $buttonVariable=basename($invoice_url);

        //get first attatchment
         $attachments = explode(',', $logistics->attachment);
         $logistics->attachment = $attachments[0] ?? null;
         //pdf+image combine start
         $isPdf = preg_match('/\.pdf$/i', $logistics->attachment);
         $media_id = $isPdf ? $this->whatsAppWebService->uploadMedia($logistics->attachment) : null;
         
         $templateName = $isPdf ? 'utility_logistic_fresh_pdfs' : 'utility_logistic_fresh';
         //pdf+image combine end

        // Prepare WhatsApp message template
        $templateData = [
            'name' => $templateName,
            'language' => 'en_US',
            'components' => [
                
                [
                    'type' => 'header',
                    'parameters' => $isPdf 
                        ? [[
                            'type' => 'document',
                            'document' => [
                                'id' => $media_id['media_id'],
                                'filename' => 'Order Logistic Notification' // Adding filename for PDFs
                            ]
                        ]]
                        : [[
                            'type' => 'image',
                            'image' => ['link' => $logistics->attachment]
                        ]], // Image handling
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $logistics->company_name ?: 'N/A'], // Customer name
                        ['type' => 'text', 'text' => $logistics->invoice_no ?: 'N/A'], // Invoice 
                        ['type' => 'text', 'text' => $logistics->invoice_date ?: 'N/A'], // Invoice date
                        ['type' => 'text', 'text' => $logistics->transport_name ?: 'N/A'], // Transport name - lr number                           
                        ['type' => 'text', 'text' => $logistics->lr_date ?: 'N/A'], // LR date
                        ['type' => 'text', 'text' => $logistics->no_of_boxes ?: '0.00'], // No. of boxes
                    ],
                ],
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [
                        ['type' => 'text', 'text' => $buttonVariable],
                    ],
                ],
            ],
        ];

        $user = DB::table('users')->where('id', $logistics->user_id)->first();
        $managerPhone = $this->getManagerPhone($user->manager_id);

        $to = [$user->phone,$managerPhone];
        //$to=['7044300330'];
        

        foreach ($to as $recipient) {
            $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($recipient, $templateData);

            // Log the response for debugging
            Log::info('WhatsApp Response', ['recipient' => $recipient, 'response' => $jsonResponse]);

            // Optionally check for errors in the API response
            if (isset($jsonResponse['messages'][0]['message_status']) && $jsonResponse['messages'][0]['message_status'] !== 'accepted') {
                return response()->json([
                    'success' => false,
                    'error' => 'Message was not accepted by WhatsApp API.',
                    'details' => $jsonResponse
                ], 500);
            }
        }
        return response()->json(['success' => true,'message'=>'PDF sent successfully via WhatsApp.']);

    }

    //Temporary Function  start
     public function oldtempAllOrders(Request $request) {
        CoreComponentRepository::instantiateShopRepository();

        $date            = $request->date;
        $sort_search     = null;
        $delivery_status = null;
        $payment_status  = '';

        // Fetch the admin user ID efficiently
        $admin_user_id = User::where('user_type', 'admin')->value('id');

        // Fetch distinct Salezing Order Punch Status responses
        $salzing_statuses = DB::table('salezing_logs')->distinct()->pluck('response');

        // Optimize the update query to avoid large-scale updates
        DB::table('orders')
            ->whereIn('code', function ($query) {
                $query->select('code')->from('order_approvals')->where('status', 'Approved');
            })
            ->update(['delivery_status' => 'Approved']);

        // Start Query Builder
        $orders = Order::query()
            ->select([
                'orders.id',
                'orders.code',
                'orders.combined_order_id',
                'orders.user_id',
                'orders.seller_id',
                'orders.delivery_status',
                'orders.payment_status',
                'orders.created_at',
                'addresses.company_name',
                DB::raw("(SELECT response FROM salezing_logs WHERE BINARY salezing_logs.code = BINARY orders.code LIMIT 1) as salezing_response"),
                DB::raw("(SELECT status FROM salezing_logs WHERE BINARY salezing_logs.code = BINARY orders.code LIMIT 1) as salezing_status"),
                'users.warehouse_id',
                'manager_users.name as manager_name',
                'warehouses.name as warehouse_name'
            ])
            ->join('addresses', 'orders.address_id', '=', 'addresses.id')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->leftJoin('users as manager_users', 'users.manager_id', '=', 'manager_users.id')
            ->leftJoin('warehouses', 'users.warehouse_id', '=', 'warehouses.id')
            ->orderByDesc('orders.id');

        // Apply permission-based filters early
        if (Route::currentRouteName() === 'inhouse_orders.index' && Auth::user()->can('view_inhouse_orders')) {
            $orders->where('orders.seller_id', $admin_user_id);
        } elseif (Route::currentRouteName() === 'seller_orders.index' && Auth::user()->can('view_seller_orders')) {
            $orders->where('orders.seller_id', '!=', $admin_user_id);
        }

        // Apply search filters efficiently
        if ($request->filled('search')) {
            $orders->where('orders.code', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('payment_status')) {
            $orders->where('orders.payment_status', $request->payment_status);
            $payment_status = $request->payment_status;
        }
        if ($request->filled('delivery_status')) {
            $orders->where('orders.delivery_status', $request->delivery_status);
            $delivery_status = $request->delivery_status;
        }
        if ($request->filled('date')) {
            $dateRange = explode(" to ", $request->date);
            $orders->whereBetween('orders.created_at', [
                date('Y-m-d', strtotime($dateRange[0])) . ' 00:00:00',
                date('Y-m-d', strtotime($dateRange[1])) . ' 23:59:59'
            ]);
        }

        // Optimize filtering Salezing Order Punch Status using subquery
        if ($request->filled('salzing_status')) {
            $orders->whereRaw("(SELECT response FROM salezing_logs WHERE BINARY salezing_logs.code = BINARY orders.code LIMIT 1) = ?", [$request->salzing_status]);
        }

        // Paginate and return the result
        $orders = $orders->paginate(15);
        // echo "<pre>";
        // print_r($orders->toArray());
        // die();

        return view('backend.sales.index', compact('orders', 'sort_search', 'payment_status', 'delivery_status', 'date', 'salzing_statuses'));
    }
}