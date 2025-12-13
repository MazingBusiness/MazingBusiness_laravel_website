<?php

namespace App\Http\Controllers;

use App\Models\ImportPo;
use App\Models\ImportPoItem;
use App\Models\ImportCompany;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
class ImportPoController extends Controller
{


	public function importProductsAjaxSearch(Request $request)
    {
        $term = trim($request->get('q', ''));

        if ($term === '') {
            return response()->json([
                'results' => []
            ]);
        }

        $products = Product::query()
            ->with(['importSupplier:id,supplier_name']) // relation already in Product model
            ->where(function ($q) use ($term) {
                $q->where('part_no', 'like', "%{$term}%")
                  ->orWhere('name', 'like', "%{$term}%");
            })
            ->orderBy('part_no')
            ->limit(50)
            ->get([
                'id',
                'part_no',
                'name',
                'import_print_name',
                'weight_per_carton',
                'cbm_per_carton',
                'quantity_per_carton',
                'dollar_price',
                'supplier_id',
                'import_photos',        // multiple IDs, comma separated
                'import_thumbnail_img', // single ID
            ]);

        $results = $products->map(function ($p) {
            // ---- choose best image id ----
            $photoId = null;

            if (!empty($p->import_thumbnail_img)) {
                $photoId = $p->import_thumbnail_img;
            } elseif (!empty($p->import_photos)) {
                // if comma separated ids, pick the first one
                $parts = explode(',', $p->import_photos);
                if (!empty($parts[0])) {
                    $photoId = trim($parts[0]);
                }
            }

            return [
                'id'                  => $p->id,
                'text'                => $p->part_no . ' — ' . $p->name,
                'import_print_name'   => $p->import_print_name,
                'weight_per_carton'   => $p->weight_per_carton,
                'cbm_per_carton'      => $p->cbm_per_carton,
                'quantity_per_carton' => $p->quantity_per_carton,
                'dollar_price'        => $p->dollar_price,
                'supplier_id'         => $p->supplier_id,
                'supplier_name'       => optional($p->importSupplier)->supplier_name,

                // NEW: image info for PO modal
                'import_photo_id'     => $photoId,
                'import_photo_url'    => $photoId ? uploaded_asset($photoId) : null,
            ];
        });

        return response()->json([
            'results' => $results,
        ]);
    }
    /**
     * Show Create PO form
     */
    public function create()
    {
        $importCompanies = ImportCompany::orderBy('company_name')->get();
        $suppliers       = Supplier::orderBy('supplier_name')->get();

        return view('backend.import_po.create', compact('importCompanies', 'suppliers'));
    }

