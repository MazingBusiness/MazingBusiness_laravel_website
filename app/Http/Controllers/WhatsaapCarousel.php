<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Services\WhatsappCarouselService;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Jobs\SendWhatsAppCarouselJob;



use App\Models\Category;
use App\Models\User;
use App\Models\Address;
use App\Models\Warehouse;
use App\Models\Product;


class WhatsaapCarousel extends Controller
{


    /** Graph API version */
    protected string $graphVersion = 'v23.0';

    /** Given creds */
    protected string $accessToken   = 'EAAPUmpU2NucBO8we70yBZAOMH2J8dW1ZCKMZBFCOVey6muS6seQuoRg4BZAtZCcqqIddK1MUmZC62G0xbFmXA2tDvobSuhV3StEcPK1PbLGQQE8kpJwnZBj5uFDLELlwBli7MBwlrSkD3Vbyn6VNEAQ0PpcItd7qDhO1opb45uAhmZADqjEC3aAeaaSZC0a0knyxb';
    protected string $phoneNumberId = '147572895113819';
    protected string $appId         = '1078185323542247';
    protected string $wabaId        = '171710262688364';

    /** Demo image */
    protected string $defaultImageLink = 'https://mazingbusiness.com/public/uploads/all/fRDJWDaZZqUjETWXHtdejKAmf2voEAXvUIUUeA8u.jpg';

    /* ===================== ROUTES ===================== */

