<?php

namespace App\Http\Controllers;

use App\Models\WarrantyClaim;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\WarrantyClaimDetail;

use App\Models\InvoiceOrder;
use App\Models\InvoiceOrderDetail;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceDetail;

use App\Models\SubOrder;
use App\Models\SubOrderDetail;
use App\Models\ResetProduct;

use App\Models\Product;
use App\Models\Barcode;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Address;
use Illuminate\Support\Facades\Auth;

// Services
use App\Services\WhatsAppWebService;

class WarrantyClaimController extends Controller
{
    // Optional: generic index (not used in routes above)
    public function index(Request $request)
    {
        $claims = WarrantyClaim::latest()->paginate(30);
        return view('backend.warranty.claims.pending', compact('claims'));
    }

    public function pendingList(Request $request)
    {
        $claims = WarrantyClaim::query()
            ->where('status', 'pending')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string) $request->search);
                $q->where(function ($qq) use ($s) {
                    $qq->where('ticket_id', 'like', "%{$s}%")
                       ->orWhere('name',      'like', "%{$s}%")
                       ->orWhere('phone',     'like', "%{$s}%")
                       ->orWhere('email',     'like', "%{$s}%");
                });
            })
            ->latest()
            ->paginate(30)
            ->appends($request->query());

        return view('backend.warranty.claims.pending', compact('claims'));
    }

    public function approvedList(Request $request)
    {
        $claims = WarrantyClaim::query()
            ->where('status', 'approved')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string) $request->search);
                $q->where(function ($qq) use ($s) {
                    $qq->where('ticket_id', 'like', "%{$s}%")
                       ->orWhere('name',      'like', "%{$s}%")
                       ->orWhere('phone',     'like', "%{$s}%")
                       ->orWhere('email',     'like', "%{$s}%");
                });
            })
            ->latest()
            ->paginate(30)
            ->appends($request->query());

        return view('backend.warranty.claims.approved', compact('claims'));
    }

    public function rejectedList(Request $request)
    {
        $claims = WarrantyClaim::query()
            ->where('status', 'rejected')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string) $request->search);
                $q->where(function ($qq) use ($s) {
                    $qq->where('ticket_id', 'like', "%{$s}%")
                       ->orWhere('name',      'like', "%{$s}%")
                       ->orWhere('phone',     'like', "%{$s}%")
                       ->orWhere('email',     'like', "%{$s}%");
                });
            })
            ->latest()
            ->paginate(30)
            ->appends($request->query());

        return view('backend.warranty.claims.rejected', compact('claims'));
    }

    // Actions
    /** OVERALL APPROVE: claim + ALL details = approved (1) */
    public function approve($id)
    {
        $claim = WarrantyClaim::with('details')->findOrFail($id);

        // Claim status
        $claim->status = 'approved';
        $claim->save();

        // Sare detail items approve
        WarrantyClaimDetail::where('warranty_claim_id', $claim->id)
            ->update(['approval_status' => 1]);

        return redirect()
            ->route('claims.approved')
            ->with('success', 'Claim approved successfully and all items marked approved.');
    }

    /** OVERALL REJECT: claim + ALL details = rejected (2) */
    public function reject($id)
    {
        $claim = WarrantyClaim::with('details')->findOrFail($id);

        // Claim status
        $claim->status = 'rejected';
        $claim->save();

        // Sare detail items reject
        WarrantyClaimDetail::where('warranty_claim_id', $claim->id)
            ->update(['approval_status' => 2]);

        return redirect()
            ->route('claims.rejected')
            ->with('success', 'Claim rejected successfully and all items marked rejected.');
    }

    /** ITEM-LEVEL APPROVE */
    public function approveDetail(Request $request, WarrantyClaimDetail $detail)
    {
        $detail->approval_status = 1; // approved
        $detail->save();

        // keep the "from" context (pending/approved/rejected/draft) for the show page + sidebar
        $from = $request->input('from');

        return redirect()
            ->route('claims.show', ['id' => $detail->warranty_claim_id, 'from' => 'approved'])
            ->with('success', 'Item approved.');
    }

    /** ITEM-LEVEL REJECT */
    public function rejectDetail(Request $request, WarrantyClaimDetail $detail)
    {
        $detail->approval_status = 2; // rejected
        $detail->save();

        $from = $request->input('from');

        return redirect()
            ->route('claims.show', ['id' => $detail->warranty_claim_id, 'from' => 'rejected'])
            ->with('success', 'Item rejected.');
    }

    /** Auto-sync parent claim status based on all item statuses */
    protected function syncClaimStatusFromDetails(WarrantyClaim $claim): void
    {
        if (!$claim) return;

        $total     = $claim->details()->count();
        if ($total === 0) {
            // koi item nahin hai, to pending rakh do
            $claim->status = 'pending';
            $claim->save();
            return;
        }

        $approved  = $claim->details()->where('approval_status', 1)->count();
        $rejected  = $claim->details()->where('approval_status', 2)->count();

        if ($approved === $total) {
            $claim->status = 'approved';
        } elseif ($rejected === $total) {
            $claim->status = 'rejected';
        } else {
            $claim->status = 'pending'; // mixed / partial
        }
        $claim->save();
    }

    // DETAILS PAGE
   public function show($id)
    {
        $claim = WarrantyClaim::with([
            'user',
            'details.product:id,name',
            'details.warrantyProduct:id,name',
        ])->findOrFail($id);

        $total        = $claim->details->count();
        $approvedCnt  = $claim->details->where('approval_status', 1)->count();
        $rejectedCnt  = $claim->details->where('approval_status', 2)->count();
        $pendingCnt   = $total - $approvedCnt - $rejectedCnt;

        return view('backend.warranty.claims.show', compact(
            'claim', 'total', 'approvedCnt', 'rejectedCnt', 'pendingCnt'
        ));
    }

    public function draft($id)
    {
        $claim = WarrantyClaim::findOrFail($id);
        $claim->status = 'draft';
        $claim->save();

        // Go to show with from=draft so the Save (Draft -> Approved) UI appears,
        // and the sidebar highlights "Draft".
        return redirect()
            ->route('claims.show', ['id' => $claim->id, 'from' => 'draft'])
            ->with('success', 'Claim saved as draft.');
    }

    public function draftListing(Request $request)
    {
        $claims = WarrantyClaim::query()
            ->where('status', 'draft')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string) $request->search);
                $q->where(function ($qq) use ($s) {
                    $qq->where('ticket_id', 'like', "%{$s}%")
                       ->orWhere('name',      'like', "%{$s}%")
                       ->orWhere('phone',     'like', "%{$s}%")
                       ->orWhere('email',     'like', "%{$s}%");
                });
            })
            ->latest()
            ->paginate(30)
            ->appends($request->query());

        return view('backend.warranty.claims.draft', compact('claims'));
    }

    public function approveFromDraft($id)
    {
        $claim = WarrantyClaim::findOrFail($id);

        // Sirf parent ko approved karo — details ko touch na karo
        $claim->status = 'approved';
        $claim->save();

        return redirect()->route('claims.approved')
            ->with('success', 'Draft claim saved as approved.');
    }


    public function completedList(Request $request)
    {
        $claims = WarrantyClaim::query()
            ->where('status', 'completed')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string) $request->search);
                $q->where(function ($qq) use ($s) {
                    $qq->where('ticket_id', 'like', "%{$s}%")
                       ->orWhere('name',      'like', "%{$s}%")
                       ->orWhere('phone',     'like', "%{$s}%")
                       ->orWhere('email',     'like', "%{$s}%");
                });
            })
            ->with('user:id,name,party_code,phone')
            ->withCount([
                'details as total_items',
                'details as approved_items' => function ($q) { $q->where('approval_status', 1); },
                'details as rejected_items' => function ($q) { $q->where('approval_status', 2); },
            ])
            ->latest()
            ->paginate(30)
            ->appends($request->query());

        return view('backend.warranty.claims.completed', compact('claims'));
    }