    /**
     * Store PO + items
     */
   public function store(Request $request)
   {
        $request->validate([
            'import_company_id' => 'required|exists:import_companies,id',
            'supplier_id'       => 'required|exists:suppliers,id',
            'po_no'             => 'required|string|max:100',
            'po_date'           => 'required|date',
            'currency_code'     => 'required|string|max:10',

            'lines.supplier_model_no.*' => 'nullable|string|max:100',
            'lines.description.*'       => 'nullable|string',
            'lines.requirement_qty.*'   => 'nullable|numeric|min:0',
            'lines.unit_price_usd.*'    => 'nullable|numeric|min:0',
            'lines.unit_price_rmb.*'    => 'nullable|numeric|min:0',
            // packing_details JSON optional; no special validation
            'lines.packing_details.*'   => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // ---------- PO HEADER ----------
            $po = new ImportPo();
            $po->po_no             = $request->po_no;
            $po->import_company_id = $request->import_company_id;
            $po->supplier_id       = $request->supplier_id;
            $po->po_date           = $request->po_date;
            $po->currency_code     = $request->currency_code;
            $po->delivery_terms    = $request->delivery_terms;
            $po->payment_terms     = $request->payment_terms;
            $po->remarks           = $request->remarks;
            $po->status            = 'draft';
            // $po->created_by        = Auth::id();
            // $po->updated_by        = Auth::id();
            $po->total_qty         = 0;
            $po->total_value_usd   = 0;
            $po->total_value_rmb   = 0;
            $po->save();

            // ---------- LINE ARRAYS ----------
            $lines = $request->input('lines', []);

            $supplierModelLines   = $lines['supplier_model_no']    ?? [];
            $descriptionLines     = $lines['description']          ?? [];
            $reqQtyLines          = $lines['requirement_qty']      ?? [];
            $unitPriceUsdLines    = $lines['unit_price_usd']       ?? [];
            $unitPriceRmbLines    = $lines['unit_price_rmb']       ?? [];
            $packagingLines       = $lines['packaging']            ?? [];
            $remarksLines         = $lines['remarks']              ?? [];
            $weightLines          = $lines['weight_per_carton_kg'] ?? [];
            $cbmLines             = $lines['cbm_per_carton']       ?? [];
            $productIdLines       = $lines['product_id']           ?? [];
            $photoIdLines         = $lines['photo_id']             ?? [];
            $packingDetailsLines  = $lines['packing_details']      ?? []; // 👈 JSON aa raha hai
            $qtyPerCartonLines    = $lines['qty_per_carton']       ?? [];

            $count = max(
                count($supplierModelLines),
                count($descriptionLines),
                count($reqQtyLines),
                count($unitPriceUsdLines),
                count($unitPriceRmbLines),
                count($packagingLines),
                count($remarksLines),
                count($weightLines),
                count($cbmLines),
                count($productIdLines),
                count($photoIdLines),
                count($packingDetailsLines),
                count($qtyPerCartonLines)
            );

            $lineNo        = 1;
            $totalQty      = 0;
            $totalValueUsd = 0;
            $totalValueRmb = 0;

            for ($i = 0; $i < $count; $i++) {
                $supplierModelNo = trim((string)($supplierModelLines[$i]   ?? ''));
                $description     = trim((string)($descriptionLines[$i]     ?? ''));
                $requirementQty  = (float)($reqQtyLines[$i]                ?? 0);

                $unitPriceUsd = isset($unitPriceUsdLines[$i]) && $unitPriceUsdLines[$i] !== ''
                    ? (float)$unitPriceUsdLines[$i]
                    : null;

                $unitPriceRmb = isset($unitPriceRmbLines[$i]) && $unitPriceRmbLines[$i] !== ''
                    ? (float)$unitPriceRmbLines[$i]
                    : null;

                $packaging    = trim((string)($packagingLines[$i]          ?? ''));
                $remarks      = trim((string)($remarksLines[$i]            ?? ''));

                $weightPerCtn = isset($weightLines[$i]) && $weightLines[$i] !== ''
                    ? (float)$weightLines[$i]
                    : null;

                $cbmPerCtn    = isset($cbmLines[$i]) && $cbmLines[$i] !== ''
                    ? (float)$cbmLines[$i]
                    : null;

                $productId    = $productIdLines[$i]   ?? null;
                $photoId      = $photoIdLines[$i]     ?? null;

                $qtyPerCarton = isset($qtyPerCartonLines[$i]) && $qtyPerCartonLines[$i] !== ''
                    ? (float)$qtyPerCartonLines[$i]
                    : null;

                // 👇 packing details JSON
                $packingDetailsJson = null;
                $rawPackingJson     = $packingDetailsLines[$i] ?? null;
                if (!is_null($rawPackingJson) && trim((string)$rawPackingJson) !== '') {
                    $rawPackingJson = (string)$rawPackingJson;
                    $decoded = json_decode($rawPackingJson, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        // normalized JSON store
                        $packingDetailsJson = json_encode($decoded);
                    } else {
                        // agar parse fail ho gaya to as-it-is string rakh sakte ho
                        $packingDetailsJson = $rawPackingJson;
                    }
                }

                // ---- EMPTY LINE SKIP ----
                if (
                    $supplierModelNo === '' &&
                    $description === '' &&
                    $requirementQty == 0 &&
                    $unitPriceUsd === null &&
                    $unitPriceRmb === null &&
                    $productId === null
                ) {
                    continue;
                }

                // ---- CREATE ITEM ----
                $item = new ImportPoItem();
                $item->import_po_id          = $po->id;
                // $item->line_no               = $lineNo++;
                $item->product_id            = $productId;
                $item->photo_id              = $photoId;
                $item->supplier_model_no     = $supplierModelNo;
                $item->description           = $description;
                $item->packing_details       = $packingDetailsJson; // 👈 JSON string store ho raha
                $item->requirement_qty       = $requirementQty;
                $item->unit_price_usd        = $unitPriceUsd;
                $item->unit_price_rmb        = $unitPriceRmb;
                $item->packaging             = $packaging;
                $item->weight_per_carton_kg  = $weightPerCtn;
                $item->cbm_per_carton        = $cbmPerCtn;
                $item->quantity_allocated    = 0;
                $item->quantity_balance      = $requirementQty;
                $item->remarks               = $remarks;
                $item->save();

                // totals
                $totalQty += $requirementQty;
                if ($unitPriceUsd !== null) {
                    $totalValueUsd += $requirementQty * $unitPriceUsd;
                }
                if ($unitPriceRmb !== null) {
                    $totalValueRmb += $requirementQty * $unitPriceRmb;
                }

                // ---- OPTIONAL PRODUCT MASTER UPDATE ----
                if (!empty($productId)) {
                    $product = Product::find($productId);
                    if ($product) {
                        $dirty = false;

                        if ($photoId && empty($product->import_photos)) {
                            $product->import_photos = $photoId;
                            $dirty = true;
                        }
                        if ($photoId && empty($product->import_thumbnail_img)) {
                            $product->import_thumbnail_img = $photoId;
                            $dirty = true;
                        }

                        if ($weightPerCtn !== null && $product->weight_per_carton === null) {
                            $product->weight_per_carton = $weightPerCtn;
                            $dirty = true;
                        }
                        if ($cbmPerCtn !== null && $product->cbm_per_carton === null) {
                            $product->cbm_per_carton = $cbmPerCtn;
                            $dirty = true;
                        }
                        if ($qtyPerCarton !== null && $product->quantity_per_carton === null) {
                            $product->quantity_per_carton = $qtyPerCarton;
                            $dirty = true;
                        }
                        if ($unitPriceUsd !== null && $product->dollar_price === null) {
                            $product->dollar_price = $unitPriceUsd;
                            $dirty = true;
                        }

                        if ($dirty) {
                            $product->save();
                        }
                    }
                }
            }

            $po->total_qty       = $totalQty;
            $po->total_value_usd = $totalValueUsd;
            $po->total_value_rmb = $totalValueRmb;
            $po->save();

            DB::commit();

            return redirect()
                ->route('import_pos.create')
                ->with('success', 'Import PO created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Error storing Import PO', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create Import PO. ' . $e->getMessage());
        }
   }




}
