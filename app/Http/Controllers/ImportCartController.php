<?php

namespace App\Http\Controllers;

use App\Models\BlDetail;
use App\Models\ImportCart;
use App\Models\Supplier;
use App\Models\BlItemDetail;
use App\Models\CiDetail;
use App\Models\CiItemDetail;
use App\Models\CiSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use ZipArchive;
use Illuminate\Support\Facades\File;

use Illuminate\Support\Facades\Mail;
use App\Mail\CiPlZipMail;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use App\Services\NoReplyMailer;

class ImportCartController extends Controller
{
    // Cart listing for a BL
    public function index($blId)
    {
        
        $bl = BlDetail::with('importCompany')->findOrFail($blId);

        $cartItems = ImportCart::with('product')
            ->where('bl_detail_id', $bl->id)
            ->orderBy('id')
            ->get();
    
        // MAIN totals used in your Blade header
        $totalQty   = (float) $cartItems->sum('quantity');
        $totalValue = (float) $cartItems->sum(function ($row) {
            return (float) $row->quantity * (float) $row->dollar_price;
        });
    
        // Packages / Weight / CBM shown in the right box
        $sumPackages = (float) $cartItems->sum('total_no_of_packages');
        $sumWeight   = (float) $cartItems->sum('total_weight');
        $sumCbm      = (float) $cartItems->sum('total_cbm');
    
        $suppliers = Supplier::orderBy('supplier_name')->get();
    
        return view('backend.imports.cart.index', [
            'bl'          => $bl,
            'cartItems'   => $cartItems,
            'totalQty'    => $totalQty,
            'totalValue'  => $totalValue,
            'sumPackages' => $sumPackages,
            'sumWeight'   => $sumWeight,
            'sumCbm'      => $sumCbm,
            'suppliers'   => $suppliers,
        ]);
    }