public function warrantyClaimToSubOrder($claimId)
{
    // ==== Load claim & relations ====
    /** @var \App\Models\WarrantyClaim $claim */
    $claim = WarrantyClaim::with([
        'user',                               // WarrantyUser
        'details.warrantyProduct:id,name,part_no',
        'details.product:id,name,part_no',
    ])->findOrFail($claimId);

    // ---- Only approved details ----
    $approvedDetails = $claim->details->filter(function ($d) {
        return (int)($d->approval_status ?? 0) === 1;
    });
    if ($approvedDetails->isEmpty()) {
        return back()->with('error', 'No approved items found on this claim to create Sub Order.');
    }

    // ---- Resolve app user & warehouse ----
    $appUserId   = optional($claim->user)->user_id ?: $claim->user_id; // prefer WarrantyUser->user_id
    $appUser     = $appUserId ? User::find($appUserId) : null;
    $warehouseId = optional($appUser)->warehouse_id;
    $warehouse   = $warehouseId ? Warehouse::find($warehouseId) : null;

    if (!$warehouseId || !$warehouse) {
        return back()->with('error', 'No warehouse linked to this claim user. Cannot create Sub Order.');
    }

    $warehouseName = $warehouse->name ?? 'WH';

    // ---- Shipping/Billing via party_code ----
    $partyCode   = optional($claim->user)->party_code;
    $shipAddress = $partyCode
        ? Address::where('acc_code', $partyCode)->latest('id')->first()
        : null;

    $shippingAddressId = optional($shipAddress)->id;
    $billingAddressId  = $shippingAddressId;

    // Build JSON address (inline)
    $shippingAddressJson = null;
    if ($shippingAddressId && $appUserId) {
        $addr = Address::with(['country','state'])->find($shippingAddressId);
        if ($addr) {
            $shippingAddressJson = json_encode([
                'id'           => $addr->id,
                'user_id'      => $appUserId,
                'company_name' => $addr->company_name,
                'address'      => $addr->address,
                'address_2'    => $addr->address_2,
                'city'         => $addr->city,
                'postal_code'  => $addr->postal_code,
                'country'      => optional($addr->country)->name,
                'state'        => optional($addr->state)->name,
                'phone'        => $addr->phone ?? $claim->phone,
                'gstin'        => $addr->gstin,
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    $billingAddressJson = $shippingAddressJson;

    // ---- Financial year via your helper ----
    $fy = $this->getFinancialYear(); // e.g., "25-26"

    // ---- Generate SO number: SO/{WHC}/{000001}/{FY} ----
    $whCode = strtoupper(substr($warehouseName, 0, 3));
    $last   = SubOrder::where('warehouse_id', $warehouseId)
                ->where('status', 'completed')
                ->orderByDesc('id')
                ->first();

    $seq = 1;
    if ($last && !empty($last->order_no)) {
        // Expected format: SO/ABC/000123/25-26
        $parts = explode('/', $last->order_no);
        if (count($parts) >= 4 && is_numeric($parts[2])) {
            $seq = (int)$parts[2] + 1;
        }
        // if FY rolled over, you may want to reset $seq = 1 (optional):
        if (isset($parts[3]) && trim($parts[3]) !== $fy) {
            $seq = 1;
        }
    }
    $orderNo = 'SO/' . $whCode . '/' . str_pad($seq, 6, '0', STR_PAD_LEFT) . '/' . $fy;

    // ---- SubOrder header (mapping per your requirement) ----
    $subOrderPayload = [
        'order_id'               => $claim->id,                      // claim id
        'combined_order_id'      => $claim->id,                      // claim id
        'order_no'               => $orderNo,
        'user_id'                => $appUserId,
        'seller_id'              => null,
        'shipping_address_id'    => $shippingAddressId,
        'shipping_address'       => $shippingAddressJson,
        'billing_address_id'     => $billingAddressId,
        'billing_address'        => $billingAddressJson,

        'additional_info'        => null,
        'shipping_type'          => 'warranty',                      // NOT NULL
        'payment_status'         => 'paid',
        'payment_details'        => null,
        'grand_total'            => 0,
        'payable_amount'         => 0,
        'payment_discount'       => 0,
        'coupon_discount'        => 0,

        'code'                   => $claim->ticket_id,               // ticket_id
        'date'                   => now()->toDateString(),
        'viewed'                 => 0,
        'order_from'             => 'warranty',                      // literal
        'payment_status_viewed'  => 0,
        'commission_calculated'  => 0,

        'status'                 => 'completed',                     // literal
        'warehouse_id'           => $warehouseId,
        'sub_order_user_name'    => ($claim->name ?: optional($claim->user)->name ?: optional($appUser)->name ?: 'Customer'),
        'early_payment_check'    => 0,
        'conveince_fee_payment_check' => 0,
        'type'                   => 'sub_order',                     // literal
        'is_warranty'            => 0,                               // literal
    ];

    /** @var \App\Models\SubOrder $subOrder */
    $subOrder = SubOrder::create($subOrderPayload);

    // ---- Create SubOrderDetails for each approved item ----
    foreach ($approvedDetails as $detail) {
        $product = $detail->warrantyProduct ?: $detail->product;

        // Resolve product_id or fallback via part_no
        $productId = optional($product)->id;
        $partNo    = $detail->warranty_product_part_number
                     ?: $detail->part_number
                     ?: optional($product)->part_no;

        if (!$productId && $partNo) {
            $found = Product::where('part_no', $partNo)->first();
            if ($found) $productId = $found->id;
        }

        // Closing stock lookup from products_api for CURRENT warehouse
        $closingStocksData = \DB::table('products_api')
            ->where('part_no', $partNo)
            ->where('godown',  $warehouseName)  // godown == warehouse name
            ->first();

        $closingStock = $closingStocksData ? (int)$closingStocksData->closing_stock : 0;

        // Always 1 item per approved claim line
        $qty  = 1;
        $rate = 1;   // neutral rate per your flow

        /** @var \App\Models\SubOrderDetail $createdDetail */
        $createdDetail = SubOrderDetail::create([
            'order_id'               => $claim->id,            // claim id
            'order_type'             => 'warranty',            // helpful tag
            'seller_id'              => null,

            // ensure NOT NULL by using current warehouse id
            'og_product_warehouse_id'=> $warehouseId,
            'product_warehouse_id'   => $warehouseId,

            'product_id'             => $productId,
            'variation'              => null,
            'shipping_cost'          => 0,
            'quantity'               => $qty,
            'payment_status'         => 'paid',
            'delivery_status'        => 'pending',
            'shipping_type'          => 'warranty',            // NOT NULL
            'pickup_point_id'        => null,
            'product_referral_code'  => null,
            'earn_point'             => 0,
            'cash_and_carry_item'    => 0,
            'applied_offer_id'       => null,
            'complementary_item'     => 0,
            'offer_rewards'          => null,
            'remarks'                => null,

            'price'                  => $rate,
            'tax'                    => 0,
            'sub_order_id'           => $subOrder->id,
            'order_details_id'       => null,
            'challan_quantity'       => 0,
            'pre_close_quantity'     => 0,
            'approved_quantity'      => $qty,
            'closing_qty'            => $closingStock,
            'approved_rate'          => $rate,
            'warehouse_id'           => $warehouseId,
            'type'                   => 'sub_order',           // literal
            'is_warranty'            => 0,                     // literal
            'barcode'                => (($b = trim((string) $detail->barcode)) !== '' ? $b : null),
        ]);

        // ===== ResetProduct seed =====
        if ($productId) {
            $getResetProductData = ResetProduct::where('product_id', $productId)->first();
            if ($getResetProductData == null) {
                $prodRow = $productId ? Product::find($productId) : null;
                ResetProduct::create([
                    'product_id' => $productId,
                    'part_no'    => $prodRow->part_no ?? $partNo,
                ]);
            }
        }

        // ===== Negative Stock Entry (call OrderController method) =====
        if ($closingStock < $qty && $subOrder->status === 'completed' && !empty($subOrder->order_no)) {
            $requestSubmit = new \Illuminate\Http\Request();
            $requestSubmit->merge([
                'order_no'             => $subOrder->order_no,
                'sub_order_details_id' => $createdDetail->id,
            ]);

            // Call existing method from OrderController (since it isn't on this controller)
            try {
                $orderCtrl = app(\App\Http\Controllers\OrderController::class);
                $orderCtrl->negativeStockEntry($requestSubmit);
            } catch (\Throwable $e) {
                \Log::warning('negativeStockEntry call failed: '.$e->getMessage());
            }
        }

        // Mark this claim row as completed (3)
        if ((int)$detail->approval_status !== 3) {
            $detail->approval_status = 3;
            $detail->save();
        }
    }

    // ---- Mark the claim completed too ----
    $claim->status = 'completed';
    $claim->save();

    /* ===========================
     * GST Verify + Zoho Sync
     * =========================== */
    try {
        // Use the same address you used on the SubOrder
        $shippingAddress = $shippingAddressId ? Address::find($shippingAddressId) : null;

        if ($shippingAddress && $shippingAddress->gstin) {
            // Verify GST via AppyFlow, then decide Zoho update (same pattern you used)
            $gstResponse = \Illuminate\Support\Facades\Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://appyflow.in/api/verifyGST', [
                'key_secret' => env('APPYFLOW_KEYSECRET'),
                'gstNo'      => $shippingAddress->gstin,
            ]);

            $isGstValid = false;

            if ($gstResponse->successful()) {
                $gstData = json_decode($gstResponse->body(), true);
                if (isset($gstData['taxpayerInfo']['gstin']) && ($gstData['taxpayerInfo']['sts'] ?? null) === 'Active') {
                    $isGstValid = true;
                }
            }

            if (!$isGstValid && $shippingAddress->zoho_customer_id) {
                // Update existing Zoho customer if GST is invalid (your earlier rule)
                try {
                    $zohoController = app(\App\Http\Controllers\ZohoController::class);
                    $zohoController->updateCustomerInZoho($shippingAddress->zoho_customer_id);
                } catch (\Throwable $e) {
                    \Log::error('Zoho customer update (invalid GST) failed: '.$e->getMessage());
                }
            }
        } else {
            // No GST — still try to keep Zoho in sync if we have a customer id
            if ($shippingAddress && $shippingAddress->zoho_customer_id) {
                try {
                    $zohoController = app(\App\Http\Controllers\ZohoController::class);
                    $zohoController->updateCustomerInZoho($shippingAddress->zoho_customer_id);
                } catch (\Throwable $e) {
                    \Log::error('Zoho customer update (no GST) failed: '.$e->getMessage());
                }
            }
        }
    } catch (\Throwable $e) {
        \Log::error('Zoho GST Validation or Customer Update Error: '.$e->getMessage());
    }

    return redirect()
        ->route('claims.completed')
        ->with('success', 'Warranty Claim converted to Sub Order: '.$subOrder->order_no);
}


private function getFinancialYear() {
      $currentYear = date('Y');  // Get current year (e.g., 2024)
      $currentMonth = date('m'); // Get current month (e.g., 03 for March)

      if ($currentMonth >= 4) {
          // Financial year starts in April
          $fyStart = substr($currentYear, -2);         // Last two digits of the current year (e.g., "24")
          $fyEnd = substr($currentYear + 1, -2);       // Last two digits of next year (e.g., "25")
      } else {
          // Before April, it's still the previous financial year
          $fyStart = substr($currentYear - 1, -2);     // Last two digits of last year (e.g., "23")
          $fyEnd = substr($currentYear, -2);          // Last two digits of current year (e.g., "24")
      }
      return $fyStart . '-' . $fyEnd;
  }



    public function completeToInvoice($claimId)
    {
        // not uses anywhere
        $claim = WarrantyClaim::with([
            'user', // WarrantyUser (has party_code, user_id, name, phone)
            'details.product:id,name,part_no,hsncode,tax',
            'details.warrantyProduct:id,name,part_no,hsncode,tax',
        ])->findOrFail($claimId);

        // Must be approved & have items
        if (strtolower($claim->status ?? '') !== 'approved') {
            return back()->with('error', 'Only approved claims can be converted to invoice.');
        }
        if ($claim->details->isEmpty()) {
            return back()->with('error', 'This claim has no items to invoice.');
        }

        /* -------- USERS → WAREHOUSE -------- */
        $appUserId   = optional($claim->user)->user_id;               // from WarrantyUser
        $appUser     = $appUserId ? User::find($appUserId) : null;
        $warehouseId = optional($appUser)->warehouse_id;
        $warehouse   = $warehouseId ? Warehouse::find($warehouseId) : null;

        $warehouseName = optional($warehouse)->name ?: 'WH';
        $warehouseCode = strtoupper(substr($warehouseName, 0, 3));

        /* -------- Invoice No: {CODE}/{0001}/25-26 -------- */
        $lastInvoice = InvoiceOrder::query()
            ->where('warehouse_id', $warehouseId)
            ->where('invoice_no', 'LIKE', "{$warehouseCode}/%")
            ->orderByDesc('id')
            ->first();

        $lastNumber = 0;
        if ($lastInvoice) {
            $parts = explode('/', $lastInvoice->invoice_no);
            $lastNumber = isset($parts[1]) ? (int) $parts[1] : 0;
        }
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        $invoiceNo = $warehouseCode . '/' . $newNumber . '/25-26';

        /* -------- Shipping address via party_code (addresses.acc_code) -------- */
        $partyCode = optional($claim->user)->party_code;
        $shippingAddress = $partyCode
            ? Address::where('acc_code', $partyCode)->latest('id')->first()
            : null;

        $shippingAddressId = optional($shippingAddress)->id;

        // read state ids for tax split
        $shippingStateId  = optional($shippingAddress)->state_id;
        $warehouseStateId = $warehouse->state_id ?? optional(optional($warehouse)->state)->id;
        $isIntra          = $shippingStateId && $warehouseStateId && ($shippingStateId == $warehouseStateId);

        /* -------- Build party_info JSON -------- */
        $partyInfoArr = [
            'name'         => $claim->name
                              ?? optional($claim->user)->name
                              ?? optional($appUser)->name
                              ?? null,
            'company_name' => optional($shippingAddress)->company_name,
            'gstin'        => optional($shippingAddress)->gstin,
            'email'        => $claim->email
                              ?? optional($appUser)->email
                              ?? null,
            'address'      => trim(implode(', ', array_filter([
                                  optional($shippingAddress)->address,
                                  optional($shippingAddress)->address_2,
                              ]))) ?: null,
            'country'      => optional(optional($shippingAddress)->country)->name,
            'state'        => optional(optional($shippingAddress)->state)->name,
            'city'         => optional($shippingAddress)->city
                              ?: optional(optional($shippingAddress)->city())->first()->name ?? null,
            'postal_code'  => optional($shippingAddress)->postal_code,
            'phone'        => optional($shippingAddress)->phone
                              ?? $claim->phone
                              ?? optional($claim->user)->phone
                              ?? null,
        ];
        $partyInfoJson = json_encode($partyInfoArr, JSON_UNESCAPED_UNICODE);

        /* -------- Create Invoice header -------- */
        $invoice = new InvoiceOrder();
        $invoice->party_code            = $partyCode;
        $invoice->invoice_no            = $invoiceNo;
        $invoice->warehouse_id          = $warehouseId;
        $invoice->user_id               = $appUserId;               // WarrantyUser->user_id
        $invoice->party_info            = $partyInfoJson;           // JSON here
        $invoice->shipping_address_id   = $shippingAddressId;
        $invoice->early_payment_check   = 0;
        $invoice->is_warranty           = 0;                        // keep as per your pattern
        $invoice->invoice_cancel_status = 0;
        $invoice->save();

        /* -------- ONLY approved details → line items -------- */
        $approvedDetails = $claim->details->filter(function ($d) {
            return (int)($d->approval_status ?? 0) === 1;
        });

        if ($approvedDetails->isEmpty()) {
            // nothing to invoice
            return back()->with('error', 'No approved items found to invoice for this claim.');
        }

        $totalCgst = 0; $totalSgst = 0; $totalIgst = 0;
        $subtotal  = 0;
        $usedDetailIds = [];

        foreach ($approvedDetails as $detail) {
            $product  = $detail->warrantyProduct ?: $detail->product;

            $partNo   = $detail->warranty_product_part_number
                        ?? $detail->part_number
                        ?? optional($product)->part_no
                        ?? null;

            $itemName = optional($product)->name ?: 'Warranty Item';
            $hsn      = optional($product)->hsncode ?: null;
            $gstPct   = (float) (optional($product)->tax ?? 0);

            $qty      = 1;
            $rate     = 1.00;                                  // inclusive rate
            $priceEx  = $gstPct > 0 ? round($rate / (1 + $gstPct / 100), 2) : $rate;  // excl tax
            $grossAmt = round($priceEx * $qty, 2);

            // tax split
            $cgst = 0; $sgst = 0; $igst = 0;
            if ($gstPct > 0) {
                if ($isIntra) {
                    $cgst = round(($grossAmt * ($gstPct / 2)) / 100, 2);
                    $sgst = round(($grossAmt * ($gstPct / 2)) / 100, 2);
                } else {
                    $igst = round(($grossAmt * $gstPct) / 100, 2);
                }
            }

            $billedAmt = round($rate * $qty, 0);               // inclusive

            InvoiceOrderDetail::create([
                'invoice_order_id' => $invoice->id,
                'part_no'          => $partNo,
                'item_name'        => $itemName,
                'hsn_no'           => $hsn,
                'gst'              => $gstPct,
                'billed_qty'       => $qty,
                'rate'             => $rate,
                'billed_amt'       => $billedAmt,
                'cgst'             => $cgst,
                'sgst'             => $sgst,
                'igst'             => $igst,
                'price'            => $priceEx,                // excl. tax
                'gross_amt'        => $grossAmt,               // excl. tax
                'is_warranty'      => 0,
                'barcode'          => (($b = trim((string) $detail->barcode)) !== '' ? $b : null),
            ]);

            $subtotal  += $grossAmt;
            $totalCgst += $cgst;
            $totalSgst += $sgst;
            $totalIgst += $igst;

            $usedDetailIds[] = $detail->id; // mark this row later
        }

        // header totals
        $invoice->total_cgst  = $totalCgst;
        $invoice->total_sgst  = $totalSgst;
        $invoice->total_igst  = $totalIgst;
        $invoice->grand_total = round($subtotal + $totalCgst + $totalSgst + $totalIgst, 0);
        $invoice->save();

        /* -------- MARKINGS (important part) -------- */
        // 1) Mark only the invoiced detail rows as completed (status = 3)
        if (!empty($usedDetailIds)) {
            WarrantyClaimDetail::whereIn('id', $usedDetailIds)->update([
                'approval_status' => 3,           // 0=pending,1=approved,2=rejected,3=completed
                'updated_at'      => now(),
                // 'completed_at'  => now(),      // uncomment if your table has it
                // 'invoice_id'    => $invoice->id, // uncomment if you keep linkage
            ]);
        }

        $claim->invoice_order_id = $invoice->id;

        // 2) Mark the claim as completed
        $claim->status = 'completed';
        $claim->save();

        return redirect()
            ->route('claims.approved')
            ->with('success', 'Invoice created from Warranty Claim #'.$claim->id.' ('.$invoiceNo.')');
    }


    


    public function warrantyCreditNoteService(Request $request, $claimId)
    {

        // Load claim + warranty user (party_code, user_id)
        $claim = WarrantyClaim::with(['user'])->findOrFail($claimId);

        // Only allowed for approved claims
        // if (strtolower($claim->status ?? '') !== 'approved') {
        //     return back()->with('error', 'Only approved claims can create a Service Credit Note.');
        // }

        // --- GET params (with sensible defaults) ---
        $note        = (string) $request->query('note', 'Warranty Claim');
        $sacCode     = (string) $request->query('sac_code', '9987');
        $rate        = (float)  $request->query('rate', 1);   // will be overridden if we can compute
        $quantity    = (int)    $request->query('quantity', 1);
        $sellerNo    = (string) ($request->query('seller_invoice_no') ?: 'NA');
        $sellerDate  = $request->query('seller_invoice_date')
                        ? \Carbon\Carbon::parse($request->query('seller_invoice_date'))->toDateString()
                        : now()->toDateString();

        // --- Resolve Warehouse via App User (WarrantyUser->user_id -> users.warehouse_id) ---
        $appUserId   = optional($claim->user)->user_id ?: $claim->user_id; // prefer related, else claim column
        $appUser     = $appUserId ? User::find($appUserId) : null;
        $warehouseId = optional($appUser)->warehouse_id;
        $warehouse   = $warehouseId ? Warehouse::with('state')->find($warehouseId) : null;

        // Company state = state of the user who owns this warehouse
        $companyOwnerUser = $warehouse ? User::where('id', $warehouse->user_id)->first() : null;
        $companyState     = strtoupper((string) optional($companyOwnerUser)->state);

        // Warehouse code (first 3 chars)
        $warehouseName = optional($warehouse)->name ?: 'WH';
        $warehouseCode = strtoupper(substr($warehouseName, 0, 3));

        // --- Customer shipping address via WarrantyUser.party_code -> addresses.acc_code ---
        $partyCode   = optional($claim->user)->party_code;
        $shipAddress = $partyCode
            ? Address::with(['state','country','city'])
                ->where('acc_code', $partyCode)->latest('id')->first()
            : null;

        $customerState = strtoupper((string) optional($shipAddress->state)->name);
        $isIntra       = ($customerState !== '' && $companyState !== '' && $customerState === $companyState);

        /* ==========================================================
         * NEW: Compute tax-inclusive total from claim items using
         *       user's discount % (from users.discount)
         * ========================================================== */
        $discountPct = (float) (User::where('id', $appUserId)->value('discount') ?? 0);

        $details = WarrantyClaimDetail::where('warranty_claim_id', $claimId)
        ->where('approval_status', 1)   // only approved rows
        ->get();

        $grandTotalIncl = 0.0;
        $itemCount      = 0;

        foreach ($details as $row) {
            // your table has "part_number"; also try alternate columns just in case
            $partNo =  $row->warranty_product_part_number;
            if (!$partNo) {
                continue;
            }

            // fetch product by part number (adjust where-clauses to your schema)
            $product = Product::where('part_no', $partNo)
                       
                        ->first();

            $mrp = (float) ($product->mrp ?? $product->unit_price ?? 0);
            if ($mrp <= 0) {
                continue;
            }

            // discount is applied on MRP (inclusive per your requirement)
            $priceAfterDisc = round($mrp - ($mrp * ($discountPct / 100)), 2);

            $grandTotalIncl += $priceAfterDisc;
            $itemCount++;
        }

        // If computed successfully, override $rate (single service line) and force qty = 1
        if ($itemCount > 0) {
            $rate     = round($grandTotalIncl, 2); // tax-inclusive consolidated amount
            $quantity = 1;
            $note = trim($note . ($claim->ticket_id ? ' - Ticket ' . $claim->ticket_id : ''));
        }
        /* ======================= /NEW ======================= */

        // --- Credit Note Number: {WHC}/CN/{001..} ---
        $lastCreditNote = PurchaseInvoice::where('credit_note_number', 'LIKE', $warehouseCode.'/CN/%')
            ->orderByDesc('id')
            ->value('credit_note_number');

        if ($lastCreditNote) {
            $lastNumber = (int) substr($lastCreditNote, -3);
            $newNumber  = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber  = '001';
        }
        $creditNoteNumber = $warehouseCode . '/CN/' . $newNumber;

        // --- Purchase Invoice No: pn-xxx ---
        $lastPurchase   = PurchaseInvoice::orderByDesc('id')->first();
        $newPurchaseSeq = $lastPurchase ? ((int) substr($lastPurchase->purchase_no, 3)) + 1 : 1;
        $purchaseNo     = 'pn-' . str_pad($newPurchaseSeq, 3, '0', STR_PAD_LEFT);

        // --- Create header (customer CN, Service Entry) ---
        $purchaseInvoiceId = PurchaseInvoice::insertGetId([
            'purchase_no'           => $purchaseNo,
            'purchase_order_no'     => 'Service Entry',
            'seller_invoice_no'     => $sellerNo,
            'seller_invoice_date'   => $sellerDate ?: null,
            'addresses_id'          => optional($shipAddress)->id,
            'warehouse_id'          => $warehouseId,
            'purchase_invoice_type' => 'customer',
            'credit_note_number'    => $creditNoteNumber,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        // --- Tax calc @18% (rate is GST-inclusive) ---
        $gstRate    = 18;
        $priceExGST = $gstRate > 0 ? round($rate / (1 + $gstRate/100), 2) : $rate;
        $grossAmt   = round($priceExGST * max($quantity, 1), 2);

        $cgst = 0; $sgst = 0; $igst = 0;
        if ($gstRate > 0) {
            if ($isIntra) {
                $cgst = round($grossAmt * 0.09, 2);
                $sgst = round($grossAmt * 0.09, 2);
            } else {
                $igst = round($grossAmt * 0.18, 2);
            }
        }

        // --- Line item (Service) ---
        PurchaseInvoiceDetail::create([
            'purchase_invoice_id' => $purchaseInvoiceId,
            'purchase_invoice_no' => $purchaseNo,
            'purchase_order_no'   => 'Service Entry',
            'order_no'            => 'Service Entry',
            'part_no'             => $note,      // store note/summary here
            'hsncode'             => $sacCode,   // SAC code
            'qty'                 => max($quantity, 1),
            'price'               => $priceExGST,
            'gross_amt'           => $grossAmt,
            'cgst'                => $cgst,
            'sgst'                => $sgst,
            'igst'                => $igst,
            'tax'                 => $gstRate,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // --- Update header totals ---
        PurchaseInvoice::where('id', $purchaseInvoiceId)->update([
            'total_cgst' => $cgst,
            'total_sgst' => $sgst,
            'total_igst' => $igst,
        ]);

        // // --- Zoho push (disabled) ---
        try {
            $zoho = new \App\Http\Controllers\ZohoController();
            $zoho->createZohoServiceCreditNote($purchaseInvoiceId);
        } catch (\Throwable $e) {
            \Log::error('Zoho service CN error: '.$e->getMessage());
        }

        /* ===== Mark the claim COMPLETED ===== */
        $claim->purchase_invoice_id = $purchaseInvoiceId; // << link
        $claim->status = 'completed';   // final state
        $claim->save();



        WarrantyClaimDetail::where('warranty_claim_id', $claimId)
            ->where('approval_status', 1)   // sirf approved items
            ->update([
                'approval_status' => 3,      // completed
                'updated_at'      => now(),
            ]);


          
        $response = $this->sendCreditNoteWhatsAppFromClaim(
            $claim->id,              // claimId
            $purchaseInvoiceId,      // purchaseInvoiceId
            $creditNoteNumber        // creditNoteNumber
        );

       
        return redirect()->route('purchase.credit.note.list')
            ->with('status', "Service Credit Note created: {$creditNoteNumber}");
    }

    

    private function sendCreditNoteWhatsAppFromClaim(
        int $claimId,
        int $purchaseInvoiceId,
        string $creditNoteNumber
    ) {
        try {
            // 1) Load claim + user
            /** @var \App\Models\WarrantyClaim $claim */
            $claim = WarrantyClaim::with(['user'])->find($claimId);
            if (!$claim) {
                return [
                    'ok'      => false,
                    'message' => 'Claim not found.',
                    'claim_id'=> $claimId,
                    'purchase_invoice_id' => $purchaseInvoiceId,
                    'credit_note_number'  => $creditNoteNumber,
                ];
            }

            $customerName  = $claim->name ?: optional($claim->user)->name ?: 'Customer';
            $ticketId      = (string) ($claim->ticket_id ?: $claim->id); // fall back to claim id if ticket missing
            $rawPhone      = optional($claim->user)->phone ?: $claim->phone;
            $to            = $rawPhone;
            if (!$to) {
                return [
                    'ok'      => false,
                    'message' => 'Invalid or missing customer phone.',
                    'claim_id'=> $claimId,
                    'purchase_invoice_id' => $purchaseInvoiceId,
                    'credit_note_number'  => $creditNoteNumber,
                ];
            }

            // 2) PDF URL
            $pdfUrl = app(\App\Http\Controllers\PurchaseOrderController::class)
                ->getCreditNoteInvoicePDFURL($purchaseInvoiceId);
            if (!filter_var($pdfUrl, FILTER_VALIDATE_URL)) {
                return [
                    'ok'      => false,
                    'message' => 'Invalid PDF URL.',
                    'to'      => $to,
                    'claim_id'=> $claimId,
                    'purchase_invoice_id' => $purchaseInvoiceId,
                    'credit_note_number'  => $creditNoteNumber,
                    'pdf_url' => $pdfUrl,
                ];
            }

            // 3) Amount from DB
            /** @var \App\Models\PurchaseInvoice $pi */
            $pi = PurchaseInvoice::find($purchaseInvoiceId);
            if (!$pi) {
                return [
                    'ok'      => false,
                    'message' => 'Purchase invoice not found.',
                    'to'      => $to,
                    'claim_id'=> $claimId,
                    'purchase_invoice_id' => $purchaseInvoiceId,
                    'credit_note_number'  => $creditNoteNumber,
                    'pdf_url' => $pdfUrl,
                ];
            }

            $detailsGross = PurchaseInvoiceDetail::where('purchase_invoice_id', $purchaseInvoiceId)->sum('gross_amt');
            $totalTaxes   = (float)($pi->total_cgst ?? 0) + (float)($pi->total_sgst ?? 0) + (float)($pi->total_igst ?? 0);
            $totalAmount  = (float)$detailsGross + $totalTaxes;

            // 4) Filename
            $fileName = 'Credit-Note-' . $creditNoteNumber . '.pdf';

            // 5) Template payload
            
            $templateData = [
                'name'     => 'warranty_claim_credit_note', // <-- your template name
                'language' => 'en_US',
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [[
                            'type'     => 'document',
                            'document' => [
                                'link'     => $pdfUrl,
                                'filename' => $fileName,
                            ],
                        ]],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => (string) $customerName],                                 // {{1}}
                            ['type' => 'text', 'text' => (string) $ticketId],                                     // {{2}}
                            ['type' => 'text', 'text' => (string) $creditNoteNumber],                             // {{3}}
                            ['type' => 'text', 'text' => number_format((float)$totalAmount, 2, '.', '')],         // {{4}}
                        ],
                    ],
                    
                ],
            ];

            // 6) Send
            $svc  = new \App\Services\WhatsAppWebService(); // adjust namespace if needed
            $resp = $svc->sendTemplateMessage($to, $templateData);

            return $resp;

           
            
        } catch (\Throwable $e) {
            \Log::error('WA CN send exception: ' . $e->getMessage(), [
                'claim_id' => $claimId,
                'pi_id'    => $purchaseInvoiceId,
                'cn_no'    => $creditNoteNumber,
            ]);
            return [
                'ok'                  => false,
                'message'             => 'Exception while sending WhatsApp: ' . $e->getMessage(),
                'purchase_invoice_id' => $purchaseInvoiceId,
                'credit_note_number'  => $creditNoteNumber,
                'claim_id'            => $claimId,
            ];
        }
    }



    



    public function sendWarrantyClaimCreatedWA()
    {
        // --- Static values (change if needed) ---
        $to           = '7044300330';                
        $customerName = 'Test Customer';
        $ticketId     = 'WC-1001';
        $dateStr      = Carbon::now()->format('d M Y, h:i A');
        $managerPhone = '7044300330';
        $pdfUrl    = 'https://mazingbusiness.com/public/reward_pdf/earlypayment_party_OPEL0100526_1757917911.pdf';
        $template  = 'wc_text_hdr_20250919_073833 ';                              

        try {
            $svc = new WhatsAppWebService();                     
            $upload  = $svc->uploadMedia($pdfUrl);
            $mediaId = $upload['id'] ?? $upload['media_id'] ?? null;
            if (!$mediaId) {
                throw new \RuntimeException('Media upload failed, no media id returned.');
            }
            $templateData = [
                'name'     => $template,
                'language' => 'en_US',
                'components' => [
                    [
                        'type'       => 'header',
                        'parameters' => [[
                            'type'     => 'document',
                            'document' => [
                                'filename' => "TicketID - {$ticketId}.pdf",
                                'id'       => $mediaId,    
                            ],
                        ]],
                    ],
                    [
                        'type'       => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $customerName], 
                            ['type' => 'text', 'text' => $ticketId],     
                            ['type' => 'text', 'text' => $dateStr],     
                            ['type' => 'text', 'text' => $managerPhone], 
                        ],
                    ],
                ],
            ];

            // 3) Send
            $response = $svc->sendTemplateMessage($to, $templateData);
            return $response;

           
        } catch (\Throwable $e) {
            \Log::error("WA WarrantyClaim send failed for ticket {$ticketId}: " . $e->getMessage());
            return false;
        }
    }



}