    public function findProductByPartNo(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));
            if ($q === '') {
                return response()->json(['ok'=>false, 'message'=>'Part number is required.'], 422);
            }

            // Exact match first; adjust if your column is named differently
            $product = Product::query()
                ->select('id','name','slug','thumbnail_img','part_no')
                ->where('part_no', $q)
                ->first();

            if (!$product) {
                // fallback: case-insensitive LIKE (optional)
                $product = Product::query()
                    ->select('id','name','slug','thumbnail_img','part_no')
                    ->where('part_no', 'LIKE', $q)
                    ->first();
            }

            if (!$product) {
                return response()->json(['ok'=>false, 'message'=>'No product found for this part number.'], 404);
            }

            // Build absolute image URL using your helper; fallback if missing
            $imageUrl = '';
            try { $imageUrl = uploaded_asset($product->thumbnail_img); } catch (\Throwable $e) { $imageUrl = ''; }

            // Optional default/fallback image if no thumbnail
            if (!$imageUrl) {
                $imageUrl = $this->defaultImageLink ?? '';
            }

            return response()->json([
                'ok' => true,
                'product' => [
                    'id'      => (int) $product->id,
                    'name'    => (string) $product->name,   // used in BODY {{1}}
                    'slug'    => (string) $product->slug,   // used in URL {{1}}
                    'part_no' => (string) ($product->part_no ?? ''),
                    'image'   => (string) $imageUrl,        // used as media link
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'message'=>$e->getMessage()], 500);
        }
    }


    public function createTemplateForm()
    {
        return view('backend.marketing.whatsapp_carousel.create');
    }


    public function listPage()
    {
        $wa  = new \App\Services\WhatsappCarouselService();

        // PHP 7.x friendly: positional params (int $limit, ?string $name)
        $res = $wa->listTemplates(50, null);

        if (!($res['ok'] ?? false)) {
            return view('backend.marketing.whatsapp_carousel.index', [
                'templates' => [],
            ])->with('wa_templates_error', data_get($res, 'error.error_user_msg', 'Failed to load templates.'));
        }

        $raw = (array) data_get($res, 'data.data', []);
        $templates = [];

        foreach ($raw as $t) {
            $components = (array) ($t['components'] ?? []);
            $carousel   = null;

            // Find CAROUSEL component (case-insensitive)
            foreach ($components as $c) {
                if (strtoupper((string) ($c['type'] ?? '')) === 'CAROUSEL') {
                    $carousel = $c;
                    break;
                }
            }

            $cards = (array) ($carousel['cards'] ?? []);
            $count = is_array($cards) ? count($cards) : 0;

            // Keep only templates with at least 1 card
            if ($count > 0) {
                // (Optional) attach count/format if you want to use in Blade
                $t['card_count'] = $count;

                // Try to derive header format from first card's HEADER component
                $headerFormat = '-';
                if (!empty($cards[0]['components'])) {
                    foreach ((array) $cards[0]['components'] as $cc) {
                        if (strtoupper((string) ($cc['type'] ?? '')) === 'HEADER') {
                            $headerFormat = strtoupper((string) ($cc['format'] ?? '-'));
                            break;
                        }
                    }
                }
                $t['header_format'] = $headerFormat;

                $templates[] = $t;
            }
        }

        return view('backend.marketing.whatsapp_carousel.index', compact('templates'))
                ->with('wa_templates_ok', 'Templates loaded.');
    }



     /**
     * Create MARKETING Media-Card Carousel template using Resumable Upload (returns APPROVAL submission).
     * GET/POST /wa/carousel/create-template
     */
    public function createTemplate(Request $request)
    {
        try {
            $wa = new WhatsappCarouselService();

            // 1) Inputs
            $language     = $request->input('language', 'en');
            $headerFormat = strtoupper($request->input('header_format', 'IMAGE'));
            if (!in_array($headerFormat, ['IMAGE', 'VIDEO'], true)) {
                $headerFormat = 'IMAGE';
            }

            // Helpers
            $normalize = function ($s) {
                // normalize punctuation + remove Blade escape (@{{ -> {{ , @{ -> {)
                $s = (string)$s;
                $s = str_replace(['—','’','“','”','…'], ['-',"'",'"','"','...'], $s);
                $s = str_replace(['@{{','@{'], ['{{','{'], $s);
                return trim($s);
            };
            $varNums = function (string $text): array {
                preg_match_all('/\{\{\s*(\d+)\s*\}\}|\{\s*(\d+)\s*\}/', $text, $m);
                $nums = [];
                foreach ($m[1] as $i => $n1) {
                    $n = $n1 !== '' ? (int)$n1 : (int)($m[2][$i] ?? 0);
                    if ($n > 0) $nums[$n] = true;
                }
                return array_keys($nums); // unique, unsorted
            };

            // Top body text (make sure it’s plain {{n}} without @)
            $topBody = $normalize($request->input('body', '')) ?:
                'Hi {{1}} {{2}}, thanks for connecting with Mazing Business. Explore today\'s featured tools and offers curated specially for you.';

            // Top examples
            $topExamples = array_values(array_filter(
                (array)$request->input('body_example', ['Burhan', 'Immani']),
                fn($v) => trim((string)$v) !== ''
            ));
            $topVarSet  = $varNums($topBody);
            if (count($topVarSet) > 0) {
                $need = max($topVarSet);
                for ($i = count($topExamples); $i < $need; $i++) {
                    $topExamples[] = 'Sample '.($i+1);
                }
                $topExamples = array_slice($topExamples, 0, $need);
            }

            // 2) Cards input
            $cardsInput = $request->input('cards', []);
            if (empty($cardsInput) || !is_array($cardsInput)) {
                // Default seed (with two variables)
                $cardsInput = [
                    [
                        'header_link'      => 'https://mazingbusiness.com/public/uploads/all/fRDJWDaZZqUjETWXHtdejKAmf2voEAXvUIUUeA8u.jpg',
                        'body_text'        => 'Product highlight: {{1}} — reliable performance. Price {{2}} including GST.',
                        'body_example'     => ['Precision finishing', '₹1499'],
                        'url_btn_example'  => 'https://mazingbusiness.com/product/xtrive-angle-grinder-dw801',
                        'quick_reply_text' => 'Interested',
                    ],
                    [
                        'header_link'      => 'https://mazingbusiness.com/public/uploads/all/sAdom0t5BxZBEnzvwYJsm3D3XHgxkxTJ8rpPuwEj.jpg',
                        'body_text'        => 'Product highlight: {{1}} — reliable performance. Price {{2}} including GST.',
                        'body_example'     => ['Heavy-duty use', '₹1799'],
                        'url_btn_example'  => 'https://mazingbusiness.com/product/xtrive-angle-grinder-dw802',
                        'quick_reply_text' => 'Interested',
                    ],
                ];
            }
            $cardsInput = array_values(array_slice($cardsInput, 0, 10));

            // 3) Template name
            $rawName = trim((string)$request->input('name')) ?:
                'mb_media_carousel_v'.date('Ymd_His').'_'.substr(bin2hex(random_bytes(3)), 0, 6);
            $tplName = strtolower(preg_replace('/[^a-z0-9_]/', '_', $rawName));

            // 4) Build CAROUSEL cards
            $cardsComponents = [];
            foreach ($cardsInput as $i => $c) {
                $link = (string)($c['header_link'] ?? '');
                if (!filter_var($link, FILTER_VALIDATE_URL)) {
                    return redirect()->back()->withInput()->with('wa_create_error', [
                        'message' => "cards[$i].header_link must be a valid URL."
                    ]);
                }

                // Create 4:: header handle
                $handle4 = $wa->makeHeaderHandleFromLink($link);

                // Card body + examples
                $cardBodyText = $normalize((string)($c['body_text'] ?? ''));
                if ($cardBodyText === '') {
                    $cardBodyText = 'Product highlight: {{1}} — reliable performance. Price {{2}} including GST.';
                }

                $nums = $varNums($cardBodyText);
                $need = $nums ? max($nums) : 0;

                $cardBodyExample = array_values(array_map('trim', (array)($c['body_example'] ?? [])));
                // pad examples to the number of variables
                for ($k = count($cardBodyExample); $k < $need; $k++) {
                    $cardBodyExample[] = 'Sample '.($k+1);
                }
                if ($need > 0) {
                    $cardBodyExample = array_slice($cardBodyExample, 0, $need);
                } else {
                    $cardBodyExample = [];
                }

                // URL example
                $urlExample = (string)($c['url_btn_example'] ?? '');
                if ($urlExample === '' || !preg_match('~^https?://~i', $urlExample)) {
                    $urlExample = 'https://mazingbusiness.com/product/sample-slug-'.($i+1);
                }

                // Quick reply text (default Interested)
                $quickReply = trim((string)($c['quick_reply_text'] ?? 'Interested'));

                // Buttons: URL + QUICK_REPLY
                $buttons = [
                    [
                        'type'    => 'URL',
                        'text'    => 'View',
                        'url'     => 'https://mazingbusiness.com/product/{{1}}',
                        'example' => $urlExample,
                    ],
                ];
                if ($quickReply !== '') {
                    $buttons[] = ['type'=>'QUICK_REPLY','text'=>$quickReply];
                }

                $card = [
                    'components' => [
                        [
                            'type'   => 'HEADER',
                            'format' => $headerFormat,
                            'example'=> ['header_handle' => [ $handle4 ]],
                        ],
                    ],
                ];

                // BODY (with examples if variables are present)
                $bodyComp = ['type'=>'BODY','text'=>$cardBodyText];
                if ($need > 0) {
                    $bodyComp['example'] = ['body_text' => [ $cardBodyExample ]]; // one set of examples matching {1..n}
                }
                $card['components'][] = $bodyComp;

                // BUTTONS
                $card['components'][] = [
                    'type'    => 'BUTTONS',
                    'buttons' => $buttons,
                ];

                $cardsComponents[] = $card;
            }

            // 5) Final payload
            $topBodyComponent = ['type'=>'BODY','text'=>$topBody];
            if (!empty($topVarSet)) {
                $need = max($topVarSet);
                $topBodyComponent['example'] = ['body_text' => [ array_slice($topExamples, 0, $need) ]];
            }

            $createPayload = [
                'name'                  => $tplName,
                'category'              => 'MARKETING',
                'allow_category_change' => true,
                'language'              => $language,
                'components'            => [
                    $topBodyComponent,
                    ['type'=>'CAROUSEL','cards'=>$cardsComponents],
                ],
            ];

            // 6) Submit to Meta
            $resCreate = $wa->createTemplateWithPayload($createPayload);

            if (($resCreate['ok'] ?? false) === true) {
                return redirect()
                    ->route('wa.carousel.create-template.form')
                    ->with('wa_create_success', [
                        'template_name' => $createPayload['name'],
                        'id'       => data_get($resCreate, 'result.id'),
                        'status'   => data_get($resCreate, 'result.status'),
                        'category' => data_get($resCreate, 'result.category'),
                    ]);
            }

            $msg = data_get($resCreate, 'error.error_user_msg')
                ?? data_get($resCreate, 'error.error.message')
                ?? 'Template creation failed.';

            return redirect()->back()->withInput()->with('wa_create_error', [
                'message' => $msg,
                'debug'   => $resCreate,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('wa_create_error', [
                'message' => $e->getMessage(),
            ]);
        }
    }








    /**
     * List templates (optional filter ?name=)
     */
    public function listTemplates(Request $request)
    {
        $wa = new WhatsappCarouselService();

        $limit = (int) $request->input('limit', 50);
        if ($limit <= 0) $limit = 50;

        $name = $request->has('name') ? trim((string) $request->input('name')) : null;
        if ($name === '') $name = null;

        try {
            // PHP 7-compatible: positional parameters
            $res = $wa->listTemplates($limit, $name);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => ['message' => $e->getMessage()],
            ], 500);
        }

        if (empty($res['ok'])) {
            return response()->json($res, 422);
        }

        return response()->json($res);
    }


    /**
     * Send demo (after APPROVED).
     * POST /wa/carousel/send-demo
     * Optional params:
     * - header_format: IMAGE | VIDEO (must match the template)
     * - use_media_id: true|false (if true, we upload via /media and send by ID)
     */

    public function getStatesByManager(Request $request)
    {
        $managerId = (int) $request->query('manager_id', 0);
        if ($managerId <= 0) {
            return response()->json([]);
        }

        // Distinct states for customers under this manager with counts
        $rows = User::query()
            ->select('state', DB::raw('COUNT(*) as cnt'))
            ->where('user_type', 'customer')
            ->where('manager_id', $managerId)
            ->whereNotNull('state')
            ->where('state', '<>', '')
            ->groupBy('state')
            ->orderBy('state')
            ->get();

        $out = $rows->map(function ($r) {
            return ['state' => (string) $r->state, 'count' => (int) $r->cnt];
        })->values();

        return response()->json($out);
    }


    public function sendFromForm(Request $request)
    {
        try {
            // ----- BASIC INPUTS -----
            $templateName = trim((string) $request->input('template_name', ''));
            $language     = trim((string) $request->input('language', 'en')) ?: 'en';
            $toRaw        = trim((string) $request->input('to', ''));          // optional if using filters
            $topParams    = (array) $request->input('top_params', []);         // locked by blade
            $cardsIn      = (array) $request->input('cards', []);              // locked by blade

            // Filters
            $warehouseId  = (int) $request->input('warehouse_id', 0);
            $managerId    = (int) $request->input('manager_id', 0);
            $state        = trim((string) $request->input('state', ''));       // NEW

            if ($templateName === '') {
                return response()->json(['ok'=>false,'message'=>'Template is required.'], 422);
            }
            if (empty($cardsIn)) {
                return response()->json(['ok'=>false,'message'=>'At least one card is required.'], 422);
            }

            // ----- BUILD COMPONENTS (as stored in queue "content") -----
            $components = [];

            // Top BODY params
            if (!empty($topParams)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => array_map(function($t){
                        return ['type'=>'text','text'=>(string)$t];
                    }, $topParams),
                ];
            }

            // Cards (use image link from product thumbnail)
            $cardsOut = [];
            $cardsIn  = array_values($cardsIn); // normalize indices 0..n-1
            foreach ($cardsIn as $i => $c) {
                $cardComps = [];

                $mediaLink = trim((string)($c['media_link'] ?? ''));
                if ($mediaLink === '') {
                    return response()->json(['ok'=>false,'message'=>"Card #".($i+1).": media link (product thumbnail) is required."], 422);
                }
                $cardComps[] = [
                    'type' => 'header',
                    'parameters' => [
                        ['type' => 'image', 'image' => ['link' => $mediaLink]],
                    ],
                ];

                $bodyParams = array_map(function($t){
                    return ['type'=>'text','text'=>(string)$t];
                }, (array)($c['body_params'] ?? []));
                if (!empty($bodyParams)) {
                    $cardComps[] = [
                        'type' => 'body',
                        'parameters' => $bodyParams,
                    ];
                }

                $urlParam = trim((string)($c['url_button_param'] ?? ''));
                if ($urlParam !== '') {
                    $cardComps[] = [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => '0',
                        'parameters' => [
                            ['type' => 'text', 'text' => $urlParam],
                        ],
                    ];
                }

                $cardsOut[] = [
                    'card_index' => $i,
                    'components' => $cardComps,
                ];
            }

            $components[] = [
                'type'  => 'carousel',
                'cards' => $cardsOut,
            ];

            $templateData = [
                'name'       => $templateName,
                'language'   => $language,
                'components' => $components,
            ];

            // ----- RECIPIENTS (either explicit "to" or via filters) -----
            $recipients = [];

            // Normalize to E.164 (+91 if 10 digits)
            $toE164ify = function ($raw) {
                $raw = trim((string)$raw);
                if ($raw === '') return null;
                if ($raw[0] === '+') return $raw;
                $digits = preg_replace('/\D+/', '', $raw);
                if (strlen($digits) === 10) return '+91'.$digits;
                if (preg_match('/^\d{11,15}$/', $digits)) return '+'.$digits;
                return null;
            };

            if ($toRaw !== '') {
                $one = $toE164ify($toRaw);
                if (!$one) return response()->json(['ok'=>false,'message'=>'Invalid E.164 recipient.'], 422);
                $recipients = [$one];
            } else {
                if ($warehouseId <= 0) {
                    return response()->json(['ok'=>false,'message'=>'Select a Branch or enter a single recipient.'], 422);
                }

                $uq = User::query()
                    ->select('id','name','phone','warehouse_id','manager_id','state')
                    ->where('user_type', 'customer')                 // NEW: only customers
                    ->where('warehouse_id', $warehouseId)
                    ->whereNotNull('phone')
                    ->where('phone','<>','');

                if ($managerId > 0) {
                    $uq->where('manager_id', $managerId);           // optional manager filter
                }
                if ($state !== '') {
                    $uq->where('state', $state);                    // NEW: state filter
                }

                $users = $uq->limit(5000)->get(); // safety cap

                foreach ($users as $u) {
                    $ph = $toE164ify($u->phone);
                    if ($ph) $recipients[] = $ph;
                }

                // De-dupe phone numbers just in case
                $recipients = array_values(array_unique($recipients));

                if (empty($recipients)) {
                    return response()->json(['ok'=>false,'message'=>'No valid phone numbers found for selected filters.'], 422);
                }
            }

            // ----- QUEUE INSERTS -----
            $groupId = 'wa_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
            $now = now();

            $rows = [];
            foreach ($recipients as $to) {
                $rows[] = [
                    'group_id'       => $groupId,
                    'callback_data'  => $templateName,
                    'recipient_type' => 'individual',
                    'to_number'      => $to,
                    'type'           => 'template',
                    'file_url'       => '',
                    'file_name'      => 'carousel',
                    'content'        => json_encode($templateData),
                    'status'         => 'pending',
                    'response'       => '',
                    'msg_id'         => '',
                    'msg_status'     => '',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }

            DB::table('wa_sales_queue')->insert($rows);

            // ----- DISPATCH JOB FOR THIS GROUP -----
             SendWhatsAppCarouselJob::dispatch($groupId);

            return response()->json([
                'ok'         => true,
                'queued'     => true,
                'group_id'   => $groupId,
                'recipients' => count($recipients),
            ]);

        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'message'=>$e->getMessage()], 500);
        }
    }



    public function sendForm()
    {
        $wa  = new WhatsappCarouselService();
        $res = $wa->listTemplates(50, null); // keep PHP 7 friendly

        $templates = [];
        if (($res['ok'] ?? false)) {
            $raw = (array) data_get($res, 'data.data', []);
            foreach ($raw as $t) {
                if (strtoupper($t['status'] ?? '') !== 'APPROVED') continue;

                // Count cards
                $cardCount = 0;
                foreach ((array)($t['components'] ?? []) as $c) {
                    if (isset($c['type']) && strtoupper($c['type']) === 'CAROUSEL') {
                        $cardCount = is_array($c['cards'] ?? null) ? count($c['cards']) : 0;
                        break;
                    }
                }
                if ($cardCount < 1) continue;

                // Attach card_count (used by the blade) – keep full components for JS parsing
                $t['card_count'] = $cardCount;
                $templates[] = $t;
            }
        }

        // Products for product dropdown (auto-fill card body {{1}}, URL {{1}}, media_link)
        $products = Product::select('id', 'name', 'slug', 'thumbnail_img')
                    ->limit(1500)->get();

        $categories = Category::where('parent_id', 0)
            ->with('childrenCategories')
            ->get();

        // $products = Product::select('id', 'name', 'slug', 'thumbnail_img')
        //     ->where('current_stock', 1)
        //     ->latest()
        //     ->get();

        // Branches (warehouses) for the filter
        $warehouses = Warehouse::select('id','name')->orderBy('name')->get();

         // NEW: Total customers with valid phone
        $totalCustomers = User::where('user_type','customer')
            ->whereNotNull('phone')->where('phone','<>','')
            ->count();

            // echo $templates;
            // die();

        return view('backend.marketing.whatsapp_carousel.send', compact('templates','products','warehouses','totalCustomers'));
    }

    public function getManagersByWarehouse(Request $request)
    {
        // Fetch managers based on the selected warehouse
        $managers = User::join('staff', 'users.id', '=', 'staff.user_id')
        ->where('staff.role_id', 5)
        ->where('users.warehouse_id', $request->warehouse_id)  // Apply condition on users table
        ->select('users.*')
        ->get();

    
        return response()->json($managers); // Return managers as JSON response
    }




    private function getManagerPhone($managerId)
    {
        $managerData = DB::table('users')
            ->where('id', $managerId)
            ->select('phone')
            ->first();

        return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
    }
    
   // ---------- AJAX #1: INSERT ONLY (filters optional; if none -> send to ALL customers) ----------
    public function sendFromFormAjax(Request $request)
    {
        try {
            $templateName = trim((string) $request->input('template_name', ''));
            $language     = trim((string) $request->input('language', 'en')) ?: 'en';
            $toE164Raw    = trim((string) $request->input('to', '')); // optional
            $topParamsIn  = (array) $request->input('top_params', []); // literals or tokens
            $cardsIn      = array_values((array) $request->input('cards', []));

            // optional filters
            $warehouseId  = (int) $request->input('warehouse_id', 0);
            $managerId    = (int) $request->input('manager_id', 0);
            $state        = trim((string) $request->input('state', ''));

            if ($templateName === '') {
                return response()->json(['ok'=>false,'message'=>'Template is required.'], 422);
            }
            if (empty($cardsIn)) {
                return response()->json(['ok'=>false,'message'=>'At least one card is required.'], 422);
            }

            // ---- normalize top tokens ----
            $topTokens = [];
            foreach ($topParamsIn as $tp) { $topTokens[] = trim((string)$tp); }

            // E.164 helper
            $toE164ify = function ($raw) {
                $raw = trim((string)$raw);
                if ($raw === '') return null;
                if ($raw[0] === '+') return $raw;
                $digits = preg_replace('/\D+/', '', $raw);
                if (strlen($digits) === 10) return '+91'.$digits;
                if (preg_match('/^\d{11,15}$/', $digits)) return '+'.$digits;
                return null;
            };

            // Dynamic token detector
            $isDynamicToken = function ($s) {
                return (bool)preg_match('/^[a-zA-Z][a-zA-Z0-9_]*\.[a-zA-Z][a-zA-Z0-9_]*$/', $s);
            };

            // INR formatter
            $formatINR = function ($n) {
                $n = (float)$n;
                if ($n < 0) $n = 0;
                return '₹' . number_format($n, 0);
            };

            // Parse literal to price text (1799/₹1799 → ₹1799)
            $literalToPrice = function (?string $src) use ($formatINR) {
                $s = trim((string)$src);
                if ($s === '') return null;
                $digits = preg_replace('/[^\d.]/', '', $s);
                if ($digits !== '' && is_numeric($digits)) {
                    return $formatINR((float)$digits);
                }
                return $s;
            };

            // Top BODY param resolver (users.*, addresses.*, users.manager_id -> manager phone)
            $resolveTopParams = function(array $tokens, ?\App\Models\User $user, ?\App\Models\Address $addr, ?string $managerPhone) use ($isDynamicToken) {
                $out = [];
                foreach ($tokens as $tok) {
                    $tok = (string)$tok;
                    $val = '';
                    if ($tok !== '' && $isDynamicToken($tok)) {
                        [$tbl,$col] = explode('.', $tok, 2);
                        $tbl = strtolower($tbl);
                        $col = (string)$col;

                        if ($tbl === 'users' && $user) {
                            if ($col === 'manager_id' || $col === 'manger_id') {
                                $val = (string)($managerPhone ?? ($user->manager_id ?? ''));
                            } else {
                                $val = (string)($user->{$col} ?? '');
                            }
                        } elseif ($tbl === 'addresses' && $addr) {
                            $val = (string)($addr->{$col} ?? '');
                        } else {
                            $val = '';
                        }
                    } else {
                        $val = $tok; // literal
                    }
                    $out[] = ['type'=>'text','text'=>$val];
                }
                return $out;
            };

            // ---- Collect slugs from form (URL {{1}} param) to preload products ----
            $slugs = [];
            foreach ($cardsIn as $c) {
                $slug = trim((string)($c['url_button_param'] ?? ''));
                if ($slug !== '') $slugs[] = $slug;
            }
            $slugs = array_values(array_unique($slugs));

            // ---- Preload products by slug (SAFE: only existing columns) ----
            $prodMap = [];
            if (!empty($slugs)) {
                $products = Product::whereIn('slug', $slugs)
                    ->get(['id','slug','name','mrp']); // removed non-existent columns
                foreach ($products as $p) { $prodMap[$p->slug] = $p; }
            }

            // Robust MRP getter (prefers DB mrp, then posted hidden mrp)
            $getMrp = function ($slug, $postedMrp = null) use ($prodMap) {
                $p = $slug ? ($prodMap[$slug] ?? null) : null;
                $mrpDb = $p ? (float)($p->mrp ?? 0) : 0.0;
                if ($mrpDb > 0) return $mrpDb;

                $mrpPosted = (float)($postedMrp ?? 0);
                if ($mrpPosted > 0) return $mrpPosted;

                return 0.0;
            };

            // Build per-user carousel (discount-aware, uses only MRP)
            $buildCarouselFor = function (? User $user) use ($cardsIn, $prodMap, $formatINR, $literalToPrice, $getMrp) {
                $discountPct = (float)($user->discount ?? 0);
                $outCards = [];

                foreach ($cardsIn as $i => $c) {
                    $mediaLink   = trim((string)($c['media_link'] ?? ''));
                    if ($mediaLink === '') continue;

                    $slug        = trim((string)($c['url_button_param'] ?? ''));
                    $priceSource = trim((string)($c['price_source'] ?? 'products.mrp')); // default MRP
                    $needsVar2   = (int)($c['needs_var2'] ?? 0) === 1;
                    $postedMrp   = isset($c['mrp']) ? (float)$c['mrp'] : null;

                    $mrpNumber   = $getMrp($slug, $postedMrp);

                    $product     = $slug !== '' ? ($prodMap[$slug] ?? null) : null;
                    $nameFromDb  = (string)($product->name ?? '');

                    // BODY {1}
                    $bodyParamsIn  = (array)($c['body_params'] ?? []);
                    $bp = [];
                    $v1 = trim((string)($bodyParamsIn[0] ?? ''));
                    if ($v1 === '' && $nameFromDb !== '') $v1 = $nameFromDb;
                    if ($v1 !== '') { $bp[] = ['type'=>'text','text'=>$v1]; }

                    // BODY {2}
                    if ($needsVar2) {
                        $src = strtolower($priceSource);
                        if ($src === 'products.mrp') {
                            $final = $mrpNumber - ($mrpNumber * $discountPct / 100.0);
                            if ($final < 0) $final = 0;
                            $bp[] = ['type'=>'text','text'=>$formatINR($final)];
                        } else {
                            $bp[] = ['type'=>'text','text'=>$literalToPrice($priceSource) ?? ''];
                        }
                    }

                    $components = [];
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'image', 'image' => ['link' => $mediaLink]],
                        ],
                    ];
                    if (!empty($bp)) {
                        $components[] = ['type'=>'body','parameters'=>$bp];
                    }
                    if ($slug !== '') {
                        $components[] = [
                            'type'       => 'button',
                            'sub_type'   => 'url',
                            'index'      => '0',
                            'parameters' => [ ['type'=>'text','text'=>$slug] ],
                        ];
                    }

                    $outCards[] = ['card_index'=>$i, 'components'=>$components];
                }

                return ['type'=>'carousel','cards'=>$outCards];
            };

            // ---- Prepare queue ----
            $groupId   = 'wa_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
            $now       = now();
            $queued    = 0;

            // A) Single recipient
            if ($toE164Raw !== '') {
                $to = $toE164ify($toE164Raw);
                if (!$to) return response()->json(['ok'=>false,'message'=>'Invalid E.164 recipient.'], 422);

                // Best-effort lookup user by phone
                $digits = preg_replace('/\D+/', '', $toE164Raw);
                $last10 = substr($digits, -10);
                $user   = User::query()
                            ->where('user_type','customer')
                            ->where(function($q) use($to,$digits,$last10){
                                $q->where('phone', $to)
                                  ->orWhere('phone', $digits)
                                  ->orWhere('phone', 'like', '%'.$last10);
                            })->first();

                $addr   = $user ? Address::where('user_id', $user->id)->latest('id')->first() : null;
                $managerPhone = ($user && $user->manager_id) ? $this->getManagerPhone($user->manager_id) : null;

                $components = [];
                if (!empty($topTokens)) {
                    $components[] = [
                        'type' => 'body',
                        'parameters' => $resolveTopParams($topTokens, $user, $addr, $managerPhone),
                    ];
                }
                $components[] = $buildCarouselFor($user);

                $content = [
                    'name'       => $templateName,
                    'language'   => $language,
                    'components' => $components,
                ];

                DB::table('wa_sales_queue')->insert([
                    'group_id'       => $groupId,
                    'callback_data'  => $templateName,
                    'recipient_type' => 'individual',
                    'to_number'      => $to,
                    'type'           => 'template',
                    'file_url'       => '',
                    'file_name'      => 'carousel',
                    'content'        => json_encode($content),
                    'status'         => 'pending',
                    'response'       => '',
                    'msg_id'         => '',
                    'msg_status'     => '',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
                $queued = 1;

            } else {
                // B) Bulk via filters (if none -> all customers)
                $uq = User::query()
                    ->select('id','phone','name','warehouse_id','manager_id','state','discount')
                    ->where('user_type', 'customer')
                    ->whereNotNull('phone')->where('phone','<>','');

                if ($warehouseId > 0) { $uq->where('warehouse_id', $warehouseId); }
                if ($managerId   > 0) { $uq->where('manager_id',   $managerId);   }
                if ($state !== '')   { $uq->where('state',         $state);       }

                if (!(clone $uq)->exists()) {
                    return response()->json(['ok'=>false,'message'=>'No users found for the selected filters.'], 422);
                }

                $uq->orderBy('id')->chunkById(2000, function($users) use (&$queued, $groupId, $templateName, $language, $now, $toE164ify, $topTokens, $resolveTopParams, $buildCarouselFor) {

                    // Prefetch addresses
                    $userIds = $users->pluck('id')->all();
                    $addrMap = Address::whereIn('user_id', $userIds)->get()->groupBy('user_id');

                    // Prefetch manager phones for this chunk (avoid N+1)
                    $managerIds = $users->pluck('manager_id')->filter()->unique()->values()->all();
                    $mgrMap = [];
                    if (!empty($managerIds)) {
                        $mgrMap = DB::table('users')
                            ->whereIn('id', $managerIds)
                            ->pluck('phone','id')
                            ->toArray();
                    }

                    $rows = [];
                    foreach ($users as $u) {
                        $ph = $toE164ify($u->phone);
                        if (!$ph) continue;

                        $addr = (isset($addrMap[$u->id]) && $addrMap[$u->id]->count() > 0)
                            ? $addrMap[$u->id]->sortByDesc('id')->first()
                            : null;

                        $managerPhone = $u->manager_id ? ($mgrMap[$u->manager_id] ?? 'No Manager Phone') : 'No Manager Phone';

                        $components = [];
                        if (!empty($topTokens)) {
                            $components[] = [
                                'type' => 'body',
                                'parameters' => $resolveTopParams($topTokens, $u, $addr, $managerPhone),
                            ];
                        }
                        $components[] = $buildCarouselFor($u);

                        $content = [
                            'name'       => $templateName,
                            'language'   => $language,
                            'components' => $components,
                        ];

                        $rows[] = [
                            'group_id'       => $groupId,
                            'callback_data'  => $templateName,
                            'recipient_type' => 'individual',
                            'to_number'      => $ph,
                            'type'           => 'template',
                            'file_url'       => '',
                            'file_name'      => 'carousel',
                            'content'        => json_encode($content),
                            'status'         => 'pending',
                            'response'       => '',
                            'msg_id'         => '',
                            'msg_status'     => '',
                            'created_at'     => $now,
                            'updated_at'     => $now,
                        ];
                    }

                    if (!empty($rows)) {
                        foreach (array_chunk($rows, 1000) as $chunk) {
                            DB::table('wa_sales_queue')->insert($chunk);
                            $queued += count($chunk);
                        }
                    }
                });

                if ($queued === 0) {
                    return response()->json(['ok'=>false,'message'=>'No valid phone numbers after formatting.'], 422);
                }
            }

            return response()->json([
                'ok'         => true,
                'group_id'   => $groupId,
                'recipients' => $queued,
            ]);

        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'message'=>$e->getMessage()], 500);
        }
    }







     // ---------- AJAX #2: DISPATCH A GROUP ----------
    public function dispatchCarouselGroup(Request $request)
    {
        $groupId = trim((string)$request->input('group_id', ''));
        if ($groupId === '') {
            return response()->json(['ok'=>false,'message'=>'group_id is required'], 422);
        }

        // Optional: verify group exists & still pending
        $exists = DB::table('wa_sales_queue')->where('group_id',$groupId)->exists();
        if (!$exists) {
            return response()->json(['ok'=>false,'message'=>'Invalid group_id'], 404);
        }

        SendWhatsAppCarouselJob::dispatch($groupId);

        return response()->json(['ok'=>true, 'dispatched'=>true, 'group_id'=>$groupId]);
    }


    public function deleteTemplate(Request $request)
    {
        try {
            $id   = trim((string) $request->input('id', ''));    // template id (aka hsm_id)
            $name = trim((string) $request->input('name', ''));  // template name

            if ($id === '' && $name === '') {
                return response()->json(['ok' => false, 'message' => 'name or id is required'], 422);
            }

            $wa = new WhatsappCarouselService();

            // Prefer deleting by id+name when both are present, else fallback.
            if ($id !== '' && $name !== '') {
                $res = $wa->deleteTemplateById($id, $name);
            } elseif ($id !== '') {
                $res = $wa->deleteTemplateById($id, null);
            } else {
                $res = $wa->deleteTemplateByName($name);
            }

            if (!($res['ok'] ?? false)) {
                $msg = data_get($res, 'error.error_user_msg')
                    ?? data_get($res, 'error.error.message')
                    ?? data_get($res, 'error.message')
                    ?? 'Delete failed.';
                return response()->json(['ok' => false, 'message' => $msg, 'debug' => $res], 422);
            }

            return response()->json(['ok' => true, 'result' => $res['result'] ?? ['success' => true]]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }


        // ============== REPLACE YOUR editTemplateForm WITH THIS ==============
    public function editTemplateForm(Request $request)
    {
        $id       = (string) $request->query('id', '');
        $name     = (string) $request->query('name', '');
        $language = (string) $request->query('language', 'en');

        // direct instantiate (no constructor)
        $wa = new WhatsappCarouselService();

        $tpl = null;
        if ($id !== '') {
            $got = $wa->getTemplateById($id);
            if (!empty($got['ok'])) $tpl = $got['data'];
        } elseif ($name !== '') {
            $got = $wa->getTemplateByNameAndLanguage($name, $language);
            if (!empty($got['ok'])) $tpl = $got['data'];
        }

        if (!$tpl) {
            return back()->with('wa_create_error', [
                'message' => 'Template not found for edit. Provide ?name=&language= or ?id=',
                'debug'   => compact('id', 'name', 'language')
            ]);
        }

        $prefill = $wa->parseTemplateForEdit($tpl);
        $prefill['language'] = strtolower(substr((string)$prefill['language'], 0, 2));

        return view('backend.marketing.whatsapp_carousel.edit', [
            'prefill' => $prefill,
        ]);
    }


    // ============== REPLACE YOUR updateTemplate WITH THIS ==============
    public function updateTemplate(Request $request)
    {
        // NOTE: keep old template name; update via {TEMPLATE_ID} endpoint
        $validated = $request->validate([
            'template_id'       => 'nullable|string',      // hidden in form; preferred
            'old_name'          => 'required|string',
            'old_language'      => 'required|string',
            'language'          => 'required|string|in:en,hi',
            'header_format'     => 'required|string|in:IMAGE,VIDEO',
            'body'              => 'required|string',
            'body_example'      => 'array',
            'cards'             => 'required|array|min:1|max:10',
            'cards.*.header_link'      => 'required|url',
            'cards.*.body_text'        => 'required|string',
            'cards.*.body_example'     => 'array',
            'cards.*.url_btn_example'  => 'nullable|url',
            'cards.*.quick_reply_text' => 'nullable|string|max:20',
        ]);

        $wa = new \App\Services\WhatsappCarouselService();

        // 1) Find template ID
        $templateId = (string) ($validated['template_id'] ?? '');
        if ($templateId === '') {
            $got = $wa->getTemplateByNameAndLanguage($validated['old_name'], $validated['old_language']);
            if (!($got['ok'] ?? false)) {
                return back()->with('wa_create_error', [
                    'message' => 'Template not found by name+language. Please open edit via list.',
                    'debug'   => $got,
                ])->withInput();
            }
            $templateId = (string) data_get($got, 'data.id', '');
            if ($templateId === '') {
                return back()->with('wa_create_error', [
                    'message' => 'Template ID missing.',
                    'debug'   => $got,
                ])->withInput();
            }
        }

        // 2) Build components (upload new 4:: handles if header links provided)
        $headerFmt = strtoupper($validated['header_format']) === 'VIDEO' ? 'VIDEO' : 'IMAGE';

        // Top BODY
        $topBody = [
            'type' => 'BODY',
            'text' => (string) $validated['body'],
        ];
        $topEx = array_values($validated['body_example'] ?? []);
        if (!empty($topEx)) {
            $topBody['example'] = ['body_text' => [ $topEx ]];
        }

        // Cards
        $cardsPayload = [];
        foreach (array_values($validated['cards']) as $i => $c) {
            try {
                $handle4 = $wa->makeHeaderHandleFromLink((string)$c['header_link']);
            } catch (\Throwable $e) {
                return back()->with('wa_create_error', [
                    'message' => "Header upload failed for card #".($i+1).": ".$e->getMessage(),
                ])->withInput();
            }

            $bodyComp = [
                'type' => 'BODY',
                'text' => (string) $c['body_text'],
            ];
            $bx = array_values($c['body_example'] ?? []);
            if (!empty($bx)) {
                $bodyComp['example'] = ['body_text' => [ $bx ]];
            }

            $buttons = [];
            // URL
            $urlExample = (string) ($c['url_btn_example'] ?? '');
            if ($urlExample !== '') {
                $buttons[] = [
                    'type'    => 'URL',
                    'text'    => 'View',
                    'url'     => 'https://mazingbusiness.com/product/{{1}}',
                    'example' => $urlExample,
                ];
            }
            // QUICK_REPLY
            $qr = trim((string) ($c['quick_reply_text'] ?? 'Interested'));
            if ($qr !== '') {
                $buttons[] = ['type'=>'QUICK_REPLY','text'=>$qr];
            }

            $cardsPayload[] = [
                'components' => [
                    [
                        'type'    => 'HEADER',
                        'format'  => $headerFmt,
                        'example' => ['header_handle' => [ $handle4 ]],
                    ],
                    $bodyComp,
                    ['type' => 'BUTTONS', 'buttons' => $buttons],
                ],
            ];
        }

        // 3) Final UPDATE payload (keep same name)
        $payload = [
            'name'      => (string) $validated['old_name'],     // SAME NAME
            'language'  => (string) $validated['language'],     // 'en' | 'hi'
            'category'  => 'MARKETING',
            'components'=> [
                $topBody,
                ['type' => 'CAROUSEL', 'cards' => $cardsPayload],
            ],
        ];

        // 4) Call update endpoint
        $upd = $wa->updateTemplateWithPayload($templateId, $payload);
        if (!($upd['ok'] ?? false)) {
            return back()->with('wa_create_error', [
                'message' => 'Update failed.',
                'debug'   => $upd,
            ])->withInput();
        }

        return redirect()
            ->route('wa.carousel.templates')
            ->with('wa_create_success', [
                'template_name' => $validated['old_name'],
                'id'            => data_get($upd, 'result.id', $templateId),
                'status'        => data_get($upd, 'result.status', 'submitted'),
                'category'      => 'MARKETING',
            ]);
    }



   
}