    public function updateRow(Request $request, $cartId)
    {
        // Same validation as bulk, but ok with single row
        $request->validate([
            'qty'                    => 'required|array',
            'qty.*'                  => 'integer|min:1',

            'dollar_price'           => 'required|array',
            'dollar_price.*'         => 'numeric|min:0',

            'import_print_name'      => 'nullable|array',
            'import_print_name.*'    => 'nullable|string|max:255',

            'weight_per_carton'      => 'nullable|array',
            'weight_per_carton.*'    => 'nullable|numeric|min:0',

            'cbm_per_carton'         => 'nullable|array',
            'cbm_per_carton.*'       => 'nullable|numeric|min:0',

            'quantity_per_carton'    => 'nullable|array',
            'quantity_per_carton.*'  => 'nullable|numeric|min:0',

            'supplier_id'            => 'nullable|array',
            'supplier_id.*'          => 'nullable|integer|exists:suppliers,id',

            'supplier_invoice_no'    => 'nullable|array',
            'supplier_invoice_no.*'  => 'nullable|string|max:255',

            'supplier_invoice_date'  => 'nullable|array',
            'supplier_invoice_date.*'=> 'nullable|date',

            'terms'                  => 'nullable|array',
            'terms.*'                => 'nullable|string|max:500',
        ]);

        $qtyInput             = $request->input('qty', []);
        $priceInput           = $request->input('dollar_price', []);

        $importPrintNameInput = $request->input('import_print_name', []);
        $weightPerCartonInput = $request->input('weight_per_carton', []);
        $cbmPerCartonInput    = $request->input('cbm_per_carton', []);
        $qtyPerCartonInput    = $request->input('quantity_per_carton', []);
        $supplierIdInput      = $request->input('supplier_id', []);
        $supplierInvNoInput   = $request->input('supplier_invoice_no', []);
        $supplierInvDateInput = $request->input('supplier_invoice_date', []);
        $termsInput           = $request->input('terms', []);

        $blId = $request->input('bl_id');

        /** @var \App\Models\ImportCart|null $cart */
        $cart = ImportCart::with('product')->find($cartId);
        if (!$cart) {
            if ($request->ajax()) {
                return response()->json([
                    'message' => 'Cart row not found.',
                ], 404);
            }
            return redirect()->route('import.cart.index', $blId)
                ->with('error', 'Cart row not found.');
        }

        // Update only this row
        if (isset($qtyInput[$cartId])) {
            $cart->quantity = (int) $qtyInput[$cartId];
        }

        if (isset($priceInput[$cartId])) {
            $cart->dollar_price = (float) $priceInput[$cartId];
        }

        $cart->import_print_name   = $importPrintNameInput[$cartId] ?? null;
        $cart->weight_per_carton   = isset($weightPerCartonInput[$cartId])
            ? (float) $weightPerCartonInput[$cartId]
            : null;
        $cart->cbm_per_carton      = isset($cbmPerCartonInput[$cartId])
            ? (float) $cbmPerCartonInput[$cartId]
            : null;
        $cart->quantity_per_carton = isset($qtyPerCartonInput[$cartId])
            ? (float) $qtyPerCartonInput[$cartId]
            : null;

        if (array_key_exists($cartId, $supplierIdInput) &&
            $supplierIdInput[$cartId] !== null &&
            $supplierIdInput[$cartId] !== '') {
            $cart->supplier_id = (int) $supplierIdInput[$cartId];
        } else {
            $cart->supplier_id = null;
        }

        $cart->supplier_invoice_no = $supplierInvNoInput[$cartId] ?? null;

        if (!empty($supplierInvDateInput[$cartId] ?? null)) {
            $cart->supplier_invoice_date = $supplierInvDateInput[$cartId];
        } else {
            $cart->supplier_invoice_date = null;
        }

        $cart->terms = $termsInput[$cartId] ?? null;

        // Recalc totals (ceil for packages)
        $cart->total_no_of_packages = null;
        $cart->total_weight         = null;
        $cart->total_cbm            = null;

        if (!empty($cart->quantity) && !empty($cart->quantity_per_carton) && $cart->quantity_per_carton > 0) {
            $totalPackages = (int) ceil($cart->quantity / $cart->quantity_per_carton);
            $cart->total_no_of_packages = $totalPackages;

            if (!empty($cart->weight_per_carton)) {
                $cart->total_weight = $cart->weight_per_carton * $totalPackages;
            }
            if (!empty($cart->cbm_per_carton)) {
                $cart->total_cbm = $cart->cbm_per_carton * $totalPackages;
            }
        }

        $cart->save();

        // Sync to product
        if ($cart->product) {
            $product = $cart->product;

            $product->import_print_name   = $cart->import_print_name;
            $product->weight_per_carton   = $cart->weight_per_carton;
            $product->cbm_per_carton      = $cart->cbm_per_carton;
            $product->quantity_per_carton = $cart->quantity_per_carton;
            $product->supplier_id         = $cart->supplier_id;
            $product->dollar_price        = $cart->dollar_price;

            $product->save();
        }

        if ($request->ajax()) {
            $totals = ImportCart::where('bl_detail_id', $blId)
                ->selectRaw('
                    COUNT(*) as total_items,
                    SUM(quantity) as total_qty,
                    SUM(quantity * dollar_price) as total_value,
                    SUM(total_no_of_packages) as total_packages,
                    SUM(total_weight) as total_weight,
                    SUM(total_cbm) as total_cbm
                ')
                ->first();

            return response()->json([
                'message'        => 'Cart row updated successfully.',
                'total_items'    => (int) ($totals->total_items ?? 0),
                'total_qty'      => (int) ($totals->total_qty ?? 0),
                'total_value'    => (float) ($totals->total_value ?? 0),
                'total_packages' => (float) ($totals->total_packages ?? 0),
                'total_weight'   => (float) ($totals->total_weight ?? 0),
                'total_cbm'      => (float) ($totals->total_cbm ?? 0),
            ]);
        }

        return redirect()
            ->route('import.cart.index', $blId)
            ->with('success', 'Cart row updated successfully.');
    }

    // ✅ Quantity + Unit Price update from cart page
    public function update(Request $request)
    {
        // Arrays keyed by cart_id: qty[ID], dollar_price[ID], ...
        $qtyInput                  = $request->input('qty', []);
        $priceInput                = $request->input('dollar_price', []);

        $importPrintNameInput      = $request->input('import_print_name', []);
        $weightPerCartonInput      = $request->input('weight_per_carton', []);
        $cbmPerCartonInput         = $request->input('cbm_per_carton', []);
        $qtyPerCartonInput         = $request->input('quantity_per_carton', []);
        $supplierIdInput           = $request->input('supplier_id', []);
        $supplierInvNoInput        = $request->input('supplier_invoice_no', []);
        $supplierInvDateInput      = $request->input('supplier_invoice_date', []);
        $termsInput                = $request->input('terms', []);

        $blId = $request->input('bl_id');

        // Validation
        $request->validate([
            'qty'                    => 'required|array',
            'qty.*'                  => 'integer|min:1',

            'dollar_price'           => 'required|array',
            'dollar_price.*'         => 'numeric|min:0',

            'import_print_name'      => 'nullable|array',
            'import_print_name.*'    => 'nullable|string|max:255',

            'weight_per_carton'      => 'nullable|array',
            'weight_per_carton.*'    => 'nullable|numeric|min:0',

            'cbm_per_carton'         => 'nullable|array',
            'cbm_per_carton.*'       => 'nullable|numeric|min:0',

            'quantity_per_carton'    => 'nullable|array',
            'quantity_per_carton.*'  => 'nullable|numeric|min:0',

            'supplier_id'            => 'nullable|array',
            'supplier_id.*'          => 'nullable|integer|exists:suppliers,id',

            'supplier_invoice_no'    => 'nullable|array',
            'supplier_invoice_no.*'  => 'nullable|string|max:255',

            'supplier_invoice_date'  => 'nullable|array',
            'supplier_invoice_date.*'=> 'nullable|date',

            'terms'                  => 'nullable|array',
            'terms.*'                => 'nullable|string|max:500',
        ]);

        foreach ($qtyInput as $cartId => $qty) {
            /** @var \App\Models\ImportCart|null $cart */
            $cart = ImportCart::with('product')->find($cartId);
            if (!$cart) {
                continue;
            }

            // ====== ImportCart fields ======
            // Qty & price
            $cart->quantity = (int) $qty;

            if (isset($priceInput[$cartId])) {
                $cart->dollar_price = (float) $priceInput[$cartId];
            }

            // Import fields (cart table)
            $cart->import_print_name   = $importPrintNameInput[$cartId] ?? null;
            $cart->weight_per_carton   = isset($weightPerCartonInput[$cartId])
                ? (float) $weightPerCartonInput[$cartId]
                : null;
            $cart->cbm_per_carton      = isset($cbmPerCartonInput[$cartId])
                ? (float) $cbmPerCartonInput[$cartId]
                : null;
            $cart->quantity_per_carton = isset($qtyPerCartonInput[$cartId])
                ? (float) $qtyPerCartonInput[$cartId]
                : null;

            // Supplier id in cart
            if (array_key_exists($cartId, $supplierIdInput) &&
                $supplierIdInput[$cartId] !== null &&
                $supplierIdInput[$cartId] !== '') {
                $cart->supplier_id = (int) $supplierIdInput[$cartId];
            } else {
                $cart->supplier_id = null;
            }

            // Supplier invoice no/date
            $cart->supplier_invoice_no = $supplierInvNoInput[$cartId] ?? null;

            if (!empty($supplierInvDateInput[$cartId] ?? null)) {
                $cart->supplier_invoice_date = $supplierInvDateInput[$cartId];
            } else {
                $cart->supplier_invoice_date = null;
            }

            // Terms
            $cart->terms = $termsInput[$cartId] ?? null;

            // Recalc totals
            $cart->total_no_of_packages = null;
            $cart->total_weight         = null;
            $cart->total_cbm            = null;

            if (!empty($cart->quantity) && !empty($cart->quantity_per_carton) && $cart->quantity_per_carton > 0) {
                $totalPackages = (int) ceil($cart->quantity / $cart->quantity_per_carton);
                $cart->total_no_of_packages = $totalPackages;

                if (!empty($cart->weight_per_carton)) {
                    $cart->total_weight = $cart->weight_per_carton * $totalPackages;
                }
                if (!empty($cart->cbm_per_carton)) {
                    $cart->total_cbm = $cart->cbm_per_carton * $totalPackages;
                }
            }

            $cart->save();

            // ====== Linked Product update ======
            if ($cart->product) {
                $product = $cart->product;

                $product->import_print_name   = $cart->import_print_name;
                $product->weight_per_carton   = $cart->weight_per_carton;
                $product->cbm_per_carton      = $cart->cbm_per_carton;
                $product->quantity_per_carton = $cart->quantity_per_carton;

                $product->supplier_id = $cart->supplier_id;

                if (isset($priceInput[$cartId])) {
                    $product->dollar_price = (float) $priceInput[$cartId];
                }

                $product->save();
            }
        }

        return redirect()
            ->route('import.cart.index', $blId)
            ->with('success', 'Cart updated successfully.');
    }

    // Remove single line
    public function remove(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|integer|exists:import_carts,id',
        ]);

