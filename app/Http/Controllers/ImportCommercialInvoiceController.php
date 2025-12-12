<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ImportCompany;
use Illuminate\Support\Facades\File; // 👈 add this
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\Supplier;
use App\Models\SupplierBankAccount;
use App\Models\BlDetail;

use App\Models\CategoryGroup;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Upload;
use App\Models\ImportCart;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use CoreComponentRepository;

use App\Models\BlItemDetail;
use App\Models\CiDetail;
use App\Models\CiItemDetail;
use Illuminate\Support\Facades\DB;

class ImportCommercialInvoiceController extends Controller
{
    /**
     * LIST page: show all import companies
     */
    public function importCompaniesIndex(Request $request)
    {
        $query = ImportCompany::query();

        // 🔍 Search handling
        if ($search = trim($request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('state', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%")
                  ->orWhere('iec_no', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // per page rows (yaha change karke control kar sakte ho)
        $companies = $query
            ->orderBy('company_name')
            ->paginate(10) // 10 rows per page
            ->appends($request->only('search')); // 🔁 search term pagination me carry karega

        return view('backend.imports.import_companies.index', compact('companies'));
    }



    public function getStatesByCountry(Request $request)
    {
        $countryId = $request->input('country_id');

        $states = State::where('country_id', $countryId)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($states);
    }

    public function getCitiesByState(Request $request)
    {
        $stateId = $request->input('state_id');

        $cities = City::where('state_id', $stateId)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($cities);
    }

    public function checkGstin(Request $request)
    {
        $gstin = trim($request->get('gstin', ''));

        if ($gstin === '') {
            return response()->json([
                'exists' => false
            ]);
        }

        $exists = ImportCompany::where('gstin', $gstin)->exists();

        return response()->json([
            'exists' => $exists
        ]);
    }

    /**
     * ADD page: show create form
     */
    public function importCompanyCreate()
    {
        // sirf active countries (agar status column use kar rahe ho)
        $countries = Country::where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);


        return view('backend.imports.import_companies.create', compact('countries'));
    }

    /**
     * Store new import company
     */
    public function importCompanyStore(Request $request)
    {
        $validated = $request->validate([
                'company_name' => 'required|string|max:255',
                'address_1'    => 'required|string|max:255',
                'address_2'    => 'nullable|string|max:255',
                'city'         => 'required|string|max:100',
                'pincode'      => 'required|string|max:20',
                'state'        => 'required|string|max:100',
                'country'      => 'required|string|max:100',
                'gstin'        => 'nullable|string|max:30|unique:import_companies,gstin',
                'iec_no'       => 'nullable|string|max:30',
                'email'        => 'nullable|email|max:191',
                'phone'        => 'nullable|string|max:50',
                'buyer_stamp'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

        // yahan ab sirf relative path rakhenge
        $buyerStampPath = null;

        if ($request->hasFile('buyer_stamp')) {
            $file = $request->file('buyer_stamp');

            // folder: public/import_commercial_invoice
            $folder = 'import_commercial_invoice';
            $destinationPath = public_path($folder);

            // create folder if not exists
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            // unique file name
            $filename = uniqid('buyer_stamp_') . '.' . $file->getClientOriginalExtension();

            // move file to /public/import_commercial_invoice
            $file->move($destinationPath, $filename);

            // ✅ Sirf relative path store karna hai
            // eg: "import_commercial_invoice/buyer_stamp_xxx.jpeg"
            $buyerStampPath = $folder . '/' . $filename;
        }

        ImportCompany::create([
            'company_name' => $validated['company_name'],
            'address_1'    => $validated['address_1'],
            'address_2'    => $validated['address_2'] ?? null,
            'city'         => $validated['city'],
            'pincode'      => $validated['pincode'],
            'state'        => $validated['state'],
            'country'      => $validated['country'],
            'gstin'        => $validated['gstin'] ?? null,
            'iec_no'       => $validated['iec_no'] ?? null,
            'email'        => $validated['email'] ?? null,
            'phone'        => $validated['phone'] ?? null,
            'buyer_stamp'  => $buyerStampPath,   // 👈 ab sirf relative path
        ]);

        return redirect()
            ->route('import_companies.index')
            ->with('success', 'Import company created successfully.');
    }


    public function importCompanyEdit($id)
    {
        $company   = ImportCompany::findOrFail($id);
        $countries = Country::where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('backend.imports.import_companies.edit', compact('company', 'countries'));
    }

    public function importCompanyUpdate(Request $request, $id)
    {
        $company = ImportCompany::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'address_1'    => 'required|string|max:255',
            'address_2'    => 'nullable|string|max:255',
            'city'         => 'required|string|max:100',
            'pincode'      => 'required|string|max:20',
            'state'        => 'required|string|max:100',
            'country'      => 'required|string|max:100',
            'gstin'        => 'nullable|string|max:30',
            'iec_no'       => 'nullable|string|max:30',
            'email'        => 'nullable|email|max:191',
            'phone'        => 'nullable|string|max:50',
            'buyer_stamp'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $buyerStampPath = $company->buyer_stamp; // keep old by default

        if ($request->hasFile('buyer_stamp')) {
            $file   = $request->file('buyer_stamp');
            $folder = 'import_commercial_invoice';
            $destinationPath = public_path($folder);

            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            // delete old file if exists
            if ($buyerStampPath) {
                $oldFullPath = public_path($buyerStampPath);
                if (File::exists($oldFullPath)) {
                    File::delete($oldFullPath);
                }
            }

            $filename = uniqid('buyer_stamp_') . '.' . $file->getClientOriginalExtension();
            $file->move($destinationPath, $filename);

            // relative path only
            $buyerStampPath = $folder . '/' . $filename;
        }

        $company->update([
            'company_name' => $validated['company_name'],
            'address_1'    => $validated['address_1'],
            'address_2'    => $validated['address_2'] ?? null,
            'city'         => $validated['city'],
            'pincode'      => $validated['pincode'],
            'state'        => $validated['state'],
            'country'      => $validated['country'],
            'gstin'        => $validated['gstin'] ?? null,
            'iec_no'       => $validated['iec_no'] ?? null,
            'email'        => $validated['email'] ?? null,
            'phone'        => $validated['phone'] ?? null,
            'buyer_stamp'  => $buyerStampPath,
        ]);

        return redirect()
            ->route('import_companies.index')
            ->with('success', 'Import company updated successfully.');
    }

    public function importCompanyDestroy($id)
    {
        $company = ImportCompany::findOrFail($id);

        // buyer_stamp file delete karna (relative path stored)
        if ($company->buyer_stamp) {
            $filePath = public_path($company->buyer_stamp);

            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        }

        // record delete
        $company->delete();

        return redirect()
            ->route('import_companies.index')
            ->with('success', 'Import company deleted successfully.');
    }


    public function suppliersIndex(Request $request)
    {
        $query = Supplier::query();

        // 🔍 Search handling
        if ($search = trim($request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('supplier_name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('district', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%")
                  ->orWhere('contact', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // with counts for BL / CI / Summary (optional, UI me use kiya hai)
        $suppliers = $query
            ->withCount([
                'blDetails as bl_count',
                'ciDetails as ci_count',
                'ciSummaries as ci_summary_count',
            ])
            ->orderBy('id')
            ->paginate(10)
            ->appends($request->only('search'));

        return view('backend.imports.suppliers.index', compact('suppliers'));
    }

    public function supplierCreate()
    {
        // Import Company jaise hi active countries
        $countries = Country::where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('backend.imports.suppliers.create', compact('countries'));
    }

    /**
     * STORE: Save new supplier
     */
     public function supplierStore(Request $request)
    {
        $validated = $request->validate([
            'supplier_name' => 'required|string|max:255',
            'address'       => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:100',
            'district'      => 'nullable|string|max:100', // yaha state/district save hoga
            'country'       => 'nullable|string|max:100',
            'zip_code'      => 'nullable|string|max:20',
            'contact'       => 'nullable|string|max:50',
            'email'         => 'nullable|email|max:191',
    
            // stamp
            'stamp'         => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
    
            // bank accounts (new)
            'bank_accounts'                                => 'nullable|array',
            'bank_accounts.*.currency'                     => 'nullable|string|max:10',
            'bank_accounts.*.intermediary_bank_name'       => 'nullable|string|max:191',
            'bank_accounts.*.intermediary_swift_code'      => 'nullable|string|max:50',
            'bank_accounts.*.account_bank_name'            => 'nullable|string|max:191',
            'bank_accounts.*.account_swift_code'           => 'nullable|string|max:50',
            'bank_accounts.*.account_bank_address'         => 'nullable|string',
            'bank_accounts.*.beneficiary_name'             => 'nullable|string|max:191',
            'bank_accounts.*.beneficiary_address'          => 'nullable|string',
            'bank_accounts.*.account_number'               => 'nullable|string|max:50',
            'bank_accounts.*.is_default'                   => 'nullable|boolean',
        ]);
    
        $stampPath = null;
    
        if ($request->hasFile('stamp')) {
            $file = $request->file('stamp');
    
            $folder = 'import_suppliers_stamps';
            $destinationPath = public_path($folder);
    
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
    
            $filename = uniqid('supplier_stamp_') . '.' . $file->getClientOriginalExtension();
            $file->move($destinationPath, $filename);
    
            // sirf relative path store karenge
            $stampPath = $folder . '/' . $filename;
        }
    
        DB::transaction(function () use ($validated, $stampPath) {
    
            // 1) Supplier create
            /** @var \App\Models\Supplier $supplier */
            $supplier = Supplier::create([
                'supplier_name' => $validated['supplier_name'],
                'address'       => $validated['address'] ?? null,
                'city'          => $validated['city'] ?? null,
                'district'      => $validated['district'] ?? null, // state/district
                'country'       => $validated['country'] ?? null,
                'zip_code'      => $validated['zip_code'] ?? null,
                'contact'       => $validated['contact'] ?? null,
                'email'         => $validated['email'] ?? null,
                // old `bank_details` field removed
                'stamp'         => $stampPath,
            ]);
    
            // 2) Bank accounts create (if any submitted)
            if (!empty($validated['bank_accounts']) && is_array($validated['bank_accounts'])) {
    
                $hasDefault = false;
    
                foreach ($validated['bank_accounts'] as $bankAccount) {
    
                    // skip completely empty bank rows
                    $fieldsToCheck = [
                        'currency',
                        'intermediary_bank_name',
                        'intermediary_swift_code',
                        'account_bank_name',
                        'account_swift_code',
                        'account_bank_address',
                        'beneficiary_name',
                        'beneficiary_address',
                        'account_number',
                    ];
    
                    $allEmpty = true;
                    foreach ($fieldsToCheck as $field) {
                        if (!empty($bankAccount[$field])) {
                            $allEmpty = false;
                            break;
                        }
                    }
    
                    if ($allEmpty) {
                        continue;
                    }
    
                    // only one default – first checked wins
                    $isDefault = !empty($bankAccount['is_default']) && !$hasDefault;
                    if ($isDefault) {
                        $hasDefault = true;
                    }
    
                    SupplierBankAccount::create([
                        'supplier_id'              => $supplier->id,
                        'currency'                 => $bankAccount['currency'] ?? 'USD',
                        'intermediary_bank_name'   => $bankAccount['intermediary_bank_name'] ?? null,
                        'intermediary_swift_code'  => $bankAccount['intermediary_swift_code'] ?? null,
                        'account_bank_name'        => $bankAccount['account_bank_name'] ?? null,
                        'account_swift_code'       => $bankAccount['account_swift_code'] ?? null,
                        'account_bank_address'     => $bankAccount['account_bank_address'] ?? null,
                        'beneficiary_name'         => $bankAccount['beneficiary_name'] ?? null,
                        'beneficiary_address'      => $bankAccount['beneficiary_address'] ?? null,
                        'account_number'           => $bankAccount['account_number'] ?? null,
                        'is_default'               => $isDefault ? 1 : 0,
                    ]);
                }
            }
        });
    
        return redirect()
            ->route('import_suppliers.index')
            ->with('success', 'Supplier added successfully.');
    }

    public function storeBlDetail(Request $request)
    {
        $validated = $request->validate([
            'import_company_id' => 'required|exists:import_companies,id',
            'bl_no'             => 'required|string|max:191',
            'ob_date'           => 'nullable|date',
            'vessel_name'       => 'nullable|string|max:191',
            'no_of_packages'    => 'nullable|integer',
            'gross_weight'      => 'nullable|numeric',
            'net_weight'        => 'nullable|numeric',
            'gross_cbm'         => 'nullable|numeric',
            'port_of_loading'   => 'nullable|string|max:191',
            'place_of_delivery' => 'nullable|string|max:191',

            // 👇 PDF validate
            'bl_pdf'            => 'nullable|file|mimes:pdf|max:5120', // 5 MB
        ]);

        $pdfPath = null;

        // 👇 File handle
        if ($request->hasFile('bl_pdf')) {
            $file = $request->file('bl_pdf');

            // folder: public/import_bl_pdfs
            $folder = 'import_bl_pdfs';
            $destinationPath = public_path($folder);

            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $filename = uniqid('bl_') . '.' . $file->getClientOriginalExtension();
            $file->move($destinationPath, $filename);

            // sirf relative path store karenge
            $pdfPath = $folder . '/' . $filename;
        }

        BlDetail::create([
            'import_company_id' => $validated['import_company_id'],
            'supplier_id'       => null, // future me supplier link kar sakte ho
            'bl_no'             => $validated['bl_no'],
            'ob_date'           => $validated['ob_date'] ?? null,
            'vessel_name'       => $validated['vessel_name'] ?? null,
            'no_of_packages'    => $validated['no_of_packages'] ?? null,
            'gross_weight'      => $validated['gross_weight'] ?? null,
            'net_weight'        => $validated['net_weight'] ?? null,
            'gross_cbm'         => $validated['gross_cbm'] ?? null,
            'port_of_loading'   => $validated['port_of_loading'] ?? null,
            'place_of_delivery' => $validated['place_of_delivery'] ?? null,
            'pdf_path'          => $pdfPath, // 👈 yaha save ho raha hai
        ]);

        return redirect()
            ->route('import_bl_details.index')
            ->with('success', 'BL details added successfully.');
    }


    public function blDetailsIndex(Request $request)
    {
        $search = trim($request->input('search', ''));

        $query = BlDetail::with(['importCompany', 'supplier'])
            ->where('status', 'draft'); // ✅ sirf draft BL

        // 🔍 Search handling
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('bl_no', 'like', "%{$search}%")
                        ->orWhere('port_of_loading', 'like', "%{$search}%")
                        ->orWhere('place_of_delivery', 'like', "%{$search}%");
                })
                ->orWhereHas('importCompany', function ($cq) use ($search) {
                    $cq->where('company_name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%");
                })
                ->orWhereHas('supplier', function ($sq) use ($search) {
                    $sq->where('supplier_name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%");
                });
            });
        }

        $blDetails = $query
            ->orderByDesc('ob_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->appends($request->only('search'));

        return view('backend.imports.bl_details.index', compact('blDetails', 'search'));
    }


    /**
     * IMPORT PRODUCT LISTING for a given BL
     */
    public function importProductsList(Request $request, $bl_id)
    {
        $bl = BlDetail::with(['importCompany', 'supplier'])->findOrFail($bl_id);

        // Supplier dropdown options (yahi preload karenge)
        $suppliers = Supplier::orderBy('supplier_name')->get();

        // Product options ab AJAX se aayenge, isliye yaha se nahi bhej rahe
        return view('backend.imports.products.product_lising', compact(
            'bl',
            'suppliers'
        ));
    }

    

      /**
     * Product listing se Add to Cart
     */
    public function importProductsAddToCart(Request $request, $bl_id)
    {
        $bl = BlDetail::findOrFail($bl_id);
        $importCompanyId = $bl->import_company_id;

        $productIds           = (array) $request->input('product_id', []);
        $quantities           = (array) $request->input('quantity', []);
        $dollarPrices         = (array) $request->input('dollar_price', []);
        $importPrintNames     = (array) $request->input('import_print_name', []);
        $weightsPerCarton     = (array) $request->input('weight_per_carton', []);
        $cbmPerCarton         = (array) $request->input('cbm_per_carton', []);
        $qtyPerCarton         = (array) $request->input('quantity_per_carton', []);
        $supplierIds          = (array) $request->input('supplier_id', []);
        $supplierInvoiceNos   = (array) $request->input('supplier_invoice_no', []);
        $supplierInvoiceDates = (array) $request->input('supplier_invoice_date', []);
        $termsArr             = (array) $request->input('terms', []);

        // 🔹 import image IDs (modal se aata hai)
        $importPhotoIds       = (array) $request->input('import_photo_id', []);

        // (Optional) incoming totals – hum khud recompute bhi kar rahe hain
        $totalPackagesArr     = (array) $request->input('total_no_of_packages', []);
        $totalWeightArr       = (array) $request->input('total_weight', []);
        $totalCbmArr          = (array) $request->input('total_cbm', []);

        if (empty($productIds)) {
            return redirect()
                ->back()
                ->with('error', 'Please add at least one row with product.');
        }

        foreach ($productIds as $index => $productId) {
            if (empty($productId)) {
                continue;
            }

            $qty              = isset($quantities[$index]) ? (int) $quantities[$index] : 0;
            $dollarPrice      = $dollarPrices[$index]         ?? null;
            $impPrintName     = $importPrintNames[$index]     ?? null;
            $wtCarton         = $weightsPerCarton[$index]     ?? null;
            $cbmCarton        = $cbmPerCarton[$index]         ?? null;
            $qtyCarton        = $qtyPerCarton[$index]         ?? null;
            $supplierId       = $supplierIds[$index]          ?? null;
            $invNo            = $supplierInvoiceNos[$index]   ?? null;
            $invDate          = $supplierInvoiceDates[$index] ?? null;
            $terms            = $termsArr[$index]             ?? null;
            $importPhotoId    = $importPhotoIds[$index]       ?? null;   // 🔹 NEW

            if ($qty <= 0) {
                continue;
            }

            // 1️⃣ Totals calculate (ceil packages)
            $totalPackages = null;
            $totalWeight   = null;
            $totalCbm      = null;

            if (!empty($qtyCarton) && (float) $qtyCarton > 0 && $qty > 0) {
                // ✅ ALWAYS ROUND UP
                $totalPackages = (int) ceil($qty / (float) $qtyCarton);

                if (!empty($wtCarton)) {
                    $totalWeight = (float) $wtCarton * $totalPackages;
                }
                if (!empty($cbmCarton)) {
                    $totalCbm = (float) $cbmCarton * $totalPackages;
                }
            }

            // 2️⃣ PRODUCT TABLE UPDATE (master sync)
            $product = Product::find($productId);
            if ($product) {
                // basic import fields
                $product->import_print_name   = $impPrintName ?: $product->import_print_name;
                $product->weight_per_carton   = $wtCarton;
                $product->cbm_per_carton      = $cbmCarton;
                $product->quantity_per_carton = $qtyCarton;

                // dollar price save
                if ($dollarPrice !== null && $dollarPrice !== '') {
                    $product->dollar_price = (float) $dollarPrice;
                }

                // supplier
                if ($supplierId) {
                    $product->supplier_id = $supplierId;
                }

                // 🔹 IMAGE FIELDS: import_photos + import_thumbnail_img
                if (!empty($importPhotoId)) {
                    $product->import_photos = $importPhotoId;

                    // agar thumbnail blank hai ya aap hamesha same rakhna chahte ho to:
                    $product->import_thumbnail_img = $importPhotoId;
                    // agar sirf blank hone par set karna hai to:
                    // if (empty($product->import_thumbnail_img)) {
                    //     $product->import_thumbnail_img = $importPhotoId;
                    // }
                }

                $product->save();
            }

            // 3️⃣ IMPORT CART UPDATE / CREATE
            //  🔴 IMPORTANT CHANGE:
            //  Ab cart row uniqueness: (bl_detail_id, import_company_id, product_id, supplier_id)
            $cartRowQuery = ImportCart::where('bl_detail_id', $bl->id)
                ->where('import_company_id', $importCompanyId)
                ->where('product_id', $productId);

            if ($supplierId) {
                // specific supplier
                $cartRowQuery->where('supplier_id', $supplierId);
            } else {
                // rows jaha supplier_id NULL hai
                $cartRowQuery->whereNull('supplier_id');
            }

            $cartRow = $cartRowQuery->first();

            $data = [
                'bl_detail_id'          => $bl->id,
                'import_company_id'     => $importCompanyId,
                'product_id'            => $productId,
                'quantity'              => $qty,
                'dollar_price'          => $dollarPrice,
                'import_print_name'     => $impPrintName,
                'weight_per_carton'     => $wtCarton,
                'cbm_per_carton'        => $cbmCarton,
                'quantity_per_carton'   => $qtyCarton,
                'supplier_id'           => $supplierId,
                'supplier_invoice_no'   => $invNo,
                'supplier_invoice_date' => $invDate,
                'terms'                 => $terms,
                'total_no_of_packages'  => $totalPackages,
                'total_weight'          => $totalWeight,
                'total_cbm'             => $totalCbm,
                // 🔹 cart item par bhi image id save
                'import_photo_id'       => $importPhotoId,
            ];

            if ($cartRow) {
                // Same product + same supplier → update existing row
                $cartRow->update($data);
            } else {
                // Same product + different supplier → NEW row
                ImportCart::create($data);
            }
        }

        return redirect()
            ->route('import.cart.index', $bl->id)
            ->with('success', 'Products added/updated in cart for BL: ' . $bl->bl_no)
            ->with('scroll_to_cart', true);
    }

    public function ajaxSearchProducts(Request $request)
    {
        $q = trim($request->get('q', ''));

        if ($q === '') {
            return response()->json([]);
        }

        $products = Product::query()
            ->select('id', 'name', 'part_no')
            ->where(function ($query) use ($q) {
                $query->where('part_no', 'like', '%' . $q . '%')
                      ->orWhere('name', 'like', '%' . $q . '%');
            })
            ->orderBy('part_no')
            ->limit(20)
            ->get();

        return response()->json(
            $products->map(function ($p) {
                return [
                    'id'      => $p->id,
                    'name'    => $p->name,
                    'part_no' => $p->part_no,
                ];
            })
        );
    }

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
                'import_photos', // 👈 ID of Upload (image)
            ]);

        // Select2 expected format: { results: [ {id:..., text:..., extra...}, ... ] }
        $results = $products->map(function ($p) {
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
                // NEW: image info for auto-fill
                'import_photo_id'     => $p->import_photos,
                'import_photo_url'    => $p->import_photos
                                            ? uploaded_asset($p->import_photos)
                                            : null,
            ];
        });

        return response()->json([
            'results' => $results,
        ]);
    }


   public function ciIndex(Request $request)
    {
        $search = trim($request->input('search', ''));

        $ciHeaders = CiDetail::with([
                'importCompany',
                'supplier',
                'items.product',
                'bl', // BL relation so we can show bl_no
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('supplier_invoice_no', 'like', "%{$search}%")
                        ->orWhere('bl_id', 'like', "%{$search}%")
                        ->orWhereHas('importCompany', function ($q2) use ($search) {
                            $q2->where('company_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('supplier', function ($q3) use ($search) {
                            $q3->where('supplier_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(25);

        return view('backend.imports.ci.index', compact('ciHeaders', 'search'));
    }

    public function ciShow($ciId)
    {
        $ci = CiDetail::with([
            'importCompany',
            'supplier',
            'bl',
            'items.product',
        ])->findOrFail($ciId);

        return view('backend.imports.ci.show', compact('ci'));
    }




    /**
     * Pending BL listing (only BLs where status = 'pending')
     */
    public function pendingBlIndex(Request $request)
    {
        $search = trim($request->get('search'));

        $blHeaders = BlDetail::with([
                'importCompany',
                'supplier',
                'items.product',
            ])
            ->where('status', 'pending')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('bl_no', 'like', "%{$search}%")
                        ->orWhere('vessel_name', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($sq) use ($search) {
                            $sq->where('supplier_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('importCompany', function ($cq) use ($search) {
                            $cq->where('company_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->appends(['search' => $search]); // Keep search in pagination

        return view('backend.imports.bl_details.pending_index', compact('blHeaders', 'search'));
    }


    // ✅ NEW: show a single pending BL with items + upload button
    public function pendingBlShow($blId)
    {
        $bl = BlDetail::with([
                'importCompany',
                'supplier',
                'items.product',
            ])
            ->where('status', 'pending')   // ✅ sirf pending BL
            ->findOrFail($blId);

        return view('backend.imports.bl_details.pending_show', compact('bl'));
    }


    // ✅ NEW: upload Bill of Entry PDF
    public function uploadBillOfEntry(Request $request, $blId)
    {
        $bl = BlDetail::findOrFail($blId);

        $validated = $request->validate([
            'bill_of_entry_pdf' => 'required|file|mimes:pdf|max:5120', // 5MB
        ]);

        $file = $validated['bill_of_entry_pdf'];

        $folder = 'import_bill_of_entry_pdfs';
        $destinationPath = public_path($folder);

        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        // delete old file if exists
        if ($bl->bill_of_entry_pdf) {
            $oldFull = public_path($bl->bill_of_entry_pdf);
            if (File::exists($oldFull)) {
                File::delete($oldFull);
            }
        }

        $filename = uniqid('bill_of_entry_') . '.' . $file->getClientOriginalExtension();
        $file->move($destinationPath, $filename);

        $relativePath = $folder . '/' . $filename;

        $bl->bill_of_entry_pdf = $relativePath;
        $bl->status ='completed';
        $bl->save();

        return redirect()
            ->route('import_bl_details.completed')
            ->with('success', 'Bill of Entry PDF uploaded successfully.');
    }


    public function completedBlIndex(Request $request)
    {
        $search = trim($request->get('search'));

        $blHeaders = BlDetail::with([
                'importCompany',
                'supplier',
                'items.product',
            ])
            ->where('status', 'completed') // ✅ completed BL
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('bl_no', 'like', "%{$search}%")
                        ->orWhere('vessel_name', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($sq) use ($search) {
                            $sq->where('supplier_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('importCompany', function ($cq) use ($search) {
                            $cq->where('company_name', 'like', "%{$search}%");
                        });
                });
            })
            ->where('status', 'completed') // ✅ completed BL
            ->orderByDesc('ob_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->appends(['search' => $search]);

        return view('backend.imports.bl_details.completed_index', compact('blHeaders', 'search'));
    }

    public function completedBlShow($blId)
    {
        $bl = BlDetail::with([
                'importCompany',
                'supplier',
                'items.product',
            ])
            ->where('status', 'completed')   // ✅ सिर्फ completed BL allow
            ->findOrFail($blId);

        return view('backend.imports.bl_details.completed_show', compact('bl'));
    }
    
    public function downloadBlItemsPosterPdf($blId)
    {
        $bl = BlDetail::with('items')->findOrFail($blId);
    
        // Start with rows that have both fields
        $items = $bl->items
            ->filter(function ($row) {
                return !empty($row->item_name) && !empty($row->import_photo_id);
            })
            ->sortBy('id'); // optional, just to make "first" deterministic
    
        $seenNames  = [];
        $seenPhotos = [];
    
        // Keep only rows where BOTH name and photo are still unused
        $distinctRows = $items->filter(function ($row) use (&$seenNames, &$seenPhotos) {
            $nameKey  = Str::upper(trim($row->item_name));
            $photoKey = (string) $row->import_photo_id;
    
            if (in_array($nameKey, $seenNames, true)) {
                return false;
            }
            if (in_array($photoKey, $seenPhotos, true)) {
                return false;
            }
    
            $seenNames[]  = $nameKey;
            $seenPhotos[] = $photoKey;
    
            return true;
        });
    
        // Build final array for PDF
        $itemsForPdf = $distinctRows
            ->map(function ($row) {
                $name     = trim($row->item_name);
                $imageUrl = uploaded_asset($row->import_photo_id);
    
                return [
                    'name'      => $name,
                'image_url' => $imageUrl,
                ];
            })
            ->filter(function ($item) {
                // final safety: only keep items with a real URL
                return !empty($item['image_url']);
            })
            ->values(); // reindex 0..n-1
    
        // (Optional) quick debug – will log 6 if everything is correct:
        // \Log::info('Items for poster PDF', ['count' => $itemsForPdf->count(), 'items' => $itemsForPdf->pluck('name')]);
    
        $data = [
            'items' => $itemsForPdf,
        ];
    
        $fileName = 'BL-' . ($bl->bl_no ?? $bl->id) . '-items-poster.pdf';
    
        $pdf = Pdf::loadView('backend.imports.bl_details.items_poster_pdf', $data)
            ->setPaper('a4', 'portrait');
    
        return $pdf->download($fileName);
    }
    
    public function downloadBlItemsPosterWord($blId)
    {
        $bl = BlDetail::with('items')->findOrFail($blId);
    
        // 1) Only rows that have both item_name AND import_photo_id
        // 2) Make them distinct by BOTH name and photo
        $items = $bl->items
            ->filter(function ($row) {
                return !empty($row->item_name) && !empty($row->import_photo_id);
            })
            ->sortBy('id'); // optional, just to make "first" deterministic
    
        $seenNames  = [];
        $seenPhotos = [];
    
        $distinctRows = $items->filter(function ($row) use (&$seenNames, &$seenPhotos) {
            $nameKey  = Str::upper(trim($row->item_name));
            $photoKey = (string) $row->import_photo_id;
    
            if (in_array($nameKey, $seenNames, true)) {
                return false;
            }
            if (in_array($photoKey, $seenPhotos, true)) {
                return false;
            }
    
            $seenNames[]  = $nameKey;
            $seenPhotos[] = $photoKey;
    
            return true;
        });
    
        // Build final array for "Word" view
        $itemsForDoc = $distinctRows
            ->map(function ($row) {
                $name     = trim($row->item_name);
                $imageUrl = uploaded_asset($row->import_photo_id); // should be absolute URL
    
                return [
                    'name'      => $name,
                    'image_url' => $imageUrl,
                ];
            })
            ->filter(function ($item) {
                // only keep items with a real URL
                return !empty($item['image_url']);
            })
            ->values();
    
        $data = [
            'items' => $itemsForDoc,
        ];
    
        $fileName = 'BL-' . ($bl->bl_no ?? $bl->id) . '-items-poster.doc';
    
        // Render Blade as HTML and send as Word attachment
        $html = view('backend.imports.bl_details.items_poster_word', $data)->render();
    
        return response($html)
            ->header('Content-Type', 'application/msword')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    public function supplierEdit($id)
    {
        /** @var \App\Models\Supplier $supplier */
        $supplier = Supplier::with('bankAccounts')->findOrFail($id);
    
        // same as create
        $countries = Country::where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);
    
        return view('backend.imports.suppliers.edit', compact('supplier', 'countries'));
    }
    
    public function supplierUpdate(Request $request, $id)
    {
        /** @var \App\Models\Supplier $supplier */
        $supplier = Supplier::with('bankAccounts')->findOrFail($id);
    
        $validated = $request->validate([
            'supplier_name' => 'required|string|max:255',
            'address'       => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:100',
            'district'      => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'zip_code'      => 'nullable|string|max:20',
            'contact'       => 'nullable|string|max:50',
            'email'         => 'nullable|email|max:191',
    
            // stamp (optional)
            'stamp'         => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
    
            // bank accounts
            'bank_accounts'                                => 'nullable|array',
            'bank_accounts.*.currency'                     => 'nullable|string|max:10',
            'bank_accounts.*.intermediary_bank_name'       => 'nullable|string|max:191',
            'bank_accounts.*.intermediary_swift_code'      => 'nullable|string|max:50',
            'bank_accounts.*.account_bank_name'            => 'nullable|string|max:191',
            'bank_accounts.*.account_swift_code'           => 'nullable|string|max:50',
            'bank_accounts.*.account_bank_address'         => 'nullable|string',
            'bank_accounts.*.beneficiary_name'             => 'nullable|string|max:191',
            'bank_accounts.*.beneficiary_address'          => 'nullable|string',
            'bank_accounts.*.account_number'               => 'nullable|string|max:50',
            'bank_accounts.*.is_default'                   => 'nullable|boolean',
        ]);
    
        // handle stamp
        $stampPath = $supplier->stamp;
    
        if ($request->hasFile('stamp')) {
            $file   = $request->file('stamp');
            $folder = 'import_suppliers_stamps';
            $destinationPath = public_path($folder);
    
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
    
            // delete old file
            if ($stampPath) {
                $oldFullPath = public_path($stampPath);
                if (File::exists($oldFullPath)) {
                    File::delete($oldFullPath);
                }
            }
    
            $filename = uniqid('supplier_stamp_') . '.' . $file->getClientOriginalExtension();
            $file->move($destinationPath, $filename);
    
            $stampPath = $folder . '/' . $filename;
        }
    
        DB::transaction(function () use ($supplier, $validated, $stampPath) {
    
            // 1) update supplier basic info
            $supplier->update([
                'supplier_name' => $validated['supplier_name'],
                'address'       => $validated['address'] ?? null,
                'city'          => $validated['city'] ?? null,
                'district'      => $validated['district'] ?? null,
                'country'       => $validated['country'] ?? null,
                'zip_code'      => $validated['zip_code'] ?? null,
                'contact'       => $validated['contact'] ?? null,
                'email'         => $validated['email'] ?? null,
                'stamp'         => $stampPath,
            ]);
    
            // 2) bank accounts – easiest is: delete old + recreate
            $supplier->bankAccounts()->delete();
    
            if (!empty($validated['bank_accounts']) && is_array($validated['bank_accounts'])) {
                $hasDefault = false;
    
                foreach ($validated['bank_accounts'] as $bankAccount) {
    
                    // skip fully empty row
                    $fieldsToCheck = [
                        'currency',
                        'intermediary_bank_name',
                        'intermediary_swift_code',
                        'account_bank_name',
                        'account_swift_code',
                        'account_bank_address',
                        'beneficiary_name',
                        'beneficiary_address',
                        'account_number',
                    ];
    
                    $allEmpty = true;
                    foreach ($fieldsToCheck as $field) {
                        if (!empty($bankAccount[$field])) {
                            $allEmpty = false;
                            break;
                        }
                    }
    
                    if ($allEmpty) {
                        continue;
                    }
    
                    $isDefault = !empty($bankAccount['is_default']) && !$hasDefault;
                    if ($isDefault) {
                        $hasDefault = true;
                    }
    
                    SupplierBankAccount::create([
                        'supplier_id'              => $supplier->id,
                        'currency'                 => $bankAccount['currency'] ?? 'USD',
                        'intermediary_bank_name'   => $bankAccount['intermediary_bank_name'] ?? null,
                        'intermediary_swift_code'  => $bankAccount['intermediary_swift_code'] ?? null,
                        'account_bank_name'        => $bankAccount['account_bank_name'] ?? null,
                        'account_swift_code'       => $bankAccount['account_swift_code'] ?? null,
                        'account_bank_address'     => $bankAccount['account_bank_address'] ?? null,
                        'beneficiary_name'         => $bankAccount['beneficiary_name'] ?? null,
                        'beneficiary_address'      => $bankAccount['beneficiary_address'] ?? null,
                        'account_number'           => $bankAccount['account_number'] ?? null,
                        'is_default'               => $isDefault ? 1 : 0,
                    ]);
                }
            }
        });
    
        return redirect()
            ->route('import_suppliers.index')
            ->with('success', 'Supplier updated successfully.');
    }
}