        ImportCart::where('id', $request->cart_id)->delete();

        return back()->with('success', 'Item removed from cart.');
    }

    // Clear complete cart for one BL + company
    public function clear(Request $request)
    {
        $request->validate([
            'bl_id'             => 'required|integer|exists:bl_details,id',
            'import_company_id' => 'required|integer|exists:import_companies,id',
        ]);

        ImportCart::where('bl_detail_id', $request->bl_id)
            ->where('import_company_id', $request->import_company_id)
            ->delete();

        return back()->with('success', 'Cart cleared.');
    }

    public function proceed(Request $request)
    {
        $request->validate([
            'bl_id'             => 'required|integer|exists:bl_details,id',
            'import_company_id' => 'required|integer|exists:import_companies,id',
        ]);

        $blId            = (int) $request->input('bl_id');
        $importCompanyId = (int) $request->input('import_company_id');

        /** @var \App\Models\BlDetail $bl */
        $bl = BlDetail::with('importCompany')->findOrFail($blId);

        $cartItems = ImportCart::where('bl_detail_id', $blId)
            ->where('import_company_id', $importCompanyId)
            ->with('product')
            ->get();

        if ($cartItems->isEmpty()) {
            return back()->with('error', 'No items in cart to proceed.');
        }

        DB::transaction(function () use ($cartItems, $bl, $importCompanyId, $blId) {

            // 1️⃣ Ensure cart totals (ceil for packages, recompute weight/cbm)
            foreach ($cartItems as $cart) {
                $needsSave = false;

                if (
                    !empty($cart->quantity)
                    && !empty($cart->quantity_per_carton)
                    && $cart->quantity_per_carton > 0
                ) {
                    $totalPackages = (int) ceil($cart->quantity / $cart->quantity_per_carton);

                    if (
                        empty($cart->total_no_of_packages) ||
                        $cart->total_no_of_packages != $totalPackages
                    ) {
                        $cart->total_no_of_packages = $totalPackages;
                        $needsSave = true;
                    }

                    if (!empty($cart->weight_per_carton)) {
                        $cart->total_weight = (float) $cart->weight_per_carton * $totalPackages;
                        $needsSave = true;
                    }

                    if (!empty($cart->cbm_per_carton)) {
                        $cart->total_cbm = (float) $cart->cbm_per_carton * $totalPackages;
                        $needsSave = true;
                    }
                }

                if ($needsSave) {
                    $cart->save();
                }
            }

            // 2️⃣ BL ITEM DETAILS
            $invoiceNos = $cartItems->pluck('supplier_invoice_no')
                ->filter()
                ->unique()
                ->values();

            if ($invoiceNos->isNotEmpty()) {
                BlItemDetail::where('bl_id', $bl->id)
                    ->whereIn('supplier_invoice_no', $invoiceNos)
                    ->delete();
            }

            foreach ($cartItems as $cart) {
                $product = $cart->product;

                BlItemDetail::create([
                    'bl_id'                 => $bl->id,
                    'product_id'            => $cart->product_id,
                    'item_name'             => $cart->import_print_name ?: ($product->name ?? null),

                    'weight_per_carton'     => $cart->weight_per_carton,
                    'cbm_per_carton'        => $cart->cbm_per_carton,
                    'quantity'              => $cart->quantity,
                    'dollar_price'          => $cart->dollar_price,

                    'supplier_invoice_no'   => $cart->supplier_invoice_no,
                    'supplier_invoice_date' => $cart->supplier_invoice_date,

                    'total_no_of_packages'  => $cart->total_no_of_packages,
                    'total_weight'          => $cart->total_weight,
                    'total_cbm'             => $cart->total_cbm,

                    'supplier_id'           => $cart->supplier_id,
                    'import_photo_id'       => $cart->import_photo_id,
                ]);
            }

            // 3️⃣ CI DETAILS (supplier-wise) – UPDATE OR CREATE
            $bySupplier = $cartItems->filter(function ($row) {
                return !empty($row->supplier_id);
            })->groupBy('supplier_id');

            $ciMap = [];

            foreach ($bySupplier as $supplierId => $items) {

                $totalPackages = (int) $items->sum(function ($row) {
                    return (int) ($row->total_no_of_packages ?? 0);
                });

                $grossWeight = (float) $items->sum(function ($row) {
                    return (float) ($row->total_weight ?? 0);
                });

                $grossCbm = (float) $items->sum(function ($row) {
                    return (float) ($row->total_cbm ?? 0);
                });

                $netWeight = $grossWeight * 0.75;

                $firstWithInvoice = $items->first(function ($row) {
                    return !empty($row->supplier_invoice_no) || !empty($row->supplier_invoice_date);
                });

                $supplierInvoiceNo   = $firstWithInvoice->supplier_invoice_no   ?? null;
                $supplierInvoiceDate = $firstWithInvoice->supplier_invoice_date ?? null;

                $ciAttributes = [
                    'import_company_id'   => $importCompanyId,
                    'supplier_id'         => $supplierId,
                    'bl_id'               => $bl->id,
                    'supplier_invoice_no' => $supplierInvoiceNo,
                ];

                $ciData = [
                    'supplier_invoice_date' => $supplierInvoiceDate,
                    'no_of_packages'        => $totalPackages,
                    'gross_weight'          => $grossWeight,
                    'net_weight'            => $netWeight,
                    'gross_cbm'             => $grossCbm,
                    'pdf_path'              => null,
                ];

                $ci = CiDetail::updateOrCreate($ciAttributes, $ciData);

                $ciMap[$supplierId] = $ci;
            }

            // ⭐⭐⭐ YAHAN ADD KARO: supplier IDs ko comma-separated string bana ke BL me save karo
            if (!empty($ciMap)) {
                $supplierIds = array_keys($ciMap);                 // [1, 5, 7] etc.
                $supplierIdsStr = implode(',', $supplierIds);      // "1,5,7"

                $bl->supplier_id = $supplierIdsStr;                // column type VARCHAR / TEXT hona chahiye
                $bl->save();
            }
            // ⭐⭐⭐ yahi par BL me comma separated supplier ids store ho jayenge

            // 4️⃣ Clear old CI items & summaries for these CIs
            foreach ($ciMap as $ci) {
                CiItemDetail::where('ci_id', $ci->id)->delete();
                CiSummary::where('ci_id', $ci->id)->delete();
            }

            // 5️⃣ CI ITEM DETAILS
            foreach ($cartItems as $cart) {
                if (empty($cart->supplier_id)) {
                    continue;
                }

                if (!isset($ciMap[$cart->supplier_id])) {
                    continue;
                }

                $ci = $ciMap[$cart->supplier_id];

                CiItemDetail::create([
                    'ci_id'                => $ci->id,
                    'product_id'           => $cart->product_id,
                    'supplier_id'          => $cart->supplier_id,
                    'item_name'            => $cart->import_print_name ?: optional($cart->product)->name,
                    'weight_per_carton'    => $cart->weight_per_carton,
                    'cbm_per_carton'       => $cart->cbm_per_carton,
                    'quantity'             => $cart->quantity,
                    'dollar_price'         => $cart->dollar_price,
                    'total_no_of_packages' => $cart->total_no_of_packages,
                    'total_weight'         => $cart->total_weight,
                    'total_cbm'            => $cart->total_cbm,
                    'import_photo_id'      => $cart->import_photo_id,
                ]);
            }

            // 6️⃣ CI SUMMARY
            foreach ($ciMap as $supplierId => $ci) {

                $itemsGrouped = CiItemDetail::where('ci_id', $ci->id)
                    ->where('supplier_id', $supplierId)
                    ->get()
                    ->groupBy('item_name');

                foreach ($itemsGrouped as $itemName => $rows) {

                    $totalQty = (int) $rows->sum('quantity');

                    $totalValue = (float) $rows->sum(function ($row) {
                        return (float) $row->quantity * (float) $row->dollar_price;
                    });

                    $itemDollarPrice = $totalQty > 0
                                        ? round($totalValue / $totalQty, 2)   // 2 decimal places
                                        : 0.00;
                        
                    $updatedItemTotalValue = round($totalQty * $itemDollarPrice, 2);
                        

                    $cartonsTotal = (int) $rows->sum(function ($row) {
                        return (int) ($row->total_no_of_packages ?? 0);
                    });

                    $weightTotal = (float) $rows->sum(function ($row) {
                        return (float) ($row->total_weight ?? 0);
                    });

                    $cbmTotal = (float) $rows->sum(function ($row) {
                        return (float) ($row->total_cbm ?? 0);
                    });

                    $firstRow = $rows->first();

                    CiSummary::create([
                        'ci_id'             => $ci->id,
                        'supplier_id'       => $supplierId,
                        'item_print_name'   => $itemName,
                        'item_quantity'     => $totalQty,
                        'item_dollar_price' => $itemDollarPrice,
                        'summary_type'      => 'final ci summary',
                        'cartons_total'     => $cartonsTotal,
                        'weight_total'      => $weightTotal,
                        'cbm_total'         => $cbmTotal,
                        'value_total'       => $updatedItemTotalValue,
                        'import_photo_id'   => $firstRow->import_photo_id ?? null,
                    ]);
                }
            }
        });

        return redirect()
            ->route('import_bl.ci_supplier_summary', $blId)
            ->with('success', 'BL Items, CI details/items, and CI summary created/updated successfully from this cart.');
    }



    public function blCiSupplierSummary($blId)
    {
        $bl = BlDetail::with('importCompany')->findOrFail($blId);

        $ciIds = CiDetail::where('bl_id', $blId)->pluck('id');
        if ($ciIds->isEmpty()) {
            return redirect()
                ->route('import_bl_details.index', $bl->import_company_id)
                ->with('error', 'No CI details found for this BL.');
        }

        $summaryRows = CiSummary::with(['supplier', 'ci'])
            ->whereIn('ci_id', $ciIds)
            ->get();

        if ($summaryRows->isEmpty()) {
            return redirect()
                ->route('import_bl_details.index', $bl->import_company_id)
                ->with('error', 'No CI summary entries found for this BL.');
        }

        $supplierItems = $summaryRows->groupBy('supplier_id');

        $supplierRows = $supplierItems->map(function ($group, $supplierId) {
            $first        = $group->first();
            $supplierName = optional($first->supplier)->supplier_name ?? 'Unknown';

            $cartons = (float) $group->sum('cartons_total');
            $wt      = (float) $group->sum('weight_total');
            $cbm     = (float) $group->sum('cbm_total');
            $value   = (float) $group->sum('value_total');

            return (object) [
                'supplier_id'   => $supplierId,
                'supplier_name' => $supplierName,
                'cartons'       => $cartons,
                'wt'            => $wt,
                'cbm'           => $cbm,
                'value_usd'     => $value,
            ];
        })->values();

        $ciTotals = [
            'cartons' => (float) $summaryRows->sum('cartons_total'),
            'wt'      => (float) $summaryRows->sum('weight_total'),
            'cbm'     => (float) $summaryRows->sum('cbm_total'),
            'value'   => (float) $summaryRows->sum('value_total'),
        ];

        $blTotals = [
            'cartons' => (float) ($bl->no_of_packages ?? 0),
            'wt'      => (float) ($bl->gross_weight   ?? 0),
            'cbm'     => (float) ($bl->gross_cbm      ?? 0),
            'value'   => (float) $ciTotals['value'],
        ];

        $diffTotals = [
            'cartons' => $ciTotals['cartons'] - $blTotals['cartons'],
            'wt'      => $ciTotals['wt']      - $blTotals['wt'],
            'cbm'     => $ciTotals['cbm']     - $blTotals['cbm'],
            'value'   => $ciTotals['value']   - $blTotals['value'],
        ];

        return view('backend.imports.bl_details.supplier_summary', [
            'bl'            => $bl,
            'supplierRows'  => $supplierRows,
            'supplierItems' => $supplierItems,
            'ciTotals'      => $ciTotals,
            'blTotals'      => $blTotals,
            'diffTotals'    => $diffTotals,
        ]);
    }

    public function updateCiSupplierSummary(Request $request, $blId)
    {
        $itemsInput = $request->input('items', []);

        if (empty($itemsInput)) {
            return redirect()
                ->back()
                ->with('warning', 'No changes to save.');
        }

        DB::transaction(function () use ($itemsInput) {

            foreach ($itemsInput as $id => $data) {
                /** @var \App\Models\CiSummary|null $summary */
                $summary = CiSummary::find($id);
                if (!$summary) {
                    continue;
                }

                $qty        = array_key_exists('item_quantity', $data)
                    ? (float) $data['item_quantity']
                    : (float) $summary->item_quantity;

                $cartons    = array_key_exists('cartons_total', $data)
                    ? (float) $data['cartons_total']
                    : (float) $summary->cartons_total;

                $wt         = array_key_exists('weight_total', $data)
                    ? (float) $data['weight_total']
                    : (float) $summary->weight_total;

                $cbm        = array_key_exists('cbm_total', $data)
                    ? (float) $data['cbm_total']
                    : (float) $summary->cbm_total;

                $unitPrice  = array_key_exists('item_dollar_price', $data)
                    ? (float) $data['item_dollar_price']
                    : (float) $summary->item_dollar_price;

                $summary->item_print_name   = $data['item_print_name'] ?? $summary->item_print_name;
                $summary->item_quantity     = $qty;
                $summary->cartons_total     = $cartons;
                $summary->weight_total      = $wt;
                $summary->cbm_total         = $cbm;
                $summary->item_dollar_price = $unitPrice;

                $summary->value_total       = $qty * $unitPrice;

                $summary->save();
            }
        });

        return redirect()
            ->back()
            ->with('success', 'CI summary updated successfully.');
    }

    public function completeCiForBl($blId)
    {
        // BL + Import Company
        $bl = BlDetail::with('importCompany')->findOrFail($blId);

        // All CI headers for this BL (one per supplier)
        $ciDetails = CiDetail::with(['supplier.defaultBankAccount', 'supplier.bankAccounts'])
            ->where('bl_id', $blId)
            ->get();

        if ($ciDetails->isEmpty()) {
            return redirect()
                ->route('import_bl.ci_supplier_summary', $blId)
                ->with('error', 'No CI details found for this BL.');
        }

        // All CI ids
        $ciIds = $ciDetails->pluck('id');

        // All CI summary rows for this BL (supplier-wise item summary)
        $summaryRows = CiSummary::with('supplier')
            ->whereIn('ci_id', $ciIds)
            ->get();

        if ($summaryRows->isEmpty()) {
            return redirect()
                ->route('import_bl.ci_supplier_summary', $blId)
                ->with('error', 'No CI summary entries found for this BL.');
        }

        // 🔹 BL status ko pending kar do (jab sab CI data ready hai)
        $bl->status = 'pending';   // apne status ke hisaab se value adjust kar sakte ho
        $bl->save();

        // 🔹 IMPORT CART ko clear karo (iss BL + company ka)
        // ImportCart::where('bl_detail_id', $blId)
        //     ->where('import_company_id', $bl->import_company_id)
        //     ->delete();

        // Group summary rows by supplier
        $bySupplier = $summaryRows->groupBy('supplier_id');

        // Build invoice structures per supplier
        $invoices = collect();

        foreach ($bySupplier as $supplierId => $rows) {
            /** @var \Illuminate\Support\Collection $rows */

            // For each supplier, we assume **one** CiDetail (per your proceed logic)
            $ci = $ciDetails->firstWhere('supplier_id', $supplierId);
            if (!$ci) {
                continue;
            }

            $supplier = $ci->supplier;

            // Totals per supplier from summary rows
            $totCartons = (float) $rows->sum('cartons_total');
            $totWt      = (float) $rows->sum('weight_total');
            $totCbm     = (float) $rows->sum('cbm_total');
            $totValue   = (float) $rows->sum('value_total');
            $totalQty   = (float) $rows->sum('item_quantity');

            $invoices->push((object) [
                'ci'        => $ci,        // has supplier_invoice_no, supplier_invoice_date, supplier_id
                'supplier'  => $supplier,  // supplier model (+ defaultBankAccount, bankAccounts)
                'rows'      => $rows,      // CiSummary rows
                'totals'    => [
                    'cartons' => $totCartons,
                    'wt'      => $totWt,
                    'cbm'     => $totCbm,
                    'value'   => $totValue,
                    'qty'     => $totalQty,
                ],
            ]);
        }

        if ($invoices->isEmpty()) {
            return redirect()
                ->route('import_bl.ci_supplier_summary', $blId)
                ->with('error', 'No supplier-wise CI summary could be built.');
        }

        // ✅ High-entropy token (microtime + uniqid) to bust cache
        $micro  = microtime(true);
        $token  = str_replace('.', '', (string) $micro) . '_' . uniqid();

        // Temp folder for files
        $tempDir = storage_path('app/tmp_ci_pl');
        if (!\Illuminate\Support\Facades\File::exists($tempDir)) {
            \Illuminate\Support\Facades\File::makeDirectory($tempDir, 0755, true);
        }

        $generatedFiles = [];

        // For each supplier invoice object, generate **own CI + PL PDF**
        foreach ($invoices as $invoice) {
            /** @var \App\Models\CiDetail $ci */
            $ci = $invoice->ci;

            // Base invoice number for filenames: supplier_invoice_no OR fallback BL+supplier
            $rawInvoiceNo = $ci->supplier_invoice_no
                ?: (($bl->bl_no ?? $bl->id) . '_S' . $ci->supplier_id);

            // Filename-safe version
            $baseInvoiceNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $rawInvoiceNo);

            // Filenames for this supplier (token added)
            $ciFileName = 'CI_' . $baseInvoiceNo . '_' . $token . '.pdf';
            $plFileName = 'PL_' . $baseInvoiceNo . '_' . $token . '.pdf';

            $ciPath = $tempDir . '/' . $ciFileName;
            $plPath = $tempDir . '/' . $plFileName;

            // Wrap in a mini-collection because blades loop over $invoices
            $singleInvoiceCollection = collect([$invoice]);

            // 🔹 Generate Commercial Invoice PDF for THIS supplier
            $pdfCI = \Barryvdh\DomPDF\Facade\Pdf::loadView(
                'backend.imports.ci.commercial_invoice_pdf',
                [
                    'bl'       => $bl,
                    'company'  => $bl->importCompany,
                    'invoices' => $singleInvoiceCollection,
                ]
            )->setPaper('A4', 'landscape');

            // 🔹 Generate Packing List PDF for THIS supplier
            $pdfPL = \Barryvdh\DomPDF\Facade\Pdf::loadView(
                'backend.imports.ci.packing_list_pdf',
                [
                    'bl'       => $bl,
                    'company'  => $bl->importCompany,
                    'invoices' => $singleInvoiceCollection,
                ]
            )->setPaper('A4', 'landscape');

            // Save both PDFs to disk
            $pdfCI->save($ciPath);
            $pdfPL->save($plPath);

            $generatedFiles[] = $ciPath;
            $generatedFiles[] = $plPath;
        }

        if (empty($generatedFiles)) {
            return redirect()
                ->route('import_bl.ci_supplier_summary', $blId)
                ->with('error', 'No PDFs could be generated for suppliers.');
        }

        // 🔹 Create a ZIP containing all suppliers' CI+PL PDFs (unique name with token)
        $zipBase = $bl->bl_no ?? ('BL' . $bl->id);
        $zipBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $zipBase);

        $zipName = 'CI_PL_' . $zipBase . '_' . $token . '.zip';
        $zipPath = $tempDir . '/' . $zipName;

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($generatedFiles as $filePath) {
                $zip->addFile($filePath, basename($filePath));
            }
            $zip->close();
        }

        // Delete individual PDFs; keep only ZIP to send
        \Illuminate\Support\Facades\File::delete($generatedFiles);

        // after ZIP created & individual PDFs deleted
       $this->sendCiPlZipEmail($bl, $zipPath, $zipName);

        // Download ZIP with no-cache headers
        return response()->download($zipPath, $zipName, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ])->deleteFileAfterSend(true);
    }


    private function sendCiPlZipEmail($bl, string $zipPath, string $zipName): void
    {
        $user    = auth()->user();
        $toEmail = 'dipak.mazing@gmail.com';
        $toName  = 'Imports Team';

        $subject = 'CI & Packing List ZIP for BL: ' . ($bl->bl_no ?? $bl->id);

        $htmlBody = view('backend.imports.ci.email_ci_pl_zip', [
            'bl'   => $bl,
            'name' => $toName,
        ])->render();

        /** @var NoReplyMailer $mailer */
        $mailer = app(NoReplyMailer::class);

        $ok = $mailer->sendWithZip(
            $toEmail,
            $toName,
            $subject,
            $htmlBody,
            $zipPath,
            $zipName
        );

       

        

        if (!$ok) {
            \Log::warning('CI/PL ZIP email NOT sent for BL ID: ' . $bl->id);
        }
    }

    protected function _sendCiPlZipEmail(BlDetail $bl, string $zipPath, string $zipName): void
    {
        // You can later change this to company email, etc.
        $toEmail = 'dipak.mazing@gmail.com';

        try {
            Mail::to($toEmail)->send(
                new CiPlZipMail($bl, $zipPath, $zipName)
            );
            // No dd() here in production
        } catch (\Throwable $e) {
            // Optional: log error
            \Log::error('CI+PL ZIP mail failed: ' . $e->getMessage(), [
                'bl_id'    => $bl->id,
                'zip_path' => $zipPath,
            ]);
        }
    }


    // ✅ ONLY CI ZIP download (all suppliers CI PDFs)
    public function downloadCiZipForBl($blId)
    {
        $bl = BlDetail::with('importCompany')->findOrFail($blId);

        $ciDetails = CiDetail::with(['supplier.defaultBankAccount', 'supplier.bankAccounts'])
            ->where('bl_id', $blId)
            ->get();

        if ($ciDetails->isEmpty()) {
            return back()->with('error', 'No CI details found for this BL.');
        }

        $ciIds = $ciDetails->pluck('id');

        $summaryRows = CiSummary::with('supplier')
            ->whereIn('ci_id', $ciIds)
            ->get();

        if ($summaryRows->isEmpty()) {
            return back()->with('error', 'No CI summary entries found for this BL.');
        }

        $bySupplier = $summaryRows->groupBy('supplier_id');
        $invoices = collect();

        foreach ($bySupplier as $supplierId => $rows) {
            $ci = $ciDetails->firstWhere('supplier_id', $supplierId);
            if (!$ci) continue;

            $supplier = $ci->supplier;

            $invoices->push((object)[
                'ci' => $ci,
                'supplier' => $supplier,
                'rows' => $rows,
                'totals' => [
                    'cartons' => (float)$rows->sum('cartons_total'),
                    'wt'      => (float)$rows->sum('weight_total'),
                    'cbm'     => (float)$rows->sum('cbm_total'),
                    'value'   => (float)$rows->sum('value_total'),
                    'qty'     => (float)$rows->sum('item_quantity'),
                ],
            ]);
        }

        if ($invoices->isEmpty()) {
            return back()->with('error', 'No supplier-wise CI summary could be built.');
        }

        $micro = microtime(true);
        $token = str_replace('.', '', (string)$micro).'_'.uniqid();

        $tempDir = storage_path('app/tmp_ci_pl');
        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $generatedFiles = [];

        foreach ($invoices as $invoice) {
            $ci = $invoice->ci;

            $rawInvoiceNo = $ci->supplier_invoice_no
                ?: (($bl->bl_no ?? $bl->id) . '_S' . $ci->supplier_id);

            $baseInvoiceNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $rawInvoiceNo);

            $ciFileName = 'CI_' . $baseInvoiceNo . '_' . $token . '.pdf';
            $ciPath = $tempDir . '/' . $ciFileName;

            $singleInvoiceCollection = collect([$invoice]);

            $pdfCI = Pdf::loadView(
                'backend.imports.ci.commercial_invoice_pdf',
                [
                    'bl'       => $bl,
                    'company'  => $bl->importCompany,
                    'invoices' => $singleInvoiceCollection,
                ]
            )->setPaper('A4', 'landscape');

            $pdfCI->save($ciPath);
            $generatedFiles[] = $ciPath;
        }

        if (empty($generatedFiles)) {
            return back()->with('error', 'No CI PDFs could be generated.');
        }

        $zipBase = $bl->bl_no ?? ('BL' . $bl->id);
        $zipBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $zipBase);

        $zipName = 'CI_ONLY_' . $zipBase . '_' . $token . '.zip';
        $zipPath = $tempDir . '/' . $zipName;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($generatedFiles as $filePath) {
                $zip->addFile($filePath, basename($filePath));
            }
            $zip->close();
        }

        File::delete($generatedFiles);

        return response()->download($zipPath, $zipName, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ])->deleteFileAfterSend(true);
    }


    // ✅ ONLY PL ZIP download (all suppliers PL PDFs)
    public function downloadPlZipForBl($blId)
    {
        $bl = BlDetail::with('importCompany')->findOrFail($blId);

        $ciDetails = CiDetail::with(['supplier.defaultBankAccount', 'supplier.bankAccounts'])
            ->where('bl_id', $blId)
            ->get();

        if ($ciDetails->isEmpty()) {
            return back()->with('error', 'No CI details found for this BL.');
        }

        $ciIds = $ciDetails->pluck('id');

        $summaryRows = CiSummary::with('supplier')
            ->whereIn('ci_id', $ciIds)
            ->get();

        if ($summaryRows->isEmpty()) {
            return back()->with('error', 'No CI summary entries found for this BL.');
        }

        $bySupplier = $summaryRows->groupBy('supplier_id');
        $invoices = collect();

        foreach ($bySupplier as $supplierId => $rows) {
            $ci = $ciDetails->firstWhere('supplier_id', $supplierId);
            if (!$ci) continue;

            $supplier = $ci->supplier;

            $invoices->push((object)[
                'ci' => $ci,
                'supplier' => $supplier,
                'rows' => $rows,
                'totals' => [
                    'cartons' => (float)$rows->sum('cartons_total'),
                    'wt'      => (float)$rows->sum('weight_total'),
                    'cbm'     => (float)$rows->sum('cbm_total'),
                    'value'   => (float)$rows->sum('value_total'),
                    'qty'     => (float)$rows->sum('item_quantity'),
                ],
            ]);
        }

        if ($invoices->isEmpty()) {
            return back()->with('error', 'No supplier-wise CI summary could be built.');
        }

        $micro = microtime(true);
        $token = str_replace('.', '', (string)$micro).'_'.uniqid();

        $tempDir = storage_path('app/tmp_ci_pl');
        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $generatedFiles = [];

        foreach ($invoices as $invoice) {
            $ci = $invoice->ci;

            $rawInvoiceNo = $ci->supplier_invoice_no
                ?: (($bl->bl_no ?? $bl->id) . '_S' . $ci->supplier_id);

            $baseInvoiceNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $rawInvoiceNo);

            $plFileName = 'PL_' . $baseInvoiceNo . '_' . $token . '.pdf';
            $plPath = $tempDir . '/' . $plFileName;

            $singleInvoiceCollection = collect([$invoice]);

            $pdfPL = Pdf::loadView(
                'backend.imports.ci.packing_list_pdf',
                [
                    'bl'       => $bl,
                    'company'  => $bl->importCompany,
                    'invoices' => $singleInvoiceCollection,
                ]
            )->setPaper('A4', 'landscape');

            $pdfPL->save($plPath);
            $generatedFiles[] = $plPath;
        }

        if (empty($generatedFiles)) {
            return back()->with('error', 'No PL PDFs could be generated.');
        }

        $zipBase = $bl->bl_no ?? ('BL' . $bl->id);
        $zipBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $zipBase);

        $zipName = 'PL_ONLY_' . $zipBase . '_' . $token . '.zip';
        $zipPath = $tempDir . '/' . $zipName;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($generatedFiles as $filePath) {
                $zip->addFile($filePath, basename($filePath));
            }
            $zip->close();
        }

        File::delete($generatedFiles);

        return response()->download($zipPath, $zipName, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ])->deleteFileAfterSend(true);
    }


}
